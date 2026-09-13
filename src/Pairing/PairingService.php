<?php
declare(strict_types=1);

namespace Tihloh\VendoGateway\Pairing;

use Tihloh\VendoGateway\Device\DeviceRepository;
use Tihloh\VendoGateway\Security\SecretProtector;
use Tihloh\VendoGateway\Support\Clock;
use Tihloh\VendoGateway\Support\Random;

final class PairingService
{
    public function __construct(
        private PairingRepository $pairings,
        private DeviceRepository $devices,
        private SecretProtector $protector,
        private Clock $clock,
        private int $ttlSeconds = 600
    ) {}

    public function begin(array $deviceInfo): array
    {
        $now = $this->clock->now();
        $pairingId = Random::id('PAIR-', 8);
        $pairingToken = Random::token(32);
        $pairingCode = Random::numericCode(6);

        $this->pairings->create([
            'pairing_id' => $pairingId,
            'pairing_code' => $pairingCode,
            'pairing_token' => $pairingToken,
            'hardware_uid' => $deviceInfo['hardware_uid'] ?? null,
            'hardware_model' => $deviceInfo['hardware_model'] ?? null,
            'hardware_revision' => $deviceInfo['hardware_revision'] ?? null,
            'firmware_version' => $deviceInfo['firmware_version'] ?? null,
            'capabilities' => $deviceInfo['capabilities'] ?? [],
            'expires_at' => $now->modify("+{$this->ttlSeconds} seconds"),
            'created_at' => $now
        ]);

        return [
            'status' => 'pairing_required',
            'pairing_id' => $pairingId,
            'pairing_token' => $pairingToken,
            'pairing_code' => $pairingCode,
            'expires_in' => $this->ttlSeconds
        ];
    }

    public function claim(string $pairingCode, string $claimedBy, array $context = []): array
    {
        $row = $this->pairings->findPendingByCode($pairingCode);
        if (!$row) {
            throw new \RuntimeException('Invalid or expired pairing code.');
        }

        $now = $this->clock->now();
        $this->pairings->claim($row['pairing_id'], $claimedBy, $context, $now);

        $deviceId = Random::id('DEV-', 10);
        $deviceSecret = Random::token(32);

        $this->devices->create(
            $deviceId,
            $row['hardware_uid'] ?: null,
            $deviceSecret,
            $row['hardware_model'] ?: null,
            $row['hardware_revision'] ?: null,
            $row['firmware_version'] ?: null
        );

        $capabilities = json_decode($row['capabilities_json'] ?: '{}', true) ?: [];
        if ($capabilities) {
            $this->devices->replaceCapabilities($deviceId, $capabilities, $now);
        }

        $this->pairings->complete(
            $row['pairing_id'],
            $deviceId,
            $this->protector->encrypt($deviceSecret),
            $now
        );

        return [
            'status' => 'claimed',
            'pairing_id' => $row['pairing_id'],
            'device_id' => $deviceId
        ];
    }

    public function status(string $pairingId, string $pairingToken): array
    {
        $row = $this->pairings->findByToken($pairingId, $pairingToken);
        if (!$row) {
            throw new \RuntimeException('Invalid pairing credentials.');
        }

        $now = $this->clock->now();
        $expiresAt = new \DateTimeImmutable($row['expires_at'], new \DateTimeZone('UTC'));
        if ($expiresAt <= $now && $row['status'] === 'pending') {
            return ['status' => 'expired'];
        }

        if ($row['status'] !== 'completed') {
            return ['status' => $row['status']];
        }

        if (!$row['issued_device_secret_encrypted']) {
            return [
                'status' => 'registered',
                'device_id' => $row['device_id'],
                'credentials_delivered' => true
            ];
        }

        $secret = $this->protector->decrypt($row['issued_device_secret_encrypted']);
        $this->pairings->markDelivered($pairingId, $now);

        return [
            'status' => 'registered',
            'device_id' => $row['device_id'],
            'device_secret' => $secret,
            'credentials_delivered' => false
        ];
    }
}
