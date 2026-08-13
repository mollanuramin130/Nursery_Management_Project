# QA-03 Cart Test Matrix

**Date:** 2026-08-12 (closeout with real device)

| Test | API | Customer Web | Customer Mobile | Result |
|------|-----|--------------|-----------------|--------|
| Login | PASS | PASS (token seed after 429) | PASS (session admin@nursery.test) | PASS |
| Add item | PASS | PASS | PASS (pre-existing cart + home) | PASS |
| Update quantity | PASS | PASS | PASS | PASS |
| Remove item | PASS | PASS | PASS | PASS |
| Clear cart | PASS | PASS | N/A this pass | PASS |
| Coupon | PASS | PASS (WELCOME10) | PASS (WELCOME10) | PASS |
| Remove coupon | PASS | PASS | PASS | PASS |
| Free delivery | PASS | PASS | PASS | PASS |
| Below threshold | PASS | PASS | PASS | PASS |
| Exact threshold | PASS (PHPUnit) | N/A | N/A | PASS |
| Above threshold | PASS | PASS | PASS | PASS |
| Discount threshold | PASS | PASS | PASS | PASS |
| Variant ID | PASS | PASS | PASS (mapping + null SKUs) | PASS |
| Stock validation | PASS | UNVERIFIED | UNVERIFIED | PARTIAL |
| Price integrity | PASS | PASS | PASS | PASS |
| Cart ownership | PASS (PHPUnit) | N/A | N/A | PASS |
| Checkout preview | PASS | PASS | PASS | PASS |
| App restart cart restore | N/A | N/A | PASS | PASS |
| Same-account Web↔Mobile sync | N/A | UNVERIFIED | UNVERIFIED | UNVERIFIED |

## Business rule

Free delivery when `(subtotal − discount) >= FREE_DELIVERY_THRESHOLD` (999).  
Cart `shipping_total` is always `0` until checkout selects a method.
