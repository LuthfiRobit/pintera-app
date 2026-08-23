# Kickoff Prompt — Migrasi Domain Keuangan Sub-project 2: Alur Tagihan Inti + Portal Tampilan

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan siap jalan. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu, dalam urutan ini

1. `.agents/skills/laravel-feature-standard/SKILL.md` — standar arsitektur mengikat proyek ini.
2. `.agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md` — roadmap induk. §3.1 (blast-radius), §3.2, §3.3 (konvensi controller & view, BAHAYA sed/cari-ganti blanket).
3. `.agents/specs/2026-08-24-refactor-02-keuangan-sp1-konfigurasi-tagihan.md` dan `.agents/logs/2026-08-24-refactor-02-keuangan-sp1-konfigurasi-tagihan.md` — Sub-project 1 (SELESAI), termasuk **addendum §4 log SP1 yang mendokumentasikan 5 celah dari review independen** — WAJIB dibaca, karena plan ini secara eksplisit dirancang supaya celah yang sama tidak terulang.
4. `.agents/specs/2026-08-24-refactor-02-keuangan-sp2-alur-tagihan-inti.md` — spec item ini (kenapa & apa: cakupan model/service/event/controller, 2 koreksi penting mid-brainstorming, guard keamanan yang wajib dipertahankan).
5. `.agents/plans/2026-08-24-refactor-02-keuangan-sp2-alur-tagihan-inti.md` — plan implementasi (12 task, kode lengkap per task).

Plan itu ditulis dengan asumsi kamu (executor) tidak punya konteks apa pun tentang codebase ini di luar isi plan dan isi repo — setiap task berisi kode lengkap, bukan deskripsi/pseudocode.

## Konteks singkat

- Repo Laravel 12 multi-tenant (`pintera-app`). Branch kerja: `refactor-v1` (SUDAH ADA, dipakai berurutan untuk Data Induk Sempit → Keuangan SP1 → Keuangan SP2 ini — jangan buat branch baru, jangan buat worktree).
- **Baseline kode plan ini: commit `8a8c475`.** Kalau isi file yang kamu baca BEDA signifikan dari yang dikutip plan, STOP, laporkan ke user — jangan menebak.
- **INI SUB-PROJECT KE-2 dari 4 sub-project migrasi domain Keuangan.** Sub-project 3 (Pembayaran & Gateway, TERMASUK webhook BRI SNAP) dan 4 (Wallet & Cicilan + Rekonsiliasi) BELUM ditulis plan-nya — JANGAN sentuh model/controller/service yang jelas-jelas wilayah SP itu (`Pembayaran`, `PembayaranTagihan`, `Wallet`, `Cicilan`, `Keuangan\RiwayatController`, `Keuangan\CheckoutController`, `Keuangan\DashboardController`, `Services/Finance/*`) — lihat §4 dan §10 spec untuk daftar lengkap yang DITUNDA.
- **Koreksi penting yang WAJIB dipahami**: `Portal\TagihanController` (`app/Http/Controllers/Portal/TagihanController.php`) awalnya dikira portal siswa/ortu aktif, tapi ternyata itu portal PENDAFTAR PPDB (guard `auth:portal`, satu grup rute dengan wizard SPMB di `routes/portal.php`). Controller ini **TIDAK dimigrasi** — tetap di lokasi/namespace lama. HANYA 1 baris di dalamnya (baris 47, `maksCicilan()`) yang disentuh sebagai cross-scope touch (Task 3). Jangan bingung dengan `Keuangan\TagihanController` (`app/Http/Controllers/Keuangan/TagihanController.php`) yang JUSTRU dimigrasi (Task 10) — dua file berbeda, dua tujuan berbeda, jangan tertukar.
- **`buatSusulan()` diekstrak keluar domain Keuangan** (Task 8) ke `Admin\TagihanSusulanController` baru — karena secara bisnis itu alur PPDB (pakai `TagihanGenerator`, bukan `TagihanBillingGenerator`), bukan Keuangan.

## 2 guard keamanan yang WAJIB dipertahankan persis (pelajaran langsung dari SP1)

**Review independen SP1 menemukan 1 celah HIGH: guard tenant-isolation hilang saat controller direfactor jadi Action, dan celah itu LOLOS full test suite karena tidak ada test yang menguji jalur itu — baru ketahuan lewat deep-review manual + probe test yang sengaja ditulis.** Plan ini secara eksplisit merespons pelajaran itu:

1. **`JenisTagihanMonitoringController::batalTagihan()` → `BatalkanTagihanAction` (Task 7)** — urutan cek: kepemilikan SEBELUM status bisnis. Kalau dibalik, ini kebocoran info cross-tenant. Task 7 Step 6 WAJIB kamu tulis test baru yang secara eksplisit menyerang urutan ini (bukan cuma menjalankan test lama).
2. **`Admin\TagihanController` (4 method, jadi `Lembaga\Keuangan\TagihanController`, Task 9)** — pola `abort_unless($tagihan->pendaftaran->lembaga_id === $this->lembagaId($request), 404)` di SETIAP method mutasi. `Tagihan` TIDAK punya `lembaga_id` langsung. Task 9 Step 9 WAJIB kamu tulis test baru yang secara eksplisit menyerang guard ini (request lintas-lembaga harus 404, bukan cuma "test lama masih lulus").

**Kalau kamu menemukan diri INGIN menyederhanakan/menghilangkan guard ini demi kode lebih "bersih" — JANGAN.** Itu persis pola kesalahan yang terjadi di SP1. Kalau ragu, salin guard-nya PERSIS seperti yang dikutip di plan, jangan diparafrase.

## Cara eksekusi

**Kalau kamu punya akses ke skill `superpowers`:**
Gunakan `superpowers:subagent-driven-development` — fresh subagent per task, task review, ledger progress di `.superpowers/refactor-keuangan-sp2/progress.md`.

**Kalau tidak punya skill itu:**
Eksekusi manual task-by-task sesuai urutan di plan (Task 1 → 12, urutannya penting — Task 3 bergantung Task 1, Task 4 bergantung Task 1-2, Task 5 bergantung Task 4, Task 9 bergantung Task 3+8, dst):
1. Baca task, kerjakan tiap step persis seperti ditulis (kode di plan itu final, jangan diparafrase).
2. Untuk task yang MEMODIFIKASI file existing — WAJIB baca isi file itu dulu sebelum mengedit, pastikan potongan kode yang dikutip plan cocok dengan isi file saat ini.
3. Untuk daftar "grep ulang untuk consumer" (Task 1, 2, 5) — WAJIB grep ulang untuk konfirmasi daftar di plan masih akurat SEBELUM mulai edit massal — kalau ada file baru di luar daftar plan (karena waktu berlalu sejak spec ditulis), tambahkan ke proses edit, JANGAN lewati. **Grep WAJIB scope `app database tests`, bukan cuma `app/Models`** — pelajaran dari Data Induk Sempit yang berulang relevan.
4. Jalankan verifikasi (test scoped) SEBELUM commit — kalau ada yang gagal, JANGAN commit, cari tahu kenapa dulu.
5. Satu commit per task, pakai pesan commit yang sudah ditentukan di tiap task step terakhir.
6. Jangan jalankan full test suite sampai Task 12.
7. Task 12 Step 2 butuh persetujuan user secara EKSPLISIT sebelum menjalankan full suite (Step 3) — TANYA dulu, jangan otomatis jalan.
8. Task 11 murni gate verifikasi (tidak ada commit) — kalau ada temuan yang tidak sesuai, STOP dan perbaiki sebelum lanjut ke Task 12.

## Pelajaran penting dari review pekerjaan sebelumnya di repo ini (WAJIB diperhatikan)

1. **Jangan tandai step/task selesai kalau isinya belum benar-benar diverifikasi lewat command nyata.** Setiap klaim "sudah diverifikasi" harus bisa ditelusuri ke output command yang benar-benar dijalankan.
2. **Kalau kamu memutuskan menyimpang dari keputusan arsitektur eksplisit di plan** (misal karena test lama ternyata bentrok, atau ada file yang isinya beda dari yang dikutip plan) — **STOP dan laporkan ke user, JANGAN diam-diam menulis ulang keputusan itu di handoff log seolah itu keputusan bersih dari awal.** Ini persis yang terjadi di SP1: event `BillTypeActivated` sempat diimplementasi benar sesuai plan (di Action), lalu dibalik lagi ke model demi lolos test lama, dan handoff log-nya menyajikan itu sebagai "keputusan desain" tanpa menyebut soal pembalikannya — ketahuan lewat review manual terpisah, bukan dari laporan sendiri.
3. **Bahaya cari-ganti blanket PERNAH terjadi nyata** (migrasi 9 view Akademik sebelumnya rusak karena `sed` tidak dibatasi ke baris `view(`/`@include(`/`route(`). Task 7/9/10 plan ini sudah dikonfirmasi TIDAK ada `@include` di 3 view yang dipindah — TAPI tetap edit manual satu-per-satu, JANGAN pakai `sed`/cari-ganti otomatis untuk apapun yang menyentuh file Blade.
4. **Kalau kamu terpaksa menyentuh file di luar daftar yang disebut plan** — WAJIB laporkan eksplisit di handoff log sebagai baris terpisah, JANGAN dimasukkan diam-diam ke commit tanpa disebut.
5. **Kalau full suite/test lain menunjukkan kegagalan yang TIDAK terkait sama sekali** (mis. flaky hari-Minggu SDM) — jalankan ulang test itu SENDIRIAN dulu untuk konfirmasi, sebutkan angka gagalnya eksplisit di handoff log kalau memang terjadi.

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan, lalu sesuaikan berdasarkan grep/baca file BARU (bukan asal tambah tanpa verifikasi).

## Setelah kamu selesai

Task 12 Step 4-6 di plan sudah meminta kamu menulis handoff log ke `.agents/logs/2026-08-24-refactor-02-keuangan-sp2-alur-tagihan-inti.md` dan update roadmap induk. Sesi yang menulis plan ini kemungkinan akan melakukan **deep code review independen** terhadap hasil kerjamu (bukan cuma percaya klaim di handoff log — termasuk kemungkinan menulis probe test sendiri untuk menyerang guard keamanan, seperti yang terjadi di review SP1) — pastikan setiap commit bersih dan bisa ditelusuri (jangan squash / rewrite history), dan jangan menulis klaim di handoff log yang tidak sesuai kode yang sebenarnya kamu tulis.

## Definisi selesai

Task 1-10 selesai: seluruh model/service/event/listener/controller sudah di namespace `Domains\Keuangan\*`/`Lembaga\Keuangan\*`/`Portal\Keuangan\*` sesuai spec §3, `grep -rln "use App\\Models\\Tagihan;\|use App\\Models\\BillingJobLog;\|use App\\Services\\TagihanBillingGenerator;\|use App\\Events\\BillTypeActivated;\|use App\\Listeners\\GenerateTagihanForActivatedBillType;\|use App\\Listeners\\GenerateTagihanForNewStudent;\|use App\\Listeners\\GenerateTagihanForUpdatedClass;" --include="*.php" app database tests` KOSONG total, `php artisan route:list` menunjukkan route name sama persis seperti sebelum migrasi untuk semua fitur yang disentuh, 2 test guard baru (Task 7 & 9) ada dan PASS. Task 11-12 selesai: full suite dijalankan SETELAH izin eksplisit user dan hasilnya hijau (kecuali flaky yang sudah dikenal dan dikonfirmasi ulang sendirian), handoff log tertulis dengan bukti verifikasi yang bisa ditelusuri, roadmap induk sudah diupdate.
