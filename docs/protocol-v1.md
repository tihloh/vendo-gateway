# Vendo Gateway Protocol v1

Base path: `/vendo/v1`. Device traffic should use HTTPS.

## Pairing

The host creates a short-lived setup code. The ESP submits the setup code together with its hardware identity to `POST /pairings`.

The response returns:

```text
device_id
device_token
enrollment_id
ack_required
```

The ESP stores the device ID and token, then acknowledges credential delivery through `POST /pairings/{id}/ack`. Existing stored device secrets are reused as device tokens, so already-paired devices do not need to be paired again.

## Device authentication

Every post-pairing request includes:

```text
X-Vendo-Device: DEV-...
Authorization: Bearer <device_token>
```

The server checks that the device is active and that the token matches its registered credential.

Authentication does not depend on the ESP clock. There is no timestamp, NTP requirement, nonce, request sequence, HMAC, or request-body signature.

## Runtime sync

`POST /sync` is the preferred runtime endpoint. Protocol 2 combines reported state, coin events, command acknowledgements, configuration, desired state and command polling in one exchange.

Events keep their own stable event ID and event sequence for deduplication. Event sequence is not used for authentication.

If the ESP clock has not synchronized yet, `occurred_at` may be omitted. The server still records its own receipt time.

## Commands

Commands remain at-least-once until acknowledged or expired. Devices must handle repeated command IDs safely.

Current firmware commands include:

```text
coin.enable
coin.disable
relay.set
config.refresh
firmware.check
firmware.update
system.restart
system.factory_reset
```

## Other endpoints

Specialized endpoints remain available for compatibility and maintenance:

```text
GET  /vendo/v1/discover
POST /vendo/v1/pairings
POST /vendo/v1/pairings/{id}/ack
POST /vendo/v1/heartbeat
POST /vendo/v1/events
GET  /vendo/v1/commands
POST /vendo/v1/commands/{id}/ack
GET  /vendo/v1/config
POST /vendo/v1/state
POST /vendo/v1/firmware/check
```

All post-pairing endpoints use the same device ID and bearer token.

## Device lifecycle

Devices can be active, suspended or revoked. Suspended or revoked devices cannot authenticate.

## Separation of responsibilities

Vendo Gateway handles device identity, communication, commands, events, state and firmware metadata. The host application owns business rules such as pricing, Wi-Fi time, vouchers and accounting.
