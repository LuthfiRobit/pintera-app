---
paths:
  - 'app/Services/**'
---

# App Services

## Root Services are cross-cutting, not domain-specific
app/Services/** (root, outside app/Domains) is reserved for genuinely app-wide/cross-cutting logic (account generators, dashboard aggregation, permission catalog, OTP) — not logic specific to one business domain. Domain-specific reusable logic belongs in app/Domains/{Domain}/Services/, not here.
