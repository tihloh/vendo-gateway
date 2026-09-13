<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Firmware;
use PDO;
final class PdoFirmwareRepository implements FirmwareRepository
{
    public function __construct(private PDO $pdo) {}
    public function latest(string $hardwareModel,?string $hardwareRevision,string $channel): ?array
    {
        $stmt=$this->pdo->prepare('SELECT firmware_id,hardware_model,hardware_revision,channel,version,url,sha256,size_bytes,mandatory,release_notes FROM vg_firmware WHERE hardware_model=? AND channel=? AND enabled=1 AND (hardware_revision IS NULL OR hardware_revision=?) ORDER BY id DESC');$stmt->execute([$hardwareModel,$channel,$hardwareRevision]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);if(!$rows)return null;usort($rows,fn($a,$b)=>version_compare($b['version'],$a['version']));return $rows[0];
    }
    public function add(array $f): void
    {
        $stmt=$this->pdo->prepare('INSERT INTO vg_firmware (firmware_id,hardware_model,hardware_revision,channel,version,url,sha256,size_bytes,mandatory,release_notes,enabled,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,1,?)');$stmt->execute([$f['firmware_id'],$f['hardware_model'],$f['hardware_revision'],$f['channel'],$f['version'],$f['url'],$f['sha256'],$f['size_bytes'],$f['mandatory']?1:0,$f['release_notes'],$f['created_at']->format('Y-m-d H:i:s')]);
    }
}
