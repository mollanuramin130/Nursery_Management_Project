# QA-22 REPORT — Push + Order Status Notifications

**Date:** 2026-08-12  
**Status:** **PARTIAL**  
**GREEN:** **NO** (FCM LIVE blocked; Firebase client credentials unavailable)

---

## 1. QA-22 status

**PARTIAL** — Phase 11 stack extended for two-way operational notifications, deep links, token registration paths, and automated tests. LIVE FCM delivery **BLOCKED** without `FCM_SERVER_KEY` + Firebase client projects.

---

## 2. Notification architecture

See `docs/QA-22-NOTIFICATION-ARCHITECTURE.md`. Reuses `NotificationService`, queue job, `user_devices`, inbox APIs. Added `OrderNotificationDispatcher`.

---

## 3. Customer → Admin

On successful order create: `new_order` to staff with `orders.view` / `notifications.view`.  
Also: payment confirmed (staff), customer cancel, return request (staff).

---

## 4. Admin → Customer

After successful status transitions: confirmed, processing, packed, shipped, OFD, delivered, cancelled, payment failed, return/refund types.

---

## 5. FCM configuration

| Item | Status |
|------|--------|
| `FCM_SERVER_KEY` | **EMPTY** on this host |
| `FcmPushGateway` | local stub non-prod |
| Flutter `firebase_messaging` | **not added** (would break builds without `google-services.json`) |
| Web Firebase | optional dynamic import when `NEXT_PUBLIC_FIREBASE_*` set |

---

## 6. Token lifecycle

Register/deactivate APIs reused. Clients register device on login; optional `FCM_PUSH_TOKEN` dart-define / browser FCM when configured. Logout deactivates device. Invalid tokens deactivated on send.

---

## 7. Notification center

Existing GET `/notifications`, unread, mark read/all — unchanged contract.

---

## 8. Deep links

`route` + ids in payload; Web/Mobile helpers.

---

## 9–10. Payment / order status

Payment notify only from `finalizeSuccess` (server verified). Status notify after SM transition success. Idempotent keys prevent duplicates.

---

## 11–14. Tests

`Qa22NotificationsTest` **9 PASS**. Related payment/security suites included in regression. See test matrix.

---

## 15. Real-device

**BLOCKED / UNVERIFIED** — no Firebase project / empty FCM key.

---

## 16–19. Clients

| App | Code | LIVE push |
|-----|------|-----------|
| Customer Web | device register + deep link + optional FCM | BLOCKED |
| Customer Mobile | device register + deep link + env token | BLOCKED |
| Admin Web | device register + deep link unit | BLOCKED |
| Admin Mobile | device register + inbox tap deep link | BLOCKED |

---

## 20. Database

**NONE** — reused Phase 11 tables.

---

## 21. API

**Additive** types/payload fields (`route`, `audience`). No breaking changes.

---

## Remaining risks

- LIVE push unproven  
- QA-SEC-001 still OPEN  
- Without queue worker, email/push lag  
- Flutter Firebase not wired until `google-services.json` provided
