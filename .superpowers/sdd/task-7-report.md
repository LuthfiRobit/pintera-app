# Task 7 Report: Cross-parent authorization regression suite

## What was implemented

Created `tests/Feature/Keuangan/CheckoutAuthorizationTest.php` with 6 tests total:

1. The 5 tests exactly as given in `.superpowers/sdd/task-7-brief.md` (verbatim `makeParentWithChild()` fixture + the 5 `it(...)` cases covering `TagihanController@index`, `checkout.wallet`, `checkout.transfer`, `checkout.status`, `checkout.sukses`).
2. One additional test (per the parent agent's instruction, closing a gap flagged by Task 6's reviewer): `it('blocks a parent from viewing another parent\'s menunggu-verifikasi page')`. It reuses the same `makeParentWithChild()` fixture and the `Storage::fake('public')` + `UploadedFile::fake()->image(...)` pattern from `tests/Feature/Keuangan/CheckoutControllerTransferTest.php`. Parent A submits a manual-transfer checkout for their own tagihan (producing a `Pembayaran` with `metode = transfer_manual`), then Parent B calls `GET route('keuangan.checkout.menunggu-verifikasi', $pembayaranA)` and the test asserts `assertForbidden()`.

## Was a gap found?

**No production authorization gap was found.** `CheckoutController::menungguVerifikasi()` already calls `$this->authorizePembayaran($request, $pembayaran)` as its first line (app/Http/Controllers/Keuangan/CheckoutController.php:145), exactly like every other `Pembayaran`-loading action in the controller. The new test passed on the first run with no code changes.

## Test-fixture issue found and fixed (not a production gap)

The brief's first test (`it('does not show another parent\'s tagihan in the rekap tagihan list')`) failed on first run with:

```
ErrorException: Attempt to read property "nama" on null
at tests/Feature/Keuangan/CheckoutAuthorizationTest.php:53
$response->assertSee($tagihanA->jenisTagihan->nama);
```

**Root cause:** `JenisTagihan` carries `TenantScope`, which filters `WHERE lembaga_id = $actingUser->lembaga_id`. Parents (`orang_tua` role) are created with `lembaga_id: null` (per `makeParentWithChild()`, matching how parents are modeled — they're not tied to a lembaga directly, their children are). When the test itself accessed `$tagihanA->jenisTagihan` as a lazy relation *while still `actingAs($userA)`*, the scope applied `lembaga_id = null`, which doesn't match the `JenisTagihan` row's real (randomly-factory-assigned) `lembaga_id`, so the relation resolved to `null`.

This is exactly the caveat the parent agent flagged in advance: `TagihanController@index` (app/Http/Controllers/Keuangan/TagihanController.php:26) already eager-loads `jenisTagihan` with `->withoutGlobalScope(TenantScope::class)` in the controller itself, so the **actual HTTP response correctly includes the jenis name** — `$response->assertOk()` passed fine. The failure was purely an artifact of the test reaching for the relation directly through a scoped Eloquent call, not a controller/production bug.

**Fix (test-only):** replaced the direct relation access with an explicit scope-bypassing lookup, mirroring the controller's own pattern:

```php
$namaJenisA = JenisTagihan::withoutGlobalScope(TenantScope::class)->find($tagihanA->jenis_tagihan_id)->nama;
$response->assertSee($namaJenisA);
```

No production code was touched.

## Test commands run

```
php artisan test tests/Feature/Keuangan/CheckoutAuthorizationTest.php
```

- First run: 5 passed, 1 failed (the `jenisTagihan` relation issue above).
- After the test-only fix, re-run: **6 passed (10 assertions)**, ~9.4s.

No other test command was run (per instructions, only this file was run, and only in the foreground/synchronously).

## Self-review notes

- All 5 brief tests kept verbatim (fixture and assertions unchanged, aside from the one necessary relation-access fix described above — the assertion still checks the same thing, just via an unscoped read of the same data).
- New 6th test follows the identical two-party `makeParentWithChild()` pattern, reuses `Storage::fake('public')` + `UploadedFile::fake()->image(...)` from `CheckoutControllerTransferTest.php`, and asserts `assertForbidden()` exactly like the brief's other cross-parent GET tests (`status`, `sukses`).
- No production code was modified — `authorizePembayaran()` was already correctly wired into `menungguVerifikasi()` from Task 6.
- Confirmed guard is `web` only (fixture uses `Role::firstOrCreate([...guard_name' => 'web'])`); no `PaymentService`, `AutoAllocationEngine`, `Wallet`, or BRI webhook files were touched; cicilan not referenced.

## Commit

Committed with message documenting that this is a pure regression-suite addition (no production fix needed):

```
test(keuangan): add two-party cross-parent authorization regression suite for 6b
```

(See `git log` for the exact commit hash — recorded in the STATUS reply.)

## Fix round: strengthen vacuous assertions

Following a task review, 2 of the 6 tests in `tests/Feature/Keuangan/CheckoutAuthorizationTest.php` were found to have assertions that would stay green even if the real authorization gap they're named for were reintroduced. Both were strengthened in place (no new test methods for these two), and one optional 7th test was added per the reviewer's Minor note.

### Bug 1 fix — "does not show another parent's tagihan in the rekap tagihan list"

**Before:** `assertDatabaseMissing('tagihan', ['id' => $tagihanB->id, 'tagihable_id' => $tagihanA->tagihable_id])` — checked whether tagihanB's DB row got reassigned to siswaA's FK, which no view/query-scoping leak in `TagihanController::index` could ever cause.

**After:** captured `$namaJenisB` the same scope-bypassing way as `$namaJenisA`, and replaced the vacuous DB assertion with `$response->assertDontSee($namaJenisB)`, kept alongside the existing `$response->assertSee($namaJenisA)`. Also had to fix a latent test-fixture flaw exposed by this change: `JenisTagihan::factory()` hardcodes `nama => 'Biaya Pendaftaran'`, so both parents' tagihan had the identical jenis name and `assertDontSee` was trivially unsatisfiable regardless of scoping. Fixed by giving each fixture's `JenisTagihan` a label-distinct name: `'nama' => "Jenis Tagihan {$label}"` in `makeParentWithChild()`.

**Red/green proof:** Temporarily removed the `->where('tagihable_type', ...)->where('tagihable_id', ...)` filter in `TagihanController::index` (app/Http/Controllers/Keuangan/TagihanController.php), replacing it with `Tagihan::query()->whereIn('status', ...)`. Re-ran the single test:

```
FAIL  Tests\Feature\Keuangan\CheckoutAuthorizationTest > it does not show another parent's tagihan...
Not to contain: Jenis Tagihan B
at tests/Feature/Keuangan/CheckoutAuthorizationTest.php:63
Tests: 1 failed (3 assertions)
```

Confirmed RED. Reverted the controller change exactly (`git diff --stat` on the file showed no residual diff after revert). Re-ran the full file: all 7 passed again (GREEN).

### Bug 2 fix — "rejects wallet checkout for a tagihan belonging to another parent's child"

**Before:** `assertEquals(0, Pembayaran::where('siswa_id', $tagihanB->tagihable_id)->count())` — wrong field. Traced `PaymentService::createWalletPayment()` → `createPembayaranRecord()` (app/Services/Finance/PaymentService.php:242-263): it stamps `Pembayaran.siswa_id` from the acting parent's own `$siswa` argument (siswaA), never from the tagihan being paid. A hypothetical regression that dropped the ownership check would produce a leaked `Pembayaran` with `siswa_id = siswaA->id`, not `tagihanB->tagihable_id`, so this assertion would stay green even with the real bug present.

**After:**
```php
$this->assertEquals(0, Pembayaran::count());
$this->assertEquals('belum_bayar', $tagihanB->fresh()->status);
```
Two independent discriminating signals: no payment record created at all, and tagihanB's own status untouched.

**Red/green proof:** Temporarily removed the `->where('tagihable_type', ...)->where('tagihable_id', ...)` ownership filter in `CheckoutController::resolveSelectedTagihan()` (app/Http/Controllers/Keuangan/CheckoutController.php:173-180), leaving only the status/id filters. Re-ran the single test:

```
FAIL  Tests\Feature\Keuangan\CheckoutAuthorizationTest > it rejects wallet checkout for a tagihan belonging to...
Failed asserting that 1 matches expected 0.
at tests/Feature/Keuangan/CheckoutAuthorizationTest.php:77 ($this->assertEquals(0, Pembayaran::count());)
Tests: 1 failed (1 assertions)
```

Confirmed RED (leaked Pembayaran with siswaA's own siswa_id was created, but was still caught by the count-based assertion — proving it's now discriminating where the old `siswa_id`-filtered assertion was not). Reverted the controller change exactly (`git diff --stat` on the file showed no residual diff after revert). Re-ran the full file: all 7 passed again (GREEN).

### Optional 7th test added

Per the reviewer's Minor note, added `it('blocks a parent from viewing another parent's qris checkout page')`: Parent A submits `POST route('keuangan.checkout.qris')` for their own tagihan, producing a `Pembayaran` with `metode = 'qris'`; Parent B then `GET route('keuangan.checkout.show', $pembayaranA)` and the test asserts `assertForbidden()`. Mirrors the existing `status`/`sukses` two-party pattern; no unusual fixture setup was needed since `makeParentWithChild()` and the `checkout.qris` route already existed and `CheckoutController::show()` already calls `authorizePembayaran()` for both VA and QRIS pembayaran alike.

### Final test run

```
php artisan test tests/Feature/Keuangan/CheckoutAuthorizationTest.php

PASS  Tests\Feature\Keuangan\CheckoutAuthorizationTest
✓ it does not show another parent's tagihan in the rekap tagihan list
✓ it rejects wallet checkout for a tagihan belonging to another parent's child
✓ it rejects manual transfer checkout for a tagihan belonging to another parent's child
✓ it blocks a parent from polling the status of another parent's pembayaran
✓ it blocks a parent from viewing another parent's wallet success page
✓ it blocks a parent from viewing another parent's qris checkout page
✓ it blocks a parent from viewing another parent's menunggu-verifikasi page

Tests: 7 passed (12 assertions)
```
