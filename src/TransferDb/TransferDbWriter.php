<?php

declare(strict_types=1);

namespace Luna\TransferDb;

use PDO;
use RuntimeException;

final class TransferDbWriter
{
    /**
     * @param array<string, mixed> $data
     */
    public function source(PDO $pdo, array $data): int
    {
        $workspaceKey = (string) ($data['workspace_key'] ?? '');
        $sourceType = (string) ($data['source_type'] ?? '');
        $sourceKey = (string) ($data['source_key'] ?? '');
        if ($workspaceKey === '' || $sourceType === '' || $sourceKey === '') {
            throw new RuntimeException('TransferDB Source ist unvollständig.');
        }

        $statement = $pdo->prepare(
            'SELECT id FROM luna_transfer_sources WHERE workspace_key = :workspace_key AND source_type = :source_type AND source_key = :source_key',
        );
        $statement->execute([
            'workspace_key' => $workspaceKey,
            'source_type' => $sourceType,
            'source_key' => $sourceKey,
        ]);
        $existing = $statement->fetchColumn();
        if ($existing !== false) {
            $update = $pdo->prepare(
                'UPDATE luna_transfer_sources
                 SET provider = :provider,
                     schema_key = :schema_key,
                     schema_version = :schema_version,
                     is_active = :is_active,
                     updated_at = ' . $this->now($pdo) . '
                 WHERE id = :id',
            );
            $update->execute([
                'id' => (int) $existing,
                'provider' => $data['provider'] ?? null,
                'schema_key' => $data['schema_key'] ?? null,
                'schema_version' => $data['schema_version'] ?? null,
                'is_active' => ! array_key_exists('is_active', $data) || ! empty($data['is_active']) ? 1 : 0,
            ]);

            return (int) $existing;
        }

        $insert = $pdo->prepare(
            'INSERT INTO luna_transfer_sources
             (workspace_key, source_type, source_key, provider, schema_key, schema_version, is_active, created_at, updated_at)
             VALUES (:workspace_key, :source_type, :source_key, :provider, :schema_key, :schema_version, :is_active, ' . $this->now($pdo) . ', ' . $this->now($pdo) . ')',
        );
        $insert->execute([
            'workspace_key' => $workspaceKey,
            'source_type' => $sourceType,
            'source_key' => $sourceKey,
            'provider' => $data['provider'] ?? null,
            'schema_key' => $data['schema_key'] ?? null,
            'schema_version' => $data['schema_version'] ?? null,
            'is_active' => ! array_key_exists('is_active', $data) || ! empty($data['is_active']) ? 1 : 0,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function batch(PDO $pdo, int $sourceId, array $data): int
    {
        $statement = $pdo->prepare(
            'INSERT INTO luna_transfer_runs
             (source_id, external_id, run_type, status, record_count, payload_hash, metadata_json,
              received_at, generated_at, processed_at, error_message, created_at, updated_at)
             VALUES
             (:source_id, :external_id, :run_type, :status, :record_count, :payload_hash, :metadata_json,
              :received_at, :generated_at, :processed_at, :error_message, ' . $this->now($pdo) . ', ' . $this->now($pdo) . ')',
        );
        $statement->execute([
            'source_id' => $sourceId,
            'external_id' => $data['external_id'] ?? null,
            'run_type' => (string) ($data['batch_type'] ?? $data['run_type'] ?? 'manual_import'),
            'status' => (string) ($data['status'] ?? 'received'),
            'record_count' => (int) ($data['record_count'] ?? 0),
            'payload_hash' => $data['payload_hash'] ?? null,
            'metadata_json' => $this->json($data['metadata'] ?? []),
            'received_at' => $data['received_at'] ?? null,
            'generated_at' => $data['generated_at'] ?? null,
            'processed_at' => $data['processed_at'] ?? null,
            'error_message' => $this->safeMessage((string) ($data['error_message'] ?? '')) ?: null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function updateBatchRecordCount(PDO $pdo, int $batchId, int $recordCount): void
    {
        $statement = $pdo->prepare(
            'UPDATE luna_transfer_runs SET record_count = :record_count, updated_at = ' . $this->now($pdo) . ' WHERE id = :id',
        );
        $statement->execute(['id' => $batchId, 'record_count' => $recordCount]);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $data
     */
    public function record(PDO $pdo, int $batchId, int $sourceId, array $payload, array $data = []): int
    {
        $payloadJson = $this->json($payload);
        $statement = $pdo->prepare(
            'INSERT INTO luna_transfer_records
             (batch_id, source_id, record_key, record_index, operation, status, payload_json, payload_hash,
              schema_key, schema_version, validation_status, validation_errors_json, created_at, updated_at)
             VALUES
             (:batch_id, :source_id, :record_key, :record_index, :operation, :status, :payload_json, :payload_hash,
              :schema_key, :schema_version, :validation_status, :validation_errors_json, ' . $this->now($pdo) . ', ' . $this->now($pdo) . ')',
        );
        $statement->execute([
            'batch_id' => $batchId,
            'source_id' => $sourceId,
            'record_key' => $data['record_key'] ?? $this->recordKey($payload, (int) ($data['record_index'] ?? 0)),
            'record_index' => $data['record_index'] ?? null,
            'operation' => $data['operation'] ?? null,
            'status' => (string) ($data['status'] ?? 'staged'),
            'payload_json' => $payloadJson,
            'payload_hash' => hash('sha256', $payloadJson),
            'schema_key' => $data['schema_key'] ?? null,
            'schema_version' => $data['schema_version'] ?? null,
            'validation_status' => $data['validation_status'] ?? 'not_validated',
            'validation_errors_json' => isset($data['validation_errors']) ? $this->json($data['validation_errors']) : null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $data
     */
    public function endpointSnapshotRecord(PDO $pdo, int $snapshotId, int $batchId, int $sourceId, array $payload, array $data = []): int
    {
        $payloadJson = $this->json($payload);
        $statement = $pdo->prepare(
            'INSERT INTO luna_endpoint_snapshot_records
             (snapshot_id, batch_id, source_id, record_key, record_index, operation, status, payload_json, payload_hash,
              schema_key, schema_version, validation_status, validation_errors_json, created_at, updated_at)
             VALUES
             (:snapshot_id, :batch_id, :source_id, :record_key, :record_index, :operation, :status, :payload_json, :payload_hash,
              :schema_key, :schema_version, :validation_status, :validation_errors_json, ' . $this->now($pdo) . ', ' . $this->now($pdo) . ')',
        );
        $statement->execute([
            'snapshot_id' => $snapshotId,
            'batch_id' => $batchId,
            'source_id' => $sourceId,
            'record_key' => $data['record_key'] ?? $this->recordKey($payload, (int) ($data['record_index'] ?? 0)),
            'record_index' => $data['record_index'] ?? null,
            'operation' => $data['operation'] ?? null,
            'status' => (string) ($data['status'] ?? 'staged'),
            'payload_json' => $payloadJson,
            'payload_hash' => hash('sha256', $payloadJson),
            'schema_key' => $data['schema_key'] ?? null,
            'schema_version' => $data['schema_version'] ?? null,
            'validation_status' => $data['validation_status'] ?? 'not_validated',
            'validation_errors_json' => isset($data['validation_errors']) ? $this->json($data['validation_errors']) : null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function log(PDO $pdo, string $workspaceKey, string $level, string $message, array $metadata = [], ?string $contextType = null, ?string $contextId = null): int
    {
        $level = in_array($level, ['info', 'warning', 'error'], true) ? $level : 'info';
        $statement = $pdo->prepare(
            'INSERT INTO luna_transfer_run_logs
             (workspace_key, level, context_type, context_id, message, metadata_json, created_at)
             VALUES (:workspace_key, :level, :context_type, :context_id, :message, :metadata_json, ' . $this->now($pdo) . ')',
        );
        $statement->execute([
            'workspace_key' => $workspaceKey,
            'level' => $level,
            'context_type' => $contextType,
            'context_id' => $contextId,
            'message' => $this->safeMessage($message),
            'metadata_json' => $this->json($metadata),
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function json(mixed $value): string
    {
        $json = json_encode($this->maskSecrets($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new RuntimeException('TransferDB JSON konnte nicht serialisiert werden.');
        }

        return $json;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function recordKey(array $payload, int $fallbackIndex = 0): string
    {
        foreach (['model', 'sku', 'id', 'order_id'] as $key) {
            if (isset($payload[$key]) && is_scalar($payload[$key]) && (string) $payload[$key] !== '') {
                return (string) $payload[$key];
            }
        }

        return 'hash_' . substr(hash('sha256', $this->json($payload) . '#' . $fallbackIndex), 0, 24);
    }

    public function now(PDO $pdo): string
    {
        return (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'CURRENT_TIMESTAMP' : 'NOW()';
    }

    private function safeMessage(string $message): string
    {
        return preg_replace('/(password|secret|token|api_key|authorization|bearer|cookie)=([^\s]+)/i', '$1=***', $message) ?? $message;
    }

    private function maskSecrets(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            if (is_string($key) && preg_match('/password|passwd|secret|token|api_key|authorization|bearer|cookie|set-cookie|x-api-key|x-auth-token/i', $key) === 1) {
                $value[$key] = '***';
                continue;
            }

            if (is_array($item)) {
                $value[$key] = $this->maskSecrets($item);
            }
        }

        return $value;
    }
}