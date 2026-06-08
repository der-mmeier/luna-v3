<?php

declare(strict_types=1);

namespace Luna\TransferDb;

use PDO;

final class TransferDbSchemaManager
{
    public const VERSION = '2026_06_09_000001';
    public const NAME = 'transferdb_foundation';

    /**
     * @return list<string>
     */
    public function tableNames(): array
    {
        return [
            'luna_transfer_schema_migrations',
            'luna_transfer_sources',
            'luna_transfer_batches',
            'luna_transfer_records',
            'luna_transfer_webhook_events',
            'luna_transfer_endpoint_snapshots',
            'luna_transfer_logs',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function status(PDO $pdo): array
    {
        $existing = [];
        foreach ($this->tableNames() as $table) {
            if ($this->tableExists($pdo, $table)) {
                $existing[] = $table;
            }
        }

        $missing = array_values(array_diff($this->tableNames(), $existing));

        return [
            'configured' => true,
            'reachable' => true,
            'schema_current' => $missing === [] && $this->migrationApplied($pdo),
            'missing_tables' => $missing,
            'existing_tables' => $existing,
            'migration_version' => $this->latestMigrationVersion($pdo),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function migrate(PDO $pdo): array
    {
        foreach ($this->statements($pdo) as $statement) {
            $pdo->exec($statement);
        }

        $this->recordMigration($pdo);

        return $this->status($pdo);
    }

    /**
     * @return list<string>
     */
    public function statements(PDO $pdo): array
    {
        return $this->isSqlite($pdo) ? $this->sqliteStatements() : $this->mysqlStatements();
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        if ($this->isSqlite($pdo)) {
            $statement = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
            $statement->execute(['name' => $table]);

            return $statement->fetchColumn() !== false;
        }

        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
        );
        $statement->execute(['table' => $table]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function migrationApplied(PDO $pdo): bool
    {
        if (! $this->tableExists($pdo, 'luna_transfer_schema_migrations')) {
            return false;
        }

        $statement = $pdo->prepare('SELECT COUNT(*) FROM luna_transfer_schema_migrations WHERE version = :version');
        $statement->execute(['version' => self::VERSION]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function latestMigrationVersion(PDO $pdo): ?string
    {
        if (! $this->tableExists($pdo, 'luna_transfer_schema_migrations')) {
            return null;
        }

        $statement = $pdo->query('SELECT version FROM luna_transfer_schema_migrations ORDER BY id DESC LIMIT 1');
        $version = $statement === false ? false : $statement->fetchColumn();

        return $version === false ? null : (string) $version;
    }

    private function recordMigration(PDO $pdo): void
    {
        $checksum = hash('sha256', implode("\n", $this->statements($pdo)));
        if ($this->isSqlite($pdo)) {
            $statement = $pdo->prepare(
                'INSERT OR IGNORE INTO luna_transfer_schema_migrations (version, name, checksum, applied_at)
                 VALUES (:version, :name, :checksum, CURRENT_TIMESTAMP)',
            );
        } else {
            $statement = $pdo->prepare(
                'INSERT IGNORE INTO luna_transfer_schema_migrations (version, name, checksum, applied_at)
                 VALUES (:version, :name, :checksum, NOW())',
            );
        }

        $statement->execute([
            'version' => self::VERSION,
            'name' => self::NAME,
            'checksum' => $checksum,
        ]);
    }

    private function isSqlite(PDO $pdo): bool
    {
        return (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    /**
     * @return list<string>
     */
    private function mysqlStatements(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS luna_transfer_schema_migrations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                version VARCHAR(80) NOT NULL UNIQUE,
                name VARCHAR(190) NOT NULL,
                checksum VARCHAR(64) NULL,
                applied_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS luna_transfer_sources (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                workspace_key VARCHAR(120) NOT NULL,
                source_type VARCHAR(40) NOT NULL,
                source_key VARCHAR(190) NOT NULL,
                provider VARCHAR(80) NULL,
                schema_key VARCHAR(190) NULL,
                schema_version VARCHAR(80) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_luna_transfer_sources_source (workspace_key, source_type, source_key),
                INDEX idx_luna_transfer_sources_workspace (workspace_key),
                INDEX idx_luna_transfer_sources_type (source_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS luna_transfer_batches (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source_id BIGINT UNSIGNED NOT NULL,
                external_id VARCHAR(190) NULL,
                batch_type VARCHAR(60) NOT NULL,
                status VARCHAR(40) NOT NULL DEFAULT 'received',
                record_count INT UNSIGNED NOT NULL DEFAULT 0,
                payload_hash VARCHAR(64) NULL,
                metadata_json LONGTEXT NULL,
                received_at DATETIME NULL,
                generated_at DATETIME NULL,
                processed_at DATETIME NULL,
                error_message TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_luna_transfer_batches_source (source_id),
                INDEX idx_luna_transfer_batches_status (status),
                INDEX idx_luna_transfer_batches_external (external_id),
                INDEX idx_luna_transfer_batches_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS luna_transfer_records (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                batch_id BIGINT UNSIGNED NOT NULL,
                source_id BIGINT UNSIGNED NOT NULL,
                record_key VARCHAR(190) NULL,
                record_index INT NULL,
                operation VARCHAR(40) NULL,
                status VARCHAR(40) NOT NULL DEFAULT 'staged',
                payload_json LONGTEXT NOT NULL,
                payload_hash VARCHAR(64) NOT NULL,
                schema_key VARCHAR(190) NULL,
                schema_version VARCHAR(80) NULL,
                validation_status VARCHAR(40) NULL,
                validation_errors_json LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_luna_transfer_records_batch (batch_id),
                INDEX idx_luna_transfer_records_source (source_id),
                INDEX idx_luna_transfer_records_key (record_key),
                INDEX idx_luna_transfer_records_hash (payload_hash),
                INDEX idx_luna_transfer_records_status (status),
                INDEX idx_luna_transfer_records_validation (validation_status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS luna_transfer_webhook_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                batch_id BIGINT UNSIGNED NULL,
                workspace_key VARCHAR(120) NOT NULL,
                provider VARCHAR(80) NOT NULL,
                trigger_key VARCHAR(190) NOT NULL,
                configured_topic VARCHAR(120) NULL,
                received_topic VARCHAR(120) NULL,
                event_name VARCHAR(80) NULL,
                resource VARCHAR(80) NULL,
                action VARCHAR(80) NULL,
                external_event_id VARCHAR(190) NULL,
                external_delivery_id VARCHAR(190) NULL,
                source_url VARCHAR(255) NULL,
                signature_valid TINYINT(1) NOT NULL DEFAULT 0,
                signature_algorithm VARCHAR(80) NULL,
                payload_hash VARCHAR(64) NOT NULL,
                payload_json LONGTEXT NOT NULL,
                headers_json LONGTEXT NULL,
                status VARCHAR(40) NOT NULL DEFAULT 'received',
                rejection_reason TEXT NULL,
                received_at DATETIME NOT NULL,
                processed_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_luna_transfer_webhooks_workspace (workspace_key),
                INDEX idx_luna_transfer_webhooks_provider (provider),
                INDEX idx_luna_transfer_webhooks_trigger (trigger_key),
                INDEX idx_luna_transfer_webhooks_delivery (external_delivery_id),
                INDEX idx_luna_transfer_webhooks_hash (payload_hash),
                INDEX idx_luna_transfer_webhooks_status (status),
                INDEX idx_luna_transfer_webhooks_received (received_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS luna_transfer_endpoint_snapshots (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                batch_id BIGINT UNSIGNED NULL,
                workspace_key VARCHAR(120) NOT NULL,
                endpoint_key VARCHAR(190) NULL,
                mapping_id BIGINT UNSIGNED NULL,
                process_id BIGINT UNSIGNED NULL,
                process_run_id BIGINT UNSIGNED NULL,
                schema_key VARCHAR(190) NULL,
                schema_version VARCHAR(80) NULL,
                result_count INT UNSIGNED NOT NULL DEFAULT 0,
                result_hash VARCHAR(64) NOT NULL,
                result_json LONGTEXT NULL,
                status VARCHAR(40) NOT NULL DEFAULT 'generated',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_luna_transfer_snapshots_workspace (workspace_key),
                INDEX idx_luna_transfer_snapshots_endpoint (endpoint_key),
                INDEX idx_luna_transfer_snapshots_mapping (mapping_id),
                INDEX idx_luna_transfer_snapshots_process (process_id),
                INDEX idx_luna_transfer_snapshots_run (process_run_id),
                INDEX idx_luna_transfer_snapshots_hash (result_hash),
                INDEX idx_luna_transfer_snapshots_status (status),
                INDEX idx_luna_transfer_snapshots_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS luna_transfer_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                workspace_key VARCHAR(120) NOT NULL,
                level VARCHAR(20) NOT NULL,
                context_type VARCHAR(60) NULL,
                context_id VARCHAR(190) NULL,
                message TEXT NOT NULL,
                metadata_json LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_luna_transfer_logs_workspace (workspace_key),
                INDEX idx_luna_transfer_logs_level (level),
                INDEX idx_luna_transfer_logs_context (context_type, context_id),
                INDEX idx_luna_transfer_logs_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
    }

    /**
     * @return list<string>
     */
    private function sqliteStatements(): array
    {
        return [
            'CREATE TABLE IF NOT EXISTS luna_transfer_schema_migrations (id INTEGER PRIMARY KEY AUTOINCREMENT, version TEXT NOT NULL UNIQUE, name TEXT NOT NULL, checksum TEXT NULL, applied_at TEXT NOT NULL)',
            'CREATE TABLE IF NOT EXISTS luna_transfer_sources (id INTEGER PRIMARY KEY AUTOINCREMENT, workspace_key TEXT NOT NULL, source_type TEXT NOT NULL, source_key TEXT NOT NULL, provider TEXT NULL, schema_key TEXT NULL, schema_version TEXT NULL, is_active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)',
            'CREATE UNIQUE INDEX IF NOT EXISTS uniq_luna_transfer_sources_source ON luna_transfer_sources (workspace_key, source_type, source_key)',
            'CREATE TABLE IF NOT EXISTS luna_transfer_batches (id INTEGER PRIMARY KEY AUTOINCREMENT, source_id INTEGER NOT NULL, external_id TEXT NULL, batch_type TEXT NOT NULL, status TEXT NOT NULL DEFAULT "received", record_count INTEGER NOT NULL DEFAULT 0, payload_hash TEXT NULL, metadata_json TEXT NULL, received_at TEXT NULL, generated_at TEXT NULL, processed_at TEXT NULL, error_message TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)',
            'CREATE TABLE IF NOT EXISTS luna_transfer_records (id INTEGER PRIMARY KEY AUTOINCREMENT, batch_id INTEGER NOT NULL, source_id INTEGER NOT NULL, record_key TEXT NULL, record_index INTEGER NULL, operation TEXT NULL, status TEXT NOT NULL DEFAULT "staged", payload_json TEXT NOT NULL, payload_hash TEXT NOT NULL, schema_key TEXT NULL, schema_version TEXT NULL, validation_status TEXT NULL, validation_errors_json TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)',
            'CREATE TABLE IF NOT EXISTS luna_transfer_webhook_events (id INTEGER PRIMARY KEY AUTOINCREMENT, batch_id INTEGER NULL, workspace_key TEXT NOT NULL, provider TEXT NOT NULL, trigger_key TEXT NOT NULL, configured_topic TEXT NULL, received_topic TEXT NULL, event_name TEXT NULL, resource TEXT NULL, action TEXT NULL, external_event_id TEXT NULL, external_delivery_id TEXT NULL, source_url TEXT NULL, signature_valid INTEGER NOT NULL DEFAULT 0, signature_algorithm TEXT NULL, payload_hash TEXT NOT NULL, payload_json TEXT NOT NULL, headers_json TEXT NULL, status TEXT NOT NULL DEFAULT "received", rejection_reason TEXT NULL, received_at TEXT NOT NULL, processed_at TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)',
            'CREATE TABLE IF NOT EXISTS luna_transfer_endpoint_snapshots (id INTEGER PRIMARY KEY AUTOINCREMENT, batch_id INTEGER NULL, workspace_key TEXT NOT NULL, endpoint_key TEXT NULL, mapping_id INTEGER NULL, process_id INTEGER NULL, process_run_id INTEGER NULL, schema_key TEXT NULL, schema_version TEXT NULL, result_count INTEGER NOT NULL DEFAULT 0, result_hash TEXT NOT NULL, result_json TEXT NULL, status TEXT NOT NULL DEFAULT "generated", created_at TEXT NOT NULL, updated_at TEXT NOT NULL)',
            'CREATE TABLE IF NOT EXISTS luna_transfer_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, workspace_key TEXT NOT NULL, level TEXT NOT NULL, context_type TEXT NULL, context_id TEXT NULL, message TEXT NOT NULL, metadata_json TEXT NULL, created_at TEXT NOT NULL)',
        ];
    }
}
