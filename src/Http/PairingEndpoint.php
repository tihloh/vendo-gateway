<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Http;
use Tihloh\VendoGateway\Pairing\PairingService;
final class PairingEndpoint
{
    public function __construct(private PairingService $pairings) {}
    public function enroll(array $body): array{$code=(string)($body['setup_code']??'');unset($body['setup_code']);return $this->pairings->enroll($code,$body);}
    public function acknowledge(string $enrollmentId,string $setupCode): array{return $this->pairings->acknowledge($enrollmentId,$setupCode);}
}
