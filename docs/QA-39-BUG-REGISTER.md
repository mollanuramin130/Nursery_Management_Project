# QA-39 BUG REGISTER

**Date:** 2026-08-15 · **GREEN:** NO · **LIVE:** OUT OF SCOPE

---

## FIXED

### QA-39-001 — Snackbar "View Cart" truncated on Vivo
- **Severity:** P1 · **Client:** Customer Mobile · **Device:** vivo 1951  
- **Steps:** Add to cart on PDP → observe snackbar action  
- **Expected:** Full readable action  
- **Actual:** "View Ca"  
- **Root cause:** Long SnackBarAction on narrow width + FAB overlap  
- **Fix:** Action label `Open` + length guard in `AppFeedback`  
- **Status:** **FIXED** (device evidence post-fix shows "Open")

### QA-39-002 — Debug FAB overlapped snackbar
- **Severity:** P2 · **Fix:** FAB `bottom: 200`  
- **Status:** **FIXED** (DEBUG-only residual risk remains)

### QA-39-003 — Order detail dual bottom chrome
- **Severity:** P1 · **Fix:** Move Return/Reorder/Cancel into list body; `ShellNavPolicy.preferInBodyActions`  
- **Status:** **FIXED** (code; device re-check UNVERIFIED)

### QA-39-004 — Wishlist remove old-list flash
- **Severity:** P1 · **Root cause:** `_reload` called `bootstrap(loading:true)` racing optimistic removes  
- **Fix:** Remove bootstrap from soft reload; `bootstrap(silent:)`  
- **Status:** **FIXED** (code; device re-observe UNVERIFIED)

### QA-39-005 — Snackbar clearance same for shell vs fullscreen
- **Severity:** P1 · **Fix:** Path-aware clearance via `ShellNavPolicy` + `StickyCommerceBar.clearance`  
- **Status:** **FIXED** (code; latest APK reinstall UNVERIFIED)

---

## OPEN / INTENTIONAL / UNVERIFIED

| ID | Class | Notes |
|----|-------|-------|
| QA-39-006 | INTENTIONAL | Cart sticky Checkout + shell tabs (compact sticky) |
| QA-39-007 | LOW / DEBUG | FAB vs content residual |
| QA-39-008 | PRE-EXISTING | Sample/category image mismatches |
| QA-39-009 | UNVERIFIED | Wishlist remove flicker after fix on device |
| QA-39-010 | UNVERIFIED | COD + Razorpay TEST on Vivo |
| QA-39-011 | UNVERIFIED | Admin Mobile full matrix |
| QA-39-012 | UNVERIFIED | Web responsive re-walk |
| UPI settle | BLOCKED | Provider/app settle out of scope |
| LIVE | OUT OF SCOPE | — |
