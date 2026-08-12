# PHASE 16 — Customer Experience Audit

**Date:** 2026-08-12  
**Platform:** GreenLeaf Nursery  
**Rule:** Evidence from live code/docs only. No fabricated CX.

---

## Journey map

| Stage | Status | Friction / notes |
|-------|--------|------------------|
| Discovery (home) | **PARTIAL → improved** | Home rails existed; web underused `recommended_for_you`; taxonomy rails only when plant_profiles exist |
| Search | **PARTIAL → improved** | Suggestions EXIST; zero-result was thin; assist API added |
| Category / shop | **EXISTS** | Filters via products API |
| Product detail | **EXISTS** | Rich plant care; gallery lightbox added |
| Recently viewed | **PARTIAL → improved** | Was client-only; now also `product_views` API |
| Recommendations | **EXISTS** | Rule-based related/similar/fbt; OOS excluded |
| Wishlist | **EXISTS** | Android OOS move-to-cart disabled |
| Cart | **PARTIAL → improved** | Free delivery EXISTS; `warnings` + `checkout_blocked` added |
| Checkout | **EXISTS** | Backend authoritative |
| Payment failure | **PARTIAL → improved** | Clearer recovery copy (try again / cart / shop) |
| Order / tracking | **EXISTS** | Status-driven timeline |
| Review | **EXISTS** | Helpful votes still MISSING |
| Loyalty / subscriptions / notifications / prefs | **EXISTS** | No rebuild |
| Back-in-stock | **MISSING → EXISTS** | Stock alert subscriptions + restock notify |
| Price-change history | **MISSING** | Documented future |
| AI recommendations | **NOT IN SCOPE** | Explainable rules only |
| Plant care reminders | **MISSING** | Needs consent + scheduling — deferred |

---

## Capability audit (pre → post)

| Capability | Before | After Phase 16 |
|------------|--------|----------------|
| Home easy-care / indoor / outdoor rails | API partial | Home feed + web/Android surfaces when data exists |
| Recently viewed | Client local | Local + `POST /product-views` + `GET /recently-viewed` |
| Search zero-result | Weak empty state | `GET /search/assist` + alternatives UI |
| Cart warnings | Fields only | API `warnings[]` + clients |
| Notify me (OOS) | Missing | Full API + clients + admin list + inventory hook |
| Product gallery zoom | Basic | Web lightbox |
| Wishlist OOS UX (Android) | Move always enabled | Disabled when OOS |
| Price drop alerts | Missing | Still missing (no price history) |
| Helpful review votes | Missing | Still missing |
| Care reminder scheduler | Missing | Still missing (consent gap) |

---

## Evidence sources

- Catalog: `HomeService`, `ProductService`, `PlantFinderService`, search controllers  
- Phase 15 analytics gaps / backlog H1  
- Phase C / Phase 4 docs (notify-me historically missing)  
- Cart `present()`, Checkout inventory asserts  
- Clients: `nursery-web`, `nursery_app`

---

## Non-goals confirmed

No AI recommender, no fabricated care text, no fake payment/order status, no client-only stock alert state, no duplicated business logic in Next/Flutter.
