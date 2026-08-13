# QA-17 DEPLOYMENT VALIDATION REPORT

**Date:** 2026-08-12  
**Environment:** Local / UAT-like Mac host — **not** production  

---

## 1. Result

| Area | Result |
|------|--------|
| Documented deploy checklist exists | **PASS** (`docs/QA-16-DEPLOYMENT-CHECKLIST.md`) |
| Clean production-like deploy on prod host | **UNVERIFIED** |
| Migration safety review (no destructive prod migrate) | **PASS** (no prod DB; no new schema in QA-17) |
| Local API health | **PASS** (`/api/v1/health` 200) |
| LAN API health | **PASS** |
| Customer Web HTTP | **PASS** (`:3000` → 200) |
| Admin Web HTTP | **PASS** (`:3001` → 200) |
| Queue schedule definitions | **PASS** (see below) |
| Queue worker process on prod | **UNVERIFIED** |
| Config/route cache on prod | **UNVERIFIED** |
| Mobile production API URL build | **UNVERIFIED** (physical build not re-signed this phase) |

---

## 2. Scheduler (registered locally)

| Schedule | Command |
|----------|---------|
| Hourly | `inventory:release-expired-reservations --hours=24` |
| Every 15 min | `subscriptions:process-due --limit=100` |
| Hourly | `marketing:process-abandoned-carts --limit=100` |
| Daily 10:00 | `marketing:process-post-purchase --limit=100` |

Worker daemon presence on a production host remains **UNVERIFIED**.

---

## 3. Safety rules observed

- No destructive migration against production.  
- No schema change in QA-17.  
- Secrets not printed.  
- Razorpay not faked.

---

## 4. Recommendation

Treat deployment docs as ready for a **staging/production operator runbook**. Re-run the checklist on the real host and attach evidence before claiming deploy **PASS** for GREEN.
