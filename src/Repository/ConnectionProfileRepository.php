<?php

declare(strict_types=1);

namespace Luna\Repository;

use Luna\Connections\ConnectionProfileData;
use Luna\Database\SystemDatabase;
use Luna\Security\EncryptionService;
use PDO;
use Throwable;

final class ConnectionProfileRepository
{
    public function __construct(
        private readonly SystemDatabase $database,
        private readonly EncryptionService $encryption,
        private readonly ?PDO $pdo = null,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $statement = $this->pdo()->query(
            'SELECT cp.*, w.name AS workspace_name
             FROM luna_connection_profiles cp
             LEFT JOIN luna_workspaces w ON w.id = cp.workspace_id
             ORDER BY cp.name',
        );

        return $statement->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT cp.*, w.name AS workspace_name
             FROM luna_connection_profiles cp
             LEFT JOIN luna_workspaces w ON w.id = cp.workspace_id
             WHERE cp.id = :id',
        );
        $statement->execute(['id' => $id]);
        $profile = $statement->fetch();

        return $profile === false ? null : $profile;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $secrets
     */
    public function create(array $data, array $secrets): int
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $statement = $pdo->prepare(
                'INSERT INTO luna_connection_profiles
                (workspace_id, name, type, driver, host, port, database_name, username, read_only, is_active, config_json, notes, created_at, updated_at)
                VALUES
                (:workspace_id, :name, :type, :driver, :host, :port, :database_name, :username, :read_only, :is_active, :config_json, :notes, NOW(), NOW())',
            );
            $statement->execute($this->profilePayload($data));
            $id = (int) $pdo->lastInsertId();
            $this->storeSecrets($id, $secrets);
            $this->syncWorkspaces($id, $data);
            $pdo->commit();

            return $id;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $secrets
     */
    public function update(int $id, array $data, array $secrets = []): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $payload = $this->profilePayload($data);
            $payload['id'] = $id;
            $statement = $pdo->prepare(
                'UPDATE luna_connection_profiles
                 SET workspace_id = :workspace_id, name = :name, type = :type, driver = :driver,
                     host = :host, port = :port, database_name = :database_name, username = :username,
                     read_only = :read_only, is_active = :is_active, config_json = :config_json,
                     notes = :notes, updated_at = NOW()
                 WHERE id = :id',
            );
            $statement->execute($payload);
            $this->storeSecrets($id, $secrets);
            $this->syncWorkspaces($id, $data);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function delete(int $id): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            if ($this->tableExists('luna_connection_workspaces')) {
                $statement = $pdo->prepare('DELETE FROM luna_connection_workspaces WHERE connection_id = :id');
                $statement->execute(['id' => $id]);
            }
            $statement = $pdo->prepare('DELETE FROM luna_connection_secrets WHERE connection_profile_id = :id');
            $statement->execute(['id' => $id]);
            $statement = $pdo->prepare('DELETE FROM luna_connection_profiles WHERE id = :id');
            $statement->execute(['id' => $id]);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function canDelete(int $id): DeleteCheckResult
    {
        $profile = $this->find($id);
        $connectionName = $profile === null ? ('#' . $id) : (string) $profile['name'];
        $mappingNames = $this->mappingNamesForConnection($id);
        $jobNames = $this->jobNamesForConnection($id);
        $endpointNames = $this->endpointNamesForConnection($id);
        $defaultWorkspaceNames = $this->defaultTransferDbWorkspaceNames($id);
        $transferNames = $this->transferNamesForConnection($id);
        $woocommerceNames = $this->woocommerceNamesForConnection($id);
        $datasetSourceNames = $this->datasetSourceNamesForConnection($id);
        $reportNames = $this->reportNamesForConnection($id);
        $targetActionNames = $this->targetActionNamesForConnection($id);
        $schemaCount = $this->countByConnection('luna_schema_snapshots', $id)
            + $this->countByConnection('luna_table_notes', $id)
            + $this->countByConnection('luna_column_notes', $id);

        $blockingNames = array_values(array_unique(array_merge(
            $mappingNames,
            $jobNames,
            $endpointNames,
            $defaultWorkspaceNames,
            $transferNames,
            $woocommerceNames,
            $datasetSourceNames,
            $reportNames,
            $targetActionNames,
        )));
        if ($blockingNames !== []) {
            return DeleteCheckResult::blocked(
                sprintf('Connection "%s" kann nicht gelöscht werden, weil abhängige Ressourcen existieren. Bitte löschen, verschieben oder deaktivieren Sie diese Ressourcen zuerst.', $connectionName),
                $blockingNames,
                [
                    'mappings' => count($mappingNames),
                    'jobs' => count($jobNames),
                    'endpoints' => count($endpointNames),
                    'workspace_defaults' => count($defaultWorkspaceNames),
                    'transfers' => count($transferNames),
                    'woocommerce' => count($woocommerceNames),
                    'dataset_sources' => count($datasetSourceNames),
                    'reports' => count($reportNames),
                    'target_actions' => count($targetActionNames),
                    'schema' => $schemaCount,
                ],
            );
        }

        if ($schemaCount > 0) {
            return DeleteCheckResult::blocked(
                sprintf('Connection "%s" kann nicht gelöscht werden, weil noch %d Schema-/Explorer-Einträge vorhanden sind. Bitte löschen Sie diese Explorer-Daten zuerst.', $connectionName, $schemaCount),
                [],
                ['schema' => $schemaCount],
            );
        }

        return DeleteCheckResult::allowed();
    }

    /**
     * @return array<string, string>
     */
    public function secretsFor(int $connectionProfileId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT secret_key, secret_value_encrypted FROM luna_connection_secrets WHERE connection_profile_id = :id',
        );
        $statement->execute(['id' => $connectionProfileId]);
        $secrets = [];

        foreach ($statement->fetchAll() as $row) {
            $secrets[$row['secret_key']] = $this->encryption->decrypt($row['secret_value_encrypted']);
        }

        return $secrets;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function workspacesForConnection(int $connectionId): array
    {
        if (! $this->tableExists('luna_connection_workspaces')) {
            $profile = $this->find($connectionId);
            if ($profile === null || empty($profile['workspace_id'])) {
                return [];
            }

            return [[
                'id' => (int) $profile['workspace_id'],
                'name' => (string) ($profile['workspace_name'] ?? ''),
                'role' => (string) ($profile['type'] ?? ''),
                'is_default' => 0,
            ]];
        }

        $statement = $this->pdo()->prepare(
            'SELECT w.*, cw.role, cw.is_default
             FROM luna_connection_workspaces cw
             INNER JOIN luna_workspaces w ON w.id = cw.workspace_id
             WHERE cw.connection_id = :connection_id
             ORDER BY w.name',
        );
        $statement->execute(['connection_id' => $connectionId]);

        return $statement->fetchAll();
    }

    /**
     * @return list<int>
     */
    public function workspaceIdsForConnection(int $connectionId): array
    {
        return array_map(static fn (array $row): int => (int) $row['id'], $this->workspacesForConnection($connectionId));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function transferDbConnectionsForWorkspace(int $workspaceId): array
    {
        if ($this->tableExists('luna_connection_workspaces')) {
            $statement = $this->pdo()->prepare(
                "SELECT DISTINCT cp.*, w.name AS workspace_name
                 FROM luna_connection_profiles cp
                 LEFT JOIN luna_workspaces w ON w.id = cp.workspace_id
                 LEFT JOIN luna_connection_workspaces cw ON cw.connection_id = cp.id
                 WHERE cp.is_active = 1
                   AND cp.type IN ('transfer_db', 'mixed')
                   AND (cp.workspace_id = :workspace_id OR cw.workspace_id = :shared_workspace_id)
                 ORDER BY cp.name",
            );
            $statement->execute(['workspace_id' => $workspaceId, 'shared_workspace_id' => $workspaceId]);

            return $statement->fetchAll();
        }

        $statement = $this->pdo()->prepare(
            "SELECT cp.*, w.name AS workspace_name
             FROM luna_connection_profiles cp
             LEFT JOIN luna_workspaces w ON w.id = cp.workspace_id
             WHERE cp.is_active = 1 AND cp.type IN ('transfer_db', 'mixed') AND cp.workspace_id = :workspace_id
             ORDER BY cp.name",
        );
        $statement->execute(['workspace_id' => $workspaceId]);

        return $statement->fetchAll();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function profilePayload(array $data): array
    {
        $normalized = ConnectionProfileData::normalize($data);

        return [
            'workspace_id' => $normalized['workspace_id'],
            'name' => $normalized['name'],
            'type' => $normalized['type'],
            'driver' => $normalized['driver'],
            'host' => $normalized['host'],
            'port' => $normalized['port'],
            'database_name' => $normalized['database_name'],
            'username' => $normalized['username'],
            'read_only' => $normalized['read_only'],
            'is_active' => $normalized['is_active'],
            'config_json' => json_encode(['charset' => $normalized['charset']], JSON_UNESCAPED_SLASHES),
            'notes' => $normalized['notes'],
        ];
    }

    /**
     * @param array<string, string> $secrets
     */
    private function storeSecrets(int $connectionProfileId, array $secrets): void
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_connection_secrets
             (connection_profile_id, secret_key, secret_value_encrypted, encryption_version, created_at, updated_at)
             VALUES (:connection_profile_id, :secret_key, :secret_value_encrypted, :encryption_version, NOW(), NOW())
             ON DUPLICATE KEY UPDATE secret_value_encrypted = VALUES(secret_value_encrypted), encryption_version = VALUES(encryption_version), updated_at = NOW()',
        );

        foreach ($secrets as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $statement->execute([
                'connection_profile_id' => $connectionProfileId,
                'secret_key' => (string) $key,
                'secret_value_encrypted' => $this->encryption->encrypt((string) $value),
                'encryption_version' => 'v1',
            ]);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function syncWorkspaces(int $connectionId, array $data): void
    {
        if (! $this->tableExists('luna_connection_workspaces')) {
            return;
        }

        $ids = [];
        if (! empty($data['workspace_id'])) {
            $ids[] = (int) $data['workspace_id'];
        }
        foreach ((array) ($data['shared_workspace_ids'] ?? []) as $workspaceId) {
            if ((int) $workspaceId > 0) {
                $ids[] = (int) $workspaceId;
            }
        }
        $ids = array_values(array_unique($ids));

        $this->pdo()->prepare('DELETE FROM luna_connection_workspaces WHERE connection_id = :id')->execute(['id' => $connectionId]);
        if ($ids === []) {
            return;
        }

        $insert = $this->pdo()->prepare(
            'INSERT INTO luna_connection_workspaces (connection_id, workspace_id, role, is_default, created_at, updated_at)
             VALUES (:connection_id, :workspace_id, :role, :is_default, NOW(), NOW())',
        );
        $type = (string) ($data['type'] ?? 'source');
        foreach ($ids as $workspaceId) {
            $insert->execute([
                'connection_id' => $connectionId,
                'workspace_id' => $workspaceId,
                'role' => $type,
                'is_default' => 0,
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function mappingNamesForConnection(int $connectionId): array
    {
        $names = [];
        $conditions = [];
        if ($this->columnExists('luna_mapping_sets', 'source_connection_id')) {
            $conditions[] = 'source_connection_id = :source_connection_id';
        }
        if ($this->columnExists('luna_mapping_sets', 'target_connection_id')) {
            $conditions[] = 'target_connection_id = :target_connection_id';
        }

        if ($conditions !== []) {
            $statement = $this->pdo()->prepare(sprintf('SELECT name FROM luna_mapping_sets WHERE %s ORDER BY name', implode(' OR ', $conditions)));
            $statement->execute(['source_connection_id' => $connectionId, 'target_connection_id' => $connectionId]);
            foreach ($statement->fetchAll() as $row) {
                $names[] = 'Mapping "' . (string) $row['name'] . '"';
            }
        }

        if ($this->columnExists('luna_mapping_fields', 'lookup_connection_id')) {
            $statement = $this->pdo()->prepare(
                'SELECT DISTINCT ms.name
                 FROM luna_mapping_sets ms
                 INNER JOIN luna_mapping_fields mf ON mf.mapping_set_id = ms.id
                 WHERE mf.lookup_connection_id = :id
                 ORDER BY ms.name',
            );
            $statement->execute(['id' => $connectionId]);
            foreach ($statement->fetchAll() as $row) {
                $names[] = 'Mapping "' . (string) $row['name'] . '"';
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return list<string>
     */
    private function jobNamesForConnection(int $connectionId): array
    {
        if (! $this->columnExists('luna_jobs', 'mapping_set_id')) {
            return [];
        }

        $statement = $this->pdo()->prepare(
            'SELECT DISTINCT j.name
             FROM luna_jobs j
             INNER JOIN luna_mapping_sets ms ON ms.id = j.mapping_set_id
             WHERE ms.source_connection_id = :id OR ms.target_connection_id = :id
             ORDER BY j.name',
        );
        $statement->execute(['id' => $connectionId]);

        return array_map(static fn (array $row): string => 'Job "' . (string) $row['name'] . '"', $statement->fetchAll());
    }

    /**
     * @return list<string>
     */
    private function endpointNamesForConnection(int $connectionId): array
    {
        if (! $this->columnExists('luna_endpoints', 'mapping_set_id')) {
            return [];
        }

        $statement = $this->pdo()->prepare(
            'SELECT DISTINCT e.name
             FROM luna_endpoints e
             INNER JOIN luna_mapping_sets ms ON ms.id = e.mapping_set_id
             WHERE ms.source_connection_id = :id OR ms.target_connection_id = :id
             ORDER BY e.name',
        );
        $statement->execute(['id' => $connectionId]);

        return array_map(static fn (array $row): string => 'Endpoint "' . (string) $row['name'] . '"', $statement->fetchAll());
    }

    /**
     * @return list<string>
     */
    private function defaultTransferDbWorkspaceNames(int $connectionId): array
    {
        if (! $this->columnExists('luna_workspaces', 'transfer_db_connection_id')) {
            return [];
        }

        $statement = $this->pdo()->prepare('SELECT name FROM luna_workspaces WHERE transfer_db_connection_id = :id ORDER BY name');
        $statement->execute(['id' => $connectionId]);

        return array_map(static fn (array $row): string => 'Workspace Default-TransferDB "' . (string) $row['name'] . '"', $statement->fetchAll());
    }

    /**
     * @return list<string>
     */
    private function transferNamesForConnection(int $connectionId): array
    {
        if (! $this->columnExists('luna_dataset_transfers', 'target_connection_id')) {
            return [];
        }

        $statement = $this->pdo()->prepare('SELECT name FROM luna_dataset_transfers WHERE target_connection_id = :id ORDER BY name');
        $statement->execute(['id' => $connectionId]);

        return array_map(static fn (array $row): string => 'Transfer "' . (string) $row['name'] . '"', $statement->fetchAll());
    }

    /**
     * @return list<string>
     */
    private function woocommerceNamesForConnection(int $connectionId): array
    {
        if (! $this->columnExists('luna_woocommerce_connections', 'connection_id')) {
            return [];
        }

        $statement = $this->pdo()->prepare('SELECT name FROM luna_woocommerce_connections WHERE connection_id = :id ORDER BY name');
        $statement->execute(['id' => $connectionId]);

        return array_map(static fn (array $row): string => 'WooCommerce-Anbindung "' . (string) $row['name'] . '"', $statement->fetchAll());
    }

    /**
     * @return list<string>
     */
    private function datasetSourceNamesForConnection(int $connectionId): array
    {
        foreach (['luna_dataset_sources', 'luna_datasets'] as $table) {
            if (! $this->columnExists($table, 'connection_id')) {
                continue;
            }

            $nameColumn = $this->columnExists($table, 'name') ? 'name' : 'id';
            $statement = $this->pdo()->prepare(sprintf('SELECT %s AS name FROM %s WHERE connection_id = :id ORDER BY %s', $nameColumn, $table, $nameColumn));
            $statement->execute(['id' => $connectionId]);

            return array_map(static fn (array $row): string => 'Dataset "' . (string) $row['name'] . '"', $statement->fetchAll());
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function reportNamesForConnection(int $connectionId): array
    {
        if (! $this->columnExists('luna_reports', 'connection_id')) {
            return [];
        }

        $nameColumn = $this->columnExists('luna_reports', 'name') ? 'name' : 'subject';
        $statement = $this->pdo()->prepare(sprintf('SELECT %s AS name FROM luna_reports WHERE connection_id = :id ORDER BY %s', $nameColumn, $nameColumn));
        $statement->execute(['id' => $connectionId]);

        return array_map(static fn (array $row): string => 'Report "' . (string) $row['name'] . '"', $statement->fetchAll());
    }

    /**
     * @return list<string>
     */
    private function targetActionNamesForConnection(int $connectionId): array
    {
        if (! $this->tableExists('luna_target_actions') || ! $this->columnExists('luna_target_actions', 'config_json')) {
            return [];
        }

        $statement = $this->pdo()->query('SELECT name, action_key, config_json FROM luna_target_actions ORDER BY name');
        $names = [];
        foreach ($statement->fetchAll() as $row) {
            $config = json_decode((string) ($row['config_json'] ?? ''), true);
            if (! is_array($config)) {
                continue;
            }

            $configuredId = $config['connection_id'] ?? $config['connection_profile_id'] ?? null;
            if ((int) $configuredId !== $connectionId) {
                continue;
            }

            $label = trim((string) ($row['name'] ?? '')) ?: (string) ($row['action_key'] ?? '');
            $names[] = 'Target Action "' . $label . '"';
        }

        return $names;
    }

    private function countByConnection(string $table, int $connectionId): int
    {
        if (! $this->columnExists($table, 'connection_profile_id')) {
            return 0;
        }

        $statement = $this->pdo()->prepare(sprintf('SELECT COUNT(*) FROM %s WHERE connection_profile_id = :id', $table));
        $statement->execute(['id' => $connectionId]);

        return (int) $statement->fetchColumn();
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            $this->pdo()->query(sprintf('SELECT %s FROM %s WHERE 1 = 0', $column, $table));

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            $this->pdo()->query(sprintf('SELECT 1 FROM %s WHERE 1 = 0', $table));

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function pdo(): PDO
    {
        return $this->pdo ?? $this->database->pdo();
    }
}
