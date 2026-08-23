# Kickoff Prompt — Scanner QR Kamera untuk Kehadiran SDM

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan siap jalan. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu, dalam urutan ini

1. `.agents/specs/2026-08-23-sdm-05-scanner-qr-kamera.md` — spec fitur ini (kenapa dan apa: scanner QR kamera browser, opsi tambahan berdampingan dengan input manual yang sudah ada)
2. `.agents/plans/2026-08-23-sdm-05-scanner-qr-kamera.md` — plan implementasi (3 task, lengkap dengan kode JS/Blade dan langkah verifikasi eksplisit)

Plan itu ditulis dengan asumsi kamu (executor) tidak punya konteks apa pun tentang codebase ini di luar isi plan dan isi repo — setiap task berisi kode lengkap, bukan deskripsi/pseudocode.

## Konteks singkat

- Repo Laravel 12 multi-tenant (`pintera-app`). Branch kerja: `sdm-v1` (SUDAH ADA, jangan buat branch baru, jangan buat worktree).
- Modul Kehadiran SDM (Sub-project 1-4 + item shift bergilir + redesain UI/UX) SUDAH SELESAI dan stabil di branch ini. Fitur ini MEMPERLUAS satu halaman yang sudah ada (`admin/kehadiran-sdm/scan.blade.php`) — bukan halaman baru.
- **Masalah yang diperbaiki:** halaman scan saat ini HANYA punya input teks token manual (didesain untuk scanner barcode fisik USB/Bluetooth). Tidak ada cara memindai QR pegawai memakai kamera device secara visual. Fitur ini menambahkan itu.
- **ATURAN KERAS: TIDAK ADA perubahan file PHP apapun** (controller/Action/route/model/permission) — `AttendanceQrScanController.php`, `ScanQrAttendanceAction.php`, dan semua route SDM TIDAK BOLEH disentuh sama sekali. Ini murni pekerjaan frontend (Blade + JS), backend endpoint yang sudah ada dipakai apa adanya.
- Library baru yang dipakai: `html5-qrcode` (npm) — dipilih user secara eksplisit lewat brainstorming (bukan `jsQR` atau `BarcodeDetector` native), karena paling stabil lintas browser termasuk Safari/iOS dan sudah menangani izin kamera + decode loop secara built-in.
- Task 1 plan juga sekalian membereskan 1 baris duplikat `Alpine.data('triaseForm', triaseForm);` di `app.js` (temuan dari review terpisah, kebetulan persis di area yang sedang disentuh) — jangan lewatkan langkah itu, tapi jangan perluas ke pembersihan lain di luar yang diminta plan.
- **Tidak ada cara otomatis menguji perilaku kamera browser sungguhan** (tidak ada kamera di lingkungan test PHP/CI) — Task 3 plan WAJIB dilakukan verifikasi manual di device desktop DAN device HP sungguhan, dicatat jujur per-skenario (PASS/FAIL) di handoff log, BUKAN diklaim "sudah ditest" tanpa detail nyata.

## Cara eksekusi

**Kalau kamu punya akses ke skill `superpowers` (Claude Code dengan plugin Superpowers terpasang):**
Gunakan skill `superpowers:subagent-driven-development` untuk mengeksekusi plan ini task-by-task — fresh subagent per task, task review, ledger progress di `.superpowers/sdm-scanner-qr/progress.md`. Task 3 (verifikasi manual browser) TIDAK BISA didelegasikan ke subagent otomatis — kamu (controller session) atau user sendiri yang harus benar-benar memegang device dan mencoba kameranya.

**Kalau kamu tidak punya skill itu (agent lain / tanpa Superpowers):**
Eksekusi manual task-by-task sesuai urutan di plan (Task 1 → 3, urutannya penting):
1. Baca task, kerjakan tiap step-nya persis seperti ditulis (kode di plan itu final, jangan diparafrase).
2. Task 2 Step 3 meminta kamu MENIMPA SELURUH ISI file `scan.blade.php` — baca dulu isi file saat ini untuk pastikan tidak ada perubahan lain yang sudah masuk di luar yang diasumsikan plan (baseline: commit `a7e30dd`). Kalau isi filenya sudah beda signifikan dari yang dikutip plan, STOP dan laporkan, jangan menebak/menggabungkan sendiri.
3. Jalankan verifikasi (test scoped) SEBELUM commit — kalau ada yang gagal, JANGAN commit, cari tahu kenapa dulu.
4. Satu commit per task, pakai pesan commit yang sudah ditentukan di tiap task Step terakhir.
5. Task 3 WAJIB dilakukan dengan device browser sungguhan — kalau kamu adalah agent tanpa akses ke browser/device fisik (mis. lingkungan headless), STOP di Task 3 dan minta user sendiri yang menjalankan checklist verifikasi manual itu, JANGAN mengarang hasil PASS.

## Pelajaran penting dari review pekerjaan sebelumnya di repo ini (WAJIB diperhatikan)

1. **Jangan tandai step/task selesai kalau isinya belum benar-benar diverifikasi lewat command atau uji coba nyata.** Setiap klaim "sudah diverifikasi" harus bisa ditelusuri ke output command yang benar-benar dijalankan (test PASS dengan jumlah pasti) atau hasil uji coba manual yang benar-benar dicoba di device — bukan asumsi dari membaca kode.
2. **Redesain UI SDM sebelumnya (`.agents/logs/redesain-uiux-sdm-presensi.md`) pernah menyentuh 2 file controller di luar scope yang diizinkan** tanpa disebut di handoff log-nya — itu ketahuan lewat review manual. Untuk fitur ini scope-nya jelas dan sempit (murni frontend); kalau kamu merasa PERLU menyentuh file PHP apapun untuk membuat fitur ini bekerja, itu tanda plan-nya salah asumsi — STOP dan laporkan ke user, JANGAN diam-diam melakukannya.
3. **Kalau full suite/test lain menunjukkan kegagalan yang TIDAK terkait sama sekali dengan fitur ini** (mis. test yang gagal karena kebetulan hari ini hari libur mingguan dan test memakai `now()` — pernah terjadi di modul ini), jangan langsung anggap itu masalah dari pekerjaanmu; jalankan ulang test yang gagal SENDIRIAN dulu untuk konfirmasi apakah memang terkait perubahanmu.
4. **Handoff log harus jujur dan bisa ditelusuri** — kalau ada bagian yang belum sempat/tidak bisa dicoba (misal tidak ada device HP tersedia saat eksekusi), tulis itu apa adanya sebagai catatan terbuka untuk user, JANGAN mengisi kolom PASS tanpa bukti.

## Kalau menemukan sesuatu yang tidak sesuai plan

Kode yang dikutip plan ini berbasis commit `a7e30dd` di branch `sdm-v1`. Kalau kamu menemukan isi file yang BEDA signifikan dari yang dikutip plan (bukan cuma beda nomor baris), atau blok kode yang dicari plan ternyata TIDAK ADA sama sekali — STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Setelah kamu selesai

Task 3 Step 4-5 di plan sudah meminta kamu menulis handoff log ke `.agents/logs/2026-08-23-sdm-05-scanner-qr-kamera.md` (ringkasan per task, commit hash, hasil test otomatis dengan angka pasti, DAN hasil verifikasi manual browser per-skenario yang jujur). Sesi yang menulis plan ini kemungkinan akan melakukan review independen terhadap hasil kerjamu (termasuk `git diff` terhadap baseline dan cross-check klaim di handoff log terhadap kode sungguhan) — pastikan setiap commit bersih dan bisa ditelusuri (jangan squash / rewrite history), dan jangan menulis klaim di handoff log yang tidak sesuai kode yang sebenarnya kamu tulis.

## Definisi selesai

Task 1-2 selesai: `npm run build` sukses, `AttendanceQrScanViewTest` (1 test baru) dan `AttendanceQrScanControllerTest` (2 test existing, regresi) sama-sama PASS, `git diff` terhadap SEMUA file PHP di repo (controller/Action/route/model/permission) sejak baseline `a7e30dd` KOSONG (tidak ada satupun file PHP yang berubah). Task 3 selesai: checklist verifikasi manual browser (desktop + HP + skenario izin ditolak) sudah benar-benar dicoba dan hasilnya (PASS/FAIL jujur, dengan device/browser yang dipakai) tertulis di handoff log `.agents/logs/2026-08-23-sdm-05-scanner-qr-kamera.md`.
