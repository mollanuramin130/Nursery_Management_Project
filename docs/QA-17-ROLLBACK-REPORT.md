# QA-17 ROLLBACK DRILL REPORT

**Date:** 2026-08-12  
**Plan source:** `docs/QA-16-ROLLBACK-PLAN.md`  
**Environment:** Local documentation + readiness validation — **no production rollback executed**

---

## 1. Result

| Check | Result |
|-------|--------|
| Rollback plan documented | **PASS** |
| Application code rollback (git revert / prior release) | **UNVERIFIED** on prod-like host |
| Database rollback strategy documented | **PASS** (forward-fix preferred; restore from backup when needed) |
| Live rollback drill | **UNVERIFIED** |
| Post-rollback payment/order safety | **UNVERIFIED** (requires live paid traffic + backup) |

---

## 2. What is reversible vs not

| Change type | Reversible? | Notes |
|-------------|-------------|-------|
| Application code deploy | Usually yes | Redeploy previous artifact; clear caches |
| Additive indexes / non-destructive migrations | Often yes with care | Prefer forward fix; restore backup if data wrong |
| Destructive schema / data deletes | Not safely reversible without backup | Require pre-change backup |
| Completed customer payments (Razorpay) | Not “rolled back” via app alone | Use provider refunds; avoid duplicate capture |
| Queue jobs mid-flight | Needs drain/restart discipline | Document worker stop before cutover |

---

## 3. QA-17 code change rollback note

QA-17 changed only:

- `ProductionReadinessCommand` strict env gate  
- `Qa16ProductionHardeningTest` assertion  

These are safe to keep; reverting them would re-introduce misleading local `--strict` exit 0.

---

## 4. Classification

**Rollback procedure:** documented **PASS**  
**Rollback drill evidence:** **UNVERIFIED**  

Do not claim GREEN rollback gate from documentation alone.
