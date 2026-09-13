# Vendo Gateway Protocol v1

Base path: `/vendo/v1`. Production deployments must use HTTPS.

## Discovery

`GET /discover` is unauthenticated and returns the protocol identifier, version, API base and supported features.

## Pairing

`POST /pairings`

```json
{
  "hardware_uid":"ESP32-A7F29C",
  "hardware_model":"VG-VENDO-01",
  "hardware_revision":"1",
  "firmware_version":"1.0.0",
  "capabilities":{"coin":{"channels":1},"relay":{"channels":1}}
}
```

The gateway returns a long private `pairing_token` for the device and a short `pairing_code` for the administrator. The host application claims the short code. The device polls pairing status with its private token and receives `device_id` and `device_secret` once.

A non-revoked `hardware_uid` cannot silently register as another device. Re-pairing requires an explicit administrative recovery/revocation flow.

## Authentication

All post-pairing device requests include:

```text
X-Vendo-Device: DEV-...
X-Vendo-Timestamp: 1789305000
X-Vendo-Nonce: random-per-request
X-Vendo-Signature: hex-hmac-sha256
```

Canonical input:

```text
METHOD
/path
unix_timestamp
nonce
sha256(raw_request_body)
```

The path is the API path only; exclude scheme, host and query string. The default clock-skew window is 300 seconds. Nonces are single-use within that window.

## Heartbeat

`POST /heartbeat`

Reports firmware version and optional capabilities. The server records last-seen time/IP and returns server time.

## Sync

`POST /sync` is the preferred lightweight device poll.

```json
{
  "config_revision":"previous-sha256-or-null",
  "reported_state":{"coin_enabled":true},
  "firmware_version":"1.0.0",
  "command_limit":10
}
```

Response contains configuration only when changed, desired state, pending commands, OTA availability and server time.

## Configuration

Configuration is server-owned. Device profiles provide common config, required capability names and firmware channel. Per-device config overlays profile config recursively. A SHA-256 revision of the resolved config changes whenever either layer changes.

## Commands

Generic examples:

```text
coin.enable
coin.disable
relay.set
display.write
system.factory_reset
firmware.update
```

Commands are delivered at least once until ACK/failure or expiry. Devices must treat `command_id` idempotently.

ACK body:

```json
{"status":"acked","result":{"ok":true}}
```

`status` is `acked` or `failed`.

## Events

`POST /events`

```json
{
  "event_id":"DEV01-4821",
  "sequence":4821,
  "type":"coin.inserted",
  "occurred_at":"2026-09-13T14:00:00Z",
  "data":{"channel":1,"pulses":5}
}
```

Both `event_id` and `(device_id, sequence)` are unique. Safe retries cannot credit the same physical event twice. Device acceptance is separated from host business processing; failed host handlers can be replayed from stored unprocessed events.

## Desired/reported state

The host sets desired state. The device reports actual state. This is appropriate for persistent intentions such as `coin_enabled`, relay state or maintenance mode. One-shot actions should use commands instead.

## Firmware / OTA

Firmware records are selected by hardware model, optional revision and channel. Metadata contains version, download URL, SHA-256, size, mandatory flag and release notes. The firmware image itself may be hosted anywhere accessible to the device. Devices must verify SHA-256 before installing.

## Device states

Server device lifecycle:

```text
active
suspended
revoked
```

Suspended/revoked devices cannot authenticate. Revocation permits an explicit hardware re-pairing flow.

## Separation of responsibilities

Vendo Gateway understands physical/device semantics (`coin.inserted`, relay state, firmware). The host application owns commercial semantics (money, promotional credit, Wi-Fi time, charging service, vouchers, sales and accounting).
