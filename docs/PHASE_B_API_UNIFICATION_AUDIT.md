# Phase B — API Unification Audit

**Platform:** GreenLeaf Nursery  
**Scope:** Login / Register / Account / Addresses / Auth state  
**Date:** 2026-08-11  
**Status:** Audit complete — reuse existing `/api/v1` contract; close client gaps

---

## Executive verdict

Phase B is **already implemented** on a single PHP REST surface:

| Layer | Status |
|-------|--------|
| Database | Sufficient (`users`, `refresh_tokens`, `addresses`, `customer_profiles`) |
| API | Authoritative under `/api/v1/auth/*` and `/api/v1/customer/*` |
| Android | Largely complete consumer of those APIs |
| Website | Same APIs for login/register/logout/me/addresses list+create+delete; **gaps** vs Android |

**Do not create** `/mobile/auth/login`, `/addresses` aliases, or a second JSON shape.  
**Authoritative paths remain:**

- `POST /api/v1/auth/login|register|logout|refresh|forgot-password|reset-password`
- `GET /api/v1/auth/me`
- `PUT /api/v1/customer/profile`
- `GET|POST /api/v1/customer/addresses`
- `PUT|DELETE /api/v1/customer/addresses/{id}`

Address JSON fields use project names: `line1`, `line2`, `postal_code`, `is_default` (not `address_line_1`).

---

## 1. Current database structure

| Table | Purpose | Notes |
|-------|---------|-------|
| `users` | Identity | email/phone unique, hashed password, status, soft deletes |
| `roles` / pivots | RBAC | `customer` role on register |
| `customer_profiles` | Profile prefs | includes `default_address_id` **unused by services** |
| `refresh_tokens` | Opaque refresh | SHA-256 hashed; rotation on refresh |
| `addresses` | User addresses | `user_id`, `is_default`, soft deletes |
| `password_reset_tokens` | Forgot/reset | Laravel broker |
| `personal_access_tokens` | — | **Not used** (JWT, not Sanctum) |

**Schema change required for unification?** No new tables. Optional SQL only for documentation / seed notes.

---

## 2. Current authentication API

| Method | Path | Auth |
|--------|------|------|
| POST | `/auth/register` | Public |
| POST | `/auth/login` | Public |
| POST | `/auth/refresh` | Public (refresh body) |
| POST | `/auth/logout` | Bearer |
| GET | `/auth/me` | Bearer |
| POST | `/auth/forgot-password` | Public |
| POST | `/auth/reset-password` | Public |

**Mechanism:** JWT access (`tymon/jwt-auth`) + opaque hashed refresh tokens.  
**Envelope:** `{ success, message, data, errors, meta }`.

Login/register `data`:

```
token_type, access_token, refresh_token, expires_in,
user: { id, name, email, phone, roles[] }
```

`GET /auth/me` `data`: `{ id, name, email, phone, roles[], permissions[] }`

---

## 3. Current Website API calls

| Feature | Endpoint | Wired? |
|---------|----------|--------|
| Login | `POST /auth/login` | Yes |
| Register | `POST /auth/register` | Yes (+ `device.platform=web`) |
| Logout | `POST /auth/logout` | Yes |
| Me / bootstrap | `GET /auth/me` | Yes |
| Refresh | `POST /auth/refresh` | Interceptor |
| Forgot / reset | | **No** |
| Profile update | `PUT /customer/profile` | **No** |
| Address list/create/delete | `/customer/addresses` | Yes |
| Address update / set default | service exists | **UI missing** |
| Return intent after login | | **No** (always `/account`) |
| Token storage | `localStorage` `gl_*` | Yes |
| Guest cart merge | `X-Cart-Token` on login/register | Yes |

---

## 4. Current Android API calls

| Feature | Endpoint | Wired? |
|---------|----------|--------|
| Login / register / logout / me | `/auth/*` | Yes |
| Refresh | interceptor | Yes |
| Forgot password | `POST /auth/forgot-password` | Yes (no reset UI) |
| Profile update | `PUT /customer/profile` | Yes |
| Addresses CRUD + set default | `/customer/addresses` | Yes |
| Return intent | `?redirect=` sanitized | Yes |
| Token storage | `flutter_secure_storage` `gl_*` | Yes |
| Guest cart merge | `X-Cart-Token` | Yes |

---

## 5–7. APIs only on one client / duplicates

| Only Website | Only Android | Duplicates |
|--------------|--------------|------------|
| — | Forgot-password UI, profile UI, address edit/default UI, return intent | **None** — no parallel login APIs |

Both already hit the same `/api/v1` paths for core auth + address list/create/delete.

---

## 8. Different JSON response formats

**None at API level.** Both clients parse the same envelope and field names (`access_token`, `line1`, `is_default`, etc.).

Minor client omissions (safe):

- Website ignores `roles`/`permissions`/`expires_in` in some paths
- Android `User` model omits `roles` (does not break auth)

---

## 9. Different validation rules

| Area | Server (authoritative) | Website UX | Android UX |
|------|------------------------|------------|------------|
| Password | min 8 + confirmed on register | client checks | client checks |
| Address | required name/phone/line1/city/state/postal_code | similar | similar |
| Country | size 2, default `IN` | hardcoded `IN` | hardcoded `IN` |

No conflicting server rules. Client UX validation may differ slightly; server wins.

---

## 10. Different authentication behavior

| Behavior | Website | Android |
|----------|---------|---------|
| Login `device` payload | omitted | `{ platform: android }` |
| Post-login redirect | always `/account` | `?redirect=` or `/account` |
| Demo credentials hint | shown on login page | not shown |
| Forgot password | missing | present |

---

## 11. Different address behavior

| Behavior | Website | Android | API |
|----------|---------|---------|-----|
| List / create / delete | Yes | Yes | Yes |
| Edit | No UI | Yes | PUT supported |
| Set default | only first-create `is_default` | PUT `{ is_default: true }` | Yes |
| Checkout uses same list | Yes | Yes | Yes |

---

## 12. Different business logic

| Rule | Where it lives | Client duplication? |
|------|----------------|---------------------|
| Default uniqueness | `AddressService` clears other defaults | No |
| Guest cart merge | `CartService.mergeGuestCart` on login/register | Clients only send header |
| Password hashing | backend | No |
| Delete default → no auto-promote | backend (implicit) | Clients do not invent rules |

---

## 13. Security issues

| Issue | Severity | Notes |
|-------|----------|-------|
| Cross-user address access | OK | Scoped by `user_id`; wrong id → 404 |
| JWT not blacklisted on logout | Medium | Access JWT valid until TTL; refresh revoked |
| Blocked user with unexpired JWT | Medium | Status not re-checked on every request |
| Tokens in website `localStorage` | Accepted for SPA | XSS risk; httpOnly cookies not used |
| Demo credentials in website login UI | Low | Dev convenience; remove or gate for production |
| Client-supplied `user_id` for addresses | N/A | Not accepted |

---

## 14. Token handling differences

| | Website | Android |
|--|---------|---------|
| Access + refresh keys | `gl_access_token`, `gl_refresh_token` | same names |
| Storage | localStorage | secure storage |
| Refresh on 401 | Yes | Yes |
| Logout clears access+refresh | Yes | Yes |
| Cart token after login | cleared | cleared |

---

## 15. Guest cart / auth interaction

Both:

1. Attach `X-Cart-Token` while guest.
2. Send it on login/register so API merges.
3. Clear local cart token after success.
4. Fetch authenticated cart.

**Limitation if merge fails:** undocumented silent no-op when header missing. Documented in API docs.

---

## 16. Missing endpoints (relative to conceptual doc)

Conceptual `/api/v1/addresses` and `POST .../default` are **not needed** — project already has:

- `/customer/addresses`
- default via `is_default` on create/update

Missing **client** features (API exists):

- Website profile update
- Website forgot password
- Website address edit / set default
- Website return intent
- Both: reset-password UI (API exists)

---

## 17. Required changes (prioritized)

### Critical / High (do now)

1. **Website:** wire `PUT /customer/profile` + account profile UI.
2. **Website:** address edit + set default using existing PUT.
3. **Website:** forgot-password page → `POST /auth/forgot-password`.
4. **Website:** return intent (`?next=` / redirect) for login/register.
5. **Website:** send `device.platform: web` on login (parity with register/Android).
6. **Document** final contract; no second address API.

### Medium

7. API: optionally re-check `user.status === active` on authenticated requests.
8. API: document delete-default behavior (no auto-promote) — keep unless product asks otherwise.
9. Remove or env-gate demo credentials on website login for production.

### Low / defer

10. Reset-password UI (needs email token delivery).
11. Sync unused `customer_profiles.default_address_id` (avoid dual source of truth — leave unused).
12. JWT blacklist on logout.

---

## 18. APIs that can be reused (no change)

All of AUTH-01…07 and CUST-01…05 as implemented.

---

## 19. APIs that need modification

| Change | Why |
|--------|-----|
| Optional active-user guard | Security hardening |
| None for path/JSON rename | Would break both clients |

---

## 20. APIs that should be deprecated

**None.** Do not introduce `/addresses` parallel routes. Do not deprecate `/customer/*`.

---

## Final API contract (authoritative)

See `docs/PHASE_B_API_DOCUMENTATION.md` (created after fixes).

Summary:

```
POST   /api/v1/auth/register
POST   /api/v1/auth/login
POST   /api/v1/auth/logout          (Bearer)
POST   /api/v1/auth/refresh
GET    /api/v1/auth/me              (Bearer)
POST   /api/v1/auth/forgot-password
POST   /api/v1/auth/reset-password
PUT    /api/v1/customer/profile     (Bearer)
GET    /api/v1/customer/addresses   (Bearer)
POST   /api/v1/customer/addresses   (Bearer)
PUT    /api/v1/customer/addresses/{id} (Bearer)
DELETE /api/v1/customer/addresses/{id} (Bearer)
```

Default address: send `"is_default": true` on create/update; backend clears others in a transaction.

---

## Traceability

```
FEATURE              DATABASE           API                         WEBSITE              ANDROID
Login                users              POST /auth/login            store/auth.ts        AuthProvider
Register             users+profile      POST /auth/register         store/auth.ts        AuthProvider
Me                   users+roles        GET /auth/me                bootstrap            bootstrap
Profile              users              PUT /customer/profile       (gap→fix)           ProfileScreen
Addresses            addresses          /customer/addresses*        account/addresses    addresses_*
Default address      addresses.is_default  PUT is_default           (gap→fix)           addresses_screen
Checkout address     addresses          GET + address_id on order   checkout/page        checkout_screen
Guest cart merge     carts              X-Cart-Token on login       api interceptor      Dio interceptor
```

---

## Next steps (implementation order)

1. ~~Audit~~ (this document)
2. Minimal SQL doc file (`database/phase_b_api_unification.sql`) — no destructive schema
3. Optional API active-user check
4. Website parity fixes
5. Android spot-check (already aligned)
6. Independent API tests + cross-client tests
7. `PHASE_B_API_DOCUMENTATION.md` + final report
