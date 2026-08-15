# QA-40 BUG REGISTER

**Date:** 2026-08-15 · **GREEN:** NO · **LIVE:** OUT OF SCOPE

---

## FIXED

### QA-40-001 — Cart snackbar under-clears sticky + tabs
- **Fix:** `ShellNavPolicy.shellHasStickyCommerce` + `AppFeedback` clearance  
- **Status:** **FIXED**

### QA-40-002 — FadeInUp opacity flash on soft refresh
- **Fix:** StatefulWidget; delay Future once in `initState`  
- **Status:** **FIXED**

### QA-40-003 — Wishlist keep-alive stale rows
- **Fix:** Soft `_reload` when provider `ids` diverge  
- **Status:** **FIXED** (code)

### QA-40-004 / QA-40-005 — Orders skeleton + not cache-first
- **Fix:** Keep list + soft updating; `getOrders(onImmediate:)`  
- **Status:** **FIXED**

### QA-40-006 — Inconsistent image widgets
- **Fix:** `ResilientNetworkImage` on cart, orders, order detail, offers, find-your-plant, mini-cart  
- **Status:** **FIXED**

### QA-40-007 — Checkout / primary loading dark-on-muted
- **Root cause:** `onPressed: null` while loading → Material disabled colors  
- **Fix:** Loading keeps primaryDeep + white; primary/danger label color forced white  
- **Status:** **FIXED** (unit + Vivo pixel sample on idle Checkout)

### QA-40-008 — Categories refresh blank
- **Fix:** Keep last categories + soft progress  
- **Status:** **FIXED**

### QA-40-009 — Cart move-to-wishlist loud bootstrap
- **Fix:** `bootstrap(silent: true)`  
- **Status:** **FIXED**

### QA-40-010 — Wishlist heart semantics
- **Fix:** `productName` → “Add/Remove {name} to/from wishlist”  
- **Status:** **FIXED**

### QA-40-011 — Web Button hover/disabled
- **Fix:** hover `primary-hover`; solid disabled tokens  
- **Status:** **FIXED**

### QA-40-012 — Warning/success contrast tokens
- **Fix:** Web/Admin/Mobile warning `#92400e`, success `#1f6b3a`  
- **Status:** **FIXED**

### QA-40-013 / QA-40-017 — MiniCart + cart/wishlist SafeImage + shared Button
- **Status:** **FIXED**

### QA-40-014 — AnalyticsShell refresh flicker
- **Fix:** Keep data; `refreshing` indicator  
- **Status:** **FIXED**

### QA-40-015 — Account reviews/subscriptions error-as-empty
- **Fix:** Preserve rows; error + retry  
- **Status:** **FIXED**

### QA-40-016 — Wishlist web first-load flash
- **Fix:** Skeleton when `loading && !items`  
- **Status:** **FIXED**

---

## OPEN / UNVERIFIED / INTENTIONAL

| ID | Class | Notes |
|----|-------|-------|
| QA-40-018 | OPEN | Admin products/orders list `setLoading(true)` blanks UI |
| QA-40-019 | OPEN | Catalog filter remounts provider → brief skeleton |
| QA-40-020 | OPEN | Mock thumbnails mostly null (placeholders mitigate) |
| QA-40-021 | INTENTIONAL | Admin Mobile online-first (no offline banner) |
| QA-40-022 | UNVERIFIED | Wishlist remove flicker on Vivo |
| QA-40-023 | UNVERIFIED | COD + Razorpay TEST on Vivo |
| QA-40-024 | UNVERIFIED | Admin Mobile full matrix |
| QA-40-025 | UNVERIFIED | Web responsive full re-walk |
| LIVE / UPI settle | OUT OF SCOPE / BLOCKED | — |
