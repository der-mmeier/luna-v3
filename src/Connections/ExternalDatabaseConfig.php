<?php

declare(strict_types=1);

namespace Luna\Connections;

use RuntimeException;

final class ExternalDatabaseConfig
{
    public function __construct(
        private readonly string $driver,
        private readonly string $host,
        private readonly ?int $port,
        private readonly string $databaseName,
        private readonly string $username,
        private readonly string $password,
        private readonly string $charset = 'utf8mb4',
        private readonly bool $readOnly = true,
    ) {
    }

    public static function fromProfile(array $profile, array $secrets): self
    {
        return new self(
            (string) ($profile['driver'] ?? 'mysql'),
            (string) ($profile['host'] ?? ''),
            isset($profile['port']) ? (int) $profile['port'] : null,
            (string) ($profile['database_name'] ?? ''),
            (string) ($profile['username'] ?? ''),
            (string) ($secrets['password'] ?? ''),
            self::charsetFromProfile($profile),
            (bool) ($profile['read_only'] ?? true),
        );
    }

    public function dsn(): string
    {
        if (! in_array($this->driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Unsupported external database driver.');
        }

        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->host,
            $this->port ?? 3306,
            $this->databaseName,
            $this->charset,
        );
    }

    public function username(): string
    {
        return $this->username;
    }

    public function password(): string
    {
        return $this->password;
    }

    public function databaseName(): string
    {
        return $this->databaseName;
    }

    public function readOnly(): bool
    {
        return $this->readOnly;
    }

    private static function charsetFromProfile(array $profile): string
    {
        $config = json_decode((string) ($profile['config_json'] ?? ''), true);

        if (is_array($config) && isset($config['charset'])) {
            return (string) $config['charset'];
        }

        return 'utf8mb4';
    }
}
