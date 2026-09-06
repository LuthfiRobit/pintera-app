# Kickoff: Batas Waktu Edit Presensi & Jurnal KBM

**Base commit**: `b2a25c83` (`docs(akademik): implementation plan batas waktu edit presensi & jurnal kbm`)
**Branch**: `akademik-v2` (tetap di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

Item backlog Low-severity, ditemukan sejak audit 27-28 Agustus 2026, baru dicatat resmi di `PETA_PENGEMBANGAN.md` tanggal 6 September 2026: `Guru\Akademik\JurnalKbmController::update()` tidak punya batasan waktu untuk mengedit presensi/jurnal sesi lama — guru bisa mengubah kehadiran dari bulan-bulan lalu tanpa batasan. Bukan celah keamanan (kepemilikan sudah benar), tapi risiko integritas data historis (rekap presensi yang sudah jadi dasar rapor bisa berubah diam-diam).

Melalui brainstorming, keputusan akhirnya: **batas waktu N hari yang bisa dikonfigurasi per-Lembaga** (bukan hardcoded, bukan terikat status rapor — alasan: platform ini melayani banyak jenjang dengan budaya administrasi berbeda), dengan **Wali Kelas dikecualikan untuk sesi kelasnya sendiri**.

Selama diskusi, muncul 2 topik terkait yang **SENGAJA DIPISAH, TIDAK masuk cakupan kerja ini**:
- **Proyek B** — Waka Kurikulum bisa mengakses & mengedit Jurnal KBM guru LAIN (bukan sekadar override, tapi akses lintas-guru yang belum ada infrastrukturnya sama sekali). Backlog terpisah, belum ada spec.
- **Proyek C** — "Guru piket": siapa yang mengisi presensi hari itu juga kalau guru pemilik sesi berhalangan hadir (sakit/izin mendadak). Gap operasional nyata, berbeda akar masalah dari cutoff ini (bukan koreksi, tapi pengisian normal real-time). Akan dikerjakan SETELAH proyek ini selesai, kemungkinan reuse pola pengaturan-per-Lembaga di sini.

**JANGAN mengerjakan Proyek B atau C di kickoff ini** — kalau menemukan alasan kuat kenapa keduanya perlu digabung, STOP dan laporkan ke user dulu.

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-06-batas-edit-presensi-jurnal.md` — keputusan desain lengkap (8 poin di §2) beserta alasan tiap keputusan.
2. `.agents/plans/2026-09-06-batas-edit-presensi-jurnal.md` — 7 task, kode lengkap tiap task, sudah self-review.
3. `.agents/specs/2026-09-06-kartu-digital-siswa-presensi-scan.md` dan `.agents/plans/2026-09-06-kartu-digital-siswa-presensi-scan.md` — konteks fitur Scan Presensi (Kartu Digital Siswa) yang BARU SAJA ditambahkan ke file yang sama (`JurnalKbmController.php`, `show.blade.php`) — WAJIB dipahami supaya tidak merusaknya.
4. `.ai/rules/index.md` lalu setiap file rule yang glob-nya cocok dengan path yang akan disentuh (`actions.md`, `controllers.md`, `data-transfer-objects.md`, `domains-models.md`, `migrations.md`, `routes.md`, `views.md`, `tests.md`).

## 3. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **Basis cutoff: rolling N hari dari HARI INI, dikonfigurasi per-Lembaga** (kolom `lembaga.batas_edit_absen_hari`, default `3`). BUKAN terikat status rapor, BUKAN terikat semester aktif — ini keputusan eksplisit setelah pertimbangan matang (dibahas panjang di sesi brainstorming, jangan didesain ulang).
- **Override HANYA Wali Kelas, HANYA untuk sesi kelas yang dia menjadi wali kelasnya sendiri** (`$sesi->kelas->wali_kelas_guru_id === $guru->id`). Guru mapel biasa di kelas LAIN yang kebetulan dia jadi wali kelasnya TIDAK ikut kena override — cek scenario test #4 di Task 5 plan untuk memastikan ini benar.
- **Waka Kurikulum TIDAK termasuk override apa pun di sini** — itu Proyek B, di luar cakupan total.
- **Seluruh form dikunci** (materi + presensi + keterangan) saat terkunci — bukan sebagian field saja.
- **`show()` WAJIB kirim status terkunci ke view SEBELUM guru sempat isi form** — jangan baru gagal setelah klik Simpan. Ini keputusan UX eksplisit.
- **`update()` yang ditolak karena cutoff TIDAK BOLEH memanggil `RecordJurnalDanPresensiAction` sama sekali** — fail sebelum ada perubahan data apa pun tersimpan (lihat Task 5 test scenario "tidak ada perubahan tersimpan").
- **Action baru (`UpdateBatasEditAbsenLembagaAction`) WAJIB menerima DTO (`BatasEditAbsenLembagaData`), BUKAN scalar `int` langsung** — ini koreksi yang sudah ditemukan & diperbaiki di spec (verifikasi ke sibling Action `UpdateHariAktifLembagaAction` yang memang konsisten pakai DTO di domain ini).
- **JANGAN rusak fitur Scan Presensi Kartu Digital Siswa** yang baru ditambahkan ke `JurnalKbmController.php` dan `show.blade.php` (method `resolveKartu()`, blok "Scan Presensi via Kartu Digital", event bus `@presensi-scanned.window`) — itu fitur terpisah yang harus tetap utuh sepenuhnya setelah plan ini selesai.
- **Tidak pakai worktree, tidak pindah branch.**

## 4. Fakta Operasional

- **MySQL deadlock risk**: project ini pernah kena deadlock kalau 2 proses test jalan bersamaan — SELALU cek dulu (`Get-CimInstance Win32_Process -Filter "Name='php.exe'"` di PowerShell) sebelum full suite di Task 7.
- **Migrations di project ini sudah di-squash** — base schema di `database/schema/mysql-schema.sql`, migrasi baru cukup ditambah normal via `php artisan make:migration`, jangan bingung kalau cuma sedikit file migrasi yang terlihat.
- **Task 4 & Task 6 butuh verifikasi manual di browser** (view-only changes, tidak ada test Pest untuk sebagiannya) — WAJIB benar-benar dilakukan dan dilaporkan hasilnya secara jujur di laporan task, JANGAN diasumsikan lolos karena kode "terlihat benar". Kalau ada akses ke Playwright/browser automation, boleh dipakai untuk verifikasi lebih genuine (lihat pola script `storage/verify-scan-presensi.mjs` yang pernah dipakai sesi sebelumnya untuk fitur Scan Presensi sebagai referensi — file itu SUDAH DIHAPUS setelah dipakai, cuma polanya yang relevan sbg referensi).
- **Akun demo untuk verifikasi manual**: cari akun dengan permission `pengaturan-akademik.kelola` untuk Task 4 (kemungkinan `kurikulum.sd@demo.test`), dan akun guru dengan sesi Jurnal KBM untuk Task 6 (`guru.sd1@demo.test` atau `hendra.gunawan@demo.test`, cek seeder demo dulu, JANGAN menebak password — konvensi project ini `password` untuk semua akun demo, tapi verifikasi tetap dianjurkan).

## 5. Instruksi Stop-and-Report

- **Kalau menemukan `JurnalKbmController.php` atau `show.blade.php` BERBEDA dari yang diasumsikan plan** (kemungkinan berubah lagi sejak plan ditulis) — STOP, baca versi terkini, sesuaikan implementasi mengikuti struktur SEKARANG, catat di laporan task apa yang berbeda dan kenapa.
- **Kalau test regresi Task 5 Step 7 (`JurnalKbmControllerTest`, `JurnalKbmResolveKartuTest`, `JurnalKbmTanggalSusulanTest`) gagal** karena view butuh variabel baru yang belum dikirim — itu WAJAR dan diperbaiki di Task 6, TAPI kalau ada kegagalan lain yang tidak terduga (bukan soal variabel `terkunci`/`batasEditHari`), STOP dan laporkan sebagai BLOCKED, jangan asumsikan "pasti akan terperbaiki nanti".
- **Kalau ragu soal cakupan** (mis. apakah suatu detail masuk plan ini atau termasuk Proyek B/C yang ditunda): tanyakan, jangan menebak sepihak.

## 6. Catatan Serah Terima

- Spec ini sudah melalui 2 putaran koreksi setelah verifikasi langsung ke kode (bukan asumsi) — pola DTO Action, konfirmasi `$fillable`, konfirmasi namespace folder `Kalender`. Kalau menemukan bagian lain di plan yang ternyata tidak cocok dengan kode terkini, perbaiki dan catat kenapa, JANGAN diam-diam ikuti draft yang salah.
- User secara eksplisit meminta kickoff kali ini (bukan eksekusi inline di sesi yang sama) — kerjakan mandiri sampai selesai atau sampai benar-benar BLOCKED butuh keputusan user.
- Setelah Task 7 selesai, JANGAN merge branch `akademik-v2` ke `main` — itu keputusan terpisah milik user (branch ini juga masih membawa hasil A2, A3/Kartu Digital Siswa, dan audit-audit sebelumnya yang semuanya belum di-merge).

## 7. Mulai dari mana

Mulai dari **Task 1** (migrasi & model `Lembaga`) di `.agents/plans/2026-09-06-batas-edit-presensi-jurnal.md`, kerjakan berurutan Task 1 → 7. Task 5 adalah inti fitur (enforcement) — baca file `JurnalKbmController.php` SAAT INI dulu sebelum menyentuhnya, jangan asumsikan dari isi plan semata.
