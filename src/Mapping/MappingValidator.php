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

        if ($sourceConnection !== null && $sourceTable !== '') {
            $sourceColumns = $this->columnsFor($sourceConnection, $sourceTable, 'Source', $result);
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
                $result->addError('Ein Mapping Field hat kein Transfer-Feld.');
            }

            if ($targetColumn !== '') {
                if (isset($mappedTargets[$targetColumn])) {
                    $result->addError(sprintf('Transfer-Feld "%s" ist mehrfach gemappt.', $targetColumn));
                }
                $mappedTargets[$targetColumn] = true;
            }

            if (! in_array($transformType, ['static', 'static_value', 'lookup_value', 'json_path'], true)) {
                $sourceColumn = (string) ($field['source_column'] ?? '');
                if ($sourceColumn === '') {
                    $result->addError(sprintf('Source Column für Transfer-Feld "%s" fehlt.', $targetColumn));
                } elseif (! isset($sourceColumns[$sourceColumn]) && $sourceColumns !== []) {
                    $result->addError(sprintf('Source Column "%s" existiert nicht.', $sourceColumn));
                }
            }

            if ($transformType === 'json_path' && trim((string) ($field['source_json_path'] ?? '')) === '') {
                $result->addError(sprintf('JSON Path für Transfer-Feld "%s" fehlt.', $targetColumn));
            }

            if (in_array($transformType, ['static', 'static_value'], true) && (string) ($field['default_value'] ?? '') === '') {
                $result->addError(sprintf('Static Mapping für Transfer-Feld "%s" braucht default_value.', $targetColumn));
            }

            if ($transformType === 'lookup_value') {
                foreach (['lookup_connection_id', 'lookup_table', 'lookup_key_column', 'lookup_value_column', 'lookup_key_template'] as $key) {
                    if (empty($field[$key])) {
                        $result->addError(sprintf('Lookup Mapping für Transfer-Feld "%s" braucht %s.', $targetColumn, $key));
                    }
                }
            }

            if ($transformType === 'lookup_value') {
                $matchMode = (string) ($field['lookup_match_mode'] ?? 'exact');
                if (! LookupMatchMode::isValid($matchMode)) {
                    $result->addError(sprintf('Lookup Match Mode "%s" ist nicht erlaubt.', $matchMode));
                }

                $resultMode = (string) ($field['lookup_result_mode'] ?? 'first');
                if (! LookupResultMode::isValid($resultMode)) {
                    $result->addError(sprintf('Lookup Result Mode "%s" ist nicht erlaubt.', $resultMode));
                }

                if ($resultMode === 'key_value_map' && empty($field['lookup_result_key_column'])) {
                    $result->addError(sprintf('Lookup Mapping für Transfer-Feld "%s" braucht lookup_result_key_column.', $targetColumn));
                }

                $resultKeyTransform = (string) ($field['lookup_result_key_transform'] ?? 'none');
                if (! LookupResultMode::isValidKeyTransform($resultKeyTransform)) {
                    $result->addError(sprintf('Lookup Result Key Transform "%s" ist nicht erlaubt.', $resultKeyTransform));
                }
            }

            if ($transformType === 'enum_map' && $this->mappings->valueRulesForField((int) $field['id']) === []) {
                $result->addError(sprintf('Enum Mapping für Transfer-Feld "%s" braucht mindestens eine Value Rule.', $targetColumn));
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
