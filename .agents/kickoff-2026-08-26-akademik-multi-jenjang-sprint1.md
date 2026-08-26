# Kickoff Prompt — Fondasi Akademik Multi-Jenjang, Sprint 1 (Subjek Penilaian Polymorphic)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan sudah 2x direview (approved dengan penajaman, lalu review teknis kedua yang menemukan 1 perbaikan nyata sudah diterapkan). Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu

1. `.agents/specs/2026-08-26-akademik-multi-jenjang-fondasi.md` — spec lengkap (latar belakang kenapa refactor ini perlu, cakupan 5 sprint, detail penuh Sprint 1, acceptance criteria).
2. `.agents/plans/2026-08-26-akademik-multi-jenjang-sprint1.md` — plan implementasi (6 task, kode lengkap per task, TDD step-by-step).

## Konteks singkat

- Repo Laravel 12.63.0 multi-tenant (`pintera-app`). Branch kerja: `akademik-v2` (SUDAH ADA — jangan buat branch baru, jangan buat worktree).
- **Baseline kode plan ini: commit `ce14f33`.** Kalau isi file yang kamu baca BEDA signifikan dari yang dikutip plan, STOP, laporkan ke user.
- **Kenapa sub-project ini ada**: sistem sedang dibenahi supaya bisa menangani jenjang PAUD (bukan cuma SD/SMP/SMA/SMK) — PAUD tidak punya mata pelajaran, melainkan "Elemen Capaian Pembelajaran" (Kurikulum Merdeka). Saat ini `KomponenPenilaian`/`Asesmen` MEWAJIBKAN `mata_pelajaran_id`, jadi PAUD terpaksa pakai "mata pelajaran dummy" — tambal sulam. Sprint 1 mengganti ini jadi relasi polymorphic `subjek_type`/`subjek_id` (MataPelajaran ATAU ElemenCp).
- **Status project: masih development/demo, BELUM ada sekolah produksi memakai sistem.** Ini KENAPA breaking changes (drop kolom, ubah nama relasi model, ubah semua call site) diperbolehkan dan sengaja dipilih — sudah didiskusikan panjang dan disepakati eksplisit sebagai keputusan sadar, BUKAN kecerobohan. Volume data demo saat ini kecil (9 `komponen_penilaian`, 2 `asesmen`).
- **Cakupan Sprint 1 SAJA** — jangan mengerjakan Sprint 2 (Assessment Type), Sprint 3 (Curriculum Phase), Sprint 4 (Academic Profile), Sprint 5 (Report Engine), atau modul bisnis apa pun (P5, PKL, UKK, Tracer Study, e-Ijazah). Kalau kamu tergoda "sekalian aja", STOP — itu scope creep yang sudah eksplisit ditolak di 2 putaran review.

## Urutan eksekusi

**Task 1 → 2 → 3 → 4 → 5 → 6 murni LINEAR** — setiap task butuh hasil task sebelumnya (tambah kolom baru → backfill data → geser kode → geser UI → bersihkan test lama & drop kolom lama). TIDAK ADA task yang bisa dikerjakan paralel di Sprint 1 ini (beda dari sub-project dashboard sebelumnya yang task-nya independen).

**Kalau kamu punya akses ke skill `superpowers`:**
Gunakan `superpowers:subagent-driven-development` — fresh subagent per task, task review, ledger progress di `.superpowers/akademik-multi-jenjang-sprint1/progress.md`. Karena semua task linear, dispatch subagent SATU PER SATU secara berurutan, jangan dispatch task berikutnya sebelum task sebelumnya lolos review.

**Kalau tidak punya skill itu:**
Eksekusi manual task-by-task (Task 1 → 6):
1. Baca task, kerjakan tiap step persis seperti ditulis (kode di plan itu final, sudah 2x direview).
2. WAJIB baca isi file existing dulu sebelum menimpa/mengedit — semua field/relasi model di plan ini sudah diverifikasi langsung dari kode via subagent Explore, tapi tetap baca ulang file existing yang akan diedit.
3. Jalankan `php -l <file>` (syntax check) setelah tiap edit PHP, SEBELUM commit.
4. Jalankan test scoped SEBELUM commit — kalau gagal, JANGAN commit, diagnosis dulu.
5. Satu commit per task (plan sudah menyediakan pesan commit per task).
6. **JANGAN jalankan full test suite sampai Task 6 Step 5** — task 1-5 cukup test scoped.
7. Task 6 Step 3 (verifikasi grep nol-hasil) WAJIB lolos SEBELUM Step 4 (migration drop kolom) dijalankan — ini bukan opsional, dan tidak bisa dibalik kalau sudah dijalankan tanpa backup.

## Peringatan eksplisit dari plan — beberapa bagian punya ketidakpastian yang HARUS diverifikasi, bukan diasumsikan

Plan ini menandai beberapa titik sbg "Catatan implementer" karena butuh verifikasi runtime yang tidak bisa dipastikan 100% tanpa menjalankan kode sungguhan — SEMUA WAJIB diikuti, JANGAN dilewati atau ditebak:

1. **Task 3 Step 2** — pendekatan test `Artisan::call('migrate', ['--path' => ...])` untuk menjalankan SATU migration file terisolasi mungkin tidak berjalan mulus kalau `RefreshDatabase` sudah menjalankan migration itu sekaligus dalam satu batch. Verifikasi dulu, kalau tidak jalan, cari pendekatan lain (mis. panggil method backfill langsung sbg static/testable method) dan laporkan penyesuaiannya di handoff log.
2. **Task 4 Step 18** — `'subjek_id' => MataPelajaran::factory()'` di factory kolom polymorphic biasa (bukan relasi Eloquent standar) MUNGKIN tidak otomatis ter-resolve jadi integer id. Verifikasi dengan test nyata; kalau gagal, ganti jadi closure eksplisit `fn () => MataPelajaran::factory()->create()->id`.
3. **Task 4 Step 17** — `CapaianKompetensiGenerator::generateNarasi()` param type-hint `SubjekPenilaian $subjek` (interface marker kosong) mungkin tidak bisa dipakai memanggil `getMorphClass()`/`getKey()` (method itu milik `Model`, bukan dideklarasikan interface). Kalau PHP menolak, longgarkan jadi union type `MataPelajaran|ElemenCp $subjek`.
4. **Task 4 Step 19** — nama route/permission `operator_akademik` untuk komponen-penilaian di test `SubjekTenantValidationTest` adalah ASUMSI. Cek `routes/admin/akademik-master.php` dan `RoleSeeder.php` untuk nama sebenarnya sebelum finalisasi assertion.
5. **Task 5 Step 5** — nama route `guru.komponen-penilaian.store`/`create` di test `KomponenPenilaianElemenCpUiTest` adalah ASUMSI. Cek route Guru portal komponen-penilaian sebenarnya.
6. **Task 6 Step 4** — nama foreign key constraint (`dropForeign(['mata_pelajaran_id'])`) mengasumsikan konvensi default Laravel. Kalau migration gagal karena nama constraint tidak cocok, cek via `SHOW CREATE TABLE komponen_penilaian;`/`SHOW CREATE TABLE asesmen;`.

## Pelajaran penting dari sub-project sebelumnya di repo ini (berlaku juga di sini)

1. **Migration tidak boleh bergantung pada Eloquent model** — plan Sprint 1 SENGAJA pakai `DB::table()` murni di migration backfill (Task 3), bukan `ElemenCp::where(...)`. Ini sudah diperbaiki di review kedua — JANGAN kembalikan ke pola Eloquent di migration manapun yang kamu tulis/modifikasi.
2. **`whereHasMorph()` vs `whereHas()` biasa** — untuk filter berdasarkan atribut model konkret di balik relasi polymorphic (`subjek`), WAJIB pakai `whereHasMorph('subjek', [KelasModel::class], fn ($q) => ...)`. `whereHas('subjek')` biasa (tanpa constraint tipe) HANYA valid untuk cek eksistensi generik, bukan filter atribut spesifik satu tipe. Plan sudah benar di titik-titik ini (`DashboardStatsService`) — JANGAN diubah polanya saat implementasi.
3. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
4. **Kalau kamu menemukan file yang isinya BEDA dari yang dikutip plan (baseline sudah berubah) — STOP dan laporkan ke user**, jangan menebak-nebak menyesuaikan sendiri.
5. **JANGAN jalankan lebih dari satu proses `php artisan test`/`migrate:fresh` bersamaan.**
6. **Klaim hasil test di handoff log HARUS bisa ditelusuri (command + angka pasti)**, jangan asumsikan atau ekstrapolasi.
7. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan — STOP dan laporkan ke user, JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.** Kalau sebuah test gagal karena skenario yang memang seharusnya berhasil, perbaiki KODE-nya, JANGAN lemahkan test-nya (mis. menghapus skenario pemicu bug supaya lolos).
8. **Ini refactor fondasi, bukan pembangunan fitur baru** — kalau kamu tergoda menambah kemampuan di luar yang diminta plan (mis. auto-suggest fase kurikulum, validasi tambahan yang "kelihatannya bagus"), STOP dulu. Global Constraints di plan eksplisit membatasi scope.

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Definisi selesai

- Task 1: `elemen_cp` table + model + factory + seeder (3 baris tetap) + interface `SubjekPenilaian` + helper `SubjekPenilaianKey` + morph map terdaftar, 4 test baru PASS.
- Task 2: kolom `subjek_type`/`subjek_id` nullable ada di `komponen_penilaian` & `asesmen`, 1 test PASS, data lama tidak rusak.
- Task 3: migration backfill jalan dengan precedence `elemen_cp` > `mata_pelajaran_id`, fail-fast utk baris tak terpetakan, 4 test PASS.
- Task 4: seluruh Model/Action/DTO/Request/Controller/Service pakai `subjek_type`/`subjek_id` (bukan `mata_pelajaran_id` lagi di jalur baru), composite key dipakai konsisten via `SubjekPenilaianKey`, 2 test regresi baru PASS (tenant validation + no-collision rekap).
- Task 5: toggle "Jenis Subjek Penilaian" tampil di portal Lembaga DAN Guru (Guru sebelumnya tidak punya sama sekali), tervalidasi backend, 2 test baru PASS.
- Task 6: 26 file test lama diperbaiki ke pola `subjek_type`/`subjek_id` dan lolos, `git grep` utk `mata_pelajaran_id`/`elemen_cp`/`->mataPelajaran` di area KomponenPenilaian/Asesmen menghasilkan NOL, migration drop kolom lama berhasil, `migrate:fresh --seed` sukses, full test suite hijau (kegagalan pre-existing yang terbukti tidak terkait boleh diabaikan dengan bukti re-run terisolasi), handoff log tertulis dengan bukti verifikasi yang bisa ditelusuri (commit hash per task, angka test pasti, catatan penyelesaian tiap "Catatan implementer" di atas).
