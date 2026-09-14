<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Firmware;
use Tihloh\VendoGateway\Command\CommandService;
use Tihloh\VendoGateway\Device\DeviceRepository;
use Tihloh\VendoGateway\Support\Clock;
use Tihloh\VendoGateway\Support\Random;
final class FirmwareService
{
    private ReleaseManifestService $releases;
    public function __construct(private FirmwareRepository $firmware,private Clock $clock,?ReleaseManifestService $releases=null,private ?DeviceRepository $devices=null,private ?CommandService $commands=null){$this->releases=$releases??new ReleaseManifestService();}
    public function publish(string $model,?string $revision,string $channel,string $version,string $url,string $sha256,?int $sizeBytes=null,bool $mandatory=false,?string $releaseNotes=null): string
    {
        if(!preg_match('/^[a-f0-9]{64}$/i',$sha256))throw new \InvalidArgumentException('Invalid SHA-256.');$id=Random::id('FW-',10);$this->firmware->add(['firmware_id'=>$id,'hardware_model'=>$model,'hardware_revision'=>$revision,'channel'=>$channel,'version'=>$version,'url'=>$url,'sha256'=>strtolower($sha256),'size_bytes'=>$sizeBytes,'mandatory'=>$mandatory,'release_notes'=>$releaseNotes,'created_at'=>$this->clock->now()]);return $id;
    }
    public function check(string $model,?string $revision,string $channel,string $currentVersion): array
    {
        $latest=$this->firmware->latest($model,$revision,$channel);if(!$latest||version_compare($latest['version'],$currentVersion,'<='))return ['update_available'=>false];return ['update_available'=>true,'firmware'=>$latest];
    }
    public function releaseStatus(string $currentVersion,string $target): array{return $this->releases->status($currentVersion,$this->target($target));}
    public function latestRelease(?string $target=null): ?array{return $this->releases->latest($target===null?null:$this->target($target));}
    public function deviceStatus(string $deviceId): array
    {
        if(!$this->devices)throw new \RuntimeException('Device repository is not available.');$device=$this->devices->find($deviceId);if(!$device)throw new \InvalidArgumentException('Device not found.');$caps=$this->devices->capabilities($deviceId);$target=(string)($caps['platform']??'');if($target==='')return ['device_id'=>$deviceId,'current_version'=>$device->firmwareVersion,'latest_version'=>null,'update_available'=>null,'target'=>null];return ['device_id'=>$deviceId]+$this->releaseStatus((string)($device->firmwareVersion??'0.0.0'),$target);
    }
    public function requestCheck(string $deviceId): string
    {
        if(!$this->commands)throw new \RuntimeException('Command service is not available.');return $this->commands->queue($deviceId,'firmware.check',[],300);
    }
    public function requestUpdate(string $deviceId): string
    {
        if(!$this->commands)throw new \RuntimeException('Command service is not available.');return $this->commands->queue($deviceId,'firmware.update',[],600);
    }
    public function normalizeSettings(array $settings): array
    {
        $hours=(int)($settings['check_interval_hours']??2);if($hours<1||$hours>24)throw new \InvalidArgumentException('Firmware check interval must be between 1 and 24 hours.');$channel=(string)($settings['channel']??'stable');if($channel!=='stable')throw new \InvalidArgumentException('Unsupported firmware channel.');return ['auto_check'=>(bool)($settings['auto_check']??true),'check_interval_hours'=>$hours,'auto_update'=>(bool)($settings['auto_update']??false),'channel'=>$channel];
    }
    private function target(string $target): string{$target=strtolower(trim($target));if(!in_array($target,['esp8266','esp32'],true))throw new \InvalidArgumentException('Unsupported firmware target.');return $target;}
}
