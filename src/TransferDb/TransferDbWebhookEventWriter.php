<?php

declare(strict_types=1);

namespace Luna\TransferDb;

use Throwable;
use PDO;

final class TransferDbWebhookEventWriter
{
    public function __construct(
        private readonly TransferDbConnectionResolver $resolver,
        private readonly TransferDbSchemaManager $schemaManager,
        private readonly TransferDbWriter $writer,
    ) {
    }

    /**
     * @param array<string, mixed> $event
     * @param array<string, mixed> $headers
     * @return array<string, mixed>
     */
    public function write(string|int $workspaceIdentifier, array $event, array $headers = []): array
    {
        $resolved = $this->resolver->resolve($workspaceIdentifier);
        return $this->writeResolved($resolved['pdo'], $resolved['workspace'], $event, $headers);
    }

    /**
     * @param array<string, mixed> $workspace
     * @param array<string, mixed> $event
     * @param array<string, mixed> $headers
     * @return array<string, mixed>
     */
    public function writeResolved(PDO $pdo, array $workspace, array $event, array $headers = []): array
    {
        $this->schemaManager->migrate($pdo);
        $workspaceKey = (string) ($workspace['slug'] ?? $workspace['id'] ?? 'workspace');
        $provider = (string) ($event['provider'] ?? 'generic');
        $topic = (string) ($event['topic'] ?? 'generic');
        $triggerKey = (string) ($event['trigger_key'] ?? '');
        $payload = $event['transfer_payload'] ?? $event['payload'] ?? $event['payload_summary'] ?? [];
        if (! is_array($payload)) {
            $payload = ['value' => $payload];
        }
        $payloadJson = $this->writer->json($payload);
        $payloadHash = (string) ($event['payload_hash'] ?? hash('sha256', $payloadJson));

        $pdo->beginTransaction();
        try {
            $sourceId = $this->writer->source($pdo, [
                'workspace_key' => $workspaceKey,
                'source_type' => 'webhook',
                'source_key' => $provider . ':' . $topic . ':' . ($triggerKey !== '' ? $triggerKey : 'generic'),
                'provider' => $provider,
                'schema_key' => $event['schema_key'] ?? null,
                'schema_version' => $event['schema_version'] ?? null,
            ]);
            $batchId = $this->writer->batch($pdo, $sourceId, [
                'external_id' => $event['delivery_id'] ?? null,
                'batch_type' => 'webhook_delivery',
                'status' => ! empty($event['signature_valid']) ? 'received' : 'failed',
                'record_count' => 1,
                'payload_hash' => $payloadHash,
                'metadata' => [
                    'provider' => $provider,
                    'topic' => $topic,
                    'resource' => $event['resource'] ?? null,
                    'event' => $event['event'] ?? null,
                    'signature_valid' => ! empty($event['signature_valid']),
                ],
                'received_at' => $this->dateTime((string) ($event['received_at'] ?? '')),
                'error_message' => $event['rejection_reason'] ?? null,
            ]);
            $webhookId = $this->webhookEvent($pdo, $workspaceKey, $batchId, $event, $headers, $payloadJson, $payloadHash);
            $recordId = $this->writer->record($pdo, $batchId, $sourceId, $payload, [
                'record_key' => $event['source_order_id'] ?? null,
                'record_index' => 0,
                'operation' => 'event',
                'status' => 'staged',
                'schema_key' => $event['schema_key'] ?? null,
                'schema_version' => $event['schema_version'] ?? null,
            ]);
            $this->writer->log($pdo, $workspaceKey, 'info', 'Webhook Event in TransferDB gespeichert.', [
                'provider' => $provider,
                'topic' => $topic,
                'batch_id' => $batchId,
                'webhook_event_id' => $webhookId,
            ], 'webhook', (string) $webhookId);
            $pdo->commit();

            return [
                'success' => true,
                'source_id' => $sourceId,
                'batch_id' => $batchId,
                'webhook_event_id' => $webhookId,
                'record_id' => $recordId,
            ];
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $event
     * @param array<string, mixed> $headers
     */
    private function webhookEvent(\PDO $pdo, string $workspaceKey, int $batchId, array $event, array $headers, string $payloadJson, string $payloadHash): int
    {
        $statement = $pdo->prepare(
            'INSERT INTO luna_transfer_webhook_events
             (batch_id, workspace_key, provider, trigger_key, configured_topic, received_topic, event_name, resource, action,
              external_event_id, external_delivery_id, source_url, signature_valid, signature_algorithm, payload_hash,
              payload_json, headers_json, status, rejection_reason, received_at, processed_at, created_at, updated_at)
             VALUES
             (:batch_id, :workspace_key, :provider, :trigger_key, :configured_topic, :received_topic, :event_name, :resource, :action,
              :external_event_id, :external_delivery_id, :source_url, :signature_valid, :signature_algorithm, :payload_hash,
              :payload_json, :headers_json, :status, :rejection_reason, :received_at, :processed_at, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
        );
        $statement->execute([
            'batch_id' => $batchId,
            'workspace_key' => $workspaceKey,
            'provider' => (string) ($event['provider'] ?? 'generic'),
            'trigger_key' => (string) ($event['trigger_key'] ?? ''),
            'configured_topic' => $event['configured_topic'] ?? null,
            'received_topic' => (string) ($event['topic'] ?? ''),
            'event_name' => (string) ($event['event'] ?? ''),
            'resource' => (string) ($event['resource'] ?? ''),
            'action' => (string) ($event['event'] ?? ''),
            'external_event_id' => $event['webhook_id'] ?? null,
            'external_delivery_id' => $event['delivery_id'] ?? null,
            'source_url' => $event['source_domain'] ?? null,
            'signature_valid' => ! empty($event['signature_valid']) ? 1 : 0,
            'signature_algorithm' => 'base64_hmac_sha256',
            'payload_hash' => $payloadHash,
            'payload_json' => $payloadJson,
            'headers_json' => $this->writer->json($this->maskHeaders($headers)),
            'status' => ! empty($event['signature_valid']) ? 'accepted' : 'rejected',
            'rejection_reason' => $event['rejection_reason'] ?? null,
            'received_at' => $this->dateTime((string) ($event['received_at'] ?? '')) ?? date('Y-m-d H:i:s'),
            'processed_at' => null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $headers
     * @return array<string, mixed>
     */
    private function maskHeaders(array $headers): array
    {
        foreach ($headers as $key => $value) {
            if (preg_match('/authorization|cookie|set-cookie|x-api-key|x-auth-token|token|secret/i', (string) $key) === 1) {
                $headers[$key] = '***';
            }
        }

        return $headers;
    }

    private function dateTime(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? $value : date('Y-m-d H:i:s', $timestamp);
    }
}
