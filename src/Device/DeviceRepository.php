<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Device;
interface DeviceRepository
{
    public function find(string $deviceId): ?Device;
    public function findByHardwareUid(string $hardwareUid): ?Device;
    public function secret(string $deviceId): ?string;
    public function create(string $deviceId,?string $hardwareUid,string $deviceSecret,?string $hardwareModel,?string $hardwareRevision,?string $firmwareVersion): Device;
    public function heartbeat(string $deviceId,?string $firmwareVersion,?string $ip,\DateTimeImmutable $at): void;
    public function replaceCapabilities(string $deviceId,array $capabilities,\DateTimeImmutable $reportedAt): void;
    public function capabilities(string $deviceId): array;
    public function setState(string $deviceId,string $state,\DateTimeImmutable $at): void;
}
