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

        $statement = $this->pdo->query(
            'SELECT TABLE_NAME AS name
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
             ORDER BY TABLE_NAME',
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
