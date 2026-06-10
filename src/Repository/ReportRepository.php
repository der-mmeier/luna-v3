<?php

declare(strict_types=1);

namespace Luna\Repository;

use Luna\Database\SystemDatabase;
use PDO;

final class ReportRepository
{
    public function __construct(
        private readonly SystemDatabase $database,
        private readonly ?PDO $pdo = null,
    ) {
    }

    public function create(array $data): int
    {
        $payload = $this->payload($data);
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_reports
             (job_run_id, workspace_id, type, report_key, name, subject, body, config_json, notes, recipients, status, created_at, updated_at)
             VALUES (:job_run_id, :workspace_id, :type, :report_key, :name, :subject, :body, :config_json, :notes, :recipients, :status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
        );
        $statement->execute($payload);

        return (int) $this->pdo()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $payload = $this->payload($data);
        $payload['id'] = $id;
        $statement = $this->pdo()->prepare(
            'UPDATE luna_reports
             SET job_run_id = :job_run_id,
                 workspace_id = :workspace_id,
                 type = :type,
                 report_key = :report_key,
                 name = :name,
                 subject = :subject,
                 body = :body,
                 config_json = :config_json,
                 notes = :notes,
                 recipients = :recipients,
                 status = :status,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
        );
        $statement->execute($payload);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo()->prepare('DELETE FROM luna_reports WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT r.*, w.name AS workspace_name
             FROM luna_reports r
             LEFT JOIN luna_workspaces w ON w.id = r.workspace_id
             WHERE r.id = :id',
        );
        $statement->execute(['id' => $id]);
        $report = $statement->fetch();

        return $report === false ? null : $report;
    }

    public function all(): array
    {
        $statement = $this->pdo()->query(
            'SELECT r.*, w.name AS workspace_name
             FROM luna_reports r
             LEFT JOIN luna_workspaces w ON w.id = r.workspace_id
             ORDER BY r.created_at DESC, r.id DESC',
        );

        return $statement->fetchAll();
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
        $statement = $this->pdo()->prepare("UPDATE luna_reports SET status = 'sent', sent_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $statement->execute(['id' => $id]);
    }

    public function markFailed(int $id, string $message): void
    {
        $statement = $this->pdo()->prepare("UPDATE luna_reports SET status = 'mail_failed', error_message = :message, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $statement->execute(['id' => $id, 'message' => $message]);
    }

    public function markSkipped(int $id, string $message): void
    {
        $statement = $this->pdo()->prepare("UPDATE luna_reports SET status = 'mail_skipped', error_message = :message, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $statement->execute(['id' => $id, 'message' => $message]);
    }

    public function keyExists(?int $workspaceId, string $key, ?int $ignoreId = null): bool
    {
        $key = self::normalizeKey($key);
        $sql = 'SELECT COUNT(*) FROM luna_reports WHERE report_key = :report_key';
        $params = ['report_key' => $key];
        if ($workspaceId === null) {
            $sql .= ' AND workspace_id IS NULL';
        } else {
            $sql .= ' AND workspace_id = :workspace_id';
            $params['workspace_id'] = $workspaceId;
        }
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :ignore_id';
            $params['ignore_id'] = $ignoreId;
        }

        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    public static function normalizeKey(string $value): string
    {
        $key = strtolower(trim($value));
        $key = preg_replace('/[^a-z0-9_.-]+/', '_', $key) ?? '';
        $key = trim($key, '_.-');

        return $key;
    }

    private function payload(array $data): array
    {
        $name = trim((string) ($data['name'] ?? $data['subject'] ?? ''));
        $key = trim((string) ($data['report_key'] ?? ''));
        if ($key === '') {
            $key = self::normalizeKey($name !== '' ? $name : 'report');
        }

        $configJson = $data['config_json'] ?? null;
        if ($configJson === null && isset($data['body'])) {
            $configJson = $this->looksLikeJson((string) $data['body']) ? (string) $data['body'] : null;
        }

        return [
            'job_run_id' => empty($data['job_run_id']) ? null : (int) $data['job_run_id'],
            'workspace_id' => empty($data['workspace_id']) ? null : (int) $data['workspace_id'],
            'type' => trim((string) ($data['type'] ?? 'process_runs')) ?: 'process_runs',
            'report_key' => self::normalizeKey($key),
            'name' => $name,
            'subject' => trim((string) ($data['subject'] ?? $name)),
            'body' => (string) ($data['body'] ?? $configJson ?? ''),
            'config_json' => $configJson === null || trim((string) $configJson) === '' ? null : trim((string) $configJson),
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'recipients' => trim((string) ($data['recipients'] ?? '')) ?: null,
            'status' => trim((string) ($data['status'] ?? 'draft')) ?: 'draft',
        ];
    }

    private function looksLikeJson(string $value): bool
    {
        $value = trim($value);
        if ($value === '' || ! in_array($value[0], ['{', '['], true)) {
            return false;
        }

        json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE;
    }

    private function pdo(): PDO
    {
        return $this->pdo ?? $this->database->pdo();
    }
}
