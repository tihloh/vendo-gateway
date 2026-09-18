# Changelog

## 1.4.0 - 2026-09-18

- replace timestamp/nonce/sequence HMAC request authentication with per-device bearer tokens
- remove NTP/clock dependency from device authentication
- reuse existing registered device secrets as tokens so paired devices do not need re-pairing
- return `device_token` during pairing credential delivery
- keep protocol-2 unified sync and event sequence deduplication independent from authentication

## 1.3.2 - 2026-09-17

- pairing lifecycle is owned by Vendo Gateway
- list pending setup-code enrollments through `PairingService::pending()`
- remove expired unclaimed setup codes through `PairingService::cleanupExpired()`
- retain completed enrollment until the ESP acknowledges credential delivery
- delete the pairing record after a successful credential ACK
- keep ACK retries idempotent after the pairing record is removed
- include expired pairing cleanup in gateway maintenance

## 1.0.0 - 2026-09-14

First stable release of the generic Vendo Gateway package:

- device pairing and admin claim
- retry-safe permanent credential delivery with explicit device ACK
- HMAC-SHA256 device authentication
- timestamp and nonce replay protection
- heartbeat and capabilities
- encrypted device-secret storage
- remote config and reusable device profiles
- desired/reported state
- command queue, retries, ACK/failure and expiry
- idempotent sequenced events with replayable host processing
- firmware/OTA metadata and channels
- device lifecycle management
- database migration runner
- discovery and lightweight sync endpoint
- package factory, tests and CI

## 0.1.0

- pairing and admin claim
- permanent device credentials
- HMAC-SHA256 request authentication
- timestamp and nonce replay protection
- heartbeat and capabilities
- encrypted device-secret storage
- initial schema and protocol documentation
