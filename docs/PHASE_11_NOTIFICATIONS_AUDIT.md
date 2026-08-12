# PHASE 11 — Notifications Audit

## Summary

| Area | Status |
|------|--------|
| In-app inbox | **Exists** — extend |
| Customer APIs (list/read/devices) | **Exists** — extend |
| Preferences | **Exists** — not enforced at send |
| `notification_templates` table | **Exists unused** |
| `user_devices` | **Exists** — register only |
| Email send | **Missing** (mail config only, log driver) |
| FCM push send | **Missing** (env placeholders only) |
| Queue jobs | **Missing** (DB queue ready) |
| Events/listeners | **Missing** |
| Idempotency | **Missing** |
| Admin monitoring | **Missing** |
| Website bell/badge | **Partial** (page exists, no badge) |
| Android FCM client | **Missing** |

## Decision

**Extend** `NotificationService::notify` into a multi-channel orchestrator. Do not create a second inbox/queue/email stack.

## Billing model for channels

- **IN_APP**: always for transactional (unless system mute)
- **EMAIL**: transactional always allowed when `notify_email`; marketing requires `marketing_opt_in` + `notify_promotions`
- **PUSH**: when `notify_push` (marketing also needs opt-in)

## Out of scope

SMS, WhatsApp, full Firebase Android client (document + server-ready FCM; device register API works), complex CDP segmentation.
