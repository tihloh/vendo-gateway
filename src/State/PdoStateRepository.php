<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\State;
use PDO;
final class PdoStateRepository implements StateRepository
{
    public function __construct(private PDO $pdo) {}
    public function desired(string $deviceId): array
    {
        $stmt=$this->pdo->prepare('SELECT desired_state_json FROM vg_devices WHERE device_id=? LIMIT 1');$stmt->execute([$deviceId]);$json=$stmt->fetchColumn();return json_decode(is_string($json)?$json:'{}',true)?:[];
    }
    public function setDesired(string $deviceId,array $state,\DateTimeImmutable $at): void
    {
        $stmt=$this->pdo->prepare('UPDATE vg_devices SET desired_state_json=?,updated_at=? WHERE device_id=?');$stmt->execute([json_encode($state,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$at->format('Y-m-d H:i:s'),$deviceId]);
    }
    public function report(string $deviceId,array $state,\DateTimeImmutable $at): void
    {
        $stmt=$this->pdo->prepare('UPDATE vg_devices SET reported_state_json=?,updated_at=? WHERE device_id=?');$stmt->execute([json_encode($state,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$at->format('Y-m-d H:i:s'),$deviceId]);
    }
}
