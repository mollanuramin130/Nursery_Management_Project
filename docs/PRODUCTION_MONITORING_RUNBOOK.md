# Production Monitoring Runbook — GreenLeaf Nursery

How to watch the soft-launch / production system.  
Complements: `PRODUCTION_MONITORING_PLAN.md`, `PRODUCTION_RUNBOOK.md`, `PHASE_13_STAGING_QA.md`.

---

## 1. What to monitor

| Area | Signal | Where |
|------|--------|-------|
| Availability | `/api/v1/health`, `/health/ready`, `/up` | Uptime ping + logs |
| API errors | 5xx / exception volume | `storage/logs/laravel.log`, host access logs |
| Correlation | `X-Request-Id` response header + `meta.request_id` | Clients now send IDs (web/admin/android) |
| Payments | success/fail, webhook rejects | Razorpay dashboard + Laravel payment logs |
| Orders | status distribution, pending payment stuck | Admin dashboard KPIs + `/admin/orders` |
| Inventory | low stock, negative sellable | Admin inventory + dashboard KPI |
| Queue | `failed_jobs`, worker up | `php artisan queue:failed`, Supervisor |
| Email/Push | delivery rows failed | Admin notifications dashboard + provider |
| Security | failed login spikes, audit anomalies | Auth logs + `audit_logs` |
| Website/Admin | build env, JS console errors | Browser + host |
| Android | crash / wrong API host | Play Console / device logs |

**Never log:** passwords, JWTs, Razorpay secrets, full card data.

---

## 2. Soft-launch stages

| Stage | Audience | Expand when |
|-------|----------|-------------|
| 0 | Operators only (internal prod smoke) | Health + smoke PASS |
| 1 | Internal staff accounts | No CRITICAL incidents 24h |
| 2 | Small trusted customers | Payment + order invariants hold |
| 3 | Limited public | Support load manageable |
| 4 | Broader public | Criteria in §7 |

Do **not** jump stages without evidence.

---

## 3. What constitutes an incident

| Severity | Examples | Response |
|----------|----------|----------|
| CRITICAL | Payments corrupt, orders not creating, auth down, data loss, active security breach | Stop rollout; page owner; fix/rollback |
| HIGH | Checkout down, admin cannot fulfill, widespread 5xx, payment webhook failing | Mitigate within hours |
| MEDIUM | Partial feature break, intermittent API errors | Ticket + schedule fix |
| LOW | Cosmetic UI | Backlog |

Record every CRITICAL/HIGH in `PRODUCTION_ISSUE_BACKLOG.md`.

---

## 4. Investigation steps

1. Note time + `X-Request-Id` / order id / payment id  
2. `curl` health endpoints  
3. Check last deploy tag  
4. Search Laravel log by request id / order number  
5. Razorpay dashboard for payment events  
6. `php artisan queue:failed`  
7. Admin order/payment/inventory screens  
8. Classify + assign owner  

---

## 5. Escalation

1. On-call operator (hosting)  
2. Backend engineer (API/payments)  
3. Business owner (refunds / customer comms)  

Preserve evidence; do not delete failed jobs blindly.

---

## 6. Rollback / verify

Follow `PRODUCTION_RUNBOOK.md` §12. Prefer app rollback; DB restore only with approved plan.  
After recovery: health → smoke → update backlog → resume stage only if stable.

---

## 7. Expansion criteria (evidence-based defaults)

Adjust to real capacity once baselines exist. Until production metrics exist, treat thresholds as **targets after first baseline**, not current claims.

| Signal | Suggested pause if |
|--------|-------------------|
| API 5xx | Sustained > 1% of requests for 15+ min |
| Health | Ready fails 2 consecutive checks |
| Payment success (online) | Sharp drop vs prior day without PSP outage note |
| Queue | Worker down > 15 min OR failed_jobs growing unbounded |
| Checkout | Cannot complete smoke order |
| Inventory | Confirmed oversell / negative sellable |

---

## 8. Daily soft-launch checklist

- [ ] Health ready OK  
- [ ] Admin dashboard: pending payment, failed payments, failed jobs, open returns  
- [ ] Razorpay: no unexplained mismatches  
- [ ] Queue worker running  
- [ ] Scheduler cron present  
- [ ] New CRITICAL/HIGH issues logged  
- [ ] Backup job completed (if scheduled)  

---

## 9. Metric definitions (do not redefine casually)

Revenue / AOV / qualifying orders: `docs/PHASE_5_METRIC_DEFINITIONS.md`.  
Dashboard **Revenue today** = gross `SUM(grand_total)` excl. CANCELLED / PAYMENT_FAILED — **not** profit, **not** refund-adjusted.
