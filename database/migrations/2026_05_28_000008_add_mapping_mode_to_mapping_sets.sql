ALTER TABLE luna_mapping_sets
    ADD COLUMN mapping_mode VARCHAR(50) NOT NULL DEFAULT 'transfer' AFTER description,
    ADD INDEX idx_luna_mapping_sets_mapping_mode (mapping_mode);
