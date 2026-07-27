# Tahap 7 Remediasi (IDOR & Skema NilaiSiswa) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix 3 real cross-lembaga IDOR/tenant-scoping bugs and a permission-seeding gap introduced when Tahap 7 (Asesmen Sumatif) shipped directly to `main` without a worktree or review cycle, and correct `NilaiSiswa`'s schema to match the original plan's per-`KomponenPenilaian` scoring design instead of the single-score-per-`Asesmen` shape that was actually shipped. Also fixes one small pre-existing bug in Tahap 5's `SesiPembelajaranController` found during the audit.

**Architecture:** Eight independently-testable tasks: (1) permission seeding, (2) a one-line Tahap 5 query-precedence fix, (3) IDOR fix in `KomponenPenilaianController`, (4) IDOR fix in `Guru\AsesmenController::store()`, (5) `nilai_siswa` schema migration to per-komponen scoring, (6) rewiring the guru grading UI + `RaporController` to the new schema, (7) updating `AcademicDummySeeder` to match, (8) updating/adding regression tests for everything above.

**Tech Stack:** Laravel 12, Pest 4, Alpine.js, Blade, MySQL 8.0.

## Global Constraints

- All new/changed UI copy stays in Indonesian, matching every existing string in these files.
- Any controller validating a foreign key that points at a **tenant-scoped** model (has `BelongsToTenant`) must resolve it via `Model::find($id)` + `abort(404)` when null — **never** a raw `exists:table,column` validation rule, because `exists:` bypasses Eloquent's `TenantScope` global scope entirely (queries the raw table). This is a standing rule in this project, violated 4 times before Tahap 7 and now a 5th/6th/7th time within Tahap 7 itself.
- Any action that combines two independently-tenant-scoped models in one write (e.g. linking a `MataPelajaran` to a `Semester`) must explicitly compare their `lembaga_id` and `abort(404)` on mismatch — resolving each individually through its own tenant scope is not sufficient for a yayasan-scoped actor with no active lembaga selected (session `active_lembaga_id` is null), since `TenantScope` adds no constraint at all in that state.
- Follow the TailAdmin visual style already used throughout these views (`rounded-2xl border border-gray-200 bg-white shadow-card`, `<x-input-label>`, `<x-input-error>`, `<x-primary-button>`) — do not introduce a different token set.
- Every task's migration/model/controller change must keep `php artisan test` green end-to-end, not just its own new test file.

---

### Task 1: Wire academic permissions to real roles in `RoleSeeder`

**Files:**
- Modify: `database/seeders/RoleSeeder.php`
- Test: `tests/Unit/Seeders/RoleSeederAcademicPermissionsTest.php`

**Interfaces:**
- Consumes: existing permission strings `presensi.isi`, `asesmen.kelola`, `komponen-penilaian.kelola`, `rapor.view` (already registered automatically by `permissions:sync`, which scans `$this->authorize()` calls — see `app/Console/Commands/SyncPermissions.php`).
- Produces: nothing new consumed by later tasks — this task is standalone.

**Context:** Confirmed by reading `RoleSeeder.php` that `guru` gets **zero** permissions from any `if ($name === ...)` branch, and no role at all gets `komponen-penilaian.kelola` or `rapor.view` except `yayasan_super_admin` (which gets every permission automatically via `syncPermissions(Permission::pluck('name')->all())`). Only `AcademicDummySeeder` (a dev-only dummy-data seeder, not part of the real seed path) grants `presensi.isi`/`asesmen.kelola` to `guru`. On a fresh production `php artisan db:seed`, no lembaga-scoped staff member can use presensi, jurnal, asesmen, TP management, or rapor at all. `kepala_sekolah` is the closest existing lembaga-scoped academic role in `RoleSeeder`, so it is the target for the TP/rapor permissions (note: a dedicated `admin_akademik` role is referenced pervasively across this project's *tests* going back to Tahap 1, but was never actually added to `RoleSeeder` — that is a larger, pre-existing gap spanning multiple already-shipped tahap, out of scope for this remediation; flagged here for a future dedicated pass).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Seeders/RoleSeederAcademicPermissionsTest.php`:

```php
<?php

use App\Models\Role;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;

it('grants presensi and asesmen permissions to the guru role', function () {
    Artisan::call('permissions:sync');
    (new RoleSeeder())->run();

    $guru = Role::where('name', 'guru')->firstOrFail();

    expect($guru->hasPermissionTo('presensi.isi'))->toBeTrue();
    expect($guru->hasPermissionTo('asesmen.kelola'))->toBeTrue();
});

it('grants komponen-penilaian and rapor permissions to kepala_sekolah', function () {
    Artisan::call('permissions:sync');
    (new RoleSeeder())->run();

    $kepsek = Role::where('name', 'kepala_sekolah')->firstOrFail();

    expect($kepsek->hasPermissionTo('komponen-penilaian.kelola'))->toBeTrue();
    expect($kepsek->hasPermissionTo('rapor.view'))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Seeders/RoleSeederAcademicPermissionsTest.php`
Expected: FAIL (`hasPermissionTo('presensi.isi')` returns `false`)

- [ ] **Step 3: Update `RoleSeeder`**

In `database/seeders/RoleSeeder.php`, add two new `if` branches inside the `foreach` loop (after the existing `kepala_sekolah` branch):

```php
            if ($name === 'kepala_sekolah') {
                $role->givePermissionTo([
                    'spmb-pendaftaran.view', 'spmb-pendaftaran.verifikasi-dokumen', 'spmb-pendaftaran.nilai-seleksi',
                    'spmb-pendaftaran.tetapkan-keputusan', 'spmb-pendaftaran.terbitkan-sk',
                    'tagihan.view',
                    'komponen-penilaian.kelola', 'rapor.view',
                ]);
            }

            if ($name === 'guru') {
                $role->givePermissionTo([
                    'presensi.isi', 'asesmen.kelola',
                ]);
            }
```

(This just appends `komponen-penilaian.kelola`/`rapor.view` to the existing `kepala_sekolah` array and adds a brand-new `guru` branch — the `guru` role previously had no branch at all.)

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Seeders/RoleSeederAcademicPermissionsTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add database/seeders/RoleSeeder.php tests/Unit/Seeders/RoleSeederAcademicPermissionsTest.php
git commit -m "fix: grant presensi/asesmen/komponen-penilaian/rapor permissions to real roles"
```

---

### Task 2: Fix unparenthesized tenant-scope-bypassing OR in `SesiPembelajaranController` (Tahap 5 audit finding)

**Files:**
- Modify: `app/Http/Controllers/Guru/SesiPembelajaranController.php:26-28`
- Test: `tests/Feature/Guru/SesiPembelajaranTenantScopeTest.php`

**Interfaces:** Standalone — no dependency on other tasks.

**Context:** `Kelas::whereHas('jadwalPelajaran', fn ($q) => $q->where('guru_id', $guru->id))->orWhere('wali_kelas_guru_id', $guru->id)->get()` compiles to `WHERE EXISTS(...) OR wali_kelas_guru_id = ? AND lembaga_id = ?` — MySQL's `AND`-binds-tighter-than-`OR` precedence means `Kelas`'s own `TenantScope` (`lembaga_id = ?`, appended as a plain `->where()`) only constrains the second branch. The `whereHas` branch is currently safe only because `JadwalPelajaranController::store()` independently prevents a guru from being assigned to a foreign lembaga's `JadwalPelajaran` — an incidental protection, not a guarantee.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Guru/SesiPembelajaranTenantScopeTest.php`:

```php
<?php

use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\PolaJam;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('never lists a kelas from another lembaga even via a raw jadwal_pelajaran row bypassing tenant checks', function () {
    Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru_lintas_test', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['presensi.isi']);

    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);

    $guruUser = User::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $guruUser->assignRole($role);
    $guru = Guru::factory()->create(['lembaga_id' => $lembagaSaya->id, 'user_id' => $guruUser->id]);

    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranLain->id, 'status_aktif' => true]);
    $polaLain = PolaJam::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $jamLain = JamPelajaran::factory()->create(['pola_jam_id' => $polaLain->id]);
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $tahunAjaranLain->id, 'pola_jam_id' => $polaLain->id]);
    $mapelLain = MataPelajaran::factory()->create(['lembaga_id' => $lembagaLain->id]);

    // Simulate a raw/legacy row where this guru is (incorrectly) attached to a foreign lembaga's jadwal.
    JadwalPelajaran::withoutGlobalScopes()->create([
        'kelas_id' => $kelasLain->id, 'jam_pelajaran_id' => $jamLain->id, 'mata_pelajaran_id' => $mapelLain->id,
        'guru_id' => $guru->id, 'semester_id' => $semesterLain->id,
    ]);

    $response = $this->actingAs($guruUser)->get(route('guru.sesi.index'));

    $response->assertOk();
    $response->assertDontSee($kelasLain->nama);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Guru/SesiPembelajaranTenantScopeTest.php`
Expected: FAIL — the foreign-lembaga kelas name leaks into the response (or a duplicate-registration/500 error occurs from `JadwalPelajaran` not itself being tenant-scoped — either way, confirms the gap before the fix).

- [ ] **Step 3: Fix the query**

In `app/Http/Controllers/Guru/SesiPembelajaranController.php`, replace lines 26-28:

```php
            $kelasList = Kelas::whereHas('jadwalPelajaran', fn ($q) => $q->where('guru_id', $guru->id))
                ->orWhere('wali_kelas_guru_id', $guru->id)
                ->get();
```

with:

```php
            $kelasList = Kelas::where(function ($query) use ($guru) {
                $query->whereHas('jadwalPelajaran', fn ($q) => $q->where('guru_id', $guru->id))
                    ->orWhere('wali_kelas_guru_id', $guru->id);
            })->get();
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Guru/SesiPembelajaranTenantScopeTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Guru/SesiPembelajaranController.php tests/Feature/Guru/SesiPembelajaranTenantScopeTest.php
git commit -m "fix: parenthesize guru kelas-list OR so TenantScope applies to both branches"
```

---

### Task 3: Fix cross-lembaga IDOR in `KomponenPenilaianController`

**Files:**
- Modify: `app/Http/Controllers/Admin/KomponenPenilaianController.php`
- Modify: `tests/Feature/Admin/KomponenPenilaianCrudTest.php`

**Interfaces:** Standalone — no dependency on other tasks.

**Context:** `KomponenPenilaian` has no `lembaga_id`/`BelongsToTenant` of its own (only transitively via `mata_pelajaran_id`/`semester_id`, both of which point at directly tenant-scoped models). `index()` currently has zero tenant filtering (leaks every lembaga's TP data to any `admin_akademik`/`kepala_sekolah`). `store()` validates both FKs with raw `exists:` rules and never checks they belong to the same lembaga (the same "Lembaga-A linked to Lembaga-B" bug class this project shipped once before in Tahap 4b).

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Admin/KomponenPenilaianCrudTest.php` (append after the existing `it('creates a komponen penilaian', ...)` block):

```php
it('does not list another lembaga\'s komponen penilaian', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $tahunAjaranSaya = TahunAjaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $semesterSaya = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranSaya->id]);
    $mapelSaya = MataPelajaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapelSaya->id, 'semester_id' => $semesterSaya->id, 'kode' => 'TP-SAYA']);

    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranLain->id]);
    $mapelLain = MataPelajaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapelLain->id, 'semester_id' => $semesterLain->id, 'kode' => 'TP-LAIN']);

    $manager = actingAsKomponenManager($lembagaSaya);

    $response = $this->actingAs($manager)->get(route('admin.komponen-penilaian.index'));

    $response->assertOk();
    $response->assertSee('TP-SAYA');
    $response->assertDontSee('TP-LAIN');
});

it('rejects creating a komponen penilaian mixing a mata pelajaran and semester from different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $mapelSaya = MataPelajaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranLain->id]);

    $manager = actingAsKomponenManager($lembagaSaya);

    $this->actingAs($manager)->post(route('admin.komponen-penilaian.store'), [
        'mata_pelajaran_id' => $mapelSaya->id,
        'semester_id' => $semesterLain->id,
        'deskripsi' => 'Campur lembaga',
    ])->assertNotFound();

    expect(KomponenPenilaian::where('deskripsi', 'Campur lembaga')->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php`
Expected: FAIL — `assertDontSee('TP-LAIN')` fails (leaked), and the mixed-lembaga POST succeeds instead of 404ing.

- [ ] **Step 3: Fix the controller**

Replace `app/Http/Controllers/Admin/KomponenPenilaianController.php` entirely with:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\KomponenPenilaian;
use App\Models\MataPelajaran;
use App\Models\Semester;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class KomponenPenilaianController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('komponen-penilaian.kelola');

        return view('admin.komponen-penilaian.index', [
            'komponenList' => KomponenPenilaian::whereHas('mataPelajaran')->with(['mataPelajaran', 'semester'])->orderByDesc('id')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('komponen-penilaian.kelola');

        return view('admin.komponen-penilaian.create', [
            'mataPelajaranList' => MataPelajaran::orderBy('nama')->get(),
            'semesterList' => Semester::orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('komponen-penilaian.kelola');

        $data = $request->validate([
            'mata_pelajaran_id' => ['required', 'integer'],
            'semester_id' => ['required', 'integer'],
            'kode' => ['nullable', 'string', 'max:50'],
            'deskripsi' => ['required', 'string'],
            'kktp' => ['nullable', 'string'],
        ]);

        $mataPelajaran = MataPelajaran::find($data['mata_pelajaran_id']);
        $semester = Semester::find($data['semester_id']);

        abort_if($mataPelajaran === null || $semester === null, 404);
        abort_if($mataPelajaran->lembaga_id !== $semester->lembaga_id, 404);

        KomponenPenilaian::create($data);

        return redirect()->route('admin.komponen-penilaian.index')->with('status', 'Komponen penilaian (TP) berhasil disimpan.');
    }
}
```

(`index()`'s `whereHas('mataPelajaran')` filters `KomponenPenilaian` down to rows whose linked `MataPelajaran` passes `MataPelajaran`'s own `TenantScope` — Laravel applies a related model's global scopes inside `whereHas` subqueries by default, so this is sufficient without adding a `lembaga_id` column to `KomponenPenilaian` itself.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/KomponenPenilaianController.php tests/Feature/Admin/KomponenPenilaianCrudTest.php
git commit -m "fix: close cross-lembaga IDOR in KomponenPenilaianController"
```

---

### Task 4: Fix cross-lembaga IDOR in `Guru\AsesmenController::store()` + enforce v1 `jenis` scope

**Files:**
- Modify: `app/Http/Controllers/Guru/AsesmenController.php:63-109`
- Modify: `tests/Feature/Guru/AsesmenControllerTest.php`

**Interfaces:** Standalone for the IDOR/validation fix. Task 6 will further modify this same file's `store()`/`show()`/`updateNilai()` for the schema change — do this task first so Task 6 starts from a secure baseline.

**Context:** `store()` validates `kelas_id`/`mata_pelajaran_id`/`semester_id` with raw `exists:` rules and never re-checks the guru actually teaches that combination via `JadwalPelajaran` — a guru with `asesmen.kelola` can currently create (and auto-seed `NilaiSiswa` for) an `Asesmen` against any kelas/mapel in any lembaga. Separately, `jenis` is validated as `'string'` only, so a crafted POST can create a `diagnostik_kognitif`/`formatif` `Asesmen` even though the UI dropdown only ever renders the 3 v1 sumatif options.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Guru/AsesmenControllerTest.php` (append):

```php
it('rejects creating an asesmen for a kelas/mapel/semester combination the guru does not teach', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $tahunAjaranSaya = TahunAjaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $semesterSaya = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranSaya->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $user = actingAsGuruAsesmen($guru);

    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranLain->id]);
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $tahunAjaranLain->id]);
    $mapelLain = MataPelajaran::factory()->create(['lembaga_id' => $lembagaLain->id]);

    $this->actingAs($user)->post(route('guru.asesmen.store'), [
        'kelas_id' => $kelasLain->id,
        'mata_pelajaran_id' => $mapelLain->id,
        'semester_id' => $semesterSaya->id,
        'jenis' => JenisAsesmen::SumatifLingkupMateri->value,
        'judul' => 'Coba Asesmen Kelas Lain',
        'tanggal' => now()->toDateString(),
    ])->assertForbidden();

    expect(Asesmen::where('judul', 'Coba Asesmen Kelas Lain')->exists())->toBeFalse();
});

it('rejects a jenis outside the v1-supported sumatif options', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = actingAsGuruAsesmen($guru);

    JadwalPelajaran::create([
        'kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $guru->id, 'semester_id' => $semester->id,
    ]);

    $this->actingAs($user)->post(route('guru.asesmen.store'), [
        'kelas_id' => $kelas->id,
        'mata_pelajaran_id' => $mapel->id,
        'semester_id' => $semester->id,
        'jenis' => JenisAsesmen::Formatif->value,
        'judul' => 'Coba Jenis Formatif',
        'tanggal' => now()->toDateString(),
    ])->assertSessionHasErrors('jenis');

    expect(Asesmen::where('judul', 'Coba Jenis Formatif')->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Guru/AsesmenControllerTest.php`
Expected: FAIL — both new tests fail (the cross-lembaga POST succeeds instead of 403ing; the `Formatif` POST succeeds instead of a validation error).

- [ ] **Step 3: Fix the controller**

In `app/Http/Controllers/Guru/AsesmenController.php`, replace the `store()` method (lines 63-109) with:

```php
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('asesmen.kelola');

        $guru = $request->user()->guru;
        abort_if(!$guru, 403, 'Profil guru tidak ditemukan.');

        $data = $request->validate([
            'kelas_id' => ['required', 'integer'],
            'mata_pelajaran_id' => ['required', 'integer'],
            'semester_id' => ['required', 'integer'],
            'jenis' => ['required', 'in:sumatif_lingkup_materi,sumatif_akhir_semester,sumatif_akhir_jenjang'],
            'judul' => ['required', 'string', 'max:255'],
            'tanggal' => ['required', 'date'],
            'komponen_id' => ['nullable', 'array'],
            'komponen_id.*' => ['integer'],
        ]);

        $mengajarKombinasiIni = JadwalPelajaran::where('guru_id', $guru->id)
            ->where('kelas_id', $data['kelas_id'])
            ->where('mata_pelajaran_id', $data['mata_pelajaran_id'])
            ->where('semester_id', $data['semester_id'])
            ->exists();

        abort_unless($mengajarKombinasiIni, 403, 'Anda tidak mengajar kombinasi kelas dan mata pelajaran ini.');

        $komponenIds = !empty($data['komponen_id'])
            ? KomponenPenilaian::whereIn('id', $data['komponen_id'])->where('mata_pelajaran_id', $data['mata_pelajaran_id'])->pluck('id')
            : collect();

        $asesmen = DB::transaction(function () use ($guru, $data, $komponenIds) {
            $asesmen = Asesmen::create([
                'guru_id' => $guru->id,
                'kelas_id' => $data['kelas_id'],
                'mata_pelajaran_id' => $data['mata_pelajaran_id'],
                'semester_id' => $data['semester_id'],
                'jenis' => JenisAsesmen::from($data['jenis']),
                'judul' => $data['judul'],
                'tanggal' => $data['tanggal'],
            ]);

            if ($komponenIds->isNotEmpty()) {
                $asesmen->komponenPenilaian()->attach($komponenIds);
            }

            // Populate initial empty NilaiSiswa rows for all enrolled students
            $siswaList = $asesmen->kelas->siswa()->get();
            foreach ($siswaList as $siswa) {
                NilaiSiswa::firstOrCreate([
                    'asesmen_id' => $asesmen->id,
                    'siswa_id' => $siswa->id,
                ]);
            }

            return $asesmen;
        });

        return redirect()->route('guru.asesmen.show', $asesmen)->with('status', 'Asesmen berhasil dibuat. Silakan masukkan nilai peserta didik.');
    }
```

(The `NilaiSiswa::firstOrCreate` seeding loop here still uses the OLD one-row-per-siswa shape — Task 6 replaces this block again once the schema is per-komponen. Doing the IDOR/validation fix first, in isolation, keeps this task's diff reviewable on its own.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Guru/AsesmenControllerTest.php`
Expected: PASS (all tests, including the 2 new ones)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Guru/AsesmenController.php tests/Feature/Guru/AsesmenControllerTest.php
git commit -m "fix: enforce JadwalPelajaran ownership and v1 jenis scope on Asesmen creation"
```

---

### Task 5: Migrate `nilai_siswa` to per-`KomponenPenilaian` scoring

**Files:**
- Modify: `database/migrations/2026_07_25_131000_create_asesmen_tables.php`
- Modify: `app/Models/NilaiSiswa.php`
- Modify: `database/factories/NilaiSiswaFactory.php`
- Test: `tests/Unit/Models/NilaiSiswaTest.php`

**Interfaces:**
- Produces: `App\Models\NilaiSiswa` (`$fillable = ['asesmen_id', 'siswa_id', 'komponen_penilaian_id', 'nilai_angka', 'predikat', 'catatan']`, unique on `['asesmen_id', 'siswa_id', 'komponen_penilaian_id']`). Task 6 and Task 7 depend on this exact shape.

**Context:** This migration was applied fewer than 3 days ago against local dev data only (`AcademicDummySeeder` dummy rows, no real end-user data exists yet) — editing the file in place and rolling back/re-migrating is safe and avoids stacking a patch migration on top of a schema that was never functionally correct. `komponen_penilaian_id` is NOT NULL because per-plan design, a score is always against a specific TP/komponen, not the `Asesmen` as a whole.

- [ ] **Step 1: Write the failing test**

Replace `tests/Unit/Models/NilaiSiswaTest.php` entirely with:

```php
<?php

use App\Enums\JenisAsesmen;
use App\Models\Asesmen;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\KomponenPenilaian;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

function siapkanAsesmenUntukNilaiSiswaTest(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen = Asesmen::create([
        'guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id,
        'jenis' => JenisAsesmen::SumatifAkhirSemester, 'judul' => 'UAS', 'tanggal' => '2026-12-10',
    ]);

    return compact('siswa', 'asesmen', 'komponen');
}

it('stores a nilai_angka for a siswa on a specific komponen of an asesmen', function () {
    ['siswa' => $siswa, 'asesmen' => $asesmen, 'komponen' => $komponen] = siapkanAsesmenUntukNilaiSiswaTest();

    $nilai = NilaiSiswa::create([
        'asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id,
        'nilai_angka' => 88, 'predikat' => null, 'catatan' => null,
    ]);

    expect($nilai->fresh()->komponenPenilaian->id)->toBe($komponen->id);
    expect($nilai->fresh()->nilai_angka)->toBe(88);
});

it('allows nilai_angka to be null with only predikat/catatan for narrative-style scoring', function () {
    ['siswa' => $siswa, 'asesmen' => $asesmen, 'komponen' => $komponen] = siapkanAsesmenUntukNilaiSiswaTest();

    $nilai = NilaiSiswa::create([
        'asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id,
        'nilai_angka' => null, 'predikat' => 'Berkembang Sesuai Harapan', 'catatan' => 'Aktif berinteraksi dengan teman sebaya',
    ]);

    expect($nilai->fresh()->nilai_angka)->toBeNull();
    expect($nilai->fresh()->predikat)->toBe('Berkembang Sesuai Harapan');
});

it('enforces one nilai row per siswa per komponen per asesmen', function () {
    ['siswa' => $siswa, 'asesmen' => $asesmen, 'komponen' => $komponen] = siapkanAsesmenUntukNilaiSiswaTest();

    NilaiSiswa::create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 80]);

    expect(fn () => NilaiSiswa::create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 90]))
        ->toThrow(Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Models/NilaiSiswaTest.php`
Expected: FAIL — `komponen_penilaian_id` column/relation doesn't exist yet.

- [ ] **Step 3: Edit the migration**

In `database/migrations/2026_07_25_131000_create_asesmen_tables.php`, replace the `nilai_siswa` block:

```php
        Schema::create('nilai_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asesmen_id')->constrained('asesmen')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->decimal('skor', 5, 2)->nullable(); // 0 - 100 with decimals
            $table->text('catatan')->nullable(); // deskripsi kualitatif
            $table->timestamps();

            $table->unique(['asesmen_id', 'siswa_id']);
        });
```

with:

```php
        Schema::create('nilai_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asesmen_id')->constrained('asesmen')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('komponen_penilaian_id')->constrained('komponen_penilaian')->cascadeOnDelete();
            $table->unsignedTinyInteger('nilai_angka')->nullable(); // 0 - 100
            $table->string('predikat')->nullable(); // narrative-style grading (e.g. PAUD aspek perkembangan)
            $table->text('catatan')->nullable(); // deskripsi kualitatif
            $table->timestamps();

            $table->unique(['asesmen_id', 'siswa_id', 'komponen_penilaian_id'], 'nilai_siswa_unik');
        });
```

Run (against the local dev DB, since this migration was already applied there with the old shape):
```bash
php artisan migrate:rollback --step=1
php artisan migrate
```
Expected: the `nilai_siswa` table is dropped and recreated with the new columns; `asesmen`/`asesmen_komponen_penilaian` are unaffected (rollback drops all 3 tables this one migration file creates, migrate recreates all 3 identically except `nilai_siswa`).

- [ ] **Step 4: Update the model**

Replace `app/Models/NilaiSiswa.php` entirely with:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NilaiSiswa extends Model
{
    use HasFactory;

    protected $table = 'nilai_siswa';

    protected $fillable = [
        'asesmen_id',
        'siswa_id',
        'komponen_penilaian_id',
        'nilai_angka',
        'predikat',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'nilai_angka' => 'integer',
        ];
    }

    public function asesmen(): BelongsTo
    {
        return $this->belongsTo(Asesmen::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function komponenPenilaian(): BelongsTo
    {
        return $this->belongsTo(KomponenPenilaian::class);
    }
}
```

- [ ] **Step 5: Update the factory**

Replace `database/factories/NilaiSiswaFactory.php` entirely with:

```php
<?php

namespace Database\Factories;

use App\Models\Asesmen;
use App\Models\KomponenPenilaian;
use App\Models\NilaiSiswa;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

class NilaiSiswaFactory extends Factory
{
    protected $model = NilaiSiswa::class;

    public function definition(): array
    {
        return [
            'asesmen_id' => Asesmen::factory(),
            'siswa_id' => Siswa::factory(),
            'komponen_penilaian_id' => KomponenPenilaian::factory(),
            'nilai_angka' => $this->faker->numberBetween(60, 100),
            'predikat' => null,
            'catatan' => $this->faker->optional(0.7)->sentence(5),
        ];
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Unit/Models/NilaiSiswaTest.php`
Expected: PASS (3 tests)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_25_131000_create_asesmen_tables.php app/Models/NilaiSiswa.php database/factories/NilaiSiswaFactory.php tests/Unit/Models/NilaiSiswaTest.php
git commit -m "fix: migrate nilai_siswa to per-komponen-penilaian scoring per original plan"
```

---

### Task 6: Rewire guru grading UI + `RaporController` for per-komponen `nilai_angka`

**Files:**
- Modify: `app/Http/Controllers/Guru/AsesmenController.php` (`store()`'s `NilaiSiswa` seeding, `show()`, `updateNilai()`)
- Modify: `resources/views/guru/asesmen/create.blade.php`
- Modify: `resources/views/guru/asesmen/show.blade.php`
- Modify: `app/Http/Controllers/Admin/RaporController.php`
- Modify: `tests/Feature/Guru/AsesmenControllerTest.php`

**Interfaces:**
- Consumes: `App\Models\NilaiSiswa` per-komponen shape from Task 5.

**Context:** The shipped `show.blade.php` renders one `skor`+`catatan` input per siswa (no komponen dimension at all). With Task 5's schema change, a score always belongs to a specific `(siswa, asesmen, komponen)` triple, so the grading table becomes a matrix: one row per siswa, one nilai+catatan input pair per komponen column. `komponen_id` on creation becomes mandatory (an `Asesmen` with zero komponen has nothing to grade against).

- [ ] **Step 1: Write the failing test**

Replace the `it('allows guru to create an asesmen and grade students', ...)` test in `tests/Feature/Guru/AsesmenControllerTest.php` with:

```php
it('allows guru to create an asesmen and grade students per komponen', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'status_aktif' => true]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = actingAsGuruAsesmen($guru);

    JadwalPelajaran::create([
        'kelas_id' => $kelas->id,
        'jam_pelajaran_id' => $jam->id,
        'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $guru->id,
        'semester_id' => $semester->id,
    ]);

    $komponen = KomponenPenilaian::factory()->create([
        'mata_pelajaran_id' => $mapel->id,
        'semester_id' => $semester->id,
    ]);

    $siswa1 = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $siswa2 = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    $response = $this->actingAs($user)->post(route('guru.asesmen.store'), [
        'kelas_id' => $kelas->id,
        'mata_pelajaran_id' => $mapel->id,
        'semester_id' => $semester->id,
        'jenis' => JenisAsesmen::SumatifLingkupMateri->value,
        'judul' => 'Ulangan Bab 1',
        'tanggal' => now()->toDateString(),
        'komponen_id' => [$komponen->id],
    ]);

    $asesmen = Asesmen::first();
    expect($asesmen)->not->toBeNull();
    $response->assertRedirect(route('guru.asesmen.show', $asesmen));

    $this->actingAs($user)->put(route('guru.asesmen.update-nilai', $asesmen), [
        'nilai' => [
            $siswa1->id => [$komponen->id => ['nilai_angka' => '85', 'catatan' => 'Baik sekali']],
            $siswa2->id => [$komponen->id => ['nilai_angka' => '90', 'catatan' => 'Sempurna']],
        ],
    ])->assertRedirect(route('guru.asesmen.show', $asesmen));

    expect(NilaiSiswa::where('asesmen_id', $asesmen->id)->where('siswa_id', $siswa1->id)->where('komponen_penilaian_id', $komponen->id)->value('nilai_angka'))->toBe(85);
    expect(NilaiSiswa::where('asesmen_id', $asesmen->id)->where('siswa_id', $siswa2->id)->where('komponen_penilaian_id', $komponen->id)->value('nilai_angka'))->toBe(90);
});

it('ignores a nilai submitted for a komponen not attached to the asesmen', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = actingAsGuruAsesmen($guru);
    $asesmen = Asesmen::factory()->create(['guru_id' => $guru->id]);
    $komponenAsing = KomponenPenilaian::factory()->create();
    $siswa = Siswa::factory()->create();

    $this->actingAs($user)->put(route('guru.asesmen.update-nilai', $asesmen), [
        'nilai' => [
            $siswa->id => [$komponenAsing->id => ['nilai_angka' => '99']],
        ],
    ])->assertRedirect(route('guru.asesmen.show', $asesmen));

    expect(NilaiSiswa::where('komponen_penilaian_id', $komponenAsing->id)->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Guru/AsesmenControllerTest.php`
Expected: FAIL — `update-nilai` still expects the old `['skor' => ..., 'catatan' => ...]` shape per siswa, not nested per-komponen.

- [ ] **Step 3: Update the controller**

In `app/Http/Controllers/Guru/AsesmenController.php`:

Replace the `NilaiSiswa::firstOrCreate` seeding loop inside `store()`'s transaction (added in Task 4) with a per-komponen version:

```php
            // Populate initial empty NilaiSiswa rows for all enrolled students, per komponen
            $siswaList = $asesmen->kelas->siswa()->get();
            foreach ($siswaList as $siswa) {
                foreach ($komponenIds as $komponenId) {
                    NilaiSiswa::firstOrCreate([
                        'asesmen_id' => $asesmen->id,
                        'siswa_id' => $siswa->id,
                        'komponen_penilaian_id' => $komponenId,
                    ]);
                }
            }
```

Replace `show()` entirely:

```php
    public function show(Asesmen $asesmen): View
    {
        $this->authorize('asesmen.kelola');
        $this->authorizeMilikGuru($asesmen);

        $komponenList = $asesmen->komponenPenilaian;
        $siswaList = $asesmen->kelas->siswa()->orderBy('nama_lengkap')->get();

        // Ensure any newly added student/komponen combination has a NilaiSiswa row
        foreach ($siswaList as $siswa) {
            foreach ($komponenList as $komponen) {
                NilaiSiswa::firstOrCreate([
                    'asesmen_id' => $asesmen->id,
                    'siswa_id' => $siswa->id,
                    'komponen_penilaian_id' => $komponen->id,
                ]);
            }
        }

        $nilaiMatrix = NilaiSiswa::where('asesmen_id', $asesmen->id)
            ->get()
            ->keyBy(fn ($n) => $n->siswa_id.'-'.$n->komponen_penilaian_id);

        return view('guru.asesmen.show', [
            'asesmen' => $asesmen->load(['kelas', 'mataPelajaran', 'semester']),
            'komponenList' => $komponenList,
            'siswaList' => $siswaList,
            'nilaiMatrix' => $nilaiMatrix,
        ]);
    }
```

Replace `updateNilai()` entirely:

```php
    public function updateNilai(Request $request, Asesmen $asesmen): RedirectResponse
    {
        $this->authorize('asesmen.kelola');
        $this->authorizeMilikGuru($asesmen);

        $komponenIds = $asesmen->komponenPenilaian()->pluck('komponen_penilaian.id');

        $data = $request->validate([
            'nilai' => ['required', 'array'],
            'nilai.*.*.nilai_angka' => ['nullable', 'integer', 'min:0', 'max:100'],
            'nilai.*.*.catatan' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($asesmen, $data, $komponenIds) {
            foreach ($data['nilai'] as $siswaId => $perKomponen) {
                foreach ($perKomponen as $komponenId => $values) {
                    if (!$komponenIds->contains((int) $komponenId)) {
                        continue;
                    }

                    NilaiSiswa::updateOrCreate(
                        ['asesmen_id' => $asesmen->id, 'siswa_id' => $siswaId, 'komponen_penilaian_id' => $komponenId],
                        [
                            'nilai_angka' => isset($values['nilai_angka']) && $values['nilai_angka'] !== '' ? (int) $values['nilai_angka'] : null,
                            'catatan' => $values['catatan'] ?? null,
                        ]
                    );
                }
            }
        });

        return redirect()->route('guru.asesmen.show', $asesmen)->with('status', 'Nilai dan catatan asesmen berhasil disimpan.');
    }
```

- [ ] **Step 4: Update `create.blade.php`**

In `resources/views/guru/asesmen/create.blade.php`, make komponen selection mandatory. Change the validation-adjacent copy at line 130 from:

```html
                        <p class="text-xs text-gray-500">Pilih indikator TP dari Kurikulum Merdeka yang diasesmen pada kegiatan ini (opsional).</p>
```

to:

```html
                        <p class="text-xs text-gray-500">Pilih minimal satu indikator TP dari Kurikulum Merdeka yang diasesmen pada kegiatan ini. Nilai siswa dicatat per-TP.</p>
```

And add a server-side-driven error slot right after that `</div>` (after line 132, before the checkbox list `<div class="space-y-2 ...">`):

```html
                        <x-input-error :messages="$errors->get('komponen_id')" class="mt-1" />
```

- [ ] **Step 5: Rewrite `show.blade.php`'s grading table**

In `resources/views/guru/asesmen/show.blade.php`, replace the `@php` block at the top (lines 2-6):

```php
    @php
        $totalCells = $siswaList->count() * max($komponenList->count(), 1);
        $filledCount = $nilaiMatrix->filter(fn ($n) => $n->nilai_angka !== null)->count();
        $progressPct = $totalCells > 0 ? round(($filledCount / $totalCells) * 100) : 0;
    @endphp
```

Replace the entire `<!-- Grading Table Shell -->` block (from `<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">` through its closing `</div>`, i.e. lines 87-157) with:

```html
        <!-- Grading Table Shell -->
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
            <form method="POST" action="{{ route('guru.asesmen.update-nilai', $asesmen) }}">
                @csrf
                @method('PUT')

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-gray-100 bg-white px-6 py-4">
                    <div>
                        <p class="font-display text-sm font-bold text-gray-900">Lembar Input Nilai per Tujuan Pembelajaran</p>
                        <p class="text-xs text-gray-500">Masukkan skor angka (0 - 100) dan catatan deskriptif untuk setiap TP.</p>
                    </div>
                    <x-primary-button>
                        <x-icon name="check_circle" class="h-4 w-4 mr-1.5" />
                        Simpan Perubahan Nilai
                    </x-primary-button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[700px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-100 text-xs uppercase font-bold tracking-wider text-gray-600">
                                <th class="py-3.5 pl-6 pr-3 w-12 text-center">No</th>
                                <th class="px-4 py-3.5 w-56">Nama Peserta Didik</th>
                                @foreach ($komponenList as $komponen)
                                    <th class="px-4 py-3.5 min-w-[220px]">{{ $komponen->kode ?: $komponen->deskripsi }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($siswaList as $index => $siswa)
                                <tr class="transition duration-150 hover:bg-brand-50/20">
                                    <td class="py-4 pl-6 pr-3 text-center font-semibold text-gray-500">
                                        {{ $index + 1 }}
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="font-bold text-gray-900 text-base">{{ $siswa->nama_lengkap }}</div>
                                        <div class="text-xs font-medium text-gray-400">{{ $siswa->nis ?: ($siswa->nisn ?: 'Tanpa NIS') }}</div>
                                    </td>
                                    @foreach ($komponenList as $komponen)
                                        @php $nilai = $nilaiMatrix->get($siswa->id.'-'.$komponen->id); @endphp
                                        <td class="px-4 py-4 space-y-1.5">
                                            <input
                                                type="number"
                                                step="1"
                                                min="0"
                                                max="100"
                                                name="nilai[{{ $siswa->id }}][{{ $komponen->id }}][nilai_angka]"
                                                value="{{ old('nilai.'.$siswa->id.'.'.$komponen->id.'.nilai_angka', $nilai?->nilai_angka) }}"
                                                placeholder="0 - 100"
                                                class="w-24 text-center font-extrabold text-base rounded-lg border-gray-300 py-1.5 shadow-sm focus:border-brand-500 focus:ring-brand-500 placeholder:text-gray-300 placeholder:font-normal {{ $nilai?->nilai_angka !== null ? 'bg-emerald-50/50 text-emerald-800 border-emerald-300' : 'text-gray-900' }}"
                                            >
                                            <input
                                                type="text"
                                                name="nilai[{{ $siswa->id }}][{{ $komponen->id }}][catatan]"
                                                value="{{ old('nilai.'.$siswa->id.'.'.$komponen->id.'.catatan', $nilai?->catatan) }}"
                                                placeholder="Catatan..."
                                                class="w-full rounded-lg border-gray-200 text-xs text-gray-900 shadow-sm py-1.5 px-2.5 focus:border-brand-500 focus:ring-brand-500 placeholder:text-gray-400"
                                            >
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-end border-t border-gray-100 bg-gray-50/50 px-6 py-4">
                    <x-primary-button>
                        <x-icon name="check_circle" class="h-5 w-5 mr-1.5" />
                        Simpan Seluruh Nilai &amp; Deskripsi
                    </x-primary-button>
                </div>
            </form>
        </div>
```

(The "Tujuan Pembelajaran / Indikator Terkait" summary block further up in the same file, lines 61-84, is unaffected — it already iterates `$asesmen->komponenPenilaian`, which is still valid.)

- [ ] **Step 6: Update `RaporController`**

In `app/Http/Controllers/Admin/RaporController.php`, replace lines 50-53:

```php
                    $scores = $allNilai->whereIn('asesmen_id', $mapelAsesmenIds)
                        ->where('siswa_id', $siswa->id)
                        ->whereNotNull('skor')
                        ->pluck('skor');
```

with:

```php
                    $scores = $allNilai->whereIn('asesmen_id', $mapelAsesmenIds)
                        ->where('siswa_id', $siswa->id)
                        ->whereNotNull('nilai_angka')
                        ->pluck('nilai_angka');
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Guru/AsesmenControllerTest.php`
Expected: PASS (all tests)

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Guru/AsesmenController.php app/Http/Controllers/Admin/RaporController.php resources/views/guru/asesmen/create.blade.php resources/views/guru/asesmen/show.blade.php tests/Feature/Guru/AsesmenControllerTest.php
git commit -m "feat: rework guru grading UI and rapor recap for per-komponen nilai_angka"
```

---

### Task 7: Update `AcademicDummySeeder` and `RaporControllerTest` for the new schema

**Files:**
- Modify: `database/seeders/AcademicDummySeeder.php`
- Modify: `tests/Feature/Admin/RaporControllerTest.php`

**Interfaces:** Consumes the Task 5 `NilaiSiswa` shape.

**Context:** `AcademicDummySeeder` still calls `NilaiSiswa::updateOrCreate(['asesmen_id' => ..., 'siswa_id' => ...], ['skor' => ..., 'catatan' => ...])` (one row per siswa) — this will now fail (`skor` column no longer exists, `komponen_penilaian_id` is required). `RaporControllerTest` similarly creates a `NilaiSiswa` row with `'skor' => 88.0` and no `komponen_penilaian_id`.

- [ ] **Step 1: Write the failing test**

Update `tests/Feature/Admin/RaporControllerTest.php`'s `it('displays the rapor recap page ...')` test — replace the `NilaiSiswa::create([...])` block:

```php
    NilaiSiswa::create([
        'asesmen_id' => $asesmen->id,
        'siswa_id' => $siswa->id,
        'skor' => 88.0,
    ]);
```

with:

```php
    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen->komponenPenilaian()->attach($komponen->id);

    NilaiSiswa::create([
        'asesmen_id' => $asesmen->id,
        'siswa_id' => $siswa->id,
        'komponen_penilaian_id' => $komponen->id,
        'nilai_angka' => 88,
    ]);
```

Add `use App\Models\KomponenPenilaian;` to the file's `use` block.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/RaporControllerTest.php`
Expected: FAIL — `nilai_siswa.skor` doesn't exist / `komponen_penilaian_id` NOT NULL violation (whichever the DB rejects first).

- [ ] **Step 3: Fix the seeder**

In `database/seeders/AcademicDummySeeder.php`, replace the two `foreach ($siswaA as ...)` blocks (around lines 392-397 and 405-411) that call `NilaiSiswa::updateOrCreate` keyed on `['asesmen_id', 'siswa_id']` with `['skor', 'catatan']`:

```php
        foreach ($siswaA as $i => $siswa) {
            NilaiSiswa::updateOrCreate(
                ['asesmen_id' => $asesmenMtk->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $tpMtk1->id],
                ['nilai_angka' => (int) round($skorMtk[$i] ?? 80), 'catatan' => $catatanMtk[$i] ?? 'Baik']
            );
        }
```

and:

```php
        foreach ($siswaA as $i => $siswa) {
            NilaiSiswa::updateOrCreate(
                ['asesmen_id' => $asesmenIpa->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $tpIpa1->id],
                ['nilai_angka' => (int) round($skorIpa[$i] ?? 85), 'catatan' => 'Mampu menggunakan jangka sorong dan mikrometer sekrup dengan ketelitian baik.']
            );
        }
```

(Using `$tpMtk1`/`$tpIpa1` — the first komponen already attached to each asesmen a few lines above — as the specific komponen each dummy score is against.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/RaporControllerTest.php`
Expected: PASS

Then re-seed the real dev DB (per this project's standing post-migration step):
```bash
php artisan db:seed --class=AcademicDummySeeder
```

- [ ] **Step 5: Commit**

```bash
git add database/seeders/AcademicDummySeeder.php tests/Feature/Admin/RaporControllerTest.php
git commit -m "fix: update AcademicDummySeeder and RaporControllerTest for per-komponen nilai_siswa"
```

---

### Task 8: Full-suite regression check

**Files:** None (verification-only task).

- [ ] **Step 1: Run the entire suite**

Run: `php artisan test`
Expected: All tests pass, including every file touched in Tasks 1-7 and every pre-existing test elsewhere in the app (confirms none of the schema/controller changes broke an unrelated caller — e.g. any other place that might reference `NilaiSiswa::skor` or `KomponenPenilaianController`'s old query shape).

- [ ] **Step 2: Grep for any remaining reference to the old `skor` column**

Run: `grep -rn "nilai_siswa.*skor\|->skor\b" app/ resources/ database/ tests/ --include=*.php --include=*.blade.php`
Expected: no matches outside of comments/history (if any appear, fix them before proceeding — this catches any caller Tasks 1-7 missed).

- [ ] **Step 3: Report final status**

No commit for this task — it's a checkpoint before final review/merge, per this project's standard subagent-driven-development flow (task-level review already happened per-task above; this is the equivalent of the "final whole-branch review" gate this project's post-mortems keep stressing every prior tahap needed and Tahap 7 skipped entirely).
