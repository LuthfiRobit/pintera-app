# Kickoff Prompt — RaporCalculationService Type-Aware + Fix Key-Mismatch (Priority 2)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan sudah direview mendalam (brainstorming interaktif + review user yang menghasilkan 6 koreksi + spec self-review). Kamu tidak perlu brainstorming ulang, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu

1. `.agents/specs/2026-08-27-akademik-rapor-calculation-type-aware.md` — spec lengkap. §1 WAJIB dibaca — menjelaskan bahwa bug ini LEBIH LUAS dari yang tercatat di roadmap awal (bukan cuma "PAUD kosong", tapi kolom per-mapel selalu kosong utk SEMUA jenjang krn key-mismatch, ditemukan saat brainstorming).
2. `.agents/plans/2026-08-27-akademik-rapor-calculation-type-aware.md` — plan implementasi (4 task, kode lengkap, TDD step-by-step, sudah lolos self-review spec-coverage + placeholder-scan + type-consistency, termasuk 3 perbaikan yang saya lakukan sendiri di penulisan plan: instruksi Task 3 Step 2 diverifikasi langsung ke file asli — bukan tebakan, bug `assertSeeText`→`assertSee` diperbaiki, parameter test helper yang tak terpakai dihapus).

## Proyek ini pakai Laravel Boost — WAJIB dipakai selama eksekusi

Project ini punya Laravel Boost MCP server terpasang. Ikuti aturan project (`CLAUDE.md` root):

- **Sebelum mengandalkan API Laravel/package version-sensitive** (mis. sintaks enum backed comparison, `Collection::keyBy()`/`flatMap()` edge-case behavior) — panggil `search-docs` dulu, jangan asumsikan dari versi Laravel lain.
- **Untuk verifikasi struktur tabel** (`nilai_siswa`, `komponen_penilaian`, `asesmen_komponen_penilaian` pivot) — pakai `database-schema`, bukan buka migration manual satu-satu.
- **Untuk debugging data selama development** — pakai `database-query` (read-only), BUKAN `php artisan tinker` manual, dan BUKAN membuat script verifikasi terpisah (aturan project: test yang mencakup fungsionalitas lebih penting daripada script verifikasi ad-hoc).
- **Kalau test gagal dan errornya tidak jelas** — pakai `last-error`/`read-log-entries` sebelum menebak-nebak.
- **`search-docs` TIDAK PERLU** dipanggil di setiap step — sebagian besar plan ini transkripsi kode lengkap yang sudah ditulis, hanya panggil di titik version-sensitive di atas.

## Project Rules gate — WAJIB dibaca sebelum edit apa pun

Buka `.ai/rules/index.md`, lalu baca rule file yang glob-nya mencakup path yang disentuh plan ini:

- `.ai/rules/domains-models.md`, `.ai/rules/models.md`, `.ai/rules/services.md`, `.ai/rules/app-services.md` (Task 1-2: DTO baru, tulis ulang Service)
- `.ai/rules/views.md` (Task 3: 2 view Blade dimodifikasi)
- `.ai/rules/tests.md` (semua task: konvensi Pest project ini)

Kalau ada isi rule yang tampak bertentangan dgn instruksi plan, STOP dan laporkan konfliknya ke user — jangan diam-diam pilih salah satu.

## Konteks singkat — PENTING, baca sebelum eksekusi

- Repo Laravel 12.63.0 multi-tenant (`pintera-app`), Pest v4, MySQL 8.0.30. Branch kerja: `akademik-v2` (SUDAH ADA — jangan buat branch baru/worktree, kecuali diminta eksplisit).
- **Ini gabungan bug-fix + fitur baru dalam satu plan** (keputusan user eksplisit, lihat spec §1) — jangan bingung menganggap salah satu bagian "di luar scope", keduanya memang disatukan dgn sengaja krn berada di file/area yang sama.
- **`RekapNilaiSel` DTO HANYA 3 field** (`assessmentType`, `label`, `tuntas`) — TIDAK ADA field `value`. Kalau kamu tergoda menambah field `value: float` "supaya lebih lengkap", JANGAN — itu pelanggaran eksplisit hasil review user (spec §4.1, Global Constraints plan). `classAvg`/`highestScore` HARUS dihitung dari array bantu numeric mentah terpisah di dalam service, bukan dari DTO.
- **`mapelList` di-`keyBy()` sebagai langkah TERAKHIR** — jangan ada `->values()` susulan di titik mana pun, itu akan membuang fix bug key-mismatch yang jadi inti Task 2 Step 5.
- **Precedence numeric > predicate > narrative WAJIB dibuktikan KEDUA ARAH** (numeric mengalahkan predicate, DAN predicate mengalahkan narrative) — jangan cuma test satu arah dan anggap selesai, plan Task 2 Step 3 sudah menyediakan test utk keduanya secara eksplisit.
- **Sel kosong (predicate tanpa nilai valid, narrative dgn total=0) HARUS `null`** — JANGAN hasilkan `RekapNilaiSel` dgn label kosong/`"0/0"`. Ini keputusan eksplisit dari review user (spec §4.3), bukan detail sepele.
- **Definisi "terisi" narrative**: `trim($catatan ?? '') !== ''` — persis begini, jangan pakai `!empty($catatan)` (beda perilaku utk string `"0"`) atau `$catatan !== null` (tidak menangkap whitespace).

## Urutan eksekusi

**Task 1 → 2 → 3 → 4 murni LINEAR.**

**PERHATIAN KHUSUS Task 2**: task ini mengganti isi `RaporCalculationService.php` SEPENUHNYA (bukan edit sebagian) DAN meretrofit 3 file test lama (`RaporCalculationServiceTest.php`, `RaporCalculationServiceAssessmentTypeTest.php`, `RaporCalculationCompositeKeyTest.php`) ke kontrak DTO baru. Baca §"PENTING — kenapa 2 file test lama harus diretrofit" di kepala Task 2 sebelum mulai — nilai numeric yang diharapkan test TIDAK BOLEH berubah, cuma cara aksesnya (`->label` bukan float langsung).

**Kalau kamu punya akses ke skill `superpowers`:**
Plan ini medium (4 task, ~10 file tersentuh, tapi Task 2 padat — 1 file service ditulis ulang total + 3 file test diretrofit + 1 file test baru) — **REKOMENDASI pakai `superpowers:subagent-driven-development`**, terutama supaya Task 2 dapat review terpisah dari Task 3 (view). Kalau tidak tersedia, pakai `superpowers:executing-plans`.

**Kalau tidak punya skill itu sama sekali:**
Eksekusi manual:
1. Baca task, kerjakan tiap step persis seperti ditulis — kode di plan sudah lengkap, jangan menulis versi "yang menurutmu lebih baik".
2. **WAJIB baca file existing SEBELUM edit** (`RaporCalculationService.php`, kedua view, 3 file test yang diretrofit) dan bandingkan dgn kutipan "Files: Modify" di tiap task — kalau baseline beda dari yang plan asumsikan, STOP dan laporkan ke user.
3. Jalankan `php -l <file>` setelah tiap edit PHP, SEBELUM commit.
4. Jalankan test scoped SEBELUM commit — kalau gagal, JANGAN commit, diagnosis dulu (pakai Boost `last-error`/`read-log-entries` kalau errornya tidak jelas).
5. 4 commit total (satu per task, pesan commit sudah ditulis di tiap Step terakhir).
6. **JANGAN jalankan full test suite sampai Task 4 Step 2.**
7. Jalankan `vendor/bin/pint --dirty --format agent` di akhir Task 2 dan Task 3 (sudah tertulis eksplisit di step-nya).

## Peringatan eksplisit dari plan — titik yang HARUS diverifikasi, bukan diasumsikan

1. **Task 2 Step 5** — precedence HARUS `resolveNumeric() ?? resolvePredicate() ?? resolveNarrative()` (null-coalescing chain persis spt kode plan) — bukan if/else manual yang bisa salah urutan.
2. **Task 2 Step 5** — `totalNarrativeBySubjek` dihitung SEKALI SAJA di luar loop per-siswa (tidak bergantung siswa) — kalau diletakkan di dalam loop siswa, itu pemborosan query berulang yang tidak perlu (bukan bug fungsional, tapi menyimpang dari desain plan).
3. **Task 3 Step 1** — kolom "Rata-Rata Umum" (`$generalAvg`) di `_hasil.blade.php` HARUS difilter ulang supaya cuma menghitung dari sel yang `tuntas !== null` (numeric) — kalau tidak, kolom itu akan error/salah hasil krn sekarang `$rekapNilai[$siswa->id]` berisi campuran `RekapNilaiSel` DTO, bukan float polos.
4. **Task 3 Step 3** — test presisi yang menggantikan `assertSee('88')` lama HARUS membuktikan badge muncul SPESIFIK di sel per-mapel (bukan cuma di kolom ringkasan) — itulah kenapa bug lama lolos, jangan sampai test baru punya kelemahan yang sama.
5. **Task 4 Step 1** — grep akhir HARUS mencari pola `$mapel->id]` di KEDUA folder view (`akademik/rapor/` dan `rapor/persetujuan/`) — kalau ada view lain dgn bug yang sama yang terlewat audit awal, STOP dan laporkan ke user.

## Pelajaran penting dari sprint-sprint akademik sebelumnya (berlaku juga di sini)

1. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
2. **JANGAN jalankan lebih dari satu proses `php artisan test` bersamaan.**
3. **Klaim hasil test di handoff log HARUS bisa ditelusuri (command + angka pasti)** — jalankan `php artisan test --compact` TANPA filter apa pun di Task 4 utk klaim "full suite hijau".
4. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan — STOP dan laporkan ke user, JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.**
5. **Jangan menambah scope di luar 4 task ini** — termasuk godaan menambah kartu ringkasan kelas baru utk distribusi predicate/narrative (eksplisit Non-Goal di spec §6), atau menyentuh `RaporPdfDataBuilder`/rapor PDF per siswa (di luar scope, spec §6).

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Cek dulu via Boost (`search-docs`/`database-schema`/`database-query`) kalau itu pertanyaan teknis yang bisa dijawab tool, baru laporkan ke user kalau tetap ambigu.

## Definisi selesai

- Task 1: `RekapNilaiSel` DTO lulus 2 unit test.
- Task 2: `RaporCalculationService` tertulis ulang, 3 file test lama diretrofit ke kontrak DTO (nilai numeric TIDAK berubah), 1 file test baru (`RaporCalculationServiceTypeAwareTest.php`) lulus 8 test (modus, tie-break, predicate-null, narrative-completion-rate, narrative-whitespace, narrative-zero-slot-null, precedence numeric>predicate, precedence predicate>narrative, classAvg-null-utk-PAUD).
- Task 3: kedua view (`_hasil.blade.php`, `persetujuan/show.blade.php`) pakai `$subjekKey` composite utk lookup (bukan `$mapel->id`), render badge sesuai tipe, 2 test feature baru/diperkuat membuktikan bug key-mismatch tertutup di kedua halaman.
- Task 4: grep akhir nol referensi liar ke pola `$mapel->id]`, **full test suite (`php artisan test --compact` tanpa filter) 0 failed**, angka pasti dicatat, `PETA_PENGEMBANGAN.md` sudah ditandai Prioritas #2 SELESAI, laporan final ke user berisi angka pasti + 4 commit hash.
