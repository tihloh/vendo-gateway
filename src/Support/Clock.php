<?php
declare(strict_types=1);

namespace Tihloh\VendoGateway\Support;

interface Clock
{
    public function now(): \DateTimeImmutable;
}
