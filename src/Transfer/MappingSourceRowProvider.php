<?php

declare(strict_types=1);

namespace Luna\Transfer;

use PDO;
use RuntimeException;

final class MappingSourceRowProvider
{
    private const OPERATORS = [
        'equals',
        'not_equals',
        'contains',
        'not_contains',
        'starts_with',
        'not_starts_with',
        'ends_with',
        'not_ends_with',
        'like',
        'not_like',
        'is_empty',
        'is_not_empty',
        'numeric_equals',
        'numeric_not_equals',
        'numeric_greater_than',
        'numeric_greater_or_equal',
        'numeric_less_than',
        'numeric_less_or_equal',
        'in',
        'not_in',
        'none',
        'is_numeric_gt_zero',
        'numeric_gt',
        'gt',
        'gte',
        'eq',
    ];

    /**
     * @param array<string, mixed> $mappingSet
     *
     * @return list<array<string, mixed>>
     */
    public function rows(PDO $pdo, string $tableName, array $mappingSet, ?int $limit = null): array
    {
        $this->assertIdentifier($tableName);
        $filters = $this->filtersFromMappingSet($mappingSet);

        if ($filters === []) {
            return $this->unfilteredRows($pdo, $tableName, $limit);
        }

        foreach ($filters as $filter) {
            $this->assertIdentifier($filter['source_column']);
        }

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            return $this->filterRowsInPhp($this->unfilteredRows($pdo, $tableName, null), $filters, $limit);
        }

        $whereParts = [];
        $params = [];
        foreach ($filters as $index => $filter) {
            $whereParts[] = $this->whereSql($this->quoteIdentifier($filter['source_column']), $filter, $index, $params);
        }

        $limitSql = $limit === null ? '' : sprintf(' LIMIT %d', max(1, min($limit, 1000)));
        $statement = $pdo->prepare(sprintf(
            'SELECT * FROM %s WHERE %s%s',
            $this->quoteIdentifier($tableName),
            implode(' AND ', array_map(static fn (string $part): string => '(' . $part . ')', $whereParts)),
            $limitSql,
        ));
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /**
     * @param array<string, mixed> $mappingSet
     *
     * @return list<array{source_column: string, operator: string, filter_value: string, sort_order: int}>
     */
    public function filtersFromMappingSet(array $mappingSet): array
    {
        $filters = [];
        if (isset($mappingSet['source_filters']) && is_array($mappingSet['source_filters'])) {
            foreach ($mappingSet['source_filters'] as $index => $filter) {
                if (! is_array($filter)) {
                    continue;
                }
                $normalized = $this->normalizeFilter($filter, $index);
                if ($normalized !== null) {
                    $filters[] = $normalized;
                }
            }
        }

        if ($filters !== []) {
            return $filters;
        }

        $legacy = $this->normalizeFilter([
            'source_column' => $mappingSet['source_filter_column'] ?? '',
            'operator' => $mappingSet['source_filter_operator'] ?? 'none',
            'filter_value' => $mappingSet['source_filter_value'] ?? '',
        ], 0);

        return $legacy === null ? [] : [$legacy];
    }

    /**
     * @return list<string>
     */
    public static function operators(): array
    {
        return self::OPERATORS;
    }

    /**
     * @param array<string, mixed> $filter
     *
     * @return array{source_column: string, operator: string, filter_value: string, sort_order: int}|null
     */
    private function normalizeFilter(array $filter, int $fallbackSortOrder): ?array
    {
        $column = trim((string) ($filter['source_column'] ?? ''));
        $operator = $this->normalizeOperator((string) ($filter['operator'] ?? $filter['source_filter_operator'] ?? 'none'));

        if ($column === '' || $operator === 'none') {
            return null;
        }

        return [
            'source_column' => $column,
            'operator' => $operator,
            'filter_value' => $operator === 'is_numeric_gt_zero' ? '0' : (string) ($filter['filter_value'] ?? $filter['source_filter_value'] ?? ''),
            'sort_order' => (int) ($filter['sort_order'] ?? $fallbackSortOrder),
        ];
    }

    private function normalizeOperator(string $operator): string
    {
        return match ($operator) {
            'is_numeric_gt_zero', 'numeric_gt', 'gt' => 'numeric_greater_than',
            'gte' => 'numeric_greater_or_equal',
            'eq' => 'equals',
            default => in_array($operator, self::OPERATORS, true) ? $operator : 'none',
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function unfilteredRows(PDO $pdo, string $tableName, ?int $limit): array
    {
        $limitSql = $limit === null ? '' : sprintf(' LIMIT %d', max(1, min($limit, 1000)));

        return $pdo->query(sprintf('SELECT * FROM %s%s', $this->quoteIdentifier($tableName), $limitSql))->fetchAll();
    }

    /**
     * @param array{source_column: string, operator: string, filter_value: string, sort_order: int} $filter
     * @param array<string, mixed> $params
     */
    private function whereSql(string $column, array $filter, int $index, array &$params): string
    {
        $operator = $filter['operator'];
        $value = $filter['filter_value'];
        $placeholder = 'filter_' . $index;

        if ($operator === 'is_empty') {
            return sprintf('%s IS NULL OR %s = \'\'', $column, $column);
        }

        if ($operator === 'is_not_empty') {
            return sprintf('%s IS NOT NULL AND %s <> \'\'', $column, $column);
        }

        if (str_starts_with($operator, 'numeric_')) {
            $params[$placeholder] = $operator === 'is_numeric_gt_zero' ? '0' : $value;
            $comparison = [
                'numeric_equals' => '=',
                'numeric_not_equals' => '<>',
                'numeric_greater_than' => '>',
                'numeric_greater_or_equal' => '>=',
                'numeric_less_than' => '<',
                'numeric_less_or_equal' => '<=',
            ][$operator] ?? '>';

            return sprintf(
                "TRIM(CAST(%s AS CHAR)) REGEXP '^-?[0-9]+(\\\\.[0-9]+)?$' AND CAST(%s AS DECIMAL(20,6)) %s :%s",
                $column,
                $column,
                $comparison,
                $placeholder,
            );
        }

        if (in_array($operator, ['contains', 'not_contains', 'starts_with', 'not_starts_with', 'ends_with', 'not_ends_with', 'like', 'not_like'], true)) {
            $params[$placeholder] = $this->likeValue($operator, $value);
            $not = str_starts_with($operator, 'not_') ? 'NOT ' : '';
            $sql = sprintf('%s %sLIKE :%s ESCAPE \'\\\\\'', $column, $not, $placeholder);

            return str_starts_with($operator, 'not_') ? sprintf('(%s OR %s IS NULL)', $sql, $column) : $sql;
        }

        if (in_array($operator, ['in', 'not_in'], true)) {
            $values = $this->listValues($value);
            if ($values === []) {
                return $operator === 'in' ? '1 = 0' : '1 = 1';
            }

            $placeholders = [];
            foreach ($values as $valueIndex => $item) {
                $itemPlaceholder = sprintf('%s_%d', $placeholder, $valueIndex);
                $placeholders[] = ':' . $itemPlaceholder;
                $params[$itemPlaceholder] = $item;
            }

            $sql = sprintf('%s %sIN (%s)', $column, $operator === 'not_in' ? 'NOT ' : '', implode(', ', $placeholders));

            return $operator === 'not_in' ? sprintf('(%s OR %s IS NULL)', $sql, $column) : $sql;
        }

        $params[$placeholder] = $value;

        return match ($operator) {
            'not_equals' => sprintf('(%s <> :%s OR %s IS NULL)', $column, $placeholder, $column),
            default => sprintf('%s = :%s', $column, $placeholder),
        };
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<array{source_column: string, operator: string, filter_value: string, sort_order: int}> $filters
     *
     * @return list<array<string, mixed>>
     */
    private function filterRowsInPhp(array $rows, array $filters, ?int $limit): array
    {
        $filtered = [];
        foreach ($rows as $row) {
            foreach ($filters as $filter) {
                if (! $this->rowMatches($row[$filter['source_column']] ?? null, $filter)) {
                    continue 2;
                }
            }

            $filtered[] = $row;
            if ($limit !== null && count($filtered) >= max(1, min($limit, 1000))) {
                break;
            }
        }

        return $filtered;
    }

    /**
     * @param array{source_column: string, operator: string, filter_value: string, sort_order: int} $filter
     */
    private function rowMatches(mixed $rawValue, array $filter): bool
    {
        $operator = $filter['operator'];
        $rawText = $rawValue === null ? null : (string) $rawValue;
        $value = $rawText === null ? null : trim($rawText);

        if ($operator === 'is_empty') {
            return $rawValue === null || $rawText === '';
        }

        if ($operator === 'is_not_empty') {
            return $rawValue !== null && $rawText !== '';
        }

        if ($value === null || $value === '') {
            return in_array($operator, ['not_equals', 'not_contains', 'not_starts_with', 'not_ends_with', 'not_like', 'not_in'], true);
        }

        $filterValue = $filter['filter_value'];

        if (str_starts_with($operator, 'numeric_')) {
            if (! is_numeric($value)) {
                return false;
            }
            $left = (float) $value;
            $right = (float) $filterValue;

            return match ($operator) {
                'numeric_equals' => $left === $right,
                'numeric_not_equals' => $left !== $right,
                'numeric_greater_than' => $left > $right,
                'numeric_greater_or_equal' => $left >= $right,
                'numeric_less_than' => $left < $right,
                'numeric_less_or_equal' => $left <= $right,
                default => false,
            };
        }

        return match ($operator) {
            'equals' => $value === $filterValue,
            'not_equals' => $value !== $filterValue,
            'contains' => str_contains($value, $filterValue),
            'not_contains' => ! str_contains($value, $filterValue),
            'starts_with' => str_starts_with($value, $filterValue),
            'not_starts_with' => ! str_starts_with($value, $filterValue),
            'ends_with' => str_ends_with($value, $filterValue),
            'not_ends_with' => ! str_ends_with($value, $filterValue),
            'like' => $this->likeMatches($value, $filterValue),
            'not_like' => ! $this->likeMatches($value, $filterValue),
            'in' => in_array($value, $this->listValues($filterValue), true),
            'not_in' => ! in_array($value, $this->listValues($filterValue), true),
            default => true,
        };
    }

    private function likeValue(string $operator, string $value): string
    {
        $escaped = $operator === 'like' || $operator === 'not_like'
            ? str_replace('\\', '\\\\', $value)
            : $this->escapeLike($value);

        return match ($operator) {
            'contains', 'not_contains' => '%' . $escaped . '%',
            'starts_with', 'not_starts_with' => $escaped . '%',
            'ends_with', 'not_ends_with' => '%' . $escaped,
            default => $escaped,
        };
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function likeMatches(string $value, string $pattern): bool
    {
        $quoted = preg_quote($pattern, '/');
        $regex = '/^' . str_replace(['%', '_'], ['.*', '.'], $quoted) . '$/u';

        return preg_match($regex, $value) === 1;
    }

    /**
     * @return list<string>
     */
    private function listValues(string $value): array
    {
        return array_values(array_filter(array_map(static fn (string $part): string => trim($part), explode(',', $value)), static fn (string $part): bool => $part !== ''));
    }

    private function assertIdentifier(string $identifier): void
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
            throw new RuntimeException('Invalid source identifier.');
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        $this->assertIdentifier($identifier);

        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
