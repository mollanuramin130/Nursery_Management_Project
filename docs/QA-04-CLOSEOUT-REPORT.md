# QA-04 CLOSEOUT REPORT

## 1. Status

**COMPLETE**

Live Razorpay provider path: **UNVERIFIED** (empty keys in local env — not falsely marked PASS).

## 2. Bugs Fixed

- **QA-CHK-001** — Customer Web never places/pays on cart merchandise total; preview required; stale preview invalidated; retry UI.
- **QA-PAY-001** — Customer Web refuses `local_stub` in production; API production gate verified; Flutter release guard message aligned.
- COD + order confirmation + admin visibility verified.
- Duplicate place mitigated: client locks + `X-Request-Id` idempotency (same order id on replay).

## 3. Bugs Still Open / deferred

- **QA-PAY-002** — Non-prod empty-key stub remains intentional (ops: set production keys).
- **QA-PAR-001**, **QA-SEC-001**, Admin Users UI → later phases.
- Live Razorpay UI smoke → when test keys available.

## 4. API Verification

| Check | Result |
|-------|--------|
| Health LAN | PASS |
| Checkout preview totals | PASS |
| COD `POST /orders` → `CONFIRMED` | PASS (`ORD-20260812-00007`) |
| Admin `GET /admin/orders/{id}` | PASS |
| Same-account order GET (mobile headers) | PASS |
| Production stub refusal (PHPUnit) | PASS |
| Idempotent `X-Request-Id` same order | PASS |

## 5. Customer Web Verification

| Step | Result |
|------|--------|
| Preview required for payable / Place order | PASS (code + unit) |
| Preview failure → no cart fallback | PASS |
| Stale invalidation on cart/address/shipping/coupon | PASS (code) |
| Production stub message | PASS (unit) |
| COD via API stack | PASS |

## 6. Customer Mobile Real Device Verification

| Step | Result |
|------|--------|
| Device vivo 1951 + LAN API | PASS |
| Checkout review totals from nursery | PASS |
| Place order → Order confirmed | PASS `ORD-20260812-00006` ₹149 CONFIRMED |

## 7. Payment Verification

| Path | Result |
|------|--------|
| COD | PASS (API + device) |
| `local_stub` non-prod | ALLOWED (intentional) |
| `local_stub` production | BLOCKED (API + Web + Flutter release) |
| Live Razorpay | UNVERIFIED — payment provider environment unavailable |

## 8. Unit / Integration / Regression

| Suite | Result |
|-------|--------|
| PHPUnit Qa02+Qa03+Qa04 (24) | PASS |
| Web `test:unit` | PASS |
| Flutter checkout + cart mapping | PASS |
| QA-02 regression | PASS |
| QA-03 regression | PASS |

## 9. Cross-platform same account

| Check | Result |
|-------|--------|
| Asha order created → mobile client retrieves same order | PASS |
| Concurrent Web UI + Mobile UI filmed sync | UNVERIFIED |

## 10. Database

**NONE**

## 11. Definition of Done checklist

- [x] QA-CHK-001 fixed
- [x] QA-PAY-001 fixed
- [x] Checkout preview mandatory / no cart payable fallback
- [x] Stale preview cannot place (Web + Mobile force/refresh)
- [x] Payment amount server-authoritative
- [x] Production local_stub blocked
- [x] COD verified
- [x] Razorpay verified **if env available** → N/A → UNVERIFIED documented
- [x] Payment verification (server + stub tests)
- [x] Order / inventory consistency (existing + COD smoke)
- [x] Duplicate submission handled
- [x] Customer Web / Mobile / Admin visibility tested
- [x] Same-account cross-platform attempted (API PASS)
- [x] Unit + integration + QA-02/03 regression PASS
- [x] Real Android COD smoke PASS
- [x] No DB migration
- [x] Docs + bug register + feature matrix + roadmap updated

## 12. QA-05 Ready

**YES**
