<?php

declare(strict_types=1);

namespace Luna\Repository;

use Luna\Database\SystemDatabase;
use PDO;

final class WorkspaceRepository
{
    public function __construct(
        private readonly SystemDatabase $database,
        private readonly ?PDO $pdo = null,
    ) {
    }

    public function all(): array
    {
        $statement = $this->pdo()->query('SELECT * FROM luna_workspaces ORDER BY name');

        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM luna_workspaces WHERE id = :id');
        $statement->execute(['id' => $id]);
        $workspace = $statement->fetch();

        return $workspace === false ? null : $workspace;
    }

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
        $slug = self::normalizeSlug($slug !== '' ? $slug : $name);
        $payload = [
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'status' => 'active',
        ];
        if ($this->columnExists('luna_workspaces', 'transfer_db_connection_id')) {
            $payload['transfer_db_connection_id'] = $transferDbConnectionId;
            $column = ', transfer_db_connection_id';
            $placeholder = ', :transfer_db_connection_id';
        } else {
            $column = '';
            $placeholder = '';
        }
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_workspaces (slug, name, description, status' . $column . ', created_at, updated_at)
             VALUES (:slug, :name, :description, :status' . $placeholder . ', NOW(), NOW())',
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
            $payload['transfer_db_connection_id'] = $transferDbConnectionId;
            $transferDbSet = 'transfer_db_connection_id = :transfer_db_connection_id,';
        }
        $statement = $this->pdo()->prepare(
            'UPDATE luna_workspaces
             SET slug = :slug,
                 name = :name,
                 description = :description,
                 status = :status,
                 ' . $transferDbSet . '
                 updated_at = NOW()
             WHERE id = :id',
        );
        $statement->execute($payload);
    }

    public function setTransferDbConnection(int $id, ?int $connectionId): void
    {
        if (! $this->columnExists('luna_workspaces', 'transfer_db_connection_id')) {
            return;
        }

        $statement = $this->pdo()->prepare(
            'UPDATE luna_workspaces SET transfer_db_connection_id = :connection_id, updated_at = NOW() WHERE id = :id',
        );
        $statement->execute([
            'id' => $id,
            'connection_id' => $connectionId,
        ]);
    }

    public function canDelete(int $id): DeleteCheckResult
    {
        $counts = [
            'connections' => $this->countByWorkspace('luna_connection_profiles', $id),
            'mappings' => $this->countByWorkspace('luna_mapping_sets', $id),
            'endpoints' => $this->countByWorkspace('luna_endpoints', $id),
        ];

        if (array_sum($counts) > 0) {
            return DeleteCheckResult::blocked(
                'Dieser Workspace kann nicht gelöscht werden, weil noch Connections, Mappings oder Endpoints vorhanden sind.',
                [],
                $counts,
            );
        }

        return DeleteCheckResult::allowed();
    }

    public function delete(int $id): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            if ($this->tableExists('luna_connection_workspaces')) {
                $statement = $pdo->prepare('DELETE FROM luna_connection_workspaces WHERE workspace_id = :id');
                $statement->execute(['id' => $id]);
            }

            $statement = $pdo->prepare('DELETE FROM luna_workspaces WHERE id = :id');
            $statement->execute(['id' => $id]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
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
        $statement = $this->pdo()->prepare(sprintf('SELECT COUNT(*) FROM %s WHERE workspace_id = :workspace_id', $table));
        $statement->execute(['workspace_id' => $workspaceId]);

        return (int) $statement->fetchColumn();
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

    private function pdo(): PDO
    {
        return $this->pdo ?? $this->database->pdo();
    }
}
