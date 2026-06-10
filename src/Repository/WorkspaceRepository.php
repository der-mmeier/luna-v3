<?php

declare(strict_types=1);

namespace Luna\Repository;

use Luna\Database\SystemDatabase;
use PDO;
use Throwable;

final class WorkspaceRepository
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
        $statement = $this->pdo()->query('SELECT * FROM luna_workspaces ORDER BY name');

        return $statement->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM luna_workspaces WHERE id = :id');
        $statement->execute(['id' => $id]);
        $workspace = $statement->fetch();

        return $workspace === false ? null : $workspace;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByIdentifier(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        if (ctype_digit($identifier)) {
            return $this->find((int) $identifier);
        }

        $statement = $this->pdo()->prepare('SELECT * FROM luna_workspaces WHERE slug = :slug');
        $statement->execute(['slug' => self::normalizeSlug($identifier)]);
        $workspace = $statement->fetch();

        return $workspace === false ? null : $workspace;
    }

    public function create(string $slug, string $name, ?string $description = null, ?int $transferDbConnectionId = null): int
    {
        $columns = ['slug', 'name', 'description', 'status'];
        $payload = [
            'slug' => self::normalizeSlug($slug !== '' ? $slug : $name),
            'name' => $name,
            'description' => $description,
            'status' => 'active',
        ];
        if ($this->columnExists('luna_workspaces', 'transfer_db_connection_id')) {
            $columns[] = 'transfer_db_connection_id';
            $payload['transfer_db_connection_id'] = $transferDbConnectionId;
        }

        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_workspaces (' . implode(', ', $columns) . ', created_at, updated_at)
             VALUES (' . implode(', ', $placeholders) . ', NOW(), NOW())',
        );
        $statement->execute($payload);

        return (int) $this->pdo()->lastInsertId();
    }

    public function update(int $id, string $slug, string $name, ?string $description, string $status, ?int $transferDbConnectionId = null): void
    {
        $payload = [
            'id' => $id,
            'slug' => self::normalizeSlug($slug !== '' ? $slug : $name),
            'name' => trim($name),
            'description' => $description === null ? null : trim($description),
            'status' => $status,
        ];
        $transferDbSet = '';
        if ($this->columnExists('luna_workspaces', 'transfer_db_connection_id')) {
            $transferDbSet = ', transfer_db_connection_id = :transfer_db_connection_id';
            $payload['transfer_db_connection_id'] = $transferDbConnectionId;
        }

        $statement = $this->pdo()->prepare(
            'UPDATE luna_workspaces
             SET slug = :slug, name = :name, description = :description, status = :status' . $transferDbSet . ', updated_at = NOW()
             WHERE id = :id',
        );
        $statement->execute($payload);
    }

    public function canDelete(int $id): DeleteCheckResult
    {
        $counts = [
            'connections' => $this->countByWorkspace('luna_connection_profiles', $id),
            'shared_connections' => $this->countSharedConnections($id),
            'mappings' => $this->countByWorkspace('luna_mapping_sets', $id),
            'endpoints' => $this->countByWorkspace('luna_endpoints', $id),
            'processes' => $this->countByWorkspace('luna_processes', $id),
            'schemas' => $this->countByWorkspace('luna_schemas', $id),
            'jobs' => $this->countByWorkspace('luna_jobs', $id),
        ];

        if (array_sum($counts) > 0) {
            return DeleteCheckResult::blocked(
                'Dieser Workspace kann nicht gelöscht werden, weil abhängige Ressourcen existieren. Bitte löschen oder verschieben Sie diese Ressourcen zuerst.',
                [],
                $counts,
            );
        }

        return DeleteCheckResult::allowed();
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo()->prepare('DELETE FROM luna_workspaces WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $slug = self::normalizeSlug($slug);
        $sql = 'SELECT COUNT(*) FROM luna_workspaces WHERE slug = :slug';
        $params = ['slug' => $slug];

        if ($ignoreId !== null) {
            $sql .= ' AND id != :ignore_id';
            $params['ignore_id'] = $ignoreId;
        }

        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    public static function normalizeSlug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug;
    }

    private function countByWorkspace(string $table, int $workspaceId): int
    {
        if (! $this->columnExists($table, 'workspace_id')) {
            return 0;
        }

        $statement = $this->pdo()->prepare(sprintf('SELECT COUNT(*) FROM %s WHERE workspace_id = :workspace_id', $table));
        $statement->execute(['workspace_id' => $workspaceId]);

        return (int) $statement->fetchColumn();
    }

    private function countSharedConnections(int $workspaceId): int
    {
        if (! $this->columnExists('luna_connection_workspaces', 'workspace_id')) {
            return 0;
        }

        $statement = $this->pdo()->prepare('SELECT COUNT(*) FROM luna_connection_workspaces WHERE workspace_id = :workspace_id');
        $statement->execute(['workspace_id' => $workspaceId]);

        return (int) $statement->fetchColumn();
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            $this->pdo()->query(sprintf('SELECT %s FROM %s WHERE 1 = 0', $column, $table));

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function pdo(): PDO
    {
        return $this->pdo ?? $this->database->pdo();
    }
}