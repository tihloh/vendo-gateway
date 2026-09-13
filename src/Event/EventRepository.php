<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Event;
interface EventRepository
{
    public function insert(DeviceEvent $event): bool;
    /** @return list<DeviceEvent> */
    public function pending(int $limit=100): array;
    public function markProcessed(string $eventId,\DateTimeImmutable $at): void;
}
