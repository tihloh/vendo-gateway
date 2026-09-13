<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Device;
use Tihloh\VendoGateway\Support\Clock;
final class DeviceService
{
    public function __construct(private DeviceRepository $devices,private Clock $clock) {}
    public function get(string $deviceId): ?Device{return $this->devices->find($deviceId);}
    public function capabilities(string $deviceId): array{return $this->devices->capabilities($deviceId);}
    public function suspend(string $deviceId): void{$this->devices->setState($deviceId,'suspended',$this->clock->now());}
    public function activate(string $deviceId): void{$this->devices->setState($deviceId,'active',$this->clock->now());}
    public function revoke(string $deviceId): void{$this->devices->setState($deviceId,'revoked',$this->clock->now());}
}
