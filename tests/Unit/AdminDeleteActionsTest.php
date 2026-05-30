<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Config\Config;
use Luna\Core\Application;
use Luna\Core\Paths;
use Luna\Database\DatabaseConfig;
use Luna\Database\PdoConnectionFactory;
use Luna\Database\SystemDatabase;
use Luna\Repository\ConnectionProfileRepository;
use Luna\Repository\EndpointRepository;
use Luna\Repository\MappingRepository;
use Luna\Repository\WorkspaceRepository;
use Luna\Routing\RouteCollection;
use Luna\Security\EncryptionService;
use PDO;
use PHPUnit\Framework\TestCase;

final class AdminDeleteActionsTest extends TestCase
{
    public function testEndpointCanBeDeleted(): void
    {
        $pdo = $this->pdo();
        $repo = new EndpointRepository($this->systemDatabase(), new EncryptionService(new Config()), $pdo);
        $pdo->exec("INSERT INTO luna_endpoints (id, workspace_id, name, endpoint_key, mapping_set_id) VALUES (1, 1, 'ISR', 'isr_prices', 10)");
        $pdo->exec("INSERT INTO luna_endpoint_secrets (endpoint_id, secret_key, secret_value_encrypted) VALUES (1, 'secret', 'encrypted')");

        $repo->delete(1);

        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM luna_endpoints')->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM luna_endpoint_secrets')->fetchColumn());
    }

    public function testMappingCannotBeDeletedWhenEndpointReferencesIt(): void
    {
        $pdo = $this->pdo();
        $repo = new MappingRepository($this->systemDatabase(), $pdo);
        $pdo->exec("INSERT INTO luna_mapping_sets (id, workspace_id, name) VALUES (10, 1, 'ISR Mapping')");
        $pdo->exec("INSERT INTO luna_endpoints (id, workspace_id, name, endpoint_key, mapping_set_id) VALUES (1, 1, 'ISR', 'isr_prices', 10)");

        $check = $repo->canDeleteSet(10);

        self::assertFalse($check->allowed);
        self::assertStringContainsString('Endpoint', $check->message);
        self::assertSame(['ISR'], $check->blockingNames);
    }

    public function testMappingCanBeDeletedWhenUnused(): void
    {
        $pdo = $this->pdo();
        $repo = new MappingRepository($this->systemDatabase(), $pdo);
        $pdo->exec("INSERT INTO luna_mapping_sets (id, workspace_id, name) VALUES (10, 1, 'ISR Mapping')");
        $pdo->exec("INSERT INTO luna_mapping_fields (id, mapping_set_id, target_column) VALUES (99, 10, 'price')");
        $pdo->exec("INSERT INTO luna_mapping_value_rules (mapping_field_id, source_value, target_value) VALUES (99, '1', '2')");

        self::assertTrue($repo->canDeleteSet(10)->allowed);
        $repo->deleteSet(10);

        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM luna_mapping_sets')->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM luna_mapping_fields')->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM luna_mapping_value_rules')->fetchColumn());
    }

    public function testConnectionCannotBeDeletedWhenMappingUsesIt(): void
    {
        $pdo = $this->pdo();
        $repo = new ConnectionProfileRepository($this->systemDatabase(), new EncryptionService(new Config()), $pdo);
        $pdo->exec("INSERT INTO luna_connection_profiles (id, workspace_id, name) VALUES (2, 1, 'PIMCORE')");
        $pdo->exec("INSERT INTO luna_mapping_sets (id, workspace_id, name, source_connection_id) VALUES (10, 1, 'ISR Mapping', 2)");

        $check = $repo->canDelete(2);

        self::assertFalse($check->allowed);
        self::assertStringContainsString('Mapping', $check->message);
        self::assertSame(['ISR Mapping'], $check->blockingNames);
    }

    public function testConnectionCannotBeDeletedWhenMappingFieldUsesItAsLookupConnection(): void
    {
        $pdo = $this->pdo();
        $repo = new ConnectionProfileRepository($this->systemDatabase(), new EncryptionService(new Config()), $pdo);
        $pdo->exec("INSERT INTO luna_connection_profiles (id, workspace_id, name) VALUES (3, 1, 'PIMCORE-Settings')");
        $pdo->exec("INSERT INTO luna_mapping_sets (id, workspace_id, name) VALUES (10, 1, 'ISR Mapping')");
        $pdo->exec("INSERT INTO luna_mapping_fields (id, mapping_set_id, target_column, lookup_connection_id) VALUES (99, 10, 'price', 3)");

        $check = $repo->canDelete(3);

        self::assertFalse($check->allowed);
        self::assertSame(['ISR Mapping'], $check->blockingNames);
    }

    public function testConnectionCanBeDeletedWhenUnused(): void
    {
        $pdo = $this->pdo();
        $repo = new ConnectionProfileRepository($this->systemDatabase(), new EncryptionService(new Config()), $pdo);
        $pdo->exec("INSERT INTO luna_connection_profiles (id, workspace_id, name) VALUES (2, 1, 'PIMCORE')");
        $pdo->exec("INSERT INTO luna_connection_secrets (connection_profile_id, secret_key, secret_value_encrypted) VALUES (2, 'password', 'encrypted-secret')");

        self::assertTrue($repo->canDelete(2)->allowed);
        $repo->delete(2);

        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM luna_connection_profiles')->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM luna_connection_secrets')->fetchColumn());
    }

    public function testWorkspaceCannotBeDeletedWhenNotEmpty(): void
    {
        $pdo = $this->pdo();
        $repo = new WorkspaceRepository($this->systemDatabase(), $pdo);
        $pdo->exec("INSERT INTO luna_workspaces (id, slug, name) VALUES (1, 'isr', 'ISR')");
        $pdo->exec("INSERT INTO luna_connection_profiles (id, workspace_id, name) VALUES (2, 1, 'PIMCORE')");

        $check = $repo->canDelete(1);

        self::assertFalse($check->allowed);
        self::assertStringContainsString('Connections', $check->message);
    }

    public function testWorkspaceCanBeDeletedWhenEmpty(): void
    {
        $pdo = $this->pdo();
        $repo = new WorkspaceRepository($this->systemDatabase(), $pdo);
        $pdo->exec("INSERT INTO luna_workspaces (id, slug, name) VALUES (1, 'isr', 'ISR')");

        self::assertTrue($repo->canDelete(1)->allowed);
        $repo->delete(1);

        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM luna_workspaces')->fetchColumn());
    }

    public function testDeleteRoutesArePostOnly(): void
    {
        $routes = $this->loadWebRoutes();

        foreach ([
            '/admin/workspaces/1/delete',
            '/admin/connections/1/delete',
            '/admin/mappings/1/delete',
            '/admin/endpoints/1/delete',
        ] as $path) {
            self::assertNull($routes->match(new \Luna\Http\Request('GET', $path)), $path);
            self::assertNotNull($routes->match(new \Luna\Http\Request('POST', $path)), $path);
        }
    }

    public function testTransferMappingRequiresSourceAndTargetConnection(): void
    {
        $this->loadWebRoutes();

        $errors = \mappingSetErrors([
            'name' => 'Transfer Mapping',
            'status' => 'draft',
            'mapping_mode' => 'transfer',
            'source_connection_id' => 1,
            'target_connection_id' => '',
        ]);

        self::assertContains('Target Connection ist für Transfer-Mappings erforderlich.', $errors);
    }

    public function testJsonEndpointMappingRequiresSourceButNoTargetConnection(): void
    {
        $this->loadWebRoutes();

        $errors = \mappingSetErrors([
            'name' => 'JSON Mapping',
            'status' => 'draft',
            'mapping_mode' => 'json_endpoint',
            'source_connection_id' => 1,
            'target_connection_id' => '',
        ]);

        self::assertSame([], $errors);
    }

    public function testMappingFieldWithoutOutputFieldIsRejected(): void
    {
        $this->loadWebRoutes();
        $pdo = $this->pdo();
        $connections = new ConnectionProfileRepository($this->systemDatabase(), new EncryptionService(new Config()), $pdo);

        $errors = \mappingFieldErrors(['id' => 10, 'workspace_id' => 1], [
            'target_column' => '',
            'transform_type' => 'direct',
        ], $connections);

        self::assertSame(['Ausgabe-Feld ist erforderlich.'], $errors);
    }

    public function testMappingFieldOutputFieldRejectsInvalidCharacters(): void
    {
        $this->loadWebRoutes();
        $pdo = $this->pdo();
        $connections = new ConnectionProfileRepository($this->systemDatabase(), new EncryptionService(new Config()), $pdo);

        $errors = \mappingFieldErrors(['id' => 10, 'workspace_id' => 1], [
            'target_column' => 'price.group',
            'transform_type' => 'direct',
        ], $connections);

        self::assertSame(['Ausgabe-Feld darf nur Buchstaben, Zahlen und Unterstriche enthalten.'], $errors);
    }

    public function testDeleteMessagesDoNotExposeSecretsDsnOrStacktrace(): void
    {
        $this->loadWebRoutes();
        $message = \deleteBlockedMessage('Diese Connection kann nicht gelöscht werden, weil sie noch von Mapping(s) verwendet wird.', [
            'ISR Mapping',
        ]);

        self::assertStringNotContainsString('encrypted-secret', $message);
        self::assertStringNotContainsString('mysql://', $message);
        self::assertStringNotContainsString('Stack trace', $message);
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE luna_workspaces (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, workspace_id INTEGER NULL)');
        $pdo->exec('CREATE TABLE luna_connection_profiles (id INTEGER PRIMARY KEY, workspace_id INTEGER NULL, name TEXT)');
        $pdo->exec('CREATE TABLE luna_connection_secrets (connection_profile_id INTEGER, secret_key TEXT, secret_value_encrypted TEXT)');
        $pdo->exec('CREATE TABLE luna_schema_snapshots (id INTEGER PRIMARY KEY, connection_profile_id INTEGER)');
        $pdo->exec('CREATE TABLE luna_table_notes (id INTEGER PRIMARY KEY, connection_profile_id INTEGER)');
        $pdo->exec('CREATE TABLE luna_column_notes (id INTEGER PRIMARY KEY, connection_profile_id INTEGER)');
        $pdo->exec('CREATE TABLE luna_mapping_sets (id INTEGER PRIMARY KEY, workspace_id INTEGER NULL, name TEXT, mapping_mode TEXT DEFAULT "transfer", source_connection_id INTEGER NULL, target_connection_id INTEGER NULL)');
        $pdo->exec('CREATE TABLE luna_mapping_fields (id INTEGER PRIMARY KEY, mapping_set_id INTEGER, target_column TEXT, lookup_connection_id INTEGER NULL)');
        $pdo->exec('CREATE TABLE luna_mapping_value_rules (id INTEGER PRIMARY KEY, mapping_field_id INTEGER, source_value TEXT, target_value TEXT)');
        $pdo->exec('CREATE TABLE luna_mapping_source_filters (id INTEGER PRIMARY KEY, mapping_set_id INTEGER, source_column TEXT, operator TEXT, filter_value TEXT, value_type TEXT NULL, sort_order INTEGER)');
        $pdo->exec('CREATE TABLE luna_endpoints (id INTEGER PRIMARY KEY, workspace_id INTEGER NULL, name TEXT, endpoint_key TEXT, mapping_set_id INTEGER NULL)');
        $pdo->exec('CREATE TABLE luna_endpoint_secrets (endpoint_id INTEGER, secret_key TEXT, secret_value_encrypted TEXT)');
        $pdo->exec('CREATE TABLE luna_jobs (id INTEGER PRIMARY KEY, name TEXT, mapping_set_id INTEGER NULL)');

        return $pdo;
    }

    private function systemDatabase(): SystemDatabase
    {
        return new SystemDatabase(new DatabaseConfig(new Config()), new PdoConnectionFactory());
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
