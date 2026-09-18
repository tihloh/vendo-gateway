<?php
declare(strict_types=1);

namespace Tihloh\VendoGateway\Auth;

use Tihloh\VendoGateway\Device\Device;
use Tihloh\VendoGateway\Device\DeviceRepository;

final readonly class DeviceAuthenticator
{
    public function __construct(private DeviceRepository $devices) {}

    public function authenticate(string $deviceId,string $token): Device
    {
        $device=$this->devices->find($deviceId);
        if(!$device||$device->state!=='active')throw new \RuntimeException('Device authentication failed.');
        $secret=$this->devices->secret($deviceId);
        if(!$secret||$token===''||!hash_equals($secret,$token))throw new \RuntimeException('Device authentication failed.');
        return$device;
    }
}
