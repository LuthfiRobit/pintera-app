# Handoff Log: Jenis Karyawan & Jabatan Tambahan Master Per-Yayasan

> **Tanggal**: 7 September 2026  
> **Branch**: `rbac-v2`  
> **Spec**: `.agents/specs/2026-09-07-jenis-karyawan-jabatan-tambahan-per-yayasan.md`  
> **Plan**: `.agents/plans/2026-09-07-jenis-karyawan-jabatan-tambahan-per-yayasan.md`  
> **Base commit**: `554f0b02`  
> **Status**: Selesai & Terverifikasi (Full Suite: 2.958 test lulus, 0 regresi)

---

## 1. Apa yang Dikerjakan

Menyelesaikan item backlog 🟡 dari audit scope yayasan/lembaga: mengubah tabel `jenis_karyawan_master` dan `jabatan_tambahan_master` dari katalog global lintas seluruh sistem menjadi data per-yayasan penuh (`yayasan_id` NOT NULL), tanpa baris nasional/global bersama.

### Rincian Commit:
1. **Commit `e9c79d8b` — Task 1: `JenisKaryawanMaster` Per-Yayasan**
   - Migrasi `2026_09_07_095800_add_yayasan_id_to_jenis_karyawan_master_table.php`: penambahan kolom `yayasan_id` (NOT NULL, FK ke `yayasan.id` `ON DELETE CASCADE`), index unik komposit `UNIQUE(yayasan_id, nama)`, dan backfill defensif berbasis pemakai riil di tabel `karyawan` (termasuk percabangan clone + repoint jika >1 yayasan memakai baris yang sama).
   - Model `app/Domains/Sdm/Models/JenisKaryawanMaster.php`: penambahan `YayasanScope` via `booted()` method (pola sama dengan model `Person`) dan `$fillable = ['yayasan_id', 'nama', 'is_konselor']`.
   - Factory `database/factories/JenisKaryawanMasterFactory.php`: default `'yayasan_id' => Yayasan::factory()`.
   - Action `CreateJenisKaryawanAction`: penambahan parameter `$yayasanId` (dihitung di Controller, bukan dari input DTO/payload form).
   - Action `DeleteJenisKaryawanAction`: guard delete dihitung ulang per-yayasan pemilik baris (`where('yayasan_id', $jenisKaryawanMaster->yayasan_id)`), bukan per-aktor login dan bukan lintas yayasan.
   - Controller `app/Http/Controllers/Lembaga/Sdm/JenisKaryawanMasterController.php`: penghitungan `$yayasanId` dari aktor login, validasi composite unique scoped `Rule::unique(...)->where('yayasan_id', ...)`.
   - Test `tests/Feature/Admin/JenisKaryawanMasterCrudTest.php`: update fixture existing dan penambahan 4 test isolasi tenant/boundary (12 test lulus).

2. **Commit `15de2d90` — Task 2: `JabatanTambahanMaster` Per-Yayasan**
   - Migrasi `2026_09_07_100302_add_yayasan_id_to_jabatan_tambahan_master_table.php`: penambahan kolom `yayasan_id` (NOT NULL, FK ke `yayasan.id` `ON DELETE CASCADE`), composite unique `UNIQUE(yayasan_id, nama)`, dan backfill defensif lewat join pivot `guru_jabatan_tambahan` $\rightarrow$ `guru.lembaga_id` $\rightarrow$ `lembaga.yayasan_id` (karena `Guru` tidak memiliki `yayasan_id` langsung).
   - Model `app/Domains/Sdm/Models/JabatanTambahanMaster.php`: penambahan `HasFactory`, `YayasanScope` via `booted()`, dan `'yayasan_id'` pada `$fillable`.
   - Factory baru `database/factories/JabatanTambahanMasterFactory.php` dibuat dari nol.
   - Action `CreateJabatanTambahanAction`: penambahan parameter `$yayasanId`.
   - Action `DeleteJabatanTambahanAction`: guard delete di-scope ke yayasan pemilik baris via pluck ID lembaga yayasan tersebut.
   - Controller `app/Http/Controllers/Lembaga/Sdm/JabatanTambahanMasterController.php`: hitung `$yayasanId` dan validasi composite unique scoped.
   - Test `tests/Feature/Admin/JabatanTambahanMasterCrudTest.php`: rewrite fixture total dari `beforeEach` tanpa yayasan menjadi helper `actingAsJabatanTambahanManager()` dengan hak akses role dan scope yayasan (10 test lulus).

3. **Commit `f98bbca7` — Task 3: Penyesuaian Seeder & Fixture Pendukung**
   - `database/seeders/JenisKaryawanMasterSeeder.php` dan `database/seeders/JabatanTambahanMasterSeeder.php`: disesuaikan agar selalu menyertakan `yayasan_id` (menggunakan `Yayasan::value('id')` atau memanggil `YayasanSeeder`), menghindari error 1364 NOT NULL saat migrate fresh / seeding.
   - `tests/Feature/Admin/GuruRelationalProfileTest.php`: update pembuatan `JabatanTambahanMaster` fixture agar menyertakan `yayasan_id`.

---

## 2. Keputusan Penting yang Diambil

1. **Per-Yayasan Penuh (Tanpa Baris Global/Nasional)**:
   - Sesuai keputusan bisnis user: `yayasan_id` berstatus `NOT NULL` di kedua tabel. Tidak ada baris global/nasional dengan `yayasan_id = NULL`. Setiap yayasan mengelola daftar masternya sendiri secara mandiri.
2. **Backfill Defensif Lengkap**:
   - Walaupun hasil tinker empiris terhadap database riil `pintera_sdm_app` menunjukkan semua data existing terkonsentrasi di `yayasan_id = 1` (4 baris jenis karyawan, 16 baris jabatan tambahan), logika percabangan untuk menangani baris yang dipakai lebih dari 1 yayasan (clone row + repoint child FK) tetap diimplementasikan secara penuh di migrasi untuk menjamin keamanan di lingkungan manapun.
3. **Perbedaan Model Karyawan vs Guru**:
   - `Karyawan` memiliki kolom `yayasan_id` langsung (pola pool), sehingga query backfill dan guard delete dapat langsung memfilter `where('yayasan_id', ...)`.
   - `Guru` hanya memiliki `lembaga_id`, sehingga query backfill dan guard delete harus melalui `Lembaga::where('yayasan_id', ...)->pluck('id')`.
4. **Perbedaan Cakupan `index()` vs Guard Delete (Sengaja Dibiarkan Berbeda, Bukan Bug)**:
   - Pada `JenisKaryawanMasterController::index()`, `withCount('karyawan')` tidak membypass `TenantScope` bawaan `Karyawan`. Untuk aktor lembaga-scope, angka yang tampil adalah karyawan di lembaganya saat ini. Sedangkan guard delete di `DeleteJenisKaryawanAction` sengaja membypass `TenantScope` untuk mengecek seluruh lembaga dalam yayasan pemilik row agar delete tetap aman.
   - Pada `JabatanTambahanMasterController::index()`, `withCount(['guru' => withoutGlobalScopes()])` sudah ada sebelum fix ini dan sengaja dipertahankan (menampilkan total guru di seluruh lembaga yayasan tersebut).
5. **Relational Integrity MySQL (ON DELETE RESTRICT pada Karyawan)**:
   - Tabel `karyawan.jenis_karyawan_id` memiliki foreign key `ON DELETE RESTRICT` di level basis data MySQL. Oleh karena itu, pengujian delete lintas-yayasan memvalidasi bahwa jika Yayasan B memiliki karyawan yang menggunakan jenis karyawan milik Yayasan B (dengan nama sama), hal tersebut tidak memblokir Yayasan A untuk menghapus jenis karyawan milik Yayasan A.
6. **Query Assertion Lintas-Yayasan Menggunakan `withoutGlobalScopes()`**:
   - Ketika seorang aktor yayasan sedang terotentikasi dalam test runner, pemanggilan `find($id)` pada model yang memakai `YayasanScope` akan memfilter row ke yayasan aktor tersebut. Untuk memverifikasi keberadaan row milik yayasan lain di basis data, assertion test menggunakan `withoutGlobalScopes()->find(...)`.

---

## 3. Hal yang Masih Perlu Direview Manusia / Claude

1. **Hasil Full Test Suite**:
   - Total test yang dieksekusi: **2.962 tests** (8.023 assertions).
   - **2.958 test PASSED**.
   - **4 test FAILED** adalah kegagalan *pre-existing* yang sudah dikenal dan tidak terkait dengan modul SDM:
     - `Tests\Unit\M3DemoDataSeederTest > it seeds a spread of pendaftaran states across K-9 institutions...`
     - `Tests\Unit\M3DemoDataSeederTest > it is idempotent when the full DatabaseSeeder is run twice`
     - `Tests\Unit\PresensiSeederTest > it seeds student attendance records for the SD institution...`
     - `Tests\Unit\SesiPembelajaranSeederTest > it seeds learning sessions across all K-9 institutions`
   - Tidak ada satupun regresi baru yang diperkenalkan oleh perubahan ini.
2. **Item di Luar Scope (Sesuai Arahan User)**:
   - Hook seeder onboarding saat yayasan baru dibuat di masa depan (starter catalog seeder per onboarding) sengaja tidak dikerjakan dalam scope ini, sesuai konfirmasi eksplisit user: *"jangan pikirkan yayasan lain dulu, aman saja"*.
   - Kloning katalog ke data demo yayasan 2, 3, 4 sengaja tidak dikerjakan.
3. **Status Git**:
   - Branch aktif: `rbac-v2`.
   - Perubahan telah di-commit secara rapi (atomic commits).
   - **TIDAK di-merge ke `main`** dan **TIDAK di-push** (sesuai instruksi kickoff: keputusan merge dan push diserahkan kepada user).
