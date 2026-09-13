<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Command;
use PDO;
final class PdoCommandRepository implements CommandRepository
{
    public function __construct(private PDO $pdo) {}
    public function enqueue(array $c): void
    {
        $stmt=$this->pdo->prepare('INSERT INTO vg_device_commands (command_id,device_id,type,payload_json,status,priority,attempts,available_at,expires_at,created_at,updated_at) VALUES (?,?,?,? ,"pending",0,0,?,?,?,?)');
        $stmt->execute([$c['command_id'],$c['device_id'],$c['type'],json_encode($c['payload'],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$c['available_at']->format('Y-m-d H:i:s'),$c['expires_at']?->format('Y-m-d H:i:s'),$c['created_at']->format('Y-m-d H:i:s'),$c['created_at']->format('Y-m-d H:i:s')]);
    }
    public function pending(string $deviceId,int $limit,\DateTimeImmutable $now): array
    {
        $this->expire($now);
        $stmt=$this->pdo->prepare('SELECT command_id,type,payload_json,expires_at FROM vg_device_commands WHERE device_id=? AND status IN ("pending","delivered") AND available_at<=? AND (expires_at IS NULL OR expires_at>?) ORDER BY priority DESC,id ASC LIMIT '.max(1,min($limit,50)));
        $date=$now->format('Y-m-d H:i:s');$stmt->execute([$deviceId,$date,$date]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as &$row){$this->pdo->prepare('UPDATE vg_device_commands SET status="delivered",attempts=attempts+1,delivered_at=COALESCE(delivered_at,?),updated_at=? WHERE command_id=?')->execute([$date,$date,$row['command_id']]);$row['payload']=json_decode($row['payload_json']?:'{}',true)?:[];unset($row['payload_json']);}
        return $rows;
    }
    public function acknowledge(string $deviceId,string $commandId,string $status,array $result,\DateTimeImmutable $at): bool
    {
        if(!in_array($status,['acked','failed'],true)) throw new \InvalidArgumentException('Invalid command acknowledgement status.');
        $date=$at->format('Y-m-d H:i:s');$stmt=$this->pdo->prepare('UPDATE vg_device_commands SET status=?,result_json=?,acknowledged_at=?,updated_at=? WHERE device_id=? AND command_id=? AND status IN ("pending","delivered")');
        $stmt->execute([$status,json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$date,$date,$deviceId,$commandId]);return $stmt->rowCount()===1;
    }
    public function expire(\DateTimeImmutable $now): int
    {
        $date=$now->format('Y-m-d H:i:s');$stmt=$this->pdo->prepare('UPDATE vg_device_commands SET status="expired",updated_at=? WHERE status IN ("pending","delivered") AND expires_at IS NOT NULL AND expires_at<=?');$stmt->execute([$date,$date]);return $stmt->rowCount();
    }
}
