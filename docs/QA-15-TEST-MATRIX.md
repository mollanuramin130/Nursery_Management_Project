# QA-15 TEST MATRIX — Final Acceptance

**Date:** 2026-08-12 · Status: PARTIAL / YELLOW

| ID | Gate | Result |
|----|------|--------|
| T15-01 | API ready local+LAN | PASS |
| T15-02 | Customer/admin login | PASS |
| T15-03 | Customer→admin 403 | PASS |
| T15-04 | Cart cross-session sync | PASS |
| T15-05 | Checkout preview authority | PASS |
| T15-06 | COD place + admin visibility | PASS (`ORD-20260812-00018`) |
| T15-07 | Web page smoke | PASS |
| T15-08 | Device adb + LAN health | PASS |
| T15-09 | Full PHPUnit QA+Phase | PASS 162/754 |
| T15-10 | Web/Admin/Flutter units | PASS |
| T15-11 | composer audit | PASS |
| T15-12 | local_stub production guards (code) | VERIFIED |
| T15-13 | Razorpay live E2E | UNVERIFIED |
| T15-14 | Interactive Customer Mobile UI | UNVERIFIED |
| T15-15 | Interactive Admin Mobile fulfill | UNVERIFIED |
| T15-16 | Playwright full Web journeys | UNVERIFIED |
| T15-17 | Controlled load | UNVERIFIED |
| T15-18 | QA-SEC-001 cookie/BFF | OPEN |
| T15-19 | Production backup/monitor | UNVERIFIED |
| T15-20 | APP_DEBUG production-safe | FAIL on local RC (must flip for prod) |

## Carry-forward classification

| Item | Classification |
|------|----------------|
| QA-SEC-001 | OPEN / HIGH RISK / DEFERRED SECURITY EPIC |
| QA-PERF-010 | ACCEPTED RISK (current scale) |
| QA-PERF-011 | FIXED / VERIFIED |
| QA-ADM-002 | INTENTIONAL / BY DESIGN |
| QA-09 device pick→pack→ship UI | UNVERIFIED |
| Razorpay live | UNVERIFIED → paid launch BLOCKED |
| Load / EXPLAIN / 4-UI | UNVERIFIED |
| Interactive Customer/Admin Mobile | UNVERIFIED |
| Full Web UI journeys | UNVERIFIED |
