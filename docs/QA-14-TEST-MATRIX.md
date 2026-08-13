# QA-14 TEST MATRIX — E2E Business Validation

**Date:** 2026-08-12 · **Status:** PARTIAL

| ID | Case | Evidence | Result |
|----|------|----------|--------|
| T14-01 | API health/ready local+LAN | curl/urllib | PASS |
| T14-02 | Web :3000/:3001 pages | HTTP 200 | PASS |
| T14-03 | Customer login/catalog/cart/preview/COD | live API | PASS |
| T14-04 | COD idempotency X-Request-Id | live + Qa14 | PASS |
| T14-05 | Admin fulfill pick→…→deliver | live `ORD-20260812-00017` | PASS |
| T14-06 | Customer sees DELIVERED + shipment | live | PASS |
| T14-07 | Dual-session cart sync | live | PASS |
| T14-08 | Customer→admin 403 | live + Qa14 | PASS |
| T14-09 | Wishlist duplicate 409 | live | PASS |
| T14-10 | Logout with refresh_token | live | PASS |
| T14-11 | Device LAN health (adb) | vivo 2d3714f | PASS |
| T14-12 | Qa14GoldenJourneyTest | PHPUnit | PASS |
| T14-13 | Full QA+Phase regression | 162 / 754 | PASS |
| T14-14 | Client unit suites | Web/Admin/Flutter | PASS |
| T14-15 | Web+Admin build | npm run build | PASS |
| T14-16 | Customer debug APK | flutter build apk | PASS |
| T14-17 | composer audit | no advisories | PASS |
| T14-18 | Razorpay live | keys empty | UNVERIFIED |
| T14-19 | Browser full UI golden | — | UNVERIFIED |
| T14-20 | Mobile interactive golden | — | UNVERIFIED |
| T14-21 | Admin Mobile device fulfill | — | UNVERIFIED |
| T14-22 | Controlled load | — | UNVERIFIED |
| T14-23 | QA-SEC-001 cookie migration | — | OPEN |

## Carry-forward triage

| Item | Disposition |
|------|-------------|
| QA-SEC-001 | OPEN |
| QA-PERF-010 | OPEN / ACCEPTED at current scale |
| QA-ADM-002 | INTENTIONAL PARTIAL |
| QA-09 device mutations | UNVERIFIED / PARTIAL |
| Razorpay live | UNVERIFIED |
| Load / EXPLAIN / 4-UI | UNVERIFIED |
