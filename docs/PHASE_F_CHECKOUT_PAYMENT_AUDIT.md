# Phase F — Checkout + Real Payment + Order Success Audit

**Date:** 2026-08-11  
**Status:** Audit complete → **implemented** (see `PHASE_F_CHECKOUT_PAYMENT_API_DOCUMENTATION.md`, `PAYMENT_CONFIGURATION.md`, `PHASE_F_IMPLEMENTATION_REPORT.md`)  
**Gateway:** Razorpay (already selected — do **not** introduce another gateway)

---

## Executive verdict

| Area | Status |
|------|--------|
| Checkout preview / place order | **Implemented** — server rebuilds totals from auth cart |
| Address snapshot on order | **Implemented** (`shipping_address_json`) |
| Shipping methods from API | **Implemented** (`GET /shipping/methods`) |
| COD | **Implemented** — confirm + commit stock at place |
| Razorpay server create + HMAC verify | **Implemented** — but **local stub** when keys empty |
| Webhook route | **Exists** — signature optional if secret empty |
| Real Razorpay Checkout SDK (Web) | **Missing** — web fabricates `local_*` verify |
| Real Razorpay SDK (Android) | **Missing** — online hidden / stays `pay=pending` |
| Cart clear only after successful pay | **Broken** — cart cleared at `placeOrder` before payment |
| Coupon usage limits / redemptions | **Not enforced** |
| Guest checkout | **Not supported** (auth required — keep) |
| Payment retry API | **Missing** |
| Pending-order timeout / stock release job | **Missing** |
| Order create idempotency | **Weak** (`request_id` stored, not deduped) |

**Do not rebuild checkout from scratch.** Standardize and harden the existing `CheckoutService` + `PaymentService` + Razorpay integration, then fix Website/Android clients to use real gateway SDKs and server-confirmed success only.

---

## 1. Existing checkout architecture

```
Authenticated cart
    ↓
POST /checkout/preview   → totals (no stock reserve)
    ↓
POST /orders             → PENDING_PAYMENT + inventory.reserve + CLEAR CART
    ├─ payment_method=cod      → commit stock + CONFIRMED
    └─ payment_method=razorpay → stay PENDING_PAYMENT
         ↓
POST /payments/initiate  → payments row + Razorpay order (or stub)
         ↓
Client pays (SDK — missing today)
         ↓
POST /payments/verify and/or webhook
         ↓
finalizeSuccess → commit stock + CONFIRMED
   or failPayment → release stock + PAYMENT_FAILED
```

**Key files**

| Layer | Path |
|-------|------|
| Preview | `app/Modules/Order/Http/Controllers/CheckoutController.php` |
| Place | `app/Modules/Order/Http/Controllers/OrderController.php` |
| Service | `app/Modules/Order/Services/CheckoutService.php` |
| Shipping | `app/Modules/Delivery/Http/Controllers/ShippingController.php` |
| Payment | `app/Modules/Payment/Services/PaymentService.php` |
| Gateway | `app/Integrations/Payment/RazorpayGateway.php` |

**Amount authority:** `grand_total` always from server `buildTotals()` — clients never submit trusted totals. Good.

---

## 2. Existing order architecture

- **Model:** `Order`, `OrderItem`, `OrderStatusHistory`
- **Order number:** `ORD-{Ymd}-{#####}` via `CheckoutService::nextOrderNumber()` (app-level sequence — concurrency race risk)
- **State machine:** `OrderStateMachine` — only legal transitions mutate status

**Statuses (relevant to Phase F)**

```
PENDING_PAYMENT → CONFIRMED | PAYMENT_FAILED | CANCELLED
PAYMENT_FAILED  → PENDING_PAYMENT | CANCELLED
CONFIRMED → PROCESSING → PACKED → SHIPPED → …
```

**Customer APIs:** `GET /orders`, `GET /orders/{id}`, cancel, returns — scoped by `user_id`.

---

## 3. Existing payment architecture

Single `payments` table (no separate transactions table).

| Field | Notes |
|-------|-------|
| `provider` | `razorpay` / `cod` |
| `status` | `pending` / `success` / `failed` |
| `amount` | From `order.grand_total` |
| `idempotency_key` | Unique per initiate (`pay_{uuid}`) |
| `provider_order_id` / `provider_payment_id` / `provider_signature` | Gateway refs |

Env: `PAYMENT_DRIVER`, `RAZORPAY_KEY`, `RAZORPAY_SECRET`, `RAZORPAY_WEBHOOK_SECRET`.

---

## 4. Existing Razorpay implementation

| Behavior | Detail |
|----------|--------|
| Create order | Real `POST https://api.razorpay.com/v1/orders` when keys set; else stub `order_local_*` |
| Verify | HMAC-SHA256 `orderId|paymentId` with secret; if secret empty accepts `local_*` |
| Webhook | `POST /payments/webhooks/{provider}`; secret check skipped when empty |
| Secrets | Server `.env` only — not in Flutter/JS source |
| Client public key | Returned in `client_payload.key` from initiate |

**Current local mode = stub/demo.** Production must refuse stub when `APP_ENV=production`.

---

## 5. Existing database schema

From `0001_01_01_000006_create_orders_engagement_tables.php` (+ commerce migration):

| Table | Phase F role |
|-------|----------------|
| `orders` | Totals, coupon_code, payment_method, address JSON snapshots |
| `order_items` | Line snapshots (name, sku, unit_price, qty) |
| `payments` | Gateway payment records |
| `shipping_methods` | Delivery options + price |
| `coupons` | Discount rules (usage limits columns exist) |
| `inventory_*` | Reserve / commit / release |

**Missing / drift**

- No `coupon_redemptions` / `coupon_usages` table
- `provider_payment_id` indexed but **not unique** (should be unique where not null for idempotency)
- `shipping_zones` unused by checkout pricing

**No destructive DROP required** for baseline Phase F. Prefer additive migration for coupon redemptions + unique payment id.

---

## 6. Cart integration

- Checkout uses **authenticated** active cart only (`CheckoutService::activeCart`)
- Guest `X-Cart-Token` cannot place orders (login required)
- Preview/place re-read cart lines and re-price
- **Critical bug vs Phase F rules:** cart items deleted inside `placeOrder` **before** Razorpay success (lines 146–149)

---

## 7. Coupon integration

- Cart coupon reused; preview/place revalidate date/status/min/max
- Order stores `coupon_code` string
- **Not enforced:** `usage_limit_total`, `usage_limit_per_user`, redemption ledger
- Failed payment does not increment usage (good — but success also doesn’t)

---

## 8. Address integration

- `address_id` must belong to user (`AddressService::owned`)
- Snapshot via `$address->toApiOrderArray()` / `toApiArray()` into `shipping_address_json` + `billing_address_json`
- Later address-book edits do **not** mutate historical orders — **correct**

---

## 9. Website checkout

**Files:** `apps/nursery-web/src/app/checkout/page.tsx`, `CheckoutStepper.tsx`, `lib/services.ts`

- Steps: Address → Delivery → Payment → Review
- Defaults to **COD**
- Online path: `payments/initiate` then **fabricates**  
  `provider_payment_id: local_…` and `provider_signature: local_…`
- Verify may omit correct `provider_order_id` from initiate payload shape
- **No Razorpay Checkout.js**
- Success = navigate to `/account/orders/{id}?placed=…` (banner even when payment may be pending)
- Weak double-submit protection

---

## 10. Android checkout

**Files:** `apps/nursery_app/lib/screens/checkout_screen.dart`, `core/config.dart`

- Same step model; `_submissionLocked` prevents double tap
- Online gated by `ONLINE_PAYMENTS_ENABLED` (default **false**)
- **No `razorpay_flutter` dependency**
- When online attempted without SDK fields → navigates with `pay=pending` (refuses fake success — better than web)
- Verify body incomplete vs API contract
- No retry-payment CTA on order detail

---

## 11. Order success flow

| Client | Surface |
|--------|---------|
| Website | Order detail with `?placed=` banner |
| Android | Order detail with `?placed=` / `pay=pending` |

No dedicated success route. Acceptable if order detail is loaded from **GET /orders/{id}`** after server confirm — but must not show “paid/confirmed” until API says so.

---

## 12. Payment verification

**Designed:** server HMAC + order ownership + amount from DB order.  
**Today:** empty secret accepts `local_*` → web can fake success in stub mode.  
**Rule for Phase F:** success UI only after `POST /payments/verify` (or status poll) returns confirmed paid order.

---

## 13. Payment failure handling

- `failPayment` → payment failed, stock released, order `PAYMENT_FAILED`
- Cart already empty (cleared at place) → **user loses cart** — violates Phase F
- No first-class retry endpoint to reopen `PENDING_PAYMENT` / re-initiate

---

## 14. Webhook

- Route: `POST /api/v1/payments/webhooks/razorpay`
- Events: `payment.captured`, `order.paid`, `payment.failed`
- Gaps: optional signature, limited idempotency, lookup by `provider_order_id` only

---

## 15. Stock handling

| Event | Inventory |
|-------|-----------|
| Place order | `assertAvailable` + `reserve` (`lockForUpdate`) |
| COD / pay success | `commit` |
| Pay fail / cancel unpaid | `release` |

Model is sound (**reserve at place, commit after pay**). Do **not** invent a second reservation system. Fix cart timing + add timeout for abandoned pending orders.

---

## 16. Transaction handling

- `placeOrder`, `initiate`, `finalizeSuccess`, `failPayment` use `DB::transaction`
- Payment initiate has unique `idempotency_key`
- `POST /orders` lacks request-level idempotency → double-tap can create two orders

---

## 17. Security issues

| Issue | Severity |
|-------|----------|
| Stub verify (`local_*`) when secret empty | **Critical** if shipped to prod |
| Webhook signature optional when secret empty | **Critical** in prod |
| Website fabricates verify payload | **High** |
| Cart cleared before payment success | **High** (UX + recovery) |
| No order-create idempotency | **High** |
| Coupon limits not enforced | **Medium** |
| Order number race | **Medium** |
| Amount from server | OK |
| Secrets not in clients | OK |
| Order IDOR mitigated by `user_id` | OK |

---

## 18. Inconsistencies

| Topic | API | Website | Android |
|-------|-----|---------|---------|
| Online payment | Supported | Fake local verify | Flag-gated, no SDK |
| Default method | client chooses | COD | COD |
| Cart after failed pay | emptied | emptied | emptied |
| Success criteria | verify/webhook | Optimistic navigate | Distinguishes pending |
| Guest checkout | No | No | No |

---

## 19. Required changes (minimum for Phase F Done)

### Backend
1. **Defer cart clear** until COD confirm or Razorpay `finalizeSuccess`; on payment fail keep cart intact (or restore from order lines if already cleared historically — prefer defer)
2. Prevent double place while cart has open `PENDING_PAYMENT` for same user **or** enforce `Idempotency-Key` / `X-Request-Id` on `POST /orders`
3. Production gate: refuse Razorpay stub when `APP_ENV=production` or keys missing
4. Require webhook secret in production; reject unsigned webhooks
5. Unique index on `payments.provider_payment_id` (nullable unique)
6. Coupon redemption table + increment only on successful confirm
7. Payment retry: re-initiate for `PENDING_PAYMENT` / reopen from `PAYMENT_FAILED` with re-reserve
8. Optional: payment status `GET /payments/{id}` or order poll for network recovery
9. Align free-delivery shipping with Phase E threshold inside `buildTotals` (shipping method price → 0 when qualifies)
10. Expose payment methods / online enabled via `GET /app/config` or dedicated endpoint

### Website
1. Load Razorpay Checkout.js with `client_payload`
2. Remove `local_*` fabricate path
3. Call verify with real `razorpay_order_id`, `razorpay_payment_id`, `razorpay_signature`
4. Success only after verify returns confirmed order
5. Failure/cancel UX + retry; cart preserved
6. Desktop two-column checkout polish (summary sticky)

### Android
1. Add official Razorpay Flutter plugin
2. Enable online when config says so
3. Open checkout → verify → success
4. Pending / fail / cancel / retry UX on order detail
5. Never show confirmed from SDK callback alone

### Docs / SQL / config
- `PHASE_F_CHECKOUT_PAYMENT_API_DOCUMENTATION.md`
- `PAYMENT_CONFIGURATION.md` (placeholders only)
- `database/phase_f_checkout_payment.sql`
- Smoke + sandbox test notes

---

## 20. Risks

- Stuck inventory reservations from abandoned online checkouts
- Fake paid orders if stub keys reach production
- Duplicate orders without idempotency
- Coupon abuse without redemptions
- Webhook spoofing with empty secret
- Changing cart-clear timing mid-flight needs careful migration of in-progress pending orders

---

## 21. Recommended implementation order

1. Write this audit ✅  
2. Fix **cart clear timing** + order idempotency + prod Razorpay gate + webhook secret enforcement  
3. Coupon redemptions + unique `provider_payment_id` (SQL/migration)  
4. Free-delivery shipping alignment in checkout totals  
5. Payment retry + status recovery endpoints  
6. API smoke tests (COD + stub Razorpay + fail paths)  
7. Website Razorpay Checkout.js + success/fail UX  
8. Android Razorpay SDK + success/fail UX  
9. Sandbox E2E with real test keys (when configured)  
10. Docs + configuration guide + final report  
11. Phase B/C/E/order regression  

---

## Final API contract (reuse — do not duplicate)

| Method | Path | Auth | Role |
|--------|------|------|------|
| GET | `/cart` | optional JWT | Cart review |
| GET | `/customer/addresses` | Bearer | Address book |
| GET | `/shipping/methods` | optional JWT | Delivery options |
| POST | `/checkout/preview` | Bearer | Server totals |
| POST | `/orders` | Bearer | Create pending/COD order |
| POST | `/payments/initiate` | Bearer | Create gateway order |
| POST | `/payments/verify` | Bearer | Server verify + confirm |
| POST | `/payments/webhooks/{provider}` | Signature | Authoritative events |
| GET | `/orders` / `/orders/{id}` | Bearer | List/detail |
| GET | `/app/config` | public | Feature flags / commerce |

**Proposed additions (only if needed after harden):**

| Method | Path | Role |
|--------|------|------|
| POST | `/orders/{id}/retry-payment` | Re-initiate after fail/pending |
| GET | `/payments/{id}` | Status recovery |

**Do not add** separate website/android payment APIs or a second gateway.

---

## Guest checkout decision

**Keep auth-required checkout.** Guest cart (Phase E) merges on login; Phase B addresses require user. Adding guest checkout is out of scope unless product explicitly demands it later.

---

## Sandbox / production note

- Dev/test: Razorpay **test** keys + test cards/UPI  
- Production: live keys + webhook URL + secrets only in server env  
- Never commit real credentials  
- Phase F cannot be marked complete without at least one successful **sandbox** payment when keys are available; without keys, document limitation and keep stub **dev-only** with hard prod refusal
