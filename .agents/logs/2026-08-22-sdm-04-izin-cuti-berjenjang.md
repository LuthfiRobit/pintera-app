# Handoff Log: SDM Sub-project 4 — Pengajuan Izin/Cuti Mandiri & Approval Berjenjang

**Tanggal**: 22 Agustus 2026  
**Branch**: `sdm-v1`  
**Status**: Selesai & Terverifikasi (Task 1 – Task 10)  
**Dokumen Terkait**:
- Spec: [`.agents/specs/2026-08-22-sdm-04-izin-cuti-berjenjang.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-22-sdm-04-izin-cuti-berjenjang.md)
- Plan: [`.agents/plans/2026-08-22-sdm-04-izin-cuti-berjenjang.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-22-sdm-04-izin-cuti-berjenjang.md)

---

## 1. Apa yang dikerjakan

Implementasi lengkap Sub-project 4 (SDM-04) untuk fitur Pengajuan Izin/Cuti Pegawai (Guru & Karyawan) secara mandiri (self-service portal) yang terintegrasi dengan Universal Workflow Engine Pintera (`ApprovalRequest`, `WorkflowDefinition`, `ApprovalAction`, `ApprovalStatus`) dan sinkronisasi kehadiran otomatis (`AttendanceEvent` + `AttendanceRecordAggregator`):

1. **Database Schema & Enums**:
   - Migration `2026_08_22_130000_create_pengajuan_izin_cuti_table.php` (`id`, `lembaga_id`, `pegawai_type`, `pegawai_id`, `kategori`, `tanggal_mulai`, `tanggal_selesai`, `alasan`, `lampiran_path`, `status`).
   - Enum `App\Domains\Sdm\Enums\KategoriPengajuanIzin` (`Izin`, `Sakit`, `Cuti`).
   - Enum `App\Domains\Sdm\Enums\AttendanceStatus` ditambah case `Cuti = 'cuti'`.
   - Enum `App\Domains\Workflow\Enums\ApprovalStatus` ditambah case `Cancelled = 'cancelled'` (murni aditif).
   - Enum `App\Domains\Workflow\Enums\ApprovalAction` ditambah case `Cancel = 'cancel'` (murni aditif).

2. **Model Domain & Relasi Morph**:
   - Model `App\Domains\Sdm\Models\PengajuanIzinCuti` dengan trait `BelongsToTenant`, casting tanggal, relasi `pegawai()`, `approvalRequests()`, `currentApprovalRequest()`, `latestApprovalRequest()`.
   - Relasi `morphMany(PengajuanIzinCuti::class, 'pegawai')` pada model `App\Models\Guru` dan `App\Models\Karyawan`.

3. **Universal Workflow & RBAC**:
   - Definisi seeder workflow `IZIN_CUTI_SDM` dengan 2-step approval (`kepala_sekolah` → `admin_sdm`) di `WorkflowDefinitionSeeder.php`.
   - Permissions baru di `PermissionSeeder.php`:
     - `kehadiran-sdm.izin.ajukan` (diberikan ke `guru`)
     - `kehadiran-sdm.izin.approve` (diberikan ke `kepala_sekolah`, `admin_sdm`, `yayasan_super_admin`)
     - `kehadiran-sdm.izin.lihat-sendiri` (diberikan ke `guru`)
   - Role assignments di `RoleSeeder.php` dan `RolePermissionSeederTest`/`RoleSeederTest`/`PermissionSeederTest`.

4. **Action Classes (Domain SDM)**:
   - `App\Domains\Sdm\Actions\AjukanIzinCutiAction`: Validasi rentang tanggal, validasi tumpang tindih (overlap) pengajuan aktif, create row `PengajuanIzinCuti`, dan inisialisasi `InitializeApprovalRequestAction::execute($pengajuan, 'IZIN_CUTI_SDM')`.
   - `App\Domains\Sdm\Actions\ProsesApprovalIzinCutiAction`: Eksekusi `ProcessApprovalAction::execute(...)`, update status pengajuan, dan jika final `Approved`, mengenerate `AttendanceEvent` method `system` (status `Izin`/`Sakit`/`Cuti`) untuk setiap hari kerja dalam rentang tanggal via `KalenderKerjaSdmResolver` & `AttendancePolicyResolver`, lalu sinkronisasi via `AttendanceRecordAggregator::syncDate(...)`.
   - `App\Domains\Sdm\Actions\BatalkanPengajuanIzinCutiAction`: Pembatalan pengajuan oleh pemilik (selama status `diajukan`/`in_review`) dan rekonsiliasi workflow request ke `Cancelled`.

5. **Console Command Integration**:
   - `app/Console/Commands/TandaiAlpaOtomatisSdm.php`: Ditambahkan pengecekan skip alpa jika pegawai memiliki pengajuan izin/cuti yang berstatus `diajukan` atau `in_review` pada tanggal target, sehingga tidak dicap alpa secara prematur saat approval masih berjalan.

6. **Self-Service & Admin UI / Controllers**:
   - `App\Http\Controllers\PengajuanIzinCutiController`: Portal mandiri (index riwayat pengajuan, create form + file upload lampiran, cancel action).
   - `App\Http\Controllers\Admin\ApprovalIzinCutiController`: Portal admin kehadiran SDM (index tab Menunggu Persetujuan & Riwayat, show detail modal/halaman dengan riwayat tracking approval step, approve, reject, revise).
   - Route `routes/sdm.php` dan `routes/admin/kehadiran-sdm.php`.
   - Blade views: `resources/views/sdm/izin-cuti/index.blade.php`, `create.blade.php`, `resources/views/admin/kehadiran-sdm/izin-cuti/index.blade.php`, `show.blade.php`.
   - Frontend asset compilation via `npm run build` (Vite build sukses).

---

## 2. Keputusan penting yang diambil

1. **Zero Modification to Workflow Engine Core Logic**:
   - Domain `App\Domains\Workflow\Models\*`, `App\Domains\Workflow\Services\ApproverResolverService`, `InitializeApprovalRequestAction`, dan `ProcessApprovalAction` **tidak diubah logic-nya sama sekali** (diff kosong).
   - Seluruh integrasi pengajuan izin/cuti memanfaatkan polymorphic approvable model `PengajuanIzinCuti` yang sudah disediakan oleh universal workflow engine.
   - Enums `ApprovalStatus` (`Cancelled`) dan `ApprovalAction` (`Cancel`) diperluas secara murni aditif tanpa mengubah value lama.

2. **Otorisasi Murni Berbasis Permission, Bukan Hardcoded Role**:
   - Di `ApprovalIzinCutiController` dan `PengajuanIzinCutiController`, tidak ada pemeriksaan `$user->hasRole(...)` dalam logika bisnis atau controller. Seluruh gating controller memakai `$this->authorize('kehadiran-sdm.izin.*')` dan validasi giliran approval didelegasikan ke `ProcessApprovalAction` via `ApproverResolverService`.

3. **Tenant & Identity Isolation**:
   - Pengajuan izin/cuti secara otomatis mengaitkan `pegawai_type` dan `pegawai_id` berdasarkan relasi authenticated user (`Guru` atau `Karyawan`).
   - Tenant isolation dijamin secara native menggunakan global scope `BelongsToTenant` pada model `PengajuanIzinCuti`.

4. **Event-Driven Attendance Reconciliation**:
   - Saat approval disetujui (final `Approved`), sistem langsung mengenerate `AttendanceEvent` method `system` untuk setiap tanggal kerja aktif (mengabaikan hari libur kalender kerja SDM / policy) dan memanggil `AttendanceRecordAggregator::syncDate(...)` agar `AttendanceRecord` harian langsung ter-update secara deterministik.

---

## 3. Hal yang masih perlu direview manusia/Claude & Status Git

1. **Hasil Verifikasi Regresi**:
   - Scoped tests Rapor Akademik (`php artisan test --filter=Rapor`): **72 tests passed**.
   - Scoped tests Pengadaan Sarpras (`php artisan test --filter=Pengadaan`): **25 tests passed**.
   - Seluruh SDM Domain Feature Tests (`php artisan test tests/Feature/Sdm tests/Feature/Admin/ApprovalIzinCutiControllerTest.php`): **73 tests passed**.
   - Seeder & Permission Tests (`PermissionSeederTest`, `RoleSeederTest`, `RolePermissionSeederTest`): **100% passed**.

2. **Git State**:
   - Current Branch: `sdm-v1`
   - Uncommitted Changes: None (semua task 1 s.d. 10 telah di-commit ke branch `sdm-v1`).
   - Pushed: Belum di-push ke remote (siap untuk dieksekusi atau di-merge sesuai arahan pengguna).
