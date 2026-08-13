# QA-16 BASELINE AUDIT

**Date:** 2026-08-12  
**Source:** QA-15 YELLOW + live repository/runtime  
**Release target:** Convert conditional COD soft-launch into **GREEN** production candidate — **only if blockers clear**.

---

## 1. Current blockers (P0)

| ID | Item | Evidence |
|----|------|----------|
| P0-PAY | Razorpay live + webhook | Keys EMPTY on local API; cannot execute live matrix |
| P0-CFG | Production host config | This machine is `APP_ENV=local` `APP_DEBUG=true` — not a production host |
| P0-HTTPS | HTTPS on public origins | Not verified (no production URL in this env) |
| P0-CORS-PROD | Production-only CORS | Local CORS includes localhost (correct for local; prod must differ) |
| P0-BACKUP | DB backup/restore drill | No production/staging host access |
| P0-DEVICE-UI | Interactive Mobile UI | Device attached but interactive golden journeys not executed as UI automation |

---

## 2. Open bugs / security

| ID | Classification |
|----|----------------|
| QA-SEC-001 | OPEN — HIGH RISK — DEFERRED EPIC (HttpOnly/BFF not implemented) |

---

## 3. UNVERIFIED (carry-forward)

- Razorpay live/webhook/duplicate/failure  
- Full Playwright Web UI  
- Customer Mobile interactive UI  
- Admin Mobile pick→pack→ship on device  
- Load 10/25/50 + P95/P99  
- Large EXPLAIN  
- 4-UI concurrency  
- Production backup/restore  
- Production monitoring  

---

## 4. Intentional / accepted

| ID | Type |
|----|------|
| QA-ADM-002 | INTENTIONAL Admin Mobile ops scope |
| QA-PERF-010 | ACCEPTED at current scale (reassess for prod data) |

---

## 5. Current environment (this host)

| Item | Value |
|------|-------|
| API | Laravel local, MySQL healthy |
| APP_ENV | local |
| APP_DEBUG | true |
| Razorpay | EMPTY |
| Schedule | expired reservations + marketing + subscriptions registered |
| Device | vivo `2d3714f` attached |
| Web | :3000 / :3001 available |

---

## 6. Deployment assumptions

- Production uses `.env.production.example` profile (`APP_DEBUG=false`, HTTPS URLs, Redis cache recommended).  
- Mobile production builds use HTTPS API base (never `10.0.2.2` / LAN).  
- Queue: `database` or Redis + worker.  
- Cron: Laravel scheduler for reservation release.  

---

## 7. Tests available

- PHPUnit Qa02–Qa14 + Phase* (~162)  
- Web/Admin `qa-unit-checks`  
- Flutter unit suites  
- Qa04PaymentStubGuardTest (stub refusal)  
- Qa14GoldenJourneyTest (COD fulfill)  

---

## 8. Exact release target for QA-16

1. Harden production readiness **checks + docs + tests** on what this env can prove.  
2. Attempt Razorpay — if credentials absent → remain **BLOCKED**, never PASS.  
3. Re-probe device/network; interactive UI stays UNVERIFIED without operator UI drive.  
4. Honest final decision: expect **YELLOW** until P0 Razorpay + prod host evidence exist.  

---

## 9. Dependency order

```
Baseline audit
→ Production readiness checker + tests
→ Razorpay attempt (BLOCKED if no keys)
→ Config/docs/deploy/rollback
→ Regression + builds
→ Honest GREEN/YELLOW/RED
```

**Do not claim GREEN without Razorpay live PASS + production host verification.**
