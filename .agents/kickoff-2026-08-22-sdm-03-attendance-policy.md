# Kickoff Prompt — Kehadiran SDM Sub-project 3 (Attendance Policy Dasar)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan siap jalan. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu, dalam urutan ini

1. `.agents/specs/2026-08-22-sdm-01-fondasi-kehadiran-admin-qr.md` — spec Sub-project 1 (konteks fondasi, tidak berubah)
2. `.agents/specs/2026-08-22-sdm-02-kalender-kerja-sdm.md` — spec Sub-project 2 (konteks kalender kerja, TIDAK diubah sub-project ini)
3. `.agents/specs/2026-08-22-sdm-03-attendance-policy.md` — spec Sub-project 3 (kenapa dan apa: Attendance Policy jam kerja/toleransi per kategori pegawai)
4. `.agents/plans/2026-08-22-sdm-03-attendance-policy.md` — plan implementasi Sub-project 3 (9 task, lengkap dengan kode PHP/Blade dan langkah verifikasi)

Plan itu ditulis dengan asumsi kamu (executor) tidak punya konteks apa pun tentang codebase ini di luar isi plan dan isi repo — setiap task berisi kode lengkap, bukan deskripsi/pseudocode.

## Konteks singkat

- Repo Laravel 11 multi-tenant (`pintera-app`). Branch kerja: `sdm-v1` (SUDAH ADA, jangan buat branch baru, jangan buat worktree).
- **Sub-project 1 (fondasi) dan Sub-project 2 (Kalender Kerja SDM) SUDAH SELESAI diimplementasi dan diverifikasi** di branch ini. Sub-project 3 ini MEMPERLUAS kode yang sudah ada — beberapa task memodifikasi file existing (`RecordManualAttendanceAction`, `ScanQrAttendanceAction`, `AttendanceRecordAggregator`, `TandaiAlpaOtomatisSdm`, `AttendanceConfigurationController`, `konfigurasi.blade.php`), baca isi filenya SEBELUM mengedit untuk pastikan sesuai baseline yang dikutip plan.
- Sub-project 3 membangun **Attendance Policy** (jam kerja + toleransi keterlambatan per `jenis_ptk`/`jenis_karyawan_id`) dan menambahkan kolom `is_late`/`late_minutes` ke `AttendanceRecord`.
- **ATURAN KERAS: `App\Domains\Sdm\Services\KalenderKerjaSdmResolver.php` (Sub-project 2) TIDAK BOLEH diubah/disentuh SAMA SEKALI** di plan ini. `AttendancePolicyResolver` (baru, dibangun di Task 3) MEMBUNGKUSNYA lewat constructor injection dan MENDELEGASIKAN ke situ kalau pegawai tidak punya Policy override. Task 9 Step 2 plan memverifikasi ini eksplisit lewat `git diff` — kalau ada perubahan sekecil apapun di file itu, itu artinya ada kesalahan yang harus diperbaiki sebelum lanjut.
- **`AttendancePolicy` model WAJIB `BelongsToTenant`, dan `AttendancePolicyResolver::resolvePolicy()` WAJIB `withoutGlobalScope(TenantScope::class)` di kedua query-nya** — pola dan alasan identik `KalenderKerjaSdmResolver` (sudah dijelaskan detail di Task 3, jangan dilewati).
- **`TandaiAlpaOtomatisSdm` (Task 6) diubah signifikan dari versi Sub-project 2**: BUKAN lagi cek libur di level lembaga dulu baru kondisional cek Policy — sekarang mengecek `AttendancePolicyResolver::resolveLibur()` PER PEGAWAI tanpa kecuali, karena celah auto-alpa ternyata 2 ARAH (pegawai yang Policy-nya menambah hari kerja MAUPUN yang menguranginya terhadap kalender lembaga) — dijelaskan detail alasannya di Task 6 Step 1, JANGAN "disederhanakan kembali" ke versi 1-arah yang lama.
- Unique index DB di `attendance_policies` TIDAK CUKUP mencegah duplikat sendirian (MySQL menganggap 2 baris NULL "tidak sama" untuk unique index) — WAJIB ada pengecekan `exists()` eksplisit di controller sebelum insert (Task 7), sudah dijelaskan detail di Global Constraints plan.
- TIDAK ADA hardcode nama role apapun — gerbang Policy nasional pakai `$request->user()->widestScopeLevel() === 'yayasan'`.
- Shift bergilir per periode/minggu SENGAJA TIDAK dibangun di sub-project ini — jangan tergoda menambahkannya walau terasa "related".

## Cara eksekusi

**Kalau kamu punya akses ke skill `superpowers` (Claude Code dengan plugin Superpowers terpasang):**
Gunakan skill `superpowers:subagent-driven-development` untuk mengeksekusi plan ini task-by-task — fresh subagent per task, task review, ledger progress di `.superpowers/sdm-sdd-3/progress.md`.

**Kalau kamu tidak punya skill itu (agent lain / tanpa Superpowers):**
Eksekusi manual task-by-task sesuai urutan di plan (Task 1 → 9, urutannya penting — tiap task punya blok "Interfaces: Consumes/Produces" eksplisit yang menandai dependency ke task sebelumnya):
1. Baca task, kerjakan tiap step-nya persis seperti ditulis (kode di plan itu final, jangan diparafrase).
2. Untuk task yang MEMODIFIKASI file existing — WAJIB baca isi file itu dulu sebelum mengedit, pastikan potongan kode yang dikutip plan cocok dengan isi file saat ini. Kalau beda, STOP dan laporkan.
3. Jalankan verifikasi (test scoped) SEBELUM commit — kalau ada yang gagal, JANGAN commit, cari tahu kenapa dulu.
4. Satu commit per task, pakai pesan commit yang sudah ditentukan di tiap task Step terakhir.
5. Jangan jalankan full test suite sampai Task 9.
6. Task 9 Step 5 butuh persetujuan user secara EKSPLISIT sebelum menjalankan full suite (Step 6) — TANYA dulu, jangan otomatis jalan.

## Pelajaran penting dari review pekerjaan sebelumnya di repo ini (WAJIB diperhatikan)

1. **Jangan tandai step/task selesai kalau isinya belum benar-benar diverifikasi lewat command nyata.** Setiap klaim "sudah diverifikasi" harus bisa ditelusuri ke output command yang benar-benar dijalankan (test PASS dengan jumlah pasti) — bukan asumsi dari membaca kode.
2. **Kalau full suite menunjukkan kegagalan yang TIDAK terkait sama sekali dengan Attendance Policy, jangan langsung anggap itu masalah dari pekerjaanmu.** Ada pola flaky test pre-existing di branch ini (`KomponenPenilaianCrudTest`, `RaporPdfDataBuilderTest`) — jalankan ulang test yang gagal SENDIRIAN dulu untuk konfirmasi.
3. **Kalau plan mengasumsikan isi file existing yang ternyata sudah berbeda saat kamu baca** — JANGAN menebak atau "memperbaiki sendiri" secara diam-diam. Laporkan penyimpangannya ke user.
4. **Task 4, 5, 6, 7, 8 masing-masing punya langkah eksplisit menjalankan ulang test Sub-project 1/2 yang berpotensi tersentuh** — JANGAN lewati langkah itu, itu jaring pengaman regresi-silang. Task 4 dan Task 6 khususnya mengganti isi file yang SUDAH ADA seluruhnya (bukan cuma nambah baris) — pastikan test lama tetap hijau SEMUA sebelum lanjut.
5. **Test tenant-isolation WAJIB benar-benar dijalankan dan hijau, jangan dilewati.**

## Kalau menemukan sesuatu yang tidak sesuai plan

Kode yang dikutip plan ini berbasis commit `3aece06` di branch `sdm-v1`. Kalau kamu menemukan isi file yang BEDA signifikan dari yang dikutip plan (bukan cuma beda nomor baris), atau blok kode yang dicari plan ternyata TIDAK ADA sama sekali — STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Setelah kamu selesai

Task 9 Step 7 di plan sudah meminta kamu menulis handoff log ke `.agents/logs/2026-08-22-sdm-03-attendance-policy.md` (ringkasan per task, commit hash, hasil verifikasi akhir dengan angka pasti). Sesi yang menulis plan ini kemungkinan akan melakukan review independen terhadap hasil kerjamu (termasuk `git diff` terhadap baseline dan menjalankan full test suite sendiri) — pastikan setiap commit bersih dan bisa ditelusuri (jangan squash / rewrite history).

## Definisi selesai

Task 9 selesai: seluruh test scoped Task 2-8 hijau bersama-sama (≥ 24 test baru), test Sub-project 1/2 yang disebut di Task 4/5/6/7/8 tetap hijau tanpa perubahan jumlah, full suite dijalankan SETELAH izin eksplisit user dan hasilnya hijau (0 failed, 0 error), grep `hasRole(` di area Sdm kosong, `git diff` terhadap `KalenderKerjaSdmResolver.php` sejak baseline plan KOSONG (file itu benar-benar tidak tersentuh), handoff log tertulis di `.agents/logs/2026-08-22-sdm-03-attendance-policy.md` dengan bukti verifikasi yang bisa ditelusuri (bukan klaim tanpa command).
