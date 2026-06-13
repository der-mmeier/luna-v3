<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Admin\DeletionGuard;
use Luna\Config\Config;
use Luna\Database\DatabaseConfig;
use Luna\Database\PdoConnectionFactory;
use Luna\Database\SystemDatabase;
use Luna\Repository\ReportRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class DeletionGuardCompletionTest extends TestCase
{
    public function testConnectionBlockersContainConcreteMappingJobAndDatasetNames(): void
    {
        $pdo = $this->pdo();
        $pdo->exec("INSERT INTO luna_connection_profiles (id, name) VALUES (2, 'ASF Gmbh')");
        $pdo->exec("INSERT INTO luna_mapping_sets (id, name, source_connection_id) VALUES (10, 'ISR Prices Endpoint Mapping v2', 2)");
        $pdo->exec("INSERT INTO luna_jobs (id, name, mapping_set_id) VALUES (12, 'Connection Test ASF Gmbh', 10)");
        $pdo->exec("INSERT INTO luna_endpoints (id, name, endpoint_key, mapping_set_id) VALUES (14, 'ISR Source Dataset', 'isr_source', 10)");

        $guard = new DeletionGuard($this->systemDatabase(), $pdo);
        $check = $guard->canDelete('connection', 2);
        $names = array_column($check['blockers'], 'name');

        self::assertFalse($check['can_delete']);
        self::assertContains('ISR Prices Endpoint Mapping v2', $names);
        self::assertContains('Connection Test ASF Gmbh', $names);
        self::assertContains('ISR Source Dataset', $names);
        self::assertStringContainsString('Connection "ASF Gmbh" kann nicht gelöscht werden', $guard->message($check));
        self::assertStringContainsString('- Job "Connection Test ASF Gmbh"', $guard->message($check));
    }

    public function testTransferRunBlockerContainsIdAndDate(): void
    {
        $pdo = $this->pdo();
        $pdo->exec("INSERT INTO luna_dataset_transfers (id, name) VALUES (7, 'ISR Transfer Test')");
        $pdo->exec("INSERT INTO luna_dataset_transfer_runs (id, transfer_id, started_at, created_at) VALUES (123, 7, '2026-06-13 12:00:00', '2026-06-13 11:59:00')");

        $guard = new DeletionGuard($this->systemDatabase(), $pdo);
        $check = $guard->canDelete('transfer', 7);

        self::assertFalse($check['can_delete']);
        self::assertSame('#123 vom 2026-06-13 12:00:00', $check['blockers'][0]['name']);
        self::assertStringContainsString('Transfer-Lauf "#123 vom 2026-06-13 12:00:00"', $guard->message($check));
    }

    public function testWooCommerceBlockersContainTriggerProfileAndQueueEntry(): void
    {
        $pdo = $this->pdo();
        $pdo->exec("INSERT INTO luna_woocommerce_connections (id, name) VALUES (5, 'Shop ASF Test')");
        $pdo->exec("INSERT INTO luna_export_profiles (id, connection_id, name) VALUES (6, 5, 'Orders Export')");
        $pdo->exec("INSERT INTO luna_process_triggers (id, name, config_json) VALUES (8, 'WooCommerce Order Updated', '{\"provider\":\"woocommerce\",\"woocommerce_connection_id\":5}')");
        $pdo->exec("INSERT INTO luna_woocommerce_transfer_queue (id, woocommerce_connection_id, topic, source_order_id, created_at) VALUES (9, 5, 'order.updated', '4711', '2026-06-13 13:00:00')");

        $guard = new DeletionGuard($this->systemDatabase(), $pdo);
        $check = $guard->canDelete('woocommerce', 5);
        $names = array_column($check['blockers'], 'name');

        self::assertFalse($check['can_delete']);
        self::assertContains('Orders Export', $names);
        self::assertContains('WooCommerce Order Updated', $names);
        self::assertContains('#9 (order.updated, 4711) vom 2026-06-13 13:00:00', $names);
        self::assertStringContainsString('WooCommerce-Anbindung "Shop ASF Test" kann nicht gelöscht werden', $guard->message($check));
    }

    public function testReportIsExplicitlyDeletableAndCrudNormalizesEmptyWorkspace(): void
    {
        $pdo = $this->pdo();
        $repository = new ReportRepository($this->systemDatabase(), $pdo);
        $id = $repository->create([
            'workspace_id' => '',
            'type' => 'manual',
            'subject' => 'Cleanup Test Report',
            'body' => 'Initial content',
            'recipients' => '',
            'status' => 'created',
        ]);

        self::assertNull($repository->find($id)['workspace_id']);
        self::assertTrue((new DeletionGuard($this->systemDatabase(), $pdo))->canDelete('report', $id)['can_delete']);

        $repository->update($id, [
            'workspace_id' => '',
            'type' => 'manual',
            'subject' => 'Cleanup Test Report Updated',
            'body' => 'Updated content',
            'recipients' => '',
            'status' => 'draft',
        ]);
        self::assertSame('Cleanup Test Report Updated', $repository->find($id)['subject']);

        $repository->delete($id);
        self::assertNull($repository->find($id));
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->sqliteCreateFunction('NOW', static fn (): string => '2026-06-13 14:00:00');
        $pdo->exec('CREATE TABLE luna_workspaces (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec('CREATE TABLE luna_connection_profiles (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec('CREATE TABLE luna_mapping_sets (id INTEGER PRIMARY KEY, name TEXT, source_connection_id INTEGER NULL, target_connection_id INTEGER NULL)');
        $pdo->exec('CREATE TABLE luna_mapping_fields (id INTEGER PRIMARY KEY, mapping_set_id INTEGER, lookup_connection_id INTEGER NULL)');
        $pdo->exec('CREATE TABLE luna_jobs (id INTEGER PRIMARY KEY, name TEXT, mapping_set_id INTEGER NULL)');
        $pdo->exec('CREATE TABLE luna_endpoints (id INTEGER PRIMARY KEY, name TEXT, endpoint_key TEXT, mapping_set_id INTEGER NULL, job_id INTEGER NULL)');
        $pdo->exec('CREATE TABLE luna_dataset_transfers (id INTEGER PRIMARY KEY, name TEXT, target_connection_id INTEGER NULL)');
        $pdo->exec('CREATE TABLE luna_dataset_transfer_runs (id INTEGER PRIMARY KEY, transfer_id INTEGER, started_at TEXT NULL, created_at TEXT NULL)');
        $pdo->exec('CREATE TABLE luna_woocommerce_connections (id INTEGER PRIMARY KEY, name TEXT, connection_id INTEGER NULL)');
        $pdo->exec('CREATE TABLE luna_woocommerce_webhook_configs (id INTEGER PRIMARY KEY, woocommerce_connection_id INTEGER, webhook_name TEXT)');
        $pdo->exec('CREATE TABLE luna_export_profiles (id INTEGER PRIMARY KEY, connection_id INTEGER, name TEXT)');
        $pdo->exec('CREATE TABLE luna_process_triggers (id INTEGER PRIMARY KEY, name TEXT, config_json TEXT)');
        $pdo->exec('CREATE TABLE luna_woocommerce_transfer_queue (id INTEGER PRIMARY KEY, woocommerce_connection_id INTEGER, topic TEXT, source_order_id TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE luna_reports (id INTEGER PRIMARY KEY AUTOINCREMENT, job_run_id INTEGER NULL, workspace_id INTEGER NULL, type TEXT, subject TEXT, body TEXT, recipients TEXT NULL, sent_at TEXT NULL, status TEXT, error_message TEXT NULL, created_at TEXT, updated_at TEXT)');

        return $pdo;
    }

    private function systemDatabase(): SystemDatabase
    {
        return new SystemDatabase(new DatabaseConfig(new Config()), new PdoConnectionFactory());
    }
}
