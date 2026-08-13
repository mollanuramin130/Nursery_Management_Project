# QA-27 TEST MATRIX

**Date:** 2026-08-13 · Readiness only · No LIVE charge

| # | Case | Result |
|---|------|--------|
| 1 | Local Razorpay vars presence (no values) | **PASS** (SET, test class) |
| 2 | No `rzp_live_*` on local | **PASS** |
| 3 | Production checker rejects `rzp_test_*` | **PASS** |
| 4 | Production checker requires unsigned=false | **PASS** |
| 5 | Production checker requires HTTPS APP_URL | **PASS** |
| 6 | Production refuses stub when keys empty | **PASS** |
| 7 | Webhook route registered | **PASS** |
| 8 | Unsigned webhooks false in `.env` | **PASS** |
| 9 | `--strict` on local | **FAIL** (expected) |
| 10 | `--strict` on production host | **UNVERIFIED** |
| 11 | LIVE credentials on prod | **NOT CONFIGURED / UNVERIFIED** |
| 12 | LIVE HTTPS webhook | **UNVERIFIED** |
| 13 | Payment security automated | **PASS** |
| 14 | COD + TEST payment regression | **PASS** |
| 15 | QA-SEC-001 | **OPEN** |
| 16 | Backup / restore / rollback / monitoring | **UNVERIFIED** |
| 17 | LIVE charge executed | **BLOCKED** (not run) |
| 18 | GREEN | **NO** |
