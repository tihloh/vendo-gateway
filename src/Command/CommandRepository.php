<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Command;
interface CommandRepository
{
    public function enqueue(array $command): void;
    public function pending(string $deviceId,int $limit,\DateTimeImmutable $now): array;
    public function acknowledge(string $deviceId,string $commandId,string $status,array $result,\DateTimeImmutable $at): bool;
    public function expire(\DateTimeImmutable $now): int;
}
