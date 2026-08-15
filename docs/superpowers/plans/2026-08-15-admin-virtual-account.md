# Halaman Admin Kelola Virtual Account Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an admin page (`/admin/virtual-account`) to search/list students who already have a BRI Virtual Account, view their inbound payment history, generate VA numbers manually (single or bulk) for students who don't have one yet, and export the VA list to Excel.

**Architecture:** New `VirtualAccountController` (Admin namespace) follows the exact pattern of the existing `ManualPaymentController` — tenant-scoped queries via `lembagaId()`/`siswaLembagaId()` helpers, server-rendered index page with an AJAX-refreshed table fragment, Alpine.js-driven filters. No new models or migrations — everything reuses `BriVirtualAccount`, `BriInboundPaymentLog`, `Wallet`, `Siswa` from the earlier BRI SNAP VA Inbound work. VA creation itself is delegated entirely to the existing `PaymentService::getOrCreatePermanentVa()` — this plan adds no new VA-generation logic, only an admin-facing way to trigger it in bulk.

**Tech Stack:** Laravel 12, Blade + Alpine.js (no Livewire in this codebase), Pest-style tests (`it()`/`test()`), `maatwebsite/excel` (already installed) for export, Spatie permissions.

## Global Constraints

- Every controller method must call `$this->authorize('pembayaran.virtual-account')` — this is a brand-new permission, not a reuse of `pembayaran.verifikasi`.
- Every query must be scoped to the acting admin's lembaga using the exact `lembagaId()`/`siswaLembagaId()` pattern from `app/Http/Controllers/Admin/ManualPaymentController.php:73-93` — do not rely solely on `Siswa`'s implicit `TenantScope` (this codebase has a documented history of cross-tenant IDOR bugs; explicit checks are the established defensive convention here).
- VA generation must always filter to `Siswa::status === StatusSiswa::Aktif->value` server-side — never trust a status value from client input (there is no status field in the request at all, by design).
- Generate (bulk or manual) must never abort partway: each student is attempted independently (try/catch per student), a student's failure is logged and reported in the flash summary, but does not stop the others.
- No new database migrations. No new Eloquent models. Reuse `BriVirtualAccount`, `BriInboundPaymentLog`, `Wallet`, `Siswa`, `Kelas`.
- Money is not touched anywhere in this plan (VA creation is a local DB write, not a payment) — the money-safety patterns from the BRI SNAP VA Inbound work do not apply here, but the "never fail silently" pattern (log + report) does.

---

## Task 1: Add the `pembayaran.virtual-account` permission

**Files:**
- Modify: `database/seeders/PermissionSeeder.php:49`
- Modify: `database/seeders/RoleSeeder.php:53`
- Modify: `tests/Unit/PermissionSeederTest.php:11-17,31-36`
- Modify: `tests/Unit/RoleSeederTest.php:17-30,51-57`
- Modify: `tests/Feature/RolePermissionSeederTest.php:31,49,58,106-122,128-129`

**Interfaces:**
- Produces: permission name string `'pembayaran.virtual-account'`, assigned to role `admin_keuangan`. All later tasks' controller methods call `$this->authorize('pembayaran.virtual-account')` — this is the permission that check resolves against.

### Context you need before starting

This app seeds permissions in `PermissionSeeder.php` as a flat array of strings, then `RoleSeeder.php` assigns subsets to roles via `givePermissionTo([...])`. Three existing test files hardcode the **total permission count** and **admin_keuangan's permission count** as literal numbers. Adding one permission bumps every one of those numbers by exactly 1. If you skip updating any of them, that test will fail with a message like `Failed asserting that 106 matches expected 105` — this is expected and means you found all the places; don't "fix" it any other way.

- [ ] **Step 1: Add the permission to `PermissionSeeder.php`**

Read `database/seeders/PermissionSeeder.php:47-52` first — it currently reads:

```php
            'jenis-tagihan.view', 'jenis-tagihan.create', 'jenis-tagihan.edit', 'jenis-tagihan.delete',
            'tagihan.view', 'tagihan.buat-susulan',
            'pembayaran.view', 'pembayaran.verifikasi', 'pembayaran.catat-manual',
            'cicilan.kelola',
            'keuangan.akses',
            'yayasan.kelola',
```

Change line 49 to:

```php
            'pembayaran.view', 'pembayaran.verifikasi', 'pembayaran.catat-manual', 'pembayaran.virtual-account',
```

- [ ] **Step 2: Assign the permission to `admin_keuangan` in `RoleSeeder.php`**

Read `database/seeders/RoleSeeder.php:49-57` first — it currently reads:

```php
            if ($name === 'admin_keuangan') {
                $role->givePermissionTo([
                    'jenis-tagihan.view', 'jenis-tagihan.create', 'jenis-tagihan.edit', 'jenis-tagihan.delete',
                    'tagihan.view', 'tagihan.buat-susulan',
                    'pembayaran.view', 'pembayaran.verifikasi', 'pembayaran.catat-manual',
                    'cicilan.kelola',
                    'spmb-pendaftaran.view',
                ]);
            }
```

Change line 53 to:

```php
                    'pembayaran.view', 'pembayaran.verifikasi', 'pembayaran.catat-manual', 'pembayaran.virtual-account',
```

- [ ] **Step 3: Update `tests/Unit/PermissionSeederTest.php`**

Read the file first (it's 45 lines). Change:
- Line 14: `expect(Permission::count())->toBe(105);` → `expect(Permission::count())->toBe(106);`
- Line 35: `expect(Permission::count())->toBe(105);` → `expect(Permission::count())->toBe(106);`

Also add a new assertion right after line 16 (inside the `'seeds exactly 85 permissions'` test, after the `cicilan.kelola` existence check) so the new permission's existence is explicitly covered:

```php
    expect(Permission::where('name', 'pembayaran.virtual-account')->exists())->toBeTrue();
```

- [ ] **Step 4: Update `tests/Unit/RoleSeederTest.php`**

Read the file first (it's 115 lines). Change:
- Line 23: `expect($superAdmin->permissions()->count())->toBe(105);` → `->toBe(106);`
- Line 55: `expect($adminKeuangan->permissions()->count())->toBe(11);` → `->toBe(12);`

Also add this assertion right after line 56 (`expect($adminKeuangan->hasPermissionTo('cicilan.kelola'))->toBeTrue();`) inside the `'gives admin_keuangan the correct 11 permissions'` test:

```php
    expect($adminKeuangan->hasPermissionTo('pembayaran.virtual-account'))->toBeTrue();
```

- [ ] **Step 5: Update `tests/Feature/RolePermissionSeederTest.php`**

Read the file first (it's 135+ lines) — this is a composite test exercising `RolePermissionSeeder` (which just calls `PermissionSeeder` then `RoleSeeder` — see `database/seeders/RolePermissionSeeder.php`). Same four numbers need the same +1 bump:
- Line 31: add `'pembayaran.virtual-account'` to the `$expected` array (next to `'pembayaran.catat-manual'`).
- Line 49: `expect(Permission::count())->toBe(105);` → `->toBe(106);`
- Line 58: `expect($superAdmin->permissions()->count())->toBe(105);` → `->toBe(106);`
- Line 113: add `'pembayaran.virtual-account'` to the `$expected` array in the `admin_keuangan` test (next to `'pembayaran.catat-manual'`).
- Line 121: `expect($adminKeuangan->permissions()->count())->toBe(11);` → `->toBe(12);`
- Line 129: `expect(Permission::count())->toBe(105);` → `->toBe(106);`

- [ ] **Step 6: Run the affected tests to verify they pass**

Run: `php artisan test tests/Unit/PermissionSeederTest.php tests/Unit/RoleSeederTest.php tests/Feature/RolePermissionSeederTest.php tests/Feature/Keuangan/KeuanganPermissionSeedTest.php`
Expected: all tests PASS (the last file is unrelated to this change but sits in the same seeder-testing area — run it as a smoke check that nothing else broke).

- [ ] **Step 7: Commit**

```bash
git add database/seeders/PermissionSeeder.php database/seeders/RoleSeeder.php tests/Unit/PermissionSeederTest.php tests/Unit/RoleSeederTest.php tests/Feature/RolePermissionSeederTest.php
git commit -m "feat(admin): tambah permission pembayaran.virtual-account"
```

---

## Task 2: Add `Wallet::briVirtualAccounts()` relation

**Files:**
- Modify: `app/Models/Wallet.php:26-29` (right after the existing `siswa()` relation)
- Test: `tests/Unit/Models/WalletBriVirtualAccountsRelationTest.php` (create)

**Interfaces:**
- Consumes: nothing new.
- Produces: `Wallet::briVirtualAccounts(): HasMany` — returns all `BriVirtualAccount` rows for this wallet. Later tasks (3, 5, 6) use `whereDoesntHave('wallet.briVirtualAccounts', ...)` and `whereHas('wallet.briVirtualAccounts', ...)` on `Siswa`/`BriVirtualAccount` queries — this relation is what makes that dot-notation nested-relation syntax work.

### Context you need before starting

`app/Models/Wallet.php` currently has no relation to `BriVirtualAccount` at all (confirmed by reading the whole file — only `siswa()` and `mutasi()` exist). `BriVirtualAccount` already has the inverse `wallet(): BelongsTo` in `app/Models/BriVirtualAccount.php:26-29`. You're adding the missing other half.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/WalletBriVirtualAccountsRelationTest.php`:

```php
<?php

use App\Models\BriVirtualAccount;
use App\Models\Siswa;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns the bri virtual accounts belonging to a wallet', function () {
    $siswa = Siswa::factory()->create();
    $wallet = Wallet::factory()->create(['siswa_id' => $siswa->id]);

    $va = BriVirtualAccount::create([
        'wallet_id' => $wallet->id,
        'va_type' => 'WALLET_PERMANENT',
        'va_number' => '88081234567890',
        'status' => 'PERMANENT',
    ]);

    expect($wallet->briVirtualAccounts)->toHaveCount(1);
    expect($wallet->briVirtualAccounts->first()->id)->toBe($va->id);
});

it('returns an empty collection when the wallet has no virtual account yet', function () {
    $siswa = Siswa::factory()->create();
    $wallet = Wallet::factory()->create(['siswa_id' => $siswa->id]);

    expect($wallet->briVirtualAccounts)->toHaveCount(0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Models/WalletBriVirtualAccountsRelationTest.php`
Expected: FAIL with `Call to undefined method App\Models\Wallet::briVirtualAccounts()`

- [ ] **Step 3: Add the relation**

In `app/Models/Wallet.php`, add the `use` import for `HasMany` — check line 10 first, it already has:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
```

(This import already exists because `mutasi(): HasMany` uses it — no import change needed.)

Add the new method right after `siswa()` (after line 29, before `mutasi()`):

```php
    public function briVirtualAccounts(): HasMany
    {
        return $this->hasMany(BriVirtualAccount::class);
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Models/WalletBriVirtualAccountsRelationTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Models/Wallet.php tests/Unit/Models/WalletBriVirtualAccountsRelationTest.php
git commit -m "feat(admin): tambah relasi Wallet::briVirtualAccounts()"
```

---

## Task 3: Controller skeleton — `index()` + route + view + sidebar menu

**Files:**
- Create: `app/Http/Controllers/Admin/VirtualAccountController.php`
- Modify: `routes/admin.php:32` (add `use` import), `routes/admin.php:210-212` (add route)
- Modify: `resources/views/layouts/sidebar.blade.php:44-45` (add menu item at the TOP of the Keuangan group)
- Create: `resources/views/admin/virtual-account/index.blade.php`
- Create: `resources/views/admin/virtual-account/_daftar.blade.php`
- Create: `resources/js/virtual-account-filter.js`
- Modify: `resources/js/app.js:43` (import), `resources/js/app.js:89` (register — after the last `Alpine.data(...)` line, `triaseForm`)
- Test: `tests/Feature/Admin/VirtualAccountControllerTest.php` (create)

**Interfaces:**
- Consumes: `Wallet::briVirtualAccounts()` from Task 2.
- Produces: `VirtualAccountController::index()`, private helpers `lembagaId(Request $request): ?int` and `siswaLembagaId(?int $siswaId): ?int` (Tasks 4, 5, 6 reuse both helpers — copy them into the same controller class body in this task, don't redefine them later). Route name `admin.virtual-account.index`.

### Context you need before starting

This is the "walking skeleton" task: after this task, `/admin/virtual-account` is a real, working, tested page — just without the generate/riwayat/export features yet (those come in Tasks 4-8). Read `app/Http/Controllers/Admin/ManualPaymentController.php` in full first (it's short, ~226 lines) — you are copying its `lembagaId()`/`siswaLembagaId()` pattern verbatim, and its AJAX-fragment-detection pattern verbatim.

Two things are NOT obvious from that file and matter here:
1. `Siswa` has a `BelongsToTenant` trait that auto-applies tenant filtering to any `Siswa::` query for the logged-in user — but this codebase's convention (per `ManualPaymentController`) is to ALSO filter explicitly rather than relying on that alone. Follow the same convention here.
2. `BriVirtualAccount` and `Wallet` do NOT have tenant scoping themselves — only `Siswa` does. Since `index()` queries `BriVirtualAccount` as its base table (not `Siswa`), tenant scoping must be applied via an explicit `whereHas('wallet.siswa', ...)` closure — it does NOT happen automatically just because `Siswa` is nested in the relation chain (the closure's query builder is unscoped by default for a `BelongsTo`/`HasOne` closure — you must add the `where('lembaga_id', $lembagaId)` inside the closure yourself).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/VirtualAccountControllerTest.php`:

```php
<?php

use App\Models\BriVirtualAccount;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function buatAdminKeuanganUntukVirtualAccount(): array
{
    Permission::firstOrCreate(['name' => 'pembayaran.virtual-account', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_keuangan', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('pembayaran.virtual-account');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    return [$user, $lembaga];
}

function buatSiswaDenganVa(Lembaga $lembaga, string $nama, ?Kelas $kelas = null): array
{
    $siswa = Siswa::factory()->create([
        'lembaga_id' => $lembaga->id,
        'nama_lengkap' => $nama,
        'kelas_id' => $kelas?->id,
        'status' => 'aktif',
    ]);
    $wallet = Wallet::factory()->create(['siswa_id' => $siswa->id, 'balance' => 50000]);
    $va = BriVirtualAccount::create([
        'wallet_id' => $wallet->id,
        'va_type' => 'WALLET_PERMANENT',
        'va_number' => '8808' . str_pad((string) $siswa->id, 16, '0', STR_PAD_LEFT),
        'status' => 'PERMANENT',
    ]);

    return [$siswa, $wallet, $va];
}

it('denies access without pembayaran.virtual-account permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.virtual-account.index'))->assertForbidden();
});

it('lists only students who already have a virtual account', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    buatSiswaDenganVa($lembaga, 'Sudah Punya VA');
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Belum Punya VA', 'status' => 'aktif']);

    $response = $this->actingAs($user)->get(route('admin.virtual-account.index'));

    $response->assertOk();
    $response->assertSee('Sudah Punya VA');
    $response->assertDontSee('Belum Punya VA');
});

it('scopes the list to the admin own lembaga', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    buatSiswaDenganVa($lembaga, 'Anak Lembaga Sendiri');

    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    buatSiswaDenganVa($otherLembaga, 'Anak Lembaga Lain');

    $response = $this->actingAs($user)->get(route('admin.virtual-account.index'));

    $response->assertOk();
    $response->assertSee('Anak Lembaga Sendiri');
    $response->assertDontSee('Anak Lembaga Lain');
});

it('filters by search on siswa name', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    buatSiswaDenganVa($lembaga, 'Budi Santoso');
    buatSiswaDenganVa($lembaga, 'Siti Aminah');

    $response = $this->actingAs($user)->get(route('admin.virtual-account.index', ['search' => 'Budi']));

    $response->assertOk();
    $response->assertSee('Budi Santoso');
    $response->assertDontSee('Siti Aminah');
});

it('filters by kelas', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '6A']);
    $kelasB = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '6B']);
    buatSiswaDenganVa($lembaga, 'Anak Kelas A', $kelasA);
    buatSiswaDenganVa($lembaga, 'Anak Kelas B', $kelasB);

    $response = $this->actingAs($user)->get(route('admin.virtual-account.index', ['kelas_id' => $kelasA->id]));

    $response->assertOk();
    $response->assertSee('Anak Kelas A');
    $response->assertDontSee('Anak Kelas B');
});

it('returns only the table partial for an AJAX request', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    buatSiswaDenganVa($lembaga, 'Anak Ajax');

    $response = $this->actingAs($user)->get(route('admin.virtual-account.index'), ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertOk();
    $response->assertDontSee('<x-app-layout', false);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/VirtualAccountControllerTest.php`
Expected: FAIL — route `admin.virtual-account.index` not defined.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Admin/VirtualAccountController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\BriVirtualAccount;
use App\Models\Kelas;
use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class VirtualAccountController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('pembayaran.virtual-account');

        $lembagaId = $this->lembagaId($request);
        $search = $request->input('search');
        $kelasId = $request->input('kelas_id');

        $query = BriVirtualAccount::where('va_type', 'WALLET_PERMANENT')
            ->whereHas('wallet.siswa', function ($q) use ($lembagaId, $search, $kelasId) {
                $q->where('lembaga_id', $lembagaId);

                if ($search) {
                    $q->where(function ($q2) use ($search) {
                        $q2->where('nama_lengkap', 'like', "%{$search}%")
                            ->orWhere('nis', 'like', "%{$search}%");
                    });
                }

                if ($kelasId) {
                    $q->where('kelas_id', $kelasId);
                }
            })
            ->with(['wallet.siswa.kelas'])
            ->latest('created_at');

        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;
        $paginated = $query->paginate($perPage)->withQueryString();

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('admin.virtual-account._daftar', [
                'vaList' => $paginated,
                'perPage' => $perPage,
            ]);
        }

        $kelasList = Kelas::where('lembaga_id', $lembagaId)->orderBy('nama')->get();

        return view('admin.virtual-account.index', [
            'vaList' => $paginated,
            'perPage' => $perPage,
            'kelasList' => $kelasList,
        ]);
    }

    private function lembagaId(Request $request): ?int
    {
        return $request->user()->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $request->user()->lembaga_id;
    }

    // Siswa punya TenantScope global (BelongsToTenant) yang otomatis memfilter query
    // berdasarkan tenant user yang sedang login. Method ini sengaja bypass scope itu
    // untuk mendapatkan lembaga_id SEBENARNYA dari siswa manapun (termasuk siswa
    // tenant lain), supaya bisa dibandingkan eksplisit dengan lembagaId() — pola
    // sama persis app/Http/Controllers/Admin/ManualPaymentController.php:86-93.
    private function siswaLembagaId(?int $siswaId): ?int
    {
        if ($siswaId === null) {
            return null;
        }

        return Siswa::withoutGlobalScope(TenantScope::class)->find($siswaId)?->lembaga_id;
    }
}
```

- [ ] **Step 4: Add the route**

In `routes/admin.php`, add the import at line 32 (alphabetically between `SkPpdbController` and `SpmbKonfigurasiController` — check current lines 47-48 to find exact spot):

```php
use App\Http\Controllers\Admin\VirtualAccountController;
```

This goes right after line 51 (`use App\Http\Controllers\Admin\UserController;`) and before line 52 (`use App\Http\Controllers\Admin\WhatsAppTemplateController;`) — alphabetically `V` comes between `U` and `W`.

Add the route right after the `manual-payment` routes (after line 212, before line 214's blank line / `pola-jam` routes):

```php

    Route::get('virtual-account', [VirtualAccountController::class, 'index'])->name('virtual-account.index');
```

- [ ] **Step 5: Create the index view**

Create `resources/views/admin/virtual-account/index.blade.php`:

```blade
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Virtual Account</h1>
                <p class="mt-0.5 text-xs text-gray-500">Kelola nomor Virtual Account BRI siswa untuk top-up wallet.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Virtual Account</b>
            </p>
        </div>

        <div
            class="space-y-4"
            x-data="virtualAccountFilter({
                search: @js(request('search', '')),
                kelasId: @js(request('kelas_id', '')),
                perPage: @js($perPage ?? 20),
                indexUrlBase: @js(route('admin.virtual-account.index')),
            })"
        >
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                        Filter Data
                    </p>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:items-end">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Cari Nama/NIS Siswa</label>
                        <div class="flex h-[42px] items-center gap-2 rounded-[10px] border border-gray-200 bg-gray-50 px-3.5">
                            <x-icon name="search" class="h-[14px] w-[14px] shrink-0 text-gray-400" />
                            <input type="text" x-model="search" @input.debounce.400ms="muatUlangDaftar()" placeholder="Nama atau NIS siswa..." class="w-full border-0 bg-transparent p-0 text-xs sm:text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0">
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Kelas</label>
                        <select x-model="kelasId" @change="muatUlangDaftar()" class="w-full rounded-lg border-gray-200 text-sm">
                            <option value="">Semua Kelas</option>
                            @foreach ($kelasList as $kelas)
                                <option value="{{ $kelas->id }}">{{ $kelas->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div x-ref="daftarVirtualAccount">
                @include('admin.virtual-account._daftar')
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Create the AJAX fragment view**

Create `resources/views/admin/virtual-account/_daftar.blade.php`:

```blade
<div class="rounded-2xl border border-gray-200 bg-white shadow-card overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">Daftar Virtual Account</p>
        <div class="flex items-center gap-2">
            <label for="per_page" class="text-xs font-medium text-gray-500">Tampilkan:</label>
            <select id="per_page" x-model="perPage" @change="muatUlangDaftar()" class="rounded-lg border-gray-200 py-1 pl-2.5 pr-8 text-xs text-gray-700 shadow-sm transition focus:border-brand-500 focus:ring-brand-500">
                @foreach ([10, 20, 25, 50] as $n)
                    <option value="{{ $n }}" @selected(($perPage ?? 20) == $n)>{{ $n }} / hal</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-sm">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50/75 font-display text-xs font-bold uppercase tracking-wider text-gray-500">
                    <th class="px-4 py-3">Nama Siswa</th>
                    <th class="px-4 py-3">Kelas</th>
                    <th class="px-4 py-3">Nomor VA</th>
                    <th class="px-4 py-3 text-right">Saldo Wallet</th>
                    <th class="px-4 py-3">Tanggal Dibuat</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 font-normal">
                @forelse ($vaList as $va)
                    <tr class="transition-colors hover:bg-gray-50/60">
                        <td class="px-4 py-3.5 font-medium text-gray-900">{{ $va->wallet->siswa->nama_lengkap ?? '-' }}</td>
                        <td class="px-4 py-3.5 text-xs text-gray-600">{{ $va->wallet->siswa->kelas->nama ?? '-' }}</td>
                        <td class="px-4 py-3.5 font-mono text-xs font-semibold text-gray-700">{{ $va->va_number }}</td>
                        <td class="px-4 py-3.5 text-right font-mono text-xs font-semibold text-gray-700">Rp{{ number_format($va->wallet->balance, 0, ',', '.') }}</td>
                        <td class="px-4 py-3.5 text-xs text-gray-600">{{ $va->created_at->translatedFormat('d M Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-gray-500">
                            <p class="text-sm">Belum ada siswa dengan nomor Virtual Account.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($vaList->hasPages())
        <div class="border-t border-gray-200 px-5 py-3">
            {{ $vaList->links('pagination.tailadmin') }}
        </div>
    @endif
</div>
```

(The "Lihat Riwayat" action column and its modal are added in Task 4 — this task deliberately ships without it so the table works and is tested first.)

- [ ] **Step 7: Create the Alpine filter component**

Create `resources/js/virtual-account-filter.js`:

```js
export function virtualAccountFilter(config) {
    return {
        search: config.search ?? '',
        kelasId: config.kelasId ?? '',
        perPage: config.perPage ?? 20,
        indexUrlBase: config.indexUrlBase,

        async muatUlangDaftar() {
            try {
                const url = new URL(this.indexUrlBase, window.location.origin);
                if (this.search) url.searchParams.set('search', this.search);
                if (this.kelasId) url.searchParams.set('kelas_id', this.kelasId);
                if (this.perPage !== 20) url.searchParams.set('per_page', this.perPage);

                const response = await fetch(url, {
                    headers: {
                        Accept: 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    Alpine.store('toast').push('error', 'Gagal memuat daftar virtual account.');
                    return;
                }

                const html = await response.text();
                this.perbaruiUrl(url);
                this.$refs.daftarVirtualAccount.innerHTML = html;
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memuat daftar virtual account.');
            }
        },

        perbaruiUrl(url) {
            window.history.pushState({}, '', url);
        },
    };
}
```

- [ ] **Step 8: Register the component in `app.js`**

In `resources/js/app.js`, add the import after line 43 (`import { triaseForm } from './triase-form';` is currently the last import):

```js
import { virtualAccountFilter } from './virtual-account-filter';
```

Add the registration after line 89 (`Alpine.data('triaseForm', triaseForm);` is currently the last registration line — check the file's current end to confirm, since Tasks 4-8 will also append to this same list):

```js
Alpine.data('virtualAccountFilter', virtualAccountFilter);
```

- [ ] **Step 9: Add the sidebar menu item at the TOP of the Keuangan group**

In `resources/views/layouts/sidebar.blade.php`, read lines 41-52 first. The `'items' => array_filter([` array on line 44 currently starts with the Jenis Tagihan item. Insert the new Virtual Account item as the very FIRST element of that array, before line 45:

```php
                Auth::user()->can('pembayaran.virtual-account') ? ['route' => 'admin.virtual-account.index', 'pattern' => 'admin.virtual-account.*', 'label' => 'Virtual Account', 'icon' => 'credit-card'] : null,
```

So lines 44-45 become:

```php
            'items' => array_filter([
                Auth::user()->can('pembayaran.virtual-account') ? ['route' => 'admin.virtual-account.index', 'pattern' => 'admin.virtual-account.*', 'label' => 'Virtual Account', 'icon' => 'credit-card'] : null,
                Auth::user()->can('jenis-tagihan.view') ? ['route' => 'admin.jenis-tagihan.index', 'pattern' => 'admin.jenis-tagihan.*', 'label' => 'Jenis Tagihan', 'icon' => 'wallet'] : null,
```

- [ ] **Step 10: Build frontend assets**

Run: `npm run build`
Expected: build succeeds with no errors (this compiles `virtual-account-filter.js` into the bundle).

- [ ] **Step 11: Run test to verify it passes**

Run: `php artisan test tests/Feature/Admin/VirtualAccountControllerTest.php`
Expected: PASS (7 tests)

- [ ] **Step 12: Commit**

```bash
git add app/Http/Controllers/Admin/VirtualAccountController.php routes/admin.php resources/views/layouts/sidebar.blade.php resources/views/admin/virtual-account/index.blade.php resources/views/admin/virtual-account/_daftar.blade.php resources/js/virtual-account-filter.js resources/js/app.js tests/Feature/Admin/VirtualAccountControllerTest.php
git commit -m "feat(admin): tambah halaman index Virtual Account dengan filter"
```

---

## Task 4: `riwayat()` endpoint + modal

**Files:**
- Modify: `app/Http/Controllers/Admin/VirtualAccountController.php` (add `riwayat()` method + `use` imports)
- Modify: `routes/admin.php` (add route right after `virtual-account.index`)
- Create: `resources/views/admin/virtual-account/_riwayat-list.blade.php`
- Create: `resources/views/admin/virtual-account/_riwayat-modal.blade.php`
- Modify: `resources/views/admin/virtual-account/_daftar.blade.php` (add "Lihat Riwayat" button column)
- Modify: `resources/views/admin/virtual-account/index.blade.php` (include the modal)
- Test: `tests/Feature/Admin/VirtualAccountControllerTest.php` (append)

**Interfaces:**
- Consumes: `BriVirtualAccount`, `BriInboundPaymentLog` models (both pre-existing, no changes).
- Produces: `VirtualAccountController::riwayat(Request $request, Siswa $siswa)`, route name `admin.virtual-account.riwayat`. No other task depends on this one's internals.

### Context you need before starting

`BriInboundPaymentLog` (from `database/migrations/2026_08_15_100000_create_bri_inbound_payment_logs_table.php`) stores `payment_request_id`, `va_number`, `amount`, and a nullable `pembayaran_id`. The most reliable way to find a student's payment history is to look up their VA number first, then filter `BriInboundPaymentLog` by that exact `va_number` — do NOT go through the `pembayaran_id` relation, since that FK is nullable and its exact population semantics were shaped by a different concern (idempotent replay handling) in the BRI SNAP VA Inbound work, not payment-history lookup.

The modal fetches its content via `fetch()` and injects it with Alpine's `x-html` — this is different from the `_daftar.blade.php` pattern (which replaces `innerHTML` directly via `$refs`). Either works for static content display; `x-html` is used here because the modal has no other Alpine-bound children inside the fetched fragment (the fragment is a plain read-only table, no inputs/checkboxes) — if you needed interactive elements inside fetched content, you would NOT use `x-html` (Alpine does not process directives in dynamically-injected HTML), you'd fetch JSON and render with `x-for` instead. Task 7's candidate-picker table needs interactivity, so it uses the JSON+`x-for` approach instead of this one.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Admin/VirtualAccountControllerTest.php` (add at the end of the file, add `use App\Models\BriInboundPaymentLog;` to the top `use` block first):

```php
it('shows the inbound payment history for a student virtual account', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    [$siswa, , $va] = buatSiswaDenganVa($lembaga, 'Anak Riwayat');

    BriInboundPaymentLog::create([
        'payment_request_id' => 'PR-001',
        'va_number' => $va->va_number,
        'amount' => 75000,
    ]);

    $response = $this->actingAs($user)->get(route('admin.virtual-account.riwayat', $siswa));

    $response->assertOk();
    $response->assertSee('75.000');
    $response->assertSee('PR-001');
});

it('shows an empty state when a student virtual account has no payment history', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    [$siswa] = buatSiswaDenganVa($lembaga, 'Anak Belum Bayar');

    $response = $this->actingAs($user)->get(route('admin.virtual-account.riwayat', $siswa));

    $response->assertOk();
    $response->assertSee('Belum ada pembayaran');
});

it('returns 404 when viewing riwayat for a student in another lembaga', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    [$siswaLain] = buatSiswaDenganVa($otherLembaga, 'Anak Lembaga Lain Riwayat');

    $this->actingAs($user)->get(route('admin.virtual-account.riwayat', $siswaLain))->assertNotFound();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/VirtualAccountControllerTest.php --filter=riwayat`
Expected: FAIL — route `admin.virtual-account.riwayat` not defined.

- [ ] **Step 3: Add the `riwayat()` method**

In `app/Http/Controllers/Admin/VirtualAccountController.php`, add these imports at the top (alphabetical order with the existing ones):

```php
use App\Models\BriInboundPaymentLog;
```

Add the method right after `index()` (before the `lembagaId()` private helper):

```php
    public function riwayat(Request $request, Siswa $siswa): View
    {
        $this->authorize('pembayaran.virtual-account');

        $siswaLembagaId = $this->siswaLembagaId($siswa->id);
        abort_unless($siswaLembagaId !== null && $siswaLembagaId === $this->lembagaId($request), 404);

        $va = BriVirtualAccount::where('wallet_id', $siswa->wallet?->id)
            ->where('va_type', 'WALLET_PERMANENT')
            ->first();

        $logs = $va
            ? BriInboundPaymentLog::where('va_number', $va->va_number)->latest()->get()
            : collect();

        return view('admin.virtual-account._riwayat-list', ['logs' => $logs]);
    }
```

- [ ] **Step 4: Add the route**

In `routes/admin.php`, right after the `virtual-account.index` route added in Task 3:

```php
    Route::get('virtual-account/{siswa}/riwayat', [VirtualAccountController::class, 'riwayat'])->name('virtual-account.riwayat');
```

- [ ] **Step 5: Create the riwayat fragment view**

Create `resources/views/admin/virtual-account/_riwayat-list.blade.php`:

```blade
@if ($logs->isEmpty())
    <p class="text-sm text-gray-400 text-center py-6">Belum ada pembayaran masuk lewat VA ini.</p>
@else
    <table class="w-full text-left text-xs">
        <thead>
            <tr class="text-[10px] uppercase tracking-wider text-gray-400 border-b border-gray-100">
                <th class="py-2">Tanggal</th>
                <th class="py-2 text-right">Nominal</th>
                <th class="py-2">Referensi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @foreach ($logs as $log)
                <tr>
                    <td class="py-2 text-gray-600">{{ $log->created_at->translatedFormat('d M Y H:i') }}</td>
                    <td class="py-2 text-right font-mono font-semibold text-gray-800">Rp{{ number_format($log->amount, 0, ',', '.') }}</td>
                    <td class="py-2 font-mono text-gray-500">{{ $log->payment_request_id }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
```

- [ ] **Step 6: Create the modal**

Create `resources/views/admin/virtual-account/_riwayat-modal.blade.php`:

```blade
<div
    x-data="{ open: false, siswaId: null, siswaNama: '', loading: false, html: '' }"
    x-on:open-riwayat-modal.window="
        open = true; siswaId = $event.detail.siswaId; siswaNama = $event.detail.siswaNama; loading = true; html = '';
        fetch(`{{ url('admin/virtual-account') }}/${siswaId}/riwayat`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text()).then(t => { html = t; loading = false; })
            .catch(() => { loading = false; html = '<p class=\'text-sm text-error-600\'>Gagal memuat riwayat.</p>'; });
    "
    x-show="open"
    style="display: none;"
    class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4"
>
    <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-elevated" @click.outside="open = false">
        <div class="flex items-center justify-between">
            <h3 class="font-display text-sm font-bold text-gray-900">Riwayat Pembayaran VA — <span x-text="siswaNama"></span></h3>
            <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        <div class="mt-4 max-h-96 overflow-y-auto" x-show="loading">
            <p class="text-sm text-gray-400">Memuat...</p>
        </div>
        <div class="mt-4 max-h-96 overflow-y-auto" x-show="!loading" x-html="html"></div>
    </div>
</div>
```

- [ ] **Step 7: Add the "Lihat Riwayat" button to the table**

In `resources/views/admin/virtual-account/_daftar.blade.php`, change the `<thead>` row (add an Aksi column at the end):

```blade
                    <th class="px-4 py-3">Tanggal Dibuat</th>
                    <th class="px-4 py-3 w-32">Aksi</th>
```

And in the `@forelse` row, add a new `<td>` right before the closing `</tr>` (after the "Tanggal Dibuat" `<td>`):

```blade
                        <td class="px-4 py-3.5">
                            <button type="button" x-data @click="$dispatch('open-riwayat-modal', { siswaId: {{ $va->wallet->siswa_id }}, siswaNama: @js($va->wallet->siswa->nama_lengkap ?? '-') })" class="rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100">Lihat Riwayat</button>
                        </td>
```

Also update the `colspan="5"` in the `@empty` row to `colspan="6"` (one more column now).

- [ ] **Step 8: Include the modal in the index page**

In `resources/views/admin/virtual-account/index.blade.php`, add right before the closing `</div>` of the outermost `<div class="mx-auto max-w-6xl space-y-4">` (after the `x-data="virtualAccountFilter(...)"` block closes):

```blade

        @include('admin.virtual-account._riwayat-modal')
```

- [ ] **Step 9: Run test to verify it passes**

Run: `php artisan test tests/Feature/Admin/VirtualAccountControllerTest.php`
Expected: PASS (10 tests total)

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Admin/VirtualAccountController.php routes/admin.php resources/views/admin/virtual-account tests/Feature/Admin/VirtualAccountControllerTest.php
git commit -m "feat(admin): tambah riwayat pembayaran VA per siswa"
```

---

## Task 5: `calonGenerate()` endpoint (candidate list for manual generate)

**Files:**
- Modify: `app/Http/Controllers/Admin/VirtualAccountController.php` (add `calonGenerate()` method + `use App\Enums\StatusSiswa;` import)
- Modify: `routes/admin.php` (add route)
- Test: `tests/Feature/Admin/VirtualAccountControllerTest.php` (append)

**Interfaces:**
- Consumes: `Wallet::briVirtualAccounts()` from Task 2.
- Produces: `VirtualAccountController::calonGenerate(Request $request): JsonResponse` returning `{"data": [{"id", "nama_lengkap", "nis", "kelas"}, ...]}`. Route name `admin.virtual-account.calon`. Task 7's frontend JS consumes this exact JSON shape.

### Context you need before starting

This returns **JSON**, not a Blade fragment — unlike `index()`'s AJAX branch. That's a deliberate choice: Task 7's "Pilih Manual" table needs checkboxes with live-updating selection state, and Alpine cannot bind directives (`@click`, `:checked`, etc.) to HTML injected via `innerHTML` or `x-html` after the fact. Fetching JSON and rendering rows client-side with Alpine's `x-for` keeps the checkboxes fully interactive. (This exact gotcha — "Alpine doesn't process directives in injected HTML" — is a known trap in this codebase; the `index()` fragment in Task 3 avoids it because that fragment has no interactive children, only a `<select>` outside the fragment controls it.)

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Admin/VirtualAccountControllerTest.php`:

```php
it('calon-generate returns only active students without a virtual account', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    buatSiswaDenganVa($lembaga, 'Sudah Ada VA');
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Belum Ada VA', 'status' => 'aktif']);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Siswa Lulus', 'status' => 'lulus']);

    $response = $this->actingAs($user)->getJson(route('admin.virtual-account.calon'));

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('nama_lengkap');
    expect($names)->toContain('Belum Ada VA');
    expect($names)->not->toContain('Sudah Ada VA');
    expect($names)->not->toContain('Siswa Lulus');
});

it('calon-generate scopes to the admin own lembaga', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Calon Lembaga Sendiri', 'status' => 'aktif']);

    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    Siswa::factory()->create(['lembaga_id' => $otherLembaga->id, 'nama_lengkap' => 'Calon Lembaga Lain', 'status' => 'aktif']);

    $response = $this->actingAs($user)->getJson(route('admin.virtual-account.calon'));

    $names = collect($response->json('data'))->pluck('nama_lengkap');
    expect($names)->toContain('Calon Lembaga Sendiri');
    expect($names)->not->toContain('Calon Lembaga Lain');
});

it('calon-generate filters by search and kelas', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '5A']);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'nama_lengkap' => 'Ahmad Fauzi', 'status' => 'aktif']);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Dewi Lestari', 'status' => 'aktif']);

    $response = $this->actingAs($user)->getJson(route('admin.virtual-account.calon', ['search' => 'Ahmad']));
    expect(collect($response->json('data'))->pluck('nama_lengkap'))->toContain('Ahmad Fauzi');
    expect(collect($response->json('data'))->pluck('nama_lengkap'))->not->toContain('Dewi Lestari');

    $response = $this->actingAs($user)->getJson(route('admin.virtual-account.calon', ['kelas_id' => $kelas->id]));
    expect(collect($response->json('data'))->pluck('nama_lengkap'))->toContain('Ahmad Fauzi');
    expect(collect($response->json('data'))->pluck('nama_lengkap'))->not->toContain('Dewi Lestari');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/VirtualAccountControllerTest.php --filter=calon-generate`
Expected: FAIL — route `admin.virtual-account.calon` not defined.

- [ ] **Step 3: Add the `calonGenerate()` method**

Add this import to the top of `app/Http/Controllers/Admin/VirtualAccountController.php`:

```php
use App\Enums\StatusSiswa;
use Illuminate\Http\JsonResponse;
```

Add the method right after `riwayat()`:

```php
    public function calonGenerate(Request $request): JsonResponse
    {
        $this->authorize('pembayaran.virtual-account');

        $lembagaId = $this->lembagaId($request);
        $search = $request->input('search');
        $kelasId = $request->input('kelas_id');

        $query = Siswa::where('lembaga_id', $lembagaId)
            ->where('status', StatusSiswa::Aktif->value)
            ->whereDoesntHave('wallet.briVirtualAccounts', fn ($q) => $q->where('va_type', 'WALLET_PERMANENT'))
            ->with('kelas');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%");
            });
        }

        if ($kelasId) {
            $query->where('kelas_id', $kelasId);
        }

        $siswaList = $query->orderBy('nama_lengkap')->limit(200)->get();

        return response()->json([
            'data' => $siswaList->map(fn (Siswa $siswa) => [
                'id' => $siswa->id,
                'nama_lengkap' => $siswa->nama_lengkap,
                'nis' => $siswa->nis,
                'kelas' => $siswa->kelas->nama ?? '-',
            ]),
        ]);
    }
```

- [ ] **Step 4: Add the route**

In `routes/admin.php`, right after the `virtual-account.riwayat` route from Task 4:

```php
    Route::get('virtual-account/calon', [VirtualAccountController::class, 'calonGenerate'])->name('virtual-account.calon');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/Admin/VirtualAccountControllerTest.php`
Expected: PASS (13 tests total)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/VirtualAccountController.php routes/admin.php tests/Feature/Admin/VirtualAccountControllerTest.php
git commit -m "feat(admin): tambah endpoint calon siswa untuk generate VA manual"
```

---

## Task 6: `generate()` endpoint (bulk + manual modes)

**Files:**
- Modify: `app/Http/Controllers/Admin/VirtualAccountController.php` (add `generate()` method + imports)
- Modify: `routes/admin.php` (add route)
- Test: `tests/Feature/Admin/VirtualAccountControllerTest.php` (append)

**Interfaces:**
- Consumes: `PaymentService::getOrCreatePermanentVa(Siswa $siswa): BriVirtualAccount` (pre-existing, `app/Services/Finance/PaymentService.php:92-134` — throws `PaymentException` if the student has no wallet).
- Produces: `VirtualAccountController::generate(Request $request): RedirectResponse`, route name `admin.virtual-account.generate` (POST). Task 7's modal form submits here with `mode` (`'semua'`/`'manual'`) and `siswa_ids[]` (only when `mode=manual`).

### Context you need before starting

`PaymentService::getOrCreatePermanentVa()` is **idempotent by itself** — if a student already has a `WALLET_PERMANENT` VA, calling it again just returns the existing one instead of erroring (see `app/Services/Finance/PaymentService.php:99-109`). This task still filters candidates with `whereDoesntHave(...)` before looping, for two reasons: (1) it makes the flash summary count meaningful — "5 berhasil dibuat" should mean 5 *new* VAs, not 5 no-op calls — and (2) it matches the spec's stated idempotency requirement explicitly rather than relying on an implementation detail of a different service you didn't write in this task.

The try/catch is **per student, inside the loop** — a `PaymentException` (no wallet) or any other `\Throwable` for one student must not stop the loop for the rest.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Admin/VirtualAccountControllerTest.php`:

```php
it('generates VA for all active students without one when mode is semua', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    $siswaA = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Calon A', 'status' => 'aktif']);
    Wallet::factory()->create(['siswa_id' => $siswaA->id]);
    $siswaB = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Calon B', 'status' => 'aktif']);
    Wallet::factory()->create(['siswa_id' => $siswaB->id]);
    [$siswaSudahAda] = buatSiswaDenganVa($lembaga, 'Sudah Ada VA Generate');

    $response = $this->actingAs($user)->post(route('admin.virtual-account.generate'), ['mode' => 'semua']);

    $response->assertRedirect(route('admin.virtual-account.index'));
    expect(BriVirtualAccount::whereHas('wallet', fn ($q) => $q->where('siswa_id', $siswaA->id))->exists())->toBeTrue();
    expect(BriVirtualAccount::whereHas('wallet', fn ($q) => $q->where('siswa_id', $siswaB->id))->exists())->toBeTrue();
    expect(BriVirtualAccount::whereHas('wallet', fn ($q) => $q->where('siswa_id', $siswaSudahAda->id))->count())->toBe(1);
});

it('generates VA only for the selected students when mode is manual', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    $siswaDipilih = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'aktif']);
    Wallet::factory()->create(['siswa_id' => $siswaDipilih->id]);
    $siswaTidakDipilih = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'aktif']);
    Wallet::factory()->create(['siswa_id' => $siswaTidakDipilih->id]);

    $response = $this->actingAs($user)->post(route('admin.virtual-account.generate'), [
        'mode' => 'manual',
        'siswa_ids' => [$siswaDipilih->id],
    ]);

    $response->assertRedirect(route('admin.virtual-account.index'));
    expect(BriVirtualAccount::whereHas('wallet', fn ($q) => $q->where('siswa_id', $siswaDipilih->id))->exists())->toBeTrue();
    expect(BriVirtualAccount::whereHas('wallet', fn ($q) => $q->where('siswa_id', $siswaTidakDipilih->id))->exists())->toBeFalse();
});

it('does not let manual mode generate VA for a student in another lembaga', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    $siswaLain = Siswa::factory()->create(['lembaga_id' => $otherLembaga->id, 'status' => 'aktif']);
    Wallet::factory()->create(['siswa_id' => $siswaLain->id]);

    $this->actingAs($user)->post(route('admin.virtual-account.generate'), [
        'mode' => 'manual',
        'siswa_ids' => [$siswaLain->id],
    ]);

    expect(BriVirtualAccount::whereHas('wallet', fn ($q) => $q->where('siswa_id', $siswaLain->id))->exists())->toBeFalse();
});

it('does not fail the whole batch when one student has no wallet', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    $siswaTanpaWallet = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'aktif']);
    $siswaDenganWallet = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'aktif']);
    Wallet::factory()->create(['siswa_id' => $siswaDenganWallet->id]);

    $response = $this->actingAs($user)->post(route('admin.virtual-account.generate'), ['mode' => 'semua']);

    $response->assertRedirect(route('admin.virtual-account.index'));
    expect(BriVirtualAccount::whereHas('wallet', fn ($q) => $q->where('siswa_id', $siswaDenganWallet->id))->exists())->toBeTrue();
    expect(BriVirtualAccount::whereHas('wallet', fn ($q) => $q->where('siswa_id', $siswaTanpaWallet->id))->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/VirtualAccountControllerTest.php --filter=generate`
Expected: FAIL — route `admin.virtual-account.generate` not defined.

- [ ] **Step 3: Add the `generate()` method**

Add these imports to the top of `app/Http/Controllers/Admin/VirtualAccountController.php`:

```php
use App\Services\Finance\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
```

Add the method right after `calonGenerate()`:

```php
    public function generate(Request $request): RedirectResponse
    {
        $this->authorize('pembayaran.virtual-account');

        $data = $request->validate([
            'mode' => ['required', 'in:semua,manual'],
            'siswa_ids' => ['required_if:mode,manual', 'array'],
            'siswa_ids.*' => ['integer'],
        ]);

        $lembagaId = $this->lembagaId($request);

        $query = Siswa::where('lembaga_id', $lembagaId)
            ->where('status', StatusSiswa::Aktif->value)
            ->whereDoesntHave('wallet.briVirtualAccounts', fn ($q) => $q->where('va_type', 'WALLET_PERMANENT'));

        if ($data['mode'] === 'manual') {
            $query->whereIn('id', $data['siswa_ids'] ?? []);
        }

        $siswaList = $query->get();

        $berhasil = 0;
        $gagalNama = [];

        foreach ($siswaList as $siswa) {
            try {
                app(PaymentService::class)->getOrCreatePermanentVa($siswa);
                $berhasil++;
            } catch (\Throwable $e) {
                Log::error("Gagal generate VA untuk siswa id={$siswa->id}: ".$e->getMessage());
                $gagalNama[] = $siswa->nama_lengkap;
            }
        }

        $status = "{$berhasil} nomor VA berhasil dibuat.";
        if (count($gagalNama) > 0) {
            $status .= ' Gagal untuk: '.implode(', ', $gagalNama).'.';
        }

        return redirect()->route('admin.virtual-account.index')->with('status', $status);
    }
```

- [ ] **Step 4: Add the route**

In `routes/admin.php`, right after the `virtual-account.calon` route from Task 5:

```php
    Route::post('virtual-account/generate', [VirtualAccountController::class, 'generate'])->name('virtual-account.generate');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/Admin/VirtualAccountControllerTest.php`
Expected: PASS (17 tests total)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/VirtualAccountController.php routes/admin.php tests/Feature/Admin/VirtualAccountControllerTest.php
git commit -m "feat(admin): tambah endpoint generate VA massal dan manual"
```

---

## Task 7: "Generate VA" modal UI

**Files:**
- Modify: `resources/views/admin/virtual-account/index.blade.php` (add the "Generate VA" button and include the modal, pass `$kelasList` which already exists from Task 3)
- Create: `resources/views/admin/virtual-account/_generate-modal.blade.php`
- Create: `resources/js/virtual-account-generate-modal.js`
- Modify: `resources/js/app.js` (import + register)

**Interfaces:**
- Consumes: `admin.virtual-account.calon` (Task 5, returns JSON), `admin.virtual-account.generate` (Task 6, POST form target).
- Produces: nothing further tasks depend on — this is a leaf UI task.

### Context you need before starting

This task has no backend changes and no automated test (the endpoints it wires together are already tested in Tasks 5-6) — it's pure frontend wiring, verified manually in the browser per Step 5. Read the mockup requirements from the spec again before starting: a locked "Bank: BRI" dropdown (future-proofing, not functional), a two-option radio ("Semua Siswa Tanpa VA" default / "Pilih Manual"), and — only when "Pilih Manual" is active — a searchable, kelas-filterable, checkbox-driven table of candidates fetched from Task 5's JSON endpoint.

The form submits two things to `admin.virtual-account.generate`: a hidden `mode` input bound to Alpine state, and (only relevant when `mode=manual`) one hidden `siswa_ids[]` input per selected student, rendered via `x-for` over the `selectedIds` array. There is deliberately no `name` attribute on the radio `<input>`s themselves — the hidden `mode` input is the only thing actually submitted for that field, to avoid two same-named form fields fighting each other.

- [ ] **Step 1: Create the modal**

Create `resources/views/admin/virtual-account/_generate-modal.blade.php`:

```blade
<div
    x-data="virtualAccountGenerateModal({ calonUrlBase: @js(route('admin.virtual-account.calon')) })"
    x-on:open-generate-va-modal.window="buka()"
    x-show="open"
    style="display: none;"
    class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4"
>
    <div class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-elevated" @click.outside="open = false">
        <h3 class="font-display text-sm font-bold text-gray-900">Generate Virtual Account</h3>

        <form method="POST" action="{{ route('admin.virtual-account.generate') }}" class="mt-4 space-y-4">
            @csrf
            <input type="hidden" name="mode" :value="mode">
            <template x-for="id in selectedIds" :key="id">
                <input type="hidden" name="siswa_ids[]" :value="id">
            </template>

            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Bank <span class="text-error-500">*</span></label>
                <select disabled class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-500">
                    <option selected>BRI</option>
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Pilihan Generate <span class="text-error-500">*</span></label>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="cursor-pointer rounded-xl border p-4" :class="mode === 'semua' ? 'border-brand-500 bg-brand-50' : 'border-gray-200'" @click="mode = 'semua'">
                        <div class="flex items-start justify-between">
                            <span class="text-sm font-semibold" :class="mode === 'semua' ? 'text-brand-700' : 'text-gray-800'">Semua Siswa Tanpa VA</span>
                            <span class="mt-1 h-4 w-4 shrink-0 rounded-full border-2" :class="mode === 'semua' ? 'border-brand-600 bg-brand-600' : 'border-gray-300'"></span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Generate Virtual Account untuk seluruh siswa aktif yang belum memiliki nomor VA.</p>
                    </label>
                    <label class="cursor-pointer rounded-xl border p-4" :class="mode === 'manual' ? 'border-brand-500 bg-brand-50' : 'border-gray-200'" @click="mode = 'manual'; muatCalon()">
                        <div class="flex items-start justify-between">
                            <span class="text-sm font-semibold" :class="mode === 'manual' ? 'text-brand-700' : 'text-gray-800'">Pilih Manual</span>
                            <span class="mt-1 h-4 w-4 shrink-0 rounded-full border-2" :class="mode === 'manual' ? 'border-brand-600 bg-brand-600' : 'border-gray-300'"></span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Pilih satu atau beberapa siswa tertentu dari daftar untuk dibuatkan VA.</p>
                    </label>
                </div>
            </div>

            <div x-show="mode === 'manual'" class="space-y-3 rounded-xl border border-gray-100 bg-gray-50 p-4">
                <div class="flex flex-wrap items-center gap-3">
                    <input type="text" x-model="search" @input.debounce.400ms="muatCalon()" placeholder="Cari nama/NIS..." class="flex-1 rounded-lg border-gray-200 text-xs">
                    <select x-model="kelasId" @change="muatCalon()" class="rounded-lg border-gray-200 text-xs">
                        <option value="">Semua Kelas</option>
                        @foreach ($kelasList as $kelas)
                            <option value="{{ $kelas->id }}">{{ $kelas->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="max-h-64 overflow-y-auto rounded-lg bg-white border border-gray-100">
                    <table class="w-full text-left text-xs">
                        <tbody class="divide-y divide-gray-50">
                            <template x-for="siswa in calonList" :key="siswa.id">
                                <tr class="hover:bg-gray-50">
                                    <td class="w-8 px-3 py-2">
                                        <input type="checkbox" :checked="selectedIds.includes(siswa.id)" @change="toggleSiswa(siswa.id)">
                                    </td>
                                    <td class="px-3 py-2 font-medium text-gray-800" x-text="siswa.nama_lengkap"></td>
                                    <td class="px-3 py-2 text-gray-500" x-text="siswa.nis"></td>
                                    <td class="px-3 py-2 text-gray-500" x-text="siswa.kelas"></td>
                                </tr>
                            </template>
                            <tr x-show="calonList.length === 0">
                                <td colspan="4" class="px-3 py-6 text-center text-gray-400">Tidak ada siswa aktif tanpa VA.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-500"><span x-text="selectedIds.length"></span> siswa terpilih.</p>
            </div>

            <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                <button type="button" @click="open = false" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                <button type="submit" :disabled="mode === 'manual' && selectedIds.length === 0" class="rounded-lg bg-brand-600 px-4 py-2 text-xs font-semibold text-white hover:bg-brand-700 disabled:opacity-50">Simpan</button>
            </div>
        </form>
    </div>
</div>
```

- [ ] **Step 2: Create the Alpine component**

Create `resources/js/virtual-account-generate-modal.js`:

```js
export function virtualAccountGenerateModal(config) {
    return {
        open: false,
        mode: 'semua',
        search: '',
        kelasId: '',
        calonList: [],
        selectedIds: [],
        calonUrlBase: config.calonUrlBase,

        buka() {
            this.open = true;
            this.mode = 'semua';
            this.search = '';
            this.kelasId = '';
            this.calonList = [];
            this.selectedIds = [];
        },

        async muatCalon() {
            try {
                const url = new URL(this.calonUrlBase, window.location.origin);
                if (this.search) url.searchParams.set('search', this.search);
                if (this.kelasId) url.searchParams.set('kelas_id', this.kelasId);

                const response = await fetch(url, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!response.ok) {
                    Alpine.store('toast').push('error', 'Gagal memuat daftar siswa.');
                    return;
                }

                const json = await response.json();
                this.calonList = json.data;
                this.selectedIds = this.selectedIds.filter((id) => this.calonList.some((s) => s.id === id));
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memuat daftar siswa.');
            }
        },

        toggleSiswa(id) {
            if (this.selectedIds.includes(id)) {
                this.selectedIds = this.selectedIds.filter((x) => x !== id);
            } else {
                this.selectedIds.push(id);
            }
        },
    };
}
```

- [ ] **Step 3: Register the component in `app.js`**

Add the import (after the `virtualAccountFilter` import from Task 3):

```js
import { virtualAccountGenerateModal } from './virtual-account-generate-modal';
```

Add the registration (after `Alpine.data('virtualAccountFilter', virtualAccountFilter);` from Task 3):

```js
Alpine.data('virtualAccountGenerateModal', virtualAccountGenerateModal);
```

- [ ] **Step 4: Wire the button and include the modal in the index page**

In `resources/views/admin/virtual-account/index.blade.php`, inside the filter card's header row — the `<div class="mb-4 flex flex-wrap items-center justify-between gap-3">` block from Task 3 currently only has the "Filter Data" label. Add a button next to it:

```blade
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                        Filter Data
                    </p>
                    <button type="button" @click="$dispatch('open-generate-va-modal')" class="rounded-lg bg-brand-600 px-4 py-2 text-xs font-semibold text-white hover:bg-brand-700">Generate VA</button>
                </div>
```

And include the modal, right after the `@include('admin.virtual-account._riwayat-modal')` line added in Task 4:

```blade
        @include('admin.virtual-account._generate-modal')
```

- [ ] **Step 5: Build assets and verify manually in the browser**

Run: `npm run build`

Then, with the dev database seeded (e.g. `php artisan db:seed --class=KeuanganDemoSeeder` or any existing student data) and logged in as an `admin_keuangan` user:
1. Visit `/admin/virtual-account` — confirm the page loads, the table shows students who already have VA (if any), and "Belum ada siswa dengan nomor Virtual Account" shows if none do yet.
2. Click "Generate VA" — modal opens, "Semua Siswa Tanpa VA" is selected by default, "Bank: BRI" is visible but locked/disabled.
3. Click "Pilih Manual" — the candidate table loads via AJAX (check browser Network tab for a request to `/admin/virtual-account/calon`), showing active students without a VA.
4. Type into the search box — list filters after ~400ms debounce.
5. Select a kelas from the dropdown — list filters accordingly.
6. Check one or more checkboxes — the "X siswa terpilih" counter updates, and the "Simpan" button becomes enabled (it's disabled with 0 selected in manual mode).
7. Click "Simpan" — page redirects back to `/admin/virtual-account`, a green flash message shows "N nomor VA berhasil dibuat.", and the selected student(s) now appear in the main table.
8. Repeat with "Semua Siswa Tanpa VA" mode — confirm ALL remaining VA-less active students get one.

- [ ] **Step 6: Commit**

```bash
git add resources/views/admin/virtual-account/index.blade.php resources/views/admin/virtual-account/_generate-modal.blade.php resources/js/virtual-account-generate-modal.js resources/js/app.js
git commit -m "feat(admin): tambah modal generate VA (massal & manual)"
```

---

## Task 8: Excel export

**Files:**
- Create: `app/Exports/VirtualAccountExport.php`
- Modify: `app/Http/Controllers/Admin/VirtualAccountController.php` (add `export()` method + import)
- Modify: `routes/admin.php` (add route)
- Modify: `resources/views/admin/virtual-account/index.blade.php` (add "Export Excel" button)
- Test: `tests/Feature/Admin/VirtualAccountControllerTest.php` (append)

**Interfaces:**
- Consumes: nothing new.
- Produces: `admin.virtual-account.export` route (GET), downloads `virtual-account.xlsx`.

### Context you need before starting

`maatwebsite/excel` is already a dependency (used by `app/Exports/SiswaImportTemplateExport.php` and called via `Excel::download(...)` in `app/Http/Controllers/Admin/SiswaImportController.php:36`). This export uses `FromCollection` + `WithHeadings` (different from `SiswaImportTemplateExport`'s `FromArray`, because this export needs a real database query, not a static array).

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Admin/VirtualAccountControllerTest.php`:

```php
it('exports the virtual account list as an excel file', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukVirtualAccount();
    buatSiswaDenganVa($lembaga, 'Anak Export');

    $response = $this->actingAs($user)->get(route('admin.virtual-account.export'));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('denies export without pembayaran.virtual-account permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.virtual-account.export'))->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/VirtualAccountControllerTest.php --filter=export`
Expected: FAIL — route `admin.virtual-account.export` not defined.

- [ ] **Step 3: Create the export class**

Create `app/Exports/VirtualAccountExport.php`:

```php
<?php

namespace App\Exports;

use App\Models\BriVirtualAccount;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class VirtualAccountExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly ?int $lembagaId)
    {
    }

    public function headings(): array
    {
        return ['Nama Siswa', 'NIS', 'Kelas', 'Lembaga', 'Nomor VA', 'Tanggal Dibuat', 'Saldo Wallet'];
    }

    public function collection()
    {
        return BriVirtualAccount::where('va_type', 'WALLET_PERMANENT')
            ->whereHas('wallet.siswa', fn ($q) => $q->where('lembaga_id', $this->lembagaId))
            ->with(['wallet.siswa.kelas', 'wallet.siswa.lembaga'])
            ->get()
            ->map(function (BriVirtualAccount $va) {
                $siswa = $va->wallet->siswa;

                return [
                    $siswa->nama_lengkap,
                    $siswa->nis,
                    $siswa->kelas->nama ?? '-',
                    $siswa->lembaga->nama ?? '-',
                    $va->va_number,
                    $va->created_at->format('d-m-Y'),
                    number_format((float) $va->wallet->balance, 0, ',', '.'),
                ];
            });
    }
}
```

- [ ] **Step 4: Add the `export()` method**

Add these imports to the top of `app/Http/Controllers/Admin/VirtualAccountController.php`:

```php
use App\Exports\VirtualAccountExport;
use Maatwebsite\Excel\Facades\Excel;
```

Add the method right after `generate()`:

```php
    public function export(Request $request)
    {
        $this->authorize('pembayaran.virtual-account');

        return Excel::download(new VirtualAccountExport($this->lembagaId($request)), 'virtual-account.xlsx');
    }
```

- [ ] **Step 5: Add the route**

In `routes/admin.php`, right after the `virtual-account.generate` route from Task 6:

```php
    Route::get('virtual-account/export', [VirtualAccountController::class, 'export'])->name('virtual-account.export');
```

- [ ] **Step 6: Add the "Export Excel" button**

In `resources/views/admin/virtual-account/index.blade.php`, in the same header row where the "Generate VA" button was added in Task 7 — add the export link right before it:

```blade
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.virtual-account.export') }}" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50">Export Excel</a>
                        <button type="button" @click="$dispatch('open-generate-va-modal')" class="rounded-lg bg-brand-600 px-4 py-2 text-xs font-semibold text-white hover:bg-brand-700">Generate VA</button>
                    </div>
```

(This replaces the standalone `<button>` element from Task 7 Step 4 — wrap both in this `<div class="flex items-center gap-2">` container.)

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test tests/Feature/Admin/VirtualAccountControllerTest.php`
Expected: PASS (19 tests total)

- [ ] **Step 8: Commit**

```bash
git add app/Exports/VirtualAccountExport.php app/Http/Controllers/Admin/VirtualAccountController.php routes/admin.php resources/views/admin/virtual-account/index.blade.php tests/Feature/Admin/VirtualAccountControllerTest.php
git commit -m "feat(admin): tambah export Excel daftar Virtual Account"
```

---

## Task 9: Cross-tenant authorization test sweep + final manual verification

**Files:**
- Create: `tests/Feature/Admin/VirtualAccountAuthorizationTest.php`

**Interfaces:**
- Consumes: everything from Tasks 1-8. No production code changes in this task — it's a dedicated authorization/scope test file, separate from `VirtualAccountControllerTest.php`, mirroring the existing `ManualPaymentIndexAuthorizationTest.php` pattern (`tests/Feature/Admin/ManualPaymentIndexAuthorizationTest.php`) which keeps cross-tenant-isolation tests in their own file for clarity.

### Context you need before starting

Tasks 3-8 each included scope checks local to their own endpoint. This task adds one consolidated file that specifically stress-tests the cross-tenant boundary across ALL five endpoints together, using two full admin+lembaga setups (mirroring `tests/Feature/Admin/ManualPaymentIndexAuthorizationTest.php:18-43`'s `buatAdminDanRequestUntukLembaga()` helper pattern). This is the test file to extend first if a future cross-tenant bug is ever found in this controller — per this project's recurring-IDOR-bug history, treat this file as the authoritative regression net for this controller.

- [ ] **Step 1: Write the test file**

Create `tests/Feature/Admin/VirtualAccountAuthorizationTest.php`:

```php
<?php

use App\Models\BriVirtualAccount;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function buatAdminDanSiswaUntukVirtualAccount(string $label): array
{
    Permission::firstOrCreate(['name' => 'pembayaran.virtual-account', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_keuangan', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('pembayaran.virtual-account');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('admin_keuangan');

    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => "Anak {$label}", 'status' => 'aktif']);
    $wallet = Wallet::factory()->create(['siswa_id' => $siswa->id]);
    $va = BriVirtualAccount::create([
        'wallet_id' => $wallet->id,
        'va_type' => 'WALLET_PERMANENT',
        'va_number' => '8808'.str_pad((string) $siswa->id, 16, '0', STR_PAD_LEFT),
        'status' => 'PERMANENT',
    ]);

    return [$admin, $lembaga, $siswa, $va];
}

it('does not show another lembaga student in the index listing', function () {
    [$adminA, , $siswaA] = buatAdminDanSiswaUntukVirtualAccount('A');
    [, , $siswaB] = buatAdminDanSiswaUntukVirtualAccount('B');

    $response = $this->actingAs($adminA)->get(route('admin.virtual-account.index'));

    $response->assertOk();
    $response->assertSee($siswaA->nama_lengkap);
    $response->assertDontSee($siswaB->nama_lengkap);
});

it('blocks viewing riwayat for another lembaga student', function () {
    [$adminA] = buatAdminDanSiswaUntukVirtualAccount('A');
    [, , $siswaB] = buatAdminDanSiswaUntukVirtualAccount('B');

    $this->actingAs($adminA)->get(route('admin.virtual-account.riwayat', $siswaB))->assertNotFound();
});

it('does not include another lembaga student in calon-generate results', function () {
    [$adminA, $lembagaA] = buatAdminDanSiswaUntukVirtualAccount('A');
    $siswaCalonLembagaLain = Siswa::factory()->create([
        'lembaga_id' => Lembaga::factory()->create(['yayasan_id' => $lembagaA->yayasan_id])->id,
        'nama_lengkap' => 'Calon Lembaga Lain',
        'status' => 'aktif',
    ]);

    $response = $this->actingAs($adminA)->getJson(route('admin.virtual-account.calon'));

    $names = collect($response->json('data'))->pluck('nama_lengkap');
    expect($names)->not->toContain('Calon Lembaga Lain');
});

it('does not generate VA for another lembaga student even if their id is passed in manual mode', function () {
    [$adminA, $lembagaA] = buatAdminDanSiswaUntukVirtualAccount('A');
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $lembagaA->yayasan_id]);
    $siswaB = Siswa::factory()->create(['lembaga_id' => $lembagaB->id, 'status' => 'aktif']);
    Wallet::factory()->create(['siswa_id' => $siswaB->id]);

    $this->actingAs($adminA)->post(route('admin.virtual-account.generate'), [
        'mode' => 'manual',
        'siswa_ids' => [$siswaB->id],
    ]);

    expect(BriVirtualAccount::whereHas('wallet', fn ($q) => $q->where('siswa_id', $siswaB->id))->exists())->toBeFalse();
});

it('only exports the acting admin own lembaga students', function () {
    [$adminA, , $siswaA] = buatAdminDanSiswaUntukVirtualAccount('A');
    [, , $siswaB] = buatAdminDanSiswaUntukVirtualAccount('B');

    $response = $this->actingAs($adminA)->get(route('admin.virtual-account.export'));

    $response->assertOk();
    // Excel content is binary/zipped — assert via the underlying export class directly instead of parsing the response body.
    $export = new \App\Exports\VirtualAccountExport($adminA->lembaga_id);
    $rows = $export->collection();
    expect($rows->pluck(0))->toContain($siswaA->nama_lengkap);
    expect($rows->pluck(0))->not->toContain($siswaB->nama_lengkap);
});
```

- [ ] **Step 2: Run test to verify it passes**

Run: `php artisan test tests/Feature/Admin/VirtualAccountAuthorizationTest.php`
Expected: PASS (6 tests) — if any FAIL, it means a scope check from an earlier task was missed; go back to that task's `whereHas('wallet.siswa', ...)` / `siswaLembagaId()` check and fix it there, don't patch around it here.

- [ ] **Step 3: Run the full Virtual Account test suite together**

Run: `php artisan test tests/Feature/Admin/VirtualAccountControllerTest.php tests/Feature/Admin/VirtualAccountAuthorizationTest.php tests/Unit/Models/WalletBriVirtualAccountsRelationTest.php tests/Unit/PermissionSeederTest.php tests/Unit/RoleSeederTest.php tests/Feature/RolePermissionSeederTest.php`
Expected: all PASS (25+6 = should total roughly 32 tests across these files — exact count will confirm once run).

- [ ] **Step 4: Final manual browser verification checklist**

With a logged-in `admin_keuangan` user and existing seeded student data:
1. Sidebar: "Virtual Account" appears as the FIRST item in the Keuangan group, above "Jenis Tagihan".
2. Index page loads, search/kelas filters work without a full page reload (check Network tab shows XHR requests, not full navigation).
3. "Lihat Riwayat" modal opens and shows either payment history or the empty state correctly.
4. "Generate VA" modal: Bank field is visibly locked to BRI, both generate modes work end-to-end (re-verify Task 7 Step 5's checklist once more now that export/riwayat buttons are also present in the same header row).
5. "Export Excel" downloads a `.xlsx` file that opens correctly and contains the expected 7 columns.
6. Log in as a user from a DIFFERENT lembaga (or switch active lembaga via the yayasan-level switcher if using a yayasan-scoped account) and confirm the table, riwayat, and generate candidates are all correctly scoped to that different lembaga.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Admin/VirtualAccountAuthorizationTest.php
git commit -m "test(admin): tambah authorization sweep untuk Virtual Account"
```

---

## Self-Review Notes (for the plan author, not a task to execute)

- **Spec coverage:** index (Task 3), riwayat modal (Task 4), calon-generate + generate manual/massal (Tasks 5-6), Generate VA modal UI with locked Bank field and no status field (Task 7), export (Task 8), permission `pembayaran.virtual-account` (Task 1), Wallet relation (Task 2), sidebar position (Task 3 Step 9), cross-tenant scope (Task 9) — all spec sections have a corresponding task.
- **Idempotency:** covered by Task 6's `whereDoesntHave` pre-filter plus `PaymentService::getOrCreatePermanentVa()`'s own idempotent behavior (belt-and-suspenders, documented in Task 6's context section).
- **Error handling:** per-student try/catch in Task 6, explicitly tested by the "does not fail the whole batch when one student has no wallet" test.
- **Type/name consistency check:** `mode: 'semua'|'manual'` and `siswa_ids[]` are used identically in Task 6 (backend validation), Task 7 (frontend form fields), and Task 9 (tests) — no drift. `admin.virtual-account.*` route names are consistent across all tasks that reference them (index, riwayat, calon, generate, export).
