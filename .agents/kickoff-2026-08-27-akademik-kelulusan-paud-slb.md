# Kickoff Prompt — Kelulusan/Rapor Akhir PAUD + Keputusan SLB (Priority 3)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan sudah direview mendalam (brainstorming interaktif + review user yang menghasilkan 3 penyempurnaan test + spec self-review). Kamu tidak perlu brainstorming ulang, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu

1. `.agents/specs/2026-08-27-akademik-kelulusan-paud-slb.md` — spec lengkap. §2 WAJIB dibaca — 3 keputusan bisnis kunci: HANYA TK tingkat B yang dianggap tingkat akhir PAUD (KB/TPA/SPS TIDAK PERNAH), SLB tetap pakai template SD sbg keputusan FINAL (bukan fallback), wording label kelulusan PAUD SAMA PERSIS dgn jenjang lain (tidak ada wording khusus TK).
2. `.agents/plans/2026-08-27-akademik-kelulusan-paud-slb.md` — plan implementasi (3 task kecil, kode lengkap, TDD step-by-step, sudah lolos self-review termasuk 1 koreksi konvensi yang saya lakukan sendiri: test method private lewat Reflection diganti test via `RaporPdfDataBuilder::build()` publik, mengikuti pola test existing di codebase).

## Proyek ini pakai Laravel Boost — WAJIB dipakai selama eksekusi

Project ini punya Laravel Boost MCP server terpasang. Ikuti aturan project (`CLAUDE.md` root):

- **Untuk verifikasi struktur data** (`catatan_wali_kelas.keterangan_kenaikan`, `kelas.tingkat`) — pakai `database-schema`, bukan buka migration manual.
- **Untuk debugging** — pakai `database-query` (read-only) atau `last-error`/`read-log-entries`, BUKAN `php artisan tinker` manual, dan BUKAN membuat script verifikasi terpisah (test yang mencakup fungsionalitas lebih penting daripada script ad-hoc).
- Plan ini kecil dan sebagian besar transkripsi kode lengkap — `search-docs` HANYA perlu dipanggil kalau kamu ragu soal sintaks Blade/Pest version-sensitive, bukan di setiap step.

## Project Rules gate — WAJIB dibaca sebelum edit apa pun

Buka `.ai/rules/index.md`, lalu baca rule file yang glob-nya mencakup path yang disentuh plan ini:

- `.ai/rules/services.md`, `.ai/rules/app-services.md` (Task 1: modifikasi `RaporPdfDataBuilder`)
- `.ai/rules/views.md` (Task 2: modifikasi `paud.blade.php`)
- `.ai/rules/tests.md` (semua task — PERHATIKAN aturan "RefreshDatabase: implisit di Feature" yang jadi alasan Task 1 test disimpan di `tests/Feature/`, bukan `tests/Unit/`, meski isinya tampak seperti unit test)

Kalau ada isi rule yang tampak bertentangan dgn instruksi plan, STOP dan laporkan konfliknya ke user — jangan diam-diam pilih salah satu.

## Konteks singkat — PENTING, baca sebelum eksekusi

- Repo Laravel 12.63.0 multi-tenant (`pintera-app`), Pest v4, MySQL 8.0.30. Branch kerja: `akademik-v2` (SUDAH ADA — jangan buat branch baru/worktree).
- **Ini plan KECIL yang disengaja** (1 baris logic + 1 section Blade + 2 komentar) — JANGAN memperluas scope. Godaan yang HARUS dihindari: membuat template SLB terpisah, menambah wording khusus TK, mengubah `isTingkatAkhir()` utk KB/TPA/SPS, menyentuh SD/SMP-SMA/SMK template. Semua itu eksplisit Non-Goal di spec §6.
- **Method `isTingkatAkhir()` bersifat private** — JANGAN test lewat `ReflectionMethod`. Plan Task 1 sudah menyediakan pola test yang benar (lewat `RaporPdfDataBuilder::build()` publik, baca `data['isTingkatAkhir']` hasilnya) — ikuti persis, jangan menyingkat jadi reflection dgn alasan "lebih simpel".
- **`RaporPdfDataBuilder::build()` TIDAK PERLU diubah di Task 2** — `$isGenap`/`$labelKenaikan`/`$catatan` sudah dihitung universal utk semua jenjang SEBELUM plan ini (sudah diverifikasi dari kode asli di spec §4, dikutip persis). Kalau kamu baca kode dan menemukan itu TIDAK BENAR (mis. `$labelKenaikan` ternyata bercabang per-jenjang), STOP — itu berarti baseline sudah berubah sejak spec ditulis, laporkan ke user, jangan asal lanjut mengasumsikan spec tetap benar.
- **Task 2 test WAJIB integrasi penuh** `RaporPdfDataBuilder::build()` → `view(...)->render()` — BUKAN menyuntik `$labelKenaikan`/`$isGenap` manual ke array data view. Ini keputusan eksplisit dari review user: kalau test cuma inject variabel manual, fix `isTingkatAkhir()` di Task 1 tidak benar-benar ter-cover end-to-end.

## Urutan eksekusi

**Task 1 → 2 → 3 murni LINEAR** (Task 2 butuh fix Task 1 supaya test TK-B-nya valid; Task 3 murni dokumentasi, tidak bergantung teknis ke Task 1/2 tapi logically menutup topik yang sama).

**Kalau kamu punya akses ke skill `superpowers`:**
Plan ini sangat kecil (3 task, ~6 file tersentuh, tidak ada dependency rumit) — boleh eksekusi manual langsung (`superpowers:executing-plans`) atau `subagent-driven-development` kalau kamu mau ekstra hati-hati. Tidak wajib subagent-driven utk plan sekecil ini.

**Kalau tidak punya skill itu sama sekali:**
Eksekusi manual:
1. Baca task, kerjakan tiap step persis seperti ditulis — kode di plan sudah lengkap.
2. **WAJIB baca file existing SEBELUM edit** (`RaporPdfDataBuilder.php` baris 145-156, `paud.blade.php` 73 baris penuh, `AcademicProfile.php` baris 30-34, `RaporPdfDataBuilderTest.php` baris 18-19) dan bandingkan dgn kutipan di plan — kalau baseline beda, STOP dan laporkan ke user.
3. Jalankan `php -l <file>` setelah tiap edit PHP, SEBELUM commit.
4. Jalankan test scoped SEBELUM commit — kalau gagal, JANGAN commit, diagnosis dulu.
5. 3 commit total (satu per task, pesan commit sudah ditulis di tiap Step terakhir).
6. **JANGAN jalankan full test suite sampai Task 3 Step 5.**
7. Jalankan `vendor/bin/pint --dirty --format agent` di akhir Task 2 (sudah tertulis eksplisit di step-nya).

## Peringatan eksplisit dari plan — titik yang HARUS diverifikasi, bukan diasumsikan

1. **Task 1 Step 3** — HANYA tambah `'TK' => 'B'` ke map. `KB`/`TPA`/`SPS` TIDAK BOLEH ditambahkan ke map itu dalam kondisi apa pun.
2. **Task 2 Step 3** — section baru ditambahkan TEPAT SEBELUM `@include('pdf.rapor._tanda-tangan')`, bukan di posisi lain. Baca dulu 73 baris file itu penuh sebelum edit utk konfirmasi baris ini masih akurat.
3. **Task 2 Step 1** — dataset test `->with(['KB', 'TPA', 'SPS'])` WAJIB ada (test boundary eksplisit) — jangan dihapus dgn alasan "sudah tercakup unit test Task 1", krn ini membuktikan business rule di level INTEGRASI, bukan cuma di level fungsi internal.
4. **Task 3 Step 4** — grep akhir `"Sprint 5\|regression compatibility"` HARUS 0 hasil. Kalau ada sisa di file lain yang tidak disebut plan, STOP dan laporkan ke user (jangan diam-diam bersihkan sendiri tanpa laporan, meski kelihatannya trivial).

## Pelajaran penting dari sprint-sprint akademik sebelumnya (berlaku juga di sini)

1. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
2. **JANGAN jalankan lebih dari satu proses `php artisan test` bersamaan.**
3. **Klaim hasil test di handoff log HARUS bisa ditelusuri (command + angka pasti)** — jalankan `php artisan test --compact` TANPA filter apa pun di Task 3 Step 5 utk klaim "full suite hijau".
4. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan — STOP dan laporkan ke user, JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.**
5. **Jangan menambah scope di luar 3 task ini.**

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Definisi selesai

- Task 1: `isTingkatAkhir()` mengenali TK-B sbg tingkat akhir, 10 test regresi PASS (TK-B=true, TK-A/KB-B/TPA-B/SPS-B=false, SD-6/SLB-6/SMP-9/SMA-12/SMK-12=true tidak berubah).
- Task 2: section "Keterangan Kelulusan" tampil di `paud.blade.php` utk TK-B semester genap dgn isi `keterangan_kenaikan` yang benar, tidak tampil sama sekali di semester ganjil, tidak pernah muncul utk KB/TPA/SPS — 6 test (termasuk dataset 3x) PASS via integrasi penuh builder→view.
- Task 3: komentar `AcademicProfile.php` dan `RaporPdfDataBuilderTest.php` sudah diperbarui (SLB = keputusan final, bukan fallback), grep akhir nol sisa komentar basi, **full test suite (`php artisan test --compact` tanpa filter) 0 failed**, angka pasti dicatat, `PETA_PENGEMBANGAN.md` sudah ditandai Prioritas #3 SELESAI, laporan final ke user berisi angka pasti + 3 commit hash.
