# Razorpay TEST Configuration Guide (QA-23)

**Never commit secrets. Never paste Key Secret or Webhook Secret into chat, tickets, or docs.**

This repository uses these **server-only** variable names (already in `apps/nursery-api/.env.example`):

```bash
RAZORPAY_KEY=
RAZORPAY_SECRET=
RAZORPAY_WEBHOOK_SECRET=
```

Do **not** use `RAZORPAY_KEY_ID` / `RAZORPAY_KEY_SECRET` — those names are not wired in this codebase.

---

## 1. Create Razorpay TEST keys

1. Open Razorpay Dashboard → **Test Mode**.
2. Settings → API Keys → Generate **Test** Key Id + Key Secret.
3. Key Id must start with `rzp_test_`.
4. Never put Key Secret in frontend / Flutter / Next.js `NEXT_PUBLIC_*`.

---

## 2. Configure API `.env` (local/staging)

Edit `apps/nursery-api/.env` (gitignored):

```bash
RAZORPAY_KEY=rzp_test_xxxxxxxx
RAZORPAY_SECRET=xxxxxxxx
RAZORPAY_WEBHOOK_SECRET=xxxxxxxx
PAYMENT_ALLOW_UNSIGNED_WEBHOOKS=false
```

Then:

```bash
cd apps/nursery-api
php artisan config:clear
```

Verify presence without printing values:

```bash
php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach (["RAZORPAY_KEY","RAZORPAY_SECRET","RAZORPAY_WEBHOOK_SECRET"] as $k) {
  $v=trim((string)env($k)); echo $k."=".( $v===""?"EMPTY":"SET")."\n";
}'
```

Expected: all three `SET`. Key class should be TEST (`rzp_test_*`).

---

## 3. Webhook URL (existing route — do not invent)

Exact route in this project:

```text
POST /api/v1/payments/webhooks/razorpay
```

Examples:

| Environment | URL |
|-------------|-----|
| Local (needs tunnel) | `https://<ngrok-or-cloudflare-tunnel>/api/v1/payments/webhooks/razorpay` |
| Staging | `https://<staging-host>/api/v1/payments/webhooks/razorpay` |

Razorpay Dashboard → Test Mode → Webhooks:

- URL: as above
- Secret: paste into `RAZORPAY_WEBHOOK_SECRET` only
- Events (minimum): `payment.captured`, `order.paid` (plus failure events if used)

Signature header: `X-Razorpay-Signature`

Local `php artisan serve` is **not** publicly reachable for webhooks without a tunnel.

---

## 4. Queue worker

Payment notifications and some side effects use queues. For full flows:

```bash
php artisan queue:work
```

---

## 5. Prove TEST payment (after keys SET)

1. Customer Web or Mobile: place UPI order → Dynamic QR / Intent.
2. Complete Razorpay **TEST** UPI payment (Razorpay test instruments / UPI test flow).
3. Confirm:
   - `POST /payments/verify` or webhook marks payment `success`
   - Order `CONFIRMED`
   - Inventory committed once
   - Admin order shows method/status/provider ids
4. Record only: order number, Razorpay payment id, Razorpay order id — **never secrets**.

---

## 6. LIVE (production only — later)

Do **not** put `rzp_live_*` on local. Production readiness rejects `rzp_test_*` in `APP_ENV=production`.

LIVE requires: HTTPS, `APP_DEBUG=false`, `--strict` PASS, LIVE webhook, operator authorization for a low-value live charge.

---

## Current host status (2026-08-12 — QA-25)

| Variable | State |
|----------|-------|
| `RAZORPAY_KEY` | **EMPTY** |
| `RAZORPAY_SECRET` | **EMPTY** |
| `RAZORPAY_WEBHOOK_SECRET` | **EMPTY** |

Therefore: **REAL TEST PAYMENT = BLOCKED** (QA-24 and QA-25). Automated/stub PASS ≠ TEST PASS ≠ LIVE PASS ≠ GREEN.
