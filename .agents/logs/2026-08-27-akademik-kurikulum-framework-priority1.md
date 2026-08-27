# Handoff Log: Prioritas 1 Roadmap Kurikulum Dinamis (KurikulumFramework & KurikulumAssignment)

> **Tanggal**: 27 Agustus 2026  
> **Branch**: `akademik-v2`  
> **Status**: Selesai diimplementasikan & diverifikasi penuh (2270 test passed, 0 failed, 4 skipped)  
> **Spec**: [`.agents/specs/2026-08-27-akademik-kurikulum-framework-priority1.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-27-akademik-kurikulum-framework-priority1.md)  
> **Plan**: [`.agents/plans/2026-08-27-akademik-kurikulum-framework-priority1.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-27-akademik-kurikulum-framework-priority1.md)

---

## 1. Apa yang Dikerjakan

Implementasi menyeluruh untuk Prioritas 1 Roadmap Kurikulum Dinamis sesuai spesifikasi dan implementation plan (6 task):

1. **Task 1 (Commit `854fcf77`)**:
   - Enum `App\Domains\Akademik\Enums\KurikulumFramework` (`'k13'`, `'merdeka'`) dengan method `label(): string`.
   - Enum `App\Domains\Akademik\Enums\BentukPendidikan` (9 kasus: `KB, TPA, SPS, TK, SD, SMP, SMA, SMK, SLB`) dengan method `validTingkatValues(): array`.
   - Unit tests: `tests/Unit/Domains/Akademik/Enums/KurikulumFrameworkTest.php` (2 test, 5 assertions) dan `tests/Unit/Domains/Akademik/Enums/BentukPendidikanTest.php` (5 test, 10 assertions).

2. **Task 2 (Commit `4ec38386`)**:
   - Migration `database/migrations/2026_08_27_110000_create_kurikulum_assignment_table.php` dengan virtual generated columns `lembaga_key` (`COALESCE(lembaga_id, 0)`) dan `tingkat_key` (`COALESCE(tingkat, '*')`) untuk composite unique key MySQL handling nulls.
   - Exception `App\Domains\Akademik\Exceptions\KurikulumAssignmentNotFoundException`.
   - Model `App\Domains\Akademik\Models\KurikulumAssignment` dengan relasi `lembaga()`, `tahunAjaran()`, dan enum casting ke `KurikulumFramework`.
   - Unit test: `tests/Unit/Models/KurikulumAssignmentTest.php` (4 test).

3. **Task 3 (Commit `15c166f0`)**:
   - Service `App\Domains\Akademik\Services\KurikulumAssignmentResolver` yang mengimplementasikan 4-level precedence query (`(lembaga_id, tingkat)` > `(lembaga_id, null)` > `(null, tingkat)` > `(null, null)`), memfilter tahun ajaran dan bentuk pendidikan, serta melempar `KurikulumAssignmentNotFoundException` saat tidak ditemukan.
   - Unit test: `tests/Unit/Services/KurikulumAssignmentResolverTest.php` (7 test mencakup semua level precedence, isolasi tahun ajaran, dan exception throw).

4. **Task 4 (Commit `e29674f6`)**:
   - Migration `database/migrations/2026_08_27_110100_add_kurikulum_to_kelas_table.php` menambahkan nullable kolom `kurikulum` varchar(20) setelah `fase_id`.
   - Model `App\Models\Kelas` ditambahkan `kurikulum` ke `$fillable` dan `casts()` method ke `KurikulumFramework::class`.
   - Action `App\Domains\Akademik\Actions\Kelas\CreateKelasAction` diinjeksi `KurikulumAssignmentResolver` dan otomatis menetapkan snapshot `kurikulum`.
   - Action `UpdateKelasAction` tidak menyentuh `kurikulum` (menjaga sifat snapshot immutable).
   - Controller `App\Http\Controllers\Admin\KelasController::store()` menangkap `KurikulumAssignmentNotFoundException` dan mengembalikan validasi error 422 (`withErrors(['tingkat' => ...])`).
   - Feature test snapshot: `tests/Feature/Akademik/KelasKurikulumSnapshotTest.php` (5 test).
   - Retrofit 4 existing test files dengan seed `KurikulumAssignment`: `CreateKelasActionTest.php`, `KelasFaseAssignmentTest.php`, `KelasCrudTest.php`, `KelasPolaJamTest.php`. Total 27 test Kelas passed.

5. **Task 5 (Commit `991f923b`)**:
   - DTO `KurikulumAssignmentData`.
   - Actions: `CreateKurikulumAssignmentAction` dan `UpdateKurikulumAssignmentAction`.
   - Form Requests: `StoreKurikulumAssignmentRequest` dan `UpdateKurikulumAssignmentRequest` dengan validasi enum dan validasi silang `tingkat` vs `bentuk_pendidikan` via `withValidator()` closure.
   - Controller `App\Http\Controllers\Admin\KurikulumAssignmentController` dengan tenant-isolation dan scope guardrail (hanya platform/yayasan yang bisa assign platform default / lintas lembaga).
   - Routes: 6 routes RESTful `admin/kurikulum-assignment` di `routes/admin/akademik-master.php`.
   - Seeders: penambahan 4 permissions `kurikulum-assignment.{view,create,edit,delete}` ke `PermissionSeeder.php` dan `RoleSeeder.php` (`operator_akademik` role).
   - Blade views: `resources/views/admin/kurikulum-assignment/_form.blade.php`, `index.blade.php`, `create.blade.php`, `edit.blade.php`.
   - Feature tests: `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php` (7 test passed).

6. **Task 6 (Commit `5fdbf4bc`)**:
   - Verifikasi referensi liar / dead code: 144 referensi strictly di file-file rencana.
   - Penyesuaian expected count permission seeder tests (`PermissionSeederTest.php`, `RoleSeederTest.php`, `RolePermissionSeederTest.php` dari 145 -> 149, `operator_akademik` 50 -> 54).
   - Safe trimming kode unik di `ElemenCpFactory.php` (maks 30 karakter) untuk mencegah MySQL string truncation pada suite acak.
   - Full test suite run: **2270 passed, 4 skipped, 0 failed (6229 assertions)**.
   - Migrasi real dev database: `php artisan migrate` sukses mengeksekusi kedua migration baru.
   - Update roadmap di `PETA_PENGEMBANGAN.md` menandai Prioritas 1 SELESAI dan mencatat kandidat `TD-AKADEMIK-003`.

---

## 2. Keputusan Penting yang Diambil

1. **Enum KurikulumFramework Tanpa Model/Tabel**:
   - Sesuai Spec §2 Keputusan 1, `KurikulumFramework` adalah string-backed PHP enum (`k13`, `merdeka`). Tidak ada tabel `kurikulum_frameworks` dibuat untuk kesederhanaan dan ketegasan tipe data.
2. **Snapshot Kelas.kurikulum Terkunci (Immutable)**:
   - Nilai `Kelas.kurikulum` hanya di-resolve dan disimpan 1x saat `CreateKelasAction`. `UpdateKelasAction` tidak memiliki field `kurikulum` dan form edit kelas tidak menampilkannya sebagai field yang dapat dimanipulasi, menjaga konsistensi historis rapor.
3. **Penanganan NULL Composite Unique di MySQL**:
   - Menggunakan virtual generated columns `COALESCE(lembaga_id, 0)` dan `COALESCE(tingkat, '*')` dengan unique index `kurikulum_assignment_scope_unique` sehingga duplikasi platform catch-all (`lembaga_id IS NULL`, `tingkat IS NULL`) dicegah di level database engine.
4. **Validasi Silang Tingkat vs BentukPendidikan**:
   - Menggunakan `withValidator()` hook di `StoreKurikulumAssignmentRequest` dan `UpdateKurikulumAssignmentRequest` yang memanfaatkan helper `BentukPendidikan::validTingkatValues()` daripada membuat custom Rule class baru, konsisten dengan aturan arsitektur proyek.
5. **Seeder Test Count Alignment**:
   - Memperbarui file test seeder (`PermissionSeederTest`, `RoleSeederTest`, `RolePermissionSeederTest`) dari 145 ke 149 permission untuk mengikutsertakan permission `kurikulum-assignment.*` yang ditambahkan, mengikuti pola standar saat penambahan `fase-mapping.*`.

---

## 3. Hal yang Masih Perlu Direview Manusia / Claude

- **Kandidat Technical Debt `TD-AKADEMIK-003`**:
  - String `bentuk_pendidikan` masih di-hardcode di 4 lokasi lama (`StoreFaseDefaultMappingRequest.php`, `LembagaController.php`, `AcademicProfile.php`, `RaporPdfDataBuilder.php`). Enum `BentukPendidikan` baru (`app/Domains/Akademik/Enums/BentukPendidikan.php`) sudah siap dijadikan single source of truth jika hendak diretrofit di masa depan.
- **Git State**:
  - Branch: `akademik-v2`
  - Working tree: Clean (seluruh file telah di-commit)
  - Commits:
    - `854fcf77`: `feat(akademik): tambah enum KurikulumFramework dan BentukPendidikan`
    - `4ec38386`: `feat(akademik): tambah model dan tabel KurikulumAssignment`
    - `15c166f0`: `feat(akademik): tambah KurikulumAssignmentResolver dgn 4-level precedence`
    - `e29674f6`: `feat(akademik): snapshot Kelas.kurikulum otomatis saat create, terkunci setelahnya`
    - `991f923b`: `feat(akademik): CRUD admin Pengaturan Kurikulum (KurikulumAssignment)`
    - `5fdbf4bc`: `docs: tandai Prioritas 1 Roadmap Kurikulum Dinamis SELESAI, catat TD-AKADEMIK-003`
