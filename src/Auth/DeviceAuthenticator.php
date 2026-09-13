<?php
declare(strict_types=1);

namespace Tihloh\VendoGateway\Auth;

use Tihloh\VendoGateway\Device\Device;
use Tihloh\VendoGateway\Device\DeviceRepository;
use Tihloh\VendoGateway\Support\Clock;

final class DeviceAuthenticator
{
    public function __construct(
        private DeviceRepository $devices,
        private NonceRepository $nonces,
        private Clock $clock,
        private int $allowedClockSkewSeconds = 300
    ) {}

    public function authenticate(
        string $deviceId,
        int $timestamp,
        string $nonce,
        string $signature,
        string $method,
        string $path,
        string $body
    ): Device {
        $device = $this->devices->find($deviceId);
        if (!$device || $device->state !== 'active') {
            throw new \RuntimeException('Device authentication failed.');
        }

        $now = $this->clock->now();
        if (abs($now->getTimestamp() - $timestamp) > $this->allowedClockSkewSeconds) {
            throw new \RuntimeException('Request timestamp is outside the allowed window.');
        }

        $secret = $this->devices->secret($deviceId);
        if (!$secret) {
            throw new \RuntimeException('Device authentication failed.');
        }

        $expected = Signature::sign($secret, $method, $path, $timestamp, $nonce, $body);
        if (!hash_equals($expected, strtolower($signature))) {
            throw new \RuntimeException('Device authentication failed.');
        }

        $consumed = $this->nonces->consume(
            $deviceId,
            $nonce,
            $now,
            $now->modify("+{$this->allowedClockSkewSeconds} seconds")
        );
        if (!$consumed) {
            throw new \RuntimeException('Replay detected.');
        }

        return $device;
    }
}
