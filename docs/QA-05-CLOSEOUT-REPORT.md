# QA-05 CLOSEOUT REPORT

## 1. Status

**COMPLETE**

## 2. Bugs Fixed

- **QA-05-001** — Expired unpaid reservation release now uses `order` reference + state machine (prevents over-release on later cancel).

## 3. Bugs Still Open / deferred

- QA-PAR-001 Customer Mobile Returns → QA-07  
- QA-PAY-002 non-prod stub → intentional  
- QA-SEC-001 localStorage JWT → later  
- Interactive Admin/Customer Mobile fulfillment UI device smoke → UNVERIFIED  

## 4. Inventory Verification

| Check | Result |
|-------|--------|
| sellable = on_hand − reserved − damaged | PASS |
| Reserve / release / commit | PASS |
| Last unit cannot double-reserve | PASS |
| Duplicate reserve idempotent | PASS |
| COD commit | PASS |
| Cancel CONFIRMED restock | PASS |
| Expired reservation safe vs other orders | PASS (after fix) |
| Negative on-hand prevented on adjust | PASS (Phase6) |

## 5. Fulfillment / Delivery Verification

| Check | Result |
|-------|--------|
| Pick → pack → ship → OFD → deliver | PASS (PHPUnit + live) |
| Fail delivery → retry | PASS (PHPUnit) |
| Invalid transition | PASS |
| Permission deny | PASS |
| Shipment lowercase vs order UPPER | PASS (documented) |

## 6. Customer / Admin visibility

| Check | Result |
|-------|--------|
| Live customer status after deliver | PASS (`DELIVERED`) |
| Live tracking `current_status` | PASS |
| Admin inventory list | PASS |
| Admin fulfillment dashboard / queue | PASS |

## 7. Unit / Integration / Regression

| Suite | Result |
|-------|--------|
| PHPUnit 54 (Qa02–05 + Phase6/7/18/20) | PASS |
| Web `test:unit` | PASS |
| Customer Flutter | PASS |
| Admin Mobile Flutter | PASS |
| QA-02 / QA-03 / QA-04 | PASS |

## 8. Database

**NONE**

## 9. Definition of Done

- [x] Inventory lifecycle / reserve / release / commit / negative prevented  
- [x] Concurrent last-unit tested  
- [x] Duplicate operation tested  
- [x] Fulfillment + shipment + delivery + cancel verified  
- [x] Admin Web fulfillment live PASS  
- [x] Admin Mobile checked (unit + API; UI UNVERIFIED)  
- [x] Customer tracking verified (API live; device UI UNVERIFIED)  
- [x] PHPUnit + client unit PASS  
- [x] QA-02/03/04 regression PASS  
- [x] Docs + bug register + matrix + roadmap updated  

## 10. QA-06 Ready

**YES**
