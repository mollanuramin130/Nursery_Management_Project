# QA-36 Test Matrix

**Environment:** Local TEST · BFF · `rzp_test_*` · Device `2d3714f`  
**Date:** 2026-08-13  
**Baselines:** QA-35 **243 / 1190** · Stability mid-pass **253 / 1209**

| # | Case | Result | Evidence |
|---|------|--------|----------|
| 1 | PHPUnit baseline vs QA-35 | **PASS** | 243/1190 then 253/1209 |
| 2 | Feature inventory complete | **PASS** | FEATURE-PARITY-MATRIX |
| 3 | Customer Web↔Mobile feature matrix | **PASS** | matrix §1 |
| 4 | Screen-by-screen + L/E/E/S | **PASS** / **PARTIAL** deep UI | matrix §2 |
| 5 | Bottom nav architecture audit | **PASS** | QA-36-007 |
| 6 | Catalog/search/wishlist keep shell | **PASS** | shell routes + test |
| 7 | Intentional full-screen (auth/PDP/checkout/address) | **PASS** | documented |
| 8 | Wishlist remove flicker | **PASS** | QA-36-008 |
| 9 | Web wishlist optimistic remove | **PASS** | store |
| 10 | Admin Web↔Mobile inventory | **PASS** | QA-ADM-002 intentional |
| 11 | Payment feature parity TEST | **PASS** / settle **BLOCKED/UNVERIFIED** | |
| 12 | Order lifecycle cross-client | **PASS** | suites + prior |
| 13 | QA-36-001…006 still fixed | **PASS** | |
| 14 | SEC-001 / BFF | **PASS** | CLOSED |
| 15 | Device connected | **PASS** | adb |
| 16 | Mobile-data w/o reverse | **UNVERIFIED** | |
| 17 | Final PHPUnit | **PASS** | **253 / 1209** |
| 18 | Flutter Customer (+ shell tests) | **PASS** | 38 |
| 19 | Flutter Admin | **PASS** | |
| 20 | Web unit | **PASS** | |
| 21 | LIVE / GREEN | **NO** | |

---

## Legend

PASS · PARTIAL · UNVERIFIED · BLOCKED · OPEN · N/A · NO
