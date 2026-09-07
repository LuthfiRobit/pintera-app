# Perbaikan Tautan Orang Tua-Siswa & Konsistensi Identitas Person Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Perbaiki 4 bug fungsional pada tautan Orang Tua-Siswa-Person: pencarian NIK yang gagal total di jalur pendaftaran utama, over-count anak lintas lembaga, dan snapshot filter index yang basi.

**Architecture:** Fix backend query scope (`withoutGlobalScopes()` di titik yang tepat, `withCount` ter-scope), lengkapi data yang hilang (`User.yayasan_id`), dan pindahkan 1 dimensi filter dari client-side Alpine murni ke server-side query param — tanpa mengubah struktur SPA yang sudah ada untuk dimensi filter lain (aktif/non-aktif) yang tidak bermasalah.

**Tech Stack:** Laravel 12 / PHP 8.3, Pest, Eloquent (`TenantScope`, `Person::YayasanScope`), Alpine.js, Blade.

## Global Constraints

- Task 1 WAJIB urutan: perbaiki fixture test lama dulu (buat merah tanpa fix) → baru terapkan fix → baru hijau. JANGAN langsung tambah test baru tanpa memperbaiki fixture lama dulu.
- Pola `withoutGlobalScopes()` di Task 1 WAJIB identik dengan `OrangTuaController::store()` baris 77 (rujukan yang sudah benar) — BUKAN pola baru.
- Task 2 (over-count) TIDAK mengubah perilaku `edit()` — `edit()` SENGAJA tetap menampilkan anak lintas lembaga (`Person::YayasanScope` berbasis yayasan, bukan lembaga — ini benar by design, bukan bug).
- Task 3 bergantung urutan pada Task 2 (angka `siswa_count` yang dipakai filter WAJIB sudah ter-scope dari Task 2) — jangan dikerjakan out-of-order.
- Tidak pakai worktree, kerja langsung di branch `rbac-v2`.
- JANGAN kerjakan item "Di Luar Scope" di spec: TIDAK membangun UI untuk `MergePersonsAction`.

---

## Task 1: Bug #3 — Pencarian NIK Orang Tua Gagal Total (Prioritas Tertinggi)

**Files:**
- Modify: `app/Http/Controllers/Admin/SiswaOrangTuaController.php:28` (method `cari()`), `:66` (method `store()`)
- Modify: `app/Services/AkunOrangTuaGenerator.php:41-50` (method `buat()`)
- Modify: `tests/Feature/Admin/SiswaOrangTuaLinkingTest.php` — perbaiki 2 fixture yang false-negative-tersamar, tambah 1 test baru

**Interfaces:**
- Consumes: `User::withoutGlobalScopes()` (pola Eloquent bawaan, dipakai persis seperti `OrangTuaController::store()` baris 77 — tidak ada perubahan signature).
- Produces: `AkunOrangTuaGenerator::buat()` tetap menerima parameter yang sama (`?int $yayasanId = null` di posisi terakhir) dan mengembalikan `OrangTua` — signature TIDAK berubah, hanya `User::create()` di dalamnya diisi `yayasan_id`. Task 2/3 tidak bergantung pada task ini.

### Konteks fixture yang harus dipahami dulu

`tests/Feature/Admin/SiswaOrangTuaLinkingTest.php` punya 2 test yang SAAT INI LULUS meski bug belum diperbaiki, karena fixture-nya tidak mencerminkan kondisi produksi nyata:

1. **`'finds an existing orang tua by nik via the cari endpoint'`** (baris 24-40) — membuat `User::factory()->create(['lembaga_id' => $lembagaSama->id])` di mana `$lembagaSama` sengaja dibuat di bawah yayasan yang SAMA dengan manager (`Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id])`, baris 27). Manager di test ini SELALU yayasan-scope (dari `actingAsSiswaOrangTuaManager()` — cek `tests/Pest.php:151-162`, `scope_level: 'yayasan'`, punya `yayasan_id`, TIDAK pernah set `session('active_lembaga_id')` jadi selalu mode "Semua Lembaga"). Karena `User.lembaga_id` yang dibuat COCOK dengan salah satu lembaga milik yayasan manager, `TenantScope`'s `whereIn('lembaga_id', Lembaga::where('yayasan_id', $manager->yayasan_id))` (cabang "Semua Lembaga" untuk aktor yayasan) MENEMUKANNYA TANPA butuh `withoutGlobalScopes()` sama sekali — test ini lulus karena kebetulan struktural fixture, BUKAN karena `cari()` benar.
2. **`'returns a distinct message when linking a nik that belongs to a non-parent user'`** (baris 237-252) — pola fixture IDENTIK (`User::factory()->create(['username' => ..., 'lembaga_id' => $lembagaSama->id])`, baris 241), bug tersamar yang sama, menguji cabang `store()` yang juga kena Bug #3a.

Kondisi produksi NYATA (dari `AkunOrangTuaGenerator::buat()` SEBELUM fix Task ini): `User.lembaga_id = null` DAN `User.yayasan_id = null` — TIDAK PERNAH match `lembaga_id` manapun. Fixture di atas tidak pernah mensimulasikan kondisi ini.

### Step 1: Perbaiki fixture test #1 (buat merah dulu)

Edit `tests/Feature/Admin/SiswaOrangTuaLinkingTest.php` baris 24-40, ganti `User::factory()->create(['lembaga_id' => $lembagaSama->id])` (baris 32) menjadi `User::factory()->create(['lembaga_id' => null, 'yayasan_id' => null])` — mensimulasikan persis output `AkunOrangTuaGenerator::buat()` SEBELUM fix (kondisi paling umum di data produksi saat ini). Hapus juga variabel `$lembagaSama` (baris 27) dan komentar baris 28-31 yang sekarang tidak relevan (ganti dengan komentar baru yang menjelaskan kondisi produksi nyata):

```php
it('finds an existing orang tua by nik via the cari endpoint', function () {
    $manager = actingAsSiswaOrangTuaManager();
    $siswa = buatSiswaUntukTautan($manager->yayasan_id);
    // Mirrors AkunOrangTuaGenerator::buat()'s real output before this task's fix: the
    // underlying User account carries neither lembaga_id nor yayasan_id, so TenantScope's
    // default filtering (and even its "Semua Lembaga" whereIn/pool fallback) can never match
    // it — cari() MUST bypass scoping entirely to find it, the same way
    // OrangTuaController::store() already does.
    $orangTua = OrangTua::factory()->create(['nik' => '3201234567895555', 'user_id' => User::factory()->create(['lembaga_id' => null, 'yayasan_id' => null])->id]);

    $response = $this->actingAs($manager)->getJson(route('admin.siswa.orang-tua.cari', $siswa).'?nik=3201234567895555');

    $response->assertOk()->assertJson([
        'found' => true,
        'orang_tua' => ['id' => $orangTua->id, 'nama_lengkap' => $orangTua->nama_lengkap],
    ]);
});
```

### Step 2: Perbaiki fixture test #2 (buat merah dulu)

Edit baris 237-252 dengan cara yang sama — ganti `User::factory()->create(['username' => '3201234567899999', 'lembaga_id' => $lembagaSama->id])` (baris 241) menjadi `User::factory()->create(['username' => '3201234567899999', 'lembaga_id' => null, 'yayasan_id' => null])`, hapus `$lembagaSama` (baris 240) yang sekarang tidak dipakai lagi di test ini (cek dulu apakah masih dipakai baris lain di test yang sama — tidak, variabel ini lokal ke closure `it()` ini).

### Step 3: Jalankan test, verifikasi KEDUANYA gagal

Run: `php artisan test --filter="finds an existing orang tua by nik via the cari endpoint" tests/Feature/Admin/SiswaOrangTuaLinkingTest.php`
Expected: FAIL — `found: false` diterima padahal diharapkan `found: true` (karena `User::where('username', ...)` tanpa `withoutGlobalScopes()` tidak menemukan baris dengan `lembaga_id`/`yayasan_id` null).

Run: `php artisan test --filter="returns a distinct message when linking a nik that belongs to a non-parent user" tests/Feature/Admin/SiswaOrangTuaLinkingTest.php`
Expected: FAIL — pesan error yang diharapkan (`'NIK ini sudah terdaftar ke akun lain yang bukan profil Orang Tua.'`) tidak muncul karena `store()` juga tidak menemukan `$existingUser`, request malah lolos ke percobaan pembuatan baru lalu gagal di backstop `Rule::unique` dengan pesan generik berbeda.

### Step 4: Tambah test baru — skenario yayasan mode "Semua Lembaga" via lembaga BEDA

Tambahkan test baru setelah test `'requires siswa.edit in addition to orang-tua permissions...'` (setelah baris 235), SEBELUM test `'returns a distinct message...'`:

```php
it('finds an existing orang tua by nik even when the acting yayasan manager has not selected an active lembaga', function () {
    $manager = actingAsSiswaOrangTuaManager();
    $siswa = buatSiswaUntukTautan($manager->yayasan_id);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);
    $siswaLain = Siswa::factory()->create(['lembaga_id' => $lembagaLain->id]);
    // Registered earlier through the real Siswa-first flow: AkunOrangTuaGenerator::buat()
    // populates yayasan_id (this task's fix 3b) but never lembaga_id.
    $orangTuaUser = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $manager->yayasan_id]);
    $orangTua = OrangTua::factory()->create(['nik' => '3201234567898888', 'user_id' => $orangTuaUser->id]);
    $orangTua->siswa()->attach($siswaLain->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $response = $this->actingAs($manager)->getJson(route('admin.siswa.orang-tua.cari', $siswa).'?nik=3201234567898888');

    $response->assertOk()->assertJson(['found' => true, 'orang_tua' => ['id' => $orangTua->id]]);
});
```

Run: `php artisan test --filter="finds an existing orang tua by nik even when the acting yayasan manager" tests/Feature/Admin/SiswaOrangTuaLinkingTest.php`
Expected: FAIL (sama, `found: false`) — belum ada fix.

### Step 5: Terapkan fix 3a — `SiswaOrangTuaController`

Edit `app/Http/Controllers/Admin/SiswaOrangTuaController.php` baris 28:
```php
// Sebelum:
$user = User::where('username', $data['nik'])->first();
// Sesudah:
$user = User::withoutGlobalScopes()->where('username', $data['nik'])->first();
```

Edit baris 66:
```php
// Sebelum:
$existingUser = User::where('username', $data['nik'])->first();
// Sesudah:
$existingUser = User::withoutGlobalScopes()->where('username', $data['nik'])->first();
```

### Step 6: Terapkan fix 3b — `AkunOrangTuaGenerator`

Edit `app/Services/AkunOrangTuaGenerator.php` baris 41-50, tambahkan `'yayasan_id' => $yayasanId,` ke array `User::create()`:

```php
$user = User::create([
    'name' => $namaLengkap,
    'email' => null,
    'username' => $nik,
    'password' => Hash::make($nik),
    'lembaga_id' => null,
    'yayasan_id' => $yayasanId,
    'email_verified_at' => null,
    'is_active' => true,
    'must_change_password' => true,
]);
```

### Step 7: Jalankan seluruh file test, verifikasi semua hijau

Run: `php artisan test tests/Feature/Admin/SiswaOrangTuaLinkingTest.php --compact`
Expected: PASS — semua test (termasuk yang sudah ada sebelumnya, tidak hanya yang baru diperbaiki) lulus, 0 regresi.

### Step 8: Cek regresi `AkunOrangTuaGenerator`

Cari test lain yang memakai `AkunOrangTuaGenerator` langsung (kemungkinan tidak ada test unit terpisah untuknya — cek dulu):
Run: `Select-String -Path "tests\**\*.php" -Pattern "AkunOrangTuaGenerator" | Select-Object -Unique Path` (PowerShell, cari semua file test yang menyinggung class ini)

Kalau ada file test langsung untuk `AkunOrangTuaGenerator`, jalankan dan pastikan tetap hijau. Kalau tidak ada (kemungkinan besar hanya dipakai tidak langsung lewat `OrangTuaController`/`SiswaOrangTuaController`), jalankan juga:
Run: `php artisan test tests/Feature/Admin/OrangTuaControllerTest.php --compact` (kalau file ini ada — cek dulu dengan `Test-Path`; kalau tidak ada, lewati, akan dibuat di Task 2)
Expected: PASS, 0 regresi.

### Step 9: Commit

```bash
git add app/Http/Controllers/Admin/SiswaOrangTuaController.php app/Services/AkunOrangTuaGenerator.php tests/Feature/Admin/SiswaOrangTuaLinkingTest.php
git commit -m "fix(orang-tua): perbaiki pencarian NIK yang gagal total di jalur tautan siswa

SiswaOrangTuaController::cari()/store() tidak withoutGlobalScopes() saat
mencari User by username, padahal AkunOrangTuaGenerator tidak pernah
mengisi yayasan_id/lembaga_id pada User yang dibuat -- kombinasi ini
membuat pencarian NIK orang tua gagal total untuk semua level scope.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 2: Bug #2 — Over-Count `siswa_count` Lintas Lembaga di Index

**Files:**
- Modify: `app/Http/Controllers/Admin/OrangTuaController.php:39` (method `index()`)
- Test: `tests/Feature/Admin/OrangTuaControllerTest.php` (cek dulu apakah file ini SUDAH ADA — kalau ada, tambah ke situ mengikuti pola existing; kalau belum ada, buat baru dengan `php artisan make:test --pest OrangTuaControllerTest`)

**Interfaces:**
- Consumes: `$lembagaIdsYayasan` (baris 30) dan `$activeLembagaId` (baris 31) — SUDAH ADA di method `index()` dari fix sebelumnya (spec `2026-09-07-scope-yayasan-lembaga-menu-fix.md` Kategori A.3), JANGAN dihitung ulang.
- Produces: tidak ada perubahan signature/interface publik — murni internal query fix.

### Step 1: Cek dulu apakah test file untuk `OrangTuaController::index()` sudah ada

Run: `Test-Path "tests\Feature\Admin\OrangTuaControllerTest.php"` (PowerShell)

Kalau `True`, baca isi file itu untuk memahami pola `actingAs`/helper yang dipakai (kemungkinan pakai helper serupa `actingAsOrangTuaManager()` dari `tests/Pest.php:121-134`) sebelum menulis test baru — IKUTI pola yang sudah ada di file itu, jangan bikin gaya baru. Kalau `False`, buat file baru dengan `php artisan make:test --pest OrangTuaControllerTest` di direktori `tests/Feature/Admin/`, isi dengan helper `actingAsOrangTuaManager()`/`actingAsSiswaOrangTuaManager()` dari `tests/Pest.php` (sudah ter-load otomatis, tidak perlu import khusus di Pest).

### Step 2: Tulis test baru (RED)

Tambahkan (di file yang sudah ada atau file baru dari Step 1):

```php
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\Yayasan;

it('scopes siswa_count on the index to the acting lembaga, not the orang tua total across the whole yayasan', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswaA = Siswa::factory()->create(['lembaga_id' => $lembagaA->id]);
    $siswaB = Siswa::factory()->create(['lembaga_id' => $lembagaB->id]);
    $orangTua = OrangTua::factory()->create(['yayasan_id' => $yayasan->id]);
    $orangTua->siswa()->attach($siswaA->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $orangTua->siswa()->attach($siswaB->id, ['hubungan' => 'ayah', 'is_kontak_utama' => false]);

    $managerLembagaA = actingAsOrangTuaManager();
    $managerLembagaA->update(['lembaga_id' => $lembagaA->id]);
    $responseLembaga = $this->actingAs($managerLembagaA)->get(route('admin.orang-tua.index'));
    $responseLembaga->assertOk();
    $itemLembaga = collect($responseLembaga->viewData('orangTuaList'))->firstWhere('id', $orangTua->id);
    expect($itemLembaga->siswa_count)->toBe(1);

    $managerYayasan = actingAsSiswaOrangTuaManager();
    $managerYayasan->update(['yayasan_id' => $yayasan->id]);
    $responseYayasan = $this->actingAs($managerYayasan)->get(route('admin.orang-tua.index'));
    $responseYayasan->assertOk();
    $itemYayasan = collect($responseYayasan->viewData('orangTuaList'))->firstWhere('id', $orangTua->id);
    expect($itemYayasan->siswa_count)->toBe(2);
});
```

(Catatan: `OrangTua::factory()->create(['yayasan_id' => $yayasan->id])` — cek `database/factories/OrangTuaFactory.php` baris 39: `$attributes['yayasan_id']` dipakai langsung kalau diberikan, jadi ini valid untuk memastikan `Person` yang mendasari orang tua ini berada di yayasan yang benar terlepas dari urutan pembuatan `Lembaga`/`Yayasan::first()` fallback.)

### Step 3: Jalankan test, verifikasi gagal

Run: `php artisan test --filter="scopes siswa_count on the index" --compact`
Expected: FAIL — `$itemLembaga->siswa_count` bernilai `2` (bukan `1`), karena `withCount('siswa')` polos saat ini menghitung SEMUA anak lintas lembaga tanpa scope.

### Step 4: Terapkan fix

Edit `app/Http/Controllers/Admin/OrangTuaController.php` baris 38-54, ganti baris 39 (`->withCount('siswa')`) menjadi closure ter-scope, method lengkap setelah fix:

```php
$orangTuaList = OrangTua::with(['user' => fn ($q) => $q->withoutGlobalScope(TenantScope::class), 'person'])
    ->withCount(['siswa' => function ($q) use ($user, $lembagaIdsYayasan, $activeLembagaId) {
        $q->withoutGlobalScope(TenantScope::class);
        if ($user->widestScopeLevel() !== 'yayasan') {
            $q->where('siswa.lembaga_id', $user->lembaga_id);
        } elseif ($activeLembagaId) {
            $q->where('siswa.lembaga_id', $activeLembagaId);
        } else {
            $q->whereIn('siswa.lembaga_id', $lembagaIdsYayasan);
        }
    }])
    ->when($user->widestScopeLevel() !== 'yayasan', fn ($q) => $q->where(fn ($q2) => $q2
        ->whereDoesntHave('siswa', fn ($q3) => $q3->withoutGlobalScope(TenantScope::class))
        ->orWhereHas('siswa', fn ($q3) => $q3->withoutGlobalScope(TenantScope::class)->where('siswa.lembaga_id', $user->lembaga_id))))
    ->when($user->widestScopeLevel() === 'yayasan', fn ($q) => $q->where(function ($q2) use ($lembagaIdsYayasan, $activeLembagaId) {
        $q2->whereDoesntHave('siswa', fn ($q3) => $q3->withoutGlobalScope(TenantScope::class))
            ->orWhereHas('siswa', function ($q3) use ($lembagaIdsYayasan, $activeLembagaId) {
                $q3->withoutGlobalScope(TenantScope::class);
                $activeLembagaId
                    ? $q3->where('siswa.lembaga_id', $activeLembagaId)
                    : $q3->whereIn('siswa.lembaga_id', $lembagaIdsYayasan);
            });
    }))
    ->when($search, fn ($q) => $q->search($search))
    ->orderByNama()
    ->get();
```

Baris 40-54 lama (dua `->when()` visibilitas, `->when($search, ...)`, `->orderByNama()->get()`) TIDAK berubah isinya — hanya diformat ulang karena baris 39 (`withCount`) diganti closure yang lebih panjang.

### Step 5: Jalankan test, verifikasi lulus

Run: `php artisan test --filter="scopes siswa_count on the index" --compact`
Expected: PASS.

### Step 6: Regresi — pastikan `edit()` TIDAK berubah

`OrangTuaController::edit()` tidak disentuh sama sekali oleh task ini (`->load('siswa')` di situ TETAP tanpa scope tambahan, sesuai desain: profil orang tua menampilkan SEMUA anak lintas lembaga). Cek dulu apakah ada test existing untuk `edit()` yang mengasersi jumlah anak lintas lembaga tetap tampil lengkap — kalau ada, jalankan untuk konfirmasi tidak ada regresi:

Run: `php artisan test tests/Feature/Admin/OrangTuaControllerTest.php --compact` (atau nama file yang dipakai dari Step 1)
Expected: PASS, semua test (termasuk yang baru) hijau, 0 regresi.

### Step 7: Commit

```bash
git add app/Http/Controllers/Admin/OrangTuaController.php tests/Feature/Admin/OrangTuaControllerTest.php
git commit -m "fix(orang-tua): siswa_count pada index ter-scope ke lembaga/yayasan aktor, bukan total lintas lembaga

OrangTua::siswa() relasi baked-in withoutGlobalScopes() di level
definisi, jadi withCount('siswa') polos selalu menghitung SEMUA anak
lintas lembaga -- admin lembaga-scope melihat angka anak yang bukan
wewenangnya. edit() sengaja TIDAK diubah (tetap lintas lembaga by design).

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 3: Bug #1 — Filter "Ada Anak"/"Belum Ada Anak" Server-Side, Bukan Snapshot Client-Side Basi

**Files:**
- Modify: `app/Http/Controllers/Admin/OrangTuaController.php:24-62` (method `index()`)
- Modify: `resources/views/admin/orang-tua/index.blade.php`
- Test: file test dari Task 2 (tambah ke situ)

**Interfaces:**
- Consumes: `$orangTuaList` hasil query Task 2 (koleksi `OrangTua` dengan `siswa_count` sudah ter-scope) — task ini TIDAK mengubah query scope-nya, hanya menambah 1 tahap filter SETELAHNYA.
- Produces: view menerima 2 variabel baru: `totalAda` (int, jumlah TOTAL yang punya anak SEBELUM filter `anak` diterapkan — dipakai untuk badge, supaya badge tetap akurat walau sedang menampilkan subset) dan `totalBelum` (int, sama untuk yang belum punya anak). `$orangTuaList` yang dikirim ke view tetap nama variabel yang sama, tapi ISINYA sudah ter-filter sesuai query param `anak` kalau ada.

### Catatan penyesuaian dari spec (bukan perubahan hasil, cuma pendekatan teknis)

Spec Bug #1 mengusulkan `having('siswa_count', ...)` di level SQL. Setelah membaca `index.blade.php` penuh, ditemukan bahwa filter "Ada Anak"/"Belum Ada Anak" adalah SATU dari 5 state `activeFilter` Alpine (`semua`/`tertaut`/`belum_tertaut`/`aktif`/`non_aktif`) yang saling eksklusif, dan filter "Aktif"/"Non-Aktif" TIDAK termasuk cakupan Bug #1 (tidak basi, murni dari `$o->user->is_active` yang selalu fresh setiap page load) — jadi TIDAK ikut diubah. Karena halaman ini SUDAH mengirim seluruh dataset ke frontend sekali per page-load (tidak ada pagination server-side sama sekali, `perPage`/`currentPage` murni Alpine di atas array lengkap), pendekatan yang lebih sederhana dan tetap benar: filter `anak` diterapkan di PHP SETELAH `->get()` (bukan `having()` di SQL), dan badge dihitung dari list SEBELUM filter `anak` diterapkan supaya angka "Ada Anak: N" / "Belum Ada Anak: M" di tombol filter tetap akurat walau salah satu filter sedang aktif. Hasil akhir (baris mana yang tampil) identik dengan spec, hanya jalur teknisnya lebih pas dengan struktur halaman yang sudah ada.

### Step 1: Tulis test baru (RED)

Tambahkan ke file test dari Task 2:

```php
it('filters the orang tua index by anak query param without relying on a stale client snapshot', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTuaDenganAnak = OrangTua::factory()->create(['yayasan_id' => $yayasan->id]);
    $orangTuaDenganAnak->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $orangTuaTanpaAnak = OrangTua::factory()->create(['yayasan_id' => $yayasan->id]);

    $manager = actingAsOrangTuaManager();
    $manager->update(['lembaga_id' => $lembaga->id]);

    $responseAda = $this->actingAs($manager)->get(route('admin.orang-tua.index', ['anak' => 'ada']));
    $responseAda->assertOk();
    $idsAda = collect($responseAda->viewData('orangTuaList'))->pluck('id');
    expect($idsAda)->toContain($orangTuaDenganAnak->id)->not->toContain($orangTuaTanpaAnak->id);

    $responseBelum = $this->actingAs($manager)->get(route('admin.orang-tua.index', ['anak' => 'belum']));
    $responseBelum->assertOk();
    $idsBelum = collect($responseBelum->viewData('orangTuaList'))->pluck('id');
    expect($idsBelum)->toContain($orangTuaTanpaAnak->id)->not->toContain($orangTuaDenganAnak->id);
});
```

### Step 2: Jalankan test, verifikasi gagal

Run: `php artisan test --filter="filters the orang tua index by anak query param" --compact`
Expected: FAIL — route `admin.orang-tua.index` saat ini tidak membaca query param `anak` sama sekali, kedua request mengembalikan SEMUA orang tua tanpa filter.

### Step 3: Terapkan fix — Controller

Edit `app/Http/Controllers/Admin/OrangTuaController.php`, method `index()` (baris 24-62). Tambahkan pembacaan query param setelah baris `$search = $request->query('search');` (baris 29), dan filter PHP setelah `->get()` (baris 54), sebelum `return view(...)`:

```php
public function index(Request $request): View
{
    $this->authorize('orang-tua.view');

    $user = auth()->user();
    $search = $request->query('search');
    $anakFilter = $request->query('anak');
    $lembagaIdsYayasan = Lembaga::where('yayasan_id', $user->yayasan_id)->pluck('id');
    $activeLembagaId = session('active_lembaga_id');

    // OrangTua accounts always have lembaga_id = null by design, so eager-loading `user`
    // must bypass TenantScope or a lembaga-scoped viewer's own scope silently filters it
    // to null (Eloquent turns `where('lembaga_id', null)` into `whereNull`, which happens
    // to only pass when the viewer ALSO has a null lembaga_id — masking this for any
    // fixture/manager that never set one).
    $orangTuaList = OrangTua::with(['user' => fn ($q) => $q->withoutGlobalScope(TenantScope::class), 'person'])
        ->withCount(['siswa' => function ($q) use ($user, $lembagaIdsYayasan, $activeLembagaId) {
            $q->withoutGlobalScope(TenantScope::class);
            if ($user->widestScopeLevel() !== 'yayasan') {
                $q->where('siswa.lembaga_id', $user->lembaga_id);
            } elseif ($activeLembagaId) {
                $q->where('siswa.lembaga_id', $activeLembagaId);
            } else {
                $q->whereIn('siswa.lembaga_id', $lembagaIdsYayasan);
            }
        }])
        ->when($user->widestScopeLevel() !== 'yayasan', fn ($q) => $q->where(fn ($q2) => $q2
            ->whereDoesntHave('siswa', fn ($q3) => $q3->withoutGlobalScope(TenantScope::class))
            ->orWhereHas('siswa', fn ($q3) => $q3->withoutGlobalScope(TenantScope::class)->where('siswa.lembaga_id', $user->lembaga_id))))
        ->when($user->widestScopeLevel() === 'yayasan', fn ($q) => $q->where(function ($q2) use ($lembagaIdsYayasan, $activeLembagaId) {
            $q2->whereDoesntHave('siswa', fn ($q3) => $q3->withoutGlobalScope(TenantScope::class))
                ->orWhereHas('siswa', function ($q3) use ($lembagaIdsYayasan, $activeLembagaId) {
                    $q3->withoutGlobalScope(TenantScope::class);
                    $activeLembagaId
                        ? $q3->where('siswa.lembaga_id', $activeLembagaId)
                        : $q3->whereIn('siswa.lembaga_id', $lembagaIdsYayasan);
                });
        }))
        ->when($search, fn ($q) => $q->search($search))
        ->orderByNama()
        ->get();

    $totalAda = $orangTuaList->filter(fn ($o) => $o->siswa_count > 0)->count();
    $totalBelum = $orangTuaList->filter(fn ($o) => $o->siswa_count === 0)->count();

    if ($anakFilter === 'ada') {
        $orangTuaList = $orangTuaList->filter(fn ($o) => $o->siswa_count > 0)->values();
    } elseif ($anakFilter === 'belum') {
        $orangTuaList = $orangTuaList->filter(fn ($o) => $o->siswa_count === 0)->values();
    }

    return view('admin.orang-tua.index', [
        'orangTuaList' => $orangTuaList,
        'search' => $search,
        'anakFilter' => $anakFilter,
        'totalOrangTua' => $orangTuaList->count(),
        'totalAktif' => $orangTuaList->filter(fn ($o) => $o->user && $o->user->is_active)->count(),
        'totalAda' => $totalAda,
        'totalBelum' => $totalBelum,
    ]);
}
```

### Step 4: Jalankan test, verifikasi lulus

Run: `php artisan test --filter="filters the orang tua index by anak query param" --compact`
Expected: PASS.

### Step 5: Terapkan fix — View

Edit `resources/views/admin/orang-tua/index.blade.php`. Ganti 2 tombol filter "Ada Anak" (baris 101-104) dan "Belum Ada Anak" (baris 105-108) dari `<button @click=...>` Alpine murni menjadi `<a>` yang navigasi server-side, TAPI TETAP mempertahankan styling `:class` reaktif Alpine (supaya highlight tombol tetap bekerja berdasarkan `activeFilter` yang di-set dari nilai server saat load, bukan re-computed client-side). Tombol "Semua" (baris 97-100) juga diubah jadi link untuk membersihkan filter `anak` (tapi TETAP mempertahankan kemampuan set `activeFilter='semua'` untuk kombinasi dengan filter aktif/non-aktif client-side).

Sebelum (baris 97-108):
```blade
<button @click="activeFilter = 'semua'; currentPage = 1" type="button" :class="activeFilter === 'semua' ? 'bg-brand-50 font-semibold text-brand-600 border-brand-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-gray-900 border-gray-200'" class="flex-1 sm:flex-none justify-center px-3.5 py-2 rounded-lg text-xs font-semibold border transition-all whitespace-nowrap flex items-center gap-1.5">
    <span>Semua</span>
    <span :class="activeFilter === 'semua' ? 'bg-brand-100/80 text-brand-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="items.length"></span>
</button>
<button @click="activeFilter = 'tertaut'; currentPage = 1" type="button" :class="activeFilter === 'tertaut' ? 'bg-blue-50 font-semibold text-blue-700 border-blue-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-gray-900 border-gray-200'" class="flex-1 sm:flex-none justify-center px-3.5 py-2 rounded-lg text-xs font-semibold border transition-all whitespace-nowrap flex items-center gap-1.5">
    <span>Ada Anak</span>
    <span :class="activeFilter === 'tertaut' ? 'bg-blue-100/80 text-blue-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countTertaut"></span>
</button>
<button @click="activeFilter = 'belum_tertaut'; currentPage = 1" type="button" :class="activeFilter === 'belum_tertaut' ? 'bg-slate-100 font-semibold text-slate-700 border-slate-300 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-gray-900 border-gray-200'" class="flex-1 sm:flex-none justify-center px-3.5 py-2 rounded-lg text-xs font-semibold border transition-all whitespace-nowrap flex items-center gap-1.5">
    <span>Belum Ada Anak</span>
    <span :class="activeFilter === 'belum_tertaut' ? 'bg-slate-200 text-slate-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countBelumTertaut"></span>
</button>
```

Sesudah:
```blade
<a href="{{ route('admin.orang-tua.index', array_merge(request()->except(['anak', 'page']), [])) }}" @click="activeFilter = 'semua'; currentPage = 1" :class="activeFilter === 'semua' ? 'bg-brand-50 font-semibold text-brand-600 border-brand-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-gray-900 border-gray-200'" class="flex-1 sm:flex-none justify-center px-3.5 py-2 rounded-lg text-xs font-semibold border transition-all whitespace-nowrap flex items-center gap-1.5">
    <span>Semua</span>
    <span :class="activeFilter === 'semua' ? 'bg-brand-100/80 text-brand-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold">{{ $totalAda + $totalBelum }}</span>
</a>
<a href="{{ route('admin.orang-tua.index', array_merge(request()->except(['anak', 'page']), ['anak' => 'ada'])) }}" :class="activeFilter === 'tertaut' ? 'bg-blue-50 font-semibold text-blue-700 border-blue-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-gray-900 border-gray-200'" class="flex-1 sm:flex-none justify-center px-3.5 py-2 rounded-lg text-xs font-semibold border transition-all whitespace-nowrap flex items-center gap-1.5">
    <span>Ada Anak</span>
    <span :class="activeFilter === 'tertaut' ? 'bg-blue-100/80 text-blue-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold">{{ $totalAda }}</span>
</a>
<a href="{{ route('admin.orang-tua.index', array_merge(request()->except(['anak', 'page']), ['anak' => 'belum'])) }}" :class="activeFilter === 'belum_tertaut' ? 'bg-slate-100 font-semibold text-slate-700 border-slate-300 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-gray-900 border-gray-200'" class="flex-1 sm:flex-none justify-center px-3.5 py-2 rounded-lg text-xs font-semibold border transition-all whitespace-nowrap flex items-center gap-1.5">
    <span>Belum Ada Anak</span>
    <span :class="activeFilter === 'belum_tertaut' ? 'bg-slate-200 text-slate-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold">{{ $totalBelum }}</span>
</a>
```

(Tombol "Aktif"/"Non-Aktif", baris 109-116, TIDAK diubah — tetap `<button>` Alpine murni, tetap pakai `items.length`-based `countAktif`/`countNonAktif` karena dimensi ini bukan cakupan Bug #1.)

Di dalam blok `<script>`, ubah nilai awal `activeFilter` (baris 247) supaya highlight cocok dengan query param `anak` yang sedang aktif saat page load:
```js
// Sebelum:
activeFilter: 'semua',
// Sesudah:
activeFilter: '{{ $anakFilter === 'ada' ? 'tertaut' : ($anakFilter === 'belum' ? 'belum_tertaut' : 'semua') }}',
```

`filteredItems` getter (baris 268-284) TIDAK PERLU diubah — filter `tertaut`/`belum_tertaut` di situ sekarang jadi no-op idempotent (item yang dikirim dari server SUDAH ter-filter, memfilter ulang dengan kondisi yang sama tidak mengubah apa pun), aman dibiarkan.

### Step 6: Verifikasi manual di browser

`php artisan serve` (atau pakai dev server yang sudah jalan), login sebagai admin dengan permission `orang-tua.view`, buka halaman Data Induk → Orang Tua:
1. Klik "Ada Anak" → URL berubah jadi `?anak=ada`, hanya baris dengan anak yang tampil, badge angka di tombol "Ada Anak" tetap sama sebelum/sesudah diklik (menunjukkan itu total, bukan hasil filter saat ini).
2. Klik "Belum Ada Anak" → sebaliknya.
3. Klik "Semua" → kembali menampilkan semua baris.
4. Kombinasikan dengan filter "Aktif" (klik "Ada Anak" dulu, lalu klik "Aktif") → hanya baris yang PUNYA anak DAN aktif yang tampil (irisan kedua filter, tanpa reload kedua).
5. Buka tab baru, tautkan anak baru ke salah satu orang tua yang tadinya "Belum Ada Anak" (lewat halaman Siswa), kembali ke tab index (reload biasa, tanpa filter aktif) → orang tua itu sekarang muncul di "Ada Anak".

Laporkan hasil verifikasi ini secara jujur di laporan task — jangan diasumsikan lolos hanya karena kode terlihat benar.

### Step 7: Commit

```bash
git add app/Http/Controllers/Admin/OrangTuaController.php resources/views/admin/orang-tua/index.blade.php tests/Feature/Admin/OrangTuaControllerTest.php
git commit -m "fix(orang-tua): filter Ada Anak/Belum Ada Anak server-side, bukan snapshot client basi

Filter ini sebelumnya murni Alpine di atas 1 snapshot data page-load
pertama, tanpa mekanisme refresh -- karena penautan anak selalu terjadi
dari halaman Siswa (bukan halaman ini), admin yang berpindah tab bisa
melihat data basi. Filter Aktif/Non-Aktif TIDAK berubah (tidak basi).

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 4: Penutup — Full Suite, Pint, Handoff Log, Roadmap

**Files:**
- Create: `.agents/logs/2026-09-07-orang-tua-siswa-person-tautan.md`
- Modify: `PETA_PENGEMBANGAN.md`

**Interfaces:**
- Consumes: hasil commit Task 1-3 (`git log --oneline` untuk merangkum).
- Produces: tidak ada — task penutup dokumentasi murni.

### Step 1: Cek proses PHP lain sebelum full suite

Run (PowerShell): `Get-CimInstance Win32_Process -Filter "Name='php.exe'"`

Kalau ada proses `php artisan test` lain yang sedang berjalan (bukan `artisan serve`/dev server), TUNGGU sampai selesai — project ini pernah kena MySQL deadlock kalau 2 proses test jalan bersamaan.

### Step 2: Full Test Suite

Run: `php artisan test --compact`

Expected: SEMUA test lulus KECUALI 4 kegagalan pre-existing yang SUDAH terdokumentasi di `.agents/logs/2026-09-07-scope-yayasan-lembaga-menu-fix.md` (`M3DemoDataSeederTest` x2, `PresensiSeederTest`, `SesiPembelajaranSeederTest` — seeder demo PPDB/presensi, day-of-week-dependent, TIDAK terkait sama sekali dengan file yang disentuh plan ini). Kalau ada kegagalan LAIN di luar daftar itu, STOP — itu regresi nyata dari task 1-3, investigasi dan perbaiki sebelum lanjut.

### Step 3: Pint

Run: `vendor/bin/pint --dirty --format agent`

Expected: `{"tool":"pint","result":"passed"}` atau perbaikan otomatis diterapkan tanpa error. Kalau ada perubahan, commit terpisah:
```bash
git add -u
git commit -m "style: pint --dirty setelah plan orang-tua-siswa-person-tautan

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

### Step 4: Handoff Log

Buat `.agents/logs/2026-09-07-orang-tua-siswa-person-tautan.md`, ikuti struktur `.agents/logs/2026-09-07-scope-yayasan-lembaga-menu-fix.md` sebagai referensi (3 bagian: "1. Apa yang Dikerjakan" — rangkuman Task 1-3 dengan commit hash masing-masing, disusun dari `git log --oneline` sejak base commit plan ini; "2. Keputusan Penting" — sertakan keputusan bisnis "Siswa dulu, tautkan Orang Tua kemudian" dari spec, dan penjelasan kenapa `edit()` sengaja tidak diubah di Task 2; "3. Hal yang Masih Perlu Direview" — hasil full suite lengkap dari Step 2, dan poin "Di Luar Scope" dari spec yang sengaja tidak dikerjakan: UI untuk `MergePersonsAction`).

### Step 5: Update Roadmap

Tambahkan entri baru ke `PETA_PENGEMBANGAN.md`, ikuti gaya penulisan entri lain di file yang sama (judul + tanggal, latar belakang singkat, ringkasan 4 bug yang diperbaiki, commit range, link ke spec/plan/log).

### Step 6: Commit dokumentasi

```bash
git add .agents/logs/2026-09-07-orang-tua-siswa-person-tautan.md PETA_PENGEMBANGAN.md
git commit -m "docs(orang-tua): handoff log & update roadmap -- perbaikan tautan orang tua-siswa-person selesai

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Self-Review

**1. Spec coverage**: Bug #3 (prioritas tertinggi) → Task 1 (fix 3a + 3b, termasuk perbaikan fixture false-negative). Bug #2 (over-count) → Task 2. Bug #1 (snapshot basi) → Task 3 (dengan penyesuaian teknis terdokumentasi, hasil akhir tetap sesuai spec). Keputusan bisnis (Siswa-first) → dicatat di Task 4 handoff log, tidak butuh task kode sendiri (murni konteks). "Di Luar Scope" spec (UI merge Person) → eksplisit TIDAK dikerjakan, dicatat di Global Constraints dan handoff log Task 4. Tidak ada requirement spec yang tidak punya task.

**2. Placeholder scan**: Tidak ditemukan "TBD"/"nanti"/"tambahkan validasi" tanpa kode konkret — setiap step kode berisi snippet lengkap siap tempel, setiap command test punya nama filter persis.

**3. Type consistency**: `AkunOrangTuaGenerator::buat()` signature tidak berubah di Task 1 (parameter `$yayasanId` sudah ada sebelumnya, hanya dipakai lebih lengkap). `$lembagaIdsYayasan`/`$activeLembagaId` dipakai konsisten dari Task 2 ke Task 3 (variabel yang sama, tidak dihitung ulang). `$orangTuaList` tetap nama variabel yang sama dari Task 2 ke Task 3 meski isinya berubah tahap (koleksi Eloquent → hasil `->filter()->values()`, tetap Collection, tidak mematahkan `@foreach`/`collect()` di view/test manapun).
