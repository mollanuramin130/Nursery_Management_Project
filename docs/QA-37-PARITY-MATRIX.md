# QA-37 Parity Matrix — Customer Web ↔ Customer Mobile ↔ API

**Date:** 2026-08-13 · **TEST only** · **GREEN:** NO  
Statuses: **PASS** · **PARTIAL** · **MISSING-WEB** · **MISSING-MOBILE** · **INTENTIONAL-DIFFERENCE** · **BUG** (fixed this phase noted)

---

## Feature / API / business parity

| Feature | Web | Mobile | API contract | Auth | Classification |
|---------|-----|--------|--------------|------|----------------|
| Register / Login / Logout | PASS | PASS | `/auth/*` | JWT BFF / Bearer | **PASS** |
| Session refresh / expiry | PASS | PASS | refresh + clear | — | **PASS** (QA-37-001 heart clear) |
| Forgot / Reset password | PASS | PASS | reset endpoints | — | **PASS** |
| Profile | PASS | PASS | `PUT /customer/profile` | Yes | **PASS** |
| Address CRUD | PASS | PASS | `/customer/addresses` | Yes | **PASS** |
| Home / banners / campaigns | PASS | PASS | `/home` | No | **PASS** |
| Categories / listing / search / sort / filter | PASS | PASS | catalog APIs | No | **PASS** |
| Product detail / images / stock / price | PASS | PASS | `/products/:slug` | No | **PASS** |
| Variant picker | PARTIAL | PARTIAL | variants in API | No | **PARTIAL** both |
| Wishlist add/remove/list | PASS | PASS | `/wishlist` | Yes | **PASS** (optimistic QA-36-008) |
| Cart CRUD / coupon / free delivery | PASS | PASS | `/cart*` | Guest+auth | **PASS** (server totals) |
| Checkout preview / place | PASS | PASS | preview + place | Yes | **PASS** |
| COD | PASS | PASS | place COD | Yes | **PASS** |
| Razorpay Checkout | PASS | PASS | payments | Yes | **PASS** TEST |
| Dynamic QR | PASS | MISSING | payments | Yes | **INTENTIONAL-DIFFERENCE** |
| UPI Intent | PASS | PASS | payments | Yes | **PASS** / settle **BLOCKED** |
| Payment retry / status | PASS | PASS | retry + GET payment | Yes | **PASS** |
| Orders list/detail/timeline | PASS | PASS | `/orders*` | Yes | **PASS** |
| Cancel / Return / Refund view | PASS | PASS | cancel/returns | Yes | **PASS** |
| Reorder | PASS | PASS | reorder | Yes | **PASS** |
| Notifications | PASS | PASS | notifications | Yes | **PASS** |
| Deep links | PASS | PASS | data.route | — | **PASS** |
| Bottom / primary nav | Header | Bottom shell | N/A | — | **PASS** platform chrome |
| Stock-alert cancel/state | PASS | PARTIAL | alerts API | Yes | **PARTIAL** (LOW; Mobile subscribe-only) |

---

## Business rules

| Rule | Authority | Web | Mobile | Status |
|------|-----------|-----|--------|--------|
| Cart / checkout totals | Server preview | Display API | Display API | **PASS** |
| Coupon / free delivery | Server | Same | Same | **PASS** |
| Stock / checkout block | Server | Same | Same | **PASS** |
| Payment amount | Server | Same | Same | **PASS** |
| Order / payment / return status | Server enums + labels | Same meaning | Same meaning | **PASS** |
| Cancel / return eligibility | Server `actions` | Same | Same | **PASS** |

---

## UI / UX terminology

| Concept | Web | Mobile | OK? |
|---------|-----|--------|-----|
| PENDING_PAYMENT | Order placed | Order placed | Yes |
| Payment success | Paid | Paid | Yes |
| Add to cart / Wishlist | Same CTAs | Same CTAs | Yes |
| Returns access | Account → Returns | Account → My returns | Yes |

---

## Intentional differences

1. Web header vs Mobile bottom nav  
2. Dynamic QR UI Web-only; Mobile UPI Intent primary  
3. Full-screen Mobile: auth, PDP, checkout, addresses  
4. Path chrome: `/account/orders` Web ↔ `/orders` Mobile (helpers remap)  
5. Pagination vs infinite scroll  

---

## Bugs fixed in QA-37

| ID | Summary |
|----|---------|
| QA-37-001 | Session expiry left Mobile wishlist hearts stale |
| QA-37-002 | Cart qty/remove race (both clients) |
| QA-37-003 | PDP wishlist double-tap (both) |
| QA-37-004 | Web cart fetch error looked empty |
| QA-37-005 | Web wishlist row busy + remove error toast |

See `docs/QA-37-BUG-REGISTER.md`.
