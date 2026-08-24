# RBAC v2 — Role Taxonomy & Migration Baseline Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menulis ulang `RoleSeeder.php` ke 17 role baseline sesuai taxonomy RBAC v2, memperbaiki seluruh consumer kode (aplikasi + seeder demo + test) yang masih pakai 4 nama role lama (`karyawan_pool`, `karyawan_lembaga`, `admin_akademik`, `admin_keuangan`), dan menambah baseline `pegawai_lembaga`/`pegawai_yayasan` ke akun-akun demo yang seharusnya memilikinya.

**Architecture:** 1 migration kecil (extend ENUM `scope_level`) → tulis ulang `RoleSeeder.php` → update seluruh consumer (app + seeder demo + test) berdasarkan grep segar (bukan daftar lama) → checkpoint zero-crash → test invariant baru → verifikasi akhir.

**Tech Stack:** Laravel 12, Spatie Permission, Pest.

## Global Constraints

- Baseline kode: commit `5d9e42f` di branch `rbac-v2`. Kalau isi file berbeda signifikan dari yang dikutip plan, STOP, laporkan ke user.
- **Data seeder demo buang-pakai** — `migrate:fresh --seed` adalah verifikasi normal, TIDAK perlu script migrasi data yang menyelamatkan assignment lama (lihat spec §11).
- **Hanya 4 nama role yang berubah**: `karyawan_pool`→`pegawai_yayasan`, `karyawan_lembaga`→`pegawai_lembaga`, `admin_akademik`→`operator_akademik`, `admin_keuangan`→`bendahara_lembaga`. Role lain (`kepala_sekolah`, `guru`, `admin_sarpras`, `admin_administrasi`, `admin_sdm`, `bendahara_yayasan`, `yayasan_super_admin`, `orang_tua`, `siswa`) namanya TIDAK berubah — JANGAN cari-ganti nama-nama ini.
- **Invariant baseline pegawai**: setiap akun staf lembaga (guru, kepala_sekolah, operator_akademik, bendahara_lembaga, admin_sdm, admin_sarpras-yang-lembaga-affiliated) WAJIB ditambah role `pegawai_lembaga` di SAMPING role fungsionalnya (multi-role, bukan pengganti) — lihat spec §6.1, §7.
- `RolePermissionAssignmentSeeder.php` dan `Admin\KaryawanController.php` **TIDAK perlu diedit** (dikonfirmasi lewat grep — tidak mengandung 4 string role yang berubah) — task terkait di plan ini murni verifikasi, BUKAN edit.
- Test scoped SEBELUM commit. Full suite HANYA task terakhir, izin eksplisit user dulu.
- JANGAN jalankan lebih dari satu proses `php artisan test`/`migrate:fresh` bersamaan.

---

## Task 1: Migration — Extend ENUM `roles.scope_level` dengan Nilai `platform`

**Files:**
- Create: `database/migrations/2026_08_24_140000_add_platform_scope_level_to_roles_table.php`

**Interfaces:**
- Produces: kolom `roles.scope_level` menerima 4 nilai (`yayasan`, `lembaga`, `diri_sendiri`, `platform`) — dipakai Task 3 untuk `platform_super_admin`.

**Alasan**: kolom `roles.scope_level` adalah MySQL ENUM ketat 3 nilai (`database/migrations/2026_07_12_073217_create_permission_tables.php:46`). Spec §5.1 melarang `platform_super_admin` diberi `scope_level=yayasan`, tapi tidak ada nilai lain yang valid tanpa migration ini. Ini BUKAN redesign mekanisme `widestScopeLevel()` (yang tetap TIDAK diubah, lihat Non-Goals §15) — murni menambah 1 nilai ENUM supaya role bisa disimpan sesuai larangan spec sendiri.

- [ ] **Step 1: Buat migration**

```php
<?php
// database/migrations/2026_08_24_140000_add_platform_scope_level_to_roles_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE roles MODIFY scope_level ENUM('yayasan', 'lembaga', 'diri_sendiri', 'platform') NOT NULL DEFAULT 'lembaga'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE roles MODIFY scope_level ENUM('yayasan', 'lembaga', 'diri_sendiri') NOT NULL DEFAULT 'lembaga'");
    }
};
```

- [ ] **Step 2: Jalankan migration**

```bash
php artisan migrate
```
Expected: migration `2026_08_24_140000_add_platform_scope_level_to_roles_table` sukses, tidak ada error.

- [ ] **Step 3: Verifikasi**

```bash
php artisan tinker --execute="echo DB::select(\"SHOW COLUMNS FROM roles WHERE Field = 'scope_level'\")[0]->Type;"
```
Expected: output menunjukkan 4 nilai termasuk `'platform'`.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_08_24_140000_add_platform_scope_level_to_roles_table.php
git commit -m "feat(rbac): extend ENUM roles.scope_level dengan nilai platform untuk platform_super_admin"
```

---

## Task 2: Grep Menyeluruh — Bangun Daftar Consumer Otoritatif

**Files:**
- Tidak ada file baru — task ini murni audit, hasilnya jadi rujukan Task 5-13, 15-23.

**JANGAN percaya daftar file di spec §10 tanpa verifikasi ulang** — itu hasil audit sebelumnya yang mungkin sudah basi. Pola ini sudah 2× terbukti punya blind spot di sub-project sebelumnya di repo ini.

- [ ] **Step 1: Grep 4 string role lama, scope SELURUH codebase**

```bash
grep -rln "karyawan_pool\|karyawan_lembaga" --include="*.php" app database tests resources/views routes
grep -rln "admin_akademik\|admin_keuangan" --include="*.php" app database tests resources/views routes
```

- [ ] **Step 2: Bandingkan hasil dengan daftar berikut (hasil grep 24 Agustus 2026, WAJIB dikonfirmasi ulang, bukan diasumsikan sama)**

`karyawan_pool`/`karyawan_lembaga`:
```
app/Domains/Kasus/Actions/Pengajuan/ListKasusUntukUserAction.php
app/Http/Controllers/Admin/DashboardController.php
app/Services/AkunKaryawanGenerator.php
database/seeders/OrangTuaKaryawanSeeder.php
tests/Feature/Admin/KaryawanCrudTest.php
tests/Feature/DashboardKasusTest.php
tests/Feature/KaryawanDashboardTest.php
tests/Feature/KasusEvaluasiTest.php
tests/Feature/KasusKonselorAksesTest.php
tests/Feature/KasusTugasReviewTest.php
tests/Feature/Sdm/AttendanceRbacSeedTest.php
tests/Feature/Sdm/IzinCutiWorkflowSeedTest.php
tests/Unit/Services/AkunKaryawanGeneratorTest.php
```

`admin_akademik`/`admin_keuangan`:
```
app/Domains/Kasus/Actions/Consent/ApproveConsentAction.php
app/Domains/Kasus/Actions/Evaluasi/CatatEvaluasiAction.php
database/seeders/EssentialUserSeeder.php
database/seeders/UserSeeder.php
database/seeders/RoleSeeder.php   <- akan ditulis ulang total di Task 3, bukan diedit manual
```

- [ ] **Step 3: Kalau ADA file selain di atas, STOP dan laporkan ke user sebelum lanjut** — daftar di plan ini jadi tidak lengkap, task 5-13/15-23 perlu ditambah.

- [ ] **Step 4: Kalau grep KURANG dari daftar di atas (file sudah berubah/dihapus sejak plan ditulis), catat file mana yang tidak ada lagi, JANGAN buat task untuk file yang sudah tidak ada.**

Tidak ada commit di task ini — murni audit gate.

---

## Task 3: Tulis Ulang `RoleSeeder.php` — 17 Role Baseline

**Files:**
- Modify: `database/seeders/RoleSeeder.php`

**Interfaces:**
- Consumes: `Permission` (dari `PermissionSeeder`, tidak berubah).
- Produces: 17 `Role` baseline dengan `scope_level` benar — dipakai SEMUA task berikutnya.

**PENTING**: role lama (`karyawan_pool`, `karyawan_lembaga`) **dihapus langsung dari seeder**, TIDAK dipertahankan untuk "masa transisi" (spec §11 — data buang-pakai, tidak ada deployment live yang butuh itu).

- [ ] **Step 1: Baca ulang file existing, konfirmasi 13 role sama persis dengan baseline (dikutip di §4 spec analisis awal)**

```bash
cat database/seeders/RoleSeeder.php
```

- [ ] **Step 2: Timpa seluruh isi file**

```php
<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        if (Permission::count() === 0) {
            (new PermissionSeeder())->run();
        }

        $roles = [
            // PLATFORM
            'platform_super_admin' => ['scope_level' => 'platform', 'is_protected' => true],

            // YAYASAN
            'yayasan_super_admin' => ['scope_level' => 'yayasan', 'is_protected' => true],
            'bendahara_yayasan' => ['scope_level' => 'yayasan', 'is_protected' => false],

            // ORGANIZATIONAL SCOPE / PEGAWAI (scope-carrier, lihat spec §5.5)
            'pegawai_lembaga' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'pegawai_yayasan' => ['scope_level' => 'yayasan', 'is_protected' => false],

            // FUNCTIONAL — LEMBAGA
            'kepala_sekolah' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'wakasek_kurikulum' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'wakasek_kesiswaan' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'operator_akademik' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'admin_sdm' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'bendahara_lembaga' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'guru' => ['scope_level' => 'diri_sendiri', 'is_protected' => false],
            'wali_kelas' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'guru_bk' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'admin_sarpras' => ['scope_level' => 'yayasan', 'is_protected' => false],

            // SPMB legacy (dibekukan, spec §9 — TIDAK disentuh RBAC v2 ini)
            'admin_administrasi' => ['scope_level' => 'lembaga', 'is_protected' => false],

            // PRINCIPAL
            'siswa' => ['scope_level' => 'diri_sendiri', 'is_protected' => false],
            'orang_tua' => ['scope_level' => 'diri_sendiri', 'is_protected' => false],
        ];

        foreach ($roles as $name => $attributes) {
            $role = Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                $attributes
            );

            if ($name === 'yayasan_super_admin') {
                $role->givePermissionTo(Permission::all());
            }

            if ($name === 'admin_administrasi') {
                $role->givePermissionTo([
                    'jenis-tes.view', 'jenis-tes.create', 'jenis-tes.edit', 'jenis-tes.delete',
                    'gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit',
                    'jalur-ppdb.view', 'jalur-ppdb.create', 'jalur-ppdb.edit',
                    'formulir-field.create', 'formulir-field.delete',
                    'dokumen-syarat.create', 'dokumen-syarat.delete',
                    'seleksi.create', 'seleksi.delete',
                    'spmb-konfigurasi.duplikasi',
                    'spmb-pendaftaran.view', 'spmb-pendaftaran.verifikasi-dokumen', 'spmb-pendaftaran.nilai-seleksi',
                ]);
            }

            if ($name === 'bendahara_lembaga') {
                $role->givePermissionTo([
                    'jenis-tagihan.view', 'jenis-tagihan.create', 'jenis-tagihan.edit', 'jenis-tagihan.delete',
                    'tagihan.view', 'tagihan.buat-susulan',
                    'pembayaran.view', 'pembayaran.verifikasi', 'pembayaran.catat-manual', 'pembayaran.virtual-account',
                    'cicilan.kelola',
                    'spmb-pendaftaran.view',
                ]);
            }

            if ($name === 'kepala_sekolah') {
                $role->givePermissionTo([
                    'spmb-pendaftaran.view', 'spmb-pendaftaran.verifikasi-dokumen', 'spmb-pendaftaran.nilai-seleksi',
                    'spmb-pendaftaran.tetapkan-keputusan', 'spmb-pendaftaran.terbitkan-sk',
                    'tagihan.view',
                    'komponen-penilaian.kelola', 'rapor.view', 'rapor.approve',
                    'kenaikan-kelas.kelola',
                    'rpp.view', 'rpp.verify',
                    'kehadiran-sdm.izin.approve',
                ]);
            }

            if ($name === 'guru') {
                $role->givePermissionTo([
                    'presensi.isi', 'asesmen.kelola', 'komponen-penilaian.kelola-sendiri', 'rapor.input-wali', 'rapor.ajukan',
                    'kasus.ajukan', 'kasus.view',
                    'rpp.view', 'rpp.kelola',
                    'kehadiran-sdm.lihat-qr-sendiri',
                    'kehadiran-sdm.izin.ajukan', 'kehadiran-sdm.izin.lihat-sendiri',
                ]);
            }

            if ($name === 'wali_kelas') {
                // Capability gate murni -- assignment kelas mana yang dikelola tetap dari
                // Kelas.wali_kelas_guru_id (relasi domain), BUKAN dari permission ini.
                // Lihat spec §8. Untuk sekarang belum ada permission khusus wali_kelas
                // yang berbeda dari guru biasa -- capability baru ditambah begitu ada
                // fitur nyata yang butuh membedakannya (mis. rapor.input-wali sudah
                // dipegang 'guru' secara umum, cukup untuk saat ini).
            }

            if ($name === 'guru_bk') {
                $role->givePermissionTo([
                    'kasus.view', 'kasus.triase',
                ]);
            }

            if ($name === 'wakasek_kurikulum') {
                $role->givePermissionTo([
                    'komponen-penilaian.kelola', 'rapor.view', 'rapor.verify',
                    'kenaikan-kelas.kelola',
                    'rpp.view', 'rpp.verify',
                    'jadwal-pelajaran.kelola',
                ]);
            }

            if ($name === 'wakasek_kesiswaan') {
                $role->givePermissionTo([
                    'kasus.view', 'kasus.triase', 'kasus.lihat-log-akses',
                ]);
            }

            if ($name === 'operator_akademik') {
                $role->givePermissionTo([
                    'kelas.view', 'kelas.create', 'kelas.edit',
                    'mata-pelajaran.view', 'mata-pelajaran.create', 'mata-pelajaran.edit',
                    'siswa.view', 'siswa.create', 'siswa.edit', 'siswa.spmb-daftar', 'siswa.import',
                    'orang-tua.view', 'orang-tua.create', 'orang-tua.edit',
                    'karyawan.view', 'karyawan.create', 'karyawan.edit',
                    'kasus.view', 'kasus.triase', 'kasus.lihat-log-akses', 'kasus.hapus', 'kasus.pulihkan',
                    'whatsapp-template.edit',
                    'tahun-ajaran.view', 'tahun-ajaran.create', 'tahun-ajaran.activate',
                    'semester.create', 'semester.activate',
                    'pola-jam.view', 'pola-jam.create', 'pola-jam.edit', 'pola-jam.delete',
                    'jam-pelajaran.create', 'jam-pelajaran.edit', 'jam-pelajaran.delete',
                    'jadwal-pelajaran.kelola',
                    'kalender-akademik.view', 'kalender-akademik.kelola',
                    'pengaturan-akademik.kelola',
                    'komponen-penilaian.kelola',
                    'rapor.view', 'rapor.verify',
                    'kenaikan-kelas.kelola',
                    'rpp.view', 'rpp.kelola', 'rpp.verify',
                ]);
            }

            if ($name === 'orang_tua') {
                $role->givePermissionTo([
                    'kasus.ajukan', 'kasus.view', 'kasus.consent',
                    'keuangan.akses',
                ]);
            }

            if ($name === 'siswa') {
                $role->givePermissionTo(['kasus.view']);
            }

            if (in_array($name, ['pegawai_lembaga', 'pegawai_yayasan'], true)) {
                $role->givePermissionTo(['kasus.view', 'kehadiran-sdm.lihat-qr-sendiri', 'kehadiran-sdm.izin.ajukan', 'kehadiran-sdm.izin.lihat-sendiri']);
            }

            if ($name === 'admin_sdm') {
                $role->givePermissionTo([
                    'kehadiran-sdm.view', 'kehadiran-sdm.catat', 'kehadiran-sdm.kelola-konfigurasi', 'kehadiran-sdm.lihat-qr-sendiri',
                    'kehadiran-sdm.izin.approve',
                ]);
            }
        }
    }
}
```

Catatan perubahan dari baseline: `admin_akademik`→`operator_akademik` (nama role DAN blok permission-nya, isi permission SAMA PERSIS — zero-behavior-change untuk permission bundle-nya, hanya nama role berubah). `admin_keuangan`→`bendahara_lembaga` (idem). `karyawan_pool`/`karyawan_lembaga`→`pegawai_lembaga`/`pegawai_yayasan` (nama berubah, blok permission `in_array` disesuaikan). Role BARU ditambahkan: `platform_super_admin` (scope `platform`, TANPA permission — di luar cakupan spec, lihat Non-Goals §15), `wakasek_kurikulum`, `wakasek_kesiswaan`, `wali_kelas` (capability kosong, lihat komentar di kode), `guru_bk`.

- [ ] **Step 3: Verifikasi syntax**

```bash
php -l database/seeders/RoleSeeder.php
```

- [ ] **Step 4: Commit**

```bash
git add database/seeders/RoleSeeder.php
git commit -m "feat(rbac): tulis ulang RoleSeeder ke 17 role baseline RBAC v2"
```

---

## Task 4: Verifikasi `RolePermissionAssignmentSeeder.php` — Tidak Perlu Diedit

**Files:**
- Tidak ada file diedit — task ini murni verifikasi gate.

- [ ] **Step 1: Grep konfirmasi file ini TIDAK mengandung 4 string role yang berubah**

```bash
grep -n "karyawan_pool\|karyawan_lembaga\|admin_akademik\|admin_keuangan" database/seeders/RolePermissionAssignmentSeeder.php
```
Expected: KOSONG. File ini cuma referensi `admin_sarpras`, `admin_administrasi`, `kepala_sekolah`, `bendahara_yayasan`, `yayasan_super_admin` — semua nama TIDAK berubah di RBAC v2 ini.

- [ ] **Step 2: Kalau grep TIDAK kosong (baseline sudah berubah sejak plan ditulis), STOP dan laporkan ke user — mungkin perlu task edit tambahan.**

Tidak ada commit di task ini.

---

## Task 5: `AkunKaryawanGenerator.php`

**Files:**
- Modify: `app/Services/AkunKaryawanGenerator.php`

**Interfaces:**
- Consumes: `Role` (Task 3, `pegawai_yayasan`/`pegawai_lembaga`).

- [ ] **Step 1: Baca ulang file, konfirmasi baris `assignRole()` sama dengan baseline**

```bash
grep -n "assignRole" app/Services/AkunKaryawanGenerator.php
```
Expected baseline: `$user->assignRole($lembagaId === null ? 'karyawan_pool' : 'karyawan_lembaga');`

- [ ] **Step 2: Ganti baris tersebut**

Ganti:
```php
            $user->assignRole($lembagaId === null ? 'karyawan_pool' : 'karyawan_lembaga');
```
Menjadi:
```php
            $user->assignRole($lembagaId === null ? 'pegawai_yayasan' : 'pegawai_lembaga');
```

Logic `is_pool`/`lembagaId` TIDAK disentuh — HANYA nama role yang berubah (spec §5.5).

- [ ] **Step 3: Verifikasi syntax**

```bash
php -l app/Services/AkunKaryawanGenerator.php
```

- [ ] **Step 4: Jalankan test scoped**

```bash
php artisan test tests/Unit/Services/AkunKaryawanGeneratorTest.php
```
Expected: kemungkinan GAGAL di sini karena test-nya sendiri belum diupdate (lihat Task 23) — itu WAJAR, jangan panik, cukup catat, lanjut ke commit file ini.

- [ ] **Step 5: Commit**

```bash
git add app/Services/AkunKaryawanGenerator.php
git commit -m "refactor(rbac): AkunKaryawanGenerator assignRole pegawai_yayasan/pegawai_lembaga"
```

---

## Task 6: Verifikasi `Admin\KaryawanController.php` — Tidak Perlu Diedit

**Files:**
- Tidak ada file diedit — task ini murni verifikasi gate.

- [ ] **Step 1: Grep konfirmasi**

```bash
grep -n "karyawan_pool\|karyawan_lembaga\|admin_akademik\|admin_keuangan" app/Http/Controllers/Admin/KaryawanController.php
```
Expected: KOSONG. Guard `is_pool`+`hasRole('yayasan_super_admin')` (nama TIDAK berubah) TIDAK perlu diedit.

- [ ] **Step 2: Kalau grep TIDAK kosong, STOP dan laporkan ke user.**

Tidak ada commit di task ini.

---

## Task 7: `ListKasusUntukUserAction.php`

**Files:**
- Modify: `app/Domains/Kasus/Actions/Pengajuan/ListKasusUntukUserAction.php`

- [ ] **Step 1: Baca ulang file, konfirmasi baris sama dengan baseline**

```bash
grep -n "karyawan_pool\|karyawan_lembaga" app/Domains/Kasus/Actions/Pengajuan/ListKasusUntukUserAction.php
```
Expected baseline: `} elseif ($user->hasRole('karyawan_pool') || $user->hasRole('karyawan_lembaga')) {`

- [ ] **Step 2: Ganti baris tersebut**

Ganti:
```php
        } elseif ($user->hasRole('karyawan_pool') || $user->hasRole('karyawan_lembaga')) {
```
Menjadi:
```php
        } elseif ($user->hasRole('pegawai_yayasan') || $user->hasRole('pegawai_lembaga')) {
```

- [ ] **Step 3: Verifikasi syntax**

```bash
php -l app/Domains/Kasus/Actions/Pengajuan/ListKasusUntukUserAction.php
```

- [ ] **Step 4: Commit**

```bash
git add app/Domains/Kasus/Actions/Pengajuan/ListKasusUntukUserAction.php
git commit -m "refactor(rbac): ListKasusUntukUserAction hasRole pegawai_yayasan/pegawai_lembaga"
```

---

## Task 8: `Admin\DashboardController.php`

**Files:**
- Modify: `app/Http/Controllers/Admin/DashboardController.php`

- [ ] **Step 1: Baca ulang file, konfirmasi baris sama dengan baseline**

```bash
grep -n "karyawan_pool\|karyawan_lembaga" app/Http/Controllers/Admin/DashboardController.php
```
Expected baseline: `if ($user->hasRole('karyawan_pool') || $user->hasRole('karyawan_lembaga')) {`

- [ ] **Step 2: Ganti baris tersebut**

Ganti:
```php
        if ($user->hasRole('karyawan_pool') || $user->hasRole('karyawan_lembaga')) {
```
Menjadi:
```php
        if ($user->hasRole('pegawai_yayasan') || $user->hasRole('pegawai_lembaga')) {
```

- [ ] **Step 3: Verifikasi syntax**

```bash
php -l app/Http/Controllers/Admin/DashboardController.php
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Admin/DashboardController.php
git commit -m "refactor(rbac): Admin\DashboardController hasRole pegawai_yayasan/pegawai_lembaga"
```

---

## Task 9: `ApproveConsentAction.php`

**Files:**
- Modify: `app/Domains/Kasus/Actions/Consent/ApproveConsentAction.php`

**Keputusan**: `admin_akademik` di sini dipakai untuk notifikasi fan-out ke "admin lembaga" — diganti `operator_akademik` (penerus langsung permission bundle `admin_akademik` per §6.2), BUKAN dihapus/dikosongkan.

- [ ] **Step 1: Baca ulang file, konfirmasi baris sama dengan baseline**

```bash
grep -n "admin_akademik" app/Domains/Kasus/Actions/Consent/ApproveConsentAction.php
```
Expected baseline (2 tempat): komentar `// 'admin_akademik' role hasn't been created...` dan `->whereHas('roles', fn ($query) => $query->where('name', 'admin_akademik'))`.

- [ ] **Step 2: Ganti KEDUA kemunculan**

Ganti:
```php
        // Avoid Spatie's ->role() query scope here: it throws RoleDoesNotExist when the
        // 'admin_akademik' role hasn't been created yet in the current guard (e.g. in tests
        // that don't need lembaga-admin notifications). whereHas() degrades to zero matches
        // instead, which is the correct behavior for a best-effort notification fan-out.
        $lembagaAdmins = User::withoutGlobalScope(TenantScope::class)
            ->whereHas('roles', fn ($query) => $query->where('name', 'admin_akademik'))
```
Menjadi:
```php
        // Avoid Spatie's ->role() query scope here: it throws RoleDoesNotExist when the
        // 'operator_akademik' role hasn't been created yet in the current guard (e.g. in tests
        // that don't need lembaga-admin notifications). whereHas() degrades to zero matches
        // instead, which is the correct behavior for a best-effort notification fan-out.
        $lembagaAdmins = User::withoutGlobalScope(TenantScope::class)
            ->whereHas('roles', fn ($query) => $query->where('name', 'operator_akademik'))
```

- [ ] **Step 3: Verifikasi syntax**

```bash
php -l app/Domains/Kasus/Actions/Consent/ApproveConsentAction.php
```

- [ ] **Step 4: Commit**

```bash
git add app/Domains/Kasus/Actions/Consent/ApproveConsentAction.php
git commit -m "refactor(rbac): ApproveConsentAction notifikasi fan-out ke operator_akademik"
```

---

## Task 10: `CatatEvaluasiAction.php`

**Files:**
- Modify: `app/Domains/Kasus/Actions/Evaluasi/CatatEvaluasiAction.php`

- [ ] **Step 1: Baca ulang file, konfirmasi baris sama dengan baseline**

```bash
grep -n "admin_akademik" app/Domains/Kasus/Actions/Evaluasi/CatatEvaluasiAction.php
```
Expected baseline: `->whereHas('roles', fn ($q) => $q->where('name', 'admin_akademik'))`

- [ ] **Step 2: Ganti**

Ganti:
```php
            $admins = User::withoutGlobalScope(TenantScope::class)
                ->whereHas('roles', fn ($q) => $q->where('name', 'admin_akademik'))
```
Menjadi:
```php
            $admins = User::withoutGlobalScope(TenantScope::class)
                ->whereHas('roles', fn ($q) => $q->where('name', 'operator_akademik'))
```

- [ ] **Step 3: Verifikasi syntax**

```bash
php -l app/Domains/Kasus/Actions/Evaluasi/CatatEvaluasiAction.php
```

- [ ] **Step 4: Commit**

```bash
git add app/Domains/Kasus/Actions/Evaluasi/CatatEvaluasiAction.php
git commit -m "refactor(rbac): CatatEvaluasiAction notifikasi eskalasi ke operator_akademik"
```

---

## Task 11: `EssentialUserSeeder.php` — Rename Role + Tambah Baseline `pegawai_lembaga`

**Files:**
- Modify: `database/seeders/EssentialUserSeeder.php`

**Keputusan (temuan verifikasi langsung)**: akun `sarpras.sd@demo.test` (`admin_sarpras`) di file ini PUNYA `lembaga_id` terisi (bukan pool) — menjawab "pending konfirmasi" spec §6.1: dapat `pegawai_lembaga`, BUKAN `pegawai_yayasan`. Seluruh akun `$akunLembagaScoped` di file ini (kepsek, adm, keuangan, kurikulum, guru, sarpras) sama-sama lembaga-affiliated → SEMUA dapat tambahan `pegawai_lembaga`.

- [ ] **Step 1: Baca ulang file, konfirmasi struktur sama dengan baseline**

```bash
cat database/seeders/EssentialUserSeeder.php
```

- [ ] **Step 2: Ganti 2 role string di array `$akunLembagaScoped`**

Ganti:
```php
        $akunLembagaScoped = [
            'kepsek.sd@demo.test' => ['name' => 'Abdullah, M.Pd.', 'role' => 'kepala_sekolah'],
            'adm.sd@demo.test' => ['name' => 'Lukman, S.Kom.', 'role' => 'admin_administrasi'],
            'keuangan.sd@demo.test' => ['name' => 'Hasan, S.E.', 'role' => 'admin_keuangan'],
            'kurikulum.sd@demo.test' => ['name' => 'Kurikulum (Contoh)', 'role' => 'admin_akademik'],
            'guru.sd1@demo.test' => ['name' => 'Sari Wulandari, S.Pd.', 'role' => 'guru'],
            'sarpras.sd@demo.test' => ['name' => 'Sarpras (Contoh)', 'role' => 'admin_sarpras'],
        ];

        foreach ($akunLembagaScoped as $email => $data) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $data['name'],
                    'password' => 'password',
                    'lembaga_id' => $lembaga->id,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );
            $user->update(['lembaga_id' => $lembaga->id]);
            $user->assignRole($data['role']);
        }
```
Menjadi:
```php
        $akunLembagaScoped = [
            'kepsek.sd@demo.test' => ['name' => 'Abdullah, M.Pd.', 'role' => 'kepala_sekolah'],
            'adm.sd@demo.test' => ['name' => 'Lukman, S.Kom.', 'role' => 'admin_administrasi'],
            'keuangan.sd@demo.test' => ['name' => 'Hasan, S.E.', 'role' => 'bendahara_lembaga'],
            'kurikulum.sd@demo.test' => ['name' => 'Kurikulum (Contoh)', 'role' => 'operator_akademik'],
            'guru.sd1@demo.test' => ['name' => 'Sari Wulandari, S.Pd.', 'role' => 'guru'],
            'sarpras.sd@demo.test' => ['name' => 'Sarpras (Contoh)', 'role' => 'admin_sarpras'],
        ];

        foreach ($akunLembagaScoped as $email => $data) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $data['name'],
                    'password' => 'password',
                    'lembaga_id' => $lembaga->id,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );
            $user->update(['lembaga_id' => $lembaga->id]);
            $user->assignRole($data['role']);
            // Baseline scope-carrier (RBAC v2 spec §6.1, §7) -- semua akun di array ini
            // lembaga-affiliated (lembaga_id terisi), jadi pegawai_lembaga, bukan
            // pegawai_yayasan.
            $user->assignRole('pegawai_lembaga');
        }
```

Catatan: `adm.sd@demo.test` (`admin_administrasi`) TIDAK ditambah `pegawai_lembaga` per keputusan eksplisit — cek dulu apakah spec menyebut ini. **Verifikasi**: spec §9 (SPMB Freeze) tidak melarang penambahan baseline `pegawai_lembaga` ke `admin_administrasi` (hanya melarang perubahan PERMISSION `spmb.*`) — jadi baris di atas yang menambahkan `pegawai_lembaga` ke SEMUA akun (termasuk `adm.sd@demo.test`) sudah benar, JANGAN dikecualikan.

- [ ] **Step 3: Update baris warning (kosmetik, opsional tapi disarankan untuk akurasi)**

Ganti:
```php
            $this->command?->warn('Belum ada Lembaga -- akun kepala_sekolah/admin_administrasi/admin_keuangan/guru dilewati.');
```
Menjadi:
```php
            $this->command?->warn('Belum ada Lembaga -- akun kepala_sekolah/admin_administrasi/bendahara_lembaga/guru dilewati.');
```

- [ ] **Step 4: Verifikasi syntax**

```bash
php -l database/seeders/EssentialUserSeeder.php
```

- [ ] **Step 5: Commit**

```bash
git add database/seeders/EssentialUserSeeder.php
git commit -m "refactor(rbac): EssentialUserSeeder rename role + tambah baseline pegawai_lembaga"
```

---

## Task 12: `UserSeeder.php` — Rename Role + Tambah Baseline `pegawai_lembaga`

**Files:**
- Modify: `database/seeders/UserSeeder.php`

- [ ] **Step 1: Baca ulang file, konfirmasi struktur sama dengan baseline**

```bash
grep -n "admin_keuangan\|assignRole" database/seeders/UserSeeder.php
```

- [ ] **Step 2: Ganti role string di array pimpinan**

Ganti:
```php
            ['name' => 'Hasan, S.E.', 'email' => 'keuangan.sd@demo.test', 'role' => 'admin_keuangan'],
```
Menjadi:
```php
            ['name' => 'Hasan, S.E.', 'email' => 'keuangan.sd@demo.test', 'role' => 'bendahara_lembaga'],
```

- [ ] **Step 3: Tambah `assignRole('pegawai_lembaga')` di method `seedStaf()` — untuk pimpinan DAN guru (keduanya lembaga-affiliated)**

Baca method `seedStaf()`, cari 2 baris `$user->assignRole(...)` (satu untuk `$data['role']` di loop pimpinan, satu untuk `'guru'` literal di loop guru). Tambahkan `$user->assignRole('pegawai_lembaga');` PERSIS SETELAH masing-masing baris `assignRole` tersebut (2 titik penambahan dalam method yang sama).

Contoh hasil akhir method (sesuaikan dengan isi aktual hasil Step 1 kalau berbeda):
```php
    private function seedStaf(Lembaga $lembaga, array $pimpinan, array $guruList): void
    {
        foreach ($pimpinan as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => 'password',
                    'lembaga_id' => $lembaga->id,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );
            $user->update(['name' => $data['name'], 'lembaga_id' => $lembaga->id]);
            $user->assignRole($data['role']);
            $user->assignRole('pegawai_lembaga');
        }

        foreach ($guruList as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => 'password',
                    'lembaga_id' => $lembaga->id,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );
            $user->update(['name' => $data['name'], 'lembaga_id' => $lembaga->id]);
            $user->assignRole('guru');
            $user->assignRole('pegawai_lembaga');
        }
    }
```

- [ ] **Step 4: Verifikasi syntax**

```bash
php -l database/seeders/UserSeeder.php
```

- [ ] **Step 5: Commit**

```bash
git add database/seeders/UserSeeder.php
git commit -m "refactor(rbac): UserSeeder rename role + tambah baseline pegawai_lembaga (pimpinan+guru)"
```

---

## Task 13: `OrangTuaKaryawanSeeder.php`

**Files:**
- Modify: `database/seeders/OrangTuaKaryawanSeeder.php`

- [ ] **Step 1: Baca ulang file, konfirmasi baris sama dengan baseline**

```bash
grep -n "karyawan_pool" database/seeders/OrangTuaKaryawanSeeder.php
```
Expected baseline: `$user->assignRole('karyawan_pool');` (di method `seedKaryawanPool()`, akun psikolog pool `lembaga_id: null`).

- [ ] **Step 2: Ganti**

Ganti:
```php
        $user->assignRole('karyawan_pool');
```
Menjadi:
```php
        $user->assignRole('pegawai_yayasan');
```

Akun ini `lembaga_id: null` (genuinely pool) — TIDAK butuh penambahan role lain, `pegawai_yayasan` saja sudah benar sesuai invariant §5.5.

- [ ] **Step 3: Verifikasi syntax**

```bash
php -l database/seeders/OrangTuaKaryawanSeeder.php
```

- [ ] **Step 4: Commit**

```bash
git add database/seeders/OrangTuaKaryawanSeeder.php
git commit -m "refactor(rbac): OrangTuaKaryawanSeeder assignRole pegawai_yayasan untuk psikolog pool"
```

---

## Task 14: Verifikasi Checkpoint — Zero-Crash

**Files:**
- Tidak ada file baru — task ini murni verifikasi gate.

- [ ] **Step 1: Jalankan migrate:fresh --seed, tangkap SELURUH output**

```bash
php artisan migrate:fresh --seed 2>&1 | tail -100
```
Expected: TIDAK ADA exception (khususnya `RoleDoesNotExist` dari Spatie kalau ada role yang lupa di-assign/rename). Warning biasa (mis. dari seeder guru lain yang mencari user tertentu) BOLEH muncul, itu bukan masalah RBAC.

- [ ] **Step 2: Verifikasi role baseline via tinker**

```bash
php artisan tinker --execute="
echo 'Total role: '.\App\Models\Role::count().PHP_EOL;
echo 'platform_super_admin scope: '.(\App\Models\Role::where('name','platform_super_admin')->first()?->scope_level ?? 'MISSING').PHP_EOL;
echo 'kepsek.sd pegawai_lembaga: '.(\App\Models\User::where('email','kepsek.sd@demo.test')->first()?->hasRole('pegawai_lembaga') ? 'YES' : 'NO').PHP_EOL;
echo 'sarpras.sd pegawai_lembaga: '.(\App\Models\User::where('email','sarpras.sd@demo.test')->first()?->hasRole('pegawai_lembaga') ? 'YES' : 'NO').PHP_EOL;
echo 'guru.sd1 pegawai_lembaga: '.(\App\Models\User::where('email','guru.sd1@demo.test')->first()?->hasRole('pegawai_lembaga') ? 'YES' : 'NO').PHP_EOL;
"
```
Expected: `Total role`=17, `platform_super_admin scope`=`platform`, ketiga cek `pegawai_lembaga`=`YES`.

- [ ] **Step 3: Kalau ada temuan tidak sesuai, STOP dan perbaiki sebelum lanjut Task 15.**

Tidak ada commit di task ini.

---

## Task 15: `tests/Feature/Admin/KaryawanCrudTest.php`

**Files:**
- Modify: `tests/Feature/Admin/KaryawanCrudTest.php`

- [ ] **Step 1: Baca ulang file, hitung kemunculan (baseline: 6)**

```bash
grep -c "karyawan_pool\|karyawan_lembaga" tests/Feature/Admin/KaryawanCrudTest.php
```

- [ ] **Step 2: Ganti SEMUA kemunculan `karyawan_pool`→`pegawai_yayasan`, `karyawan_lembaga`→`pegawai_lembaga` (replace_all, HANYA nama role, JANGAN ubah logic test lain)**

- [ ] **Step 3: Verifikasi tidak ada sisa**

```bash
grep -c "karyawan_pool\|karyawan_lembaga" tests/Feature/Admin/KaryawanCrudTest.php
```
Expected: 0.

- [ ] **Step 4: Jalankan test scoped**

```bash
php artisan test tests/Feature/Admin/KaryawanCrudTest.php
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Admin/KaryawanCrudTest.php
git commit -m "fix(test): KaryawanCrudTest rename karyawan_pool/lembaga ke pegawai_yayasan/lembaga"
```

---

## Task 16: `tests/Feature/DashboardKasusTest.php`

**Files:**
- Modify: `tests/Feature/DashboardKasusTest.php`

- [ ] **Step 1-5**: Pola identik Task 15 (baseline: 7 kemunculan).

```bash
grep -c "karyawan_pool\|karyawan_lembaga" tests/Feature/DashboardKasusTest.php
```
Ganti semua, verifikasi 0 sisa, jalankan `php artisan test tests/Feature/DashboardKasusTest.php`, commit:
```bash
git add tests/Feature/DashboardKasusTest.php
git commit -m "fix(test): DashboardKasusTest rename karyawan_pool/lembaga ke pegawai_yayasan/lembaga"
```

---

## Task 17: `tests/Feature/KaryawanDashboardTest.php`

**Files:**
- Modify: `tests/Feature/KaryawanDashboardTest.php`

Pola identik Task 15 (baseline: 2 kemunculan).

```bash
grep -c "karyawan_pool\|karyawan_lembaga" tests/Feature/KaryawanDashboardTest.php
```
Ganti semua, verifikasi 0 sisa, jalankan `php artisan test tests/Feature/KaryawanDashboardTest.php`, commit:
```bash
git add tests/Feature/KaryawanDashboardTest.php
git commit -m "fix(test): KaryawanDashboardTest rename karyawan_pool/lembaga ke pegawai_yayasan/lembaga"
```

---

## Task 18: `tests/Feature/KasusEvaluasiTest.php`

**Files:**
- Modify: `tests/Feature/KasusEvaluasiTest.php`

Pola identik Task 15 (baseline: 4 kemunculan `karyawan_pool`/`karyawan_lembaga`). **Cek juga apakah file ini punya referensi `admin_akademik`** (kemungkinan besar TIDAK, tapi verifikasi karena nama file "KasusEvaluasi" berkaitan dengan `CatatEvaluasiAction.php` Task 10) — kalau ADA, ganti juga ke `operator_akademik`.

```bash
grep -c "karyawan_pool\|karyawan_lembaga\|admin_akademik" tests/Feature/KasusEvaluasiTest.php
```
Ganti semua (termasuk `admin_akademik`→`operator_akademik` kalau ada), verifikasi 0 sisa, jalankan `php artisan test tests/Feature/KasusEvaluasiTest.php`, commit:
```bash
git add tests/Feature/KasusEvaluasiTest.php
git commit -m "fix(test): KasusEvaluasiTest rename role lama ke role baru RBAC v2"
```

---

## Task 19: `tests/Feature/KasusKonselorAksesTest.php`

**Files:**
- Modify: `tests/Feature/KasusKonselorAksesTest.php`

Pola identik Task 15 (baseline: 5 kemunculan).

```bash
grep -c "karyawan_pool\|karyawan_lembaga" tests/Feature/KasusKonselorAksesTest.php
```
Ganti semua, verifikasi 0 sisa, jalankan `php artisan test tests/Feature/KasusKonselorAksesTest.php`, commit:
```bash
git add tests/Feature/KasusKonselorAksesTest.php
git commit -m "fix(test): KasusKonselorAksesTest rename karyawan_pool/lembaga ke pegawai_yayasan/lembaga"
```

---

## Task 20: `tests/Feature/KasusTugasReviewTest.php`

**Files:**
- Modify: `tests/Feature/KasusTugasReviewTest.php`

Pola identik Task 15 (baseline: 3 kemunculan).

```bash
grep -c "karyawan_pool\|karyawan_lembaga" tests/Feature/KasusTugasReviewTest.php
```
Ganti semua, verifikasi 0 sisa, jalankan `php artisan test tests/Feature/KasusTugasReviewTest.php`, commit:
```bash
git add tests/Feature/KasusTugasReviewTest.php
git commit -m "fix(test): KasusTugasReviewTest rename karyawan_pool/lembaga ke pegawai_yayasan/lembaga"
```

---

## Task 21: `tests/Feature/Sdm/AttendanceRbacSeedTest.php`

**Files:**
- Modify: `tests/Feature/Sdm/AttendanceRbacSeedTest.php`

Pola identik Task 15 (baseline: 1 kemunculan).

```bash
grep -c "karyawan_pool\|karyawan_lembaga" tests/Feature/Sdm/AttendanceRbacSeedTest.php
```
Ganti, verifikasi 0 sisa, jalankan `php artisan test tests/Feature/Sdm/AttendanceRbacSeedTest.php`, commit:
```bash
git add tests/Feature/Sdm/AttendanceRbacSeedTest.php
git commit -m "fix(test): AttendanceRbacSeedTest rename karyawan_pool/lembaga ke pegawai_yayasan/lembaga"
```

---

## Task 22: `tests/Feature/Sdm/IzinCutiWorkflowSeedTest.php`

**Files:**
- Modify: `tests/Feature/Sdm/IzinCutiWorkflowSeedTest.php`

Pola identik Task 15 (baseline: 1 kemunculan).

```bash
grep -c "karyawan_pool\|karyawan_lembaga" tests/Feature/Sdm/IzinCutiWorkflowSeedTest.php
```
Ganti, verifikasi 0 sisa, jalankan `php artisan test tests/Feature/Sdm/IzinCutiWorkflowSeedTest.php`, commit:
```bash
git add tests/Feature/Sdm/IzinCutiWorkflowSeedTest.php
git commit -m "fix(test): IzinCutiWorkflowSeedTest rename karyawan_pool/lembaga ke pegawai_yayasan/lembaga"
```

---

## Task 23: `tests/Unit/Services/AkunKaryawanGeneratorTest.php`

**Files:**
- Modify: `tests/Unit/Services/AkunKaryawanGeneratorTest.php`

Pola identik Task 15 (baseline: 6 kemunculan). Ini test untuk Task 5 (`AkunKaryawanGenerator.php`) — WAJIB lolos setelah diupdate, karena Task 5 sengaja belum menjalankan test scoped-nya sampai file test ini juga diupdate.

```bash
grep -c "karyawan_pool\|karyawan_lembaga" tests/Unit/Services/AkunKaryawanGeneratorTest.php
```
Ganti semua, verifikasi 0 sisa, jalankan `php artisan test tests/Unit/Services/AkunKaryawanGeneratorTest.php`, commit:
```bash
git add tests/Unit/Services/AkunKaryawanGeneratorTest.php
git commit -m "fix(test): AkunKaryawanGeneratorTest rename karyawan_pool/lembaga ke pegawai_yayasan/lembaga"
```

---

## Task 24: Test Invariant Baru (Spec §13)

**Files:**
- Create: `tests/Feature/Rbac/RoleTaxonomyInvariantTest.php`

**Interfaces:**
- Consumes: `RoleSeeder` (Task 3), `AkunKaryawanGenerator` (Task 5), `Kelas.wali_kelas_guru_id` (existing, TIDAK diubah plan ini).

- [ ] **Step 1: Baca `app/Http/Controllers/Admin/KaryawanController.php::store()` sekali lagi untuk konfirmasi PERSIS bagaimana `is_pool` divalidasi (dikutip di Task 6), supaya test request body-nya benar.**

- [ ] **Step 2: Buat file test baru**

```php
<?php
// tests/Feature/Rbac/RoleTaxonomyInvariantTest.php

use App\Models\Karyawan;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use App\Services\AkunKaryawanGenerator;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
});

// ── M2: pegawai_lembaga XOR pegawai_yayasan via AkunKaryawanGenerator ──────

it('assigns pegawai_lembaga when AkunKaryawanGenerator creates a lembaga-scoped karyawan', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $jenisKaryawan = \App\Domains\Sdm\Models\JenisKaryawanMaster::factory()->create();

    $karyawan = app(AkunKaryawanGenerator::class)->buat(
        'Test Karyawan', '1234567890123456', $yayasan->id, $lembaga->id, $jenisKaryawan->id
    );

    expect($karyawan->user->hasRole('pegawai_lembaga'))->toBeTrue();
    expect($karyawan->user->hasRole('pegawai_yayasan'))->toBeFalse();
});

it('assigns pegawai_yayasan when AkunKaryawanGenerator creates a pool karyawan (null lembaga)', function () {
    $yayasan = Yayasan::factory()->create();
    $jenisKaryawan = \App\Domains\Sdm\Models\JenisKaryawanMaster::factory()->create();

    $karyawan = app(AkunKaryawanGenerator::class)->buat(
        'Test Karyawan Pool', '1234567890123457', $yayasan->id, null, $jenisKaryawan->id
    );

    expect($karyawan->user->hasRole('pegawai_yayasan'))->toBeTrue();
    expect($karyawan->user->hasRole('pegawai_lembaga'))->toBeFalse();
});

it('rejects a non-yayasan_super_admin from creating a pool karyawan via is_pool', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $jenisKaryawan = \App\Domains\Sdm\Models\JenisKaryawanMaster::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('operator_akademik');
    $admin->givePermissionTo('karyawan.create');

    $this->actingAs($admin)->post(route('admin.karyawan.store'), [
        'is_pool' => '1',
        'yayasan_id' => $yayasan->id,
        'nik' => '1234567890123458',
        'nama' => 'Test Pool Ditolak',
        'jenis_karyawan_id' => $jenisKaryawan->id,
    ])->assertForbidden();
});

// ── M3, M7: widestScopeLevel() regression untuk pegawai_* ──────────────────

it('resolves widestScopeLevel to lembaga for a user with only pegawai_lembaga', function () {
    $user = User::factory()->create();
    $user->assignRole('pegawai_lembaga');

    expect($user->widestScopeLevel())->toBe('lembaga');
});

it('resolves widestScopeLevel to yayasan for a user with only pegawai_yayasan', function () {
    $user = User::factory()->create();
    $user->assignRole('pegawai_yayasan');

    expect($user->widestScopeLevel())->toBe('yayasan');
});

// ── Multi-role composition ──────────────────────────────────────────────────

it('keeps all permissions active when a user has pegawai_lembaga + guru + wali_kelas combined', function () {
    $user = User::factory()->create();
    $user->assignRole(['pegawai_lembaga', 'guru', 'wali_kelas']);

    expect($user->hasRole('pegawai_lembaga'))->toBeTrue();
    expect($user->hasRole('guru'))->toBeTrue();
    expect($user->hasRole('wali_kelas'))->toBeTrue();
    expect($user->can('kasus.ajukan'))->toBeTrue(); // dari role guru
    expect($user->can('kehadiran-sdm.lihat-qr-sendiri'))->toBeTrue(); // dari role pegawai_lembaga
});

// ── Wali Kelas: capability vs relation (spec §8) ────────────────────────────

it('denies a wali_kelas-role guru from managing a kelas that is not theirs via wali_kelas_guru_id', function () {
    $lembaga = Lembaga::factory()->create();
    $guruLain = \App\Models\Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'wali_kelas_guru_id' => $guruLain->id]);

    $userWaliKelas = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userWaliKelas->assignRole(['pegawai_lembaga', 'guru', 'wali_kelas']);
    $guruSaya = \App\Models\Guru::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $userWaliKelas->id]);

    // Pola authorization yang benar (spec §8): role HANYA capability gate,
    // relasi Kelas.wali_kelas_guru_id yang menentukan kelas mana yang dikelola.
    $bolehKelola = $userWaliKelas->hasRole('wali_kelas') && $kelas->wali_kelas_guru_id === $guruSaya->id;

    expect($bolehKelola)->toBeFalse();
});

it('allows a wali_kelas-role guru to manage the kelas they are actually assigned to via wali_kelas_guru_id', function () {
    $lembaga = Lembaga::factory()->create();
    $userWaliKelas = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userWaliKelas->assignRole(['pegawai_lembaga', 'guru', 'wali_kelas']);
    $guruSaya = \App\Models\Guru::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $userWaliKelas->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'wali_kelas_guru_id' => $guruSaya->id]);

    $bolehKelola = $userWaliKelas->hasRole('wali_kelas') && $kelas->wali_kelas_guru_id === $guruSaya->id;

    expect($bolehKelola)->toBeTrue();
});
```

**Sebelum menulis persis seperti di atas**: WAJIB baca `database/factories/GuruFactory.php`, `database/factories/KelasFactory.php`, `database/factories/JenisKaryawanMasterFactory.php` dulu untuk konfirmasi factory-nya ada dan field wajibnya cocok dengan yang dipakai di atas — kalau ada field required yang belum di-set (mis. NIK unik, dsb), sesuaikan.

- [ ] **Step 3: Verifikasi syntax**

```bash
php -l tests/Feature/Rbac/RoleTaxonomyInvariantTest.php
```

- [ ] **Step 4: Jalankan test scoped**

```bash
php artisan test tests/Feature/Rbac/RoleTaxonomyInvariantTest.php
```
Expected: semua PASS. Kalau ada yang gagal karena factory/route/permission tidak sesuai asumsi plan, STOP, sesuaikan berdasarkan struktur aktual (JANGAN paksakan assertion yang salah demi lolos).

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Rbac/RoleTaxonomyInvariantTest.php
git commit -m "test(rbac): tambah test invariant RoleTaxonomy (pegawai_* assignment, widestScopeLevel, multi-role, wali_kelas)"
```

---

## Task 25: Grep Ulang Final — Verifikasi Kosong Total

**Files:**
- Tidak ada file baru — task ini murni verifikasi gate.

- [ ] **Step 1: Grep gabungan seluruh codebase**

```bash
grep -rln "karyawan_pool\|karyawan_lembaga" --include="*.php" app database tests resources/views routes
grep -rln "'admin_akademik'\|\"admin_akademik\"\|'admin_keuangan'\|\"admin_keuangan\"" --include="*.php" app database tests resources/views routes
```
Expected: KOSONG total (untuk `admin_akademik`/`admin_keuangan`, grep dibatasi ke literal string dengan tanda kutip supaya tidak salah tangkap kata lain yang kebetulan mengandung substring serupa — verifikasi manual kalau ada hit yang meragukan).

- [ ] **Step 2: Kalau ADA sisa, STOP dan perbaiki file yang terlewat sebelum lanjut Task 26.**

Tidak ada commit di task ini.

---

## Task 26: Verifikasi Akhir Menyeluruh + Handoff Log

**Files:**
- Create: `.agents/logs/2026-08-24-rbac-v2-role-taxonomy.md`
- Modify: `.agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md` (opsional, kalau user minta didaftarkan di roadmap induk — TIDAK WAJIB karena RBAC v2 bukan bagian dari roadmap migrasi domain itu)

- [ ] **Step 1: Jalankan seluruh test yang disentuh plan ini sekaligus**

```bash
php artisan test tests/Feature/Admin/KaryawanCrudTest.php tests/Feature/DashboardKasusTest.php tests/Feature/KaryawanDashboardTest.php tests/Feature/KasusEvaluasiTest.php tests/Feature/KasusKonselorAksesTest.php tests/Feature/KasusTugasReviewTest.php tests/Feature/Sdm/AttendanceRbacSeedTest.php tests/Feature/Sdm/IzinCutiWorkflowSeedTest.php tests/Unit/Services/AkunKaryawanGeneratorTest.php tests/Feature/Rbac/RoleTaxonomyInvariantTest.php
```
Expected: 0 failed.

- [ ] **Step 2: Minta izin user untuk full test suite**

Tanya ke user: "Task 1-25 selesai, grep gabungan kosong total, seluruh test yang disentuh plan ini hijau. Boleh saya jalankan full test suite untuk verifikasi akhir?" — TUNGGU jawaban eksplisit.

- [ ] **Step 3: Jalankan full suite SOLO**

```bash
php artisan test
```
Catat angka PASTI passed/failed/duration.

- [ ] **Step 4: Tulis handoff log**

Buat `.agents/logs/2026-08-24-rbac-v2-role-taxonomy.md` (Bahasa Indonesia): ringkasan Task 1-25 dengan commit hash, hasil grep Task 25 (kosong), hasil test Step 1 dan Step 3 (angka pasti, jangan dicampur), daftar 17 role final + scope_level-nya.

- [ ] **Step 5: Commit**

```bash
git add .agents/logs/2026-08-24-rbac-v2-role-taxonomy.md
git commit -m "docs(rbac): handoff log implementasi RBAC v2 role taxonomy"
```
