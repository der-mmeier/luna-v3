<?php

declare(strict_types=1);

namespace Luna\Schema;

use PDO;
use RuntimeException;

final class SampleDataReader
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function sampleRows(string $tableName, ?string $schemaName = null, int $limit = 5): array
    {
        $limit = max(1, min($limit, 25));
        $table = $this->qualifiedTableName($tableName, $schemaName);
        $statement = $this->pdo->query(sprintf('SELECT * FROM %s LIMIT %d', $table, $limit));

        return $statement->fetchAll();
    }

    private function qualifiedTableName(string $tableName, ?string $schemaName): string
    {
        $table = $this->quoteIdentifier($tableName);

        if ($schemaName === null || $schemaName === '') {
            return $table;
        }

        return $this->quoteIdentifier($schemaName) . '.' . $table;
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
            throw new RuntimeException('Invalid SQL identifier.');
        }

        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
