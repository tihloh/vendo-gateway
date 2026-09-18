<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Tests;

use PHPUnit\Framework\TestCase;
use Tihloh\VendoGateway\Auth\DeviceAuthenticator;
use Tihloh\VendoGateway\Device\Device;
use Tihloh\VendoGateway\Device\DeviceRepository;
use Tihloh\VendoGateway\Http\DeviceAuth;

final class DeviceAuthTest extends TestCase
{
    private function auth(string $state='active'): DeviceAuth
    {
        $devices=$this->createStub(DeviceRepository::class);
        $devices->method('find')->willReturn(new Device('DEV-test',null,$state,null,null,null,null));
        $devices->method('secret')->willReturn('test-token');
        return new DeviceAuth(new DeviceAuthenticator($devices));
    }

    public function testBearerTokenAuthenticatesActiveDevice(): void
    {
        $device=$this->auth()->authenticate([
            'X-Vendo-Device'=>'DEV-test',
            'Authorization'=>'Bearer test-token'
        ],'{}','POST','/vendo/v1/sync');
        self::assertSame('DEV-test',$device->deviceId);
    }

    public function testWrongTokenIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->auth()->authenticate([
            'X-Vendo-Device'=>'DEV-test',
            'Authorization'=>'Bearer wrong-token'
        ],'{}','POST','/vendo/v1/sync');
    }

    public function testSuspendedDeviceIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->auth('suspended')->authenticate([
            'X-Vendo-Device'=>'DEV-test',
            'Authorization'=>'Bearer test-token'
        ],'{}','POST','/vendo/v1/sync');
    }
}
