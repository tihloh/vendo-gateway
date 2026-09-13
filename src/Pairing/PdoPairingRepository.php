<?php
declare(strict_types=1);

namespace Tihloh\VendoGateway\Pairing;

use PDO;

final class PdoPairingRepository implements PairingRepository
{
    public function __construct(private PDO $pdo) {}

    public function create(array $data): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO vg_pairings
            (pairing_id, pairing_code, pairing_token_hash, hardware_uid, hardware_model, hardware_revision, firmware_version,
             capabilities_json, status, expires_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, "pending", ?, ?, ?)'
        );
        $stmt->execute([
            $data['pairing_id'],
            $data['pairing_code'],
            password_hash($data['pairing_token'], PASSWORD_DEFAULT),
            $data['hardware_uid'],
            $data['hardware_model'],
            $data['hardware_revision'],
            $data['firmware_version'],
            json_encode($data['capabilities'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $data['expires_at']->format('Y-m-d H:i:s'),
            $data['created_at']->format('Y-m-d H:i:s'),
            $data['created_at']->format('Y-m-d H:i:s')
        ]);
    }

    public function findByToken(string $pairingId, string $pairingToken): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM vg_pairings WHERE pairing_id = ? LIMIT 1');
        $stmt->execute([$pairingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !password_verify($pairingToken, $row['pairing_token_hash'])) {
            return null;
        }
        return $row;
    }

    public function findPendingByCode(string $pairingCode): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM vg_pairings
             WHERE pairing_code = ? AND status = "pending" AND expires_at > UTC_TIMESTAMP()
             LIMIT 1'
        );
        $stmt->execute([$pairingCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function claim(string $pairingId, string $claimedBy, array $context, \DateTimeImmutable $at): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE vg_pairings
             SET status = "claimed", claimed_by = ?, claimed_context_json = ?, claimed_at = ?, updated_at = ?
             WHERE pairing_id = ? AND status = "pending"'
        );
        $date = $at->format('Y-m-d H:i:s');
        $stmt->execute([
            $claimedBy,
            json_encode($context, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $date,
            $date,
            $pairingId
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('Pairing is no longer claimable.');
        }
    }

    public function complete(
        string $pairingId,
        string $deviceId,
        string $encryptedDeviceSecret,
        \DateTimeImmutable $at
    ): void {
        $stmt = $this->pdo->prepare(
            'UPDATE vg_pairings
             SET status = "completed", device_id = ?, issued_device_secret_encrypted = ?, updated_at = ?
             WHERE pairing_id = ? AND status = "claimed"'
        );
        $stmt->execute([
            $deviceId,
            $encryptedDeviceSecret,
            $at->format('Y-m-d H:i:s'),
            $pairingId
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('Pairing completion failed.');
        }
    }

    public function markDelivered(string $pairingId, \DateTimeImmutable $at): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE vg_pairings
             SET issued_device_secret_encrypted = NULL, updated_at = ?
             WHERE pairing_id = ?'
        );
        $stmt->execute([$at->format('Y-m-d H:i:s'), $pairingId]);
    }
}
