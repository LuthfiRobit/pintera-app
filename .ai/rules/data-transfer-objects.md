---
paths:
  - 'app/Domains/*/DataTransferObjects/**'
---

# Data Transfer Objects

## DTOs are plain readonly classes
DTOs are `final readonly class` with public promoted constructor properties — not spatie/laravel-data. When a factory method is needed, name it fromArray(), not fromRequest()/fromValidated()/make().
