<?php

declare(strict_types=1);

namespace Luna\Repository;

use Luna\Connections\ConnectionProfileData;
use Luna\Database\SystemDatabase;
use Luna\Security\EncryptionService;
use PDO;
use Throwable;

final class ConnectionProfileRepository
{
    public function __construct(
        private readonly SystemDatabase $database,
        private readonly EncryptionService $encryption,
        private readonly ?PDO $pdo = null,
    ) {
    }

    public function all(): array
    {
        $statement = $this->pdo()->query(
            'SELECT cp.*, w.name AS workspace_name
             FROM luna_connection_profiles cp
             LEFT JOIN luna_workspaces w ON w.id = cp.workspace_id
             ORDER BY cp.name',
        );

        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT cp.*, w.name AS workspace_name
             FROM luna_connection_profiles cp
             LEFT JOIN luna_workspaces w ON w.id = cp.workspace_id
             WHERE cp.id = :id',
        );
        $statement->execute(['id' => $id]);
        $profile = $statement->fetch();

        return $profile === false ? null : $profile;
    }

    public function create(array $data, array $secrets): int
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $statement = $pdo->prepare(
                'INSERT INTO luna_connection_profiles
                (workspace_id, name, type, driver, host, port, database_name, username, read_only, is_active, config_json, notes, created_at, updated_at)
                VALUES
                (:workspace_id, :name, :type, :driver, :host, :port, :database_name, :username, :read_only, :is_active, :config_json, :notes, NOW(), NOW())',
            );
            $statement->execute($this->profilePayload($data));
            $id = (int) $pdo->lastInsertId();
            $this->storeSecrets($id, $secrets);
            $pdo->commit();

            return $id;
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function update(int $id, array $data, array $secrets = []): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $payload = $this->profilePayload($data);
            $payload['id'] = $id;
            $statement = $pdo->prepare(
                'UPDATE luna_connection_profiles
                 SET workspace_id = :workspace_id, name = :name, type = :type, driver = :driver,
                     host = :host, port = :port, database_name = :database_name, username = :username,
                     read_only = :read_only, is_active = :is_active, config_json = :config_json,
                     notes = :notes, updated_at = NOW()
                 WHERE id = :id',
            );
            $statement->execute($payload);
            $this->storeSecrets($id, $secrets);
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function delete(int $id): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $statement = $pdo->prepare('DELETE FROM luna_connection_secrets WHERE connection_profile_id = :id');
            $statement->execute(['id' => $id]);
            $statement = $pdo->prepare('DELETE FROM luna_connection_profiles WHERE id = :id');
            $statement->execute(['id' => $id]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function canDelete(int $id): DeleteCheckResult
    {
        $mappingNames = $this->mappingNamesForConnection($id);
        $schemaCount = $this->countByConnection('luna_schema_snapshots', $id)
            + $this->countByConnection('luna_table_notes', $id)
            + $this->countByConnection('luna_column_notes', $id);

        if ($mappingNames !== []) {
            return DeleteCheckResult::blocked(
                'Diese Connection kann nicht gelöscht werden, weil sie noch von Mapping(s) verwendet wird.',
                $mappingNames,
                ['mappings' => count($mappingNames), 'schema' => $schemaCount],
            );
        }

        if ($schemaCount > 0) {
            return DeleteCheckResult::blocked(
                'Diese Connection kann nicht gelöscht werden, weil noch Schema-/Explorer-Einträge vorhanden sind.',
                [],
                ['schema' => $schemaCount],
            );
        }

        return DeleteCheckResult::allowed();
    }

    public function secretsFor(int $connectionProfileId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT secret_key, secret_value_encrypted FROM luna_connection_secrets WHERE connection_profile_id = :id',
        );
        $statement->execute(['id' => $connectionProfileId]);
        $secrets = [];

        foreach ($statement->fetchAll() as $row) {
            $secrets[$row['secret_key']] = $this->encryption->decrypt($row['secret_value_encrypted']);
        }

        return $secrets;
    }

    private function profilePayload(array $data): array
    {
        $normalized = ConnectionProfileData::normalize($data);

        return [
            'workspace_id' => $normalized['workspace_id'],
            'name' => $normalized['name'],
            'type' => $normalized['type'],
            'driver' => $normalized['driver'],
            'host' => $normalized['host'],
            'port' => $normalized['port'],
            'database_name' => $normalized['database_name'],
            'username' => $normalized['username'],
            'read_only' => $normalized['read_only'],
            'is_active' => $normalized['is_active'],
            'config_json' => json_encode(['charset' => $normalized['charset']], JSON_UNESCAPED_SLASHES),
            'notes' => $normalized['notes'],
        ];
    }

    private function storeSecrets(int $connectionProfileId, array $secrets): void
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_connection_secrets
             (connection_profile_id, secret_key, secret_value_encrypted, encryption_version, created_at, updated_at)
             VALUES (:connection_profile_id, :secret_key, :secret_value_encrypted, :encryption_version, NOW(), NOW())
             ON DUPLICATE KEY UPDATE secret_value_encrypted = VALUES(secret_value_encrypted), encryption_version = VALUES(encryption_version), updated_at = NOW()',
        );

        foreach ($secrets as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $statement->execute([
                'connection_profile_id' => $connectionProfileId,
                'secret_key' => (string) $key,
                'secret_value_encrypted' => $this->encryption->encrypt((string) $value),
                'encryption_version' => 'v1',
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function mappingNamesForConnection(int $connectionId): array
    {
        $names = [];

        $conditions = [];
        if ($this->columnExists('luna_mapping_sets', 'source_connection_id')) {
            $conditions[] = 'source_connection_id = :source_connection_id';
        }
        if ($this->columnExists('luna_mapping_sets', 'target_connection_id')) {
            $conditions[] = 'target_connection_id = :target_connection_id';
        }

        if ($conditions !== []) {
            $statement = $this->pdo()->prepare(sprintf(
                'SELECT name FROM luna_mapping_sets WHERE %s ORDER BY name',
                implode(' OR ', $conditions),
            ));
            $statement->execute(['source_connection_id' => $connectionId, 'target_connection_id' => $connectionId]);
            foreach ($statement->fetchAll() as $row) {
                $names[] = (string) $row['name'];
            }
        }

        if ($this->columnExists('luna_mapping_fields', 'lookup_connection_id')) {
            $statement = $this->pdo()->prepare(
                'SELECT DISTINCT ms.name
                 FROM luna_mapping_sets ms
                 INNER JOIN luna_mapping_fields mf ON mf.mapping_set_id = ms.id
                 WHERE mf.lookup_connection_id = :id
                 ORDER BY ms.name',
            );
            $statement->execute(['id' => $connectionId]);
            foreach ($statement->fetchAll() as $row) {
                $names[] = (string) $row['name'];
            }
        }

        return array_values(array_unique($names));
    }

    private function countByConnection(string $table, int $connectionId): int
    {
        if (! $this->columnExists($table, 'connection_profile_id')) {
            return 0;
        }

        $statement = $this->pdo()->prepare(sprintf('SELECT COUNT(*) FROM %s WHERE connection_profile_id = :id', $table));
        $statement->execute(['id' => $connectionId]);

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
