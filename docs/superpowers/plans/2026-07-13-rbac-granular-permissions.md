# RBAC Granular Permissions — Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the app's flat, per-module Spatie permissions (`manage-guru`, `manage-ppdb`, etc.) with granular per-action permissions (`guru.view`, `guru.create`, `jalur-ppdb.edit`, etc.) across the seeder, all 13 admin controllers, the sidebar, and the full test suite — a clean, behavior-preserving cutover with no old/new coexistence.

**Architecture:** Same authorization mechanism throughout (Spatie `$this->authorize()` / `$user->can()` / policies) — only the permission *names* and *granularity* change. No new routes, no new UI in this plan (that is a separate follow-up plan for the Roles page redesign). Each task renames the permission(s) checked by one group of controllers, then updates every test that grants the old blanket permission to instead grant the new module's full action set, so no test's access is narrowed by the rename.

**Tech Stack:** Laravel 12, Spatie `laravel-permission`, Pest PHP.

## Global Constraints

- Permission naming convention: `modul.aksi` (kebab-case module, kebab-case action), exactly as enumerated in `docs/superpowers/specs/2026-07-13-rbac-granular-permissions-design.md` §2 (36 permissions total across 14 modules).
- Clean cutover: old flat permission names (`manage-roles`, `manage-users`, `manage-yayasan`, `manage-lembaga`, `manage-tahun-ajaran`, `manage-guru`, `view-audit-log`, `manage-ppdb`) are fully replaced, never left coexisting.
- No new CRUD actions are added to any controller in this plan — only the permission string(s) each existing `authorize()`/`can()` call references change.
- `manage-yayasan` is dropped entirely (no controller ever checked it; not replaced by any granular equivalent).
- Test helper functions that currently grant one blanket permission are updated to grant the **full action set for that module** (all of that module's granular permissions), not just the single action the nearest test happens to exercise — this guarantees no existing test's access is accidentally narrowed by the rename.
- `RoleController.php`'s existing (uncommitted) fix — resolving submitted permission IDs to `Permission` models via `Permission::whereIn('id', ...)->get()` before `syncPermissions()` — is preserved as-is; this plan does not touch `RoleController.php`'s `store()`/`update()` bodies, only `RolePolicy.php`.
- Every task's test run is the full affected test file(s), not just the touched `it()` blocks — a rename can silently break sibling tests in the same file.

---

### Task 1: Permission seeder — 36 granular permissions

**Files:**
- Modify: `database/seeders/RolePermissionSeeder.php`
- Test: `tests/Feature/RolePermissionSeederTest.php`

**Interfaces:**
- Produces: the 36 permission names below exist in the `permissions` table after this seeder runs. All later tasks' controllers and tests reference these exact strings.

```
roles.view, roles.create, roles.edit, roles.delete
users.view, users.create, users.edit, users.toggle-active
lembaga.view, lembaga.create, lembaga.edit
guru.view, guru.create, guru.edit
tahun-ajaran.view, tahun-ajaran.create, tahun-ajaran.activate
semester.create, semester.activate
jenis-tes.view, jenis-tes.create, jenis-tes.delete
gelombang-ppdb.view, gelombang-ppdb.create, gelombang-ppdb.edit
jalur-ppdb.view, jalur-ppdb.create, jalur-ppdb.edit
formulir-field.create, formulir-field.delete
dokumen-syarat.create, dokumen-syarat.delete
seleksi.create, seleksi.delete
spmb-konfigurasi.duplikasi
audit-log.view
```

`yayasan_super_admin` gets all 36. `admin_administrasi` gets the 16 SPMB-related permissions (jenis-tes, gelombang-ppdb, jalur-ppdb, formulir-field, dokumen-syarat, seleksi, spmb-konfigurasi — everything it previously got via the single `manage-ppdb` grant).

- [ ] **Step 1: Rewrite the seeder**

Replace the full contents of `database/seeders/RolePermissionSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
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
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

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
                $role->syncPermissions($permissions);
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
                ]);
            }
        }
    }
}
```

- [ ] **Step 2: Run the existing test to confirm it now fails**

Run: `php artisan test tests/Feature/RolePermissionSeederTest.php`
Expected: FAIL — old assertions expect 8 permissions and names like `manage-roles`, which no longer exist; `admin_administrasi->permissions()->count()` expects an old count that no longer matches.

- [ ] **Step 3: Rewrite the test to match the new seeder**

Replace the full contents of `tests/Feature/RolePermissionSeederTest.php`:

```php
<?php

use App\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Permission;

it('seeds the initial permissions', function () {
    (new RolePermissionSeeder())->run();

    $expected = [
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
    ];

    foreach ($expected as $name) {
        expect(Permission::where('name', $name)->exists())->toBeTrue();
    }

    expect(Permission::count())->toBe(36);
});

it('seeds the initial roles with correct scope and protection', function () {
    (new RolePermissionSeeder())->run();

    $superAdmin = Role::where('name', 'yayasan_super_admin')->first();
    expect($superAdmin->scope_level)->toBe('yayasan');
    expect($superAdmin->is_protected)->toBeTrue();
    expect($superAdmin->permissions()->count())->toBe(36);

    expect(Role::where('name', 'kepala_sekolah')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'admin_administrasi')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'admin_keuangan')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'guru')->first()->scope_level)->toBe('diri_sendiri');
});

it('gives admin_administrasi the SPMB-related granular permissions by default', function () {
    (new RolePermissionSeeder())->run();

    $adminAdministrasi = Role::where('name', 'admin_administrasi')->first();
    $expected = [
        'jenis-tes.view', 'jenis-tes.create', 'jenis-tes.delete',
        'gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit',
        'jalur-ppdb.view', 'jalur-ppdb.create', 'jalur-ppdb.edit',
        'formulir-field.create', 'formulir-field.delete',
        'dokumen-syarat.create', 'dokumen-syarat.delete',
        'seleksi.create', 'seleksi.delete',
        'spmb-konfigurasi.duplikasi',
    ];

    foreach ($expected as $name) {
        expect($adminAdministrasi->hasPermissionTo($name))->toBeTrue();
    }
    expect($adminAdministrasi->permissions()->count())->toBe(16);
});

it('is idempotent when run twice', function () {
    (new RolePermissionSeeder())->run();
    (new RolePermissionSeeder())->run();

    expect(Role::count())->toBe(5);
    expect(Permission::count())->toBe(36);
});
```

- [ ] **Step 4: Run the test again to confirm it passes**

Run: `php artisan test tests/Feature/RolePermissionSeederTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add database/seeders/RolePermissionSeeder.php tests/Feature/RolePermissionSeederTest.php
git commit -m "feat: seed 36 granular per-action permissions replacing flat manage-* permissions"
```

---

### Task 2: Roles authorization — policy rename + already-fixed sync bug

**Files:**
- Modify: `app/Policies/RolePolicy.php`
- No change needed: `app/Http/Controllers/Admin/RoleController.php` (already calls `$this->authorize('viewAny'|'create'|'update'|'delete', ...)`, which routes through the policy — only the policy's permission strings change. Its `syncPermissions(Permission::whereIn('id', ...)->get())` fix from the earlier bug investigation is already in place in the working tree and is committed as part of this task.)
- Test: `tests/Feature/Admin/RoleBuilderTest.php`

**Interfaces:**
- Consumes: `roles.view`, `roles.create`, `roles.edit`, `roles.delete` from Task 1.

**Context:** `RoleController.php` currently has an uncommitted fix (from the earlier `PermissionDoesNotExist` bug investigation) changing both `store()` and `update()` to call `$role->syncPermissions(Permission::whereIn('id', $data['permissions'] ?? [])->get());` instead of passing raw IDs directly to `syncPermissions()`. Leave this exactly as-is — do not modify `RoleController.php` in this task.

- [ ] **Step 1: Rewrite the policy**

Replace the full contents of `app/Policies/RolePolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('roles.view');
    }

    public function create(User $user): bool
    {
        return $user->can('roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('roles.edit');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can('roles.delete') && ! $role->is_protected;
    }
}
```

- [ ] **Step 2: Run the existing test to confirm it now fails**

Run: `php artisan test tests/Feature/Admin/RoleBuilderTest.php`
Expected: FAIL — helpers still grant `manage-roles`/`manage-guru`, which no longer authorize anything.

- [ ] **Step 3: Rewrite the test**

Replace the full contents of `tests/Feature/Admin/RoleBuilderTest.php`:

```php
<?php

use App\Models\Role;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function actingAsSuperAdmin(): User
{
    foreach (['roles.view', 'roles.create', 'roles.edit', 'roles.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(
        ['name' => 'yayasan_super_admin', 'guard_name' => 'web'],
        ['scope_level' => 'yayasan', 'is_protected' => true]
    );
    $role->givePermissionTo(['roles.view', 'roles.create', 'roles.edit', 'roles.delete']);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('denies access to a user without roles.view permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.roles.index'))->assertForbidden();
});

it('lets an authorized user create a role with a scope level and permissions', function () {
    $admin = actingAsSuperAdmin();
    Permission::firstOrCreate(['name' => 'guru.view', 'guard_name' => 'web']);

    $this->actingAs($admin)->post(route('admin.roles.store'), [
        'name' => 'admin_perpustakaan',
        'scope_level' => 'lembaga',
        'permissions' => [Permission::where('name', 'guru.view')->first()->id],
    ])->assertRedirect(route('admin.roles.index'));

    $created = Role::where('name', 'admin_perpustakaan')->first();
    expect($created->scope_level)->toBe('lembaga');
    expect($created->hasPermissionTo('guru.view'))->toBeTrue();
});

it('syncs permissions when their ids arrive as strings, matching a real HTML checkbox submission', function () {
    // Regression test: a real browser form-urlencoded POST always sends checkbox
    // values as strings (e.g. "6"), never as native PHP ints. Pest's ->post() with
    // a PHP array literal does NOT reproduce this — it preserves the int type from
    // Permission::first()->id, which masked the original bug. Casting to string
    // here forces the same string-typed input a real submission produces.
    $admin = actingAsSuperAdmin();
    Permission::firstOrCreate(['name' => 'guru.view', 'guard_name' => 'web']);
    $permissionId = Permission::where('name', 'guru.view')->first()->id;

    $this->actingAs($admin)->post(route('admin.roles.store'), [
        'name' => 'admin_string_ids',
        'scope_level' => 'lembaga',
        'permissions' => [(string) $permissionId],
    ])->assertRedirect(route('admin.roles.index'));

    $created = Role::where('name', 'admin_string_ids')->first();
    expect($created->hasPermissionTo('guru.view'))->toBeTrue();
});

it('lets an authorized user edit a non-protected role, including its scope level', function () {
    $admin = actingAsSuperAdmin();
    $role = Role::create(['name' => 'editable', 'guard_name' => 'web', 'scope_level' => 'lembaga']);

    $this->actingAs($admin)->put(route('admin.roles.update', $role), [
        'name' => 'editable-renamed',
        'scope_level' => 'yayasan',
        'permissions' => [],
    ])->assertRedirect(route('admin.roles.index'));

    expect($role->fresh()->name)->toBe('editable-renamed');
    expect($role->fresh()->scope_level)->toBe('yayasan');
});

it('does not let anyone change the scope_level of the protected super admin role', function () {
    $admin = actingAsSuperAdmin();
    $protected = Role::where('name', 'yayasan_super_admin')->first();

    $this->actingAs($admin)->put(route('admin.roles.update', $protected), [
        'name' => 'yayasan_super_admin',
        'permissions' => [],
    ])->assertRedirect(route('admin.roles.index'));

    expect($protected->fresh()->scope_level)->toBe('yayasan');
});

it('refuses to delete a protected role', function () {
    $admin = actingAsSuperAdmin();
    $protected = Role::where('name', 'yayasan_super_admin')->first();

    $this->actingAs($admin)->delete(route('admin.roles.destroy', $protected))->assertForbidden();

    expect(Role::find($protected->id))->not->toBeNull();
});

it('refuses to delete a role that still has assigned users', function () {
    $admin = actingAsSuperAdmin();
    $role = Role::create(['name' => 'in-use', 'guard_name' => 'web', 'scope_level' => 'lembaga']);
    User::factory()->create()->assignRole($role);

    $this->actingAs($admin)->delete(route('admin.roles.destroy', $role))
        ->assertRedirect()
        ->assertSessionHasErrors('role');

    expect(Role::find($role->id))->not->toBeNull();
});

it('refuses to let a lembaga-scoped role-manager create a yayasan-scoped role', function () {
    foreach (['roles.view', 'roles.create', 'roles.edit', 'roles.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $lembagaRole = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $lembagaRole->givePermissionTo(['roles.view', 'roles.create', 'roles.edit', 'roles.delete']);
    $manager = User::factory()->create();
    $manager->assignRole($lembagaRole);

    $this->actingAs($manager)->post(route('admin.roles.store'), [
        'name' => 'sneaky_admin',
        'scope_level' => 'yayasan',
        'permissions' => [],
    ])->assertSessionHasErrors('scope_level');

    expect(Role::where('name', 'sneaky_admin')->exists())->toBeFalse();
});
```

- [ ] **Step 4: Run the test again to confirm it passes**

Run: `php artisan test tests/Feature/Admin/RoleBuilderTest.php`
Expected: PASS (8 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Policies/RolePolicy.php app/Http/Controllers/Admin/RoleController.php tests/Feature/Admin/RoleBuilderTest.php
git commit -m "fix: resolve permission ids to models before syncing; migrate RolePolicy to granular permissions"
```

---

### Task 3: Users — granular per-action permissions

**Files:**
- Modify: `app/Http/Controllers/Admin/UserController.php`
- Test: `tests/Feature/Admin/UserManagementTest.php`

**Interfaces:**
- Consumes: `users.view`, `users.create`, `users.edit`, `users.toggle-active` from Task 1.

- [ ] **Step 1: Update the controller's authorize calls**

In `app/Http/Controllers/Admin/UserController.php`, change each method's permission string (method bodies are otherwise unchanged):

| Method | Old | New |
|---|---|---|
| `index()` | `$this->authorize('manage-users');` | `$this->authorize('users.view');` |
| `create()` | `$this->authorize('manage-users');` | `$this->authorize('users.create');` |
| `store()` | `$this->authorize('manage-users');` | `$this->authorize('users.create');` |
| `edit()` | `$this->authorize('manage-users');` | `$this->authorize('users.edit');` |
| `update()` | `$this->authorize('manage-users');` | `$this->authorize('users.edit');` |
| `toggleActive()` | `$this->authorize('manage-users');` | `$this->authorize('users.toggle-active');` |

- [ ] **Step 2: Run the existing test to confirm it now fails**

Run: `php artisan test tests/Feature/Admin/UserManagementTest.php`
Expected: FAIL — helpers still grant `manage-users`, which no longer authorizes anything.

- [ ] **Step 3: Rewrite the test**

Replace the full contents of `tests/Feature/Admin/UserManagementTest.php`:

```php
<?php

use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsUserManager(): User
{
    foreach (['users.view', 'users.create', 'users.edit', 'users.toggle-active'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(
        ['name' => 'yayasan_super_admin', 'guard_name' => 'web'],
        ['scope_level' => 'yayasan', 'is_protected' => true]
    );
    $role->givePermissionTo(['users.view', 'users.create', 'users.edit', 'users.toggle-active']);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('denies access to a user without users.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.users.index'))->assertForbidden();
});

it('lets a yayasan-scoped manager create a staff account with a role and a lembaga', function () {
    $manager = actingAsUserManager();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'Kepala Sekolah Satu',
        'email' => 'kepsek1@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'lembaga_id' => $lembaga->id,
        'role' => 'kepala_sekolah',
    ])->assertRedirect(route('admin.users.index'));

    $created = User::withoutGlobalScopes()->where('email', 'kepsek1@example.test')->first();
    expect($created)->not->toBeNull();
    expect($created->lembaga_id)->toBe($lembaga->id);
    expect($created->hasRole('kepala_sekolah'))->toBeTrue();
});

it('forces lembaga_id to the acting lembaga-scoped manager\'s own lembaga, ignoring submitted input', function () {
    foreach (['users.view', 'users.create', 'users.edit', 'users.toggle-active'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $lembagaRole = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $lembagaRole->givePermissionTo(['users.view', 'users.create', 'users.edit', 'users.toggle-active']);

    $yayasan = Yayasan::factory()->create();
    $ownLembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $manager = User::factory()->create(['lembaga_id' => $ownLembaga->id]);
    $manager->assignRole('admin_administrasi');

    Role::firstOrCreate(['name' => 'admin_keuangan', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'Staf Keuangan',
        'email' => 'keuangan1@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'lembaga_id' => $otherLembaga->id,
        'role' => 'admin_keuangan',
    ]);

    $created = User::withoutGlobalScopes()->where('email', 'keuangan1@example.test')->first();
    expect($created->lembaga_id)->toBe($ownLembaga->id);
});

it('requires a lembaga when creating a user with a lembaga-scoped role', function () {
    $manager = actingAsUserManager();
    Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'Tanpa Lembaga',
        'email' => 'tanpalembaga@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'kepala_sekolah',
    ])->assertSessionHasErrors('lembaga_id');

    expect(User::withoutGlobalScopes()->where('email', 'tanpalembaga@example.test')->exists())->toBeFalse();
});

it('deactivates a staff account so it can no longer log in', function () {
    $manager = actingAsUserManager();
    $staff = User::factory()->create(['is_active' => true]);

    $this->actingAs($manager)->patch(route('admin.users.toggle-active', $staff))
        ->assertRedirect(route('admin.users.index'));

    expect($staff->fresh()->is_active)->toBeFalse();
});

it('refuses to let a lembaga-scoped manager assign a yayasan-scoped role to a new user', function () {
    foreach (['users.view', 'users.create', 'users.edit', 'users.toggle-active'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $lembagaRole = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $lembagaRole->givePermissionTo(['users.view', 'users.create', 'users.edit', 'users.toggle-active']);
    Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($lembagaRole);

    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'Sneaky User',
        'email' => 'sneaky@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'yayasan_super_admin',
    ])->assertSessionHasErrors('role');

    expect(User::withoutGlobalScopes()->where('email', 'sneaky@example.test')->exists())->toBeFalse();
});
```

- [ ] **Step 4: Run the test again to confirm it passes**

Run: `php artisan test tests/Feature/Admin/UserManagementTest.php`
Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/UserController.php tests/Feature/Admin/UserManagementTest.php
git commit -m "feat: migrate UserController to granular users.* permissions"
```

---

### Task 4: Lembaga — granular per-action permissions

**Files:**
- Modify: `app/Http/Controllers/Admin/LembagaController.php`
- Test: `tests/Feature/Admin/LembagaCrudTest.php`

**Interfaces:**
- Consumes: `lembaga.view`, `lembaga.create`, `lembaga.edit` from Task 1.

- [ ] **Step 1: Update the controller's authorize calls**

In `app/Http/Controllers/Admin/LembagaController.php`:

| Method | Old | New |
|---|---|---|
| `index()` | `$this->authorize('manage-lembaga');` | `$this->authorize('lembaga.view');` |
| `create()` | `$this->authorize('manage-lembaga');` | `$this->authorize('lembaga.create');` |
| `store()` | `$this->authorize('manage-lembaga');` | `$this->authorize('lembaga.create');` |
| `edit()` | `$this->authorize('manage-lembaga');` | `$this->authorize('lembaga.edit');` |
| `update()` | `$this->authorize('manage-lembaga');` | `$this->authorize('lembaga.edit');` |

- [ ] **Step 2: Run the existing test to confirm it now fails**

Run: `php artisan test tests/Feature/Admin/LembagaCrudTest.php`
Expected: FAIL — helpers still grant `manage-lembaga`.

- [ ] **Step 3: Rewrite the test**

Replace the full contents of `tests/Feature/Admin/LembagaCrudTest.php`:

```php
<?php

use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('denies access to a user without lembaga.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.lembaga.index'))->assertForbidden();
});

it('lets a yayasan-scoped user create a new lembaga', function () {
    foreach (['lembaga.view', 'lembaga.create', 'lembaga.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['lembaga.view', 'lembaga.create', 'lembaga.edit']);
    $manager = User::factory()->create();
    $manager->assignRole($role);

    $yayasan = Yayasan::factory()->create();

    $this->actingAs($manager)->post(route('admin.lembaga.store'), [
        'yayasan_id' => $yayasan->id,
        'npsn' => '20301234',
        'nama' => 'SMA Pintera Tiga',
        'bentuk_pendidikan' => 'SMA',
        'status_sekolah' => 'swasta',
        'naungan' => 'kemendikdasmen',
    ])->assertRedirect(route('admin.lembaga.index'));

    expect(Lembaga::where('npsn', '20301234')->exists())->toBeTrue();
});

it('forbids a lembaga-scoped user from editing a lembaga that is not their own', function () {
    foreach (['lembaga.view', 'lembaga.create', 'lembaga.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['lembaga.view', 'lembaga.create', 'lembaga.edit']);

    $yayasan = Yayasan::factory()->create();
    $ownLembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $manager = User::factory()->create(['lembaga_id' => $ownLembaga->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->get(route('admin.lembaga.edit', $otherLembaga))->assertForbidden();
});

it('lets a lembaga-scoped user edit their own lembaga', function () {
    foreach (['lembaga.view', 'lembaga.create', 'lembaga.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['lembaga.view', 'lembaga.create', 'lembaga.edit']);

    $yayasan = Yayasan::factory()->create();
    $ownLembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $manager = User::factory()->create(['lembaga_id' => $ownLembaga->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->put(route('admin.lembaga.update', $ownLembaga), [
        'yayasan_id' => $yayasan->id,
        'npsn' => $ownLembaga->npsn,
        'nama' => 'Nama Baru',
        'bentuk_pendidikan' => $ownLembaga->bentuk_pendidikan,
        'status_sekolah' => $ownLembaga->status_sekolah,
        'naungan' => $ownLembaga->naungan,
    ])->assertRedirect(route('admin.lembaga.index'));

    expect($ownLembaga->fresh()->nama)->toBe('Nama Baru');
});
```

- [ ] **Step 4: Run the test again to confirm it passes**

Run: `php artisan test tests/Feature/Admin/LembagaCrudTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/LembagaController.php tests/Feature/Admin/LembagaCrudTest.php
git commit -m "feat: migrate LembagaController to granular lembaga.* permissions"
```

---

### Task 5: Guru — granular per-action permissions

**Files:**
- Modify: `app/Http/Controllers/Admin/GuruController.php`
- Test: `tests/Feature/Admin/GuruCrudTest.php`
- Test: `tests/Feature/CrossTenantAuthorizationTest.php` (guru-related blocks only — lines ~11-34 and ~60-97 as read; the tahun-ajaran block in the same file is Task 6's responsibility)

**Interfaces:**
- Consumes: `guru.view`, `guru.create`, `guru.edit` from Task 1.

- [ ] **Step 1: Update the controller's authorize calls**

In `app/Http/Controllers/Admin/GuruController.php`:

| Method | Old | New |
|---|---|---|
| `index()` | `$this->authorize('manage-guru');` | `$this->authorize('guru.view');` |
| `create()` | `$this->authorize('manage-guru');` | `$this->authorize('guru.create');` |
| `store()` | `$this->authorize('manage-guru');` | `$this->authorize('guru.create');` |
| `edit()` | `$this->authorize('manage-guru');` | `$this->authorize('guru.edit');` |
| `update()` | `$this->authorize('manage-guru');` | `$this->authorize('guru.edit');` |

- [ ] **Step 2: Run the existing tests to confirm they now fail**

Run: `php artisan test tests/Feature/Admin/GuruCrudTest.php tests/Feature/CrossTenantAuthorizationTest.php`
Expected: FAIL — helpers still grant `manage-guru`.

- [ ] **Step 3a: Rewrite `tests/Feature/Admin/GuruCrudTest.php`**

Replace the full contents:

```php
<?php

use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsGuruManager(Lembaga $lembaga): User
{
    foreach (['guru.view', 'guru.create', 'guru.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['guru.view', 'guru.create', 'guru.edit']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('denies access to a user without guru.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.guru.index'))->assertForbidden();
});

it('only offers users with the guru role and no existing profile when creating', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembaga);

    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $eligible = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $eligible->assignRole('guru');

    $notGuru = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($manager)->get(route('admin.guru.create'));

    $response->assertOk();
    $response->assertViewHas('eligibleUsers', function ($users) use ($eligible, $notGuru) {
        return $users->contains('id', $eligible->id) && ! $users->contains('id', $notGuru->id);
    });
});

it('creates a guru profile for an eligible user', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembaga);

    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $eligible = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $eligible->assignRole('guru');

    $this->actingAs($manager)->post(route('admin.guru.store'), [
        'user_id' => $eligible->id,
        'nik' => '3201234567891234',
        'nama' => 'Guru Baru',
        'jenis_kelamin' => 'P',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ])->assertRedirect(route('admin.guru.index'));

    expect(Guru::where('user_id', $eligible->id)->exists())->toBeTrue();
});

it('only lists guru belonging to the acting lembaga-scoped manager\'s own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembagaA);

    Guru::create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembagaA->id])->id,
        'lembaga_id' => $lembagaA->id,
        'nik' => '3201234567895555',
        'nama' => 'Guru Lembaga A',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);

    Guru::withoutGlobalScopes()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembagaB->id])->id,
        'lembaga_id' => $lembagaB->id,
        'nik' => '3201234567896666',
        'nama' => 'Guru Lembaga B',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);

    $response = $this->actingAs($manager)->get(route('admin.guru.index'));

    $response->assertSee('Guru Lembaga A');
    $response->assertDontSee('Guru Lembaga B');
});

it('shows a friendly validation error instead of a 500 when creating a guru with a duplicate NIK', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembaga);

    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $firstUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $firstUser->assignRole('guru');

    Guru::create([
        'user_id' => $firstUser->id,
        'lembaga_id' => $lembaga->id,
        'nik' => '3201234567899999',
        'nama' => 'Guru Pertama',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);

    $secondUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $secondUser->assignRole('guru');

    $this->actingAs($manager)->post(route('admin.guru.store'), [
        'user_id' => $secondUser->id,
        'nik' => '3201234567899999',
        'nama' => 'Guru Kedua',
        'jenis_kelamin' => 'P',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ])->assertSessionHasErrors('nik');

    expect(Guru::where('user_id', $secondUser->id)->exists())->toBeFalse();
});

it('allows updating a guru while keeping their own unchanged NIK', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembaga);

    $guru = Guru::create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembaga->id])->id,
        'lembaga_id' => $lembaga->id,
        'nik' => '3201234567898888',
        'nama' => 'Guru Uji Update',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);

    $this->actingAs($manager)->put(route('admin.guru.update', $guru), [
        'nik' => '3201234567898888',
        'nama' => 'Guru Uji Update Baru',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ])->assertRedirect(route('admin.guru.index'));

    expect($guru->fresh()->nama)->toBe('Guru Uji Update Baru');
});
```

- [ ] **Step 3b: Update the guru-related blocks in `tests/Feature/CrossTenantAuthorizationTest.php`**

Change the two guru-related `it()` blocks (leave the tahun-ajaran block untouched — Task 6 handles it):

Replace:
```php
it('404s when a lembaga-scoped admin opens the edit page for a guru in another lembaga', function () {
    Permission::firstOrCreate(['name' => 'manage-guru', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('manage-guru');
```
with:
```php
it('404s when a lembaga-scoped admin opens the edit page for a guru in another lembaga', function () {
    foreach (['guru.view', 'guru.create', 'guru.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['guru.view', 'guru.create', 'guru.edit']);
```

Replace:
```php
it('lets a yayasan-scoped user filter the guru list down to one lembaga via the switcher, and back to all', function () {
    Permission::firstOrCreate(['name' => 'manage-guru', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo('manage-guru');
```
with:
```php
it('lets a yayasan-scoped user filter the guru list down to one lembaga via the switcher, and back to all', function () {
    foreach (['guru.view', 'guru.create', 'guru.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['guru.view', 'guru.create', 'guru.edit']);
```

- [ ] **Step 4: Run the tests again to confirm they pass**

Run: `php artisan test tests/Feature/Admin/GuruCrudTest.php`
Expected: PASS (6 tests).

Run: `php artisan test tests/Feature/CrossTenantAuthorizationTest.php`
Expected: the two guru-related tests PASS; the tahun-ajaran test still FAILs (expected — fixed in Task 6).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/GuruController.php tests/Feature/Admin/GuruCrudTest.php tests/Feature/CrossTenantAuthorizationTest.php
git commit -m "feat: migrate GuruController to granular guru.* permissions"
```

---

### Task 6: Tahun Ajaran & Semester — granular per-action permissions

**Files:**
- Modify: `app/Http/Controllers/Admin/TahunAjaranController.php`
- Modify: `app/Http/Controllers/Admin/SemesterController.php`
- Test: `tests/Feature/Admin/TahunAjaranSemesterPanelTest.php`
- Test: `tests/Feature/CrossTenantAuthorizationTest.php` (the remaining tahun-ajaran block)

**Interfaces:**
- Consumes: `tahun-ajaran.view`, `tahun-ajaran.create`, `tahun-ajaran.activate`, `semester.create`, `semester.activate` from Task 1.

- [ ] **Step 1: Update the controllers' authorize calls**

In `app/Http/Controllers/Admin/TahunAjaranController.php`:

| Method | Old | New |
|---|---|---|
| `index()` | `$this->authorize('manage-tahun-ajaran');` | `$this->authorize('tahun-ajaran.view');` |
| `create()` | `$this->authorize('manage-tahun-ajaran');` | `$this->authorize('tahun-ajaran.create');` |
| `store()` | `$this->authorize('manage-tahun-ajaran');` | `$this->authorize('tahun-ajaran.create');` |
| `activate()` | `$this->authorize('manage-tahun-ajaran');` | `$this->authorize('tahun-ajaran.activate');` |

In `app/Http/Controllers/Admin/SemesterController.php`:

| Method | Old | New |
|---|---|---|
| `store()` | `$this->authorize('manage-tahun-ajaran');` | `$this->authorize('semester.create');` |
| `activate()` | `$this->authorize('manage-tahun-ajaran');` | `$this->authorize('semester.activate');` |

- [ ] **Step 2: Run the existing tests to confirm they now fail**

Run: `php artisan test tests/Feature/Admin/TahunAjaranSemesterPanelTest.php tests/Feature/CrossTenantAuthorizationTest.php`
Expected: FAIL — helpers still grant `manage-tahun-ajaran`.

- [ ] **Step 3a: Rewrite `tests/Feature/Admin/TahunAjaranSemesterPanelTest.php`**

Replace the full contents:

```php
<?php

use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsTahunAjaranManager(Lembaga $lembaga): User
{
    $permissions = ['tahun-ajaran.view', 'tahun-ajaran.create', 'tahun-ajaran.activate', 'semester.create', 'semester.activate'];
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo($permissions);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('creates a tahun ajaran auto-scoped to the acting lembaga-scoped user', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsTahunAjaranManager($lembaga);

    $this->actingAs($manager)->post(route('admin.tahun-ajaran.store'), [
        'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2027-06-30',
    ])->assertRedirect(route('admin.tahun-ajaran.index'));

    $tahunAjaran = TahunAjaran::where('nama', '2026/2027')->first();
    expect($tahunAjaran->lembaga_id)->toBe($lembaga->id);
});

it('activates a tahun ajaran via the panel, deactivating the previous one', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsTahunAjaranManager($lembaga);
    $this->actingAs($manager);

    $lama = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2025/2026',
        'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30', 'status_aktif' => true,
    ]);
    $baru = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30',
    ]);

    $this->patch(route('admin.tahun-ajaran.activate', $baru))->assertRedirect(route('admin.tahun-ajaran.index'));

    expect($lama->fresh()->status_aktif)->toBeFalse();
    expect($baru->fresh()->status_aktif)->toBeTrue();
});

it('creates a semester under a tahun ajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsTahunAjaranManager($lembaga);
    $this->actingAs($manager);

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);

    $this->post(route('admin.semester.store'), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Ganjil',
        'urutan' => 1,
        'kode_dapodik' => '20261',
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2027-01-15',
    ])->assertRedirect(route('admin.tahun-ajaran.index'));

    expect(Semester::where('tahun_ajaran_id', $tahunAjaran->id)->where('nama', 'Ganjil')->exists())->toBeTrue();
});

it('shows a friendly error instead of a 500 when activating a semester whose tahun ajaran is inactive', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsTahunAjaranManager($lembaga);
    $this->actingAs($manager);

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => false,
    ]);
    $semester = Semester::create([
        'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Ganjil', 'urutan' => 1,
        'kode_dapodik' => '20261', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-01-15',
    ]);

    $this->patch(route('admin.semester.activate', $semester))
        ->assertRedirect()
        ->assertSessionHasErrors('semester');

    expect($semester->fresh()->status_aktif)->toBeFalse();
});

it('lets a yayasan-scoped user create a tahun ajaran for the lembaga they have switched into', function () {
    $permissions = ['tahun-ajaran.view', 'tahun-ajaran.create', 'tahun-ajaran.activate', 'semester.create', 'semester.activate'];
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo($permissions);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = User::factory()->create();
    $manager->assignRole($role);
    $this->actingAs($manager);

    session(['active_lembaga_id' => $lembaga->id]);

    $this->post(route('admin.tahun-ajaran.store'), [
        'nama' => '2027/2028',
        'tanggal_mulai' => '2027-07-01',
        'tanggal_selesai' => '2028-06-30',
    ])->assertRedirect(route('admin.tahun-ajaran.index'));

    $tahunAjaran = TahunAjaran::where('nama', '2027/2028')->first();
    expect($tahunAjaran)->not->toBeNull();
    expect($tahunAjaran->lembaga_id)->toBe($lembaga->id);
});

it('shows a friendly error instead of a 500 when a yayasan-scoped user creates a tahun ajaran without switching to a lembaga', function () {
    $permissions = ['tahun-ajaran.view', 'tahun-ajaran.create', 'tahun-ajaran.activate', 'semester.create', 'semester.activate'];
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo($permissions);

    $manager = User::factory()->create();
    $manager->assignRole($role);

    $this->actingAs($manager)->post(route('admin.tahun-ajaran.store'), [
        'nama' => '2027/2028',
        'tanggal_mulai' => '2027-07-01',
        'tanggal_selesai' => '2028-06-30',
    ])->assertSessionHasErrors('lembaga_id');

    expect(TahunAjaran::withoutGlobalScopes()->where('nama', '2027/2028')->exists())->toBeFalse();
});
```

- [ ] **Step 3b: Update the remaining tahun-ajaran block in `tests/Feature/CrossTenantAuthorizationTest.php`**

Replace:
```php
it('404s when a lembaga-scoped admin tries to activate a tahun ajaran belonging to another lembaga', function () {
    Permission::firstOrCreate(['name' => 'manage-tahun-ajaran', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('manage-tahun-ajaran');
```
with:
```php
it('404s when a lembaga-scoped admin tries to activate a tahun ajaran belonging to another lembaga', function () {
    Permission::firstOrCreate(['name' => 'tahun-ajaran.activate', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('tahun-ajaran.activate');
```

- [ ] **Step 4: Run the tests again to confirm they pass**

Run: `php artisan test tests/Feature/Admin/TahunAjaranSemesterPanelTest.php tests/Feature/CrossTenantAuthorizationTest.php`
Expected: PASS (6 tests in the panel file; all 3 tests in CrossTenantAuthorizationTest now pass).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/TahunAjaranController.php app/Http/Controllers/Admin/SemesterController.php tests/Feature/Admin/TahunAjaranSemesterPanelTest.php tests/Feature/CrossTenantAuthorizationTest.php
git commit -m "feat: migrate TahunAjaranController and SemesterController to granular permissions"
```

---

### Task 7: Jenis Tes, Gelombang PPDB, Jalur PPDB — granular per-action permissions

**Files:**
- Modify: `app/Http/Controllers/Admin/JenisTesMasterController.php`
- Modify: `app/Http/Controllers/Admin/GelombangPpdbController.php`
- Modify: `app/Http/Controllers/Admin/JalurPpdbController.php`
- Test: `tests/Feature/Admin/JenisTesMasterTest.php`
- Test: `tests/Feature/Admin/GelombangPpdbTest.php`
- Test: `tests/Feature/Admin/JalurPpdbTest.php`

**Interfaces:**
- Consumes: `jenis-tes.view/create/delete`, `gelombang-ppdb.view/create/edit`, `jalur-ppdb.view/create/edit` from Task 1.

- [ ] **Step 1: Update the three controllers' authorize calls**

In `app/Http/Controllers/Admin/JenisTesMasterController.php`:

| Method | Old | New |
|---|---|---|
| `index()` | `$this->authorize('manage-ppdb');` | `$this->authorize('jenis-tes.view');` |
| `store()` | `$this->authorize('manage-ppdb');` | `$this->authorize('jenis-tes.create');` |
| `destroy()` | `$this->authorize('manage-ppdb');` | `$this->authorize('jenis-tes.delete');` |

In `app/Http/Controllers/Admin/GelombangPpdbController.php`:

| Method | Old | New |
|---|---|---|
| `index()` | `$this->authorize('manage-ppdb');` | `$this->authorize('gelombang-ppdb.view');` |
| `create()` | `$this->authorize('manage-ppdb');` | `$this->authorize('gelombang-ppdb.create');` |
| `store()` | `$this->authorize('manage-ppdb');` | `$this->authorize('gelombang-ppdb.create');` |
| `edit()` | `$this->authorize('manage-ppdb');` | `$this->authorize('gelombang-ppdb.edit');` |
| `update()` | `$this->authorize('manage-ppdb');` | `$this->authorize('gelombang-ppdb.edit');` |

In `app/Http/Controllers/Admin/JalurPpdbController.php`:

| Method | Old | New |
|---|---|---|
| `index()` | `$this->authorize('manage-ppdb');` | `$this->authorize('jalur-ppdb.view');` |
| `create()` | `$this->authorize('manage-ppdb');` | `$this->authorize('jalur-ppdb.create');` |
| `store()` | `$this->authorize('manage-ppdb');` | `$this->authorize('jalur-ppdb.create');` |
| `edit()` | `$this->authorize('manage-ppdb');` | `$this->authorize('jalur-ppdb.edit');` |
| `update()` | `$this->authorize('manage-ppdb');` | `$this->authorize('jalur-ppdb.edit');` |

- [ ] **Step 2: Run the existing tests to confirm they now fail**

Run: `php artisan test tests/Feature/Admin/JenisTesMasterTest.php tests/Feature/Admin/GelombangPpdbTest.php tests/Feature/Admin/JalurPpdbTest.php`
Expected: FAIL — helpers still grant `manage-ppdb`.

- [ ] **Step 3a: Update `tests/Feature/Admin/JenisTesMasterTest.php`**

Replace both helper functions at the top of the file (leave every `it()` block unchanged):

```php
function buatAdminPpdb(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    foreach (['jenis-tes.view', 'jenis-tes.create', 'jenis-tes.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['jenis-tes.view', 'jenis-tes.create', 'jenis-tes.delete']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    return [$lembaga, $user];
}

function buatYayasanSuperAdminDenganLembagaAktif(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    foreach (['jenis-tes.view', 'jenis-tes.create', 'jenis-tes.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['jenis-tes.view', 'jenis-tes.create', 'jenis-tes.delete']);
    $user = User::factory()->create();
    $user->assignRole($role);

    test()->actingAs($user);
    test()->get('/dashboard?switch_lembaga='.$lembaga->id);

    return [$lembaga, $user];
}
```

- [ ] **Step 3b: Update `tests/Feature/Admin/GelombangPpdbTest.php`**

Replace the helper function at the top:

```php
function buatAdminPpdbDenganTahunAktif(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    foreach (['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);

    return [$lembaga, $user, $tahunAjaran];
}
```

Then, using `replace_all`, replace every remaining occurrence of:
```php
    Permission::firstOrCreate(['name' => 'manage-ppdb', 'guard_name' => 'web']);
```
with:
```php
    foreach (['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
```

Then update the two remaining grant lines individually (both use `$role->` in the empty-state test at the top, and `$yayasanRole->` in the three later tests):

Replace (first occurrence, inside the "shows an empty-state prompt" test):
```php
    $role->givePermissionTo('manage-ppdb');
```
with:
```php
    $role->givePermissionTo(['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit']);
```

Replace all 3 remaining occurrences (`replace_all`) of:
```php
    $yayasanRole->givePermissionTo('manage-ppdb');
```
with:
```php
    $yayasanRole->givePermissionTo(['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit']);
```

- [ ] **Step 3c: Update `tests/Feature/Admin/JalurPpdbTest.php`**

Replace the helper function at the top:

```php
function buatAdminJalur(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    foreach (['jalur-ppdb.view', 'jalur-ppdb.create', 'jalur-ppdb.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['jalur-ppdb.view', 'jalur-ppdb.create', 'jalur-ppdb.edit']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);

    return [$lembaga, $user, $tahunAjaran];
}
```

- [ ] **Step 4: Run the tests again to confirm they pass**

Run: `php artisan test tests/Feature/Admin/JenisTesMasterTest.php`
Expected: PASS (6 tests).

Run: `php artisan test tests/Feature/Admin/GelombangPpdbTest.php`
Expected: PASS (11 tests).

Run: `php artisan test tests/Feature/Admin/JalurPpdbTest.php`
Expected: PASS (7 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/JenisTesMasterController.php app/Http/Controllers/Admin/GelombangPpdbController.php app/Http/Controllers/Admin/JalurPpdbController.php tests/Feature/Admin/JenisTesMasterTest.php tests/Feature/Admin/GelombangPpdbTest.php tests/Feature/Admin/JalurPpdbTest.php
git commit -m "feat: migrate JenisTesMaster, GelombangPpdb, JalurPpdb controllers to granular permissions"
```

---

### Task 8: Formulir Field, Dokumen Syarat, Seleksi, SPMB Konfigurasi — granular per-action permissions

**Files:**
- Modify: `app/Http/Controllers/Admin/FormulirFieldController.php`
- Modify: `app/Http/Controllers/Admin/DokumenSyaratController.php`
- Modify: `app/Http/Controllers/Admin/SeleksiController.php`
- Modify: `app/Http/Controllers/Admin/SpmbKonfigurasiController.php`
- Test: `tests/Feature/Admin/FormulirFieldTest.php`
- Test: `tests/Feature/Admin/DokumenSyaratTest.php`
- Test: `tests/Feature/Admin/SeleksiTest.php`
- Test: `tests/Feature/Admin/SpmbKonfigurasiDuplikasiTest.php`

**Interfaces:**
- Consumes: `formulir-field.create/delete`, `dokumen-syarat.create/delete`, `seleksi.create/delete`, `spmb-konfigurasi.duplikasi` from Task 1.

- [ ] **Step 1: Update the four controllers' authorize calls**

In `app/Http/Controllers/Admin/FormulirFieldController.php`:

| Method | Old | New |
|---|---|---|
| `store()` | `$this->authorize('manage-ppdb');` | `$this->authorize('formulir-field.create');` |
| `destroy()` | `$this->authorize('manage-ppdb');` | `$this->authorize('formulir-field.delete');` |

In `app/Http/Controllers/Admin/DokumenSyaratController.php`:

| Method | Old | New |
|---|---|---|
| `store()` | `$this->authorize('manage-ppdb');` | `$this->authorize('dokumen-syarat.create');` |
| `destroy()` | `$this->authorize('manage-ppdb');` | `$this->authorize('dokumen-syarat.delete');` |

In `app/Http/Controllers/Admin/SeleksiController.php`:

| Method | Old | New |
|---|---|---|
| `store()` | `$this->authorize('manage-ppdb');` | `$this->authorize('seleksi.create');` |
| `destroy()` | `$this->authorize('manage-ppdb');` | `$this->authorize('seleksi.delete');` |

In `app/Http/Controllers/Admin/SpmbKonfigurasiController.php`:

| Method | Old | New |
|---|---|---|
| `duplikasi()` | `$this->authorize('manage-ppdb');` | `$this->authorize('spmb-konfigurasi.duplikasi');` |

- [ ] **Step 2: Run the existing tests to confirm they now fail**

Run: `php artisan test tests/Feature/Admin/FormulirFieldTest.php tests/Feature/Admin/DokumenSyaratTest.php tests/Feature/Admin/SeleksiTest.php tests/Feature/Admin/SpmbKonfigurasiDuplikasiTest.php`
Expected: FAIL — helpers still grant `manage-ppdb`.

- [ ] **Step 3a: Update the helper in `tests/Feature/Admin/FormulirFieldTest.php`**

Replace the `buatJalurUntukFormulir()` function:

```php
function buatJalurUntukFormulir(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    foreach (['formulir-field.create', 'formulir-field.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['formulir-field.create', 'formulir-field.delete']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Tahfidz']);

    return [$lembaga, $user, $jalur];
}
```

- [ ] **Step 3b: Update the helper in `tests/Feature/Admin/DokumenSyaratTest.php`**

Replace the `buatJalurUntukDokumen()` function:

```php
function buatJalurUntukDokumen(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    foreach (['dokumen-syarat.create', 'dokumen-syarat.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['dokumen-syarat.create', 'dokumen-syarat.delete']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Afirmasi']);

    return [$lembaga, $user, $jalur];
}
```

- [ ] **Step 3c: Update the helper in `tests/Feature/Admin/SeleksiTest.php`**

Replace the `buatKonteksSeleksi()` function:

```php
function buatKonteksSeleksi(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    foreach (['seleksi.create', 'seleksi.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['seleksi.create', 'seleksi.delete']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Prestasi']);
    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => '2026-08-01', 'tanggal_tutup' => '2026-09-01', 'kuota' => 40,
    ]);
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);

    return [$lembaga, $user, $tahunAjaran, $jalur, $gelombang, $jenisTes];
}
```

- [ ] **Step 3d: Update the helper in `tests/Feature/Admin/SpmbKonfigurasiDuplikasiTest.php`**

Replace the two lines inside `buatKonteksDuplikasi()`:

Replace:
```php
    Permission::firstOrCreate(['name' => 'manage-ppdb', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('manage-ppdb');
```
with:
```php
    Permission::firstOrCreate(['name' => 'spmb-konfigurasi.duplikasi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('spmb-konfigurasi.duplikasi');
```

- [ ] **Step 4: Run the tests again to confirm they pass**

Run: `php artisan test tests/Feature/Admin/FormulirFieldTest.php`
Expected: PASS (7 tests).

Run: `php artisan test tests/Feature/Admin/DokumenSyaratTest.php`
Expected: PASS (4 tests).

Run: `php artisan test tests/Feature/Admin/SeleksiTest.php`
Expected: PASS (5 tests).

Run: `php artisan test tests/Feature/Admin/SpmbKonfigurasiDuplikasiTest.php`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/FormulirFieldController.php app/Http/Controllers/Admin/DokumenSyaratController.php app/Http/Controllers/Admin/SeleksiController.php app/Http/Controllers/Admin/SpmbKonfigurasiController.php tests/Feature/Admin/FormulirFieldTest.php tests/Feature/Admin/DokumenSyaratTest.php tests/Feature/Admin/SeleksiTest.php tests/Feature/Admin/SpmbKonfigurasiDuplikasiTest.php
git commit -m "feat: migrate FormulirField, DokumenSyarat, Seleksi, SpmbKonfigurasi controllers to granular permissions"
```

---

### Task 9: Sidebar migration + full regression

**Files:**
- Modify: `resources/views/layouts/sidebar.blade.php`

**Interfaces:**
- Consumes: `lembaga.view`, `guru.view`, `tahun-ajaran.view`, `gelombang-ppdb.view`, `jalur-ppdb.view`, `jenis-tes.view`, `users.view`, `roles.view` — all produced by Tasks 1-8.

- [ ] **Step 1: Update the sidebar's `can()` checks**

In `resources/views/layouts/sidebar.blade.php`, replace the `$navGroups` array (lines 1-33) with:

```php
@php
    $navGroups = [
        [
            'label' => 'I. Ringkasan',
            'items' => [
                ['route' => 'dashboard', 'pattern' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard'],
            ],
        ],
        [
            'label' => 'II. Data Induk',
            'items' => array_filter([
                Auth::user()->can('lembaga.view') ? ['route' => 'admin.lembaga.index', 'pattern' => 'admin.lembaga.*', 'label' => 'Lembaga', 'icon' => 'apartment'] : null,
                Auth::user()->can('guru.view') ? ['route' => 'admin.guru.index', 'pattern' => 'admin.guru.*', 'label' => 'Guru', 'icon' => 'school'] : null,
                Auth::user()->can('tahun-ajaran.view') ? ['route' => 'admin.tahun-ajaran.index', 'pattern' => 'admin.tahun-ajaran.*', 'label' => 'Tahun Ajaran', 'icon' => 'calendar_month'] : null,
            ]),
        ],
        [
            'label' => 'III. SPMB',
            'items' => array_filter([
                Auth::user()->can('gelombang-ppdb.view') ? ['route' => 'admin.gelombang-ppdb.index', 'pattern' => 'admin.gelombang-ppdb.*', 'label' => 'Gelombang PPDB', 'icon' => 'waves'] : null,
                Auth::user()->can('jalur-ppdb.view') ? ['route' => 'admin.jalur-ppdb.index', 'pattern' => 'admin.jalur-ppdb.*', 'label' => 'Jalur PPDB', 'icon' => 'signpost'] : null,
                Auth::user()->can('jenis-tes.view') ? ['route' => 'admin.jenis-tes.index', 'pattern' => 'admin.jenis-tes.*', 'label' => 'Jenis Tes', 'icon' => 'quiz'] : null,
            ]),
        ],
        [
            'label' => 'IV. Akses & Peran',
            'items' => array_filter([
                Auth::user()->can('users.view') ? ['route' => 'admin.users.index', 'pattern' => 'admin.users.*', 'label' => 'Pengguna', 'icon' => 'group'] : null,
                Auth::user()->can('roles.view') ? ['route' => 'admin.roles.index', 'pattern' => 'admin.roles.*', 'label' => 'Peran', 'icon' => 'shield_person'] : null,
            ]),
        ],
    ];
@endphp
```

(Everything below the `@endphp` in the file is unchanged.)

- [ ] **Step 2: Run the full test suite**

Run: `php artisan test`
Expected: PASS — 0 failures. No test file references the sidebar directly, so this is a final safety check that nothing else in the app (e.g. a Dusk/browser test, if any exist) or a leftover reference was missed. If any test still fails, grep the whole repo for the old flat permission names to find what was missed:

Run: `grep -rn "manage-roles\|manage-users\|manage-yayasan\|manage-lembaga\|manage-tahun-ajaran\|manage-guru\|view-audit-log\|manage-ppdb" app resources tests database`
Expected: no matches.

- [ ] **Step 3: Commit**

```bash
git add resources/views/layouts/sidebar.blade.php
git commit -m "feat: migrate sidebar visibility checks to granular view permissions"
```

---

## Post-Plan Note

This plan covers the permission-model migration only (Phase A of the RBAC redesign spec). The Roles page's visual redesign — server-side datatable, Alpine.js/fetch no-reload CRUD, module-grouped permission matrix, and toast notifications, per spec §4 — is intentionally **out of scope** here and will be written as its own follow-up implementation plan once this one is merged, since the new UI's permission-picker depends on the granular permissions this plan creates.
