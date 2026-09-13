<?php
declare(strict_types=1);

namespace Tihloh\VendoGateway\Http;

use Tihloh\VendoGateway\Auth\DeviceAuthenticator;
use Tihloh\VendoGateway\Heartbeat\HeartbeatService;

final class HeartbeatEndpoint
{
    public function __construct(
        private DeviceAuthenticator $auth,
        private HeartbeatService $heartbeats
    ) {}

    public function handle(
        array $headers,
        string $rawBody,
        string $method,
        string $path,
        ?string $ip = null
    ): array {
        $deviceId = $headers['X-Vendo-Device'] ?? $headers['x-vendo-device'] ?? '';
        $timestamp = (int) ($headers['X-Vendo-Timestamp'] ?? $headers['x-vendo-timestamp'] ?? 0);
        $nonce = $headers['X-Vendo-Nonce'] ?? $headers['x-vendo-nonce'] ?? '';
        $signature = $headers['X-Vendo-Signature'] ?? $headers['x-vendo-signature'] ?? '';

        $device = $this->auth->authenticate(
            $deviceId,
            $timestamp,
            $nonce,
            $signature,
            $method,
            $path,
            $rawBody
        );

        $payload = json_decode($rawBody, true, flags: JSON_THROW_ON_ERROR);
        return $this->heartbeats->record($device->deviceId, $payload, $ip);
    }
}
