<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Firmware;
use Tihloh\VendoGateway\Support\Clock;
use Tihloh\VendoGateway\Support\Random;
final class FirmwareService
{
    public function __construct(private FirmwareRepository $firmware,private Clock $clock) {}
    public function publish(string $model,?string $revision,string $channel,string $version,string $url,string $sha256,?int $sizeBytes=null,bool $mandatory=false,?string $releaseNotes=null): string
    {
        if(!preg_match('/^[a-f0-9]{64}$/i',$sha256)) throw new \InvalidArgumentException('Invalid SHA-256.');$id=Random::id('FW-',10);$this->firmware->add(['firmware_id'=>$id,'hardware_model'=>$model,'hardware_revision'=>$revision,'channel'=>$channel,'version'=>$version,'url'=>$url,'sha256'=>strtolower($sha256),'size_bytes'=>$sizeBytes,'mandatory'=>$mandatory,'release_notes'=>$releaseNotes,'created_at'=>$this->clock->now()]);return $id;
    }
    public function check(string $model,?string $revision,string $channel,string $currentVersion): array
    {
        $latest=$this->firmware->latest($model,$revision,$channel);if(!$latest||version_compare($latest['version'],$currentVersion,'<='))return ['update_available'=>false];return ['update_available'=>true,'firmware'=>$latest];
    }
}
