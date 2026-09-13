<?php
declare(strict_types=1);

namespace Tihloh\VendoGateway\Http;

use Tihloh\VendoGateway\Pairing\PairingService;

final class PairingEndpoint
{
    public function __construct(private PairingService $pairings) {}

    public function create(array $body): array
    {
        return $this->pairings->begin($body);
    }

    public function status(string $pairingId, string $pairingToken): array
    {
        return $this->pairings->status($pairingId, $pairingToken);
    }
}
