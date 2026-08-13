# QA-09 Test Matrix — Admin Mobile Ops

**Date:** 2026-08-12 · Device `2d3714f` · API `192.168.1.3:8000`

| Flow | API | Unit | Device |
|------|-----|------|--------|
| Login valid/invalid messaging | PASS | PASS | PASS |
| Refresh single-flight / failure clears session | PASS | PASS | N/A |
| Dashboard KPIs | PASS | N/A | PASS |
| Orders list + detail | PASS | N/A | PASS |
| Inventory list | PASS | N/A | PASS |
| Scan / SKU lookup | PASS | N/A | PASS |
| Fulfillment hub + queue + order | PASS | PASS (gates) | PASS |
| Pick→pack→ship full mutation | PASS (QA-05) | PASS | PARTIAL* |
| Purchase orders | PASS | PASS (perm OR) | PASS |
| Suppliers | PASS | N/A | PASS |
| Warehouses | PASS | N/A | PASS |
| Notifications | PASS | N/A | PASS |
| Logout | PASS | N/A | PASS |
| 403 ≠ logout | PASS | PASS | N/A |
| QA-02…QA-08 regression | PASS | PASS | N/A |
| Razorpay live | — | — | UNVERIFIED |

\* Device showed fulfillment actions (`Complete pick`); full chain not re-mutated this session.

## Commands

```text
php artisan test --filter='Qa09|Qa08|Qa07|Qa02|Qa03|Qa04|Qa05'  # 44 PASS
cd apps/nursery_admin_mobile && flutter test && flutter analyze
flutter build apk --debug --dart-define=API_BASE_URL=http://192.168.1.3:8000/api/v1
cd apps/nursery-admin && npm run test:unit
```
