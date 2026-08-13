# QA-03 CLOSEOUT REPORT

## 1. Status

**COMPLETE**

## 2. Bugs Fixed

- **QA-CART-001** — free-delivery uses merchandise after discount on cart and checkout
- **QA-CART-002** — clients consume API `variant_id`
- Coupon usage-limit check shared on cart apply + checkout

## 3. Bugs Still Open

- QA-CHK-001 / QA-PAY-001 (checkout/payment production hardening) → **QA-04**
- QA-PAR-001, QA-SEC-001 → later phases
- Same-account Web↔Mobile live sync not run (different sessions used)

## 4. API Verification

| Check | Result |
|-------|--------|
| Health LAN `192.168.1.3:8000` | PASS |
| Login tokens | PASS |
| Add / qty update / remove / clear | PASS |
| WELCOME10 apply / remove | PASS |
| Checkout preview agrees on subtotal/discount/FD | PASS |
| Shipping: cart `0`, preview method price unless free | PASS (by design) |

## 5. Customer Web Verification

| Step | Result |
|------|--------|
| Authenticated session (Asha) | PASS |
| Add products + qty | PASS |
| Apply WELCOME10 (Discount -₹75 on ₹748) | PASS |
| Checkout preview `Place order — ₹722` (= 748−75+49) | PASS |
| Preview failure shows rate-limit / retry (not silent cart total) | PASS |

## 6. Customer Mobile Real Device Verification

| Step | Result |
|------|--------|
| Device vivo 1951 (`2d3714f`) + LAN API | PASS |
| Home loads catalog / cart badge | PASS |
| Cart qty increase 1→2 (₹398→₹647) then decrease | PASS |
| Apply WELCOME10 (Discount -₹39.80, Total ₹358.20) | PASS |
| Checkout “Totals confirmed by nursery checkout” | PASS |
| Remove item; restart app; cart restored (1× Vermicompost ₹149) | PASS |
| API cart for same admin user matches UI | PASS |

## 7. Cart Calculation Verification

Rule: free delivery when `(subtotal − discount) ≥ 999`.

| Scenario | Evidence | Result |
|----------|----------|--------|
| Below threshold | Mobile ₹398 → “Add ₹601 more…” | PASS |
| After discount still below | WELCOME10 → remaining updates | PASS |
| Qty pushes above threshold | API qty2 on ₹549 → qualifies true | PASS |
| Cart vs preview FD agree | Live API smoke | PASS |

## 8. Coupon Verification

| Case | Result |
|------|--------|
| Valid WELCOME10 Web + Mobile | PASS |
| Invalid NOPE123 API | PASS (400) |
| Remove coupon Mobile | PASS |
| Exhausted usage rejected at apply (code + tests) | PASS |

## 9. Variant Mapping Verification

| Check | Result |
|-------|--------|
| API item has `variant_id`, not `product_variant_id` | PASS |
| Flutter `CartItem.variantId` mapping unit tests | PASS |
| Live items `variant_id: null` for simple SKUs | PASS (expected) |

## 10. Unit Tests

- PHPUnit Qa03 + Qa02: **PASS** (19)
- Flutter cart + auth: **PASS**
- Web `npm run test:unit`: **PASS**

## 11. Integration Tests

- API cart ↔ checkout preview: **PASS**
- Mobile UI ↔ API cart: **PASS**
- Web UI ↔ checkout preview totals: **PASS**

## 12. Regression Tests

- QA-02 auth tests: **PASS**
- QA-CHK-001 preview gate behavior observed: **PASS**

## 13. Files Changed

No new production code in closeout (verification + docs). Prior QA-03 code remains as implemented.

## 14. Database Changes

**NONE**

## 15. API Contract Changes

**NONE** (additive internal reuse only)

## 16. Remaining Risks

- Login/checkout throttles during aggressive automation
- Flutter debug attach flaky on this vivo device
- Cross-client same-account simultaneous sync not proven

## 17. QA-04 Readiness

**YES**

**QA-03 COMPLETE — QA-04 READY**
