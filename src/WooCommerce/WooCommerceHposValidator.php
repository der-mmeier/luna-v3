<?php

declare(strict_types=1);

namespace Luna\WooCommerce;

use PDO;
use Throwable;

final class WooCommerceHposValidator
{
    private const MIN_VERSION = '10.7.0';

    private const REQUIRED_TABLES = [
        'wc_orders',
        'wc_order_addresses',
        'wc_order_operational_data',
        'wc_orders_meta',
        'woocommerce_order_items',
        'woocommerce_order_itemmeta',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const REQUIRED_COLUMNS = [
        'wc_orders' => ['id', 'status', 'currency', 'type', 'tax_amount', 'total_amount', 'customer_id', 'billing_email', 'date_created_gmt', 'date_updated_gmt', 'parent_order_id', 'payment_method', 'payment_method_title', 'transaction_id', 'ip_address', 'user_agent', 'customer_note'],
        'wc_order_addresses' => ['id', 'order_id', 'address_type', 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'email', 'phone'],
        'wc_order_operational_data' => ['order_id', 'created_via', 'woocommerce_version', 'prices_include_tax', 'coupon_usages_are_counted', 'download_permission_granted', 'cart_hash', 'new_order_email_sent', 'order_key', 'order_stock_reduced', 'date_paid_gmt', 'date_completed_gmt', 'shipping_tax_amount', 'shipping_total_amount', 'discount_tax_amount', 'discount_total_amount', 'recorded_sales'],
        'wc_orders_meta' => ['id', 'order_id', 'meta_key', 'meta_value'],
        'woocommerce_order_items' => ['order_item_id', 'order_id', 'order_item_name', 'order_item_type'],
        'woocommerce_order_itemmeta' => ['meta_id', 'order_item_id', 'meta_key', 'meta_value'],
    ];

    public function validate(PDO $pdo): WooCommerceValidationResult
    {
        $prefix = $this->detectPrefix($pdo);
        $errors = [];
        $warnings = [
            'HPOS Data Caching wird in Luna v2.0.0 nicht als produktiver Datenpfad verwendet.',
        ];

        if ($prefix === null) {
            return new WooCommerceValidationResult(
                null,
                null,
                false,
                false,
                false,
                false,
                false,
                0,
                null,
                null,
                ['wc_orders'],
                ['WooCommerce-HPOS-Tabelle wc_orders wurde nicht gefunden.'],
                $warnings,
            );
        }

        $version = $this->readOption($pdo, $prefix, 'woocommerce_version');
        $versionAccepted = is_string($version) && version_compare($version, self::MIN_VERSION, '>=');
        if (! $versionAccepted) {
            $errors[] = sprintf(
                'Diese WooCommerce-Anbindung erfordert WooCommerce >= 10.7.0 mit gültiger HPOS-Struktur. Erkannte Version: %s. Der Import wurde blockiert.',
                $version === null || $version === '' ? 'unbekannt' : $version,
            );
        }

        $hposOptions = $this->readHposOptions($pdo, $prefix);
        $hposEnabled = ($hposOptions['woocommerce_custom_orders_table_enabled'] ?? '') === 'yes';
        $hposAuthoritative = $hposEnabled;
        if (! $hposEnabled) {
            $errors[] = 'HPOS ist nicht authoritative aktiv. Luna v2.0.0 akzeptiert für WooCommerce >= 10.7.0 ausschließlich HPOS als produktive Order-Quelle.';
        }

        $missingSchemaParts = $this->missingSchemaParts($pdo, $prefix);
        $schemaComplete = $missingSchemaParts === [];
        if (! $schemaComplete) {
            $errors[] = 'Die WooCommerce-Version ist grundsätzlich zulässig, aber die benötigte HPOS-Struktur ist nicht vollständig. Fehlend: ' . implode(', ', $missingSchemaParts);
        }

        $orderStats = $schemaComplete ? $this->orderStats($pdo, $prefix) : ['count' => 0, 'oldest' => null, 'newest' => null];

        return new WooCommerceValidationResult(
            $prefix,
            $version,
            $versionAccepted,
            $hposEnabled,
            $hposAuthoritative,
            $schemaComplete,
            $versionAccepted && $hposEnabled && $schemaComplete,
            (int) $orderStats['count'],
            is_string($orderStats['oldest']) ? $orderStats['oldest'] : null,
            is_string($orderStats['newest']) ? $orderStats['newest'] : null,
            $missingSchemaParts,
            $errors,
            $warnings,
            $hposOptions,
        );
    }

    private function detectPrefix(PDO $pdo): ?string
    {
        foreach ($this->tableNames($pdo, '%wc_orders') as $tableName) {
            if (str_ends_with($tableName, 'wc_orders')) {
                return substr($tableName, 0, -strlen('wc_orders'));
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function tableNames(PDO $pdo, string $like): array
    {
        if ($this->driver($pdo) === 'sqlite') {
            $statement = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE :like ORDER BY LENGTH(name), name");
            $statement->execute(['like' => $like]);

            return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
        }

        $statement = $pdo->prepare(
            'SELECT table_name
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name LIKE :like
             ORDER BY LENGTH(table_name), table_name',
        );
        $statement->execute(['like' => $like]);

        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private function readOption(PDO $pdo, string $prefix, string $optionName): ?string
    {
        if (! $this->tableExists($pdo, $prefix . 'options')) {
            return null;
        }

        $statement = $pdo->prepare(sprintf(
            'SELECT option_value FROM %s WHERE option_name = :option_name',
            $this->quoteIdentifier($prefix . 'options'),
        ));
        $statement->execute(['option_name' => $optionName]);
        $value = $statement->fetchColumn();

        return $value === false ? null : (string) $value;
    }

    /**
     * @return array<string, string>
     */
    private function readHposOptions(PDO $pdo, string $prefix): array
    {
        if (! $this->tableExists($pdo, $prefix . 'options')) {
            return [];
        }

        $names = [
            'woocommerce_custom_orders_table_enabled',
            'woocommerce_custom_orders_table_data_sync_enabled',
            'woocommerce_auto_flip_authoritative_table_roles',
        ];
        $placeholders = [];
        $params = [];
        foreach ($names as $index => $name) {
            $placeholder = 'option_' . $index;
            $placeholders[] = ':' . $placeholder;
            $params[$placeholder] = $name;
        }

        $statement = $pdo->prepare(sprintf(
            'SELECT option_name, option_value FROM %s WHERE option_name IN (%s)',
            $this->quoteIdentifier($prefix . 'options'),
            implode(', ', $placeholders),
        ));
        $statement->execute($params);

        $options = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $options[(string) $row['option_name']] = (string) $row['option_value'];
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    private function missingSchemaParts(PDO $pdo, string $prefix): array
    {
        $missing = [];
        foreach (self::REQUIRED_TABLES as $table) {
            $fullTable = $prefix . $table;
            if (! $this->tableExists($pdo, $fullTable)) {
                $missing[] = $fullTable;
                continue;
            }

            foreach (self::REQUIRED_COLUMNS[$table] ?? [] as $column) {
                if (! $this->columnExists($pdo, $fullTable, $column)) {
                    $missing[] = $fullTable . '.' . $column;
                }
            }
        }

        return $missing;
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        try {
            if ($this->driver($pdo) === 'sqlite') {
                $statement = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = :table");
                $statement->execute(['table' => $table]);

                return (int) $statement->fetchColumn() > 0;
            }

            $statement = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = :table',
            );
            $statement->execute(['table' => $table]);

            return (int) $statement->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        try {
            if ($this->driver($pdo) === 'sqlite') {
                $statement = $pdo->query(sprintf('PRAGMA table_info(%s)', $this->quoteIdentifier($table)));
                foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    if ((string) $row['name'] === $column) {
                        return true;
                    }
                }

                return false;
            }

            $statement = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = :table
                   AND column_name = :column',
            );
            $statement->execute(['table' => $table, 'column' => $column]);

            return (int) $statement->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array{count: int, oldest: ?string, newest: ?string}
     */
    private function orderStats(PDO $pdo, string $prefix): array
    {
        $statement = $pdo->query(sprintf(
            "SELECT COUNT(*) AS order_count, MIN(date_created_gmt) AS oldest_order_at, MAX(date_created_gmt) AS newest_order_at
             FROM %s
             WHERE type = 'shop_order'",
            $this->quoteIdentifier($prefix . 'wc_orders'),
        ));
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'count' => (int) ($row['order_count'] ?? 0),
            'oldest' => isset($row['oldest_order_at']) ? (string) $row['oldest_order_at'] : null,
            'newest' => isset($row['newest_order_at']) ? (string) $row['newest_order_at'] : null,
        ];
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function driver(PDO $pdo): string
    {
        return (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }
}
