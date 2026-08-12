# PHASE 17 — Marketing Automation + CRM Final Report

**Date:** 2026-08-12  
**Platform:** GreenLeaf Nursery

---

## Final status

**PHASE 17 PARTIALLY COMPLETE — DOCUMENTED GAPS**

CRM Customer 360, server-side segments, marketing automations, abandoned-cart/post-purchase processing, preference-respecting delivery, attribution column, Admin hub, and tests shipped on the existing Laravel architecture. Gaps (opens/clicks, plant-category segment fields, full 8-step wizard, SMS, repeat-purchase category rules) are documented — not fabricated.

---

## Quality gate

| Area | Result |
|------|--------|
| DATABASE | **PASS** — `customer_segments`, `marketing_automations`, `marketing_deliveries`; `orders.campaign_id`; `coupons.campaign_id` |
| REST API | **PASS** — CRM controller routes + RBAC |
| ADMIN CRM | **PASS** — `/marketing`, automations, segments, Customer 360 |
| CUSTOMER WEBSITE | **PASS / NOT REQUIRED for new surfaces** — preferences already exist; storefront campaigns unchanged |
| ANDROID | **NOT REQUIRED** for Phase 17 core — prefs/notifications reuse Phase 11; no duplicate eligibility |
| EMAIL | **PASS** — via NotificationService marketing category + templates |
| PUSH | **PASS** — same path; transactional vs marketing separated |
| QUEUE | **PASS** — scheduled Artisan commands + notification queue; HTTP dispatch batched |
| ANALYTICS | **PASS** — attribution only when `campaign_id` present; delivery stats from real rows |
| RBAC | **PASS** — new permissions seeded |
| AUDIT LOG | **PASS** — segment/automation actions |
| PRIVACY | **PASS** — server-side segments; no secrets in 360 |
| PERFORMANCE | **PASS** — SQL segments, pagination, batch size, indexes on deliveries |

---

## 1. Existing CRM audit

See `PHASE_17_CRM_AUDIT.md`. Pre-existing commerce + notifications; missing segments/automations/360 filled in this phase.

## 2. Customer 360

`GET /admin/customers/{id}/360` + Admin detail page sections (profile, orders, activity, loyalty, subscriptions, marketing prefs).

## 3. Segmentation

Dynamic AND criteria; system defaults via `marketing:seed`; Admin list/detail/members.

## 4. Marketing campaigns

Catalog campaigns retained. New **automations** lifecycle for CRM journeys/blasts.

## 5. Abandoned cart recovery

Eligibility from config; command + schedule; stop rules; draft-by-default automation.

## 6. Customer journeys

Welcome (register hook), post-purchase review delay, reactivation draft — see design doc.

## 7. Communication preferences

Existing customer preferences; marketing opt-out does not kill transactional.

## 8–9. Email / Push

Reuse Phase 11 NotificationService; type `campaign_promo`, category `marketing`.

## 10–11. Campaign analytics / attribution

Delivery sent/skipped/failed counts. Revenue attribution only via `orders.campaign_id`. Opens/clicks not claimed.

## 12. Database changes

Migration `2026_08_12_050000_phase17_crm_marketing.php`.

## 13. API changes

Documented in `PHASE_17_MARKETING_API.md`.

## 14. Admin changes

Marketing hub, automations CRUD/actions, segments UI, enriched customer page, nav + permissions.

## 15. Website changes

No duplicate eligibility logic. Preferences already available. Optional checkout `campaign_id` supported by API for future UX wiring.

## 16. Android changes

Not required for core CRM; notifications continue through existing channels.

## 17. Queue changes

Scheduled abandoned-cart + post-purchase commands; batch dispatch.

## 18. Security / RBAC

`customers.view|segment`, `marketing.view|manage|launch`; OR permission middleware for 360.

## 19. Privacy

Server-side filtering; minimal member fields; no fabricated profiling claims.

## 20. Performance

Pagination, indexes, batch limits, no full DB dump to Admin browser.

## 21. Data gaps

| Gap | Notes |
|-----|-------|
| Email open/click tracking | Provider-dependent; not invented |
| Plant-category purchase segment field | Taxonomy exists; criteria field not added yet |
| Frequent-buyer / category journeys UI | Segmentable partially via order_count; category rules TBD |
| Full 8-step campaign wizard | Simplified operational create flow shipped |
| Controlled canary % launch | Test user IDs + draft/activate; no % ramp engine |
| Signed unsubscribe tokens in email HTML | Prefs account page exists; deep-link token UX optional |

## 22. Known limitations

- Reactivation automation requires linking a segment before activate  
- Abandoned cart starts **draft** intentionally  
- Test dispatch still subject to NotificationService marketing prefs when sending real notifications  
- Merchandising campaign state machine remains separate from automations  

## 23. Future improvements

- Category-based segment fields with purchase thresholds  
- Repeat-purchase reminders for soil/fertilizer using category rules  
- Email open/click ingestion when ESP webhooks available  
- Richer multi-rule segment editor + wizard  
- Checkout UI to pass `campaign_id` from active storefront campaign  

---

## Tests

`Phase17CrmMarketingTest` — 4 passed (seed/dashboard, 360 + members, automation lifecycle, invalid segment field).

---

## Deliverables

- `docs/PHASE_17_CRM_AUDIT.md`  
- `docs/PHASE_17_CRM_DESIGN.md`  
- `docs/PHASE_17_MARKETING_API.md`  
- `docs/PHASE_17_MARKETING_RUNBOOK.md`  
- `docs/PHASE_17_FINAL_REPORT.md`  

**STOP after PHASE 17.**
