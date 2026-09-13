<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Event;
use Tihloh\VendoGateway\Support\Clock;
final class EventService
{
    public function __construct(private EventRepository $events,private EventDispatcher $dispatcher,private Clock $clock) {}
    public function ingest(string $deviceId,array $input): array
    {
        foreach(['event_id','sequence','type'] as $field)if(!isset($input[$field]))throw new \InvalidArgumentException("Missing {$field}.");$now=$this->clock->now();$event=new DeviceEvent((string)$input['event_id'],$deviceId,(int)$input['sequence'],(string)$input['type'],is_array($input['data']??null)?$input['data']:[],isset($input['occurred_at'])?new \DateTimeImmutable((string)$input['occurred_at']):null,$now);$inserted=$this->events->insert($event);if(!$inserted)return ['accepted'=>true,'duplicate'=>true,'event_id'=>$event->eventId];$processed=$this->process($event);return ['accepted'=>true,'duplicate'=>false,'processed'=>$processed,'event_id'=>$event->eventId];
    }
    public function processPending(int $limit=100): array
    {
        $processed=0;$failed=0;foreach($this->events->pending($limit) as $event){$this->process($event)?$processed++:$failed++;}return ['processed'=>$processed,'failed'=>$failed];
    }
    private function process(DeviceEvent $event): bool
    {
        try{$this->dispatcher->dispatch($event);$this->events->markProcessed($event->eventId,$this->clock->now());return true;}catch(\Throwable){return false;}
    }
}
