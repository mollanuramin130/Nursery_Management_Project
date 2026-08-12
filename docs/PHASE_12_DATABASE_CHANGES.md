# PHASE 12 — Database Changes

## Migrations

| Migration | Purpose |
|-----------|---------|
| `2026_08_12_020000_phase12_reviews_indexes.php` | Speed product/user/moderation review listings |

### Indexes added (`reviews`)

| Index | Columns | Why |
|-------|---------|-----|
| `reviews_product_status_id_index` | `(product_id, status, id)` | PDP approved reviews + cursor |
| `reviews_user_id_index` | `(user_id, id)` | “My reviews” |
| `reviews_status_created_at_index` | `(status, created_at)` | Admin moderation queue |

### Rollback

```bash
php artisan migrate:rollback --step=1
```

Drops the three indexes above. Safe; no data rewrite.

---

## Query optimizations (no schema)

| Area | Change |
|------|--------|
| PlantFinder | `limit(300)` after filters; order by popularity before in-memory score |
| Home feed | Cache 60s (`catalog:home:feed:v1`); invalidate on Product/Category/Banner/Campaign save/delete |
| Wishlist list | Soft `limit(100)` |

---

## Constraints

No new FK/unique constraints in Phase 12. Existing payment `idempotency_key` NOT NULL remains authoritative.

---

## Production migration notes

1. Backup DB (`PRODUCTION_BACKUP_PLAN.md` / runbook).  
2. `php artisan migrate --force` during low traffic.  
3. Index builds on large `reviews` tables may lock — prefer offline window on Hostinger shared plans.  
4. Verify: `SHOW INDEX FROM reviews;`
