<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Config\Config;
use Luna\Database\DatabaseConfig;
use Luna\Database\PdoConnectionFactory;
use Luna\Database\SystemDatabase;
use Luna\Dataset\DatasetRegistry;
use Luna\Repository\EndpointRepository;
use Luna\Repository\MappingRepository;
use Luna\Security\EncryptionService;
use Luna\Transfer\MappingExecutionResult;
use PDO;
use PHPUnit\Framework\TestCase;

final class DatasetRegistryTest extends TestCase
{
    public function testRegistryListsEndpointDatasetsWithOutputFieldsAndFilters(): void
    {
        $pdo = $this->pdo();
        $registry = $this->registry($pdo);

        $datasets = $registry->all();

        self::assertCount(1, $datasets);
        self::assertSame('isr_prices_v2', $datasets[0]['name']);
        self::assertSame('endpoint', $datasets[0]['source_type']);
        self::assertSame('ISR Prices v2 Mapping', $datasets[0]['mapping_name']);
        self::assertTrue($datasets[0]['is_source_available']);
        self::assertSame(['model', 'price_group', 'dr_quantities'], array_column($datasets[0]['fields'], 'name'));
        self::assertSame('priceGroup', $datasets[0]['source_filters'][0]['source_column']);
        self::assertSame('numeric_greater_than', $datasets[0]['source_filters'][0]['operator']);
    }

    public function testDatasetPreviewUsesMappingExecutionWithoutWriting(): void
    {
        $pdo = $this->pdo();
        $registry = $this->registry($pdo);

        $preview = $registry->preview('isr_prices_v2', 10);

        self::assertSame('isr_prices_v2', $preview['dataset']['name']);
        self::assertSame(1, $preview['summary']['source_count']);
        self::assertSame(1, $preview['summary']['transformed_count']);
        self::assertSame(0, $preview['summary']['written_count']);
        self::assertSame('DR001', $preview['rows'][0]['model']);
        self::assertSame('1', $preview['rows'][0]['price_group']);
        self::assertSame([], $preview['rows'][0]['dr_quantities']);
    }

    public function testJsonEndpointMappingWithoutEndpointIsListedAsDataset(): void
    {
        $pdo = $this->pdo();
        $pdo->exec("DELETE FROM luna_endpoints");
        $registry = $this->registry($pdo);

        $datasets = $registry->all();

        self::assertCount(1, $datasets);
        self::assertSame('isr_prices_v2_mapping', $datasets[0]['name']);
        self::assertSame('mapping_set', $datasets[0]['source_type']);
    }

    private function registry(PDO $pdo): DatasetRegistry
    {
        $database = new SystemDatabase(new DatabaseConfig(new Config()), new PdoConnectionFactory());
        $mappingExecutor = static function (int $mappingSetId, bool $dryRun, ?int $limit): MappingExecutionResult {
            self::assertSame(33, $mappingSetId);
            self::assertTrue($dryRun);
            self::assertSame(10, $limit);

            $result = new MappingExecutionResult(true);
            $result->sourceCount = 1;
            $result->transformedCount = 1;
            $result->writtenCount = 0;
            $result->addPreviewRow([
                'model' => 'DR001',
                'price_group' => '1',
                'dr_quantities' => [],
            ]);

            return $result;
        };

        return new DatasetRegistry(
            new EndpointRepository($database, new EncryptionService(new Config()), $pdo),
            new MappingRepository($database, $pdo),
            $mappingExecutor,
        );
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE luna_workspaces (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)');
        $pdo->exec('CREATE TABLE luna_connection_profiles (id INTEGER PRIMARY KEY, workspace_id INTEGER, name TEXT)');
        $pdo->exec('CREATE TABLE luna_jobs (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec('CREATE TABLE luna_endpoints (id INTEGER PRIMARY KEY, workspace_id INTEGER, name TEXT, endpoint_key TEXT, description TEXT, method TEXT, status TEXT, secret_mode TEXT, source_type TEXT, mapping_set_id INTEGER, job_id INTEGER, config_json TEXT, cache_enabled INTEGER, cache_ttl_seconds INTEGER, updated_at TEXT)');
        $pdo->exec('CREATE TABLE luna_mapping_sets (id INTEGER PRIMARY KEY, workspace_id INTEGER, name TEXT, description TEXT, mapping_mode TEXT, source_connection_id INTEGER, source_table TEXT, target_connection_id INTEGER NULL, target_table TEXT NULL, status TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE luna_mapping_fields (id INTEGER PRIMARY KEY, mapping_set_id INTEGER, source_column TEXT, source_json_path TEXT NULL, target_column TEXT, transform_type TEXT, default_value TEXT NULL, lookup_connection_id INTEGER NULL, lookup_table TEXT NULL, lookup_key_column TEXT NULL, lookup_value_column TEXT NULL, lookup_key_template TEXT NULL, fallback_value TEXT NULL, missing_behavior TEXT NULL, sort_order INTEGER)');
        $pdo->exec('CREATE TABLE luna_mapping_source_filters (id INTEGER PRIMARY KEY, mapping_set_id INTEGER, source_column TEXT, operator TEXT, filter_value TEXT, value_type TEXT NULL, sort_order INTEGER)');
        $pdo->exec('CREATE TABLE luna_mapping_value_rules (id INTEGER PRIMARY KEY, mapping_field_id INTEGER, source_value TEXT, target_value TEXT)');
        $pdo->exec("INSERT INTO luna_workspaces (id, slug, name) VALUES (1, 'asfinstocks', 'AsfInstocks')");
        $pdo->exec("INSERT INTO luna_connection_profiles (id, workspace_id, name) VALUES (1, 1, 'PIMCORE-Objects')");
        $pdo->exec("INSERT INTO luna_mapping_sets (id, workspace_id, name, description, mapping_mode, source_connection_id, source_table, target_connection_id, target_table, status, updated_at) VALUES (33, 1, 'ISR Prices v2 Mapping', '', 'json_endpoint', 1, 'object_query_1', NULL, NULL, 'active', '2026-06-01 12:00:00')");
        $pdo->exec("INSERT INTO luna_endpoints (id, workspace_id, name, endpoint_key, description, method, status, secret_mode, source_type, mapping_set_id, job_id, config_json, cache_enabled, cache_ttl_seconds, updated_at) VALUES (5, 1, 'ISR Prices v2', 'isr_prices_v2', '', 'GET', 'active', 'none', 'mapping', 33, NULL, '', 0, NULL, '2026-06-01 12:00:00')");
        $pdo->exec("INSERT INTO luna_mapping_source_filters (id, mapping_set_id, source_column, operator, filter_value, sort_order) VALUES (1, 33, 'priceGroup', 'numeric_greater_than', '0', 0)");
        $pdo->exec("INSERT INTO luna_mapping_fields (id, mapping_set_id, source_column, target_column, transform_type, lookup_key_template, sort_order) VALUES (1, 33, 'customfield_asf_model', 'model', 'first_non_empty', '', 1)");
        $pdo->exec("INSERT INTO luna_mapping_fields (id, mapping_set_id, source_column, target_column, transform_type, lookup_key_template, sort_order) VALUES (2, 33, 'priceGroup', 'price_group', 'direct', '', 2)");
        $pdo->exec("INSERT INTO luna_mapping_fields (id, mapping_set_id, source_column, target_column, transform_type, lookup_key_template, sort_order) VALUES (3, 33, 'stock_lookup_model', 'dr_quantities', 'key_value_map_by_prefix', '{{stock_lookup_model}}D', 4)");

        return $pdo;
    }
}
