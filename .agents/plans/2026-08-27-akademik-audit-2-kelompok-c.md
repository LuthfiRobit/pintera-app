# Audit Sistematis Akademik Tahap 2 — Kelompok C (RPP Reporting & Test Coverage) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambah reporting kurikulum di daftar RPP (badge + filter dua arah), validasi konsistensi kelas-semester di form RPP, dan test regresi cross-tenant IDOR untuk ekstrakurikuler — kelompok TERAKHIR dari audit sistematis tahap 2, diakhiri checkpoint full test suite gabungan Kelompok B+C.

**Architecture:** Task 1 murni menambah query filter + badge tampilan (reuse relasi `kelas` yang sudah eager-loaded, tanpa ubah skema). Task 2 menambah lapis validasi relasional (`withValidator()`) di atas `exists:` yang sudah ada, tanpa mengubah DTO/Action. Task 3 murni test tambahan tanpa perubahan kode produksi.

**Tech Stack:** Laravel 12.68, Pest v4, Blade + Alpine.js (pola AJAX-fragment yang sudah baku di halaman ini — `rpp.js::muatUlangDaftar()` generik meng-iterasi objek `filters`, jadi field baru otomatis ikut terkirim tanpa perubahan JS).

## Global Constraints

- `kurikulum` sbg query param HARUS divalidasi terhadap `KurikulumFramework::cases()` di `RppController::index()` — nilai tidak dikenal (mis. `?kurikulum=foobar`) fallback ke `null` (= tanpa filter), request tetap `assertOk()`, TIDAK error, TIDAK menghasilkan hasil kosong.
- Filter kurikulum WAJIB konsisten di kedua jalur: request biasa (full-page `index`) dan request AJAX (`X-Requested-With: XMLHttpRequest`, fragment `_daftar`). Ini otomatis terjamin selama parameter ditambahkan ke SATU pemanggilan `$this->listRppAction->execute(...)` yang sudah ada sebelum percabangan `$request->ajax()` — JANGAN buat cabang kode terpisah untuk filter kurikulum di masing-masing jalur.
- `withValidator()->after()` di `StoreRppRequest`/`UpdateRppRequest` MELENGKAPI rule `exists:kelas,id`/`exists:semester,id` yang sudah ada — bukan pengganti. Kedua rule tetap ada.
- Tidak ada perubahan skema tabel `rpp`. Tidak ada perubahan `RppData`/`CreateRppAction`/`UpdateRppAction`. Tidak ada perubahan `EkstrakurikulerController` (kode-nya sudah benar, Task 3 murni test).
- Test yang membuktikan markup Blade WAJIB di-scope ke baris yang benar (bukan `assertSee()` global) — proyek ini tidak punya `symfony/dom-crawler`, gunakan pencarian posisi `<tr`/substring manual seperti pola yang sudah dipakai di Kelompok B.
- `assertSessionHasErrors(['kelas_id'])` HARUS spesifik menyebut field, bukan `assertSessionHasErrors()` generik.
- Test IDOR (Task 3) HARUS memakai 2 lembaga + 2 manager yang benar-benar terpisah (bukan 2 record di 1 lembaga yang sama).
- Jalankan `vendor/bin/pint --dirty --format agent` di akhir setiap task sebelum commit.
- Test scoped per task (Task 1 & 2). **Task 3 Step terakhir WAJIB menjalankan full test suite (`php artisan test --compact`, TANPA filter) sebagai checkpoint penutup gabungan Kelompok B+C** — ini SATU-SATUNYA titik di seluruh audit tahap 2 di mana full suite dijalankan, jangan dijalankan di task/step lain.

---

### Task 1: Badge & filter kurikulum di daftar RPP

**Files:**
- Modify: `app/Domains/Akademik/Actions/Rpp/ListRppAction.php`
- Modify: `app/Http/Controllers/Admin/RppController.php:47-129`
- Modify: `resources/views/portals/lembaga/akademik/rpp/_daftar.blade.php:186-190`
- Modify: `resources/views/portals/lembaga/akademik/rpp/index.blade.php:1-16, 189-198`
- Test: `tests/Feature/Akademik/RppKurikulumReportingTest.php` (BARU)

**Interfaces:**
- Produces: `ListRppAction::execute(..., ?string $kurikulum = null)` — parameter baru, opsional, ditambahkan di AKHIR daftar parameter existing supaya tidak mengubah urutan named-argument call yang sudah ada di `RppController::index()`.

- [ ] **Step 1: Baca file existing untuk konfirmasi baseline**

Baca `app/Domains/Akademik/Actions/Rpp/ListRppAction.php`, `app/Http/Controllers/Admin/RppController.php` baris 47-129, `resources/views/portals/lembaga/akademik/rpp/_daftar.blade.php` baris 186-190, dan `resources/views/portals/lembaga/akademik/rpp/index.blade.php` baris 1-16 & 189-198 — pastikan cocok dengan kutipan di step-step berikutnya. Kalau beda, STOP dan laporkan ke user.

- [ ] **Step 2: Tulis test yang gagal — filter dua arah + badge scoped + kontrak AJAX + nilai invalid**

Buat `tests/Feature/Akademik/RppKurikulumReportingTest.php`:

```php
<?php

use App\Domains\Akademik\Enums\StatusRpp;
use App\Domains\Akademik\Models\Rpp;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function siapkanRppKurikulumFixture(): array
{
    Permission::firstOrCreate(['name' => 'rpp.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'wakasek_kurikulum_reporting', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['rpp.view']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'status_aktif' => true]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $userKurikulum = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userKurikulum->assignRole($role);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $kelasMerdeka = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Kelas Merdeka X', 'kurikulum' => 'merdeka']);
    $kelasK13 = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Kelas K13 Y', 'kurikulum' => 'k13']);
    $kelasLegacy = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Kelas Legacy Z', 'kurikulum' => null]);

    $rppMerdeka = Rpp::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'guru_id' => $guru->id,
        'tahun_ajaran_id' => $tahunAjaran->id, 'semester_id' => $semester->id, 'kelas_id' => $kelasMerdeka->id,
        'mata_pelajaran_id' => $mapel->id, 'judul_topik' => 'Topik RPP Merdeka Unik',
        'alokasi_waktu' => '2 JP', 'file_path' => 'rpp/fake-merdeka.pdf', 'file_name' => 'fake-merdeka.pdf',
        'mime_type' => 'application/pdf', 'status' => StatusRpp::Draft,
    ]);
    $rppK13 = Rpp::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'guru_id' => $guru->id,
        'tahun_ajaran_id' => $tahunAjaran->id, 'semester_id' => $semester->id, 'kelas_id' => $kelasK13->id,
        'mata_pelajaran_id' => $mapel->id, 'judul_topik' => 'Topik RPP K13 Unik',
        'alokasi_waktu' => '2 JP', 'file_path' => 'rpp/fake-k13.pdf', 'file_name' => 'fake-k13.pdf',
        'mime_type' => 'application/pdf', 'status' => StatusRpp::Draft,
    ]);
    $rppLegacy = Rpp::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'guru_id' => $guru->id,
        'tahun_ajaran_id' => $tahunAjaran->id, 'semester_id' => $semester->id, 'kelas_id' => $kelasLegacy->id,
        'mata_pelajaran_id' => $mapel->id, 'judul_topik' => 'Topik RPP Legacy Unik',
        'alokasi_waktu' => '2 JP', 'file_path' => 'rpp/fake-legacy.pdf', 'file_name' => 'fake-legacy.pdf',
        'mime_type' => 'application/pdf', 'status' => StatusRpp::Draft,
    ]);

    return [$userKurikulum, $rppMerdeka, $rppK13, $rppLegacy];
}

function chunkSekitarTeks(string $html, string $penanda): string
{
    $pos = strpos($html, $penanda);
    expect($pos)->not->toBeFalse();
    $trOpenPos = strrpos(substr($html, 0, $pos), '<tr');
    expect($trOpenPos)->not->toBeFalse();

    return substr($html, $trOpenPos, ($pos - $trOpenPos) + 3000);
}

it('shows the correct kurikulum badge scoped to each RPP row, including legacy null', function () {
    [$userKurikulum, $rppMerdeka, $rppK13, $rppLegacy] = siapkanRppKurikulumFixture();

    // Existence dulu: ketiga RPP benar-benar tersimpan sebelum assert badge-nya.
    expect(Rpp::whereIn('id', [$rppMerdeka->id, $rppK13->id, $rppLegacy->id])->count())->toBe(3);

    $response = $this->actingAs($userKurikulum)->get(route('admin.rpp.index', ['tab' => 'saya']));
    $response->assertOk();
    $html = $response->getContent();

    $chunkMerdeka = chunkSekitarTeks($html, 'Topik RPP Merdeka Unik');
    expect($chunkMerdeka)->toContain('Kurikulum Merdeka');

    $chunkK13 = chunkSekitarTeks($html, 'Topik RPP K13 Unik');
    expect($chunkK13)->toContain('Kurikulum 2013 (K13)');

    $chunkLegacy = chunkSekitarTeks($html, 'Topik RPP Legacy Unik');
    expect($chunkLegacy)->toContain('Belum Diketahui');
});

it('filters to only Merdeka RPP when kurikulum=merdeka, both on full-page and AJAX fragment requests', function () {
    [$userKurikulum, $rppMerdeka, $rppK13, $rppLegacy] = siapkanRppKurikulumFixture();

    $fullPage = $this->actingAs($userKurikulum)->get(route('admin.rpp.index', ['tab' => 'saya', 'kurikulum' => 'merdeka']));
    $fullPage->assertOk();
    $fullPage->assertViewHas('rppList', fn ($list) => $list->pluck('id')->contains($rppMerdeka->id) && ! $list->pluck('id')->contains($rppK13->id));

    $ajax = $this->actingAs($userKurikulum)->get(route('admin.rpp.index', ['tab' => 'saya', 'kurikulum' => 'merdeka']), [
        'X-Requested-With' => 'XMLHttpRequest',
    ]);
    $ajax->assertOk();
    $ajaxHtml = $ajax->getContent();
    expect($ajaxHtml)->toContain('Topik RPP Merdeka Unik');
    expect($ajaxHtml)->not->toContain('Topik RPP K13 Unik');
});

it('filters to only K13 RPP when kurikulum=k13', function () {
    [$userKurikulum, $rppMerdeka, $rppK13, $rppLegacy] = siapkanRppKurikulumFixture();

    $response = $this->actingAs($userKurikulum)->get(route('admin.rpp.index', ['tab' => 'saya', 'kurikulum' => 'k13']));
    $response->assertOk();
    $response->assertViewHas('rppList', fn ($list) => $list->pluck('id')->contains($rppK13->id) && ! $list->pluck('id')->contains($rppMerdeka->id));
});

it('treats an unknown kurikulum value as no filter at all, without erroring', function () {
    [$userKurikulum, $rppMerdeka, $rppK13, $rppLegacy] = siapkanRppKurikulumFixture();

    $response = $this->actingAs($userKurikulum)->get(route('admin.rpp.index', ['tab' => 'saya', 'kurikulum' => 'foobar']));

    $response->assertOk();
    $response->assertViewHas('rppList', fn ($list) => $list->pluck('id')->contains($rppMerdeka->id) && $list->pluck('id')->contains($rppK13->id) && $list->pluck('id')->contains($rppLegacy->id));
});
```

- [ ] **Step 3: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Feature/Akademik/RppKurikulumReportingTest.php --compact`
Expected: FAIL — belum ada badge kurikulum di markup, belum ada parameter `kurikulum` di `ListRppAction`.

- [ ] **Step 4: Tambah parameter `kurikulum` ke `ListRppAction`**

Edit `app/Domains/Akademik/Actions/Rpp/ListRppAction.php`. Ubah signature `execute()` (tambah parameter baru di AKHIR daftar, sebelum `int $perPage`):

```php
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
```

Tambah filter setelah blok `if ($mapelId) { ... }` (sebelum blok `if ($status && ...)`):

```php
        if ($kurikulum) {
            $query->whereHas('kelas', fn ($q) => $q->where('kurikulum', $kurikulum));
        }
```

- [ ] **Step 5: Update `RppController::index()` — validasi enum + teruskan filter**

Edit `app/Http/Controllers/Admin/RppController.php`. Tambah import:

```php
use App\Domains\Akademik\Enums\KurikulumFramework;
```

Tambah baris setelah `$status = $request->query('status');` (baris 62):

```php
        $kurikulum = $request->query('kurikulum');
        if ($kurikulum !== null && ! in_array($kurikulum, array_column(KurikulumFramework::cases(), 'value'), true)) {
            $kurikulum = null;
        }
```

Tambah `kurikulum: $kurikulum,` sbg argumen terakhir named-argument call ke `$this->listRppAction->execute(...)` (baris 73-83):

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

Tambah `'kurikulum' => $kurikulum,` ke array data view (baik untuk cabang `$request->ajax()` maupun full-page — CATATAN: cabang ajax saat ini hanya `compact('rppList', 'tab', 'perPage')`, tidak perlu `kurikulum` di situ karena `_daftar.blade.php` tidak memakai variabel itu, filter sudah terwujud lewat isi `$rppList` yang sudah terfilter). Tambah ke array view full-page (baris 113-129), setelah `'mapelId' => $mapelId,`:

```php
            'kurikulum' => $kurikulum,
```

- [ ] **Step 6: Update view — badge kurikulum di `_daftar.blade.php`**

Edit `resources/views/portals/lembaga/akademik/rpp/_daftar.blade.php`, ubah blok "Kelas & Semester" (baris 186-190) dari:

```blade
                        {{-- Kelas & Semester --}}
                        <td class="px-5 py-3.5 text-gray-700 text-xs">
                            <span class="font-bold text-gray-900">{{ $rpp->kelas->nama }}</span>
                            <p class="text-gray-500 text-[11px]">{{ $rpp->semester->nama }} &bull; {{ $rpp->tahunAjaran->nama ?? '' }}</p>
                        </td>
```

menjadi:

```blade
                        {{-- Kelas & Semester --}}
                        <td class="px-5 py-3.5 text-gray-700 text-xs">
                            <span class="font-bold text-gray-900">{{ $rpp->kelas->nama }}</span>
                            <p class="text-gray-500 text-[11px]">{{ $rpp->semester->nama }} &bull; {{ $rpp->tahunAjaran->nama ?? '' }}</p>
                            @if ($rpp->kelas->kurikulum)
                                <x-badge tone="{{ $rpp->kelas->kurikulum->value === 'merdeka' ? 'green' : 'blue' }}">{{ $rpp->kelas->kurikulum->label() }}</x-badge>
                            @else
                                <x-badge tone="slate">Belum Diketahui</x-badge>
                            @endif
                        </td>
```

- [ ] **Step 7: Update view — dropdown filter kurikulum di `index.blade.php`**

Edit `resources/views/portals/lembaga/akademik/rpp/index.blade.php`. Tambah `kurikulum` ke objek `filters` di `x-data` (baris 4-16), setelah baris `status: @js($status ?? '')`:

```blade
        x-data="rppPageManager({
            filters: {
                tab: @js($tab),
                search: @js(request('search', '')),
                tahun_ajaran_id: @js($tahunAjaranId),
                semester_id: @js($semesterId ?? ''),
                kelas_id: @js($kelasId ?? ''),
                mata_pelajaran_id: @js($mapelId ?? ''),
                status: @js($status ?? ''),
                kurikulum: @js($kurikulum ?? '')
            },
            perPage: @js($perPage ?? 20),
            indexUrlBase: @js(route('admin.rpp.index'))
        })"
```

Tambah dropdown baru setelah blok filter Mata Pelajaran (baris 189-198), sebelum penutup `</div>` filter container:

```blade
            {{-- Kurikulum Filter (Row 2) --}}
            <div class="pt-2 flex items-center gap-3">
                <span class="text-xs font-semibold text-gray-500 whitespace-nowrap">Kurikulum:</span>
                <select x-model="filters.kurikulum" @change="muatUlangDaftar()" class="block w-full max-w-sm rounded-lg border-gray-200 text-xs text-gray-800 shadow-sm focus:border-brand-500 focus:ring-brand-500 py-1.5">
                    <option value="">— Semua Kurikulum —</option>
                    @foreach (\App\Domains\Akademik\Enums\KurikulumFramework::cases() as $kf)
                        <option value="{{ $kf->value }}">{{ $kf->label() }}</option>
                    @endforeach
                </select>
            </div>
```

**Tidak perlu ubah `resources/js/rpp.js`** — `muatUlangDaftar()` sudah men-generik-kan iterasi `Object.entries(this.filters)`, jadi key `kurikulum` otomatis ikut terkirim di query string tanpa perubahan JS.

- [ ] **Step 8: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Feature/Akademik/RppKurikulumReportingTest.php --compact`
Expected: PASS, 4/4 test.

- [ ] **Step 9: Jalankan test RPP existing supaya tidak regresi**

Run: `php artisan test tests/Feature/Akademik/RppWorkflowTest.php --compact`
Expected: PASS, semua test lama tetap lulus (parameter `kurikulum` opsional dgn default `null` tidak mengubah pemanggilan lain).

- [ ] **Step 10: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domains/Akademik/Actions/Rpp/ListRppAction.php app/Http/Controllers/Admin/RppController.php resources/views/portals/lembaga/akademik/rpp/_daftar.blade.php resources/views/portals/lembaga/akademik/rpp/index.blade.php tests/Feature/Akademik/RppKurikulumReportingTest.php
git commit -m "feat(akademik): badge dan filter kurikulum di daftar RPP"
```

---

### Task 2: Validasi konsistensi kelas-semester di form RPP

**Files:**
- Modify: `app/Http/Requests/Akademik/StoreRppRequest.php`
- Modify: `app/Http/Requests/Akademik/UpdateRppRequest.php`
- Test: `tests/Feature/Akademik/StoreRppRequestKelasSemesterTest.php` (BARU)

**Interfaces:**
- Tidak ada perubahan interface publik — `withValidator()` adalah hook standar Laravel `FormRequest`, dipanggil otomatis oleh framework.

- [ ] **Step 1: Baca file existing untuk konfirmasi baseline**

Baca `app/Http/Requests/Akademik/StoreRppRequest.php` dan `app/Http/Requests/Akademik/UpdateRppRequest.php` penuh — pastikan cocok dengan kutipan Step 3/5. Kalau beda, STOP dan laporkan.

- [ ] **Step 2: Tulis test yang gagal**

Buat `tests/Feature/Akademik/StoreRppRequestKelasSemesterTest.php`:

```php
<?php

use App\Domains\Akademik\Models\Rpp;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

function siapkanRppRequestFixture(): array
{
    Storage::fake('public');
    Permission::firstOrCreate(['name' => 'rpp.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'rpp.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru_rpp_validasi', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['rpp.view', 'rpp.kelola']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunA = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2025/2026']);
    $tahunB = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2026/2027']);
    $semesterTahunA = Semester::factory()->create(['tahun_ajaran_id' => $tahunA->id]);
    $kelasTahunA = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunA->id]);
    $kelasTahunB = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunB->id]);

    $userGuru = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userGuru->assignRole($role);
    $guru = Guru::factory()->create(['user_id' => $userGuru->id, 'lembaga_id' => $lembaga->id]);

    return [$userGuru, $semesterTahunA, $kelasTahunA, $kelasTahunB];
}

it('rejects store when kelas belongs to a different tahun ajaran than the selected semester', function () {
    [$userGuru, $semesterTahunA, $kelasTahunA, $kelasTahunB] = siapkanRppRequestFixture();
    $file = UploadedFile::fake()->create('rpp.pdf', 100, 'application/pdf');

    $response = $this->actingAs($userGuru)->post(route('admin.rpp.store'), [
        'kelas_id' => $kelasTahunB->id,
        'semester_id' => $semesterTahunA->id,
        'judul_topik' => 'Topik Tidak Konsisten',
        'alokasi_waktu' => '2 JP',
        'file' => $file,
    ]);

    $response->assertSessionHasErrors(['kelas_id']);
    $this->assertDatabaseMissing('rpp', ['judul_topik' => 'Topik Tidak Konsisten']);
});

it('allows store when kelas and semester share the same tahun ajaran', function () {
    [$userGuru, $semesterTahunA, $kelasTahunA, $kelasTahunB] = siapkanRppRequestFixture();
    $file = UploadedFile::fake()->create('rpp.pdf', 100, 'application/pdf');

    $response = $this->actingAs($userGuru)->post(route('admin.rpp.store'), [
        'kelas_id' => $kelasTahunA->id,
        'semester_id' => $semesterTahunA->id,
        'judul_topik' => 'Topik Konsisten',
        'alokasi_waktu' => '2 JP',
        'file' => $file,
    ]);

    $response->assertSessionDoesntHaveErrors();
    $this->assertDatabaseHas('rpp', ['judul_topik' => 'Topik Konsisten', 'kelas_id' => $kelasTahunA->id]);
});

it('rejects update when the new kelas belongs to a different tahun ajaran than the RPP semester', function () {
    [$userGuru, $semesterTahunA, $kelasTahunA, $kelasTahunB] = siapkanRppRequestFixture();
    $guru = Guru::where('user_id', $userGuru->id)->first();
    $rpp = Rpp::create([
        'yayasan_id' => $kelasTahunA->lembaga->yayasan_id, 'lembaga_id' => $kelasTahunA->lembaga_id, 'guru_id' => $guru->id,
        'tahun_ajaran_id' => $semesterTahunA->tahun_ajaran_id, 'semester_id' => $semesterTahunA->id, 'kelas_id' => $kelasTahunA->id,
        'judul_topik' => 'RPP Sebelum Update', 'alokasi_waktu' => '2 JP',
        'file_path' => 'rpp/existing.pdf', 'file_name' => 'existing.pdf', 'mime_type' => 'application/pdf',
        'status' => \App\Domains\Akademik\Enums\StatusRpp::Draft,
    ]);

    $response = $this->actingAs($userGuru)->put(route('admin.rpp.update', $rpp), [
        'kelas_id' => $kelasTahunB->id,
        'judul_topik' => 'RPP Sesudah Update',
        'alokasi_waktu' => '2 JP',
    ]);

    $response->assertSessionHasErrors(['kelas_id']);
    expect($rpp->fresh()->kelas_id)->toBe($kelasTahunA->id);
});

it('allows update when the new kelas shares the same tahun ajaran as the RPP semester', function () {
    [$userGuru, $semesterTahunA, $kelasTahunA, $kelasTahunB] = siapkanRppRequestFixture();
    $guru = Guru::where('user_id', $userGuru->id)->first();
    $kelasTahunALain = Kelas::factory()->create(['lembaga_id' => $kelasTahunA->lembaga_id, 'tahun_ajaran_id' => $kelasTahunA->tahun_ajaran_id]);
    $rpp = Rpp::create([
        'yayasan_id' => $kelasTahunA->lembaga->yayasan_id, 'lembaga_id' => $kelasTahunA->lembaga_id, 'guru_id' => $guru->id,
        'tahun_ajaran_id' => $semesterTahunA->tahun_ajaran_id, 'semester_id' => $semesterTahunA->id, 'kelas_id' => $kelasTahunA->id,
        'judul_topik' => 'RPP Sebelum Update Valid', 'alokasi_waktu' => '2 JP',
        'file_path' => 'rpp/existing2.pdf', 'file_name' => 'existing2.pdf', 'mime_type' => 'application/pdf',
        'status' => \App\Domains\Akademik\Enums\StatusRpp::Draft,
    ]);

    $response = $this->actingAs($userGuru)->put(route('admin.rpp.update', $rpp), [
        'kelas_id' => $kelasTahunALain->id,
        'judul_topik' => 'RPP Sesudah Update Valid',
        'alokasi_waktu' => '2 JP',
    ]);

    $response->assertSessionDoesntHaveErrors();
    expect($rpp->fresh()->kelas_id)->toBe($kelasTahunALain->id);
});
```

- [ ] **Step 3: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Feature/Akademik/StoreRppRequestKelasSemesterTest.php --compact`
Expected: FAIL — 2 dari 4 test gagal (kombinasi tidak konsisten masih diterima karena belum ada validasi relasional).

- [ ] **Step 4: Tambah `withValidator()` ke `StoreRppRequest`**

Edit `app/Http/Requests/Akademik/StoreRppRequest.php`. Tambah import:

```php
use App\Models\Kelas;
use App\Models\Semester;
use Illuminate\Validation\Validator;
```

(Cek dulu — `App\Models\Kelas` dan `App\Models\Semester` sudah di-import di file ini untuk method `toDTO()`, jangan duplikasi import.)

Tambah method baru setelah `rules()`, sebelum `messages()`:

```php
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $kelasId = $this->input('kelas_id');
            $semesterId = $this->input('semester_id');
            if (! $kelasId || ! $semesterId) {
                return;
            }

            $kelas = Kelas::find($kelasId);
            $semester = Semester::find($semesterId);
            if ($kelas && $semester && $kelas->tahun_ajaran_id !== $semester->tahun_ajaran_id) {
                $validator->errors()->add('kelas_id', 'Kelas yang dipilih bukan berasal dari tahun ajaran yang sama dengan semester ini.');
            }
        });
    }
```

- [ ] **Step 5: Tambah `withValidator()` ke `UpdateRppRequest`**

Edit `app/Http/Requests/Akademik/UpdateRppRequest.php`. Tambah import:

```php
use Illuminate\Validation\Validator;
```

(`App\Models\Kelas` sudah di-import di file ini.)

Tambah method baru setelah `rules()`, sebelum `messages()`:

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

- [ ] **Step 6: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Feature/Akademik/StoreRppRequestKelasSemesterTest.php --compact`
Expected: PASS, 4/4 test.

- [ ] **Step 7: Jalankan test RPP existing supaya tidak regresi**

Run: `php artisan test tests/Feature/Akademik/RppWorkflowTest.php --compact`
Expected: PASS — SEMUA test existing memakai kombinasi kelas+semester yang konsisten (dari `beforeEach` yang sama), jadi validasi baru tidak boleh menolaknya. Kalau ada yang gagal, itu tanda validasi baru terlalu ketat — perbaiki kode Step 4/5, JANGAN ubah test existing.

- [ ] **Step 8: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Akademik/StoreRppRequest.php app/Http/Requests/Akademik/UpdateRppRequest.php tests/Feature/Akademik/StoreRppRequestKelasSemesterTest.php
git commit -m "feat(akademik): validasi konsistensi kelas-semester pada form RPP"
```

---

### Task 3: Test regresi cross-tenant IDOR ekstrakurikuler + checkpoint full suite

**Files:**
- Modify: `tests/Feature/Admin/LembagaRelationalManagementTest.php` (tambah 2 test, TIDAK ADA perubahan kode produksi)

**Interfaces:**
- Tidak ada — murni test tambahan terhadap `EkstrakurikulerController` existing.

- [ ] **Step 1: Baca file existing untuk konfirmasi baseline**

Baca `tests/Feature/Admin/LembagaRelationalManagementTest.php` baris 1-53 dan `app/Http/Controllers/Admin/Lembaga/EkstrakurikulerController.php` penuh — pastikan cocok dengan pemahaman di plan ini (guard `abort_unless($ekstrakurikuler->lembaga_id === $lembaga->id, 404)` di `update()`/`destroy()`). Kalau beda, STOP dan laporkan.

- [ ] **Step 2: Tulis test yang gagal — 2 lembaga + 2 manager terpisah**

Tambahkan di akhir `tests/Feature/Admin/LembagaRelationalManagementTest.php` (gunakan import yang sudah ada di file: `EkstrakurikulerLembaga`, `Lembaga`, `Role`, `User`, `Yayasan`, `Permission`):

```php
it('rejects updating an ekstrakurikuler that belongs to a different lembaga (cross-tenant IDOR)', function () {
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $this->yayasan->id]);
    $managerB = User::factory()->create(['yayasan_id' => $this->yayasan->id]);
    $managerB->assignRole($this->role);

    $ekskulLembagaA = EkstrakurikulerLembaga::create([
        'lembaga_id' => $this->lembaga->id,
        'jenis_ekskul' => 'wajib',
        'nama_ekskul' => 'Pramuka Lembaga A Asli',
    ]);

    $response = $this->actingAs($managerB)->put(
        route('admin.lembaga.ekstrakurikuler.update', [$lembagaB, $ekskulLembagaA]),
        ['jenis_ekskul' => 'wajib', 'nama_ekskul' => 'Nama Diubah Oleh Lembaga B']
    );

    $response->assertNotFound();
    $this->assertDatabaseHas('ekstrakurikuler_lembaga', [
        'id' => $ekskulLembagaA->id,
        'lembaga_id' => $this->lembaga->id,
        'nama_ekskul' => 'Pramuka Lembaga A Asli',
    ]);
});

it('rejects deleting an ekstrakurikuler that belongs to a different lembaga (cross-tenant IDOR)', function () {
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $this->yayasan->id]);
    $managerB = User::factory()->create(['yayasan_id' => $this->yayasan->id]);
    $managerB->assignRole($this->role);

    $ekskulLembagaA = EkstrakurikulerLembaga::create([
        'lembaga_id' => $this->lembaga->id,
        'jenis_ekskul' => 'pilihan',
        'nama_ekskul' => 'Futsal Lembaga A Asli',
    ]);

    $response = $this->actingAs($managerB)->delete(
        route('admin.lembaga.ekstrakurikuler.destroy', [$lembagaB, $ekskulLembagaA])
    );

    $response->assertNotFound();
    $this->assertDatabaseHas('ekstrakurikuler_lembaga', [
        'id' => $ekskulLembagaA->id,
        'lembaga_id' => $this->lembaga->id,
    ]);
});
```

- [ ] **Step 3: Jalankan test, pastikan lulus (bukti kode existing sudah benar)**

Run: `php artisan test tests/Feature/Admin/LembagaRelationalManagementTest.php --compact`
Expected: PASS, semua test di file (existing + 2 baru) lulus TANPA perlu mengubah kode produksi — kalau salah satu dari 2 test baru GAGAL, itu berarti audit sebelumnya salah menilai kode `EkstrakurikulerController` sudah benar; STOP dan laporkan ke user, JANGAN memperbaiki kode produksi tanpa konfirmasi (di luar scope plan ini kalau ternyata memang ada bug).

- [ ] **Step 4: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
git add tests/Feature/Admin/LembagaRelationalManagementTest.php
git commit -m "test(akademik): tambah regresi cross-tenant IDOR ekstrakurikuler_lembaga"
```

- [ ] **Step 5: CHECKPOINT — Jalankan full test suite gabungan Kelompok B+C (WAJIB, tanpa filter)**

Ini SATU-SATUNYA titik di seluruh audit sistematis tahap 2 di mana full suite dijalankan — bukan per-kelompok.

Run: `php artisan test --compact`
Expected: 0 failed. Catat angka pasti (passed/skipped/assertions/durasi) di laporan akhir — HARUS bisa ditelusuri (command + output nyata), bukan diasumsikan.

**Kalau ada test GAGAL yang TIDAK terkait file yang disentuh Kelompok B/C** (mis. flaky test pre-existing yang tidak berhubungan): STOP, laporkan detail kegagalannya ke user dengan nama test + pesan error lengkap — JANGAN diam-diam mengabaikan atau "menganggap tidak terkait" tanpa investigasi, dan JANGAN mengubah test yang gagal itu tanpa izin eksplisit.

- [ ] **Step 6: Catat penyelesaian Kelompok C + penutup audit tahap 2 di PETA_PENGEMBANGAN.md**

Baca dulu bagian "Audit Sistematis Akademik Tahap 2" yang sudah ada, update baris Kelompok C dari "🟡 Menunggu pengerjaan terpisah" jadi "✅ SELESAI (tanggal hari ini)" dengan ringkasan singkat (badge/filter kurikulum RPP, validasi kelas-semester, test regresi IDOR ekskul), dan tambahkan satu baris penutup bahwa SELURUH audit sistematis tahap 2 (Kelompok A, B, C) kini selesai, dengan angka full suite final dari Step 5.

```bash
git add PETA_PENGEMBANGAN.md
git commit -m "docs: catat penyelesaian Kelompok C, tutup audit sistematis tahap 2 akademik"
```
