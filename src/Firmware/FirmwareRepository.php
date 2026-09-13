<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Firmware;
interface FirmwareRepository
{
    public function latest(string $hardwareModel,?string $hardwareRevision,string $channel): ?array;
    public function add(array $firmware): void;
}
