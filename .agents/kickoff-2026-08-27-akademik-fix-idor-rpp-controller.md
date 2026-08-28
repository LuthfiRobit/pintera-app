# Kickoff Prompt — Fix Kritis IDOR Lintas-Guru RppController

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP untuk **FIX KEAMANAN KRITIS** — celah IDOR (Insecure Direct Object Reference) lintas-guru pada `RppController`, temuan paling serius dari audit ulang total 4-layer modul Akademik. Kamu tidak perlu audit ulang, tidak perlu menulis spec baru. Prioritaskan ketelitian di atas kecepatan — ini fix keamanan, bukan fitur.

## Yang harus kamu baca dulu

1. `.agents/specs/2026-08-27-akademik-fix-idor-rpp-controller.md` — spec lengkap. §1 WAJIB dibaca — menjelaskan persis celahnya dan kenapa `RppController` dipakai 2 aktor berbeda (guru pemilik vs verifikator).
2. `.agents/plans/2026-08-27-akademik-fix-idor-rpp-controller.md` — plan implementasi (5 task, kode lengkap, TDD step-by-step).

## Konteks penting — kenapa fix ini ada

`RppController::update/submit/destroy/download` tidak pernah mengecek `$rpp->guru_id` cocok dengan guru yang login — hanya permission generik (`rpp.kelola`/`rpp.view`) yang dimiliki SEMUA guru di satu lembaga. Guru A bisa mengubah/menghapus/mengajukan/mengunduh RPP milik Guru B hanya dengan mengganti ID di URL. `store()` juga tidak verifikasi guru benar-benar mengajar kelas yang dipilih. `VerifyRppAction` juga kurang lapis cross-check `lembaga_id` eksplisit (defense-in-depth, beda dari pola Rapor sejenis).

## Peringatan PALING KRITIS — 2 aktor berbeda di controller yang sama

`RppController` dipakai GURU (pemilik dokumen) DAN WAKA KURIKULUM/KEPSEK (verifikator). **JANGAN** menerapkan ownership-check guru ke method `verify()` — itu justru harus BISA diakses aktor lain (verifikator), bukan guru pemilik. Begitu juga `download()` — HARUS bisa diakses guru pemilik ATAU siapa pun dengan permission `rpp.verify`, BUKAN cuma guru pemilik saja. Plan sudah menulis kode yang benar untuk masing-masing method — SALIN PERSIS, jangan disamaratakan.

## Peringatan KEDUA — regresi test yang SUDAH diketahui, WAJIB ditambal di Task 3, bukan "dicek nanti"

`tests/Feature/Akademik/StoreRppRequestKelasSemesterTest.php` (dari sprint Kelompok C sebelumnya) mem-POST ke `store()` TANPA `mata_pelajaran_id` — ini akan jatuh ke cabang "tematik/wali kelas" di fix baru dan GAGAL karena kelasnya belum py `wali_kelas_guru_id`. Plan Task 3 Step 5 SUDAH menulis fix eksplisit untuk ini (1 baris `$kelasTahunA->update(['wali_kelas_guru_id' => $guru->id]);` di fixture) — WAJIB diterapkan, jangan dilewati.

## Proyek ini pakai Laravel Boost — WAJIB dipakai selama eksekusi

- Kalau ragu struktur tabel (`rpp`, `jadwal_pelajaran`, `kelas`) — pakai `database-schema`, jangan buka migration manual.
- **JANGAN buat script verifikasi terpisah/tinker** — test yang ditulis plan sudah cukup.

## Project Rules gate — WAJIB dibaca sebelum edit apa pun

Buka `.ai/rules/index.md`, lalu baca rule file yang glob-nya mencakup path yang disentuh plan ini:

- `.ai/rules/controllers.md` (Task 2, 3, 4: `RppController`)
- `.ai/rules/actions.md` (Task 4: `VerifyRppAction`)
- `.ai/rules/tests.md`

Kalau ada isi rule yang tampak bertentangan dgn instruksi plan, STOP dan laporkan konfliknya ke user — jangan diam-diam pilih salah satu.

## Konteks singkat — PENTING, baca sebelum eksekusi

- Repo Laravel 12.68.0 multi-tenant (`pintera-app`), Pest v4, MySQL. Branch kerja: `akademik-v2` (SUDAH ADA — jangan buat branch baru/worktree).
- **Task 4**: `VerifyRppAction::execute()` bertambah parameter WAJIB baru `int $verifierLembagaId` — WAJIB grep dulu SEMUA pemanggil method ini (`grep -rn "verifyRppAction->execute\|VerifyRppAction::execute" app/ tests/`) sebelum ubah signature, pastikan tidak ada pemanggil lain di luar yang disebut plan.
- **Task 3**: verifikasi kombinasi mengajar HANYA berlaku kalau `$guru` (variabel yang SUDAH ADA di `store()`, dari `Guru::where('user_id', $user->id)->first()`) tidak null — JANGAN buat pemanggilan `auth()->user()->guru` baru, pakai variabel yang sudah ada.
- **Task 5 (terakhir) WAJIB full test suite** (`php artisan test --compact` TANPA filter) — ini fix otorisasi yang bisa berdampak ke test lain di luar domain RPP. Kalau ada kegagalan di luar file yang disebut plan, itu tanda ada test lain yang memanipulasi `Rpp`/`VerifyRppAction` tanpa setup yang benar — perbaiki fixture-nya, JANGAN longgarkan guard baru untuk meloloskan test.

## Urutan eksekusi

**Task 1 → 2 → 3 → 4 → 5 murni LINEAR** (Task 1 prasyarat baseline, Task 5 checkpoint penutup).

**Kalau kamu punya akses ke skill `superpowers`:**
Plan ini menengah (5 task, ~6 file tersentuh, 1 method signature berubah) — `subagent-driven-development` direkomendasikan karena ini fix keamanan yang perlu review dua-lapis (task reviewer per task).

**Kalau tidak punya skill itu sama sekali:**
Eksekusi manual:
1. Baca task, kerjakan tiap step persis seperti ditulis — kode di plan sudah lengkap.
2. **WAJIB baca file existing SEBELUM edit** (`RppController.php` penuh, `VerifyRppAction.php`, `RppWorkflowTest.php` baris 1-96, `StoreRppRequestKelasSemesterTest.php` baris 1-38) dan bandingkan dgn kutipan di plan — kalau baseline beda, STOP dan laporkan ke user.
3. Jalankan `php -l <file>` setelah tiap edit PHP, SEBELUM commit.
4. Jalankan test scoped SEBELUM commit — kalau gagal, JANGAN commit, diagnosis dulu.
5. 7 commit total (5 task, salah satunya Task 3 bisa 1 commit gabungan kalau ada penyesuaian test tambahan), pesan commit sudah ditulis di tiap Step terakhir.
6. **Full suite HANYA di Task 5** — jangan jalankan di task lain.

## Peringatan eksplisit dari plan — titik yang HARUS diverifikasi, bukan diasumsikan

1. **Task 2** — `authorizeMilikGuru()` TIDAK MENGGANTIKAN `$this->authorize('rpp.kelola')` yang sudah ada di `submit()`/`destroy()` — dua lapis, bukan pengganti. Jangan hapus baris `authorize()` yang lama.
2. **Task 2** — `download()` guard-nya BUKAN `authorizeMilikGuru()` (private method Task 2) tapi guard inline terpisah yang mengizinkan pemilik ATAU `rpp.verify` — jangan disamakan dengan method `authorizeMilikGuru()`.
3. **Task 3** — percabangan verifikasi (mapel vs tematik/wali-kelas) dibungkus `if ($guru !== null)` — untuk aktor non-guru (admin membuatkan RPP atas nama guru lain), verifikasi ini DILEWATI SEPENUHNYA, bukan gagal.
4. **Task 4** — parameter baru `$verifierLembagaId` disisipkan SEBELUM `$catatanRevisi` (yang punya default value) di signature — parameter tanpa default tidak boleh setelah parameter dengan default.

## Pelajaran penting dari sprint-sprint akademik sebelumnya (berlaku juga di sini)

1. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
2. **JANGAN jalankan lebih dari satu proses `php artisan test` bersamaan.**
3. **Klaim hasil test di handoff log HARUS bisa ditelusuri (command + angka pasti).**
4. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan — STOP dan laporkan ke user, JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.**
5. **Ini fix keamanan — jangan pernah melonggarkan guard yang baru ditambahkan hanya demi meloloskan test yang gagal.** Kalau test gagal karena fixture-nya butuh setup baru (guru jadi wali kelas / punya JadwalPelajaran yang sesuai), perbaiki fixture-nya, BUKAN kode guard.

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Cek dulu via Boost (`database-schema`/`database-query`) kalau itu pertanyaan teknis yang bisa dijawab tool, baru laporkan ke user kalau tetap ambigu.

## Definisi selesai

- Task 1: fixture `RppWorkflowTest.php` punya `wali_kelas_guru_id` terisi, test existing tetap PASS.
- Task 2: Guru B tidak bisa update/submit/destroy/download RPP milik Guru A (403), Guru A tetap bisa mengelola miliknya sendiri, verifikator (`rpp.verify`) tetap bisa download.
- Task 3: `store()` menolak guru yang tidak mengajar kombinasi kelas+mapel+semester, atau bukan wali kelas untuk RPP tematik — fixture `StoreRppRequestKelasSemesterTest.php` sudah ditambal.
- Task 4: `VerifyRppAction` menolak verifier dari lembaga lain, verifier lembaga sama tetap sukses.
- Task 5: **Full test suite (`php artisan test --compact` tanpa filter) 0 failed**, angka pasti dicatat, `PETA_PENGEMBANGAN.md` dicatat, laporan final ke user berisi angka pasti + commit hash + konfirmasi celah IDOR sudah tertutup di 4 titik (update/submit/destroy/download + store + verify).
