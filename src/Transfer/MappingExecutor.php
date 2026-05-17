<?php

declare(strict_types=1);

namespace Luna\Transfer;

use Luna\Connections\ExternalDatabaseConfig;
use Luna\Connections\ExternalPdoConnectionFactory;
use Luna\Repository\ConnectionProfileRepository;
use Luna\Repository\MappingRepository;
use Luna\Mapping\MappingValidator;
use PDO;
use RuntimeException;

final class MappingExecutor
{
    public function __construct(
        private readonly MappingRepository $mappings,
        private readonly MappingValidator $validator,
        private readonly ConnectionProfileRepository $connections,
        private readonly ExternalPdoConnectionFactory $pdoFactory,
        private readonly MappingRowTransformer $transformer,
        private readonly TargetWriter $writer,
    ) {}

    public function execute(int $mappingSetId, bool $dryRun = true, ?int $limit = null): MappingExecutionResult
    {
        $result = new MappingExecutionResult($dryRun);
        $result->addLog('info', $dryRun ? 'mapping.dry_run.started' : 'mapping.transfer.started', [
            'mapping_set_id' => $mappingSetId,
        ]);
        $validation = $this->validator->validate($mappingSetId);

        if (! $validation->isValid()) {
            foreach ($validation->errors() as $error) {
                $result->addError($error);
            }
            $result->addLog('error', 'Mapping validation failed.');
            return $result;
        }

        $result->addLog('info', 'Mapping validiert.');

        $set = $this->mappings->find($mappingSetId);
        if ($set === null) {
            $result->addError('Mapping Set existiert nicht.');
            return $result;
        }

        $source = $this->connections->find((int) $set['source_connection_id']);
        $target = $this->connections->find((int) $set['target_connection_id']);
        if ($source === null || $target === null) {
            $result->addError('Source oder Target Connection fehlt.');
            return $result;
        }

        $sourcePdo = $this->pdoFactory->create(ExternalDatabaseConfig::fromProfile($source, $this->connections->secretsFor((int) $source['id'])));
        $rows = $this->readSourceRows($sourcePdo, (string) $set['source_table'], $limit ?? 25);
        $fields = $this->mappings->fieldsForSet($mappingSetId);
        $targetRows = [];
        $result->sourceCount = count($rows);
        $result->addLog('info', 'Source Rows gelesen.', ['source_count' => $result->sourceCount]);

        foreach ($rows as $row) {
            $targetRow = $this->transformer->transform($row, $fields, $result);
            $result->transformedCount++;
            $result->addPreviewRow($targetRow);
            $targetRows[] = $targetRow;
        }

        $result->addLog('info', 'Rows transformiert.', ['transformed_count' => $result->transformedCount]);

        if ($dryRun) {
            $result->writtenCount = 0;
            $result->addLog('info', 'Dry Run beendet, keine Zielschreiboperation ausgefuehrt.');
            return $result;
        }

        $result->addLog('info', 'Target read_only geprueft.', ['target_read_only' => (int) $target['read_only'] === 1]);
        if ((int) $target['read_only'] === 1) {
            $result->writtenCount = 0;
            $result->addError('Target connection is read-only. Transfer was blocked.');
            return $result;
        }

        $targetPdo = $this->pdoFactory->create(ExternalDatabaseConfig::fromProfile($target, $this->connections->secretsFor((int) $target['id'])));

        try {
            $result->writtenCount = $this->writer->insertRows($targetPdo, (string) $set['target_table'], $targetRows);
            $result->addLog('info', 'Rows geschrieben.', ['written_count' => $result->writtenCount]);
        } catch (\Throwable) {
            $result->writtenCount = 0;
            $result->addError('Target insert failed.');
            return $result;
        }

        $result->addLog('info', 'mapping.transfer.success');
        return $result;
    }

    private function readSourceRows(PDO $pdo, string $tableName, int $limit): array
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $tableName) !== 1) {
            throw new RuntimeException('Invalid source table.');
        }
        $limit = max(1, min($limit, 1000));
        return $pdo->query(sprintf('SELECT * FROM `%s` LIMIT %d', $tableName, $limit))->fetchAll();
    }
}
