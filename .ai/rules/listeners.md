---
paths:
  - 'app/Listeners/**'
---

# Listeners

## Events dispatched via event() helper, listeners registered manually
Dispatch domain events with the `event(new X($model))` helper, not `X::dispatch()`. Register listeners explicitly via `Event::listen()` calls in `AppServiceProvider`, not auto-discovery or a dedicated `EventServiceProvider`. Listeners run synchronously — do not add `ShouldQueue`.
