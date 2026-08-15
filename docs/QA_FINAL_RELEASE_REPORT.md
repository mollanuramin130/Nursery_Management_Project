# QA FINAL RELEASE REPORT — GreenLeaf Nursery Platform

**Date:** 2026-08-15  
**Updated under:** QA-42A refresh UX + QA-42 matrix

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

- QA-42A: normal PTR no longer shows saved-data banner; true offline banner only when degraded — `docs/qa42a-mobile/`  
- PHPUnit **253 / 1209** · Customer Flutter **73** · Admin Flutter **29** · Web unit **PASS**  

---

## Explicit non-claims

No GREEN · No LIVE · No fabricated device passes · Offline cannot fake payments/orders  
