<?php

declare(strict_types=1);

namespace Luna\Schema;

use PDO;
use RuntimeException;

final class SchemaInspector
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function tables(?string $schemaName = null): array
    {
        $schemaName ??= $this->currentDatabase();
        $statement = $this->pdo->prepare(
            'SELECT TABLE_NAME AS table_name, TABLE_TYPE AS table_type, TABLE_ROWS AS table_rows, TABLE_COMMENT AS table_comment
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = :schema
             ORDER BY TABLE_NAME',
        );
        $statement->execute(['schema' => $schemaName]);

        return $statement->fetchAll();
    }

    public function columns(string $tableName, ?string $schemaName = null): array
    {
        $this->assertIdentifier($tableName);
        $schemaName ??= $this->currentDatabase();
        $statement = $this->pdo->prepare(
            'SELECT COLUMN_NAME AS column_name, DATA_TYPE AS data_type, COLUMN_TYPE AS column_type,
                    IS_NULLABLE AS is_nullable, COLUMN_DEFAULT AS column_default, COLUMN_KEY AS column_key,
                    EXTRA AS extra, COLUMN_COMMENT AS column_comment
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table
             ORDER BY ORDINAL_POSITION',
        );
        $statement->execute(['schema' => $schemaName, 'table' => $tableName]);

        return $statement->fetchAll();
    }

    private function currentDatabase(): string
    {
        $database = $this->pdo->query('SELECT DATABASE()')->fetchColumn();

        if (! is_string($database) || $database === '') {
            throw new RuntimeException('No database selected.');
        }

        return $database;
    }

    private function assertIdentifier(string $identifier): void
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
            throw new RuntimeException('Invalid table identifier.');
        }
    }
}
