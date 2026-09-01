# Jenis Tagihan — Konsolidasi Sasaran/Tarif/Keringanan + Engine Recalculate Tagihan Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Consolidate Target Sasaran, Tarif Berdimensi, and Keringanan management into the Jenis Tagihan form itself (no more visiting the Siswa edit page to assign discounts), fix 3 real technical bugs in the matching/validation logic, and add a generic, guard-protected engine that recalculates `net_amount` on unpaid Tagihan when the underlying Keringanan/Tarif configuration changes after the bill was created.

**Architecture:** A shared `TagihanStatusResolver` service eliminates duplicated status-transition logic between the existing `PaymentAllocationService` and the new `RecalculateTagihanNominalAction`. The recalc action re-runs `TagihanNominalResolver`'s full resolution (nominal + discount) under a row lock, guarded against overpayment, cicilan interference, and closed/cancelled bills — anything that fails a guard gets flagged `perlu_ditinjau_ulang` for manual admin review rather than silently changed. Four trigger sources feed the same action: a synchronous single-siswa path (SiswaKeringanan changes) and three bulk paths (Keringanan rule change, Tarif nominal change, Tarif priority reorder) that each dispatch one queued job per affected Tagihan. `SyncJenisTagihanBillingConfigAction` — which currently delete-and-recreates all Tarif/Keringanan rows on every form save — becomes diff-aware so it only reports "changed" (and triggers recalc) when the actual configuration differs, not on every unrelated save.

**Tech Stack:** Laravel 12, PHP 8.3, Pest v4, MySQL 8.0.30, Laravel Queue (`QUEUE_CONNECTION=database`).

## Global Constraints

These come from `.agents/specs/2026-09-01-jenis-tagihan-konsolidasi-sasaran-tarif-keringanan.md` and are binding on every task below:

- **`RecalculateTagihanNominalAction::execute()` MUST guard `tagihable_type !== Siswa::class` as its very first check**, returning immediately (no-op, no exception) — regardless of how any caller's query is written. Sasaran/Tarif/Keringanan never apply to PPDB (`Pendaftaran`-tagihable) bills.
- **Trigger #1's query for "all tagihan for this siswa" MUST filter by `tagihable_type = Siswa::class` AND `tagihable_id = $siswa->id` explicitly — NEVER by `person_id`.** `person_id` unifies the ledger across `tagihable_type` (Pendaftaran and Siswa), so a `person_id`-based query would silently pull old PPDB bills into recalc scope.
- **Auto-apply guards (all three must pass, or the Tagihan is flagged `perlu_ditinjau_ulang` instead of changed):** (1) `net_amount` baru ≥ `paid_amount` (no silent overpayment), (2) `$tagihan->skemaCicilan()->doesntExist()` (cicilan reconciliation is out of scope, always flagged), (3) `status` not in `['lunas', 'dibatalkan']`.
- **`TagihanStatusResolver` is the single source of truth for status transitions** (`lunas`/`sebagian`/`belum_bayar`/`dibatalkan`-preserved) — both `PaymentAllocationService::allocate()` and `RecalculateTagihanNominalAction` must call the same service, never duplicate the comparison logic.
- **`RecalculateTagihanNominalAction` MUST use `Tagihan::lockForUpdate()->find($id)` inside `DB::transaction()`** before reading `paid_amount`, matching `PaymentAllocationService::allocate()`'s existing locking pattern — prevents a race between a concurrent payment and a recalc on the same Tagihan.
- **Flagged Tagihan are always re-evaluated on the next trigger, never skipped.** If guards still fail, `alasan_perlu_ditinjau` is overwritten with the latest reason (never appended/stacked). If guards now pass, the recalc auto-applies AND auto-clears the flag in the same operation.
- **`SyncJenisTagihanBillingConfigAction` must become diff-aware BEFORE trigger #2/#3 go live** (Stage 6 before Stage 7 in task order below) — it currently deletes and recreates every Tarif/Keringanan row on every form save regardless of whether those sections changed; naively hooking recalc to Eloquent `deleted` events would fire a recalc storm on every unrelated save.
- **Trigger #4 (Tarif priority reorder) is a separate lightweight endpoint, dispatched directly from its own action — it never goes through `SyncJenisTagihanBillingConfigAction`'s diff detection**, because the reorder UI never submits the full form.
- **1 queued job per affected Tagihan for bulk triggers (#2, #3, #4) — never 1 big job that loops internally.** Isolates failures, allows granular per-item retry.
- **`SiswaKeringanan` remains global data owned by the Siswa, never scoped to one Jenis Tagihan.** The existing Siswa-edit-page assignment UI is never removed — the new in-form widget (Stage 9) is an additional entry point to the same data, reusing the same `SiswaKeringananController::store()`/`destroy()` endpoints, not a new backend.
- **`bisa_digabung` on `KategoriKeringanan` defaults to `false` for all existing rows** — current best-only discount behavior must not change for any category unless an admin explicitly opts it in.

---

## Stage 1 — Perbaikan Teknis Murni (Task 1)

### Task 1: Hapus kriteria "lembaga", perketat validasi "kelas"

**Files:**
- Modify: `app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php:34` (`KRITERIA_FIELDS`), `:349-391` (`billingRules()`)
- Test: `tests/Feature/Admin/JenisTagihanKriteriaValidasiTest.php`

**Interfaces:**
- Produces: `KRITERIA_FIELDS` no longer contains `'lembaga'`. `billingRules()` additionally rejects a `kelas`-field kriteria value that references a `Kelas` outside the acting lembaga.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Admin/JenisTagihanKriteriaValidasiTest.php

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('rejects lembaga as a sasaran kriteria field', function () {
    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($admin)->postJson(route('admin.jenis-tagihan.store'), [
        'nama' => 'Test', 'kategori' => 'spp', 'mode' => 'manual', 'tipe' => 'sekali',
        'sasaran' => [['kriteria' => [['field' => 'lembaga', 'operator' => 'in', 'value' => [(string) $lembaga->id]]]]],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('sasaran.0.kriteria.0.field');
});

it('rejects a kelas kriteria value referencing a kelas from a different lembaga', function () {
    $lembaga = Lembaga::factory()->create();
    $lembagaLain = Lembaga::factory()->create();
    $kelasLembagaLain = Kelas::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($admin)->postJson(route('admin.jenis-tagihan.store'), [
        'nama' => 'Test', 'kategori' => 'spp', 'mode' => 'manual', 'tipe' => 'sekali',
        'sasaran' => [['kriteria' => [['field' => 'kelas', 'operator' => 'in', 'value' => [(string) $kelasLembagaLain->id]]]]],
    ]);

    $response->assertStatus(422);
});

it('accepts a kelas kriteria value referencing a kelas from the same lembaga', function () {
    $lembaga = Lembaga::factory()->create();
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($admin)->postJson(route('admin.jenis-tagihan.store'), [
        'nama' => 'Test', 'kategori' => 'spp', 'mode' => 'manual', 'tipe' => 'sekali',
        'sasaran' => [['kriteria' => [['field' => 'kelas', 'operator' => 'in', 'value' => [(string) $kelas->id]]]]],
    ]);

    $response->assertStatus(201);
});

it('still accepts non-kelas kriteria fields without triggering the kelas existence check', function () {
    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($admin)->postJson(route('admin.jenis-tagihan.store'), [
        'nama' => 'Test', 'kategori' => 'spp', 'mode' => 'manual', 'tipe' => 'sekali',
        'sasaran' => [['kriteria' => [['field' => 'status_siswa', 'operator' => 'in', 'value' => ['aktif']]]]],
    ]);

    $response->assertStatus(201);
});
```

Read `app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php` in full first to confirm exact current line numbers for `KRITERIA_FIELDS` and `billingRules()` (may have shifted from the Tipe Penjadwalan work), and confirm the exact `resolveLembagaIdOrFail()`/session pattern this controller's other tests already use.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=JenisTagihanKriteriaValidasiTest`
Expected: FAIL (`lembaga` still accepted, foreign-lembaga `kelas` id still accepted)

- [ ] **Step 3: Remove `lembaga` from `KRITERIA_FIELDS`**

```php
private const KRITERIA_FIELDS = ['tahun_ajaran', 'tingkat', 'kelas', 'jenis_kelamin', 'status_siswa'];
```

- [ ] **Step 4: Add the cross-tenant `kelas` check via `Validator::after()`**

In `store()` and `update()`, after `$request->validate($this->billingRules($lembagaId, $request))` succeeds (or fold this into `billingRules()`'s caller), add a manual check. Simplest correct approach — add this as a private method and call it right after the existing `billingRules()` validation in both `store()` and `update()`:

```php
private function validateKelasKriteriaLembaga(array $billing, int $lembagaId): void
{
    $semuaKriteria = collect($billing['sasaran'] ?? [])->flatMap(fn ($grup) => $grup['kriteria'] ?? [])
        ->merge(collect($billing['tarif'] ?? [])->flatMap(fn ($grup) => $grup['kriteria'] ?? []));

    foreach ($semuaKriteria as $kriteria) {
        if (($kriteria['field'] ?? null) !== 'kelas') {
            continue;
        }

        $idsValid = Kelas::where('lembaga_id', $lembagaId)->whereIn('id', $kriteria['value'] ?? [])->pluck('id')->all();
        $idsDiminta = array_map('intval', $kriteria['value'] ?? []);

        if (array_diff($idsDiminta, $idsValid) !== []) {
            throw ValidationException::withMessages(['sasaran' => 'Salah satu kelas yang dipilih tidak ditemukan di lembaga ini.']);
        }
    }
}
```

Call `$this->validateKelasKriteriaLembaga($billing, $lembagaId);` in `store()` right after `$billing = $request->validate($this->billingRules($lembagaId, $request));` (and the equivalent line in `update()`), before `$duplicateError = $this->findDuplicateKeringanan(...)`.

Add `use App\Models\Kelas;` and `use Illuminate\Validation\ValidationException;` imports if not already present (check the file's existing imports first — `ValidationException` is likely already imported since `destroy()` catches it).

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=JenisTagihanKriteriaValidasiTest`
Expected: PASS (4 tests)

- [ ] **Step 6: Document temuan #6 as verified-safe (comment only, no code change)**

In `app/Domains/Keuangan/Services/JenisTagihanSasaranMatcher.php`, add a one-line comment above the `jenis_kelamin` handling in `siswaMatchesKriteria()` (find the exact line via `grep -n "jenis_kelamin" app/Domains/Keuangan/Services/JenisTagihanSasaranMatcher.php`):

```php
// $siswa->jenis_kelamin reads via Siswa::getJenisKelaminAttribute() -> $this->person->jenis_kelamin
// (siswa.jenis_kelamin was dropped in identity-v1 Task 28) -- this is the SAME source as the
// SQL-side whereHas('person', ...) check above, not a divergent one. Verified 2026-09-01.
```

- [ ] **Step 7: Run existing regression suites**

Run: `php artisan test --filter='JenisTagihanTest|JenisTagihanFormTest|JenisTagihanSasaranFormTest'`
Expected: PASS, unchanged.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php app/Domains/Keuangan/Services/JenisTagihanSasaranMatcher.php tests/Feature/Admin/JenisTagihanKriteriaValidasiTest.php
git commit -m "fix(keuangan): remove dead lembaga sasaran field, validate kelas kriteria against tenant"
```

---

## Stage 2 — `TagihanStatusResolver` (Task 2)

### Task 2: Service tunggal untuk transisi status Tagihan

**Files:**
- Create: `app/Domains/Keuangan/Services/TagihanStatusResolver.php`
- Modify: `app/Domains/Keuangan/Services/PaymentAllocationService.php:16-56`
- Test: `tests/Unit/Keuangan/TagihanStatusResolverTest.php`

**Interfaces:**
- Produces: `TagihanStatusResolver::resolve(float $paidAmount, float $netAmount, string $currentStatus): string`. Task 4 (`RecalculateTagihanNominalAction`) depends on this exact signature.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Keuangan\Services\TagihanStatusResolver;

it('resolves lunas when paid_amount covers net_amount', function () {
    expect((new TagihanStatusResolver)->resolve(500000, 500000, 'sebagian'))->toBe('lunas');
    expect((new TagihanStatusResolver)->resolve(600000, 500000, 'sebagian'))->toBe('lunas');
});

it('resolves sebagian when paid_amount is positive but below net_amount', function () {
    expect((new TagihanStatusResolver)->resolve(100000, 500000, 'belum_bayar'))->toBe('sebagian');
});

it('resolves belum_bayar when paid_amount is zero', function () {
    expect((new TagihanStatusResolver)->resolve(0, 500000, 'belum_bayar'))->toBe('belum_bayar');
});

it('preserves dibatalkan regardless of amounts', function () {
    expect((new TagihanStatusResolver)->resolve(500000, 500000, 'dibatalkan'))->toBe('dibatalkan');
    expect((new TagihanStatusResolver)->resolve(0, 500000, 'dibatalkan'))->toBe('dibatalkan');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TagihanStatusResolverTest`
Expected: FAIL (class doesn't exist)

- [ ] **Step 3: Create the service**

```php
<?php

namespace App\Domains\Keuangan\Services;

class TagihanStatusResolver
{
    public function resolve(float $paidAmount, float $netAmount, string $currentStatus): string
    {
        if ($currentStatus === 'dibatalkan') {
            return $currentStatus;
        }

        if ($paidAmount >= $netAmount) {
            return 'lunas';
        }

        return $paidAmount > 0 ? 'sebagian' : 'belum_bayar';
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=TagihanStatusResolverTest`
Expected: PASS (4 tests)

- [ ] **Step 5: Refactor `PaymentAllocationService::allocate()` to use it**

Read `app/Domains/Keuangan/Services/PaymentAllocationService.php` in full first (already confirmed lines 16-56 contain the constructor and `allocate()`). Add `TagihanStatusResolver` to the constructor:

```php
public function __construct(
    private readonly NotificationDispatcher $dispatcher,
    private readonly TagihanStatusResolver $statusResolver,
) {
}
```

Replace the status-decision block inside `allocate()` (currently lines 47-54):

```php
$lockedTagihan->paid_amount += $pt->amount_allocated;

$statusBaru = $this->statusResolver->resolve((float) $lockedTagihan->paid_amount, (float) $lockedTagihan->net_amount, $lockedTagihan->status);
$becameLunas = $statusBaru === 'lunas' && $lockedTagihan->status !== 'lunas';
$lockedTagihan->status = $statusBaru;
```

- [ ] **Step 6: Run existing `PaymentAllocationService`/payment regression tests**

Run: `php artisan test --filter='PembayaranTagihanTest|VirtualAccountControllerTest|PembayaranTest'` (search `grep -rln "PaymentAllocationService" tests/` first to confirm the actual covering test files, adjust filter to match).
Expected: PASS, unchanged behavior.

- [ ] **Step 7: Commit**

```bash
git add app/Domains/Keuangan/Services/TagihanStatusResolver.php app/Domains/Keuangan/Services/PaymentAllocationService.php tests/Unit/Keuangan/TagihanStatusResolverTest.php
git commit -m "feat(keuangan): extract TagihanStatusResolver as single source of truth for status transitions"
```

---

## Stage 3 — Engine Recalculate: Kolom Flag + Action + Aksi Tinjau (Tasks 3-5)

### Task 3: Migration `perlu_ditinjau_ulang` + `alasan_perlu_ditinjau`

**Files:**
- Create: `database/migrations/2026_09_01_000004_add_perlu_ditinjau_ulang_to_tagihan_table.php`
- Test: `tests/Feature/Keuangan/TagihanPerluDitinjauMigrationTest.php`

**Interfaces:**
- Produces: `tagihan.perlu_ditinjau_ulang` (boolean, default false), `tagihan.alasan_perlu_ditinjau` (text, nullable). Task 4 depends on these column names exactly.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Keuangan\Models\Tagihan;

it('has perlu_ditinjau_ulang defaulting to false and alasan_perlu_ditinjau nullable', function () {
    $tagihan = Tagihan::factory()->create();

    expect($tagihan->fresh()->perlu_ditinjau_ulang)->toBeFalse();
    expect($tagihan->fresh()->alasan_perlu_ditinjau)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TagihanPerluDitinjauMigrationTest`
Expected: FAIL (columns don't exist)

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagihan', function (Blueprint $table) {
            $table->boolean('perlu_ditinjau_ulang')->default(false)->after('discount_type');
            $table->text('alasan_perlu_ditinjau')->nullable()->after('perlu_ditinjau_ulang');
        });
    }

    public function down(): void
    {
        Schema::table('tagihan', function (Blueprint $table) {
            $table->dropColumn(['perlu_ditinjau_ulang', 'alasan_perlu_ditinjau']);
        });
    }
};
```

Run: `php artisan migrate`

Also add `'perlu_ditinjau_ulang', 'alasan_perlu_ditinjau'` to `Tagihan::$fillable` (`app/Domains/Keuangan/Models/Tagihan.php:34`) and add `'perlu_ditinjau_ulang' => 'boolean'` to its `casts()`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=TagihanPerluDitinjauMigrationTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_09_01_000004_add_perlu_ditinjau_ulang_to_tagihan_table.php app/Domains/Keuangan/Models/Tagihan.php tests/Feature/Keuangan/TagihanPerluDitinjauMigrationTest.php
git commit -m "feat(keuangan): add perlu_ditinjau_ulang/alasan_perlu_ditinjau columns to tagihan"
```

---

### Task 4: `RecalculateTagihanNominalAction`

**Files:**
- Create: `app/Domains/Keuangan/Actions/Tagihan/RecalculateTagihanNominalAction.php`
- Test: `tests/Feature/Keuangan/RecalculateTagihanNominalActionTest.php`

**Interfaces:**
- Consumes: `TagihanNominalResolver::resolve()` (existing), `TagihanStatusResolver::resolve()` (Task 2).
- Produces: `RecalculateTagihanNominalAction::execute(int $tagihanId): void`. Tasks 12-14 (trigger wiring) and the queued job (Task 12) depend on this exact signature — it takes an **id**, not a model instance, so it can be safely serialized into a queue job payload.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Domains\Keuangan\Actions\Tagihan\RecalculateTagihanNominalAction;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\JenisTagihanKeringanan;
use App\Domains\Keuangan\Models\KategoriKeringanan;
use App\Domains\Keuangan\Models\SiswaKeringanan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Pendaftaran;
use App\Models\Siswa;

it('is a no-op for a PPDB tagihan (tagihable_type = Pendaftaran), not an error', function () {
    $pendaftaran = Pendaftaran::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Pendaftaran::class,
        'tagihable_id' => $pendaftaran->id,
        'pendaftaran_id' => $pendaftaran->id,
        'net_amount' => 500000,
        'status' => 'belum_bayar',
    ]);

    app(RecalculateTagihanNominalAction::class)->execute($tagihan->id);

    $fresh = $tagihan->fresh();
    expect((float) $fresh->net_amount)->toBe(500000.0);
    expect($fresh->status)->toBe('belum_bayar');
    expect($fresh->perlu_ditinjau_ulang)->toBeFalse();
});

it('recalculates net_amount when a keringanan is added after the tagihan was created', function () {
    $siswa = Siswa::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $siswa->lembaga_id, 'default_amount' => 300000]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'total_tagihan' => 300000, 'net_amount' => 300000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);
    $kategori = KategoriKeringanan::factory()->create(['lembaga_id' => $siswa->lembaga_id]);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategori->id, 'tipe_potongan' => 'fixed', 'nilai' => 50000]);
    SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategori->id, 'berlaku_dari' => now()->subDay()->toDateString()]);

    app(RecalculateTagihanNominalAction::class)->execute($tagihan->id);

    $fresh = $tagihan->fresh();
    expect((float) $fresh->discount_amount)->toBe(50000.0);
    expect((float) $fresh->net_amount)->toBe(250000.0);
    expect($fresh->perlu_ditinjau_ulang)->toBeFalse();
});

it('flags perlu_ditinjau_ulang instead of applying when the new net_amount would be below paid_amount', function () {
    $siswa = Siswa::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $siswa->lembaga_id, 'default_amount' => 300000]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'total_tagihan' => 300000, 'net_amount' => 300000, 'paid_amount' => 280000, 'status' => 'sebagian',
    ]);
    $kategori = KategoriKeringanan::factory()->create(['lembaga_id' => $siswa->lembaga_id]);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategori->id, 'tipe_potongan' => 'fixed', 'nilai' => 100000]);
    SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategori->id, 'berlaku_dari' => now()->subDay()->toDateString()]);

    app(RecalculateTagihanNominalAction::class)->execute($tagihan->id);

    $fresh = $tagihan->fresh();
    expect((float) $fresh->net_amount)->toBe(300000.0); // unchanged
    expect($fresh->perlu_ditinjau_ulang)->toBeTrue();
    expect($fresh->alasan_perlu_ditinjau)->toContain('lebih kecil dari yang sudah dibayar');
});

it('flags perlu_ditinjau_ulang instead of applying when the tagihan already has a skema cicilan', function () {
    $siswa = Siswa::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $siswa->lembaga_id, 'default_amount' => 300000]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'total_tagihan' => 300000, 'net_amount' => 300000, 'paid_amount' => 0, 'status' => 'dicicil',
    ]);
    app(\App\Domains\Keuangan\Services\PembayaranService::class)->buatSkemaCicilan($tagihan, 3, 'admin');
    $kategori = KategoriKeringanan::factory()->create(['lembaga_id' => $siswa->lembaga_id]);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategori->id, 'tipe_potongan' => 'fixed', 'nilai' => 50000]);
    SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategori->id, 'berlaku_dari' => now()->subDay()->toDateString()]);

    app(RecalculateTagihanNominalAction::class)->execute($tagihan->id);

    $fresh = $tagihan->fresh();
    expect((float) $fresh->net_amount)->toBe(300000.0);
    expect($fresh->perlu_ditinjau_ulang)->toBeTrue();
    expect($fresh->alasan_perlu_ditinjau)->toContain('cicilan');
});

it('does not recalculate a lunas or dibatalkan tagihan', function () {
    $siswa = Siswa::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $siswa->lembaga_id, 'default_amount' => 300000]);
    $tagihanLunas = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'total_tagihan' => 300000, 'net_amount' => 300000, 'paid_amount' => 300000, 'status' => 'lunas',
    ]);

    app(RecalculateTagihanNominalAction::class)->execute($tagihanLunas->id);

    expect($tagihanLunas->fresh()->perlu_ditinjau_ulang)->toBeFalse();
});

it('re-evaluates a previously flagged tagihan and auto-clears the flag once the guard passes', function () {
    $siswa = Siswa::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $siswa->lembaga_id, 'default_amount' => 300000]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'total_tagihan' => 300000, 'net_amount' => 300000, 'paid_amount' => 280000, 'status' => 'sebagian',
        'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'alasan basi sebelumnya',
    ]);

    // Situasi membaik: paid_amount turun secara hipotetis tidak realistis, jadi simulasikan
    // dengan menaikkan net_amount kembali (mis. keringanan yang tadinya bikin net_amount < paid_amount
    // dicabut lagi) -- tidak ada JenisTagihanKeringanan/SiswaKeringanan sama sekali, jadi resolve()
    // menghasilkan net_amount = default_amount = 300000, yang >= paid_amount 280000.
    app(RecalculateTagihanNominalAction::class)->execute($tagihan->id);

    $fresh = $tagihan->fresh();
    expect($fresh->perlu_ditinjau_ulang)->toBeFalse();
    expect($fresh->alasan_perlu_ditinjau)->toBeNull();
    expect($fresh->status)->toBe('sebagian');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=RecalculateTagihanNominalActionTest`
Expected: FAIL (class doesn't exist)

- [ ] **Step 3: Write the action**

```php
<?php

namespace App\Domains\Keuangan\Actions\Tagihan;

use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Services\TagihanNominalResolver;
use App\Domains\Keuangan\Services\TagihanStatusResolver;
use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use Illuminate\Support\Facades\DB;

class RecalculateTagihanNominalAction
{
    public function __construct(
        private readonly TagihanNominalResolver $nominalResolver,
        private readonly TagihanStatusResolver $statusResolver,
    ) {
    }

    public function execute(int $tagihanId): void
    {
        DB::transaction(function () use ($tagihanId) {
            $tagihan = Tagihan::withoutGlobalScope(TenantScope::class)->lockForUpdate()->find($tagihanId);

            if ($tagihan === null || in_array($tagihan->status, ['lunas', 'dibatalkan'], true)) {
                return;
            }

            // Guard defensif WAJIB, terlepas dari bagaimana query pemanggil ditulis: Sasaran/
            // Tarif/Keringanan cuma berlaku untuk tagihan Siswa. Tagihan PPDB (tagihable_type =
            // Pendaftaran::class) pakai mekanisme nominal-per-jalur yang berbeda total.
            if ($tagihan->tagihable_type !== Siswa::class) {
                return;
            }

            $siswa = Siswa::withoutGlobalScope(TenantScope::class)->find($tagihan->tagihable_id);
            $jenisTagihan = $tagihan->jenisTagihan;

            if ($siswa === null || $jenisTagihan === null) {
                return;
            }

            $resolved = $this->nominalResolver->resolve($siswa, $jenisTagihan);
            $newNetAmount = max(0, $resolved['nominal'] - $resolved['discount_amount']);

            $adaOverpayment = $newNetAmount < (float) $tagihan->paid_amount;
            $adaCicilan = $tagihan->skemaCicilan()->exists();

            if ($adaOverpayment || $adaCicilan) {
                $alasan = $adaOverpayment
                    ? 'Net amount baru Rp'.number_format($newNetAmount, 0, ',', '.').' lebih kecil dari yang sudah dibayar Rp'.number_format((float) $tagihan->paid_amount, 0, ',', '.')
                    : 'Tagihan sudah punya skema cicilan -- rekonsiliasi manual via halaman cicilan.';

                $tagihan->update(['perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => $alasan]);

                return;
            }

            $tagihan->total_tagihan = $resolved['nominal'];
            $tagihan->discount_amount = $resolved['discount_amount'];
            $tagihan->discount_type = $resolved['discount_type'];
            $tagihan->net_amount = $newNetAmount;
            $tagihan->status = $this->statusResolver->resolve((float) $tagihan->paid_amount, $newNetAmount, $tagihan->status);
            $tagihan->perlu_ditinjau_ulang = false;
            $tagihan->alasan_perlu_ditinjau = null;
            $tagihan->save();
        });
    }
}
```

(Notifikasi `TagihanDirevisiNotification` disambung di Task 15 — task ini sengaja belum mengirim notifikasi apapun, murni logic recalc.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=RecalculateTagihanNominalActionTest`
Expected: PASS (6 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Keuangan/Actions/Tagihan/RecalculateTagihanNominalAction.php tests/Feature/Keuangan/RecalculateTagihanNominalActionTest.php
git commit -m "feat(keuangan): add RecalculateTagihanNominalAction with overpayment/cicilan/tagihable guards"
```

---

### Task 5: `SelesaikanTinjauanTagihanAction` + halaman dasar

**Files:**
- Create: `app/Domains/Keuangan/Actions/Tagihan/SelesaikanTinjauanTagihanAction.php`
- Modify: `app/Http/Controllers/Lembaga/Keuangan/TagihanController.php` (tambah method `tandaiSelesaiDitinjau`)
- Modify: `routes/admin/keuangan.php`
- Test: `tests/Feature/Keuangan/SelesaikanTinjauanTagihanActionTest.php`

**Interfaces:**
- Produces: `SelesaikanTinjauanTagihanAction::execute(Tagihan $tagihan): void`, route `POST admin/tagihan/{tagihan}/selesai-ditinjau` named `admin.tagihan.selesai-ditinjau`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Keuangan\Actions\Tagihan\SelesaikanTinjauanTagihanAction;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('clears the flag and reason without touching any nominal column', function () {
    $tagihan = Tagihan::factory()->create([
        'net_amount' => 300000, 'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'contoh alasan',
    ]);

    app(SelesaikanTinjauanTagihanAction::class)->execute($tagihan);

    $fresh = $tagihan->fresh();
    expect($fresh->perlu_ditinjau_ulang)->toBeFalse();
    expect($fresh->alasan_perlu_ditinjau)->toBeNull();
    expect((float) $fresh->net_amount)->toBe(300000.0);
});

it('exposes the action via a route guarded by permission', function () {
    $lembaga = \App\Models\Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    $tagihan = Tagihan::factory()->create(['lembaga_id' => $lembaga->id, 'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'x']);

    $response = $this->actingAs($admin)->post(route('admin.tagihan.selesai-ditinjau', $tagihan));

    $response->assertRedirect();
    expect($tagihan->fresh()->perlu_ditinjau_ulang)->toBeFalse();
});
```

Read `app/Http/Controllers/Lembaga/Keuangan/TagihanController.php` in full first to confirm its exact existing structure/permission-check pattern before adding a new method to it (matches the project's existing single-controller-per-resource convention already used for `JenisTagihanController`).

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SelesaikanTinjauanTagihanActionTest`
Expected: FAIL (class/route don't exist)

- [ ] **Step 3: Write the action**

```php
<?php

namespace App\Domains\Keuangan\Actions\Tagihan;

use App\Domains\Keuangan\Models\Tagihan;

class SelesaikanTinjauanTagihanAction
{
    public function execute(Tagihan $tagihan): void
    {
        $tagihan->update(['perlu_ditinjau_ulang' => false, 'alasan_perlu_ditinjau' => null]);
    }
}
```

- [ ] **Step 4: Add the controller method and route**

In `TagihanController.php`, add (matching whatever permission the file's other write actions already check — read the file to find the exact permission string used, e.g. `tagihan.edit`):

```php
public function tandaiSelesaiDitinjau(Tagihan $tagihan, SelesaikanTinjauanTagihanAction $action): RedirectResponse
{
    $this->authorize('tagihan.edit'); // confirm exact permission string from this file's other methods

    $action->execute($tagihan);

    return back()->with('status', 'Tagihan ditandai selesai ditinjau.');
}
```

In `routes/admin/keuangan.php`, add near the other `tagihan/{tagihan}` routes:
```php
Route::post('tagihan/{tagihan}/selesai-ditinjau', [TagihanController::class, 'tandaiSelesaiDitinjau'])->name('tagihan.selesai-ditinjau');
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=SelesaikanTinjauanTagihanActionTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Keuangan/Actions/Tagihan/SelesaikanTinjauanTagihanAction.php app/Http/Controllers/Lembaga/Keuangan/TagihanController.php routes/admin/keuangan.php tests/Feature/Keuangan/SelesaikanTinjauanTagihanActionTest.php
git commit -m "feat(keuangan): add SelesaikanTinjauanTagihanAction and its route"
```

---

## Stage 4 — Kolom `priority` untuk Tarif (Task 6)

### Task 6: Migration + backfill `priority`, ganti `resolveNominal()` ke `orderBy('priority')`

**Files:**
- Create: `database/migrations/2026_09_01_000005_add_priority_to_jenis_tagihan_sasaran_grup_table.php`
- Modify: `app/Domains/Keuangan/Services/TagihanNominalResolver.php:43`
- Modify: `app/Domains/Keuangan/Models/JenisTagihanSasaranGrup.php` (`$fillable`)
- Test: `tests/Feature/Keuangan/TarifPriorityBackfillTest.php`

**Interfaces:**
- Produces: `jenis_tagihan_sasaran_grup.priority` (int, nullable for `sasaran`-type rows, populated for `tarif`-type rows). `resolveNominal()` now orders by `priority` ascending.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\JenisTagihanSasaranGrup;
use App\Domains\Keuangan\Services\TagihanNominalResolver;
use App\Domains\Keuangan\Services\JenisTagihanSasaranMatcher;
use App\Models\Siswa;
use Illuminate\Support\Facades\DB;

it('backfills priority for existing tarif grups matching their original id order, per jenis_tagihan_id', function () {
    $jenisTagihanA = JenisTagihan::factory()->create();
    $jenisTagihanB = JenisTagihan::factory()->create();

    // Simulasikan baris "lama" (sebelum migrasi ini) dengan insert langsung, priority belum diisi.
    $grupA1 = DB::table('jenis_tagihan_sasaran_grup')->insertGetId(['jenis_tagihan_id' => $jenisTagihanA->id, 'tipe' => 'tarif', 'nominal' => 100000, 'created_at' => now(), 'updated_at' => now()]);
    $grupA2 = DB::table('jenis_tagihan_sasaran_grup')->insertGetId(['jenis_tagihan_id' => $jenisTagihanA->id, 'tipe' => 'tarif', 'nominal' => 200000, 'created_at' => now(), 'updated_at' => now()]);
    $grupB1 = DB::table('jenis_tagihan_sasaran_grup')->insertGetId(['jenis_tagihan_id' => $jenisTagihanB->id, 'tipe' => 'tarif', 'nominal' => 300000, 'created_at' => now(), 'updated_at' => now()]);

    DB::statement('
        UPDATE jenis_tagihan_sasaran_grup g
        JOIN (SELECT id, ROW_NUMBER() OVER (PARTITION BY jenis_tagihan_id ORDER BY id) AS rn FROM jenis_tagihan_sasaran_grup WHERE tipe = "tarif") ranked
        ON g.id = ranked.id
        SET g.priority = ranked.rn
    ');

    expect(JenisTagihanSasaranGrup::find($grupA1)->priority)->toBe(1);
    expect(JenisTagihanSasaranGrup::find($grupA2)->priority)->toBe(2);
    expect(JenisTagihanSasaranGrup::find($grupB1)->priority)->toBe(1); // partition terpisah per jenis_tagihan_id
});

it('resolveNominal picks the tarif grup with the lowest priority that matches, not insertion order', function () {
    $lembaga = \App\Models\Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'default_amount' => 999999]);

    $grupUmum = $jenisTagihan->sasaranGrup()->create(['tipe' => 'tarif', 'nominal' => 100000, 'priority' => 2]);
    $grupSpesifik = $jenisTagihan->sasaranGrup()->create(['tipe' => 'tarif', 'nominal' => 250000, 'priority' => 1]);
    // Kedua grup dibuat tanpa kriteria sama sekali -> siswaMatchesGrup() true untuk keduanya (AND kosong = true).

    $matcher = new JenisTagihanSasaranMatcher();
    $resolver = new TagihanNominalResolver($matcher);
    $result = $resolver->resolve($siswa, $jenisTagihan);

    expect($result['nominal'])->toBe(250000.0); // grupSpesifik (priority 1) menang meski dibuat SETELAH grupUmum
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=TarifPriorityBackfillTest`
Expected: FAIL (`priority` column doesn't exist)

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jenis_tagihan_sasaran_grup', function (Blueprint $table) {
            $table->unsignedInteger('priority')->nullable()->after('nominal');
        });

        DB::statement('
            UPDATE jenis_tagihan_sasaran_grup g
            JOIN (SELECT id, ROW_NUMBER() OVER (PARTITION BY jenis_tagihan_id ORDER BY id) AS rn FROM jenis_tagihan_sasaran_grup WHERE tipe = "tarif") ranked
            ON g.id = ranked.id
            SET g.priority = ranked.rn
        ');
    }

    public function down(): void
    {
        Schema::table('jenis_tagihan_sasaran_grup', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
```

Run: `php artisan migrate`

Add `'priority'` to `JenisTagihanSasaranGrup::$fillable` (currently `['jenis_tagihan_id', 'tipe', 'nominal']`).

- [ ] **Step 4: Change `resolveNominal()`'s ordering**

In `app/Domains/Keuangan/Services/TagihanNominalResolver.php:43`, change:
```php
$tarifGrups = $jenisTagihan->sasaranGrup()->where('tipe', 'tarif')->with('kriteria')->orderBy('id')->get();
```
to:
```php
$tarifGrups = $jenisTagihan->sasaranGrup()->where('tipe', 'tarif')->with('kriteria')->orderBy('priority')->get();
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=TarifPriorityBackfillTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Run existing regression**

Run: `php artisan test --filter='TagihanNominalResolverTest|TagihanBillingGeneratorTest'`
Expected: PASS, unchanged — existing tests create tarif grups sequentially without ever setting `priority`, but since `SyncJenisTagihanBillingConfigAction` (still delete-recreate at this point in the plan, refactored in Stage 6) creates them via `sasaranGrup()->create()` in the same insertion order the form submits them, `priority` will be `NULL` for any grup created AFTER this migration point via the current sync action — this is fine for now (Stage 6 fixes assignment of `priority` at creation time); this task only needs the BACKFILL for pre-existing rows and the resolver's ordering change.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_09_01_000005_add_priority_to_jenis_tagihan_sasaran_grup_table.php app/Domains/Keuangan/Services/TagihanNominalResolver.php app/Domains/Keuangan/Models/JenisTagihanSasaranGrup.php tests/Feature/Keuangan/TarifPriorityBackfillTest.php
git commit -m "feat(keuangan): add explicit priority column for tarif grup, backfilled from id order"
```

---

## Stage 5 — `bisa_digabung` (Task 7)

### Task 7: Kolom `bisa_digabung` + logic penjumlahan di `resolveDiscount()`

**Files:**
- Create: `database/migrations/2026_09_01_000006_add_bisa_digabung_to_kategori_keringanan_table.php`
- Modify: `app/Domains/Keuangan/Models/KategoriKeringanan.php` (`$fillable`)
- Modify: `app/Domains/Keuangan/Services/TagihanNominalResolver.php:57-91` (`resolveDiscount()`)
- Test: `tests/Feature/Keuangan/TagihanNominalResolverBisaDigabungTest.php`

**Interfaces:**
- Produces: `kategori_keringanan.bisa_digabung` (boolean, default false). `resolveDiscount()`'s behavior for `bisa_digabung=false` categories is UNCHANGED (best-only); for `bisa_digabung=true` categories, amounts are summed alongside the best non-combinable amount, clamped to `$nominal`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\JenisTagihanKeringanan;
use App\Domains\Keuangan\Models\KategoriKeringanan;
use App\Domains\Keuangan\Models\SiswaKeringanan;
use App\Domains\Keuangan\Services\JenisTagihanSasaranMatcher;
use App\Domains\Keuangan\Services\TagihanNominalResolver;
use App\Models\Siswa;

it('still picks only the largest discount when all matching categories are non-combinable (regression)', function () {
    $lembaga = \App\Models\Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'default_amount' => 500000]);

    $kategoriKecil = KategoriKeringanan::factory()->create(['lembaga_id' => $lembaga->id, 'bisa_digabung' => false]);
    $kategoriBesar = KategoriKeringanan::factory()->create(['lembaga_id' => $lembaga->id, 'bisa_digabung' => false]);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategoriKecil->id, 'tipe_potongan' => 'fixed', 'nilai' => 50000]);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategoriBesar->id, 'tipe_potongan' => 'fixed', 'nilai' => 150000]);
    SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategoriKecil->id, 'berlaku_dari' => now()->subDay()->toDateString()]);
    SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategoriBesar->id, 'berlaku_dari' => now()->subDay()->toDateString()]);

    $resolver = new TagihanNominalResolver(new JenisTagihanSasaranMatcher());
    $result = $resolver->resolve($siswa, $jenisTagihan);

    expect($result['discount_amount'])->toBe(150000.0); // hanya yang terbesar, tidak dijumlah
});

it('sums combinable discounts on top of the best non-combinable one', function () {
    $lembaga = \App\Models\Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'default_amount' => 500000]);

    $kategoriUtama = KategoriKeringanan::factory()->create(['lembaga_id' => $lembaga->id, 'bisa_digabung' => false]);
    $kategoriTambahan = KategoriKeringanan::factory()->create(['lembaga_id' => $lembaga->id, 'bisa_digabung' => true]);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategoriUtama->id, 'tipe_potongan' => 'fixed', 'nilai' => 150000]);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategoriTambahan->id, 'tipe_potongan' => 'fixed', 'nilai' => 50000]);
    SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategoriUtama->id, 'berlaku_dari' => now()->subDay()->toDateString()]);
    SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategoriTambahan->id, 'berlaku_dari' => now()->subDay()->toDateString()]);

    $resolver = new TagihanNominalResolver(new JenisTagihanSasaranMatcher());
    $result = $resolver->resolve($siswa, $jenisTagihan);

    expect($result['discount_amount'])->toBe(200000.0); // 150000 (terbesar non-combinable) + 50000 (combinable)
});

it('clamps total discount to the nominal, never producing a negative net_amount', function () {
    $lembaga = \App\Models\Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'default_amount' => 100000]);

    $kategoriUtama = KategoriKeringanan::factory()->create(['lembaga_id' => $lembaga->id, 'bisa_digabung' => false]);
    $kategoriTambahan = KategoriKeringanan::factory()->create(['lembaga_id' => $lembaga->id, 'bisa_digabung' => true]);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategoriUtama->id, 'tipe_potongan' => 'fixed', 'nilai' => 80000]);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategoriTambahan->id, 'tipe_potongan' => 'fixed', 'nilai' => 80000]);
    SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategoriUtama->id, 'berlaku_dari' => now()->subDay()->toDateString()]);
    SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategoriTambahan->id, 'berlaku_dari' => now()->subDay()->toDateString()]);

    $resolver = new TagihanNominalResolver(new JenisTagihanSasaranMatcher());
    $result = $resolver->resolve($siswa, $jenisTagihan);

    expect($result['discount_amount'])->toBe(100000.0); // 80000+80000=160000 di-clamp ke nominal 100000
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=TagihanNominalResolverBisaDigabungTest`
Expected: FAIL (`bisa_digabung` column doesn't exist, `resolveDiscount()` doesn't sum)

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kategori_keringanan', function (Blueprint $table) {
            $table->boolean('bisa_digabung')->default(false)->after('keterangan');
        });
    }

    public function down(): void
    {
        Schema::table('kategori_keringanan', function (Blueprint $table) {
            $table->dropColumn('bisa_digabung');
        });
    }
};
```

Run: `php artisan migrate`

Add `'bisa_digabung'` to `KategoriKeringanan::$fillable` and add `casts()` method with `'bisa_digabung' => 'boolean'` (this model currently has no `casts()` method — add one).

- [ ] **Step 4: Rewrite `resolveDiscount()`**

Replace the body of `TagihanNominalResolver::resolveDiscount()` (`app/Domains/Keuangan/Services/TagihanNominalResolver.php:57-91`):

```php
private function resolveDiscount(Siswa $siswa, JenisTagihan $jenisTagihan, float $nominal): array
{
    $today = now()->toDateString();

    $kategoriIds = SiswaKeringanan::where('siswa_id', $siswa->id)
        ->where('berlaku_dari', '<=', $today)
        ->where(function ($q) use ($today) {
            $q->whereNull('berlaku_sampai')->orWhere('berlaku_sampai', '>=', $today);
        })
        ->pluck('kategori_keringanan_id');

    if ($kategoriIds->isEmpty()) {
        return [0.0, null];
    }

    $rules = JenisTagihanKeringanan::where('jenis_tagihan_id', $jenisTagihan->id)
        ->whereIn('kategori_keringanan_id', $kategoriIds)
        ->with('kategoriKeringanan')
        ->get();

    $bestNonCombinable = 0.0;
    $bestType = null;
    $totalCombinable = 0.0;

    foreach ($rules as $rule) {
        $amount = $rule->tipe_potongan === 'persen'
            ? round($nominal * ((float) $rule->nilai) / 100, 2)
            : (float) $rule->nilai;

        if ($rule->kategoriKeringanan->bisa_digabung) {
            $totalCombinable += $amount;

            continue;
        }

        if ($amount > $bestNonCombinable) {
            $bestNonCombinable = $amount;
            $bestType = $rule->tipe_potongan;
        }
    }

    $totalDiscount = min($nominal, $bestNonCombinable + $totalCombinable);
    $discountType = $bestType ?? ($totalCombinable > 0 ? 'gabungan' : null);

    return [$totalDiscount, $discountType];
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=TagihanNominalResolverBisaDigabungTest`
Expected: PASS (3 tests)

- [ ] **Step 6: Run existing `TagihanNominalResolverTest`/`TagihanBillingGeneratorTest` regression**

Run: `php artisan test --filter='TagihanNominalResolverTest|TagihanBillingGeneratorTest'`
Expected: PASS, unchanged (all existing categories default `bisa_digabung=false`, so behavior is identical to before).

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_09_01_000006_add_bisa_digabung_to_kategori_keringanan_table.php app/Domains/Keuangan/Models/KategoriKeringanan.php app/Domains/Keuangan/Services/TagihanNominalResolver.php tests/Feature/Keuangan/TagihanNominalResolverBisaDigabungTest.php
git commit -m "feat(keuangan): implement actual discount summation for bisa_digabung kategori keringanan"
```

---

## Stage 6 — `SyncJenisTagihanBillingConfigAction` Diff-Aware (Task 8)

### Task 8: Deteksi perubahan nyata sebelum delete-recreate

**Files:**
- Modify: `app/Domains/Keuangan/Actions/JenisTagihan/SyncJenisTagihanBillingConfigAction.php`
- Create: `app/Domains/Keuangan/DataTransferObjects/SyncBillingConfigResult.php`
- Test: `tests/Feature/Keuangan/SyncJenisTagihanBillingConfigActionDiffTest.php`

**Interfaces:**
- Produces: `SyncJenisTagihanBillingConfigAction::execute(JenisTagihan $jenisTagihan, ?array $billing): SyncBillingConfigResult` (changed return type from `void`). `SyncBillingConfigResult` has public readonly `bool $tarifBerubah` and `bool $keringananBerubah`. Task 12 (trigger wiring) depends on this exact class/property names. **This changes the method's call sites** — grep for `SyncJenisTagihanBillingConfigAction` usage (`CreateJenisTagihanAction.php`, `UpdateJenisTagihanAction.php`) before editing, both currently call `->execute(...)` and discard the return value (`void`) — they must be updated too even though they don't need the result themselves (Task 12's controller-level trigger wiring does).

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Domains\Keuangan\Actions\JenisTagihan\SyncJenisTagihanBillingConfigAction;
use App\Domains\Keuangan\Models\JenisTagihan;

it('reports tarifBerubah=false and keringananBerubah=false when the billing config is unchanged', function () {
    $jenisTagihan = JenisTagihan::factory()->create();
    $action = app(SyncJenisTagihanBillingConfigAction::class);

    $billing = ['tarif' => [['nominal' => 100000, 'kriteria' => [['field' => 'status_siswa', 'operator' => 'in', 'value' => ['aktif']]]]], 'keringanan' => []];

    $action->execute($jenisTagihan, $billing); // panggilan pertama: dari kosong -> ada isi, JELAS berubah
    $result = $action->execute($jenisTagihan, $billing); // panggilan kedua: payload IDENTIK

    expect($result->tarifBerubah)->toBeFalse();
    expect($result->keringananBerubah)->toBeFalse();
});

it('reports tarifBerubah=true when a tarif grup nominal changes', function () {
    $jenisTagihan = JenisTagihan::factory()->create();
    $action = app(SyncJenisTagihanBillingConfigAction::class);

    $action->execute($jenisTagihan, ['tarif' => [['nominal' => 100000, 'kriteria' => [['field' => 'status_siswa', 'operator' => 'in', 'value' => ['aktif']]]]], 'keringanan' => []]);
    $result = $action->execute($jenisTagihan, ['tarif' => [['nominal' => 150000, 'kriteria' => [['field' => 'status_siswa', 'operator' => 'in', 'value' => ['aktif']]]]], 'keringanan' => []]);

    expect($result->tarifBerubah)->toBeTrue();
});

it('reports tarifBerubah=true when a tarif grup is removed entirely', function () {
    $jenisTagihan = JenisTagihan::factory()->create();
    $action = app(SyncJenisTagihanBillingConfigAction::class);

    $action->execute($jenisTagihan, ['tarif' => [['nominal' => 100000, 'kriteria' => [['field' => 'status_siswa', 'operator' => 'in', 'value' => ['aktif']]]]], 'keringanan' => []]);
    $result = $action->execute($jenisTagihan, ['tarif' => [], 'keringanan' => []]);

    expect($result->tarifBerubah)->toBeTrue();
});

it('reports keringananBerubah=true when a keringanan rule nilai changes, unrelated to tarif', function () {
    $jenisTagihan = JenisTagihan::factory()->create();
    $lembaga = $jenisTagihan->lembaga;
    $kategori = \App\Domains\Keuangan\Models\KategoriKeringanan::factory()->create(['lembaga_id' => $lembaga->id]);
    $action = app(SyncJenisTagihanBillingConfigAction::class);

    $action->execute($jenisTagihan, ['tarif' => [], 'keringanan' => [['kategori_keringanan_id' => $kategori->id, 'tipe_potongan' => 'fixed', 'nilai' => 50000]]]);
    $result = $action->execute($jenisTagihan, ['tarif' => [], 'keringanan' => [['kategori_keringanan_id' => $kategori->id, 'tipe_potongan' => 'fixed', 'nilai' => 75000]]]);

    expect($result->keringananBerubah)->toBeTrue();
    expect($result->tarifBerubah)->toBeFalse();
});

it('does not blow up and reports both false when billing is null both times', function () {
    $jenisTagihan = JenisTagihan::factory()->create();
    $action = app(SyncJenisTagihanBillingConfigAction::class);

    $action->execute($jenisTagihan, null);
    $result = $action->execute($jenisTagihan, null);

    expect($result->tarifBerubah)->toBeFalse();
    expect($result->keringananBerubah)->toBeFalse();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=SyncJenisTagihanBillingConfigActionDiffTest`
Expected: FAIL (method still returns `void`)

- [ ] **Step 3: Create the result DTO**

```php
<?php

namespace App\Domains\Keuangan\DataTransferObjects;

final readonly class SyncBillingConfigResult
{
    public function __construct(
        public bool $tarifBerubah,
        public bool $keringananBerubah,
    ) {
    }
}
```

- [ ] **Step 4: Rewrite the action to diff before delete-recreate**

```php
<?php

namespace App\Domains\Keuangan\Actions\JenisTagihan;

use App\Domains\Keuangan\DataTransferObjects\SyncBillingConfigResult;
use App\Domains\Keuangan\Models\JenisTagihan;

class SyncJenisTagihanBillingConfigAction
{
    /**
     * @param  array<string, mixed>|null  $billing
     */
    public function execute(JenisTagihan $jenisTagihan, ?array $billing): SyncBillingConfigResult
    {
        $tarifLama = $this->snapshotTarif($jenisTagihan);
        $keringananLama = $this->snapshotKeringanan($jenisTagihan);

        $jenisTagihan->sasaranGrup()->delete();
        $jenisTagihan->keringananRules()->delete();

        if ($billing !== null) {
            foreach ($billing['sasaran'] ?? [] as $grupData) {
                $grup = $jenisTagihan->sasaranGrup()->create(['tipe' => 'sasaran']);
                foreach ($grupData['kriteria'] as $kriteriaData) {
                    $grup->kriteria()->create($kriteriaData);
                }
            }

            foreach ($billing['tarif'] ?? [] as $index => $grupData) {
                $grup = $jenisTagihan->sasaranGrup()->create(['tipe' => 'tarif', 'nominal' => $grupData['nominal'], 'priority' => $index + 1]);
                foreach ($grupData['kriteria'] as $kriteriaData) {
                    $grup->kriteria()->create($kriteriaData);
                }
            }

            foreach ($billing['keringanan'] ?? [] as $ruleData) {
                $jenisTagihan->keringananRules()->create($ruleData);
            }
        }

        $tarifBaru = $this->snapshotTarif($jenisTagihan->fresh());
        $keringananBaru = $this->snapshotKeringanan($jenisTagihan->fresh());

        return new SyncBillingConfigResult(
            tarifBerubah: $tarifLama !== $tarifBaru,
            keringananBerubah: $keringananLama !== $keringananBaru,
        );
    }

    private function snapshotTarif(JenisTagihan $jenisTagihan): string
    {
        $grups = $jenisTagihan->sasaranGrup()->where('tipe', 'tarif')->with('kriteria')->orderBy('priority')->get();

        return json_encode($grups->map(fn ($g) => [
            'nominal' => (float) $g->nominal,
            'priority' => $g->priority,
            'kriteria' => $g->kriteria->map(fn ($k) => ['field' => $k->field, 'operator' => $k->operator, 'value' => $k->value])->all(),
        ])->all());
    }

    private function snapshotKeringanan(JenisTagihan $jenisTagihan): string
    {
        $rules = $jenisTagihan->keringananRules()->orderBy('kategori_keringanan_id')->get();

        return json_encode($rules->map(fn ($r) => [
            'kategori_keringanan_id' => $r->kategori_keringanan_id,
            'tipe_potongan' => $r->tipe_potongan,
            'nilai' => (float) $r->nilai,
        ])->all());
    }
}
```

**Catatan**: `snapshotTarif()` sekarang juga menyertakan `priority` di kolom `create()` (mengisi `priority` berdasarkan urutan submit form, bukan lagi `NULL` seperti disebutkan di Task 6 Step 6) — ini menyelesaikan gap yang disebutkan di task itu: begitu form disubmit ulang lewat action ini, setiap grup Tarif baru langsung punya `priority` eksplisit sesuai urutan pengiriman form.

- [ ] **Step 5: Update the two call sites**

`app/Domains/Keuangan/Actions/JenisTagihan/CreateJenisTagihanAction.php` and `UpdateJenisTagihanAction.php` both call `$this->syncBillingConfig->execute($jenisTagihan, $data->billing);` and discard the result — no change needed to these two files YET (Task 12 will use the return value at the controller level, which calls `UpdateJenisTagihanAction::execute()`, which will need to bubble the result up — see Task 12 for that wiring). For THIS task, just confirm both files still compile/work with the new return type (a `void`-typed call site ignoring a return value is valid PHP, no syntax error) by running their existing tests.

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=SyncJenisTagihanBillingConfigActionDiffTest`
Expected: PASS (5 tests)

- [ ] **Step 7: Run existing regression**

Run: `php artisan test --filter='JenisTagihanTest|JenisTagihanFormTest|JenisTagihanBillingColumnsTest'`
Expected: PASS, unchanged.

- [ ] **Step 8: Commit**

```bash
git add app/Domains/Keuangan/Actions/JenisTagihan/SyncJenisTagihanBillingConfigAction.php app/Domains/Keuangan/DataTransferObjects/SyncBillingConfigResult.php tests/Feature/Keuangan/SyncJenisTagihanBillingConfigActionDiffTest.php
git commit -m "feat(keuangan): make SyncJenisTagihanBillingConfigAction diff-aware, report real changes only"
```

---

## Stage 7 — Wiring 4 Sumber Trigger (Tasks 9-11)

### Task 9: `RecalculateTagihanNominalJob` + trigger #2/#3 (Keringanan rule & Tarif nominal berubah)

**Files:**
- Create: `app/Jobs/RecalculateTagihanNominalJob.php`
- Modify: `app/Domains/Keuangan/Actions/JenisTagihan/UpdateJenisTagihanAction.php`
- Modify: `app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php` (`update()`)
- Test: `tests/Feature/Keuangan/RecalculateTagihanNominalJobDispatchTest.php`

**Interfaces:**
- Consumes: `SyncBillingConfigResult` (Task 8), `RecalculateTagihanNominalAction::execute(int $tagihanId)` (Task 4).
- Produces: `RecalculateTagihanNominalJob` (queued, constructor `int $tagihanId`).

**Ini adalah Job class PERTAMA di codebase ini** (dikonfirmasi via grep — tidak ada `implements ShouldQueue` selain di Listener yang justru sengaja TIDAK di-queue). `QUEUE_CONNECTION=database` sudah dikonfigurasi (`.env:38`) — job yang di-dispatch akan masuk tabel `jobs` dan butuh `php artisan queue:work` berjalan untuk benar-benar diproses; di test, gunakan `Queue::fake()` untuk assert dispatch tanpa benar-benar menjalankan worker.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\KategoriKeringanan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Jobs\RecalculateTagihanNominalJob;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('dispatches one job per affected unpaid tagihan when a keringanan rule nilai changes', function () {
    Queue::fake();

    $lembaga = \App\Models\Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);

    $siswaSatu = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswaDua = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'default_amount' => 300000]);
    $kategori = KategoriKeringanan::factory()->create(['lembaga_id' => $lembaga->id]);

    $tagihanSatu = Tagihan::factory()->create(['tagihable_type' => Siswa::class, 'tagihable_id' => $siswaSatu->id, 'jenis_tagihan_id' => $jenisTagihan->id, 'status' => 'belum_bayar']);
    $tagihanDua = Tagihan::factory()->create(['tagihable_type' => Siswa::class, 'tagihable_id' => $siswaDua->id, 'jenis_tagihan_id' => $jenisTagihan->id, 'status' => 'lunas']); // TIDAK boleh kena job

    $this->actingAs($admin)->putJson(route('admin.jenis-tagihan.update', $jenisTagihan), [
        'nama' => $jenisTagihan->nama, 'kategori' => 'spp', 'mode' => 'manual', 'tipe' => 'sekali',
        'keringanan' => [['kategori_keringanan_id' => $kategori->id, 'tipe_potongan' => 'fixed', 'nilai' => 50000]],
    ]);

    Queue::assertPushed(RecalculateTagihanNominalJob::class, 1);
    Queue::assertPushed(fn (RecalculateTagihanNominalJob $job) => $job->tagihanId === $tagihanSatu->id);
});

it('does NOT dispatch any job when the form is saved without touching tarif/keringanan at all', function () {
    Queue::fake();

    $lembaga = \App\Models\Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);

    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    Tagihan::factory()->create(['tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenisTagihan->id, 'status' => 'belum_bayar']);

    $this->actingAs($admin)->putJson(route('admin.jenis-tagihan.update', $jenisTagihan), [
        'nama' => 'Nama Baru Saja', 'kategori' => 'spp', 'mode' => 'manual', 'tipe' => 'sekali',
    ]);

    Queue::assertNotPushed(RecalculateTagihanNominalJob::class);
});
```

Read `app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php:171-202` (`update()`) in full first to confirm exact current structure before modifying.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=RecalculateTagihanNominalJobDispatchTest`
Expected: FAIL (job class doesn't exist, no dispatch wired)

- [ ] **Step 3: Create the job**

```php
<?php

namespace App\Jobs;

use App\Domains\Keuangan\Actions\Tagihan\RecalculateTagihanNominalAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecalculateTagihanNominalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $tagihanId)
    {
    }

    public function handle(RecalculateTagihanNominalAction $action): void
    {
        $action->execute($this->tagihanId);
    }
}
```

- [ ] **Step 4: Update `UpdateJenisTagihanAction` to return the sync result**

In `app/Domains/Keuangan/Actions/JenisTagihan/UpdateJenisTagihanAction.php`, change `execute()`'s return type from `JenisTagihan` to a small wrapper, OR simpler — keep returning `JenisTagihan` but stash the `SyncBillingConfigResult` as a public property the controller reads afterward. Simplest correct approach given this action already returns `$fresh` (a `JenisTagihan`): capture the sync result and attach it as a transient (non-persisted) property on the model before returning:

```php
public function execute(JenisTagihan $jenisTagihan, JenisTagihanData $data): JenisTagihan
{
    return DB::transaction(function () use ($jenisTagihan, $data) {
        $wasActive = (bool) $jenisTagihan->is_active;

        $syncResult = $this->syncBillingConfig->execute($jenisTagihan, $data->billing);

        $attributes = array_merge($data->attributes, [
            'nama' => $data->nama,
            'kategori' => $data->kategori,
            'bisa_dicicil' => $data->bisaDicicil,
            'maks_cicilan' => $data->maksCicilan,
        ]);

        $jenisTagihan->update($attributes);

        $fresh = $jenisTagihan->fresh();
        $fresh->syncBillingConfigResult = $syncResult; // properti transient, tidak masuk $fillable/kolom DB

        if (! $wasActive && (bool) $fresh->is_active) {
            event(new BillTypeActivated($fresh));
        }

        return $fresh;
    });
}
```

(Menambahkan properti dinamis ke model Eloquent seperti ini valid di PHP tapi memicu deprecation notice di PHP 8.2+ kalau model tidak `#[AllowDynamicProperties]` — cek versi PHP project ini (`composer.json`, sudah dikonfirmasi 8.3 di awal sesi) dan tambahkan `#[AllowDynamicProperties]` di atas class `JenisTagihan` KALAU muncul warning saat test dijalankan. Kalau ingin lebih bersih tanpa dynamic property, deklarasikan `public ?SyncBillingConfigResult $syncBillingConfigResult = null;` langsung di `JenisTagihan` model sebagai properti biasa (bukan kolom database) — pilih pendekatan ini sebagai yang lebih rapi, tambahkan baris itu di `JenisTagihan.php` di bawah deklarasi `protected $table`.)

- [ ] **Step 5: Wire the controller to dispatch jobs based on the sync result**

In `JenisTagihanController::update()`, after `$jenisTagihan = $action->execute($jenisTagihan, $dto);` (existing line), add:

```php
if ($jenisTagihan->syncBillingConfigResult?->tarifBerubah || $jenisTagihan->syncBillingConfigResult?->keringananBerubah) {
    Tagihan::where('jenis_tagihan_id', $jenisTagihan->id)
        ->whereNotIn('status', ['lunas', 'dibatalkan'])
        ->pluck('id')
        ->each(fn (int $tagihanId) => RecalculateTagihanNominalJob::dispatch($tagihanId));
}
```

Add `use App\Jobs\RecalculateTagihanNominalJob;` and `use App\Domains\Keuangan\Models\Tagihan;` imports.

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=RecalculateTagihanNominalJobDispatchTest`
Expected: PASS (2 tests)

- [ ] **Step 7: Run existing regression**

Run: `php artisan test --filter='JenisTagihanTest|JenisTagihanFormTest'`
Expected: PASS, unchanged.

- [ ] **Step 8: Commit**

```bash
git add app/Jobs/RecalculateTagihanNominalJob.php app/Domains/Keuangan/Actions/JenisTagihan/UpdateJenisTagihanAction.php app/Domains/Keuangan/Models/JenisTagihan.php app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php tests/Feature/Keuangan/RecalculateTagihanNominalJobDispatchTest.php
git commit -m "feat(keuangan): dispatch RecalculateTagihanNominalJob per affected tagihan when tarif/keringanan actually change"
```

---

### Task 10: Trigger #1 — `SiswaKeringanan` berubah (sinkron)

**Files:**
- Modify: `app/Http/Controllers/Lembaga/Keuangan/SiswaKeringananController.php` (`store()`, `destroy()`)
- Test: `tests/Feature/Keuangan/SiswaKeringananRecalcTriggerTest.php`

**Interfaces:**
- Consumes: `RecalculateTagihanNominalAction::execute(int $tagihanId)` (Task 4).

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\KategoriKeringanan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Pendaftaran;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('recalculates unpaid siswa tagihan synchronously when a keringanan is assigned', function () {
    $siswa = Siswa::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $siswa->lembaga_id]);
    $admin->assignRole('bendahara_lembaga');
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $siswa->lembaga_id, 'default_amount' => 300000]);
    $tagihan = Tagihan::factory()->create(['tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id, 'net_amount' => 300000, 'status' => 'belum_bayar']);
    $kategori = KategoriKeringanan::factory()->create(['lembaga_id' => $siswa->lembaga_id]);
    \App\Domains\Keuangan\Models\JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategori->id, 'tipe_potongan' => 'fixed', 'nilai' => 40000]);

    $this->actingAs($admin)->post(route('admin.siswa.keringanan.store', $siswa), [
        'kategori_keringanan_id' => $kategori->id, 'berlaku_dari' => now()->toDateString(),
    ]);

    expect((float) $tagihan->fresh()->net_amount)->toBe(260000.0);
});

it('does NOT touch a PPDB (Pendaftaran-tagihable) tagihan belonging to the same person', function () {
    $siswa = Siswa::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $siswa->lembaga_id]);
    $admin->assignRole('bendahara_lembaga');
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $siswa->lembaga_id]);
    $tagihanPpdb = Tagihan::factory()->create([
        'tagihable_type' => Pendaftaran::class, 'tagihable_id' => $pendaftaran->id, 'pendaftaran_id' => $pendaftaran->id,
        'person_id' => $siswa->person_id, 'net_amount' => 500000, 'status' => 'belum_bayar', 'kategori' => 'pendaftaran',
    ]);
    $kategori = KategoriKeringanan::factory()->create(['lembaga_id' => $siswa->lembaga_id]);

    $this->actingAs($admin)->post(route('admin.siswa.keringanan.store', $siswa), [
        'kategori_keringanan_id' => $kategori->id, 'berlaku_dari' => now()->toDateString(),
    ]);

    expect((float) $tagihanPpdb->fresh()->net_amount)->toBe(500000.0); // sama sekali tidak tersentuh
});
```

Read `app/Http/Controllers/Lembaga/Keuangan/SiswaKeringananController.php` in full first (confirmed earlier this session — `store()`/`destroy()` exist with tenant guards) before adding the trigger call.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=SiswaKeringananRecalcTriggerTest`
Expected: FAIL (no recalc happens on assign)

- [ ] **Step 3: Wire the trigger**

In `store()`, after `$siswa->siswaKeringanan()->create($validated);`, add:

```php
Tagihan::where('tagihable_type', Siswa::class)
    ->where('tagihable_id', $siswa->id)
    ->whereNotIn('status', ['lunas', 'dibatalkan'])
    ->pluck('id')
    ->each(fn (int $tagihanId) => $recalcAction->execute($tagihanId));
```

(inject `RecalculateTagihanNominalAction $recalcAction` as a `store(Request $request, Siswa $siswa, RecalculateTagihanNominalAction $recalcAction)` parameter). Add the identical block in `destroy()` after `$siswaKeringanan->delete();`, resolving `$siswa` from `$siswaKeringanan->siswa` first (read the file to confirm the exact variable available in that method's scope at that point).

Add `use App\Domains\Keuangan\Actions\Tagihan\RecalculateTagihanNominalAction;` and `use App\Domains\Keuangan\Models\Tagihan;` imports.

**Catatan yang WAJIB dipatuhi**: query di atas pakai `tagihable_type`+`tagihable_id` eksplisit, BUKAN `person_id` — inilah kenapa test kedua di atas ada, membuktikan tagihan PPDB milik `person_id` yang sama TIDAK ikut ke-recalc.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=SiswaKeringananRecalcTriggerTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Run existing regression**

Run: `php artisan test --filter=SiswaKeringananControllerTest`
Expected: PASS, unchanged.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Lembaga/Keuangan/SiswaKeringananController.php tests/Feature/Keuangan/SiswaKeringananRecalcTriggerTest.php
git commit -m "feat(keuangan): trigger synchronous recalc when SiswaKeringanan is assigned or revoked"
```

---

### Task 11: Trigger #4 — `ReorderTarifGrupAction` + endpoint

**Files:**
- Create: `app/Domains/Keuangan/Actions/JenisTagihan/ReorderTarifGrupAction.php`
- Modify: `app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php` (tambah method `reorderTarif`)
- Modify: `routes/admin/keuangan.php`
- Test: `tests/Feature/Keuangan/ReorderTarifGrupActionTest.php`

**Interfaces:**
- Consumes: `RecalculateTagihanNominalJob::dispatch(int $tagihanId)` (Task 9).
- Produces: route `PATCH admin/jenis-tagihan/{jenisTagihan}/tarif-grup/reorder`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Domains\Keuangan\Actions\JenisTagihan\ReorderTarifGrupAction;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Jobs\RecalculateTagihanNominalJob;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('updates priority for each grup id in the given order, scoped to the correct jenis_tagihan', function () {
    $jenisTagihan = JenisTagihan::factory()->create();
    $grupA = $jenisTagihan->sasaranGrup()->create(['tipe' => 'tarif', 'nominal' => 100000, 'priority' => 1]);
    $grupB = $jenisTagihan->sasaranGrup()->create(['tipe' => 'tarif', 'nominal' => 200000, 'priority' => 2]);

    app(ReorderTarifGrupAction::class)->execute($jenisTagihan, [$grupB->id, $grupA->id]);

    expect($grupB->fresh()->priority)->toBe(1);
    expect($grupA->fresh()->priority)->toBe(2);
});

it('dispatches one recalc job per unpaid tagihan for that jenis_tagihan directly, without going through form submit', function () {
    Queue::fake();

    $lembaga = \App\Models\Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $grupA = $jenisTagihan->sasaranGrup()->create(['tipe' => 'tarif', 'nominal' => 100000, 'priority' => 1]);
    $grupB = $jenisTagihan->sasaranGrup()->create(['tipe' => 'tarif', 'nominal' => 200000, 'priority' => 2]);
    $tagihan = Tagihan::factory()->create(['tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id, 'status' => 'belum_bayar']);

    $this->actingAs($admin)->patchJson(route('admin.jenis-tagihan.tarif-grup.reorder', $jenisTagihan), [
        'urutan_grup_id' => [$grupB->id, $grupA->id],
    ]);

    Queue::assertPushed(RecalculateTagihanNominalJob::class, 1);
    Queue::assertPushed(fn (RecalculateTagihanNominalJob $job) => $job->tagihanId === $tagihan->id);
});

it('rejects a grup id belonging to a different jenis_tagihan (tenant guard)', function () {
    $jenisTagihanA = JenisTagihan::factory()->create();
    $jenisTagihanB = JenisTagihan::factory()->create();
    $grupLain = $jenisTagihanB->sasaranGrup()->create(['tipe' => 'tarif', 'nominal' => 100000, 'priority' => 1]);

    app(ReorderTarifGrupAction::class)->execute($jenisTagihanA, [$grupLain->id]);

    expect($grupLain->fresh()->jenis_tagihan_id)->toBe($jenisTagihanB->id); // tidak berubah kepemilikan, update() dengan where jenis_tagihan_id filter tidak match apapun
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ReorderTarifGrupActionTest`
Expected: FAIL (class/route don't exist)

- [ ] **Step 3: Write the action**

```php
<?php

namespace App\Domains\Keuangan\Actions\JenisTagihan;

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\JenisTagihanSasaranGrup;
use App\Domains\Keuangan\Models\Tagihan;
use App\Jobs\RecalculateTagihanNominalJob;
use Illuminate\Support\Facades\DB;

class ReorderTarifGrupAction
{
    public function execute(JenisTagihan $jenisTagihan, array $urutanGrupId): void
    {
        DB::transaction(function () use ($jenisTagihan, $urutanGrupId) {
            foreach ($urutanGrupId as $index => $grupId) {
                JenisTagihanSasaranGrup::where('id', $grupId)
                    ->where('jenis_tagihan_id', $jenisTagihan->id)
                    ->update(['priority' => $index + 1]);
            }
        });

        Tagihan::where('jenis_tagihan_id', $jenisTagihan->id)
            ->whereNotIn('status', ['lunas', 'dibatalkan'])
            ->pluck('id')
            ->each(fn (int $tagihanId) => RecalculateTagihanNominalJob::dispatch($tagihanId));
    }
}
```

- [ ] **Step 4: Add the controller method and route**

In `JenisTagihanController.php`:

```php
public function reorderTarif(Request $request, JenisTagihan $jenisTagihan, ReorderTarifGrupAction $action): JsonResponse
{
    $this->authorize('jenis-tagihan.edit');

    $data = $request->validate(['urutan_grup_id' => ['required', 'array']]);

    $action->execute($jenisTagihan, $data['urutan_grup_id']);

    return response()->json(['message' => 'Urutan prioritas Tarif berhasil disimpan.']);
}
```

In `routes/admin/keuangan.php`:
```php
Route::patch('jenis-tagihan/{jenisTagihan}/tarif-grup/reorder', [JenisTagihanController::class, 'reorderTarif'])->name('jenis-tagihan.tarif-grup.reorder');
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=ReorderTarifGrupActionTest`
Expected: PASS (3 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Keuangan/Actions/JenisTagihan/ReorderTarifGrupAction.php app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php routes/admin/keuangan.php tests/Feature/Keuangan/ReorderTarifGrupActionTest.php
git commit -m "feat(keuangan): add ReorderTarifGrupAction, dispatches recalc directly outside form submit"
```

---

## Stage 8 — Notifikasi, Audit Trail, Badge Counter, Halaman Tinjau (Tasks 12-14)

### Task 12: `TagihanDirevisiNotification` + wiring dari `RecalculateTagihanNominalAction`

**Files:**
- Create: `app/Notifications/Finance/TagihanDirevisiNotification.php`
- Modify: `app/Domains/Keuangan/Actions/Tagihan/RecalculateTagihanNominalAction.php`
- Test: `tests/Feature/Keuangan/TagihanDirevisiNotificationTest.php`

**Interfaces:**
- Consumes: `NotificationDispatcher::send()` (existing), `FinanceNotification` (existing abstract base).

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Domains\Keuangan\Actions\Tagihan\RecalculateTagihanNominalAction;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\JenisTagihanKeringanan;
use App\Domains\Keuangan\Models\KategoriKeringanan;
use App\Domains\Keuangan\Models\SiswaKeringanan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Notifications\Finance\TagihanDirevisiNotification;
use Illuminate\Support\Facades\Notification;

it('sends TagihanDirevisiNotification to the kontak utama orang tua when net_amount actually changes', function () {
    Notification::fake();

    $siswa = Siswa::factory()->create();
    $orangTua = OrangTua::factory()->create();
    $siswa->orangTua()->attach($orangTua->id, ['is_kontak_utama' => true]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $siswa->lembaga_id, 'default_amount' => 300000]);
    $tagihan = Tagihan::factory()->create(['tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id, 'net_amount' => 300000, 'status' => 'belum_bayar']);
    $kategori = KategoriKeringanan::factory()->create(['lembaga_id' => $siswa->lembaga_id]);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategori->id, 'tipe_potongan' => 'fixed', 'nilai' => 50000]);
    SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategori->id, 'berlaku_dari' => now()->subDay()->toDateString()]);

    app(RecalculateTagihanNominalAction::class)->execute($tagihan->id);

    Notification::assertSentTo($orangTua, TagihanDirevisiNotification::class);
});

it('does not send any notification when recalc results in the exact same net_amount', function () {
    Notification::fake();

    $siswa = Siswa::factory()->create();
    $orangTua = OrangTua::factory()->create();
    $siswa->orangTua()->attach($orangTua->id, ['is_kontak_utama' => true]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $siswa->lembaga_id, 'default_amount' => 300000]);
    $tagihan = Tagihan::factory()->create(['tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id, 'total_tagihan' => 300000, 'net_amount' => 300000, 'status' => 'belum_bayar']);

    app(RecalculateTagihanNominalAction::class)->execute($tagihan->id); // tidak ada keringanan sama sekali -> hasil resolve sama

    Notification::assertNothingSentTo($orangTua);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=TagihanDirevisiNotificationTest`
Expected: FAIL (notification class doesn't exist, not wired)

- [ ] **Step 3: Create the notification**

Read `app/Notifications/Finance/TagihanDiterbitkanNotification.php` in full first to copy its exact structure. Create:

```php
<?php

namespace App\Notifications\Finance;

use App\Domains\Keuangan\Models\Tagihan;
use Illuminate\Notifications\Messages\MailMessage;

class TagihanDirevisiNotification extends FinanceNotification
{
    public function __construct(public Tagihan $tagihan, public float $netAmountLama)
    {
    }

    public function isUrgent(): bool
    {
        return false;
    }

    public function via(object $notifiable): array
    {
        return $this->baseChannels();
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'tagihan_id' => $this->tagihan->id,
            'message' => "Tagihan {$this->tagihan->jenisTagihan?->nama} direvisi: Rp".number_format($this->netAmountLama, 0, ',', '.')." -> Rp".number_format((float) $this->tagihan->net_amount, 0, ',', '.').'.',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Tagihan Direvisi')
            ->line("Tagihan {$this->tagihan->jenisTagihan?->nama} telah direvisi.")
            ->line('Nominal lama: Rp'.number_format($this->netAmountLama, 0, ',', '.'))
            ->line('Nominal baru: Rp'.number_format((float) $this->tagihan->net_amount, 0, ',', '.'));
    }

    public function toWhatsApp(object $notifiable): ?string
    {
        return \App\Domains\Keuangan\Models\WhatsAppTemplate::renderKode('tagihan_direvisi', [
            'jenis_tagihan' => $this->tagihan->jenisTagihan?->nama ?? '',
            'net_amount_lama' => number_format($this->netAmountLama, 0, ',', '.'),
            'net_amount_baru' => number_format((float) $this->tagihan->net_amount, 0, ',', '.'),
        ]);
    }
}
```

Confirm `WhatsAppTemplate`'s exact namespace first (`grep -n "class WhatsAppTemplate" app/ -r`) — adjust the `use`/FQCN if it's not under `App\Domains\Keuangan\Models`. This notification's WhatsApp template `tagihan_direvisi` doesn't exist yet in `WhatsAppTemplateSeeder` — add a new seeder entry there too, following the exact pattern of the existing `tagihan_baru` entry (`database/seeders/WhatsAppTemplateSeeder.php`).

- [ ] **Step 4: Wire dispatch into `RecalculateTagihanNominalAction`**

In `app/Domains/Keuangan/Actions/Tagihan/RecalculateTagihanNominalAction.php`, inject `NotificationDispatcher` via constructor, capture `$netAmountLama = (float) $tagihan->net_amount;` BEFORE mutating the model, and after the successful `$tagihan->save();` in the success branch:

```php
if ($netAmountLama !== $newNetAmount) {
    $kontakUtama = $siswa->orangTua()->wherePivot('is_kontak_utama', true)->first();
    if ($kontakUtama !== null) {
        try {
            $this->dispatcher->send($kontakUtama, new TagihanDirevisiNotification($tagihan->fresh(), $netAmountLama));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Gagal mengirim TagihanDirevisiNotification: '.$e->getMessage());
        }
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=TagihanDirevisiNotificationTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Notifications/Finance/TagihanDirevisiNotification.php app/Domains/Keuangan/Actions/Tagihan/RecalculateTagihanNominalAction.php database/seeders/WhatsAppTemplateSeeder.php tests/Feature/Keuangan/TagihanDirevisiNotificationTest.php
git commit -m "feat(keuangan): notify parents when a recalculated net_amount actually changes"
```

---

### Task 13: Activitylog + badge counter

**Files:**
- Modify: `app/Domains/Keuangan/Models/Tagihan.php` (`getActivitylogOptions()`)
- Modify: `resources/views/layouts/topbar.blade.php`
- Modify: `app/Http/Controllers/Admin/DashboardController.php` (atau file yang menyuplai data ke `topbar.blade.php` — cek dulu bagaimana `$unreadCount` disuplai)
- Test: `tests/Feature/Keuangan/TagihanActivitylogAndBadgeTest.php`

**Interfaces:**
- Produces: badge count query `Tagihan::withoutGlobalScope(TenantScope::class)->where('lembaga_id', $lembagaId)->where('perlu_ditinjau_ulang', true)->count()` — exact scoping confirmed at implementation time by checking how `Tagihan` resolves its own `lembaga_id` (via `jenisTagihan.lembaga_id` or a direct column — check schema).

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Domains\Keuangan\Models\Tagihan;

it('logs net_amount, discount_amount, discount_type, perlu_ditinjau_ulang, and alasan_perlu_ditinjau changes', function () {
    $tagihan = Tagihan::factory()->create(['net_amount' => 300000, 'perlu_ditinjau_ulang' => false]);

    $tagihan->update(['net_amount' => 250000, 'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'contoh']);

    $activity = $tagihan->activities()->latest()->first();
    expect($activity->changes['attributes'])->toHaveKeys(['net_amount', 'perlu_ditinjau_ulang', 'alasan_perlu_ditinjau']);
});

it('shows a badge count of tagihan perlu_ditinjau_ulang scoped to the acting lembaga', function () {
    $lembagaSatu = \App\Models\Lembaga::factory()->create();
    $lembagaDua = \App\Models\Lembaga::factory()->create();
    $admin = \App\Models\User::factory()->create(['lembaga_id' => $lembagaSatu->id]);
    $admin->assignRole('bendahara_lembaga');
    (new \Database\Seeders\RolePermissionSeeder)->run();

    Tagihan::factory()->create(['lembaga_id' => $lembagaSatu->id, 'perlu_ditinjau_ulang' => true]);
    Tagihan::factory()->create(['lembaga_id' => $lembagaSatu->id, 'perlu_ditinjau_ulang' => true]);
    Tagihan::factory()->create(['lembaga_id' => $lembagaDua->id, 'perlu_ditinjau_ulang' => true]); // lembaga lain, tidak boleh terhitung

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertSee('2'); // badge count utk lembagaSatu saja
});
```

Read `resources/views/layouts/topbar.blade.php` in full first (confirmed earlier this session — `$unreadCount` at line 11, badge markup at line 91) and find where `$unreadCount` is actually computed/passed (likely a View Composer or middleware, `grep -rn "unreadCount" app/`) to replicate the exact same wiring mechanism for the new badge, rather than guessing.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=TagihanActivitylogAndBadgeTest`
Expected: FAIL (activitylog doesn't track new fields, badge doesn't exist)

- [ ] **Step 3: Update `Tagihan::getActivitylogOptions()`**

Change (`Tagihan.php:90-96`):
```php
LogOptions::defaults()->logOnly(['status', 'total_tagihan', 'net_amount', 'discount_amount', 'discount_type', 'perlu_ditinjau_ulang', 'alasan_perlu_ditinjau'])->logOnlyDirty()->useLogName('tagihan');
```

- [ ] **Step 4: Add the badge**

Wire `$perluDitinjauCount` the same way `$unreadCount` is wired (whatever mechanism Step 2's grep revealed — View Composer, middleware, or controller-level `view()->share()`). Compute it as:
```php
$perluDitinjauCount = Tagihan::where('lembaga_id', $lembagaId)->where('perlu_ditinjau_ulang', true)->count();
```
(confirm `Tagihan` actually has a direct `lembaga_id` column by checking `database/schema/mysql-schema.sql` — if it doesn't, resolve lembaga via `jenisTagihan.lembaga_id` or `tagihable`'s own lembaga, whichever the schema actually supports; do not guess).

Add a badge to `topbar.blade.php` near a new "Tagihan Perlu Ditinjau" nav link, following the EXACT visual pattern of the existing unread-notification badge (line 91): `<span x-show="..." class="absolute -right-0.5 -top-0.5 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white">{{ $perluDitinjauCount }}</span>`.

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=TagihanActivitylogAndBadgeTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Keuangan/Models/Tagihan.php resources/views/layouts/topbar.blade.php tests/Feature/Keuangan/TagihanActivitylogAndBadgeTest.php
git commit -m "feat(keuangan): log recalc-relevant field changes, show perlu_ditinjau_ulang badge in topbar"
```

(File tempat badge count benar-benar dihitung/di-share ke view akan bergantung pada temuan grep di Step 2 — sesuaikan path `Modify:` di header task ini begitu ditemukan, sebelum commit.)

---

### Task 14: Halaman "Tagihan Perlu Ditinjau"

**Files:**
- Modify: `app/Http/Controllers/Lembaga/Keuangan/TagihanController.php` (tambah method `perluDitinjau`)
- Create: `resources/views/portals/lembaga/keuangan/tagihan/perlu-ditinjau.blade.php`
- Modify: `routes/admin/keuangan.php`
- Test: `tests/Feature/Keuangan/TagihanPerluDitinjauPageTest.php`

**Interfaces:**
- Consumes: `SelesaikanTinjauanTagihanAction` (Task 5).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Keuangan\Models\Tagihan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('lists only tagihan with perlu_ditinjau_ulang=true, scoped to the acting lembaga, with a selesai-ditinjau button', function () {
    $lembaga = \App\Models\Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    $flagged = Tagihan::factory()->create(['lembaga_id' => $lembaga->id, 'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'Alasan uji']);
    Tagihan::factory()->create(['lembaga_id' => $lembaga->id, 'perlu_ditinjau_ulang' => false]);

    $response = $this->actingAs($admin)->get(route('admin.tagihan.perlu-ditinjau'));

    $response->assertOk();
    $response->assertSee('Alasan uji');
    $response->assertSee(route('admin.tagihan.selesai-ditinjau', $flagged), false);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TagihanPerluDitinjauPageTest`
Expected: FAIL (route/view don't exist)

- [ ] **Step 3: Add controller method, route, and view**

```php
public function perluDitinjau(): View
{
    $this->authorize('tagihan.edit');

    $tagihanList = Tagihan::with(['jenisTagihan', 'tagihable'])
        ->where('perlu_ditinjau_ulang', true)
        ->latest('updated_at')
        ->paginate(20);

    return view('portals.lembaga.keuangan.tagihan.perlu-ditinjau', compact('tagihanList'));
}
```

Route: `Route::get('tagihan/perlu-ditinjau', [TagihanController::class, 'perluDitinjau'])->name('tagihan.perlu-ditinjau');` (register BEFORE the existing `tagihan/{tagihan}` routes if any share a prefix ambiguity — check existing route order in the file first).

View — a simple table listing `$tagihanList`, each row showing siswa/jenisTagihan/`alasan_perlu_ditinjau`, with a form `POST` to `route('admin.tagihan.selesai-ditinjau', $tagihan)`. Follow this project's existing table-list Blade conventions (check `resources/views/portals/lembaga/keuangan/jenis-tagihan/index.blade.php` for the pattern this codebase already uses for paginated admin tables).

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=TagihanPerluDitinjauPageTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Lembaga/Keuangan/TagihanController.php resources/views/portals/lembaga/keuangan/tagihan/perlu-ditinjau.blade.php routes/admin/keuangan.php tests/Feature/Keuangan/TagihanPerluDitinjauPageTest.php
git commit -m "feat(keuangan): add halaman Tagihan Perlu Ditinjau with selesai-ditinjau action"
```

---

## Stage 9 — Live Preview + Widget Assignment Keringanan (Tasks 15-17)

### Task 15: Endpoint preview Target Sasaran

**Files:**
- Modify: `app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php`
- Modify: `routes/admin/keuangan.php`
- Test: `tests/Feature/Keuangan/JenisTagihanPreviewSasaranTest.php`

**Interfaces:**
- Produces: `POST admin/jenis-tagihan/preview-sasaran`, menerima payload `sasaran` draft (belum tersimpan), mengembalikan `{count: int}`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('previews how many siswa match a draft sasaran without saving anything', function () {
    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);
    Siswa::factory()->count(3)->create(['lembaga_id' => $lembaga->id, 'status' => 'aktif']);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'lulus']);

    $response = $this->actingAs($admin)->postJson(route('admin.jenis-tagihan.preview-sasaran'), [
        'sasaran' => [['kriteria' => [['field' => 'status_siswa', 'operator' => 'in', 'value' => ['aktif']]]]],
    ]);

    $response->assertOk();
    $response->assertJson(['count' => 3]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=JenisTagihanPreviewSasaranTest`
Expected: FAIL (route doesn't exist)

- [ ] **Step 3: Implement the endpoint**

`JenisTagihanSasaranMatcher::resolveTargetSiswa()` takes a `JenisTagihan` model (persisted, with real `sasaranGrup` relations), so previewing a DRAFT (unsaved) `sasaran` payload needs a lightweight in-memory variant. Add a new method to the controller that builds a throwaway, unsaved `JenisTagihan` + `JenisTagihanSasaranGrup`/`JenisTagihanSasaranKriteria` instances (never call `->save()`), then reuse `siswaMatchesJenisTagihan()` (the PHP-side matcher, not the SQL-side query) against every Siswa in the lembaga:

```php
public function previewSasaran(Request $request, JenisTagihanSasaranMatcher $matcher): JsonResponse
{
    $this->authorize('jenis-tagihan.create');

    $lembagaId = $this->resolveLembagaIdOrFail($request);
    $data = $request->validate(['sasaran' => ['nullable', 'array']]);

    $draftJenisTagihan = new JenisTagihan(['lembaga_id' => $lembagaId]);
    $draftGrups = collect($data['sasaran'] ?? [])->map(function ($grupData) {
        $grup = new JenisTagihanSasaranGrup(['tipe' => 'sasaran']);
        $grup->setRelation('kriteria', collect($grupData['kriteria'] ?? [])->map(fn ($k) => new JenisTagihanSasaranKriteria($k)));

        return $grup;
    });
    $draftJenisTagihan->setRelation('sasaranGrup', $draftGrups);

    $count = Siswa::where('lembaga_id', $lembagaId)
        ->get()
        ->filter(fn (Siswa $siswa) => $matcher->siswaMatchesJenisTagihan($siswa, $draftJenisTagihan))
        ->count();

    return response()->json(['count' => $count]);
}
```

**Catatan**: `siswaMatchesJenisTagihan()` membaca `$jenisTagihan->sasaranGrup` (relasi) — `setRelation()` mengisi relasi itu secara in-memory tanpa query database, persis yang dibutuhkan untuk draft yang belum tersimpan. Konfirmasi ulang `siswaMatchesJenisTagihan()`'s exact internal access pattern (`$jenisTagihan->sasaranGrup` vs `$jenisTagihan->sasaranGrup()`) di `JenisTagihanSasaranMatcher.php` sebelum finalisasi — kalau method itu memanggil relasi sebagai method call (query ulang), pendekatan `setRelation()` tidak akan terpakai dan draft kriteria tidak akan terbaca; kalau begitu, sesuaikan `siswaMatchesJenisTagihan()`/`siswaMatchesGrup()` supaya menerima `Collection` grup sebagai parameter langsung alih-alih membaca relasi dari model (perubahan kecil, cek dulu baris kodenya).

Route: `Route::post('jenis-tagihan/preview-sasaran', [JenisTagihanController::class, 'previewSasaran'])->name('jenis-tagihan.preview-sasaran');`

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=JenisTagihanPreviewSasaranTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php routes/admin/keuangan.php tests/Feature/Keuangan/JenisTagihanPreviewSasaranTest.php
git commit -m "feat(keuangan): add live preview endpoint for draft Target Sasaran kriteria"
```

---

### Task 16: Endpoint preview Tarif + Keringanan (live counter)

**Files:**
- Modify: `app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php`
- Modify: `routes/admin/keuangan.php`
- Test: `tests/Feature/Keuangan/JenisTagihanPreviewKeringananTest.php`

**Interfaces:**
- Produces: `POST admin/jenis-tagihan/preview-keringanan`, menerima `kategori_keringanan_id`, mengembalikan `{count: int}` — jumlah siswa DI LEMBAGA INI yang saat ini punya `SiswaKeringanan` aktif untuk kategori itu (tidak perlu draft-siswa-matching seperti Task 15, karena assignment keringanan bersifat data existing, bukan draft kriteria).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\User;
use App\Domains\Keuangan\Models\KategoriKeringanan;
use App\Domains\Keuangan\Models\SiswaKeringanan;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('counts siswa currently assigned an active kategori keringanan', function () {
    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    $kategori = KategoriKeringanan::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswaAktif = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    SiswaKeringanan::create(['siswa_id' => $siswaAktif->id, 'kategori_keringanan_id' => $kategori->id, 'berlaku_dari' => now()->subDay()->toDateString()]);
    $siswaExpired = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    SiswaKeringanan::create(['siswa_id' => $siswaExpired->id, 'kategori_keringanan_id' => $kategori->id, 'berlaku_dari' => now()->subMonths(2)->toDateString(), 'berlaku_sampai' => now()->subMonth()->toDateString()]);

    $response = $this->actingAs($admin)->postJson(route('admin.jenis-tagihan.preview-keringanan'), [
        'kategori_keringanan_id' => $kategori->id,
    ]);

    $response->assertOk();
    $response->assertJson(['count' => 1]); // hanya siswaAktif, siswaExpired sudah lewat berlaku_sampai
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=JenisTagihanPreviewKeringananTest`
Expected: FAIL

- [ ] **Step 3: Implement the endpoint**

```php
public function previewKeringanan(Request $request): JsonResponse
{
    $this->authorize('jenis-tagihan.create');

    $data = $request->validate(['kategori_keringanan_id' => ['required', 'integer']]);
    $today = now()->toDateString();

    $count = SiswaKeringanan::where('kategori_keringanan_id', $data['kategori_keringanan_id'])
        ->where('berlaku_dari', '<=', $today)
        ->where(fn ($q) => $q->whereNull('berlaku_sampai')->orWhere('berlaku_sampai', '>=', $today))
        ->count();

    return response()->json(['count' => $count]);
}
```

Add `use App\Domains\Keuangan\Models\SiswaKeringanan;` import. Route: `Route::post('jenis-tagihan/preview-keringanan', [JenisTagihanController::class, 'previewKeringanan'])->name('jenis-tagihan.preview-keringanan');`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=JenisTagihanPreviewKeringananTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php routes/admin/keuangan.php tests/Feature/Keuangan/JenisTagihanPreviewKeringananTest.php
git commit -m "feat(keuangan): add live counter endpoint for keringanan category assignment"
```

---

### Task 17: Widget assignment Keringanan langsung di form + UI live preview

**Files:**
- Modify: `resources/views/portals/lembaga/keuangan/jenis-tagihan/form.blade.php`
- Modify: `resources/js/jenis-tagihan-form.js`
- Test: `tests/Feature/Admin/JenisTagihanFormKeringananWidgetTest.php`

**Interfaces:**
- Consumes: `SiswaKeringananController::store()`/`destroy()` (existing, unmodified), preview endpoints (Tasks 15-16).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\KategoriKeringanan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('renders a keringanan assignment widget with siswa checkboxes wired to the existing SiswaKeringananController endpoints', function () {
    $lembaga = \App\Models\Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = \App\Models\Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    KategoriKeringanan::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Yatim Piatu']);

    $response = $this->actingAs($admin)->get(route('admin.jenis-tagihan.edit', $jenisTagihan));

    $response->assertOk();
    $response->assertSee(route('admin.siswa.keringanan.store', $siswa), false);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=JenisTagihanFormKeringananWidgetTest`
Expected: FAIL (widget doesn't exist yet)

- [ ] **Step 3: Add the widget markup**

In the Keringanan card of `form.blade.php` (found via `grep -n "Keringanan & Potongan Biaya" resources/views/portals/lembaga/keuangan/jenis-tagihan/form.blade.php`), after the existing per-rule inputs, add a collapsible sub-panel listing siswa matching the current draft `sasaran` (fetched via Task 15's preview endpoint on the client side), with a checkbox per siswa per kategori keringanan defined in `form.keringanan`. Each checkbox toggle calls a new Alpine method `toggleSiswaKeringanan(siswaId, kategoriKeringananId, isChecked)` in `jenis-tagihan-form.js` that does:

```js
async toggleSiswaKeringanan(siswaId, kategoriKeringananId, isChecked) {
    const url = isChecked
        ? `/admin/siswa/${siswaId}/keringanan`
        : `/admin/siswa-keringanan/${this.siswaKeringananIdFor(siswaId, kategoriKeringananId)}`;

    await fetch(url, {
        method: isChecked ? 'POST' : 'DELETE',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: isChecked ? JSON.stringify({ kategori_keringanan_id: kategoriKeringananId, berlaku_dari: new Date().toISOString().slice(0, 10) }) : undefined,
    });
}
```

(`siswaKeringananIdFor()` perlu data awal id `SiswaKeringanan` per siswa+kategori yang dilewatkan lewat `@js()` config, mirip pola `initialKeringanan` yang sudah ada — kalau siswa itu belum punya assignment, checkbox unchecked dan `siswaKeringananIdFor()` tidak pernah dipanggil karena aksi DELETE hanya terjadi saat un-checking sesuatu yang sudah checked.)

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=JenisTagihanFormKeringananWidgetTest`
Expected: PASS

- [ ] **Step 5: Manual browser verification (wajib per CLAUDE.md project ini)**

`npm run build`, buka halaman edit Jenis Tagihan di browser, uji: centang/hilangkan centang keringanan untuk siswa, konfirmasi request AJAX ke endpoint `SiswaKeringananController` benar-benar berhasil (cek halaman edit Siswa yang bersangkutan menunjukkan assignment yang sama setelah reload) — MEMBUKTIKAN data global, bukan duplikat, tersinkron dari 2 pintu masuk.

- [ ] **Step 6: Commit**

```bash
git add resources/views/portals/lembaga/keuangan/jenis-tagihan/form.blade.php resources/js/jenis-tagihan-form.js tests/Feature/Admin/JenisTagihanFormKeringananWidgetTest.php
git commit -m "feat(keuangan): embed keringanan assignment widget directly in Jenis Tagihan form"
```

---

## Final Step: Full Test Suite

- [ ] Run: `php artisan test --compact`
- [ ] Expected: PASS, 0 failures.
- [ ] Run `vendor/bin/pint --dirty --format agent` and commit any formatting-only fixes separately.

**Plan complete when this full-suite run and Pint pass are both clean.**
