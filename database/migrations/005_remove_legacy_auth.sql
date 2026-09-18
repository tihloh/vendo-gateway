DROP TABLE IF EXISTS vg_device_nonces;

ALTER TABLE vg_devices
    DROP COLUMN IF EXISTS last_request_sequence,
    DROP COLUMN IF EXISTS auth_mode;
