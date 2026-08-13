# QA-10 REPORT — Cross-Platform Integration, Shared State & Data Consistency

**Date:** 2026-08-12  
**Phase:** QA-10  
**Status:** **COMPLETE**

---

## 1. Status

**COMPLETE**

Server-authoritative shared state verified across Customer Web/Mobile and Admin Web/Mobile sessions against the same Laravel `/api/v1` API. No production business-logic defects required fixes in this phase. Carry-forward risks remain honestly open.

---

## 2. Scope

| In scope | Out of scope |
|----------|--------------|
| Same-account cart/order visibility | Feature cloning Admin Mobile → Admin Web |
| Order status / tracking consistency | Real-time WebSocket sync |
| Idempotent place-order | Live Razorpay provider verification |
| RBAC (customer ≠ admin) | QA-SEC-001 httpOnly cookie auth |
| Inventory/order consistency regression | New marketing/analytics modules |
| QA-02…QA-09 regression | Database schema changes |

Architecture confirmed:

```
Customer Web / Customer Mobile / Admin Web / Admin Mobile
        → Laravel REST /api/v1
        → Services / State Machines
        → MySQL
```

---

## 3. Bugs found

| ID | Severity | Tags | Finding | Disposition |
|----|----------|------|---------|-------------|
| — | — | — | No new product CRITICAL/HIGH integration defects | N/A |
| (test-only) | LOW | CONFIGURATION | PHPUnit + tymon/jwt-auth sticky token/user across consecutive `$this->json` calls when switching Bearer principals | Fixed in test harness via `actingAs($user, 'api')` — **not** a production multi-client bug |

---

## 4. Bugs fixed

| Item | Notes |
|------|-------|
| `Qa10CrossPlatformIntegrationTest` auth hygiene | Cross-principal assertions use `actingAs('api')` + `JWTAuth::unsetToken()` + `forgetGuards()`; staff permissions synced explicitly (QA-05/09 pattern) |
| Production code | **No production code changes** — live API already consistent |

---

## 5. Customer Web verification

| Check | Result |
|-------|--------|
| Login (Asha) against LAN API | **PASS** (session token) |
| Cart mutate → shared account cart | **PASS** (API session simulating Web) |
| Place COD order | **PASS** (`ORD-20260812-00013`) |
| Order show totals/status | **PASS** |
| Observe admin-driven status changes after refresh | **PASS** |
| Concurrent UI refresh UX | **UNVERIFIED** (no Playwright run this phase; API refresh authoritative) |

---

## 6. Customer Mobile verification

| Check | Result |
|-------|--------|
| Same Asha account second session (android device claim) | **PASS** |
| Read cart after Web add; qty update visible to Web | **PASS** |
| Same order id/number/totals as Web | **PASS** |
| Status after admin transition | **PASS** |
| Physical device interactive UI (4-app concurrent) | **UNVERIFIED** (device `2d3714f` available; Customer app configured with `API_BASE_URL=http://192.168.1.3:8000/api/v1`; full UI journey not re-driven in QA-10) |
| Prior QA-07 device parity | **PASS** (carry-forward) |

---

## 7. Admin Web verification

| Check | Result |
|-------|--------|
| Login `admin@nursery.test` | **PASS** |
| Show same COD order as customer | **PASS** |
| Status transitions PROCESSING → PACKED → SHIPPED | **PASS** |
| Invalid transition → 409 | **PASS** (PHPUnit; live cancel path throttled on retry) |
| Shipment status lowercase vs order UPPER | **PASS** (`shipped` / `SHIPPED`) — intentional; not “fixed” for cosmetics |

---

## 8. Admin Mobile verification

| Check | Result |
|-------|--------|
| Same admin token/session family reading `/admin/orders/{id}` | **PASS** |
| Sees updated status after Admin Web transition | **PASS** |
| Ops scope (not full Web clone) | **PASS** / intentional **PARTIAL** (QA-ADM-002) |
| Returns module | **MISSING** by design (unchanged) |
| QA-09 device ops journey | **PASS** (carry-forward; pick→pack→ship mutation was PARTIAL in QA-09) |

---

## 9. API verification

| Check | Result |
|-------|--------|
| `GET /health/ready` | **PASS** |
| Dual customer sessions share cart | **PASS** |
| COD place + `X-Request-Id` idempotency | **PASS** |
| Customer + admin order field agreement (number, totals, items, status) | **PASS** |
| Order state machine transitions | **PASS** |
| Customer → `/admin/*` → 403 | **PASS** |
| Envelope `{success,message,data,errors,meta}` | **PASS** |
| `Qa10CrossPlatformIntegrationTest` (5 tests) | **PASS** |

---

## 10. Cross-platform consistency

| Flow | Result |
|------|--------|
| A Order creation (Web + Mobile sessions) | **PASS** |
| B Admin visibility | **PASS** |
| C Status update propagation | **PASS** |
| D Tracking / shipment casing | **PASS** (order UPPER, shipment lower) |
| E Cancellation | **PASS** (PHPUnit); live cancel **PARTIAL** this session (login throttle after golden path) |
| F Reorder | **UNVERIFIED** (no defect found in code path; not re-executed live) |
| G Returns visibility | **PASS** (list readable both customer sessions via `/customer/returns`); Admin Mobile N/A |

Refresh behavior: clients must re-fetch after server changes; real-time push is **not** promised.

---

## 11. Inventory/order consistency

| Check | Result |
|-------|--------|
| QA-05 regression PHPUnit | **PASS** (included in Qa02–Qa10 filter run) |
| Live COD order inventory side-effects | **PASS** (order created CONFIRMED; prior QA-05 commit/cancel matrix unchanged) |

---

## 12. Authentication/session verification

| Client | Login | Notes |
|--------|-------|-------|
| Customer Web/Mobile sessions | **PASS** | Independent JWTs, same user/cart |
| Admin Web/Mobile sessions | **PASS** | |
| Expired-token deep matrix | **UNVERIFIED** this phase | Covered earlier QA-02/09 |
| QA-SEC-001 localStorage JWT | **OPEN** | Not fixed |

---

## 13. RBAC verification

| Check | Result |
|-------|--------|
| Customer hitting admin orders | **PASS** (403) |
| Admin with `orders.view` / `orders.update_status` | **PASS** |
| EnsurePermission OR (QA-09-001) | **PASS** (Qa09 regression) |
| Super-admin bypass | **UNVERIFIED** this phase (existing architecture; not regressed) |

---

## 14. Unit tests

| Suite | Result |
|-------|--------|
| API: `Qa10CrossPlatformIntegrationTest` | **PASS** (5/5, 40 assertions) |
| Client unit suites | No client code changed → not re-required; prior QA suites still green from earlier phases |

---

## 15. Integration tests

| Suite | Result |
|-------|--------|
| Live LAN golden API (dual cust + dual admin sessions) | **PASS** (`ORD-20260812-00013`) |
| PHPUnit cross-platform feature tests | **PASS** |

---

## 16. Regression tests

```
php artisan test --filter='Qa10|Qa09|Qa08|Qa07|Qa05|Qa04|Qa03|Qa02'
→ result=passed tests=49 passed=49 assertions=238
```

**PASS**

---

## 17. Real-device tests

| Item | Result |
|------|--------|
| Device present (`vivo 1951` / `2d3714f`) | Yes |
| Customer app LAN define in terminal | Present |
| Interactive 4-client UI golden journey | **UNVERIFIED** |
| API dual-session substitute for Web/Mobile clients | **PASS** |

---

## 18. Build/lint/analyze

| App | Result |
|-----|--------|
| nursery-api PHPUnit QA filter | **PASS** |
| Client builds | Not re-run (no client code changes) |

---

## 19. Database changes

**None.** `QA_DATABASE_CHANGE_PROPOSAL.md` not required.

---

## 20. API contract changes

**None.**

---

## 21. UNVERIFIED

- Live Razorpay payment UI / provider credentials  
- Concurrent four-UI interactive refresh on physical device  
- Reorder live E2E this phase  
- Full expired-token matrix this phase  
- Live cancel retry after auth throttle (PHPUnit covers cancel)  
- Super-admin bypass re-prove this phase  

---

## 22. BLOCKED

**None.**

---

## 23. Remaining risks

1. **QA-SEC-001** — Web JWT in `localStorage` (OPEN → QA-11)  
2. **QA-ADM-002** — Admin Mobile thinner by design (intentional PARTIAL)  
3. **Razorpay live** — UNVERIFIED without provider keys  
4. **QA-09** pick→pack→ship device mutation was PARTIAL — still not claimed fully closed  
5. Clients may show stale UI until refresh (by design)  

---

## 24. Files changed

- `apps/nursery-api/tests/Feature/Qa10CrossPlatformIntegrationTest.php` (**new/updated** — automated cross-platform integration tests only)
- `docs/QA-10-REPORT.md` (this file)
- `docs/QA-10-CLOSEOUT-REPORT.md`
- `docs/QA-10-TEST-MATRIX.md`
- `docs/QA_MASTER_BUG_REGISTER.md` (status note)
- `docs/QA_FEATURE_MATRIX.md`
- `docs/QA_REMEDIATION_ROADMAP.md`

---

## 25. QA-11 readiness

**YES** — proceed to Security + RBAC hardening (QA-SEC-001). Do not claim Razorpay or SEC-001 fixed by QA-10.
