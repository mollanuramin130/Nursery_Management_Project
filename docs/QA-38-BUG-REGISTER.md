# QA-38 Bug Register

**Date:** 2026-08-15 · **GREEN:** NO · **LIVE:** OUT OF SCOPE

---

## FIXED — Offline sync (2026-08-13)

### QA-38-001 — Cache lacked metadata / stale distinction
- **Severity:** HIGH · **Fix:** `CacheEnvelope`  
- **Status:** **FIXED**

### QA-38-002 — Mock JSON could overwrite fresher API cache
- **Severity:** CRITICAL · **Fix:** `CacheEnvelope.mayWrite`  
- **Status:** **FIXED**

### QA-38-003 — Reads were API-first (blank wait) not cache-first
- **Severity:** HIGH · **Fix:** peek + soft Updating  
- **Status:** **FIXED**

### QA-38-004 — Reconnect did not soft-refresh screens
- **Severity:** HIGH · **Fix:** `onReconnected` → syncGeneration  
- **Status:** **FIXED**

### QA-38-005 — Wishlist remove brief old-list flash
- **Severity:** MEDIUM · **Fix:** list epoch / no reseed  
- **Status:** **FIXED**

### QA-38-006 — DEBUG missing reconnect control
- **Severity:** LOW · **Fix:** `NetworkSimulation.reconnect`  
- **Status:** **FIXED**

---

## FIXED — UI polish (2026-08-15)

### QA-38-007 — Toast overlapped sticky commerce CTAs (Customer Web)
- **Severity:** CRITICAL · **Screens:** PDP, Checkout review  
- **Fix:** `--sticky-cta-h` + ToastViewport offset; set on mount  
- **Status:** **FIXED** (device UNVERIFIED)

### QA-38-008 — Network banner covered sticky header
- **Severity:** HIGH · **Fix:** relative banner, readable warning ink  
- **Status:** **FIXED**

### QA-38-009 — Wishlist heart no active red (Customer Web)
- **Severity:** HIGH · **Fix:** wishlist tokens + chip contrast  
- **Status:** **FIXED**

### QA-38-010 — Disabled non-primary buttons looked enabled
- **Severity:** HIGH · **Fix:** shared disabled opacity on Button  
- **Status:** **FIXED**

### QA-38-011 — Inactive wishlist heart brand green (Mobile)
- **Severity:** HIGH · **Fix:** `AppColors.wishlistInactive`  
- **Status:** **FIXED**

### QA-38-012 — Snackbar covered bottom navigation (Mobile)
- **Severity:** HIGH · **Fix:** AppFeedback bottom margin +72 + viewPadding  
- **Status:** **FIXED** (device UNVERIFIED)

### QA-38-013 — Admin soft toasts low contrast
- **Severity:** HIGH · **Fix:** solid semantic toast fills  
- **Status:** **FIXED**

### QA-38-014 — Admin Mobile snackbar vs NavigationBar
- **Severity:** HIGH · **Fix:** snackBarTheme insetPadding  
- **Status:** **FIXED**

### QA-38-015 — Sale badge used warning amber
- **Severity:** MEDIUM · **Fix:** `sale` tone Web + Mobile  
- **Status:** **FIXED**

### QA-38-016 — Wishlist busy washed out active red
- **Severity:** MEDIUM · **Fix:** `WishlistHeart.busy` soft alpha  
- **Status:** **FIXED**

### QA-38-017 — Rating star unused gold token
- **Severity:** MEDIUM · **Fix:** `--color-rating` on ProductRating  
- **Status:** **FIXED**

### QA-38-018 — Admin login loading low visibility
- **Severity:** MEDIUM · **Fix:** spinner + disabled button colours  
- **Status:** **FIXED**

### QA-38-019 — Debug network FAB over bottom chrome
- **Severity:** LOW · **Fix:** FAB bottom 160  
- **Status:** **FIXED**

### QA-38-020 — Wishlist header count green-on-green
- **Severity:** LOW · **Fix:** red count chip  
- **Status:** **FIXED**

---

## OPEN / UNVERIFIED / BLOCKED

| Item | Class |
|------|-------|
| Vivo physical UI matrix | **UNVERIFIED** |
| Cart/order-detail dual bottom chrome (Customer Mobile) | **OPEN** MEDIUM |
| Offline banner missing on PDP/checkout/auth | **OPEN** MEDIUM |
| UPI-app settle | **BLOCKED** |
| QA-ADM-002 | **OPEN** intentional |
| Razorpay SDK overlay hang | **PRE-EXISTING** |
