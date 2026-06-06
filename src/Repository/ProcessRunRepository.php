<?php

declare(strict_types=1);

namespace Luna\Repository;

use Luna\Database\SystemDatabase;
use PDO;

final class ProcessRunRepository
{
    public function __construct(
        private readonly SystemDatabase $database,
        private readonly ?PDO $pdo = null,
    ) {
    }

    public function createRun(
        int $processId,
        string $mode,
        string $triggerType,
        ?string $triggerRef = null,
        array $context = [],
        ?int $triggerId = null,
        ?string $triggerSource = null,
        array $triggerPayloadMeta = [],
    ): int {
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_process_runs
             (process_id, status, mode, trigger_type, trigger_ref, trigger_id, trigger_source, trigger_payload_meta, context_json, created_at, updated_at)
             VALUES (:process_id, :status, :mode, :trigger_type, :trigger_ref, :trigger_id, :trigger_source, :trigger_payload_meta, :context_json, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
        );
        $statement->execute([
            'process_id' => $processId,
            'status' => 'queued',
            'mode' => $mode,
            'trigger_type' => $triggerType,
            'trigger_ref' => $triggerRef,
            'trigger_id' => $triggerId,
            'trigger_source' => $triggerSource,
            'trigger_payload_meta' => json_encode($this->maskSecrets($triggerPayloadMeta), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'context_json' => json_encode($this->maskSecrets($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function markRunning(int $runId): void
    {
        $statement = $this->pdo()->prepare("UPDATE luna_process_runs SET status = 'running', started_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $statement->execute(['id' => $runId]);
    }

    public function markSuccess(int $runId, int $durationMs, array $context = []): void
    {
        $statement = $this->pdo()->prepare(
            "UPDATE luna_process_runs
             SET status = 'success', finished_at = CURRENT_TIMESTAMP, duration_ms = :duration_ms, error_message = NULL, context_json = :context_json, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id",
        );
        $statement->execute([
            'id' => $runId,
            'duration_ms' => $durationMs,
            'context_json' => json_encode($this->maskSecrets($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function markFailed(int $runId, string $message, int $durationMs, array $context = []): void
    {
        $statement = $this->pdo()->prepare(
            "UPDATE luna_process_runs
             SET status = 'failed', finished_at = CURRENT_TIMESTAMP, duration_ms = :duration_ms, error_message = :error_message, context_json = :context_json, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id",
        );
        $statement->execute([
            'id' => $runId,
            'duration_ms' => $durationMs,
            'error_message' => $this->safeMessage($message),
            'context_json' => json_encode($this->maskSecrets($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function addLog(int $runId, string $level, string $message, array $context = []): void
    {
        $level = in_array($level, ['debug', 'info', 'warning', 'error'], true) ? $level : 'info';
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_process_run_logs (process_run_id, level, message, context_json, created_at)
             VALUES (:process_run_id, :level, :message, :context_json, CURRENT_TIMESTAMP)',
        );
        $statement->execute([
            'process_run_id' => $runId,
            'level' => $level,
            'message' => $this->safeMessage($message),
            'context_json' => json_encode($this->maskSecrets($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function findRun(int $runId): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT pr.*, p.name AS process_name, p.process_key, w.name AS workspace_name
             FROM luna_process_runs pr
             INNER JOIN luna_processes p ON p.id = pr.process_id
             INNER JOIN luna_workspaces w ON w.id = p.workspace_id
             WHERE pr.id = :id',
        );
        $statement->execute(['id' => $runId]);
        $run = $statement->fetch();

        return $run === false ? null : $run;
    }

    public function runsForProcess(int $processId, int $limit = 20): array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM luna_process_runs WHERE process_id = :id ORDER BY created_at DESC, id DESC LIMIT :limit');
        $statement->bindValue('id', $processId, PDO::PARAM_INT);
        $statement->bindValue('limit', max(1, min($limit, 100)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function logsForRun(int $runId): array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM luna_process_run_logs WHERE process_run_id = :id ORDER BY id');
        $statement->execute(['id' => $runId]);

        return $statement->fetchAll();
    }

    private function safeMessage(string $message): string
    {
        return preg_replace('/(password|secret|token|api_key|app_key|client_secret|key)=([^\\s]+)/i', '$1=***', $message) ?? 'Error';
    }

    private function maskSecrets(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_string($key) && preg_match('/secret|password|token|api_key|app_key|client_secret|key/i', $key) === 1) {
                $context[$key] = '***';
            } elseif (is_array($value)) {
                $context[$key] = $this->maskSecrets($value);
            }
        }

        return $context;
    }

    private function pdo(): PDO
    {
        return $this->pdo ?? $this->database->pdo();
    }
}
