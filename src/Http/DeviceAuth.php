<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Http;
use Tihloh\VendoGateway\Auth\DeviceAuthenticator;
use Tihloh\VendoGateway\Device\Device;
final readonly class DeviceAuth
{
    public function __construct(private DeviceAuthenticator $auth) {}
    public function authenticate(array $headers,string $body,string $method,string $path): Device
    {
        $normalized=[];
        foreach($headers as $name=>$value)$normalized[strtolower((string)$name)]=(string)$value;
        $device=trim($normalized['x-vendo-device']??'');
        $authorization=trim($normalized['authorization']??'');
        if(!preg_match('/^Bearer\s+(.+)$/i',$authorization,$match))throw new \RuntimeException('Device authentication failed.');
        return$this->auth->authenticate($device,trim($match[1]));
    }
}
