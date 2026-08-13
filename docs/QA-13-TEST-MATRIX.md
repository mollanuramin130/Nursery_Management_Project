# QA-13 TEST MATRIX — Automated Regression + Coverage

**Date:** 2026-08-12 · **Phase status:** COMPLETE

Legend: **PASS** · **FAIL** · **UNVERIFIED** · **BLOCKED** · **N/A**

---

## A. New / updated automated (executed)

| ID | Case | Command / evidence | Result |
|----|------|--------------------|--------|
| T13-01 | Qa13RegressionTest (10 cases) | `php artisan test --filter=Qa13RegressionTest` | PASS |
| T13-02 | Phase5 campaigns support flag | `Phase5AnalyticsTest` (updated) | PASS |
| T13-03 | Web preview race helpers | `npm run test:unit` (nursery-web) | PASS |
| T13-04 | Flutter preview race helpers | `flutter test …checkout_preview_rules_test.dart` | PASS |
| T13-05 | Qa02–Qa13 ×2 (flake check) | filter run twice → 74/74 | PASS |
| T13-06 | QA+Phase full filter | 160 tests, 732 assertions | PASS |
| T13-07 | Admin Mobile full flutter test | 21 tests | PASS |
| T13-08 | Customer Web build | `npm run build` | PASS |
| T13-09 | Flutter analyze checkout files | PASS | PASS |
| T13-10 | Live analytics inventory timing | 376ms / 56 SKUs | PASS (assess) |

---

## B. Prior-phase suites (included in T13-06)

| Suite | Result |
|-------|--------|
| Qa02 Auth | PASS |
| Qa03 Cart | PASS |
| Qa04 Checkout/COD/Stub | PASS |
| Qa05 Inventory/Fulfillment | PASS |
| Qa07 Returns/Reviews | PASS |
| Qa08 Users/Roles | PASS |
| Qa09 Admin Ops Mobile | PASS |
| Qa10 Cross-platform | PASS |
| Qa11 Security | PASS |
| Qa12 Performance | PASS |
| Phase3–21 (filtered) | PASS |

---

## C. Explicit assessments

| Item | Result |
|------|--------|
| QA-PERF-010 | OPEN — assessed OK at 56 SKUs / 376ms; growth risk |
| QA-PERF-011 | FIXED — generation guards + tests |
| QA-SEC-001 | OPEN — not in scope |
| Razorpay live | UNVERIFIED |
| Device pick→pack→ship | UNVERIFIED / PARTIAL |
| Controlled load | UNVERIFIED |

---

## Notes

- Never invent PASS. Counts from executed PHPUnit JSON / Flutter / npm output.
- Web E2E Playwright browser suite not expanded this phase → UI concurrency UNVERIFIED.
