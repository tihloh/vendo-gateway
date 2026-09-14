<?php
declare(strict_types=1);

namespace Tihloh\VendoGateway\Support;

final class Random
{
    public static function token(int $bytes=32): string{return bin2hex(random_bytes($bytes));}
    public static function id(string $prefix,int $bytes=10): string{return $prefix.strtoupper(bin2hex(random_bytes($bytes)));}
    public static function setupCode(int $length=8): string
    {
        $alphabet='23456789ABCDEFGHJKMNPQRSTUVWXYZ';$out='';$max=strlen($alphabet)-1;
        for($i=0;$i<$length;$i++)$out.=$alphabet[random_int(0,$max)];
        return $out;
    }
}
