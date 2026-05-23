<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Config\Config;
use Luna\Core\Application;
use Luna\Core\Paths;
use Luna\Http\Request;
use Luna\Http\Response;
use Luna\Routing\RouteCollection;
use Luna\Schema\TableNameReader;
use PDO;
use PHPUnit\Framework\TestCase;

final class SchemaTablesRouteTest extends TestCase
{
    public function testConnectionTablesAdminApiRouteIsRegisteredAtRuntime(): void
    {
        $routes = $this->loadWebRoutes();
        $request = new Request('GET', '/admin/api/connection-tables', ['connection_id' => '3']);
        $route = $routes->match($request);

        self::assertNotNull($route);
        self::assertSame('admin.api.connection_tables', $route->name());
        self::assertSame([], $route->parameters($request));
    }

    public function testLegacySchemaTablesJsonRouteIsRegisteredAtRuntime(): void
    {
        $routes = $this->loadWebRoutes();
        $request = new Request('GET', '/admin/schema/3/tables.json');
        $route = $routes->match($request);

        self::assertNotNull($route);
        self::assertSame('admin.schema.tables_json', $route->name());
        self::assertSame(['connectionId' => '3'], $route->parameters($request));
    }

    public function testConnectionTablesHandlerReturnsOnlyTableNames(): void
    {
        $this->loadWebRoutes();

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE table_b (id INTEGER PRIMARY KEY, sample TEXT)');
        $pdo->exec('CREATE TABLE table_a (id INTEGER PRIMARY KEY, sample TEXT)');

        $connections = static fn (): object => new class {
            public function find(int $id): ?array
            {
                return $id === 3 ? ['id' => 3, 'driver' => 'sqlite'] : null;
            }
        };
        $pdoFactory = static fn (): object => new class ($pdo) {
            public function __construct(private readonly PDO $pdo)
            {
            }

            public function create(mixed $config): PDO
            {
                return $this->pdo;
            }
        };
        $configFor = static fn (array $profile): array => $profile;

        $response = \connectionTablesJsonResponse($connections, $pdoFactory, $configFor, 3);
        $body = $this->jsonBody($response);

        self::assertSame(200, $response->statusCode());
        self::assertSame('application/json; charset=UTF-8', $response->headers()['Content-Type'] ?? null);
        self::assertSame(true, $body['success'] ?? null);
        self::assertSame(3, $body['connection_id'] ?? null);
        self::assertSame([['name' => 'table_a'], ['name' => 'table_b']], $body['tables'] ?? null);

        foreach ($body['tables'] as $table) {
            self::assertSame(['name'], array_keys($table));
        }
    }

    public function testTableNameReaderLoadsOnlyNames(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE z_table (id INTEGER PRIMARY KEY, sample TEXT)');
        $pdo->exec('CREATE TABLE a_table (id INTEGER PRIMARY KEY, sample TEXT)');

        self::assertSame(
            [['name' => 'a_table'], ['name' => 'z_table']],
            (new TableNameReader($pdo))->tableNames(),
        );
    }

    public function testConnectionTablesRoutesAreRegisteredBeforeGenericSchemaRoute(): void
    {
        $routeNames = array_map(
            static fn ($route): ?string => $route->name(),
            $this->loadWebRoutes()->all(),
        );

        $apiIndex = array_search('admin.api.connection_tables', $routeNames, true);
        $legacyIndex = array_search('admin.schema.tables_json', $routeNames, true);
        $schemaTableIndex = array_search('admin.schema.table', $routeNames, true);
        $schemaTableNoteIndex = array_search('admin.schema.table_note', $routeNames, true);
        $schemaColumnNoteIndex = array_search('admin.schema.column_note', $routeNames, true);
        $schemaIndex = array_search('admin.schema.connection', $routeNames, true);

        self::assertIsInt($apiIndex);
        self::assertIsInt($legacyIndex);
        self::assertIsInt($schemaTableIndex);
        self::assertIsInt($schemaTableNoteIndex);
        self::assertIsInt($schemaColumnNoteIndex);
        self::assertIsInt($schemaIndex);
        self::assertLessThan($schemaIndex, $apiIndex);
        self::assertLessThan($schemaIndex, $legacyIndex);
        self::assertLessThan($schemaIndex, $schemaTableIndex);
        self::assertLessThan($schemaIndex, $schemaTableNoteIndex);
        self::assertLessThan($schemaIndex, $schemaColumnNoteIndex);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonBody(Response $response): array
    {
        $decoded = json_decode($response->body(), true);

        self::assertIsArray($decoded);

        return $decoded;
    }

    private function loadWebRoutes(): RouteCollection
    {
        $basePath = dirname(__DIR__, 2);
        $app = new Application(new Paths($basePath), new Config());
        $routes = new RouteCollection();
        $loader = require $basePath . '/routes/web.php';

        $loader($routes, $app);

        return $routes;
    }
}
