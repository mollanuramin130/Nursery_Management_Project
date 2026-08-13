# QA-38 Offline Sync Report — Customer Mobile

**Date:** 2026-08-13  
**Mode:** TEST / LOCAL  
**GREEN / LIVE:** **NO / OUT OF SCOPE**  
**Extends:** QA-37 offline-first (not a rewrite)

---

## Authoritative data priority

```
REAL API DATA
    ↓
FRESH LOCAL CACHE  (≤ 5 min)
    ↓
STALE LOCAL CACHE
    ↓
MOCK JSON
    ↓
EMPTY STATE
```

**Mock never overwrites API-origin cache** (`CacheEnvelope.mayWrite`).

---

## Cache-first + reconnect flow

```
SCREEN OPEN
  → peek cache (instant UI)
  → background API
  → success: write envelope(source=remote) → smooth replace
  → failure: keep cache → else mock

NETWORK RESTORED
  → health OK
  → OfflineController.syncGeneration++
  → sync home/catalog (+ cart/wishlist when safe)
  → banner: “Updating…” → clear serving-local
```

---

## Key components

| Piece | Path |
|-------|------|
| Envelope + mayWrite | `lib/data/cache_envelope.dart` |
| Persist | `lib/data/offline_local_store.dart` |
| Resolver | `lib/data/catalog_repository.dart` (`peekHome`, `getHome`, `syncAfterReconnect`) |
| Sync gen | `OfflineController.notifyReconnected` |
| Hook | `NetworkStatusProvider.onReconnected` in `main.dart` |
| Wishlist flicker | epoch + no skeleton over list + no re-seed empty snapshot |
| DEBUG | NetworkSimulation includes `reconnect` |

---

## Mutations (unchanged safety)

| Action | Offline |
|--------|---------|
| Cart add/qty/remove | Local + “Saved on this device” |
| Wishlist heart | Local optimistic |
| Place order / pay | **Blocked** — never faked |

---

## Device

Vivo `2d3714f` connected. Full physical Wi‑Fi OFF matrix: exercise via DEBUG sim; interactive radio matrix **PARTIAL / UNVERIFIED** this session.
