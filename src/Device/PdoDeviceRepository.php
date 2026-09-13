<?php
declare(strict_types=1);

namespace Tihloh\VendoGateway\Device;

use PDO;
use Tihloh\VendoGateway\Security\SecretProtector;

final class PdoDeviceRepository implements DeviceRepository
{
    public function __construct(
        private PDO $pdo,
        private SecretProtector $protector
    ) {}

    public function find(string $deviceId): ?Device
    {
        $stmt = $this->pdo->prepare('SELECT * FROM vg_devices WHERE device_id = ? LIMIT 1');
        $stmt->execute([$deviceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->map($row) : null;
    }

    public function secret(string $deviceId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT device_secret_encrypted FROM vg_devices WHERE device_id = ? LIMIT 1');
        $stmt->execute([$deviceId]);
        $value = $stmt->fetchColumn();
        return is_string($value) && $value !== '' ? $this->protector->decrypt($value) : null;
    }

    public function create(
        string $deviceId,
        ?string $hardwareUid,
        string $deviceSecret,
        ?string $hardwareModel,
        ?string $hardwareRevision,
        ?string $firmwareVersion
    ): Device {
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO vg_devices
            (device_id, hardware_uid, device_secret_hash, device_secret_encrypted, hardware_model, hardware_revision, firmware_version, state, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, "active", ?, ?)'
        );
        $stmt->execute([
            $deviceId,
            $hardwareUid,
            password_hash($deviceSecret, PASSWORD_DEFAULT),
            $this->protector->encrypt($deviceSecret),
            $hardwareModel,
            $hardwareRevision,
            $firmwareVersion,
            $now,
            $now
        ]);
        return $this->find($deviceId) ?? throw new \RuntimeException('Device creation failed.');
    }

    public function heartbeat(
        string $deviceId,
        ?string $firmwareVersion,
        ?string $ip,
        \DateTimeImmutable $at
    ): void {
        $stmt = $this->pdo->prepare(
            'UPDATE vg_devices
             SET firmware_version = COALESCE(?, firmware_version),
                 last_seen_at = ?,
                 last_ip = ?,
                 updated_at = ?
             WHERE device_id = ?'
        );
        $date = $at->format('Y-m-d H:i:s');
        $stmt->execute([$firmwareVersion, $date, $ip, $date, $deviceId]);
    }

    public function replaceCapabilities(
        string $deviceId,
        array $capabilities,
        \DateTimeImmutable $reportedAt
    ): void {
        $this->pdo->beginTransaction();
        try {
            $delete = $this->pdo->prepare('DELETE FROM vg_device_capabilities WHERE device_id = ?');
            $delete->execute([$deviceId]);
            $insert = $this->pdo->prepare(
                'INSERT INTO vg_device_capabilities (device_id, capability, metadata_json, reported_at)
                 VALUES (?, ?, ?, ?)'
            );
            foreach ($capabilities as $name => $metadata) {
                $insert->execute([
                    $deviceId,
                    (string) $name,
                    json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    $reportedAt->format('Y-m-d H:i:s')
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function map(array $row): Device
    {
        return new Device(
            $row['device_id'],
            $row['hardware_uid'] ?: null,
            $row['state'],
            $row['hardware_model'] ?: null,
            $row['hardware_revision'] ?: null,
            $row['firmware_version'] ?: null,
            $row['last_seen_at'] ? new \DateTimeImmutable($row['last_seen_at'], new \DateTimeZone('UTC')) : null
        );
    }
}
