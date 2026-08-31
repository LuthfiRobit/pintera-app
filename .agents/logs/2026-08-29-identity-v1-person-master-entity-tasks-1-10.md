# Handoff Log: Identity v1 (Person Master Entity) — Tasks 1-10 of 28

- **Slug Task**: `2026-08-29-identity-v1-person-master-entity`
- **Spec**: `.agents/specs/2026-08-29-identity-v1-person-master-entity.md`
- **Plan**: `.agents/plans/2026-08-29-identity-v1-person-master-entity.md` (28 tasks, 6 stages; Task 28 explicitly deferred/out of scope)
- **Branch**: `identity-v1`
- **Tanggal**: 31 Agustus 2026
- **Status**: Tasks 1-10 SELESAI dan direview. Task 11 BELUM dikerjakan (lihat §5). Sisa pekerjaan (Task 11-27) diserahkan ke agent lain lewat kickoff terpisah: `.agents/kickoff-2026-08-29-identity-v1-stage4-cutover.md`.

Ini BUKAN handoff akhir proyek — ini titik jeda di tengah eksekusi plan 28-task. Dokumen ini merekam apa yang SUDAH selesai dan diverifikasi, supaya agent berikutnya tidak perlu menebak atau mengulang.

---

## 1. Apa yang Dikerjakan (Tasks 1-10)

Dieksekusi via `superpowers:subagent-driven-development` — 1 implementer subagent per task, review spec-compliance + code-quality setelahnya, fix loop kalau ada temuan, sampai reviewer approve. Semua task berikut SELESAI, diverifikasi test, dan di-commit:

| Task | Isi | Commit |
|---|---|---|
| 1 | Migration `persons` table (26 kolom, FK `persons.user_id → users.id`, `UNIQUE(yayasan_id, nik_hash)`) + `users.merged_into_user_id` | `b66d2234` |
| 2 | `Person` model + `YayasanScope` (scope BARU, bukan `TenantScope` yang sudah ada) + `nik_hash` saving-hook | `b280d1d0` (setelah 1 fix round) |
| 3 | Migration tambah `person_id` nullable ke 5 tabel role + relax SEMUA kolom identitas lama jadi nullable, migration yang SAMA | `04347ebd` (setelah 2 fix round) |
| 4 | `identity:backfill-persons` command | `c912e0d9` (setelah 3 fix round) |
| 5 | `identity:verify-backfill` command | `4c06636f` |
| 6 | `CreatePersonAction` (resolusi `yayasan_id` transitif + dedup NIK) | `71ffa4ff` |
| 7 | `PersonDuplicateFinder` | `130f8f34` (setelah 1 fix round) |
| 8 | `UpdatePersonAction` | `f66effca` |
| 9 | `MergePersonsAction` + `ConflictingUserAccountsException` | `958ada20` |
| 10 | `DeactivatePersonAction` / `ReactivatePersonAction` | `98033aee` |

Ditambah 1 commit dokumentasi murni: `0119fbcc` (mencatat 2 koreksi Task 6 & 9 langsung di file plan, lihat §2).

**HEAD branch `identity-v1` saat ini: `0119fbcc`.**

Stage 1 (Schema), Stage 2 (Backfill+Verifikasi), dan Stage 3 (PersonService lengkap) dari plan SEPENUHNYA selesai. Stage 4 (Code Cutover, Task 11-21), Stage 5 (Query-builder cutover, Task 22-26), dan Stage 6 (NOT NULL + full suite, Task 27) BELUM disentuh sama sekali.

---

## 2. Keputusan Penting yang Diambil / Koreksi Ditemukan

Setiap task dari 1-10 (KECUALI Task 1, 5, 8, 10 yang lolos review pertama tanpa temuan) menemukan MINIMAL satu bug nyata yang hanya kelihatan saat dicek langsung ke kode/schema sungguhan, bukan lewat percaya ke prosa plan. Semua sudah diperbaiki dan didokumentasikan LANGSUNG di file plan (`.agents/plans/2026-08-29-identity-v1-person-master-entity.md`, cari teks **"Correction found during..."** di tiap Task section) supaya plan tetap jadi sumber kebenaran yang benar, bukan cuma catatan di sini. Ringkasan (detail lengkap ada di file plan, jangan diringkas ulang secara keliru):

1. **Task 2 — `TenantScope` TIDAK BISA dipakai langsung untuk `Person`.** `TenantScope`'s 2 dari 3 cabang non-platform-nya mengasumsikan model punya kolom `lembaga_id`, yang `persons` TIDAK PUNYA SAMA SEKALI (batasnya cuma `yayasan_id`). Solusinya BUKAN mengubah `TenantScope` (file shared, dipakai banyak model lain) — dibuatkan scope BARU khusus, `app/Models/Scopes/YayasanScope.php`.
2. **Task 3 — 2 kelas bug di migration relax-nullable:**
   - `->string('jenis_kelamin')->nullable()->change()` DIAM-DIAM menghapus constraint `enum('L','P')` jadi `varchar` bebas (Laravel `change()` merekonstruksi definisi kolom PENUH dari yang ditulis, bukan cuma field yang disebut). Sama untuk `kewarganegaraan`'s default `'WNI'` yang hilang.
   - Daftar kolom yang di-relax awalnya TIDAK LENGKAP: `guru`/`karyawan.nik_hash` (unique NOT NULL), `orang_tua.nik`+`no_hp` (NOT NULL), dan SELURUH kolom identitas `calon_murid` (nik, nik_hash, nama_lengkap, jenis_kelamin, tempat_lahir, tanggal_lahir, agama — SEMUA NOT NULL) terlewat sama sekali di draft pertama. Ini butuh perbaikan langsung ke database dev (lihat §4) karena migration sudah kadung dijalankan dengan versi salah.
3. **Task 4 — backfill command kehilangan data**: `backfillGuru()` awalnya tidak menyalin 9 field alamat/kewarganegaraan (`kewarganegaraan, alamat_jalan, rt, rw, desa_kelurahan, kecamatan, kabupaten_kota, provinsi, kode_pos`), `backfillSiswa()` tidak menyalin `agama`. Plus laporan collision NIK awalnya cuma menyebut 1 tabel (yang reuse), bukan tabel asal — lalu perbaikan PERTAMA untuk itu tanpa sengaja bikin regresi baru: collision intra-table `orang_tua` (satu-satunya tabel role yang `nik`-nya TIDAK punya unique constraint DB) jadi tidak terlaporkan. Fix final keys pada `(table, row_id)` bukan nama tabel saja.
4. **Task 6 — dedup NIK di `CreatePersonAction` bisa gagal senyap** kalau yayasan aktor yang login beda dari yayasan target yang di-resolve, karena `YayasanScope`'s filter ambient AND dengan filter eksplisit `where('yayasan_id', ...)` di query dedup. Fix: `Person::withoutGlobalScope(YayasanScope::class)` khusus di lookup dedup ini saja (BUKAN di `Person::create()`).
5. **Task 7 — test tidak pernah benar-benar membuktikan tenant-scoping bekerja** (cuma test 1-yayasan). Ditambah test cross-tenant, diverifikasi reviewer dengan cara mematikan scope sementara dan konfirmasi test baru gagal (lalu scope dikembalikan).
6. **Task 9 — race unique constraint** di `MergePersonsAction`: set `$winning->user_id` SEBELUM `$losing->user_id` dikosongkan melanggar `UNIQUE` di `persons.user_id`. Fix: kosongkan losing side dulu (digabung dengan `merged_into_person_id` dalam 1 `update()`), baru assign ke winning side, semua dalam 1 transaction yang sama.

**Pola yang WAJIB diteruskan ke agent berikutnya**: HAMPIR SETIAP task 1-10 (kecuali 4 dari 10) punya bug nyata yang cuma ketahuan lewat verifikasi langsung ke schema/kode sungguhan, BUKAN dari percaya ke teks brief/plan. Task 11-27 yang tersisa JAUH lebih berisiko (Task 11 me-refactor relasi yang dipakai 87 file; Task 14-20 menulis ulang titik create/update; Task 22-26 menulis ulang ~30 titik query-builder) — kewaspadaan yang sama, atau lebih tinggi, WAJIB diteruskan. Detail lengkap ada di kickoff terpisah.

---

## 3. Daftar Commit (kronologis)

```
b66d2234  feat(identity): create persons master identity table
b270c7fe  feat(identity): add Person model with YayasanScope tenant boundary
b280d1d0  fix(identity): correct YayasanScope guard comment and cover scope bypass/fail-closed paths
3782c96d  feat(identity): add nullable person_id and relax legacy identity NOT NULL constraints
04347ebd  fix(identity): preserve enum/default on change() and relax all NOT NULL identity columns
7d22e3fa  feat(identity): add BackfillPersonsFromRoleTables command
54fd251d  fix(identity): add missing field mappings and improve collision reporting
c912e0d9  fix(identity): detect intra-table NIK collisions with no DB unique constraint
4c06636f  feat(identity): add identity:verify-backfill command
71ffa4ff  feat(identity): add CreatePersonAction with transitive yayasan_id resolution
52d4b6c1  feat(identity): add PersonDuplicateFinder for NIK-absent dedup warning
130f8f34  test(identity): verify PersonDuplicateFinder respects YayasanScope with cross-tenant test
f66effca  feat(identity): add UpdatePersonAction with immutable yayasan_id
958ada20  feat(identity): add MergePersonsAction with conflicting-user-accounts guard
98033aee  feat(identity): add DeactivatePersonAction and ReactivatePersonAction
0119fbcc  docs(identity): record Task 6 and Task 9 corrections found during implementation
```

---

## 4. Hasil Verifikasi

- **Test scoped per task**: semua lulus pada commit terakhirnya masing-masing (lihat tabel §1). Total test baru yang ditambahkan Task 1-10: ~40 test di `tests/Feature/Identity/`.
- **Full suite BELUM dijalankan** sejak identity-v1 dimulai — sesuai plan, full suite cuma wajib di Task 27 (gate akhir sebelum merge).
- **Database dev**: sudah dimigrasikan sampai migration Task 3 (`2026_08_29_000002_add_person_id_and_relax_identity_columns.php`), termasuk perbaikan manual (dikonfirmasi user via `AskUserQuestion`) untuk mengembalikan `guru.jenis_kelamin`/`siswa.jenis_kelamin` ke `enum('L','P')` dan `guru.kewarganegaraan` ke default `'WNI'` setelah migration versi awal (buggy) sempat ter-apply. Skema dev SEKARANG SUDAH KONSISTEN dengan migration file yang ter-commit — tidak perlu perbaikan manual lagi untuk Task 1-3.
- **Backfill command belum pernah dijalankan terhadap data real** — `identity:backfill-persons` cuma diuji lewat factory di test suite, BELUM dijalankan `php artisan identity:backfill-persons` sungguhan terhadap database dev yang berisi data asli. Ini masuk scope Stage 2 eksekusi nyata yang jadwalnya SETELAH Task 11-21 (code cutover) selesai, sesuai urutan migrasi di plan §8 — JANGAN dijalankan lebih awal dari itu kecuali diminta eksplisit.
- **Pint**: bersih di semua commit.

---

## 5. Hal yang Masih Perlu Direview Manusia / Claude

- **Task 11 TIDAK DIKERJAKAN** — sempat didispatch tapi sesi background agent-nya terputus (status "stopped", tidak ada commit yang landed, `git log` mengonfirmasi HEAD masih di Task 10). Agent berikutnya HARUS memulai Task 11 dari NOL, bukan melanjutkan dari state parsial (tidak ada state parsial — `app/Models/User.php` masih persis sama seperti sebelum Task 11 didispatch).
- **State Git**: branch `identity-v1`, belum di-push ke remote, belum di-merge ke manapun.
- **`.superpowers/sdd/` adalah scratch/gitignored** — berisi ledger progress (`progress.md`), semua brief/report/diff task 1-10. BERGUNA untuk konteks tapi TIDAK terikat di git; kalau direset (`git clean -fdx`), hilang. Jangan andalkan sebagai satu-satunya sumber kebenaran — `git log` + file plan yang sudah di-commit adalah ground truth.
- **Task 28 (drop kolom lama) sengaja TIDAK masuk scope eksekusi ini sama sekali** — dijadwalkan terpisah, setelah minimal 1 siklus rilis produksi penuh pasca Task 27, sesuai instruksi user yang eksplisit di awal sesi ini.
