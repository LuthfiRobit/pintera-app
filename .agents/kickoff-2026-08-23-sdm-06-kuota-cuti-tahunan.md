# Kickoff Prompt — Kuota/Saldo Cuti Tahunan

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan siap jalan. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan (termasuk hasil review teknis eksplisit dari user soal race condition dan lintas-tahun kalender).

## Yang harus kamu baca dulu, dalam urutan ini

1. `.agents/specs/2026-08-22-sdm-04-izin-cuti-berjenjang.md` — spec Sub-project 4 (fondasi alur izin/cuti berjenjang, SUDAH SHIPPED, item ini memperluasnya)
2. `.agents/specs/2026-08-23-sdm-06-kuota-cuti-tahunan.md` — spec item ini (kenapa dan apa: kuota Cuti tahunan, arsitektur dihitung-ulang tanpa saldo, mitigasi race condition, larangan lintas-tahun)
3. `.agents/plans/2026-08-23-sdm-06-kuota-cuti-tahunan.md` — plan implementasi (6 task, lengkap dengan kode PHP/Blade dan langkah verifikasi eksplisit)

Plan itu ditulis dengan asumsi kamu (executor) tidak punya konteks apa pun tentang codebase ini di luar isi plan dan isi repo — setiap task berisi kode lengkap, bukan deskripsi/pseudocode.

## Konteks singkat

- Repo Laravel 12 multi-tenant (`pintera-app`). Branch kerja: `sdm-v1` (SUDAH ADA, jangan buat branch baru, jangan buat worktree).
- Sub-project 4 (Izin/Cuti Berjenjang, alur approval generik via `App\Domains\Workflow`) SUDAH SELESAI dan stabil. Item ini MEMPERLUAS `AjukanIzinCutiAction` yang sudah ada — baca isi filenya dulu sebelum edit, plan sudah mengutip baseline-nya persis untuk kamu bandingkan.
- **Arsitektur inti — WAJIB dipahami sebelum mulai**: TIDAK ADA tabel saldo/ledger kuota. Sisa kuota SELALU dihitung ulang on-the-fly dari `SUM(hari)` pengajuan Cuti tahun berjalan berstatus Pending/InReview/Approved. Ini keputusan desain SENGAJA (bukan kelalaian) — konsisten dengan pola "Resolver" yang sudah dipakai berulang di modul ini (`KalenderKerjaSdmResolver`, `AttendancePolicyResolver`, `ShiftAwareAttendanceResolver`). JANGAN menambahkan kolom/tabel saldo apapun.
- **`KuotaCutiResolver` (Task 2) query-nya SENGAJA berbeda bentuk dari `AttendancePolicyResolver`** yang sudah ada — `AttendancePolicyResolver` mewajibkan match persis per-jenis-pegawai (tidak ada baris "berlaku semua"), sedangkan `KuotaCutiResolver` punya resolusi 4-tingkat (spesifik lembaga → flat lembaga → spesifik nasional → flat nasional) supaya MVP bisa flat (1 baris NULL/NULL berlaku semua) TAPI tetap siap diperluas per-jenis nanti tanpa migrasi baru. JANGAN "diperbaiki" supaya sama persis `AttendancePolicyResolver` — baca catatan lengkapnya di Global Constraints plan.
- **Concurrency WAJIB ditangani** via `Cache::lock()` (Task 3) — bukan opsional, bukan "boleh dilewati kalau ribet". `CACHE_STORE` environment ini adalah `database` (cek `.env` sendiri kalau ragu). Test khusus untuk mekanisme lock ini akan memakan waktu nyata ~5 detik (menunggu timeout lock) — itu wajar, BUKAN test yang hang/bug.
- **Cuti lintas pergantian tahun kalender DITOLAK** (validasi keras) — TAPI ini KHUSUS kategori Cuti. Izin dan Sakit TIDAK terkena larangan ini sama sekali, tetap boleh lintas tahun seperti sekarang. Jangan sampai keliru menerapkan larangan ini ke semua kategori.
- **TIDAK ADA perubahan sama sekali** di `App\Domains\Workflow` (Model/Service/Action generik approval) dan `ProsesApprovalIzinCutiAction`. Task 6 Step 2 plan memverifikasi ini eksplisit lewat `git diff` — kalau ada perubahan sekecil apapun di file-file itu, itu kesalahan yang harus diperbaiki sebelum lanjut.

## Cara eksekusi

**Kalau kamu punya akses ke skill `superpowers` (Claude Code dengan plugin Superpowers terpasang):**
Gunakan skill `superpowers:subagent-driven-development` untuk mengeksekusi plan ini task-by-task — fresh subagent per task, task review, ledger progress di `.superpowers/sdm-kuota-cuti/progress.md`.

**Kalau kamu tidak punya skill itu (agent lain / tanpa Superpowers):**
Eksekusi manual task-by-task sesuai urutan di plan (Task 1 → 6, urutannya penting — tiap task punya blok "Interfaces: Consumes/Produces" eksplisit yang menandai dependency ke task sebelumnya):
1. Baca task, kerjakan tiap step-nya persis seperti ditulis (kode di plan itu final, jangan diparafrase).
2. Untuk task yang MEMODIFIKASI file existing (Task 3, 4, 5) — WAJIB baca isi file itu dulu sebelum mengedit, pastikan potongan kode yang dikutip plan cocok dengan isi file saat ini. Task 3 secara eksplisit mengutip seluruh baseline `AjukanIzinCutiAction.php` untuk kamu bandingkan dulu.
3. Jalankan verifikasi (test scoped) SEBELUM commit — kalau ada yang gagal, JANGAN commit, cari tahu kenapa dulu.
4. Satu commit per task, pakai pesan commit yang sudah ditentukan di tiap task Step terakhir.
5. Jangan jalankan full test suite sampai Task 6.
6. Task 6 Step 3 butuh persetujuan user secara EKSPLISIT sebelum menjalankan full suite (Step 4) — TANYA dulu, jangan otomatis jalan.

## Pelajaran penting dari review pekerjaan sebelumnya di repo ini (WAJIB diperhatikan)

1. **Jangan tandai step/task selesai kalau isinya belum benar-benar diverifikasi lewat command nyata.** Setiap klaim "sudah diverifikasi" harus bisa ditelusuri ke output command yang benar-benar dijalankan (test PASS dengan jumlah pasti) — bukan asumsi dari membaca kode.
2. **Handoff log sebelumnya di project ini (redesain UI SDM, scanner QR kamera) pernah menyentuh file di luar scope tanpa disebut di ringkasan "Apa yang Dikerjakan"** — ketahuan lewat review manual terpisah. Untuk item ini scope-nya jelas (9 file spesifik disebut per-task) — kalau kamu merasa PERLU menyentuh file lain di luar yang disebut task manapun, itu tanda plan-nya salah asumsi, STOP dan laporkan ke user, JANGAN diam-diam melakukannya lalu tidak disebut di handoff log.
3. **Kalau full suite/test lain menunjukkan kegagalan yang TIDAK terkait sama sekali dengan kuota cuti** (mis. `ScanQrAttendanceActionTest` gagal karena kebetulan hari eksekusi hari Minggu — flaky yang sudah dikenal di modul ini karena test itu memakai `now()`), jangan langsung anggap itu masalah dari pekerjaanmu; jalankan ulang test yang gagal SENDIRIAN dulu untuk konfirmasi.
4. **Handoff log harus jujur dan bisa ditelusuri** — test mekanisme lock (Task 3) itu MEMBUKTIKAN mekanisme lock bekerja secara isolasi dalam 1 proses, BUKAN bukti race-condition-proof penuh dengan request paralel nyata (keterbatasan Pest, sudah dijelaskan eksplisit di spec §3.6). Jangan mengklaim lebih dari itu di handoff log.

## Kalau menemukan sesuatu yang tidak sesuai plan

Kode yang dikutip plan ini berbasis commit `210c673` di branch `sdm-v1`. Kalau kamu menemukan isi file yang BEDA signifikan dari yang dikutip plan (bukan cuma beda nomor baris), atau blok kode yang dicari plan ternyata TIDAK ADA sama sekali — STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Setelah kamu selesai

Task 6 Step 5-6 di plan sudah meminta kamu menulis handoff log ke `.agents/logs/2026-08-23-sdm-06-kuota-cuti-tahunan.md` (ringkasan per task, commit hash, hasil verifikasi test dengan angka pasti, hasil `git diff` terhadap file-file yang dilarang berubah). Sesi yang menulis plan ini kemungkinan akan melakukan review independen terhadap hasil kerjamu (termasuk `git diff` terhadap baseline dan cross-check klaim di handoff log terhadap kode sungguhan) — pastikan setiap commit bersih dan bisa ditelusuri (jangan squash / rewrite history), dan jangan menulis klaim di handoff log yang tidak sesuai kode yang sebenarnya kamu tulis.

## Definisi selesai

Task 1-5 selesai: seluruh test scoped tiap task hijau bersama-sama (~20+ test baru), `git diff 210c673..HEAD -- app/Domains/Workflow/ app/Domains/Sdm/Actions/ProsesApprovalIzinCutiAction.php app/Domains/Sdm/Services/KalenderKerjaSdmResolver.php app/Domains/Sdm/Services/AttendancePolicyResolver.php app/Domains/Sdm/Services/ShiftAwareAttendanceResolver.php` KOSONG total. Task 6 selesai: full suite dijalankan SETELAH izin eksplisit user dan hasilnya hijau (kecuali flaky yang sudah dikenal dan dikonfirmasi ulang sendirian), handoff log tertulis di `.agents/logs/2026-08-23-sdm-06-kuota-cuti-tahunan.md` dengan bukti verifikasi yang bisa ditelusuri (bukan klaim tanpa command).
