# QA-40 TEST MATRIX

**Date:** 2026-08-15 · **GREEN:** NO

| Area | Customer Web | Customer Mobile | Admin Web | Admin Mobile |
|------|--------------|-----------------|-----------|--------------|
| Primary button contrast | PASS (code) | PASS (code + Vivo pixel sample) | PASS (code) | PASS (theme intact) |
| Disabled/loading buttons | PASS | PASS (unit) | PASS | UNVERIFIED |
| Wishlist heart | PASS | PASS (code) | N/A | N/A |
| Wishlist optimistic / no flash | PASS (store) | PASS (code) / UNVERIFIED device | N/A | N/A |
| Image fallback | PASS (SafeImage expanded) | PASS (ResilientNetworkImage) | PARTIAL (admin lists) | UNVERIFIED |
| Cache → mock → API | N/A (BFF) | PASS (home/catalog/orders) | N/A | INTENTIONAL online |
| Soft refresh (no blank) | PARTIAL | PASS (orders/categories/home) | PARTIAL (analytics FIXED; lists OPEN) | UNVERIFIED |
| Bottom nav / snackbar | PASS (toast tokens) | PASS (device) | N/A | PASS (theme inset) |
| Offline UX | PARTIAL | PASS (device banner) | N/A | INTENTIONAL |
| Responsive | UNVERIFIED | N/A | UNVERIFIED | N/A |
| Auth / session | PASS (prior) | PASS (prior) | PASS (prior) | PASS (prior) |
| COD / Razorpay TEST | UNVERIFIED | UNVERIFIED | N/A | N/A |
| Payment security | UNCHANGED | UNCHANGED | UNCHANGED | UNCHANGED |

## Automated

| Suite | Result |
|-------|--------|
| PHPUnit | 253 tests / 1209 assertions |
| Customer Flutter | 63 passed |
| Admin Flutter | 27 passed |
| Customer Web `npm run test:unit` | PASS |

## Parity (functionality meaning)

| Feature | Web vs Mobile | Notes |
|---------|---------------|-------|
| Auth / wishlist / cart / checkout / orders | PASS / PARTIAL | Prior QA-37; QA-40 does not regress |
| Offline cache/mock | INTENTIONAL DIFF | Mobile rich; Web BFF-online |
| Admin offline | INTENTIONAL DIFF | Ops online-first |
