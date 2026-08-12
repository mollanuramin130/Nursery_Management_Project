# Analytics Data Quality

**Platform:** GreenLeaf Nursery  
**Phase:** 15  
**Rule:** Document gaps; do not invent values; do not auto-repair financial records for aesthetics.

---

## 1. Event & behavioral tracking audit

| Signal | Status | Evidence | Impact |
|--------|--------|----------|--------|
| Page views | **MISSING** | No page_view table / SDK | Funnel incomplete |
| Product views | **MISSING** | No product_view events | Cannot compute view→cart or view→purchase |
| Search (server) | **EXISTS** (Phase 15) | `search_events` + `SearchEventRecorder` | Catalog gap discovery |
| Search conversion | **MISSING** | No session_id → order link | Cannot prove search ROI |
| Add to cart | **MISSING** as analytics event | Carts are operational | No abandon rate |
| Remove from cart | **MISSING** as analytics event | | |
| Wishlist | **PARTIAL** | Wishlist tables exist; no analytics aggregation endpoint | |
| Checkout start | **MISSING** | | |
| Checkout complete / order | **EXISTS** | `orders` | |
| Payment success/fail | **EXISTS** | `payments` | |
| Cancellation | **EXISTS** | `orders.status` | |
| Return / refund | **EXISTS** | `return_requests`, `refunds` | |
| Review | **EXISTS** | `reviews` | |
| Coupon usage | **EXISTS** | `coupon_redemptions`, `orders.coupon_code` | |
| Campaign interaction | **PARTIAL** | Campaigns may exist; **no order attribution** | Campaign “revenue” unsupported |
| Subscription | **PARTIAL** | Subscription module exists; no Phase 15 analytics API | |
| Loyalty | **PARTIAL** | Loyalty ledger exists; no Phase 15 analytics API | |
| Notification open/click | **MISSING / provider** | Depends on provider webhooks | |

---

## 2. Funnel integrity

Admin orders funnel reports `supported: partial` and lists missing stages explicitly.

Do **not** chart fabricated drop-off between product_view and add_to_cart.

Transactional stages (orders, payments, delivery) are comparable within themselves but are **not** a classic browser funnel.

---

## 3. Financial consistency risks

| Risk | Detail | Guidance |
|------|--------|----------|
| Revenue vs refunds | Revenue KPI is gross order grand totals; refunds reported separately | Never present gross as “net revenue” |
| Order currency mix | Analytics sums `grand_total` without FX conversion | Assume single configured store currency in production |
| Payment amount vs order grand_total | May diverge on partial/failed/retry paths | Investigate outliers; do not “fix” rows for dashboards |
| Cancelled after payment | Status transitions can leave historical payment success + cancelled order | Report both payment and order views |
| Tax/shipping inclusion | Grand total includes stored tax/shipping | Document in every revenue UI |

Analytics services are **read-only** against orders/payments/refunds.

---

## 4. Search event quality

| Issue | Risk | Mitigation |
|-------|------|------------|
| Duplicate searches | Same user/query spam | Acceptable for ops; optional future rate-limit |
| Short queries | `q` length &lt; 2 not logged | Documented |
| Fulltext vs LIKE engines | SQLite tests lack fulltext; production MySQL uses engine features | Logging is independent of search engine success if called after list |
| PII in query string | Users may type emails/phones | Truncate to 200 chars; no passwords/tokens; retention operator-owned |
| Logging failure | Must not break search | `try/catch` swallow in `SearchEventRecorder` |

---

## 5. Orphans & references

| Check | Notes |
|-------|-------|
| Order items without products | Historical SKU snapshots may remain; product joins can null |
| Plant analytics omitting non-plant SKUs | By design — join to `plant_profiles` |
| Payments without orders | Schema constrains `order_id`; orphans unexpected |
| Refunds without returns | Possible depending on admin flows — report refunds independently |

---

## 6. Timestamps

| Domain | Clock used |
|--------|------------|
| Range filters | App timezone day boundaries |
| Soft-deleted payments | SoftDeletes — confirm aggregates respect default scopes |
| Inventory “attention” | Sales in range + **live** stock (timestamp mismatch intentional; documented) |

Incorrect client clocks do not affect server `created_at` for orders/payments.

---

## 7. Attribution gaps

| Gap | Status | Doc |
|-----|--------|-----|
| Campaign → order | **MISSING** | `docs/PHASE_5_CAMPAIGN_ATTRIBUTION_GAP.md` |
| Coupon → campaign | **MISSING** | Coupons measurable; campaigns not |
| UTM / deep-link | **MISSING** | |
| Commercial seasons | **MISSING** | Monthly proxy only |

---

## 8. Duplicate / broken historical analytics

| Item | Status |
|------|--------|
| Phase 5 `/admin/analytics/*` | **EXISTS** — extended in Phase 15 |
| Legacy `/admin/reports/{type}` | **EXISTS** — still available; prefer analytics endpoints |
| Client-side revenue math | **Avoided** — Admin uses API aggregates |

---

## 9. Privacy inventory (events collected)

| Event / store | Fields | Why | Retention |
|---------------|--------|-----|-----------|
| `search_events` | query (truncated), normalized_query, results_count, optional user_id, platform, created_at | Catalog gap + search ops | Operator-owned purge; no passwords/tokens/payment data |
| Transactions | Existing order/payment/refund PII as already stored for commerce | Legal/commerce | Existing policy |
| Not collected | Passwords, card PANs, CVV, JWT tokens, raw auth secrets | — | — |

---

## 10. Recommended quality checks (ops)

1. Spot-check `SUM(grand_total)` for a day vs PSP settlement (expect differences).  
2. Compare payment success count vs orders leaving `PENDING_PAYMENT`.  
3. Review zero-result search weekly after soft launch traffic.  
4. Confirm `reports.view` / `reports.export` not granted to all staff.  
5. Monitor `search_events` table growth; add purge job when volume warrants.

Do **not** bulk-update financial rows to make charts “look right.”
