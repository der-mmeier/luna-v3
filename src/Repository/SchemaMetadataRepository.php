<?php

declare(strict_types=1);

namespace Luna\Repository;

use Luna\Database\SystemDatabase;

final class SchemaMetadataRepository
{
    public function __construct(
        private readonly SystemDatabase $database,
    ) {
    }

    public function tableNote(int $connectionId, ?string $schemaName, string $tableName): ?array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT * FROM luna_table_notes
             WHERE connection_profile_id = :connection_id
               AND schema_name <=> :schema_name
               AND table_name = :table_name
             LIMIT 1',
        );
        $statement->execute(['connection_id' => $connectionId, 'schema_name' => $schemaName, 'table_name' => $tableName]);
        $note = $statement->fetch();

        return $note === false ? null : $note;
    }

    public function saveTableNote(int $connectionId, ?int $workspaceId, ?string $schemaName, string $tableName, ?string $note): void
    {
        $existing = $this->tableNote($connectionId, $schemaName, $tableName);

        if ($existing === null) {
            $statement = $this->database->pdo()->prepare(
                'INSERT INTO luna_table_notes (workspace_id, connection_profile_id, schema_name, table_name, note, created_at, updated_at)
                 VALUES (:workspace_id, :connection_id, :schema_name, :table_name, :note, NOW(), NOW())',
            );
        } else {
            $statement = $this->database->pdo()->prepare(
                'UPDATE luna_table_notes
                 SET workspace_id = :workspace_id, note = :note, updated_at = NOW()
                 WHERE connection_profile_id = :connection_id
                   AND schema_name <=> :schema_name
                   AND table_name = :table_name',
            );
        }

        $statement->execute([
            'workspace_id' => $workspaceId,
            'connection_id' => $connectionId,
            'schema_name' => $schemaName,
            'table_name' => $tableName,
            'note' => $note,
        ]);
    }

    public function columnNote(int $connectionId, ?string $schemaName, string $tableName, string $columnName): ?array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT * FROM luna_column_notes
             WHERE connection_profile_id = :connection_id
               AND schema_name <=> :schema_name
               AND table_name = :table_name
               AND column_name = :column_name
             LIMIT 1',
        );
        $statement->execute([
            'connection_id' => $connectionId,
            'schema_name' => $schemaName,
            'table_name' => $tableName,
            'column_name' => $columnName,
        ]);
        $note = $statement->fetch();

        return $note === false ? null : $note;
    }

    public function saveColumnNote(
        int $connectionId,
        ?int $workspaceId,
        ?string $schemaName,
        string $tableName,
        string $columnName,
        ?string $note,
        ?string $exampleValue = null,
    ): void {
        $existing = $this->columnNote($connectionId, $schemaName, $tableName, $columnName);

        if ($existing === null) {
            $statement = $this->database->pdo()->prepare(
                'INSERT INTO luna_column_notes
                 (workspace_id, connection_profile_id, schema_name, table_name, column_name, note, example_value, created_at, updated_at)
                 VALUES (:workspace_id, :connection_id, :schema_name, :table_name, :column_name, :note, :example_value, NOW(), NOW())',
            );
        } else {
            $statement = $this->database->pdo()->prepare(
                'UPDATE luna_column_notes
                 SET workspace_id = :workspace_id, note = :note, example_value = :example_value, updated_at = NOW()
                 WHERE connection_profile_id = :connection_id
                   AND schema_name <=> :schema_name
                   AND table_name = :table_name
                   AND column_name = :column_name',
            );
        }

        $statement->execute([
            'workspace_id' => $workspaceId,
            'connection_id' => $connectionId,
            'schema_name' => $schemaName,
            'table_name' => $tableName,
            'column_name' => $columnName,
            'note' => $note,
            'example_value' => $exampleValue,
        ]);
    }
}
