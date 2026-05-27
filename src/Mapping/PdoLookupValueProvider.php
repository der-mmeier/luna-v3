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

        if (! $this->validIdentifier($table)) {
            return LookupResult::error('lookup_table_not_found');
        }

        if (! $this->validIdentifier($keyColumn)) {
            return LookupResult::error('lookup_key_column_not_found');
        }

        if (! $this->validIdentifier($valueColumn)) {
            return LookupResult::error('lookup_value_column_not_found');
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
            $schemaError = $this->schemaError($pdo, $table, $keyColumn, $valueColumn);

            if ($schemaError !== null) {
                return LookupResult::error($schemaError);
            }

            $statement = $pdo->prepare(sprintf('SELECT `%s` AS lookup_value FROM `%s` WHERE `%s` = :lookup_key LIMIT 2', $valueColumn, $table, $keyColumn));
            $statement->execute(['lookup_key' => $key]);
            $rows = $statement->fetchAll();
        } catch (Throwable) {
            return LookupResult::error('lookup_connection_unavailable');
        }

        if (count($rows) === 0) {
            return LookupResult::error('lookup_key_not_found');
        }

        if (count($rows) > 1) {
            return LookupResult::error('ambiguous_lookup_result');
        }

        return LookupResult::found($rows[0]['lookup_value'] ?? null);
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
}
