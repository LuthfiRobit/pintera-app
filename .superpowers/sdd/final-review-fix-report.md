# Final Review Fix Report — Keuangan Sub-project 6c

Consolidated fix pass for the final whole-branch code review of Keuangan Sub-project 6c
(parent-facing riwayat transaksi + kwitansi PDF, admin pengaturan yayasan) on branch `demo`.

## Final review fix round

### Important findings

**1. `siswa.kelas` eager-loaded without `TenantScope` bypass — Fixed**
`app/Http/Controllers/Keuangan/RiwayatController.php`, `kwitansi()`: added
`'siswa.kelas' => fn ($q) => $q->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)` to the
`load()` array, alongside the existing `siswa` and `pembayaranTagihan.tagihan.jenisTagihan` bypasses.
New test: `tests/Feature/Keuangan/KwitansiControllerTest.php` →
`it('renders the kelas name, siswa name, and total amount in the kwitansi view')` — renders
`view('pdf.kwitansi', ...)` directly (bypassing the binary PDF stream) and asserts the rendered HTML
contains the kelas name (`7A Istimewa`), siswa name (`Anak Kwitansi`), and total amount (`100.000`).

**2. Riwayat list omits the "Total" (amount) column — Fixed**
`resources/views/keuangan/riwayat/index.blade.php`: added a total amount `<p>` computed from
`$pembayaran->pembayaranTagihan->sum('amount_allocated')`, formatted as `Rp{{ number_format(...) }}`,
placed next to the status badge in each row.
New test: `tests/Feature/Keuangan/RiwayatControllerIndexTest.php` →
`it('shows the total amount for each transaction row')` — asserts `100.000` appears in the response.

**3. `YayasanSettingController::update()` has no null guard — Fixed**
`app/Http/Controllers/Admin/YayasanSettingController.php`: added
`abort_if($yayasan === null, 404);` immediately after `$yayasan = Yayasan::first();` in `update()`.
New test: `tests/Feature/Admin/YayasanSettingControllerTest.php` →
`it('returns 404 on update when no yayasan row exists')` — PUTs with no `Yayasan` row seeded, asserts 404.

**4. Kwitansi tests never verified PDF content — Fixed**
`tests/Feature/Keuangan/KwitansiControllerTest.php`: added two new tests using the direct-view-render
approach (`view('pdf.kwitansi', [...])->render()`), since dompdf's HTTP response is a binary stream:
- `it('renders the kelas name, siswa name, and total amount in the kwitansi view')` — normal fixture,
  asserts siswa name, kelas name, and total amount all appear in the rendered HTML (also covers Finding 1).
- `it('renders an img tag when yayasan logo is set')` — sets `$yayasan->logo` to a fake path
  (`yayasan-logo/test-logo.png`, no real file on disk needed since only the template render is under
  test, not dompdf's file-embedding I/O), asserts render doesn't throw and the HTML contains an
  `<img` tag referencing that path.

**5. Date-range filtering: only invalid path tested, half-filled range misleading — Fixed**
`app/Http/Controllers/Keuangan/RiwayatController.php`:
- (a) Added `it('filters by a valid full date range including the end-of-day boundary')` to
  `tests/Feature/Keuangan/RiwayatControllerIndexTest.php` — creates a payment at 23:59 on the `sampai`
  date and one 5 days later, filters with `dari`/`sampai` both set to today, asserts only the in-range
  payment is returned (exercises the `23:59:59` end-of-day suffix on the previously-untested valid path).
- (b) Changed the controller to support one-sided date filters: `$dateRangeValid` now only rejects an
  explicitly inverted range (`dari > sampai`); the query applies `created_at >= dari 00:00:00` and
  `created_at <= sampai 23:59:59` independently via separate `when()` clauses, so either bound alone now
  filters. Also introduced a `filterActive` view variable
  (`$metode || ($dateRangeValid && ($dari || $sampai))`) passed to the view so the empty-state message
  in `resources/views/keuangan/riwayat/index.blade.php` only claims "no results match this filter" when
  a filter is actually in effect, instead of whenever `dari`/`sampai`/`metode` are merely present in the
  querystring (which previously misfired on an ignored invalid range).
  New test: `it('narrows results with a dari-only filter')` — asserts a `dari`-only filter actually
  excludes an older payment instead of being silently ignored.

### Minor findings

**6. Logo deleted before `update()` call — Fixed**
`app/Http/Controllers/Admin/YayasanSettingController.php`: moved `Storage::disk('public')->delete($oldLogo)`
to after a successful `$yayasan->update($data)` call, so a failed update no longer orphans the DB
reference by deleting the file first. Existing test
`it('uploads a new logo and deletes the old one')` still covers the happy path and passes.

**7. Skipped — svg upload support.** Spec-mandated (svg upload is explicitly required by the spec);
actual risk is low given only the highest-privilege role (`yayasan_super_admin`) can upload. Left as-is
per spec, no change made.

**8. Null-safety on `tagihan->jenisTagihan->nama` — Fixed**
Both `resources/views/keuangan/riwayat/index.blade.php` and `resources/views/pdf/kwitansi.blade.php`
changed to `$item->tagihan?->jenisTagihan?->nama ?? '-'` (and the equivalent in the riwayat list's
`$rincianLabel` computation) to guard against a deleted/orphaned `tagihan`/`jenisTagihan` row.

**9. `?:` vs explicit emptiness check on kwitansi total — Fixed**
`resources/views/pdf/kwitansi.blade.php`: total line changed from
`sum('amount_allocated') ?: ($pembayaran->amount ?? 0)` to
`$pembayaran->pembayaranTagihan->isNotEmpty() ? $pembayaran->pembayaranTagihan->sum('amount_allocated') : ($pembayaran->amount ?? 0)`,
so a legitimate all-zero-allocation sum no longer incorrectly falls through to `$pembayaran->amount`.

**10. Skipped — no migration needed at current scale.** Reviewed and confirmed already correctly
deferred per the plan; no change made.

**11. Skipped — cosmetic icon omission.** Matches what the plan itself specified, not a real gap.
Reviewed and accepted as-is; no change made.

## New / updated tests

- `tests/Feature/Admin/YayasanSettingControllerTest.php`
  - `it('returns 404 on update when no yayasan row exists')` (new)
- `tests/Feature/Keuangan/KwitansiControllerTest.php`
  - `it('renders the kelas name, siswa name, and total amount in the kwitansi view')` (new)
  - `it('renders an img tag when yayasan logo is set')` (new)
- `tests/Feature/Keuangan/RiwayatControllerIndexTest.php`
  - `it('shows the total amount for each transaction row')` (new)
  - `it('filters by a valid full date range including the end-of-day boundary')` (new)
  - `it('narrows results with a dari-only filter')` (new)

## Test command output

Command:
```
php artisan test tests/Feature/Keuangan/RiwayatControllerIndexTest.php tests/Feature/Keuangan/KwitansiControllerTest.php tests/Feature/Admin/YayasanSettingControllerTest.php
```

Output:
```
   PASS  Tests\Feature\Keuangan\RiwayatControllerIndexTest
  ✓ it lists the active child payment history ordered newest first                                               8.67s
  ✓ it filters by metode                                                                                         0.16s
  ✓ it ignores an invalid date range instead of erroring                                                         0.15s
  ✓ it shows the total amount for each transaction row                                                           0.15s
  ✓ it filters by a valid full date range including the end-of-day boundary                                      0.15s
  ✓ it narrows results with a dari-only filter                                                                   0.17s
  ✓ it shows the kwitansi download link only for lunas rows                                                      0.14s
   PASS  Tests\Feature\Keuangan\KwitansiControllerTest
  ✓ it streams a pdf kwitansi for a lunas pembayaran                                                             0.24s
  ✓ it returns 404 for a pembayaran that is not yet lunas                                                        0.15s
  ✓ it blocks a parent from downloading another parent child's kwitansi                                          0.15s
  ✓ it renders without a logo when yayasan logo is not set                                                       0.14s
  ✓ it renders the kelas name, siswa name, and total amount in the kwitansi view                                 0.11s
  ✓ it renders an img tag when yayasan logo is set                                                               0.10s
   PASS  Tests\Feature\Admin\YayasanSettingControllerTest
  ✓ it shows the yayasan settings form with existing data                                                        0.09s
  ✓ it updates all yayasan fields                                                                                0.06s
  ✓ it uploads a new logo and deletes the old one                                                                0.07s
  ✓ it rejects a logo file that is too large                                                                     0.08s
  ✓ it returns 404 on update when no yayasan row exists                                                          0.06s
  ✓ it denies access to a user without yayasan.kelola permission                                                 0.05s
  Tests:    19 passed (40 assertions)
  Duration: 11.09s
```

## Commit

See `git log -1` on branch `demo` for the exact hash of the fix-wave commit
(message: `fix(keuangan): close kelas TenantScope gap, add total column, harden yayasan update, strengthen kwitansi/date-range test coverage`).

## Fix round 2 (re-review findings)

A re-review of fix round 1 (commit `6a79e6f`) found 3 remaining problems. All 3 fixed in commit `7f11048`.

### Issue 1 — Riwayat list's "Total" column showed "Rp0" for pending transactions — Fixed

`resources/views/keuangan/riwayat/index.blade.php`: the row-level `$totalAmount` computation summed
`$rincianItems->sum('amount_allocated')` unconditionally. Since `pembayaran_tagihan` rows are only
written at settlement time, any `menunggu_pembayaran`/`menunggu_verifikasi` row (VA/QRIS/transfer-manual
still pending) had an empty `pembayaranTagihan` collection and showed "Rp0" instead of the real pending
amount held in `Pembayaran.amount`.

Fix: mirrored the exact fallback pattern already used in `resources/views/pdf/kwitansi.blade.php`'s
total line:
```php
$totalAmount = $rincianItems->isNotEmpty() ? $rincianItems->sum('amount_allocated') : ($pembayaran->amount ?? 0);
```

New test: `tests/Feature/Keuangan/RiwayatControllerIndexTest.php` →
`it('shows the pending amount for a menunggu_pembayaran transaction with no rincian yet')` — creates a
`menunggu_pembayaran` `Pembayaran` with `amount => 250000` and no `PembayaranTagihan` rows, asserts
`250.000` appears in the response. Confirmed this test fails against the pre-fix template (would show
`0` instead) and passes after the one-line fix.

### Issue 2 — Kwitansi content tests bypassed the HTTP layer and never exercised the TenantScope bug — Fixed

`tests/Feature/Keuangan/KwitansiControllerTest.php`: the two tests added in fix round 1 called
`view('pdf.kwitansi', [...])->render()` directly, building their own `$pembayaran`/`$siswa` view
variables by hand. This meant `$this->actingAs()` was never invoked, so `TenantScope::apply()`
short-circuited on `auth()->id()` being null and never actually filtered anything — the
`withoutGlobalScope(TenantScope::class)` bypass this finding exists to protect was completely inert
during the test. It also bypassed `RiwayatController::kwitansi()` entirely, so a future regression in
the controller's `load()` call would not be caught.

Fix: rewrote both tests to go through the real HTTP route (`$this->actingAs($user)->get(route('keuangan.riwayat.kwitansi', $pembayaran))`),
matching the pattern of the file's other tests. Because dompdf's stream response is binary/FlateDecode-
compressed, a plain `assertSee()`/`toContain()` on the raw response body cannot see rendered text (the
PDF content streams are zlib-compressed, confirmed empirically — see red/green proof below). To still
assert on the actual rendered *content* (not just "no exception"), the "kelas name" test now extracts
and inflates each `stream...endstream` block in the PDF body with `gzuncompress()` and asserts the
decompressed PDF content-stream text contains `7A Istimewa` (the test's `Kelas` name). The "logo" test
keeps the plain `assertOk()` + non-empty-content-length sanity check, since it verifies a different
(non-kelas, non-crashing) concern.

**Red/green proof (temporary local-only change, not part of the committed diff):**

1. Temporarily reverted the bypass in `app/Http/Controllers/Keuangan/RiwayatController.php`,
   `kwitansi()`, from:
   `'siswa.kelas' => fn ($q) => $q->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)`
   to plain `'siswa.kelas'` (no bypass).
2. Ran `php artisan test tests/Feature/Keuangan/KwitansiControllerTest.php --filter="kelas name"`.
   **RED** — failed:
   ```
   Failed asserting that ... [Encoding detection failed] contains "7A Istimewa" [ASCII].
   ```
   Root cause confirmed via the decompressed PDF stream: with the bypass removed, the acting orang_tua
   user's `TenantScope` (which filters `Kelas` by `lembaga_id = $actingUser->lembaga_id`, and orang_tua
   users have `lembaga_id = null`) filtered out the real `Kelas` row entirely, so the PDF rendered
   `BT ... [(Kelas)] TJ ET` followed by `[(-)] TJ ET` instead of `7A Istimewa` — a silent wrong-data bug,
   not a crash (which is why a bare `assertOk()` alone, as originally drafted, would NOT have caught
   this — it had to be upgraded to inspect decompressed PDF content).
3. Restored the bypass to its original form.
4. Reran the same test. **GREEN** — passed, decompressed stream now contains `7A Istimewa`.
5. Reran the full `KwitansiControllerTest.php` file with the bypass restored — all 6 tests pass.

### Issue 3 — Malformed `dari`/`sampai` query values silently coerced instead of being validated — Fixed

`app/Http/Controllers/Keuangan/RiwayatController.php`, `index()`: `dari` and `sampai` were read via
raw `$request->query()` with no format validation, so `?dari=abc` reached the query as
`created_at >= 'abc 00:00:00'`, which MySQL coerces rather than rejects, silently producing a
wrong/empty result set.

Fix: added request validation at the top of `index()`:
```php
$validated = $request->validate([
    'dari' => ['nullable', 'date'],
    'sampai' => ['nullable', 'date'],
    'metode' => ['nullable', 'string'],
]);

$dari = $validated['dari'] ?? null;
$sampai = $validated['sampai'] ?? null;
$metode = $validated['metode'] ?? null;
```
A validation failure now redirects back with session errors (Laravel's default `$request->validate()`
behavior), rather than proceeding with a garbage value.

New test: `tests/Feature/Keuangan/RiwayatControllerIndexTest.php` →
`it('rejects a malformed dari filter with a validation error instead of a silent query')` — requests
`?dari=not-a-date`, asserts `assertSessionHasErrors('dari')`.

### Full covering test suite

Command:
```
php artisan test tests/Feature/Keuangan/RiwayatControllerIndexTest.php tests/Feature/Keuangan/KwitansiControllerTest.php
```

Output:
```
   PASS  Tests\Feature\Keuangan\RiwayatControllerIndexTest
  ✓ it lists the active child payment history ordered newest first                                              8.67s
  ✓ it filters by metode                                                                                        0.17s
  ✓ it ignores an invalid date range instead of erroring                                                        0.11s
  ✓ it shows the total amount for each transaction row                                                          0.11s
  ✓ it shows the pending amount for a menunggu_pembayaran transaction with no rincian yet                       0.07s
  ✓ it filters by a valid full date range including the end-of-day boundary                                     0.16s
  ✓ it narrows results with a dari-only filter                                                                  0.14s
  ✓ it rejects a malformed dari filter with a validation error instead of a silent query                        0.08s
  ✓ it shows the kwitansi download link only for lunas rows                                                     0.10s

   PASS  Tests\Feature\Keuangan\KwitansiControllerTest
  ✓ it streams a pdf kwitansi for a lunas pembayaran                                                            0.17s
  ✓ it returns 404 for a pembayaran that is not yet lunas                                                       0.08s
  ✓ it blocks a parent from downloading another parent child's kwitansi                                         0.14s
  ✓ it renders without a logo when yayasan logo is not set                                                      0.11s
  ✓ it renders the kelas name, siswa name, and total amount in the kwitansi view via the real controller route  0.15s
  ✓ it renders an img tag when yayasan logo is set, via the real controller route                               0.11s

  Tests:    15 passed (34 assertions)
  Duration: 10.53s
```

### Fix round 2 commit

`7f11048` — `fix(keuangan): fix pending-payment Rp0 display, strengthen kwitansi kelas-scope test coverage, validate riwayat date filters`
(parent: `6a79e6f`, the fix round 1 commit).
