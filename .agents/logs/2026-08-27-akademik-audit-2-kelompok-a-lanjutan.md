# Handoff Log: Fix Susulan Kelompok A — Widget Jadwal Siswa & Orang Tua

**Tanggal**: 2026-08-27  
**Branch**: `akademik-v2`  
**Spec**: [`.agents/specs/2026-08-27-akademik-audit-2-kelompok-a-lanjutan.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-27-akademik-audit-2-kelompok-a-lanjutan.md)  
**Plan**: [`.agents/plans/2026-08-27-akademik-audit-2-kelompok-a-lanjutan.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-27-akademik-audit-2-kelompok-a-lanjutan.md)  
**Status**: ✅ **100% COMPLETE & VERIFIED** (DashboardTest: 22 passed, 66 assertions, 0 failed)

---

## 1. Apa yang Dikerjakan

Menangani 2 consumer widget jadwal di `DashboardController.php` (siswa dan orang tua) yang terlewat saat pengerjaan Kelompok A, serta membenahi `scopeSemesterAktif()` agar aman di konteks lintas-lembaga:

1. **Task 1: Perbaiki `scopeSemesterAktif()` agar tenant-safe di semua konteks**
   - Mengubah `app/Models/JadwalPelajaran.php` method `scopeSemesterAktif()` agar subquery ke relasi `semester` menggunakan `->withoutGlobalScope(TenantScope::class)->where('status_aktif', true)`.
   - Menambahkan import `use App\Models\Scopes\TenantScope;`.
   - Memverifikasi regresi guru pada `DashboardTest.php` sebelum dan sesudah perubahan (keduanya identik 2/2 passed).
   - Commit: `beed431b` (`fix(akademik): scopeSemesterAktif bypass TenantScope agar aman lintas-lembaga`).

2. **Task 2: Terapkan `->semesterAktif()` ke widget jadwal siswa**
   - Menambahkan filter `->semesterAktif()` pada query `JadwalPelajaran` di branch siswa `DashboardController.php:123-127`.
   - Menambahkan 2 feature test di `tests/Feature/DashboardTest.php` (eksklusi jadwal semester non-aktif dan penanganan lembaga tanpa semester aktif).
   - Memverifikasi seluruh 6 test siswa di `DashboardTest.php` lulus (17 assertions).
   - Commit: `692edc4c` (`fix(akademik): widget jadwal hari ini siswa filter semester aktif`).

3. **Task 3: Terapkan `->semesterAktif()` ke widget jadwal orang tua (termasuk skenario lintas-lembaga)**
   - Menambahkan filter `->semesterAktif()` pada query `JadwalPelajaran` di branch orang tua `DashboardController.php:234-240`.
   - Menambahkan 3 feature test di `tests/Feature/DashboardTest.php` (eksklusi jadwal non-aktif 1 anak, inklusi jadwal aktif 2 anak di 2 lembaga berbeda / cross-tenant, dan eksklusi independen jadwal non-aktif anak A sementara anak B aktif di lembaga lain).
   - Menjalankan seluruh test suite `DashboardTest.php` sebagai checkpoint final (22 passed, 66 assertions, 0 failed).
   - Commit: `ec883808` (`fix(akademik): widget jadwal hari ini orang tua filter semester aktif per anak`).

4. **Task 4: Dokumentasi & Peta Pengembangan**
   - Memperbarui `PETA_PENGEMBANGAN.md` pada bagian Kelompok A dengan tindak lanjut fix susulan ini.
   - Commit: `a14c3a5d` (`docs: catat fix susulan widget jadwal siswa & orang tua Kelompok A`).

---

## 2. Keputusan Penting yang Diambil

1. **Bypass TenantScope pada Subquery `semester`**:
   - Model `Semester` menerapkan `BelongsToTenant`. Ketika user orang tua (yang memiliki `lembaga_id = null`) mengakses dashboard anak-anaknya di berbagai lembaga, query jadwal orang tua sengaja membypass TenantScope.
   - Oleh karena itu, subquery `whereHas('semester', ...)` di dalam `scopeSemesterAktif()` harus secara eksplisit membypass `TenantScope::class` agar mengecek `status_aktif` dari semester milik anak tersebut, bukan milik user orang tua.
   - Untuk user guru (single-tenant), bypass ini terbukti tidak mengubah hasil query sama sekali karena query induk guru sudah terisolasi oleh `lembaga_id` guru.
2. **Preservasi Pola `withoutGlobalScope(TenantScope::class)` Existing**:
   - `->semesterAktif()` ditambahkan murni sebagai filter tambahan tanpa mengganggu rantai `withoutGlobalScope()` pada relasi `kelas`, `mataPelajaran`, dan `jamPelajaran` di branch orang tua maupun siswa.
3. **Integritas Data Riwayat Jadwal**:
   - Sama seperti di Kelompok A, jadwal lama tidak dihapus dari tabel `jadwal_pelajaran` agar riwayat presensi siswa historis tetap utuh dan valid.

---

## 3. Hal yang Perlu Direview / Catatan Lanjutan

1. **Surface Area Siswa & Orang Tua Terkonfirmasi Lengkap**:
   - Audit lanjutan mengonfirmasi tidak ada controller, action, atau route Akademik lain yang diakses oleh role `siswa` atau `orang_tua` di luar `DashboardController.php`. Seluruh query jadwal aktif di dashboard kini 100% menggunakan `scopeSemesterAktif()`.
2. **Git State**:
   - Branch: `akademik-v2`
   - Commits Fix Susulan:
     - `beed431b`: `fix(akademik): scopeSemesterAktif bypass TenantScope agar aman lintas-lembaga`
     - `692edc4c`: `fix(akademik): widget jadwal hari ini siswa filter semester aktif`
     - `ec883808`: `fix(akademik): widget jadwal hari ini orang tua filter semester aktif per anak`
     - `a14c3a5d`: `docs: catat fix susulan widget jadwal siswa & orang tua Kelompok A`

---

## 4. Hasil Verifikasi Checkpoint `DashboardTest.php`

```text
Command : php artisan test tests/Feature/DashboardTest.php --compact
Hasil   : 22 passed (66 assertions), 0 failed
Durasi  : 12.55s
Status  : 100% HIJAU
```
