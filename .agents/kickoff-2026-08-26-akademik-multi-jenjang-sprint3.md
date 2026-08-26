# Kickoff Prompt — Fondasi Akademik Multi-Jenjang, Sprint 3 (Curriculum Phase)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan sudah 2x direview mendalam (termasuk 1 putaran koreksi setelah verifikasi struktur routing/authorization nyata di codebase). Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu

1. `.agents/specs/2026-08-26-akademik-multi-jenjang-sprint3.md` — spec lengkap (§2 uniqueness/generated-column, §3 authorization eksplisit krn model TIDAK pakai `TenantScope` — WAJIB dibaca sebelum Task 6).
2. `.agents/plans/2026-08-26-akademik-multi-jenjang-sprint3.md` — plan implementasi (9 task, kode lengkap per task, TDD step-by-step).

## Konteks singkat

- Repo Laravel 12.63.0 multi-tenant (`pintera-app`), MySQL 8.0.30. Branch kerja: `akademik-v2` (SUDAH ADA — jangan buat branch baru, jangan buat worktree).
- **Tidak ada prasyarat teknis dari Sprint 1-2** — Sprint 3 (Fase/Curriculum Phase) tidak menyentuh `subjek_type`/`assessment_type`. Hanya diurutkan setelahnya sesuai roadmap, bukan dependency nyata.
- **Kenapa sub-project ini ada**: `Kelas.tingkat` (string bebas) tidak cukup untuk reasoning kurikulum Merdeka (Fase Fondasi/A-F). Sprint 3 memperkenalkan `Fase` sbg entitas eksplisit, dgn koreksi desain krusial dari user: **mapping default `bentuk_pendidikan`+`tingkat`→`fase` HARUS jadi data yang bisa dikonfigurasi (tabel `fase_default_mapping`), BUKAN business rule hardcoded (`match()`/`if-else`) di service** — karena kebijakan kurikulum bisa berubah tanpa deployment.
- **Cakupan Sprint 3 SAJA** — jangan mengerjakan curriculum designer, curriculum versioning, mapping CP/TP, dimensi "pilih kurikulum" (K13/KMA-450 Kemenag), auto-backfill `fase_id` massal ke Kelas existing, atau konsolidasi `ElemenCp` (technical debt `TD-AKADEMIK-001`, dicatat di `PETA_PENGEMBANGAN.md`, TIDAK bagian sprint ini). Kalau tergoda "sekalian aja", STOP — semua itu sudah eksplisit jadi Non-Goal di §7 spec.

## Koreksi penting yang sudah dilakukan sebelum plan ini ditulis (jangan diulang kesalahannya)

Draft spec pertama SALAH ASUMSI bahwa project ini punya `routes/admin/*` (platform) vs `routes/lembaga/*` (institution) yang terpisah. **Setelah verifikasi langsung ke codebase, ternyata SEMUA route (termasuk yang platform-wide dan yang institution-scoped) hidup dalam SATU grup `routes/admin.php` — pemisahan platform vs institution dilakukan lewat permission string + `widestScopeLevel()` check DI DALAM controller** (pola `Admin\LembagaController::authorizeOwnLembaga()`), bukan lewat namespace/prefix route berbeda. Plan ini SUDAH dikoreksi mengikuti pola nyata ini (Task 6). **Jangan bikin controller/namespace baru yang menduplikasi pemisahan platform/institution lewat routing — ikuti pola `authorizeMappingScope()` yang sudah ditulis di plan.**

Path Kelas juga sudah diverifikasi langsung: `App\Http\Controllers\Admin\KelasController` + `resources/views/admin/kelas/_form.blade.php` (BUKAN `Lembaga\Akademik\KelasController` atau `portals/lembaga/akademik/kelas/` seperti dugaan draft awal).

## Urutan eksekusi

**Task 1 → 2 → 3 → 4 murni LINEAR** (skema dasar → resolver). **Task 5 punya kekhususan urutan** (baca poin di bawah). **Task 6 → 7 → 8 → 9 LINEAR** setelah Task 5.

**PERHATIAN KHUSUS Task 5**: baris `RoleSeeder`/`DatabaseSeeder` ditulis di Task 5 Step 1-2, TAPI test-nya (Step 3) SENGAJA BELUM dijalankan sampai Task 6 (controller) selesai — karena `php artisan permissions:sync` (dijalankan otomatis di `DatabaseSeeder`) butuh string `fase-mapping.*` benar-benar dipanggil lewat `$this->authorize(...)` di kode controller dulu, baru bisa auto-create baris `Permission`-nya. Jangan jalankan Task 5 Step 3 sebelum Task 6 Step 3 selesai — plan sudah menandai ini eksplisit di kedua task.

**Kalau kamu punya akses ke skill `superpowers`:**
Gunakan `superpowers:subagent-driven-development` — fresh subagent per task, task review, ledger progress di `.superpowers/akademik-multi-jenjang-sprint3/progress.md`. Dispatch berurutan (bukan paralel). Kalau memilih ini, tetap ikuti urutan khusus Task 5 di atas — jangan biarkan subagent Task 5 menjalankan Step 3-nya sendiri sebelum subagent Task 6 commit.

**Kalau tidak punya skill itu:**
Eksekusi manual task-by-task (Task 1 → 9), pola sama persis dengan kickoff Sprint 1/2:
1. Baca task, kerjakan tiap step persis seperti ditulis.
2. WAJIB baca isi file existing dulu sebelum menimpa/mengedit (terutama `RoleSeeder.php`, `DatabaseSeeder.php`, `routes/admin/akademik-master.php`, `Admin\KelasController.php`, `admin/kelas/_form.blade.php` — semua di-modify, bukan di-create baru).
3. Jalankan `php -l <file>` setelah tiap edit PHP, SEBELUM commit.
4. Jalankan test scoped SEBELUM commit — kalau gagal, JANGAN commit, diagnosis dulu.
5. Satu commit per task (kecuali Task 5, yang commit-nya ditunda ke setelah Task 6 — lihat Step 4 Task 5 di plan).
6. **JANGAN jalankan full test suite sampai Task 9.**

## Peringatan eksplisit dari plan — titik yang HARUS diverifikasi, bukan diasumsikan

1. **Task 2 Step 1** — migration pakai `DB::statement()` raw SQL untuk generated column (`GENERATED ALWAYS AS ... STORED`) karena fluent `storedAs()` Laravel tidak konsisten across versi utk ekspresi `COALESCE`. Verifikasi migration benar-benar jalan tanpa error SQL syntax di MySQL 8.0.30 environment ini sebelum lanjut ke Step 3 (test uniqueness).
2. **Task 4 Step 2** — sintaks `orderByRaw('tingkat = ? DESC', [$tingkat])` dgn parameter binding WAJIB diverifikasi benar-benar menghasilkan urutan yang benar (test Step 1 akan membuktikan, tapi kalau ada 1-2 test precedence yang gagal dgn urutan tidak terduga, cek dulu apakah `orderByRaw` binding-nya benar sebelum mengubah pendekatan).
3. **Task 6 Step 3** — daftar role yang diberi `fase-mapping.*` di §3 spec HANYA menyebut `operator_akademik` scr eksplisit (yg lain "diverifikasi implementer dari RoleSeeder saat ini") — plan HANYA menambah ke `operator_akademik` (mengikuti persis pola `kelas.*` yang JUGA hanya diberikan ke `operator_akademik`, bukan role lain). **Jangan menambah role lain yang tidak eksplisit di plan tanpa melaporkan ke user dulu.**
4. **Task 8 Step 4** — komponen Blade `x-text-input` diasumsikan meneruskan atribut Alpine (`x-model`, `x-on:change`) ke elemen `<input>` di dalamnya. Plan sudah menandai ini sbg ketidakpastian jujur — **WAJIB verifikasi manual lewat browser (Step 5 Task 8), bukan cuma percaya test Pest** (Pest tidak menjalankan Alpine/JS).
5. **Task 9 Step 3** — migrasi dev database (Laragon/MySQL nyata) WAJIB dilakukan, bukan cuma test database. Jangan lupa 3 command tambahan (`db:seed --class=FaseSeeder`, `db:seed --class=FaseDefaultMappingSeeder`, `permissions:sync`) — bukan cuma `php artisan migrate` polos.

## Pelajaran penting dari Sprint 1-2 (berlaku juga di sini)

1. **Migration tidak boleh bergantung pada Eloquent model** — Task 1-3 semuanya `Schema::create`/`Schema::table` murni, aman.
2. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
3. **Kalau kamu menemukan file yang isinya BEDA dari yang dikutip plan (baseline sudah berubah, mis. `RoleSeeder.php` block `operator_akademik` sudah pindah baris) — STOP dan laporkan ke user**, jangan menebak-nebak menyesuaikan sendiri.
4. **JANGAN jalankan lebih dari satu proses `php artisan test`/`migrate:fresh` bersamaan** — pernah menyebabkan MySQL deadlock palsu yang terlihat seperti bug kode padahal cuma race antar proses test.
5. **Klaim hasil test di handoff log HARUS bisa ditelusuri (command + angka pasti)** — jalankan `php artisan test` TANPA filter apa pun untuk klaim "full suite hijau", bukan `--filter=Akademik` atau kombinasi manual.
6. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan — STOP dan laporkan ke user, JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.**
7. **Jangan menambah fitur di luar 9 task ini** — termasuk godaan "sekalian" membangun dimensi kurikulum (K13/Kemenag) krn "tabelnya sudah ada, tinggal tambah kolom". Itu Non-Goal eksplisit §7 spec.

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Definisi selesai

- Task 1: tabel `fase` + model + `FaseSeeder` (7 baris, idempotent), test PASS.
- Task 2: tabel `fase_default_mapping` dgn unique constraint generated-column terbukti mencegah duplikat dlm scope yang sama (5 skenario uniqueness) + model + `FaseDefaultMappingSeeder` (17 baris platform, idempotent, SLB sengaja tidak ada), test PASS.
- Task 3: `kelas.fase_id` nullable FK + relasi `Kelas::fase()`, terbukti immutable terhadap perubahan data `Fase` yang dirujuknya, test PASS.
- Task 4: `FaseDefaultResolver::resolve()` terbukti benar utk 6 skenario precedence (termasuk lembaga catch-all vs platform exact-match) TANPA satu pun `if`/`match` berbasis `$bentukPendidikan`/`$tingkat` di dalamnya, test PASS.
- Task 5: permission `fase-mapping.{view,create,edit,delete}` ter-assign ke `operator_akademik` dan otomatis ke `yayasan_super_admin`, terverifikasi lewat `permissions:sync` (test dijalankan SETELAH Task 6), test PASS.
- Task 6: `Admin\FaseDefaultMappingController` CRUD lengkap, tenant-isolation terbukti (lembaga A tidak bisa sentuh mapping lembaga B, tidak bisa bikin mapping platform; yayasan/platform scope bisa lintas lembaga), uniqueness konflik menghasilkan pesan error jelas bukan crash, 9 test PASS.
- Task 7: endpoint `GET admin/kelas/fase-suggestion` read-only, terbukti mengabaikan `bentuk_pendidikan`/`lembaga_id` dari request (selalu pakai punya user login), 3 test PASS.
- Task 8: form Kelas terima `fase_id` di create/update, admin override manual terbukti tidak ditimpa suggestion, **immutability end-to-end terbukti** (ubah mapping tidak mengubah Kelas lama, Kelas baru ikut mapping baru), 5 test PASS + verifikasi manual browser (Alpine suggestion fetch benar-benar bekerja).
- Task 9: **full test suite (`php artisan test` tanpa filter) 0 failed** (angka pasti dicatat, bukan "tampak hijau"), dev database (Laragon/MySQL nyata) sudah dimigrasi + diseed + `permissions:sync`, handoff log dgn bukti verifikasi yang bisa ditelusuri (commit hash per task, catatan penyelesaian tiap peringatan di atas).
