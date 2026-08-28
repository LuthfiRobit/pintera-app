# Kickoff: Fix Rapor Semester Mismatch & Kenaikan Kelas Mundur

**Untuk**: Antigravity (execution agent)
**Branch**: `akademik-v2` (lanjutkan di branch ini, JANGAN buat branch baru)
**Spec**: `.agents/specs/2026-08-28-akademik-fix-rapor-semester-mismatch-dan-kenaikan-kelas-mundur.md`
**Plan**: `.agents/plans/2026-08-28-akademik-fix-rapor-semester-mismatch-dan-kenaikan-kelas-mundur.md`

---

## Peringatan Kritis — Baca Dulu Sebelum Mulai

1. **Baca `.agents/plans/2026-08-28-akademik-fix-rapor-semester-mismatch-dan-kenaikan-kelas-mundur.md` SELURUHNYA sebelum menulis kode apa pun.** Plan ini punya 2 task independen, masing-masing dengan kode lengkap di setiap step — jangan improvisasi di luar apa yang tertulis.
2. **Baca dulu `.ai/rules/index.md`**, lalu buka rule file yang cocok dengan glob untuk `app/Http/Controllers/**`, `app/Domains/*/Actions/**`, dan `tests/**`.
3. **Task 2 punya jebakan teknis yang WAJIB dipahami sebelum coding**: `tahun_ajaran.tanggal_mulai` adalah kolom `date`, dan `TahunAjaranFactory` selalu default ke `now()` untuk SEMUA baris tanpa variasi. Ini berarti 2 test existing di `ProsesKenaikanKelasActionTest.php` (`promotes siswa...`, `skips a jadwal row...`) membuat 2 `TahunAjaran` dengan `tanggal_mulai` yang IDENTIK. Plan Task 2 Step 4 EKSPLISIT mewajibkan operator `<` (strict), BUKAN `<=` — kalau pakai `<=`, 2 test existing itu akan pecah. Baca catatan "PENTING" di Task 2 Step 1 sebelum menulis kode.
4. **Verifikasi baseline sebelum edit** — Step 1 di masing-masing task meminta kamu membaca ulang file sumber dan membandingkan dengan baseline yang tertulis di plan. Kalau berbeda, STOP dan laporkan.

## Konteks Masalah (ringkas)

**Task 1**: `Admin\RaporController::cetak()` sudah benar — cross-check `semester->tahun_ajaran_id === kelas->tahun_ajaran_id` sebelum generate rapor. Tapi `Guru\RaporController` (4 method: `edit`, `generateNarasi`, `ajukan`, `cetak`) tidak punya guard yang sama — guru wali kelas bisa mengirim `semester_id` yang valid (milik lembaganya sendiri, jadi BUKAN celah lintas-tenant) tapi milik tahun ajaran yang salah, mencemari data rapor dengan kombinasi kelas×semester yang tidak konsisten.

**Task 2**: `ProsesKenaikanKelasAction` cuma menolak kalau tahun ajaran tujuan SAMA PERSIS dengan tahun ajaran asal (guard existing, jangan diubah), tapi tidak mengecek apakah tahun ajaran tujuan benar-benar LEBIH BARU. Admin bisa "menaikkan" siswa ke kelas yang secara kronologis ada di tahun ajaran yang lebih lama (mundur). Bukan celah lintas-tenant (guard lembaga sudah benar dan tidak diubah), murni integritas data.

Kedua temuan ini BUKAN bug lintas-lembaga (IDOR) seperti 4 bug sebelumnya di sesi audit ini — keduanya murni soal konsistensi periode (semester/tahun ajaran) dalam lembaga yang sama.

## Urutan Eksekusi

2 task independen (file berbeda, tidak saling bergantung). Kerjakan berurutan sesuai plan (Task 1 dulu, lalu Task 2) untuk kejelasan commit history. Masing-masing task: baca baseline → tulis test gagal → jalankan & pastikan gagal → implementasi fix → jalankan & pastikan semua lolos → (Task 2 juga: jalankan regresi di 2 file test terkait lain) → pint (Task 2) → commit.

## Titik Verifikasi Wajib

**Task 1:**
- Setelah Step 3 (sebelum fix): 4 test reproduksi HARUS **gagal** (response 200 alih-alih 404). Kalau sudah PASS di titik ini, STOP — ada kesalahan setup test.
- Setelah Step 5 (setelah fix): `php artisan test tests/Feature/Guru/RaporControllerTest.php --compact` HARUS hijau semua (baseline + 4 test baru).

**Task 2:**
- Setelah Step 3 (sebelum fix): test `throws a DomainException when kelas tujuan is in a tahun ajaran with an earlier tanggal_mulai...` HARUS **gagal** (tidak ada exception dilempar). Kalau sudah PASS, STOP.
- Setelah Step 5 (setelah fix): `php artisan test tests/Unit/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasActionTest.php --compact` HARUS hijau semua (3 test baseline existing + 2 test baru) — TERMASUK 2 test existing yang memakai `tanggal_mulai` sama (`promotes siswa...`, `skips a jadwal row...`) HARUS tetap PASS, ini bukti bahwa `<` (bukan `<=`) sudah dipakai dengan benar.
- Step 6: `php artisan test tests/Feature/Admin/KenaikanKelasControllerTest.php tests/Feature/Akademik/KenaikanKelasControllerUxTest.php --compact` juga HARUS hijau — regresi tidak langsung lewat controller.

**TIDAK PERLU full suite** untuk fix sekecil ini (sudah dikonfirmasi user) — cukup test scoped per task seperti di atas.

Jalankan `vendor/bin/pint --dirty --format agent` sebelum commit Task 2 (Step 7).

## Pelajaran Penting dari Sprint-Sprint Akademik Sebelumnya

- **Selalu baca definisi kolom/factory sebelum menulis validasi berbasis perbandingan angka/tanggal** — Task 2 ini contoh nyata: kalau langsung ikut spec awal yang bilang "pakai `<=`" tanpa cek fixture existing, akan menghasilkan fix yang justru merusak 2 test yang sudah ada. Plan sudah dikoreksi ke `<`, tapi pelajarannya: selalu cross-check terhadap fixture test existing sebelum finalisasi logika perbandingan.
- **Bug lintas-periode (semester/tahun ajaran) berbeda dari bug lintas-tenant (lembaga)** — 4 bug sebelumnya di sesi ini semua tentang kebocoran/inkonsistensi ANTAR LEMBAGA. 2 temuan kali ini murni tentang konsistensi ANTAR PERIODE WAKTU dalam lembaga yang sama. Jangan bingung menerapkan pola `withoutGlobalScope(TenantScope::class)` di sini — TIDAK relevan untuk kedua fix ini karena `Semester`/`TahunAjaran` yang dipakai sudah otomatis dalam lembaga yang benar (TenantScope sudah menyaring itu); masalahnya murni "field A dan field B sama-sama valid milik lembaga X, tapi tidak konsisten satu sama lain di dimensi WAKTU".

## Kalau Menemukan Sesuatu Tidak Sesuai Plan

- Kalau baseline kode di repo berbeda dari yang tertulis di plan Step 1 (masing-masing task), STOP dan laporkan detail perbedaannya di handoff log.
- Kalau ada test lain (di luar yang disebut di plan) yang tiba-tiba FAIL setelah fix diterapkan, JANGAN diam-diam mengubah assertion test itu. Laporkan sebagai temuan di handoff log.

## Definisi Selesai

- [ ] `Guru\RaporController.php` mendapat 4 `abort_if` baru sesuai plan Task 1 Step 4.
- [ ] 4 test baru ditambahkan di `RaporControllerTest.php` dan PASS, seluruh test existing di file itu tetap PASS.
- [ ] `ProsesKenaikanKelasAction.php` mendapat 1 blok pengecekan `tanggal_mulai` (pakai `<`) + import `TahunAjaran`, sesuai plan Task 2 Step 4.
- [ ] 2 test baru ditambahkan di `ProsesKenaikanKelasActionTest.php` dan PASS, seluruh test existing di file itu (termasuk 2 yang pakai `tanggal_mulai` sama) tetap PASS.
- [ ] `php artisan test tests/Feature/Admin/KenaikanKelasControllerTest.php tests/Feature/Akademik/KenaikanKelasControllerUxTest.php --compact` hijau (regresi tidak langsung).
- [ ] `vendor/bin/pint --dirty --format agent` sudah dijalankan.
- [ ] 2 commit dibuat sesuai pesan di plan Task 1 Step 6 dan Task 2 Step 8.
- [ ] Handoff log ditulis ke `.agents/logs/2026-08-28-akademik-fix-rapor-semester-mismatch-dan-kenaikan-kelas-mundur.md` mengikuti format handoff log sebelumnya (lihat `.agents/logs/2026-08-28-akademik-fix-ruangan-id-cross-lembaga-jadwal-pelajaran.md` sebagai contoh format): ringkasan apa yang dikerjakan (kedua task), keputusan penting yang diambil (termasuk kenapa `<` bukan `<=`), hal yang perlu direview manusia.
