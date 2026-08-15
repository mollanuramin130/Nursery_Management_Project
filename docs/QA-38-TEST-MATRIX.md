# QA-38 TEST MATRIX

**Date:** 2026-08-15 · **GREEN:** NO

## A. Offline sync (prior)

| Case | Expected | Result |
|------|----------|--------|
| Cache-first home | Peek cache then soft update | PASS (unit) |
| Mock ≯ API cache | `CacheEnvelope.mayWrite` | PASS (unit) |
| Reconnect sync | syncGeneration bump | PASS (unit) |
| Wishlist flicker | epoch / no reseed | PASS (unit) |

## B. UI polish

| Case | Platform | Expected | Result |
|------|----------|----------|--------|
| Toast above sticky Buy/Cart | Customer Web PDP | Toast clears CTA + bottom nav | PASS (code) / UNVERIFIED device |
| Toast above Place Order | Customer Web checkout step 4 | Same | PASS (code) / UNVERIFIED device |
| Network banner does not cover header | Customer Web | Banner in flow, readable | PASS (code) |
| Wishlist active red | Web + Mobile | Filled red heart | PASS (code) |
| Wishlist inactive neutral | Web + Mobile | Muted, not brand green | PASS (code) |
| Disabled outline button | Customer Web | Visible disabled opacity | PASS (code) |
| Sale badge red | Web + Mobile | `sale` tone | PASS (code) |
| Snackbar above bottom nav | Customer Mobile | Margin ≥ nav + inset | PASS (code) / UNVERIFIED device |
| Admin toast contrast | Admin Web | Solid white-on-semantic | PASS (code) |
| Admin snackbar vs nav | Admin Mobile | insetPadding clears nav | PASS (code) |
| Admin login loading | Admin Mobile | Spinner visible | PASS (code) |
| Offline architecture intact | Mobile | No fake pay/order | PASS (regression) |

## C. Regression suites

| Suite | Result |
|-------|--------|
| `php artisan test` | 253 / 1209 (251+2 skip) |
| `nursery_app` flutter test | 58 passed |
| `nursery_admin_mobile` flutter test | 27 passed |
| `nursery-web` qa-unit-checks | PASS |

## D. Device

| Device | Result |
|--------|--------|
| Vivo physical | **UNVERIFIED** this session |
