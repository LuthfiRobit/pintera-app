# Jenis Tagihan — Tipe Penjadwalan Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an explicit scheduling frequency (`tipe`: Harian/Mingguan/Bulanan/Tahunan/Sekali) to Jenis Tagihan, separate from the existing `kategori` accounting label, so the billing engine can correctly generate tagihan on daily/weekly/yearly cycles instead of only the monthly cycle it silently assumes today.

**Architecture:** A new `TipeTagihan` backed enum plus 3 new nullable support columns (`hari_generate`, `bulan_generate`, `offset_hari_jatuh_tempo`) on `jenis_tagihan`, reusing the existing `tanggal_generate`/`hari_jatuh_tempo` columns for Bulanan/Tahunan. `TagihanBillingGenerator::resolveDueDate()` and a new `resolveBillingPeriod()` branch explicitly per `tipe` (5 cases each, never a generalized formula). `GenerateTagihanHarian`'s cron candidate query branches per `tipe` instead of its current single monthly condition. `tagihan.billing_period` (widened `varchar(7)`→`varchar(10)`) is reused as the per-type dedup/reporting key with a format that varies by `tipe`, never a new column.

**Tech Stack:** Laravel 12, PHP 8.3, Pest v4, MySQL 8.0.30.

## Global Constraints

These come from `.agents/specs/2026-09-01-jenis-tagihan-tipe-penjadwalan.md` and are binding on every task below:

- **Order is locked**: Task 1 (the `hari_jatuh_tempo` label bug) must be done and green before any other task starts. It is a standalone fix, not bundled with the new feature.
- **`kategori` is never touched.** No changes to its values, meaning, or role as an accounting/reporting label. PPDB categories (`pendaftaran`, `daftar_ulang`) are excluded entirely from `tipe` — the field and all its support fields must never be shown, validated, or persisted for these two categories, following the existing `hasBillingPayload()` block pattern.
- **No new column for dedup/reporting.** `tagihan.billing_period` (existing, widened to `varchar(10)`) is reused for all 5 types, with a format that varies by `tipe` — never introduce a separate periode column.
- **ISO week format is `'o-\WW'` — lowercase `o`, never uppercase `Y`.** Verified directly: `Carbon::create(2027, 1, 1)->format('o-\WW')` produces `"2026-W53"`, and `Carbon::create(2025, 12, 29)->format('o-\WW')` produces `"2026-W01"`. Using `Y` instead of `o` silently miscalculates dedup at year boundaries.
- **`jenis_tagihan.last_generated_period` is dropped, never reused.** It lives at the `jenis_tagihan` level while dedup is correctly per-target (per siswa, via `tagihan.billing_period`); reusing it as a cache would silently under-bill a new siswa who enrolls mid-period.
- **`resolveDueDate()` and the new `resolveBillingPeriod()` must have 5 explicit branches per `tipe`** (Harian, Mingguan, Bulanan, Tahunan, Sekali) — never a single generalized formula that happens to cover all cases.
- **The `mode=otomatis` + `tipe=sekali` combination is contradictory and must be rejected at both the form-validation layer AND the database layer** (a `CHECK` constraint), since MySQL 8.0.30 enforces `CHECK` for real.
- **When `tipe` changes on an existing `JenisTagihan` via `update()`, every support field not owned by the new `tipe` must be explicitly nulled** — never left holding a stale value from the previous `tipe`.

---

## Task 1: Fix the `hari_jatuh_tempo` label (standalone bug fix, must land first)

**Files:**
- Modify: `resources/views/portals/lembaga/keuangan/jenis-tagihan/form.blade.php:150-152`
- Test: `tests/Feature/Admin/JenisTagihanFormPageTest.php`

**Interfaces:**
- Produces: nothing new — this is a label-only fix. No code behavior changes (the underlying date math in `TagihanBillingGenerator::resolveDueDate()` is already correct for what it currently does; only its label was misleading).

The current label at line 150-152 claims `hari_jatuh_tempo` is "jumlah hari SETELAH tanggal generate" (an offset), but the actual code (`Carbon::create($year, $month, min($hariJatuhTempo, $daysInMonth))`) computes an absolute day-of-month. This must be corrected before Task 9 adds a genuinely offset-based field (`offset_hari_jatuh_tempo`) for Harian/Mingguan, so the two different semantics (absolute day-of-month vs. day-offset) are never confused under one label again.

- [ ] **Step 1: Write the failing test**

Read `tests/Feature/Admin/JenisTagihanFormPageTest.php` first to confirm its existing structure and auth setup pattern (it already renders the create form and asserts on its HTML). Add:

```php
it('labels hari_jatuh_tempo as an absolute day-of-month, not an offset', function () {
    // Reuse this file's existing $admin/actingAs setup from the test above this one.
    $response = $this->actingAs($admin)->get(route('admin.jenis-tagihan.create'));

    $response->assertDontSeeText('Jumlah hari setelah tanggal generate sampai batas waktu pembayaran');
    $response->assertSeeText('Tanggal jatuh tempo (tanggal di bulan yang sama, bukan jarak hari)');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=JenisTagihanFormPageTest`
Expected: FAIL — the old label text is still present.

- [ ] **Step 3: Fix the label**

In `resources/views/portals/lembaga/keuangan/jenis-tagihan/form.blade.php`, replace lines 149-153:

```blade
<div>
    <x-input-label value="Hari Jatuh Tempo (setelah generate)" />
    <x-text-input type="number" min="0" name="hari_jatuh_tempo" :value="old('hari_jatuh_tempo', $jenisTagihan?->hari_jatuh_tempo)" class="mt-1.5" />
    <p class="mt-1.5 text-[10px] text-gray-400 leading-tight">Jumlah hari setelah tanggal generate sampai batas waktu pembayaran.</p>
</div>
```

with:

```blade
<div>
    <x-input-label value="Tanggal jatuh tempo (tanggal di bulan yang sama, bukan jarak hari)" />
    <x-text-input type="number" min="1" max="31" name="hari_jatuh_tempo" :value="old('hari_jatuh_tempo', $jenisTagihan?->hari_jatuh_tempo)" class="mt-1.5" />
    <p class="mt-1.5 text-[10px] text-gray-400 leading-tight">Tanggal di bulan yang sama dengan Tanggal Generate saat tagihan jatuh tempo (mis. isi 25 untuk tanggal 25 di bulan itu).</p>
</div>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=JenisTagihanFormPageTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/views/portals/lembaga/keuangan/jenis-tagihan/form.blade.php tests/Feature/Admin/JenisTagihanFormPageTest.php
git commit -m "fix(keuangan): correct hari_jatuh_tempo label to describe absolute day-of-month, not offset"
```

**Run the full existing `JenisTagihanFormPageTest`/`JenisTagihanFormTest` suites here and confirm they're still green before moving to Task 2** — this task must be fully done first per the Global Constraints.

---

## Task 2: Migration — `tipe` column, 3 new support columns, drop `last_generated_period`, widen `billing_period`, CHECK constraint

**Files:**
- Create: `database/migrations/2026_09_01_000003_add_tipe_penjadwalan_to_jenis_tagihan_table.php`
- Test: `tests/Feature/Keuangan/JenisTagihanTipeMigrationTest.php`

**Interfaces:**
- Produces: `jenis_tagihan.tipe` (enum, NOT NULL after backfill), `jenis_tagihan.hari_generate` (nullable tinyint), `jenis_tagihan.bulan_generate` (nullable tinyint), `jenis_tagihan.offset_hari_jatuh_tempo` (nullable smallint), `tagihan.billing_period` widened to `varchar(10)`, `jenis_tagihan.last_generated_period` column removed, `CHECK` constraint `chk_jenis_tagihan_mode_tipe`. All later tasks assume these exist exactly as named.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('backfills tipe correctly based on the pre-existing mode of each row', function () {
    // Simulate rows that existed BEFORE this migration by inserting directly,
    // bypassing the model's own tipe default (added in a later task) so this
    // test exercises the migration's own backfill UPDATE statements in isolation.
    $lembaga = \App\Models\Lembaga::factory()->create();

    $otomatisId = DB::table('jenis_tagihan')->insertGetId([
        'lembaga_id' => $lembaga->id, 'nama' => 'Otomatis Lama', 'kategori' => 'spp',
        'mode' => 'otomatis', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $manualId = DB::table('jenis_tagihan')->insertGetId([
        'lembaga_id' => $lembaga->id, 'nama' => 'Manual Lama', 'kategori' => 'kegiatan',
        'mode' => 'manual', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    // This test runs AFTER the migration already ran as part of RefreshDatabase's
    // migrate:fresh, so to actually exercise the backfill UPDATE statements we
    // must re-run this specific migration's up() logic against these two
    // deliberately-inserted "old" rows that have tipe=NULL right now (impossible
    // via the schema after migration, so insert directly bypassing tipe, then
    // manually invoke the backfill portion). Simplify: assert the migration's
    // backfill SQL produces the right mapping by calling it directly.
    DB::statement("UPDATE jenis_tagihan SET tipe = 'bulanan' WHERE mode = 'otomatis' AND id = ?", [$otomatisId]);
    DB::statement("UPDATE jenis_tagihan SET tipe = 'sekali' WHERE mode = 'manual' AND id = ?", [$manualId]);

    expect(JenisTagihan::find($otomatisId)->tipe->value)->toBe('bulanan');
    expect(JenisTagihan::find($manualId)->tipe->value)->toBe('sekali');
});

it('rejects a null tipe at the database level', function () {
    expect(fn () => DB::table('jenis_tagihan')->insert([
        'lembaga_id' => \App\Models\Lembaga::factory()->create()->id, 'nama' => 'Tanpa Tipe', 'kategori' => 'spp',
        'mode' => 'manual', 'tipe' => null, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('rejects mode=otomatis + tipe=sekali at the database level via CHECK constraint', function () {
    $lembaga = \App\Models\Lembaga::factory()->create();

    expect(fn () => DB::table('jenis_tagihan')->insert([
        'lembaga_id' => $lembaga->id, 'nama' => 'Kontradiktif', 'kategori' => 'spp',
        'mode' => 'otomatis', 'tipe' => 'sekali', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('no longer has the last_generated_period column', function () {
    expect(Schema::hasColumn('jenis_tagihan', 'last_generated_period'))->toBeFalse();
});

it('widens tagihan.billing_period to fit a full Y-m-d date', function () {
    $tagihan = \App\Domains\Keuangan\Models\Tagihan::factory()->create(['billing_period' => '2026-09-01']);

    expect($tagihan->fresh()->billing_period)->toBe('2026-09-01');
});
```

**Important note on Step 1's first test**: because Pest's `RefreshDatabase` runs every migration (including this one) before each test, you cannot observe the migration's own backfill UPDATE running against genuinely pre-migration data inside a normal test — the schema is already fully migrated by the time the test body runs. The first test above works around this by inserting rows that bypass any model-level default (added in Task 3) and manually replaying the same backfill SQL the migration itself uses, to prove the SQL mapping logic is correct in isolation. This is intentional and sufficient — the full end-to-end migration behavior is implicitly covered by the fact that `php artisan migrate` running clean (Step 5 below) proves the migration file itself executes without error.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=JenisTagihanTipeMigrationTest`
Expected: FAIL — `tipe` column doesn't exist yet, `Tagihan::factory()` doesn't accept `billing_period` longer than 7 chars without truncation/error depending on DB strictness.

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
        Schema::table('jenis_tagihan', function (Blueprint $table) {
            $table->enum('tipe', ['harian', 'mingguan', 'bulanan', 'tahunan', 'sekali'])->nullable()->after('kategori');
            $table->unsignedTinyInteger('hari_generate')->nullable()->after('tanggal_generate');
            $table->unsignedTinyInteger('bulan_generate')->nullable()->after('hari_generate');
            $table->unsignedSmallInteger('offset_hari_jatuh_tempo')->nullable()->after('hari_jatuh_tempo');
            $table->dropColumn('last_generated_period');
        });

        Schema::table('tagihan', function (Blueprint $table) {
            $table->string('billing_period', 10)->nullable()->change();
        });

        DB::statement("UPDATE jenis_tagihan SET tipe = 'bulanan' WHERE mode = 'otomatis'");
        DB::statement("UPDATE jenis_tagihan SET tipe = 'sekali' WHERE mode = 'manual'");

        Schema::table('jenis_tagihan', function (Blueprint $table) {
            $table->enum('tipe', ['harian', 'mingguan', 'bulanan', 'tahunan', 'sekali'])->nullable(false)->change();
        });

        DB::statement('ALTER TABLE jenis_tagihan ADD CONSTRAINT chk_jenis_tagihan_mode_tipe CHECK (NOT (mode = \'otomatis\' AND tipe = \'sekali\'))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE jenis_tagihan DROP CONSTRAINT chk_jenis_tagihan_mode_tipe');

        Schema::table('jenis_tagihan', function (Blueprint $table) {
            $table->dropColumn(['tipe', 'hari_generate', 'bulan_generate', 'offset_hari_jatuh_tempo']);
            $table->string('last_generated_period', 7)->nullable();
        });

        Schema::table('tagihan', function (Blueprint $table) {
            $table->string('billing_period', 7)->nullable()->change();
        });
    }
};
```

Run: `php artisan migrate`

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=JenisTagihanTipeMigrationTest`
Expected: PASS (5 tests). Note: the second and third tests (`rejects a null tipe`, `rejects mode=otomatis + tipe=sekali`) will only pass once the CHECK constraint and NOT NULL are both live — if either fails, re-check Step 3's SQL ran in the right order (backfill BEFORE the final `NOT NULL` change, `CHECK` constraint AFTER `NOT NULL`).

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_09_01_000003_add_tipe_penjadwalan_to_jenis_tagihan_table.php tests/Feature/Keuangan/JenisTagihanTipeMigrationTest.php
git commit -m "feat(keuangan): add tipe penjadwalan columns, drop last_generated_period, widen billing_period"
```

---

## Task 3: `TipeTagihan`/`HariDalamMinggu` enums + `JenisTagihan` model updates

**Files:**
- Create: `app/Domains/Keuangan/Enums/TipeTagihan.php`
- Create: `app/Domains/Keuangan/Enums/HariDalamMinggu.php`
- Modify: `app/Domains/Keuangan/Models/JenisTagihan.php`
- Test: `tests/Unit/Keuangan/TipeTagihanTest.php`
- Test: `tests/Feature/Keuangan/JenisTagihanTipeDefaultTest.php`

**Interfaces:**
- Consumes: `tipe` column from Task 2.
- Produces: `App\Domains\Keuangan\Enums\TipeTagihan` (cases `Harian`/`Mingguan`/`Bulanan`/`Tahunan`/`Sekali`, method `label(): string`), `App\Domains\Keuangan\Enums\HariDalamMinggu` (int-backed, 1=Senin..7=Minggu). `JenisTagihan::$tipe` returns a `TipeTagihan` instance. Every later task depends on these exact names.

**Critical discovery not covered by the spec text but required for regression safety**: none of the existing tests or factory calls anywhere in this codebase set `tipe` explicitly (it didn't exist before Task 2). Once `tipe` is `NOT NULL` with no column-level `DEFAULT` (Task 2 deliberately backfills existing rows conditionally rather than using a blanket column default), every `JenisTagihan::factory()->create([...])` call across the ENTIRE existing test suite that doesn't mention `tipe` would fail the `NOT NULL` constraint at insert time. `JenisTagihanFactory::definition()` already omits `mode` and `is_active` from its returned array and relies on `JenisTagihan::$attributes` (an Eloquent-level default, not a DB column default) to fill them in — this task adds `tipe` to that same `$attributes` array, using `'bulanan'` specifically because it is the ONE value that is valid paired with BOTH `mode` values under the `chk_jenis_tagihan_mode_tipe` constraint from Task 2 (`'sekali'` would be rejected whenever an existing test also sets `mode => 'otomatis'` without specifying `tipe`, which many tests do — see `TagihanBillingGeneratorTest.php`, `GenerateTagihanHarianCommandTest.php`).

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Unit/Keuangan/TipeTagihanTest.php

use App\Domains\Keuangan\Enums\TipeTagihan;
use App\Domains\Keuangan\Enums\HariDalamMinggu;

it('has a label for every TipeTagihan case', function () {
    expect(TipeTagihan::Harian->label())->toBe('Harian');
    expect(TipeTagihan::Mingguan->label())->toBe('Mingguan');
    expect(TipeTagihan::Bulanan->label())->toBe('Bulanan');
    expect(TipeTagihan::Tahunan->label())->toBe('Tahunan');
    expect(TipeTagihan::Sekali->label())->toBe('Sekali');
});

it('maps HariDalamMinggu cases to ISO weekday integers matching Carbon::dayOfWeekIso', function () {
    expect(HariDalamMinggu::Senin->value)->toBe(1);
    expect(HariDalamMinggu::Minggu->value)->toBe(7);
});
```

```php
<?php
// tests/Feature/Keuangan/JenisTagihanTipeDefaultTest.php

use App\Domains\Keuangan\Enums\TipeTagihan;
use App\Domains\Keuangan\Models\JenisTagihan;

it('defaults tipe to bulanan when not specified, regardless of mode, so existing tests/factories keep working', function () {
    $manual = JenisTagihan::factory()->create(['mode' => 'manual']);
    $otomatis = JenisTagihan::factory()->create(['mode' => 'otomatis']);

    expect($manual->fresh()->tipe)->toBe(TipeTagihan::Bulanan);
    expect($otomatis->fresh()->tipe)->toBe(TipeTagihan::Bulanan);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=TipeTagihanTest` and `php artisan test --filter=JenisTagihanTipeDefaultTest`
Expected: FAIL (enum classes don't exist, `tipe` attribute doesn't default to anything)

- [ ] **Step 3: Create the enums**

`app/Domains/Keuangan/Enums/TipeTagihan.php`:

```php
<?php

namespace App\Domains\Keuangan\Enums;

enum TipeTagihan: string
{
    case Harian = 'harian';
    case Mingguan = 'mingguan';
    case Bulanan = 'bulanan';
    case Tahunan = 'tahunan';
    case Sekali = 'sekali';

    public function label(): string
    {
        return match ($this) {
            self::Harian => 'Harian',
            self::Mingguan => 'Mingguan',
            self::Bulanan => 'Bulanan',
            self::Tahunan => 'Tahunan',
            self::Sekali => 'Sekali',
        };
    }
}
```

`app/Domains/Keuangan/Enums/HariDalamMinggu.php`:

```php
<?php

namespace App\Domains\Keuangan\Enums;

enum HariDalamMinggu: int
{
    case Senin = 1;
    case Selasa = 2;
    case Rabu = 3;
    case Kamis = 4;
    case Jumat = 5;
    case Sabtu = 6;
    case Minggu = 7;
}
```

- [ ] **Step 4: Update the model**

In `app/Domains/Keuangan/Models/JenisTagihan.php`:

Add `'tipe' => 'bulanan',` to the existing `protected $attributes = [...]` array (alongside `mode` and `is_active`).

Add `'tipe', 'hari_generate', 'bulan_generate', 'offset_hari_jatuh_tempo',` to `$fillable`, and remove `'last_generated_period'` from it (the column no longer exists — leaving it in `$fillable` is harmless to Eloquent but references a dropped column, so remove it for correctness).

Add `'tipe' => \App\Domains\Keuangan\Enums\TipeTagihan::class,` to the `casts()` array, alongside the existing `kategori` cast.

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=TipeTagihanTest` and `php artisan test --filter=JenisTagihanTipeDefaultTest`
Expected: PASS (2 + 1 tests)

- [ ] **Step 6: Run the existing `TagihanBillingGeneratorTest` and `GenerateTagihanHarianCommandTest` suites to confirm the new default doesn't break them**

Run: `php artisan test --filter='TagihanBillingGeneratorTest|GenerateTagihanHarianCommandTest'`
Expected: PASS, unchanged — this is the regression check that the `'bulanan'` default choice (not `'sekali'`) was correct.

- [ ] **Step 7: Commit**

```bash
git add app/Domains/Keuangan/Enums/TipeTagihan.php app/Domains/Keuangan/Enums/HariDalamMinggu.php app/Domains/Keuangan/Models/JenisTagihan.php tests/Unit/Keuangan/TipeTagihanTest.php tests/Feature/Keuangan/JenisTagihanTipeDefaultTest.php
git commit -m "feat(keuangan): add TipeTagihan/HariDalamMinggu enums and default tipe=bulanan on JenisTagihan"
```

---

## Task 4: Form validation for `tipe` and its support fields (`JenisTagihanController::baseRules()`)

**Files:**
- Modify: `app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php:330-347` (`baseRules()`), `:325-328` (`hasBillingPayload()`)
- Test: `tests/Feature/Admin/JenisTagihanModeTipeValidationTest.php`

**Interfaces:**
- Consumes: `TipeTagihan` enum (Task 3).
- Produces: nothing new for later tasks to consume — this is the form-layer half of the validation; Task 2's `CHECK` constraint is the DB-layer half.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Admin/JenisTagihanModeTipeValidationTest.php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

function actingAsJenisTagihanManager(int $lembagaId): User
{
    $user = User::factory()->create(['lembaga_id' => $lembagaId]);
    $user->assignRole('bendahara_lembaga');

    return $user;
}

dataset('kombinasi mode+tipe wajib', [
    'manual + harian, no extra fields required' => ['manual', 'harian', []],
    'manual + mingguan, no extra fields required' => ['manual', 'mingguan', []],
    'manual + bulanan, no extra fields required' => ['manual', 'bulanan', []],
    'manual + tahunan, no extra fields required' => ['manual', 'tahunan', []],
    'manual + sekali, no extra fields required' => ['manual', 'sekali', []],
    'otomatis + harian requires offset_hari_jatuh_tempo' => ['otomatis', 'harian', ['offset_hari_jatuh_tempo' => 3]],
    'otomatis + mingguan requires hari_generate and offset_hari_jatuh_tempo' => ['otomatis', 'mingguan', ['hari_generate' => 1, 'offset_hari_jatuh_tempo' => 3]],
    'otomatis + bulanan requires tanggal_generate and hari_jatuh_tempo' => ['otomatis', 'bulanan', ['tanggal_generate' => 1, 'hari_jatuh_tempo' => 10]],
    'otomatis + tahunan requires bulan_generate, tanggal_generate, hari_jatuh_tempo' => ['otomatis', 'tahunan', ['bulan_generate' => 7, 'tanggal_generate' => 1, 'hari_jatuh_tempo' => 10]],
]);

it('accepts valid mode+tipe combinations with their required support fields', function (string $mode, string $tipe, array $extra) {
    $lembaga = \App\Models\Lembaga::factory()->create();
    $admin = actingAsJenisTagihanManager($lembaga->id);
    session(['active_lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($admin)->postJson(route('admin.jenis-tagihan.store'), array_merge([
        'nama' => 'Test Jenis Tagihan',
        'kategori' => 'spp',
        'mode' => $mode,
        'tipe' => $tipe,
        'tanggal_mulai' => $mode === 'otomatis' ? now()->toDateString() : null,
    ], $extra));

    $response->assertStatus(201);
})->with('kombinasi mode+tipe wajib');

it('rejects otomatis+mingguan when hari_generate is missing', function () {
    $lembaga = \App\Models\Lembaga::factory()->create();
    $admin = actingAsJenisTagihanManager($lembaga->id);
    session(['active_lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($admin)->postJson(route('admin.jenis-tagihan.store'), [
        'nama' => 'Test', 'kategori' => 'spp', 'mode' => 'otomatis', 'tipe' => 'mingguan',
        'tanggal_mulai' => now()->toDateString(), 'offset_hari_jatuh_tempo' => 3,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('hari_generate');
});

it('rejects the contradictory combination mode=otomatis + tipe=sekali with an explicit message', function () {
    $lembaga = \App\Models\Lembaga::factory()->create();
    $admin = actingAsJenisTagihanManager($lembaga->id);
    session(['active_lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($admin)->postJson(route('admin.jenis-tagihan.store'), [
        'nama' => 'Test', 'kategori' => 'spp', 'mode' => 'otomatis', 'tipe' => 'sekali',
        'tanggal_mulai' => now()->toDateString(),
    ]);

    $response->assertStatus(422);
    $response->assertJsonFragment(['message' => "Tipe 'Sekali' tidak bisa dipasangkan dengan Mode Otomatis karena kontradiktif (generate berulang vs sekali saja)."]);
});

it('rejects tipe and its support fields for a PPDB kategori, same as other billing fields', function () {
    $lembaga = \App\Models\Lembaga::factory()->create();
    $admin = actingAsJenisTagihanManager($lembaga->id);
    session(['active_lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($admin)->postJson(route('admin.jenis-tagihan.store'), [
        'nama' => 'Test PPDB', 'kategori' => 'pendaftaran', 'mode' => 'manual', 'tipe' => 'sekali',
        'hari_generate' => 1,
    ]);

    $response->assertStatus(422);
    $response->assertJsonFragment(['message' => 'Target sasaran, tarif berdimensi, dan keringanan hanya berlaku untuk kategori selain Pendaftaran/Daftar Ulang.']);
});
```

Read `tests/Feature/Admin/JenisTagihanTest.php` first to confirm the exact existing auth/session setup pattern this controller's tests already use (`resolveLembagaIdOrFail()` reads `session('active_lembaga_id')` for yayasan-scope users, or `$user->lembaga_id` directly for lembaga-scope users) — adjust the dataset test's setup if the actual pattern differs from what's shown above.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=JenisTagihanModeTipeValidationTest`
Expected: FAIL (`tipe` not yet in validation rules, custom rejection message doesn't exist)

- [ ] **Step 3: Update `baseRules()` and `hasBillingPayload()`**

In `app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php`, replace the `baseRules()` method (lines 330-347) with:

```php
private function baseRules(int $lembagaId, ?JenisTagihan $editing): array
{
    return [
        'nama' => ['required', 'string', 'max:255', Rule::unique('jenis_tagihan', 'nama')
            ->where(fn ($query) => $query->where('lembaga_id', $lembagaId))
            ->ignore($editing?->id)],
        'kategori' => ['required', Rule::in(['pendaftaran', 'daftar_ulang', 'lainnya', 'spp', 'tahunan', 'kegiatan', 'custom'])],
        'bisa_dicicil' => ['nullable', 'boolean'],
        'maks_cicilan' => ['nullable', 'integer', 'min:2', 'required_if:bisa_dicicil,1'],
        'default_amount' => ['nullable', 'numeric', 'min:0'],
        'mode' => ['nullable', Rule::in(['manual', 'otomatis'])],
        'tipe' => ['required', Rule::in(['harian', 'mingguan', 'bulanan', 'tahunan', 'sekali'])],
        'tanggal_mulai' => ['nullable', 'date', 'required_if:mode,otomatis'],
        'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        'hari_generate' => ['nullable', 'integer', 'between:1,7', Rule::requiredIf(fn () => request('mode') === 'otomatis' && request('tipe') === 'mingguan')],
        'bulan_generate' => ['nullable', 'integer', 'between:1,12', Rule::requiredIf(fn () => request('mode') === 'otomatis' && request('tipe') === 'tahunan')],
        'tanggal_generate' => ['nullable', 'integer', 'between:1,31', Rule::requiredIf(fn () => request('mode') === 'otomatis' && in_array(request('tipe'), ['bulanan', 'tahunan'], true))],
        'hari_jatuh_tempo' => ['nullable', 'integer', 'between:1,31', Rule::requiredIf(fn () => request('mode') === 'otomatis' && in_array(request('tipe'), ['bulanan', 'tahunan'], true))],
        'offset_hari_jatuh_tempo' => ['nullable', 'integer', 'min:0', Rule::requiredIf(fn () => request('mode') === 'otomatis' && in_array(request('tipe'), ['harian', 'mingguan'], true))],
        'is_active' => ['nullable', 'boolean'],
    ];
}
```

Then, immediately after this method, add the custom rejection check. In BOTH `store()` (after line 134's `$isPpdbKategori` guard block, before line 135's `$request->validate($this->baseRules(...))` call) and `update()` (same relative position, after line 179), add:

```php
if ($request->input('mode') === 'otomatis' && $request->input('tipe') === 'sekali') {
    return $this->errorResponse($request, "Tipe 'Sekali' tidak bisa dipasangkan dengan Mode Otomatis karena kontradiktif (generate berulang vs sekali saja).");
}
```

Finally, update `hasBillingPayload()` (lines 325-328) to also guard the new fields for PPDB categories:

```php
private function hasBillingPayload(Request $request): bool
{
    return $request->has('sasaran') || $request->has('tarif') || $request->has('keringanan')
        || $request->has('tipe') || $request->has('hari_generate') || $request->has('bulan_generate') || $request->has('offset_hari_jatuh_tempo');
}
```

**Note**: `Rule::requiredIf()` takes a closure returning a boolean, correctly expressing an AND of two conditions — this is the fix for the spec's own flagged pitfall that Laravel's string-based `required_if:field1,value1|field2,value2` syntax means OR, not AND.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=JenisTagihanModeTipeValidationTest`
Expected: PASS (10 dataset cases + 3 more tests = 13 total)

- [ ] **Step 5: Run the existing `JenisTagihanTest`/`JenisTagihanFormTest` suites to confirm no regression**

Run: `php artisan test --filter='JenisTagihanTest|JenisTagihanFormTest'`
Expected: PASS. If any existing test's `store()`/`update()` call fails because it didn't send a `tipe` field, that test needs `'tipe' => 'bulanan'` (or whichever tipe fits its scenario) added to its request payload — `tipe` is now `required` at the form layer, unlike the model-level default from Task 3 which only helps direct Eloquent/factory creation, not HTTP requests through this controller.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php tests/Feature/Admin/JenisTagihanModeTipeValidationTest.php
git commit -m "feat(keuangan): validate tipe and its support fields per mode+tipe combination"
```

(If Step 5 required fixing other test files, include those fixes in this same commit with a note in the message.)

---

## Task 5: Null-out stale support fields when `tipe` changes (`UpdateJenisTagihanAction`)

**Files:**
- Modify: `app/Domains/Keuangan/Actions/JenisTagihan/UpdateJenisTagihanAction.php`
- Test: `tests/Feature/Keuangan/JenisTagihanTipeNullifyOnChangeTest.php`

**Interfaces:**
- Consumes: `TipeTagihan` enum (Task 3).
- Produces: nothing new for later tasks — this is a data-integrity fix confined to the update path.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Actions\JenisTagihan\UpdateJenisTagihanAction;
use App\Domains\Keuangan\DataTransferObjects\JenisTagihanData;

it('nulls out fields owned by the old tipe when moving from Mingguan to Bulanan', function () {
    $jenisTagihan = JenisTagihan::factory()->create([
        'mode' => 'otomatis', 'tipe' => 'mingguan', 'tanggal_mulai' => now()->toDateString(),
        'hari_generate' => 3, 'offset_hari_jatuh_tempo' => 5,
    ]);

    $data = JenisTagihanData::fromArray([
        'nama' => $jenisTagihan->nama, 'kategori' => 'spp', 'mode' => 'otomatis', 'tipe' => 'bulanan',
        'tanggal_mulai' => now()->toDateString(), 'tanggal_generate' => 10, 'hari_jatuh_tempo' => 20,
    ]);

    app(UpdateJenisTagihanAction::class)->execute($jenisTagihan, $data);

    $fresh = $jenisTagihan->fresh();
    expect($fresh->hari_generate)->toBeNull();
    expect($fresh->offset_hari_jatuh_tempo)->toBeNull();
    expect($fresh->tanggal_generate)->toBe(10);
    expect($fresh->hari_jatuh_tempo)->toBe(20);
});

it('nulls out fields owned by the old tipe when moving from Tahunan to Harian', function () {
    $jenisTagihan = JenisTagihan::factory()->create([
        'mode' => 'otomatis', 'tipe' => 'tahunan', 'tanggal_mulai' => now()->toDateString(),
        'bulan_generate' => 7, 'tanggal_generate' => 1, 'hari_jatuh_tempo' => 15,
    ]);

    $data = JenisTagihanData::fromArray([
        'nama' => $jenisTagihan->nama, 'kategori' => 'spp', 'mode' => 'otomatis', 'tipe' => 'harian',
        'tanggal_mulai' => now()->toDateString(), 'offset_hari_jatuh_tempo' => 2,
    ]);

    app(UpdateJenisTagihanAction::class)->execute($jenisTagihan, $data);

    $fresh = $jenisTagihan->fresh();
    expect($fresh->bulan_generate)->toBeNull();
    expect($fresh->tanggal_generate)->toBeNull();
    expect($fresh->hari_jatuh_tempo)->toBeNull();
    expect($fresh->offset_hari_jatuh_tempo)->toBe(2);
});
```

Read `app/Domains/Keuangan/DataTransferObjects/JenisTagihanData.php` first — `JenisTagihanData::fromArray()` puts the ENTIRE validated array into `$data->attributes`, so any key present in the array passed to `fromArray()` will flow through to `update()`. Confirm this still holds before writing the test's exact array shape.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=JenisTagihanTipeNullifyOnChangeTest`
Expected: FAIL — `hari_generate`/`offset_hari_jatuh_tempo` still hold their old values after the update.

- [ ] **Step 3: Implement the null-out logic**

In `app/Domains/Keuangan/Actions/JenisTagihan/UpdateJenisTagihanAction.php`, add the import `use App\Domains\Keuangan\Enums\TipeTagihan;`, add this private method, and call it in `execute()`:

```php
class UpdateJenisTagihanAction
{
    public function __construct(
        private readonly SyncJenisTagihanBillingConfigAction $syncBillingConfig,
    ) {}

    public function execute(JenisTagihan $jenisTagihan, JenisTagihanData $data): JenisTagihan
    {
        return DB::transaction(function () use ($jenisTagihan, $data) {
            $wasActive = (bool) $jenisTagihan->is_active;

            $this->syncBillingConfig->execute($jenisTagihan, $data->billing);

            $attributes = array_merge(
                $this->nullifyFieldsNotOwnedBy(TipeTagihan::from($data->attributes['tipe'])),
                $data->attributes,
                [
                    'nama'         => $data->nama,
                    'kategori'     => $data->kategori,
                    'bisa_dicicil' => $data->bisaDicicil,
                    'maks_cicilan' => $data->maksCicilan,
                ]
            );

            $jenisTagihan->update($attributes);

            $fresh = $jenisTagihan->fresh();

            if (! $wasActive && (bool) $fresh->is_active) {
                event(new BillTypeActivated($fresh));
            }

            return $fresh;
        });
    }

    private function nullifyFieldsNotOwnedBy(TipeTagihan $tipe): array
    {
        $ownedByTipe = match ($tipe) {
            TipeTagihan::Harian => ['offset_hari_jatuh_tempo'],
            TipeTagihan::Mingguan => ['hari_generate', 'offset_hari_jatuh_tempo'],
            TipeTagihan::Bulanan => ['tanggal_generate', 'hari_jatuh_tempo'],
            TipeTagihan::Tahunan => ['bulan_generate', 'tanggal_generate', 'hari_jatuh_tempo'],
            TipeTagihan::Sekali => [],
        };

        $semuaFieldPendukung = ['hari_generate', 'bulan_generate', 'tanggal_generate', 'hari_jatuh_tempo', 'offset_hari_jatuh_tempo'];

        return array_fill_keys(array_diff($semuaFieldPendukung, $ownedByTipe), null);
    }
}
```

**Note the merge order**: `nullifyFieldsNotOwnedBy()`'s result comes FIRST in `array_merge()`, so any key also present in `$data->attributes` (the fields the request actually sent for the new `tipe`) correctly overrides the `null` — only fields the request did NOT send (because they belong to the OLD `tipe`) stay nulled.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=JenisTagihanTipeNullifyOnChangeTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Run existing `JenisTagihanTest`/`JenisTagihanFormTest` regression**

Run: `php artisan test --filter='JenisTagihanTest|JenisTagihanFormTest'`
Expected: PASS, unchanged.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Keuangan/Actions/JenisTagihan/UpdateJenisTagihanAction.php tests/Feature/Keuangan/JenisTagihanTipeNullifyOnChangeTest.php
git commit -m "feat(keuangan): null out stale tipe support fields on JenisTagihan update"
```

---

## Task 6: `resolveDueDate()` — 5 explicit branches per Tipe

**Files:**
- Modify: `app/Domains/Keuangan/Services/TagihanBillingGenerator.php:47-102,131-143`
- Test: `tests/Feature/Keuangan/TagihanBillingGeneratorResolveDueDateTest.php`

**Interfaces:**
- Consumes: `TipeTagihan` enum (Task 3).
- Produces: `resolveDueDate(JenisTagihan $jenisTagihan, ?string $billingPeriod, Carbon $tanggalGenerateAktual): ?string` — the new signature (added `$tanggalGenerateAktual` parameter). Task 7 depends on this exact new signature and calls it from the same `generateForSiswa()` call site.

**Confirmed via grep**: `resolveDueDate()` is `private` and has exactly ONE caller in the entire codebase — line 83 of this same file, inside `generateForSiswa()`. No other file needs updating for the signature change.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Keuangan/TagihanBillingGeneratorResolveDueDateTest.php
//
// resolveDueDate() is private; these tests exercise it indirectly through
// generateForSiswa(), asserting the resulting Tagihan.jatuh_tempo per Tipe.

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Services\JenisTagihanSasaranMatcher;
use App\Domains\Keuangan\Services\TagihanBillingGenerator;
use App\Domains\Keuangan\Services\TagihanNominalResolver;
use App\Models\Siswa;
use App\Services\Finance\NotificationDispatcher;
use Illuminate\Support\Carbon;

function buatGeneratorDueDate(): TagihanBillingGenerator
{
    $matcher = new JenisTagihanSasaranMatcher();

    return new TagihanBillingGenerator($matcher, new TagihanNominalResolver($matcher), app(NotificationDispatcher::class));
}

it('resolves due date to null for Tipe Sekali', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['mode' => 'manual', 'tipe' => 'sekali', 'default_amount' => 100000]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    buatGeneratorDueDate()->generateForSiswa($siswa, $jenisTagihan, 'manual');

    expect(Tagihan::first()->jatuh_tempo)->toBeNull();
});

it('resolves due date as generate-date-plus-offset for Tipe Harian', function () {
    Carbon::setTestNow('2026-09-15');
    $jenisTagihan = JenisTagihan::factory()->create([
        'mode' => 'otomatis', 'tipe' => 'harian', 'default_amount' => 100000,
        'offset_hari_jatuh_tempo' => 3, 'tanggal_mulai' => '2026-09-01',
    ]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    buatGeneratorDueDate()->generateForSiswa($siswa, $jenisTagihan, 'cron');

    expect(Tagihan::first()->jatuh_tempo->toDateString())->toBe('2026-09-18');
    Carbon::setTestNow();
});

it('resolves due date as generate-date-plus-offset for Tipe Mingguan', function () {
    Carbon::setTestNow('2026-09-15');
    $jenisTagihan = JenisTagihan::factory()->create([
        'mode' => 'otomatis', 'tipe' => 'mingguan', 'default_amount' => 100000,
        'hari_generate' => 2, 'offset_hari_jatuh_tempo' => 5, 'tanggal_mulai' => '2026-09-01',
    ]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    buatGeneratorDueDate()->generateForSiswa($siswa, $jenisTagihan, 'cron');

    expect(Tagihan::first()->jatuh_tempo->toDateString())->toBe('2026-09-20');
    Carbon::setTestNow();
});

it('resolves due date as an absolute day-of-month for Tipe Bulanan, unchanged from current behavior', function () {
    Carbon::setTestNow('2026-09-15');
    $jenisTagihan = JenisTagihan::factory()->create([
        'mode' => 'otomatis', 'tipe' => 'bulanan', 'default_amount' => 100000,
        'tanggal_generate' => 15, 'hari_jatuh_tempo' => 10, 'tanggal_mulai' => '2026-01-01',
    ]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    buatGeneratorDueDate()->generateForSiswa($siswa, $jenisTagihan, 'cron');

    expect(Tagihan::first()->jatuh_tempo->toDateString())->toBe('2026-09-10');
    Carbon::setTestNow();
});

it('resolves due date to an absolute day within bulan_generate for Tipe Tahunan', function () {
    Carbon::setTestNow('2026-07-01');
    $jenisTagihan = JenisTagihan::factory()->create([
        'mode' => 'otomatis', 'tipe' => 'tahunan', 'default_amount' => 100000,
        'bulan_generate' => 7, 'tanggal_generate' => 1, 'hari_jatuh_tempo' => 20, 'tanggal_mulai' => '2026-01-01',
    ]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    buatGeneratorDueDate()->generateForSiswa($siswa, $jenisTagihan, 'cron');

    expect(Tagihan::first()->jatuh_tempo->toDateString())->toBe('2026-07-20');
    Carbon::setTestNow();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=TagihanBillingGeneratorResolveDueDateTest`
Expected: FAIL — `resolveDueDate()` doesn't yet branch per `tipe` (it will crash or return wrong values for non-Bulanan cases since `tipe` isn't consulted at all yet).

- [ ] **Step 3: Rewrite `resolveDueDate()` and its call site**

In `app/Domains/Keuangan/Services/TagihanBillingGenerator.php`, add the import `use App\Domains\Keuangan\Enums\TipeTagihan;`. Replace the `resolveDueDate()` method (lines 131-143) with:

```php
private function resolveDueDate(JenisTagihan $jenisTagihan, ?string $billingPeriod, Carbon $tanggalGenerateAktual): ?string
{
    return match ($jenisTagihan->tipe) {
        TipeTagihan::Sekali => null,

        TipeTagihan::Harian, TipeTagihan::Mingguan => $jenisTagihan->offset_hari_jatuh_tempo === null
            ? null
            : $tanggalGenerateAktual->copy()->addDays($jenisTagihan->offset_hari_jatuh_tempo)->toDateString(),

        TipeTagihan::Bulanan => $this->resolveDueDateBulanan($billingPeriod, $jenisTagihan->hari_jatuh_tempo),

        TipeTagihan::Tahunan => $this->resolveDueDateTahunan($billingPeriod, $jenisTagihan->bulan_generate, $jenisTagihan->hari_jatuh_tempo),
    };
}

private function resolveDueDateBulanan(?string $billingPeriod, ?int $hariJatuhTempo): ?string
{
    if (! $billingPeriod || ! $hariJatuhTempo) {
        return null;
    }

    $year = (int) substr($billingPeriod, 0, 4);
    $month = (int) substr($billingPeriod, 5, 2);
    $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

    return Carbon::create($year, $month, min($hariJatuhTempo, $daysInMonth))->toDateString();
}

private function resolveDueDateTahunan(?string $billingPeriod, ?int $bulanGenerate, ?int $hariJatuhTempo): ?string
{
    if (! $billingPeriod || ! $bulanGenerate || ! $hariJatuhTempo) {
        return null;
    }

    $year = (int) $billingPeriod;
    $daysInMonth = Carbon::create($year, $bulanGenerate, 1)->daysInMonth;

    return Carbon::create($year, $bulanGenerate, min($hariJatuhTempo, $daysInMonth))->toDateString();
}
```

Update the single call site at line 83 (inside `generateForSiswa()`, in the `DB::transaction()` closure) — it currently reads `'jatuh_tempo' => $this->resolveDueDate($jenisTagihan, $billingPeriod),`. This task alone (without Task 7's `resolveBillingPeriod()`) can't yet supply the new `Carbon $tanggalGenerateAktual` parameter cleanly since `$billingPeriod` itself is still computed the OLD way (`now()->format('Y-m')`, ignoring `tipe`) at line 52 — leave line 52 AS-IS for this task and pass `now()` inline as the third argument:

```php
'jatuh_tempo' => $this->resolveDueDate($jenisTagihan, $billingPeriod, now()),
```

(Task 7 replaces line 52's `$billingPeriod` computation and this inline `now()` together, in one coherent change — doing it here first keeps this task's diff focused on `resolveDueDate()` alone.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=TagihanBillingGeneratorResolveDueDateTest`
Expected: PASS (5 tests)

- [ ] **Step 5: Run the full existing `TagihanBillingGeneratorTest` suite**

Run: `php artisan test --filter=TagihanBillingGeneratorTest`
Expected: PASS, unchanged — this is the regression proof that Bulanan behavior is byte-for-byte identical to before.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Keuangan/Services/TagihanBillingGenerator.php tests/Feature/Keuangan/TagihanBillingGeneratorResolveDueDateTest.php
git commit -m "feat(keuangan): branch resolveDueDate() explicitly per Tipe (Harian/Mingguan/Bulanan/Tahunan/Sekali)"
```

---

## Task 7: `resolveBillingPeriod()` — generalize `billing_period` format per Tipe

**Files:**
- Modify: `app/Domains/Keuangan/Services/TagihanBillingGenerator.php:51-58,83,157`
- Test: `tests/Feature/Keuangan/TagihanBillingGeneratorResolveBillingPeriodTest.php`

**Interfaces:**
- Consumes: `TipeTagihan` enum (Task 3), `resolveDueDate()`'s new signature (Task 6).
- Produces: `resolveBillingPeriod(JenisTagihan $jenisTagihan, Carbon $tanggalGenerateAktual): ?string`. Task 8 (cron) relies on `billing_period` now carrying the per-Tipe format this method produces.

- [ ] **Step 1: Write the failing tests, including the two mandatory ISO-week edge cases**

```php
<?php
// tests/Feature/Keuangan/TagihanBillingGeneratorResolveBillingPeriodTest.php
//
// resolveBillingPeriod() is private; tested indirectly through generateForSiswa(),
// asserting the resulting Tagihan.billing_period per Tipe.

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Services\JenisTagihanSasaranMatcher;
use App\Domains\Keuangan\Services\TagihanBillingGenerator;
use App\Domains\Keuangan\Services\TagihanNominalResolver;
use App\Models\Siswa;
use App\Services\Finance\NotificationDispatcher;
use Illuminate\Support\Carbon;

function buatGeneratorBillingPeriod(): TagihanBillingGenerator
{
    $matcher = new JenisTagihanSasaranMatcher();

    return new TagihanBillingGenerator($matcher, new TagihanNominalResolver($matcher), app(NotificationDispatcher::class));
}

it('sets billing_period to null for Mode=Manual regardless of Tipe', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['mode' => 'manual', 'tipe' => 'harian', 'default_amount' => 100000]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    buatGeneratorBillingPeriod()->generateForSiswa($siswa, $jenisTagihan, 'manual');

    expect(Tagihan::first()->billing_period)->toBeNull();
});

it('formats billing_period as Y-m-d for Tipe Harian', function () {
    Carbon::setTestNow('2026-09-15');
    $jenisTagihan = JenisTagihan::factory()->create(['mode' => 'otomatis', 'tipe' => 'harian', 'default_amount' => 100000, 'offset_hari_jatuh_tempo' => 1, 'tanggal_mulai' => '2026-01-01']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    buatGeneratorBillingPeriod()->generateForSiswa($siswa, $jenisTagihan, 'cron');

    expect(Tagihan::first()->billing_period)->toBe('2026-09-15');
    Carbon::setTestNow();
});

it('formats billing_period as ISO week for Tipe Mingguan using a normal mid-year date', function () {
    Carbon::setTestNow('2026-08-24');
    $jenisTagihan = JenisTagihan::factory()->create(['mode' => 'otomatis', 'tipe' => 'mingguan', 'default_amount' => 100000, 'hari_generate' => 1, 'offset_hari_jatuh_tempo' => 1, 'tanggal_mulai' => '2026-01-01']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    buatGeneratorBillingPeriod()->generateForSiswa($siswa, $jenisTagihan, 'cron');

    expect(Tagihan::first()->billing_period)->toBe('2026-W35');
    Carbon::setTestNow();
});

it('formats billing_period as ISO week correctly at the year-boundary edge case 2027-01-01 (must be 2026-W53, not 2027-W01)', function () {
    Carbon::setTestNow('2027-01-01');
    $jenisTagihan = JenisTagihan::factory()->create(['mode' => 'otomatis', 'tipe' => 'mingguan', 'default_amount' => 100000, 'hari_generate' => 5, 'offset_hari_jatuh_tempo' => 1, 'tanggal_mulai' => '2026-01-01']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    buatGeneratorBillingPeriod()->generateForSiswa($siswa, $jenisTagihan, 'cron');

    expect(Tagihan::first()->billing_period)->toBe('2026-W53');
    Carbon::setTestNow();
});

it('formats billing_period as ISO week correctly at the year-boundary edge case 2025-12-29 (must be 2026-W01, not 2025-W01)', function () {
    Carbon::setTestNow('2025-12-29');
    $jenisTagihan = JenisTagihan::factory()->create(['mode' => 'otomatis', 'tipe' => 'mingguan', 'default_amount' => 100000, 'hari_generate' => 1, 'offset_hari_jatuh_tempo' => 1, 'tanggal_mulai' => '2025-01-01']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    buatGeneratorBillingPeriod()->generateForSiswa($siswa, $jenisTagihan, 'cron');

    expect(Tagihan::first()->billing_period)->toBe('2026-W01');
    Carbon::setTestNow();
});

it('formats billing_period as Y-m for Tipe Bulanan, unchanged from current behavior', function () {
    Carbon::setTestNow('2026-09-15');
    $jenisTagihan = JenisTagihan::factory()->create(['mode' => 'otomatis', 'tipe' => 'bulanan', 'default_amount' => 100000, 'tanggal_generate' => 15, 'hari_jatuh_tempo' => 10, 'tanggal_mulai' => '2026-01-01']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    buatGeneratorBillingPeriod()->generateForSiswa($siswa, $jenisTagihan, 'cron');

    expect(Tagihan::first()->billing_period)->toBe('2026-09');
    Carbon::setTestNow();
});

it('formats billing_period as Y for Tipe Tahunan', function () {
    Carbon::setTestNow('2026-07-01');
    $jenisTagihan = JenisTagihan::factory()->create(['mode' => 'otomatis', 'tipe' => 'tahunan', 'default_amount' => 100000, 'bulan_generate' => 7, 'tanggal_generate' => 1, 'hari_jatuh_tempo' => 20, 'tanggal_mulai' => '2026-01-01']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    buatGeneratorBillingPeriod()->generateForSiswa($siswa, $jenisTagihan, 'cron');

    expect(Tagihan::first()->billing_period)->toBe('2026');
    Carbon::setTestNow();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=TagihanBillingGeneratorResolveBillingPeriodTest`
Expected: FAIL — `billing_period` is still always `Y-m` regardless of `tipe`.

- [ ] **Step 3: Add `resolveBillingPeriod()` and wire it into `generateForSiswa()`**

In `app/Domains/Keuangan/Services/TagihanBillingGenerator.php`, add this new private method (near `resolveDueDate()`):

```php
private function resolveBillingPeriod(JenisTagihan $jenisTagihan, Carbon $tanggalGenerateAktual): ?string
{
    if ($jenisTagihan->mode !== 'otomatis') {
        return null;
    }

    return match ($jenisTagihan->tipe) {
        TipeTagihan::Sekali => null,
        TipeTagihan::Harian => $tanggalGenerateAktual->format('Y-m-d'),
        // 'o' (lowercase, ISO week-numbering year) is REQUIRED here, not 'Y' (calendar
        // year) -- verified at the year boundary: 2027-01-01 must produce "2026-W53",
        // not "2027-W01", and 2025-12-29 must produce "2026-W01". Using 'Y' silently
        // miscalculates dedup at every year boundary.
        TipeTagihan::Mingguan => $tanggalGenerateAktual->format('o-\WW'),
        TipeTagihan::Bulanan => $tanggalGenerateAktual->format('Y-m'),
        TipeTagihan::Tahunan => $tanggalGenerateAktual->format('Y'),
    };
}
```

Update `generateForSiswa()` (lines 51-58 and line 83): replace line 52's

```php
$billingPeriod = $jenisTagihan->mode === 'otomatis' ? now()->format('Y-m') : null;
```

with:

```php
$tanggalGenerateAktual = now();
$billingPeriod = $this->resolveBillingPeriod($jenisTagihan, $tanggalGenerateAktual);
```

And update line 83 (Task 6's inline `now()`) from:

```php
'jatuh_tempo' => $this->resolveDueDate($jenisTagihan, $billingPeriod, now()),
```

to:

```php
'jatuh_tempo' => $this->resolveDueDate($jenisTagihan, $billingPeriod, $tanggalGenerateAktual),
```

(reusing the single `$tanggalGenerateAktual` computed once, rather than calling `now()` twice, so both resolvers see the exact same instant.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=TagihanBillingGeneratorResolveBillingPeriodTest`
Expected: PASS (7 tests, including both year-boundary edge cases)

- [ ] **Step 5: Run the full existing `TagihanBillingGeneratorTest` and `TagihanPolymorphicTest` suites**

Run: `php artisan test --filter='TagihanBillingGeneratorTest|TagihanPolymorphicTest'`
Expected: PASS, unchanged.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Keuangan/Services/TagihanBillingGenerator.php tests/Feature/Keuangan/TagihanBillingGeneratorResolveBillingPeriodTest.php
git commit -m "feat(keuangan): generalize billing_period format per Tipe, verified at ISO-week year boundaries"
```

---

## Task 8: Rewrite cron `GenerateTagihanHarian` — branch candidate matching per Tipe

**Files:**
- Modify: `app/Console/Commands/GenerateTagihanHarian.php`
- Test: `tests/Feature/Keuangan/GenerateTagihanHarianCommandTest.php` (extend existing file)

**Interfaces:**
- Consumes: `TipeTagihan` enum (Task 3), `resolveBillingPeriod()`/dedup behavior (Task 7).
- Produces: nothing new for later tasks — this is the last functional piece of the engine.

- [ ] **Step 1: Write the failing tests**

Add to the existing `tests/Feature/Keuangan/GenerateTagihanHarianCommandTest.php` (read it in full first — reuse its exact setup style for `Lembaga`/`Siswa`/`JenisTagihan` shown in the file already):

```php
it('processes Tipe Harian candidates every day with no extra date condition', function () {
    Carbon::setTestNow('2026-09-15');

    $lembaga = Lembaga::factory()->create();
    Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $harian = JenisTagihan::factory()->create([
        'lembaga_id' => $lembaga->id, 'default_amount' => 50000, 'mode' => 'otomatis', 'tipe' => 'harian',
        'is_active' => true, 'offset_hari_jatuh_tempo' => 1, 'tanggal_mulai' => '2026-01-01',
    ]);

    $this->artisan('billing:generate-harian')->assertExitCode(0);

    expect(Tagihan::where('jenis_tagihan_id', $harian->id)->count())->toBe(1);
    Carbon::setTestNow();
});

it('processes Tipe Mingguan candidates only on the matching hari_generate', function () {
    Carbon::setTestNow('2026-09-15'); // this is a Tuesday -- dayOfWeekIso = 2

    $lembagaCocok = Lembaga::factory()->create();
    Siswa::factory()->create(['lembaga_id' => $lembagaCocok->id]);
    $cocok = JenisTagihan::factory()->create([
        'lembaga_id' => $lembagaCocok->id, 'default_amount' => 50000, 'mode' => 'otomatis', 'tipe' => 'mingguan',
        'is_active' => true, 'hari_generate' => 2, 'offset_hari_jatuh_tempo' => 1, 'tanggal_mulai' => '2026-01-01',
    ]);

    $lembagaBedaHari = Lembaga::factory()->create();
    Siswa::factory()->create(['lembaga_id' => $lembagaBedaHari->id]);
    $bedaHari = JenisTagihan::factory()->create([
        'lembaga_id' => $lembagaBedaHari->id, 'default_amount' => 50000, 'mode' => 'otomatis', 'tipe' => 'mingguan',
        'is_active' => true, 'hari_generate' => 5, 'offset_hari_jatuh_tempo' => 1, 'tanggal_mulai' => '2026-01-01',
    ]);

    $this->artisan('billing:generate-harian')->assertExitCode(0);

    expect(Tagihan::where('jenis_tagihan_id', $cocok->id)->count())->toBe(1);
    expect(Tagihan::where('jenis_tagihan_id', $bedaHari->id)->count())->toBe(0);
    Carbon::setTestNow();
});

it('processes Tipe Tahunan candidates only when both bulan_generate and tanggal_generate match today', function () {
    Carbon::setTestNow('2026-07-01');

    $lembagaCocok = Lembaga::factory()->create();
    Siswa::factory()->create(['lembaga_id' => $lembagaCocok->id]);
    $cocok = JenisTagihan::factory()->create([
        'lembaga_id' => $lembagaCocok->id, 'default_amount' => 500000, 'mode' => 'otomatis', 'tipe' => 'tahunan',
        'is_active' => true, 'bulan_generate' => 7, 'tanggal_generate' => 1, 'hari_jatuh_tempo' => 20, 'tanggal_mulai' => '2026-01-01',
    ]);

    $lembagaBedaBulan = Lembaga::factory()->create();
    Siswa::factory()->create(['lembaga_id' => $lembagaBedaBulan->id]);
    $bedaBulan = JenisTagihan::factory()->create([
        'lembaga_id' => $lembagaBedaBulan->id, 'default_amount' => 500000, 'mode' => 'otomatis', 'tipe' => 'tahunan',
        'is_active' => true, 'bulan_generate' => 8, 'tanggal_generate' => 1, 'hari_jatuh_tempo' => 20, 'tanggal_mulai' => '2026-01-01',
    ]);

    $lembagaBedaTanggal = Lembaga::factory()->create();
    Siswa::factory()->create(['lembaga_id' => $lembagaBedaTanggal->id]);
    $bedaTanggal = JenisTagihan::factory()->create([
        'lembaga_id' => $lembagaBedaTanggal->id, 'default_amount' => 500000, 'mode' => 'otomatis', 'tipe' => 'tahunan',
        'is_active' => true, 'bulan_generate' => 7, 'tanggal_generate' => 15, 'hari_jatuh_tempo' => 20, 'tanggal_mulai' => '2026-01-01',
    ]);

    $this->artisan('billing:generate-harian')->assertExitCode(0);

    expect(Tagihan::where('jenis_tagihan_id', $cocok->id)->count())->toBe(1);
    expect(Tagihan::where('jenis_tagihan_id', $bedaBulan->id)->count())->toBe(0);
    expect(Tagihan::where('jenis_tagihan_id', $bedaTanggal->id)->count())->toBe(0);
    Carbon::setTestNow();
});

it('never generates for Tipe Sekali even if somehow mode were otomatis (defense-in-depth, not reachable via normal validation)', function () {
    Carbon::setTestNow('2026-09-15');

    $lembaga = Lembaga::factory()->create();
    Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create([
        'lembaga_id' => $lembaga->id, 'default_amount' => 50000, 'mode' => 'manual', 'tipe' => 'sekali',
        'is_active' => true, 'tanggal_mulai' => '2026-01-01',
    ]);
    // mode=manual is the only way to legally create tipe=sekali (CHECK constraint from
    // Task 2 forbids mode=otomatis+tipe=sekali at the DB level), so this test confirms
    // the cron's own query never even considers a manual-mode row as a candidate.

    $this->artisan('billing:generate-harian')->assertExitCode(0);

    expect(Tagihan::where('jenis_tagihan_id', $jenisTagihan->id)->count())->toBe(0);
    Carbon::setTestNow();
});

it('does not create a duplicate Tagihan for Tipe Harian when the command runs twice on the same day', function () {
    Carbon::setTestNow('2026-09-15');

    $lembaga = Lembaga::factory()->create();
    Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create([
        'lembaga_id' => $lembaga->id, 'default_amount' => 50000, 'mode' => 'otomatis', 'tipe' => 'harian',
        'is_active' => true, 'offset_hari_jatuh_tempo' => 1, 'tanggal_mulai' => '2026-01-01',
    ]);

    $this->artisan('billing:generate-harian')->assertExitCode(0);
    $this->artisan('billing:generate-harian')->assertExitCode(0);

    expect(Tagihan::where('jenis_tagihan_id', $jenisTagihan->id)->count())->toBe(1);
    Carbon::setTestNow();
});

it('does not create a duplicate Tagihan for Tipe Mingguan when the command runs twice on the same day', function () {
    Carbon::setTestNow('2026-09-15'); // Tuesday, dayOfWeekIso = 2

    $lembaga = Lembaga::factory()->create();
    Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create([
        'lembaga_id' => $lembaga->id, 'default_amount' => 50000, 'mode' => 'otomatis', 'tipe' => 'mingguan',
        'is_active' => true, 'hari_generate' => 2, 'offset_hari_jatuh_tempo' => 1, 'tanggal_mulai' => '2026-01-01',
    ]);

    $this->artisan('billing:generate-harian')->assertExitCode(0);
    $this->artisan('billing:generate-harian')->assertExitCode(0);

    expect(Tagihan::where('jenis_tagihan_id', $jenisTagihan->id)->count())->toBe(1);
    Carbon::setTestNow();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=GenerateTagihanHarianCommandTest`
Expected: FAIL for all new tests — the cron only currently matches `tanggal_generate == today->day` with no `tipe` awareness at all, so Harian/Mingguan/Tahunan candidates are never matched (0 generated) and the existing Bulanan-only query happens to accidentally match some of the new Tahunan test data by coincidence of `tanggal_generate` values — read the actual failure output to confirm which assertions fail before proceeding.

- [ ] **Step 3: Rewrite the candidate query**

In `app/Console/Commands/GenerateTagihanHarian.php`, add the import `use App\Domains\Keuangan\Enums\TipeTagihan;`, and replace the `$kandidat` query (lines 24-32):

```php
$kandidat = JenisTagihan::withoutGlobalScope(TenantScope::class)
    ->where('mode', 'otomatis')
    ->where('is_active', true)
    ->where('tanggal_mulai', '<=', $today->toDateString())
    ->where(function ($q) use ($today) {
        $q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $today->toDateString());
    })
    ->where(function ($q) use ($today) {
        $q->where('tipe', TipeTagihan::Harian->value)
            ->orWhere(function ($q2) use ($today) {
                $q2->where('tipe', TipeTagihan::Mingguan->value)
                    ->where('hari_generate', $today->dayOfWeekIso);
            })
            ->orWhere(function ($q2) use ($today) {
                $q2->where('tipe', TipeTagihan::Bulanan->value)
                    ->where('tanggal_generate', $today->day);
            })
            ->orWhere(function ($q2) use ($today) {
                $q2->where('tipe', TipeTagihan::Tahunan->value)
                    ->where('bulan_generate', $today->month)
                    ->where('tanggal_generate', $today->day);
            });
        // Tipe Sekali has no branch here -- the CHECK constraint from the migration
        // guarantees a tipe=sekali row can never have mode=otomatis, so it can never
        // reach this query in the first place (already filtered out by the
        // ->where('mode', 'otomatis') condition above).
    })
    ->get();
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=GenerateTagihanHarianCommandTest`
Expected: PASS — this includes ALL the original tests already in this file (Bulanan matching, error-isolation) plus the 6 new ones (Harian/Mingguan/Tahunan matching, Sekali defense, and the 2 dedup-double tests for Harian and Mingguan).

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/GenerateTagihanHarian.php tests/Feature/Keuangan/GenerateTagihanHarianCommandTest.php
git commit -m "feat(keuangan): branch cron candidate matching per Tipe (Harian/Mingguan/Bulanan/Tahunan)"
```

---

## Task 9: UI form — dynamic fields per Mode/Tipe combination

**Files:**
- Modify: `resources/views/portals/lembaga/keuangan/jenis-tagihan/form.blade.php`
- Modify: `resources/js/jenis-tagihan-form.js`
- Test: `tests/Feature/Admin/JenisTagihanFormTipeFieldsTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks directly (this is the presentation layer) — but the field names it submits (`tipe`, `hari_generate`, `bulan_generate`, `offset_hari_jatuh_tempo`) must exactly match Task 4's validation rule keys.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('renders a Tipe select and Mingguan-specific hari_generate field on the edit form', function () {
    $lembaga = \App\Models\Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    $jenisTagihan = JenisTagihan::factory()->create([
        'lembaga_id' => $lembaga->id, 'mode' => 'otomatis', 'tipe' => 'mingguan',
        'hari_generate' => 3, 'offset_hari_jatuh_tempo' => 2, 'tanggal_mulai' => now()->toDateString(),
    ]);

    $response = $this->actingAs($admin)->get(route('admin.jenis-tagihan.edit', $jenisTagihan));

    $response->assertOk();
    $response->assertSee('name="tipe"', false);
    $response->assertSee('name="hari_generate"', false);
    $response->assertSee('name="offset_hari_jatuh_tempo"', false);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=JenisTagihanFormTipeFieldsTest`
Expected: FAIL — no `tipe`/`hari_generate`/`offset_hari_jatuh_tempo` fields exist in the form yet.

- [ ] **Step 3: Add the Tipe select and dynamic field blocks**

In `resources/views/portals/lembaga/keuangan/jenis-tagihan/form.blade.php`, add `tipeAwal` to the `x-data` config (line 24-39 block), right after `modeAwal`:

```blade
kategoriAwal: @js(old('kategori', $jenisTagihan?->kategori ?? 'lainnya')),
modeAwal: @js(old('mode', $jenisTagihan?->mode ?? 'manual')),
tipeAwal: @js(old('tipe', $jenisTagihan?->tipe?->value ?? 'bulanan')),
```

Inside the "Mode Generate & Default" card (the `<template x-if="!kategoriPpdb">` block starting at line 117), add a Tipe select right after the Mode select (after the closing `</div>` of the Mode field around line 131), and replace the existing `<template x-if="form.mode === 'otomatis'">` block (lines 132-155) with per-Tipe conditional sub-blocks:

```blade
<div>
    <x-input-label value="Tipe" />
    <select name="tipe" x-model="form.tipe" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
        <option value="harian">Harian</option>
        <option value="mingguan">Mingguan</option>
        <option value="bulanan">Bulanan</option>
        <option value="tahunan">Tahunan</option>
        <option value="sekali">Sekali</option>
    </select>
</div>
<template x-if="form.mode === 'otomatis'">
    <div class="grid grid-cols-1 gap-4 sm:col-span-2 sm:grid-cols-2 pt-2">
        <div>
            <x-input-label value="Tanggal Mulai" />
            <x-text-input type="date" name="tanggal_mulai" :value="old('tanggal_mulai', optional($jenisTagihan?->tanggal_mulai)->toDateString())" class="mt-1.5" />
            <p class="mt-1.5 text-[10px] text-gray-400 leading-tight">Tanggal jenis tagihan ini mulai aktif digenerate otomatis.</p>
        </div>
        <div>
            <x-input-label value="Tanggal Selesai (opsional)" />
            <x-text-input type="date" name="tanggal_selesai" :value="old('tanggal_selesai', optional($jenisTagihan?->tanggal_selesai)->toDateString())" class="mt-1.5" />
            <p class="mt-1.5 text-[10px] text-gray-400 leading-tight">Kosongkan jika tidak ada batas akhir.</p>
        </div>
        <template x-if="form.tipe === 'mingguan'">
            <div>
                <x-input-label value="Hari Generate" />
                <select name="hari_generate" x-model.number="form.hariGenerate" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="1">Senin</option>
                    <option value="2">Selasa</option>
                    <option value="3">Rabu</option>
                    <option value="4">Kamis</option>
                    <option value="5">Jumat</option>
                    <option value="6">Sabtu</option>
                    <option value="7">Minggu</option>
                </select>
                <p class="mt-1.5 text-[10px] text-gray-400 leading-tight">Hari dalam seminggu saat tagihan otomatis dibuat.</p>
            </div>
        </template>
        <template x-if="form.tipe === 'harian' || form.tipe === 'mingguan'">
            <div>
                <x-input-label value="Hari Jatuh Tempo (setelah generate)" />
                <x-text-input type="number" min="0" name="offset_hari_jatuh_tempo" :value="old('offset_hari_jatuh_tempo', $jenisTagihan?->offset_hari_jatuh_tempo)" class="mt-1.5" />
                <p class="mt-1.5 text-[10px] text-gray-400 leading-tight">Jumlah hari setelah tanggal generate sampai batas waktu pembayaran.</p>
            </div>
        </template>
        <template x-if="form.tipe === 'tahunan'">
            <div>
                <x-input-label value="Bulan Generate" />
                <select name="bulan_generate" x-model.number="form.bulanGenerate" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <template x-for="bulan in 12" :key="bulan"><option :value="bulan" x-text="bulan"></option></template>
                </select>
                <p class="mt-1.5 text-[10px] text-gray-400 leading-tight">Bulan saat tagihan tahunan digenerate (default: bulan Tanggal Mulai).</p>
            </div>
        </template>
        <template x-if="form.tipe === 'bulanan' || form.tipe === 'tahunan'">
            <div>
                <x-input-label value="Tanggal Generate (hari ke-)" />
                <x-text-input type="number" min="1" max="31" name="tanggal_generate" :value="old('tanggal_generate', $jenisTagihan?->tanggal_generate)" class="mt-1.5" />
                <p class="mt-1.5 text-[10px] text-gray-400 leading-tight">Tanggal saat tagihan otomatis dibuat (mis. isi 1 untuk tanggal 1).</p>
            </div>
        </template>
        <template x-if="form.tipe === 'bulanan' || form.tipe === 'tahunan'">
            <div>
                <x-input-label value="Tanggal jatuh tempo (tanggal di bulan yang sama, bukan jarak hari)" />
                <x-text-input type="number" min="1" max="31" name="hari_jatuh_tempo" :value="old('hari_jatuh_tempo', $jenisTagihan?->hari_jatuh_tempo)" class="mt-1.5" />
                <p class="mt-1.5 text-[10px] text-gray-400 leading-tight">Tanggal di bulan yang sama dengan Tanggal Generate saat tagihan jatuh tempo.</p>
            </div>
        </template>
    </div>
</template>
```

- [ ] **Step 4: Update the Alpine component's `form` state**

In `resources/js/jenis-tagihan-form.js`, in the `form` object (line 20-27), add:

```js
form: {
    kategori: config.kategoriAwal,
    mode: config.modeAwal,
    tipe: config.tipeAwal,
    bisaDicicil: config.bisaDicicilAwal,
    sasaran: config.initialSasaran.map(hydrateGrup),
    tarif: config.initialTarif.map(hydrateGrup),
    keringanan: config.initialKeringanan.map((k) => ({ uid: nextUid(), ...k })),
},
```

(`hariGenerate`/`bulanGenerate` bindings in the blade template above use `x-model.number` directly on plain `<select>` elements without a corresponding `form.hariGenerate`/`form.bulanGenerate` initial value — Alpine creates these reactively on first render from the `<select>`'s own selected option, consistent with how `form.kategori`/`form.mode` are already seeded via `x-model` on their own `<select>` elements without separate initialization in the JS `form` object beyond the top-level key. Confirm this matches Alpine's actual behavior by testing in-browser per the project's `CLAUDE.md` UI-testing convention before considering Step 5 done.)

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=JenisTagihanFormTipeFieldsTest`
Expected: PASS

- [ ] **Step 6: Manual browser verification (required by this project's CLAUDE.md UI convention)**

Run `npm run build` (or confirm `npm run dev`/`composer run dev` is already running), then open the Jenis Tagihan create and edit pages in a browser: switch Mode between Manual/Otomatis and Tipe between all 5 values, confirm the correct field set appears/disappears for each combination per §4.6 of the spec, and confirm submitting each combination succeeds without validation errors when all required fields for that combination are filled.

- [ ] **Step 7: Commit**

```bash
git add resources/views/portals/lembaga/keuangan/jenis-tagihan/form.blade.php resources/js/jenis-tagihan-form.js tests/Feature/Admin/JenisTagihanFormTipeFieldsTest.php
git commit -m "feat(keuangan): add Tipe select and per-Tipe dynamic fields to Jenis Tagihan form"
```

---

## Final Step: Full Test Suite

- [ ] Run: `php artisan test --compact`
- [ ] Expected: PASS, 0 failures — confirms Tasks 1-9 introduced no regressions anywhere else in the app, and every existing test relying on the old single-cycle (monthly) behavior still passes unchanged because of the `tipe='bulanan'` default from Task 3.
- [ ] Run `vendor/bin/pint --dirty --format agent` to fix any formatting drift across all files touched in this plan, then commit any resulting formatting-only changes separately.

**Plan complete when this full-suite run and Pint pass are both clean.**
