# BRI VA Payment Schema & Service Scaffolding Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Prepare a gateway-agnostic database schema and service-layer abstraction for a future BRI Virtual Account (BRIVA/H2H) payment integration, without implementing the real BRI API calls (documentation/credentials are not available yet).

**Architecture:** A new `virtual_account` table (belongs to `Tagihan`) holds VA lifecycle data; `pembayaran` gains a `'gateway'` source and an external-reference column; `PembayaranService::catatPembayaran()` gets two new optional, backward-compatible parameters so a future gateway callback can reuse the exact same verified business logic (locking, duplicate-attempt guard, `tandaiLunas()`) that manual payments already use; a `PaymentGatewayInterface` + stub `BrivaGatewayService` sit ready to be filled in later, not wired into any route/controller/container binding yet.

**Tech Stack:** Laravel 12, Pest 4, MySQL (no `doctrine/dbal` installed — enum changes must use raw SQL, not `Schema::table()->change()`).

## Global Constraints

- Scope is `Tagihan` only — `Cicilan`/`SkemaCicilan` are NOT touched by this plan; installment payments stay transfer-manual only.
- `virtual_account` is a NEW table, not new columns on `tagihan`. One `tagihan` may have multiple `virtual_account` rows over time (regeneration on expiry) — no unique constraint on `tagihan_id`, only global uniqueness on `nomor_va`.
- `pembayaran.sumber` gains exactly one new enum value: `'gateway'`. `pembayaran.metode`'s existing `'va_bri'` value is NOT touched (already present from an earlier migration) — do not add or modify it.
- No `doctrine/dbal` is installed in this project (confirmed via `composer.json`) — any enum column modification MUST use `DB::statement('ALTER TABLE ... MODIFY COLUMN ...')`, never `Schema::table()->change()`.
- Zero changes to routes, controllers, views, or the service container (`AppServiceProvider` etc.) in this plan — this is backend scaffolding only. No "Bayar via VA" UI, no webhook route.
- `PaymentGatewayInterface` and `BrivaGatewayService` live under `app/Services/PaymentGateway/`. `BrivaGatewayService`'s two methods must both throw `RuntimeException` with a message containing the substring `"belum tersedia"` — this is asserted by a test and is the explicit "not implemented yet" contract.
- `PembayaranService::catatPembayaran()`'s existing 5 parameters and their order are NOT changed — the 2 new parameters (`string $metode = 'transfer_manual'`, `?string $referensiEksternal = null`) are appended at the end with defaults that reproduce today's exact behavior, so `Portal\TagihanController`'s 3 existing call sites need zero changes.

---

### Task 1: `virtual_account` table + `VirtualAccount` model

**Files:**
- Create: `database/migrations/2026_07_24_140000_create_virtual_account_table.php`
- Create: `app/Models/VirtualAccount.php`
- Modify: `app/Models/Tagihan.php` (add `virtualAccount()` relation)
- Test: `tests/Unit/VirtualAccountTest.php`

**Interfaces:**
- Produces: `VirtualAccount` model with `$fillable = ['tagihan_id', 'provider', 'nomor_va', 'status', 'kedaluwarsa_pada', 'referensi_eksternal', 'payload_permintaan', 'payload_respons']`, casts `kedaluwarsa_pada => 'datetime'`, `payload_permintaan => 'array'`, `payload_respons => 'array'`, relation `tagihan(): BelongsTo`. `Tagihan::virtualAccount(): HasMany`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/VirtualAccountTest.php`:

```php
<?php
// tests/Unit/VirtualAccountTest.php

use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\Tagihan;
use App\Models\VirtualAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function buatTagihanUntukVa(): Tagihan
{
    $lembaga = Lembaga::factory()->create();
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id]);

    return Tagihan::create([
        'pendaftaran_id' => $pendaftaran->id,
        'kategori' => 'pendaftaran',
        'total_tagihan' => 150000,
        'status' => 'belum_bayar',
    ]);
}

it('belongs to a tagihan and casts its json/datetime columns', function () {
    $tagihan = buatTagihanUntukVa();

    $va = VirtualAccount::create([
        'tagihan_id' => $tagihan->id,
        'provider' => 'bri',
        'nomor_va' => '12345678901234',
        'status' => 'aktif',
        'kedaluwarsa_pada' => '2026-08-01 00:00:00',
        'referensi_eksternal' => 'BRI-REF-001',
        'payload_permintaan' => ['nominal' => 150000],
        'payload_respons' => ['status' => 'success'],
    ]);

    expect($va->tagihan->is($tagihan))->toBeTrue();
    expect($va->kedaluwarsa_pada)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    expect($va->payload_permintaan)->toBe(['nominal' => 150000]);
    expect($va->payload_respons)->toBe(['status' => 'success']);
});

it('allows a tagihan to accumulate multiple virtual_account rows over time (regeneration history)', function () {
    $tagihan = buatTagihanUntukVa();

    VirtualAccount::create(['tagihan_id' => $tagihan->id, 'provider' => 'bri', 'nomor_va' => '111', 'status' => 'kedaluwarsa']);
    VirtualAccount::create(['tagihan_id' => $tagihan->id, 'provider' => 'bri', 'nomor_va' => '222', 'status' => 'aktif']);

    expect($tagihan->virtualAccount()->count())->toBe(2);
});

it('requires nomor_va to be globally unique', function () {
    $tagihanA = buatTagihanUntukVa();
    $tagihanB = buatTagihanUntukVa();
    VirtualAccount::create(['tagihan_id' => $tagihanA->id, 'provider' => 'bri', 'nomor_va' => 'SAMA-123', 'status' => 'aktif']);

    expect(fn () => VirtualAccount::create(['tagihan_id' => $tagihanB->id, 'provider' => 'bri', 'nomor_va' => 'SAMA-123', 'status' => 'aktif']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/VirtualAccountTest.php`
Expected: FAIL — table `virtual_account` doesn't exist / class `App\Models\VirtualAccount` not found.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_24_140000_create_virtual_account_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_account', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tagihan_id')->constrained('tagihan')->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('nomor_va', 30)->unique();
            $table->enum('status', ['aktif', 'kedaluwarsa', 'dipakai'])->default('aktif');
            $table->timestamp('kedaluwarsa_pada')->nullable();
            $table->string('referensi_eksternal', 100)->nullable();
            $table->json('payload_permintaan')->nullable();
            $table->json('payload_respons')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_account');
    }
};
```

- [ ] **Step 4: Create the `VirtualAccount` model**

Create `app/Models/VirtualAccount.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VirtualAccount extends Model
{
    protected $table = 'virtual_account';

    protected $fillable = [
        'tagihan_id', 'provider', 'nomor_va', 'status',
        'kedaluwarsa_pada', 'referensi_eksternal', 'payload_permintaan', 'payload_respons',
    ];

    protected function casts(): array
    {
        return [
            'kedaluwarsa_pada' => 'datetime',
            'payload_permintaan' => 'array',
            'payload_respons' => 'array',
        ];
    }

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(Tagihan::class);
    }
}
```

- [ ] **Step 5: Add the `virtualAccount()` relation to `Tagihan`**

No new import is needed in `app/Models/Tagihan.php` — `HasMany` is already imported at the top of the file (it backs the existing `pembayaran()` relation). Add this method right after the existing `pembayaran(): HasMany` method (around line 52):

```php
    public function virtualAccount(): HasMany
    {
        return $this->hasMany(VirtualAccount::class);
    }
```

- [ ] **Step 6: Run the migration and the test**

Run: `php artisan migrate` (applies to the dev DB) then `vendor/bin/pest tests/Unit/VirtualAccountTest.php`
Expected: migration runs with no errors; all 3 tests PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_24_140000_create_virtual_account_table.php app/Models/VirtualAccount.php app/Models/Tagihan.php tests/Unit/VirtualAccountTest.php
git commit -m "feat: add virtual_account table and model for future payment gateway VAs"
```

---

### Task 2: Extend `pembayaran` for gateway support (`sumber='gateway'` + `referensi_eksternal`)

**Files:**
- Create: `database/migrations/2026_07_24_140100_add_gateway_support_to_pembayaran_table.php`
- Modify: `app/Models/Pembayaran.php`
- Test: `tests/Unit/PembayaranGatewayColumnsTest.php`

**Interfaces:**
- Consumes: nothing from Task 1.
- Produces: `pembayaran.sumber` accepts `'gateway'` as a valid value (in addition to the existing `'calon_siswa'`/`'admin'`); `pembayaran.referensi_eksternal` (nullable string) column; `Pembayaran::$fillable` includes `'referensi_eksternal'`. Task 3 relies on both.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/PembayaranGatewayColumnsTest.php`:

```php
<?php
// tests/Unit/PembayaranGatewayColumnsTest.php

use App\Models\Lembaga;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\Tagihan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('accepts sumber=gateway and stores referensi_eksternal', function () {
    $lembaga = Lembaga::factory()->create();
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tagihan = Tagihan::create([
        'pendaftaran_id' => $pendaftaran->id,
        'kategori' => 'pendaftaran',
        'total_tagihan' => 150000,
        'status' => 'belum_bayar',
    ]);

    $pembayaran = Pembayaran::create([
        'tagihan_id' => $tagihan->id,
        'sumber' => 'gateway',
        'metode' => 'va_bri',
        'status' => 'lunas',
        'referensi_eksternal' => 'BRI-TRX-999',
    ]);

    expect($pembayaran->fresh()->sumber)->toBe('gateway');
    expect($pembayaran->fresh()->referensi_eksternal)->toBe('BRI-TRX-999');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/PembayaranGatewayColumnsTest.php`
Expected: FAIL — either a `QueryException` (enum truncation on `sumber='gateway'`) or `referensi_eksternal` not in `$fillable` (mass-assignment silently dropped, so `expect(...)->toBe('BRI-TRX-999')` fails).

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_24_140100_add_gateway_support_to_pembayaran_table.php`. This project has NO `doctrine/dbal` installed, so the `sumber` enum change MUST use raw SQL, not `Schema::table()->change()`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pembayaran', function (Blueprint $table) {
            $table->string('referensi_eksternal')->nullable()->after('metode');
        });

        DB::statement("ALTER TABLE pembayaran MODIFY COLUMN sumber ENUM('calon_siswa', 'admin', 'gateway') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE pembayaran MODIFY COLUMN sumber ENUM('calon_siswa', 'admin') NOT NULL");

        Schema::table('pembayaran', function (Blueprint $table) {
            $table->dropColumn('referensi_eksternal');
        });
    }
};
```

- [ ] **Step 4: Add `referensi_eksternal` to `Pembayaran::$fillable`**

In `app/Models/Pembayaran.php`, change:

```php
    protected $fillable = [
        'tagihan_id', 'cicilan_id', 'sumber', 'metode', 'file_path',
        'status', 'catatan_verifikasi', 'diverifikasi_oleh_user_id', 'diverifikasi_pada',
    ];
```

to:

```php
    protected $fillable = [
        'tagihan_id', 'cicilan_id', 'sumber', 'metode', 'file_path',
        'status', 'catatan_verifikasi', 'diverifikasi_oleh_user_id', 'diverifikasi_pada', 'referensi_eksternal',
    ];
```

- [ ] **Step 5: Run the migration and the test**

Run: `php artisan migrate` then `vendor/bin/pest tests/Unit/PembayaranGatewayColumnsTest.php`
Expected: migration runs with no errors; test PASSES.

- [ ] **Step 6: Run the full existing Pembayaran-related test suite to confirm no regression**

Run: `vendor/bin/pest tests/Unit/PembayaranServiceTest.php tests/Feature/Admin/CatatManualPembayaranTest.php tests/Feature/Admin/VerifikasiPembayaranTest.php tests/Feature/Portal/TagihanPembayaranTest.php`
Expected: all PASS (this migration is purely additive — no existing column/enum value was removed).

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_24_140100_add_gateway_support_to_pembayaran_table.php app/Models/Pembayaran.php tests/Unit/PembayaranGatewayColumnsTest.php
git commit -m "feat: add gateway payment source and external reference column to pembayaran"
```

---

### Task 3: Generalize `PembayaranService::catatPembayaran()` for gateway-originated payments

**Files:**
- Modify: `app/Services/PembayaranService.php`
- Test: `tests/Unit/PembayaranServiceTest.php`

**Interfaces:**
- Consumes: `pembayaran.sumber='gateway'` and `pembayaran.referensi_eksternal` (Task 2).
- Produces: `PembayaranService::catatPembayaran(?Tagihan $tagihan, ?Cicilan $cicilan, string $sumber, ?string $filePath, ?int $userId, string $metode = 'transfer_manual', ?string $referensiEksternal = null): Pembayaran` — the two new trailing parameters are what Task 4's future real gateway implementation (not part of this plan) will pass. `$sumber === 'gateway'` behaves like `$sumber === 'admin'` for the immediate-`lunas` fast path.

- [ ] **Step 1: Write the failing tests**

Add these two `it()` blocks to the end of `tests/Unit/PembayaranServiceTest.php` (the file already has the `buatTagihanDaftarUlangUntukPembayaran()` helper at the top — reuse it, no new helper needed):

```php
it('records a gateway-confirmed payment as immediately lunas, with its metode and referensi_eksternal stored', function () {
    $tagihan = buatTagihanDaftarUlangUntukPembayaran(500000);

    $pembayaran = app(PembayaranService::class)->catatPembayaran(
        $tagihan, null, 'gateway', null, null, 'va_bri', 'BRI-TRX-001'
    );

    expect($pembayaran->status)->toBe('lunas');
    expect($pembayaran->metode)->toBe('va_bri');
    expect($pembayaran->referensi_eksternal)->toBe('BRI-TRX-001');
    expect($tagihan->fresh()->status)->toBe('lunas');
});

it('still defaults metode to transfer_manual and referensi_eksternal to null when the new params are omitted', function () {
    $tagihan = buatTagihanDaftarUlangUntukPembayaran(500000);

    $pembayaran = app(PembayaranService::class)->catatPembayaran($tagihan, null, 'calon_siswa', 'bukti/x.pdf', null);

    expect($pembayaran->metode)->toBe('transfer_manual');
    expect($pembayaran->referensi_eksternal)->toBeNull();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/PembayaranServiceTest.php`
Expected: the first new test FAILS (either a `\TypeError`/`\ArgumentCountError` for the extra arguments the current signature doesn't accept, or `$pembayaran->status` is `menunggu_verifikasi` not `lunas` since `sumber='gateway'` isn't yet special-cased). The second new test currently PASSES already (today's hardcoded behavior already matches) — that's fine, it's here as a regression guard for after the signature change.

- [ ] **Step 3: Update `PembayaranService::catatPembayaran()`**

In `app/Services/PembayaranService.php`, change the method signature (currently):

```php
    public function catatPembayaran(?Tagihan $tagihan, ?Cicilan $cicilan, string $sumber, ?string $filePath, ?int $userId): Pembayaran
```

to:

```php
    public function catatPembayaran(
        ?Tagihan $tagihan,
        ?Cicilan $cicilan,
        string $sumber,
        ?string $filePath,
        ?int $userId,
        string $metode = 'transfer_manual',
        ?string $referensiEksternal = null,
    ): Pembayaran
```

Inside the method body, change this line:

```php
            $statusAwal = $sumber === 'admin' ? 'lunas' : 'menunggu_verifikasi';
```

to:

```php
            $statusAwal = in_array($sumber, ['admin', 'gateway'], true) ? 'lunas' : 'menunggu_verifikasi';
```

And change the `Pembayaran::create([...])` call from:

```php
            $pembayaran = Pembayaran::create([
                'tagihan_id' => $tagihan?->id,
                'cicilan_id' => $cicilan?->id,
                'sumber' => $sumber,
                'metode' => 'transfer_manual',
                'file_path' => $filePath,
                'status' => $statusAwal,
                'diverifikasi_oleh_user_id' => $sumber === 'admin' ? $userId : null,
                'diverifikasi_pada' => $sumber === 'admin' ? now() : null,
            ]);
```

to:

```php
            $pembayaran = Pembayaran::create([
                'tagihan_id' => $tagihan?->id,
                'cicilan_id' => $cicilan?->id,
                'sumber' => $sumber,
                'metode' => $metode,
                'file_path' => $filePath,
                'status' => $statusAwal,
                'referensi_eksternal' => $referensiEksternal,
                'diverifikasi_oleh_user_id' => $sumber === 'admin' ? $userId : null,
                'diverifikasi_pada' => $sumber === 'admin' ? now() : null,
            ]);
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/PembayaranServiceTest.php`
Expected: all tests PASS, including all pre-existing ones (the 2 new parameters are optional with defaults that preserve today's exact behavior for every existing call).

- [ ] **Step 5: Run the full regression set for anything that calls `catatPembayaran()`**

Run: `vendor/bin/pest tests/Feature/Admin/CatatManualPembayaranTest.php tests/Feature/Admin/VerifikasiPembayaranTest.php tests/Feature/Portal/TagihanPembayaranTest.php tests/Feature/Spmb/TagihanPendaftaranHookTest.php`
Expected: all PASS — `Portal\TagihanController`'s 3 call sites (`bayarLunas`, `bayarCicilan`) never pass the 2 new trailing parameters, so they get the exact same defaults as before.

- [ ] **Step 6: Commit**

```bash
git add app/Services/PembayaranService.php tests/Unit/PembayaranServiceTest.php
git commit -m "feat: let PembayaranService::catatPembayaran accept a gateway-originated payment"
```

---

### Task 4: `PaymentGatewayInterface` + stub `BrivaGatewayService`

**Files:**
- Create: `app/Services/PaymentGateway/PaymentGatewayInterface.php`
- Create: `app/Services/PaymentGateway/BrivaGatewayService.php`
- Test: `tests/Unit/BrivaGatewayServiceTest.php`

**Interfaces:**
- Consumes: `App\Models\Tagihan`, `App\Models\VirtualAccount` (Task 1) as type hints only — no actual database interaction in this stub.
- Produces: `App\Services\PaymentGateway\PaymentGatewayInterface` with `buatVirtualAccount(Tagihan $tagihan): VirtualAccount` and `tanganiNotifikasi(array $payload): void`. `App\Services\PaymentGateway\BrivaGatewayService implements PaymentGatewayInterface`. Neither class is bound in any service provider or called from any controller/route — this plan does not wire them in.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/BrivaGatewayServiceTest.php`:

```php
<?php
// tests/Unit/BrivaGatewayServiceTest.php

use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\Tagihan;
use App\Services\PaymentGateway\BrivaGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('throws a clear not-yet-available exception from buatVirtualAccount', function () {
    $lembaga = Lembaga::factory()->create();
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tagihan = Tagihan::create([
        'pendaftaran_id' => $pendaftaran->id,
        'kategori' => 'pendaftaran',
        'total_tagihan' => 150000,
        'status' => 'belum_bayar',
    ]);

    expect(fn () => (new BrivaGatewayService())->buatVirtualAccount($tagihan))
        ->toThrow(RuntimeException::class, 'belum tersedia');
});

it('throws a clear not-yet-available exception from tanganiNotifikasi', function () {
    expect(fn () => (new BrivaGatewayService())->tanganiNotifikasi(['foo' => 'bar']))
        ->toThrow(RuntimeException::class, 'belum tersedia');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/BrivaGatewayServiceTest.php`
Expected: FAIL — class `App\Services\PaymentGateway\BrivaGatewayService` not found.

- [ ] **Step 3: Create the interface**

Create `app/Services/PaymentGateway/PaymentGatewayInterface.php`:

```php
<?php

namespace App\Services\PaymentGateway;

use App\Models\Tagihan;
use App\Models\VirtualAccount;

interface PaymentGatewayInterface
{
    /**
     * Membuat (atau mengambil yang sudah aktif) Virtual Account untuk sebuah tagihan.
     */
    public function buatVirtualAccount(Tagihan $tagihan): VirtualAccount;

    /**
     * Memproses payload notifikasi/webhook mentah dari gateway: verifikasi signature,
     * cocokkan ke VirtualAccount yang relevan, lalu catat pembayarannya lewat
     * PembayaranService::catatPembayaran() (sumber='gateway'). Tidak mengembalikan
     * apa pun — kegagalan dilempar sebagai exception agar endpoint webhook bisa
     * merespons kode HTTP yang sesuai ke pemanggilnya.
     */
    public function tanganiNotifikasi(array $payload): void;
}
```

- [ ] **Step 4: Create the stub implementation**

Create `app/Services/PaymentGateway/BrivaGatewayService.php`:

```php
<?php

namespace App\Services\PaymentGateway;

use App\Models\Tagihan;
use App\Models\VirtualAccount;
use RuntimeException;

/**
 * Placeholder — integrasi BRI API (BRIVA/H2H) yang sesungguhnya BELUM tersedia.
 * Institusi sudah terdaftar sebagai partner BRI, tapi dokumentasi & kredensial
 * resmi (format endpoint, autentikasi, format payload notifikasi/signature) perlu
 * diperoleh ulang sebelum kelas ini bisa diimplementasikan sungguhan. Kedua method
 * sengaja melempar exception yang jelas, bukan diam-diam melakukan sesuatu yang
 * salah, supaya pemanggilan tidak sengaja langsung ketahuan gagal saat development.
 */
class BrivaGatewayService implements PaymentGatewayInterface
{
    private const PESAN_BELUM_TERSEDIA = 'Integrasi BRI API belum tersedia — menunggu dokumentasi/kredensial resmi dari BRI.';

    public function buatVirtualAccount(Tagihan $tagihan): VirtualAccount
    {
        throw new RuntimeException(self::PESAN_BELUM_TERSEDIA);
    }

    public function tanganiNotifikasi(array $payload): void
    {
        throw new RuntimeException(self::PESAN_BELUM_TERSEDIA);
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/BrivaGatewayServiceTest.php`
Expected: both tests PASS.

- [ ] **Step 6: Run the full test suite to confirm no regressions anywhere**

Run: `vendor/bin/pest`
Expected: all tests PASS (this task adds new, self-contained files — nothing in the codebase references them yet).

- [ ] **Step 7: Commit**

```bash
git add app/Services/PaymentGateway/PaymentGatewayInterface.php app/Services/PaymentGateway/BrivaGatewayService.php tests/Unit/BrivaGatewayServiceTest.php
git commit -m "feat: add PaymentGatewayInterface and a stub BrivaGatewayService"
```

---

## Post-implementation note (not a task — informational only)

This plan intentionally stops short of a working integration. When the real BRI API documentation/credentials are obtained, the follow-up work is: implement `BrivaGatewayService`'s two methods for real, add a webhook route + controller that calls `tanganiNotifikasi()`, add a "Bayar via VA" button to `resources/views/portal/tagihan/index.blade.php`, and bind `PaymentGatewayInterface` to `BrivaGatewayService` in a service provider. None of that is in scope here — brainstorm it fresh once the docs are in hand, per `[[project_spmb_account_first_redesign]]` memory guidance.
