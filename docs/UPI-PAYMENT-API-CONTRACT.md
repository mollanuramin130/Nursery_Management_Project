# UPI Payment API Contract (QA-21)

**Base:** `/api/v1`  
**Envelope:** `{ success, message, data, errors, meta }`  
**Change type:** **Additive** (no breaking changes). Existing Razorpay + COD paths preserved.

---

## Payment methods (checkout / order)

| Value | Meaning |
|-------|---------|
| `cod` | Cash on delivery |
| `upi` | Online UPI via Razorpay channel |
| `razorpay` | Online Razorpay Checkout (still accepted) |

---

## UPI modes (`POST /payments/initiate`)

| `mode` | Description |
|--------|-------------|
| `dynamic_qr` | QR + deep-link payload |
| `upi_intent` | Deep-link for native UPI apps |
| `checkout` | Hosted Razorpay Checkout (UPI preferred) |

---

## `POST /payments/initiate`

**Auth:** Customer Bearer  
**Idempotency:** Prefer `X-Request-Id`

### Request

```json
{
  "order_id": 123,
  "method": "upi",
  "mode": "dynamic_qr",
  "amount": 1000.00
}
```

- `method`: `upi` | `razorpay` (nullable → default online)
- `mode`: optional; defaults `dynamic_qr` for `upi`, `checkout` for `razorpay`
- `amount`: **optional**. If present must equal `order.grand_total` or **409** `CONFLICT`

### Response `data` (conceptual)

```json
{
  "payment_id": 45,
  "order_id": 123,
  "method": "upi",
  "channel": "upi",
  "upi_mode": "dynamic_qr",
  "amount": 1000,
  "currency": "INR",
  "payment_status": "pending",
  "provider_order_id": "order_xxx",
  "expires_at": "2026-08-12T12:00:00+00:00",
  "client_payload": {
    "key": "rzp_…",
    "order_id": "order_xxx",
    "amount": 100000,
    "currency": "INR",
    "channel": "upi",
    "upi_mode": "dynamic_qr",
    "qr_data": "upi://pay?…",
    "qr_image_url": null,
    "upi_intent_url": "upi://pay?…",
    "expires_at": "…",
    "mode": "local_stub"
  }
}
```

`client_payload.amount` is **paise** (Razorpay convention). Top-level `amount` is rupees (order grand total).

Local stub may set `mode: local_stub` and `stub_confirm_allowed: true` — **blocked in production**.

---

## `POST /payments/verify`

Unchanged contract. Client may call after Checkout / stub; **must not** invent success without server response.

### Request

```json
{
  "payment_id": 45,
  "provider_order_id": "order_xxx",
  "provider_payment_id": "pay_xxx",
  "provider_signature": "…"
}
```

### Success `data`

```json
{
  "payment_status": "success",
  "order_status": "CONFIRMED",
  "order_id": 123
}
```

Server verifies signature, ownership, amount, and commits inventory once.

---

## `GET /payments/{payment}`

Poll status while pending.

### Response `data` (includes)

- `payment_id`, `payment_status`, `order_id`, `order_status`
- `amount`, `method`, `channel`, `upi_mode`, `expires_at` when applicable
- `client_payload` fields as presented for pending UPI

Terminal for UI polling: `success` / `failed` (plus UI-only expired/cancelled mapping).

---

## `POST /payments/webhook`

Razorpay webhook (signature header). Not customer-authenticated.

Validates signature → maps payment → amount/order checks → idempotent finalize.

Invalid signature → reject. Duplicate success events → idempotent OK.

---

## Admin order detail (additive)

`GET /admin/orders/{id}` `payment` object may include:

- `provider`, `provider_payment_id`, `provider_order_id`
- `upi_mode`, `channel`

Existing fields unchanged.

---

## Errors (selected)

| Case | Typical |
|------|---------|
| Amount mismatch | 409 CONFLICT |
| Wrong order / ownership | 403 / 404 |
| Already paid / duplicate verify | Idempotent success or conflict per existing rules |
| Cancelled / non-payable order | 409 / business error |
| Invalid signature | Reject verify / webhook |

---

## Compatibility

- COD `payment_method: cod` unchanged.
- Legacy `method: razorpay` initiate unchanged.
- No new required client fields.
