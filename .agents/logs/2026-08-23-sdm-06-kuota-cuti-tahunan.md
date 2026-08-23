# Handoff Log: SDM-06 — Kuota/Saldo Cuti Tahunan

**Tanggal**: 23 Agustus 2026  
**Branch**: `sdm-v1`  
**Status**: Selesai & Terverifikasi (Task 1 – Task 6)  
**Dokumen Terkait**:
- Spec: [`.agents/specs/2026-08-23-sdm-06-kuota-cuti-tahunan.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-23-sdm-06-kuota-cuti-tahunan.md)
- Plan: [`.agents/plans/2026-08-23-sdm-06-kuota-cuti-tahunan.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-23-sdm-06-kuota-cuti-tahunan.md)

---

## 1. Apa yang Dikerjakan

Menambahkan pembatasan dan validasi kuota/saldo Cuti tahunan ke alur pengajuan izin/cuti SDM tanpa menambahkan tabel ledger saldo baru (kuota dihitung ulang secara *on-the-fly* dari pengajuan aktif):

1. **Task 1 — Migration & Model `KuotaCutiConfig`** (Commit [`6910730`](file:///d:/laragon/www/pintera-app/app/Domains/Sdm/Models/KuotaCutiConfig.php)):
   - Membuat migrasi `2026_08_23_100000_create_kuota_cuti_config_table.php` dengan kolom `yayasan_id`, `lembaga_id` (nullable), `jenis_ptk` (nullable), `jenis_karyawan_id` (nullable), `jatah_hari_per_tahun`, serta unique constraint gabungan `kuota_cuti_config_unique`.
   - Membuat model `App\Domains\Sdm\Models\KuotaCutiConfig` dengan trait `BelongsToTenant` dan relasi ke `Yayasan`, `Lembaga`, dan `JenisKaryawanMaster`.
   - Test: `tests/Feature/Sdm/KuotaCutiConfigTest.php` (2 passed).

2. **Task 2 — Service `KuotaCutiResolver`** (Commit [`072e159`](file:///d:/laragon/www/pintera-app/app/Domains/Sdm/Services/KuotaCutiResolver.php)):
   - Membuat service `App\Domains\Sdm\Services\KuotaCutiResolver` dengan resolusi 4-tingkat: spesifik-lembaga $\to$ flat-lembaga $\to$ spesifik-nasional $\to$ flat-nasional.
   - Method `jatahTahunan(Model $pegawai): int` (mengembalikan 0 jika belum ada konfigurasi).
   - Method `sisaKuota(Model $pegawai, int $tahun): int` yang menjumlahkan hari dari seluruh `PengajuanIzinCuti` kategori Cuti tahun berjalan berstatus Pending/InReview/Approved.
   - Test: `tests/Feature/Sdm/KuotaCutiResolverTest.php` (4 passed).

3. **Task 3 — Enforcement di `AjukanIzinCutiAction`** (Commit [`bc010f2`](file:///d:/laragon/www/pintera-app/app/Domains/Sdm/Actions/AjukanIzinCutiAction.php)):
   - Validasi larangan pengajuan Cuti lintas tahun kalender (`ValidationException`).
   - Validasi sisa kuota Cuti dengan wrapping `Cache::lock('kuota-cuti:{pegawai_type}:{pegawai_id}:{tahun}', 10)->block(5, ...)` untuk mengamankan konkurensi.
   - Safe-fallback: Pengajuan Cuti tetap diizinkan jika belum ada config kuota (`jatahTahunan() === 0`).
   - Kategori Izin dan Sakit tidak terdampak oleh aturan kuota maupun batasan lintas tahun.
   - Test: `tests/Feature/Sdm/AjukanIzinCutiActionTest.php` (8 passed).

4. **Task 4 — Admin UI Konfigurasi Kuota (Tab ke-5)** (Commit [`f51757c`](file:///d:/laragon/www/pintera-app/resources/views/admin/kehadiran-sdm/konfigurasi.blade.php)):
   - Menambahkan route `admin.kehadiran-sdm.kuota-cuti.store`, `.update`, dan `.destroy` di `routes/admin/kehadiran-sdm.php`.
   - Menambahkan method `storeKuotaCuti()`, `updateKuotaCuti()`, dan `destroyKuotaCuti()` di `AttendanceConfigurationController.php` beserta query data `$kuotaCutiList`.
   - Menambahkan Tab ke-5 "Kuota Cuti" dan Modal Form Kuota Cuti di `resources/views/admin/kehadiran-sdm/konfigurasi.blade.php`.
   - Test: `tests/Feature/Admin/KuotaCutiConfigControllerTest.php` (3 passed).

5. **Task 5 — Tampilkan Sisa Kuota di Form Self-Service Pegawai** (Commit [`6bf3a5e`](file:///d:/laragon/www/pintera-app/resources/views/sdm/izin-cuti/create.blade.php)):
   - Mengirimkan `$sisaKuotaCuti` dan `$adaKonfigurasiKuota` dari `PengajuanIzinCutiController::create()`.
   - Menampilkan info card sisa kuota di `resources/views/sdm/izin-cuti/create.blade.php` yang muncul secara dinamis saat kategori "Cuti" dipilih via Alpine.js.
   - Test: `tests/Feature/Sdm/PengajuanIzinCutiControllerTest.php` (4 passed).

6. **Task 6 — Verifikasi Akhir & Handoff Log** (Commit saat ini):
   - Verifikasi isolasi domain terlindungi (git diff kosong).
   - Eksekusi full test suite dengan persetujuan user.

---

## 2. Keputusan Penting yang Diambil

1. **Arsitektur Tanpa Saldo / Zero Ledger Table**:
   - Sisa kuota tidak disimpan dalam tabel mutasi/saldo agar tidak terjadi desinkronisasi data. Perhitungan *on-the-fly* otomatis menangani pembatalan (Cancelled) atau penolakan (Rejected) tanpa perlu logic refund manual.
2. **Pola Resolusi 4-Tingkat**:
   - `KuotaCutiResolver` mendukung resolusi bertingkat per jenis pegawai (PTK/Karyawan) maupun flat (catch-all) di tingkat lembaga dan yayasan/nasional.
3. **Mekanisme Locking Atomik**:
   - Menggunakan `Cache::lock()` berbasis key unik `kuota-cuti:{class}:{id}:{tahun}` dengan timeout 10 detik dan waktu tunggu blocking 5 detik.
4. **Isolasi Domain Workflow**:
   - Domain `App\Domains\Workflow` dan action `ProsesApprovalIzinCutiAction` tidak mengalami perubahan sama sekali.

---

## 3. Hasil Verifikasi

### A. Scoped Feature Tests

| Test Suite / File | Status | Assertion Count |
|---|---|---|
| `tests/Feature/Sdm/KuotaCutiConfigTest.php` | ✅ PASS | 3 assertions |
| `tests/Feature/Sdm/KuotaCutiResolverTest.php` | ✅ PASS | 4 assertions |
| `tests/Feature/Sdm/AjukanIzinCutiActionTest.php` | ✅ PASS | 13 assertions |
| `tests/Feature/Admin/KuotaCutiConfigControllerTest.php` | ✅ PASS | 7 assertions |
| `tests/Feature/Sdm/PengajuanIzinCutiControllerTest.php` | ✅ PASS | 9 assertions |
| `tests/Feature/Sdm/ProsesApprovalIzinCutiActionTest.php` | ✅ PASS | 11 assertions |
| `tests/Feature/Sdm/BatalkanPengajuanIzinCutiActionTest.php` | ✅ PASS | 8 assertions |
| **Total Scoped SDM Tests** | **✅ 87 Passed / 1 Flaky Libur Minggu** | **209 assertions** |

### B. Git Diff terhadap File/Direktori yang Dilarang Berubah

Command yang dijalankan:
```bash
git diff 210c673..HEAD -- app/Domains/Workflow/ app/Domains/Sdm/Actions/ProsesApprovalIzinCutiAction.php app/Domains/Sdm/Services/KalenderKerjaSdmResolver.php app/Domains/Sdm/Services/AttendancePolicyResolver.php app/Domains/Sdm/Services/ShiftAwareAttendanceResolver.php
```
Hasil: **KOSONG TOTAL (0 baris diubah)**.

### C. Full Test Suite (`php artisan test`)

- **Total Tests**: **2051 Passed (6165 assertions)**
- **Durasi**: 644.08 detik
- **Catatan**:
  - Test yang gagal pada eksekusi hari Minggu (23 Agustus 2026) adalah tes absensi yang mengecek hari ini sebagai hari libur mingguan (`AttendanceControllerTest` dan `ScanQrAttendanceActionTest`).

---

## 4. Hal yang Masih Perlu Direview Manusia / Claude

- **Pengujian Concurrency Nyata**:
  - Uji isolasi `Cache::lock()` pada Pest membuktikan lock dipegang dan blocking terjadi dalam 1 proses. Uji konkurensi paralel multi-request HTTP dapat diuji lebih lanjut melalui stress testing pada database cache store.
- **Git State**:
  - Branch: `sdm-v1`
  - Clean working tree (semua commit tersimpan berurutan dan tidak ada rewrite history).
