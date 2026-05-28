<?php

declare(strict_types=1);

namespace Luna\Mapping;

use Luna\Connections\ExternalDatabaseConfig;
use Luna\Connections\ExternalPdoConnectionFactory;
use Luna\Repository\ConnectionProfileRepository;
use PDO;
use Throwable;

final class PdoLookupValueProvider implements LookupValueProvider
{
    public function __construct(
        private readonly ConnectionProfileRepository $connections,
        private readonly ExternalPdoConnectionFactory $pdoFactory,
    ) {
    }

    public function lookup(array $field, string $key): LookupResult
    {
        $connectionId = (int) ($field['lookup_connection_id'] ?? 0);
        $table = (string) ($field['lookup_table'] ?? '');
        $keyColumn = (string) ($field['lookup_key_column'] ?? '');
        $valueColumn = (string) ($field['lookup_value_column'] ?? '');
        $matchMode = LookupMatchMode::normalize(isset($field['lookup_match_mode']) ? (string) $field['lookup_match_mode'] : null);
        $resultMode = LookupResultMode::normalize(isset($field['lookup_result_mode']) ? (string) $field['lookup_result_mode'] : null);
        $resultKeyColumn = (string) ($field['lookup_result_key_column'] ?? '');
        $resultKeyTransform = LookupResultMode::normalizeKeyTransform(isset($field['lookup_result_key_transform']) ? (string) $field['lookup_result_key_transform'] : null);
        $limit = $this->resultLimit($field);

        if (! $this->validIdentifier($table)) {
            return LookupResult::error('lookup_table_not_found');
        }

        if (! $this->validIdentifier($keyColumn)) {
            return LookupResult::error('lookup_key_column_not_found');
        }

        if (! $this->validIdentifier($valueColumn)) {
            return LookupResult::error('lookup_value_column_not_found');
        }

        if ($resultMode === 'key_value_map' && ! $this->validIdentifier($resultKeyColumn)) {
            return LookupResult::error('missing_result_key_column');
        }

        if (! LookupMatchMode::hasSearchValue($matchMode, $key)) {
            return LookupResult::error('lookup_key_empty');
        }

        try {
            $profile = $this->connections->find($connectionId);

            if ($profile === null) {
                return LookupResult::error('lookup_connection_unavailable');
            }

            $pdo = $this->pdoFactory->create(
                ExternalDatabaseConfig::fromProfile($profile, $this->connections->secretsFor($connectionId)),
                false,
            );
            $schemaError = $this->schemaError($pdo, $table, $keyColumn, $valueColumn, $resultMode === 'key_value_map' ? $resultKeyColumn : null);

            if ($schemaError !== null) {
                return LookupResult::error($schemaError);
            }

            $select = $resultMode === 'key_value_map'
                ? sprintf('`%s` AS result_key, `%s` AS lookup_value', $resultKeyColumn, $valueColumn)
                : sprintf('`%s` AS lookup_value', $valueColumn);
            $sql = sprintf(
                'SELECT %s FROM `%s` WHERE `%s` %s :lookup_key LIMIT %d',
                $select,
                $table,
                $keyColumn,
                LookupMatchMode::sqlOperator($matchMode),
                $matchMode === 'exact' && $resultMode === 'first' ? 2 : $limit,
            );
            $statement = $pdo->prepare($sql);
            $statement->execute(['lookup_key' => LookupMatchMode::parameter($matchMode, $key)]);
            $rows = $statement->fetchAll();
        } catch (Throwable) {
            return LookupResult::error('lookup_connection_unavailable');
        }

        if (count($rows) === 0) {
            return LookupResult::error('lookup_key_not_found');
        }

        if ($matchMode === 'exact' && $resultMode === 'first' && count($rows) > 1) {
            return LookupResult::error('ambiguous_lookup_result');
        }

        return LookupResultMode::reduceRows($rows, $resultMode, [
            'key_transform' => $resultKeyTransform,
            'rendered_prefix' => (string) ($field['_lookup_result_key_prefix'] ?? ''),
        ]);
    }

    private function validIdentifier(string $identifier): bool
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $identifier) === 1;
    }

    private function schemaError(PDO $pdo, string $table, string $keyColumn, string $valueColumn, ?string $resultKeyColumn = null): ?string
    {
        try {
            $statement = $pdo->query(sprintf('SHOW COLUMNS FROM `%s`', $table));
            $columns = $statement === false ? [] : array_column($statement->fetchAll(), 'Field');
        } catch (Throwable) {
            return 'lookup_table_not_found';
        }

        if (! in_array($keyColumn, $columns, true)) {
            return 'lookup_key_column_not_found';
        }

        if (! in_array($valueColumn, $columns, true)) {
            return 'lookup_value_column_not_found';
        }

        if ($resultKeyColumn !== null && ! in_array($resultKeyColumn, $columns, true)) {
            return 'missing_result_key_column';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $field
     */
    private function resultLimit(array $field): int
    {
        $limit = (int) ($field['lookup_result_limit'] ?? 100);

        return max(1, min($limit, 500));
    }
}
