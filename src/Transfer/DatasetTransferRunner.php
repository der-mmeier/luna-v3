<?php

declare(strict_types=1);

namespace Luna\Transfer;

use Closure;
use Luna\Connections\ExternalDatabaseConfig;
use Luna\Connections\ExternalPdoConnectionFactory;
use Luna\Dataset\DatasetRegistry;
use Luna\Repository\ConnectionProfileRepository;
use Luna\Repository\DatasetTransferRepository;
use PDO;

final class DatasetTransferRunner
{
    private Closure $targetPdoFactory;

    public function __construct(
        private readonly DatasetTransferRepository $transfers,
        private readonly DatasetRegistry $datasets,
        private readonly ConnectionProfileRepository $connections,
        private readonly SingleTableTransferWriter $writer,
        ExternalPdoConnectionFactory $externalPdoFactory,
        ?callable $targetPdoFactory = null,
    ) {
        $this->targetPdoFactory = Closure::fromCallable($targetPdoFactory ?? function (array $profile) use ($externalPdoFactory): PDO {
            return $externalPdoFactory->create(ExternalDatabaseConfig::fromProfile($profile, $this->connections->secretsFor((int) $profile['id'])), false);
        });
    }

    public function run(int $transferId, bool $dryRun = true, ?int $limit = null): DatasetTransferResult
    {
        $transfer = $this->transfers->find($transferId);
        if ($transfer === null) {
            $result = new DatasetTransferResult($dryRun, '', '', '', '');
            $result->addError('Transfer wurde nicht gefunden.');

            return $result;
        }

        $result = new DatasetTransferResult(
            $dryRun,
            (string) ($transfer['source_dataset'] ?? ''),
            (string) ($transfer['target_table'] ?? ''),
            (string) ($transfer['operation_type'] ?? ''),
            (string) ($transfer['upsert_key'] ?? ''),
        );

        $legacyFields = $this->transfers->fieldsForTransfer($transferId);
        $groups = $this->groupsForTransfer($transferId, $transfer, $legacyFields);
        foreach ($this->validateGroups($transfer, $groups) as $error) {
            $result->addError($error);
        }
        if ($result->errorCount() > 0) {
            return $result;
        }

        $profile = $this->connections->find((int) $transfer['target_connection_id']);
        if ($profile === null) {
            $result->addError('Target Connection wurde nicht gefunden.');

            return $result;
        }

        $targetPdo = ($this->targetPdoFactory)($profile);
        $datasetFields = array_values(array_map(
            static fn (mixed $field): string => (string) $field,
            array_column($this->datasets->fields((string) $transfer['source_dataset']), 'name'),
        ));

        foreach ($groups as $group) {
            $targetColumns = $this->targetColumns($targetPdo, (string) $group['target_table']);
            $this->validateGroupReferences($group, $datasetFields, $targetColumns, $result);
        }
        if ($result->errorCount() > 0) {
            return $result;
        }

        $datasetRows = $this->datasets->rows((string) $transfer['source_dataset'], $limit);
        $result->sourceCount = count($datasetRows);

        $operationGroups = [];
        foreach ($groups as $group) {
            $operations = $this->planGroupOperations($group, $datasetRows, $result);
            foreach ($operations as $operation) {
                $result->addPreviewOperation($operation);
            }
            $result->addTargetGroup($group, $operations);
            $operationGroups[] = [
                'target_table' => (string) $group['target_table'],
                'operations' => $operations,
            ];
        }
        if ($result->errorCount() > 0) {
            return $result;
        }

        $result->plannedCount = array_sum(array_map(static fn (array $group): int => count($group['operations']), $operationGroups));
        if ($dryRun) {
            return $result;
        }

        $result->writtenCount = $this->writer->writeGroups($targetPdo, $operationGroups);

        return $result;
    }

    /**
     * @param array<string, mixed> $transfer
     * @param list<array<string, mixed>> $fields
     *
     * @return list<string>
     */
    public function validate(array $transfer, array $fields): array
    {
        $groups = [[
            'name' => 'Root',
            'group_type' => 'root',
            'source_path' => '$',
            'target_table' => (string) ($transfer['target_table'] ?? ''),
            'operation_type' => (string) ($transfer['operation_type'] ?? 'upsert'),
            'upsert_key' => (string) ($transfer['upsert_key'] ?? ''),
            'fields' => $fields,
        ]];

        return $this->validateGroups($transfer, $groups);
    }

    /**
     * @param array<string, mixed> $transfer
     * @param list<array<string, mixed>> $legacyFields
     *
     * @return list<array<string, mixed>>
     */
    private function groupsForTransfer(int $transferId, array $transfer, array $legacyFields): array
    {
        $groups = $this->transfers->groupsForTransfer($transferId);
        if ($groups === []) {
            return [[
                'id' => 0,
                'name' => 'Root',
                'group_type' => 'root',
                'source_path' => '$',
                'target_table' => (string) ($transfer['target_table'] ?? ''),
                'operation_type' => (string) ($transfer['operation_type'] ?? 'upsert'),
                'upsert_key' => (string) ($transfer['upsert_key'] ?? ''),
                'fields' => $legacyFields,
            ]];
        }

        foreach ($groups as $index => $group) {
            $groups[$index]['fields'] = $this->transfers->fieldsForGroup((int) $group['id']);
        }

        return $groups;
    }

    /**
     * @param array<string, mixed> $transfer
     * @param list<array<string, mixed>> $groups
     *
     * @return list<string>
     */
    private function validateGroups(array $transfer, array $groups): array
    {
        $errors = [];

        if (trim((string) ($transfer['name'] ?? '')) === '') {
            $errors[] = 'Name ist erforderlich.';
        }
        if ($this->datasets->find((string) ($transfer['source_dataset'] ?? '')) === null) {
            $errors[] = 'Source Dataset wurde nicht gefunden.';
        }
        if (empty($transfer['target_connection_id'])) {
            $errors[] = 'Target Connection ist für Transfers erforderlich.';
        }
        if ($groups === []) {
            $errors[] = 'Mindestens eine Target Group ist erforderlich.';
        }

        foreach ($groups as $group) {
            $operation = (string) ($group['operation_type'] ?? 'upsert');
            if (trim((string) ($group['name'] ?? '')) === '') {
                $errors[] = 'Target Group Name ist erforderlich.';
            }
            if (! in_array((string) ($group['group_type'] ?? 'root'), ['root', 'child'], true)) {
                $errors[] = 'Target Group Typ ist ungültig.';
            }
            if (trim((string) ($group['target_table'] ?? '')) === '') {
                $errors[] = 'Target Table ist für Target Groups erforderlich.';
            }
            if (! in_array($operation, ['insert', 'update', 'upsert'], true)) {
                $errors[] = 'Operation ist ungültig.';
            }
            if (($operation === 'update' || $operation === 'upsert') && $this->upsertKeyColumns((string) ($group['upsert_key'] ?? '')) === []) {
                $errors[] = 'Upsert Key ist für jede Update-/Upsert-Target-Group erforderlich.';
            }
            if ((array) ($group['fields'] ?? []) === []) {
                $errors[] = 'Jede Target Group benötigt mindestens eine Feldzuordnung.';
            }
            if ((string) ($group['group_type'] ?? 'root') === 'child') {
                if (! str_ends_with((string) ($group['source_path'] ?? ''), '[]')) {
                    $errors[] = 'Child Target Groups benötigen eine Source Collection wie positions[].';
                }
                if (trim((string) ($group['parent_link_source'] ?? '')) === '' || trim((string) ($group['parent_link_target'] ?? '')) === '') {
                    $errors[] = 'Child Target Groups benötigen einen Parent Link.';
                }
            }
        }

        return $errors;
    }

    /**
     * @param list<string> $datasetFields
     * @param list<string> $targetColumns
     */
    private function validateGroupReferences(array $group, array $datasetFields, array $targetColumns, DatasetTransferResult $result): void
    {
        foreach ((array) ($group['fields'] ?? []) as $field) {
            $datasetField = (string) ($field['dataset_field'] ?? '');
            $targetColumn = (string) ($field['target_column'] ?? '');

            if (! $this->fieldReferenceCanBeValidated($datasetField, $group, $datasetFields)) {
                $result->addError('Dataset-Feld wurde nicht gefunden: ' . $datasetField);
            }
            if (! in_array($targetColumn, $targetColumns, true)) {
                $result->addError('Zielspalte wurde nicht gefunden: ' . $targetColumn);
            }
        }

        $parentTarget = (string) ($group['parent_link_target'] ?? '');
        if ($parentTarget !== '' && ! in_array($parentTarget, $targetColumns, true)) {
            $result->addError('Parent-Link-Zielspalte wurde nicht gefunden: ' . $parentTarget);
        }

        foreach ($this->upsertKeyColumns((string) ($group['upsert_key'] ?? '')) as $column) {
            if (! in_array($column, $targetColumns, true)) {
                $result->addError('Upsert-Key-Zielspalte wurde nicht gefunden: ' . $column);
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array{operation: string, key: array<string, mixed>, data: array<string, mixed>}>
     */
    private function planGroupOperations(array $group, array $rows, DatasetTransferResult $result): array
    {
        if ((string) ($group['group_type'] ?? 'root') === 'child') {
            return $this->planChildOperations($group, $rows, $result);
        }

        $operations = [];
        $keyColumns = $this->upsertKeyColumns((string) ($group['upsert_key'] ?? ''));
        $missingFields = [];

        foreach ($rows as $row) {
            $data = [];
            foreach ((array) ($group['fields'] ?? []) as $field) {
                $datasetField = (string) $field['dataset_field'];
                $targetColumn = (string) $field['target_column'];
                $data[$targetColumn] = $this->rootValue($row, $datasetField, $exists);
                if (! $exists) {
                    $missingFields[$datasetField] = true;
                }
            }

            $operations[] = [
                'operation' => (string) ($group['operation_type'] ?? 'upsert'),
                'key' => $this->keyFromData($data, $keyColumns, $result),
                'data' => $data,
            ];
        }

        foreach (array_keys($missingFields) as $field) {
            $result->addError('Dataset-Feld wurde nicht gefunden: ' . $field);
        }

        return $operations;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array{operation: string, key: array<string, mixed>, data: array<string, mixed>}>
     */
    private function planChildOperations(array $group, array $rows, DatasetTransferResult $result): array
    {
        $operations = [];
        $keyColumns = $this->upsertKeyColumns((string) ($group['upsert_key'] ?? ''));
        $collectionName = $this->collectionName((string) ($group['source_path'] ?? ''));
        $missingFields = [];
        $missingCollectionWarned = false;

        foreach ($rows as $row) {
            if (! array_key_exists($collectionName, $row)) {
                if (! $missingCollectionWarned) {
                    $result->addWarning('Child Collection ist nicht vorhanden: ' . $collectionName);
                    $missingCollectionWarned = true;
                }
                continue;
            }

            $items = $row[$collectionName];
            if (! is_array($items)) {
                $result->addWarning('Child Collection ist nicht als Liste verfügbar: ' . $collectionName);
                continue;
            }

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $data = [];
                $parentTarget = (string) ($group['parent_link_target'] ?? '');
                if ($parentTarget !== '') {
                    $data[$parentTarget] = $this->rootValue($row, (string) ($group['parent_link_source'] ?? ''), $exists);
                    if (! $exists) {
                        $missingFields[(string) ($group['parent_link_source'] ?? '')] = true;
                    }
                }

                foreach ((array) ($group['fields'] ?? []) as $field) {
                    $datasetField = (string) $field['dataset_field'];
                    $targetColumn = (string) $field['target_column'];
                    $data[$targetColumn] = $this->childValue($item, $row, $datasetField, $collectionName, $exists);
                    if (! $exists) {
                        $missingFields[$datasetField] = true;
                    }
                }

                $operations[] = [
                    'operation' => (string) ($group['operation_type'] ?? 'upsert'),
                    'key' => $this->keyFromData($data, $keyColumns, $result),
                    'data' => $data,
                ];
            }
        }

        foreach (array_keys($missingFields) as $field) {
            if ($field !== '') {
                $result->addError('Dataset-Feld wurde nicht gefunden: ' . $field);
            }
        }

        return $operations;
    }

    /**
     * @param list<string> $keyColumns
     *
     * @return array<string, mixed>
     */
    private function keyFromData(array $data, array $keyColumns, DatasetTransferResult $result): array
    {
        $key = [];
        foreach ($keyColumns as $column) {
            if (! array_key_exists($column, $data)) {
                $result->addError('Upsert-Key-Wert konnte nicht aus Feldzuordnungen abgeleitet werden: ' . $column);
            }
            $key[$column] = $data[$column] ?? null;
        }

        return $key;
    }

    /**
     * @param list<string> $datasetFields
     */
    private function fieldReferenceCanBeValidated(string $field, array $group, array $datasetFields): bool
    {
        if ($field === '') {
            return false;
        }

        if ((string) ($group['group_type'] ?? 'root') === 'root') {
            $rootField = str_starts_with($field, 'root.') ? substr($field, 5) : $field;

            return in_array($rootField, $datasetFields, true);
        }

        if (str_starts_with($field, 'root.')) {
            return in_array(substr($field, 5), $datasetFields, true);
        }

        $collection = $this->collectionName((string) ($group['source_path'] ?? ''));
        if (str_starts_with($field, $collection . '[].')) {
            return true;
        }

        return true;
    }

    private function rootValue(array $row, string $field, ?bool &$exists = null): mixed
    {
        $rootField = str_starts_with($field, 'root.') ? substr($field, 5) : $field;
        $exists = array_key_exists($rootField, $row);

        return $exists ? $row[$rootField] : null;
    }

    private function childValue(array $item, array $row, string $field, string $collectionName, ?bool &$exists = null): mixed
    {
        if (str_starts_with($field, 'root.')) {
            return $this->rootValue($row, $field, $exists);
        }

        $localField = str_starts_with($field, $collectionName . '[].')
            ? substr($field, strlen($collectionName) + 3)
            : $field;
        $exists = array_key_exists($localField, $item);

        return $exists ? $item[$localField] : null;
    }

    private function collectionName(string $sourcePath): string
    {
        $sourcePath = trim($sourcePath);
        if (str_ends_with($sourcePath, '[]')) {
            return substr($sourcePath, 0, -2);
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function upsertKeyColumns(string $upsertKey): array
    {
        return array_values(array_filter(array_map(
            static fn (string $column): string => trim($column),
            explode(',', $upsertKey),
        ), static fn (string $column): bool => $column !== ''));
    }

    /**
     * @return list<string>
     */
    private function targetColumns(PDO $pdo, string $table): array
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
            return [];
        }

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $pdo->query('PRAGMA table_info(`' . str_replace('`', '``', $table) . '`)');
            if ($statement === false) {
                return [];
            }

            return array_values(array_map(static fn (array $row): string => (string) $row['name'], $statement->fetchAll()));
        }

        $databaseStatement = $pdo->query('SELECT DATABASE()');
        $database = $databaseStatement === false ? '' : $databaseStatement->fetchColumn();
        $statement = $pdo->prepare(
            'SELECT COLUMN_NAME AS column_name
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table
             ORDER BY ORDINAL_POSITION',
        );
        $statement->execute(['schema' => (string) $database, 'table' => $table]);

        return array_values(array_map(static fn (array $row): string => (string) $row['column_name'], $statement->fetchAll()));
    }
}
