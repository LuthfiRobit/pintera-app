# Handoff Log: Sub-project 3b — Penugasan Shift per Periode

**Tanggal**: 2026-08-22  
**Branch**: `sdm-v1`  
**Git status**: Work in progress on `sdm-v1` (not yet merged to main, not yet pushed).  
**Spec**: [`.agents/specs/2026-08-22-sdm-03b-shift-bergilir.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-22-sdm-03b-shift-bergilir.md)  
**Plan**: [`.agents/plans/2026-08-22-sdm-03b-shift-bergilir.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-22-sdm-03b-shift-bergilir.md)  
**Full Test Suite Result**: 2007 passed (6059 assertions), 0 failed.

---

## Apa yang Dikerjakan

1. **Database Migrations (Task 1)**:
   - `database/migrations/2026_08_22_120000_create_jenis_shift_table.php`: Tabel `jenis_shift` dengan `yayasan_id`, nullable `lembaga_id`, `nama`, `jam_masuk`, `jam_pulang`.
   - `database/migrations/2026_08_22_120100_create_penugasan_shift_table.php`: Tabel `penugasan_shift` dengan non-null `lembaga_id`, polymorphic pegawai (`pegawai_type`, `pegawai_id`), `jenis_shift_id`, `tanggal_mulai`, nullable `tanggal_selesai`, nullable JSON `hari_kerja`.

2. **Models & Domain Logic (Task 2)**:
   - Model `App\Domains\Sdm\Models\JenisShift` & `PenugasanShift` with `BelongsToTenant` trait.
   - Morph relations `penugasanShift(): MorphMany` on `App\Models\Guru` and `App\Models\Karyawan`.
   - DTO `ShiftAssignmentData`, Exception `ShiftAssignmentOverlapException`, and Action `AssignShiftAction` (anti-overlap logic supporting both bounded and open-ended ranges with `excludingId` support).
   - Automated tests: `tests/Feature/Sdm/AssignShiftActionTest.php` (6 tests).

3. **Resolver Decorator Service (Task 3)**:
   - `App\Domains\Sdm\Services\ShiftAwareAttendanceResolver`: Membungkus `AttendancePolicyResolver` melalui constructor DI tanpa memodifikasi `AttendancePolicyResolver` maupun `KalenderKerjaSdmResolver`.
   - Unit tests: `tests/Unit/Services/ShiftAwareAttendanceResolverTest.php` (6 tests).

4. **Swap Dependency di Actions, Services, dan Console (Tasks 4, 5, 6)**:
   - `RecordManualAttendanceAction` & `ScanQrAttendanceAction`: Menggunakan `ShiftAwareAttendanceResolver` untuk validasi hari libur/shift.
   - `AttendanceRecordAggregator`: Menggunakan `ShiftAwareAttendanceResolver` untuk menentukan `jam_masuk` efektif dan toleransi shift.
   - `TandaiAlpaOtomatisSdm`: Menggunakan `ShiftAwareAttendanceResolver` untuk menentukan kewajiban hadir pegawai yang mendapat penugasan shift pada hari libur lembaga.
   - Regression tests + shift coverage tests di `RecordManualAttendanceActionTest`, `AttendanceRecordAggregatorLateDetectionTest`, and `TandaiAlpaOtomatisSdmTest`.

5. **Controller, Routing & Frontend JS (Task 7)**:
   - `AttendanceConfigurationController`: Menambahkan method `storeJenisShift`, `updateJenisShift`, `destroyJenisShift`, `storePenugasanShift`, `updatePenugasanShift`, `destroyPenugasanShift`, serta query shift di `index()`.
   - `routes/admin/kehadiran-sdm.php`: 6 route baru untuk manajemen shift.
   - `resources/js/shift-penugasan-form.js` & `resources/js/app.js`: Alpine data component dengan Tom Select untuk pencarian guru/karyawan.
   - Assets dibangun via `npm.cmd run build`.
   - Automated tests: `tests/Feature/Admin/ShiftControllerTest.php` (5 tests).

6. **View Configuration Tab (Task 8)**:
   - `resources/views/admin/kehadiran-sdm/konfigurasi.blade.php`: Tab 4 "Shift Bergilir" dengan daftar jenis shift dan penugasan shift, serta 2 modal CRUD (Modal Jenis Shift & Modal Penugasan Shift).
   - Automated tests: `tests/Feature/Admin/ShiftViewTest.php` (1 test).

7. **Verifikasi & Safety Checks (Task 9)**:
   - 0 instance `hasRole(` di kode domain SDM (Role-agnostic).
   - `AttendancePolicyResolver.php` dan `KalenderKerjaSdmResolver.php` terbukti 0 diff (unmodified).
   - 0 hardcoded keyword "satpam" di logika bisnis.
   - 2007 tests passing di seluruh codebase.

---

## Keputusan Penting yang Diambil

1. **Decorator Pattern Tanpa Mengubah Fondasi**:
   `ShiftAwareAttendanceResolver` mengimplementasikan decorator pattern di atas `AttendancePolicyResolver` yang mengembalikan jam kerja shift jika ada penugasan aktif, dan meminjam `toleransi_menit` dari `AttendancePolicy` bila tersedia (default 0 menit bila tidak ada Policy). Hal ini menjamin backward-compatibility 100% tanpa mengubah sebaris pun kode di `AttendancePolicyResolver.php` dan `KalenderKerjaSdmResolver.php`.

2. **Mandatory Tenant `lembaga_id` di Penugasan Shift**:
   Berbeda dengan entri konfigurasi lain yang mendukung fallback nasional (`lembaga_id === null`), `penugasan_shift` SELALU memiliki `lembaga_id` terisi (diwariskan dari lembaga pegawai yang ditugaskan).

3. **Anti-Overlap Logic Terpusat di DTO/Action**:
   Validasi tumpang tindih shift dijalankan secara seragam di `AssignShiftAction` baik untuk penugasan bertanggal akhir pasti maupun penugasan terbuka (`tanggal_selesai === null`).

---

## Hal yang Masih Perlu Direview Manusia/Claude

1. **Rotasi Shift Otomatis (Next Feature/Sub-project)**:
   Sesuai scope Sub-project 3b, penugasan shift saat ini dibuat secara manual per rentang periode. Pola rotasi otomatis berulang (mis. rotasi 3-hari bergilir) dapat dikembangkan pada sub-project lanjutan jika diperlukan.
2. **Git State**:
   - Branch: `sdm-v1`
   - Uncommitted: `.agents/logs/2026-08-22-sdm-03b-shift-bergilir.md` (akan segera di-commit)
   - Belum di-push ke remote repository.
