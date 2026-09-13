# Changelog

## Unreleased

Complete generic gateway implementation:

- remote config and profiles
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
