# Rekap Rapor Cascading Filter, Cetak PDF & Ambang Nilai Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the Rekap Rapor page's filter so Kelas and Semester are always scoped to a selected Tahun Ajaran (closing the same year-ambiguity bug already fixed elsewhere), replace the broken `window.print()` button with a real PDF export via `dompdf`, and move the hardcoded "Tuntas (≥75)" threshold into config.

**Architecture:** Two tasks. Task 1 reworks `RaporController::index()` into a filterable, AJAX-refreshable page — extracting the recap/matrix into a `_hasil.blade.php` partial, adding a Tahun Ajaran→(Kelas, Semester) fan-out `opsi()` endpoint (both Kelas and Semester depend directly on Tahun Ajaran, not on each other, so this is a 1-level fan-out, not a 3-level chain), a new JS filter module, and a shared `hitungRekap()` helper that computes the score matrix once for reuse by both the web fragment and the PDF. Task 2 adds `RaporController::cetak()`, which reuses `hitungRekap()` to render a plain (non-Tailwind) Blade view through `barryvdh/laravel-dompdf`, replacing the `window.print()` button with a real link.

**Tech Stack:** Laravel 12, Blade, Alpine.js, Tom Select, `barryvdh/laravel-dompdf` (already installed and already used by `BuktiPendaftaranController`/`SkPpdbController`/`CekStatusController`), Pest 4.

**Spec:** `docs/superpowers/specs/2026-07-29-rapor-cascading-filter-cetak-pdf-design.md` — read this for full rationale.

## Global Constraints

- `Kelas`, `Semester`, and `TahunAjaran` all use the `BelongsToTenant` trait — `Model::find($id)` returns `null` for an ID belonging to another tenant. Every new/changed action that resolves one of these from a request must do `abort_if($model === null, 404)` — the same pattern already used in `KomponenPenilaianController::opsi()`. This is the recurring cross-tenant IDOR bug pattern in this codebase; do not skip it anywhere in this plan.
- No database schema changes — the score threshold becomes a config value (`config('akademik.ambang_tuntas')`), not a new column. `KomponenPenilaian.kktp` stays free text, untouched.
- New JS follows this codebase's established convention: a factory function in `resources/js/<name>.js`, registered via `Alpine.data('<name>', <factory>)` in `resources/js/app.js`, Tom Select instantiated via `new TomSelect(el, {...})` inside an `initXSelect(el)` method called from Blade via `x-init`.
- No-reload filtering follows the exact fetch/toast/URL-sync mechanics already built in `resources/js/komponen-penilaian-filter.js` (`Accept`/`X-Requested-With` headers on the fragment fetch, `Alpine.store('toast').push('error', ...)` on failure, `window.history.pushState` to keep the URL in sync).
- The existing permission `rapor.view` is reused for both new actions (`opsi`, `cetak`) — no new permission.

---

### Task 1: Cascading filter (Tahun Ajaran → Kelas & Semester) with AJAX no-reload matrix

**Files:**
- Modify: `app/Http/Controllers/Admin/RaporController.php` (rewrite `index()`, add `opsi()`, add private `hitungRekap()`)
- Modify: `resources/views/admin/rapor/index.blade.php`
- Create: `resources/views/admin/rapor/_hasil.blade.php`
- Create: `resources/js/rapor-filter.js`
- Modify: `resources/js/app.js`
- Create: `config/akademik.php`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/RaporControllerTest.php`

**Interfaces:**
- Consumes: `App\Models\TahunAjaran` (existing, `status_aktif` column, `BelongsToTenant`), `App\Models\Kelas::tahun_ajaran_id` (existing column), `App\Models\Semester::tahun_ajaran_id` (existing column).
- Produces: route `admin.rapor.opsi` (GET, JSON `{kelasList: [{id, nama}], semesterList: [{id, nama}]}`); `admin.rapor.index` returns a plain HTML string (not a full page) when called with header `X-Requested-With: XMLHttpRequest`, accepting `tahun_ajaran_id`/`kelas_id`/`semester_id` query params; private method `hitungRekap(?Kelas $kelas, ?Semester $semester): array` returning `['siswaList' => Collection, 'mapelList' => Collection, 'rekapNilai' => array, 'classAvg' => ?float, 'highestScore' => ?float]` — Task 2 depends on this exact signature and return shape.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Admin/RaporControllerTest.php` (at the end of the file):

```php
it('defaults to the active tahun ajaran, first kelas, and latest semester when none is selected', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $taAktif = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taAktif->id, 'nama' => '7A']);
    $kelasB = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taAktif->id, 'nama' => '7B']);
    $semesterLama = Semester::factory()->create(['tahun_ajaran_id' => $taAktif->id]);
    $semesterBaru = Semester::factory()->create(['tahun_ajaran_id' => $taAktif->id]);

    $viewer = actingAsRaporViewer($lembaga);

    $response = $this->actingAs($viewer)->get(route('admin.rapor.index'));

    $response->assertViewHas('tahunAjaranId', $taAktif->id);
    $response->assertViewHas('selectedKelas', fn ($kelas) => $kelas->id === $kelasA->id);
    $response->assertViewHas('selectedSemester', fn ($semester) => $semester->id === $semesterBaru->id);
});

it('only offers kelas and semester options belonging to the selected tahun ajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $taLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $taBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taLama->id]);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taBaru->id]);
    $semesterLama = Semester::factory()->create(['tahun_ajaran_id' => $taLama->id]);
    $semesterBaru = Semester::factory()->create(['tahun_ajaran_id' => $taBaru->id]);

    $viewer = actingAsRaporViewer($lembaga);

    $response = $this->actingAs($viewer)->get(route('admin.rapor.index', ['tahun_ajaran_id' => $taBaru->id]));

    $response->assertViewHas('kelasList', fn ($list) => $list->contains('id', $kelasBaru->id) && ! $list->contains('id', $kelasLama->id));
    $response->assertViewHas('semesterList', fn ($list) => $list->contains('id', $semesterBaru->id) && ! $list->contains('id', $semesterLama->id));
});

it('ignores a kelas_id or semester_id that does not belong to the selected tahun ajaran and falls back to defaults', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $taLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $taBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taLama->id]);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taBaru->id]);
    $semesterBaru = Semester::factory()->create(['tahun_ajaran_id' => $taBaru->id]);

    $viewer = actingAsRaporViewer($lembaga);

    // kelas_id belongs to $taLama but tahun_ajaran_id in the request is $taBaru — mismatch must be ignored.
    $response = $this->actingAs($viewer)->get(route('admin.rapor.index', [
        'tahun_ajaran_id' => $taBaru->id,
        'kelas_id' => $kelasLama->id,
    ]));

    $response->assertViewHas('selectedKelas', fn ($kelas) => $kelas->id === $kelasBaru->id);
    $response->assertViewHas('selectedSemester', fn ($semester) => $semester->id === $semesterBaru->id);
});

it('returns only the fragment for an ajax request, not the full page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $viewer = actingAsRaporViewer($lembaga);

    $response = $this->actingAs($viewer)->get(route('admin.rapor.index'), ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertOk();
    $response->assertDontSee('raporFilter(', false);
});

it('returns kelas and semester options scoped to the given tahun ajaran via the opsi endpoint', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '9C']);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Genap']);

    $viewer = actingAsRaporViewer($lembaga);

    $response = $this->actingAs($viewer)->getJson(route('admin.rapor.opsi', ['tahun_ajaran_id' => $tahunAjaran->id]));

    $response->assertOk();
    $response->assertJsonFragment(['id' => $kelas->id, 'nama' => '9C']);
    $response->assertJsonFragment(['id' => $semester->id, 'nama' => 'Genap']);
});

it('rejects a tahun_ajaran_id belonging to another lembaga on the opsi endpoint', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $viewer = actingAsRaporViewer($lembagaA);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);

    $this->actingAs($viewer)->getJson(route('admin.rapor.opsi', ['tahun_ajaran_id' => $tahunAjaranB->id]))
        ->assertNotFound();
});

it('uses the configured ambang tuntas threshold in the legend', function () {
    config(['akademik.ambang_tuntas' => 80]);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    $viewer = actingAsRaporViewer($lembaga);

    $response = $this->actingAs($viewer)->get(route('admin.rapor.index', ['tahun_ajaran_id' => $tahunAjaran->id, 'kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $response->assertSee('Tuntas (&ge; 80)', false);
    $response->assertSee('Perlu Bimbingan (&lt; 80)', false);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/RaporControllerTest.php`
Expected: FAIL — `route('admin.rapor.opsi')` doesn't exist yet, `assertViewHas('kelasList', ...)` fails because `kelasList` isn't scoped by tahun ajaran, `raporFilter(` isn't in the markup, and the ambang text is hardcoded to 75.

- [ ] **Step 3: Add `config/akademik.php`**

```php
<?php

return [
    'ambang_tuntas' => 75,
];
```

- [ ] **Step 4: Add routes**

In `routes/admin.php`, replace:

```php
    Route::get('rapor', [RaporController::class, 'index'])->name('rapor.index');
```

with:

```php
    Route::get('rapor', [RaporController::class, 'index'])->name('rapor.index');
    Route::get('rapor/opsi', [RaporController::class, 'opsi'])->name('rapor.opsi');
```

- [ ] **Step 5: Rewrite `RaporController`**

Replace the full contents of `app/Http/Controllers/Admin/RaporController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\Asesmen;
use App\Models\Kelas;
use App\Models\NilaiSiswa;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class RaporController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View|string
    {
        $this->authorize('rapor.view');

        $tahunAjaranId = $request->query('tahun_ajaran_id');
        if (! $tahunAjaranId && $request->query('kelas_id')) {
            // Deep link with kelas_id but no tahun_ajaran_id (e.g. a bookmarked/shared URL):
            // derive it from the kelas itself instead of falling back to the active tahun
            // ajaran, which may not be the one the kelas actually belongs to.
            $tahunAjaranId = Kelas::find($request->query('kelas_id'))?->tahun_ajaran_id;
        }
        if (! $tahunAjaranId) {
            $tahunAjaranId = TahunAjaran::where('status_aktif', true)->value('id');
        }

        $kelasList = $tahunAjaranId ? Kelas::where('tahun_ajaran_id', $tahunAjaranId)->orderBy('nama')->get() : collect();
        $semesterList = $tahunAjaranId ? Semester::where('tahun_ajaran_id', $tahunAjaranId)->orderByDesc('id')->get() : collect();

        $kelasId = $request->query('kelas_id');
        if (! $kelasId || ! $kelasList->contains('id', (int) $kelasId)) {
            $kelasId = $kelasList->first()?->id;
        }
        $semesterId = $request->query('semester_id');
        if (! $semesterId || ! $semesterList->contains('id', (int) $semesterId)) {
            $semesterId = $semesterList->first()?->id;
        }

        $selectedKelas = $kelasId ? Kelas::find($kelasId) : null;
        $selectedSemester = $semesterId ? Semester::find($semesterId) : null;

        $rekap = $this->hitungRekap($selectedKelas, $selectedSemester);

        if ($request->ajax()) {
            return view('admin.rapor._hasil', array_merge([
                'selectedKelas' => $selectedKelas,
                'selectedSemester' => $selectedSemester,
            ], $rekap))->render();
        }

        return view('admin.rapor.index', array_merge([
            'tahunAjaranList' => TahunAjaran::orderByDesc('id')->get(),
            'tahunAjaranId' => $tahunAjaranId,
            'kelasList' => $kelasList,
            'semesterList' => $semesterList,
            'selectedKelas' => $selectedKelas,
            'selectedSemester' => $selectedSemester,
        ], $rekap));
    }

    public function opsi(Request $request): JsonResponse
    {
        $this->authorize('rapor.view');

        $data = $request->validate(['tahun_ajaran_id' => ['required', 'integer']]);

        $tahunAjaran = TahunAjaran::find($data['tahun_ajaran_id']);
        abort_if($tahunAjaran === null, 404);

        return response()->json([
            'kelasList' => Kelas::where('tahun_ajaran_id', $tahunAjaran->id)->orderBy('nama')->get(['id', 'nama']),
            'semesterList' => Semester::where('tahun_ajaran_id', $tahunAjaran->id)->orderByDesc('id')->get(['id', 'nama']),
        ]);
    }

    private function hitungRekap(?Kelas $kelas, ?Semester $semester): array
    {
        if (! $kelas || ! $semester) {
            return [
                'siswaList' => collect(),
                'mapelList' => collect(),
                'rekapNilai' => [],
                'classAvg' => null,
                'highestScore' => null,
            ];
        }

        $siswaList = Siswa::where('kelas_id', $kelas->id)->orderBy('nama_lengkap')->get();

        $asesmenList = Asesmen::where('kelas_id', $kelas->id)
            ->where('semester_id', $semester->id)
            ->with('mataPelajaran')
            ->get();

        $mapelList = $asesmenList->pluck('mataPelajaran')->unique('id')->sortBy('nama');
        $allNilai = NilaiSiswa::whereIn('asesmen_id', $asesmenList->pluck('id'))->get();

        $rekapNilai = [];
        foreach ($siswaList as $siswa) {
            $rekapNilai[$siswa->id] = [];
            foreach ($mapelList as $mapel) {
                $mapelAsesmenIds = $asesmenList->where('mata_pelajaran_id', $mapel->id)->pluck('id');
                $scores = $allNilai->whereIn('asesmen_id', $mapelAsesmenIds)
                    ->where('siswa_id', $siswa->id)
                    ->whereNotNull('nilai_angka')
                    ->pluck('nilai_angka');

                $rekapNilai[$siswa->id][$mapel->id] = $scores->count() > 0 ? round($scores->avg(), 1) : null;
            }
        }

        $allScores = collect($rekapNilai)->flatMap(fn ($m) => collect($m)->filter(fn ($v) => $v !== null));

        return [
            'siswaList' => $siswaList,
            'mapelList' => $mapelList,
            'rekapNilai' => $rekapNilai,
            'classAvg' => $allScores->count() > 0 ? round($allScores->avg(), 1) : null,
            'highestScore' => $allScores->count() > 0 ? $allScores->max() : null,
        ];
    }
}
```

- [ ] **Step 6: Create `resources/views/admin/rapor/_hasil.blade.php`**

```blade
<div class="space-y-4">
    @if ($selectedKelas && $selectedSemester)
        <!-- Class Stat Summary -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card transition duration-200 hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Total Peserta Didik</p>
                        <p class="mt-1 font-display text-2xl font-bold text-gray-900">{{ $siswaList->count() }} <span class="text-xs font-normal text-gray-400">Siswa</span></p>
                    </div>
                    <div class="rounded-xl bg-brand-50 p-3 text-brand-600">
                        <x-icon name="group" class="h-6 w-6" />
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card transition duration-200 hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Rata-Rata Kelas</p>
                        <p class="mt-1 font-display text-2xl font-bold text-gray-900">{{ $classAvg ?? '—' }}</p>
                    </div>
                    <div class="rounded-xl bg-emerald-50 p-3 text-emerald-600">
                        <x-icon name="analytics" class="h-6 w-6" />
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card transition duration-200 hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Skor Tertinggi</p>
                        <p class="mt-1 font-display text-2xl font-bold text-gray-900">{{ $highestScore ?? '—' }}</p>
                    </div>
                    <div class="rounded-xl bg-amber-50 p-3 text-amber-600">
                        <x-icon name="workspace_premium" class="h-6 w-6" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Matrix Table Card -->
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="flex flex-wrap items-center justify-between border-b border-gray-100 bg-white px-6 py-4 gap-3">
                <div>
                    <p class="font-display text-sm font-bold text-gray-900">Matriks Rata-Rata Nilai Asesmen Per Mapel</p>
                    <p class="text-xs text-gray-500">Nilai dihitung dari rata-rata seluruh asesmen sumatif yang dilaksanakan.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <!-- Legend -->
                    <div class="flex items-center gap-3 text-xs font-medium">
                        <span class="flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Tuntas (&ge; {{ config('akademik.ambang_tuntas') }})
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span> Perlu Bimbingan (&lt; {{ config('akademik.ambang_tuntas') }})
                        </span>
                    </div>
                    @if ($siswaList->isNotEmpty())
                        <x-link-button variant="ghost" href="{{ route('admin.rapor.cetak', ['kelas_id' => $selectedKelas->id, 'semester_id' => $selectedSemester->id]) }}" target="_blank">
                            <x-icon name="print" class="h-4 w-4 mr-1.5 text-gray-500" />
                            Cetak Rekap Nilai
                        </x-link-button>
                    @endif
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm min-w-[600px]">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-100 text-xs font-bold uppercase tracking-wider text-gray-600">
                            <th class="py-3 pl-6 pr-3 w-12 text-center">No</th>
                            <th class="px-4 py-3 min-w-[220px]">Nama Peserta Didik</th>
                            @forelse ($mapelList as $mapel)
                                <th class="px-3 py-3 text-center min-w-[120px]">
                                    <span class="block text-gray-900 font-extrabold">{{ $mapel->nama }}</span>
                                </th>
                            @empty
                                <th class="px-4 py-3 text-center text-gray-400 font-medium">Belum Ada Mapel Terasesmen</th>
                            @endforelse
                            <th class="px-6 py-3 text-center font-extrabold text-brand-700 w-32 bg-brand-50/50">Rata-Rata Umum</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($siswaList as $index => $siswa)
                            @php
                                $studentScores = collect($rekapNilai[$siswa->id] ?? [])->filter(fn ($v) => $v !== null);
                                $generalAvg = $studentScores->count() > 0 ? round($studentScores->avg(), 1) : null;
                            @endphp
                            <tr class="transition hover:bg-gray-50/60">
                                <td class="py-4 pl-6 pr-3 text-center font-semibold text-gray-500">
                                    {{ $index + 1 }}
                                </td>
                                <td class="px-4 py-4">
                                    <div class="font-bold text-gray-900 text-base">{{ $siswa->nama_lengkap }}</div>
                                    <div class="text-xs text-gray-400">{{ $siswa->nis ?: ($siswa->nisn ?: 'Tanpa NIS') }}</div>
                                </td>
                                @forelse ($mapelList as $mapel)
                                    @php
                                        $skor = $rekapNilai[$siswa->id][$mapel->id] ?? null;
                                    @endphp
                                    <td class="px-3 py-4 text-center font-extrabold text-base">
                                        @if ($skor !== null)
                                            <span class="inline-block rounded-lg px-2.5 py-1 {{ $skor >= config('akademik.ambang_tuntas') ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                                                {{ $skor }}
                                            </span>
                                        @else
                                            <span class="text-gray-300 font-normal text-xs">—</span>
                                        @endif
                                    </td>
                                @empty
                                    <td class="px-4 py-4 text-center text-gray-300 text-xs">—</td>
                                @endforelse
                                <td class="px-6 py-4 text-center font-black text-lg text-brand-700 bg-brand-50/20">
                                    @if ($generalAvg !== null)
                                        <span class="inline-block rounded-xl px-3 py-1 bg-brand-50 text-brand-800 border border-brand-200">
                                            {{ $generalAvg }}
                                        </span>
                                    @else
                                        <span class="text-gray-300 font-normal text-xs">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 3 + $mapelList->count() }}" class="py-12 text-center text-gray-400">
                                    Belum ada siswa terdaftar di kelas ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="rounded-2xl border border-dashed border-gray-300 p-12 text-center text-gray-400 space-y-3 bg-white">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                <x-icon name="assessment" class="h-7 w-7" />
            </div>
            <div>
                <p class="text-base font-semibold text-gray-700">Silakan Pilih Kelas dan Semester</p>
                <p class="text-xs text-gray-400 max-w-sm mx-auto mt-0.5">Pilih parameter kelas di bagian atas untuk menampilkan rekapitulasi nilai rapor peserta didik.</p>
            </div>
        </div>
    @endif
</div>
```

- [ ] **Step 7: Rewrite `resources/views/admin/rapor/index.blade.php`**

```blade
<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-4">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Rekapitulasi Nilai Rapor</h1>
            <p class="text-sm text-gray-500">
                Akademik <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Rekap Rapor</b>
            </p>
        </div>

        <div
            class="space-y-4"
            x-data="raporFilter({
                tahunAjaranId: @js($tahunAjaranId),
                kelasId: @js($selectedKelas?->id),
                semesterId: @js($selectedSemester?->id),
                opsiUrl: @js(route('admin.rapor.opsi')),
                indexUrlBase: @js(route('admin.rapor.index')),
            })"
        >
            <!-- Filter Controls Card -->
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="flex-1 min-w-[220px]">
                        <x-input-label value="Tahun Ajaran" />
                        <select x-ref="tahunAjaranSelect" x-init="initTahunAjaranSelect($refs.tahunAjaranSelect)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-bold text-gray-900 transition focus:border-brand-500 focus:ring-brand-500">
                            @foreach ($tahunAjaranList as $tahunAjaran)
                                <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex-1 min-w-[220px]">
                        <x-input-label value="Pilih Kelas" />
                        <select x-ref="kelasSelect" x-init="initKelasSelect($refs.kelasSelect)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-bold text-gray-900 transition focus:border-brand-500 focus:ring-brand-500">
                            @foreach ($kelasList as $kelas)
                                <option value="{{ $kelas->id }}" @selected($selectedKelas && $selectedKelas->id === $kelas->id)>{{ $kelas->nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex-1 min-w-[220px]">
                        <x-input-label value="Pilih Semester" />
                        <select x-ref="semesterSelect" x-init="initSemesterSelect($refs.semesterSelect)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-bold text-gray-900 transition focus:border-brand-500 focus:ring-brand-500">
                            @foreach ($semesterList as $semester)
                                <option value="{{ $semester->id }}" @selected($selectedSemester && $selectedSemester->id === $semester->id)>{{ $semester->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div x-ref="hasilRapor">
                @include('admin.rapor._hasil')
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 8: Create `resources/js/rapor-filter.js`**

```js
import TomSelect from 'tom-select';

export function raporFilter(config) {
    return {
        tahunAjaranId: config.tahunAjaranId ?? '',
        kelasId: config.kelasId ?? '',
        semesterId: config.semesterId ?? '',
        opsiUrl: config.opsiUrl,
        indexUrlBase: config.indexUrlBase,
        kelasTomSelect: null,
        semesterTomSelect: null,

        initTahunAjaranSelect(el) {
            new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari tahun ajaran...',
                onChange: (value) => {
                    this.tahunAjaranId = value;
                    this.gantiTahunAjaran(value);
                },
            });
        },

        initKelasSelect(el) {
            this.kelasTomSelect = new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari kelas...',
                onChange: (value) => {
                    this.kelasId = value;
                    this.muatUlangDaftar();
                },
            });
        },

        initSemesterSelect(el) {
            this.semesterTomSelect = new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari semester...',
                onChange: (value) => {
                    this.semesterId = value;
                    this.muatUlangDaftar();
                },
            });
        },

        async gantiTahunAjaran(tahunAjaranId) {
            this.kelasTomSelect?.clear(true);
            this.kelasTomSelect?.clearOptions();
            this.semesterTomSelect?.clear(true);
            this.semesterTomSelect?.clearOptions();
            this.kelasId = '';
            this.semesterId = '';

            if (!tahunAjaranId) {
                await this.muatUlangDaftar();
                return;
            }

            try {
                const url = new URL(this.opsiUrl, window.location.origin);
                url.searchParams.set('tahun_ajaran_id', tahunAjaranId);
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', 'Gagal memuat opsi kelas dan semester.');
                } else {
                    json.kelasList.forEach((kelas) => {
                        this.kelasTomSelect.addOption({ value: String(kelas.id), text: kelas.nama });
                    });
                    this.kelasTomSelect.refreshOptions(false);
                    if (json.kelasList.length > 0) {
                        this.kelasId = String(json.kelasList[0].id);
                        this.kelasTomSelect.setValue(this.kelasId, true);
                    }

                    json.semesterList.forEach((semester) => {
                        this.semesterTomSelect.addOption({ value: String(semester.id), text: semester.nama });
                    });
                    this.semesterTomSelect.refreshOptions(false);
                    if (json.semesterList.length > 0) {
                        this.semesterId = String(json.semesterList[0].id);
                        this.semesterTomSelect.setValue(this.semesterId, true);
                    }
                }
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memuat opsi kelas dan semester.');
            }

            await this.muatUlangDaftar();
        },

        async muatUlangDaftar() {
            try {
                const url = new URL(this.indexUrlBase, window.location.origin);
                url.searchParams.set('tahun_ajaran_id', this.tahunAjaranId ?? '');
                if (this.kelasId) url.searchParams.set('kelas_id', this.kelasId);
                if (this.semesterId) url.searchParams.set('semester_id', this.semesterId);

                const response = await fetch(url, {
                    headers: {
                        Accept: 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    Alpine.store('toast').push('error', 'Gagal memuat rekap nilai.');
                    return;
                }

                const html = await response.text();
                this.perbaruiUrl();
                this.$refs.hasilRapor.innerHTML = html;
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memuat rekap nilai.');
            }
        },

        perbaruiUrl() {
            const url = new URL(window.location.href);
            const params = url.searchParams;
            params.set('tahun_ajaran_id', this.tahunAjaranId ?? '');
            this.kelasId ? params.set('kelas_id', this.kelasId) : params.delete('kelas_id');
            this.semesterId ? params.set('semester_id', this.semesterId) : params.delete('semester_id');
            window.history.pushState({}, '', url);
        },
    };
}
```

- [ ] **Step 9: Register the component in `resources/js/app.js`**

Add the import alongside the other Alpine component imports (right after `import { komponenPenilaianCreateForm } from './komponen-penilaian-create';`):

```js
import { raporFilter } from './rapor-filter';
```

Add the registration alongside the other `Alpine.data(...)` calls (right after `Alpine.data('komponenPenilaianCreateForm', komponenPenilaianCreateForm);`):

```js
Alpine.data('raporFilter', raporFilter);
```

- [ ] **Step 10: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/RaporControllerTest.php`
Expected: PASS — all tests, including the 6 new ones. (Task 2's `admin.rapor.cetak` route does not exist yet, so this task's tests must not reference it — they don't.)

- [ ] **Step 11: Build assets**

Run: `npm run build`
Expected: builds successfully with no errors.

- [ ] **Step 12: Commit**

```bash
git add app/Http/Controllers/Admin/RaporController.php resources/views/admin/rapor/index.blade.php resources/views/admin/rapor/_hasil.blade.php resources/js/rapor-filter.js resources/js/app.js config/akademik.php routes/admin.php tests/Feature/Admin/RaporControllerTest.php
git commit -m "feat: cascade Rekap Rapor filter through tahun ajaran and move ambang tuntas to config"
```

---

### Task 2: Cetak Rekap Nilai via dompdf

**Files:**
- Modify: `app/Http/Controllers/Admin/RaporController.php` (add `cetak()`)
- Create: `resources/views/pdf/rekap-rapor.blade.php`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/RaporControllerTest.php`

**Interfaces:**
- Consumes: `RaporController::hitungRekap()` (from Task 1, private — called internally by `cetak()`), `Barryvdh\DomPDF\Facade\Pdf::loadView(string $view, array $data)` (existing package, already used by `BuktiPendaftaranController`).
- Produces: route `admin.rapor.cetak` (GET, `kelas_id` + `semester_id` required, streams a `application/pdf` response).

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Admin/RaporControllerTest.php` (at the end of the file):

```php
it('streams a pdf for the selected kelas and semester via the cetak endpoint', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'nama_lengkap' => 'Budi Santoso']);

    $viewer = actingAsRaporViewer($lembaga);

    $response = $this->actingAs($viewer)->get(route('admin.rapor.cetak', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

it('rejects a kelas_id belonging to another lembaga on the cetak endpoint', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $viewer = actingAsRaporViewer($lembagaA);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $kelasB = Kelas::factory()->create(['lembaga_id' => $lembagaB->id, 'tahun_ajaran_id' => $tahunAjaranB->id]);

    $this->actingAs($viewer)->get(route('admin.rapor.cetak', ['kelas_id' => $kelasB->id, 'semester_id' => $semesterA->id]))
        ->assertNotFound();
});

it('rejects a semester_id belonging to another lembaga on the cetak endpoint', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $viewer = actingAsRaporViewer($lembagaA);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunAjaranA->id]);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $semesterB = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id]);

    $this->actingAs($viewer)->get(route('admin.rapor.cetak', ['kelas_id' => $kelasA->id, 'semester_id' => $semesterB->id]))
        ->assertNotFound();
});

it('shows the cetak rekap nilai link pointing at the cetak route when there are students', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    $viewer = actingAsRaporViewer($lembaga);

    $response = $this->actingAs($viewer)->get(route('admin.rapor.index', ['tahun_ajaran_id' => $tahunAjaran->id, 'kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $response->assertSee(route('admin.rapor.cetak', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]), false);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/RaporControllerTest.php`
Expected: FAIL — `route('admin.rapor.cetak')` doesn't exist yet.

- [ ] **Step 3: Add the route**

In `routes/admin.php`, replace:

```php
    Route::get('rapor/opsi', [RaporController::class, 'opsi'])->name('rapor.opsi');
```

with:

```php
    Route::get('rapor/opsi', [RaporController::class, 'opsi'])->name('rapor.opsi');
    Route::get('rapor/cetak', [RaporController::class, 'cetak'])->name('rapor.cetak');
```

- [ ] **Step 4: Add `cetak()` to `RaporController`**

Add the `use` import right after `use App\Models\TahunAjaran;`:

```php
use Barryvdh\DomPDF\Facade\Pdf;
```

Add the `use` import right after `use Illuminate\Http\Request;`:

```php
use Illuminate\Http\Response;
```

Add the method right after `opsi()` (before the private `hitungRekap()` method):

```php
    public function cetak(Request $request): Response
    {
        $this->authorize('rapor.view');

        $data = $request->validate([
            'kelas_id' => ['required', 'integer'],
            'semester_id' => ['required', 'integer'],
        ]);

        $selectedKelas = Kelas::find($data['kelas_id']);
        abort_if($selectedKelas === null, 404);
        $selectedSemester = Semester::find($data['semester_id']);
        abort_if($selectedSemester === null, 404);

        $rekap = $this->hitungRekap($selectedKelas, $selectedSemester);

        $pdf = Pdf::loadView('pdf.rekap-rapor', array_merge([
            'selectedKelas' => $selectedKelas,
            'selectedSemester' => $selectedSemester,
        ], $rekap));

        return $pdf->stream('rekap-rapor-'.$selectedKelas->nama.'.pdf');
    }
```

- [ ] **Step 5: Create `resources/views/pdf/rekap-rapor.blade.php`**

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #111827; }
        h1 { font-size: 15px; margin-bottom: 2px; }
        p.subtitle { color: #5B6478; margin-top: 0; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #E5E7EB; padding: 5px 6px; text-align: center; }
        th { background-color: #F3F4F6; font-size: 10px; text-transform: uppercase; }
        td.nama { text-align: left; font-weight: bold; }
        td.tuntas { background-color: #ECFDF5; color: #047857; font-weight: bold; }
        td.bimbingan { background-color: #FFFBEB; color: #B45309; font-weight: bold; }
        td.umum { background-color: #EFF6FF; color: #1D4ED8; font-weight: bold; }
        p.legend { margin-top: 10px; font-size: 10px; color: #5B6478; }
    </style>
</head>
<body>
    <h1>Rekap Nilai Rapor — {{ $selectedKelas->nama }}</h1>
    <p class="subtitle">{{ $selectedSemester->nama }} — {{ $selectedSemester->tahunAjaran->nama }} &middot; Dicetak {{ now()->translatedFormat('d F Y H:i') }}</p>

    <table>
        <thead>
            <tr>
                <th style="width: 30px;">No</th>
                <th style="width: 140px; text-align: left;">Nama Peserta Didik</th>
                @forelse ($mapelList as $mapel)
                    <th>{{ $mapel->nama }}</th>
                @empty
                    <th>Belum Ada Mapel Terasesmen</th>
                @endforelse
                <th style="width: 70px;">Rata-Rata Umum</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($siswaList as $index => $siswa)
                @php
                    $studentScores = collect($rekapNilai[$siswa->id] ?? [])->filter(fn ($v) => $v !== null);
                    $generalAvg = $studentScores->count() > 0 ? round($studentScores->avg(), 1) : null;
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="nama">{{ $siswa->nama_lengkap }}</td>
                    @forelse ($mapelList as $mapel)
                        @php $skor = $rekapNilai[$siswa->id][$mapel->id] ?? null; @endphp
                        <td class="{{ $skor === null ? '' : ($skor >= config('akademik.ambang_tuntas') ? 'tuntas' : 'bimbingan') }}">
                            {{ $skor ?? '—' }}
                        </td>
                    @empty
                        <td>—</td>
                    @endforelse
                    <td class="umum">{{ $generalAvg ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 3 + $mapelList->count() }}">Belum ada siswa terdaftar di kelas ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="legend">Tuntas: skor &ge; {{ config('akademik.ambang_tuntas') }} &nbsp;&nbsp; Perlu Bimbingan: skor &lt; {{ config('akademik.ambang_tuntas') }}</p>
</body>
</html>
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/RaporControllerTest.php`
Expected: PASS — all tests, including the 4 new ones from this task.

- [ ] **Step 7: Run the full test suite**

Run: `php artisan test`
Expected: all tests pass.

- [ ] **Step 8: Build assets**

Run: `npm run build`
Expected: builds successfully with no errors.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Admin/RaporController.php resources/views/pdf/rekap-rapor.blade.php routes/admin.php tests/Feature/Admin/RaporControllerTest.php
git commit -m "feat: export Rekap Rapor to PDF via dompdf"
```

---

## Plan Self-Review Notes

- **Spec coverage**: requirement #1-2 (cascading TA→Kelas&Semester, auto-refresh) → Task 1 Steps 5-8; #3 (no full reload on Kelas/Semester change) → Task 1 Step 8 (`muatUlangDaftar()` on every select's `onChange`); #4 (Tom Select everywhere) → Task 1 Step 7-8; #5 (dompdf export, separate view) → Task 2; #6 (ambang → config) → Task 1 Steps 3, 6; #7 (tenant-safe `opsi`/`cetak`) → Task 1 Step 5 (`opsi`), Task 2 Step 4 (`cetak`), both tested; #8 (mismatched kelas_id/semester_id falls back to defaults) → Task 1 Step 5 (`index()`), tested in Task 1 Step 1's third test.
- **No placeholders**: every code block is complete and literal; PDF view and `_hasil.blade.php` are full rewrites of the existing markup, not diffs against something the implementer has to reconstruct.
- **Type/signature consistency**: `hitungRekap(?Kelas $kelas, ?Semester $semester): array` is defined once in Task 1 Step 5 and consumed identically (same keys: `siswaList`, `mapelList`, `rekapNilai`, `classAvg`, `highestScore`) by both `index()`/`_hasil.blade.php` (Task 1) and `cetak()`/`pdf/rekap-rapor.blade.php` (Task 2) — no divergent shapes.
- **Scope**: only `RaporController`, `admin/rapor/*` views, `pdf/rekap-rapor.blade.php`, `rapor-filter.js`, `config/akademik.php`, and the 3 `rapor.*` routes. No other controller, model, or migration touched.
- **Regression check**: the pre-existing test `it displays the rapor recap page for selected class and semester` (already in `RaporControllerTest.php`) passes `kelas_id`/`semester_id` without `tahun_ajaran_id`, and its `TahunAjaran` is not marked `status_aktif`. Task 1 Step 5's `index()` derives `tahunAjaranId` from the given `kelas_id` in that case (instead of only falling back to the active TA), so this deep-link scenario keeps working — verified by re-running the full `RaporControllerTest.php` file at the end of both tasks.
