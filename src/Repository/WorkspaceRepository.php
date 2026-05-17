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
}
