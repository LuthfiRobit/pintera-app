# FASE 5.1: Restrukturisasi Rute Modular Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pecah `routes/admin.php` (368 baris, campur ~15 domain dalam satu file) menjadi file-file kecil per modul, tanpa mengubah satu pun nama route, URI, urutan pendaftaran relatif, atau middleware.

**Architecture:** `routes/admin.php` menjadi loader tipis (middleware+prefix+name wrapper) yang me-`require` 13 file modul di `routes/admin/`. Dua blok yang selama ini numpang fisik di `admin.php` tapi bukan bagian grup `admin` (`kasus.*` dan `guru.*` portal) dipindah keluar jadi `routes/kasus.php` dan `routes/guru.php`, didaftarkan lewat `require` baru di `routes/web.php`.

**Tech Stack:** Laravel 11 routing (`Route::group`, `Route::resource`, file `require`), Pest untuk full-suite regression di akhir.

## Global Constraints

- **Tidak ada perubahan** nama route, URI, urutan pendaftaran relatif dalam satu modul, atau middleware. Setiap task murni memindahkan teks `Route::` yang sudah ada — tidak menulis logic baru.
- Verifikasi wajib tiap task: `php artisan route:list` (FULL, bukan filtered) sebelum vs sesudah harus identik — setiap baris (URI, method, name, action, middleware) sama persis, cuma boleh beda urutan tampil.
- Full test suite (`php artisan test`) HANYA dijalankan sekali, di Task 15 (task terakhir), dan hanya setelah user diberi kesempatan approve seperti pola RBAC v2 sebelumnya. Task 1-14 tidak menjalankan full suite.
- Baseline full suite saat ini (sebelum plan ini): **1861 passed, 0 failed**.
- Cari blok kode yang disebut di tiap task dengan **pencarian teks (exact string match)**, bukan nomor baris — nomor baris di `routes/admin.php` bergeser setiap task selesai karena baris sebelumnya sudah dipindah/dihapus oleh task lain.
- Setiap file route baru TIDAK dibungkus middleware/prefix/name tambahan untuk level `admin` — itu sudah diwariskan dari `Route::group` di `routes/admin.php`. Sub-grouping yang SUDAH ADA di source asli (mis. `Route::prefix('sarpras')->name('sarpras.')->group(...)`) dipindah apa adanya, tidak dihapus.

---

## Konteks Umum untuk Semua Task

Sumber tunggal semua pemindahan adalah `routes/admin.php` versi commit `a7ce013` (branch `rbac-v2`). File itu berisi 3 grup fisik:

1. `Route::middleware(['auth','verified'])->prefix('admin')->name('admin.')->group(function () { ... })` — grup utama, isi 246 baris tempat 13 modul di bawah ini diambil.
2. `Route::bind('kasus', ...)` + `Route::middleware(['auth','verified'])->prefix('kasus')->name('kasus.')->group(...)` — di LUAR grup admin, dipindah utuh ke `routes/kasus.php` (Task 13).
3. `Route::middleware(['auth','verified'])->prefix('guru')->name('guru.')->group(...)` — di LUAR grup admin, dipindah utuh ke `routes/guru.php` (Task 14).

Pola tiap task modul (Task 1-12): buat file baru di `routes/admin/<nama>.php` berisi potongan `Route::` yang dipindah (plus `use` import untuk controller yang dipakai lewat nama pendek di blok itu), lalu di `routes/admin.php` HAPUS blok itu dan sisipkan SATU baris `require base_path('routes/admin/<nama>.php');` di lokasi blok PERTAMA yang diambil (kalau modul tersebar di beberapa blok tidak berdekatan, blok kedua dst cukup dihapus tanpa sisipan apa pun — require sudah mewakili seluruh isi modul).

---

### Task 1: Modul Roles & WhatsApp Template

**Files:**
- Create: `routes/admin/roles.php`
- Create: `routes/admin/whatsapp-template.php`
- Modify: `routes/admin.php`

**Interfaces:**
- Consumes: tidak ada (task independen pertama)
- Produces: pola `require base_path('routes/admin/<nama>.php');` yang dipakai identik oleh semua task modul lain

- [ ] **Step 1: Buat `routes/admin/roles.php`**

```php
<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::get('roles/permissions-catalog', [RoleController::class, 'permissionsCatalog'])->name('roles.permissions-catalog');
Route::resource('roles', RoleController::class)->except(['show']);
Route::resource('users', UserController::class)->except(['show', 'destroy']);
Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
```

- [ ] **Step 2: Buat `routes/admin/whatsapp-template.php`**

```php
<?php

use App\Http\Controllers\Admin\WhatsAppTemplateController;
use Illuminate\Support\Facades\Route;

Route::get('whatsapp-template', [WhatsAppTemplateController::class, 'index'])->name('whatsapp-template.index');
Route::put('whatsapp-template/{whatsappTemplate}', [WhatsAppTemplateController::class, 'update'])->name('whatsapp-template.update');
```

- [ ] **Step 3: Simpan snapshot `route:list` sebelum edit**

Run: `php artisan route:list > /tmp/before-task1.txt`

- [ ] **Step 4: Di `routes/admin.php`, cari blok persis ini dan HAPUS (ganti dengan baris `require`)**

Cari:
```php
    Route::get('roles/permissions-catalog', [RoleController::class, 'permissionsCatalog'])->name('roles.permissions-catalog');
    Route::resource('roles', RoleController::class)->except(['show']);
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
```

Ganti dengan:
```php
    require base_path('routes/admin/roles.php');
```

- [ ] **Step 5: Di `routes/admin.php`, cari blok persis ini dan HAPUS (ganti dengan baris `require`)**

Cari:
```php
    Route::get('whatsapp-template', [WhatsAppTemplateController::class, 'index'])->name('whatsapp-template.index');
    Route::put('whatsapp-template/{whatsappTemplate}', [WhatsAppTemplateController::class, 'update'])->name('whatsapp-template.update');
```

Ganti dengan:
```php
    require base_path('routes/admin/whatsapp-template.php');
```

- [ ] **Step 6: Hapus `use` statement yang sudah tidak dipakai lagi di `routes/admin.php`**

Hapus baris `use App\Http\Controllers\Admin\RoleController;`, `use App\Http\Controllers\Admin\UserController;`, dan `use App\Http\Controllers\Admin\WhatsAppTemplateController;` dari bagian atas `routes/admin.php` — TAPI HANYA jika class itu tidak dipakai lagi di sisa file (cek dengan grep, karena beberapa controller dipakai di lebih dari satu tempat, lihat catatan `TagihanController` di Task 6/7).

Run: `grep -c "RoleController\|UserController::class\|WhatsAppTemplateController" routes/admin.php` — pastikan hasilnya 0 sebelum menghapus use statement-nya (di luar baris `use` itu sendiri).

- [ ] **Step 7: Verifikasi route:list identik**

Run: `php artisan route:list > /tmp/after-task1.txt && diff /tmp/before-task1.txt /tmp/after-task1.txt`
Expected: tidak ada output (file identik, boleh beda urutan baris — kalau ada perbedaan urutan, gunakan `diff <(sort /tmp/before-task1.txt) <(sort /tmp/after-task1.txt)` untuk memastikan isinya sama, cuma urutan tampil beda).

- [ ] **Step 8: Commit**

```bash
git add routes/admin.php routes/admin/roles.php routes/admin/whatsapp-template.php
git commit -m "refactor(routes): ekstrak modul roles & whatsapp-template ke routes/admin/"
```

---

### Task 2: Modul Lembaga

**Files:**
- Create: `routes/admin/lembaga.php`
- Modify: `routes/admin.php`

**Interfaces:**
- Consumes: pola dari Task 1 (buat file → hapus blok di admin.php → sisip require → verifikasi route:list → commit)
- Produces: tidak ada yang dikonsumsi task lain (modul independen)

- [ ] **Step 1: Buat `routes/admin/lembaga.php`**

```php
<?php

use App\Http\Controllers\Admin\Lembaga\DataPeriodikController as LembagaDataPeriodikController;
use App\Http\Controllers\Admin\Lembaga\EkstrakurikulerController as LembagaEkstrakurikulerController;
use App\Http\Controllers\Admin\Lembaga\LayananKhususController as LembagaLayananKhususController;
use App\Http\Controllers\Admin\Lembaga\ProgramInklusiController as LembagaProgramInklusiController;
use App\Http\Controllers\Admin\LembagaController;
use Illuminate\Support\Facades\Route;

Route::resource('lembaga', LembagaController::class)->except(['show', 'destroy']);
Route::get('pengaturan-yayasan', [\App\Http\Controllers\Admin\YayasanSettingController::class, 'edit'])->name('yayasan.edit');
Route::put('pengaturan-yayasan', [\App\Http\Controllers\Admin\YayasanSettingController::class, 'update'])->name('yayasan.update');
Route::prefix('lembaga/{lembaga}')->name('lembaga.')->group(function () {
    Route::post('data-periodik', [LembagaDataPeriodikController::class, 'store'])->name('data-periodik.store');
    Route::put('data-periodik/{dataPeriodik}', [LembagaDataPeriodikController::class, 'update'])->name('data-periodik.update');
    Route::delete('data-periodik/{dataPeriodik}', [LembagaDataPeriodikController::class, 'destroy'])->name('data-periodik.destroy');

    Route::post('ekstrakurikuler', [LembagaEkstrakurikulerController::class, 'store'])->name('ekstrakurikuler.store');
    Route::put('ekstrakurikuler/{ekstrakurikuler}', [LembagaEkstrakurikulerController::class, 'update'])->name('ekstrakurikuler.update');
    Route::delete('ekstrakurikuler/{ekstrakurikuler}', [LembagaEkstrakurikulerController::class, 'destroy'])->name('ekstrakurikuler.destroy');

    Route::post('layanan-khusus', [LembagaLayananKhususController::class, 'store'])->name('layanan-khusus.store');
    Route::put('layanan-khusus/{layananKhusus}', [LembagaLayananKhususController::class, 'update'])->name('layanan-khusus.update');
    Route::delete('layanan-khusus/{layananKhusus}', [LembagaLayananKhususController::class, 'destroy'])->name('layanan-khusus.destroy');

    Route::post('program-inklusi', [LembagaProgramInklusiController::class, 'store'])->name('program-inklusi.store');
    Route::put('program-inklusi/{programInklusi}', [LembagaProgramInklusiController::class, 'update'])->name('program-inklusi.update');
    Route::delete('program-inklusi/{programInklusi}', [LembagaProgramInklusiController::class, 'destroy'])->name('program-inklusi.destroy');
});
```

- [ ] **Step 2: Snapshot `route:list` sebelum edit**

Run: `php artisan route:list > /tmp/before-task2.txt`

- [ ] **Step 3: Di `routes/admin.php`, cari blok berikut dan ganti dengan `require base_path('routes/admin/lembaga.php');`**

Cari (persis dari `Route::resource('lembaga'...` sampai penutup `});` grup `lembaga/{lembaga}`):
```php
    Route::resource('lembaga', LembagaController::class)->except(['show', 'destroy']);
    Route::get('pengaturan-yayasan', [\App\Http\Controllers\Admin\YayasanSettingController::class, 'edit'])->name('yayasan.edit');
    Route::put('pengaturan-yayasan', [\App\Http\Controllers\Admin\YayasanSettingController::class, 'update'])->name('yayasan.update');
    Route::prefix('lembaga/{lembaga}')->name('lembaga.')->group(function () {
        Route::post('data-periodik', [LembagaDataPeriodikController::class, 'store'])->name('data-periodik.store');
        Route::put('data-periodik/{dataPeriodik}', [LembagaDataPeriodikController::class, 'update'])->name('data-periodik.update');
        Route::delete('data-periodik/{dataPeriodik}', [LembagaDataPeriodikController::class, 'destroy'])->name('data-periodik.destroy');

        Route::post('ekstrakurikuler', [LembagaEkstrakurikulerController::class, 'store'])->name('ekstrakurikuler.store');
        Route::put('ekstrakurikuler/{ekstrakurikuler}', [LembagaEkstrakurikulerController::class, 'update'])->name('ekstrakurikuler.update');
        Route::delete('ekstrakurikuler/{ekstrakurikuler}', [LembagaEkstrakurikulerController::class, 'destroy'])->name('ekstrakurikuler.destroy');

        Route::post('program-inklusi', [LembagaProgramInklusiController::class, 'store'])->name('program-inklusi.store');
        Route::put('program-inklusi/{programInklusi}', [LembagaProgramInklusiController::class, 'update'])->name('program-inklusi.update');
        Route::delete('program-inklusi/{programInklusi}', [LembagaProgramInklusiController::class, 'destroy'])->name('program-inklusi.destroy');
    });
```

Ganti dengan: `require base_path('routes/admin/lembaga.php');`

(Catatan: cek juga baris `layanan-khusus` di antara `ekstrakurikuler` dan `program-inklusi` pada file asli — sertakan dalam blok yang dihapus, sudah tercakup di file baru Step 1.)

- [ ] **Step 4: Hapus `use` statement lembaga yang sudah tidak dipakai di `routes/admin.php`** (cek dulu dengan grep seperti Task 1 Step 6, khususnya `LembagaController` — hanya hapus kalau grep hasilnya 0 pemakaian tersisa)

- [ ] **Step 5: Verifikasi**

Run: `php artisan route:list > /tmp/after-task2.txt && diff <(sort /tmp/before-task2.txt) <(sort /tmp/after-task2.txt)`
Expected: no output.

- [ ] **Step 6: Commit**

```bash
git add routes/admin.php routes/admin/lembaga.php
git commit -m "refactor(routes): ekstrak modul lembaga ke routes/admin/"
```

---

### Task 3: Modul Guru (Data Kepegawaian)

**Files:**
- Create: `routes/admin/guru-data.php`
- Modify: `routes/admin.php`

**Interfaces:**
- Consumes: pola Task 1-2
- Produces: tidak ada yang dikonsumsi task lain

- [ ] **Step 1: Buat `routes/admin/guru-data.php`**

```php
<?php

use App\Http\Controllers\Admin\Guru\JabatanTambahanController as GuruJabatanTambahanController;
use App\Http\Controllers\Admin\Guru\RiwayatPendidikanController as GuruRiwayatPendidikanController;
use App\Http\Controllers\Admin\Guru\SertifikasiController as GuruSertifikasiController;
use App\Http\Controllers\Admin\GuruController;
use App\Http\Controllers\Admin\JabatanTambahanMasterController;
use App\Http\Controllers\Admin\JenisKaryawanMasterController;
use Illuminate\Support\Facades\Route;

Route::resource('guru', GuruController::class)->except(['show', 'destroy']);
Route::patch('guru/{guru}/status', [GuruController::class, 'updateStatus'])->name('guru.update-status');

Route::post('guru/{guru}/riwayat-pendidikan', [GuruRiwayatPendidikanController::class, 'store'])->name('guru.riwayat-pendidikan.store');
Route::put('guru/{guru}/riwayat-pendidikan/{riwayatPendidikan}', [GuruRiwayatPendidikanController::class, 'update'])->name('guru.riwayat-pendidikan.update');
Route::delete('guru/{guru}/riwayat-pendidikan/{riwayatPendidikan}', [GuruRiwayatPendidikanController::class, 'destroy'])->name('guru.riwayat-pendidikan.destroy');

Route::post('guru/{guru}/sertifikasi', [GuruSertifikasiController::class, 'store'])->name('guru.sertifikasi.store');
Route::put('guru/{guru}/sertifikasi/{sertifikasi}', [GuruSertifikasiController::class, 'update'])->name('guru.sertifikasi.update');
Route::delete('guru/{guru}/sertifikasi/{sertifikasi}', [GuruSertifikasiController::class, 'destroy'])->name('guru.sertifikasi.destroy');

Route::post('guru/{guru}/jabatan-tambahan', [GuruJabatanTambahanController::class, 'store'])->name('guru.jabatan-tambahan.store');
Route::delete('guru/{guru}/jabatan-tambahan/{jabatanMasterId}', [GuruJabatanTambahanController::class, 'destroy'])->name('guru.jabatan-tambahan.destroy');
Route::get('jabatan-tambahan-master', [JabatanTambahanMasterController::class, 'index'])->name('jabatan-tambahan-master.index');
Route::post('jabatan-tambahan-master', [JabatanTambahanMasterController::class, 'store'])->name('jabatan-tambahan-master.store');
Route::put('jabatan-tambahan-master/{jabatanTambahanMaster}', [JabatanTambahanMasterController::class, 'update'])->name('jabatan-tambahan-master.update');
Route::delete('jabatan-tambahan-master/{jabatanTambahanMaster}', [JabatanTambahanMasterController::class, 'destroy'])->name('jabatan-tambahan-master.destroy');

Route::get('jenis-karyawan-master', [JenisKaryawanMasterController::class, 'index'])->name('jenis-karyawan-master.index');
Route::post('jenis-karyawan-master', [JenisKaryawanMasterController::class, 'store'])->name('jenis-karyawan-master.store');
Route::put('jenis-karyawan-master/{jenisKaryawanMaster}', [JenisKaryawanMasterController::class, 'update'])->name('jenis-karyawan-master.update');
Route::delete('jenis-karyawan-master/{jenisKaryawanMaster}', [JenisKaryawanMasterController::class, 'destroy'])->name('jenis-karyawan-master.destroy');
```

- [ ] **Step 2: Snapshot route:list, hapus blok yang sama persis di `routes/admin.php` (dari `Route::resource('guru'...` sampai baris `jenis-karyawan-master` destroy), ganti dengan `require base_path('routes/admin/guru-data.php');`, hapus use statement yang sudah tidak terpakai, verifikasi diff kosong, commit.**

Ikuti pola Step 2-6 dari Task 2 (snapshot → cari-hapus-ganti → bersihkan use → diff → commit), dengan pesan commit: `"refactor(routes): ekstrak modul guru-data ke routes/admin/"`.

---

### Task 4: Modul Akademik Master (multi-blok)

**Files:**
- Create: `routes/admin/akademik-master.php`
- Modify: `routes/admin.php`

**Interfaces:**
- Consumes: pola Task 1-3
- Produces: tidak ada

Modul ini TERSEBAR di 4 blok tidak berdekatan di source asli (mata-pelajaran+kelas, lalu kalender-akademik+pengaturan-akademik, lalu tahun-ajaran+semester, lalu pola-jam+jam-pelajaran+jadwal-pelajaran). Ikuti Konteks Umum: blok PERTAMA diganti `require`, blok ke-2/3/4 cukup DIHAPUS (tanpa require lagi).

- [ ] **Step 1: Buat `routes/admin/akademik-master.php`** (gabungan ke-4 blok, urutan dipertahankan sesuai source)

```php
<?php

use App\Http\Controllers\Admin\JadwalPelajaranController;
use App\Http\Controllers\Admin\JamPelajaranController;
use App\Http\Controllers\Admin\KalenderAkademikController;
use App\Http\Controllers\Admin\KelasController;
use App\Http\Controllers\Admin\MataPelajaranController;
use App\Http\Controllers\Admin\PengaturanAkademikController;
use App\Http\Controllers\Admin\PolaJamController;
use App\Http\Controllers\Admin\SemesterController;
use App\Http\Controllers\Admin\TahunAjaranController;
use Illuminate\Support\Facades\Route;

Route::resource('mata-pelajaran', MataPelajaranController::class)->except(['show', 'destroy']);
Route::resource('kelas', KelasController::class)->parameters(['kelas' => 'kelas'])->except(['show', 'destroy']);

Route::post('kalender-akademik', [KalenderAkademikController::class, 'store'])->name('kalender-akademik.store');
Route::put('kalender-akademik/{kalenderAkademik}', [KalenderAkademikController::class, 'update'])->name('kalender-akademik.update');
Route::delete('kalender-akademik/{kalenderAkademik}', [KalenderAkademikController::class, 'destroy'])->name('kalender-akademik.destroy');
Route::get('pengaturan/akademik', [PengaturanAkademikController::class, 'index'])->name('pengaturan.akademik.index');
Route::put('pengaturan/akademik/hari-aktif', [PengaturanAkademikController::class, 'updateHariAktif'])->name('pengaturan.akademik.hari-aktif');

Route::get('tahun-ajaran', [TahunAjaranController::class, 'index'])->name('tahun-ajaran.index');
Route::get('tahun-ajaran/create', [TahunAjaranController::class, 'create'])->name('tahun-ajaran.create');
Route::post('tahun-ajaran', [TahunAjaranController::class, 'store'])->name('tahun-ajaran.store');
Route::put('tahun-ajaran/{tahunAjaran}', [TahunAjaranController::class, 'update'])->name('tahun-ajaran.update');
Route::patch('tahun-ajaran/{tahunAjaran}/activate', [TahunAjaranController::class, 'activate'])->name('tahun-ajaran.activate');

Route::post('semester', [SemesterController::class, 'store'])->name('semester.store');
Route::patch('semester/{semester}/activate', [SemesterController::class, 'activate'])->name('semester.activate');

Route::get('pola-jam', [PolaJamController::class, 'index'])->name('pola-jam.index');
Route::get('pola-jam/create', [PolaJamController::class, 'create'])->name('pola-jam.create');
Route::post('pola-jam', [PolaJamController::class, 'store'])->name('pola-jam.store');
Route::get('pola-jam/{polaJam}/edit', [PolaJamController::class, 'edit'])->name('pola-jam.edit');
Route::put('pola-jam/{polaJam}', [PolaJamController::class, 'update'])->name('pola-jam.update');
Route::delete('pola-jam/{polaJam}', [PolaJamController::class, 'destroy'])->name('pola-jam.destroy');
Route::put('pola-jam/{polaJam}/assign-kelas', [PolaJamController::class, 'assignKelas'])->name('pola-jam.assign-kelas');
Route::post('pola-jam/{polaJam}/duplicate', [PolaJamController::class, 'duplicate'])->name('pola-jam.duplicate');
Route::post('jam-pelajaran', [JamPelajaranController::class, 'store'])->name('jam-pelajaran.store');
Route::get('jam-pelajaran/{jamPelajaran}/edit', [JamPelajaranController::class, 'edit'])->name('jam-pelajaran.edit');
Route::put('jam-pelajaran/{jamPelajaran}', [JamPelajaranController::class, 'update'])->name('jam-pelajaran.update');
Route::delete('jam-pelajaran/{jamPelajaran}', [JamPelajaranController::class, 'destroy'])->name('jam-pelajaran.destroy');

Route::get('jadwal-pelajaran', [JadwalPelajaranController::class, 'index'])->name('jadwal-pelajaran.index');
Route::get('jadwal-pelajaran/opsi', [JadwalPelajaranController::class, 'opsi'])->name('jadwal-pelajaran.opsi');
Route::get('jadwal-pelajaran/create', [JadwalPelajaranController::class, 'create'])->name('jadwal-pelajaran.create');
Route::post('jadwal-pelajaran', [JadwalPelajaranController::class, 'store'])->name('jadwal-pelajaran.store');
Route::get('jadwal-pelajaran/{jadwalPelajaran}/edit', [JadwalPelajaranController::class, 'edit'])->name('jadwal-pelajaran.edit');
Route::put('jadwal-pelajaran/{jadwalPelajaran}', [JadwalPelajaranController::class, 'update'])->name('jadwal-pelajaran.update');
Route::delete('jadwal-pelajaran/{jadwalPelajaran}', [JadwalPelajaranController::class, 'destroy'])->name('jadwal-pelajaran.destroy');
Route::post('jadwal-pelajaran/duplicate', [JadwalPelajaranController::class, 'duplicate'])->name('jadwal-pelajaran.duplicate');
```

- [ ] **Step 2: Snapshot route:list sebelum edit**

- [ ] **Step 3: Di `routes/admin.php`, cari blok 1 (mata-pelajaran + kelas) dan ganti dengan require**

Cari:
```php
    Route::resource('mata-pelajaran', MataPelajaranController::class)->except(['show', 'destroy']);
    Route::resource('kelas', KelasController::class)->parameters(['kelas' => 'kelas'])->except(['show', 'destroy']);
```
Ganti dengan: `    require base_path('routes/admin/akademik-master.php');`

- [ ] **Step 4: Di `routes/admin.php`, cari blok 2 (kalender-akademik + pengaturan-akademik) dan HAPUS (tanpa pengganti)**

Cari dan hapus seluruhnya:
```php
    Route::post('kalender-akademik', [KalenderAkademikController::class, 'store'])->name('kalender-akademik.store');
    Route::put('kalender-akademik/{kalenderAkademik}', [KalenderAkademikController::class, 'update'])->name('kalender-akademik.update');
    Route::delete('kalender-akademik/{kalenderAkademik}', [KalenderAkademikController::class, 'destroy'])->name('kalender-akademik.destroy');
    Route::get('pengaturan/akademik', [PengaturanAkademikController::class, 'index'])->name('pengaturan.akademik.index');
    Route::put('pengaturan/akademik/hari-aktif', [PengaturanAkademikController::class, 'updateHariAktif'])->name('pengaturan.akademik.hari-aktif');
```

- [ ] **Step 5: Di `routes/admin.php`, cari blok 3 (tahun-ajaran + semester) dan HAPUS**

Cari dan hapus seluruhnya:
```php
    Route::get('tahun-ajaran', [TahunAjaranController::class, 'index'])->name('tahun-ajaran.index');
    Route::get('tahun-ajaran/create', [TahunAjaranController::class, 'create'])->name('tahun-ajaran.create');
    Route::post('tahun-ajaran', [TahunAjaranController::class, 'store'])->name('tahun-ajaran.store');
    Route::put('tahun-ajaran/{tahunAjaran}', [TahunAjaranController::class, 'update'])->name('tahun-ajaran.update');
    Route::patch('tahun-ajaran/{tahunAjaran}/activate', [TahunAjaranController::class, 'activate'])->name('tahun-ajaran.activate');

    Route::post('semester', [SemesterController::class, 'store'])->name('semester.store');
    Route::patch('semester/{semester}/activate', [SemesterController::class, 'activate'])->name('semester.activate');
```

- [ ] **Step 6: Di `routes/admin.php`, cari blok 4 (pola-jam + jam-pelajaran + jadwal-pelajaran) dan HAPUS**

Cari dan hapus seluruhnya:
```php
    Route::get('pola-jam', [PolaJamController::class, 'index'])->name('pola-jam.index');
    Route::get('pola-jam/create', [PolaJamController::class, 'create'])->name('pola-jam.create');
    Route::post('pola-jam', [PolaJamController::class, 'store'])->name('pola-jam.store');
    Route::get('pola-jam/{polaJam}/edit', [PolaJamController::class, 'edit'])->name('pola-jam.edit');
    Route::put('pola-jam/{polaJam}', [PolaJamController::class, 'update'])->name('pola-jam.update');
    Route::delete('pola-jam/{polaJam}', [PolaJamController::class, 'destroy'])->name('pola-jam.destroy');
    Route::put('pola-jam/{polaJam}/assign-kelas', [PolaJamController::class, 'assignKelas'])->name('pola-jam.assign-kelas');
    Route::post('pola-jam/{polaJam}/duplicate', [PolaJamController::class, 'duplicate'])->name('pola-jam.duplicate');
    Route::post('jam-pelajaran', [JamPelajaranController::class, 'store'])->name('jam-pelajaran.store');
    Route::get('jam-pelajaran/{jamPelajaran}/edit', [JamPelajaranController::class, 'edit'])->name('jam-pelajaran.edit');
    Route::put('jam-pelajaran/{jamPelajaran}', [JamPelajaranController::class, 'update'])->name('jam-pelajaran.update');
    Route::delete('jam-pelajaran/{jamPelajaran}', [JamPelajaranController::class, 'destroy'])->name('jam-pelajaran.destroy');

    Route::get('jadwal-pelajaran', [JadwalPelajaranController::class, 'index'])->name('jadwal-pelajaran.index');
    Route::get('jadwal-pelajaran/opsi', [JadwalPelajaranController::class, 'opsi'])->name('jadwal-pelajaran.opsi');
    Route::get('jadwal-pelajaran/create', [JadwalPelajaranController::class, 'create'])->name('jadwal-pelajaran.create');
    Route::post('jadwal-pelajaran', [JadwalPelajaranController::class, 'store'])->name('jadwal-pelajaran.store');
    Route::get('jadwal-pelajaran/{jadwalPelajaran}/edit', [JadwalPelajaranController::class, 'edit'])->name('jadwal-pelajaran.edit');
    Route::put('jadwal-pelajaran/{jadwalPelajaran}', [JadwalPelajaranController::class, 'update'])->name('jadwal-pelajaran.update');
    Route::delete('jadwal-pelajaran/{jadwalPelajaran}', [JadwalPelajaranController::class, 'destroy'])->name('jadwal-pelajaran.destroy');
    Route::post('jadwal-pelajaran/duplicate', [JadwalPelajaranController::class, 'duplicate'])->name('jadwal-pelajaran.duplicate');
```

- [ ] **Step 7: Bersihkan `use` statement yang tidak terpakai lagi (grep dulu seperti task sebelumnya), verifikasi diff route:list kosong, commit** dengan pesan `"refactor(routes): ekstrak modul akademik-master ke routes/admin/"`.

---

### Task 5: Modul Siswa

**Files:**
- Create: `routes/admin/siswa.php`
- Modify: `routes/admin.php`

- [ ] **Step 1: Buat `routes/admin/siswa.php`**

```php
<?php

use App\Http\Controllers\Admin\KaryawanController;
use App\Http\Controllers\Admin\OrangTuaController;
use App\Http\Controllers\Admin\PendaftaranSiswaController;
use App\Http\Controllers\Admin\SiswaController;
use App\Http\Controllers\Admin\SiswaImportController;
use App\Http\Controllers\Admin\SiswaOrangTuaController;
use Illuminate\Support\Facades\Route;

Route::post('siswa/generate-akun-massal', [SiswaController::class, 'generateAkunMassal'])->name('siswa.generate-akun-massal');
Route::post('siswa/{siswa}/generate-akun', [SiswaController::class, 'generateAkun'])->name('siswa.generate-akun');
Route::resource('siswa', SiswaController::class)->except(['show', 'destroy']);
Route::get('siswa/{siswa}/orang-tua/cari', [SiswaOrangTuaController::class, 'cari'])->name('siswa.orang-tua.cari');
Route::post('siswa/{siswa}/orang-tua', [SiswaOrangTuaController::class, 'store'])->name('siswa.orang-tua.store');
Route::patch('siswa/{siswa}/orang-tua/{orangTua}/kontak-utama', [SiswaOrangTuaController::class, 'updateKontakUtama'])->name('siswa.orang-tua.kontak-utama');
Route::delete('siswa/{siswa}/orang-tua/{orangTua}', [SiswaOrangTuaController::class, 'destroy'])->name('siswa.orang-tua.destroy');
Route::resource('orang-tua', OrangTuaController::class)->except(['show', 'destroy']);
Route::patch('orang-tua/{orangTua}/status', [OrangTuaController::class, 'updateStatus'])->name('orang-tua.update-status');
Route::resource('karyawan', KaryawanController::class)->except(['show', 'destroy']);
Route::patch('karyawan/{karyawan}/status', [KaryawanController::class, 'updateStatus'])->name('karyawan.update-status');
Route::patch('siswa/{siswa}/status', [SiswaController::class, 'updateStatus'])->name('siswa.update-status');
Route::patch('siswa/{siswa}/reset-password', [SiswaController::class, 'resetPassword'])->name('siswa.reset-password');
Route::get('siswa-spmb-daftar', [PendaftaranSiswaController::class, 'index'])->name('siswa.spmb-daftar.index');
Route::post('siswa-spmb-daftar', [PendaftaranSiswaController::class, 'store'])->name('siswa.spmb-daftar.store');
Route::get('siswa-import', [SiswaImportController::class, 'index'])->name('siswa.import.index');
Route::get('siswa-import/template', [SiswaImportController::class, 'template'])->name('siswa.import.template');
Route::post('siswa-import/preview', [SiswaImportController::class, 'preview'])->name('siswa.import.preview');
Route::post('siswa-import/confirm', [SiswaImportController::class, 'confirm'])->name('siswa.import.confirm');
```

- [ ] **Step 2: Di `routes/admin.php`, cari blok berikut (satu blok berdekatan mencakup siswa+orang-tua+karyawan, kemudian blok kedua siswa-spmb-daftar+siswa-import beberapa baris di bawahnya) dan tangani seperti Task 4 (blok pertama → require, blok kedua → hapus tanpa pengganti):**

Blok 1 (ganti dengan `require base_path('routes/admin/siswa.php');`):
```php
    Route::post('siswa/generate-akun-massal', [SiswaController::class, 'generateAkunMassal'])->name('siswa.generate-akun-massal');
    Route::post('siswa/{siswa}/generate-akun', [SiswaController::class, 'generateAkun'])->name('siswa.generate-akun');
    Route::resource('siswa', SiswaController::class)->except(['show', 'destroy']);
    Route::get('siswa/{siswa}/orang-tua/cari', [SiswaOrangTuaController::class, 'cari'])->name('siswa.orang-tua.cari');
    Route::post('siswa/{siswa}/orang-tua', [SiswaOrangTuaController::class, 'store'])->name('siswa.orang-tua.store');
    Route::patch('siswa/{siswa}/orang-tua/{orangTua}/kontak-utama', [SiswaOrangTuaController::class, 'updateKontakUtama'])->name('siswa.orang-tua.kontak-utama');
    Route::delete('siswa/{siswa}/orang-tua/{orangTua}', [SiswaOrangTuaController::class, 'destroy'])->name('siswa.orang-tua.destroy');
    Route::resource('orang-tua', OrangTuaController::class)->except(['show', 'destroy']);
    Route::patch('orang-tua/{orangTua}/status', [OrangTuaController::class, 'updateStatus'])->name('orang-tua.update-status');
    Route::resource('karyawan', KaryawanController::class)->except(['show', 'destroy']);
    Route::patch('karyawan/{karyawan}/status', [KaryawanController::class, 'updateStatus'])->name('karyawan.update-status');
    Route::patch('siswa/{siswa}/status', [SiswaController::class, 'updateStatus'])->name('siswa.update-status');
    Route::patch('siswa/{siswa}/reset-password', [SiswaController::class, 'resetPassword'])->name('siswa.reset-password');
```

Blok 2 (HAPUS, tanpa pengganti — sudah tercakup di require blok 1):
```php
    Route::get('siswa-spmb-daftar', [PendaftaranSiswaController::class, 'index'])->name('siswa.spmb-daftar.index');
    Route::post('siswa-spmb-daftar', [PendaftaranSiswaController::class, 'store'])->name('siswa.spmb-daftar.store');
    Route::get('siswa-import', [SiswaImportController::class, 'index'])->name('siswa.import.index');
    Route::get('siswa-import/template', [SiswaImportController::class, 'template'])->name('siswa.import.template');
    Route::post('siswa-import/preview', [SiswaImportController::class, 'preview'])->name('siswa.import.preview');
    Route::post('siswa-import/confirm', [SiswaImportController::class, 'confirm'])->name('siswa.import.confirm');
```

- [ ] **Step 3: Bersihkan `use` tidak terpakai, verifikasi route:list diff kosong (snapshot before/after seperti task sebelumnya), commit** dengan pesan `"refactor(routes): ekstrak modul siswa ke routes/admin/"`.

---

### Task 6: Modul SPMB

**Files:**
- Create: `routes/admin/spmb.php`
- Modify: `routes/admin.php`

**Catatan penting:** `TagihanController` dipakai DI SINI (baris `tagihan-susulan`, `use`-import) DAN di Task 7 (`routes/admin/keuangan.php`). Jangan hapus `use App\Http\Controllers\Admin\TagihanController;` dari `routes/admin.php` di task ini kalau Task 7 belum jalan — grep dulu, baru hapus kalau sudah 0 pemakaian tersisa di `routes/admin.php`.

- [ ] **Step 1: Buat `routes/admin/spmb.php`**

```php
<?php

use App\Http\Controllers\Admin\DokumenSyaratController;
use App\Http\Controllers\Admin\FormulirFieldController;
use App\Http\Controllers\Admin\GelombangPpdbController;
use App\Http\Controllers\Admin\JalurPpdbController;
use App\Http\Controllers\Admin\JenisTesMasterController;
use App\Http\Controllers\Admin\PendaftaranAdminController;
use App\Http\Controllers\Admin\SeleksiController;
use App\Http\Controllers\Admin\SkPpdbController;
use App\Http\Controllers\Admin\SpmbKonfigurasiController;
use App\Http\Controllers\Admin\TagihanController;
use Illuminate\Support\Facades\Route;

Route::get('jenis-tes', [JenisTesMasterController::class, 'index'])->name('jenis-tes.index');
Route::post('jenis-tes', [JenisTesMasterController::class, 'store'])->name('jenis-tes.store');
Route::put('jenis-tes/{jenisTes}', [JenisTesMasterController::class, 'update'])->name('jenis-tes.update');
Route::delete('jenis-tes/{jenisTes}', [JenisTesMasterController::class, 'destroy'])->name('jenis-tes.destroy');

Route::resource('gelombang-ppdb', GelombangPpdbController::class)->except(['show', 'destroy']);

Route::resource('jalur-ppdb', JalurPpdbController::class)->except(['show', 'destroy']);

Route::post('formulir-field', [FormulirFieldController::class, 'store'])->name('formulir-field.store');
Route::delete('formulir-field/{formulirField}', [FormulirFieldController::class, 'destroy'])->name('formulir-field.destroy');

Route::post('dokumen-syarat', [DokumenSyaratController::class, 'store'])->name('dokumen-syarat.store');
Route::delete('dokumen-syarat/{dokumenSyarat}', [DokumenSyaratController::class, 'destroy'])->name('dokumen-syarat.destroy');

Route::post('seleksi', [SeleksiController::class, 'store'])->name('seleksi.store');
Route::delete('seleksi/{seleksi}', [SeleksiController::class, 'destroy'])->name('seleksi.destroy');

Route::post('spmb-konfigurasi/duplikasi', [SpmbKonfigurasiController::class, 'duplikasi'])->name('spmb-konfigurasi.duplikasi');

Route::get('spmb-pendaftaran', [PendaftaranAdminController::class, 'index'])->name('spmb-pendaftaran.index');
Route::get('spmb-pendaftaran/data', [PendaftaranAdminController::class, 'data'])->name('spmb-pendaftaran.data');
Route::get('spmb-pendaftaran/{pendaftaran}', [PendaftaranAdminController::class, 'show'])->name('spmb-pendaftaran.show');
Route::post('spmb-pendaftaran/{pendaftaran}/dokumen/{dokumen}', [PendaftaranAdminController::class, 'verifikasiDokumen'])->name('spmb-pendaftaran.verifikasi-dokumen');
Route::post('spmb-pendaftaran/{pendaftaran}/nilai', [PendaftaranAdminController::class, 'simpanNilai'])->name('spmb-pendaftaran.nilai');
Route::post('spmb-pendaftaran/{pendaftaran}/keputusan', [PendaftaranAdminController::class, 'tetapkanKeputusan'])->name('spmb-pendaftaran.keputusan');
Route::post('spmb-pendaftaran/{pendaftaran}/tagihan-susulan', [TagihanController::class, 'buatSusulan'])->name('tagihan.susulan');
Route::get('spmb-pendaftaran-nilai-massal', [PendaftaranAdminController::class, 'nilaiMassal'])->name('spmb-pendaftaran.nilai-massal');
Route::post('spmb-pendaftaran-nilai-massal', [PendaftaranAdminController::class, 'simpanNilaiMassal'])->name('spmb-pendaftaran.nilai-massal.store');

Route::get('sk-ppdb/create', [SkPpdbController::class, 'create'])->name('sk-ppdb.create');
Route::post('sk-ppdb', [SkPpdbController::class, 'store'])->name('sk-ppdb.store');
```

- [ ] **Step 2: Di `routes/admin.php`, cari blok persis dari `Route::get('jenis-tes'...` sampai `Route::post('sk-ppdb'...` (satu blok berdekatan) dan ganti dengan `require base_path('routes/admin/spmb.php');`**

- [ ] **Step 3: Snapshot before/after route:list, diff harus kosong, commit** dengan pesan `"refactor(routes): ekstrak modul spmb ke routes/admin/"`.

---

### Task 7: Modul Keuangan

**Files:**
- Create: `routes/admin/keuangan.php`
- Modify: `routes/admin.php`

**Catatan:** setelah task ini, `TagihanController` sudah dipakai di 2 file baru (`spmb.php` dari Task 6 dan `keuangan.php` di sini) — TIDAK ADA MASALAH, `use` statement boleh muncul di kedua file secara independen (PHP tidak melarang import yang sama di file berbeda). Task ini yang berhak menghapus `use App\Http\Controllers\Admin\TagihanController;` dari `routes/admin.php` (karena setelah Task 6+7, pemakaiannya di `admin.php` sudah 0).

- [ ] **Step 1: Buat `routes/admin/keuangan.php`**

```php
<?php

use App\Http\Controllers\Admin\JenisTagihanController;
use App\Http\Controllers\Admin\JenisTagihanMonitoringController;
use App\Http\Controllers\Admin\KategoriKeringananController;
use App\Http\Controllers\Admin\ManualPaymentController;
use App\Http\Controllers\Admin\PembayaranController;
use App\Http\Controllers\Admin\TagihanController;
use App\Http\Controllers\Admin\VirtualAccountController;
use Illuminate\Support\Facades\Route;

Route::get('jenis-tagihan/create', [JenisTagihanController::class, 'create'])->name('jenis-tagihan.create');
Route::get('jenis-tagihan', [JenisTagihanController::class, 'index'])->name('jenis-tagihan.index');
Route::post('jenis-tagihan', [JenisTagihanController::class, 'store'])->name('jenis-tagihan.store');
Route::get('jenis-tagihan/{jenisTagihan}/edit', [JenisTagihanController::class, 'edit'])->name('jenis-tagihan.edit');
Route::put('jenis-tagihan/{jenisTagihan}', [JenisTagihanController::class, 'update'])->name('jenis-tagihan.update');
Route::delete('jenis-tagihan/{jenisTagihan}', [JenisTagihanController::class, 'destroy'])->name('jenis-tagihan.destroy');
Route::post('jenis-tagihan/{jenisTagihan}/proses', [JenisTagihanController::class, 'prosesTagihan'])->name('jenis-tagihan.proses');
Route::get('jenis-tagihan/{jenisTagihan}/nominal', [JenisTagihanController::class, 'nominal'])->name('jenis-tagihan.nominal');
Route::post('jenis-tagihan/{jenisTagihan}/nominal', [JenisTagihanController::class, 'simpanNominal'])->name('jenis-tagihan.nominal.store');

Route::get('jenis-tagihan/{jenisTagihan}/monitoring', [JenisTagihanMonitoringController::class, 'index'])->name('jenis-tagihan.monitoring.index');
Route::post('jenis-tagihan/{jenisTagihan}/batal-tagihan/{tagihan}', [JenisTagihanMonitoringController::class, 'batalTagihan'])->name('jenis-tagihan.monitoring.batal');

Route::post('kategori-keringanan', [KategoriKeringananController::class, 'store'])->name('kategori-keringanan.store');

Route::get('tagihan', [TagihanController::class, 'index'])->name('tagihan.index');
Route::get('tagihan/data', [TagihanController::class, 'data'])->name('tagihan.data');
Route::post('tagihan/{tagihan}/skema-cicilan', [TagihanController::class, 'buatSkemaCicilan'])->name('tagihan.skema-cicilan.store');
Route::post('skema-cicilan/{skemaCicilan}/nominal', [TagihanController::class, 'simpanNominalCicilan'])->name('skema-cicilan.nominal.store');
Route::post('tagihan/{tagihan}/catat-manual', [TagihanController::class, 'catatManualTagihan'])->name('tagihan.catat-manual');
Route::post('cicilan/{cicilan}/catat-manual', [TagihanController::class, 'catatManualCicilan'])->name('cicilan.catat-manual');

Route::get('pembayaran', [PembayaranController::class, 'index'])->name('pembayaran.index');
Route::get('pembayaran/data', [PembayaranController::class, 'data'])->name('pembayaran.data');
Route::post('pembayaran/{pembayaran}/verifikasi', [PembayaranController::class, 'verifikasi'])->name('pembayaran.verifikasi');

Route::get('manual-payment', [ManualPaymentController::class, 'index'])->name('manual-payment.index');
Route::post('manual-payment/{manualPaymentRequest}/approve', [ManualPaymentController::class, 'approve'])->name('manual-payment.approve');
Route::post('manual-payment/{manualPaymentRequest}/reject', [ManualPaymentController::class, 'reject'])->name('manual-payment.reject');

Route::get('virtual-account', [VirtualAccountController::class, 'index'])->name('virtual-account.index');
Route::get('virtual-account/{siswa}/riwayat', [VirtualAccountController::class, 'riwayat'])->name('virtual-account.riwayat');
Route::get('virtual-account/calon', [VirtualAccountController::class, 'calonGenerate'])->name('virtual-account.calon');
Route::post('virtual-account/generate', [VirtualAccountController::class, 'generate'])->name('virtual-account.generate');
Route::get('virtual-account/export', [VirtualAccountController::class, 'export'])->name('virtual-account.export');
```

- [ ] **Step 2: Di `routes/admin.php`, cari blok persis dari `Route::get('jenis-tagihan/create'...` sampai `Route::get('virtual-account/export'...` dan ganti dengan `require base_path('routes/admin/keuangan.php');`**

- [ ] **Step 3: Bersihkan `use` (termasuk `TagihanController` sekarang, cek grep = 0), snapshot before/after route:list, diff kosong, commit** dengan pesan `"refactor(routes): ekstrak modul keuangan ke routes/admin/"`.

---

### Task 8: Modul RPP

**Files:**
- Create: `routes/admin/rpp.php`
- Modify: `routes/admin.php`

- [ ] **Step 1: Buat `routes/admin/rpp.php`**

```php
<?php

use App\Http\Controllers\Admin\RppController;
use Illuminate\Support\Facades\Route;

// Perangkat Mengajar (RPP / Modul Ajar)
Route::get('rpp', [RppController::class, 'index'])->name('rpp.index');
Route::post('rpp', [RppController::class, 'store'])->name('rpp.store');
Route::get('rpp/{rpp}/download', [RppController::class, 'download'])->name('rpp.download');
Route::put('rpp/{rpp}', [RppController::class, 'update'])->name('rpp.update');
Route::delete('rpp/{rpp}', [RppController::class, 'destroy'])->name('rpp.destroy');
Route::post('rpp/{rpp}/submit', [RppController::class, 'submit'])->name('rpp.submit');
Route::post('rpp/{rpp}/verify', [RppController::class, 'verify'])->name('rpp.verify');
```

- [ ] **Step 2: Di `routes/admin.php`, cari blok (termasuk komentarnya) dan ganti dengan `require base_path('routes/admin/rpp.php');`**

Cari:
```php
    // Perangkat Mengajar (RPP / Modul Ajar)
    Route::get('rpp', [RppController::class, 'index'])->name('rpp.index');
    Route::post('rpp', [RppController::class, 'store'])->name('rpp.store');
    Route::get('rpp/{rpp}/download', [RppController::class, 'download'])->name('rpp.download');
    Route::put('rpp/{rpp}', [RppController::class, 'update'])->name('rpp.update');
    Route::delete('rpp/{rpp}', [RppController::class, 'destroy'])->name('rpp.destroy');
    Route::post('rpp/{rpp}/submit', [RppController::class, 'submit'])->name('rpp.submit');
    Route::post('rpp/{rpp}/verify', [RppController::class, 'verify'])->name('rpp.verify');
```

- [ ] **Step 3: Bersihkan use, snapshot before/after route:list, diff kosong, commit** dengan pesan `"refactor(routes): ekstrak modul rpp ke routes/admin/"`.

---

### Task 9: Modul Penilaian & Rapor

**Files:**
- Create: `routes/admin/penilaian-rapor.php`
- Modify: `routes/admin.php`

- [ ] **Step 1: Buat `routes/admin/penilaian-rapor.php`**

```php
<?php

use App\Http\Controllers\Admin\KenaikanKelasController;
use App\Http\Controllers\Admin\KomponenPenilaianController;
use App\Http\Controllers\Admin\RaporController;
use Illuminate\Support\Facades\Route;

Route::get('komponen-penilaian', [KomponenPenilaianController::class, 'index'])->name('komponen-penilaian.index');
Route::get('komponen-penilaian/create', [KomponenPenilaianController::class, 'create'])->name('komponen-penilaian.create');
Route::post('komponen-penilaian', [KomponenPenilaianController::class, 'store'])->name('komponen-penilaian.store');
Route::get('komponen-penilaian/opsi', [KomponenPenilaianController::class, 'opsi'])->name('komponen-penilaian.opsi');
Route::get('komponen-penilaian/{komponenPenilaian}/edit', [KomponenPenilaianController::class, 'edit'])->name('komponen-penilaian.edit');
Route::put('komponen-penilaian/{komponenPenilaian}', [KomponenPenilaianController::class, 'update'])->name('komponen-penilaian.update');
Route::delete('komponen-penilaian/{komponenPenilaian}', [KomponenPenilaianController::class, 'destroy'])->name('komponen-penilaian.destroy');
Route::get('rapor', [RaporController::class, 'index'])->name('rapor.index');
Route::get('rapor/opsi', [RaporController::class, 'opsi'])->name('rapor.opsi');
Route::get('rapor/cetak', [RaporController::class, 'cetak'])->name('rapor.cetak');
Route::get('rapor/persetujuan', [\App\Http\Controllers\Lembaga\Rapor\PersetujuanController::class, 'index'])->name('rapor.persetujuan.index');
Route::get('rapor/persetujuan/{pengajuanRapor}', [\App\Http\Controllers\Lembaga\Rapor\PersetujuanController::class, 'show'])->name('rapor.persetujuan.show');
Route::post('rapor/persetujuan/{pengajuanRapor}/keputusan', [\App\Http\Controllers\Lembaga\Rapor\PersetujuanController::class, 'decision'])->name('rapor.persetujuan.decision');
Route::get('rapor/persetujuan/{pengajuanRapor}/cetak/{siswa}', [\App\Http\Controllers\Lembaga\Rapor\PersetujuanController::class, 'cetak'])->name('rapor.persetujuan.cetak');

Route::get('kenaikan-kelas', [KenaikanKelasController::class, 'index'])->name('kenaikan-kelas.index');
Route::post('kenaikan-kelas', [KenaikanKelasController::class, 'store'])->name('kenaikan-kelas.store');
```

- [ ] **Step 2: Di `routes/admin.php`, cari blok persis di atas dan ganti dengan `require base_path('routes/admin/penilaian-rapor.php');`**

- [ ] **Step 3: Bersihkan use, snapshot before/after route:list, diff kosong, commit** dengan pesan `"refactor(routes): ekstrak modul penilaian-rapor ke routes/admin/"`.

---

### Task 10: Modul Kasus (Admin)

**Files:**
- Create: `routes/admin/kasus-admin.php`
- Modify: `routes/admin.php`

**Catatan:** ini BEDA dari `routes/kasus.php` (Task 13) — file ini berisi rute `admin.kasus.*` (manajemen kasus oleh admin: index, triase, assign-konselor, destroy, restore, log-akses, terhapus), tetap di bawah prefix `/admin`.

- [ ] **Step 1: Buat `routes/admin/kasus-admin.php`**

```php
<?php

use App\Http\Controllers\Admin\KasusAksesLogController;
use App\Http\Controllers\Admin\KasusController as AdminKasusController;
use App\Http\Controllers\Admin\KasusTerhapusController;
use Illuminate\Support\Facades\Route;

Route::get('kasus', [AdminKasusController::class, 'index'])->name('kasus.index');
Route::get('kasus/{kasus}/triase', [AdminKasusController::class, 'triase'])->name('kasus.triase');
Route::post('kasus/{kasus}/assign-konselor', [AdminKasusController::class, 'assignKonselor'])->name('kasus.assign-konselor');
Route::delete('kasus/{kasus}', [AdminKasusController::class, 'destroy'])->name('kasus.destroy');
Route::post('kasus/{kasus}/pulihkan', [AdminKasusController::class, 'restore'])->name('kasus.restore');
Route::get('kasus-log-akses', [KasusAksesLogController::class, 'index'])->name('kasus.log-akses');
Route::get('kasus-terhapus', [KasusTerhapusController::class, 'index'])->name('kasus.terhapus');
```

- [ ] **Step 2: Di `routes/admin.php`, cari blok persis di atas (dengan komentar `// Sarana & Prasarana (Sarpras)` di baris SETELAHnya — jangan ikut terhapus, itu milik Task 11) dan ganti dengan `require base_path('routes/admin/kasus-admin.php');`**

- [ ] **Step 3: Bersihkan use, snapshot before/after route:list, diff kosong, commit** dengan pesan `"refactor(routes): ekstrak modul kasus-admin ke routes/admin/"`.

---

### Task 11: Modul Sarpras

**Files:**
- Create: `routes/admin/sarpras.php`
- Modify: `routes/admin.php`

- [ ] **Step 1: Buat `routes/admin/sarpras.php`**

```php
<?php

use Illuminate\Support\Facades\Route;

// Sarana & Prasarana (Sarpras)
Route::prefix('sarpras')->name('sarpras.')->group(function () {
    Route::resource('gedung', \App\Http\Controllers\Lembaga\Sarpras\GedungController::class)->except(['show']);
    Route::resource('ruangan', \App\Http\Controllers\Lembaga\Sarpras\RuanganController::class);
    Route::resource('kategori', \App\Http\Controllers\Lembaga\Sarpras\KategoriAsetController::class)->only(['index', 'store', 'destroy']);
    Route::resource('aset', \App\Http\Controllers\Lembaga\Sarpras\AsetBarangController::class);
    Route::get('mutasi', [\App\Http\Controllers\Lembaga\Sarpras\MutasiAsetController::class, 'index'])->name('mutasi.index');
    Route::post('mutasi', [\App\Http\Controllers\Lembaga\Sarpras\MutasiAsetController::class, 'store'])->name('mutasi.store');
    Route::get('kir/{ruangan}', [\App\Http\Controllers\Lembaga\Sarpras\KirController::class, 'show'])->name('kir.show');
    Route::get('kir/{ruangan}/export-pdf', [\App\Http\Controllers\Lembaga\Sarpras\KirController::class, 'exportPdf'])->name('kir.export');
    Route::get('rekap-global', [\App\Http\Controllers\Yayasan\Sarpras\RekapAsetGlobalController::class, 'index'])->name('rekap-global');
});
```

(Tidak perlu `use` tambahan — semua controller sudah dipanggil pakai FQCN inline di source asli.)

- [ ] **Step 2: Di `routes/admin.php`, cari blok persis di atas (termasuk komentar `// Sarana & Prasarana (Sarpras)`) dan ganti dengan `require base_path('routes/admin/sarpras.php');`**

- [ ] **Step 3: Snapshot before/after route:list, diff kosong, commit** dengan pesan `"refactor(routes): ekstrak modul sarpras ke routes/admin/"`.

---

### Task 12: Modul Pengadaan

**Files:**
- Create: `routes/admin/pengadaan.php`
- Modify: `routes/admin.php`

- [ ] **Step 1: Buat `routes/admin/pengadaan.php`**

```php
<?php

use Illuminate\Support\Facades\Route;

// Pengadaan & LPJ Sarpras
Route::prefix('pengadaan')->name('pengadaan.')->group(function () {
    // Portal Lembaga
    Route::resource('proposal', \App\Http\Controllers\Lembaga\Pengadaan\PengajuanPengadaanController::class);
    Route::post('proposal/{proposal}/submit', [\App\Http\Controllers\Lembaga\Pengadaan\PengajuanPengadaanController::class, 'submit'])->name('proposal.submit');
    Route::get('lpj/{proposal}/create', [\App\Http\Controllers\Lembaga\Pengadaan\LpjPengadaanController::class, 'create'])->name('lpj.create');
    Route::post('lpj/{proposal}', [\App\Http\Controllers\Lembaga\Pengadaan\LpjPengadaanController::class, 'store'])->name('lpj.store');
    Route::get('lpj/{lpj}/staging-inventory', [\App\Http\Controllers\Lembaga\Pengadaan\LpjPengadaanController::class, 'stagingInventory'])->name('lpj.staging-inventory');
    Route::post('lpj/{lpj}/convert-inventory', [\App\Http\Controllers\Lembaga\Pengadaan\LpjPengadaanController::class, 'convertInventory'])->name('lpj.convert-inventory');

    // Portal Yayasan & Approval
    Route::get('inbox', [\App\Http\Controllers\Yayasan\Pengadaan\ApprovalPengadaanController::class, 'index'])->name('inbox.index');
    Route::get('inbox/{proposal}', [\App\Http\Controllers\Yayasan\Pengadaan\ApprovalPengadaanController::class, 'review'])->name('inbox.review');
    Route::post('inbox/{proposal}/decision', [\App\Http\Controllers\Yayasan\Pengadaan\ApprovalPengadaanController::class, 'decision'])->name('inbox.decision');
    Route::get('disbursement', [\App\Http\Controllers\Yayasan\Pengadaan\DisbursementPengadaanController::class, 'index'])->name('disbursement.index');
    Route::post('disbursement/{proposal}', [\App\Http\Controllers\Yayasan\Pengadaan\DisbursementPengadaanController::class, 'store'])->name('disbursement.store');
    Route::get('audit-lpj', [\App\Http\Controllers\Yayasan\Pengadaan\AuditLpjController::class, 'index'])->name('audit-lpj.index');
    Route::get('audit-lpj/{lpj}', [\App\Http\Controllers\Yayasan\Pengadaan\AuditLpjController::class, 'show'])->name('audit-lpj.show');
    Route::post('audit-lpj/{lpj}/verify', [\App\Http\Controllers\Yayasan\Pengadaan\AuditLpjController::class, 'verify'])->name('audit-lpj.verify');
});
```

- [ ] **Step 2: Di `routes/admin.php`, cari blok persis di atas (termasuk komentar `// Pengadaan & LPJ Sarpras`) dan ganti dengan `require base_path('routes/admin/pengadaan.php');`**

Setelah task ini, isi `Route::group` utama di `routes/admin.php` seharusnya HANYA berisi 13 baris `require`, sesuai spec §3.2. Verifikasi manual: buka `routes/admin.php`, pastikan tidak ada baris `Route::` lain tersisa di dalam grup utama selain 13 `require`.

- [ ] **Step 3: Snapshot before/after route:list, diff kosong, commit** dengan pesan `"refactor(routes): ekstrak modul pengadaan ke routes/admin/, admin.php kini murni loader"`.

---

### Task 13: Pisahkan `routes/kasus.php`

**Files:**
- Create: `routes/kasus.php`
- Modify: `routes/admin.php` (hapus blok yang dipindah)
- Modify: `routes/web.php` (tambah require)

**Interfaces:**
- Consumes: tidak ada
- Produces: pola `require __DIR__.'/kasus.php';` yang jadi acuan Task 14

- [ ] **Step 1: Buat `routes/kasus.php`**

```php
<?php

use App\Http\Controllers\KasusConsentController;
use App\Http\Controllers\KasusController;
use App\Http\Controllers\KasusEvaluasiController;
use App\Http\Controllers\KasusSesiController;
use App\Http\Controllers\KasusTugasBatchPreviewController;
use App\Http\Controllers\KasusTugasController;
use App\Http\Controllers\KasusTugasSubmissionController;
use Illuminate\Support\Facades\Route;

// Orang tua accounts have no lembaga_id of their own, so implicit route-model binding's
// default TenantScope-applied lookup would 404 on {kasus} before the controller's own
// isSubmitter/isKontakUtama/kasus.triase authorization logic ever runs. Bind explicitly,
// bypassing the tenant scope; real authorization stays inside each controller action.
Route::bind('kasus', function ($value) {
    return \App\Models\Kasus::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
        ->withTrashed()
        ->findOrFail($value);
});

Route::middleware(['auth', 'verified'])->prefix('kasus')->name('kasus.')->group(function () {
    Route::get('/', [KasusController::class, 'index'])->name('index');
    Route::get('ajukan', [KasusController::class, 'create'])->name('create');
    Route::post('/', [KasusController::class, 'store'])->name('store');
    Route::get('{kasus}', [KasusController::class, 'show'])->name('show');
    Route::patch('{kasus}/consent/{kasusConsent}', [KasusConsentController::class, 'approve'])->name('consent.approve');
    Route::post('{kasus}/sesi', [KasusSesiController::class, 'store'])->name('sesi.store');
    Route::patch('{kasus}/sesi/{kasusSesi}', [KasusSesiController::class, 'updateStatus'])->name('sesi.update-status');
    Route::post('{kasus}/tugas', [KasusTugasController::class, 'store'])->name('tugas.store');
    Route::post('{kasus}/tugas/preview', [KasusTugasBatchPreviewController::class, 'preview'])->name('tugas.preview');
    Route::post('{kasus}/tugas/{kasusTugas}/submission', [KasusTugasSubmissionController::class, 'store'])->name('tugas.submission.store');
    Route::patch('{kasus}/tugas/{kasusTugas}/submission/{kasusTugasSubmission}/review', [KasusTugasSubmissionController::class, 'review'])->name('tugas.submission.review');
    Route::get('{kasus}/tugas/{kasusTugas}/submission/{kasusTugasSubmission}/lampiran', [KasusTugasSubmissionController::class, 'download'])->name('tugas.submission.lampiran');
    Route::patch('{kasus}/tugas/{kasusTugas}/selesai', [KasusTugasController::class, 'markSelesai'])->name('tugas.selesai');
    Route::post('{kasus}/evaluasi', [KasusEvaluasiController::class, 'store'])->name('evaluasi.store');
});
```

- [ ] **Step 2: Di `routes/admin.php`, HAPUS seluruh blok berikut** (komentar + `Route::bind` + grup `kasus.*` lengkap — ini berada SETELAH penutup `});` grup admin utama, BUKAN di dalamnya):

```php
// Orang tua accounts have no lembaga_id of their own, so implicit route-model binding's
// default TenantScope-applied lookup would 404 on {kasus} before the controller's own
// isSubmitter/isKontakUtama/kasus.triase authorization logic ever runs. Bind explicitly,
// bypassing the tenant scope; real authorization stays inside each controller action.
Route::bind('kasus', function ($value) {
    return \App\Models\Kasus::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
        ->withTrashed()
        ->findOrFail($value);
});

Route::middleware(['auth', 'verified'])->prefix('kasus')->name('kasus.')->group(function () {
    Route::get('/', [KasusController::class, 'index'])->name('index');
    Route::get('ajukan', [KasusController::class, 'create'])->name('create');
    Route::post('/', [KasusController::class, 'store'])->name('store');
    Route::get('{kasus}', [KasusController::class, 'show'])->name('show');
    Route::patch('{kasus}/consent/{kasusConsent}', [KasusConsentController::class, 'approve'])->name('consent.approve');
    Route::post('{kasus}/sesi', [KasusSesiController::class, 'store'])->name('sesi.store');
    Route::patch('{kasus}/sesi/{kasusSesi}', [KasusSesiController::class, 'updateStatus'])->name('sesi.update-status');
    Route::post('{kasus}/tugas', [KasusTugasController::class, 'store'])->name('tugas.store');
    Route::post('{kasus}/tugas/preview', [KasusTugasBatchPreviewController::class, 'preview'])->name('tugas.preview');
    Route::post('{kasus}/tugas/{kasusTugas}/submission', [KasusTugasSubmissionController::class, 'store'])->name('tugas.submission.store');
    Route::patch('{kasus}/tugas/{kasusTugas}/submission/{kasusTugasSubmission}/review', [KasusTugasSubmissionController::class, 'review'])->name('tugas.submission.review');
    Route::get('{kasus}/tugas/{kasusTugas}/submission/{kasusTugasSubmission}/lampiran', [KasusTugasSubmissionController::class, 'download'])->name('tugas.submission.lampiran');
    Route::patch('{kasus}/tugas/{kasusTugas}/selesai', [KasusTugasController::class, 'markSelesai'])->name('tugas.selesai');
    Route::post('{kasus}/evaluasi', [KasusEvaluasiController::class, 'store'])->name('evaluasi.store');
});
```

- [ ] **Step 3: Hapus `use` yang sudah tidak dipakai (`KasusConsentController`, `KasusController` (unaliased), `KasusEvaluasiController`, `KasusSesiController`, `KasusTugasBatchPreviewController`, `KasusTugasController`, `KasusTugasSubmissionController`) dari `routes/admin.php`** — HATI-HATI jangan hapus `use App\Http\Controllers\Admin\KasusController as AdminKasusController;` (beda class, dipakai `routes/admin/kasus-admin.php` dari Task 10, sudah dihapus dari admin.php di task itu — cek dulu dengan grep, kemungkinan baris ini sudah tidak ada lagi di admin.php setelah Task 10).

- [ ] **Step 4: Tambahkan require di `routes/web.php`**

Cari di `routes/web.php`:
```php
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
```

Ganti dengan:
```php
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/kasus.php';
```

- [ ] **Step 5: Snapshot before/after `php artisan route:list` (FULL, bukan cuma path admin — karena kasus.* pindah keluar dari file admin.php tapi TETAP terdaftar via web.php), diff harus kosong**

- [ ] **Step 6: Commit**

```bash
git add routes/admin.php routes/kasus.php routes/web.php
git commit -m "refactor(routes): pisahkan grup kasus.* ke routes/kasus.php (bukan bagian admin)"
```

---

### Task 14: Pisahkan `routes/guru.php` (Portal Guru)

**Files:**
- Create: `routes/guru.php`
- Modify: `routes/admin.php` (hapus blok yang dipindah)
- Modify: `routes/web.php` (tambah require)

**Catatan:** `routes/guru.php` ini portal guru (jurnal-kbm, asesmen, komponen-penilaian versi guru, catatan rapor guru) — beda dari `routes/admin/guru-data.php` (Task 3, CRUD data kepegawaian guru oleh admin).

- [ ] **Step 1: Buat `routes/guru.php`**

```php
<?php

use App\Http\Controllers\Guru\AsesmenController;
use App\Http\Controllers\Guru\Akademik\JurnalKbmController;
use App\Http\Controllers\Guru\Akademik\RekapKehadiranController;
use App\Http\Controllers\Guru\RaporController as GuruRaporController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('guru')->name('guru.')->group(function () {
    Route::get('jurnal-kbm', [JurnalKbmController::class, 'index'])->name('jurnal-kbm.index');
    Route::get('jurnal-kbm/{sesi}', [JurnalKbmController::class, 'show'])->name('jurnal-kbm.show');
    Route::put('jurnal-kbm/{sesi}', [JurnalKbmController::class, 'update'])->name('jurnal-kbm.update');
    Route::get('jurnal-kbm-rekap', [RekapKehadiranController::class, 'index'])->name('jurnal-kbm.rekap');

    Route::get('asesmen', [AsesmenController::class, 'index'])->name('asesmen.index');
    Route::get('asesmen/create', [AsesmenController::class, 'create'])->name('asesmen.create');
    Route::post('asesmen', [AsesmenController::class, 'store'])->name('asesmen.store');
    Route::get('asesmen/{asesmen}', [AsesmenController::class, 'show'])->name('asesmen.show');
    Route::put('asesmen/{asesmen}/nilai', [AsesmenController::class, 'updateNilai'])->name('asesmen.update-nilai');

    Route::get('komponen-penilaian', [App\Http\Controllers\Guru\KomponenPenilaianController::class, 'index'])->name('komponen-penilaian.index');
    Route::get('komponen-penilaian/create', [App\Http\Controllers\Guru\KomponenPenilaianController::class, 'create'])->name('komponen-penilaian.create');
    Route::post('komponen-penilaian', [App\Http\Controllers\Guru\KomponenPenilaianController::class, 'store'])->name('komponen-penilaian.store');
    Route::get('komponen-penilaian/{komponenPenilaian}/edit', [App\Http\Controllers\Guru\KomponenPenilaianController::class, 'edit'])->name('komponen-penilaian.edit');
    Route::put('komponen-penilaian/{komponenPenilaian}', [App\Http\Controllers\Guru\KomponenPenilaianController::class, 'update'])->name('komponen-penilaian.update');
    Route::delete('komponen-penilaian/{komponenPenilaian}', [App\Http\Controllers\Guru\KomponenPenilaianController::class, 'destroy'])->name('komponen-penilaian.destroy');

    Route::get('rapor', [GuruRaporController::class, 'index'])->name('rapor.catatan.index');
    Route::get('rapor/siswa/{siswa}', [GuruRaporController::class, 'edit'])->name('rapor.catatan.edit');
    Route::put('rapor/siswa/{siswa}', [GuruRaporController::class, 'update'])->name('rapor.catatan.update');
    Route::post('rapor/generate-narasi/{siswa}', [GuruRaporController::class, 'generateNarasi'])->name('rapor.catatan.generate-narasi');
    Route::post('rapor/ajukan', [GuruRaporController::class, 'ajukan'])->name('rapor.pengajuan.submit');
    Route::get('rapor/cetak/{siswa}', [GuruRaporController::class, 'cetak'])->name('rapor.cetak');
});
```

- [ ] **Step 2: Di `routes/admin.php`, HAPUS seluruh blok grup `guru.*` ini** (berada SETELAH grup `kasus.*` yang sudah dipindah di Task 13 — kalau Task 13 sudah jalan, blok ini langsung berada di akhir file setelah penutup grup admin):

```php
Route::middleware(['auth', 'verified'])->prefix('guru')->name('guru.')->group(function () {
    Route::get('jurnal-kbm', [JurnalKbmController::class, 'index'])->name('jurnal-kbm.index');
    Route::get('jurnal-kbm/{sesi}', [JurnalKbmController::class, 'show'])->name('jurnal-kbm.show');
    Route::put('jurnal-kbm/{sesi}', [JurnalKbmController::class, 'update'])->name('jurnal-kbm.update');
    Route::get('jurnal-kbm-rekap', [RekapKehadiranController::class, 'index'])->name('jurnal-kbm.rekap');

    Route::get('asesmen', [AsesmenController::class, 'index'])->name('asesmen.index');
    Route::get('asesmen/create', [AsesmenController::class, 'create'])->name('asesmen.create');
    Route::post('asesmen', [AsesmenController::class, 'store'])->name('asesmen.store');
    Route::get('asesmen/{asesmen}', [AsesmenController::class, 'show'])->name('asesmen.show');
    Route::put('asesmen/{asesmen}/nilai', [AsesmenController::class, 'updateNilai'])->name('asesmen.update-nilai');

    Route::get('komponen-penilaian', [App\Http\Controllers\Guru\KomponenPenilaianController::class, 'index'])->name('komponen-penilaian.index');
    Route::get('komponen-penilaian/create', [App\Http\Controllers\Guru\KomponenPenilaianController::class, 'create'])->name('komponen-penilaian.create');
    Route::post('komponen-penilaian', [App\Http\Controllers\Guru\KomponenPenilaianController::class, 'store'])->name('komponen-penilaian.store');
    Route::get('komponen-penilaian/{komponenPenilaian}/edit', [App\Http\Controllers\Guru\KomponenPenilaianController::class, 'edit'])->name('komponen-penilaian.edit');
    Route::put('komponen-penilaian/{komponenPenilaian}', [App\Http\Controllers\Guru\KomponenPenilaianController::class, 'update'])->name('komponen-penilaian.update');
    Route::delete('komponen-penilaian/{komponenPenilaian}', [App\Http\Controllers\Guru\KomponenPenilaianController::class, 'destroy'])->name('komponen-penilaian.destroy');

    Route::get('rapor', [GuruRaporController::class, 'index'])->name('rapor.catatan.index');
    Route::get('rapor/siswa/{siswa}', [GuruRaporController::class, 'edit'])->name('rapor.catatan.edit');
    Route::put('rapor/siswa/{siswa}', [GuruRaporController::class, 'update'])->name('rapor.catatan.update');
    Route::post('rapor/generate-narasi/{siswa}', [GuruRaporController::class, 'generateNarasi'])->name('rapor.catatan.generate-narasi');
    Route::post('rapor/ajukan', [GuruRaporController::class, 'ajukan'])->name('rapor.pengajuan.submit');
    Route::get('rapor/cetak/{siswa}', [GuruRaporController::class, 'cetak'])->name('rapor.cetak');
});
```

Setelah task ini, `routes/admin.php` seharusnya HANYA berisi: tag `<?php`, `use App\Http\Controllers\...` (kalau masih ada sisa yang genuinely dipakai — cek), `use Illuminate\Support\Facades\Route;`, dan satu `Route::group` berisi 13 `require`. Bandingkan dengan bentuk target di spec §3.2.

- [ ] **Step 3: Hapus `use` yang sudah tidak dipakai (`AsesmenController`, `JurnalKbmController`, `RekapKehadiranController`, `GuruRaporController`) dari `routes/admin.php`**

- [ ] **Step 4: Tambahkan require di `routes/web.php`**

Cari:
```php
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/kasus.php';
```

Ganti dengan:
```php
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/kasus.php';
require __DIR__.'/guru.php';
```

- [ ] **Step 5: Snapshot before/after `php artisan route:list` FULL, diff harus kosong**

- [ ] **Step 6: Commit**

```bash
git add routes/admin.php routes/guru.php routes/web.php
git commit -m "refactor(routes): pisahkan portal guru ke routes/guru.php (bukan bagian admin)"
```

---

### Task 15: Verifikasi Akhir, Full Suite, Update Master Plan, Handoff Log

**Files:**
- Modify: `.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md` (checklist FASE 5.1)
- Create: `.agents/logs/2026-08-20-fase-5-1-restrukturisasi-rute-modular.md`

- [ ] **Step 1: Verifikasi struktur akhir `routes/admin.php` sesuai spec §3.2**

Run: `cat routes/admin.php` — pastikan isinya persis pola loader (middleware/prefix/name wrapper + 13 baris `require`), tidak ada `Route::` lain tersisa di dalam grup, tidak ada `use` yang sudah tak terpakai (grep tiap class di `use` terhadap sisa isi file).

- [ ] **Step 2: Snapshot `route:list` FULL sekali lagi terhadap baseline sebelum Task 1 dimulai (kalau implementer menyimpan snapshot awal itu; kalau tidak, checkout commit sebelum Task 1 di worktree terpisah untuk snapshot pembanding), pastikan identik**

- [ ] **Step 3: Minta persetujuan user untuk menjalankan full suite** (ikuti pola RBAC v2: tanyakan dulu, jangan otomatis jalan)

- [ ] **Step 4: Jalankan full suite setelah disetujui**

Run: `php artisan test`
Expected: semua test tetap PASS, jumlah sama dengan baseline sebelum plan ini (1861 passed, 0 failed) — TIDAK BOLEH ada test yang gagal, karena tidak ada logic yang berubah.

- [ ] **Step 5: Update checklist FASE 5.1 di master plan**

Di `.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md`, ganti baris:
```markdown
- [ ] **5.1. Pendaftaran Rute Modular:**
  - [ ] Susun berkas rute modular `routes/lembaga.php`, `routes/guru.php`, `routes/siswa.php`, `routes/orang-tua.php`, dan `routes/yayasan.php`.
  - [ ] Pertahankan route alias untuk backward compatibility.
```

Menjadi:
```markdown
- [x] **5.1. Pendaftaran Rute Modular:** (2026-08-20, lihat `.agents/specs/2026-08-20-fase-5-1-restrukturisasi-rute-modular-design.md` dan `.agents/plans/2026-08-20-fase-5-1-restrukturisasi-rute-modular.md`)
  - [x] Split per MODUL domain (bukan per scope aktor seperti draft awal) — `routes/admin.php` jadi loader 13 file di `routes/admin/`, plus `routes/kasus.php` dan `routes/guru.php` dipisah keluar dari admin.php.
  - [x] Tidak perlu alias backward-compat — zero rename nama route/URI, diverifikasi lewat diff `php artisan route:list` di tiap task.
```

- [ ] **Step 6: Tulis handoff log ke `.agents/logs/2026-08-20-fase-5-1-restrukturisasi-rute-modular.md`**

Isi minimal: ringkasan tugas, daftar 14 file baru yang dibuat, hasil verifikasi route:list (identik) per task, hasil full suite akhir, dan konfirmasi tidak ada perubahan nama route/URI/middleware.

- [ ] **Step 7: Commit**

```bash
git add .agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md .agents/logs/2026-08-20-fase-5-1-restrukturisasi-rute-modular.md
git commit -m "docs(routes): tutup FASE 5.1 - update checklist master plan & handoff log"
```
