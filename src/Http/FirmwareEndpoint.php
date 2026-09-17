<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Http;
use Tihloh\VendoGateway\Firmware\FirmwareService;
final class FirmwareEndpoint
{
    public function __construct(private DeviceAuth $auth,private FirmwareService $firmware) {}
    public function check(array $headers,string $rawBody,string $method,string $path): array
    {
        $device=$this->auth->authenticate($headers,$rawBody,$method,$path);$status=$this->firmware->deviceStatus($device->deviceId);
        $result=['update_available'=>$status['update_available'],'error'=>$status['error']??null];
        if($status['update_available']===true)$result['firmware']=[
            'version'=>$status['latest_version'],'url'=>$status['url'],'sha256'=>$status['sha256'],
            'size_bytes'=>$status['size'],'target'=>$status['target'],'channel'=>$status['channel'],
            'hardware_model'=>$status['hardware_model'],'hardware_revision'=>$status['hardware_revision'],
        ];
        return $result;
    }
}
