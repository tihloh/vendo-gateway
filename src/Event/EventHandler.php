<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Event;
interface EventHandler
{
    public function handle(DeviceEvent $event): void;
}
