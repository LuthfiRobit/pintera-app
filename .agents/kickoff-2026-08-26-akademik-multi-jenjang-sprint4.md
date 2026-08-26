# Kickoff Prompt — Fondasi Akademik Multi-Jenjang, Sprint 4 (Academic Profile Service)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan sudah direview mendalam (termasuk pemangkasan scope dari outline awal 4 field jadi 2, lewat diskusi eksplisit dengan user). Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu

1. `.agents/specs/2026-08-26-akademik-multi-jenjang-sprint4.md` — spec lengkap (§Keputusan Desain poin 1 — kenapa `AcademicProfile` boleh statis padahal `FaseDefaultMapping` Sprint 3 harus config-driven — WAJIB dibaca supaya tidak salah menerapkan prinsip Sprint 3 di sini).
2. `.agents/plans/2026-08-26-akademik-multi-jenjang-sprint4.md` — plan implementasi (2 task, kode lengkap, TDD step-by-step).

## Konteks singkat

- Repo Laravel 12.63.0 multi-tenant (`pintera-app`). Branch kerja: `akademik-v2` (SUDAH ADA — jangan buat branch baru, jangan buat worktree).
- **Tidak ada prasyarat teknis dari Sprint 1-3** — Sprint 4 murni menambah 1 value object baru yang reuse `ModePembelajaran` (existing, sudah ada sebelum Sprint 1). Hanya diurutkan setelah Sprint 3 sesuai roadmap, bukan dependency nyata.
- **Kenapa sub-project ini ada**: outline awal roadmap punya `AcademicProfile` dengan 4 field (`learningMode`, `defaultAssessmentType`, `subjectRequired`, `reportTemplate`). Setelah dicek terhadap kode nyata, 2 field di-drop: `defaultAssessmentType` sudah tergantikan defaulting Sprint 2 yang berbasis `subjekType` (lebih presisi), `subjectRequired` di-drop karena belum ada satupun consumer nyata yang membutuhkannya. Sisa scope Sprint 4 cuma `learningMode` + `reportTemplate`.
- **Cakupan Sprint 4 SAJA** — jangan menambah enum `ReportTemplate`, jangan refactor consumer existing (`GenerateSesiHarianAction` dkk tetap pakai `ModePembelajaran::fromBentukPendidikan()` langsung, TIDAK diganti ke `AcademicProfile`), jangan menyentuh `RaporPdfDataBuilder::templateUntukJenjang()`, jangan membangun `ReportEngine`/`ReportBuilder` (itu semua Sprint 5). Kalau tergoda "sekalian aja biar service ini kelihatan terpakai", STOP — itu eksplisit ditolak di diskusi desain ("jangan menambahkan consumer hanya agar service terpakai").

## Urutan eksekusi

**Task 1 → 2 murni LINEAR.** Task 1 sangat kecil (1 file implementasi + 1 file test, TDD 5 step). Task 2 murni verifikasi (full suite + laporan).

**Kalau kamu punya akses ke skill `superpowers`:**
Boleh eksekusi manual langsung (`superpowers:executing-plans` atau inline) — scope terlalu kecil untuk butuh `subagent-driven-development` (overhead dispatch tidak sepadan untuk 2 task sederhana), TAPI kalau kamu tetap ingin pakai itu, tidak dilarang.

**Kalau tidak punya skill itu:**
Eksekusi manual:
1. Baca task, kerjakan tiap step persis seperti ditulis.
2. Jalankan `php -l <file>` setelah tiap edit PHP, SEBELUM commit.
3. Jalankan test scoped SEBELUM commit — kalau gagal, JANGAN commit, diagnosis dulu.
4. Satu commit untuk Task 1 (Task 2 tidak menghasilkan commit, murni verifikasi + laporan).
5. **JANGAN jalankan full test suite sampai Task 2 Step 1.**

## Peringatan eksplisit dari plan — titik yang HARUS diverifikasi, bukan diasumsikan

1. **Task 1 Step 4** — verifikasi encapsulation (`private` constructor, `final` class, `readonly` property) dilakukan lewat PEMBACAAN SOURCE manual, BUKAN dengan menulis test Pest yang mencoba `new AcademicProfile(...)` dari luar class. Ini keputusan eksplisit di spec §2 (poin catatan #2 dari review user) — jangan menambah test yang tidak diminta di sini walau kelihatannya "lebih thorough".
2. **Task 2 Step 1** — baseline sebelum Sprint 4 adalah **2201 passed, 4 skipped** (state akhir setelah Sprint 3 + fix review-nya). Plan menyebut ekspektasi kira-kira "2221 passed" (baseline + 20 test baru dari Task 1) TAPI itu bukan angka yang boleh diasumsikan benar — laporkan angka NYATA dari output `php artisan test`, jangan menulis ulang angka perkiraan plan sbg hasil aktual.
3. **Jangan buat `enum ReportTemplate`** — `reportTemplate` WAJIB tetap `string` polos di Sprint 4 (Global Constraints plan, sudah ditegaskan 2x baik di spec maupun plan). Ini keputusan eksplisit, bukan kelalaian yang "sebaiknya dirapikan sekalian".

## Pelajaran penting dari Sprint 1-3 (berlaku juga di sini)

1. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
2. **Kalau kamu menemukan file yang isinya BEDA dari yang dikutip plan (baseline sudah berubah) — STOP dan laporkan ke user**, jangan menebak-nebak menyesuaikan sendiri.
3. **JANGAN jalankan lebih dari satu proses `php artisan test`/`migrate:fresh` bersamaan.**
4. **Klaim hasil test di handoff log HARUS bisa ditelusuri (command + angka pasti)** — jalankan `php artisan test` TANPA filter apa pun untuk klaim "full suite hijau", bukan `--filter=Akademik` atau kombinasi manual.
5. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan — STOP dan laporkan ke user, JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.** (Sprint 3 sudah 2x kejadian deviasi yang akhirnya ditemukan lewat review manual — salah satunya kontrak JSON yang menyimpang dari spec tanpa dilaporkan, dan 1 test kritis yang hilang tanpa disebutkan di handoff log.)
6. **Jangan menambah fitur di luar 2 task ini** — termasuk godaan menambah field, enum, atau refactor consumer "karena kelihatan gampang sekalian".

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Definisi selesai

- Task 1: `app/Domains/Akademik/Support/AcademicProfile.php` dibuat sesuai spec §1 (docblock platform-default eksplisit, constructor `private`, class `final`, property `readonly`), `reportTemplate` mengembalikan key abstrak (`paud`/`sd`/`smp-sma`/`smk`) BUKAN path Blade, SLB→`sd` sbg baris eksplisit dgn komentar compatibility, `bentuk_pendidikan` di luar whitelist 9 nilai throw `InvalidArgumentException`. 20 test (4 `it()`/`with()` block) PASS, `php -l` bersih, 1 commit.
- Task 2: **full test suite (`php artisan test` tanpa filter) 0 failed**, angka pasti dicatat (bukan "tampak hijau" atau angka perkiraan plan), laporan final ke user berisi angka pasti + commit hash Task 1 + konfirmasi hanya 2 file yang tersentuh (implementasi + test, tidak ada file lain yang di-modify).
