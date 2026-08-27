# Handoff Log: RaporCalculationService Type-Aware + Fix Key-Mismatch (Prioritas 2)

**Tanggal**: 27 Agustus 2026  
**Branch**: `akademik-v2`  
**Git Commits**:
- `07a0cbe7` — `feat(akademik): tambah DTO RekapNilaiSel`
- `9c5a547c` — `feat(akademik): RaporCalculationService type-aware (numeric/predicate/narrative) + fix keyBy composite key`
- `45be35d5` — `fix(akademik): perbaiki key-mismatch matriks rekap rapor, render badge per tipe assessment`
- `0962ca0f` — `fix(akademik): adapt RaporPdfDataBuilder and pdf rapor views for RekapNilaiSel and composite key`
- `803528bc` — `docs: tandai Prioritas 2 Roadmap Kurikulum Dinamis SELESAI`
- `70332cf4` — `docs(plan): tandai seluruh checklist langkah implementasi Prioritas 2 selesai`

**Spec**: [`.agents/specs/2026-08-27-akademik-rapor-calculation-type-aware.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-27-akademik-rapor-calculation-type-aware.md)  
**Plan**: [`.agents/plans/2026-08-27-akademik-rapor-calculation-type-aware.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-27-akademik-rapor-calculation-type-aware.md)  
**Test Status**: **2282 passed, 4 skipped, 0 failed (6255 assertions)**

---

## 1. Apa yang dikerjakan

1. **DTO `RekapNilaiSel`** (`app/Domains/Akademik/DataTransferObjects/RekapNilaiSel.php`):
   - `final readonly class` dengan 3 properti: `assessmentType: AssessmentType`, `label: string`, `tuntas: ?bool`.
   - Tidak ada properti `value` (mengikuti konvensi DTO murni & konsistensi isolasi agregasi numerik).
   - Dilengkapi unit test di `tests/Unit/DataTransferObjects/RekapNilaiSelTest.php`.

2. **Refactor & Perbaikan `RaporCalculationService`** (`app/Domains/Akademik/Services/RaporCalculationService.php`):
   - Perbaikan bug key-mismatch: `$subjekList` di-`keyBy(fn ($s) => SubjekPenilaianKey::dari($s))` sebagai langkah akhir tanpa `->values()`.
   - Agregasi type-aware per sel per siswa dengan urutan precedence: `numeric > predicate > narrative`.
   - **Numeric**: Rata-rata terbobot komponen numerik, menghitung `tuntas: bool` berdasar `config('akademik.ambang_tuntas')`.
   - **Predicate**: Modus (frekuensi terbanyak) dengan tie-breaker hierarki PAUD: `BSB=4 > BSH=3 > MB=2 > BB=1`. Sel bernilai `null` jika tidak ada entri predikat terisi.
   - **Narrative**: Format label rasio completion `"{terisi}/{total}"`, di mana `terisi` mendeteksi `trim($catatan ?? '') !== ''` dan `total` dihitung pra-looping per subjek dalam semester. Sel bernilai `null` jika `$total === 0`.
   - `classAvg` dan `highestScore` dihitung secara independen dari array bantu numerik mentah (`$rekapNumericMentah`), mengabaikan sel predikat/naratif dan tetap bernilai `null` jika kelas hanya memiliki komponen non-numerik.

3. **Perbaikan View Matriks Rekap Rapor & Persetujuan**:
   - `resources/views/portals/lembaga/akademik/rapor/_hasil.blade.php`:
     - Loop header & body memakai `$subjekKey => $mapel`.
     - Lookup `$rekapNilai[$siswa->id][$subjekKey]`.
     - Badge emerald/amber untuk numerik tuntas/belum tuntas, abu-abu untuk predikat & naratif, dan `—` untuk sel kosong.
     - Perhitungan "Rata-Rata Umum" siswa hanya menyertakan sel numerik bertuntas valid (`$sel->tuntas !== null`).
   - `resources/views/portals/lembaga/rapor/persetujuan/show.blade.php`:
     - Loop header & body diselaraskan ke composite `$subjekKey`.
     - Badge disesuaikan dengan tipe assessment.

4. **Konsistensi Rapor PDF & Builder**:
   - `app/Domains/Akademik/Services/RaporPdfDataBuilder.php`:
     - Menangani perhitungan rata-rata tahunan (Ganjil + Genap) dengan mengekstrak nilai float dari DTO `RekapNilaiSel` ketika bertipe numerik.
   - `resources/views/pdf/rapor/sd.blade.php`, `smp-sma.blade.php`, `smk.blade.php`, `paud.blade.php`:
     - Menggunakan `SubjekPenilaianKey::dari($mapel)` untuk mengakses `$rekapNilai`, `$narasiPerMapel`, dan `$nilaiRataRataTahunan`.
     - Menghindari percampuran sintaks inline `@php(...)` dan blok Blade.

5. **Pengujian & Regresi**:
   - Unit test baru `RaporCalculationServiceTypeAwareTest` (8 test skenario komprehensif).
   - Retrofit unit test `RaporCalculationServiceTest` dan `RaporCalculationServiceAssessmentTypeTest`.
   - Retrofit feature test `RaporCalculationCompositeKeyTest`.
   - Penguatan assertion di `RaporControllerTest` dan `RaporPersetujuanControllerTest` untuk menjamin tidak ada regresi tampilan sel per-mapel.
   - Verifikasi penuh test suite: 2282 tests passed (6255 assertions).

---

## 2. Keputusan Penting yang Diambil

1. **Struktur DTO `RekapNilaiSel` Tanpa Properti `value`**:
   - DTO hanya bertindak sebagai wadah presentasi hasil evaluasi sel (`label`, `tuntas`, `assessmentType`).
   - Agregasi kelas (`classAvg`, `highestScore`) sengaja dihitung di service dari array float mentah terpisah sehingga tidak ada ketergantungan tipe parsial pada DTO.

2. **Penanganan Kasus Tidak Ada Komponen Narrative pada Subjek**:
   - Jika subjek dalam semester tidak memiliki komponen naratif sama sekali (`$total === 0`), `resolveNarrative()` mengembalikan `null` (bukan DTO kosong atau `"0/0"`).

3. **Konsistensi PDF Report Builder**:
   - Perubahan format kembalian `rekapNilai` pada `RaporCalculationService` dari array `float|null` menjadi array `?RekapNilaiSel` langsung diintegrasikan ke `RaporPdfDataBuilder` dan template PDF terkait untuk menjaga keutuhan pipeline cetak rapor seluruh jenjang (SD, SMP/SMA, SMK, PAUD).

---

## 3. Hal yang Perlu Direview Manusia / Claude

1. **Git State**:
   - Branch saat ini: `akademik-v2` (belum di-merge ke main, sesuai preferensi user agar proses merge/finishing dilakukan sendiri).
   - Semua perubahan sudah di-commit dengan pesan commit deskriptif dan terstruktur.
2. **Prioritas Berikutnya di Roadmap (`PETA_PENGEMBANGAN.md`)**:
   - Prioritas 1 & 2 telah **SELESAI**.
   - Prioritas 3 yang menanti adalah: *Kelulusan/Rapor Akhir untuk PAUD/TK + keputusan sadar soal SLB*.
