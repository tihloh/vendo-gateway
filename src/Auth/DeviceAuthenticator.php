<?php
declare(strict_types=1);

namespace Tihloh\VendoGateway\Auth;

use Tihloh\VendoGateway\Device\Device;
use Tihloh\VendoGateway\Device\DeviceRepository;

final class DeviceAuthenticator
{
    public function __construct(private DeviceRepository $devices) {}

    public function authenticateToken(string $deviceId,string $token): Device
    {
        $device=$this->devices->find($deviceId);
        if(!$device||$device->state!=='active'||$token==='')throw new \RuntimeException('Device authentication failed.');
        $secret=$this->devices->secret($deviceId);
        if(!$secret||!hash_equals($secret,$token))throw new \RuntimeException('Device authentication failed.');
        return$device;
    }
}
