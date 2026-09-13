<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Http;
use Tihloh\VendoGateway\State\StateService;
final class StateEndpoint
{
    public function __construct(private DeviceAuth $auth,private StateService $states) {}
    public function report(array $headers,string $rawBody,string $method,string $path): array
    {
        $device=$this->auth->authenticate($headers,$rawBody,$method,$path);$payload=json_decode($rawBody,true,flags:JSON_THROW_ON_ERROR);return $this->states->report($device->deviceId,is_array($payload['state']??null)?$payload['state']:$payload);
    }
}
