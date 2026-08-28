# Kickoff Prompt — Audit Sistematis Akademik Tahap 2, Kelompok C (RPP + Test Coverage, PENUTUP)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP. Ini adalah **Kelompok C, kelompok TERAKHIR** dari audit sistematis Akademik tahap 2 (Kelompok A dan B sudah SELESAI dan sudah direview sebelumnya). Spec ini sudah direvisi 1x berdasarkan code review manual yang ketat sebelum plan ditulis — semua ambiguitas sudah diselesaikan. Kamu tidak perlu audit ulang, tidak perlu menulis spec baru.

## Yang harus kamu baca dulu

1. `.agents/specs/2026-08-27-akademik-audit-2-kelompok-c.md` — spec lengkap (versi final, sudah direvisi).
2. `.agents/plans/2026-08-27-akademik-audit-2-kelompok-c.md` — plan implementasi (3 task, kode lengkap, TDD step-by-step).

## Konteks penting — kenapa fix ini ada & kenapa ini PENUTUP

3 item terakhir dari audit sistematis tahap 2: (1) daftar RPP tidak bisa dilaporkan per kurikulum meski `kelas.kurikulum` sudah ada sejak Priority #1, (2) form RPP tidak validasi bahwa kelas & semester yang dipilih berasal dari tahun ajaran yang sama, (3) tidak ada test regresi yang membuktikan guard cross-tenant IDOR ekstrakurikuler tetap benar ke depan. **Task 3 Step 5 di plan ini adalah SATU-SATUNYA titik di seluruh audit tahap 2 (mencakup Kelompok A, B, DAN C) di mana full test suite dijalankan** — checkpoint penutup gabungan, bukan per-kelompok. Ini keputusan eksplisit soal kadensi test yang sudah disepakati user sebelumnya (jangan jalankan full suite di titik lain manapun di plan ini).

## Peringatan PALING KRITIS — jangan sampai filter kurikulum bocor antar jalur AJAX/full-page

`RppController::index()` memanggil `$this->listRppAction->execute(...)` **SATU KALI** sebelum percabangan `if ($request->ajax())`. Filter `kurikulum` HARUS ditambahkan ke pemanggilan tunggal itu (lihat Task 1 Step 5) — **JANGAN** membuat cabang kode terpisah untuk menambahkan filter kurikulum khusus di salah satu jalur (ajax atau full-page). Kalau kamu menemukan dirimu menulis `if ($request->ajax()) { ...tambah kurikulum di sini... } else { ...tambah lagi di sini... }`, itu tanda kamu salah paham arsitekturnya — STOP dan baca ulang kode existing.

## Proyek ini pakai Laravel Boost — WAJIB dipakai selama eksekusi

- Kalau ragu struktur tabel (`rpp`, `kelas`, `ekstrakurikuler_lembaga`) — pakai `database-schema`, jangan buka migration manual.
- **JANGAN buat script verifikasi terpisah/tinker** — test yang ditulis plan sudah cukup.
- **PENTING**: `symfony/dom-crawler` TIDAK terinstal di proyek ini (sama seperti Kelompok B). Test scoping HTML pakai helper `chunkSekitarTeks()` (regex + `strrpos` cari posisi `<tr` sungguhan) yang SUDAH ditulis lengkap di plan Task 1 Step 2 — SALIN PERSIS.

## Project Rules gate — WAJIB dibaca sebelum edit apa pun

Buka `.ai/rules/index.md`, lalu baca rule file yang glob-nya mencakup path yang disentuh plan ini:

- `.ai/rules/actions.md` (Task 1: `ListRppAction`)
- `.ai/rules/controllers.md` (Task 1: `RppController`)
- `.ai/rules/requests.md` (Task 2: `StoreRppRequest`/`UpdateRppRequest`)
- `.ai/rules/views.md`, `.ai/rules/js.md` (Task 1: view + Alpine)
- `.ai/rules/tests.md`

Kalau ada isi rule yang tampak bertentangan dgn instruksi plan, STOP dan laporkan konfliknya ke user — jangan diam-diam pilih salah satu.

## Konteks singkat — PENTING, baca sebelum eksekusi

- Repo Laravel 12.68.0 multi-tenant (`pintera-app`), Pest v4, MySQL. Branch kerja: `akademik-v2` (SUDAH ADA — jangan buat branch baru/worktree).
- **Task 1**: tidak perlu ubah `resources/js/rpp.js` sama sekali — `muatUlangDaftar()` sudah generik meng-iterasi objek `filters`, key `kurikulum` otomatis ikut terkirim. Kalau kamu tergoda menambah logic khusus di JS untuk filter ini, itu tanda desainnya disalahpahami — plan sudah menjelaskan ini eksplisit.
- **Task 1**: `?kurikulum=<nilai tidak dikenal>` WAJIB fallback ke "tanpa filter" (bukan error 500, bukan hasil kosong) — validasi terhadap `KurikulumFramework::cases()` di controller SEBELUM diteruskan ke Action.
- **Task 1 test**: memakai `tab=saya` (BUKAN `tab=verifikasi`) — plan sudah menjelaskan kenapa: `tab=verifikasi` butuh permission `rpp.verify` tambahan DAN otomatis memfilter `status=Diajukan`, sedangkan fixture RPP di test dibuat berstatus `Draft`. Ini bug yang sudah ditemukan & diperbaiki saat plan ditulis — jangan diubah balik ke `verifikasi`.
- **Task 2**: `withValidator()` MELENGKAPI rule `exists:kelas,id`/`exists:semester,id` yang sudah ada, BUKAN pengganti — jangan hapus rule `exists` yang lama.
- **Task 2**: `UpdateRppRequest` TIDAK PUNYA `semester_id` di request sama sekali (semester RPP tidak bisa diubah saat update) — perbandingannya terhadap `$this->route('rpp')->semester->tahun_ajaran_id`, BUKAN terhadap input request.
- **Task 3**: `EkstrakurikulerController` TIDAK BOLEH diubah — kode guard-nya sudah benar dan diverifikasi manual sebelumnya, Task 3 murni menambah test pembuktian. Kalau test yang kamu tulis ternyata GAGAL (bukan karena salah tulis test, tapi kode produksi memang salah), STOP dan laporkan ke user — jangan langsung "perbaiki" kode produksi tanpa konfirmasi, itu di luar scope plan ini.
- **Test IDOR (Task 3)**: WAJIB pakai 2 lembaga + 2 manager (`$managerB` baru, BUKAN `$this->manager` yang sudah ada) — kalau cuma 1 lembaga dgn 2 record, itu tidak membuktikan cross-tenant apa pun.

## Urutan eksekusi

**Task 1 → 2 → 3 murni LINEAR.** Task 3 Step 5 (full suite) HARUS jadi langkah TERAKHIR sebelum docs — jangan dipindah ke tempat lain.

**Kalau kamu punya akses ke skill `superpowers`:**
Plan ini sedang (3 task, ~9 file tersentuh termasuk test baru) — boleh eksekusi manual langsung (`superpowers:executing-plans`), atau `subagent-driven-development`.

**Kalau tidak punya skill itu sama sekali:**
Eksekusi manual:
1. Baca task, kerjakan tiap step persis seperti ditulis — kode di plan sudah lengkap.
2. **WAJIB baca file existing SEBELUM edit** (`ListRppAction.php`, `RppController.php` baris 47-129, `_daftar.blade.php` baris 186-190, `index.blade.php` baris 1-16 & 189-198, `StoreRppRequest.php`, `UpdateRppRequest.php`, `LembagaRelationalManagementTest.php` baris 1-53, `EkstrakurikulerController.php`) dan bandingkan dgn kutipan di plan — kalau baseline beda, STOP dan laporkan ke user.
3. Jalankan `php -l <file>` setelah tiap edit PHP, SEBELUM commit.
4. Jalankan test scoped SEBELUM commit — kalau gagal, JANGAN commit, diagnosis dulu.
5. 4 commit total (3 task + 1 docs), pesan commit sudah ditulis di tiap Step terakhir.
6. **Full suite HANYA di Task 3 Step 5** — jangan jalankan di step/task lain.

## Peringatan eksplisit dari plan — titik yang HARUS diverifikasi, bukan diasumsikan

1. **Task 1 Step 4** — parameter `?string $kurikulum = null` ditambahkan di AKHIR daftar parameter `ListRppAction::execute()`, dengan default value — supaya tidak mematahkan test lama yang memanggil method ini dengan named argument tanpa `kurikulum`.
2. **Task 1 Step 6** — badge tone HARUS pakai `green`/`blue`/`slate` (tone valid komponen `<x-badge>` proyek ini), BUKAN `emerald`/`gray`/`purple` (tone yang tidak ada di komponen, akan diam-diam fallback ke slate tanpa error).
3. **Task 2 Step 4/5** — cek dulu apakah `App\Models\Kelas`/`App\Models\Semester` sudah di-import di masing-masing file SEBELUM menambah import baru — kedua file sudah punya sebagian import ini untuk method `toDTO()`, jangan duplikasi.
4. **Task 3 Step 5** — jalankan `php artisan test --compact` TANPA filter apa pun, catat angka pasti (passed/skipped/assertions/durasi) verbatim dari output nyata di laporan akhir.

## Pelajaran penting dari sprint-sprint akademik sebelumnya (berlaku juga di sini)

1. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
2. **JANGAN jalankan lebih dari satu proses `php artisan test` bersamaan.**
3. **Klaim hasil test di handoff log HARUS bisa ditelusuri (command + angka pasti).**
4. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan — STOP dan laporkan ke user, JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.**
5. **Ini kelompok TERAKHIR** — jangan menambah scope baru di luar 3 task ini, apa pun godaannya. Kalau full suite di Task 3 Step 5 menemukan kegagalan TIDAK TERKAIT (pre-existing/flaky), laporkan ke user, jangan "sekalian diperbaiki".

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Cek dulu via Boost (`database-schema`/`database-query`) kalau itu pertanyaan teknis yang bisa dijawab tool, baru laporkan ke user kalau tetap ambigu.

## Definisi selesai

- Task 1: badge kurikulum tampil scoped per baris RPP (termasuk "Belum Diketahui" utk legacy null), filter kurikulum dua arah bekerja konsisten di full-page & AJAX fragment, nilai invalid fallback aman.
- Task 2: `StoreRppRequest`/`UpdateRppRequest` menolak kombinasi kelas-semester tidak konsisten dgn error spesifik di `kelas_id`, kombinasi valid tetap sukses seperti biasa.
- Task 3: 2 test IDOR baru PASS tanpa perubahan kode produksi.
- **Full test suite (`php artisan test --compact` tanpa filter) 0 failed** — checkpoint penutup SELURUH audit sistematis tahap 2 (Kelompok A+B+C). Angka pasti dicatat, `PETA_PENGEMBANGAN.md` dicatat penutupnya, laporan final ke user berisi angka pasti + commit hash + konfirmasi bahwa audit tahap 2 secara keseluruhan SELESAI.
