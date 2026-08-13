# PHASE 20 — Delivery Architecture

**Reuse Phase 7:** Order state machine + `shipments` / `shipment_events` + `orders.meta.fulfillment`.

```
Order (SM)
  CONFIRMED → PROCESSING (pick) → PACKED → SHIPPED → OUT_FOR_DELIVERY → DELIVERED
                                                     ↘ DELIVERY_FAILED → retry OFD
Shipment (row + events)
  tracking, ETA, assigned_driver_user_id, meta.pod / meta.reschedule
Inventory
  reserve @ checkout · commit @ payment · pick/pack do NOT re-deduct
```

Clients consume `/api/v1/admin/fulfillment/*` and customer `/orders/{id}/tracking`.

**No** parallel `deliveries` table. Drivers = staff `users` with delivery roles.
