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
        $timestamp=(int)$get('X-Vendo-Timestamp');
        $signature=(string)$get('X-Vendo-Signature');
        $sequence=(string)$get('X-Vendo-Sequence');
        if($sequence!==''){
            $payload=json_decode($body,true);
            $promote=is_array($payload)&&(int)($payload['protocol']??0)>=2;
            return$this->auth->authenticateSequence($device,$timestamp,$sequence,$signature,$method,$path,$body,$promote);
        }
        return$this->auth->authenticate($device,$timestamp,(string)$get('X-Vendo-Nonce'),$signature,$method,$path,$body);
    }
}
