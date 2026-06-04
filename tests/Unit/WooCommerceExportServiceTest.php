<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Config\Config;
use Luna\Database\DatabaseConfig;
use Luna\Database\PdoConnectionFactory;
use Luna\Database\SystemDatabase;
use Luna\Export\WooCommerceExportService;
use Luna\Repository\ExportProfileRepository;
use Luna\Security\EncryptionService;
use PDO;
use PHPUnit\Framework\TestCase;

final class WooCommerceExportServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['APP_KEY'] = 'unit-test-app-key';
    }

    protected function tearDown(): void
    {
        unset($_ENV['APP_KEY']);
    }

    public function testOrdersFullExportsStagingRowsAndRecordsRun(): void
    {
        [$pdo, $repository, $service, $profile] = $this->fixture();

        $result = $service->export($profile, ['limit' => 10], 'cli');

        self::assertTrue($result['success']);
        self::assertSame('orders_full', $result['profile']);
        self::assertSame(2, $result['count']);
        self::assertSame('2026-06-03 11:00:00', $result['watermark']['next_since']);
        self::assertSame(10001, $result['data'][0]['source_order_id']);
        self::assertSame('a@example.test', $result['data'][0]['billing_email']);
        self::assertCount(1, $result['data'][0]['addresses']);
        self::assertCount(1, $result['data'][0]['items']);
        self::assertArrayNotHasKey('meta', $result['data'][0]);
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM luna_export_runs WHERE status = 'success'")->fetchColumn());
        self::assertSame('2026-06-03 11:00:00', (string) $pdo->query('SELECT last_successful_watermark FROM luna_export_profiles WHERE id = ' . (int) $profile['id'])->fetchColumn());

        $filtered = $service->export($repository->find((int) $profile['id']) ?? $profile, [
            'order_id' => '10002',
            'include_raw_meta' => '1',
            'include_item_raw_meta' => '1',
        ], 'cli');

        self::assertTrue($filtered['success']);
        self::assertSame(1, $filtered['count']);
        self::assertSame(10002, $filtered['data'][0]['source_order_id']);
        self::assertArrayHasKey('meta', $filtered['data'][0]);
        self::assertArrayHasKey('meta', $filtered['data'][0]['items'][0]);
    }

    public function testExportAuthSupportsBearerTokenAndHmac(): void
    {
        [, $repository, $service, $profile] = $this->fixture();
        $token = $repository->generateToken();
        $secret = $repository->generateSecret();
        $repository->setToken((int) $profile['id'], $token);
        $repository->setSecret((int) $profile['id'], $secret);
        $profile = $repository->find((int) $profile['id']) ?? $profile;

        self::assertTrue($service->authenticate($profile, 'GET', '/api/exports/woocommerce/orders_full', '', '', 'Bearer ' . $token, []));
        self::assertFalse($service->authenticate($profile, 'GET', '/api/exports/woocommerce/orders_full', '', '', 'Bearer wrong', []));

        $timestamp = (string) time();
        $base = "GET\n/api/exports/woocommerce/orders_full\nlimit=10\n\n" . $timestamp;
        $signature = hash_hmac('sha256', $base, $secret);

        self::assertTrue($service->authenticate($profile, 'GET', '/api/exports/woocommerce/orders_full', 'limit=10', '', '', [
            'x-luna-export-token' => $token,
            'x-luna-timestamp' => $timestamp,
            'x-luna-signature' => $signature,
        ]));
        self::assertFalse($service->authenticate($profile, 'GET', '/api/exports/woocommerce/orders_full', 'limit=10', '', '', [
            'x-luna-export-token' => $token,
            'x-luna-timestamp' => (string) (time() - 1000),
            'x-luna-signature' => $signature,
        ]));
    }

    /**
     * @return array{0: PDO, 1: ExportProfileRepository, 2: WooCommerceExportService, 3: array<string, mixed>}
     */
    private function fixture(): array
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->createTables($pdo);
        $this->seedStagingData($pdo);

        $database = new SystemDatabase(new DatabaseConfig(new Config()), new PdoConnectionFactory());
        $repository = new ExportProfileRepository($database, new EncryptionService(new Config()), $pdo);
        $repository->createDefaultWooCommerceProfiles([
            'id' => 77,
            'workspace_id' => 1,
        ]);
        $profile = $repository->findEnabledWooCommerceProfile('orders_full');
        self::assertIsArray($profile);

        return [$pdo, $repository, new WooCommerceExportService($repository, $database, $pdo), $profile];
    }

    private function createTables(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE luna_export_profiles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER NULL,
            connection_id INTEGER NULL,
            integration_type TEXT NOT NULL,
            profile_key TEXT NOT NULL,
            name TEXT NOT NULL,
            description TEXT NULL,
            is_enabled INTEGER NOT NULL DEFAULT 1,
            export_format TEXT NOT NULL DEFAULT "json",
            auth_mode TEXT NOT NULL DEFAULT "token_hmac",
            token_hash TEXT NULL,
            secret_encrypted TEXT NULL,
            include_raw_meta INTEGER NOT NULL DEFAULT 0,
            include_item_raw_meta INTEGER NOT NULL DEFAULT 0,
            batch_size INTEGER NOT NULL DEFAULT 100,
            last_successful_export_at TEXT NULL,
            last_successful_watermark TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE luna_export_runs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER NULL,
            export_profile_id INTEGER NOT NULL,
            integration_type TEXT NOT NULL,
            profile_key TEXT NOT NULL,
            status TEXT NOT NULL,
            triggered_by TEXT NOT NULL,
            started_at TEXT NULL,
            finished_at TEXT NULL,
            requested_since TEXT NULL,
            requested_until TEXT NULL,
            watermark_before TEXT NULL,
            watermark_after TEXT NULL,
            records_found INTEGER NOT NULL DEFAULT 0,
            records_exported INTEGER NOT NULL DEFAULT 0,
            error_count INTEGER NOT NULL DEFAULT 0,
            summary_json TEXT NULL,
            error_message TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE luna_woocommerce_order_headers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER NULL,
            woocommerce_connection_id INTEGER NOT NULL,
            source_order_id INTEGER NOT NULL,
            order_status TEXT NULL,
            currency TEXT NULL,
            total_amount TEXT NULL,
            billing_email TEXT NULL,
            updated_at_gmt TEXT NULL,
            last_imported_at TEXT NOT NULL,
            raw_order_json TEXT NULL
        )');
        $pdo->exec('CREATE TABLE luna_woocommerce_order_addresses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER NULL,
            woocommerce_connection_id INTEGER NOT NULL,
            source_address_id INTEGER NOT NULL,
            source_order_id INTEGER NOT NULL,
            address_type TEXT NOT NULL,
            first_name TEXT NULL,
            last_name TEXT NULL,
            last_imported_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE luna_woocommerce_order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER NULL,
            woocommerce_connection_id INTEGER NOT NULL,
            source_order_item_id INTEGER NOT NULL,
            source_order_id INTEGER NOT NULL,
            item_name TEXT NULL,
            product_id INTEGER NULL,
            quantity TEXT NULL,
            line_total TEXT NULL,
            last_imported_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE luna_woocommerce_order_itemmeta_raw (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER NULL,
            woocommerce_connection_id INTEGER NOT NULL,
            source_item_meta_id INTEGER NOT NULL,
            source_order_item_id INTEGER NOT NULL,
            source_order_id INTEGER NOT NULL,
            meta_key TEXT NULL,
            meta_value TEXT NULL,
            last_imported_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE luna_woocommerce_order_meta_raw (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER NULL,
            woocommerce_connection_id INTEGER NOT NULL,
            source_order_meta_id INTEGER NOT NULL,
            source_order_id INTEGER NOT NULL,
            meta_key TEXT NULL,
            meta_value TEXT NULL,
            last_imported_at TEXT NOT NULL
        )');
    }

    private function seedStagingData(PDO $pdo): void
    {
        $pdo->exec("INSERT INTO luna_woocommerce_order_headers (workspace_id, woocommerce_connection_id, source_order_id, order_status, currency, total_amount, billing_email, updated_at_gmt, last_imported_at, raw_order_json)
            VALUES
            (1, 77, 10001, 'wc-processing', 'EUR', '150.00000000', 'a@example.test', '2026-06-03 10:00:00', '2026-06-03 10:05:00', '{}'),
            (1, 77, 10002, 'wc-completed', 'EUR', '85.00000000', 'b@example.test', '2026-06-03 11:00:00', '2026-06-03 11:05:00', '{}')");
        $pdo->exec("INSERT INTO luna_woocommerce_order_addresses (workspace_id, woocommerce_connection_id, source_address_id, source_order_id, address_type, first_name, last_name, last_imported_at)
            VALUES
            (1, 77, 1, 10001, 'billing', 'A', 'Kunde', '2026-06-03 10:05:00'),
            (1, 77, 2, 10002, 'billing', 'B', 'Kunde', '2026-06-03 11:05:00')");
        $pdo->exec("INSERT INTO luna_woocommerce_order_items (workspace_id, woocommerce_connection_id, source_order_item_id, source_order_id, item_name, product_id, quantity, line_total, last_imported_at)
            VALUES
            (1, 77, 11, 10001, 'Produkt A', 501, '1.00000000', '150.00000000', '2026-06-03 10:05:00'),
            (1, 77, 12, 10002, 'Produkt B', 502, '1.00000000', '85.00000000', '2026-06-03 11:05:00')");
        $pdo->exec("INSERT INTO luna_woocommerce_order_itemmeta_raw (workspace_id, woocommerce_connection_id, source_item_meta_id, source_order_item_id, source_order_id, meta_key, meta_value, last_imported_at)
            VALUES
            (1, 77, 101, 11, 10001, '_sku', 'SKU-A', '2026-06-03 10:05:00'),
            (1, 77, 102, 12, 10002, '_sku', 'SKU-B', '2026-06-03 11:05:00')");
        $pdo->exec("INSERT INTO luna_woocommerce_order_meta_raw (workspace_id, woocommerce_connection_id, source_order_meta_id, source_order_id, meta_key, meta_value, last_imported_at)
            VALUES
            (1, 77, 201, 10001, '_note', 'A', '2026-06-03 10:05:00'),
            (1, 77, 202, 10002, '_note', 'B', '2026-06-03 11:05:00')");
    }
}
