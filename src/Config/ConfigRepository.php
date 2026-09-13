<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Config;
interface ConfigRepository
{
    public function profile(string $profileKey): ?array;
    public function upsertProfile(string $profileKey,string $name,array $config,array $requiredCapabilities,string $firmwareChannel,\DateTimeImmutable $at): int;
    public function device(string $deviceId): ?array;
    public function assignProfile(string $deviceId,?string $profileKey): void;
    public function saveDeviceConfig(string $deviceId,array $config,\DateTimeImmutable $at): int;
}
