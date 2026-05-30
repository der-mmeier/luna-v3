<?php

declare(strict_types=1);

namespace Luna\Transfer;

use Luna\Connections\ExternalDatabaseConfig;
use Luna\Connections\ExternalPdoConnectionFactory;
use Luna\Repository\ConnectionProfileRepository;
use Luna\Repository\MappingRepository;
use Luna\Mapping\MappingValidator;

final class MappingExecutor
{
    public function __construct(
        private readonly MappingRepository $mappings,
        private readonly MappingValidator $validator,
        private readonly ConnectionProfileRepository $connections,
        private readonly ExternalPdoConnectionFactory $pdoFactory,
        private readonly MappingRowTransformer $transformer,
        private readonly TargetWriter $writer,
        private readonly MappingSourceRowProvider $sourceRows,
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
        $set['source_filters'] = $this->mappings->sourceFiltersForSet($mappingSetId);

        $mode = (string) ($set['mapping_mode'] ?? 'transfer');
        $source = $this->connections->find((int) $set['source_connection_id']);
        $target = $mode === 'transfer' ? $this->connections->find((int) $set['target_connection_id']) : null;
        if ($source === null || ($mode === 'transfer' && $target === null)) {
            $result->addError($mode === 'transfer' ? 'Source oder Target Connection fehlt.' : 'Source Connection fehlt.');
            return $result;
        }

        $sourcePdo = $this->pdoFactory->create(ExternalDatabaseConfig::fromProfile($source, $this->connections->secretsFor((int) $source['id'])));
        $rows = $this->sourceRows->rows($sourcePdo, (string) $set['source_table'], $set, $limit);
        $fields = $this->mappings->fieldsForSet($mappingSetId);
        $fields = $this->withLookupConnectionNames($fields);
        $targetRows = [];
        $result->sourceCount = count($rows);
        $result->addLog('info', 'Source Rows gelesen.', ['source_count' => $result->sourceCount]);
        $this->transformer->warmUpPrefixLookups($rows, $fields, $result);

        foreach ($rows as $row) {
            $result->addSourceRow($row);
            $eventOffset = $result->resolverEventCount();
            $targetRow = $this->transformer->transform($row, $fields, $result);
            $result->transformedCount++;
            $result->addPreviewRow($targetRow);
            $result->addPreviewRecord($row, $targetRow, $result->resolverEventsSince($eventOffset));
            $targetRows[] = $targetRow;
        }

        $result->addLog('info', 'Rows transformiert.', ['transformed_count' => $result->transformedCount]);

        if ($dryRun || $mode === 'json_endpoint') {
            $result->writtenCount = 0;
            $result->addLog('info', $mode === 'json_endpoint' ? 'JSON Endpoint Mapping beendet, keine Zielschreiboperation ausgeführt.' : 'Dry Run beendet, keine Zielschreiboperation ausgeführt.');
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

    private function withLookupConnectionNames(array $fields): array
    {
        $connectionNames = [];

        foreach ($fields as $field) {
            $connectionId = (int) ($field['lookup_connection_id'] ?? 0);

            if ($connectionId <= 0 || array_key_exists($connectionId, $connectionNames)) {
                continue;
            }

            $profile = $this->connections->find($connectionId);
            $connectionNames[$connectionId] = $profile === null ? '' : (string) ($profile['name'] ?? '');
        }

        foreach ($fields as $index => $field) {
            $connectionId = (int) ($field['lookup_connection_id'] ?? 0);

            if ($connectionId > 0) {
                $fields[$index]['lookup_connection_name'] = $connectionNames[$connectionId] ?? '';
            }
        }

        return $fields;
    }

}
