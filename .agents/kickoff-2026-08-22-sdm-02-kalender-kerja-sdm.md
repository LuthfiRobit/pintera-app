# Kickoff Prompt — Kehadiran SDM Sub-project 2 (Kalender Kerja SDM)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan siap jalan. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu, dalam urutan ini

1. `.agents/specs/2026-08-22-sdm-01-fondasi-kehadiran-admin-qr.md` — spec Sub-project 1 (konteks fondasi: model data, tenant isolation, RBAC dasar yang SUDAH ADA dan tidak berubah)
2. `.agents/specs/2026-08-22-sdm-02-kalender-kerja-sdm.md` — spec Sub-project 2 (kenapa dan apa: kalender kerja SDM independen dari kalender akademik)
3. `.agents/plans/2026-08-22-sdm-02-kalender-kerja-sdm.md` — plan implementasi Sub-project 2 (10 task, lengkap dengan kode PHP/Blade dan langkah verifikasi)

Plan itu ditulis dengan asumsi kamu (executor) tidak punya konteks apa pun tentang codebase ini di luar isi plan dan isi repo — setiap task berisi kode lengkap, bukan deskripsi/pseudocode.

## Konteks singkat

- Repo Laravel 11 multi-tenant (`pintera-app`). Branch kerja: `sdm-v1` (SUDAH ADA, jangan buat branch baru, jangan buat worktree).
- **Sub-project 1 (fondasi Kehadiran SDM) SUDAH SELESAI diimplementasi dan diverifikasi** di branch ini (baca handoff log `.agents/logs/2026-08-22-sdm-01-fondasi-kehadiran-admin-qr.md` kalau perlu detail). Domain `App\Domains\Sdm\` sudah ada berisi model `AttendanceMethodConfiguration`, `AttendancePoint`, `AttendanceEvent`, `AttendanceRecord`, `EmployeeQrCode`, Action `RecordManualAttendanceAction`/`ScanQrAttendanceAction`/dll, controller `AttendanceConfigurationController`/`AttendanceController`/`AttendanceQrScanController`. Sub-project 2 ini MEMPERLUAS kode yang sudah ada itu, BUKAN membuatnya dari nol — beberapa task memodifikasi file existing (bukan create), baca isi filenya SEBELUM mengedit untuk pastikan sesuai baseline yang dikutip plan.
- Sub-project 2 membangun **kalender kerja khusus SDM** — SENGAJA independen dari `App\Domains\Akademik\Models\KalenderAkademik`/`KalenderAkademikResolver` yang sudah ada (dipakai jadwal pelajaran murid). Dua kalender ini TIDAK BOLEH digabung, TIDAK saling menulis — hanya ada 1 fitur read-only "salin snapshot" satu arah dari akademik ke SDM.
- **2 hal WAJIB diperhatikan soal `TenantScope`, KEDUANYA sudah dijelaskan detail di Global Constraints plan**:
  1. `KalenderKerjaSdm` model WAJIB pakai `BelongsToTenant` (BEDA dari `KalenderAkademik` yang scope manual) — ini keputusan sadar, JANGAN "dikoreksi" jadi manual scope.
  2. `KalenderKerjaSdmResolver`, KEDUA query di dalamnya WAJIB `withoutGlobalScope(TenantScope::class)` — kalau lupa, resolver akan salah hasil (validasi libur nasional bocor total) untuk aktor `scope_level: lembaga` yang memanggilnya lewat request HTTP nyata (`RecordManualAttendanceAction`/`ScanQrAttendanceAction`). Ini BUKAN opsional, sudah dijelaskan detail kenapa di plan Task 2 dan diverifikasi test khusus (`KalenderKerjaSdmTenantIsolationTest`).
- TIDAK ADA hardcode nama role apapun — gerbang entri kalender nasional (`lembaga_id = null`) pakai `$request->user()->widestScopeLevel() === 'yayasan'`, BUKAN `hasRole(...)`.
- Shift kerja/jadwal per-pegawai (satpam, dinas luar) SENGAJA TIDAK dibangun di sub-project ini — itu Sub-project 3, jangan tergoda menambahkannya walau terasa "related".

## Cara eksekusi

**Kalau kamu punya akses ke skill `superpowers` (Claude Code dengan plugin Superpowers terpasang):**
Gunakan skill `superpowers:subagent-driven-development` untuk mengeksekusi plan ini task-by-task — fresh subagent per task, task review, ledger progress di `.superpowers/sdm-sdd-2/progress.md`.

**Kalau kamu tidak punya skill itu (agent lain / tanpa Superpowers):**
Eksekusi manual task-by-task sesuai urutan di plan (Task 1 → 10, urutannya penting — tiap task punya blok "Interfaces: Consumes/Produces" eksplisit yang menandai dependency ke task sebelumnya):
1. Baca task, kerjakan tiap step-nya persis seperti ditulis (kode di plan itu final, jangan diparafrase).
2. Untuk task yang MEMODIFIKASI file existing (Task 4, 5, 6 poin routes/console.php, 8, 9) — WAJIB baca isi file itu dulu sebelum mengedit, pastikan potongan kode yang dikutip plan ("Cari baris... Ganti jadi...") benar-benar cocok dengan isi file saat ini. Kalau beda, STOP dan laporkan (lihat bagian "Kalau menemukan sesuatu yang tidak sesuai" di bawah).
3. Jalankan verifikasi (test scoped) SEBELUM commit — kalau ada yang gagal, JANGAN commit, cari tahu kenapa dulu.
4. Satu commit per task, pakai pesan commit yang sudah ditentukan di tiap task Step terakhir.
5. Jangan jalankan full test suite sampai Task 10.
6. Task 10 Step 5 butuh persetujuan user secara EKSPLISIT sebelum menjalankan full suite (Step 6) — TANYA dulu, jangan otomatis jalan.

## Pelajaran penting dari review pekerjaan sebelumnya di repo ini (WAJIB diperhatikan)

1. **Jangan tandai step/task selesai kalau isinya belum benar-benar diverifikasi lewat command nyata.** Setiap klaim "sudah diverifikasi" harus bisa ditelusuri ke output command yang benar-benar dijalankan (test PASS dengan jumlah pasti) — bukan asumsi dari membaca kode.
2. **Kalau full suite menunjukkan kegagalan yang TIDAK terkait sama sekali dengan Kalender Kerja SDM, jangan langsung anggap itu masalah dari pekerjaanmu.** Ada pola flaky test pre-existing di branch ini (`KomponenPenilaianCrudTest`, `RaporPdfDataBuilderTest`) — jalankan ulang test yang gagal SENDIRIAN dulu untuk konfirmasi.
3. **Kalau plan mengasumsikan isi file existing yang ternyata sudah berbeda saat kamu baca** — JANGAN menebak atau "memperbaiki sendiri" secara diam-diam. Laporkan penyimpangannya ke user.
4. **Task 8 dan Task 9 masing-masing punya langkah eksplisit menjalankan ulang test Sub-project 1 yang berpotensi tersentuh** (`AttendanceConfigurationControllerTest.php`, `AttendanceControllerTest.php`) — JANGAN lewati langkah itu, itu jaring pengaman regresi-silang.
5. **Test tenant-isolation dan test bypass-TenantScope WAJIB benar-benar dijalankan dan hijau, jangan dilewati** — ini bukan test hiasan, tapi bukti bug class nyata (dijelaskan panjang di spec §4) tidak muncul lagi.

## Kalau menemukan sesuatu yang tidak sesuai plan

Kode yang dikutip plan ini berbasis commit `a8177da` di branch `sdm-v1`. Kalau kamu menemukan isi file yang BEDA signifikan dari yang dikutip plan (bukan cuma beda nomor baris), atau blok kode yang dicari plan ternyata TIDAK ADA sama sekali — STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Setelah kamu selesai

Task 10 Step 7 di plan sudah meminta kamu menulis handoff log ke `.agents/logs/2026-08-22-sdm-02-kalender-kerja-sdm.md` (ringkasan per task, commit hash, hasil verifikasi akhir dengan angka pasti). Sesi yang menulis plan ini kemungkinan akan melakukan review independen terhadap hasil kerjamu (termasuk `git diff` terhadap baseline dan menjalankan full test suite sendiri) — pastikan setiap commit bersih dan bisa ditelusuri (jangan squash / rewrite history).

## Definisi selesai

Task 10 selesai: seluruh test scoped Task 2-9 hijau bersama-sama (≥ 36 test baru), test Sub-project 1 yang disebut di Task 8/9 tetap hijau tanpa perubahan jumlah, full suite dijalankan SETELAH izin eksplisit user dan hasilnya hijau (0 failed, 0 error), grep `hasRole(` di area Sdm kosong, grep write-ke-`KalenderAkademik` dari domain Sdm kosong, handoff log tertulis di `.agents/logs/2026-08-22-sdm-02-kalender-kerja-sdm.md` dengan bukti verifikasi yang bisa ditelusuri (bukan klaim tanpa command).
