# Keuangan Sub-project 6b: Rekap Tagihan Aktif & Checkout Multi-Channel — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give orang-tua users a working "Rekap Tagihan Aktif" page and a 4-channel checkout flow (VA BRI, QRIS, Saldo Wallet, Transfer Manual) under the existing `/keuangan` portal, wiring Sub-project 04/05's payment backend to real UI for the first time.

**Architecture:** Two new controllers (`TagihanController`, `CheckoutController`) under `App\Http\Controllers\Keuangan`, one new service method (`PaymentService::createWalletPayment`), and Blade views following the existing tab pattern from `resources/views/admin/guru/edit.blade.php`. All VA/QRIS/manual-transfer logic reuses `PaymentService` methods shipped in Sub-project 04/05 unmodified.

**Tech Stack:** Laravel 12, Eloquent, Blade + Alpine.js, Pest, Playwright (Node, existing script).

## Global Constraints

- Guard: `web` only. No `Portal`/`AkunPendaftar` code paths.
- All new routes live under the existing `keuangan.*` group in `routes/web.php` (middleware `['auth','verified','permission:keuangan.akses','resolve.active.siswa']`) — do not create a new middleware stack.
- The active child is `$request->attributes->get('activeSiswa')`, set by `ResolveActiveSiswa` (already authorizes the child belongs to the acting `orang_tua` — no need to re-derive or re-check tenant ownership of the child itself in these controllers).
- `Tagihan` has **no** `TenantScope` global scope — query it directly by `tagihable_id`/`tagihable_type`, no `withoutGlobalScope` needed.
- Do **not** modify `AutoAllocationEngine`, `Wallet::topup()`, `Wallet::debit()`, or the BRI webhook controller.
- Cicilan (`Tagihan::cicilan()`, `SkemaCicilan`) is out of scope — do not add any cicilan UI or logic.
- Every controller action that loads a `Pembayaran` or `Tagihan` by route/query id must verify it belongs to a child of `Auth::user()->orangTua` — this is the project's most-recurring bug class (10+ prior IDOR recurrences).
- Reuse `<x-app-layout>` and the existing tab visual pattern (`border-brand-600 text-brand-600` active / `border-transparent text-gray-500` inactive, from `resources/views/admin/guru/edit.blade.php`).
- Testing: during work, run only `tests/Feature/Keuangan/` plus any directly-touched model's tests — not the full suite. Run the full suite exactly once, in isolation, as the final gate (Task 8).
- No new database migration is needed for query performance: `tagihan` already has `idx_tagihan_status_jtempo` (status, jatuh_tempo) and an index on (tagihable_type, tagihable_id) from prior migrations, confirmed by reading `database/migrations/2026_07_15_120200_create_tagihan_table.php` and `2026_08_10_130000_add_polymorphic_columns_to_tagihan_table.php`. Since a query always filters to one student's tagihan (typically well under 100 rows), these two indexes are sufficient — do not add a redundant composite index.
- Playwright: extend `scripts/keuangan-6a-browser-check.mjs` with the minimum needed to prove tab-switching works and one full channel (wallet) checkout succeeds end-to-end — not every channel.

---

### Task 1: `PaymentService::createWalletPayment()`

**Files:**
- Modify: `app/Services/Finance/PaymentService.php`
- Test: `tests/Feature/Keuangan/PaymentServiceWalletPaymentTest.php`

**Interfaces:**
- Consumes: `Wallet::debitWithinTransaction(float $amount, ?Pembayaran $pembayaran, ?string $keterangan): void` (existing, `app/Models/Wallet.php:97`), `PaymentAllocationService::allocate(Pembayaran $pembayaran): void` (existing), `PaymentService::createPembayaranRecord()` (existing protected method), `PaymentService::guardAgainstInvalidTagihan()` (existing protected method).
- Produces: `PaymentService::createWalletPayment(Siswa $siswa, Collection $tagihans): Pembayaran` — throws `App\Exceptions\InsufficientBalanceException` if wallet balance is less than the sum of outstanding tagihan amounts. On success returns a `Pembayaran` with `metode = 'wallet_saldo'`, `status = 'lunas'`, and the related tagihan already allocated (their `status`/`paid_amount` updated by `PaymentAllocationService::allocate`).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Keuangan/PaymentServiceWalletPaymentTest.php`:

```php
<?php

namespace Tests\Feature\Keuangan;

use App\Contracts\PaymentGatewayInterface;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Pembayaran;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Services\Finance\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentServiceWalletPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.bri.gateway' => 'mock']);
        $this->app->forgetInstance(PaymentGatewayInterface::class);
        $this->service = app()->make(PaymentService::class);
    }

    public function test_create_wallet_payment_debits_wallet_and_allocates_tagihan()
    {
        $siswa = Siswa::factory()->create();
        $siswa->wallet->update(['balance' => 100000]);

        $tagihan = Tagihan::factory()->create([
            'tagihable_id' => $siswa->id,
            'tagihable_type' => Siswa::class,
            'status' => 'belum_bayar',
            'total_tagihan' => 60000,
            'net_amount' => 60000,
            'paid_amount' => 0,
        ]);

        $pembayaran = $this->service->createWalletPayment($siswa, collect([$tagihan]));

        $this->assertInstanceOf(Pembayaran::class, $pembayaran);
        $this->assertEquals('wallet_saldo', $pembayaran->metode);
        $this->assertEquals('lunas', $pembayaran->status);

        $siswa->wallet->refresh();
        $this->assertEquals(40000, $siswa->wallet->balance);

        $tagihan->refresh();
        $this->assertEquals('lunas', $tagihan->status);
        $this->assertEquals(60000, $tagihan->paid_amount);
    }

    public function test_create_wallet_payment_throws_when_balance_insufficient()
    {
        $siswa = Siswa::factory()->create();
        $siswa->wallet->update(['balance' => 10000]);

        $tagihan = Tagihan::factory()->create([
            'tagihable_id' => $siswa->id,
            'tagihable_type' => Siswa::class,
            'status' => 'belum_bayar',
            'total_tagihan' => 60000,
            'net_amount' => 60000,
            'paid_amount' => 0,
        ]);

        $this->expectException(InsufficientBalanceException::class);

        try {
            $this->service->createWalletPayment($siswa, collect([$tagihan]));
        } finally {
            // Assert no partial state: wallet untouched, no Pembayaran row created,
            // tagihan untouched — the whole operation must roll back atomically.
            $siswa->wallet->refresh();
            $this->assertEquals(10000, $siswa->wallet->balance);
            $this->assertEquals(0, Pembayaran::where('siswa_id', $siswa->id)->count());
            $tagihan->refresh();
            $this->assertEquals('belum_bayar', $tagihan->status);
        }
    }

    public function test_create_wallet_payment_rejects_cancelled_or_paid_tagihan()
    {
        $siswa = Siswa::factory()->create();
        $siswa->wallet->update(['balance' => 100000]);

        $tagihan = Tagihan::factory()->create([
            'tagihable_id' => $siswa->id,
            'tagihable_type' => Siswa::class,
            'status' => 'lunas',
        ]);

        $this->expectException(\App\Exceptions\PaymentException::class);

        $this->service->createWalletPayment($siswa, collect([$tagihan]));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Keuangan/PaymentServiceWalletPaymentTest.php`
Expected: FAIL with "Call to undefined method App\Services\Finance\PaymentService::createWalletPayment()"

- [ ] **Step 3: Implement `createWalletPayment()`**

In `app/Services/Finance/PaymentService.php`, add `use App\Exceptions\InsufficientBalanceException;` and `use App\Models\Tagihan;` to the `use` block at the top, then add this public method (place it after `createManualTopupPayment()` and before `createCashPayment()`):

```php
    /**
     * Pay one or more tagihan directly from the student's wallet balance.
     * Debits within the same locked transaction as the balance check
     * (via Wallet::debitWithinTransaction) so two concurrent submissions
     * cannot both pass the balance check and double-spend.
     */
    public function createWalletPayment(Siswa $siswa, Collection $tagihans): Pembayaran
    {
        $this->guardAgainstInvalidTagihan($tagihans);

        return DB::transaction(function () use ($siswa, $tagihans) {
            $wallet = $siswa->wallet()->lockForUpdate()->first();

            if ($wallet === null) {
                throw new PaymentException('Siswa tidak memiliki wallet.');
            }

            $totalTagihan = $tagihans->reduce(
                fn (float $carry, Tagihan $tagihan) => $carry + ($tagihan->net_amount - $tagihan->paid_amount),
                0.0
            );

            if ($wallet->balance < $totalTagihan) {
                throw new InsufficientBalanceException('Saldo wallet tidak mencukupi untuk membayar tagihan terpilih.');
            }

            $pembayaran = $this->createPembayaranRecord($siswa, $tagihans, 'wallet_saldo', 'lunas');

            $wallet->debitWithinTransaction($totalTagihan, $pembayaran, 'Bayar tagihan dari saldo wallet');

            $this->allocationService->allocate($pembayaran);

            return $pembayaran;
        });
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Keuangan/PaymentServiceWalletPaymentTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Finance/PaymentService.php tests/Feature/Keuangan/PaymentServiceWalletPaymentTest.php
git commit -m "feat(keuangan): add PaymentService::createWalletPayment for on-demand wallet checkout"
```

---

### Task 2: Rekap Tagihan Aktif page

**Files:**
- Create: `app/Http/Controllers/Keuangan/TagihanController.php`
- Create: `resources/views/keuangan/tagihan/index.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/sidebar.blade.php`
- Test: `tests/Feature/Keuangan/TagihanControllerTest.php`

**Interfaces:**
- Consumes: `$request->attributes->get('activeSiswa')` (existing, set by `ResolveActiveSiswa`), `SystemSetting::getResolved(string $key, ?int $lembagaId, $default)` (existing, `app/Models/SystemSetting.php`).
- Produces: route `keuangan.tagihan.index` (`GET /keuangan/tagihan`), view `keuangan.tagihan.index` receiving `$activeSiswa`, `$tagihans` (Collection of `Tagihan`, eager-loaded `jenisTagihan`), `$autoDebitEnabled` (bool).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Keuangan/TagihanControllerTest.php`:

```php
<?php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\SystemSetting;
use App\Models\Tagihan;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAsOrangTuaForTagihan(): array
{
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Anak Tagihan']);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Tagihan',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200004444',
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    return [$user, $orangTua, $siswa, $lembaga];
}

it('lists only belum_bayar and sebagian tagihan for the active child, ordered by jatuh_tempo', function () {
    [$user, , $siswa] = actingAsOrangTuaForTagihan();

    $jenis = JenisTagihan::factory()->create(['nama' => 'SPP Bulanan']);

    $near = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'belum_bayar', 'net_amount' => 100000, 'paid_amount' => 0, 'jatuh_tempo' => now()->addDays(2),
    ]);
    $far = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'sebagian', 'net_amount' => 200000, 'paid_amount' => 50000, 'jatuh_tempo' => now()->addDays(10),
    ]);
    Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'lunas', 'net_amount' => 50000, 'paid_amount' => 50000, 'jatuh_tempo' => now()->addDays(1),
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.tagihan.index'));

    $response->assertOk();
    $response->assertSeeInOrder(['SPP Bulanan', 'SPP Bulanan']);
    $response->assertViewHas('tagihans', function ($tagihans) use ($near, $far) {
        return $tagihans->pluck('id')->all() === [$near->id, $far->id];
    });
});

it('shows the auto-debit banner only when the setting is enabled for the lembaga', function () {
    [$user, , $siswa, $lembaga] = actingAsOrangTuaForTagihan();

    SystemSetting::create(['lembaga_id' => $lembaga->id, 'key' => 'auto_debit_enabled', 'value' => true]);

    $response = $this->actingAs($user)->get(route('keuangan.tagihan.index'));

    $response->assertOk();
    $response->assertSee('Auto-debit aktif');
});

it('denies access without keuangan.akses permission', function () {
    $user = User::factory()->create(['lembaga_id' => null]);

    $response = $this->actingAs($user)->get(route('keuangan.tagihan.index'));

    $response->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/TagihanControllerTest.php`
Expected: FAIL — route `keuangan.tagihan.index` not defined.

- [ ] **Step 3: Add the route**

In `routes/web.php`, modify the existing `keuangan.*` group:

```php
Route::middleware(['auth', 'verified', 'permission:keuangan.akses', 'resolve.active.siswa'])
    ->prefix('keuangan')->name('keuangan.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Keuangan\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/tagihan', [\App\Http\Controllers\Keuangan\TagihanController::class, 'index'])->name('tagihan.index');
    });
```

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/Keuangan/TagihanController.php`:

```php
<?php
// app/Http/Controllers/Keuangan/TagihanController.php

namespace App\Http\Controllers\Keuangan;

use App\Models\SystemSetting;
use App\Models\Tagihan;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class TagihanController extends BaseController
{
    public function index(Request $request): View
    {
        $activeSiswa = $request->attributes->get('activeSiswa');

        if ($activeSiswa === null) {
            return view('keuangan.tanpa-anak');
        }

        $tagihans = Tagihan::where('tagihable_type', get_class($activeSiswa))
            ->where('tagihable_id', $activeSiswa->id)
            ->whereIn('status', ['belum_bayar', 'sebagian'])
            ->with('jenisTagihan')
            ->orderBy('jatuh_tempo')
            ->get();

        $autoDebitEnabled = (bool) SystemSetting::getResolved('auto_debit_enabled', $activeSiswa->lembaga_id, false);

        return view('keuangan.tagihan.index', [
            'activeSiswa' => $activeSiswa,
            'tagihans' => $tagihans,
            'autoDebitEnabled' => $autoDebitEnabled,
        ]);
    }
}
```

- [ ] **Step 5: Create the view**

Create `resources/views/keuangan/tagihan/index.blade.php`:

```blade
{{-- resources/views/keuangan/tagihan/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-xl font-bold text-gray-900">Rekap Tagihan Aktif — {{ $activeSiswa->nama_lengkap }}</h2>
    </x-slot>

    <div class="space-y-6" x-data="{ selected: [] }">
        @if ($autoDebitEnabled)
            <div class="rounded-2xl border border-brand-200 bg-brand-50 p-4 text-sm text-brand-800">
                Auto-debit aktif — tagihan akan otomatis dipotong dari saldo wallet saat top-up. Anda tetap bisa membayar tagihan tertentu secara manual di bawah ini.
            </div>
        @endif

        <div class="rounded-2xl border border-gray-200 bg-white p-6">
            @if ($tagihans->isEmpty())
                <p class="text-sm text-gray-500">Tidak ada tagihan aktif saat ini.</p>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($tagihans as $tagihan)
                        <label class="flex items-center gap-4 py-4">
                            <input type="checkbox" value="{{ $tagihan->id }}" x-model="selected" class="h-4 w-4 rounded border-gray-300 text-brand-600">
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-gray-900">{{ $tagihan->jenisTagihan->nama }}</p>
                                <p class="text-xs text-gray-500">Jatuh tempo {{ $tagihan->jatuh_tempo?->translatedFormat('d M Y') ?? '-' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold text-gray-900">Rp{{ number_format($tagihan->net_amount - $tagihan->paid_amount, 0, ',', '.') }}</p>
                                <span class="text-xs uppercase text-gray-400">{{ $tagihan->status }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>

                <div x-show="selected.length > 0" x-cloak class="mt-6 flex items-center justify-end">
                    <a :href="`{{ route('keuangan.checkout.create') }}?` + selected.map(id => `tagihan_ids[]=${id}`).join('&')"
                       class="inline-flex items-center justify-center rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                        Bayar Terpilih
                    </a>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Add sidebar nav link**

Find the `keuangan.akses`-gated nav item in `resources/views/layouts/sidebar.blade.php` from 6a and add a second link right after it, following the same `@if` guard pattern already in that file:

```blade
<a href="{{ route('keuangan.tagihan.index') }}" class="{{ request()->routeIs('keuangan.tagihan.*') ? 'bg-brand-50 text-brand-700' : 'text-gray-600 hover:bg-gray-50' }} flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium">
    <x-icon name="receipt_long" class="h-5 w-5" />
    <span>Tagihan</span>
</a>
```

(Match the exact class structure of the existing dashboard link found in that file — read it first and mirror its wrapper/conditional exactly rather than introducing a new style.)

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Keuangan/TagihanControllerTest.php`
Expected: PASS (3 tests). Note: route `keuangan.checkout.create` referenced in the view does not exist until Task 3 — the test above does not click that link, so it will still pass; if you add a Playwright/browser check before Task 3 exists, skip the "Bayar Terpilih" click until then.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Keuangan/TagihanController.php resources/views/keuangan/tagihan/index.blade.php routes/web.php resources/views/layouts/sidebar.blade.php tests/Feature/Keuangan/TagihanControllerTest.php
git commit -m "feat(keuangan): add rekap tagihan aktif page"
```

---

### Task 3: Checkout tab page (channel selection, GET only)

**Files:**
- Create: `app/Http/Controllers/Keuangan/CheckoutController.php`
- Create: `resources/views/keuangan/checkout/create.blade.php`
- Create: `resources/views/keuangan/checkout/tabs/va.blade.php`
- Create: `resources/views/keuangan/checkout/tabs/qris.blade.php`
- Create: `resources/views/keuangan/checkout/tabs/wallet.blade.php`
- Create: `resources/views/keuangan/checkout/tabs/transfer.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/keuangan/dashboard.blade.php`
- Test: `tests/Feature/Keuangan/CheckoutControllerCreateTest.php`

**Interfaces:**
- Consumes: `$request->attributes->get('activeSiswa')`, `Tagihan` model (Task 2's query pattern), `Wallet` model (`balance`).
- Produces: route `keuangan.checkout.create` (`GET /keuangan/checkout`), view receiving `$activeSiswa`, `$tagihans` (selected, Collection), `$totalTagihan` (float), `$wallet`. This task does NOT implement any POST submit handler — that is Tasks 4/5/6. The tab partials contain `<form>` tags pointing at routes named `keuangan.checkout.va`/`.qris`/`.wallet`/`.transfer`, which will 404 until those tasks land; that is expected and acceptable at the end of this task.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Keuangan/CheckoutControllerCreateTest.php`:

```php
<?php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAsOrangTuaForCheckout(): array
{
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Anak Checkout']);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Checkout',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200005555',
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    return [$user, $orangTua, $siswa];
}

it('shows the checkout tabs with the selected tagihan total', function () {
    [$user, , $siswa] = actingAsOrangTuaForCheckout();

    $jenis = JenisTagihan::factory()->create(['nama' => 'SPP Bulanan']);
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'belum_bayar', 'net_amount' => 150000, 'paid_amount' => 0,
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.checkout.create', ['tagihan_ids' => [$tagihan->id]]));

    $response->assertOk();
    $response->assertSee('150.000', false);
    $response->assertSee('VA BRI');
    $response->assertSee('QRIS');
    $response->assertSee('Saldo Wallet');
    $response->assertSee('Transfer Manual');
});

it('ignores tagihan ids that do not belong to the active child', function () {
    [$user, , $siswa] = actingAsOrangTuaForCheckout();
    $otherSiswa = Siswa::factory()->create();

    $jenis = JenisTagihan::factory()->create();
    $foreignTagihan = Tagihan::factory()->create([
        'tagihable_id' => $otherSiswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'belum_bayar', 'net_amount' => 999999, 'paid_amount' => 0,
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.checkout.create', ['tagihan_ids' => [$foreignTagihan->id]]));

    $response->assertOk();
    $response->assertDontSee('999.999', false);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/CheckoutControllerCreateTest.php`
Expected: FAIL — route `keuangan.checkout.create` not defined.

- [ ] **Step 3: Add the route**

In `routes/web.php`, extend the `keuangan.*` group from Task 2:

```php
        Route::get('/checkout', [\App\Http\Controllers\Keuangan\CheckoutController::class, 'create'])->name('checkout.create');
```

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/Keuangan/CheckoutController.php`:

```php
<?php
// app/Http/Controllers/Keuangan/CheckoutController.php

namespace App\Http\Controllers\Keuangan;

use App\Models\Tagihan;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class CheckoutController extends BaseController
{
    public function create(Request $request): View
    {
        $activeSiswa = $request->attributes->get('activeSiswa');

        $tagihanIds = (array) $request->query('tagihan_ids', []);

        $tagihans = Tagihan::where('tagihable_type', get_class($activeSiswa))
            ->where('tagihable_id', $activeSiswa->id)
            ->whereIn('status', ['belum_bayar', 'sebagian'])
            ->whereIn('id', $tagihanIds)
            ->with('jenisTagihan')
            ->get();

        $totalTagihan = $tagihans->reduce(
            fn (float $carry, Tagihan $tagihan) => $carry + ($tagihan->net_amount - $tagihan->paid_amount),
            0.0
        );

        return view('keuangan.checkout.create', [
            'activeSiswa' => $activeSiswa,
            'tagihans' => $tagihans,
            'totalTagihan' => $totalTagihan,
            'wallet' => $activeSiswa->wallet,
        ]);
    }
}
```

- [ ] **Step 5: Create the main checkout view with tabs**

Create `resources/views/keuangan/checkout/create.blade.php`:

```blade
{{-- resources/views/keuangan/checkout/create.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-xl font-bold text-gray-900">Checkout Pembayaran</h2>
    </x-slot>

    <div class="space-y-6" x-data="{ activeTab: 'va', topupAmount: '' }">
        <div class="rounded-2xl border border-gray-200 bg-white p-6">
            <p class="text-sm font-semibold text-gray-900">Tagihan Terpilih</p>
            @if ($tagihans->isEmpty())
                <p class="mt-2 text-sm text-gray-500">Tidak ada tagihan valid yang dipilih.</p>
            @else
                <ul class="mt-3 space-y-2">
                    @foreach ($tagihans as $tagihan)
                        <li class="flex justify-between text-sm">
                            <span class="text-gray-700">{{ $tagihan->jenisTagihan->nama }}</span>
                            <span class="font-semibold text-gray-900">Rp{{ number_format($tagihan->net_amount - $tagihan->paid_amount, 0, ',', '.') }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-4 flex justify-between border-t border-gray-100 pt-3 text-sm font-bold">
                    <span>Total</span>
                    <span>Rp{{ number_format($totalTagihan, 0, ',', '.') }}</span>
                </div>
            @endif

            <div class="mt-4" x-show="activeTab !== 'wallet'">
                <label class="text-sm font-medium text-gray-700">Sekalian Top Up Wallet (opsional)</label>
                <input type="number" min="0" x-model="topupAmount" placeholder="0" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
            </div>
        </div>

        <div>
            <div class="flex border-b border-gray-200 overflow-x-auto text-sm font-semibold text-gray-500 scrollbar-none">
                <button type="button" @click="activeTab = 'va'" :class="activeTab === 'va' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="flex items-center gap-2 border-b-2 py-3 px-4 transition whitespace-nowrap">
                    <span>VA BRI</span>
                </button>
                <button type="button" @click="activeTab = 'qris'" :class="activeTab === 'qris' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="flex items-center gap-2 border-b-2 py-3 px-4 transition whitespace-nowrap">
                    <span>QRIS</span>
                </button>
                <button type="button" @click="activeTab = 'wallet'" :class="activeTab === 'wallet' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="flex items-center gap-2 border-b-2 py-3 px-4 transition whitespace-nowrap">
                    <span>Saldo Wallet</span>
                </button>
                <button type="button" @click="activeTab = 'transfer'" :class="activeTab === 'transfer' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="flex items-center gap-2 border-b-2 py-3 px-4 transition whitespace-nowrap">
                    <span>Transfer Manual</span>
                </button>
            </div>

            <div class="mt-6">
                @include('keuangan.checkout.tabs.va')
                @include('keuangan.checkout.tabs.qris')
                @include('keuangan.checkout.tabs.wallet')
                @include('keuangan.checkout.tabs.transfer')
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Create the VA tab partial**

Create `resources/views/keuangan/checkout/tabs/va.blade.php`:

```blade
<div x-show="activeTab === 'va'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
    <form method="POST" action="{{ route('keuangan.checkout.va') }}" class="rounded-2xl border border-gray-200 bg-white p-6">
        @csrf
        @foreach ($tagihans as $tagihan)
            <input type="hidden" name="tagihan_ids[]" value="{{ $tagihan->id }}">
        @endforeach
        <input type="hidden" name="topup_amount" x-bind:value="topupAmount">
        <p class="text-sm text-gray-600">Bayar via Virtual Account BRI. Nomor VA akan dibuat setelah Anda klik tombol di bawah.</p>
        <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
            Buat VA BRI
        </button>
    </form>
</div>
```

- [ ] **Step 7: Create the QRIS tab partial**

Create `resources/views/keuangan/checkout/tabs/qris.blade.php`:

```blade
<div x-show="activeTab === 'qris'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
    <form method="POST" action="{{ route('keuangan.checkout.qris') }}" class="rounded-2xl border border-gray-200 bg-white p-6">
        @csrf
        @foreach ($tagihans as $tagihan)
            <input type="hidden" name="tagihan_ids[]" value="{{ $tagihan->id }}">
        @endforeach
        <input type="hidden" name="topup_amount" x-bind:value="topupAmount">
        <p class="text-sm text-gray-600">Bayar via QRIS. Kode QR akan ditampilkan setelah Anda klik tombol di bawah.</p>
        <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
            Buat Kode QRIS
        </button>
    </form>
</div>
```

- [ ] **Step 8: Create the Wallet tab partial**

Create `resources/views/keuangan/checkout/tabs/wallet.blade.php`:

```blade
<div x-show="activeTab === 'wallet'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
    <form method="POST" action="{{ route('keuangan.checkout.wallet') }}" class="rounded-2xl border border-gray-200 bg-white p-6">
        @csrf
        @foreach ($tagihans as $tagihan)
            <input type="hidden" name="tagihan_ids[]" value="{{ $tagihan->id }}">
        @endforeach
        <p class="text-sm text-gray-600">Saldo Wallet saat ini: <span class="font-semibold">Rp{{ number_format($wallet?->balance ?? 0, 0, ',', '.') }}</span></p>
        @if (($wallet?->balance ?? 0) < $totalTagihan)
            <p class="mt-2 text-sm font-semibold text-red-600">Saldo tidak cukup, kurang Rp{{ number_format($totalTagihan - ($wallet?->balance ?? 0), 0, ',', '.') }}</p>
            <button type="submit" disabled class="mt-4 inline-flex cursor-not-allowed items-center justify-center rounded-xl bg-gray-300 px-4 py-2.5 text-sm font-semibold text-white">
                Bayar dari Saldo Wallet
            </button>
        @else
            <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                Bayar dari Saldo Wallet
            </button>
        @endif
    </form>
</div>
```

- [ ] **Step 9: Create the Transfer Manual tab partial**

Create `resources/views/keuangan/checkout/tabs/transfer.blade.php`:

```blade
<div x-show="activeTab === 'transfer'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
    <form method="POST" action="{{ route('keuangan.checkout.transfer') }}" enctype="multipart/form-data" class="rounded-2xl border border-gray-200 bg-white p-6 space-y-4">
        @csrf
        @foreach ($tagihans as $tagihan)
            <input type="hidden" name="tagihan_ids[]" value="{{ $tagihan->id }}">
        @endforeach
        <div>
            <label class="text-sm font-medium text-gray-700">Bank Asal Transfer</label>
            <input type="text" name="bank_origin" class="mt-1 w-full rounded-xl border-gray-300 text-sm" placeholder="Contoh: BCA">
        </div>
        <div>
            <label class="text-sm font-medium text-gray-700">Tanggal Transfer</label>
            <input type="date" name="transfer_date" required class="mt-1 w-full rounded-xl border-gray-300 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium text-gray-700">Bukti Transfer</label>
            <input type="file" name="transfer_proof" required accept="image/*,.pdf" class="mt-1 w-full text-sm">
        </div>
        @error('transfer_proof')
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror
        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
            Kirim Bukti Transfer
        </button>
    </form>
</div>
```

- [ ] **Step 10: Wire the dashboard's disabled placeholder buttons**

In `resources/views/keuangan/dashboard.blade.php`, replace the two disabled `<button>` placeholders from 6a with real links:

Replace:
```blade
                <button type="button" disabled title="Checkout top-up akan tersedia di Sub-project 6b" class="inline-flex cursor-not-allowed items-center justify-center rounded-xl bg-amber-600/50 px-4 py-2 text-sm font-semibold text-white">
                    Top-up Rp{{ number_format($skipAlert['selisih'], 0, ',', '.') }} Sekarang
                </button>
```
With:
```blade
                <a href="{{ route('keuangan.checkout.create') }}" class="inline-flex items-center justify-center rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-700">
                    Top-up Rp{{ number_format($skipAlert['selisih'], 0, ',', '.') }} Sekarang
                </a>
```

Replace:
```blade
            <button type="button" disabled title="Halaman top-up akan tersedia di Sub-project 6b" class="mt-4 inline-flex cursor-not-allowed items-center justify-center rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-400">
                + Top Up
            </button>
```
With:
```blade
            <a href="{{ route('keuangan.checkout.create') }}" class="mt-4 inline-flex items-center justify-center rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                + Top Up
            </a>
```

**Note:** this changes `tests/Feature/Keuangan/DashboardControllerTest.php` and/or `tests/Feature/Keuangan/ChildSwitcherTest.php` if either asserts the old `disabled`/`button:has-text("+ Top Up")` markup — grep both files for `Top Up` and `Checkout top-up akan tersedia` before this step and update any matching assertion to expect an `<a>` tag instead, in the same commit.

- [ ] **Step 11: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Keuangan/CheckoutControllerCreateTest.php tests/Feature/Keuangan/DashboardControllerTest.php`
Expected: PASS

- [ ] **Step 12: Commit**

```bash
git add app/Http/Controllers/Keuangan/CheckoutController.php resources/views/keuangan/checkout routes/web.php resources/views/keuangan/dashboard.blade.php tests/Feature/Keuangan/CheckoutControllerCreateTest.php tests/Feature/Keuangan/DashboardControllerTest.php tests/Feature/Keuangan/ChildSwitcherTest.php
git commit -m "feat(keuangan): add checkout channel-selection page with tab UI, wire dashboard CTAs"
```

---

### Task 4: VA & QRIS checkout submit + "menunggu pembayaran" page

**Files:**
- Modify: `app/Http/Controllers/Keuangan/CheckoutController.php`
- Create: `resources/views/keuangan/checkout/show.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Keuangan/CheckoutControllerVaQrisTest.php`

**Interfaces:**
- Consumes: `PaymentService::createVaPayment(Siswa $siswa, Collection $tagihans): Pembayaran` (existing), `PaymentService::createQrisPayment(...)` (existing), `Pembayaran::briVirtualAccount` / `->briQrisPayment` (existing `HasOne` relations).
- Produces: routes `keuangan.checkout.va` (POST), `keuangan.checkout.qris` (POST), `keuangan.checkout.show` (`GET /keuangan/checkout/{pembayaran}`), `keuangan.checkout.status` (`GET /keuangan/checkout/{pembayaran}/status`, JSON `{"status": "..."}`).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Keuangan/CheckoutControllerVaQrisTest.php`:

```php
<?php

use App\Contracts\PaymentGatewayInterface;
use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Pembayaran;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAsOrangTuaForVaQris(): array
{
    config(['services.bri.gateway' => 'mock']);
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu VaQris',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200006666',
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $jenis = JenisTagihan::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'belum_bayar', 'net_amount' => 120000, 'paid_amount' => 0,
    ]);

    return [$user, $orangTua, $siswa, $tagihan];
}

it('creates a VA payment and redirects to the waiting page', function () {
    [$user, , , $tagihan] = actingAsOrangTuaForVaQris();

    $response = $this->actingAs($user)->post(route('keuangan.checkout.va'), [
        'tagihan_ids' => [$tagihan->id],
    ]);

    $pembayaran = Pembayaran::where('metode', 'va_bri')->firstOrFail();
    $response->assertRedirect(route('keuangan.checkout.show', $pembayaran));
    expect($pembayaran->briVirtualAccount)->not->toBeNull();
});

it('creates a QRIS payment and redirects to the waiting page', function () {
    [$user, , , $tagihan] = actingAsOrangTuaForVaQris();

    $response = $this->actingAs($user)->post(route('keuangan.checkout.qris'), [
        'tagihan_ids' => [$tagihan->id],
    ]);

    $pembayaran = Pembayaran::where('metode', 'qris')->firstOrFail();
    $response->assertRedirect(route('keuangan.checkout.show', $pembayaran));
    expect($pembayaran->briQrisPayment)->not->toBeNull();
});

it('does not create a second VA for the same tagihan while one is still waiting', function () {
    [$user, , , $tagihan] = actingAsOrangTuaForVaQris();

    $this->actingAs($user)->post(route('keuangan.checkout.va'), ['tagihan_ids' => [$tagihan->id]]);
    $this->actingAs($user)->post(route('keuangan.checkout.va'), ['tagihan_ids' => [$tagihan->id]]);

    expect(Pembayaran::where('metode', 'va_bri')->count())->toBe(1);
});

it('rejects tagihan_ids that do not belong to the active child', function () {
    [$user] = actingAsOrangTuaForVaQris();
    $otherSiswa = Siswa::factory()->create();
    $jenis = JenisTagihan::factory()->create();
    $foreignTagihan = Tagihan::factory()->create([
        'tagihable_id' => $otherSiswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'belum_bayar', 'net_amount' => 999999, 'paid_amount' => 0,
    ]);

    $this->actingAs($user)->post(route('keuangan.checkout.va'), ['tagihan_ids' => [$foreignTagihan->id]]);

    expect(Pembayaran::where('metode', 'va_bri')->count())->toBe(0);
});

it('shows the waiting page with the VA number', function () {
    [$user, , , $tagihan] = actingAsOrangTuaForVaQris();
    $this->actingAs($user)->post(route('keuangan.checkout.va'), ['tagihan_ids' => [$tagihan->id]]);
    $pembayaran = Pembayaran::where('metode', 'va_bri')->firstOrFail();

    $response = $this->actingAs($user)->get(route('keuangan.checkout.show', $pembayaran));

    $response->assertOk();
    $response->assertSee($pembayaran->briVirtualAccount->va_number);
});

it('blocks viewing a pembayaran belonging to another parent\'s child', function () {
    [$user, , , $tagihan] = actingAsOrangTuaForVaQris();
    $this->actingAs($user)->post(route('keuangan.checkout.va'), ['tagihan_ids' => [$tagihan->id]]);
    $pembayaran = Pembayaran::where('metode', 'va_bri')->firstOrFail();

    [$otherUser] = actingAsOrangTuaForVaQris();

    $response = $this->actingAs($otherUser)->get(route('keuangan.checkout.show', $pembayaran));

    $response->assertForbidden();
});

it('returns the payment status as json for polling', function () {
    [$user, , , $tagihan] = actingAsOrangTuaForVaQris();
    $this->actingAs($user)->post(route('keuangan.checkout.va'), ['tagihan_ids' => [$tagihan->id]]);
    $pembayaran = Pembayaran::where('metode', 'va_bri')->firstOrFail();

    $response = $this->actingAs($user)->getJson(route('keuangan.checkout.status', $pembayaran));

    $response->assertOk();
    $response->assertJson(['status' => 'menunggu_pembayaran']);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Keuangan/CheckoutControllerVaQrisTest.php`
Expected: FAIL — routes `keuangan.checkout.va`/`.qris`/`.show`/`.status` not defined.

- [ ] **Step 3: Add the routes**

In `routes/web.php`, extend the `keuangan.*` group:

```php
        Route::post('/checkout/va', [\App\Http\Controllers\Keuangan\CheckoutController::class, 'va'])->name('checkout.va');
        Route::post('/checkout/qris', [\App\Http\Controllers\Keuangan\CheckoutController::class, 'qris'])->name('checkout.qris');
        Route::get('/checkout/{pembayaran}', [\App\Http\Controllers\Keuangan\CheckoutController::class, 'show'])->name('checkout.show');
        Route::get('/checkout/{pembayaran}/status', [\App\Http\Controllers\Keuangan\CheckoutController::class, 'status'])->name('checkout.status');
```

- [ ] **Step 4: Implement the controller actions**

In `app/Http/Controllers/Keuangan/CheckoutController.php`, add these imports at the top:

```php
use App\Exceptions\PaymentException;
use App\Models\Pembayaran;
use App\Services\Finance\PaymentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
```

Add a constructor and the new actions:

```php
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function va(Request $request)
    {
        $activeSiswa = $request->attributes->get('activeSiswa');
        $tagihans = $this->resolveSelectedTagihan($activeSiswa, (array) $request->input('tagihan_ids', []));

        if ($tagihans->isEmpty()) {
            return back()->withErrors(['tagihan_ids' => 'Tidak ada tagihan valid yang dipilih.']);
        }

        $existing = $this->findPendingVaFor($tagihans);
        if ($existing !== null) {
            return redirect()->route('keuangan.checkout.show', $existing);
        }

        try {
            $pembayaran = $this->paymentService->createVaPayment($activeSiswa, $tagihans);
        } catch (PaymentException $e) {
            Log::error('Gagal membuat VA BRI: '.$e->getMessage());
            return back()->withErrors(['tagihan_ids' => 'Gagal membuat pembayaran, silakan coba lagi.']);
        }

        return redirect()->route('keuangan.checkout.show', $pembayaran);
    }

    public function qris(Request $request)
    {
        $activeSiswa = $request->attributes->get('activeSiswa');
        $tagihans = $this->resolveSelectedTagihan($activeSiswa, (array) $request->input('tagihan_ids', []));

        if ($tagihans->isEmpty()) {
            return back()->withErrors(['tagihan_ids' => 'Tidak ada tagihan valid yang dipilih.']);
        }

        try {
            $pembayaran = $this->paymentService->createQrisPayment($activeSiswa, $tagihans);
        } catch (PaymentException $e) {
            Log::error('Gagal membuat QRIS: '.$e->getMessage());
            return back()->withErrors(['tagihan_ids' => 'Gagal membuat pembayaran, silakan coba lagi.']);
        }

        return redirect()->route('keuangan.checkout.show', $pembayaran);
    }

    public function show(Request $request, Pembayaran $pembayaran)
    {
        $this->authorizePembayaran($request, $pembayaran);

        return view('keuangan.checkout.show', ['pembayaran' => $pembayaran->load(['briVirtualAccount', 'briQrisPayment'])]);
    }

    public function status(Request $request, Pembayaran $pembayaran)
    {
        $this->authorizePembayaran($request, $pembayaran);

        return response()->json(['status' => $pembayaran->status]);
    }

    private function resolveSelectedTagihan($activeSiswa, array $tagihanIds)
    {
        return Tagihan::where('tagihable_type', get_class($activeSiswa))
            ->where('tagihable_id', $activeSiswa->id)
            ->whereIn('status', ['belum_bayar', 'sebagian'])
            ->whereIn('id', $tagihanIds)
            ->get();
    }

    private function findPendingVaFor($tagihans): ?Pembayaran
    {
        $tagihanIds = $tagihans->pluck('id');

        return Pembayaran::where('metode', 'va_bri')
            ->where('status', 'menunggu_pembayaran')
            ->whereHas('pembayaranTagihan', fn ($q) => $q->whereIn('tagihan_id', $tagihanIds))
            ->whereHas('briVirtualAccount', fn ($q) => $q->where('expired_at', '>', now()))
            ->first();
    }

    private function authorizePembayaran(Request $request, Pembayaran $pembayaran): void
    {
        $orangTua = Auth::user()->orangTua;
        $ownsChild = $orangTua !== null
            && $orangTua->siswa()->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)->whereKey($pembayaran->siswa_id)->exists();

        abort_unless($ownsChild, 403);
    }
```

Also add `use App\Models\Tagihan;` if not already present from Task 3 (it already is, from `create()`).

- [ ] **Step 5: Create the "menunggu pembayaran" view**

Create `resources/views/keuangan/checkout/show.blade.php`:

```blade
{{-- resources/views/keuangan/checkout/show.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-xl font-bold text-gray-900">Menunggu Pembayaran</h2>
    </x-slot>

    <div class="rounded-2xl border border-gray-200 bg-white p-6"
         x-data="{
            status: '{{ $pembayaran->status }}',
            expiredAt: {{ $pembayaran->briVirtualAccount?->expired_at?->timestamp ?? $pembayaran->briQrisPayment?->expired_at?->timestamp ?? 'null' }},
            remaining: '',
            expired: false,
            tick() {
                if (this.expiredAt === null) return;
                const diff = this.expiredAt - Math.floor(Date.now() / 1000);
                if (diff <= 0) { this.expired = true; this.remaining = '00:00'; return; }
                const m = Math.floor(diff / 60).toString().padStart(2, '0');
                const s = (diff % 60).toString().padStart(2, '0');
                this.remaining = `${m}:${s}`;
            },
            poll() {
                fetch('{{ route('keuangan.checkout.status', $pembayaran) }}')
                    .then(r => r.json())
                    .then(data => { this.status = data.status; });
            }
         }"
         x-init="tick(); setInterval(() => tick(), 1000); setInterval(() => poll(), 5000)">

        <template x-if="status === 'lunas'">
            <p class="text-sm font-semibold text-emerald-700">Pembayaran berhasil diterima. Terima kasih.</p>
        </template>

        <template x-if="status !== 'lunas' && !expired">
            <div>
                @if ($pembayaran->briVirtualAccount)
                    <p class="text-sm text-gray-500">Nomor Virtual Account BRI</p>
                    <p class="mt-1 font-mono text-2xl font-bold text-gray-900">{{ $pembayaran->briVirtualAccount->va_number }}</p>
                    <p class="mt-1 text-sm text-gray-500">Nominal: Rp{{ number_format($pembayaran->briVirtualAccount->amount, 0, ',', '.') }}</p>
                @elseif ($pembayaran->briQrisPayment)
                    <p class="text-sm text-gray-500">Kode QRIS</p>
                    <p class="mt-1 font-mono text-lg font-bold text-gray-900">{{ $pembayaran->briQrisPayment->qr_code }}</p>
                    <p class="mt-1 text-sm text-gray-500">Nominal: Rp{{ number_format($pembayaran->briQrisPayment->amount, 0, ',', '.') }}</p>
                @endif
                <p class="mt-4 text-sm text-gray-500">Sisa waktu: <span class="font-mono font-semibold" x-text="remaining"></span></p>
            </div>
        </template>

        <template x-if="status !== 'lunas' && expired">
            <div>
                <p class="text-sm font-semibold text-red-600">Kode pembayaran sudah kadaluarsa.</p>
                <a href="{{ route('keuangan.checkout.create') }}" class="mt-4 inline-flex items-center justify-center rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white">
                    Buat Ulang
                </a>
            </div>
        </template>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Keuangan/CheckoutControllerVaQrisTest.php`
Expected: PASS (7 tests)

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Keuangan/CheckoutController.php resources/views/keuangan/checkout/show.blade.php routes/web.php tests/Feature/Keuangan/CheckoutControllerVaQrisTest.php
git commit -m "feat(keuangan): implement VA BRI and QRIS checkout submit + waiting page"
```

---

### Task 5: Wallet checkout submit + success page

**Files:**
- Modify: `app/Http/Controllers/Keuangan/CheckoutController.php`
- Create: `resources/views/keuangan/checkout/sukses.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Keuangan/CheckoutControllerWalletTest.php`

**Interfaces:**
- Consumes: `PaymentService::createWalletPayment(Siswa $siswa, Collection $tagihans): Pembayaran` (Task 1).
- Produces: route `keuangan.checkout.wallet` (POST), redirects to `keuangan.checkout.sukses` (`GET /keuangan/checkout/{pembayaran}/sukses`).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Keuangan/CheckoutControllerWalletTest.php`:

```php
<?php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Pembayaran;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAsOrangTuaForWalletCheckout(float $balance = 200000): array
{
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa->wallet->update(['balance' => $balance]);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Wallet',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200007777',
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $jenis = JenisTagihan::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'belum_bayar', 'net_amount' => 100000, 'paid_amount' => 0,
    ]);

    return [$user, $orangTua, $siswa, $tagihan];
}

it('pays a tagihan from wallet balance and redirects to the success page', function () {
    [$user, , $siswa, $tagihan] = actingAsOrangTuaForWalletCheckout();

    $response = $this->actingAs($user)->post(route('keuangan.checkout.wallet'), [
        'tagihan_ids' => [$tagihan->id],
    ]);

    $pembayaran = Pembayaran::where('metode', 'wallet_saldo')->firstOrFail();
    $response->assertRedirect(route('keuangan.checkout.sukses', $pembayaran));

    $siswa->wallet->refresh();
    $this->assertEquals(100000, $siswa->wallet->balance);

    $tagihan->refresh();
    $this->assertEquals('lunas', $tagihan->status);
});

it('rejects wallet checkout when balance is insufficient', function () {
    [$user, , $siswa, $tagihan] = actingAsOrangTuaForWalletCheckout(balance: 10000);

    $response = $this->actingAs($user)->post(route('keuangan.checkout.wallet'), [
        'tagihan_ids' => [$tagihan->id],
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('tagihan_ids');
    $this->assertEquals(0, Pembayaran::where('metode', 'wallet_saldo')->count());

    $siswa->wallet->refresh();
    $this->assertEquals(10000, $siswa->wallet->balance);
});

it('shows the success page after a wallet payment', function () {
    [$user, , , $tagihan] = actingAsOrangTuaForWalletCheckout();
    $this->actingAs($user)->post(route('keuangan.checkout.wallet'), ['tagihan_ids' => [$tagihan->id]]);
    $pembayaran = Pembayaran::where('metode', 'wallet_saldo')->firstOrFail();

    $response = $this->actingAs($user)->get(route('keuangan.checkout.sukses', $pembayaran));

    $response->assertOk();
    $response->assertSee('berhasil');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Keuangan/CheckoutControllerWalletTest.php`
Expected: FAIL — route `keuangan.checkout.wallet` not defined.

- [ ] **Step 3: Add the routes**

In `routes/web.php`, extend the `keuangan.*` group:

```php
        Route::post('/checkout/wallet', [\App\Http\Controllers\Keuangan\CheckoutController::class, 'wallet'])->name('checkout.wallet');
        Route::get('/checkout/{pembayaran}/sukses', [\App\Http\Controllers\Keuangan\CheckoutController::class, 'sukses'])->name('checkout.sukses');
```

- [ ] **Step 4: Implement the controller actions**

In `app/Http/Controllers/Keuangan/CheckoutController.php`, add `use App\Exceptions\InsufficientBalanceException;` to the imports, then add:

```php
    public function wallet(Request $request)
    {
        $activeSiswa = $request->attributes->get('activeSiswa');
        $tagihans = $this->resolveSelectedTagihan($activeSiswa, (array) $request->input('tagihan_ids', []));

        if ($tagihans->isEmpty()) {
            return back()->withErrors(['tagihan_ids' => 'Tidak ada tagihan valid yang dipilih.']);
        }

        try {
            $pembayaran = $this->paymentService->createWalletPayment($activeSiswa, $tagihans);
        } catch (InsufficientBalanceException|PaymentException $e) {
            return back()->withErrors(['tagihan_ids' => 'Saldo wallet tidak mencukupi untuk tagihan terpilih.']);
        }

        return redirect()->route('keuangan.checkout.sukses', $pembayaran);
    }

    public function sukses(Request $request, Pembayaran $pembayaran)
    {
        $this->authorizePembayaran($request, $pembayaran);

        return view('keuangan.checkout.sukses', ['pembayaran' => $pembayaran->load('pembayaranTagihan.tagihan.jenisTagihan')]);
    }
```

- [ ] **Step 5: Create the success view**

Create `resources/views/keuangan/checkout/sukses.blade.php`:

```blade
{{-- resources/views/keuangan/checkout/sukses.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-xl font-bold text-gray-900">Pembayaran Berhasil</h2>
    </x-slot>

    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6">
        <p class="text-sm font-semibold text-emerald-800">Pembayaran dari Saldo Wallet berhasil diproses.</p>
        <ul class="mt-4 space-y-2">
            @foreach ($pembayaran->pembayaranTagihan as $pt)
                <li class="flex justify-between text-sm text-emerald-900">
                    <span>{{ $pt->tagihan->jenisTagihan->nama }}</span>
                    <span class="font-semibold">Rp{{ number_format($pt->amount_allocated, 0, ',', '.') }}</span>
                </li>
            @endforeach
        </ul>
        <a href="{{ route('keuangan.dashboard') }}" class="mt-6 inline-flex items-center justify-center rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white">
            Kembali ke Dashboard
        </a>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Keuangan/CheckoutControllerWalletTest.php`
Expected: PASS (3 tests)

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Keuangan/CheckoutController.php resources/views/keuangan/checkout/sukses.blade.php routes/web.php tests/Feature/Keuangan/CheckoutControllerWalletTest.php
git commit -m "feat(keuangan): implement wallet-balance checkout submit + success page"
```

---

### Task 6: Transfer manual checkout submit + "menunggu verifikasi" page

**Files:**
- Modify: `app/Http/Controllers/Keuangan/CheckoutController.php`
- Create: `app/Http/Requests/Keuangan/StoreManualTransferRequest.php`
- Create: `resources/views/keuangan/checkout/menunggu-verifikasi.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Keuangan/CheckoutControllerTransferTest.php`

**Interfaces:**
- Consumes: `PaymentService::createManualPayment(Siswa $siswa, Collection $tagihans, array $data): Pembayaran` (existing).
- Produces: route `keuangan.checkout.transfer` (POST), redirects to `keuangan.checkout.menunggu-verifikasi` (`GET /keuangan/checkout/{pembayaran}/menunggu-verifikasi`).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Keuangan/CheckoutControllerTransferTest.php`:

```php
<?php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Pembayaran;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAsOrangTuaForTransfer(): array
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
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Transfer',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200008888',
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $jenis = JenisTagihan::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'belum_bayar', 'net_amount' => 80000, 'paid_amount' => 0,
    ]);

    return [$user, $orangTua, $siswa, $tagihan];
}

it('submits a manual transfer proof and redirects to the verification-pending page', function () {
    Storage::fake('public');
    [$user, , , $tagihan] = actingAsOrangTuaForTransfer();

    $response = $this->actingAs($user)->post(route('keuangan.checkout.transfer'), [
        'tagihan_ids' => [$tagihan->id],
        'bank_origin' => 'BCA',
        'transfer_date' => now()->toDateString(),
        'transfer_proof' => UploadedFile::fake()->image('bukti.jpg'),
    ]);

    $pembayaran = Pembayaran::where('metode', 'transfer_manual')->firstOrFail();
    $response->assertRedirect(route('keuangan.checkout.menunggu-verifikasi', $pembayaran));
    $this->assertEquals('menunggu_verifikasi', $pembayaran->status);
    $this->assertNotNull($pembayaran->manualRequest);
    Storage::disk('public')->assertExists($pembayaran->manualRequest->transfer_proof_path);
});

it('requires a transfer proof file', function () {
    [$user, , , $tagihan] = actingAsOrangTuaForTransfer();

    $response = $this->actingAs($user)->post(route('keuangan.checkout.transfer'), [
        'tagihan_ids' => [$tagihan->id],
        'transfer_date' => now()->toDateString(),
    ]);

    $response->assertSessionHasErrors('transfer_proof');
});

it('rejects a transfer proof larger than 2MB', function () {
    Storage::fake('public');
    [$user, , , $tagihan] = actingAsOrangTuaForTransfer();

    $response = $this->actingAs($user)->post(route('keuangan.checkout.transfer'), [
        'tagihan_ids' => [$tagihan->id],
        'transfer_date' => now()->toDateString(),
        'transfer_proof' => UploadedFile::fake()->create('bukti.pdf', 3000, 'application/pdf'),
    ]);

    $response->assertSessionHasErrors('transfer_proof');
});

it('shows the verification-pending page', function () {
    Storage::fake('public');
    [$user, , , $tagihan] = actingAsOrangTuaForTransfer();
    $this->actingAs($user)->post(route('keuangan.checkout.transfer'), [
        'tagihan_ids' => [$tagihan->id],
        'transfer_date' => now()->toDateString(),
        'transfer_proof' => UploadedFile::fake()->image('bukti.jpg'),
    ]);
    $pembayaran = Pembayaran::where('metode', 'transfer_manual')->firstOrFail();

    $response = $this->actingAs($user)->get(route('keuangan.checkout.menunggu-verifikasi', $pembayaran));

    $response->assertOk();
    $response->assertSee('menunggu verifikasi', false);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Keuangan/CheckoutControllerTransferTest.php`
Expected: FAIL — route `keuangan.checkout.transfer` not defined.

- [ ] **Step 3: Add the routes**

In `routes/web.php`, extend the `keuangan.*` group:

```php
        Route::post('/checkout/transfer', [\App\Http\Controllers\Keuangan\CheckoutController::class, 'transfer'])->name('checkout.transfer');
        Route::get('/checkout/{pembayaran}/menunggu-verifikasi', [\App\Http\Controllers\Keuangan\CheckoutController::class, 'menungguVerifikasi'])->name('checkout.menunggu-verifikasi');
```

- [ ] **Step 4: Create the form request**

Create `app/Http/Requests/Keuangan/StoreManualTransferRequest.php`:

```php
<?php
// app/Http/Requests/Keuangan/StoreManualTransferRequest.php

namespace App\Http\Requests\Keuangan;

use Illuminate\Foundation\Http\FormRequest;

class StoreManualTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tagihan_ids' => ['required', 'array', 'min:1'],
            'tagihan_ids.*' => ['integer'],
            'bank_origin' => ['nullable', 'string', 'max:100'],
            'transfer_date' => ['required', 'date'],
            'transfer_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ];
    }
}
```

- [ ] **Step 5: Implement the controller actions**

In `app/Http/Controllers/Keuangan/CheckoutController.php`, add `use App\Http\Requests\Keuangan\StoreManualTransferRequest;` and `use Illuminate\Support\Facades\Auth;` (Auth already added in Task 4) to imports, then:

```php
    public function transfer(StoreManualTransferRequest $request)
    {
        $activeSiswa = $request->attributes->get('activeSiswa');
        $tagihans = $this->resolveSelectedTagihan($activeSiswa, (array) $request->input('tagihan_ids', []));

        if ($tagihans->isEmpty()) {
            return back()->withErrors(['tagihan_ids' => 'Tidak ada tagihan valid yang dipilih.']);
        }

        $path = $request->file('transfer_proof')->store('bukti-transfer', 'public');

        $totalTagihan = $tagihans->reduce(
            fn (float $carry, \App\Models\Tagihan $tagihan) => $carry + ($tagihan->net_amount - $tagihan->paid_amount),
            0.0
        );

        try {
            $pembayaran = $this->paymentService->createManualPayment($activeSiswa, $tagihans, [
                'amount' => $totalTagihan,
                'transfer_proof_path' => $path,
                'bank_origin' => $request->input('bank_origin'),
                'transfer_date' => $request->input('transfer_date'),
                'requested_by' => Auth::id(),
            ]);
        } catch (PaymentException $e) {
            return back()->withErrors(['tagihan_ids' => 'Gagal mengirim bukti transfer, silakan coba lagi.']);
        }

        return redirect()->route('keuangan.checkout.menunggu-verifikasi', $pembayaran);
    }

    public function menungguVerifikasi(Request $request, Pembayaran $pembayaran)
    {
        $this->authorizePembayaran($request, $pembayaran);

        return view('keuangan.checkout.menunggu-verifikasi', ['pembayaran' => $pembayaran->load('manualRequest')]);
    }
```

- [ ] **Step 6: Create the verification-pending view**

Create `resources/views/keuangan/checkout/menunggu-verifikasi.blade.php`:

```blade
{{-- resources/views/keuangan/checkout/menunggu-verifikasi.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-xl font-bold text-gray-900">Menunggu Verifikasi</h2>
    </x-slot>

    <div class="rounded-2xl border border-gray-200 bg-white p-6">
        <p class="text-sm text-gray-700">Bukti transfer Anda sudah diterima dan sedang menunggu verifikasi oleh admin. Anda akan menerima notifikasi setelah pembayaran ini diverifikasi.</p>
        <p class="mt-3 text-sm text-gray-500">Nominal: Rp{{ number_format($pembayaran->manualRequest->amount, 0, ',', '.') }}</p>
        <a href="{{ route('keuangan.dashboard') }}" class="mt-6 inline-flex items-center justify-center rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
            Kembali ke Dashboard
        </a>
    </div>
</x-app-layout>
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Keuangan/CheckoutControllerTransferTest.php`
Expected: PASS (4 tests)

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Keuangan/CheckoutController.php app/Http/Requests/Keuangan/StoreManualTransferRequest.php resources/views/keuangan/checkout/menunggu-verifikasi.blade.php routes/web.php tests/Feature/Keuangan/CheckoutControllerTransferTest.php
git commit -m "feat(keuangan): implement manual-transfer checkout submit + verification-pending page"
```

---

### Task 7: Cross-parent authorization regression suite

**Files:**
- Create: `tests/Feature/Keuangan/CheckoutAuthorizationTest.php`

**Interfaces:**
- Consumes: all routes/controllers from Tasks 2-6. No production code changes expected in this task — if a gap is found, fix it here and note the fix in the commit message.

- [ ] **Step 1: Write the two-party cross-authorization tests**

Create `tests/Feature/Keuangan/CheckoutAuthorizationTest.php`:

```php
<?php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Pembayaran;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeParentWithChild(string $label, float $walletBalance = 0): array
{
    config(['services.bri.gateway' => 'mock']);
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => "Anak {$label}"]);
    $siswa->wallet->update(['balance' => $walletBalance]);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => "Ortu {$label}",
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '0812'.random_int(10000000, 99999999),
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $jenis = JenisTagihan::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'belum_bayar', 'net_amount' => 100000, 'paid_amount' => 0,
    ]);

    return [$user, $orangTua, $siswa, $tagihan];
}

it('does not show another parent\'s tagihan in the rekap tagihan list', function () {
    [$userA, , , $tagihanA] = makeParentWithChild('A');
    [, , , $tagihanB] = makeParentWithChild('B');

    $response = $this->actingAs($userA)->get(route('keuangan.tagihan.index'));

    $response->assertOk();
    $response->assertSee($tagihanA->jenisTagihan->nama);
    $this->assertDatabaseMissing('tagihan', ['id' => $tagihanB->id, 'tagihable_id' => $tagihanA->tagihable_id]);
});

it('rejects wallet checkout for a tagihan belonging to another parent\'s child', function () {
    [$userA] = makeParentWithChild('A', walletBalance: 500000);
    [, , , $tagihanB] = makeParentWithChild('B');

    $this->actingAs($userA)->post(route('keuangan.checkout.wallet'), ['tagihan_ids' => [$tagihanB->id]]);

    $this->assertEquals(0, Pembayaran::where('siswa_id', $tagihanB->tagihable_id)->count());
});

it('rejects manual transfer checkout for a tagihan belonging to another parent\'s child', function () {
    Illuminate\Support\Facades\Storage::fake('public');
    [$userA] = makeParentWithChild('A');
    [, , , $tagihanB] = makeParentWithChild('B');

    $response = $this->actingAs($userA)->post(route('keuangan.checkout.transfer'), [
        'tagihan_ids' => [$tagihanB->id],
        'transfer_date' => now()->toDateString(),
        'transfer_proof' => Illuminate\Http\UploadedFile::fake()->image('bukti.jpg'),
    ]);

    $response->assertSessionHasErrors('tagihan_ids');
    $this->assertEquals(0, Pembayaran::where('siswa_id', $tagihanB->tagihable_id)->count());
});

it('blocks a parent from polling the status of another parent\'s pembayaran', function () {
    [$userA, , , $tagihanA] = makeParentWithChild('A');
    $this->actingAs($userA)->post(route('keuangan.checkout.va'), ['tagihan_ids' => [$tagihanA->id]]);
    $pembayaranA = Pembayaran::where('metode', 'va_bri')->firstOrFail();

    [$userB] = makeParentWithChild('B');

    $response = $this->actingAs($userB)->getJson(route('keuangan.checkout.status', $pembayaranA));

    $response->assertForbidden();
});

it('blocks a parent from viewing another parent\'s wallet success page', function () {
    [$userA, , , $tagihanA] = makeParentWithChild('A', walletBalance: 500000);
    $this->actingAs($userA)->post(route('keuangan.checkout.wallet'), ['tagihan_ids' => [$tagihanA->id]]);
    $pembayaranA = Pembayaran::where('metode', 'wallet_saldo')->firstOrFail();

    [$userB] = makeParentWithChild('B');

    $response = $this->actingAs($userB)->get(route('keuangan.checkout.sukses', $pembayaranA));

    $response->assertForbidden();
});
```

- [ ] **Step 2: Run the tests**

Run: `php artisan test tests/Feature/Keuangan/CheckoutAuthorizationTest.php`
Expected: PASS (5 tests). If any fails, the fix belongs in `CheckoutController` or `TagihanController` (tighten the authorization/scoping check that let the leak through) — fix it in this task, then re-run.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Keuangan/CheckoutAuthorizationTest.php
git commit -m "test(keuangan): add two-party cross-parent authorization regression suite for 6b"
```

(If Step 2 required a production-code fix, include the modified file(s) in this commit and describe the fix in the commit message instead of the message above.)

---

### Task 8: Playwright verification + scoped regression + full-suite gate

**Files:**
- Modify: `scripts/keuangan-6a-browser-check.mjs`

**Interfaces:**
- Consumes: the live dev server (`http://localhost:8000`), the demo account `ortu.demo@permatakraksaan.sch.id` / `password`.
- Produces: two new check functions appended to the existing script, invoked the same way as the existing `--check=` flag.

- [ ] **Step 1: Ensure the demo account has a tagihan + wallet balance fixture for this check**

Run this once against the real dev DB (not the test DB) via tinker, so the Playwright check below has something to click:

```bash
php artisan tinker --execute="
\$siswa = \App\Models\Siswa::where('nama_lengkap', 'Eliana Putri')->first();
\$siswa->wallet->update(['balance' => 500000]);
\$jenis = \App\Models\JenisTagihan::first();
\App\Models\Tagihan::updateOrCreate(
    ['tagihable_id' => \$siswa->id, 'tagihable_type' => \App\Models\Siswa::class, 'jenis_tagihan_id' => \$jenis->id, 'status' => 'belum_bayar'],
    ['total_tagihan' => 50000, 'net_amount' => 50000, 'paid_amount' => 0, 'jatuh_tempo' => now()->addDays(7)]
);
echo 'wallet balance: '.\$siswa->wallet->balance.' tagihan ready'.PHP_EOL;
"
```

Expected output: `wallet balance: 500000 tagihan ready`

- [ ] **Step 2: Add `checkTagihanAndWalletCheckout()` to the Playwright script**

Append to `scripts/keuangan-6a-browser-check.mjs` (match the file's existing `checkX(page)` function style and login flow exactly — read the file first to copy the existing login/navigation boilerplate rather than reinventing it):

```javascript
async function checkTagihanAndWalletCheckout(page) {
  await page.goto(`${BASE_URL}/keuangan/tagihan`);
  const firstCheckbox = page.locator('input[type="checkbox"]').first();
  await firstCheckbox.waitFor({ state: 'visible', timeout: 3000 });
  await firstCheckbox.check();

  const bayarButton = page.locator('a:has-text("Bayar Terpilih")');
  await bayarButton.waitFor({ state: 'visible', timeout: 3000 });
  await bayarButton.click();

  await page.waitForURL(/\/keuangan\/checkout/, { timeout: 5000 });

  const walletTab = page.locator('button:has-text("Saldo Wallet")');
  await walletTab.click();
  const walletSubmit = page.locator('form[action*="checkout/wallet"] button[type="submit"]');
  await walletSubmit.waitFor({ state: 'visible', timeout: 3000 });
  await walletSubmit.click();

  await page.waitForURL(/\/keuangan\/checkout\/\d+\/sukses/, { timeout: 5000 });
  const successMessage = page.locator('text=Pembayaran dari Saldo Wallet berhasil diproses.');
  await successMessage.waitFor({ state: 'visible', timeout: 3000 });
  console.log('[tagihan+wallet] tagihan list -> checkout tabs -> wallet payment succeeded: OK');
}
```

Add `checkTagihanAndWalletCheckout` to the script's existing dispatch block (mirror the `if (checkArg === 'all' || checkArg === 'dashboard')` pattern already in the file) under the flag name `tagihan-checkout`.

- [ ] **Step 3: Run the Playwright check against the live dev server**

Ensure `php artisan serve` (or Laragon's Apache/Nginx) is running on port 8000, then:

Run: `KEUANGAN_CHECK_BASE_URL=http://localhost:8000 node scripts/keuangan-6a-browser-check.mjs --check=tagihan-checkout`
Expected: `[tagihan+wallet] tagihan list -> checkout tabs -> wallet payment succeeded: OK`

- [ ] **Step 4: Run the scoped regression suite**

Run: `php artisan test tests/Feature/Keuangan/`
Expected: all tests pass (no failures). If any pre-existing Keuangan test now fails, it is a real regression from this plan — fix it before continuing.

- [ ] **Step 5: Run the full-suite as the final gate, in isolation**

Confirm no other `php artisan test` process is currently running (check running processes), then:

Run: `php artisan test`
Expected: only the established pre-existing baseline failures from Sub-project 6a's handoff log (`LembagaCrudTest`, `RoleBuilderTest` x4, `RoleFormAuditBannerTest` — 6 total), zero new failures. If the count or the specific failing tests differ from that baseline, investigate before proceeding — do not assume it is DB-race noise without re-running in isolation to confirm.

- [ ] **Step 6: Commit**

```bash
git add scripts/keuangan-6a-browser-check.mjs
git commit -m "test(keuangan): add tagihan-to-wallet-checkout Playwright check, completing 6b verification"
```

---

## After all tasks: handoff log

Write `.agents/logs/keuangan-06b-rekap-tagihan-checkout.md` following the exact structure of `.agents/logs/keuangan-06a-fondasi-dashboard.md` (status, what was built, task-by-task summary, process notes, final verification numbers, explicitly-out-of-scope items, open items deferred to 6c/6d — in particular re-surface 6a's still-unaddressed open items: notification panel not filtered to active child, no mark-as-read mechanism, `NotificationFeedResolver` namespace).
