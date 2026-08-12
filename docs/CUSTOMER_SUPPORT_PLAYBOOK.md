# Customer Support Playbook — GreenLeaf Nursery (Soft Launch)

For support/ops staff using **Admin Portal** with least privilege.  
Backend RBAC remains authoritative — do not share super_admin casually.

---

## Lookup cheatsheet

| Issue | Where | Notes |
|-------|-------|-------|
| Order | Admin → Orders → search order # / email | Copy order id + status |
| Payment | Order detail + Razorpay dashboard | Never ask customer for full card; use payment id |
| Refund | Admin → Refunds / Return detail | Follow status machine; do not invent amounts |
| Cancel | Order detail (if status allows) | Inventory rules apply in API |
| Return | Admin → Returns | Approve → pickup → receive → inspect |
| Account | Customers | Prefer password-reset flow; no password sharing |
| Coupon | Coupons + order discount lines | Expired/usage limits enforced by API |
| Delivery | Fulfillment / shipment tracking | Update status via Admin actions only |

Always record **order number**, **customer email**, and **X-Request-Id** if customer reports a site/app error.

---

## Common scripts (tone only — not legal advice)

1. Acknowledge issue + order number  
2. State what you will check  
3. Do not promise refunds/shipping times outside policy  
4. Escalate CRITICAL payment mismatches to engineering  

---

## Escalation

- Payment/order mismatch → engineering + `PRODUCTION_ISSUE_BACKLOG.md`  
- Suspected security issue → stop, escalate, do not tip attacker  
- Data restore requests → never restore over prod without runbook  

---

## Permissions reminder

Grant only needed roles (`orders.*`, `returns.*`, `payments.refund`, etc.).  
Frontend hiding is not security.
