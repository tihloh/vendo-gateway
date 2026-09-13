<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Event;
final readonly class DeviceEvent
{
    public function __construct(
        public string $eventId,
        public string $deviceId,
        public int $sequence,
        public string $type,
        public array $payload,
        public ?\DateTimeImmutable $occurredAt,
        public \DateTimeImmutable $receivedAt
    ) {}
}
