<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Tests;
use PHPUnit\Framework\TestCase;
use Tihloh\VendoGateway\Firmware\{FirmwareService,FirmwareRepository,ReleaseManifestService};
use Tihloh\VendoGateway\Device\{Device,DeviceRepository};
use Tihloh\VendoGateway\Command\{CommandService,CommandRepository};
use Tihloh\VendoGateway\Config\{ConfigService,ConfigRepository};
use Tihloh\VendoGateway\Support\Clock;

final class FirmwareTest extends TestCase
{
    private string $cache;
    private array $manifest;
    private array $release;
    private int $requests = 0;
    protected function setUp(): void
    {
        $this->cache=sys_get_temp_dir().'/vg-test-'.bin2hex(random_bytes(8));
        mkdir($this->cache);
        $this->manifest=['protocol'=>'vendo-gateway-firmware','version'=>'1.2.2','channel'=>'stable','hardware_model'=>'VG-VENDO-01','hardware_revision'=>'1','files'=>[
            ['target'=>'esp32','file'=>'vendogate-esp32.bin','sha256'=>str_repeat('a',64),'size'=>1234],
            ['target'=>'esp8266','file'=>'vendogate-esp8266.bin','sha256'=>str_repeat('b',64),'size'=>4321],
        ]];
        $this->release=['tag_name'=>'v1.2.2','draft'=>false,'prerelease'=>false,'assets'=>[
            ['name'=>'manifest.json','browser_download_url'=>'https://example.test/v1.2.2/manifest.json'],
            ['name'=>'vendogate-esp32.bin','browser_download_url'=>'https://example.test/v1.2.2/esp32.bin','size'=>1234,'digest'=>'sha256:'.str_repeat('a',64)],
            ['name'=>'vendogate-esp8266.bin','browser_download_url'=>'https://example.test/v1.2.2/esp8266.bin','size'=>4321,'digest'=>'sha256:'.str_repeat('b',64)],
        ]];
    }
    protected function tearDown(): void
    {
        foreach(glob($this->cache.'/*.json')?:[] as $file)unlink($file);
        rmdir($this->cache);
    }
    private function releases(?\Closure $fetch=null): ReleaseManifestService
    {
        return new ReleaseManifestService('https://example.test/latest/manifest.json',300,'https://example.test/releases/latest',
            $fetch??function(string $url):string{$this->requests++;return json_encode(str_ends_with($url,'manifest.json')?$this->manifest:$this->release,JSON_THROW_ON_ERROR);},$this->cache);
    }
    private function clock(): Clock
    {
        $clock=$this->createStub(Clock::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-09-16T00:00:00Z'));
        return $clock;
    }
    private function gateway(?CommandRepository $commands=null,?ConfigRepository $configs=null,?array $capabilities=null,string $version='1.0.0',?string $hardwareUid=null): FirmwareService
    {
        $devices=$this->createMock(DeviceRepository::class);
        $devices->method('find')->willReturn(new Device('DEV-test',$hardwareUid,'active','VG-VENDO-01','1',$version,null));
        $devices->method('capabilities')->willReturn($capabilities??['platform'=>'esp32']);
        $devices->expects(self::never())->method('heartbeat');
        $devices->expects(self::never())->method('secret');
        return new FirmwareService($this->createStub(FirmwareRepository::class),$this->clock(),$this->releases(),$devices,
            $commands?new CommandService($commands,$this->clock()):null,
            $configs?new ConfigService($configs,$this->clock()):null);
    }
    public function testPageLoadsUseSharedReleaseCacheAndManualCheckRefreshes(): void
    {
        $first=$this->releases()->status('1.0.0','esp32');
        self::assertTrue($first['update_available']);
        self::assertSame('https://example.test/v1.2.2/esp32.bin',$first['url']);
        self::assertSame(2,$this->requests);
        self::assertTrue($this->releases()->status('1.0.0','esp8266')['update_available']);
        self::assertSame(2,$this->requests);
        $this->releases()->status('1.0.0','esp32',true);
        self::assertSame(4,$this->requests);
    }
    public function testMissingTargetCannotEnableUpdate(): void
    {
        array_pop($this->manifest['files']);
        $status=$this->releases()->status('1.0.0','esp8266');
        self::assertNull($status['update_available']);
        self::assertNotEmpty($status['error']);
    }
    public function testDigestMismatchCannotEnableUpdate(): void
    {
        $this->release['assets'][1]['digest']='sha256:'.str_repeat('c',64);
        self::assertNull($this->releases()->status('1.0.0','esp32')['update_available']);
    }
    public function testManifestCannotBeMixedWithAnotherRelease(): void
    {
        $this->manifest['version']='1.3.0';
        self::assertNull($this->releases()->status('1.0.0','esp32')['update_available']);
    }
    public function testFailedRefreshDoesNotOfferStaleUpdate(): void
    {
        self::assertTrue($this->releases()->status('1.0.0','esp32')['update_available']);
        $status=$this->releases(fn()=>null)->status('1.0.0','esp32',true);
        self::assertNull($status['update_available']);
        self::assertNotEmpty($status['error']);
    }
    public function testUnavailableGithubApiCanUseValidatedManifest(): void
    {
        $service=$this->releases(fn(string $url)=>str_ends_with($url,'manifest.json')?json_encode($this->manifest):null);
        self::assertTrue($service->status('1.0.0','esp32')['update_available']);
    }
    public function testOfflineDeviceCheckQueuesNothing(): void
    {
        $commands=$this->createMock(CommandRepository::class);
        $commands->expects(self::never())->method('enqueue');
        self::assertTrue($this->gateway($commands)->requestCheck('DEV-test')['update_available']);
    }
    public function testUpdateQueuesExactVerifiedArtifact(): void
    {
        $commands=$this->createMock(CommandRepository::class);
        $commands->expects(self::once())->method('enqueue')->with(self::callback(function(array $command):bool{
            self::assertSame('firmware.update',$command['type']);
            self::assertSame('https://example.test/v1.2.2/esp32.bin',$command['payload']['url']);
            self::assertSame(str_repeat('a',64),$command['payload']['sha256']);
            self::assertSame('esp32',$command['payload']['target']);
            self::assertSame(1234,$command['payload']['size']);
            self::assertSame(86400,$command['expires_at']->getTimestamp()-$command['created_at']->getTimestamp());
            return true;
        }));
        self::assertStringStartsWith('CMD-',$this->gateway($commands)->requestUpdate('DEV-test'));
    }
    public function testCurrentDeviceCannotQueueUpdate(): void
    {
        $commands=$this->createMock(CommandRepository::class);
        $commands->expects(self::never())->method('enqueue');
        $this->expectException(\RuntimeException::class);
        $this->gateway($commands,version:'1.2.2')->requestUpdate('DEV-test');
    }
    public function testUnknownInstalledVersionCannotEnableUpdate(): void
    {
        self::assertNull($this->gateway(version:'')->deviceStatus('DEV-test')['update_available']);
    }
    public function testSettingsSavedByGatewayPreserveOtherOverrides(): void
    {
        $configs=$this->createMock(ConfigRepository::class);
        $configs->method('device')->willReturn(['config'=>['coin'=>['settle_ms'=>500]]]);
        $configs->expects(self::once())->method('saveDeviceConfig')->with('DEV-test',[
            'coin'=>['settle_ms'=>500],'firmware'=>['auto_check'=>true,'auto_update'=>true,'check_interval_hours'=>6,'channel'=>'stable'],
        ],self::anything())->willReturn(2);
        $commands=$this->createMock(CommandRepository::class);
        $commands->expects(self::once())->method('enqueue')->with(self::callback(fn($c)=>$c['type']==='config.refresh'));
        $this->gateway($commands,$configs)->saveSettings('DEV-test',['auto_check'=>true,'auto_update'=>true,'check_interval_hours'=>6]);
    }
    public function testPolicyDefaultsDoNotEnableAutoInstall(): void
    {
        self::assertFalse($this->gateway()->normalizeSettings([])['auto_update']);
    }
    public function testInvalidIntervalRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->gateway()->normalizeSettings(['check_interval_hours'=>0]);
    }
    public function testAutomaticInstallRequiresChecks(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->gateway()->normalizeSettings(['auto_check'=>false,'auto_update'=>true]);
    }
    public function testReportedPlatformWorksWithoutCurrentEspContact(): void
    {
        $configs=$this->createStub(ConfigRepository::class);
        $configs->method('device')->willReturn(['profile_key'=>null,'config_version'=>1,'config'=>[],'reported_state'=>['hardware'=>['platform'=>'esp8266']]]);
        $status=$this->gateway(configs:$configs,capabilities:[])->deviceStatus('DEV-test');
        self::assertTrue($status['update_available']);
        self::assertSame('esp8266',$status['target']);
    }
    public function testLegacyDeviceHardwareUidRestoresMissingTarget(): void
    {
        $status=$this->gateway(capabilities:[],hardwareUid:'ESP8266-A1B2C3')->deviceStatus('DEV-test');
        self::assertTrue($status['update_available']);
        self::assertSame('esp8266',$status['target']);
    }
    public function testLegacyTopLevelReportedPlatformRestoresMissingTarget(): void
    {
        $configs=$this->createStub(ConfigRepository::class);
        $configs->method('device')->willReturn(['profile_key'=>null,'config_version'=>1,'config'=>[],'reported_state'=>['platform'=>'esp32']]);
        self::assertSame('esp32',$this->gateway(configs:$configs,capabilities:[])->deviceStatus('DEV-test')['target']);
    }
    public function testHardwareMismatchCannotEnableUpdate(): void
    {
        $this->manifest['hardware_model']='different';
        self::assertNull($this->gateway()->deviceStatus('DEV-test')['update_available']);
    }
    public function testCommandIdHasLegacyAlias(): void
    {
        $commands=$this->createStub(CommandRepository::class);
        $commands->method('pending')->willReturn([['command_id'=>'CMD-test','type'=>'firmware.update','payload'=>[]]]);
        $result=(new CommandService($commands,$this->clock()))->poll('DEV-test');
        self::assertSame('CMD-test',$result[0]['id']);
        self::assertSame('CMD-test',$result[0]['command_id']);
    }
    public function testConfigEnvelopeKeepsCanonicalAndLegacyFields(): void
    {
        $configs=$this->createStub(ConfigRepository::class);
        $configs->method('device')->willReturn(['profile_key'=>null,'config_version'=>1,'config'=>['firmware'=>['auto_check'=>false]]]);
        $service=new ConfigService($configs,$this->clock());
        $first=$service->pull('DEV-test');
        self::assertSame($first['config']['firmware'],$first['firmware']);
        self::assertFalse($service->pull('DEV-test',$first['revision'])['changed']);
    }

    private function authentication(string $raw,string $path): array
    {
        $devices=$this->createStub(DeviceRepository::class);
        $devices->method('find')->willReturn(new Device('DEV-test',null,'active','VG-VENDO-01','1','1.0.0',null));
        $devices->method('secret')->willReturn('test-secret');
        $auth=new \Tihloh\VendoGateway\Http\DeviceAuth(new \Tihloh\VendoGateway\Auth\DeviceAuthenticator($devices));
        $headers=['X-Vendo-Device'=>'DEV-test','Authorization'=>'Bearer test-secret'];
        return [$auth,$headers];
    }
    public function testLegacyFailureAcknowledgementIsNotMarkedSuccessful(): void
    {
        $raw='{"ok":false,"result":{"error":"sha256_mismatch"}}';$path='/vendo/v1/commands/CMD-test/ack';
        [$auth,$headers]=$this->authentication($raw,$path);
        $commands=$this->createMock(CommandRepository::class);
        $commands->expects(self::once())->method('acknowledge')->with('DEV-test','CMD-test','failed',['error'=>'sha256_mismatch'],self::anything())->willReturn(true);
        $endpoint=new \Tihloh\VendoGateway\Http\CommandEndpoint($auth,new CommandService($commands,$this->clock()));
        self::assertTrue($endpoint->acknowledge($headers,$raw,'POST',$path,'CMD-test')['ok']);
    }
    public function testDeviceFirmwareEndpointUsesSameVerifiedReleaseCatalog(): void
    {
        $raw='{}';$path='/vendo/v1/firmware/check';
        [$auth,$headers]=$this->authentication($raw,$path);
        $endpoint=new \Tihloh\VendoGateway\Http\FirmwareEndpoint($auth,$this->gateway());
        $result=$endpoint->check($headers,$raw,'POST',$path);
        self::assertTrue($result['update_available']);
        self::assertSame('1.2.2',$result['firmware']['version']);
        self::assertSame(1234,$result['firmware']['size_bytes']);
    }
}
