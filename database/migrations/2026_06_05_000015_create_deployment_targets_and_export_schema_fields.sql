CREATE TABLE IF NOT EXISTS luna_deployment_targets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    environment VARCHAR(40) NOT NULL,
    public_base_url VARCHAR(500) NOT NULL,
    endpoint_base_url VARCHAR(500) NULL,
    webhook_base_url VARCHAR(500) NULL,
    license_server_url VARCHAR(500) NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    origin VARCHAR(40) NOT NULL DEFAULT 'customer_created',
    support_status VARCHAR(40) NOT NULL DEFAULT 'unverified',
    module_key VARCHAR(120) NULL,
    requires_entitlement TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_luna_deployment_targets_workspace (workspace_id),
    INDEX idx_luna_deployment_targets_environment (environment),
    INDEX idx_luna_deployment_targets_active (is_active),
    INDEX idx_luna_deployment_targets_default (workspace_id, environment, is_default)
);

ALTER TABLE luna_mapping_fields
    ADD COLUMN schema_type VARCHAR(40) NULL AFTER notes,
    ADD COLUMN schema_required TINYINT(1) NOT NULL DEFAULT 0 AFTER schema_type,
    ADD COLUMN schema_description TEXT NULL AFTER schema_required,
    ADD COLUMN schema_example TEXT NULL AFTER schema_description;
