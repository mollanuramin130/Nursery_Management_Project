# PHASE 5 — Analytics Audit

**Date:** 2026-08-11  
**Status:** Audit complete; implementation follows.

## Existing capability

| Capability | Status | Notes |
|------------|--------|-------|
| `GET /admin/dashboard` | Exists | Today KPIs + last 7 days sales; no date filter |
| `GET /admin/reports/{sales\|inventory\|top_products}` | Exists | Date range for sales/top_products; Admin UI not wired |
| Permission `reports.view` | Exists | Used for dashboard + reports |
| Chart library in Admin | **None** | Use tables + CSS bar sparklines |
| Campaign → order attribution | **Missing** | No `orders.campaign_id` |
| Product COGS / profit | **Missing** | No sell-side cost fields |
| Async export jobs | **Missing** | Sync CSV only for bounded exports |
| Inventory thresholds | Exists | `inventory_items.low_stock_threshold` |
| Stock movements | Exists | `stock_movements` table |
| Timezone | `Asia/Kolkata` | `config/app.php` via `APP_TIMEZONE` |

## Implementation approach

1. Keep existing dashboard KPIs (today / ops).
2. Add `GET /admin/analytics/*` aggregated endpoints (authoritative).
3. Gate with `reports.view`; add `reports.export` for CSV.
4. Wire Admin Analytics nav + pages; no fake metrics.
5. Document campaign attribution + profit gaps.

## Non-goals

Fake campaign revenue, estimated profit, inventing seasons, client-side revenue math, invasive customer tracking, queue-based mega-exports without jobs infrastructure.
