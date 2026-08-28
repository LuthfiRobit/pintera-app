# Kickoff Prompt — Audit Sistematis Akademik Tahap 2, Kelompok B (Kenaikan Kelas UX)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP, hasil brainstorming + spec + review manusia yang ketat (spec ini sudah direvisi 1x berdasarkan code review manual sebelum plan ditulis — semua ambiguitas sudah diselesaikan). Kamu tidak perlu audit ulang, tidak perlu menulis spec baru.

## Yang harus kamu baca dulu

1. `.agents/specs/2026-08-27-akademik-audit-2-kelompok-b.md` — spec lengkap (sudah direvisi, versi final). §1 dan §2.1 WAJIB dibaca — menjelaskan kenapa `isTingkatAkhir()` TIDAK BOLEH diderivasi generik dari `validTingkatValues()`.
2. `.agents/plans/2026-08-27-akademik-audit-2-kelompok-b.md` — plan implementasi (3 task, kode lengkap, TDD step-by-step).

## Konteks penting — kenapa fix ini ada

Audit sistematis tahap 2 (setelah Kelompok A selesai) menemukan 2 gap UX safety-net di fitur Kenaikan Kelas (workflow manual admin-driven, BUKAN bug data): (1) tidak ada saran otomatis "Lulus" untuk kelas di tingkat akhir jenjangnya, (2) tidak ada peringatan kalau kurikulum kelas tujuan berbeda dari kelas asal. Temuan ke-3 dari audit awal (guard `bentuk_pendidikan`) sudah DIVERIFIKASI BUKAN gap nyata — jangan dikerjakan, itu sudah tercover guard lintas-`lembaga_id` existing.

## Peringatan PALING KRITIS — jangan sampai regresi Priority #3

`app/Domains/Akademik/Enums/BentukPendidikan.php` sudah punya `validTingkatValues()` yang mengelompokkan `Kb, Tpa, Sps, Tk` sama-sama menghasilkan `['A','B']`. **JANGAN PERNAH** menulis `isTingkatAkhir()` sbg `end($this->validTingkatValues())` atau bentuk generik apa pun turunan dari situ — itu akan membuat KB/TPA/SPS di tingkat "B" salah dianggap tingkat akhir, meregresi keputusan bisnis terkunci Priority #3 (Kelulusan PAUD & SLB: hanya TK-B yang boleh dianggap tingkat akhir, KB/TPA/SPS dikecualikan PERMANEN). Plan sudah menulis implementasi `match` eksplisit yang benar — SALIN PERSIS kodenya, jangan "disederhanakan".

Ada test regresi YANG SUDAH ADA sebelum plan ini (`tests/Feature/Akademik/RaporPdfDataBuilderIsTingkatAkhirTest.php`) yang secara eksplisit menguji KB/TPA/SPS tetap `false` — kalau test ini gagal setelah refactor Task 1, itu tanda regresi nyata, JANGAN ubah test-nya, perbaiki kode.

## Proyek ini pakai Laravel Boost — WAJIB dipakai selama eksekusi

- Kalau ragu struktur tabel (`kelas`, `lembaga`) — pakai `database-schema`, jangan buka migration manual.
- **JANGAN buat script verifikasi terpisah/tinker** — test yang ditulis plan sudah cukup.
- **PENTING**: `symfony/dom-crawler` TIDAK terinstal di proyek ini. Test yang butuh scoping HTML per baris kelas (Task 2 & 3) pakai helper function manual (regex + `strrpos` cari posisi `<tr` sungguhan) yang SUDAH ditulis lengkap di plan — SALIN PERSIS, jangan mencoba pakai `$response->getCrawler()` atau menambah dependency baru tanpa izin user.

## Project Rules gate — WAJIB dibaca sebelum edit apa pun

Buka `.ai/rules/index.md`, lalu baca rule file yang glob-nya mencakup path yang disentuh plan ini:

- `.ai/rules/domains-enums.md` (Task 1: `BentukPendidikan`)
- `.ai/rules/services.md` (Task 1: `RaporPdfDataBuilder`)
- `.ai/rules/controllers.md` (Task 2: `KenaikanKelasController`)
- `.ai/rules/views.md`, `.ai/rules/js.md` (Task 2, 3: view + Alpine inline)
- `.ai/rules/tests.md`

Kalau ada isi rule yang tampak bertentangan dgn instruksi plan, STOP dan laporkan konfliknya ke user — jangan diam-diam pilih salah satu.

## Konteks singkat — PENTING, baca sebelum eksekusi

- Repo Laravel 12.68.0 multi-tenant (`pintera-app`), Pest v4, MySQL. Branch kerja: `akademik-v2` (SUDAH ADA — jangan buat branch baru/worktree).
- **Task 1**: `tests/Unit/Domains/Akademik/Enums/BentukPendidikanTest.php` SUDAH ADA (isi test `validTingkatValues()`) — plan MENAMBAH test baru ke file yang sama, BUKAN membuat file baru. Jangan bikin file duplikat.
- **Task 2 & 3**: `tests/Feature/Akademik/KenaikanKelasControllerUxTest.php` adalah file BARU, terpisah dari `tests/Feature/Admin/KenaikanKelasControllerTest.php` yang sudah ada (yang itu menguji business logic `execute()`, jangan disentuh/digabung).
- **Tidak ada validasi server-side baru** — `ProsesKenaikanKelasAction::execute()` TIDAK BOLEH diubah sama sekali di plan ini. Semua perubahan murni di Controller (query eager-load) dan View (Blade + Alpine).
- **Peringatan kurikulum HANYA muncul kalau KEDUA sisi non-null dan berbeda** — kalau kurikulum kelas asal `null`, peringatan TIDAK PERNAH muncul apa pun kurikulum tujuannya. Ini keputusan sadar dari spec, bukan celah yang perlu "diperbaiki".
- **Test markup Blade WAJIB discope ke baris/select yang benar** — jangan pakai `assertSee()` global kalau ada >1 baris kelas di test, itu bisa false-positive.

## Urutan eksekusi

**Task 1 → 2 → 3 murni LINEAR.**

**Kalau kamu punya akses ke skill `superpowers`:**
Plan ini kecil (3 task, ~5 file tersentuh) — boleh eksekusi manual langsung (`superpowers:executing-plans`), atau `subagent-driven-development` kalau mau ekstra hati-hati.

**Kalau tidak punya skill itu sama sekali:**
Eksekusi manual:
1. Baca task, kerjakan tiap step persis seperti ditulis — kode di plan sudah lengkap.
2. **WAJIB baca file existing SEBELUM edit** (`BentukPendidikan.php`, `RaporPdfDataBuilder.php` baris 145-157, `KenaikanKelasController.php` baris 20-41, `kenaikan-kelas/index.blade.php`) dan bandingkan dgn kutipan di plan — kalau baseline beda, STOP dan laporkan ke user.
3. Jalankan `php -l <file>` setelah tiap edit PHP, SEBELUM commit.
4. Jalankan test scoped SEBELUM commit — kalau gagal, JANGAN commit, diagnosis dulu.
5. 4 commit total (3 task + 1 docs), pesan commit sudah ditulis di tiap Step terakhir.
6. **JANGAN jalankan full test suite** — sesuai Global Constraints plan ini, full suite ditunda sampai checkpoint gabungan Kelompok B+C nanti (bukan bagian plan ini). Cukup test scoped yang disebutkan di Task 3 Step 5.

## Peringatan eksplisit dari plan — titik yang HARUS diverifikasi, bukan diasumsikan

1. **Task 1 Step 7** — 2 file test regresi (`RaporPdfDataBuilderIsTingkatAkhirTest.php` dan `RaporPdfDataBuilderTest.php`) HARUS tetap pass tanpa modifikasi assertion apa pun.
2. **Task 2 Step 5** — `$kelasLama->lembaga->bentuk_pendidikan` adalah STRING biasa (model `Lembaga` tidak cast ke enum, dikonfirmasi Priority #7) — `BentukPendidikan::from(...)` menerima string itu langsung, jangan tambah `->value` atau konversi lain.
3. **Task 3 Step 3** — urutan penempatan `x-data` di `<tr>` dan penambahan `data-kurikulum`/`data-tingkat` di `<option>` HARUS persis seperti kutipan plan — kalau attribute name berbeda (mis. `data-kurikulum-tujuan` bukan `data-kurikulum`), test helper di Task 3 Step 1 akan gagal karena mencari string `data-kurikulum="..."` eksak.
4. **Test HTML-scoping helper** (`htmlSelectByName`, `selectedOptionValue`, dan pola `strrpos(..., '<tr')` di Task 3) sudah ditulis lengkap di plan — SALIN PERSIS regex-nya, jangan menulis ulang dengan pendekatan berbeda yang belum diverifikasi.

## Pelajaran penting dari sprint-sprint akademik sebelumnya (berlaku juga di sini)

1. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
2. **JANGAN jalankan lebih dari satu proses `php artisan test` bersamaan.**
3. **Klaim hasil test di handoff log HARUS bisa ditelusuri (command + angka pasti).**
4. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan — STOP dan laporkan ke user, JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.**
5. **Jangan menambah scope di luar 3 task ini** — termasuk godaan mengerjakan Kelompok C (RPP reporting) yang disebut sbg "menyusul terpisah" — itu bukan bagian plan ini.
6. **Jangan tambah dependency baru** (mis. `symfony/dom-crawler`) tanpa izin eksplisit user — proyek ini sengaja tidak menginstalnya, plan sudah menyediakan alternatif tanpa dependency baru.

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Cek dulu via Boost (`database-schema`/`database-query`) kalau itu pertanyaan teknis yang bisa dijawab tool, baru laporkan ke user kalau tetap ambigu.

## Definisi selesai

- Task 1: `BentukPendidikan::isTingkatAkhir()` ada dan benar utk 9 case + null (14 assertion data-driven dari spec §4.1), `RaporPdfDataBuilder` delegasi tanpa mengubah 2 file test regresi existing.
- Task 2: dropdown tindakan Kenaikan Kelas pre-select "Lulus" utk kelas tingkat akhir, test scoped per baris (bukan `assertSee` global) PASS.
- Task 3: peringatan kurikulum live via Alpine inline, markup Blade contract (data attribute + expression) teruji PASS, runtime JS TIDAK diklaim teruji oleh test otomatis (sesuai spec §4.4 lapis 2).
- Test scoped gabungan Task 3 Step 5 (5 file test) 0 failed, angka pasti dicatat, `PETA_PENGEMBANGAN.md` sudah dicatat tindak lanjutnya, laporan final ke user berisi angka pasti + commit hash.
