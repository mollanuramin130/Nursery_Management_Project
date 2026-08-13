# QA-09 REPORT — Admin Mobile Operations Hardening + Physical Device Validation

**Date:** 2026-08-12  
**Status:** **COMPLETE**

Device: vivo 1951 (`2d3714f`) · API `http://192.168.1.3:8000/api/v1`  
APK: debug with LAN `--dart-define` (not `10.0.2.2`)

---

## 1. Status

**QA-09 COMPLETE**

Admin Mobile ops scope validated on a physical device. Session refresh hardened with single-flight coordinator + session-expired login message. Critical middleware bug fixed so comma-separated permission ORs actually work (e.g. `purchase_orders.view` alone).

QA-ADM-002 remains **intentional PARTIAL** (no marketing/analytics/catalog/Users on Admin Mobile).  
QA-SEC-001 remains **OPEN**. Razorpay live remains **UNVERIFIED**.

---

## 2. Bugs fixed

| ID | Fix |
|----|-----|
| **QA-09-001** | `EnsurePermission` ignored Laravel-split middleware args after the first — OR permissions such as `purchase_orders.view` failed with 403. Fixed with variadic `string ...$permissionArgs`. |
| **QA-AUTH-004** (hardening) | Single-flight `TokenRefreshCoordinator`; session-expired message via `clearLocalSession(sessionExpired: true)` |
| (support) | ApiException 500/502/503 user messages; shared `fulfillment_gates.dart` |

---

## 3. Bugs still open

- **QA-SEC-001** — Web JWT localStorage → QA-11  
- **QA-ADM-002** — Intentional thinner Admin Mobile (not a defect)  
- Razorpay live UNVERIFIED  

---

## 4–13. Area verification

| Area | Result | Evidence |
|------|--------|----------|
| Auth / login | PASS | Device login as `admin@nursery.test` |
| Session / refresh | PASS | Coordinator unit tests + invalidate clears tokens |
| Dashboard | PASS | KPIs Pending/Low stock/POs; Hello Ops |
| Orders | PASS | List + detail `ORD-20260812-00010` CONFIRMED |
| Inventory | PASS | Stock list with on-hand/reserved/avail |
| Scanning | PASS | Scan/lookup SKU screen (SKU search; camera optional) |
| Fulfillment | PASS | Hub queues + order screen (`Complete pick` on PROCESSING) |
| Purchase orders | PASS | `PO-20260810-0001` listed |
| Suppliers | PASS | ClayCraft / Green Valley |
| Warehouses | PASS | WH-PUNE / WH-BLR |
| Notifications | PASS | Order confirmed list |
| RBAC | PASS | Nav gated; 403 tests; permission OR fix |
| Logout | PASS | Returns to Sign in |

Full pick→pack→ship **mutation** on this device not re-executed end-to-end (UI + queues PASS; API covered QA-05).

---

## 14. Physical device testing

PASS for golden ops journey (login → dashboard → orders → inventory → scan → fulfillment → PO → suppliers → warehouses → notifications → logout).

---

## 15–18. Regressions

| Suite | Result |
|-------|--------|
| Customer Web (API users/roles/orders + QA-08 unit) | PASS |
| Customer Mobile (API cart/returns/reviews) | PASS |
| Admin Web (API + `npm run test:unit`) | PASS |
| PHPUnit Qa02–09 | **44 PASS** |

---

## 19–24. Tests / build / analyze

| Check | Result |
|-------|--------|
| Flutter unit (`session_refresh`, fulfillment, permissions, auth messages) | PASS |
| PHPUnit `Qa09AdminOpsMobileTest` | PASS (5) |
| `flutter build apk --debug` + LAN define | PASS |
| `flutter analyze` (touched) | PASS |
| Database | **NONE** |
| API | Additive behavior fix only (middleware OR); no route contract break |

---

## 25–29. Risks / UNVERIFIED / BLOCKED

**UNVERIFIED:** Razorpay live; full device pick→pack→ship mutation chain; Customer Mobile UI this session  

**BLOCKED:** None  

**Risks:** QA-SEC-001; Admin Mobile intentionally excludes marketing/analytics/Users  

---

## 30. QA-10 readiness

**YES**
