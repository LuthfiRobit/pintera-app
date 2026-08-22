# Kickoff Prompt — Kehadiran SDM Sub-project 4 (TERAKHIR): Izin/Cuti Berjenjang

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan siap jalan. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu, dalam urutan ini

1. `.agents/specs/2026-08-22-sdm-01-fondasi-kehadiran-admin-qr.md` — spec Sub-project 1 (konteks fondasi, tidak berubah)
2. `.agents/specs/2026-08-22-sdm-02-kalender-kerja-sdm.md`, `sdm-03-attendance-policy.md`, `sdm-03b-shift-bergilir.md` — spec Sub-project 2, 3, item tertunda (konteks, TIDAK diubah sub-project ini)
3. `.agents/specs/2026-08-22-sdm-04-izin-cuti-berjenjang.md` — spec Sub-project 4 (kenapa dan apa: pengajuan izin/cuti mandiri + approval berjenjang)
4. `.agents/plans/2026-08-22-sdm-04-izin-cuti-berjenjang.md` — plan implementasi (10 task, lengkap dengan kode PHP/Blade dan langkah verifikasi)

Plan itu ditulis dengan asumsi kamu (executor) tidak punya konteks apa pun tentang codebase ini di luar isi plan dan isi repo — setiap task berisi kode lengkap, bukan deskripsi/pseudocode.

## Konteks singkat

- Repo **Laravel 12** (BUKAN Laravel 11 — cek `composer.json`: `"laravel/framework": "^12.0"` — kalau kamu terbiasa asumsi versi dari nama file/dokumentasi lama, JANGAN, pastikan API yang dipakai kompatibel Laravel 12), multi-tenant (`pintera-app`). Branch kerja: `sdm-v1` (SUDAH ADA, jangan buat branch baru, jangan buat worktree).
- **Ini SUB-PROJECT TERAKHIR dari 4 sub-project resmi modul Kehadiran SDM.** Sub-project 1, 2, 3 (termasuk item tertunda Shift Bergilir) SUDAH SELESAI diimplementasi dan diverifikasi di branch ini. Setelah plan ini selesai, SELURUH modul Kehadiran SDM (sesuai PRD awal) resmi tuntas.
- Sub-project ini membangun **pengajuan izin/sakit/cuti mandiri** oleh Guru/Karyawan + **approval berjenjang 2 lapis** (Kepala Sekolah → Admin SDM) dengan **REUSE TOTAL** domain generik `App\Domains\Workflow` yang SUDAH DIPAKAI Rapor Akademik & Pengadaan Sarpras.
- **ATURAN PALING KERAS di plan ini: `App\Domains\Workflow\Models\*`, `Services\ApproverResolverService.php`, `Actions\InitializeApprovalRequestAction.php`, `Actions\ProcessApprovalAction.php` TIDAK BOLEH ada perubahan LOGIC SAMA SEKALI.** HANYA `Enums\ApprovalStatus.php` dan `Enums\ApprovalAction.php` yang boleh disentuh, dan HANYA berupa PENAMBAHAN 1 case baru masing-masing (`Cancelled`/`Cancel`) — TIDAK ADA baris dihapus/diubah di file itu. Task 10 Step 2-3 plan memverifikasi ini eksplisit lewat `git diff` — kalau ada perubahan LOGIC di file manapun dalam daftar itu, atau ada baris DIHAPUS di kedua enum itu, STOP, itu kesalahan fatal yang harus diperbaiki sebelum lanjut.
- **JANGAN meniru pola `App\Http\Controllers\Yayasan\Pengadaan\ApprovalPengadaanController.php`** kalau kamu membacanya sebagai referensi (disebutkan di plan sebagai contoh konkret pola reuse) — file itu punya 1 baris `$user->hasRole(['super_admin', 'yayasan_super_admin'])` (hardcode role) yang BERTENTANGAN dengan disiplin modul SDM ini. Controller baru WAJIB pakai `$this->authorize('kehadiran-sdm.izin.xxx')` SAJA.
- **Task 10 WAJIB menjalankan test suite Rapor Akademik DAN Pengadaan Sarpras secara eksplisit** (`php artisan test --filter=Rapor` dan `--filter=Pengadaan`), BUKAN cuma full-suite-di-akhir — ini SATU-SATUNYA sub-project SDM yang menyentuh file shared lintas domain, jadi regresi ke domain lain harus dibuktikan konkret, bukan diasumsikan aman.
- TIDAK ADA hardcode nama role apapun.
- TIDAK membangun kuota/saldo cuti — di luar cakupan.

## Cara eksekusi

**Kalau kamu punya akses ke skill `superpowers` (Claude Code dengan plugin Superpowers terpasang):**
Gunakan skill `superpowers:subagent-driven-development` untuk mengeksekusi plan ini task-by-task — fresh subagent per task, task review, ledger progress di `.superpowers/sdm-sdd-4/progress.md`.

**Kalau kamu tidak punya skill itu (agent lain / tanpa Superpowers):**
Eksekusi manual task-by-task sesuai urutan di plan (Task 1 → 10, urutannya penting — tiap task punya blok "Interfaces: Consumes/Produces" eksplisit yang menandai dependency ke task sebelumnya):
1. Baca task, kerjakan tiap step-nya persis seperti ditulis (kode di plan itu final, jangan diparafrase).
2. Untuk task yang MEMODIFIKASI file existing (terutama Task 1 Step 5-6 yang menyentuh enum SHARED) — WAJIB baca isi file itu dulu sebelum mengedit, pastikan potongan kode yang dikutip plan cocok dengan isi file saat ini. Kalau beda, STOP dan laporkan.
3. Jalankan verifikasi (test scoped) SEBELUM commit — kalau ada yang gagal, JANGAN commit, cari tahu kenapa dulu.
4. Satu commit per task, pakai pesan commit yang sudah ditentukan di tiap task Step terakhir.
5. Jangan jalankan full test suite sampai Task 10.
6. Task 10 Step 6 butuh persetujuan user secara EKSPLISIT sebelum menjalankan full suite (Step 7) — TANYA dulu, jangan otomatis jalan.

## Pelajaran penting dari review pekerjaan sebelumnya di repo ini (WAJIB diperhatikan)

1. **Jangan tandai step/task selesai kalau isinya belum benar-benar diverifikasi lewat command nyata.** Setiap klaim "sudah diverifikasi" harus bisa ditelusuri ke output command yang benar-benar dijalankan (test PASS dengan jumlah pasti) — bukan asumsi dari membaca kode.
2. **Kalau full suite menunjukkan kegagalan yang TIDAK terkait sama sekali dengan Izin/Cuti, jangan langsung anggap itu masalah dari pekerjaanmu.** Ada pola flaky test pre-existing di branch ini (`KomponenPenilaianCrudTest`, `RaporPdfDataBuilderTest`) — jalankan ulang test yang gagal SENDIRIAN dulu untuk konfirmasi.
3. **Kalau plan mengasumsikan isi file existing yang ternyata sudah berbeda saat kamu baca** — JANGAN menebak atau "memperbaiki sendiri" secara diam-diam. Laporkan penyimpangannya ke user.
4. **Task 1 Step 7 (verifikasi tinker kedua enum shared) WAJIB benar-benar dijalankan dan hasilnya dibaca** — ini bukti langsung `UnhandledMatchError` tidak terjadi untuk SEMUA case (lama maupun baru), bukan cuma asumsi teoretis "aditif pasti aman".
5. **Task 7 mengubah `TandaiAlpaOtomatisSdm.php` LAGI (sudah diubah 2x sebelumnya di Sub-project 2 & 3b)** — WAJIB jalankan ulang SEMUA 9 test lama file itu sebelum menambah yang baru, pastikan tidak regresi terhadap perilaku shift-aware maupun kalender-aware yang sudah ada.

## Kalau menemukan sesuatu yang tidak sesuai plan

Kode yang dikutip plan ini berbasis commit `5d86314` di branch `sdm-v1`. Kalau kamu menemukan isi file yang BEDA signifikan dari yang dikutip plan (bukan cuma beda nomor baris), atau blok kode yang dicari plan ternyata TIDAK ADA sama sekali — STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Setelah kamu selesai

Task 10 Step 8 di plan sudah meminta kamu menulis handoff log ke `.agents/logs/2026-08-22-sdm-04-izin-cuti-berjenjang.md` (ringkasan per task, commit hash, hasil verifikasi akhir dengan angka pasti, TERMASUK hasil test Rapor & Pengadaan secara eksplisit). Sesi yang menulis plan ini kemungkinan akan melakukan review independen terhadap hasil kerjamu (termasuk `git diff` terhadap baseline dan menjalankan full test suite sendiri) — pastikan setiap commit bersih dan bisa ditelusuri (jangan squash / rewrite history).

## Definisi selesai

Task 10 selesai: seluruh test scoped Task 2-9 hijau bersama-sama (≥ 22 test baru), test Sub-project 1-3b tetap hijau tanpa perubahan jumlah, test Rapor Akademik DAN Pengadaan Sarpras dijalankan eksplisit dan hijau, full suite dijalankan SETELAH izin eksplisit user dan hasilnya hijau (0 failed, 0 error), grep `hasRole(` kosong, `git diff` terhadap `Models`/`Services`/`Actions` Workflow domain sejak baseline plan KOSONG total, diff kedua enum shared HANYA berisi penambahan (tidak ada baris dihapus), handoff log tertulis dengan bukti verifikasi yang bisa ditelusuri (bukan klaim tanpa command). **Setelah task ini selesai, SELURUH modul Kehadiran SDM (4 sub-project resmi) dinyatakan tuntas.**
