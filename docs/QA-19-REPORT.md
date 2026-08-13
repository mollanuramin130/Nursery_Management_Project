# QA-19 REPORT — Live Payment + Production/Staging Release Readiness

**Date:** 2026-08-12  
**Phase:** QA-19  
**Status:** **PARTIAL**  
**Release decision:** **YELLOW — CONDITIONAL RELEASE**  
**GREEN RELEASE:** **NO**

---

## 1. Status

**PARTIAL**

QA-19 audited the payment architecture, blocked accidental `rzp_test_` keys in production readiness, added live-readiness gap tests (`Qa19LivePaymentReadinessTest`), and re-ran full regression (**186 / 918 PASS**).

Razorpay credentials remain **EMPTY**. This host remains `APP_ENV=local` / `APP_DEBUG=true`. Therefore:

- LIVE payment = **BLOCKED**
- LIVE webhook = **BLOCKED**
- Production `--strict` = **UNVERIFIED** (local correctly fails)
- UPI physical-device payment = **BLOCKED / UNVERIFIED**

Automated stub/signed simulation is **NOT** claimed as LIVE PASS.

---

## 2. Bugs fixed

| Item | Fix |
|------|-----|
| Production could accept `rzp_test_` keys in readiness profile | `ProductionReadinessChecker` emits P0 `RAZORPAY_TEST_KEY_IN_PRODUCTION` |
| Missing regression for test-key-in-prod | Qa16 + Qa19 unit coverage |

No commerce payment-path product bugs newly proven this phase.

---

## 3. New bugs

None filed. Environment blockers remain (credentials, prod host).

---

## 4. Payment verification

| Layer | Result |
|-------|--------|
| Credential presence | **NO** |
| LIVE UPI / card / netbanking | **BLOCKED** |
| LIVE webhook | **BLOCKED** |
| Automated provider simulation (Qa18+Qa19) | **PASS** |
| COD regression | **PASS** |
| Client cannot independently mark PAID | **PASS** (architecture: verify/webhook only; clients call `/payments/verify`) |

### QA-19 automated matrix (simulation)

| Case | Result |
|------|--------|
| Duplicate initiate reuses pending | **PASS** |
| Already-paid reject initiate | **PASS** |
| Wrong provider_order_id on verify | **PASS** |
| Cancelled order non-payable | **PASS** |
| Inventory commit once + verify replay | **PASS** |
| Test key blocked in production profile | **PASS** |

---

## 5. Web verification

| Check | Result |
|-------|--------|
| Unit (`test:unit`) | **PASS** |
| Preview mandatory / no cart-total fallback | Prior + unit **PASS** |
| Real browser UPI payment | **BLOCKED** (no keys) |
| Playwright payment UI | **UNVERIFIED** |

---

## 6. Customer Mobile verification

| Check | Result |
|-------|--------|
| Flutter unit tests | **PASS** |
| Device `2d3714f` attached | **PASS** |
| LAN health | **PASS** |
| Interactive UPI on device | **BLOCKED / UNVERIFIED** (no credentials) |

---

## 7. Admin verification

| Check | Result |
|-------|--------|
| Admin Web unit | **PASS** |
| Admin Mobile unit | **PASS** |
| Interactive payment/refund UI golden | **UNVERIFIED** |
| Admin cannot manufacture gateway success | Prior refund stub gates + no admin “force paid” in payment service |

---

## 8. Webhook verification

| Check | Result |
|-------|--------|
| Signed / invalid / duplicate / amount (Qa18) | **PASS** (simulation) |
| LIVE webhook delivery | **BLOCKED** |

---

## 9. Security verification

| Item | Result |
|------|--------|
| QA-SEC-001 | **OPEN** — HttpOnly/BFF not implemented (Option C; not safe partial migrate in QA-19) |
| Stub blocked in production | **PASS** |
| Test keys blocked in production readiness | **PASS** (new) |
| composer audit | **PASS** |
| Residual risk | XSS can steal Web JWTs until SEC-001 or risk acceptance |

**Mitigation while OPEN:** Mobile secure storage; Bearer API; stub blocked in prod; rate limits; no secrets in public env.

---

## 10. Unit tests

**PASS** — Qa19 (6) + Qa16 test-key case + prior suites.

---

## 11. Integration tests

**PASS** — Qa19LivePaymentReadinessTest + Qa18ProductionPaymentTest (automated simulation).

---

## 12. Regression

**PASS** — 186 tests / 918 assertions (`Qa19|Qa18|…|Qa02|Phase`).

---

## 13. Build / lint / analyze

| Check | Result |
|-------|--------|
| API PHPUnit | **PASS** |
| composer audit | **PASS** |
| Web unit | **PASS** |
| Web build | Not re-run (no Web code change) |
| Flutter tests | **PASS** both apps |
| Flutter analyze | Not re-run (no mobile code change) |
| New lint from QA-19 | **N/A** (API-only change) |
| Pre-existing Web lint debt | Documented prior — unchanged |

---

## 14. Database changes

**NONE**

---

## 15. API contract changes

**NONE**

---

## 16. Production readiness

| Item | Result |
|------|--------|
| This host APP_ENV | local |
| APP_DEBUG | true |
| `--strict` on this host | exit **1** (correct) |
| Staging/prod `--strict` exit 0 | **UNVERIFIED** |
| HTTPS / CORS lockdown on prod | **UNVERIFIED** |
| Queue worker / cron on prod | **UNVERIFIED** |

---

## 17. Backup / restore

Local drill (QA-17) **PASS**; production-like **UNVERIFIED**.

---

## 18. Rollback

Plan documented; live drill **UNVERIFIED**.

---

## 19. UNVERIFIED

- Production/staging `--strict` exit 0  
- HTTPS, APP_DEBUG=false, prod CORS  
- Queue workers, cron daemon, cache/storage on prod  
- Production backup/restore + rollback drill  
- Monitoring/alerting on prod  
- Playwright full payment UI  
- Interactive Admin payment UI  
- Load / EXPLAIN  

---

## 20. BLOCKED

- Razorpay LIVE / TEST credentials (empty)  
- LIVE webhook  
- UPI physical-device payment  
- Customer Web real gateway payment  
- GREEN release  

---

## 21. Remaining risks

1. Paid go-live without LIVE evidence.  
2. QA-SEC-001 XSS token theft on Web.  
3. Ops without verified prod backup/workers/monitoring.  

---

## 22. Exact QA-20 readiness

**YES** — when Razorpay TEST or LIVE keys + webhook secret are provisioned in a staging/production-like host, and operators can run:

1. Real payment matrix (incl. UPI device)  
2. `nursery:production-readiness --strict` → 0  
3. Prod backup/restore + rollback evidence  
4. Device + Web interactive payment sign-off  
5. SEC-001 fix **or** formal client risk acceptance  

Suggested QA-20 title: **Live Razorpay cutover + staging GREEN gate**.
