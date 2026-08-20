# Kickoff Prompt — Perbaikan Bug Seeder + Konvensi Seeder Pintera

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan siap jalan. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu, dalam urutan ini

1. `.agents/specs/2026-08-21-audit-perbaikan-seeder.md` — spec (kenapa dan apa, hasil audit menyeluruh 59 file seeder)
2. `.agents/plans/2026-08-21-audit-perbaikan-seeder.md` — plan implementasi (6 task, lengkap dengan kode dan langkah)

Plan itu ditulis dengan asumsi kamu (executor) tidak punya konteks apa pun tentang codebase ini di luar isi plan dan isi repo — setiap task berisi kode PHP lengkap, bukan deskripsi/pseudocode.

## Konteks singkat

- Repo Laravel 11 multi-tenant (`pintera-app`), RBAC pakai `spatie/laravel-permission`. Branch kerja: `rbac-v2` (SUDAH ADA, jangan buat branch baru, jangan buat worktree).
- Tugasnya: perbaiki 18 temuan audit seeder (`database/seeders/*.php`, dipanggil dari `database/seeders/DatabaseSeeder.php`) — 4 Critical (snapshot permission super admin basi, role name salah target `super_admin` vs `yayasan_super_admin`, idempotency `Kasus` duplikat, akun super admin berpola email produksi dengan password lemah), plus High/Medium/Low lainnya. Ditutup dengan dokumen konvensi permanen `.agents/skills/seeder-standard/SKILL.md`.
- **Task 1 (Restrukturisasi RBAC) adalah fondasi seluruh task lain** — 1-tabel-1-seeder untuk `permissions`/`roles`/pivot `role_has_permissions`, file baru `RolePermissionAssignmentSeeder.php` WAJIB jadi entri PALING AKHIR di `DatabaseSeeder::run()`. Task 4 (konsolidasi `SarprasPengadaanDemoSeeder`) BUTUH Task 1 selesai duluan.
- **Task 2 mencakup 10 fungsi di `PendampinganSeeder.php`, bukan 7** — plan sudah mendokumentasikan alasannya di "Global Constraints" (3 fungsi tambahan ditemukan saat plan ditulis, punya bug idempotency yang sama persis). Kerjakan semua 10 sesuai urutan Step di Task 2, jangan cuma yang disebut spec.
- **Setiap task yang menyentuh `DatabaseSeeder::run()` WAJIB diverifikasi dengan benar-benar menjalankan `php artisan migrate:fresh --seed`** di database lokal (Laragon/MySQL sudah harus jalan) — bukan cuma baca kode. Setiap task punya command verifikasi eksplisit (tinker query, grep, dsb) di bagian akhir task-nya, jalankan semua.
- Task 1 Step 4 mengubah `SarprasPengadaanDemoSeeder.php` baris `callOnce([...])` SEBELUM Task 1 menghapus `SarprasPermissionSeeder.php`/`PengadaanPermissionSeeder.php` — urutan Step di dalam Task 1 penting, jangan dibalik (hapus file dulu baru edit callOnce akan bikin `migrate:fresh --seed` fatal error di antara langkah).

## Cara eksekusi

**Kalau kamu punya akses ke skill `superpowers` (Claude Code dengan plugin Superpowers terpasang):**
Gunakan skill `superpowers:subagent-driven-development` untuk mengeksekusi plan ini task-by-task — fresh subagent per task, task review, ledger progress di `.superpowers/sdd/progress.md`. Ini mode yang sama yang dipakai untuk RBAC v2, FASE 5.1, dan migrasi domain Kasus/Akademik sebelumnya di branch yang sama.

**Kalau kamu tidak punya skill itu (agent lain / tanpa Superpowers):**
Eksekusi manual task-by-task sesuai urutan di plan (Task 1 → 6, urutannya penting — Task 4 butuh Task 1 & Task 3 selesai, ikuti bagian "Interfaces: Consumes" di tiap task):
1. Baca task, kerjakan tiap step-nya persis seperti ditulis (kode di plan itu final, jangan diparafrase).
2. Jalankan verifikasi manual (`migrate:fresh --seed`, tinker, grep) DAN test scoped yang disebut di tiap task SEBELUM commit — kalau ada yang gagal, JANGAN commit, cari tahu kenapa dulu.
3. Satu commit per task, pakai pesan commit yang sudah ditentukan di tiap task.
4. Jangan jalankan full test suite sampai Task 6.
5. Task 6 Step 3 butuh persetujuan user sebelum menjalankan full suite — TANYA dulu, jangan otomatis jalan.

## Kalau menemukan sesuatu yang tidak sesuai plan

Kode yang dikutip plan ini berbasis commit `67a8727` di branch `rbac-v2`. Plan sudah dicek langsung terhadap isi file asli (bukan diringkas dari memori), termasuk 2 temuan tambahan yang sudah didokumentasikan di "Global Constraints" plan. Kalau kamu menemukan isi file yang BEDA signifikan dari yang dikutip plan (bukan cuma beda nomor baris), atau blok kode yang dicari plan ternyata TIDAK ADA sama sekali — STOP, jangan menebak atau "memperbaiki sendiri". Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

Plan ini TIDAK mencakup rebranding email/nama sekolah/nama manusia (itu Spec 2 terpisah, `.agents/specs/2026-08-21-rebranding-data-demo-pintera.md`, BELUM ada plan implementasinya) — jangan mengerjakan bagian itu meskipun terlihat berkaitan, itu di luar cakupan tugas ini.

## Setelah kamu selesai

Tulis handoff log di `.agents/logs/2026-08-21-audit-perbaikan-seeder.md` (format mengikuti handoff log migrasi Kasus/Akademik sebelumnya di folder yang sama: ringkasan, riwayat commit per task, keputusan penting, hasil verifikasi akhir, hal yang perlu direview). Sesi yang menulis plan ini (bukan kamu) kemungkinan akan melakukan review terhadap hasil kerjamu — pastikan setiap commit bersih dan bisa ditelusuri (jangan squash / rewrite history).

## Definisi selesai

Task 6 selesai: `.agents/skills/seeder-standard/SKILL.md` sudah ada, seluruh 6 task punya commit terpisah dengan test scoped hijau, full suite dijalankan SETELAH izin eksplisit user dan hasilnya hijau (0 failed, 0 error), handoff log tertulis di `.agents/logs/2026-08-21-audit-perbaikan-seeder.md`.
