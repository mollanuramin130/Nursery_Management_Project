# Phase B — API Unification Implementation Report

**Date:** 2026-08-11  
**Related:** `PHASE_B_API_UNIFICATION_AUDIT.md`, `PHASE_B_API_DOCUMENTATION.md`, `database/phase_b_api_unification.sql`

---

## 1. Existing Phase B architecture

Already a single API surface:

- JWT access + opaque refresh tokens
- `/api/v1/auth/*` + `/api/v1/customer/*`
- Shared envelope `{ success, message, data, errors, meta }`
- Android was largely complete; Website had feature gaps against the same APIs

## 2. Problems discovered

| Problem | Severity | Resolution |
|---------|----------|------------|
| Website missing profile update | High | Wired `PUT /customer/profile` + `/account/profile` |
| Website missing address edit / set default | High | Addresses UI now create/edit/delete/default via same API |
| Website missing forgot-password | High | `/forgot-password` → `POST /auth/forgot-password` |
| Website no return intent after login | High | `?next=` + `sanitizeNext` (parity with Android `redirect`) |
| Website login omitted `device.platform` | Medium | Now sends `{ platform: "web" }` |
| Demo credentials always visible | Low | Dev-only (`NODE_ENV === "development"`) |
| Inactive user could use unexpired JWT | Medium | Middleware `active.user` on auth me/logout + customer routes |
| Conceptual `/addresses` vs `/customer/addresses` | Doc risk | Kept `/customer/addresses` as authoritative |

## 3–4. Database / SQL changes

**No schema CREATE/ALTER required.**  
`database/phase_b_api_unification.sql` documents rules + verification SELECTs only.

## 5–7. APIs standardized / removed / final list

**Standardized (reused):** all AUTH-01…07 and CUST-01…05.  
**Removed/deprecated:** none created; do not add `/addresses` aliases.  
**Final endpoints:** see `PHASE_B_API_DOCUMENTATION.md`.

## 8–9. JSON examples

See documentation. Key address fields: `line1`, `line2`, `postal_code`, `is_default`.

## 10. Website changes

- `store/auth.ts` — login device, forgotPassword, updateProfile
- `lib/auth-redirect.ts` — return intent sanitizer
- Login / register / forgot-password / profile pages
- Addresses: edit, make default, confirm delete
- Checkout/wishlist/orders login CTAs use `loginHref(...)`
- Refresh interceptor skips forgot/reset/register

## 11. Android changes

**None required for unification** — already consumed the same APIs (AuthProvider, address screens, redirect sanitizer, secure storage, X-Cart-Token merge).

## 12. Authentication flow

```
Login/Register (+ X-Cart-Token)
  → API issues access + refresh
  → merge guest cart server-side
  → client stores tokens, clears guest cart token
  → GET /auth/me on bootstrap
Logout → revoke refresh → clear local tokens
401 → POST /auth/refresh → retry
```

## 13. Address flow

```
GET/POST/PUT/DELETE /customer/addresses
Default: PUT { is_default: true }  (backend clears others)
Delete default: no auto-promote
Checkout: same address list → address_id
```

## 14. Checkout integration

Website + Android both use `GET /customer/addresses` and pass `address_id` to preview/orders. No parallel address store.

## 15. Guest cart behavior

Supported via `X-Cart-Token` on login/register. Documented limitation: no merge if header absent.

## 16. Security fixes

- `EnsureUserIsActive` middleware (`active.user`)
- Ownership 404 on foreign address ids (verified)
- Open-redirect sanitization on website `next`
- Demo credentials gated to development

## 17. Tests performed (API)

| Case | Result |
|------|--------|
| Login valid | OK |
| Login invalid | 401 |
| Me with token | OK |
| Me without token | 401 |
| Address list/create/update/delete | OK |
| Set default | OK |
| Foreign address update | 404 |
| Profile update | OK |
| Refresh + logout | OK |
| Weak register | 422 |
| Addresses unauthenticated | 401 |
| Forgot password | OK (generic success) |

## 18. Cross-platform tests

Same user (`asha@example.com`) and same `/customer/addresses` contract:

- Website create/edit/default → API → DB → Android list (same endpoints)
- Android create/default → API → DB → Website list (same endpoints)

Clients share field names and auth headers; no client-specific JSON.

## 19. Build status

| Check | Status |
|-------|--------|
| PHP syntax (new middleware) | OK |
| Website `tsc --noEmit` | OK |
| `flutter analyze` | No issues |
| `flutter test` | 9/9 passed |

## 20. Remaining limitations

1. Access JWT not blacklisted on logout (refresh revoked; access until TTL).
2. Reset-password **UI** not built (API exists; needs email token delivery).
3. `customer_profiles.default_address_id` unused (do not dual-write).
4. Website tokens in `localStorage` (SPA trade-off).
5. Best-effort forgot-password depends on mail configuration.

---

## Architecture (confirmed)

```
DATABASE → PHP REST /api/v1 → Website + Android
ONE database · ONE API · ONE JSON contract · TWO UIs
```
