# Admin API Gaps

Living document of Admin Portal needs vs backend contracts.

## Phase 2

### Inventory

| Field | Endpoint | Current | Required | Reason | Alternative | DB impact | Clients | Recommendation |
|-------|----------|---------|----------|--------|-------------|-----------|---------|----------------|
| History prior/new qty | `GET /admin/inventory/movements` | `qty_delta` only | previous + new qty | Ops audit clarity | Show delta only (done) | Would need columns or compute | Admin | Optional additive columns later |
| Pagination | `GET /admin/inventory` | Full list | Server pagination | Large catalogs | Client filters only | None | Admin | Add paginate() |
| Absolute set | `POST /admin/inventory/adjust` | `adjustment` delta | Absolute set | UX convenience | UI computes delta (done) | None | Admin | Keep delta; UI helper OK |

### Coupons

| Field | Endpoint | Current | Required | Reason | Alternative | DB impact | Clients | Recommendation |
|-------|----------|---------|----------|--------|-------------|-----------|---------|----------------|
| Product/category/customer restrictions | coupon write | Not in schema/API | Restrictions | Merchandising rules | Document unsupported | New tables/columns | Admin + checkout | Future phase |
| Pagination | `GET /admin/coupons` | Full list | Paginated | Scale | Client list OK for small N | None | Admin | Add pagination |

### Campaigns

| Field | Endpoint | Current | Required | Reason | Alternative | DB impact | Clients | Recommendation |
|-------|----------|---------|----------|--------|-------------|-----------|---------|----------------|
| Category association | campaign write | `product_ids` only | category_ids | Spec asked categories | Products only (done) | Pivot if needed | Admin + customer | Optional later |
| SEO meta | campaign | Not present | meta title/description | Spec SEO section | Omit (done) | columns/meta JSON | Website | Use `meta` JSON if needed |
| Scheduled/Expired filters | list | `status` only | schedule filters | Ops | Derive client-side from dates | None | Admin | Optional query params |

### Banners

| Field | Endpoint | Current | Required | Reason | Alternative | DB impact | Clients | Recommendation |
|-------|----------|---------|----------|--------|-------------|-----------|---------|----------------|
| Mobile image | banner | single `image_url` | mobile_image_url | Responsive assets | Same URL (done) | New column | Website + Mobile | Additive column later |
| Subtitle | banner | none | subtitle | Spec | Omit | Column | Clients | Optional |

### Customers

| Field | Endpoint | Current | Required | Reason | Alternative | DB impact | Clients | Recommendation |
|-------|----------|---------|----------|--------|-------------|-----------|---------|----------------|
| Dedicated customers route | — | `/admin/users?role=customer` | `/admin/customers` | Clarity | Query param (done) | None | Admin | Keep users filter |
| Addresses on detail | user show | not returned | addresses | Spec | Omit / future | None | Admin | Additive include |

### Refunds

| Field | Endpoint | Current | Required | Reason | Alternative | DB impact | Clients | Recommendation |
|-------|----------|---------|----------|--------|-------------|-----------|---------|----------------|
| Gateway refund | `POST /admin/refunds` | local_stub | Provider refund | Real money | Documented limitation | Integration | All | Phase 3+ payments |
| List existed? | — | create only | list | Ops | Added `GET /admin/refunds` | None | Admin | Done |

### Settings

| Field | Endpoint | Current | Required | Reason | Alternative | DB impact | Clients | Recommendation |
|-------|----------|---------|----------|--------|-------------|-----------|---------|----------------|
| Typed store settings catalog | `GET /admin/settings` | key/value rows | curated business keys | UX | Edit whatever exists | Seed rows | Admin | Seed recommended keys |

### Permissions

| Gap | Notes |
|-----|-------|
| Sample `admin` role lacks `users.manage` | By design in sample SQL; use superadmin for customers/settings/audit |
| Coupons/campaigns/banners share `campaigns.manage` | Spec wanted finer permissions; do not invent duplicate slugs |

## Phase 1 carry-forward

- Categories GET requires `products.write`
- Orders lack payment/date/sort filters
- No warehouses list endpoint (default warehouse id env used on product create)


## Phase 17 CRM permissions

`customers.view`, `customers.segment`, `marketing.view`, `marketing.manage`, `marketing.launch` — see PHASE_17_MARKETING_API.md.
