# Handoff Log: Fix Konsistensi Ownership Tahun Ajaran vs Lembaga pada Kurikulum Assignment

**Tanggal**: 28 Agustus 2026 (WIB) / 27 Agustus 2026  
**Branch**: `akademik-v2`  
**Spec**: [`.agents/specs/2026-08-27-akademik-fix-tahun-ajaran-lembaga-kurikulum-assignment.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-27-akademik-fix-tahun-ajaran-lembaga-kurikulum-assignment.md)  
**Plan**: [`.agents/plans/2026-08-27-akademik-fix-tahun-ajaran-lembaga-kurikulum-assignment.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-27-akademik-fix-tahun-ajaran-lembaga-kurikulum-assignment.md)  

---

## 1. Apa yang Dikerjakan

Menutup celah *defense-in-depth* pada `KurikulumAssignmentController::store()` di mana sebelumnya `StoreKurikulumAssignmentRequest` hanya memvalidasi `tahun_ajaran_id` dengan rule `exists:tahun_ajaran,id` tanpa memeriksa kepemilikan lembaga, sehingga melalui POST manual dapat tercipta baris `kurikulum_assignment` dengan `lembaga_id` terisi tapi `tahun_ajaran_id` milik lembaga lain.

1. **Implementasi Validasi Ownership Invariant (`app/Http/Controllers/Admin/KurikulumAssignmentController.php`)**:
   - Menambahkan pengecekan berbasis nilai efektif `$lembagaId`:
     ```php
     if ($lembagaId !== null) {
         $tahunAjaranValid = TahunAjaran::whereKey($validated['tahun_ajaran_id'])
             ->where('lembaga_id', $lembagaId)
             ->exists();

         if (! $tahunAjaranValid) {
             return back()->withErrors(['tahun_ajaran_id' => 'Tahun ajaran yang dipilih bukan milik lembaga ini.'])->withInput();
         }
     }
     ```
   - Invariant berbasis nilai menjamin bahwa:
     - Jika `$lembagaId !== null` (baik dipaksa controller untuk admin lembaga, maupun dipilih eksplisit oleh user platform/yayasan), `tahun_ajaran_id` wajib milik lembaga yang sama.
     - Jika `$lembagaId === null` (kasus default nasional), tidak ada validasi ownership tambahan yang dijalankan.

2. **Pengujian Komprehensif Matrix 5 Kasus (`tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`)**:
   - Menambahkan helper `actingAsPlatformKurikulumManager()` dan 4 test baru yang mencakup seluruh matrix pengujian:
     - Matrix #1: Admin lembaga A + tahun ajaran A → sukses (tercover oleh test existing `creates a kurikulum assignment`).
     - Matrix #2: Admin lembaga A + tahun ajaran B → ditolak `assertSessionHasErrors('tahun_ajaran_id')`.
     - Matrix #3: Platform/yayasan + lembaga target A + tahun ajaran A → sukses `assertRedirect`.
     - Matrix #4: Platform/yayasan + lembaga target A + tahun ajaran B → ditolak `assertSessionHasErrors('tahun_ajaran_id')`.
     - Matrix #5: Platform/yayasan + lembaga null (default nasional) + tahun ajaran mana pun → tidak ditolak karena ownership `assertSessionDoesntHaveErrors(['tahun_ajaran_id'])`.
   - **Commit**: [`da9e97c3`](file:///d:/laragon/www/pintera-app/app/Http/Controllers/Admin/KurikulumAssignmentController.php) (`fix(akademik): validasi konsistensi ownership tahun_ajaran vs lembaga pada kurikulum assignment`).

3. **Verifikasi Checkpoint Test Scoped & Dokumentasi**:
   - Menjalankan `php artisan test tests/Feature/Akademik/KurikulumAssignmentControllerTest.php --compact`: **11 passed, 0 failed (29 assertions)**.
   - Mencatat pada `PETA_PENGEMBANGAN.md`.
   - **Commit**: [`f2255bd6`](file:///d:/laragon/www/pintera-app/PETA_PENGEMBANGAN.md) (`docs: catat fix konsistensi ownership tahun ajaran kurikulum assignment`).

---

## 2. Keputusan Penting yang Diambil

1. **Invariant Berbasis Nilai `$lembagaId` Bukan Role Check**:
   - Validasi ownership tidak menggunakan percabangan `if ($user->isPlatform())` melainkan murni mengevaluasi `$lembagaId !== null`. Ini memastikan bahwa siapapun aktornya (admin lembaga, yayasan, atau super admin platform), selama target assignment terikat pada lembaga tertentu, tahun ajaran yang dipilih wajib milik lembaga tersebut.
2. **Kueri Tunggal `TahunAjaran::whereKey(...)->where('lembaga_id', ...)->exists()`**:
   - Kueri ownership dilakukan dalam 1 kueri gabungan yang efisien tanpa instansiasi model yang tidak perlu.
3. **Penyelarasan Fixture Multi-Tenant Yayasan pada Test**:
   - Fixture manager dengan role `scope_level => 'yayasan'` menyelaraskan relasi `yayasan_id` pada `Lembaga` target agar konsisten dengan `TenantScope` global.

---

## 3. Hal yang Perlu Direview Manusia / Claude

- Seluruh 11 test di `KurikulumAssignmentControllerTest.php` lulus 100%.
- Git State:
  - Branch: `akademik-v2`
  - Bersih, semua perubahan di-commit secara rapi dan terdokumentasi.
