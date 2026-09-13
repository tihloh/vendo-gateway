<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Tests;
use PHPUnit\Framework\TestCase;
use Tihloh\VendoGateway\Security\OpenSslSecretProtector;
final class SecretProtectorTest extends TestCase
{
    public function testRoundTrip(): void
    {
        $p=new OpenSslSecretProtector(str_repeat('k',32));$cipher=$p->encrypt('device-secret');self::assertNotSame('device-secret',$cipher);self::assertSame('device-secret',$p->decrypt($cipher));
    }
}
