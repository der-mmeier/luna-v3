<?php

declare(strict_types=1);

namespace Luna\Database;

use PDO;
use RuntimeException;

final class SystemDatabase
{
    private ?PDO $pdo = null;

    public function __construct(
        private readonly DatabaseConfig $config,
        private readonly PdoConnectionFactory $factory,
    ) {
    }

    public function pdo(): PDO
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Luna system database is not configured.');
        }

        if ($this->pdo === null) {
            $this->pdo = $this->factory->create($this->config);
        }

        return $this->pdo;
    }

    public function isConfigured(): bool
    {
        return $this->config->isConfigured();
    }

    public function testConnection(): bool
    {
        $this->pdo()->query('SELECT 1');

        return true;
    }
}
