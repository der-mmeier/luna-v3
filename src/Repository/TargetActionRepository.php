<?php

declare(strict_types=1);

namespace Luna\Repository;

use Luna\Database\SystemDatabase;
use PDO;

final class TargetActionRepository
{
    public const EXECUTABLE_TYPES = [
        'http_get',
        'http_post',
        'http_put',
        'file_export',
        'database_insert',
        'database_upsert',
    ];

    public const FUTURE_TYPES = [
        'woocommerce_api',
        'afterbuy_api',
        'erp_api',
        'amazon_sp_api',
    ];

    public const ALL_TYPES = [
        'http_get',
        'http_post',
        'http_put',
        'file_export',
        'database_insert',
        'database_upsert',
        'woocommerce_api',
        'afterbuy_api',
        'erp_api',
        'amazon_sp_api',
    ];

    public function __construct(
        private readonly SystemDatabase $database,
        private readonly ?PDO $pdo = null,
    ) {
    }

    public function all(): array
    {
        $statement = $this->pdo()->query(
            'SELECT ta.*, w.name AS workspace_name
             FROM luna_target_actions ta
             INNER JOIN luna_workspaces w ON w.id = ta.workspace_id
             ORDER BY ta.updated_at DESC, ta.name',
        );

        return $statement->fetchAll();
    }

    public function forWorkspace(?int $workspaceId): array
    {
        if ($workspaceId === null || $workspaceId <= 0) {
            return $this->all();
        }

        $statement = $this->pdo()->prepare(
            'SELECT ta.*, w.name AS workspace_name
             FROM luna_target_actions ta
             INNER JOIN luna_workspaces w ON w.id = ta.workspace_id
             WHERE ta.workspace_id = :workspace_id
             ORDER BY ta.name, ta.id',
        );
        $statement->execute(['workspace_id' => $workspaceId]);

        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT ta.*, w.name AS workspace_name
             FROM luna_target_actions ta
             INNER JOIN luna_workspaces w ON w.id = ta.workspace_id
             WHERE ta.id = :id',
        );
        $statement->execute(['id' => $id]);
        $action = $statement->fetch();

        return $action === false ? null : $action;
    }

    public function create(array $data): int
    {
        $payload = $this->payload($data);
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_target_actions
             (workspace_id, name, action_key, action_type, is_active, config_json, created_at, updated_at)
             VALUES (:workspace_id, :name, :action_key, :action_type, :is_active, :config_json, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
        );
        $statement->execute($payload);

        return (int) $this->pdo()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $payload = $this->payload($data);
        $payload['id'] = $id;
        $statement = $this->pdo()->prepare(
            'UPDATE luna_target_actions
             SET workspace_id = :workspace_id,
                 name = :name,
                 action_key = :action_key,
                 action_type = :action_type,
                 is_active = :is_active,
                 config_json = :config_json,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
        );
        $statement->execute($payload);
    }

    public function setActive(int $id, bool $active): void
    {
        $statement = $this->pdo()->prepare('UPDATE luna_target_actions SET is_active = :is_active, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $statement->execute(['id' => $id, 'is_active' => $active ? 1 : 0]);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo()->prepare('DELETE FROM luna_target_actions WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public static function normalizeKey(string $value): string
    {
        $key = strtolower(trim($value));
        $key = preg_replace('/[^a-z0-9_\\-]+/', '_', $key) ?? '';
        $key = trim($key, '_-');

        return $key;
    }

    private function payload(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $keySource = trim((string) ($data['action_key'] ?? ''));
        $type = (string) ($data['action_type'] ?? 'http_get');

        return [
            'workspace_id' => (int) ($data['workspace_id'] ?? 0),
            'name' => $name,
            'action_key' => self::normalizeKey($keySource === '' ? $name : $keySource),
            'action_type' => in_array($type, self::ALL_TYPES, true) ? $type : 'http_get',
            'is_active' => array_key_exists('is_active', $data) ? (! empty($data['is_active']) ? 1 : 0) : 1,
            'config_json' => trim((string) ($data['config_json'] ?? '')) ?: null,
        ];
    }

    private function pdo(): PDO
    {
        return $this->pdo ?? $this->database->pdo();
    }
}
