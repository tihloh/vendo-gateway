<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Tests;
use PHPUnit\Framework\TestCase;
use Tihloh\VendoGateway\Auth\Signature;
final class SignatureTest extends TestCase
{
    public function testSignatureIsDeterministic(): void
    {
        $a=Signature::sign('secret','POST','/vendo/v1/events',1700000000,'abc','{"x":1}');$b=Signature::sign('secret','POST','/vendo/v1/events',1700000000,'abc','{"x":1}');self::assertSame($a,$b);self::assertSame(64,strlen($a));
    }
    public function testBodyChangesSignature(): void
    {
        self::assertNotSame(Signature::sign('secret','POST','/x',1,'n','a'),Signature::sign('secret','POST','/x',1,'n','b'));
    }
}
