<?php

declare(strict_types=1);

namespace Luna\Repository;

use Luna\Database\SystemDatabase;

final class ReportRepository
{
    public function __construct(private readonly SystemDatabase $database) {}

    public function create(array $data): int
    {
        $statement = $this->database->pdo()->prepare(
            'INSERT INTO luna_reports (job_run_id, workspace_id, type, subject, body, recipients, status, created_at, updated_at)
             VALUES (:job_run_id, :workspace_id, :type, :subject, :body, :recipients, :status, NOW(), NOW())',
        );
        $statement->execute([
            'job_run_id' => $data['job_run_id'] ?? null,
            'workspace_id' => $data['workspace_id'] ?? null,
            'type' => $data['type'] ?? 'job_run',
            'subject' => $data['subject'],
            'body' => $data['body'],
            'recipients' => $data['recipients'] ?? null,
            'status' => $data['status'] ?? 'created',
        ]);
        return (int) $this->database->pdo()->lastInsertId();
    }

    public function find(int $id): ?array
    {
        $statement = $this->database->pdo()->prepare('SELECT * FROM luna_reports WHERE id = :id');
        $statement->execute(['id' => $id]);
        $report = $statement->fetch();
        return $report === false ? null : $report;
    }

    public function all(): array
    {
        return $this->database->pdo()->query('SELECT * FROM luna_reports ORDER BY created_at DESC, id DESC')->fetchAll();
    }

    public function latestForJob(int $jobId): ?array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT r.*
             FROM luna_reports r
             INNER JOIN luna_job_runs jr ON jr.id = r.job_run_id
             WHERE jr.job_id = :job_id
             ORDER BY r.created_at DESC, r.id DESC
             LIMIT 1',
        );
        $statement->execute(['job_id' => $jobId]);
        $report = $statement->fetch();

        return $report === false ? null : $report;
    }

    public function latestForWorkspace(int $workspaceId): ?array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT * FROM luna_reports WHERE workspace_id = :workspace_id ORDER BY created_at DESC, id DESC LIMIT 1',
        );
        $statement->execute(['workspace_id' => $workspaceId]);
        $report = $statement->fetch();

        return $report === false ? null : $report;
    }

    public function markSent(int $id): void
    {
        $statement = $this->database->pdo()->prepare("UPDATE luna_reports SET status = 'sent', sent_at = NOW(), updated_at = NOW() WHERE id = :id");
        $statement->execute(['id' => $id]);
    }

    public function markFailed(int $id, string $message): void
    {
        $statement = $this->database->pdo()->prepare("UPDATE luna_reports SET status = 'mail_failed', error_message = :message, updated_at = NOW() WHERE id = :id");
        $statement->execute(['id' => $id, 'message' => $message]);
    }

    public function markSkipped(int $id, string $message): void
    {
        $statement = $this->database->pdo()->prepare("UPDATE luna_reports SET status = 'mail_skipped', error_message = :message, updated_at = NOW() WHERE id = :id");
        $statement->execute(['id' => $id, 'message' => $message]);
    }
}
