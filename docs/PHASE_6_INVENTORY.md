# PHASE 6 — Inventory, Warehouse & Procurement

**Status:** PARTIALLY COMPLETE  
**Date:** 2026-08-11

## 1. Current inventory architecture

Stock is stored **per product (optional variant) per warehouse** in `inventory_items`.

| Field | Meaning |
|-------|---------|
| `qty_on_hand` | Physical units in the warehouse |
| `qty_reserved` | Units held for unpaid / in-flight orders |
| `qty_damaged` | Unsellable portion still on-hand |
| `low_stock_threshold` | Reorder level |

**Available / sellable** = `max(0, on_hand − reserved − damaged)`.

Backend (`InventoryService`) is the single source of truth. Clients never write MySQL.

## 2. Inventory model

Reuse existing tables. No second inventory system.

- List / show / threshold update / reorder suggestions via Admin inventory API
- Adjustments always create `stock_movements`

## 3. Stock movement model

Immutable ledger in `stock_movements`. Types used in Phase 6:

| Type | Use |
|------|-----|
| `reserve` / `release` / `sale` | Checkout lifecycle |
| `purchase_in` | PO receiving |
| `damage` / `loss` | Write-offs |
| `adjust_in` / `adjust_out` | Manual / reconciliation |
| `return_in` | Cancelled committed sales (existing) |
| `supplier_return` | Reserved for future |

History is not edited; corrections are new movements.

## 4. Reservation model

Tied to order lifecycle (not a separate reservation table):

1. Checkout → `reserve` (increases `qty_reserved`)
2. Payment success → `commit` (decreases on-hand + reserved, movement `sale`)
3. Cancel unpaid → `release`
4. Cancel committed → adjust `return_in` (existing Phase 2/3 behavior)

**Expiry:** `php artisan inventory:release-expired-reservations --hours=24`  
Scheduled hourly (`withoutOverlapping`). Marks old `PENDING_PAYMENT` as `PAYMENT_FAILED` after release.

Cart does **not** reserve stock (pre-existing design).

## 5. Warehouse model

`warehouses`: code, name, city, is_default, status (`active`/`inactive`).

Admin CRUD: `GET/POST /admin/warehouses`, `PUT /admin/warehouses/{id}`.

Inactive warehouses cannot receive purchase stock (`purchase_in`).

Zones/racks: **not implemented** (deferred).

## 6. Supplier model

Existing `suppliers` + Admin UI. Permissions: `inventory.adjust` (create/manage).

Supplier product catalog / lead times: **not a separate table** — deferred.

## 7. Procurement model

Procurement = reorder suggestions → create PO → approve → receive.

No automatic PO creation from low stock.

## 8. Purchase order lifecycle

Statuses: `draft` → `ordered` → `approved` → `partially_received` → `received` | `cancelled`.

| Action | From | Permission |
|--------|------|------------|
| Create | — | `inventory.adjust` |
| Approve | draft, ordered | `inventory.adjust` |
| Cancel | draft, ordered, approved | `inventory.adjust` |
| Receive | ordered, approved, partially_received | `inventory.adjust` |

Partially received POs cannot be cancelled.

## 9. Receiving lifecycle

- Partial receive supported (per line: quantity + optional damaged)
- Full receive (no items body) remains for backward compatibility
- Accepted qty → `purchase_in`
- Damaged qty → `purchase_in` then `damage` (on-hand includes damaged; sellable excludes it)
- Idempotent reference keys on receive lines reduce duplicate movement risk

## 10. Reconciliation

`POST /admin/inventory/reconcile` with `inventory_item_id`, `physical_qty`, `reason`.

Difference posted as `adjust_in` / `adjust_out`. Never silent overwrite of `qty_on_hand`.

Admin UI: `/inventory/reconciliation`.

## 11. Fulfillment integration

Existing order state machine / shipment flows unchanged. Phase 6 does **not** add pick/pack Admin screens (gap — see `PHASE_6_API_GAPS.md`).

Inventory allocation remains reserve → commit on payment.

## 12. API endpoints

| Method | Path | Permission |
|--------|------|------------|
| GET | `/admin/inventory` | inventory.view |
| GET | `/admin/inventory/{id}` | inventory.view |
| GET | `/admin/inventory/movements` | inventory.view |
| GET | `/admin/inventory/reorder-suggestions` | inventory.view |
| POST | `/admin/inventory/adjust` | inventory.adjust |
| POST | `/admin/inventory/reconcile` | inventory.adjust |
| PUT | `/admin/inventory/{id}/threshold` | inventory.adjust |
| GET/POST | `/admin/warehouses` | view / adjust |
| PUT | `/admin/warehouses/{id}` | inventory.adjust |
| GET/POST | `/admin/suppliers` | inventory.adjust* |
| GET/POST | `/admin/purchase-orders` | inventory.adjust |
| GET | `/admin/purchase-orders/{id}` | inventory.adjust |
| POST | `/admin/purchase-orders/{id}/approve` | inventory.adjust |
| POST | `/admin/purchase-orders/{id}/cancel` | inventory.adjust |
| POST | `/admin/purchase-orders/{id}/receive` | inventory.adjust |

\* Supplier list historically gated with adjust; view-only procurement permissions not split yet.

## 13–14. Database / indexes

No new tables in Phase 6. See `PHASE_6_DATABASE_CHANGES.md`.

## 15. Transactions

Reserve, commit, release, adjust, reconcile, PO create/receive/approve/cancel use `DB::transaction` + `lockForUpdate` where stock is mutated.

## 16. Concurrency

Stock mutations lock `inventory_items` rows. Concurrent reserves of 7 + 5 against 10 → one succeeds, one fails with insufficient inventory.

## 17. Idempotency

- Adjust with matching `reference_type` + `reference_id` + `qty_delta` + type → replay, no second movement
- Optional `idempotency_key` on adjust maps to reference fields
- PO receive uses deterministic reference ids per cumulative receive point

## 18. Permissions

Existing: `inventory.view`, `inventory.adjust`.  
Fine-grained `procurement.*` / `warehouse.*` / `fulfillment.*` **not** seeded (documented gap).

## 19. Audit logging

Uses existing `AuditLogger`: warehouse create/update, supplier CRUD, PO create/approve/cancel/receive, inventory adjust/reconcile/threshold.

## 20. Testing

`tests/Feature/Phase6InventoryTest.php` — reserve/release/commit, concurrent reserve, partial/full receive, damaged receive, reconcile, RBAC, warehouse + reorder, adjust idempotency.

## 21. Known limitations

- No warehouse zones/racks
- No supplier returns workflow
- Customer returns do not auto-restock
- No inventory valuation / COGS / FIFO
- No pick/pack Admin UI
- No dedicated `procurement.*` permission slugs
- Reservation expiry is time-based on `PENDING_PAYMENT`, not a separate `expires_at` column
- Supplier cost never exposed on customer APIs (unchanged)
