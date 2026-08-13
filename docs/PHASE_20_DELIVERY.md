# PHASE 20 — Delivery

## Shipment = delivery record

Assignment: `shipments.assigned_driver_user_id` → staff user.  
POD: `shipments.meta.pod`.  
Reschedule: updates `eta_date` + event `RESCHEDULED` + customer notification.

## Customer tracking

Uses backend timestamps from order status history + `shipment_events`.  
No fabricated ETA. Display `shipments.eta_date` only when set.

## Gaps (documented)

| Gap | Notes |
|-----|-------|
| Delivery slots / capacity | Not in DB — do not fake booking UI |
| External courier adapters | `ShippingProvider` still Internal only |
| Continuous GPS / fleet | Not required |
| Photo POD upload UX | API accepts URL; upload pipeline reuse later |
| Dedicated barcode column | Scan matches product SKU |
| Plant-specific return rules | Phase 8 returns; product-policy matrix still gap |

Returns/refunds: reuse Phase 8 Admin + customer APIs (not reimplemented).
