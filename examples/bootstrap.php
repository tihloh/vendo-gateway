<?php
declare(strict_types=1);

use Tihloh\VendoGateway\Database\Migrator;
use Tihloh\VendoGateway\GatewayFactory;
use Tihloh\VendoGateway\Http\CommandEndpoint;
use Tihloh\VendoGateway\Http\ConfigEndpoint;
use Tihloh\VendoGateway\Http\DeviceAuth;
use Tihloh\VendoGateway\Http\DiscoveryEndpoint;
use Tihloh\VendoGateway\Http\EventEndpoint;
use Tihloh\VendoGateway\Http\FirmwareEndpoint;
use Tihloh\VendoGateway\Http\PairingEndpoint;
use Tihloh\VendoGateway\Http\StateEndpoint;
use Tihloh\VendoGateway\Http\SyncEndpoint;

require __DIR__.'/../vendor/autoload.php';

$pdo=new PDO(getenv('DB_DSN'),getenv('DB_USER'),getenv('DB_PASS'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION
]);
$masterKey=(string)getenv('VENDO_GATEWAY_MASTER_KEY');
(new Migrator($pdo))->migrate();
$gateway=GatewayFactory::pdo($pdo,$masterKey);
$deviceAuth=new DeviceAuth(GatewayFactory::authenticator($pdo,$masterKey));

$discoveryEndpoint=new DiscoveryEndpoint();
$pairingEndpoint=new PairingEndpoint($gateway->pairings);
$syncEndpoint=new SyncEndpoint($deviceAuth,$gateway->configs,$gateway->commands,$gateway->states,$gateway->firmware);
$eventEndpoint=new EventEndpoint($deviceAuth,$gateway->events);
$commandEndpoint=new CommandEndpoint($deviceAuth,$gateway->commands);
$configEndpoint=new ConfigEndpoint($deviceAuth,$gateway->configs);
$stateEndpoint=new StateEndpoint($deviceAuth,$gateway->states);
$firmwareEndpoint=new FirmwareEndpoint($deviceAuth,$gateway->firmware);
