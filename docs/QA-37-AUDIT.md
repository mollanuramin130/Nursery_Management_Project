# QA-37 AUDIT — Network Resilience + Offline UX (Phase 1)

**Date:** 2026-08-13 · **Code changes in this phase:** none (audit only)  
**Baseline:** QA-36 COMPLETE · PHPUnit **253 / 1209** · Prior QA-37 parity pass (001–005) already landed  
**GREEN / LIVE:** OUT OF SCOPE

---

## 1. Current architecture

| Client | Transport | Auth | Timeouts |
|--------|-----------|------|----------|
| Customer Mobile | Dio → Laravel `/api/v1` | Bearer + refresh | connect 20s / receive 30s |
| Customer Web | axios → `/api/bff/proxy` | HttpOnly cookies + CSRF (QA-33) | axios 30s; BFF upstream **no timeout** |
| Admin Mobile | Dio | Bearer | same as customer |
| Admin Web | axios BFF | HttpOnly | 30s |
| API | Laravel | JWT | `health` / `health/live` / `health/ready`; throttle 120/min |

Envelope: `{ success, message, data, errors, meta }` — preserve.

---

## 2. Existing network / loading / error handling

**Present**

- Dio/axios map timeout & connection to user strings (partial)
- `ErrorStateView` / `EmptyState` / skeletons / toasts
- Cart keeps last cart on fetch fail; wishlist optimistic remove (QA-36-008)
- Cart `mutating` single-flight (QA-37-002); PDP wish busy (QA-37-003)
- Checkout submission lock; payment status server-authoritative
- Login-time `probeApiHealth` / Web `api-health.ts`
- Session expiry clears wishlist hearts (QA-37-001)

**Missing / weak**

- No connectivity listener / offline banner
- No global ONLINE vs API_UNAVAILABLE distinction
- **CRITICAL:** token refresh `catch` clears session on *any* failure (including network blip)
- Customer Web `unwrapError` ops-facing (“php artisan serve…”)
- BFF `laravelFetch` has no AbortSignal timeout
- Customer Web `ErrorState` component underused
- No safe auto-retry for idempotent GETs; no explicit “Still connecting…”
- Payment overlay can linger if gateway never returns (HIGH UX)

---

## 3. Navigation (carry-forward)

QA-36-007 shell policy remains correct:

- SHOW bottom nav: Home, Shop (+ catalog/search…), Cart, Orders (+ detail), Account (+ wishlist/notifications)
- HIDE: auth, PDP, checkout, address forms

---

## 4. Identified inconsistencies

| Topic | Web | Mobile |
|-------|-----|--------|
| Offline copy | Ops toast | Sanitized snackbar |
| Cart fetch error | Fixed QA-37-004 | ErrorStateView |
| Refresh network fail | Logout | Logout |
| Connectivity UI | None | None |

---

## 5. Proposed solution (minimal)

1. **NetworkKind / NetworkStatus** shared model per client family (not a redesign).  
2. **Fix refresh:** clear session only on auth rejection (401/invalid body), not connection/timeout.  
3. **Banner:** “You're offline” vs “GreenLeaf is temporarily unavailable” + Retry.  
4. **Preserve content:** keep loaded lists; banner only when stale refresh fails.  
5. **Soft retries:** limited backoff for safe GETs / health only — never place-order/pay/refund.  
6. **User-facing HTTP map** (401/403/404/408/409/422/429/5xx).  
7. **BFF upstream timeout** (~30s) with AbortSignal.  
8. **App resume:** lifecycle → health probe → clear banner / soft refresh.  
9. Reuse existing shells, ErrorStateView, EmptyState, payment locks.

---

## 6. Files expected to change

- `apps/nursery_app/lib/core/api_client.dart`, `network_status.dart` (new), `network_errors.dart` (new), `main.dart`, `shell_screen.dart`, banner widget  
- `apps/nursery-web/src/lib/api.ts`, `network-status.ts` (new), `network-errors.ts` (new), `bff-upstream.ts`, `Providers.tsx`, banner component  
- Admin Mobile/Web api clients (refresh + message map)  
- Tests: network error map, refresh-does-not-logout-on-timeout  
- Docs: QA-37-* (network) + preserve parity findings  

---

## 7. Risks

| Risk | Mitigation |
|------|------------|
| False logout | Fix refresh catch classification |
| Double place-order | Keep submission locks; no auto-retry mutations |
| Payment false failure | Timeout ≠ failed; poll/reconcile |
| Aggressive polling | Cap retries; resume-triggered health only |
| Dependency bloat | Prefer browser `onLine` + Dio error signals; avoid heavy offline DB |

---

## 8. Prior QA-37 parity (already FIXED)

QA-37-001…005 remain valid. This audit extends QA-37 into **network resilience** without discarding parity work.
