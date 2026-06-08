<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Config\Config;
use Luna\Connections\ConnectionProfileData;
use Luna\Connections\ExternalPdoConnectionFactory;
use Luna\Database\DatabaseConfig;
use Luna\Database\PdoConnectionFactory;
use Luna\Database\SystemDatabase;
use Luna\Repository\ConnectionProfileRepository;
use Luna\Repository\WorkspaceRepository;
use Luna\Security\EncryptionService;
use Luna\TransferDb\TransferDbConnectionResolver;
use Luna\TransferDb\TransferDbEndpointSnapshotWriter;
use Luna\TransferDb\TransferDbSchemaManager;
use Luna\TransferDb\TransferDbWebhookEventWriter;
use Luna\TransferDb\TransferDbWriter;
use PDO;
use PHPUnit\Framework\TestCase;

final class TransferDbFoundationTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['APP_KEY'] = 'unit-test-app-key';
    }

    protected function tearDown(): void
    {
        unset($_ENV['APP_KEY']);
    }

    public function testConnectionCanBeMarkedAsTransferDb(): void
    {
        self::assertContains('transfer_db', ConnectionProfileData::roles());

        $values = ConnectionProfileData::normalize([
            'name' => 'Toolbox TransferDB',
            'type' => 'transfer_db',
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'database_name' => 'toolbox_transfer',
            'username' => 'transfer',
        ]);

        self::assertSame('transfer_db', $values['type']);
        self::assertSame(0, $values['read_only']);
        self::assertSame([], ConnectionProfileData::validate($values));
    }

    public function testWorkspaceCanReferenceTransferDbConnection(): void
    {
        $pdo = $this->systemPdo();
        $repository = new WorkspaceRepository($this->database(), $pdo);
        $workspaceId = $repository->create('asf-in-stocks', 'AsfInStocks', null, 7);
        $workspace = $repository->find($workspaceId);

        self::assertNotNull($workspace);
        self::assertSame(7, (int) ($workspace['transfer_db_connection_id'] ?? 0));
    }

    public function testSchemaManagerCreatesOnlyLunaTransferTables(): void
    {
        $pdo = $this->transferPdo();
        $manager = new TransferDbSchemaManager();
        $status = $manager->migrate($pdo);

        self::assertTrue($status['schema_current']);
        foreach ($manager->tableNames() as $table) {
            self::assertStringStartsWith('luna_', $table);
            self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = " . $pdo->quote($table))->fetchColumn());
        }
    }

    public function testCheckDetectsMissingAndExistingTables(): void
    {
        $pdo = $this->transferPdo();
        $manager = new TransferDbSchemaManager();

        self::assertNotEmpty($manager->status($pdo)['missing_tables']);
        $manager->migrate($pdo);
        self::assertSame([], $manager->status($pdo)['missing_tables']);
    }

    public function testWebhookEventWriterStoresEventWithoutSecrets(): void
    {
        $pdo = $this->transferPdo();
        $writer = $this->webhookWriter();
        $result = $writer->writeResolved($pdo, ['id' => 1, 'slug' => 'asf-in-stocks'], [
            'provider' => 'woocommerce',
            'trigger_key' => 'wc-order-updated',
            'configured_topic' => 'order.updated',
            'topic' => 'order.updated',
            'resource' => 'order',
            'event' => 'updated',
            'delivery_id' => 'delivery-1',
            'webhook_id' => 'webhook-1',
            'source_domain' => 'shop.example.test',
            'source_order_id' => '10001',
            'signature_valid' => true,
            'payload_hash' => hash('sha256', '{"id":10001}'),
            'received_at' => '2026-06-09T10:00:00+00:00',
            'transfer_payload' => ['id' => 10001, 'status' => 'processing', 'api_key' => 'plain-secret'],
        ], [
            'Authorization' => 'Bearer plain-secret',
            'X-WC-Webhook-Signature' => 'signature-value',
        ]);

        self::assertSame(1, (int) $result['batch_id']);
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM luna_transfer_webhook_events')->fetchColumn());
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM luna_transfer_records')->fetchColumn());
        $stored = (string) $pdo->query('SELECT headers_json || payload_json FROM luna_transfer_webhook_events')->fetchColumn();
        self::assertStringNotContainsString('plain-secret', $stored);
        self::assertStringContainsString('***', $stored);
    }

    public function testEndpointSnapshotWriterStoresItemsAsRecords(): void
    {
        $pdo = $this->transferPdo();
        $writer = $this->endpointWriter();
        $result = $writer->writeResolved($pdo, ['id' => 1, 'slug' => 'asf-in-stocks'], [
            'id' => 10,
            'endpoint_key' => 'isr_prices',
            'mapping_set_id' => 20,
        ], [
            'success' => true,
            'count' => 2,
            'items' => [
                ['model' => 'S001', 'price' => 85],
                ['model' => 'S002', 'price' => 95],
            ],
        ]);

        self::assertSame(2, (int) $result['record_count']);
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM luna_transfer_endpoint_snapshots')->fetchColumn());
        self::assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM luna_transfer_records')->fetchColumn());
        self::assertSame('S001', (string) $pdo->query('SELECT record_key FROM luna_transfer_records ORDER BY id LIMIT 1')->fetchColumn());
    }

    public function testCliUsageKeepsExistingCommandsAndAddsTransferDbCommands(): void
    {
        $bin = file_get_contents(dirname(__DIR__, 2) . '/bin/luna');
        self::assertIsString($bin);

        foreach ([
            'endpoint:export',
            'integration:export',
            'export:woocommerce:list',
            'export:woocommerce:run',
            'process:run',
            'transferdb:check',
            'transferdb:migrate',
        ] as $command) {
            self::assertStringContainsString($command, $bin);
        }
    }

    private function webhookWriter(): TransferDbWebhookEventWriter
    {
        return new TransferDbWebhookEventWriter($this->unusedResolver(), new TransferDbSchemaManager(), new TransferDbWriter());
    }

    private function endpointWriter(): TransferDbEndpointSnapshotWriter
    {
        return new TransferDbEndpointSnapshotWriter($this->unusedResolver(), new TransferDbSchemaManager(), new TransferDbWriter());
    }

    private function unusedResolver(): TransferDbConnectionResolver
    {
        $pdo = $this->systemPdo();
        $database = $this->database();

        return new TransferDbConnectionResolver(
            new WorkspaceRepository($database, $pdo),
            new ConnectionProfileRepository($database, new EncryptionService(new Config()), $pdo),
            new ExternalPdoConnectionFactory(),
        );
    }

    private function database(): SystemDatabase
    {
        return new SystemDatabase(new DatabaseConfig(new Config()), new PdoConnectionFactory());
    }

    private function systemPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'));
        $pdo->exec('CREATE TABLE luna_workspaces (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slug TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            description TEXT NULL,
            status TEXT NOT NULL DEFAULT "active",
            transfer_db_connection_id INTEGER NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE luna_connection_profiles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER NULL,
            name TEXT NOT NULL,
            type TEXT NOT NULL,
            driver TEXT NOT NULL,
            host TEXT NULL,
            port INTEGER NULL,
            database_name TEXT NULL,
            username TEXT NULL,
            read_only INTEGER NOT NULL DEFAULT 1,
            is_active INTEGER NOT NULL DEFAULT 1,
            config_json TEXT NULL,
            notes TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE luna_connection_secrets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            connection_profile_id INTEGER NOT NULL,
            secret_key TEXT NOT NULL,
            secret_value_encrypted TEXT NOT NULL,
            encryption_version TEXT NOT NULL DEFAULT "v1",
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');

        return $pdo;
    }

    private function transferPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }
}
