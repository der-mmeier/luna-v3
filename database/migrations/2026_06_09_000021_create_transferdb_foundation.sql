ALTER TABLE luna_workspaces
    ADD COLUMN transfer_db_connection_id BIGINT UNSIGNED NULL AFTER status,
    ADD INDEX idx_luna_workspaces_transfer_db_connection (transfer_db_connection_id);

ALTER TABLE luna_connection_profiles
    ADD INDEX idx_luna_connection_profiles_type (type);
