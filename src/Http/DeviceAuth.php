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
        $get=fn(string $name)=>$headers[$name]??$headers[strtolower($name)]??'';return $this->auth->authenticate((string)$get('X-Vendo-Device'),(int)$get('X-Vendo-Timestamp'),(string)$get('X-Vendo-Nonce'),(string)$get('X-Vendo-Signature'),$method,$path,$body);
    }
}
