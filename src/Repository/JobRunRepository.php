<?php

declare(strict_types=1);

namespace Luna\Repository;

use Luna\Database\SystemDatabase;

final class JobRunRepository
{
    public function __construct(private readonly SystemDatabase $database) {}

    public function createRun(?int $jobId, ?int $workspaceId, ?int $mappingSetId, bool $dryRun): int
    {
        $statement = $this->database->pdo()->prepare(
            'INSERT INTO luna_job_runs (job_id, workspace_id, mapping_set_id, status, dry_run, created_at, updated_at)
             VALUES (:job_id, :workspace_id, :mapping_set_id, :status, :dry_run, NOW(), NOW())',
        );
        $statement->execute([
            'job_id' => $jobId,
            'workspace_id' => $workspaceId,
            'mapping_set_id' => $mappingSetId,
            'status' => 'pending',
            'dry_run' => $dryRun ? 1 : 0,
        ]);
        return (int) $this->database->pdo()->lastInsertId();
    }

    public function markRunning(int $runId): void
    {
        $statement = $this->database->pdo()->prepare("UPDATE luna_job_runs SET status = 'running', started_at = NOW(), updated_at = NOW() WHERE id = :id");
        $statement->execute(['id' => $runId]);
    }

    public function markFinished(int $runId, array $summary): void
    {
        $status = ((int) ($summary['error_count'] ?? 0)) > 0 ? 'partial' : 'success';
        $statement = $this->database->pdo()->prepare(
            'UPDATE luna_job_runs SET status = :status, finished_at = NOW(), source_count = :source_count,
             transformed_count = :transformed_count, written_count = :written_count, skipped_count = :skipped_count,
             error_count = :error_count, summary_json = :summary_json, error_message = NULL, updated_at = NOW() WHERE id = :id',
        );
        $statement->execute([
            'id' => $runId,
            'status' => $status,
            'source_count' => (int) ($summary['source_count'] ?? 0),
            'transformed_count' => (int) ($summary['transformed_count'] ?? 0),
            'written_count' => (int) ($summary['written_count'] ?? 0),
            'skipped_count' => (int) ($summary['skipped_count'] ?? 0),
            'error_count' => (int) ($summary['error_count'] ?? 0),
            'summary_json' => json_encode($this->maskSecrets($summary), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function markFailed(int $runId, string $message, array $summary = []): void
    {
        $statement = $this->database->pdo()->prepare(
            "UPDATE luna_job_runs SET status = 'failed', finished_at = NOW(), source_count = :source_count,
             transformed_count = :transformed_count, written_count = :written_count, skipped_count = :skipped_count,
             error_count = :error_count, summary_json = :summary_json, error_message = :message, updated_at = NOW() WHERE id = :id",
        );
        $statement->execute([
            'id' => $runId,
            'source_count' => (int) ($summary['source_count'] ?? 0),
            'transformed_count' => (int) ($summary['transformed_count'] ?? 0),
            'written_count' => (int) ($summary['written_count'] ?? 0),
            'skipped_count' => (int) ($summary['skipped_count'] ?? 0),
            'error_count' => max(1, (int) ($summary['error_count'] ?? 1)),
            'summary_json' => json_encode($this->maskSecrets($summary), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'message' => $this->safeMessage($message),
        ]);
    }

    public function addLog(int $runId, string $level, string $message, array $context = []): void
    {
        $statement = $this->database->pdo()->prepare(
            'INSERT INTO luna_job_run_logs (job_run_id, level, message, context_json, created_at)
             VALUES (:job_run_id, :level, :message, :context_json, NOW())',
        );
        $statement->execute([
            'job_run_id' => $runId,
            'level' => $level,
            'message' => $this->safeMessage($message),
            'context_json' => json_encode($this->maskSecrets($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function findRun(int $runId): ?array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT jr.*, j.name AS job_name, j.report_recipients, ms.name AS mapping_name
             FROM luna_job_runs jr
             LEFT JOIN luna_jobs j ON j.id = jr.job_id
             LEFT JOIN luna_mapping_sets ms ON ms.id = jr.mapping_set_id
             WHERE jr.id = :id',
        );
        $statement->execute(['id' => $runId]);
        $run = $statement->fetch();
        return $run === false ? null : $run;
    }

    public function logsForRun(int $runId): array
    {
        $statement = $this->database->pdo()->prepare('SELECT * FROM luna_job_run_logs WHERE job_run_id = :id ORDER BY id');
        $statement->execute(['id' => $runId]);
        return $statement->fetchAll();
    }

    public function runsForJob(int $jobId): array
    {
        $statement = $this->database->pdo()->prepare('SELECT * FROM luna_job_runs WHERE job_id = :id ORDER BY created_at DESC, id DESC');
        $statement->execute(['id' => $jobId]);
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
}
