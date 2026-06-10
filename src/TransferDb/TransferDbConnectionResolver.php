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

    /** @return array{workspace: array<string, mixed>, connection: array<string, mixed>, pdo: PDO} */
    public function resolve(string|int $workspaceIdentifier, ?int $connectionId = null): array
    {
        $workspace = $this->workspaces->findByIdentifier((string) $workspaceIdentifier);
        if ($workspace === null) {
            throw new RuntimeException('Workspace wurde nicht gefunden.');
        }

        $selectedConnectionId = $connectionId ?? (empty($workspace['transfer_db_connection_id']) ? null : (int) $workspace['transfer_db_connection_id']);
        if ($selectedConnectionId === null || $selectedConnectionId <= 0) {
            $available = $this->connections->transferDbConnectionsForWorkspace((int) $workspace['id']);
            if ($available === []) {
                throw new RuntimeException(sprintf('Für Workspace "%s" ist keine TransferDB verfügbar. Bitte legen Sie eine TransferDB-Connection an oder geben Sie eine bestehende TransferDB für diesen Workspace frei.', (string) $workspace['name']));
            }
            $selectedConnectionId = (int) $available[0]['id'];
        }

        $resolved = $this->resolveConnection($selectedConnectionId);
        $allowedIds = array_map(static fn (array $row): int => (int) $row['id'], $this->connections->transferDbConnectionsForWorkspace((int) $workspace['id']));
        if (! in_array($selectedConnectionId, $allowedIds, true)) {
            throw new RuntimeException(sprintf('TransferDB "%s" ist für Workspace "%s" nicht freigegeben.', (string) $resolved['connection']['name'], (string) $workspace['name']));
        }

        return ['workspace' => $workspace, 'connection' => $resolved['connection'], 'pdo' => $resolved['pdo']];
    }

    /** @return array{connection: array<string, mixed>, pdo: PDO} */
    public function resolveConnection(int $connectionId): array
    {
        $connection = $this->connections->find($connectionId);
        if ($connection === null || empty($connection['is_active'])) {
            throw new RuntimeException('TransferDB-Connection wurde nicht gefunden oder ist inaktiv.');
        }
        if (! in_array((string) ($connection['type'] ?? ''), ['transfer_db', 'mixed'], true)) {
            throw new RuntimeException('Die ausgewählte Connection ist nicht als TransferDB markiert.');
        }

        $pdo = $this->pdoFactory->create(ExternalDatabaseConfig::fromProfile($connection, $this->connections->secretsFor($connectionId)), false);

        return ['connection' => $connection, 'pdo' => $pdo];
    }
}
