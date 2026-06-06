<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Config\Config;
use Luna\Database\DatabaseConfig;
use Luna\Database\PdoConnectionFactory;
use Luna\Database\SystemDatabase;
use Luna\Deployment\DeploymentTargetUrlBuilder;
use Luna\Export\EndpointExportContractService;
use Luna\Export\EndpointExportSanitizer;
use Luna\Export\EndpointSchemaBuilder;
use Luna\Repository\ConnectionProfileRepository;
use Luna\Repository\DeploymentTargetRepository;
use Luna\Repository\EndpointRepository;
use Luna\Repository\MappingRepository;
use Luna\Repository\WorkspaceRepository;
use Luna\Security\EncryptionService;
use PDO;
use PHPUnit\Framework\TestCase;

final class EndpointExportContractServiceTest extends TestCase
{
    public function testExportCreatesRequiredFilesWithoutSecrets(): void
    {
        $target = $this->tempDirectory();
        $result = $this->service()->exportEndpoint(5, null, $target);
        $path = (string) $result['absolute_target_path'];

        foreach (['manifest.json', 'schema.json', 'endpoint.json', 'mapping.json', 'README.md', 'checksums.json'] as $file) {
            self::assertFileExists($path . '/' . $file);
        }

        $manifest = json_decode((string) file_get_contents($path . '/manifest.json'), true);
        $schema = json_decode((string) file_get_contents($path . '/schema.json'), true);
        $mapping = json_decode((string) file_get_contents($path . '/mapping.json'), true);
        $checksums = json_decode((string) file_get_contents($path . '/checksums.json'), true);
        $exported = $this->readExport($path);

        self::assertSame('endpoint', $manifest['artifact_type']);
        self::assertSame('isr_prices', $manifest['endpoint']['slug']);
        self::assertSame('ISR Prices Export', $manifest['mapping']['name']);
        self::assertFalse($manifest['security']['contains_secrets']);
        self::assertNull($manifest['target']);
        self::assertSame('number', $schema['properties']['items']['items']['properties']['price']['type']);
        self::assertSame('object', $schema['properties']['items']['items']['properties']['dr_quantities']['type']);
        self::assertSame('PIMCORE-Objects', $mapping['source']['connection_ref']['name']);
        self::assertTrue($mapping['source']['connection_ref']['secret_free']);
        self::assertArrayHasKey('manifest.json', $checksums);
        self::assertStringNotContainsString('plain-secret', $exported);
        self::assertStringNotContainsString('objects-password', $exported);
        self::assertStringNotContainsString('objects_user', $exported);
        self::assertStringNotContainsString('mysql://', $exported);
    }

    public function testExportWithProductionTargetAddsEndpointUrl(): void
    {
        $target = $this->tempDirectory();
        $result = $this->service(true)->exportEndpoint(5, 'production', $target);
        $path = (string) $result['absolute_target_path'];
        $manifest = json_decode((string) file_get_contents($path . '/manifest.json'), true);

        self::assertSame('production', $manifest['target']['environment']);
        self::assertSame('https://toolbox.example.com/luna/api/endpoints/isr_prices', $manifest['target']['endpoint_url']);
    }

    private function service(bool $withDeploymentTarget = false): EndpointExportContractService
    {
        $_ENV['APP_KEY'] = 'unit-test-app-key';
        $_SERVER['APP_KEY'] = 'unit-test-app-key';
        $pdo = $this->pdo($withDeploymentTarget);
        $database = new SystemDatabase(new DatabaseConfig(new Config()), new PdoConnectionFactory());
        $encryption = new EncryptionService(new Config());
        $urlBuilder = new DeploymentTargetUrlBuilder();

        return new EndpointExportContractService(
            new EndpointRepository($database, $encryption, $pdo),
            new MappingRepository($database, $pdo),
            new ConnectionProfileRepository($database, $encryption, $pdo),
            new WorkspaceRepository($database, $pdo),
            new DeploymentTargetRepository($database, $urlBuilder, $pdo),
            $urlBuilder,
            new EndpointSchemaBuilder(),
            new EndpointExportSanitizer(),
            $this->tempDirectory(),
        );
    }

    private function pdo(bool $withDeploymentTarget): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE luna_workspaces (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)');
        $pdo->exec('CREATE TABLE luna_connection_profiles (id INTEGER PRIMARY KEY, workspace_id INTEGER, name TEXT, type TEXT, driver TEXT, host TEXT, port INTEGER, database_name TEXT, username TEXT, read_only INTEGER)');
        $pdo->exec('CREATE TABLE luna_jobs (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec('CREATE TABLE luna_endpoints (id INTEGER PRIMARY KEY, workspace_id INTEGER, name TEXT, endpoint_key TEXT, method TEXT, status TEXT, secret_mode TEXT, secret_hash TEXT, source_type TEXT, mapping_set_id INTEGER, job_id INTEGER, config_json TEXT, cache_enabled INTEGER, cache_ttl_seconds INTEGER)');
        $pdo->exec('CREATE TABLE luna_endpoint_secrets (endpoint_id INTEGER, secret_key TEXT, secret_value_encrypted TEXT)');
        $pdo->exec('CREATE TABLE luna_mapping_sets (id INTEGER PRIMARY KEY, workspace_id INTEGER, name TEXT, mapping_mode TEXT, source_connection_id INTEGER, source_table TEXT, target_connection_id INTEGER, target_table TEXT)');
        $pdo->exec('CREATE TABLE luna_mapping_fields (id INTEGER PRIMARY KEY, mapping_set_id INTEGER, source_column TEXT, target_column TEXT, transform_type TEXT, default_value TEXT, lookup_connection_id INTEGER, lookup_table TEXT, lookup_key_column TEXT, lookup_value_column TEXT, lookup_key_template TEXT, lookup_result_mode TEXT, fallback_value TEXT, missing_behavior TEXT, notes TEXT, schema_type TEXT, schema_required INTEGER, schema_description TEXT, schema_example TEXT, sort_order INTEGER)');
        $pdo->exec('CREATE TABLE luna_mapping_source_filters (id INTEGER PRIMARY KEY, mapping_set_id INTEGER, source_column TEXT, operator TEXT, filter_value TEXT, sort_order INTEGER)');
        $pdo->exec('CREATE TABLE luna_deployment_targets (id INTEGER PRIMARY KEY AUTOINCREMENT, workspace_id INTEGER NULL, name TEXT, environment TEXT, public_base_url TEXT, endpoint_base_url TEXT NULL, webhook_base_url TEXT NULL, license_server_url TEXT NULL, is_default INTEGER, is_active INTEGER, origin TEXT, support_status TEXT, module_key TEXT NULL, requires_entitlement INTEGER, created_at TEXT, updated_at TEXT)');

        $pdo->exec("INSERT INTO luna_workspaces (id, slug, name) VALUES (1, 'asfinstocks', 'AsfInstocks')");
        $pdo->exec("INSERT INTO luna_connection_profiles (id, workspace_id, name, type, driver, host, port, database_name, username, read_only) VALUES (1, 1, 'PIMCORE-Objects', 'database', 'mysql', 'objects.local', 3307, 'objects_db', 'objects_user', 1)");
        $pdo->exec("INSERT INTO luna_connection_profiles (id, workspace_id, name, type, driver, host, port, database_name, username, read_only) VALUES (2, 1, 'PIMCORE-Settings', 'database', 'mysql', 'settings.local', 3308, 'settings_db', 'settings_user', 1)");
        $pdo->exec("INSERT INTO luna_endpoints (id, workspace_id, name, endpoint_key, method, status, secret_mode, secret_hash, source_type, mapping_set_id, job_id, config_json, cache_enabled, cache_ttl_seconds) VALUES (5, 1, 'ISR Prices', 'isr_prices', 'GET', 'active', 'required', 'hashed-secret', 'mapping', 33, NULL, '', 0, NULL)");
        $pdo->exec("INSERT INTO luna_mapping_sets (id, workspace_id, name, mapping_mode, source_connection_id, source_table, target_connection_id, target_table) VALUES (33, 1, 'ISR Prices Export', 'json_endpoint', 1, 'object_query_1', NULL, NULL)");
        $pdo->exec("INSERT INTO luna_mapping_source_filters (id, mapping_set_id, source_column, operator, filter_value, sort_order) VALUES (1, 33, 'priceGroup', 'numeric_greater_than', '0', 0)");
        $pdo->exec("INSERT INTO luna_mapping_fields (id, mapping_set_id, source_column, target_column, transform_type, lookup_connection_id, lookup_table, lookup_key_column, lookup_value_column, lookup_key_template, lookup_result_mode, missing_behavior, schema_type, schema_required, sort_order) VALUES (1, 33, 'old_name,customfield_asf_model', 'model', 'first_non_empty', NULL, NULL, NULL, NULL, NULL, NULL, 'nullable', 'string', 1, 0)");
        $pdo->exec("INSERT INTO luna_mapping_fields (id, mapping_set_id, source_column, target_column, transform_type, lookup_connection_id, lookup_table, lookup_key_column, lookup_value_column, lookup_key_template, lookup_result_mode, missing_behavior, schema_type, schema_required, sort_order) VALUES (2, 33, 'priceGroup', 'price', 'lookup_value', 2, 'zweipunkt_setting', 'name', 'value', 'pricegroup_{{price_group}}', NULL, 'nullable', 'number', 0, 1)");
        $pdo->exec("INSERT INTO luna_mapping_fields (id, mapping_set_id, source_column, target_column, transform_type, lookup_connection_id, lookup_table, lookup_key_column, lookup_value_column, lookup_key_template, lookup_result_mode, missing_behavior, schema_type, schema_required, sort_order) VALUES (3, 33, 'customfield_asf_model', 'dr_quantities', 'key_value_map_by_prefix', 1, 'products', 'product_code', 'quantity', '{{stock_lookup_model}}D', 'key_value_map', 'nullable', 'object', 0, 2)");

        if ($withDeploymentTarget) {
            $pdo->exec("INSERT INTO luna_deployment_targets (id, workspace_id, name, environment, public_base_url, endpoint_base_url, webhook_base_url, license_server_url, is_default, is_active, origin, support_status, module_key, requires_entitlement, created_at, updated_at) VALUES (1, 1, 'Production', 'production', 'https://toolbox.example.com/luna', NULL, NULL, NULL, 1, 1, 'customer_created', 'unverified', NULL, 0, '2026-06-05 00:00:00', '2026-06-05 00:00:00')");
        }

        return $pdo;
    }

    private function tempDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/luna_contract_' . bin2hex(random_bytes(6));
        mkdir($directory, 0775, true);

        return str_replace('\\', '/', $directory);
    }

    private function readExport(string $target): string
    {
        $contents = '';
        foreach (['manifest.json', 'schema.json', 'endpoint.json', 'mapping.json', 'README.md', 'checksums.json'] as $file) {
            $contents .= (string) file_get_contents($target . '/' . $file);
        }

        return $contents;
    }
}
