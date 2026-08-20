# Kickoff Prompt — FASE 5.1: Restrukturisasi Rute Modular

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan siap jalan. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu, dalam urutan ini

1. `.agents/specs/2026-08-20-fase-5-1-restrukturisasi-rute-modular-design.md` — spec (kenapa dan apa)
2. `.agents/plans/2026-08-20-fase-5-1-restrukturisasi-rute-modular.md` — plan implementasi (15 task, lengkap dengan kode dan langkah)

Plan itu ditulis dengan asumsi kamu (executor) tidak punya konteks apa pun tentang codebase ini di luar isi plan dan isi repo — setiap task berisi kode lengkap, bukan deskripsi.

## Konteks singkat

- Repo Laravel 11 multi-tenant (`pintera-app`). Branch kerja: `rbac-v2` (SUDAH ADA, jangan buat branch baru, jangan buat worktree — user sudah eksplisit minta kerja langsung di branch ini tanpa isolasi tambahan).
- Tugasnya murni **pemindahan teks routing**: `routes/admin.php` (368 baris, campur ~15 domain) dipecah jadi 13 file modul di `routes/admin/` + 2 file top-level (`routes/kasus.php`, `routes/guru.php`) yang dipisah keluar karena ternyata bukan bagian grup admin sama sekali.
- **TIDAK ADA perubahan** nama route, URI, urutan pendaftaran relatif, atau middleware. Kalau kamu merasa perlu mengubah salah satu dari itu, STOP dan laporkan sebagai blocker — itu di luar scope plan ini.
- Setiap task diverifikasi dengan `php artisan route:list` (FULL, bukan filtered) sebelum vs sesudah — harus identik isinya (boleh beda urutan tampil).
- Baseline full test suite SEBELUM plan ini mulai: **1861 passed, 0 failed**. Full suite hanya dijalankan SEKALI, di Task 15, setelah eksekusi seluruh 14 task sebelumnya selesai — bukan per-task.

## Cara eksekusi

**Kalau kamu punya akses ke skill `superpowers` (Claude Code dengan plugin Superpowers terpasang):**
Gunakan skill `superpowers:subagent-driven-development` untuk mengeksekusi plan ini task-by-task — fresh subagent per task, task review, ledger progress di `.superpowers/sdd/progress.md`. Ini mode yang sama yang dipakai untuk menyelesaikan RBAC v2 sebelumnya di branch yang sama.

**Kalau kamu tidak punya skill itu (agent lain / tanpa Superpowers):**
Eksekusi manual task-by-task sesuai urutan di plan:
1. Baca task, kerjakan tiap step-nya persis seperti ditulis (kode di plan itu final, jangan diparafrase).
2. Verifikasi diff `route:list` per task SEBELUM commit — kalau ada perbedaan, JANGAN commit, cari tahu kenapa (kemungkinan besar salah kutip blok teks yang mau dipindah, atau ada baris yang kelewatan/ganda).
3. Satu commit per task, pakai pesan commit yang sudah ditentukan di tiap task.
4. Jangan jalankan full test suite sampai Task 15.
5. Task 15 butuh persetujuan user sebelum menjalankan full suite — TANYA dulu ("full suite sekarang?"), jangan otomatis jalan.

## Kalau menemukan sesuatu yang tidak sesuai plan

Nomor baris di plan bisa sedikit meleset kalau ada commit lain yang menyentuh `routes/admin.php` di antara plan ini ditulis dan kamu mulai eksekusi (plan sudah mengantisipasi ini — instruksinya pakai pencarian teks exact-match, bukan nomor baris). Tapi kalau kamu menemukan blok kode yang DICARI plan ternyata TIDAK ADA sama sekali di file saat ini, atau isinya beda signifikan dari yang dikutip plan — STOP, jangan menebak atau "memperbaiki sendiri". Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Definisi selesai

Task 15 selesai: `routes/admin.php` jadi loader murni (13 baris `require`), full suite hijau sama seperti baseline (1861 passed), checklist FASE 5.1 di `.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md` sudah dicentang, handoff log tertulis di `.agents/logs/2026-08-20-fase-5-1-restrukturisasi-rute-modular.md`.
