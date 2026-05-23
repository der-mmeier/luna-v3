<?php

declare(strict_types=1);

namespace Luna\Schema;

use PDO;

final class TableNameReader
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /**
     * @return list<array{name: string}>
     */
    public function tableNames(): array
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            return $this->fetchSqliteTableNames();
        }

        return $this->fetchMysqlTableNames();
    }

    /**
     * @return list<array{name: string}>
     */
    private function fetchMysqlTableNames(): array
    {
        $statement = $this->pdo->query('SHOW TABLES');
        if ($statement === false) {
            return [];
        }

        $tables = [];
        foreach ($statement->fetchAll(PDO::FETCH_NUM) as $row) {
            if (! isset($row[0])) {
                continue;
            }

            $name = (string) $row[0];
            if ($name !== '') {
                $tables[] = ['name' => $name];
            }
        }

        usort($tables, static fn (array $left, array $right): int => strcmp($left['name'], $right['name']));

        return $tables;
    }

    /**
     * @return list<array{name: string}>
     */
    private function fetchSqliteTableNames(): array
    {
        $statement = $this->pdo->query(
            "SELECT name
             FROM sqlite_master
             WHERE type = 'table' AND name NOT LIKE 'sqlite_%'
             ORDER BY name",
        );
        if ($statement === false) {
            return [];
        }

        $tables = [];
        foreach ($statement->fetchAll() as $row) {
            $tables[] = ['name' => (string) $row['name']];
        }

        return $tables;
    }
}
