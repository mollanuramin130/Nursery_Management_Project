# PHASE 19 — Admin Mobile Architecture

**App path:** `apps/nursery_admin_mobile`  
**Package:** `nursery_admin_mobile`  
**Display name:** GreenLeaf Ops

## Stack

| Layer | Choice |
|-------|--------|
| Framework | Flutter / Dart SDK ^3.10.9 (matches customer app) |
| State | Provider + ChangeNotifier |
| Routing | go_router (shell + nested routes) |
| HTTP | Dio + JSON envelope unwrap |
| Tokens | flutter_secure_storage (`gl_ops_*` keys) |
| Scan | mobile_scanner (SKU lookup; no dedicated barcode column) |

## Architecture rule

```
GreenLeaf Ops (Flutter)
        ↓ HTTPS JSON
Laravel /api/v1
        ↓
Business logic + MySQL
```

No second backend. No client-authoritative stock/order totals.

## Module layout

```
lib/
  core/          config, api_client, session_storage, permissions
  theme/         operational Material 3 theme
  models/
  providers/     auth + orders/inventory/purchasing/dashboard
  features/      auth, dashboard, orders, inventory, purchasing, more, shell
  shared/        chips, empty/error, confirm dialogs
```

## Auth

1. `POST /auth/login` with `device.platform` + `app: admin_mobile`
2. Staff gate: reject pure `customer` accounts (same idea as Admin Web `isStaffUser`)
3. `GET /auth/me` loads roles + permissions
4. 401 → refresh → retry; failure → clear secure storage → login
5. Device register/deactivate best-effort via existing notification device APIs

## Navigation (permission-aware)

Bottom shell: Dashboard · Orders (if `orders.view`) · Stock (if `inventory.view`) · More  

More hosts POs, suppliers, warehouses, low stock, notifications, profile.
