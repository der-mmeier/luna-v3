<?php

declare(strict_types=1);

namespace Luna\Repository;

use Luna\Database\SystemDatabase;
use PDO;

final class WooCommerceRuntimeEventRepository
{
    public function __construct(
        private readonly SystemDatabase $database,
        private readonly ?PDO $pdo = null,
    ) {
    }

    /**
     * @param array<string, mixed> $event
     */
    public function create(array $event, string $status = 'received', string $message = ''): int
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_woocommerce_runtime_events
             (workspace_id, process_trigger_id, process_run_id, provider, topic, resource, event_action, delivery_id, webhook_id,
              source_domain, source_order_id, signature_valid, payload_size, payload_hash, payload_summary_json, payload_meta_json,
              processing_status, processing_message, received_at, created_at, updated_at)
             VALUES
             (:workspace_id, :process_trigger_id, :process_run_id, :provider, :topic, :resource, :event_action, :delivery_id, :webhook_id,
              :source_domain, :source_order_id, :signature_valid, :payload_size, :payload_hash, :payload_summary_json, :payload_meta_json,
              :processing_status, :processing_message, :received_at, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
        );
        $statement->execute([
            'workspace_id' => empty($event['workspace_id']) ? null : (int) $event['workspace_id'],
            'process_trigger_id' => empty($event['process_trigger_id']) ? null : (int) $event['process_trigger_id'],
            'process_run_id' => empty($event['process_run_id']) ? null : (int) $event['process_run_id'],
            'provider' => 'woocommerce',
            'topic' => (string) ($event['topic'] ?? ''),
            'resource' => (string) ($event['resource'] ?? ''),
            'event_action' => (string) ($event['event'] ?? ''),
            'delivery_id' => (string) ($event['delivery_id'] ?? ''),
            'webhook_id' => (string) ($event['webhook_id'] ?? ''),
            'source_domain' => (string) ($event['source_domain'] ?? ''),
            'source_order_id' => isset($event['source_order_id']) ? (string) $event['source_order_id'] : null,
            'signature_valid' => ! empty($event['signature_valid']) ? 1 : 0,
            'payload_size' => (int) ($event['payload_size'] ?? 0),
            'payload_hash' => (string) ($event['payload_hash'] ?? ''),
            'payload_summary_json' => $this->json($event['payload_summary'] ?? []),
            'payload_meta_json' => $this->json($event['payload_meta'] ?? []),
            'processing_status' => $status,
            'processing_message' => $message === '' ? null : $message,
            'received_at' => (string) ($event['received_at'] ?? date('Y-m-d H:i:s')),
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function attachRun(int $eventId, ?int $processRunId, string $status, string $message = ''): void
    {
        $statement = $this->pdo()->prepare(
            'UPDATE luna_woocommerce_runtime_events
             SET process_run_id = :process_run_id,
                 processing_status = :processing_status,
                 processing_message = :processing_message,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
        );
        $statement->execute([
            'id' => $eventId,
            'process_run_id' => $processRunId,
            'processing_status' => $status,
            'processing_message' => $message === '' ? null : $message,
        ]);
    }

    private function json(mixed $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $json === false ? '{}' : $json;
    }

    private function pdo(): PDO
    {
        return $this->pdo ?? $this->database->pdo();
    }
}
