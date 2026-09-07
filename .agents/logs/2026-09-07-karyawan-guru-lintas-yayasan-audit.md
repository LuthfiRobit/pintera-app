# Handoff Log: Perbaikan Lintas-Yayasan Modul Karyawan & Guru

> **Tanggal**: 7 September 2026  
> **Branch**: `rbac-v2`  
> **Spec**: `.agents/specs/2026-09-07-karyawan-guru-lintas-yayasan-audit.md`  
> **Plan**: `.agents/plans/2026-09-07-karyawan-guru-lintas-yayasan-audit.md`  
> **Base commit sebelum kickoff**: `662a8321`  
> **Commit range**: `50eedb01..5db68559` (6 commit)  
> **Status**: Selesai & Terverifikasi (Full Suite: 2.973 test lulus, 0 regresi baru)

---

## 1. Apa yang Dikerjakan

Menyelesaikan audit menyeluruh pada modul Karyawan & Guru yang menemukan 6 bug (termasuk 2 kebocoran data riil lintas yayasan, 4 titik IDOR validasi `Rule::exists`, penanganan karyawan pool pada sistem presensi & alpa otomatis, error 500 race condition Guru, dan fitur pencarian NIK di index Karyawan).

### Rincian Commit:

1. **Commit `50eedb01` — Task 1: Kelompok A — 4 Titik Validasi `Rule::exists` Scoped ke Yayasan (IDOR)**
   - `app/Http/Controllers/Admin/KaryawanController.php`:
     - `store()`: `Rule::exists('jenis_karyawan_master', 'id')->where('yayasan_id', $yayasanId)` (menggunakan yayasan aktor login).
     - `update()`: `Rule::exists('jenis_karyawan_master', 'id')->where('yayasan_id', $karyawan->yayasan_id)` (menggunakan yayasan pemilik row karyawan).
   - `app/Http/Controllers/Admin/AttendancePolicyController.php`:
     - `validatePayload()`: Menghitung `$yayasanId` dari raw input request (`lembaga_id` atau fallback aktor login) sebelum validasi dijalankan, lalu menerapkan `Rule::exists('jenis_karyawan_master', 'id')->where('yayasan_id', $yayasanId)`.
   - `app/Http/Controllers/Admin/GuruRelationalProfileController.php`:
     - `updateJabatanTambahan()`: Mengambil yayasan guru pemilik row via `$guru->lembaga->yayasan_id`, lalu menerapkan `Rule::exists('jabatan_tambahan_master', 'id')->where('yayasan_id', $yayasanId)`.
   - Pengujian: Menambahkan 5 test isolasi tenant IDOR di `KaryawanCrudTest.php`, `AttendancePolicyControllerTest.php`, dan `GuruRelationalProfileTest.php`.

2. **Commit `eec40563` — Task 2: Kelompok B — Tutup Kebocoran Lintas-Yayasan pada 2 Resolver SDM**
   - `app/Domains/Sdm/Services/AttendancePolicyResolver.php`:
     - `resolvePolicy()`: Menambahkan filter per-yayasan eksplisit pada Tier 2 (Lembaga Default: `where('yayasan_id', $yayasanId)`) dan Tier 3 (Yayasan Default: `where('yayasan_id', $yayasanId)`).
     - `resolveLiburPool()`: Menambahkan helper khusus untuk karyawan pool yang mengecek libur nasional/eksplisit via `KalenderKerjaSdm` scoped ke yayasan pool, dengan fallback hari kerja (default hari kerja, tanpa kolom mingguan yayasan).
   - `app/Domains/Sdm/Services/KuotaCutiResolver.php`:
     - `resolveConfig()`: Menambahkan filter `where('yayasan_id', $yayasanId)` pada Tier 1 (Lembaga + Jenis Karyawan), Tier 2 (Lembaga Default), dan Tier 3 (Yayasan Default).
   - Pengujian: Menambahkan 2 test di `AttendancePolicyTenantIsolationTest.php` dan 1 test di `KuotaCutiResolverTest.php`. Verifikasi grep memastikan tidak ada resolver SDM lain yang memiliki pola un-scoped serupa.

3. **Commit `2599a731` — Task 3: Kelompok C — Sertakan Karyawan Pool di Alpa Otomatis & Dropdown Pemilih Karyawan**
   - Migrasi `2026_09_07_132218_make_lembaga_id_nullable_on_attendance_events_table.php`:
     - Mengubah kolom `lembaga_id` menjadi `nullable()` pada tabel `attendance_events` DAN `attendance_records` (Foreign Key `ON DELETE CASCADE` tetap dipertahankan).
   - `app/Console/Commands/TandaiAlpaOtomatisSdm.php`:
     - Menambahkan pass kedua per-yayasan setelah loop per-lembaga selesai untuk memproses karyawan pool (`lembaga_id IS NULL`, `yayasan_id = $yayasan->id`).
     - Menggunakan `resolveLiburPool()` dan menandai alpa menggunakan `AttendanceRecord` tanpa lembaga.
   - `app/Http/Controllers/Admin/AttendanceConfigurationController.php` & `AttendanceController.php`:
     - Query `$karyawanList` dropdown diperbarui menjadi pool-aware (`where('karyawan.lembaga_id', $lembagaId)->orWhere(fn ($q) => $q->whereNull('karyawan.lembaga_id')->where('karyawan.yayasan_id', $yayasanId))`).
     - Mengkualifikasi nama kolom dengan prefix tabel `karyawan.` untuk mencegah ambiguitas akibat join dengan tabel `persons`.
   - Pengujian: Menambahkan 2 test di `TandaiAlpaOtomatisSdmTest.php`, 1 test di `AttendanceConfigurationControllerTest.php`, dan 1 test di `AttendanceControllerTest.php`.

4. **Commit `58be527e` — Task 4: Kelompok D — Tangkap `PersonAlreadyExistsException` di `GuruController::store()`**
   - `app/Http/Controllers/Admin/GuruController.php`:
     - Membungkus pemanggilan `DB::transaction` di `store()` dengan blok `try / catch (PersonAlreadyExistsException $exception)` untuk mengembalikan error validasi ramah (`withErrors(['nik' => '...'])`) alih-alih HTTP 500 mentah saat terjadi race condition konkuren.
   - Pengujian: Menambahkan test penanganan error duplikasi NIK di `GuruCrudTest.php`.

5. **Commit `2c863e89` — Task 5: Kelompok E — Index Karyawan: Tambah Pencarian NIK**
   - `resources/views/admin/karyawan/index.blade.php`:
     - Menambahkan `'nik' => $k->person?->nik` pada array payload JSON `$spaItems`.
     - Memperbarui getter Alpine.js `filteredItems` dengan kondisi `|| (i.nik && i.nik.toLowerCase().includes(q))`.
   - Pengujian: Menambahkan test assert JSON payload NIK di `KaryawanCrudTest.php` dan verifikasi visual end-to-end langsung via Playwright browser subagent.

6. **Commit `5db68559` — Penyelarasan Fixture Test Pre-Existing**
   - `tests/Feature/KaryawanControllerTest.php`:
     - Menyelaraskan pembuatan fixture `$jenisKaryawan = JenisKaryawanMaster::factory()->create(['yayasan_id' => $yayasan->id]);` agar sesuai dengan validasi tenant scope baru pada update Karyawan.

---

## 2. Keputusan Penting yang Diambil

1. **`attendance_records.lembaga_id` Dijadikan Nullable Bersama `attendance_events`**:
   - Plan awal hanya menyebutkan `attendance_events.lembaga_id` diubah nullable. Namun saat eksekusi command Alpa otomatis, `AttendanceRecordAggregator::sync()` memetakan `$event->lembaga_id` ke record baru di `attendance_records`. Jika `attendance_records.lembaga_id` tetap `NOT NULL`, database melempar SQL Error 1048.
   - Keputusan: Migrasi `2026_09_07_132218_make_lembaga_id_nullable_on_attendance_events_table.php` diterapkan secara atomik pada KEDUA tabel (`attendance_events` dan `attendance_records`).
2. **Kualifikasi Tabel Eksplisit pada Query Dropdown Karyawan**:
   - Scope `orderByNama()` pada model `Karyawan` melakukan join ke tabel `persons`. Karena kedua tabel memiliki kolom `yayasan_id` dan `lembaga_id`, pemanggilan `where('yayasan_id', ...)` atau `whereNull('lembaga_id')` memicu MySQL Error 1052 (*Column 'yayasan_id' in where clause is ambiguous*).
   - Keputusan: Semua klausa filter tenant di `AttendanceConfigurationController.php` dan `AttendanceController.php` wajib ditulis dengan kualifikasi tabel lengkap (`karyawan.yayasan_id` dan `karyawan.lembaga_id`).
3. **Default Karyawan Pool Dianggap Hari Kerja**:
   - Sesuai konfirmasi bisnis user, karyawan pool tidak mengacu pada hari libur mingguan lembaga manapun dan model `Yayasan` sengaja tidak memiliki kolom libur mingguan.
   - Karyawan pool selalu dianggap hari kerja kecuali terdapat entri libur eksplisit pada `KalenderKerjaSdm` (`lembaga_id IS NULL`).
4. **Penyelarasan Fixture Test Existing `KaryawanControllerTest`**:
   - Sebelum perbaikan Task 1, `KaryawanController::update` menerima `jenis_karyawan_id` milik yayasan mana pun (celah IDOR). Tes lama `tests/Feature/KaryawanControllerTest.php` membuat `$jenisKaryawan` tanpa parameter `yayasan_id` (sehingga terbuat di yayasan acak baru).
   - Setelah IDOR ditutup, tes ini gagal secara valid dengan pesan validasi *"The selected jenis karyawan id is invalid."* Fixture tes diselaraskan agar berada di yayasan yang sama dengan karyawan.
5. **Defense-in-Depth pada GuruController Race Condition**:
   - Draf pengujian mengakui secara jujur bahwa test HTTP sekuensial sulit mereplikasi race condition konkuren murni. Penanganan `PersonAlreadyExistsException` tetap diimplementasikan sebagai lapisan pertahanan mendalam (*defense-in-depth*) melengkapi pre-check `validateProfil()`.

---

## 3. Hal yang Masih Perlu Direview Manusia / Claude

1. **Hasil Full Test Suite**:
   - Total test yang dieksekusi: **2.977 tests** (8.077 assertions).
   - **2.973 test PASSED**.
   - **4 test FAILED** adalah kegagalan *pre-existing* yang sudah dikenal dan tidak terkait dengan modul SDM:
     - `Tests\Unit\M3DemoDataSeederTest > it seeds a spread of pendaftaran states across K-9 institutions...`
     - `Tests\Unit\M3DemoDataSeederTest > it is idempotent when the full DatabaseSeeder is run twice`
     - `Tests\Unit\PresensiSeederTest > it seeds student attendance records for the SD institution...`
     - `Tests\Unit\SesiPembelajaranSeederTest > it seeds learning sessions across all K-9 institutions`
   - **0 regresi baru** pada seluruh suite aplikasi.
2. **Item di Luar Scope (Dikonfirmasi User)**:
   - Kolom `hari_libur_mingguan_sdm` pada model/tabel `yayasan` sengaja tidak dibuat.
   - Perilaku migrasi `down()` yang lossy jika sudah ada baris `lembaga_id IS NULL` adalah perilaku yang diharapkan (*by design*).
3. **Status Git**:
   - Branch aktif: `rbac-v2`.
   - **TIDAK di-merge ke `main`** dan **TIDAK di-push** sesuai instruksi eksplisit kickoff.
