<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Http;
use Tihloh\VendoGateway\Config\ConfigService;
final class ConfigEndpoint
{
    public function __construct(private DeviceAuth $auth,private ConfigService $configs) {}
    public function pull(array $headers,string $rawBody,string $method,string $path,?string $revision=null): array
    {
        $device=$this->auth->authenticate($headers,$rawBody,$method,$path);return $this->configs->pull($device->deviceId,$revision);
    }
}
