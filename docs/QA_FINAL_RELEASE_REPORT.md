# QA FINAL RELEASE REPORT — GreenLeaf Nursery Platform

**Date:** 2026-08-18  
**Updated under:** QA-43 splash/icons + error handling + auth/throttle friction (preserves QA-42A)

---

## FINAL RELEASE STATUS

# YELLOW — CONDITIONAL RELEASE

**GREEN RELEASE: NO**

| Mode | Allowed? |
|------|----------|
| Local/UAT demo | YES |
| Offline browse (cache/mock) + reconnect sync | YES |
| COD soft-launch | YES |
| Razorpay TEST | YES (device Checkout UNVERIFIED) |
| LIVE Razorpay / LIVE FCM / production | **NO** |
| Claim GREEN | **NO** |

---

## Latest evidence

- QA-43 Track 3: global 120/min IP limiter was 429-ing valid logins after shop browsing — env-aware named limiters; production protection kept — `docs/QA-43-REPORT.md`
- QA-43 branding: customer seed→sprout splash; admin leaf+shield+grid — `docs/QA-43-REPORT.md` (Vivo **UNVERIFIED**)
- QA-43: centralized error categories + branded unknown-error UI + helpline `8926627220` — `docs/QA-43-ERROR-HANDLING-REPORT.md`  
- QA-42A: normal PTR no longer shows saved-data banner; true offline banner only when degraded — `docs/qa42a-mobile/`  
- PHPUnit **not re-run this pass** · Customer splash unit **PASS** · Admin splash unit **PASS**  

---

## Explicit non-claims

No GREEN · No LIVE · No fabricated device passes · Offline cannot fake payments/orders  
