<?php
declare(strict_types=1);

namespace Tihloh\VendoGateway\Auth;

final class Signature
{
    public static function canonical(
        string $method,
        string $path,
        int $timestamp,
        string $nonce,
        string $body
    ): string {
        return implode("\n", [
            strtoupper($method),
            $path,
            (string) $timestamp,
            $nonce,
            hash('sha256', $body)
        ]);
    }

    public static function sign(
        string $secret,
        string $method,
        string $path,
        int $timestamp,
        string $nonce,
        string $body
    ): string {
        return hash_hmac(
            'sha256',
            self::canonical($method, $path, $timestamp, $nonce, $body),
            $secret
        );
    }
}
