# PHASE 11 — Notifications & Customer Communication

## 1. Architecture

```
Domain services (Order/Payment/Returns/Loyalty/Subscription/Review)
        ↓
NotificationService::notify (idempotent in-app write)
        ↓
DeliverNotificationChannelsJob (queue)
        ↓
  IN_APP delivery row | EMAIL (Mail) | PUSH (FCM gateway)
```

Backend remains source of truth. No SMTP/FCM secrets in Website/Android/Admin.

## 2. Event architecture

Existing callers keep using `notify($user, $type, $title, $body, $data)`.
Idempotency key defaults from `type + user + order_id|subscription_id|…`.

## 3. Channels

Configured in `NotificationChannelMap` (not controllers):

- Transactional: in_app + email/push per type
- Marketing: requires `marketing_opt_in` + `notify_promotions`

## 4–6. Email / Push / In-app

- Email: `CustomerNotificationMail` + markdown template; uses `MAIL_*` (default log)
- Push: `FcmPushGateway` — local stub without `FCM_SERVER_KEY`; multi-device; invalid tokens deactivated
- In-app: existing `notifications` table + customer inbox APIs

## 7. Preferences

`PreferenceService` authoritative. Marketing gated at notify + channel job. Transactional email is not blocked by marketing opt-out.

## 8. Templates

Reuse `notification_templates` table + `NotificationTemplateSeeder`. Variables: `{{customer_name}}`, `{{order_number}}`, `{{tracking_number}}`, etc. No untrusted HTML.

## 9–11. Queue / retry / idempotency

- `QUEUE_CONNECTION=database` (existing)
- Job tries=3; channel unique `(notification_id, channel)`
- Duplicate business events → one notification row

## 12. Device registration

`POST /devices/register`, `POST /devices/deactivate`  
Android registers stable `device_id` on login; deactivates **that** device on logout (other sessions untouched).

## 13. Deep links

Safe keys only: `order_id`, `subscription_id`, `product_slug`, `campaign_slug` (validated pattern). No arbitrary URLs.

## 14. Marketing

`campaign_promo` type + admin send (permissioned). Reuses preferences; does not invent CDP.

## 15–17. Admin / RBAC / audit

`/admin/notifications`, templates, send  
Permissions: `notifications.view|send|manage_templates`  
Audit: template update, admin send

## 18. Security

FCM/SMTP secrets server-side only. Customers only see own inbox.

## 19. Testing

`Phase11NotificationsTest` — idempotency, ownership, devices, email delivery, marketing opt-out, admin RBAC.
