---
paths:
  - 'app/Support/**'
---

# Support

## Support/ is for app-wide helpers only, never per-domain
app/Support/ (root, singular) holds only stateless utilities that are truly app-wide and unrelated to any single business domain. Never create a Support/ folder inside a domain — a domain's stateless helper belongs in that domain's Services/ folder instead.
