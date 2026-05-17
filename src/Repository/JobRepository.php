<?php

declare(strict_types=1);

namespace Luna\Repository;

use Luna\Database\SystemDatabase;

final class JobRepository
{
    public function __construct(private readonly SystemDatabase $database) {}

    public function all(): array
    {
        $sql = 'SELECT j.*, w.name AS workspace_name, ms.name AS mapping_name
                FROM luna_jobs j
                LEFT JOIN luna_workspaces w ON w.id = j.workspace_id
                LEFT JOIN luna_mapping_sets ms ON ms.id = j.mapping_set_id
                ORDER BY j.updated_at DESC, j.name';
        return $this->database->pdo()->query($sql)->fetchAll();
    }

    public function active(): array
    {
        $statement = $this->database->pdo()->query("SELECT * FROM luna_jobs WHERE status = 'active' ORDER BY name");
        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT j.*, w.name AS workspace_name, ms.name AS mapping_name
             FROM luna_jobs j
             LEFT JOIN luna_workspaces w ON w.id = j.workspace_id
             LEFT JOIN luna_mapping_sets ms ON ms.id = j.mapping_set_id
             WHERE j.id = :id',
        );
        $statement->execute(['id' => $id]);
        $job = $statement->fetch();
        return $job === false ? null : $job;
    }

    public function create(array $data): int
    {
        $statement = $this->database->pdo()->prepare(
            'INSERT INTO luna_jobs
             (workspace_id, mapping_set_id, name, type, status, run_mode, transfer_mode, dry_run_default, batch_size, row_limit, report_enabled, report_recipients, notes, created_at, updated_at)
             VALUES (:workspace_id, :mapping_set_id, :name, :type, :status, :run_mode, :transfer_mode, :dry_run_default, :batch_size, :row_limit, :report_enabled, :report_recipients, :notes, NOW(), NOW())',
        );
        $statement->execute($this->payload($data));
        return (int) $this->database->pdo()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $payload = $this->payload($data);
        $payload['id'] = $id;
        $statement = $this->database->pdo()->prepare(
            'UPDATE luna_jobs SET workspace_id = :workspace_id, mapping_set_id = :mapping_set_id, name = :name,
             type = :type, status = :status, run_mode = :run_mode, transfer_mode = :transfer_mode,
             dry_run_default = :dry_run_default, batch_size = :batch_size, row_limit = :row_limit,
             report_enabled = :report_enabled, report_recipients = :report_recipients, notes = :notes,
             updated_at = NOW() WHERE id = :id',
        );
        $statement->execute($payload);
    }

    public function delete(int $id): void
    {
        $statement = $this->database->pdo()->prepare('DELETE FROM luna_jobs WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function touchLastRun(int $id): void
    {
        $statement = $this->database->pdo()->prepare('UPDATE luna_jobs SET last_run_at = NOW(), updated_at = NOW() WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    private function payload(array $data): array
    {
        return [
            'workspace_id' => empty($data['workspace_id']) ? null : (int) $data['workspace_id'],
            'mapping_set_id' => empty($data['mapping_set_id']) ? null : (int) $data['mapping_set_id'],
            'name' => trim((string) ($data['name'] ?? '')),
            'type' => trim((string) ($data['type'] ?? 'mapping_transfer')) ?: 'mapping_transfer',
            'status' => trim((string) ($data['status'] ?? 'draft')) ?: 'draft',
            'run_mode' => trim((string) ($data['run_mode'] ?? 'manual')) ?: 'manual',
            'transfer_mode' => trim((string) ($data['transfer_mode'] ?? 'insert')) ?: 'insert',
            'dry_run_default' => ! empty($data['dry_run_default']) ? 1 : 0,
            'batch_size' => max(1, (int) ($data['batch_size'] ?? 100)),
            'row_limit' => empty($data['row_limit']) ? null : (int) $data['row_limit'],
            'report_enabled' => ! empty($data['report_enabled']) ? 1 : 0,
            'report_recipients' => trim((string) ($data['report_recipients'] ?? '')) ?: null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ];
    }
}
