---
paths:
  - 'app/Domains/*/Contracts/**'
---

# Contracts

## Contracts: dependency-inversion interface or polymorphic marker
An interface here is either (a) a real contract with methods for a swappable implementation (e.g. a payment gateway), or (b) an intentionally empty marker interface used to constrain which models are valid for a polymorphic relation. Don't add methods to a marker interface without an actual caller that needs them.
