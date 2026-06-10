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
            'SELECT * FROM luna_dataset_transfer_fields WHERE transfer_id = :id AND (group_id IS NULL OR group_id = 0) ORDER BY sort_order, id',
        );
        $statement->execute(['id' => $transferId]);

        return $statement->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function groupsForTransfer(int $transferId): array
    {
        if (! $this->columnExists('luna_dataset_transfer_fields', 'group_id')) {
            return [];
        }

        $statement = $this->pdo()->prepare(
            'SELECT * FROM luna_dataset_transfer_groups WHERE transfer_id = :id ORDER BY sort_order, id',
        );
        $statement->execute(['id' => $transferId]);

        return $statement->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fieldsForGroup(int $groupId): array
    {
        if (! $this->columnExists('luna_dataset_transfer_fields', 'group_id')) {
            return [];
        }

        $statement = $this->pdo()->prepare(
            'SELECT * FROM luna_dataset_transfer_fields WHERE group_id = :id ORDER BY sort_order, id',
        );
        $statement->execute(['id' => $groupId]);

        return $statement->fetchAll();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function addGroup(int $transferId, array $data): int
    {
        $payload = $this->groupPayload($data);
        $payload['transfer_id'] = $transferId;
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_dataset_transfer_groups
             (transfer_id, name, group_type, source_path, target_table, operation_type, upsert_key, parent_link_source, parent_link_target, sort_order, created_at, updated_at)
             VALUES (:transfer_id, :name, :group_type, :source_path, :target_table, :operation_type, :upsert_key, :parent_link_source, :parent_link_target, :sort_order, NOW(), NOW())',
        );
        $statement->execute($payload);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateGroup(int $groupId, array $data): void
    {
        $payload = $this->groupPayload($data);
        $payload['id'] = $groupId;
        $statement = $this->pdo()->prepare(
            'UPDATE luna_dataset_transfer_groups
             SET name = :name,
                 group_type = :group_type,
                 source_path = :source_path,
                 target_table = :target_table,
                 operation_type = :operation_type,
                 upsert_key = :upsert_key,
                 parent_link_source = :parent_link_source,
                 parent_link_target = :parent_link_target,
                 sort_order = :sort_order,
                 updated_at = NOW()
             WHERE id = :id',
        );
        $statement->execute($payload);
    }

    public function deleteGroup(int $groupId): void
    {
        $statement = $this->pdo()->prepare('DELETE FROM luna_dataset_transfer_groups WHERE id = :id');
        $statement->execute(['id' => $groupId]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function addField(int $transferId, array $data): int
    {
        $payload = $this->fieldPayload($data);
        $payload['transfer_id'] = $transferId;
        $hasGroupId = $this->columnExists('luna_dataset_transfer_fields', 'group_id');
        $sql = $hasGroupId
            ? 'INSERT INTO luna_dataset_transfer_fields
               (transfer_id, group_id, dataset_field, target_column, sort_order, created_at, updated_at)
               VALUES (:transfer_id, :group_id, :dataset_field, :target_column, :sort_order, NOW(), NOW())'
            : 'INSERT INTO luna_dataset_transfer_fields
               (transfer_id, dataset_field, target_column, sort_order, created_at, updated_at)
               VALUES (:transfer_id, :dataset_field, :target_column, :sort_order, NOW(), NOW())';
        if (! $hasGroupId) {
            unset($payload['group_id']);
        }
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($payload);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateField(int $fieldId, array $data): void
    {
        $payload = $this->fieldPayload($data);
        unset($payload['group_id']);
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

    public function canDelete(int $id): DeleteCheckResult
    {
        $transfer = $this->find($id);
        if ($transfer === null) {
            return DeleteCheckResult::allowed();
        }

        $blockingNames = $this->runNamesForTransfer($id);
        if ($blockingNames === []) {
            return DeleteCheckResult::allowed();
        }

        return DeleteCheckResult::blocked(
            sprintf(
                'Transfer "%s" kann nicht gelöscht werden, weil noch Transfer Runs existieren. Bitte prüfen oder bereinigen Sie diese Runs zuerst.',
                (string) ($transfer['name'] ?? ('#' . $id)),
            ),
            $blockingNames,
            ['transfer_runs' => count($blockingNames)],
        );
    }

    public function delete(int $id): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $pdo->prepare('DELETE FROM luna_dataset_transfer_fields WHERE transfer_id = :id')->execute(['id' => $id]);
            if ($this->tableExists('luna_dataset_transfer_groups')) {
                $pdo->prepare('DELETE FROM luna_dataset_transfer_groups WHERE transfer_id = :id')->execute(['id' => $id]);
            }
            $pdo->prepare('DELETE FROM luna_dataset_transfers WHERE id = :id')->execute(['id' => $id]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
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
            'group_id' => empty($data['group_id']) ? null : (int) $data['group_id'],
            'dataset_field' => trim((string) ($data['dataset_field'] ?? '')),
            'target_column' => trim((string) ($data['target_column'] ?? '')),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function groupPayload(array $data): array
    {
        $groupType = (string) ($data['group_type'] ?? 'root');
        $operation = (string) ($data['operation_type'] ?? 'upsert');

        return [
            'name' => trim((string) ($data['name'] ?? '')),
            'group_type' => in_array($groupType, ['root', 'child'], true) ? $groupType : 'root',
            'source_path' => trim((string) ($data['source_path'] ?? '$')) ?: '$',
            'target_table' => trim((string) ($data['target_table'] ?? '')),
            'operation_type' => in_array($operation, ['insert', 'update', 'upsert'], true) ? $operation : 'upsert',
            'upsert_key' => trim((string) ($data['upsert_key'] ?? '')) ?: null,
            'parent_link_source' => trim((string) ($data['parent_link_source'] ?? '')) ?: null,
            'parent_link_target' => trim((string) ($data['parent_link_target'] ?? '')) ?: null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            $this->pdo()->query(sprintf('SELECT %s FROM %s WHERE 1 = 0', $column, $table));

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            $this->pdo()->query(sprintf('SELECT 1 FROM %s WHERE 1 = 0', $table));

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    private function runNamesForTransfer(int $id): array
    {
        if (! $this->tableExists('luna_dataset_transfer_runs') || ! $this->columnExists('luna_dataset_transfer_runs', 'transfer_id')) {
            return [];
        }

        $createdColumn = $this->columnExists('luna_dataset_transfer_runs', 'created_at') ? 'created_at' : 'id';
        $statement = $this->pdo()->prepare(
            sprintf(
                'SELECT id, %s AS created_at FROM luna_dataset_transfer_runs WHERE transfer_id = :id ORDER BY id DESC LIMIT 10',
                $createdColumn,
            ),
        );
        $statement->execute(['id' => $id]);

        $names = [];
        foreach ($statement->fetchAll() as $run) {
            $createdAt = (string) ($run['created_at'] ?? '');
            $names[] = 'Transfer Run #' . (int) $run['id'] . ($createdAt === '' ? '' : ' vom ' . $createdAt);
        }

        return $names;
    }

    private function pdo(): PDO
    {
        return $this->pdo ?? $this->database->pdo();
    }
}
