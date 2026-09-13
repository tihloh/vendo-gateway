<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Maintenance;
use PDO;
use Tihloh\VendoGateway\Support\Clock;
final class MaintenanceService
{
    public function __construct(private PDO $pdo,private Clock $clock) {}
    public function cleanup(int $eventRetentionDays=90,int $completedCommandRetentionDays=30): array
    {
        $now=$this->clock->now();$nonce=$this->pdo->prepare('DELETE FROM vg_device_nonces WHERE expires_at<?');$nonce->execute([$now->format('Y-m-d H:i:s')]);$events=$this->pdo->prepare('DELETE FROM vg_device_events WHERE received_at<?');$events->execute([$now->modify("-{$eventRetentionDays} days")->format('Y-m-d H:i:s')]);$commands=$this->pdo->prepare('DELETE FROM vg_device_commands WHERE status IN ("acked","failed","expired","cancelled") AND updated_at<?');$commands->execute([$now->modify("-{$completedCommandRetentionDays} days")->format('Y-m-d H:i:s')]);return ['nonces'=>$nonce->rowCount(),'events'=>$events->rowCount(),'commands'=>$commands->rowCount()];
    }
}
