# QA-42A TEST MATRIX

| Scenario | Expected | Result | Evidence |
|----------|----------|--------|----------|
| Online idle Home | No offline banner | PASS | `01-home-online-idle.png` |
| Online pull-to-refresh | Content stays; RefreshIndicator only; no saved-data banner | PASS | `02-home-ptr-in-progress.png` |
| Online PTR settled | No banner | PASS | `03-home-ptr-settled.png` |
| API down + PTR | Content stays; offline · saved data banner | PASS | `06-home-ptr-api-down.png` |
| API restore + PTR | Smooth recovery / optional Back online | PARTIAL | `07-home-ptr-api-restored.png` |
| Unit: peek ≠ degraded | servingLocal false | PASS | `qa42a_refresh_banner_test.dart` |
| Unit: markDegraded | Banner copy | PASS | same |
| Unit: quiet sync | No offline copy | PASS | same |
| Shop/Cart/Orders PTR | Same rules | UNVERIFIED | — |
| Admin soft refresh | No false offline chrome | N/A / prior soft-error | — |
| Web banner | Still connectivity-only | PASS (unit) | — |
