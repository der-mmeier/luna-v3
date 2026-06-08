CREATE TABLE IF NOT EXISTS luna_schemas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    schema_key VARCHAR(190) NOT NULL,
    version VARCHAR(40) NOT NULL,
    name VARCHAR(190) NOT NULL,
    description TEXT NULL,
    definition_json LONGTEXT NOT NULL,
    example_json LONGTEXT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'draft',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_luna_schemas_workspace_key_version (workspace_id, schema_key, version),
    INDEX idx_luna_schemas_workspace (workspace_id),
    INDEX idx_luna_schemas_key (schema_key),
    INDEX idx_luna_schemas_status (status)
);

CREATE TABLE IF NOT EXISTS luna_schema_revisions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schema_id BIGINT UNSIGNED NOT NULL,
    version VARCHAR(40) NOT NULL,
    definition_json LONGTEXT NOT NULL,
    example_json LONGTEXT NULL,
    change_summary TEXT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_luna_schema_revisions_schema (schema_id),
    INDEX idx_luna_schema_revisions_version (schema_id, version)
);

ALTER TABLE luna_endpoints
    ADD COLUMN schema_id BIGINT UNSIGNED NULL AFTER mapping_set_id,
    ADD INDEX idx_luna_endpoints_schema_id (schema_id);
