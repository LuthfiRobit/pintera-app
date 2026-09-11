# Kickoff: Audit & Perbaikan Modul Pola Jam & Jam Pelajaran

**Base commit**: `5f21feb1` (`docs(pola-jam): tulis implementation plan, 5 putaran self-review...`)
**Branch**: `rbac-v2` (TETAP di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 0. ⚠️ ATURAN KHUSUS KICKOFF INI — JANGAN COMMIT SEBELUM USER SETUJU

**Ini instruksi eksplisit user untuk siklus ini, BEDA dari siklus-siklus sebelumnya di sesi ini** (kurikulum-assignment, jadwal-piket-guru, dll — yang semuanya commit per-task sepanjang eksekusi).

- **JANGAN jalankan `git commit` apa pun** selama mengerjakan Task 1-9, walaupun plan (`.agents/plans/2026-09-11-pola-jam-audit-perbaikan.md`) menuliskan langkah "Commit" di akhir tiap task — **ABAIKAN langkah commit itu untuk sekarang**, tetap kerjakan semua langkah LAIN di tiap task (tulis test, jalankan test, implementasi, verifikasi) seperti biasa, HANYA lewati bagian `git add`/`git commit`-nya.
- **Selesaikan SEMUA task (1 sampai 9) dulu** sampai tuntas — kode berubah, test lulus, Pint bersih, build sukses — SEMUA itu tetap wajib dilakukan seperti biasa, cuma TANPA commit di antaranya.
- Setelah SEMUA task selesai (termasuk Task 9 regression sweep), **STOP dan laporkan ke user** — tampilkan `git status`/`git diff --stat` supaya user bisa review perubahan MENTAH sebelum ada satu commit pun dibuat.
- **User yang akan memutuskan sendiri** kapan dan bagaimana perubahan ini di-commit (bisa satu commit besar, atau diminta commit per-task belakangan, atau ada revisi dulu) — JANGAN berasumsi apa pun soal itu, tunggu instruksi eksplisit.
- Kalau ragu apakah suatu langkah "termasuk commit" atau bukan — semua langkah yang literal menjalankan `git add`/`git commit` di plan TIDAK dijalankan sampai user bilang boleh. Langkah `vendor/bin/pint --dirty --format agent` TETAP boleh/harus dijalankan (itu memformat file, bukan commit).

---

## 1. Konteks

Modul Pola Jam adalah *bell schedule* — template ritme harian sekolah (Pola Jam) berisi slot Jam Pelajaran, ditautkan ke Kelas. Audit UI/UX menemukan backend (Actions, DTO, FormRequest) **sudah solid, TIDAK ada perubahan domain layer di plan ini**. Temuan paling kritis: **5 nama icon yang dipakai di halaman TIDAK terdaftar di komponen icon global**, sehingga tampil sebagai ikon tanda-tanya "?" — ini regresi visual nyata, bukan preferensi desain, dan diverifikasi langsung lewat grep ke kode (bukan cuma percaya laporan audit).

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-11-pola-jam-audit-perbaikan.md` — spec lengkap, 5 putaran self-review, kode current-vs-fix KONKRET untuk 8 kelompok temuan (§2.1-§2.8), plus koreksi penting: klaim audit awal soal "matriks mingguan menyesatkan di kolom kiri" TERNYATA BERLEBIHAN (per-sel sudah akurat, cuma label ringkasan kolom kiri yang perlu diperjelas) — lihat §2.5 dan §4.
2. `.agents/plans/2026-09-11-pola-jam-audit-perbaikan.md` — 9 task, 5 putaran self-review. SEMUA task berisi kode lengkap siap salin.

## 3. ⚠️ Peringatan Struktural Plan Ini — BEDA dari Siklus Sebelumnya

**7 dari 9 task (Task 1, 2, 3, 4, 5, 6, 8) SEMUA mengedit `index.blade.php`.** Ini plan pertama di sesi ini yang task-nya SANGAT tidak independen — kebalikan dari kurikulum-assignment kemarin.

- Task 1, 2, 3, 4, 5, 6, 8 **WAJIB dikerjakan SEKUENSIAL persis dalam urutan angka itu** — **TIDAK BOLEH di-dispatch sebagai subagent paralel** satu sama lain, walau kelihatan seperti task-task independen dari nomornya. Kalau pakai `subagent-driven-development`, controller (kamu) HARUS dispatch satu-satu menunggu task sebelumnya benar-benar selesai (termasuk review), BUKAN batch-dispatch.
- **HANYA Task 7** (`_modal-pola.blade.php` + `_modal-edit-slot.blade.php`, file berbeda total) **aman diparalelkan** dengan Task 1-6/8.
- **Task 9 (regression sweep) WAJIB paling akhir**, setelah SEMUA task lain (termasuk Task 7) selesai.
- Plan sudah menandai eksplisit di tiap task yang mengedit `index.blade.php`: **"Baca ulang `index.blade.php` TERKINI" sebagai Step 1** — WAJIB dipatuhi, karena kode "cari blok ini" yang dikutip plan mengasumsikan state file SETELAH task-task sebelumnya, BUKAN file original sebelum plan ini mulai dikerjakan. Contoh konkret: kode target Task 2 sudah mengasumsikan `name="school"`/`name="groups"` (hasil perbaikan Task 1), bukan `name="class"`/`name="add_circle"` yang ada di file SEBELUM Task 1 dikerjakan.

## 4. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **JANGAN implementasikan auto-increment urutan & jam mulai** di form input slot — sengaja dikeluarkan (asumsi hari/urutan acuan tidak jelas untuk form multi-hari sekaligus, salah-suggest lebih berbahaya daripada field kosong).
- **JANGAN redesain ulang warna Jam Belajar vs Non-KBM di Matriks Mingguan** — SUDAH ADA di kode saat ini, menyentuhnya lagi di Task 5 adalah scope creep.
- **JANGAN tambah KPI "Rata-rata Jam Belajar per Hari"** — dikeluarkan karena definisi bisnisnya ambigu (PAUD/SD/SMP beda hari aktif, 1 lembaga bisa punya banyak Pola Jam).
- **JANGAN redesain besar-besaran Matriks Mingguan** di Task 5 — HANYA ganti label kolom kiri sesuai kode di plan, per-sel matriks SUDAH akurat.
- **JANGAN sentuh `PolaJamController.php`, `JamPelajaranController.php`, atau domain layer manapun** (Actions/DTOs) — semua data yang dibutuhkan (KPI, tautan kelas, dsb) SUDAH tersedia dari eager-load `index()` yang ada.
- **Preset label slot WAJIB pakai `<datalist>`**, BUKAN pill button seperti preset Hari — field Label teks bebas, beda kebutuhan dari field Hari yang domainnya terbatas.
- **Icon `content_copy` yang baru ditambahkan ke `icon.blade.php` juga otomatis memperbaiki halaman `jadwal-pelajaran`** (komponen dipakai bersama) — ini EFEK SAMPING yang diharapkan dan boleh dilaporkan, TAPI JANGAN ada perubahan lain yang disengaja ke file `jadwal-pelajaran` manapun, itu di luar scope.

## 5. Fakta Operasional

- **Test helper existing yang WAJIB direuse**: `actingAsPolaJamManager(Lembaga $lembaga): User` di `tests/Feature/Admin/PolaJamCrudTest.php` — SUDAH ADA, dipakai apa adanya di semua task test baru. Perhatikan helper ini defaultnya HANYA punya permission `pola-jam.*` + `jam-pelajaran.create` — task yang butuh permission lain (`kelas.edit` di Task 2, `jam-pelajaran.edit`/`jam-pelajaran.delete` di Task 4) harus tambah permission itu SECARA LOKAL di test (`$manager->givePermissionTo(...)`), JANGAN ubah helper bersama itu (bisa mempengaruhi test lain yang memakainya).
- **File test lain yang jadi baseline regresi**: `tests/Feature/Admin/KelasPolaJamTest.php` — WAJIB tetap lulus di setiap task yang menyentuh `index.blade.php`, karena test ini membuktikan hubungan Kelas↔PolaJam bekerja benar dari sisi lain (bukan cuma dari sisi PolaJamCrudTest).
- **`JamPelajaran::jam_mulai`/`jam_selesai` TIDAK di-cast** (dicek langsung ke model, `protected function casts()` cuma cast `hari` dan `is_pelajaran`) — artinya nilai mentah dari DB (kemungkinan besar format `TIME` MySQL yang balik sebagai string `"07:00:00"` dengan detik) itulah yang bikin masalah format waktu di §2.4 nyata, bukan asumsi kosong.
- **`BentukPendidikan`/`Hari::aktifDari()`** dipakai untuk filter hari aktif per lembaga (`hari_libur_mingguan` di model `Lembaga`) — SEMUA task yang menyentuh loop hari (Task 3, 4) WAJIB tetap pakai `$hariAktifPola` (variabel yang SUDAH ada, computed di `index.blade.php` dari `\App\Enums\Hari::aktifDari($pola->lembaga->hari_libur_mingguan ?? [])`), JANGAN loop `\App\Enums\Hari::cases()` mentah untuk elemen yang seharusnya menghormati hari libur lembaga (Daftar Harian Task 4 SENGAJA tetap loop `\App\Enums\Hari::cases()` untuk BLOK KONTEN per-hari, supaya slot yang ada tetap kebaca walau harinya "libur" menurut setting terbaru — tapi TAB NAVIGASI-nya pakai `$hariAktifPola` supaya user tidak diarahkan ke hari yang sudah tidak aktif secara default; ini nuansa yang sudah tepat di kode plan, JANGAN disamakan begitu saja).

## 6. Instruksi Stop-and-Report

- **Kalau test baru di task manapun ("harus GAGAL sebelum fix") ternyata sudah PASS** — STOP, laporkan detail, jangan asumsikan "berarti sudah aman".
- **Kalau `KelasPolaJamTest.php` atau test lama `PolaJamCrudTest.php` gagal SETELAH perubahan task manapun** — STOP TOTAL sebelum lanjut ke task berikutnya, laporkan detail. Ini baseline regresi paling kritis di plan ini.
- **Kalau Task 1 Step 6 (verifikasi grep icon) menemukan nama yang MASIH tidak terdaftar** — STOP, JANGAN lanjut ke Task 2 (Task 2 mengasumsikan icon Task 1 sudah benar).
- **JANGAN jalankan full suite bersamaan dengan proses test lain yang sedang berjalan** — insiden nyata pernah terjadi di sesi ini (audit Pengadaan), 2 proses test paralel ke database test yang sama menghasilkan kegagalan palsu massal.
- **Task 9 Step 6 eksplisit: JANGAN jalankan full suite (`php artisan test` tanpa filter) tanpa izin user terlebih dahulu.**
- **Ingat §0 di atas**: kalau menemukan diri sendiri (atau subagent) hendak menjalankan `git commit`, STOP — itu belum boleh sampai user review dan approve.

## 7. Catatan Serah Terima

- Spec ditulis dan direview 5 kali. Plan ditulis dan direview 5 kali, menyederhanakan 1 ekspresi test yang berpotensi rapuh (fallback tahun-ajaran-aktif lewat `Semester::factory()` yang tidak jelas semantiknya, diganti `TahunAjaran::factory()` langsung).
- **Berbeda dari siklus-siklus sebelumnya di sesi ini**: plan ini TIDAK di-commit per-task selama eksekusi (lihat §0) — setelah Task 9 selesai, kumpulkan semua perubahan, laporkan ke user via `git status`/`git diff --stat`, dan TUNGGU instruksi commit eksplisit sebelum menjalankan `git add`/`git commit` apa pun.
- Setelah user approve dan commit dibuat, JANGAN merge/push branch — itu keputusan terpisah milik user, sama seperti siklus-siklus sebelumnya.

## 8. Mulai dari mana

Mulai dari **Task 1** (icon fix — paling kritis, prasyarat Task 2) di `.agents/plans/2026-09-11-pola-jam-audit-perbaikan.md`. Ingat: jalankan semua langkah task SAMPAI SELESAI kecuali langkah commit-nya (lihat §0), lalu lanjut ke task berikutnya secara sekuensial.
