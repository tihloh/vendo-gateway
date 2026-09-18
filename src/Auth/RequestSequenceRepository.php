<?php
declare(strict_types=1);

namespace Tihloh\VendoGateway\Auth;

interface RequestSequenceRepository
{
    public function mode(string $deviceId): string;
    public function accept(string $deviceId,string $sequence,bool $allowPromote): bool;
}
