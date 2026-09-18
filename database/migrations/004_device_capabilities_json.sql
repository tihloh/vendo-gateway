ALTER TABLE vg_devices
    ADD COLUMN IF NOT EXISTS capabilities_json JSON NULL AFTER firmware_version;

UPDATE vg_devices d
JOIN (
    SELECT
        device_id,
        CONCAT(
            '{',
            GROUP_CONCAT(
                CONCAT(JSON_QUOTE(capability), ':', COALESCE(NULLIF(metadata_json, ''), 'null'))
                ORDER BY capability SEPARATOR ','
            ),
            '}'
        ) AS capabilities_json
    FROM vg_device_capabilities
    GROUP BY device_id
) c ON c.device_id=d.device_id
SET d.capabilities_json=c.capabilities_json
WHERE d.capabilities_json IS NULL;

DROP TABLE IF EXISTS vg_device_capabilities;
