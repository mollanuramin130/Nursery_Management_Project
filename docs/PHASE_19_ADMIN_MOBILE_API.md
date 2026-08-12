# PHASE 19 — Admin Mobile API Contract

Base: `/api/v1`  
Headers: `Authorization: Bearer`, `X-Platform`, `X-App-Version`, `X-Client: admin-mobile`, `X-Request-Id`

## Auth

| Call | Path |
|------|------|
| Login | `POST /auth/login` |
| Me | `GET /auth/me` |
| Refresh | `POST /auth/refresh` |
| Logout | `POST /auth/logout` |
| Device | `POST /devices/register`, `POST /devices/deactivate` |

## Operations (Phase 6/18 Admin APIs)

| Area | Paths |
|------|-------|
| Orders | `GET /admin/orders`, `GET /admin/orders/{id}`, `POST /admin/orders/{id}/status` |
| Inventory | `GET /admin/inventory`, `GET /admin/inventory/dashboard`, `GET /admin/inventory/{id}`, `POST /admin/inventory/adjust`, `GET /admin/inventory/movements` |
| PO | `GET /admin/purchase-orders`, `GET /admin/purchase-orders/{id}`, `POST /admin/purchase-orders/{id}/receive` |
| Suppliers | `GET /admin/suppliers` |
| Warehouses | `GET /admin/warehouses` |
| Notifications | `GET /notifications` |

Mutations use server validation; adjust supports `idempotency_key`.

## Env

```
--dart-define=API_BASE_URL=https://api.example.com/api/v1
```

Release builds refuse localhost / cleartext defaults.
