CREATE TABLE IF NOT EXISTS vg_device_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    profile_key VARCHAR(80) NOT NULL,
    name VARCHAR(120) NOT NULL,
    config_json JSON NOT NULL,
    required_capabilities_json JSON NULL,
    firmware_channel VARCHAR(50) NOT NULL DEFAULT 'stable',
    config_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_vg_profile_key (profile_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE vg_devices
    ADD COLUMN IF NOT EXISTS profile_key VARCHAR(80) NULL AFTER state,
    ADD COLUMN IF NOT EXISTS config_version BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER firmware_version,
    ADD COLUMN IF NOT EXISTS reported_state_json JSON NULL AFTER last_ip,
    ADD COLUMN IF NOT EXISTS desired_state_json JSON NULL AFTER reported_state_json;

CREATE TABLE IF NOT EXISTS vg_device_configs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(64) NOT NULL,
    config_version BIGINT UNSIGNED NOT NULL,
    config_json JSON NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_vg_device_config_version (device_id, config_version),
    KEY idx_vg_device_configs_device (device_id),
    CONSTRAINT fk_vg_config_device FOREIGN KEY (device_id) REFERENCES vg_devices(device_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vg_device_commands (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    command_id VARCHAR(64) NOT NULL,
    device_id VARCHAR(64) NOT NULL,
    type VARCHAR(100) NOT NULL,
    payload_json JSON NULL,
    status ENUM('pending','delivered','acked','failed','expired','cancelled') NOT NULL DEFAULT 'pending',
    priority SMALLINT NOT NULL DEFAULT 0,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL,
    expires_at DATETIME NULL,
    delivered_at DATETIME NULL,
    acknowledged_at DATETIME NULL,
    result_json JSON NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_vg_command_id (command_id),
    KEY idx_vg_commands_poll (device_id, status, available_at, priority),
    CONSTRAINT fk_vg_command_device FOREIGN KEY (device_id) REFERENCES vg_devices(device_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vg_device_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(100) NOT NULL,
    device_id VARCHAR(64) NOT NULL,
    sequence_no BIGINT UNSIGNED NOT NULL,
    type VARCHAR(100) NOT NULL,
    payload_json JSON NULL,
    occurred_at DATETIME NULL,
    received_at DATETIME NOT NULL,
    processed_at DATETIME NULL,
    UNIQUE KEY uq_vg_event_id (event_id),
    UNIQUE KEY uq_vg_event_sequence (device_id, sequence_no),
    KEY idx_vg_events_device_type (device_id, type, received_at),
    CONSTRAINT fk_vg_event_device FOREIGN KEY (device_id) REFERENCES vg_devices(device_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vg_firmware (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    firmware_id VARCHAR(64) NOT NULL,
    hardware_model VARCHAR(100) NOT NULL,
    hardware_revision VARCHAR(50) NULL,
    channel VARCHAR(50) NOT NULL DEFAULT 'stable',
    version VARCHAR(50) NOT NULL,
    url TEXT NOT NULL,
    sha256 CHAR(64) NOT NULL,
    size_bytes BIGINT UNSIGNED NULL,
    mandatory TINYINT(1) NOT NULL DEFAULT 0,
    release_notes TEXT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_vg_firmware_id (firmware_id),
    UNIQUE KEY uq_vg_firmware_release (hardware_model, hardware_revision, channel, version),
    KEY idx_vg_firmware_lookup (hardware_model, channel, enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;