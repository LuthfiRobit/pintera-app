# Handoff Log: Fondasi Kehadiran SDM (Admin Manual + QR)

- **Spec**: `.agents/specs/2026-08-22-sdm-01-fondasi-kehadiran-admin-qr.md`
- **Plan**: `.agents/plans/2026-08-22-sdm-01-fondasi-kehadiran-admin-qr.md`
- **Tanggal**: 2026-08-22
- **Branch**: `sdm-v1`
- **Hasil Verifikasi**: 1933 tests passed (5909 assertions), 0 failures.

---

## 1. Apa yang dikerjakan

Mengimplementasikan fondasi modul **Kehadiran SDM (Sub-project 1)** secara menyeluruh dari Task 1 hingga Task 10 sesuai spesifikasi dan rencana implementasi:

1. **Migrations & Enums (Task 1)**:
   - 5 migration baru: `attendance_method_configurations`, `attendance_points`, `attendance_events`, `attendance_records`, `employee_qr_codes`.
   - 2 Enum: `App\Domains\Sdm\Enums\AttendanceMethod` (`manual`, `qr`, `gps_selfie`, `fingerprint`, `rfid_machine`) dan `App\Domains\Sdm\Enums\AttendanceStatus` (`hadir`, `izin`, `sakit`, `alpa`, `terlambat`, `pulang_cepat`, `dinas_luar`, `cuti`, `libur`).

2. **Domain Models & Polymorphic Relations (Task 2)**:
   - 5 domain model di `app/Domains/Sdm/Models/` dengan trait `BelongsToTenant` dan helper methods: `AttendanceMethodConfiguration`, `AttendancePoint`, `AttendanceEvent` (immutable, `UPDATED_AT = null`), `AttendanceRecord`, `EmployeeQrCode`.
   - Relasi polymorphic di `App\Models\Guru` dan `App\Models\Karyawan` (`attendanceEvents()`, `attendanceRecords()`, `employeeQrCode()`).

3. **RBAC Seeding (Task 3)**:
   - 4 permission granular baru: `kehadiran-sdm.view`, `kehadiran-sdm.catat`, `kehadiran-sdm.kelola-konfigurasi`, `kehadiran-sdm.lihat-qr-sendiri`.
   - Role baru `admin_sdm` (scope level: `lembaga`) dengan 4 permission SDM.
   - Assignment permission `kehadiran-sdm.lihat-qr-sendiri` ke role `guru`, `karyawan_pool`, dan `karyawan_lembaga`.

4. **DTO, Aggregator, & Manual Action (Task 4)**:
   - DTO `RecordManualAttendanceData`.
   - Service `AttendanceRecordAggregator` untuk agregasi `attendance_events` harian menjadi baris tunggal `attendance_records` dengan penentuan status berhierarki (Izin/Sakit/Alpa > Hadir).
   - Action `RecordManualAttendanceAction` dengan eksekusi atomik database transaction.

5. **QR Generation & Configuration Actions (Task 5)**:
   - `GenerateEmployeeQrTokenAction`: rotasi token QR 32-karakter unik per pegawai (deaktivasi token lama sebelum pembuatan token baru).
   - `SetAttendanceMethodConfigurationAction`: manajemen enable/disable metode absensi tingkat lembaga atau default yayasan.

6. **QR Scan Action & Tenant Isolation (Task 6)**:
   - DTO `ScanQrAttendanceData`.
   - Exception `InvalidQrTokenException` (422) dan `QrTokenLembagaMismatchException` (422).
   - `ScanQrAttendanceAction`: memvalidasi token aktif dan lembaga pegawai vs lembaga scanner sebelum mencatat event & memperbarui aggregate record.

7. **Controller Konfigurasi & Titik Absen (Task 7)**:
   - `AttendanceConfigurationController` (`index`, `updateMetode`, `storeTitik`, `updateTitik`, `destroyTitik`).
   - View `resources/views/admin/kehadiran-sdm/konfigurasi.blade.php`.
   - Route `admin/kehadiran-sdm/konfigurasi*`.

8. **Controller Absensi Manual & Rekap Harian (Task 8)**:
   - `AttendanceController` (`index`, `create`, `store`).
   - View `resources/views/admin/kehadiran-sdm/index.blade.php` & `create.blade.php`.
   - Alpine component `resources/js/attendance-manual-form.js` terintegrasi dengan TomSelect dan dibundle via Vite.

9. **QR Scan Controller & Self-View QR (Task 9)**:
   - `AttendanceQrScanController` (`index`, `store` JSON endpoint dengan error handling 422).
   - `EmployeeQrCodeController` (`show`, `generate` untuk akses pegawai melihat QR sendiri).
   - Route `sdm/qr-saya*` dan view `resources/views/sdm/qr-saya.blade.php` & `resources/views/admin/kehadiran-sdm/scan.blade.php`.

10. **Verifikasi Akhir & Full Suite Testing (Task 10)**:
    - Grep verification: 0 hardcoded role checks di controller/action (`hasRole('admin_sdm')` = 0).
    - 29/29 scoped SDM test lulus.
    - Full test suite: 1933/1933 test lulus (5909 assertions), 0 failures.

---

## 2. Keputusan penting yang diambil

1. **Resolusi `yayasan_id` untuk Lembaga-Scoped Users**:
   - Model `User` dengan `scope_level: lembaga` (seperti `admin_sdm`) memiliki `lembaga_id` terisi, namun kolom `$user->yayasan_id` bernilai `null` pada skema database.
   - Pada `AttendanceConfigurationController`, ditambahkan method `resolveYayasanId` yang melakukan fallback ke `Lembaga::find($lembagaId)?->yayasan_id` agar yayasan-level configuration lookup dan storage berfungsi mulus.
2. **Exception Handling pada `AttendanceQrScanController::store`**:
   - `ScanQrAttendanceAction` melempar `InvalidQrTokenException` atau `QrTokenLembagaMismatchException`. Controller menangkap kedua domain exception ini dan merespons dengan format JSON 422 (`{ "message": "..." }`) sehingga interface scanner (AJAX/Fetch) dapat menampilkan pesan error user-friendly tanpa unhandled 500 error.
3. **Pembaruan Seeder Unit Test Expectations**:
   - Penambahan 4 permission dan 1 role baru (`admin_sdm`) secara sah menambah total permission dari 134 menjadi 138 dan total role dari 12 menjadi 13.
   - File test seeder (`RolePermissionSeederTest.php`, `PermissionSeederTest.php`, `RoleSeederTest.php`) disesuaikan agar secara eksplisit memverifikasi ketersediaan permission & role SDM ini.

---

## 3. Hal yang masih perlu direview manusia/Claude

1. **Integrasi Menu Sidebar / Navigation**:
   - Rute admin `admin.kehadiran-sdm.index`, `admin.kehadiran-sdm.create`, `admin.kehadiran-sdm.scan.index`, `admin.kehadiran-sdm.konfigurasi.index` dan rute pegawai `sdm.qr-saya` sudah aktif dan terlindungi izin Spatie.
   - Claude/Developer dapat menambahkan entri link navigasi sidebar pada komponen layout (`navigation.blade.php` / `sidebar.blade.php`) bila ingin memunculkan menu di UI navigasi utama.
2. **Git State**:
   - Branch: `sdm-v1`
   - Semua perubahan telah di-commit secara modular dan teratur per task.
   - Status: Siap untuk merge / review.
