<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Firmware;
use Tihloh\VendoGateway\Command\CommandService;
use Tihloh\VendoGateway\Config\ConfigService;
use Tihloh\VendoGateway\Device\DeviceRepository;
use Tihloh\VendoGateway\Support\Clock;
use Tihloh\VendoGateway\Support\Random;
final class FirmwareService
{
    private ReleaseManifestService $releases;
    public function __construct(private FirmwareRepository $firmware,private Clock $clock,?ReleaseManifestService $releases=null,private ?DeviceRepository $devices=null,private ?CommandService $commands=null,private ?ConfigService $configs=null){$this->releases=$releases??new ReleaseManifestService();}
    public function publish(string $model,?string $revision,string $channel,string $version,string $url,string $sha256,?int $sizeBytes=null,bool $mandatory=false,?string $releaseNotes=null): string
    {
        if(!preg_match('/^[a-f0-9]{64}$/i',$sha256))throw new \InvalidArgumentException('Invalid SHA-256.');$id=Random::id('FW-',10);$this->firmware->add(['firmware_id'=>$id,'hardware_model'=>$model,'hardware_revision'=>$revision,'channel'=>$channel,'version'=>$version,'url'=>$url,'sha256'=>strtolower($sha256),'size_bytes'=>$sizeBytes,'mandatory'=>$mandatory,'release_notes'=>$releaseNotes,'created_at'=>$this->clock->now()]);return $id;
    }
    public function check(string $model,?string $revision,string $channel,string $currentVersion): array
    {
        $latest=$this->firmware->latest($model,$revision,$channel);if(!$latest||version_compare($latest['version'],$currentVersion,'<='))return ['update_available'=>false];return ['update_available'=>true,'firmware'=>$latest];
    }
    public function releaseStatus(string $currentVersion,string $target,bool $refresh=false): array{return $this->releases->status($currentVersion,$this->target($target),$refresh);}
    public function latestRelease(?string $target=null,bool $refresh=false): ?array{return $this->releases->latest($target===null?null:$this->target($target),$refresh);}
    /** Read persisted device identity and release metadata; never contacts the ESP. */
    public function deviceStatus(string $deviceId,bool $refresh=false): array
    {
        if(!$this->devices)throw new \RuntimeException('Device repository is not available.');
        $device=$this->devices->find($deviceId)??throw new \InvalidArgumentException('Device not found.');
        $caps=$this->devices->capabilities($deviceId);
        $reported=$this->configs?->reportedState($deviceId)??[];
        $target=$caps['platform']??$reported['hardware']['platform']??$reported['platform']??$this->targetFromHardwareUid($device->hardwareUid);
        $settings=$this->settings($deviceId);
        $base=['device_id'=>$deviceId,'current_version'=>$device->firmwareVersion,'latest_version'=>null,'update_available'=>null,'target'=>null,'settings'=>$settings];
        if(!is_string($target)||!in_array(strtolower($target),['esp32','esp8266'],true))return $base+['error'=>'Device firmware target is unknown.'];
        $status=$this->releaseStatus((string)($device->firmwareVersion??''),strtolower($target),$refresh);
        if(isset($status['hardware_model'])&&($device->hardwareModel!==$status['hardware_model']||($status['hardware_revision']!==''&&$device->hardwareRevision!==$status['hardware_revision']))){
            $status['update_available']=null;$status['error']='Firmware does not match this hardware model or revision.';
        }
        return array_replace($base,$status);
    }

    public function requestCheck(string $deviceId): array{return $this->deviceStatus($deviceId,true);}

    public function requestUpdate(string $deviceId): string
    {
        if(!$this->commands)throw new \RuntimeException('Command service is not available.');
        $device=$this->devices?->find($deviceId);
        if(!$device||$device->state!=='active')throw new \RuntimeException('Device must be active to update.');
        $status=$this->deviceStatus($deviceId,true);
        if(($status['update_available']??null)!==true)throw new \RuntimeException($status['error']??'Device is already up to date.');
        // Pin the complete artifact at the time the operator requests the update.
        return $this->commands->queue($deviceId,'firmware.update',[
            'version'=>$status['latest_version'],'channel'=>$status['channel'],'target'=>$status['target'],
            'hardware_model'=>$status['hardware_model'],'hardware_revision'=>$status['hardware_revision'],
            'url'=>$status['url'],'sha256'=>$status['sha256'],'size'=>$status['size'],'size_bytes'=>$status['size'],
        ],86400);
    }

    public function normalizeSettings(array $input): array
    {
        $hours=filter_var($input['check_interval_hours']??2,FILTER_VALIDATE_INT);
        if($hours===false||$hours<1||$hours>24)throw new \InvalidArgumentException('Check interval must be between 1 and 24 hours.');
        $channel=(string)($input['channel']??'stable');
        if($channel!=='stable')throw new \InvalidArgumentException('Only the stable firmware channel is supported.');
        $check=filter_var($input['auto_check']??true,FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE);
        $update=filter_var($input['auto_update']??false,FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE);
        if($check===null||$update===null)throw new \InvalidArgumentException('Invalid firmware policy switch.');
        if($update&&!$check)throw new \InvalidArgumentException('Enable automatic checks before enabling automatic updates.');
        return ['auto_check'=>$check,'auto_update'=>$update,'check_interval_hours'=>$hours,'channel'=>$channel];
    }

    public function settings(string $deviceId): array
    {
        $resolved=$this->configs?->resolve($deviceId)??[];
        return $this->normalizeSettings($resolved['config']['firmware']??[]);
    }

    public function saveSettings(string $deviceId,array $input): array
    {
        if(!$this->configs||!$this->commands)throw new \RuntimeException('Firmware configuration services are not available.');
        $settings=$this->normalizeSettings($input);
        $this->configs->patchDeviceConfig($deviceId,['firmware'=>$settings]);
        $this->commands->queue($deviceId,'config.refresh',[],86400);
        return $settings;
    }

    private function targetFromHardwareUid(?string $hardwareUid): ?string
    {
        $uid=strtoupper(trim((string)$hardwareUid));
        if(str_starts_with($uid,'ESP32-'))return 'esp32';
        if(str_starts_with($uid,'ESP8266-'))return 'esp8266';
        return null;
    }

    private function target(string $target): string{$target=strtolower(trim($target));if(!in_array($target,['esp8266','esp32'],true))throw new \InvalidArgumentException('Unsupported firmware target.');return $target;}
}
