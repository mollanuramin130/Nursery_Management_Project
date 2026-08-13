# QA-26 REPORT — Real Razorpay TEST Payment E2E Verification

**Date:** 2026-08-13  
**Status:** **COMPLETE** (Razorpay **TEST** mode only)  
**GREEN / LIVE:** **NO**

---

## 1. Status

QA-26 is **COMPLETE** based on verified database rows, Laravel application logs, and live API reads for the real TEST payment below. No values were invented. Architecture was not changed for closeout.

---

## 2. Verified real TEST payment evidence

| Field | Verified value |
|-------|----------------|
| GreenLeaf Payment ID | **5526** |
| GreenLeaf Order ID | **9062** (`ORD-20260813-00006`) |
| Amount | **₹1697.00** (payment + order grand_total match) |
| Payment status | **success** |
| Order status | **CONFIRMED** |
| Paid / confirmed at | **2026-08-13 13:28:01** |
| Razorpay Payment ID | **pay_TPBCfrCIzFjCKz** |
| Razorpay Order ID | **order_TPBCV5HS098qBW** |
| Method | order `upi` · payment provider `razorpay` |
| UPI mode (initiate log) | **checkout** |

### ID linkage

- Payment `5526.order_id` = **9062** — **YES**  
- `provider_payment_id` = `pay_TPBCfrCIzFjCKz` — **YES**  
- `provider_order_id` = `order_TPBCV5HS098qBW` — **YES**  
- Exactly **one** payment row for that Razorpay payment id — **YES**

---

## 3. Server verification + webhook (logs)

From `storage/logs/laravel.log` at **2026-08-13 13:28:01** (local channel):

1. `payment.webhook` event=`order.paid` · order=`order_TPBCV5HS098qBW` · pay=`pay_TPBCfrCIzFjCKz`  
2. `payment.verify.succeeded` · payment_id=`5526` · order_id=`9062`  
3. `payment.webhook` event=`payment.captured` · same Razorpay ids  

Also earlier: `payment.initiate` for order **9062** amount **1697.0** mode **checkout**.

### Webhook signature

- Host `RAZORPAY_WEBHOOK_SECRET` is **SET**; `PAYMENT_ALLOW_UNSIGNED_WEBHOOKS=false`.  
- `PaymentService::handleWebhook` verifies HMAC **before** `Log::info('payment.webhook', …)`.  
- Those webhook INFO lines exist for this payment → signature check **passed** for the received events.  
- No `payment.webhook.unsigned_accepted` on the **local** channel at 13:28 (only earlier `testing` PHPUnit noise).

Client `POST /payments/verify` was also reached (operator evidence + DB `provider_signature` **present**). After webhook finalize, verify is idempotent (`status === success` early return).

---

## 4. Inventory single-commit

`stock_movements` for `reference_type=order`, `reference_id=9062`:

| id | type | product | qty_delta | time |
|----|------|---------|-----------|------|
| 179 | reserve | 601 | +1 | 13:26:02 |
| 180 | reserve | 702 | +2 | 13:26:02 |
| 181 | sale | 601 | -1 | 13:28:01 |
| 182 | sale | 702 | -2 | 13:28:01 |

- Sale/commit rows: **exactly one per line** at payment success time.  
- No duplicate sale rows for this order.  
- Matches `InventoryService::commit` via `finalizeSuccess`.

---

## 5. Customer + Admin visibility (API, executed)

| Check | Result |
|-------|--------|
| `GET /api/v1/orders/9062` (order owner) | **200** · status **CONFIRMED** · payment **success** · total **1697** |
| `GET /api/v1/admin/orders/9062` (admin) | **200** · **CONFIRMED** · payment **success** · `provider_payment_id=pay_TPBCfrCIzFjCKz` |
| `GET /api/v1/admin/payments?search=5526` | **404** route absent — payment still visible on admin **order** detail |

Customer Web UI interactive click-through of order 9062 after closeout: **UNVERIFIED** (API contract verified).

---

## 6. Notifications

At **2026-08-13 13:28:01** for customer user_id **13**:

- `payment_confirmed` (notification id **171**)  
- `order_confirmed` (notification id **177**)  

Staff `new_order` notifications created at order placement (**13:26:02**).  

LIVE FCM device push: **UNVERIFIED / BLOCKED** (Firebase credentials still a separate gate). Inbox/DB notifications for payment success: **PASS**.

---

## 7. Idempotency

- Second webhook `payment.captured` after success → handler returns idempotent when `status === success`.  
- Single `payment.verify.succeeded` finalize log for 5526/9062.  
- Single set of `sale` movements.  
- Single payment row for Razorpay payment id.

---

## 8. COD / regression / security

| Item | Result |
|------|--------|
| COD regression (`Qa26…`) | **PASS** (suite) |
| Payment filter Qa04/18/21/24/25/26 | **34 pass + 2 skipped** |
| Full Qa02–Qa26 + Phase | **233 tests / 1120 assertions** (231 pass + 2 skipped) |
| Customer Web unit | **PASS** |
| Secrets exposed | **NO** (not printed; `.env` ignored) |
| LIVE / GREEN claim | **NO** |

---

## 9. UNVERIFIED (honest)

1. Interactive Customer Web / Admin Web browser UI smoke for order 9062 after payment (API verified).  
2. LIVE FCM push delivery for this payment.  
3. Separate Dynamic QR / UPI Intent **physical** charge for this same payment (this success used **checkout** mode).  
4. Admin dedicated `/admin/payments` index route (does not exist; order detail carries payment fields).

None of these block QA-26 COMPLETE for the stated TEST Checkout → verify/webhook → PAID chain.

---

## 10. Files touched (closeout docs only)

- `docs/QA-26-REPORT.md`  
- `docs/QA-26-CLOSEOUT-REPORT.md`  
- `docs/QA-26-TEST-MATRIX.md`  
- Register / roadmap / `RUN.txt` status lines  

No payment code changes required for this closeout.
