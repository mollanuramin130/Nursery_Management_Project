# QA-12 TEST MATRIX — Performance + Reliability

**Date:** 2026-08-12 · **Phase status:** COMPLETE

Legend: **PASS** · **FAIL** · **UNVERIFIED** · **BLOCKED** · **N/A**

---

## A. Automated API / Feature

| ID | Case | Command / evidence | Result |
|----|------|--------------------|--------|
| T12-01 | sellableQtyMap batches inventory | `php artisan test --filter=Qa12PerformanceTest` | PASS |
| T12-02 | inventory list SQL pagination | same | PASS |
| T12-03 | canReorder without N+1 exists | same | PASS |
| T12-04 | admin coupons paginated meta | same | PASS |
| T12-05 | expired empty-item → PAYMENT_FAILED | same | PASS |
| T12-06 | Qa02–Qa12 regression filter | `php artisan test --filter='Qa12\|…\|Qa02'` | PASS (65) |
| T12-07 | Qa05 inventory concurrency regression | included in T12-06 | PASS |
| T12-08 | Index migration applied | `migrate:status` … `qa12_performance_indexes` Ran | PASS |

---

## B. Client unit / widget

| ID | Case | Command | Result |
|----|------|---------|--------|
| T12-10 | Customer Mobile refresh single-flight | `flutter test test/token_refresh_coordinator_test.dart` (+ auth messages) | PASS |
| T12-11 | Admin Mobile session refresh | `flutter test test/session_refresh_test.dart` | PASS |
| T12-12 | Customer Web qa-unit-checks | `npm run test:unit` | PASS |
| T12-13 | Admin Web qa-unit-checks | `npm run test:unit` | PASS |

---

## C. Build / analyze

| ID | Case | Command | Result |
|----|------|---------|--------|
| T12-20 | Customer Web build | `npm run build` | PASS |
| T12-21 | Admin Web build | `npm run build` | PASS |
| T12-22 | Flutter analyze (changed refresh files) | `flutter analyze …` | PASS |

---

## D. Live timings / load

| ID | Case | Result |
|----|------|--------|
| T12-30 | Health / products / cart / orders timings | PASS (recorded in QA-12-REPORT) |
| T12-31 | Admin dashboard/inventory timings (closeout) | UNVERIFIED (admin login 429 during burst) |
| T12-32 | Controlled load 10/25/50 users | UNVERIFIED |
| T12-33 | EXPLAIN ANALYZE large production-like DB | UNVERIFIED |

---

## E. Device / payment / security carry-forward

| ID | Case | Result |
|----|------|--------|
| T12-40 | Razorpay live | UNVERIFIED |
| T12-41 | Physical device interactive auth/perf | UNVERIFIED |
| T12-42 | QA-SEC-001 HttpOnly migration | OPEN / not in scope |
| T12-43 | QA-09 device pick→pack→ship mutations | PARTIAL (carry-forward) |

---

## Notes

- Never mark PASS without executed command.  
- Local timings ≠ production capacity.  
- Coupon list pagination is additive `meta.pagination`.
