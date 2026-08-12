# PHASE 7 — API Gaps

**Date:** 2026-08-11

### 1. External courier integrations

| | |
|--|--|
| **Feature** | Delhivery / Shiprocket / BlueDart APIs |
| **Current** | `ShippingProvider` + `InternalDeliveryProvider` |
| **Missing** | Provider adapters, webhooks, label PDF |
| **Recommendation** | Implement providers behind same interface; keep secrets server-side |

### 2. Serviceability by PIN

| | |
|--|--|
| **Feature** | Delivery zone / PIN check at checkout |
| **Current** | Shipping methods exist; no PIN matrix |
| **Missing** | `serviceable_pincodes` + checkout validation |
| **Recommendation** | Simple PIN table before GIS |

### 3. Multi-package customer tracking

| | |
|--|--|
| **Feature** | Multiple packages per order with per-package tracking |
| **Current** | One shipment row per order (`hasOne` latest); `package_count` meta |
| **Missing** | Multi-shipment model + customer UX |
| **Recommendation** | When nursery ships split consignments regularly |

### 4. Return reverse logistics

| | |
|--|--|
| **Feature** | Pickup scheduling after return approval |
| **Current** | Return request API from `DELIVERED` |
| **Missing** | Reverse shipment + inspection restock |
| **Recommendation** | Next commerce/ops phase |

### 5. Fulfillment duration analytics

| | |
|--|--|
| **Feature** | Avg order→picked→packed→shipped→delivered |
| **Current** | Operational counts in analytics + fulfillment dashboard |
| **Missing** | Histogram / percentile APIs from status history |
| **Recommendation** | Derive from `order_status_histories` in Phase 5 analytics extension |

### 6. Admin push for new fulfillment work

| | |
|--|--|
| **Feature** | Staff alert when order becomes CONFIRMED |
| **Current** | Customer notifications on ship/deliver |
| **Missing** | Staff notification channel |
| **Recommendation** | Hook NotificationService to ops roles |

### 7. Cart-level stock hold

| | |
|--|--|
| **Feature** | Reserve at add-to-cart |
| **Current** | Reserve at checkout only (Phase 6) |
| **Impact** | Unrelated to pick/pack; documented for clarity |
| **Recommendation** | Keep unless oversell from cart abandonment becomes material |

### 8. Driver / fleet mobile app

| | |
|--|--|
| **Feature** | Driver app, route optimization, GPS |
| **Current** | Explicit Phase 7 stop condition |
| **Recommendation** | Future logistics phase |
