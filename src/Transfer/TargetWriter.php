<?php

declare(strict_types=1);

namespace Luna\Transfer;

use PDO;
use RuntimeException;

final class TargetWriter
{
    public function insertRows(PDO $pdo, string $targetTable, array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $columns = array_keys($rows[0]);
        $table = $this->quoteIdentifier($targetTable);
        $quotedColumns = array_map(fn (string $column): string => $this->quoteIdentifier($column), $columns);
        $placeholders = array_map(fn (string $column): string => ':' . $column, $columns);
        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, implode(', ', $quotedColumns), implode(', ', $placeholders));
        $statement = $pdo->prepare($sql);

        $pdo->beginTransaction();
        try {
            $written = 0;
            foreach ($rows as $row) {
                $payload = [];
                foreach ($columns as $column) {
                    $payload[$column] = $row[$column] ?? null;
                }
                $statement->execute($payload);
                $written++;
            }
            $pdo->commit();
            return $written;
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw new RuntimeException('Target insert failed.');
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
            throw new RuntimeException('Invalid SQL identifier.');
        }
        return '`' . $identifier . '`';
    }
}
