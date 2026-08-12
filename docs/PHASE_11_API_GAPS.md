# PHASE 11 — API Gaps

## Implemented this phase

| Feature | Support |
|---------|---------|
| Unread count | `GET /notifications/unread-count` |
| Device deactivate | `POST /devices/deactivate` |
| Multi-channel notify | Extended `NotificationService` + job |
| Admin monitor | `GET /admin/notifications`, `/{id}`, dashboard |
| Templates | `GET/PUT /admin/notification-templates` |
| Marketing send | `POST /admin/notifications/send` |
| Preferences enforcement | Wired |

## Remaining gaps

### 1. Firebase client (Android)

| Field | Value |
|-------|-------|
| Current | Device register without FCM token; server FCM ready |
| Missing | `google-services.json` + `firebase_messaging` package |
| Recommendation | Ops adds Firebase project; app registers real `push_token` |

### 2. SMTP production mailer

| Field | Value |
|-------|-------|
| Current | Mailables work; default `MAIL_MAILER=log` |
| Recommendation | Set Hostinger/SMTP env in production |

### 3. Event/listener refactor

| Field | Value |
|-------|-------|
| Current | Domain services still call `notify()` directly (now safe/async) |
| Recommendation | Optional later Event classes — not required for correctness |

### 4. SMS / WhatsApp

| Field | Value |
|-------|-------|
| Recommendation | Out of scope |

### 5. Advanced campaign audience blast

| Field | Value |
|-------|-------|
| Current | Admin send to one user_id |
| Recommendation | Reuse Campaign module later for bulk opted-in audiences |

### 6. Delivery webhooks (opened/delivered)

| Field | Value |
|-------|-------|
| Current | sent/failed/skipped only |
| Recommendation | Do not claim “delivered” without provider confirmation |
