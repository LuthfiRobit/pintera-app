# Handoff Log: Audit & Perbaikan Fitur Jadwal Piket Guru & Akses Guru Pengganti

**Tanggal:** 2026-09-11  
**Branch:** `rbac-v2` (Local Only — Tidak dimerge, tidak dipush)  
**Dokumen Terkait:**
- Spec: [`.agents/specs/2026-09-11-jadwal-piket-guru-audit-perbaikan.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-09-11-jadwal-piket-guru-audit-perbaikan.md)
- Plan: [`.agents/plans/2026-09-11-jadwal-piket-guru-audit-perbaikan.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-09-11-jadwal-piket-guru-audit-perbaikan.md)
- Kickoff: [`.agents/kickoff/2026-09-11-jadwal-piket-guru-audit-perbaikan-kickoff.md`](file:///d:/laragon/www/pintera-app/.agents/kickoff/2026-09-11-jadwal-piket-guru-audit-perbaikan-kickoff.md)
- Spec Lama (Konteks): [`.agents/specs/2026-09-06-guru-piket-jurnal-kbm.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-09-06-guru-piket-jurnal-kbm.md)

---

## 1. Apa yang Dikerjakan

Implementasi dan verifikasi penuh atas 8 task perbaikan hasil audit fitur Jadwal Piket Guru:

1. **Task 1 (Timezone Scoped `Asia/Jakarta`)**:
   - Memperbaiki penentuan "hari ini" di 3 titik kritis: `GenerateJadwalPiketHarianAction.php`, `RegenerateJadwalPiketHarianAction.php`, dan `JurnalKbmController::index()`.
   - Mengatasi mismatch browsing tanggal vs hari sungguhan pada `JurnalKbmController::index()`.
   - Menambahkan feature test di `tests/Feature/Guru/JurnalKbmSesiPiketTest.php` untuk memastikan guru piket di pagi hari (00:00–06:59 WIB, saat UTC masih kemarin) tetap dapat mengakses sesi guru lain.
   - Commit: `d2d53ad3`.

2. **Task 2 (Banner "Mode Piket" di Halaman Isi Jurnal)**:
   - Menambahkan banner informatif amber di `resources/views/portals/guru/akademik/jurnal-kbm/show.blade.php` yang secara eksplisit memberitahu guru piket bahwa mereka sedang mengisi sesi milik guru lain (`$sesi->guru->nama_lengkap`), lengkap dengan catatan akuntabilitas data.
   - Eager load relasi `guru` di `JurnalKbmController::show()` untuk mencegah query N+1.
   - Menambahkan test di `tests/Feature/Guru/JurnalKbmPiketAksesTest.php`.
   - Commit: `4754bb47`.

3. **Task 3 (Kalender Read-Only Jadwal Piket Harian Mendatang)**:
   - Mengambil 30 data mendatang `$piketHarianMendatang` di `JadwalPiketMingguanController::index()`.
   - Menambahkan tabel kalender read-only di `resources/views/portals/lembaga/akademik/piket-guru/index.blade.php` dengan badge sumber (`Jadwal Mingguan` vs `Penugasan Khusus`) dan badge status (`Aktif` vs `Diliburkan`).
   - Variabel `$overrides` existing dipertahankan secara aditif untuk form penghapusan override manual.
   - Menambahkan test di `tests/Feature/Admin/JadwalPiketMingguanControllerTest.php`.
   - Commit: `2bfd1497`.

4. **Task 4 (Perlindungan Akuntabilitas `diisi_oleh_guru_id`)**:
   - Memperbaiki `RecordJurnalDanPresensiAction::execute()` menggunakan `array_filter` agar nilai `diisi_oleh_guru_id` yang bernilai `null` (saat guru pemilik mengedit/mensubmit ulang jurnal yang sebelumnya diisi oleh guru piket) tidak menimpa jejak audit guru pengganti yang sudah tersimpan.
   - Menambahkan test di `tests/Feature/Guru/JurnalKbmDiisiOlehGuruTest.php`.
   - Memverifikasi regresi terhadap seluruh 105 test di `tests/Feature/Guru` (semua lulus).
   - Commit: `b9c0d4da`.

5. **Task 5 (Konfirmasi Aksi Standar `confirmDialog`)**:
   - Mengganti dialog `onsubmit="return confirm(...)"` native browser pada form hapus jadwal mingguan dan form hapus override harian di `piket-guru/index.blade.php` menjadi `@submit.prevent="confirmDialog(...)"` konsisten dengan standar UI aplikasi.
   - Commit: `dae6c62d`.

6. **Task 6 (Cakupan Test Regresi `resolveKartu()` Guru Piket)**:
   - Menambahkan test regresi di `tests/Feature/Guru/JurnalKbmResolveKartuTest.php` untuk membuktikan bahwa status kartu KBM yang dilihat guru piket di dashboard jurnal konsisten mencerminkan keterisian sesi.
   - Commit: `c43353ff`.

7. **Task 7 (Standarisasi UI/UX Select)**:
   - Melakukan analisa UI/UX mendalam sesuai panduan kickoff: memeriksa `admin/kelas/_form.blade.php`, memastikan pola `tomSelectPegawai` dengan data lokal `$guruOptions` yang diturunkan via `@php` dari `$guruList` (bukan controller).
   - Mengganti select guru di `index.blade.php`, `create.blade.php`, dan `edit.blade.php` menjadi `tomSelectPegawai` (searchable, subtext NIP/NUPTK/jenis PTK).
   - Mempertahankan `<x-select>` untuk dropdown opsi singkat/tetap (`hari` dan `semester_id`).
   - Rebuild asset Vite via `npm.cmd run build` dan verifikasi visual dev server via browser subagent.
   - Commit: `e5fcfa93`.

8. **Task 8 (Regresi Penutup & Verifikasi Full Suite)**:
   - Uji 15 test suite terkait (76 tests, 176 assertions): PASSED 100%.
   - Laravel Pint dirty files: PASSED.
   - Full Test Suite `php artisan test --compact` (3,175 tests): 3 failed (pre-existing), 3,172 passed, 0 regresi baru.
   - Checklist plan di `.agents/plans/2026-09-11-jadwal-piket-guru-audit-perbaikan.md` ditandai selesai dan dicommit (`68b47953`).

---

## 2. Keputusan Penting yang Diambil

1. **Timezone Scoped vs Global**:
   - Mengikuti instruksi ketat kickoff dan spec §3, perbaikan timezone dibatasi hanya pada titik penentuan hari piket (`now('Asia/Jakarta')`). Perubahan global `APP_TIMEZONE` di `config/app.php` sengaja tidak dilakukan karena blast radius yang luas dan memerlukan audit terpisah.

2. **Pola Array Filter pada Action Akuntabilitas (Task 4)**:
   - Di `RecordJurnalDanPresensiAction`, parameter `$data['diisi_oleh_guru_id']` difilter dengan `array_filter(..., fn ($v) => ! is_null($v))`. Dengan cara ini, payload update tidak menyertakan key `diisi_oleh_guru_id` jika nilainya null, sehingga nilai existing di database tetap aman saat guru pemilik mengedit kembali jurnal.

3. **Sumber Data `$guruOptions` pada Blade (Task 7)**:
   - Sesuai temuan saat drafting plan, controller hanya mengirim `$guruList`. Pemetaan ke array opsi yang dibutuhkan `tomSelectPegawai` (`id`, `nama`, `subtext`) diturunkan secara lokal di dalam Blade via `@php` block, menjaga controller tetap lean dan konsisten dengan halaman kelas/rombel.

4. **Eksekusi Test Suite Tunggal**:
   - Full test suite dijalankan secara sequential tanpa proses pengujian lain yang berjalan bersamaan untuk mencegah konflik lock pada database SQLite test.

---

## 3. Hal yang Masih Perlu Direview Manusia / Claude

1. **Status Git**:
   - Cabang: `rbac-v2`
   - Status: 8 commit baru telah dibuat secara lokal. Belum dimerge dan belum dipush ke remote (sesuai instruksi: keputusan terpisah di tangan user).
2. **Item yang Sengaja Di-defer (Out of Scope)**:
   - Audit timezone global sistem (`config/app.php` / `APP_TIMEZONE`).
   - Validasi tumpang tindih semester (semester overlap) pada jadwal mingguan.
   - Pessimistic locking (`lockForUpdate`) untuk race-condition generasi piket.
   - Fitur Fase 2 dari spec lama (seperti model `LaporanPiket`).
3. **Hasil Full Test Suite**:
   - Total: 3,175 test (8,544 assertions).
   - 3 Test Gagal (Semuanya Pre-existing yang sudah dikonfirmasi):
     1. `Tests\Unit\M3DemoDataSeederTest > it seeds a spread of pendaftaran states across K-9 institutions for man…`
     2. `Tests\Unit\M3DemoDataSeederTest > it is idempotent when the full DatabaseSeeder is run twice`
     3. `Tests\Feature\Akademik\SubjekTenantValidationTest > it rejects a komponen penilaian whose mata_pelajaran…`
   - Tidak ada kegagalan baru atau regresi pada modul apa pun.
