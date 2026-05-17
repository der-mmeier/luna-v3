<?php

declare(strict_types=1);

namespace Luna\Mapping;

use Luna\Connections\ExternalDatabaseConfig;
use Luna\Connections\ExternalPdoConnectionFactory;
use Luna\Repository\ConnectionProfileRepository;
use Luna\Repository\MappingRepository;
use Luna\Schema\SchemaInspector;
use Throwable;

final class MappingValidator
{
    public function __construct(
        private readonly MappingRepository $mappings,
        private readonly ConnectionProfileRepository $connections,
        private readonly ExternalPdoConnectionFactory $pdoFactory,
    ) {
    }

    public function validate(int $mappingSetId): MappingValidationResult
    {
        $result = new MappingValidationResult();
        $set = $this->mappings->find($mappingSetId);

        if ($set === null) {
            $result->addError('Mapping Set existiert nicht.');
            return $result;
        }

        if (empty($set['workspace_id'])) {
            $result->addWarning('Mapping Set ist keinem Workspace zugeordnet.');
        }

        $sourceConnection = $this->connection((int) ($set['source_connection_id'] ?? 0), 'Source', $result);
        $targetConnection = $this->connection((int) ($set['target_connection_id'] ?? 0), 'Target', $result);
        $sourceTable = (string) ($set['source_table'] ?? '');
        $targetTable = (string) ($set['target_table'] ?? '');

        if ($sourceTable === '') {
            $result->addError('Source Table ist nicht gesetzt.');
        }

        if ($targetTable === '') {
            $result->addError('Target Table ist nicht gesetzt.');
        }

        $sourceColumns = [];
        $targetColumns = [];

        if ($sourceConnection !== null && $sourceTable !== '') {
            $sourceColumns = $this->columnsFor($sourceConnection, $sourceTable, 'Source', $result);
        }

        if ($targetConnection !== null && $targetTable !== '') {
            $targetColumns = $this->columnsFor($targetConnection, $targetTable, 'Target', $result);
        }

        $fields = $this->mappings->fieldsForSet($mappingSetId);
        $mappedTargets = [];

        foreach ($fields as $field) {
            $targetColumn = (string) ($field['target_column'] ?? '');
            $transformType = (string) ($field['transform_type'] ?? '');

            if (! TransformType::isValid($transformType)) {
                $result->addError(sprintf('Transformationsart "%s" ist nicht erlaubt.', $transformType));
            }

            if ($targetColumn === '') {
                $result->addError('Ein Mapping Field hat keine Target Column.');
            } elseif (! isset($targetColumns[$targetColumn]) && $targetColumns !== []) {
                $result->addError(sprintf('Target Column "%s" existiert nicht.', $targetColumn));
            }

            if ($targetColumn !== '') {
                if (isset($mappedTargets[$targetColumn])) {
                    $result->addError(sprintf('Target Column "%s" ist mehrfach gemappt.', $targetColumn));
                }
                $mappedTargets[$targetColumn] = true;
            }

            if (! in_array($transformType, ['static', 'json_path'], true)) {
                $sourceColumn = (string) ($field['source_column'] ?? '');
                if ($sourceColumn === '') {
                    $result->addError(sprintf('Source Column für Target "%s" fehlt.', $targetColumn));
                } elseif (! isset($sourceColumns[$sourceColumn]) && $sourceColumns !== []) {
                    $result->addError(sprintf('Source Column "%s" existiert nicht.', $sourceColumn));
                }
            }

            if ($transformType === 'json_path' && trim((string) ($field['source_json_path'] ?? '')) === '') {
                $result->addError(sprintf('JSON Path für Target "%s" fehlt.', $targetColumn));
            }

            if ($transformType === 'static' && (string) ($field['default_value'] ?? '') === '') {
                $result->addError(sprintf('Static Mapping für Target "%s" braucht default_value.', $targetColumn));
            }

            if ($transformType === 'enum_map' && $this->mappings->valueRulesForField((int) $field['id']) === []) {
                $result->addError(sprintf('Enum Mapping für Target "%s" braucht mindestens eine Value Rule.', $targetColumn));
            }
        }

        foreach ($targetColumns as $name => $column) {
            $required = ($column['is_nullable'] ?? '') === 'NO'
                && ($column['column_default'] ?? null) === null
                && ! str_contains((string) ($column['extra'] ?? ''), 'auto_increment');

            if ($required && ! isset($mappedTargets[$name])) {
                $result->addWarning(sprintf('Pflichtfeld "%s" der Target Table ist nicht gemappt.', $name));
            }
        }

        $result->addInfo(sprintf('%d Mapping Field(s) geprüft.', count($fields)));

        return $result;
    }

    private function connection(int $id, string $label, MappingValidationResult $result): ?array
    {
        if ($id <= 0) {
            $result->addError($label . ' Connection ist nicht gesetzt.');
            return null;
        }

        $connection = $this->connections->find($id);

        if ($connection === null) {
            $result->addError($label . ' Connection existiert nicht.');
        }

        return $connection;
    }

    private function columnsFor(array $connection, string $table, string $label, MappingValidationResult $result): array
    {
        try {
            $config = ExternalDatabaseConfig::fromProfile(
                $connection,
                $this->connections->secretsFor((int) $connection['id']),
            );
            $inspector = new SchemaInspector($this->pdoFactory->create($config));
            $columns = $inspector->columns($table);

            if ($columns === []) {
                $result->addError(sprintf('%s Table "%s" existiert nicht oder hat keine lesbaren Spalten.', $label, $table));
            }

            return array_column($columns, null, 'column_name');
        } catch (Throwable) {
            $result->addError(sprintf('%s Table "%s" konnte nicht gelesen werden.', $label, $table));
            return [];
        }
    }
}
