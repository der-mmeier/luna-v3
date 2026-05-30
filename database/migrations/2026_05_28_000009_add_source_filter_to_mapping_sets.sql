ALTER TABLE luna_mapping_sets
    ADD COLUMN source_filter_column VARCHAR(190) NULL AFTER source_table,
    ADD COLUMN source_filter_operator VARCHAR(50) NOT NULL DEFAULT 'none' AFTER source_filter_column,
    ADD COLUMN source_filter_value VARCHAR(190) NULL AFTER source_filter_operator;
