# Kickoff Prompt — Migrasi Domain Keuangan Sub-project 4 (TERAKHIR): Wallet & Cicilan + Rekonsiliasi

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan siap jalan. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

**INI SUB-PROJECT PENUTUP DARI SELURUH MIGRASI DOMAIN KEUANGAN (4 sub-project). Setelah kamu selesai, TIDAK ADA lagi kesempatan menunda apapun — audit final di Task 12 harus membuktikan domain Keuangan benar-benar tuntas dipindah.**

## Yang harus kamu baca dulu, dalam urutan ini

1. `.agents/skills/laravel-feature-standard/SKILL.md` — standar arsitektur mengikat proyek ini.
2. `.agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md` — roadmap induk.
3. `.agents/logs/2026-08-24-refactor-02-keuangan-sp1-konfigurasi-tagihan.md` — SP1 (SELESAI). **Baca addendum §4** — 5 celah dari review independen, termasuk 1 celah HIGH (guard tenant-isolation hilang, lolos full test suite, baru ketahuan lewat probe test manual) dan 1 pembalikan arsitektur yang tidak diungkap ke user.
4. `.agents/logs/2026-08-24-refactor-02-keuangan-sp2-alur-tagihan-inti.md` — SP2 (SELESAI). **Baca addendum §4** — deviasi namespace (`WaliMurid` seharusnya `Portal\Keuangan`) yang tidak diungkap, baru ketahuan lewat review manual.
5. `.agents/logs/2026-08-24-refactor-02-keuangan-sp3-pembayaran-gateway.md` — SP3 (SELESAI, **BERSIH** — tidak ada temuan HIGH/MEDIUM di review independen 4-subagent, hanya 2 catatan dokumentasi kosmetik). **Ini standar disiplin yang WAJIB kamu pertahankan di SP4.**
6. `.agents/specs/2026-08-24-refactor-02-keuangan-sp4-wallet-cicilan-rekonsiliasi.md` — spec sub-project ini (baca §6 keputusan desain, §9 daftar audit final, sampai benar-benar paham).
7. `.agents/plans/2026-08-24-refactor-02-keuangan-sp4-wallet-cicilan-rekonsiliasi.md` — plan implementasi (13 task, kode lengkap per task).

Plan itu ditulis dengan asumsi kamu (executor) tidak punya konteks apa pun tentang codebase ini di luar isi plan dan isi repo — setiap task berisi kode lengkap, bukan deskripsi/pseudocode.

## Konteks singkat

- Repo Laravel 12 multi-tenant (`pintera-app`). Branch kerja: `refactor-v1` (SUDAH ADA, dipakai berurutan untuk Data Induk Sempit → Keuangan SP1 → SP2 → SP3 → SP4 ini — jangan buat branch baru, jangan buat worktree).
- **Baseline kode plan ini: commit `5c71903`.** Kalau isi file yang kamu baca BEDA signifikan dari yang dikutip plan, STOP, laporkan ke user — jangan menebak, apalagi di modul uang sungguhan ini (Wallet menyimpan saldo real).
- **SP4 dari 4 sub-project migrasi domain Keuangan — INI YANG TERAKHIR.** `Wallet`, `WalletMutasi`, `Cicilan`, `AutoAllocationEngine`, `SkipAlertResolver`, `PaymentAllocationService` (SATU KELAS UTUH, `allocate()` DAN `topupSisaJikaAda()` tetap 1 file meski subjek datanya beda — keputusan sadar user, JANGAN dipecah), dan `Keuangan\DashboardController` semuanya migrasi di SP4 ini.
- **`WalletMutasi::pembayaran()` PERBAIKAN BUG DISENGAJA (Task 2)** — ini SATU-SATUNYA perubahan perilaku di seluruh SP4, PENGECUALIAN eksplisit dari default zero-behavior-change. Relasi ini rusak sejak SP3 memindahkan `Pembayaran` keluar dari `App\Models` (referensi implisit sama-namespace yang tidak pernah diperbaiki). Task 2 memperbaikinya SEBAGAI BAGIAN dari pemindahan namespace + menambahkan test regresi baru yang FAIL di kode lama, PASS setelah fix. **Catat perbaikan ini secara EKSPLISIT di handoff log — JANGAN disamakan/dicampur dengan klaim zero-behavior-change task lain.**

## Gotcha Dua Arah — TEMUAN BARU SP4, WAJIB dipahami sebelum mulai

Sub-project sebelumnya (SP1-3) hanya pernah menghadapi gotcha SATU ARAH: file yang TETAP di tempatnya mereferensikan class yang PINDAH lewat bare `ClassName::class` tanpa `use` — solusinya tambah FQCN di file yang tetap.

**SP4 punya gotcha ARAH SEBALIKNYA juga**: file yang justru PINDAH mereferensikan sibling class yang TETAP TINGGAL, lewat bare reference (karena dulu sama-namespace `app/Models`/`app/Services/Finance`). Begitu si mover pindah namespace, referensi itu putus. Plan sudah mengidentifikasi 3 titik ini secara eksplisit dan cara perbaikannya:

1. `Wallet.php` (Task 1) → `SystemSetting` (tetap di `App\Models`) — WAJIB tambah `use App\Models\SystemSetting;`.
2. `AutoAllocationEngine.php` (Task 5) → `NotificationDispatcher` (tetap di `App\Services\Finance`) — WAJIB tambah `use App\Services\Finance\NotificationDispatcher;`.
3. `PaymentAllocationService.php` (Task 7) → `NotificationDispatcher` (tetap di `App\Services\Finance`) — WAJIB tambah `use App\Services\Finance\NotificationDispatcher;`.

**Sebelum memindahkan file APAPUN di plan ini, baca ULANG isi lengkapnya dan cari SENDIRI apakah ada class name lain yang dipakai tanpa `use` jelas** — daftar di atas sudah diverifikasi tapi bukan jaminan tidak ada yang terlewat kalau isi file baseline ternyata berbeda dari yang dikutip plan.

## Cara eksekusi

**Kalau kamu punya akses ke skill `superpowers`:**
Gunakan `superpowers:subagent-driven-development` — fresh subagent per task, task review, ledger progress di `.superpowers/refactor-keuangan-sp4/progress.md`.

**Kalau tidak punya skill itu:**
Eksekusi manual task-by-task sesuai urutan di plan (Task 1 → 13, urutan penting — Task 4 dan 11 adalah gate verifikasi yang harus lulus sebelum lanjut, Task 5/6/7 butuh Task 1 selesai, Task 9 butuh Task 6 selesai, Task 10 butuh Task 9):
1. Baca task, kerjakan tiap step persis seperti ditulis (kode di plan itu final, jangan diparafrase).
2. WAJIB baca isi file existing dulu sebelum mengedit apapun, pastikan cocok dengan yang dikutip plan.
3. Untuk daftar "grep ulang untuk consumer" — WAJIB grep ulang untuk konfirmasi daftar masih akurat, scope `app database tests`, cari pola `App\Models\{ClassName}\b` (menangkap `use` DAN FQCN inline, bukan cuma baris `use` — pelajaran dari Data Induk Sempit, beberapa consumer `Cicilan` di SP4 sendiri pakai FQCN inline tanpa `use`).
4. Jalankan verifikasi (test scoped) SEBELUM commit — kalau ada yang gagal, JANGAN commit.
5. Satu commit per task.
6. Jangan jalankan full test suite sampai Task 13.
7. Task 13 Step 2 butuh persetujuan user EKSPLISIT sebelum full suite (Step 3).
8. Task 4, 11, dan 12 murni gate verifikasi/audit (tidak ada commit) — kalau ada temuan tidak sesuai, STOP dan perbaiki dulu sebelum lanjut task berikutnya.
9. **Task 12 (audit final) adalah task paling penting secara simbolis** — jalankan SEMUA command auditnya persis seperti ditulis, catat SEMUA output-nya kata demi kata untuk dikutip penuh di handoff log Task 13. Jangan diringkas jadi "sudah bersih" tanpa bukti command.

## Pelajaran penting dari review pekerjaan sebelumnya di repo ini

1. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
2. **Kalau kamu memutuskan menyimpang dari keputusan arsitektur eksplisit di plan — STOP dan laporkan ke user, JANGAN diam-diam menulis ulang keputusan itu di handoff log seolah itu keputusan bersih dari awal.** Ini terjadi 2× di SP1/SP2 (event dibalik ke model demi lolos test lama; namespace `WaliMurid` yang tidak disepakati) — KEDUANYA baru ketahuan lewat review manual, BUKAN dari laporan sendiri. SP3 berhasil BERSIH karena disiplin ini dijaga — pertahankan itu.
3. **Verifikasi grep WAJIB scope `app database tests`, bukan cuma `app/Models`.**
4. **Bahaya cari-ganti blanket** — JANGAN pakai `sed`/cari-ganti otomatis untuk apapun yang menyentuh file model/service/Blade. Edit manual satu-per-satu, terutama untuk gotcha dua arah yang butuh penambahan `use` baru (bukan cuma ganti string).
5. **Kalau kamu terpaksa menyentuh file di luar daftar yang disebut plan — WAJIB laporkan eksplisit di handoff log.**
6. **JANGAN jalankan lebih dari satu proses `php artisan test` bersamaan.** Review SP3 sempat rusak karena beberapa proses test overlap mengakses MySQL test database bersama (`pintera_app_test`) secara bersamaan, menyebabkan kegagalan palsu yang disalahartikan sebagai bug kode. Selalu tunggu satu proses test selesai total sebelum menjalankan yang lain.
7. **Untuk `WalletMutasi::pembayaran()` (Task 2) khususnya**: ini SATU-SATUNYA tempat perilaku BOLEH berubah. Tulis test regresi yang benar-benar membuktikan bug lama (jalankan test itu di kode SEBELUM fix untuk konfirmasi FAIL, baru terapkan fix, jalankan lagi untuk konfirmasi PASS) — jangan hanya menulis test yang lulus di kode baru tanpa pernah membuktikan ia gagal di kode lama.

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Setelah kamu selesai

Task 13 Step 4-6 minta kamu menulis handoff log dan update roadmap induk — **WAJIB sertakan PENUH** hasil audit final Task 12 (command + output persis, bukan ringkasan), konfirmasi eksplisit bug `WalletMutasi::pembayaran()` sudah diperbaiki dengan bukti test fail-lalu-pass, dan konfirmasi eksplisit 3 gotcha dua arah sudah ditangani. Sesi yang menulis plan ini kemungkinan akan melakukan **deep code review independen** terhadap hasil kerjamu — kemungkinan menggunakan beberapa subagent paralel dengan model/effort adaptif (pola yang dipakai untuk review SP3), termasuk menjalankan ulang audit final Task 12 secara independen untuk memverifikasi klaimmu. Pastikan setiap commit bersih dan bisa ditelusuri (jangan squash/rewrite history).

## Definisi selesai

Task 1-11 selesai: `Wallet`, `WalletMutasi`, `Cicilan` di `Domains\Keuangan\Models`; `AutoAllocationEngine`, `SkipAlertResolver`, `PaymentAllocationService` di `Domains\Keuangan\Services`; `Keuangan\DashboardController` jadi `Portal\Keuangan\DashboardController`; 3 gotcha dua arah semuanya diperbaiki; grep gabungan Task 11 Step 1 KOSONG total. Task 12 selesai: audit menyeluruh membuktikan TIDAK ADA sisa kode Keuangan di luar `app/Domains/Keuangan/` (kecuali pengecualian yang sudah dikonfirmasi domain lain: `TagihanGenerator`, `Admin\TagihanSusulanController`, `Portal\TagihanController`, `Keuangan\NotifikasiController`). Task 13 selesai: full suite dijalankan SETELAH izin eksplisit user dan hasilnya hijau (kecuali flaky yang sudah dikenal dan dikonfirmasi ulang sendirian), handoff log tertulis dengan bukti audit final yang bisa ditelusuri, roadmap induk diupdate dengan catatan **seluruh migrasi domain Keuangan (SP1-4) TUNTAS**.
