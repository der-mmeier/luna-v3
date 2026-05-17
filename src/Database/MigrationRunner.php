<?php

declare(strict_types=1);

namespace Luna\Database;

use RuntimeException;

final class MigrationRunner
{
    public function __construct(
        private readonly SystemDatabase $database,
        private readonly string $migrationPath,
    ) {
    }

    /**
     * @return list<string>
     */
    public function pending(): array
    {
        $this->ensureMigrationTable();
        $executed = $this->executedMigrations();

        return array_values(array_filter(
            $this->migrationFiles(),
            static fn (string $migration): bool => ! in_array($migration, $executed, true),
        ));
    }

    /**
     * @return list<string>
     */
    public function migrate(): array
    {
        $executed = [];
        $pending = $this->pending();

        foreach ($pending as $migration) {
            $file = $this->migrationPath . DIRECTORY_SEPARATOR . $migration;
            $sql = file_get_contents($file);

            if ($sql === false) {
                throw new RuntimeException(sprintf('Could not read migration "%s".', $migration));
            }

            foreach ($this->splitStatements($sql) as $statement) {
                $this->database->pdo()->exec($statement);
            }

            $batch = $this->nextBatch();
            $insert = $this->database->pdo()->prepare(
                'INSERT INTO luna_migrations (migration, batch, executed_at) VALUES (:migration, :batch, NOW())',
            );
            $insert->execute([
                'migration' => $migration,
                'batch' => $batch,
            ]);

            $executed[] = $migration;
        }

        return $executed;
    }

    private function ensureMigrationTable(): void
    {
        $this->database->pdo()->exec(
            "CREATE TABLE IF NOT EXISTS luna_migrations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                batch INT UNSIGNED NOT NULL DEFAULT 1,
                executed_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        );
    }

    /**
     * @return list<string>
     */
    private function executedMigrations(): array
    {
        $statement = $this->database->pdo()->query('SELECT migration FROM luna_migrations ORDER BY migration');

        if ($statement === false) {
            return [];
        }

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @return list<string>
     */
    private function migrationFiles(): array
    {
        $files = glob($this->migrationPath . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        $migrations = array_map('basename', $files);
        sort($migrations);

        return $migrations;
    }

    private function nextBatch(): int
    {
        $statement = $this->database->pdo()->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM luna_migrations');

        return (int) $statement->fetchColumn();
    }

    /**
     * @return list<string>
     */
    private function splitStatements(string $sql): array
    {
        return array_values(array_filter(
            array_map('trim', explode(';', $sql)),
            static fn (string $statement): bool => $statement !== '',
        ));
    }
}
