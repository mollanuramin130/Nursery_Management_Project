# PHASE 10 — Subscriptions Audit

## 1. What already exists

| Area | Status |
|------|--------|
| Subscriptions / plans / cycles | **None** |
| Recurring Razorpay / mandates / tokens | **None** |
| Saved payment methods | **None** |
| Orders / checkout / payments | Full one-time cart checkout |
| Inventory reserve/commit | Reusable |
| Fulfillment / shipping / returns / refunds | Reusable via normal orders |
| Loyalty earn on delivery | Reusable (order-based) |
| Scheduler | Laravel `Schedule` in `routes/console.php` (inventory release only) |
| Queue | Laravel queue available; not required for MVP sync processor |

## 2. Payment provider capabilities

`RazorpayGateway` supports only:

- Create Razorpay **order** (one-shot)
- Verify payment signature
- Local stub when keys absent (non-production)

**Does NOT support in this codebase:** recurring billing, mandates, customer tokens, auto-debit.

## 3. Recurring payments supported?

**NO.** Phase 10 must **not** fake automatic charges.

### Adopted model

```
Subscription ACTIVE
      ↓
Cycle due (scheduler)
      ↓
Create normal Order (PENDING_PAYMENT)
      ↓
Notify customer: payment required
      ↓
Customer pays via existing PaymentService initiate/verify
      ↓
Order CONFIRMED → fulfillment → delivery → loyalty
      ↓
Next cycle date advanced
```

If payment not completed within policy window → `PAYMENT_FAILED` / retry window → cancel after max failed cycles (configurable).

## 4. Existing DB structures

- No subscription tables
- `orders.meta` JSON available for soft link; Phase 10 adds nullable `subscription_id` / `subscription_cycle_id` FKs for integrity
- Products: `status` draft|active|archived; `meta` JSON

## 5. Reuse

- `CheckoutService` patterns (totals, inventory, status history) via **new** `placeSubscriptionOrder` (no cart)
- `PaymentService` initiate/verify/retry
- `InventoryService`, `FulfillmentService`, returns/refunds, loyalty hooks
- `NotificationService`, `AuditLogger`, RBAC
- Admin/Web/Android account patterns from Phase 9

## 6. Missing (to implement)

- Plans + subscriptions + cycles + events schema
- Customer + admin APIs
- Cycle processor command + schedule
- Admin plans + subscriptions UI
- Website PDP Subscribe & Save + account pages
- Android subscribe + my subscriptions
- Permissions `subscriptions.view|manage`

## 7–11. Required surfaces

| Layer | Work |
|-------|------|
| DB | `subscription_plans`, `subscriptions`, `subscription_cycles`, `subscription_events`; order FK columns |
| API | Customer CRUD lifecycle; admin plans/subscriptions; process due |
| Admin | `/subscription-plans`, `/subscriptions`, `/subscriptions/[id]` |
| Website | PDP subscribe, `/account/subscriptions`, detail actions |
| Android | Subscribe flow, list/detail, pause/resume/cancel |

## 12. Risks

- Customers may ignore pay-per-cycle invoices → churn/`PAYMENT_FAILED`
- Seasonal stock → skip cycle (no silent substitute)
- Concurrent scheduler → need row locks + unique `(subscription_id, cycle_number)`
- Pending unpaid subscription order blocks other checkouts (existing `PENDING_ORDER_EXISTS` rule) — document; processor should avoid stacking unpaid cycles

## 13. Payment-provider limitations

Documented permanently: **no auto-debit until Razorpay mandates/tokens are integrated in a future payments phase.**

## Price rule (chosen)

**Lock unit price** on subscription at creation from plan. Plan price edits affect **new** subscriptions only. Historical cycles/orders keep charged amounts.

## Favorites / cart

Subscription create is **direct** (plan → subscription), not silent cart conversion. Cart remains one-time unless a future “subscribe from cart” is added.
