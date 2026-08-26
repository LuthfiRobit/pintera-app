---
paths:
  - 'routes/**'
---

# Routes

## Route handlers are controller references, never closures
Routes always point to [Controller::class, 'method'], never inline closures.

## Middleware assigned at the route/group level
Middleware is attached via ->middleware() on routes/groups. Controllers never implement HasMiddleware or use a #[Middleware] attribute.

## Rate limiting: inline throttle, no named limiters
Rate limiting uses inline throttle:N,1 middleware directly on routes. There's no RateLimiter::for() named limiter defined anywhere — don't introduce one.
