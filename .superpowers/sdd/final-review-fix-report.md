# Final Whole-Plan Review Fix Report — Keuangan Sub-project 05: Notifikasi

Branch: `demo`, base HEAD before this work: `d02ce92`.
Scope: fix Critical + Important + Minor findings from the final whole-branch review of Sub-project 05, in one combined commit.

## Fix 1 (CRITICAL) — Unguarded notification dispatch in `AutoAllocationEngine::run()`

**File**: `app/Services/Finance/AutoAllocationEngine.php`

Wrapped the `SaldoTidakCukupNotification` dispatch (post-`DB::transaction()`) in try/catch with `Log::error(...)`, matching the exact pattern already used in `ManualPaymentController::approve()`'s topup block and `PaymentAllocationService::allocate()`'s `afterCommit` block. Added `use Illuminate\Support\Facades\Log;`. Nothing else in the method (locking, transaction structure) was touched — this loop body is now also combined with Fix 3's "first skipped only" change (see below), since both touch the same few lines.

Why this matters: `Wallet::topup()` calls `run()` uncaught (`app/Models/Wallet.php:71`). Before this fix, if `MailChannel` threw (it does not swallow exceptions the way `WhatsAppChannel` does — confirmed by reading `app/Notifications/Channels`), the exception would propagate out of `run()` → `topup()` → the caller. In `ManualPaymentController::approve()`'s topup branch (lines 99–105), that already-committed topup would get marked `topup_status = 'failed'` even though the money had landed. `ReconcilePayments::retryFailedTopups()` (`app/Console/Commands/ReconcilePayments.php:121-142`) would then re-call `topup()` on that same row later, crediting the wallet a second time — duplicate money. Now the notification failure is logged and swallowed, and `topup()`'s caller sees success.

## Fix 2 (IMPORTANT) — Same missing guard in `TagihanBillingGenerator` and `KirimDueReminderTagihan`

**Files**:
- `app/Services/TagihanBillingGenerator.php` — wrapped the `TagihanDiterbitkanNotification` dispatch in `generateForSiswa()` (after `DB::transaction()` returns) in try/catch + `Log::error(...)`. Added `use Illuminate\Support\Facades\Log;`.
- `app/Console/Commands/KirimDueReminderTagihan.php` — wrapped the `$dispatcher->send(...)` call inside the per-tagihan `foreach` loop in try/catch + `Log::error(...)`, with an explicit `continue` in the catch block so one bad recipient (e.g. a `MailChannel` throw) does not abort the whole run — the loop proceeds to the next tagihan. Added `use Illuminate\Support\Facades\Log;`.

No other logic in either file was changed.

## Fix 3 (IMPORTANT) — `SaldoTidakCukupNotification` over-fires

**File**: `app/Services/Finance/AutoAllocationEngine.php`

Re-read `.agents/specs/keuangan-05-notifikasi.md`:
- Line 73 (event table): "Auto-Allocation Engine men-skip **tagihan prioritas tertinggi** karena saldo kurang" — singular, highest-priority only.
- Lines 151–156 (Addendum A, "In-memory atau persist ke DB?"): explicitly confirms the design is **pure in-memory**, with no new table/column, and gives 3 reasons why persistence is unnecessary — `notification_logs` already gives an audit trail of every send attempt, and a separate "skipped_tagihan" idempotency table is called out by name as **YAGNI**. The spec does NOT mention any same-day idempotency requirement for this notification anywhere (unlike `DueReminderNotification`, where H-3/H-1 same-day dedup via `notification_logs` IS explicitly spec'd at line 94).

Conclusion: the spec wants "first skipped tagihan only," not "first skipped + same-day idempotency." I implemented only the first-only fix, per the instructions' explicit permission to skip idempotency if the spec doesn't call for both.

Changed the dispatch loop from iterating all of `$skippedTagihan` to taking only `$skippedTagihan->first()` (the collection is already priority-ordered upstream by the `orderBy('jenis_tagihan.priority_score', ...)` query that produced `$tagihans`), and dispatches once.

**Test update**: `tests/Feature/Keuangan/SaldoTidakCukupNotificationTest.php` — the existing "sends ... for a tagihan that gets fully skipped" test already only had ONE skipped tagihan in its scenario, so it needed no change and still passes. I added a NEW test, `'only sends SaldoTidakCukupNotification for the highest-priority skipped tagihan when multiple are skipped'`, with 3 tagihan where 2 get skipped — it asserts the notification fires for the higher-priority of the two skipped tagihan (not the lowest-priority one), and asserts `assertSentToTimes($orangTua, SaldoTidakCukupNotification::class, 1)` to lock in the "only once" behavior.

## Fix 4 (Minor) — `siswaLembagaId()` nullable parameter

**File**: `app/Http/Controllers\Admin\ManualPaymentController.php`

Changed `private function siswaLembagaId(int $siswaId): ?int` to `private function siswaLembagaId(?int $siswaId): ?int`, returning `null` immediately when `$siswaId` is `null`. This lets `abort_unless($siswaLembagaId !== null && ...)` correctly 404 instead of hitting a `TypeError` for `Pembayaran` rows created by `AutoAllocationEngine` that have `wallet_id` set but no `siswa_id`.

## Fix 5 (Minor) — Regression test for `logAttempt()` OrangTua-vs-User fix

**File**: `tests/Feature/Keuangan/NotificationDispatcherTest.php`

Added `'logs notification_logs.user_id using the OrangTua notifiable's user_id, not its own id'`: creates a throwaway `OrangTua` first purely to push subsequent auto-increment ids out of alignment (so `id !== user_id` is guaranteed even on a fresh test DB where the first row of both tables could otherwise coincide at id 1), then a second `OrangTua`, asserts `$orangTua->id !== $orangTua->user_id`, dispatches via `NotificationDispatcher::send($orangTua, ...)`, and asserts the resulting `NotificationLog->user_id` equals `$orangTua->user_id`, not `$orangTua->id`.

TDD sanity check (via `git show` on `logAttempt()`'s pre-Task-5 form and mentally substituting): the old code used `$notifiable->id` unconditionally for the log's `user_id` (or an equivalent shape that didn't special-case `OrangTua`), which would have written the `OrangTua`'s own PK instead of `user_id` — the new test's `expect($log->user_id)->toBe($orangTua->user_id)` would have failed against that old code (since `$orangTua->id !== $orangTua->user_id` by construction) and correctly fails to catch the bug if run against current code — confirmed it passes now (see test run below).

## Fix 6 (Minor) — Deleted placeholder test

**File**: `tests/Feature/Keuangan/PaymentServiceManualTopupTest.php`

Deleted the `it('does not affect the existing createManualPayment bill-payment path', ...)` block entirely — its body was `expect(true)->toBeTrue()`, a no-op. Its own docblock already states the real regression coverage lives in `PaymentServiceTest.php`, which is run separately (and was run again below). File is otherwise untouched.

## Fix 7 (Minor, cosmetic) — Import ordering in `routes/admin.php`

**File**: `routes/admin.php`

`use App\Http\Controllers\Admin\ManualPaymentController;` was between `OrangTuaController` and `PembayaranController` (out of order). Moved it to its correct alphabetical position, immediately before `MataPelajaranController` and after `LembagaController` ("Man" < "Mat" alphabetically). Note: a pre-existing, unrelated ordering issue was spotted nearby (`JenisTesMasterController` appearing after `KategoriKeringananController`) — left untouched, out of scope for this plan.

## What was explicitly NOT touched (per instructions)

- `PaymentAllocationService::allocate()`'s `paid_amount +=` double-counting on re-call — confirmed pre-existing/unrelated.
- No "partial allocation" notification added.
- Minor-1 (`TransferManualDisetujuiNotification` message wording) and Minor-2 (double notification on bill-payment approval) — left as product/UX decisions, not touched.

## Test commands run and output

All run individually in the foreground against MySQL `pintera_app_test`, one at a time, per instructions:

```
php artisan test tests/Feature/Keuangan/AutoAllocationEngineTest.php
  Tests: 4 passed (21 assertions)

php artisan test tests/Feature/Keuangan/SaldoTidakCukupNotificationTest.php
  Tests: 4 passed (5 assertions)

php artisan test tests/Feature/Keuangan/TagihanDiterbitkanNotificationTest.php tests/Feature/Keuangan/TagihanBillingGeneratorTest.php
  Tests: 12 passed (38 assertions)

php artisan test tests/Feature/Console/KirimDueReminderTagihanTest.php
  Tests: 4 passed (7 assertions)

php artisan test tests/Feature/Admin/ManualPaymentControllerTest.php
  Tests: 8 passed (35 assertions)
```

Final full-surface run (whole plan's test surface together):

```
php artisan test tests/Feature/Keuangan/ tests/Feature/Admin/ManualPaymentControllerTest.php tests/Feature/Console/KirimDueReminderTagihanTest.php

Tests:    135 passed (382 assertions)
Duration: 38.34s
```

Zero failures, zero regressions.

## Deviations from instructions

- **Fix 3 idempotency**: per the instructions' own conditional ("if the spec re-read reveals same-day idempotency is ALSO required ... implement both"), I re-read Addendum A and found it explicitly rules out persistence/idempotency infrastructure as YAGNI for this specific notification. I implemented first-only ONLY, no same-day idempotency layer. This is a deliberate application of the instructions' branch, not an omission.
- No other deviations. All 7 fixes were applied as specified, at the exact call sites named, without touching surrounding logic.

## Self-review

- Fix 1 and Fix 3 both touch the same ~10 lines in `AutoAllocationEngine::run()`'s post-transaction block; I combined them into one coherent edit rather than two separate passes, since doing them separately would have meant writing then immediately rewriting the same lines. Structure and transaction/locking boundaries are unchanged, per the instruction not to touch that discipline.
- Fix 2's `KirimDueReminderTagihan` catch block places `continue` explicitly rather than relying on falling through, to make the "don't abort the loop" intent unambiguous to a future reader, even though it's the last statement in the loop body either way.
- Fix 4: confirmed via `AutoAllocationEngine::run()` that `Pembayaran::create()` there sets `wallet_id` but never `siswa_id`, which is the concrete (not hypothetical) source of nullable `siswa_id` rows this fix protects against.
- Fix 5's test creates an extra throwaway `OrangTua` first specifically to avoid a flaky pass on a fresh DB where the first-ever `OrangTua` row could accidentally have `id === user_id` (both starting at 1), which would make the assertion `$orangTua->id !== $orangTua->user_id` fail for the wrong reason (data coincidence, not the code's actual correctness). This is a bit defensive but keeps the test deterministic regardless of run order/seed state.
- All existing covering tests for touched files pass unchanged (except the intentionally-updated `SaldoTidakCukupNotificationTest.php`, whose existing test needed no edits and one new test was added, and `PaymentServiceManualTopupTest.php`, where one dead test was deleted).
- Ran the full combined suite (135 tests, 382 assertions) as the final gate; all green.
