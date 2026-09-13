<?php
declare(strict_types=1);

namespace Tihloh\VendoGateway\Auth;

use PDO;
use PDOException;

final class PdoNonceRepository implements NonceRepository
{
    public function __construct(private PDO $pdo) {}

    public function consume(
        string $deviceId,
        string $nonce,
        \DateTimeImmutable $usedAt,
        \DateTimeImmutable $expiresAt
    ): bool {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO vg_device_nonces (device_id, nonce, used_at, expires_at)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([
                $deviceId,
                $nonce,
                $usedAt->format('Y-m-d H:i:s'),
                $expiresAt->format('Y-m-d H:i:s')
            ]);
            return true;
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }
    }
}
