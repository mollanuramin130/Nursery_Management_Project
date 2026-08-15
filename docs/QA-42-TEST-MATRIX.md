# QA-42 TEST MATRIX

Device: vivo 1951 (`2d3714f`) · `adb reverse` · `API_BASE_URL=http://127.0.0.1:8000/api/v1`

## Customer Mobile

| Screen | Evidence | Status |
|--------|----------|--------|
| Home | `01-home.png` | PASS |
| Shop / Categories | `02-shop-categories.png`, `08-categories-see-all.png` | PASS |
| Cart (empty) | `03-cart.png` | PASS |
| Orders | `04-orders.png` | PARTIAL (auth-gated UI) |
| Account | `05-account.png` | PARTIAL |
| Search | `07-search.png` | PARTIAL |
| Home PTR | code + prior QA-41 | PASS (code) |
| Checkout soft bootstrap | code | FIXED / device UNVERIFIED |
| Wishlist hearts | `01-home.png` red/neutral | PASS (visual) |
| PDP / Returns / Addresses | — | UNVERIFIED |
| Offline recovery | prior QA-41 | PARTIAL |

## Admin Mobile

| Screen | Evidence | Status |
|--------|----------|--------|
| Login | `10-admin-login.png` | PASS |
| Dashboard / Orders / Inventory / More | — | UNVERIFIED (login automation failed) |
| Soft error keep-data | code + unit | FIXED |
| Products route | N/A | INTENTIONAL (Inventory stand-in) |

## Payments

| Flow | Status | Evidence |
|------|--------|----------|
| COD API | PASS | `payment-smoke.log` |
| Razorpay initiate TEST | PASS | log + curl |
| Razorpay verify (stub sig) | FAIL expected | log |
| Razorpay device Checkout | UNVERIFIED | — |
| LIVE / UPI | BLOCKED | — |

## Automated

| Suite | Status |
|-------|--------|
| Customer Flutter 68 | PASS |
| Admin Flutter 29 | PASS |
| PHPUnit 253/1209 | PASS |
| Web unit (customer+admin) | PASS |
