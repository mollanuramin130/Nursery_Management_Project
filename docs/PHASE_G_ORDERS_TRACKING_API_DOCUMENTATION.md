# Phase G — Orders + Tracking + Cancel + Reorder API

**Base:** `/api/v1`  
**Auth:** Bearer access token (`auth:api` + `active.user`)  
**Envelope:** `{ success, message, data, errors, meta }`

Statuses are **UPPERCASE** server values (`CONFIRMED`, `SHIPPED`, …). Clients map labels for display only.

---

## State machine (customer-facing)

```
PENDING_PAYMENT → CONFIRMED → PROCESSING → PACKED → SHIPPED → OUT_FOR_DELIVERY → DELIVERED
       ↓              ↓            ↓          ↓
   CANCELLED      CANCELLED    CANCELLED  CANCELLED
PAYMENT_FAILED → CANCELLED (or retry payment → CONFIRMED)
```

Customer cancel **allowed:** `PENDING_PAYMENT`, `PAYMENT_FAILED`, `CONFIRMED`, `PROCESSING`, `PACKED`  
Customer cancel **blocked:** `SHIPPED`, `OUT_FOR_DELIVERY`, `DELIVERED`, `CANCELLED`, …

Admin/fulfillment transitions remain server-side only.

---

## GET `/orders`

List authenticated user's orders.

| Query | Type | Notes |
|-------|------|--------|
| `page` | int | Default 1 |
| `per_page` | int | 1–50, default 20 |
| `status` | string | Exact UPPERCASE status, or `active` (pre-delivery open statuses) |
| `q` | string | Search `order_number` (server-side) |

**Auth:** required  
**Success 200:** `data` = array of summaries; `meta.pagination` = `{ current_page, per_page, total, last_page }`

Summary fields include: `id`, `order_number`, `status`, `payment_status`, `payment_method`, `grand_total`, `currency`, `item_count`, `thumbnail`, `preview_name`, `placed_at`, `estimated_delivery`, `can_cancel`, `can_reorder`.

**Security:** scoped to `user_id`. No other users' orders.

---

## GET `/orders/{id}`

Order detail for owner only.

**Auth:** required  
**Success 200:** full detail including `items` (historical prices), `payment`, `shipping_address` (snapshot), `shipment`, `tracking` (timeline), `status_history`, `actions.can_cancel` / `actions.can_reorder`, `cancel_reason`, `cancelled_at`.

**Errors:**
- `404` — not found **or** not owned (IDOR-safe; no leak)

---

## GET `/orders/{id}/tracking`

Tracking payload only (also embedded in detail as `tracking`).

**Auth:** required (owner)  
**Success 200 `data`:**

```json
{
  "order_id": 1001,
  "order_number": "ORD-…",
  "current_status": "SHIPPED",
  "estimated_delivery": "2026-08-15",
  "shipment": {
    "carrier": "…",
    "tracking_number": "…",
    "tracking_url": "…"
  },
  "timeline": [
    {
      "status": "CONFIRMED",
      "title": "Order confirmed",
      "description": "…",
      "completed": true,
      "current": false,
      "created_at": "2026-08-10T10:30:00+05:30"
    }
  ]
}
```

Timeline timestamps come from `order_status_histories` when present. Carrier fields only when a `shipments` row exists.

---

## POST `/orders/{id}/cancel`

Server decides cancellation eligibility. Client must **not** send a status field.

**Body:**

```json
{
  "reason_code": "changed_mind",
  "reason": "optional free text when reason_code=other"
}
```

`reason_code` enum: `changed_mind`, `ordered_by_mistake`, `better_price`, `delivery_slow`, `no_longer_needed`, `other`.

**Success 200:** cancelled order + `payment_status`:
- Paid Razorpay cancel → `refund_pending` (not `refunded`)
- COD / unpaid → `not_required`

**Errors:**
- `409` + code `ORDER_CANCELLATION_NOT_ALLOWED` — past cancel window
- `409` + `CONFLICT` — already cancelled
- `404` — not found / not owned

**Server side effects (transactional):**
- Status → `CANCELLED` + history note
- Unpaid: release inventory reservation
- Confirmed/processing/packed: restock + delete `coupon_redemptions` for the order
- Paid online: mark payment `refund_pending` (no fake completed refund)

---

## POST `/orders/{id}/reorder`

Adds purchasable lines to the **current cart** at **current** catalog prices/stock. Does **not** create a new order.

**Body:** `{}`  
**Success 200:**

```json
{
  "cart": { "...existing cart contract..." },
  "reorder_summary": {
    "added": [{ "product_id": 1, "quantity": 2, "name": "…" }],
    "unavailable": [{ "product_id": 2, "reason": "out_of_stock" }],
    "price_changed": [{ "product_id": 1, "previous_unit_price": 400, "current_unit_price": 450 }],
    "quantity_adjusted": []
  }
}
```

**Security:** owner only. Partial success is normal when some products are discontinued/OOS.

---

## Related (Phase F, unchanged)

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/orders` | Place order |
| POST | `/orders/{id}/retry-payment` | Retry unpaid online payment |
| POST | `/checkout/preview` | Preview totals |

Invoice download is **not** implemented — do not expose a fake button.

---

## Client rules

1. Use `can_cancel` / `can_reorder` for UI only; always handle API rejection.
2. Historical line prices from order items; never replace with live product price on detail.
3. Reorder → cart → normal checkout/payment.
4. Website and Android share these endpoints only.
