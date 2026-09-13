<?php
declare(strict_types=1);

namespace Tihloh\VendoGateway\Security;

interface SecretProtector
{
    public function encrypt(string $plaintext): string;
    public function decrypt(string $ciphertext): string;
}
