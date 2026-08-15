# QA-40 MOBILE BUG REGISTER

**Date:** 2026-08-15 · **GREEN:** NO · **LIVE:** OUT OF SCOPE

---

## FIXED

### QA-40-M-001 — Addresses pull-to-refresh did not await API
- **Severity:** P0 · **Client:** Customer Mobile  
- **Root cause:** `onRefresh: () async => _reload()` with void `_reload`  
- **Fix:** `_reload` returns `Future`; soft path awaits fetch  
- **Status:** **FIXED**

### QA-40-M-002 — FutureBuilder refresh skeleton flash
- **Severity:** P1 · Screens: offers, notifications, reviews, returns, rewards, subscriptions, order detail, addresses  
- **Root cause:** Assign incomplete Future → waiting state  
- **Fix:** `softReplaceFuture` + `completedFuture`  
- **Status:** **FIXED**

### QA-40-M-003 — Cart refresh forces loading Checkout
- **Severity:** P1  
- **Fix:** `fetch({soft: true})` when items present  
- **Status:** **FIXED**

### QA-40-M-004 — Reconnect loud wishlist bootstrap
- **Severity:** P2  
- **Fix:** `silent: true` + soft cart fetch on reconnect  
- **Status:** **FIXED**

### QA-40-M-005 — Retry recovery only refreshed Home
- **Severity:** P1  
- **Fix:** `syncGeneration` soft-reload on Orders, Categories, Wishlist, Catalog  
- **Status:** **FIXED**

### QA-40-M-006 — Admin soft loading
- **Severity:** P2 · Dashboard / Orders / Inventory  
- **Status:** **FIXED** (code)

### QA-40-M-007 — PDP missing pull-to-refresh
- **Severity:** P2  
- **Status:** **FIXED**

---

## OPEN / UNVERIFIED / INTENTIONAL

| ID | Class | Notes |
|----|-------|-------|
| QA-40-M-008 | OPEN | Admin Products/Purchasing/More screens may still hard-load |
| QA-40-M-009 | OPEN | Catalog filter remount brief skeleton (route key) |
| QA-40-M-010 | UNVERIFIED | Full Admin Mobile interactive pull matrix on Vivo |
| QA-40-M-011 | UNVERIFIED | Search/Account hub refresh (no list PTR by design) |
| QA-40-M-012 | UNVERIFIED | COD / Razorpay TEST during refresh (out of payment scope) |
| LIVE | OUT OF SCOPE | — |

---

## Prior QA-40 UI IDs still relevant

QA-40-018…025 from UI polish pass remain as documented in `QA-40-BUG-REGISTER.md`.
