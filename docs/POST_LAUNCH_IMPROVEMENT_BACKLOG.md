# Post-Launch Improvement Backlog

Ideas discovered during readiness / soft-launch prep. **Do not implement in Phase 14** unless required to clear a production blocker.

---

## Technical

- Wire Sentry (or equivalent) for API + Next.js + Flutter  
- Monorepo CI (lint/test/build) on PR  
- Redis as default prod cache for multi-node JWT blacklist  
- Durable object storage for uploads if leaving URL-only media  
- API latency histograms / slow-query log export  
- Sitemap + richer Open Graph for SEO  

## UX

- Privacy / terms / support contact pages (legal content — owner)  
- Clearer checkout payment error copy with request id  
- Admin incident strip linking to failed jobs / returns  

## Commerce

- Live PSP refund automation (if still limited)  
- Campaign attribution beyond coupon redemptions  
- Search zero-result Admin report (needs search logging)  

## Marketing

- Controlled promo campaigns post soft-launch  
- Abandoned-cart email (only with consent + reliable mail)  

## Operations

- Support macros in helpdesk tool (out of repo)  
- Playbook screenshots for fulfillment  
- Capacity load test on staging with realistic catalog size  

## Analytics

- Client-side funnel events only if privacy-reviewed  
- Conversion rate only when session/view data exists — do not invent  

## Future phases (explicitly out of scope)

- AI plant assistant  
- Dynamic pricing  
- Warehouse routing optimization  
- Advanced CRM / CDP  
- Elasticsearch replacement for search  
