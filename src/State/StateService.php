<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\State;
use Tihloh\VendoGateway\Support\Clock;
final class StateService
{
    public function __construct(private StateRepository $states,private Clock $clock) {}
    public function desired(string $deviceId): array{return $this->states->desired($deviceId);}
    public function setDesired(string $deviceId,array $state): void{$this->states->setDesired($deviceId,$state,$this->clock->now());}
    public function report(string $deviceId,array $state): array{$this->states->report($deviceId,$state,$this->clock->now());return ['ok'=>true,'desired'=>$this->states->desired($deviceId)];}
}
