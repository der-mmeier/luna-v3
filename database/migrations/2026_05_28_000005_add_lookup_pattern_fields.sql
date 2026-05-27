ALTER TABLE luna_mapping_fields
    ADD COLUMN lookup_match_mode VARCHAR(50) NOT NULL DEFAULT 'exact' AFTER lookup_key_template,
    ADD COLUMN lookup_result_mode VARCHAR(50) NOT NULL DEFAULT 'first' AFTER lookup_match_mode,
    ADD COLUMN lookup_result_limit INT UNSIGNED NULL AFTER lookup_result_mode;
