# Handoff Log: Sprint 2 (Assessment Type / Tipe Penilaian)

- **Tanggal Eksekusi**: 26 Agustus 2026
- **Branch**: `akademik-v2`
- **Spec Reference**: `.agents/specs/2026-08-26-akademik-multi-jenjang-sprint2.md`
- **Plan Reference**: `.agents/plans/2026-08-26-akademik-multi-jenjang-sprint2.md`

---

## 1. Apa yang dikerjakan

Semua 6 task dalam Plan Sprint 2 telah dieksekusi secara lengkap dan atomic:

1. **Task 1: Skema Database & Enum Domain Tipe Penilaian**
   - Migration `2026_08_26_110000_add_assessment_type_to_komponen_penilaian_table.php` ditambahkan (`assessment_type` string default `'numeric'`).
   - Backed enum `AssessmentType` (`numeric`, `narrative`, `predicate`) dan `PredikatPaud` (`BB`, `MB`, `BSH`, `BSB`).
   - Cast `$fillable` & `casts()` pada model `KomponenPenilaian` & `NilaiSiswa`.
   - Commit: `4773183d`

2. **Task 2: DTO, Action, & Form Request untuk CRUD Komponen Penilaian**
   - DTO `KomponenPenilaianData` & `UpdateKomponenPenilaianData` mendukung `assessmentType`.
   - `CreateKomponenPenilaianAction` menghitung default domain-layer (`elemen_cp` -> `narrative`, `mata_pelajaran` -> `numeric`) HANYA jika input `null`.
   - `UpdateKomponenPenilaianAction` mengunci `assessment_type` jika komponen sudah dipakai (`$dipakai`).
   - Validasi `Rule::enum(AssessmentType::class)` pada 4 Form Request (`StoreKomponenPenilaianRequest`, `StoreKomponenPenilaianSendiriRequest`, `UpdateKomponenPenilaianRequest`, `UpdateKomponenPenilaianSendiriRequest`).
   - Commit: `f2bc01b1`

3. **Task 3: UI Form Komponen Penilaian (Portal Admin Lembaga & Guru)**
   - Input dropdown "Tipe Penilaian" ditambahkan di 4 file Blade form komponen penilaian (`create` & `edit` admin/guru).
   - Pre-select otomatis via Alpine `@change` radio `subjek_type`. Disabled di form edit ketika `$dipakai` true.
   - Commit: `a857bb31`

4. **Task 4: Input Nilai Guru — Validasi Kondisional 2-Layer + UI per Tipe**
   - **RED test step dipastikan FAILED dulu** sebelum implementasi (`UpdateNilaiSiswaValidationTest`).
   - Validasi HTTP `UpdateNilaiSiswaRequest` mengecek `assessment_type` per sel `siswa x komponen` secara dinamis dari DB.
   - Guard domain `SimpanNilaiSiswaAction` memaksa invariant per tipe (prohibited fields di-reset ke `null`).
   - Dynamic cell rendering di `resources/views/portals/guru/akademik/asesmen/show.blade.php` (numeric: input angka + catatan, predicate: select BB/MB/BSH/BSB + catatan, narrative: textarea catatan).
   - Commit: `59134fa3`

5. **Task 5: Consumer Audit Fix — Rapor, Stats, & Export PDF**
   - `DashboardStatsService::statistikProgressRaporKelas()` menghitung progress dengan mendukung subjek `elemen_cp` dan non-numeric `assessment_type` (`predikat` / `catatan`).
   - Template PDF Rapor PAUD (`resources/views/pdf/rapor/paud.blade.php`) merender predikat dan catatan langsung untuk subjek `elemen_cp`.
   - Commit: `160997c1`

6. **Task 6: Verifikasi & Handoff Log**
   - `migrate:fresh --seed` berhasil dijalankan tanpa error.
   - Full test suite Pest diverifikasi.

---

## 2. Keputusan Penting yang Diambil

- **Dynamic Cell Validation**: Form request `UpdateNilaiSiswaRequest` menyusun aturan validasi per `nilai.{siswaId}.{komponenId}` berdasarkan query `assessment_type` per komponen. Pola ini terbukti berhasil dan lulus tes tanpa butuh callback `Validator::make` manual.
- **Layer 2 Invariant Enforcement**: `SimpanNilaiSiswaAction` selalu membersihkan payload sesuai `assessment_type` komponen dari DB (misal: jika komponen `numeric`, `predikat` selalu di-set `null`), sehingga manipulasi payload liar dari client diabaikan total.
- **Preservasi DashboardStatsService Signature**: Signature `statistikProgressRaporKelas(Kelas $kelas)` dipertahankan tanpa mengubah argumen, dan query-nya diperbaiki agar mencakup seluruh subjek & tipe penilaian.

---

## 3. Hal yang Perlu Direview Manusia / Agent Selanjutnya

- Baseline Sprint 2 selesai dengan 5 commit fitur pada branch `akademik-v2`.
- Siap dilanjutkan ke Sprint 3 (Deskripsi Capaian Kompetensi & Formula Rapor) sesuai roadmap spec.
