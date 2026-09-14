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
    public function prepare(string $createdBy,array $context=[]): array
    {
        $now=$this->clock->now();
        for($attempt=0;$attempt<5;$attempt++){
            try{
                $id=Random::id('ENR-',8);$code=Random::setupCode(8);$this->pairings->create(['pairing_id'=>$id,'pairing_code'=>$code,'pairing_token'=>Random::token(32),'claimed_by'=>$createdBy,'context'=>$context,'expires_at'=>$now->modify("+{$this->ttlSeconds} seconds"),'created_at'=>$now]);
                return ['status'=>'setup_code_ready','enrollment_id'=>$id,'setup_code'=>$code,'expires_in'=>$this->ttlSeconds];
            }catch(\PDOException $e){if((string)$e->getCode()!=='23000'||$attempt===4)throw $e;}
        }
        throw new \RuntimeException('Unable to allocate setup code.');
    }
    public function enroll(string $setupCode,array $info): array
    {
        $code=$this->normalizeCode($setupCode);if(!preg_match('/^[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{8}$/',$code))throw new \InvalidArgumentException('Invalid setup code.');
        $row=$this->pairings->findByCode($code);if(!$row)throw new \RuntimeException('Invalid or expired setup code.');$uid=trim((string)($info['hardware_uid']??''));if($uid==='')throw new \InvalidArgumentException('hardware_uid is required.');
        if($row['status']==='completed')return $this->completedEnrollment($row,$uid);
        if($row['status']!=='pending')throw new \RuntimeException('Setup code is already being used.');$existing=$this->devices->findByHardwareUid($uid);if($existing&&$existing->state!=='revoked')throw new \RuntimeException('Hardware UID is already registered; administrative recovery is required.');
        $context=json_decode((string)($row['claimed_context_json']??'{}'),true)?:[];$createdBy=(string)($row['claimed_by']??'system');$now=$this->clock->now();$deviceInfo=['hardware_uid'=>$uid,'hardware_model'=>$info['hardware_model']??null,'hardware_revision'=>$info['hardware_revision']??null,'firmware_version'=>$info['firmware_version']??null,'capabilities'=>$info['capabilities']??[]];
        $this->pairings->claim((string)$row['pairing_id'],$createdBy,$context,$now,$deviceInfo);$id=Random::id('DEV-',10);$secret=Random::token(32);$this->devices->create($id,$uid,$secret,$deviceInfo['hardware_model'],$deviceInfo['hardware_revision'],$deviceInfo['firmware_version']);if($deviceInfo['capabilities'])$this->devices->replaceCapabilities($id,$deviceInfo['capabilities'],$now);$this->pairings->complete((string)$row['pairing_id'],$id,$this->protector->encrypt($secret),$now);
        return ['status'=>'registered','enrollment_id'=>$row['pairing_id'],'device_id'=>$id,'device_secret'=>$secret,'context'=>$context,'ack_required'=>true];
    }
    public function acknowledge(string $enrollmentId,string $setupCode): array
    {
        $row=$this->pairings->findByCode($this->normalizeCode($setupCode));if(!$row||(string)$row['pairing_id']!==$enrollmentId)throw new \RuntimeException('Invalid enrollment credentials.');if($row['status']!=='completed')throw new \RuntimeException('Enrollment is not completed.');if(!$row['issued_device_secret_encrypted'])return ['status'=>'registered','device_id'=>$row['device_id'],'credentials_delivered'=>true];$this->pairings->markDelivered($enrollmentId,$this->clock->now());return ['status'=>'registered','device_id'=>$row['device_id'],'credentials_delivered'=>true];
    }
    private function completedEnrollment(array $row,string $uid): array
    {
        if((string)($row['hardware_uid']??'')!==$uid)throw new \RuntimeException('Setup code has already been used by another device.');$context=json_decode((string)($row['claimed_context_json']??'{}'),true)?:[];if(!$row['issued_device_secret_encrypted'])return ['status'=>'registered','device_id'=>$row['device_id'],'credentials_delivered'=>true,'context'=>$context];return ['status'=>'registered','enrollment_id'=>$row['pairing_id'],'device_id'=>$row['device_id'],'device_secret'=>$this->protector->decrypt($row['issued_device_secret_encrypted']),'context'=>$context,'ack_required'=>true];
    }
    private function normalizeCode(string $code): string{return strtoupper(preg_replace('/[^A-Z0-9]/i','',$code)??'');}
}
