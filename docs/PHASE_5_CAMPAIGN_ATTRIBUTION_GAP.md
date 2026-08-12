# PHASE 5 — Campaign Attribution Gap

**Status:** Unsupported in current schema.

## Finding

- Campaigns attach products via `campaign_products`.
- Orders store `coupon_code` and link to `coupon_redemptions`.
- There is **no** `orders.campaign_id` (or equivalent attribution event).

## What we will NOT display

- Campaign views / clicks / attributed orders / attributed revenue

## What we CAN display (related, not campaign attribution)

Coupon performance from `coupon_redemptions` + order totals where `orders.coupon_code` is set — labeled clearly as **coupon**, not campaign.

## Recommended future design

1. Add optional `orders.campaign_id` (nullable FK) set at checkout when a campaign session/context is present.
2. Or store attribution events: `campaign_id`, `user_id`, `session_id`, `order_id`, `attributed_at`.
3. Define rule: order attributed only if campaign context was active within N hours before payment success.
4. Admin analytics then aggregates attributed orders/revenue.

Until then, `/admin/analytics/campaigns` returns an explicit unsupported payload (not zeros that look like data).
