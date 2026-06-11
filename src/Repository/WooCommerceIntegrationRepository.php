<?php

declare(strict_types=1);

namespace Luna\Repository;

use Luna\Database\SystemDatabase;
use Luna\Security\EncryptionService;
use PDO;

final class WooCommerceIntegrationRepository
{
    public function __construct(
        private readonly SystemDatabase $database,
        private readonly EncryptionService $encryption,
        private readonly ?PDO $pdo = null,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->allConnections();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allConnections(): array
    {
        $statement = $this->pdo()->query(
            'SELECT wc.*, w.name AS workspace_name, cp.name AS connection_name
             FROM luna_woocommerce_connections wc
             LEFT JOIN luna_workspaces w ON w.id = wc.workspace_id
             LEFT JOIN luna_connection_profiles cp ON cp.id = wc.connection_id
             ORDER BY wc.name',
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findConnection(int $id): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT wc.*, w.name AS workspace_name, cp.name AS connection_name
             FROM luna_woocommerce_connections wc
             LEFT JOIN luna_workspaces w ON w.id = wc.workspace_id
             LEFT JOIN luna_connection_profiles cp ON cp.id = wc.connection_id
             WHERE wc.id = :id',
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function findConnectionByToken(string $token): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT * FROM luna_woocommerce_connections WHERE connection_token = :token',
        );
        $statement->execute(['token' => $token]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function createConnection(array $values): int
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_woocommerce_connections
             (workspace_id, connection_id, name, connection_token, storage_mode, hpos_data_caching_allowed, hpos_data_caching_warning_acknowledged, created_at, updated_at)
             VALUES (:workspace_id, :connection_id, :name, :connection_token, :storage_mode, 0, 0, :created_at, :updated_at)',
        );
        $now = $this->now();
        $statement->execute([
            'workspace_id' => empty($values['workspace_id']) ? null : (int) $values['workspace_id'],
            'connection_id' => (int) ($values['connection_id'] ?? 0),
            'name' => (string) ($values['name'] ?? ''),
            'connection_token' => bin2hex(random_bytes(24)),
            'storage_mode' => 'hpos',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function updateConnectionValidation(int $id, array $validation): void
    {
        $statement = $this->pdo()->prepare(
            'UPDATE luna_woocommerce_connections
             SET detected_table_prefix = :detected_table_prefix,
                 detected_woocommerce_version = :detected_woocommerce_version,
                 storage_mode = :storage_mode,
                 hpos_enabled = :hpos_enabled,
                 hpos_authoritative = :hpos_authoritative,
                 hpos_data_caching_allowed = 0,
                 updated_at = :updated_at
             WHERE id = :id',
        );
        $statement->execute([
            'id' => $id,
            'detected_table_prefix' => $validation['table_prefix'] ?? null,
            'detected_woocommerce_version' => $validation['woocommerce_version'] ?? null,
            'storage_mode' => 'hpos',
            'hpos_enabled' => ! empty($validation['hpos_enabled']) ? 1 : 0,
            'hpos_authoritative' => ! empty($validation['hpos_authoritative']) ? 1 : 0,
            'updated_at' => $this->now(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function webhooksForConnection(int $woocommerceConnectionId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT * FROM luna_woocommerce_webhook_configs
             WHERE woocommerce_connection_id = :id
             ORDER BY is_required DESC, topic, webhook_name',
        );
        $statement->execute(['id' => $woocommerceConnectionId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['has_secret'] = ! empty($row['secret_encrypted']);
            unset($row['secret_encrypted']);
        }

        return $rows;
    }

    public function createWebhookConfig(array $values, string $secret = ''): int
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_woocommerce_webhook_configs
             (workspace_id, woocommerce_connection_id, webhook_name, topic, delivery_url, secret_encrypted, expected_status, api_version, is_required, validation_status, validation_message, created_at, updated_at)
             VALUES (:workspace_id, :woocommerce_connection_id, :webhook_name, :topic, :delivery_url, :secret_encrypted, :expected_status, :api_version, :is_required, :validation_status, :validation_message, :created_at, :updated_at)',
        );
        $now = $this->now();
        $statement->execute([
            'workspace_id' => empty($values['workspace_id']) ? null : (int) $values['workspace_id'],
            'woocommerce_connection_id' => (int) ($values['woocommerce_connection_id'] ?? 0),
            'webhook_name' => (string) ($values['webhook_name'] ?? ''),
            'topic' => (string) ($values['topic'] ?? ''),
            'delivery_url' => (string) ($values['delivery_url'] ?? ''),
            'secret_encrypted' => $secret === '' ? null : $this->encryption->encrypt($secret),
            'expected_status' => (string) ($values['expected_status'] ?? 'active'),
            'api_version' => (string) ($values['api_version'] ?? 'WP REST API Integration v3'),
            'is_required' => ! empty($values['is_required']) ? 1 : 0,
            'validation_status' => (string) ($values['validation_status'] ?? 'unknown'),
            'validation_message' => (string) ($values['validation_message'] ?? ''),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function updateWebhookConfig(int $id, array $values, string $secret = ''): void
    {
        $setSecretSql = $secret === '' ? '' : ', secret_encrypted = :secret_encrypted';
        $statement = $this->pdo()->prepare(
            'UPDATE luna_woocommerce_webhook_configs
             SET webhook_name = :webhook_name,
                 topic = :topic,
                 delivery_url = :delivery_url,
                 expected_status = :expected_status,
                 api_version = :api_version,
                 is_required = :is_required,
                 validation_status = :validation_status,
                 validation_message = :validation_message,
                 updated_at = :updated_at' . $setSecretSql . '
             WHERE id = :id',
        );
        $payload = [
            'id' => $id,
            'webhook_name' => (string) ($values['webhook_name'] ?? ''),
            'topic' => (string) ($values['topic'] ?? ''),
            'delivery_url' => (string) ($values['delivery_url'] ?? ''),
            'expected_status' => (string) ($values['expected_status'] ?? 'active'),
            'api_version' => (string) ($values['api_version'] ?? 'WP REST API Integration v3'),
            'is_required' => ! empty($values['is_required']) ? 1 : 0,
            'validation_status' => (string) ($values['validation_status'] ?? 'unknown'),
            'validation_message' => (string) ($values['validation_message'] ?? ''),
            'updated_at' => $this->now(),
        ];
        if ($secret !== '') {
            $payload['secret_encrypted'] = $this->encryption->encrypt($secret);
        }
        $statement->execute($payload);
    }

    public function findWebhookConfig(int $id): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT * FROM luna_woocommerce_webhook_configs WHERE id = :id',
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $row['has_secret'] = ! empty($row['secret_encrypted']);
        unset($row['secret_encrypted']);

        return $row;
    }

    public function deleteWebhookConfig(int $id): void
    {
        $statement = $this->pdo()->prepare('DELETE FROM luna_woocommerce_webhook_configs WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function deleteConnection(int $id): void
    {
        $statement = $this->pdo()->prepare('DELETE FROM luna_woocommerce_connections WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function secretForTopic(int $woocommerceConnectionId, string $topic): ?string
    {
        $statement = $this->pdo()->prepare(
            'SELECT secret_encrypted
             FROM luna_woocommerce_webhook_configs
             WHERE woocommerce_connection_id = :connection_id
               AND topic = :topic
             ORDER BY is_required DESC, id
             LIMIT 1',
        );
        $statement->execute(['connection_id' => $woocommerceConnectionId, 'topic' => $topic]);
        $encrypted = $statement->fetchColumn();

        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        return $this->encryption->decrypt($encrypted);
    }

    public function storeWebhookEvent(array $values): int
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_woocommerce_webhook_events
             (workspace_id, woocommerce_connection_id, topic, resource, event_action, source_order_id, delivery_id, signature_valid, raw_headers_json, raw_payload_json, received_at, processing_status, processing_message)
             VALUES (:workspace_id, :woocommerce_connection_id, :topic, :resource, :event_action, :source_order_id, :delivery_id, :signature_valid, :raw_headers_json, :raw_payload_json, :received_at, :processing_status, :processing_message)',
        );
        $statement->execute([
            'workspace_id' => empty($values['workspace_id']) ? null : (int) $values['workspace_id'],
            'woocommerce_connection_id' => (int) ($values['woocommerce_connection_id'] ?? 0),
            'topic' => (string) ($values['topic'] ?? ''),
            'resource' => (string) ($values['resource'] ?? ''),
            'event_action' => (string) ($values['event_action'] ?? ''),
            'source_order_id' => isset($values['source_order_id']) ? (string) $values['source_order_id'] : null,
            'delivery_id' => (string) ($values['delivery_id'] ?? ''),
            'signature_valid' => ! empty($values['signature_valid']) ? 1 : 0,
            'raw_headers_json' => $this->json($values['raw_headers'] ?? []),
            'raw_payload_json' => (string) ($values['raw_payload'] ?? ''),
            'received_at' => $this->now(),
            'processing_status' => (string) ($values['processing_status'] ?? 'received'),
            'processing_message' => (string) ($values['processing_message'] ?? ''),
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function queueTransfer(array $values): int
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_woocommerce_transfer_queue
             (workspace_id, woocommerce_connection_id, webhook_event_id, source_order_id, topic, reason, status, created_at, updated_at)
             VALUES (:workspace_id, :woocommerce_connection_id, :webhook_event_id, :source_order_id, :topic, :reason, :status, :created_at, :updated_at)',
        );
        $now = $this->now();
        $statement->execute([
            'workspace_id' => empty($values['workspace_id']) ? null : (int) $values['workspace_id'],
            'woocommerce_connection_id' => (int) ($values['woocommerce_connection_id'] ?? 0),
            'webhook_event_id' => empty($values['webhook_event_id']) ? null : (int) $values['webhook_event_id'],
            'source_order_id' => (string) ($values['source_order_id'] ?? ''),
            'topic' => (string) ($values['topic'] ?? ''),
            'reason' => (string) ($values['reason'] ?? ''),
            'status' => (string) ($values['status'] ?? 'pending'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function transferQueueForConnection(int $woocommerceConnectionId, int $limit = 50): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT *
             FROM luna_woocommerce_transfer_queue
             WHERE woocommerce_connection_id = :connection_id
             ORDER BY id DESC
             LIMIT ' . max(1, $limit),
        );
        $statement->execute(['connection_id' => $woocommerceConnectionId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentRunsForConnection(int $woocommerceConnectionId, int $limit = 20): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT *
             FROM luna_woocommerce_transfer_runs
             WHERE woocommerce_connection_id = :connection_id
             ORDER BY id DESC
             LIMIT ' . max(1, $limit),
        );
        $statement->execute(['connection_id' => $woocommerceConnectionId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentWebhookEventsForConnection(int $woocommerceConnectionId, int $limit = 20): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT *
             FROM luna_woocommerce_webhook_events
             WHERE woocommerce_connection_id = :connection_id
             ORDER BY id DESC
             LIMIT ' . max(1, $limit),
        );
        $statement->execute(['connection_id' => $woocommerceConnectionId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingQueue(?int $woocommerceConnectionId = null, ?int $queueId = null, int $limit = 10, bool $retryFailed = false): array
    {
        $conditions = ['status IN (' . ($retryFailed ? "'pending', 'failed'" : "'pending'") . ')'];
        $params = [];

        if ($woocommerceConnectionId !== null) {
            $conditions[] = 'woocommerce_connection_id = :connection_id';
            $params['connection_id'] = $woocommerceConnectionId;
        }

        if ($queueId !== null) {
            $conditions[] = 'id = :queue_id';
            $params['queue_id'] = $queueId;
        }

        $statement = $this->pdo()->prepare(sprintf(
            'SELECT *
             FROM luna_woocommerce_transfer_queue
             WHERE %s
             ORDER BY id ASC
             LIMIT %d',
            implode(' AND ', $conditions),
            max(1, $limit),
        ));
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function lockQueue(int $queueId, bool $retryFailed = false): bool
    {
        $condition = $retryFailed ? "status IN ('pending', 'failed')" : "status = 'pending'";
        $statement = $this->pdo()->prepare(
            "UPDATE luna_woocommerce_transfer_queue
             SET status = 'processing',
                 locked_at = :locked_at,
                 started_at = :started_at,
                 finished_at = NULL,
                 attempts = attempts + 1,
                 last_error = NULL,
                 updated_at = :updated_at
             WHERE id = :id
               AND {$condition}",
        );
        $now = $this->now();
        $statement->execute([
            'id' => $queueId,
            'locked_at' => $now,
            'started_at' => $now,
            'updated_at' => $now,
        ]);

        return $statement->rowCount() === 1;
    }

    public function createTransferRun(array $queue): int
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_woocommerce_transfer_runs
             (workspace_id, woocommerce_connection_id, queue_id, run_type, status, started_at, source_order_id, created_at, updated_at)
             VALUES (:workspace_id, :woocommerce_connection_id, :queue_id, :run_type, :status, :started_at, :source_order_id, :created_at, :updated_at)',
        );
        $now = $this->now();
        $statement->execute([
            'workspace_id' => empty($queue['workspace_id']) ? null : (int) $queue['workspace_id'],
            'woocommerce_connection_id' => (int) $queue['woocommerce_connection_id'],
            'queue_id' => (int) $queue['id'],
            'run_type' => (string) ($queue['topic'] ?? ''),
            'status' => 'processing',
            'started_at' => $now,
            'source_order_id' => (string) ($queue['source_order_id'] ?? ''),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function finishTransferRun(int $runId, string $status, array $counts, array $summary = [], string $errorMessage = ''): void
    {
        $statement = $this->pdo()->prepare(
            'UPDATE luna_woocommerce_transfer_runs
             SET status = :status,
                 finished_at = :finished_at,
                 orders_found = :orders_found,
                 orders_written = :orders_written,
                 addresses_written = :addresses_written,
                 items_written = :items_written,
                 item_meta_written = :item_meta_written,
                 order_meta_written = :order_meta_written,
                 refunds_seen = :refunds_seen,
                 skipped_count = :skipped_count,
                 error_count = :error_count,
                 summary_json = :summary_json,
                 error_message = :error_message,
                 updated_at = :updated_at
             WHERE id = :id',
        );
        $now = $this->now();
        $statement->execute([
            'id' => $runId,
            'status' => $status,
            'finished_at' => $now,
            'orders_found' => (int) ($counts['orders_found'] ?? 0),
            'orders_written' => (int) ($counts['orders_written'] ?? 0),
            'addresses_written' => (int) ($counts['addresses_written'] ?? 0),
            'items_written' => (int) ($counts['items_written'] ?? 0),
            'item_meta_written' => (int) ($counts['item_meta_written'] ?? 0),
            'order_meta_written' => (int) ($counts['order_meta_written'] ?? 0),
            'refunds_seen' => (int) ($counts['refunds_seen'] ?? 0),
            'skipped_count' => (int) ($counts['skipped_count'] ?? 0),
            'error_count' => (int) ($counts['error_count'] ?? ($status === 'failed' ? 1 : 0)),
            'summary_json' => $this->json($summary),
            'error_message' => $errorMessage === '' ? null : $errorMessage,
            'updated_at' => $now,
        ]);
    }

    public function markQueueSuccess(int $queueId, int $runId): void
    {
        $this->finishQueue($queueId, $runId, 'success', null);
    }

    public function markQueueFailed(int $queueId, int $runId, string $errorMessage): void
    {
        $this->finishQueue($queueId, $runId, 'failed', $errorMessage);
    }

    private function finishQueue(int $queueId, int $runId, string $status, ?string $errorMessage): void
    {
        $statement = $this->pdo()->prepare(
            'UPDATE luna_woocommerce_transfer_queue
             SET status = :status,
                 finished_at = :finished_at,
                 last_error = :last_error,
                 last_run_id = :last_run_id,
                 updated_at = :updated_at
             WHERE id = :id',
        );
        $now = $this->now();
        $statement->execute([
            'id' => $queueId,
            'status' => $status,
            'finished_at' => $now,
            'last_error' => $errorMessage,
            'last_run_id' => $runId,
            'updated_at' => $now,
        ]);
    }

    private function json(mixed $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $json === false ? '{}' : $json;
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
