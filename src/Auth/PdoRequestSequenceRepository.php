<?php
declare(strict_types=1);

namespace Tihloh\VendoGateway\Auth;

use PDO;

final class PdoRequestSequenceRepository implements RequestSequenceRepository
{
    public function __construct(private PDO $pdo) {}

    public function mode(string $deviceId): string
    {
        $stmt=$this->pdo->prepare('SELECT auth_mode FROM vg_devices WHERE device_id=? LIMIT 1');
        $stmt->execute([$deviceId]);
        return(string)($stmt->fetchColumn()?:'nonce');
    }

    public function accept(string $deviceId,string $sequence,bool $allowPromote): bool
    {
        if(!$this->valid($sequence))return false;
        $stmt=$this->pdo->prepare(
            "UPDATE vg_devices
             SET auth_mode='sequence',last_request_sequence=CAST(? AS DECIMAL(20,0)),updated_at=UTC_TIMESTAMP()
             WHERE device_id=?
               AND (auth_mode='sequence' OR (auth_mode='nonce' AND ?=1))
               AND last_request_sequence<CAST(? AS DECIMAL(20,0))"
        );
        $stmt->execute([$sequence,$deviceId,$allowPromote?1:0,$sequence]);
        return$stmt->rowCount()===1;
    }

    private function valid(string $sequence): bool
    {
        if(!preg_match('/^[1-9][0-9]{0,19}$/D',$sequence))return false;
        $max='18446744073709551615';
        return strlen($sequence)<20||(strlen($sequence)===20&&strcmp($sequence,$max)<=0);
    }
}
