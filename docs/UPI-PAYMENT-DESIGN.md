# UPI Payment Design (QA-21)

**Date:** 2026-08-12  
**Status:** Code COMPLETE on this host · LIVE payment BLOCKED (Razorpay credentials EMPTY)

---

## Principle

The client **never** declares `payment = PAID`.

Authoritative path:

```
Customer → GreenLeaf API → Razorpay (PSP) → UPI → Customer UPI app
  → PSP verify / webhook → GreenLeaf API
  → verify order · amount · payment IDs · signature · state · idempotency
  → mark payment success → confirm order → commit inventory once
```

App return / QR scan / browser refresh means only **customer returned** or **UI refreshed**, not payment success.

---

## Architecture (reuse, not replace)

Existing stack extended; no new payment tables.

| Layer | Component |
|-------|-----------|
| Routes | `POST /payments/initiate`, `POST /payments/verify`, `POST /payments/webhook`, `GET /payments/{id}` |
| Service | `PaymentService` |
| PSP | `RazorpayGateway` (+ UPI channel helpers) |
| Order | `OrderStateMachine` · `CheckoutService` |
| Inventory | Existing reserve → commit on verify/webhook |
| Idempotency | `X-Request-Id` + payment row locks + provider payment id uniqueness |

Conceptual providers:

```
PaymentService
  ├── COD (order path; no PSP)
  └── Online (method: upi | razorpay)
        └── RazorpayGateway
              ├── checkout (hosted)
              ├── dynamic_qr
              └── upi_intent
```

`method: upi` is a **channel** on Razorpay, not a second payment driver.

---

## Payment states (existing terminology)

Payment row:

- `pending`
- `success`
- `failed`

Order (online UPI):

- `PENDING_PAYMENT` → `CONFIRMED` (on verified success)
- failure / cancel paths reuse existing `PAYMENT_FAILED` / cancel flows where applicable

UI labels map API statuses to Pending / Waiting / Paid / Failed / Expired / Cancelled. UI mapping is display-only.

---

## UPI modes

| Mode | Client use | Server |
|------|------------|--------|
| `dynamic_qr` | Show QR from `qr_data` / `qr_image_url` | Create Razorpay order + UPI deep-link (+ optional QR API when keys present) |
| `upi_intent` | Launch `upi_intent_url` | Same deep-link; mobile opens external UPI app |
| `checkout` | Razorpay Checkout sheet (fallback) | Full checkout payload |

Default: Web uses `dynamic_qr`; Mobile prefers `upi_intent`.

---

## Amount authority

- Payable amount = `order.grand_total` from server checkout.
- Client `amount` if sent must match or initiate returns **409 CONFLICT**.
- Webhook / finalize also check payment amount vs order grand total.

---

## Webhook / verify

Unchanged Razorpay signature verification + idempotent finalize:

1. Verify signature (reject invalid).
2. Lock payment/order.
3. Map provider order/payment IDs.
4. Validate amount / ownership / payable state.
5. If already `success` → idempotent success.
6. Mark payment success → confirm order → commit inventory once.

---

## Environment

| Variable | Role |
|----------|------|
| `RAZORPAY_KEY` / `RAZORPAY_SECRET` | PSP credentials |
| `RAZORPAY_WEBHOOK_SECRET` | Webhook HMAC |
| `UPI_PAYEE_VPA` | Payee VPA for deep-link (local/stub display; live QR prefers provider) |
| `PAYMENT_*` / existing services config | As already wired in `config/services.php` |

Never log secrets. Production readiness rejects empty keys and `rzp_test_` in `APP_ENV=production`.

---

## Client behavior

**Customer Web:** COD | UPI → initiate `dynamic_qr` → QR + bounded poll (3s / ~3 min) → status from `GET /payments/{id}` only.

**Customer Mobile:** COD | UPI → initiate `upi_intent` → launch URL → poll API → on timeout/fail leave `pay=pending`. Never treat intent return as paid. Stub/checkout fallback only when payload is `local_stub` / checkout mode.

**Admin Web / Mobile:** Show method (`UPI`), status, amount, provider txn / order ids, `upi_mode` when present.

---

## Rollback

1. Clients can hide UPI via online-payments flag / empty keys (existing).
2. Orders with `payment_method=upi` still verify through Razorpay paths.
3. No schema rollback required (no new tables).
4. Revert gateway UPI helpers + client UPI UI if needed; COD unaffected.

---

## Honest limits on this host

| Item | Result |
|------|--------|
| Stub / PHPUnit UPI | PASS |
| LIVE Razorpay UPI / webhook | **BLOCKED** (credentials EMPTY) |
| Production `--strict` | **UNVERIFIED** (`APP_ENV=local`) |
| QA-SEC-001 | **OPEN** (unchanged) |
