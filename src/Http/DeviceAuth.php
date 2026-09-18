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
        $get=fn(string $name)=>$headers[$name]??$headers[strtolower($name)]??'';
        $device=(string)$get('X-Vendo-Device');
        $authorization=trim((string)$get('Authorization'));
        if(!preg_match('/^Bearer\s+(.+)$/i',$authorization,$match))throw new \RuntimeException('Device authentication failed.');
        return$this->auth->authenticateToken($device,trim($match[1]));
    }
}
