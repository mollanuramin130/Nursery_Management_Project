# PHASE 6 — Database Changes

**Date:** 2026-08-11

## Summary

Phase 6 **adds no new migrations**. Inventory, warehouses, stock movements, suppliers, and purchase orders already existed. Changes are service-layer, API, Admin UI, scheduling, and tests.

---

## Tables reused (no schema change)

### `warehouses`

| Columns (existing) | Notes |
|--------------------|-------|
| id, code, name, city, is_default, status, meta, timestamps | CRUD via API |

**Indexes:** existing unique/code as defined in foundation migration.  
**Reason for Phase 6 use:** Admin warehouse management + PO receive target.  
**Existing alternative:** seed-only warehouses — insufficient for ops.  
**Migration safety:** N/A (no migration).  
**Rollback:** disable Admin warehouse routes.

### `inventory_items`

| Columns | Notes |
|---------|-------|
| warehouse_id, product_id, product_variant_id | Unique balance key |
| qty_on_hand, qty_reserved, qty_damaged | Sellable formula |
| low_stock_threshold | Reorder level |
| version | Optimistic counter on mutate |

**Indexes:** unique `(warehouse_id, product_id, product_variant_id)`.  
**Reason:** Foundation for all Phase 6 ops.  
**No additive columns** (no `expires_at` on reservations — order-level expiry job instead).

### `stock_movements`

Ledger for every stock-changing operation. Types expanded in application code only (`purchase_in`, `adjust_in`, `adjust_out`, `damage`, etc.).  
**Indexes:** existing FKs / query filters as in foundation.  
**Immutable:** no update/delete Admin API.

### `suppliers`

Unchanged. Soft-delete if present in model.

### `purchase_orders` / `purchase_order_items`

| Column | Phase 6 behavior |
|--------|------------------|
| status | Now includes `partially_received`, `approved` transitions |
| quantity_ordered / quantity_received | Partial receive updates received only |
| warehouse_id | Required for receive |

**Foreign keys:** supplier, warehouse, product — unchanged.  
**Migration safety:** N/A. Status values are string columns; new statuses are application-level.

---

## Indexes considered (not added)

| Candidate | Decision |
|-----------|----------|
| `stock_movements (product_id, created_at)` | Defer until movement volume warrants |
| `purchase_orders (status, created_at)` | List already OK at current scale |
| `inventory_items (low_stock_threshold)` | Reorder suggestions scan in PHP; optimize later if SKU count large |

Inspect existing indexes before adding any in a future migration.

---

## Scheduled job (no table)

`inventory:release-expired-reservations` — uses `orders.status` + `orders.created_at`. No new columns.

---

## Rollback considerations

1. Revert API/service/Admin commits.  
2. Leave historical `stock_movements` and PO statuses as-is (data remains valid).  
3. Do not drop tables.
