<?php

declare(strict_types=1);

namespace Luna\Repository;

use Luna\Database\SystemDatabase;
use PDO;

final class ProcessRepository
{
    public function __construct(
        private readonly SystemDatabase $database,
        private readonly ?PDO $pdo = null,
    ) {
    }

    public function all(): array
    {
        $statement = $this->pdo()->query(
            'SELECT p.*, w.name AS workspace_name,
                    (SELECT COUNT(*) FROM luna_process_steps ps WHERE ps.process_id = p.id) AS step_count,
                    (SELECT COUNT(*) FROM luna_process_triggers pt WHERE pt.process_id = p.id) AS trigger_count,
                    (SELECT pr.status FROM luna_process_runs pr WHERE pr.process_id = p.id ORDER BY pr.created_at DESC, pr.id DESC LIMIT 1) AS last_run_status
             FROM luna_processes p
             INNER JOIN luna_workspaces w ON w.id = p.workspace_id
             ORDER BY p.updated_at DESC, p.name',
        );

        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT p.*, w.name AS workspace_name
             FROM luna_processes p
             INNER JOIN luna_workspaces w ON w.id = p.workspace_id
             WHERE p.id = :id',
        );
        $statement->execute(['id' => $id]);
        $process = $statement->fetch();

        return $process === false ? null : $process;
    }

    public function create(array $data): int
    {
        $payload = $this->processPayload($data);
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_processes
             (workspace_id, name, process_key, description, status, default_mode, created_at, updated_at)
             VALUES (:workspace_id, :name, :process_key, :description, :status, :default_mode, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
        );
        $statement->execute($payload);

        return (int) $this->pdo()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $payload = $this->processPayload($data);
        $payload['id'] = $id;
        $statement = $this->pdo()->prepare(
            'UPDATE luna_processes
             SET workspace_id = :workspace_id,
                 name = :name,
                 process_key = :process_key,
                 description = :description,
                 status = :status,
                 default_mode = :default_mode,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
        );
        $statement->execute($payload);
    }

    public function delete(int $id): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $statement = $pdo->prepare('DELETE FROM luna_process_run_logs WHERE process_run_id IN (SELECT id FROM luna_process_runs WHERE process_id = :id)');
            $statement->execute(['id' => $id]);
            $statement = $pdo->prepare('DELETE FROM luna_process_runs WHERE process_id = :id');
            $statement->execute(['id' => $id]);
            $statement = $pdo->prepare('DELETE FROM luna_process_triggers WHERE process_id = :id');
            $statement->execute(['id' => $id]);
            $statement = $pdo->prepare('DELETE FROM luna_process_steps WHERE process_id = :id');
            $statement->execute(['id' => $id]);
            $statement = $pdo->prepare('DELETE FROM luna_processes WHERE id = :id');
            $statement->execute(['id' => $id]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function stepsForProcess(int $processId): array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM luna_process_steps WHERE process_id = :id ORDER BY position, id');
        $statement->execute(['id' => $processId]);

        return $statement->fetchAll();
    }

    public function findStep(int $stepId): ?array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM luna_process_steps WHERE id = :id');
        $statement->execute(['id' => $stepId]);
        $step = $statement->fetch();

        return $step === false ? null : $step;
    }

    public function addStep(int $processId, array $data): int
    {
        $payload = $this->stepPayload($data);
        $payload['process_id'] = $processId;
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_process_steps
             (process_id, position, name, step_type, reference_type, reference_id, config_json, is_enabled, continue_on_error, created_at, updated_at)
             VALUES (:process_id, :position, :name, :step_type, :reference_type, :reference_id, :config_json, :is_enabled, :continue_on_error, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
        );
        $statement->execute($payload);

        return (int) $this->pdo()->lastInsertId();
    }

    public function updateStep(int $stepId, array $data): void
    {
        $payload = $this->stepPayload($data);
        $payload['id'] = $stepId;
        $statement = $this->pdo()->prepare(
            'UPDATE luna_process_steps
             SET position = :position,
                 name = :name,
                 step_type = :step_type,
                 reference_type = :reference_type,
                 reference_id = :reference_id,
                 config_json = :config_json,
                 is_enabled = :is_enabled,
                 continue_on_error = :continue_on_error,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
        );
        $statement->execute($payload);
    }

    public function deleteStep(int $stepId): void
    {
        $statement = $this->pdo()->prepare('DELETE FROM luna_process_steps WHERE id = :id');
        $statement->execute(['id' => $stepId]);
    }

    public static function normalizeKey(string $value): string
    {
        $key = strtolower(trim($value));
        $key = preg_replace('/[^a-z0-9_]+/', '_', $key) ?? '';
        $key = trim($key, '_');

        return $key;
    }

    private function processPayload(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $keySource = trim((string) ($data['process_key'] ?? ''));
        $key = self::normalizeKey($keySource === '' ? $name : $keySource);

        return [
            'workspace_id' => empty($data['workspace_id']) ? null : (int) $data['workspace_id'],
            'name' => $name,
            'process_key' => $key,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'status' => in_array((string) ($data['status'] ?? 'draft'), ['draft', 'active', 'inactive'], true) ? (string) ($data['status'] ?? 'draft') : 'draft',
            'default_mode' => in_array((string) ($data['default_mode'] ?? 'dry_run'), ['run', 'dry_run'], true) ? (string) ($data['default_mode'] ?? 'dry_run') : 'dry_run',
        ];
    }

    private function stepPayload(array $data): array
    {
        $stepType = (string) ($data['step_type'] ?? 'mapping_run');

        return [
            'position' => max(0, (int) ($data['position'] ?? 0)),
            'name' => trim((string) ($data['name'] ?? '')) ?: 'Mapping ausführen',
            'step_type' => in_array($stepType, ['mapping_run'], true) ? $stepType : 'mapping_run',
            'reference_type' => 'mapping_set',
            'reference_id' => empty($data['reference_id']) ? null : (int) $data['reference_id'],
            'config_json' => trim((string) ($data['config_json'] ?? '')) ?: null,
            'is_enabled' => array_key_exists('is_enabled', $data) ? (! empty($data['is_enabled']) ? 1 : 0) : 1,
            'continue_on_error' => ! empty($data['continue_on_error']) ? 1 : 0,
        ];
    }

    private function pdo(): PDO
    {
        return $this->pdo ?? $this->database->pdo();
    }
}
