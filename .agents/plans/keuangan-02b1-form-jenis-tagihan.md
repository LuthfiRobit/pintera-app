# Keuangan Sub-project 2b-1: Form Jenis Tagihan — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the current inline-card "Jenis Tagihan" form with a dedicated create/edit page that branches by kategori — PPDB kategori (`pendaftaran`/`daftar_ulang`) keep today's simple form untouched, every other kategori gets 4 sections (Informasi Dasar+Mode, Target Sasaran, Tarif Berdimensi, Keringanan) that feed the Sub-project 2a billing engine.

**Architecture:** Two new full-page routes (`create`, `edit`) render `admin/jenis-tagihan/form.blade.php`. The page is a plain server-rendered `<form method="POST">` (matching the existing precedent in `admin/jenis-tagihan/nominal.blade.php` — dedicated pages in this codebase use real form posts, not the AJAX/JSON pattern reserved for index-page inline modals). Alpine.js manages the repeatable-group UI (Sasaran/Tarif/Keringanan cards) by dynamically binding `:name` on real `<select>`/`<input>` elements, so Laravel parses the nested arrays from standard form-encoded POST data with zero custom JS submission logic. Validation failures use the ordinary `back()->withErrors()->withInput()` cycle; `old()` seeds Alpine's initial state on re-render. The one exception is the "+ Kategori Baru" affordance inside Keringanan, which is a genuine separate-resource creation and uses a small `fetch()` call.

**Tech Stack:** Laravel 12, Blade, Alpine.js (inline `<script>` in the view — no new JS file), Pest.

## Global Constraints

- Kategori enum (DB, already migrated in Sub-project 1): `pendaftaran`, `daftar_ulang`, `lainnya`, `spp`, `tahunan`, `kegiatan`, `custom`. `PPDB_KATEGORI = ['pendaftaran', 'daftar_ulang']` — these two keep the existing nominal-per-jalur mechanism and MUST NOT accept `sasaran`/`tarif`/`keringanan` payload keys (reject with 422, checked server-side, never trust that the UI simply didn't send them).
- Every other kategori gets the full mode/sasaran/tarif/keringanan feature set.
- Replace-all-on-save is the confirmed-safe persistence strategy for `sasaranGrup`+`kriteria` and `keringananRules` (verified: no FK from `billing_job_logs` or `tagihan` into these config tables — see `.agents/specs/keuangan-02b1-form-jenis-tagihan.md` "Verifikasi Keamanan Data"). Always wrap delete+recreate in one `DB::transaction()`.
- `jenis_tagihan_sasaran_kriteria.field` valid values: `lembaga`, `tahun_ajaran`, `tingkat`, `kelas`, `jenis_kelamin`, `status_siswa` (DB enum, matches `JenisTagihanSasaranMatcher::applyKriteriaToQuery()`). `operator`: `in`, `not_in`. `value` is a JSON array, never empty.
- `jenis_tagihan_keringanan.tipe_potongan` valid values: `fixed`, `persen` (DB enum). `unique(jenis_tagihan_id, kategori_keringanan_id)` DB constraint — validate no duplicate `kategori_keringanan_id` within one payload BEFORE insert, don't rely on the raw DB error.
- Permission: reuse `jenis-tagihan.create`/`jenis-tagihan.edit` for everything in this plan, including the inline kategori-keringanan creation — it's part of the same form, no new permission needed.
- Tenant scoping for reference data (Lembaga list for the `lembaga` kriteria field is yayasan-wide by design — every other reference list, i.e. TahunAjaran/Kelas/KategoriKeringanan, is scoped to the `jenis_tagihan`'s own `lembaga_id`, mirroring how `store()`/`update()` already resolve `lembaga_id` today).
- Do not touch `TagihanBillingGenerator`, `JenisTagihanSasaranMatcher`, or `TagihanNominalResolver` (Sub-project 2a, already shipped and merged) — this plan only adds admin CRUD around the data they consume.

---

### Task 1: Backend — extend `JenisTagihanController` store/update + create/edit routes

**Files:**
- Modify: `app/Http/Controllers/Admin/JenisTagihanController.php`
- Modify: `routes/admin.php:179-184`
- Test: `tests/Feature/Admin/JenisTagihanFormTest.php`

**Interfaces:**
- Consumes: `JenisTagihan::sasaranGrup()`, `::keringananRules()` (existing relations), `JenisTagihanSasaranGrup::kriteria()` (existing relation), models `Lembaga`, `TahunAjaran`, `Kelas`, `KategoriKeringanan`.
- Produces: `JenisTagihanController::create()`, `::edit()` (new actions), extended `::store()`/`::update()` accepting the billing-config payload. Later tasks (3-6, the Blade views) render the form these actions serve.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Admin/JenisTagihanFormTest.php

use App\Models\JenisTagihan;
use App\Models\JenisTagihanSasaranGrup;
use App\Models\KategoriKeringanan;
use App\Models\Lembaga;
use App\Models\TagihanBillingGenerator;
use App\Models\User;
use App\Services\TagihanBillingGenerator as BillingGenerator;
use App\Models\Yayasan;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

function buatUserKeuangan(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    return [$user, $lembaga];
}

it('creates a non-ppdb jenis tagihan with sasaran, tarif, and keringanan in one save', function () {
    [$user, $lembaga] = buatUserKeuangan();
    $kategoriKeringanan = KategoriKeringanan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Yatim Piatu']);

    $response = $this->actingAs($user)->post(route('admin.jenis-tagihan.store'), [
        'nama' => 'SPP Bulanan',
        'kategori' => 'spp',
        'bisa_dicicil' => false,
        'mode' => 'otomatis',
        'default_amount' => 500000,
        'tanggal_mulai' => '2026-07-01',
        'tanggal_generate' => 1,
        'hari_jatuh_tempo' => 10,
        'sasaran' => [
            ['kriteria' => [['field' => 'status_siswa', 'operator' => 'in', 'value' => ['aktif']]]],
        ],
        'tarif' => [
            ['nominal' => 450000, 'kriteria' => [['field' => 'jenis_kelamin', 'operator' => 'in', 'value' => ['L']]]],
        ],
        'keringanan' => [
            ['kategori_keringanan_id' => $kategoriKeringanan->id, 'tipe_potongan' => 'persen', 'nilai' => 50, 'keterangan' => 'Beasiswa'],
        ],
    ]);

    $response->assertRedirect(route('admin.jenis-tagihan.index'));
    $jenisTagihan = JenisTagihan::where('nama', 'SPP Bulanan')->firstOrFail();
    expect($jenisTagihan->mode)->toBe('otomatis');

    $sasaranGrup = $jenisTagihan->sasaranGrup()->where('tipe', 'sasaran')->with('kriteria')->first();
    expect($sasaranGrup->kriteria)->toHaveCount(1);
    expect($sasaranGrup->kriteria->first()->field)->toBe('status_siswa');

    $tarifGrup = $jenisTagihan->sasaranGrup()->where('tipe', 'tarif')->first();
    expect((float) $tarifGrup->nominal)->toBe(450000.0);

    $rule = $jenisTagihan->keringananRules()->first();
    expect($rule->kategori_keringanan_id)->toBe($kategoriKeringanan->id);
    expect((float) $rule->nilai)->toBe(50.0);
});

it('rejects a sasaran payload for a ppdb kategori with a 422, creating nothing', function () {
    [$user] = buatUserKeuangan();

    $response = $this->actingAs($user)->postJson(route('admin.jenis-tagihan.store'), [
        'nama' => 'Biaya Pendaftaran',
        'kategori' => 'pendaftaran',
        'bisa_dicicil' => false,
        'sasaran' => [['kriteria' => [['field' => 'status_siswa', 'operator' => 'in', 'value' => ['aktif']]]]],
    ]);

    $response->assertStatus(422);
    expect(JenisTagihan::where('nama', 'Biaya Pendaftaran')->exists())->toBeFalse();
    expect(JenisTagihanSasaranGrup::count())->toBe(0);
});

it('rejects two keringanan rules for the same kategori_keringanan_id in one payload', function () {
    [$user, $lembaga] = buatUserKeuangan();
    $kategoriKeringanan = KategoriKeringanan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Yatim Piatu']);

    $response = $this->actingAs($user)->postJson(route('admin.jenis-tagihan.store'), [
        'nama' => 'SPP Bulanan', 'kategori' => 'spp', 'bisa_dicicil' => false,
        'keringanan' => [
            ['kategori_keringanan_id' => $kategoriKeringanan->id, 'tipe_potongan' => 'fixed', 'nilai' => 10000],
            ['kategori_keringanan_id' => $kategoriKeringanan->id, 'tipe_potongan' => 'persen', 'nilai' => 20],
        ],
    ]);

    $response->assertStatus(422);
    expect(JenisTagihan::where('nama', 'SPP Bulanan')->exists())->toBeFalse();
});

it('replaces sasaran on update without touching already-generated tagihan for that jenis tagihan', function () {
    [$user, $lembaga] = buatUserKeuangan();
    $jenisTagihan = JenisTagihan::create([
        'lembaga_id' => $lembaga->id, 'nama' => 'SPP Bulanan', 'kategori' => 'spp', 'bisa_dicicil' => false,
        'mode' => 'manual', 'default_amount' => 500000,
    ]);
    $grup = $jenisTagihan->sasaranGrup()->create(['tipe' => 'sasaran']);
    $grup->kriteria()->create(['field' => 'status_siswa', 'operator' => 'in', 'value' => ['aktif']]);

    $log = app(BillingGenerator::class)->generate($jenisTagihan, 'manual');
    expect($log->bills_generated)->toBeGreaterThanOrEqual(0);
    $existingTagihanCount = \App\Models\Tagihan::where('jenis_tagihan_id', $jenisTagihan->id)->count();

    $response = $this->actingAs($user)->put(route('admin.jenis-tagihan.update', $jenisTagihan), [
        'nama' => 'SPP Bulanan', 'kategori' => 'spp', 'bisa_dicicil' => false,
        'mode' => 'manual', 'default_amount' => 500000,
        'sasaran' => [
            ['kriteria' => [['field' => 'status_siswa', 'operator' => 'in', 'value' => ['lulus']]]],
        ],
    ]);

    $response->assertRedirect(route('admin.jenis-tagihan.index'));
    expect(\App\Models\Tagihan::where('jenis_tagihan_id', $jenisTagihan->id)->count())->toBe($existingTagihanCount);
    $newGrup = $jenisTagihan->sasaranGrup()->where('tipe', 'sasaran')->first();
    expect($newGrup->kriteria->first()->value)->toBe(['lulus']);
});

it('still creates a ppdb jenis tagihan without any billing fields, unchanged from before', function () {
    [$user] = buatUserKeuangan();

    $response = $this->actingAs($user)->post(route('admin.jenis-tagihan.store'), [
        'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false,
    ]);

    $response->assertRedirect();
    $jenisTagihan = JenisTagihan::where('nama', 'Biaya Pendaftaran')->firstOrFail();
    $response->assertRedirect(route('admin.jenis-tagihan.nominal', $jenisTagihan));
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/JenisTagihanFormTest.php`
Expected: FAIL (routes/columns/validation don't exist yet — `mode`/`sasaran`/etc. not accepted, `create`/`edit` actions missing).

- [ ] **Step 3: Extend `routes/admin.php`**

Replace lines 179-184 with:

```php
    Route::get('jenis-tagihan/create', [JenisTagihanController::class, 'create'])->name('jenis-tagihan.create');
    Route::get('jenis-tagihan', [JenisTagihanController::class, 'index'])->name('jenis-tagihan.index');
    Route::post('jenis-tagihan', [JenisTagihanController::class, 'store'])->name('jenis-tagihan.store');
    Route::get('jenis-tagihan/{jenisTagihan}/edit', [JenisTagihanController::class, 'edit'])->name('jenis-tagihan.edit');
    Route::put('jenis-tagihan/{jenisTagihan}', [JenisTagihanController::class, 'update'])->name('jenis-tagihan.update');
    Route::delete('jenis-tagihan/{jenisTagihan}', [JenisTagihanController::class, 'destroy'])->name('jenis-tagihan.destroy');
    Route::get('jenis-tagihan/{jenisTagihan}/nominal', [JenisTagihanController::class, 'nominal'])->name('jenis-tagihan.nominal');
    Route::post('jenis-tagihan/{jenisTagihan}/nominal', [JenisTagihanController::class, 'simpanNominal'])->name('jenis-tagihan.nominal.store');
```

(`create` route must be registered before the bare `jenis-tagihan` GET so `/jenis-tagihan/create` isn't swallowed by a `{jenisTagihan}` wildcard elsewhere — there isn't one on this specific verb here, but keeping `create` first matches Laravel resource-route convention and avoids future foot-guns.)

- [ ] **Step 4: Rewrite `app/Http/Controllers/Admin/JenisTagihanController.php`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\KategoriKeringanan;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\NominalTagihanJalur;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JenisTagihanController extends BaseController
{
    use AuthorizesRequests;

    private const PPDB_KATEGORI = ['pendaftaran', 'daftar_ulang'];

    private const KRITERIA_FIELDS = ['lembaga', 'tahun_ajaran', 'tingkat', 'kelas', 'jenis_kelamin', 'status_siswa'];

    public function index(): View
    {
        $this->authorize('jenis-tagihan.view');

        return view('admin.jenis-tagihan.index', [
            'jenisTagihanList' => JenisTagihan::withCount(['nominalJalur', 'tagihanItem'])->orderBy('nama')->get(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $this->authorize('jenis-tagihan.create');

        $lembagaId = $this->resolveLembagaIdOrFail($request);
        if ($lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah jenis tagihan.']);
        }

        return view('admin.jenis-tagihan.form', array_merge(
            ['jenisTagihan' => null],
            $this->referenceData($lembagaId)
        ));
    }

    public function edit(JenisTagihan $jenisTagihan): View
    {
        $this->authorize('jenis-tagihan.edit');

        $jenisTagihan->load(['sasaranGrup.kriteria', 'keringananRules.kategoriKeringanan']);

        return view('admin.jenis-tagihan.form', array_merge(
            ['jenisTagihan' => $jenisTagihan],
            $this->referenceData($jenisTagihan->lembaga_id)
        ));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('jenis-tagihan.create');

        $lembagaId = $this->resolveLembagaIdOrFail($request);
        if ($lembagaId === null) {
            $message = 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah jenis tagihan.';

            if ($request->wantsJson()) {
                return response()->json(['message' => $message, 'errors' => ['lembaga_id' => [$message]]], 422);
            }

            return back()->withErrors(['lembaga_id' => $message])->withInput();
        }

        $isPpdbKategori = in_array($request->input('kategori'), self::PPDB_KATEGORI, true);

        if ($isPpdbKategori && $this->hasBillingPayload($request)) {
            return $this->errorResponse($request, 'Target sasaran, tarif berdimensi, dan keringanan hanya berlaku untuk kategori selain Pendaftaran/Daftar Ulang.');
        }

        $data = $request->validate($this->baseRules($lembagaId, null));
        $data['bisa_dicicil'] = $request->boolean('bisa_dicicil');

        $billing = null;
        if (! $isPpdbKategori) {
            $billing = $request->validate($this->billingRules($lembagaId));
            $duplicateError = $this->findDuplicateKeringanan($billing['keringanan'] ?? []);
            if ($duplicateError) {
                return $this->errorResponse($request, $duplicateError);
            }
        }

        if ($request->user()->widestScopeLevel() === 'yayasan') {
            $data['lembaga_id'] = $lembagaId;
        }

        $jenisTagihan = DB::transaction(function () use ($data, $billing) {
            $jenisTagihan = JenisTagihan::create($data);
            if ($billing !== null) {
                $this->syncBillingConfig($jenisTagihan, $billing);
            }

            return $jenisTagihan;
        });

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $jenisTagihan->fresh(),
                'redirect' => $isPpdbKategori ? route('admin.jenis-tagihan.nominal', $jenisTagihan) : null,
            ], 201);
        }

        if ($isPpdbKategori) {
            return redirect()->route('admin.jenis-tagihan.nominal', $jenisTagihan)
                ->with('status', 'Jenis tagihan berhasil ditambahkan. Atur nominal per jalur di bawah.');
        }

        return redirect()->route('admin.jenis-tagihan.index')
            ->with('status', 'Jenis tagihan berhasil ditambahkan.');
    }

    public function update(Request $request, JenisTagihan $jenisTagihan): RedirectResponse|JsonResponse
    {
        $this->authorize('jenis-tagihan.edit');

        $isPpdbKategori = in_array($request->input('kategori'), self::PPDB_KATEGORI, true);

        if ($isPpdbKategori && $this->hasBillingPayload($request)) {
            return $this->errorResponse($request, 'Target sasaran, tarif berdimensi, dan keringanan hanya berlaku untuk kategori selain Pendaftaran/Daftar Ulang.');
        }

        $data = $request->validate($this->baseRules($jenisTagihan->lembaga_id, $jenisTagihan));
        $data['bisa_dicicil'] = $request->boolean('bisa_dicicil');

        $billing = null;
        if (! $isPpdbKategori) {
            $billing = $request->validate($this->billingRules($jenisTagihan->lembaga_id));
            $duplicateError = $this->findDuplicateKeringanan($billing['keringanan'] ?? []);
            if ($duplicateError) {
                return $this->errorResponse($request, $duplicateError);
            }
        }

        DB::transaction(function () use ($jenisTagihan, $data, $billing) {
            $jenisTagihan->update($data);
            if ($billing !== null) {
                $this->syncBillingConfig($jenisTagihan, $billing);
            } else {
                $jenisTagihan->sasaranGrup()->delete();
                $jenisTagihan->keringananRules()->delete();
            }
        });

        if ($request->wantsJson()) {
            return response()->json(['data' => $jenisTagihan->fresh()->loadCount(['nominalJalur', 'tagihanItem'])]);
        }

        return redirect()->route('admin.jenis-tagihan.index')->with('status', 'Jenis tagihan berhasil diperbarui.');
    }

    public function destroy(Request $request, JenisTagihan $jenisTagihan): RedirectResponse|JsonResponse
    {
        $this->authorize('jenis-tagihan.delete');

        $jumlahTagihan = $jenisTagihan->tagihanItem()->count();
        if ($jumlahTagihan > 0) {
            return $this->errorResponse(
                $request,
                "Tidak bisa dihapus, sudah dipakai di {$jumlahTagihan} tagihan milik calon murid."
            );
        }

        $jumlahNominal = $jenisTagihan->nominalJalur()->count();
        if ($jumlahNominal > 0) {
            return $this->errorResponse(
                $request,
                "Tidak bisa dihapus, sudah ada {$jumlahNominal} nominal jalur yang dikonfigurasi. Hapus dulu di halaman Kelola Nominal."
            );
        }

        $jenisTagihan->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Jenis tagihan berhasil dihapus.']);
        }

        return redirect()->route('admin.jenis-tagihan.index')->with('status', 'Jenis tagihan berhasil dihapus.');
    }

    public function nominal(JenisTagihan $jenisTagihan): View|RedirectResponse
    {
        $this->authorize('jenis-tagihan.edit');

        if ($jenisTagihan->kategori === 'lainnya') {
            return redirect()->route('admin.jenis-tagihan.index')
                ->withErrors(['kategori' => 'Nominal per jalur PPDB hanya berlaku untuk kategori Pendaftaran/Daftar Ulang. Kategori "Lainnya" belum punya mekanisme penentuan nominal.']);
        }

        $tahunAjaranAktif = TahunAjaran::where('lembaga_id', $jenisTagihan->lembaga_id)->where('status_aktif', true)->first();

        return view('admin.jenis-tagihan.nominal', [
            'jenisTagihan' => $jenisTagihan,
            'jalurList' => $tahunAjaranAktif
                ? JalurPpdb::where('tahun_ajaran_id', $tahunAjaranAktif->id)->orderBy('nama')->get()
                : collect(),
            'nominalMap' => NominalTagihanJalur::where('jenis_tagihan_id', $jenisTagihan->id)->pluck('nominal', 'jalur_ppdb_id'),
            'tahunAjaranAktif' => $tahunAjaranAktif,
        ]);
    }

    public function simpanNominal(Request $request, JenisTagihan $jenisTagihan): RedirectResponse
    {
        $this->authorize('jenis-tagihan.edit');

        if ($jenisTagihan->kategori === 'lainnya') {
            return redirect()->route('admin.jenis-tagihan.index')
                ->withErrors(['kategori' => 'Nominal per jalur PPDB hanya berlaku untuk kategori Pendaftaran/Daftar Ulang.']);
        }

        $data = $request->validate([
            'nominal' => ['required', 'array'],
            'nominal.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $jalurIds = JalurPpdb::where('lembaga_id', $jenisTagihan->lembaga_id)->pluck('id');

        foreach ($data['nominal'] as $jalurPpdbId => $nominal) {
            if (! $jalurIds->contains((int) $jalurPpdbId) || $nominal === null || $nominal === '') {
                continue;
            }

            NominalTagihanJalur::updateOrCreate(
                ['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalurPpdbId],
                ['nominal' => $nominal]
            );
        }

        return redirect()->route('admin.jenis-tagihan.nominal', $jenisTagihan)->with('status', 'Nominal berhasil disimpan.');
    }

    private function resolveLembagaIdOrFail(Request $request): ?int
    {
        if ($request->user()->widestScopeLevel() === 'yayasan') {
            return session('active_lembaga_id');
        }

        return $request->user()->lembaga_id;
    }

    private function referenceData(int $lembagaId): array
    {
        return [
            'lembagaList' => Lembaga::orderBy('nama')->get(['id', 'nama']),
            'tahunAjaranList' => TahunAjaran::where('lembaga_id', $lembagaId)->orderBy('nama')->get(['id', 'nama']),
            'kelasList' => Kelas::where('lembaga_id', $lembagaId)->orderBy('nama')->get(['id', 'nama']),
            'tingkatList' => Kelas::where('lembaga_id', $lembagaId)->whereNotNull('tingkat')->distinct()->orderBy('tingkat')->pluck('tingkat'),
            'kategoriKeringananList' => KategoriKeringanan::where('lembaga_id', $lembagaId)->orderBy('nama')->get(['id', 'nama']),
        ];
    }

    private function hasBillingPayload(Request $request): bool
    {
        return $request->has('sasaran') || $request->has('tarif') || $request->has('keringanan');
    }

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
            'tanggal_mulai' => ['nullable', 'date', 'required_if:mode,otomatis'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'tanggal_generate' => ['nullable', 'integer', 'between:1,31', 'required_if:mode,otomatis'],
            'hari_jatuh_tempo' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function billingRules(int $lembagaId): array
    {
        return [
            'sasaran' => ['nullable', 'array'],
            'sasaran.*.kriteria' => ['required', 'array', 'min:1'],
            'sasaran.*.kriteria.*.field' => ['required', Rule::in(self::KRITERIA_FIELDS)],
            'sasaran.*.kriteria.*.operator' => ['required', Rule::in(['in', 'not_in'])],
            'sasaran.*.kriteria.*.value' => ['required', 'array', 'min:1'],
            'tarif' => ['nullable', 'array'],
            'tarif.*.nominal' => ['required', 'numeric', 'min:0'],
            'tarif.*.kriteria' => ['required', 'array', 'min:1'],
            'tarif.*.kriteria.*.field' => ['required', Rule::in(self::KRITERIA_FIELDS)],
            'tarif.*.kriteria.*.operator' => ['required', Rule::in(['in', 'not_in'])],
            'tarif.*.kriteria.*.value' => ['required', 'array', 'min:1'],
            'keringanan' => ['nullable', 'array'],
            'keringanan.*.kategori_keringanan_id' => ['required', 'integer', Rule::exists('kategori_keringanan', 'id')->where('lembaga_id', $lembagaId)],
            'keringanan.*.tipe_potongan' => ['required', Rule::in(['fixed', 'persen'])],
            'keringanan.*.nilai' => ['required', 'numeric', 'min:0'],
            'keringanan.*.keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function findDuplicateKeringanan(array $keringanan): ?string
    {
        $ids = array_column($keringanan, 'kategori_keringanan_id');
        if (count($ids) !== count(array_unique($ids))) {
            return 'Satu kategori keringanan tidak boleh dipakai lebih dari sekali untuk jenis tagihan yang sama.';
        }

        return null;
    }

    private function syncBillingConfig(JenisTagihan $jenisTagihan, array $billing): void
    {
        $jenisTagihan->sasaranGrup()->delete();
        $jenisTagihan->keringananRules()->delete();

        foreach ($billing['sasaran'] ?? [] as $grupData) {
            $grup = $jenisTagihan->sasaranGrup()->create(['tipe' => 'sasaran']);
            foreach ($grupData['kriteria'] as $kriteriaData) {
                $grup->kriteria()->create($kriteriaData);
            }
        }

        foreach ($billing['tarif'] ?? [] as $grupData) {
            $grup = $jenisTagihan->sasaranGrup()->create(['tipe' => 'tarif', 'nominal' => $grupData['nominal']]);
            foreach ($grupData['kriteria'] as $kriteriaData) {
                $grup->kriteria()->create($kriteriaData);
            }
        }

        foreach ($billing['keringanan'] ?? [] as $ruleData) {
            $jenisTagihan->keringananRules()->create($ruleData);
        }
    }

    private function errorResponse(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->withErrors(['jenis_tagihan' => $message])->withInput();
    }
}
```

Note on `Rule::exists('kategori_keringanan', 'id')->where('lembaga_id', $lembagaId)`: this both validates existence AND enforces tenant scoping in one rule — a `kategori_keringanan_id` belonging to another lembaga fails validation rather than silently succeeding.

- [ ] **Step 5: Add the `mode`/`default_amount`/etc. fillable check**

`JenisTagihan::$fillable` already includes `mode`, `default_amount`, `tanggal_mulai`, `tanggal_selesai`, `tanggal_generate`, `hari_jatuh_tempo`, `is_active` (added in Sub-project 1/2a) — verify via `grep fillable app/Models/JenisTagihan.php` before running tests; no model change expected, this step is a verification checkpoint only.

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/JenisTagihanFormTest.php`
Expected: PASS (5/5)

- [ ] **Step 7: Run the pre-existing `JenisTagihanTest.php` to confirm no regression**

Run: `php artisan test tests/Feature/Admin/JenisTagihanTest.php`
Expected: PASS (21/21, unchanged)

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/JenisTagihanController.php routes/admin.php tests/Feature/Admin/JenisTagihanFormTest.php
git commit -m "feat(keuangan): extend jenis-tagihan controller with mode/sasaran/tarif/keringanan"
```

---

### Task 2: `KategoriKeringananController` — inline "+ Kategori Baru" AJAX endpoint

**Files:**
- Create: `app/Http/Controllers/Admin/KategoriKeringananController.php`
- Modify: `routes/admin.php` (add one route near the `jenis-tagihan` block)
- Test: `tests/Feature/Admin/KategoriKeringananTest.php`

**Interfaces:**
- Consumes: `KategoriKeringanan` model (existing, `fillable = ['lembaga_id', 'nama', 'keterangan']`).
- Produces: `POST admin/kategori-keringanan` → JSON `{id, nama}`, consumed by Task 5's Alpine "+ Kategori Baru" button.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Admin/KategoriKeringananTest.php

use App\Models\KategoriKeringanan;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('lets admin_keuangan create a kategori keringanan inline, scoped to their own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->postJson(route('admin.kategori-keringanan.store'), [
        'nama' => 'Yatim Piatu',
        'keterangan' => 'Anak yatim piatu terdaftar',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.nama', 'Yatim Piatu');
    $kategori = KategoriKeringanan::where('nama', 'Yatim Piatu')->firstOrFail();
    expect($kategori->lembaga_id)->toBe($lembaga->id);
});

it('denies kategori keringanan creation without jenis-tagihan.create permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->postJson(route('admin.kategori-keringanan.store'), ['nama' => 'X'])
        ->assertForbidden();
});

it('rejects a duplicate kategori keringanan name within the same lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    KategoriKeringanan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Yatim Piatu']);

    $this->actingAs($user)->postJson(route('admin.kategori-keringanan.store'), ['nama' => 'Yatim Piatu'])
        ->assertStatus(422);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/KategoriKeringananTest.php`
Expected: FAIL (route/controller don't exist)

- [ ] **Step 3: Add the route**

In `routes/admin.php`, add directly after the `jenis-tagihan.nominal.store` line:

```php
    Route::post('kategori-keringanan', [KategoriKeringananController::class, 'store'])->name('kategori-keringanan.store');
```

Add the `use` import near the other `use App\Http\Controllers\Admin\...` lines:

```php
use App\Http\Controllers\Admin\KategoriKeringananController;
```

- [ ] **Step 4: Write the controller**

```php
<?php
// app/Http/Controllers/Admin/KategoriKeringananController.php

namespace App\Http\Controllers\Admin;

use App\Models\KategoriKeringanan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;

class KategoriKeringananController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request): JsonResponse
    {
        $this->authorize('jenis-tagihan.create');

        $lembagaId = $request->user()->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $request->user()->lembaga_id;

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('kategori_keringanan', 'nama')
                ->where(fn ($query) => $query->where('lembaga_id', $lembagaId))],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);
        $data['lembaga_id'] = $lembagaId;

        $kategori = KategoriKeringanan::create($data);

        return response()->json(['data' => $kategori], 201);
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/Admin/KategoriKeringananTest.php`
Expected: PASS (3/3)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/KategoriKeringananController.php routes/admin.php tests/Feature/Admin/KategoriKeringananTest.php
git commit -m "feat(keuangan): add inline kategori-keringanan creation endpoint"
```

---

### Task 3: Blade form page — skeleton + Section 1 (Informasi Dasar + Mode)

**Files:**
- Create: `resources/views/admin/jenis-tagihan/form.blade.php`
- Test: `tests/Feature/Admin/JenisTagihanFormPageTest.php`

**Interfaces:**
- Consumes: view data from Task 1's `create()`/`edit()` — `$jenisTagihan` (nullable), `$lembagaList`, `$tahunAjaranList`, `$kelasList`, `$tingkatList`, `$kategoriKeringananList`.
- Produces: the `jenisTagihanForm()` Alpine factory and its `kategoriPpdb` computed getter that Tasks 4-5 extend with Section 2/3/4 markup inside the same file.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Admin/JenisTagihanFormPageTest.php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('renders the create page with the kategori select and mode toggle', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.create'));

    $response->assertOk();
    $response->assertSee('Tambah Jenis Tagihan');
    $response->assertSee('name="kategori"', false);
});

it('renders the edit page pre-filled with the existing jenis tagihan nama', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'SPP Bulanan', 'kategori' => 'spp', 'bisa_dicicil' => false]);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.edit', $jenisTagihan));

    $response->assertOk();
    $response->assertSee('value="SPP Bulanan"', false);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/JenisTagihanFormPageTest.php`
Expected: FAIL (view doesn't exist)

- [ ] **Step 3: Write the view (skeleton + Section 1 only — Sections 2-4 are empty placeholders `<!-- Task 4/5 -->` filled in by later tasks in this SAME file)**

```blade
<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-4">
        <a href="{{ route('admin.jenis-tagihan.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-gray-500 hover:text-brand-600">
            &larr; Kembali ke Jenis Tagihan
        </a>

        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">{{ $jenisTagihan === null ? 'Tambah Jenis Tagihan' : 'Edit Jenis Tagihan' }}</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.jenis-tagihan.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Jenis Tagihan</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">{{ $jenisTagihan === null ? 'Tambah' : 'Edit' }}</b>
            </p>
        </div>

        <form
            method="POST"
            action="{{ $jenisTagihan === null ? route('admin.jenis-tagihan.store') : route('admin.jenis-tagihan.update', $jenisTagihan) }}"
            x-data="jenisTagihanForm({
                kategoriAwal: @js(old('kategori', $jenisTagihan?->kategori ?? 'lainnya')),
                kategoriKeringananList: @js($kategoriKeringananList),
            })"
            class="space-y-5"
        >
            @csrf
            @if ($jenisTagihan !== null)
                @method('PUT')
            @endif

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
                <p class="font-display text-sm font-bold text-gray-900">1. Informasi Dasar</p>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Nama" />
                        <x-text-input type="text" name="nama" :value="old('nama', $jenisTagihan?->nama)" placeholder="mis. SPP Bulanan" class="mt-1.5" required />
                    </div>
                    <div>
                        <x-input-label value="Kategori" />
                        <select name="kategori" x-model="form.kategori" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="pendaftaran">Pendaftaran</option>
                            <option value="daftar_ulang">Daftar Ulang</option>
                            <option value="lainnya">Lainnya</option>
                            <option value="spp">SPP</option>
                            <option value="tahunan">Tahunan</option>
                            <option value="kegiatan">Kegiatan</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="bisa_dicicil" value="1" x-model="form.bisaDicicil" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500" {{ old('bisa_dicicil', $jenisTagihan?->bisa_dicicil) ? 'checked' : '' }}>
                            Bisa dicicil
                        </label>
                        <div x-show="form.bisaDicicil" x-cloak class="mt-2 max-w-[160px]">
                            <x-input-label value="Maksimal Jumlah Cicilan" />
                            <x-text-input type="number" min="2" name="maks_cicilan" :value="old('maks_cicilan', $jenisTagihan?->maks_cicilan)" class="mt-1.5" />
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500" {{ old('is_active', $jenisTagihan?->is_active ?? true) ? 'checked' : '' }}>
                            Status Aktif
                        </label>
                    </div>
                </div>

                <div x-show="!kategoriPpdb" x-cloak class="grid grid-cols-1 gap-3 border-t border-gray-100 pt-4 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Nominal Default" />
                        <x-text-input type="number" step="0.01" min="0" name="default_amount" :value="old('default_amount', $jenisTagihan?->default_amount)" class="mt-1.5" placeholder="Dipakai jika tidak ada Tarif Berdimensi yang cocok" />
                    </div>
                    <div>
                        <x-input-label value="Mode" />
                        <select name="mode" x-model="form.mode" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="manual">Manual</option>
                            <option value="otomatis">Otomatis</option>
                        </select>
                    </div>
                    <template x-if="form.mode === 'otomatis'">
                        <div class="grid grid-cols-1 gap-3 sm:col-span-2 sm:grid-cols-2">
                            <div>
                                <x-input-label value="Tanggal Mulai" />
                                <x-text-input type="date" name="tanggal_mulai" :value="old('tanggal_mulai', optional($jenisTagihan?->tanggal_mulai)->toDateString())" class="mt-1.5" />
                            </div>
                            <div>
                                <x-input-label value="Tanggal Selesai (opsional)" />
                                <x-text-input type="date" name="tanggal_selesai" :value="old('tanggal_selesai', optional($jenisTagihan?->tanggal_selesai)->toDateString())" class="mt-1.5" />
                            </div>
                            <div>
                                <x-input-label value="Tanggal Generate (hari ke-)" />
                                <x-text-input type="number" min="1" max="31" name="tanggal_generate" :value="old('tanggal_generate', $jenisTagihan?->tanggal_generate)" class="mt-1.5" />
                            </div>
                            <div>
                                <x-input-label value="Hari Jatuh Tempo (setelah generate)" />
                                <x-text-input type="number" min="0" name="hari_jatuh_tempo" :value="old('hari_jatuh_tempo', $jenisTagihan?->hari_jatuh_tempo)" class="mt-1.5" />
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Task 4: Section 2 (Target Sasaran) + Section 3 (Tarif Berdimensi) go here -->

            <!-- Task 5: Section 4 (Keringanan) goes here -->

            <div class="flex items-center gap-3">
                <x-primary-button type="submit">{{ $jenisTagihan === null ? 'Tambah' : 'Simpan' }}</x-primary-button>
                <x-secondary-button type="button" @click="window.location.href = @js(route('admin.jenis-tagihan.index'))">Batal</x-secondary-button>
            </div>
        </form>
    </div>

    <script>
        function jenisTagihanForm(config) {
            return {
                form: {
                    kategori: config.kategoriAwal,
                    mode: @js(old('mode', $jenisTagihan?->mode ?? 'manual')),
                    bisaDicicil: @js((bool) old('bisa_dicicil', $jenisTagihan?->bisa_dicicil ?? false)),
                },
                kategoriKeringananOptions: config.kategoriKeringananList,
                get kategoriPpdb() {
                    return ['pendaftaran', 'daftar_ulang'].includes(this.form.kategori);
                },
            };
        }
    </script>
</x-app-layout>
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/JenisTagihanFormPageTest.php`
Expected: PASS (2/2)

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/jenis-tagihan/form.blade.php tests/Feature/Admin/JenisTagihanFormPageTest.php
git commit -m "feat(keuangan): add jenis-tagihan form page skeleton with informasi dasar section"
```

---

### Task 4: Section 2 (Target Sasaran) + Section 3 (Tarif Berdimensi)

**Files:**
- Modify: `resources/views/admin/jenis-tagihan/form.blade.php` (replace the `<!-- Task 4: ... -->` placeholder; extend the `jenisTagihanForm()` Alpine factory)
- Test: `tests/Feature/Admin/JenisTagihanSasaranFormTest.php`

**Interfaces:**
- Consumes: `$lembagaList`, `$tahunAjaranList`, `$kelasList`, `$tingkatList` (Task 1's `referenceData()`); `JenisTagihanSasaranMatcher::KRITERIA_FIELDS`-equivalent list (`lembaga`, `tahun_ajaran`, `tingkat`, `kelas`, `jenis_kelamin`, `status_siswa` — duplicated as a JS constant here since the matcher's list lives in PHP).
- Produces: `sasaran`/`tarif` array state on the shared Alpine `form` object, submitted as native nested form-array inputs consumed by Task 1's `syncBillingConfig()`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Admin/JenisTagihanSasaranFormTest.php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('renders sasaran and tarif section markers on the create page for a non-ppdb kategori default', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.create'));

    $response->assertOk();
    $response->assertSee('2. Target Sasaran');
    $response->assertSee('3. Tarif Berdimensi');
});

it('pre-fills sasaran kriteria fields from an existing jenis tagihan on the edit page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'SPP Bulanan', 'kategori' => 'spp', 'bisa_dicicil' => false]);
    $grup = $jenisTagihan->sasaranGrup()->create(['tipe' => 'sasaran']);
    $grup->kriteria()->create(['field' => 'status_siswa', 'operator' => 'in', 'value' => ['aktif']]);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.edit', $jenisTagihan));

    $response->assertOk();
    $response->assertSee('status_siswa', false);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/JenisTagihanSasaranFormTest.php`
Expected: FAIL (sections don't exist yet)

- [ ] **Step 3: Replace the `<!-- Task 4: ... -->` placeholder** in `form.blade.php` with:

```blade
            <div x-show="!kategoriPpdb" x-cloak class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
                <p class="font-display text-sm font-bold text-gray-900">2. Target Sasaran</p>
                <div class="flex items-center gap-4 text-sm">
                    <label class="flex items-center gap-2"><input type="radio" value="semua" x-model="sasaranMode"> Semua Siswa</label>
                    <label class="flex items-center gap-2"><input type="radio" value="kriteria" x-model="sasaranMode"> Berdasarkan Kriteria</label>
                </div>

                <template x-if="sasaranMode === 'kriteria'">
                    <div class="space-y-3">
                        <template x-for="(grup, gi) in form.sasaran" :key="grup.uid">
                            <div class="rounded-xl border border-gray-200 p-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-semibold uppercase text-gray-500" x-text="'Sasaran #' + (gi + 1)"></p>
                                    <button type="button" class="text-xs font-semibold text-error-600" @click="form.sasaran.splice(gi, 1)">Hapus</button>
                                </div>
                                <template x-for="(kriteria, ki) in grup.kriteria" :key="kriteria.uid">
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-4">
                                        <select :name="'sasaran[' + gi + '][kriteria][' + ki + '][field]'" x-model="kriteria.field" class="rounded-lg border-gray-200 text-sm">
                                            <template x-for="fieldOpt in kriteriaFields" :key="fieldOpt"><option :value="fieldOpt" x-text="fieldOpt" :selected="fieldOpt === kriteria.field"></option></template>
                                        </select>
                                        <select :name="'sasaran[' + gi + '][kriteria][' + ki + '][operator]'" x-model="kriteria.operator" class="rounded-lg border-gray-200 text-sm">
                                            <option value="in" :selected="kriteria.operator === 'in'">Termasuk</option>
                                            <option value="not_in" :selected="kriteria.operator === 'not_in'">Tidak Termasuk</option>
                                        </select>
                                        <select :name="'sasaran[' + gi + '][kriteria][' + ki + '][value][]'" multiple x-model="kriteria.value" class="rounded-lg border-gray-200 text-sm sm:col-span-1">
                                            <template x-for="opt in optionsFor(kriteria.field)" :key="opt.value"><option :value="opt.value" x-text="opt.label"></option></template>
                                        </select>
                                        <button type="button" class="text-xs font-semibold text-error-600" @click="grup.kriteria.splice(ki, 1)">Hapus Kriteria</button>
                                    </div>
                                </template>
                                <button type="button" class="text-xs font-semibold text-brand-600" @click="grup.kriteria.push(newKriteria())">+ Tambah Kriteria</button>
                            </div>
                        </template>
                        <button type="button" class="text-sm font-semibold text-brand-600" @click="form.sasaran.push(newGrup())">+ Tambah Sasaran</button>
                    </div>
                </template>
            </div>

            <div x-show="!kategoriPpdb" x-cloak class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
                <p class="font-display text-sm font-bold text-gray-900">3. Tarif Berdimensi <span class="font-normal text-gray-400">(opsional)</span></p>
                <template x-for="(grup, gi) in form.tarif" :key="grup.uid">
                    <div class="rounded-xl border border-gray-200 p-4 space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold uppercase text-gray-500" x-text="'Tarif #' + (gi + 1)"></p>
                            <input type="number" step="0.01" min="0" :name="'tarif[' + gi + '][nominal]'" x-model="grup.nominal" placeholder="Nominal" class="w-40 rounded-lg border-gray-200 text-sm">
                            <button type="button" class="text-xs font-semibold text-error-600" @click="form.tarif.splice(gi, 1)">Hapus</button>
                        </div>
                        <template x-for="(kriteria, ki) in grup.kriteria" :key="kriteria.uid">
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-4">
                                <select :name="'tarif[' + gi + '][kriteria][' + ki + '][field]'" x-model="kriteria.field" class="rounded-lg border-gray-200 text-sm">
                                    <template x-for="fieldOpt in kriteriaFields" :key="fieldOpt"><option :value="fieldOpt" x-text="fieldOpt" :selected="fieldOpt === kriteria.field"></option></template>
                                </select>
                                <select :name="'tarif[' + gi + '][kriteria][' + ki + '][operator]'" x-model="kriteria.operator" class="rounded-lg border-gray-200 text-sm">
                                    <option value="in" :selected="kriteria.operator === 'in'">Termasuk</option>
                                    <option value="not_in" :selected="kriteria.operator === 'not_in'">Tidak Termasuk</option>
                                </select>
                                <select :name="'tarif[' + gi + '][kriteria][' + ki + '][value][]'" multiple x-model="kriteria.value" class="rounded-lg border-gray-200 text-sm sm:col-span-1">
                                    <template x-for="opt in optionsFor(kriteria.field)" :key="opt.value"><option :value="opt.value" x-text="opt.label"></option></template>
                                </select>
                                <button type="button" class="text-xs font-semibold text-error-600" @click="grup.kriteria.splice(ki, 1)">Hapus Kriteria</button>
                            </div>
                        </template>
                        <button type="button" class="text-xs font-semibold text-brand-600" @click="grup.kriteria.push(newKriteria())">+ Tambah Kriteria</button>
                    </div>
                </template>
                <button type="button" class="text-sm font-semibold text-brand-600" @click="form.tarif.push(newGrup())">+ Tambah Tarif</button>
            </div>
```

- [ ] **Step 4: Extend the `jenisTagihanForm()` Alpine factory** in the same file's `<script>` block — replace the `return { ... }` body with:

```js
function jenisTagihanForm(config) {
    let uidCounter = 0;
    const nextUid = () => ++uidCounter;

    return {
        kriteriaFields: ['lembaga', 'tahun_ajaran', 'tingkat', 'kelas', 'jenis_kelamin', 'status_siswa'],
        referenceOptions: config.referenceOptions,
        sasaranMode: config.initialSasaran.length > 0 ? 'kriteria' : 'semua',
        form: {
            kategori: config.kategoriAwal,
            mode: config.modeAwal,
            bisaDicicil: config.bisaDicicilAwal,
            sasaran: config.initialSasaran.map((g) => this.hydrateGrup(g)),
            tarif: config.initialTarif.map((g) => this.hydrateGrup(g)),
            keringanan: config.initialKeringanan.map((k) => ({ uid: nextUid(), ...k })),
        },
        kategoriKeringananOptions: config.kategoriKeringananList,
        get kategoriPpdb() {
            return ['pendaftaran', 'daftar_ulang'].includes(this.form.kategori);
        },
        hydrateGrup(grup) {
            return { uid: nextUid(), nominal: grup.nominal ?? '', kriteria: grup.kriteria.map((k) => ({ uid: nextUid(), ...k })) };
        },
        newKriteria() {
            return { uid: nextUid(), field: 'status_siswa', operator: 'in', value: [] };
        },
        newGrup() {
            return { uid: nextUid(), nominal: '', kriteria: [this.newKriteria()] };
        },
        optionsFor(field) {
            if (field === 'jenis_kelamin') return [{ value: 'L', label: 'Laki-laki' }, { value: 'P', label: 'Perempuan' }];
            if (field === 'status_siswa') return [{ value: 'aktif', label: 'Aktif' }, { value: 'lulus', label: 'Lulus' }, { value: 'pindah', label: 'Pindah' }, { value: 'keluar', label: 'Keluar' }];
            return this.referenceOptions[field] ?? [];
        },
    };
}
```

- [ ] **Step 5: Wire the new config keys into the `x-data` call on `<form>`**

```blade
            x-data="jenisTagihanForm({
                kategoriAwal: @js(old('kategori', $jenisTagihan?->kategori ?? 'lainnya')),
                modeAwal: @js(old('mode', $jenisTagihan?->mode ?? 'manual')),
                bisaDicicilAwal: @js((bool) old('bisa_dicicil', $jenisTagihan?->bisa_dicicil ?? false)),
                kategoriKeringananList: @js($kategoriKeringananList),
                referenceOptions: {
                    lembaga: @js($lembagaList->map(fn ($l) => ['value' => $l->id, 'label' => $l->nama])),
                    tahun_ajaran: @js($tahunAjaranList->map(fn ($t) => ['value' => $t->id, 'label' => $t->nama])),
                    tingkat: @js($tingkatList->map(fn ($t) => ['value' => $t, 'label' => $t])),
                    kelas: @js($kelasList->map(fn ($k) => ['value' => $k->id, 'label' => $k->nama])),
                },
                initialSasaran: @js(old('sasaran', $jenisTagihan?->sasaranGrup->where('tipe', 'sasaran')->map(fn ($g) => ['nominal' => null, 'kriteria' => $g->kriteria->map(fn ($k) => ['field' => $k->field, 'operator' => $k->operator, 'value' => $k->value])->values()->all()])->values()->all() ?? [])),
                initialTarif: @js(old('tarif', $jenisTagihan?->sasaranGrup->where('tipe', 'tarif')->map(fn ($g) => ['nominal' => $g->nominal, 'kriteria' => $g->kriteria->map(fn ($k) => ['field' => $k->field, 'operator' => $k->operator, 'value' => $k->value])->values()->all()])->values()->all() ?? [])),
                initialKeringanan: @js(old('keringanan', [])),
            })"
```

(Note: this replaces the simpler `x-data` call written in Task 3 — Task 3's version only had `kategoriAwal`/`kategoriKeringananList`; this step supersedes it with the full config. `initialKeringanan` defaults to `[]` here — Task 5 wires the edit-page prefill for keringanan rules.)

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/JenisTagihanSasaranFormTest.php`
Expected: PASS (2/2)

- [ ] **Step 7: Re-run Task 3's test to confirm no regression**

Run: `php artisan test tests/Feature/Admin/JenisTagihanFormPageTest.php`
Expected: PASS (2/2)

- [ ] **Step 8: Commit**

```bash
git add resources/views/admin/jenis-tagihan/form.blade.php tests/Feature/Admin/JenisTagihanSasaranFormTest.php
git commit -m "feat(keuangan): add target sasaran and tarif berdimensi sections to jenis-tagihan form"
```

---

### Task 5: Section 4 (Keringanan) + inline "+ Kategori Baru"

**Files:**
- Modify: `resources/views/admin/jenis-tagihan/form.blade.php` (replace the `<!-- Task 5: ... -->` placeholder; extend Alpine factory with keringanan methods + the fetch call to Task 2's endpoint)
- Test: `tests/Feature/Admin/JenisTagihanKeringananFormTest.php`

**Interfaces:**
- Consumes: `POST admin/kategori-keringanan` (Task 2) via `fetch()`.
- Produces: `keringanan` array on the shared `form` object, submitted as native nested inputs consumed by Task 1's `syncBillingConfig()`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Admin/JenisTagihanKeringananFormTest.php

use App\Models\JenisTagihan;
use App\Models\KategoriKeringanan;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('renders the keringanan section with existing kategori keringanan options', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    KategoriKeringanan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Yatim Piatu']);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.create'));

    $response->assertOk();
    $response->assertSee('4. Keringanan');
    $response->assertSee('Yatim Piatu');
});

it('pre-fills existing keringanan rules on the edit page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'SPP Bulanan', 'kategori' => 'spp', 'bisa_dicicil' => false]);
    $kategori = KategoriKeringanan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Yatim Piatu']);
    $jenisTagihan->keringananRules()->create(['kategori_keringanan_id' => $kategori->id, 'tipe_potongan' => 'persen', 'nilai' => 50]);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.edit', $jenisTagihan));

    $response->assertOk();
    $response->assertSee((string) $kategori->id, false);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/JenisTagihanKeringananFormTest.php`
Expected: FAIL (section doesn't exist yet)

- [ ] **Step 3: Replace the `<!-- Task 5: ... -->` placeholder** with:

```blade
            <div x-show="!kategoriPpdb" x-cloak class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
                <p class="font-display text-sm font-bold text-gray-900">4. Keringanan <span class="font-normal text-gray-400">(opsional)</span></p>
                <template x-for="(rule, ri) in form.keringanan" :key="rule.uid">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-5">
                        <select :name="'keringanan[' + ri + '][kategori_keringanan_id]'" x-model.number="rule.kategori_keringanan_id" class="rounded-lg border-gray-200 text-sm">
                            <template x-for="opt in kategoriKeringananOptions" :key="opt.id"><option :value="opt.id" x-text="opt.nama" :selected="opt.id === rule.kategori_keringanan_id"></option></template>
                        </select>
                        <select :name="'keringanan[' + ri + '][tipe_potongan]'" x-model="rule.tipe_potongan" class="rounded-lg border-gray-200 text-sm">
                            <option value="fixed" :selected="rule.tipe_potongan === 'fixed'">Nominal Tetap</option>
                            <option value="persen" :selected="rule.tipe_potongan === 'persen'">Persentase</option>
                        </select>
                        <input type="number" min="0" :max="rule.tipe_potongan === 'persen' ? 100 : null" step="0.01" :name="'keringanan[' + ri + '][nilai]'" x-model="rule.nilai" placeholder="Nilai" class="rounded-lg border-gray-200 text-sm">
                        <input type="text" :name="'keringanan[' + ri + '][keterangan]'" x-model="rule.keterangan" placeholder="Keterangan" class="rounded-lg border-gray-200 text-sm">
                        <button type="button" class="text-xs font-semibold text-error-600" @click="form.keringanan.splice(ri, 1)">Hapus</button>
                    </div>
                </template>
                <div class="flex items-center gap-3">
                    <button type="button" class="text-sm font-semibold text-brand-600" @click="form.keringanan.push(newKeringanan())">+ Tambah Keringanan</button>
                    <button type="button" class="text-sm font-semibold text-gray-500" @click="showKategoriBaru = true">+ Kategori Baru</button>
                </div>

                <div x-show="showKategoriBaru" x-cloak class="rounded-xl border border-dashed border-gray-300 p-4 space-y-2">
                    <x-input-label value="Nama Kategori Keringanan" />
                    <input type="text" x-model="kategoriBaruNama" class="w-full rounded-lg border-gray-200 text-sm" placeholder="mis. Prestasi Akademik">
                    <p class="text-sm text-error-600" x-show="kategoriBaruError" x-text="kategoriBaruError"></p>
                    <div class="flex gap-2">
                        <x-secondary-button type="button" x-bind:disabled="kategoriBaruSubmitting" @click="submitKategoriBaru()">Simpan Kategori</x-secondary-button>
                        <x-secondary-button type="button" @click="showKategoriBaru = false">Batal</x-secondary-button>
                    </div>
                </div>
            </div>
```

- [ ] **Step 4: Extend the Alpine factory** — add these keys to the returned object (alongside the ones from Task 4) and update `initialKeringanan` hydration:

```js
        showKategoriBaru: false,
        kategoriBaruNama: '',
        kategoriBaruError: '',
        kategoriBaruSubmitting: false,
        newKeringanan() {
            return { uid: nextUid(), kategori_keringanan_id: this.kategoriKeringananOptions[0]?.id ?? null, tipe_potongan: 'fixed', nilai: '', keterangan: '' };
        },
        async submitKategoriBaru() {
            this.kategoriBaruSubmitting = true;
            this.kategoriBaruError = '';
            try {
                const response = await fetch(config.kategoriKeringananStoreUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ nama: this.kategoriBaruNama }),
                });
                const json = await response.json();
                if (!response.ok) {
                    this.kategoriBaruError = json.message ?? 'Gagal menambah kategori.';
                    return;
                }
                this.kategoriKeringananOptions.push(json.data);
                this.kategoriBaruNama = '';
                this.showKategoriBaru = false;
            } catch (error) {
                this.kategoriBaruError = 'Gagal menambah kategori.';
            } finally {
                this.kategoriBaruSubmitting = false;
            }
        },
```

- [ ] **Step 5: Wire `kategoriKeringananStoreUrl` and `initialKeringanan` into the `x-data` config** — update the `x-data="jenisTagihanForm({...})"` call from Task 4:

```blade
                kategoriKeringananStoreUrl: @js(route('admin.kategori-keringanan.store')),
                initialKeringanan: @js(old('keringanan', $jenisTagihan?->keringananRules->map(fn ($r) => ['kategori_keringanan_id' => $r->kategori_keringanan_id, 'tipe_potongan' => $r->tipe_potongan, 'nilai' => (float) $r->nilai, 'keterangan' => $r->keterangan])->values()->all() ?? [])),
```

(Replaces the `initialKeringanan: @js(old('keringanan', []))` line Task 4 wrote — this is the real edit-page prefill.)

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/JenisTagihanKeringananFormTest.php`
Expected: PASS (2/2)

- [ ] **Step 7: Re-run Task 3 and Task 4 tests to confirm no regression**

Run: `php artisan test tests/Feature/Admin/JenisTagihanFormPageTest.php tests/Feature/Admin/JenisTagihanSasaranFormTest.php`
Expected: PASS (4/4)

- [ ] **Step 8: Commit**

```bash
git add resources/views/admin/jenis-tagihan/form.blade.php tests/Feature/Admin/JenisTagihanKeringananFormTest.php
git commit -m "feat(keuangan): add keringanan section with inline kategori-baru creation"
```

---

### Task 6: Simplify the index page — wire to create/edit pages, drop the inline form

**Files:**
- Modify: `resources/views/admin/jenis-tagihan/index.blade.php`
- Modify: `resources/js/jenis-tagihan-table.js`
- Test: `tests/Feature/Admin/JenisTagihanTest.php` (existing — verify it still passes; no new test file, this task is a UI simplification with no new backend behavior)

**Interfaces:**
- Consumes: `route('admin.jenis-tagihan.create')`, `route('admin.jenis-tagihan.edit', $item)` (Task 1).
- Produces: nothing new consumed elsewhere — this is the final integration point.

- [ ] **Step 1: Rewrite `resources/views/admin/jenis-tagihan/index.blade.php`**

```blade
<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Jenis Tagihan</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Jenis Tagihan</b>
            </p>
        </div>

        <div
            x-data="jenisTagihanTable({
                initialItems: @js($jenisTagihanList),
                deleteUrlTemplate: @js(route('admin.jenis-tagihan.destroy', ['jenisTagihan' => '__ID__'])),
                nominalUrlTemplate: @js(route('admin.jenis-tagihan.nominal', ['jenisTagihan' => '__ID__'])),
                editUrlTemplate: @js(route('admin.jenis-tagihan.edit', ['jenisTagihan' => '__ID__'])),
            })"
            class="space-y-5"
        >
            @can('jenis-tagihan.create')
                <div class="flex justify-end">
                    <a href="{{ route('admin.jenis-tagihan.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600">
                        <x-icon name="plus" class="h-4 w-4" /> Tambah Jenis Tagihan
                    </a>
                </div>
            @endcan

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
                <div class="border-b border-gray-200 px-5 py-4">
                    <p class="font-display text-sm font-bold text-gray-900">Daftar Jenis Tagihan</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                <th class="sticky left-0 z-10 bg-white px-5 py-3">Aksi</th>
                                <th class="px-5 py-3">Nama</th>
                                <th class="px-5 py-3">Kategori</th>
                                <th class="px-5 py-3">Cicilan</th>
                                <th class="px-5 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-if="items.length === 0">
                                <tr><td colspan="5" class="px-5 py-6 text-center text-sm text-gray-500">Belum ada jenis tagihan.</td></tr>
                            </template>
                            <template x-for="item in items" :key="item.id">
                                <tr class="transition hover:bg-gray-50">
                                    <td class="sticky left-0 z-10 bg-white px-5 py-3">
                                        <x-table-actions>
                                            @can('jenis-tagihan.edit')
                                                <x-dropdown-link x-bind:href="editUrl(item)">Edit</x-dropdown-link>
                                                <x-dropdown-link x-bind:href="nominalUrl(item)">Kelola Nominal</x-dropdown-link>
                                            @endcan
                                            @can('jenis-tagihan.delete')
                                                <x-dropdown-link href="#" @click.prevent="deleteItem(item)" class="text-error-600">Hapus</x-dropdown-link>
                                            @endcan
                                        </x-table-actions>
                                    </td>
                                    <td class="px-5 py-3.5 font-semibold text-gray-900" x-text="item.nama"></td>
                                    <td class="px-5 py-3.5 text-gray-600" x-text="item.kategori"></td>
                                    <td class="px-5 py-3.5 text-gray-600" x-text="item.bisa_dicicil ? 'Maks ' + item.maks_cicilan + 'x' : 'Tidak dicicil'"></td>
                                    <td class="px-5 py-3.5">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                            :class="item.tagihan_item_count > 0 ? 'bg-brand-50 text-brand-600' : (item.nominal_jalur_count > 0 ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600')"
                                            x-text="item.tagihan_item_count > 0 ? 'Dipakai di ' + item.tagihan_item_count + ' Tagihan' : (item.nominal_jalur_count > 0 ? item.nominal_jalur_count + ' Nominal Dikonfigurasi' : 'Belum Dipakai')"
                                        ></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 2: Rewrite `resources/js/jenis-tagihan-table.js`** (drop `startEdit`/`cancelEdit`/`submit` — the form is gone from this page; keep `deleteItem`, add `editUrl`)

```js
export function jenisTagihanTable(config) {
    return {
        items: config.initialItems,
        deleteUrlTemplate: config.deleteUrlTemplate,
        nominalUrlTemplate: config.nominalUrlTemplate,
        editUrlTemplate: config.editUrlTemplate,

        nominalUrl(item) {
            return this.nominalUrlTemplate.replace('__ID__', item.id);
        },

        editUrl(item) {
            return this.editUrlTemplate.replace('__ID__', item.id);
        },

        async deleteItem(item) {
            const confirmed = await confirmDialog('Hapus Jenis Tagihan?', `Apakah Anda yakin ingin menghapus "${item.nama}"?`);
            if (!confirmed) {
                return;
            }

            try {
                const response = await fetch(this.deleteUrlTemplate.replace('__ID__', item.id), {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });

                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menghapus jenis tagihan.');
                    return;
                }

                this.items = this.items.filter((existing) => existing.id !== item.id);
                Alpine.store('toast').push('success', json.message ?? 'Jenis tagihan berhasil dihapus.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menghapus jenis tagihan.');
            }
        },
    };
}
```

- [ ] **Step 3: Run the existing regression test**

Run: `php artisan test tests/Feature/Admin/JenisTagihanTest.php`
Expected: PASS (21/21 — this test file exercises `store`/`update`/`destroy`/`index` via HTTP, none of which changed contract, only the index view/JS changed)

- [ ] **Step 4: Manual verification (no automated browser test in this project — Dusk not installed)**

Run `npm run dev` (or confirm `npm run build` if the dev server isn't already running), log in as an `admin_keuangan` user, and manually verify in a browser:
- Index page: "+ Tambah Jenis Tagihan" navigates to the create page.
- Create page: selecting kategori "SPP" reveals Mode/Sasaran/Tarif/Keringanan; selecting "Pendaftaran" hides them.
- Add a Sasaran group with 1 kriteria, a Tarif group with 1 kriteria + nominal, a Keringanan rule using "+ Kategori Baru" — submit, confirm redirect to index and the new row appears.
- Edit that same jenis_tagihan — confirm all 3 sections are pre-filled with what was just saved.
- Index page: "Kelola Nominal" and "Hapus" still work for a Pendaftaran-kategori row (unchanged regression check).

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/jenis-tagihan/index.blade.php resources/js/jenis-tagihan-table.js
git commit -m "feat(keuangan): wire jenis-tagihan index to the new create/edit pages"
```

---

### Task 7: Full regression verification

**Files:** none (verification-only task)

- [ ] **Step 1: Run every test file touched or exercised by this plan**

```bash
php artisan test tests/Feature/Admin/JenisTagihanFormTest.php tests/Feature/Admin/KategoriKeringananTest.php tests/Feature/Admin/JenisTagihanFormPageTest.php tests/Feature/Admin/JenisTagihanSasaranFormTest.php tests/Feature/Admin/JenisTagihanKeringananFormTest.php tests/Feature/Admin/JenisTagihanTest.php
```
Expected: all PASS, no failures.

- [ ] **Step 2: Run the full Keuangan-related suite to confirm Sub-project 2a is untouched**

```bash
php artisan test tests/Feature/Keuangan/
```
Expected: 55 passed (matches the count verified after Sub-project 2a merged).

- [ ] **Step 3: Run the full project suite** (single foreground run — do not run this in the background or concurrently with any other `php artisan test` process, per the Sub-project 2a lesson about shared-test-DB corruption)

```bash
php artisan test
```
Expected: same pass/fail count as the `demo` baseline (1378 passed / 6 pre-existing unrelated failures — `LembagaCrudTest`, `RoleBuilderTest` x4, `RoleFormAuditBannerTest` — confirmed pre-existing on `demo` itself in the Sub-project 2a handoff). Any NEW failure beyond this baseline is a real regression from this plan and must be fixed before moving on.

- [ ] **Step 4: Write the handoff log**

Per `AGENTS.md` Stage 7, write `.agents/logs/keuangan-02b1-form-jenis-tagihan.md` covering what was built, the kategori-branching decision, the plain-form-POST-over-AJAX decision (with the `nominal.blade.php` precedent cited), the replace-all-on-save safety verification, and current git state.
