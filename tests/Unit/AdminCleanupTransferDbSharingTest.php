<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Config\Config;
use Luna\Database\DatabaseConfig;
use Luna\Database\PdoConnectionFactory;
use Luna\Database\SystemDatabase;
use Luna\Repository\ConnectionProfileRepository;
use Luna\Repository\DatasetTransferRepository;
use Luna\Repository\ExportProfileRepository;
use Luna\Repository\JobRepository;
use Luna\Repository\ReportRepository;
use Luna\Repository\SchemaRegistryRepository;
use Luna\Repository\WooCommerceIntegrationRepository;
use Luna\Security\EncryptionService;
use Luna\TransferDb\TransferDbSchemaManager;
use PDO;
use PHPUnit\Framework\TestCase;

final class AdminCleanupTransferDbSharingTest extends TestCase
{
    public function testConnectionCanBeSharedWithMultipleWorkspaces(): void
    {
        $pdo = $this->adminPdo();
        $repo = new ConnectionProfileRepository($this->systemDatabase(), new EncryptionService(new Config()), $pdo);
        $pdo->exec("INSERT INTO luna_workspaces (id, slug, name) VALUES (1, 'toolbox', 'Toolbox'), (2, 'asf', 'AsfInStocks')");
        $pdo->exec("INSERT INTO luna_connection_profiles (id, workspace_id, name, type, is_active) VALUES (10, 1, 'ASF Gmbh', 'transfer_db', 1)");
        $pdo->exec("INSERT INTO luna_connection_workspaces (connection_id, workspace_id, role, is_default, created_at, updated_at) VALUES (10, 1, 'transfer_db', 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP), (10, 2, 'transfer_db', 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        $ids = $repo->workspaceIdsForConnection(10);
        sort($ids);

        self::assertSame([1, 2], $ids);
    }

    public function testSharedTransferDbIsAvailableForEndpointWorkspace(): void
    {
        $pdo = $this->adminPdo();
        $repo = new ConnectionProfileRepository($this->systemDatabase(), new EncryptionService(new Config()), $pdo);
        $pdo->exec("INSERT INTO luna_workspaces (id, slug, name) VALUES (1, 'toolbox', 'Toolbox'), (2, 'asf', 'AsfInStocks')");
        $pdo->exec("INSERT INTO luna_connection_profiles (id, workspace_id, name, type, is_active) VALUES (10, 1, 'ASF Gmbh', 'transfer_db', 1)");
        $pdo->exec("INSERT INTO luna_connection_workspaces (connection_id, workspace_id, role, is_default, created_at, updated_at) VALUES (10, 2, 'transfer_db', 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        $connections = $repo->transferDbConnectionsForWorkspace(2);

        self::assertCount(1, $connections);
        self::assertSame('ASF Gmbh', $connections[0]['name']);
    }

    public function testConnectionDeleteBlockerNamesReferencingJobsMappingsAndEndpoints(): void
    {
        $pdo = $this->adminPdo();
        $repo = new ConnectionProfileRepository($this->systemDatabase(), new EncryptionService(new Config()), $pdo);
        $pdo->exec("INSERT INTO luna_workspaces (id, slug, name) VALUES (1, 'asf', 'AsfInStocks')");
        $pdo->exec("INSERT INTO luna_connection_profiles (id, workspace_id, name, type, is_active) VALUES (10, 1, 'ASF Gmbh', 'source', 1)");
        $pdo->exec("INSERT INTO luna_mapping_sets (id, workspace_id, name, source_connection_id) VALUES (20, 1, 'ISR Prices Mapping', 10)");
        $pdo->exec("INSERT INTO luna_jobs (id, workspace_id, name, mapping_set_id) VALUES (30, 1, 'Connection Smoke Test', 20)");
        $pdo->exec("INSERT INTO luna_endpoints (id, workspace_id, name, endpoint_key, mapping_set_id) VALUES (40, 1, 'ISR Prices Endpoint Mapping v2', 'isr_prices', 20)");
        $pdo->exec("INSERT INTO luna_dataset_transfers (id, workspace_id, name, target_connection_id) VALUES (50, 1, 'ISR Transfer Test', 10)");
        $pdo->exec("INSERT INTO luna_woocommerce_connections (id, workspace_id, connection_id, name) VALUES (60, 1, 10, 'WooCommerce Runtime Test')");

        $check = $repo->canDelete(10);

        self::assertFalse($check->allowed);
        self::assertStringContainsString('Connection "ASF Gmbh"', $check->message);
        self::assertContains('Mapping "ISR Prices Mapping"', $check->blockingNames);
        self::assertContains('Job "Connection Smoke Test"', $check->blockingNames);
        self::assertContains('Endpoint "ISR Prices Endpoint Mapping v2"', $check->blockingNames);
        self::assertContains('Transfer "ISR Transfer Test"', $check->blockingNames);
        self::assertContains('WooCommerce-Anbindung "WooCommerce Runtime Test"', $check->blockingNames);
    }

    public function testJobDeleteCascadesRunsLogsAndReports(): void
    {
        $pdo = $this->jobPdo();
        $repo = new JobRepository($this->systemDatabase(), $pdo);
        $pdo->exec("INSERT INTO luna_jobs (id, workspace_id, name) VALUES (1, 1, 'Smoke Job')");
        $pdo->exec("INSERT INTO luna_job_runs (id, job_id) VALUES (2, 1)");
        $pdo->exec("INSERT INTO luna_job_run_logs (id, job_run_id, message) VALUES (3, 2, 'log')");
        $pdo->exec("INSERT INTO luna_reports (id, job_run_id, workspace_id, report_key, name, type, subject, body, status) VALUES (4, 2, 1, 'report_4', 'Report', 'job_run', 'Report', '{}', 'created')");

        $repo->delete(1);

        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM luna_jobs')->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM luna_job_runs')->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM luna_job_run_logs')->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM luna_reports')->fetchColumn());
    }

    public function testReportCanBeCreatedUpdatedAndDeleted(): void
    {
        $pdo = $this->reportPdo();
        $repo = new ReportRepository($this->systemDatabase(), $pdo);

        $id = $repo->create(['workspace_id' => 1, 'name' => 'Runtime Report', 'report_key' => 'runtime_report', 'type' => 'process_runs', 'status' => 'draft', 'config_json' => '{}']);
        $repo->update($id, ['workspace_id' => 1, 'name' => 'Runtime Report Updated', 'report_key' => 'runtime_report', 'type' => 'process_runs', 'status' => 'active', 'config_json' => '{"limit":10}']);
        $report = $repo->find($id);

        self::assertSame('Runtime Report Updated', $report['name']);
        self::assertSame('active', $report['status']);

        $repo->delete($id);
        self::assertNull($repo->find($id));
    }

    public function testTransferDeleteBlocksExistingRunsAndDeletesOwnConfiguration(): void
    {
        $pdo = $this->transferPdo();
        $repo = new DatasetTransferRepository($this->systemDatabase(), $pdo);
        $pdo->exec("INSERT INTO luna_workspaces (id, slug, name) VALUES (1, 'asf', 'AsfInStocks')");
        $pdo->exec("INSERT INTO luna_connection_profiles (id, workspace_id, name) VALUES (10, 1, 'TransferDB')");
        $pdo->exec("INSERT INTO luna_dataset_transfers (id, workspace_id, name, source_dataset, target_connection_id) VALUES (20, 1, 'ISR Transfer Test', 'isr_prices', 10)");
        $pdo->exec("INSERT INTO luna_dataset_transfer_fields (id, transfer_id, dataset_field, target_column) VALUES (21, 20, 'model', 'sku')");
        $pdo->exec("INSERT INTO luna_dataset_transfer_groups (id, transfer_id, name) VALUES (22, 20, 'orders')");
        $pdo->exec("INSERT INTO luna_dataset_transfer_runs (id, transfer_id, created_at) VALUES (23, 20, '2026-06-09 20:15:00')");

        $blocked = $repo->canDelete(20);

        self::assertFalse($blocked->allowed);
        self::assertContains('Transfer Run #23 vom 2026-06-09 20:15:00', $blocked->blockingNames);

        $pdo->exec('DELETE FROM luna_dataset_transfer_runs WHERE transfer_id = 20');
        self::assertTrue($repo->canDelete(20)->allowed);

        $repo->delete(20);

        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM luna_dataset_transfers')->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM luna_dataset_transfer_fields')->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM luna_dataset_transfer_groups')->fetchColumn());
    }

    public function testWooCommerceConnectionDeleteBlocksExportProfilesAndDeletesLocalChildren(): void
    {
        $pdo = $this->woocommercePdo();
        $encryption = new EncryptionService(new Config());
        $woocommerce = new WooCommerceIntegrationRepository($this->systemDatabase(), $encryption, $pdo);
        $profiles = new ExportProfileRepository($this->systemDatabase(), $encryption, $pdo);
        $pdo->exec("INSERT INTO luna_workspaces (id, slug, name) VALUES (1, 'asf', 'AsfInStocks')");
        $pdo->exec("INSERT INTO luna_connection_profiles (id, workspace_id, name) VALUES (10, 1, 'Woo DB')");
        $pdo->exec("INSERT INTO luna_woocommerce_connections (id, workspace_id, connection_id, name, connection_token) VALUES (20, 1, 10, 'Woo Runtime', 'token')");
        $pdo->exec("INSERT INTO luna_export_profiles (id, workspace_id, connection_id, integration_type, profile_key, name) VALUES (30, 1, 20, 'woocommerce', 'orders', 'Woo Orders')");

        $blocked = $woocommerce->canDeleteConnection(20);

        self::assertFalse($blocked->allowed);
        self::assertContains('Exportprofil "Woo Orders"', $blocked->blockingNames);

        $profiles->delete(30);
        $pdo->exec("INSERT INTO luna_woocommerce_webhook_configs (id, workspace_id, woocommerce_connection_id, webhook_name, topic) VALUES (40, 1, 20, 'Order Updated', 'order.updated')");
        $pdo->exec("INSERT INTO luna_woocommerce_webhook_events (id, workspace_id, woocommerce_connection_id, topic) VALUES (41, 1, 20, 'order.updated')");
        $pdo->exec("INSERT INTO luna_woocommerce_transfer_queue (id, workspace_id, woocommerce_connection_id, status) VALUES (42, 1, 20, 'pending')");
        $pdo->exec("INSERT INTO luna_woocommerce_transfer_runs (id, workspace_id, woocommerce_connection_id, queue_id, status) VALUES (43, 1, 20, 42, 'success')");

        self::assertTrue($woocommerce->canDeleteConnection(20)->allowed);

        $woocommerce->deleteConnection(20);

        foreach ([
            'luna_woocommerce_connections',
            'luna_woocommerce_webhook_configs',
            'luna_woocommerce_webhook_events',
            'luna_woocommerce_transfer_queue',
            'luna_woocommerce_transfer_runs',
        ] as $table) {
            self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn(), $table);
        }
    }

    public function testSchemaDeleteReportsEndpointBlockerName(): void
    {
        $pdo = $this->schemaPdo();
        $repo = new SchemaRegistryRepository($this->systemDatabase(), $pdo);
        $pdo->exec("INSERT INTO luna_workspaces (id, slug, name) VALUES (1, 'asf', 'AsfInStocks')");
        $pdo->exec("INSERT INTO luna_schemas (id, workspace_id, schema_key, version, name, definition_json, status) VALUES (5, 1, 'isr_prices', '1', 'ISR Prices Schema', '{}', 'active')");
        $pdo->exec("INSERT INTO luna_endpoints (id, workspace_id, name, endpoint_key, schema_id) VALUES (6, 1, 'ISR Prices Endpoint Mapping v2', 'isr_prices', 5)");

        $this->expectExceptionMessage('ISR Prices Endpoint Mapping v2');
        $repo->delete(5);
    }

    public function testTransferDbSchemaManagerCreatesOnlyLunaPrefixedTables(): void
    {
        $pdo = $this->memoryPdo();
        $manager = new TransferDbSchemaManager();

        foreach ($manager->tableNames() as $table) {
            self::assertStringStartsWith('luna_', $table);
        }

        $status = $manager->migrate($pdo);

        self::assertTrue($status['schema_current']);
        self::assertSame([], $status['missing_tables']);
    }

    private function adminPdo(): PDO
    {
        $pdo = $this->memoryPdo();
        $pdo->exec('CREATE TABLE luna_workspaces (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, transfer_db_connection_id INTEGER NULL)');
        $pdo->exec('CREATE TABLE luna_connection_profiles (id INTEGER PRIMARY KEY, workspace_id INTEGER NULL, name TEXT, type TEXT DEFAULT "source", is_active INTEGER DEFAULT 1)');
        $pdo->exec('CREATE TABLE luna_connection_secrets (connection_profile_id INTEGER, secret_key TEXT, secret_value_encrypted TEXT)');
        $pdo->exec('CREATE TABLE luna_connection_workspaces (id INTEGER PRIMARY KEY AUTOINCREMENT, connection_id INTEGER, workspace_id INTEGER, role TEXT NULL, is_default INTEGER DEFAULT 0, created_at TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE luna_schema_snapshots (id INTEGER PRIMARY KEY, connection_profile_id INTEGER)');
        $pdo->exec('CREATE TABLE luna_table_notes (id INTEGER PRIMARY KEY, connection_profile_id INTEGER)');
        $pdo->exec('CREATE TABLE luna_column_notes (id INTEGER PRIMARY KEY, connection_profile_id INTEGER)');
        $pdo->exec('CREATE TABLE luna_mapping_sets (id INTEGER PRIMARY KEY, workspace_id INTEGER NULL, name TEXT, source_connection_id INTEGER NULL, target_connection_id INTEGER NULL)');
        $pdo->exec('CREATE TABLE luna_mapping_fields (id INTEGER PRIMARY KEY, mapping_set_id INTEGER, target_column TEXT, lookup_connection_id INTEGER NULL)');
        $pdo->exec('CREATE TABLE luna_jobs (id INTEGER PRIMARY KEY, workspace_id INTEGER NULL, name TEXT, mapping_set_id INTEGER NULL)');
        $pdo->exec('CREATE TABLE luna_endpoints (id INTEGER PRIMARY KEY, workspace_id INTEGER NULL, name TEXT, endpoint_key TEXT, mapping_set_id INTEGER NULL)');
        $pdo->exec('CREATE TABLE luna_dataset_transfers (id INTEGER PRIMARY KEY, workspace_id INTEGER NULL, name TEXT, target_connection_id INTEGER NULL)');
        $pdo->exec('CREATE TABLE luna_woocommerce_connections (id INTEGER PRIMARY KEY, workspace_id INTEGER NULL, connection_id INTEGER, name TEXT)');

        return $pdo;
    }

    private function jobPdo(): PDO
    {
        $pdo = $this->memoryPdo();
        $pdo->exec('CREATE TABLE luna_jobs (id INTEGER PRIMARY KEY, workspace_id INTEGER NULL, name TEXT)');
        $pdo->exec('CREATE TABLE luna_job_runs (id INTEGER PRIMARY KEY, job_id INTEGER)');
        $pdo->exec('CREATE TABLE luna_job_run_logs (id INTEGER PRIMARY KEY, job_run_id INTEGER, message TEXT)');
        $pdo->exec('CREATE TABLE luna_reports (id INTEGER PRIMARY KEY, job_run_id INTEGER NULL, workspace_id INTEGER NULL, report_key TEXT, name TEXT, type TEXT, subject TEXT, body TEXT, status TEXT)');

        return $pdo;
    }

    private function reportPdo(): PDO
    {
        $pdo = $this->memoryPdo();
        $pdo->exec('CREATE TABLE luna_workspaces (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)');
        $pdo->exec('CREATE TABLE luna_reports (id INTEGER PRIMARY KEY AUTOINCREMENT, job_run_id INTEGER NULL, workspace_id INTEGER NULL, type TEXT, report_key TEXT, name TEXT, subject TEXT, body TEXT, config_json TEXT NULL, notes TEXT NULL, recipients TEXT NULL, status TEXT, sent_at TEXT NULL, error_message TEXT NULL, created_at TEXT, updated_at TEXT)');
        $pdo->exec("INSERT INTO luna_workspaces (id, slug, name) VALUES (1, 'asf', 'AsfInStocks')");

        return $pdo;
    }

    private function schemaPdo(): PDO
    {
        $pdo = $this->memoryPdo();
        $pdo->exec('CREATE TABLE luna_workspaces (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)');
        $pdo->exec('CREATE TABLE luna_schemas (id INTEGER PRIMARY KEY, workspace_id INTEGER, schema_key TEXT, version TEXT, name TEXT, description TEXT NULL, definition_json TEXT, example_json TEXT NULL, status TEXT, created_at TEXT NULL, updated_at TEXT NULL)');
        $pdo->exec('CREATE TABLE luna_schema_revisions (id INTEGER PRIMARY KEY, schema_id INTEGER, version TEXT, definition_json TEXT, example_json TEXT NULL, change_summary TEXT NULL, created_at TEXT NULL)');
        $pdo->exec('CREATE TABLE luna_endpoints (id INTEGER PRIMARY KEY, workspace_id INTEGER NULL, name TEXT, endpoint_key TEXT, schema_id INTEGER NULL)');

        return $pdo;
    }

    private function transferPdo(): PDO
    {
        $pdo = $this->memoryPdo();
        $pdo->exec('CREATE TABLE luna_workspaces (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)');
        $pdo->exec('CREATE TABLE luna_connection_profiles (id INTEGER PRIMARY KEY, workspace_id INTEGER NULL, name TEXT)');
        $pdo->exec('CREATE TABLE luna_dataset_transfers (id INTEGER PRIMARY KEY, workspace_id INTEGER NULL, name TEXT, source_dataset TEXT NULL, target_connection_id INTEGER NULL, target_table TEXT NULL)');
        $pdo->exec('CREATE TABLE luna_dataset_transfer_fields (id INTEGER PRIMARY KEY, transfer_id INTEGER, dataset_field TEXT, target_column TEXT)');
        $pdo->exec('CREATE TABLE luna_dataset_transfer_groups (id INTEGER PRIMARY KEY, transfer_id INTEGER, name TEXT)');
        $pdo->exec('CREATE TABLE luna_dataset_transfer_runs (id INTEGER PRIMARY KEY, transfer_id INTEGER, created_at TEXT)');

        return $pdo;
    }

    private function woocommercePdo(): PDO
    {
        $pdo = $this->memoryPdo();
        $pdo->exec('CREATE TABLE luna_workspaces (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)');
        $pdo->exec('CREATE TABLE luna_connection_profiles (id INTEGER PRIMARY KEY, workspace_id INTEGER NULL, name TEXT)');
        $pdo->exec('CREATE TABLE luna_woocommerce_connections (id INTEGER PRIMARY KEY, workspace_id INTEGER NULL, connection_id INTEGER, name TEXT, connection_token TEXT)');
        $pdo->exec('CREATE TABLE luna_woocommerce_webhook_configs (id INTEGER PRIMARY KEY, workspace_id INTEGER NULL, woocommerce_connection_id INTEGER, webhook_name TEXT, topic TEXT)');
        $pdo->exec('CREATE TABLE luna_woocommerce_webhook_events (id INTEGER PRIMARY KEY, workspace_id INTEGER NULL, woocommerce_connection_id INTEGER, topic TEXT)');
        $pdo->exec('CREATE TABLE luna_woocommerce_transfer_queue (id INTEGER PRIMARY KEY, workspace_id INTEGER NULL, woocommerce_connection_id INTEGER, status TEXT)');
        $pdo->exec('CREATE TABLE luna_woocommerce_transfer_runs (id INTEGER PRIMARY KEY, workspace_id INTEGER NULL, woocommerce_connection_id INTEGER, queue_id INTEGER NULL, status TEXT)');
        $pdo->exec('CREATE TABLE luna_export_profiles (id INTEGER PRIMARY KEY, workspace_id INTEGER NULL, connection_id INTEGER NULL, integration_type TEXT, profile_key TEXT, name TEXT)');

        return $pdo;
    }

    private function memoryPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }

    private function systemDatabase(): SystemDatabase
    {
        return new SystemDatabase(new DatabaseConfig(new Config()), new PdoConnectionFactory());
    }
}
