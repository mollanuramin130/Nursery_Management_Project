# PHASE 6 — API Gaps

**Date:** 2026-08-11

Gaps intentionally deferred after auditing existing Laravel API + Admin portal.

---

### 1. Warehouse zones / locations

| | |
|--|--|
| **Feature** | Zone → rack location hierarchy |
| **Current** | Warehouse only |
| **Missing** | location CRUD, inventory by location |
| **Database** | Would need `warehouse_locations` + FK on inventory |
| **Admin** | Location picker on receive/adjust |
| **Customer** | None |
| **Recommendation** | Future phase when multi-bin picking is required |

### 2. Fine-grained procurement permissions

| | |
|--|--|
| **Feature** | `procurement.view/create/approve/receive`, `warehouse.manage` |
| **Current** | `inventory.view` / `inventory.adjust` cover all |
| **Missing** | Separate permission slugs + seed |
| **Database** | `permissions` rows only |
| **Admin** | Nav already sectioned; gates reuse inventory.* |
| **Recommendation** | Seed aliases when ops roles expand |

### 3. Supplier product catalog

| | |
|--|--|
| **Feature** | Supplier SKU price, lead time, MOQ |
| **Current** | Unit cost entered per PO line |
| **Missing** | `supplier_products` table + endpoints |
| **Customer** | Must never see cost |
| **Recommendation** | Add when multi-supplier sourcing is frequent |

### 4. Supplier returns

| | |
|--|--|
| **Feature** | Return stock to supplier |
| **Current** | Movement type `supplier_return` reserved in adjust reason map; no PO return workflow |
| **Missing** | Return document + approve + receive reverse |
| **Recommendation** | Documented for future phase |

### 5. Customer return restock

| | |
|--|--|
| **Feature** | Auto restock on approved return |
| **Current** | Returns create request; stock not auto-increased |
| **Missing** | Policy-driven restock (sellable vs damaged) |
| **Customer** | Return UX exists (Phase 4) |
| **Recommendation** | Tie to return disposition in a later phase |

### 6. Pick / pack fulfillment UI

| | |
|--|--|
| **Feature** | `/admin/fulfillment/picking` |
| **Current** | Order status machine + shipments exist |
| **Missing** | Dedicated pick list endpoints/UI |
| **Recommendation** | Build on existing order transitions — do not invent a second lifecycle |

### 7. Reservation `expires_at` column

| | |
|--|--|
| **Feature** | Per-reservation expiry timestamp |
| **Current** | Hourly job on `PENDING_PAYMENT.created_at` |
| **Missing** | Dedicated expiry fields |
| **Recommendation** | Sufficient for nursery checkout; extend if cart holds are added |

### 8. Inventory valuation / COGS

| | |
|--|--|
| **Feature** | FIFO / weighted average valuation |
| **Current** | PO `unit_cost` stored; no valuation engine |
| **Missing** | Cost layers + reporting |
| **Recommendation** | Do not implement until cost method is chosen by business |

### 9. Operational notification events

| | |
|--|--|
| **Feature** | Low stock, PO overdue, discrepancy alerts |
| **Current** | Phase 4 notification infrastructure for customers; Admin sees low-stock list |
| **Missing** | Staff push/email for procurement events |
| **Recommendation** | Hook into existing notification module when staff channels exist |

### 10. Transfer between warehouses

| | |
|--|--|
| **Feature** | `TRANSFER_IN` / `TRANSFER_OUT` |
| **Current** | Movement types conceptually reserved; no transfer API |
| **Missing** | Atomic dual-warehouse adjust |
| **Recommendation** | Implement when second active warehouse is used in production |

### 11. Paginated inventory list

| | |
|--|--|
| **Feature** | Server-side pagination on `GET /admin/inventory` |
| **Current** | Full filtered list (OK for current catalog size) |
| **Missing** | `page` / `per_page` meta |
| **Recommendation** | Add when SKU count grows; movements & POs already paginated |
