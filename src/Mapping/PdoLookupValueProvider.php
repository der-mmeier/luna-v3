<?php

declare(strict_types=1);

namespace Luna\Mapping;

use Luna\Connections\ExternalDatabaseConfig;
use Luna\Connections\ExternalPdoConnectionFactory;
use Luna\Repository\ConnectionProfileRepository;
use PDO;
use Throwable;

final class PdoLookupValueProvider implements LookupValueProvider, PrefixLookupWarmupProvider
{
    private const PREFIX_BATCH_SIZE = 100;

    /**
     * @var array<int, PDO>
     */
    private array $pdoCache = [];

    /**
     * @var array<string, string|null>
     */
    private array $schemaErrorCache = [];

    /**
     * @var array<string, LookupResult>
     */
    private array $lookupValueCache = [];

    /**
     * @var array<string, LookupResult>
     */
    private array $prefixLookupCache = [];

    /**
     * @var array<string, int|float>
     */
    private array $prefixDiagnostics = [
        'prefix_lookup_queries' => 0,
        'prefix_lookup_prefixes' => 0,
        'prefix_lookup_rules' => 0,
        'prefix_lookup_batch_queries' => 0,
        'prefix_lookup_runtime_ms' => 0.0,
    ];

    public function __construct(
        private readonly ConnectionProfileRepository $connections,
        private readonly ExternalPdoConnectionFactory $pdoFactory,
    ) {
    }

    public function warmUpPrefixLookups(array $requests): array
    {
        $started = microtime(true);
        $this->prefixDiagnostics = [
            'prefix_lookup_queries' => 0,
            'prefix_lookup_prefixes' => 0,
            'prefix_lookup_rules' => count($requests),
            'prefix_lookup_batch_queries' => 0,
            'prefix_lookup_runtime_ms' => 0.0,
        ];

        $groups = [];

        foreach ($requests as $request) {
            $field = $request['field'];
            $prefixes = $request['prefixes'];
            $connectionId = (int) ($field['lookup_connection_id'] ?? 0);
            $table = (string) ($field['lookup_table'] ?? '');
            $keyColumn = (string) ($field['lookup_key_column'] ?? '');
            $valueColumn = (string) ($field['lookup_value_column'] ?? '');

            foreach ($prefixes as $prefix) {
                if ($prefix === '') {
                    continue;
                }

                $cacheKey = $this->prefixCacheKey($connectionId, $table, $keyColumn, $valueColumn, $prefix);
                if (array_key_exists($cacheKey, $this->prefixLookupCache)) {
                    continue;
                }

                $groupKey = $this->prefixConfigCacheKey($connectionId, $table, $keyColumn, $valueColumn);
                $groups[$groupKey]['field'] = $field;
                $groups[$groupKey]['prefixes'][$prefix] = $prefix;
            }
        }

        foreach ($groups as $group) {
            $field = $group['field'];
            $prefixes = array_values($group['prefixes']);
            $this->prefixDiagnostics['prefix_lookup_prefixes'] += count($prefixes);
            $this->warmUpPrefixGroup($field, $prefixes);
        }

        $this->prefixDiagnostics['prefix_lookup_runtime_ms'] = round((microtime(true) - $started) * 1000, 3);

        return $this->prefixDiagnostics;
    }

    public function lookup(array $field, string $key): LookupResult
    {
        $connectionId = (int) ($field['lookup_connection_id'] ?? 0);
        $table = (string) ($field['lookup_table'] ?? '');
        $keyColumn = (string) ($field['lookup_key_column'] ?? '');
        $valueColumn = (string) ($field['lookup_value_column'] ?? '');

        if ($connectionId <= 0) {
            return LookupResult::error('lookup_connection_unavailable');
        }

        if (! $this->validIdentifier($table)) {
            return LookupResult::error('lookup_table_not_found');
        }

        if (! $this->validIdentifier($keyColumn)) {
            return LookupResult::error('lookup_key_column_not_found');
        }

        if (! $this->validIdentifier($valueColumn)) {
            return LookupResult::error('lookup_value_column_not_found');
        }

        $cacheKey = $this->lookupCacheKey(
            $connectionId,
            $table,
            $keyColumn,
            $valueColumn,
            $key,
        );

        if (array_key_exists($cacheKey, $this->lookupValueCache)) {
            return $this->lookupValueCache[$cacheKey];
        }

        try {
            $pdo = $this->pdoForConnection($connectionId);

            if ($pdo === null) {
                $result = LookupResult::error('lookup_connection_unavailable');
                $this->lookupValueCache[$cacheKey] = $result;

                return $result;
            }

            $schemaError = $this->cachedSchemaError(
                $pdo,
                $connectionId,
                $table,
                $keyColumn,
                $valueColumn,
            );

            if ($schemaError !== null) {
                $result = LookupResult::error($schemaError);
                $this->lookupValueCache[$cacheKey] = $result;

                return $result;
            }

            $statement = $pdo->prepare(sprintf(
                'SELECT `%s` AS lookup_value FROM `%s` WHERE `%s` = :lookup_key LIMIT 2',
                $valueColumn,
                $table,
                $keyColumn,
            ));
            $statement->execute(['lookup_key' => $key]);
            $rows = $statement->fetchAll();
        } catch (Throwable) {
            $result = LookupResult::error('lookup_connection_unavailable');
            $this->lookupValueCache[$cacheKey] = $result;

            return $result;
        }

        if (count($rows) === 0) {
            $result = LookupResult::error('lookup_key_not_found');
            $this->lookupValueCache[$cacheKey] = $result;

            return $result;
        }

        if (count($rows) > 1) {
            $result = LookupResult::error('ambiguous_lookup_result');
            $this->lookupValueCache[$cacheKey] = $result;

            return $result;
        }

        $result = LookupResult::found($rows[0]['lookup_value'] ?? null);
        $this->lookupValueCache[$cacheKey] = $result;

        return $result;
    }

    public function lookupByPrefix(array $field, string $prefix): LookupResult
    {
        $connectionId = (int) ($field['lookup_connection_id'] ?? 0);
        $table = (string) ($field['lookup_table'] ?? '');
        $keyColumn = (string) ($field['lookup_key_column'] ?? '');
        $valueColumn = (string) ($field['lookup_value_column'] ?? '');

        if ($prefix === '') {
            return LookupResult::found((object) []);
        }

        if ($connectionId <= 0) {
            return LookupResult::error('lookup_connection_unavailable');
        }

        if (! $this->validIdentifier($table)) {
            return LookupResult::error('lookup_table_not_found');
        }

        if (! $this->validIdentifier($keyColumn)) {
            return LookupResult::error('lookup_key_column_not_found');
        }

        if (! $this->validIdentifier($valueColumn)) {
            return LookupResult::error('lookup_value_column_not_found');
        }

        $cacheKey = $this->prefixCacheKey(
            $connectionId,
            $table,
            $keyColumn,
            $valueColumn,
            $prefix,
        );

        if (array_key_exists($cacheKey, $this->prefixLookupCache)) {
            return $this->prefixLookupCache[$cacheKey];
        }

        try {
            $pdo = $this->pdoForConnection($connectionId);

            if ($pdo === null) {
                $result = LookupResult::error('lookup_connection_unavailable');
                $this->prefixLookupCache[$cacheKey] = $result;

                return $result;
            }

            $schemaError = $this->cachedSchemaError(
                $pdo,
                $connectionId,
                $table,
                $keyColumn,
                $valueColumn,
            );

            if ($schemaError !== null) {
                $result = LookupResult::error($schemaError);
                $this->prefixLookupCache[$cacheKey] = $result;

                return $result;
            }
            $statement = $pdo->prepare(sprintf(
                'SELECT `%s` AS lookup_key, `%s` AS lookup_value FROM `%s` WHERE `%s` LIKE :lookup_pattern ORDER BY `%s`',
                $keyColumn,
                $valueColumn,
                $table,
                $keyColumn,
                $keyColumn,
            ));
            $statement->execute(['lookup_pattern' => $prefix . '%']);
            $rows = $statement->fetchAll();
            $this->prefixDiagnostics['prefix_lookup_queries']++;
        } catch (Throwable) {
            $result = LookupResult::error('lookup_connection_unavailable');
            $this->prefixLookupCache[$cacheKey] = $result;

            return $result;
        }

        if ($rows === []) {
            $result = LookupResult::found((object) []);
            $this->prefixLookupCache[$cacheKey] = $result;

            return $result;
        }

        $map = [];
        foreach ($rows as $row) {
            $rawKey = (string) ($row['lookup_key'] ?? '');
            $outputKey = str_starts_with($rawKey, $prefix) ? substr($rawKey, strlen($prefix)) : $rawKey;

            if ($outputKey === '') {
                $result = LookupResult::error('empty_result_key');
                $this->prefixLookupCache[$cacheKey] = $result;

                return $result;
            }

            if (array_key_exists($outputKey, $map)) {
                $result = LookupResult::error('duplicate_result_key');
                $this->prefixLookupCache[$cacheKey] = $result;

                return $result;
            }

            $map[$outputKey] = $this->normalizeValue($row['lookup_value'] ?? null);
        }

        $result = LookupResult::found($map);
        $this->prefixLookupCache[$cacheKey] = $result;

        return $result;
    }

    /**
     * @param array<string, mixed> $field
     * @param list<string> $prefixes
     */
    private function warmUpPrefixGroup(array $field, array $prefixes): void
    {
        $connectionId = (int) ($field['lookup_connection_id'] ?? 0);
        $table = (string) ($field['lookup_table'] ?? '');
        $keyColumn = (string) ($field['lookup_key_column'] ?? '');
        $valueColumn = (string) ($field['lookup_value_column'] ?? '');

        if ($prefixes === []) {
            return;
        }

        if ($connectionId <= 0) {
            $this->cachePrefixErrors($connectionId, $table, $keyColumn, $valueColumn, $prefixes, 'lookup_connection_unavailable');
            return;
        }

        if (! $this->validIdentifier($table)) {
            $this->cachePrefixErrors($connectionId, $table, $keyColumn, $valueColumn, $prefixes, 'lookup_table_not_found');
            return;
        }

        if (! $this->validIdentifier($keyColumn)) {
            $this->cachePrefixErrors($connectionId, $table, $keyColumn, $valueColumn, $prefixes, 'lookup_key_column_not_found');
            return;
        }

        if (! $this->validIdentifier($valueColumn)) {
            $this->cachePrefixErrors($connectionId, $table, $keyColumn, $valueColumn, $prefixes, 'lookup_value_column_not_found');
            return;
        }

        try {
            $pdo = $this->pdoForConnection($connectionId);

            if ($pdo === null) {
                $this->cachePrefixErrors($connectionId, $table, $keyColumn, $valueColumn, $prefixes, 'lookup_connection_unavailable');
                return;
            }

            $schemaError = $this->cachedSchemaError(
                $pdo,
                $connectionId,
                $table,
                $keyColumn,
                $valueColumn,
            );

            if ($schemaError !== null) {
                $this->cachePrefixErrors($connectionId, $table, $keyColumn, $valueColumn, $prefixes, $schemaError);
                return;
            }

            foreach (array_chunk($prefixes, self::PREFIX_BATCH_SIZE) as $chunk) {
                $this->warmUpPrefixChunk($pdo, $connectionId, $table, $keyColumn, $valueColumn, $chunk);
            }
        } catch (Throwable) {
            $this->cachePrefixErrors($connectionId, $table, $keyColumn, $valueColumn, $prefixes, 'lookup_connection_unavailable');
        }
    }

    /**
     * @param list<string> $prefixes
     */
    private function warmUpPrefixChunk(PDO $pdo, int $connectionId, string $table, string $keyColumn, string $valueColumn, array $prefixes): void
    {
        $whereParts = [];
        $params = [];

        foreach ($prefixes as $index => $prefix) {
            $placeholder = 'prefix_' . $index;
            $whereParts[] = sprintf('`%s` LIKE :%s', $keyColumn, $placeholder);
            $params[$placeholder] = $prefix . '%';
        }

        $buckets = [];
        foreach ($prefixes as $prefix) {
            $buckets[$prefix] = [
                'map' => [],
                'error' => null,
            ];
        }

        try {
            $statement = $pdo->prepare(sprintf(
                'SELECT `%s` AS lookup_key, `%s` AS lookup_value FROM `%s` WHERE %s ORDER BY `%s`',
                $keyColumn,
                $valueColumn,
                $table,
                implode(' OR ', $whereParts),
                $keyColumn,
            ));
            $statement->execute($params);
            $rows = $statement->fetchAll();
            $this->prefixDiagnostics['prefix_lookup_queries']++;
            $this->prefixDiagnostics['prefix_lookup_batch_queries']++;
        } catch (Throwable) {
            $this->cachePrefixErrors($connectionId, $table, $keyColumn, $valueColumn, $prefixes, 'lookup_connection_unavailable');
            return;
        }

        foreach ($rows as $row) {
            $rawKey = (string) ($row['lookup_key'] ?? '');

            foreach ($prefixes as $prefix) {
                if (! str_starts_with($rawKey, $prefix)) {
                    continue;
                }

                if ($buckets[$prefix]['error'] !== null) {
                    continue;
                }

                $outputKey = substr($rawKey, strlen($prefix));

                if ($outputKey === '') {
                    $buckets[$prefix]['error'] = 'empty_result_key';
                    $buckets[$prefix]['map'] = [];
                    continue;
                }

                if (array_key_exists($outputKey, $buckets[$prefix]['map'])) {
                    $buckets[$prefix]['error'] = 'duplicate_result_key';
                    $buckets[$prefix]['map'] = [];
                    continue;
                }

                $buckets[$prefix]['map'][$outputKey] = $this->normalizeValue($row['lookup_value'] ?? null);
            }
        }

        foreach ($buckets as $prefix => $bucket) {
            $cacheKey = $this->prefixCacheKey($connectionId, $table, $keyColumn, $valueColumn, $prefix);
            $this->prefixLookupCache[$cacheKey] = $bucket['error'] === null
                ? LookupResult::found($bucket['map'] === [] ? (object) [] : $bucket['map'])
                : LookupResult::error((string) $bucket['error']);
        }
    }

    /**
     * @param list<string> $prefixes
     */
    private function cachePrefixErrors(int $connectionId, string $table, string $keyColumn, string $valueColumn, array $prefixes, string $errorCode): void
    {
        foreach ($prefixes as $prefix) {
            $this->prefixLookupCache[$this->prefixCacheKey($connectionId, $table, $keyColumn, $valueColumn, $prefix)] = LookupResult::error($errorCode);
        }
    }

    private function validIdentifier(string $identifier): bool
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $identifier) === 1;
    }

    private function schemaError(PDO $pdo, string $table, string $keyColumn, string $valueColumn): ?string
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

        return null;
    }

    private function cachedSchemaError(
        PDO $pdo,
        int $connectionId,
        string $table,
        string $keyColumn,
        string $valueColumn,
    ): ?string {
        $cacheKey = implode('|', [
            (string) $connectionId,
            $table,
            $keyColumn,
            $valueColumn,
        ]);

        if (array_key_exists($cacheKey, $this->schemaErrorCache)) {
            return $this->schemaErrorCache[$cacheKey];
        }

        $error = $this->schemaError($pdo, $table, $keyColumn, $valueColumn);
        $this->schemaErrorCache[$cacheKey] = $error;

        return $error;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (! is_string($value) || ! is_numeric($value)) {
            return $value;
        }

        return str_contains($value, '.') ? (float) $value : (int) $value;
    }

    private function pdoForConnection(int $connectionId): ?PDO
    {
        if (isset($this->pdoCache[$connectionId])) {
            return $this->pdoCache[$connectionId];
        }

        $profile = $this->connections->find($connectionId);

        if ($profile === null) {
            return null;
        }

        $pdo = $this->pdoFactory->create(
            ExternalDatabaseConfig::fromProfile(
                $profile,
                $this->connections->secretsFor($connectionId),
            ),
            true,
        );

        $this->pdoCache[$connectionId] = $pdo;

        return $pdo;
    }

    private function lookupCacheKey(
        int $connectionId,
        string $table,
        string $keyColumn,
        string $valueColumn,
        string $key,
    ): string {
        return implode('|', [
            'lookup_value',
            (string) $connectionId,
            $table,
            $keyColumn,
            $valueColumn,
            $key,
        ]);
    }

    private function prefixCacheKey(
        int $connectionId,
        string $table,
        string $keyColumn,
        string $valueColumn,
        string $prefix,
    ): string {
        return implode('|', [
            'key_value_map_by_prefix',
            (string) $connectionId,
            $table,
            $keyColumn,
            $valueColumn,
            $prefix,
        ]);
    }

    private function prefixConfigCacheKey(
        int $connectionId,
        string $table,
        string $keyColumn,
        string $valueColumn,
    ): string {
        return implode('|', [
            'key_value_map_by_prefix',
            (string) $connectionId,
            $table,
            $keyColumn,
            $valueColumn,
        ]);
    }
}
