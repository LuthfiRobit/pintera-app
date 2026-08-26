# Handoff Log: Fondasi Akademik Multi-Jenjang — Sprint 3 (Curriculum Phase)

- **Tanggal**: 2026-08-26
- **Branch**: `akademik-v2`
- **Spec**: `.agents/specs/2026-08-26-akademik-multi-jenjang-sprint3.md`
- **Plan**: `.agents/plans/2026-08-26-akademik-multi-jenjang-sprint3.md`
- **Status Akhir**: SELESAI & TERVERIFIKASI (Full Test Suite: 2200 passed, 4 skipped, 0 failed, 6113 assertions)

---

## 1. Apa yang Dikerjakan

Sprint 3 mengimplementasikan pengenalan `Fase` (Fondasi/A-F Kurikulum Merdeka) sebagai entitas eksplisit yang terpisah dari string bebas `Kelas.tingkat`. Pemetaan default `bentuk_pendidikan` + `tingkat` → `fase` dijadikan **data yang dapat dikonfigurasi** melalui tabel `fase_default_mapping` dan diselesaikan via `FaseDefaultResolver` berbasis SQL query, bukan hardcoded `if/match` di logika PHP. Penugasan pada `Kelas.fase_id` bersifat immutable snapshot.

Secara rinci:
1. **Tabel Reference Global `fase` & Model** (`3a2014a2`):
   - Migration `2026_08_27_090000_create_fase_table.php` (`kode` unique, `nama`, `urutan`).
   - Model `App\Domains\Akademik\Models\Fase`.
   - Seeder `Database\Seeders\FaseSeeder` (7 fase standar: `foundation`, `a`–`f`).
   - Unit tests: `tests/Unit/Models/FaseTest.php`, `tests/Unit/Seeders/FaseSeederTest.php`.

2. **Tabel `fase_default_mapping` dgn Generated Columns** (`1e1302cc`):
   - Migration `2026_08_27_090100_create_fase_default_mapping_table.php` (`lembaga_id` nullable FK on delete cascade, `bentuk_pendidikan`, `tingkat` nullable, `fase_id` FK restrict).
   - Generated columns: `lembaga_key = COALESCE(lembaga_id, 0)` dan `tingkat_key = COALESCE(tingkat, '*')` dengan unique index composite `(lembaga_key, bentuk_pendidikan, tingkat_key)`.
   - Model `App\Domains\Akademik\Models\FaseDefaultMapping` (sengaja tanpa `TenantScope` agar baris `lembaga_id = NULL` terbaca lintas tenant).
   - Seeder `Database\Seeders\FaseDefaultMappingSeeder` (17 pemetaan rekomendasi platform Kurikulum Merdeka).
   - Unit tests: `tests/Unit/Models/FaseDefaultMappingTest.php`, `tests/Unit/Seeders/FaseDefaultMappingSeederTest.php`.

3. **Snapshot Column `kelas.fase_id` & Relasi** (`4428c0ae`):
   - Migration `2026_08_27_090200_add_fase_id_to_kelas_table.php` (`fase_id` nullable FK on delete null).
   - Update `$fillable` dan relasi `fase()` pada `App\Models\Kelas`.
   - Unit test: `tests/Unit/Models/KelasFaseTest.php`.

4. **Service `FaseDefaultResolver`** (`2c81ef0c`):
   - Class `App\Domains\Akademik\Services\FaseDefaultResolver` dengan resolusi precedence 4 tingkat murni via SQL:
     1. Override lembaga tingkat spesifik
     2. Override lembaga catch-all (`tingkat IS NULL`)
     3. Platform tingkat spesifik (`lembaga_id IS NULL`)
     4. Platform catch-all (`lembaga_id IS NULL` + `tingkat IS NULL`)
   - Unit test: `tests/Unit/Services/FaseDefaultResolverTest.php`.

5. **Registrasi Permission & Seeder** (`f36c8235`, `3aa5774e`, `0443fcf7`):
   - Daftarkan `fase-mapping.view`, `fase-mapping.create`, `fase-mapping.edit`, `fase-mapping.delete` ke `PermissionSeeder.php` dan `RoleSeeder.php` (`operator_akademik`).
   - Daftarkan `FaseSeeder` dan `FaseDefaultMappingSeeder` di `DatabaseSeeder.php`.
   - Test: `tests/Feature/Akademik/FaseMappingPermissionSeederTest.php`, `tests/Unit/PermissionSeederTest.php`.

6. **Controller CRUD `Admin\FaseDefaultMappingController`** (`c3e9b254`):
   - Authorization eksplisit `authorizeMappingScope()` untuk validasi boundary tenant dan platform.
   - Route CRUD `admin.fase-mapping.*` di `routes/admin/akademik-master.php`.
   - Blade views: `resources/views/admin/fase-mapping/` (`index`, `create`, `edit`, `_form`).
   - Feature test: `tests/Feature/Akademik/FaseDefaultMappingControllerTest.php`.

7. **Endpoint Suggestion `KelasController::faseSuggestion`** (`6c91e585`):
   - Route `GET admin/kelas/fase-suggestion` (read-only, terikat pada `bentuk_pendidikan` & `lembaga_id` user login).
   - Feature test: `tests/Feature/Akademik/KelasFaseSuggestionTest.php`.

8. **Integrasi UI Form Kelas & Immutability End-to-End** (`6d532fea`):
   - Tambah dropdown Fase dan Alpine auto-fetcher `x-model="tingkat"` / `x-model="faseId"` dengan guard agar pilihan manual/existing tidak tertimpa.
   - Validasi `fase_id` pada `KelasController::store()` dan `update()`.
   - Passing `$faseList` dari `create()` dan `edit()`.
   - Feature test: `tests/Feature/Akademik/KelasFaseAssignmentTest.php`.

9. **Fixes & Pembersihan Test Suite Keseluruhan** (`680ec182`, `a3e0a136`):
   - Tambah trait `RefreshDatabase` ke `tests/Feature/GelombangJalurRestrictionTest.php` agar tidak ada kebocoran status DB.
   - Cegah flakiness `TahunAjaranFactory` dengan `$this->faker->unique()->numberBetween(1900, 2900)`.
   - Sesuaikan ekspektasi count permission pada `tests/Unit/RoleSeederTest.php` dan `tests/Feature/RolePermissionSeederTest.php` menjadi 145 (total) dan 50 (`operator_akademik`).

---

## 2. Keputusan Penting yang Diambil

1. **`virtualAs` vs `storedAs` pada MySQL 8.0**:
   - MySQL 8.0 InnoDB melarang `STORED GENERATED COLUMN` pada kolom yang memiliki foreign key dengan constraint `ON DELETE CASCADE` (`SQLSTATE[HY000]: General error: 1215 Cannot add foreign key constraint`).
   - Menggunakan `VIRTUAL GENERATED COLUMN` (`virtualAs(...)`) mengatasi batasan ini sekaligus tetap mendukung unique composite index `(lembaga_key, bentuk_pendidikan, tingkat_key)`.

2. **Pendaftaran Statis Permission di `PermissionSeeder.php`**:
   - Meskipun ada command `permissions:sync`, unit test dan feature test yang menjalankan `(new PermissionSeeder())->run()` secara langsung dalam isolasi (seperti `tests/Unit/PermissionSeederTest.php`) membutuhkan string permission tercatat di `PermissionSeeder.php`. Oleh karena itu, permission `fase-mapping.*` didaftarkan secara statis di `PermissionSeeder.php` dan total count disesuaikan menjadi 145.

3. **Precedence SQL Murni di `FaseDefaultResolver`**:
   - Tidak ada satu barispun `switch/match/if` untuk pemetaan kurikulum di kode PHP resolver. Logika precedence diimplementasikan via:
     ```php
     $query->orderByRaw('lembaga_id IS NULL')
           ->orderByRaw('tingkat IS NULL');
     if ($tingkat !== null) {
         $query->orderByRaw('tingkat = ? DESC', [$tingkat]);
     }
     ```
   - Dengan ini, penambahan jenjang/fase baru cukup menambahkan baris data di `fase_default_mapping`.

4. **Pencegahan Overwrite pada Alpine Form Kelas**:
   - Pada `resources/views/admin/kelas/_form.blade.php`, fungsi Alpine `fetchSuggestion()` memiliki pengecekan `if (this.faseId !== '') return;`. Hal ini menjamin bahwa saat form edit dibuka atau ketika admin sengaja memilih fase manual terlebih dahulu, saran otomatis tidak menimpa input admin.

---

## 3. Hal yang Perlu Direview Manusia / Claude

1. **Git State**:
   - Branch: `akademik-v2`
   - Status git: Clean (semua perubahan sudah ter-commit).
   - Belum di-push ke remote (keputusan push/PR diserahkan kepada pengguna/Claude per workflow).

2. **Kesiapan Menuju Sprint 4 (Integrasi Akademik & Nilai/Rapor)**:
   - Fondasi `Fase` dan `Kelas.fase_id` sudah siap. Sprint berikutnya dapat mengonsumsi `fase_id` dari `Kelas` untuk mapping Capaian Pembelajaran (CP) dan Tujuan Pembelajaran (TP) per fase.

3. **Verifikasi Test Suite**:
   - Telah dijalankan penuh: `php artisan test`
   - Hasil: `Tests: 4 skipped, 2200 passed (6113 assertions)`, durasi 534.50s, 0 failed.
