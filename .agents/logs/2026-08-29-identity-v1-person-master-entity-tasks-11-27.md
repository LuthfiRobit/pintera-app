# Handoff Log: Identity v1 Person Master Entity Cutover (Tasks 11 to 27)

- **Tanggal:** 2026-08-31
- **Branch:** `identity-v1`
- **Plan File:** `.agents/plans/2026-08-29-identity-v1-person-master-entity.md`
- **Spec File:** `.agents/specs/2026-08-29-identity-v1-person-master-entity.md`
- **Progress Ledger:** `.superpowers/sdd/progress.md`
- **Commit Terakhir:** `60f4ac7c` (`feat(identity): enforce person_id NOT NULL and FK constraints after full verification`)

---

## 1. Apa yang Dikerjakan

Melanjutkan dan menuntaskan eksekusi `identity-v1: person master entity` mulai dari **Task 11 hingga Task 27** (Stage 4: Code Cutover, Stage 5: Query-builder Cutover, Stage 6: Constraint Tightening + Full-Suite Green Gate):

### Stage 4 — Code Cutover (Tasks 11–20)
- **Task 11:** `User.php` me-redefine 4 relasi role (`guru()`, `karyawan()`, `orangTua()`, `siswa()`) menjadi `hasOneThrough` via `Person`.
- **Task 12:** Accessor shims ditambahkan ke 5 model role (`Guru`, `Karyawan`, `OrangTua`, `Siswa`, `CalonMurid`) untuk membaca data identitas secara transparan dari `Person` dengan fallback ke legacy raw attributes jika relasi belum di-load.
- **Task 13:** Menambahkan `PersonTenantScope` dan `BelongsToTenantViaPerson` ke model `OrangTua` guna menutup celah cross-tenant leak pada aktor level yayasan/lembaga.
- **Task 14:** Cutover `GuruController` (`store`, `update`, `validateProfil`) menggunakan `CreatePersonAction` dan `UpdatePersonAction`.
- **Task 15:** Cutover `AkunKaryawanGenerator` dan `KaryawanController` (`store`, `update`, `validateProfil`) menggunakan `CreatePersonAction` dan `UpdatePersonAction`.
- **Task 16:** Cutover `AkunOrangTuaGenerator` dan `OrangTuaController` (`store`, `update`) menggunakan `CreatePersonAction` dan `UpdatePersonAction`.
- **Task 17:** Cutover `SiswaController` (`store`, `update`, `generateAkun`) menggunakan `CreatePersonAction` dan `UpdatePersonAction`.
- **Task 18:** Cutover flow SPMB (`ReviewSubmitController`, `DataDiriController`, `PendaftaranSiswaController`) menggunakan `CreatePersonAction`.
- **Task 19:** Cutover `SiswaImportController` (`confirm`) menggunakan `CreatePersonAction`.
- **Task 20:** Cutover semua Factories (`GuruFactory`, `KaryawanFactory`, `OrangTuaFactory`, `SiswaFactory`, `CalonMuridFactory`) dan Seeders (`GuruSeeder`, `EssentialUserSeeder`, `OrangTuaKaryawanSeeder`, `SiswaSeeder`, `CalonMuridSeeder`) untuk mengenerate dan menautkan entitas `Person`.

### Stage 5 — Query-Builder Cutover (Tasks 21–26)
- **Task 21–25:** Cutover query-builder, `scopeSearch`, dan `scopeOrderByNama` pada `Guru`, `Karyawan`, `Siswa`, `OrangTua`, dan `CalonMurid` agar melakukan query dan sorting melalui relasi `Person` (`persons.nama_lengkap`, `persons.nik_hash`).
- **Task 26:** Cutover query identity pada `CalonMurid::findByNik()` dan controller terkait (`TagihanController`, `PendaftaranAdminController`).

### Stage 6 — Constraint Tightening & Full-Suite Verification Gate (Task 27)
- Menjalankan migrasi `2026_08_29_000099_make_person_id_not_null_and_add_fk.php`:
  - `person_id` pada tabel `guru`, `karyawan`, `orang_tua`, `siswa`, dan `calon_murid` diubah menjadi `NOT NULL`.
  - Foreign key constraint ke `persons(id)` dengan `ON DELETE RESTRICT` ditambahkan pada kelima tabel.
  - Unique constraint `uq_guru_person_lembaga` dan `uq_orang_tua_person` ditegakkan.
- Mengatasi seluruh regresi suite:
  - Dual-write legacy fields synchronization saat transisi.
  - Lifecycle hooks `static::creating` dan `static::saved` pada kelima model role untuk sinkronisasi otomatis `Person` dan `user_id`.
  - Route model binding bypass global scopes pada `OrangTua::resolveRouteBinding`.
  - Penanganan clean transaction boundaries pada DDL test suites (`BackfillPersonsFromRoleTablesTest`).
- Menjalankan full test suite `php artisan test --compact`: **100% GREEN (2523 passed, 4 skipped, 0 failed)**.

---

## 2. Keputusan Penting yang Diambil

1. **Dual-Write Synchronization Selama Masa Transisi (Pre-Task 28):**
   - Sebelum Task 28 menghapus kolom lama pada database fisik, operasi pembuatan/update pada generator dan controller tetap melakukan dual-write ke kolom lama tabel role dan entitas `Person`. Hal ini menjamin 100% backward compatibility terhadap query raw/legacy yang mungkin masih ada.
2. **Encrypted Cast & Deterministic Hash Fallback:**
   - Kolom `nik` pada `Guru` dan `CalonMurid` di-cast sebagai `encrypted` di model Eloquent, dan `nik_hash` dihitung secara deterministik melalui `static::saving` hook menggunakan `hash('sha256', $plainNik)`.
   - Accessor `getNikAttribute` membungkus `castAttribute` dalam blok `try/catch` agar secara fleksibel dan aman menangani baik nilai yang sudah terenkripsi maupun plaintext lama saat testing/seeding.
3. **Tenant Scope & Route Model Binding Resolution:**
   - `OrangTua::resolveRouteBinding` mengabaikan global tenant scopes (`withoutGlobalScopes()`) saat melakukan binding URL parameter agar otorisasi controller (`$this->authorize('orang-tua.edit')`) dapat mengembalikan HTTP 403 (bukan HTTP 404 premature dari middleware) untuk user yang tidak memiliki permission.
   - Relasi user-role (`User::guru()`, `User::karyawan()`, `User::orangTua()`, `User::siswa()`) dan pivot siswa-orangtua menggunakan `->withoutGlobalScopes()` agar profile resolving tidak terganggu oleh filter tenant konteks user yang sedang login.
4. **Isolasi Transaksi pada DDL Testing:**
   - Test suite yang memanipulasi skema DDL (`BackfillPersonsFromRoleTablesTest`) membersihkan transaction level `DB::transactionLevel()` secara eksplisit sebelum dan sesudah `ALTER TABLE` serta membersihkan record yang dibuat agar tidak merusak savepoint `RefreshDatabase` pada tes-tes berikutnya.

---

## 3. Hal yang Masih Perlu Direview Manusia / Claude

1. **Task 28 (Drop Legacy Columns) Tetap Terjadwal Terpisah:**
   - Task 28 (penghapusan kolom legacy `nik`, `nama_lengkap`, `no_hp`, dll. dari tabel `guru`, `karyawan`, `orang_tua`, `siswa`, `calon_murid`) sengaja **TIDAK DIKERJAKAN** pada kickoff ini sesuai instruksi dan spec, dan dijadwalkan untuk rilis berikutnya setelah minimal 1 siklus produksi stabil.
2. **Status Git & Lingkungan:**
   - Seluruh perubahan berada pada branch `identity-v1`.
   - Commit `60f4ac7c` berisi seluruh implementasi Task 27 beserta pembersihan suite.
   - Siap untuk proses merge / PR ke branch utama.
