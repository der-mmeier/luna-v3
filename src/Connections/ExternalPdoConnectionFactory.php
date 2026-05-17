<?php

declare(strict_types=1);

namespace Luna\Connections;

use PDO;
use Throwable;

final class ExternalPdoConnectionFactory
{
    public function create(ExternalDatabaseConfig $config): PDO
    {
        $pdo = new PDO(
            $config->dsn(),
            $config->username(),
            $config->password(),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );

        if ($config->readOnly()) {
            try {
                $pdo->exec('SET SESSION TRANSACTION READ ONLY');
            } catch (Throwable) {
            }
        }

        return $pdo;
    }
}
