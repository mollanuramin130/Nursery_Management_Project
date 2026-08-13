# QA-21 CLOSEOUT REPORT — UPI Payment

**Date:** 2026-08-12  
**Phase:** QA-21  
**Verdict:** **PARTIAL** · UPI **CODE** complete · LIVE payment **BLOCKED**  
**Release:** **YELLOW** · **GREEN: NO**

---

## Acceptance checklist

| Criterion | Result |
|-----------|--------|
| API contract implemented | **PASS** (additive) |
| Dynamic QR (provider-backed / stub) | **PASS** automated · LIVE **BLOCKED** |
| UPI Intent | **PASS** automated · LIVE **BLOCKED** |
| Server-authoritative verification | **PASS** |
| Webhook signature / idempotency | **PASS** (Qa18–20 suite) · LIVE **BLOCKED** |
| Amount authority | **PASS** |
| Order ownership / IDOR | **PASS** |
| Inventory single-commit | **PASS** |
| Duplicate payment protection | **PASS** |
| Customer Web | Unit **PASS** · LIVE **BLOCKED** |
| Customer Mobile | Unit/analyze **PASS** · LIVE device **BLOCKED/UNVERIFIED** |
| Admin payment visibility | **PASS** (code) · LIVE **UNVERIFIED** |
| COD regression | **PASS** |
| Unit / integration / regression | **PASS** — **198 tests / 1011 assertions** |
| Web builds | Customer + Admin **PASS** |
| Mobile tests | Customer **30** PASS · Admin **21** PASS |
| Security (Qa11) | **PASS** (11/41) · QA-SEC-001 **OPEN** |
| Provider TEST / LIVE payment | **BLOCKED** (credentials EMPTY) |
| Production `--strict` on this host | exit **1** (correct for `APP_ENV=local`) · real prod **UNVERIFIED** |

---

## Counts

| Suite | Result |
|-------|--------|
| PHPUnit QA filter | **198 passed**, **1011 assertions** |
| Prior QA-20 | 193 / 966 |
| Delta | +5 Qa21 UPI tests (+ assertions) |
| Customer Web `test:unit` | PASS |
| Admin Web `test:unit` | OK |
| Customer Flutter `flutter test` | All tests passed |
| Admin Flutter `flutter test` | All tests passed |
| `composer audit` | No advisories |
| `npm audit --omit=dev` (web) | 0 vulnerabilities |

---

## Database

**NONE**

---

## Docs delivered

- `docs/UPI-PAYMENT-DESIGN.md`
- `docs/UPI-PAYMENT-API-CONTRACT.md`
- `docs/UPI-PAYMENT-TEST-MATRIX.md`
- `docs/QA-21-REPORT.md`
- This closeout

---

## Honest release statement

UPI Dynamic QR + Intent + server verification are implemented on Razorpay without inventing client-side PAID.  
**Do not claim LIVE payment success** until Razorpay KEY/SECRET/WEBHOOK are configured and a real TEST/LIVE transaction + webhook are evidenced.  
**GREEN RELEASE: NO.**
