<?php

declare(strict_types=1);

namespace Luna\Tests\Unit;

use Luna\WooCommerce\WooCommerceHposValidator;
use PDO;
use PHPUnit\Framework\TestCase;

final class WooCommerceHposValidatorTest extends TestCase
{
    public function testAcceptsWooCommerceTenSevenWithCompleteHposSchema(): void
    {
        $pdo = $this->woocommercePdo('10.7.0', 'yes');

        $result = (new WooCommerceHposValidator())->validate($pdo);

        self::assertSame('shop_', $result->tablePrefix);
        self::assertSame('10.7.0', $result->woocommerceVersion);
        self::assertTrue($result->versionAccepted);
        self::assertTrue($result->hposEnabled);
        self::assertTrue($result->hposAuthoritative);
        self::assertTrue($result->schemaComplete);
        self::assertTrue($result->transferReady);
        self::assertSame(1, $result->orderCount);
    }

    public function testBlocksWooCommerceVersionBelowTenSeven(): void
    {
        $pdo = $this->woocommercePdo('10.6.9', 'yes');

        $result = (new WooCommerceHposValidator())->validate($pdo);

        self::assertFalse($result->versionAccepted);
        self::assertFalse($result->transferReady);
        self::assertNotEmpty($result->errors);
    }

    public function testBlocksWhenHposIsDisabled(): void
    {
        $pdo = $this->woocommercePdo('10.8.1', 'no');

        $result = (new WooCommerceHposValidator())->validate($pdo);

        self::assertFalse($result->hposEnabled);
        self::assertFalse($result->transferReady);
        self::assertStringContainsString('HPOS ist nicht authoritative aktiv', implode(' ', $result->errors));
    }

    public function testBlocksMissingRequiredHposSchema(): void
    {
        $pdo = $this->woocommercePdo('10.8.1', 'yes');
        $pdo->exec('DROP TABLE shop_wc_order_addresses');

        $result = (new WooCommerceHposValidator())->validate($pdo);

        self::assertFalse($result->schemaComplete);
        self::assertFalse($result->transferReady);
        self::assertContains('shop_wc_order_addresses', $result->missingSchemaParts);
    }

    private function woocommercePdo(string $version, string $hposEnabled): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $pdo->exec('CREATE TABLE shop_options (option_name TEXT PRIMARY KEY, option_value TEXT)');
        $insertOption = $pdo->prepare('INSERT INTO shop_options (option_name, option_value) VALUES (:name, :value)');
        $insertOption->execute(['name' => 'woocommerce_version', 'value' => $version]);
        $insertOption->execute(['name' => 'woocommerce_custom_orders_table_enabled', 'value' => $hposEnabled]);
        $insertOption->execute(['name' => 'woocommerce_custom_orders_table_data_sync_enabled', 'value' => 'yes']);
        $insertOption->execute(['name' => 'woocommerce_auto_flip_authoritative_table_roles', 'value' => 'yes']);

        $pdo->exec('CREATE TABLE shop_wc_orders (
            id INTEGER PRIMARY KEY,
            status TEXT,
            currency TEXT,
            type TEXT,
            tax_amount REAL,
            total_amount REAL,
            customer_id INTEGER,
            billing_email TEXT,
            date_created_gmt TEXT,
            date_updated_gmt TEXT,
            parent_order_id INTEGER,
            payment_method TEXT,
            payment_method_title TEXT,
            transaction_id TEXT,
            ip_address TEXT,
            user_agent TEXT,
            customer_note TEXT
        )');
        $pdo->exec("INSERT INTO shop_wc_orders (id, status, currency, type, tax_amount, total_amount, customer_id, billing_email, date_created_gmt, date_updated_gmt, parent_order_id, payment_method, payment_method_title, transaction_id, ip_address, user_agent, customer_note)
            VALUES (10001, 'wc-processing', 'EUR', 'shop_order', 0, 150, 1, 'kunde@example.de', '2026-06-02 10:00:00', '2026-06-02 11:00:00', 0, 'stripe', 'Stripe', 'txn', '127.0.0.1', 'agent', '')");
        $pdo->exec('CREATE TABLE shop_wc_order_addresses (
            id INTEGER PRIMARY KEY,
            order_id INTEGER,
            address_type TEXT,
            first_name TEXT,
            last_name TEXT,
            company TEXT,
            address_1 TEXT,
            address_2 TEXT,
            city TEXT,
            state TEXT,
            postcode TEXT,
            country TEXT,
            email TEXT,
            phone TEXT
        )');
        $pdo->exec('CREATE TABLE shop_wc_order_operational_data (
            order_id INTEGER PRIMARY KEY,
            created_via TEXT,
            woocommerce_version TEXT,
            prices_include_tax INTEGER,
            coupon_usages_are_counted INTEGER,
            download_permission_granted INTEGER,
            cart_hash TEXT,
            new_order_email_sent INTEGER,
            order_key TEXT,
            order_stock_reduced INTEGER,
            date_paid_gmt TEXT,
            date_completed_gmt TEXT,
            shipping_tax_amount REAL,
            shipping_total_amount REAL,
            discount_tax_amount REAL,
            discount_total_amount REAL,
            recorded_sales INTEGER
        )');
        $pdo->exec('CREATE TABLE shop_wc_orders_meta (
            id INTEGER PRIMARY KEY,
            order_id INTEGER,
            meta_key TEXT,
            meta_value TEXT
        )');
        $pdo->exec('CREATE TABLE shop_woocommerce_order_items (
            order_item_id INTEGER PRIMARY KEY,
            order_id INTEGER,
            order_item_name TEXT,
            order_item_type TEXT
        )');
        $pdo->exec('CREATE TABLE shop_woocommerce_order_itemmeta (
            meta_id INTEGER PRIMARY KEY,
            order_item_id INTEGER,
            meta_key TEXT,
            meta_value TEXT
        )');

        return $pdo;
    }
}
