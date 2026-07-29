# Komponen Penilaian Index Filter Rework, Edit & Delete Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rework the Komponen Penilaian (TP) index page's filter to use real Tahun Ajaran/Semester/Mata Pelajaran data (Tom Select, no page reload) instead of options derived from whatever happens to already be loaded, and add edit/delete capability for existing TP entries — currently there is none — with guards protecting data already referenced by `Asesmen`/`NilaiSiswa`.

**Architecture:** Two tasks. Task 1 reworks `index()` into a filterable, AJAX-refreshable page (extracting the list into a `_daftar.blade.php` partial, adding a Tahun Ajaran→Semester cascading `opsi()` endpoint, and a new JS filter module) — this also fixes the underlying bug where the Semester filter's options were derived from already-loaded records instead of the `Semester` table. Task 2 adds `edit()`/`update()`/`destroy()`, building on the partial Task 1 created (adding Edit/Hapus actions to each row), with the mata_pelajaran_id/semester_id fields locked once a TP is referenced by an `Asesmen` or `NilaiSiswa`.

**Tech Stack:** Laravel 12, Blade, Alpine.js, Tom Select, Pest 4.

**Spec:** `docs/superpowers/specs/2026-07-29-komponen-penilaian-index-edit-delete-design.md` — read this for full rationale (why the old Semester filter only ever showed one option, why edit/delete need usage guards).

## Global Constraints

- Out of scope: the Create page (`KomponenPenilaianController::create()`/`store()`, `create.blade.php`) — a separate follow-up package will add Tahun Ajaran context and cascading there. Do not touch `create()`/`store()`/`create.blade.php` in this plan.
- `KomponenPenilaian` is NOT tenant-scoped directly (no `lembaga_id` column, no `BelongsToTenant` trait). Any FK resolution in `edit()`/`update()`/`destroy()` must enforce tenant isolation explicitly by resolving `MataPelajaran::find($komponenPenilaian->mata_pelajaran_id)` + `abort(404)` if `null` — `MataPelajaran` IS tenant-scoped (`BelongsToTenant`), so this transitively blocks cross-lembaga access. This is the same pattern already used for `JadwalPelajaran` via `Kelas::find()`.
- Both new actions (edit/delete) reuse the single existing permission `komponen-penilaian.kelola` — no new permission.
- Delete confirmation must reuse the existing global `confirmDialog` JS helper exactly as used in `resources/views/admin/pola-jam/index.blade.php` — no new confirmation component.
- New JS follows this codebase's established convention: a factory function in `resources/js/<name>.js`, registered via `Alpine.data('<name>', <factory>)` in `resources/js/app.js`, Tom Select instantiated via `new TomSelect(el, {...})` inside an `initXSelect(el)` method called from Blade via `x-init`.
- No-reload filtering follows the exact fetch/toast/URL-sync mechanics already built in `resources/js/jadwal-pelajaran-filter.js` (`Accept`/`X-Requested-With` headers on the fragment fetch, `Alpine.store('toast').push('error', ...)` on failure, `window.history.pushState` to keep the URL in sync).

---

### Task 1: Index filter rework — Tahun Ajaran/Semester/Mata Pelajaran, no reload

**Files:**
- Modify: `app/Http/Controllers/Admin/KomponenPenilaianController.php` (`index()`, new `opsi()`)
- Modify: `resources/views/admin/komponen-penilaian/index.blade.php`
- Create: `resources/views/admin/komponen-penilaian/_daftar.blade.php`
- Create: `resources/js/komponen-penilaian-filter.js`
- Modify: `resources/js/app.js`
- Test: `tests/Feature/Admin/KomponenPenilaianCrudTest.php`

**Interfaces:**
- Consumes: `App\Models\TahunAjaran` (existing, `status_aktif` column, `BelongsToTenant`), `App\Models\Semester::tahunAjaran(): BelongsTo` (existing).
- Produces: route `admin.komponen-penilaian.opsi` (GET, JSON `{semesterList: [{id, nama}]}`), and `admin.komponen-penilaian.index` now returns a plain HTML string (not a full page) when called with header `X-Requested-With: XMLHttpRequest`, filtered by `tahun_ajaran_id`/`semester_id`/`mata_pelajaran_id`/`search` query params. Task 2 depends on `_daftar.blade.php` existing and depends on the `$komponenList` variable it renders with (each item having `mataPelajaran`, `semester`, `semester.tahunAjaran` eager-loaded).

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Admin/KomponenPenilaianCrudTest.php` (at the end of the file):

```php
it('only offers semester options belonging to the selected tahun ajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $taLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $taBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semesterLama = Semester::factory()->create(['tahun_ajaran_id' => $taLama->id]);
    $semesterBaru = Semester::factory()->create(['tahun_ajaran_id' => $taBaru->id]);

    $response = $this->actingAs($manager)->get(route('admin.komponen-penilaian.index', ['tahun_ajaran_id' => $taBaru->id]));

    $response->assertViewHas('semesterList', fn ($list) => $list->contains('id', $semesterBaru->id) && ! $list->contains('id', $semesterLama->id));
});

it('defaults to the active tahun ajaran when none is selected', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $taAktif = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);

    $response = $this->actingAs($manager)->get(route('admin.komponen-penilaian.index'));

    $response->assertViewHas('tahunAjaranId', $taAktif->id);
});

it('filters the komponen list by tahun ajaran, semester, and mata pelajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semesterCocok = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapelCocok = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $mapelLain = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapelCocok->id, 'semester_id' => $semesterCocok->id, 'kode' => 'TP-COCOK']);
    KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapelLain->id, 'semester_id' => $semesterCocok->id, 'kode' => 'TP-MAPEL-LAIN']);
    KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapelCocok->id, 'semester_id' => $semesterLain->id, 'kode' => 'TP-SEMESTER-LAIN']);

    $response = $this->actingAs($manager)->get(route('admin.komponen-penilaian.index', [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'semester_id' => $semesterCocok->id,
        'mata_pelajaran_id' => $mapelCocok->id,
    ]), ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertOk();
    $response->assertSee('TP-COCOK');
    $response->assertDontSee('TP-MAPEL-LAIN');
    $response->assertDontSee('TP-SEMESTER-LAIN');
});

it('filters the komponen list by search text on kode or deskripsi', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'kode' => 'TP 3.1', 'deskripsi' => 'Siklus air']);
    KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'kode' => 'TP 4.2', 'deskripsi' => 'Fotosintesis']);

    $response = $this->actingAs($manager)->get(route('admin.komponen-penilaian.index', ['search' => 'siklus']));

    $response->assertOk();
    $response->assertSee('Siklus air');
    $response->assertDontSee('Fotosintesis');
});

it('shows semester and tahun ajaran together on each row to avoid ambiguity', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2026/2027']);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Ganjil']);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);

    $response = $this->actingAs($manager)->get(route('admin.komponen-penilaian.index'));

    $response->assertSee('Ganjil — 2026/2027');
});

it('returns only the fragment for an ajax request, not the full page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.komponen-penilaian.index'), ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertOk();
    $response->assertSee('Daftar Komponen &amp; Tujuan Pembelajaran', false);
    $response->assertDontSee('komponenPenilaianFilter(', false);
});

it('returns semester options scoped to the given tahun ajaran via the opsi endpoint', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Ganjil']);

    $response = $this->actingAs($manager)->getJson(route('admin.komponen-penilaian.opsi', ['tahun_ajaran_id' => $tahunAjaran->id]));

    $response->assertOk();
    $response->assertJsonFragment(['id' => $semester->id, 'nama' => 'Ganjil']);
});

it('rejects a tahun_ajaran_id belonging to another lembaga on the opsi endpoint', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $manager = actingAsKomponenManager($lembagaA);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);

    $this->actingAs($manager)->getJson(route('admin.komponen-penilaian.opsi', ['tahun_ajaran_id' => $tahunAjaranB->id]))
        ->assertNotFound();
});

it('wires the filter card with komponenPenilaianFilter and the correct initial values', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($manager)->get(route('admin.komponen-penilaian.index', [
        'tahun_ajaran_id' => $tahunAjaran->id, 'semester_id' => $semester->id, 'mata_pelajaran_id' => $mapel->id,
    ]));

    $response->assertSee('komponenPenilaianFilter(', false);
    $response->assertSee((string) $tahunAjaran->id, false);
    $response->assertSee((string) $semester->id, false);
    $response->assertSee((string) $mapel->id, false);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php`
Expected: FAIL — `admin.komponen-penilaian.opsi` route doesn't exist (`RouteNotFoundException`), `assertViewHas('tahunAjaranId', ...)`/`assertViewHas('semesterList', ...)` fail because those view keys don't exist yet, and the ajax/search/filter tests fail because `index()` doesn't support any of these query params yet.

- [ ] **Step 3: Add the opsi route**

In `routes/admin.php`, immediately after the existing line `Route::post('komponen-penilaian', [KomponenPenilaianController::class, 'store'])->name('komponen-penilaian.store');` (currently line 140), add:

```php
    Route::get('komponen-penilaian/opsi', [KomponenPenilaianController::class, 'opsi'])->name('komponen-penilaian.opsi');
```

- [ ] **Step 4: Rewrite `index()` and add `opsi()`**

Replace `app/Http/Controllers/Admin/KomponenPenilaianController.php`'s `index()` method, and add `opsi()` right after it:

```php
    public function index(Request $request): View|string
    {
        $this->authorize('komponen-penilaian.kelola');

        $tahunAjaranId = $request->query('tahun_ajaran_id');
        if ($tahunAjaranId === null) {
            $tahunAjaranId = TahunAjaran::where('status_aktif', true)->value('id');
        }
        $semesterId = $request->query('semester_id');
        $mataPelajaranId = $request->query('mata_pelajaran_id');
        $search = $request->query('search');

        $komponenList = KomponenPenilaian::whereHas('mataPelajaran')
            ->with(['mataPelajaran', 'semester.tahunAjaran'])
            ->when($tahunAjaranId, fn ($q) => $q->whereHas('semester', fn ($q2) => $q2->where('tahun_ajaran_id', $tahunAjaranId)))
            ->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId))
            ->when($mataPelajaranId, fn ($q) => $q->where('mata_pelajaran_id', $mataPelajaranId))
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2->where('kode', 'like', "%{$search}%")->orWhere('deskripsi', 'like', "%{$search}%")))
            ->orderByDesc('id')
            ->get();

        if ($request->ajax()) {
            return view('admin.komponen-penilaian._daftar', ['komponenList' => $komponenList])->render();
        }

        return view('admin.komponen-penilaian.index', [
            'tahunAjaranList' => TahunAjaran::orderByDesc('id')->get(),
            'tahunAjaranId' => $tahunAjaranId,
            'semesterList' => $tahunAjaranId ? Semester::where('tahun_ajaran_id', $tahunAjaranId)->orderByDesc('id')->get() : collect(),
            'mataPelajaranList' => MataPelajaran::orderBy('nama')->get(),
            'semesterId' => $semesterId,
            'mataPelajaranId' => $mataPelajaranId,
            'search' => $search,
            'komponenList' => $komponenList,
        ]);
    }

    public function opsi(Request $request): JsonResponse
    {
        $this->authorize('komponen-penilaian.kelola');

        $data = $request->validate(['tahun_ajaran_id' => ['required', 'integer']]);

        $tahunAjaran = TahunAjaran::find($data['tahun_ajaran_id']);
        abort_if($tahunAjaran === null, 404);

        return response()->json([
            'semesterList' => Semester::where('tahun_ajaran_id', $tahunAjaran->id)->orderByDesc('id')->get(['id', 'nama']),
        ]);
    }
```

Add these two `use` statements at the top of the file, alongside the existing ones (`use App\Models\KomponenPenilaian;` etc.):

```php
use App\Models\TahunAjaran;
use Illuminate\Http\JsonResponse;
```

- [ ] **Step 5: Extract the daftar partial**

Create `resources/views/admin/komponen-penilaian/_daftar.blade.php`:

```blade
<div class="space-y-4">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card transition duration-200 hover:shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Total Tujuan Pembelajaran</p>
                    <p class="mt-1 font-display text-2xl font-bold text-gray-900">{{ $komponenList->count() }}</p>
                </div>
                <div class="rounded-xl bg-brand-50 p-3 text-brand-600">
                    <x-icon name="checklist" class="h-6 w-6" />
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card transition duration-200 hover:shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Mapel Tercover</p>
                    <p class="mt-1 font-display text-2xl font-bold text-gray-900">{{ $komponenList->pluck('mata_pelajaran_id')->unique()->count() }}</p>
                </div>
                <div class="rounded-xl bg-blue-50 p-3 text-blue-600">
                    <x-icon name="menu_book" class="h-6 w-6" />
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card transition duration-200 hover:shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Dengan KKTP Spesifik</p>
                    <p class="mt-1 font-display text-2xl font-bold text-gray-900">{{ $komponenList->filter(fn($k) => !empty($k->kktp))->count() }}</p>
                </div>
                <div class="rounded-xl bg-amber-50 p-3 text-amber-600">
                    <x-icon name="fact_check" class="h-6 w-6" />
                </div>
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
        <div class="flex flex-wrap items-center justify-between border-b border-gray-100 bg-white px-6 py-4 gap-3">
            <p class="font-display text-sm font-bold text-gray-900">Daftar Komponen &amp; Tujuan Pembelajaran</p>
            <div class="flex items-center gap-2">
                <x-badge tone="brand" class="text-xs font-semibold px-2.5 py-0.5">{{ $komponenList->count() }} Data</x-badge>
                <x-link-button href="{{ route('admin.komponen-penilaian.create') }}">
                    <span class="text-base leading-none mr-1.5">+</span> Tambah TP Baru
                </x-link-button>
            </div>
        </div>

        <div class="divide-y divide-gray-100">
            @forelse ($komponenList as $komponen)
                <div class="p-6 transition hover:bg-gray-50/60 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2.5">
                            @if ($komponen->kode)
                                <span class="inline-flex items-center rounded-md border border-brand-500/30 bg-brand-50 px-2.5 py-1 text-xs font-bold text-brand-700">
                                    {{ $komponen->kode }}
                                </span>
                            @endif
                            <span class="font-bold text-gray-900 text-base">{{ $komponen->mataPelajaran->nama }}</span>
                        </div>
                        <x-badge tone="slate" class="text-xs font-medium">{{ $komponen->semester->nama }} — {{ $komponen->semester->tahunAjaran->nama }}</x-badge>
                    </div>

                    <p class="text-sm text-gray-800 leading-relaxed font-medium">
                        {{ $komponen->deskripsi }}
                    </p>

                    @if ($komponen->kktp)
                        <div class="rounded-xl bg-amber-50/60 p-3.5 border border-amber-200/60 text-xs text-amber-900 space-y-1">
                            <p class="font-bold text-amber-800 uppercase tracking-wide text-[10px] flex items-center gap-1">
                                <x-icon name="fact_check" class="h-3.5 w-3.5 text-amber-600" />
                                KKTP (Kriteria Ketercapaian Tujuan Pembelajaran):
                            </p>
                            <p class="text-amber-900 leading-relaxed font-medium pl-4 border-l-2 border-amber-400">{{ $komponen->kktp }}</p>
                        </div>
                    @endif
                </div>
            @empty
                <div class="py-12 text-center text-gray-400 space-y-3">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                        <x-icon name="checklist" class="h-7 w-7" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-700">Belum Ada Tujuan Pembelajaran</p>
                        <p class="text-xs text-gray-400 max-w-sm mx-auto mt-0.5">Tambahkan Tujuan Pembelajaran (TP) untuk mempermudah guru merujuk indikator penilaian saat menginput nilai asesmen.</p>
                    </div>
                    <x-link-button href="{{ route('admin.komponen-penilaian.create') }}" class="inline-flex justify-center">
                        <span class="text-base leading-none mr-1.5">+</span> Tambah TP Pertama
                    </x-link-button>
                </div>
            @endforelse
        </div>
    </div>
</div>
```

- [ ] **Step 6: Rewrite the index view**

Replace the full contents of `resources/views/admin/komponen-penilaian/index.blade.php`:

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
            <h1 class="font-display text-lg font-bold text-gray-900">Komponen Penilaian (TP)</h1>
            <p class="text-sm text-gray-500">
                Akademik <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Komponen Penilaian</b>
            </p>
        </div>

        <div
            class="space-y-4"
            x-data="komponenPenilaianFilter({
                tahunAjaranId: @js($tahunAjaranId),
                semesterId: @js($semesterId),
                mataPelajaranId: @js($mataPelajaranId),
                search: @js($search),
                opsiUrl: @js(route('admin.komponen-penilaian.opsi')),
                indexUrlBase: @js(route('admin.komponen-penilaian.index')),
            })"
        >
            {{-- Filter Card --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card">
                <div class="flex flex-col gap-3 md:flex-row md:items-center">
                    <div class="relative flex-1">
                        <x-icon name="search" class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input
                            type="text"
                            x-model="search"
                            @input.debounce.400ms="muatUlangDaftar()"
                            placeholder="Cari kode TP atau deskripsi..."
                            class="w-full rounded-lg border-gray-200 pl-10 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 py-2"
                        >
                    </div>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <select x-ref="tahunAjaranSelect" x-init="initTahunAjaranSelect($refs.tahunAjaranSelect)" class="rounded-lg border-gray-200 text-sm text-gray-700 shadow-sm py-2">
                            <option value="">Semua Tahun Ajaran</option>
                            @foreach ($tahunAjaranList as $tahunAjaran)
                                <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
                            @endforeach
                        </select>
                        <select x-ref="semesterSelect" x-init="initSemesterSelect($refs.semesterSelect)" class="rounded-lg border-gray-200 text-sm text-gray-700 shadow-sm py-2">
                            <option value="">Semua Semester</option>
                            @foreach ($semesterList as $semester)
                                <option value="{{ $semester->id }}" @selected($semesterId == $semester->id)>{{ $semester->nama }}</option>
                            @endforeach
                        </select>
                        <select x-ref="mataPelajaranSelect" x-init="initMataPelajaranSelect($refs.mataPelajaranSelect)" class="rounded-lg border-gray-200 text-sm text-gray-700 shadow-sm py-2">
                            <option value="">Semua Mata Pelajaran</option>
                            @foreach ($mataPelajaranList as $mapel)
                                <option value="{{ $mapel->id }}" @selected($mataPelajaranId == $mapel->id)>{{ $mapel->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div x-ref="daftarKomponen">
                @include('admin.komponen-penilaian._daftar')
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 7: Create the JS filter module**

Create `resources/js/komponen-penilaian-filter.js`:

```js
import TomSelect from 'tom-select';

export function komponenPenilaianFilter(config) {
    return {
        tahunAjaranId: config.tahunAjaranId ?? '',
        semesterId: config.semesterId ?? '',
        mataPelajaranId: config.mataPelajaranId ?? '',
        search: config.search ?? '',
        opsiUrl: config.opsiUrl,
        indexUrlBase: config.indexUrlBase,
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

            if (!this.tahunAjaranId) {
                this.semesterTomSelect.disable();
            }
        },

        initMataPelajaranSelect(el) {
            new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari mata pelajaran...',
                onChange: (value) => {
                    this.mataPelajaranId = value;
                    this.muatUlangDaftar();
                },
            });
        },

        async gantiTahunAjaran(tahunAjaranId) {
            this.semesterId = '';
            this.semesterTomSelect?.clear(true);
            this.semesterTomSelect?.clearOptions();

            if (!tahunAjaranId) {
                this.semesterTomSelect?.disable();
                await this.muatUlangDaftar();
                return;
            }

            this.semesterTomSelect?.enable();

            try {
                const url = new URL(this.opsiUrl, window.location.origin);
                url.searchParams.set('tahun_ajaran_id', tahunAjaranId);
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', 'Gagal memuat opsi semester.');
                } else {
                    json.semesterList.forEach((semester) => {
                        this.semesterTomSelect.addOption({ value: String(semester.id), text: semester.nama });
                    });
                    this.semesterTomSelect.refreshOptions(false);
                }
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memuat opsi semester.');
            }

            await this.muatUlangDaftar();
        },

        async muatUlangDaftar() {
            try {
                const url = new URL(this.indexUrlBase, window.location.origin);
                if (this.tahunAjaranId) url.searchParams.set('tahun_ajaran_id', this.tahunAjaranId);
                if (this.semesterId) url.searchParams.set('semester_id', this.semesterId);
                if (this.mataPelajaranId) url.searchParams.set('mata_pelajaran_id', this.mataPelajaranId);
                if (this.search) url.searchParams.set('search', this.search);

                const response = await fetch(url, {
                    headers: {
                        Accept: 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    Alpine.store('toast').push('error', 'Gagal memuat daftar komponen penilaian.');
                    return;
                }

                const html = await response.text();
                this.perbaruiUrl();
                this.$refs.daftarKomponen.innerHTML = html;
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memuat daftar komponen penilaian.');
            }
        },

        perbaruiUrl() {
            const url = new URL(window.location.href);
            const params = url.searchParams;
            this.tahunAjaranId ? params.set('tahun_ajaran_id', this.tahunAjaranId) : params.delete('tahun_ajaran_id');
            this.semesterId ? params.set('semester_id', this.semesterId) : params.delete('semester_id');
            this.mataPelajaranId ? params.set('mata_pelajaran_id', this.mataPelajaranId) : params.delete('mata_pelajaran_id');
            this.search ? params.set('search', this.search) : params.delete('search');
            window.history.pushState({}, '', url);
        },
    };
}
```

- [ ] **Step 8: Register the component in app.js**

In `resources/js/app.js`, add the import alongside the other Alpine component imports (right after `import { jadwalPelajaranCreateForm } from './jadwal-pelajaran-create';`):

```js
import { komponenPenilaianFilter } from './komponen-penilaian-filter';
```

And add the registration alongside the other `Alpine.data(...)` calls (right after `Alpine.data('jadwalPelajaranCreateForm', jadwalPelajaranCreateForm);`):

```js
Alpine.data('komponenPenilaianFilter', komponenPenilaianFilter);
```

- [ ] **Step 9: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php`
Expected: PASS — all tests, including the 9 new ones and the 4 pre-existing ones.

- [ ] **Step 10: Build assets**

Run: `npm run build`
Expected: builds successfully with no errors.

- [ ] **Step 11: Run the full test suite**

Run: `php artisan test`
Expected: all tests pass.

- [ ] **Step 12: Commit**

```bash
git add app/Http/Controllers/Admin/KomponenPenilaianController.php routes/admin.php resources/views/admin/komponen-penilaian/index.blade.php resources/views/admin/komponen-penilaian/_daftar.blade.php resources/js/komponen-penilaian-filter.js resources/js/app.js tests/Feature/Admin/KomponenPenilaianCrudTest.php
git commit -m "feat: rework Komponen Penilaian index filter with tahun ajaran, real semester options, and no-reload"
```

---

### Task 2: Edit and delete for existing Komponen Penilaian entries

**Files:**
- Modify: `app/Models/KomponenPenilaian.php` (add `asesmen()`, `nilaiSiswa()` relations)
- Modify: `routes/admin.php` (add edit/update/destroy routes)
- Modify: `app/Http/Controllers/Admin/KomponenPenilaianController.php` (add `edit()`, `update()`, `destroy()`)
- Modify: `resources/views/admin/komponen-penilaian/_daftar.blade.php` (add Edit/Hapus actions per row)
- Create: `resources/views/admin/komponen-penilaian/edit.blade.php`
- Create: `resources/js/komponen-penilaian-edit.js`
- Modify: `resources/js/app.js`
- Test: `tests/Feature/Admin/KomponenPenilaianCrudTest.php`

**Interfaces:**
- Consumes: `App\Models\Asesmen` (existing, `komponenPenilaian(): BelongsToMany` via pivot `asesmen_komponen_penilaian`), `App\Models\NilaiSiswa` (existing, `komponen_penilaian_id` FK), `resources/views/admin/komponen-penilaian/_daftar.blade.php` from Task 1 (the `$komponen` loop variable, each with `mataPelajaran`/`semester`/`semester.tahunAjaran` loaded).
- Produces: routes `admin.komponen-penilaian.edit` (GET), `.update` (PUT), `.destroy` (DELETE), all keyed by `KomponenPenilaian` route-model-binding.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Admin/KomponenPenilaianCrudTest.php` (at the end of the file):

```php
it('shows edit and hapus actions for each komponen in the daftar', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);

    $response = $this->actingAs($manager)->get(route('admin.komponen-penilaian.index'));

    $response->assertSee(route('admin.komponen-penilaian.edit', $komponen), false);
    $response->assertSee('Hapus');
    $response->assertSee('confirmDialog', false);
    $response->assertSee(route('admin.komponen-penilaian.destroy', $komponen), false);
});

it('updates a komponen penilaian including mata pelajaran and semester when not yet used', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semesterLama = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $semesterBaru = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapelLama = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $mapelBaru = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapelLama->id, 'semester_id' => $semesterLama->id, 'kode' => 'TP LAMA']);

    $this->actingAs($manager)->put(route('admin.komponen-penilaian.update', $komponen), [
        'mata_pelajaran_id' => $mapelBaru->id,
        'semester_id' => $semesterBaru->id,
        'kode' => 'TP BARU',
        'deskripsi' => 'Deskripsi baru',
        'kktp' => 'KKTP baru',
    ])->assertRedirect(route('admin.komponen-penilaian.index'));

    $komponen->refresh();
    expect($komponen->mata_pelajaran_id)->toBe($mapelBaru->id);
    expect($komponen->semester_id)->toBe($semesterBaru->id);
    expect($komponen->kode)->toBe('TP BARU');
    expect($komponen->deskripsi)->toBe('Deskripsi baru');
});

it('locks mata pelajaran and semester when the komponen is already used in an asesmen', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $mapelLain = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen = Asesmen::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen->komponenPenilaian()->attach($komponen->id);

    $editResponse = $this->actingAs($manager)->get(route('admin.komponen-penilaian.edit', $komponen));
    $editResponse->assertSee('tidak bisa diubah');

    $this->actingAs($manager)->put(route('admin.komponen-penilaian.update', $komponen), [
        'mata_pelajaran_id' => $mapelLain->id,
        'deskripsi' => 'Coba ganti mapel',
    ])->assertRedirect(route('admin.komponen-penilaian.index'));

    expect($komponen->fresh()->mata_pelajaran_id)->toBe($mapel->id);
    expect($komponen->fresh()->deskripsi)->toBe('Coba ganti mapel');
});

it('locks mata pelajaran and semester when the komponen is already used in a nilai siswa', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);
    NilaiSiswa::factory()->create(['komponen_penilaian_id' => $komponen->id]);

    $this->actingAs($manager)->put(route('admin.komponen-penilaian.update', $komponen), [
        'semester_id' => $semesterLain->id,
        'deskripsi' => 'Coba ganti semester',
    ])->assertRedirect(route('admin.komponen-penilaian.index'));

    expect($komponen->fresh()->semester_id)->toBe($semester->id);
    expect($komponen->fresh()->deskripsi)->toBe('Coba ganti semester');
});

it('rejects updating to a mata pelajaran and semester from different lembaga when not yet used', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $manager = actingAsKomponenManager($lembagaA);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $mapelA = MataPelajaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapelA->id, 'semester_id' => $semesterA->id]);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $semesterB = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id]);

    $this->actingAs($manager)->put(route('admin.komponen-penilaian.update', $komponen), [
        'mata_pelajaran_id' => $mapelA->id,
        'semester_id' => $semesterB->id,
        'deskripsi' => 'Campur lembaga',
    ])->assertNotFound();

    expect($komponen->fresh()->semester_id)->toBe($semesterA->id);
});

it('rejects editing or updating a komponen penilaian belonging to another lembaga', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $manager = actingAsKomponenManager($lembagaA);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $semesterB = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id]);
    $mapelB = MataPelajaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $komponenB = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapelB->id, 'semester_id' => $semesterB->id]);

    $this->actingAs($manager)->get(route('admin.komponen-penilaian.edit', $komponenB))->assertNotFound();
    $this->actingAs($manager)->put(route('admin.komponen-penilaian.update', $komponenB), ['deskripsi' => 'Coba'])->assertNotFound();
});

it('deletes a komponen penilaian that is not used anywhere', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);

    $this->actingAs($manager)->delete(route('admin.komponen-penilaian.destroy', $komponen))
        ->assertRedirect(route('admin.komponen-penilaian.index'))
        ->assertSessionHas('status');

    expect(KomponenPenilaian::find($komponen->id))->toBeNull();
});

it('blocks deleting a komponen penilaian already used in an asesmen', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen = Asesmen::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen->komponenPenilaian()->attach($komponen->id);

    $this->actingAs($manager)->delete(route('admin.komponen-penilaian.destroy', $komponen))
        ->assertSessionHasErrors('komponen_penilaian');

    expect(KomponenPenilaian::find($komponen->id))->not->toBeNull();
});

it('blocks deleting a komponen penilaian already used in a nilai siswa', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);
    NilaiSiswa::factory()->create(['komponen_penilaian_id' => $komponen->id]);

    $this->actingAs($manager)->delete(route('admin.komponen-penilaian.destroy', $komponen))
        ->assertSessionHasErrors('komponen_penilaian');

    expect(KomponenPenilaian::find($komponen->id))->not->toBeNull();
});

it('rejects deleting a komponen penilaian belonging to another lembaga', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $manager = actingAsKomponenManager($lembagaA);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $semesterB = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id]);
    $mapelB = MataPelajaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $komponenB = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapelB->id, 'semester_id' => $semesterB->id]);

    $this->actingAs($manager)->delete(route('admin.komponen-penilaian.destroy', $komponenB))->assertNotFound();

    expect(KomponenPenilaian::find($komponenB->id))->not->toBeNull();
});

it('denies access to edit, update, and destroy without komponen-penilaian.kelola permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);
    $outsider = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($outsider)->get(route('admin.komponen-penilaian.edit', $komponen))->assertForbidden();
    $this->actingAs($outsider)->put(route('admin.komponen-penilaian.update', $komponen), ['deskripsi' => 'x'])->assertForbidden();
    $this->actingAs($outsider)->delete(route('admin.komponen-penilaian.destroy', $komponen))->assertForbidden();
});
```

Add these `use` statements to the top of `tests/Feature/Admin/KomponenPenilaianCrudTest.php` (alongside the existing ones):

```php
use App\Models\Asesmen;
use App\Models\NilaiSiswa;
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php`
Expected: FAIL — the new edit/update/destroy routes don't exist yet (`RouteNotFoundException` on every new test).

- [ ] **Step 3: Add the model relations**

In `app/Models/KomponenPenilaian.php`, add these two methods after the existing `semester()` method, and add the two new `use` imports at the top:

```php
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
```

```php
    public function asesmen(): BelongsToMany
    {
        return $this->belongsToMany(Asesmen::class, 'asesmen_komponen_penilaian', 'komponen_penilaian_id', 'asesmen_id');
    }

    public function nilaiSiswa(): HasMany
    {
        return $this->hasMany(NilaiSiswa::class);
    }
```

- [ ] **Step 4: Add the routes**

In `routes/admin.php`, immediately after the `komponen-penilaian/opsi` route added in Task 1, add:

```php
    Route::get('komponen-penilaian/{komponenPenilaian}/edit', [KomponenPenilaianController::class, 'edit'])->name('komponen-penilaian.edit');
    Route::put('komponen-penilaian/{komponenPenilaian}', [KomponenPenilaianController::class, 'update'])->name('komponen-penilaian.update');
    Route::delete('komponen-penilaian/{komponenPenilaian}', [KomponenPenilaianController::class, 'destroy'])->name('komponen-penilaian.destroy');
```

- [ ] **Step 5: Add the controller methods**

In `app/Http/Controllers/Admin/KomponenPenilaianController.php`, add these three public methods right after `store()`:

```php
    public function edit(KomponenPenilaian $komponenPenilaian): View
    {
        $this->authorize('komponen-penilaian.kelola');

        $mataPelajaran = MataPelajaran::find($komponenPenilaian->mata_pelajaran_id);
        if (! $mataPelajaran) {
            abort(404);
        }

        $dipakai = $komponenPenilaian->asesmen()->exists() || $komponenPenilaian->nilaiSiswa()->exists();

        return view('admin.komponen-penilaian.edit', [
            'komponenPenilaian' => $komponenPenilaian->load(['mataPelajaran', 'semester.tahunAjaran']),
            'dipakai' => $dipakai,
            'mataPelajaranList' => MataPelajaran::orderBy('nama')->get(),
            'semesterList' => Semester::orderByDesc('id')->get(),
        ]);
    }

    public function update(Request $request, KomponenPenilaian $komponenPenilaian): RedirectResponse
    {
        $this->authorize('komponen-penilaian.kelola');

        $mataPelajaranSaatIni = MataPelajaran::find($komponenPenilaian->mata_pelajaran_id);
        if (! $mataPelajaranSaatIni) {
            abort(404);
        }

        $dipakai = $komponenPenilaian->asesmen()->exists() || $komponenPenilaian->nilaiSiswa()->exists();

        $rules = [
            'kode' => ['nullable', 'string', 'max:50'],
            'deskripsi' => ['required', 'string'],
            'kktp' => ['nullable', 'string'],
        ];
        if (! $dipakai) {
            $rules['mata_pelajaran_id'] = ['required', 'integer'];
            $rules['semester_id'] = ['required', 'integer'];
        }

        $data = $request->validate($rules);

        if (! $dipakai) {
            $mataPelajaran = MataPelajaran::find($data['mata_pelajaran_id']);
            $semester = Semester::find($data['semester_id']);
            abort_if($mataPelajaran === null || $semester === null, 404);
            abort_if($mataPelajaran->lembaga_id !== $semester->lembaga_id, 404);

            $komponenPenilaian->mata_pelajaran_id = $data['mata_pelajaran_id'];
            $komponenPenilaian->semester_id = $data['semester_id'];
        }

        $komponenPenilaian->kode = $data['kode'] ?? null;
        $komponenPenilaian->deskripsi = $data['deskripsi'];
        $komponenPenilaian->kktp = $data['kktp'] ?? null;
        $komponenPenilaian->save();

        return redirect()->route('admin.komponen-penilaian.index')->with('status', 'Komponen penilaian (TP) berhasil diperbarui.');
    }

    public function destroy(KomponenPenilaian $komponenPenilaian): RedirectResponse
    {
        $this->authorize('komponen-penilaian.kelola');

        $mataPelajaran = MataPelajaran::find($komponenPenilaian->mata_pelajaran_id);
        if (! $mataPelajaran) {
            abort(404);
        }

        if ($komponenPenilaian->asesmen()->exists() || $komponenPenilaian->nilaiSiswa()->exists()) {
            return back()->withErrors(['komponen_penilaian' => 'Komponen ini sudah dipakai pada asesmen atau nilai siswa — tidak bisa dihapus.']);
        }

        $komponenPenilaian->delete();

        return redirect()->route('admin.komponen-penilaian.index')->with('status', 'Komponen penilaian (TP) berhasil dihapus.');
    }
```

- [ ] **Step 6: Add Edit/Hapus actions to the daftar partial**

In `resources/views/admin/komponen-penilaian/_daftar.blade.php`, replace this block:

```blade
                        <x-badge tone="slate" class="text-xs font-medium">{{ $komponen->semester->nama }} — {{ $komponen->semester->tahunAjaran->nama }}</x-badge>
                    </div>
```

with:

```blade
                        <div class="flex items-center gap-3">
                            <x-badge tone="slate" class="text-xs font-medium">{{ $komponen->semester->nama }} — {{ $komponen->semester->tahunAjaran->nama }}</x-badge>
                            @can('komponen-penilaian.kelola')
                                <a href="{{ route('admin.komponen-penilaian.edit', $komponen) }}" class="text-xs font-semibold text-gray-500 hover:text-gray-900 transition-colors">Edit</a>
                                <form method="POST" action="{{ route('admin.komponen-penilaian.destroy', $komponen) }}" x-data @submit.prevent="confirmDialog('Hapus Komponen Penilaian?', @js('Apakah Anda yakin ingin menghapus TP ' . ($komponen->kode ?: $komponen->deskripsi) . '?'), { confirmLabel: 'Ya, Hapus' }).then(confirmed => { if (confirmed) $el.submit() })">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-error-500 hover:text-error-700 transition-colors">Hapus</button>
                                </form>
                            @endcan
                        </div>
                    </div>
```

- [ ] **Step 7: Create the edit view**

Create `resources/views/admin/komponen-penilaian/edit.blade.php`:

```blade
<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-4">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Edit Komponen Penilaian (TP)</h1>
            <p class="text-sm text-gray-500">
                Akademik <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.komponen-penilaian.index') }}" class="font-semibold text-gray-700 hover:text-brand-600 transition-colors">Komponen Penilaian</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Edit</b>
            </p>
        </div>

        @if ($dipakai)
            <div class="flex items-start gap-3 rounded-2xl border border-warning-200 bg-warning-50 p-4 text-sm text-warning-700">
                <x-icon name="warning" class="mt-0.5 h-5 w-5 shrink-0 text-warning-500" />
                <p>Komponen ini sudah dipakai pada asesmen atau nilai siswa — Mata Pelajaran dan Semester tidak bisa diubah supaya data nilai yang sudah tercatat tetap konsisten.</p>
            </div>
        @endif

        {{-- Form Card --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="border-b border-gray-100 bg-white px-6 py-4">
                <p class="flex items-center gap-2 font-display text-sm font-bold text-gray-900">
                    <x-icon name="checklist" class="h-4 w-4 text-brand-500" />
                    Formulir Tujuan Pembelajaran &amp; KKTP
                </p>
                <p class="mt-0.5 text-xs text-gray-500">Ubah rincian kode, deskripsi TP, dan kriteria ketuntasan.</p>
            </div>

            <form method="POST" action="{{ route('admin.komponen-penilaian.update', $komponenPenilaian) }}" class="p-6 space-y-6" x-data="komponenPenilaianEditForm()">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Mata Pelajaran *" />
                        @if ($dipakai)
                            <p class="mt-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ $komponenPenilaian->mataPelajaran->nama }}</p>
                        @else
                            <select
                                name="mata_pelajaran_id"
                                required
                                x-ref="mataPelajaranSelect"
                                x-init="initMataPelajaranSelect($refs.mataPelajaranSelect)"
                                class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                            >
                                <option value="">— Pilih Mata Pelajaran —</option>
                                @foreach ($mataPelajaranList as $mapel)
                                    <option value="{{ $mapel->id }}" @selected(old('mata_pelajaran_id', $komponenPenilaian->mata_pelajaran_id) == $mapel->id)>{{ $mapel->nama }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('mata_pelajaran_id')" class="mt-1" />
                        @endif
                    </div>

                    <div>
                        <x-input-label value="Semester *" />
                        @if ($dipakai)
                            <p class="mt-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ $komponenPenilaian->semester->nama }} — {{ $komponenPenilaian->semester->tahunAjaran->nama }}</p>
                        @else
                            <select
                                name="semester_id"
                                required
                                x-ref="semesterSelect"
                                x-init="initSemesterSelect($refs.semesterSelect)"
                                class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                            >
                                <option value="">— Pilih Semester —</option>
                                @foreach ($semesterList as $semester)
                                    <option value="{{ $semester->id }}" @selected(old('semester_id', $komponenPenilaian->semester_id) == $semester->id)>{{ $semester->nama }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('semester_id')" class="mt-1" />
                        @endif
                    </div>
                </div>

                <div>
                    <x-input-label value="Kode Tujuan Pembelajaran (Opsional)" />
                    <input
                        type="text"
                        name="kode"
                        value="{{ old('kode', $komponenPenilaian->kode) }}"
                        placeholder="Contoh: TP 3.1 atau TP 4.2"
                        class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                    >
                    <x-input-error :messages="$errors->get('kode')" class="mt-1" />
                </div>

                <div>
                    <x-input-label value="Deskripsi Tujuan Pembelajaran *" />
                    <textarea
                        name="deskripsi"
                        rows="3"
                        required
                        class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500 p-3"
                    >{{ old('deskripsi', $komponenPenilaian->deskripsi) }}</textarea>
                    <x-input-error :messages="$errors->get('deskripsi')" class="mt-1" />
                </div>

                <div>
                    <x-input-label value="KKTP / Kriteria Ketercapaian (Opsional)" />
                    <textarea
                        name="kktp"
                        rows="3"
                        class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500 p-3"
                    >{{ old('kktp', $komponenPenilaian->kktp) }}</textarea>
                    <x-input-error :messages="$errors->get('kktp')" class="mt-1" />
                </div>

                <div class="flex items-center gap-3 pt-4 border-t border-gray-100">
                    <x-primary-button type="submit">
                        Simpan Perubahan
                    </x-primary-button>
                    <a href="{{ route('admin.komponen-penilaian.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                        Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 8: Create the edit JS module**

Create `resources/js/komponen-penilaian-edit.js`:

```js
import TomSelect from 'tom-select';

export function komponenPenilaianEditForm() {
    return {
        initMataPelajaranSelect(el) {
            new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari mata pelajaran...',
            });
        },

        initSemesterSelect(el) {
            new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari semester...',
            });
        },
    };
}
```

- [ ] **Step 9: Register the component in app.js**

In `resources/js/app.js`, add the import alongside the other Alpine component imports (right after `import { komponenPenilaianFilter } from './komponen-penilaian-filter';`):

```js
import { komponenPenilaianEditForm } from './komponen-penilaian-edit';
```

And add the registration alongside the other `Alpine.data(...)` calls (right after `Alpine.data('komponenPenilaianFilter', komponenPenilaianFilter);`):

```js
Alpine.data('komponenPenilaianEditForm', komponenPenilaianEditForm);
```

- [ ] **Step 10: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php`
Expected: PASS — all tests, including the 11 new ones from this task.

- [ ] **Step 11: Build assets**

Run: `npm run build`
Expected: builds successfully with no errors.

- [ ] **Step 12: Run the full test suite**

Run: `php artisan test`
Expected: all tests pass.

- [ ] **Step 13: Commit**

```bash
git add app/Models/KomponenPenilaian.php routes/admin.php app/Http/Controllers/Admin/KomponenPenilaianController.php resources/views/admin/komponen-penilaian/_daftar.blade.php resources/views/admin/komponen-penilaian/edit.blade.php resources/js/komponen-penilaian-edit.js resources/js/app.js tests/Feature/Admin/KomponenPenilaianCrudTest.php
git commit -m "feat: add edit and delete for Komponen Penilaian with usage guards against asesmen and nilai siswa"
```

---

## Plan Self-Review Notes

- **Spec coverage**: all 11 requirements map to concrete steps — Tahun Ajaran filter + real Semester options fixing the `->unique()` dedup bug (Task 1 Steps 4-7), Tom Select everywhere (Task 1 Step 7, Task 2 Step 8), no-reload AJAX (Task 1 Steps 4-7), Semester+Tahun Ajaran shown per row (Task 1 Step 5), Edit/Hapus actions with `confirmDialog` (Task 2 Steps 5-6), usage guards on both edit (field locking) and delete (Task 2 Step 5).
- **Type consistency**: `$dipakai` is computed identically in `edit()`, `update()`, and implicitly checked again in `destroy()` — same two-relation check (`asesmen()->exists() || nilaiSiswa()->exists()`) every time, no drift between the three methods.
- **No placeholders**: every code block is complete and literal, including the full before/after diff for `_daftar.blade.php` in Task 2.
- **Task boundary**: Task 2 depends on `_daftar.blade.php` existing from Task 1 (adds action buttons to it) — this is a real, intentional sequential dependency, not an accidental coupling; Task 1 alone is a complete, shippable improvement to the index page even before edit/delete land.
