# PHASE 5 — Metric Definitions

**Timezone:** `config('app.timezone')` default **Asia/Kolkata** (`APP_TIMEZONE`)  
**Currency:** INR (order `currency` field; Admin formats via `formatMoney`)  
**Date interval:** inclusive calendar days `[startOfDay(from), endOfDay(to)]`  
**Rounding:** money 2 decimals; percentages 1 decimal; rates 2 decimals  

**Revenue-qualifying orders:** `status NOT IN ('CANCELLED', 'PAYMENT_FAILED')`

---

## Revenue

| Field | Value |
|-------|-------|
| Definition | Sum of order grand totals for revenue-qualifying orders in range |
| Formula | `SUM(orders.grand_total)` |
| Source | `orders` |
| API | `/admin/analytics/overview`, `/admin/analytics/sales` |
| Limitations | Not “profit”. Includes tax/shipping as stored on order. |

## Orders (qualifying)

| Field | Value |
|-------|-------|
| Definition | Count of revenue-qualifying orders in range |
| Formula | `COUNT(*)` where not cancelled/payment_failed |
| Source | `orders` |

## Average Order Value (AOV)

| Field | Value |
|-------|-------|
| Definition | Average grand total per revenue-qualifying order |
| Formula | `revenue / qualifying_orders` (0 if no orders) |
| Source | derived |

## Units sold

| Field | Value |
|-------|-------|
| Definition | Sum of line quantities on revenue-qualifying orders |
| Formula | `SUM(order_items.quantity)` via order filter |
| Source | `order_items` + `orders` |

## Discount / Shipping / Tax totals

| Field | Value |
|-------|-------|
| Definition | Sum of respective order columns for revenue-qualifying orders |
| Formula | `SUM(discount_total)`, `SUM(shipping_total)`, `SUM(tax_total)` |
| Source | `orders` |

## Period change %

| Field | Value |
|-------|-------|
| Definition | Change vs immediately previous period of equal length |
| Formula | `((current - previous) / previous) * 100` |
| Edge case | If previous = 0 → API returns `null` (UI shows n/a). Never Infinity/NaN. |

## Delivered / Cancelled orders

| Field | Value |
|-------|-------|
| Definition | Count by exact status in range (`created_at`) |
| Source | `orders.status` |

## Refund amount

| Field | Value |
|-------|-------|
| Definition | Sum of refund row amounts in range |
| Formula | `SUM(refunds.amount)` by `refunds.created_at` |
| Limitations | Reflects recorded refunds; PSP settlement may differ |

## Return requests

| Field | Value |
|-------|-------|
| Definition | Count of `return_requests` in range by status |
| Source | `return_requests` |

## Product performance

| Field | Value |
|-------|-------|
| Definition | Aggregated units/revenue/orders per `product_id` from order items on qualifying orders |
| Stock column | Current sellable from `inventory_items` (not historical) |

## Category performance

| Field | Value |
|-------|-------|
| Definition | Same as products, grouped via `product_categories` → `categories` |
| Note | Products in multiple categories contribute to each |

## Customer metrics

| Metric | Definition |
|--------|------------|
| Total customers | Users with role `customer` |
| New customers | Customer users with `created_at` in range |
| Buyers in period | Distinct `user_id` with ≥1 qualifying order in range |
| Returning buyers in period | Buyers with ≥2 qualifying orders in range |
| Lifetime returning | Customers with ≥2 qualifying orders lifetime |
| High value in period | Top 10 by spend in range |

## Inventory stock health

| Status | Rule |
|--------|------|
| out_of_stock | sellable ≤ 0 |
| critical | sellable > 0 and ≤ max(1, floor(threshold/2)) when threshold > 0 |
| low_stock | sellable ≤ `low_stock_threshold` and not critical/out |
| healthy | otherwise |
| sellable | `qty_on_hand - qty_reserved - qty_damaged` (≥ 0) |

## Campaign revenue

**Not defined** — attribution unsupported. See `PHASE_5_CAMPAIGN_ATTRIBUTION_GAP.md`.

## Profit / margin

**Not defined** — no sell-side COGS on products/order_items.

## Coupon performance

Redemptions from `coupon_redemptions`; order revenue/discount where `orders.coupon_code` matches. **Not** campaign attribution.
