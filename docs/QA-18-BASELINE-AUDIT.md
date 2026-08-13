# QA-18 BASELINE AUDIT

**Date:** 2026-08-12  
**Mode:** Read-only (no code changes during this audit)  
**Incoming release:** QA-17 **YELLOW — CONDITIONAL RELEASE**  
**Objective:** Move toward genuine **GREEN** via payment + production + ops verification — without converting UNVERIFIED → PASS by inference.

---

## 1. Environment snapshot (this host)

| Item | Observed |
|------|----------|
| APP_ENV | `local` |
| APP_DEBUG | `true` |
| APP_URL | `http://localhost:8000` |
| RAZORPAY_KEY / SECRET / WEBHOOK | **EMPTY** |
| PAYMENT_ALLOW_UNSIGNED_WEBHOOKS | `false` |
| QUEUE_CONNECTION | `database` |
| CACHE | `file` |
| `nursery:production-readiness` | exit 0 (local profile) |
| `nursery:production-readiness --strict` | exit **1** (requires production) |
| Device `2d3714f` | attached |
| API / Web / Admin HTTP | 200 |

**This is not a production host.** Do not claim production configuration PASS.

---

## 2. PASS (carry-forward — re-verify in QA-18, do not assume forever)

| Item | Evidence |
|------|----------|
| COD soft-launch path (API) | QA-14/15 live + suites |
| Production `local_stub` refusal | Qa04PaymentStubGuardTest |
| Webhook missing `order_id` does not bind latest payment | Phase21WebhookHardeningTest |
| Strict gate rejects non-production | QA-17 fix + Qa16 test |
| Local isolated backup/restore drill | QA-17 drill DATA_MATCH |
| Deploy / rollback **documentation** | QA-16 checklist + plan |
| Schedule job **registration** | inventory release, subscriptions, marketing |
| QA regression suite | QA-17: 168 / 768 |
| Client unit suites (Web + Flutter) | QA-17 PASS |
| QA-SEC-002…004 | Prior phases FIXED |
| No known unresolved P0/P1 **code** defects for COD path | Final bug register claim |

---

## 3. OPEN

| ID / Item | Notes |
|-----------|-------|
| QA-SEC-001 | Web JWT in localStorage; HttpOnly/BFF not implemented |
| QA-OPS-001 | Prod backup schedule + monitoring on prod host |
| QA-DOC-001 / UX-001 / TYPE-001 | Low / prior |

---

## 4. UNVERIFIED

| Item | Why |
|------|-----|
| Production `--strict` exit 0 | No production host |
| HTTPS public origins | Local HTTP only |
| APP_DEBUG=false on target | Local true |
| Prod CORS lockdown | Local CORS includes localhost |
| Queue **worker process** on prod | Only schedule definitions verified |
| Cron daemon on prod | Schedule list only |
| Config/route cache on prod | Not run as prod deploy |
| Interactive Customer Mobile UI golden | Device attached; interactive not signed this phase |
| Interactive Admin Mobile fulfill UI | Same |
| Playwright Customer/Admin Web journeys | HTTP smoke only historically |
| Controlled load / large EXPLAIN / 4-UI | Never executed |
| Production backup/restore | Local only in QA-17 |
| Live rollback drill | Docs only |
| Monitoring/alerting / failed-job visibility on prod | Local logs only |
| Razorpay LIVE payment (keys empty) | See BLOCKED |

---

## 5. BLOCKED

| Item | Blocker |
|------|---------|
| Razorpay LIVE + webhook matrix | Credentials EMPTY — cannot execute real gateway |
| GREEN release claim | Mandatory gates unmet |
| Public paid checkout go-live | Same |

**Policy:** Do not fabricate payment evidence. Test-mode credentials (if later provided) ≠ LIVE PASS.

---

## 6. Deferred / intentional

| Item | Type |
|------|------|
| QA-ADM-002 Admin Mobile ≠ full Admin Web | INTENTIONAL |
| QA-PERF-010 analytics full inventory scan | ACCEPTED at current scale |
| Guest checkout | BY DESIGN |
| SEC-001 soft-launch | Only with client risk acceptance |

---

## 7. Payment architecture (inspection summary)

- Driver: Razorpay via `PaymentGatewayManager` / `RazorpayGateway`
- Routes: `POST /payments/initiate`, `verify`, `GET /payments/{id}`, `POST /payments/webhooks/{provider}`
- Server amount authority: payment row must match order `grand_total` on finalize
- Stub mode when keys empty (non-production only); production refuses stub create/verify
- Webhook HMAC via `RAZORPAY_WEBHOOK_SECRET`; unsigned only when explicitly allowed off-production
- Existing tests: Qa04 stub guards, Phase21 webhook IDOR hardening, COD/idempotency in Qa13/Qa14

**Provider change:** Not requested. Razorpay remains the payment provider.

---

## 8. QA-18 work plan (post-audit)

1. Keep Razorpay LIVE as **BLOCKED** while keys empty.  
2. Add `Qa18ProductionPaymentTest` covering automated payment/webhook/idempotency/COD/stub/auth paths (local/testing — not LIVE PASS).  
3. Re-confirm readiness command, local backup evidence, health/monitoring probes.  
4. Run full regression + client suites.  
5. Document honestly; release stays **YELLOW** unless all GREEN gates suddenly become available.

**Baseline verdict:** GREEN **not** achievable on this host without credentials + production profile evidence.
