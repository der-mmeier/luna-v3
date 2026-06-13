<?php

declare(strict_types=1);

namespace Luna\Repository;

use Luna\Database\SystemDatabase;
use PDO;

final class ReportRepository
{
    public function __construct(
        private readonly SystemDatabase $database,
        private readonly ?PDO $connection = null,
    ) {
    }

    public function create(array $data): int
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_reports (job_run_id, workspace_id, type, subject, body, recipients, status, created_at, updated_at)
             VALUES (:job_run_id, :workspace_id, :type, :subject, :body, :recipients, :status, NOW(), NOW())',
        );
        $statement->execute([
            'job_run_id' => $data['job_run_id'] ?? null,
            'workspace_id' => empty($data['workspace_id']) ? null : (int) $data['workspace_id'],
            'type' => trim((string) ($data['type'] ?? 'manual')) ?: 'manual',
            'subject' => trim((string) ($data['subject'] ?? '')),
            'body' => trim((string) ($data['body'] ?? '')),
            'recipients' => trim((string) ($data['recipients'] ?? '')) ?: null,
            'status' => trim((string) ($data['status'] ?? 'created')) ?: 'created',
        ]);
        return (int) $this->pdo()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $statement = $this->pdo()->prepare(
            'UPDATE luna_reports
             SET workspace_id = :workspace_id,
                 type = :type,
                 subject = :subject,
                 body = :body,
                 recipients = :recipients,
                 status = :status,
                 updated_at = NOW()
             WHERE id = :id',
        );
        $statement->execute([
            'id' => $id,
            'workspace_id' => empty($data['workspace_id']) ? null : (int) $data['workspace_id'],
            'type' => trim((string) ($data['type'] ?? 'manual')) ?: 'manual',
            'subject' => trim((string) ($data['subject'] ?? '')),
            'body' => trim((string) ($data['body'] ?? '')),
            'recipients' => trim((string) ($data['recipients'] ?? '')) ?: null,
            'status' => trim((string) ($data['status'] ?? 'created')) ?: 'created',
        ]);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo()->prepare('DELETE FROM luna_reports WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM luna_reports WHERE id = :id');
        $statement->execute(['id' => $id]);
        $report = $statement->fetch();
        return $report === false ? null : $report;
    }

    public function all(): array
    {
        return $this->pdo()->query(
            'SELECT r.*, w.name AS workspace_name
             FROM luna_reports r
             LEFT JOIN luna_workspaces w ON w.id = r.workspace_id
             ORDER BY r.created_at DESC, r.id DESC',
        )->fetchAll();
    }

    public function latestForJob(int $jobId): ?array
    {
        $statement = $this->pdo()->prepare(
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
        $statement = $this->pdo()->prepare(
            'SELECT * FROM luna_reports WHERE workspace_id = :workspace_id ORDER BY created_at DESC, id DESC LIMIT 1',
        );
        $statement->execute(['workspace_id' => $workspaceId]);
        $report = $statement->fetch();

        return $report === false ? null : $report;
    }

    public function markSent(int $id): void
    {
        $statement = $this->pdo()->prepare("UPDATE luna_reports SET status = 'sent', sent_at = NOW(), updated_at = NOW() WHERE id = :id");
        $statement->execute(['id' => $id]);
    }

    public function markFailed(int $id, string $message): void
    {
        $statement = $this->pdo()->prepare("UPDATE luna_reports SET status = 'mail_failed', error_message = :message, updated_at = NOW() WHERE id = :id");
        $statement->execute(['id' => $id, 'message' => $message]);
    }

    public function markSkipped(int $id, string $message): void
    {
        $statement = $this->pdo()->prepare("UPDATE luna_reports SET status = 'mail_skipped', error_message = :message, updated_at = NOW() WHERE id = :id");
        $statement->execute(['id' => $id, 'message' => $message]);
    }

    private function pdo(): PDO
    {
        return $this->connection ?? $this->database->pdo();
    }
}
