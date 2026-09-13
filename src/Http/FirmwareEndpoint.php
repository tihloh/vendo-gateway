<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Http;
use Tihloh\VendoGateway\Firmware\FirmwareService;
final class FirmwareEndpoint
{
    public function __construct(private DeviceAuth $auth,private FirmwareService $firmware) {}
    public function check(array $headers,string $rawBody,string $method,string $path): array
    {
        $device=$this->auth->authenticate($headers,$rawBody,$method,$path);$payload=json_decode($rawBody,true,flags:JSON_THROW_ON_ERROR);return $this->firmware->check((string)($payload['hardware_model']??$device->hardwareModel),(string)($payload['hardware_revision']??$device->hardwareRevision),(string)($payload['channel']??'stable'),(string)($payload['current_version']??$device->firmwareVersion??'0.0.0'));
    }
}
