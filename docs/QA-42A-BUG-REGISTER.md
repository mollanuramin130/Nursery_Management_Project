# QA-42A BUG REGISTER

| ID | Severity | Client | Root cause | Fix | Status |
|----|----------|--------|------------|-----|--------|
| QA-42A-001 | P0 | Customer Mobile | Cache peek set `servingLocal` → offline banner on every refresh | `markSource` quiet by default; `markDegraded` on real fallback | FIXED |
| QA-42A-002 | P1 | Customer Mobile | Banner also shown for quiet `syncing` | Show sync chrome only when degraded/recovering | FIXED |
| QA-42A-003 | P2 | Customer Mobile | Wording “Showing your saved GreenLeaf data” felt like failure | “You're offline · Showing saved data” + Back online | FIXED |
| QA-42A-010 | P2 | Customer Mobile | Multi-screen PTR not fully filmed | — | UNVERIFIED |
| QA-42A-011 | P3 | Customer Mobile | PDP image spinner during blip | — | UNVERIFIED / PRE-EXISTING |

## Classification

- **FIXED:** QA-42A-001…003  
- **UNVERIFIED:** QA-42A-010, QA-42A-011  
- **BLOCKED:** none for this scope (LIVE still out of scope globally)
