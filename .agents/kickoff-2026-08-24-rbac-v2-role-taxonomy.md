# Kickoff Prompt — RBAC v2 Role Taxonomy & Migration Baseline

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan siap jalan. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu

1. `.agents/specs/2026-08-24-rbac-v2-role-taxonomy.md` — spec lengkap (17 role baseline, mapping migrasi §6, invariant pegawai_lembaga/pegawai_yayasan §5.5, SPMB freeze §9, wali_kelas capability-vs-relation §8).
2. `.agents/plans/2026-08-24-rbac-v2-role-taxonomy.md` — plan implementasi (26 task, kode lengkap per task).

## Konteks singkat

- Repo Laravel 12 multi-tenant (`pintera-app`). Branch kerja: `rbac-v2` (SUDAH ADA — jangan buat branch baru, jangan buat worktree).
- **Baseline kode plan ini: commit `f35cecc`.** Kalau isi file yang kamu baca BEDA signifikan dari yang dikutip plan, STOP, laporkan ke user.
- **Kenapa sub-project ini ada**: RBAC lama punya 13 role dengan beberapa masalah taxonomy (nama role generik yang menabrak konsep organisasi vs fungsi, `karyawan_pool`/`karyawan_lembaga` yang namanya membingungkan padahal sebenarnya scope-carrier, tidak ada role platform-level). RBAC v2 menata ulang jadi 17 role baseline dengan pemisahan jelas: role fungsional (apa yang bisa dilakukan) vs role scope-carrier (`pegawai_lembaga`/`pegawai_yayasan`, menentukan `widestScopeLevel()`).
- **Data seeder demo buang-pakai** — `migrate:fresh --seed` adalah verifikasi normal, jalankan sesering perlu. TIDAK ada data produksi yang perlu diselamatkan atau dimigrasikan.
- **HANYA 4 nama role yang berubah**: `karyawan_pool`→`pegawai_yayasan`, `karyawan_lembaga`→`pegawai_lembaga`, `admin_akademik`→`operator_akademik`, `admin_keuangan`→`bendahara_lembaga`. Role lain TIDAK berubah namanya — jangan cari-ganti di luar 4 ini.

## Urutan eksekusi

Task 1 (migration ENUM) WAJIB pertama — semua task lain butuh kolom `scope_level` sudah menerima nilai `platform`. Task 2 (grep audit) WAJIB kedua, sebelum task edit manapun — **plan ini secara eksplisit tidak mempercayai daftar file dari spec §10 tanpa verifikasi ulang**, kamu WAJIB grep ulang sendiri dan bandingkan dengan daftar di Task 2. Task 3 (RoleSeeder rewrite) WAJIB sebelum Task 4-13 (semua consumer bergantung pada role baru sudah terdaftar). Task 14 (checkpoint zero-crash) WAJIB sebelum Task 15-23 (perbaikan test). Task 15-23 independen satu sama lain. Task 24 (test invariant baru) setelah Task 3 dan Task 5 selesai. Task 25-26 di akhir, berurutan.

**Kalau kamu punya akses ke skill `superpowers`:**
Gunakan `superpowers:subagent-driven-development` — fresh subagent per task, task review, ledger progress di `.superpowers/rbac-v2-role-taxonomy/progress.md`.

**Kalau tidak punya skill itu:**
Eksekusi manual task-by-task (Task 1 → 26):
1. Baca task, kerjakan tiap step persis seperti ditulis (kode di plan itu final, kecuali step yang eksplisit bilang "baca dulu, verifikasi" — itu WAJIB kamu jalankan verifikasinya sebelum eksekusi kode yang dikutip).
2. WAJIB baca isi file existing dulu sebelum menimpa/mengedit — pastikan cocok dengan yang dikutip plan.
3. Jalankan `php -l <file>` (syntax check) setelah tiap edit, SEBELUM commit.
4. Jalankan test scoped SEBELUM commit — kalau gagal, JANGAN commit, diagnosis dulu (KECUALI Task 5 yang plan-nya SENGAJA mengizinkan test scoped gagal karena test-nya sendiri baru diupdate di Task 23 — baca step Task 5 dengan teliti).
5. Satu commit per task (task verifikasi-saja seperti Task 4, 6, 14, 25 TIDAK menghasilkan commit — itu normal, sudah ditulis eksplisit di plan).
6. Jangan jalankan full test suite sampai Task 26.
7. Task 26 Step 2 butuh persetujuan user EKSPLISIT sebelum full suite (Step 3).

## Peringatan eksplisit dari plan — beberapa task punya ketidakpastian yang HARUS diverifikasi, bukan diasumsikan

1. **Task 2 (grep audit)** — daftar file yang dikutip di plan adalah hasil grep 24 Agustus 2026. Kalau grep-mu sekarang menemukan file TAMBAHAN yang tidak ada di daftar, atau file di daftar sudah tidak ada lagi, plan ini TIDAK LENGKAP untuk kasus itu — STOP dan laporkan ke user sebelum lanjut, jangan diam-diam menambah/mengurangi task sendiri.
2. **Task 11-12 (EssentialUserSeeder/UserSeeder — tambah baseline `pegawai_lembaga`)** — plan mengasumsikan struktur `$akunLembagaScoped`/`seedStaf()` masih persis seperti yang dikutip. Kalau strukturnya sudah berubah (field lain, urutan lain), JANGAN paksakan diff yang dikutip plan — terapkan INTENSI-nya (assign `pegawai_lembaga` di samping role fungsional untuk semua akun lembaga-affiliated) ke struktur aktual, lalu laporkan penyesuaian ini secara eksplisit di commit message dan nanti di handoff log.
3. **Task 24 (test invariant baru)** — plan MEMINTA kamu membaca `GuruFactory`, `KelasFactory`, `JenisKaryawanMasterFactory` dulu sebelum menulis file test, karena kode test yang dikutip plan adalah TEBAKAN struktur factory berdasarkan konvensi umum project, BUKAN hasil baca langsung. Kalau field factory tidak cocok (nama field beda, ada field wajib lain), sesuaikan test — tapi JANGAN ubah esensi assertion-nya (pegawai_lembaga vs pegawai_yayasan XOR, widestScopeLevel per role, multi-role composition, wali_kelas capability-vs-relation).
4. **Task 18 (`KasusEvaluasiTest.php`)** — plan mencurigai file ini MUNGKIN juga mengandung `admin_akademik` (karena namanya berkaitan dengan `CatatEvaluasiAction.php` di Task 10), tapi ini BELUM dikonfirmasi lewat grep langsung saat plan ditulis. WAJIB grep dulu sebelum menyimpulkan ada/tidaknya.

## Pelajaran penting dari sub-project sebelumnya di repo ini (branch `rbac-v2` dan sebelumnya)

1. **Audit blind spot berulang** — di 3+ sub-project sebelumnya di repo ini, grep audit yang scope-nya diasumsikan sudah lengkap ternyata melewatkan consumer di luar dugaan (contoh nyata: 3 dari 6 seeder demo yang awalnya dicurigai butuh edit ternyata TIDAK perlu; 2 file (`RolePermissionAssignmentSeeder.php`, `KaryawanController.php`) yang awalnya diasumsikan perlu edit ternyata tidak). **Selalu percaya grep segar, bukan daftar tertulis di spec/plan** — itulah kenapa Task 2 dan Task 25 (grep final) ada sebagai gate eksplisit, bukan sekadar formalitas.
2. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
3. **Kalau kamu menemukan file yang isinya BEDA dari yang dikutip plan (baseline sudah berubah) — STOP dan laporkan ke user**, jangan menebak-nebak menyesuaikan sendiri secara diam-diam.
4. **JANGAN jalankan lebih dari satu proses `php artisan test`/`migrate:fresh` bersamaan.**
5. **Klaim hasil test di handoff log HARUS bisa ditelusuri (command + angka pasti), jangan asumsikan atau ekstrapolasi.** Di sub-project sebelumnya, klaim "339 test gagal" di handoff log ternyata anomali environmental sesaat (run ulang bersih: 0 gagal) — untung ketahuan karena direview independen. Kalau kamu melihat lonjakan kegagalan test yang tidak masuk akal dari perubahan yang kamu buat, JANGAN buru-buru menyimpulkan penyebabnya tanpa bukti — laporkan sebagai temuan yang perlu verifikasi, bukan fakta final.
6. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan (misal Task 11/12/24 ternyata strukturnya beda) — STOP dan laporkan ke user, JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.**

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Definisi selesai

- Task 1: migration jalan, `roles.scope_level` menerima 4 nilai termasuk `platform`.
- Task 2: daftar consumer otoritatif terkonfirmasi lewat grep, cocok (atau sudah dikoreksi & dilaporkan) dengan daftar di plan.
- Task 3: `RoleSeeder.php` berisi 17 role, syntax valid.
- Task 4-13: seluruh consumer app+seeder diedit sesuai keputusan (`operator_akademik`, `bendahara_lembaga`, `pegawai_lembaga`, `pegawai_yayasan`), 2 file (Task 4, Task 6) terverifikasi tidak perlu edit.
- Task 14: `migrate:fresh --seed` sukses tanpa exception, tinker check menunjukkan 17 role dan baseline `pegawai_lembaga` benar di akun demo.
- Task 15-23: `karyawan_pool`/`karyawan_lembaga` KOSONG total di 9 file test tsb, masing-masing test scoped PASS.
- Task 24: test invariant baru (pegawai_* assignment, widestScopeLevel, multi-role, wali_kelas) semua PASS.
- Task 25: grep gabungan 4 string role lama KOSONG TOTAL di seluruh codebase.
- Task 26: full suite dijalankan SETELAH izin eksplisit user dan hasilnya hijau (atau kegagalan yang ada terbukti pre-existing/tidak terkait, dengan bukti), handoff log tertulis dengan bukti verifikasi yang bisa ditelusuri (commit hash per task, angka test pasti, hasil grep).
