<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\State;
interface StateRepository
{
    public function desired(string $deviceId): array;
    public function setDesired(string $deviceId,array $state,\DateTimeImmutable $at): void;
    public function report(string $deviceId,array $state,\DateTimeImmutable $at): void;
}
