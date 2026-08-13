# QA-22 Notification Architecture

**Date:** 2026-08-12  
**Extends:** Phase 11 notification system (do not replace)

---

## Flow

```
Domain success (order / payment / fulfillment / return)
        ↓
OrderNotificationDispatcher / NotificationService::notify
        ↓
notifications row (idempotent)
        ↓
DeliverNotificationChannelsJob (afterCommit)
        ↓
in_app | email | FCM push (multi-device)
```

Customer → Admin: `notifyNewOrder` / payment / cancel / return staff alerts  
Admin → Customer: status map via `notifyCustomerStatus` after successful transitions

---

## Key classes

| Class | Role |
|-------|------|
| `NotificationService` | Idempotent inbox write + queue |
| `OrderNotificationDispatcher` | QA-22 order/staff mapping + deep links |
| `NotificationChannelMap` | Channel strategy per type |
| `DeliverNotificationChannelsJob` | Email/push delivery |
| `FcmPushGateway` | FCM send; local stub without `FCM_SERVER_KEY` |
| `UserDevice` | Multi-device tokens |

---

## Types (selected)

Customer: `payment_confirmed`, `order_confirmed`, `order_processing`, `order_packed`, `order_shipped`, `order_out_for_delivery`, `order_delivered`, `order_cancelled`, return/refund variants  

Staff: `new_order`, `payment_confirmed_admin`, `customer_cancelled`, `return_requested_admin`

There is **no** separate `READY_FOR_DELIVERY` order status — packing uses `PACKED`.

---

## Token lifecycle

`POST /devices/register` · `POST /devices/deactivate`  
Invalid FCM tokens deactivated on send. Logout deactivates current device.

---

## Deep links

Payload includes safe `route` + `order_id` / `return_id`. Clients prefer `route` when relative.

---

## LIVE push requirements

1. Prefer `FIREBASE_CREDENTIALS` (service account JSON) for HTTP v1 — or legacy `FCM_SERVER_KEY`  
2. Firebase Android apps + real `google-services.json` (gitignored) for Customer + Admin packages  
3. Flutter `firebase_core` / `firebase_messaging` once JSON exists  
4. Queue worker running (`php artisan queue:work`)  
5. Device notification permission granted  

Without these: in-app inbox + queued jobs still work; FCM uses **local_stub** in non-production.  

See `docs/QA-23-FCM-SETUP.md`.
