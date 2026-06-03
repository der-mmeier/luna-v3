CREATE TABLE IF NOT EXISTS luna_woocommerce_connections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NULL,
    connection_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL,
    connection_token VARCHAR(80) NOT NULL,
    detected_table_prefix VARCHAR(80) NULL,
    detected_woocommerce_version VARCHAR(40) NULL,
    storage_mode VARCHAR(40) NOT NULL DEFAULT 'hpos',
    hpos_enabled TINYINT(1) NOT NULL DEFAULT 0,
    hpos_authoritative TINYINT(1) NOT NULL DEFAULT 0,
    hpos_data_caching_allowed TINYINT(1) NOT NULL DEFAULT 0,
    hpos_data_caching_warning_acknowledged TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_luna_woocommerce_connections_token (connection_token),
    INDEX idx_luna_woocommerce_connections_workspace_id (workspace_id),
    INDEX idx_luna_woocommerce_connections_connection_id (connection_id),
    CONSTRAINT fk_luna_woocommerce_connections_workspace
        FOREIGN KEY (workspace_id) REFERENCES luna_workspaces(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_luna_woocommerce_connections_connection
        FOREIGN KEY (connection_id) REFERENCES luna_connection_profiles(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS luna_woocommerce_webhook_configs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NULL,
    woocommerce_connection_id BIGINT UNSIGNED NOT NULL,
    webhook_name VARCHAR(190) NOT NULL,
    topic VARCHAR(80) NOT NULL,
    delivery_url VARCHAR(500) NOT NULL,
    secret_encrypted TEXT NULL,
    expected_status VARCHAR(40) NOT NULL DEFAULT 'active',
    api_version VARCHAR(80) NOT NULL DEFAULT 'WP REST API Integration v3',
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    last_seen_status VARCHAR(80) NULL,
    last_seen_webhook_id VARCHAR(80) NULL,
    last_seen_at DATETIME NULL,
    validation_status VARCHAR(40) NOT NULL DEFAULT 'unknown',
    validation_message TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_luna_woocommerce_webhook_configs_connection_id (woocommerce_connection_id),
    INDEX idx_luna_woocommerce_webhook_configs_topic (topic),
    CONSTRAINT fk_luna_woocommerce_webhook_configs_connection
        FOREIGN KEY (woocommerce_connection_id) REFERENCES luna_woocommerce_connections(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS luna_woocommerce_webhook_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NULL,
    woocommerce_connection_id BIGINT UNSIGNED NOT NULL,
    topic VARCHAR(80) NOT NULL,
    resource VARCHAR(80) NULL,
    event_action VARCHAR(80) NULL,
    source_order_id VARCHAR(80) NULL,
    delivery_id VARCHAR(190) NULL,
    signature_valid TINYINT(1) NOT NULL DEFAULT 0,
    raw_headers_json LONGTEXT NULL,
    raw_payload_json LONGTEXT NULL,
    received_at DATETIME NOT NULL,
    processed_at DATETIME NULL,
    processing_status VARCHAR(40) NOT NULL DEFAULT 'received',
    processing_message TEXT NULL,
    created_transfer_job_id BIGINT UNSIGNED NULL,
    INDEX idx_luna_woocommerce_webhook_events_connection_id (woocommerce_connection_id),
    INDEX idx_luna_woocommerce_webhook_events_source_order_id (source_order_id),
    INDEX idx_luna_woocommerce_webhook_events_delivery_id (delivery_id),
    CONSTRAINT fk_luna_woocommerce_webhook_events_connection
        FOREIGN KEY (woocommerce_connection_id) REFERENCES luna_woocommerce_connections(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS luna_woocommerce_transfer_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NULL,
    woocommerce_connection_id BIGINT UNSIGNED NOT NULL,
    webhook_event_id BIGINT UNSIGNED NULL,
    source_order_id VARCHAR(80) NOT NULL,
    topic VARCHAR(80) NOT NULL,
    reason VARCHAR(190) NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_luna_woocommerce_transfer_queue_connection_id (woocommerce_connection_id),
    INDEX idx_luna_woocommerce_transfer_queue_source_order_id (source_order_id),
    INDEX idx_luna_woocommerce_transfer_queue_status (status),
    CONSTRAINT fk_luna_woocommerce_transfer_queue_connection
        FOREIGN KEY (woocommerce_connection_id) REFERENCES luna_woocommerce_connections(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_luna_woocommerce_transfer_queue_event
        FOREIGN KEY (webhook_event_id) REFERENCES luna_woocommerce_webhook_events(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
