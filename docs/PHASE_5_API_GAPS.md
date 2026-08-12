# PHASE 5 — API Gaps

| Feature | Metric | Availability | Missing | Recommended |
|---------|--------|--------------|---------|-------------|
| Campaign analytics | Attributed revenue/orders/views/clicks | Unsupported | `orders.campaign_id` or attribution events | See PHASE_5_CAMPAIGN_ATTRIBUTION_GAP.md |
| Profitability | Gross/net margin | Unsupported | Product/order COGS | Cost model phase |
| Conversion funnel | Payment initiated stage | Partial | Distinct payment-initiated events | Optional payment event log |
| Season taxonomy | Named seasons | Unsupported | Season calendar config | Config table + mapping |
| Async export | Large CSV/Excel | Sync only ≤5k rows | Job queue + storage | Queue worker phase |
| Finder analytics | Session/search | Out of scope here | Events | Growth analytics phase |

Legacy `GET /admin/reports/{type}` remains for compatibility; prefer `/admin/analytics/*` for new Admin screens.
