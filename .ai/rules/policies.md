---
paths:
  - 'app/Policies/**'
---

# Policies

## Policy classes are not the primary authorization mechanism
This app authorizes primarily through Spatie permission strings ($this->authorize()/$user->can()), not Laravel Policy classes. The few Policy-shaped classes that exist are typically plain injectable domain services manually called from controllers, not framework-resolved Policies — don't assume policy auto-discovery is wired up.
