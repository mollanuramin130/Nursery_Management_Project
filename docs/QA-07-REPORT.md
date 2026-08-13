# QA-07 REPORT — Customer Mobile Feature Parity + Real Device Regression

**Date:** 2026-08-12  
**Status:** **COMPLETE**

---

## 1. Status

**QA-07 COMPLETE**

Customer Mobile Returns (QA-PAR-001) and My Reviews (QA-PAR-002) are implemented against existing Laravel APIs, covered by PHPUnit + Flutter unit tests, and verified on physical Android (`vivo 1951` / `2d3714f`) with LAN API `http://192.168.1.3:8000/api/v1`.

Razorpay live remains **UNVERIFIED** (empty provider credentials) — not falsely marked PASS.

---

## 2. Bugs found

| ID | Severity | Finding |
|----|----------|---------|
| QA-PAR-001 | HIGH | Account Returns list/detail missing on Mobile (request existed on order detail only) |
| QA-PAR-002 | MEDIUM | Dedicated My Reviews account page missing (PDP review only) |
| (ops) | LOW | Debug APK without `--dart-define=API_BASE_URL` defaults to `10.0.2.2` — unusable on physical device |

No new CRITICAL defects discovered in QA-07 scope.

---

## 3. Bugs fixed

| ID | Fix |
|----|-----|
| **QA-PAR-001** | Flutter `ReturnsScreen` + `ReturnDetailScreen`; Account nav; routes `/account/returns`, `/account/returns/:id`; reuse `GET /customer/returns`, `GET /returns/{id}`; request path remains `POST /orders/{id}/returns` on order detail |
| **QA-PAR-002** | Flutter `MyReviewsScreen`; Account nav; route `/account/reviews`; reuse `GET /customer/reviews` (read-only — API has no customer edit/delete) |

---

## 4. Customer Mobile parity audit

See `docs/QA-07-AUDIT-FINDINGS.md`.

Summary vs Web for Returns/Reviews:

| Capability | API | Web | Mobile post-QA-07 |
|------------|-----|-----|-------------------|
| Returns list | WORKING | WORKING | WORKING |
| Return detail | WORKING | WORKING | WORKING |
| Return request | WORKING | Order flow | Order detail (existing) |
| My reviews list | WORKING | WORKING | WORKING |
| Review create | WORKING | PDP | PDP (existing) |
| Review edit/delete | N/A | N/A | N/A (not invented) |

---

## 5. Returns implementation / verification

- Models: `CustomerReturnSummary` + list payload parse  
- UI states: loading / empty / error / success / unauthorized  
- Live API: Asha list includes Return #1 (RECEIVED), #2 (RETURN_REQUESTED)  
- Device: Account → My returns → Return #2 detail (status, item, notes, view order) **PASS**

---

## 6. Reviews implementation / verification

- Model: `CustomerMyReview`  
- Device: Account → My reviews shows Indoor Starter Kit / Rose / Money Plant with ratings & APPROVED **PASS**  
- No edit/delete UI (API unsupported)

---

## 7. API verification

| Endpoint | Result |
|----------|--------|
| `GET /customer/returns` | PASS (200, auth required) |
| `GET /returns/{id}` | PASS |
| `POST /orders/{id}/returns` | PASS (existing; smoke create earlier) |
| `GET /customer/reviews` | PASS |
| 401 unauthenticated | PASS (`Qa07CustomerReturnsReviewsTest`) |

**No new API endpoints. No schema changes.**

---

## 8. Real-device verification

| Step | Result |
|------|--------|
| APK with LAN `API_BASE_URL` | PASS |
| Login `asha@example.com` | PASS |
| Account shows My returns / My reviews | PASS |
| Returns list + detail | PASS |
| My Reviews | PASS |
| Orders list + order detail | PASS |
| Cart qty / totals / Checkout CTA | PASS |
| Checkout → COD → Place order | PASS → **ORD-20260812-00010** CONFIRMED ₹179 |
| Razorpay live | UNVERIFIED |

Device: `2d3714f` · API: `192.168.1.3:8000` (not `10.0.2.2`).

---

## 9. Unit tests

- Flutter: `test/customer_account_mapping_test.dart` PASS  
- Flutter regression: `test/checkout_preview_rules_test.dart` PASS  
- Analyze (touched): 1 info `dangling_library_doc_comments` only  

---

## 10. Integration tests

- PHPUnit `Qa07CustomerReturnsReviewsTest` (+ Qa02–Qa05 filter): **34 PASS**  
- Covers returns list/show, reviews list, unauthorized  

---

## 11. Regression tests

| Phase | Result |
|-------|--------|
| QA-02 auth messages / reset | PASS (PHPUnit) |
| QA-03 cart FD / variant_id | PASS (PHPUnit) |
| QA-04 preview gate / stub / COD | PASS (PHPUnit + device COD) |
| QA-05 inventory reservation | PASS (PHPUnit) |
| QA-06 search race / SafeImage | Not re-touched; prior COMPLETE stands |

---

## 12. Accessibility

New screens use semantics / content-desc visible to TalkBack-style dumps (`My returns`, `My reviews`, `Return #n`). Touch targets use existing list tiles / AppBar patterns. No full a11y redesign.

---

## 13. Performance

List endpoints only; no full-history client-side aggregation. No new state-management framework.

---

## 14. Build / lint status

- `flutter build apk --debug --dart-define=API_BASE_URL=http://192.168.1.3:8000/api/v1` PASS  
- Flutter analyze on touched files: info only  
- Web lint: not modified in QA-07  

---

## 15. Database changes

**NONE.** Proposal updated accordingly.

---

## 16. API contract changes

**NONE.** Existing customer returns/reviews contracts reused.

---

## 17. Remaining risks

- Razorpay live still UNVERIFIED  
- Admin Mobile returns still MISSING (out of QA-07 scope → QA-09)  
- Physical device must always use LAN dart-define (document operationally)  
- Concurrent dual-UI Web↔Mobile same second still UNVERIFIED (API-backed consistency OK)

---

## 18. UNVERIFIED / BLOCKED

| Item | Status |
|------|--------|
| Razorpay live payment | UNVERIFIED — credentials empty |
| Full responsive Web device lab | UNVERIFIED (QA-06 carry-over; not QA-07 blocker) |
| BLOCKED | **None** |

---

## 19. Files changed (QA-07 primary)

**Flutter**

- `lib/models/customer_account_models.dart` (new)  
- `lib/screens/returns_screen.dart` (new)  
- `lib/screens/return_detail_screen.dart` (new)  
- `lib/screens/my_reviews_screen.dart` (new)  
- `lib/app.dart` (routes)  
- `lib/screens/account_screen.dart` (nav)  
- `lib/screens/order_detail_screen.dart` (deep link to return detail)  
- `lib/models/models.dart` (export)  
- `test/customer_account_mapping_test.dart` (new)  

**API**

- `tests/Feature/Qa07CustomerReturnsReviewsTest.php` (new)  

**Docs**

- `docs/QA-07-*.md`, bug register, feature matrix, roadmap, DB proposal  

---

## 20. QA-08 readiness

**YES** — Customer Mobile parity gaps QA-PAR-001/002 closed; proceed to Admin Web regression (QA-08).
