# Tahap 3b — Pengaturan Akademik & Rentang Libur Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close two gaps found after Tahap 3 shipped, discovered by comparing the built admin CRUD against a real school's printed kalender akademik and a settings-page mockup: (1) `kalender_akademik` entries can only span a single date, but real holidays are multi-day ranges (e.g. "23 Agustus – 1 September: Libur Maulid"); (2) `Lembaga::$hari_libur_mingguan` (built in Tahap 3 Task 1) has no admin UI at all — it can currently only be edited via `tinker`. This plan adds date-range support to `kalender_akademik`, builds a UI for `hari_libur_mingguan`, and consolidates both into one new "Pengaturan Akademik" page, retiring the standalone Kalender Akademik list/create/edit pages built in Tahap 3 Task 4 in favor of a single-page inline table (mirroring the established `admin/jenis-tagihan` pattern: one Alpine.js component, JSON-responding controller actions, no separate create/edit routes).

**Architecture:** Five slices in dependency order: (1) `tanggal_selesai` column + model/factory (data layer), (2) `KalenderAkademikResolver` becomes range-aware (service layer, return contract unchanged), (3) `KalenderAkademikController` rewritten to a JSON-first inline-CRUD controller with range validation, overlap-checked duplicates, and a `destroy()` action that did not exist before, (4) a new `pengaturan-akademik.kelola` permission + `PengaturanAkademikController` for saving `hari_libur_mingguan`, (5) the consolidated "Pengaturan Akademik" page (Blade + new Alpine component) that replaces the sidebar's "Kalender Akademik" entry.

**Tech Stack:** Laravel 12, Blade, Alpine.js, Pest 4.

**Explicitly out of scope (per user decision 2026-07-25):**
- Yayasan-level default for `hari_libur_mingguan` — stays per-lembaga only, no inheritance tier. If this is wanted later, it is a separate plan.
- "Berulang Tiap Tahun" (annual recurrence without a fixed year) for `kalender_akademik` entries — dropped entirely, not built even as a disabled UI element.
- Merging `admin/tahun-ajaran` into this page — confirmed with user: Tahun Ajaran stays its own standalone menu/page. The tab strip in Task 5 only links out to it.
- **Non-libur event categories** (ASTS, ASAS, MPLS, "Awal Masuk Semester", "Tanggal Rapor" — seen in the real printed kalender akademik reference image that motivated this plan) — confirmed with user: Libur/Kerja binary `tipe` is enough for now. **Noted for future development**: a later plan would add a `kategori`/`tag` field (with an associated color for a legend) to `kalender_akademik` so non-holiday school events can be recorded alongside holidays, plus a printable full-year grid report. Do not build any part of this in Tahap 3b — this note exists only so a future session doesn't have to rediscover the requirement from scratch.

## Global Constraints

- Same conventions as Tahap 1–3: `casts()` method style, inline validation (no FormRequest classes), `AuthorizesRequests`, existing TailAdmin Blade token set (`rounded-2xl border-gray-200 shadow-card`, `<x-badge>`, `<x-input-label>`/`<x-text-input>`, etc).
- **Reference implementation to read before writing any code in Tasks 3 and 5:** `app/Http/Controllers/Admin/JenisTagihanController.php` + `resources/views/admin/jenis-tagihan/index.blade.php` + `resources/js/jenis-tagihan-table.js`. This is the established pattern in this codebase for "inline table with add/edit form on one page, JSON-responding controller, no separate create/edit routes" — mirror its shape (dual `RedirectResponse|JsonResponse` return types keyed on `$request->wantsJson()`, `Alpine.data('...', ...)` registration in `resources/js/app.js`, `Alpine.store('toast')` for success/error messages, `confirmDialog()` before delete) rather than inventing a new convention.
- `kalender_akademik.tanggal` remains the range's **start** date (no rename — minimizes churn on the already-shipped, tested column). The new `tanggal_selesai` column is nullable at the DB level for backward compatibility with rows created before this migration (which only ever set `tanggal`), but the controller **always** writes a concrete value going forward (`tanggal_selesai ?? tanggal`) — new/updated rows are never null. Every read path (resolver, index listing, overlap validation) must treat a null `tanggal_selesai` as if it equalled `tanggal` (single-day entry), via `COALESCE` at the query level or `?? $entri->tanggal` at the model level — do not assume `tanggal_selesai` is always populated.
- The `KalenderAkademikResolver::resolve()` **return contract does not change**: still exactly `['libur' => bool, 'alasan' => string]`. Tahap 5 (Presensi & Jurnal) depends on this exact shape — do not add, remove, or rename keys.
- The cross-tenant ownership guard added in Tahap 3's final review (a lembaga-scoped user must not be able to view/edit/delete another lembaga's non-national entry — `abort(404)` when `lembaga_id !== null && lembaga_id !== $actingLembagaId`) **must be preserved and ported** to every mutating action on `KalenderAkademikController`, including the new `destroy()`. This is a proven security fix from the prior review, not optional cleanup — losing it while refactoring to JSON responses would silently reintroduce the IDOR.
- `KalenderAkademikController@index`, `@create`, `@edit` are **removed** in this plan — the new `PengaturanAkademikController@index` renders the whole page (Hari Aktif card + Hari Libur Akademik card) in one view. `store`/`update`/`destroy` remain on `KalenderAkademikController`.
- The "Pengaturan Akademik" page's `hari_libur_mingguan` editor is **not** tahun-ajaran-scoped (per the out-of-scope note above, it is a flat per-lembaga setting) — the tahun ajaran context on the page (if shown) only filters which `kalender_akademik` entries are listed, it does not affect how `hari_libur_mingguan` is read or saved.
- New permission `pengaturan-akademik.kelola` gates saving `hari_libur_mingguan`, separate from `kalender-akademik.kelola`/`kelola-nasional` (which continue to gate the libur-entries card) — these are different settings and should be independently assignable via RBAC, consistent with the two-tier-permission precedent already established in this project.
- Do not build an "Umum" settings tab — no such feature exists yet in this codebase and inventing an empty placeholder would be a half-finished implementation. The page header may show "Akademik" as the only functional tab; a "Tahun Ajaran" tab (if included) must link to the existing `admin.tahun-ajaran.index` page, not a new stub.

---

### Task 1: `tanggal_selesai` on `kalender_akademik`

**Files:**
- Create: `database/migrations/2026_07_26_100000_add_tanggal_selesai_to_kalender_akademik_table.php`
- Modify: `app/Models/KalenderAkademik.php`, `database/factories/KalenderAkademikFactory.php`
- Test: `tests/Unit/Models/KalenderAkademikTest.php` (extend existing file — do not create a second test file for the same model)

**Interfaces:**
- Consumes: existing `kalender_akademik` table (has `id`, `lembaga_id`, `tanggal`, `nama`, `tipe`, `keterangan`).
- Produces: `KalenderAkademik::$tanggal_selesai` (nullable `date` cast). Task 2's resolver and Task 3's controller both read this column.

- [ ] **Step 1: Write the failing test**

Add to `tests/Unit/Models/KalenderAkademikTest.php`:

```php
it('allows tanggal_selesai to be null (single-day entry, backward compatible)', function () {
    $entri = KalenderAkademik::factory()->create(['tanggal' => '2026-08-17', 'tanggal_selesai' => null]);

    expect($entri->fresh()->tanggal_selesai)->toBeNull();
});

it('casts tanggal_selesai to a date when a range is stored', function () {
    $entri = KalenderAkademik::factory()->create(['tanggal' => '2026-08-23', 'tanggal_selesai' => '2026-09-01']);

    expect($entri->fresh()->tanggal_selesai->toDateString())->toBe('2026-09-01');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Models/KalenderAkademikTest.php`
Expected: FAIL — `tanggal_selesai` column does not exist.

- [ ] **Step 3: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kalender_akademik', function (Blueprint $table) {
            $table->date('tanggal_selesai')->nullable()->after('tanggal');
        });
    }

    public function down(): void
    {
        Schema::table('kalender_akademik', function (Blueprint $table) {
            $table->dropColumn('tanggal_selesai');
        });
    }
};
```

Run: `php artisan migrate`
Expected: column added without error. (This table may already have rows from Tahap 3 use on the real dev DB — they will have `tanggal_selesai = NULL`, which is intentional; no backfill needed since every read path treats `NULL` as "same as `tanggal`".)

- [ ] **Step 4: Add to model and factory**

In `app/Models/KalenderAkademik.php`, add `'tanggal_selesai'` to `$fillable` and to the `casts()` array (`'date'`).

In `database/factories/KalenderAkademikFactory.php`, add `'tanggal_selesai' => null` to `definition()` (keeps existing factory usages — which construct single-day entries — unchanged), and add a factory state:

```php
public function rentang(string $mulai, string $selesai): static
{
    return $this->state(fn () => ['tanggal' => $mulai, 'tanggal_selesai' => $selesai]);
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Unit/Models/KalenderAkademikTest.php`
Expected: PASS (all tests in the file, including the 2 new ones).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_26_100000_add_tanggal_selesai_to_kalender_akademik_table.php app/Models/KalenderAkademik.php database/factories/KalenderAkademikFactory.php tests/Unit/Models/KalenderAkademikTest.php
git commit -m "feat: add tanggal_selesai to kalender_akademik for date ranges"
```

---

### Task 2: `KalenderAkademikResolver` — range-aware resolution

**Files:**
- Modify: `app/Services/KalenderAkademikResolver.php`
- Test: `tests/Unit/Services/KalenderAkademikResolverTest.php` (extend existing file)

**Interfaces:**
- Consumes: `KalenderAkademik::$tanggal`/`$tanggal_selesai` (Task 1).
- Produces: `KalenderAkademikResolver::resolve()` — **same return shape as before**, `['libur' => bool, 'alasan' => string]`. Callers (Tahap 5) are unaffected by this change.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Unit/Services/KalenderAkademikResolverTest.php`:

```php
it('resolves a date in the middle of a multi-day lembaga range as libur', function () {
    $lembaga = Lembaga::factory()->create();
    KalenderAkademik::factory()->create([
        'lembaga_id' => $lembaga->id,
        'tanggal' => '2026-08-23',
        'tanggal_selesai' => '2026-09-01',
        'nama' => 'Libur Maulid',
        'tipe' => TipeKalenderAkademik::Libur,
    ]);

    $hasil = app(KalenderAkademikResolver::class)->resolve($lembaga, Carbon::parse('2026-08-27'));

    expect($hasil)->toBe(['libur' => true, 'alasan' => 'Libur Maulid']);
});

it('resolves the last day of a range (inclusive boundary) as still libur', function () {
    $lembaga = Lembaga::factory()->create();
    KalenderAkademik::factory()->create([
        'lembaga_id' => $lembaga->id,
        'tanggal' => '2026-08-23',
        'tanggal_selesai' => '2026-09-01',
        'tipe' => TipeKalenderAkademik::Libur,
    ]);

    $hasil = app(KalenderAkademikResolver::class)->resolve($lembaga, Carbon::parse('2026-09-01'));

    expect($hasil['libur'])->toBeTrue();
});

it('resolves the day after a range ends as a normal effective day', function () {
    $lembaga = Lembaga::factory()->create(['hari_libur_mingguan' => [0]]);
    KalenderAkademik::factory()->create([
        'lembaga_id' => $lembaga->id,
        'tanggal' => '2026-08-23',
        'tanggal_selesai' => '2026-09-01',
        'tipe' => TipeKalenderAkademik::Libur,
    ]);

    $hasil = app(KalenderAkademikResolver::class)->resolve($lembaga, Carbon::parse('2026-09-02'));

    expect($hasil)->toBe(['libur' => false, 'alasan' => 'Hari efektif belajar']);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/Services/KalenderAkademikResolverTest.php`
Expected: FAIL — current query does `whereDate('tanggal', $tanggal)`, an exact-date match, so a date inside a range but not equal to the start date resolves as a normal effective day instead of libur.

- [ ] **Step 3: Update the resolver**

```php
<?php

namespace App\Services;

use App\Enums\TipeKalenderAkademik;
use App\Models\KalenderAkademik;
use App\Models\Lembaga;
use Carbon\CarbonInterface;

class KalenderAkademikResolver
{
    /**
     * @return array{libur: bool, alasan: string}
     */
    public function resolve(Lembaga $lembaga, CarbonInterface $tanggal): array
    {
        $entriLembaga = KalenderAkademik::untukLembaga($lembaga->id)
            ->where(fn ($q) => $this->cocokRentang($q, $tanggal))
            ->first();

        if ($entriLembaga) {
            return [
                'libur' => $entriLembaga->tipe === TipeKalenderAkademik::Libur,
                'alasan' => $entriLembaga->nama,
            ];
        }

        $entriNasional = KalenderAkademik::nasional()
            ->where(fn ($q) => $this->cocokRentang($q, $tanggal))
            ->first();

        if ($entriNasional) {
            return [
                'libur' => $entriNasional->tipe === TipeKalenderAkademik::Libur,
                'alasan' => $entriNasional->nama,
            ];
        }

        if (in_array($tanggal->dayOfWeek, $lembaga->hari_libur_mingguan ?? [], true)) {
            return ['libur' => true, 'alasan' => 'Libur mingguan'];
        }

        return ['libur' => false, 'alasan' => 'Hari efektif belajar'];
    }

    private function cocokRentang($query, CarbonInterface $tanggal)
    {
        $tgl = $tanggal->toDateString();

        return $query->whereDate('tanggal', '<=', $tgl)
            ->where(fn ($q) => $q->whereNull('tanggal_selesai')->orWhereDate('tanggal_selesai', '>=', $tgl))
            ->where(fn ($q) => $q->whereDate('tanggal', $tgl)->orWhereDate('tanggal_selesai', '>=', $tgl));
    }
}
```

Note on the last `where` clause in `cocokRentang`: it exists to reject a null-`tanggal_selesai` row whose single `tanggal` is before `$tgl` (e.g. a single-day entry from 2026-08-01 must not match 2026-08-15 just because `tanggal <= $tgl`). Simplify only if you can prove the simpler form still rejects that case — add a regression test for it if you do.

Actually — prefer the simplest correct form. Before finalizing, write one more test: a single-day (`tanggal_selesai = null`) entry on 2026-08-01 must NOT match a resolve() call for 2026-08-15. Use that test to settle the exact WHERE clause; do not ship the clause above without it passing.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Unit/Services/KalenderAkademikResolverTest.php`
Expected: PASS (all tests, including the pre-existing ones from Tahap 3 — re-run the full file, not just the new tests, to confirm no regression on the single-day cases).

- [ ] **Step 5: Commit**

```bash
git add app/Services/KalenderAkademikResolver.php tests/Unit/Services/KalenderAkademikResolverTest.php
git commit -m "feat: make KalenderAkademikResolver range-aware"
```

---

### Task 3: `KalenderAkademikController` — JSON inline-CRUD with ranges

**Files:**
- Modify: `app/Http/Controllers/Admin/KalenderAkademikController.php`
- Modify: `routes/admin.php`
- Delete: `resources/views/admin/kalender-akademik/create.blade.php`, `resources/views/admin/kalender-akademik/edit.blade.php`, `resources/views/admin/kalender-akademik/index.blade.php` (Task 5 replaces `index.blade.php`'s role with the new Pengaturan Akademik page)
- Test: rewrite `tests/Feature/Admin/KalenderAkademikCrudTest.php`

**Interfaces:**
- Consumes: `KalenderAkademik` model (Task 1).
- Produces: JSON-responding `store`/`update`/`destroy` for the Alpine component built in Task 5. `PengaturanAkademikController` (Task 4) calls `KalenderAkademik::query()` directly for the initial listing — it does not call this controller's (now-removed) `index()`.

- [ ] **Step 1: Read the reference implementation**

Read `app/Http/Controllers/Admin/JenisTagihanController.php` in full before writing this task — mirror its `store`/`update`/`destroy` shape (validate → mutate → `if ($request->wantsJson())` branch → else redirect).

- [ ] **Step 2: Write the failing tests**

Rewrite `tests/Feature/Admin/KalenderAkademikCrudTest.php`. Keep every existing scenario (permission checks for `kelola`/`kelola-nasional`, the cross-tenant IDOR guard on edit/update from Tahap 3's final review, the yayasan-no-active-lembaga guard from Tahap 3 Task 4's fix) but adapt each to the JSON API — `edit()` no longer exists, so port its coverage onto `update()`/`destroy()` (a cross-tenant `PUT`/`DELETE` must still 404). Add new cases:

```php
it('creates a range entry and stores tanggal_selesai', function () {
    // ... acting as a lembaga-scoped manager with kalender-akademik.kelola
    $response = $this->postJson(route('admin.kalender-akademik.store'), [
        'tanggal' => '2026-08-23',
        'tanggal_selesai' => '2026-09-01',
        'nama' => 'Libur Maulid',
        'tipe' => 'libur',
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('kalender_akademik', ['nama' => 'Libur Maulid', 'tanggal_selesai' => '2026-09-01']);
});

it('defaults tanggal_selesai to tanggal when omitted (single-day entry)', function () {
    // ... POST without tanggal_selesai
    // assert stored row has tanggal_selesai === tanggal
});

it('rejects a tanggal_selesai before tanggal', function () {
    // ... POST tanggal=2026-09-01, tanggal_selesai=2026-08-23 -> 422
});

it('rejects a new range that overlaps an existing entry in the same scope', function () {
    // existing: lembaga entry 2026-08-23..2026-09-01
    // new: lembaga entry 2026-08-30..2026-09-05 (overlaps) -> 422
});

it('allows an overlapping range in a DIFFERENT scope (own-lembaga vs national)', function () {
    // existing: national entry 2026-08-23..2026-09-01
    // new: own-lembaga entry same dates -> allowed (different scope, lembaga override wins per resolver)
});

it('deletes an entry the acting lembaga-scoped user owns', function () { /* ... */ });

it('rejects deleting another lembaga's non-national entry with 404', function () { /* ... */ });

it('rejects deleting a national entry without kalender-akademik.kelola-nasional', function () { /* ... */ });
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/KalenderAkademikCrudTest.php`
Expected: FAIL — routes/controller don't exist yet in this shape.

- [ ] **Step 4: Rewrite the controller**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\KalenderAkademik;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class KalenderAkademikController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('kalender-akademik.kelola');

        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal'],
            'nama' => ['required', 'string', 'max:255'],
            'tipe' => ['required', 'in:libur,kerja'],
            'keterangan' => ['nullable', 'string'],
            'berlaku_nasional' => ['nullable', 'boolean'],
        ]);

        $nasional = $request->boolean('berlaku_nasional');

        if ($nasional) {
            $this->authorize('kalender-akademik.kelola-nasional');
        }

        if (! $nasional && $request->user()->widestScopeLevel() === 'yayasan' && session('active_lembaga_id') === null) {
            return $this->errorResponse($request, 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah entri kalender.', 'lembaga_id');
        }

        $lembagaId = $nasional ? null : ($request->user()->lembaga_id ?? session('active_lembaga_id'));
        $tanggalSelesai = $data['tanggal_selesai'] ?? $data['tanggal'];

        if ($this->tumpangTindih($lembagaId, $data['tanggal'], $tanggalSelesai)) {
            return $this->errorResponse($request, 'Rentang tanggal ini tumpang tindih dengan entri lain pada cakupan yang sama.', 'tanggal');
        }

        $entri = KalenderAkademik::create([
            'lembaga_id' => $lembagaId,
            'tanggal' => $data['tanggal'],
            'tanggal_selesai' => $tanggalSelesai,
            'nama' => $data['nama'],
            'tipe' => $data['tipe'],
            'keterangan' => $data['keterangan'] ?? null,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['data' => $entri->fresh()], 201);
        }

        return redirect()->route('admin.pengaturan.akademik.index')->with('status', 'Entri kalender berhasil disimpan.');
    }

    public function update(Request $request, KalenderAkademik $kalenderAkademik): RedirectResponse|JsonResponse
    {
        $this->authorize('kalender-akademik.kelola');

        $lembagaId = $request->user()->lembaga_id ?? session('active_lembaga_id');
        if ($kalenderAkademik->lembaga_id !== null && $kalenderAkademik->lembaga_id !== $lembagaId) {
            abort(404);
        }

        if ($kalenderAkademik->lembaga_id === null) {
            $this->authorize('kalender-akademik.kelola-nasional');
        }

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'tipe' => ['required', 'in:libur,kerja'],
            'keterangan' => ['nullable', 'string'],
        ]);

        $kalenderAkademik->update($data);

        if ($request->wantsJson()) {
            return response()->json(['data' => $kalenderAkademik->fresh()]);
        }

        return redirect()->route('admin.pengaturan.akademik.index')->with('status', 'Entri kalender berhasil diperbarui.');
    }

    public function destroy(Request $request, KalenderAkademik $kalenderAkademik): RedirectResponse|JsonResponse
    {
        $this->authorize('kalender-akademik.kelola');

        $lembagaId = $request->user()->lembaga_id ?? session('active_lembaga_id');
        if ($kalenderAkademik->lembaga_id !== null && $kalenderAkademik->lembaga_id !== $lembagaId) {
            abort(404);
        }

        if ($kalenderAkademik->lembaga_id === null) {
            $this->authorize('kalender-akademik.kelola-nasional');
        }

        $kalenderAkademik->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Entri kalender berhasil dihapus.']);
        }

        return redirect()->route('admin.pengaturan.akademik.index')->with('status', 'Entri kalender berhasil dihapus.');
    }

    private function tumpangTindih(?int $lembagaId, string $mulai, string $selesai, ?int $kecualiId = null): bool
    {
        return KalenderAkademik::where(fn ($q) => $lembagaId === null ? $q->whereNull('lembaga_id') : $q->where('lembaga_id', $lembagaId))
            ->when($kecualiId, fn ($q) => $q->where('id', '!=', $kecualiId))
            ->where('tanggal', '<=', $selesai)
            ->where(fn ($q) => $q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $mulai))
            ->exists();
    }

    private function errorResponse(Request $request, string $message, string $field): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'errors' => [$field => [$message]]], 422);
        }

        return back()->withErrors([$field => $message])->withInput();
    }
}
```

Double-check `tumpangTindih`'s WHERE clause against a single-day row (`tanggal_selesai = null`) the same way Task 2 double-checked the resolver — write the overlap test in Step 2 before trusting this exact clause.

- [ ] **Step 5: Update routes**

In `routes/admin.php`, replace the existing `Route::resource('kalender-akademik', ...)` line with:

```php
Route::post('kalender-akademik', [KalenderAkademikController::class, 'store'])->name('kalender-akademik.store');
Route::put('kalender-akademik/{kalenderAkademik}', [KalenderAkademikController::class, 'update'])->name('kalender-akademik.update');
Route::delete('kalender-akademik/{kalenderAkademik}', [KalenderAkademikController::class, 'destroy'])->name('kalender-akademik.destroy');
```

Do not add `pengaturan.akademik.*` routes here — Task 4 adds those.

- [ ] **Step 6: Delete the retired views**

```bash
git rm resources/views/admin/kalender-akademik/create.blade.php resources/views/admin/kalender-akademik/edit.blade.php resources/views/admin/kalender-akademik/index.blade.php resources/views/admin/kalender-akademik/_form.blade.php
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/KalenderAkademikCrudTest.php`
Expected: FAIL at this point is acceptable only for tests that need the Task 5 page to exist (none should — this file tests the controller/routes directly via `postJson`/`putJson`/`deleteJson`, not the page). All tests in this file should PASS after this task.

Run: `php artisan test`
Expected: some failures are OK here — Task 5 will still be routing `admin.kalender-akademik.index`/`.create`/`.edit` references in the sidebar and elsewhere to a 404 until it lands. Confirm the ONLY failures are route-not-found errors traceable to the sidebar/removed views, not anything else. Note them; Task 5 fixes them.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/KalenderAkademikController.php routes/admin.php tests/Feature/Admin/KalenderAkademikCrudTest.php
git rm resources/views/admin/kalender-akademik/create.blade.php resources/views/admin/kalender-akademik/edit.blade.php resources/views/admin/kalender-akademik/index.blade.php resources/views/admin/kalender-akademik/_form.blade.php
git commit -m "refactor: KalenderAkademikController becomes JSON inline-CRUD with range overlap validation"
```

---

### Task 4: `pengaturan-akademik.kelola` permission + `PengaturanAkademikController`

**Files:**
- Create: `app/Http/Controllers/Admin/PengaturanAkademikController.php`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/PengaturanAkademikControllerTest.php`

**Interfaces:**
- Consumes: `Lembaga::$hari_libur_mingguan` (Tahap 3 Task 1), `KalenderAkademik` (Task 1/3).
- Produces: `admin.pengaturan.akademik.index` (GET, view) and `admin.pengaturan.akademik.hari-aktif` (PUT, JSON) routes that Task 5's page uses.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Admin/PengaturanAkademikControllerTest.php`:

```php
it('denies access to a user without kalender-akademik.view', function () {
    // acting as a user without the permission -> GET index -> 403
});

it('shows the acting lembaga-scoped user's own hari_libur_mingguan and kalender entries', function () {
    // GET index -> assertOk, assert view has 'lembaga' and 'entriList' scoped correctly
});

it('denies saving hari_libur_mingguan without pengaturan-akademik.kelola', function () {
    // PUT hari-aktif without the permission -> 403
});

it('saves a new hari_libur_mingguan for the acting lembaga-scoped user's own lembaga', function () {
    // PUT hari-aktif with hari_aktif=[1,2,3,4,6] (Jumat off) -> assertOk
    // assert lembaga->fresh()->hari_libur_mingguan === [0, 5]  (inverse: Sunday + Friday off)
});

it('does not let a yayasan-scoped user without an active lembaga save hari_libur_mingguan', function () {
    // yayasan-scoped, session active_lembaga_id null -> PUT hari-aktif -> 422
});
```

Decide the payload shape before writing these: the UI sends which days are ACTIVE (checked boxes), the controller must invert that to `hari_libur_mingguan` (the OFF days) since that's what `Lembaga` stores and what the resolver reads. Validate `hari_aktif` as `['required','array'], 'hari_aktif.*' => ['integer','between:0,6']`.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/PengaturanAkademikControllerTest.php`
Expected: FAIL — controller/routes don't exist.

- [ ] **Step 3: Create the controller**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\KalenderAkademik;
use App\Models\Lembaga;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class PengaturanAkademikController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('kalender-akademik.view');

        $lembagaId = $request->user()->lembaga_id ?? session('active_lembaga_id');
        $lembaga = Lembaga::findOrFail($lembagaId);

        return view('admin.pengaturan.akademik', [
            'lembaga' => $lembaga,
            'entriList' => KalenderAkademik::where(fn ($q) => $q->whereNull('lembaga_id')->orWhere('lembaga_id', $lembagaId))
                ->orderBy('tanggal')
                ->get(),
            'bolehNasional' => $request->user()->can('kalender-akademik.kelola-nasional'),
        ]);
    }

    public function updateHariAktif(Request $request): JsonResponse
    {
        $this->authorize('pengaturan-akademik.kelola');

        if ($request->user()->widestScopeLevel() === 'yayasan' && session('active_lembaga_id') === null) {
            return response()->json([
                'message' => 'Pilih lembaga aktif melalui pengalih lembaga terlebih dahulu.',
                'errors' => ['lembaga_id' => ['Pilih lembaga aktif melalui pengalih lembaga terlebih dahulu.']],
            ], 422);
        }

        $data = $request->validate([
            'hari_aktif' => ['present', 'array'],
            'hari_aktif.*' => ['integer', 'between:0,6'],
        ]);

        $lembagaId = $request->user()->lembaga_id ?? session('active_lembaga_id');
        $lembaga = Lembaga::findOrFail($lembagaId);

        $hariLibur = array_values(array_diff(range(0, 6), $data['hari_aktif']));
        $lembaga->update(['hari_libur_mingguan' => $hariLibur]);

        return response()->json(['data' => ['hari_libur_mingguan' => $lembaga->fresh()->hari_libur_mingguan]]);
    }
}
```

Before finalizing, confirm `$lembaga->widestScopeLevel()` — this method lives on `User` in this codebase per Tahap 1–3 usage (`$request->user()->widestScopeLevel()`), not on `Lembaga`. Read the existing usages in `KalenderAkademikController`/`MataPelajaranController` to confirm the exact call site before writing this method — do not guess the API.

- [ ] **Step 4: Add routes**

```php
Route::get('pengaturan/akademik', [PengaturanAkademikController::class, 'index'])->name('pengaturan.akademik.index');
Route::put('pengaturan/akademik/hari-aktif', [PengaturanAkademikController::class, 'updateHariAktif'])->name('pengaturan.akademik.hari-aktif');
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/PengaturanAkademikControllerTest.php`
Expected: PASS.

- [ ] **Step 6: Verify `permissions:sync` picks up the new permission**

Run: `php artisan permissions:sync`
Expected: `pengaturan-akademik.kelola` appears in the "added" list (it's referenced via `$this->authorize('pengaturan-akademik.kelola')` in Step 3's code, so the existing regex-based scanner from Tahap 1 should find it automatically — confirm rather than assume).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/PengaturanAkademikController.php routes/admin.php tests/Feature/Admin/PengaturanAkademikControllerTest.php
git commit -m "feat: add PengaturanAkademikController for hari_libur_mingguan"
```

---

### Task 5: "Pengaturan Akademik" page (view, Alpine component, sidebar)

**Files:**
- Create: `resources/views/admin/pengaturan/akademik.blade.php`, `resources/js/pengaturan-akademik.js`
- Modify: `resources/js/app.js`, `resources/views/layouts/sidebar.blade.php`
- Test: browser/manual verification per the "Report" section below (this task is view+JS wiring; the controllers it calls are already covered by Task 3/4's feature tests) — if this environment has a working HTTP test client for view rendering, add one smoke test asserting the page renders 200 with both cards present.

**Interfaces:**
- Consumes: `admin.pengaturan.akademik.index`/`.hari-aktif` (Task 4), `admin.kalender-akademik.store`/`.update`/`.destroy` (Task 3).
- Produces: the page real users interact with. Nothing downstream depends on this task's internals.

- [ ] **Step 1: Read the reference implementation again**

Re-read `resources/views/admin/jenis-tagihan/index.blade.php` and `resources/js/jenis-tagihan-table.js` side by side before writing this task's Blade/JS — this task's "Hari Libur Akademik" card is structurally the same component (inline add/edit form + table + Alpine-driven fetch calls), just with different fields.

- [ ] **Step 2: Build the Alpine component**

Create `resources/js/pengaturan-akademik.js` exporting `kalenderAkademikTable(config)`, following `jenis-tagihan-table.js`'s shape exactly:
- `items` = initial `entriList` (JSON from the controller).
- `form` fields: `tanggal`, `tanggal_selesai`, `nama`, `tipe` (default `'libur'`), `keterangan`, `berlaku_nasional` (only relevant if `config.bolehNasional`).
- `submit()`: POST to `storeUrl` when `editingId === null`, PUT to `updateUrlTemplate` otherwise — **but note `update()` (Task 3) does not accept `tanggal`/`tanggal_selesai`/`berlaku_nasional`** (scope and dates are immutable on edit, matching the existing Tahap 3 convention already in place — "cakupan tidak dapat diubah"). The edit form must only send `nama`/`tipe`/`keterangan`; hide the date/scope inputs when `editingId !== null` (an `x-show="editingId === null"` on those fields is sufficient — do not send them in the PUT body either way, since the controller ignores extra fields).
- `deleteItem(item)`: `confirmDialog(...)` then `DELETE` to a `deleteUrlTemplate`.
- Format `tanggal`/`tanggal_selesai` for display as a single date when they're equal, or `"{tanggal} – {tanggal_selesai}"` when they differ.

- [ ] **Step 3: Register the component**

In `resources/js/app.js`, add:
```js
import { kalenderAkademikTable } from './pengaturan-akademik';
// ...
Alpine.data('kalenderAkademikTable', kalenderAkademikTable);
```

- [ ] **Step 4: Build the Blade view**

Create `resources/views/admin/pengaturan/akademik.blade.php` using the TailAdmin token set (same as `admin/jenis-tagihan/index.blade.php`'s outer structure: breadcrumb `<h1>`+`<p>` header, no `<x-slot name="header">`).

Structure:
1. A lightweight tab strip at the top: "Tahun Ajaran" (a plain `<a>` linking to `route('admin.tahun-ajaran.index')`, not a tab that renders content here) and "Akademik" (the active/current tab, non-interactive). Do not build an "Umum" tab (see Global Constraints).
2. Two side-by-side cards (`grid grid-cols-1 lg:grid-cols-2 gap-4` or similar, matching the mockup's 2-column layout):
   - **"Hari Aktif Sekolah"**: a plain (non-Alpine, or minimal Alpine) form with 7 checkboxes Senin–Minggu, pre-checked based on `$lembaga->hari_libur_mingguan` (checked = NOT in that array), submitting via `fetch` PUT to `route('admin.pengaturan.akademik.hari-aktif')` with a `hari_aktif` array of the checked day-of-week integers (0=Minggu..6=Sabtu, matching the existing `hari_libur_mingguan` convention from Tahap 3 — Minggu is index 0, not Senin). Gate the save button behind `@can('pengaturan-akademik.kelola')`.
   - **"Hari Libur Akademik"**: the `x-data="kalenderAkademikTable({...})"` card — inline form (Nama, Tanggal Mulai, Tanggal Selesai, Tipe select, and a `berlaku_nasional` checkbox shown only `x-show` when `bolehNasional` is true) + table (Nama/Tanggal/Tipe/Cakupan/Aksi columns, mirroring the deleted `index.blade.php`'s badge styling for Tipe/Cakupan). Gate the add-form behind `@can('kalender-akademik.kelola')`.

- [ ] **Step 5: Update the sidebar**

In `resources/views/layouts/sidebar.blade.php`, change the existing "III. Akademik" group entry:
```php
Auth::user()->can('kalender-akademik.view') ? ['route' => 'admin.pengaturan.akademik.index', 'pattern' => 'admin.pengaturan.akademik.*', 'label' => 'Pengaturan Akademik', 'icon' => 'schedule'] : null,
```
(was `'route' => 'admin.kalender-akademik.index', 'pattern' => 'admin.kalender-akademik.*'`). Confirm no other file references the old route names (`admin.kalender-akademik.index`/`.create`/`.edit`) before finishing — `grep -rn "kalender-akademik.index\|kalender-akademik.create\|kalender-akademik.edit" resources/ app/` should return nothing.

- [ ] **Step 6: Build assets and run the full suite**

```bash
npm run build
php artisan test
```
Expected: full suite green, including the route-not-found failures noted at the end of Task 3 (now fixed since the sidebar and any other references point to the new route).

- [ ] **Step 7: Manual verification**

Since this environment has no Playwright/chromium-cli set up (per Tahap 3's precedent), verify via authenticated `curl` (CSRF token + cookie jar, same approach as Tahap 3's design-system redesign): log in, `GET admin/pengaturan/akademik` → 200, confirm both card headings ("Hari Aktif Sekolah", "Hari Libur Akademik") appear in the response body. If Playwright becomes available in a future session, prefer a real screenshot instead.

- [ ] **Step 8: Commit**

```bash
git add resources/views/admin/pengaturan/akademik.blade.php resources/js/pengaturan-akademik.js resources/js/app.js resources/views/layouts/sidebar.blade.php
git commit -m "feat: add consolidated Pengaturan Akademik page"
```

---

## Plan Self-Review Notes

- **Judgment call — no "Umum" settings tab.** The original mockup showed 3 tabs (Umum/Tahun Ajaran/Akademik); this plan only builds "Akademik" as functional and links "Tahun Ajaran" to the pre-existing page. "Umum" is omitted entirely rather than stubbed, per YAGNI — there is no existing "general lembaga settings" feature for it to represent. If the user wants a real Umum tab, that's a separate plan.
- **Judgment call — `berlaku_nasional` kept in the inline form even though the mockup didn't show it.** Tahap 3 shipped national-entry creation (`kalender-akademik.kelola-nasional`) as a real, tested capability; dropping it while consolidating to this new page would be a silent regression. It's included as a conditionally-shown checkbox.
- **Judgment call — inline-CRUD (Alpine + JSON) instead of the classic create/edit pages Tahap 3 built.** This matches the mockup's actual depicted UX (inline add-form on the same page as the table) and this codebase's own established pattern for that UX (`admin/jenis-tagihan`), rather than retrofitting the old multi-page flow to show a range picker.
- **Watch item for the Task 2 and Task 3 implementers:** the exact SQL WHERE clause for "does date X fall in range [tanggal, tanggal_selesai ?? tanggal]" and "do ranges [a,b] and [c,d] overlap" are easy to get subtly wrong at the NULL/inclusive-boundary edges. Both tasks explicitly require a dedicated test for the null-`tanggal_selesai` edge case before trusting the clause — do not skip those tests even under time pressure.
- **Not re-verified against a live MySQL instance in this plan-writing pass** — Tahap 3 hit a real MySQL 8.0.30 JSON-default gotcha that wasn't visible from reasoning alone. This plan's new column (`tanggal_selesai`, plain nullable `date`) is much lower-risk than that JSON column, but the implementer should still run the migration against the real dev DB early (Task 1 Step 3) rather than assuming it will apply cleanly.
