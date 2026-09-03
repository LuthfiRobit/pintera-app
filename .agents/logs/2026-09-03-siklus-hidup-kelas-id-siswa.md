# Handoff Log: Siklus Hidup `kelas_id` Siswa Saat Status Berubah

**Tanggal**: 2026-09-03  
**Branch**: `akademik-v2` (tetap di branch ini sesuai instruksi user)  
**Spec**: [`.agents/specs/2026-09-03-siklus-hidup-kelas-id-siswa.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-09-03-siklus-hidup-kelas-id-siswa.md)  
**Plan**: [`.agents/plans/2026-09-03-siklus-hidup-kelas-id-siswa.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-09-03-siklus-hidup-kelas-id-siswa.md)  

---

## 1. Apa yang Dikerjakan

Menutup akar masalah `kelas_id` siswa yang tidak pernah dibersihkan saat status berubah ke non-aktif (`Lulus`, `Pindah`, `Keluar`) melalui 10 task bertahap TDD:

1. **Task 1 (`8654c8ce`)**: Menambahkan guard validasi di `SiswaController::validateSiswa()` yang melempar `ValidationException` jika admin mencoba menempatkan `kelas_id` untuk siswa yang berstatus non-aktif melalui form edit siswa.
2. **Task 2 (`81732d5f`)**: Membuat migration `2026_09_03_000001_add_kelas_terakhir_id_to_siswa_table.php`:
   - Menambahkan kolom `kelas_terakhir_id` (`BIGINT UNSIGNED NULL`) berelasi ke `kelas.id`.
   - Menjalankan backfill 1 statement raw SQL memindahkan `kelas_id` ke `kelas_terakhir_id` dan mengosongkan `kelas_id` untuk semua siswa non-aktif yang sudah ada.
   - Mengubah referential action `siswa_kelas_id_foreign` menjadi `ON DELETE RESTRICT` (mengatasi batasan MySQL 8.0 Error 3823 terkait referential action pada CHECK constraint).
   - Menambahkan CHECK constraint `chk_siswa_kelas_id_null_saat_nonaktif` (`CHECK (status = 'aktif' OR kelas_id IS NULL)`).
   - Menambal test factory yang melanggar constraint (`SesiPembelajaranGeneratorTest` dan `SubmitPengajuanRaporActionTest`).
3. **Task 3 (`b87cf5c9`)**: Membuat `App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction` yang mengelola snapshot `kelas_id` ke `kelas_terakhir_id` saat transisi keluar dari Aktif, pemulihan otomatis saat kembali ke Aktif, idempotency, dan sinkronisasi `is_active` pada User. Menginjeksi action ini ke `SiswaController::updateStatus()`.
4. **Task 4 (`2211e741`)**: Menyempurnakan test `SesiPembelajaranGeneratorTest` dan `SubmitPengajuanRaporActionTest` agar menggunakan `UpdateStatusSiswaAction` (alur realistis) alih-alih manipulasi factory mentah.
5. **Task 5 (`5fc7d56d`)**: Menambahkan relasi `kelasTerakhir()` dan accessor `kelas_efektif` di model `Siswa` (`app/Models/Siswa.php`), menambahkan `kelas_terakhir_id` ke `$fillable` dan `SiswaFactory`, serta menerapkan `$siswa->kelas_efektif` dengan `abort_if($kelas === null, 404)` pada `RaporPdfDataBuilder`.
6. **Task 6 (`6b7562b6`)**: Memperbarui tampilan daftar siswa (`_daftar.blade.php`) dan tab profil (`tabs/profil.blade.php`) agar menampilkan nama kelas efektif beserta penanda visual `(kelas terakhir)` untuk siswa non-aktif, serta menambahkan eager loading `kelasTerakhir` di `SiswaController::index()`.
7. **Task 7 (`f1dbe645`)**: Menambahkan frontend guard di `_form.blade.php` sehingga field `kelas_id` otomatis berstatus `disabled` dan menampilkan petunjuk khusus jika sedang mengedit siswa non-aktif.
8. **Task 8 (`74e4eb92`)**: Menambahkan regression test pada `KenaikanKelasControllerTest` yang membuktikan bahwa siswa non-aktif (`Keluar`) tidak ikut terangkat/berpindah saat proses kenaikan kelas dijalankan (mewakili titik-titik query "gratis" lainnya).
9. **Task 9 (`2a20ade7`)**: Menambahkan regression test pada `RaporControllerTest` untuk memastikan siswa non-aktif tidak muncul pada listing rapor catatan wali kelas dan tidak merusak indeks urutan navigasi siswa sebelumnya / berikutnya (`siswaSebelumnya`/`siswaBerikutnya`).
10. **Task 10**: Menjalankan pengujian full test suite final. Hasil: **2741 passed (7488 assertions)**, 0 failures. Memastikan code format bersih via `vendor/bin/pint --dirty --format agent`.

---

## 2. Keputusan Penting yang Diambil

1. **Penyesuaian MySQL 8.0 FK Referential Action (`ON DELETE RESTRICT`)**:
   - Saat menjalankan migration Task 2, MySQL 8.0 melempar `Error 3823: Column 'kelas_id' cannot be used in a check constraint: needed in a foreign key constraint 'siswa_kelas_id_foreign' referential action` karena FK lama memakai `ON DELETE SET NULL`.
   - Sesuai keputusan interaktif bersama user, FK `siswa_kelas_id_foreign` diubah menjadi `ON DELETE RESTRICT` sebelum `ADD CONSTRAINT`. Ini 100% aman karena `KelasController` tidak menyediakan operasi `destroy()` (penghapusan kelas tidak pernah ada di UI).
2. **Perbaikan Test Pendahulu `SubmitPengajuanRaporActionTest`**:
   - Test dari commit `13d97610` sengaja membuat siswa `Keluar` dengan `kelas_id` terisi. Begitu CHECK constraint aktif, kombinasi ini ditolak di level database. Test diperbarui untuk menyimulasikan transisi status via `UpdateStatusSiswaAction` (atau status `Keluar` dengan `kelas_id` null), sehingga tetap menguji bahwa submission rapor wali kelas tidak diblokir oleh mantan siswa.
3. **Penyelarasan Query Navigasi Test Task 9**:
   - Model `Siswa` tidak memiliki kolom `nama_lengkap` langsung di tabel `siswa` (kolom tersebut berada di tabel `persons` via relasi). Test navigasi Task 9 diselaraskan dengan memanfaatkan instance siswa yang dikembalikan langsung oleh helper `siapkanWaliKelasUntukRapor()`.

---

## 3. Catatan Serah-Terima ke Sesi Keuangan (`keuangan-v2`)

> **PERHATIAN UNTUK SESI KEUANGAN:**  
> File `app/Domains/Keuangan/Services/JenisTagihanSasaranMatcher.php` dan `TagihanBillingGenerator.php` **SAMA SEKALI TIDAK DISENTUH** pada paket ini karena sedang aktif dikerjakan secara paralel di branch `keuangan-v2`.  
>
> **Temuan Audit untuk Ditindaklanjuti di Sesi Keuangan:**  
> Base query pada `JenisTagihanSasaranMatcher::resolveTargetSiswa()` dan `countTotalSiswaPool()` saat ini hanya memfilter `lembaga_id` tanpa memfilter status siswa. Jika sasaran tagihan berbasis non-kelas (mis. "semua siswa" atau tingkat global), siswa non-aktif (`Lulus`/`Pindah`/`Keluar`) masih berpotensi terhitung/tertagih meskipun `kelas_id` mereka sudah `NULL`.  
> **Rekomendasi:** Tambahkan `->where('status', \App\Enums\StatusSiswa::Aktif->value)` pada base query tersebut di sesi Keuangan. Perbaikan ini independen dari modul Akademik.

---

## 4. Hal yang Perlu Direview Manusia / State Git

- **Git Branch**: `akademik-v2`
- **Status Git**: Bersih, semua perubahan telah di-commit secara modular dan teratur.
- **Hasil Verifikasi**: 2741 test lolos (100% pass, 0 error).
- **Audit View Konsumen**: 3 tempat konsumen tampilan (`RaporPdfDataBuilder`, `_daftar.blade.php`, dan `profil.blade.php`) seluruhnya telah seragam menggunakan `$siswa->kelas_efektif`.
