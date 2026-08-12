# PHASE 18 — API Changes

## New / extended Admin endpoints

| Method | Path | Notes |
|--------|------|-------|
| GET | `/admin/inventory/dashboard` | Ops KPIs |
| GET | `/admin/inventory/dead-stock` | Rule-based dead stock |
| GET | `/admin/inventory` | Pagination + `status` filter |
| GET | `/admin/inventory/{id}` | Includes `recent_movements` |
| GET | `/admin/inventory/movements` | Filters: type, actor, dates; `qty_before`/`qty_after` |
| GET/POST | `/admin/inventory/transfers` | Warehouse transfers |
| POST | `/admin/inventory/transfers/{id}/ship\|complete\|cancel` | Lifecycle |
| GET | `/admin/suppliers/{id}` | Detail + performance + products |
| POST | `/admin/suppliers/{id}/products` | Supplier-product upsert |

Existing PO receive / inventory adjust remain authoritative.

Permissions (additive): `inventory.transfer`, `inventory.receive`, `warehouses.*`, `suppliers.*`, `purchase_orders.*` — OR with legacy `inventory.adjust` where noted.
