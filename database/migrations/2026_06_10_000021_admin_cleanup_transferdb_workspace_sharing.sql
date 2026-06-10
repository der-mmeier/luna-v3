SET @luna_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'luna_workspaces' AND COLUMN_NAME = 'transfer_db_connection_id') = 0,
    'ALTER TABLE luna_workspaces ADD COLUMN transfer_db_connection_id BIGINT UNSIGNED NULL AFTER status',
    'DO 0'
);
PREPARE luna_stmt FROM @luna_sql;
EXECUTE luna_stmt;
DEALLOCATE PREPARE luna_stmt;

SET @luna_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'luna_workspaces' AND INDEX_NAME = 'idx_luna_workspaces_transfer_db_connection') = 0,
    'ALTER TABLE luna_workspaces ADD INDEX idx_luna_workspaces_transfer_db_connection (transfer_db_connection_id)',
    'DO 0'
);
PREPARE luna_stmt FROM @luna_sql;
EXECUTE luna_stmt;
DEALLOCATE PREPARE luna_stmt;

SET @luna_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'luna_connection_profiles' AND INDEX_NAME = 'idx_luna_connection_profiles_type') = 0,
    'ALTER TABLE luna_connection_profiles ADD INDEX idx_luna_connection_profiles_type (type)',
    'DO 0'
);
PREPARE luna_stmt FROM @luna_sql;
EXECUTE luna_stmt;
DEALLOCATE PREPARE luna_stmt;

CREATE TABLE IF NOT EXISTS luna_connection_workspaces (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    connection_id BIGINT UNSIGNED NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,
    role VARCHAR(64) NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_luna_connection_workspace (connection_id, workspace_id),
    INDEX idx_luna_connection_workspaces_connection (connection_id),
    INDEX idx_luna_connection_workspaces_workspace (workspace_id),
    INDEX idx_luna_connection_workspaces_default (workspace_id, role, is_default),
    CONSTRAINT fk_luna_connection_workspaces_connection
        FOREIGN KEY (connection_id) REFERENCES luna_connection_profiles(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_luna_connection_workspaces_workspace
        FOREIGN KEY (workspace_id) REFERENCES luna_workspaces(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO luna_connection_workspaces (connection_id, workspace_id, role, is_default, created_at, updated_at)
SELECT id, workspace_id, type, 0, NOW(), NOW()
FROM luna_connection_profiles
WHERE workspace_id IS NOT NULL;

SET @luna_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'luna_reports' AND COLUMN_NAME = 'report_key') = 0,
    'ALTER TABLE luna_reports ADD COLUMN report_key VARCHAR(190) NULL AFTER workspace_id',
    'DO 0'
);
PREPARE luna_stmt FROM @luna_sql;
EXECUTE luna_stmt;
DEALLOCATE PREPARE luna_stmt;

SET @luna_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'luna_reports' AND COLUMN_NAME = 'name') = 0,
    'ALTER TABLE luna_reports ADD COLUMN name VARCHAR(190) NULL AFTER report_key',
    'DO 0'
);
PREPARE luna_stmt FROM @luna_sql;
EXECUTE luna_stmt;
DEALLOCATE PREPARE luna_stmt;

SET @luna_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'luna_reports' AND COLUMN_NAME = 'config_json') = 0,
    'ALTER TABLE luna_reports ADD COLUMN config_json LONGTEXT NULL AFTER body',
    'DO 0'
);
PREPARE luna_stmt FROM @luna_sql;
EXECUTE luna_stmt;
DEALLOCATE PREPARE luna_stmt;

SET @luna_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'luna_reports' AND COLUMN_NAME = 'notes') = 0,
    'ALTER TABLE luna_reports ADD COLUMN notes TEXT NULL AFTER config_json',
    'DO 0'
);
PREPARE luna_stmt FROM @luna_sql;
EXECUTE luna_stmt;
DEALLOCATE PREPARE luna_stmt;

UPDATE luna_reports
SET name = COALESCE(name, subject),
    report_key = COALESCE(report_key, CONCAT('report_', id))
WHERE name IS NULL OR report_key IS NULL;

SET @luna_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'luna_reports' AND INDEX_NAME = 'uniq_luna_reports_workspace_key') = 0,
    'ALTER TABLE luna_reports ADD UNIQUE KEY uniq_luna_reports_workspace_key (workspace_id, report_key)',
    'DO 0'
);
PREPARE luna_stmt FROM @luna_sql;
EXECUTE luna_stmt;
DEALLOCATE PREPARE luna_stmt;
