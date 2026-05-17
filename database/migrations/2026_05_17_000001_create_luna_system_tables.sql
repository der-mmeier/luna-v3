CREATE TABLE IF NOT EXISTS luna_migrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL UNIQUE,
    batch INT UNSIGNED NOT NULL DEFAULT 1,
    executed_at DATETIME NOT NULL,
    INDEX idx_luna_migrations_executed_at (executed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS luna_workspaces (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(120) NOT NULL UNIQUE,
    name VARCHAR(190) NOT NULL,
    description TEXT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_luna_workspaces_status (status),
    INDEX idx_luna_workspaces_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS luna_connection_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NULL,
    name VARCHAR(190) NOT NULL,
    type VARCHAR(50) NOT NULL,
    driver VARCHAR(50) NOT NULL,
    host VARCHAR(190) NULL,
    port INT UNSIGNED NULL,
    database_name VARCHAR(190) NULL,
    username VARCHAR(190) NULL,
    read_only TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    config_json LONGTEXT NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_luna_connection_profiles_workspace_id (workspace_id),
    INDEX idx_luna_connection_profiles_created_at (created_at),
    CONSTRAINT fk_luna_connection_profiles_workspace
        FOREIGN KEY (workspace_id) REFERENCES luna_workspaces(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS luna_connection_secrets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    connection_profile_id BIGINT UNSIGNED NOT NULL,
    secret_key VARCHAR(120) NOT NULL,
    secret_value_encrypted LONGTEXT NOT NULL,
    encryption_version VARCHAR(50) NOT NULL DEFAULT 'v1',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_luna_connection_secrets_profile_key (connection_profile_id, secret_key),
    INDEX idx_luna_connection_secrets_connection_profile_id (connection_profile_id),
    INDEX idx_luna_connection_secrets_created_at (created_at),
    CONSTRAINT fk_luna_connection_secrets_profile
        FOREIGN KEY (connection_profile_id) REFERENCES luna_connection_profiles(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS luna_schema_snapshots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    connection_profile_id BIGINT UNSIGNED NOT NULL,
    schema_name VARCHAR(190) NULL,
    snapshot_json LONGTEXT NOT NULL,
    checksum CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_luna_schema_snapshots_connection_profile_id (connection_profile_id),
    INDEX idx_luna_schema_snapshots_created_at (created_at),
    CONSTRAINT fk_luna_schema_snapshots_profile
        FOREIGN KEY (connection_profile_id) REFERENCES luna_connection_profiles(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS luna_table_notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NULL,
    connection_profile_id BIGINT UNSIGNED NOT NULL,
    schema_name VARCHAR(190) NULL,
    table_name VARCHAR(190) NOT NULL,
    note TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_luna_table_notes_workspace_id (workspace_id),
    INDEX idx_luna_table_notes_connection_profile_id (connection_profile_id),
    INDEX idx_luna_table_notes_table_name (table_name),
    INDEX idx_luna_table_notes_created_at (created_at),
    CONSTRAINT fk_luna_table_notes_workspace
        FOREIGN KEY (workspace_id) REFERENCES luna_workspaces(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_luna_table_notes_profile
        FOREIGN KEY (connection_profile_id) REFERENCES luna_connection_profiles(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS luna_column_notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NULL,
    connection_profile_id BIGINT UNSIGNED NOT NULL,
    schema_name VARCHAR(190) NULL,
    table_name VARCHAR(190) NOT NULL,
    column_name VARCHAR(190) NOT NULL,
    note TEXT NULL,
    example_value TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_luna_column_notes_workspace_id (workspace_id),
    INDEX idx_luna_column_notes_connection_profile_id (connection_profile_id),
    INDEX idx_luna_column_notes_table_name (table_name),
    INDEX idx_luna_column_notes_column_name (column_name),
    INDEX idx_luna_column_notes_created_at (created_at),
    CONSTRAINT fk_luna_column_notes_workspace
        FOREIGN KEY (workspace_id) REFERENCES luna_workspaces(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_luna_column_notes_profile
        FOREIGN KEY (connection_profile_id) REFERENCES luna_connection_profiles(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS luna_mapping_sets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NULL,
    name VARCHAR(190) NOT NULL,
    description TEXT NULL,
    source_connection_id BIGINT UNSIGNED NULL,
    source_table VARCHAR(190) NULL,
    target_connection_id BIGINT UNSIGNED NULL,
    target_table VARCHAR(190) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'draft',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_luna_mapping_sets_workspace_id (workspace_id),
    INDEX idx_luna_mapping_sets_source_connection_id (source_connection_id),
    INDEX idx_luna_mapping_sets_target_connection_id (target_connection_id),
    INDEX idx_luna_mapping_sets_created_at (created_at),
    CONSTRAINT fk_luna_mapping_sets_workspace
        FOREIGN KEY (workspace_id) REFERENCES luna_workspaces(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_luna_mapping_sets_source_connection
        FOREIGN KEY (source_connection_id) REFERENCES luna_connection_profiles(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_luna_mapping_sets_target_connection
        FOREIGN KEY (target_connection_id) REFERENCES luna_connection_profiles(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS luna_mapping_fields (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mapping_set_id BIGINT UNSIGNED NOT NULL,
    source_column VARCHAR(190) NULL,
    source_json_path VARCHAR(255) NULL,
    target_column VARCHAR(190) NOT NULL,
    transform_type VARCHAR(80) NOT NULL DEFAULT 'direct',
    default_value TEXT NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_luna_mapping_fields_mapping_set_id (mapping_set_id),
    INDEX idx_luna_mapping_fields_created_at (created_at),
    CONSTRAINT fk_luna_mapping_fields_set
        FOREIGN KEY (mapping_set_id) REFERENCES luna_mapping_sets(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS luna_mapping_value_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mapping_field_id BIGINT UNSIGNED NOT NULL,
    source_value VARCHAR(255) NOT NULL,
    target_value VARCHAR(255) NOT NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_luna_mapping_value_rules_mapping_field_id (mapping_field_id),
    INDEX idx_luna_mapping_value_rules_created_at (created_at),
    CONSTRAINT fk_luna_mapping_value_rules_field
        FOREIGN KEY (mapping_field_id) REFERENCES luna_mapping_fields(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS luna_audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NULL,
    actor_type VARCHAR(50) NOT NULL DEFAULT 'system',
    actor_id VARCHAR(120) NULL,
    action VARCHAR(120) NOT NULL,
    entity_type VARCHAR(120) NULL,
    entity_id VARCHAR(120) NULL,
    message TEXT NULL,
    context_json LONGTEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_luna_audit_log_workspace_id (workspace_id),
    INDEX idx_luna_audit_log_action (action),
    INDEX idx_luna_audit_log_created_at (created_at),
    CONSTRAINT fk_luna_audit_log_workspace
        FOREIGN KEY (workspace_id) REFERENCES luna_workspaces(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
