# PHASE 16 — Customer Website UX

**App:** `apps/nursery-web`

---

## Home

- **Continue shopping** rail from local recently viewed (hidden if empty)
- Surfaces **Easy care picks** (`recommended_for_you`) with honest copy (rule-based)
- **Indoor** / **Outdoor & balcony** rails only when API returns data
- Existing featured / best sellers / new arrivals / campaigns retained

## Search

- Zero results call `/search/assist`
- Shows filter shortcuts, related categories, popular searches (from `search_events` when available), popular in-stock products, Find Your Plant CTA
- Does not invent products

## Product detail

- Gallery: tap-to-enlarge lightbox, keyboard Esc/arrows
- **Notify me** when out of stock (login required) via stock-alert API
- Recently viewed still local + best-effort `POST /product-views`
- Related / recommendations unchanged (API filters OOS)

## Cart

- Renders API `warnings`
- Blocks checkout CTA when `checkout_blocked`

## Orders / payment recovery

- Payment failed copy: try again, return to cart, continue shopping
- Does not claim success until backend confirms

## Types / client plumbing

- `HomeData` extended; `Cart.warnings` / `checkout_blocked`
- Guest token in `storage` + `X-Guest-Token` on API requests

## Deferred (documented)

- Price-change badges (no history)
- Helpful review votes
- Care reminder scheduler
- Separate `/checkout/success` page (order detail query params remain)
