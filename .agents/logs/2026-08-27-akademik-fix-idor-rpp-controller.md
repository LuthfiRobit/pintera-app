# Handoff Log: Fix Kritis IDOR Lintas-Guru pada RppController

**Tanggal**: 27 Agustus 2026  
**Branch**: `akademik-v2`  
**Spec**: [`.agents/specs/2026-08-27-akademik-fix-idor-rpp-controller.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-27-akademik-fix-idor-rpp-controller.md)  
**Plan**: [`.agents/plans/2026-08-27-akademik-fix-idor-rpp-controller.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-27-akademik-fix-idor-rpp-controller.md)  

---

## 1. Apa yang Dikerjakan

Menutup celah keamanan kritis **IDOR (Insecure Direct Object Reference)** lintas-guru pada modul RPP (`RppController`) dan menambahkan defense-in-depth cross-check lembaga eksplisit pada `VerifyRppAction`:

1. **Perbaikan Baseline Test Fixture (`tests/Feature/Akademik/RppWorkflowTest.php`)**:
   - Menambahkan `wali_kelas_guru_id` dan fixture `JadwalPelajaran` di `beforeEach` agar test existing selaras dengan validasi kepemilikan dan kombinasi mengajar baru.
   - Commit: `62761e2e` (`test(akademik): lengkapi fixture wali_kelas_guru_id di RppWorkflowTest`).

2. **Ownership Check pada `update()`, `submit()`, `destroy()`, dan `download()` (`RppController.php`)**:
   - Menambahkan private method `authorizeMilikGuru(Rpp $rpp): void` yang memverifikasi `$guru !== null && $rpp->guru_id === $guru->id` (abort 403 jika tidak cocok).
   - Memasang `authorizeMilikGuru($rpp)` pada method `update()`, `submit()`, dan `destroy()`.
   - Mengubah guard `download()` agar hanya mengizinkan guru pemilik sah ATAU user yang memiliki permission verifikator `rpp.verify` di lembaga yang sama.
   - Commit: `9c2c1310` (`fix(akademik): cegah IDOR lintas-guru pada update/submit/destroy/download RPP`).

3. **Verifikasi Kombinasi Mengajar pada `store()` (`RppController.php`)**:
   - Memvalidasi bahwa jika pembuat adalah guru (`$guru !== null`), untuk RPP bermapel wajib memiliki relasi `JadwalPelajaran` pada `(guru_id, kelas_id, mata_pelajaran_id, semester_id)`.
   - Untuk RPP tematik (`mata_pelajaran_id` null), memvalidasi bahwa guru adalah `wali_kelas_guru_id` kelas terkait.
   - Memperbaiki fixture di `StoreRppRequestKelasSemesterTest.php` (`$kelasTahunA->update(['wali_kelas_guru_id' => $guru->id])`).
   - Commit: `07339216` (`fix(akademik): verifikasi kombinasi mengajar guru pada store RPP`).

4. **Cross-Check Lembaga Eksplisit pada `VerifyRppAction` (`VerifyRppAction.php`)**:
   - Menambahkan parameter `int $verifierLembagaId` pada method `VerifyRppAction::execute(...)`.
   - Memvalidasi `(int) $rpp->lembaga_id !== $verifierLembagaId` dan melempar `ValidationException` jika berbeda.
   - Memperbarui satu-satunya pemanggil di `RppController::verify()`.
   - Commit: `e01f8886` (`fix(akademik): tambah cross-check lembaga eksplisit di VerifyRppAction`).

5. **Verifikasi Full Test Suite & Dokumentasi**:
   - Menjalankan `php artisan test --compact`: **2373 passed, 4 skipped, 0 failed (6498 assertions)**.
   - Mencatat resolusi pada `PETA_PENGEMBANGAN.md`.
   - Commit: `fbfac254` (`docs: catat fix kritis IDOR RppController`).

---

## 2. Keputusan Penting yang Diambil

1. **Dual Actor Guarding pada `download()`**:
   - Method `download()` sengaja tidak menggunakan `authorizeMilikGuru()` saja, melainkan `($isPemilik || auth()->user()->can('rpp.verify'))`. Ini menjamin Waka Kurikulum / Kepala Sekolah tetap dapat mengunduh dan menelaah berkas RPP yang diajukan untuk verifikasi tanpa diblokir oleh ownership check.
2. **Bypass Verifikasi Kombinasi Mengajar untuk Aktor Non-Guru**:
   - Pada `RppController::store()`, pengecekan kombinasi jadwal hanya dijalankan jika `$guru !== null`. Jika admin/operator tanpa relasi `Guru` membuatkan draf RPP atas nama guru lain, pengecekan ini di-bypass sehingga fleksibilitas administratif tetap terjaga.
3. **Defense-in-Depth pada `VerifyRppAction`**:
   - Walaupun route-model-binding Laravel secara otomatis mengisolasi query via `TenantScope` (mengembalikan 404), penambahan pengecekan eksplisit di `VerifyRppAction` menjamin keamanan tetap kokoh jika di masa mendatang ada eksekusi aksi via job, command, atau perubahan model binding.

---

## 3. Hal yang Perlu Direview Manusia / Claude

- **Pemisahan Role Guru vs Admin**: Seluruh flow di `RppControllerIdorTest.php` dan `RppWorkflowTest.php` mengonfirmasi bahwa batasan guru vs verifikator telah bekerja sesuai spesifikasi RBAC.
- **Git State**:
  - Branch: `akademik-v2`
  - Status: Semua perubahan sudah di-commit secara atomic per task.
