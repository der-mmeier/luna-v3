<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Config\Config;
use Luna\Connections\ExternalPdoConnectionFactory;
use Luna\Database\DatabaseConfig;
use Luna\Database\PdoConnectionFactory;
use Luna\Database\SystemDatabase;
use Luna\Dataset\DatasetRegistry;
use Luna\Repository\ConnectionProfileRepository;
use Luna\Repository\DatasetTransferRepository;
use Luna\Repository\EndpointRepository;
use Luna\Repository\MappingRepository;
use Luna\Security\EncryptionService;
use Luna\Transfer\DatasetTransferRunner;
use Luna\Transfer\MappingExecutionResult;
use Luna\Transfer\SingleTableTransferWriter;
use PDO;
use PHPUnit\Framework\TestCase;

final class DatasetTransferRunnerTest extends TestCase
{
    public function testDryRunCreatesWritePlanWithoutWriting(): void
    {
        [$systemPdo, $targetPdo, $runner] = $this->fixture();

        $result = $runner->run(1, true)->toArray();

        self::assertSame(2, $result['source_count']);
        self::assertSame(2, $result['planned_count']);
        self::assertSame(0, $result['written_count']);
        self::assertSame(0, $targetPdo->query('SELECT COUNT(*) FROM transfer_isr_prices')->fetchColumn());
        self::assertSame('DR001', $result['preview_operations'][0]['key']['model']);
        self::assertSame(85, $result['preview_operations'][0]['data']['price']);
        self::assertSame('upsert', $systemPdo->query('SELECT operation_type FROM luna_dataset_transfers WHERE id = 1')->fetchColumn());
    }

    public function testUpsertInsertsAndUpdatesTargetRows(): void
    {
        [, $targetPdo, $runner] = $this->fixture();

        $insert = $runner->run(1, false)->toArray();
        self::assertSame(2, $insert['written_count']);
        self::assertSame('85', (string) $targetPdo->query("SELECT price FROM transfer_isr_prices WHERE model = 'DR001'")->fetchColumn());

        $targetPdo->exec("UPDATE transfer_isr_prices SET price = 1 WHERE model = 'DR001'");
        $update = $runner->run(1, false)->toArray();

        self::assertSame(2, $update['written_count']);
        self::assertSame('85', (string) $targetPdo->query("SELECT price FROM transfer_isr_prices WHERE model = 'DR001'")->fetchColumn());
    }

    public function testTransferValidationRequiresTargetConnectionTableFieldsAndUpsertKey(): void
    {
        [, , $runner] = $this->fixture();

        $errors = $runner->validate([
            'name' => 'Invalid',
            'source_dataset' => 'isr_prices_v2',
            'operation_type' => 'upsert',
        ], []);

        self::assertContains('Target Connection ist für Transfers erforderlich.', $errors);
        self::assertContains('Target Table ist für Target Groups erforderlich.', $errors);
        self::assertContains('Upsert Key ist für jede Update-/Upsert-Target-Group erforderlich.', $errors);
        self::assertContains('Jede Target Group benötigt mindestens eine Feldzuordnung.', $errors);
    }

    public function testMissingDatasetFieldCreatesError(): void
    {
        [$systemPdo, , $runner] = $this->fixture();
        $systemPdo->exec("UPDATE luna_dataset_transfer_fields SET dataset_field = 'missing_field' WHERE id = 2");

        $result = $runner->run(1, true)->toArray();

        self::assertSame(1, $result['error_count']);
        self::assertSame('Dataset-Feld wurde nicht gefunden: missing_field', $result['errors'][0]);
    }

    public function testMissingTargetColumnCreatesError(): void
    {
        [$systemPdo, , $runner] = $this->fixture();
        $systemPdo->exec("UPDATE luna_dataset_transfer_fields SET target_column = 'missing_column' WHERE id = 2");

        $result = $runner->run(1, true)->toArray();

        self::assertSame(1, $result['error_count']);
        self::assertSame('Zielspalte wurde nicht gefunden: missing_column', $result['errors'][0]);
    }

    public function testDatasetRowsUseMappingExecutionOutputRows(): void
    {
        [$systemPdo] = $this->fixture();
        $registry = $this->datasetRegistry($systemPdo);

        $rows = $registry->rows('isr_prices_v2');

        self::assertSame(['DR001', 'W001'], array_column($rows, 'model'));
    }

    public function testParentChildDryRunCreatesRootAndChildOperationsWithoutWriting(): void
    {
        [, $targetPdo, $runner] = $this->fixture();

        $result = $runner->run(2, true)->toArray();

        self::assertSame(2, $result['source_count']);
        self::assertSame(5, $result['planned_count']);
        self::assertSame(0, $result['written_count']);
        self::assertCount(2, $result['target_groups']);
        self::assertSame('orders', $result['target_groups'][0]['name']);
        self::assertSame('positions', $result['target_groups'][1]['name']);
        self::assertSame(2, $result['target_groups'][0]['planned_count']);
        self::assertSame(3, $result['target_groups'][1]['planned_count']);
        self::assertSame('10001', $result['target_groups'][1]['preview_operations'][0]['data']['order_number']);
        self::assertSame('DR001D48', $result['target_groups'][1]['preview_operations'][0]['data']['sku']);
        self::assertSame(0, $targetPdo->query('SELECT COUNT(*) FROM transfer_orders')->fetchColumn());
        self::assertSame(0, $targetPdo->query('SELECT COUNT(*) FROM transfer_order_positions')->fetchColumn());
    }

    public function testParentChildRunWritesAndUpsertsRootAndChildRows(): void
    {
        [, $targetPdo, $runner] = $this->fixture();

        $insert = $runner->run(2, false)->toArray();
        self::assertSame(5, $insert['written_count']);
        self::assertSame(2, $targetPdo->query('SELECT COUNT(*) FROM transfer_orders')->fetchColumn());
        self::assertSame(3, $targetPdo->query('SELECT COUNT(*) FROM transfer_order_positions')->fetchColumn());

        $targetPdo->exec("UPDATE transfer_orders SET total = 1 WHERE order_number = '10001'");
        $targetPdo->exec("UPDATE transfer_order_positions SET quantity = 9 WHERE order_number = '10001' AND sku = 'DR001D48'");
        $update = $runner->run(2, false)->toArray();

        self::assertSame(5, $update['written_count']);
        self::assertSame('150', (string) $targetPdo->query("SELECT total FROM transfer_orders WHERE order_number = '10001'")->fetchColumn());
        self::assertSame('1', (string) $targetPdo->query("SELECT quantity FROM transfer_order_positions WHERE order_number = '10001' AND sku = 'DR001D48'")->fetchColumn());
    }

    public function testMissingChildCollectionDoesNotCreateFatalError(): void
    {
        [$systemPdo, , $runner] = $this->fixture();
        $systemPdo->exec("UPDATE luna_dataset_transfers SET source_dataset = 'orders_without_positions' WHERE id = 2");

        $result = $runner->run(2, true)->toArray();

        self::assertSame(0, $result['error_count']);
        self::assertSame(1, $result['source_count']);
        self::assertSame(1, $result['planned_count']);
        self::assertSame(1, $result['target_groups'][0]['planned_count']);
        self::assertSame(0, $result['target_groups'][1]['planned_count']);
        self::assertNotEmpty($result['warnings']);
    }

    /**
     * @return array{0: PDO, 1: PDO, 2: DatasetTransferRunner}
     */
    private function fixture(): array
    {
        $systemPdo = $this->systemPdo();
        $targetPdo = $this->targetPdo();
        $database = new SystemDatabase(new DatabaseConfig(new Config()), new PdoConnectionFactory());
        $connections = new ConnectionProfileRepository($database, new EncryptionService(new Config()), $systemPdo);
        $runner = new DatasetTransferRunner(
            new DatasetTransferRepository($database, $systemPdo),
            $this->datasetRegistry($systemPdo),
            $connections,
            new SingleTableTransferWriter(),
            new ExternalPdoConnectionFactory(),
            static fn (array $profile): PDO => $targetPdo,
        );

        return [$systemPdo, $targetPdo, $runner];
    }

    private function datasetRegistry(PDO $systemPdo): DatasetRegistry
    {
        $database = new SystemDatabase(new DatabaseConfig(new Config()), new PdoConnectionFactory());
        $mappingExecutor = static function (int $mappingSetId, bool $dryRun, ?int $limit): MappingExecutionResult {
            self::assertTrue($dryRun);
            self::assertNull($limit);

            $result = new MappingExecutionResult(true);
            if ($mappingSetId === 33) {
                $result->sourceCount = 2;
                $result->transformedCount = 2;
                $result->addPreviewRow(['model' => 'DR001', 'price_group' => '1', 'price' => 85, 'pseudo_price' => 170, 'dr_quantities' => ['48' => 2]]);
                $result->addPreviewRow(['model' => 'W001', 'price_group' => '6', 'price' => 115, 'pseudo_price' => 230, 'dr_quantities' => ['50' => 4]]);
            } elseif ($mappingSetId === 44) {
                $result->sourceCount = 2;
                $result->transformedCount = 2;
                $result->addPreviewRow([
                    'order_number' => '10001',
                    'order_date' => '2026-06-02 14:30:00',
                    'customer_email' => 'kunde@example.de',
                    'total' => 150,
                    'positions' => [
                        ['sku' => 'DR001D48', 'name' => 'Ring DR001 Damen 48', 'quantity' => 1, 'price' => 85],
                        ['sku' => 'DR001H60', 'name' => 'Ring DR001 Herren 60', 'quantity' => 1, 'price' => 65],
                    ],
                ]);
                $result->addPreviewRow([
                    'order_number' => '10002',
                    'order_date' => '2026-06-02 15:30:00',
                    'customer_email' => 'kunde2@example.de',
                    'total' => 85,
                    'positions' => [
                        ['sku' => 'DR002D50', 'name' => 'Ring DR002 Damen 50', 'quantity' => 1, 'price' => 85],
                    ],
                ]);
            } elseif ($mappingSetId === 45) {
                $result->sourceCount = 1;
                $result->transformedCount = 1;
                $result->addPreviewRow([
                    'order_number' => '10003',
                    'order_date' => '2026-06-02 16:30:00',
                    'customer_email' => 'kunde3@example.de',
                    'total' => 45,
                ]);
            }

            return $result;
        };

        return new DatasetRegistry(
            new EndpointRepository($database, new EncryptionService(new Config()), $systemPdo),
            new MappingRepository($database, $systemPdo),
            $mappingExecutor,
        );
    }

    private function systemPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->sqliteCreateFunction('NOW', static fn (): string => '2026-06-02 12:00:00');
        $pdo->exec('CREATE TABLE luna_workspaces (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)');
        $pdo->exec('CREATE TABLE luna_connection_profiles (id INTEGER PRIMARY KEY, workspace_id INTEGER, name TEXT)');
        $pdo->exec('CREATE TABLE luna_connection_secrets (id INTEGER PRIMARY KEY, connection_profile_id INTEGER, secret_key TEXT, secret_value_encrypted TEXT)');
        $pdo->exec('CREATE TABLE luna_jobs (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec('CREATE TABLE luna_endpoints (id INTEGER PRIMARY KEY, workspace_id INTEGER, name TEXT, endpoint_key TEXT, description TEXT, method TEXT, status TEXT, secret_mode TEXT, source_type TEXT, mapping_set_id INTEGER, job_id INTEGER, config_json TEXT, cache_enabled INTEGER, cache_ttl_seconds INTEGER, updated_at TEXT)');
        $pdo->exec('CREATE TABLE luna_mapping_sets (id INTEGER PRIMARY KEY, workspace_id INTEGER, name TEXT, description TEXT, mapping_mode TEXT, source_connection_id INTEGER, source_table TEXT, target_connection_id INTEGER NULL, target_table TEXT NULL, status TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE luna_mapping_fields (id INTEGER PRIMARY KEY, mapping_set_id INTEGER, source_column TEXT, source_json_path TEXT NULL, target_column TEXT, transform_type TEXT, default_value TEXT NULL, lookup_connection_id INTEGER NULL, lookup_table TEXT NULL, lookup_key_column TEXT NULL, lookup_value_column TEXT NULL, lookup_key_template TEXT NULL, fallback_value TEXT NULL, missing_behavior TEXT NULL, sort_order INTEGER)');
        $pdo->exec('CREATE TABLE luna_mapping_source_filters (id INTEGER PRIMARY KEY, mapping_set_id INTEGER, source_column TEXT, operator TEXT, filter_value TEXT, value_type TEXT NULL, sort_order INTEGER)');
        $pdo->exec('CREATE TABLE luna_mapping_value_rules (id INTEGER PRIMARY KEY, mapping_field_id INTEGER, source_value TEXT, target_value TEXT)');
        $pdo->exec('CREATE TABLE luna_dataset_transfers (id INTEGER PRIMARY KEY, workspace_id INTEGER, name TEXT, description TEXT, status TEXT, source_dataset TEXT, target_connection_id INTEGER, target_table TEXT, operation_type TEXT, upsert_key TEXT, created_at TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE luna_dataset_transfer_groups (id INTEGER PRIMARY KEY, transfer_id INTEGER, name TEXT, group_type TEXT, source_path TEXT, target_table TEXT, operation_type TEXT, upsert_key TEXT, parent_link_source TEXT NULL, parent_link_target TEXT NULL, sort_order INTEGER, created_at TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE luna_dataset_transfer_fields (id INTEGER PRIMARY KEY, transfer_id INTEGER, group_id INTEGER NULL, dataset_field TEXT, target_column TEXT, sort_order INTEGER, created_at TEXT, updated_at TEXT)');
        $pdo->exec("INSERT INTO luna_workspaces (id, slug, name) VALUES (1, 'asfinstocks', 'AsfInstocks')");
        $pdo->exec("INSERT INTO luna_connection_profiles (id, workspace_id, name) VALUES (1, 1, 'PIMCORE-Objects')");
        $pdo->exec("INSERT INTO luna_connection_profiles (id, workspace_id, name) VALUES (10, 1, 'Transferdatenbank')");
        $pdo->exec("INSERT INTO luna_mapping_sets (id, workspace_id, name, description, mapping_mode, source_connection_id, source_table, target_connection_id, target_table, status, updated_at) VALUES (33, 1, 'ISR Prices v2 Mapping', '', 'json_endpoint', 1, 'object_query_1', NULL, NULL, 'active', '2026-06-01 12:00:00')");
        $pdo->exec("INSERT INTO luna_mapping_sets (id, workspace_id, name, description, mapping_mode, source_connection_id, source_table, target_connection_id, target_table, status, updated_at) VALUES (44, 1, 'WooCommerce Orders v1 Mapping', '', 'json_endpoint', 1, 'wp_posts', NULL, NULL, 'active', '2026-06-01 12:00:00')");
        $pdo->exec("INSERT INTO luna_mapping_sets (id, workspace_id, name, description, mapping_mode, source_connection_id, source_table, target_connection_id, target_table, status, updated_at) VALUES (45, 1, 'Orders Without Positions', '', 'json_endpoint', 1, 'wp_posts', NULL, NULL, 'active', '2026-06-01 12:00:00')");
        $pdo->exec("INSERT INTO luna_endpoints (id, workspace_id, name, endpoint_key, description, method, status, secret_mode, source_type, mapping_set_id, job_id, config_json, cache_enabled, cache_ttl_seconds, updated_at) VALUES (5, 1, 'ISR Prices v2', 'isr_prices_v2', '', 'GET', 'active', 'none', 'mapping', 33, NULL, '', 0, NULL, '2026-06-01 12:00:00')");
        $pdo->exec("INSERT INTO luna_endpoints (id, workspace_id, name, endpoint_key, description, method, status, secret_mode, source_type, mapping_set_id, job_id, config_json, cache_enabled, cache_ttl_seconds, updated_at) VALUES (6, 1, 'WooCommerce Orders v1', 'woocommerce_orders_v1', '', 'GET', 'active', 'none', 'mapping', 44, NULL, '', 0, NULL, '2026-06-01 12:00:00')");
        $pdo->exec("INSERT INTO luna_endpoints (id, workspace_id, name, endpoint_key, description, method, status, secret_mode, source_type, mapping_set_id, job_id, config_json, cache_enabled, cache_ttl_seconds, updated_at) VALUES (7, 1, 'Orders Without Positions', 'orders_without_positions', '', 'GET', 'active', 'none', 'mapping', 45, NULL, '', 0, NULL, '2026-06-01 12:00:00')");
        $mappingFields = [
            [1, 33, 'customfield_asf_model', 'model', 'direct', 1],
            [2, 33, 'priceGroup', 'price_group', 'direct', 2],
            [3, 33, 'priceGroup', 'price', 'lookup_value', 3],
            [4, 33, 'priceGroup', 'pseudo_price', 'lookup_value', 4],
            [5, 33, 'stock_lookup_model', 'dr_quantities', 'key_value_map_by_prefix', 5],
            [6, 44, 'order_number', 'order_number', 'direct', 1],
            [7, 44, 'order_date', 'order_date', 'direct', 2],
            [8, 44, 'customer_email', 'customer_email', 'direct', 3],
            [9, 44, 'total', 'total', 'direct', 4],
            [10, 44, 'positions', 'positions', 'direct', 5],
            [11, 45, 'order_number', 'order_number', 'direct', 1],
            [12, 45, 'order_date', 'order_date', 'direct', 2],
            [13, 45, 'customer_email', 'customer_email', 'direct', 3],
            [14, 45, 'total', 'total', 'direct', 4],
        ];
        foreach ($mappingFields as [$id, $mappingSetId, $sourceColumn, $targetColumn, $transformType, $sortOrder]) {
            $pdo->exec(sprintf(
                "INSERT INTO luna_mapping_fields (id, mapping_set_id, source_column, target_column, transform_type, sort_order) VALUES (%d, %d, '%s', '%s', '%s', %d)",
                $id,
                $mappingSetId,
                $sourceColumn,
                $targetColumn,
                $transformType,
                $sortOrder,
            ));
        }
        $pdo->exec("INSERT INTO luna_dataset_transfers (id, workspace_id, name, description, status, source_dataset, target_connection_id, target_table, operation_type, upsert_key, created_at, updated_at) VALUES (1, 1, 'ISR Prices Transfer', '', 'active', 'isr_prices_v2', 10, 'transfer_isr_prices', 'upsert', 'model', '2026-06-02 12:00:00', '2026-06-02 12:00:00')");
        $pdo->exec("INSERT INTO luna_dataset_transfer_fields (id, transfer_id, dataset_field, target_column, sort_order) VALUES (1, 1, 'model', 'model', 1)");
        $pdo->exec("INSERT INTO luna_dataset_transfer_fields (id, transfer_id, dataset_field, target_column, sort_order) VALUES (2, 1, 'price_group', 'price_group', 2)");
        $pdo->exec("INSERT INTO luna_dataset_transfer_fields (id, transfer_id, dataset_field, target_column, sort_order) VALUES (3, 1, 'price', 'price', 3)");
        $pdo->exec("INSERT INTO luna_dataset_transfer_fields (id, transfer_id, dataset_field, target_column, sort_order) VALUES (4, 1, 'pseudo_price', 'pseudo_price', 4)");
        $pdo->exec("INSERT INTO luna_dataset_transfer_fields (id, transfer_id, dataset_field, target_column, sort_order) VALUES (5, 1, 'dr_quantities', 'dr_quantities', 5)");
        $pdo->exec("INSERT INTO luna_dataset_transfers (id, workspace_id, name, description, status, source_dataset, target_connection_id, target_table, operation_type, upsert_key, created_at, updated_at) VALUES (2, 1, 'WooCommerce Orders Transfer', '', 'active', 'woocommerce_orders_v1', 10, '', 'upsert', '', '2026-06-02 12:00:00', '2026-06-02 12:00:00')");
        $pdo->exec("INSERT INTO luna_dataset_transfer_groups (id, transfer_id, name, group_type, source_path, target_table, operation_type, upsert_key, parent_link_source, parent_link_target, sort_order, created_at, updated_at) VALUES (1, 2, 'orders', 'root', '$', 'transfer_orders', 'upsert', 'order_number', NULL, NULL, 1, '2026-06-02 12:00:00', '2026-06-02 12:00:00')");
        $pdo->exec("INSERT INTO luna_dataset_transfer_groups (id, transfer_id, name, group_type, source_path, target_table, operation_type, upsert_key, parent_link_source, parent_link_target, sort_order, created_at, updated_at) VALUES (2, 2, 'positions', 'child', 'positions[]', 'transfer_order_positions', 'upsert', 'order_number, sku', 'root.order_number', 'order_number', 2, '2026-06-02 12:00:00', '2026-06-02 12:00:00')");
        $pdo->exec("INSERT INTO luna_dataset_transfer_fields (id, transfer_id, group_id, dataset_field, target_column, sort_order) VALUES (6, 2, 1, 'order_number', 'order_number', 1)");
        $pdo->exec("INSERT INTO luna_dataset_transfer_fields (id, transfer_id, group_id, dataset_field, target_column, sort_order) VALUES (7, 2, 1, 'order_date', 'order_date', 2)");
        $pdo->exec("INSERT INTO luna_dataset_transfer_fields (id, transfer_id, group_id, dataset_field, target_column, sort_order) VALUES (8, 2, 1, 'customer_email', 'customer_email', 3)");
        $pdo->exec("INSERT INTO luna_dataset_transfer_fields (id, transfer_id, group_id, dataset_field, target_column, sort_order) VALUES (9, 2, 1, 'total', 'total', 4)");
        $pdo->exec("INSERT INTO luna_dataset_transfer_fields (id, transfer_id, group_id, dataset_field, target_column, sort_order) VALUES (10, 2, 2, 'positions[].sku', 'sku', 1)");
        $pdo->exec("INSERT INTO luna_dataset_transfer_fields (id, transfer_id, group_id, dataset_field, target_column, sort_order) VALUES (11, 2, 2, 'positions[].name', 'name', 2)");
        $pdo->exec("INSERT INTO luna_dataset_transfer_fields (id, transfer_id, group_id, dataset_field, target_column, sort_order) VALUES (12, 2, 2, 'positions[].quantity', 'quantity', 3)");
        $pdo->exec("INSERT INTO luna_dataset_transfer_fields (id, transfer_id, group_id, dataset_field, target_column, sort_order) VALUES (13, 2, 2, 'positions[].price', 'price', 4)");

        return $pdo;
    }

    private function targetPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE transfer_isr_prices (model TEXT PRIMARY KEY, price_group TEXT, price NUMERIC, pseudo_price NUMERIC, dr_quantities TEXT)');
        $pdo->exec('CREATE TABLE transfer_orders (order_number TEXT PRIMARY KEY, order_date TEXT, customer_email TEXT, total NUMERIC)');
        $pdo->exec('CREATE TABLE transfer_order_positions (order_number TEXT, sku TEXT, name TEXT, quantity INTEGER, price NUMERIC, PRIMARY KEY (order_number, sku))');

        return $pdo;
    }
}
