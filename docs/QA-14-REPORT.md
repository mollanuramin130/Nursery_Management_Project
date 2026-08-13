# QA-14 REPORT — Full E2E Business Validation & Release Gate

**Date:** 2026-08-12  
**Phase:** QA-14  
**Status:** **PARTIAL**

Interactive Customer/Admin Mobile UI golden journeys and Razorpay live remain **UNVERIFIED**. Server-authoritative golden API journey (COD → pick → pack → ship → OFD → deliver) **PASS**. Automated regression **162 passed**.

---

## 1. Executive Summary

GreenLeaf was validated as a release candidate for **local/LAN COD soft-launch** with explicit disclosure of open security (QA-SEC-001), payment (Razorpay live), and device-UI gaps.

| Gate | Result |
|------|--------|
| Environment / health | PASS |
| Golden customer API journey | PASS |
| Golden admin API fulfill → deliver | PASS (`ORD-20260812-00017` → DELIVERED) |
| Cross-platform cart/order (API) | PASS |
| Automated regression Qa02–14 + Phase | PASS (162 / 754 assertions) |
| Builds | PASS (Web + Admin + Customer debug APK) |
| Physical device LAN API | PASS (vivo `2d3714f` curl health) |
| Interactive mobile UI golden | UNVERIFIED |
| Razorpay live | UNVERIFIED |
| Controlled load | UNVERIFIED |

**Release recommendation:** Proceed to QA-15 client acceptance for **COD-only soft launch** with documented risks. Do **not** claim production Razorpay readiness.

---

## 2. Environment

| Check | Result |
|-------|--------|
| MySQL + migrations (incl. QA-12 indexes) | PASS (Ran) |
| `GET /health` + `/health/ready` local + LAN | PASS 200 |
| `APP_ENV=local` `APP_DEBUG=1` | Expected for RC local — production must flip |
| CORS includes :3000/:3001 + LAN | PASS |
| Razorpay keys | **empty** → live UNVERIFIED; stub blocked in production builds (prior QA-04) |
| Customer/Admin Web `.env.local` → `127.0.0.1:8000` | OK for local; production examples use HTTPS placeholders |
| No `10.0.2.2` in runtime device config for this pass | Device uses LAN `192.168.1.3` |
| Web :3000 / Admin :3001 | PASS 200 |
| Storage link | NOT LINKED (local note) |

---

## 3. Applications Tested

| App | Mode | Evidence |
|-----|------|----------|
| Laravel API | Live + PHPUnit | Golden script + Qa14GoldenJourneyTest |
| Customer Web | Page smoke + unit + build | / /login /shop 200; build PASS |
| Admin Web | Page smoke + unit + build | /login /dashboard 200; build PASS |
| Customer Mobile | Device net + unit + debug APK | adb curl LAN; flutter test; APK built |
| Admin Mobile | Unit + analyze | flutter test PASS; interactive UI UNVERIFIED |
| MySQL | Via API readiness | healthy |

---

## 4. Golden Customer Journey

### API (executed live)

Login → catalog → search → PDP → wishlist (409 if dup) → cart add/qty → coupon attempt → addresses → shipping → checkout preview (`grand_total` server) → COD place (`ORD-20260812-00015`/`00017`) → idempotent replay same `X-Request-Id` → order detail → logout with `refresh_token` → PASS

### Customer Web UI

Page reachability PASS. Full click-path Playwright E2E **UNVERIFIED** this phase.

### Customer Mobile UI

Device can reach LAN API PASS. Interactive golden UI on vivo **UNVERIFIED**.

---

## 5. Golden Admin Journey

### API (executed live)

Admin login → dashboard → inventory → users → analytics inventory → order detail →  
`pick/start` → `pick/scan` (`code`) → `pick/complete` → `pack` → `ship` → `out-for-delivery` → `deliver` → customer sees **DELIVERED** + shipment lowercase `delivered` — **PASS** (`ORD-20260812-00017`)

### Admin Web UI

Login/dashboard pages 200. Full UI click journey **UNVERIFIED**.

### Admin Mobile UI

Intentional scope (QA-ADM-002). Interactive pick→pack→ship on device **UNVERIFIED** (carry-forward from QA-09).

---

## 6. Cross-Platform Validation

| Flow | Result |
|------|--------|
| Dual-session cart same account | PASS (`item_count` sync True) |
| Order visible after multi-login | PASS (after rate-limit cooldown) |
| Admin fulfill → customer status | PASS |
| Simultaneous 4-UI | UNVERIFIED |
| Qa10 automated cross-platform | PASS (in regression) |

---

## 7. Inventory / Fulfillment

- Live COD commits stock and reaches DELIVERED via fulfillment API — PASS  
- Qa05 concurrency / expired reservation — PASS in regression  
- Invalid transitions rejected — covered Qa13/Qa05  

---

## 8. Payment

| Item | Status |
|------|--------|
| COD | PASS (live + Qa14 test) |
| local_stub production block | PASS (prior Qa04 + unit) |
| Razorpay live initiate/verify/webhook | **UNVERIFIED** (keys empty) |

---

## 9. Security

| Item | Status |
|------|--------|
| QA-SEC-001 HttpOnly/BFF | **OPEN** (Option C) |
| Customer → admin 403 | PASS (live + Qa14) |
| Qa11 RBAC/IDOR/escalation | PASS (regression) |
| Wishlist duplicate | 409 safe (not 500 on retest) |
| Logout requires refresh_token | Expected 422 without body; 200 with refresh |

---

## 10. Performance

| Item | Status |
|------|--------|
| QA-PERF-010 | OPEN — assessed prior; analytics inventory OK at current scale |
| QA-PERF-011 | FIXED (QA-13) — regression retained |
| Controlled 10/25/50 load | UNVERIFIED |
| Large EXPLAIN | UNVERIFIED |

---

## 11. Unit Tests

| Suite | Result |
|-------|--------|
| Customer Web `test:unit` | PASS |
| Admin Web `test:unit` | PASS |
| Customer Flutter `flutter test` | PASS (27) |
| Admin Flutter `flutter test` | PASS (21) |

---

## 12. Integration Tests

`Qa14GoldenJourneyTest` — **2 PASS**  
Included in full filter below.

---

## 13. E2E Tests

| Layer | Result |
|-------|--------|
| Live API golden + fulfill | PASS |
| Automated Qa14 golden | PASS |
| Browser full UI E2E | UNVERIFIED |
| Device interactive E2E | UNVERIFIED |

---

## 14. Regression

`php artisan test --filter='Qa14|…|Qa02|Phase'` → **162 passed**, **754 assertions**, 0 failed.

---

## 15. Build / Lint / Analyze

| Check | Result |
|-------|--------|
| Customer Web build | PASS |
| Admin Web build | PASS |
| Customer debug APK (LAN define) | PASS |
| Flutter analyze | info-only debt (no errors); pre-existing |
| composer audit | No advisories found |

---

## 16. Database Integrity

Observed live order `9049` / `ORD-20260812-00017` progressed CONFIRMED → … → DELIVERED with shipment status `delivered`. No negative inventory or duplicate order under same `X-Request-Id` in this pass. Full orphan audit **not** exhaustively scripted → spot-check PASS.

---

## 17. Bugs Found

| ID | Severity | Notes |
|----|----------|-------|
| — | — | No new CRITICAL/HIGH production defects requiring code fix in this phase |
| QA-14-OBS-001 | LOW | Login rate-limit (429) can interrupt rapid E2E scripts — expected throttle, not a defect |
| QA-14-OBS-002 | LOW | Fulfillment pick scan requires field `code` (not `sku`) — documented for operators |

Initial script false failures (wishlist 500 / logout 422 / cross-session 401) reproduced as: duplicate wishlist→409, logout without refresh_token→422, rate-limited second login→empty token. **Not product regressions.**

---

## 18. Bugs Fixed

None required for release-blocking defects. Added `Qa14GoldenJourneyTest` as permanent golden coverage.

---

## 19. Open / Unverified / Blocked

### OPEN
- QA-SEC-001  
- QA-PERF-010 (accepted at current scale)  
- QA-ADM-002 intentional PARTIAL  

### UNVERIFIED
- Razorpay live  
- Interactive Customer Mobile golden UI  
- Interactive Admin Mobile pick→pack→ship  
- Browser Playwright full journeys  
- Controlled load / large EXPLAIN  
- Concurrent 4-UI  

### BLOCKED
- None  

---

## 20. Release Readiness

**PARTIAL / soft-launch eligible (COD)**

No known unresolved Critical launch blockers for COD on local/LAN. High security residual: Web JWT in localStorage (QA-SEC-001) — disclose to client. Payment: Razorpay not production-verified.

---

## 21. Client Handover Recommendation

1. Accept COD soft-launch with SEC-001 + Razorpay disclosures.  
2. Before public paid launch: Razorpay sandbox/live verification + webhook.  
3. Schedule SEC-001 cookie/BFF project.  
4. Complete physical-device UI golden sign-off (QA-15).  
5. Flip `APP_DEBUG=false`, cache config, storage link, production secrets on deploy host.
