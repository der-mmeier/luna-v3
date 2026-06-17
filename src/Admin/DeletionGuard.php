<?php

declare(strict_types=1);

namespace Luna\Admin;

use Luna\Database\SystemDatabase;
use PDO;
use Throwable;

final class DeletionGuard
{
    public function __construct(
        private readonly SystemDatabase $database,
        private readonly ?PDO $connection = null,
    ) {
    }

    /**
     * @return array{
     *     can_delete: bool,
     *     entity: array{type: string, id: int, name: string},
     *     blockers: list<array{type: string, id: int, name: string, reason: string}>
     * }
     */
    public function canDelete(string $entityType, int $id): array
    {
        $entityType = $this->normalizeType($entityType);
        $entity = [
            'type' => $entityType,
            'id' => $id,
            'name' => $this->entityName($entityType, $id),
        ];

        $blockers = match ($entityType) {
            'workspace' => $this->workspaceBlockers($id),
            'connection' => $this->connectionBlockers($id),
            'mapping' => $this->mappingBlockers($id),
            'schema' => $this->schemaBlockers($id),
            'process' => $this->processBlockers($id),
            'target_action' => $this->targetActionBlockers($id),
            'transfer' => $this->transferBlockers($id),
            'job' => $this->jobBlockers($id),
            'report' => $this->reportBlockers($id),
            'woocommerce' => $this->woocommerceConnectionBlockers($id),
            default => [],
        };

        return [
            'can_delete' => $blockers === [],
            'entity' => $entity,
            'blockers' => $blockers,
        ];
    }

    /**
     * @param array{
     *     can_delete: bool,
     *     entity: array{type: string, id: int, name: string},
     *     blockers: list<array{type: string, id: int, name: string, reason: string}>
     * } $check
     */
    public function message(array $check): string
    {
        $entity = $check['entity'];
        $blockers = $check['blockers'];
        $label = $this->typeLabel((string) $entity['type']);
        $name = (string) $entity['name'];

        if ($blockers === []) {
            return $label . ' "' . $name . '" kann gelöscht werden.';
        }

        $lines = [$label . ' "' . $name . '" kann nicht gelöscht werden, ' . match ($entity['type']) {
            'connection' => $this->onlyShareBlockers($blockers) ? 'weil sie noch für folgende Workspaces freigegeben ist:' : 'weil sie noch verwendet wird:',
            'woocommerce' => 'weil sie noch verwendet wird:',
            'transfer' => 'weil noch Läufe oder Runtime-Daten existieren:',
            'job', 'report' => 'weil er noch verwendet wird:',
            default => 'weil der Eintrag noch verwendet wird:',
        }];

        foreach (array_slice($blockers, 0, 10) as $blocker) {
            $lines[] = '- ' . $this->typeLabel($blocker['type']) . ' "' . $blocker['name'] . '"';
        }

        if (count($blockers) > 10) {
            $lines[] = '- weitere ' . (count($blockers) - 10) . ' Einträge';
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<array{type: string, id: int, name: string, reason: string}>
     */
    private function workspaceBlockers(int $workspaceId): array
    {
        return array_merge(
            $this->rowsByColumn('luna_connection_profiles', 'workspace_id', $workspaceId, 'connection', 'name', 'belongs to workspace'),
            $this->rowsByColumn('luna_mapping_sets', 'workspace_id', $workspaceId, 'mapping', 'name', 'belongs to workspace'),
            $this->rowsByColumn('luna_endpoints', 'workspace_id', $workspaceId, 'endpoint', 'name', 'belongs to workspace'),
            $this->rowsByColumn('luna_processes', 'workspace_id', $workspaceId, 'process', 'name', 'belongs to workspace'),
            $this->rowsByColumn('luna_target_actions', 'workspace_id', $workspaceId, 'target_action', 'name', 'belongs to workspace'),
            $this->rowsByColumn('luna_jobs', 'workspace_id', $workspaceId, 'job', 'name', 'belongs to workspace'),
            $this->rowsByColumn('luna_reports', 'workspace_id', $workspaceId, 'report', 'subject', 'belongs to workspace'),
            $this->schemaRowsByWorkspace($workspaceId),
            $this->rowsByColumn('luna_deployment_targets', 'workspace_id', $workspaceId, 'deployment_target', 'name', 'belongs to workspace'),
            $this->rowsByColumn('luna_dataset_transfers', 'workspace_id', $workspaceId, 'transfer', 'name', 'belongs to workspace'),
            $this->rowsByColumn('luna_woocommerce_connections', 'workspace_id', $workspaceId, 'woocommerce', 'name', 'belongs to workspace'),
            $this->rowsByColumn('luna_export_profiles', 'workspace_id', $workspaceId, 'export_profile', 'name', 'belongs to workspace'),
        );
    }

    /**
     * @return list<array{type: string, id: int, name: string, reason: string}>
     */
    private function connectionBlockers(int $connectionId): array
    {
        $blockers = array_merge(
            $this->workspaceShareRowsForConnection($connectionId),
            $this->mappingRowsForConnection($connectionId),
            $this->jobRowsForConnection($connectionId),
            $this->datasetRowsForConnection($connectionId),
            $this->rowsByColumn('luna_dataset_transfers', 'target_connection_id', $connectionId, 'transfer', 'name', 'uses connection as target'),
            $this->rowsByColumn('luna_woocommerce_connections', 'connection_id', $connectionId, 'woocommerce', 'name', 'uses connection'),
            $this->rowsByColumn('luna_workspaces', 'transfer_db_connection_id', $connectionId, 'workspace', 'name', 'uses connection as TransferDB'),
            $this->processStepRows('connection', $connectionId),
            $this->processStepRows('connection_profile', $connectionId),
            $this->targetActionsReferencingConnection($connectionId),
        );

        $schemaCount = count($this->rowsByColumn('luna_schema_snapshots', 'connection_profile_id', $connectionId, 'schema_snapshot', 'id', 'uses connection'))
            + count($this->rowsByColumn('luna_table_notes', 'connection_profile_id', $connectionId, 'schema_note', 'id', 'uses connection'))
            + count($this->rowsByColumn('luna_column_notes', 'connection_profile_id', $connectionId, 'schema_note', 'id', 'uses connection'));

        if ($schemaCount > 0) {
            $blockers[] = [
                'type' => 'schema_explorer',
                'id' => 0,
                'name' => $schemaCount . ' Schema-/Explorer-Einträge',
                'reason' => 'uses connection',
            ];
        }

        return $this->uniqueBlockers($blockers);
    }

    /**
     * @return list<array{type: string, id: int, name: string, reason: string}>
     */
    private function mappingBlockers(int $mappingId): array
    {
        return array_merge(
            $this->rowsByColumn('luna_endpoints', 'mapping_set_id', $mappingId, 'endpoint', 'name', 'uses mapping'),
            $this->rowsByColumn('luna_jobs', 'mapping_set_id', $mappingId, 'job', 'name', 'uses mapping'),
            $this->processStepRows('mapping_set', $mappingId),
        );
    }

    /**
     * @return list<array{type: string, id: int, name: string, reason: string}>
     */
    private function schemaBlockers(int $schemaId): array
    {
        return array_merge(
            $this->rowsByColumn('luna_endpoints', 'schema_id', $schemaId, 'endpoint', 'name', 'uses schema'),
            $this->processStepRows('schema', $schemaId),
        );
    }

    /**
     * @return list<array{type: string, id: int, name: string, reason: string}>
     */
    private function processBlockers(int $processId): array
    {
        return $this->rowsByColumn('luna_process_runs', 'process_id', $processId, 'process_run', 'id', 'process has run history');
    }

    /**
     * @return list<array{type: string, id: int, name: string, reason: string}>
     */
    private function targetActionBlockers(int $targetActionId): array
    {
        return $this->processStepRows('target_action', $targetActionId);
    }

    /**
     * @return list<array{type: string, id: int, name: string, reason: string}>
     */
    private function transferBlockers(int $transferId): array
    {
        return $this->runtimeRowsByColumn(
            'luna_dataset_transfer_runs',
            'transfer_id',
            $transferId,
            'transfer_run',
            [],
            ['started_at', 'created_at'],
            'transfer has run history',
        );
    }

    /**
     * @return list<array{type: string, id: int, name: string, reason: string}>
     */
    private function jobBlockers(int $jobId): array
    {
        return $this->rowsByColumn('luna_endpoints', 'job_id', $jobId, 'endpoint', 'name', 'uses job');
    }

    /**
     * @return list<array{type: string, id: int, name: string, reason: string}>
     */
    private function reportBlockers(int $reportId): array
    {
        return [];
    }

    /**
     * @return list<array{type: string, id: int, name: string, reason: string}>
     */
    private function woocommerceConnectionBlockers(int $woocommerceConnectionId): array
    {
        return $this->uniqueBlockers(array_merge(
            $this->rowsByColumn('luna_woocommerce_webhook_configs', 'woocommerce_connection_id', $woocommerceConnectionId, 'woocommerce_webhook', 'webhook_name', 'belongs to WooCommerce connection'),
            $this->rowsByColumn('luna_export_profiles', 'connection_id', $woocommerceConnectionId, 'export_profile', 'name', 'uses WooCommerce connection'),
            $this->processTriggersReferencingWooCommerceConnection($woocommerceConnectionId),
            $this->runtimeRowsByColumn('luna_woocommerce_webhook_events', 'woocommerce_connection_id', $woocommerceConnectionId, 'woocommerce_event', ['topic', 'source_order_id'], ['received_at', 'created_at'], 'belongs to WooCommerce connection'),
            $this->runtimeRowsByColumn('luna_woocommerce_transfer_queue', 'woocommerce_connection_id', $woocommerceConnectionId, 'woocommerce_queue', ['topic', 'source_order_id'], ['created_at'], 'belongs to WooCommerce connection'),
            $this->runtimeRowsByColumn('luna_woocommerce_transfer_runs', 'woocommerce_connection_id', $woocommerceConnectionId, 'woocommerce_run', ['run_type', 'source_order_id'], ['started_at', 'created_at'], 'belongs to WooCommerce connection'),
            $this->runtimeRowsByColumn('luna_woocommerce_order_headers', 'woocommerce_connection_id', $woocommerceConnectionId, 'woocommerce_data', ['source_order_id'], ['created_at'], 'cached Luna data belongs to WooCommerce connection'),
            $this->runtimeRowsByColumn('luna_woocommerce_order_addresses', 'woocommerce_connection_id', $woocommerceConnectionId, 'woocommerce_data', ['source_order_id', 'source_address_id'], ['created_at'], 'cached Luna data belongs to WooCommerce connection'),
            $this->runtimeRowsByColumn('luna_woocommerce_order_items', 'woocommerce_connection_id', $woocommerceConnectionId, 'woocommerce_data', ['source_order_id', 'source_order_item_id'], ['created_at'], 'cached Luna data belongs to WooCommerce connection'),
            $this->runtimeRowsByColumn('luna_woocommerce_order_itemmeta', 'woocommerce_connection_id', $woocommerceConnectionId, 'woocommerce_data', ['source_order_id', 'source_item_meta_id'], ['created_at'], 'cached Luna data belongs to WooCommerce connection'),
            $this->runtimeRowsByColumn('luna_woocommerce_order_meta', 'woocommerce_connection_id', $woocommerceConnectionId, 'woocommerce_data', ['source_order_id', 'source_order_meta_id'], ['created_at'], 'cached Luna data belongs to WooCommerce connection'),
        ));
    }

    /**
     * @return list<array{type: string, id: int, name: string, reason: string}>
     */
    private function jobRowsForConnection(int $connectionId): array
    {
        $mappingIds = array_column($this->mappingRowsForConnection($connectionId), 'id');
        if ($mappingIds === [] || ! $this->tableExists('luna_jobs') || ! $this->columnExists('luna_jobs', 'mapping_set_id')) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($mappingIds), '?'));
        $statement = $this->pdo()->prepare('SELECT id, name FROM luna_jobs WHERE mapping_set_id IN (' . $placeholders . ') ORDER BY name');
        $statement->execute(array_values($mappingIds));

        return array_map(static fn (array $row): array => [
            'type' => 'job',
            'id' => (int) $row['id'],
            'name' => trim((string) ($row['name'] ?? '')) ?: '#' . (int) $row['id'],
            'reason' => 'job uses a mapping for this connection',
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @return list<array{type: string, id: int, name: string, reason: string}>
     */
    private function datasetRowsForConnection(int $connectionId): array
    {
        $mappingIds = array_column($this->mappingRowsForConnection($connectionId), 'id');
        if ($mappingIds === [] || ! $this->tableExists('luna_endpoints') || ! $this->columnExists('luna_endpoints', 'mapping_set_id')) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($mappingIds), '?'));
        $selectKey = $this->columnExists('luna_endpoints', 'endpoint_key') ? 'endpoint_key' : 'name';
        $statement = $this->pdo()->prepare(sprintf(
            'SELECT id, name, %s AS dataset_key FROM luna_endpoints WHERE mapping_set_id IN (%s) ORDER BY name',
            $selectKey,
            $placeholders,
        ));
        $statement->execute(array_values($mappingIds));

        $blockers = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $name = trim((string) ($row['name'] ?? '')) ?: trim((string) ($row['dataset_key'] ?? ''));
            $blockers[] = [
                'type' => 'dataset',
                'id' => (int) $row['id'],
                'name' => $name !== '' ? $name : '#' . (int) $row['id'],
                'reason' => 'dataset uses a mapping for this connection',
            ];
        }

        return $blockers;
    }

    /**
     * @return list<array{type: string, id: int, name: string, reason: string}>
     */
    private function processTriggersReferencingWooCommerceConnection(int $woocommerceConnectionId): array
    {
        if (! $this->tableExists('luna_process_triggers') || ! $this->columnExists('luna_process_triggers', 'config_json')) {
            return [];
        }

        $statement = $this->pdo()->query('SELECT id, name, config_json FROM luna_process_triggers ORDER BY name');
        $blockers = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $config = json_decode((string) ($row['config_json'] ?? ''), true);
            if (! is_array($config) || (string) ($config['provider'] ?? '') !== 'woocommerce') {
                continue;
            }

            $referencedId = (int) ($config['woocommerce_connection_id'] ?? $config['integration_id'] ?? 0);
            if ($referencedId !== $woocommerceConnectionId) {
                continue;
            }

            $blockers[] = [
                'type' => 'process_trigger',
                'id' => (int) $row['id'],
                'name' => trim((string) ($row['name'] ?? '')) ?: '#' . (int) $row['id'],
                'reason' => 'trigger uses WooCommerce connection',
            ];
        }

        return $blockers;
    }

    /**
     * @return list<array{type: string, id: int, name: string, reason: string}>
     */
    private function mappingRowsForConnection(int $connectionId): array
    {
        if (! $this->tableExists('luna_mapping_sets')) {
            return [];
        }

        $conditions = [];
        if ($this->columnExists('luna_mapping_sets', 'source_connection_id')) {
            $conditions[] = 'source_connection_id = :id';
        }
        if ($this->columnExists('luna_mapping_sets', 'target_connection_id')) {
            $conditions[] = 'target_connection_id = :id';
        }

        $blockers = [];
        if ($conditions !== []) {
            $statement = $this->pdo()->prepare('SELECT id, name FROM luna_mapping_sets WHERE ' . implode(' OR ', $conditions) . ' ORDER BY name');
            $statement->execute(['id' => $connectionId]);
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $blockers[] = [
                    'type' => 'mapping',
                    'id' => (int) $row['id'],
                    'name' => (string) ($row['name'] ?? ('#' . $row['id'])),
                    'reason' => 'uses connection',
                ];
            }
        }

        if ($this->tableExists('luna_mapping_fields') && $this->columnExists('luna_mapping_fields', 'lookup_connection_id')) {
            $statement = $this->pdo()->prepare(
                'SELECT DISTINCT ms.id, ms.name
                 FROM luna_mapping_sets ms
                 INNER JOIN luna_mapping_fields mf ON mf.mapping_set_id = ms.id
                 WHERE mf.lookup_connection_id = :id
                 ORDER BY ms.name',
            );
            $statement->execute(['id' => $connectionId]);
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $blockers[] = [
                    'type' => 'mapping',
                    'id' => (int) $row['id'],
                    'name' => (string) ($row['name'] ?? ('#' . $row['id'])),
                    'reason' => 'uses connection as lookup connection',
                ];
            }
        }

        return $this->uniqueBlockers($blockers);
    }

    /**
     * @return list<array{type: string, id: int, name: string, reason: string}>
     */
    private function processStepRows(string $referenceType, int $referenceId): array
    {
        if (! $this->tableExists('luna_process_steps')) {
            return [];
        }

        $statement = $this->pdo()->prepare(
            'SELECT ps.id, ps.name, p.name AS process_name
             FROM luna_process_steps ps
             LEFT JOIN luna_processes p ON p.id = ps.process_id
             WHERE ps.reference_type = :reference_type AND ps.reference_id = :reference_id
             ORDER BY p.name, ps.position, ps.id',
        );
        $statement->execute(['reference_type' => $referenceType, 'reference_id' => $referenceId]);

        $blockers = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $stepName = (string) ($row['name'] ?? ('#' . $row['id']));
            $processName = (string) ($row['process_name'] ?? '');
            $blockers[] = [
                'type' => 'process_step',
                'id' => (int) $row['id'],
                'name' => $processName === '' ? $stepName : $stepName . ' im Prozess ' . $processName,
                'reason' => 'uses ' . $referenceType,
            ];
        }

        return $blockers;
    }

    /**
     * @return list<array{type: string, id: int, name: string, reason: string}>
     */
    private function workspaceShareRowsForConnection(int $connectionId): array
    {
        if (! $this->tableExists('luna_connection_workspaces') || ! $this->tableExists('luna_workspaces')) {
            return [];
        }

        $statement = $this->pdo()->prepare(
            'SELECT w.id, w.name
             FROM luna_connection_workspaces cw
             INNER JOIN luna_workspaces w ON w.id = cw.workspace_id
             WHERE cw.connection_id = :connection_id
             ORDER BY w.name',
        );
        $statement->execute(['connection_id' => $connectionId]);

        return array_map(static fn (array $row): array => [
            'type' => 'workspace_share',
            'id' => (int) $row['id'],
            'name' => trim((string) ($row['name'] ?? '')) ?: '#' . (int) $row['id'],
            'reason' => 'connection is shared with workspace',
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @return list<array{type: string, id: int, name: string, reason: string}>
     */
    private function targetActionsReferencingConnection(int $connectionId): array
    {
        if (! $this->tableExists('luna_target_actions') || ! $this->columnExists('luna_target_actions', 'config_json')) {
            return [];
        }

        $statement = $this->pdo()->prepare(
            'SELECT id, name FROM luna_target_actions
             WHERE config_json LIKE :quoted OR config_json LIKE :plain
             ORDER BY name',
        );
        $statement->execute([
            'quoted' => '%"connection_id":' . $connectionId . '%',
            'plain' => '%connection_id%' . $connectionId . '%',
        ]);

        $blockers = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $blockers[] = [
                'type' => 'target_action',
                'id' => (int) $row['id'],
                'name' => (string) ($row['name'] ?? ('#' . $row['id'])),
                'reason' => 'configuration references connection',
            ];
        }

        return $blockers;
    }

    /**
     * @return list<array{type: string, id: int, name: string, reason: string}>
     */
    private function schemaRowsByWorkspace(int $workspaceId): array
    {
        if (! $this->tableExists('luna_schemas')) {
            return [];
        }

        $statement = $this->pdo()->prepare(
            'SELECT id, name, schema_key, version FROM luna_schemas WHERE workspace_id = :workspace_id ORDER BY schema_key, version',
        );
        $statement->execute(['workspace_id' => $workspaceId]);

        $blockers = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $name = (string) ($row['schema_key'] ?? ('#' . $row['id']));
            }
            $version = trim((string) ($row['version'] ?? ''));
            $blockers[] = [
                'type' => 'schema',
                'id' => (int) $row['id'],
                'name' => $version === '' ? $name : $name . ' v' . $version,
                'reason' => 'belongs to workspace',
            ];
        }

        return $blockers;
    }

    /**
     * @param list<string> $detailColumns
     * @param list<string> $dateColumns
     * @return list<array{type: string, id: int, name: string, reason: string}>
     */
    private function runtimeRowsByColumn(
        string $table,
        string $column,
        int $value,
        string $type,
        array $detailColumns,
        array $dateColumns,
        string $reason,
    ): array {
        if (! $this->tableExists($table) || ! $this->columnExists($table, $column)) {
            return [];
        }

        $availableDetails = array_values(array_filter($detailColumns, fn (string $detail): bool => $this->columnExists($table, $detail)));
        $availableDates = array_values(array_filter($dateColumns, fn (string $date): bool => $this->columnExists($table, $date)));
        $select = array_merge(['id'], $availableDetails, $availableDates);
        $statement = $this->pdo()->prepare(sprintf(
            'SELECT %s FROM %s WHERE %s = :value ORDER BY id',
            implode(', ', $select),
            $table,
            $column,
        ));
        $statement->execute(['value' => $value]);

        $blockers = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $details = [];
            foreach ($availableDetails as $detailColumn) {
                $detail = trim((string) ($row[$detailColumn] ?? ''));
                if ($detail !== '') {
                    $details[] = $detail;
                }
            }

            $date = '';
            foreach ($availableDates as $dateColumn) {
                $candidate = trim((string) ($row[$dateColumn] ?? ''));
                if ($candidate !== '') {
                    $date = $candidate;
                    break;
                }
            }

            $name = '#' . (int) $row['id'];
            if ($details !== []) {
                $name .= ' (' . implode(', ', $details) . ')';
            }
            if ($date !== '') {
                $name .= ' vom ' . $date;
            }

            $blockers[] = [
                'type' => $type,
                'id' => (int) $row['id'],
                'name' => $name,
                'reason' => $reason,
            ];
        }

        return $blockers;
    }

    /**
     * @return list<array{type: string, id: int, name: string, reason: string}>
     */
    private function rowsByColumn(string $table, string $column, int $value, string $type, string $nameColumn, string $reason): array
    {
        if (! $this->tableExists($table) || ! $this->columnExists($table, $column)) {
            return [];
        }

        $selectName = $this->columnExists($table, $nameColumn) ? $nameColumn : 'id';
        $statement = $this->pdo()->prepare(sprintf(
            'SELECT id, %s AS blocker_name FROM %s WHERE %s = :value ORDER BY %s',
            $selectName,
            $table,
            $column,
            $selectName,
        ));
        $statement->execute(['value' => $value]);

        $blockers = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $name = trim((string) ($row['blocker_name'] ?? ''));
            $blockers[] = [
                'type' => $type,
                'id' => (int) $row['id'],
                'name' => $name === '' ? '#' . (int) $row['id'] : $name,
                'reason' => $reason,
            ];
        }

        return $blockers;
    }

    private function entityName(string $entityType, int $id): string
    {
        $map = [
            'workspace' => ['luna_workspaces', 'name'],
            'connection' => ['luna_connection_profiles', 'name'],
            'mapping' => ['luna_mapping_sets', 'name'],
            'endpoint' => ['luna_endpoints', 'name'],
            'schema' => ['luna_schemas', 'name'],
            'process' => ['luna_processes', 'name'],
            'target_action' => ['luna_target_actions', 'name'],
            'job' => ['luna_jobs', 'name'],
            'report' => ['luna_reports', 'subject'],
            'transfer' => ['luna_dataset_transfers', 'name'],
            'woocommerce' => ['luna_woocommerce_connections', 'name'],
            'woocommerce_webhook' => ['luna_woocommerce_webhook_configs', 'webhook_name'],
            'export_profile' => ['luna_export_profiles', 'name'],
            'deployment_target' => ['luna_deployment_targets', 'name'],
        ];

        [$table, $column] = $map[$entityType] ?? ['', ''];
        if ($table === '' || ! $this->tableExists($table) || ! $this->columnExists($table, $column)) {
            return '#' . $id;
        }

        $statement = $this->pdo()->prepare(sprintf('SELECT %s FROM %s WHERE id = :id', $column, $table));
        $statement->execute(['id' => $id]);
        $name = $statement->fetchColumn();

        return is_string($name) && trim($name) !== '' ? trim($name) : '#' . $id;
    }

    private function normalizeType(string $entityType): string
    {
        return match ($entityType) {
            'connection_profile' => 'connection',
            'mapping_set' => 'mapping',
            'dataset_transfer' => 'transfer',
            'woocommerce_connection' => 'woocommerce',
            default => $entityType,
        };
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'workspace' => 'Workspace',
            'workspace_share' => 'Workspace',
            'connection' => 'Connection',
            'mapping' => 'Mapping',
            'dataset' => 'Dataset',
            'endpoint' => 'Endpoint',
            'schema' => 'Schema',
            'process' => 'Prozess',
            'process_step' => 'Prozess-Schritt',
            'process_trigger' => 'Process Trigger',
            'process_run' => 'Prozesslauf',
            'target_action' => 'Target Action',
            'job' => 'Job',
            'report' => 'Report',
            'transfer' => 'Transfer',
            'transfer_run' => 'Transfer-Lauf',
            'deployment_target' => 'Deployment Target',
            'woocommerce' => 'WooCommerce-Anbindung',
            'woocommerce_webhook' => 'WooCommerce-Webhook',
            'woocommerce_event' => 'WooCommerce-Event',
            'woocommerce_queue' => 'WooCommerce-Queue-Eintrag',
            'woocommerce_run' => 'WooCommerce-Lauf',
            'woocommerce_data' => 'WooCommerce-Runtime-Datensatz',
            'export_profile' => 'Exportprofil',
            'schema_explorer' => 'Schema Explorer',
            default => $type,
        };
    }

    /**
     * @param list<array{type: string, id: int, name: string, reason: string}> $blockers
     * @return list<array{type: string, id: int, name: string, reason: string}>
     */
    private function uniqueBlockers(array $blockers): array
    {
        $seen = [];
        $unique = [];

        foreach ($blockers as $blocker) {
            $key = $blocker['type'] . ':' . $blocker['id'] . ':' . $blocker['name'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $blocker;
        }

        return $unique;
    }

    /**
     * @param list<array{type: string, id: int, name: string, reason: string}> $blockers
     */
    private function onlyShareBlockers(array $blockers): bool
    {
        return $blockers !== [] && array_unique(array_column($blockers, 'type')) === ['workspace_share'];
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

    private function columnExists(string $table, string $column): bool
    {
        try {
            $this->pdo()->query(sprintf('SELECT %s FROM %s WHERE 1 = 0', $column, $table));

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function pdo(): PDO
    {
        return $this->connection ?? $this->database->pdo();
    }
}
