# QA-31 Test Matrix

**Environment:** Local TEST · `rzp_test_*` · Device `2d3714f` + `adb reverse`  
**Date:** 2026-08-13  
**Baseline:** QA-30 COMPLETE

| # | Case | Result | Evidence |
|---|------|--------|----------|
| 1 | Preflight device + TEST keys | **PASS** | `2d3714f`, `rzp_test_*` |
| 2 | Web exposes Dynamic QR / Intent / Checkout | **PASS** | `checkout/page.tsx` |
| 3 | Web Dynamic QR initiate (amount/order/expiry) | **PASS** | Order **9067** / pay **5531** |
| 4 | Web Dynamic QR real settle | **UNVERIFIED** | No completed QR charge |
| 5 | Mobile UPI Intent generated + chooser | **PASS** | Order **9068** |
| 6 | UPI app opens with correct ₹/order | **PASS** | GPay ₹49 + ORD-…-00012 |
| 7 | True UPI-app settlement | **BLOCKED** | GPay no payment account |
| 8 | Checkout/netbanking as UPI Intent PASS | **N/A** | Explicitly not claimed |
| 9 | Failed → retry → success, one sale | **PASS** | `Qa31AmountAuthority…` |
| 10 | QA-30-001 recovery still PASS | **PASS** | PHPUnit |
| 11 | Duplicate verify / webhook idempotent | **PASS** | Qa18 + Qa21 + Qa30/31 |
| 12 | Amount tamper rejected (incl. pending reuse) | **PASS** | QA-31-001 + live 409 |
| 13 | Cross-client parity (9066) | **PASS** | API + Mobile + Admin |
| 14 | UPI UI terminology Web/Mobile | **PASS** | Web modes + Mobile label fix |
| 15 | Mobile-data without reverse | **UNVERIFIED** | LAN curl 000 |
| 16 | In-app notifications (not LIVE FCM) | **PASS** | 9066 payment/order/new_order |
| 17 | QA-SEC-001 | **OPEN** | Unchanged |
| 18 | PHPUnit QA filter | **PASS** | 240 / 1170 (238+2 skip) |
| 19 | Web unit + Customer/Admin builds | **PASS** | |
| 20 | Flutter tests | **PASS** | Customer UPI + Admin suite |
| 21 | composer audit | **PASS** | No advisories |

---

## Legend

PASS · PARTIAL · UNVERIFIED · BLOCKED · OPEN · N/A
