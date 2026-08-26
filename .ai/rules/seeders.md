---
paths:
  - 'database/seeders/**'
---

# Seeders

## Seeders write idempotently
Seeders use firstOrCreate()/updateOrCreate() for the records they own, not upsert() or find-then-save. (Plain ::where()->first() lookups of a different model's existing record, e.g. an active TahunAjaran, are a normal dependency fetch, not a rival pattern.)
