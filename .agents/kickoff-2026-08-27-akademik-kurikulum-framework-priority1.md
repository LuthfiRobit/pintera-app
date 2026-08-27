# Kickoff Prompt — Kurikulum Framework Priority 1 (`KurikulumFramework` + `KurikulumAssignment`)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan sudah direview mendalam (brainstorming interaktif + spec self-review). Kamu tidak perlu brainstorming ulang, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu

1. `.agents/specs/2026-08-27-akademik-kurikulum-framework-priority1.md` — spec lengkap (§2 WAJIB dibaca — berisi 6 keputusan desain kunci yang tidak boleh disimpangi: enum-only bukan tabel, cakupan enum cuma K13+Merdeka, snapshot benar-benar terkunci beda dari `fase_id`, `null`=legacy bukan valid-state baru, auto-resolve tanpa override manual, edit assignment tidak retroaktif).
2. `.agents/plans/2026-08-27-akademik-kurikulum-framework-priority1.md` — plan implementasi (6 task, kode lengkap, TDD step-by-step, sudah lolos self-review spec-coverage + placeholder-scan + type-consistency).

## Proyek ini pakai Laravel Boost — WAJIB dipakai selama eksekusi

Project ini punya Laravel Boost MCP server terpasang (`composer.json` sudah punya `laravel/boost`). Ikuti aturan project (`CLAUDE.md` root) — Boost tools BUKAN opsional, mereka pengganti wajib utk pendekatan manual berikut:

- **Sebelum mengandalkan API Laravel/package apa pun yang version-sensitive** (contoh konkret di plan ini: enum casting via `casts()` method, `Rule::enum()`, kolom virtual/generated `virtualAs()` di migration, `withValidator()` hook di FormRequest) — panggil `search-docs` dulu utk konfirmasi syntax yang benar utk versi Laravel 12.63.0 yang terpasang di project ini. JANGAN asumsikan syntax dari versi Laravel lain.
- **Untuk mengecek struktur tabel** (`kelas`, `tahun_ajaran`, `lembaga`, `fase_default_mapping` sbg pembanding pola) — pakai `database-schema`, jangan buka file migration satu-satu secara manual kalau Boost bisa jawab langsung.
- **Untuk verifikasi data selama debugging** (mis. mengecek apakah `KurikulumAssignment` tersimpan benar, mengecek nilai `Kelas.kurikulum` setelah test) — pakai `database-query` (read-only), BUKAN `php artisan tinker` manual, dan BUKAN membuat script verifikasi terpisah (aturan project: "Jangan buat verifikasi script atau tinker kalau test sudah mencakup fungsionalitas itu — unit dan feature test lebih penting").
- **Kalau ada test gagal dan errornya tidak jelas dari output `php artisan test`** — pakai `last-error`/`read-log-entries` sebelum menebak-nebak.
- **Kalau kamu menemukan pola/keputusan baru yang layak jadi aturan permanen project** (jarang terjadi di plan ini krn semua sudah mengikuti pola existing `FaseDefaultMapping`/`FaseDefaultResolver`, tapi kalau terjadi) — pakai `record-rule` utk menulis ke `.ai/rules/`, JANGAN simpan di memori/catatan pribadi.
- **JANGAN pakai `search-docs` utk hal yang copy-only atau tidak bergantung pada versi package** — plan ini sebagian besar transkripsi kode yang sudah lengkap di plan, jadi `search-docs` hanya perlu dipanggil di titik-titik version-sensitive di atas, bukan di setiap step.

## Project Rules gate — WAJIB dibaca sebelum edit apa pun

Sebelum kamu masuk Task 1, buka `.ai/rules/index.md`, lalu baca SEMUA rule file yang glob-nya mencakup path yang akan disentuh plan ini:

- `.ai/rules/domains-enums.md`, `.ai/rules/enums.md` (Task 1: enum baru di domain)
- `.ai/rules/domains-models.md`, `.ai/rules/models.md` (Task 2 & 4: model baru + modifikasi `app/Models/Kelas.php`)
- `.ai/rules/migrations.md` (Task 2 & 4: 2 migration baru — perhatikan aturan `down()` yang sudah disepakati: migration struktur wajib reverse bersih, `dropIfExists`/`dropColumn`)
- `.ai/rules/services.md`, `.ai/rules/app-services.md` (Task 3: resolver baru)
- `.ai/rules/actions.md` (Task 4 & 5: Action classes)
- `.ai/rules/controllers.md`, `.ai/rules/requests.md`, `.ai/rules/routes.md` (Task 5: controller+FormRequest+routes baru)
- `.ai/rules/views.md` (Task 5: 4 view Blade baru)
- `.ai/rules/tests.md` (semua task: konvensi Pest project ini)

Rule-rule ini HASIL AUDIT LANGSUNG dari kode project ini (bukan opini generik) — kalau ada isi rule yang tampak bertentangan dgn instruksi plan, STOP dan laporkan konfliknya ke user, jangan diam-diam pilih salah satu.

## Konteks singkat — PENTING, baca sebelum eksekusi

- Repo Laravel 12.63.0 multi-tenant (`pintera-app`), Pest v4, MySQL 8.0.30. Branch kerja: `akademik-v2` (SUDAH ADA — jangan buat branch baru, jangan buat worktree, kecuali kamu memang menjalankan lewat mekanisme worktree eksplisit yang diminta user).
- **Ini fondasi baru, bukan bug fix** — `KurikulumFramework`/`KurikulumAssignment` adalah entitas yang SAMA SEKALI BELUM ADA di kode. Tidak ada perilaku lama yang "diperbaiki", murni penambahan.
- **Task 4 punya blast radius ke 4 file test lama** (`CreateKelasActionTest.php`, `KelasFaseAssignmentTest.php`, `KelasCrudTest.php`, `KelasPolaJamTest.php`) — plan SUDAH menjelaskan persis kenapa dan bagaimana meretrofitnya (seed 1 baris `KurikulumAssignment` per test/helper). Ini DISENGAJA, bukan regresi tak terduga — jangan kaget dan jangan mengubah pendekatan retrofit yang sudah ditulis di plan.
- **`Kelas.kurikulum` benar-benar terkunci** — beda perlakuan dari `Kelas.fase_id` (yang ternyata cuma default-saran yang masih bisa diedit manual). JANGAN tergoda menambahkan field `kurikulum` ke form edit Kelas atau ke `KelasData`/`UpdateKelasAction` dengan alasan apa pun "supaya konsisten dgn fase_id" — itu justru pelanggaran spec §2 poin 3.
- **`BentukPendidikan` enum baru HANYA dipakai di fitur ini** — JANGAN retrofit 4 lokasi lama yang masih hardcode (`StoreFaseDefaultMappingRequest`, `LembagaController`, `AcademicProfile`, `RaporPdfDataBuilder`) meskipun tergoda "sekalian beresin". Itu tercatat sbg TD-AKADEMIK-003 terpisah di Task 6 — di luar scope plan ini.
- **`KurikulumAssignmentResolver::resolve()` HARUS throw, bukan return null** — ini beda sengaja dari `FaseDefaultResolver` (yang return `?Fase`). Jangan "menyamakan" gaya keduanya.

## Urutan eksekusi

**Task 1 → 2 → 3 → 4 → 5 → 6 murni LINEAR** (masing-masing task punya dependency eksplisit ke task sebelumnya, lihat blok "Interfaces" di tiap task pada plan).

**Kalau kamu punya akses ke skill `superpowers`:**
Plan ini cukup besar (6 task, ~25 file tersentuh, blast radius test lintas file) — **REKOMENDASI KUAT pakai `superpowers:subagent-driven-development`** (fresh subagent per task + review dua-tahap), bukan eksekusi manual linear. Kalau tidak tersedia, pakai `superpowers:executing-plans`.

**Kalau tidak punya skill itu sama sekali:**
Eksekusi manual:
1. Baca task, kerjakan tiap step persis seperti ditulis — termasuk kode lengkap yang sudah disediakan, jangan menulis versi "yang menurutmu lebih baik".
2. **WAJIB baca file existing yang akan diubah SEBELUM edit** (`app/Models/Kelas.php`, `app/Domains/Akademik/Actions/Kelas/CreateKelasAction.php`, `app/Http/Controllers/Admin/KelasController.php`, 4 file test Task 4, `routes/admin/akademik-master.php`, `database/seeders/PermissionSeeder.php`, `database/seeders/RoleSeeder.php`) dan bandingkan dgn kutipan "Files: Modify" di tiap task — kalau baseline beda dari yang plan asumsikan, STOP dan laporkan ke user.
3. Jalankan `php -l <file>` setelah tiap edit PHP, SEBELUM commit.
4. Jalankan test scoped SEBELUM commit — kalau gagal, JANGAN commit, diagnosis dulu (pakai Boost `last-error`/`read-log-entries` kalau errornya tidak jelas).
5. 6 commit total (satu per task, sesuai pesan commit yang sudah ditulis di tiap Step terakhir).
6. **JANGAN jalankan full test suite sampai Task 6 Step 2.**
7. Jalankan `vendor/bin/pint --dirty --format agent` di akhir Task 4 dan Task 5 (sudah tertulis eksplisit di step-nya) — JANGAN `--test`, langsung format.

## Peringatan eksplisit dari plan — titik yang HARUS diverifikasi, bukan diasumsikan

1. **Task 2 Step 3** — migration `kurikulum_assignment` WAJIB pakai trik virtual-column (`lembaga_key`, `tingkat_key`) yang identik polanya dgn `fase_default_mapping` — JANGAN sederhanakan jadi unique constraint biasa, itu akan gagal menangani `NULL` dgn benar di MySQL.
2. **Task 3 Step 3** — precedence resolver dinyatakan lewat `ORDER BY`, BUKAN cabang if/match manual — ikuti persis kode `FaseDefaultResolver` yang jadi acuan.
3. **Task 4 Step 5** — urutan kode di `CreateKelasAction` PENTING: resolusi kurikulum harus terjadi SETELAH abort_if wali_kelas/pola_jam, SEBELUM `Kelas::create()`. Kalau urutan ditukar, beberapa test 404 di Task 4 Step 8 bisa berubah perilaku (lihat catatan di plan Step 8).
4. **Task 4 Step 8-11** — retrofit 4 file test HARUS persis seperti ditulis (1 baris `KurikulumAssignment::create(...)` ditambahkan, bukan mengubah assertion lain). Kalau grep/test menemukan file test LAIN (di luar 4 yang disebut plan) yang ternyata juga gagal krn perubahan ini, STOP dan laporkan ke user — itu tandanya audit blast-radius sebelum plan ini kurang lengkap.
5. **Task 5 Step 3** — validasi silang `tingkat` vs `bentuk_pendidikan` pakai `withValidator()` closure, BUKAN custom Rule class terpisah — ikuti persis kode yang sudah ditulis (project ini tidak punya konvensi `app/Rules/` yang mapan utk kasus cross-field seperti ini).
6. **Task 6 Step 1** — grep akhir HARUS mencakup `.blade.php` juga, bukan cuma `.php`.

## Pelajaran penting dari sprint-sprint akademik sebelumnya (berlaku juga di sini)

1. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
2. **JANGAN jalankan lebih dari satu proses `php artisan test`/`migrate:fresh` bersamaan.**
3. **Klaim hasil test di handoff log HARUS bisa ditelusuri (command + angka pasti)** — jalankan `php artisan test --compact` TANPA filter apa pun di Task 6 utk klaim "full suite hijau".
4. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan — STOP dan laporkan ke user, JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.**
5. **Jangan menambah scope di luar 6 task ini** — termasuk godaan retrofit `BentukPendidikan` ke 4 lokasi lama, atau mulai mengerjakan Prioritas #2-5 roadmap kurikulum krn "sudah kebayang alurnya". Semua itu eksplisit di luar scope (spec §7 Non-Goals).

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Cek dulu via Boost (`search-docs`/`database-schema`/`database-query`) kalau itu pertanyaan teknis yang bisa dijawab tool, baru laporkan ke user kalau tetap ambigu.

## Definisi selesai

- Task 1: `KurikulumFramework` (2 case) dan `BentukPendidikan` (9 case + `validTingkatValues()`) enum lulus semua unit test.
- Task 2: tabel+model `KurikulumAssignment` + exception class, unique constraint terbukti lewat test (4 test PASS).
- Task 3: `KurikulumAssignmentResolver` lulus 7 test (4 level precedence + throw + isolasi tahun_ajaran + isolasi lembaga).
- Task 4: `Kelas.kurikulum` ter-snapshot otomatis saat create, terkunci setelahnya, 4 file test lama sudah diretrofit dan tetap PASS, 5 test baru PASS.
- Task 5: CRUD "Pengaturan Kurikulum" lengkap (6 route, FormRequest+DTO+Action+Controller+4 view), 7 test feature PASS.
- Task 6: **full test suite (`php artisan test --compact` tanpa filter) 0 failed**, angka pasti dicatat, dev database (Laragon/MySQL nyata) sudah dimigrasi via `php artisan migrate`, `PETA_PENGEMBANGAN.md` sudah ditandai selesai + TD-AKADEMIK-003 dicatat, laporan final ke user berisi angka pasti + 6 commit hash.
