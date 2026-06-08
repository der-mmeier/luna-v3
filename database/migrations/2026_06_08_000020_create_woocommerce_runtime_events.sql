CREATE TABLE IF NOT EXISTS luna_woocommerce_runtime_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NULL,
    process_trigger_id BIGINT UNSIGNED NULL,
    process_run_id BIGINT UNSIGNED NULL,
    provider VARCHAR(40) NOT NULL DEFAULT 'woocommerce',
    topic VARCHAR(120) NOT NULL,
    resource VARCHAR(80) NULL,
    event_action VARCHAR(80) NULL,
    delivery_id VARCHAR(190) NULL,
    webhook_id VARCHAR(190) NULL,
    source_domain VARCHAR(255) NULL,
    source_order_id VARCHAR(80) NULL,
    signature_valid TINYINT(1) NOT NULL DEFAULT 0,
    payload_size INT UNSIGNED NOT NULL DEFAULT 0,
    payload_hash VARCHAR(64) NULL,
    payload_summary_json LONGTEXT NULL,
    payload_meta_json LONGTEXT NULL,
    processing_status VARCHAR(40) NOT NULL DEFAULT 'received',
    processing_message TEXT NULL,
    received_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_luna_wc_runtime_events_trigger (process_trigger_id),
    INDEX idx_luna_wc_runtime_events_run (process_run_id),
    INDEX idx_luna_wc_runtime_events_topic (topic),
    INDEX idx_luna_wc_runtime_events_delivery (delivery_id),
    INDEX idx_luna_wc_runtime_events_order (source_order_id)
);

ALTER TABLE luna_process_triggers
    ADD COLUMN secret_encrypted LONGTEXT NULL AFTER secret_hash;
