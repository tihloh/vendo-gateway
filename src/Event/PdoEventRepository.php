<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Event;
use PDO;
use PDOException;
final class PdoEventRepository implements EventRepository
{
    public function __construct(private PDO $pdo) {}
    public function insert(DeviceEvent $event): bool
    {
        try{$stmt=$this->pdo->prepare('INSERT INTO vg_device_events (event_id,device_id,sequence_no,type,payload_json,occurred_at,received_at) VALUES (?,?,?,?,?,?,?)');$stmt->execute([$event->eventId,$event->deviceId,$event->sequence,$event->type,json_encode($event->payload,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$event->occurredAt?->format('Y-m-d H:i:s'),$event->receivedAt->format('Y-m-d H:i:s')]);return true;}catch(PDOException $e){if((string)$e->getCode()==='23000')return false;throw $e;}
    }
    public function pending(int $limit=100): array
    {
        $stmt=$this->pdo->query('SELECT * FROM vg_device_events WHERE processed_at IS NULL ORDER BY id ASC LIMIT '.max(1,min($limit,1000)));$out=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r)$out[]=$this->map($r);return $out;
    }
    public function markProcessed(string $eventId,\DateTimeImmutable $at): void
    {
        $stmt=$this->pdo->prepare('UPDATE vg_device_events SET processed_at=? WHERE event_id=? AND processed_at IS NULL');$stmt->execute([$at->format('Y-m-d H:i:s'),$eventId]);
    }
    private function map(array $r): DeviceEvent
    {
        return new DeviceEvent($r['event_id'],$r['device_id'],(string)$r['sequence_no'],$r['type'],json_decode($r['payload_json']?:'{}',true)?:[],$r['occurred_at']?new \DateTimeImmutable($r['occurred_at'],new \DateTimeZone('UTC')):null,new \DateTimeImmutable($r['received_at'],new \DateTimeZone('UTC')));
    }
}
