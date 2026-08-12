# PHASE 2 — Payment / Refund Limitations (updated Phase 3)

## Current behavior (after Phase 3)

`POST /api/v1/admin/refunds`:

| Environment | Behavior |
|-------------|----------|
| **production** | Refused (`503 PAYMENT_GATEWAY_UNAVAILABLE`) — no stub payout |
| **local/staging** | Creates refund with `status=recorded_local`, `meta.mode=local_stub` |

Local stub does **not**:

- Call Razorpay refund API  
- Transition the order to `REFUNDED`  
- Claim money was returned  

Payment auto-link looks for payment status `success` or `captured`.

## Customer pay path

- Initiate/verify remain server-authoritative  
- Frontend cannot set `payment_status`  
- Webhooks require HMAC secret unless `PAYMENT_ALLOW_UNSIGNED_WEBHOOKS=true` (forbidden mindset for production)

## Production implication

Do **not** market Admin refunds as live PSP refunds until gateway integration is built.
