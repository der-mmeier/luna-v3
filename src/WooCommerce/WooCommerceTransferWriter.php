<?php

declare(strict_types=1);

namespace Luna\WooCommerce;

use Luna\Database\SystemDatabase;
use PDO;

final class WooCommerceTransferWriter
{
    public function __construct(
        private readonly SystemDatabase $database,
        private readonly ?PDO $pdo = null,
    ) {
    }

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $connection
     * @return array{orders_written: int, addresses_written: int, items_written: int, item_meta_written: int, order_meta_written: int}
     */
    public function writeOrder(array $order, array $connection): array
    {
        $workspaceId = empty($connection['workspace_id']) ? null : (int) $connection['workspace_id'];
        $connectionId = (int) $connection['id'];
        $now = $this->now();
        $header = $order['header'];

        $this->upsert('luna_woocommerce_order_headers', [
            'workspace_id' => $workspaceId,
            'woocommerce_connection_id' => $connectionId,
            'source_order_id' => (int) $header['source_order_id'],
            'order_status' => $header['order_status'] ?? null,
            'currency' => $header['currency'] ?? null,
            'order_type' => $header['order_type'] ?? 'shop_order',
            'tax_amount' => $header['tax_amount'] ?? null,
            'total_amount' => $header['total_amount'] ?? null,
            'customer_id' => $header['customer_id'] ?? null,
            'billing_email' => $header['billing_email'] ?? null,
            'created_at_gmt' => $header['created_at_gmt'] ?? null,
            'updated_at_gmt' => $header['updated_at_gmt'] ?? null,
            'parent_order_id' => $header['parent_order_id'] ?? null,
            'payment_method' => $header['payment_method'] ?? null,
            'payment_method_title' => $header['payment_method_title'] ?? null,
            'transaction_id' => $header['transaction_id'] ?? null,
            'customer_ip' => $header['customer_ip'] ?? null,
            'customer_user_agent' => $header['customer_user_agent'] ?? null,
            'customer_note' => $header['customer_note'] ?? null,
            'created_via' => $header['created_via'] ?? null,
            'source_woocommerce_version' => $header['source_woocommerce_version'] ?? null,
            'prices_include_tax' => $header['prices_include_tax'] ?? null,
            'coupon_usages_are_counted' => $header['coupon_usages_are_counted'] ?? null,
            'download_permission_granted' => $header['download_permission_granted'] ?? null,
            'cart_hash' => $header['cart_hash'] ?? null,
            'new_order_email_sent' => $header['new_order_email_sent'] ?? null,
            'order_key' => $header['order_key'] ?? null,
            'order_stock_reduced' => $header['order_stock_reduced'] ?? null,
            'paid_at_gmt' => $header['paid_at_gmt'] ?? null,
            'completed_at_gmt' => $header['completed_at_gmt'] ?? null,
            'shipping_tax_amount' => $header['shipping_tax_amount'] ?? null,
            'shipping_total_amount' => $header['shipping_total_amount'] ?? null,
            'discount_tax_amount' => $header['discount_tax_amount'] ?? null,
            'discount_total_amount' => $header['discount_total_amount'] ?? null,
            'recorded_sales' => $header['recorded_sales'] ?? null,
            'raw_order_json' => $this->json($header),
            'last_imported_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['woocommerce_connection_id', 'source_order_id']);

        $counts = [
            'orders_written' => 1,
            'addresses_written' => 0,
            'items_written' => 0,
            'item_meta_written' => 0,
            'order_meta_written' => 0,
        ];

        foreach ((array) ($order['addresses'] ?? []) as $address) {
            $this->upsert('luna_woocommerce_order_addresses', [
                'workspace_id' => $workspaceId,
                'woocommerce_connection_id' => $connectionId,
                'source_address_id' => (int) $address['source_address_id'],
                'source_order_id' => (int) $address['source_order_id'],
                'address_type' => (string) $address['address_type'],
                'first_name' => $address['first_name'] ?? null,
                'last_name' => $address['last_name'] ?? null,
                'company' => $address['company'] ?? null,
                'address_1' => $address['address_1'] ?? null,
                'address_2' => $address['address_2'] ?? null,
                'city' => $address['city'] ?? null,
                'state' => $address['state'] ?? null,
                'postcode' => $address['postcode'] ?? null,
                'country' => $address['country'] ?? null,
                'email' => $address['email'] ?? null,
                'phone' => $address['phone'] ?? null,
                'last_imported_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ], ['woocommerce_connection_id', 'source_address_id']);
            $counts['addresses_written']++;
        }

        foreach ((array) ($order['items'] ?? []) as $item) {
            $this->upsert('luna_woocommerce_order_items', [
                'workspace_id' => $workspaceId,
                'woocommerce_connection_id' => $connectionId,
                'source_order_item_id' => (int) $item['source_order_item_id'],
                'source_order_id' => (int) $item['source_order_id'],
                'item_name' => $item['item_name'] ?? null,
                'item_type' => (string) $item['item_type'],
                'product_id' => $item['product_id'] ?? null,
                'variation_id' => $item['variation_id'] ?? null,
                'quantity' => $item['quantity'] ?? null,
                'line_subtotal' => $item['line_subtotal'] ?? null,
                'line_subtotal_tax' => $item['line_subtotal_tax'] ?? null,
                'line_total' => $item['line_total'] ?? null,
                'line_tax' => $item['line_tax'] ?? null,
                'tax_class' => $item['tax_class'] ?? null,
                'tax_data_raw' => $item['tax_data_raw'] ?? null,
                'last_imported_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ], ['woocommerce_connection_id', 'source_order_item_id']);
            $counts['items_written']++;
        }

        foreach ((array) ($order['item_meta'] ?? []) as $meta) {
            $this->upsert('luna_woocommerce_order_itemmeta_raw', [
                'workspace_id' => $workspaceId,
                'woocommerce_connection_id' => $connectionId,
                'source_item_meta_id' => (int) $meta['source_item_meta_id'],
                'source_order_item_id' => (int) $meta['source_order_item_id'],
                'source_order_id' => (int) $meta['source_order_id'],
                'meta_key' => $meta['meta_key'] ?? null,
                'meta_value' => $meta['meta_value'] ?? null,
                'last_imported_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ], ['woocommerce_connection_id', 'source_item_meta_id']);
            $counts['item_meta_written']++;
        }

        foreach ((array) ($order['order_meta'] ?? []) as $meta) {
            $this->upsert('luna_woocommerce_order_meta_raw', [
                'workspace_id' => $workspaceId,
                'woocommerce_connection_id' => $connectionId,
                'source_order_meta_id' => (int) $meta['source_order_meta_id'],
                'source_order_id' => (int) $meta['source_order_id'],
                'meta_key' => $meta['meta_key'] ?? null,
                'meta_value' => $meta['meta_value'] ?? null,
                'last_imported_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ], ['woocommerce_connection_id', 'source_order_meta_id']);
            $counts['order_meta_written']++;
        }

        return $counts;
    }

    public function markOrderDeleted(array $connection, int $sourceOrderId): int
    {
        $statement = $this->pdo()->prepare(
            "UPDATE luna_woocommerce_order_headers
             SET order_status = 'deleted', updated_at = :updated_at, last_imported_at = :last_imported_at
             WHERE woocommerce_connection_id = :connection_id
               AND source_order_id = :source_order_id",
        );
        $now = $this->now();
        $statement->execute([
            'updated_at' => $now,
            'last_imported_at' => $now,
            'connection_id' => (int) $connection['id'],
            'source_order_id' => $sourceOrderId,
        ]);

        return $statement->rowCount();
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $keyColumns
     */
    private function upsert(string $table, array $data, array $keyColumns): void
    {
        $where = [];
        $wherePayload = [];
        foreach ($keyColumns as $column) {
            $where[] = $this->quoteIdentifier($column) . ' = :where_' . $column;
            $wherePayload['where_' . $column] = $data[$column] ?? null;
        }

        $statement = $this->pdo()->prepare(sprintf(
            'SELECT id FROM %s WHERE %s LIMIT 1',
            $this->quoteIdentifier($table),
            implode(' AND ', $where),
        ));
        $statement->execute($wherePayload);
        $id = $statement->fetchColumn();

        if ($id === false) {
            $columns = array_keys($data);
            $insert = $this->pdo()->prepare(sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $this->quoteIdentifier($table),
                implode(', ', array_map($this->quoteIdentifier(...), $columns)),
                implode(', ', array_map(static fn (string $column): string => ':' . $column, $columns)),
            ));
            $insert->execute($data);

            return;
        }

        $updateColumns = array_values(array_filter(
            array_keys($data),
            static fn (string $column): bool => ! in_array($column, $keyColumns, true) && $column !== 'created_at',
        ));
        $sets = array_map(fn (string $column): string => $this->quoteIdentifier($column) . ' = :' . $column, $updateColumns);
        $payload = array_intersect_key($data, array_flip($updateColumns));
        $payload['id'] = $id;
        $update = $this->pdo()->prepare(sprintf(
            'UPDATE %s SET %s WHERE id = :id',
            $this->quoteIdentifier($table),
            implode(', ', $sets),
        ));
        $update->execute($payload);
    }

    private function json(mixed $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $json === false ? '{}' : $json;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function pdo(): PDO
    {
        return $this->pdo ?? $this->database->pdo();
    }
}
