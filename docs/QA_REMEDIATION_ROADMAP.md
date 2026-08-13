# QA Remediation Roadmap

**Date:** 2026-08-12 · Derived from QA-00 audit  
**Rule:** Fix root causes in dependency order. API first when contracts change. No fake fixes.

---

## Recommended phases

### QA-01 — Environment + API health integrity
- Smoke: MySQL up, migrate, sample seed, `GET /api/v1/health/ready`
- CORS includes web `:3000` and admin `:3001`
- Document LAN vs emulator API URLs for Flutter
- **Clears:** QA-CFG-001 class failures  
- **Exit:** Login smoke from Web + Mobile against local API PASS

### QA-02 — Authentication messaging + session
- Fix AuthenticationException message mapping (QA-AUTH-001)
- Align Admin Mobile refresh failure → clear tokens (QA-AUTH-004)
- Add reset-password UI Web + Mobile (QA-AUTH-002)
- Align register password hints with API rules (QA-AUTH-003)
- **Exit:** Automated auth feature tests + client E2E login/register/forgot/reset

### QA-03 — Cart totals + coupon consistency
- Unify free-delivery base (QA-CART-001) — **DONE**
- Align `variant_id` typing (QA-CART-002) — **DONE**
- Real-device + Web smoke closeout — **COMPLETE** (`docs/QA-03-CLOSEOUT-REPORT.md`)
- **Exit:** Cart vs preview totals agreement tests + device/Web PASS

### QA-04 — Checkout + payment + order
- Web: refuse place-order without successful preview (QA-CHK-001) — **DONE**
- Web: block `local_stub` in production builds (QA-PAY-001) — **DONE**
- Stale preview protection Web + Mobile — **DONE**
- COD E2E API + real device — **DONE**
- Razorpay live UI — **UNVERIFIED** (empty keys); server verify/webhook unchanged (Phase 21)
- Same-account cross-platform order visibility (API) — **DONE**
- **Exit:** COMPLETE — `docs/QA-04-CLOSEOUT-REPORT.md` · `docs/QA-04-TEST-MATRIX.md`
- **QA-05 ready:** YES

### QA-05 — Inventory + fulfillment + delivery
- Stock lifecycle matrix documented — **DONE** (`docs/QA-05-STOCK-LIFECYCLE.md`)
- Concurrent last-unit + cancel restock + expired reservation fix (QA-05-001) — **DONE**
- Phase6/7/18/20 + Qa05 PHPUnit — **PASS**
- Live Admin fulfillment → customer DELIVERED — **PASS** (`ORD-20260812-00007`)
- Admin/Customer Mobile interactive UI — **UNVERIFIED** (API + unit covered)
- **Exit:** COMPLETE — `docs/QA-05-CLOSEOUT-REPORT.md`
- **QA-06 ready:** YES

### QA-06 — Customer Website regression
- Forensic audit + golden COD journey — **DONE**
- Search stale-response guard (QA-06-001) — **DONE**
- Dead Unsplash image repair + SafeImage (QA-06-002/003) — **DONE**
- Unit + build PASS; full lint pre-existing FAIL documented
- **Exit:** COMPLETE — `docs/QA-06-CLOSEOUT-REPORT.md`
- **QA-07 ready:** YES

### QA-07 — Customer Mobile feature parity
- Implement Returns flow (QA-PAR-001) — **DONE**
- My Reviews account page (QA-PAR-002) — **DONE**
- Real-device LAN journey (login/cart/checkout/COD/orders/returns/reviews) — **DONE**
- Razorpay live — **UNVERIFIED** (empty keys; not claimed PASS)
- **Exit:** COMPLETE — `docs/QA-07-CLOSEOUT-REPORT.md` · `docs/QA-07-TEST-MATRIX.md`
- **QA-08 ready:** YES

### QA-08 — Admin Web regression
- Users & Roles UI (QA-ADM-001) — **DONE**
- Refund stub production gates (QA-PAY-002 Admin layer) — **DONE**
- Admin API golden journey — **DONE**
- QA-SEC-001 — **OPEN** (not claimed fixed)
- Razorpay live — **UNVERIFIED**
- **Exit:** COMPLETE — `docs/QA-08-CLOSEOUT-REPORT.md` · `docs/QA-08-TEST-MATRIX.md`
- **QA-09 ready:** YES

### QA-09 — Admin Mobile parity (ops scope)
- Ops scope confirmed (no forced Web parity) — **DONE**
- Session refresh single-flight + clear tokens — **DONE**
- EnsurePermission OR middleware fix (QA-09-001) — **DONE**
- Physical Android golden journey — **DONE** (`2d3714f`)
- QA-ADM-002 intentional PARTIAL — **CLARIFIED**
- **Exit:** COMPLETE — `docs/QA-09-CLOSEOUT-REPORT.md` · `docs/QA-09-TEST-MATRIX.md`
- **QA-10 ready:** YES

### QA-10 — Cross-platform integration
- Same-account dual-session cart/order/status — **DONE** (`ORD-20260812-00013`)
- Customer+Admin Web/Mobile API visibility — **DONE**
- Idempotency + RBAC + shipment casing — **DONE**
- `Qa10CrossPlatformIntegrationTest` + Qa02–09 regression — **PASS**
- Concurrent 4-UI device journey — **UNVERIFIED**; Razorpay / QA-SEC-001 unchanged
- **Exit:** COMPLETE — `docs/QA-10-CLOSEOUT-REPORT.md` · `docs/QA-10-TEST-MATRIX.md`
- **QA-11 ready:** YES

### QA-11 — Security + RBAC
- Security architecture design — **DONE** (`docs/QA-11-SECURITY-DESIGN.md`)
- QA-SEC-001 decision — **OPEN** (Option C; cookie/BFF deferred)
- Privilege escalation guard (QA-SEC-002) — **DONE**
- Admin open-redirect + Customer session-clear (QA-SEC-003/004) — **DONE**
- `Qa11SecurityTest` RBAC/IDOR/logout — **PASS**
- Qa02–Qa10 regression — **PASS**
- **Exit:** COMPLETE — `docs/QA-11-CLOSEOUT-REPORT.md` · `docs/QA-11-TEST-MATRIX.md`
- **QA-12 ready:** YES

### QA-12 — Performance + reliability
- Baselines + N+1/pagination/index fixes — **DONE**
- Cart sellable batch, inventory SQL paginate, coupons paginate, dashboard low-stock SQL — **DONE**
- Expired-reservation cron empty-order + schedule mutex — **DONE**
- Customer Mobile refresh single-flight + Admin Web search debounce — **DONE**
- `Qa12PerformanceTest` + Qa02–12 regression — **PASS**
- Load test / Razorpay live / SEC-001 — **UNVERIFIED / OPEN** (not falsely closed)
- **Exit:** COMPLETE — `docs/QA-12-CLOSEOUT-REPORT.md` · `docs/QA-12-TEST-MATRIX.md`
- **QA-13 ready:** YES

### QA-13 — Automated regression
- Test inventory + coverage matrix — **DONE**
- Gap suite `Qa13RegressionTest` (negatives, boundaries, idempotency, analytics) — **DONE**
- QA-PERF-011 checkout preview race fix + tests — **DONE**
- QA-PERF-010 formally assessed (OPEN growth) — **DONE**
- Full QA+Phase regression — **PASS** (160)
- **Exit:** COMPLETE — `docs/QA-13-CLOSEOUT-REPORT.md` · `docs/QA-13-TEST-MATRIX.md`
- **QA-14 ready:** YES

### QA-14 — Full E2E business validation
- Live golden COD + fulfill→DELIVERED — **PASS** (`ORD-20260812-00017`)
- Cross-session cart sync — **PASS**
- `Qa14GoldenJourneyTest` + QA+Phase 162 — **PASS**
- Device LAN reachability + debug APK — **PASS**
- Interactive mobile/browser UI + Razorpay live — **UNVERIFIED**
- **Exit:** PARTIAL — `docs/QA-14-CLOSEOUT-REPORT.md` · `docs/QA-14-TEST-MATRIX.md`
- **QA-15 ready:** YES (with disclosed caveats)

### QA-16 — Production release hardening
- Baseline audit — **DONE** (`docs/QA-16-BASELINE-AUDIT.md`)
- Production readiness checker + tests — **DONE** (5 PASS)
- Deploy/rollback/readiness docs — **DONE**
- Razorpay live — **BLOCKED** (credentials unavailable)
- Prod host / device UI / load — **UNVERIFIED**
- Regression 167/767 — **PASS**
- **Exit:** PARTIAL · **YELLOW** (not GREEN)
- **QA-17:** Production host go-live when Razorpay + HTTPS staging available

### QA-17 — Production go-live validation
- Baseline + gate probes — **DONE** (`docs/QA-17-BASELINE-AUDIT.md`)
- Strict readiness gate hardened (non-prod `--strict` fails) — **DONE** + unit test
- Local backup/restore drill — **PASS**; prod drill — **UNVERIFIED**
- Razorpay live — **BLOCKED** (keys still empty)
- Device/Web interactive golden — **UNVERIFIED**
- Regression 168/768 — **PASS**
- **Exit:** PARTIAL · **YELLOW** (not GREEN) — `docs/QA-17-CLOSEOUT-REPORT.md`
- **QA-18 ready:** YES when Razorpay credentials + production/staging host available

### QA-18 — Production payment, deployment & operational readiness
- Baseline — **DONE** (`docs/QA-18-BASELINE-AUDIT.md`)
- `Qa18ProductionPaymentTest` — **PASS** (11); LIVE Razorpay — **BLOCKED**
- Regression 179/873 — **PASS**
- Prod `--strict` / HTTPS / device UI / prod backup — **UNVERIFIED**
- QA-SEC-001 — **OPEN**
- **Exit:** PARTIAL · **YELLOW** — `docs/QA-18-CLOSEOUT-REPORT.md`
- **QA-19 ready:** YES when LIVE keys + production host evidence available

### QA-19 — Live payment + production/staging readiness
- Credentials re-probed — still **EMPTY** → LIVE **BLOCKED**
- Production readiness rejects `rzp_test_` in production — **DONE** + tests
- `Qa19LivePaymentReadinessTest` — **PASS** (6); simulation only
- Regression 186/918 — **PASS**
- SEC-001 — **OPEN**; device UPI / prod `--strict` — **BLOCKED/UNVERIFIED**
- **Exit:** PARTIAL · **YELLOW** — `docs/QA-19-CLOSEOUT-REPORT.md`
- **QA-20 ready:** YES when Razorpay keys + staging/prod host available

### QA-20 — Live payment + production release gate
- Baseline QA-02…19 — **PASS** 186/918
- Credentials still **EMPTY** → LIVE **BLOCKED**
- `Qa20ProductionReleaseGateTest` — **PASS** (7); simulation only
- Regression 193/966 — **PASS**; Qa11 security — **PASS**
- SEC-001 — **OPEN** (risk acceptance template in QA-20 report)
- **Exit:** PARTIAL · **YELLOW** — `docs/QA-20-CLOSEOUT-REPORT.md`
- **QA-21 ready:** YES when credentials + staging/prod host available

### QA-21 — UPI Dynamic QR + Intent + server verification
- Reused PaymentService / RazorpayGateway / webhook / inventory — **DONE**
- `Qa21UpiPaymentTest` — **PASS** (5); Customer Web/Mobile UPI UI — **DONE** (LIVE **BLOCKED**)
- Regression **198 / 1011** — **PASS**; Qa11 — **PASS**
- LIVE Razorpay UPI / webhook / prod `--strict` — **BLOCKED / UNVERIFIED**
- QA-SEC-001 — **OPEN**
- **Exit:** PARTIAL · **YELLOW** — `docs/QA-21-CLOSEOUT-REPORT.md`
- **Next:** configure TEST credentials → real PSP TEST + webhook → then staging/prod GREEN gate

### QA-22 — Push notifications + order status notifications
- Extended Phase 11 with `OrderNotificationDispatcher` — **DONE**
- Customer↔Admin event wiring + deep links + device register — **DONE**
- `Qa22NotificationsTest` — **PASS** (9); regression **207 / 1045** — **PASS**
- LIVE FCM / Firebase client / device push matrix — **BLOCKED / UNVERIFIED**
- QA-SEC-001 — **OPEN**
- **Exit:** PARTIAL · **YELLOW** — `docs/QA-22-CLOSEOUT-REPORT.md`
- **QA-23 ready:** YES when FCM credentials + Firebase client config available

### QA-23 — Firebase FCM LIVE push + real device verification
- Reused QA-22 notification architecture — **DONE**
- `FcmPushGateway` HTTP v1 + legacy + stub — **DONE**
- `Qa23FcmLiveReadinessTest` — **PASS** (6)
- Firebase credentials / `google-services.json` / Flutter Firebase SDK — **EMPTY / MISSING**
- Real-device FG/BG/terminated — **BLOCKED**
- Web push — **DEFERRED**
- Razorpay TEST gate — preserved under `docs/QA-23-*-RAZORPAY.md`
- **Exit:** PARTIAL / BLOCKED · **YELLOW** — `docs/QA-23-CLOSEOUT-REPORT.md`
- **Next:** Configure Firebase SA + client JSON → wire Flutter Firebase → queue worker → device matrix

### QA-24 — Real Razorpay TEST payment + webhook + device
- Credentials probe — **EMPTY** → real TEST **STOPPED**
- `Qa24RealTestPaymentGateTest` — **PASS**
- Regression **224 / 1100** — **PASS**
- Real TEST QR/intent/webhook/device — **BLOCKED**
- Payment architecture unchanged; QA-22 notifications intact
- **Exit:** **BLOCKED** · **YELLOW** — `docs/QA-24-CLOSEOUT-REPORT.md`
- **QA-25 ready:** YES when TEST credentials configured → execute real TEST matrix

### QA-25 — Real Razorpay TEST + UPI E2E / Web UI
- Credentials later **SET**; Customer Web UPI/Razorpay UI integrated
- **Exit:** **PARTIAL** — `docs/QA-25-REPORT.md` / `docs/QA-25-UI-CHECKOUT-REPORT.md`

### QA-26 — Real Razorpay TEST payment E2E
- Real TEST PAID verified: payment **5526**, order **9062**, `pay_TPBCfrCIzFjCKz`, `order_TPBCV5HS098qBW`
- Webhook + verify + CONFIRMED + inventory single sale + notifications — **PASS**
- Regression **233 / 1120** — **PASS** (2 skipped)
- **Exit:** **COMPLETE** (TEST only) · **YELLOW** (not GREEN/LIVE) — `docs/QA-26-CLOSEOUT-REPORT.md`
- **QA-27 ready:** **YES**

### QA-27 — LIVE Razorpay readiness gate
- Readiness audit only — **no LIVE charge**
- `--strict` on local EXIT **1** (expected)
- LIVE credentials / HTTPS webhook / ops — **UNVERIFIED**
- `Qa27LiveReadinessGateTest` — **7 PASS**
- Regression **240 / 1133** — **PASS** (2 skipped)
- **Exit:** **COMPLETE** (gate) · LIVE **BLOCKED** · GREEN **NO** — `docs/QA-27-CLOSEOUT-REPORT.md`
- **QA-28 ready:** YES (authorized LIVE low-value charge on prod host **or** production ops hardening)

---

## Dependency order (do not invert)

```
CONFIG/HEALTH → AUTH → CART TOTAL → CHECKOUT/PAYMENT → ORDER/INVENTORY
 → FULFILLMENT → CUSTOMER WEB → CUSTOMER MOBILE → ADMIN WEB → ADMIN MOBILE
 → SECURITY HARDENING → PERF → AUTOMATION → ACCEPTANCE
```

## Database changes

**QA-00–QA-11:** no mandatory schema for those phase fixes.  
**QA-12:** additive performance indexes — see `docs/QA_DATABASE_CHANGE_PROPOSAL.md` + migration `2026_08_12_180000_qa12_performance_indexes.php`.
