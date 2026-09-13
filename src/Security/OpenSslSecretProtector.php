<?php
declare(strict_types=1);

namespace Tihloh\VendoGateway\Security;

final class OpenSslSecretProtector implements SecretProtector
{
    private string $key;

    public function __construct(string $masterKey)
    {
        if (strlen($masterKey) < 32) {
            throw new \InvalidArgumentException('Master key must be at least 32 characters.');
        }
        $this->key = hash('sha256', $masterKey, true);
    }

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false) {
            throw new \RuntimeException('Unable to encrypt secret.');
        }
        return base64_encode($iv . $tag . $ciphertext);
    }

    public function decrypt(string $ciphertext): string
    {
        $raw = base64_decode($ciphertext, true);
        if ($raw === false || strlen($raw) < 29) {
            throw new \RuntimeException('Invalid encrypted secret.');
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $data = substr($raw, 28);
        $plaintext = openssl_decrypt($data, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plaintext === false) {
            throw new \RuntimeException('Unable to decrypt secret.');
        }
        return $plaintext;
    }
}
