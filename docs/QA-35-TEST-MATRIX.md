# QA-35 Test Matrix

**Environment:** Local TEST · BFF · `rzp_test_*` · Device `2d3714f`  
**Date:** 2026-08-13  
**Baseline:** QA-34 COMPLETE · 242 / 1184

| # | Case | Result | Evidence |
|---|------|--------|----------|
| 1 | Baseline PHPUnit vs QA-34 | **PASS** | 242 / 1184 |
| 2 | Baseline Web/Admin unit + builds | **PASS** | |
| 3 | Baseline Flutter + analyze | **PASS** | info-only |
| 4 | BFF / SEC-001 regression | **PASS** | smoke |
| 5 | API contract spot (orders/payments) | **PASS** | |
| 6 | Customer Web auth/session | **PASS** | BFF |
| 7 | Customer Web order detail banners | **PASS** | QA-35-002/004 |
| 8 | Customer Web returns error state | **PASS** | QA-35-006 |
| 9 | Customer Mobile PENDING copy | **PASS** | QA-35-002 |
| 10 | Admin Web status transitions labels | **PASS** | QA-35-003 |
| 11 | Admin Web payment labels | **PASS** | QA-35-005 |
| 12 | Admin Mobile return deep link | **PASS** | QA-35-001 + test |
| 13 | Admin Mobile status busy + labels | **PASS** | QA-35-003 |
| 14 | Cross-client order/payment parity | **PASS** | |
| 15 | UI uniformity (status/payment) | **PASS** | |
| 16 | Commerce lifecycle suite | **PASS** | prior + filter |
| 17 | Razorpay TEST automated | **PASS** | Qa18–Qa31 |
| 18 | COD | **PASS** | |
| 19 | Dynamic QR settlement | **UNVERIFIED** | |
| 20 | UPI-app settlement | **BLOCKED** | |
| 21 | Concurrency/idempotency suite | **PASS** | |
| 22 | Notifications stub/in-app | **PASS** | |
| 23 | Device APK launch | **PASS** | |
| 24 | Full interactive every-screen UI | **PARTIAL** | targeted + prior |
| 25 | Mobile-data w/o reverse | **UNVERIFIED** | |
| 26 | QA-ADM-002 | **OPEN** intentional | |
| 27 | Final PHPUnit | **PASS** | **243 / 1190** |
| 28 | Final builds / BFF smoke | **PASS** | |
| 29 | LIVE Razorpay / FCM / GREEN | **NO** / N/A | |

---

## Legend

PASS · PARTIAL · UNVERIFIED · BLOCKED · OPEN · N/A · NO
