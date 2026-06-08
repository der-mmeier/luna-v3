CREATE TABLE IF NOT EXISTS luna_target_actions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL,
    action_key VARCHAR(190) NOT NULL,
    action_type VARCHAR(80) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    config_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_luna_target_actions_workspace_key (workspace_id, action_key),
    INDEX idx_luna_target_actions_workspace (workspace_id),
    INDEX idx_luna_target_actions_type (action_type),
    INDEX idx_luna_target_actions_active (is_active)
);
