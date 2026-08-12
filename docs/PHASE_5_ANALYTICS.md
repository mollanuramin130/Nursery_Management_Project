# PHASE 5 — Analytics & Operational Reporting

## 1. Audit

See [PHASE_5_ANALYTICS_AUDIT.md](./PHASE_5_ANALYTICS_AUDIT.md).

## 2. Existing analytics

- `GET /admin/dashboard` — today KPIs + 7-day sales (unchanged)
- `GET /admin/reports/{sales|inventory|top_products}` — still available

## 3. New analytics

Dedicated aggregates under `GET /admin/analytics/*` (permission `reports.view`):

| Endpoint | Purpose |
|----------|---------|
| `/overview` | Executive KPIs, comparison, trend, top products/categories, status, ops |
| `/sales` | Sales summary + breakdowns |
| `/orders` | Order counts, status, funnel gap note |
| `/products` | Product performance + stock lists |
| `/categories` | Category performance |
| `/customers` | Customer KPIs + segments |
| `/inventory` | Stock health + movements |
| `/campaigns` | Explicit unsupported |
| `/coupons` | Coupon performance |
| `/returns` | Returns + refunds |
| `/seasonal` | Monthly demand (no season taxonomy) |
| `/export` | CSV (`reports.export`) |

Admin UI: `/analytics/*`, `/reports`.

## 4. Metric definitions

[PHASE_5_METRIC_DEFINITIONS.md](./PHASE_5_METRIC_DEFINITIONS.md)

## 5–6. API / database

- No new business tables
- Indexes: `2026_08_11_210000_phase_5_analytics_indexes.php`
- Permission seed: `reports.export` via migration + seeder

## 7. Indexes

- `orders(created_at, status)`, `orders(created_at, payment_method)`
- `order_items(product_id, order_id)`
- `refunds(created_at, status)`, `return_requests(created_at, status)`
- `stock_movements(created_at, type)`

## 8. Caching

None. Metrics are live aggregates. Short TTL can be added later if load requires it.

## 9. Permissions

- View: `reports.view`
- Export: `reports.export`
- Frontend checks are UX-only; middleware enforces backend.

## 10. Export

Synchronous CSV ≤ 5000 rows; audited as `analytics.export`. No queue jobs yet — document gap for large async exports.

## 11–13. Security / privacy / performance

- Staff JWT + permission
- No PII beyond user_id in high-value list
- No passwords/tokens/payment secrets
- SQL aggregation (`SUM`/`COUNT`/`GROUP BY`); range capped at 366 days

## 14. Testing

`Phase5AnalyticsTest` — revenue formula, authz, campaign unsupported, export permission.

## 15. Known limitations

- No campaign attribution
- No profit
- No commercial season taxonomy
- No payment funnel events beyond order status
- No async mega-exports
- Charts = CSS bars (no chart library)

## 16. Future

Campaign attribution model, COGS, season calendar, queued exports, optional Redis cache for overview.
