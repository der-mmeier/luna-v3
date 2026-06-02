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

        $fields = $this->transfers->fieldsForTransfer($transferId);
        $errors = $this->validate($transfer, $fields);
        if ($errors !== []) {
            foreach ($errors as $error) {
                $result->addError($error);
            }

            return $result;
        }

        $profile = $this->connections->find((int) $transfer['target_connection_id']);
        if ($profile === null) {
            $result->addError('Target Connection wurde nicht gefunden.');

            return $result;
        }

        $targetPdo = ($this->targetPdoFactory)($profile);
        $targetColumns = $this->targetColumns($targetPdo, (string) $transfer['target_table']);
        $datasetFields = array_values(array_map(
            static fn (mixed $field): string => (string) $field,
            array_column($this->datasets->fields((string) $transfer['source_dataset']), 'name'),
        ));
        $this->validateFieldReferences($fields, $datasetFields, $targetColumns, $result);
        if ($result->errorCount() > 0) {
            return $result;
        }

        $datasetRows = $this->datasets->rows((string) $transfer['source_dataset'], $limit);
        $result->sourceCount = count($datasetRows);

        $operations = $this->planOperations($transfer, $fields, $datasetRows);
        foreach ($operations as $operation) {
            $result->addPreviewOperation($operation);
        }

        $result->plannedCount = count($operations);
        if ($dryRun) {
            return $result;
        }

        $result->writtenCount = $this->writer->write($targetPdo, (string) $transfer['target_table'], $operations);

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
        $errors = [];
        $operation = (string) ($transfer['operation_type'] ?? 'upsert');

        if (trim((string) ($transfer['name'] ?? '')) === '') {
            $errors[] = 'Name ist erforderlich.';
        }

        if ($this->datasets->find((string) ($transfer['source_dataset'] ?? '')) === null) {
            $errors[] = 'Source Dataset wurde nicht gefunden.';
        }

        if (empty($transfer['target_connection_id'])) {
            $errors[] = 'Target Connection ist für Transfers erforderlich.';
        }

        if (trim((string) ($transfer['target_table'] ?? '')) === '') {
            $errors[] = 'Target Table ist für Transfers erforderlich.';
        }

        if (! in_array($operation, ['insert', 'update', 'upsert'], true)) {
            $errors[] = 'Operation ist ungültig.';
        }

        if (($operation === 'update' || $operation === 'upsert') && $this->upsertKeyColumns((string) ($transfer['upsert_key'] ?? '')) === []) {
            $errors[] = 'Upsert Key ist für Update und Upsert erforderlich.';
        }

        if ($fields === []) {
            $errors[] = 'Mindestens eine Feldzuordnung ist erforderlich.';
        }

        return $errors;
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @param list<string> $datasetFields
     * @param list<string> $targetColumns
     */
    private function validateFieldReferences(array $fields, array $datasetFields, array $targetColumns, DatasetTransferResult $result): void
    {
        foreach ($fields as $field) {
            $datasetField = (string) ($field['dataset_field'] ?? '');
            $targetColumn = (string) ($field['target_column'] ?? '');

            if (! in_array($datasetField, $datasetFields, true)) {
                $result->addError('Dataset-Feld wurde nicht gefunden: ' . $datasetField);
            }

            if (! in_array($targetColumn, $targetColumns, true)) {
                $result->addError('Zielspalte wurde nicht gefunden: ' . $targetColumn);
            }
        }
    }

    /**
     * @param array<string, mixed> $transfer
     * @param list<array<string, mixed>> $fields
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array{operation: string, key: array<string, mixed>, data: array<string, mixed>}>
     */
    private function planOperations(array $transfer, array $fields, array $rows): array
    {
        $operations = [];
        $keyColumns = $this->upsertKeyColumns((string) ($transfer['upsert_key'] ?? ''));

        foreach ($rows as $row) {
            $data = [];
            foreach ($fields as $field) {
                $datasetField = (string) $field['dataset_field'];
                $targetColumn = (string) $field['target_column'];
                $data[$targetColumn] = $row[$datasetField] ?? null;
            }

            $key = [];
            foreach ($keyColumns as $column) {
                $key[$column] = $data[$column] ?? null;
            }

            $operations[] = [
                'operation' => (string) ($transfer['operation_type'] ?? 'upsert'),
                'key' => $key,
                'data' => $data,
            ];
        }

        return $operations;
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
