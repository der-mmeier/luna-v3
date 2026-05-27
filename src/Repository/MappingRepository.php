<?php

declare(strict_types=1);

namespace Luna\Repository;

use Luna\Database\SystemDatabase;

final class MappingRepository
{
    public function __construct(
        private readonly SystemDatabase $database,
    ) {
    }

    public function all(): array
    {
        $statement = $this->database->pdo()->query(
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
        $statement = $this->database->pdo()->prepare(
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
        $statement = $this->database->pdo()->prepare(
            'INSERT INTO luna_mapping_sets
             (workspace_id, name, description, source_connection_id, source_table, target_connection_id, target_table, status, created_at, updated_at)
             VALUES (:workspace_id, :name, :description, :source_connection_id, :source_table, :target_connection_id, :target_table, :status, NOW(), NOW())',
        );
        $statement->execute($this->setPayload($data));

        return (int) $this->database->pdo()->lastInsertId();
    }

    public function updateSet(int $id, array $data): void
    {
        $payload = $this->setPayload($data);
        $payload['id'] = $id;
        $statement = $this->database->pdo()->prepare(
            'UPDATE luna_mapping_sets
             SET workspace_id = :workspace_id,
                 name = :name,
                 description = :description,
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

    public function deleteSet(int $id): void
    {
        $statement = $this->database->pdo()->prepare('DELETE FROM luna_mapping_sets WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function fieldsForSet(int $mappingSetId): array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT * FROM luna_mapping_fields WHERE mapping_set_id = :id ORDER BY sort_order, id',
        );
        $statement->execute(['id' => $mappingSetId]);

        return $statement->fetchAll();
    }

    public function addField(int $mappingSetId, array $data): int
    {
        $payload = $this->fieldPayload($data);
        $payload['mapping_set_id'] = $mappingSetId;
        $statement = $this->database->pdo()->prepare(
            'INSERT INTO luna_mapping_fields
             (mapping_set_id, source_column, source_json_path, target_column, transform_type, default_value,
              lookup_connection_id, lookup_table, lookup_key_column, lookup_value_column, lookup_key_template, fallback_value, missing_behavior,
              is_required, notes, sort_order, created_at, updated_at)
             VALUES (:mapping_set_id, :source_column, :source_json_path, :target_column, :transform_type, :default_value,
              :lookup_connection_id, :lookup_table, :lookup_key_column, :lookup_value_column, :lookup_key_template, :fallback_value, :missing_behavior,
              :is_required, :notes, :sort_order, NOW(), NOW())',
        );
        $statement->execute($payload);

        return (int) $this->database->pdo()->lastInsertId();
    }

    public function updateField(int $fieldId, array $data): void
    {
        $payload = $this->fieldPayload($data);
        $payload['id'] = $fieldId;
        $statement = $this->database->pdo()->prepare(
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
        $statement = $this->database->pdo()->prepare('DELETE FROM luna_mapping_fields WHERE id = :id');
        $statement->execute(['id' => $fieldId]);
    }

    public function findField(int $fieldId): ?array
    {
        $statement = $this->database->pdo()->prepare('SELECT * FROM luna_mapping_fields WHERE id = :id');
        $statement->execute(['id' => $fieldId]);
        $field = $statement->fetch();

        return $field === false ? null : $field;
    }

    public function valueRulesForField(int $mappingFieldId): array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT * FROM luna_mapping_value_rules WHERE mapping_field_id = :id ORDER BY source_value, id',
        );
        $statement->execute(['id' => $mappingFieldId]);

        return $statement->fetchAll();
    }

    public function addValueRule(int $mappingFieldId, string $sourceValue, string $targetValue, ?string $notes = null): int
    {
        $statement = $this->database->pdo()->prepare(
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

        return (int) $this->database->pdo()->lastInsertId();
    }

    public function updateValueRule(int $ruleId, string $sourceValue, string $targetValue, ?string $notes = null): void
    {
        $statement = $this->database->pdo()->prepare(
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
        $statement = $this->database->pdo()->prepare('DELETE FROM luna_mapping_value_rules WHERE id = :id');
        $statement->execute(['id' => $ruleId]);
    }

    private function setPayload(array $data): array
    {
        return [
            'workspace_id' => empty($data['workspace_id']) ? null : (int) $data['workspace_id'],
            'name' => trim((string) ($data['name'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'source_connection_id' => empty($data['source_connection_id']) ? null : (int) $data['source_connection_id'],
            'source_table' => trim((string) ($data['source_table'] ?? '')) ?: null,
            'target_connection_id' => empty($data['target_connection_id']) ? null : (int) $data['target_connection_id'],
            'target_table' => trim((string) ($data['target_table'] ?? '')) ?: null,
            'status' => trim((string) ($data['status'] ?? 'draft')) ?: 'draft',
        ];
    }

    private function fieldPayload(array $data): array
    {
        return [
            'source_column' => trim((string) ($data['source_column'] ?? '')) ?: null,
            'source_json_path' => trim((string) ($data['source_json_path'] ?? '')) ?: null,
            'target_column' => trim((string) ($data['target_column'] ?? '')),
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
}
