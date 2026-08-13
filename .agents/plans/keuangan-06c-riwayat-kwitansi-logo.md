# Keuangan Sub-project 6c: Riwayat Transaksi & Kwitansi PDF + Pengaturan Yayasan — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give parent users a transaction-history page with PDF kwitansi download, and give `yayasan_super_admin` a dedicated settings page to manage all `yayasan` table fields (including the logo used on kwitansi).

**Architecture:** Two new controllers — `App\Http\Controllers\Keuangan\RiwayatController` (parent-facing, reuses the `keuangan.*` route group/middleware from 6a/6b) and `App\Http\Controllers\Admin\YayasanSettingController` (admin-facing, single-record settings page). A shared `AuthorizesPembayaran` trait is extracted from `CheckoutController` (6b) so both `CheckoutController` and `RiwayatController` use the identical cross-tenant ownership check. Kwitansi PDFs are generated on-demand via `Pdf::loadView(...)->stream(...)` (no storage, no new DB columns) — the same pattern already used by `BuktiPendaftaranController`.

**Tech Stack:** Laravel 12, Eloquent, Blade + Alpine.js (view/edit-mode toggle, not tabs), `barryvdh/laravel-dompdf` (already installed), Pest.

## Global Constraints

- Guard: `web` only for both the parent-facing and admin-facing pieces.
- Parent-facing routes live inside the existing `keuangan.*` group in `routes/web.php` (`auth`, `verified`, `permission:keuangan.akses`, `resolve.active.siswa` already applied at the group level).
- Admin-facing routes live in `routes/admin.php`, inside the existing `admin` prefix/name group. Authorization uses `$this->authorize('yayasan.kelola')` inside each controller action — this is the established convention in this codebase (see `LembagaController`), not route-level `permission:` middleware.
- `Pembayaran` has no `TenantScope` global scope — query it directly by `siswa_id`, no `withoutGlobalScope` needed. `JenisTagihan` DOES have `TenantScope` (via `BelongsToTenant`) — any eager-load of `jenisTagihan` for an `orang_tua` acting user (whose `lembaga_id` is null) needs `->with(['jenisTagihan' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])`, exactly as already done in `CheckoutController`/`TagihanController`.
- `Pembayaran.status` enum is exactly `['menunggu_pembayaran', 'menunggu_verifikasi', 'lunas', 'ditolak']` (verified against `database/migrations/2026_08_11_200000_add_siswa_id_and_status_to_pembayaran_table.php` — the spec's mention of `gagal`/`dibatalkan` statuses was inaccurate and is corrected here; use `ditolak` instead). `Pembayaran.metode` enum is `['transfer_manual', 'va_bri', 'cash', 'qris', 'wallet_auto', 'wallet_saldo']`.
- No new database migration needed anywhere in this plan — `yayasan` table already has every field the spec requires, `pembayaran` already has the indexes needed for a `where('siswa_id', ...)` lookup pattern (siswa_id is a foreign key with an implicit index).
- Do not modify `PaymentService`, `PaymentAllocationService`, `AutoAllocationEngine`, `Wallet`, or any of `CheckoutController`'s existing POST-handling methods (`va`, `qris`, `wallet`, `transfer`) — Task 2's refactor moves `authorizePembayaran()` only, with zero behavior change.
- Kwitansi PDF: stream on-demand (`->stream()`), never persisted to storage, no new `file_path`-style column.
- Cross-tenant/IDOR: every new action loading a `Pembayaran` by route id must verify it belongs to a child of `Auth::user()->orangTua` — reuse the exact same check already proven in 6b, do not write a second implementation of it.

---

### Task 1: `yayasan.kelola` permission

**Files:**
- Modify: `database/seeders/PermissionSeeder.php`
- Test: `tests/Unit/PermissionSeederTest.php`

**Interfaces:**
- Produces: permission `yayasan.kelola`, automatically granted to `yayasan_super_admin` (that role syncs ALL permissions in `RoleSeeder.php` line ~33 — `$role->syncPermissions(Permission::pluck('name')->all())` — so no explicit grant is needed in `RoleSeeder.php` for this task).

- [ ] **Step 1: Write the failing test**

Open `tests/Unit/PermissionSeederTest.php`. Find the existing assertion that checks the total permission count (search for `Permission::count()`) and note its current expected number `N`. Add a new assertion right after it:

```php
    it('includes the yayasan.kelola permission', function () {
        (new \Database\Seeders\PermissionSeeder())->run();

        expect(\Spatie\Permission\Models\Permission::where('name', 'yayasan.kelola')->exists())->toBeTrue();
    });
```

Also update every hardcoded `Permission::count()->toBe(N)` assertion in this file to `N + 1` (there is precedent for this exact kind of bump in Sub-project 6a's Task 1 — search this same file and `tests/Unit/RoleSeederTest.php` and `tests/Feature/RolePermissionSeederTest.php` for other hardcoded counts that will also need `+1`).

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/PermissionSeederTest.php`
Expected: FAIL — `yayasan.kelola` does not exist yet, and/or the count assertions are off by one.

- [ ] **Step 3: Add the permission**

In `database/seeders/PermissionSeeder.php`, find the line containing `'keuangan.akses',` and add the new permission on its own line immediately after it:

```php
            'keuangan.akses',
            'yayasan.kelola',
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Unit/PermissionSeederTest.php tests/Unit/RoleSeederTest.php tests/Feature/RolePermissionSeederTest.php`
Expected: PASS — including the bumped `count()` assertions.

- [ ] **Step 5: Commit**

```bash
git add database/seeders/PermissionSeeder.php tests/Unit/PermissionSeederTest.php tests/Unit/RoleSeederTest.php tests/Feature/RolePermissionSeederTest.php
git commit -m "feat(keuangan): add yayasan.kelola permission for admin logo/profile management"
```

---

### Task 2: Extract `AuthorizesPembayaran` trait from `CheckoutController`

**Files:**
- Create: `app/Http/Controllers/Keuangan/Concerns/AuthorizesPembayaran.php`
- Modify: `app/Http/Controllers/Keuangan/CheckoutController.php`

**Interfaces:**
- Produces: trait `App\Http\Controllers\Keuangan\Concerns\AuthorizesPembayaran` with method `authorizePembayaran(Pembayaran $pembayaran): void` — identical body/behavior to the private method currently in `CheckoutController`. Task 3/4's `RiwayatController` will `use` this same trait.
- Consumes: nothing new — this is a pure code-move, zero behavior change. All of `CheckoutController`'s existing tests (`CheckoutControllerVaQrisTest`, `CheckoutControllerWalletTest`, `CheckoutControllerTransferTest`, `CheckoutAuthorizationTest`) are the safety net proving the move didn't change anything.

- [ ] **Step 1: Create the trait**

Create `app/Http/Controllers/Keuangan/Concerns/AuthorizesPembayaran.php`:

```php
<?php
// app/Http/Controllers/Keuangan/Concerns/AuthorizesPembayaran.php

namespace App\Http\Controllers\Keuangan\Concerns;

use App\Models\Pembayaran;
use App\Models\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;

trait AuthorizesPembayaran
{
    private function authorizePembayaran(Pembayaran $pembayaran): void
    {
        $orangTua = Auth::user()->orangTua;
        $ownsChild = $orangTua !== null
            && $orangTua->siswa()->withoutGlobalScope(TenantScope::class)->whereKey($pembayaran->siswa_id)->exists();

        abort_unless($ownsChild, 403);
    }
}
```

- [ ] **Step 2: Update `CheckoutController` to use the trait**

In `app/Http/Controllers/Keuangan/CheckoutController.php`:

1. Add the import: `use App\Http\Controllers\Keuangan\Concerns\AuthorizesPembayaran;`
2. Add `use AuthorizesPembayaran;` as the first line inside the `class CheckoutController extends BaseController { ... }` body (right after the opening brace, before the constructor).
3. Delete the entire private `authorizePembayaran()` method (currently the last method in the file, right after `findPendingPembayaranFor()`).

The class should now call `$this->authorizePembayaran($pembayaran)` exactly as before (4 call sites: `menungguVerifikasi`, `sukses`, `show`, `status`) — those call sites do not change, only the method's origin changes from "defined in this class" to "defined in the trait."

- [ ] **Step 3: Run the full existing CheckoutController test suite to confirm zero behavior change**

Run: `php artisan test tests/Feature/Keuangan/CheckoutControllerVaQrisTest.php tests/Feature/Keuangan/CheckoutControllerWalletTest.php tests/Feature/Keuangan/CheckoutControllerTransferTest.php tests/Feature/Keuangan/CheckoutAuthorizationTest.php`
Expected: PASS — same pass count as before this task (no new tests added in this task; this run proves the refactor is behavior-preserving).

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Keuangan/Concerns/AuthorizesPembayaran.php app/Http/Controllers/Keuangan/CheckoutController.php
git commit -m "refactor(keuangan): extract authorizePembayaran into a shared trait for reuse by RiwayatController"
```

---

### Task 3: Riwayat Transaksi page

**Files:**
- Create: `app/Http/Controllers/Keuangan/RiwayatController.php`
- Create: `resources/views/keuangan/riwayat/index.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/sidebar.blade.php`
- Test: `tests/Feature/Keuangan/RiwayatControllerIndexTest.php`

**Interfaces:**
- Consumes: `$request->attributes->get('activeSiswa')` (existing, set by `ResolveActiveSiswa`), `Pembayaran` model (existing).
- Produces: route `keuangan.riwayat.index` (`GET /keuangan/riwayat`), controller method `RiwayatController::index(Request $request): View`. Task 4 (`kwitansi()`) will be added to this same controller/class.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Keuangan/RiwayatControllerIndexTest.php`:

```php
<?php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Pembayaran;
use App\Models\PembayaranTagihan;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAsOrangTuaForRiwayat(): array
{
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Riwayat',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200009999',
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    return [$user, $orangTua, $siswa];
}

function makeLunasPembayaran(Siswa $siswa, string $metode = 'wallet_saldo', ?\Carbon\Carbon $createdAt = null): Pembayaran
{
    $jenis = JenisTagihan::factory()->create(['nama' => 'SPP Bulanan']);
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'lunas', 'net_amount' => 100000, 'paid_amount' => 100000,
    ]);

    $pembayaran = Pembayaran::create([
        'siswa_id' => $siswa->id, 'metode' => $metode, 'status' => 'lunas',
        'channel_reference' => (string) \Illuminate\Support\Str::uuid(),
    ]);
    if ($createdAt !== null) {
        $pembayaran->created_at = $createdAt;
        $pembayaran->save();
    }

    PembayaranTagihan::create([
        'pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihan->id, 'amount_allocated' => 100000,
    ]);

    return $pembayaran;
}

it('lists the active child payment history ordered newest first', function () {
    [$user, , $siswa] = actingAsOrangTuaForRiwayat();

    $older = makeLunasPembayaran($siswa, createdAt: now()->subDays(5));
    $newer = makeLunasPembayaran($siswa, createdAt: now());

    $response = $this->actingAs($user)->get(route('keuangan.riwayat.index'));

    $response->assertOk();
    $response->assertViewHas('pembayarans', function ($pembayarans) use ($older, $newer) {
        return $pembayarans->pluck('id')->all() === [$newer->id, $older->id];
    });
});

it('filters by metode', function () {
    [$user, , $siswa] = actingAsOrangTuaForRiwayat();

    $wallet = makeLunasPembayaran($siswa, metode: 'wallet_saldo');
    $va = makeLunasPembayaran($siswa, metode: 'va_bri');

    $response = $this->actingAs($user)->get(route('keuangan.riwayat.index', ['metode' => 'wallet_saldo']));

    $response->assertOk();
    $response->assertViewHas('pembayarans', function ($pembayarans) use ($wallet, $va) {
        return $pembayarans->pluck('id')->all() === [$wallet->id] && ! $pembayarans->contains('id', $va->id);
    });
});

it('ignores an invalid date range instead of erroring', function () {
    [$user, , $siswa] = actingAsOrangTuaForRiwayat();
    $pembayaran = makeLunasPembayaran($siswa);

    $response = $this->actingAs($user)->get(route('keuangan.riwayat.index', [
        'dari' => now()->toDateString(),
        'sampai' => now()->subDays(10)->toDateString(),
    ]));

    $response->assertOk();
    $response->assertViewHas('pembayarans', fn ($pembayarans) => $pembayarans->contains('id', $pembayaran->id));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/RiwayatControllerIndexTest.php`
Expected: FAIL — route `keuangan.riwayat.index` not defined.

- [ ] **Step 3: Add the route**

In `routes/web.php`, extend the existing `keuangan.*` group (add after the `tagihan.index` route):

```php
        Route::get('/riwayat', [\App\Http\Controllers\Keuangan\RiwayatController::class, 'index'])->name('riwayat.index');
```

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/Keuangan/RiwayatController.php`:

```php
<?php
// app/Http/Controllers/Keuangan/RiwayatController.php

namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Keuangan\Concerns\AuthorizesPembayaran;
use App\Models\Pembayaran;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class RiwayatController extends BaseController
{
    use AuthorizesPembayaran;

    public function index(Request $request): View
    {
        $activeSiswa = $request->attributes->get('activeSiswa');

        if ($activeSiswa === null) {
            return view('keuangan.tanpa-anak');
        }

        $dari = $request->query('dari');
        $sampai = $request->query('sampai');
        $metode = $request->query('metode');

        $dateRangeValid = $dari && $sampai && $dari <= $sampai;

        $pembayarans = Pembayaran::where('siswa_id', $activeSiswa->id)
            ->when($dateRangeValid, fn ($q) => $q->whereBetween('created_at', [$dari.' 00:00:00', $sampai.' 23:59:59']))
            ->when($metode, fn ($q) => $q->where('metode', $metode))
            ->with(['pembayaranTagihan.tagihan.jenisTagihan' => fn ($q) => $q->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)])
            ->orderByDesc('created_at')
            ->paginate(15)
            ->appends($request->query());

        return view('keuangan.riwayat.index', [
            'activeSiswa' => $activeSiswa,
            'pembayarans' => $pembayarans,
            'dari' => $dari,
            'sampai' => $sampai,
            'metode' => $metode,
        ]);
    }
}
```

- [ ] **Step 5: Create the view**

Create `resources/views/keuangan/riwayat/index.blade.php`:

```blade
{{-- resources/views/keuangan/riwayat/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-xl font-bold text-gray-900">Riwayat Transaksi — {{ $activeSiswa->nama_lengkap }}</h2>
    </x-slot>

    <div class="space-y-6">
        <form method="GET" action="{{ route('keuangan.riwayat.index') }}" class="flex flex-wrap items-end gap-4 rounded-2xl border border-gray-200 bg-white p-4">
            <div>
                <label class="text-xs font-semibold text-gray-500">Dari Tanggal</label>
                <input type="date" name="dari" value="{{ $dari }}" class="mt-1 rounded-xl border-gray-300 text-sm">
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500">Sampai Tanggal</label>
                <input type="date" name="sampai" value="{{ $sampai }}" class="mt-1 rounded-xl border-gray-300 text-sm">
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500">Metode</label>
                <select name="metode" class="mt-1 rounded-xl border-gray-300 text-sm">
                    <option value="">Semua Metode</option>
                    @foreach (['va_bri' => 'VA BRI', 'qris' => 'QRIS', 'wallet_saldo' => 'Saldo Wallet', 'wallet_auto' => 'Auto-Debit Wallet', 'transfer_manual' => 'Transfer Manual', 'cash' => 'Tunai'] as $value => $label)
                        <option value="{{ $value }}" @selected($metode === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white">
                Terapkan Filter
            </button>
            @if ($dari || $sampai || $metode)
                <a href="{{ route('keuangan.riwayat.index') }}" class="text-sm font-semibold text-gray-500 hover:text-gray-700">Reset</a>
            @endif
        </form>

        <div class="rounded-2xl border border-gray-200 bg-white p-6">
            @if ($pembayarans->isEmpty())
                <p class="text-sm text-gray-500">
                    @if ($dari || $sampai || $metode)
                        Tidak ada transaksi yang cocok dengan filter ini.
                    @else
                        Belum ada riwayat transaksi.
                    @endif
                </p>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($pembayarans as $pembayaran)
                        @php
                            $metodeLabel = match ($pembayaran->metode) {
                                'va_bri' => 'VA BRI',
                                'qris' => 'QRIS',
                                'wallet_saldo' => 'Saldo Wallet',
                                'wallet_auto' => 'Auto-Debit Wallet',
                                'transfer_manual' => 'Transfer Manual',
                                'cash' => 'Tunai',
                                default => $pembayaran->metode,
                            };
                            $statusBadge = match ($pembayaran->status) {
                                'lunas' => ['bg-emerald-50 text-emerald-700 border-emerald-200', 'Lunas'],
                                'menunggu_pembayaran' => ['bg-amber-50 text-amber-700 border-amber-200', 'Menunggu Pembayaran'],
                                'menunggu_verifikasi' => ['bg-amber-50 text-amber-700 border-amber-200', 'Menunggu Verifikasi'],
                                'ditolak' => ['bg-rose-50 text-rose-700 border-rose-200', 'Ditolak'],
                                default => ['bg-gray-50 text-gray-600 border-gray-200', $pembayaran->status],
                            };
                            $rincianItems = $pembayaran->pembayaranTagihan;
                            $rincianLabel = $rincianItems->isEmpty()
                                ? '-'
                                : $rincianItems->first()->tagihan->jenisTagihan->nama.($rincianItems->count() > 1 ? ' +'.($rincianItems->count() - 1).' lainnya' : '');
                        @endphp
                        <div class="flex flex-wrap items-center justify-between gap-3 py-4">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $rincianLabel }}</p>
                                <p class="text-xs text-gray-500">{{ $pembayaran->created_at->translatedFormat('d M Y H:i') }} &middot; {{ $metodeLabel }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $statusBadge[0] }}">{{ $statusBadge[1] }}</span>
                                @if ($pembayaran->status === 'lunas')
                                    {{-- literal url() here, not route(): the named route 'keuangan.riwayat.kwitansi'
                                         is only registered in Task 4, which runs after this task — route() would
                                         throw RouteNotFoundException at page-RENDER time (this exact situation
                                         and fix already happened once in Sub-project 6b's Task 3/4 split; Task 4
                                         of this plan switches this back to route() once the name exists). --}}
                                    <a href="{{ url('/keuangan/riwayat/'.$pembayaran->id.'/kwitansi') }}" target="_blank" class="text-sm font-semibold text-brand-600 hover:text-brand-700">
                                        Unduh Kwitansi
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">
                    {{ $pembayarans->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Add sidebar nav link**

In `resources/views/layouts/sidebar.blade.php`, find the `'Keuangan'` group's `items` array (search for `'keuangan.tagihan.index'`) and add a new entry right after it:

```php
                Auth::user()->can('keuangan.akses') && Auth::user()->orangTua !== null ? ['route' => 'keuangan.riwayat.index', 'pattern' => 'keuangan.riwayat.*', 'label' => 'Riwayat', 'icon' => 'history'] : null,
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Keuangan/RiwayatControllerIndexTest.php`
Expected: PASS (3 tests). The view's kwitansi link intentionally uses a literal `url()` (see the comment in Step 5's Blade code) rather than `route('keuangan.riwayat.kwitansi', ...)`, precisely so this task's tests — which do render `lunas` rows — don't require that named route to exist yet. Task 4 registers the named route and switches this link back to `route()`.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Keuangan/RiwayatController.php resources/views/keuangan/riwayat/index.blade.php routes/web.php resources/views/layouts/sidebar.blade.php tests/Feature/Keuangan/RiwayatControllerIndexTest.php
git commit -m "feat(keuangan): add riwayat transaksi page with date/metode filters"
```

---

### Task 4: Kwitansi PDF download

**Files:**
- Modify: `app/Http/Controllers/Keuangan/RiwayatController.php`
- Create: `resources/views/pdf/kwitansi.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Keuangan/KwitansiControllerTest.php`

**Interfaces:**
- Consumes: `AuthorizesPembayaran::authorizePembayaran()` (Task 2), `Pdf::loadView()` (existing package, see `app/Http/Controllers/Portal/BuktiPendaftaranController.php` for the exact usage pattern).
- Produces: route `keuangan.riwayat.kwitansi` (`GET /keuangan/riwayat/{pembayaran}/kwitansi`), controller method `RiwayatController::kwitansi(Request $request, Pembayaran $pembayaran)`.
- Also switches `resources/views/keuangan/riwayat/index.blade.php`'s kwitansi link from a literal `url()` (Task 3's temporary form) to `route('keuangan.riwayat.kwitansi', $pembayaran)`, now that the named route exists.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Keuangan/KwitansiControllerTest.php`:

```php
<?php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Pembayaran;
use App\Models\PembayaranTagihan;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAsOrangTuaForKwitansi(): array
{
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create(['nama' => 'Yayasan Uji Kwitansi']);
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Anak Kwitansi']);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Kwitansi',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200001111',
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $jenis = JenisTagihan::factory()->create(['nama' => 'SPP Bulanan']);
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'lunas', 'net_amount' => 100000, 'paid_amount' => 100000,
    ]);

    $pembayaran = Pembayaran::create([
        'siswa_id' => $siswa->id, 'metode' => 'wallet_saldo', 'status' => 'lunas',
        'channel_reference' => (string) \Illuminate\Support\Str::uuid(),
    ]);
    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihan->id, 'amount_allocated' => 100000]);

    return [$user, $orangTua, $siswa, $pembayaran];
}

it('streams a pdf kwitansi for a lunas pembayaran', function () {
    [$user, , , $pembayaran] = actingAsOrangTuaForKwitansi();

    $response = $this->actingAs($user)->get(route('keuangan.riwayat.kwitansi', $pembayaran));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

it('returns 404 for a pembayaran that is not yet lunas', function () {
    [$user, , $siswa] = actingAsOrangTuaForKwitansi();
    $pending = Pembayaran::create([
        'siswa_id' => $siswa->id, 'metode' => 'va_bri', 'status' => 'menunggu_pembayaran',
        'channel_reference' => (string) \Illuminate\Support\Str::uuid(),
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.riwayat.kwitansi', $pending));

    $response->assertNotFound();
});

it('blocks a parent from downloading another parent child\'s kwitansi', function () {
    [, , , $pembayaranA] = actingAsOrangTuaForKwitansi();
    [$userB] = actingAsOrangTuaForKwitansi();

    $response = $this->actingAs($userB)->get(route('keuangan.riwayat.kwitansi', $pembayaranA));

    $response->assertForbidden();
});

it('renders without a logo when yayasan logo is not set', function () {
    [$user, , , $pembayaran] = actingAsOrangTuaForKwitansi();

    $response = $this->actingAs($user)->get(route('keuangan.riwayat.kwitansi', $pembayaran));

    $response->assertOk();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/KwitansiControllerTest.php`
Expected: FAIL — route `keuangan.riwayat.kwitansi` not defined.

- [ ] **Step 3: Add the route**

In `routes/web.php`, extend the `keuangan.*` group:

```php
        Route::get('/riwayat/{pembayaran}/kwitansi', [\App\Http\Controllers\Keuangan\RiwayatController::class, 'kwitansi'])->name('riwayat.kwitansi');
```

- [ ] **Step 4: Implement the controller method**

In `app/Http/Controllers/Keuangan/RiwayatController.php`, add these imports:

```php
use App\Models\Pembayaran;
use Barryvdh\DomPDF\Facade\Pdf;
```

(`App\Models\Pembayaran` is likely already imported from Task 3 — don't duplicate the `use` line if so.)

Add the method:

```php
    public function kwitansi(Request $request, Pembayaran $pembayaran)
    {
        $this->authorizePembayaran($pembayaran);

        abort_unless($pembayaran->status === 'lunas', 404);

        $pembayaran->load(['pembayaranTagihan.tagihan.jenisTagihan' => fn ($q) => $q->withoutGlobalScope(\App\Models\Scopes\TenantScope::class), 'siswa.lembaga.yayasan', 'siswa.kelas']);

        $pdf = Pdf::loadView('pdf.kwitansi', [
            'pembayaran' => $pembayaran,
            'siswa' => $pembayaran->siswa,
            'lembaga' => $pembayaran->siswa->lembaga,
            'yayasan' => $pembayaran->siswa->lembaga->yayasan,
        ]);

        return $pdf->stream('kwitansi-'.$pembayaran->id.'.pdf');
    }
```

- [ ] **Step 5: Create the PDF template**

Create `resources/views/pdf/kwitansi.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1F2937; }
        h1 { font-size: 16px; margin-bottom: 2px; }
        .header { display: flex; align-items: center; gap: 12px; border-bottom: 2px solid #1F2937; padding-bottom: 12px; margin-bottom: 16px; }
        .header img { height: 48px; }
        .header .lembaga-info p { margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        td { padding: 6px 0; }
        td.label { color: #5B6478; width: 40%; }
        table.rincian { border: 1px solid #E5E7EB; margin-top: 16px; }
        table.rincian th, table.rincian td { border: 1px solid #E5E7EB; padding: 8px; text-align: left; }
        table.rincian th { background: #F9FAFB; }
        .total-row td { font-weight: bold; }
        .footer { margin-top: 48px; text-align: right; font-size: 11px; color: #5B6478; }
    </style>
</head>
<body>
    <div class="header">
        @if ($yayasan?->logo)
            <img src="{{ public_path('storage/'.$yayasan->logo) }}" alt="Logo">
        @endif
        <div class="lembaga-info">
            <h1>{{ $lembaga->nama }}</h1>
            <p>{{ $lembaga->alamat ?? '-' }}</p>
        </div>
    </div>

    <h2 style="text-align: center;">KWITANSI PEMBAYARAN</h2>
    <p style="text-align: center;">No. KW-{{ $pembayaran->id }}</p>

    <table>
        <tr><td class="label">Tanggal Pembayaran</td><td>{{ $pembayaran->created_at->translatedFormat('d F Y H:i') }}</td></tr>
        <tr><td class="label">Nama Siswa</td><td>{{ $siswa->nama_lengkap }}</td></tr>
        <tr><td class="label">NIS / NISN</td><td>{{ $siswa->nis ?? '-' }} / {{ $siswa->nisn ?? '-' }}</td></tr>
        <tr><td class="label">Kelas</td><td>{{ $siswa->kelas?->nama ?? '-' }}</td></tr>
        <tr><td class="label">Metode Pembayaran</td><td>{{ match ($pembayaran->metode) {
            'va_bri' => 'VA BRI', 'qris' => 'QRIS', 'wallet_saldo' => 'Saldo Wallet',
            'wallet_auto' => 'Auto-Debit Wallet', 'transfer_manual' => 'Transfer Manual', 'cash' => 'Tunai',
            default => $pembayaran->metode,
        } }}</td></tr>
    </table>

    <table class="rincian">
        <thead>
            <tr><th>Rincian Tagihan</th><th style="text-align: right;">Nominal</th></tr>
        </thead>
        <tbody>
            @forelse ($pembayaran->pembayaranTagihan as $item)
                <tr>
                    <td>{{ $item->tagihan->jenisTagihan->nama }}</td>
                    <td style="text-align: right;">Rp{{ number_format($item->amount_allocated, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td>Rincian tidak tersedia</td><td style="text-align: right;">Rp{{ number_format($pembayaran->amount ?? 0, 0, ',', '.') }}</td></tr>
            @endforelse
            <tr class="total-row">
                <td>Total</td>
                <td style="text-align: right;">Rp{{ number_format($pembayaran->pembayaranTagihan->sum('amount_allocated') ?: ($pembayaran->amount ?? 0), 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <p>Dokumen ini dicetak otomatis oleh sistem dan sah tanpa tanda tangan basah.</p>
        <p>Administrasi Keuangan — {{ $lembaga->nama }}</p>
    </div>
</body>
</html>
```

- [ ] **Step 6: Switch the riwayat list's kwitansi link from `url()` to `route()`**

In `resources/views/keuangan/riwayat/index.blade.php` (created in Task 3), replace the temporary literal-URL link with the real named route now that it exists:

Replace:
```blade
                                    {{-- literal url() here, not route(): the named route 'keuangan.riwayat.kwitansi'
                                         is only registered in Task 4, which runs after this task — route() would
                                         throw RouteNotFoundException at page-RENDER time (this exact situation
                                         and fix already happened once in Sub-project 6b's Task 3/4 split; Task 4
                                         of this plan switches this back to route() once the name exists). --}}
                                    <a href="{{ url('/keuangan/riwayat/'.$pembayaran->id.'/kwitansi') }}" target="_blank" class="text-sm font-semibold text-brand-600 hover:text-brand-700">
                                        Unduh Kwitansi
                                    </a>
```
With:
```blade
                                    <a href="{{ route('keuangan.riwayat.kwitansi', $pembayaran) }}" target="_blank" class="text-sm font-semibold text-brand-600 hover:text-brand-700">
                                        Unduh Kwitansi
                                    </a>
```

- [ ] **Step 7: Add a test proving the link only appears for lunas rows**

Append this test to `tests/Feature/Keuangan/RiwayatControllerIndexTest.php` (the file created in Task 3 — this test needs the now-registered `keuangan.riwayat.kwitansi` route, which is why it lives here instead of Task 3):

```php
it('shows the kwitansi download link only for lunas rows', function () {
    [$user, , $siswa] = actingAsOrangTuaForRiwayat();

    $lunas = makeLunasPembayaran($siswa);
    $pending = Pembayaran::create([
        'siswa_id' => $siswa->id, 'metode' => 'va_bri', 'status' => 'menunggu_pembayaran',
        'channel_reference' => (string) \Illuminate\Support\Str::uuid(),
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.riwayat.index'));

    $response->assertOk();
    $response->assertSee(route('keuangan.riwayat.kwitansi', $lunas));
    $response->assertDontSee(route('keuangan.riwayat.kwitansi', $pending));
});
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Keuangan/KwitansiControllerTest.php tests/Feature/Keuangan/RiwayatControllerIndexTest.php`
Expected: PASS (4 + 4 = 8 tests)

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Keuangan/RiwayatController.php resources/views/pdf/kwitansi.blade.php resources/views/keuangan/riwayat/index.blade.php routes/web.php tests/Feature/Keuangan/KwitansiControllerTest.php tests/Feature/Keuangan/RiwayatControllerIndexTest.php
git commit -m "feat(keuangan): add on-demand kwitansi PDF download"
```

---

### Task 5: Admin Pengaturan Yayasan page

**Files:**
- Create: `app/Http/Controllers/Admin/YayasanSettingController.php`
- Create: `resources/views/admin/yayasan/edit.blade.php`
- Modify: `routes/admin.php`
- Modify: `resources/views/layouts/sidebar.blade.php`
- Test: `tests/Feature/Admin/YayasanSettingControllerTest.php`

**Interfaces:**
- Consumes: `Yayasan` model (existing, all fields already in `$fillable`), `$this->authorize('yayasan.kelola')` (Laravel's built-in `AuthorizesRequests` trait, already available on `Illuminate\Routing\Controller`).
- Produces: routes `admin.yayasan.edit` (`GET /admin/pengaturan-yayasan`), `admin.yayasan.update` (`PUT /admin/pengaturan-yayasan`).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Admin/YayasanSettingControllerTest.php`:

```php
<?php

use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAsYayasanSuperAdmin(): array
{
    Permission::firstOrCreate(['name' => 'yayasan.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo('yayasan.kelola');

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('yayasan_super_admin');

    return [$user];
}

it('shows the yayasan settings form with existing data', function () {
    [$user] = actingAsYayasanSuperAdmin();
    $yayasan = Yayasan::factory()->create(['nama' => 'Yayasan Permata Kraksaan']);

    $response = $this->actingAs($user)->get(route('admin.yayasan.edit'));

    $response->assertOk();
    $response->assertSee('Yayasan Permata Kraksaan');
});

it('updates all yayasan fields', function () {
    [$user] = actingAsYayasanSuperAdmin();
    Yayasan::factory()->create();

    $response = $this->actingAs($user)->put(route('admin.yayasan.update'), [
        'nama' => 'Yayasan Baru',
        'npwp_yayasan' => '12.345.678.9-012.000',
        'akta_pendirian_nomor' => 'AKT-001',
        'akta_pendirian_tanggal' => '2020-01-15',
        'sk_kemenkumham_nomor' => 'SK-002',
        'alamat' => 'Jl. Contoh No. 1',
        'telepon' => '0331123456',
        'email' => 'yayasan@example.test',
        'website' => 'https://example.test',
        'nama_ketua_pembina' => 'Budi Santoso',
        'nama_ketua_pengurus' => 'Siti Aminah',
    ]);

    $response->assertRedirect(route('admin.yayasan.edit'));
    $this->assertDatabaseHas('yayasan', ['nama' => 'Yayasan Baru', 'nama_ketua_pembina' => 'Budi Santoso']);
});

it('uploads a new logo and deletes the old one', function () {
    Storage::fake('public');
    [$user] = actingAsYayasanSuperAdmin();
    $oldPath = 'yayasan-logo/old.png';
    Storage::disk('public')->put($oldPath, 'dummy-old-content');
    $yayasan = Yayasan::factory()->create(['logo' => $oldPath]);

    $response = $this->actingAs($user)->put(route('admin.yayasan.update'), [
        'nama' => $yayasan->nama,
        'logo' => UploadedFile::fake()->image('logo-baru.png'),
    ]);

    $response->assertRedirect(route('admin.yayasan.edit'));
    $yayasan->refresh();
    Storage::disk('public')->assertExists($yayasan->logo);
    Storage::disk('public')->assertMissing($oldPath);
    expect($yayasan->logo)->not->toBe($oldPath);
});

it('rejects a logo file that is too large', function () {
    Storage::fake('public');
    [$user] = actingAsYayasanSuperAdmin();
    Yayasan::factory()->create();

    $response = $this->actingAs($user)->put(route('admin.yayasan.update'), [
        'nama' => 'Yayasan X',
        'logo' => UploadedFile::fake()->create('logo.png', 2000, 'image/png'),
    ]);

    $response->assertSessionHasErrors('logo');
});

it('denies access to a user without yayasan.kelola permission', function () {
    $user = User::factory()->create(['lembaga_id' => null]);

    $response = $this->actingAs($user)->get(route('admin.yayasan.edit'));

    $response->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/YayasanSettingControllerTest.php`
Expected: FAIL — route `admin.yayasan.edit` not defined.

- [ ] **Step 3: Add the routes**

In `routes/admin.php`, find the existing `Route::resource('lembaga', LembagaController::class)...` line and add after it (still inside the same `admin`-prefixed/named middleware group):

```php
    Route::get('pengaturan-yayasan', [\App\Http\Controllers\Admin\YayasanSettingController::class, 'edit'])->name('yayasan.edit');
    Route::put('pengaturan-yayasan', [\App\Http\Controllers\Admin\YayasanSettingController::class, 'update'])->name('yayasan.update');
```

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/Admin/YayasanSettingController.php`:

```php
<?php
// app/Http/Controllers/Admin/YayasanSettingController.php

namespace App\Http\Controllers\Admin;

use App\Models\Yayasan;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class YayasanSettingController extends BaseController
{
    public function edit(): View
    {
        $this->authorize('yayasan.kelola');

        $yayasan = Yayasan::first();

        return view('admin.yayasan.edit', ['yayasan' => $yayasan]);
    }

    public function update(Request $request)
    {
        $this->authorize('yayasan.kelola');

        $yayasan = Yayasan::first();

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'npwp_yayasan' => ['nullable', 'string', 'max:255'],
            'akta_pendirian_nomor' => ['nullable', 'string', 'max:255'],
            'akta_pendirian_tanggal' => ['nullable', 'date'],
            'sk_kemenkumham_nomor' => ['nullable', 'string', 'max:255'],
            'alamat' => ['nullable', 'string'],
            'telepon' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'nama_ketua_pembina' => ['nullable', 'string', 'max:255'],
            'nama_ketua_pengurus' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,svg', 'max:1024'],
        ]);

        if ($request->hasFile('logo')) {
            $oldLogo = $yayasan->logo;

            $data['logo'] = $request->file('logo')->store('yayasan-logo', 'public');

            if ($oldLogo) {
                Storage::disk('public')->delete($oldLogo);
            }
        }

        $yayasan->update($data);

        return redirect()->route('admin.yayasan.edit')->with('status', 'Data yayasan berhasil diperbarui.');
    }
}
```

- [ ] **Step 5: Create the view**

Create `resources/views/admin/yayasan/edit.blade.php`, following `admin/lembaga/edit.blade.php`'s hero-card + view/edit-mode-toggle pattern (without the tab structure, since Yayasan has no sub-collections):

```blade
<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6" x-data="{ mode: {{ $errors->any() ? "'edit'" : "'view'" }}, logoPreview: null }">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm font-medium text-success-700 shadow-sm">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm font-medium text-error-700 shadow-sm">
                Terdapat kesalahan pengisian pada formulir, silakan periksa kembali isian di bawah.
            </div>
        @endif

        @if ($yayasan === null)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-800">
                Belum ada data yayasan pada sistem ini. Hubungi developer untuk inisialisasi data.
            </div>
        @else
            {{-- Hero Card --}}
            <div class="relative overflow-hidden rounded-2xl border border-gray-200/80 bg-white p-6 shadow-card md:p-8">
                <div class="relative flex flex-col gap-6 md:flex-row md:items-center justify-between">
                    <div class="flex items-center gap-6">
                        <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-600 via-teal-700 to-emerald-800 text-white shadow-md overflow-hidden">
                            @if ($yayasan->logo)
                                <img src="{{ Storage::disk('public')->url($yayasan->logo) }}" alt="Logo Yayasan" class="h-full w-full object-cover">
                            @else
                                <x-icon name="account_balance" class="h-10 w-10" />
                            @endif
                        </div>
                        <div>
                            <h1 class="font-display text-2xl font-bold tracking-tight text-gray-900">{{ $yayasan->nama }}</h1>
                            <p class="mt-1 text-sm text-gray-500">{{ $yayasan->lembaga->count() }} lembaga di bawah naungan</p>
                        </div>
                    </div>
                    <button type="button" @click="mode = (mode === 'view' ? 'edit' : 'view')" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-bold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95">
                        <x-icon name="edit" class="h-4 w-4 text-brand-600" x-show="mode === 'view'" />
                        <x-icon name="visibility" class="h-4 w-4 text-indigo-600" x-show="mode === 'edit'" style="display: none;" />
                        <span x-text="mode === 'view' ? 'Mode Edit Profil' : 'Mode Lihat Profil'">Mode Edit Profil</span>
                    </button>
                </div>
            </div>

            {{-- READ-ONLY VIEW MODE --}}
            <div x-show="mode === 'view'" class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
                    <h3 class="mb-4 font-display font-bold text-gray-900">Identitas Yayasan</h3>
                    <dl class="space-y-4">
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">NPWP</dt><dd class="mt-1 font-mono text-gray-900">{{ $yayasan->npwp_yayasan ?: '-' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">Alamat</dt><dd class="mt-1 text-gray-900">{{ $yayasan->alamat ?: '-' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">Telepon</dt><dd class="mt-1 text-gray-900">{{ $yayasan->telepon ?: '-' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">Email</dt><dd class="mt-1 text-gray-900">{{ $yayasan->email ?: '-' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">Website</dt><dd class="mt-1 text-gray-900">{{ $yayasan->website ?: '-' }}</dd></div>
                    </dl>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
                    <h3 class="mb-4 font-display font-bold text-gray-900">Legalitas &amp; Kepemimpinan</h3>
                    <dl class="space-y-4">
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">No. Akta Pendirian</dt><dd class="mt-1 text-gray-900">{{ $yayasan->akta_pendirian_nomor ?: '-' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">Tanggal Akta</dt><dd class="mt-1 text-gray-900">{{ $yayasan->akta_pendirian_tanggal?->translatedFormat('d F Y') ?: '-' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">No. SK Kemenkumham</dt><dd class="mt-1 text-gray-900">{{ $yayasan->sk_kemenkumham_nomor ?: '-' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">Ketua Pembina</dt><dd class="mt-1 text-gray-900">{{ $yayasan->nama_ketua_pembina ?: '-' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">Ketua Pengurus</dt><dd class="mt-1 text-gray-900">{{ $yayasan->nama_ketua_pengurus ?: '-' }}</dd></div>
                    </dl>
                </div>
            </div>

            {{-- EDIT MODE FORM --}}
            <form x-show="mode === 'edit'" method="POST" action="{{ route('admin.yayasan.update') }}" enctype="multipart/form-data" class="grid grid-cols-1 gap-6 lg:grid-cols-2" style="display: none;">
                @csrf
                @method('PUT')
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                    <h3 class="font-display font-bold text-gray-900">Identitas Yayasan</h3>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Nama Yayasan</label>
                        <input type="text" name="nama" value="{{ old('nama', $yayasan->nama) }}" required class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">NPWP</label>
                        <input type="text" name="npwp_yayasan" value="{{ old('npwp_yayasan', $yayasan->npwp_yayasan) }}" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Alamat</label>
                        <textarea name="alamat" rows="3" class="mt-1 w-full rounded-xl border-gray-300 text-sm">{{ old('alamat', $yayasan->alamat) }}</textarea>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Telepon</label>
                        <input type="text" name="telepon" value="{{ old('telepon', $yayasan->telepon) }}" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" value="{{ old('email', $yayasan->email) }}" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Website</label>
                        <input type="url" name="website" value="{{ old('website', $yayasan->website) }}" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Logo</label>
                        <div class="mt-1 flex items-center gap-3">
                            <template x-if="logoPreview">
                                <img :src="logoPreview" class="h-12 w-12 rounded-lg object-cover border border-gray-200">
                            </template>
                            <template x-if="!logoPreview && '{{ $yayasan->logo }}'">
                                <img src="{{ $yayasan->logo ? Storage::disk('public')->url($yayasan->logo) : '' }}" class="h-12 w-12 rounded-lg object-cover border border-gray-200">
                            </template>
                            <input type="file" name="logo" accept=".jpg,.jpeg,.png,.svg" @change="logoPreview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" class="text-sm">
                        </div>
                        @error('logo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                    <h3 class="font-display font-bold text-gray-900">Legalitas &amp; Kepemimpinan</h3>
                    <div>
                        <label class="text-sm font-medium text-gray-700">No. Akta Pendirian</label>
                        <input type="text" name="akta_pendirian_nomor" value="{{ old('akta_pendirian_nomor', $yayasan->akta_pendirian_nomor) }}" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Tanggal Akta Pendirian</label>
                        <input type="date" name="akta_pendirian_tanggal" value="{{ old('akta_pendirian_tanggal', $yayasan->akta_pendirian_tanggal?->toDateString()) }}" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">No. SK Kemenkumham</label>
                        <input type="text" name="sk_kemenkumham_nomor" value="{{ old('sk_kemenkumham_nomor', $yayasan->sk_kemenkumham_nomor) }}" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Nama Ketua Pembina</label>
                        <input type="text" name="nama_ketua_pembina" value="{{ old('nama_ketua_pembina', $yayasan->nama_ketua_pembina) }}" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Nama Ketua Pengurus</label>
                        <input type="text" name="nama_ketua_pengurus" value="{{ old('nama_ketua_pengurus', $yayasan->nama_ketua_pengurus) }}" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    </div>
                    <div class="pt-2">
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                            Simpan Perubahan
                        </button>
                    </div>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
```

- [ ] **Step 6: Add sidebar nav link**

In `resources/views/layouts/sidebar.blade.php`, find the `'Data Induk'` group's `items` array (search for `'admin.lembaga.index'`) and add a new entry right after it:

```php
                Auth::user()->can('yayasan.kelola') ? ['route' => 'admin.yayasan.edit', 'pattern' => 'admin.yayasan.*', 'label' => 'Pengaturan Yayasan', 'icon' => 'landmark'] : null,
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/YayasanSettingControllerTest.php`
Expected: PASS (5 tests)

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/YayasanSettingController.php resources/views/admin/yayasan/edit.blade.php routes/admin.php resources/views/layouts/sidebar.blade.php tests/Feature/Admin/YayasanSettingControllerTest.php
git commit -m "feat(admin): add pengaturan yayasan page for full profile + logo management"
```

---

### Task 6: Cross-parent authorization regression suite for Riwayat/Kwitansi

**Files:**
- Create: `tests/Feature/Keuangan/RiwayatAuthorizationTest.php`

**Interfaces:**
- Consumes: routes/controllers from Tasks 3-4. No production code changes expected — if a gap is found, fix it here and document in the commit message.

- [ ] **Step 1: Write the two-party cross-authorization tests**

Create `tests/Feature/Keuangan/RiwayatAuthorizationTest.php`:

```php
<?php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Pembayaran;
use App\Models\PembayaranTagihan;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeParentWithLunasPembayaran(string $label): array
{
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => "Anak {$label}"]);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => "Ortu {$label}",
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '0812'.random_int(10000000, 99999999),
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $jenis = JenisTagihan::factory()->create(['nama' => "Jenis Tagihan {$label}"]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'lunas', 'net_amount' => 100000, 'paid_amount' => 100000,
    ]);
    $pembayaran = Pembayaran::create([
        'siswa_id' => $siswa->id, 'metode' => 'wallet_saldo', 'status' => 'lunas',
        'channel_reference' => (string) \Illuminate\Support\Str::uuid(),
    ]);
    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihan->id, 'amount_allocated' => 100000]);

    return [$user, $orangTua, $siswa, $pembayaran, $jenis];
}

it('does not show another parent\'s payment history entries', function () {
    [$userA, , , , $jenisA] = makeParentWithLunasPembayaran('A');
    [, , , , $jenisB] = makeParentWithLunasPembayaran('B');

    $response = $this->actingAs($userA)->get(route('keuangan.riwayat.index'));

    $response->assertOk();
    $response->assertSee($jenisA->nama);
    $response->assertDontSee($jenisB->nama);
});

it('blocks downloading another parent child\'s kwitansi', function () {
    [, , , $pembayaranA] = makeParentWithLunasPembayaran('A');
    [$userB] = makeParentWithLunasPembayaran('B');

    $response = $this->actingAs($userB)->get(route('keuangan.riwayat.kwitansi', $pembayaranA));

    $response->assertForbidden();
});
```

- [ ] **Step 2: Run the tests**

Run: `php artisan test tests/Feature/Keuangan/RiwayatAuthorizationTest.php`
Expected: PASS (2 tests). If either fails, the fix belongs in `RiwayatController` (tighten the `siswa_id` scoping or the `authorizePembayaran()` call) — fix it in this task, then re-run.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Keuangan/RiwayatAuthorizationTest.php
git commit -m "test(keuangan): add two-party cross-parent authorization suite for riwayat/kwitansi"
```

---

### Task 7: Playwright verification + scoped regression + full-suite gate

**Files:**
- Modify: `scripts/keuangan-6a-browser-check.mjs`

**Interfaces:**
- Consumes: the live dev server (`http://localhost:8000`), demo account `ortu.demo@permatakraksaan.sch.id` / `password`.
- Produces: one new check function appended to the existing script.

- [ ] **Step 1: Ensure the demo account has at least one lunas payment for this check**

Run this once against the real dev DB (not the test DB) via tinker:

```bash
php artisan tinker --execute="
\$siswa = \App\Models\Siswa::whereHas('orangTua.user', fn(\$q) => \$q->where('email', 'ortu.demo@permatakraksaan.sch.id'))->first();
\$jenis = \App\Models\JenisTagihan::first();
\$tagihan = \App\Models\Tagihan::updateOrCreate(
    ['tagihable_id' => \$siswa->id, 'tagihable_type' => \App\Models\Siswa::class, 'jenis_tagihan_id' => \$jenis->id, 'status' => 'lunas'],
    ['total_tagihan' => 25000, 'net_amount' => 25000, 'paid_amount' => 25000, 'jatuh_tempo' => now()->subDays(3)]
);
\$pembayaran = \App\Models\Pembayaran::firstOrCreate(
    ['siswa_id' => \$siswa->id, 'metode' => 'cash', 'status' => 'lunas'],
    ['channel_reference' => (string) \Illuminate\Support\Str::uuid()]
);
\App\Models\PembayaranTagihan::firstOrCreate(
    ['pembayaran_id' => \$pembayaran->id, 'tagihan_id' => \$tagihan->id],
    ['amount_allocated' => 25000]
);
echo 'riwayat fixture ready, pembayaran id: '.\$pembayaran->id.PHP_EOL;
"
```

Expected output: `riwayat fixture ready, pembayaran id: <some number>`

- [ ] **Step 2: Add `checkRiwayatKwitansi()` to the Playwright script**

Read `scripts/keuangan-6a-browser-check.mjs` in full first, then append a new function matching the file's existing style (login flow, `console.log` format, dispatch-block wiring):

```javascript
async function checkRiwayatKwitansi(page) {
  await page.goto(`${BASE_URL}/keuangan/riwayat`);
  const lunasRow = page.locator('text=Lunas').first();
  await lunasRow.waitFor({ state: 'visible', timeout: 3000 });

  const kwitansiLink = page.locator('a:has-text("Unduh Kwitansi")').first();
  await kwitansiLink.waitFor({ state: 'visible', timeout: 3000 });
  const href = await kwitansiLink.getAttribute('href');

  const response = await page.request.get(href);
  const contentType = response.headers()['content-type'];
  if (!contentType || !contentType.includes('application/pdf')) {
    throw new Error(`Expected PDF content-type, got: ${contentType}`);
  }
  console.log('[riwayat] history page renders lunas row and kwitansi PDF link returns application/pdf: OK');
}
```

Add `checkRiwayatKwitansi` to the script's existing dispatch block under the flag name `riwayat`.

- [ ] **Step 3: Run the Playwright check against the live dev server**

Ensure the dev server is running on port 8000, then:

Run: `KEUANGAN_CHECK_BASE_URL=http://localhost:8000 node scripts/keuangan-6a-browser-check.mjs --check=riwayat`
Expected: `[riwayat] history page renders lunas row and kwitansi PDF link returns application/pdf: OK`

- [ ] **Step 4: Run the scoped regression suite**

Run: `php artisan test tests/Feature/Keuangan/ tests/Feature/Admin/YayasanSettingControllerTest.php tests/Unit/PermissionSeederTest.php tests/Unit/RoleSeederTest.php tests/Feature/RolePermissionSeederTest.php`
Expected: all pass, zero failures. If any pre-existing test now fails, it's a real regression from this plan — fix before continuing.

- [ ] **Step 5: Run the full-suite as the final gate, in isolation**

Confirm no other `php artisan test` process is running, then:

Run: `php artisan test`
Expected: only the established pre-existing baseline failures (`LembagaCrudTest`, `RoleBuilderTest` x4, `RoleFormAuditBannerTest` — 6 total, per Sub-project 6a/6b's handoff logs), zero new failures. If the count or specific tests differ, investigate before proceeding — re-run in isolation to confirm before assuming DB-race noise.

- [ ] **Step 6: Commit**

```bash
git add scripts/keuangan-6a-browser-check.mjs
git commit -m "test(keuangan): add riwayat+kwitansi Playwright check, completing 6c verification"
```

---

## After all tasks: handoff log

Write `.agents/logs/keuangan-06c-riwayat-kwitansi-logo.md` following the exact structure of `.agents/logs/keuangan-06b-rekap-tagihan-checkout.md` (status, what was built, task-by-task summary, process notes, final verification numbers, explicitly-out-of-scope items, open items deferred to 6c2/6d — in particular re-surface 6b's still-unaddressed open items: bundled top-up needs a real design, admin verification UI for manual-transfer proofs, `CheckoutController::status()`'s heavier-than-spec polling query).
