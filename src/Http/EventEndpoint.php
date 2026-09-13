<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Http;
use Tihloh\VendoGateway\Event\EventService;
final class EventEndpoint
{
    public function __construct(private DeviceAuth $auth,private EventService $events) {}
    public function handle(array $headers,string $rawBody,string $method,string $path): array
    {
        $device=$this->auth->authenticate($headers,$rawBody,$method,$path);$payload=json_decode($rawBody,true,flags:JSON_THROW_ON_ERROR);return $this->events->ingest($device->deviceId,$payload);
    }
}
