# Handoff Log: Fix lembaga_id Basi pada Update Komponen Penilaian

**Tanggal**: 28 Agustus 2026 (WIB) / 27 Agustus 2026  
**Branch**: `akademik-v2`  
**Spec**: [`.agents/specs/2026-08-27-akademik-fix-lembaga-id-stale-komponen-penilaian.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-27-akademik-fix-lembaga-id-stale-komponen-penilaian.md)  
**Plan**: [`.agents/plans/2026-08-27-akademik-fix-lembaga-id-stale-komponen-penilaian.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-27-akademik-fix-lembaga-id-stale-komponen-penilaian.md)  

---

## 1. Apa yang Dikerjakan

Menutup celah inkonsistensi data pada pemindahan `semester_id` komponen penilaian lintas-lembaga (khususnya oleh aktor level yayasan):

1. **Recompute `lembaga_id` di `UpdateKomponenPenilaianAction` (`app/Domains/Akademik/Actions/Penilaian/UpdateKomponenPenilaianAction.php`)**:
   - Menambahkan import `use App\Models\Semester;`.
   - Di dalam blok `if (! $dipakai && $data->subjekType !== null && $data->subjekId !== null && $data->semesterId !== null)`, menambahkan:
     ```php
     $komponen->lembaga_id = Semester::findOrFail($data->semesterId)->lembaga_id;
     ```
   - Ini menyelaraskan perilaku update dengan `CreateKomponenPenilaianAction` di mana `lembaga_id` selalu diderivasi dari relasi `Semester` terkait (karena subjek seperti `ElemenCp` tidak memiliki kolom `lembaga_id` sendiri).

2. **Pengujian TDD & Reproduksi Bug Lintas-Lembaga (`tests/Feature/Admin/KomponenPenilaianCrudTest.php`)**:
   - Menambahkan helper `actingAsYayasanKomponenManager(Yayasan $yayasan): User` (aktor level yayasan tanpa `session('active_lembaga_id')`).
   - Menambahkan 3 test baru:
     - `it('recomputes lembaga_id to follow the new semester for elemen_cp when a yayasan actor moves it across lembaga')` (terbukti gagal sebelum fix, lulus setelah fix).
     - `it('recomputes lembaga_id to follow the new semester for mata_pelajaran when a yayasan actor moves it across lembaga')` (terbukti gagal sebelum fix, lulus setelah fix).
     - `it('does not touch lembaga_id when updating a komponen without changing semester_id')` (regresi negatif, tetap lulus).
   - Memastikan 4 test update existing (baris 254-352) tetap lulus tanpa modifikasi assertion.
   - **Commit**: [`a47c6718`](file:///d:/laragon/www/pintera-app/app/Domains/Akademik/Actions/Penilaian/UpdateKomponenPenilaianAction.php) (`fix(akademik): recompute lembaga_id saat semester_id berubah pada update komponen penilaian`).

3. **Verifikasi Checkpoint Test Scoped & Dokumentasi**:
   - Menjalankan `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php --compact`: **34 passed, 0 failed (99 assertions)**.
   - Mencatat pada `PETA_PENGEMBANGAN.md`.
   - **Commit**: [`390411c7`](file:///d:/laragon/www/pintera-app/PETA_PENGEMBANGAN.md) (`docs: catat fix recompute lembaga_id komponen penilaian di peta pengembangan`).

---

## 2. Keputusan Penting yang Diambil

1. **Recompute Diletakkan di Action Saja**:
   - Perubahan hanya dilakukan di level Action (`UpdateKomponenPenilaianAction`) saat `semester_id` berubah, tanpa menambah guard cross-lembaga buatan di controller untuk `elemen_cp`, menjaga arsitektur tetap bersih dan meminimalisir risiko side effect.
2. **Kesesuaian Tipe Properti DTO**:
   - Menggunakan tipe `int` literal (`bobot: 100`, `kktpMinimal: null`) sesuai constructor aktual `KomponenPenilaianData`.

---

## 3. Hal yang Perlu Direview Manusia / Claude

- Seluruh 34 test di `KomponenPenilaianCrudTest.php` lulus 100%.
- Git State:
  - Branch: `akademik-v2`
  - Bersih, semua perubahan di-commit secara rapi dan terdokumentasi.
