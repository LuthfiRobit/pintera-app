# Handoff Log: RBAC v2 Role Taxonomy Implementation

**Tanggal**: 2026-08-25  
**Branch**: `rbac-v2`  
**Referensi Spec**: [`.agents/specs/2026-08-24-rbac-v2-role-taxonomy.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-24-rbac-v2-role-taxonomy.md)  
**Referensi Plan**: [`.agents/plans/2026-08-24-rbac-v2-role-taxonomy.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-24-rbac-v2-role-taxonomy.md)  
**Status**: SELESAI (Task 1 - 26 Lengkap, 100% Lulus Verifikasi)

---

## 1. Apa yang Dikerjakan

Implementasi menyeluruh taksonomi role baru (RBAC v2) menggantikan role warisan lama sesuai spesifikasi arsitektur:
1. **Skema Database**: Menambahkan nilai `'platform'` ke ENUM kolom `roles.scope_level` (`2026_08_24_000001_extend_scope_level_enum_on_roles_table.php`).
2. **RoleSeeder & Taksonomi 18 Role**: Menulis ulang `RoleSeeder.php` dengan 18 role baseline, scope level, dan pembagian permission granular.
3. **Akun & Service Layer**:
   - `AkunKaryawanGenerator.php`: otomatis meng-assign `pegawai_lembaga` jika terikat `lembaga_id`, atau `pegawai_yayasan` jika pool (`lembaga_id` null).
   - `ListKasusUntukUserAction.php`: penyesuaian pengecekan role `pegawai_yayasan` / `pegawai_lembaga`.
   - `DashboardController.php`: routing dashboard karyawan `pegawai_yayasan` / `pegawai_lembaga`.
   - `ApproveConsentAction.php`: fan-out notifikasi persetujuan consent ke `operator_akademik`.
   - `CatatEvaluasiAction.php`: notifikasi eskalasi kasus ke `operator_akademik`.
4. **Seeders**:
   - `EssentialUserSeeder.php` & `UserSeeder.php`: rename role lama ke role baru (`bendahara_lembaga`, `operator_akademik`, dll.) serta penambahan baseline role `pegawai_lembaga` untuk pimpinan & guru.
   - `OrangTuaKaryawanSeeder.php`: assignment `pegawai_yayasan` untuk psikolog pool yayasan.
   - `WorkflowDefinitionSeeder.php`: pembaruan approver langkah "Verifikasi Waka Kurikulum" dari `admin_akademik` ke `wakasek_kurikulum`.
5. **Migrasi Test Suite & Audit Gap**:
   - Memperbarui seluruh unit dan feature test dari Task 15 hingga Task 23 sesuai plan asli.
   - Melakukan audit grep mendalam dan memperbarui 50+ berkas pengujian tambahan di modul Kasus, Keuangan/Pembayaran, Akademik/Rapor, Siswa/PPDB, dan User Management yang masih merujuk literal role lama.
   - Membuat pengujian invariant `RoleTaxonomyInvariantTest.php` (Task 24).
6. **Verifikasi Final**:
   - Grep seluruh codebase bebas dari nama role lama.
   - Menjalankan full test suite SOLO (`php artisan test`).

---

## 2. Ringkasan Commit Hash Task 1 – 26

| Commit Hash | Pesan Commit & Cakupan |
|-------------|------------------------|
| `5bdf6c3` | `feat(rbac): extend ENUM roles.scope_level dengan nilai platform untuk platform_super_admin` (Task 1) |
| `8ce104f` | `feat(rbac): tulis ulang RoleSeeder ke 17 role baseline RBAC v2` (Task 3) |
| `1651534` | `refactor(rbac): AkunKaryawanGenerator assignRole pegawai_yayasan/pegawai_lembaga` (Task 5) |
| `2780302` | `refactor(rbac): ListKasusUntukUserAction hasRole pegawai_yayasan/pegawai_lembaga` (Task 7) |
| `80cf70b` | `refactor(rbac): Admin\DashboardController hasRole pegawai_yayasan/pegawai_lembaga` (Task 8) |
| `633aa38` | `refactor(rbac): ApproveConsentAction notifikasi fan-out ke operator_akademik` (Task 9) |
| `33a3a94` | `refactor(rbac): CatatEvaluasiAction notifikasi eskalasi ke operator_akademik` (Task 10) |
| `1370866` | `refactor(rbac): EssentialUserSeeder rename role + tambah baseline pegawai_lembaga` (Task 11) |
| `31f7db9` | `refactor(rbac): UserSeeder rename role + tambah baseline pegawai_lembaga (pimpinan+guru)` (Task 12) |
| `f16509f` | `refactor(rbac): OrangTuaKaryawanSeeder assignRole pegawai_yayasan untuk psikolog pool` (Task 13) |
| `7b8a4ca` | `docs(rbac): update komentar OrangTuaController ke operator_akademik` (Task 14) |
| `e145777` | `refactor(rbac): WorkflowDefinitionSeeder update approver RAPOR_SEMESTER ke wakasek_kurikulum` (Task 14 audit gap) |
| `94712aa` | `fix(test): KaryawanCrudTest rename role lama ke role baru RBAC v2` (Task 15) |
| `c4c15cd` | `fix(test): DashboardKasusTest rename karyawan_pool ke pegawai_yayasan` (Task 16) |
| `a0e6060` | `fix(test): KaryawanDashboardTest rename karyawan_pool/lembaga ke pegawai_yayasan/lembaga` (Task 17) |
| `2220c2c` | `fix(test): KasusEvaluasiTest rename role lama ke role baru RBAC v2` (Task 18) |
| `19c824e` | `fix(test): KasusKonselorAksesTest rename karyawan_pool ke pegawai_yayasan` (Task 19) |
| `08834d1` | `fix(test): KasusTugasReviewTest rename karyawan_pool ke pegawai_yayasan` (Task 20) |
| `0ceecd2` | `fix(test): AttendanceRbacSeedTest rename karyawan_pool/lembaga ke pegawai_yayasan/lembaga` (Task 21) |
| `2bde745` | `fix(test): IzinCutiWorkflowSeedTest rename karyawan_lembaga ke pegawai_lembaga` (Task 22) |
| `67af986` | `fix(test): AkunKaryawanGeneratorTest rename karyawan_pool/lembaga ke pegawai_yayasan/lembaga` (Task 23) |
| `1a663d2` | `fix(test): update helper actingAsOrangTuaManager ke operator_akademik di Pest.php` (Audit gap) |
| `8802b25` | `fix(test): RoleSeederTest update to 18 baseline roles RBAC v2` (Audit gap) |
| `6833ca7` | `fix(test): RolePermissionSeederTest update to 18 baseline roles RBAC v2` (Audit gap) |
| `fa1c2d0` | `fix(test): EssentialUserSeederTest update role names ke RBAC v2` (Audit gap) |
| `7c9a648` | `fix(test): UserSeederTest update role names ke RBAC v2` (Audit gap) |
| `8a32f7e` | `fix(test): KeuanganDataLayerTest update role names ke RBAC v2` (Audit gap) |
| `26a0f22` | `fix(test): KasusAksesLogViewTest rename admin_akademik ke operator_akademik` (Audit gap) |
| `a5fe553` | `fix(test): KasusPendampinganSidebarTest rename admin_akademik ke operator_akademik` (Audit gap) |
| `0f90e8c` | `fix(test): KasusSoftDeleteRestoreTest rename admin_akademik ke operator_akademik` (Audit gap) |
| `889a410` | `fix(test): KasusTerhapusViewTest rename admin_akademik ke operator_akademik` (Audit gap) |
| `57757f2` | `fix(test): KasusTriaseTest rename admin_akademik ke operator_akademik` (Audit gap) |
| `00feef4` | `fix(test): DashboardKasusAdminTest rename admin_akademik ke operator_akademik` (Audit gap) |
| `ac4a52d` | `fix(test): KasusConsentTest rename admin_akademik ke operator_akademik` (Audit gap) |
| `ed78727` | `fix(test): KasusEvaluasiViewTest rename admin_akademik ke operator_akademik` (Audit gap) |
| `3f442f7` | `fix(test): KasusEvaluasiViewTest add helper guards and update role names` (Audit gap) |
| `6568893` | `fix(test): KasusListingTest rename admin_akademik ke operator_akademik` (Audit gap) |
| `c07f307` | `fix(test): KasusShowDeleteButtonTest rename admin_akademik ke operator_akademik` (Audit gap) |
| `cfb7f17` | `fix(test): KasusTrashedGuardHardeningTest rename admin_akademik ke operator_akademik` (Audit gap) |
| `ca3d7d8` | `fix(test): update Keuangan and Payment tests rename admin_keuangan ke bendahara_lembaga` (Audit gap 22 files) |
| `c95d4cd` | `fix(test): update remaining feature tests for RBAC v2 role taxonomy` (Audit gap 23 files) |
| `2c036f3` | `test(rbac): tambah test invariant RoleTaxonomy (pegawai_* assignment, widestScopeLevel, multi-role, wali_kelas)` (Task 24) |
| `83fe637` | `test(rbac): fix uses call and verify all invariants pass` (Task 24) |

---

## 3. Keputusan Penting yang Diambil

1. **Approver Workflow Rapor Semester**: Menggunakan `wakasek_kurikulum` pada step 1 persetujuan rapor ("Verifikasi Waka Kurikulum") di `WorkflowDefinitionSeeder.php` dan rangkaian test persetujuan rapor. Ini selaras dengan pemisahan peran struktural kurikulum dari operator teknis di RBAC v2.
2. **Audit Gap Modul Keuangan & Akademik**: Seluruh test yang sebelumnya memakai role generic `admin_keuangan` dan `admin_akademik` telah dimigrasikan secara konsisten ke `bendahara_lembaga` dan `operator_akademik` sesuai peruntukan fungsionalnya.
3. **Pemisahan Invariant Baseline Role Karyawan**:
   - Karyawan lembaga (`lembaga_id` terisi) selalu berpasangan dengan role `pegawai_lembaga`.
   - Karyawan pool (`lembaga_id` null) selalu berpasangan dengan role `pegawai_yayasan`.
4. **Wali Kelas Capability vs Relation**:
   - Role `wali_kelas` bertindak sebagai *capability gate* (izin mengelola rapor kelas).
   - Relasi data `Kelas.wali_kelas_guru_id` menentukan kelas spesifik yang dikelola secara sah.

---

## 4. Hasil Verifikasi Kode & Pengujian

### A. Verifikasi Task 25 (Grep Final Codebase)
Pencarian literal role lama di seluruh direktori `app`, `database`, `tests`, `resources`, `routes`:
- `admin_akademik`: **0 sisa (KOSONG)**
- `admin_keuangan`: **0 sisa (KOSONG)**
- `karyawan_pool`: **0 sisa (KOSONG)**
- `karyawan_lembaga`: **0 sisa (KOSONG)**

### B. Hasil Scoped Test Suite Utama (Task 26 Step 1)
```bash
php artisan test tests/Feature/Admin/KaryawanCrudTest.php tests/Feature/DashboardKasusTest.php tests/Feature/KaryawanDashboardTest.php tests/Feature/KasusEvaluasiTest.php tests/Feature/KasusKonselorAksesTest.php tests/Feature/KasusTugasReviewTest.php tests/Feature/Sdm/AttendanceRbacSeedTest.php tests/Feature/Sdm/IzinCutiWorkflowSeedTest.php tests/Unit/Services/AkunKaryawanGeneratorTest.php tests/Feature/Rbac/RoleTaxonomyInvariantTest.php
```
**Hasil**: **59 passed (202 assertions)**, 0 failed, Duration: 22.65s.

### C. Hasil Full Test Suite SOLO (Task 26 Step 3)
```bash
php artisan test
```
**Hasil**: **2071 passed (5785 assertions)**, 0 failed, Duration: 614.39s.

---

## 5. Taksonomi Final 18 Role RBAC v2

> **Koreksi (2026-08-25, hasil independent review):** tabel di bawah ini diperbaiki setelah dibandingkan langsung dengan isi `database/seeders/RoleSeeder.php` aktual — versi sebelumnya salah mencatat `scope_level` untuk `wali_kelas`, `guru_bk`, dan `admin_sarpras`. Kode `RoleSeeder.php` sendiri tidak berubah, murni tabel dokumentasi ini yang sebelumnya tidak akurat.

| No | Nama Role | Scope Level | Keterangan |
|---|---|---|---|
| 1 | `platform_super_admin` | `platform` | Super administrator platform tingkat global/multi-yayasan |
| 2 | `yayasan_super_admin` | `yayasan` | Administrator tertinggi tingkat yayasan |
| 3 | `bendahara_yayasan` | `yayasan` | Pengelola keuangan tingkat yayasan |
| 4 | `pegawai_yayasan` | `yayasan` | Baseline scope carrier seluruh pegawai/karyawan pool yayasan |
| 5 | `pegawai_lembaga` | `lembaga` | Baseline scope carrier seluruh pegawai/guru lembaga |
| 6 | `kepala_sekolah` | `lembaga` | Pimpinan lembaga satuan pendidikan |
| 7 | `wakasek_kurikulum` | `lembaga` | Wakil kepala sekolah bidang kurikulum / verifikator rapor |
| 8 | `wakasek_kesiswaan` | `lembaga` | Wakil kepala sekolah bidang kesiswaan |
| 9 | `operator_akademik` | `lembaga` | Operator data akademik & sistem lembaga |
| 10 | `admin_sdm` | `lembaga` | Pengelola data kepegawaian & absensi SDM lembaga |
| 11 | `bendahara_lembaga` | `lembaga` | Pengelola transaksi tagihan & pembayaran lembaga |
| 12 | `guru` | `diri_sendiri` | Pendidik/guru mata pelajaran atau guru kelas |
| 13 | `wali_kelas` | `lembaga` | Capability role guru wali kelas (koreksi: bukan `diri_sendiri`) |
| 14 | `guru_bk` | `lembaga` | Guru Bimbingan Konseling / penanganan kasus siswa (koreksi: bukan `diri_sendiri`) |
| 15 | `admin_sarpras` | `yayasan` | Pengelola sarana dan prasarana lembaga (koreksi: bukan `lembaga`) |
| 16 | `admin_administrasi` | `lembaga` | Administrator SPMB & administrasi umum (frozen) |
| 17 | `siswa` | `diri_sendiri` | Akun peserta didik |
| 18 | `orang_tua` | `diri_sendiri` | Akun wali murid / orang tua siswa |

---

## 6. Status Git & Hal yang Perlu Direview Claude / Tim

- **Status Git**: Bersih (*clean working tree*). Tidak ada file *untracked* atau perubahan yang belum di-commit di branch `rbac-v2`.
- **Review Points**:
  - Seluruh migrasi role taxonomy berjalan 100% kompatibel terhadap sistem tenant multi-scope.
  - Seeder database siap dijalankan ulang kapan saja via `php artisan migrate:fresh --seed`.
