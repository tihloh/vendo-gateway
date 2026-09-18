<?php
declare(strict_types=1);

namespace Tihloh\VendoGateway\Http;

use Tihloh\VendoGateway\Heartbeat\HeartbeatService;

final class HeartbeatEndpoint
{
    public function __construct(
        private DeviceAuth $auth,
        private HeartbeatService $heartbeats
    ) {}

    public function handle(
        array $headers,
        string $rawBody,
        string $method,
        string $path,
        ?string $ip=null
    ): array {
        $device=$this->auth->authenticate($headers,$rawBody,$method,$path);
        $payload=json_decode($rawBody,true,flags:JSON_THROW_ON_ERROR);
        return$this->heartbeats->record($device->deviceId,$payload,$ip);
    }
}
