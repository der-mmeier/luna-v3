<?php

declare(strict_types=1);

namespace Luna\Repository;

use Luna\Database\SystemDatabase;
use PDO;

final class SchemaRegistryRepository
{
    public const STATUSES = ['draft', 'active', 'deprecated'];

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
        $statement = $this->pdo()->query(
            'SELECT s.*, w.name AS workspace_name
             FROM luna_schemas s
             INNER JOIN luna_workspaces w ON w.id = s.workspace_id
             ORDER BY s.updated_at DESC, s.schema_key, s.version',
        );

        return $statement->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forWorkspace(?int $workspaceId): array
    {
        if ($workspaceId === null || $workspaceId <= 0) {
            return $this->all();
        }

        $statement = $this->pdo()->prepare(
            'SELECT s.*, w.name AS workspace_name
             FROM luna_schemas s
             INNER JOIN luna_workspaces w ON w.id = s.workspace_id
             WHERE s.workspace_id = :workspace_id
             ORDER BY s.schema_key, s.version',
        );
        $statement->execute(['workspace_id' => $workspaceId]);

        return $statement->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeForWorkspace(?int $workspaceId): array
    {
        $schemas = $this->forWorkspace($workspaceId);

        return array_values(array_filter(
            $schemas,
            static fn (array $schema): bool => (string) ($schema['status'] ?? '') === 'active',
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT s.*, w.name AS workspace_name
             FROM luna_schemas s
             INNER JOIN luna_workspaces w ON w.id = s.workspace_id
             WHERE s.id = :id',
        );
        $statement->execute(['id' => $id]);
        $schema = $statement->fetch();

        return $schema === false ? null : $schema;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByKey(int $workspaceId, string $schemaKey, string $version): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT s.*, w.name AS workspace_name
             FROM luna_schemas s
             INNER JOIN luna_workspaces w ON w.id = s.workspace_id
             WHERE s.workspace_id = :workspace_id AND s.schema_key = :schema_key AND s.version = :version',
        );
        $statement->execute([
            'workspace_id' => $workspaceId,
            'schema_key' => self::normalizeKey($schemaKey),
            'version' => trim($version),
        ]);
        $schema = $statement->fetch();

        return $schema === false ? null : $schema;
    }

    public function create(array $data): int
    {
        $payload = $this->payload($data);
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_schemas
             (workspace_id, schema_key, version, name, description, definition_json, example_json, status, created_at, updated_at)
             VALUES (:workspace_id, :schema_key, :version, :name, :description, :definition_json, :example_json, :status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
        );
        $statement->execute($payload);
        $id = (int) $this->pdo()->lastInsertId();
        $this->addRevision($id, $payload['version'], $payload['definition_json'], $payload['example_json'], (string) ($data['change_summary'] ?? 'Initiale Version'));

        return $id;
    }

    public function update(int $id, array $data): void
    {
        $current = $this->find($id);
        if ($current === null) {
            return;
        }

        $payload = $this->payload($data);
        $payload['id'] = $id;
        $statement = $this->pdo()->prepare(
            'UPDATE luna_schemas
             SET workspace_id = :workspace_id,
                 schema_key = :schema_key,
                 version = :version,
                 name = :name,
                 description = :description,
                 definition_json = :definition_json,
                 example_json = :example_json,
                 status = :status,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
        );
        $statement->execute($payload);

        if (
            (string) ($current['definition_json'] ?? '') !== $payload['definition_json']
            || (string) ($current['example_json'] ?? '') !== (string) $payload['example_json']
            || (string) ($current['version'] ?? '') !== $payload['version']
        ) {
            $this->addRevision($id, $payload['version'], $payload['definition_json'], $payload['example_json'], (string) ($data['change_summary'] ?? 'Schema geändert'));
        }
    }

    public function updateStatus(int $id, string $status): void
    {
        $status = in_array($status, self::STATUSES, true) ? $status : 'draft';
        $statement = $this->pdo()->prepare('UPDATE luna_schemas SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $statement->execute(['id' => $id, 'status' => $status]);
    }

    public function delete(int $id): void
    {
        if ($this->hasColumn('luna_endpoints', 'schema_id')) {
            $statement = $this->pdo()->prepare('SELECT name, endpoint_key FROM luna_endpoints WHERE schema_id = :id ORDER BY name LIMIT 10');
            $statement->execute(['id' => $id]);
            $endpoints = $statement->fetchAll();
            if ($endpoints !== []) {
                $names = array_map(
                    static fn (array $endpoint): string => trim((string) ($endpoint['name'] ?? '')) ?: (string) ($endpoint['endpoint_key'] ?? 'Endpoint'),
                    $endpoints,
                );
                throw new \RuntimeException('Schema kann nicht gelöscht werden, weil ' . count($endpoints) . ' Endpoint(s) dieses Schema referenzieren: ' . implode(', ', $names) . '. Bitte entfernen Sie die Schema-Zuordnung zuerst.');
            }
        }

        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare('DELETE FROM luna_schema_revisions WHERE schema_id = :id');
            $statement->execute(['id' => $id]);
            $statement = $pdo->prepare('DELETE FROM luna_schemas WHERE id = :id');
            $statement->execute(['id' => $id]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function revisionsForSchema(int $schemaId): array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM luna_schema_revisions WHERE schema_id = :schema_id ORDER BY id DESC');
        $statement->execute(['schema_id' => $schemaId]);

        return $statement->fetchAll();
    }

    public function existsVersion(int $workspaceId, string $schemaKey, string $version, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM luna_schemas WHERE workspace_id = :workspace_id AND schema_key = :schema_key AND version = :version';
        $params = [
            'workspace_id' => $workspaceId,
            'schema_key' => self::normalizeKey($schemaKey),
            'version' => trim($version),
        ];
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

    private function addRevision(int $schemaId, string $version, string $definitionJson, ?string $exampleJson, string $changeSummary): void
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_schema_revisions
             (schema_id, version, definition_json, example_json, change_summary, created_at)
             VALUES (:schema_id, :version, :definition_json, :example_json, :change_summary, CURRENT_TIMESTAMP)',
        );
        $statement->execute([
            'schema_id' => $schemaId,
            'version' => $version,
            'definition_json' => $definitionJson,
            'example_json' => $exampleJson,
            'change_summary' => trim($changeSummary) ?: null,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $keySource = trim((string) ($data['schema_key'] ?? ''));
        $status = (string) ($data['status'] ?? 'draft');

        return [
            'workspace_id' => (int) ($data['workspace_id'] ?? 0),
            'schema_key' => self::normalizeKey($keySource === '' ? $name : $keySource),
            'version' => trim((string) ($data['version'] ?? '1')),
            'name' => $name,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'definition_json' => trim((string) ($data['definition_json'] ?? '')),
            'example_json' => trim((string) ($data['example_json'] ?? '')) ?: null,
            'status' => in_array($status, self::STATUSES, true) ? $status : 'draft',
        ];
    }

    private function pdo(): PDO
    {
        return $this->pdo ?? $this->database->pdo();
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            $statement = $this->pdo()->prepare('SELECT ' . $column . ' FROM ' . $table . ' LIMIT 1');
            $statement->execute();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
