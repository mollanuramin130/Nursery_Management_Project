# QA-25 TEST MATRIX

**Date:** 2026-08-12 · Credentials: **EMPTY**

| # | Case | Result |
|---|------|--------|
| 0 | Env gate — KEY/SECRET/WEBHOOK presence (no values) | **PASS** (EMPTY) |
| 1 | Reject `rzp_live_*` for QA-25 | **PASS** (N/A empty) |
| 2 | `.env` gitignored | **PASS** |
| 3 | Webhook route registered / not 404 | **PASS** |
| 4 | Explicit BLOCKED when empty | **PASS** |
| 5 | COD regression + staff/customer notify | **PASS** |
| 6 | Stub initiate / amount mismatch (Qa24) | **PASS** (prior) |
| 7 | Duplicate webhook idempotency (Qa18) | **PASS** (prior automated) |
| 8 | Real TEST initiate → PSP | **BLOCKED** |
| 9 | Real TEST success + verify + finalize | **BLOCKED** |
| 10 | Real TEST Dynamic QR | **BLOCKED** |
| 11 | Real TEST UPI Intent | **BLOCKED** |
| 12 | Real TEST failure / cancel | **BLOCKED** |
| 13 | Real Dashboard webhook delivery | **BLOCKED** |
| 14 | Customer Web real TEST | **BLOCKED** |
| 15 | Customer Mobile real TEST | **BLOCKED** |
| 16 | Admin Web real visibility | **BLOCKED** |
| 17 | Admin Mobile | **UNVERIFIED** |
| 18 | LIVE payment | **BLOCKED** (out of scope) |
| 19 | GREEN release | **NO** |
