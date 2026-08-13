# QA-23 TEST MATRIX — FCM LIVE

| # | Case | Result |
|---|------|--------|
| 1 | Gateway stub without credentials | PASS |
| 2 | Legacy key invalid token | PASS |
| 3 | HTTP v1 send (Http::fake + fake SA file) | PASS |
| 4 | HTTP v1 UNREGISTERED flag | PASS |
| 5 | Delivery job deactivates invalid token | PASS |
| 6 | Payload has no secrets | PASS |
| 7 | Qa22 notification dispatch / COD staff notify | PASS (prior) |
| 8 | Real Customer→Admin FCM | **BLOCKED** |
| 9 | Real Admin→Customer FCM | **BLOCKED** |
| 10 | Foreground device | **BLOCKED** |
| 11 | Background device | **BLOCKED** |
| 12 | Terminated device | **BLOCKED** |
| 13 | Multi-device LIVE | **BLOCKED** |
| 14 | Web push | **DEFERRED** |
| 15 | Queue worker LIVE observation | **UNVERIFIED** |

Setup: `docs/QA-23-FCM-SETUP.md`
