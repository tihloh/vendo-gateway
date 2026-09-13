<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway;
use Tihloh\VendoGateway\Command\CommandService;
use Tihloh\VendoGateway\Config\ConfigService;
use Tihloh\VendoGateway\Device\DeviceService;
use Tihloh\VendoGateway\Event\EventDispatcher;
use Tihloh\VendoGateway\Event\EventService;
use Tihloh\VendoGateway\Firmware\FirmwareService;
use Tihloh\VendoGateway\Heartbeat\HeartbeatService;
use Tihloh\VendoGateway\Maintenance\MaintenanceService;
use Tihloh\VendoGateway\Pairing\PairingService;
use Tihloh\VendoGateway\State\StateService;
final readonly class Gateway
{
    public function __construct(
        public PairingService $pairings,
        public DeviceService $devices,
        public HeartbeatService $heartbeats,
        public ConfigService $configs,
        public CommandService $commands,
        public EventService $events,
        public EventDispatcher $dispatcher,
        public StateService $states,
        public FirmwareService $firmware,
        public MaintenanceService $maintenance
    ) {}
}
