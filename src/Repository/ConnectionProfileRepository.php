<?php

declare(strict_types=1);

namespace Luna\Repository;

use Luna\Connections\ConnectionProfileData;
use Luna\Database\SystemDatabase;
use Luna\Security\EncryptionService;
use PDO;

final class ConnectionProfileRepository
{
    public function __construct(
        private readonly SystemDatabase $database,
        private readonly EncryptionService $encryption,
    ) {
    }

    public function all(): array
    {
        $statement = $this->database->pdo()->query(
            'SELECT cp.*, w.name AS workspace_name
             FROM luna_connection_profiles cp
             LEFT JOIN luna_workspaces w ON w.id = cp.workspace_id
             ORDER BY cp.name',
        );

        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->database->pdo()->prepare(
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
        $pdo = $this->database->pdo();
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
        $pdo = $this->database->pdo();
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
        $statement = $this->database->pdo()->prepare('DELETE FROM luna_connection_profiles WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function secretsFor(int $connectionProfileId): array
    {
        $statement = $this->database->pdo()->prepare(
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
        $statement = $this->database->pdo()->prepare(
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
}
