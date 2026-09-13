# Vendo Gateway

Reusable PHP/Composer device gateway for ESP8266/ESP32 vending hardware.

## v0.1.0 scope

- pairing request + admin claim
- permanent device credentials
- HMAC request authentication
- heartbeat
- capabilities reporting
- device status
- migration SQL
- framework-neutral endpoint handlers

Planned next: remote config, commands + ACK, idempotent events, recovery metadata, OTA.

## Install

```bash
composer require tihloh/vendo-gateway
```

## Database

Run:

```text
database/migrations/001_vendo_gateway.sql
```

The package owns all tables prefixed `vg_`.

## Pairing model

There is intentionally no unique factory secret in v0.1.0.

1. Device calls `POST /vendo/v1/pairings`.
2. Gateway creates:
   - long private `pairing_token`, returned only to the device
   - short human `pairing_code`
3. Installer/admin enters the pairing code in the host application.
4. Host application calls `PairingService::claim()`.
5. Device polls pairing status using its private pairing token.
6. After claim, the device receives `device_id` + `device_secret` once.
7. All normal device calls use HMAC authentication.

The universal initial AP password is firmware/local-setup behavior and is not a server credential.

## HMAC

Authenticated requests send:

```text
X-Vendo-Device: <device_id>
X-Vendo-Timestamp: <unix timestamp>
X-Vendo-Nonce: <random nonce>
X-Vendo-Signature: <hex hmac sha256>
```

Canonical message:

```text
METHOD
/path
timestamp
nonce
sha256(raw_request_body)
```

Signature:

```text
HMAC-SHA256(canonical_message, device_secret)
```

The gateway rejects stale timestamps and reused nonces.

## Framework integration

The package does not own your router/framework. Wire the endpoint handlers from `src/Http/` to your existing routing package.

Example:

```php
$pairing = new PairingEndpoint($pairingService);
$result = $pairing->create($jsonBody);
```

PixiePoint should subscribe to gateway-level events/services later rather than directly manipulate `vg_*` tables.
