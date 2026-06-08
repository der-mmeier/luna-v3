<?php

declare(strict_types=1);

namespace Luna\Repository;

use Luna\Database\SystemDatabase;
use Luna\Security\EncryptionService;
use PDO;

final class EndpointRepository
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
            'SELECT e.*, w.name AS workspace_name, w.slug AS workspace_slug, ms.name AS mapping_name, j.name AS job_name
             FROM luna_endpoints e
             LEFT JOIN luna_workspaces w ON w.id = e.workspace_id
             LEFT JOIN luna_mapping_sets ms ON ms.id = e.mapping_set_id
             LEFT JOIN luna_jobs j ON j.id = e.job_id
             ORDER BY e.updated_at DESC, e.name',
        );

        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT e.*, w.name AS workspace_name, w.slug AS workspace_slug, ms.name AS mapping_name, j.name AS job_name
             FROM luna_endpoints e
             LEFT JOIN luna_workspaces w ON w.id = e.workspace_id
             LEFT JOIN luna_mapping_sets ms ON ms.id = e.mapping_set_id
             LEFT JOIN luna_jobs j ON j.id = e.job_id
             WHERE e.id = :id',
        );
        $statement->execute(['id' => $id]);
        $endpoint = $statement->fetch();

        return $endpoint === false ? null : $endpoint;
    }

    public function findByKey(string $endpointKey): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT * FROM luna_endpoints WHERE endpoint_key = :endpoint_key',
        );
        $statement->execute(['endpoint_key' => self::normalizeEndpointKey($endpointKey)]);
        $endpoint = $statement->fetch();

        return $endpoint === false ? null : $endpoint;
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->findByKey($slug);
    }

    public function listByWorkspace(int $workspaceId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT e.*, ms.name AS mapping_name
             FROM luna_endpoints e
             LEFT JOIN luna_mapping_sets ms ON ms.id = e.mapping_set_id
             WHERE e.workspace_id = :workspace_id
             ORDER BY e.updated_at DESC, e.name',
        );
        $statement->execute(['workspace_id' => $workspaceId]);

        return $statement->fetchAll();
    }

    public function create(array $data, array $secrets = []): int
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $payload = $this->payload($data);
            $columns = [
                'workspace_id',
                'name',
                'endpoint_key',
                'description',
                'method',
                'visibility',
                'status',
                'secret_mode',
                'response_type',
                'source_type',
                'mapping_set_id',
                'job_id',
                'config_json',
                'cache_enabled',
                'cache_ttl_seconds',
                'rate_limit_per_minute',
                'notes',
            ];
            if ($this->hasColumn('luna_endpoints', 'schema_id')) {
                $columns[] = 'schema_id';
            } else {
                unset($payload['schema_id']);
            }

            $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
            $statement = $pdo->prepare(
                'INSERT INTO luna_endpoints
                 (' . implode(', ', $columns) . ', created_at, updated_at)
                 VALUES
                 (' . implode(', ', $placeholders) . ', NOW(), NOW())',
            );
            $statement->execute($payload);
            $id = (int) $pdo->lastInsertId();
            $this->storeSecrets($id, $secrets);
            $this->storeSecretHash($id, $secrets);
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
            $payload = $this->payload($data);
            $payload['id'] = $id;
            $schemaSet = '';
            if (! $this->hasColumn('luna_endpoints', 'schema_id')) {
                unset($payload['schema_id']);
            } else {
                $schemaSet = 'schema_id = :schema_id,';
            }
            $statement = $pdo->prepare(
                'UPDATE luna_endpoints
                 SET workspace_id = :workspace_id, name = :name, endpoint_key = :endpoint_key, description = :description,
                     method = :method, visibility = :visibility, status = :status, response_type = :response_type,
                     secret_mode = :secret_mode, source_type = :source_type, mapping_set_id = :mapping_set_id, job_id = :job_id,
                     ' . $schemaSet . '
                     config_json = :config_json, cache_enabled = :cache_enabled, cache_ttl_seconds = :cache_ttl_seconds, rate_limit_per_minute = :rate_limit_per_minute,
                     notes = :notes, updated_at = NOW()
                 WHERE id = :id',
            );
            $statement->execute($payload);
            $this->storeSecrets($id, $secrets);
            $this->storeSecretHash($id, $secrets);
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
            $statement = $pdo->prepare('DELETE FROM luna_endpoint_secrets WHERE endpoint_id = :id');
            $statement->execute(['id' => $id]);
            $statement = $pdo->prepare('DELETE FROM luna_endpoints WHERE id = :id');
            $statement->execute(['id' => $id]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function canDelete(int $id): DeleteCheckResult
    {
        return DeleteCheckResult::allowed();
    }

    public function updateStatus(int $id, string $status): void
    {
        $status = in_array($status, ['active', 'inactive', 'draft', 'disabled'], true) ? $status : 'inactive';
        $statement = $this->pdo()->prepare('UPDATE luna_endpoints SET status = :status, updated_at = NOW() WHERE id = :id');
        $statement->execute(['id' => $id, 'status' => $status]);
    }

    public function secretsFor(int $endpointId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT secret_key, secret_value_encrypted FROM luna_endpoint_secrets WHERE endpoint_id = :id',
        );
        $statement->execute(['id' => $endpointId]);
        $secrets = [];

        foreach ($statement->fetchAll() as $row) {
            $secrets[$row['secret_key']] = $this->encryption->decrypt($row['secret_value_encrypted']);
        }

        return $secrets;
    }

    public function hasSecret(int $endpointId, string $secretKey = 'secret'): bool
    {
        $statement = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM luna_endpoint_secrets WHERE endpoint_id = :endpoint_id AND secret_key = :secret_key',
        );
        $statement->execute(['endpoint_id' => $endpointId, 'secret_key' => $secretKey]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function verifySecret(int $endpointId, string $providedSecret, string $secretKey = 'secret'): bool
    {
        if ($providedSecret === '') {
            return false;
        }

        $statement = $this->pdo()->prepare('SELECT secret_hash FROM luna_endpoints WHERE id = :id');
        $statement->execute(['id' => $endpointId]);
        $hash = $statement->fetchColumn();

        if (is_string($hash) && $hash !== '') {
            return password_verify($providedSecret, $hash);
        }

        $secrets = $this->secretsFor($endpointId);
        $expected = $secrets[$secretKey] ?? null;

        return is_string($expected) && hash_equals($expected, $providedSecret);
    }

    public static function normalizeEndpointKey(string $endpointKey): string
    {
        $key = strtolower(trim($endpointKey));
        $key = trim($key, '/');
        $key = preg_replace('#[^a-z0-9_\-/]+#', '-', $key) ?? '';
        $key = preg_replace('#/{2,}#', '/', $key) ?? '';

        return trim($key, '/');
    }

    private function payload(array $data): array
    {
        return [
            'workspace_id' => empty($data['workspace_id']) ? null : (int) $data['workspace_id'],
            'name' => trim((string) ($data['name'] ?? '')),
            'endpoint_key' => self::normalizeEndpointKey((string) ($data['endpoint_key'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'method' => strtoupper(trim((string) ($data['method'] ?? 'GET'))) ?: 'GET',
            'visibility' => trim((string) ($data['visibility'] ?? 'private')) ?: 'private',
            'status' => trim((string) ($data['status'] ?? 'draft')) ?: 'draft',
            'secret_mode' => in_array((string) ($data['secret_mode'] ?? 'none'), ['none', 'optional', 'required'], true)
                ? (string) ($data['secret_mode'] ?? 'none')
                : 'none',
            'response_type' => trim((string) ($data['response_type'] ?? 'json')) ?: 'json',
            'source_type' => trim((string) ($data['source_type'] ?? 'static')) ?: 'static',
            'mapping_set_id' => empty($data['mapping_set_id']) ? null : (int) $data['mapping_set_id'],
            'job_id' => empty($data['job_id']) ? null : (int) $data['job_id'],
            'schema_id' => empty($data['schema_id']) ? null : (int) $data['schema_id'],
            'config_json' => trim((string) ($data['config_json'] ?? '')) ?: null,
            'cache_enabled' => ! empty($data['cache_enabled']) ? 1 : 0,
            'cache_ttl_seconds' => empty($data['cache_ttl_seconds']) ? null : max(1, min((int) $data['cache_ttl_seconds'], 86400)),
            'rate_limit_per_minute' => empty($data['rate_limit_per_minute']) ? null : (int) $data['rate_limit_per_minute'],
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ];
    }

    private function storeSecretHash(int $endpointId, array $secrets): void
    {
        $secret = $secrets['secret'] ?? null;
        if ($secret === null || $secret === '') {
            return;
        }

        $statement = $this->pdo()->prepare('UPDATE luna_endpoints SET secret_hash = :secret_hash, updated_at = NOW() WHERE id = :id');
        $statement->execute([
            'id' => $endpointId,
            'secret_hash' => password_hash((string) $secret, PASSWORD_DEFAULT),
        ]);
    }

    private function storeSecrets(int $endpointId, array $secrets): void
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_endpoint_secrets
             (endpoint_id, secret_key, secret_value_encrypted, encryption_version, created_at, updated_at)
             VALUES (:endpoint_id, :secret_key, :secret_value_encrypted, :encryption_version, NOW(), NOW())
             ON DUPLICATE KEY UPDATE secret_value_encrypted = VALUES(secret_value_encrypted), encryption_version = VALUES(encryption_version), updated_at = NOW()',
        );

        foreach ($secrets as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $statement->execute([
                'endpoint_id' => $endpointId,
                'secret_key' => (string) $key,
                'secret_value_encrypted' => $this->encryption->encrypt((string) $value),
                'encryption_version' => 'v1',
            ]);
        }
    }

    private function pdo(): PDO
    {
        return $this->pdo ?? $this->database->pdo();
    }

    private function hasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $pdo = $this->pdo();
        $key = spl_object_id($pdo) . '.' . $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $statement = $pdo->query('PRAGMA table_info(' . $table . ')');
            $columns = $statement === false ? [] : $statement->fetchAll();
            foreach ($columns as $row) {
                if ((string) ($row['name'] ?? '') === $column) {
                    return $cache[$key] = true;
                }
            }

            return $cache[$key] = false;
        }

        $statement = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name',
        );
        $statement->execute(['table_name' => $table, 'column_name' => $column]);

        return $cache[$key] = (int) $statement->fetchColumn() > 0;
    }
}

