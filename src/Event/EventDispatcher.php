<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Event;
final class EventDispatcher
{
    /** @var array<string,list<EventHandler>> */
    private array $handlers = [];
    public function listen(string $type, EventHandler $handler): void
    {
        $this->handlers[$type][] = $handler;
    }
    public function dispatch(DeviceEvent $event): void
    {
        foreach ([...($this->handlers[$event->type] ?? []), ...($this->handlers['*'] ?? [])] as $handler) {
            $handler->handle($event);
        }
    }
}
