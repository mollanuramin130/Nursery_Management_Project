# QA-29 TEST MATRIX

**Device:** vivo 1951 · TEST-only · No LIVE payment / FCM / production  

Legend: PASS / FAIL / PARTIAL / BLOCKED / UNVERIFIED / ACCEPTED / N/A

---

## 1. Pre-flight

| Check | Result |
|-------|--------|
| Device connected / authorized | PASS |
| Flutter sees device | PASS |
| Install + launch Customer | PASS |
| Install + launch Admin | PASS |
| API reachable (adb reverse) | PASS |
| Cleartext debug fix | PASS (QA-29-001) |

---

## 2. Customer Mobile smoke

| Area | Result | Notes |
|------|--------|-------|
| Login | PASS | asha@example.com |
| Session persistence | PASS | relaunch still Asha |
| Invalid credentials | UNVERIFIED | not re-run after login success |
| Home / categories / products / images | PASS | |
| Search | UNVERIFIED | |
| Add to cart / qty / clear | PASS | |
| Wishlist add | FAIL/OPEN | QA-29-003 |
| Coupon UI present | PASS | apply invalid UNVERIFIED |
| Checkout address/delivery/payment/review | PASS | |
| COD place | PASS | order **9063** |
| UPI option visible | PASS | |
| Razorpay Checkout complete | UNVERIFIED | no device charge this phase |
| Dynamic QR complete | UNVERIFIED | |
| UPI Intent complete | UNVERIFIED | |
| Order list/detail timeline | PASS | through DELIVERED |
| Notifications center | PASS | |
| Notification deep link | PASS | → order 9063 |
| Logout | UNVERIFIED | |

---

## 3. Admin Mobile

| Area | Result |
|------|--------|
| Login | PASS |
| Dashboard | PASS (shows COD order) |
| Orders / detail | PASS |
| Payment visibility | PARTIAL (blank after status — fixed in code) |
| Status transitions | PASS |
| Invalid transitions gated | PASS |
| Notifications | UNVERIFIED (UI) |
| Inventory screens | UNVERIFIED |

---

## 4. Cross-client journey

| Step | Result |
|------|--------|
| Customer COD place | PASS |
| Admin see order | PASS |
| Admin PROCESSING…DELIVERED | PASS |
| Customer sees each status | PASS (timeline + list Delivered) |
| Notifications per status | PASS (in-app) |
| Web parity same order | PARTIAL | API agrees; Web UI click-through UNVERIFIED this session |

---

## 5. Payment modes (device)

| Mode | Result |
|------|--------|
| COD | PASS |
| Razorpay TEST baseline (QA-26) | PASS (prior) |
| Checkout (device complete) | UNVERIFIED |
| Dynamic QR (device) | UNVERIFIED / BLOCKED if not offered on Mobile |
| UPI Intent (device complete) | UNVERIFIED |
| Webhook | PASS (QA-26; not re-fired) |

---

## 6. Build / analyze

| Suite | Result |
|-------|--------|
| PHPUnit | **244 / 1143** (242 pass + 2 skipped) |
| Customer flutter test | PASS |
| Admin flutter test | PASS |
| Customer analyze | 10 info PRE-EXISTING NON-BLOCKING |
| Admin analyze | 9 info PRE-EXISTING NON-BLOCKING |
| Customer/Admin debug APK build | PASS |
