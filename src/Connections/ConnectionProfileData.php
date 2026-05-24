<?php

declare(strict_types=1);

namespace Luna\Connections;

final class ConnectionProfileData
{
    /**
     * @return list<string>
     */
    public static function roles(): array
    {
        return ['source', 'transfer', 'target'];
    }

    /**
     * @return list<string>
     */
    public static function drivers(): array
    {
        return ['mysql', 'mariadb'];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        $type = strtolower(trim((string) ($data['type'] ?? 'source')));
        $driver = strtolower(trim((string) ($data['driver'] ?? 'mysql')));
        $readOnly = self::readOnlyValue($data, $type);

        return [
            'workspace_id' => empty($data['workspace_id']) ? null : (int) $data['workspace_id'],
            'name' => trim((string) ($data['name'] ?? '')),
            'type' => $type,
            'driver' => $driver,
            'host' => trim((string) ($data['host'] ?? '')),
            'port' => empty($data['port']) ? null : (int) $data['port'],
            'database_name' => trim((string) ($data['database_name'] ?? '')),
            'username' => trim((string) ($data['username'] ?? '')),
            'charset' => trim((string) ($data['charset'] ?? 'utf8mb4')) ?: 'utf8mb4',
            'read_only' => $readOnly,
            'is_active' => ! array_key_exists('is_active', $data) || ! empty($data['is_active']) ? 1 : 0,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ];
    }

    /**
     * @param array<string, mixed> $values
     * @return list<string>
     */
    public static function validate(array $values): array
    {
        $errors = [];

        foreach (['name' => 'Name', 'host' => 'Host', 'database_name' => 'Datenbankname', 'username' => 'Benutzername'] as $key => $label) {
            if (trim((string) ($values[$key] ?? '')) === '') {
                $errors[] = $label . ' ist erforderlich.';
            }
        }

        if (! in_array((string) ($values['type'] ?? ''), self::roles(), true)) {
            $errors[] = 'Connection-Rolle ist ungueltig.';
        }

        if (! in_array((string) ($values['driver'] ?? ''), self::drivers(), true)) {
            $errors[] = 'Connection-Driver ist ungueltig.';
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    public static function secretsFromPassword(string $password): array
    {
        $password = trim($password);

        return $password === '' ? [] : ['password' => $password];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function readOnlyValue(array $data, string $type): int
    {
        if (! array_key_exists('read_only', $data)) {
            return $type === 'source' ? 1 : 0;
        }

        return ! empty($data['read_only']) ? 1 : 0;
    }
}
