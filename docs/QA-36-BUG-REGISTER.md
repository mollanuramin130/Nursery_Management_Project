# QA-36 Bug Register

**Date:** 2026-08-13 · **Scope:** Stability audit + cross-platform feature parity / navigation / wishlist UX  
**GREEN:** NO · **LIVE:** OUT OF SCOPE

---

## FIXED this phase

### QA-36-001 — Customer Mobile treated `paid=1` as “Order confirmed”
- **Severity:** HIGH · **Component:** Customer Mobile order detail + UPI poll  
- **Status:** **FIXED** (stability pass)

### QA-36-002 — Customer Web `?pay=pending` forced unpaid banner
- **Severity:** MEDIUM · **Component:** Customer Web order detail  
- **Status:** **FIXED**

### QA-36-003 — Invalid order id hung on infinite skeleton
- **Severity:** MEDIUM · **Component:** Customer Web order detail  
- **Status:** **FIXED**

### QA-36-004 — Wishlist fetch errors looked empty; heart double-tap race
- **Severity:** MEDIUM · **Component:** Customer Web wishlist (+ Mobile heart busy)  
- **Status:** **FIXED**

### QA-36-005 — Customer deep links accepted bare `/returns/*`
- **Severity:** MEDIUM · **Component:** Customer Web + Mobile notifications  
- **Status:** **FIXED**

### QA-36-006 — Admin Web order header showed raw payment status
- **Severity:** LOW · **Component:** Admin Web order detail header  
- **Status:** **FIXED**

### QA-36-007 — Customer Mobile bottom navigation missing on browse/account routes
- **Severity:** HIGH · **Component:** Customer Mobile navigation shell  
- **Repro:** Open Catalog, Search, Wishlist, Notifications, or Order detail from Home/Account — bottom tabs (Home/Shop/Cart/Orders/Account) disappear  
- **Expected:** Primary section routes keep shell `NavigationBar`; only intentional full-screen flows hide it  
- **Actual:** Those routes were top-level `GoRoute`s outside `StatefulShellRoute`  
- **Root cause:** Route configuration / shell structure (not per-screen omission)  
- **Fix:** Move catalog/search/offers/campaigns/find-your-plant into Shop branch; nest order detail under Orders; nest account subpages + wishlist + notifications into Account branch. Keep auth, PDP, checkout, address forms outside (sticky bars / focus flows)  
- **Test:** `test/shell_nav_routes_test.dart`  
- **Retest:** PASS  
- **Status:** **FIXED**  
- **Note:** Command draft IDs “QA-36-001 bottom nav” conflicted with existing QA-36-001; assigned **007**

### QA-36-008 — Wishlist removal stale UI / skeleton flicker
- **Severity:** MEDIUM · **Component:** Customer Mobile wishlist (+ Web optimistic remove)  
- **Repro:** Tap Remove on wishlist item — list briefly replaced by loading skeleton (old→skeleton→new flash)  
- **Expected:** Row disappears immediately; no full-list skeleton; restore + error on API failure  
- **Actual:** Success path called `_reload()` which reset `FutureBuilder` into loading → `ProductGridSkeleton`  
- **Root cause:** Refetch race / non-optimistic list rebuild  
- **Fix:** Optimistic local list + provider remove; skeleton only on initial empty load; soft refresh without blanking; Web store optimistic DELETE  
- **Retest:** PASS (code path)  
- **Status:** **FIXED**  
- **Note:** Command draft “QA-36-002 flicker” → assigned **008** to avoid ID collision

---

## OPEN (carry-forward)

| ID | Summary | Status |
|----|---------|--------|
| QA-ADM-002 | Admin Mobile ops subset | **OPEN** intentional |

---

## PARTIAL / intentional gaps (not defects)

| Item | Classification |
|------|----------------|
| Dynamic QR UI on Customer Mobile | **INTENTIONAL** (Web path; Mobile UPI Intent) |
| Variant PDP picker missing Web + Mobile | **PARTIAL** (API supports; both clients lack picker) |
| Customer “Order placed” vs Admin “Pending payment” | **INTENTIONAL** |

---

## Not defects / environment / provider

| Item | Classification |
|------|----------------|
| True UPI-app TEST settlement | **BLOCKED** / **PROVIDER LIMITATION** |
| Dynamic QR settlement | **UNVERIFIED** |
| Mobile-data without `adb reverse` | **UNVERIFIED** / **ENVIRONMENT LIMITATION** |
| Live HTTPS Secure cookie jar | **UNVERIFIED** |
| QA-SEC-001 | **CLOSED** |
| Flutter analyze info/deprecation | **PRE-EXISTING** |
| LIVE Razorpay / LIVE FCM / GREEN | **OUT OF SCOPE** / **NO** |
