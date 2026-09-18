CREATE TABLE IF NOT EXISTS vg_devices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(64) NOT NULL,
    hardware_uid VARCHAR(128) NULL,
    device_secret_hash VARCHAR(255) NOT NULL,
    device_secret_encrypted TEXT NULL,
    hardware_model VARCHAR(100) NULL,
    hardware_revision VARCHAR(50) NULL,
    firmware_version VARCHAR(50) NULL,
    state ENUM('active','suspended','revoked') NOT NULL DEFAULT 'active',
    last_seen_at DATETIME NULL,
    last_ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_vg_devices_device_id (device_id),
    KEY idx_vg_devices_hardware_uid (hardware_uid),
    KEY idx_vg_devices_state (state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vg_pairings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pairing_id VARCHAR(64) NOT NULL,
    pairing_code VARCHAR(12) NOT NULL,
    pairing_token_hash VARCHAR(255) NOT NULL,
    hardware_uid VARCHAR(128) NULL,
    hardware_model VARCHAR(100) NULL,
    hardware_revision VARCHAR(50) NULL,
    firmware_version VARCHAR(50) NULL,
    capabilities_json JSON NULL,
    status ENUM('pending','claimed','completed','expired','cancelled') NOT NULL DEFAULT 'pending',
    claimed_by VARCHAR(128) NULL,
    claimed_context_json JSON NULL,
    device_id VARCHAR(64) NULL,
    issued_device_secret_encrypted TEXT NULL,
    expires_at DATETIME NOT NULL,
    claimed_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_vg_pairings_pairing_id (pairing_id),
    UNIQUE KEY uq_vg_pairings_pairing_code (pairing_code),
    KEY idx_vg_pairings_status (status),
    KEY idx_vg_pairings_hardware_uid (hardware_uid),
    KEY idx_vg_pairings_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vg_device_capabilities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(64) NOT NULL,
    capability VARCHAR(80) NOT NULL,
    metadata_json JSON NULL,
    reported_at DATETIME NOT NULL,
    UNIQUE KEY uq_vg_device_capability (device_id, capability),
    CONSTRAINT fk_vg_capability_device
        FOREIGN KEY (device_id) REFERENCES vg_devices(device_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
