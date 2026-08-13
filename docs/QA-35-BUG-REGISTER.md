# QA-35 Bug Register

**Date:** 2026-08-13 · **Scope:** Comprehensive TEST bug / parity / UI uniformity audit  
**GREEN:** NO · **LIVE:** OUT OF SCOPE

---

## FIXED this phase

### QA-35-001 — Admin Mobile return notification deep link 404
- **Severity:** HIGH · **Component:** Admin Mobile notifications  
- **Repro:** Staff notification with API `route=/returns/{id}` (+ `return_id`, `order_id`)  
- **Expected:** Open related order (ops has no returns module)  
- **Actual:** Followed `/returns/…` → dead route  
- **Root cause:** Deep-link helper trusted `route` before remapping returns  
- **Fix:** Remap any `/returns*` route to `/orders/{order_id}` or `/orders`  
- **Test:** `notification_deep_link_test.dart` QA-35 case  
- **Status:** **FIXED**

### QA-35-002 — Customer PENDING_PAYMENT copy inconsistency
- **Severity:** MEDIUM · **Component:** Customer Web / Mobile / API timeline  
- **Expected:** Customer-facing title **Order placed** (Admin may say Pending payment)  
- **Actual:** Mix of “Payment pending”, “Awaiting payment”, badge “Order placed”  
- **Fix:** Web banner + Mobile banner + API timeline title → **Order placed**; pay CTA copy unchanged  
- **Test:** `Qa35ParityAndTimelineTest`; Web unit assert  
- **Status:** **FIXED**

### QA-35-003 — Admin transition controls showed raw status codes
- **Severity:** MEDIUM · **Component:** Admin Web / Admin Mobile  
- **Fix:** `orderStatusLabel` / `opsStatusLabel` on selects, buttons, confirm dialog; busy guard on Mobile taps  
- **Status:** **FIXED**

### QA-35-004 — Customer Web success banner trusted `paid=1` query alone
- **Severity:** MEDIUM · **Component:** Customer Web order detail  
- **Expected:** Success only when server `status === CONFIRMED`  
- **Actual:** `paid=1` could show success while still PENDING_PAYMENT (alongside pending banner)  
- **Fix:** Gate success banner on `CONFIRMED` only  
- **Status:** **FIXED**

### QA-35-005 — Raw payment status enums in Admin/Customer UI
- **Severity:** MEDIUM · **Component:** Admin Web/Mobile, Customer Web cancel block  
- **Fix:** Shared `paymentStatusLabel` / `opsPaymentStatusLabel` (Paid / Pending / Failed / …)  
- **Test:** Web/Admin unit + Flutter payment label test  
- **Status:** **FIXED**

### QA-35-006 — Customer Web returns list hid fetch errors as empty
- **Severity:** MEDIUM · **Component:** Customer Web `/account/returns`  
- **Fix:** Distinct “Unable to load returns” empty/error state  
- **Status:** **FIXED**

---

## OPEN (carry-forward)

| ID | Summary | Status |
|----|---------|--------|
| QA-ADM-002 | Admin Mobile ops subset | **OPEN** intentional |

---

## LOW deferred (not blocking QA-35)

| ID | Summary | Notes |
|----|---------|--------|
| QA-35-008 | Currency digit/grouping variance Web vs Admin Mobile | Cosmetic; totals correct |
| QA-35-009 | Return-module status title-casing | Non-blocking |

---

## Not defects

| Item | Classification |
|------|----------------|
| True UPI-app settle | **BLOCKED** (provider) |
| Dynamic QR settle | **UNVERIFIED** |
| Mobile-data w/o reverse | **UNVERIFIED** |
| Live HTTPS Secure cookie jar | **UNVERIFIED** (QA-34) |
| Timeline marketing titles (“Preparing your plants”) | **INTENTIONAL** descriptive copy |
| Customer “Order placed” vs Admin “Pending payment” | **INTENTIONAL** role copy |
