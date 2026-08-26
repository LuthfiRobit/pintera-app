---
paths:
  - 'app/Domains/**'
---

# Domains

## New business logic always lives under app/Domains/{Domain}
All new models, Actions, Services, DTOs, and Enums for a business domain go under app/Domains/{Domain}/... . app/Models/ and app/Enums/ (root) are a frozen legacy zone from before the domain refactor — read/reference them, but never add new files there, even for a domain whose older model still lives in app/Models/.
