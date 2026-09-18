<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Tests;

use PHPUnit\Framework\TestCase;
use Tihloh\VendoGateway\Auth\DeviceAuthenticator;
use Tihloh\VendoGateway\Device\{Device,DeviceRepository};
use Tihloh\VendoGateway\Http\DeviceAuth;

final class DeviceAuthTest extends TestCase
{
    private function auth(): DeviceAuth
    {
        $devices=$this->createStub(DeviceRepository::class);
        $devices->method('find')->willReturn(new Device('DEV-test',null,'active','VG-VENDO-01','1','1.0.0',null));
        $devices->method('secret')->willReturn('test-secret');
        return new DeviceAuth(new DeviceAuthenticator($devices));
    }

    public function testBearerTokenAuthenticates(): void
    {
        $device=$this->auth()->authenticate([
            'X-Vendo-Device'=>'DEV-test',
            'Authorization'=>'Bearer test-secret'
        ],'{}','POST','/vendo/v1/sync');
        self::assertSame('DEV-test',$device->deviceId);
    }

    public function testLegacySignedHeadersAreRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->auth()->authenticate([
            'X-Vendo-Device'=>'DEV-test',
            'X-Vendo-Timestamp'=>'1700000000',
            'X-Vendo-Nonce'=>'legacy',
            'X-Vendo-Signature'=>str_repeat('a',64)
        ],'{}','POST','/vendo/v1/sync');
    }
}
