CREATE TABLE IF NOT EXISTS luna_processes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL,
    process_key VARCHAR(190) NOT NULL,
    description TEXT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'draft',
    default_mode VARCHAR(40) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_luna_processes_workspace_key (workspace_id, process_key),
    INDEX idx_luna_processes_workspace (workspace_id),
    INDEX idx_luna_processes_status (status)
);

CREATE TABLE IF NOT EXISTS luna_process_steps (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    process_id BIGINT UNSIGNED NOT NULL,
    position INT UNSIGNED NOT NULL DEFAULT 0,
    name VARCHAR(190) NOT NULL,
    step_type VARCHAR(80) NOT NULL,
    reference_type VARCHAR(80) NULL,
    reference_id BIGINT UNSIGNED NULL,
    config_json LONGTEXT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    continue_on_error TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_luna_process_steps_process (process_id),
    INDEX idx_luna_process_steps_position (process_id, position)
);

CREATE TABLE IF NOT EXISTS luna_process_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    process_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'queued',
    mode VARCHAR(40) NOT NULL DEFAULT 'run',
    trigger_type VARCHAR(40) NOT NULL DEFAULT 'manual',
    trigger_ref VARCHAR(190) NULL,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    duration_ms INT UNSIGNED NULL,
    error_message TEXT NULL,
    context_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_luna_process_runs_process (process_id),
    INDEX idx_luna_process_runs_status (status),
    INDEX idx_luna_process_runs_started (started_at)
);

CREATE TABLE IF NOT EXISTS luna_process_run_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    process_run_id BIGINT UNSIGNED NOT NULL,
    level VARCHAR(40) NOT NULL,
    message TEXT NOT NULL,
    context_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_luna_process_run_logs_run (process_run_id),
    INDEX idx_luna_process_run_logs_level (level)
);
