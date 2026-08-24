# Kickoff Prompt — Migrasi Domain Keuangan Sub-project 3: Pembayaran & Gateway

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan siap jalan. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

**INI SUB-PROJECT PALING BERISIKO DI SELURUH MIGRASI DOMAIN KEUANGAN — modul ini uang sungguhan yang benar-benar bergerak (debit wallet, verifikasi transfer, callback bank produksi). Baca semuanya dengan teliti sebelum mulai.**

## Yang harus kamu baca dulu, dalam urutan ini

1. `.agents/skills/laravel-feature-standard/SKILL.md` — standar arsitektur mengikat proyek ini.
2. `.agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md` — roadmap induk.
3. `.agents/specs/2026-08-24-refactor-02-keuangan-sp1-konfigurasi-tagihan.md` + `.agents/logs/2026-08-24-refactor-02-keuangan-sp1-konfigurasi-tagihan.md` — SP1 (SELESAI). **Baca addendum §4 log-nya** — 5 celah dari review independen, termasuk 1 celah HIGH (guard tenant-isolation hilang) dan 1 pembalikan arsitektur yang tidak diungkap.
4. `.agents/specs/2026-08-24-refactor-02-keuangan-sp2-alur-tagihan-inti.md` + `.agents/logs/2026-08-24-refactor-02-keuangan-sp2-alur-tagihan-inti.md` — SP2 (SELESAI). **Baca addendum §4 log-nya** — deviasi namespace (`WaliMurid` seharusnya `Portal\Keuangan`) yang tidak diungkap, baru ketahuan lewat review manual.
5. `.agents/specs/2026-08-24-refactor-02-keuangan-sp3-pembayaran-gateway.md` — spec item ini (§4 tabel response webhook byte-identical dan §7 daftar 7 guard WAJIB dibaca berulang kali sampai benar-benar paham).
6. `.agents/plans/2026-08-24-refactor-02-keuangan-sp3-pembayaran-gateway.md` — plan implementasi (19 task, kode lengkap per task, termasuk Task 16 webhook yang paling kritis).

Plan itu ditulis dengan asumsi kamu (executor) tidak punya konteks apa pun tentang codebase ini di luar isi plan dan isi repo — setiap task berisi kode lengkap, bukan deskripsi/pseudocode.

## Konteks singkat

- Repo Laravel 12 multi-tenant (`pintera-app`). Branch kerja: `refactor-v1` (SUDAH ADA, dipakai berurutan untuk Data Induk Sempit → Keuangan SP1 → SP2 → SP3 ini — jangan buat branch baru, jangan buat worktree).
- **Baseline kode plan ini: commit `ffe5400`.** Kalau isi file yang kamu baca BEDA signifikan dari yang dikutip plan, STOP, laporkan ke user — jangan menebak, apalagi di modul uang sungguhan ini.
- **SP3 dari 4 sub-project migrasi domain Keuangan.** SP4 (Wallet & Cicilan + Rekonsiliasi) BELUM ditulis plan-nya — JANGAN sentuh `Wallet`, `Cicilan`, `AutoAllocationEngine`, `SkipAlertResolver`, `PaymentAllocationService` (SATU KELAS UTUH, termasuk `allocate()`) — semuanya SENGAJA ditunda ke SP4 meski dipanggil aktif dari kode yang kamu migrasi (persis pola SP1 memanggil `TagihanBillingGenerator` sebelum SP2 memindahkannya — inject dari lokasi lama, jangan pindahkan).
- **Webhook BRI SNAP (`Api\BriVaInboundController` → `Api\Keuangan\BriVaInboundController`) WAJIB dimigrasi** — user eksplisit menegaskan ini di awal sesi ("tidak boleh ditunda termasuk webhook"), sempat diusulkan dikecualikan tapi ditolak user.
- **Response JSON webhook WAJIB byte-identical** — bukan cuma "nama route tidak berubah", tapi bentuk JSON persis (field, tipe string-desimal via `number_format(...,2,'.','')`, status HTTP) untuk SETIAP dari 11 kombinasi kondisi di §4 spec. URL path (`/snap/v1.0/...`) juga tidak boleh berubah (literal string, tidak terikat namespace).
- **Dua jalur pencatatan Pembayaran paralel** ikut migrasi sekaligus: jalur checkout modern (`PaymentService`+Gateway, Task 7/14) dan jalur verifikasi manual legacy (`PembayaranService`+`Admin\PembayaranController`, Task 8/10) — keduanya menulis ke model `Pembayaran` yang sama.

## 7 Guard Keamanan yang WAJIB Dipertahankan Persis (baca §7 spec, ini bukan opsional)

**Review SP1 menemukan 1 celah HIGH yang LOLOS full test suite** (tidak ada test yang menguji jalur itu, baru ketahuan lewat deep-review manual + probe test yang sengaja ditulis). **Review SP2 menemukan 1 deviasi namespace tak terungkap.** Di SP3 ini risikonya lebih tinggi — kalau guard hilang di sini, uang bisa hilang/dobel-kredit, bukan sekadar bug tampilan.

1. `Admin\PembayaranController::verifikasi()` (Task 10) — dua jalur resolusi lembaga (via `tagihan` ATAU `cicilan`).
2. `Admin\ManualPaymentController::approve()`/`reject()` (Task 11) — `siswaLembagaId()` bypass `TenantScope`.
3. **`Admin\ManualPaymentController::approve()` (Task 11) — GUARD DATA-CONSISTENCY PALING KRITIS.** Komentar aslinya sendiri bilang: *"Uang nyata terlibat — lebih baik gagal keras & jelas daripada salah diam-diam."* Task 11 Step 7 WAJIB kamu tulis 2 test baru yang eksplisit menyerang guard ini (bukan cuma "test lama masih lulus").
4. `Admin\VirtualAccountController::riwayat()` (Task 12) — pola `siswaLembagaId()` identik #2, JANGAN dikonsolidasi jadi 1 helper tanpa lapor ke user dulu.
5. `AuthorizesPembayaran::authorizePembayaran()` (Task 9, 14, 15) — cek kepemilikan orangTua-siswa, WAJIB tetap dipanggil di SETIAP titik akses `Pembayaran` oleh portal.
6. **Webhook `payment()` (Task 16) — urutan idempotency-check → validasi amount → VA lookup → insert log dengan disambiguasi genuine-duplicate vs real-failure.** Correctness-critical untuk cegah double-charge/double-credit. Task 16 Step 9 WAJIB kamu cek SEMUA 11 kombinasi §4 spec sudah ada test-nya, tambahkan yang belum ada.
7. `PembayaranService::catatPembayaran()` (Task 8) — mutual-exclusivity, row-lock, cek pembayaran-aktif, urutan cicilan berurutan.

**Kalau kamu menemukan diri INGIN menyederhanakan/menggabungkan/menghilangkan salah satu guard ini demi kode lebih "bersih" — JANGAN.** Itu persis pola kesalahan SP1. Salin PERSIS seperti dikutip di plan, jangan diparafrase.

## Cara eksekusi

**Kalau kamu punya akses ke skill `superpowers`:**
Gunakan `superpowers:subagent-driven-development` — fresh subagent per task, task review, ledger progress di `.superpowers/refactor-keuangan-sp3/progress.md`.

**Kalau tidak punya skill itu:**
Eksekusi manual task-by-task sesuai urutan di plan (Task 1 → 19, urutannya SANGAT penting di sub-project ini — banyak task saling bergantung: Task 7 butuh Task 1/3/5, Task 16 butuh Task 1/3/5, Task 11/12/14 butuh Task 1/3/6/7/9, dst):
1. Baca task, kerjakan tiap step persis seperti ditulis (kode di plan itu final, jangan diparafrase — TERUTAMA Task 16 webhook).
2. WAJIB baca isi file existing dulu sebelum mengedit apapun, pastikan cocok dengan yang dikutip plan.
3. Untuk daftar "grep ulang untuk consumer" — WAJIB grep ulang untuk konfirmasi daftar masih akurat, scope `app database tests`.
4. Jalankan verifikasi (test scoped) SEBELUM commit — kalau ada yang gagal, JANGAN commit.
5. Satu commit per task.
6. Jangan jalankan full test suite sampai Task 19.
7. Task 19 Step 2 butuh persetujuan user EKSPLISIT sebelum full suite (Step 3).
8. Task 13 dan 18 murni gate verifikasi (tidak ada commit) — kalau ada temuan tidak sesuai, STOP dan perbaiki dulu.

## Pelajaran penting dari review pekerjaan sebelumnya di repo ini (WAJIB diperhatikan, lebih ketat di sini)

1. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
2. **Kalau kamu memutuskan menyimpang dari keputusan arsitektur eksplisit di plan — STOP dan laporkan ke user, JANGAN diam-diam menulis ulang keputusan itu di handoff log seolah itu keputusan bersih dari awal.** Ini terjadi 2× di sub-project sebelumnya (SP1: event dibalik ke model demi lolos test lama; SP2: namespace `WaliMurid` yang tidak disepakati) — KEDUANYA baru ketahuan lewat review manual, BUKAN dari laporan sendiri. Jangan sampai terjadi lagi, apalagi di modul uang sungguhan.
3. **Verifikasi grep WAJIB scope `app database tests`, bukan cuma `app/Models`.**
4. **Bahaya cari-ganti blanket** — JANGAN pakai `sed`/cari-ganti otomatis untuk apapun yang menyentuh file Blade atau webhook. Edit manual satu-per-satu.
5. **Kalau kamu terpaksa menyentuh file di luar daftar yang disebut plan — WAJIB laporkan eksplisit di handoff log.**
6. **Untuk Task 16 webhook khususnya**: setelah selesai, JANGAN cuma percaya test yang lulus — baca ulang SETIAP `response()->json()` di controller baru vs tabel §4 spec satu-per-satu, huruf demi huruf. Kalau ragu satu karakter pun, STOP dan tanya user, jangan menebak — sistem BRI eksternal bergantung pada bentuk response ini persis.

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Setelah kamu selesai

Task 19 Step 4-6 minta kamu menulis handoff log dan update roadmap induk — **WAJIB sebutkan eksplisit** daftar kondisi webhook yang sudah ada test-nya vs baru ditambahkan (jangan digeneralisir "semua sudah tertest"), dan konfirmasi checklist 7 guard di atas satu-per-satu. Sesi yang menulis plan ini kemungkinan akan melakukan **deep code review independen** terhadap hasil kerjamu — termasuk kemungkinan menulis probe test sendiri untuk menyerang guard keamanan dan membandingkan response webhook byte-per-byte, seperti yang terjadi di review SP1/SP2. Pastikan setiap commit bersih dan bisa ditelusuri (jangan squash/rewrite history).

## Definisi selesai

Task 1-17 selesai: seluruh model/service/contract/gateway/controller/webhook sudah di namespace `Domains\Keuangan\*`/`Lembaga\Keuangan\*`/`Portal\Keuangan\*`/`Api\Keuangan\*` sesuai spec §3, grep gabungan Task 18 Step 1 KOSONG total, `php artisan route:list` menunjukkan route name DAN url webhook sama persis seperti sebelum migrasi, 7 guard §7 semuanya terverifikasi tetap ada dengan test yang menyerang langsung (bukan cuma test lama lulus). Task 18-19 selesai: full suite dijalankan SETELAH izin eksplisit user dan hasilnya hijau (kecuali flaky yang sudah dikenal dan dikonfirmasi ulang sendirian), handoff log tertulis dengan bukti verifikasi yang bisa ditelusuri, roadmap induk sudah diupdate.
