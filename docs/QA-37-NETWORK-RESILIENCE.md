# QA-37 Network Resilience Guide

**Date:** 2026-08-13 · **Scope:** TEST / local · **GREEN:** NO

---

## Unified state model

| Kind | Meaning | Typical UI |
|------|---------|------------|
| ONLINE | Reachable | No banner |
| OFFLINE | No device Internet / DNS/socket fail | “You're offline” banner |
| CONNECTING / RECONNECTING | Probe in flight | “Reconnecting…” |
| API_UNAVAILABLE | Transport OK-ish / 502–504 | “GreenLeaf is temporarily unavailable” |
| API_TIMEOUT | Client/upstream timeout | “Connection is taking too long” |
| SERVER_ERROR | 500 | Soft server message |
| RATE_LIMITED | 429 | Wait copy |
| AUTH_EXPIRED | 401 after confirmed auth rejection | Sign in |
| UNKNOWN_ERROR | Other business errors | Toast / inline — **no** global banner |

---

## Client implementation

### Customer Mobile
- `lib/core/network_errors.dart` — classification  
- `lib/providers/network_status_provider.dart` — banner state + capped backoff health probes (1s/2s/4s, max 3)  
- `lib/widgets/network_status_banner.dart` — shell banner (content preserved underneath)  
- `ApiClient.onTransportFailure/Success` + lifecycle resume probe  
- **Refresh:** clear session only on 401/403 response — **not** on connection/timeout  

### Customer Web
- `src/lib/network-errors.ts`  
- `src/store/network-status.ts`  
- `NetworkStatusBanner` + `navigator.onLine` / online events  
- Soft `unwrapError` (no php artisan / API_PROXY_TARGET to shoppers)  
- BFF `laravelFetch` AbortSignal **30s**  

### Admin
- Same refresh transport rule (Mobile Dio + Admin Web axios)  

---

## Safe retry policy

| Operation | Auto-retry |
|-----------|------------|
| Health / reconnect probe | Yes (capped backoff) |
| Idempotent GET after banner Retry | Manual / resume |
| Place order / pay / verify / refund / cancel / stock | **Never** blind auto-retry |

Payment: client timeout ≠ payment failed; server/webhook authoritative (unchanged).

---

## Navigation (unchanged policy)

SHOW bottom nav: Home, Shop (+ browse), Cart, Orders, Account (+ wishlist/notifications)  
HIDE: auth, PDP, checkout, address forms  

---

## Device honesty

Vivo connected (`adb devices`). Real Wi-Fi toggle / API stop can be exercised locally.  
**Mobile-data without `adb reverse`:** still **UNVERIFIED / ENVIRONMENT LIMITATION**.

---

## Offline-first companion (QA-37 extension)

See **`QA-37-OFFLINE-ARCHITECTURE.md`** and **`QA-37-MOCK-DATA-CONTRACT.md`**.

Customer Mobile AUTO path: remote → cache → bundled `assets/mock_data/*`.  
Same UI widgets; DEBUG floating **QA Network Sim** (`mockOnly` / `offline` / `apiError`).  
Place order / Razorpay remain server-authoritative — never faked offline.
