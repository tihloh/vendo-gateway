<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway;
use PDO;
use Tihloh\VendoGateway\Auth\DeviceAuthenticator;
use Tihloh\VendoGateway\Auth\PdoNonceRepository;
use Tihloh\VendoGateway\Auth\PdoRequestSequenceRepository;
use Tihloh\VendoGateway\Command\CommandService;
use Tihloh\VendoGateway\Command\PdoCommandRepository;
use Tihloh\VendoGateway\Config\ConfigService;
use Tihloh\VendoGateway\Config\PdoConfigRepository;
use Tihloh\VendoGateway\Device\DeviceService;
use Tihloh\VendoGateway\Device\PdoDeviceRepository;
use Tihloh\VendoGateway\Event\EventDispatcher;
use Tihloh\VendoGateway\Event\EventService;
use Tihloh\VendoGateway\Event\PdoEventRepository;
use Tihloh\VendoGateway\Firmware\FirmwareService;
use Tihloh\VendoGateway\Firmware\PdoFirmwareRepository;
use Tihloh\VendoGateway\Firmware\ReleaseManifestService;
use Tihloh\VendoGateway\Heartbeat\HeartbeatService;
use Tihloh\VendoGateway\Maintenance\MaintenanceService;
use Tihloh\VendoGateway\Pairing\PairingService;
use Tihloh\VendoGateway\Pairing\PdoPairingRepository;
use Tihloh\VendoGateway\Security\OpenSslSecretProtector;
use Tihloh\VendoGateway\State\PdoStateRepository;
use Tihloh\VendoGateway\State\StateService;
use Tihloh\VendoGateway\Support\Clock;
use Tihloh\VendoGateway\Support\SystemClock;
final class GatewayFactory
{
    public static function pdo(PDO $pdo,string $masterKey,?Clock $clock=null): Gateway
    {
        $clock??=new SystemClock();
        $protector=new OpenSslSecretProtector($masterKey);
        $devicesRepo=new PdoDeviceRepository($pdo,$protector);
        $commands=new CommandService(new PdoCommandRepository($pdo),$clock);
        $dispatcher=new EventDispatcher();
        $configs=new ConfigService(new PdoConfigRepository($pdo),$clock,$devicesRepo);
        $firmware=new FirmwareService(new PdoFirmwareRepository($pdo),$clock,new ReleaseManifestService(),$devicesRepo,$commands,$configs);
        return new Gateway(
            new PairingService(new PdoPairingRepository($pdo),$devicesRepo,$protector,$clock),
            new DeviceService($devicesRepo,$clock),
            new HeartbeatService($devicesRepo,$clock),
            $configs,
            $commands,
            new EventService(new PdoEventRepository($pdo),$dispatcher,$clock),
            $dispatcher,
            new StateService(new PdoStateRepository($pdo),$clock),
            $firmware,
            new MaintenanceService($pdo,$clock)
        );
    }
    public static function authenticator(PDO $pdo,string $masterKey,?Clock $clock=null): DeviceAuthenticator
    {
        $clock??=new SystemClock();
        return new DeviceAuthenticator(new PdoDeviceRepository($pdo,new OpenSslSecretProtector($masterKey)),new PdoNonceRepository($pdo),new PdoRequestSequenceRepository($pdo),$clock);
    }
}
