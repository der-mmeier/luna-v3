<?php

declare(strict_types=1);

namespace Luna\Database;

use Luna\Config\Config;

final class DatabaseConfig
{
    public function __construct(
        private readonly Config $config,
    ) {
    }

    public function dsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->host(),
            $this->port(),
            $this->database(),
            $this->charset(),
        );
    }

    public function username(): string
    {
        return $this->config->string('LUNA_DB_USERNAME');
    }

    public function password(): string
    {
        return $this->config->string('LUNA_DB_PASSWORD');
    }

    /**
     * @return array<int, mixed>
     */
    public function options(): array
    {
        return [];
    }

    public function isConfigured(): bool
    {
        return $this->host() !== ''
            && $this->database() !== ''
            && $this->username() !== '';
    }

    private function host(): string
    {
        return $this->config->string('LUNA_DB_HOST');
    }

    private function port(): int
    {
        return $this->config->int('LUNA_DB_PORT', 3306);
    }

    private function database(): string
    {
        return $this->config->string('LUNA_DB_DATABASE');
    }

    private function charset(): string
    {
        return $this->config->string('LUNA_DB_CHARSET', 'utf8mb4');
    }
}
