<?php
declare(strict_types=1);

namespace Tihloh\VendoGateway\Auth;

use Tihloh\VendoGateway\Device\Device;
use Tihloh\VendoGateway\Device\DeviceRepository;
use Tihloh\VendoGateway\Support\Clock;

final class DeviceAuthenticator
{
    public function __construct(
        private DeviceRepository $devices,
        private NonceRepository $nonces,
        private RequestSequenceRepository $sequences,
        private Clock $clock,
        private int $allowedClockSkewSeconds=300
    ) {}

    public function authenticate(
        string $deviceId,
        int $timestamp,
        string $nonce,
        string $signature,
        string $method,
        string $path,
        string $body
    ): Device {
        if($this->sequences->mode($deviceId)==='sequence'){
            throw new \RuntimeException('Nonce authentication is disabled for this device.');
        }
        $device=$this->verify($deviceId,$timestamp,$nonce,$signature,$method,$path,$body);
        $now=$this->clock->now();
        if(!$this->nonces->consume($deviceId,$nonce,$now,$now->modify("+{$this->allowedClockSkewSeconds} seconds"))){
            throw new \RuntimeException('Replay detected.');
        }
        return$device;
    }

    public function authenticateSequence(
        string $deviceId,
        int $timestamp,
        string $sequence,
        string $signature,
        string $method,
        string $path,
        string $body,
        bool $allowPromote=false
    ): Device {
        $device=$this->verify($deviceId,$timestamp,$sequence,$signature,$method,$path,$body);
        if(!$this->sequences->accept($deviceId,$sequence,$allowPromote)){
            throw new \RuntimeException('Sequence rejected.');
        }
        return$device;
    }

    private function verify(
        string $deviceId,
        int $timestamp,
        string $requestValue,
        string $signature,
        string $method,
        string $path,
        string $body
    ): Device {
        $device=$this->devices->find($deviceId);
        if(!$device||$device->state!=='active')throw new \RuntimeException('Device authentication failed.');
        $now=$this->clock->now();
        if(abs($now->getTimestamp()-$timestamp)>$this->allowedClockSkewSeconds){
            throw new \RuntimeException('Request timestamp is outside the allowed window.');
        }
        $secret=$this->devices->secret($deviceId);
        if(!$secret)throw new \RuntimeException('Device authentication failed.');
        $expected=Signature::sign($secret,$method,$path,$timestamp,$requestValue,$body);
        if(!hash_equals($expected,strtolower($signature)))throw new \RuntimeException('Device authentication failed.');
        return$device;
    }
}
