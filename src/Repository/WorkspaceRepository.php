<?php

declare(strict_types=1);

namespace Luna\Repository;

use Luna\Database\SystemDatabase;

final class WorkspaceRepository
{
    public function __construct(
        private readonly SystemDatabase $database,
    ) {
    }

    public function all(): array
    {
        $statement = $this->database->pdo()->query('SELECT * FROM luna_workspaces ORDER BY name');

        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->database->pdo()->prepare('SELECT * FROM luna_workspaces WHERE id = :id');
        $statement->execute(['id' => $id]);
        $workspace = $statement->fetch();

        return $workspace === false ? null : $workspace;
    }

    public function create(string $slug, string $name, ?string $description = null): int
    {
        $slug = self::normalizeSlug($slug !== '' ? $slug : $name);
        $statement = $this->database->pdo()->prepare(
            'INSERT INTO luna_workspaces (slug, name, description, status, created_at, updated_at)
             VALUES (:slug, :name, :description, :status, NOW(), NOW())',
        );
        $statement->execute([
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'status' => 'active',
        ]);

        return (int) $this->database->pdo()->lastInsertId();
    }

    public function update(int $id, string $slug, string $name, ?string $description, string $status): void
    {
        $statement = $this->database->pdo()->prepare(
            'UPDATE luna_workspaces
             SET slug = :slug, name = :name, description = :description, status = :status, updated_at = NOW()
             WHERE id = :id',
        );
        $statement->execute([
            'id' => $id,
            'slug' => self::normalizeSlug($slug !== '' ? $slug : $name),
            'name' => trim($name),
            'description' => $description === null ? null : trim($description),
            'status' => $status,
        ]);
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

        $statement = $this->database->pdo()->prepare($sql);
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
}
