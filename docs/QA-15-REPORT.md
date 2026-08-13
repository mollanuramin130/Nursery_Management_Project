# QA-15 REPORT — Final Client Acceptance + Production Release Gate

**Date:** 2026-08-12  
**Phase:** QA-15  
**Status:** **PARTIAL** (acceptance complete with conditional release)  
**Release decision:** **YELLOW — CONDITIONAL RELEASE**

---

## QA-15 STATUS

**PARTIAL / YELLOW**

GreenLeaf is suitable for **COD soft-launch / controlled client demo** on a correctly configured environment. It is **not** honest “full production paid commerce” until Razorpay live is verified and production config is hardened (`APP_DEBUG=false`, secrets, HTTPS, backups).

---

## EXECUTIVE SUMMARY

| Area | Verdict |
|------|---------|
| Core COD commerce (API) | READY |
| Cross-platform cart/order (API) | READY |
| Fulfillment → customer tracking (API) | READY (QA-14 evidence retained) |
| Automated regression | READY (162 / 754) |
| Razorpay live paid orders | **BLOCKED** until verified |
| QA-SEC-001 Web JWT localStorage | **HIGH RISK — DEFERRED EPIC** (disclose) |
| Interactive mobile UI golden | UNVERIFIED |
| Production ops (backup/monitoring) | UNVERIFIED from this host |
| Local `APP_DEBUG=true` | Unsafe for production as-is |

**Do not claim zero bugs.** Claim: **No known unresolved Critical defects for COD soft-launch**; High residual security risk (SEC-001) and payment blocker for paid checkout remain.

---

## APPLICATIONS AUDITED

### CUSTOMER WEB
- Page smoke PASS (`/`, `/checkout`)
- Unit + production build PASS (QA-14/15)
- Full Playwright click-path golden: **UNVERIFIED**
- JWT in localStorage: **QA-SEC-001 OPEN**

### CUSTOMER MOBILE
- Device `2d3714f` LAN health PASS
- Unit tests PASS (27)
- Debug APK build PASS (QA-14)
- Interactive UI golden on device: **UNVERIFIED**

### ADMIN WEB
- Login page smoke PASS
- Unit + build PASS
- Full UI admin golden: **UNVERIFIED**
- Users/roles + RBAC API: PASS (prior + regression)

### ADMIN MOBILE
- Unit/analyze PASS
- Scope: **QA-ADM-002 INTENTIONAL / BY DESIGN** (ops, not full Admin Web clone)
- Interactive pick→pack→ship on device: **UNVERIFIED**

### API
- Health/ready local+LAN PASS
- QA-15 smoke: login, preview, COD `ORD-20260812-00018`, admin visibility, cart sync PASS
- Regression Qa02–14 + Phase: **162 passed**

### DATABASE
- MySQL healthy; migrations including QA-12 indexes Ran
- No new schema change in QA-15
- Large EXPLAIN / orphan full audit: **UNVERIFIED**

---

## GOLDEN CUSTOMER JOURNEY

| Layer | Result |
|-------|--------|
| API (login→cart→preview→COD→admin visible) | **PASS** (QA-15 live `ORD-20260812-00018`) |
| API fulfill→DELIVERED | **PASS** (QA-14 `ORD-20260812-00017` + Qa14GoldenJourneyTest) |
| Customer Web full UI | **UNVERIFIED** |
| Customer Mobile full UI | **UNVERIFIED** |

---

## GOLDEN ADMIN JOURNEY

| Layer | Result |
|-------|--------|
| API fulfill chain | **PASS** (QA-14) |
| Admin Web full UI | **UNVERIFIED** |
| Admin Mobile device ops | **UNVERIFIED** / intentional thinner scope |

---

## PAYMENT VERIFICATION

| Check | Result |
|-------|--------|
| COD | **PASS** |
| Razorpay keys present | **NO** |
| Razorpay live initiate/verify/webhook | **UNVERIFIED** |
| local_stub blocked in production (code) | **VERIFIED** (Web `isProductionSite` + API production guards) |
| **Paid production orders** | **BLOCKED** until Razorpay live verified |

---

## SECURITY VERIFICATION

| Item | Classification |
|------|----------------|
| QA-SEC-001 HttpOnly/BFF | **OPEN — HIGH RISK — DEFERRED SECURITY EPIC** (not blocking COD soft-launch if disclosed) |
| RBAC / IDOR / escalation (Qa11) | **VERIFIED** via regression |
| Customer→admin 403 | **VERIFIED** (QA-15 smoke) |
| Production stub refusal | **VERIFIED** (code + unit) |
| Local APP_DEBUG=true | **BLOCKING for production deploy** until flipped |
| “100% secure” | **Not claimed** |

---

## PERFORMANCE VERIFICATION

| Item | Classification |
|------|----------------|
| QA-PERF-011 preview race | **FIXED / VERIFIED** (QA-13 + regression) |
| QA-PERF-010 analytics scan | **ACCEPTED RISK** at current scale / OPEN for growth |
| Controlled load 10/25/50 | **UNVERIFIED** |
| Large-DB EXPLAIN | **UNVERIFIED** |

---

## DATABASE VERIFICATION

- Connectivity + readiness PASS  
- No QA-15 migration required  
- Spot consistency on COD orders PASS  
- Exhaustive orphan/negative inventory audit: **UNVERIFIED**

---

## CROSS-PLATFORM VERIFICATION

| Flow | Result |
|------|--------|
| Cart dual-session | **PASS** (QA-15) |
| Order customer→admin | **PASS** |
| Admin fulfill→customer | **PASS** (QA-14) |
| 4-UI simultaneous | **UNVERIFIED** |

---

## FEATURE PARITY

| Gap | Classification |
|-----|----------------|
| QA-ADM-002 Admin Mobile thinner | **INTENTIONAL / BY DESIGN** |
| Admin Mobile returns/analytics/users | **INTENTIONAL** (Web primary) |
| Razorpay UI live | **REQUIRED for paid launch** — currently UNVERIFIED |
| Customer Mobile returns/reviews | **WORKING** (QA-07) API/unit; device UI UNVERIFIED |

---

## AUTOMATED TEST RESULTS

| Suite | Result |
|-------|--------|
| PHPUnit Qa02–14 + Phase | **162 passed**, 754 assertions, 0 failed |
| Customer Web unit | PASS |
| Admin Web unit | PASS |
| Customer Flutter | PASS |
| Admin Flutter | PASS |
| composer audit | No advisories |

---

## REAL DEVICE RESULTS

| Check | Result |
|-------|--------|
| Device attached `2d3714f` | PASS |
| LAN API from device | PASS |
| Interactive Customer/Admin Mobile golden | **UNVERIFIED** |

---

## BUILD / LINT / ANALYZE

| Check | Result |
|-------|--------|
| Web/Admin builds | PASS (QA-14; unchanged this phase) |
| Flutter analyze | info-level pre-existing debt only |
| Customer debug APK | PASS (QA-14) |

---

## REGRESSION RESULTS

`php artisan test --filter='Qa14|Qa13|…|Qa02|Phase'`  
**162 tests · 754 assertions · 0 fail · 0 skip recorded**

(No separate Qa15* PHPUnit file — acceptance is evidence + docs; golden coverage remains Qa14GoldenJourneyTest.)

---

## OPEN BUGS

None newly opened as CRITICAL/HIGH product defects in QA-15.

Carry-forward security: **QA-SEC-001 OPEN**.

---

## UNVERIFIED ITEMS

- Razorpay live + webhook end-to-end  
- Interactive Customer Mobile golden UI  
- Interactive Admin Mobile fulfill UI  
- Full Customer/Admin Web Playwright journeys  
- Controlled load / P95/P99  
- Large EXPLAIN  
- 4-UI concurrency  
- Production backup/monitoring/restore on deploy host  

---

## ACCEPTED RISKS

- QA-PERF-010 at current inventory scale  
- Soft-launch with Web JWT localStorage **if client accepts SEC-001 disclosure**  
- Local demo with `APP_DEBUG=true` (must not ship to production as-is)  

---

## INTENTIONAL GAPS

- QA-ADM-002 Admin Mobile ops scope  
- No guest checkout (auth required by design)  

---

## BLOCKED ITEMS

- **Production paid (Razorpay) checkout** — BLOCKED until live verification  
- **Production deploy with APP_DEBUG=true** — BLOCKED until hardened  

---

## PRODUCTION CONFIGURATION

| Item | Local RC | Production requirement |
|------|----------|------------------------|
| APP_ENV | local | production |
| APP_DEBUG | true | **false** (mandatory) |
| Razorpay keys | empty | set + webhook secret |
| JWT secret | set | unique strong secret |
| CORS | localhost + LAN | production origins only |
| HTTPS | not verified | required |
| Queue/schedule | local | worker + cron for expired reservations |
| Backup/monitor | UNVERIFIED | required before go-live |

---

## BACKUP / MONITORING

**UNVERIFIED** from this development environment. See handover checklist for required ops items.

---

## CLIENT HANDOVER

See `docs/QA_CLIENT_HANDOVER_CHECKLIST.md`.

---

## FINAL RELEASE DECISION

# YELLOW — CONDITIONAL RELEASE

**Allowed:** COD soft-launch / UAT / client demo after production config hardening.  
**Not allowed without further work:** Public paid Razorpay checkout; claiming security “complete”; claiming device UI fully signed off.
