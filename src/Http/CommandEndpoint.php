<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Http;
use Tihloh\VendoGateway\Command\CommandService;
final class CommandEndpoint
{
    public function __construct(private DeviceAuth $auth,private CommandService $commands) {}
    public function poll(array $headers,string $rawBody,string $method,string $path,int $limit=10): array
    {
        $device=$this->auth->authenticate($headers,$rawBody,$method,$path);return ['commands'=>$this->commands->poll($device->deviceId,$limit)];
    }
    public function acknowledge(array $headers,string $rawBody,string $method,string $path,string $commandId): array
    {
        $device=$this->auth->authenticate($headers,$rawBody,$method,$path);$payload=json_decode($rawBody,true,flags:JSON_THROW_ON_ERROR);$ok=$this->commands->acknowledge($device->deviceId,$commandId,(string)($payload['status']??(($payload['ok']??true)?'acked':'failed')),is_array($payload['result']??null)?$payload['result']:[]);return ['ok'=>$ok];
    }
}
