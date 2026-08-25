# Kickoff Prompt — Perbaikan Halaman Peran (Keamanan Nama Role, Scope Platform, Chip Filter, UX Matriks)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan siap jalan. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu

1. `.agents/specs/2026-08-25-halaman-peran-perbaikan.md` — spec lengkap (9 masalah yang ditemukan, detail perbaikan §4-§9).
2. `.agents/plans/2026-08-25-halaman-peran-perbaikan.md` — plan implementasi (11 task, kode lengkap per task).

## Konteks singkat

- Repo Laravel 12 multi-tenant (`pintera-app`). Branch kerja: `rbac-v2` (SUDAH ADA — jangan buat branch baru, jangan buat worktree).
- **Baseline kode plan ini: commit `1602eb3`.** Kalau isi file yang kamu baca BEDA signifikan dari yang dikutip plan, STOP, laporkan ke user.
- **Kenapa sub-project ini ada**: review terhadap halaman "Peran" (setelah 2 sub-project sebelumnya menyempurnakan halaman Pengguna) menemukan **bug keamanan HIGH**: nama role `is_protected` (mis. `guru`, `yayasan_super_admin`) bisa diubah bebas lewat form edit — TIDAK ADA guard di model, controller, ATAU UI. Kalau nama role protected berubah, SEMUA `hasRole('guru')`/middleware gate di seluruh codebase rusak diam-diam. Plus ditemukan `RoleController` masih pakai `scopeRank()`/validasi `scope_level` versi PRA-RBAC-v2 (belum mengenali `platform`, padahal `UserController::scopeRank()` sudah diperbaiki di sub-project sebelumnya) — dua implementasi terpisah, cuma satu yang di-update.
- **PALING KRITIS**: nama role protected dikunci di 3 LAPIS sekaligus — model (`Role::saving()` guard), controller (`update()` skip field `name` untuk protected, JANGAN sekadar mengandalkan exception dari model sebagai satu-satunya pertahanan), dan UI (`edit.blade.php` `:disabled`). Permission BOLEH tetap diubah untuk role protected — itu perilaku existing yang TIDAK berubah, JANGAN ikut dikunci.
- **Data seeder demo buang-pakai** — `migrate:fresh --seed` boleh dijalankan sesering perlu, tapi kebanyakan task diverifikasi lewat Pest test.

## Urutan eksekusi

Task 1 (guard model) WAJIB pertama — Task 2 bergantung padanya (test Task 2 sengaja memicu exception dari guard Task 1 untuk membuktikan controller menangkapnya dengan baik, BUKAN membiarkan HTTP 500). Task 3 (scopeRank platform) independen, bisa kapan saja setelah Task 2. Task 4-5 (Blade opsi Platform + kunci nama UI) SETELAH Task 2-3 selesai. Task 6-8 (index/chip/format/link/tooltip) independen dari Task 1-5, bisa dikerjakan kapan saja tapi urutan 6→7→8 lebih mudah (7 dan 8 sama-sama butuh variabel dari Task 6). Task 9 (live search + edukatif) independen, butuh `$isPlatformActor` dari Task 4. Task 10 (checklist manual browser) SETELAH semua Blade selesai (Task 4,5,7,9). Task 11 di akhir.

**Kalau kamu punya akses ke skill `superpowers`:**
Gunakan `superpowers:subagent-driven-development` — fresh subagent per task, task review, ledger progress di `.superpowers/halaman-peran-perbaikan/progress.md`.

**Kalau tidak punya skill itu:**
Eksekusi manual task-by-task (Task 1 → 11):
1. Baca task, kerjakan tiap step persis seperti ditulis (kode di plan itu final).
2. WAJIB baca isi file existing dulu sebelum menimpa/mengedit — pastikan cocok dengan yang dikutip plan.
3. Jalankan `php -l <file>` (syntax check PHP) / `npm run build` (JS) setelah tiap edit, SEBELUM commit.
4. Jalankan test scoped SEBELUM commit — kalau gagal, JANGAN commit, diagnosis dulu.
5. Satu commit per task.
6. Jangan jalankan full test suite sampai Task 11.
7. Task 11 Step 3 butuh persetujuan user EKSPLISIT sebelum full suite (Step 4).

## Peringatan eksplisit dari plan — beberapa task punya ketidakpastian yang HARUS diverifikasi, bukan diasumsikan

1. **Task 8 (test tooltip permission "+N lainnya")** — test memakai 6 permission baru (`a.view`..`f.view`) dan mengasumsikan urutan alfabetis + limit 5 menghasilkan sisa persis 1. Kalau test environment sudah punya permission lain yang ter-seed sebelumnya (dari `RefreshDatabase` seharusnya bersih, tapi verifikasi), atau urutan berbeda dari dugaan, verifikasi via tinker dulu sebelum menyimpulkan test salah.
2. **Task 5 (test render `:disabled`)** — test HANYA memverifikasi markup HTML `:disabled="isProtected"` ADA di response, BUKAN memverifikasi perilaku JS sungguhan (Pest tidak menjalankan Alpine/JS). Verifikasi perilaku sungguhan (input benar-benar tidak bisa diketik di browser) masuk checklist manual Task 10 — JANGAN anggap Task 5 sudah cukup membuktikan fitur ini bekerja end-to-end.
3. **Task 10 (checklist manual)** — WAJIB dijalankan dan hasilnya WAJIB dicatat per-poin di handoff log (Task 11), BUKAN cuma ditulis "sudah dites di browser" tanpa detail. Kalau kamu (executor) tidak punya akses browser/GUI untuk menjalankan checklist ini, STOP dan laporkan ke user — jangan mengarang hasil checklist yang tidak benar-benar dijalankan.

## Pelajaran penting dari sub-project sebelumnya di repo ini

1. **Bug keamanan yang jadi alasan sub-project ini ada ditemukan lewat review kode independen (verifikasi manual ke source code), BUKAN oleh executor sub-project sebelumnya.** Kalau kamu menemukan sesuatu yang mencurigakan di luar cakupan plan saat mengerjakan task, JANGAN abaikan, laporkan ke user meski di luar scope.
2. **Dua implementasi `scopeRank()` terpisah (`UserController` dan `RoleController`) yang harusnya sinkron tapi cuma satu diupdate** adalah pola "audit blind spot" yang sudah berulang di project ini. Kalau kamu menemukan pola serupa (logic yang di-copy-paste ke tempat lain tapi tidak ikut di-update saat aslinya diperbaiki), laporkan sebagai temuan tambahan.
3. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
4. **Kalau kamu menemukan file yang isinya BEDA dari yang dikutip plan (baseline sudah berubah) — STOP dan laporkan ke user**, jangan menebak-nebak menyesuaikan sendiri secara diam-diam.
5. **JANGAN jalankan lebih dari satu proses `php artisan test`/`migrate:fresh` bersamaan.**
6. **Klaim hasil test di handoff log HARUS bisa ditelusuri (command + angka pasti)**, jangan asumsikan atau ekstrapolasi.
7. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan — STOP dan laporkan ke user, JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.** Sub-project sebelumnya punya kasus test yang "dilemahkan" (skenario pemicu bug dihapus) supaya lolos alih-alih bug-nya diperbaiki — itu HARUS dihindari. Kalau sebuah test gagal karena skenario yang memang seharusnya berhasil, perbaiki KODE-nya, JANGAN lemahkan test-nya.

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Definisi selesai

- Task 1: `Role::save()` melempar exception untuk perubahan `name` pada role protected, 4 test baru PASS.
- Task 2: `RoleController::update()` tidak memproses `name` untuk role protected (tidak 500, redirect/200 bersih), 2 test baru + seluruh `RoleBuilderTest.php` PASS.
- Task 3: `scopeRank('platform') === 4`, validasi `store()`/`update()` menerima `platform`, 2 test baru PASS.
- Task 4: opsi "Platform" di dropdown create/edit HANYA muncul untuk actor platform-scope, 2 test baru PASS.
- Task 5: input nama di edit.blade.php `:disabled` untuk role protected, 1 test render PASS.
- Task 6: `$totalPlatform`/`$totalDiriSendiri` terhitung benar, eager-load permission terbatas aktif, 1 test baru PASS.
- Task 7: 5 chip scope + 5 stat card tampil, tidak ada sisa `<select filters.scope>`.
- Task 8: nama Title Case, link Users ke Pengguna terfilter, tooltip Permissions — 3 test baru PASS.
- Task 9: live search matriks bekerja (client-side), blok edukatif scope level tampil, test regresi `RoleBuilderTest.php`/`PermissionAuditTest.php`/`RoleFormAuditBannerTest.php` semua PASS.
- Task 10: checklist manual browser dijalankan, hasil per-poin dicatat (bukan diklaim tanpa bukti).
- Task 11: grep verifikasi kosong, full suite dijalankan SETELAH izin eksplisit user dan hasilnya hijau (atau kegagalan yang ada terbukti pre-existing/tidak terkait, dengan bukti), handoff log tertulis dengan bukti verifikasi yang bisa ditelusuri (commit hash per task, angka test pasti, hasil checklist manual per poin).
