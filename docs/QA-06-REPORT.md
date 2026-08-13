# QA-06 REPORT — Customer Website Full Regression

## 1. Status

**COMPLETE** (Razorpay live UNVERIFIED; full viewport lab PARTIAL)

## 2. Scope

Production-oriented regression of `apps/nursery-web` against Laravel API after QA-01–05. No redesign. No Mobile Returns. No QA-SEC-001 cookie migration.

## 3. Bugs investigated / fixed

| ID | Severity | Finding | Fix |
|----|----------|---------|-----|
| QA-06-001 | MEDIUM | Search suggestions could apply stale responses | Generation guard in `SearchBox` + unit helpers |
| QA-06-002 | MEDIUM | Dead Unsplash seed URLs → Next Image upstream 404 | Data repair script + `SafeImage` fallback |
| QA-06-003 | LOW | No image onError fallback on cards/gallery | `SafeImage` on ProductCard + ProductGallery |

## 4. Non-bugs

- Offers “Coming soon” = upcoming campaigns section (intentional)
- Guest→auth cart merge via `X-Cart-Token` (working; documented)
- Lint `set-state-in-effect` on ProductReviews / RecentlyViewed / checkout etc. = **pre-existing**

## 5. Golden journey (Asha)

Login → search/product → cart qty → WELCOME10 → preview → COD `ORD-20260812-00009` → detail/tracking → cancel → CANCELLED. Idempotent duplicate `X-Request-Id` returned same order.

## 6. API / DB changes

- **API contract:** NONE  
- **Database schema:** NONE  
- **Data repair:** `scripts/qa06_repair_product_images.php` (9 image/banner/campaign URLs)

## 7. Tests

| Suite | Result |
|-------|--------|
| Web `test:unit` | PASS |
| Touched-file eslint | PASS |
| Full `npm run lint` | FAIL (15 pre-existing) |
| `npm run build` | PASS |
| PHPUnit Qa02–05 | PASS (31) |

## 8. Remaining / UNVERIFIED

- Live Razorpay UI  
- Exhaustive multi-breakpoint visual lab  
- Multi-tab stale cart UI filming  

## 9. QA-07 readiness

**YES**
