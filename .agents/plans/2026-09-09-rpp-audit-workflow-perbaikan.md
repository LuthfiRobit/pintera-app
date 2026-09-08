# Audit & Perbaikan Menu RPP (Workflow, Wording, Backend) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Perbaiki 10 temuan audit menu RPP — 1 kritis (tab "Perangkat Ajar Saya" bocor data guru lain untuk aktor tanpa profil Guru), 1 bug scoping (`TenantContext` tidak validasi ulang session), dan 8 item wording/UX/backend minor.

**Architecture:** Task 1 (kritis) berdiri sendiri. Task 2 (`TenantContext`) adalah fondasi wajib SEBELUM Task 4 (badge scope). Task 3 (empty-state + compact() fix cabang ajax) WAJIB sebelum Task 4 (keduanya menyentuh baris yang sama). Task 5-9 independen satu sama lain dan dari task lainnya. Task 10 penutup.

**Tech Stack:** Laravel 12, Blade, Alpine.js, Pest (function-style test).

## Global Constraints

- **Backend keamanan lain TIDAK diubah** — semua `abort_if`/`authorizeMilikGuru()`/FormRequest `authorize()` existing tetap apa adanya, KECUALI perubahan eksplisit yang disebutkan di task masing-masing (Task 1, 2, 8, 9).
- **`TenantContext` untuk 13 file LAIN di luar RPP TIDAK disentuh** — backlog terpisah, di luar scope plan ini.
- **Jalur "admin membuat RPP atas nama guru" (verifikasi guru mengajar kombinasi) TIDAK diubah** — pertanyaan produk belum dijawab user, di luar scope plan ini.
- **Modal RPP (create/edit/verify) TETAP pakai POST + reload halaman penuh** — TIDAK dimodernisasi ke AJAX di plan ini.
- **`$stats` KPI TIDAK diubah supaya ikut filter kontrol** — Task 7 cuma menambah teks keterangan, BUKAN mengubah query.
- **Task 3 WAJIB selesai sebelum Task 4** — keduanya mengubah baris `compact()`/return `view()` yang sama di cabang ajax `index()`; Task 4 mengasumsikan hasil akhir Task 3 sudah ada.
- **Task 2 WAJIB selesai sebelum Task 4** — badge scope (Task 4) baru akurat kalau `$targetLembagaId` sudah tervalidasi (Task 2).

---

## Konteks File yang Sudah Ada (baca sebelum mulai)

- `app/Http/Controllers/Admin/RppController.php` — `index()` sudah pakai `ResolveLembagaScopeTrait` (constructor SUDAH `use ResolveLembagaScopeTrait;`), TAPI belum meng-`use App\Models\Lembaga;`. Cabang ajax (baris ±93-95) SAAT INI cuma `compact('rppList', 'tab', 'perPage')`.
- `app/Domains/Akademik/Actions/Rpp/ListRppAction.php` — constructor inject `TenantContext $tenantContext`, dipakai SATU kali di baris `$targetLembagaId = $this->tenantContext->activeLembagaId();`. `$stats` dihitung dari `$baseQuery` (SUDAH kena filter tab='saya' guru_id kalau ada) SEBELUM filter kontrol (search/kelas/dst) diterapkan ke `$query` terpisah.
- `app/Http/Requests/Akademik/UpdateRppRequest.php` — SUDAH `use App\Models\Kelas;`, tidak perlu import baru.
- `resources/views/portals/lembaga/akademik/rpp/_daftar.blade.php` — action buttons baris ±109 digerbangi `canBeEditedByGuru()` + `@can('rpp.kelola')` SAJA (TANPA cek kepemilikan). Empty-state baris ±247-258.
- `resources/views/portals/lembaga/akademik/rpp/index.blade.php` — header baris ±28-36, KPI cards baris ±38-91, dropdown Status baris ±179-187.
- `tests/Feature/Akademik/RppWorkflowTest.php` — `beforeEach()` SUDAH setup: `$this->yayasan`, `$this->lembaga`, `$this->tahunAjaran`, `$this->semester`, `$this->kelas`, `$this->mapel`, `$this->userGuru` (role `guru`), `$this->guru`, `$this->userKurikulum` (role `wakasek_kurikulum`, permission `rpp.view`+`rpp.kelola`+`rpp.verify`). Permission `rpp.view`/`rpp.kelola`/`rpp.verify` SUDAH di-`firstOrCreate` di `beforeEach()` — JANGAN bikin ulang.

---

### Task 1: 🔴 Fix Kritis — Tab "Saya" Bocor RPP Guru Lain untuk Aktor Tanpa Profil Guru

**Files:**
- Modify: `app/Domains/Akademik/Actions/Rpp/ListRppAction.php`
- Modify: `resources/views/portals/lembaga/akademik/rpp/_daftar.blade.php`
- Test: `tests/Feature/Akademik/RppWorkflowTest.php`

**Interfaces:**
- Tidak ada interface baru — perubahan internal query + view.

- [x] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/Akademik/RppWorkflowTest.php` (akhir file):

```php
it('tidak menampilkan RPP guru lain di tab saya untuk aktor tanpa profil Guru', function () {
    $roleOperator = Role::firstOrCreate(['name' => 'operator_akademik_test_saya_tab', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $roleOperator->givePermissionTo(['rpp.view', 'rpp.kelola']);
    $operator = User::factory()->create(['lembaga_id' => $this->lembaga->id]);
    $operator->assignRole($roleOperator);
    expect($operator->guru)->toBeNull();

    $file = UploadedFile::fake()->create('rpp_orang_lain.pdf', 200, 'application/pdf');
    $path = $file->store("rpp/{$this->lembaga->id}", 'public');
    Rpp::create([
        'yayasan_id' => $this->yayasan->id, 'lembaga_id' => $this->lembaga->id, 'guru_id' => $this->guru->id,
        'tahun_ajaran_id' => $this->tahunAjaran->id, 'semester_id' => $this->semester->id, 'kelas_id' => $this->kelas->id,
        'mata_pelajaran_id' => $this->mapel->id, 'judul_topik' => 'Topik Milik Guru Lain', 'alokasi_waktu' => '2 JP',
        'file_path' => $path, 'file_name' => 'rpp_orang_lain.pdf', 'file_size_bytes' => 2048,
        'mime_type' => 'application/pdf', 'status' => StatusRpp::Draft,
    ]);

    $response = $this->actingAs($operator)->get(route('admin.rpp.index', ['tab' => 'saya']));

    $response->assertOk()->assertDontSee('Topik Milik Guru Lain');
});
```

**Catatan soal perbaikan #3 (cek kepemilikan eksplisit di tombol aksi)**: TIDAK ada test perilaku terpisah untuk ini di Step 1 — setelah perbaikan #1 (query) diterapkan, tombol Edit/Hapus/Ajukan HANYA PERNAH dirender di cabang `@else` (tab `saya`, lihat `_daftar.blade.php` struktur `@if ($tab === 'verifikasi') ... @else ... @endif`), dan cabang itu sekarang SELALU kosong untuk aktor tanpa profil Guru — TIDAK ADA jalur kode lain saat ini yang bisa merender tombol itu untuk RPP bukan milik aktor. Perbaikan #3 murni defense-in-depth untuk jalur MASA DEPAN (fitur baru yang mungkin menampilkan RPP lintas-guru) — tidak bisa diuji perilakunya secara independen hari ini tanpa jalur nyata yang mengeksposnya. Tetap terapkan perbaikan #3 di Step 3 (kode-nya benar dan murah), TAPI JANGAN memaksakan test tambahan yang sebenarnya cuma menguji ulang perbaikan #1.

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="tidak menampilkan RPP guru lain di tab saya" --compact`
Expected: FAIL — saat ini operator tanpa profil Guru melihat RPP guru lain di tab "saya".

- [x] **Step 3: Implementasi minimal**

Di `app/Domains/Akademik/Actions/Rpp/ListRppAction.php`, ganti:

```php
if ($tab === 'saya') {
    $guru = $user->guru;
    if ($guru) {
        $baseQuery->where('guru_id', $guru->id);
    }
} elseif ($tab === 'verifikasi' && $status === null) {
    $status = StatusRpp::Diajukan->value;
}
```

menjadi:

```php
if ($tab === 'saya') {
    $guru = $user->guru;
    if ($guru) {
        $baseQuery->where('guru_id', $guru->id);
    } else {
        // Aktor tanpa profil Guru (operator/kepsek/wakasek) tidak punya RPP
        // pribadi -- tab "Saya" WAJIB kosong, bukan menampilkan RPP orang lain.
        $baseQuery->whereRaw('1 = 0');
    }
} elseif ($tab === 'verifikasi' && $status === null) {
    $status = StatusRpp::Diajukan->value;
}
```

Di `resources/views/portals/lembaga/akademik/rpp/_daftar.blade.php` baris ±109, ganti:

```blade
@if ($rpp->canBeEditedByGuru())
    @can('rpp.kelola')
```

menjadi:

```blade
@if ($rpp->canBeEditedByGuru() && auth()->user()->guru?->id === $rpp->guru_id)
    @can('rpp.kelola')
```

(`@endif`/`@endcan` di bawahnya TIDAK berubah.)

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="tidak menampilkan RPP guru lain di tab saya" --compact`
Expected: PASS.

- [x] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Akademik/RppWorkflowTest.php tests/Feature/Akademik/RppControllerIdorTest.php --compact`
Expected: PASS semua — termasuk `RppControllerIdorTest` (banyak test IDOR pemilik/verifikator yang HARUS tetap lulus, `authorizeMilikGuru()` sendiri tidak diubah).

- [x] **Step 6: Commit**

```bash
git add app/Domains/Akademik/Actions/Rpp/ListRppAction.php resources/views/portals/lembaga/akademik/rpp/_daftar.blade.php tests/Feature/Akademik/RppWorkflowTest.php
git commit -m "fix(rpp): tutup kebocoran RPP guru lain di tab Saya utk aktor tanpa profil Guru + cek kepemilikan eksplisit di tombol aksi"
```

---

### Task 2: `ListRppAction` Berhenti Memakai `TenantContext` yang Tidak Tervalidasi

**Files:**
- Modify: `app/Http/Controllers/Admin/RppController.php`
- Modify: `app/Domains/Akademik/Actions/Rpp/ListRppAction.php`
- Test: `tests/Feature/Akademik/RppWorkflowTest.php`

**Interfaces:**
- Produces: `ListRppAction::execute()` sekarang menerima `?int $targetLembagaId` sebagai parameter (bukan resolve sendiri), TIDAK lagi mengembalikan `targetLembagaId` di array hasil.
- Consumes (Task 4): `RppController::index()` menghitung `$targetLembagaId` via `$this->resolveActiveLembagaId()` — dipakai ulang oleh Task 4 (`scopeHeaderData()`).

- [x] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('menampilkan mode agregat (bukan kosong salah) di tab verifikasi utk yayasan-scope actor dengan active_lembaga_id stale', function () {
    $roleYayasanVerify = Role::firstOrCreate(['name' => 'yayasan_rpp_index_stale_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $roleYayasanVerify->givePermissionTo(['rpp.view', 'rpp.verify']);

    $yayasanLain = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    $verifierYayasan = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $this->yayasan->id]);
    $verifierYayasan->assignRole($roleYayasanVerify);
    session(['active_lembaga_id' => $lembagaLain->id]);

    $file = UploadedFile::fake()->create('rpp_index_stale.pdf', 200, 'application/pdf');
    $path = $file->store("rpp/{$this->lembaga->id}", 'public');
    Rpp::create([
        'yayasan_id' => $this->yayasan->id, 'lembaga_id' => $this->lembaga->id, 'guru_id' => $this->guru->id,
        'tahun_ajaran_id' => $this->tahunAjaran->id, 'semester_id' => $this->semester->id, 'kelas_id' => $this->kelas->id,
        'mata_pelajaran_id' => $this->mapel->id, 'judul_topik' => 'Topik Uji Agregat Stale', 'alokasi_waktu' => '2 JP',
        'file_path' => $path, 'file_name' => 'rpp_index_stale.pdf', 'file_size_bytes' => 2048,
        'mime_type' => 'application/pdf', 'status' => StatusRpp::Diajukan,
    ]);

    $response = $this->actingAs($verifierYayasan)->get(route('admin.rpp.index', ['tab' => 'verifikasi']));

    $response->assertOk()->assertSee('Topik Uji Agregat Stale');
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="menampilkan mode agregat .bukan kosong salah." --compact`
Expected: FAIL — saat ini `TenantContext::activeLembagaId()` mengembalikan `$lembagaLain->id` mentah tanpa validasi, hasil query jadi kosong (irisan `TenantScope` vs filter manual asing = 0 baris).

- [x] **Step 3: Implementasi minimal**

Di `app/Http/Controllers/Admin/RppController.php`, method `index()`, ganti:

```php
[
    'rppList' => $rppList,
    'stats' => $stats,
    'status' => $status,
    'targetLembagaId' => $targetLembagaId,
] = $this->listRppAction->execute(
    user: $user,
    tab: $tab,
    search: $search,
    tahunAjaranId: $tahunAjaranId ? (int) $tahunAjaranId : null,
    semesterId: $semesterId ? (int) $semesterId : null,
    kelasId: $kelasId ? (int) $kelasId : null,
    mapelId: $mapelId ? (int) $mapelId : null,
    status: $status,
    perPage: $perPage,
    kurikulum: $kurikulum,
);
```

menjadi:

```php
$targetLembagaId = $user->widestScopeLevel() === 'yayasan'
    ? $this->resolveActiveLembagaId($user)
    : $user->lembaga_id;

[
    'rppList' => $rppList,
    'stats' => $stats,
    'status' => $status,
] = $this->listRppAction->execute(
    user: $user,
    tab: $tab,
    search: $search,
    tahunAjaranId: $tahunAjaranId ? (int) $tahunAjaranId : null,
    semesterId: $semesterId ? (int) $semesterId : null,
    kelasId: $kelasId ? (int) $kelasId : null,
    mapelId: $mapelId ? (int) $mapelId : null,
    status: $status,
    perPage: $perPage,
    kurikulum: $kurikulum,
    targetLembagaId: $targetLembagaId,
);
```

Di `app/Domains/Akademik/Actions/Rpp/ListRppAction.php`, ganti seluruh isi file:

```php
final class ListRppAction
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function execute(
        User $user,
        string $tab,
        ?string $search,
        ?int $tahunAjaranId,
        ?int $semesterId,
        ?int $kelasId,
        ?int $mapelId,
        ?string $status,
        int $perPage,
        ?string $kurikulum = null,
    ): array {
        $targetLembagaId = $this->tenantContext->activeLembagaId();

        $baseQuery = Rpp::query();
```

menjadi:

```php
final class ListRppAction
{
    public function execute(
        User $user,
        string $tab,
        ?string $search,
        ?int $tahunAjaranId,
        ?int $semesterId,
        ?int $kelasId,
        ?int $mapelId,
        ?string $status,
        int $perPage,
        ?string $kurikulum = null,
        ?int $targetLembagaId = null,
    ): array {
        $baseQuery = Rpp::query();
```

Dan hapus `use App\Domains\Shared\Context\TenantContext;` dari daftar import di atas class, hapus juga baris `return [... 'targetLembagaId' => $targetLembagaId,];` di akhir method — sisakan cuma `'rppList'`, `'stats'`, `'status'`.

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="menampilkan mode agregat .bukan kosong salah." --compact`
Expected: PASS.

- [x] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Akademik/RppWorkflowTest.php tests/Feature/Akademik/RppKurikulumReportingTest.php --compact`
Expected: PASS semua — termasuk "menolak actor yayasan dengan active_lembaga_id stale saat memverifikasi RPP" (Task ini TIDAK mengubah `verify()`, jadi test itu harus tetap lulus tanpa perubahan).

- [x] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/RppController.php app/Domains/Akademik/Actions/Rpp/ListRppAction.php tests/Feature/Akademik/RppWorkflowTest.php
git commit -m "fix(rpp): ganti TenantContext dengan resolveActiveLembagaId() tervalidasi di ListRppAction"
```

---

### Task 3: Empty-State Sadar Filter + Payload Cabang Ajax Dilengkapi

**Files:**
- Modify: `app/Http/Controllers/Admin/RppController.php`
- Modify: `resources/views/portals/lembaga/akademik/rpp/_daftar.blade.php`
- Test: `tests/Feature/Akademik/RppWorkflowTest.php`

**Interfaces:**
- Produces: cabang ajax `index()` sekarang mengirim `search`, `tahunAjaranId`, `semesterId`, `kelasId`, `mapelId`, `kurikulum`, `tahunAjaranAktif` ke `_daftar.blade.php` (Task 4 akan menambah `isYayasan`/`activeLembaga` ke payload YANG SAMA — baca catatan Task 4).

- [x] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('menampilkan pesan sadar-filter di Inbox Verifikasi ketika hasil kosong karena filter, bukan klaim semua sudah ditinjau', function () {
    $response = $this->actingAs($this->userKurikulum)->get(route('admin.rpp.index', [
        'tab' => 'verifikasi', 'search' => 'topik-yang-tidak-ada-sama-sekali',
    ]));

    $response->assertOk()
        ->assertDontSee('Semua pengajuan RPP telah selesai ditinjau.')
        ->assertSee('Tidak ada dokumen yang cocok dengan filter di Inbox Verifikasi.');
});

it('tetap menampilkan pesan default Inbox kosong ketika benar-benar tidak ada filter aktif', function () {
    $response = $this->actingAs($this->userKurikulum)->get(route('admin.rpp.index', ['tab' => 'verifikasi']));

    $response->assertOk()->assertSee('Semua pengajuan RPP telah selesai ditinjau.');
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="menampilkan pesan sadar-filter di Inbox Verifikasi|tetap menampilkan pesan default Inbox kosong" --compact`
Expected: test pertama FAIL (pesan generik selalu sama saat ini). Test kedua SUDAH PASS (baseline, tidak berubah).

- [x] **Step 3: Implementasi minimal**

Di `app/Http/Controllers/Admin/RppController.php`, ganti:

```php
if ($request->ajax()) {
    return view('portals.lembaga.akademik.rpp._daftar', compact('rppList', 'tab', 'perPage'));
}
```

menjadi:

```php
if ($request->ajax()) {
    return view('portals.lembaga.akademik.rpp._daftar', compact(
        'rppList', 'tab', 'perPage', 'search', 'tahunAjaranId', 'semesterId', 'kelasId', 'mapelId', 'kurikulum', 'tahunAjaranAktif'
    ));
}
```

Di `resources/views/portals/lembaga/akademik/rpp/_daftar.blade.php` baris ±247-258, ganti:

```blade
@if ($rppList->isEmpty())
    <tr>
        <td colspan="8" class="px-5 py-12 text-center text-gray-500">
            <x-icon name="description" class="mx-auto h-10 w-10 text-gray-300 mb-2" />
            <p class="font-semibold text-gray-700">
                {{ $tab === 'verifikasi' ? 'Tidak ada perangkat ajar yang sedang menunggu review verifikasi kurikulum.' : 'Belum ada dokumen perangkat ajar yang cocok dengan filter.' }}
            </p>
            <p class="text-xs text-gray-400 mt-0.5">
                {{ $tab === 'verifikasi' ? 'Semua pengajuan RPP telah selesai ditinjau.' : 'Silakan sesuaikan kriteria filter atau unggah dokumen baru.' }}
            </p>
        </td>
    </tr>
@endif
```

menjadi:

```blade
@if ($rppList->isEmpty())
    <tr>
        <td colspan="8" class="px-5 py-12 text-center text-gray-500">
            <x-icon name="description" class="mx-auto h-10 w-10 text-gray-300 mb-2" />
            @php
                $adaFilterAktif = $search || $semesterId || $kelasId || $mapelId || $kurikulum
                    || ($tahunAjaranId && $tahunAjaranId != ($tahunAjaranAktif->id ?? null))
                    || ($tab === 'verifikasi' && $status !== \App\Domains\Akademik\Enums\StatusRpp::Diajukan->value);
            @endphp
            <p class="font-semibold text-gray-700">
                @if ($tab === 'saya' && ! auth()->user()->guru)
                    Akun Anda tidak terhubung dengan profil Guru, sehingga tidak ada dokumen RPP pribadi di sini.
                @elseif ($tab === 'verifikasi' && ! $adaFilterAktif)
                    Tidak ada perangkat ajar yang sedang menunggu review verifikasi kurikulum.
                @elseif ($tab === 'verifikasi')
                    Tidak ada dokumen yang cocok dengan filter di Inbox Verifikasi.
                @else
                    Belum ada dokumen perangkat ajar yang cocok dengan filter.
                @endif
            </p>
            <p class="text-xs text-gray-400 mt-0.5">
                @if ($tab === 'verifikasi' && ! $adaFilterAktif)
                    Semua pengajuan RPP telah selesai ditinjau.
                @else
                    Silakan sesuaikan kriteria filter atau unggah dokumen baru.
                @endif
            </p>
        </td>
    </tr>
@endif
```

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="menampilkan pesan sadar-filter di Inbox Verifikasi|tetap menampilkan pesan default Inbox kosong" --compact`
Expected: PASS kedua test.

- [x] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Akademik/RppWorkflowTest.php --compact`
Expected: PASS semua.

- [x] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/RppController.php resources/views/portals/lembaga/akademik/rpp/_daftar.blade.php tests/Feature/Akademik/RppWorkflowTest.php
git commit -m "fix(rpp): empty-state Inbox Verifikasi sadar filter (bukan klaim salah 'semua sudah ditinjau')"
```

---

### Task 4: Badge Scope (isYayasan/activeLembaga)

**Files:**
- Modify: `app/Http/Controllers/Admin/RppController.php`
- Modify: `resources/views/portals/lembaga/akademik/rpp/index.blade.php`
- Test: `tests/Feature/Akademik/RppWorkflowTest.php`

**Interfaces:**
- Consumes: `$this->resolveActiveLembagaId()` (dari `ResolveLembagaScopeTrait`, SUDAH di-`use` controller ini).
- Produces: `isYayasan`, `activeLembaga` dikirim ke KEDUA cabang `index()`.

**PRASYARAT: Task 2 dan Task 3 HARUS sudah selesai sebelum task ini.**

- [x] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('menampilkan badge nama lembaga aktif utk aktor yayasan-scope yang sudah switch lembaga', function () {
    $roleYayasanVerify = Role::firstOrCreate(['name' => 'yayasan_rpp_badge_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $roleYayasanVerify->givePermissionTo(['rpp.view', 'rpp.verify']);
    $lembagaBernama = Lembaga::factory()->create(['yayasan_id' => $this->yayasan->id, 'nama' => 'SD Cempaka Raya']);
    $verifier = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $this->yayasan->id]);
    $verifier->assignRole($roleYayasanVerify);
    session(['active_lembaga_id' => $lembagaBernama->id]);

    $this->actingAs($verifier)->get(route('admin.rpp.index'))
        ->assertSee('SD Cempaka Raya')
        ->assertSee('border-brand-200 bg-brand-50 text-brand-700', false);
});

it('menampilkan badge "Semua Lembaga" dalam mode agregat utk aktor yayasan-scope', function () {
    $roleYayasanVerify = Role::firstOrCreate(['name' => 'yayasan_rpp_badge_agregat_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $roleYayasanVerify->givePermissionTo(['rpp.view', 'rpp.verify']);
    $verifier = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $this->yayasan->id]);
    $verifier->assignRole($roleYayasanVerify);

    $this->actingAs($verifier)->get(route('admin.rpp.index'))
        ->assertSee('Semua Lembaga')
        ->assertSee('border-purple-200 bg-purple-50 text-purple-700', false);
});

it('tidak menampilkan badge scope utk aktor lembaga-scope', function () {
    $this->actingAs($this->userGuru)->get(route('admin.rpp.index'))
        ->assertDontSee('Semua Lembaga');
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="menampilkan badge nama lembaga aktif|menampilkan badge .Semua Lembaga.|tidak menampilkan badge scope" --compact`
Expected: 2 test pertama FAIL (badge belum ada). Test ketiga SUDAH PASS (baseline).

- [x] **Step 3: Implementasi minimal**

Di `app/Http/Controllers/Admin/RppController.php`, tambahkan import setelah `use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;`:

```php
use App\Models\Lembaga;
```

Tambahkan method private baru sebelum `index()`:

```php
private function scopeHeaderData(Request $request): array
{
    $isYayasan = $request->user()->widestScopeLevel() === 'yayasan';
    $lembagaId = $this->resolveActiveLembagaId($request->user());

    return [
        'isYayasan' => $isYayasan,
        'activeLembaga' => ($isYayasan && $lembagaId) ? Lembaga::withoutGlobalScopes()->find($lembagaId) : null,
    ];
}
```

Ganti cabang ajax (hasil akhir Task 3):

```php
if ($request->ajax()) {
    return view('portals.lembaga.akademik.rpp._daftar', compact(
        'rppList', 'tab', 'perPage', 'search', 'tahunAjaranId', 'semesterId', 'kelasId', 'mapelId', 'kurikulum', 'tahunAjaranAktif'
    ));
}
```

menjadi:

```php
if ($request->ajax()) {
    return view('portals.lembaga.akademik.rpp._daftar', array_merge(compact(
        'rppList', 'tab', 'perPage', 'search', 'tahunAjaranId', 'semesterId', 'kelasId', 'mapelId', 'kurikulum', 'tahunAjaranAktif'
    ), $this->scopeHeaderData($request)));
}
```

Ganti return `view('portals.lembaga.akademik.rpp.index', [...])` (halaman penuh), tambahkan spread di akhir array:

```php
return view('portals.lembaga.akademik.rpp.index', [
    'tab' => $tab,
    'rppList' => $rppList,
    'stats' => $stats,
    'kelasList' => $kelasList,
    'mataPelajaranList' => $mataPelajaranList,
    'guruList' => $guruList,
    'semesterList' => $semesterList,
    'tahunAjaranList' => $tahunAjaranList,
    'tahunAjaranAktif' => $tahunAjaranAktif,
    'tahunAjaranId' => $tahunAjaranId,
    'semesterId' => $semesterId,
    'kelasId' => $kelasId,
    'mapelId' => $mapelId,
    'kurikulum' => $kurikulum,
    'status' => $status,
    'search' => $search,
    'perPage' => $perPage,
    ...$this->scopeHeaderData($request),
]);
```

Di `resources/views/portals/lembaga/akademik/rpp/index.blade.php` baris ±28-36, ganti:

```blade
<div>
    <h1 class="font-display text-lg font-bold text-gray-900">Perangkat Ajar (RPP / Modul Ajar)</h1>
    <p class="text-xs text-gray-500 mt-0.5">Kelola penyusunan dokumen perencanaan pembelajaran, pengajuan, dan verifikasi kurikulum.</p>
</div>
```

menjadi:

```blade
<div>
    <div class="flex flex-wrap items-center gap-2.5">
        <h1 class="font-display text-lg font-bold text-gray-900">Perangkat Ajar (RPP / Modul Ajar)</h1>
        @if ($isYayasan ?? false)
            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($activeLembaga ?? null) ? 'border border-brand-200 bg-brand-50 text-brand-700' : 'border border-purple-200 bg-purple-50 text-purple-700' }}">
                <x-icon name="apartment" class="h-3.5 w-3.5" />
                {{ ($activeLembaga ?? null) ? $activeLembaga->nama : 'Semua Lembaga' }}
            </span>
        @endif
    </div>
    <p class="text-xs text-gray-500 mt-0.5">Kelola penyusunan dokumen perencanaan pembelajaran, pengajuan, dan verifikasi kurikulum.</p>
</div>
```

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="menampilkan badge nama lembaga aktif|menampilkan badge .Semua Lembaga.|tidak menampilkan badge scope" --compact`
Expected: PASS ketiga test.

- [x] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Akademik/RppWorkflowTest.php --compact`
Expected: PASS semua.

- [x] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/RppController.php resources/views/portals/lembaga/akademik/rpp/index.blade.php tests/Feature/Akademik/RppWorkflowTest.php
git commit -m "feat(rpp): badge scope isYayasan/activeLembaga di header index"
```

---

### Task 5: Fix Wording "Waka Kurikulum"

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/rpp/_daftar.blade.php`
- Test: `tests/Feature/Akademik/RppWorkflowTest.php`

**Interfaces:**
- Tidak ada interface baru.

- [x] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('tidak menyebut role spesifik "Waka Kurikulum" di dialog konfirmasi pengajuan RPP', function () {
    $response = $this->actingAs($this->userGuru)->get(route('admin.rpp.index'));

    $response->assertOk()
        ->assertDontSee('Waka Kurikulum', false)
        ->assertSee('diverifikasi oleh pihak kurikulum', false);
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="tidak menyebut role spesifik .Waka Kurikulum." --compact`
Expected: FAIL — teks "Waka Kurikulum" masih ada.

- [x] **Step 3: Implementasi minimal**

Di `_daftar.blade.php` baris ±116, ganti:

```blade
@submit.prevent="confirmDialog('Ajukan RPP ke Kurikulum?', 'Apakah Anda yakin ingin mengajukan berkas ini untuk diverifikasi oleh Waka Kurikulum?', { confirmLabel: 'Ya, Ajukan' }).then(c => { if(c) $el.submit() })"
```

menjadi:

```blade
@submit.prevent="confirmDialog('Ajukan RPP ke Kurikulum?', 'Apakah Anda yakin ingin mengajukan berkas ini untuk diverifikasi oleh pihak kurikulum?', { confirmLabel: 'Ya, Ajukan' }).then(c => { if(c) $el.submit() })"
```

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="tidak menyebut role spesifik .Waka Kurikulum." --compact`
Expected: PASS.

- [x] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Akademik/RppWorkflowTest.php --compact`
Expected: PASS semua.

- [x] **Step 6: Commit**

```bash
git add resources/views/portals/lembaga/akademik/rpp/_daftar.blade.php tests/Feature/Akademik/RppWorkflowTest.php
git commit -m "fix(rpp): ganti wording 'Waka Kurikulum' jadi 'pihak kurikulum' (3 role bisa verifikasi, bukan cuma 1)"
```

---

### Task 6: Label Status Dropdown Tab Verifikasi

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/rpp/index.blade.php`
- Test: `tests/Feature/Akademik/RppWorkflowTest.php`

**Interfaces:**
- Tidak ada interface baru.

- [x] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('menampilkan label default Inbox pada dropdown Status di tab verifikasi', function () {
    $response = $this->actingAs($this->userKurikulum)->get(route('admin.rpp.index', ['tab' => 'verifikasi']));

    $response->assertOk()->assertSee('(Inbox default: Menunggu Verifikasi)', false);
});

it('tidak menampilkan label default Inbox pada dropdown Status di tab saya', function () {
    $response = $this->actingAs($this->userGuru)->get(route('admin.rpp.index', ['tab' => 'saya']));

    $response->assertOk()->assertDontSee('(Inbox default: Menunggu Verifikasi)', false);
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="menampilkan label default Inbox|tidak menampilkan label default Inbox" --compact`
Expected: test pertama FAIL. Test kedua SUDAH PASS (baseline).

- [x] **Step 3: Implementasi minimal**

Di `index.blade.php` baris ±179-187, ganti:

```blade
{{-- Status --}}
<div>
    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Status</label>
    <select x-model="filters.status" @change="muatUlangDaftar()" class="block w-full rounded-lg border-gray-200 text-xs text-gray-800 shadow-sm focus:border-brand-500 focus:ring-brand-500 py-2">
        <option value="">— Semua Status —</option>
        @foreach (\App\Domains\Akademik\Enums\StatusRpp::cases() as $s)
            <option value="{{ $s->value }}">{{ $s->label() }}</option>
        @endforeach
    </select>
</div>
```

menjadi:

```blade
{{-- Status --}}
<div>
    <label class="mb-1.5 block text-xs font-semibold text-gray-500">
        Status
        @if ($tab === 'verifikasi')
            <span class="font-normal text-gray-400">(Inbox default: Menunggu Verifikasi)</span>
        @endif
    </label>
    <select x-model="filters.status" @change="muatUlangDaftar()" class="block w-full rounded-lg border-gray-200 text-xs text-gray-800 shadow-sm focus:border-brand-500 focus:ring-brand-500 py-2">
        <option value="">{{ $tab === 'verifikasi' ? '— Semua Status (Keluar dari Inbox Default) —' : '— Semua Status —' }}</option>
        @foreach (\App\Domains\Akademik\Enums\StatusRpp::cases() as $s)
            <option value="{{ $s->value }}">{{ $s->label() }}</option>
        @endforeach
    </select>
</div>
```

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="menampilkan label default Inbox|tidak menampilkan label default Inbox" --compact`
Expected: PASS kedua test.

- [x] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Akademik/RppWorkflowTest.php --compact`
Expected: PASS semua.

- [x] **Step 6: Commit**

```bash
git add resources/views/portals/lembaga/akademik/rpp/index.blade.php tests/Feature/Akademik/RppWorkflowTest.php
git commit -m "feat(rpp): label default Inbox pada dropdown Status tab verifikasi"
```

---

### Task 7: Keterangan Cakupan KPI

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/rpp/index.blade.php`
- Test: `tests/Feature/Akademik/RppWorkflowTest.php`

**Interfaces:**
- Tidak ada interface baru.

- [x] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('menampilkan keterangan cakupan KPI berbeda per tab', function () {
    $this->actingAs($this->userGuru)->get(route('admin.rpp.index', ['tab' => 'saya']))
        ->assertOk()->assertSee('Ringkasan dokumen Anda', false);

    $this->actingAs($this->userKurikulum)->get(route('admin.rpp.index', ['tab' => 'verifikasi']))
        ->assertOk()->assertSee('Ringkasan seluruh dokumen di lembaga ini', false);
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="menampilkan keterangan cakupan KPI" --compact`
Expected: FAIL — keterangan belum ada.

- [x] **Step 3: Implementasi minimal**

Di `index.blade.php`, sebelum baris ±39 (`<div class="grid grid-cols-1 gap-3 sm:grid-cols-4">`), tambahkan:

```blade
<p class="text-[11px] text-gray-400 -mb-1">Ringkasan {{ $tab === 'saya' ? 'dokumen Anda' : 'seluruh dokumen di lembaga ini' }} (tidak berubah mengikuti filter pencarian/Tahun Ajaran/Semester/Kelas/Mapel/Kurikulum di bawah).</p>
```

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="menampilkan keterangan cakupan KPI" --compact`
Expected: PASS.

- [x] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Akademik/RppWorkflowTest.php --compact`
Expected: PASS semua.

- [x] **Step 6: Commit**

```bash
git add resources/views/portals/lembaga/akademik/rpp/index.blade.php tests/Feature/Akademik/RppWorkflowTest.php
git commit -m "feat(rpp): keterangan cakupan KPI (tidak ikut filter kontrol)"
```

---

### Task 8: `UpdateRppRequest` — Tambah Cek Lembaga `kelas_id`

**Files:**
- Modify: `app/Http/Requests/Akademik/UpdateRppRequest.php`
- Test: `tests/Feature/Akademik/RppWorkflowTest.php`

**Interfaces:**
- Tidak ada interface baru.

- [x] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('menolak update RPP dengan kelas_id dari lembaga lain', function () {
    $file = UploadedFile::fake()->create('rpp_uji_lembaga.pdf', 200, 'application/pdf');
    $path = $file->store("rpp/{$this->lembaga->id}", 'public');
    $rpp = Rpp::create([
        'yayasan_id' => $this->yayasan->id, 'lembaga_id' => $this->lembaga->id, 'guru_id' => $this->guru->id,
        'tahun_ajaran_id' => $this->tahunAjaran->id, 'semester_id' => $this->semester->id, 'kelas_id' => $this->kelas->id,
        'mata_pelajaran_id' => $this->mapel->id, 'judul_topik' => 'Topik Sebelum Update', 'alokasi_waktu' => '2 JP',
        'file_path' => $path, 'file_name' => 'rpp_uji_lembaga.pdf', 'file_size_bytes' => 2048,
        'mime_type' => 'application/pdf', 'status' => StatusRpp::Draft,
    ]);

    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $this->yayasan->id]);
    // tahun_ajaran_id SENGAJA disamakan dengan punya RPP ($this->semester->tahun_ajaran_id)
    // supaya cek tahun-ajaran LOLOS dan cek lembaga BENAR-BENAR yang menangkap error ini,
    // bukan tertangkap lebih dulu oleh cek tahun ajaran yang sudah ada.
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $this->semester->tahun_ajaran_id]);

    $response = $this->actingAs($this->userGuru)->put(route('admin.rpp.update', $rpp), [
        'kelas_id' => $kelasLain->id,
        'judul_topik' => 'Topik Setelah Update',
        'alokasi_waktu' => '2 JP',
    ]);

    $response->assertSessionHasErrors('kelas_id');
    expect($rpp->fresh()->judul_topik)->toBe('Topik Sebelum Update');
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="menolak update RPP dengan kelas_id dari lembaga lain" --compact`
Expected: FAIL — saat ini tidak ada cek lembaga, update lolos.

- [x] **Step 3: Implementasi minimal**

Di `app/Http/Requests/Akademik/UpdateRppRequest.php`, ganti:

```php
public function withValidator(Validator $validator): void
{
    $validator->after(function (Validator $validator) {
        $kelasId = $this->input('kelas_id');
        $rpp = $this->route('rpp');
        if (! $kelasId || ! $rpp) {
            return;
        }

        $kelas = Kelas::find($kelasId);
        if ($kelas && $kelas->tahun_ajaran_id !== $rpp->semester->tahun_ajaran_id) {
            $validator->errors()->add('kelas_id', 'Kelas yang dipilih bukan berasal dari tahun ajaran yang sama dengan semester dokumen RPP ini.');
        }
    });
}
```

menjadi:

```php
public function withValidator(Validator $validator): void
{
    $validator->after(function (Validator $validator) {
        $kelasId = $this->input('kelas_id');
        $rpp = $this->route('rpp');
        if (! $kelasId || ! $rpp) {
            return;
        }

        $kelas = Kelas::find($kelasId);
        if (! $kelas) {
            return;
        }

        if ($kelas->tahun_ajaran_id !== $rpp->semester->tahun_ajaran_id) {
            $validator->errors()->add('kelas_id', 'Kelas yang dipilih bukan berasal dari tahun ajaran yang sama dengan semester dokumen RPP ini.');
        }

        if ($kelas->lembaga_id !== $rpp->lembaga_id) {
            $validator->errors()->add('kelas_id', 'Kelas yang dipilih bukan berasal dari lembaga yang sama dengan dokumen RPP ini.');
        }
    });
}
```

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="menolak update RPP dengan kelas_id dari lembaga lain" --compact`
Expected: PASS.

- [x] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Akademik/RppWorkflowTest.php tests/Feature/Akademik/RppControllerIdorTest.php tests/Feature/Akademik/StoreRppRequestKelasSemesterTest.php --compact`
Expected: PASS semua — termasuk test update RPP normal existing ("mengizinkan guru mengajukan RPP...") yang HARUS tetap lulus (kelas_id sama lembaga, tidak boleh kena error baru).

- [x] **Step 6: Commit**

```bash
git add app/Http/Requests/Akademik/UpdateRppRequest.php tests/Feature/Akademik/RppWorkflowTest.php
git commit -m "fix(rpp): tambah cek lembaga kelas_id di UpdateRppRequest, samakan dengan StoreRppRequest"
```

---

### Task 9: Urutan Hapus File Sebelum Commit di `UpdateRppAction`

**Files:**
- Modify: `app/Domains/Akademik/Actions/Rpp/UpdateRppAction.php`
- Test: `tests/Feature/Akademik/RppWorkflowTest.php`

**Interfaces:**
- Tidak ada interface baru — perilaku publik `execute()` (parameter, return type, exception) TIDAK berubah.

- [x] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('tetap mengganti berkas fisik dengan benar saat update RPP dengan file baru (regresi urutan hapus-setelah-commit)', function () {
    $fileLama = UploadedFile::fake()->create('rpp_lama.pdf', 200, 'application/pdf');
    $pathLama = $fileLama->store("rpp/{$this->lembaga->id}", 'public');
    $rpp = Rpp::create([
        'yayasan_id' => $this->yayasan->id, 'lembaga_id' => $this->lembaga->id, 'guru_id' => $this->guru->id,
        'tahun_ajaran_id' => $this->tahunAjaran->id, 'semester_id' => $this->semester->id, 'kelas_id' => $this->kelas->id,
        'mata_pelajaran_id' => $this->mapel->id, 'judul_topik' => 'Topik File Lama', 'alokasi_waktu' => '2 JP',
        'file_path' => $pathLama, 'file_name' => 'rpp_lama.pdf', 'file_size_bytes' => 2048,
        'mime_type' => 'application/pdf', 'status' => StatusRpp::Draft,
    ]);
    Storage::disk('public')->assertExists($pathLama);

    $fileBaru = UploadedFile::fake()->create('rpp_baru.pdf', 300, 'application/pdf');
    $response = $this->actingAs($this->userGuru)->put(route('admin.rpp.update', $rpp), [
        'kelas_id' => $this->kelas->id,
        'judul_topik' => 'Topik File Baru',
        'alokasi_waktu' => '2 JP',
        'file' => $fileBaru,
    ]);

    $response->assertRedirect();
    $rppFresh = $rpp->fresh();
    expect($rppFresh->file_name)->toBe('rpp_baru.pdf');
    Storage::disk('public')->assertMissing($pathLama);
    Storage::disk('public')->assertExists($rppFresh->file_path);
});
```

- [x] **Step 2: Jalankan test, pastikan LULUS (baseline, belum ada perubahan kode)**

Run: `php artisan test --filter="tetap mengganti berkas fisik dengan benar" --compact`
Expected: PASS — ini test REGRESI untuk memastikan refactor Task ini TIDAK mengubah perilaku yang benar (file lama tetap terhapus, file baru tetap tersimpan) — bukan test fitur baru. Kalau test ini gagal SEBELUM refactor, STOP dan laporkan ke user (berarti pemahaman kode saat ini salah).

- [x] **Step 3: Implementasi**

Di `app/Domains/Akademik/Actions/Rpp/UpdateRppAction.php`, ganti seluruh method `execute()`:

```php
public function execute(Rpp $rpp, RppData $data): Rpp
{
    if (! $rpp->canBeEditedByGuru()) {
        throw ValidationException::withMessages([
            'status' => 'Dokumen RPP ini sedang diverifikasi atau sudah disetujui, sehingga tidak dapat disunting.',
        ]);
    }

    $storedPath = $rpp->file_path;
    $originalFileName = $rpp->file_name;
    $fileSize = $rpp->file_size_bytes;
    $mimeType = $rpp->mime_type;

    if ($data->file) {
        if ($rpp->file_path && Storage::disk('public')->exists($rpp->file_path)) {
            Storage::disk('public')->delete($rpp->file_path);
        }

        $file = $data->file;
        $originalFileName = $file->getClientOriginalName();
        $fileSize = $file->getSize();
        $mimeType = $file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream';
        $storedPath = $file->store("rpp/{$data->lembagaId}", 'public');
    }

    return DB::transaction(function () use ($rpp, $data, $storedPath, $originalFileName, $fileSize, $mimeType) {
        $rpp->update([
            'kelas_id' => $data->kelasId,
            'mata_pelajaran_id' => $data->mataPelajaranId,
            'judul_topik' => $data->judulTopik,
            'alokasi_waktu' => $data->alokasiWaktu,
            'pertemuan_ke' => $data->pertemuanKe,
            'file_path' => $storedPath,
            'file_name' => $originalFileName,
            'file_size_bytes' => $fileSize,
            'mime_type' => $mimeType,
        ]);

        return $rpp->fresh();
    });
}
```

menjadi:

```php
public function execute(Rpp $rpp, RppData $data): Rpp
{
    if (! $rpp->canBeEditedByGuru()) {
        throw ValidationException::withMessages([
            'status' => 'Dokumen RPP ini sedang diverifikasi atau sudah disetujui, sehingga tidak dapat disunting.',
        ]);
    }

    $storedPath = $rpp->file_path;
    $originalFileName = $rpp->file_name;
    $fileSize = $rpp->file_size_bytes;
    $mimeType = $rpp->mime_type;
    $fileBerubah = false;

    if ($data->file) {
        $fileBerubah = true;
        $file = $data->file;
        $originalFileName = $file->getClientOriginalName();
        $fileSize = $file->getSize();
        $mimeType = $file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream';
        $storedPath = $file->store("rpp/{$data->lembagaId}", 'public');
    }

    return DB::transaction(function () use ($rpp, $data, $storedPath, $originalFileName, $fileSize, $mimeType, $fileBerubah) {
        $oldFilePath = $rpp->file_path;

        $rpp->update([
            'kelas_id' => $data->kelasId,
            'mata_pelajaran_id' => $data->mataPelajaranId,
            'judul_topik' => $data->judulTopik,
            'alokasi_waktu' => $data->alokasiWaktu,
            'pertemuan_ke' => $data->pertemuanKe,
            'file_path' => $storedPath,
            'file_name' => $originalFileName,
            'file_size_bytes' => $fileSize,
            'mime_type' => $mimeType,
        ]);

        if ($fileBerubah && $oldFilePath && Storage::disk('public')->exists($oldFilePath)) {
            Storage::disk('public')->delete($oldFilePath);
        }

        return $rpp->fresh();
    });
}
```

- [x] **Step 4: Jalankan test, pastikan TETAP lulus setelah refactor**

Run: `php artisan test --filter="tetap mengganti berkas fisik dengan benar" --compact`
Expected: PASS — perilaku observable SAMA, cuma urutan internal berubah.

- [x] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Akademik/RppWorkflowTest.php --compact`
Expected: PASS semua.

- [x] **Step 6: Commit**

```bash
git add app/Domains/Akademik/Actions/Rpp/UpdateRppAction.php tests/Feature/Akademik/RppWorkflowTest.php
git commit -m "fix(rpp): pindahkan hapus file lama ke SETELAH commit transaksi (hindari state tidak konsisten kalau update gagal)"
```

---

### Task 10: Penutup — Regresi Penuh, Pint, Verifikasi Manual

**Files:**
- Tidak ada file baru — task verifikasi murni.

- [x] **Step 1: Jalankan seluruh test domain RPP**

Run: `php artisan test --compact --filter="RppWorkflowTest|RppControllerIdorTest|RppKurikulumReportingTest|StoreRppRequestKelasSemesterTest|RppVerifyTest"`
Expected: PASS semua, 0 gagal.

- [x] **Step 2: Jalankan Pint pada file yang diubah**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}`.

- [x] **Step 3: Verifikasi manual via browser (WAJIB)**

Login sebagai role tanpa profil Guru (mis. `operator_akademik`): buka RPP, pastikan tab "Perangkat Ajar Saya" KOSONG dengan pesan jelas ("Akun Anda tidak terhubung dengan profil Guru..."), BUKAN menampilkan RPP guru lain.

Login sebagai guru: buka tab "Saya", pastikan cuma lihat RPP sendiri, tombol Edit/Hapus/Ajukan berfungsi normal.

Login sebagai verifikator (`wakasek_kurikulum`/`kepala_sekolah`/`operator_akademik`): buka "Inbox Verifikasi Kurikulum" — dialog "Ajukan ke Kurikulum" (dari sisi guru) sekarang bilang "pihak kurikulum" bukan "Waka Kurikulum". Dropdown Status di tab ini punya label "(Inbox default: Menunggu Verifikasi)". Filter dengan kriteria yang pasti 0 hasil — pesan kosong bilang "cocok dengan filter", BUKAN "semua sudah ditinjau". Hapus semua filter — pesan kembali ke "semua sudah ditinjau".

Login sebagai YAYASAN-scope verifikator, mode "Semua Lembaga": badge ungu "Semua Lembaga" di header. Switch ke 1 lembaga: badge berubah nama lembaga, brand color.

Edit RPP existing dengan ganti berkas baru: pastikan berkas lama benar-benar terganti (bukan menumpuk), tidak ada error.

- [x] **Step 4: Laporkan hasil**

TIDAK perlu menulis file handoff log baru di task ini — kalau user menghendaki log terpisah, itu permintaan tambahan setelah plan ini selesai.

---

## Self-Review

**1. Spec coverage** — SEMUA 10 item spec `.agents/specs/2026-09-09-rpp-audit-workflow-perbaikan.md` tercakup: Item A → Task 1, Item B → Task 2, Item D → Task 3, Item G → Task 4, Item C → Task 5, Item E → Task 6, Item F → Task 7, Item H → Task 8, Item I → Task 9. Task 10 menutup dengan regresi + Pint + verifikasi manual.

**2. Placeholder scan** — tidak ada "TBD"/dst. Semua step berisi kode lengkap, ditranskripsi persis dari spec yang sudah 2x direview (termasuk 3 koreksi: klaim stats yang salah di Item F, `$adaFilterAktif` kurang cek status di Item D, dan penggabungan Item D+G di cabang ajax).

**3. Type consistency** — `targetLembagaId` (Task 2, sekarang parameter bukan hasil resolve internal) dipakai konsisten di Task 4. `isYayasan`/`activeLembaga` (Task 4) nama variabel sama persis dengan pola menu lain.

**Catatan tambahan hasil self-review**:
- Task 1 SENGAJA jadi task pertama — ini temuan KRITIS (kebocoran data antar-guru), tidak boleh menunggu task lain.
- Task 2, 3 WAJIB selesai sebelum Task 4 — dicatat eksplisit di Global Constraints DAN di header Task 4 sendiri sebagai prasyarat, supaya kalau plan ini dieksekusi per-task oleh subagent terpisah, urutannya tidak sampai terbalik.
- Task 9 Step 1-2 SENGAJA pola "test regresi dulu, verifikasi baseline lulus SEBELUM refactor" (bukan TDD murni tulis-gagal-dulu) — karena Task ini murni refactor urutan internal, bukan fitur baru; kalau baseline test gagal SEBELUM refactor, itu tanda pemahaman kode salah, bukan bug yang mau diperbaiki task ini.
- Task 8's test menggunakan `Kelas::factory()->withoutGlobalScopes()->create(...)` supaya kelas lembaga lain bisa dibuat lewat factory standar (factory biasanya auto-assign `lembaga_id` dari aktor yang login lewat `BelongsToTenant`, perlu di-bypass eksplisit untuk membuat data lembaga LAIN sebagai fixture test).
