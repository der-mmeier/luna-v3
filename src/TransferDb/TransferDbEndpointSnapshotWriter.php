<?php

declare(strict_types=1);

namespace Luna\TransferDb;

use PDO;
use Throwable;

final class TransferDbEndpointSnapshotWriter
{
    public function __construct(
        private readonly TransferDbConnectionResolver $resolver,
        private readonly TransferDbSchemaManager $schemaManager,
        private readonly TransferDbWriter $writer,
    ) {
    }

    /**
     * @param array<string, mixed> $endpoint
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public function write(string|int $workspaceIdentifier, array $endpoint, array $result): array
    {
        $resolved = $this->resolver->resolve($workspaceIdentifier);
        return $this->writeResolved($resolved['pdo'], $resolved['workspace'], $endpoint, $result);
    }

    /**
     * @param array<string, mixed> $workspace
     * @param array<string, mixed> $endpoint
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public function writeResolved(PDO $pdo, array $workspace, array $endpoint, array $result): array
    {
        $this->schemaManager->migrate($pdo);
        $workspaceKey = (string) ($workspace['slug'] ?? $workspace['id'] ?? 'workspace');
        $endpointKey = (string) ($endpoint['endpoint_key'] ?? $endpoint['slug'] ?? 'endpoint');
        $items = $this->items($result);
        $resultJson = $this->writer->json($result);
        $resultHash = hash('sha256', $resultJson);

        $pdo->beginTransaction();
        try {
            $sourceId = $this->writer->source($pdo, [
                'workspace_key' => $workspaceKey,
                'source_type' => 'endpoint',
                'source_key' => $endpointKey,
                'provider' => 'luna',
                'schema_key' => $endpoint['schema_key'] ?? null,
                'schema_version' => $endpoint['schema_version'] ?? null,
            ]);
            $batchId = $this->writer->batch($pdo, $sourceId, [
                'external_id' => $endpointKey . ':' . substr($resultHash, 0, 16),
                'batch_type' => 'endpoint_snapshot',
                'status' => 'staged',
                'record_count' => count($items),
                'payload_hash' => $resultHash,
                'metadata' => [
                    'endpoint_id' => $endpoint['id'] ?? null,
                    'endpoint_key' => $endpointKey,
                    'result_count' => count($items),
                ],
                'generated_at' => date('Y-m-d H:i:s'),
            ]);
            $snapshotId = $this->snapshot($pdo, $batchId, $workspaceKey, $endpoint, count($items), $resultHash, $resultJson);
            $recordIds = [];
            foreach ($items as $index => $item) {
                $recordIds[] = $this->writer->endpointSnapshotRecord($pdo, $snapshotId, $batchId, $sourceId, $item, [
                    'record_index' => $index,
                    'operation' => 'snapshot',
                    'status' => 'staged',
                    'schema_key' => $endpoint['schema_key'] ?? null,
                    'schema_version' => $endpoint['schema_version'] ?? null,
                ]);
            }
            $this->writer->log($pdo, $workspaceKey, 'info', 'Endpoint Snapshot in TransferDB gespeichert.', [
                'endpoint_key' => $endpointKey,
                'batch_id' => $batchId,
                'snapshot_id' => $snapshotId,
                'record_count' => count($recordIds),
            ], 'endpoint', (string) ($endpoint['id'] ?? $endpointKey));
            $pdo->commit();

            return [
                'success' => true,
                'source_id' => $sourceId,
                'batch_id' => $batchId,
                'snapshot_id' => $snapshotId,
                'record_count' => count($recordIds),
                'record_ids' => $recordIds,
            ];
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $result
     * @return list<array<string, mixed>>
     */
    private function items(array $result): array
    {
        $items = $result['items'] ?? null;
        if (is_array($items)) {
            return array_values(array_filter($items, static fn (mixed $item): bool => is_array($item)));
        }

        return [$result];
    }

    /**
     * @param array<string, mixed> $endpoint
     */
    private function snapshot(\PDO $pdo, int $batchId, string $workspaceKey, array $endpoint, int $count, string $resultHash, string $resultJson): int
    {
        $statement = $pdo->prepare(
            'INSERT INTO luna_endpoint_snapshots
             (batch_id, workspace_key, endpoint_key, mapping_id, process_id, process_run_id, schema_key, schema_version,
              result_count, result_hash, result_json, status, created_at, updated_at)
             VALUES
             (:batch_id, :workspace_key, :endpoint_key, :mapping_id, :process_id, :process_run_id, :schema_key, :schema_version,
              :result_count, :result_hash, :result_json, :status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
        );
        $statement->execute([
            'batch_id' => $batchId,
            'workspace_key' => $workspaceKey,
            'endpoint_key' => (string) ($endpoint['endpoint_key'] ?? ''),
            'mapping_id' => empty($endpoint['mapping_set_id']) ? null : (int) $endpoint['mapping_set_id'],
            'process_id' => $endpoint['process_id'] ?? null,
            'process_run_id' => $endpoint['process_run_id'] ?? null,
            'schema_key' => $endpoint['schema_key'] ?? null,
            'schema_version' => $endpoint['schema_version'] ?? null,
            'result_count' => $count,
            'result_hash' => $resultHash,
            'result_json' => $resultJson,
            'status' => 'staged',
        ]);

        return (int) $pdo->lastInsertId();
    }
}
