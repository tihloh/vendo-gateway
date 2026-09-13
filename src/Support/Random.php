<?php
declare(strict_types=1);

namespace Tihloh\VendoGateway\Support;

final class Random
{
    public static function token(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function id(string $prefix, int $bytes = 10): string
    {
        return $prefix . strtoupper(bin2hex(random_bytes($bytes)));
    }

    public static function numericCode(int $digits = 6): string
    {
        $min = 10 ** ($digits - 1);
        $max = (10 ** $digits) - 1;
        return (string) random_int($min, $max);
    }
}
