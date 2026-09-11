# Kickoff: Audit & Perbaikan Modul Jadwal Pelajaran

**Base commit**: `0ceff944` (`docs(jadwal-pelajaran): tulis implementation plan, 5 putaran self-review...`)
**Branch**: `rbac-v2` (TETAP di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

Modul Jadwal Pelajaran punya fondasi arsitektur baik (filter tersinkron URL, dual-view Matriks/Daftar, modal duplikasi antar kelas dengan anti-bentrok), tapi audit menemukan **1 bug fungsional nyata**: tombol "+ Tambah Slot Jadwal" mengarah ke URL `/undefined?...` karena `createUrlBase` tidak pernah diinisialisasi di state Alpine, walau sudah dikirim dari Blade. Backend (`JadwalPelajaranController`, `DuplicateJadwalAction`) sudah diverifikasi solid — **TIDAK ADA perubahan controller/domain layer di plan ini sama sekali**.

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-12-jadwal-pelajaran-audit-perbaikan.md` — spec lengkap, 5 putaran self-review, kode current-vs-fix KONKRET untuk 9 kelompok temuan (§2.1-§2.9), plus 2 koreksi penting terhadap laporan audit awal (§1, §4): "feedback duplikasi rinci" TERNYATA SUDAH ADA end-to-end (jangan dikerjakan ulang), dan `scopeHeaderData()`/kolom `is_shared` TERNYATA SUDAH ADA (bukan tidak tersedia seperti dugaan awal).
2. `.agents/plans/2026-09-12-jadwal-pelajaran-audit-perbaikan.md` — 9 task, 5 putaran self-review. SEMUA task berisi kode lengkap siap salin.

## 3. ⚠️ Peringatan Struktural Plan Ini — Banyak File Disentuh Berkali-Kali

Mirip pola siklus Pola Jam sebelumnya: beberapa file disentuh oleh BANYAK task berbeda dan **WAJIB dikerjakan sekuensial**, TIDAK BOLEH paralel satu sama lain:

- **`resources/js/jadwal-pelajaran-filter.js`**: Task 1, 3, 5 — sekuensial.
- **`resources/views/portals/lembaga/akademik/jadwal-pelajaran/index.blade.php`**: Task 5, 7 — sekuensial.
- **`resources/views/portals/lembaga/akademik/jadwal-pelajaran/_daftar.blade.php`**: Task 2, 3, 4, 6 — sekuensial.
- **`resources/views/portals/lembaga/akademik/jadwal-pelajaran/_matrix-roster.blade.php`**: Task 2, 3 — sekuensial.

**HANYA Task 8** (`create.blade.php`/`edit.blade.php`, file berbeda total) **aman diparalelkan** dengan task lain kalau memakai subagent-driven-development.

**Task 9 (regression sweep) WAJIB paling akhir**, setelah SEMUA task lain (termasuk Task 8) selesai.

Plan sudah menandai eksplisit di tiap task yang mengedit file yang sudah disentuh task sebelumnya: **"Baca ulang file TERKINI" sebagai Step 1** — WAJIB dipatuhi, karena kode "cari blok ini" yang dikutip plan mengasumsikan state file SETELAH task-task sebelumnya, bukan file original sebelum plan ini mulai.

## 4. ⚠️ Task 5 — Titik Paling Berisiko Regresi di Seluruh Plan

**Task 5** (filter Semester diubah dari `<select>` polos jadi TomSelect) **BUKAN task biasa** — method `gantiTahunAjaran()` yang SUDAH ADA memanipulasi `<select>` semester langsung lewat `innerHTML`/`appendChild`. Begitu elemen itu dikelola TomSelect, manipulasi DOM manual TIDAK akan terlihat TomSelect (TomSelect membungkus elemen asli, tidak "mengamati" perubahan DOM di luar API-nya sendiri).

Plan Task 5 Step 5 SUDAH menulis versi LENGKAP `gantiTahunAjaran()` yang disesuaikan (pakai `this.semesterTomSelect.addOption()`+`refreshOptions()` alih-alih manipulasi DOM manual) — **implementer WAJIB menerapkan versi itu SELURUHNYA, bukan cuma menambah TomSelect baru di atas kode lama**. Kalau terlewat, filter Semester akan **terlihat kosong** walau opsi sebenarnya sudah ter-fetch — bug yang TIDAK akan terdeteksi test `assertSee` biasa (karena itu soal *state JS runtime setelah interaksi*, bukan markup awal).

**Task 5 Step 8 mewajibkan verifikasi manual di browser** (bukan opsional) — satu-satunya langkah verifikasi manual wajib di seluruh plan ini. Pelaksana WAJIB benar-benar membuka halaman, pilih Tahun Ajaran, pastikan dropdown Semester terisi, pilih Semester, pastikan daftar ter-update, klik Reset Filter, pastikan semua kembali kosong — SEBELUM lanjut ke Task 6.

## 5. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **JANGAN implementasikan "preview jumlah sesi kelas sumber"** di modal duplikasi — butuh endpoint/query tambahan, disproporsional untuk siklus ini.
- **JANGAN tambah KPI "Total Sesi Belajar (X/Y slot terisi)" atau "Pola Jam Kelas"** — keduanya butuh query tambahan di luar prinsip "KPI ringan tanpa query tambahan" yang dipakai spec ini.
- **JANGAN implementasikan color-coding/aksen warna chip per mata pelajaran** di Matriks — definisi bisnis kategorisasi warna belum jelas, perlu didiskusikan terpisah kalau memang diinginkan.
- **JANGAN tambah badge "Ruang Bersama"** di dropdown Ruangan Sarpras — kolom `is_shared` MEMANG ADA di model `Ruangan`, tapi sengaja dikeluarkan murni soal prioritas, bukan keterbatasan data.
- **JANGAN sentuh `app/Http/Controllers/Admin/JadwalPelajaranController.php` sama sekali** — `scopeHeaderData()` sudah ada dan sudah terkirim ke `index()`, dikonfirmasi langsung dari kode. Tidak ada satu pun alasan untuk membuka file ini di plan ini.
- **JANGAN sentuh `DuplicateJadwalAction` atau domain layer manapun** — sudah diverifikasi solid, di luar scope.
- **AJAX hapus (Task 3) WAJIB menyertakan CSRF token manual** dari `<meta name="csrf-token">` di body `FormData` — `fetch()`+`FormData` tanpa hidden input `@csrf` TIDAK otomatis membawa token seperti `<form>` asli. Terlewat = response 419, bukan silent failure, jadi akan cepat ketahuan saat dites — tapi tetap WAJIB ditulis dari awal sesuai kode di plan, jangan baru ditambal setelah lihat error.

## 6. Fakta Operasional

- **Test helper existing yang WAJIB direuse**: `actingAsJadwalManager(Lembaga $lembaga): User` di `tests/Feature/Admin/JadwalPelajaranCrudTest.php` — sudah ada, dipakai apa adanya di semua task.
- **File test lain yang jadi baseline regresi**: `JadwalPelajaranBentrokWaktuTest.php`, `JadwalPelajaranTenantGuardTest.php`, `JamPelajaranCrudTest.php` — WAJIB tetap lulus setelah Task 3 (AJAX hapus), karena test-test itu membuktikan validasi bentrok waktu dan tenant-guard bekerja benar lewat jalur `destroy()`/`store()`/`update()` yang TIDAK boleh berubah perilakunya.
- **Icon yang sudah valid dari siklus Pola Jam sebelumnya**: `data_table`, `list`, `school` — SEMUA sudah ada sebagai `@case` di `icon.blade.php`, Task 2 di plan ini TINGGAL ganti nama pemakaian, TIDAK perlu tambah case baru untuk ketiganya (HANYA `event_busy` yang perlu case baru).
- **`content_copy` SUDAH VALID** (ditambahkan sebagai efek samping siklus Pola Jam) — dipakai di `index.blade.php` dan `_modal-duplicate.blade.php`, TIDAK perlu disentuh sama sekali di plan ini.

## 7. Instruksi Stop-and-Report

- **Kalau test baru di task manapun ("harus GAGAL sebelum fix") ternyata sudah PASS** — STOP, laporkan detail, jangan asumsikan "berarti sudah aman".
- **Kalau salah satu dari test regresi lama (`JadwalPelajaranBentrokWaktuTest.php`, `JadwalPelajaranTenantGuardTest.php`, `JamPelajaranCrudTest.php`) gagal SETELAH perubahan Task manapun** — STOP TOTAL sebelum lanjut task berikutnya, laporkan detail.
- **Kalau Task 5 Step 8 (verifikasi manual browser) menunjukkan dropdown Semester kosong setelah Tahun Ajaran dipilih** — STOP TOTAL, JANGAN lanjut ke Task 6. Ini bukti `gantiTahunAjaran()` versi baru tidak diterapkan dengan benar, perbaiki dulu sebelum lanjut apa pun.
- **JANGAN jalankan full suite bersamaan dengan proses test lain yang sedang berjalan** — insiden nyata pernah terjadi di sesi ini (audit Pengadaan), 2 proses test paralel ke database test yang sama menghasilkan kegagalan palsu massal.
- **Task 9 Step 7 eksplisit: JANGAN jalankan full suite (`php artisan test` tanpa filter) tanpa izin user terlebih dahulu.**

## 8. Catatan Serah Terima

- Spec ditulis dan direview 5 kali, menangkap 1 icon rusak tambahan yang terlewat laporan audit awal (`event_busy`) dan mengoreksi 2 klaim laporan yang salah (lihat §2). Plan ditulis dan direview 5 kali, menangkap 1 koreksi: `edit.blade.php` sempat ditandai "perlu verifikasi" di draf, setelah benar-benar dibaca penuh saat plan disusun ternyata strukturnya cocok persis `create.blade.php` — Task 8 diperbarui memuat kode langsung, bukan instruksi verifikasi lagi.
- **Plan ini memakai komit per-task seperti biasa** (BEDA dari siklus Pola Jam sebelumnya yang sempat memakai aturan "jangan commit sampai disetujui") — kecuali user menyatakan lain secara eksplisit sebelum eksekusi dimulai, ikuti langkah commit di tiap task apa adanya.
- Setelah semua task selesai, JANGAN merge/push branch — keputusan terpisah milik user.

## 9. Mulai dari mana

Mulai dari **Task 1** (fix `createUrlBase` — paling kritis, paling kecil, independen) di `.agents/plans/2026-09-12-jadwal-pelajaran-audit-perbaikan.md`. Task 2 (icon fix) bisa didispatch bersamaan kalau pakai subagent-driven (file berbeda dari Task 1), TAPI Task 3 harus menunggu Task 1 SELESAI (sama-sama edit `jadwal-pelajaran-filter.js`).
