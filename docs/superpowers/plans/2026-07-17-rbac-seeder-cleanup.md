# RBAC Seeder Cleanup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Split the monolithic `RolePermissionSeeder` into one seeder per table (`PermissionSeeder`, `RoleSeeder`), add a new `EssentialUserSeeder` for minimal per-role verification accounts, and wire all three into `DatabaseSeeder` — while keeping `RolePermissionSeeder` itself working as a thin backward-compatible wrapper so every existing test that calls it directly keeps passing unmodified.

**Architecture:** `PermissionSeeder` owns the `permissions` table exclusively. `RoleSeeder` owns the `roles` table and role-permission assignment (reading whatever permissions already exist in the database — it does not define the permission list itself). `RolePermissionSeeder` becomes a 2-line delegator calling both in order. `EssentialUserSeeder` owns 5 minimal, generically-named User accounts (one per role) for role-verification, separate from the existing per-lembaga demo staff accounts (`kepsek.smp@alhikmah.sch.id` etc.) which are untouched by this plan.

**Tech Stack:** Laravel 12, Pest PHP, Spatie Laravel Permission.

## Global Constraints

- `PermissionSeeder` registers exactly the 51 permissions currently in `RolePermissionSeeder::$permissions`, verbatim, no additions or removals.
- **Deviation from the design spec, found during planning — flag this to the user:** the spec said the legacy flat-name permission cleanup (`Permission::whereIn(['manage-roles', ...])->delete()`) should be dropped from the new seeders as dead weight. However, `tests/Feature/RolePermissionSeederTest.php` (pre-existing, must keep passing unmodified per this same spec's stronger regression-safety requirement) has a test — `it('removes orphaned old flat permission rows on re-seed', ...)` — that specifically asserts this cleanup still happens when `RolePermissionSeeder` runs. Dropping the cleanup would break that test. This plan resolves the conflict by **keeping** the cleanup logic (inside `PermissionSeeder`, since it's genuinely permission-table-scoped) — it is a harmless no-op on a clean install (matches zero rows), and this satisfies the spec's explicit "existing test suite passes unmodified" requirement, which takes priority over the "drop dead weight" phrasing.
- `RoleSeeder` creates exactly the 5 roles (`yayasan_super_admin`, `kepala_sekolah`, `admin_administrasi`, `admin_keuangan`, `guru`) with the exact `scope_level`/`is_protected`/permission-per-role values currently in `RolePermissionSeeder`, and must run AFTER `PermissionSeeder` (it reads `Permission::pluck('name')` for the `yayasan_super_admin` role's full-access grant, it does not define permissions itself).
- `RolePermissionSeeder.php` (existing file) is modified, not deleted — it becomes a thin wrapper (`(new PermissionSeeder())->run(); (new RoleSeeder())->run();`) so `tests/Feature/RolePermissionSeederTest.php` and every other test file across the project that calls `(new RolePermissionSeeder())->run()` directly keeps working with zero modification.
- `EssentialUserSeeder`'s 5 accounts use generic emails (`superadmin@sistem.test`, `kepsek@sistem.test`, `adm@sistem.test`, `keuangan@sistem.test`, `guru@sistem.test`), password `password` — these are additive and distinct from the existing per-lembaga demo accounts (`kepsek.smp@alhikmah.sch.id` etc., referenced throughout this project's manual testing docs), which this plan does not touch.
- `EssentialUserSeeder`'s 4 lembaga-scoped accounts attach to `Lembaga::first()`; if no `Lembaga` exists at all, they are skipped with a console warning (not an error) and only `superadmin@sistem.test` is created.
- `DatabaseSeeder.php` calls `PermissionSeeder::class` and `RoleSeeder::class` directly (not through `RolePermissionSeeder::class`) at the start, and `EssentialUserSeeder::class` is added at the **end** of the existing call list (after `M3DemoDataSeeder::class`) — this is a temporary position, documented inline, since `EssentialUserSeeder` should ideally run right after a `LembagaSeeder` that doesn't exist yet (that seeder is Sub-project 2 of this initiative, not yet started).
- PHP is not on PATH — use `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe` for all `artisan`/test commands.

---

### Task 1: `PermissionSeeder`

**Files:**
- Create: `database/seeders/PermissionSeeder.php`
- Test: `tests/Unit/PermissionSeederTest.php`

**Interfaces:**
- Produces: `PermissionSeeder::run(): void` — creates/ensures exactly 51 `Permission` rows (guard `web`) exist. Task 2's `RoleSeeder` and Task 3's `RolePermissionSeeder` both depend on this running first.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/PermissionSeederTest.php

use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('seeds exactly 51 permissions', function () {
    (new PermissionSeeder())->run();

    expect(Permission::count())->toBe(51);
    expect(Permission::where('name', 'roles.view')->exists())->toBeTrue();
    expect(Permission::where('name', 'cicilan.kelola')->exists())->toBeTrue();
});

it('is idempotent when run twice', function () {
    (new PermissionSeeder())->run();
    (new PermissionSeeder())->run();

    expect(Permission::count())->toBe(51);
});

it('removes orphaned legacy flat-name permissions on re-seed', function () {
    Permission::firstOrCreate(['name' => 'manage-guru', 'guard_name' => 'web']);

    (new PermissionSeeder())->run();

    expect(Permission::where('name', 'manage-guru')->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/PermissionSeederTest.php`
Expected: FAIL — `Database\Seeders\PermissionSeeder` doesn't exist yet.

- [ ] **Step 3: Write `PermissionSeeder`**

```php
<?php
// database/seeders/PermissionSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Legacy flat-name permissions from an earlier RBAC iteration. Matches zero rows on
        // a clean install, so this is a harmless no-op there -- kept so this seeder alone
        // stays safe to run against a database that still has them (mirrors what
        // RolePermissionSeeder used to do, so its own pre-existing regression test keeps
        // passing unmodified).
        Permission::whereIn('name', [
            'manage-roles', 'manage-users', 'manage-yayasan',
            'manage-lembaga', 'manage-tahun-ajaran', 'manage-guru',
            'view-audit-log', 'manage-ppdb',
        ])->delete();

        $permissions = [
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
            'users.view', 'users.create', 'users.edit', 'users.toggle-active',
            'lembaga.view', 'lembaga.create', 'lembaga.edit',
            'guru.view', 'guru.create', 'guru.edit',
            'tahun-ajaran.view', 'tahun-ajaran.create', 'tahun-ajaran.activate',
            'semester.create', 'semester.activate',
            'jenis-tes.view', 'jenis-tes.create', 'jenis-tes.delete',
            'gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit',
            'jalur-ppdb.view', 'jalur-ppdb.create', 'jalur-ppdb.edit',
            'formulir-field.create', 'formulir-field.delete',
            'dokumen-syarat.create', 'dokumen-syarat.delete',
            'seleksi.create', 'seleksi.delete',
            'spmb-konfigurasi.duplikasi',
            'audit-log.view',
            'spmb-pendaftaran.view', 'spmb-pendaftaran.verifikasi-dokumen', 'spmb-pendaftaran.nilai-seleksi',
            'spmb-pendaftaran.tetapkan-keputusan', 'spmb-pendaftaran.terbitkan-sk',
            'jenis-tagihan.view', 'jenis-tagihan.create', 'jenis-tagihan.edit', 'jenis-tagihan.delete',
            'tagihan.view', 'tagihan.buat-susulan',
            'pembayaran.view', 'pembayaran.verifikasi', 'pembayaran.catat-manual',
            'cicilan.kelola',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/PermissionSeederTest.php`
Expected: PASS (3/3)

- [ ] **Step 5: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all pre-existing tests still pass (this file is additive only — `RolePermissionSeeder.php` is untouched until Task 3).

- [ ] **Step 6: Commit**

```bash
git add database/seeders/PermissionSeeder.php tests/Unit/PermissionSeederTest.php
git commit -m "feat: add PermissionSeeder, splitting permission registration out of RolePermissionSeeder"
```

---

### Task 2: `RoleSeeder`

**Files:**
- Create: `database/seeders/RoleSeeder.php`
- Test: `tests/Unit/RoleSeederTest.php`

**Interfaces:**
- Consumes: `Permission::pluck('name')` (from Task 1's `PermissionSeeder`, must have already run) for the `yayasan_super_admin` role's full grant.
- Produces: `RoleSeeder::run(): void` — creates/ensures exactly 5 `Role` rows with their permission assignments. Task 3's `RolePermissionSeeder` wrapper calls this after `PermissionSeeder`. Task 4's `EssentialUserSeeder` depends on these 5 role names existing (for `assignRole()`).

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/RoleSeederTest.php

use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
});

it('seeds 5 roles with correct scope and protection', function () {
    (new RoleSeeder())->run();

    $superAdmin = Role::where('name', 'yayasan_super_admin')->first();
    expect($superAdmin->scope_level)->toBe('yayasan');
    expect($superAdmin->is_protected)->toBeTrue();
    expect($superAdmin->permissions()->count())->toBe(51);

    expect(Role::where('name', 'kepala_sekolah')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'admin_administrasi')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'admin_keuangan')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'guru')->first()->scope_level)->toBe('diri_sendiri');
});

it('gives admin_administrasi the correct 19 SPMB-related permissions', function () {
    (new RoleSeeder())->run();

    $adminAdministrasi = Role::where('name', 'admin_administrasi')->first();
    expect($adminAdministrasi->permissions()->count())->toBe(19);
    expect($adminAdministrasi->hasPermissionTo('jalur-ppdb.create'))->toBeTrue();
});

it('gives kepala_sekolah the correct 6 permissions', function () {
    (new RoleSeeder())->run();

    $kepalaSekolah = Role::where('name', 'kepala_sekolah')->first();
    expect($kepalaSekolah->permissions()->count())->toBe(6);
    expect($kepalaSekolah->hasPermissionTo('spmb-pendaftaran.tetapkan-keputusan'))->toBeTrue();
});

it('gives admin_keuangan the correct 11 permissions', function () {
    (new RoleSeeder())->run();

    $adminKeuangan = Role::where('name', 'admin_keuangan')->first();
    expect($adminKeuangan->permissions()->count())->toBe(11);
    expect($adminKeuangan->hasPermissionTo('cicilan.kelola'))->toBeTrue();
});

it('is idempotent when run twice', function () {
    (new RoleSeeder())->run();
    (new RoleSeeder())->run();

    expect(Role::count())->toBe(5);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/RoleSeederTest.php`
Expected: FAIL — `Database\Seeders\RoleSeeder` doesn't exist yet.

- [ ] **Step 3: Write `RoleSeeder`**

```php
<?php
// database/seeders/RoleSeeder.php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'yayasan_super_admin' => ['scope_level' => 'yayasan', 'is_protected' => true],
            'kepala_sekolah' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'admin_administrasi' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'admin_keuangan' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'guru' => ['scope_level' => 'diri_sendiri', 'is_protected' => false],
        ];

        foreach ($roles as $name => $attributes) {
            $role = Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                $attributes
            );

            if ($name === 'yayasan_super_admin') {
                $role->syncPermissions(Permission::pluck('name')->all());
            }

            if ($name === 'admin_administrasi') {
                $role->givePermissionTo([
                    'jenis-tes.view', 'jenis-tes.create', 'jenis-tes.delete',
                    'gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit',
                    'jalur-ppdb.view', 'jalur-ppdb.create', 'jalur-ppdb.edit',
                    'formulir-field.create', 'formulir-field.delete',
                    'dokumen-syarat.create', 'dokumen-syarat.delete',
                    'seleksi.create', 'seleksi.delete',
                    'spmb-konfigurasi.duplikasi',
                    'spmb-pendaftaran.view', 'spmb-pendaftaran.verifikasi-dokumen', 'spmb-pendaftaran.nilai-seleksi',
                ]);
            }

            if ($name === 'admin_keuangan') {
                $role->givePermissionTo([
                    'jenis-tagihan.view', 'jenis-tagihan.create', 'jenis-tagihan.edit', 'jenis-tagihan.delete',
                    'tagihan.view', 'tagihan.buat-susulan',
                    'pembayaran.view', 'pembayaran.verifikasi', 'pembayaran.catat-manual',
                    'cicilan.kelola',
                    'spmb-pendaftaran.view',
                ]);
            }

            if ($name === 'kepala_sekolah') {
                $role->givePermissionTo([
                    'spmb-pendaftaran.view', 'spmb-pendaftaran.verifikasi-dokumen', 'spmb-pendaftaran.nilai-seleksi',
                    'spmb-pendaftaran.tetapkan-keputusan', 'spmb-pendaftaran.terbitkan-sk',
                    'tagihan.view',
                ]);
            }
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/RoleSeederTest.php`
Expected: PASS (5/5)

- [ ] **Step 5: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all pre-existing tests still pass.

- [ ] **Step 6: Commit**

```bash
git add database/seeders/RoleSeeder.php tests/Unit/RoleSeederTest.php
git commit -m "feat: add RoleSeeder, splitting role and permission-assignment out of RolePermissionSeeder"
```

---

### Task 3: `RolePermissionSeeder` Becomes a Delegator, Wire into `DatabaseSeeder`

**Files:**
- Modify: `database/seeders/RolePermissionSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

**Interfaces:**
- Consumes: `PermissionSeeder::run()` (Task 1), `RoleSeeder::run()` (Task 2).
- Produces: `RolePermissionSeeder::run(): void` keeps its exact existing public signature and end-to-end behavior — every other file in the project that calls `(new RolePermissionSeeder())->run()` (there are many, across every prior sub-project's tests) needs zero changes.

- [ ] **Step 1: Replace `database/seeders/RolePermissionSeeder.php`**

```php
<?php
// database/seeders/RolePermissionSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        (new PermissionSeeder())->run();
        (new RoleSeeder())->run();
    }
}
```

- [ ] **Step 2: Run the pre-existing regression test to verify nothing broke**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/RolePermissionSeederTest.php`
Expected: PASS (7/7) — this file is not modified by this plan; it must pass exactly as it did before Task 1/2/3.

- [ ] **Step 3: Update `database/seeders/DatabaseSeeder.php`**

Replace the file with:

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            YayasanSeeder::class,
            JabatanTambahanMasterSeeder::class,
            DemoDataSeeder::class,
            M3DemoDataSeeder::class,
            // EssentialUserSeeder runs last for now because it needs at least one Lembaga to
            // exist for 4 of its 5 accounts -- DemoDataSeeder is what creates Lembaga rows
            // today. Once a dedicated LembagaSeeder exists (seeder-architecture-cleanup
            // sub-project 2), move this line to run right after it instead.
            EssentialUserSeeder::class,
        ]);
    }
}
```

- [ ] **Step 4: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all tests pass. (`EssentialUserSeeder` doesn't exist yet — this will fail until Task 4 is complete. If executing tasks in strict order, this full-suite run will fail at this step; that's expected and resolved by Task 4. Do not skip re-running the full suite again at the end of Task 4.)

- [ ] **Step 5: Commit**

```bash
git add database/seeders/RolePermissionSeeder.php database/seeders/DatabaseSeeder.php
git commit -m "refactor: make RolePermissionSeeder delegate to PermissionSeeder and RoleSeeder"
```

---

### Task 4: `EssentialUserSeeder`

**Files:**
- Create: `database/seeders/EssentialUserSeeder.php`
- Test: `tests/Unit/EssentialUserSeederTest.php`

**Interfaces:**
- Consumes: the 5 role names created by `RoleSeeder` (Task 2) — `assignRole()` will throw if a role doesn't exist, so this seeder must run after `RoleSeeder` (already the case in `DatabaseSeeder`'s call order from Task 3).
- Produces: `EssentialUserSeeder::run(): void` — up to 5 `User` rows (`superadmin@sistem.test`, `kepsek@sistem.test`, `adm@sistem.test`, `keuangan@sistem.test`, `guru@sistem.test`), each with the matching role assigned.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/EssentialUserSeederTest.php

use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\EssentialUserSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
});

it('creates only the superadmin account when no lembaga exists yet', function () {
    (new EssentialUserSeeder())->run();

    $superAdmin = User::where('email', 'superadmin@sistem.test')->first();
    expect($superAdmin)->not->toBeNull();
    expect($superAdmin->hasRole('yayasan_super_admin'))->toBeTrue();
    expect($superAdmin->lembaga_id)->toBeNull();

    expect(User::where('email', 'kepsek@sistem.test')->exists())->toBeFalse();
});

it('creates all 5 essential accounts when a lembaga exists, attaching the lembaga-scoped ones to it', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    (new EssentialUserSeeder())->run();

    expect(User::where('email', 'superadmin@sistem.test')->exists())->toBeTrue();

    $kepsek = User::where('email', 'kepsek@sistem.test')->first();
    expect($kepsek->hasRole('kepala_sekolah'))->toBeTrue();
    expect($kepsek->lembaga_id)->toBe($lembaga->id);

    $adm = User::where('email', 'adm@sistem.test')->first();
    expect($adm->hasRole('admin_administrasi'))->toBeTrue();

    $keuangan = User::where('email', 'keuangan@sistem.test')->first();
    expect($keuangan->hasRole('admin_keuangan'))->toBeTrue();

    $guru = User::where('email', 'guru@sistem.test')->first();
    expect($guru->hasRole('guru'))->toBeTrue();
});

it('is idempotent when run twice', function () {
    Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);

    (new EssentialUserSeeder())->run();
    (new EssentialUserSeeder())->run();

    expect(User::count())->toBe(5);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/EssentialUserSeederTest.php`
Expected: FAIL — `Database\Seeders\EssentialUserSeeder` doesn't exist yet.

- [ ] **Step 3: Write `EssentialUserSeeder`**

```php
<?php
// database/seeders/EssentialUserSeeder.php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\User;
use Illuminate\Database\Seeder;

class EssentialUserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@sistem.test'],
            [
                'name' => 'Admin Sistem',
                'password' => 'password',
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        $superAdmin->assignRole('yayasan_super_admin');

        $lembaga = Lembaga::first();

        if (! $lembaga) {
            $this->command?->warn('Belum ada Lembaga -- akun kepala_sekolah/admin_administrasi/admin_keuangan/guru dilewati.');

            return;
        }

        $akunLembagaScoped = [
            'kepsek@sistem.test' => ['name' => 'Kepala Sekolah (Contoh)', 'role' => 'kepala_sekolah'],
            'adm@sistem.test' => ['name' => 'Admin Administrasi (Contoh)', 'role' => 'admin_administrasi'],
            'keuangan@sistem.test' => ['name' => 'Admin Keuangan (Contoh)', 'role' => 'admin_keuangan'],
            'guru@sistem.test' => ['name' => 'Guru (Contoh)', 'role' => 'guru'],
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
            $user->assignRole($data['role']);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/EssentialUserSeederTest.php`
Expected: PASS (3/3)

- [ ] **Step 5: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all tests pass, including the `DatabaseSeeder`-dependent full-suite run that Task 3 Step 4 left failing.

- [ ] **Step 6: Commit**

```bash
git add database/seeders/EssentialUserSeeder.php tests/Unit/EssentialUserSeederTest.php
git commit -m "feat: add EssentialUserSeeder for minimal per-role verification accounts"
```

---

## Post-Plan Note

After Task 4, `PermissionSeeder`/`RoleSeeder`/`EssentialUserSeeder` exist as focused, single-table seeders, `RolePermissionSeeder` still works for every existing caller, and `DatabaseSeeder` runs all of them in the correct order. This closes sub-project 1 of 3 in the seeder-architecture-cleanup initiative. Sub-project 2 (master/reference data: `LembagaSeeder`, `GuruSeeder`, `TahunAjaranSeeder`, PPDB configuration seeders, etc. — replacing `DemoDataSeeder`) and sub-project 3 (transactional/scenario data: `CalonMuridSeeder`, `PendaftaranSeeder`, `TagihanSeeder`, etc. — replacing `M3DemoDataSeeder`/`PembayaranDemoSeeder`) are separate, not-yet-started plans. When sub-project 2 lands a `LembagaSeeder`, revisit `DatabaseSeeder`'s call order to move `EssentialUserSeeder::class` to run immediately after it, per the comment left in Task 3.
