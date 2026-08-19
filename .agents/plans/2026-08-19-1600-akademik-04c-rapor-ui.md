# Sub-Task 04c: Adaptive E-Rapor Engine — UI 4 Role Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the web UI (controllers, routes, views) for the Adaptive E-Rapor Engine's approval workflow — wali kelas fills per-student report-card notes and submits a class's rapor for review; Waka Kurikulum verifies; Kepala Sekolah gives final approval — on top of the fully-built, fully-tested headless backend from Sub-Task 04a/04b.

**Architecture:** Two new thin controllers (`Guru\RaporController` for the wali-kelas side, `Lembaga\Rapor\PersetujuanController` for the Waka+Kepsek approval inbox) that call existing Actions/Services from `app/Domains/Akademik/`. One new backend Action (`GenerateNarasiPerkembanganAction`) that composes an existing per-mapel narrative generator into a per-student draft. Views follow this codebase's two established patterns: plain GET-reload filter forms (wali kelas side, matching `Guru\Akademik\RekapKehadiranController`) and AJAX-partial list + dedicated decision page (Waka/Kepsek side, matching `Yayasan\Pengadaan\ApprovalPengadaanController`).

**Tech Stack:** Laravel 11, Blade + Alpine.js (no Livewire/Vue in this codebase), Pest tests, Spatie `laravel-permission`.

## Global Constraints

- **No new permissions needed.** `rapor.input-wali`, `rapor.ajukan`, `rapor.verify`, `rapor.approve` already exist (seeded in Sub-Task 04b, in `database/seeders/PermissionSeeder.php` and assigned to roles `guru`/`admin_akademik`/`kepala_sekolah` in `database/seeders/RoleSeeder.php`). Do NOT add new permissions or touch these two seeder files.
- **The approval decision form offers ONLY 2 choices: Approve / Reject.** NEVER add a third "Minta Revisi" (`RequestRevision`) option, even though the generic `ApprovalAction` enum has that case and Pengadaan's own decision form offers it. Reason (verified against actual code): `VerifyPengajuanRaporAction`/`ApprovePengajuanRaporAction` only have `if`/`elseif` branches for `ApprovalStatus::Rejected` and `ApprovalStatus::InReview`/`Approved` — there is no branch for `RevisionRequired`. If `RequestRevision` were submitted, the generic engine's `ApprovalRequest.status` would become `RevisionRequired` but `PengajuanRapor.status` (the domain's own synced column) would NOT be updated, causing a desync bug. The `ProcessRaporApprovalRequest` FormRequest in this plan enforces this at the validation layer (`Rule::in(['APPROVE', 'REJECT'])`) — do not weaken that rule.
- **Namespace convention**: wali-kelas controller is `App\Http\Controllers\Guru\RaporController` (flat, NOT nested under `Guru\Akademik\`). Waka/Kepsek controller is `App\Http\Controllers\Lembaga\Rapor\PersetujuanController` (NOT `Admin\...` — this project's newest convention puts lembaga-scoped domain controllers under the top-level `Lembaga\` namespace regardless of which specific role acts, reserving `Yayasan\` only for yayasan-scoped steps, which Rapor has none of).
- **`CatatanWaliKelas` is never locked.** Only `NilaiSiswa` (grades) get locked after `Disetujui`, enforced already by 04b's `SimpanNilaiSiswaAction`. Do not add any lock check to `SimpanCatatanWaliKelasAction` or to this plan's new controller — wali kelas can always edit notes, even after approval. This is an intentional, already-approved design decision, not a gap to fix.
- **`catatan_revisi` is not auto-cleared on resubmit.** Any view that displays `$pengajuanRapor->catatan_revisi` MUST guard it behind `$pengajuanRapor->status === StatusPengajuanRapor::Ditolak` — a non-null `catatan_revisi` does not by itself mean "this is the current rejection reason," it could be stale from an earlier cycle.
- **"Siswa lengkap" badge definition must match `SubmitPengajuanRaporAction` exactly**: existence of a `CatatanWaliKelas` row for `[siswa_id, semester_id]` — NOT per-field validation. Do not invent a stricter definition for the UI badge.
- Every new PHP file starts with `declare(strict_types=1);` (matches `App\Domains\Akademik\*` and `App\Http\Requests\Akademik\*` convention already used in 04a/04b files).
- Every FormRequest's `authorize()` checks a permission string directly via `$this->user()->can(...)` (or `canAny([...])` for the shared decision endpoint) — do not rely on `return true` + controller-only authorization; that is NOT this codebase's pattern (see `StoreKomponenPenilaianRequest`/`StoreAsesmenRequest`).
- Tests: run only the scoped test file(s) for each task, in the shell, synchronously — do NOT background a `php artisan test` run and wait for a notification, it will stall the task. The full suite runs exactly once, at the final task, and only after asking the user for explicit permission first.

---

## File Map

| File | Task | Purpose |
|---|---|---|
| `app/Domains/Akademik/Actions/Rapor/GenerateNarasiPerkembanganAction.php` | 1 | Loop `CapaianKompetensiGenerator` across all mapel a student takes, concatenate into one draft paragraph |
| `tests/Feature/Akademik/GenerateNarasiPerkembanganActionTest.php` | 1 | TDD test for the above |
| `app/Http/Controllers/Guru/RaporController.php` | 2, 3, 4 | Wali kelas: list siswa, per-siswa form, save, generate-narasi, submit |
| `app/Http/Requests/Akademik/StoreCatatanWaliKelasRequest.php` | 3 | Validates the per-siswa catatan form |
| `app/Http/Requests/Akademik/SubmitPengajuanRaporRequest.php` | 4 | Validates the "Ajukan Rapor" submit action |
| `resources/views/portals/guru/rapor/catatan/index.blade.php` | 2 | List of siswa in the guru's kelas + completeness badges + submit button |
| `resources/views/portals/guru/rapor/catatan/edit.blade.php` | 2, 3 | Per-siswa form with jenjang-conditional fields, repeatable arrays, Generate Otomatis |
| `tests/Feature/Guru/RaporControllerTest.php` | 2, 3, 4 | Feature tests for all of the above |
| `app/Http/Controllers/Lembaga/Rapor/PersetujuanController.php` | 5, 6 | Waka/Kepsek: inbox list, review detail, decision |
| `app/Http/Requests/Akademik/ProcessRaporApprovalRequest.php` | 6 | Validates the decision form (Approve/Reject only) |
| `resources/views/portals/lembaga/rapor/persetujuan/index.blade.php` | 5 | Inbox list page (AJAX filter wrapper) |
| `resources/views/portals/lembaga/rapor/persetujuan/_daftar.blade.php` | 5 | AJAX-swapped table partial |
| `resources/views/portals/lembaga/rapor/persetujuan/show.blade.php` | 5, 6 | Review page: rekap nilai + catatan wali kelas + decision form |
| `tests/Feature/Rapor/RaporPersetujuanControllerTest.php` | 5, 6 | Feature tests for all of the above |
| `routes/admin.php` | 4, 6 | Register `guru.rapor.*` and `admin.rapor.persetujuan.*` routes |
| `resources/views/layouts/sidebar.blade.php` | 7 | Add 2 nav entries |
| `.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md`, `.agents/logs/2026-08-19-1600-akademik-04c-rapor-ui.md` | 7 | Master plan update + handoff log |

---

### Task 1: `GenerateNarasiPerkembanganAction`

**Files:**
- Create: `app/Domains/Akademik/Actions/Rapor/GenerateNarasiPerkembanganAction.php`
- Test: `tests/Feature/Akademik/GenerateNarasiPerkembanganActionTest.php`

**Interfaces:**
- Consumes: `App\Domains\Akademik\Services\RaporCalculationService::hitungRekapKelas(Kelas $kelas, Semester $semester): array{siswaList: Collection, mapelList: Collection, rekapNilai: array, classAvg: ?float, highestScore: ?float}` (already exists, from Sub-Task 04a). `App\Domains\Akademik\Services\CapaianKompetensiGenerator::generateNarasi(Siswa $siswa, MataPelajaran $mapel, Semester $semester): array{tertinggi: ?string, terendah: ?string}` (already exists, from Sub-Task 04b).
- Produces: `GenerateNarasiPerkembanganAction::execute(Siswa $siswa, Kelas $kelas, Semester $semester): string` — used by Task 3's `Guru\RaporController::generateNarasi()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Akademik\Actions\Rapor\GenerateNarasiPerkembanganAction;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

function siapkanKelasSemesterUntukNarasi(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    return compact('kelas', 'semester', 'siswa');
}

it('concatenates narasi across every mapel the kelas has an asesmen for', function () {
    ['kelas' => $kelas, 'semester' => $semester, 'siswa' => $siswa] = siapkanKelasSemesterUntukNarasi();

    $matematika = MataPelajaran::factory()->create(['lembaga_id' => $kelas->lembaga_id, 'nama' => 'Matematika']);
    $asesmenMtk = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'mata_pelajaran_id' => $matematika->id, 'semester_id' => $semester->id]);
    $komponenMtk = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $matematika->id, 'semester_id' => $semester->id, 'deskripsi' => 'operasi bilangan bulat', 'kktp_minimal' => 75]);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmenMtk->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenMtk->id, 'nilai_angka' => 90]);

    $ipa = MataPelajaran::factory()->create(['lembaga_id' => $kelas->lembaga_id, 'nama' => 'IPA']);
    $asesmenIpa = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'mata_pelajaran_id' => $ipa->id, 'semester_id' => $semester->id]);
    $komponenIpa = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $ipa->id, 'semester_id' => $semester->id, 'deskripsi' => 'siklus air', 'kktp_minimal' => 75]);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmenIpa->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenIpa->id, 'nilai_angka' => 50]);

    $narasi = app(GenerateNarasiPerkembanganAction::class)->execute($siswa, $kelas, $semester);

    expect($narasi)->toContain('Menunjukkan penguasaan sangat baik dalam operasi bilangan bulat.');
    expect($narasi)->toContain('Perlu bimbingan dan pendampingan dalam siklus air.');
});

it('returns an empty string when the kelas has no asesmen at all in the semester', function () {
    ['kelas' => $kelas, 'semester' => $semester, 'siswa' => $siswa] = siapkanKelasSemesterUntukNarasi();

    $narasi = app(GenerateNarasiPerkembanganAction::class)->execute($siswa, $kelas, $semester);

    expect($narasi)->toBe('');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Akademik/GenerateNarasiPerkembanganActionTest.php`
Expected: FAIL — `Class "App\Domains\Akademik\Actions\Rapor\GenerateNarasiPerkembanganAction" not found`

- [ ] **Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Rapor;

use App\Domains\Akademik\Services\CapaianKompetensiGenerator;
use App\Domains\Akademik\Services\RaporCalculationService;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;

final class GenerateNarasiPerkembanganAction
{
    public function __construct(
        private readonly RaporCalculationService $raporCalculationService,
        private readonly CapaianKompetensiGenerator $capaianKompetensiGenerator,
    ) {
    }

    /**
     * Gabungkan narasi capaian tertinggi/terendah lintas semua mapel yang diikuti siswa
     * di kelas+semester tsb jadi satu draft paragraf untuk field catatan_perkembangan.
     * String kosong jika kelas tidak punya asesmen sama sekali di semester itu.
     */
    public function execute(Siswa $siswa, Kelas $kelas, Semester $semester): string
    {
        $mapelList = $this->raporCalculationService->hitungRekapKelas($kelas, $semester)['mapelList'];

        $kalimat = [];
        foreach ($mapelList as $mapel) {
            $narasi = $this->capaianKompetensiGenerator->generateNarasi($siswa, $mapel, $semester);
            if ($narasi['tertinggi'] !== null) {
                $kalimat[] = $narasi['tertinggi'];
            }
            if ($narasi['terendah'] !== null) {
                $kalimat[] = $narasi['terendah'];
            }
        }

        return implode(' ', $kalimat);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Akademik/GenerateNarasiPerkembanganActionTest.php`
Expected: PASS — 2 passed

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Akademik/Actions/Rapor/GenerateNarasiPerkembanganAction.php tests/Feature/Akademik/GenerateNarasiPerkembanganActionTest.php
git commit -m "feat(akademik): tambah GenerateNarasiPerkembanganAction untuk draft catatan_perkembangan"
```

---

### Task 2: `Guru\RaporController` — `index()` + `edit()` (read-only pages)

**Files:**
- Create: `app/Http/Controllers/Guru/RaporController.php`
- Create: `resources/views/portals/guru/rapor/catatan/index.blade.php`
- Create: `resources/views/portals/guru/rapor/catatan/edit.blade.php`
- Test: `tests/Feature/Guru/RaporControllerTest.php`

**Interfaces:**
- Consumes: `App\Domains\Akademik\Models\CatatanWaliKelas` (fillable: `siswa_id`, `semester_id`, `catatan_sikap`, `catatan_perkembangan`, `tinggi_badan_cm`, `berat_badan_kg`, `lingkar_kepala_cm`, `ekstrakurikuler` (array), `prestasi` (array), `pkl_info` (array), `keterangan_kenaikan`). `App\Domains\Akademik\Models\PengajuanRapor` (`status` cast to `App\Domains\Akademik\Enums\StatusPengajuanRapor`, `catatan_revisi`). `App\Models\Kelas` (`wali_kelas_guru_id`, `lembaga` relation → `bentuk_pendidikan`). `App\Models\Siswa` (`nama_lengkap`, `kelas_id`, `kelas` relation).
- Produces: `Guru\RaporController::index()`/`edit()` — GET-only in this task; `update()`/`generateNarasi()`/`ajukan()` are added in Tasks 3–4 in the SAME file (do not create a second controller).

**Design decisions locked in for this task** (do not deviate — an implementer re-deriving these from scratch would likely choose differently and break the spec):
1. Jenjang-conditional fields use this exact whitelist (matches `ModePembelajaran::fromBentukPendidikan()` from Sub-Task 03b, but is NOT that enum — just the same literal value list, duplicated here per YAGNI, since this is a one-off display concern):
   - `bentuk_pendidikan` IN `['KB', 'TPA', 'SPS', 'TK']` → show antropometri fields (`tinggi_badan_cm`, `berat_badan_kg`, `lingkar_kepala_cm`).
   - `bentuk_pendidikan` === `'SMK'` → show `pkl_info` repeatable rows.
   - All other/unknown values → neither.
   - "Field umum" (`catatan_sikap`, `catatan_perkembangan`, `ekstrakurikuler`, `prestasi`, `keterangan_kenaikan`) shown for every jenjang, no exceptions.
2. Repeatable-array row shapes (freeform JSON, no DB-level schema — pick these exact keys, they must match across the create form JS, the FormRequest validation in Task 3, and any future 04d PDF consumer):
   - `ekstrakurikuler`: `[{nama: string, peran: string}]`
   - `prestasi`: `[{nama: string, tingkat: string, tahun: string}]`
   - `pkl_info`: `[{perusahaan: string, posisi: string, durasi: string}]`
3. List pattern for `index()`: plain GET-reload with `onchange="this.form.submit()"` selects — NOT the AJAX `dataTableFilter` pattern. This deliberately follows `Guru\Akademik\RekapKehadiranController::index()` (the closer, same-role precedent) rather than the spec's earlier generic suggestion of the AJAX pattern; per-kelas student counts here are always small (a single classroom), so there is no pagination need that would justify AJAX.

**`app/Http/Controllers/Guru/RaporController.php`** (only `index()` and `edit()` in this task — `__construct()` already declares ALL dependencies needed by later tasks so you don't have to re-touch the signature):

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guru;

use App\Domains\Akademik\Actions\Rapor\GenerateNarasiPerkembanganAction;
use App\Domains\Akademik\Actions\Rapor\SimpanCatatanWaliKelasAction;
use App\Domains\Akademik\Actions\Rapor\SubmitPengajuanRaporAction;
use App\Domains\Akademik\DataTransferObjects\CatatanWaliKelasData;
use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\CatatanWaliKelas;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Http\Requests\Akademik\StoreCatatanWaliKelasRequest;
use App\Http\Requests\Akademik\SubmitPengajuanRaporRequest;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class RaporController extends BaseController
{
    use AuthorizesRequests;

    private const JENJANG_ANTROPOMETRI = ['KB', 'TPA', 'SPS', 'TK'];

    public function __construct(
        private readonly SimpanCatatanWaliKelasAction $simpanCatatanWaliKelasAction,
        private readonly SubmitPengajuanRaporAction $submitPengajuanRaporAction,
        private readonly GenerateNarasiPerkembanganAction $generateNarasiPerkembanganAction,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('rapor.input-wali');

        $guru = $request->user()->guru;
        abort_if($guru === null, 403);

        $lembagaId = $request->user()->lembaga_id;

        $tahunAjaranQuery = TahunAjaran::query();
        if ($lembagaId) {
            $tahunAjaranQuery->where('lembaga_id', $lembagaId);
        }
        $tahunAjaranList = $tahunAjaranQuery->orderByDesc('tanggal_mulai')->orderByDesc('id')->get();
        $tahunAjaranAktif = $tahunAjaranList->firstWhere('status_aktif', true);

        $tahunAjaranId = $request->has('tahun_ajaran_id')
            ? ($request->query('tahun_ajaran_id') !== '' ? (int) $request->query('tahun_ajaran_id') : null)
            : ($tahunAjaranAktif?->id ?? $tahunAjaranList->first()?->id);

        $semesterQuery = Semester::query();
        if ($lembagaId) {
            $semesterQuery->where('lembaga_id', $lembagaId);
        }
        if ($tahunAjaranId) {
            $semesterQuery->where('tahun_ajaran_id', $tahunAjaranId);
        }
        $semesterList = $semesterQuery->orderBy('urutan')->orderBy('nama')->get();
        $semesterAktif = $semesterList->firstWhere('status_aktif', true);

        $semesterId = $request->has('semester_id')
            ? ($request->query('semester_id') !== '' ? (int) $request->query('semester_id') : null)
            : ($semesterAktif?->id ?? null);

        $kelasQuery = Kelas::where('wali_kelas_guru_id', $guru->id);
        if ($tahunAjaranId) {
            $kelasQuery->where('tahun_ajaran_id', $tahunAjaranId);
        }
        $kelasList = $kelasQuery->orderBy('nama')->get();

        $kelasId = $request->has('kelas_id') && $request->query('kelas_id') !== ''
            ? (int) $request->query('kelas_id')
            : optional($kelasList->first())->id;

        $kelas = $kelasList->firstWhere('id', $kelasId);
        $semester = $semesterId ? $semesterList->firstWhere('id', $semesterId) : null;

        $siswaList = collect();
        $pengajuanRapor = null;
        if ($kelas && $semester) {
            $siswaList = Siswa::where('kelas_id', $kelas->id)->orderBy('nama_lengkap')->get();
            $siswaIdsWithCatatan = CatatanWaliKelas::where('semester_id', $semester->id)
                ->whereIn('siswa_id', $siswaList->pluck('id'))
                ->pluck('siswa_id');
            $siswaList = $siswaList->map(function (Siswa $siswa) use ($siswaIdsWithCatatan) {
                $siswa->catatan_lengkap = $siswaIdsWithCatatan->contains($siswa->id);

                return $siswa;
            });

            $pengajuanRapor = PengajuanRapor::where('kelas_id', $kelas->id)->where('semester_id', $semester->id)->first();
        }

        return view('portals.guru.rapor.catatan.index', [
            'tahunAjaranList' => $tahunAjaranList,
            'tahunAjaranId' => $tahunAjaranId,
            'semesterList' => $semesterList,
            'semesterId' => $semesterId,
            'kelasList' => $kelasList,
            'kelasId' => $kelasId,
            'kelas' => $kelas,
            'semester' => $semester,
            'siswaList' => $siswaList,
            'pengajuanRapor' => $pengajuanRapor,
        ]);
    }

    public function edit(Siswa $siswa, Request $request): View
    {
        $this->authorize('rapor.input-wali');

        $guru = $request->user()->guru;
        abort_if($guru === null, 403);
        abort_unless($siswa->kelas && $siswa->kelas->wali_kelas_guru_id === $guru->id, 403);

        $semesterId = (int) $request->query('semester_id');
        abort_if($semesterId === 0, 404, 'Konteks semester wajib disertakan untuk membuka form catatan wali kelas.');
        $semester = Semester::find($semesterId);
        abort_if($semester === null, 404);

        $catatan = CatatanWaliKelas::where('siswa_id', $siswa->id)->where('semester_id', $semester->id)->first()
            ?? new CatatanWaliKelas(['siswa_id' => $siswa->id, 'semester_id' => $semester->id]);

        $siswaListKelas = Siswa::where('kelas_id', $siswa->kelas_id)->orderBy('nama_lengkap')->get();
        $posisiSaatIni = $siswaListKelas->search(fn (Siswa $s) => $s->id === $siswa->id);
        $siswaSebelumnya = $posisiSaatIni > 0 ? $siswaListKelas->get($posisiSaatIni - 1) : null;
        $siswaBerikutnya = $posisiSaatIni !== false && $posisiSaatIni < $siswaListKelas->count() - 1 ? $siswaListKelas->get($posisiSaatIni + 1) : null;

        $bentukPendidikan = $siswa->kelas->lembaga->bentuk_pendidikan ?? null;

        return view('portals.guru.rapor.catatan.edit', [
            'siswa' => $siswa,
            'semester' => $semester,
            'catatan' => $catatan,
            'siswaSebelumnya' => $siswaSebelumnya,
            'siswaBerikutnya' => $siswaBerikutnya,
            'tampilkanAntropometri' => in_array($bentukPendidikan, self::JENJANG_ANTROPOMETRI, true),
            'tampilkanPklInfo' => $bentukPendidikan === 'SMK',
        ]);
    }

    public function update(Siswa $siswa, StoreCatatanWaliKelasRequest $request): RedirectResponse
    {
        $guru = $request->user()->guru;
        abort_if($guru === null, 403);
        abort_unless($siswa->kelas && $siswa->kelas->wali_kelas_guru_id === $guru->id, 403);

        $this->simpanCatatanWaliKelasAction->execute(
            CatatanWaliKelasData::fromArray([...$request->validated(), 'siswa_id' => $siswa->id])
        );

        $nextSiswaId = $request->input('next_siswa_id');
        if ($nextSiswaId) {
            return redirect()
                ->route('guru.rapor.catatan.edit', ['siswa' => $nextSiswaId, 'semester_id' => $request->input('semester_id')])
                ->with('success', 'Catatan wali kelas berhasil disimpan.');
        }

        return redirect()
            ->route('guru.rapor.catatan.index', ['kelas_id' => $siswa->kelas_id, 'semester_id' => $request->input('semester_id')])
            ->with('success', 'Catatan wali kelas berhasil disimpan.');
    }

    public function generateNarasi(Siswa $siswa, Request $request): JsonResponse
    {
        $this->authorize('rapor.input-wali');

        $guru = $request->user()->guru;
        abort_if($guru === null, 403);
        abort_unless($siswa->kelas && $siswa->kelas->wali_kelas_guru_id === $guru->id, 403);

        $semester = Semester::find((int) $request->query('semester_id'));
        abort_if($semester === null, 404);

        $narasi = $this->generateNarasiPerkembanganAction->execute($siswa, $siswa->kelas, $semester);

        return response()->json(['narasi' => $narasi]);
    }

    public function ajukan(SubmitPengajuanRaporRequest $request): RedirectResponse
    {
        $guru = $request->user()->guru;
        abort_if($guru === null, 403);

        $kelas = Kelas::find($request->validated('kelas_id'));
        abort_if($kelas === null, 404);
        abort_unless($kelas->wali_kelas_guru_id === $guru->id, 403);

        $semester = Semester::find($request->validated('semester_id'));
        abort_if($semester === null, 404);

        $this->submitPengajuanRaporAction->execute($kelas, $semester, $request->user());

        return redirect()
            ->route('guru.rapor.catatan.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id])
            ->with('success', 'Rapor kelas berhasil diajukan untuk verifikasi Waka Kurikulum.');
    }
}
```

**Note for this task**: `update()`, `generateNarasi()`, `ajukan()` are written now (the file is one cohesive class) but their routes are NOT registered until Tasks 3–4, and `StoreCatatanWaliKelasRequest`/`SubmitPengajuanRaporRequest` don't exist yet until Task 3. **This task only wires up and tests `index()` and `edit()`** — leave the other 3 methods present in the file (they won't be reachable without routes, and PHP won't error on an unused-but-valid class) but do not write their tests or routes yet. This ordering avoids a broken intermediate state where `use App\Http\Requests\Akademik\StoreCatatanWaliKelasRequest;` points at a nonexistent class — **so as an exception to normal step ordering, write the two FormRequest files (empty rules for now is NOT allowed — write their FULL final content, it's simple enough to do in one pass) as part of THIS task's Step 1, before the controller**, using the content shown in Task 3 and Task 4 verbatim (copy them now, do not wait). This keeps every commit in this plan green and buildable.

- [ ] **Step 1: Create the two FormRequest files needed for the controller to compile** (full content — copied verbatim from Tasks 3 and 4 below, created now to avoid a broken intermediate state)

Create `app/Http/Requests/Akademik/StoreCatatanWaliKelasRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\DataTransferObjects\CatatanWaliKelasData;
use Illuminate\Foundation\Http\FormRequest;

final class StoreCatatanWaliKelasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rapor.input-wali');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'semester_id' => ['required', 'integer', 'exists:semester,id'],
            'catatan_sikap' => ['nullable', 'string', 'max:2000'],
            'catatan_perkembangan' => ['nullable', 'string', 'max:2000'],
            'tinggi_badan_cm' => ['nullable', 'numeric', 'min:0'],
            'berat_badan_kg' => ['nullable', 'numeric', 'min:0'],
            'lingkar_kepala_cm' => ['nullable', 'numeric', 'min:0'],
            'ekstrakurikuler' => ['nullable', 'array'],
            'ekstrakurikuler.*.nama' => ['required_with:ekstrakurikuler', 'string', 'max:255'],
            'ekstrakurikuler.*.peran' => ['nullable', 'string', 'max:255'],
            'prestasi' => ['nullable', 'array'],
            'prestasi.*.nama' => ['required_with:prestasi', 'string', 'max:255'],
            'prestasi.*.tingkat' => ['nullable', 'string', 'max:255'],
            'prestasi.*.tahun' => ['nullable', 'string', 'max:4'],
            'pkl_info' => ['nullable', 'array'],
            'pkl_info.*.perusahaan' => ['required_with:pkl_info', 'string', 'max:255'],
            'pkl_info.*.posisi' => ['nullable', 'string', 'max:255'],
            'pkl_info.*.durasi' => ['nullable', 'string', 'max:255'],
            'keterangan_kenaikan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function toDTO(int $siswaId): CatatanWaliKelasData
    {
        return CatatanWaliKelasData::fromArray([...$this->validated(), 'siswa_id' => $siswaId]);
    }
}
```

Create `app/Http/Requests/Akademik/SubmitPengajuanRaporRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use Illuminate\Foundation\Http\FormRequest;

final class SubmitPengajuanRaporRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rapor.ajukan');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'semester_id' => ['required', 'integer', 'exists:semester,id'],
        ];
    }
}
```

- [ ] **Step 2: Write the controller**

Create `app/Http/Controllers/Guru/RaporController.php` with the full content shown above.

- [ ] **Step 3: Write the failing tests for `index()` and `edit()` only**

Create `tests/Feature/Guru/RaporControllerTest.php`:

```php
<?php

use App\Domains\Akademik\Models\CatatanWaliKelas;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function siapkanWaliKelasUntukRapor(string $bentukPendidikan = 'SD'): array
{
    Permission::firstOrCreate(['name' => 'rapor.input-wali', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'rapor.ajukan', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru_wali_rapor', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['rapor.input-wali', 'rapor.ajukan']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => $bentukPendidikan]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'status_aktif' => true]);

    $guruUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruUser->assignRole($role);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $guruUser->id]);

    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'wali_kelas_guru_id' => $guru->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'nama_lengkap' => 'Ahmad Fauzi']);

    return compact('guruUser', 'guru', 'kelas', 'siswa', 'lembaga', 'yayasan', 'tahunAjaran', 'semester');
}

it('denies access without rapor.input-wali permission', function () {
    $this->actingAs(User::factory()->create())->get(route('guru.rapor.catatan.index'))->assertForbidden();
});

it('lists siswa in the kelas the guru is wali kelas of, with a completeness badge', function () {
    ['guruUser' => $guruUser, 'kelas' => $kelas, 'siswa' => $siswa, 'semester' => $semester, 'tahunAjaran' => $tahunAjaran] = siapkanWaliKelasUntukRapor();

    $response = $this->actingAs($guruUser)->get(route('guru.rapor.catatan.index', [
        'tahun_ajaran_id' => $tahunAjaran->id, 'semester_id' => $semester->id, 'kelas_id' => $kelas->id,
    ]));

    $response->assertOk();
    $response->assertSee('Ahmad Fauzi');
    $response->assertViewHas('siswaList', function ($list) use ($siswa) {
        return $list->firstWhere('id', $siswa->id)?->catatan_lengkap === false;
    });
});

it('marks a siswa complete once a CatatanWaliKelas row exists for that semester', function () {
    ['guruUser' => $guruUser, 'kelas' => $kelas, 'siswa' => $siswa, 'semester' => $semester] = siapkanWaliKelasUntukRapor();
    CatatanWaliKelas::factory()->create(['siswa_id' => $siswa->id, 'semester_id' => $semester->id]);

    $response = $this->actingAs($guruUser)->get(route('guru.rapor.catatan.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $response->assertViewHas('siswaList', function ($list) use ($siswa) {
        return $list->firstWhere('id', $siswa->id)?->catatan_lengkap === true;
    });
});

it('does not list a kelas the guru is not wali kelas of', function () {
    ['guruUser' => $guruUser, 'lembaga' => $lembaga, 'tahunAjaran' => $tahunAjaran] = siapkanWaliKelasUntukRapor();
    $kelasBukanWali = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    $response = $this->actingAs($guruUser)->get(route('guru.rapor.catatan.index', ['kelas_id' => $kelasBukanWali->id]));

    $response->assertViewHas('kelas', fn ($kelas) => $kelas === null);
});

it('shows antropometri fields on the edit form for a TK kelas but not for an SMP kelas', function () {
    ['guruUser' => $guruUser, 'siswa' => $siswa, 'semester' => $semester] = siapkanWaliKelasUntukRapor('TK');
    $responseTk = $this->actingAs($guruUser)->get(route('guru.rapor.catatan.edit', ['siswa' => $siswa->id, 'semester_id' => $semester->id]));
    $responseTk->assertOk();
    $responseTk->assertViewHas('tampilkanAntropometri', true);
    $responseTk->assertViewHas('tampilkanPklInfo', false);

    ['guruUser' => $guruUserSmp, 'siswa' => $siswaSmp, 'semester' => $semesterSmp] = siapkanWaliKelasUntukRapor('SMP');
    $responseSmp = $this->actingAs($guruUserSmp)->get(route('guru.rapor.catatan.edit', ['siswa' => $siswaSmp->id, 'semester_id' => $semesterSmp->id]));
    $responseSmp->assertOk();
    $responseSmp->assertViewHas('tampilkanAntropometri', false);
    $responseSmp->assertViewHas('tampilkanPklInfo', false);
});

it('shows pkl_info fields on the edit form for an SMK kelas', function () {
    ['guruUser' => $guruUser, 'siswa' => $siswa, 'semester' => $semester] = siapkanWaliKelasUntukRapor('SMK');

    $response = $this->actingAs($guruUser)->get(route('guru.rapor.catatan.edit', ['siswa' => $siswa->id, 'semester_id' => $semester->id]));

    $response->assertOk();
    $response->assertViewHas('tampilkanPklInfo', true);
    $response->assertViewHas('tampilkanAntropometri', false);
});

it('denies opening a siswa edit form the guru is not wali kelas of', function () {
    ['guruUser' => $guruUser, 'lembaga' => $lembaga, 'tahunAjaran' => $tahunAjaran, 'semester' => $semester] = siapkanWaliKelasUntukRapor();
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswaLain = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasLain->id]);

    $this->actingAs($guruUser)
        ->get(route('guru.rapor.catatan.edit', ['siswa' => $siswaLain->id, 'semester_id' => $semester->id]))
        ->assertForbidden();
});

it('requires a semester_id query param to open the edit form', function () {
    ['guruUser' => $guruUser, 'siswa' => $siswa] = siapkanWaliKelasUntukRapor();

    $this->actingAs($guruUser)
        ->get(route('guru.rapor.catatan.edit', ['siswa' => $siswa->id]))
        ->assertNotFound();
});
```

- [ ] **Step 4: Register ONLY the GET routes needed for this task**

Open `routes/admin.php`. Find the import block near the top (alphabetically sorted `use App\Http\Controllers\...` lines). There is already `use App\Http\Controllers\Admin\RaporController;` for the admin-side controller — the new one MUST be aliased to avoid a class name collision. Add this line in alphabetical position among the other `Guru\` imports (search for `use App\Http\Controllers\Guru\` to find the right spot):

```php
use App\Http\Controllers\Guru\RaporController as GuruRaporController;
```

Then find the `guru.` route group (search for `Route::prefix('guru')->name('guru.')->group`). Add these 2 lines right before the closing `});` of that group (after the existing `komponen-penilaian.destroy` line):

```php
    Route::get('rapor', [GuruRaporController::class, 'index'])->name('rapor.catatan.index');
    Route::get('rapor/siswa/{siswa}', [GuruRaporController::class, 'edit'])->name('rapor.catatan.edit');
```

Do NOT add the `PUT`/`POST` routes yet — those belong to Tasks 3–4.

- [ ] **Step 5: Write the views**

Create `resources/views/portals/guru/rapor/catatan/index.blade.php`:

```blade
<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-5">
        @if (session('success'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Rapor Wali Kelas</h1>
                <p class="text-xs text-gray-500 mt-0.5">Isi catatan rapor tiap siswa, lalu ajukan rapor kelas untuk diverifikasi Waka Kurikulum.</p>
            </div>
            <p class="text-sm text-gray-500">
                Ruang Guru <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Rapor Wali Kelas</b>
            </p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <form method="GET" action="{{ route('guru.rapor.catatan.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Tahun Ajaran</label>
                    <select name="tahun_ajaran_id" onchange="this.form.submit()" class="block w-full rounded-lg border-gray-200 text-xs text-gray-800 shadow-sm focus:border-brand-500 focus:ring-brand-500 py-2">
                        @foreach ($tahunAjaranList as $ta)
                            <option value="{{ $ta->id }}" @selected($tahunAjaranId == $ta->id)>{{ $ta->nama }} {{ $ta->status_aktif ? '(Aktif)' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Semester</label>
                    <select name="semester_id" onchange="this.form.submit()" class="block w-full rounded-lg border-gray-200 text-xs text-gray-800 shadow-sm focus:border-brand-500 focus:ring-brand-500 py-2">
                        <option value="">— Pilih Semester —</option>
                        @foreach ($semesterList as $sem)
                            <option value="{{ $sem->id }}" @selected($semesterId == $sem->id)>{{ $sem->nama }} {{ $sem->status_aktif ? '(Aktif)' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Kelas</label>
                    <select name="kelas_id" onchange="this.form.submit()" class="block w-full rounded-lg border-gray-200 text-xs text-gray-800 shadow-sm focus:border-brand-500 focus:ring-brand-500 py-2">
                        @if ($kelasList->isEmpty())
                            <option value="">— Anda Bukan Wali Kelas —</option>
                        @else
                            @foreach ($kelasList as $k)
                                <option value="{{ $k->id }}" @selected($kelas && $kelas->id == $k->id)>{{ $k->nama }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>
            </form>
        </div>

        @if (! $kelas || ! $semester)
            <div class="rounded-2xl border border-gray-200 bg-white p-8 text-center text-sm text-gray-500 shadow-card">
                <x-icon name="info" class="mx-auto h-8 w-8 text-gray-400 mb-2" />
                <p class="font-medium text-gray-700">Pilih kelas dan semester untuk melihat daftar siswa.</p>
            </div>
        @else
            @if ($pengajuanRapor && $pengajuanRapor->status === \App\Domains\Akademik\Enums\StatusPengajuanRapor::Ditolak)
                <div class="rounded-2xl border border-error-200 bg-error-50 p-5 text-sm text-error-700">
                    <p class="font-semibold">Pengajuan rapor kelas ini ditolak dan perlu direvisi.</p>
                    @if ($pengajuanRapor->catatan_revisi)
                        <p class="mt-1">Catatan: {{ $pengajuanRapor->catatan_revisi }}</p>
                    @endif
                </div>
            @endif

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-700">
                        <thead class="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-5 py-3">Nama Siswa</th>
                                <th class="px-5 py-3 text-center">Status Catatan</th>
                                <th class="px-5 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($siswaList as $siswa)
                                <tr class="hover:bg-gray-50/50">
                                    <td class="px-5 py-3.5 font-medium text-gray-900">{{ $siswa->nama_lengkap }}</td>
                                    <td class="px-5 py-3.5 text-center">
                                        <x-badge :tone="$siswa->catatan_lengkap ? 'green' : 'amber'">
                                            {{ $siswa->catatan_lengkap ? 'Lengkap' : 'Belum Lengkap' }}
                                        </x-badge>
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <a href="{{ route('guru.rapor.catatan.edit', ['siswa' => $siswa->id, 'semester_id' => $semester->id]) }}" class="font-semibold text-brand-600 hover:underline">
                                            Isi Catatan
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-5 py-10 text-center text-sm text-gray-500">Belum ada siswa terdaftar di kelas ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($siswaList->isNotEmpty())
                    <div class="flex items-center justify-end gap-3 border-t border-gray-200 px-5 py-4">
                        <form method="POST" action="{{ route('guru.rapor.pengajuan.submit') }}">
                            @csrf
                            <input type="hidden" name="kelas_id" value="{{ $kelas->id }}">
                            <input type="hidden" name="semester_id" value="{{ $semester->id }}">
                            <x-primary-button type="submit" @if(! $siswaList->every(fn($s) => $s->catatan_lengkap)) disabled @endif>
                                Ajukan Rapor untuk Verifikasi
                            </x-primary-button>
                        </form>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-app-layout>
```

Create `resources/views/portals/guru/rapor/catatan/edit.blade.php`:

```blade
<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-5" x-data="catatanWaliKelasForm({
        ekstrakurikuler: @js($catatan->ekstrakurikuler ?? []),
        prestasi: @js($catatan->prestasi ?? []),
        pklInfo: @js($catatan->pkl_info ?? []),
    })">
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Catatan Wali Kelas — {{ $siswa->nama_lengkap }}</h1>
                <p class="text-xs text-gray-500 mt-0.5">Semester: {{ $semester->nama }}</p>
            </div>
            <a href="{{ route('guru.rapor.catatan.index', ['kelas_id' => $siswa->kelas_id, 'semester_id' => $semester->id]) }}" class="text-sm font-semibold text-gray-500 hover:text-gray-700">
                &larr; Kembali ke Daftar
            </a>
        </div>

        <form method="POST" action="{{ route('guru.rapor.catatan.update', $siswa) }}" class="space-y-5">
            @csrf
            @method('PUT')
            <input type="hidden" name="semester_id" value="{{ $semester->id }}">

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Catatan Sikap</label>
                    <textarea name="catatan_sikap" rows="3" class="w-full rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('catatan_sikap', $catatan->catatan_sikap) }}</textarea>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-semibold text-gray-700">Catatan Perkembangan</label>
                        <button type="button" @click="generateNarasi()" class="text-xs font-semibold text-brand-600 hover:underline">
                            Generate Otomatis
                        </button>
                    </div>
                    <textarea name="catatan_perkembangan" x-ref="catatanPerkembangan" rows="4" class="w-full rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('catatan_perkembangan', $catatan->catatan_perkembangan) }}</textarea>
                </div>

                @if ($tampilkanAntropometri)
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Tinggi Badan (cm)</label>
                            <input type="number" step="0.1" name="tinggi_badan_cm" value="{{ old('tinggi_badan_cm', $catatan->tinggi_badan_cm) }}" class="w-full rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Berat Badan (kg)</label>
                            <input type="number" step="0.1" name="berat_badan_kg" value="{{ old('berat_badan_kg', $catatan->berat_badan_kg) }}" class="w-full rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Lingkar Kepala (cm)</label>
                            <input type="number" step="0.1" name="lingkar_kepala_cm" value="{{ old('lingkar_kepala_cm', $catatan->lingkar_kepala_cm) }}" class="w-full rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        </div>
                    </div>
                @endif
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-3">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-semibold text-gray-700">Ekstrakurikuler</label>
                    <button type="button" @click="ekstrakurikuler.push({nama: '', peran: ''})" class="text-xs font-semibold text-brand-600 hover:underline">+ Tambah Baris</button>
                </div>
                <template x-for="(row, index) in ekstrakurikuler" :key="index">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-5 items-center">
                        <input type="text" :name="`ekstrakurikuler[${index}][nama]`" x-model="row.nama" placeholder="Nama kegiatan" class="sm:col-span-2 rounded-lg border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500">
                        <input type="text" :name="`ekstrakurikuler[${index}][peran]`" x-model="row.peran" placeholder="Peran (mis. Anggota)" class="sm:col-span-2 rounded-lg border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500">
                        <button type="button" @click="ekstrakurikuler.splice(index, 1)" class="text-xs font-medium text-error-600 hover:underline">Hapus</button>
                    </div>
                </template>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-3">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-semibold text-gray-700">Prestasi</label>
                    <button type="button" @click="prestasi.push({nama: '', tingkat: '', tahun: ''})" class="text-xs font-semibold text-brand-600 hover:underline">+ Tambah Baris</button>
                </div>
                <template x-for="(row, index) in prestasi" :key="index">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-7 items-center">
                        <input type="text" :name="`prestasi[${index}][nama]`" x-model="row.nama" placeholder="Nama prestasi" class="sm:col-span-3 rounded-lg border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500">
                        <input type="text" :name="`prestasi[${index}][tingkat]`" x-model="row.tingkat" placeholder="Tingkat" class="sm:col-span-2 rounded-lg border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500">
                        <input type="text" :name="`prestasi[${index}][tahun]`" x-model="row.tahun" placeholder="Tahun" class="rounded-lg border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500">
                        <button type="button" @click="prestasi.splice(index, 1)" class="text-xs font-medium text-error-600 hover:underline">Hapus</button>
                    </div>
                </template>
            </div>

            @if ($tampilkanPklInfo)
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-semibold text-gray-700">Info PKL</label>
                        <button type="button" @click="pklInfo.push({perusahaan: '', posisi: '', durasi: ''})" class="text-xs font-semibold text-brand-600 hover:underline">+ Tambah Baris</button>
                    </div>
                    <template x-for="(row, index) in pklInfo" :key="index">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-7 items-center">
                            <input type="text" :name="`pkl_info[${index}][perusahaan]`" x-model="row.perusahaan" placeholder="Perusahaan" class="sm:col-span-3 rounded-lg border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500">
                            <input type="text" :name="`pkl_info[${index}][posisi]`" x-model="row.posisi" placeholder="Posisi" class="sm:col-span-2 rounded-lg border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500">
                            <input type="text" :name="`pkl_info[${index}][durasi]`" x-model="row.durasi" placeholder="Durasi" class="rounded-lg border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500">
                            <button type="button" @click="pklInfo.splice(index, 1)" class="text-xs font-medium text-error-600 hover:underline">Hapus</button>
                        </div>
                    </template>
                </div>
            @endif

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
                <label class="block text-xs font-semibold text-gray-700 mb-1">Keterangan Kenaikan Kelas</label>
                <textarea name="keterangan_kenaikan" rows="2" class="w-full rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('keterangan_kenaikan', $catatan->keterangan_kenaikan) }}</textarea>
            </div>

            <div class="flex items-center justify-between gap-3">
                <div>
                    @if ($siswaSebelumnya)
                        <a href="{{ route('guru.rapor.catatan.edit', ['siswa' => $siswaSebelumnya->id, 'semester_id' => $semester->id]) }}" class="text-sm font-semibold text-gray-500 hover:text-gray-700">&larr; Siswa Sebelumnya</a>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    <x-primary-button type="submit">Simpan & Kembali ke Daftar</x-primary-button>
                    @if ($siswaBerikutnya)
                        <button type="submit" name="next_siswa_id" value="{{ $siswaBerikutnya->id }}" class="inline-flex items-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
                            Simpan & Siswa Berikutnya &rarr;
                        </button>
                    @endif
                </div>
            </div>
        </form>
    </div>

    <script>
        function catatanWaliKelasForm(initial) {
            return {
                ekstrakurikuler: initial.ekstrakurikuler.length ? initial.ekstrakurikuler : [],
                prestasi: initial.prestasi.length ? initial.prestasi : [],
                pklInfo: initial.pklInfo.length ? initial.pklInfo : [],
                async generateNarasi() {
                    const existing = this.$refs.catatanPerkembangan.value.trim();
                    if (existing && !(await confirmDialog('Timpa Catatan?', 'Draft otomatis akan menimpa isi catatan perkembangan yang sudah ada. Lanjutkan?'))) {
                        return;
                    }
                    const url = @js(route('guru.rapor.catatan.generate-narasi', $siswa)) + '?semester_id=' + @js($semester->id);
                    const response = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': @js(csrf_token()), Accept: 'application/json' } });
                    const data = await response.json();
                    this.$refs.catatanPerkembangan.value = data.narasi;
                },
            };
        }
    </script>
</x-app-layout>
```

Note: verified via `grep -rln "@stack" resources/views` that this codebase does NOT use `@push`/`@stack` for scripts anywhere — so the `<script>` block above is placed directly at the bottom of the file, plain, outside any layout slot. This matches how other pages in this codebase inline their own `<script>` tags (e.g. `resources/views/portals/lembaga/pengadaan/proposal/create.blade.php`'s `proposalCreateForm()`, referenced in the spec's precedent research).

- [ ] **Step 6: Run the tests**

Run: `php artisan test tests/Feature/Guru/RaporControllerTest.php`
Expected: PASS — 7 passed (the `update`/`generateNarasi`/`ajukan` routes and their own tests come in Tasks 3–4; this task's tests only exercise `index`/`edit`)

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Guru/RaporController.php app/Http/Requests/Akademik/StoreCatatanWaliKelasRequest.php app/Http/Requests/Akademik/SubmitPengajuanRaporRequest.php resources/views/portals/guru/rapor tests/Feature/Guru/RaporControllerTest.php routes/admin.php
git commit -m "feat(akademik): Guru\\RaporController index() + edit() untuk catatan wali kelas"
```

---

### Task 3: `update()` + `generateNarasi()` — save catatan & AJAX narasi

**Files:**
- Modify: `routes/admin.php` (add PUT/POST routes)
- Test: `tests/Feature/Guru/RaporControllerTest.php` (append)

**Interfaces:**
- Consumes: `App\Http\Controllers\Guru\RaporController::update()`/`generateNarasi()` (already written in Task 2's file — this task only adds routes + tests, no new PHP class code).

- [ ] **Step 1: Add the routes**

In `routes/admin.php`, inside the same `guru.` group from Task 2, add these 2 lines right after the 2 already added:

```php
    Route::put('rapor/siswa/{siswa}', [GuruRaporController::class, 'update'])->name('rapor.catatan.update');
    Route::post('rapor/generate-narasi/{siswa}', [GuruRaporController::class, 'generateNarasi'])->name('rapor.catatan.generate-narasi');
```

- [ ] **Step 2: Write the failing tests** (append to `tests/Feature/Guru/RaporControllerTest.php`)

```php
it('saves catatan wali kelas via update and redirects back to the index', function () {
    ['guruUser' => $guruUser, 'kelas' => $kelas, 'siswa' => $siswa, 'semester' => $semester] = siapkanWaliKelasUntukRapor();

    $response = $this->actingAs($guruUser)->put(route('guru.rapor.catatan.update', $siswa), [
        'semester_id' => $semester->id,
        'catatan_sikap' => 'Sopan dan santun.',
        'ekstrakurikuler' => [['nama' => 'Pramuka', 'peran' => 'Anggota']],
    ]);

    $response->assertRedirect(route('guru.rapor.catatan.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));
    $this->assertDatabaseHas('catatan_wali_kelas', [
        'siswa_id' => $siswa->id,
        'semester_id' => $semester->id,
        'catatan_sikap' => 'Sopan dan santun.',
    ]);
});

it('redirects to the next siswa edit page when next_siswa_id is submitted', function () {
    ['guruUser' => $guruUser, 'kelas' => $kelas, 'siswa' => $siswa, 'semester' => $semester] = siapkanWaliKelasUntukRapor();
    $siswaKedua = Siswa::factory()->create(['lembaga_id' => $kelas->lembaga_id, 'kelas_id' => $kelas->id, 'nama_lengkap' => 'Budi Santoso']);

    $response = $this->actingAs($guruUser)->put(route('guru.rapor.catatan.update', $siswa), [
        'semester_id' => $semester->id,
        'next_siswa_id' => $siswaKedua->id,
    ]);

    $response->assertRedirect(route('guru.rapor.catatan.edit', ['siswa' => $siswaKedua->id, 'semester_id' => $semester->id]));
});

it('rejects updating catatan for a siswa the guru is not wali kelas of', function () {
    ['guruUser' => $guruUser, 'lembaga' => $lembaga, 'tahunAjaran' => $tahunAjaran, 'semester' => $semester] = siapkanWaliKelasUntukRapor();
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswaLain = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasLain->id]);

    $this->actingAs($guruUser)
        ->put(route('guru.rapor.catatan.update', $siswaLain), ['semester_id' => $semester->id])
        ->assertForbidden();
});

it('generates a narasi draft via AJAX for a siswa with nilai', function () {
    ['guruUser' => $guruUser, 'kelas' => $kelas, 'siswa' => $siswa, 'semester' => $semester] = siapkanWaliKelasUntukRapor();
    $mapel = \App\Models\MataPelajaran::factory()->create(['lembaga_id' => $kelas->lembaga_id]);
    $asesmen = \App\Domains\Akademik\Models\Asesmen::factory()->create(['kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);
    $komponen = \App\Domains\Akademik\Models\KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'deskripsi' => 'membaca lancar', 'kktp_minimal' => 75]);
    \App\Domains\Akademik\Models\NilaiSiswa::factory()->create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 88]);

    $response = $this->actingAs($guruUser)->post(route('guru.rapor.catatan.generate-narasi', ['siswa' => $siswa->id, 'semester_id' => $semester->id]));
    $response->assertOk();
    $response->assertJson(['narasi' => 'Menunjukkan penguasaan sangat baik dalam membaca lancar.']);
});
```

Note: `semester_id` is passed as a query string param via the route array (`['siswa' => ..., 'semester_id' => ...]`), not as a POST body field — `generateNarasi()` reads it via `$request->query('semester_id')`.

- [ ] **Step 3: Run the tests**

Run: `php artisan test tests/Feature/Guru/RaporControllerTest.php`
Expected: PASS — 11 passed

- [ ] **Step 4: Commit**

```bash
git add routes/admin.php tests/Feature/Guru/RaporControllerTest.php
git commit -m "feat(akademik): route update() dan generateNarasi() Guru\\RaporController"
```

---

### Task 4: `ajukan()` — submit pengajuan rapor

**Files:**
- Modify: `routes/admin.php` (add POST route)
- Test: `tests/Feature/Guru/RaporControllerTest.php` (append)

**Interfaces:**
- Consumes: `App\Http\Controllers\Guru\RaporController::ajukan()` (already written in Task 2's file).

- [ ] **Step 1: Add the route**

In `routes/admin.php`, inside the same `guru.` group, add this as the last line before the group's closing `});`:

```php
    Route::post('rapor/ajukan', [GuruRaporController::class, 'ajukan'])->name('rapor.pengajuan.submit');
```

- [ ] **Step 2: Write the failing tests** (append to `tests/Feature/Guru/RaporControllerTest.php`)

```php
it('submits the pengajuan rapor when every siswa has a CatatanWaliKelas', function () {
    ['guruUser' => $guruUser, 'kelas' => $kelas, 'siswa' => $siswa, 'semester' => $semester] = siapkanWaliKelasUntukRapor();
    CatatanWaliKelas::factory()->create(['siswa_id' => $siswa->id, 'semester_id' => $semester->id]);

    $response = $this->actingAs($guruUser)->post(route('guru.rapor.pengajuan.submit'), [
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
    ]);

    $response->assertRedirect(route('guru.rapor.catatan.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));
    $this->assertDatabaseHas('pengajuan_rapor', [
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
        'status' => \App\Domains\Akademik\Enums\StatusPengajuanRapor::Diajukan->value,
    ]);
});

it('redirects back with errors when a siswa is missing a CatatanWaliKelas on submit', function () {
    ['guruUser' => $guruUser, 'kelas' => $kelas, 'semester' => $semester] = siapkanWaliKelasUntukRapor();

    $response = $this->actingAs($guruUser)->post(route('guru.rapor.pengajuan.submit'), [
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
    ]);

    $response->assertSessionHasErrors('catatan_wali_kelas');
});

it('rejects submitting a pengajuan for a kelas the guru is not wali kelas of', function () {
    ['guruUser' => $guruUser, 'lembaga' => $lembaga, 'tahunAjaran' => $tahunAjaran, 'semester' => $semester] = siapkanWaliKelasUntukRapor();
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    $this->actingAs($guruUser)
        ->post(route('guru.rapor.pengajuan.submit'), ['kelas_id' => $kelasLain->id, 'semester_id' => $semester->id])
        ->assertForbidden();
});
```

- [ ] **Step 3: Run the tests**

Run: `php artisan test tests/Feature/Guru/RaporControllerTest.php`
Expected: PASS — 14 passed

- [ ] **Step 4: Commit**

```bash
git add routes/admin.php tests/Feature/Guru/RaporControllerTest.php
git commit -m "feat(akademik): route ajukan() Guru\\RaporController — submit pengajuan rapor"
```

---

### Task 5: `Lembaga\Rapor\PersetujuanController` — `index()` + `show()`

**Files:**
- Create: `app/Http/Controllers/Lembaga/Rapor/PersetujuanController.php`
- Create: `resources/views/portals/lembaga/rapor/persetujuan/index.blade.php`
- Create: `resources/views/portals/lembaga/rapor/persetujuan/_daftar.blade.php`
- Create: `resources/views/portals/lembaga/rapor/persetujuan/show.blade.php`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Rapor/RaporPersetujuanControllerTest.php`

**Interfaces:**
- Consumes: `App\Domains\Akademik\Models\PengajuanRapor` (`BelongsToTenant` — route-model-binding is automatically tenant-scoped). `App\Domains\Akademik\Services\RaporCalculationService::hitungRekapKelas()`. `App\Domains\Akademik\Models\CatatanWaliKelas`.
- Produces: `Lembaga\Rapor\PersetujuanController::index()`/`show()` — `decision()` is added in Task 6 in the same file.

**`app/Http/Controllers/Lembaga/Rapor/PersetujuanController.php`** (only `index()` and `show()` this task — the constructor already declares everything Task 6 needs):

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lembaga\Rapor;

use App\Domains\Akademik\Actions\Rapor\ApprovePengajuanRaporAction;
use App\Domains\Akademik\Actions\Rapor\VerifyPengajuanRaporAction;
use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\CatatanWaliKelas;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Domains\Akademik\Services\RaporCalculationService;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Http\Requests\Akademik\ProcessRaporApprovalRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class PersetujuanController extends BaseController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly RaporCalculationService $raporCalculationService,
        private readonly VerifyPengajuanRaporAction $verifyPengajuanRaporAction,
        private readonly ApprovePengajuanRaporAction $approvePengajuanRaporAction,
    ) {
    }

    public function index(Request $request): View|string
    {
        abort_unless($request->user()->canAny(['rapor.verify', 'rapor.approve']), 403);

        $statusYangDicari = $this->statusUntukAktor($request);

        $query = PengajuanRapor::where('status', $statusYangDicari)
            ->with(['kelas.tahunAjaran', 'semester'])
            ->when($request->search, function ($q, $search) {
                $q->whereHas('kelas', fn ($k) => $k->where('nama', 'like', "%{$search}%"));
            })
            ->latest();

        $pengajuanList = $query->get();

        if ($request->ajax()) {
            return view('portals.lembaga.rapor.persetujuan._daftar', compact('pengajuanList'))->render();
        }

        return view('portals.lembaga.rapor.persetujuan.index', compact('pengajuanList'));
    }

    public function show(PengajuanRapor $pengajuanRapor, Request $request): View
    {
        abort_unless($request->user()->canAny(['rapor.verify', 'rapor.approve']), 403);
        abort_unless($pengajuanRapor->status === $this->statusUntukAktor($request), 404, 'Pengajuan ini bukan berada di tahap Anda.');

        $pengajuanRapor->load(['kelas', 'semester', 'approvalRequest.logs.user', 'approvalRequest.currentStep']);

        $rekap = $this->raporCalculationService->hitungRekapKelas($pengajuanRapor->kelas, $pengajuanRapor->semester);
        $catatanList = CatatanWaliKelas::where('semester_id', $pengajuanRapor->semester_id)
            ->whereIn('siswa_id', $rekap['siswaList']->pluck('id'))
            ->get()
            ->keyBy('siswa_id');

        return view('portals.lembaga.rapor.persetujuan.show', array_merge([
            'pengajuanRapor' => $pengajuanRapor,
            'catatanList' => $catatanList,
        ], $rekap));
    }

    private function statusUntukAktor(Request $request): StatusPengajuanRapor
    {
        return $request->user()->can('rapor.approve') ? StatusPengajuanRapor::Diverifikasi : StatusPengajuanRapor::Diajukan;
    }
}
```

**Note on `statusUntukAktor()`**: if a user has BOTH `rapor.verify` and `rapor.approve` (should not happen given `RoleSeeder`'s deliberate role separation, but the code must not crash if it ever does), this prioritizes `rapor.approve` — i.e. shows the Kepsek inbox. This is documented, intentional behavior, not a bug.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Rapor/RaporPersetujuanControllerTest.php`:

```php
<?php

use App\Domains\Akademik\Actions\Rapor\SimpanCatatanWaliKelasAction;
use App\Domains\Akademik\Actions\Rapor\SubmitPengajuanRaporAction;
use App\Domains\Akademik\Actions\Rapor\VerifyPengajuanRaporAction;
use App\Domains\Akademik\DataTransferObjects\CatatanWaliKelasData;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Domains\Workflow\Actions\InitializeApprovalRequestAction;
use App\Domains\Workflow\Actions\ProcessApprovalAction;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\WorkflowDefinitionSeeder;
use Spatie\Permission\Models\Permission;

function siapkanAktorPersetujuan(): array
{
    Permission::firstOrCreate(['name' => 'rapor.verify', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'rapor.approve', 'guard_name' => 'web']);
    $roleWaka = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web']);
    $roleWaka->givePermissionTo(['rapor.verify']);
    $roleKepsek = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web']);
    $roleKepsek->givePermissionTo(['rapor.approve']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Kelas 5A']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $userWali = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userWaka = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userWaka->assignRole($roleWaka);
    $userKepsek = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userKepsek->assignRole($roleKepsek);

    (new SimpanCatatanWaliKelasAction())->execute(CatatanWaliKelasData::fromArray(['siswa_id' => $siswa->id, 'semester_id' => $semester->id]));
    $pengajuan = (new SubmitPengajuanRaporAction(app(InitializeApprovalRequestAction::class)))->execute($kelas, $semester, $userWali);

    return compact('lembaga', 'kelas', 'semester', 'siswa', 'userWaka', 'userKepsek', 'pengajuan');
}

it('denies access without rapor.verify or rapor.approve permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.rapor.persetujuan.index'))->assertForbidden();
});

it('shows Waka the pengajuan that is Diajukan, not the ones already Diverifikasi', function () {
    ['userWaka' => $userWaka, 'kelas' => $kelas] = siapkanAktorPersetujuan();

    $response = $this->actingAs($userWaka)->get(route('admin.rapor.persetujuan.index'));

    $response->assertOk();
    $response->assertSee('Kelas 5A');
});

it('does not let Waka open the show page for a pengajuan not at their step', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['userWaka' => $userWaka, 'userKepsek' => $userKepsek, 'pengajuan' => $pengajuan] = siapkanAktorPersetujuan();

    (new VerifyPengajuanRaporAction(app(ProcessApprovalAction::class)))->execute($pengajuan, $userWaka, ApprovalAction::Approve);

    $this->actingAs($userWaka)
        ->get(route('admin.rapor.persetujuan.show', $pengajuan))
        ->assertNotFound();
});

it('shows Kepsek the show page once the pengajuan is Diverifikasi, with rekap nilai and catatan wali kelas', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['userWaka' => $userWaka, 'userKepsek' => $userKepsek, 'pengajuan' => $pengajuan, 'siswa' => $siswa] = siapkanAktorPersetujuan();

    (new VerifyPengajuanRaporAction(app(ProcessApprovalAction::class)))->execute($pengajuan, $userWaka, ApprovalAction::Approve);

    $response = $this->actingAs($userKepsek)->get(route('admin.rapor.persetujuan.show', $pengajuan->fresh()));

    $response->assertOk();
    $response->assertSee($siswa->nama_lengkap);
    $response->assertViewHas('catatanList', fn ($list) => $list->has($siswa->id));
});

it('is tenant-scoped: PengajuanRapor from another lembaga 404s via route model binding', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['userWaka' => $userWaka] = siapkanAktorPersetujuan();

    $yayasanLain = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranLain->id]);
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $tahunAjaranLain->id]);
    $pengajuanLain = PengajuanRapor::withoutGlobalScopes()->create([
        'lembaga_id' => $lembagaLain->id, 'kelas_id' => $kelasLain->id, 'semester_id' => $semesterLain->id,
        'status' => \App\Domains\Akademik\Enums\StatusPengajuanRapor::Diajukan,
    ]);

    $this->actingAs($userWaka)->get(route('admin.rapor.persetujuan.show', $pengajuanLain))->assertNotFound();
});
```


- [ ] **Step 2: Write the controller**

Create `app/Http/Controllers/Lembaga/Rapor/PersetujuanController.php` with the content shown above.

- [ ] **Step 3: Register the routes**

In `routes/admin.php`, find these 3 existing lines (search for `Route::get('rapor', [RaporController::class`):

```php
    Route::get('rapor', [RaporController::class, 'index'])->name('rapor.index');
    Route::get('rapor/opsi', [RaporController::class, 'opsi'])->name('rapor.opsi');
    Route::get('rapor/cetak', [RaporController::class, 'cetak'])->name('rapor.cetak');
```

Add these 2 lines immediately after them (still inside the same enclosing `admin.` group — `decision` route comes in Task 6):

```php
    Route::get('rapor/persetujuan', [\App\Http\Controllers\Lembaga\Rapor\PersetujuanController::class, 'index'])->name('rapor.persetujuan.index');
    Route::get('rapor/persetujuan/{pengajuanRapor}', [\App\Http\Controllers\Lembaga\Rapor\PersetujuanController::class, 'show'])->name('rapor.persetujuan.show');
```

(Fully-qualified inline class reference — matches the style already used for `Lembaga\Sarpras\...`/`Lembaga\Pengadaan\...` controllers elsewhere in this same file, no `use` import needed.)

- [ ] **Step 4: Write the views**

Create `resources/views/portals/lembaga/rapor/persetujuan/index.blade.php`:

```blade
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        @if (session('success'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('success') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Persetujuan Rapor</h1>
                <p class="text-xs text-gray-500 mt-0.5">Daftar kelas yang menunggu keputusan Anda pada alur persetujuan rapor semester.</p>
            </div>
            <p class="text-sm text-gray-500">
                Akademik <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Persetujuan Rapor</b>
            </p>
        </div>

        <div
            class="space-y-4"
            x-data="dataTableFilter({
                filters: { search: @js(request('search', '')) },
                indexUrlBase: @js(route('admin.rapor.persetujuan.index')),
            })"
        >
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <label class="mb-1.5 block text-xs font-semibold text-gray-500">Cari Kelas</label>
                <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                    <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                    <input type="text" x-model="filters.search" @input.debounce.500ms="muatUlangDaftar()" placeholder="Nama kelas..." class="w-full border-0 bg-transparent p-0 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0">
                </div>
            </div>

            <div x-ref="tableContainer">
                @include('portals.lembaga.rapor.persetujuan._daftar', ['pengajuanList' => $pengajuanList])
            </div>
        </div>
    </div>
</x-app-layout>
```

Create `resources/views/portals/lembaga/rapor/persetujuan/_daftar.blade.php`:

```blade
<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-700">
            <thead class="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-5 py-3">Aksi</th>
                    <th class="px-5 py-3">Kelas</th>
                    <th class="px-5 py-3">Semester</th>
                    <th class="px-5 py-3">Diajukan Pada</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($pengajuanList as $pengajuan)
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-5 py-3.5">
                            <a href="{{ route('admin.rapor.persetujuan.show', $pengajuan) }}" class="font-semibold text-brand-600 hover:underline">Review & Keputusan</a>
                        </td>
                        <td class="px-5 py-3.5 font-medium text-gray-900">{{ $pengajuan->kelas->nama }}</td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $pengajuan->semester->nama }}</td>
                        <td class="px-5 py-3.5 text-gray-500">{{ $pengajuan->diajukan_pada?->format('d M Y H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-12 text-center text-gray-500">
                            <x-icon name="inbox" class="mx-auto h-8 w-8 text-gray-400 mb-2" />
                            Tidak ada pengajuan rapor yang menunggu keputusan Anda saat ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
```

Create `resources/views/portals/lembaga/rapor/persetujuan/show.blade.php` (rekap nilai + catatan wali kelas, WITHOUT a decision form yet — Task 6 appends the form):

```blade
<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4">
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Review Rapor — {{ $pengajuanRapor->kelas->nama }}</h1>
                <p class="text-xs text-gray-500 mt-0.5 font-mono">Semester: {{ $pengajuanRapor->semester->nama }}</p>
            </div>
            <a href="{{ route('admin.rapor.persetujuan.index') }}" class="text-sm font-semibold text-gray-500 hover:text-gray-700">&larr; Kembali ke Daftar</a>
        </div>

        @if ($pengajuanRapor->status === \App\Domains\Akademik\Enums\StatusPengajuanRapor::Diajukan && $pengajuanRapor->catatan_revisi)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800">
                <p class="font-semibold">Catatan revisi dari siklus sebelumnya (jika masih relevan):</p>
                <p class="mt-1">{{ $pengajuanRapor->catatan_revisi }}</p>
            </div>
        @endif

        <div class="rounded-2xl border border-gray-200 bg-white shadow-card overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="font-display text-sm font-bold text-gray-900">Rekap Nilai Per Mapel</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm min-w-[600px]">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 text-xs font-bold uppercase tracking-wider text-gray-600">
                            <th class="px-4 py-3">Nama Siswa</th>
                            @foreach ($mapelList as $mapel)
                                <th class="px-3 py-3 text-center">{{ $mapel->nama }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($siswaList as $siswa)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $siswa->nama_lengkap }}</td>
                                @foreach ($mapelList as $mapel)
                                    <td class="px-3 py-3 text-center">{{ $rekapNilai[$siswa->id][$mapel->id] ?? '—' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-3">
            <h2 class="font-display text-sm font-bold text-gray-900 px-1">Catatan Wali Kelas Per Siswa</h2>
            @foreach ($siswaList as $siswa)
                @php($catatan = $catatanList->get($siswa->id))
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                    <p class="font-semibold text-gray-900 mb-2">{{ $siswa->nama_lengkap }}</p>
                    @if ($catatan)
                        <dl class="grid grid-cols-1 gap-2 text-xs text-gray-600 sm:grid-cols-2">
                            <div><dt class="font-semibold text-gray-500">Catatan Sikap</dt><dd>{{ $catatan->catatan_sikap ?: '—' }}</dd></div>
                            <div><dt class="font-semibold text-gray-500">Catatan Perkembangan</dt><dd>{{ $catatan->catatan_perkembangan ?: '—' }}</dd></div>
                        </dl>
                    @else
                        <p class="text-xs text-error-600">Belum ada catatan wali kelas untuk siswa ini.</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 5: Run the tests**

Run: `php artisan test tests/Feature/Rapor/RaporPersetujuanControllerTest.php`
Expected: PASS — 5 passed

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Lembaga/Rapor resources/views/portals/lembaga/rapor tests/Feature/Rapor routes/admin.php
git commit -m "feat(akademik): Lembaga\\Rapor\\PersetujuanController index() + show()"
```

---

### Task 6: `decision()` — Approve/Reject

**Files:**
- Create: `app/Http/Requests/Akademik/ProcessRaporApprovalRequest.php`
- Modify: `app/Http/Controllers/Lembaga/Rapor/PersetujuanController.php` (add `decision()` method)
- Modify: `resources/views/portals/lembaga/rapor/persetujuan/show.blade.php` (append decision form)
- Modify: `routes/admin.php`
- Test: `tests/Feature/Rapor/RaporPersetujuanControllerTest.php` (append)

**Interfaces:**
- Consumes: `App\Domains\Akademik\Actions\Rapor\VerifyPengajuanRaporAction::execute(PengajuanRapor, User, ApprovalAction, ?string $catatan): PengajuanRapor` and `ApprovePengajuanRaporAction` (same signature) — both already injected in the controller's constructor since Task 5.

- [ ] **Step 1: Write the FormRequest**

Create `app/Http/Requests/Akademik/ProcessRaporApprovalRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ProcessRaporApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAny(['rapor.verify', 'rapor.approve']) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['APPROVE', 'REJECT'])],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

- [ ] **Step 2: Write the failing tests** (append to `tests/Feature/Rapor/RaporPersetujuanControllerTest.php`)

```php
it('lets Waka approve, advancing status to Diverifikasi', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['userWaka' => $userWaka, 'pengajuan' => $pengajuan] = siapkanAktorPersetujuan();

    $response = $this->actingAs($userWaka)->post(route('admin.rapor.persetujuan.decision', $pengajuan), [
        'action' => 'APPROVE', 'catatan' => 'Lengkap dan sudah sesuai.',
    ]);

    $response->assertRedirect(route('admin.rapor.persetujuan.index'));
    $this->assertDatabaseHas('pengajuan_rapor', [
        'id' => $pengajuan->id,
        'status' => \App\Domains\Akademik\Enums\StatusPengajuanRapor::Diverifikasi->value,
    ]);
});

it('lets Waka reject, setting status to Ditolak with catatan_revisi', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['userWaka' => $userWaka, 'pengajuan' => $pengajuan] = siapkanAktorPersetujuan();

    $this->actingAs($userWaka)->post(route('admin.rapor.persetujuan.decision', $pengajuan), [
        'action' => 'REJECT', 'catatan' => 'Nilai belum lengkap.',
    ]);

    $this->assertDatabaseHas('pengajuan_rapor', [
        'id' => $pengajuan->id,
        'status' => \App\Domains\Akademik\Enums\StatusPengajuanRapor::Ditolak->value,
        'catatan_revisi' => 'Nilai belum lengkap.',
    ]);
});

it('lets Kepsek approve a Diverifikasi pengajuan, advancing status to Disetujui', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['userWaka' => $userWaka, 'userKepsek' => $userKepsek, 'pengajuan' => $pengajuan] = siapkanAktorPersetujuan();
    (new VerifyPengajuanRaporAction(app(ProcessApprovalAction::class)))->execute($pengajuan, $userWaka, ApprovalAction::Approve);

    $this->actingAs($userKepsek)->post(route('admin.rapor.persetujuan.decision', $pengajuan->fresh()), ['action' => 'APPROVE']);

    $this->assertDatabaseHas('pengajuan_rapor', [
        'id' => $pengajuan->id,
        'status' => \App\Domains\Akademik\Enums\StatusPengajuanRapor::Disetujui->value,
    ]);
});

it('rejects REQUEST_REVISION as an invalid action value with a 422', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['userWaka' => $userWaka, 'pengajuan' => $pengajuan] = siapkanAktorPersetujuan();

    $this->actingAs($userWaka)
        ->post(route('admin.rapor.persetujuan.decision', $pengajuan), ['action' => 'REQUEST_REVISION'])
        ->assertSessionHasErrors('action');
});

it('rejects a decision from the wrong step (Kepsek trying to decide before Waka verifies)', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['userKepsek' => $userKepsek, 'pengajuan' => $pengajuan] = siapkanAktorPersetujuan();

    $this->actingAs($userKepsek)
        ->post(route('admin.rapor.persetujuan.decision', $pengajuan), ['action' => 'APPROVE'])
        ->assertNotFound();
});
```

- [ ] **Step 3: Add `decision()` to the controller**

Edit `app/Http/Controllers/Lembaga/Rapor/PersetujuanController.php` — add this method right after `show()` (before the closing `private function statusUntukAktor` stays as the last method):

```php
    public function decision(ProcessRaporApprovalRequest $request, PengajuanRapor $pengajuanRapor): RedirectResponse
    {
        abort_unless($pengajuanRapor->status === $this->statusUntukAktor($request), 404, 'Pengajuan ini bukan berada di tahap Anda.');

        $action = ApprovalAction::from($request->validated('action'));
        $catatan = $request->validated('catatan');

        if ($request->user()->can('rapor.approve')) {
            $this->approvePengajuanRaporAction->execute($pengajuanRapor, $request->user(), $action, $catatan);
        } else {
            $this->verifyPengajuanRaporAction->execute($pengajuanRapor, $request->user(), $action, $catatan);
        }

        $pesan = $action === ApprovalAction::Approve
            ? 'Keputusan berhasil disimpan.'
            : 'Pengajuan berhasil ditolak. Wali kelas dapat mengajukan ulang setelah revisi.';

        return redirect()->route('admin.rapor.persetujuan.index')->with('success', $pesan);
    }
```

(The `use App\Http\Requests\Akademik\ProcessRaporApprovalRequest;` import was already added to the top of the file back in Task 5's version of this controller — verify it's there; if not, add it now.)

- [ ] **Step 4: Register the decision route**

In `routes/admin.php`, right after the `rapor.persetujuan.show` line added in Task 5, add:

```php
    Route::post('rapor/persetujuan/{pengajuanRapor}/keputusan', [\App\Http\Controllers\Lembaga\Rapor\PersetujuanController::class, 'decision'])->name('rapor.persetujuan.decision');
```

- [ ] **Step 5: Append the decision form to `show.blade.php`**

Add this block right before the closing `</div>` and `</x-app-layout>` tags of `resources/views/portals/lembaga/rapor/persetujuan/show.blade.php` (after the "Catatan Wali Kelas Per Siswa" section's closing `</div>`):

```blade
        <form method="POST" action="{{ route('admin.rapor.persetujuan.decision', $pengajuanRapor) }}" x-data="{ action: 'APPROVE' }" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
            @csrf
            <label class="block text-xs font-semibold text-gray-700">Keputusan</label>
            <div class="flex flex-wrap gap-4">
                <label class="flex items-center gap-2 text-xs font-medium text-gray-800 cursor-pointer">
                    <input type="radio" name="action" value="APPROVE" x-model="action" class="text-brand-600 focus:ring-brand-500">
                    <span>Setujui</span>
                </label>
                <label class="flex items-center gap-2 text-xs font-medium text-rose-800 cursor-pointer">
                    <input type="radio" name="action" value="REJECT" x-model="action" class="text-rose-600 focus:ring-rose-500">
                    <span>Tolak, Minta Revisi Wali Kelas</span>
                </label>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Catatan (Opsional)</label>
                <textarea name="catatan" rows="2" placeholder="Catatan untuk wali kelas..." class="w-full rounded-lg border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500"></textarea>
            </div>
            <div class="flex items-center justify-end">
                <x-primary-button type="submit">Kirim Keputusan</x-primary-button>
            </div>
        </form>
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test tests/Feature/Rapor/RaporPersetujuanControllerTest.php`
Expected: PASS — 10 passed

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/Akademik/ProcessRaporApprovalRequest.php app/Http/Controllers/Lembaga/Rapor/PersetujuanController.php resources/views/portals/lembaga/rapor/persetujuan/show.blade.php tests/Feature/Rapor/RaporPersetujuanControllerTest.php routes/admin.php
git commit -m "feat(akademik): decision() Lembaga\\Rapor\\PersetujuanController — Approve/Reject only"
```

---

### Task 7: Navigation + Final Verification + Handoff

**Files:**
- Modify: `resources/views/layouts/sidebar.blade.php`
- Modify: `.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md`
- Create: `.agents/logs/2026-08-19-1600-akademik-04c-rapor-ui.md`

- [ ] **Step 1: Add sidebar entries**

In `resources/views/layouts/sidebar.blade.php`, find the "Ruang Guru" group's `items` array (the line with `Auth::user()->can('asesmen.kelola') ? ['route' => 'guru.asesmen.index', ...`). Add this line right after it, still inside `array_filter([...])`:

```php
                Auth::user()->can('rapor.input-wali') ? ['route' => 'guru.rapor.catatan.index', 'pattern' => 'guru.rapor.*', 'label' => 'Rapor Wali Kelas', 'icon' => 'book-text'] : null,
```

Find the "Akademik" group's `items` array (the line with `Auth::user()->can('rapor.view') ? ['route' => 'admin.rapor.index', ...`). Add this line right after it:

```php
                Auth::user()->canAny(['rapor.verify', 'rapor.approve']) ? ['route' => 'admin.rapor.persetujuan.index', 'pattern' => 'admin.rapor.persetujuan.*', 'label' => 'Persetujuan Rapor', 'icon' => 'check-square'] : null,
```

- [ ] **Step 2: Manually verify sidebar rendering**

Run: `php artisan route:list --name=guru.rapor` and `php artisan route:list --name=admin.rapor.persetujuan`
Expected: 5 routes and 3 routes respectively, all present, no errors.

- [ ] **Step 3: Ask the user for permission, then run the full test suite**

Ask: "Sub-Task 04c selesai diimplementasikan. Jalankan full test suite `php artisan test` sekarang untuk verifikasi akhir?"

If approved, run (synchronously, do not background this):

Run: `php artisan test`
Expected: all tests pass, 0 failed (baseline before this sub-task was 1797 passed per Sub-Task 04b's handoff log — this task's new tests add 2 + 14 + 10 = 26 more, expect roughly 1823 passed, 0 failed; exact count may differ slightly from other unrelated test additions between sessions, the important number is **0 failed**)

If any test fails, fix it before proceeding — do not write the handoff log with known failures.

- [ ] **Step 4: Update the master plan**

Edit `.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md`. Find this line:

```
| **04c** | **UI 4 Role (Guru/Wali Kelas/Waka/Kepsek)** | `.agents/specs/akademik-04c-rapor-ui.md` | `.agents/plans/akademik-04c-rapor-ui.md` | `.agents/logs/akademik-04c-rapor-ui.md` | ⚪ PENDING |
```

Replace it with:

```
| **04c** | **UI 4 Role (Guru/Wali Kelas/Waka/Kepsek)** | [`.agents/specs/2026-08-19-1600-akademik-04c-rapor-ui.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-19-1600-akademik-04c-rapor-ui.md) | [`.agents/plans/2026-08-19-1600-akademik-04c-rapor-ui.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-19-1600-akademik-04c-rapor-ui.md) | [`.agents/logs/2026-08-19-1600-akademik-04c-rapor-ui.md`](file:///d:/laragon/www/pintera-app/.agents/logs/2026-08-19-1600-akademik-04c-rapor-ui.md) | 🟢 **SELESAI (COMPLETED)** |
```

Find this line:

```
- **Status Master:** 🟡 IN PROGRESS (Sub-Task 01, 02, 03a, 03b, 03c, 04a, 04b SELESAI — Sub-Task 04c/04d belum dimulai)
```

Replace it with:

```
- **Status Master:** 🟡 IN PROGRESS (Sub-Task 01, 02, 03a, 03b, 03c, 04a, 04b, 04c SELESAI — Sub-Task 04d belum dimulai)
```

- [ ] **Step 5: Write the handoff log**

Create `.agents/logs/2026-08-19-1600-akademik-04c-rapor-ui.md`:

```markdown
# 📋 Handoff Log: Sub-Task 04c — Adaptive E-Rapor Engine: UI 4 Role

- **Spec:** [`.agents/specs/2026-08-19-1600-akademik-04c-rapor-ui.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-19-1600-akademik-04c-rapor-ui.md)
- **Plan:** [`.agents/plans/2026-08-19-1600-akademik-04c-rapor-ui.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-19-1600-akademik-04c-rapor-ui.md)
- **Status:** 🟢 SELESAI

## Ringkasan

UI headless-consumer di atas backend 04a/04b: `Guru\RaporController` (wali kelas isi CatatanWaliKelas per siswa + ajukan rapor kelas) dan `Lembaga\Rapor\PersetujuanController` (inbox gabungan Waka+Kepsek, satu controller melayani kedua step karena keduanya scope lembaga). Satu Action backend baru: `GenerateNarasiPerkembanganAction` (menggabungkan narasi capaian lintas-mapel dari `CapaianKompetensiGenerator` yang sudah ada). Tidak ada permission baru — 4 permission `rapor.*` dari 04b sudah cukup. Form keputusan Waka/Kepsek sengaja hanya 2 opsi (Approve/Reject), TIDAK meniru opsi "Minta Revisi" milik Pengadaan, karena Action Rapor tidak punya cabang sinkronisasi status untuk RequestRevision.

## Item Terbuka untuk Sub-Task Berikutnya

1. **Sub-Task 04d**: 4 Template PDF Resmi DomPDF Berbasis Jenjang (PAUD, SD, SMP/SMA, SMK).
2. Gap arsitektur `TenantScope.php` tanpa filter yayasan (ditemukan di 04a) masih terbuka, belum diputuskan.
```

- [ ] **Step 6: Commit**

```bash
git add resources/views/layouts/sidebar.blade.php .agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md .agents/logs/2026-08-19-1600-akademik-04c-rapor-ui.md
git commit -m "docs(akademik): tutup Sub-Task 04c, update master plan & handoff log"
```

---

## Self-Review Notes (already applied above, kept here for transparency)

- **Spec coverage**: §3.6 (`GenerateNarasiPerkembanganAction`) → Task 1. §4 (Guru controller, all 5 actions) → Tasks 2–4. §5 (Persetujuan controller, all 3 actions) → Tasks 5–6. §6 (sidebar) → Task 7. §7 (testing) → covered per-task. §8 (assumptions: no lock on CatatanWaliKelas, catatan_revisi staleness, badge definition, jenjang whitelist) → encoded as Global Constraints and inline design decisions in Tasks 2 and 5. No gaps found.
- **Placeholder scan**: none — every step has complete, real code, no TBD/TODO markers, no dead code left for the implementer to prune.
- **Type/signature consistency**: `GenerateNarasiPerkembanganAction::execute(Siswa, Kelas, Semester): string` is identical between Task 1's definition and Task 3's controller usage. `StoreCatatanWaliKelasRequest`/`SubmitPengajuanRaporRequest` are defined once (Task 2) and never redefined. Route names (`guru.rapor.catatan.index/edit/update/generate-narasi`, `guru.rapor.pengajuan.submit`, `admin.rapor.persetujuan.index/show/decision`) are consistent across every task that references them.
