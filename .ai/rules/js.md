---
paths:
  - 'resources/js/**'
---

# Js

## Complex Alpine logic goes in a registered module
Non-trivial Alpine.js logic (forms, tables, filters, charts) is extracted into its own file under resources/js/*.js and registered via Alpine.data('name', fn) in app.js, then referenced from Blade as x-data="name(...)". Inline x-data="{ ... }" is reserved for trivial UI state (toggles, small dropdowns).
