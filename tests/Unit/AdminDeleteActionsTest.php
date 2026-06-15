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
            '/admin/jobs/1/delete',
            '/admin/reports/1/delete',
            '/admin/transfers/1/delete',
            '/admin/woocommerce/1/delete',
            '/admin/woocommerce/1/webhooks/2/delete',
            '/admin/woocommerce/1/exports/2/delete',
            '/admin/mappings/1/delete',
            '/admin/mappings/1/fields/2/sort-order',
            '/admin/endpoints/1/delete',
        ] as $path) {
            self::assertNull($routes->match(new \Luna\Http\Request('GET', $path)), $path);
            self::assertNotNull($routes->match(new \Luna\Http\Request('POST', $path)), $path);
        }
    }

    public function testAffectedAdminViewsPostToExistingDeleteRoutes(): void
    {
        $basePath = dirname(__DIR__, 2);
        $forms = [
            'resources/views/admin/jobs/index.php' => '/admin/jobs/',
            'resources/views/admin/jobs/show.php' => '/delete',
            'resources/views/admin/reports/index.php' => '/admin/reports/',
            'resources/views/admin/reports/show.php' => '/delete',
            'resources/views/admin/connections/index.php' => '/admin/connections/',
            'resources/views/admin/connections/show.php' => '/delete',
            'resources/views/admin/transfers/index.php' => '/admin/transfers/',
            'resources/views/admin/transfers/show.php' => '/delete',
            'resources/views/admin/woocommerce/index.php' => '/admin/woocommerce/',
            'resources/views/admin/woocommerce/show.php' => '/delete',
        ];

        foreach ($forms as $file => $routeFragment) {
            $contents = file_get_contents($basePath . '/' . $file);
            self::assertIsString($contents);
            self::assertStringContainsString('method="post"', $contents, $file);
            self::assertStringContainsString($routeFragment, $contents, $file);
            self::assertStringContainsString('confirm_delete', $contents, $file);
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
            'status' => 'active',
            'mapping_mode' => 'json_endpoint',
            'source_connection_id' => 1,
            'target_connection_id' => '',
        ]);

        self::assertSame([], $errors);
    }

    public function testMappingFieldSortOrderCanBeUpdated(): void
    {
        $pdo = $this->pdo();
        $repo = new MappingRepository($this->systemDatabase(), $pdo);
        $pdo->exec("INSERT INTO luna_mapping_sets (id, workspace_id, name) VALUES (10, 1, 'ISR Mapping')");
        $pdo->exec("INSERT INTO luna_mapping_fields (id, mapping_set_id, source_column, target_column, transform_type, sort_order) VALUES (99, 10, 'stock_lookup_model', 'dr_quantities', 'key_value_map_by_prefix', 30)");

        $repo->updateFieldSortOrder(99, -5);

        self::assertSame(-5, (int) $pdo->query('SELECT sort_order FROM luna_mapping_fields WHERE id = 99')->fetchColumn());
    }

    public function testEndpointMappingSummaryShowsFiltersFieldsOrderAndRules(): void
    {
        $this->loadWebRoutes();
        $pdo = $this->pdo();
        $pdo->exec("INSERT INTO luna_connection_profiles (id, workspace_id, name) VALUES (1, 1, 'PIMCORE-Objects')");
        $pdo->exec("INSERT INTO luna_connection_profiles (id, workspace_id, name) VALUES (2, 1, 'PIMCORE-Settings')");
        $pdo->exec("INSERT INTO luna_mapping_sets (id, workspace_id, name, mapping_mode, source_connection_id, source_table, status) VALUES (10, 1, 'ISR Prices v2', 'json_endpoint', 1, 'object_query_1', 'active')");
        $pdo->exec("INSERT INTO luna_mapping_source_filters (id, mapping_set_id, source_column, operator, filter_value, sort_order) VALUES (1, 10, 'priceGroup', 'numeric_greater_than', '0', 0)");
        $pdo->exec("INSERT INTO luna_mapping_fields (id, mapping_set_id, source_column, target_column, transform_type, lookup_connection_id, lookup_table, lookup_key_column, lookup_value_column, lookup_key_template, missing_behavior, sort_order) VALUES (99, 10, 'priceGroup', 'price', 'lookup_value', 2, 'zweipunkt_setting', 'name', 'value', 'pricegroup_{{price_group}}', 'nullable', 4)");
        $pdo->exec("INSERT INTO luna_mapping_value_rules (id, mapping_field_id, source_value, target_value) VALUES (7, 99, 'aktiv', 'active')");

        $summary = \endpointMappingSummary(
            ['mapping_set_id' => 10],
            fn (): MappingRepository => new MappingRepository($this->systemDatabase(), $pdo),
            fn (): ConnectionProfileRepository => new ConnectionProfileRepository($this->systemDatabase(), new EncryptionService(new Config()), $pdo),
        );

        self::assertNull($summary['message']);
        self::assertSame('ISR Prices v2', $summary['mapping']['name']);
        self::assertSame('PIMCORE-Objects', $summary['mapping']['source_connection_name']);
        self::assertSame('numeric_greater_than', $summary['filters'][0]['operator']);
        self::assertSame(4, $summary['fields'][0]['sort_order']);
        self::assertSame('price', $summary['fields'][0]['target_column']);
        self::assertSame('PIMCORE-Settings', $summary['fields'][0]['lookup_connection']);
        self::assertSame('pricegroup_{{price_group}}', $summary['fields'][0]['lookup_key_template']);
        self::assertSame('aktiv', $summary['fields'][0]['value_rules'][0]['source_value']);
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
        $pdo->exec('CREATE TABLE luna_mapping_sets (id INTEGER PRIMARY KEY, workspace_id INTEGER NULL, name TEXT, description TEXT NULL, mapping_mode TEXT DEFAULT "transfer", source_connection_id INTEGER NULL, source_table TEXT NULL, target_connection_id INTEGER NULL, target_table TEXT NULL, status TEXT DEFAULT "draft")');
        $pdo->exec('CREATE TABLE luna_mapping_fields (id INTEGER PRIMARY KEY, mapping_set_id INTEGER, source_column TEXT NULL, source_json_path TEXT NULL, target_column TEXT, transform_type TEXT NULL, default_value TEXT NULL, lookup_connection_id INTEGER NULL, lookup_table TEXT NULL, lookup_key_column TEXT NULL, lookup_value_column TEXT NULL, lookup_key_template TEXT NULL, fallback_value TEXT NULL, missing_behavior TEXT NULL, sort_order INTEGER DEFAULT 0)');
        $pdo->exec('CREATE TABLE luna_mapping_value_rules (id INTEGER PRIMARY KEY, mapping_field_id INTEGER, source_value TEXT, target_value TEXT, notes TEXT NULL)');
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
