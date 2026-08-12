# PHASE 17 — Marketing Runbook

**Date:** 2026-08-12

---

## Deploy checklist

1. Migrate: `php artisan migrate` (includes `2026_08_12_050000_phase17_crm_marketing`)
2. Seed permissions: `php artisan db:seed --class=RolePermissionSeeder`
3. Seed marketing defaults: `php artisan marketing:seed`
4. Ensure queue worker + scheduler running (existing Phase 11 pattern)
5. Confirm env (optional):

```
MARKETING_MAX_PER_DAY=2
MARKETING_MAX_PER_WEEK=5
ABANDONED_CART_HOURS=24
ABANDONED_CART_MAX_MESSAGES=2
ABANDONED_CART_MIN_SUBTOTAL=0
ABANDONED_CART_COOLDOWN_HOURS=48
MARKETING_WELCOME_ENABLED=true
MARKETING_POST_PURCHASE_ENABLED=true
MARKETING_REVIEW_DAYS=3
MARKETING_REACTIVATION_DAYS=90
MARKETING_BATCH_SIZE=100
```

---

## Safe launch path

```
DRAFT → TEST dispatch (test_user_ids) → ACTIVATE → MONITOR deliveries → SCALE
```

- Abandoned cart / post-purchase seed as **draft** — activate only after copy + coupon review.
- Welcome seeds as **active** (registration hook); disable via config `MARKETING_WELCOME_ENABLED=false` if needed.
- Never mass-dispatch without checking segment member count on detail page.

---

## Operations

### View marketing health

Admin → **Marketing hub** (`/marketing`)

### Activate abandoned cart

1. Admin → Automations → Abandoned Cart Recovery  
2. Confirm templates (no fake urgency)  
3. Optionally attach existing coupon (coupon rules unchanged)  
4. Activate (`marketing.launch`)  
5. Scheduler runs `marketing:process-abandoned-carts` hourly  

### Manual blast

1. Create/verify segment  
2. Create `manual_blast` automation with segment_id  
3. Test dispatch to staff customer IDs  
4. Activate + dispatch batch  

### If deliveries stuck / skipped

Check `marketing_deliveries.skip_reason`:

| Reason | Meaning |
|--------|---------|
| `opted_out` | marketing_opt_in or notify_promotions false |
| `frequency_cap` | daily marketing cap hit |
| `notify_returned_null` | NotificationService refused (prefs/idempotency) |
| `exception` | failure recorded; campaign continues for others |

---

## Incident: accidental blast

1. Pause automation immediately  
2. Do not delete delivery history (audit)  
3. Review audit logs for `marketing_automation.*`  
4. Communicate only if messages were actually `sent`

---

## Unsubscribe / prefs

- Customers manage marketing at website **Account → Preferences**
- Marketing emails/push use existing notification templates + marketing category
- Do not disable order/payment transactional notifications via marketing opt-out

---

## Retention

- `marketing_deliveries`: operational; retain ≥ 90 days recommended; archive later if volume grows
- Segments/automations: soft archive (`status=archived`), avoid hard delete of system keys
