# QA-05 Test Matrix — Inventory + Fulfillment + Delivery

**Date:** 2026-08-12

| Flow | API | Web | Mobile | Admin Web | Admin Mobile | Unit | Integration | Real Device |
|------|-----|-----|--------|-----------|--------------|------|-------------|-------------|
| Stock reserve / sellable math | PASS | N/A | N/A | PASS | PASS | PASS | PASS | N/A |
| Last-unit concurrency | PASS | N/A | N/A | N/A | N/A | PASS | PASS | N/A |
| Duplicate reserve idempotent | PASS | N/A | N/A | N/A | N/A | PASS | PASS | N/A |
| COD commit stock | PASS | N/A | N/A | N/A | N/A | PASS | PASS | N/A |
| Cancel restock (CONFIRMED) | PASS | N/A | N/A | N/A | N/A | PASS | PASS | N/A |
| Expired reservation release | PASS | N/A | N/A | N/A | N/A | PASS | PASS | N/A |
| Invalid order transition | PASS | N/A | N/A | N/A | N/A | PASS | PASS | N/A |
| Pick → pack → ship → OFD → deliver | PASS | N/A | N/A | PASS | PASS* | PASS | PASS | UNVERIFIED |
| Delivery fail → retry | PASS | N/A | N/A | PASS | PASS* | PASS | PASS | UNVERIFIED |
| Permission deny pick | PASS | N/A | N/A | PASS | PASS* | PASS | PASS | N/A |
| Customer tracking sync | PASS | PASS† | PASS† | PASS | N/A | PASS | PASS | UNVERIFIED |
| PO receive partial/full | PASS | N/A | N/A | PASS | PASS* | PASS | PASS | N/A |
| QA-02/03/04 regression | PASS | PASS | PASS | N/A | N/A | PASS | PASS | N/A |

\* Admin Mobile: unit action gates PASS; interactive UI smoke UNVERIFIED this phase.  
† Customer Web/Mobile: order detail/tracking consume API; live API customer status PASS after admin deliver.

## Commands

```text
php artisan test --filter='Qa05|Phase6|Phase7|Phase18|Phase20|Qa04|Qa03|Qa02'  # 54 PASS
flutter test (admin_mobile fulfillment_actions + permissions)
flutter test (nursery_app checkout/cart)
npm run test:unit (nursery-web)
# Live: ORD-20260812-00007 pick→…→DELIVERED; customer tracking DELIVERED
```
