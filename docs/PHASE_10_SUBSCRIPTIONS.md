# PHASE 10 — Subscriptions & Recurring Commerce

## 1. Subscription architecture

```
Plan (catalog offer)
   ↓
Subscription (customer agreement, locked unit price)
   ↓
Cycle (idempotent subscription_id + cycle_number)
   ↓
Order (existing orders table + subscription_id / subscription_cycle_id)
   ↓
Payment → Fulfillment → Shipping → Delivery → Loyalty
```

Subscriptions are **not** a second order system.

## 2. Plan architecture

Table `subscription_plans` links a product to frequency + subscription unit price.
Frequencies: `WEEKLY`, `BIWEEKLY`, `MONTHLY`, `QUARTERLY`, `YEARLY`.
Status: `draft` | `active` | `archived`.

## 3. Lifecycle

`PENDING` → (first cycle paid/COD) → `ACTIVE` ↔ `PAUSED` → `CANCELLED`  
Also: `PAYMENT_FAILED`, `COMPLETED` (max cycles), `EXPIRED` (reserved).

## 4. Cycle generation

Command: `subscriptions:process-due` (scheduled every 15 minutes, `withoutOverlapping`).

Unique constraint on `(subscription_id, cycle_number)` prevents duplicates.

## 5. Payment (critical)

**Razorpay in this codebase does not support mandates/tokens/recurring debit.**

Billing model: **pay_per_cycle**

- Each cycle creates `PENDING_PAYMENT` order
- Customer pays via existing `PaymentService` initiate/verify
- **No fake auto-charge**
- No raw card/CVV storage

COD cycles confirm immediately like one-time COD.

## 6. Order generation

`CheckoutService::placeSubscriptionOrder` creates normal orders (inventory reserve/commit, status history). Cart is not used.

## 7. Inventory

Availability checked before each cycle. If unavailable: cycle `SKIPPED`, customer notified, **no silent substitute**.

## 8–11. Fulfillment / shipping / returns / refunds

Reuse Phase 7–8 pipelines on the generated order. Cancelling a subscription does **not** cancel existing fulfilled orders.

## 12. Loyalty

Subscription orders earn points via existing Phase 9 delivery hook (once per order).

## 13. Notifications

`subscription_created`, `subscription_paused/resumed/cancelled`, `subscription_cycle_created`, `subscription_payment_*`, `subscription_unavailable`.

## 14. RBAC

`subscriptions.view`, `subscriptions.manage`

## 15. Audit logging

`subscription.create|pause|resume|cancel|quantity`, `subscription_plan.create|update`

## 16–17. Concurrency / idempotency

Row locks on process; unique cycle key; open unpaid cycle skips new generation; unpaid customer order blocks stacking.

## 18. Security

Customer ownership enforced; admin permissions required; payment secrets stay server-side.

## 19. Testing

`tests/Feature/Phase10SubscriptionsTest.php`

## 20. Production considerations

- Run scheduler (`subscriptions:process-due`)
- Configure plans carefully for seasonal stock
- Do not market as “auto-billed” until Razorpay mandates exist
- Price rule: **unit price locked** at subscription create; plan edits affect new subscriptions only
