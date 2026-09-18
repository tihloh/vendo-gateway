<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Http;

use Tihloh\VendoGateway\Command\CommandService;
use Tihloh\VendoGateway\Config\ConfigService;
use Tihloh\VendoGateway\Event\EventService;
use Tihloh\VendoGateway\Firmware\FirmwareService;
use Tihloh\VendoGateway\Heartbeat\HeartbeatService;
use Tihloh\VendoGateway\State\StateService;

final class SyncEndpoint
{
    public function __construct(
        private DeviceAuth $auth,
        private ConfigService $configs,
        private CommandService $commands,
        private StateService $states,
        private FirmwareService $firmware,
        private EventService $events,
        private HeartbeatService $heartbeats
    ) {}

    public function handle(array $headers,string $rawBody,string $method,string $path,?string $ip=null): array
    {
        $device=$this->auth->authenticate($headers,$rawBody,$method,$path);
        $payload=json_decode($rawBody,true,flags:JSON_THROW_ON_ERROR);
        if(!is_array($payload))throw new \InvalidArgumentException('Invalid sync payload.');
        $protocol=(int)($payload['protocol']??1);

        if(isset($payload['reported_state'])&&is_array($payload['reported_state'])){
            $this->states->report($device->deviceId,$payload['reported_state']);
        }
        $this->heartbeats->record($device->deviceId,[
            'firmware_version'=>$payload['firmware_version']??$device->firmwareVersion
        ],$ip);

        $acceptedEvents=[];
        $acceptedAcks=[];
        if($protocol>=2){
            $acks=is_array($payload['command_acks']??null)?array_slice($payload['command_acks'],0,4):[];
            foreach($acks as $ack){
                if(!is_array($ack))continue;
                $id=(string)($ack['command_id']??'');
                if($id===''||strlen($id)>64)continue;
                $ok=(bool)($ack['ok']??true);
                $result=is_array($ack['result']??null)?$ack['result']:[];
                if($this->commands->acknowledge($device->deviceId,$id,$ok?'acked':'failed',$result))$acceptedAcks[]=$id;
            }
            $events=is_array($payload['events']??null)?array_slice($payload['events'],0,4):[];
            foreach($events as $event){
                if(!is_array($event))continue;
                try{
                    $accepted=$this->events->ingest($device->deviceId,$event);
                    if(($accepted['accepted']??false)===true)$acceptedEvents[]=(string)($accepted['event_id']??$event['event_id']??'');
                }
                catch(\InvalidArgumentException){
                    break;
                }
            }
        }

        $config=$this->configs->pull($device->deviceId,isset($payload['config_revision'])?(string)$payload['config_revision']:null);
        $resolved=$this->configs->resolve($device->deviceId);
        if($protocol>=2){
            $limit=max(0,min(1,(int)($payload['max_commands']??0)));
            $response=[
                'protocol'=>2,
                'config'=>$config,
                'desired_state'=>$this->states->desired($device->deviceId),
                'commands'=>$limit?$this->commands->poll($device->deviceId,$limit):[],
                'firmware'=>null,
                'accepted_event_ids'=>array_values(array_filter($acceptedEvents,static fn(string $id):bool=>$id!=='')),
                'accepted_command_ack_ids'=>$acceptedAcks,
                'server_time'=>time()
            ];
            return$response;
        }

        return[
            'config'=>$config,
            'desired_state'=>$this->states->desired($device->deviceId),
            'commands'=>$this->commands->poll($device->deviceId,(int)($payload['command_limit']??10)),
            'firmware'=>null,
            'server_time'=>time()
        ];
    }
}
