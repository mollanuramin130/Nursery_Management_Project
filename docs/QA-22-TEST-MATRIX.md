# QA-22 TEST MATRIX — Notifications / FCM

**Date:** 2026-08-12  
Legend: PASS = executed · UNVERIFIED · BLOCKED

| Area | Case | Result |
|------|------|--------|
| Channel map | QA-22 types include push | PASS (`Qa22NotificationsTest`) |
| Token | register + deactivate | PASS |
| Security | customer cannot read another's notification | PASS |
| Customer→Admin | COD place → staff `new_order` | PASS |
| Idempotency | packed duplicate → one row | PASS |
| Payment notify | payment_confirmed idempotent | PASS |
| Push job | local FCM stub delivery | PASS |
| Processing | startPicking → `order_processing` | PASS |
| Deep link | no secrets in payload | PASS |
| Phase11 | prior suite | PASS (regression) |
| Web unit | deep link helpers | PASS |
| Admin unit | deep link helpers | PASS |
| Flutter unit | deep link + register body | PASS |
| Admin Flutter unit | deep link | PASS |
| LIVE FCM send | real device push | **BLOCKED** (`FCM_SERVER_KEY` empty; no Firebase client project) |
| Browser push permission | real Chrome/Firefox | **UNVERIFIED** |
| Foreground/background/terminated | physical device | **UNVERIFIED / BLOCKED** |
| Payment LIVE notify | Razorpay | **BLOCKED** (credentials empty; simulated only) |

---

## Integration (automated)

COD create → staff + customer notifications: **PASS**  
Status PROCESSING via fulfillment: **PASS**  
Full PACKED→DELIVERED UI device chain: **UNVERIFIED**
