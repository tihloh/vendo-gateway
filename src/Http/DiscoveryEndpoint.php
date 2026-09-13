<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Http;
final readonly class DiscoveryEndpoint
{
    public function __construct(private string $name='Vendo Gateway',private string $apiBase='/vendo/v1') {}
    public function handle(): array{return ['protocol'=>'vendo-gateway','version'=>1,'name'=>$this->name,'api_base'=>$this->apiBase,'pairing'=>true,'features'=>['pairing','hmac','heartbeat','capabilities','config','profiles','commands','events','desired_reported_state','ota']];}
}
