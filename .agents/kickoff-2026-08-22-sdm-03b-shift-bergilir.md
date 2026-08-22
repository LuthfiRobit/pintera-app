# Kickoff Prompt — Penugasan Shift per Periode (Item Tertunda Sub-project 3)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan siap jalan. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu, dalam urutan ini

1. `.agents/specs/2026-08-22-sdm-01-fondasi-kehadiran-admin-qr.md` — spec Sub-project 1 (konteks fondasi, tidak berubah)
2. `.agents/specs/2026-08-22-sdm-02-kalender-kerja-sdm.md` — spec Sub-project 2 (konteks kalender kerja, TIDAK diubah item ini)
3. `.agents/specs/2026-08-22-sdm-03-attendance-policy.md` — spec Sub-project 3 (konteks Attendance Policy, TIDAK diubah item ini)
4. `.agents/specs/2026-08-22-sdm-03b-shift-bergilir.md` — spec item ini (kenapa dan apa: penugasan shift manual per periode)
5. `.agents/plans/2026-08-22-sdm-03b-shift-bergilir.md` — plan implementasi (9 task, lengkap dengan kode PHP/Blade/JS dan langkah verifikasi)

Plan itu ditulis dengan asumsi kamu (executor) tidak punya konteks apa pun tentang codebase ini di luar isi plan dan isi repo — setiap task berisi kode lengkap, bukan deskripsi/pseudocode.

## Konteks singkat

- Repo Laravel 11 multi-tenant (`pintera-app`). Branch kerja: `sdm-v1` (SUDAH ADA, jangan buat branch baru, jangan buat worktree).
- **Sub-project 1, 2, dan 3 SUDAH SELESAI diimplementasi dan diverifikasi** di branch ini. Item ini MEMPERLUAS kode yang sudah ada — beberapa task memodifikasi file existing (`RecordManualAttendanceAction`, `ScanQrAttendanceAction`, `AttendanceRecordAggregator`, `TandaiAlpaOtomatisSdm`, `AttendanceConfigurationController`, `konfigurasi.blade.php`, `app.js`), baca isi filenya SEBELUM mengedit untuk pastikan sesuai baseline yang dikutip plan.
- **PENTING soal penomoran**: item ini BUKAN "Sub-project 4" resmi. Sub-project 4 (Izin/Cuti berjenjang via `App\Domains\Workflow`) tetap dialokasikan terpisah dan belum dikerjakan — urutan itu JANGAN diubah/dianggap sudah termasuk di sini. Ini adalah item yang sengaja ditunda dari brainstorming Sub-project 3 (shift kompleks), dikerjakan sekarang atas permintaan eksplisit user.
- Item ini membangun **penugasan shift manual per periode** ke pegawai INDIVIDU (bukan per-kategori, bukan rotasi otomatis) — `jenis_shift` (template) + `penugasan_shift` (penugasan ke pegawai spesifik per rentang tanggal, dengan validasi anti-tumpang-tindih).
- **ATURAN KERAS: `App\Domains\Sdm\Services\AttendancePolicyResolver.php` (Sub-project 3) dan `App\Domains\Sdm\Services\KalenderKerjaSdmResolver.php` (Sub-project 2) TIDAK BOLEH diubah/disentuh SAMA SEKALI**. `ShiftAwareAttendanceResolver` (baru, Task 3) MEMBUNGKUS `AttendancePolicyResolver` lewat constructor injection. Task 9 Step 2 plan memverifikasi ini eksplisit lewat `git diff` terhadap KEDUA file — kalau ada perubahan sekecil apapun, itu kesalahan yang harus diperbaiki sebelum lanjut.
- **`PenugasanShift` model WAJIB `BelongsToTenant` DENGAN `lembaga_id` WAJIB TERISI** (beda dari kebanyakan tabel config Sdm lain yang punya baris nasional `lembaga_id` nullable) — alasannya dijelaskan detail di spec §3.2, JANGAN "dikoreksi" jadi nullable.
- **TIDAK ADA hardcode nama kategori pegawai apapun** (mis. "satpam") di kode manapun — itu cuma contoh ilustrasi di spec/plan/percakapan, BUKAN bagian dari struktur data. Task 9 Step 3 plan grep eksplisit untuk memastikan ini.
- Reuse pola Tom Select untuk pemilih pegawai (Task 7) — WAJIB modul JS baru serupa `attendance-manual-form.js`, BUKAN native `<select>` polos.
- Rotasi otomatis SENGAJA TIDAK dibangun di item ini — jangan tergoda menambahkannya walau terasa "related".

## Cara eksekusi

**Kalau kamu punya akses ke skill `superpowers` (Claude Code dengan plugin Superpowers terpasang):**
Gunakan skill `superpowers:subagent-driven-development` untuk mengeksekusi plan ini task-by-task — fresh subagent per task, task review, ledger progress di `.superpowers/sdm-sdd-3b/progress.md`.

**Kalau kamu tidak punya skill itu (agent lain / tanpa Superpowers):**
Eksekusi manual task-by-task sesuai urutan di plan (Task 1 → 9, urutannya penting — tiap task punya blok "Interfaces: Consumes/Produces" eksplisit yang menandai dependency ke task sebelumnya):
1. Baca task, kerjakan tiap step-nya persis seperti ditulis (kode di plan itu final, jangan diparafrase).
2. Untuk task yang MEMODIFIKASI file existing — WAJIB baca isi file itu dulu sebelum mengedit, pastikan potongan kode yang dikutip plan cocok dengan isi file saat ini. Kalau beda, STOP dan laporkan.
3. Jalankan verifikasi (test scoped) SEBELUM commit — kalau ada yang gagal, JANGAN commit, cari tahu kenapa dulu.
4. Satu commit per task, pakai pesan commit yang sudah ditentukan di tiap task Step terakhir.
5. Jangan jalankan full test suite sampai Task 9.
6. Task 9 Step 6 butuh persetujuan user secara EKSPLISIT sebelum menjalankan full suite (Step 7) — TANYA dulu, jangan otomatis jalan.

## Pelajaran penting dari review pekerjaan sebelumnya di repo ini (WAJIB diperhatikan)

1. **Jangan tandai step/task selesai kalau isinya belum benar-benar diverifikasi lewat command nyata.** Setiap klaim "sudah diverifikasi" harus bisa ditelusuri ke output command yang benar-benar dijalankan (test PASS dengan jumlah pasti) — bukan asumsi dari membaca kode.
2. **Kalau full suite menunjukkan kegagalan yang TIDAK terkait sama sekali dengan Penugasan Shift, jangan langsung anggap itu masalah dari pekerjaanmu.** Ada pola flaky test pre-existing di branch ini (`KomponenPenilaianCrudTest`, `RaporPdfDataBuilderTest`) — jalankan ulang test yang gagal SENDIRIAN dulu untuk konfirmasi.
3. **Kalau plan mengasumsikan isi file existing yang ternyata sudah berbeda saat kamu baca** — JANGAN menebak atau "memperbaiki sendiri" secara diam-diam. Laporkan penyimpangannya ke user.
4. **Task 4, 5, 6, 7, 8 masing-masing punya langkah eksplisit menjalankan ulang test Sub-project 1-3 yang berpotensi tersentuh** — JANGAN lewati langkah itu, itu jaring pengaman regresi-silang. Task 5 khususnya mengganti isi `AttendanceRecordAggregator.php` seluruhnya (bukan cuma nambah baris) — pastikan test lama tetap hijau SEMUA sebelum lanjut.
5. **Test anti-tumpang-tindih (`AssignShiftActionTest`) WAJIB benar-benar dijalankan dan hijau** — termasuk kasus tricky `tanggal_selesai` null (open-ended) di kedua sisi perbandingan, ini bukan test hiasan.

## Kalau menemukan sesuatu yang tidak sesuai plan

Kode yang dikutip plan ini berbasis commit `785e1a8` di branch `sdm-v1`. Kalau kamu menemukan isi file yang BEDA signifikan dari yang dikutip plan (bukan cuma beda nomor baris), atau blok kode yang dicari plan ternyata TIDAK ADA sama sekali — STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Setelah kamu selesai

Task 9 Step 8 di plan sudah meminta kamu menulis handoff log ke `.agents/logs/2026-08-22-sdm-03b-shift-bergilir.md` (ringkasan per task, commit hash, hasil verifikasi akhir dengan angka pasti). Sesi yang menulis plan ini kemungkinan akan melakukan review independen terhadap hasil kerjamu (termasuk `git diff` terhadap baseline dan menjalankan full test suite sendiri) — pastikan setiap commit bersih dan bisa ditelusuri (jangan squash / rewrite history).

## Definisi selesai

Task 9 selesai: seluruh test scoped Task 2-8 hijau bersama-sama (≥ 25 test baru), test Sub-project 1-3 yang disebut di Task 4/5/6/7/8 tetap hijau tanpa perubahan jumlah, full suite dijalankan SETELAH izin eksplisit user dan hasilnya hijau (0 failed, 0 error), grep `hasRole(` kosong, grep `"satpam"` (case-insensitive) di `app/` kosong, `git diff` terhadap `AttendancePolicyResolver.php` DAN `KalenderKerjaSdmResolver.php` sejak baseline plan KOSONG (kedua file benar-benar tidak tersentuh), handoff log tertulis di `.agents/logs/2026-08-22-sdm-03b-shift-bergilir.md` dengan bukti verifikasi yang bisa ditelusuri (bukan klaim tanpa command).
