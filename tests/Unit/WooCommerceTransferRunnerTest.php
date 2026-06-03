<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\Config\Config;
use Luna\Connections\ExternalPdoConnectionFactory;
use Luna\Database\DatabaseConfig;
use Luna\Database\PdoConnectionFactory;
use Luna\Database\SystemDatabase;
use Luna\Repository\ConnectionProfileRepository;
use Luna\Repository\WooCommerceIntegrationRepository;
use Luna\Security\EncryptionService;
use Luna\WooCommerce\WooCommerceHposOrderReader;
use Luna\WooCommerce\WooCommerceHposValidator;
use Luna\WooCommerce\WooCommerceTransferRunner;
use Luna\WooCommerce\WooCommerceTransferWriter;
use PDO;
use PHPUnit\Framework\TestCase;

final class WooCommerceTransferRunnerTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['APP_KEY']);
    }

    public function testInitialImportProcessesQueueAndWritesStagingTablesIdempotently(): void
    {
        [$systemPdo, $sourcePdo, $repository, $runner, $connectionId] = $this->fixture();

        $queueId = $repository->queueTransfer([
            'workspace_id' => 1,
            'woocommerce_connection_id' => $connectionId,
            'source_order_id' => '*',
            'topic' => 'initial_import',
            'reason' => 'initial WooCommerce HPOS import',
            'status' => 'pending',
        ]);

        $result = $runner->run($connectionId, $queueId, 1);

        self::assertSame(1, $result['processed']);
        self::assertSame(1, $result['success']);
        self::assertSame(2, $result['orders_written']);
        self::assertSame(2, (int) $systemPdo->query('SELECT COUNT(*) FROM luna_woocommerce_order_headers')->fetchColumn());
        self::assertSame(2, (int) $systemPdo->query('SELECT COUNT(*) FROM luna_woocommerce_order_addresses')->fetchColumn());
        self::assertSame(2, (int) $systemPdo->query('SELECT COUNT(*) FROM luna_woocommerce_order_items')->fetchColumn());
        self::assertSame(16, (int) $systemPdo->query('SELECT COUNT(*) FROM luna_woocommerce_order_itemmeta_raw')->fetchColumn());
        self::assertSame(2, (int) $systemPdo->query('SELECT COUNT(*) FROM luna_woocommerce_order_meta_raw')->fetchColumn());
        self::assertSame('success', (string) $systemPdo->query('SELECT status FROM luna_woocommerce_transfer_queue WHERE id = ' . $queueId)->fetchColumn());
        self::assertSame(1, (int) $systemPdo->query('SELECT attempts FROM luna_woocommerce_transfer_queue WHERE id = ' . $queueId)->fetchColumn());
        self::assertSame('success', (string) $systemPdo->query('SELECT status FROM luna_woocommerce_transfer_runs WHERE queue_id = ' . $queueId)->fetchColumn());

        $secondQueue = $repository->queueTransfer([
            'workspace_id' => 1,
            'woocommerce_connection_id' => $connectionId,
            'source_order_id' => '*',
            'topic' => 'initial_import',
            'reason' => 'initial WooCommerce HPOS import',
            'status' => 'pending',
        ]);
        $runner->run($connectionId, $secondQueue, 1);

        self::assertSame(2, (int) $systemPdo->query('SELECT COUNT(*) FROM luna_woocommerce_order_headers')->fetchColumn());
        self::assertSame(2, (int) $systemPdo->query('SELECT COUNT(*) FROM luna_woocommerce_order_items')->fetchColumn());
        self::assertSame(2, (int) $sourcePdo->query("SELECT COUNT(*) FROM wp_wc_orders WHERE type = 'shop_order'")->fetchColumn());
    }

    public function testSingleOrderQueueUpdatesExistingStagingRowWithoutDuplicate(): void
    {
        [$systemPdo, $sourcePdo, $repository, $runner, $connectionId] = $this->fixture();

        $repository->queueTransfer([
            'workspace_id' => 1,
            'woocommerce_connection_id' => $connectionId,
            'source_order_id' => '*',
            'topic' => 'initial_import',
            'reason' => 'initial WooCommerce HPOS import',
            'status' => 'pending',
        ]);
        $runner->run($connectionId, null, 1);

        $sourcePdo->exec("UPDATE wp_wc_orders SET total_amount = 175 WHERE id = 10001");
        $queueId = $repository->queueTransfer([
            'workspace_id' => 1,
            'woocommerce_connection_id' => $connectionId,
            'source_order_id' => '10001',
            'topic' => 'order.updated',
            'reason' => 'webhook order.updated',
            'status' => 'pending',
        ]);

        $result = $runner->run($connectionId, $queueId, 1);

        self::assertSame(1, $result['orders_written']);
        self::assertSame('175', (string) $systemPdo->query('SELECT total_amount FROM luna_woocommerce_order_headers WHERE source_order_id = 10001')->fetchColumn());
        self::assertSame(2, (int) $systemPdo->query('SELECT COUNT(*) FROM luna_woocommerce_order_headers')->fetchColumn());
        self::assertSame('success', (string) $systemPdo->query('SELECT status FROM luna_woocommerce_transfer_queue WHERE id = ' . $queueId)->fetchColumn());
    }

    public function testMissingSingleOrderFailsQueueAndRun(): void
    {
        [$systemPdo, , $repository, $runner, $connectionId] = $this->fixture();

        $queueId = $repository->queueTransfer([
            'workspace_id' => 1,
            'woocommerce_connection_id' => $connectionId,
            'source_order_id' => '99999',
            'topic' => 'order.updated',
            'reason' => 'webhook order.updated',
            'status' => 'pending',
        ]);

        $result = $runner->run($connectionId, $queueId, 1);

        self::assertSame(1, $result['failed']);
        self::assertSame('failed', (string) $systemPdo->query('SELECT status FROM luna_woocommerce_transfer_queue WHERE id = ' . $queueId)->fetchColumn());
        self::assertStringContainsString('Order 99999 wurde in HPOS nicht gefunden', (string) $systemPdo->query('SELECT last_error FROM luna_woocommerce_transfer_queue WHERE id = ' . $queueId)->fetchColumn());
        self::assertSame('failed', (string) $systemPdo->query('SELECT status FROM luna_woocommerce_transfer_runs WHERE queue_id = ' . $queueId)->fetchColumn());
    }

    /**
     * @return array{0: PDO, 1: PDO, 2: WooCommerceIntegrationRepository, 3: WooCommerceTransferRunner, 4: int}
     */
    private function fixture(): array
    {
        $_ENV['APP_KEY'] = 'unit-test-app-key';

        $systemPdo = new PDO('sqlite::memory:');
        $systemPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $systemPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->createSystemTables($systemPdo);

        $sourcePdo = new PDO('sqlite::memory:');
        $sourcePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sourcePdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->createWooCommerceTables($sourcePdo);

        $database = new SystemDatabase(new DatabaseConfig(new Config()), new PdoConnectionFactory());
        $encryption = new EncryptionService(new Config());
        $connections = new ConnectionProfileRepository($database, $encryption, $systemPdo);
        $repository = new WooCommerceIntegrationRepository($database, $encryption, $systemPdo);
        $connectionId = $repository->createConnection([
            'workspace_id' => 1,
            'connection_id' => 10,
            'name' => 'WooCommerce Test',
        ]);
        $runner = new WooCommerceTransferRunner(
            $repository,
            $connections,
            new ExternalPdoConnectionFactory(),
            new WooCommerceHposValidator(),
            new WooCommerceHposOrderReader(),
            new WooCommerceTransferWriter($database, $systemPdo),
            static fn (array $profile): PDO => $sourcePdo,
        );

        return [$systemPdo, $sourcePdo, $repository, $runner, $connectionId];
    }

    private function createSystemTables(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE luna_workspaces (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO luna_workspaces (id, name) VALUES (1, 'Workspace')");
        $pdo->exec('CREATE TABLE luna_connection_profiles (
            id INTEGER PRIMARY KEY,
            workspace_id INTEGER NULL,
            name TEXT,
            type TEXT,
            driver TEXT,
            host TEXT,
            port INTEGER,
            database_name TEXT,
            username TEXT,
            read_only INTEGER,
            is_active INTEGER,
            config_json TEXT,
            notes TEXT
        )');
        $pdo->exec("INSERT INTO luna_connection_profiles (id, workspace_id, name, type, driver, host, port, database_name, username, read_only, is_active, config_json, notes)
            VALUES (10, 1, 'Woo DB', 'source', 'mysql', 'localhost', 3306, 'woo', 'user', 1, 1, '{\"charset\":\"utf8mb4\"}', '')");

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
        $pdo->exec('CREATE TABLE luna_woocommerce_transfer_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER NULL,
            woocommerce_connection_id INTEGER NOT NULL,
            webhook_event_id INTEGER NULL,
            source_order_id TEXT NOT NULL,
            topic TEXT NOT NULL,
            reason TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT "pending",
            locked_at TEXT NULL,
            started_at TEXT NULL,
            finished_at TEXT NULL,
            attempts INTEGER NOT NULL DEFAULT 0,
            last_error TEXT NULL,
            last_run_id INTEGER NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE luna_woocommerce_transfer_runs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER NULL,
            woocommerce_connection_id INTEGER NOT NULL,
            queue_id INTEGER NULL,
            run_type TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT "pending",
            started_at TEXT NULL,
            finished_at TEXT NULL,
            source_order_id TEXT NULL,
            orders_found INTEGER NOT NULL DEFAULT 0,
            orders_written INTEGER NOT NULL DEFAULT 0,
            addresses_written INTEGER NOT NULL DEFAULT 0,
            items_written INTEGER NOT NULL DEFAULT 0,
            item_meta_written INTEGER NOT NULL DEFAULT 0,
            order_meta_written INTEGER NOT NULL DEFAULT 0,
            refunds_seen INTEGER NOT NULL DEFAULT 0,
            skipped_count INTEGER NOT NULL DEFAULT 0,
            error_count INTEGER NOT NULL DEFAULT 0,
            summary_json TEXT NULL,
            error_message TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');
        $this->createStagingTables($pdo);
    }

    private function createStagingTables(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE luna_woocommerce_order_headers (
            id INTEGER PRIMARY KEY AUTOINCREMENT, workspace_id INTEGER NULL, woocommerce_connection_id INTEGER NOT NULL, source_order_id INTEGER NOT NULL,
            order_status TEXT NULL, currency TEXT NULL, order_type TEXT NOT NULL, tax_amount REAL NULL, total_amount REAL NULL, customer_id INTEGER NULL,
            billing_email TEXT NULL, created_at_gmt TEXT NULL, updated_at_gmt TEXT NULL, parent_order_id INTEGER NULL, payment_method TEXT NULL,
            payment_method_title TEXT NULL, transaction_id TEXT NULL, customer_ip TEXT NULL, customer_user_agent TEXT NULL, customer_note TEXT NULL,
            created_via TEXT NULL, source_woocommerce_version TEXT NULL, prices_include_tax INTEGER NULL, coupon_usages_are_counted INTEGER NULL,
            download_permission_granted INTEGER NULL, cart_hash TEXT NULL, new_order_email_sent INTEGER NULL, order_key TEXT NULL, order_stock_reduced INTEGER NULL,
            paid_at_gmt TEXT NULL, completed_at_gmt TEXT NULL, shipping_tax_amount REAL NULL, shipping_total_amount REAL NULL, discount_tax_amount REAL NULL,
            discount_total_amount REAL NULL, recorded_sales INTEGER NULL, raw_order_json TEXT NULL, last_imported_at TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE luna_woocommerce_order_addresses (
            id INTEGER PRIMARY KEY AUTOINCREMENT, workspace_id INTEGER NULL, woocommerce_connection_id INTEGER NOT NULL, source_address_id INTEGER NOT NULL,
            source_order_id INTEGER NOT NULL, address_type TEXT NOT NULL, first_name TEXT NULL, last_name TEXT NULL, company TEXT NULL, address_1 TEXT NULL,
            address_2 TEXT NULL, city TEXT NULL, state TEXT NULL, postcode TEXT NULL, country TEXT NULL, email TEXT NULL, phone TEXT NULL,
            last_imported_at TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE luna_woocommerce_order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT, workspace_id INTEGER NULL, woocommerce_connection_id INTEGER NOT NULL, source_order_item_id INTEGER NOT NULL,
            source_order_id INTEGER NOT NULL, item_name TEXT NULL, item_type TEXT NOT NULL, product_id INTEGER NULL, variation_id INTEGER NULL, quantity REAL NULL,
            line_subtotal REAL NULL, line_subtotal_tax REAL NULL, line_total REAL NULL, line_tax REAL NULL, tax_class TEXT NULL, tax_data_raw TEXT NULL,
            last_imported_at TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE luna_woocommerce_order_itemmeta_raw (
            id INTEGER PRIMARY KEY AUTOINCREMENT, workspace_id INTEGER NULL, woocommerce_connection_id INTEGER NOT NULL, source_item_meta_id INTEGER NOT NULL,
            source_order_item_id INTEGER NOT NULL, source_order_id INTEGER NOT NULL, meta_key TEXT NULL, meta_value TEXT NULL,
            last_imported_at TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE luna_woocommerce_order_meta_raw (
            id INTEGER PRIMARY KEY AUTOINCREMENT, workspace_id INTEGER NULL, woocommerce_connection_id INTEGER NOT NULL, source_order_meta_id INTEGER NOT NULL,
            source_order_id INTEGER NOT NULL, meta_key TEXT NULL, meta_value TEXT NULL,
            last_imported_at TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL
        )');
    }

    private function createWooCommerceTables(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE wp_options (option_name TEXT PRIMARY KEY, option_value TEXT)');
        $pdo->exec("INSERT INTO wp_options (option_name, option_value) VALUES
            ('woocommerce_version', '10.7.0'),
            ('woocommerce_custom_orders_table_enabled', 'yes'),
            ('woocommerce_custom_orders_table_data_sync_enabled', 'yes'),
            ('woocommerce_auto_flip_authoritative_table_roles', 'yes')");
        $pdo->exec('CREATE TABLE wp_wc_orders (
            id INTEGER PRIMARY KEY, status TEXT, currency TEXT, type TEXT, tax_amount REAL, total_amount REAL, customer_id INTEGER,
            billing_email TEXT, date_created_gmt TEXT, date_updated_gmt TEXT, parent_order_id INTEGER, payment_method TEXT,
            payment_method_title TEXT, transaction_id TEXT, ip_address TEXT, user_agent TEXT, customer_note TEXT
        )');
        $pdo->exec("INSERT INTO wp_wc_orders VALUES
            (10001, 'wc-processing', 'EUR', 'shop_order', 0, 150, 1, 'a@example.test', '2026-06-02 10:00:00', '2026-06-02 11:00:00', 0, 'stripe', 'Stripe', 'txn1', '127.0.0.1', 'agent', ''),
            (10002, 'wc-completed', 'EUR', 'shop_order', 0, 85, 2, 'b@example.test', '2026-06-02 12:00:00', '2026-06-02 13:00:00', 0, 'paypal', 'PayPal', 'txn2', '127.0.0.2', 'agent', ''),
            (20001, 'wc-refunded', 'EUR', 'shop_order_refund', 0, -10, 0, '', '2026-06-02 14:00:00', '2026-06-02 14:00:00', 10001, '', '', '', '', '', '')");
        $pdo->exec('CREATE TABLE wp_wc_order_operational_data (
            order_id INTEGER PRIMARY KEY, created_via TEXT, woocommerce_version TEXT, prices_include_tax INTEGER, coupon_usages_are_counted INTEGER,
            download_permission_granted INTEGER, cart_hash TEXT, new_order_email_sent INTEGER, order_key TEXT, order_stock_reduced INTEGER,
            date_paid_gmt TEXT, date_completed_gmt TEXT, shipping_tax_amount REAL, shipping_total_amount REAL, discount_tax_amount REAL,
            discount_total_amount REAL, recorded_sales INTEGER
        )');
        $pdo->exec("INSERT INTO wp_wc_order_operational_data VALUES
            (10001, 'checkout', '10.7.0', 0, 1, 0, 'cart1', 1, 'key1', 1, '2026-06-02 10:10:00', NULL, 0, 5, 0, 0, 1),
            (10002, 'checkout', '10.7.0', 0, 1, 0, 'cart2', 1, 'key2', 1, '2026-06-02 12:10:00', '2026-06-02 13:00:00', 0, 5, 0, 0, 1)");
        $pdo->exec('CREATE TABLE wp_wc_order_addresses (
            id INTEGER PRIMARY KEY, order_id INTEGER, address_type TEXT, first_name TEXT, last_name TEXT, company TEXT, address_1 TEXT, address_2 TEXT,
            city TEXT, state TEXT, postcode TEXT, country TEXT, email TEXT, phone TEXT
        )');
        $pdo->exec("INSERT INTO wp_wc_order_addresses VALUES
            (1, 10001, 'billing', 'A', 'Kunde', '', 'Street 1', '', 'City', '', '12345', 'DE', 'a@example.test', '123'),
            (2, 10002, 'billing', 'B', 'Kunde', '', 'Street 2', '', 'City', '', '12345', 'DE', 'b@example.test', '456')");
        $pdo->exec('CREATE TABLE wp_woocommerce_order_items (
            order_item_id INTEGER PRIMARY KEY, order_id INTEGER, order_item_name TEXT, order_item_type TEXT
        )');
        $pdo->exec("INSERT INTO wp_woocommerce_order_items VALUES
            (11, 10001, 'Ring A', 'line_item'),
            (12, 10002, 'Ring B', 'line_item')");
        $pdo->exec('CREATE TABLE wp_woocommerce_order_itemmeta (
            meta_id INTEGER PRIMARY KEY, order_item_id INTEGER, meta_key TEXT, meta_value TEXT
        )');
        $metaId = 1;
        foreach ([[11, 10001], [12, 10002]] as [$itemId]) {
            foreach (['_product_id' => '501', '_variation_id' => '601', '_qty' => '1', '_line_subtotal' => '85', '_line_subtotal_tax' => '0', '_line_total' => '85', '_line_tax' => '0', '_tax_class' => ''] as $key => $value) {
                $pdo->prepare('INSERT INTO wp_woocommerce_order_itemmeta (meta_id, order_item_id, meta_key, meta_value) VALUES (:id, :item_id, :key, :value)')
                    ->execute(['id' => $metaId++, 'item_id' => $itemId, 'key' => $key, 'value' => $value]);
            }
        }
        $pdo->exec('CREATE TABLE wp_wc_orders_meta (
            id INTEGER PRIMARY KEY, order_id INTEGER, meta_key TEXT, meta_value TEXT
        )');
        $pdo->exec("INSERT INTO wp_wc_orders_meta VALUES
            (1, 10001, '_note', 'A'),
            (2, 10002, '_note', 'B')");
    }
}
