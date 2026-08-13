# QA-06 Forensic Audit Findings (pre-code)

**Date:** 2026-08-12 · **App:** `apps/nursery-web`  
**Rule:** Audit complete before modifications.

## Preserved from QA-02–05 (do not regress)

| Area | Status in code |
|------|----------------|
| Auth messages / reset UI | Present (`auth-messages`, `/reset-password`) |
| Cart free-delivery / variant_id | Present in cart store + types |
| Checkout preview gate / no cart total fallback | Present (`checkoutPayableTotal`, `canPlaceOrder`) |
| Production `local_stub` block | Present (`razorpay.ts`) |
| X-Request-Id place order | Present |

## Findings

| ID | Sev | Class | Finding | Proposed action |
|----|-----|-------|---------|-----------------|
| QA-06-001 | MEDIUM | FRONTEND | `SearchBox` suggestion fetch has no in-flight generation guard — slower responses can overwrite newer queries | Fix race with request seq / AbortController |
| QA-06-002 | MEDIUM | FRONTEND / DATA | Next.js Image upstream 404 for some Unsplash URLs in catalog/banners — pages still 200; broken visuals | SafeImage fallback + repair known dead URLs in DB if present |
| QA-06-003 | LOW | UX | ProductCard / gallery lack `onError` image fallback → blank/broken image chrome | SafeImage component |
| QA-MSG-001 | — | PRODUCT | Offers “Coming soon” section is **intentional** for `upcoming_campaigns` | Document; not a Web bug |
| QA-SEC-001 | HIGH | SECURITY | JWT in localStorage | Defer (out of QA-06 scope) |
| Guest cart | — | WORKING | Merge via `X-Cart-Token` on login; token cleared after | Document actual behavior |
| Razorpay live | — | ENV | Keys empty | UNVERIFIED |

## Intentional / not bugs

- Offers page live campaigns + coupons when API returns data
- Shipment lowercase vs order UPPER (QA-05)
- Customer Mobile Returns → QA-07

## Golden journey plan

Login Asha → shop → PDP → cart → coupon → checkout preview → COD → order detail/tracking → cancel if allowed → account.
