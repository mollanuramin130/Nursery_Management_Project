# QA FINAL RELEASE REPORT — GreenLeaf Nursery Platform

**Date:** 2026-08-13  
**Updated under:** QA-38 (offline sync hardening)

---

## FINAL RELEASE STATUS

# YELLOW — CONDITIONAL RELEASE

**GREEN RELEASE: NO**

| Mode | Allowed? |
|------|----------|
| Local/UAT demo | YES |
| Offline browse (cache/mock) + reconnect sync | YES (QA-38) |
| COD soft-launch | YES (disclosures) |
| Razorpay TEST | YES |
| LIVE Razorpay / LIVE FCM / production | **NO** |
| Claim GREEN | **NO** |

---

## Latest evidence

- QA-38: cache-first · API wins over mock · reconnect soft sync · wishlist flicker fix  
- PHPUnit **253 / 1209** · Customer Flutter **58**  
- Open/unverified: Vivo radio matrix · UPI settle · mobile-data path · QA-ADM-002  

---

## Explicit non-claims

No GREEN · No LIVE · No fake offline orders/payments · Mock is presentation-only  
