CREATE TABLE IF NOT EXISTS luna_mapping_source_filters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mapping_set_id BIGINT UNSIGNED NOT NULL,
    source_column VARCHAR(190) NOT NULL,
    operator VARCHAR(80) NOT NULL,
    filter_value TEXT NULL,
    value_type VARCHAR(50) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_luna_mapping_source_filters_mapping_set_id (mapping_set_id),
    INDEX idx_luna_mapping_source_filters_sort_order (sort_order),
    CONSTRAINT fk_luna_mapping_source_filters_set
        FOREIGN KEY (mapping_set_id) REFERENCES luna_mapping_sets(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
