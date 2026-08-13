# QA-17 BASELINE AUDIT

**Date:** 2026-08-12  
**Source:** QA-16 YELLOW + live repository/runtime  
**Objective:** Validate whether GreenLeaf can move from **YELLOW / CONDITIONAL RELEASE** to **GREEN / PRODUCTION READY**.

---

## 1. Incoming release posture

| Item | State at QA-17 start |
|------|----------------------|
| Release decision | **YELLOW — CONDITIONAL RELEASE** |
| Paid Razorpay | **BLOCKED** (keys empty) |
| QA-SEC-001 | **OPEN** (HttpOnly/BFF not implemented) |
| Prod host evidence | **UNVERIFIED** (`APP_ENV=local`, `APP_DEBUG=true`) |
| Regression (QA-16) | 167 tests / 767 assertions PASS |
| Claim | COD soft-launch viable with disclosures — not GREEN |

---

## 2. P0 gates that must PASS for GREEN

| Gate | Baseline |
|------|----------|
| Razorpay live + webhook matrix | Keys EMPTY → expected **BLOCKED** |
| `nursery:production-readiness --strict` on production | Not runnable as production on this host |
| HTTPS + `APP_DEBUG=false` | Local only |
| Backup/restore | Not yet drilled (planned in QA-17 local isolated DB) |
| Rollback drill on prod-like host | Docs exist; live drill **UNVERIFIED** |
| Customer + Admin Mobile interactive UI | Device `2d3714f` attached; interactive golden **UNVERIFIED** |
| Critical Web UI smoke | Servers may be up; Playwright golden **UNVERIFIED** |
| Workers/cron on prod | Schedule registered locally; prod worker **UNVERIFIED** |
| No unresolved P0/P1 code defects for COD path | Carry-forward from QA-15/16 |
| SEC-001 fixed OR explicit risk acceptance | OPEN unless client accepts |

---

## 3. Open / UNVERIFIED / intentional

| ID / Item | Classification |
|-----------|----------------|
| QA-SEC-001 | OPEN — HIGH residual |
| Razorpay live | BLOCKED / UNVERIFIED |
| QA-PERF-010 | ACCEPTED at current scale |
| QA-ADM-002 | INTENTIONAL Admin Mobile scope |
| Interactive mobile UI | UNVERIFIED |
| Playwright full Web | UNVERIFIED |
| Load / EXPLAIN / 4-UI | UNVERIFIED |
| Production backup/monitoring | UNVERIFIED |

---

## 4. This-host environment (do not treat as production)

| Item | Value |
|------|-------|
| APP_ENV | local |
| APP_DEBUG | true |
| APP_URL | http://localhost:8000 |
| Razorpay KEY/SECRET/WEBHOOK | EMPTY |
| Health local / LAN | 200 |
| Android device | `2d3714f` attached |
| LAN API | `http://192.168.1.3:8000/api/v1` |

---

## 5. Code/docs to preserve

- `ProductionReadinessChecker` + `nursery:production-readiness`
- QA-16 deploy checklist + rollback plan
- Qa02–Qa16 PHPUnit suites
- Final release / bug / handover docs

---

## 6. Planned QA-17 deltas (honest)

1. Re-probe Razorpay — if still empty, keep **BLOCKED** (no fake PASS).  
2. Harden `--strict` so non-production cannot claim strict success.  
3. Local isolated mysqldump → restore drill (not production DB).  
4. Full regression + client unit/analyze evidence.  
5. Document deploy/rollback as verified-by-procedure only where drill ran.  
6. End with GREEN only if all mandatory gates pass — otherwise **YELLOW** or **RED**.

**Baseline verdict before changes:** GREEN **not** achievable on this host without credentials + production profile.
