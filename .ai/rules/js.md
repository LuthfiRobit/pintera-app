---
paths:
  - 'resources/js/**'
---

# Js

## Complex Alpine logic goes in a registered module
Non-trivial Alpine.js logic (forms, tables, filters, charts) is extracted into its own file under resources/js/*.js and registered via Alpine.data('name', fn) in app.js, then referenced from Blade as x-data="name(...)". Inline x-data="{ ... }" is reserved for trivial UI state (toggles, small dropdowns).

## Filter already-loaded data client-side, not via AJAX
If a filter/search only narrows rows already present in the DOM, implement it as pure client-side filtering (Alpine reactive state, no network call). Only trigger an AJAX reload when the filter actually changes which dataset the server must return (e.g. a different tahun ajaran/semester). Debouncing a network call for data that's already in the browser is redundant and adds needless latency.
