---
paths:
  - 'app/Domains/*/Services/**'
---

# Services

## Service vs Action responsibility split
A Service holds reusable logic called from multiple Actions/Controllers (resolvers, generators, aggregators, engines) — suffix accordingly (Resolver/Generator/Aggregator/Engine). An Action represents one specific business use-case, typically called from a single controller route.
