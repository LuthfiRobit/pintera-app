---
paths:
  - 'app/**'
---

# App

## Collections: collect()->map()/filter() for data transformation
Data transformation/filtering pipelines use collect()->map()/filter()/pluck()/sortBy(), not array_map()/array_filter(). Plain foreach is still fine for side-effecting loops (building up related records, etc.) — that's a different use case, not a rival.

## Strings: native PHP functions, Str:: only for Laravel-specific helpers
Basic string operations (trim, ucfirst, strtoupper, etc.) use native PHP functions, not Str::. The Str:: facade is reserved for helpers with no native equivalent (Str::random(), Str::uuid(), Str::slug()). Str::of() fluent style is never used.

## Dates: now()/today() helpers, Carbon::parse() only for parsing
Get the current date/time via now()/today() helpers, never Carbon::now(). Carbon::parse() is fine specifically for parsing an incoming date string — that's a different job, not a rival to now().
