<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Pairing;
interface PairingRepository
{
    public function create(array $data): void;
    public function findByToken(string $pairingId,string $pairingToken): ?array;
    public function findByCode(string $setupCode): ?array;
    public function findById(string $pairingId): ?array;
    public function pending(\DateTimeImmutable $now): array;
    public function deleteExpired(\DateTimeImmutable $now): int;
    public function delete(string $pairingId): void;
    public function claim(string $pairingId,string $claimedBy,array $context,\DateTimeImmutable $at,array $deviceInfo=[]): void;
    public function complete(string $pairingId,string $deviceId,string $encryptedDeviceSecret,\DateTimeImmutable $at): void;
    public function markDelivered(string $pairingId,\DateTimeImmutable $at): void;
}
