<?php

declare(strict_types=1);

namespace Luna\Export;

use Luna\Database\SystemDatabase;
use Luna\Repository\ExportProfileRepository;
use PDO;
use RuntimeException;
use Throwable;

final class WooCommerceExportService
{
    /**
     * @var array<string, string>
     */
    private const PROFILE_TABLES = [
        'orders' => 'luna_woocommerce_order_headers',
        'order_addresses' => 'luna_woocommerce_order_addresses',
        'order_items' => 'luna_woocommerce_order_items',
        'order_itemmeta_raw' => 'luna_woocommerce_order_itemmeta_raw',
        'order_meta_raw' => 'luna_woocommerce_order_meta_raw',
    ];

    public function __construct(
        private readonly ExportProfileRepository $profiles,
        private readonly SystemDatabase $database,
        private readonly ?PDO $pdo = null,
    ) {
    }

    /**
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function export(array $profile, array $params = [], string $triggeredBy = 'api'): array
    {
        $runId = $this->profiles->createExportRun($profile, $triggeredBy, $params);

        try {
            $profileKey = (string) ($profile['profile_key'] ?? '');
            $result = $profileKey === 'orders_full'
                ? $this->exportOrdersFull($profile, $params)
                : $this->exportFlatProfile($profile, $params);

            $this->profiles->finishExportRun(
                $runId,
                $profile,
                'success',
                (int) $result['count'],
                (int) $result['count'],
                (string) ($result['watermark']['next_since'] ?? ''),
                ['filters' => $this->publicFilters($params)],
            );

            return $result;
        } catch (Throwable $exception) {
            $message = $this->safeError($exception->getMessage());
            $this->profiles->finishExportRun(
                $runId,
                $profile,
                'failed',
                0,
                0,
                null,
                ['filters' => $this->publicFilters($params)],
                $message,
            );

            return [
                'success' => false,
                'error' => [
                    'code' => 'export_failed',
                    'message' => $message,
                ],
            ];
        }
    }

    /**
     * @param array<string, mixed> $profile
     */
    public function authenticate(array $profile, string $method, string $path, string $rawQuery, string $rawBody, string $authorizationHeader, array $headers): bool
    {
        $authorizationHeader = trim($authorizationHeader);
        if (str_starts_with(strtolower($authorizationHeader), 'bearer ')) {
            return $this->profiles->tokenMatches($profile, trim(substr($authorizationHeader, 7)));
        }

        $token = trim((string) ($headers['x-luna-export-token'] ?? ''));
        $timestamp = trim((string) ($headers['x-luna-timestamp'] ?? ''));
        $signature = trim((string) ($headers['x-luna-signature'] ?? ''));
        if ($token === '' || $timestamp === '' || $signature === '') {
            return false;
        }

        if (! $this->profiles->tokenMatches($profile, $token)) {
            return false;
        }

        $timestampValue = (int) $timestamp;
        if ($timestampValue <= 0 || abs(time() - $timestampValue) > 300) {
            return false;
        }

        $secret = $this->profiles->secretForProfile($profile);
        if ($secret === null || $secret === '') {
            return false;
        }

        $base = strtoupper($method) . "\n" . $path . "\n" . $rawQuery . "\n" . $rawBody . "\n" . $timestamp;
        $expected = hash_hmac('sha256', $base, $secret);

        return hash_equals($expected, strtolower($signature));
    }

    /**
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function exportOrdersFull(array $profile, array $params): array
    {
        $headers = $this->orderHeaders($profile, $params);
        $orderIds = array_map(static fn (array $row): int => (int) $row['source_order_id'], $headers);
        $includeRawMeta = $this->boolParam($params, 'include_raw_meta', ! empty($profile['include_raw_meta']));
        $includeItemRawMeta = $this->boolParam($params, 'include_item_raw_meta', ! empty($profile['include_item_raw_meta']));

        $addresses = $this->groupByOrderId($this->rowsByOrderIds('luna_woocommerce_order_addresses', (int) $profile['connection_id'], $orderIds));
        $items = $this->groupByOrderId($this->rowsByOrderIds('luna_woocommerce_order_items', (int) $profile['connection_id'], $orderIds));
        $orderMeta = $includeRawMeta
            ? $this->groupByOrderId($this->rowsByOrderIds('luna_woocommerce_order_meta_raw', (int) $profile['connection_id'], $orderIds))
            : [];
        $itemMeta = $includeItemRawMeta
            ? $this->groupByItemId($this->rowsByOrderIds('luna_woocommerce_order_itemmeta_raw', (int) $profile['connection_id'], $orderIds))
            : [];

        $data = [];
        $watermark = null;
        foreach ($headers as $header) {
            $orderId = (int) $header['source_order_id'];
            $orderItems = [];
            foreach ($items[$orderId] ?? [] as $item) {
                if ($includeItemRawMeta) {
                    $item['meta'] = $itemMeta[(int) $item['source_order_item_id']] ?? [];
                }
                $orderItems[] = $this->withoutInternalColumns($item);
            }

            $row = $this->withoutInternalColumns($header);
            $row['addresses'] = array_map($this->withoutInternalColumns(...), $addresses[$orderId] ?? []);
            $row['items'] = $orderItems;
            if ($includeRawMeta) {
                $row['meta'] = array_map($this->withoutInternalColumns(...), $orderMeta[$orderId] ?? []);
            }
            $data[] = $row;

            $candidate = $this->rowWatermark($header);
            if ($candidate !== '' && ($watermark === null || $candidate > $watermark)) {
                $watermark = $candidate;
            }
        }

        return [
            'success' => true,
            'profile' => 'orders_full',
            'generated_at' => date(DATE_ATOM),
            'count' => count($data),
            'watermark' => [
                'field' => 'updated_at_gmt',
                'next_since' => $watermark,
            ],
            'data' => $data,
        ];
    }

    /**
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function exportFlatProfile(array $profile, array $params): array
    {
        $profileKey = (string) ($profile['profile_key'] ?? '');
        $table = self::PROFILE_TABLES[$profileKey] ?? null;
        if ($table === null) {
            throw new RuntimeException('Exportprofil wird nicht unterstützt.');
        }

        $rows = $profileKey === 'orders'
            ? $this->orderHeaders($profile, $params)
            : $this->flatRows($table, (int) $profile['connection_id'], $params, (int) ($profile['batch_size'] ?? 100));
        $watermark = null;
        foreach ($rows as $row) {
            $candidate = $this->rowWatermark($row);
            if ($candidate !== '' && ($watermark === null || $candidate > $watermark)) {
                $watermark = $candidate;
            }
        }

        return [
            'success' => true,
            'profile' => $profileKey,
            'generated_at' => date(DATE_ATOM),
            'count' => count($rows),
            'watermark' => [
                'field' => $profileKey === 'orders' ? 'updated_at_gmt' : 'last_imported_at',
                'next_since' => $watermark,
            ],
            'data' => array_map($this->withoutInternalColumns(...), $rows),
        ];
    }

    /**
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function orderHeaders(array $profile, array $params): array
    {
        $where = ['woocommerce_connection_id = :connection_id'];
        $payload = ['connection_id' => (int) $profile['connection_id']];
        $since = $this->dateParam($params['since'] ?? null);
        if ($since === null && trim((string) ($profile['last_successful_watermark'] ?? '')) !== '') {
            $since = (string) $profile['last_successful_watermark'];
        }
        $until = $this->dateParam($params['until'] ?? null);

        if ($since !== null) {
            $where[] = "COALESCE(NULLIF(updated_at_gmt, ''), last_imported_at) >= :since";
            $payload['since'] = $since;
        }
        if ($until !== null) {
            $where[] = "COALESCE(NULLIF(updated_at_gmt, ''), last_imported_at) <= :until";
            $payload['until'] = $until;
        }
        if (isset($params['order_id']) && trim((string) $params['order_id']) !== '') {
            $where[] = 'source_order_id = :order_id';
            $payload['order_id'] = (int) $params['order_id'];
        }
        if (isset($params['status']) && trim((string) $params['status']) !== '') {
            $where[] = 'order_status = :status';
            $payload['status'] = (string) $params['status'];
        }

        $limit = $this->limit($params, (int) ($profile['batch_size'] ?? 100));
        $offset = max(0, (int) ($params['offset'] ?? 0));
        $statement = $this->pdo()->prepare(sprintf(
            'SELECT *
             FROM luna_woocommerce_order_headers
             WHERE %s
             ORDER BY COALESCE(NULLIF(updated_at_gmt, \'\'), last_imported_at), source_order_id
             LIMIT %d OFFSET %d',
            implode(' AND ', $where),
            $limit,
            $offset,
        ));
        $statement->execute($payload);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function flatRows(string $table, int $connectionId, array $params, int $defaultLimit): array
    {
        $where = ['woocommerce_connection_id = :connection_id'];
        $payload = ['connection_id' => $connectionId];
        if (isset($params['order_id']) && trim((string) $params['order_id']) !== '') {
            $where[] = 'source_order_id = :order_id';
            $payload['order_id'] = (int) $params['order_id'];
        }

        $limit = $this->limit($params, $defaultLimit);
        $offset = max(0, (int) ($params['offset'] ?? 0));
        $statement = $this->pdo()->prepare(sprintf(
            'SELECT *
             FROM %s
             WHERE %s
             ORDER BY source_order_id, id
             LIMIT %d OFFSET %d',
            $this->quoteIdentifier($table),
            implode(' AND ', $where),
            $limit,
            $offset,
        ));
        $statement->execute($payload);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param list<int> $orderIds
     * @return list<array<string, mixed>>
     */
    private function rowsByOrderIds(string $table, int $connectionId, array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        $placeholders = [];
        $payload = ['connection_id' => $connectionId];
        foreach ($orderIds as $index => $orderId) {
            $key = 'order_id_' . $index;
            $placeholders[] = ':' . $key;
            $payload[$key] = $orderId;
        }

        $statement = $this->pdo()->prepare(sprintf(
            'SELECT *
             FROM %s
             WHERE woocommerce_connection_id = :connection_id
               AND source_order_id IN (%s)
             ORDER BY source_order_id, id',
            $this->quoteIdentifier($table),
            implode(', ', $placeholders),
        ));
        $statement->execute($payload);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<int, list<array<string, mixed>>>
     */
    private function groupByOrderId(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['source_order_id']][] = $row;
        }

        return $grouped;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<int, list<array<string, mixed>>>
     */
    private function groupByItemId(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['source_order_item_id']][] = $this->withoutInternalColumns($row);
        }

        return $grouped;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function withoutInternalColumns(array $row): array
    {
        unset($row['id'], $row['workspace_id'], $row['woocommerce_connection_id'], $row['raw_order_json']);

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowWatermark(array $row): string
    {
        $updated = trim((string) ($row['updated_at_gmt'] ?? ''));
        if ($updated !== '') {
            return $updated;
        }

        return trim((string) ($row['last_imported_at'] ?? ''));
    }

    /**
     * @param array<string, mixed> $params
     */
    private function limit(array $params, int $default): int
    {
        return max(1, min(1000, (int) ($params['limit'] ?? $default)));
    }

    private function dateParam(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return str_replace('T', ' ', rtrim($value, 'Z'));
    }

    /**
     * @param array<string, mixed> $params
     */
    private function boolParam(array $params, string $key, bool $default): bool
    {
        if (! array_key_exists($key, $params)) {
            return $default;
        }

        return in_array(strtolower((string) $params[$key]), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function publicFilters(array $params): array
    {
        return array_intersect_key($params, array_flip([
            'since',
            'until',
            'limit',
            'offset',
            'include_raw_meta',
            'include_item_raw_meta',
            'order_id',
            'status',
        ]));
    }

    private function safeError(string $message): string
    {
        $message = preg_replace('/([A-Za-z]:\\\\Users\\\\)[^\\s]+/i', '$1[redacted]', $message) ?? $message;

        return substr($message, 0, 1000);
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function pdo(): PDO
    {
        return $this->pdo ?? $this->database->pdo();
    }
}
