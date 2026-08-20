# Kickoff Prompt — Migrasi 3 Modul Akademik Tersisa ke Domains\Akademik

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan siap jalan. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu, dalam urutan ini

1. `.agents/skills/laravel-feature-standard/SKILL.md` — standar arsitektur wajib yang MENDASARI seluruh spec & plan ini (Controller thin, Action = 1 use-case, DTO readonly, Model cuma relationship/cast/scope)
2. `.agents/specs/2026-08-20-akademik-05-migrasi-domain-tersisa.md` — spec (kenapa dan apa, termasuk hasil audit blast-radius tiap model)
3. `.agents/plans/2026-08-20-akademik-05-migrasi-domain-tersisa.md` — plan implementasi (8 task, lengkap dengan kode dan langkah)

Plan itu ditulis dengan asumsi kamu (executor) tidak punya konteks apa pun tentang codebase ini di luar isi plan dan isi repo — setiap task berisi kode lengkap, bukan deskripsi.

## Konteks singkat

- Repo Laravel 11 multi-tenant (`pintera-app`). Branch kerja: `rbac-v2` (SUDAH ADA, jangan buat branch baru, jangan buat worktree).
- Tugasnya: migrasikan 3 modul terakhir di sidebar "Akademik" (Pengaturan Akademik & Kalender, Pola Jam, Kenaikan Kelas) yang masih pola lama (business logic di controller) ke pola `Domains\Akademik` (Action + DTO + Model domain) yang sudah dipakai modul Akademik lain (Jadwal Pelajaran, RPP, Komponen Penilaian, Rapor).
- **Modul 1 & 2 (Task 1-6): WAJIB zero-behavior-change.** Setiap guard, pesan error bahasa Indonesia, urutan validasi HARUS identik dengan sebelum migrasi. Kalau test lama gagal setelah perubahanmu, itu BUG di implementasimu, bukan alasan mengubah assertion test.
- **Modul 3 (Task 7): SATU-SATUNYA yang sengaja mengubah perilaku** — proses salin-jadwal saat kenaikan kelas sekarang divalidasi bentrok ruangan/guru (pakai Action yang sudah ada), dengan baris yang bentrok di-skip dan dilaporkan, bukan membatalkan seluruh proses kenaikan kelas. Ini keputusan yang SUDAH disepakati user secara eksplisit, bukan sesuatu yang perlu kamu pertimbangkan ulang — baca §4.3 spec untuk detail lengkapnya kalau ada keraguan implementasi.
- 3 model dipindah fisik ke `Domains\Akademik\Models\`: `KalenderAkademik`, `PolaJam`, `JamPelajaran`. 1 service dipindah ke `Domains\Akademik\Services\`: `KalenderAkademikResolver`. Task 4 sudah berisi DAFTAR LENGKAP 36 file consumer hasil `grep` nyata yang perlu diupdate import-nya — JANGAN grep ulang dan berharap dapat daftar identik (file lain bisa berubah), tapi PAKAI daftar yang sudah diberikan di Task 4 sebagai sumber kebenaran, lalu verifikasi dengan grep di akhir Task 4 (sudah ada perintahnya di plan) untuk memastikan tidak ada yang kelewat.

## Cara eksekusi

**Kalau kamu punya akses ke skill `superpowers` (Claude Code dengan plugin Superpowers terpasang):**
Gunakan skill `superpowers:subagent-driven-development` untuk mengeksekusi plan ini task-by-task — fresh subagent per task, task review, ledger progress di `.superpowers/sdd/progress.md`. Ini mode yang sama yang dipakai untuk RBAC v2 dan FASE 5.1 sebelumnya di branch yang sama.

**Kalau kamu tidak punya skill itu (agent lain / tanpa Superpowers):**
Eksekusi manual task-by-task sesuai urutan di plan (Task 1 → 8, urutannya penting — Task 3 butuh Task 1 selesai, Task 5/6 butuh Task 4 selesai, dst, ikuti bagian "Interfaces: Consumes" di tiap task):
1. Baca task, kerjakan tiap step-nya persis seperti ditulis (kode di plan itu final, jangan diparafrase).
2. Jalankan test scoped yang disebut di tiap task SEBELUM commit — kalau ada yang gagal, JANGAN commit, cari tahu kenapa dulu.
3. Satu commit per task, pakai pesan commit yang sudah ditentukan di tiap task.
4. Jangan jalankan full test suite sampai Task 8.
5. Task 8 butuh persetujuan user sebelum menjalankan full suite — TANYA dulu, jangan otomatis jalan.

## Kalau menemukan sesuatu yang tidak sesuai plan

Kode yang dikutip plan ini berbasis commit `08fb958`/`b8b6242` di branch `rbac-v2`. Kalau kamu menemukan isi file yang BEDA signifikan dari yang dikutip plan (bukan cuma beda nomor baris — plan sudah pakai pencarian teks, bukan nomor baris, untuk kasus ini), atau blok kode yang dicari plan ternyata TIDAK ADA sama sekali — STOP, jangan menebak atau "memperbaiki sendiri". Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Setelah kamu selesai

Sesi yang menulis plan ini (bukan kamu) akan melakukan REVIEW KODE DETAIL terhadap seluruh hasil kerjamu setelah kamu laporkan selesai — itu instruksi eksplisit dari user. Jadi pastikan setiap commit bersih dan bisa ditelusuri (jangan squash / rewrite history), dan handoff log Task 8 mendokumentasikan semua keputusan/penyimpangan (kalau ada) dari plan secara jujur.

## Definisi selesai

Task 8 selesai: keempat command `grep` di Task 8 Step 1 menghasilkan output kosong (tidak ada referensi ke lokasi lama), full suite hijau dengan jumlah test LEBIH BESAR dari baseline 1861 (ada test baru), handoff log tertulis di `.agents/logs/2026-08-20-akademik-05-migrasi-domain-tersisa.md`.
