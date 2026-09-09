# Perbaikan Scope Lembaga Menu TP (Komponen Penilaian) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Tutup kebocoran/ambiguitas scope lembaga di menu Admin TP (Komponen Penilaian) untuk aktor yayasan mode "Semua Lembaga" — guard "Tambah TP" supaya wajib sudah switch ke 1 lembaga, perbaiki error handling, default filter, dan label lembaga di berbagai tampilan.

**Architecture:** Semua perubahan terkonsentrasi di `KomponenPenilaianController` (Admin) + 2 view (`_daftar.blade.php`, `edit.blade.php`). Tidak ada perubahan skema database, Action, DTO, atau FormRequest. Fondasi teknis yang dipakai berulang: `ResolveLembagaScopeTrait::resolveActiveLembagaId()` (sudah `use` di controller ini) dan `TenantScope` (global scope otomatis dari `BelongsToTenant`) — begitu `active_lembaga_id` tervalidasi terisi, seluruh query Eloquent yang memakai `BelongsToTenant` otomatis terbatas ke 1 lembaga tanpa perlu filter manual tambahan.

**Tech Stack:** Laravel 12, Pest, Blade, Eloquent (tanpa Livewire/Inertia — server-rendered + sedikit Alpine.js untuk partial existing yang TIDAK disentuh plan ini).

## Global Constraints

- Guard di Item A (Task 1) WAJIB pakai `$this->resolveActiveLembagaId($request->user())` (trait `ResolveLembagaScopeTrait`, SUDAH `use` di `KomponenPenilaianController`) — BUKAN raw `session('active_lembaga_id')`. Method ini mengembalikan `null` kalau lembaga di session ternyata bukan milik yayasan aktor, bukan `abort()`.
- Setelah guard Item A lolos, JANGAN tambahkan `where('lembaga_id', ...)` manual di query mana pun di `create()`/`store()` — `TenantScope` (dari `BelongsToTenant`, sudah dipakai `MataPelajaran`, `TahunAjaran`, `Semester`) otomatis benar begitu `active_lembaga_id` tervalidasi terisi. Menambah filter manual di sini adalah over-engineering, akan ditolak saat review.
- Test file SATU-SATUNYA untuk seluruh plan ini: `tests/Feature/Admin/KomponenPenilaianCrudTest.php`. Pakai HANYA 2 helper existing di file itu — `actingAsKomponenManager(Lembaga $lembaga): User` (lembaga-scope, role `operator_akademik`) dan `actingAsYayasanKomponenManager(Yayasan $yayasan): User` (yayasan-scope, role `yayasan_admin_komponen`). JANGAN buat helper baru.
- Untuk mensimulasikan aktor yayasan yang SUDAH switch ke 1 lembaga di test: `session(['active_lembaga_id' => $lembaga->id]);` SETELAH memanggil `actingAsYayasanKomponenManager()`, SEBELUM request (pola persis dari test existing `it('shows the active lembaga badge for a yayasan-scoped actor who has switched into a lembaga', ...)`).
- Jalur Guru (`Guru\KomponenPenilaianController`, `UpdateKomponenPenilaianSendiriRequest`, view `portals/guru/...`) TIDAK disentuh SAMA SEKALI di plan ini — guru selalu lembaga-scope tunggal, tidak pernah mengalami kondisi "mode agregat".
- Tidak pakai worktree, tidak pindah branch (tetap di branch aktif saat ini). TIDAK ADA migrasi database, TIDAK ADA perubahan Action/DTO/FormRequest.
- Item G dari spec (filter Mata Pelajaran di `index()` tetap flat) SENGAJA TIDAK dapat task — itu keputusan didokumentasikan, bukan pekerjaan yang tertinggal.

---

### Task 1: 🔴 Guard "Tambah TP" — Wajib Sudah Switch ke 1 Lembaga (Item A)

**Files:**
- Modify: `app/Http/Controllers/Admin/KomponenPenilaianController.php`
- Modify: `resources/views/portals/lembaga/akademik/komponen-penilaian/_daftar.blade.php`
- Test: `tests/Feature/Admin/KomponenPenilaianCrudTest.php`

**Interfaces:**
- Tidak ada interface baru — perubahan murni guard di method existing `create()`/`store()`.

- [x] **Step 1: Tulis test yang gagal**

Tambahkan ke akhir `tests/Feature/Admin/KomponenPenilaianCrudTest.php`:

```php
it('blocks a yayasan actor from opening Tambah TP when no lembaga is active (aggregate mode)', function () {
    $yayasan = Yayasan::factory()->create();
    $manager = actingAsYayasanKomponenManager($yayasan);

    $this->actingAs($manager)->get(route('admin.komponen-penilaian.create'))
        ->assertStatus(422);
});

it('blocks a yayasan actor from submitting store() when no lembaga is active (aggregate mode)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsYayasanKomponenManager($yayasan);

    $this->actingAs($manager)->post(route('admin.komponen-penilaian.store'), [
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id,
        'semester_id' => $semester->id,
        'kode' => 'TP-BLOCKED',
        'deskripsi' => 'Tidak boleh tersimpan',
        'bobot' => 100,
    ])->assertStatus(422);

    expect(KomponenPenilaian::where('kode', 'TP-BLOCKED')->exists())->toBeFalse();
});

it('allows a yayasan actor to open and submit Tambah TP once switched into a lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsYayasanKomponenManager($yayasan);
    session(['active_lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->get(route('admin.komponen-penilaian.create'))->assertOk();

    $this->actingAs($manager)->post(route('admin.komponen-penilaian.store'), [
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id,
        'semester_id' => $semester->id,
        'kode' => 'TP-ALLOWED',
        'deskripsi' => 'Harus tersimpan',
        'bobot' => 100,
    ])->assertRedirect(route('admin.komponen-penilaian.index'));

    expect(KomponenPenilaian::where('kode', 'TP-ALLOWED')->exists())->toBeTrue();
});

it('does not affect a lembaga-scoped actor at all when accessing Tambah TP', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKomponenManager($lembaga);

    $this->actingAs($manager)->get(route('admin.komponen-penilaian.create'))->assertOk();

    $this->actingAs($manager)->post(route('admin.komponen-penilaian.store'), [
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id,
        'semester_id' => $semester->id,
        'kode' => 'TP-LEMBAGA-SCOPE',
        'deskripsi' => 'Aktor lembaga-scope tidak terpengaruh guard',
        'bobot' => 100,
    ])->assertRedirect(route('admin.komponen-penilaian.index'));

    expect(KomponenPenilaian::where('kode', 'TP-LEMBAGA-SCOPE')->exists())->toBeTrue();
});

it('hides the Tambah TP button for a yayasan actor in aggregate mode, with an explanatory message', function () {
    $yayasan = Yayasan::factory()->create();
    $manager = actingAsYayasanKomponenManager($yayasan);

    $this->actingAs($manager)->get(route('admin.komponen-penilaian.index'))
        ->assertDontSee('Tambah TP Baru')
        ->assertDontSee('Tambah TP Pertama')
        ->assertSee('Pilih 1 lembaga lewat pengalih di topbar untuk mulai menambah TP.');
});

it('shows the Tambah TP button for a yayasan actor once switched into a lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsYayasanKomponenManager($yayasan);
    session(['active_lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->get(route('admin.komponen-penilaian.index'))
        ->assertSee('Tambah TP Pertama');
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="blocks a yayasan actor|allows a yayasan actor to open and submit|does not affect a lembaga-scoped actor|hides the Tambah TP button|shows the Tambah TP button" --compact`
Expected: test "blocks..." (2 test pertama) FAIL (saat ini `create()`/`store()` TIDAK punya guard, jadi malah `assertOk()`/berhasil, bukan 422). Test "hides the Tambah TP button..." FAIL (tombol saat ini SELALU muncul). Test "allows...", "does not affect...", "shows the Tambah TP button..." kemungkinan SUDAH PASS (perilaku itu belum berubah) — itu wajar, jadi baseline regresi untuk langkah berikutnya.

- [x] **Step 3: Implementasi guard di controller**

Di `app/Http/Controllers/Admin/KomponenPenilaianController.php`, method `create()` (baris 100-117) — sisipkan blok guard TEPAT SETELAH baris `$this->authorize('komponen-penilaian.kelola');`, SEBELUM baris `$tahunAjaranId = old('tahun_ajaran_id', $request->query('tahun_ajaran_id'));`. Baris-baris lain di method ini TIDAK berubah:

```php
public function create(Request $request): View
{
    $this->authorize('komponen-penilaian.kelola');

    if ($request->user()->widestScopeLevel() === 'yayasan') {
        abort_if($this->resolveActiveLembagaId($request->user()) === null, 422, 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah TP.');
    }

    $tahunAjaranId = old('tahun_ajaran_id', $request->query('tahun_ajaran_id'));
    if (! $tahunAjaranId) {
        $tahunAjaranId = TahunAjaran::where('status_aktif', true)->value('id');
    }

    return view('portals.lembaga.akademik.komponen-penilaian.create', [
        'tahunAjaranList' => TahunAjaran::orderByDesc('id')->get(),
        'tahunAjaranId' => $tahunAjaranId,
        'semesterList' => $tahunAjaranId ? Semester::where('tahun_ajaran_id', $tahunAjaranId)->orderByDesc('id')->get() : collect(),
        'mataPelajaranList' => MataPelajaran::orderBy('nama')->get(),
        'elemenCpList' => ElemenCp::orderBy('no_urut')->get(),
        'isPaud' => BentukPendidikan::tryFrom($request->user()->lembaga?->bentuk_pendidikan ?? '')?->isPaud() ?? false,
    ]);
}
```

Method `store()` (baris 119-149) — sisipkan blok guard yang SAMA (pesan identik) sebagai baris PALING PERTAMA di dalam method, SEBELUM baris `$data = $request->validated();`. Baris-baris lain di method ini TIDAK berubah:

```php
public function store(StoreKomponenPenilaianRequest $request): RedirectResponse|JsonResponse
{
    if ($request->user()->widestScopeLevel() === 'yayasan') {
        abort_if($this->resolveActiveLembagaId($request->user()) === null, 422, 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah TP.');
    }

    $data = $request->validated();

    $subjek = match ($data['subjek_type']) {
        'mata_pelajaran' => MataPelajaran::withoutGlobalScopes()->find($data['subjek_id']),
        'elemen_cp' => ElemenCp::find($data['subjek_id']),
    };
    $semester = Semester::find($data['semester_id']);
    abort_if($subjek === null || $semester === null, 404);
    if ($data['subjek_type'] === 'mata_pelajaran') {
        abort_if($subjek->lembaga_id !== $semester->lembaga_id, 404);
    }

    try {
        $this->createKomponenPenilaianAction->execute($request->toDTO());
    } catch (ValidationException $e) {
        $msg = collect($e->errors())->collapse()->first();
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'error', 'message' => $msg], 422);
        }

        return back()->withInput()->withErrors($e->errors());
    }

    if ($request->ajax() || $request->wantsJson()) {
        return response()->json(['status' => 'success', 'message' => 'Komponen penilaian (TP) berhasil disimpan.']);
    }

    return redirect()->route('admin.komponen-penilaian.index')->with('status', 'Komponen penilaian (TP) berhasil disimpan.');
}
```

**Catatan**: `abort_if($subjek === null || ..., 404)` dan cross-check lembaga di atas TETAP APA ADANYA di Task ini — perubahannya khusus untuk Task 2 (Item B), JANGAN diubah sekarang, supaya diff Task 1 tetap fokus 1 hal.

- [x] **Step 4: Sembunyikan tombol Tambah TP di partial**

Di `resources/views/portals/lembaga/akademik/komponen-penilaian/_daftar.blade.php`, ganti (baris 84-92):

```blade
        <div class="flex flex-wrap items-center justify-between border-b border-gray-100 bg-white px-6 py-4 gap-3">
            <p class="font-display text-sm font-bold text-gray-900">Daftar Komponen &amp; Tujuan Pembelajaran</p>
            <div class="flex items-center gap-2">
                <x-badge tone="brand" class="text-xs font-semibold px-2.5 py-0.5">{{ $komponenList->count() }} Data</x-badge>
                <x-link-button href="{{ route('admin.komponen-penilaian.create') }}">
                    <span class="text-base leading-none mr-1.5">+</span> Tambah TP Baru
                </x-link-button>
            </div>
        </div>
```

menjadi:

```blade
        <div class="flex flex-wrap items-center justify-between border-b border-gray-100 bg-white px-6 py-4 gap-3">
            <p class="font-display text-sm font-bold text-gray-900">Daftar Komponen &amp; Tujuan Pembelajaran</p>
            <div class="flex items-center gap-2">
                <x-badge tone="brand" class="text-xs font-semibold px-2.5 py-0.5">{{ $komponenList->count() }} Data</x-badge>
                @if (! ($isYayasan ?? false) || ($activeLembaga ?? null))
                    <x-link-button href="{{ route('admin.komponen-penilaian.create') }}">
                        <span class="text-base leading-none mr-1.5">+</span> Tambah TP Baru
                    </x-link-button>
                @endif
            </div>
        </div>
```

Dan ganti blok `@empty` (baris 136-148):

```blade
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
```

menjadi:

```blade
            @empty
                <div class="py-12 text-center text-gray-400 space-y-3">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                        <x-icon name="checklist" class="h-7 w-7" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-700">Belum Ada Tujuan Pembelajaran</p>
                        <p class="text-xs text-gray-400 max-w-sm mx-auto mt-0.5">Tambahkan Tujuan Pembelajaran (TP) untuk mempermudah guru merujuk indikator penilaian saat menginput nilai asesmen.</p>
                    </div>
                    @if (! ($isYayasan ?? false) || ($activeLembaga ?? null))
                        <x-link-button href="{{ route('admin.komponen-penilaian.create') }}" class="inline-flex justify-center">
                            <span class="text-base leading-none mr-1.5">+</span> Tambah TP Pertama
                        </x-link-button>
                    @else
                        <p class="text-xs text-gray-400 max-w-sm mx-auto mt-2">Pilih 1 lembaga lewat pengalih di topbar untuk mulai menambah TP.</p>
                    @endif
                </div>
            @endforelse
```

**PENTING — prasyarat `$isYayasan`/`$activeLembaga` di partial ini**: `_daftar.blade.php` SAAT INI, untuk cabang render AJAX (`index()` baris 69-71), belum menerima variabel `isYayasan`/`activeLembaga` sama sekali — HANYA cabang halaman-penuh yang mengirimnya. Task 1 ini ditulis dan test-nya HANYA menguji lewat `route('admin.komponen-penilaian.index')` GET biasa (bukan `->ajax()`), jadi test di atas TETAP LULUS memakai jalur halaman-penuh yang sudah punya variabel itu. Task 4 (Item D) akan mengirim `scopeHeaderData()` juga ke cabang AJAX — TIDAK perlu diantisipasi di Task 1 ini.

- [x] **Step 5: Jalankan semua test Step 1, pastikan lulus**

Run: `php artisan test --filter="blocks a yayasan actor|allows a yayasan actor to open and submit|does not affect a lembaga-scoped actor|hides the Tambah TP button|shows the Tambah TP button" --compact`
Expected: PASS semua 6 test.

- [x] **Step 6: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php --compact`
Expected: PASS semua (test existing termasuk yang sudah membuat TP lewat `actingAsKomponenManager` — pastikan TIDAK ADA yang tiba-tiba gagal karena guard baru salah sasaran ke aktor lembaga-scope).

- [x] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/KomponenPenilaianController.php resources/views/portals/lembaga/akademik/komponen-penilaian/_daftar.blade.php tests/Feature/Admin/KomponenPenilaianCrudTest.php
git commit -m "fix(komponen-penilaian): guard Tambah TP -- wajib aktor yayasan sudah switch ke 1 lembaga"
```

---

### Task 2: 🟡 `store()` Gagal dengan Pesan Validasi, Bukan 404 Kosong (Item B)

**Files:**
- Modify: `app/Http/Controllers/Admin/KomponenPenilaianController.php`
- Test: `tests/Feature/Admin/KomponenPenilaianCrudTest.php`

**Interfaces:**
- Tidak ada interface baru.

- [x] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('returns a validation error with preserved input instead of a blank 404 when subjek and semester belong to different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $semesterB = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id]);
    $mapelA = MataPelajaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $manager = actingAsYayasanKomponenManager($yayasan);
    session(['active_lembaga_id' => $lembagaA->id]);

    $response = $this->actingAs($manager)->post(route('admin.komponen-penilaian.store'), [
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapelA->id,
        'semester_id' => $semesterB->id,
        'kode' => 'TP-MISMATCH',
        'deskripsi' => 'Deskripsi yang harus tetap ada di form setelah gagal',
        'bobot' => 100,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('subjek_id');
    $response->assertSessionHas('_old_input.deskripsi', 'Deskripsi yang harus tetap ada di form setelah gagal');
    expect(KomponenPenilaian::where('kode', 'TP-MISMATCH')->exists())->toBeFalse();
});

it('returns a validation error instead of a blank 404 when subjek_id does not exist at all', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $manager = actingAsKomponenManager($lembaga);

    $response = $this->actingAs($manager)->post(route('admin.komponen-penilaian.store'), [
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => 999999,
        'semester_id' => $semester->id,
        'kode' => 'TP-NOTFOUND',
        'deskripsi' => 'Subjek tidak pernah ada',
        'bobot' => 100,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('subjek_id');
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="returns a validation error with preserved input|returns a validation error instead of a blank 404" --compact`
Expected: FAIL keduanya (saat ini `abort_if(..., 404)` menghasilkan response 404 murni, `assertRedirect()` gagal, `assertSessionHasErrors()` tidak menemukan apa-apa di session).

**Catatan**: test pertama TIDAK akan pernah GAGAL akibat guard Task 1 (aktor sudah switch ke `$lembagaA`, jadi guard lolos) — test ini murni untuk membuktikan skenario mismatch lembaga (defense-in-depth) yang MASIH bisa terjadi lewat payload mentah SETELAH guard Task 1 ada, karena guard Task 1 hanya menjamin AKTOR sudah pilih 1 lembaga, TIDAK menjamin `subjek_id`/`semester_id` yang dikirim konsisten satu sama lain (mis. lembaga sengaja mengirim `semester_id` lembaga lain lewat request mentah/tools seperti Postman).

- [x] **Step 3: Implementasi**

Di `app/Http/Controllers/Admin/KomponenPenilaianController.php`, method `store()` — ganti blok cross-check (persis setelah baris guard Task 1, sebelum blok `try { $this->createKomponenPenilaianAction->execute(...) }`):

```php
$subjek = match ($data['subjek_type']) {
    'mata_pelajaran' => MataPelajaran::withoutGlobalScopes()->find($data['subjek_id']),
    'elemen_cp' => ElemenCp::find($data['subjek_id']),
};
$semester = Semester::find($data['semester_id']);
abort_if($subjek === null || $semester === null, 404);
if ($data['subjek_type'] === 'mata_pelajaran') {
    abort_if($subjek->lembaga_id !== $semester->lembaga_id, 404);
}
```

menjadi:

```php
$subjek = match ($data['subjek_type']) {
    'mata_pelajaran' => MataPelajaran::withoutGlobalScopes()->find($data['subjek_id']),
    'elemen_cp' => ElemenCp::find($data['subjek_id']),
};
$semester = Semester::find($data['semester_id']);
if ($subjek === null || $semester === null) {
    return back()->withInput()->withErrors(['subjek_id' => 'Subjek Penilaian atau Semester yang dipilih tidak valid.']);
}
if ($data['subjek_type'] === 'mata_pelajaran' && $subjek->lembaga_id !== $semester->lembaga_id) {
    return back()->withInput()->withErrors(['subjek_id' => 'Mata Pelajaran dan Semester yang dipilih berasal dari lembaga yang berbeda.']);
}
```

- [x] **Step 4: Jalankan test Step 1 lagi, pastikan lulus**

Run: `php artisan test --filter="returns a validation error with preserved input|returns a validation error instead of a blank 404" --compact`
Expected: PASS keduanya.

- [x] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php --compact`
Expected: PASS semua.

- [x] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/KomponenPenilaianController.php tests/Feature/Admin/KomponenPenilaianCrudTest.php
git commit -m "fix(komponen-penilaian): store() kembalikan pesan validasi (bukan 404 kosong) saat subjek/semester mismatch lembaga"
```

---

### Task 3: 🟡 Default Tahun Ajaran di `index()` Tidak Lagi Ambigu Saat Mode Agregat (Item C)

**Files:**
- Modify: `app/Http/Controllers/Admin/KomponenPenilaianController.php`
- Test: `tests/Feature/Admin/KomponenPenilaianCrudTest.php`

**Interfaces:**
- Tidak ada interface baru.

- [x] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('does not silently narrow to one lembaga\'s tahun ajaran when a yayasan actor in aggregate mode opens index() with no query string', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id, 'status_aktif' => true]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id, 'status_aktif' => true]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $semesterB = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id]);
    $mapelA = MataPelajaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $mapelB = MataPelajaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapelA->id, 'semester_id' => $semesterA->id, 'kode' => 'TP-LEMBAGA-A']);
    KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapelB->id, 'semester_id' => $semesterB->id, 'kode' => 'TP-LEMBAGA-B']);
    $manager = actingAsYayasanKomponenManager($yayasan);

    $this->actingAs($manager)->get(route('admin.komponen-penilaian.index'))
        ->assertSee('TP-LEMBAGA-A')
        ->assertSee('TP-LEMBAGA-B');
});

it('still defaults to the active tahun ajaran for a lembaga-scoped actor (regresi)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranAktif = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $tahunAjaranLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => false]);
    $semesterAktif = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranAktif->id]);
    $semesterLama = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranLama->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semesterAktif->id, 'kode' => 'TP-TAHUN-AKTIF']);
    KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semesterLama->id, 'kode' => 'TP-TAHUN-LAMA']);
    $manager = actingAsKomponenManager($lembaga);

    $this->actingAs($manager)->get(route('admin.komponen-penilaian.index'))
        ->assertSee('TP-TAHUN-AKTIF')
        ->assertDontSee('TP-TAHUN-LAMA');
});

it('still defaults to the active tahun ajaran for a yayasan actor who has switched into a lembaga (regresi)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranAktif = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $tahunAjaranLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => false]);
    $semesterAktif = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranAktif->id]);
    $semesterLama = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranLama->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semesterAktif->id, 'kode' => 'TP-YAYASAN-AKTIF']);
    KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semesterLama->id, 'kode' => 'TP-YAYASAN-LAMA']);
    $manager = actingAsYayasanKomponenManager($yayasan);
    session(['active_lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->get(route('admin.komponen-penilaian.index'))
        ->assertSee('TP-YAYASAN-AKTIF')
        ->assertDontSee('TP-YAYASAN-LAMA');
});
```

- [x] **Step 2: Jalankan test, pastikan test pertama gagal, 2 test regresi lulus**

Run: `php artisan test --filter="does not silently narrow to one lembaga|still defaults to the active tahun ajaran" --compact`
Expected: test "does not silently narrow..." FAIL (saat ini hanya salah satu dari `TP-LEMBAGA-A`/`TP-LEMBAGA-B` yang muncul, tergantung urutan baris DB — `assertSee` untuk yang tidak muncul akan gagal). 2 test "still defaults..." SUDAH PASS (baseline, perilaku itu belum diubah — konfirmasi paham kondisi awal sebelum lanjut).

- [x] **Step 3: Implementasi**

Di `app/Http/Controllers/Admin/KomponenPenilaianController.php`, method `index()` — ganti (baris 52-55):

```php
$tahunAjaranId = $request->query('tahun_ajaran_id');
if ($tahunAjaranId === null && ! $request->query->has('tahun_ajaran_id')) {
    $tahunAjaranId = TahunAjaran::where('status_aktif', true)->value('id');
}
```

menjadi:

```php
$isYayasanAggregate = $request->user()->widestScopeLevel() === 'yayasan' && $this->resolveActiveLembagaId($request->user()) === null;

$tahunAjaranId = $request->query('tahun_ajaran_id');
if ($tahunAjaranId === null && ! $request->query->has('tahun_ajaran_id') && ! $isYayasanAggregate) {
    $tahunAjaranId = TahunAjaran::where('status_aktif', true)->value('id');
}
```

- [x] **Step 4: Jalankan test Step 1 lagi, pastikan semua lulus**

Run: `php artisan test --filter="does not silently narrow to one lembaga|still defaults to the active tahun ajaran" --compact`
Expected: PASS ketiganya.

- [x] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php --compact`
Expected: PASS semua.

- [x] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/KomponenPenilaianController.php tests/Feature/Admin/KomponenPenilaianCrudTest.php
git commit -m "fix(komponen-penilaian): index() tidak lagi auto-pilih 1 lembaga acak sbg default tahun ajaran saat aktor yayasan mode agregat"
```

---

### Task 4: 🟡 Label Lembaga di Baris Daftar & Kartu Kalkulator Bobot (Item D)

**Files:**
- Modify: `app/Http/Controllers/Admin/KomponenPenilaianController.php`
- Modify: `resources/views/portals/lembaga/akademik/komponen-penilaian/_daftar.blade.php`
- Test: `tests/Feature/Admin/KomponenPenilaianCrudTest.php`

**Interfaces:**
- Produces: cabang AJAX `index()` (`_daftar.blade.php` di-render ulang lewat `route('admin.komponen-penilaian.index')` dengan header `X-Requested-With: XMLHttpRequest`) SEKARANG JUGA menerima `isYayasan`/`activeLembaga` (sebelumnya HANYA cabang halaman-penuh).

- [x] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('shows a lembaga label on each TP row and weight-calculator card for a yayasan actor in aggregate mode', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SMP Cendekia Bangsa']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'kode' => 'TP-LABEL-LEMBAGA', 'bobot' => 50]);
    $manager = actingAsYayasanKomponenManager($yayasan);

    $this->actingAs($manager)->get(route('admin.komponen-penilaian.index'))
        ->assertSee('SMP Cendekia Bangsa');
});

it('does not show a lembaga label on TP rows for a lembaga-scoped actor (regresi)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SMP Tunggal Scope']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'kode' => 'TP-NO-LABEL']);
    $manager = actingAsKomponenManager($lembaga);

    $this->actingAs($manager)->get(route('admin.komponen-penilaian.index'))
        ->assertDontSee('SMP Tunggal Scope');
});

it('shows the lembaga label on the ajax-rendered partial too, not just the initial full-page load', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Ajax Partial']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'kode' => 'TP-AJAX']);
    $manager = actingAsYayasanKomponenManager($yayasan);

    $this->actingAs($manager)
        ->get(route('admin.komponen-penilaian.index'), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertSee('SD Ajax Partial');
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="shows a lembaga label on each TP row|does not show a lembaga label|shows the lembaga label on the ajax-rendered partial" --compact`
Expected: test "shows a lembaga label..." dan "shows the lembaga label on the ajax..." FAIL (nama lembaga belum pernah ditampilkan di baris/kartu). Test "does not show..." SUDAH PASS (baseline).

- [x] **Step 3: Implementasi — controller**

Di `app/Http/Controllers/Admin/KomponenPenilaianController.php`, method `index()` — tambahkan `lembaga` ke eager-load `$komponenList` (baris 60-61):

```php
$komponenList = KomponenPenilaian::whereNotNull('subjek_id')
    ->with(['subjek', 'semester.tahunAjaran'])
```

menjadi:

```php
$komponenList = KomponenPenilaian::whereNotNull('subjek_id')
    ->with(['subjek', 'semester.tahunAjaran', 'lembaga'])
```

Kirim `scopeHeaderData()` ke cabang AJAX juga (baris 69-71). Ganti:

```php
if ($request->ajax()) {
    return view('portals.lembaga.akademik.komponen-penilaian._daftar', ['komponenList' => $komponenList])->render();
}
```

menjadi:

```php
if ($request->ajax()) {
    return view('portals.lembaga.akademik.komponen-penilaian._daftar', [
        'komponenList' => $komponenList,
        ...$this->scopeHeaderData($request),
    ])->render();
}
```

- [x] **Step 4: Implementasi — view**

Di `resources/views/portals/lembaga/akademik/komponen-penilaian/_daftar.blade.php`, kartu Live Calculator Bobot — ganti (baris 57-66, HANYA blok `<div class="truncate">` di dalamnya):

```blade
                        <div class="truncate">
                            <h4 class="font-bold text-gray-900 text-sm truncate">{{ $first->subjek->nama }}</h4>
                            <p class="text-[11px] text-gray-500">{{ $first->semester->nama }} ({{ $first->semester->tahunAjaran->nama }})</p>
                        </div>
```

menjadi:

```blade
                        <div class="truncate">
                            <h4 class="font-bold text-gray-900 text-sm truncate">{{ $first->subjek->nama }}</h4>
                            <p class="text-[11px] text-gray-500">
                                {{ $first->semester->nama }} ({{ $first->semester->tahunAjaran->nama }})
                                @if (($isYayasan ?? false) && ! ($activeLembaga ?? null))
                                    &bull; {{ $first->lembaga->nama ?? '-' }}
                                @endif
                            </p>
                        </div>
```

Baris daftar TP — ganti (baris 109-110):

```blade
                        <div class="flex items-center gap-3">
                            <x-badge tone="slate" class="text-xs font-medium">{{ $komponen->semester->nama }} — {{ $komponen->semester->tahunAjaran->nama }}</x-badge>
```

menjadi:

```blade
                        <div class="flex items-center gap-3">
                            @if (($isYayasan ?? false) && ! ($activeLembaga ?? null))
                                <x-badge tone="slate" class="text-xs font-medium">{{ $komponen->lembaga->nama ?? '-' }}</x-badge>
                            @endif
                            <x-badge tone="slate" class="text-xs font-medium">{{ $komponen->semester->nama }} — {{ $komponen->semester->tahunAjaran->nama }}</x-badge>
```

- [x] **Step 5: Jalankan test Step 1 lagi, pastikan semua lulus**

Run: `php artisan test --filter="shows a lembaga label on each TP row|does not show a lembaga label|shows the lembaga label on the ajax-rendered partial" --compact`
Expected: PASS ketiganya.

- [x] **Step 6: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php --compact`
Expected: PASS semua (termasuk seluruh test Task 1 yang bergantung pada `_daftar.blade.php` — pastikan tidak rusak oleh perubahan variabel `isYayasan`/`activeLembaga` yang sekarang juga hadir di cabang AJAX).

- [x] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/KomponenPenilaianController.php resources/views/portals/lembaga/akademik/komponen-penilaian/_daftar.blade.php tests/Feature/Admin/KomponenPenilaianCrudTest.php
git commit -m "feat(komponen-penilaian): label nama lembaga di baris daftar & kartu kalkulator bobot saat mode agregat"
```

---

### Task 5: 🟡🟢 Bersihkan Query Mati di `edit()` + Badge Lembaga di Form Edit (Item E + F)

**Files:**
- Modify: `app/Http/Controllers/Admin/KomponenPenilaianController.php`
- Modify: `resources/views/portals/lembaga/akademik/komponen-penilaian/edit.blade.php`
- Test: `tests/Feature/Admin/KomponenPenilaianCrudTest.php`

**Interfaces:**
- Produces: `KomponenPenilaianController::edit()` — signature berubah dari `edit(KomponenPenilaian $komponenPenilaian): View` menjadi `edit(Request $request, KomponenPenilaian $komponenPenilaian): View`. Tidak ada caller lain method ini selain route-model-binding (dicek: route `admin.komponen-penilaian.edit` tidak memanggilnya secara manual dari kode lain).

- [x] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('shows a lembaga badge on the Edit TP header for a yayasan actor in aggregate mode', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SMA Edit Aggregate']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $manager = actingAsYayasanKomponenManager($yayasan);

    $this->actingAs($manager)->get(route('admin.komponen-penilaian.edit', $komponen))
        ->assertSee('SMA Edit Aggregate');
});

it('does not show a lembaga badge on the Edit TP header for a lembaga-scoped actor (regresi)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SMA Edit Lembaga Scope']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $manager = actingAsKomponenManager($lembaga);

    $this->actingAs($manager)->get(route('admin.komponen-penilaian.edit', $komponen))
        ->assertDontSee('SMA Edit Lembaga Scope');
});

it('still allows editing kode, deskripsi, bobot, kktp, and assessment_type after edit() query cleanup (regresi)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);

    $this->actingAs($manager)->get(route('admin.komponen-penilaian.edit', $komponen))->assertOk();

    $this->actingAs($manager)->put(route('admin.komponen-penilaian.update', $komponen), [
        'assessment_type' => 'narrative',
        'kode' => 'TP-EDIT-OK',
        'deskripsi' => 'Deskripsi setelah edit',
        'bobot' => 80,
        'kktp' => 'KKTP setelah edit',
    ])->assertRedirect(route('admin.komponen-penilaian.index'));

    $komponen->refresh();
    expect($komponen->kode)->toBe('TP-EDIT-OK');
    expect($komponen->deskripsi)->toBe('Deskripsi setelah edit');
    expect($komponen->bobot)->toBe(80);
    expect($komponen->kktp)->toBe('KKTP setelah edit');
    expect($komponen->assessment_type->value)->toBe('narrative');
});
```

- [x] **Step 2: Jalankan test, pastikan hasil sesuai ekspektasi**

Run: `php artisan test --filter="shows a lembaga badge on the Edit TP header|does not show a lembaga badge|still allows editing kode" --compact`
Expected: test "shows a lembaga badge..." FAIL (badge belum ada). Test "does not show..." SUDAH PASS (baseline). Test "still allows editing..." SUDAH PASS (perilaku update belum diubah Task ini — ini baseline regresi, WAJIB tetap lulus setelah Step 3-4 nanti).

- [x] **Step 3: Implementasi — controller**

Di `app/Http/Controllers/Admin/KomponenPenilaianController.php`, method `edit()` — ganti SELURUH method (baris 151-170):

```php
public function edit(KomponenPenilaian $komponenPenilaian): View
{
    $this->authorize('komponen-penilaian.kelola');

    $subjek = $komponenPenilaian->subjek;
    if (! $subjek) {
        abort(404);
    }

    $dipakai = $komponenPenilaian->asesmen()->exists() || $komponenPenilaian->nilaiSiswa()->exists();

    return view('portals.lembaga.akademik.komponen-penilaian.edit', [
        'komponenPenilaian' => $komponenPenilaian->load(['subjek', 'semester.tahunAjaran']),
        'dipakai' => $dipakai,
        'mataPelajaranList' => MataPelajaran::orderBy('nama')->get(),
        'elemenCpList' => ElemenCp::orderBy('no_urut')->get(),
        'semesterList' => Semester::with('tahunAjaran')->orderByDesc('id')->get(),
        'bentukPendidikan' => auth()->user()->lembaga?->bentuk_pendidikan,
    ]);
}
```

menjadi:

```php
public function edit(Request $request, KomponenPenilaian $komponenPenilaian): View
{
    $this->authorize('komponen-penilaian.kelola');

    $subjek = $komponenPenilaian->subjek;
    if (! $subjek) {
        abort(404);
    }

    $dipakai = $komponenPenilaian->asesmen()->exists() || $komponenPenilaian->nilaiSiswa()->exists();

    return view('portals.lembaga.akademik.komponen-penilaian.edit', [
        'komponenPenilaian' => $komponenPenilaian->load(['subjek', 'semester.tahunAjaran', 'lembaga']),
        'dipakai' => $dipakai,
        ...$this->scopeHeaderData($request),
    ]);
}
```

- [x] **Step 4: Implementasi — view**

Di `resources/views/portals/lembaga/akademik/komponen-penilaian/edit.blade.php`, ganti blok header LENGKAP (baris 11-19):

```blade
        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Edit Komponen Penilaian (TP)</h1>
            <p class="text-sm text-gray-500">
                Akademik <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.komponen-penilaian.index') }}" class="font-semibold text-gray-700 hover:text-brand-600 transition-colors">Komponen Penilaian</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Edit</b>
            </p>
        </div>
```

menjadi:

```blade
        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="font-display text-lg font-bold text-gray-900">Edit Komponen Penilaian (TP)</h1>
                @if (($isYayasan ?? false) && ! ($activeLembaga ?? null))
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-200 bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">
                        <x-icon name="apartment" class="h-3.5 w-3.5" />
                        {{ $komponenPenilaian->lembaga->nama ?? '-' }}
                    </span>
                @endif
            </div>
            <p class="text-sm text-gray-500">
                Akademik <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.komponen-penilaian.index') }}" class="font-semibold text-gray-700 hover:text-brand-600 transition-colors">Komponen Penilaian</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Edit</b>
            </p>
        </div>
```

- [x] **Step 5: Jalankan test Step 1 lagi, pastikan semua lulus**

Run: `php artisan test --filter="shows a lembaga badge on the Edit TP header|does not show a lembaga badge|still allows editing kode" --compact`
Expected: PASS ketiganya.

- [x] **Step 6: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php --compact`
Expected: PASS semua (termasuk seluruh test Edit dari Task 1 spec sebelumnya — `it('locks mata pelajaran and semester when the komponen is already used...')` dan sejenisnya, WAJIB tetap lulus tanpa perubahan assertion, karena `edit()` hanya kehilangan data yang TIDAK PERNAH dipakai view manapun).

- [x] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/KomponenPenilaianController.php resources/views/portals/lembaga/akademik/komponen-penilaian/edit.blade.php tests/Feature/Admin/KomponenPenilaianCrudTest.php
git commit -m "refactor(komponen-penilaian): bersihkan query mati di edit() + tambah badge lembaga di header Edit TP saat mode agregat"
```

---

### Task 6: Penutup — Regresi Penuh & Pint

**Files:**
- Tidak ada file baru — task verifikasi murni.

- [x] **Step 1: Jalankan seluruh test domain Komponen Penilaian (Admin + Guru)**

Run: `php artisan test --compact --filter="KomponenPenilaianCrudTest|KomponenPenilaianControllerTest"`
Expected: PASS semua, 0 gagal. (Filter kedua meng-cover `tests/Feature/Guru/KomponenPenilaianControllerTest.php` — WAJIB tetap lulus tanpa perubahan, membuktikan jalur Guru sungguh tidak tersentuh oleh 5 task di atas.)

- [x] **Step 2: Jalankan Pint pada file yang diubah**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}`.

- [x] **Step 3: Verifikasi manual via browser (OPSIONAL — bukan langkah blocking)**

Kalau memungkinkan (akses browser interaktif tersedia), verifikasi manual berikut. **Kalau TIDAK memungkinkan (mis. tidak ada akses browser di sesi ini), lewati langkah ini dan serahkan ke user untuk dicek manual sebelum merge — JANGAN mengklaim langkah ini "selesai" tanpa benar-benar menjalankannya.**

- Login sebagai yayasan-scope, mode "Semua Lembaga" (belum switch): buka Komponen Penilaian — tombol "Tambah TP" TIDAK ada, ada pesan "Pilih 1 lembaga lewat pengalih di topbar...". Coba akses `/admin/komponen-penilaian/create` langsung lewat URL — dapat halaman error 422.
- Switch ke 1 lembaga lewat pengalih topbar: tombol "Tambah TP" muncul, bisa buat TP baru seperti biasa.
- Kembali ke mode "Semua Lembaga": daftar TP menampilkan TP dari BERBAGAI lembaga sekaligus (kalau ada), masing-masing baris & kartu kalkulator bobot berlabel nama lembaganya.
- Buka Edit salah satu TP dari mode agregat: badge nama lembaga TP itu muncul di header Edit.

- [x] **Step 4: Laporkan hasil**

TIDAK perlu menulis file handoff log baru di task ini — permintaan terpisah kalau user menghendaki nanti.

---

## Self-Review

**1. Spec coverage** — SEMUA 6 item spec yang butuh perubahan kode (`.agents/specs/2026-09-09-tp-scope-lembaga-perbaikan.md`) tercakup: Item A → Task 1, Item B → Task 2, Item C → Task 3, Item D → Task 4, Item E+F → Task 5 (digabung sesuai catatan eksplisit di spec: "digabung di sini supaya tidak 2x mengubah signature method yang sama"). Item G (TIDAK ada perubahan kode, didokumentasikan sengaja) SENGAJA TIDAK dapat task, sesuai instruksi spec.

**2. Placeholder scan** — tidak ada "TBD"/"TODO"/dst. Semua step berisi kode lengkap, ditranskripsi persis dari kode current-vs-fix yang sudah ada di spec (yang sendiri sudah direview 3x). Instruksi lokasi sisip (Task 1 Step 3, Task 5 Step 3) eksplisit menyebut baris sebelum/sesudah, bukan "sisipkan di tempat yang sesuai".

**3. Type consistency** — signature `edit(Request $request, KomponenPenilaian $komponenPenilaian)` (Task 5) konsisten dengan pola `create(Request $request)`/`store(StoreKomponenPenilaianRequest $request, ...)` yang SUDAH ada di controller yang sama (parameter route-model-binding SELALU terakhir). Variabel `$isYayasan`/`$activeLembaga` dipakai KONSISTEN nama & bentuk (`?? false`/`?? null`) di SEMUA view yang disentuh (Task 1 `_daftar.blade.php`, Task 4 `_daftar.blade.php`, Task 5 `edit.blade.php`) — sama persis dengan pola yang SUDAH ada di `index.blade.php` dari kickoff sebelumnya.

**Catatan tambahan hasil self-review**:
- Task 1 SENGAJA jadi 1 task besar (guard controller + sembunyikan tombol view sekaligus) — dipecah lebih halus TIDAK masuk akal karena keduanya bagian dari 1 perilaku tunggal ("Tambah TP butuh 1 lembaga aktif"), dan test-nya SUDAH menguji kombinasi keduanya sekaligus (guard backend DAN visibility tombol).
- Task 1 Step 4 punya catatan eksplisit soal prasyarat `isYayasan`/`activeLembaga` di cabang AJAX partial (BELUM ada saat Task 1, BARU ditambahkan Task 4) — test Task 1 SENGAJA cuma menguji lewat GET biasa (bukan `->ajax()`) supaya tidak bergantung ke Task 4 yang belum dikerjakan, urutan task tetap independen dan bisa direview satu-satu.
- Task 3 dan Task 1 SAMA-SAMA menyentuh soal "aktor yayasan mode agregat", TAPI fix-nya beda bentuk dengan sengaja (dijelaskan lengkap di spec Item A) — Task 1 (create/store) di-GUARD total, Task 3 (index) TETAP boleh diakses tapi defaultnya diperbaiki jadi tidak ambigu. Plan ini TIDAK mencampur keduanya jadi 1 task supaya perbedaan itu tetap terlihat jelas saat review per-task.
- Urutan Task 2-5 TIDAK saling bergantung secara ketat KECUALI: Task 4 harus SETELAH Task 1 (karena Task 4 mengubah cabang AJAX `_daftar.blade.php` yang variabelnya baru dipakai Task 1's view changes), dan disarankan tetap berurutan 1→6 karena semuanya menyentuh file yang sama (`KomponenPenilaianController.php`) — mengerjakan paralel berisiko conflict merge, bukan karena dependency logis.
