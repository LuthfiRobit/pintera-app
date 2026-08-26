# Kickoff Prompt — TD-AKADEMIK-001 (Hapus `TipeMataPelajaran::AspekPerkembangan`)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan sudah direview mendalam (termasuk audit yang dikoreksi 2x sebelum keputusan final diambil). Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu

1. `.agents/specs/2026-08-27-td-akademik-001-hapus-aspek-perkembangan.md` — spec lengkap (§Latar Belakang WAJIB dibaca — menjelaskan proses audit yang 2x dikoreksi sebelum sampai ke keputusan "hapus total", supaya kamu paham ini bukan keputusan gegabah).
2. `.agents/plans/2026-08-27-td-akademik-001-hapus-aspek-perkembangan.md` — plan implementasi (3 task, kode lengkap, TDD step-by-step).

## Konteks singkat — PENTING, baca sebelum eksekusi

- Repo Laravel 12.63.0 multi-tenant (`pintera-app`), MySQL 8.0.30. Branch kerja: `akademik-v2` (SUDAH ADA — jangan buat branch baru, jangan buat worktree).
- **Ini penghapusan fitur yang disengaja, bukan bug fix biasa.** `TipeMataPelajaran::AspekPerkembangan` adalah CRUD yang BERFUNGSI dan DIUJI, tapi tidak terintegrasi ke defaulting `assessment_type` (Sprint 2) dan tidak pernah dipakai data nyata (gap seeder SD-only, sudah dikonfirmasi lewat audit). Keputusan user: hapus total, `ElemenCp` jadi satu-satunya jalur resmi PAUD non-formal-subject.
- **`ElemenCp` (model, migration, seeder, test) TIDAK DISENTUH SAMA SEKALI** di task ini — kalau kamu tergoda "sekalian" memperkaya `ElemenCp` jadi 6 aspek STPPA (bonus temuan lama yang disebut di catatan debt), JANGAN — itu keputusan terpisah yang belum dibahas, eksplisit di luar scope (lihat spec §Non-Goals).
- **Kolom `mata_pelajaran.tipe` adalah ENUM di level DATABASE** (bukan cuma PHP enum) — Task 1 migration menyempitkannya jadi `ENUM('mapel')`, dengan guard eksplisit yang GAGAL KERAS kalau ternyata ada row yang masih pakai `aspek_perkembangan` (seharusnya nol di environment ini, tapi migration tidak boleh mengasumsikan itu tanpa verifikasi runtime).

## Urutan eksekusi

**Task 1 → 2 → 3 murni LINEAR.**

**PERHATIAN KHUSUS Task 2**: task ini HARUS 1 commit tunggal (bukan dipecah lagi per file) — menghapus case enum SEBELUM controller/view yang mereferensikannya diupdate akan membuat kode gagal compile/runtime error di tengah jalan. Kerjakan semua sub-step (test dulu, lalu enum, lalu controller, lalu view) secara berurutan dalam satu working set, baru commit sekali di akhir Step 8.

**Kalau kamu punya akses ke skill `superpowers`:**
Boleh eksekusi manual langsung (`superpowers:executing-plans` atau inline) — scope cukup kecil (3 task, ~10 file) untuk tidak wajib `subagent-driven-development`, tapi boleh dipakai kalau kamu mau ekstra hati-hati krn ini menyentuh ENUM database production.

**Kalau tidak punya skill itu:**
Eksekusi manual:
1. Baca task, kerjakan tiap step persis seperti ditulis.
2. **WAJIB baca file existing (`TipeMataPelajaran.php`, `MataPelajaranController.php`, kedua view, ketiga file test) SEBELUM edit** dan bandingkan dgn kutipan di spec/plan — kalau baseline beda, STOP dan laporkan ke user.
3. Jalankan `php -l <file>` setelah tiap edit PHP, SEBELUM commit.
4. Jalankan test scoped SEBELUM commit — kalau gagal, JANGAN commit, diagnosis dulu.
5. 2 commit total (Task 1, Task 2 — Task 3 tidak menghasilkan commit, murni verifikasi + laporan + migrasi dev DB).
6. **JANGAN jalankan full test suite sampai Task 3 Step 2.**

## Peringatan eksplisit dari plan — titik yang HARUS diverifikasi, bukan diasumsikan

1. **Task 1 Step 2** — migration WAJIB punya guard `RuntimeException` sebelum `ALTER TABLE`. JANGAN hapus/lewati guard ini "karena toh sudah dikonfirmasi kosong" — itu justru poin utamanya (defense-in-depth, bukan formalitas).
2. **Task 2 Step 7** — kalau grep/test menemukan file LAIN (di luar 3 file test yang disebut plan) yang ternyata juga mereferensikan `AspekPerkembangan` dan gagal, STOP dan laporkan ke user — JANGAN asal hapus/ubah test itu sendiri tanpa melapor, itu tandanya audit sebelum plan ini kurang lengkap.
3. **Task 3 Step 1** — grep akhir HARUS dijalankan mencakup `.blade.php` juga, bukan cuma `.php` (view lama sering jadi tempat referensi tersembunyi yang gampang terlewat).
4. **Task 3 Step 3** — migrasi dev database (Laragon/MySQL nyata, bukan cuma test DB) WAJIB dijalankan dgn `php artisan migrate` biasa (BUKAN `migrate:fresh` — ada data nyata di situ).

## Pelajaran penting dari Sprint 1-5 & TD-AKADEMIK-002 (berlaku juga di sini)

1. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
2. **JANGAN jalankan lebih dari satu proses `php artisan test`/`migrate:fresh` bersamaan.**
3. **Klaim hasil test di handoff log HARUS bisa ditelusuri (command + angka pasti)** — jalankan `php artisan test` TANPA filter apa pun untuk klaim "full suite hijau".
4. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan — STOP dan laporkan ke user, JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.**
5. **Jangan menambah scope di luar 3 task ini** — termasuk godaan memperkaya `ElemenCp` jadi 6 aspek STPPA, atau membersihkan `KelompokMataPelajaran` yang juga disebut di catatan debt lama. Keduanya eksplisit di luar scope (spec §Non-Goals).

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Definisi selesai

- Task 1: migration baru menyempitkan `mata_pelajaran.tipe` jadi `ENUM('mapel')` dgn guard defense-in-depth, 2 test PASS (insert `aspek_perkembangan` ditolak, insert `mapel` tetap valid).
- Task 2: `TipeMataPelajaran` cuma 1 case (`Mapel`), controller/view sudah tidak mereferensikan `AspekPerkembangan` sama sekali, 3 test existing disesuaikan (2 diubah minimal, 1 diadaptasi konsepnya) dan tetap PASS — 1 commit tunggal.
- Task 3: grep nol referensi tersisa di file `.php`/`.blade.php` aktif, **full test suite (`php artisan test` tanpa filter) 0 failed**, angka pasti dicatat, dev database (Laragon/MySQL nyata) sudah dimigrasi, laporan final ke user berisi angka pasti + 2 commit hash + konfirmasi `ElemenCp` tidak tersentuh sama sekali.
