<?php

declare(strict_types=1);

namespace Luna\TransferDb;

use Luna\Connections\ExternalDatabaseConfig;
use Luna\Connections\ExternalPdoConnectionFactory;
use Luna\Repository\ConnectionProfileRepository;
use Luna\Repository\WorkspaceRepository;
use PDO;
use RuntimeException;

final class TransferDbConnectionResolver
{
    public function __construct(
        private readonly WorkspaceRepository $workspaces,
        private readonly ConnectionProfileRepository $connections,
        private readonly ExternalPdoConnectionFactory $pdoFactory,
    ) {
    }

    /**
     * @return array{workspace: array<string, mixed>, connection: array<string, mixed>, pdo: PDO}
     */
    public function resolve(string|int $workspaceIdentifier): array
    {
        $workspace = $this->workspaces->findByIdentifier((string) $workspaceIdentifier);
        if ($workspace === null) {
            throw new RuntimeException('Workspace wurde nicht gefunden.');
        }

        $connectionId = (int) ($workspace['transfer_db_connection_id'] ?? 0);
        if ($connectionId <= 0) {
            throw new RuntimeException('Für diesen Workspace ist keine TransferDB konfiguriert.');
        }

        $connection = $this->connections->find($connectionId);
        if ($connection === null || empty($connection['is_active'])) {
            throw new RuntimeException('TransferDB-Connection wurde nicht gefunden oder ist inaktiv.');
        }

        if (! in_array((string) ($connection['type'] ?? ''), ['transfer_db', 'mixed'], true)) {
            throw new RuntimeException('Die ausgewählte Connection ist nicht als TransferDB markiert.');
        }

        $pdo = $this->pdoFactory->create(
            ExternalDatabaseConfig::fromProfile($connection, $this->connections->secretsFor($connectionId)),
            false,
        );

        return [
            'workspace' => $workspace,
            'connection' => $connection,
            'pdo' => $pdo,
        ];
    }
}
