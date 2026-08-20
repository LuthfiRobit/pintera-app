# Kickoff Prompt — Migrasi Domain Kasus ke `Domains\Kasus`

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan siap jalan. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu, dalam urutan ini

1. `.agents/skills/laravel-feature-standard/SKILL.md` — standar arsitektur wajib
2. `.agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md` §3 (Prinsip Arsitektur Mengikat) — aturan baku yang berlaku semua sub-task refactor di proyek ini
3. `.agents/specs/2026-08-20-2000-kasus-domain-migrasi.md` — spec (kenapa dan apa, hasil audit blast-radius, keputusan KasusPolicy, keputusan lokasi view)
4. `.agents/plans/2026-08-20-2000-kasus-domain-migrasi.md` — plan implementasi (13 task, kode lengkap per step)

Plan ditulis dengan asumsi kamu (executor) tidak punya konteks apa pun tentang codebase ini di luar isi plan dan isi repo — setiap task berisi kode lengkap, bukan deskripsi.

## Konteks singkat

- Repo Laravel 11 multi-tenant (`pintera-app`). Branch kerja: `rbac-v2` (SUDAH ADA, jangan buat branch baru, jangan buat worktree).
- Tugasnya: migrasikan modul Pendampingan/Kasus (10 controller, 1042 baris, 6 model) — domain BARU sepenuhnya, `app/Domains/Kasus/` belum pernah ada sebelumnya (beda dari migrasi Akademik sebelumnya yang domainnya sudah ada).
- **Zero-behavior-change untuk SEMUA bagian** KECUALI konsolidasi `KasusPolicy` (Task 4) — dan bahkan itu HARUS menghasilkan keputusan izin/tolak identik untuk semua kombinasi role yang sudah ada, cuma lokasi kodenya disatukan (menggantikan 4 salinan duplikat logic otorisasi yang tersebar).
- **Kendala keamanan WAJIB dipertahankan**: `Route::bind('kasus', ...)` di `routes/kasus.php` sengaja bypass `TenantScope` (akun orang tua tidak punya `lembaga_id`). JANGAN PERNAH query ulang `Kasus` di dalam Action/Policy dengan asumsi TenantScope normal — selalu terima objek yang sudah di-resolve dari route binding. Puluhan query `withoutGlobalScope(TenantScope::class)` lain di 10 controller ini WAJIB dipertahankan persis, termasuk komentar penjelasannya.
- **View WAJIB ikut pindah** (Task 12) — TAPI plan sengaja menunda perubahan nama `view()` sampai Task 12 (Task 5-11 memindahkan LOGIC controller tapi nama `view()` TETAP nama lama sampai file-nya benar-benar dipindah di Task 12) — ikuti urutan task PERSIS seperti tertulis, jangan diubah urutannya.
- **Bahaya nyata yang HARUS diwaspadai** (insiden nyata terjadi di migrasi sebelumnya): dot-notation dipakai untuk VIEW name DAN route name sekaligus, sering satu prefix sama (`kasus.xxx`). Cari-ganti WAJIB dibatasi baris `view(`/`@include(`/`assertViewIs(`/`->name()` SAJA — verifikasi wajib tiap kali habis edit view: `grep -rn "route('portals\." resources/views/portals/kasus resources/views/portals/lembaga/kasus` harus KOSONG.

## Cara eksekusi

**Kalau kamu punya akses ke skill `superpowers`:**
Gunakan skill `superpowers:subagent-driven-development` untuk mengeksekusi plan ini task-by-task — fresh subagent per task, task review, ledger progress di `.superpowers/sdd/progress.md`. Task 1-3 dan Task 5-11 punya ketergantungan urutan (baca bagian "Interfaces: Consumes" di tiap task) — JANGAN dispatch paralel, kerjakan berurutan Task 1 → 13.

**Kalau kamu tidak punya skill itu:**
Eksekusi manual task-by-task sesuai urutan di plan:
1. Baca task, kerjakan tiap step-nya persis seperti ditulis (kode di plan itu final, jangan diparafrase).
2. Jalankan test scoped yang disebut di tiap task SEBELUM commit — kalau ada yang gagal, JANGAN commit, cari tahu kenapa dulu.
3. Satu commit per task, pakai pesan commit yang sudah ditentukan di tiap task.
4. Jangan jalankan full test suite sampai Task 13.
5. Task 13 butuh persetujuan user sebelum menjalankan full suite — TANYA dulu, jangan otomatis jalan.

## Kalau menemukan sesuatu yang tidak sesuai plan

Kode yang dikutip plan berbasis commit `9cf2c0e` di branch `rbac-v2`. Kalau kamu menemukan isi file yang BEDA signifikan dari yang dikutip plan (bukan cuma beda nomor baris — plan sudah pakai pencarian teks, bukan nomor baris), atau blok kode yang dicari plan ternyata TIDAK ADA sama sekali — STOP, jangan menebak atau "memperbaiki sendiri". Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

Task 9 Step 8 secara eksplisit meminta kamu memverifikasi sendiri (bukan asumsi dari plan) apakah trait `AssertsKonselorPemegangKasus` masih dipakai di tempat lain sebelum menghapusnya — ikuti instruksi verifikasi itu persis, jangan hapus kalau masih ada pemakaian tersisa.

## Setelah kamu selesai

Sesi yang menulis plan ini (bukan kamu) akan melakukan REVIEW KODE DETAIL terhadap seluruh hasil kerjamu setelah kamu laporkan selesai. Pastikan setiap commit bersih dan bisa ditelusuri (jangan squash / rewrite history), dan handoff log Task 13 mendokumentasikan semua keputusan/penyimpangan (kalau ada) dari plan secara jujur.

## Definisi selesai

Task 13 selesai: semua command `grep` verifikasi zero-leak (Step 1) menghasilkan output kosong, full suite hijau dengan jumlah test LEBIH BESAR dari baseline 1875 (ada test baru dari Task 4-11), tabel sub-task di master roadmap terupdate, handoff log tertulis di `.agents/logs/2026-08-20-2000-kasus-domain-migrasi.md`.
