# PHASE 6 — Inventory Architecture Audit

**Date:** 2026-08-11

## Existing foundation (reuse)

| Layer | Status |
|-------|--------|
| `inventory_items` | per product (+ optional variant) **per warehouse** |
| Sellable | `max(0, on_hand - reserved - damaged)` |
| Ledger | `stock_movements` |
| Reserve / release / commit | Checkout + Payment |
| Cancel restock | release (unpaid) or adjust `return_in` (committed) |
| Suppliers | CRUD API + Admin UI |
| Purchase orders | Create/list/full-receive API; **no Admin UI** |
| Warehouses | Table + model; **no CRUD API/UI** |
| Locations/zones | **Absent** — deferred |
| Reservation expiry job | **Absent** |
| Customer returns restock | Not automatic — deferred (admin return lifecycle) |

## Phase 6 implementation plan

1. Fix movement-type fidelity + adjust idempotency via reference
2. Fix damage accounting (no double penalty)
3. Partial PO receiving + PO show/cancel
4. Warehouse CRUD
5. Reconciliation + reorder suggestions
6. Reservation expiry artisan command
7. Admin: warehouses, purchase orders, reconciliation, inventory polish
8. No zones/racks, no supplier returns, no profit valuation, no second order lifecycle
