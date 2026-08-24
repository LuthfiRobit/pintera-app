# Kickoff Prompt — Perbaikan 33 Test + 3 Seeder Cacat Pasca Susut Seeder 1-Lembaga (SD)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan siap jalan. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu

1. `.agents/specs/2026-08-24-perbaikan-test-pasca-susut-seeder.md` — spec sub-project ini (§2 tabel nilai dunia baru, §3 keputusan desain).
2. `.agents/plans/2026-08-24-perbaikan-test-pasca-susut-seeder.md` — plan implementasi (21 task, kode lengkap per task).
3. (Konteks tambahan kalau perlu) `.agents/plans/2026-08-24-seeder-susutkan-1-lembaga-sd.md` — plan sub-project SEBELUMNYA yang menyusutkan seeder demo, penyebab kenapa 33 test ini gagal.

## Konteks singkat

- Repo Laravel 12 multi-tenant (`pintera-app`). Branch kerja: `rbac-v2` (SUDAH ADA — jangan buat branch baru, jangan buat worktree).
- **Baseline kode plan ini: commit `3270019`.** Kalau isi file yang kamu baca BEDA signifikan dari yang dikutip plan, STOP, laporkan ke user.
- **Kenapa sub-project ini ada**: seeder demo baru saja disusutkan dari 4 lembaga (KB/TK/SD/SMP) jadi 1 lembaga (SD). Seeder aplikasinya sendiri sudah terverifikasi BERSIH (3 review independen + `migrate:fresh --seed` sukses), TAPI full test suite menghasilkan 45 test gagal (33 file) karena test-nya masih hardcode ekspektasi dunia lama. PLUS ditemukan 3 seeder aplikasi (`GuruJabatanTambahanSeeder`, `RiwayatPendidikanGuruSeeder`, `SertifikasiGuruSeeder`) yang TIDAK ikut disusutkan sebelumnya — masih hardcode email guru SMP yang sudah dihapus (silent-skip, bukan crash).
- **Data seeder demo buang-pakai** — `migrate:fresh --seed` adalah verifikasi normal, jalankan sesering perlu.

## Urutan eksekusi — Task 1 WAJIB pertama

Task 1 (perbaiki 3 seeder aplikasi) adalah prasyarat Task 9, 15, 16 (test yang bergantung pada guru pengganti hasil Task 1). Task 2-4 (batch mekanis) independen, boleh urutan bebas relatif satu sama lain tapi dikerjakan SETELAH Task 1. Task 5-20 (per-file judgement) juga independen satu sama lain KECUALI Task 9/15/16 yang butuh Task 1.

**Kalau kamu punya akses ke skill `superpowers`:**
Gunakan `superpowers:subagent-driven-development` — fresh subagent per task, task review, ledger progress di `.superpowers/perbaikan-test-susut-seeder/progress.md`.

**Kalau tidak punya skill itu:**
Eksekusi manual task-by-task (Task 1 → 21):
1. Baca task, kerjakan tiap step persis seperti ditulis (kode di plan itu final).
2. WAJIB baca isi file existing dulu sebelum menimpa/mengedit — pastikan cocok dengan yang dikutip plan.
3. Jalankan `php -l <file>` (syntax check) setelah tiap edit, SEBELUM commit.
4. Jalankan test scoped SEBELUM commit — kalau gagal, JANGAN commit, diagnosis dulu.
5. Satu commit per task.
6. Jangan jalankan full test suite sampai Task 21.
7. Task 21 Step 4 butuh persetujuan user EKSPLISIT sebelum full suite (Step 5).

## Peringatan eksplisit dari plan — beberapa task punya ketidakpastian yang HARUS diverifikasi, bukan diasumsikan

1. **Task 8, 13, 14 (anchor siswa/presensi berbasis urutan)** — plan menebak siswa NIS `3333001` ("Muhammad Santoso") ada di "Kelas 1-A" dan/atau dapat status "sakit" di `PresensiSeeder`. Ini BERGANTUNG pada urutan query (`Kelas::all()`, `Siswa::where(...)->get()`) yang TIDAK dijamin selalu sama dengan asumsi plan. **Setiap task ini punya instruksi eksplisit "kalau tidak match, STOP dan verifikasi lewat tinker dulu"** — JANGAN paksakan nilai dari plan kalau ternyata tidak cocok, cari nilai aktual yang benar dengan query.
2. **Task 17-19 (isolasi lintas-lembaga via `Lembaga::factory()`)** — pola ini BELUM pernah dicoba sebelumnya di codebase ini (bukan pola established). Kalau lembaga kedua hasil factory tidak dapat data lengkap karena seeder terkait butuh field/precondition yang tidak otomatis ter-generate factory (`status_aktif`, dsb), STOP dan laporkan detail error ke user — JANGAN menambah workaround sendiri tanpa melapor, ini area yang paling mungkin butuh penyesuaian dari yang ditulis di plan.
3. **Task 2 Step 5-6, Task 3 Step 1-2, Step 5** — beberapa file (`EssentialUserSeederTest`, `JamPelajaranSeederTest`, `LembagaDataPeriodikSeederTest`, `LembagaProfileSeedersTest`, `PpdbConfigurationSeedersTest`) instruksinya "baca file dulu, cari pola X" TANPA kutipan kode lengkap (karena riset awal tidak sempat baca semua 33 file secara utuh) — kamu WAJIB baca file itu sendiri secara penuh dan terapkan perubahan sesuai deskripsi pola di plan, BUKAN menunggu kode yang tidak ada.

## Pelajaran penting dari sub-project sebelumnya di repo ini

1. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
2. **Kalau kamu menemukan file yang isinya BEDA dari yang dikutip plan (baseline sudah berubah) — STOP dan laporkan ke user**, jangan menebak-nebak menyesuaikan sendiri.
3. **JANGAN jalankan lebih dari satu proses `php artisan test`/`migrate:fresh` bersamaan.**
4. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan (misal karena Task 17-19 ternyata tidak jalan seperti dirancang) — STOP dan laporkan ke user, JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.** Ini pola kesalahan yang berulang di sub-project sebelumnya, selalu ketahuan lewat review manual.

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Definisi selesai

Task 1 selesai: 3 seeder aplikasi tidak lagi silent-skip (`GuruJabatanTambahan`=4, `RiwayatPendidikanGuru`=7, `SertifikasiGuru`=3 — tapi sekarang mengarah ke guru SD yang benar-benar ada). Task 2-20 selesai: `php artisan test tests/Unit tests/Feature/GelombangJalurRestrictionTest.php` — 0 failed. Task 21 selesai: grep gabungan identitas lama KOSONG total, full suite dijalankan SETELAH izin eksplisit user dan hasilnya hijau, handoff log tertulis dengan bukti verifikasi yang bisa ditelusuri.
