# Sub-Task 03b — Mode Tematik/Harian (KB/TK/SD/SLB/TPA/SPS) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **If you are an AI agent picking up this plan cold (no prior conversation context):** read this whole header block and the Global Constraints section before touching any file. Every task below is self-contained — each one restates the exact file paths, exact code, and exact commands you need. You do not need to read any other file in this repository to complete a task, except where a step explicitly tells you to open one.

**Goal:** Complete the "Mode Tematik/Harian" (one guru-kelas session per day, used by KB/TK/SD/SLB/TPA/SPS jenjang) half of the Jurnal KBM & Presensi feature. Sub-Task 03a (already merged) built the "Mode Sesi Mapel" half (per-jam-pelajaran sessions, used by SMP/SMA/SMK) and structural groundwork this plan builds on.

**Architecture:** Domain-oriented (`app/Domains/Akademik/{Enums,Services}`), reusing the existing `SesiPembelajaran`/`Presensi` models unchanged in shape — a Tematik session is a normal `SesiPembelajaran` row with `jadwal_pelajaran_id = NULL` and `mata_pelajaran_id = NULL`. No new migrations, no new Controller, no new routes, no new Blade views — everything above the generation layer (`Guru\Akademik\JurnalKbmController`, `UpdateJurnalPresensiRequest`, `RecordJurnalDanPresensiAction`, both Blade views) already handles `NULL` mata pelajaran gracefully (built in Sub-Task 03a) and needs zero changes.

**Tech Stack:** Laravel 11, PHP 8.2+, Pest (testing framework — tests are plain functions using `it(...)`/`expect(...)`, not PHPUnit classes, EXCEPT where noted below), Eloquent, `firstOrCreate` for idempotent generation.

## Where This Repo Lives / How To Run Things

- Repo root: `d:\laragon\www\pintera-app` (Windows, XAMPP/Laragon stack). All paths below are relative to this root.
- Git branch to work on: `akademik-v2` (already exists locally, checked out). Do not create a new branch unless explicitly told to — work directly on `akademik-v2`, matching how Sub-Task 03a was executed.
- Run a single test file: `php artisan test path/to/TestFile.php`
- Run tests matching a name filter: `php artisan test --filter=SomeName`
- Run the full suite: `php artisan test` (takes ~350-420 seconds on this machine — only run this once, at the very end, and only after asking the human whether they want it run; see Global Constraints).
- **Do NOT run `php artisan test` concurrently in two terminals/processes** — this repo's test DB is shared and concurrent runs cause spurious failures from a race condition (documented incident from a prior sub-task). Finish one run before starting another.

## Global Constraints

- **No behavior change to Mode Sesi Mapel.** Every file this plan touches must leave existing SesiMapel-mode behavior byte-for-byte identical. Sub-Task 03a's existing tests (`tests/Feature/Guru/JurnalKbmControllerTest.php`, `tests/Feature/Guru/JurnalKbmTenantScopeTest.php`, `tests/Feature/Guru/RekapKehadiranControllerTest.php`, `tests/Unit/Services/SesiPembelajaranGeneratorTest.php`, `tests/Unit/Services/PresensiAggregationServiceTest.php`) must all stay green throughout — re-run the relevant ones after every task (exact commands are in each task below).
- **No new migrations.** The columns needed (`jadwal_pelajaran_id` and `mata_pelajaran_id` on `sesi_pembelajaran`, both nullable) already exist since the table was created. Do not touch `database/migrations/`.
- **No changes to:** `app/Http/Controllers/Guru/Akademik/JurnalKbmController.php`, `app/Http/Requests/Akademik/UpdateJurnalPresensiRequest.php`, `app/Domains/Akademik/Actions/Presensi/RecordJurnalDanPresensiAction.php`, `app/Domains/Akademik/DataTransferObjects/JurnalPresensiData.php`, `resources/views/portals/guru/akademik/jurnal-kbm/index.blade.php`, `resources/views/portals/guru/akademik/jurnal-kbm/show.blade.php`, `routes/admin.php`. These are all already mode-agnostic (verified during brainstorming — the views already render "(tanpa mapel)" when `mataPelajaran` is null). Touching any of them is out of scope for this plan; if you find yourself needing to, stop and flag it rather than proceeding.
- **Mode-detection rule (already decided, do not re-litigate):** `bentuk_pendidikan` IN (`SMP`, `SMA`, `SMK`) → Mode Sesi Mapel. **Every other value** (`KB`, `TPA`, `SPS`, `TK`, `SD`, `SLB`, and any future value added to that enum) → Mode Tematik. This is a negative whitelist — implement it that way (a `default` match arm that returns Tematik), not as two explicit lists.
- **Unit-test files under `tests/Unit/` in this repo do NOT automatically get `RefreshDatabase`** — Pest's global config (`tests/Pest.php`) only auto-applies `RefreshDatabase` to `tests/Feature/`. Any new file under `tests/Unit/` that touches the database MUST start with `uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);` as its own line, exactly like `tests/Unit/Services/SesiPembelajaranGeneratorTest.php` and `tests/Unit/Services/PresensiAggregationServiceTest.php` already do. Forgetting this makes tests silently leak data between test cases instead of failing loudly — check this explicitly before considering a task done.
- **Full test suite baseline going into this plan: 1753 passed, 0 failed** (per Sub-Task 03a's handoff log, commit `0b07066`). Every task's own scoped test command (given in that task) must pass before moving to the next task. Do **not** run the full `php artisan test` suite after every task — see the testing policy below.
- **Testing policy (cost-saving, explicitly requested by the project owner):** run only the scoped test command listed in each task while working. Run the full suite (`php artisan test`) **at most once**, at the very end after all tasks are done — and only after asking the human "want me to run the full suite now?" Do not run it automatically.
- **Commit after every task**, using `git add <specific files>` (never `git add -A` or `git add .`) and a Conventional-Commits-style message in Bahasa Indonesia matching this repo's existing commit log style (run `git log --oneline -10` to see examples if unsure).

---

## Task 1: `ModePembelajaran` Enum

**Files:**
- Create: `app/Domains/Akademik/Enums/ModePembelajaran.php`
- Create: `tests/Unit/Domains/Akademik/ModePembelajaranTest.php`

**Interfaces:**
- Produces: `App\Domains\Akademik\Enums\ModePembelajaran` — a pure PHP enum (no backing type) with cases `SesiMapel` and `Tematik`, and a static method `fromBentukPendidikan(string $bentukPendidikan): self`. Task 3 imports and calls this.

- [x] **Step 1: Write the failing test**

Create `tests/Unit/Domains/Akademik/ModePembelajaranTest.php` with this exact content:

```php
<?php

use App\Domains\Akademik\Enums\ModePembelajaran;

it('maps SMP, SMA, and SMK to SesiMapel', function (string $bentukPendidikan) {
    expect(ModePembelajaran::fromBentukPendidikan($bentukPendidikan))->toBe(ModePembelajaran::SesiMapel);
})->with(['SMP', 'SMA', 'SMK']);

it('maps every other bentuk_pendidikan value to Tematik', function (string $bentukPendidikan) {
    expect(ModePembelajaran::fromBentukPendidikan($bentukPendidikan))->toBe(ModePembelajaran::Tematik);
})->with(['KB', 'TPA', 'SPS', 'TK', 'SD', 'SLB']);

it('defaults an unrecognized future bentuk_pendidikan value to Tematik, not SesiMapel', function () {
    expect(ModePembelajaran::fromBentukPendidikan('JENJANG_BARU_YANG_BELUM_ADA'))->toBe(ModePembelajaran::Tematik);
});
```

This is a pure-PHP unit test with no database access, so it does NOT need `uses(RefreshDatabase::class)` — Pest will run it with the default test case.

- [x] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Domains/Akademik/ModePembelajaranTest.php`
Expected: FAIL — `Class "App\Domains\Akademik\Enums\ModePembelajaran" not found`.

- [x] **Step 3: Write the enum**

Create `app/Domains/Akademik/Enums/ModePembelajaran.php` with this exact content:

```php
<?php

namespace App\Domains\Akademik\Enums;

enum ModePembelajaran
{
    case SesiMapel;
    case Tematik;

    public static function fromBentukPendidikan(string $bentukPendidikan): self
    {
        return match ($bentukPendidikan) {
            'SMP', 'SMA', 'SMK' => self::SesiMapel,
            default => self::Tematik,
        };
    }
}
```

- [x] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Domains/Akademik/ModePembelajaranTest.php`
Expected: PASS — 3 tests (10 assertions: 3 + 6 + 1 across the two `->with()` datasets and the single test).

- [x] **Step 5: Commit**

```bash
git add app/Domains/Akademik/Enums/ModePembelajaran.php tests/Unit/Domains/Akademik/ModePembelajaranTest.php
git commit -m "feat(akademik): tambah enum ModePembelajaran untuk deteksi mode Sesi Mapel vs Tematik"
```

---

## Task 2: `SesiTematikGenerator` Service

**Files:**
- Create: `app/Domains/Akademik/Services/SesiTematikGenerator.php`
- Create: `tests/Unit/Services/SesiTematikGeneratorTest.php`

**Interfaces:**
- Consumes: `App\Domains\Akademik\Models\SesiPembelajaran` (existing, from Sub-Task 03a), `App\Domains\Akademik\Models\Presensi` (existing), `App\Services\KalenderAkademikResolver::resolve(Lembaga $lembaga, CarbonInterface $tanggal): array{libur: bool, alasan: string}` (existing, unmodified), `App\Enums\Hari::fromCarbonDayOfWeek(int $dayOfWeek): self` (existing), `App\Models\Kelas` (existing — has `wali_kelas_guru_id`, `pola_jam_id`, `polaJam(): BelongsTo`, `lembaga(): BelongsTo`, `siswa(): HasMany` relations already).
- Produces: `App\Domains\Akademik\Services\SesiTematikGenerator` with public method `generateUntukTanggal(Kelas $kelas, CarbonInterface $tanggal, int $semesterId): ?SesiPembelajaran`. Task 3 consumes this exact signature. Returns `null` when no session should be created (holiday, no wali, or no jam-pelajaran slots configured for that day); returns the `SesiPembelajaran` row (existing or freshly created) otherwise.

**Design note for whoever implements this:** the sibling class `App\Domains\Akademik\Services\SesiPembelajaranGenerator` (already in this codebase at `app/Domains/Akademik/Services/SesiPembelajaranGenerator.php`) solves the same "one row per kelas per day, skip on holiday, seed default Presensi rows" problem for Mode Sesi Mapel — read it for the general shape (holiday check via `KalenderAkademikResolver`, `firstOrCreate` on `SesiPembelajaran`, then a `wasRecentlyCreated` guard before seeding `Presensi` rows), but do NOT copy its `JadwalPelajaran`-block-merging logic — Mode Tematik never has a `JadwalPelajaran` row, so none of that applies.

**Duplicate-protection note:** the DB unique index on `sesi_pembelajaran` is `['jadwal_pelajaran_id', 'tanggal']`. Since Mode Tematik rows always have `jadwal_pelajaran_id = NULL`, and MySQL treats every `NULL` in a unique index as distinct from every other `NULL`, that index does **not** protect against duplicate Tematik rows for the same kelas+date. This is a known, accepted limitation (no DB migration in scope for this plan) — protection is app-level only, via the `firstOrCreate()` call in Step 3 below using `kelas_id` + `tanggal` + `jadwal_pelajaran_id` (explicitly `null`) as the lookup key. Concurrent double-submission is not a realistic risk for a once-a-day teacher action; do not add locking beyond what's shown.

- [x] **Step 1: Write the failing tests**

Create `tests/Unit/Services/SesiTematikGeneratorTest.php` with this exact content:

```php
<?php

use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Domains\Akademik\Services\SesiTematikGenerator;
use App\Enums\Hari;
use App\Models\Guru;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\PolaJam;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function siapkanKelasTematikDenganWali(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'TK', 'hari_libur_mingguan' => [0]]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'urutan' => 1, 'jam_mulai' => '08:00:00', 'jam_selesai' => '08:30:00', 'is_pelajaran' => true]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'urutan' => 2, 'jam_mulai' => '08:30:00', 'jam_selesai' => '09:00:00', 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'pola_jam_id' => $pola->id,
        'wali_kelas_guru_id' => $guru->id,
    ]);
    Siswa::factory()->count(3)->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    return compact('kelas', 'guru', 'semester');
}

it('creates one sesi tematik for the kelas with jadwal_pelajaran_id and mata_pelajaran_id both null', function () {
    ['kelas' => $kelas, 'guru' => $guru, 'semester' => $semester] = siapkanKelasTematikDenganWali();

    $sesi = (new SesiTematikGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id); // a Wednesday

    expect($sesi)->not->toBeNull();
    expect($sesi->jadwal_pelajaran_id)->toBeNull();
    expect($sesi->mata_pelajaran_id)->toBeNull();
    expect($sesi->guru_id)->toBe($guru->id);
    expect($sesi->kelas_id)->toBe($kelas->id);
    expect(SesiPembelajaran::where('kelas_id', $kelas->id)->count())->toBe(1);
});

it('auto-creates a hadir presensi row for every siswa aktif in the kelas', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasTematikDenganWali();

    $sesi = (new SesiTematikGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id);

    expect($sesi->presensi()->count())->toBe(3);
    expect($sesi->presensi()->first()->status->value)->toBe('hadir');
});

it('returns null and creates nothing when the kelas has no wali kelas assigned', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasTematikDenganWali();
    $kelas->update(['wali_kelas_guru_id' => null]);

    $sesi = (new SesiTematikGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id);

    expect($sesi)->toBeNull();
    expect(SesiPembelajaran::where('kelas_id', $kelas->id)->count())->toBe(0);
});

it('returns null on a day the kalender resolver marks as libur', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasTematikDenganWali();

    $sesi = (new SesiTematikGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-16'), $semester->id); // a Sunday, weekly off-day

    expect($sesi)->toBeNull();
    expect(SesiPembelajaran::where('kelas_id', $kelas->id)->count())->toBe(0);
});

it('is idempotent: calling it twice for the same date does not duplicate the sesi', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasTematikDenganWali();

    $pertama = (new SesiTematikGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id);
    $kedua = (new SesiTematikGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id);

    expect(SesiPembelajaran::where('kelas_id', $kelas->id)->count())->toBe(1);
    expect($kedua->id)->toBe($pertama->id);
});
```

- [x] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/Services/SesiTematikGeneratorTest.php`
Expected: FAIL — `Class "App\Domains\Akademik\Services\SesiTematikGenerator" not found`.

- [x] **Step 3: Write the service**

Create `app/Domains/Akademik/Services/SesiTematikGenerator.php` with this exact content:

```php
<?php

namespace App\Domains\Akademik\Services;

use App\Domains\Akademik\Models\Presensi;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Enums\Hari;
use App\Models\Kelas;
use App\Services\KalenderAkademikResolver;
use Carbon\CarbonInterface;

class SesiTematikGenerator
{
    public function generateUntukTanggal(Kelas $kelas, CarbonInterface $tanggal, int $semesterId): ?SesiPembelajaran
    {
        if ($kelas->wali_kelas_guru_id === null || $kelas->pola_jam_id === null) {
            return null;
        }

        $resolusi = (new KalenderAkademikResolver)->resolve($kelas->lembaga, $tanggal);

        if ($resolusi['libur']) {
            return null;
        }

        $hari = Hari::fromCarbonDayOfWeek($tanggal->dayOfWeek);

        $slotHariIni = $kelas->polaJam->jamPelajaran()
            ->where('hari', $hari->value)
            ->isPelajaran()
            ->orderBy('urutan')
            ->get();

        if ($slotHariIni->isEmpty()) {
            return null;
        }

        $sesi = SesiPembelajaran::firstOrCreate(
            [
                'kelas_id' => $kelas->id,
                'tanggal' => $tanggal->toDateString(),
                'jadwal_pelajaran_id' => null,
            ],
            [
                'guru_id' => $kelas->wali_kelas_guru_id,
                'mata_pelajaran_id' => null,
                'jam_mulai' => $slotHariIni->first()->jam_mulai,
                'jam_selesai' => $slotHariIni->last()->jam_selesai,
                'status' => 'terlaksana',
            ]
        );

        if ($sesi->wasRecentlyCreated) {
            foreach ($kelas->siswa()->where('status', 'aktif')->get() as $siswa) {
                Presensi::firstOrCreate(
                    ['sesi_pembelajaran_id' => $sesi->id, 'siswa_id' => $siswa->id],
                    ['status' => 'hadir']
                );
            }
        }

        return $sesi;
    }
}
```

Note: `$semesterId` is accepted but unused inside this method — it is kept in the signature to match `SesiPembelajaranGenerator::generateUntukTanggal()`'s signature exactly, since both are called polymorphically from the same call site in Task 3. This is intentional, not dead code to clean up.

- [x] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Unit/Services/SesiTematikGeneratorTest.php`
Expected: PASS — 5 tests.

- [x] **Step 5: Commit**

```bash
git add app/Domains/Akademik/Services/SesiTematikGenerator.php tests/Unit/Services/SesiTematikGeneratorTest.php
git commit -m "feat(akademik): tambah SesiTematikGenerator untuk sesi harian mode Tematik"
```

---

## Task 3: Wire Tematik Generation Into `GenerateSesiHarianAction` + `isTematik()` Accessor

**Files:**
- Modify: `app/Domains/Akademik/Actions/Presensi/GenerateSesiHarianAction.php`
- Modify: `app/Domains/Akademik/Models/SesiPembelajaran.php`
- Create: `tests/Unit/Domains/Akademik/SesiPembelajaranIsTematikTest.php`
- Create: `tests/Feature/Akademik/JurnalKbmAdaptiveTest.php`

**Interfaces:**
- Consumes: `App\Domains\Akademik\Enums\ModePembelajaran::fromBentukPendidikan()` (Task 1), `App\Domains\Akademik\Services\SesiTematikGenerator::generateUntukTanggal()` (Task 2), `App\Domains\Akademik\Services\SesiPembelajaranGenerator::generateUntukTanggal()` (existing, unmodified signature).
- Produces: `SesiPembelajaran::isTematik(): bool` — usable by any future code (not consumed elsewhere in this plan, but part of the spec's acceptance criteria).

### Part A — `isTematik()` accessor

- [x] **Step 1: Write the failing test**

Create `tests/Unit/Domains/Akademik/SesiPembelajaranIsTematikTest.php` with this exact content:

```php
<?php

use App\Domains\Akademik\Models\SesiPembelajaran;

it('reports isTematik true when jadwal_pelajaran_id is null', function () {
    $sesi = new SesiPembelajaran(['jadwal_pelajaran_id' => null]);

    expect($sesi->isTematik())->toBeTrue();
});

it('reports isTematik false when jadwal_pelajaran_id is set', function () {
    $sesi = new SesiPembelajaran(['jadwal_pelajaran_id' => 42]);

    expect($sesi->isTematik())->toBeFalse();
});
```

This instantiates `SesiPembelajaran` in memory without saving it, so no database access happens — no `RefreshDatabase` needed.

- [x] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Domains/Akademik/SesiPembelajaranIsTematikTest.php`
Expected: FAIL — `Call to undefined method App\Domains\Akademik\Models\SesiPembelajaran::isTematik()`.

- [x] **Step 3: Add the accessor**

Open `app/Domains/Akademik/Models/SesiPembelajaran.php`. Find the `presensi()` method (the last method in the class, just before the closing `}` of the class body):

```php
    public function presensi(): HasMany
    {
        return $this->hasMany(Presensi::class);
    }
}
```

Replace it with (adds `isTematik()` right after `presensi()`, still inside the class, before the closing `}`):

```php
    public function presensi(): HasMany
    {
        return $this->hasMany(Presensi::class);
    }

    public function isTematik(): bool
    {
        return $this->jadwal_pelajaran_id === null;
    }
}
```

- [x] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Domains/Akademik/SesiPembelajaranIsTematikTest.php`
Expected: PASS — 2 tests.

### Part B — Wire the generator branch into `GenerateSesiHarianAction`

- [x] **Step 5: Read the current file**

Open `app/Domains/Akademik/Actions/Presensi/GenerateSesiHarianAction.php`. Its current full content is:

```php
<?php

namespace App\Domains\Akademik\Actions\Presensi;

use App\Domains\Akademik\Services\SesiPembelajaranGenerator;
use App\Models\Guru;
use App\Models\Kelas;
use Carbon\CarbonInterface;

final class GenerateSesiHarianAction
{
    public function __construct(
        private readonly SesiPembelajaranGenerator $generator,
    ) {
    }

    public function execute(Guru $guru, CarbonInterface $tanggal): void
    {
        $kelasList = Kelas::where(function ($query) use ($guru) {
            $query->whereHas('jadwalPelajaran', fn ($q) => $q->where('guru_id', $guru->id))
                ->orWhere('wali_kelas_guru_id', $guru->id);
        })->get();

        foreach ($kelasList as $kelas) {
            $semesterId = optional($kelas->tahunAjaran->semester()->where('status_aktif', true)->first())->id;
            if ($semesterId) {
                $this->generator->generateUntukTanggal($kelas, $tanggal, $semesterId);
            }
        }
    }
}
```

The `$kelasList` query already includes kelas where the guru is `wali_kelas_guru_id` (this was already written in Sub-Task 03a, anticipating this plan) — it just always calls `$this->generator` (the Sesi Mapel generator) regardless of which condition matched. This step adds the branch.

- [x] **Step 6: Replace the file with the branched version**

Replace the entire content of `app/Domains/Akademik/Actions/Presensi/GenerateSesiHarianAction.php` with:

```php
<?php

namespace App\Domains\Akademik\Actions\Presensi;

use App\Domains\Akademik\Enums\ModePembelajaran;
use App\Domains\Akademik\Services\SesiPembelajaranGenerator;
use App\Domains\Akademik\Services\SesiTematikGenerator;
use App\Models\Guru;
use App\Models\Kelas;
use Carbon\CarbonInterface;

final class GenerateSesiHarianAction
{
    public function __construct(
        private readonly SesiPembelajaranGenerator $generator,
        private readonly SesiTematikGenerator $generatorTematik,
    ) {
    }

    public function execute(Guru $guru, CarbonInterface $tanggal): void
    {
        $kelasList = Kelas::where(function ($query) use ($guru) {
            $query->whereHas('jadwalPelajaran', fn ($q) => $q->where('guru_id', $guru->id))
                ->orWhere('wali_kelas_guru_id', $guru->id);
        })->with('lembaga')->get();

        foreach ($kelasList as $kelas) {
            $semesterId = optional($kelas->tahunAjaran->semester()->where('status_aktif', true)->first())->id;

            if (! $semesterId) {
                continue;
            }

            $mode = ModePembelajaran::fromBentukPendidikan($kelas->lembaga->bentuk_pendidikan);

            if ($mode === ModePembelajaran::SesiMapel) {
                $this->generator->generateUntukTanggal($kelas, $tanggal, $semesterId);
            } else {
                $this->generatorTematik->generateUntukTanggal($kelas, $tanggal, $semesterId);
            }
        }
    }
}
```

No service provider changes are needed — `SesiTematikGenerator` is a plain concrete class with no constructor arguments, so Laravel's container resolves it automatically, exactly like `SesiPembelajaranGenerator` already is.

- [x] **Step 7: Re-run Sub-Task 03a's existing regression tests to confirm no behavior change**

Run: `php artisan test tests/Feature/Guru/JurnalKbmControllerTest.php tests/Feature/Guru/JurnalKbmTenantScopeTest.php tests/Feature/Guru/RekapKehadiranControllerTest.php tests/Unit/Services/SesiPembelajaranGeneratorTest.php`
Expected: PASS — all tests green, same counts as before this task (these tests only exercise Mode Sesi Mapel kelas, which now hit the unchanged `if` branch).

### Part C — Feature-level adaptive test covering both modes end-to-end

- [x] **Step 8: Write the failing tests**

Create `tests/Feature/Akademik/JurnalKbmAdaptiveTest.php` with this exact content:

```php
<?php

use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Enums\Hari;
use App\Models\Guru;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\PolaJam;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Carbon\Carbon;
use Spatie\Permission\Models\Permission;

function siapkanGuruKelasTematik(): array
{
    Carbon::setTestNow(Carbon::parse('2026-08-19')); // a Wednesday

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'TK', 'hari_libur_mingguan' => [0]]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'status_aktif' => true]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'urutan' => 1, 'jam_mulai' => '08:00:00', 'jam_selesai' => '08:30:00', 'is_pelajaran' => true]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'urutan' => 2, 'jam_mulai' => '08:30:00', 'jam_selesai' => '09:00:00', 'is_pelajaran' => true]);

    Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru_kelas_tematik', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['presensi.isi']);
    $guruUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruUser->assignRole($role);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $guruUser->id]);

    $kelas = Kelas::factory()->create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'pola_jam_id' => $pola->id,
        'wali_kelas_guru_id' => $guru->id,
    ]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    return compact('guruUser', 'guru', 'kelas', 'semester', 'siswa');
}

it('auto-generates exactly one tematik sesi for the wali kelas guru today, with no mata pelajaran', function () {
    ['guruUser' => $guruUser, 'guru' => $guru, 'kelas' => $kelas] = siapkanGuruKelasTematik();

    $response = $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.index'));

    $response->assertOk();
    $response->assertViewHas('sesiList', fn ($list) => $list->count() === 1);

    $sesi = SesiPembelajaran::where('kelas_id', $kelas->id)->firstOrFail();
    expect($sesi->jadwal_pelajaran_id)->toBeNull();
    expect($sesi->mata_pelajaran_id)->toBeNull();
    expect($sesi->guru_id)->toBe($guru->id);
    expect($sesi->isTematik())->toBeTrue();
});

it('lets the wali kelas guru view and fill jurnal plus presensi for the tematik sesi through the same show/update routes as sesi mapel', function () {
    ['guruUser' => $guruUser, 'siswa' => $siswa] = siapkanGuruKelasTematik();
    $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.index')); // triggers generation
    $sesi = SesiPembelajaran::firstOrFail();

    $showResponse = $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.show', $sesi));
    $showResponse->assertOk();
    $showResponse->assertViewHas('sesi', fn ($viewSesi) => $viewSesi->is($sesi));

    $this->actingAs($guruUser)->put(route('guru.jurnal-kbm.update', $sesi), [
        'materi' => 'Mengenal warna dan bentuk',
        'presensi' => [
            $siswa->id => 'sakit',
        ],
    ])->assertRedirect(route('guru.jurnal-kbm.index'));

    expect($sesi->fresh()->materi)->toBe('Mengenal warna dan bentuk');
    expect($sesi->fresh()->presensi()->where('siswa_id', $siswa->id)->first()->status->value)->toBe('sakit');
});

it('does not generate a tematik sesi when the wali kelas guru is on a libur day', function () {
    ['guruUser' => $guruUser, 'kelas' => $kelas] = siapkanGuruKelasTematik();
    Carbon::setTestNow(Carbon::parse('2026-08-16')); // a Sunday, weekly off-day per hari_libur_mingguan => [0]

    $response = $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.index'));

    $response->assertOk();
    $response->assertViewHas('sesiList', fn ($list) => $list->count() === 0);
    expect(SesiPembelajaran::where('kelas_id', $kelas->id)->count())->toBe(0);
});
```

- [x] **Step 9: Run tests to verify they fail (before Part A/B) or pass (if run after Steps 1-7 are already done)**

Since Parts A and B were already implemented in Steps 1-7 above, these tests should PASS immediately. Run:

Run: `php artisan test tests/Feature/Akademik/JurnalKbmAdaptiveTest.php`
Expected: PASS — 3 tests. If any fail, re-check Steps 3 and 6 were applied exactly as written before debugging further.

- [x] **Step 10: Re-run the full scoped regression set for this task**

Run: `php artisan test tests/Feature/Guru/JurnalKbmControllerTest.php tests/Feature/Guru/JurnalKbmTenantScopeTest.php tests/Feature/Guru/RekapKehadiranControllerTest.php tests/Unit/Services/SesiPembelajaranGeneratorTest.php tests/Unit/Services/SesiTematikGeneratorTest.php tests/Unit/Domains/Akademik/ModePembelajaranTest.php tests/Unit/Domains/Akademik/SesiPembelajaranIsTematikTest.php tests/Feature/Akademik/JurnalKbmAdaptiveTest.php`
Expected: PASS — all green, no failures.

- [x] **Step 11: Commit**

```bash
git add app/Domains/Akademik/Actions/Presensi/GenerateSesiHarianAction.php app/Domains/Akademik/Models/SesiPembelajaran.php tests/Unit/Domains/Akademik/SesiPembelajaranIsTematikTest.php tests/Feature/Akademik/JurnalKbmAdaptiveTest.php
git commit -m "feat(akademik): sambungkan generator tematik ke GenerateSesiHarianAction, tambah isTematik() accessor"
```

---

## Task 4: Handoff Log + Master Plan Update

**Files:**
- Create: `.agents/logs/2026-08-18-2200-akademik-03b-mode-tematik-kbtksd.md`
- Modify: `.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md`

**Interfaces:** None — this is a documentation-only task, no code.

- [x] **Step 1: Ask the human whether to run the full test suite now**

Before writing the handoff log, ask: "Semua task selesai. Mau saya jalankan `php artisan test` (full suite, ~350-400 detik) sekarang sebagai verifikasi akhir sebelum handoff log ditulis?" Wait for an explicit yes/no. If yes, run `php artisan test` and record the exact pass/fail counts and duration in the handoff log's "Hasil akhir" section (Step 2 below). If no, write the handoff log noting that the full suite was not run this session, listing the exact scoped commands (from Task 3, Step 10) that WERE run and their results instead.

- [x] **Step 2: Write the handoff log**

Create `.agents/logs/2026-08-18-2200-akademik-03b-mode-tematik-kbtksd.md`. Use `.agents/logs/2026-08-18-1651-akademik-03a-migrasi-jurnal-presensi.md` as a structural reference (same 3-section format: "Apa yang Dikerjakan", "Keputusan Penting yang Diambil", "Hal yang Perlu Direview Manusia / Tahap Selanjutnya") — read that file first, then write this one following the same section headers, filled in with this sub-task's actual content: the 4 tasks completed (enum, service, action wiring + accessor, docs), the mode-detection whitelist decision and why it's negative rather than positive, the reuse-vs-separate-model decision inherited from the spec, and the known accepted limitation (no DB-level duplicate protection for Tematik rows). List manual browser verification as still needed (login as a KB/TK/SD wali kelas guru, confirm 1 sesi/day, fill it, check Rekap Kehadiran picks it up) since this plan's executor has no browser access.

- [x] **Step 3: Update the master plan table**

Open `.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md`. Find this table row (currently near the top, in the navigation table):

```
| **03b** | **Mode Tematik/Harian KB-TK-SD (baru, menyusul 03a)** | [`.agents/specs/2026-08-18-2200-akademik-03b-mode-tematik-kbtksd.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-18-2200-akademik-03b-mode-tematik-kbtksd.md) | `.agents/plans/2026-08-18-2200-akademik-03b-mode-tematik-kbtksd.md` (belum dibuat) | `.agents/logs/2026-08-18-2200-akademik-03b-mode-tematik-kbtksd.md` (belum dibuat) | 🟡 SPEC DRAFT |
```

Replace it with:

```
| **03b** | **Mode Tematik/Harian KB-TK-SD (baru, menyusul 03a)** | [`.agents/specs/2026-08-18-2200-akademik-03b-mode-tematik-kbtksd.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-18-2200-akademik-03b-mode-tematik-kbtksd.md) | [`.agents/plans/2026-08-18-2200-akademik-03b-mode-tematik-kbtksd.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-18-2200-akademik-03b-mode-tematik-kbtksd.md) | [`.agents/logs/2026-08-18-2200-akademik-03b-mode-tematik-kbtksd.md`](file:///d:/laragon/www/pintera-app/.agents/logs/2026-08-18-2200-akademik-03b-mode-tematik-kbtksd.md) | 🟢 **SELESAI (COMPLETED)** |
```

Also find the FASE 3 checklist section further down in the same file (search for `### 📝 FASE 3: Jurnal KBM Adaptif`) and check off any remaining unchecked `- [ ]` boxes under `**3.3. HTTP & UI Layer Adaptif`" and `**3.4. Pengujian Otomatis Fase 3`" that this sub-task completed — read the surrounding checklist items first to confirm which specific sub-bullets this plan's work satisfies before checking them, since some FASE 3 sub-bullets (e.g. anything about E-Rapor integration) are out of scope for 03b and must stay unchecked.

- [x] **Step 4: Commit**

```bash
git add .agents/logs/2026-08-18-2200-akademik-03b-mode-tematik-kbtksd.md .agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md
git commit -m "docs(akademik): handoff log sub-task 03b - mode tematik/harian KB-TK-SD"
```
