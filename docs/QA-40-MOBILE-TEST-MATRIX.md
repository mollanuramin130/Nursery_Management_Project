# QA-40 MOBILE TEST MATRIX

**Date:** 2026-08-15 · **GREEN:** NO

## Customer Mobile

| Screen | Initial | PTR | Soft keep data | Cache | Mock | API fail | API recovery | Device |
|--------|---------|-----|----------------|-------|------|----------|--------------|--------|
| Home | PASS | PASS | PASS | PASS | PASS | PARTIAL | PARTIAL | PASS (shots) |
| Shop | PASS | PASS | PASS | PASS | PASS | UNVERIFIED | PARTIAL (syncGen) | PARTIAL |
| Categories | PASS | PASS | PASS | PARTIAL | PASS | UNVERIFIED | PARTIAL | UNVERIFIED |
| Search | PASS | N/A | N/A | N/A | N/A | N/A | N/A | INTENTIONAL |
| PDP | PASS | PASS (new) | PASS | PASS | PASS | UNVERIFIED | UNVERIFIED | UNVERIFIED |
| Wishlist | PASS | PASS | PASS | PASS | PASS | UNVERIFIED | PARTIAL | UNVERIFIED |
| Cart | PASS | PASS | PASS (soft fetch) | PASS | PASS | PARTIAL | PARTIAL | PASS (shot) |
| Checkout | PASS | N/A | — | — | blocked offline | — | — | UNVERIFIED |
| Orders | PASS | PASS | PASS | PASS | PASS | UNVERIFIED | PARTIAL | UNVERIFIED |
| Order detail | PASS | PASS | PASS | — | — | UNVERIFIED | UNVERIFIED | UNVERIFIED |
| Addresses | PASS | PASS (fixed) | PASS | — | — | UNVERIFIED | UNVERIFIED | UNVERIFIED |
| Notifications / Offers / etc. | PASS | PASS | PASS | — | — | UNVERIFIED | UNVERIFIED | UNVERIFIED |
| Account hub | PASS | N/A | N/A | N/A | N/A | N/A | N/A | INTENTIONAL |

## Admin Mobile

| Screen | PTR | Soft keep | Device |
|--------|-----|-----------|--------|
| Dashboard | PASS | PASS (code) | UNVERIFIED |
| Orders | PASS | PASS (code) | UNVERIFIED |
| Inventory | PASS | PASS (code) | UNVERIFIED |
| Products / More / Purchasing | PARTIAL | OPEN | UNVERIFIED |

## Automated

| Suite | Result |
|-------|--------|
| Customer Flutter | **66** passed |
| Admin Flutter | **27** passed |
| PHPUnit | **253 / 1209** |
