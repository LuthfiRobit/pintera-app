# Handoff Log: Fondasi Akademik Multi-Jenjang — Sprint 5 (Konsolidasi Derivasi Kategori Jenjang Rapor)

- **Tanggal**: 2026-08-26
- **Branch**: `akademik-v2`
- **Spec**: `.agents/specs/2026-08-26-akademik-multi-jenjang-sprint5.md`
- **Plan**: `.agents/plans/2026-08-26-akademik-multi-jenjang-sprint5.md`
- **Status Akhir**: SELESAI & TERVERIFIKASI (Full Test Suite: 2230 passed, 4 skipped, 0 failed, 6145 assertions)

---

## 1. Apa yang Dikerjakan

Sprint 5 mengonsolidasikan derivasi kategori jenjang rapor ke satu sumber kebenaran tunggal (`AcademicProfile`, Sprint 4), menggantikan logika internal `in_array`/`if` duplikat yang sebelumnya ada di `RaporPdfDataBuilder::templateUntukJenjang()`.

Secara rinci:
1. **Refactor `RaporPdfDataBuilder::templateUntukJenjang()`** (`db286457`):
   - File: `app/Domains/Akademik/Services/RaporPdfDataBuilder.php`.
   - Mengubah `templateUntukJenjang(string $bentukPendidikan): string` agar mendelegasikan penentuan kategori jenjang ke `AcademicProfile::fromBentukPendidikan($bentukPendidikan)->reportTemplate`.
   - Memetakan key abstrak (`paud`, `sd`, `smp-sma`, `smk`) ke template Blade view (`pdf.rapor.paud`, `pdf.rapor.sd`, `pdf.rapor.smp-sma`, `pdf.rapor.smk`).
   - Menerapkan fail-fast 2 lapis:
     - Lapis 1: `InvalidArgumentException` dari `AcademicProfile` untuk nilai di luar whitelist 9 bentuk pendidikan.
     - Lapis 2: `default => throw new LogicException('Unsupported academic report template key.')` sebagai defense-in-depth jika di masa depan ada key valid dari `AcademicProfile` yang belum dipetakan ke Blade.
   - Menghapus komentar docblock lama terkait whitelist duplikasi.
   - `build()` dan helper `isTingkatAkhir()` tidak disentuh sama sekali.

2. **Update Test `RaporPdfDataBuilderTest`** (`db286457`):
   - File: `tests/Feature/Akademik/RaporPdfDataBuilderTest.php`.
   - Mengganti test lama yang menguji silent fallback (`'NILAI_TAK_DIKENAL' => 'pdf.rapor.sd'`) dengan:
     - Table-driven test untuk 9 bentuk pendidikan resmi (`KB`, `TPA`, `SPS`, `TK`, `SD`, `SMP`, `SMA`, `SMK`, `SLB`).
     - Test eksplisit bahwa `'NILAI_TAK_DIKENAL'` melempar `InvalidArgumentException`.
   - Seluruh test `build()` yang ada di file tersebut tetap utuh dan lulus.

3. **Verifikasi Consumer Controller** (Task 2):
   - Memeriksa pemanggilan di `app/Http/Controllers/Guru/RaporController.php` (method `cetak()`) dan `app/Http/Controllers/Lembaga/Rapor/PersetujuanController.php` (method `cetak()`).
   - Keduanya memanggil `templateUntukJenjang(...)` dengan return type `string` yang tidak berubah, sehingga tidak memerlukan modifikasi kode sama sekali.

4. **Verifikasi Penuh & Regresi** (Task 2):
   - Full test suite dijalankan secara menyeluruh tanpa filter: `php artisan test`.
   - **Hasil Aktual**: **2230 passed, 4 skipped, 0 failed** (6145 assertions, durasi 539.82s).

---

## 2. Keputusan Penting yang Diambil

1. **Pembatalan Total Premis "Report Engine Abstraction"**:
   - Premis lama roadmap (mengira hanya Dikdas yang punya builder dan jenjang lain belum diimplementasikan) terbukti tidak akurat karena ke-4 template Blade (`paud`, `sd`, `smp-sma`, `smk`) sudah production dan dilayani oleh builder generik tunggal `RaporPdfDataBuilder::build()`.
   - Pembuatan interface `ReportBuilder` / class builder per-jenjang / `ReportEngine` ditiadakan agar tidak merusak fitur production yang sudah berjalan dan tidak menambah kompleksitas yang tidak dibutuhkan.

2. **Pemisahan Tanggung Jawab (Separation of Concerns)**:
   - `AcademicProfile` bertanggung jawab atas platform preset / kategori abstrak jenjang (`reportTemplate`).
   - `RaporPdfDataBuilder` bertanggung jawab memetakan kategori abstrak tersebut ke path file Blade view (`pdf.rapor.*`).

3. **Penyelarasan Fail-Fast vs Silent Fallback**:
   - Perilaku lama yang diam-diam mem-fallback nilai tidak dikenal ke SD diganti dengan `InvalidArgumentException`, mencegah silent configuration bug pada data `bentuk_pendidikan`.

---

## 3. Hal yang Perlu Direview Manusia / Claude

1. **Git State**:
   - Branch: `akademik-v2`
   - Commit Task 1: `db286457`
   - File kode yang dimodifikasi:
     - `app/Domains/Akademik/Services/RaporPdfDataBuilder.php`
     - `tests/Feature/Akademik/RaporPdfDataBuilderTest.php`
   - Working tree: Clean.

2. **Rangkuman Roadmap Multi-Jenjang (Sprint 1–5)**:
   - **Sprint 1**: Subjek Penilaian Polimorfik (`ElemenCp` & `MataPelajaran`).
   - **Sprint 2**: Validasi Asesmen & Defaulting Subjek Tipe.
   - **Sprint 3**: Fondasi Kurikulum & Fase (`Fase`, `FaseDefaultMapping`, `Kelas.fase_id`, `FaseDefaultResolver`).
   - **Sprint 4**: Value Object `AcademicProfile` (Platform Default Preset).
   - **Sprint 5**: Konsolidasi Derivasi Kategori Jenjang Rapor.
   - Seluruh 5 sprint dalam roadmap telah selesai secara penuh dengan total 2230 passed tests.
