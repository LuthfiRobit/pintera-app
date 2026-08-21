# Kickoff Prompt — Rebranding Data Demo Pintera

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan siap jalan. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu, dalam urutan ini

1. `.agents/specs/2026-08-21-rebranding-data-demo-pintera.md` — spec (kenapa dan apa: pola email pendek, rebranding Yayasan/Lembaga ke "Pintera", nama staf netral)
2. `.agents/plans/2026-08-21-rebranding-data-demo-pintera.md` — plan implementasi (6 task, lengkap dengan kode dan langkah)

Plan itu ditulis dengan asumsi kamu (executor) tidak punya konteks apa pun tentang codebase ini di luar isi plan dan isi repo — setiap task berisi kode PHP lengkap, bukan deskripsi/pseudocode.

## Konteks singkat

- Repo Laravel 11 multi-tenant (`pintera-app`). Branch kerja: `rbac-v2` (SUDAH ADA, jangan buat branch baru, jangan buat worktree).
- Tugasnya murni data cosmetic di `database/seeders/*.php`: (a) email dummy dipendekkan ke pola `{peran}.{kode-lembaga}@demo.test` (mengganti `@sistem.test` dan `@permatakraksaan.sch.id`), (b) nama Yayasan/Lembaga direbranding jadi "Pintera" (tanpa "Kraksaan" di nama, alamat administratif TETAP asli), (c) nama staf yang masih pakai honorifik "Ustadz"/"Ustadzah" diganti ke pola netral. Role name di kode (`admin_keuangan` dkk) **TIDAK diubah sama sekali** — itu keputusan eksplisit user, ditunda ke fase lain.
- **Plan Spec 1 (`.agents/plans/2026-08-21-audit-perbaikan-seeder.md`) SUDAH DIEKSEKUSI PENUH dan sudah di-review** (branch `rbac-v2`, commit terakhir `f712786`) — jadi file-file yang disentuh kedua plan (`EssentialUserSeeder.php`, `SarprasPengadaanDemoSeeder.php`, `KeuanganDemoSeeder.php`) SUDAH dalam bentuk PASCA-Spec-1 (ada environment-guard, ada akun `sarpras@sistem.test`, dst). Plan Spec 2 sendiri sudah ditulis untuk robust terhadap kondisi ini (tiap step mencari string lama dulu sebelum edit, ada catatan eksplisit "kalau Spec 1 sudah dieksekusi..." di beberapa step) — tapi baca catatan itu dengan teliti, jangan diasumsikan salah satu skenario saja.
- Task 1 (rebranding Yayasan/Lembaga) sebaiknya duluan karena `KeuanganDemoSeeder::cariAdminKeuangan()` diperbaiki di situ (pindah dari regex `kode_lembaga` ke mapping `bentuk_pendidikan`), dan Task 2-4 (email) bergantung pada logic itu sudah benar meski tidak bergantung pada datanya sudah berubah.
- Task 2, 3, 4 (email) HARUS dikerjakan bersamaan lintas banyak file per task — beberapa string literal (`wali.diterima@example.test`, dst) dipakai di 8-9 file sekaligus sebagai kunci pencarian, kalau tidak diganti bersamaan di satu task, `migrate:fresh --seed` akan gagal dengan `ModelNotFoundException` di tengah proses.

## Cara eksekusi

**Kalau kamu punya akses ke skill `superpowers` (Claude Code dengan plugin Superpowers terpasang):**
Gunakan skill `superpowers:subagent-driven-development` untuk mengeksekusi plan ini task-by-task — fresh subagent per task, task review, ledger progress di `.superpowers/sdd/progress.md`. Ini mode yang sama yang dipakai untuk Spec 1 dan migrasi domain Kasus/Akademik sebelumnya di branch yang sama.

**Kalau kamu tidak punya skill itu (agent lain / tanpa Superpowers):**
Eksekusi manual task-by-task sesuai urutan di plan (Task 1 → 6):
1. Baca task, kerjakan tiap step-nya persis seperti ditulis (kode di plan itu final, jangan diparafrase).
2. **WAJIB**: sebelum edit apapun, cari dulu string LAMA yang disebut step itu. Kalau TIDAK DITEMUKAN (karena Spec 1 atau eksekusi plan ini sendiri di task sebelumnya sudah mengubahnya), CATAT di laporan task dan LEWATI step itu — JANGAN menebak bentuk barunya.
3. Jalankan verifikasi manual (`migrate:fresh --seed`, tinker, grep) DAN test scoped yang disebut di tiap task SEBELUM commit — kalau ada yang gagal, JANGAN commit, cari tahu kenapa dulu.
4. Satu commit per task, pakai pesan commit yang sudah ditentukan di tiap task.
5. Jangan jalankan full test suite sampai Task 6.
6. Task 6 Step 3 butuh persetujuan user sebelum menjalankan full suite — TANYA dulu, jangan otomatis jalan.

## Pelajaran penting dari review Spec 1 (WAJIB diperhatikan di sini juga)

Review independen terhadap eksekusi Spec 1 menemukan pola kegagalan yang harus dihindari di sini:
1. **Jangan tandai task selesai di handoff log kalau isinya belum benar-benar diverifikasi dengan membaca ulang file hasil edit.** Beberapa sub-item Spec 1 (komentar, sinkronisasi data) ditandai selesai padahal tidak diterapkan — ketahuan lewat `git diff` langsung terhadap baseline, bukan lewat laporan.
2. **Kalau sebuah "perbaikan arsitektur" ternyata mematahkan test yang sudah ada, JANGAN paksakan dan JANGAN diam-diam melemahkan test itu untuk membuatnya lolos.** Investigasi dulu KENAPA test itu bergantung pada perilaku lama — kalau alasannya legitimate (misalnya fixture ringan yang dipakai puluhan test lain), perubahan arsitekturnya yang harus disesuaikan/dibatalkan, bukan test-nya yang dilemahkan.
3. Setiap klaim "sudah diverifikasi" di laporan/handoff log HARUS bisa ditelusuri ke command nyata yang benar-benar dijalankan (`grep` yang hasilnya kosong, `tinker` yang outputnya dicatat, dst) — bukan asumsi dari membaca kode.

## Kalau menemukan sesuatu yang tidak sesuai plan

Kode yang dikutip plan ini berbasis commit `f712786` di branch `rbac-v2` (state SETELAH Spec 1 selesai + review). Kalau kamu menemukan isi file yang BEDA signifikan dari yang dikutip plan (bukan cuma beda nomor baris, dan bukan salah satu skenario "Spec 1 sudah/belum dieksekusi" yang sudah diantisipasi plan), atau blok kode yang dicari plan ternyata TIDAK ADA sama sekali — STOP, jangan menebak atau "memperbaiki sendiri". Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Setelah kamu selesai

Tulis handoff log di `.agents/logs/2026-08-21-rebranding-data-demo-pintera.md` (format mengikuti handoff log Spec 1 sebelumnya di folder yang sama: ringkasan, riwayat commit per task, keputusan penting, hasil verifikasi akhir — DENGAN command nyata yang dijalankan, bukan cuma klaim). Sesi yang menulis plan ini kemungkinan akan melakukan review independen terhadap hasil kerjamu (termasuk menjalankan `git diff` terhadap baseline dan full test suite sendiri) — pastikan setiap commit bersih dan bisa ditelusuri (jangan squash / rewrite history), dan jangan menandai sesuatu "selesai" di handoff log kecuali kamu benar-benar sudah verifikasi isinya lewat command, bukan lewat ingatan/asumsi.

## Definisi selesai

Task 6 selesai: grep menyeluruh (`grep -rln "permatakraksaan.sch.id\|sistem\.test\|example\.test\|Ustadz\|PERMATA KRAKSAAN" database/seeders/`) menghasilkan output kosong, seluruh 6 task punya commit terpisah dengan test scoped hijau, full suite dijalankan SETELAH izin eksplisit user dan hasilnya hijau (0 failed, 0 error), handoff log tertulis di `.agents/logs/2026-08-21-rebranding-data-demo-pintera.md` dengan bukti verifikasi yang bisa ditelusuri.
