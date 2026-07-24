# Tahap 6 — Duplikasi Awal Tahun Ajaran (Kenaikan Kelas) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Kenaikan Kelas wizard — admin maps each kelas from a previous tahun ajaran to either a target kelas in the new tahun ajaran (moving its siswa along) or "Lulus" (graduating its siswa), with an option to copy that kelas's `jadwal_pelajaran` structure into the new kelas for a chosen semester.

**Architecture:** A single-slice wizard controller. **Design reconciliation up front** (see Global Constraints) — the original design spec's Section 5 describes 4 duplication steps ("salin Pola Jam", "salin Mata Pelajaran", Kenaikan Kelas, "salin Jadwal"), but Tahap 1 and Tahap 4 built `PolaJam` and `MataPelajaran` as **lembaga-scoped, not tahun-ajaran-scoped** — they already persist across years without any copy step. Only two real actions remain: Kenaikan Kelas (this plan's core) and copying `JadwalPelajaran` rows (folded into the same form as an option).

**Tech Stack:** Laravel 12, Blade, Pest 4.

## Global Constraints

- **Spec deviation, documented**: `PolaJam` (Tahap 4 Task 2) and `MataPelajaran` (Tahap 1 Task 2) have no `tahun_ajaran_id` column — they are reusable lembaga-level templates, not per-year data. The design spec's "Langkah 1 — Salin Pola Jam" and "Langkah 2 — Salin Mata Pelajaran" are therefore **no-ops in this implementation**: nothing to build, because there is nothing to copy — both already carry forward automatically. This plan implements only the two steps that involve real per-year data: Kenaikan Kelas (kelas + siswa) and, optionally, Jadwal Pelajaran.
- Same conventions as Tahap 1-5 (`casts()` method style, inline validation, `AuthorizesRequests`, Blade tokens, `permissions:sync`).
- Moving a `Siswa` to a new `Kelas` is a plain `update()` — it does **not** create a new `Siswa` row. Graduating sets `status = lulus` and `kelas_id = null` on the same row, preserving all history (NIS, `sumber_data`, `calon_murid_id`, etc.).
- The target `Kelas` for promotion must already exist (created via the normal Kelas CRUD from Tahap 1) before this wizard runs — this plan does not auto-create kelas rows, to avoid silently creating typo'd class names.

---

### Task 1: Kenaikan Kelas wizard (mapping + siswa move/graduate + optional jadwal copy)

**Files:**
- Create: `app/Http/Controllers/Admin/KenaikanKelasController.php`
- Create: `resources/views/admin/kenaikan-kelas/index.blade.php`
- Modify: `routes/admin.php`
- Modify: `resources/views/layouts/sidebar.blade.php`
- Test: `tests/Feature/Admin/KenaikanKelasControllerTest.php`

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
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
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
            'semesterList' => \App\Models\Semester::orderByDesc('id')->get(),
            'tahunAjaranId' => $tahunAjaranId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('kenaikan-kelas.kelola');

        $data = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.*.tindakan' => ['required', 'in:naik,lulus'],
            'mapping.*.kelas_baru_id' => ['required_if:mapping.*.tindakan,naik', 'nullable', 'exists:kelas,id'],
            'mapping.*.salin_jadwal' => ['nullable', 'boolean'],
            'mapping.*.semester_tujuan_id' => ['nullable', 'exists:semester,id'],
        ]);

        foreach ($data['mapping'] as $kelasLamaId => $aksi) {
            $kelasLama = Kelas::findOrFail($kelasLamaId);

            if ($aksi['tindakan'] === 'lulus') {
                Siswa::where('kelas_id', $kelasLama->id)->update([
                    'status' => StatusSiswa::Lulus->value,
                    'kelas_id' => null,
                ]);

                continue;
            }

            $kelasBaruId = $aksi['kelas_baru_id'];

            Siswa::where('kelas_id', $kelasLama->id)->update(['kelas_id' => $kelasBaruId]);

            if (($aksi['salin_jadwal'] ?? false) && ! empty($aksi['semester_tujuan_id'])) {
                $this->salinJadwal($kelasLama->id, $kelasBaruId, $aksi['semester_tujuan_id']);
            }
        }

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
    <x-slot name="header">
        <h2 class="font-display text-2xl font-semibold text-ink">Kenaikan Kelas</h2>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-6">
        <x-panel>
            <form method="GET" action="{{ route('admin.kenaikan-kelas.index') }}" class="flex flex-wrap items-end gap-2 p-6">
                <div>
                    <label class="text-sm font-medium text-ink">Tahun Ajaran Sumber (kelas lama)</label>
                    <select name="tahun_ajaran_id" class="mt-1 rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        <option value="">— Pilih —</option>
                        @foreach ($tahunAjaranList as $tahunAjaran)
                            <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-xl bg-ink px-3 py-2 text-sm font-medium text-paper transition hover:bg-ink/90">Tampilkan</button>
            </form>
        </x-panel>

        @if ($kelasLamaList->isNotEmpty())
            <x-panel>
                <form method="POST" action="{{ route('admin.kenaikan-kelas.store') }}" class="p-6">
                    @csrf

                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-ink/10 text-left text-xs uppercase tracking-wide text-ink/60">
                                <th class="py-2 pr-2">Kelas Lama</th>
                                <th class="py-2 pr-2">Jml Siswa</th>
                                <th class="py-2 pr-2">Tindakan</th>
                                <th class="py-2 pr-2">Kelas Tujuan</th>
                                <th class="py-2 pr-2">Salin Jadwal ke Semester</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($kelasLamaList as $kelasLama)
                                <tr class="border-b border-ink/10">
                                    <td class="py-2 pr-2 text-ink">{{ $kelasLama->nama }}</td>
                                    <td class="py-2 pr-2 text-ink/70">{{ $kelasLama->siswa_count }}</td>
                                    <td class="py-2 pr-2">
                                        <select name="mapping[{{ $kelasLama->id }}][tindakan]" class="rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                                            <option value="naik">Naik Kelas</option>
                                            <option value="lulus">Lulus</option>
                                        </select>
                                    </td>
                                    <td class="py-2 pr-2">
                                        <select name="mapping[{{ $kelasLama->id }}][kelas_baru_id]" class="rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                                            <option value="">—</option>
                                            @foreach ($kelasTujuanList as $kelasBaru)
                                                <option value="{{ $kelasBaru->id }}">{{ $kelasBaru->nama }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="py-2 pr-2">
                                        <label class="flex items-center gap-1">
                                            <input type="checkbox" name="mapping[{{ $kelasLama->id }}][salin_jadwal]" value="1">
                                            <select name="mapping[{{ $kelasLama->id }}][semester_tujuan_id]" class="rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
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

                    <button type="submit" class="mt-4 rounded-xl bg-ink px-4 py-2 text-sm font-medium text-paper transition hover:bg-ink/90">Proses Kenaikan Kelas</button>
                </form>
            </x-panel>
        @endif
    </div>
</x-app-layout>
```

- [ ] **Step 6: Add sidebar entry**

In `resources/views/layouts/sidebar.blade.php`, inside the `'III. Akademik'` group, add after `jadwal-pelajaran.kelola`:

```php
Auth::user()->can('kenaikan-kelas.kelola') ? ['route' => 'admin.kenaikan-kelas.index', 'pattern' => 'admin.kenaikan-kelas.*', 'label' => 'Kenaikan Kelas', 'icon' => 'trending_up'] : null,
```

- [ ] **Step 7: Sync permissions**

Run: `php artisan permissions:sync`
Expected: Output includes `Created permission: kenaikan-kelas.kelola`.

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test tests/Feature/Admin/KenaikanKelasControllerTest.php`
Expected: PASS (5 tests)

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Admin/KenaikanKelasController.php resources/views/admin/kenaikan-kelas routes/admin.php resources/views/layouts/sidebar.blade.php tests/Feature/Admin/KenaikanKelasControllerTest.php
git commit -m "feat: add Kenaikan Kelas wizard with optional jadwal copy"
```

---

## Plan Self-Review Notes

- **Spec coverage**: Implements spec Section 5's actionable core (Kenaikan Kelas + Jadwal copy). Section 5's "Langkah 1/2" (salin Pola Jam / salin Mata Pelajaran) are explicitly not implemented — documented in Global Constraints as a deliberate deviation, since Tahap 1/4 made both lembaga-scoped rather than tahun-ajaran-scoped, so there's nothing to copy.
- **Ambiguity check resolved**: siswa already `lulus` or `pindah`/`keluar` before this wizard runs are untouched — the `Siswa::where('kelas_id', $kelasLama->id)->update(...)` queries only ever match siswa still assigned to the old kelas, regardless of their `status` value, which is correct since a graduated/withdrawn siswa's `kelas_id` was already nulled out by an earlier promotion cycle.
