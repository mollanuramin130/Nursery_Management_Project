# QA-37 Offline Architecture — Customer Mobile

**Date:** 2026-08-13  
**App:** `apps/nursery_app`  
**Principle:** Same UI widgets render Remote **or** Mock/Cache models. No second “offline app.”

---

## Audit summary (pre-implementation)

| Finding | Detail |
|---------|--------|
| No repository layer | Screens called `ApiClient` directly |
| No bundled assets | Images remote-only; placeholders were icons |
| No mock catalog | Unit fakes only |
| Cart/wishlist | Server-backed; cart kept last-known on fetch fail; wishlist optimistic |
| QA-37 network banner | Already present (`NetworkStatusProvider`) |
| Mutations | Checkout / Razorpay / cancel / refund remain **server-authoritative** |

---

## Data flow

```
UI (ProductCard, Home, Shop, …)
        ↓
Provider / Screen state
        ↓
CatalogRepository / CartProvider / WishlistProvider
        ↓
DataSourceResolver
   ├── Remote (ApiClient)     — preferred when ONLINE
   ├── Cache (secure storage) — last good catalog/orders/cart
   └── Mock JSON assets       — bundled contract-compatible fallback
        ↓
Same ProductSummary / HomeFeed / Cart models
```

---

## MockDataMode (DEBUG / QA)

| Mode | Behavior |
|------|----------|
| `auto` | Remote → on transport/5xx → cache → mock |
| `onlineOnly` | Remote only (no silent mock) |
| `mockOnly` | Always assets (QA visual pass) |
| `offlineSimulation` | Force transport failure → fallback path |

Production/release defaults to **`auto`**. Debug panel exposed only when `kDebugMode`.

---

## NetworkSimulation (DEBUG)

`off` · `offline` · `slow` · `apiError` · `timeout` — Dio interceptor; does not require toggling Wi‑Fi.

---

## Read vs mutation

| Class | Offline behavior |
|-------|------------------|
| SAFE_MOCK_READ (home, catalog, PDP, search, categories, offers list, orders list display) | Cache/mock UI |
| LOCAL_PERSIST_OK (wishlist IDs, cart lines, recent searches) | Secure storage; label “Saved on this device” |
| NO_FAKE_SUCCESS (place order, pay, verify, cancel, refund) | Blocked with clear copy; **never** fake success |

---

## Global indicator

Non-blocking shell banner:

- Offline / API unavailable → existing QA-37 banner  
- When serving mock/cache → suffix **“· Showing saved data”**

Bottom nav unchanged (shell routes from QA-36-007).

---

## Admin Mobile

Not mirrored. Customer-only mock catalog. Admin mutations stay server-only.

---

## Security

No secrets in mock JSON. No auth bypass. No fake payment IDs. HttpOnly BFF (Web) untouched.
