# QA-30 Test Matrix

**Environment:** Local TEST · Razorpay `rzp_test_*` · Cloudflare TEST webhook tunnel · Device `2d3714f` + `adb reverse`  
**Date:** 2026-08-13

| # | Case | Result | Evidence |
|---|------|--------|----------|
| 1 | Device preflight (adb, reverse, API health) | **PASS** | `2d3714f`, reverse `tcp:8000` |
| 2 | Razorpay TEST key mode | **PASS** | `rzp_test_*` prefix only |
| 3 | Customer Mobile place UPI order | **PASS** | Order **9066** created |
| 4 | UPI Intent chooser launches | **PASS** | PhonePe / GPay / PNB ONE |
| 5 | UPI Intent complete charge in UPI app | **UNVERIFIED** | Completed via Checkout Netbanking instead |
| 6 | Razorpay Checkout on device (Pay ₹49) | **PASS** | Checkout UI + Netbanking Success |
| 7 | Real TEST payment success | **PASS** | `pay_TPDwnubVyTYAiG` / payment **5530** |
| 8 | Server verify / webhook capture | **PASS** | Logs `payment.captured` + `order.paid` |
| 9 | Inventory single sale | **PASS** | 1 `sale` movement for 9066 |
| 10 | Order CONFIRMED after capture | **PASS** | After QA-30-001 fix |
| 11 | Customer Mobile order confirmation UI | **PASS** | “Order confirmed … ₹49” |
| 12 | Admin Mobile order visibility | **PASS** | ORD-20260813-00010 CONFIRMED |
| 13 | Admin Web / Customer Web API parity | **PASS** | GET orders/admin orders status CONFIRMED |
| 14 | Dynamic QR (Customer Mobile) | **UNVERIFIED** | UI mode not exposed |
| 15 | Wishlist add/remove persistence | **PASS** | QA-29-003 fixed + device |
| 16 | Tulsi vs Aloe image distinct | **PASS** | QA-29-004 data fix |
| 17 | Customer Mobile logout | **PASS** | Sign out → Welcome / Sign in |
| 18 | Admin Mobile logout | **PASS** | Sign out → Staff sign-in |
| 19 | Search Tulsi | **PASS** | Results + PDP |
| 20 | Mobile data without reverse | **UNVERIFIED** | LAN isolation |
| 21 | QA-SEC-001 HttpOnly | **OPEN** | Not in scope |
| 22 | PHPUnit QA regression filter | **PASS** | 238 tests / 1153 assertions (236 pass + 2 skipped) |
| 23 | Customer Flutter targeted tests | **PASS** | upi + auth messages |
| 24 | Admin Flutter tests | **PASS** | All passed |
| 25 | Customer/Admin Web build | **PASS** | Next builds OK |
| 26 | composer audit | **PASS** | No advisories |

---

## Legend

- **PASS** — evidenced this phase  
- **UNVERIFIED** — not proven; do not invent  
- **OPEN** — known accepted risk / intentional scope  
