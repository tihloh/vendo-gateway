<?php
declare(strict_types=1);

use Tihloh\VendoGateway\Auth\DeviceAuthenticator;
use Tihloh\VendoGateway\Auth\PdoNonceRepository;
use Tihloh\VendoGateway\Device\PdoDeviceRepository;
use Tihloh\VendoGateway\Heartbeat\HeartbeatService;
use Tihloh\VendoGateway\Http\HeartbeatEndpoint;
use Tihloh\VendoGateway\Http\PairingEndpoint;
use Tihloh\VendoGateway\Pairing\PairingService;
use Tihloh\VendoGateway\Pairing\PdoPairingRepository;
use Tihloh\VendoGateway\Security\OpenSslSecretProtector;
use Tihloh\VendoGateway\Support\SystemClock;

require __DIR__ . '/../vendor/autoload.php';

$pdo = new PDO(
    getenv('DB_DSN'),
    getenv('DB_USER'),
    getenv('DB_PASS'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$clock = new SystemClock();
$protector = new OpenSslSecretProtector(getenv('VENDO_GATEWAY_MASTER_KEY'));
$devices = new PdoDeviceRepository($pdo, $protector);
$pairings = new PdoPairingRepository($pdo);
$nonces = new PdoNonceRepository($pdo);

$pairingService = new PairingService($pairings, $devices, $protector, $clock);
$auth = new DeviceAuthenticator($devices, $nonces, $clock);
$heartbeatService = new HeartbeatService($devices, $clock);

$pairingEndpoint = new PairingEndpoint($pairingService);
$heartbeatEndpoint = new HeartbeatEndpoint($auth, $heartbeatService);
