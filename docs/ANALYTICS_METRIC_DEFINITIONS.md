# Analytics Metric Definitions

**Platform:** GreenLeaf Nursery  
**Phase:** 15 (extends Phase 5 definitions)  
**Timezone:** `config('app.timezone')` (default **Asia/Kolkata** via `APP_TIMEZONE`)  
**Currency:** Order `currency` field; Admin UI uses `formatMoney` — do not hardcode ₹ in API responses.  
**Date interval:** Inclusive calendar days `[startOfDay(from), endOfDay(to)]`  
**Rounding:** Money 2 decimals; percentage change 1 decimal in UI; rates 2–4 decimals in API  

**Canonical service:** `App\Modules\Admin\Services\AnalyticsService`  
**Related:** `docs/PHASE_5_METRIC_DEFINITIONS.md` (superseded where this document is more specific)

---

## Revenue-qualifying orders

Orders where `status NOT IN ('CANCELLED', 'PAYMENT_FAILED')`.

All “revenue”, “AOV”, “units sold”, and buyer spend metrics use this set unless stated otherwise.

---

## Core commerce metrics

| Metric | Precise definition | Formula / source | Not this |
|--------|-------------------|------------------|----------|
| **Gross sales (Revenue)** | Sum of stored order grand totals for revenue-qualifying orders in range | `SUM(orders.grand_total)` | Not profit; not net of refunds; includes tax/shipping as stored |
| **Net sales (refund-aware)** | **Not implemented as a primary KPI** | Would require `gross − refunds` with clear refund timing rules | Do not label “Revenue” as net |
| **Orders (qualifying)** | Count of revenue-qualifying orders in range | `COUNT(*)` | Excludes cancelled / payment_failed |
| **Total orders** | All orders created in range | Includes cancelled / payment_failed | |
| **Average order value (AOV)** | Mean grand total per qualifying order | `revenue / qualifying_orders` (0 if none) | |
| **Units sold** | Line quantities on qualifying orders | `SUM(order_items.quantity)` | |
| **Discount total** | Sum of order discounts | `SUM(orders.discount_total)` on qualifying orders | Not coupon profitability |
| **Shipping total** | Sum of shipping charges | `SUM(orders.shipping_total)` | |
| **Tax total** | Sum of tax | `SUM(orders.tax_total)` | |
| **Refund amount** | Sum of recorded refund rows in range | `SUM(refunds.amount)` by `refunds.created_at` | May differ from PSP settlement |

---

## Period comparison

| Field | Definition |
|-------|------------|
| Previous period | Immediately preceding window of equal length to the selected range |
| Change % | `((current − previous) / previous) * 100` |
| Zero previous | API returns `null`; UI shows **n/a** — never Infinity/NaN |

---

## Order health

| Metric | Definition |
|--------|------------|
| Status distribution | `COUNT(*)` grouped by `orders.status` in range (`created_at`) |
| Delivered | `status = DELIVERED` |
| Cancelled | `status = CANCELLED` |
| Returned path | `status IN (RETURN_REQUESTED, RETURNED, REFUNDED)` |
| Open pipeline | Pending payment through out-for-delivery statuses (see API) |
| Cancellation rate | Cancelled / total orders in range (compute in UI if needed; not always precomputed) |

Operational dashboard KPIs (`pending_payment`, `orders_to_ship`, etc.) are **live snapshots**, not date-range metrics.

---

## Payment health

| Metric | Definition |
|--------|------------|
| Payment attempts | `COUNT(payments)` with `created_at` in range |
| Successful | `payments.status = 'success'` |
| Failed | `payments.status = 'failed'` |
| Pending | `payments.status = 'pending'` |
| Success rate | `success / attempts` (`null` if attempts = 0) |
| By method / provider | Grouped counts/amounts from `payments` |

Refund amount on payment analytics still comes from the **`refunds`** table (not payment status = refunded unless that status exists in data).

---

## Conversion & funnel (partial)

| Stage | Status | Source |
|-------|--------|--------|
| Product view | **MISSING** | No event store |
| Add to cart | **MISSING** | Carts are transactional, not funnel events |
| Checkout start | **MISSING** | No event |
| Orders created | **EXISTS** | `orders` |
| Payment rows | **EXISTS** | `payments` |
| Payment success | **EXISTS** | `payments.status = success` |
| Order past pending | **EXISTS** | Order status not pending/failed/cancelled |
| Delivered | **EXISTS** | `orders.status = DELIVERED` |

**Cart abandonment rate:** **NOT DEFINED** — requires checkout-start and cart-session events.

**Checkout abandonment:** **NOT DEFINED** — same gap.

---

## Customer metrics

| Metric | Definition |
|--------|------------|
| Total customers | Users with role `customer` |
| New customers | Customer users with `created_at` in range |
| Buyers in period | Distinct `user_id` with ≥1 qualifying order in range |
| Returning buyers (period) | Buyers with ≥2 qualifying orders in range |
| Lifetime returning | Customers with ≥2 qualifying orders lifetime |
| Orders per buyer | Qualifying orders in range / buyers in period |
| Average buyer spend | Sum of qualifying grand totals in range / buyers |
| High value (period) | Top 10 by spend in range |

### Customer lifetime value (LTV)

**Not published as a primary KPI in Phase 15.**

If introduced later, document an explicit formula, e.g.:

> Historical LTV = `SUM(grand_total)` of all revenue-qualifying orders for the customer (lifetime), optionally minus lifetime refunds attributed to that customer.

Do **not** invent predicted LTV or arbitrary multipliers.

### Cohorts

Acquisition cohort = calendar month of the customer’s **first** revenue-qualifying order. Phase 15 returns cohort sizes in range only — **not** a full M1/M2/M3 retention matrix.

---

## Product / category / plant

| Metric | Definition |
|--------|------------|
| Product revenue / units / orders | Aggregated from `order_items` on qualifying orders |
| Stock on product reports | **Current** sellable from `inventory_items` (not historical) |
| Category performance | Via `product_categories` → `categories` (multi-category products count in each) |
| Plant taxonomy sales | Qualifying order items joined to `plant_profiles` (`indoor_outdoor`, `plant_kind`, `difficulty_level`, `pet_safety`, `sunlight`) |
| Products without plant_profiles | Omitted from plant taxonomy breakdowns |

**Add-to-cart rate / purchase rate from views:** **NOT DEFINED** (no views).

---

## Inventory + demand attention

| Metric | Definition |
|--------|------------|
| Sellable | `max(0, qty_on_hand − qty_reserved − qty_damaged)` |
| out_of_stock / critical / low_stock / healthy | See Phase 5 inventory rules |
| HIGH_DEMAND_LOW_STOCK | Top sellers by units in range whose **current** sellable ≤ `low_stock_threshold` |

Advisory only — does **not** create purchase orders.

**Sales velocity / reorder PO automation:** **NOT DEFINED** without supplier lead-time and reorder-point model completeness.

---

## Search

| Metric | Definition |
|--------|------------|
| Search event | Server insert into `search_events` when catalog search `q` length ≥ 2 |
| Zero-result search | `results_count = 0` |
| Zero-result rate | zero / searches |
| Search → purchase conversion | **NOT DEFINED** (no session join) |

Logging is best-effort and must not fail the catalog response.

---

## Reviews / returns / coupons / campaigns

| Area | Definition notes |
|------|------------------|
| Reviews | Count, avg rating, distribution, low-rated products (≥2 reviews, avg ≤ 3) in range |
| Returns | `return_requests` counts/trends; reasons grouped from actual DB values when present |
| Coupons | Redemptions + order discount/revenue where `orders.coupon_code` matches — **not** campaign attribution |
| Campaign revenue | **NOT DEFINED** — see `docs/PHASE_5_CAMPAIGN_ATTRIBUTION_GAP.md` |
| Seasonal commercial periods | **NOT DEFINED** — monthly revenue proxy only |

---

## Loyalty / subscriptions / notifications

| Area | Phase 15 status |
|------|-----------------|
| Loyalty points earned/redeemed analytics | **MISSING** as dedicated analytics endpoints (ledger exists operationally) |
| Subscription MRR / churn analytics | **MISSING** as dedicated analytics endpoints |
| Notification delivery/open analytics | **PARTIAL / provider-dependent** — not centralized in Admin analytics |

Do not invent rates from incomplete notification provider payloads.

---

## Permissions

| Permission | Gate |
|------------|------|
| `reports.view` | All `/api/v1/admin/analytics/*` read endpoints |
| `reports.export` | CSV export |

Backend middleware is authoritative; frontend checks are UX only.
