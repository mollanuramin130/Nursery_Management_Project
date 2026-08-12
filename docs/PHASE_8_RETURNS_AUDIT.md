# PHASE 8 — Returns & Refunds Architecture Audit

**Date:** 2026-08-11

## What already exists

| Capability | Status |
|------------|--------|
| Customer create return | `ReturnService::request` → `RETURN_REQUESTED` |
| Reasons | damaged, wrong_item, not_as_described, changed_mind, other |
| Order SM | `DELIVERED` → `RETURN_REQUESTED` → `RETURNED` \| `REFUNDED` |
| Admin refund stub | `AdminRefundService` (`recorded_local` non-prod) |
| Inventory `return_in` / `damage` | Exists; used for cancel, not customer returns |
| ShippingProvider | Reuse for reverse pickup tracking |
| Customer web + Flutter return form | Exists |
| Admin `/refunds` | List + create stub |
| Analytics returns | Exists |

## Gaps (Phase 8)

Admin approve/reject/pickup/receive/inspect, restock disposition, reverse shipment, return quantity across multiple returns, refund linked to return + cumulative cap, reject restores order to `DELIVERED`, `returns.*` permissions, Admin returns UI.

## Design decisions

1. Extend `return_requests.status` (string) — no parallel order SM.
2. Store inspection/disposition/reverse shipment in `meta` (+ light columns for timestamps).
3. Restock only after inspect with disposition SELLABLE → `return_in`; DAMAGED → `damage` path.
4. Refund via existing `AdminRefundService` with `meta.return_request_id` + idempotency.
5. No store-credit wallet. No live Razorpay refund in production (keep stub policy).
