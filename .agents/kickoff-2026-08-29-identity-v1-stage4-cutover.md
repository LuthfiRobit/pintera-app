# Kickoff Prompt — Identity v1: Stage 4-6 (Task 11-27 dari 28)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini — dikerjakan agent eksternal seperti Antigravity yang terintegrasi Laravel Boost + superpowers, bukan sesi Claude Code yang sama).

---

Kamu akan MELANJUTKAN eksekusi sebuah implementation plan 28-task yang SUDAH DITULIS LENGKAP untuk memperkenalkan `persons` sebagai tabel identitas master tunggal di belakang `Guru`, `Karyawan`, `OrangTua`, `Siswa`, dan `CalonMurid`, di app Laravel 12 multi-tenant "Pintera" (`pintera-app`). Task 1-10 (Stage 1: Schema, Stage 2: Backfill+Verifikasi, Stage 3: PersonService lengkap) SUDAH SELESAI, direview, dan di-commit oleh sesi sebelumnya. Kamu melanjutkan dari **Task 11 sampai Task 27** (Stage 4: Code Cutover, Stage 5: Query-builder Cutover, Stage 6: Constraint Tightening + Full Suite). **Task 28 (drop kolom lama) EKSPLISIT DI LUAR SCOPE kickoff ini — JANGAN dikerjakan, itu dijadwalkan terpisah setelah minimal 1 siklus rilis produksi penuh.**

Kamu tidak perlu brainstorming ulang, tidak perlu menulis spec baru, tidak perlu menulis ulang plan — semua keputusan desain sudah final dan disetujui lewat proses review berlapis. Tugasmu murni EKSEKUSI.

## Yang harus kamu baca dulu, URUTAN INI

1. `.agents/logs/2026-08-29-identity-v1-person-master-entity-tasks-1-10.md` — handoff log Task 1-10. WAJIB dibaca duluan — isinya daftar bug nyata yang ditemukan di HAMPIR SETIAP task sebelumnya (6 dari 10 task), semua sudah diperbaiki dan didokumentasikan di file plan. Pola kewaspadaan yang sama HARUS diteruskan, bukan diasumsikan "sudah aman sekarang".
2. `.agents/specs/2026-08-29-identity-v1-person-master-entity.md` — spec lengkap (audit, prinsip desain terkunci, DDL final, kebijakan merge, non-goals). §2 (7 prinsip desain terkunci) WAJIB dipahami sebelum menyentuh kode apa pun.
3. `.agents/plans/2026-08-29-identity-v1-person-master-entity.md` — plan implementasi 28-task. **Baca SELURUH file, bukan cuma Task 11-27** — Task 1-10 berisi kode `Person` model, `YayasanScope`, `CreatePersonAction`, dll yang jadi dependency langsung Task 11-27. Perhatikan KHUSUS setiap teks **"Correction found during implementation"** yang muncul di Task 2, 3, 6, 9 — itu bukan catatan sejarah, itu BAGIAN DARI DESAIN YANG SUDAH BENAR yang harus kamu pakai apa adanya (kode di plan SUDAH versi yang diperbaiki, bukan versi awal yang buggy).

## Kenapa kewaspadaan ekstra dibutuhkan — pola bug dari Task 1-10

Baca `.agents/logs/2026-08-29-identity-v1-person-master-entity-tasks-1-10.md` §2 untuk detail lengkap, tapi ringkasan pola yang WAJIB kamu bawa ke Task 11-27:

1. **Jangan percaya prosa plan/brief sebagai kebenaran mutlak soal schema.** Baca migration file SUNGGUHAN (`database/migrations/2026_*_create_{guru,karyawan,orang_tua,siswa,calon_murid}_table.php` DAN `database/migrations/2026_08_29_000002_add_person_id_and_relax_identity_columns.php`) sebelum menulis kode apa pun yang menyentuh kolom-kolom itu. Task 3 sebelumnya kehilangan 4+ kolom NOT NULL yang seharusnya di-relax karena hanya mengandalkan daftar di prosa, bukan cross-check ke migration sungguhan.
2. **`Blueprint::change()` merekonstruksi SELURUH definisi kolom dari yang ditulis di panggilan itu** — kalau kolom asalnya `enum(...)` atau punya `->default(...)`, itu HARUS ditulis ulang persis di setiap panggilan `->change()`, atau akan hilang senyap. Task 3 kehilangan constraint enum `jenis_kelamin` dan default `kewarganegaraan` karena ini.
3. **`Person`'s `YayasanScope` (BUKAN `TenantScope` — baca bedanya di `app/Models/Scopes/YayasanScope.php` vs `app/Models/Scopes/TenantScope.php`) memfilter berdasarkan yayasan_id AKTOR YANG LOGIN, independen dari filter eksplisit apa pun yang sudah ada di query.** Kalau kamu menulis query terhadap `Person` yang punya target yayasan_id eksplisit BERBEDA dari yayasan aktor yang login (jarang tapi mungkin, misal proses backfill/admin lintas-tenant), kamu HARUS `Person::withoutGlobalScope(\App\Models\Scopes\YayasanScope::class)` di query itu — lihat `CreatePersonAction` (Task 6) sebagai contoh pola yang benar.
4. **Query builder (`DB::table()`) BERBEDA dari Eloquent (`Model::query()`)** — yang pertama TIDAK PERNAH kena global scope apa pun, yang kedua SELALU kena kecuali di-`withoutGlobalScope`. Jangan asumsikan salah satu berperilaku seperti yang lain.
5. **Sebelum menandai task selesai, jalankan skenario yang MENURUTMU sudah pasti benar** (misal: apakah collision NIK betul-betul ke-detect, apakah unique constraint betul-betul tidak dilanggar) — bukan cuma percaya test hijau kalau test-nya sendiri berpotensi tidak membuktikan klaimnya (lihat Task 7's coverage gap sebagai contoh nyata: 2 test lulus tapi TIDAK PERNAH membuktikan tenant-scoping bekerja, sampai ditambah test cross-tenant secara eksplisit).

## Radius perubahan Task 11-27 (ringkasan per task, DETAIL LENGKAP+KODE ada di file plan)

- **Task 11**: `app/Models/User.php` — 4 relasi (`guru()`, `karyawan()`, `orangTua()`, `siswa()`) diubah dari `hasOne` jadi `hasOneThrough(RoleModel::class, Person::class, 'user_id', 'person_id', 'id', 'id')`. **RISIKO REGRESI TERTINGGI DI SELURUH PLAN INI** — ada ~87 file existing yang memanggil `$user->guru`/`$user->karyawan`/dst dan TIDAK BOLEH ada satupun yang berubah perilakunya. WAJIB jalankan `tests/Feature/BottomNavTest.php` (test existing, BUKAN test baru) setelah perubahan ini — itu satu-satunya jaring pengaman nyata untuk regresi relasi ini yang sudah ada di codebase.
- **Task 12**: accessor shim (`getNamaAttribute()`, dst) di 5 model role, proxy ke `$this->person->...`. Field mapping HARUS lengkap — cross-check tiap kolom yang di-drop di Task 3 (baca ulang migration itu) punya accessor pengganti, jangan sampai ada yang lolos seperti bug Task 4 (field hilang senyap).
- **Task 13**: `PersonTenantScope` BARU untuk `OrangTua` — menutup bug keamanan NYATA yang ditemukan saat audit (yayasan_super_admin bocor lihat SEMUA orang_tua lintas tenant). WAJIB ADDITIF, bukan pengganti `authorizeLembaga()` yang sudah ada di `OrangTuaController`.
- **Task 14-20**: cutover 7 controller/service (`GuruController`, `KaryawanController`+`AkunKaryawanGenerator`, `OrangTuaController`+`AkunOrangTuaGenerator`, `SiswaController`+`AkunSiswaGenerator`, `SiswaImportController`, `PendaftaranSiswaController`, `ReviewSubmitController`) supaya semua create/update identitas lewat `PersonService`, bukan langsung ke role table. Task 19 (`PendaftaranSiswaController`) PALING PENTING secara arsitektur — ini titik yang mengubah CalonMurid→Siswa dari "copy field" jadi "link ke person_id yang sama", bukti utama tujuan seluruh migrasi ini.
- **Task 21**: verifikasi (bukan kode baru) bahwa `nik_hash` cuma dihitung di `Person`, tidak lagi di 3 model role lama.
- **Task 22-26**: rewrite ~30 titik query-builder (search/orderBy) di ~20 file dari `where('nama', ...)` langsung ke `whereHas('person', ...)` — dikelompokkan per model (Guru/Karyawan/OrangTua/Siswa/CalonMurid). Task 24 (OrangTua) punya SATU perubahan perilaku yang DISENGAJA (bukan bug): pencarian NIK plaintext jadi exact-match-only via hash karena `nik` sekarang encrypted di `Person` — dokumentasikan ini eksplisit kalau muncul, jangan dianggap regresi yang harus "diperbaiki".
- **Task 27**: migration `person_id` jadi NOT NULL + FK constraint, GATE dengan `php artisan identity:verify-backfill` (harus exit 0 dulu), lalu **FULL TEST SUITE** (`php artisan test --compact` tanpa filter) — SATU-SATUNYA titik di seluruh plan ini yang boleh menjalankan full suite tanpa filter.

## Proyek ini pakai Laravel Boost — WAJIB dipakai selama eksekusi

- Kalau ragu skema kolom/tabel — pakai `database-schema` MCP tool, jangan menebak atau cuma baca migration file (migration bisa berurutan banyak, schema langsung lebih pasti).
- Kalau ragu route name — pakai `php artisan route:list`.
- Kalau ragu perilaku Laravel 12 versi-spesifik (misal `Blueprint::change()`, `hasOneThrough` parameter order) — pakai `search-docs`.
- **JANGAN buat script verifikasi terpisah/tinker untuk hal yang test-nya sudah cukup** — tapi UNTUK task berisiko tinggi seperti Task 11, verifikasi manual singkat via tinker (misal load 1 User asli, panggil `$user->guru`, bandingkan dengan hasil query manual) sebelum percaya test otomatis SAH dilakukan sebagai pengecekan tambahan, bukan pengganti test.

## Project Rules gate — WAJIB dibaca sebelum edit apa pun

Buka `.ai/rules/index.md`, baca rule file yang glob-nya mencakup path yang disentuh:

- `.ai/rules/domains-models.md` + `.ai/rules/models.md` (Task 11, 12, 13 — `app/Models/**`, `app/Domains/*/Models/**`)
- `.ai/rules/actions.md` (Task 14-20 memanggil Action classes yang sudah ada)
- `.ai/rules/controllers.md` (Task 14-20 — `app/Http/Controllers/**`)
- `.ai/rules/migrations.md` (Task 27)
- `.ai/rules/tests.md`

Kalau ada isi rule yang tampak bertentangan dengan instruksi plan, STOP dan laporkan konfliknya ke user — jangan diam-diam pilih salah satu.

## Konteks singkat

- Repo Laravel 12 multi-tenant (`pintera-app`), Pest v4, MySQL. Branch kerja: `identity-v1` (SUDAH ADA, HEAD di commit `0119fbcc` — jangan buat branch/worktree baru kecuali diminta eksplisit).
- Database dev SUDAH dimigrasikan sampai Task 3's migration, sudah diverifikasi konsisten dengan migration file yang ter-commit (lihat handoff log §4). `identity:backfill-persons` (Task 4) BELUM PERNAH dijalankan terhadap data asli — itu KEPUTUSAN SENGAJA (backfill data-asli baru jalan setelah Task 11-21 code-cutover selesai, sesuai urutan migrasi di spec §8), JANGAN jalankan backfill lebih awal dari itu kecuali user memintanya eksplisit.
- **Urutan task WAJIB LINEAR sesuai penomoran plan**: Task 11 → 12 → ... → 27. Task 12 (accessor shim) bergantung Task 11 tidak merusak apa pun. Task 13 independen tapi logis setelah 11-12. Task 14-20 masing-masing independen satu sama lain TAPI semuanya bergantung Task 6-10 (PersonService, sudah selesai) dan idealnya Task 11-13 (relasi+accessor+scope) sudah stabil dulu. Task 21 murni verifikasi setelah 12. Task 22-26 bergantung Task 12 (accessor shim harus ada dulu supaya kode lain yang BELUM di-cutover di task ini tetap jalan lewat shim). Task 27 HARUS PALING TERAKHIR.
- **Full suite HANYA di Task 27** — task lain cukup test scoped (`--filter=NamaTest`) sesuai instruksi tiap task di file plan.

## Kalau kamu punya akses ke skill `superpowers`

Plan ini besar (17 task tersisa, puluhan file produksi, ~30 titik query-builder) — **`subagent-driven-development` SANGAT direkomendasikan**, ikuti proses "1 implementer subagent per task → review spec-compliance + code-quality → fix loop kalau ada temuan → lanjut task berikutnya" persis seperti yang dilakukan sesi sebelumnya untuk Task 1-10 (lihat pola di handoff log §1-2 sebagai referensi konkret cara kerja ini berjalan). Buat ledger progress baru di `.superpowers/sdd/progress.md` (append ke yang sudah ada dari sesi sebelumnya kalau file masih ada, jangan timpa) untuk melacak task mana yang sudah selesai — ini KRUSIAL untuk plan sepanjang ini, kompaksi/interupsi harus bisa resume tanpa mengulang task yang sudah selesai.

## Kalau tidak punya skill itu sama sekali

Eksekusi manual:
1. Baca task di plan, kerjakan tiap step persis seperti ditulis — kode di plan sudah lengkap (kecuali beberapa tempat yang eksplisit ditandai "baca controller asli dulu untuk konfirmasi field/route" di Task 18-20 — itu memang butuh 1 langkah baca sebelum menulis test, bukan placeholder yang terlewat).
2. **WAJIB baca ulang baseline file SEBELUM setiap edit** — kalau baseline yang kamu baca TIDAK COCOK persis dengan kutipan di plan (karena Task 11-13 mungkin sudah mengubah file yang sama), STOP dan sesuaikan dulu ke state sungguhan.
3. Jalankan `vendor/bin/pint --dirty --format agent` setelah tiap perubahan file PHP, SEBELUM commit.
4. Jalankan test scoped SEBELUM commit — kalau gagal, JANGAN commit, diagnosis dulu (pakai `superpowers:systematic-debugging` kalau tersedia).
5. 1 commit per task minimal (task besar seperti 14-20 boleh 1 commit tapi WAJIB mencakup implementasi + test dalam commit yang sama).
6. **Full suite HANYA di Task 27 Step 5** — jangan jalankan di task lain.

## Pelajaran penting dari sesi-sesi sebelumnya (berlaku juga di sini)

1. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
2. **JANGAN jalankan lebih dari satu proses `php artisan test` bersamaan.**
3. **Klaim hasil test di handoff log HARUS bisa ditelusuri (command + angka pasti).**
4. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan — dokumentasikan alasannya LANGSUNG di file plan** (ikuti pola "Correction found during implementation" yang sudah ada di Task 2/3/6/9), JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.
5. **Jangan overreach**: Task 28 (drop kolom lama) TIDAK BOLEH disentuh sama sekali di kickoff ini, walau "kelihatannya tinggal sedikit lagi" setelah Task 27 selesai — itu keputusan user yang eksplisit untuk ditunda 1 siklus rilis penuh.
6. **Setiap kali menemukan bug/gap yang mirip pola di §2 handoff log (kolom hilang, scope interference, unique constraint race) — treat sebagai serius, bukan nitpick.** Track record Task 1-10 menunjukkan pola ini BUKAN kebetulan, ini karakteristik nyata dari besarnya radius refactor ini.

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Cek dulu via Boost (`database-schema`, `route:list`, `search-docs`) kalau itu pertanyaan teknis yang bisa dijawab tool, baru laporkan ke user kalau tetap ambigu.

## Wajib di akhir: Handoff Log (Stage 7)

Setelah Task 27 selesai (atau kalau eksekusi berhenti di tengah karena alasan apa pun), tulis `.agents/logs/2026-08-29-identity-v1-person-master-entity-tasks-11-27.md` mengikuti format yang sama persis dengan `.agents/logs/2026-08-29-identity-v1-person-master-entity-tasks-1-10.md` (bagian: Apa yang Dikerjakan, Keputusan Penting, Daftar Commit, Hasil Verifikasi, Hal yang Masih Perlu Direview). WAJIB sebut angka pasti full suite Task 27 (jumlah passed/failed/skipped, bukan "semua lulus" tanpa angka) dan commit hash range lengkap.

## Definisi selesai

- Task 11: `$user->guru`/`karyawan`/`orangTua`/`siswa` tetap berfungsi identik untuk 87 caller existing (dibuktikan minimal lewat `BottomNavTest.php` tetap hijau) sambil sekarang routing lewat `Person`.
- Task 12: `$guru->nama`, `$siswa->nama_lengkap`, dll tetap mengembalikan nilai benar via `$this->person->...`, mencakup SEMUA kolom yang di-drop di Task 3 tanpa terlewat satupun.
- Task 13: yayasan_super_admin TIDAK LAGI bisa lihat `OrangTua` lintas yayasan lain (bug keamanan tertutup), filter `authorizeLembaga()` existing tetap jalan sebagai lapisan tambahan.
- Task 14-20: SEMUA create/update identitas di 7 controller/service ini lewat `PersonService`, NOL penulisan langsung ke kolom identitas role table.
- Task 21: konfirmasi `nik_hash` cuma dihitung 1 tempat (`Person`).
- Task 22-26: SEMUA ~30 titik query-builder yang dienumerasi di spec §5.2 pindah ke `whereHas('person', ...)`/join, dibuktikan test scoped per model tetap lulus.
- Task 27: `identity:verify-backfill` exit 0, migration NOT NULL+FK berhasil, **full test suite 0 failed** dengan angka pasti tercatat di handoff log.
