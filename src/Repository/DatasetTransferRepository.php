<?php

declare(strict_types=1);

namespace Luna\Repository;

use Luna\Database\SystemDatabase;
use PDO;

final class DatasetTransferRepository
{
    public function __construct(
        private readonly SystemDatabase $database,
        private readonly ?PDO $pdo = null,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $statement = $this->pdo()->query(
            'SELECT dt.*, w.name AS workspace_name, cp.name AS target_connection_name
             FROM luna_dataset_transfers dt
             LEFT JOIN luna_workspaces w ON w.id = dt.workspace_id
             LEFT JOIN luna_connection_profiles cp ON cp.id = dt.target_connection_id
             ORDER BY dt.updated_at DESC, dt.name',
        );

        return $statement->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT dt.*, w.name AS workspace_name, cp.name AS target_connection_name
             FROM luna_dataset_transfers dt
             LEFT JOIN luna_workspaces w ON w.id = dt.workspace_id
             LEFT JOIN luna_connection_profiles cp ON cp.id = dt.target_connection_id
             WHERE dt.id = :id',
        );
        $statement->execute(['id' => $id]);
        $transfer = $statement->fetch();

        return $transfer === false ? null : $transfer;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_dataset_transfers
             (workspace_id, name, description, status, source_dataset, target_connection_id, target_table, operation_type, upsert_key, created_at, updated_at)
             VALUES (:workspace_id, :name, :description, :status, :source_dataset, :target_connection_id, :target_table, :operation_type, :upsert_key, NOW(), NOW())',
        );
        $statement->execute($this->payload($data));

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $payload = $this->payload($data);
        $payload['id'] = $id;
        $statement = $this->pdo()->prepare(
            'UPDATE luna_dataset_transfers
             SET workspace_id = :workspace_id,
                 name = :name,
                 description = :description,
                 status = :status,
                 source_dataset = :source_dataset,
                 target_connection_id = :target_connection_id,
                 target_table = :target_table,
                 operation_type = :operation_type,
                 upsert_key = :upsert_key,
                 updated_at = NOW()
             WHERE id = :id',
        );
        $statement->execute($payload);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fieldsForTransfer(int $transferId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT * FROM luna_dataset_transfer_fields WHERE transfer_id = :id ORDER BY sort_order, id',
        );
        $statement->execute(['id' => $transferId]);

        return $statement->fetchAll();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function addField(int $transferId, array $data): int
    {
        $payload = $this->fieldPayload($data);
        $payload['transfer_id'] = $transferId;
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_dataset_transfer_fields
             (transfer_id, dataset_field, target_column, sort_order, created_at, updated_at)
             VALUES (:transfer_id, :dataset_field, :target_column, :sort_order, NOW(), NOW())',
        );
        $statement->execute($payload);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateField(int $fieldId, array $data): void
    {
        $payload = $this->fieldPayload($data);
        $payload['id'] = $fieldId;
        $statement = $this->pdo()->prepare(
            'UPDATE luna_dataset_transfer_fields
             SET dataset_field = :dataset_field,
                 target_column = :target_column,
                 sort_order = :sort_order,
                 updated_at = NOW()
             WHERE id = :id',
        );
        $statement->execute($payload);
    }

    public function deleteField(int $fieldId): void
    {
        $statement = $this->pdo()->prepare('DELETE FROM luna_dataset_transfer_fields WHERE id = :id');
        $statement->execute(['id' => $fieldId]);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        return [
            'workspace_id' => empty($data['workspace_id']) ? null : (int) $data['workspace_id'],
            'name' => trim((string) ($data['name'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'status' => in_array((string) ($data['status'] ?? 'draft'), ['draft', 'active', 'archived'], true)
                ? (string) ($data['status'] ?? 'draft')
                : 'draft',
            'source_dataset' => trim((string) ($data['source_dataset'] ?? '')),
            'target_connection_id' => empty($data['target_connection_id']) ? null : (int) $data['target_connection_id'],
            'target_table' => trim((string) ($data['target_table'] ?? '')) ?: null,
            'operation_type' => in_array((string) ($data['operation_type'] ?? 'upsert'), ['insert', 'update', 'upsert'], true)
                ? (string) ($data['operation_type'] ?? 'upsert')
                : 'upsert',
            'upsert_key' => trim((string) ($data['upsert_key'] ?? '')) ?: null,
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function fieldPayload(array $data): array
    {
        return [
            'dataset_field' => trim((string) ($data['dataset_field'] ?? '')),
            'target_column' => trim((string) ($data['target_column'] ?? '')),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    private function pdo(): PDO
    {
        return $this->pdo ?? $this->database->pdo();
    }
}
