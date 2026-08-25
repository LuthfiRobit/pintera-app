# Perbaikan Halaman Peran Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menutup celah keamanan nama role protected yang bisa diubah bebas, memperbaiki dukungan scope `platform` yang terlewat di `RoleController` (mirror fix `UserController` sebelumnya), dan menyempurnakan UX halaman Peran (chip filter, format nama, link Users, tooltip Permissions, helper edukatif, live search matriks).

**Architecture:** Backend: 1 guard baru di model `Role`, `scopeRank()`+validasi diperluas ke `platform`, 2 count query baru + eager-load permission terbatas di `index()`. Frontend: chip filter reuse `dataTableFilter` (tanpa method Alpine baru), format tampilan nama, link/tooltip di `_daftar.blade.php`, live search client-side di `role-form.js`.

**Tech Stack:** Laravel 12, Pest, Alpine.js, Blade.

## Global Constraints

- Baseline kode: commit `de2068f` di branch `rbac-v2`. Kalau isi file berbeda signifikan dari baseline, STOP, laporkan ke user.
- Nama role (`Role.name`) untuk role `is_protected=true` TIDAK BOLEH berubah — dikunci di 3 lapis: model (`saving()` guard), controller (`update()` skip field), UI (`edit.blade.php` `:disabled`). Permission BOLEH tetap diubah untuk role protected (TIDAK dikunci, ini perilaku existing yang dipertahankan).
- `scope_level=platform` HANYA bisa dipilih/dilihat sebagai opsi oleh actor yang `widestScopeLevel() === 'platform'` — defense-in-depth di atas validasi rank yang sudah ada.
- Chip filter di halaman Peran berbasis kolom `scope_level` LANGSUNG (5 nilai: kosong/Semua, platform, yayasan, lembaga, diri_sendiri) — BUKAN taksonomi fungsional seperti chip halaman Pengguna.
- TIDAK mengubah `PermissionCatalog::grouped()`, `PermissionAuditService`, atau `UserController` (filter `role` di sana SUDAH ADA dan cukup).
- Test scoped SEBELUM commit. Full suite HANYA di task terakhir, izin eksplisit user dulu.

---

## Task 1: `Role.php` — Guard Nama Protected Tidak Bisa Diubah

**Files:**
- Modify: `app/Models/Role.php`
- Test: `tests/Unit/RoleModelGuardTest.php` (baru)

**Interfaces:**
- Produces: `Role::save()` melempar `RuntimeException` kalau role `is_protected` dan `name` diubah — dipakai Task 2 (controller) sebagai lapis pertahanan kedua.

- [ ] **Step 1: Baca ulang file existing, konfirmasi guard `saving()`/`deleting()` sama dengan baseline**

```bash
cat app/Models/Role.php
```
Baseline sudah dikutip lengkap di spec §4.1 — pastikan method `booted()` masih persis 2 guard (`saving` untuk `scope_level`, `deleting` untuk protected).

- [ ] **Step 2: Tulis test yang gagal dulu**

Buat file baru `tests/Unit/RoleModelGuardTest.php`:

```php
<?php

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('throws when saving a name change on a protected role', function () {
    $role = Role::create(['name' => 'guru', 'guard_name' => 'web', 'scope_level' => 'diri_sendiri', 'is_protected' => true]);

    $role->name = 'Guru Pengajar';

    expect(fn () => $role->save())->toThrow(RuntimeException::class, 'Nama role yang dilindungi tidak dapat diubah.');

    expect($role->fresh()->name)->toBe('guru');
});

it('still throws when saving a scope_level change on a protected role (regression, existing guard)', function () {
    $role = Role::create(['name' => 'yayasan_super_admin', 'guard_name' => 'web', 'scope_level' => 'yayasan', 'is_protected' => true]);

    $role->scope_level = 'lembaga';

    expect(fn () => $role->save())->toThrow(RuntimeException::class);
});

it('allows changing permissions (via syncPermissions) on a protected role without touching name/scope_level', function () {
    $role = Role::create(['name' => 'guru', 'guard_name' => 'web', 'scope_level' => 'diri_sendiri', 'is_protected' => true]);
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);

    $role->syncPermissions(['kasus.view']);

    expect($role->fresh()->hasPermissionTo('kasus.view'))->toBeTrue();
    expect($role->fresh()->name)->toBe('guru');
});

it('allows changing name freely on a non-protected role', function () {
    $role = Role::create(['name' => 'admin_perpustakaan', 'guard_name' => 'web', 'scope_level' => 'lembaga', 'is_protected' => false]);

    $role->name = 'admin_perpustakaan_v2';
    $role->save();

    expect($role->fresh()->name)->toBe('admin_perpustakaan_v2');
});
```

- [ ] **Step 3: Jalankan test, konfirmasi test pertama gagal**

```bash
php artisan test tests/Unit/RoleModelGuardTest.php
```
Expected: test `'throws when saving a name change on a protected role'` FAIL (belum ada guard untuk `name`, jadi tidak ada exception yang dilempar). 3 test lain kemungkinan sudah PASS (perilaku existing) — itu wajar, jadi baseline regresi.

- [ ] **Step 4: Tambah guard baru**

Ganti:
```php
    protected static function booted(): void
    {
        static::saving(function (Role $role) {
            if ($role->exists && $role->is_protected && $role->isDirty('scope_level')) {
                throw new RuntimeException('Scope level role yang dilindungi tidak dapat diubah.');
            }
        });

        static::deleting(function (Role $role) {
            if ($role->is_protected) {
                throw new RuntimeException('Role yang dilindungi tidak dapat dihapus.');
            }
        });
    }
```
Menjadi:
```php
    protected static function booted(): void
    {
        static::saving(function (Role $role) {
            if ($role->exists && $role->is_protected && $role->isDirty('scope_level')) {
                throw new RuntimeException('Scope level role yang dilindungi tidak dapat diubah.');
            }

            if ($role->exists && $role->is_protected && $role->isDirty('name')) {
                throw new RuntimeException('Nama role yang dilindungi tidak dapat diubah.');
            }
        });

        static::deleting(function (Role $role) {
            if ($role->is_protected) {
                throw new RuntimeException('Role yang dilindungi tidak dapat dihapus.');
            }
        });
    }
```

- [ ] **Step 5: Jalankan test, konfirmasi semua lulus**

```bash
php artisan test tests/Unit/RoleModelGuardTest.php
```
Expected: 4 test PASS.

- [ ] **Step 6: Jalankan test regresi `RoleBuilderTest.php` (khususnya test yang menyentuh update role protected)**

```bash
php artisan test tests/Feature/Admin/RoleBuilderTest.php
```
Expected: semua PASS — test `'does not let anyone change the scope_level of the protected super admin role'` mengirim `name` yang SAMA PERSIS (`'yayasan_super_admin'`, tidak berubah), jadi guard baru ini TIDAK memicu exception di test itu (guard hanya aktif kalau `name` benar-benar `isDirty`).

- [ ] **Step 7: Commit**

```bash
git add app/Models/Role.php tests/Unit/RoleModelGuardTest.php
git commit -m "fix(rbac): guard nama role protected tidak bisa diubah di level model"
```

---

## Task 2: `RoleController::update()` — Skip Field `name` untuk Role Protected

**Files:**
- Modify: `app/Http/Controllers/Admin/RoleController.php`
- Test: `tests/Feature/Admin/RoleBuilderTest.php`

**Interfaces:**
- Consumes: guard model Task 1 (lapis pertahanan kedua, controller ini lapis pertama yang mencegah field `name` bahkan diproses).

- [ ] **Step 1: Baca ulang method `update()` existing**

```bash
grep -n "public function update" -A 40 app/Http/Controllers/Admin/RoleController.php
```

- [ ] **Step 2: Tulis test yang gagal dulu**

Tambahkan ke `tests/Feature/Admin/RoleBuilderTest.php` (setelah test terakhir):

```php
it('ignores a submitted name change for a protected role instead of throwing a 500', function () {
    $admin = actingAsSuperAdmin();
    $protected = Role::where('name', 'yayasan_super_admin')->first();

    $this->actingAs($admin)->put(route('admin.roles.update', $protected), [
        'name' => 'Nama Baru Yang Dicoba',
        'permissions' => [],
    ])->assertRedirect(route('admin.roles.index'));

    expect($protected->fresh()->name)->toBe('yayasan_super_admin');
});

it('ignores a submitted name change for a protected role via AJAX, returning 200 not 500', function () {
    $admin = actingAsSuperAdmin();
    $protected = Role::where('name', 'yayasan_super_admin')->first();

    $response = $this->actingAs($admin)->putJson(route('admin.roles.update', $protected), [
        'name' => 'Nama Baru AJAX',
        'permissions' => [],
    ]);

    $response->assertOk();
    expect($protected->fresh()->name)->toBe('yayasan_super_admin');
});
```

- [ ] **Step 3: Jalankan test, konfirmasi gagal**

```bash
php artisan test tests/Feature/Admin/RoleBuilderTest.php --filter "ignores a submitted name change"
```
Expected: FAIL — saat ini `$role->name = $data['name']` dieksekusi tanpa syarat, lalu `$role->save()` melempar `RuntimeException` (dari Task 1) yang TIDAK di-catch di controller, menghasilkan HTTP 500 alih-alih redirect/200 bersih.

- [ ] **Step 4: Ubah method `update()`**

Ganti:
```php
    public function update(Request $request, Role $role): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $role);

        $rules = [
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,'.$role->id],
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];

        if (! $role->is_protected) {
            $rules['scope_level'] = ['required', 'in:yayasan,lembaga,diri_sendiri'];
        }

        $data = $request->validate($rules);

        $role->name = $data['name'];

        if (! $role->is_protected) {
            $actingRank = $this->scopeRank($request->user()->widestScopeLevel());
            if ($this->scopeRank($data['scope_level']) > $actingRank) {
                return $this->errorResponse(
                    $request,
                    'scope_level',
                    'Anda tidak dapat mengubah role ke scope lebih luas dari scope Anda sendiri.'
                );
            }
            $role->scope_level = $data['scope_level'];
        }

        $role->save();
        $role->syncPermissions(Permission::whereIn('id', $data['permissions'] ?? [])->get());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Role berhasil diperbarui.']);
        }

        return redirect()->route('admin.roles.index')->with('status', 'Role berhasil diperbarui.');
    }
```
Menjadi:
```php
    public function update(Request $request, Role $role): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $role);

        $rules = [
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];

        if (! $role->is_protected) {
            $rules['name'] = ['required', 'string', 'max:255', 'unique:roles,name,'.$role->id];
            $rules['scope_level'] = ['required', 'in:yayasan,lembaga,diri_sendiri,platform'];
        }

        $data = $request->validate($rules);

        if (! $role->is_protected) {
            $role->name = $data['name'];

            $actingRank = $this->scopeRank($request->user()->widestScopeLevel());
            if ($this->scopeRank($data['scope_level']) > $actingRank) {
                return $this->errorResponse(
                    $request,
                    'scope_level',
                    'Anda tidak dapat mengubah role ke scope lebih luas dari scope Anda sendiri.'
                );
            }
            $role->scope_level = $data['scope_level'];
        }

        $role->save();
        $role->syncPermissions(Permission::whereIn('id', $data['permissions'] ?? [])->get());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Role berhasil diperbarui.']);
        }

        return redirect()->route('admin.roles.index')->with('status', 'Role berhasil diperbarui.');
    }
```

- [ ] **Step 5: Verifikasi syntax**

```bash
php -l app/Http/Controllers/Admin/RoleController.php
```

- [ ] **Step 6: Jalankan test, konfirmasi lulus**

```bash
php artisan test tests/Feature/Admin/RoleBuilderTest.php
```
Expected: semua PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/RoleController.php tests/Feature/Admin/RoleBuilderTest.php
git commit -m "fix(rbac): RoleController update() skip field name untuk role protected, jangan proses sama sekali"
```

---

## Task 3: `RoleController::scopeRank()` & Validasi `store()` — Dukung Scope `platform`

**Files:**
- Modify: `app/Http/Controllers/Admin/RoleController.php`
- Test: `tests/Feature/Admin/RoleBuilderTest.php`

**Interfaces:**
- Produces: `scopeRank('platform') === 4`, validasi `store()`/`update()` menerima `scope_level=platform`.

- [ ] **Step 1: Baca ulang `scopeRank()` dan validasi `store()` existing**

```bash
grep -n "private function scopeRank" -A 6 app/Http/Controllers/Admin/RoleController.php
grep -n "'scope_level' => \['required'" app/Http/Controllers/Admin/RoleController.php
```

- [ ] **Step 2: Tulis test yang gagal dulu**

Tambahkan ke `tests/Feature/Admin/RoleBuilderTest.php`:

```php
it('lets a platform_super_admin create a role with scope_level platform', function () {
    foreach (['roles.view', 'roles.create', 'roles.edit', 'roles.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $platformRole = Role::firstOrCreate(['name' => 'platform_super_admin', 'guard_name' => 'web'], ['scope_level' => 'platform', 'is_protected' => true]);
    $platformRole->givePermissionTo(['roles.view', 'roles.create', 'roles.edit', 'roles.delete']);

    $admin = User::factory()->create();
    $admin->assignRole($platformRole);

    $this->actingAs($admin)->post(route('admin.roles.store'), [
        'name' => 'admin_platform_baru',
        'scope_level' => 'platform',
        'permissions' => [],
    ])->assertRedirect(route('admin.roles.index'));

    $created = Role::where('name', 'admin_platform_baru')->first();
    expect($created->scope_level)->toBe('platform');
});

it('refuses to let a yayasan-scoped role-manager create a platform-scoped role', function () {
    $admin = actingAsSuperAdmin();

    $this->actingAs($admin)->post(route('admin.roles.store'), [
        'name' => 'sneaky_platform',
        'scope_level' => 'platform',
        'permissions' => [],
    ])->assertSessionHasErrors('scope_level');

    expect(Role::where('name', 'sneaky_platform')->exists())->toBeFalse();
});
```

- [ ] **Step 3: Jalankan test, konfirmasi test pertama gagal**

```bash
php artisan test tests/Feature/Admin/RoleBuilderTest.php --filter "scope_level platform"
```
Expected: FAIL — validasi `'in:yayasan,lembaga,diri_sendiri'` saat ini menolak `platform` sebelum sempat mengecek rank sama sekali.

- [ ] **Step 4: Ubah `scopeRank()`**

Ganti:
```php
    private function scopeRank(string $level): int
    {
        return match ($level) {
            'yayasan' => 3,
            'lembaga' => 2,
            default => 1, // diri_sendiri
        };
    }
```
Menjadi:
```php
    private function scopeRank(string $level): int
    {
        return match ($level) {
            'platform' => 4,
            'yayasan' => 3,
            'lembaga' => 2,
            default => 1, // diri_sendiri
        };
    }
```

- [ ] **Step 5: Ubah validasi di `store()`**

Ganti:
```php
            'scope_level' => ['required', 'in:yayasan,lembaga,diri_sendiri'],
```
(baris di dalam method `store()`) menjadi:
```php
            'scope_level' => ['required', 'in:yayasan,lembaga,diri_sendiri,platform'],
```

- [ ] **Step 6: Konfirmasi validasi di `update()` (hasil Task 2) juga sudah `platform`**

```bash
grep -n "in:yayasan,lembaga,diri_sendiri" app/Http/Controllers/Admin/RoleController.php
```
Expected: 2 kemunculan (satu di `store()`, satu di `update()` — keduanya SUDAH `,platform` kalau Task 2 dan Step 5 di atas sudah benar). Kalau hanya 1 kemunculan tersisa tanpa `,platform`, perbaiki juga.

- [ ] **Step 7: Verifikasi syntax**

```bash
php -l app/Http/Controllers/Admin/RoleController.php
```

- [ ] **Step 8: Jalankan test, konfirmasi lulus**

```bash
php artisan test tests/Feature/Admin/RoleBuilderTest.php
```
Expected: semua PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Admin/RoleController.php tests/Feature/Admin/RoleBuilderTest.php
git commit -m "fix(rbac): scopeRank() dan validasi scope_level RoleController dukung platform"
```

---

## Task 4: `RoleController::create()`/`edit()` — Passing `$isPlatformActor`, Blade Opsi Platform Kondisional

**Files:**
- Modify: `app/Http/Controllers/Admin/RoleController.php`
- Modify: `resources/views/admin/roles/create.blade.php`
- Modify: `resources/views/admin/roles/edit.blade.php`
- Test: `tests/Feature/Admin/RoleBuilderTest.php`

**Interfaces:**
- Produces: view `admin.roles.create`/`admin.roles.edit` menerima `$isPlatformActor` (bool).

- [ ] **Step 1: Baca ulang method `create()`/`edit()` existing**

```bash
grep -n "public function create\|public function edit" -A 10 app/Http/Controllers/Admin/RoleController.php
```

- [ ] **Step 2: Ganti method `create()`**

Ganti:
```php
    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('admin.roles.create', ['moduleGroups' => PermissionCatalog::grouped()]);
    }
```
Menjadi:
```php
    public function create(Request $request): View
    {
        $this->authorize('create', Role::class);

        return view('admin.roles.create', [
            'moduleGroups' => PermissionCatalog::grouped(),
            'isPlatformActor' => $request->user()->widestScopeLevel() === 'platform',
        ]);
    }
```

- [ ] **Step 3: Ganti method `edit()`**

Ganti:
```php
    public function edit(Role $role): View
    {
        $this->authorize('update', $role);

        return view('admin.roles.edit', [
            'role' => $role,
            'moduleGroups' => PermissionCatalog::grouped(),
            'checkedIds' => $role->permissions->pluck('id')->all(),
        ]);
    }
```
Menjadi:
```php
    public function edit(Request $request, Role $role): View
    {
        $this->authorize('update', $role);

        return view('admin.roles.edit', [
            'role' => $role,
            'moduleGroups' => PermissionCatalog::grouped(),
            'checkedIds' => $role->permissions->pluck('id')->all(),
            'isPlatformActor' => $request->user()->widestScopeLevel() === 'platform',
        ]);
    }
```

- [ ] **Step 4: Ganti dropdown scope di `create.blade.php`**

Ganti:
```blade
                        <x-select x-model="scopeLevel" class="mt-1.5">
                            <option value="yayasan">Yayasan</option>
                            <option value="lembaga">Lembaga</option>
                            <option value="diri_sendiri">Diri Sendiri</option>
                        </x-select>
```
Menjadi:
```blade
                        <x-select x-model="scopeLevel" class="mt-1.5">
                            @if ($isPlatformActor)
                                <option value="platform">Platform</option>
                            @endif
                            <option value="yayasan">Yayasan</option>
                            <option value="lembaga">Lembaga</option>
                            <option value="diri_sendiri">Diri Sendiri</option>
                        </x-select>
```

- [ ] **Step 5: Ganti dropdown scope di `edit.blade.php`**

Ganti:
```blade
                        <template x-if="!isProtected">
                            <x-select x-model="scopeLevel" class="mt-1.5">
                                <option value="yayasan">Yayasan</option>
                                <option value="lembaga">Lembaga</option>
                                <option value="diri_sendiri">Diri Sendiri</option>
                            </x-select>
                        </template>
```
Menjadi:
```blade
                        <template x-if="!isProtected">
                            <x-select x-model="scopeLevel" class="mt-1.5">
                                @if ($isPlatformActor)
                                    <option value="platform">Platform</option>
                                @endif
                                <option value="yayasan">Yayasan</option>
                                <option value="lembaga">Lembaga</option>
                                <option value="diri_sendiri">Diri Sendiri</option>
                            </x-select>
                        </template>
```

- [ ] **Step 6: Tulis test**

Tambahkan ke `tests/Feature/Admin/RoleBuilderTest.php`:

```php
it('hides the Platform scope option from a non-platform actor on the create page', function () {
    $admin = actingAsSuperAdmin();

    $response = $this->actingAs($admin)->get(route('admin.roles.create'));

    $response->assertOk();
    $response->assertDontSee('<option value="platform">Platform</option>', false);
});

it('shows the Platform scope option to a platform_super_admin actor on the create page', function () {
    foreach (['roles.view', 'roles.create', 'roles.edit', 'roles.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $platformRole = Role::firstOrCreate(['name' => 'platform_super_admin', 'guard_name' => 'web'], ['scope_level' => 'platform', 'is_protected' => true]);
    $platformRole->givePermissionTo(['roles.view', 'roles.create', 'roles.edit', 'roles.delete']);
    $admin = User::factory()->create();
    $admin->assignRole($platformRole);

    $response = $this->actingAs($admin)->get(route('admin.roles.create'));

    $response->assertOk();
    $response->assertSee('<option value="platform">Platform</option>', false);
});
```

- [ ] **Step 7: Jalankan test, konfirmasi lulus**

```bash
php artisan test tests/Feature/Admin/RoleBuilderTest.php
```
Expected: semua PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/RoleController.php resources/views/admin/roles/create.blade.php resources/views/admin/roles/edit.blade.php tests/Feature/Admin/RoleBuilderTest.php
git commit -m "feat(rbac): opsi scope Platform di form Peran hanya tampil untuk actor platform_super_admin"
```

---

## Task 5: `edit.blade.php` — Kunci Input Nama untuk Role Protected

**Files:**
- Modify: `resources/views/admin/roles/edit.blade.php`
- Test: `tests/Feature/Admin/RoleBuilderTest.php`

- [ ] **Step 1: Baca ulang blok input nama existing**

```bash
grep -n "x-model=\"name\"" resources/views/admin/roles/edit.blade.php
```

- [ ] **Step 2: Ganti input nama**

Ganti:
```blade
                        <x-text-input type="text" x-model="name" class="mt-1.5" placeholder="Contoh: Admin Akademik" />
```
Menjadi:
```blade
                        <x-text-input type="text" x-model="name" :disabled="isProtected" :class="isProtected ? 'mt-1.5 cursor-not-allowed bg-gray-50 text-gray-500' : 'mt-1.5'" placeholder="Contoh: Admin Akademik" />
                        <p x-show="isProtected" class="mt-1 text-[11px] text-gray-400">Nama role bawaan sistem tidak dapat diubah.</p>
```

- [ ] **Step 3: Tulis test render (verifikasi atribut `disabled` muncul di HTML)**

Tambahkan ke `tests/Feature/Admin/RoleBuilderTest.php`:

```php
it('renders the name input as disabled when editing a protected role', function () {
    $admin = actingAsSuperAdmin();
    $protected = Role::where('name', 'yayasan_super_admin')->first();

    $response = $this->actingAs($admin)->get(route('admin.roles.edit', $protected));

    $response->assertOk();
    $response->assertSee(':disabled="isProtected"', false);
});
```

- [ ] **Step 4: Jalankan test, konfirmasi lulus**

```bash
php artisan test tests/Feature/Admin/RoleBuilderTest.php
```
Expected: semua PASS. Catatan: test ini hanya memverifikasi markup Alpine ada di HTML (bukan hasil evaluasi JS `isProtected` sungguhan, karena Pest tidak menjalankan JS) — cukup untuk regresi "atribut binding tidak sengaja terhapus", verifikasi perilaku JS sungguhan (input benar-benar tidak bisa diketik) masuk checklist manual di Task 9.

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/roles/edit.blade.php tests/Feature/Admin/RoleBuilderTest.php
git commit -m "feat(rbac): kunci input nama role di form edit untuk role protected"
```

---

## Task 6: `RoleController::index()` — Count Platform/Diri Sendiri, Eager-Load Permission Terbatas

**Files:**
- Modify: `app/Http/Controllers/Admin/RoleController.php`
- Test: `tests/Feature/Admin/RoleBuilderTest.php`

**Interfaces:**
- Produces: view `admin.roles.index` menerima `$totalPlatform`, `$totalDiriSendiri` (int) — dipakai Task 7 (Blade stat card + chip).

- [ ] **Step 1: Baca ulang method `index()` existing**

```bash
grep -n "public function index" -A 40 app/Http/Controllers/Admin/RoleController.php
```

- [ ] **Step 2: Ganti method `index()`**

Ganti:
```php
    public function index(Request $request): View|\Illuminate\Http\JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $query = Role::withCount(['users', 'permissions']);

        if ($search = trim((string) $request->string('search'))) {
            $query->where('name', 'like', '%'.$search.'%');
        }
        if ($scope = $request->string('scope')->value()) {
            $query->where('scope_level', $scope);
        }

        $query->orderBy('name', 'asc');
        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;

        $roles = $query->paginate($perPage)->withQueryString();
        
        $totalRoles = Role::count();
        $totalYayasan = Role::where('scope_level', 'yayasan')->count();
        $totalLembaga = Role::where('scope_level', 'lembaga')->count();

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('admin.roles._daftar', [
                'roles' => $roles, 
                'perPage' => $perPage
            ]);
        }

        return view('admin.roles.index', [
            'roles' => $roles,
            'perPage' => $perPage,
            'search' => $search,
            'scope' => $scope,
            'totalRoles' => $totalRoles,
            'totalYayasan' => $totalYayasan,
            'totalLembaga' => $totalLembaga,
        ]);
    }
```
Menjadi:
```php
    public function index(Request $request): View|\Illuminate\Http\JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $query = Role::withCount(['users', 'permissions'])
            ->with(['permissions' => fn ($q) => $q->orderBy('name')->limit(5)]);

        if ($search = trim((string) $request->string('search'))) {
            $query->where('name', 'like', '%'.$search.'%');
        }
        if ($scope = $request->string('scope')->value()) {
            $query->where('scope_level', $scope);
        }

        $query->orderBy('name', 'asc');
        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;

        $roles = $query->paginate($perPage)->withQueryString();

        $totalRoles = Role::count();
        $totalPlatform = Role::where('scope_level', 'platform')->count();
        $totalYayasan = Role::where('scope_level', 'yayasan')->count();
        $totalLembaga = Role::where('scope_level', 'lembaga')->count();
        $totalDiriSendiri = Role::where('scope_level', 'diri_sendiri')->count();

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('admin.roles._daftar', [
                'roles' => $roles,
                'perPage' => $perPage,
            ]);
        }

        return view('admin.roles.index', [
            'roles' => $roles,
            'perPage' => $perPage,
            'search' => $search,
            'scope' => $scope,
            'totalRoles' => $totalRoles,
            'totalPlatform' => $totalPlatform,
            'totalYayasan' => $totalYayasan,
            'totalLembaga' => $totalLembaga,
            'totalDiriSendiri' => $totalDiriSendiri,
        ]);
    }
```

- [ ] **Step 3: Tulis test**

Tambahkan ke `tests/Feature/Admin/RoleBuilderTest.php`:

```php
it('counts roles per scope level including platform and diri_sendiri on the index page', function () {
    $admin = actingAsSuperAdmin();
    Role::firstOrCreate(['name' => 'platform_super_admin', 'guard_name' => 'web'], ['scope_level' => 'platform', 'is_protected' => true]);
    Role::create(['name' => 'guru_uji', 'guard_name' => 'web', 'scope_level' => 'diri_sendiri']);

    $response = $this->actingAs($admin)->get(route('admin.roles.index'));

    $response->assertOk();
    $response->assertViewHas('totalPlatform', 1);
    $response->assertViewHas('totalDiriSendiri', 1);
});
```

- [ ] **Step 4: Verifikasi syntax**

```bash
php -l app/Http/Controllers/Admin/RoleController.php
```

- [ ] **Step 5: Jalankan test, konfirmasi lulus**

```bash
php artisan test tests/Feature/Admin/RoleBuilderTest.php
```
Expected: semua PASS. Test lama yang render `admin.roles.index` tanpa memakai `$totalPlatform`/`$totalDiriSendiri` TETAP PASS (variabel baru, tidak menghapus yang lama).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/RoleController.php tests/Feature/Admin/RoleBuilderTest.php
git commit -m "feat(rbac): RoleController index() hitung scope platform & diri_sendiri, eager-load permission terbatas"
```

---

## Task 7: `index.blade.php` — Chip Filter Scope & 5 Stat Card

**Files:**
- Modify: `resources/views/admin/roles/index.blade.php`

**Interfaces:**
- Consumes: `$totalRoles`, `$totalPlatform`, `$totalYayasan`, `$totalLembaga`, `$totalDiriSendiri` (Task 6).

- [ ] **Step 1: Baca ulang file existing**

```bash
cat resources/views/admin/roles/index.blade.php
```

- [ ] **Step 2: Ganti grid stat card (3 kolom → 5 kolom, tambah 2 kartu)**

Ganti:
```blade
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                        <x-icon name="shield" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Roles</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalRoles }}</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <x-icon name="domain" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-indigo-600">Scope Yayasan</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalYayasan }}</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-50 text-green-600">
                        <x-icon name="school" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-green-600">Scope Lembaga</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalLembaga }}</p>
                    </div>
                </div>
            </div>
        </div>
```
Menjadi:
```blade
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                        <x-icon name="shield" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Roles</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalRoles }}</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-purple-50 text-purple-600">
                        <x-icon name="public" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-purple-600">Scope Platform</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalPlatform }}</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <x-icon name="domain" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-indigo-600">Scope Yayasan</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalYayasan }}</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-50 text-green-600">
                        <x-icon name="school" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-green-600">Scope Lembaga</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalLembaga }}</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <x-icon name="person" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-amber-600">Diri Sendiri</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalDiriSendiri }}</p>
                    </div>
                </div>
            </div>
        </div>
```

- [ ] **Step 3: Ganti select scope jadi chip**

Ganti:
```blade
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
                <div class="lg:col-span-2">
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Cari Role</label>
                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                        <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                        <input x-model="filters.search" @input.debounce.500ms="muatUlangDaftar()" type="text" placeholder="Cari nama role..." class="w-full border-0 bg-transparent p-0 text-xs sm:text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0">
                    </div>
                </div>
                <div class="lg:col-span-2">
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Scope Level</label>
                    <select x-model="filters.scope" @change="muatUlangDaftar()" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Semua Scope</option>
                        <option value="yayasan">Yayasan</option>
                        <option value="lembaga">Lembaga</option>
                    </select>
                </div>
            </div>
```
Menjadi:
```blade
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Cari Role</label>
                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                        <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                        <input x-model="filters.search" @input.debounce.500ms="muatUlangDaftar()" type="text" placeholder="Cari nama role..." class="w-full border-0 bg-transparent p-0 text-xs sm:text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0">
                    </div>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-2 overflow-x-auto border-t border-gray-100 pt-3">
                @php
                    $scopeChipLabels = [
                        '' => 'Semua',
                        'platform' => 'Platform',
                        'yayasan' => 'Yayasan',
                        'lembaga' => 'Lembaga',
                        'diri_sendiri' => 'Diri Sendiri',
                    ];
                    $scopeChipCounts = [
                        '' => $totalRoles,
                        'platform' => $totalPlatform,
                        'yayasan' => $totalYayasan,
                        'lembaga' => $totalLembaga,
                        'diri_sendiri' => $totalDiriSendiri,
                    ];
                @endphp
                @foreach ($scopeChipLabels as $chipValue => $chipLabel)
                    <button
                        type="button"
                        @click="filters.scope = @js($chipValue); muatUlangDaftar()"
                        :class="(filters.scope ?? '') === @js($chipValue) ? 'bg-brand-50 font-semibold text-brand-600 border-brand-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border-gray-200'"
                        class="flex items-center gap-2 whitespace-nowrap rounded-lg border px-3.5 py-1.5 text-xs transition-all"
                    >
                        <span>{{ $chipLabel }}</span>
                        <span
                            :class="(filters.scope ?? '') === @js($chipValue) ? 'bg-brand-100 text-brand-700' : 'bg-gray-200 text-gray-700'"
                            class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                        >{{ $scopeChipCounts[$chipValue] }}</span>
                    </button>
                @endforeach
            </div>
```

- [ ] **Step 4: Verifikasi tidak ada sisa `<select ... filters.scope`**

```bash
grep -n "filters.scope" resources/views/admin/roles/index.blade.php
```
Expected: kemunculan HANYA di dalam `@click` chip baru (Step 3), TIDAK ada lagi `<select x-model="filters.scope"`.

- [ ] **Step 5: Jalankan test regresi**

```bash
php artisan test tests/Feature/Admin/RoleBuilderTest.php
```
Expected: semua PASS (test `'filters the index fragment by search and scope'` sudah menguji lewat query param langsung ke `_daftar` fragment, TIDAK bergantung pada markup select/chip di `index.blade.php`, jadi tidak terpengaruh perubahan UI ini).

- [ ] **Step 6: Commit**

```bash
git add resources/views/admin/roles/index.blade.php
git commit -m "feat(rbac): chip filter scope_level (5 chip) + stat card Platform/Diri Sendiri di halaman Peran"
```

---

## Task 8: `_daftar.blade.php` — Format Nama, Link Users, Tooltip Permissions

**Files:**
- Modify: `resources/views/admin/roles/_daftar.blade.php`
- Test: `tests/Feature/Admin/RoleBuilderTest.php`

**Interfaces:**
- Consumes: eager-load `permissions` terbatas (Task 6), komponen `<x-tooltip>` (existing).

- [ ] **Step 1: Baca ulang file existing**

```bash
cat resources/views/admin/roles/_daftar.blade.php
```

- [ ] **Step 2: Format nama role**

Ganti:
```blade
                    <td class="px-5 py-3 align-top">
                        <p class="font-medium text-gray-900">{{ $role->name }}</p>
                        @if ($role->is_protected)
                            <span class="mt-1 inline-flex items-center rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-bold text-brand-700 ring-1 ring-brand-600/20">Protected</span>
                        @endif
                    </td>
```
Menjadi:
```blade
                    <td class="px-5 py-3 align-top">
                        <p class="font-medium text-gray-900">{{ ucwords(str_replace('_', ' ', $role->name)) }}</p>
                        @if ($role->is_protected)
                            <span class="mt-1 inline-flex items-center rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-bold text-brand-700 ring-1 ring-brand-600/20">Protected</span>
                        @endif
                    </td>
```

- [ ] **Step 3: Kolom Users jadi link**

Ganti:
```blade
                    <td class="px-5 py-3 align-top font-mono text-gray-600">{{ $role->users_count }}</td>
```
Menjadi:
```blade
                    <td class="px-5 py-3 align-top font-mono text-gray-600">
                        <a href="{{ route('admin.users.index', ['role' => $role->name]) }}" class="text-brand-600 hover:underline">{{ $role->users_count }}</a>
                    </td>
```

- [ ] **Step 4: Kolom Permissions jadi tooltip**

Ganti:
```blade
                    <td class="px-5 py-3 align-top font-mono text-gray-600">{{ $role->permissions_count }}</td>
```
Menjadi:
```blade
                    <td class="px-5 py-3 align-top font-mono text-gray-600">
                        @php
                            $previewNames = $role->permissions->pluck('name')->implode(', ');
                            $sisa = max(0, $role->permissions_count - $role->permissions->count());
                            $tooltipText = $role->permissions_count > 0
                                ? $previewNames . ($sisa > 0 ? ", +{$sisa} lainnya" : '')
                                : 'Belum ada permission';
                        @endphp
                        <x-tooltip :text="$tooltipText">
                            <span class="cursor-help border-b border-dashed border-gray-300">{{ $role->permissions_count }}</span>
                        </x-tooltip>
                    </td>
```

- [ ] **Step 5: Tulis test**

Tambahkan ke `tests/Feature/Admin/RoleBuilderTest.php`:

```php
it('displays the role name in title case while keeping the raw name as the underlying value', function () {
    $admin = actingAsSuperAdmin();
    Role::create(['name' => 'admin_perpustakaan', 'guard_name' => 'web', 'scope_level' => 'lembaga']);

    $response = $this->actingAs($admin)->get(route('admin.roles.index'));

    $response->assertOk();
    $response->assertSee('Admin Perpustakaan');
});

it('links the users count to the Pengguna page filtered by this role name', function () {
    $admin = actingAsSuperAdmin();
    $role = Role::create(['name' => 'admin_perpustakaan', 'guard_name' => 'web', 'scope_level' => 'lembaga']);

    $response = $this->actingAs($admin)->get(route('admin.roles.index'));

    $response->assertOk();
    $response->assertSee(route('admin.users.index', ['role' => $role->name]), false);
});

it('shows a permissions tooltip listing the first permission names plus a remainder count', function () {
    $admin = actingAsSuperAdmin();
    $role = Role::create(['name' => 'admin_perpustakaan', 'guard_name' => 'web', 'scope_level' => 'lembaga']);
    foreach (['a.view', 'b.view', 'c.view', 'd.view', 'e.view', 'f.view'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role->givePermissionTo(['a.view', 'b.view', 'c.view', 'd.view', 'e.view', 'f.view']);

    $response = $this->actingAs($admin)->get(route('admin.roles.index'));

    $response->assertOk();
    $response->assertSee('+1 lainnya');
});
```

- [ ] **Step 6: Jalankan test, konfirmasi lulus**

```bash
php artisan test tests/Feature/Admin/RoleBuilderTest.php
```
Expected: semua PASS. Kalau test tooltip gagal karena urutan alfabetis permission tidak seperti diasumsikan, verifikasi lewat tinker dulu (6 permission `a.view`..`f.view` diurutkan `orderBy('name')`, ambil 5 pertama, sisa 1 — seharusnya `+1 lainnya` cocok, tapi cek dulu kalau ada permission lain yang sudah ter-seed sebelumnya di test environment yang bisa mengacaukan hitungan).

- [ ] **Step 7: Commit**

```bash
git add resources/views/admin/roles/_daftar.blade.php tests/Feature/Admin/RoleBuilderTest.php
git commit -m "feat(rbac): format nama Title Case, link Users ke Pengguna terfilter, tooltip preview Permissions"
```

---

## Task 9: Helper Edukatif Scope Level & Live Search Matriks Permission

**Files:**
- Modify: `resources/views/admin/roles/create.blade.php`
- Modify: `resources/views/admin/roles/edit.blade.php`
- Modify: `resources/views/admin/roles/_permission-matrix.blade.php`
- Modify: `resources/js/role-form.js`

**Interfaces:**
- Consumes: `moduleGroups` (existing, dari `roleForm()` Alpine component).
- Produces: `roleForm()` sekarang punya state `permissionSearch` dan method `filteredModuleGroups()` — dipakai `_permission-matrix.blade.php`.

- [ ] **Step 1: Baca ulang `role-form.js` existing**

```bash
cat resources/js/role-form.js
```

- [ ] **Step 2: Tambah state dan method baru**

Ganti:
```js
export function roleForm(config) {
    return {
        name: config.initialName,
        scopeLevel: config.initialScopeLevel,
        isProtected: config.initialIsProtected,
        moduleGroups: config.initialModuleGroups,
        checkedIds: [...config.initialCheckedIds],
        catalogUrl: config.catalogUrl,
        submitUrl: config.submitUrl,
        method: config.method,
        indexUrl: config.indexUrl,
        errors: {},
        submitting: false,
        syncing: false,
        auditMissingFromDatabase: [],
        auditUnusedInCode: [],
        showAuditBanner: false,

        isChecked(id) {
```
Menjadi:
```js
export function roleForm(config) {
    return {
        name: config.initialName,
        scopeLevel: config.initialScopeLevel,
        isProtected: config.initialIsProtected,
        moduleGroups: config.initialModuleGroups,
        checkedIds: [...config.initialCheckedIds],
        catalogUrl: config.catalogUrl,
        submitUrl: config.submitUrl,
        method: config.method,
        indexUrl: config.indexUrl,
        errors: {},
        submitting: false,
        syncing: false,
        auditMissingFromDatabase: [],
        auditUnusedInCode: [],
        showAuditBanner: false,
        permissionSearch: '',

        filteredModuleGroups() {
            const query = this.permissionSearch.trim().toLowerCase();
            if (!query) return this.moduleGroups;

            return this.moduleGroups
                .map((group) => ({
                    ...group,
                    permissions: group.permissions.filter((permission) =>
                        permission.label.toLowerCase().includes(query) || permission.name.toLowerCase().includes(query)
                    ),
                }))
                .filter((group) => group.permissions.length > 0);
        },

        isChecked(id) {
```

- [ ] **Step 3: Verifikasi build**

```bash
npm run build
```
Expected: sukses tanpa error.

- [ ] **Step 4: Tambah input search di `_permission-matrix.blade.php`**

Ganti:
```blade
        <div class="flex items-center justify-between border-b border-gray-100 pb-4">
            <div>
                <h3 class="font-display text-lg font-bold text-gray-900">Matriks Hak Akses</h3>
                <p class="text-xs text-gray-500">Pilih izin (permissions) yang diizinkan untuk peran ini.</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="selectAll()" class="inline-flex items-center gap-1.5 rounded-lg border border-brand-200 bg-brand-50 px-3 py-1.5 text-xs font-bold text-brand-700 transition hover:bg-brand-100">
```
Menjadi:
```blade
        <div class="flex flex-col gap-3 border-b border-gray-100 pb-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="font-display text-lg font-bold text-gray-900">Matriks Hak Akses</h3>
                <p class="text-xs text-gray-500">Pilih izin (permissions) yang diizinkan untuk peran ini.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative">
                    <input type="text" x-model="permissionSearch" placeholder="Cari permission... (mis. tagihan, rapor)" class="w-64 rounded-lg border-gray-200 bg-gray-50 py-1.5 pl-8 pr-3 text-xs focus:border-brand-500 focus:ring-brand-500">
                    <x-icon name="search" class="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                </div>
                <button type="button" @click="selectAll()" class="inline-flex items-center gap-1.5 rounded-lg border border-brand-200 bg-brand-50 px-3 py-1.5 text-xs font-bold text-brand-700 transition hover:bg-brand-100">
```

- [ ] **Step 5: Ganti sumber data `x-for` grid modul**

Ganti:
```blade
            <template x-for="group in moduleGroups" :key="group.module">
```
Menjadi:
```blade
            <template x-for="group in filteredModuleGroups()" :key="group.module">
```

- [ ] **Step 6: Tambah blok edukatif scope level di `create.blade.php`**

Tambahkan PERSIS SETELAH penutup `</x-select>` (dan sebelum `<p x-show="errors.scope_level"` yang sudah ada) di `create.blade.php`:
```blade
                        <div class="mt-2 space-y-1.5 rounded-lg bg-gray-50 p-3 text-[11px] text-gray-500">
                            @if ($isPlatformActor)
                                <p><strong class="text-gray-700">Platform:</strong> Akses lintas SEMUA yayasan (hanya untuk admin sistem tertinggi).</p>
                            @endif
                            <p><strong class="text-gray-700">Yayasan:</strong> Akses ke semua lembaga dalam 1 yayasan.</p>
                            <p><strong class="text-gray-700">Lembaga:</strong> Akses terbatas ke 1 lembaga/sekolah spesifik.</p>
                            <p><strong class="text-gray-700">Diri Sendiri:</strong> Akses terbatas ke data milik sendiri (mis. guru, siswa, orang tua).</p>
                        </div>
```

- [ ] **Step 7: Tambah blok edukatif scope level di `edit.blade.php`**

Tambahkan PERSIS SETELAH penutup `</template>` dari `x-if="!isProtected"` (dan sebelum `<p x-show="errors.scope_level"` yang sudah ada) di `edit.blade.php`:
```blade
                        <div x-show="!isProtected" class="mt-2 space-y-1.5 rounded-lg bg-gray-50 p-3 text-[11px] text-gray-500">
                            @if ($isPlatformActor)
                                <p><strong class="text-gray-700">Platform:</strong> Akses lintas SEMUA yayasan (hanya untuk admin sistem tertinggi).</p>
                            @endif
                            <p><strong class="text-gray-700">Yayasan:</strong> Akses ke semua lembaga dalam 1 yayasan.</p>
                            <p><strong class="text-gray-700">Lembaga:</strong> Akses terbatas ke 1 lembaga/sekolah spesifik.</p>
                            <p><strong class="text-gray-700">Diri Sendiri:</strong> Akses terbatas ke data milik sendiri (mis. guru, siswa, orang tua).</p>
                        </div>
```

- [ ] **Step 8: Jalankan test regresi halaman Peran**

```bash
php artisan test tests/Feature/Admin/RoleBuilderTest.php tests/Feature/Admin/PermissionAuditTest.php tests/Feature/Admin/RoleFormAuditBannerTest.php
```
Expected: semua PASS (perubahan ini murni tambahan UI, tidak mengubah kontrak data `roleForm()` yang dipakai test existing).

- [ ] **Step 9: Commit**

```bash
git add resources/views/admin/roles/create.blade.php resources/views/admin/roles/edit.blade.php resources/views/admin/roles/_permission-matrix.blade.php resources/js/role-form.js
git commit -m "feat(rbac): live search matriks permission + blok edukatif scope level di form Peran"
```

---

## Task 10: Verifikasi Manual Live Search & Checklist Browser

**Files:**
- Tidak ada file baru — task ini murni verifikasi manual (fitur client-side Alpine, tidak bisa diuji penuh via Pest).

- [ ] **Step 1: Jalankan dev server**

```bash
npm run build
php artisan serve
```

- [ ] **Step 2: Checklist manual di browser (login sebagai `yayasan_super_admin`)**

1. Buka halaman Peran (`/admin/roles`) — verifikasi 5 stat card tampil (Total, Platform, Yayasan, Lembaga, Diri Sendiri).
2. Klik tiap chip scope — verifikasi tabel terfilter benar dan badge count di chip aktif ter-highlight.
3. Verifikasi nama role tampil Title Case (mis. "Wakasek Kurikulum" bukan `wakasek_kurikulum`).
4. Hover angka di kolom Permissions — verifikasi tooltip muncul berisi nama permission.
5. Klik angka di kolom Users — verifikasi redirect ke halaman Pengguna dengan filter role sudah aktif (search box role menunjukkan role yang benar).
6. Buka Edit Role untuk role protected (mis. `yayasan_super_admin`) — verifikasi input Nama Role ter-disable (tidak bisa diketik), field Scope Level tetap "Terkunci" seperti sebelumnya.
7. Buka Create Role — ketik di kolom search matriks (mis. "tagihan") — verifikasi hanya permission yang cocok yang tampil, modul yang tidak ada match hilang dari grid.
8. Kosongkan kembali search matriks — verifikasi semua modul kembali muncul.
9. (Kalau ada akun `platform_super_admin` di seeder demo) Login sebagai platform admin, buka Create Role — verifikasi opsi "Platform" muncul di dropdown Scope Level. Login sebagai `yayasan_super_admin` lagi — verifikasi opsi itu TIDAK muncul.

- [ ] **Step 3: Catat hasil checklist manual di handoff log (Task 11)** — WAJIB dilaporkan meski manual, bukan diklaim "sudah dites" tanpa detail per poin.

---

## Task 11: Verifikasi Akhir & Handoff Log

**Files:**
- Create: `.agents/logs/2026-08-25-halaman-peran-perbaikan.md`

- [ ] **Step 1: Grep verifikasi tidak ada sisa kode lama**

```bash
grep -n "in:yayasan,lembaga,diri_sendiri'" app/Http/Controllers/Admin/RoleController.php
```
Expected: KOSONG (semua sudah `,platform` ditambahkan; kalau ada baris ini tanpa `,platform`, berarti ada yang terlewat).

```bash
grep -n "<select x-model=\"filters.scope\"" resources/views/admin/roles/index.blade.php
```
Expected: KOSONG (sudah jadi chip).

- [ ] **Step 2: Jalankan seluruh test yang disentuh plan ini sekaligus**

```bash
php artisan test tests/Unit/RoleModelGuardTest.php tests/Feature/Admin/RoleBuilderTest.php tests/Feature/Admin/PermissionAuditTest.php tests/Feature/Admin/RoleFormAuditBannerTest.php
```
Expected: 0 failed. Catat angka pasti.

- [ ] **Step 3: Minta izin user untuk full test suite**

Tanya ke user: "Task 1-10 selesai, test yang disentuh plan ini hijau, grep verifikasi kosong, checklist manual browser sudah dijalankan. Boleh saya jalankan full test suite untuk verifikasi akhir?" — TUNGGU jawaban eksplisit.

- [ ] **Step 4: Jalankan full suite SOLO**

```bash
php artisan test
```
Catat angka PASTI passed/failed/duration.

- [ ] **Step 5: Tulis handoff log**

Buat `.agents/logs/2026-08-25-halaman-peran-perbaikan.md` (Bahasa Indonesia): ringkasan Task 1-10 dengan commit hash, hasil grep Step 1 (kosong), hasil test Step 2 dan Step 4 (angka pasti, jangan dicampur), hasil checklist manual Task 10 Step 2 (per poin, bukan cuma "sudah dites"), daftar keputusan penting (guard 3-lapis untuk nama protected, chip berbasis scope_level langsung bukan taksonomi fungsional).

- [ ] **Step 6: Commit**

```bash
git add .agents/logs/2026-08-25-halaman-peran-perbaikan.md
git commit -m "docs(rbac): handoff log perbaikan halaman Peran - keamanan nama role, scope platform, UX"
```
