# Phase F — Checkout + Payment + Order API Documentation

**Base:** `/api/v1`  
**Envelope:** `{ success, message, data, errors, meta }`  
**Auth:** `Authorization: Bearer <access_token>` (+ `active.user` on payment routes)  
**Idempotency:** send stable `X-Request-Id` on `POST /orders`

Website and Android **must** use these endpoints only. Amounts, stock, coupons, and payment success are **server-authoritative**.

---

## CHECK-01 Preview

`POST /checkout/preview` — Bearer

**Request**

```json
{
  "address_id": 15,
  "shipping_method_id": 2,
  "coupon_code": "WELCOME10"
}
```

**Success 200** — items, address snapshot fields, shipping method, subtotal, discount_total, tax_total, shipping_total, grand_total, currency, free_delivery.

**Rules:** Rebuilds from authenticated cart. Revalidates coupon + stock availability indirectly via product lines. Shipping may be `0` when free-delivery threshold met.

---

## SHIP-01 Shipping methods

`GET /shipping/methods` — public / optional JWT

**Success 200** — list of `{ id, name, code, price, eta_min_days, eta_max_days, … }`

Do not hardcode shipping prices on clients.

---

## ORD-01 Place order

`POST /orders` — Bearer  
**Header:** `X-Request-Id` (recommended)

**Request**

```json
{
  "address_id": 15,
  "shipping_method_id": 2,
  "payment_method": "razorpay",
  "coupon_code": "WELCOME10",
  "notes": "Leave at gate"
}
```

`payment_method`: `razorpay` | `cod`

**Success 201** — order summary (`id`, `order_number`, `status`, totals…).

**Business rules**

| Method | Status after place | Cart | Stock |
|--------|--------------------|------|-------|
| `cod` | `CONFIRMED` | Cleared | Reserve → commit |
| `razorpay` | `PENDING_PAYMENT` | **Retained** | Reserved |

**Errors**

- `400` empty cart / invalid coupon  
- `409 PENDING_ORDER_EXISTS` unpaid order already open  
- `409 INVENTORY_INSUFFICIENT`  
- Same `X-Request-Id` → returns existing order (idempotent)

Address is snapshotted to `shipping_address_json` / `billing_address_json`.

---

## PAY-01 Initiate payment

`POST /payments/initiate` — Bearer + active.user

**Request**

```json
{ "order_id": 1001, "method": "razorpay" }
```

**Success 200**

```json
{
  "payment_id": 55,
  "order_id": 1001,
  "provider": "razorpay",
  "provider_order_id": "order_…",
  "amount": 900,
  "currency": "INR",
  "status": "pending",
  "client_payload": {
    "key": "rzp_test_…",
    "order_id": "order_…",
    "amount": 90000,
    "currency": "INR",
    "name": "GreenLeaf Nursery",
    "prefill": { "email": "…", "contact": "…", "name": "…" },
    "mode": "local_stub"
  }
}
```

**Security:** amount from `order.grand_total` only. Never returns `RAZORPAY_SECRET`.  
`mode=local_stub` only when keys empty and not production.

---

## PAY-02 Verify payment

`POST /payments/verify` — Bearer + active.user

**Request**

```json
{
  "payment_id": 55,
  "provider_order_id": "order_…",
  "provider_payment_id": "pay_…",
  "provider_signature": "…"
}
```

**Success 200**

```json
{
  "payment_id": 55,
  "order_id": 1001,
  "order_number": "ORD-20260811-00001",
  "payment_status": "success",
  "order_status": "CONFIRMED",
  "amount": 900,
  "currency": "INR",
  "order": {
    "id": 1001,
    "order_number": "ORD-20260811-00001",
    "status": "CONFIRMED",
    "payment_status": "success",
    "grand_total": 900,
    "currency": "INR"
  }
}
```

**Rules**

1. HMAC verify (or controlled local stub)  
2. `provider_order_id` must match payment row  
3. Amount already bound to order — client amount ignored  
4. On success: commit stock, record coupon redemption, **clear cart**, confirm order  
5. Idempotent if already `success`

**Errors:** `400 PAYMENT_FAILED`, `404`, `409`

Clients may show success **only** after this response (or equivalent status poll).

---

## PAY-03 Payment status

`GET /payments/{id}` — Bearer + active.user  

Recovery when network drops after gateway success.

---

## PAY-04 Retry payment

`POST /orders/{orderId}/retry-payment` — Bearer + active.user  

For `PENDING_PAYMENT` or `PAYMENT_FAILED` online orders.  
Re-reserves stock if failed, returns a fresh/reused initiate payload.

---

## PAY-05 Webhook

`POST /payments/webhooks/razorpay` — public + signature  

Header: `X-Razorpay-Signature`  
Body: Razorpay event JSON  

Production requires `RAZORPAY_WEBHOOK_SECRET`. Idempotent for already-success payments.

---

## ORD-02 / ORD-03 List & detail

`GET /orders`  
`GET /orders/{id}`  

Scoped to authenticated user. Detail includes items, address snapshot, totals, status history, shipment.

---

## ORD-04 Cancel

`POST /orders/{id}/cancel`  

Body: `{ "reason": "…" }`  

Unpaid (`PENDING_PAYMENT` / `PAYMENT_FAILED`) → release reservation. Cart was never cleared for unpaid online orders.

---

## CONFIG

`GET /app/config` — includes `payments.methods`, `feature_flags.online_payments_enabled`, `commerce.free_delivery_threshold`.

---

## Traceability

| Feature | DB | API | Website | Android |
|---------|----|-----|---------|---------|
| Preview | cart, coupons, shipping_methods | `POST /checkout/preview` | Checkout summary | Checkout preview |
| Place | orders, order_items, inventory | `POST /orders` | Checkout place | Checkout place |
| Pay | payments | initiate + verify + webhook | Checkout.js | razorpay_flutter |
| Success | orders | `GET /orders/{id}` | Order detail | Order detail |

---

## Smoke

```bash
php apps/nursery-api/scripts/phase_f_checkout_smoke.php
```

See also `docs/PAYMENT_CONFIGURATION.md`.
