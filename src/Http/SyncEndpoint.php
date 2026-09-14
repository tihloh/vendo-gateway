<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Http;
use Tihloh\VendoGateway\Command\CommandService;
use Tihloh\VendoGateway\Config\ConfigService;
use Tihloh\VendoGateway\Firmware\FirmwareService;
use Tihloh\VendoGateway\State\StateService;
final class SyncEndpoint
{
    public function __construct(private DeviceAuth $auth,private ConfigService $configs,private CommandService $commands,private StateService $states,private FirmwareService $firmware) {}
    public function handle(array $headers,string $rawBody,string $method,string $path): array
    {
        $device=$this->auth->authenticate($headers,$rawBody,$method,$path);$p=json_decode($rawBody,true,flags:JSON_THROW_ON_ERROR);if(isset($p['reported_state'])&&is_array($p['reported_state']))$this->states->report($device->deviceId,$p['reported_state']);$config=$this->configs->pull($device->deviceId,isset($p['config_revision'])?(string)$p['config_revision']:null);return ['config'=>$config,'desired_state'=>$this->states->desired($device->deviceId),'commands'=>$this->commands->poll($device->deviceId,(int)($p['command_limit']??10)),'firmware'=>null,'server_time'=>time()];
    }
}
