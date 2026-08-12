# Business Optimization Backlog

**Platform:** GreenLeaf Nursery  
**Phase:** 15  
**Rule:** Every item needs evidence from real data or an explicit data gap. No fake ROI.

---

## HIGH IMPACT

### H1 — Instrument product view → cart → checkout events
- **Category:** Conversion / Checkout  
- **Observation:** Funnel is partial; drop-off before order creation is invisible.  
- **Evidence:** `AnalyticsService` funnel `missing_stages`; overview `data_gaps`.  
- **Possible solution:** Privacy-safe `analytics_events` (or reuse pattern of `search_events`) with `event_name`, nullable `user_id`/`session_id`/`product_id`, server ingest; never fail checkout on log failure.  
- **Expected impact:** Enables cart/checkout abandonment measurement.  
- **Risk:** Storage growth; privacy review required.  
- **Required data:** Event stream + retention policy.

### H2 — Campaign → order attribution
- **Category:** Marketing  
- **Observation:** Campaign revenue cannot be claimed.  
- **Evidence:** `PHASE_5_CAMPAIGN_ATTRIBUTION_GAP.md`; campaigns analytics returns unsupported.  
- **Possible solution:** Persist `campaign_id` / UTM on cart and order at checkout.  
- **Expected impact:** Measurable campaign sales.  
- **Risk:** Incorrect last-touch attribution if multi-touch needed.  
- **Required data:** Attribution fields on orders.

### H3 — High-demand / low-stock ops loop
- **Category:** Inventory / Operations  
- **Observation:** Attention list identifies risk but does not notify.  
- **Evidence:** `/admin/analytics/attention` HIGH_DEMAND_LOW_STOCK.  
- **Possible solution:** Configurable Admin alert (email/in-app) when sellable ≤ threshold and units sold in N days ≥ M.  
- **Expected impact:** Fewer stockouts on movers.  
- **Risk:** Alert spam if thresholds loose.  
- **Required data:** Thresholds in settings; inventory + sales already exist.

### H4 — Payment failure rate alerts
- **Category:** Payments  
- **Observation:** Payment health is visible but not threshold-alerted.  
- **Evidence:** `/admin/analytics/payments` success_rate.  
- **Possible solution:** Alert when failed/attempts exceeds configured % over a sliding window.  
- **Expected impact:** Faster PSP/config incident response.  
- **Risk:** Noise during low-volume hours.  
- **Required data:** Configurable thresholds; payments table.

---

## MEDIUM IMPACT

### M1 — Zero-result search → catalog actions
- **Category:** Product / UX  
- **Observation:** Customers search for missing terms.  
- **Evidence:** `/admin/analytics/search` zero_result_queries (after traffic).  
- **Possible solution:** Synonyms, redirects, or SKU adds validated by merchandising.  
- **Expected impact:** Higher search satisfaction.  
- **Risk:** Wrong synonym mapping.  
- **Required data:** Accumulated `search_events`.

### M2 — Low-rated product attention
- **Category:** Product / Customer retention  
- **Observation:** Products with avg rating ≤ 3 and ≥2 reviews need ops review.  
- **Evidence:** `/admin/analytics/reviews` low_rated_products.  
- **Possible solution:** Workflow to QA plants/packaging; do **not** auto-delete reviews.  
- **Expected impact:** Quality improvement / fewer returns.  
- **Required data:** Reviews table (exists).

### M3 — Return reason concentration
- **Category:** Operations / Product  
- **Observation:** Return reasons (when populated) show failure modes.  
- **Evidence:** Returns analytics grouping.  
- **Possible solution:** Process fixes for top reasons (damage, wrong SKU, delivery).  
- **Expected impact:** Lower return/refund rates.  
- **Risk:** Sparse reason data early.  
- **Required data:** Consistent reason enums on return_requests.

### M4 — Coupon performance hygiene
- **Category:** Marketing  
- **Observation:** Redemptions and discount totals measurable; profitability not.  
- **Evidence:** Coupons analytics; no COGS.  
- **Possible solution:** Track AOV with vs without coupon; avoid “profit” claims.  
- **Expected impact:** Better promo discipline.  
- **Required data:** Already mostly available.

### M5 — Loyalty / subscription analytics endpoints
- **Category:** Customer retention  
- **Observation:** Modules exist; Phase 15 did not invent BI without definitions.  
- **Evidence:** No loyalty/subscription keys in `AnalyticsService`.  
- **Possible solution:** Dedicated read APIs using ledger/subscription state machines.  
- **Expected impact:** Retention ops visibility.  
- **Risk:** Mislabeling failed renewal as churn.  
- **Required data:** Clear state definitions from Phase 9/10 docs.

### M6 — Separate `financial_reports.view` permission
- **Category:** Operations / Security  
- **Observation:** All analytics share `reports.view`.  
- **Evidence:** Admin routes middleware.  
- **Possible solution:** Split financial vs operational reports in RBAC.  
- **Expected impact:** Least-privilege staffing.  
- **Risk:** Role migration work.  
- **Required data:** Permission matrix update.

---

## LOW IMPACT

### L1 — Full cohort retention matrix (M1–M3)
- **Category:** Customer retention  
- **Observation:** Acquisition cohorts only.  
- **Evidence:** `/admin/analytics/cohorts` note.  
- **Possible solution:** Materialized monthly retention when order volume justifies.  
- **Expected impact:** Retention storytelling.  
- **Risk:** Heavy queries.  
- **Required data:** Orders history + indexes.

### L2 — Commercial season taxonomy
- **Category:** Marketing / Product  
- **Observation:** Seasonal page is monthly proxy.  
- **Evidence:** seasonal analytics `taxonomy: none`.  
- **Possible solution:** Admin-configured season windows mapped to categories/campaigns.  
- **Expected impact:** Nursery-season planning.  
- **Risk:** Inventing seasons without business buy-in.  
- **Required data:** Approved season calendar.

### L3 — Future recommendation signals backlog
- **Category:** UX  
- **Observation:** No AI recommender in Phase 15 (by design).  
- **Evidence:** Phase 15 non-goals.  
- **Possible solution:** Later use views, purchases, wishlist, plant taxonomy, season, stock.  
- **Expected impact:** Merchandising assist.  
- **Risk:** Cold start; privacy.  
- **Required data:** Behavioral events (H1) + purchase history.

### L4 — A/B testing platform
- **Category:** Conversion  
- **Observation:** No experimentation framework.  
- **Evidence:** Architecture audit — not present.  
- **Possible solution:** Future assignment service + exposure logging; never randomize prices casually.  
- **Expected impact:** Controlled UX tests.  
- **Risk:** Checkout/payment fragmentation.  
- **Required data:** Experiment assignment store.

### L5 — Async mega-exports
- **Category:** Operations  
- **Observation:** Sync CSV only for bounded exports.  
- **Evidence:** Phase 5 audit.  
- **Possible solution:** Queue job + download when volume grows.  
- **Expected impact:** Safer large exports.  
- **Risk:** Job infra ops.  
- **Required data:** Queue workers (Phase 13 gap if not hosted).

---

## Explicit non-recommendations (Phase 15)

- Do not auto-purchase stock from attention signals.  
- Do not claim campaign revenue without attribution.  
- Do not build advanced AI recommendations now.  
- Do not randomly A/B prices or checkout.  
- Do not fabricate cart abandonment percentages.
