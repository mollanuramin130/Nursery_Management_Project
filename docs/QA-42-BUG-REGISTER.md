# QA-42 BUG REGISTER

| ID | Severity | Client | Screen | Root cause | Fix | Test | Evidence | Status |
|----|----------|--------|--------|------------|-----|------|----------|--------|
| QA-42-001 | High | Admin Mobile | Orders/Inventory/Purchasing lists+details | Soft refresh error swapped to full OpsError | Gate `error && empty`; keep list | qa42_stale_banner | code | FIXED |
| QA-42-002 | Med | Admin Mobile | Lists with data | No non-blocking failure UX | `OpsStaleBanner` | qa42_stale_banner | code | FIXED |
| QA-42-003 | High | Customer | Checkout | `_bootstrap` always `loading=true` → skeleton after address save | Soft bootstrap | — | code | FIXED |
| QA-42-004 | Med | Customer | PDP reviews | `_load` always spinner | Soft load | — | code | FIXED |
| QA-42-005 | Med | Admin Mobile | “Products” | No `/products` route (ops design) | Document Inventory as stand-in; soft-load inventory | — | docs | INTENTIONAL / CLOSED vs M-008 |
| QA-40-M-008 | Med | Admin | Products residual | Was hard-load / blank | Soft-load + soft-error (purchasing/inventory/more) | — | — | FIXED (via 42-001/005) |
| QA-42-010 | Med | Admin | Post-login matrix | ADB multi-field login unreliable | Manual login required | — | login screenshots | UNVERIFIED |
| QA-42-011 | Med | Payments | Razorpay verify | TEST keys require real signature | Do not fabricate | payment-smoke | initiate PASS | UNVERIFIED (E2E) |
| LIVE / UPI | Blocker | All | Payments | Out of scope | — | — | — | BLOCKED |

## Summary

- **FIXED:** QA-42-001…004, QA-40-M-008  
- **INTENTIONAL:** QA-42-005  
- **UNVERIFIED:** QA-42-010, QA-42-011, deep customer screens  
- **BLOCKED:** LIVE, UPI settlement  
