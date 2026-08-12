# PHASE 17 — CRM / Marketing Design

**Date:** 2026-08-12

---

## Architecture

```
Admin / Web / Android
        ↓ JSON REST
Laravel Marketing module (segments, automations, 360)
        ↓
Existing: Orders, Cart, Coupons, Campaigns, Notifications, Preferences
        ↓
MySQL
```

Business rules stay in Laravel. Frontends only display and trigger admin actions.

---

## Customer 360

Read-only aggregation (`Customer360Service`):

- Profile + **derived** `lifecycle_stage` (does not mutate `users.status`)
- Order summary (excludes `CANCELLED`, `PAYMENT_FAILED`)
- Activity: wishlist count, reviews, recent product view IDs, open cart
- Loyalty + subscriptions snapshots
- Marketing preferences + recent coupon redemptions

Lifecycle (analytical): `registered` → `first_purchase` → `repeat` → `loyal` (≥5 orders) → `inactive` (≥ config days since last order).

---

## Segment rule model

JSON shape:

```json
{ "all": [ { "field": "order_count", "op": "gte", "value": 2 } ] }
```

**Allowed fields:** `order_count`, `total_spent`, `last_order_days`, `registered_days`, `inactive_days`, `has_subscription`, `loyalty_balance`, `marketing_opt_in`, `has_wishlist`, `has_open_cart`, `cart_inactive_hours`

**Ops:** `eq`, `neq`, `gt`, `gte`, `lt`, `lte`

Evaluation is server-side SQL. Members are paginated — never dump the full audience to the browser.

### Documented system segment criteria

| Key | Criteria |
|-----|----------|
| `new_customers` | `registered_days ≤ 30` AND `order_count = 0` |
| `returning_customers` | `order_count ≥ 2` |
| `high_value` | `total_spent ≥ 5000` |
| `inactive_90d` | `order_count ≥ 1` AND `inactive_days ≥ 90` |
| `cart_abandoners` | `has_open_cart` AND `cart_inactive_hours ≥ config` |
| `subscription_customers` | `has_subscription = true` |
| `loyalty_members` | `loyalty_balance ≥ 1` |
| `wishlist_users` | `has_wishlist = true` |
| `marketing_opted_in` | `marketing_opt_in = true` |

---

## Marketing automations vs catalog campaigns

| | Catalog `campaigns` | `marketing_automations` |
|--|---------------------|-------------------------|
| Purpose | Merchandising / seasonal storefront | CRM journeys + blasts |
| Status | draft / active / inactive | draft / active / paused / archived |
| Targeting | Products/categories/banners | Segments + eligibility |
| Delivery | Storefront surfaces | NotificationService marketing |

Transitions validated in `MarketingAutomationService::transition`.

---

## Abandoned cart eligibility

Configured in `config/marketing.php` (env overrides):

| Rule | Default |
|------|---------|
| Inactivity before abandoned | 24 hours |
| Max recovery messages | 2 |
| Min cart subtotal | 0 |
| Cooldown between messages | 48 hours |

**Stop when:** purchase occurs, cart cleared/inactive, opt-out, frequency cap, automation not active, message max reached.

Copy must not invent scarcity unless inventory APIs assert it.

---

## Journeys

| Journey | Trigger | Default status after seed |
|---------|---------|---------------------------|
| Welcome | Registration (best-effort) | active |
| Abandoned cart | Scheduler hourly | draft (activate intentionally) |
| Post-purchase review | Scheduler daily after delivery + N days | draft |
| Reactivation | Manual blast / segment dispatch | draft (needs segment) |

---

## Channels & preferences

- Channels: `in_app`, `email`, `push` (via existing NotificationService)
- Marketing category requires `marketing_opt_in` AND `notify_promotions`
- Transactional notifications are **not** disabled by marketing opt-out
- SMS: not supported

---

## Frequency, idempotency, queue

- Max marketing messages / day (default 2)
- Delivery `idempotency_key` unique — retries do not double-send
- Abandoned cart / post-purchase via Artisan + Laravel schedule
- Manual dispatch batches (`MARKETING_BATCH_SIZE`, default 100)

---

## Attribution

- Optional `campaign_id` on checkout → `orders.campaign_id`
- Optional `coupons.campaign_id`
- Dashboard attribution counts **only** orders with non-null `campaign_id`
- Opens/clicks: not claimed unless provider events exist

---

## Privacy & RBAC

Permissions: `customers.view`, `customers.segment`, `marketing.view`, `marketing.manage`, `marketing.launch`  
Customer 360 also allowed with `users.manage` (OR middleware).

Audit events: segment create/update/archive; automation create/update/activate/pause/dispatch.
