# Permission Audit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend the existing "Sync Permission" button on the Role create/edit form so it audits real permission usage in the codebase against the database — auto-creating permissions the code references but the database is missing, and reporting (never auto-deleting) permissions the database has but no code references.

**Architecture:** A new `PermissionAuditService` scans `.php` files under given directories for `authorize('...')`/`->can('...')` string-literal calls, diffs the result against `Permission::pluck('name')`. The existing `RoleController::permissionsCatalog()` endpoint (already called by the existing "Sync Permission" button) is extended to run this audit and include the result in its JSON response. The existing `role-form.js`/`_permission-matrix.blade.php` are extended to render the two findings as a dismissible banner.

**Tech Stack:** Laravel 12, Blade, Alpine.js, Pest PHP.

## Global Constraints

- Regex `\b(?:authorize|can)\(\s*\'([a-z0-9\-\.]+)\'` is the sole detection pattern — matches `$this->authorize('modul.aksi')` and `->can('modul.aksi')` (including `auth()->user()->can(...)`) across both controller and Blade files. Arguments that aren't a string literal (e.g. `authorize($variable)`) are silently skipped — this is an accepted limitation, not a bug to work around.
- **missingFromDatabase** (referenced in code, absent from DB) is auto-created via `Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'])` — matching the exact call shape already used in `database/seeders/RolePermissionSeeder.php`. This is safe/additive.
- **unusedInCode** (in DB, never referenced in code) is reported only — this plan must never delete a `Permission` row. No `--prune`-equivalent exists anywhere in this plan.
- No new permission is introduced for gating this feature — reuse the exact existing check on `RoleController::permissionsCatalog()`: `$request->user()->can('roles.create') || $request->user()->can('roles.edit')`.
- The audit runs only when the existing "Sync Permission" button is clicked (on-demand) — never on page load, never on a schedule.
- PHP is not on PATH — use `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe` for all `artisan`/test commands. Node is not on PATH — use `D:\laragon\bin\nodejs\node-v24.15.0-win-x64\node.exe` and its sibling `npm.cmd` (or add that directory to PATH for the shell session).
- **No browser-automation tooling (Playwright/Puppeteer/Dusk) is installed in this project.** "Visual verification" in this plan means: (a) a Feature test asserting the real rendered HTML/Alpine markup is present and structurally correct, and (b) fetching the actual compiled page via a real authenticated HTTP request and reading the raw HTML output — not a screenshot. Task 3 explicitly calls this out; do not skip it or claim screenshot verification that isn't actually possible in this environment.

---

### Task 1: `PermissionAuditService`

**Files:**
- Create: `app/Services/PermissionAuditService.php`
- Test: `tests/Unit/PermissionAuditServiceTest.php`

**Interfaces:**
- Produces: `PermissionAuditService::__construct(?array $scanDirectories = null)` (defaults to `[app_path('Http/Controllers'), resource_path('views')]` when not given — tests pass an explicit array to scan an isolated temp directory instead), `::audit(): array` (keys `missingFromDatabase` — array of newly-created permission name strings, sorted; `unusedInCode` — array of permission name strings found in the DB but not referenced anywhere scanned, sorted) — Task 2's controller calls this exact method with no arguments (using the real default directories).

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/PermissionAuditServiceTest.php

use App\Services\PermissionAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function buatDirektoriUjiPermissionAudit(string $isiFile): string
{
    $dir = sys_get_temp_dir().'/permission-audit-test-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/FakeController.php', $isiFile);

    return $dir;
}

function hapusDirektoriUjiPermissionAudit(string $dir): void
{
    array_map('unlink', glob($dir.'/*'));
    rmdir($dir);
}

it('detects a permission used in code but missing from the database, and creates it', function () {
    $dir = buatDirektoriUjiPermissionAudit("<?php\nclass FakeController {\n    public function index() {\n        \$this->authorize('contoh-modul.aksi-baru');\n    }\n}\n");

    $result = (new PermissionAuditService([$dir]))->audit();

    expect($result['missingFromDatabase'])->toBe(['contoh-modul.aksi-baru']);
    expect(Permission::where('name', 'contoh-modul.aksi-baru')->where('guard_name', 'web')->exists())->toBeTrue();

    hapusDirektoriUjiPermissionAudit($dir);
});

it('is idempotent -- running audit twice does not duplicate or error', function () {
    $dir = buatDirektoriUjiPermissionAudit("<?php\nclass FakeController {\n    public function index() {\n        \$this->authorize('contoh-modul.aksi-dua-kali');\n    }\n}\n");

    (new PermissionAuditService([$dir]))->audit();
    $keduaKali = (new PermissionAuditService([$dir]))->audit();

    expect($keduaKali['missingFromDatabase'])->toBe([]);
    expect(Permission::where('name', 'contoh-modul.aksi-dua-kali')->count())->toBe(1);

    hapusDirektoriUjiPermissionAudit($dir);
});

it('reports a permission that exists in the database but is never referenced in code, without deleting it', function () {
    Permission::create(['name' => 'contoh-modul.tidak-terpakai', 'guard_name' => 'web']);
    $dir = buatDirektoriUjiPermissionAudit("<?php\nclass FakeController {\n    public function index() {}\n}\n");

    $result = (new PermissionAuditService([$dir]))->audit();

    expect($result['unusedInCode'])->toContain('contoh-modul.tidak-terpakai');
    expect(Permission::where('name', 'contoh-modul.tidak-terpakai')->exists())->toBeTrue();

    hapusDirektoriUjiPermissionAudit($dir);
});

it('does not list a permission that exists in both code and database', function () {
    Permission::create(['name' => 'contoh-modul.sudah-lengkap', 'guard_name' => 'web']);
    $dir = buatDirektoriUjiPermissionAudit("<?php\nclass FakeController {\n    public function index() {\n        \$this->authorize('contoh-modul.sudah-lengkap');\n    }\n}\n");

    $result = (new PermissionAuditService([$dir]))->audit();

    expect($result['missingFromDatabase'])->not->toContain('contoh-modul.sudah-lengkap');
    expect($result['unusedInCode'])->not->toContain('contoh-modul.sudah-lengkap');

    hapusDirektoriUjiPermissionAudit($dir);
});

it('detects ->can(...) the same way as authorize(...), and ignores dynamic arguments', function () {
    $dir = buatDirektoriUjiPermissionAudit("<?php\n\$variabel = 'sesuatu';\necho auth()->user()->can('contoh-modul.dari-can');\necho auth()->user()->can(\$variabel);\n");

    $result = (new PermissionAuditService([$dir]))->audit();

    expect($result['missingFromDatabase'])->toBe(['contoh-modul.dari-can']);

    hapusDirektoriUjiPermissionAudit($dir);
});

it('does not false-positive on an unrelated method name that merely contains "authorize" or "can"', function () {
    $dir = buatDirektoriUjiPermissionAudit("<?php\nclass FakeController {\n    public function index() {\n        \$this->reauthorize('tidak-relevan.jangan-tertangkap');\n        \$this->scan('juga-tidak-relevan.jangan-tertangkap');\n    }\n}\n");

    $result = (new PermissionAuditService([$dir]))->audit();

    expect($result['missingFromDatabase'])->toBe([]);

    hapusDirektoriUjiPermissionAudit($dir);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/PermissionAuditServiceTest.php`
Expected: FAIL — `App\Services\PermissionAuditService` doesn't exist yet.

- [ ] **Step 3: Write `PermissionAuditService`**

```php
<?php
// app/Services/PermissionAuditService.php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;

class PermissionAuditService
{
    private const PATTERN = '/\b(?:authorize|can)\(\s*\'([a-z0-9\-\.]+)\'/';

    private array $scanDirectories;

    public function __construct(?array $scanDirectories = null)
    {
        $this->scanDirectories = $scanDirectories ?? [
            app_path('Http/Controllers'),
            resource_path('views'),
        ];
    }

    public function audit(): array
    {
        $usedInCode = $this->scanCodeForPermissionNames();
        $inDatabase = Permission::pluck('name')->all();

        $missingFromDatabase = array_values(array_diff($usedInCode, $inDatabase));
        $unusedInCode = array_values(array_diff($inDatabase, $usedInCode));

        foreach ($missingFromDatabase as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        sort($missingFromDatabase);
        sort($unusedInCode);

        return [
            'missingFromDatabase' => $missingFromDatabase,
            'unusedInCode' => $unusedInCode,
        ];
    }

    private function scanCodeForPermissionNames(): array
    {
        $names = [];

        foreach ($this->filesToScan() as $file) {
            $contents = File::get($file->getPathname());

            if (preg_match_all(self::PATTERN, $contents, $matches)) {
                foreach ($matches[1] as $name) {
                    $names[$name] = true;
                }
            }
        }

        return array_keys($names);
    }

    private function filesToScan(): iterable
    {
        $files = collect();

        foreach ($this->scanDirectories as $directory) {
            if (! File::isDirectory($directory)) {
                continue;
            }

            $files = $files->concat(
                collect(File::allFiles($directory))->filter(fn ($file) => $file->getExtension() === 'php')
            );
        }

        return $files;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/PermissionAuditServiceTest.php`
Expected: PASS (6/6)

- [ ] **Step 5: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all pre-existing tests still pass.

- [ ] **Step 6: Commit**

```bash
git add app/Services/PermissionAuditService.php tests/Unit/PermissionAuditServiceTest.php
git commit -m "feat: add PermissionAuditService for code-vs-database permission drift detection"
```

---

### Task 2: Wire the Audit into `RoleController::permissionsCatalog()`

**Files:**
- Modify: `app/Http/Controllers/Admin/RoleController.php`
- Test: `tests/Feature/Admin/PermissionAuditTest.php`

**Interfaces:**
- Consumes: `PermissionAuditService::audit(): array` (Task 1) — called with no arguments (uses the real default directories).
- Produces: the JSON response of `GET admin/roles/permissions-catalog` (route name `admin.roles.permissions-catalog`, already exists) now includes an `audit` key alongside the existing `modules` key — Task 3's `role-form.js` reads `json.audit.missingFromDatabase` / `json.audit.unusedInCode`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Admin/PermissionAuditTest.php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('includes audit results alongside the module catalog in the permissions-catalog response', function () {
    $admin = User::factory()->create();
    $admin->assignRole('yayasan_super_admin');

    $response = $this->actingAs($admin)->getJson(route('admin.roles.permissions-catalog'));

    $response->assertOk();
    $response->assertJsonStructure(['modules', 'audit' => ['missingFromDatabase', 'unusedInCode']]);
});

it('still denies the permissions-catalog endpoint to a user without roles.create or roles.edit', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson(route('admin.roles.permissions-catalog'))->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/PermissionAuditTest.php`
Expected: FAIL on the first test — response JSON has no `audit` key yet.

- [ ] **Step 3: Add the constructor and extend `permissionsCatalog()`**

In `app/Http/Controllers/Admin/RoleController.php`, add `use App\Services\PermissionAuditService;` to the imports, add a constructor (this class has none today), and change `permissionsCatalog()`:

```php
class RoleController extends BaseController
{
    use AuthorizesRequests;

    public function __construct(private PermissionAuditService $permissionAudit)
    {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        return view('admin.roles.index');
    }
```

(only the `use AuthorizesRequests;` line and everything below it stays exactly as-is — this just inserts the constructor immediately after it.) Then replace:

```php
    public function permissionsCatalog(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('roles.create') || $request->user()->can('roles.edit'), 403);

        return response()->json(['modules' => PermissionCatalog::grouped()]);
    }
```

with:

```php
    public function permissionsCatalog(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('roles.create') || $request->user()->can('roles.edit'), 403);

        $audit = $this->permissionAudit->audit();

        return response()->json([
            'modules' => PermissionCatalog::grouped(),
            'audit' => $audit,
        ]);
    }
```

Every other method in this file (`create`, `store`, `edit`, `update`, `destroy`, `data`, `errorResponse`, `scopeRank`) stays untouched.

- [ ] **Step 4: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/PermissionAuditTest.php`
Expected: PASS (2/2)

- [ ] **Step 5: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all tests pass, including the pre-existing `RoleBuilderTest.php` cases (the constructor addition must not break any of them — Laravel's container auto-resolves `PermissionAuditService`'s no-argument constructor, no manual binding needed).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/RoleController.php tests/Feature/Admin/PermissionAuditTest.php
git commit -m "feat: include permission audit results in the roles permissions-catalog endpoint"
```

---

### Task 3: Audit Banner UI on the Role Form

**Files:**
- Modify: `resources/js/role-form.js`
- Modify: `resources/views/admin/roles/_permission-matrix.blade.php`
- Test: `tests/Feature/Admin/RoleFormAuditBannerTest.php`

**Interfaces:**
- Consumes: the `audit` key in the JSON response of `GET admin/roles/permissions-catalog` (Task 2) — `{ missingFromDatabase: string[], unusedInCode: string[] }`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Admin/RoleFormAuditBannerTest.php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('renders the audit banner markup on the create-role page, ready to be populated by Alpine after a sync', function () {
    $admin = User::factory()->create();
    $admin->assignRole('yayasan_super_admin');

    $response = $this->actingAs($admin)->get(route('admin.roles.create'));

    $response->assertOk();
    $response->assertSee('auditMissingFromDatabase', false);
    $response->assertSee('auditUnusedInCode', false);
    $response->assertSee('permission baru ditemukan di kode');
    $response->assertSee('tidak dipakai di kode manapun');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/RoleFormAuditBannerTest.php`
Expected: FAIL — the banner markup doesn't exist yet.

- [ ] **Step 3: Extend `resources/js/role-form.js`**

Add two new state fields (`auditMissingFromDatabase`, `auditUnusedInCode`) and a dismiss method, and populate them inside the existing `syncPermissions()` method. Replace the whole file with:

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

        dismissAuditBanner() {
            this.showAuditBanner = false;
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

                this.auditMissingFromDatabase = json.audit?.missingFromDatabase ?? [];
                this.auditUnusedInCode = json.audit?.unusedInCode ?? [];
                this.showAuditBanner = this.auditMissingFromDatabase.length > 0 || this.auditUnusedInCode.length > 0;

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

- [ ] **Step 4: Extend `resources/views/admin/roles/_permission-matrix.blade.php`**

Replace the file with:

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

        <div x-show="showAuditBanner" x-cloak class="rounded-xl border border-signal-amber/40 bg-signal-amber/10 p-4 text-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="space-y-2">
                    <template x-if="auditMissingFromDatabase.length > 0">
                        <p>
                            <span class="font-semibold text-ink" x-text="auditMissingFromDatabase.length"></span>
                            permission baru ditemukan di kode, belum ada di database — sudah otomatis ditambahkan ke daftar checkbox di bawah:
                            <span class="font-mono text-xs" x-text="auditMissingFromDatabase.join(', ')" data-testid="auditMissingFromDatabase"></span>
                        </p>
                    </template>
                    <template x-if="auditUnusedInCode.length > 0">
                        <p>
                            <span class="font-semibold text-ink" x-text="auditUnusedInCode.length"></span>
                            permission di database tidak dipakai di kode manapun:
                            <span class="font-mono text-xs" x-text="auditUnusedInCode.join(', ')" data-testid="auditUnusedInCode"></span>
                        </p>
                    </template>
                </div>
                <button type="button" @click="dismissAuditBanner()" class="text-xs font-semibold text-slate hover:text-ink">Tutup</button>
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

Note: the `data-testid` attributes exist purely so the Feature test in Step 1 can assert the Alpine bindings are wired to the right state field names (`assertSee('auditMissingFromDatabase', false)`) — this is a static-HTML check (the raw `x-text="auditMissingFromDatabase..."` attribute string appearing in the response), not a check that the banner is visually populated, since no JS executes during a Feature test.

- [ ] **Step 5: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/RoleFormAuditBannerTest.php`
Expected: PASS (1/1)

- [ ] **Step 6: Run the full suite, then `npm run build`**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all tests pass, including every pre-existing `RoleBuilderTest.php`/`RoleModelTest.php` case (the create/edit form must still submit and load exactly as before).
Run: `npm run build`
Expected: builds cleanly, no console errors about `role-form.js`.

- [ ] **Step 7: Real-HTTP visual verification (no browser-automation tooling exists in this project — see Global Constraints)**

This step is mandatory, not optional, and must be reported on in the task report:

1. Start the dev server in the background: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan serve`
2. In a separate shell, create a temporary logged-in session for a `yayasan_super_admin` user and fetch the real rendered `admin/roles/create` page HTML (e.g. via `php artisan tinker` to create/log a test user's session cookie, or by writing a one-off route temporarily — remove any temporary code before committing). Confirm in the raw HTML output:
   - The banner `<div x-show="showAuditBanner" ...>` block is present with the exact structure from Step 4.
   - The "Sync Permission" button and its `@click="syncPermissions()"` binding are present and unchanged.
3. Fetch `GET admin/roles/permissions-catalog` with that same session and confirm the raw JSON response actually contains an `audit` key with `missingFromDatabase`/`unusedInCode` arrays (real data, not the test's temp-directory fixture — this hits the real `app/Http/Controllers`/`resources/views` scan).
4. Record the exact commands run and their raw output in the task report. If anything looks structurally wrong (missing attribute, malformed JSON, Alpine directive typo), fix it before proceeding — this is what step 7 exists to catch, on top of what the automated tests already cover.
5. This step confirms the page renders correctly and the data flows end-to-end; it does not execute JavaScript, so it cannot confirm the banner's dynamic show/hide behavior visually. Note this limitation explicitly in the report, and recommend the user do one live click-through in an actual browser before considering the feature fully done.

- [ ] **Step 8: Commit**

```bash
git add resources/js/role-form.js resources/views/admin/roles/_permission-matrix.blade.php tests/Feature/Admin/RoleFormAuditBannerTest.php
git commit -m "feat: show a dismissible audit banner on the role form's Sync Permission action"
```

---

## Post-Plan Note

After Task 3, the existing "Sync Permission" button on the Role create/edit form does real code-vs-database drift detection instead of just re-fetching an already-known catalog. No CLI command was built (deliberately, per the spec's scope) — `PermissionAuditService` is reusable if one is wanted later. The final manual click-through recommended in Task 3 Step 7 is the one verification this plan cannot fully automate given the project's current tooling (no Playwright/Puppeteer/Dusk installed) — flag this honestly to the user rather than claiming full visual coverage.
