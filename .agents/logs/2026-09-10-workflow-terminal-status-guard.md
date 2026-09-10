# Handoff Log: Guard Status Terminal ProcessApprovalAction

- **Tanggal**: 2026-09-11
- **Branch**: `rbac-v2`
- **Spec**: `.agents/specs/2026-09-10-workflow-terminal-status-guard.md`
- **Plan**: `.agents/plans/2026-09-10-workflow-terminal-status-guard.md`
- **Kickoff**: `.agents/kickoff/2026-09-10-workflow-terminal-status-guard-kickoff.md`

---

## 1. Apa yang Dikerjakan

Memperbaiki celah keamanan & integritas data di engine approval workflow bersama (`ProcessApprovalAction::execute()`) yang sebelumnya memproses ulang `ApprovalRequest` yang statusnya sudah terminal (`Approved`, `Rejected`, `RevisionRequired`, `Cancelled`).

Pemicu bug: double-klik tombol approval atau duplikasi pemanggilan Action menyebabkan:
- Log persetujuan ganda (`ApprovalLog`) di semua 3 domain konsumen (Rapor, Pengadaan, SDM).
- Duplikasi catatan presensi (`AttendanceRecord`) khusus di domain SDM (izin/sakit/cuti tercatat 2x pada tanggal yang sama).

### Perubahan Kode & Test yang Diterapkan:
1. **Core Engine Fix (`app/Domains/Workflow/Actions/ProcessApprovalAction.php`)**:
   - Menambahkan guard clause di baris pertama `execute()`:
     ```php
     if (! in_array($request->status, [ApprovalStatus::Pending, ApprovalStatus::InReview], true)) {
         throw ValidationException::withMessages([
             'approval' => 'Permintaan persetujuan ini sudah selesai diproses ('.$request->status->label().'), tidak dapat diproses ulang.',
         ]);
     }
     ```
   - Status yang ditolak: `Approved`, `Rejected`, `RevisionRequired`, `Cancelled` (4 terminal statuses).
   - Status yang diizinkan: `Pending`, `InReview` (2 active statuses).
   - `current_step_id` tetap dipertahankan (tidak di-null-kan) agar view riwayat seperti `admin/kehadiran-sdm/izin-cuti/show.blade.php:84` yang membaca `$ar?->currentStep?->step_name` tetap berfungsi normal.

2. **Tests Ditambahkan (TDD & Regresi)**:
   - `tests/Feature/Workflow/ProcessApprovalActionTest.php` (baru, 6 test case):
     - Memproses request `Pending` secara normal (baseline).
     - Menolak pemrosesan ulang request `Approved` + memastikan tidak ada log ganda.
     - Menolak pemrosesan ulang request `Rejected`.
     - Menolak pemrosesan ulang request `RevisionRequired`.
     - Menolak pemrosesan ulang request `Cancelled`.
     - Tetap memproses request `InReview` (multi-step workflow mid-flight).
   - `tests/Feature/Rapor/RaporPersetujuanControllerTest.php` (+1 test):
     - Menolak approve Kepsek kedua kali pada pengajuan rapor yang sudah `Disetujui` dan tidak menduplikasi `ApprovalLog`.
   - `tests/Unit/Domains/Pengadaan/PengajuanApprovalActionTest.php` (+1 test):
     - Menolak approve kedua kali pada proposal yang sudah `Approved` dan tidak menduplikasi `ApprovalLog`.
   - `tests/Feature/Sdm/ProsesApprovalIzinCutiActionTest.php` (+1 test):
     - Menolak approve kedua kali pada izin/cuti yang sudah `Approved`, memastikan tidak ada duplikasi `AttendanceRecord` maupun `ApprovalLog`.

---

## 2. Keputusan Penting yang Diambil

1. **Root Cause Fix di Engine Workflow, Bukan Controller**:
   - Menolak pendekatan penambahan guard di masing-masing controller (Rapor / Pengadaan / SDM). Dengan meletakkan guard di `ProcessApprovalAction::execute()`, semua domain terlindungi secara konsisten dan seragam, termasuk jika di masa depan ada pemanggilan dari API, CLI command, atau modul baru.
2. **Tidak Me-null-kan `current_step_id` pada Final Step**:
   - Hasil audit kode menemukan bahwa Blade view di SDM (`admin/kehadiran-sdm/izin-cuti/show.blade.php:84`) mengasumsikan `$approvalRequest->currentStep` tetap dapat diakses untuk menampilkan nama langkah terakhir. Oleh karena itu, integritas relasi step tetap dipertahankan.
3. **Penyelarasan Import & Formatter Pint**:
   - Menambahkan import `ApprovalLog` dan `ValidationException` yang sebelumnya belum ada di file test Pengadaan dan SDM.
   - Menjalankan Pint dan menyesuaikan method casing / styling sesuai standar project.

---

## 3. Hasil Verifikasi

1. **Targeted Multi-Domain Regression Suite**:
   - Perintah: `vendor/bin/pest tests/Feature/Workflow tests/Feature/Rapor tests/Feature/Akademik/RaporApprovalActionsTest.php tests/Feature/Akademik/RaporApprovalLockTest.php tests/Feature/Akademik/RaporApprovalTenantScopeTest.php tests/Feature/Akademik/SubmitPengajuanRaporActionGuardTest.php tests/Feature/Akademik/SubmitPengajuanRaporActionTest.php tests/Feature/Pengadaan tests/Unit/Domains/Pengadaan tests/Feature/Sdm tests/Feature/Admin/ApprovalIzinCutiControllerTest.php tests/Feature/Akademik/PersetujuanRaporRiwayatTest.php --compact`
   - Hasil: **180 passed (488 assertions)** dalam 77.79 detik.
2. **Full Project Test Suite (`php artisan test --compact`)**:
   - Total test dijalankan: **3,148 tests** (8,474 assertions).
   - Lulus: **3,145 passed**.
   - Gagal: **3 failed** — ketiganya merupakan *pre-existing known failures* yang sudah terdaftar di plan dan sama sekali tidak tersentuh oleh diff perbaikan ini:
     - `Tests\Unit\M3DemoDataSeederTest > it seeds a spread of pendaftaran states across K-9 institutions...`
     - `Tests\Unit\M3DemoDataSeederTest > it is idempotent when the full DatabaseSeeder is run twice`
     - `Tests\Feature\Akademik\SubjekTenantValidationTest > it rejects a komponen penilaian whose mata_pelajaran...`
   - Tidak ada regresi baru di domain mana pun.
3. **Pint**:
   - Perintah: `vendor/bin/pint --dirty --format agent`
   - Hasil: Clean (`{"tool":"pint","result":"passed"}`).

---

## 4. Git State & Hal yang Perlu Direview

- **Branch aktif**: `rbac-v2` (local ahead of `origin/rbac-v2`).
- **Commit History untuk Task Ini**:
  - `2ad4ba50` — `docs(workflow): spec guard status terminal di ProcessApprovalAction`
  - `ae48d563` — `docs(workflow): plan + kickoff perbaikan guard status terminal ProcessApprovalAction`
  - `7194668c` — `fix(workflow): tolak memproses ulang ApprovalRequest yang statusnya sudah final`
  - `ef7a5526` — `test(rapor): regresi double-approve pada pengajuan rapor yang sudah Disetujui`
  - `f9b48039` — `test(pengadaan): regresi double-approve pada proposal yang sudah Approved`
  - `ab45e520` — `test(sdm): regresi double-approve pada izin/cuti yang sudah Approved, cek AttendanceRecord tidak dobel`
  - `41a45eb6` — `docs(workflow): tandai semua task dan step plan selesai diimplementasi dan diverifikasi`
- **Review Points untuk Manusia/Claude**:
  - Semua pesan `ValidationException` menggunakan field key `approval` dan format: `"Permintaan persetujuan ini sudah selesai diproses ({label}), tidak dapat diproses ulang."`.
  - Alur resubmit (misalnya edit & ajukan ulang pada Pengadaan dan Rapor setelah penolakan/revisi) sudah diverifikasi tetap berjalan lancar karena alur tersebut mereset `ApprovalRequest.status` sebelum masuk ke approval step berikutnya.
