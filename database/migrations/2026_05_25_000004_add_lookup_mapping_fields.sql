ALTER TABLE luna_mapping_fields
    ADD COLUMN lookup_connection_id BIGINT UNSIGNED NULL AFTER default_value,
    ADD COLUMN lookup_table VARCHAR(190) NULL AFTER lookup_connection_id,
    ADD COLUMN lookup_key_column VARCHAR(190) NULL AFTER lookup_table,
    ADD COLUMN lookup_value_column VARCHAR(190) NULL AFTER lookup_key_column,
    ADD COLUMN lookup_key_template VARCHAR(255) NULL AFTER lookup_value_column,
    ADD COLUMN fallback_value TEXT NULL AFTER lookup_key_template,
    ADD COLUMN missing_behavior VARCHAR(50) NOT NULL DEFAULT 'error' AFTER fallback_value,
    ADD INDEX idx_luna_mapping_fields_lookup_connection_id (lookup_connection_id),
    ADD CONSTRAINT fk_luna_mapping_fields_lookup_connection
        FOREIGN KEY (lookup_connection_id) REFERENCES luna_connection_profiles(id)
        ON DELETE SET NULL;
