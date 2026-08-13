# QA-13 REPORT — Automated Regression + Test Coverage

**Date:** 2026-08-12  
**Phase:** QA-13  
**Status:** **COMPLETE**

---

## 1. Status

**COMPLETE**

Test inventory audited; coverage gaps filled (negatives, boundaries, idempotency, analytics campaigns drift, QA-PERF-011 race guards). Full QA+Phase automated suite **160 passed**. No unresolved QA-13 CRITICAL/HIGH. Carry-forwards remain honestly OPEN/UNVERIFIED.

---

## 2. Test inventory

| Layer | Location | Count (approx) | Notes |
|-------|----------|----------------|-------|
| API Feature (QA-*) | `tests/Feature/Qa*.php` | Qa02–Qa13 | Auth → perf → regression gaps |
| API Feature (Phase*) | `tests/Feature/Phase*.php` | Phase3–21 | Inventory, fulfillment, analytics, CRM, etc. |
| API Unit | `tests/Unit/ExampleTest.php` | placeholder | Business logic covered via Feature |
| Customer Web | `src/lib/qa-unit-checks.ts` | script asserts | Auth, checkout, search, preview race |
| Admin Web | `src/lib/qa-unit-checks.ts` | script asserts | Redirect, refund safety |
| Customer Mobile | `apps/nursery_app/test/*.dart` | multiple | Auth, cart map, preview, refresh |
| Admin Mobile | `apps/nursery_admin_mobile/test/*.dart` | multiple | Session, fulfillment gates, RBAC |

---

## 3. Coverage matrix

| Feature | API Unit | API Feature | Cust Web | Cust Mobile | Admin Web | Admin Mobile | Integration | Negative | Status |
|---------|----------|-------------|----------|-------------|-----------|--------------|-------------|----------|--------|
| Auth login/messages | — | COVERED | COVERED | COVERED | COVERED | COVERED | PARTIAL | COVERED | COVERED |
| Refresh / session | — | PARTIAL | PARTIAL | COVERED | PARTIAL | COVERED | — | PARTIAL | PARTIAL* |
| RBAC / IDOR | — | COVERED | — | — | PARTIAL | COVERED | COVERED | COVERED | COVERED |
| Catalog list/detail | — | COVERED | PARTIAL | PARTIAL | — | — | — | COVERED | PARTIAL |
| Cart / coupon / FD | — | COVERED | PARTIAL | COVERED | — | — | COVERED | COVERED | COVERED |
| Checkout preview | — | COVERED | COVERED | COVERED | — | — | — | COVERED | COVERED† |
| COD / stub guard | — | COVERED | COVERED | PARTIAL | — | — | — | COVERED | COVERED |
| Razorpay live | — | PARTIAL | — | — | — | — | — | — | UNVERIFIED |
| Orders / transitions | — | COVERED | PARTIAL | PARTIAL | PARTIAL | PARTIAL | COVERED | COVERED | COVERED |
| Inventory concurrency | — | COVERED | — | — | PARTIAL | PARTIAL | COVERED | COVERED | COVERED |
| Fulfillment | — | COVERED | — | — | PARTIAL | COVERED | COVERED | COVERED | PARTIAL‡ |
| Returns / reviews | — | COVERED | PARTIAL | COVERED | PARTIAL | — | — | COVERED | COVERED |
| Users / roles | — | COVERED | — | — | COVERED | — | — | COVERED | COVERED |
| Analytics inventory | — | COVERED | — | — | — | — | — | — | PARTIAL§ |
| Cross-platform cart/order | — | COVERED | — | — | — | — | COVERED | — | COVERED |
| Perf indexes / N+1 | — | COVERED | — | — | — | — | — | — | COVERED |

\*QA-SEC-001 Web cookie storage OPEN.  
†QA-PERF-011 fixed with generation guards.  
‡Device pick→pack→ship mutation UNVERIFIED/PARTIAL.  
§QA-PERF-010 assessed; remain OPEN for growth.

---

## 4. Bugs found

| ID | Severity | Finding |
|----|----------|---------|
| QA-PERF-011 | MEDIUM→FIXED | Checkout preview race (Web/Mobile) could apply stale responses |
| QA-13-001 | LOW | `Phase5AnalyticsTest` asserted campaigns analytics `supported=false` while Phase17+ implements supported path when `orders.campaign_id` exists |

---

## 5. Bugs fixed

| ID | Fix |
|----|-----|
| QA-PERF-011 | Generation guards Web (`checkout-preview.ts` + checkout page) + Flutter (`CheckoutPreviewRules` + checkout screen); unit tests |
| QA-13-001 | Updated Phase5 assertion to support flag + coupon_performance; locked in Qa13 |

---

## 6. Authentication regression

Qa02 + client auth message tests **PASS**. Refresh single-flight Customer/Admin Mobile **PASS**. Web session-clear on refresh fail retained (QA-11).

---

## 7. RBAC / security regression

Qa11SecurityTest included in suite **PASS**. Qa13 customer→admin 403 **PASS**.

---

## 8. Catalog regression

Qa13 product 404 / inactive exclusion / per_page cap **PASS**. QA-06 search/image helpers retained in Web unit checks **PASS**.

---

## 9. Cart regression

Qa03 + Qa13 zero qty / oversell / invalid coupon / FD threshold ±1 **PASS**.

---

## 10. Checkout regression

Qa04 COD + preview authority **PASS**. QA-PERF-011 stale-preview guards **PASS** (unit).

---

## 11. Payment regression

Qa04PaymentStubGuard + Phase21 webhook tests in Phase filter **PASS**. Razorpay live **UNVERIFIED**.

---

## 12. Order regression

Qa04/Qa10 + invalid transition (Qa13) + COD idempotency same `X-Request-Id` **PASS**.

---

## 13. Inventory regression

Qa05 + Phase6/18 + Qa12 sellable/pagination **PASS**.

---

## 14. Fulfillment regression

Phase7/20 + Qa05 + Admin Mobile fulfillment gate tests **PASS**. Device mutation chain **UNVERIFIED**.

---

## 15. Returns / refunds regression

Qa07 + Phase8 + Admin refund safety unit checks **PASS**.

---

## 16. Users / roles regression

Qa08 + Qa11 escalation guard **PASS**.

---

## 17. Cross-platform regression

Qa10CrossPlatformIntegrationTest **PASS**. Concurrent 4-UI **UNVERIFIED**.

---

## 18. QA-PERF-010 result

**ASSESSED — remain OPEN (MEDIUM)**

- Code: `AnalyticsService::inventory()` loads all `InventoryItem` rows, then returns ≤100 sorted rows.
- Live local: `GET /admin/analytics/inventory` → **200 in 376ms**, `sku_locations=56`, `rows=56`.
- Correctness OK; not a correctness defect.
- Acceptable at current seed/scale; growth risk remains.
- Qa13 asserts row cap ≤100 + summary.
- **No optimization applied** (measurement does not justify change at current scale).

---

## 19. QA-PERF-011 result

**FIXED / CLOSED**

- Web + Mobile generation guards; stale response ignored.
- Tests: Web `qa-unit-checks`, Flutter `checkout_preview_rules_test`.

---

## 20. Unit tests

| Suite | Result |
|-------|--------|
| Customer Web `npm run test:unit` | PASS |
| Admin Web `npm run test:unit` | PASS |
| Customer Flutter checkout/auth/cart/refresh | PASS (13) |
| Admin Flutter full `flutter test` | PASS (21) |
| Flutter analyze (checkout files) | PASS |

---

## 21. Integration tests

Qa02–Qa13 filter: **74 passed** (327 assertions) ×2 runs identical (no flake).

QA+Phase filter: **160 passed**, 732 assertions.

---

## 22. Negative / edge tests

Qa13: admin 403, product 404/inactive, qty 0, oversell, invalid coupon, invalid transition, FD threshold boundaries **PASS**.

---

## 23. Concurrency tests

Qa05 last-unit + Qa13 COD idempotency + Mobile refresh single-flight **PASS**. Controlled multi-user load **UNVERIFIED**.

---

## 24. Flaky-test assessment

Qa02–Qa13 suite run twice → identical 74/74 PASS. No flake observed in this session.

---

## 25. Build / lint / analyze

| Check | Result |
|-------|--------|
| Customer Web build | PASS |
| Flutter analyze (changed) | PASS |
| Admin Web build | Not re-run this phase (unchanged production code; unit PASS) |
| composer audit | Not executed this session → UNVERIFIED |

---

## 26. Full regression

`php artisan test --filter='Qa13|Qa12|…|Qa02|Phase'` → **160 passed**, 0 failed, 732 assertions.

---

## 27. Database changes

**None** in QA-13.

---

## 28. API contract changes

**None**.

---

## 29. UNVERIFIED

- Razorpay live
- Controlled load testing
- Large-DB EXPLAIN
- Device interactive auth / Admin Mobile pick→pack→ship mutations
- Concurrent 4-UI
- composer audit

---

## 30. BLOCKED

None.

---

## 31. Remaining risks

- QA-SEC-001 Web JWT localStorage
- QA-PERF-010 analytics full scan under inventory growth
- QA-ADM-002 intentional Admin Mobile scope
- Limited Playwright/E2E browser automation (Web relies on unit + API)

---

## 32. Files changed

- `apps/nursery-web/src/lib/checkout-preview.ts` (new)
- `apps/nursery-web/src/app/checkout/page.tsx`
- `apps/nursery-web/src/lib/qa-unit-checks.ts`
- `apps/nursery_app/lib/core/checkout_preview_rules.dart`
- `apps/nursery_app/lib/screens/checkout_screen.dart`
- `apps/nursery_app/test/checkout_preview_rules_test.dart`
- `apps/nursery-api/tests/Feature/Qa13RegressionTest.php` (new)
- `apps/nursery-api/tests/Feature/Phase5AnalyticsTest.php`
- Docs: QA-13 report/closeout/matrix + register/feature matrix/roadmap

---

## 33. QA-14 readiness

**YES** — automated regression backbone is green; remaining items are known UNVERIFIED/OPEN carry-forwards suitable for full E2E business validation (QA-14).
