<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Pairing;
use PDO;
final class PdoPairingRepository implements PairingRepository
{
    public function __construct(private PDO $pdo) {}
    public function create(array $data): void
    {
        $stmt=$this->pdo->prepare('INSERT INTO vg_pairings (pairing_id,pairing_code,pairing_token_hash,hardware_uid,hardware_model,hardware_revision,firmware_version,capabilities_json,status,claimed_by,claimed_context_json,expires_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,? ,"pending",?,?,?,?,?)');
        $stmt->execute([$data['pairing_id'],$data['pairing_code'],password_hash($data['pairing_token'],PASSWORD_DEFAULT),$data['hardware_uid']??null,$data['hardware_model']??null,$data['hardware_revision']??null,$data['firmware_version']??null,json_encode($data['capabilities']??[],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$data['claimed_by']??null,json_encode($data['context']??[],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$data['expires_at']->format('Y-m-d H:i:s'),$data['created_at']->format('Y-m-d H:i:s'),$data['created_at']->format('Y-m-d H:i:s')]);
    }
    public function findByToken(string $pairingId,string $pairingToken): ?array
    {
        $stmt=$this->pdo->prepare('SELECT * FROM vg_pairings WHERE pairing_id=? LIMIT 1');$stmt->execute([$pairingId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);if(!$row||!password_verify($pairingToken,$row['pairing_token_hash']))return null;return$row;
    }
    public function findByCode(string $setupCode): ?array
    {
        $stmt=$this->pdo->prepare('SELECT * FROM vg_pairings WHERE pairing_code=? AND status NOT IN ("expired","cancelled") AND (expires_at>UTC_TIMESTAMP() OR status IN ("claimed","completed")) LIMIT 1');$stmt->execute([$setupCode]);$row=$stmt->fetch(PDO::FETCH_ASSOC);return$row?:null;
    }
    public function findById(string $pairingId): ?array
    {
        $stmt=$this->pdo->prepare('SELECT * FROM vg_pairings WHERE pairing_id=? LIMIT 1');$stmt->execute([$pairingId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);return$row?:null;
    }
    public function pending(\DateTimeImmutable $now): array
    {
        $stmt=$this->pdo->prepare('SELECT * FROM vg_pairings WHERE status="pending" AND expires_at>? ORDER BY created_at');$stmt->execute([$now->format('Y-m-d H:i:s')]);return$stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function deleteExpired(\DateTimeImmutable $now): int
    {
        $stmt=$this->pdo->prepare('DELETE FROM vg_pairings WHERE status="pending" AND expires_at<=?');$stmt->execute([$now->format('Y-m-d H:i:s')]);return$stmt->rowCount();
    }
    public function delete(string $pairingId): void
    {
        $stmt=$this->pdo->prepare('DELETE FROM vg_pairings WHERE pairing_id=?');$stmt->execute([$pairingId]);
    }
    public function claim(string $pairingId,string $claimedBy,array $context,\DateTimeImmutable $at,array $deviceInfo=[]): void
    {
        $stmt=$this->pdo->prepare('UPDATE vg_pairings SET status="claimed",claimed_by=?,claimed_context_json=?,hardware_uid=?,hardware_model=?,hardware_revision=?,firmware_version=?,capabilities_json=?,claimed_at=?,updated_at=? WHERE pairing_id=? AND status="pending"');$date=$at->format('Y-m-d H:i:s');
        $stmt->execute([$claimedBy,json_encode($context,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$deviceInfo['hardware_uid']??null,$deviceInfo['hardware_model']??null,$deviceInfo['hardware_revision']??null,$deviceInfo['firmware_version']??null,json_encode($deviceInfo['capabilities']??[],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$date,$date,$pairingId]);if($stmt->rowCount()!==1)throw new \RuntimeException('Setup code is no longer claimable.');
    }
    public function complete(string $pairingId,string $deviceId,string $encryptedDeviceSecret,\DateTimeImmutable $at): void
    {
        $stmt=$this->pdo->prepare('UPDATE vg_pairings SET status="completed",device_id=?,issued_device_secret_encrypted=?,completed_at=?,updated_at=? WHERE pairing_id=? AND status="claimed"');$date=$at->format('Y-m-d H:i:s');$stmt->execute([$deviceId,$encryptedDeviceSecret,$date,$date,$pairingId]);if($stmt->rowCount()!==1)throw new \RuntimeException('Device enrollment completion failed.');
    }
    public function markDelivered(string $pairingId,\DateTimeImmutable $at): void
    {
        $stmt=$this->pdo->prepare('UPDATE vg_pairings SET issued_device_secret_encrypted=NULL,updated_at=? WHERE pairing_id=?');$stmt->execute([$at->format('Y-m-d H:i:s'),$pairingId]);
    }
}
