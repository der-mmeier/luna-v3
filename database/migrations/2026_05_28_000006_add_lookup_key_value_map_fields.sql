ALTER TABLE luna_mapping_fields
    ADD COLUMN lookup_result_key_column VARCHAR(190) NULL AFTER lookup_result_limit,
    ADD COLUMN lookup_result_key_transform VARCHAR(50) NOT NULL DEFAULT 'none' AFTER lookup_result_key_column,
    ADD COLUMN lookup_result_key_prefix_template VARCHAR(255) NULL AFTER lookup_result_key_transform;
