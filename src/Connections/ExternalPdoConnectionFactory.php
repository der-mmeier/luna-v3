<?php

declare(strict_types=1);

namespace Luna\Connections;

use Luna\Network\HostResolver;
use PDO;
use Throwable;

final class ExternalPdoConnectionFactory
{
    public function create(ExternalDatabaseConfig $config, bool $enforceReadOnlySession = true): PDO
    {
        $connectHost = HostResolver::resolveForTcp($config->host());

        $pdo = new PDO(
            $config->dsn($connectHost),
            $config->username(),
            $config->password(),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ],
        );

        if ($enforceReadOnlySession && $config->readOnly()) {
            try {
                $pdo->exec('SET SESSION TRANSACTION READ ONLY');
            } catch (Throwable) {
            }
        }

        return $pdo;
    }
}
