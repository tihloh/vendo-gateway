# Vendo Gateway Protocol v1

Base path:

```text
/vendo/v1
```

## 1. Begin pairing

```http
POST /vendo/v1/pairings
Content-Type: application/json
```

Example:

```json
{
  "hardware_uid": "ESP32-A7F29C",
  "hardware_model": "VG-VENDO-01",
  "hardware_revision": "1",
  "firmware_version": "0.1.0",
  "capabilities": {
    "coin": { "channels": 1 },
    "status_led": {}
  }
}
```

Response:

```json
{
  "status": "pairing_required",
  "pairing_id": "PAIR-...",
  "pairing_token": "...",
  "pairing_code": "482731",
  "expires_in": 600
}
```

`pairing_token` is device-private. `pairing_code` is for the installer/admin.

## 2. Admin claim

Admin-side action is intentionally not a public device endpoint.

Host application calls:

```php
$pairingService->claim('482731', $adminUserId, [
    'name' => 'Front Vendo',
    'site_id' => 12
]);
```

## 3. Device polls pairing status

```http
GET /vendo/v1/pairings/{pairing_id}?token={pairing_token}
```

Before claim:

```json
{ "status": "pending" }
```

After claim:

```json
{
  "status": "registered",
  "device_id": "DEV-...",
  "device_secret": "...",
  "credentials_delivered": false
}
```

The secret is returned once. The device must persist it before making another status call.

## 4. Authenticated heartbeat

```http
POST /vendo/v1/heartbeat
X-Vendo-Device: DEV-...
X-Vendo-Timestamp: 1789305000
X-Vendo-Nonce: c4fd...
X-Vendo-Signature: ...
Content-Type: application/json
```

Body:

```json
{
  "firmware_version": "0.1.0",
  "uptime": 7211,
  "rssi": -61,
  "free_heap": 82144,
  "capabilities": {
    "coin": { "channels": 1 },
    "status_led": {}
  }
}
```

Response:

```json
{
  "ok": true,
  "server_time": 1789305001
}
```

## Security rules

- pairing code expires
- pairing token is high entropy and never entered by the human
- permanent device secret is high entropy
- permanent device secret is returned once
- normal requests use HMAC SHA-256
- timestamp window defaults to 5 minutes
- nonces are single-use
- suspended/revoked devices cannot authenticate
- TLS is required in production

## Local AP credentials

Local setup/recovery authentication is a firmware concern, independent from device-server authentication.

Accepted design:

- factory/unconfigured: universal initial AP password
- first setup: changing the AP password is mandatory
- configured Wi-Fi failure: recovery AP uses saved AP password
- forgotten password: physical reset held 10 seconds enables recovery and resets only AP/setup password
- full factory reset: available only from authenticated local setup UI or authenticated Gateway command
