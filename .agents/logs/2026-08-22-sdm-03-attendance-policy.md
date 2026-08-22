# Handoff Log — Kehadiran SDM Sub-project 3: Attendance Policy Dasar

## 1. Dokumen Referensi
- **Spec:** [`.agents/specs/2026-08-22-sdm-03-attendance-policy.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-22-sdm-03-attendance-policy.md)
- **Plan:** [`.agents/plans/2026-08-22-sdm-03-attendance-policy.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-22-sdm-03-attendance-policy.md)

---

## 2. Apa yang Dikerjakan

Sub-project 3 mengimplementasikan domain **Attendance Policy** (kebijakan jam kerja, jam pulang opsional, toleransi keterlambatan, serta override hari kerja per kategori pegawai) dan deteksi keterlambatan otomatis (`is_late`, `late_minutes`) pada `AttendanceRecord`.

### Rincian Eksekusi per Task & Commit History:

| Task | Deskripsi | Commit | Status |
|---|---|---|---|
| **Task 1** | Migrasi tabel `attendance_policies` & penambahan kolom `is_late`/`late_minutes` di tabel `attendance_records` | `9479e99` | ✅ Selesai |
| **Task 2** | Model [`AttendancePolicy`](file:///d:/laragon/www/pintera-app/app/Domains/Sdm/Models/AttendancePolicy.php) (`BelongsToTenant`) & penambahan `$fillable` + casts di [`AttendanceRecord`](file:///d:/laragon/www/pintera-app/app/Domains/Sdm/Models/AttendanceRecord.php) | `7ec6b8d` | ✅ Selesai (2/2 tests pass) |
| **Task 3** | Service [`AttendancePolicyResolver`](file:///d:/laragon/www/pintera-app/app/Domains/Sdm/Services/AttendancePolicyResolver.php) dengan bypass `withoutGlobalScope(TenantScope::class)` pada kedua query (lembaga & default yayasan) serta pembungkusan delegasi ke `KalenderKerjaSdmResolver` | `e78e7a8` | ✅ Selesai (8/8 tests pass) |
| **Task 4** | Integrasi `AttendancePolicyResolver` ke [`RecordManualAttendanceAction`](file:///d:/laragon/www/pintera-app/app/Domains/Sdm/Actions/RecordManualAttendanceAction.php) dan [`ScanQrAttendanceAction`](file:///d:/laragon/www/pintera-app/app/Domains/Sdm/Actions/ScanQrAttendanceAction.php) | `cc04c0a` | ✅ Selesai (10/10 tests pass) |
| **Task 5** | Perhitungan keterlambatan otomatis (`is_late`, `late_minutes`) di [`AttendanceRecordAggregator`](file:///d:/laragon/www/pintera-app/app/Domains/Sdm/Services/AttendanceRecordAggregator.php) menggunakan `CarbonInterface` | `894b3f2` | ✅ Selesai (18/18 tests pass) |
| **Task 6** | Pembaruan command [`TandaiAlpaOtomatisSdm`](file:///d:/laragon/www/pintera-app/app/Console/Commands/TandaiAlpaOtomatisSdm.php) dengan evaluasi `resolveLibur()` per-pegawai untuk menutup celah dua arah (pegawai bertugas saat libur lembaga & pegawai paruh waktu yang libur saat lembaga masuk) | `761c131` | ✅ Selesai (8/8 tests pass) |
| **Task 7** | Perluasan [`AttendanceConfigurationController`](file:///d:/laragon/www/pintera-app/app/Http/Controllers/Admin/AttendanceConfigurationController.php) (`storePolicy`, `updatePolicy`, `destroyPolicy`) dengan validasi duplikasi dan registrasi routes di [`routes/admin/kehadiran-sdm.php`](file:///d:/laragon/www/pintera-app/routes/admin/kehadiran-sdm.php) | `b79d400` | ✅ Selesai (16/16 tests pass) |
| **Task 8** | Tampilan Tab "Attendance Policy" dan modal pengelolaan di [`resources/views/admin/kehadiran-sdm/konfigurasi.blade.php`](file:///d:/laragon/www/pintera-app/resources/views/admin/kehadiran-sdm/konfigurasi.blade.php) | `b5c138f` | ✅ Selesai (3/3 tests pass) |
| **Task 9** | Verifikasi Arsitektur, Grep Audit, Test Scoped Domain SDM (82 passed), Full Test Suite (1986 passed) | (Commit saat ini) | ✅ Selesai |

---

## 3. Keputusan Penting yang Diambil

1. **Independensi Kalender SDM (`KalenderKerjaSdmResolver`) Tetap 0-Byte Diff**:
   - `KalenderKerjaSdmResolver` tidak disentuh / dimodifikasi sama sekali. `AttendancePolicyResolver` menerima instance `KalenderKerjaSdmResolver` melalui constructor injection dan hanya mendelegasikan pengecekan kalender jika pegawai tidak memiliki override `hari_kerja` di policy-nya.
2. **TenantScope Bypass**:
   - `AttendancePolicy` menggunakan `BelongsToTenant`. `AttendancePolicyResolver::resolvePolicy()` menggunakan `withoutGlobalScope(TenantScope::class)` pada kedua query (override lembaga & default yayasan `whereNull('lembaga_id')`), sehingga aktor berscope lembaga tetap dapat melihat dan menerapkan policy default yayasan.
3. **Penanganan Unique Constraint MySQL**:
   - MySQL menganggap dua nilai `NULL` tidak sama pada unique index (`yayasan_id, lembaga_id, jenis_ptk, jenis_karyawan_id`). Oleh karena itu, pengecekan duplikat eksplisit (`AttendancePolicy::where(...)->exists()`) ditambahkan di `AttendanceConfigurationController::storePolicy` sebelum insert.
4. **Celah Dua Arah pada Auto-Alpa (`TandaiAlpaOtomatisSdm`)**:
   - Pengecekan status hari kerja/libur dievaluasi per-pegawai via `AttendancePolicyResolver::resolveLibur($pegawai, $tanggal)`. Hal ini secara akurat menangani:
     - Karyawan yang harus masuk pada hari libur lembaga (misalnya Satpam/Security dengan policy kerja 7 hari).
     - Karyawan dengan jam/hari kerja khusus (misalnya paruh waktu) agar tidak salah ditandai Alpa pada hari lembaga masuk namun policy pegawai libur.
5. **RBAC Tanpa Hardcode Role**:
   - Hak istimewa pengelolaan policy nasional (`lembaga_id = null`) dibatasi dengan `$request->user()->widestScopeLevel() === 'yayasan'`. Audit grep mengonfirmasi **0** penggunaan `hasRole()` pada domain SDM.

---

## 4. Hasil Verifikasi Akhir

1. **Grep Audits**:
   - `hasRole()` di `app/Domains/Sdm`, `AttendanceConfigurationController`, dan `TandaiAlpaOtomatisSdm`: **0 temuan**.
   - `git diff` terhadap `KalenderKerjaSdmResolver.php`: **0 baris berubah** (KOSONG).
2. **SDM Scoped Test Suite (Sub-project 1, 2, & 3)**:
   - **82 passed**, 192 assertions, 0 failed.
3. **Full Test Suite (`php artisan test`)**:
   - **1986 passed**, 6022 assertions, 0 failed (bertambah 24 test baru dari baseline Sub-project 2 sebanyak 1962).

---

## 5. Hal yang Masih Perlu Direview Manusia / Claude

- Seluruh kode telah di-commit ke branch `sdm-v1`.
- Sistem siap untuk melanjutkan ke Sub-project 4 (Izin, Cuti Berjenjang & Workflow Kehadiran SDM).
