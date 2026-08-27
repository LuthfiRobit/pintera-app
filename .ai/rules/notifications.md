---
paths:
  - 'app/Notifications/**'
---

# Notifications

## Notifications are sync; custom channels registered via Notification::extend
Notifications do not use `ShouldQueue` — they send synchronously. A custom delivery channel (e.g. WhatsApp) is a plain class exposing `send()`, registered via `Notification::extend('name', ...)` in `AppServiceProvider` (see `WhatsAppChannel`).
