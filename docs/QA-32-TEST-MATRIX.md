# QA-32 Test Matrix

**Environment:** Local TEST · `rzp_test_*` · Device `2d3714f` + `adb reverse`  
**Date:** 2026-08-13  
**Baseline:** QA-31 PARTIAL

| # | Case | Result | Evidence |
|---|------|--------|----------|
| 1 | Preflight API/Web/Admin/device/TEST keys | **PASS** | health 200; CSP headers; `rzp_test_*` |
| 2 | Audit QA-SEC-001 | **PARTIAL** | CSP mitigation live; localStorage JWT **OPEN** |
| 3 | Audit QA-ADM-002 | **PASS** (intentional) | Ops subset documented; not force-parity |
| 4 | Customer/Admin login + wrong password | **PASS** | `AUTH_INVALID_CREDENTIALS` |
| 5 | Logout + refresh revoke | **PASS** | access 401; refresh invalid |
| 6 | COD order lifecycle + return/refund | **PASS** | Order **9069** full path |
| 7 | Invalid transition rejected | **PASS** | State machine (prior + UX gates) |
| 8 | Notifications in-app for lifecycle | **PASS** | packed/shipped/OFD/delivered/return |
| 9 | LIVE FCM | **N/A** | OUT OF SCOPE |
| 10 | Razorpay Checkout TEST | **PASS** | Carry-forward QA-30 **9066** |
| 11 | Dynamic QR generation | **PASS** | Carry-forward QA-31 **9067** |
| 12 | Dynamic QR settlement | **UNVERIFIED** | No completed QR charge |
| 13 | UPI Intent handoff | **PASS** | Carry-forward QA-31 **9068** |
| 14 | True UPI-app settlement | **BLOCKED** | No TEST UPI payment account |
| 15 | Amount authority / idempotency / webhook | **PASS** | PHPUnit Qa18–Qa31 |
| 16 | Client never marks PAID | **PASS** | Code + payment tests |
| 17 | Admin status label uniformity | **PASS** | QA-32-001 + unit tests |
| 18 | Customer Mobile detail return labels | **PASS** | QA-32-002 |
| 19 | Cross-client business parity | **PASS** | Same API statuses/amounts |
| 20 | Device APK launch + reverse health | **PASS** | Both packages |
| 21 | Full interactive mobile UI re-walk | **PARTIAL** | Launch + prior phases |
| 22 | Mobile-data without reverse | **UNVERIFIED** | Not performed |
| 23 | PHPUnit QA filter | **PASS** | **240 / 1170** |
| 24 | Web/Admin unit + builds | **PASS** | |
| 25 | Flutter tests + analyze | **PASS** | Admin 25; Customer UPI 4 |
| 26 | composer / npm audit | **PASS** | No advisories / 0 vulns |
| 27 | LIVE Razorpay | **N/A** | OUT OF SCOPE |
| 28 | GREEN production claim | **NO** | Explicit |

---

## Legend

PASS · PARTIAL · UNVERIFIED · BLOCKED · OPEN · N/A · NO
