# Phase I — Test Matrix

**App:** `apps/nursery_app`  
**Date:** 2026-08-11  

Status values: PASS | FAIL | NOT VERIFIED | BLOCKED  
Severity: P0–P4

| ID | Feature | Scenario | Expected | Actual | Status | Severity | Notes |
|----|---------|----------|----------|--------|--------|----------|-------|
| B01 | Build | `flutter analyze` | Clean | No issues | PASS | — | VERIFIED |
| B02 | Build | `flutter test` | All pass | 9/9 | PASS | — | VERIFIED |
| B03 | Build | Release APK | Builds | See build log | NOT VERIFIED | P1 | In progress / check artifact |
| B04 | Build | Release AAB | Builds | — | NOT VERIFIED | P1 | Needs keystore for Play |
| C01 | Config | Release without HTTPS define | App refuses start | Assert added | PASS (code) | P0 | Runtime assert VERIFIED in source |
| C02 | Config | Release signing | Upload keystore | key.properties missing | FAIL | P0 | REQUIRES OWNER INPUT |
| A01 | Auth | Login valid | Session persisted | — | NOT VERIFIED | P1 | Manual |
| A02 | Auth | Invalid credentials | Friendly error | — | NOT VERIFIED | P2 | Manual |
| A03 | Auth | 401 refresh | Silent refresh/retry | Code present | NOT VERIFIED | P1 | Device |
| A04 | Auth | Return intent wishlist/checkout | Returns to intent | — | NOT VERIFIED | P1 | Manual |
| H01 | Home | Banner deep links | Navigate correctly | Code present | NOT VERIFIED | P2 | Manual |
| H02 | Home | Offers / Find Plant CTAs | Open routes | Code present | NOT VERIFIED | P2 | Manual |
| K01 | Catalog | Filters + pagination | Correct API / no dupes | — | NOT VERIFIED | P1 | Manual |
| P01 | Product | Add to cart / wishlist | Sync server | — | NOT VERIFIED | P1 | Manual |
| T01 | Cart | Totals match API | Server authority | — | NOT VERIFIED | P0 | Manual |
| T02 | Cart | Coupon apply/remove | Server totals | — | NOT VERIFIED | P1 | Manual |
| X01 | Checkout | Full COD path | Order created | — | NOT VERIFIED | P0 | Manual |
| X02 | Payment | Success + server verify | Paid only after verify | Design OK | NOT VERIFIED | P0 | Manual + sandbox |
| X03 | Payment | Cancel / fail / double tap | No duplicate orders | Stub blocked in release | NOT VERIFIED | P0 | Manual |
| X04 | Payment | Release + R8 minify | Checkout works | ProGuard rules added | NOT VERIFIED | P1 | Manual release build |
| O01 | Orders | List/detail/track/cancel/reorder | Matches API statuses | — | NOT VERIFIED | P1 | Manual |
| F01 | Finder | Same criteria → same ranking | Deterministic | API smoke earlier | NOT VERIFIED | P1 | Android UI manual |
| F02 | Finder | Semantics selected state | Screen reader | Semantics added | NOT VERIFIED | P2 | TalkBack |
| N01 | Network | Offline mid-flow | Retry, no crash | — | NOT VERIFIED | P1 | Manual |
| S01 | A11y | Text scale largest | Usable | — | NOT VERIFIED | P2 | Manual |
| S02 | A11y | TalkBack primary flows | Labels meaningful | Partial Semantics | NOT VERIFIED | P2 | Manual |
| Z01 | Store | Privacy / Data Safety | Accurate | — | NOT VERIFIED | P0 | REQUIRES OWNER INPUT |
| Z02 | Store | Icon / screenshots | Brand quality | Adaptive shell only | FAIL | P1 | REQUIRES OWNER INPUT |

**Physical device matrix:** NOT VERIFIED (no device model recorded this session).
