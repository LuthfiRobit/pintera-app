# Roles Page Redesign (Phase B) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign the admin Roles page (index/create/edit) with a server-side datatable, no-page-reload CRUD via Alpine.js + `fetch()`, a module-grouped permission matrix, and toast notifications — building on the granular `modul.aksi` permissions already migrated in Phase A.

**Architecture:** The existing `RoleController` gains a JSON datatable endpoint (`GET /admin/roles/data`) and a permission-catalog endpoint (`GET /admin/roles/permissions-catalog`), plus content-negotiated JSON responses on `store`/`update`/`destroy` (same controller, same authorization, branching on `$request->wantsJson()` — no separate API controller). A new `PermissionCatalog` service groups the 36 permissions by module for both the server-rendered initial page and the catalog endpoint. Three new Alpine.js components (a global toast store, a datatable component, a permission-matrix form component) are added as ES modules imported into the single existing Vite entry (`resources/js/app.js`) and registered via `Alpine.data()`/`Alpine.store()` — no Livewire, no new build config.

**Tech Stack:** Laravel 12, Spatie `laravel-permission`, Blade, Alpine.js (already loaded via existing Vite/`app.js` entry), Tailwind (existing design tokens: `ink`/`paper`/`slate`/`brass`/`signal-green`/`signal-red`), Pest PHP.

## Global Constraints

- No Livewire — Blade + Alpine.js + native `fetch()` only, per the existing M0 architecture decision.
- The same `RoleController` methods handle both normal (redirect) and AJAX (JSON) requests via `$request->wantsJson()` — do not create a separate API controller.
- This redesign is scoped to the Roles page ONLY (index/create/edit). Do not touch Users, Lembaga, Guru, Tahun Ajaran, or SPMB admin pages in this plan.
- Do not add any new CRUD action beyond what already exists on `RoleController` (index/create/store/edit/update/destroy) — the two new endpoints (`data`, `permissionsCatalog`) are read-only support endpoints for the same resource, not new business actions.
- Permission checkbox groups must reflect each module's actual action count from the seeder (`roles`: 4, `guru`: 3, `semester`: 2, `spmb-konfigurasi`: 1, etc.) — never force a uniform column count.
- Toast palette uses the existing design tokens: `signal-green` for success, `signal-red` for error, consistent with `x-badge`'s existing tone system.
- All new JS lives in ES modules under `resources/js/`, imported into the single existing `resources/js/app.js` entry point (no new Vite entries, no new npm dependencies).

---

### Task 1: Backend — permission catalog service, datatable endpoint, AJAX-aware CRUD

**Files:**
- Create: `app/Services/PermissionCatalog.php`
- Modify: `app/Http/Controllers/Admin/RoleController.php`
- Modify: `routes/admin.php`
- Modify: `tests/Feature/Admin/RoleBuilderTest.php`

**Interfaces:**
- Produces: `PermissionCatalog::grouped(): array` — returns a list of `['module' => string, 'label' => string, 'permissions' => [['id' => int, 'name' => string, 'action' => string, 'label' => string], ...]]`, sorted by module name then permission name.
- Produces: `GET /admin/roles/data` (named `admin.roles.data`) — JSON `{ data: [{id, name, scope_level, is_protected, users_count, permissions_count}], meta: {current_page, last_page, per_page, total} }`.
- Produces: `GET /admin/roles/permissions-catalog` (named `admin.roles.permissions-catalog`) — JSON `{ modules: [...same shape as PermissionCatalog::grouped()...] }`.
- Produces: `store`/`update`/`destroy` now return `response()->json([...])` when `$request->wantsJson()`, otherwise the existing redirect behavior (unchanged for non-AJAX callers).
- Consumes (later tasks): Task 3 (index datatable) consumes `admin.roles.data`. Task 4 (create/edit form) consumes `admin.roles.permissions-catalog` and the JSON variants of `store`/`update`/`destroy`.

- [ ] **Step 1: Write the failing tests for the new endpoints and JSON responses**

Add these `it()` blocks to the end of `tests/Feature/Admin/RoleBuilderTest.php` (the file already has `actingAsSuperAdmin()` defined at the top — reuse it):

```php
it('returns a paginated, searchable, sortable JSON payload from the datatable endpoint', function () {
    $admin = actingAsSuperAdmin();
    Role::create(['name' => 'zzz_role', 'guard_name' => 'web', 'scope_level' => 'lembaga']);
    Role::create(['name' => 'aaa_role', 'guard_name' => 'web', 'scope_level' => 'lembaga']);

    $response = $this->actingAs($admin)->getJson(route('admin.roles.data', ['sort' => 'name', 'direction' => 'asc']));

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('name')->values();
    expect($names->first())->toBe('aaa_role');
    expect($response->json('meta.total'))->toBeGreaterThanOrEqual(3);
});

it('filters the datatable endpoint by search and scope', function () {
    $admin = actingAsSuperAdmin();
    Role::create(['name' => 'admin_perpustakaan', 'guard_name' => 'web', 'scope_level' => 'lembaga']);
    Role::create(['name' => 'admin_gudang', 'guard_name' => 'web', 'scope_level' => 'diri_sendiri']);

    $response = $this->actingAs($admin)->getJson(route('admin.roles.data', ['search' => 'perpustakaan']));

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('name');
    expect($names)->toContain('admin_perpustakaan');
    expect($names)->not->toContain('admin_gudang');

    $response = $this->actingAs($admin)->getJson(route('admin.roles.data', ['scope' => 'diri_sendiri']));
    $names = collect($response->json('data'))->pluck('name');
    expect($names)->toContain('admin_gudang');
    expect($names)->not->toContain('admin_perpustakaan');
});

it('denies the datatable endpoint to a user without roles.view permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson(route('admin.roles.data'))->assertForbidden();
});

it('returns the permission catalog grouped by module', function () {
    $admin = actingAsSuperAdmin();

    $response = $this->actingAs($admin)->getJson(route('admin.roles.permissions-catalog'));

    $response->assertOk();
    $modules = collect($response->json('modules'))->keyBy('module');
    expect($modules->has('guru'))->toBeTrue();
    expect(collect($modules['guru']['permissions'])->pluck('name')->sort()->values()->all())
        ->toBe(['guru.create', 'guru.edit', 'guru.view']);
});

it('creates a role via AJAX and returns JSON instead of redirecting', function () {
    $admin = actingAsSuperAdmin();
    Permission::firstOrCreate(['name' => 'guru.view', 'guard_name' => 'web']);

    $response = $this->actingAs($admin)->postJson(route('admin.roles.store'), [
        'name' => 'admin_ajax',
        'scope_level' => 'lembaga',
        'permissions' => [Permission::where('name', 'guru.view')->first()->id],
    ]);

    $response->assertCreated();
    expect(Role::where('name', 'admin_ajax')->exists())->toBeTrue();
});

it('returns a JSON 422 with field errors when an AJAX store fails validation via the scope ceiling check', function () {
    Permission::firstOrCreate(['name' => 'roles.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'roles.create', 'guard_name' => 'web']);
    $lembagaRole = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $lembagaRole->givePermissionTo(['roles.view', 'roles.create']);
    $manager = User::factory()->create();
    $manager->assignRole($lembagaRole);

    $response = $this->actingAs($manager)->postJson(route('admin.roles.store'), [
        'name' => 'sneaky_ajax',
        'scope_level' => 'yayasan',
        'permissions' => [],
    ]);

    $response->assertStatus(422);
    expect($response->json('errors.scope_level'))->not->toBeNull();
    expect(Role::where('name', 'sneaky_ajax')->exists())->toBeFalse();
});

it('updates a role via AJAX and returns JSON instead of redirecting', function () {
    $admin = actingAsSuperAdmin();
    $role = Role::create(['name' => 'ajax-editable', 'guard_name' => 'web', 'scope_level' => 'lembaga']);

    $response = $this->actingAs($admin)->putJson(route('admin.roles.update', $role), [
        'name' => 'ajax-editable-renamed',
        'scope_level' => 'lembaga',
        'permissions' => [],
    ]);

    $response->assertOk();
    expect($role->fresh()->name)->toBe('ajax-editable-renamed');
});

it('deletes a role via AJAX and returns JSON instead of redirecting', function () {
    $admin = actingAsSuperAdmin();
    $role = Role::create(['name' => 'ajax-deletable', 'guard_name' => 'web', 'scope_level' => 'lembaga']);

    $response = $this->actingAs($admin)->deleteJson(route('admin.roles.destroy', $role));

    $response->assertOk();
    expect(Role::find($role->id))->toBeNull();
});

it('returns a JSON 422 instead of a redirect when an AJAX delete targets a role still in use', function () {
    $admin = actingAsSuperAdmin();
    $role = Role::create(['name' => 'ajax-in-use', 'guard_name' => 'web', 'scope_level' => 'lembaga']);
    User::factory()->create()->assignRole($role);

    $response = $this->actingAs($admin)->deleteJson(route('admin.roles.destroy', $role));

    $response->assertStatus(422);
    expect($response->json('errors.role'))->not->toBeNull();
    expect(Role::find($role->id))->not->toBeNull();
});
```

- [ ] **Step 2: Run the tests to confirm they fail**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Feature/Admin/RoleBuilderTest.php`
Expected: FAIL — the routes `admin.roles.data` and `admin.roles.permissions-catalog` don't exist yet, and `store`/`update`/`destroy` don't yet respond to `wantsJson()`.

- [ ] **Step 3: Create the `PermissionCatalog` service**

Create `app/Services/PermissionCatalog.php`:

```php
<?php

namespace App\Services;

use Spatie\Permission\Models\Permission;

class PermissionCatalog
{
    private const MODULE_LABELS = [
        'roles' => 'Roles',
        'users' => 'Pengguna',
        'lembaga' => 'Lembaga',
        'guru' => 'Guru',
        'tahun-ajaran' => 'Tahun Ajaran',
        'semester' => 'Semester',
        'jenis-tes' => 'Jenis Tes',
        'gelombang-ppdb' => 'Gelombang PPDB',
        'jalur-ppdb' => 'Jalur PPDB',
        'formulir-field' => 'Formulir Field',
        'dokumen-syarat' => 'Dokumen Syarat',
        'seleksi' => 'Seleksi',
        'spmb-konfigurasi' => 'Konfigurasi SPMB',
        'audit-log' => 'Log Aktivitas',
    ];

    private const ACTION_LABELS = [
        'view' => 'Lihat',
        'create' => 'Tambah',
        'edit' => 'Ubah',
        'delete' => 'Hapus',
        'activate' => 'Aktifkan',
        'toggle-active' => 'Aktif/Nonaktifkan',
        'duplikasi' => 'Duplikasi',
    ];

    /**
     * @return array<int, array{module: string, label: string, permissions: array<int, array{id: int, name: string, action: string, label: string}>}>
     */
    public static function grouped(): array
    {
        return Permission::orderBy('name')->get()
            ->groupBy(fn (Permission $permission) => explode('.', $permission->name)[0])
            ->map(function ($permissions, string $module) {
                return [
                    'module' => $module,
                    'label' => self::MODULE_LABELS[$module] ?? ucfirst($module),
                    'permissions' => $permissions->map(function (Permission $permission) {
                        $action = explode('.', $permission->name)[1] ?? $permission->name;

                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'action' => $action,
                            'label' => self::ACTION_LABELS[$action] ?? ucfirst($action),
                        ];
                    })->values()->all(),
                ];
            })
            ->sortBy('module')
            ->values()
            ->all();
    }
}
```

- [ ] **Step 4: Add the two new routes**

In `routes/admin.php`, replace:

```php
    Route::resource('roles', RoleController::class)->except(['show']);
```

with:

```php
    Route::get('roles/data', [RoleController::class, 'data'])->name('roles.data');
    Route::get('roles/permissions-catalog', [RoleController::class, 'permissionsCatalog'])->name('roles.permissions-catalog');
    Route::resource('roles', RoleController::class)->except(['show']);
```

- [ ] **Step 5: Rewrite `RoleController.php`**

Replace the full contents of `app/Http/Controllers/Admin/RoleController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\Role;
use App\Services\PermissionCatalog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

class RoleController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        return view('admin.roles.index');
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $query = Role::withCount(['users', 'permissions']);

        if ($search = trim((string) $request->string('search'))) {
            $query->where('name', 'like', '%'.$search.'%');
        }

        if ($scope = $request->string('scope')->value()) {
            $query->where('scope_level', $scope);
        }

        $sortable = ['name', 'scope_level', 'users_count', 'permissions_count'];
        $sort = in_array($request->string('sort')->value(), $sortable, true) ? $request->string('sort')->value() : 'name';
        $direction = $request->string('direction')->value() === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sort, $direction);

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->getCollection()->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'scope_level' => $role->scope_level,
                'is_protected' => $role->is_protected,
                'users_count' => $role->users_count,
                'permissions_count' => $role->permissions_count,
            ])->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function permissionsCatalog(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('roles.create') || $request->user()->can('roles.edit'), 403);

        return response()->json(['modules' => PermissionCatalog::grouped()]);
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('admin.roles.create', ['moduleGroups' => PermissionCatalog::grouped()]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Role::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'scope_level' => ['required', 'in:yayasan,lembaga,diri_sendiri'],
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $actingRank = $this->scopeRank($request->user()->widestScopeLevel());
        if ($this->scopeRank($data['scope_level']) > $actingRank) {
            return $this->errorResponse(
                $request,
                'scope_level',
                'Anda tidak dapat membuat role dengan scope lebih luas dari scope Anda sendiri.'
            );
        }

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
            'scope_level' => $data['scope_level'],
        ]);

        $role->syncPermissions(Permission::whereIn('id', $data['permissions'] ?? [])->get());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Role berhasil dibuat.'], 201);
        }

        return redirect()->route('admin.roles.index')->with('status', 'Role berhasil dibuat.');
    }

    public function edit(Role $role): View
    {
        $this->authorize('update', $role);

        return view('admin.roles.edit', [
            'role' => $role,
            'moduleGroups' => PermissionCatalog::grouped(),
            'checkedIds' => $role->permissions->pluck('id')->all(),
        ]);
    }

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

    public function destroy(Request $request, Role $role): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $role);

        if ($role->users()->exists()) {
            return $this->errorResponse(
                $request,
                'role',
                'Role masih dipakai user aktif, pindahkan dulu sebelum menghapus.'
            );
        }

        $role->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Role berhasil dihapus.']);
        }

        return redirect()->route('admin.roles.index')->with('status', 'Role berhasil dihapus.');
    }

    private function errorResponse(Request $request, string $field, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'errors' => [$field => [$message]]], 422);
        }

        return back()->withErrors([$field => $message])->withInput();
    }

    private function scopeRank(string $level): int
    {
        return match ($level) {
            'yayasan' => 3,
            'lembaga' => 2,
            default => 1, // diri_sendiri
        };
    }
}
```

- [ ] **Step 6: Run the tests to confirm they pass**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Feature/Admin/RoleBuilderTest.php`
Expected: PASS (17 tests — the original 8 plus 9 new).

- [ ] **Step 7: Commit**

```bash
git add app/Services/PermissionCatalog.php app/Http/Controllers/Admin/RoleController.php routes/admin.php tests/Feature/Admin/RoleBuilderTest.php
git commit -m "feat: add Roles datatable/permission-catalog endpoints and AJAX-aware CRUD responses"
```

---

### Task 2: Toast notification component

**Files:**
- Create: `resources/js/toast-store.js`
- Create: `resources/views/components/toast.blade.php`
- Modify: `resources/js/app.js`
- Modify: `resources/views/layouts/app.blade.php`

**Interfaces:**
- Produces: a global Alpine store accessible anywhere as `Alpine.store('toast')`, with methods `push(type, message)` (`type` is `'success'` or `'error'`) and `remove(id)`.
- Consumes (later tasks): Tasks 3 and 4 call `Alpine.store('toast').push(...)` after AJAX responses.

There is no JS test runner configured in this project (Pest covers PHP only) — verification for this task is a manual visual check, described in Step 3.

- [ ] **Step 1: Create the toast store module**

Create `resources/js/toast-store.js`:

```js
export function registerToastStore(Alpine) {
    Alpine.store('toast', {
        items: [],
        nextId: 1,

        push(type, message) {
            const id = this.nextId++;
            this.items.push({ id, type, message });
            setTimeout(() => this.remove(id), 4000);
        },

        remove(id) {
            this.items = this.items.filter((item) => item.id !== id);
        },
    });
}
```

- [ ] **Step 2: Create the toast Blade component**

Create `resources/views/components/toast.blade.php`:

```blade
<div
    x-data
    class="pointer-events-none fixed right-4 top-4 z-50 flex w-full max-w-sm flex-col gap-2 sm:right-6 sm:top-6"
>
    <template x-for="toast in $store.toast.items" :key="toast.id">
        <div
            x-show="true"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-x-4 opacity-0"
            x-transition:enter-end="translate-x-0 opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-x-0 opacity-100"
            x-transition:leave-end="translate-x-4 opacity-0"
            class="pointer-events-auto flex items-start gap-3 rounded-xl border bg-white p-4 shadow-elevated"
            :class="toast.type === 'success' ? 'border-signal-green/20' : 'border-signal-red/20'"
        >
            <span
                class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                :class="toast.type === 'success' ? 'bg-signal-green/10 text-signal-green' : 'bg-signal-red/10 text-signal-red'"
                x-text="toast.type === 'success' ? '✓' : '✕'"
            ></span>
            <p class="flex-1 text-sm text-ink" x-text="toast.message"></p>
            <button type="button" class="text-slate hover:text-ink" @click="$store.toast.remove(toast.id)">
                <span class="text-sm">✕</span>
            </button>
        </div>
    </template>
</div>
```

- [ ] **Step 3: Wire the store into `app.js` and include the component in the layout**

In `resources/js/app.js`, replace the full contents:

```js
import './bootstrap';

import Alpine from 'alpinejs';
import { registerToastStore } from './toast-store';

window.Alpine = Alpine;

registerToastStore(Alpine);

Alpine.start();
```

In `resources/views/layouts/app.blade.php`, add `<x-toast />` right after the opening `<div x-data="{ sidebarOpen: false }" ...>` line (line 19), so it renders once per page, above the sidebar/content siblings:

```blade
        <div x-data="{ sidebarOpen: false }" class="min-h-full bg-paper lg:flex">
            <x-toast />

            @include('layouts.sidebar')
```

- [ ] **Step 4: Manual verification**

Run `npm run build` (or confirm `npm run dev` / Vite is already running) from `D:\laragon\www\pintera-app`, then load any admin page in the browser and run this in the browser devtools console to confirm the store exists and a toast renders and auto-dismisses:

```js
Alpine.store('toast').push('success', 'Test toast berhasil.');
```

Expected: a green-accented toast slides in from the top-right, shows "Test toast berhasil.", and disappears on its own after ~4 seconds (or immediately via its close button).

- [ ] **Step 5: Commit**

```bash
git add resources/js/toast-store.js resources/views/components/toast.blade.php resources/js/app.js resources/views/layouts/app.blade.php
git commit -m "feat: add global Alpine toast notification store and component"
```

---

### Task 3: Index page — server-side datatable

**Files:**
- Create: `resources/js/roles-table.js`
- Modify: `resources/js/app.js`
- Modify: `resources/views/admin/roles/index.blade.php`
- Modify: `tests/Feature/Admin/RoleBuilderTest.php` (one view-renders smoke test)

**Interfaces:**
- Consumes: `GET admin.roles.data` (Task 1), `Alpine.store('toast')` (Task 2).

There is no JS test runner in this project — the Blade view itself is verified by a lightweight Pest test (the page renders and contains the expected `x-data` mount point), and full interactive behavior is verified manually in Step 5.

- [ ] **Step 1: Write the failing test for the index view**

Add to `tests/Feature/Admin/RoleBuilderTest.php`:

```php
it('renders the roles index page with the datatable mount point instead of a server-rendered table', function () {
    $admin = actingAsSuperAdmin();

    $response = $this->actingAs($admin)->get(route('admin.roles.index'));

    $response->assertOk();
    $response->assertSee('rolesTable(', false);
});
```

- [ ] **Step 2: Run the test to confirm it fails**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Feature/Admin/RoleBuilderTest.php --filter="datatable mount point"`
Expected: FAIL — the current index view still renders a server-side `<table>` with no `rolesTable(` Alpine mount point.

- [ ] **Step 3: Create the `rolesTable` Alpine component**

Create `resources/js/roles-table.js`:

```js
export function rolesTable(config) {
    return {
        rows: [],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
        search: '',
        scope: '',
        sort: 'name',
        direction: 'asc',
        page: 1,
        loading: false,
        searchTimeout: null,
        dataUrl: config.dataUrl,
        editUrlTemplate: config.editUrlTemplate,
        deleteUrlTemplate: config.deleteUrlTemplate,

        init() {
            this.fetchData();
        },

        onSearchInput() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.page = 1;
                this.fetchData();
            }, 350);
        },

        onScopeChange() {
            this.page = 1;
            this.fetchData();
        },

        sortBy(column) {
            if (this.sort === column) {
                this.direction = this.direction === 'asc' ? 'desc' : 'asc';
            } else {
                this.sort = column;
                this.direction = 'asc';
            }
            this.fetchData();
        },

        goToPage(page) {
            if (page < 1 || page > this.meta.last_page) {
                return;
            }
            this.page = page;
            this.fetchData();
        },

        editUrl(row) {
            return this.editUrlTemplate.replace('__ID__', row.id);
        },

        async fetchData() {
            this.loading = true;
            const params = new URLSearchParams({
                search: this.search,
                scope: this.scope,
                sort: this.sort,
                direction: this.direction,
                page: this.page,
            });

            try {
                const response = await fetch(`${this.dataUrl}?${params}`, {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) {
                    throw new Error('request failed');
                }

                const json = await response.json();
                this.rows = json.data;
                this.meta = json.meta;
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memuat data role.');
            } finally {
                this.loading = false;
            }
        },

        async deleteRole(row) {
            if (!confirm(`Hapus role "${row.name}"? Tindakan ini tidak bisa dibatalkan.`)) {
                return;
            }

            try {
                const response = await fetch(this.deleteUrlTemplate.replace('__ID__', row.id), {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });

                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menghapus role.');
                    return;
                }

                Alpine.store('toast').push('success', json.message ?? 'Role berhasil dihapus.');
                this.fetchData();
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menghapus role.');
            }
        },
    };
}
```

- [ ] **Step 4: Register the component and rewrite the index view**

In `resources/js/app.js`, add the import and registration (final contents):

```js
import './bootstrap';

import Alpine from 'alpinejs';
import { registerToastStore } from './toast-store';
import { rolesTable } from './roles-table';

window.Alpine = Alpine;

registerToastStore(Alpine);
Alpine.data('rolesTable', rolesTable);

Alpine.start();
```

Replace the full contents of `resources/views/admin/roles/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Akses &amp; Peran</p>
                <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Role Builder</h2>
            </div>
            <x-link-button href="{{ route('admin.roles.create') }}">
                <span class="text-base leading-none">+</span> Buat Role Baru
            </x-link-button>
        </div>
    </x-slot>

    <div
        class="mx-auto max-w-6xl space-y-6"
        x-data="rolesTable({
            dataUrl: @js(route('admin.roles.data')),
            editUrlTemplate: @js(route('admin.roles.edit', ['role' => '__ID__'])),
            deleteUrlTemplate: @js(route('admin.roles.destroy', ['role' => '__ID__'])),
        })"
    >
        <x-panel>
            <div class="flex flex-wrap items-center gap-3 border-b border-ink/10 p-4">
                <input
                    type="search"
                    x-model="search"
                    @input="onSearchInput()"
                    placeholder="Cari nama role..."
                    class="w-full max-w-xs rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass"
                >
                <select
                    x-model="scope"
                    @change="onScopeChange()"
                    class="rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass"
                >
                    <option value="">Semua Scope</option>
                    <option value="yayasan">Yayasan</option>
                    <option value="lembaga">Lembaga</option>
                    <option value="diri_sendiri">Diri Sendiri</option>
                </select>
                <button
                    type="button"
                    @click="fetchData()"
                    class="ml-auto inline-flex items-center gap-2 rounded-xl border border-ink/15 px-3 py-2 text-sm font-medium text-ink hover:bg-paper"
                >
                    <span x-show="loading" class="inline-block h-3 w-3 animate-spin rounded-full border-2 border-ink/30 border-t-ink"></span>
                    Refresh
                </button>
            </div>

            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-ink/10 bg-paper/60 text-left text-xs uppercase tracking-wide text-slate">
                        <th class="px-5 py-3 font-display font-semibold">No</th>
                        <th class="px-5 py-3 font-display font-semibold">
                            <button type="button" @click="sortBy('name')" class="hover:text-ink">Nama Role &amp; Scope</button>
                        </th>
                        <th class="px-5 py-3 font-display font-semibold">
                            <button type="button" @click="sortBy('permissions_count')" class="hover:text-ink">Permission</button>
                        </th>
                        <th class="px-5 py-3 font-display font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink/10">
                    <template x-for="(row, index) in rows" :key="row.id">
                        <tr class="transition hover:bg-paper/50">
                            <td class="px-5 py-3.5 font-mono text-slate" x-text="(meta.current_page - 1) * meta.per_page + index + 1"></td>
                            <td class="px-5 py-3.5">
                                <p class="font-medium text-ink" x-text="row.name"></p>
                                <span
                                    class="mt-1 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold"
                                    :class="row.scope_level === 'yayasan' ? 'bg-brass/10 text-brass' : 'bg-slate/10 text-slate'"
                                    x-text="row.scope_level"
                                ></span>
                                <span x-show="row.is_protected" class="ml-1.5 inline-flex items-center rounded-full bg-brass/10 px-2.5 py-0.5 text-xs font-bold text-brass">Protected</span>
                            </td>
                            <td class="px-5 py-3.5 font-mono text-slate" x-text="row.permissions_count"></td>
                            <td class="px-5 py-3.5">
                                <div class="relative" x-data="{ menuOpen: false }" @click.outside="menuOpen = false">
                                    <button
                                        type="button"
                                        @click="menuOpen = !menuOpen"
                                        class="rounded-lg p-1.5 text-slate hover:bg-paper hover:text-ink"
                                        aria-label="Aksi"
                                    >
                                        <span class="text-lg leading-none">&#9881;</span>
                                    </button>
                                    <div
                                        x-show="menuOpen"
                                        x-transition
                                        class="absolute right-0 z-10 mt-1 w-40 rounded-xl border border-ink/10 bg-white py-1 shadow-elevated"
                                        style="display: none;"
                                    >
                                        <a :href="editUrl(row)" class="block px-4 py-2 text-sm text-ink hover:bg-paper">Edit Role</a>
                                        <template x-if="!row.is_protected">
                                            <button
                                                type="button"
                                                @click="menuOpen = false; deleteRole(row)"
                                                class="block w-full px-4 py-2 text-left text-sm text-signal-red hover:bg-signal-red/5"
                                            >
                                                Hapus
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!loading && rows.length === 0">
                        <td colspan="4" class="px-5 py-10 text-center text-slate">Tidak ada role yang cocok.</td>
                    </tr>
                </tbody>
            </table>

            <div class="flex items-center justify-between border-t border-ink/10 p-4 text-sm text-slate">
                <p>Halaman <span x-text="meta.current_page"></span> dari <span x-text="meta.last_page"></span> &middot; <span x-text="meta.total"></span> role</p>
                <div class="flex items-center gap-2">
                    <button type="button" @click="goToPage(meta.current_page - 1)" :disabled="meta.current_page <= 1" class="rounded-lg border border-ink/15 px-3 py-1.5 disabled:opacity-40">Sebelumnya</button>
                    <button type="button" @click="goToPage(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page" class="rounded-lg border border-ink/15 px-3 py-1.5 disabled:opacity-40">Berikutnya</button>
                </div>
            </div>
        </x-panel>
    </div>
</x-app-layout>
```

- [ ] **Step 5: Run the test, then verify manually**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Feature/Admin/RoleBuilderTest.php`
Expected: PASS (18 tests).

Manual check (no JS test runner in this project): with `npm run dev` running, log in as a yayasan-scoped admin and open `/admin/roles`. Confirm: the table loads via a network request to `/admin/roles/data` (visible in devtools Network tab), search filters rows after a short debounce, the scope dropdown filters rows, clicking a column header re-sorts, pagination buttons work if there are enough roles, the gear menu opens/closes and its "Hapus" option deletes a non-protected role in place with a toast and no page reload.

- [ ] **Step 6: Commit**

```bash
git add resources/js/roles-table.js resources/js/app.js resources/views/admin/roles/index.blade.php tests/Feature/Admin/RoleBuilderTest.php
git commit -m "feat: replace Roles index with a server-side datatable driven by Alpine and fetch"
```

---

### Task 4: Create/Edit pages — module-grouped permission matrix + AJAX CRUD

**Files:**
- Create: `resources/js/role-form.js`
- Modify: `resources/js/app.js`
- Modify: `resources/views/admin/roles/create.blade.php`
- Modify: `resources/views/admin/roles/edit.blade.php`
- Modify: `tests/Feature/Admin/RoleBuilderTest.php` (two view-renders smoke tests)

**Interfaces:**
- Consumes: `GET admin.roles.permissions-catalog` and the JSON variants of `store`/`update` (Task 1), `Alpine.store('toast')` (Task 2).

- [ ] **Step 1: Write the failing tests for the create/edit views**

Add to `tests/Feature/Admin/RoleBuilderTest.php`:

```php
it('renders the create-role page with the permission-matrix mount point', function () {
    $admin = actingAsSuperAdmin();

    $response = $this->actingAs($admin)->get(route('admin.roles.create'));

    $response->assertOk();
    $response->assertSee('roleForm(', false);
});

it('renders the edit-role page with the permission-matrix mount point pre-filled with the role\'s permissions', function () {
    $admin = actingAsSuperAdmin();
    Permission::firstOrCreate(['name' => 'guru.view', 'guard_name' => 'web']);
    $role = Role::create(['name' => 'editable-matrix', 'guard_name' => 'web', 'scope_level' => 'lembaga']);
    $role->givePermissionTo('guru.view');

    $response = $this->actingAs($admin)->get(route('admin.roles.edit', $role));

    $response->assertOk();
    $response->assertSee('roleForm(', false);
    $response->assertSee((string) Permission::where('name', 'guru.view')->first()->id, false);
});
```

- [ ] **Step 2: Run the tests to confirm they fail**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Feature/Admin/RoleBuilderTest.php --filter="permission-matrix"`
Expected: FAIL — the current create/edit views render flat `@foreach` checkbox lists with no `roleForm(` Alpine mount point.

- [ ] **Step 3: Create the `roleForm` Alpine component**

Create `resources/js/role-form.js`:

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

        isChecked(id) {
            return this.checkedIds.includes(id);
        },

        toggle(id) {
            if (this.isChecked(id)) {
                this.checkedIds = this.checkedIds.filter((checkedId) => checkedId !== id);
            } else {
                this.checkedIds.push(id);
            }
        },

        allCheckedInModule(group) {
            return group.permissions.length > 0 && group.permissions.every((permission) => this.isChecked(permission.id));
        },

        toggleModule(group) {
            const allChecked = this.allCheckedInModule(group);
            group.permissions.forEach((permission) => {
                const checked = this.isChecked(permission.id);
                if (allChecked && checked) {
                    this.checkedIds = this.checkedIds.filter((id) => id !== permission.id);
                } else if (!allChecked && !checked) {
                    this.checkedIds.push(permission.id);
                }
            });
        },

        selectAll() {
            this.checkedIds = this.moduleGroups.flatMap((group) => group.permissions.map((permission) => permission.id));
        },

        clearAll() {
            this.checkedIds = [];
        },

        async syncPermissions() {
            this.syncing = true;
            try {
                const response = await fetch(this.catalogUrl, { headers: { Accept: 'application/json' } });
                if (!response.ok) {
                    throw new Error('request failed');
                }
                const json = await response.json();
                const validIds = json.modules.flatMap((group) => group.permissions.map((permission) => permission.id));
                this.moduleGroups = json.modules;
                this.checkedIds = this.checkedIds.filter((id) => validIds.includes(id));
                Alpine.store('toast').push('success', 'Katalog permission diperbarui.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menyegarkan katalog permission.');
            } finally {
                this.syncing = false;
            }
        },

        async submit() {
            this.submitting = true;
            this.errors = {};

            try {
                const response = await fetch(this.submitUrl, {
                    method: this.method,
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        name: this.name,
                        scope_level: this.scopeLevel,
                        permissions: this.checkedIds,
                    }),
                });

                const json = await response.json();

                if (response.status === 422) {
                    this.errors = json.errors ?? {};
                    Alpine.store('toast').push('error', json.message ?? 'Periksa kembali form.');
                    return;
                }

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menyimpan role.');
                    return;
                }

                Alpine.store('toast').push('success', json.message ?? 'Role berhasil disimpan.');
                window.location.href = this.indexUrl;
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menyimpan role.');
            } finally {
                this.submitting = false;
            }
        },
    };
}
```

- [ ] **Step 4: Register the component**

In `resources/js/app.js`, add the import and registration (final contents):

```js
import './bootstrap';

import Alpine from 'alpinejs';
import { registerToastStore } from './toast-store';
import { rolesTable } from './roles-table';
import { roleForm } from './role-form';

window.Alpine = Alpine;

registerToastStore(Alpine);
Alpine.data('rolesTable', rolesTable);
Alpine.data('roleForm', roleForm);

Alpine.start();
```

- [ ] **Step 5: Rewrite the create view**

Replace the full contents of `resources/views/admin/roles/create.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Akses &amp; Peran</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Buat Role Baru</h2>
    </x-slot>

    <div
        class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[minmax(0,320px)_1fr]"
        x-data="roleForm({
            catalogUrl: @js(route('admin.roles.permissions-catalog')),
            submitUrl: @js(route('admin.roles.store')),
            method: 'POST',
            indexUrl: @js(route('admin.roles.index')),
            initialModuleGroups: @js($moduleGroups),
            initialCheckedIds: @js([]),
            initialName: @js(old('name', '')),
            initialScopeLevel: @js(old('scope_level', 'lembaga')),
            initialIsProtected: false,
        })"
    >
        <x-panel>
            <div class="space-y-5 p-6">
                <div>
                    <x-input-label value="Nama Role" />
                    <x-text-input type="text" x-model="name" class="mt-1.5" />
                    <p x-show="errors.name" class="mt-1.5 text-sm text-signal-red" x-text="errors.name && errors.name[0]"></p>
                </div>

                <div>
                    <x-input-label value="Scope Level" />
                    <select x-model="scopeLevel" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        <option value="yayasan">Yayasan</option>
                        <option value="lembaga">Lembaga</option>
                        <option value="diri_sendiri">Diri Sendiri</option>
                    </select>
                    <p x-show="errors.scope_level" class="mt-1.5 text-sm text-signal-red" x-text="errors.scope_level && errors.scope_level[0]"></p>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button
                        type="button"
                        @click="submit()"
                        :disabled="submitting"
                        class="inline-flex items-center gap-2 rounded-xl bg-ink px-4 py-2.5 text-sm font-bold text-paper shadow-sm transition hover:bg-ink/90 active:scale-[0.98] disabled:opacity-60"
                    >
                        <span x-text="submitting ? 'Menyimpan...' : 'Simpan'"></span>
                    </button>
                    <a href="{{ route('admin.roles.index') }}" class="text-sm text-slate hover:text-ink">Batal</a>
                </div>
            </div>
        </x-panel>

        @include('admin.roles._permission-matrix')
    </div>
</x-app-layout>
```

- [ ] **Step 6: Rewrite the edit view**

Replace the full contents of `resources/views/admin/roles/edit.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Akses &amp; Peran</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Edit Role: {{ $role->name }}</h2>
    </x-slot>

    <div
        class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[minmax(0,320px)_1fr]"
        x-data="roleForm({
            catalogUrl: @js(route('admin.roles.permissions-catalog')),
            submitUrl: @js(route('admin.roles.update', $role)),
            method: 'PUT',
            indexUrl: @js(route('admin.roles.index')),
            initialModuleGroups: @js($moduleGroups),
            initialCheckedIds: @js($checkedIds),
            initialName: @js(old('name', $role->name)),
            initialScopeLevel: @js(old('scope_level', $role->scope_level)),
            initialIsProtected: @js($role->is_protected),
        })"
    >
        <x-panel>
            <div class="space-y-5 p-6">
                <div>
                    <x-input-label value="Nama Role" />
                    <x-text-input type="text" x-model="name" class="mt-1.5" />
                    <p x-show="errors.name" class="mt-1.5 text-sm text-signal-red" x-text="errors.name && errors.name[0]"></p>
                </div>

                <div>
                    <x-input-label value="Scope Level" />
                    <template x-if="isProtected">
                        <p class="mt-1.5 rounded-xl bg-brass/10 p-2.5 text-sm text-brass" x-text="scopeLevel + ' (terkunci, role ini dilindungi)'"></p>
                    </template>
                    <template x-if="!isProtected">
                        <select x-model="scopeLevel" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                            <option value="yayasan">Yayasan</option>
                            <option value="lembaga">Lembaga</option>
                            <option value="diri_sendiri">Diri Sendiri</option>
                        </select>
                    </template>
                    <p x-show="errors.scope_level" class="mt-1.5 text-sm text-signal-red" x-text="errors.scope_level && errors.scope_level[0]"></p>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button
                        type="button"
                        @click="submit()"
                        :disabled="submitting"
                        class="inline-flex items-center gap-2 rounded-xl bg-ink px-4 py-2.5 text-sm font-bold text-paper shadow-sm transition hover:bg-ink/90 active:scale-[0.98] disabled:opacity-60"
                    >
                        <span x-text="submitting ? 'Menyimpan...' : 'Simpan'"></span>
                    </button>
                    <a href="{{ route('admin.roles.index') }}" class="text-sm text-slate hover:text-ink">Batal</a>
                </div>
            </div>
        </x-panel>

        @include('admin.roles._permission-matrix')
    </div>
</x-app-layout>
```

- [ ] **Step 7: Create the shared permission-matrix partial**

Both views above `@include('admin.roles._permission-matrix')` — this partial only references Alpine state (`moduleGroups`, `checkedIds`, `toggle`, etc.) already established by the parent `x-data`, so it needs no parameters of its own.

Create `resources/views/admin/roles/_permission-matrix.blade.php`:

```blade
<x-panel>
    <div class="space-y-4 p-6">
        <div class="flex items-center justify-between">
            <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-slate">Hak Akses (Permissions)</h3>
            <div class="flex items-center gap-3 text-sm">
                <button type="button" @click="selectAll()" class="font-medium text-ink hover:text-brass">Pilih Semua</button>
                <button type="button" @click="clearAll()" class="font-medium text-slate hover:text-ink">Kosongkan</button>
                <button
                    type="button"
                    @click="syncPermissions()"
                    :disabled="syncing"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-ink/15 px-2.5 py-1.5 font-medium text-ink hover:bg-paper disabled:opacity-60"
                >
                    <span x-text="syncing ? 'Menyegarkan...' : 'Sync Permission'"></span>
                </button>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <template x-for="group in moduleGroups" :key="group.module">
                <div class="rounded-xl border border-ink/10 p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <p class="font-display text-sm font-semibold text-ink" x-text="'Modul: ' + group.label"></p>
                        <label class="flex items-center gap-1.5 text-xs text-slate">
                            <input type="checkbox" :checked="allCheckedInModule(group)" @change="toggleModule(group)" class="rounded border-ink/25 text-brass focus:ring-brass">
                            Semua
                        </label>
                    </div>
                    <div class="flex flex-wrap gap-x-4 gap-y-2">
                        <template x-for="permission in group.permissions" :key="permission.id">
                            <label class="flex items-center gap-2 text-sm text-slate">
                                <input type="checkbox" :checked="isChecked(permission.id)" @change="toggle(permission.id)" class="rounded border-ink/25 text-brass focus:ring-brass">
                                <span x-text="permission.label"></span>
                            </label>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>
</x-panel>
```

- [ ] **Step 8: Run the tests, then verify manually**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Feature/Admin/RoleBuilderTest.php`
Expected: PASS (20 tests).

Manual check (no JS test runner in this project): with `npm run dev` running, open `/admin/roles/create`. Confirm: permission cards are grouped by module with the correct per-module checkbox count (e.g. "Modul: Roles" shows 4 checkboxes, "Modul: Semester" shows 2), "Pilih Semua" checks every box, "Sync Permission" re-fetches without a page reload, submitting with a duplicate name shows an inline error under "Nama Role" without losing any checked boxes, and a successful submit shows a success toast and lands back on `/admin/roles` without a full page reload's typical flash-of-white (confirm via Network tab that no full-document navigation occurred until the final `window.location.href` assignment). Repeat on `/admin/roles/{id}/edit` for an existing role and confirm its permissions arrive pre-checked, and that the protected `yayasan_super_admin` role shows the locked scope-level text instead of a select.

- [ ] **Step 9: Commit**

```bash
git add resources/js/role-form.js resources/js/app.js resources/views/admin/roles/create.blade.php resources/views/admin/roles/edit.blade.php resources/views/admin/roles/_permission-matrix.blade.php tests/Feature/Admin/RoleBuilderTest.php
git commit -m "feat: redesign Roles create/edit with a module-grouped permission matrix and AJAX submit"
```

---

### Task 5: Final regression and asset build check

**Files:** none (verification only)

- [ ] **Step 1: Run the full Pest suite**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test`
Expected: PASS, 0 failures (should be roughly 155 + 12 new = 167 passed, up from the 155 baseline after Phase A's final-review fixes).

- [ ] **Step 2: Confirm the frontend assets build cleanly**

Run: `npm run build` from `D:\laragon\www\pintera-app`.
Expected: Vite build completes with no errors, producing bundled output that includes `toast-store.js`, `roles-table.js`, and `role-form.js`'s code (bundled into the single `app.js` output chunk, not necessarily as separate files — confirm the build simply succeeds).

- [ ] **Step 3: Full manual walkthrough**

With the dev server running (`php artisan serve` or Laragon) and `npm run dev` active, log in as a yayasan-scoped admin and walk through, in order: index page load (network request visible, search, scope filter, sort, pagination if applicable), create a new role end-to-end (permission matrix, Sync Permission, validation error path, success path + toast), edit an existing role (pre-checked permissions, protected-role locked scope display, update success), delete a non-protected role from the index page's gear menu (confirm dialog, in-place row removal, toast), and confirm a protected role's gear menu has no "Hapus" option. Confirm no full-page reload occurs at any point except the final redirect-like `window.location.href` navigation after a successful create/update.

- [ ] **Step 4: Commit (only if Step 1-3 required any fixes)**

If everything passes with no changes needed, there is nothing to commit for this task. If any fix was needed, commit it with a message describing what regression it addressed.
