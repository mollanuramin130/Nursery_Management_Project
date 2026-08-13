# QA-08 Test Matrix

**Date:** 2026-08-12

| Flow | API | Admin Web | Unit | Integration | Notes |
|------|-----|-----------|------|-------------|-------|
| Admin login / bad password | PASS | PASS* | N/A | PASS | *API; UI existing |
| Users list / filter / pagination | PASS | PASS | PASS | PASS | |
| User create / update / status / roles | PASS | PASS | N/A | PASS | |
| Roles catalog `GET /admin/roles` | PASS | PASS | PASS | PASS | Additive |
| 403 without `users.manage` | PASS | PASS | PASS | PASS | Customer → 403 |
| Refund production stub refuse | PASS | PASS | PASS | PASS | UI + API |
| Refund non-prod stub | PASS | PASS | PASS | Prior Phase9 | Intentional |
| Dashboard / orders / products / inventory | PASS | PASS* | N/A | PASS | *API smoke |
| Fulfillment | PASS | PASS* | N/A | PASS | `GET /admin/fulfillment` |
| Returns / refunds list | PASS | PASS* | N/A | PASS | |
| Settings | PASS | PASS* | N/A | PASS | |
| QA-02…QA-07 regression | PASS | N/A | PASS | PASS | 39 PHPUnit |
| Razorpay live | — | — | — | — | UNVERIFIED |
| Customer Mobile device | — | — | — | — | UNVERIFIED |
| Admin Mobile device | — | — | — | — | UNVERIFIED |

## Commands

```text
php artisan test --filter='Qa08|Qa07|Qa02|Qa03|Qa04|Qa05'  # 39 PASS
cd apps/nursery-admin && npm run test:unit && npm run build
```
