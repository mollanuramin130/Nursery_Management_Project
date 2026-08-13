# QA-23 FCM Setup Guide

**Never commit or paste:** service account private keys, `FCM_SERVER_KEY` values, or production Firebase secrets.

---

## Architecture (existing — do not duplicate)

```
Client (registers push_token)
  → POST /api/v1/devices/register
  → user_devices

Domain event (order/payment/return)
  → NotificationService::notify (idempotent)
  → DeliverNotificationChannelsJob (queue, afterCommit)
  → FcmPushGateway
  → Firebase Cloud Messaging
  → Device
```

---

## Server credentials (pick one)

### Preferred — Firebase HTTP v1

1. Firebase Console → Project Settings → Service accounts → Generate new private key  
2. Store JSON **outside git** (e.g. `/etc/greenleaf/firebase-sa.json`)  
3. In `apps/nursery-api/.env`:

```bash
FIREBASE_CREDENTIALS=/absolute/path/to/firebase-sa.json
FCM_SERVER_KEY=
```

### Legacy — server key (optional)

```bash
FCM_SERVER_KEY=AAAA...
FIREBASE_CREDENTIALS=
```

Without either: non-production uses **local_stub** (not real FCM). Production push returns unavailable.

---

## Android client apps

| App | Package | Example config |
|-----|---------|----------------|
| Customer | `com.greenleaf.nursery_app` | `apps/nursery_app/android/app/google-services.json.example` |
| Admin | `com.greenleaf.nursery_admin_mobile` | `apps/nursery_admin_mobile/android/app/google-services.json.example` |

Steps:

1. Create Firebase Android apps with those package names  
2. Download real `google-services.json` into `android/app/` (gitignored)  
3. Add Flutter packages when ready to wire LIVE push:
   - `firebase_core`
   - `firebase_messaging`
4. Apply Google services Gradle plugin after the real JSON exists  
5. On login, register FCM token via existing `POST /devices/register` (`push_token`)

Until Firebase packages + JSON are present, apps continue registering **device_id only** (in-app inbox works; LIVE push blocked).

---

## Queue worker (required for push)

```bash
cd apps/nursery-api
php artisan queue:work --tries=3
```

`QUEUE_CONNECTION=database` on this project. Jobs: `DeliverNotificationChannelsJob`.

---

## Web push

**DEFERRED / OUT OF SCOPE for QA-23 LIVE.**  
Customer/Admin Web register devices without FCM tokens today. Browser FCM may be added later with `NEXT_PUBLIC_FIREBASE_*` — not required to close mobile LIVE push.

---

## Token lifecycle checklist

1. Firebase init on device  
2. Get FCM token  
3. `POST /devices/register` with `push_token`  
4. Multi-device: multiple rows per `user_id`  
5. Logout → `POST /devices/deactivate`  
6. Token refresh → register again (upsert by device_id/token)  
7. Invalid token → gateway sets `invalid_token` → job deactivates device  

---

## Verify without claiming LIVE

```bash
# Presence only — never print secrets
php -r '... echo FCM/FIREBASE SET|EMPTY ...'
php artisan queue:work   # separate terminal
# Then place COD order and watch Admin/Customer devices
```

Real FCM PASS requires observed notification on a physical device with credentials configured.

---

## Relationship to payments

Razorpay LIVE is a **separate** gate. FCM can PASS while Razorpay remains BLOCKED.
