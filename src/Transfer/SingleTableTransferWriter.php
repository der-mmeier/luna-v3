<?php

declare(strict_types=1);

namespace Luna\Transfer;

use PDO;
use RuntimeException;
use Throwable;

final class SingleTableTransferWriter
{
    /**
     * @param list<array{operation: string, key: array<string, mixed>, data: array<string, mixed>}> $operations
     */
    public function write(PDO $pdo, string $targetTable, array $operations): int
    {
        return $this->writeGroups($pdo, [[
            'target_table' => $targetTable,
            'operations' => $operations,
        ]]);
    }

    /**
     * @param list<array{target_table: string, operations: list<array{operation: string, key: array<string, mixed>, data: array<string, mixed>}>}> $groups
     */
    public function writeGroups(PDO $pdo, array $groups): int
    {
        $startedTransaction = ! $pdo->inTransaction();
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $written = 0;
            foreach ($groups as $group) {
                foreach ($group['operations'] as $operation) {
                    $type = (string) $operation['operation'];
                    $targetTable = $group['target_table'];
                    if ($type === 'insert') {
                        $this->insert($pdo, $targetTable, $operation['data']);
                        $written++;
                        continue;
                    }

                    if ($type === 'update') {
                        $written += $this->update($pdo, $targetTable, $operation['key'], $operation['data']);
                        continue;
                    }

                    if ($this->exists($pdo, $targetTable, $operation['key'])) {
                        $written += $this->update($pdo, $targetTable, $operation['key'], $operation['data']);
                    } else {
                        $this->insert($pdo, $targetTable, $operation['data']);
                        $written++;
                    }
                }
            }

            if ($startedTransaction) {
                $pdo->commit();
            }

            return $written;
        } catch (Throwable $exception) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw new RuntimeException('Transfer konnte nicht geschrieben werden.', 0, $exception);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function insert(PDO $pdo, string $table, array $row): void
    {
        $columns = array_keys($row);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($table),
            implode(', ', array_map(fn (string $column): string => $this->quoteIdentifier($column), $columns)),
            implode(', ', $placeholders),
        );

        $statement = $pdo->prepare($sql);
        $statement->execute($this->payload($row));
    }

    /**
     * @param array<string, mixed> $key
     * @param array<string, mixed> $row
     */
    private function update(PDO $pdo, string $table, array $key, array $row): int
    {
        $data = array_diff_key($row, $key);
        if ($data === []) {
            return 0;
        }

        $assignments = [];
        foreach (array_keys($data) as $column) {
            $assignments[] = $this->quoteIdentifier($column) . ' = :set_' . $column;
        }

        $where = [];
        foreach (array_keys($key) as $column) {
            $where[] = $this->quoteIdentifier($column) . ' = :key_' . $column;
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $this->quoteIdentifier($table),
            implode(', ', $assignments),
            implode(' AND ', $where),
        );
        $statement = $pdo->prepare($sql);
        $statement->execute($this->prefixedPayload('set_', $data) + $this->prefixedPayload('key_', $key));

        return 1;
    }

    /**
     * @param array<string, mixed> $key
     */
    private function exists(PDO $pdo, string $table, array $key): bool
    {
        $where = [];
        foreach (array_keys($key) as $column) {
            $where[] = $this->quoteIdentifier($column) . ' = :key_' . $column;
        }

        $statement = $pdo->prepare(sprintf(
            'SELECT 1 FROM %s WHERE %s LIMIT 1',
            $this->quoteIdentifier($table),
            implode(' AND ', $where),
        ));
        $statement->execute($this->prefixedPayload('key_', $key));

        return $statement->fetchColumn() !== false;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function payload(array $row): array
    {
        $payload = [];
        foreach ($row as $column => $value) {
            $payload[$column] = $this->normalizeValue($value);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function prefixedPayload(string $prefix, array $row): array
    {
        $payload = [];
        foreach ($row as $column => $value) {
            $payload[$prefix . $column] = $this->normalizeValue($value);
        }

        return $payload;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }

        return $value;
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
            throw new RuntimeException('Ungültiger Tabellen- oder Spaltenname.');
        }

        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
