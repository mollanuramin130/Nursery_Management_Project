# QA-17 REPORT — Production Go-Live Validation, Payment, Backup/Restore & Release Gate

**Date:** 2026-08-12  
**Phase:** QA-17  
**Status:** **PARTIAL / BLOCKED for GREEN**  
**Release decision:** **YELLOW — CONDITIONAL RELEASE**

Baseline: `docs/QA-17-BASELINE-AUDIT.md`

---

## 1. Status

**PARTIAL**

QA-17 re-validated go-live gates honestly, hardened the strict production readiness gate, executed a **local** backup/restore drill, and re-ran full automated regression. Mandatory GREEN gates (Razorpay live, production-host `--strict`, interactive device UI, prod backup/rollback drills) remain **BLOCKED** or **UNVERIFIED**.

---

## 2. Release decision

# YELLOW — CONDITIONAL RELEASE

**Not GREEN.** Paid Razorpay production remains **BLOCKED**. COD soft-launch / UAT remains viable with prior disclosures.

---

## 3. Bugs fixed

| ID / item | Fix |
|-----------|-----|
| Misleading `--strict` exit 0 on local | `ProductionReadinessCommand` now fails when `--strict` and `APP_ENV !== production` |
| Missing regression for strict gate | `Qa16ProductionHardeningTest::test_strict_gate_rejects_non_production_env` |

No commerce/payment logic bugs fixed (none newly proven this phase).

---

## 4. Bugs still open

| ID | Status |
|----|--------|
| QA-SEC-001 | **OPEN** — Web JWT localStorage; HttpOnly/BFF not implemented |
| QA-OPS-001 | **OPEN** — prod backups/monitoring not verified on prod host |
| QA-DOC-001 / UX-001 / TYPE-001 | OPEN (low / prior) |

---

## 5. Razorpay / payment verification

| Check | Result |
|-------|--------|
| Credentials available | **NO** |
| Live initiate / verify / webhook | **BLOCKED / UNVERIFIED** |
| Stub refused in production | **PASS** (prior + regression) |
| COD unaffected | **PASS** (regression) |

**Paid production orders remain BLOCKED.**

---

## 6. Production configuration verification

| Check | Result |
|-------|--------|
| This host `APP_ENV=production` | **NO** (local) |
| `APP_DEBUG=false` on this host | **NO** (true) |
| HTTPS public URL | **UNVERIFIED** |
| Non-strict readiness (local profile) | **PASS** exit 0 |
| Strict readiness on this host | **FAIL** exit 1 (correct) |
| Strict on real production | **UNVERIFIED** |

---

## 7. Backup / restore verification

| Scope | Result |
|-------|--------|
| Local isolated drill | **PASS** — see `QA-17-BACKUP-RESTORE-DRILL.md` |
| Production drill | **UNVERIFIED** |

---

## 8. Deployment verification

Documented procedure **PASS**; live prod-like deploy **UNVERIFIED**. See `QA-17-DEPLOYMENT-REPORT.md`.

---

## 9. Rollback verification

Plan documented **PASS**; live drill **UNVERIFIED**. See `QA-17-ROLLBACK-REPORT.md`.

---

## 10. Customer Web verification

| Check | Result |
|-------|--------|
| HTTP home 200 | **PASS** |
| Unit `test:unit` | **PASS** |
| Playwright critical journeys | **UNVERIFIED** |

---

## 11. Admin Web verification

| Check | Result |
|-------|--------|
| HTTP home 200 | **PASS** |
| Unit `test:unit` | **PASS** |
| Playwright / full RBAC UI golden | **UNVERIFIED** |

---

## 12. Customer Mobile physical-device verification

| Check | Result |
|-------|--------|
| Device `2d3714f` attached | **PASS** |
| LAN health | **PASS** |
| Interactive golden (login→COD→orders…) | **UNVERIFIED** |
| Flutter unit tests | **PASS** |

---

## 13. Admin Mobile physical-device verification

| Check | Result |
|-------|--------|
| Device attached | **PASS** |
| Interactive pick→pack→ship UI | **UNVERIFIED** |
| Flutter unit tests | **PASS** |
| API fulfill chain (prior QA-14) | **PASS** (prior evidence retained) |

---

## 14. Security verification

| Item | Result |
|------|--------|
| QA-SEC-001 | **OPEN** — not implemented this phase |
| Prior SEC-002…004 | Retained **PASS** in regression |
| composer audit | **PASS** |

Risk acceptance still required for any public Web soft-launch claim regarding XSS token theft.

---

## 15. Performance / reliability verification

| Item | Result |
|------|--------|
| QA-PERF-010 | ACCEPTED at current scale / OPEN for growth |
| Load 10/25/50 | **UNVERIFIED** |
| Large EXPLAIN | **UNVERIFIED** |
| Checkout preview race / refresh single-flight / COD idempotency | Covered in prior suites — regression **PASS** |

---

## 16. Unit tests

- PHPUnit changed-flow: strict gate **PASS**  
- Web unit Customer + Admin **PASS**  
- Flutter Customer + Admin **PASS**

---

## 17. Integration tests

PHPUnit QA+Phase filter **PASS** (168 / 768).

---

## 18. Negative / edge tests

Strict gate negative path **PASS**. Prior payment/auth/RBAC negatives retained in suite.

---

## 19. Concurrency / idempotency tests

Retained in Qa04/Qa05/Qa10/Qa12/Qa13/Qa14 suites — included in regression **PASS**. No fabricated load results.

---

## 20. Full regression

**PASS** — 168 tests, 768 assertions (`Qa16|Qa14|…|Qa02|Phase`).

---

## 21. Build / lint / analyze

| Check | Result |
|-------|--------|
| API PHPUnit | **PASS** |
| composer audit | **PASS** |
| Web unit | **PASS** |
| Web build | **NOT RE-RUN** this phase (prior phases built; no Web code change) |
| Web lint | Pre-existing failures documented in QA-06 — not reclaimed |
| Flutter test Customer/Admin | **PASS** |
| Flutter analyze | **NOT RE-RUN** this phase (tests PASS; no mobile code change) |

---

## 22. Database changes

**None** in QA-17.

---

## 23. API contract changes

**None** in QA-17.

---

## 24. Operational readiness

| Item | Result |
|------|--------|
| Schedule definitions | **PASS** |
| Worker on prod | **UNVERIFIED** |
| Monitoring/alerting | **UNVERIFIED** |
| Local backup drill | **PASS** |
| Prod backup schedule | **UNVERIFIED** |

---

## 25. UNVERIFIED items

- Production-host config / HTTPS / DEBUG=false / CORS lockdown  
- Production `--strict` success  
- Production backup/restore  
- Live rollback drill  
- Interactive Customer/Admin Mobile UI  
- Playwright full Web journeys  
- Controlled load / large EXPLAIN / 4-UI concurrency  
- Prod queue worker process / alerting  

---

## 26. BLOCKED items

- Razorpay live payment + webhook verification (credentials unavailable)  
- GREEN release claim  

---

## 27. Remaining risks

1. Paid checkout enabled without live verification → revenue/integrity risk (**BLOCKED** correctly).  
2. QA-SEC-001 XSS token theft on Web.  
3. Ops without verified prod backup/worker/monitoring.  
4. Device UI gaps for store-facing sign-off.  

---

## 28. Exact files changed

| File | Change |
|------|--------|
| `apps/nursery-api/app/Console/Commands/ProductionReadinessCommand.php` | `--strict` requires `APP_ENV=production` |
| `apps/nursery-api/tests/Feature/Qa16ProductionHardeningTest.php` | Assert strict fails off production |
| `docs/QA-17-*.md` | New phase evidence |
| `docs/QA_MASTER_BUG_REGISTER.md` | QA-17 note |
| `docs/QA_FEATURE_MATRIX.md` | Date/notes |
| `docs/QA_REMEDIATION_ROADMAP.md` | QA-17 entry |
| `docs/QA_FINAL_RELEASE_REPORT.md` | Updated decision |
| `docs/QA_FINAL_BUG_REGISTER.md` | Updated through QA-17 |
| `docs/QA_CLIENT_HANDOVER_CHECKLIST.md` | Backup local drill note |

---

## 29. Production readiness result

| Profile | Result |
|---------|--------|
| Local non-strict | PASS (no local P0 findings) |
| Local strict | FAIL (by design) |
| Production strict | **UNVERIFIED** |

---

## 30. Final release recommendation

**YELLOW — CONDITIONAL RELEASE** for COD soft-launch / UAT after client acknowledges Razorpay BLOCKED, SEC-001 OPEN, and remaining UNVERIFIED ops/UI gates.

**Do not claim GREEN.**

---

## 31. QA-18 readiness

**YES — with clear entry criteria:**

1. Real Razorpay TEST/LIVE credentials + webhook secret  
2. Staging/production host with `APP_ENV=production`, `APP_DEBUG=false`, HTTPS  
3. Run `nursery:production-readiness --strict` → exit 0  
4. Complete payment matrix + device UI sign-off + prod backup/rollback evidence  
5. SEC-001 remediated **or** signed risk acceptance  

Suggested QA-18 focus: **Production host cutover + Razorpay live matrix + device UI sign-off** (or SEC-001 epic if prioritized).
