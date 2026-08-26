---
paths:
  - 'app/Domains/*/Actions/**'
---

# Actions

## Action classes: execute() + constructor DI
Each Action class exposes exactly one public method, execute(). Inject dependencies via constructor promoted private/protected readonly properties, never app()/resolve() service location. Extra logic stays in private helper methods, not additional public entry points. An Action may inject another Action as a collaborator dependency.

## Multi-step writes use DB::transaction() closures
Wrap multi-step mutations in DB::transaction(fn () => ...). Manual beginTransaction()/commit()/rollBack() is never used.
