<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Config\Config;
use Luna\Database\DatabaseConfig;
use Luna\Database\PdoConnectionFactory;
use Luna\Database\SystemDatabase;
use Luna\Repository\WooCommerceIntegrationRepository;
use Luna\Security\EncryptionService;
use Luna\WooCommerce\WooCommerceWebhookHandler;
use PDO;
use PHPUnit\Framework\TestCase;

final class WooCommerceWebhookHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['APP_KEY'] = 'unit-test-app-key';
    }

    protected function tearDown(): void
    {
        unset($_ENV['APP_KEY']);
    }

    public function testValidWebhookStoresEventAndQueuesTransfer(): void
    {
        [$pdo, $handler] = $this->fixture('webhook-secret');
        $rawBody = '{"id":10001,"status":"processing"}';
        $signature = base64_encode(hash_hmac('sha256', $rawBody, 'webhook-secret', true));

        $result = $handler->handle('token-123', [
            'X-WC-Webhook-Signature' => $signature,
            'X-WC-Webhook-Topic' => 'order.updated',
            'X-WC-Webhook-Resource' => 'order',
            'X-WC-Webhook-Event' => 'updated',
            'X-WC-Webhook-Delivery-ID' => 'delivery-1',
        ], $rawBody);

        self::assertSame(200, $result['status']);
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM luna_woocommerce_webhook_events WHERE signature_valid = 1')->fetchColumn());
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM luna_woocommerce_transfer_queue WHERE source_order_id = 10001')->fetchColumn());
        $storedHeaders = (string) $pdo->query('SELECT raw_headers_json FROM luna_woocommerce_webhook_events')->fetchColumn();
        self::assertStringContainsString('[present]', $storedHeaders);
        self::assertStringNotContainsString($signature, $storedHeaders);
    }

    public function testInvalidWebhookStoresRejectedEventWithoutQueue(): void
    {
        [$pdo, $handler] = $this->fixture('webhook-secret');

        $result = $handler->handle('token-123', [
            'X-WC-Webhook-Signature' => 'invalid',
            'X-WC-Webhook-Topic' => 'order.updated',
            'X-WC-Webhook-Resource' => 'order',
            'X-WC-Webhook-Event' => 'updated',
        ], '{"id":10001}');

        self::assertSame(401, $result['status']);
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM luna_woocommerce_webhook_events WHERE signature_valid = 0')->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM luna_woocommerce_transfer_queue')->fetchColumn());
    }

    /**
     * @return array{0: PDO, 1: WooCommerceWebhookHandler}
     */
    private function fixture(string $secret): array
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->createSystemTables($pdo);

        $database = new SystemDatabase(new DatabaseConfig(new Config()), new PdoConnectionFactory());
        $repository = new WooCommerceIntegrationRepository($database, new EncryptionService(new Config()), $pdo);
        $connectionId = $repository->createConnection([
            'workspace_id' => 1,
            'connection_id' => 10,
            'name' => 'WooCommerce Test',
        ]);
        $pdo->prepare('UPDATE luna_woocommerce_connections SET connection_token = :token WHERE id = :id')
            ->execute(['token' => 'token-123', 'id' => $connectionId]);
        $repository->createWebhookConfig([
            'workspace_id' => 1,
            'woocommerce_connection_id' => $connectionId,
            'webhook_name' => 'Luna Order Updated',
            'topic' => 'order.updated',
            'delivery_url' => 'https://example.test/api/webhooks/woocommerce/token-123',
            'is_required' => true,
        ], $secret);

        return [$pdo, new WooCommerceWebhookHandler($repository)];
    }

    private function createSystemTables(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE luna_woocommerce_connections (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER NULL,
            connection_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            connection_token TEXT NOT NULL,
            detected_table_prefix TEXT NULL,
            detected_woocommerce_version TEXT NULL,
            storage_mode TEXT NOT NULL DEFAULT "hpos",
            hpos_enabled INTEGER NOT NULL DEFAULT 0,
            hpos_authoritative INTEGER NOT NULL DEFAULT 0,
            hpos_data_caching_allowed INTEGER NOT NULL DEFAULT 0,
            hpos_data_caching_warning_acknowledged INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE luna_woocommerce_webhook_configs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER NULL,
            woocommerce_connection_id INTEGER NOT NULL,
            webhook_name TEXT NOT NULL,
            topic TEXT NOT NULL,
            delivery_url TEXT NOT NULL,
            secret_encrypted TEXT NULL,
            expected_status TEXT NOT NULL DEFAULT "active",
            api_version TEXT NOT NULL DEFAULT "WP REST API Integration v3",
            is_required INTEGER NOT NULL DEFAULT 1,
            last_seen_status TEXT NULL,
            last_seen_webhook_id TEXT NULL,
            last_seen_at TEXT NULL,
            validation_status TEXT NOT NULL DEFAULT "unknown",
            validation_message TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE luna_woocommerce_webhook_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER NULL,
            woocommerce_connection_id INTEGER NOT NULL,
            topic TEXT NOT NULL,
            resource TEXT NULL,
            event_action TEXT NULL,
            source_order_id TEXT NULL,
            delivery_id TEXT NULL,
            signature_valid INTEGER NOT NULL DEFAULT 0,
            raw_headers_json TEXT NULL,
            raw_payload_json TEXT NULL,
            received_at TEXT NOT NULL,
            processed_at TEXT NULL,
            processing_status TEXT NOT NULL DEFAULT "received",
            processing_message TEXT NULL,
            created_transfer_job_id INTEGER NULL
        )');
        $pdo->exec('CREATE TABLE luna_woocommerce_transfer_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER NULL,
            woocommerce_connection_id INTEGER NOT NULL,
            webhook_event_id INTEGER NULL,
            source_order_id TEXT NOT NULL,
            topic TEXT NOT NULL,
            reason TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT "pending",
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');
    }
}
