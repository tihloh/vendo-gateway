<?php
declare(strict_types=1);

namespace Tihloh\VendoGateway\Auth;

interface NonceRepository
{
    public function consume(
        string $deviceId,
        string $nonce,
        \DateTimeImmutable $usedAt,
        \DateTimeImmutable $expiresAt
    ): bool;
}
