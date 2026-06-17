CREATE TABLE IF NOT EXISTS luna_connection_workspaces (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    connection_id BIGINT UNSIGNED NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_luna_connection_workspace (connection_id, workspace_id),
    INDEX idx_luna_connection_workspaces_connection (connection_id),
    INDEX idx_luna_connection_workspaces_workspace (workspace_id),
    CONSTRAINT fk_luna_connection_workspaces_connection
        FOREIGN KEY (connection_id) REFERENCES luna_connection_profiles(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_luna_connection_workspaces_workspace
        FOREIGN KEY (workspace_id) REFERENCES luna_workspaces(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
