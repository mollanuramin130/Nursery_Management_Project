# PHASE 14 — Soft Launch Report

**GreenLeaf Nursery — Controlled Soft Launch & Production Monitoring**  
**Report date:** 2026-08-12  

## Final status

# PAUSE LAUNCH — ISSUES REQUIRE FIXING

**Reason:** Phase 13 CRITICAL blockers remain open. No verified production environment, no real-user soft-launch traffic, and no production baseline metrics were available. Fabricating success metrics is forbidden.

This phase delivered **operational tooling and documentation** so a soft launch can proceed once blockers clear — it did **not** execute a live public soft launch.

---

## 1. Launch date

**Not started.** Soft launch Stage 0 (internal production verification) is blocked.

## 2. Environment

| Env | Status |
|-----|--------|
| Local / CI | Available (API tests) |
| Staging host | **NOT VERIFIED** |
| Production host | **NOT VERIFIED** |

## 3. Traffic stage

**None** (pre–Stage 0). Rollout plan documented in `PRODUCTION_MONITORING_RUNBOOK.md`.

## 4. Production baseline

| Metric | Value |
|--------|-------|
| API latency p50/p95 | **NOT AVAILABLE** |
| API error rate | **NOT AVAILABLE** |
| HTTP 4xx/5xx | **NOT AVAILABLE** |
| DB CPU / connections | **NOT AVAILABLE** |
| Queue depth / latency | **NOT AVAILABLE** |
| Storage usage | **NOT AVAILABLE** |
| Website RUM | **NOT AVAILABLE** |
| Android crash rate | **NOT AVAILABLE** |
| Payment success rate | **NOT AVAILABLE** |
| Order creation rate | **NOT AVAILABLE** |

**Needs:** Host metrics + uptime monitor + access logs after Stage 0.

## 5–7. API / Website / Android performance & stability

| Surface | Soft-launch evidence |
|---------|----------------------|
| API automated tests | PASS locally (regression suite) — not a prod baseline |
| Website prod build | PASS (Phase 13) — live RUM **NOT AVAILABLE** |
| Android release assert | Code PASS; signed prod AAB **NOT VERIFIED** |

## 8–10. Payment / Order / Inventory

| Area | Status |
|------|--------|
| Payment E2E on staging/prod | **NOT VERIFIED** |
| Order funnel monitoring | Admin KPIs ready when DB has data |
| Inventory consistency under load | **NOT VERIFIED** on production |

## 11–13. Queue / Email / Push

| Area | Status |
|------|--------|
| Queue worker on host | **NOT VERIFIED** |
| Email delivery | **NOT VERIFIED** |
| Push / FCM | **NOT VERIFIED** |

Dashboard now surfaces **failed_jobs** count from live DB when table exists.

## 14. Security events

No production security event stream verified. Auth throttles + audit logs exist in code. Live review: **NOT VERIFIED**.

## 15. Incidents

No live soft-launch incidents. Pre-launch blockers logged in `PRODUCTION_ISSUE_BACKLOG.md` (P13-C1…C8, P14-I1).

## 16. Customer feedback

No real soft-launch customers. Process: `CUSTOMER_SUPPORT_PLAYBOOK.md` + backlog for feedback intake.

## 17. Data-quality issues

No production data audited (no prod DB access in this phase). Do not auto-repair. When live: report first, then auditable fixes.

## 18. Financial reconciliation

**NOT VERIFIED** — requires Razorpay settlement reports vs `orders`/`payments`/`refunds`.

## 19. Infrastructure observations

Architecture remains Hostinger/VPS-oriented; Docker/CI absent. Capacity scaling: wait for evidence.

## 20. Fixes / improvements deployed in Phase 14

| Change | Purpose |
|--------|---------|
| Admin dashboard ops KPIs | Failed payments/orders, delivery failed, open returns, failed jobs + revenue definition |
| Web/Admin/Android `X-Request-Id` | Correlate client → API debugging |
| Monitoring runbook, issue backlog, improvement backlog, support playbook | Operate soft launch safely |

## 21. Remaining risks

Same CRITICAL set as Phase 13 plus: expanding traffic without baselines; payment/order drift if webhooks misconfigured; queue silent failure if worker not supervised.

## 22. Launch recommendation

1. Clear `PRODUCTION_ISSUE_BACKLOG` CRITICAL rows  
2. Complete Stage 0 internal prod smoke (`PRODUCTION_LAUNCH_CHECKLIST` + `PHASE_13_STAGING_QA`)  
3. Enter Stage 1–2 soft launch under `PRODUCTION_MONITORING_RUNBOOK`  
4. Expand only when expansion criteria are met  

**Do not** declare full public launch.

---

## Scorecard

| System | Status |
|--------|--------|
| API (code/tests) | PASS (local) / production **NOT VERIFIED** |
| Website (build) | PASS (local) / production **NOT VERIFIED** |
| Android | PARTIAL (guards) / release **NOT VERIFIED** |
| Admin (build + ops KPIs) | PASS (local) / production **NOT VERIFIED** |
| Database | Migrations PASS / prod **NOT VERIFIED** |
| Authentication | Code PASS / prod **NOT VERIFIED** |
| Payments | Code PASS / prod **NOT VERIFIED** |
| Orders | Code PASS / prod **NOT VERIFIED** |
| Inventory | Code PASS / prod **NOT VERIFIED** |
| Email | **NOT VERIFIED** |
| Push | **NOT VERIFIED** |
| Queue | **NOT VERIFIED** on host |
| Storage | **NOT VERIFIED** |
| Monitoring | Docs PASS / wiring **NOT VERIFIED** |
| Backup | Plan PASS / restore **NOT VERIFIED** |
| Security | Hardening PASS / live **NOT VERIFIED** |
| Performance | Phase 12 baseline docs / prod **NOT AVAILABLE** |
| Analytics | Definitions PASS (`PHASE_5_METRIC_DEFINITIONS`) / prod data **NOT AVAILABLE** |
| Customer Support | Playbook PASS / staffing **NOT VERIFIED** |

---

## Deliverables

- `docs/PRODUCTION_MONITORING_RUNBOOK.md`  
- `docs/PRODUCTION_ISSUE_BACKLOG.md`  
- `docs/POST_LAUNCH_IMPROVEMENT_BACKLOG.md`  
- `docs/CUSTOMER_SUPPORT_PLAYBOOK.md`  
- `docs/PHASE_14_SOFT_LAUNCH_REPORT.md` (this file)  

**STOP after PHASE 14.**
