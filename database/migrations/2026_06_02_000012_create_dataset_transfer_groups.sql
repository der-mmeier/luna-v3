CREATE TABLE IF NOT EXISTS luna_dataset_transfer_groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transfer_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL,
    group_type VARCHAR(40) NOT NULL DEFAULT 'root',
    source_path VARCHAR(190) NOT NULL DEFAULT '$',
    target_table VARCHAR(190) NOT NULL,
    operation_type VARCHAR(40) NOT NULL DEFAULT 'upsert',
    upsert_key VARCHAR(500) NULL,
    parent_link_source VARCHAR(190) NULL,
    parent_link_target VARCHAR(190) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_luna_dataset_transfer_groups_transfer_id (transfer_id),
    INDEX idx_luna_dataset_transfer_groups_sort_order (sort_order),
    CONSTRAINT fk_luna_dataset_transfer_groups_transfer
        FOREIGN KEY (transfer_id) REFERENCES luna_dataset_transfers(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE luna_dataset_transfer_fields
    ADD COLUMN group_id BIGINT UNSIGNED NULL AFTER transfer_id,
    ADD INDEX idx_luna_dataset_transfer_fields_group_id (group_id),
    ADD CONSTRAINT fk_luna_dataset_transfer_fields_group
        FOREIGN KEY (group_id) REFERENCES luna_dataset_transfer_groups(id)
        ON DELETE CASCADE;
