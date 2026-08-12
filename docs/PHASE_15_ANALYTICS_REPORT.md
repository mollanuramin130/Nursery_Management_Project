# PHASE 15 — Analytics, BI & Optimization Report

**Platform:** GreenLeaf Nursery  
**Date:** 2026-08-12  
**Scope:** Post-launch analytics on existing Laravel Admin APIs + Admin web. No platform rebuild. No fabricated metrics.

---

## Final status

**ANALYTICS PARTIALLY READY — DATA GAPS EXIST**

Transactional commerce analytics (orders, revenue, payments, products, inventory, returns, coupons, search logging, plant taxonomy sales) are implemented and gated. Behavioral funnel stages, campaign attribution, commercial seasons, LTV prediction, loyalty/subscription BI endpoints, and notification open analytics remain documented gaps.

---

## 1. Existing analytics audit

| Capability | Status | Notes |
|------------|--------|-------|
| Page views | **MISSING** | |
| Product views | **MISSING** | |
| Search / results / zero-result | **EXISTS** | Phase 15 `search_events` |
| Add/remove cart events | **MISSING** | Carts operational only |
| Wishlist analytics API | **MISSING** | Wishlist commerce exists |
| Checkout start | **MISSING** | |
| Payment success/fail | **EXISTS** | `payments` + Phase 15 payments report |
| Order / cancel / return / refund | **EXISTS** | |
| Review analytics | **EXISTS** | Phase 15 endpoint |
| Coupon usage | **EXISTS** | Phase 5 |
| Campaign interaction → revenue | **MISSING** | Attribution gap unchanged |
| Subscription BI | **MISSING** | Module exists; no analytics API |
| Loyalty BI | **MISSING** | Ledger exists; no analytics API |
| Phase 5 `/admin/analytics/*` | **EXISTS** | Extended, not replaced |
| Admin dashboard today KPIs | **EXISTS** | Links to analytics |
| Client-side full-order download math | **AVOIDED** | Server aggregates |

---

## 2. Metrics implemented

Documented in `docs/ANALYTICS_METRIC_DEFINITIONS.md`.

Includes: gross revenue (grand_total excl. CANCELLED/PAYMENT_FAILED), orders, AOV, units, discounts/shipping/tax, period comparison with null-safe %, order status health, payment health, product/category performance, customer new/returning/buyers, inventory health, coupon rows, returns, plant taxonomy sales, search volume/zero-results, review distribution, acquisition cohorts (size), HIGH_DEMAND_LOW_STOCK attention, partial transactional funnel.

---

## 3. Data gaps

See `docs/ANALYTICS_DATA_QUALITY.md` and overview `data_gaps` payload.

Critical product gaps: product_view, cart/checkout events, campaign attribution, commercial seasons, search→purchase conversion, predicted LTV, loyalty/subscription analytics APIs, notification engagement.

---

## 4. Database changes

| Change | Detail |
|--------|--------|
| Migration | `2026_08_12_030000_phase15_search_events.php` |
| Table | `search_events` (query, normalized_query, results_count, nullable user_id, platform, created_at + indexes) |
| Model | `App\Modules\Catalog\Models\SearchEvent` |
| Writer | `SearchEventRecorder` (best-effort; never fails catalog) |
| Call sites | `SearchController`, `ProductController@index` when `q` present |

No financial table mutations. No order/payment schema changes for analytics.

---

## 5. API changes

All under `GET /api/v1/admin/analytics/*`, middleware `permission:reports.view` (export: `reports.export`).

**New / extended:**

| Endpoint | Purpose |
|----------|---------|
| `.../payments` | Payment attempts, success/fail/pending, by method/provider, trend |
| `.../plants` | Sales by plant_profiles taxonomy |
| `.../reviews` | Rating distribution, low-rated products |
| `.../search` | Top / zero-result searches |
| `.../cohorts` | Acquisition cohorts by first-order month |
| `.../attention` | High demand + low stock |
| `.../overview` | Adds `payment_health`, `attention`, `data_gaps` |
| `.../orders` | Funnel marked `partial` with missing stages listed |
| `.../export` | Adds `payments`, `search` types |

Envelope, auth, and RBAC unchanged from existing Admin API patterns.

---

## 6. Admin dashboard / analytics UI

- `/dashboard` — today ops KPIs + link to analytics.  
- `/analytics` — sectioned overview (business, payment health, ops, trends, attention, data gaps).  
- New pages: payments, plants, search, reviews, attention, cohorts.  
- Existing: sales, orders, products, categories, customers, inventory, returns, seasonal, campaigns, coupons.  
- Shared: date presets + custom range, period comparison where API provides it, CSV export when permitted.  
- Routes use `/analytics/*` (not `/admin/reports/sales` path alias); operational Reports page remains at `/reports`.

---

## 7. Customer analytics changes

**None shipped.** Internal business metrics are Admin-only. Customer loyalty progress / order insights left for a future phase when definitions are approved.

---

## 8. Android analytics changes

**No new client SDK tracking.** When the app calls `/api/v1/search` or products with `q`, server-side search events are recorded. Sensitive fields are not added. Privacy: no passwords/tokens/payment credentials in analytics events.

---

## 9. Performance results

- Aggregations remain server-side SQL with date filters.  
- Search logging is O(1) insert; failure swallowed.  
- Cohort endpoint intentionally limited (no heavy M1–M3 matrix).  
- Production-scale load testing against live traffic **not executed in this environment** (Phase 13/14 hosting gaps). Recommend monitoring analytics latency after deploy.  
- Feature tests: `Phase15AnalyticsTest` + prior Phase 5 analytics tests **passed**.

---

## 10. Privacy review

| Collect | Avoid |
|---------|-------|
| Truncated search query, results_count, optional user_id, platform | Passwords, PANs, CVV, JWTs, auth secrets |
| Existing commerce PII already in orders | New invasive device fingerprinting |

Retention for `search_events` is operator-owned; purge job recommended when volume grows.

---

## 11. Data-quality results

Documented in `docs/ANALYTICS_DATA_QUALITY.md`. Known intentional mismatches: live stock vs ranged sales for attention; gross revenue vs separate refunds; partial funnel honesty.

---

## 12. Business insights (questions the system can answer)

| Question | Answerable? |
|----------|-------------|
| Top products by units/revenue? | Yes |
| Top categories? | Yes |
| High views / low purchases? | **No** (no views) |
| Where checkout abandons? | **No** (no checkout-start) |
| Which payment method fails most? | Yes (payments report) |
| High return products/reasons? | Partial (returns data quality dependent) |
| Campaign measurable sales? | **No** without attribution |
| OOS / low stock high demand? | Yes (attention) |
| Returning buyers? | Yes (period + lifetime counts) |

---

## 13. Optimization opportunities

See `docs/BUSINESS_OPTIMIZATION_BACKLOG.md` (H1–H4, M1–M6, L1–L5).

---

## 14. Alerts

| Alert | Status |
|-------|--------|
| High-demand low-stock attention list | **EXISTS** (UI/API advisory) |
| Configurable payment-failure / backlog / return-rate threshold alerts | **BACKLOG** (H3/H4) — avoid spam without thresholds |

---

## 15. Remaining risks

1. Behavioral conversion invisible until events exist.  
2. Campaign spend decisions without attribution.  
3. `search_events` table growth without purge.  
4. Shared `reports.view` may over-expose financial KPIs to staff.  
5. Analytics query cost on large production tables without caching (measure first).  
6. Soft-launch / staging environment gaps from Phase 13/14 still limit “production verification.”

---

## 16. Future analytics roadmap

1. Privacy-safe event ingest (views, cart, checkout_start).  
2. Order-level campaign/UTM attribution.  
3. Loyalty + subscription analytics with strict state definitions.  
4. Configurable Admin alerts.  
5. Optional `financial_reports.view` split.  
6. Cohort retention matrix when volume justifies.  
7. Recommendation **signals** backlog only — no AI engine in this phase.  
8. A/B architecture design doc — no random price experiments.

---

## Verification checklist

| Check | Result |
|-------|--------|
| Analytics read-only on financials | Yes |
| Search log failure does not break catalog | Yes (`SearchEventRecorder`) |
| No fake campaign revenue | Yes |
| Phase 15 + Phase 5 analytics tests | Passed |
| Customer checkout path unchanged by analytics writes | Intended (search logging only) |

---

## Deliverables

- `docs/ANALYTICS_METRIC_DEFINITIONS.md`  
- `docs/ANALYTICS_DATA_QUALITY.md`  
- `docs/BUSINESS_OPTIMIZATION_BACKLOG.md`  
- `docs/PHASE_15_ANALYTICS_REPORT.md` (this file)

**STOP after PHASE 15.**
