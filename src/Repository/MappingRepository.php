<?php

declare(strict_types=1);

namespace Luna\Repository;

use Luna\Database\SystemDatabase;
use PDO;

final class MappingRepository
{
    public function __construct(
        private readonly SystemDatabase $database,
        private readonly ?PDO $pdo = null,
    ) {
    }

    public function all(): array
    {
        $statement = $this->pdo()->query(
            'SELECT ms.*,
                    w.name AS workspace_name,
                    sc.name AS source_connection_name,
                    tc.name AS target_connection_name
             FROM luna_mapping_sets ms
             LEFT JOIN luna_workspaces w ON w.id = ms.workspace_id
             LEFT JOIN luna_connection_profiles sc ON sc.id = ms.source_connection_id
             LEFT JOIN luna_connection_profiles tc ON tc.id = ms.target_connection_id
             ORDER BY ms.updated_at DESC, ms.name',
        );

        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT ms.*,
                    w.name AS workspace_name,
                    sc.name AS source_connection_name,
                    tc.name AS target_connection_name
             FROM luna_mapping_sets ms
             LEFT JOIN luna_workspaces w ON w.id = ms.workspace_id
             LEFT JOIN luna_connection_profiles sc ON sc.id = ms.source_connection_id
             LEFT JOIN luna_connection_profiles tc ON tc.id = ms.target_connection_id
             WHERE ms.id = :id',
        );
        $statement->execute(['id' => $id]);
        $mapping = $statement->fetch();

        return $mapping === false ? null : $mapping;
    }

    public function createSet(array $data): int
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_mapping_sets
             (workspace_id, name, description, mapping_mode, source_connection_id, source_table, target_connection_id, target_table, status, created_at, updated_at)
             VALUES (:workspace_id, :name, :description, :mapping_mode, :source_connection_id, :source_table, :target_connection_id, :target_table, :status, NOW(), NOW())',
        );
        $statement->execute($this->setPayload($data));

        return (int) $this->pdo()->lastInsertId();
    }

    public function updateSet(int $id, array $data): void
    {
        $payload = $this->setPayload($data);
        $payload['id'] = $id;
        $statement = $this->pdo()->prepare(
            'UPDATE luna_mapping_sets
             SET workspace_id = :workspace_id,
                 name = :name,
                 description = :description,
                 mapping_mode = :mapping_mode,
                 source_connection_id = :source_connection_id,
                 source_table = :source_table,
                 target_connection_id = :target_connection_id,
                 target_table = :target_table,
                 status = :status,
                 updated_at = NOW()
             WHERE id = :id',
        );
        $statement->execute($payload);
    }

    /**
     * @param array<string, mixed> $filter
     */
    public function updateSourceFilter(int $id, array $filter): void
    {
        $this->replaceSourceFilters($id, [[
            'source_column' => $filter['source_filter_column'] ?? '',
            'operator' => $filter['source_filter_operator'] ?? 'none',
            'filter_value' => $filter['source_filter_value'] ?? '',
        ]]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sourceFiltersForSet(int $mappingSetId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT * FROM luna_mapping_source_filters WHERE mapping_set_id = :id ORDER BY sort_order, id',
        );
        $statement->execute(['id' => $mappingSetId]);

        return $statement->fetchAll();
    }

    /**
     * @param list<array<string, mixed>> $filters
     */
    public function replaceSourceFilters(int $mappingSetId, array $filters): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $statement = $pdo->prepare('DELETE FROM luna_mapping_source_filters WHERE mapping_set_id = :id');
            $statement->execute(['id' => $mappingSetId]);

            $insert = $pdo->prepare(
                'INSERT INTO luna_mapping_source_filters
                 (mapping_set_id, source_column, operator, filter_value, value_type, sort_order, created_at, updated_at)
                 VALUES (:mapping_set_id, :source_column, :operator, :filter_value, :value_type, :sort_order, NOW(), NOW())',
            );

            foreach ($filters as $index => $filter) {
                $normalized = $this->sourceFilterPayload($filter, $index);
                if ($normalized === null) {
                    continue;
                }

                $normalized['mapping_set_id'] = $mappingSetId;
                $insert->execute($normalized);
            }

            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function deleteSet(int $id): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $statement = $pdo->prepare(
                'DELETE FROM luna_mapping_value_rules WHERE mapping_field_id IN (SELECT id FROM luna_mapping_fields WHERE mapping_set_id = :id)',
            );
            $statement->execute(['id' => $id]);
            $statement = $pdo->prepare('DELETE FROM luna_mapping_source_filters WHERE mapping_set_id = :id');
            $statement->execute(['id' => $id]);
            $statement = $pdo->prepare('DELETE FROM luna_mapping_fields WHERE mapping_set_id = :id');
            $statement->execute(['id' => $id]);
            $statement = $pdo->prepare('DELETE FROM luna_mapping_sets WHERE id = :id');
            $statement->execute(['id' => $id]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function canDeleteSet(int $id): DeleteCheckResult
    {
        $endpointNames = $this->endpointNamesForMapping($id);
        if ($endpointNames !== []) {
            return DeleteCheckResult::blocked(
                'Dieses Mapping kann nicht gelöscht werden, weil es noch von Endpoint(s) verwendet wird.',
                $endpointNames,
                ['endpoints' => count($endpointNames)],
            );
        }

        $jobNames = $this->jobNamesForMapping($id);
        if ($jobNames !== []) {
            return DeleteCheckResult::blocked(
                'Dieses Mapping kann nicht gelöscht werden, weil es noch von Job(s) verwendet wird.',
                $jobNames,
                ['jobs' => count($jobNames)],
            );
        }

        return DeleteCheckResult::allowed();
    }

    public function fieldsForSet(int $mappingSetId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT * FROM luna_mapping_fields WHERE mapping_set_id = :id ORDER BY sort_order, id',
        );
        $statement->execute(['id' => $mappingSetId]);

        return $statement->fetchAll();
    }

    public function addField(int $mappingSetId, array $data): int
    {
        $payload = $this->fieldPayload($data);
        $payload['mapping_set_id'] = $mappingSetId;
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_mapping_fields
             (mapping_set_id, source_column, source_json_path, target_column, transform_type, default_value,
              lookup_connection_id, lookup_table, lookup_key_column, lookup_value_column, lookup_key_template, fallback_value, missing_behavior,
              is_required, notes, sort_order, created_at, updated_at)
             VALUES (:mapping_set_id, :source_column, :source_json_path, :target_column, :transform_type, :default_value,
              :lookup_connection_id, :lookup_table, :lookup_key_column, :lookup_value_column, :lookup_key_template, :fallback_value, :missing_behavior,
              :is_required, :notes, :sort_order, NOW(), NOW())',
        );
        $statement->execute($payload);

        return (int) $this->pdo()->lastInsertId();
    }

    public function updateField(int $fieldId, array $data): void
    {
        $payload = $this->fieldPayload($data);
        $payload['id'] = $fieldId;
        $statement = $this->pdo()->prepare(
            'UPDATE luna_mapping_fields
             SET source_column = :source_column,
                 source_json_path = :source_json_path,
                 target_column = :target_column,
                 transform_type = :transform_type,
                 default_value = :default_value,
                 lookup_connection_id = :lookup_connection_id,
                 lookup_table = :lookup_table,
                 lookup_key_column = :lookup_key_column,
                 lookup_value_column = :lookup_value_column,
                 lookup_key_template = :lookup_key_template,
                 fallback_value = :fallback_value,
                 missing_behavior = :missing_behavior,
                 is_required = :is_required,
                 notes = :notes,
                 sort_order = :sort_order,
                 updated_at = NOW()
             WHERE id = :id',
        );
        $statement->execute($payload);
    }

    public function deleteField(int $fieldId): void
    {
        $statement = $this->pdo()->prepare('DELETE FROM luna_mapping_fields WHERE id = :id');
        $statement->execute(['id' => $fieldId]);
    }

    public function findField(int $fieldId): ?array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM luna_mapping_fields WHERE id = :id');
        $statement->execute(['id' => $fieldId]);
        $field = $statement->fetch();

        return $field === false ? null : $field;
    }

    public function valueRulesForField(int $mappingFieldId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT * FROM luna_mapping_value_rules WHERE mapping_field_id = :id ORDER BY source_value, id',
        );
        $statement->execute(['id' => $mappingFieldId]);

        return $statement->fetchAll();
    }

    public function addValueRule(int $mappingFieldId, string $sourceValue, string $targetValue, ?string $notes = null): int
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO luna_mapping_value_rules
             (mapping_field_id, source_value, target_value, notes, created_at, updated_at)
             VALUES (:mapping_field_id, :source_value, :target_value, :notes, NOW(), NOW())',
        );
        $statement->execute([
            'mapping_field_id' => $mappingFieldId,
            'source_value' => $sourceValue,
            'target_value' => $targetValue,
            'notes' => $notes,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function updateValueRule(int $ruleId, string $sourceValue, string $targetValue, ?string $notes = null): void
    {
        $statement = $this->pdo()->prepare(
            'UPDATE luna_mapping_value_rules
             SET source_value = :source_value, target_value = :target_value, notes = :notes, updated_at = NOW()
             WHERE id = :id',
        );
        $statement->execute([
            'id' => $ruleId,
            'source_value' => $sourceValue,
            'target_value' => $targetValue,
            'notes' => $notes,
        ]);
    }

    public function deleteValueRule(int $ruleId): void
    {
        $statement = $this->pdo()->prepare('DELETE FROM luna_mapping_value_rules WHERE id = :id');
        $statement->execute(['id' => $ruleId]);
    }

    private function setPayload(array $data): array
    {
        return [
            'workspace_id' => empty($data['workspace_id']) ? null : (int) $data['workspace_id'],
            'name' => trim((string) ($data['name'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'mapping_mode' => in_array((string) ($data['mapping_mode'] ?? 'transfer'), ['transfer', 'json_endpoint'], true)
                ? (string) ($data['mapping_mode'] ?? 'transfer')
                : 'transfer',
            'source_connection_id' => empty($data['source_connection_id']) ? null : (int) $data['source_connection_id'],
            'source_table' => trim((string) ($data['source_table'] ?? '')) ?: null,
            'target_connection_id' => empty($data['target_connection_id']) ? null : (int) $data['target_connection_id'],
            'target_table' => trim((string) ($data['target_table'] ?? '')) ?: null,
            'status' => trim((string) ($data['status'] ?? 'draft')) ?: 'draft',
        ];
    }

    private function fieldPayload(array $data): array
    {
        $targetColumn = trim((string) ($data['target_column'] ?? ''));
        if ($targetColumn === '' || preg_match('/^[A-Za-z0-9_]+$/', $targetColumn) !== 1) {
            throw new \InvalidArgumentException('Invalid target_column.');
        }

        return [
            'source_column' => trim((string) ($data['source_column'] ?? '')) ?: null,
            'source_json_path' => trim((string) ($data['source_json_path'] ?? '')) ?: null,
            'target_column' => $targetColumn,
            'transform_type' => trim((string) ($data['transform_type'] ?? 'direct')) ?: 'direct',
            'default_value' => array_key_exists('default_value', $data) ? (string) $data['default_value'] : null,
            'lookup_connection_id' => empty($data['lookup_connection_id']) ? null : (int) $data['lookup_connection_id'],
            'lookup_table' => trim((string) ($data['lookup_table'] ?? '')) ?: null,
            'lookup_key_column' => trim((string) ($data['lookup_key_column'] ?? '')) ?: null,
            'lookup_value_column' => trim((string) ($data['lookup_value_column'] ?? '')) ?: null,
            'lookup_key_template' => trim((string) ($data['lookup_key_template'] ?? '')) ?: null,
            'fallback_value' => array_key_exists('fallback_value', $data) ? (string) $data['fallback_value'] : null,
            'missing_behavior' => in_array((string) ($data['missing_behavior'] ?? 'error'), ['error', 'warning', 'fallback', 'nullable'], true)
                ? (string) ($data['missing_behavior'] ?? 'error')
                : 'error',
            'is_required' => ! empty($data['is_required']) ? 1 : 0,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $filter
     *
     * @return array<string, mixed>|null
     */
    private function sourceFilterPayload(array $filter, int $fallbackSortOrder): ?array
    {
        $column = trim((string) ($filter['source_column'] ?? ''));
        $operator = trim((string) ($filter['operator'] ?? 'none'));

        if ($column === '' || $operator === 'none') {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9_]+$/', $column) !== 1) {
            throw new \InvalidArgumentException('Invalid source filter column.');
        }

        if (! in_array($operator, \Luna\Transfer\MappingSourceRowProvider::operators(), true)) {
            throw new \InvalidArgumentException('Invalid source filter operator.');
        }

        return [
            'source_column' => $column,
            'operator' => $operator,
            'filter_value' => (string) ($filter['filter_value'] ?? ''),
            'value_type' => trim((string) ($filter['value_type'] ?? '')) ?: null,
            'sort_order' => (int) ($filter['sort_order'] ?? $fallbackSortOrder),
        ];
    }

    /**
     * @return list<string>
     */
    private function endpointNamesForMapping(int $mappingSetId): array
    {
        $statement = $this->pdo()->prepare('SELECT name FROM luna_endpoints WHERE mapping_set_id = :id ORDER BY name');
        $statement->execute(['id' => $mappingSetId]);

        return array_values(array_map(static fn (array $row): string => (string) $row['name'], $statement->fetchAll()));
    }

    /**
     * @return list<string>
     */
    private function jobNamesForMapping(int $mappingSetId): array
    {
        $statement = $this->pdo()->prepare('SELECT name FROM luna_jobs WHERE mapping_set_id = :id ORDER BY name');
        $statement->execute(['id' => $mappingSetId]);

        return array_values(array_map(static fn (array $row): string => (string) $row['name'], $statement->fetchAll()));
    }

    private function pdo(): PDO
    {
        return $this->pdo ?? $this->database->pdo();
    }
}
