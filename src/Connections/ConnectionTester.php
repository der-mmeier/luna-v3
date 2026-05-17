<?php

declare(strict_types=1);

namespace Luna\Connections;

use Throwable;

final class ConnectionTester
{
    public function __construct(
        private readonly ExternalPdoConnectionFactory $factory,
    ) {
    }

    public function test(ExternalDatabaseConfig $config): array
    {
        try {
            $this->factory->create($config)->query('SELECT 1');

            return ['success' => true, 'message' => 'Verbindung erfolgreich getestet.'];
        } catch (Throwable) {
            return ['success' => false, 'message' => 'Verbindung konnte nicht hergestellt werden. Konfiguration prüfen.'];
        }
    }
}
