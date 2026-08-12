# Phase I — Security Report

**Date:** 2026-08-11  
**Scope:** Source / config review of Android app. **Not** a penetration test or Play Protect certification.

| Control | Result | Notes |
|---------|--------|-------|
| Hardcoded API secrets in Flutter | PASS | None found |
| Razorpay secret in client | PASS | Public key from server payload only |
| Auth tokens storage | PASS | `flutter_secure_storage` |
| Cart token storage | PASS | Secure storage |
| Sensitive debug logs | PASS (source) | No Bearer logging found in `lib/` |
| Release cleartext HTTP | PASS (guard) | Manifest false + runtime assert refuses cleartext/local hosts |
| Debug cleartext | ACCEPTABLE | Debug manifest only |
| `local_stub` payment in release | PASS (guard) | Client throws in `kReleaseMode` |
| Permissions minimal | PASS (app source) | INTERNET; verify merged release manifest before upload |
| ProGuard minify | PASS | Enabled + Razorpay keep rules added |
| Release signing | FAIL | No `key.properties` — debug signing fallback |
| Certificate pinning | NOT IMPLEMENTED | Optional future |
| Backend payment verify | DESIGN OK | Client must not trust SDK success alone — server verify path exists; prod E2E NOT VERIFIED |

## Claims boundary

This document is a **source review**. It does **not** assert full production security compliance.
