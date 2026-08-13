# QA-27 REPORT — LIVE Razorpay Readiness Gate

**Date:** 2026-08-13  
**Status:** **COMPLETE** (readiness audit)  
**LIVE payment:** **NOT EXECUTED** · **BLOCKED**  
**GREEN:** **NO**

QA-26 TEST evidence (`5526` / `9062` / `pay_TPBCfrCIzFjCKz`) is **not** used as LIVE proof.

---

## 1. Objective

Audit LIVE Razorpay readiness without performing a LIVE charge and without claiming GREEN.

---

## 2. Razorpay LIVE configuration (status only)

| Variable | This host (local) |
|----------|-------------------|
| `RAZORPAY_KEY` | **SET** · class **test** (`rzp_test_*`) |
| `RAZORPAY_SECRET` | **SET** |
| `RAZORPAY_WEBHOOK_SECRET` | **SET** |
| LIVE keys (`rzp_live_*`) on local | **NOT present** (correct for local) |
| Production LIVE credentials | **NOT CONFIGURED** on this host / no prod host verified |

Server-only names remain: `RAZORPAY_KEY` / `RAZORPAY_SECRET` / `RAZORPAY_WEBHOOK_SECRET`.  
No secrets printed; none in `NEXT_PUBLIC_*` / Flutter defines (spot-check: no matches historically).

---

## 3. Environment separation

| Check | Result |
|-------|--------|
| Local `APP_ENV` | **local** |
| Local `APP_DEBUG` | **true** |
| Local `APP_URL` | `http://localhost:8000` (not HTTPS) |
| TEST keys on local | **Allowed** (current) |
| `rzp_live_*` on local | **Absent** — **PASS** (policy) |
| Production rejects `rzp_test_*` | **PASS** (`ProductionReadinessChecker` + `Qa27LiveReadinessGateTest`) |
| Production stub refused when keys empty | **PASS** (Qa27 + Qa04) |
| `PAYMENT_ALLOW_UNSIGNED_WEBHOOKS` | **false** on this host |

---

## 4. Production readiness `--strict`

```text
php artisan nursery:production-readiness --strict
EXIT: 1
```

Message: *Strict production gate requires APP_ENV=production (current: local).*

| Mode | Result |
|------|--------|
| `--strict` on this host | **FAIL** (expected — not production) |
| Non-strict profile on local | EXIT 0 · no P0 findings for **local** profile |
| `--strict` on real production host | **UNVERIFIED** (no production host in this session) |

---

## 5. Webhook architecture

| Check | Result |
|-------|--------|
| Route `POST /api/v1/payments/webhooks/razorpay` | **PASS** (registered) |
| Signature mandatory when secret SET | **PASS** (code + Qa18/Qa27) |
| Unsigned disallowed in `.env` | **PASS** (`false`) |
| Production HTTPS webhook URL | **UNVERIFIED** (only TEST Cloudflare tunnel documented) |
| LIVE Dashboard webhook configured | **UNVERIFIED** |

TEST tunnel (QA-26): `https://age-genres-quick-heading.trycloudflare.com/...` — **not** a LIVE production webhook.

---

## 6. Payment safety (automated)

Verified by existing suites (Qa04/18/21/24/25/26/27) — not by LIVE charge:

- Server-authoritative amount  
- Server-side verify  
- Webhook signature + idempotency  
- Inventory single-commit (QA-26 TEST evidence)  
- Already-paid / failed / cancelled protections  
- Ownership / IDOR coverage in payment suites  
- Stub blocked in production  

**Payment security: PASS** (automated / TEST-proven). **LIVE charge safety: UNVERIFIED.**

---

## 7. Security / QA-SEC-001

| Item | Status |
|------|--------|
| Security/payment regression filter | **PASS** (see counts) |
| QA-SEC-001 (Web JWT in localStorage) | **OPEN** |
| “100% secure” claim | **FORBIDDEN / not claimed** |

---

## 8. Regression (executed)

| Suite | Result |
|-------|--------|
| `Qa27LiveReadinessGateTest` | **7 / 7 PASS** |
| Full Qa02–Qa27 + Phase | **240 tests / 1133 assertions** (238 pass + **2 skipped**) |
| Compare QA-26 | 233 / 1120 → **+7 tests** (Qa27 gate) |
| Focused Qa27/26/25/24/21/18/11/04 | **54 tests** (52 pass + 2 skipped) |
| Customer Web unit | **PASS** |
| Customer Flutter `flutter test` | **PASS** |
| `composer audit` | **PASS** (no advisories) |
| `npm audit --omit=dev` (nursery-web) | **PASS** (0 vulnerabilities) |

---

## 9. Production operations

| Area | Result |
|------|--------|
| HTTPS production `APP_URL` | **UNVERIFIED** |
| Production CORS allowlist | **UNVERIFIED** |
| Queue workers on prod | **UNVERIFIED** (local `database` queue observed only) |
| Scheduler/cron | **UNVERIFIED** |
| Cache/storage prod config | **UNVERIFIED** |
| Monitoring / alerting | **UNVERIFIED** |
| Backup | **UNVERIFIED** |
| Restore drill | **UNVERIFIED** |
| Rollback plan executed | **UNVERIFIED** |

---

## 10. LIVE payment GO / NO-GO

| Decision | Value |
|----------|--------|
| **LIVE PAYMENT** | **BLOCKED** |
| **LIVE READINESS** | **NOT READY** |
| **GREEN** | **NO** |

### Why BLOCKED

1. No production host with `APP_ENV=production` verified (`--strict` cannot pass here).  
2. No `rzp_live_*` credentials configured on a production/staging server (and correctly **absent** from local).  
3. No LIVE HTTPS webhook endpoint verified in Razorpay LIVE Dashboard.  
4. Ops gates (backup/restore/rollback/monitoring) **UNVERIFIED**.  
5. QA-SEC-001 remains **OPEN**.  
6. No authorized LIVE charge (by design this phase).

---

## 11. Controlled LIVE charge procedure (documentation only — DO NOT auto-run)

When a production/staging host is ready and an operator authorizes a **low-value** LIVE charge:

1. On **production** host only: set `RAZORPAY_KEY=rzp_live_…`, `RAZORPAY_SECRET`, `RAZORPAY_WEBHOOK_SECRET` (server `.env`, never commit).  
2. `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`, CORS HTTPS origins only.  
3. `PAYMENT_ALLOW_UNSIGNED_WEBHOOKS=false`.  
4. Point Razorpay **LIVE** webhook to `https://<prod-host>/api/v1/payments/webhooks/razorpay`.  
5. `php artisan config:clear && php artisan config:cache`.  
6. `php artisan nursery:production-readiness --strict` must exit **0**.  
7. Run queue worker.  
8. Place a minimal LIVE order via Customer Web; complete payment.  
9. Record only: GreenLeaf order id, Razorpay order id, Razorpay payment id.  
10. Verify: payment success, webhook, CONFIRMED, inventory once, customer/admin visibility, notifications, idempotency.  

Until steps 1–10 succeed with evidence: **LIVE = BLOCKED**.

---

## 12. Files

- `apps/nursery-api/tests/Feature/Qa27LiveReadinessGateTest.php` (new)  
- `docs/QA-27-REPORT.md` / `QA-27-CLOSEOUT-REPORT.md` / `QA-27-TEST-MATRIX.md`  
- Register / roadmap / final release updates  

No RazorpayGateway / PaymentService redesign.
