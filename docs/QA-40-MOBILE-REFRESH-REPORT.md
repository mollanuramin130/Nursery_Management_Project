# QA-40 MOBILE REFRESH REPORT

**Date:** 2026-08-15  
**Scope:** Customer Mobile + Admin Mobile  
**Device:** vivo 1951 (`2d3714f`) · API via `adb reverse tcp:8000`  
**GREEN:** **NO** · **LIVE:** **OUT OF SCOPE**

---

## Customer report

> “Mobile application does not work properly and Refresh option does not work.”

## Reproduction (before code changes)

| Screen | Pull-to-refresh | Observed |
|--------|-----------------|----------|
| Home | Present | Soft-update path OK; indicator works |
| Shop/Catalog | Present | Soft when items exist |
| Categories | Present | Soft (prior QA-40) |
| Wishlist | Present | Soft |
| Cart | Present | `fetch()` set `loading=true` → Checkout “Working…” flicker |
| Orders | Present | Soft (prior) |
| Addresses | Present | **`onRefresh` did not await API** → indicator stopped immediately (“does nothing”) |
| Offers / Notifications / Reviews / Returns / Rewards / Subscriptions / Order detail | Present | `setState(_future = _load())` → **FutureBuilder skeleton flash** |
| Product detail | Missing | No pull-to-refresh |
| Search / Account hub | N/A | No list refresh (intentional hub/search) |
| Admin Dashboard/Orders/Inventory | Present | `loading=true` always; UI mostly kept rows when non-empty |

API health pre-test: `200`. Device connected.

---

## Root causes

### QA-40-M-001 — Addresses refresh not awaited (CRITICAL)
`onRefresh: () async => _reload()` where `_reload()` returned **void**.  
`RefreshIndicator` completed instantly without waiting for `/customer/addresses`.

### QA-40-M-002 — FutureBuilder incomplete-Future assignment
Refreshing by assigning a new incomplete `Future` forces `ConnectionState.waiting` → full-screen spinner/skeleton (“refresh clears screen”).

### QA-40-M-003 — Cart `fetch()` always `loading = true`
Pull-to-refresh disabled Checkout / showed Working… even with items visible.

### QA-40-M-004 — Reconnect wishlist bootstrap loud
`main.dart` reconnect called `wishlist.bootstrap` without `silent: true`.

### QA-40-M-005 — Sync generation only wired to Home
Orders / Categories / Wishlist / Catalog did not soft-reload on banner Retry recovery.

### QA-40-M-006 — Admin list providers blanking risk
Dashboard/Orders/Inventory set `loading = true` even when data existed (Orders UI already gated on empty, but loading still noisy).

### QA-40-M-007 — Product detail no pull refresh
Users could not refresh PDP without leaving the screen.

---

## Fixes

| ID | Fix |
|----|-----|
| QA-40-M-001/002 | `soft_future_refresh.dart` + Addresses await + soft assign `completedFuture` |
| QA-40-M-002 | Offers, Notifications, Reviews, Returns, Return detail, Rewards, Subscriptions, Order detail (+ refresh button) |
| QA-40-M-003 | `CartProvider.fetch(soft:)` keeps cart when items exist; cart `onRefresh` uses soft |
| QA-40-M-004 | Reconnect `bootstrap(silent: true)` + `cart.fetch(soft: true)` |
| QA-40-M-005 | `syncGeneration` listeners on Orders, Categories, Wishlist, Catalog |
| QA-40-M-006 | Admin Dashboard/Orders/Inventory soft loading when data present |
| QA-40-M-007 | Product detail `RefreshIndicator` + soft future |

Architecture preserved: CACHE → MOCK → API → cache update. No payment/security changes.

---

## Device evidence

`docs/qa40-mobile/*.png`

Sequence exercised: API ON → pull → API reverse removed → pull (cache/offline banner) → reverse restored → Retry → Cart pull.

---

## Explicit non-claims

No GREEN · No LIVE · No fabricated payments · Admin Mobile full interactive matrix PARTIAL  
