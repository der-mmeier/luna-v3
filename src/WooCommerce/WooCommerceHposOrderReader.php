<?php

declare(strict_types=1);

namespace Luna\WooCommerce;

use PDO;

final class WooCommerceHposOrderReader
{
    /**
     * @return list<int>
     */
    public function orderIds(PDO $pdo, string $prefix): array
    {
        $statement = $pdo->query(sprintf(
            "SELECT id FROM %s WHERE type = 'shop_order' ORDER BY id ASC",
            $this->quoteIdentifier($prefix . 'wc_orders'),
        ));

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function refundCount(PDO $pdo, string $prefix): int
    {
        $statement = $pdo->query(sprintf(
            "SELECT COUNT(*) FROM %s WHERE type = 'shop_order_refund'",
            $this->quoteIdentifier($prefix . 'wc_orders'),
        ));

        return (int) $statement->fetchColumn();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function readOrder(PDO $pdo, string $prefix, int $orderId): ?array
    {
        $header = $this->readHeader($pdo, $prefix, $orderId);
        if ($header === null) {
            return null;
        }

        $itemMeta = $this->readItemMeta($pdo, $prefix, $orderId);

        return [
            'header' => $header,
            'addresses' => $this->readAddresses($pdo, $prefix, $orderId),
            'items' => $this->readItems($pdo, $prefix, $orderId, $itemMeta),
            'item_meta' => $itemMeta,
            'order_meta' => $this->readOrderMeta($pdo, $prefix, $orderId),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readHeader(PDO $pdo, string $prefix, int $orderId): ?array
    {
        $statement = $pdo->prepare(sprintf(
            "SELECT
                o.id AS source_order_id,
                o.status AS order_status,
                o.currency,
                o.type AS order_type,
                o.tax_amount,
                o.total_amount,
                o.customer_id,
                o.billing_email,
                o.date_created_gmt AS created_at_gmt,
                o.date_updated_gmt AS updated_at_gmt,
                o.parent_order_id,
                o.payment_method,
                o.payment_method_title,
                o.transaction_id,
                o.ip_address AS customer_ip,
                o.user_agent AS customer_user_agent,
                o.customer_note,
                od.created_via,
                od.woocommerce_version AS source_woocommerce_version,
                od.prices_include_tax,
                od.coupon_usages_are_counted,
                od.download_permission_granted,
                od.cart_hash,
                od.new_order_email_sent,
                od.order_key,
                od.order_stock_reduced,
                od.date_paid_gmt AS paid_at_gmt,
                od.date_completed_gmt AS completed_at_gmt,
                od.shipping_tax_amount,
                od.shipping_total_amount,
                od.discount_tax_amount,
                od.discount_total_amount,
                od.recorded_sales
             FROM %s o
             LEFT JOIN %s od ON od.order_id = o.id
             WHERE o.type = 'shop_order'
               AND o.id = :order_id",
            $this->quoteIdentifier($prefix . 'wc_orders'),
            $this->quoteIdentifier($prefix . 'wc_order_operational_data'),
        ));
        $statement->execute(['order_id' => $orderId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readAddresses(PDO $pdo, string $prefix, int $orderId): array
    {
        $statement = $pdo->prepare(sprintf(
            'SELECT
                a.id AS source_address_id,
                a.order_id AS source_order_id,
                a.address_type,
                a.first_name,
                a.last_name,
                a.company,
                a.address_1,
                a.address_2,
                a.city,
                a.state,
                a.postcode,
                a.country,
                a.email,
                a.phone
             FROM %s a
             WHERE a.order_id = :order_id',
            $this->quoteIdentifier($prefix . 'wc_order_addresses'),
        ));
        $statement->execute(['order_id' => $orderId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param list<array<string, mixed>> $itemMeta
     * @return list<array<string, mixed>>
     */
    private function readItems(PDO $pdo, string $prefix, int $orderId, array $itemMeta): array
    {
        $statement = $pdo->prepare(sprintf(
            "SELECT
                oi.order_item_id AS source_order_item_id,
                oi.order_id AS source_order_id,
                oi.order_item_name AS item_name,
                oi.order_item_type AS item_type
             FROM %s oi
             WHERE oi.order_id = :order_id
               AND oi.order_item_type = 'line_item'",
            $this->quoteIdentifier($prefix . 'woocommerce_order_items'),
        ));
        $statement->execute(['order_id' => $orderId]);
        $items = $statement->fetchAll(PDO::FETCH_ASSOC);
        $metaByItem = $this->metaByItem($itemMeta);

        foreach ($items as &$item) {
            $pivot = $metaByItem[(string) $item['source_order_item_id']] ?? [];
            $item['product_id'] = $pivot['_product_id'] ?? null;
            $item['variation_id'] = $pivot['_variation_id'] ?? null;
            $item['quantity'] = $pivot['_qty'] ?? null;
            $item['line_subtotal'] = $pivot['_line_subtotal'] ?? null;
            $item['line_subtotal_tax'] = $pivot['_line_subtotal_tax'] ?? null;
            $item['line_total'] = $pivot['_line_total'] ?? null;
            $item['line_tax'] = $pivot['_line_tax'] ?? null;
            $item['tax_class'] = $pivot['_tax_class'] ?? null;
            $item['tax_data_raw'] = $pivot['_line_tax_data'] ?? null;
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readItemMeta(PDO $pdo, string $prefix, int $orderId): array
    {
        $statement = $pdo->prepare(sprintf(
            "SELECT
                oim.meta_id AS source_item_meta_id,
                oi.order_id AS source_order_id,
                oim.order_item_id AS source_order_item_id,
                oim.meta_key,
                oim.meta_value
             FROM %s oim
             JOIN %s oi ON oi.order_item_id = oim.order_item_id
             WHERE oi.order_id = :order_id
               AND oi.order_item_type = 'line_item'",
            $this->quoteIdentifier($prefix . 'woocommerce_order_itemmeta'),
            $this->quoteIdentifier($prefix . 'woocommerce_order_items'),
        ));
        $statement->execute(['order_id' => $orderId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readOrderMeta(PDO $pdo, string $prefix, int $orderId): array
    {
        $statement = $pdo->prepare(sprintf(
            'SELECT
                om.id AS source_order_meta_id,
                om.order_id AS source_order_id,
                om.meta_key,
                om.meta_value
             FROM %s om
             WHERE om.order_id = :order_id',
            $this->quoteIdentifier($prefix . 'wc_orders_meta'),
        ));
        $statement->execute(['order_id' => $orderId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param list<array<string, mixed>> $itemMeta
     * @return array<string, array<string, mixed>>
     */
    private function metaByItem(array $itemMeta): array
    {
        $metaByItem = [];
        foreach ($itemMeta as $meta) {
            $itemId = (string) ($meta['source_order_item_id'] ?? '');
            $key = (string) ($meta['meta_key'] ?? '');
            if ($itemId === '' || $key === '') {
                continue;
            }
            $metaByItem[$itemId][$key] = $meta['meta_value'] ?? null;
        }

        return $metaByItem;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
