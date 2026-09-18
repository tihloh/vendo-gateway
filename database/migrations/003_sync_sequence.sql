ALTER TABLE vg_devices
    ADD COLUMN IF NOT EXISTS auth_mode ENUM('nonce','sequence') NOT NULL DEFAULT 'nonce' AFTER state,
    ADD COLUMN IF NOT EXISTS last_request_sequence DECIMAL(20,0) NOT NULL DEFAULT 0 AFTER auth_mode;
