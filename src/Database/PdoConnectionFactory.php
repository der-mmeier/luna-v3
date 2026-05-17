<?php

declare(strict_types=1);

namespace Luna\Database;

use PDO;

final class PdoConnectionFactory
{
    public function create(DatabaseConfig $config): PDO
    {
        $options = $config->options() + [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        return new PDO(
            $config->dsn(),
            $config->username(),
            $config->password(),
            $options,
        );
    }
}
