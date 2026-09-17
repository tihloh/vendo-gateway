<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Config;
use Tihloh\VendoGateway\Device\DeviceRepository;
use Tihloh\VendoGateway\Support\Clock;
final class ConfigService
{
    public function __construct(private ConfigRepository $configs,private Clock $clock,private ?DeviceRepository $devices=null) {}
    public function resolve(string $deviceId): array{$device=$this->configs->device($deviceId)??throw new \RuntimeException('Device not found.');$profile=$device['profile_key']?$this->configs->profile($device['profile_key']):null;$merged=array_replace_recursive($profile['config']??[],$device['config']??[]);$revision=hash('sha256',json_encode($merged,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));return ['version'=>(int)$device['config_version'],'profile'=>$device['profile_key'],'profile_version'=>(int)($profile['config_version']??0),'firmware_channel'=>$profile['firmware_channel']??'stable','required_capabilities'=>$profile['required_capabilities']??[],'revision'=>$revision,'config'=>$merged];}
    public function pull(string $deviceId,?string $currentRevision=null): array{$r=$this->resolve($deviceId);if($currentRevision!==null&&hash_equals($r['revision'],$currentRevision))return ['changed'=>false,'revision'=>$r['revision'],'version'=>$r['version'],'profile_version'=>$r['profile_version']];return ['changed'=>true,...$r]+$r['config'];}
    public function setDeviceConfig(string $deviceId,array $config): int{return $this->configs->saveDeviceConfig($deviceId,$config,$this->clock->now());}
    public function reportedState(string $deviceId): array{return $this->configs->device($deviceId)['reported_state']??[];}
    public function patchDeviceConfig(string $deviceId,array $patch): int
    {
        $device=$this->configs->device($deviceId)??throw new \RuntimeException('Device not found.');
        return $this->setDeviceConfig($deviceId,array_replace_recursive($device['config']??[],$patch));
    }
    public function assignProfile(string $deviceId,?string $profileKey): void{if($profileKey!==null){$profile=$this->configs->profile($profileKey)??throw new \RuntimeException('Profile not found.');if($this->devices){$caps=$this->devices->capabilities($deviceId);foreach(array_keys($profile['required_capabilities']??[]) as $required)if(!array_key_exists($required,$caps))throw new \RuntimeException("Device lacks required capability: {$required}");}}$this->configs->assignProfile($deviceId,$profileKey);}
    public function saveProfile(string $profileKey,string $name,array $config,array $requiredCapabilities=[],string $firmwareChannel='stable'): int{return $this->configs->upsertProfile($profileKey,$name,$config,$requiredCapabilities,$firmwareChannel,$this->clock->now());}
}
