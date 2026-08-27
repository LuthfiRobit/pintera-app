# Kickoff Prompt — Fix Filter Jenis Asesmen di 3 Consumer Lain (Audit Pasca-Priority 6)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP, hasil audit sistematis PENUH (Laravel Boost `database-schema` + 2 agent grep independen mencakup seluruh `app/` termasuk Jobs/Console/Notifications/Exports). Kamu tidak perlu audit ulang, tidak perlu menulis spec baru, tidak perlu bertanya "apakah ada consumer lain" — sudah dikonfirmasi TIDAK ADA consumer tersembunyi di luar 3 yang ditangani plan ini.

## Yang harus kamu baca dulu

1. `.agents/specs/2026-08-27-akademik-fix-filter-jenis-asesmen-consumer-lain.md` — spec lengkap. §1 WAJIB dibaca — menjelaskan efek nyata bug ini: narasi rapor CETAK RESMI bisa salah, progress kesiapan rapor guru menyesatkan, widget nilai siswa/orang tua tercampur jenis non-rapor.
2. `.agents/plans/2026-08-27-akademik-fix-filter-jenis-asesmen-consumer-lain.md` — plan implementasi (4 task, kode lengkap, TDD step-by-step, semua test mengikuti pola existing di masing-masing file target — bukan file/gaya baru).

## Konteks penting — kenapa fix ini ada

Priority #6 (sudah SELESAI sebelumnya) membuka `JenisAsesmen::DiagnostikKognitif`/`DiagnostikNonKognitif`/`Formatif` ke form guru, dengan `RaporCalculationService` diberi filter `JenisAsesmen::masukRapor()` supaya rapor tidak tercemar. **Tapi saat itu tidak diaudit consumer LAIN** yang juga membaca `NilaiSiswa`/`Asesmen` scr independen. User lalu meminta audit sistematis penuh (pakai Laravel Boost) — ditemukan persis 3 titik bocor, dikonfirmasi itu SATU-SATUNYA yang tersisa (Jobs/Console/Notifications/Exports semuanya nihil referensi Asesmen/NilaiSiswa).

## Proyek ini pakai Laravel Boost — WAJIB dipakai selama eksekusi

- Kalau kamu ragu soal struktur tabel (`nilai_siswa`, `asesmen`, `komponen_penilaian`) — pakai `database-schema`, bukan buka migration manual. Schema sudah diverifikasi lengkap saat audit (kolom `kelas.kurikulum` juga sudah ada, `lembaga.naungan` enum `kemendikdasmen`/`kemenag` sudah ada — tidak relevan ke plan ini, disebut cuma sbg konteks skema).
- **JANGAN buat script verifikasi terpisah/tinker** — test yang ditulis plan sudah cukup membuktikan fungsionalitasnya.
- Kalau ragu soal `whereHas`/`whereIn` version-sensitive Eloquent — pakai `search-docs`.

## Project Rules gate — WAJIB dibaca sebelum edit apa pun

Buka `.ai/rules/index.md`, lalu baca rule file yang glob-nya mencakup path yang disentuh plan ini:

- `.ai/rules/app-services.md`, `.ai/rules/services.md` (Task 1, 2: `CapaianKompetensiGenerator`, `DashboardStatsService`)
- `.ai/rules/controllers.md` (Task 3: `DashboardController`)
- `.ai/rules/domains-enums.md`, `.ai/rules/enums.md` (Task 4: `JenisAsesmen`)
- `.ai/rules/tests.md`

Kalau ada isi rule yang tampak bertentangan dgn instruksi plan, STOP dan laporkan konfliknya ke user — jangan diam-diam pilih salah satu.

## Konteks singkat — PENTING, baca sebelum eksekusi

- Repo Laravel 12.63.0 multi-tenant (`pintera-app`), Pest v4, MySQL 8.0.30. Branch kerja: `akademik-v2` (SUDAH ADA — jangan buat branch baru/worktree).
- **Ini pure bug-fix, TIDAK ADA fitur baru** — 4 task semuanya "tambah 1 filter" + 1 update docblock. Jangan perluas scope ke area lain (Kenaikan Kelas, Jadwal Pelajaran, RPP, dll) — itu keputusan eksplisit user utk dibahas TERPISAH setelah plan ini selesai, BUKAN bagian plan ini.
- **Task 3 branch orang tua WAJIB pakai `withoutGlobalScope(TenantScope::class)` di dalam `whereHas('asesmen', ...)`** — bukan cuma di query utama. Kalau lupa ini, query bisa diam-diam gagal menemukan hasil krn tenant scope memblokir akses ke `asesmen` milik siswa yang bukan lembaga user yang login (padahal orang tua sah lintas-lembaga kalau anaknya pindah). Plan sudah menulis kode lengkapnya — SALIN PERSIS, jangan modifikasi pola-nya.
- **Urutan `whereHas` HARUS sebelum `->latest('id')->limit(5)`** di Task 3 — kalau dibalik (filter setelah get()), hasilnya salah (bisa dapat <5 item padahal ada lebih banyak nilai Sumatif yang tersedia).
- **2 test existing yang TIDAK BOLEH berubah hasilnya**: `tests/Feature/DashboardTest.php` — `'shows a siswa their latest recorded grade...'` (baris 153) dan `'shows an orang tua the latest recorded grade...'` (baris 181) — keduanya pakai `Asesmen::factory()` TANPA `jenis` eksplisit (default `SumatifLingkupMateri`, sudah diverifikasi), jadi tetap PASS setelah fix. Kalau salah satu jadi FAIL setelah Task 3, itu tanda ada kesalahan di penempatan `whereHas`/`withoutGlobalScope` — JANGAN diam-diam ubah assertion test lama utk "menyesuaikan", perbaiki kode Task 3-nya.

## Urutan eksekusi

**Task 1 → 2 → 3 → 4 murni LINEAR** (independen satu sama lain secara teknis, tapi urutan ini memudahkan review bertahap).

**Kalau kamu punya akses ke skill `superpowers`:**
Plan ini sedang (4 task, ~6 file tersentuh, semuanya pola sama "tambah filter") — boleh eksekusi manual langsung (`superpowers:executing-plans`), atau `subagent-driven-development` kalau mau ekstra hati-hati krn menyentuh 2 halaman dashboard produksi (siswa & orang tua) sekaligus dokumen rapor resmi.

**Kalau tidak punya skill itu sama sekali:**
Eksekusi manual:
1. Baca task, kerjakan tiap step persis seperti ditulis — kode di plan sudah lengkap.
2. **WAJIB baca file existing SEBELUM edit** (`CapaianKompetensiGenerator.php`, `DashboardStatsService.php` baris 128-154, `DashboardController.php` baris 134-139 & 204-213, `JenisAsesmen.php`) dan bandingkan dgn kutipan di plan — kalau baseline beda, STOP dan laporkan ke user.
3. Jalankan `php -l <file>` setelah tiap edit PHP, SEBELUM commit.
4. Jalankan test scoped SEBELUM commit — kalau gagal, JANGAN commit, diagnosis dulu.
5. 4 commit total (satu per task, pesan commit sudah ditulis di tiap Step terakhir).
6. **JANGAN jalankan full test suite sampai Task 4 Step 2.**

## Peringatan eksplisit dari plan — titik yang HARUS diverifikasi, bukan diasumsikan

1. **Task 1 Step 1** — test WAJIB assert dulu bahwa data Formatif benar-benar tersimpan (`Asesmen::where(...)->exists()`, `NilaiSiswa::where(...)->first()->nilai_angka`) SEBELUM assert narasi — jangan hapus assert "existence" itu.
2. **Task 2 Step 1** — test membandingkan hasil SEBELUM dan SESUDAH menambah Asesmen Sumatif (dua pemanggilan `statistikProgressRaporKelas()` dalam satu test) — pola ini sengaja utk membuktikan transisi 0%→100%, bukan cuma satu state akhir.
3. **Task 3 Step 1** — kedua test baru sengaja membuat Asesmen Formatif SETELAH Asesmen Sumatif (id lebih besar) — supaya test benar-benar membuktikan filter jenis bekerja, bukan kebetulan urutan waktu yang menguntungkan.
4. **Task 4 Step 3** — kalimat baru ditambahkan ke AKHIR paragraf existing Prioritas #6 di `PETA_PENGEMBANGAN.md`, BUKAN paragraf baru terpisah — baca dulu paragraf itu penuh sebelum edit.

## Pelajaran penting dari sprint-sprint akademik sebelumnya (berlaku juga di sini)

1. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
2. **JANGAN jalankan lebih dari satu proses `php artisan test` bersamaan.**
3. **Klaim hasil test di handoff log HARUS bisa ditelusuri (command + angka pasti)** — jalankan `php artisan test --compact` TANPA filter apa pun di Task 4 Step 2 utk klaim "full suite hijau".
4. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan — STOP dan laporkan ke user, JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.**
5. **Jangan menambah scope di luar 4 task ini** — termasuk godaan memperluas audit ke Kenaikan Kelas/Jadwal Pelajaran/RPP yang disebut di spec sbg "belum diaudit" — itu keputusan terpisah milik user, BUKAN bagian plan ini.

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Cek dulu via Boost (`database-schema`/`database-query`) kalau itu pertanyaan teknis yang bisa dijawab tool, baru laporkan ke user kalau tetap ambigu.

## Definisi selesai

- Task 1: `CapaianKompetensiGenerator::generateNarasi()` mengecualikan Diagnostik/Formatif, 1 test PASS.
- Task 2: `DashboardStatsService::statistikProgressRaporKelas()` mengecualikan Diagnostik/Formatif, semua test di file (existing + baru) PASS.
- Task 3: widget "Nilai Terbaru" siswa & orang tua mengecualikan Diagnostik/Formatif, semua test di `DashboardTest.php` (existing + 2 baru) PASS — termasuk 2 test lama yang TIDAK BOLEH berubah hasilnya.
- Task 4: docblock `masukRapor()` diperbarui, **full test suite (`php artisan test --compact` tanpa filter) 0 failed**, angka pasti dicatat, `PETA_PENGEMBANGAN.md` sudah dicatat tindak lanjutnya, laporan final ke user berisi angka pasti + 4 commit hash.
