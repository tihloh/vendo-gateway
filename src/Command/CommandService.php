<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Command;
use Tihloh\VendoGateway\Support\Clock;
use Tihloh\VendoGateway\Support\Random;
final class CommandService
{
    public function __construct(private CommandRepository $commands,private Clock $clock) {}
    public function queue(string $deviceId,string $type,array $payload=[],?int $ttlSeconds=300): string
    {
        if($type==='') throw new \InvalidArgumentException('Command type is required.');
        $now=$this->clock->now();$id=Random::id('CMD-',10);
        $this->commands->enqueue(['command_id'=>$id,'device_id'=>$deviceId,'type'=>$type,'payload'=>$payload,'available_at'=>$now,'expires_at'=>$ttlSeconds===null?null:$now->modify("+{$ttlSeconds} seconds"),'created_at'=>$now]);
        return $id;
    }
    public function poll(string $deviceId,int $limit=10): array
    {
        return $this->commands->pending($deviceId,$limit,$this->clock->now());
    }
    public function acknowledge(string $deviceId,string $commandId,string $status,array $result=[]): bool
    {
        return $this->commands->acknowledge($deviceId,$commandId,$status,$result,$this->clock->now());
    }
}
