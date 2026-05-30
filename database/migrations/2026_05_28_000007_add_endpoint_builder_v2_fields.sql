ALTER TABLE luna_endpoints
    ADD COLUMN secret_mode VARCHAR(30) NOT NULL DEFAULT 'none' AFTER status,
    ADD COLUMN secret_hash VARCHAR(255) NULL AFTER secret_mode,
    ADD COLUMN cache_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER config_json,
    ADD COLUMN cache_ttl_seconds INT UNSIGNED NULL AFTER cache_enabled;
