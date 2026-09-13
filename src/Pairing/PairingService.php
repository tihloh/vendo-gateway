<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Pairing;
use Tihloh\VendoGateway\Device\DeviceRepository;
use Tihloh\VendoGateway\Security\SecretProtector;
use Tihloh\VendoGateway\Support\Clock;
use Tihloh\VendoGateway\Support\Random;
final class PairingService
{
    public function __construct(private PairingRepository $pairings,private DeviceRepository $devices,private SecretProtector $protector,private Clock $clock,private int $ttlSeconds=600) {}
    public function begin(array $info): array
    {
        $uid=isset($info['hardware_uid'])?(string)$info['hardware_uid']:null;if($uid){$existing=$this->devices->findByHardwareUid($uid);if($existing&&$existing->state!=='revoked')return ['status'=>'already_registered','device_id'=>$existing->deviceId,'recovery_required'=>true];}
        $now=$this->clock->now();for($attempt=0;$attempt<5;$attempt++){try{$id=Random::id('PAIR-',8);$token=Random::token(32);$code=Random::numericCode(6);$this->pairings->create(['pairing_id'=>$id,'pairing_code'=>$code,'pairing_token'=>$token,'hardware_uid'=>$uid,'hardware_model'=>$info['hardware_model']??null,'hardware_revision'=>$info['hardware_revision']??null,'firmware_version'=>$info['firmware_version']??null,'capabilities'=>$info['capabilities']??[],'expires_at'=>$now->modify("+{$this->ttlSeconds} seconds"),'created_at'=>$now]);return ['status'=>'pairing_required','pairing_id'=>$id,'pairing_token'=>$token,'pairing_code'=>$code,'expires_in'=>$this->ttlSeconds];}catch(\PDOException $e){if((string)$e->getCode()!=='23000'||$attempt===4)throw $e;}}
        throw new \RuntimeException('Unable to allocate pairing code.');
    }
    public function claim(string $pairingCode,string $claimedBy,array $context=[]): array
    {
        $row=$this->pairings->findPendingByCode($pairingCode);if(!$row)throw new \RuntimeException('Invalid or expired pairing code.');$now=$this->clock->now();$this->pairings->claim($row['pairing_id'],$claimedBy,$context,$now);$id=Random::id('DEV-',10);$secret=Random::token(32);$this->devices->create($id,$row['hardware_uid']?:null,$secret,$row['hardware_model']?:null,$row['hardware_revision']?:null,$row['firmware_version']?:null);$caps=json_decode($row['capabilities_json']?:'{}',true)?:[];if($caps)$this->devices->replaceCapabilities($id,$caps,$now);$this->pairings->complete($row['pairing_id'],$id,$this->protector->encrypt($secret),$now);return ['status'=>'claimed','pairing_id'=>$row['pairing_id'],'device_id'=>$id];
    }
    public function status(string $pairingId,string $pairingToken): array
    {
        $row=$this->pairings->findByToken($pairingId,$pairingToken);if(!$row)throw new \RuntimeException('Invalid pairing credentials.');$now=$this->clock->now();$expires=new \DateTimeImmutable($row['expires_at'],new \DateTimeZone('UTC'));if($expires<=$now&&$row['status']==='pending')return ['status'=>'expired'];if($row['status']!=='completed')return ['status'=>$row['status']];if(!$row['issued_device_secret_encrypted'])return ['status'=>'registered','device_id'=>$row['device_id'],'credentials_delivered'=>true];$secret=$this->protector->decrypt($row['issued_device_secret_encrypted']);return ['status'=>'registered','device_id'=>$row['device_id'],'device_secret'=>$secret,'credentials_delivered'=>false,'ack_required'=>true];
    }
    public function acknowledge(string $pairingId,string $pairingToken): array
    {
        $row=$this->pairings->findByToken($pairingId,$pairingToken);if(!$row)throw new \RuntimeException('Invalid pairing credentials.');if($row['status']!=='completed')throw new \RuntimeException('Pairing is not completed.');if(!$row['issued_device_secret_encrypted'])return ['status'=>'registered','device_id'=>$row['device_id'],'credentials_delivered'=>true];$this->pairings->markDelivered($pairingId,$this->clock->now());return ['status'=>'registered','device_id'=>$row['device_id'],'credentials_delivered'=>true];
    }
}
