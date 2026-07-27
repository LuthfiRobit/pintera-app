# Tahap 6 — Duplikasi Awal Tahun Ajaran (Kenaikan Kelas) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Kenaikan Kelas wizard — admin maps each kelas from a previous tahun ajaran to either a target kelas in the new tahun ajaran (moving its siswa along) or "Lulus" (graduating its siswa), with an option to copy that kelas's `jadwal_pelajaran` structure into the new kelas for a chosen semester.

**Architecture:** A single-slice wizard controller. **Design reconciliation up front** (see Global Constraints) — the original design spec's Section 5 describes 4 duplication steps ("salin Pola Jam", "salin Mata Pelajaran", Kenaikan Kelas, "salin Jadwal"), but Tahap 1 and Tahap 4 built `PolaJam` and `MataPelajaran` as **lembaga-scoped, not tahun-ajaran-scoped** — they already persist across years without any copy step. Only two real actions remain: Kenaikan Kelas (this plan's core) and copying `JadwalPelajaran` rows (folded into the same form as an option).

**Tech Stack:** Laravel 12, Blade, Pest 4.

**Pre-flight correction (2026-07-28, before any task dispatched):** This plan was drafted 2026-07-24, before both (a) the post-Tahap-2 design system correction (it used the old `text-ink`/`bg-paper`/`text-brass`/`<x-panel>`/`<x-slot name="header">` token set — corrected below to the current TailAdmin style already used by `admin/komponen-penilaian/*.blade.php`, the most recently-shipped reference), and (b) this project's now-well-established recurring cross-lembaga IDOR pattern (found and fixed 5 times across Tahap 3/3b/4/4b/7). The original `store()` code validated `kelas_baru_id`/`semester_tujuan_id` with raw `exists:table,column` rules and never checked they belonged to the same lembaga as the source `kelas_lama` — meaning an admin could move students into, or copy a jadwal into, another lembaga's kelas/semester. Corrected below to resolve both FKs via `Kelas::find()`/`Semester::find()` (both tenant-scoped) plus an explicit `lembaga_id` cross-check, per this project's standing rule. Also wraps the whole mapping loop in `DB::transaction()`, per the standing lesson from Tahap 2's own batch-`Siswa`-update gap ("Any future tahap adding another batch-creation flow (e.g. Tahap 6's Kenaikan Kelas siswa-moving loop) should apply the same transaction-wrapping ... pattern from the start").

## Global Constraints

- **Spec deviation, documented**: `PolaJam` (Tahap 4 Task 2) and `MataPelajaran` (Tahap 1 Task 2) have no `tahun_ajaran_id` column — they are reusable lembaga-level templates, not per-year data. The design spec's "Langkah 1 — Salin Pola Jam" and "Langkah 2 — Salin Mata Pelajaran" are therefore **no-ops in this implementation**: nothing to build, because there is nothing to copy — both already carry forward automatically. This plan implements only the two steps that involve real per-year data: Kenaikan Kelas (kelas + siswa) and, optionally, Jadwal Pelajaran.
- Same conventions as Tahap 1-5 (`casts()` method style, inline validation, `AuthorizesRequests`, `permissions:sync`).
- Follow the TailAdmin visual style already used throughout `resources/views/admin/komponen-penilaian/*.blade.php` (breadcrumb `<h1>`+`<p>` header, `rounded-2xl border border-gray-200 bg-white shadow-card`, `<x-input-label>`/`<x-input-error>`/`<x-primary-button>`, flash/error toast blocks) — do not use the old `<x-panel>`/`text-ink`/`bg-paper` token set.
- Any FK validated in `store()` that points at a tenant-scoped model (`Kelas`, `Semester` both use `BelongsToTenant`) must be resolved via `Model::find($id)` + `abort(404)` when null — never a raw `exists:table,column` rule. Any write combining two independently-resolved tenant-scoped models (source kelas + target kelas; source kelas + target semester) must explicitly compare `lembaga_id` and `abort(404)` on mismatch.
- The entire `store()` mapping loop must run inside `DB::transaction()` — a partial failure partway through a multi-kelas batch must not leave some kelas promoted and others not.
- Moving a `Siswa` to a new `Kelas` is a plain `update()` — it does **not** create a new `Siswa` row. Graduating sets `status = lulus` and `kelas_id = null` on the same row, preserving all history (NIS, `sumber_data`, `calon_murid_id`, etc.).
- The target `Kelas` for promotion must already exist (created via the normal Kelas CRUD from Tahap 1) before this wizard runs — this plan does not auto-create kelas rows, to avoid silently creating typo'd class names.

---

### Task 1: Kenaikan Kelas wizard (mapping + siswa move/graduate + optional jadwal copy)

**Files:**
- Create: `app/Http/Controllers/Admin/KenaikanKelasController.php`
- Create: `resources/views/admin/kenaikan-kelas/index.blade.php`
- Modify: `routes/admin.php`
- Modify: `resources/views/layouts/sidebar.blade.php`
- Modify: `database/seeders/RoleSeeder.php`
- Test: `tests/Feature/Admin/KenaikanKelasControllerTest.php`
- Test: `tests/Unit/RoleSeederTest.php`

**Interfaces:**
- Consumes: `App\Models\Kelas`, `App\Models\Siswa`, `App\Models\JadwalPelajaran` (Tahap 1/4), `App\Enums\StatusSiswa` (Tahap 1).
- Produces: Routes `admin.kenaikan-kelas.index` (GET, pick source tahun ajaran → list its kelas with mapping form), `admin.kenaikan-kelas.store` (POST, process mappings), permission `kenaikan-kelas.kelola`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/KenaikanKelasControllerTest.php`:

```php
<?php

use App\Enums\StatusSiswa;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\PolaJam;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsKenaikanKelasManager(Lembaga $lembaga): User
{
    Permission::firstOrCreate(['name' => 'kenaikan-kelas.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kenaikan-kelas.kelola']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('denies access without kenaikan-kelas.kelola permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.kenaikan-kelas.index'))->assertForbidden();
});

it('lists kelas belonging to the selected source tahun ajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2025/2026']);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id, 'nama' => '5A']);
    $manager = actingAsKenaikanKelasManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.kenaikan-kelas.index', ['tahun_ajaran_id' => $tahunLalu->id]));

    $response->assertOk();
    $response->assertViewHas('kelasLamaList', fn ($list) => $list->contains('id', $kelasLama->id));
});

it('moves siswa to the target kelas when mapped to promotion', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id, 'nama' => '5A']);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id, 'nama' => '6A']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasLama->id, 'status' => StatusSiswa::Aktif->value]);
    $manager = actingAsKenaikanKelasManager($lembaga);

    $this->actingAs($manager)->post(route('admin.kenaikan-kelas.store'), [
        'mapping' => [
            $kelasLama->id => ['tindakan' => 'naik', 'kelas_baru_id' => $kelasBaru->id],
        ],
    ])->assertRedirect(route('admin.kelas.index'));

    $siswa->refresh();
    expect($siswa->kelas_id)->toBe($kelasBaru->id);
    expect($siswa->status)->toBe(StatusSiswa::Aktif);
});

it('graduates siswa when mapped to lulus, clearing kelas_id', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id, 'nama' => '6A']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasLama->id, 'status' => StatusSiswa::Aktif->value]);
    $manager = actingAsKenaikanKelasManager($lembaga);

    $this->actingAs($manager)->post(route('admin.kenaikan-kelas.store'), [
        'mapping' => [
            $kelasLama->id => ['tindakan' => 'lulus'],
        ],
    ])->assertRedirect(route('admin.kelas.index'));

    $siswa->refresh();
    expect($siswa->status)->toBe(StatusSiswa::Lulus);
    expect($siswa->kelas_id)->toBeNull();
});

it('optionally copies jadwal pelajaran structure to the target kelas and semester', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semesterLalu = Semester::factory()->create(['tahun_ajaran_id' => $tahunLalu->id]);
    $semesterBaru = Semester::factory()->create(['tahun_ajaran_id' => $tahunBaru->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id, 'pola_jam_id' => $pola->id, 'nama' => '5A']);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id, 'pola_jam_id' => $pola->id, 'nama' => '6A']);
    JadwalPelajaran::create([
        'kelas_id' => $kelasLama->id, 'jam_pelajaran_id' => $jam->id, 'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $guru->id, 'semester_id' => $semesterLalu->id,
    ]);
    $manager = actingAsKenaikanKelasManager($lembaga);

    $this->actingAs($manager)->post(route('admin.kenaikan-kelas.store'), [
        'mapping' => [
            $kelasLama->id => [
                'tindakan' => 'naik',
                'kelas_baru_id' => $kelasBaru->id,
                'salin_jadwal' => '1',
                'semester_tujuan_id' => $semesterBaru->id,
            ],
        ],
    ])->assertRedirect(route('admin.kelas.index'));

    $jadwalBaru = JadwalPelajaran::where('kelas_id', $kelasBaru->id)->where('semester_id', $semesterBaru->id)->first();
    expect($jadwalBaru)->not->toBeNull();
    expect($jadwalBaru->jam_pelajaran_id)->toBe($jam->id);
    expect($jadwalBaru->mata_pelajaran_id)->toBe($mapel->id);
    expect($jadwalBaru->guru_id)->toBe($guru->id);
});

it('rejects promoting siswa into a kelas belonging to a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembagaSaya->id, 'tahun_ajaran_id' => $tahunLalu->id, 'nama' => '5A']);
    $tahunLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $tahunLain->id, 'nama' => '6A']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembagaSaya->id, 'kelas_id' => $kelasLama->id, 'status' => StatusSiswa::Aktif->value]);
    $manager = actingAsKenaikanKelasManager($lembagaSaya);

    $this->actingAs($manager)->post(route('admin.kenaikan-kelas.store'), [
        'mapping' => [
            $kelasLama->id => ['tindakan' => 'naik', 'kelas_baru_id' => $kelasLain->id],
        ],
    ])->assertNotFound();

    $siswa->refresh();
    expect($siswa->kelas_id)->toBe($kelasLama->id);
});

it('rejects copying jadwal into a semester belonging to a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembagaSaya->id, 'tahun_ajaran_id' => $tahunLalu->id, 'nama' => '5A']);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembagaSaya->id, 'tahun_ajaran_id' => $tahunBaru->id, 'nama' => '6A']);
    $tahunLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunLain->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembagaSaya->id, 'kelas_id' => $kelasLama->id, 'status' => StatusSiswa::Aktif->value]);
    $manager = actingAsKenaikanKelasManager($lembagaSaya);

    $this->actingAs($manager)->post(route('admin.kenaikan-kelas.store'), [
        'mapping' => [
            $kelasLama->id => [
                'tindakan' => 'naik',
                'kelas_baru_id' => $kelasBaru->id,
                'salin_jadwal' => '1',
                'semester_tujuan_id' => $semesterLain->id,
            ],
        ],
    ])->assertNotFound();

    $siswa->refresh();
    expect($siswa->kelas_id)->toBe($kelasLama->id);
    expect(JadwalPelajaran::where('kelas_id', $kelasBaru->id)->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/KenaikanKelasControllerTest.php`
Expected: FAIL with route `admin.kenaikan-kelas.index` not defined.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Admin/KenaikanKelasController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusSiswa;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class KenaikanKelasController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('kenaikan-kelas.kelola');

        $tahunAjaranId = $request->query('tahun_ajaran_id');

        return view('admin.kenaikan-kelas.index', [
            'tahunAjaranList' => TahunAjaran::orderByDesc('tanggal_mulai')->get(),
            'kelasLamaList' => $tahunAjaranId
                ? Kelas::where('tahun_ajaran_id', $tahunAjaranId)->withCount('siswa')->orderBy('nama')->get()
                : collect(),
            'kelasTujuanList' => Kelas::orderBy('nama')->get(),
            'semesterList' => Semester::orderByDesc('id')->get(),
            'tahunAjaranId' => $tahunAjaranId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('kenaikan-kelas.kelola');

        $data = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.*.tindakan' => ['required', 'in:naik,lulus'],
            'mapping.*.kelas_baru_id' => ['required_if:mapping.*.tindakan,naik', 'nullable', 'integer'],
            'mapping.*.salin_jadwal' => ['nullable', 'boolean'],
            'mapping.*.semester_tujuan_id' => ['nullable', 'integer'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['mapping'] as $kelasLamaId => $aksi) {
                $kelasLama = Kelas::findOrFail($kelasLamaId);

                if ($aksi['tindakan'] === 'lulus') {
                    Siswa::where('kelas_id', $kelasLama->id)->update([
                        'status' => StatusSiswa::Lulus->value,
                        'kelas_id' => null,
                    ]);

                    continue;
                }

                $kelasBaru = Kelas::find($aksi['kelas_baru_id']);
                abort_if($kelasBaru === null || $kelasBaru->lembaga_id !== $kelasLama->lembaga_id, 404);

                Siswa::where('kelas_id', $kelasLama->id)->update(['kelas_id' => $kelasBaru->id]);

                if (($aksi['salin_jadwal'] ?? false) && ! empty($aksi['semester_tujuan_id'])) {
                    $semesterTujuan = Semester::find($aksi['semester_tujuan_id']);
                    abort_if($semesterTujuan === null || $semesterTujuan->lembaga_id !== $kelasLama->lembaga_id, 404);

                    $this->salinJadwal($kelasLama->id, $kelasBaru->id, $semesterTujuan->id);
                }
            }
        });

        return redirect()->route('admin.kelas.index')->with('status', 'Kenaikan kelas berhasil diproses.');
    }

    private function salinJadwal(int $kelasLamaId, int $kelasBaruId, int $semesterTujuanId): void
    {
        $jadwalLama = JadwalPelajaran::where('kelas_id', $kelasLamaId)->get();

        foreach ($jadwalLama as $jadwal) {
            JadwalPelajaran::firstOrCreate([
                'kelas_id' => $kelasBaruId,
                'jam_pelajaran_id' => $jadwal->jam_pelajaran_id,
                'semester_id' => $semesterTujuanId,
            ], [
                'mata_pelajaran_id' => $jadwal->mata_pelajaran_id,
                'guru_id' => $jadwal->guru_id,
            ]);
        }
    }
}
```

**Note on `abort_if` inside `DB::transaction()`:** Laravel's `abort_if()` throws an `HttpException`, which propagates out of the transaction closure and triggers Laravel's automatic rollback (any exception thrown inside `DB::transaction()`'s closure rolls back before rethrowing) — no manual `DB::rollBack()` needed.

- [ ] **Step 4: Add routes**

In `routes/admin.php`, add:

```php
Route::get('kenaikan-kelas', [KenaikanKelasController::class, 'index'])->name('kenaikan-kelas.index');
Route::post('kenaikan-kelas', [KenaikanKelasController::class, 'store'])->name('kenaikan-kelas.store');
```

Add `use App\Http\Controllers\Admin\KenaikanKelasController;` at the top.

- [ ] **Step 5: Create the view**

Create `resources/views/admin/kenaikan-kelas/index.blade.php`:

```blade
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Kenaikan Kelas</h1>
            <p class="text-sm text-gray-500">
                Akademik <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Kenaikan Kelas</b>
            </p>
        </div>

        {{-- Source Tahun Ajaran Picker --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card">
            <form method="GET" action="{{ route('admin.kenaikan-kelas.index') }}" class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[220px]">
                    <x-input-label value="Tahun Ajaran Sumber (kelas lama)" />
                    <select name="tahun_ajaran_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Pilih —</option>
                        @foreach ($tahunAjaranList as $tahunAjaran)
                            <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <x-primary-button type="submit">Tampilkan</x-primary-button>
            </form>
        </div>

        @if ($kelasLamaList->isNotEmpty())
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
                <div class="border-b border-gray-100 bg-white px-6 py-4">
                    <p class="font-display text-sm font-bold text-gray-900">Pemetaan Kenaikan Kelas</p>
                    <p class="mt-0.5 text-xs text-gray-500">Tentukan tindakan untuk setiap kelas lama: naikkan ke kelas tujuan, atau luluskan.</p>
                </div>

                <form method="POST" action="{{ route('admin.kenaikan-kelas.store') }}">
                    @csrf

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[800px] text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-100 text-xs uppercase font-bold tracking-wider text-gray-600">
                                    <th class="px-6 py-3.5">Kelas Lama</th>
                                    <th class="px-4 py-3.5 text-center">Jml Siswa</th>
                                    <th class="px-4 py-3.5">Tindakan</th>
                                    <th class="px-4 py-3.5">Kelas Tujuan</th>
                                    <th class="px-4 py-3.5">Salin Jadwal ke Semester</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($kelasLamaList as $kelasLama)
                                    <tr class="transition hover:bg-gray-50/60">
                                        <td class="px-6 py-4 font-bold text-gray-900">{{ $kelasLama->nama }}</td>
                                        <td class="px-4 py-4 text-center text-gray-500">{{ $kelasLama->siswa_count }}</td>
                                        <td class="px-4 py-4">
                                            <select name="mapping[{{ $kelasLama->id }}][tindakan]" class="rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                                <option value="naik">Naik Kelas</option>
                                                <option value="lulus">Lulus</option>
                                            </select>
                                        </td>
                                        <td class="px-4 py-4">
                                            <select name="mapping[{{ $kelasLama->id }}][kelas_baru_id]" class="rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                                <option value="">—</option>
                                                @foreach ($kelasTujuanList as $kelasBaru)
                                                    <option value="{{ $kelasBaru->id }}">{{ $kelasBaru->nama }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-4 py-4">
                                            <label class="flex items-center gap-2">
                                                <input type="checkbox" name="mapping[{{ $kelasLama->id }}][salin_jadwal]" value="1" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                                <select name="mapping[{{ $kelasLama->id }}][semester_tujuan_id]" class="rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                                    <option value="">—</option>
                                                    @foreach ($semesterList as $semester)
                                                        <option value="{{ $semester->id }}">{{ $semester->nama }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-end border-t border-gray-100 bg-gray-50/50 px-6 py-4">
                        <x-primary-button type="submit">Proses Kenaikan Kelas</x-primary-button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
```

- [ ] **Step 6: Add sidebar entry**

In `resources/views/layouts/sidebar.blade.php`, inside the `'III. Akademik'` group, add after the `komponen-penilaian.kelola` entry (the current last entry in that group — this plan originally said "after `jadwal-pelajaran.kelola`", but Tahap 7 added `komponen-penilaian.kelola` after it since this plan was drafted):

```php
Auth::user()->can('kenaikan-kelas.kelola') ? ['route' => 'admin.kenaikan-kelas.index', 'pattern' => 'admin.kenaikan-kelas.*', 'label' => 'Kenaikan Kelas', 'icon' => 'trending_up'] : null,
```

- [ ] **Step 7: Sync permissions and grant to a real role**

Run: `php artisan permissions:sync`
Expected: Output includes `Created permission: kenaikan-kelas.kelola`.

**This is a new, standalone step this plan didn't originally have** — added 2026-07-28 after the Tahap 7 remediation audit found that `presensi.isi`/`asesmen.kelola`/`komponen-penilaian.kelola`/`rapor.view` had all been shipped without ever being granted to a real production role in `RoleSeeder`, making those features unreachable on a fresh seed. Do not repeat that gap here.

In `database/seeders/RoleSeeder.php`, add `kenaikan-kelas.kelola` to the existing `kepala_sekolah` permission array (the same role Tahap 7's remediation granted `komponen-penilaian.kelola`/`rapor.view` to, as the closest existing lembaga-scoped academic role — a dedicated `admin_akademik` role still doesn't exist in the real seeder, a separate pre-existing gap out of scope here):

```php
            if ($name === 'kepala_sekolah') {
                $role->givePermissionTo([
                    'spmb-pendaftaran.view', 'spmb-pendaftaran.verifikasi-dokumen', 'spmb-pendaftaran.nilai-seleksi',
                    'spmb-pendaftaran.tetapkan-keputusan', 'spmb-pendaftaran.terbitkan-sk',
                    'tagihan.view',
                    'komponen-penilaian.kelola', 'rapor.view',
                    'kenaikan-kelas.kelola',
                ]);
            }
```

Add a test case to `tests/Unit/RoleSeederTest.php` asserting `kepala_sekolah` has `kenaikan-kelas.kelola` after `permissions:sync` + `(new RoleSeeder())->run()`, following the existing pattern in that file (and update any hardcoded total-permission-count assertion in that file / `tests/Unit/PermissionSeederTest.php` / `tests/Feature/RolePermissionSeederTest.php` if one exists and doesn't auto-adjust — check these three files for a hardcoded count before assuming none needs updating, since this exact oversight caused a review round-trip during the Tahap 7 remediation).

Run: `php artisan test tests/Unit/RoleSeederTest.php tests/Unit/PermissionSeederTest.php tests/Feature/RolePermissionSeederTest.php`
Expected: PASS, no hardcoded-count regressions.

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test tests/Feature/Admin/KenaikanKelasControllerTest.php`
Expected: PASS (7 tests)

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Admin/KenaikanKelasController.php resources/views/admin/kenaikan-kelas routes/admin.php resources/views/layouts/sidebar.blade.php database/seeders/RoleSeeder.php tests/Feature/Admin/KenaikanKelasControllerTest.php tests/Unit/RoleSeederTest.php
git commit -m "feat: add Kenaikan Kelas wizard with optional jadwal copy"
```

- [ ] **Step 10: Re-seed the real dev DB**

Per this project's standing post-migration/seeder step: run `php artisan db:seed --class=RolePermissionSeeder` against the real dev DB (`pintera_app`) after merging, so `kepala_sekolah`'s new `kenaikan-kelas.kelola` grant actually reaches the shared database, not just the test DB.

---

## Plan Self-Review Notes

- **Spec coverage**: Implements spec Section 5's actionable core (Kenaikan Kelas + Jadwal copy). Section 5's "Langkah 1/2" (salin Pola Jam / salin Mata Pelajaran) are explicitly not implemented — documented in Global Constraints as a deliberate deviation, since Tahap 1/4 made both lembaga-scoped rather than tahun-ajaran-scoped, so there's nothing to copy.
- **Ambiguity check resolved**: siswa already `lulus` or `pindah`/`keluar` before this wizard runs are untouched — the `Siswa::where('kelas_id', $kelasLama->id)->update(...)` queries only ever match siswa still assigned to the old kelas, regardless of their `status` value, which is correct since a graduated/withdrawn siswa's `kelas_id` was already nulled out by an earlier promotion cycle.
- **2026-07-28 pre-flight correction summary**: (1) rewrote the view to current TailAdmin tokens (the plan was drafted before that correction existed); (2) closed 2 cross-lembaga IDOR gaps in `store()` (`kelas_baru_id`/`semester_tujuan_id` now resolved via `Model::find()`+`lembaga_id` cross-check instead of raw `exists:`, matching this project's standing rule, violated/fixed 5 times before this plan was even drafted); (3) wrapped the mapping loop in `DB::transaction()` per Tahap 2's own explicit lesson about this exact future scenario; (4) added a `RoleSeeder` step granting the new permission to `kepala_sekolah`, since 4 prior permissions across Tahap 5/7 shipped without ever being granted to a real role. Type consistency and test-count checks re-run after all edits: 7 tests in `KenaikanKelasControllerTest.php`, file lists updated to include `RoleSeeder.php`/`RoleSeederTest.php`.
