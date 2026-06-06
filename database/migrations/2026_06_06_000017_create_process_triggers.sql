CREATE TABLE IF NOT EXISTS luna_process_triggers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    process_id BIGINT UNSIGNED NOT NULL,
    workspace_id BIGINT UNSIGNED NULL,
    name VARCHAR(190) NOT NULL,
    trigger_type VARCHAR(40) NOT NULL,
    trigger_key VARCHAR(190) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    config_json LONGTEXT NULL,
    secret_hash VARCHAR(255) NULL,
    last_triggered_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_luna_process_triggers_key (trigger_key),
    INDEX idx_luna_process_triggers_process (process_id),
    INDEX idx_luna_process_triggers_workspace (workspace_id),
    INDEX idx_luna_process_triggers_type (trigger_type),
    INDEX idx_luna_process_triggers_active (is_active)
);

ALTER TABLE luna_process_runs
    ADD COLUMN trigger_id BIGINT UNSIGNED NULL AFTER trigger_ref,
    ADD COLUMN trigger_source VARCHAR(80) NULL AFTER trigger_id,
    ADD COLUMN trigger_payload_meta LONGTEXT NULL AFTER trigger_source,
    ADD INDEX idx_luna_process_runs_trigger (trigger_id);
