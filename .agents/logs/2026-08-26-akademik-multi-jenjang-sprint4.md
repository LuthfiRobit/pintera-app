# Handoff Log: Fondasi Akademik Multi-Jenjang — Sprint 4 (Academic Profile Service)

- **Tanggal**: 2026-08-26
- **Branch**: `akademik-v2`
- **Spec**: `.agents/specs/2026-08-26-akademik-multi-jenjang-sprint4.md`
- **Plan**: `.agents/plans/2026-08-26-akademik-multi-jenjang-sprint4.md`
- **Status Akhir**: SELESAI & TERVERIFIKASI (Full Test Suite: 2221 passed, 4 skipped, 0 failed, 6145 assertions)

---

## 1. Apa yang Dikerjakan

Sprint 4 mengimplementasikan `AcademicProfile` sebagai immutable value object yang menyediakan preset platform defaults untuk pre-fill UX (`learningMode` dan `reportTemplate`) berdasarkan `bentuk_pendidikan`.

Secara rinci:
1. **Value Object `AcademicProfile`** (`bc0eb411`):
   - File implementasi: `app/Domains/Akademik/Support/AcademicProfile.php`.
   - Pola: `final class`, constructor `private`, property `public readonly ModePembelajaran $learningMode`, `public readonly string $reportTemplate`.
   - Factory: `AcademicProfile::fromBentukPendidikan(string $bentukPendidikan): self`.
   - Menggunakan `ModePembelajaran::fromBentukPendidikan($bentukPendidikan)` langsung untuk `learningMode` (tidak menduplikasi aturan).
   - Mapping `reportTemplate` ke key abstrak:
     - `KB`, `TPA`, `SPS`, `TK` → `'paud'`
     - `SD` → `'sd'`
     - `SMP`, `SMA` → `'smp-sma'`
     - `SMK` → `'smk'`
     - `SLB` → `'sd'` (compatibility behavior eksplisit)
     - Di luar 9 nilai whitelist resmi throw `InvalidArgumentException`.
   - Docblock eksplisit menegaskan bahwa `AcademicProfile` adalah platform default/preset, bukan tenant policy.

2. **Unit Tests `AcademicProfileTest`** (`bc0eb411`):
   - File test: `tests/Unit/Support/AcademicProfileTest.php`.
   - 20 skenario individual mencakup:
     - Derivasi 9 bentuk pendidikan resmi terhadap `learningMode` dan `reportTemplate` (9 tests).
     - Pembuktian konsistensi `learningMode` identik dengan pemanggilan langsung `ModePembelajaran::fromBentukPendidikan()` (9 tests).
     - Unknown `bentuk_pendidikan` ('XYZ') melempar `InvalidArgumentException` dengan pesan jelas (1 test).
     - Empty string `bentuk_pendidikan` melempar `InvalidArgumentException` (1 test).

3. **Verifikasi Penuh & Regresi** (Task 2):
   - Full test suite dijalankan secara menyeluruh tanpa filter: `php artisan test`.
   - Hasil aktual: **2221 passed, 4 skipped, 0 failed** (6145 assertions, durasi 534.29s).
   - Tidak ada modifikasi pada file lain selain 2 file di Task 1 + docs (plan & handoff log).

---

## 2. Keputusan Penting yang Diambil

1. **Pemangkasan Scope dari 4 Field Menjadi 2 Field**:
   - `defaultAssessmentType` di-drop karena sudah tergantikan secara lebih presisi oleh defaulting Sprint 2 berbasis `subjekType` (`elemen_cp` → narrative, `mata_pelajaran` → numeric) pada `CreateKomponenPenilaianAction`.
   - `subjectRequired` di-drop karena belum ada satupun consumer nyata di codebase.
   - Sisa field yang diimplementasikan murni `learningMode` dan `reportTemplate`.

2. **Perbedaan Filosofi vs `FaseDefaultMapping` (Sprint 3)**:
   - `FaseDefaultMapping` adalah konfigurasi kurikulum yang tersimpan ke database (`kelas.fase_id`) dan mengikat state bisnis, sehingga harus data-driven/config-driven.
   - `AcademicProfile` adalah preset karakteristik jenjang untuk pre-fill UX sesaat yang masih bisa diubah user sebelum tersimpan, sehingga implementasi statis (`match()`) adalah pilihan tepat dan terhindar dari overengineering.

3. **Abstraksi `reportTemplate` Tetap String Polos**:
   - `reportTemplate` mengembalikan key string (`'paud'`, `'sd'`, `'smp-sma'`, `'smk'`), bukan enum dan bukan path view Blade.
   - Konsolidasi dengan `RaporPdfDataBuilder` atau pembuatan enum/registry diserahkan ke concern Sprint 5 (Report Engine).

4. **Nol Refactor Consumer Existing**:
   - Consumer existing seperti `GenerateSesiHarianAction` tetap memanggil `ModePembelajaran::fromBentukPendidikan()` secara langsung tanpa diubah paksa ke `AcademicProfile`, menjaga blast radius tetap 0.

---

## 3. Hal yang Perlu Direview Manusia / Claude

1. **Git State**:
   - Branch: `akademik-v2`
   - Commit Task 1: `bc0eb411`
   - File code yang tersentuh hanya 2:
     - `app/Domains/Akademik/Support/AcademicProfile.php` (created)
     - `tests/Unit/Support/AcademicProfileTest.php` (created)
   - Working tree: Clean.

2. **Kesiapan Menuju Sprint 5 (Report Engine / Rapor Multi-Jenjang)**:
   - Kontrak `AcademicProfile::fromBentukPendidikan($bentukPendidikan)->reportTemplate` sudah siap dikonsumsi oleh `ReportEngine` di Sprint 5 untuk menentukan Report Builder (`paud`, `sd`, `smp-sma`, `smk`).
   - Pada Sprint 5, perlu dievaluasi kembali apakah SLB tetap menggunakan builder SD (`'sd'`) atau perlu builder mandiri.
