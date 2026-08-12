# PHASE 17 — Marketing / CRM API

**Base:** `/api/v1`  
**Envelope:** `{ success, message, data, errors, meta }`

All routes require JWT + staff permission middleware.

---

## Dashboard

| Method | Path | Permission |
|--------|------|------------|
| GET | `/admin/marketing/dashboard` | `marketing.view` |

Returns automation counts, segment sample, merchandising campaigns, last 20 deliveries, attribution block (only real `orders.campaign_id` data), config snapshot.

---

## Customer 360

| Method | Path | Permission |
|--------|------|------------|
| GET | `/admin/customers/{id}/360` | `customers.view` **or** `users.manage` |

Does not return passwords, tokens, or payment credentials.

Existing customer list/update remains:

| Method | Path | Permission |
|--------|------|------------|
| GET/PUT | `/admin/users` … | `users.manage` |

---

## Segments

| Method | Path | Permission |
|--------|------|------------|
| GET | `/admin/customer-segments` | `customers.segment` |
| POST | `/admin/customer-segments` | `customers.segment` |
| GET | `/admin/customer-segments/{id}` | `customers.segment` |
| PUT | `/admin/customer-segments/{id}` | `customers.segment` |
| POST | `/admin/customer-segments/{id}/archive` | `customers.segment` |
| POST | `/admin/customer-segments/{id}/duplicate` | `customers.segment` |
| GET | `/admin/customer-segments/{id}/members` | `customers.segment` |

### Create body

```json
{
  "name": "Frequent Buyers",
  "description": "optional",
  "criteria_json": {
    "all": [{ "field": "order_count", "op": "gte", "value": 3 }]
  }
}
```

Members response includes `meta.pagination` — server-side only.

---

## Marketing automations

| Method | Path | Permission |
|--------|------|------------|
| GET | `/admin/marketing/automations` | `marketing.view` |
| POST | `/admin/marketing/automations` | `marketing.manage` |
| GET | `/admin/marketing/automations/{id}` | `marketing.view` |
| PUT | `/admin/marketing/automations/{id}` | `marketing.manage` |
| POST | `/admin/marketing/automations/{id}/activate` | `marketing.launch` |
| POST | `/admin/marketing/automations/{id}/pause` | `marketing.manage` |
| POST | `/admin/marketing/automations/{id}/dispatch` | `marketing.launch` |

### Types

`abandoned_cart` | `welcome` | `post_purchase` | `reactivation` | `manual_blast`

### Status transitions

`draft` → `active` | `paused` | `archived`  
`active` → `paused` | `archived`  
`paused` → `active` | `archived`  
`archived` → (none)

Activate requires `title_template` + `body_template`; blast/reactivation require `segment_id`.

### Dispatch body (test mode)

```json
{ "test_user_ids": [12, 34] }
```

Without `test_user_ids`, dispatches a batch from the automation segment (automation should be active). Marketing prefs still apply inside `NotificationService` for category `marketing`.

---

## Coupons / campaigns (extended, not replaced)

| Change | Detail |
|--------|--------|
| `coupons.campaign_id` | Optional FK; admin create/update accepts `campaign_id` |
| Catalog campaigns | Existing `/admin/campaigns` unchanged |
| Checkout | Optional `campaign_id` persisted on order |

---

## Customer preferences (existing)

| Method | Path |
|--------|------|
| GET/PUT | `/customer/preferences` |

Fields include `marketing_opt_in`, `notify_promotions`. Transactional prefs are separate.

---

## Commands

```bash
php artisan marketing:seed
php artisan marketing:process-abandoned-carts --limit=100
php artisan marketing:process-post-purchase --limit=100
```

Scheduled in `routes/console.php` (hourly abandoned; daily post-purchase).
