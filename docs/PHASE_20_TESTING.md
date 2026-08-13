# PHASE 20 — Testing

## Backend

```bash
cd apps/nursery-api
php artisan test --filter=Phase20FulfillmentDeliveryTest
php artisan test --filter=Phase7FulfillmentTest
```

Coverage:

- Wrong SKU scan → 422
- Pick scan → ship → assign driver → reschedule → OFD → deliver with POD
- Drivers list

## Manual E2E

1. Staff: Admin Web fulfillment desk — pick → pack → ship → assign → OFD → deliver.  
2. Staff: Admin Mobile More → Fulfillment → picking queue → scan SKU → pack/ship.  
3. Customer: Web/App order detail timeline updates (no fake ETA).  
4. Fail delivery → retry; unauthorized staff → 403.

## Seed

No production seed required. Dev: existing sample users + Phase 7 flow.  
See `PHASE_20_DATABASE_PROPOSAL.md` — only `assigned_driver_user_id` migration.
