<?php
declare(strict_types=1);

namespace Tihloh\VendoGateway\Heartbeat;

use Tihloh\VendoGateway\Device\DeviceRepository;
use Tihloh\VendoGateway\Support\Clock;

final class HeartbeatService
{
    public function __construct(
        private DeviceRepository $devices,
        private Clock $clock
    ) {}

    public function record(string $deviceId, array $payload, ?string $ip = null): array
    {
        $now = $this->clock->now();

        $this->devices->heartbeat(
            $deviceId,
            $payload['firmware_version'] ?? null,
            $ip,
            $now
        );

        if (isset($payload['capabilities']) && is_array($payload['capabilities'])) {
            $this->devices->replaceCapabilities(
                $deviceId,
                $payload['capabilities'],
                $now
            );
        }

        return [
            'ok' => true,
            'server_time' => $now->getTimestamp()
        ];
    }
}
