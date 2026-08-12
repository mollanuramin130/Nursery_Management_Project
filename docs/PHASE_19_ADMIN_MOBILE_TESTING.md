# PHASE 19 — Admin Mobile Testing

## Automated

```bash
cd apps/nursery_admin_mobile
flutter test
flutter analyze lib test
```

Coverage today: RBAC helpers, models, AppConfig (unit). Widget E2E against live API is manual.

## Manual E2E (API running)

1. Staff login → dashboard KPIs  
2. Orders → detail → valid status update  
3. Inventory → search → adjust with confirm → refresh  
4. PO → receive partial → remaining updates from API  
5. Customer account login → rejected  
6. Inventory staff without `orders.view` → Orders tab hidden; direct API still 403 if called  

## Security checks

- Tokens only in secure storage  
- No password/token logging in app code  
- Release assert blocks emulator API URLs  
