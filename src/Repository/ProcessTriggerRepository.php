<?php

declare(strict_types=1);

namespace Luna\Repository;

use Luna\Database\SystemDatabase;
use Luna\Security\EncryptionService;
use PDO;

final class ProcessTriggerRepository
{
    public const TYPES = ['manual', 'cli', 'api', 'schedule', 'webhook'];

    public function __construct(
        private readonly SystemDatabase $database,
        private readonly ?PDO $pdo = null,
        private readonly ?EncryptionService $encryption = null,
    ) {
    }

    public function all(): array
    {
        $statement = $this->pdo()->query(
            'SELECT pt.*, p.name AS process_name, w.name AS workspace_name
             FROM luna_process_triggers pt
             INNER JOIN luna_processes p ON p.id = pt.process_id
             LEFT JOIN luna_workspaces w ON w.id = pt.workspace_id
             ORDER BY pt.updated_at DESC, pt.name',
        );

        return $statement->fetchAll();
    }

    public function forProcess(int $processId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT * FROM luna_process_triggers WHERE process_id = :process_id ORDER BY trigger_type, name, id',
        );
        $statement->execute(['process_id' => $processId]);

        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM luna_process_triggers WHERE id = :id');
        $statement->execute(['id' => $id]);
        $trigger = $statement->fetch();

        return $trigger === false ? null : $trigger;
    }

    public function findByKey(string $triggerKey): ?array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM luna_process_triggers WHERE trigger_key = :trigger_key');
        $statement->execute(['trigger_key' => self::normalizeKey($triggerKey)]);
        $trigger = $statement->fetch();

        return $trigger === false ? null : $trigger;
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

        return $this->findByKey($identifier);
    }

    public function create(array $data, ?string $secret = null): int
    {
        $payload = $this->payload($data);
        $payload['secret_hash'] = $this->hashSecret($secret);
        if ($this->hasColumn('luna_process_triggers', 'secret_encrypted')) {
            $payload['secret_encrypted'] = $this->encryptSecret($secret);
            $secretColumn = ', secret_encrypted';
            $secretPlaceholder = ', :secret_encrypted';
        } else {
            $secretColumn = '';
            $secretPlaceholder = '';
        }
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_process_triggers
             (process_id, workspace_id, name, trigger_type, trigger_key, is_active, config_json, secret_hash' . $secretColumn . ', created_at, updated_at)
             VALUES
             (:process_id, :workspace_id, :name, :trigger_type, :trigger_key, :is_active, :config_json, :secret_hash' . $secretPlaceholder . ', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
        );
        $statement->execute($payload);

        return (int) $this->pdo()->lastInsertId();
    }

    public function update(int $id, array $data, ?string $secret = null, bool $replaceSecret = false): void
    {
        $payload = $this->payload($data);
        $payload['id'] = $id;

        if ($replaceSecret) {
            $payload['secret_hash'] = $this->hashSecret($secret);
            $secretEncryptedSet = '';
            if ($this->hasColumn('luna_process_triggers', 'secret_encrypted')) {
                $payload['secret_encrypted'] = $this->encryptSecret($secret);
                $secretEncryptedSet = 'secret_encrypted = :secret_encrypted,';
            }
            $sql = 'UPDATE luna_process_triggers
                    SET process_id = :process_id,
                        workspace_id = :workspace_id,
                        name = :name,
                        trigger_type = :trigger_type,
                        trigger_key = :trigger_key,
                        is_active = :is_active,
                        config_json = :config_json,
                        secret_hash = :secret_hash,
                        ' . $secretEncryptedSet . '
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id';
        } else {
            $sql = 'UPDATE luna_process_triggers
                    SET process_id = :process_id,
                        workspace_id = :workspace_id,
                        name = :name,
                        trigger_type = :trigger_type,
                        trigger_key = :trigger_key,
                        is_active = :is_active,
                        config_json = :config_json,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id';
        }

        $statement = $this->pdo()->prepare($sql);
        $statement->execute($payload);
    }

    public function setActive(int $id, bool $active): void
    {
        $statement = $this->pdo()->prepare('UPDATE luna_process_triggers SET is_active = :is_active, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $statement->execute(['id' => $id, 'is_active' => $active ? 1 : 0]);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo()->prepare('DELETE FROM luna_process_triggers WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function markTriggered(int $id): void
    {
        $statement = $this->pdo()->prepare('UPDATE luna_process_triggers SET last_triggered_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function verifySecret(array $trigger, ?string $secret): bool
    {
        $hash = trim((string) ($trigger['secret_hash'] ?? ''));
        if ($hash === '') {
            return true;
        }

        return is_string($secret) && $secret !== '' && password_verify($secret, $hash);
    }

    public function hasSecret(int $id): bool
    {
        $trigger = $this->find($id);

        return $trigger !== null && (
            trim((string) ($trigger['secret_hash'] ?? '')) !== ''
            || trim((string) ($trigger['secret_encrypted'] ?? '')) !== ''
        );
    }

    public function secretForTrigger(array $trigger): ?string
    {
        $encrypted = (string) ($trigger['secret_encrypted'] ?? '');
        if ($encrypted === '' || $this->encryption === null) {
            return null;
        }

        try {
            return $this->encryption->decrypt($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function normalizeKey(string $value): string
    {
        $key = strtolower(trim($value));
        $key = preg_replace('/[^a-z0-9\\-_]+/', '-', $key) ?? '';
        $key = trim($key, '-_');

        return $key;
    }

    private function payload(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $type = (string) ($data['trigger_type'] ?? 'manual');
        $keySource = trim((string) ($data['trigger_key'] ?? ''));
        $key = self::normalizeKey($keySource === '' ? $name : $keySource);

        return [
            'process_id' => (int) ($data['process_id'] ?? 0),
            'workspace_id' => empty($data['workspace_id']) ? null : (int) $data['workspace_id'],
            'name' => $name,
            'trigger_type' => in_array($type, self::TYPES, true) ? $type : 'manual',
            'trigger_key' => $key,
            'is_active' => array_key_exists('is_active', $data) ? (! empty($data['is_active']) ? 1 : 0) : 1,
            'config_json' => trim((string) ($data['config_json'] ?? '')) ?: null,
        ];
    }

    private function hashSecret(?string $secret): ?string
    {
        $secret = trim((string) $secret);
        if ($secret === '') {
            return null;
        }

        return password_hash($secret, PASSWORD_DEFAULT);
    }

    private function encryptSecret(?string $secret): ?string
    {
        $secret = trim((string) $secret);
        if ($secret === '' || $this->encryption === null) {
            return null;
        }

        return $this->encryption->encrypt($secret);
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
