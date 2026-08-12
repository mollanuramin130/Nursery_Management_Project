# Phase B — API Documentation (Unified Contract)

**Base:** `/api/v1`  
**Envelope:** `{ success, message, data, errors, meta }`  
**Auth:** `Authorization: Bearer <access_token>`  
**Guest cart merge:** send `X-Cart-Token` on login/register

Website and Android **must** use these endpoints only. Field names below are authoritative (`line1`, not `address_line_1`).

---

## AUTH-01 Register

`POST /auth/register` — public

**Request**

```json
{
  "name": "Nur Amin",
  "email": "nur@example.com",
  "phone": "+919999999999",
  "password": "Secret@123",
  "password_confirmation": "Secret@123",
  "device": { "platform": "web" }
}
```

`device.platform`: `web` | `android` | `ios`

**Success 201**

```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "token_type": "Bearer",
    "access_token": "...",
    "refresh_token": "...",
    "expires_in": 3600,
    "user": {
      "id": 1,
      "name": "Nur Amin",
      "email": "nur@example.com",
      "phone": "+919999999999",
      "roles": ["customer"]
    }
  },
  "errors": null,
  "meta": {}
}
```

**Errors:** 422 validation, 409 duplicate email/phone

---

## AUTH-02 Login

`POST /auth/login` — public · throttle 10/min

**Request**

```json
{
  "email": "asha@example.com",
  "password": "Secret@123",
  "device": { "platform": "android" }
}
```

**Success 200** — same token+user shape as register.

**Errors:** 401 invalid credentials / inactive account, 422 validation

---

## AUTH-03 Refresh

`POST /auth/refresh` — public · throttle 30/min

**Request:** `{ "refresh_token": "..." }`  

**Success:** `{ token_type, access_token, refresh_token, expires_in }` (no user)

Inactive users cannot refresh.

---

## AUTH-04 Logout

`POST /auth/logout` — Bearer + `active.user`

**Request:** `{ "refresh_token": "...", "all_devices"?: false }`  

**Success:** `data: null`  
Revokes refresh token(s). Access JWT remains valid until TTL (documented limitation).

---

## AUTH-05 Forgot password

`POST /auth/forgot-password` — public · throttle 5/min

**Request:** `{ "email": "..." }`  

**Success:** always generic message (`data: null`) — does not reveal whether email exists.

---

## AUTH-06 Reset password

`POST /auth/reset-password` — public · throttle 5/min

**Request:** `{ "email", "token", "password", "password_confirmation" }`  

**Success:** `data: null` · revokes all refresh tokens for user.

---

## AUTH-07 Me

`GET /auth/me` — Bearer + `active.user`

**Success**

```json
{
  "success": true,
  "message": "...",
  "data": {
    "id": 1,
    "name": "Asha Kumar",
    "email": "asha@example.com",
    "phone": "9876543210",
    "roles": ["customer"],
    "permissions": []
  }
}
```

---

## CUST-01 Profile update

`PUT /customer/profile` — Bearer + `active.user`

**Request:** `{ "name"?: "...", "phone"?: "..." | null }`  

**Success:** `{ id, name, email, phone }`  
Email is not updatable via this endpoint. Phone conflicts → 409.

---

## CUST-02 List addresses

`GET /customer/addresses` — Bearer + `active.user`

**Success `data`:** array of address objects, defaults first.

---

## CUST-03 Create address

`POST /customer/addresses` — Bearer + `active.user` · 201

**Request**

```json
{
  "label": "Home",
  "name": "Asha Kumar",
  "phone": "9876543210",
  "line1": "12 Lake Road",
  "line2": "Near Park",
  "city": "Kolkata",
  "state": "West Bengal",
  "postal_code": "700001",
  "country": "IN",
  "is_default": true
}
```

**Success `data`:** address object (same field names).

When `is_default: true`, backend clears other defaults for that user (transaction).

---

## CUST-04 Update address

`PUT /customer/addresses/{id}` — Bearer + `active.user`

Partial fields allowed (`sometimes`).  
Set default: `{ "is_default": true }`.  
Non-owned id → **404** `"Address not found"`.

---

## CUST-05 Delete address

`DELETE /customer/addresses/{id}` — Bearer + `active.user`

Soft delete.  
**Rule:** deleting the default does **not** auto-promote another address.

---

## Address object (shared)

```json
{
  "id": 15,
  "label": "Home",
  "name": "Asha Kumar",
  "phone": "9876543210",
  "line1": "12 Lake Road",
  "line2": "Near Park",
  "city": "Kolkata",
  "state": "West Bengal",
  "postal_code": "700001",
  "country": "IN",
  "is_default": true
}
```

---

## Guest cart merge

On successful login/register, if request includes `X-Cart-Token` matching an active guest cart, items merge into the authenticated cart. Clients then clear local guest cart token and refetch `/cart`.

---

## Checkout integration

Checkout uses:

1. `GET /customer/addresses` to select `address_id`
2. `POST /checkout/preview` / `POST /orders` with that `address_id`

No separate checkout address table.

---

## Client mapping

| Client | Token store | Platform header |
|--------|-------------|-----------------|
| Website | localStorage `gl_*` | `X-Platform: web` |
| Android | flutter_secure_storage `gl_*` | `X-Platform: android` |

Both parse the same JSON keys.

---

## Deprecated / do not add

- `/api/v1/addresses` (use `/customer/addresses`)
- `/mobile/auth/login`
- Client-specific response shapes
- Dual default via `customer_profiles.default_address_id` (unused)
