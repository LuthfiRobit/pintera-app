# Jadwal Pelajaran Filter UX Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rework the Jadwal Pelajaran index page's filter into a no-reload interaction: Tahun Ajaran → Semester → Kelas order, Tom Select on Tahun Ajaran/Kelas, no submit button, hari list restricted to the lembaga's active days, and a clear message when the filter is incomplete instead of a silent blank state.

**Architecture:** Two tasks. Task 1 is pure backend/Blade — extracts the schedule-list markup into a partial reused by both the full page and a new AJAX-fragment response path, adds the hari-aktif computation, and adds a small JSON endpoint for refreshing Kelas/Semester options when Tahun Ajaran changes. Task 2 is pure frontend — a new Alpine/Tom Select JS module wired into the already-restructured filter card, calling the endpoints Task 1 built. Task 1 must land first since Task 2 depends on its routes/response shapes.

**Tech Stack:** Laravel 12, Blade, Alpine.js, Tom Select (already a dependency in this codebase), Pest 4.

**Spec:** `docs/superpowers/specs/2026-07-28-jadwal-pelajaran-filter-ux-design.md` — read this for the full rationale (why HTML-fragment-swap over client-side templating, why Tahun Ajaran/Kelas get Tom Select but Semester doesn't).

## Global Constraints

- Do not touch the Create page, or `JadwalPelajaranController::create()`/`store()` — this plan is index-page-only.
- Do not add edit/delete capability for existing jadwal entries — explicitly out of scope (deferred separately).
- Any FK resolution added must follow this project's standing rule: resolve via `Model::find($id)` + `abort_if(null, 404)` for tenant-scoped models, never raw `exists:table,column`.
- New JS follows this codebase's established convention exactly: a factory function in `resources/js/<name>.js`, registered via `Alpine.data('<name>', <factory>)` in `resources/js/app.js`, Tom Select instantiated via `new TomSelect(el, {...})` inside an `initXSelect(el)` method called from Blade via `x-init`. Fetch calls use `Accept`/`X-CSRF-TOKEN` headers and `Alpine.store('toast').push(...)` for error feedback, matching `resources/js/jenis-tagihan-table.js`.
- No new npm dependency — Tom Select is already installed and themed (`resources/css/app.css`).

---

### Task 1: Backend — hari-aktif partial, AJAX fragment response, opsi endpoint

**Files:**
- Create: `resources/views/admin/jadwal-pelajaran/_daftar.blade.php`
- Modify: `resources/views/admin/jadwal-pelajaran/index.blade.php` (only the "2. Daftar Jadwal Pelajaran per Hari" section — the filter card is Task 2's job)
- Modify: `app/Http/Controllers/Admin/JadwalPelajaranController.php`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/JadwalPelajaranCrudTest.php`

**Interfaces:**
- Consumes: `App\Enums\Hari::aktifDari(array $hariLiburMingguan): array` (existing, already used by Pola Jam's index), `App\Models\Kelas::lembaga(): BelongsTo` (existing).
- Produces: route `admin.jadwal-pelajaran.opsi` (GET, JSON `{kelasList: [{id,nama}], semesterList: [{id,nama}]}`), and `admin.jadwal-pelajaran.index` now returns a plain HTML string (not a full page) when called with header `X-Requested-With: XMLHttpRequest`. Task 2's JS consumes both of these by exact URL/shape.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Admin/JadwalPelajaranCrudTest.php` (append at the end of the file):

```php
it('returns only the schedule fragment for an AJAX request, not the full page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index', [
        'tahun_ajaran_id' => $tahunAjaran->id, 'kelas_id' => $kelas->id, 'semester_id' => $semester->id,
    ]), ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertOk();
    $response->assertSee('Daftar Jadwal Pelajaran');
    $response->assertDontSee('Filter Jadwal Pelajaran');
});

it('shows an explanatory message instead of a silent empty state when the filter is incomplete', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);

    $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index'))
        ->assertSee('Lengkapi Filter Terlebih Dahulu');
});

it('only computes hari aktif from the selected kelas\' lembaga, excluding weekly libur days', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0, 6]]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index', [
        'tahun_ajaran_id' => $tahunAjaran->id, 'kelas_id' => $kelas->id, 'semester_id' => $semester->id,
    ]));

    $response->assertViewHas('hariAktif', function ($hariAktif) {
        $values = collect($hariAktif)->map(fn ($h) => $h->value);

        return ! $values->contains('sabtu') && ! $values->contains('minggu') && $values->contains('senin');
    });
});

it('returns kelas and semester options scoped to the given tahun ajaran via the opsi endpoint', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '6A']);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Ganjil']);

    $response = $this->actingAs($manager)->getJson(route('admin.jadwal-pelajaran.opsi', ['tahun_ajaran_id' => $tahunAjaran->id]));

    $response->assertOk();
    $response->assertJsonFragment(['id' => $kelas->id, 'nama' => '6A']);
    $response->assertJsonFragment(['id' => $semester->id, 'nama' => 'Ganjil']);
});

it('rejects a tahun_ajaran_id belonging to another lembaga on the opsi endpoint', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $manager = actingAsJadwalManager($lembagaA);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);

    $this->actingAs($manager)->getJson(route('admin.jadwal-pelajaran.opsi', ['tahun_ajaran_id' => $tahunAjaranB->id]))
        ->assertNotFound();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php`
Expected: FAIL — the new route `admin.jadwal-pelajaran.opsi` doesn't exist yet (`RouteNotFoundException`), and the AJAX/hari-aktif/empty-message behaviors don't exist in `index()` yet.

- [ ] **Step 3: Create the `_daftar` partial**

Create `resources/views/admin/jadwal-pelajaran/_daftar.blade.php` with exactly the content currently inside `index.blade.php`'s `@if ($kelasId && $semesterId) ... @endif` block, but looping over `$hariAktif` instead of `\App\Enums\Hari::cases()`, and with an `@else` branch for the incomplete-filter case:

```blade
@if ($kelasId && $semesterId)
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 bg-white px-6 py-4">
            <div>
                <h2 class="font-display text-base font-bold text-gray-900">Daftar Jadwal Pelajaran</h2>
                <p class="text-xs text-gray-500 mt-0.5">Jadwal kegiatan belajar mengajar mingguan untuk kelas dan semester yang terpilih.</p>
            </div>
            <span class="inline-flex items-center rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-600 ring-1 ring-inset ring-brand-500/20">
                Total {{ $jadwalList->count() }} Sesi
            </span>
        </div>

        @if ($jadwalList->isEmpty())
            <div class="px-6 py-16 text-center">
                <x-icon name="event_busy" class="mx-auto h-10 w-10 text-gray-300 mb-2" />
                <p class="text-sm font-semibold text-gray-700">Belum Ada Jadwal Pelajaran</p>
                <p class="text-xs text-gray-500 mt-1">Belum ada slot waktu dan mata pelajaran yang diatur untuk kelas dan semester ini.</p>
            </div>
        @else
            <div class="divide-y divide-gray-100 bg-white">
                @foreach ($hariAktif as $hari)
                    @php $jadwalHariIni = $jadwalList->where('jamPelajaran.hari', $hari)->sortBy('jamPelajaran.urutan'); @endphp
                    @if ($jadwalHariIni->isNotEmpty())
                        <div>
                            {{-- Section Hari --}}
                            <div class="flex items-center justify-between bg-gray-50/75 px-6 py-3 border-y border-gray-100 mt-[-1px]">
                                <div class="flex items-center gap-2">
                                    <x-icon name="calendar_today" class="h-4 w-4 text-brand-500" />
                                    <span class="text-[12px] font-bold uppercase tracking-wider text-gray-700">{{ $hari->label() }}</span>
                                </div>
                                <span class="text-xs font-medium text-gray-500">{{ $jadwalHariIni->count() }} sesi</span>
                            </div>

                            {{-- Daftar Slot per Hari --}}
                            <ul class="divide-y divide-gray-100">
                                @foreach ($jadwalHariIni as $jadwal)
                                    <li class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 px-6 py-4 transition-colors duration-150 hover:bg-gray-50/60">
                                        <div class="flex flex-wrap items-center gap-3 md:gap-4">
                                            {{-- Badge Waktu (Format tanpa detik: H:i) --}}
                                            <div class="flex items-center gap-2 font-mono text-xs">
                                                <span class="rounded bg-brand-50 px-2.5 py-1 font-bold text-brand-600 ring-1 ring-inset ring-brand-500/20">
                                                    {{ substr($jadwal->jamPelajaran->jam_mulai, 0, 5) }}
                                                </span>
                                                <span class="text-gray-400 font-medium">&rarr;</span>
                                                <span class="rounded bg-gray-100 px-2.5 py-1 font-semibold text-gray-700 ring-1 ring-inset ring-gray-300/60">
                                                    {{ substr($jadwal->jamPelajaran->jam_selesai, 0, 5) }}
                                                </span>
                                            </div>

                                            <span class="hidden md:inline text-gray-300">&bull;</span>

                                            {{-- Badge Label Slot (Jam ke-1, Istirahat, dll) --}}
                                            <span class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 ring-1 ring-inset ring-gray-200/60">
                                                {{ $jadwal->jamPelajaran->label }}
                                            </span>

                                            <span class="hidden md:inline text-gray-300">&bull;</span>

                                            {{-- Mata Pelajaran & Guru --}}
                                            <div class="flex flex-wrap items-center gap-2 md:gap-3">
                                                <span class="text-sm font-bold text-gray-900">
                                                    {{ $jadwal->mataPelajaran?->nama ?? '(tanpa mapel)' }}
                                                </span>
                                                <span class="inline-flex items-center gap-1.5 text-xs text-gray-600 sm:border-l sm:border-gray-200 sm:pl-3">
                                                    <x-icon name="person" class="h-3.5 w-3.5 text-gray-400" />
                                                    <span>Guru: <strong class="font-semibold text-gray-800">{{ $jadwal->guru->nama }}</strong></span>
                                                </span>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
@else
    <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center">
        <x-icon name="filter_alt" class="mx-auto h-10 w-10 text-gray-300 mb-2" />
        <p class="text-sm font-semibold text-gray-700">Lengkapi Filter Terlebih Dahulu</p>
        <p class="text-xs text-gray-500 mt-1">Pilih Tahun Ajaran, Semester, dan Kelas untuk menampilkan jadwal pelajaran.</p>
    </div>
@endif
```

- [ ] **Step 4: Update `index.blade.php`'s daftar section**

In `resources/views/admin/jadwal-pelajaran/index.blade.php`, replace the entire `{{-- 2. Daftar Jadwal Pelajaran per Hari --}}` block (currently the `@if ($kelasId && $semesterId) ... @endif` spanning to just before `</div></x-app-layout>`) with:

```blade
        {{-- 2. Daftar Jadwal Pelajaran per Hari --}}
        <div x-ref="daftarJadwal">
            @include('admin.jadwal-pelajaran._daftar')
        </div>
```

(`@include` shares the parent view's variable scope automatically — `$jadwalList`, `$hariAktif`, `$kelasId`, `$semesterId` are all already in scope from the controller, no explicit `compact()` needed here. The `x-ref="daftarJadwal"` wrapper is unused until Task 2 wires up the JS that targets it — harmless to add now.)

- [ ] **Step 5: Update the controller**

The current `index()` method (verified against the real file) is:

```php
    public function index(Request $request): View
    {
        $this->authorize('jadwal-pelajaran.kelola');

        $tahunAjaranId = $request->query('tahun_ajaran_id');
        if (! $tahunAjaranId) {
            $tahunAjaranId = TahunAjaran::where('status_aktif', true)->value('id');
        }

        $kelasId = $request->query('kelas_id');
        $semesterId = $request->query('semester_id');

        return view('admin.jadwal-pelajaran.index', [
            'tahunAjaranList' => TahunAjaran::orderByDesc('id')->get(),
            'tahunAjaranId' => $tahunAjaranId,
            'kelasList' => $tahunAjaranId ? Kelas::where('tahun_ajaran_id', $tahunAjaranId)->orderBy('nama')->get() : collect(),
            'semesterList' => $tahunAjaranId ? Semester::where('tahun_ajaran_id', $tahunAjaranId)->orderByDesc('id')->get() : collect(),
            'jadwalList' => $kelasId && $semesterId
                ? JadwalPelajaran::with(['jamPelajaran', 'mataPelajaran', 'guru'])
                    ->where('kelas_id', $kelasId)->where('semester_id', $semesterId)->get()
                : collect(),
            'kelasId' => $kelasId,
            'semesterId' => $semesterId,
        ]);
    }
```

Replace it with:

```php
    public function index(Request $request): View|string
    {
        $this->authorize('jadwal-pelajaran.kelola');

        $tahunAjaranId = $request->query('tahun_ajaran_id');
        if (! $tahunAjaranId) {
            $tahunAjaranId = TahunAjaran::where('status_aktif', true)->value('id');
        }

        $kelasId = $request->query('kelas_id');
        $semesterId = $request->query('semester_id');

        $kelas = $kelasId ? Kelas::with('lembaga')->find($kelasId) : null;
        $hariAktif = $kelas
            ? Hari::aktifDari($kelas->lembaga->hari_libur_mingguan ?? [])
            : Hari::cases();

        $jadwalList = $kelasId && $semesterId
            ? JadwalPelajaran::with(['jamPelajaran', 'mataPelajaran', 'guru'])
                ->where('kelas_id', $kelasId)->where('semester_id', $semesterId)->get()
            : collect();

        if ($request->ajax()) {
            return view('admin.jadwal-pelajaran._daftar', [
                'jadwalList' => $jadwalList,
                'hariAktif' => $hariAktif,
                'kelasId' => $kelasId,
                'semesterId' => $semesterId,
            ])->render();
        }

        return view('admin.jadwal-pelajaran.index', [
            'tahunAjaranList' => TahunAjaran::orderByDesc('id')->get(),
            'tahunAjaranId' => $tahunAjaranId,
            'kelasList' => $tahunAjaranId ? Kelas::where('tahun_ajaran_id', $tahunAjaranId)->orderBy('nama')->get() : collect(),
            'semesterList' => $tahunAjaranId ? Semester::where('tahun_ajaran_id', $tahunAjaranId)->orderByDesc('id')->get() : collect(),
            'jadwalList' => $jadwalList,
            'hariAktif' => $hariAktif,
            'kelasId' => $kelasId,
            'semesterId' => $semesterId,
        ]);
    }

    public function opsi(Request $request): JsonResponse
    {
        $this->authorize('jadwal-pelajaran.kelola');

        $data = $request->validate([
            'tahun_ajaran_id' => ['required', 'integer'],
        ]);

        $tahunAjaran = TahunAjaran::find($data['tahun_ajaran_id']);
        abort_if($tahunAjaran === null, 404);

        return response()->json([
            'kelasList' => Kelas::where('tahun_ajaran_id', $tahunAjaran->id)->orderBy('nama')->get(['id', 'nama']),
            'semesterList' => Semester::where('tahun_ajaran_id', $tahunAjaran->id)->orderByDesc('id')->get(['id', 'nama']),
        ]);
    }
```

Add two imports to the top of the file (both currently missing — verified against the real file's `use` block, which has `Guru`, `JadwalPelajaran`, `JamPelajaran`, `Kelas`, `MataPelajaran`, `Semester`, `TahunAjaran`, `AuthorizesRequests`, `RedirectResponse`, `Request`, `BaseController`, `View`):

```php
use App\Enums\Hari;
use Illuminate\Http\JsonResponse;
```

(Place them alphabetically alongside the existing `use` statements — `Hari` before `Guru` at the top of the `App\...` group, `JsonResponse` alongside `RedirectResponse`/`Request` in the `Illuminate\...` group.)

- [ ] **Step 6: Add the route**

In `routes/admin.php`, add right after the existing `jadwal-pelajaran.index` route registration:

```php
    Route::get('jadwal-pelajaran/opsi', [JadwalPelajaranController::class, 'opsi'])->name('jadwal-pelajaran.opsi');
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php`
Expected: PASS (all tests, including the 5 new ones)

- [ ] **Step 8: Run the full suite**

Run: `php artisan test`
Expected: all tests pass.

- [ ] **Step 9: Commit**

```bash
git add resources/views/admin/jadwal-pelajaran/_daftar.blade.php resources/views/admin/jadwal-pelajaran/index.blade.php app/Http/Controllers/Admin/JadwalPelajaranController.php routes/admin.php tests/Feature/Admin/JadwalPelajaranCrudTest.php
git commit -m "feat: add hari-aktif filtering, AJAX fragment response, and options endpoint for Jadwal Pelajaran"
```

---

### Task 2: Frontend — Tom Select + no-reload filter interaction

**Files:**
- Create: `resources/js/jadwal-pelajaran-filter.js`
- Modify: `resources/js/app.js`
- Modify: `resources/views/admin/jadwal-pelajaran/index.blade.php` (only the "1. Card Filter" section — Task 1 already handled the daftar section)
- Test: `tests/Feature/Admin/JadwalPelajaranCrudTest.php`

**Interfaces:**
- Consumes: `admin.jadwal-pelajaran.opsi` and `admin.jadwal-pelajaran.index` (both from Task 1), `Alpine.store('toast')` (existing global), `TomSelect` (existing dependency).
- Produces: nothing consumed by a later task — this is the last task in this plan.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Admin/JadwalPelajaranCrudTest.php` (append at the end):

```php
it('renders the filter fields in tahun ajaran, semester, kelas order with no submit button', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index'));
    $html = $response->getContent();

    $posisiTahunAjaran = strpos($html, 'name="tahun_ajaran_id"') ?: strpos($html, 'x-ref="tahunAjaranSelect"');
    $posisiSemester = strpos($html, 'x-ref="semesterSelect"');
    $posisiKelas = strpos($html, 'x-ref="kelasSelect"');

    expect($posisiTahunAjaran)->not->toBeFalse();
    expect($posisiSemester)->not->toBeFalse();
    expect($posisiKelas)->not->toBeFalse();
    expect($posisiTahunAjaran)->toBeLessThan($posisiSemester);
    expect($posisiSemester)->toBeLessThan($posisiKelas);

    $response->assertDontSee('Tampilkan');
});

it('wires the filter card with jadwalPelajaranFilter and the correct initial values', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index', [
        'tahun_ajaran_id' => $tahunAjaran->id, 'kelas_id' => $kelas->id, 'semester_id' => $semester->id,
    ]));

    $response->assertSee('jadwalPelajaranFilter(', false);
    $response->assertSee((string) $tahunAjaran->id, false);
    $response->assertSee((string) $kelas->id, false);
    $response->assertSee((string) $semester->id, false);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --filter "filter fields|jadwalPelajaranFilter"`
Expected: FAIL — the filter card still has the old field order (Tahun Ajaran, Kelas, Semester), a "Tampilkan" submit button, and no `x-data="jadwalPelajaranFilter(...)"`.

- [ ] **Step 3: Create the JS module**

Create `resources/js/jadwal-pelajaran-filter.js`:

```js
import TomSelect from 'tom-select';

export function jadwalPelajaranFilter(config) {
    return {
        tahunAjaranId: config.tahunAjaranId ?? '',
        kelasId: config.kelasId ?? '',
        semesterId: config.semesterId ?? '',
        opsiUrl: config.opsiUrl,
        indexUrlBase: config.indexUrlBase,
        createUrlBase: config.createUrlBase,
        kelasTomSelect: null,

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

        async gantiTahunAjaran(tahunAjaranId) {
            this.kelasId = '';
            this.semesterId = '';
            this.kelasTomSelect?.clear(true);
            this.kelasTomSelect?.clearOptions();
            if (this.$refs.semesterSelect) {
                this.$refs.semesterSelect.innerHTML = '<option value="">— Pilih Semester —</option>';
            }

            if (tahunAjaranId) {
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

                        json.semesterList.forEach((semester) => {
                            const option = document.createElement('option');
                            option.value = semester.id;
                            option.textContent = semester.nama;
                            this.$refs.semesterSelect.appendChild(option);
                        });
                    }
                } catch (error) {
                    Alpine.store('toast').push('error', 'Gagal memuat opsi kelas dan semester.');
                }
            }

            await this.muatUlangDaftar();
        },

        async muatUlangDaftar() {
            this.perbaruiUrl();

            try {
                const url = new URL(this.indexUrlBase, window.location.origin);
                if (this.tahunAjaranId) url.searchParams.set('tahun_ajaran_id', this.tahunAjaranId);
                if (this.kelasId) url.searchParams.set('kelas_id', this.kelasId);
                if (this.semesterId) url.searchParams.set('semester_id', this.semesterId);

                const response = await fetch(url, {
                    headers: {
                        Accept: 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    Alpine.store('toast').push('error', 'Gagal memuat daftar jadwal.');
                    return;
                }

                this.$refs.daftarJadwal.innerHTML = await response.text();
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memuat daftar jadwal.');
            }
        },

        perbaruiUrl() {
            const url = new URL(window.location.href);
            const params = url.searchParams;
            this.tahunAjaranId ? params.set('tahun_ajaran_id', this.tahunAjaranId) : params.delete('tahun_ajaran_id');
            this.kelasId ? params.set('kelas_id', this.kelasId) : params.delete('kelas_id');
            this.semesterId ? params.set('semester_id', this.semesterId) : params.delete('semester_id');
            window.history.pushState({}, '', url);
        },

        tambahSlotUrl() {
            const url = new URL(this.createUrlBase, window.location.origin);
            url.searchParams.set('kelas_id', this.kelasId);
            url.searchParams.set('semester_id', this.semesterId);
            return url.toString();
        },
    };
}
```

- [ ] **Step 4: Register the Alpine component**

In `resources/js/app.js`, add the import near the other form-component imports (alongside `dataDiriForm`/`formulirTambahanForm`):

```js
import { jadwalPelajaranFilter } from './jadwal-pelajaran-filter';
```

And register it near the other `Alpine.data(...)` calls:

```js
Alpine.data('jadwalPelajaranFilter', jadwalPelajaranFilter);
```

- [ ] **Step 5: Rewrite the filter card in the view**

In `resources/views/admin/jadwal-pelajaran/index.blade.php`, replace the entire `{{-- 1. Card Filter: Parameter Jadwal --}}` block with:

```blade
        {{-- 1. Card Filter: Parameter Jadwal --}}
        <div
            class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm space-y-5"
            x-data="jadwalPelajaranFilter({
                tahunAjaranId: @js($tahunAjaranId),
                kelasId: @js($kelasId),
                semesterId: @js($semesterId),
                opsiUrl: @js(route('admin.jadwal-pelajaran.opsi')),
                indexUrlBase: @js(route('admin.jadwal-pelajaran.index')),
                createUrlBase: @js(route('admin.jadwal-pelajaran.create')),
            })"
        >
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-100 pb-4">
                <div>
                    <h2 class="font-display text-base font-bold text-gray-900">Filter Jadwal Pelajaran</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Pilih parameter tahun ajaran, semester, dan kelas untuk menampilkan data.</p>
                </div>
                <template x-if="kelasId && semesterId">
                    <x-link-button href="#" x-bind:href="tambahSlotUrl()" class="shrink-0 justify-center">
                        <span class="text-base leading-none mr-1.5">+</span> Tambah Slot Jadwal
                    </x-link-button>
                </template>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <x-input-label value="Tahun Ajaran" />
                    <select x-ref="tahunAjaranSelect" x-init="initTahunAjaranSelect($refs.tahunAjaranSelect)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm">
                        <option value="">— Pilih Tahun Ajaran —</option>
                        @foreach ($tahunAjaranList as $tahunAjaran)
                            <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label value="Semester" />
                    <select x-ref="semesterSelect" x-model="semesterId" @change="muatUlangDaftar()" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Pilih Semester —</option>
                        @foreach ($semesterList as $semester)
                            <option value="{{ $semester->id }}" @selected($semesterId == $semester->id)>{{ $semester->nama }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label value="Kelas" />
                    <select x-ref="kelasSelect" x-init="initKelasSelect($refs.kelasSelect)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm">
                        <option value="">— Pilih Kelas —</option>
                        @foreach ($kelasList as $kelas)
                            <option value="{{ $kelas->id }}" @selected($kelasId == $kelas->id)>{{ $kelas->nama }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
```

(The `<form method="GET">` wrapper and the "Tampilkan" `<x-primary-button>` from the old markup are gone entirely — every field now drives `muatUlangDaftar()`/`gantiTahunAjaran()` directly via Alpine, no submit needed. `x-ref="daftarJadwal"` referenced in the JS module lives on the sibling `<div>` Task 1 already added around the `@include`, one level up in this same template — confirm it's still there after this edit, since this step only touches the filter card block, not the daftar block below it.)

- [ ] **Step 6: Build assets and run tests**

Run: `npm run build`
Expected: builds successfully with no errors (confirms the new JS module has no syntax errors and Tom Select import resolves).

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php`
Expected: PASS (all tests, including the 2 new ones from this task)

- [ ] **Step 7: Run the full suite**

Run: `php artisan test`
Expected: all tests pass.

- [ ] **Step 8: Manual verification note**

This task's actual interactive behavior (Tom Select search-as-you-type, the no-reload fetch-and-swap on filter change, URL updates via History API) cannot be exercised by Pest — there is no JS test runner configured in this project and no browser automation tool in this Windows dev environment. The Pest tests in this task only prove the STATIC markup is correct (field order, absence of a submit button, presence of the right `x-data` wiring and initial values) — they do not prove the JS actually behaves correctly when clicked. State this limitation plainly in the task report rather than claiming full verification.

- [ ] **Step 9: Commit**

```bash
git add resources/js/jadwal-pelajaran-filter.js resources/js/app.js resources/views/admin/jadwal-pelajaran/index.blade.php tests/Feature/Admin/JadwalPelajaranCrudTest.php
git commit -m "feat: no-reload Tahun Ajaran/Semester/Kelas filter with Tom Select for Jadwal Pelajaran"
```

---

## Plan Self-Review Notes

- **Spec coverage**: every requirement in the design spec (field order, Tom Select scope, no submit button, no reload, hari-aktif restriction, explanatory message, button-placement correction) maps to a concrete step across the 2 tasks.
- **Type/interface consistency**: `opsiUrl`/`indexUrlBase`/`createUrlBase` config keys used in the Blade `x-data(...)` call (Task 2 Step 5) match exactly the property names read in the JS module's constructor destructuring (Task 2 Step 3). The JSON shape `{kelasList: [{id,nama}], semesterList: [{id,nama}]}` produced by `opsi()` (Task 1 Step 5) matches exactly what `gantiTahunAjaran()` reads (Task 2 Step 3).
- **No placeholders**: all Blade/PHP/JS code blocks are complete; the one explicit caveat (JS behavior not machine-verifiable) is stated as a documented limitation, not a placeholder.
- **Task ordering**: Task 2 cannot be tested end-to-end without Task 1's routes existing — Task 1 must merge first, or at minimum land first within the same worktree before Task 2 starts.
