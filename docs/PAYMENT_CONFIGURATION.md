# Payment Configuration — Razorpay

**Gateway:** Razorpay (only)  
**Secrets:** PHP server environment only — never Flutter, never Website JS, never git.

---

## Environment variables

Set in `apps/nursery-api/.env` (see `.env.example`):

| Variable | Purpose | Client-visible? |
|----------|---------|-----------------|
| `PAYMENT_DRIVER` | `razorpay` | No |
| `RAZORPAY_KEY` | Key ID (public) | Yes — returned in `client_payload.key` |
| `RAZORPAY_SECRET` | Key secret | **Never** |
| `RAZORPAY_WEBHOOK_SECRET` | Webhook HMAC secret | **Never** |
| `COD_ENABLED` | Enable Cash on Delivery (`true`/`false`) | Via `/app/config` |
| `FREE_DELIVERY_THRESHOLD` | Free shipping threshold (INR) | Via cart + checkout preview |
| `STORE_CURRENCY` | Default `INR` | Via `/app/config` |

**Never commit real credentials.** Use placeholders in docs and `.env.example`.

---

## Modes

### Local development (no keys)

- `RAZORPAY_KEY` / `RAZORPAY_SECRET` empty
- API creates stub gateway orders (`order_local_*`)
- Clients may use `client_payload.mode = local_stub` and `local_*` signatures
- **Blocked in `APP_ENV=production`**

### Razorpay Test (sandbox)

1. Create Razorpay test keys in the Razorpay Dashboard  
2. Set `RAZORPAY_KEY=rzp_test_…` and `RAZORPAY_SECRET=…`  
3. Set webhook secret and point webhook to:

```
POST https://<your-api-host>/api/v1/payments/webhooks/razorpay
```

Header verified: `X-Razorpay-Signature`  
Events: `payment.captured`, `order.paid`, `payment.failed`

4. Use Razorpay test cards / UPI as per Razorpay docs  
5. **No real money** is charged in test mode

### Production

1. Live keys only on the production server  
2. `APP_ENV=production`  
3. Webhook secret **required** (unsigned webhooks rejected)  
4. Empty keys → API returns `503 PAYMENT_GATEWAY_UNAVAILABLE`  
5. HTTPS only for Website + API + Android build

---

## Client configuration

### Website

- Loads Checkout.js from Razorpay CDN  
- Opens checkout with `client_payload` from `POST /payments/initiate`  
- Sends gateway result to `POST /payments/verify`  
- Success UI only after `payment_status=success` and `order_status=CONFIRMED`

### Android

- Package: `razorpay_flutter`  
- Same initiate → SDK → verify flow  
- Flag: `--dart-define=ONLINE_PAYMENTS_ENABLED=true|false` (default true)  
- App config also exposes `feature_flags.online_payments_enabled`

---

## Authoritative flow

```
POST /orders (pending or COD confirmed)
POST /payments/initiate   → amount from order.grand_total
Gateway checkout
POST /payments/verify     → HMAC verify → commit stock → clear cart
   and/or
POST /payments/webhooks/razorpay
```

Clients never supply payable amount. Clients never mark orders paid.

---

## Recovery

| Situation | Action |
|-----------|--------|
| SDK success, verify network fail | `GET /payments/{id}` or open order detail + retry |
| Payment cancelled | Cart retained; order `PENDING_PAYMENT` → retry or cancel |
| Payment failed | `POST /orders/{id}/retry-payment` |
| Abandoned unpaid | Cancel unpaid order to release stock |

---

## Checklist before go-live

- [ ] Test keys work end-to-end (create → pay → verify → CONFIRMED)  
- [ ] Webhook signature verified in staging  
- [ ] Production secrets only on server  
- [ ] Stub mode impossible in production  
- [ ] COD and online both tested  
- [ ] Cart retained on fail; cleared only on confirm  
