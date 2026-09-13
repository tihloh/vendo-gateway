# Changelog

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
