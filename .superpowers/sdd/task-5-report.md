# Task 5 Report: `PembayaranBerhasilNotification` + hook in `PaymentAllocationService`

## What I implemented

1. **`app/Notifications/Finance/PembayaranBerhasilNotification.php`** (new) — extends `FinanceNotification`, constructed with `Tagihan $tagihan, string $metode`. `isUrgent()` returns `false`. `via()` returns `baseChannels()`. Implements `toDatabase`, `toMail`, `toWhatsApp` (via `WhatsAppTemplate::renderKode('pembayaran_berhasil', ...)`) exactly per the brief's Step 3 code.

2. **`app/Services/Finance/PaymentAllocationService.php`** (modified) — added a constructor taking `NotificationDispatcher $dispatcher` (this class previously had no constructor). In `allocate()`, `$becameLunas` is computed as `$lockedTagihan->status !== 'lunas'` **before** the status is reassigned to `'lunas'`, so a tagihan that's already lunas re-processed a second time does not re-trigger. When `$becameLunas` is true, a `DB::afterCommit()` callback is registered (capturing only the scalar `$tagihanId` and `$metode`, not the Eloquent model) that re-fetches the tagihan fresh (with `jenisTagihan`, `tagihable`), resolves the kontak utama via `$siswa->orangTua()->wherePivot('is_kontak_utama', true)->first()`, and dispatches through `$this->dispatcher->send(...)` (used the injected dispatcher directly rather than `app(NotificationDispatcher::class)` inside the closure — equivalent behavior, slightly cleaner since it's already a constructor dependency).

3. **`tests/Feature/Keuangan/PembayaranBerhasilNotificationTest.php`** (new) — the 3 tests exactly as specified in the brief.

## Deviation: fixed a pre-existing bug in `NotificationDispatcher::logAttempt`

**Not in the brief's file list, but required for the feature to actually work and for the full regression suite to pass.**

`NotificationDispatcher::logAttempt()` (from Task 2) wrote `notification_logs.user_id = $notifiable->id` unconditionally for any notifiable with an integer `id`. That's correct for a `User` notifiable, but wrong for `OrangTua` — `OrangTua->id` is the `orang_tua` table's own primary key, not `users.id`; the actual FK-linked user id lives on `OrangTua->user_id`. Since this task's design (per the brief itself, and matching the existing `TagihanDiterbitkanNotification` pattern from Task 4) dispatches directly to `$kontakUtama` (an `OrangTua` instance), the old code would insert `notification_logs.user_id = <orang_tua.id>`, which only "works" by coincidence when that numeric value happens to also be a valid `users.id` — otherwise it throws a FK `QueryException`. This is exactly what happened: my new test passed in isolation (lucky ID collision with a real user) but failed with `SQLSTATE[23000]` under the full `tests/Feature/Keuangan/` run once more prior tests had shifted the ID sequences out of coincidental alignment.

Fix in `app/Services/Finance/NotificationDispatcher.php`:
```php
$userId = $notifiable instanceof \App\Models\User
    ? $notifiable->id
    : ($notifiable->user_id ?? null);
```
This is a minimal, targeted fix scoped to the exact bug; it doesn't touch anything else in that class. Existing `NotificationDispatcherTest.php` tests only ever pass a `User` notifiable, so this path was previously untested and unexercised outside `Notification::fake()`-shielded contexts.

## Existing test fixed for the new constructor (Step 6, same pattern as Task 4)

`tests/Feature/Keuangan/PaymentAllocationServiceTest.php` had two call sites using `new PaymentAllocationService()` (old 0-arg constructor). Changed both to `app(PaymentAllocationService::class)`.

Verified no other production or test code calls `new PaymentAllocationService(...)` (grep confirmed the only remaining textual match was in the plan doc itself).

## Test commands run and output

**Step 2 — RED**, `php artisan test tests/Feature/Keuangan/PembayaranBerhasilNotificationTest.php`:
```
FAIL  Tests\Feature\Keuangan\PembayaranBerhasilNotificationTest
⨯ it sends PembayaranBerhasilNotification when a tagihan transitions to lunas   8.01s
✓ it does not send when the tagihan only becomes sebagian, not lunas           0.09s
✓ it does not send twice if allocate() is somehow called again on an already-lunas tagihan  0.11s
Tests: 1 failed, 2 passed (3 assertions)
```
Failure: `The expected [App\Notifications\Finance\PembayaranBerhasilNotification] notification was not sent.` — expected, since the class and hook didn't exist yet (the other two "pass" trivially because nothing is sent in either case pre-implementation).

**Step 5 — GREEN**, same command after implementing:
```
PASS  Tests\Feature\Keuangan\PembayaranBerhasilNotificationTest
✓ it sends PembayaranBerhasilNotification when a tagihan transitions to lunas   8.23s
✓ it does not send when the tagihan only becomes sebagian, not lunas           0.09s
✓ it does not send twice if allocate() is somehow called again on an already-lunas tagihan  0.09s
Tests: 3 passed (3 assertions)
```

**Step 6 — full regression**, `php artisan test tests/Feature/Keuangan/`:

First run (before the `NotificationDispatcher` fix) failed with:
- `PaymentAllocationServiceTest` × 2 — `ArgumentCountError` (old 0-arg construction) → fixed by switching to `app(PaymentAllocationService::class)`.
- `PembayaranBerhasilNotificationTest` × 2 — `SQLSTATE[23000]` FK violation on `notification_logs.user_id` → fixed by the `NotificationDispatcher::logAttempt` change described above.

Second run, after both fixes:
```
Tests:    117 passed (322 assertions)
Duration: 34.28s
```
All 33 test files in `tests/Feature/Keuangan/` pass, including the 3 new tests and the 2 corrected `PaymentAllocationServiceTest` cases.

## Self-review

- Idempotency: verified `$becameLunas` is computed strictly before the status mutation, and the 3rd test (calling `allocate()` twice on an already-lunas tagihan) passes — no duplicate notification.
- `afterCommit` closure captures only `$tagihanId` (int) and `$metode` (string), not the `$lockedTagihan` Eloquent instance, avoiding stale-model issues; it re-fetches fresh inside the callback as instructed.
- Guarded against `tagihable_type !== Siswa::class` and `$freshTagihan === null` (tagihan could theoretically have been deleted between commit and callback) before touching `$siswa`.
- Guarded against no kontak-utama existing (`$kontakUtama !== null`) so a tagihan with no parent contact doesn't throw.
- The `NotificationDispatcher` fix is a real production bug fix, not scope creep for its own sake — without it, dispatching `PembayaranBerhasilNotification` (or `TagihanDiterbitkanNotification` from Task 4, which has the identical `$kontakUtama`-is-`OrangTua` pattern) to a parent whose `orang_tua.id` doesn't coincidentally equal a valid `users.id` would throw an uncaught `QueryException` in production, outside a transaction, when persisting the notification log. I flagged this explicitly rather than silently patching it.
- Ran the entire `tests/Feature/Keuangan/` directory (117 tests), not just the new file, per Step 6's requirement, since the constructor signature change affects all 4 callers of `PaymentAllocationService`.
