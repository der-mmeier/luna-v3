CREATE TABLE IF NOT EXISTS luna_dataset_transfers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NULL,
    name VARCHAR(190) NOT NULL,
    description TEXT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'draft',
    source_dataset VARCHAR(190) NOT NULL,
    target_connection_id BIGINT UNSIGNED NULL,
    target_table VARCHAR(190) NULL,
    operation_type VARCHAR(40) NOT NULL DEFAULT 'upsert',
    upsert_key VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_luna_dataset_transfers_workspace_id (workspace_id),
    INDEX idx_luna_dataset_transfers_source_dataset (source_dataset),
    INDEX idx_luna_dataset_transfers_target_connection_id (target_connection_id),
    CONSTRAINT fk_luna_dataset_transfers_workspace
        FOREIGN KEY (workspace_id) REFERENCES luna_workspaces(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_luna_dataset_transfers_target_connection
        FOREIGN KEY (target_connection_id) REFERENCES luna_connection_profiles(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS luna_dataset_transfer_fields (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transfer_id BIGINT UNSIGNED NOT NULL,
    dataset_field VARCHAR(190) NOT NULL,
    target_column VARCHAR(190) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_luna_dataset_transfer_fields_transfer_id (transfer_id),
    INDEX idx_luna_dataset_transfer_fields_sort_order (sort_order),
    CONSTRAINT fk_luna_dataset_transfer_fields_transfer
        FOREIGN KEY (transfer_id) REFERENCES luna_dataset_transfers(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
