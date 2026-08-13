# QA-12 REPORT — Performance + Reliability

**Date:** 2026-08-12  
**Phase:** QA-12  
**Status:** **COMPLETE**

---

## 1. Status

**COMPLETE**

Baselines measured, DB/N+1/pagination audited, confirmed HIGH defects fixed with automated tests, regression Qa02–Qa12 PASS. No unresolved QA-12 CRITICAL/HIGH. Carry-forward: QA-SEC-001 OPEN; Razorpay live UNVERIFIED.

---

## 2. Performance baseline

Measured against local API `http://127.0.0.1:8000/api/v1` (dev machine — not production capacity).

### Pre-fix (session baseline)

| Endpoint | Approx time | Notes |
|----------|-------------|-------|
| `GET /health` | ~114ms | |
| `GET /products` | ~44ms | |
| `POST /auth/login` | ~410ms | bcrypt dominant |
| `GET /cart` | ~67ms | |
| `GET /admin/dashboard` | ~124ms | |
| `GET /admin/inventory` | ~136ms | Pre-fix: full-table PHP paginate risk |
| `GET /admin/orders` | ~115ms | |

### Post-fix (re-measured where not rate-limited)

| Endpoint | HTTP | Time | Payload |
|----------|------|------|---------|
| `GET /health` | 200 | 49ms | 211b |
| `GET /products?per_page=20` | 200 | 46ms | 7099b |
| `POST /auth/login` (customer) | 200 | 533ms | — |
| `GET /cart` | 200 | 49ms | 721b |
| `GET /orders?per_page=10` | 200 | 69ms | 4737b |
| `POST /auth/login` (admin) | 429 | — | Rate-limit during burst; UNVERIFIED this pass |
| Admin dashboard/inventory/orders/coupons | — | — | UNVERIFIED this pass (429 on admin login) |

Query counts for fixed paths asserted in `Qa12PerformanceTest` (sellable batch ≤2 queries for 2 SKUs).

---

## 3. Bugs found

| ID | Severity | Area | Finding |
|----|----------|------|---------|
| QA-PERF-001 | HIGH | Inventory API | `InventoryService::list` loaded full table then PHP-paginated |
| QA-PERF-002 | HIGH | Cart API | `CartService::present` N+1 `sellableQty` per line |
| QA-PERF-003 | HIGH | Orders API | Order list `canReorder()` fired `exists()` per row |
| QA-PERF-004 | HIGH | Admin coupons | Unbounded coupon index + redemption COUNT N+1 |
| QA-PERF-005 | MEDIUM | Dashboard | Low-stock KPI hydrated all inventory rows |
| QA-PERF-006 | HIGH | DB indexes | Missing composites on `stock_movements` ref lookup + `payments(created_at,status)` |
| QA-PERF-007 | HIGH | Cron | Expired-reservation skipped empty-item orders; schedule overlap without expiry |
| QA-PERF-008 | HIGH | Customer Mobile | No refresh single-flight → token refresh storm risk |
| QA-PERF-009 | MEDIUM | Admin Web | Live search on several list pages fired API per keystroke |
| QA-PERF-010 | MEDIUM | Analytics | `AnalyticsService` inventory report loads all `InventoryItem` rows |
| QA-PERF-011 | MEDIUM | Checkout clients | Preview request race/spam possible under rapid shipping changes |

---

## 4. Bugs fixed

| ID | Fix |
|----|-----|
| QA-PERF-001 | SQL `paginate` + sellable/status filters in SQL (`InventoryService::list`) |
| QA-PERF-002 | `InventoryService::sellableQtyMap` + cart `present()` batch |
| QA-PERF-003 | `withCount('items')` + `canReorder` uses count/relation; list eager-loads `items` limit 1 |
| QA-PERF-004 | Paginated admin coupons + `withCount('redemptions')` + `Coupon::redemptions()` |
| QA-PERF-005 | Dashboard low-stock via SQL `whereRaw` sellable expression |
| QA-PERF-006 | Migration `2026_08_12_180000_qa12_performance_indexes` (ran locally) |
| QA-PERF-007 | Empty-item orders → `PAYMENT_FAILED`; `--limit`; `withoutOverlapping(55)` |
| QA-PERF-008 | `TokenRefreshCoordinator` on Customer Mobile (parity Admin Mobile) |
| QA-PERF-009 | `useDebouncedValue` on returns, notifications, reviews, loyalty, subscriptions, picking, shipments, reconciliation |

Left OPEN (MEDIUM): QA-PERF-010, QA-PERF-011 — documented; not phase blockers.

---

## 5. API performance

- Inventory list: SQL pagination (max page size enforced via service).
- Cart present: O(1) inventory batch vs O(n) sellable lookups.
- Customer order list: reduced reorder existence queries.
- Admin coupons: paginated with meta; max `per_page` 100.
- Dashboard KPIs: low-stock count without full hydration.

---

## 6. Database performance

- Additive indexes only (see `docs/QA_DATABASE_CHANGE_PROPOSAL.md`).
- N+1 audit focused on cart, orders list, coupons, dashboard low-stock.
- No destructive schema changes.

---

## 7. Customer Web performance

- Prior QA-06 search stale-guard retained.
- No architecture rewrite; checkout preview race remains MEDIUM (QA-PERF-011).
- Unit checks + production build PASS.

---

## 8. Customer Mobile performance

- Single-flight refresh coordinator + unit test PASS.
- `flutter analyze` on changed files: no issues.
- Physical-device interactive perf pass: UNVERIFIED this phase (API+unit covered).

---

## 9. Admin Web performance

- Search debounce on high-churn list pages.
- Inventory/users/coupons already used applied-search pattern.
- Build PASS; unit checks PASS.

---

## 10. Admin Mobile performance

- Existing QA-09 refresh single-flight retained; session tests PASS.
- No new Admin Mobile scope expansion (QA-ADM-002 unchanged).

---

## 11. Reliability / concurrency

| Area | Result |
|------|--------|
| Expired reservation cron empty orders | FIXED + tested |
| Schedule overlap mutex | `withoutOverlapping` with expire |
| Inventory concurrency (QA-05) | Regression via Qa05 in suite |
| Order idempotency (X-Request-Id) | Prior QA coverage retained |
| Token refresh single-flight Web/Mobile | Customer Mobile added; Admin Mobile prior |
| Load / concurrent users | UNVERIFIED (no load tool run claimed) |

---

## 12. Unit tests

| Suite | Result |
|-------|--------|
| `Qa12PerformanceTest` (5) | **PASS** |
| Customer Flutter `token_refresh_coordinator_test` + auth messages | **PASS** (7) |
| Admin Mobile `session_refresh_test` | **PASS** (8) |
| Customer Web `qa-unit-checks` | **PASS** |
| Admin Web `qa-unit-checks` | **PASS** |

---

## 13. Integration tests

`php artisan test --filter='Qa12|Qa11|Qa10|Qa09|Qa08|Qa07|Qa05|Qa04|Qa03|Qa02'` → **65 passed**, 294 assertions.

---

## 14. Load / performance tests

**UNVERIFIED** — controlled multi-user load tooling not executed. Local timings only; do not infer production capacity.

---

## 15. Regression tests

Qa02–Qa12 API filter suite **PASS**. Client unit suites above **PASS**.

---

## 16. Build / lint / analyze

| Check | Result |
|-------|--------|
| `nursery-web` `npm run build` | **PASS** |
| `nursery-admin` `npm run build` | **PASS** |
| Flutter analyze (token refresh files) | **PASS** |
| Full Customer Web lint | Not re-run as gate (pre-existing FAIL documented in QA-06) |

---

## 17. Database changes

Yes — additive indexes only. Proposal + migration documented. Migrated locally: `2026_08_12_180000_qa12_performance_indexes` **Ran**.

---

## 18. API contract changes

| Change | Impact |
|--------|--------|
| `GET /admin/coupons` now paginated with `meta.pagination` | Additive; clients that ignored pagination still get first page (default `per_page=50`) |
| Inventory list pagination semantics | Same response shape; SQL-backed totals |

No breaking removals of required fields.

---

## 19. UNVERIFIED

- Razorpay live provider flow
- Device interactive auth / mobile UI perf journey
- Concurrent 4-UI load
- Admin endpoint timings during this closeout (login 429)
- Production capacity / EXPLAIN ANALYZE on large tables
- QA-09 pick→pack→ship device mutation chain (carry-forward PARTIAL)

---

## 20. BLOCKED

None for QA-12 completion gate.

---

## 21. Remaining risks

- Analytics inventory full-scan under growth (QA-PERF-010)
- Checkout preview client races (QA-PERF-011)
- QA-SEC-001 Web JWT localStorage
- Login rate-limit can throttle smoke bursts (expected)

---

## 22. Files changed (QA-12 primary)

**API:** `InventoryService.php`, `CartService.php`, `CheckoutService.php`, `AdminCouponController.php`, `Coupon.php`, `DashboardService.php`, `ReleaseExpiredReservationsCommand.php`, `routes/console.php`, migration `2026_08_12_180000_qa12_performance_indexes.php`, `tests/Feature/Qa12PerformanceTest.php`

**Customer Mobile:** `token_refresh_coordinator.dart`, `api_client.dart`, `test/token_refresh_coordinator_test.dart`

**Admin Web:** `useDebouncedValue.ts` + returns/notifications/reviews/loyalty/subscriptions/picking/shipments/reconciliation pages

**Docs:** this report, closeout, test matrix, bug register, feature matrix, remediation roadmap, DB proposal

---

## 23. QA-13 readiness

**YES** — performance/reliability phase closed with honest UNVERIFIED carry-forwards; ready for automated regression expansion (QA-13).
