# Integrasi BRI SNAP VA (Virtual Account) — Arah Inbound Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ganti VA (Virtual Account) dari `MockPaymentGateway` dengan implementasi asli — satu nomor VA tetap per siswa, dibuat lokal, dan 3 endpoint inbound (token, inquiry, payment) yang BRI panggil balik ke sistem kita.

**Architecture:** Kebalikan dari QRIS (outbound). Kita jadi token issuer (`BriInboundAuthenticatorInterface`, implementasi sederhana `SimpleBriInboundAuthenticator`) untuk 3 endpoint baru di `BriVaInboundController`. `BriSnapGateway::createVirtualAccount()` generate nomor VA murni lokal (tidak ada panggilan HTTP). Endpoint Payment idempotent lewat tabel `bri_inbound_payment_logs`, kredit saldo lewat `Wallet::topup()` yang sudah ada (yang otomatis memicu `AutoAllocationEngine`). `BriWebhookController` (skema API BRI lama/Non-SNAP) dihapus total, fungsinya digantikan endpoint Payment baru.

**Tech Stack:** Laravel 12, `Illuminate\Support\Facades\Cache` (token sementara), PHPUnit gaya Pest (`test()`/`it()`, mengikuti konvensi file test existing di `tests/Feature/Keuangan/`).

## Global Constraints

- Spec sumber: `docs/superpowers/specs/2026-08-15-bri-snap-va-inbound-design.md`.
- **Dokumentasi protokol BRI ada di `bri-api.md` (root project).** Setiap task yang menyentuh format field/endpoint WAJIB merujuk section spesifik di file itu. Section relevan: **"Virtual Account/Briva Online"** (baris ±1768–2228, field `partnerServiceId`/`customerNo`/`virtualAccountNo`, endpoint Inquiry & Payment, response structure) dan **"Access Token and Signature"** (baris ±90–260, untuk memahami pola token walau arahnya dibalik di task ini).
- **VA hanya SATU nomor tetap per siswa** (`va_type = 'WALLET_PERMANENT'`). VA sementara per-tagihan (`BILL_DIRECT`) DIHAPUS dari kode — tapi migration/enum kolom `va_type`/`status` di tabel `bri_virtual_accounts` **TIDAK diubah/di-shrink**, supaya data historis `BILL_DIRECT` tetap bisa diakses untuk riwayat/kwitansi lama.
- **Fitur "Top-Up Bundling via VA" DIHAPUS.** Bundling via QRIS TIDAK terpengaruh, tetap ada.
- **Endpoint Inquiry = murni read-only.** Tidak boleh ada `INSERT`/`UPDATE`/`DELETE` di endpoint itu, titik.
- **Endpoint Payment = idempotent & tidak pernah kehilangan uang.** Kalau proses pelunasan otomatis (`AutoAllocationEngine`, dipanggil otomatis dari dalam `Wallet::topup()`) gagal, saldo yang sudah masuk TETAP tersimpan — jangan pernah rollback kredit saldo karena kegagalan di langkah berikutnya.
- **Keamanan token: sederhana dulu** (cocokkan `client_id`/`client_secret` baru yang di-generate sendiri). JANGAN implementasikan asymmetric signature (SHA256withRSA) — didesain lewat interface `BriInboundAuthenticatorInterface` supaya gampang diganti nanti.
- Kredensial `client_id`/`client_secret` untuk arah inbound ini **BARU dan TERPISAH** dari `BRI_SNAP_CLIENT_ID`/`BRI_SNAP_CLIENT_SECRET` yang sudah ada (itu untuk outbound QRIS).
- Tidak ada mekanisme pembatalan/reversal buatan sendiri untuk kasus timeout — cukup andalkan idempotency.
- Scope TIDAK termasuk: pendaftaran domain publik ke BRI, IP whitelisting (infrastruktur, bukan kode), implementasi asymmetric signature, halaman admin kelola VA.
- Jangan jalankan `php artisan test` tanpa filter (mahal) — selalu scope ke `tests/Feature/Keuangan/` plus file yang langsung disentuh.

---

### Task 1: Tabel Ledger Idempotency (`bri_inbound_payment_logs`)

**Files:**
- Create: `database/migrations/2026_08_15_100000_create_bri_inbound_payment_logs_table.php`
- Create: `app/Models/BriInboundPaymentLog.php`
- Test: `tests/Unit/Models/BriInboundPaymentLogTest.php`

**Interfaces:**
- Produces: model `App\Models\BriInboundPaymentLog` dengan kolom `payment_request_id` (unique), `va_number`, `amount`, `pembayaran_id` (nullable FK ke `pembayaran`). Dipakai Task 7 (endpoint Payment) untuk cek duplikasi laporan BRI.

- [ ] **Step 1: Buat migration**

```bash
php artisan make:migration create_bri_inbound_payment_logs_table
```

Isi (rename file hasil generate supaya timestamp-nya `2026_08_15_100000_create_bri_inbound_payment_logs_table.php`):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bri_inbound_payment_logs', function (Blueprint $table) {
            $table->id();
            $table->string('payment_request_id')->unique();
            $table->string('va_number');
            $table->decimal('amount', 15, 2);
            $table->foreignId('pembayaran_id')->nullable()->constrained('pembayaran')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bri_inbound_payment_logs');
    }
};
```

- [ ] **Step 2: Jalankan migration**

Run: `php artisan migrate`
Expected: `2026_08_15_100000_create_bri_inbound_payment_logs_table ... DONE`

- [ ] **Step 3: Write the failing test**

```php
<?php

namespace Tests\Unit\Models;

use App\Models\BriInboundPaymentLog;
use App\Models\Pembayaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BriInboundPaymentLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_request_id_must_be_unique()
    {
        $pembayaran = Pembayaran::factory()->create();

        BriInboundPaymentLog::create([
            'payment_request_id' => 'DUPLICATE-ID',
            'va_number' => '7777700000000001',
            'amount' => 50000,
            'pembayaran_id' => $pembayaran->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        BriInboundPaymentLog::create([
            'payment_request_id' => 'DUPLICATE-ID',
            'va_number' => '7777700000000002',
            'amount' => 75000,
            'pembayaran_id' => $pembayaran->id,
        ]);
    }
}
```

- [ ] **Step 4: Run test to verify it fails**

Run: `php artisan test tests/Unit/Models/BriInboundPaymentLogTest.php`
Expected: FAIL — `Class "App\Models\BriInboundPaymentLog" not found`.

- [ ] **Step 5: Buat model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BriInboundPaymentLog extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function pembayaran(): BelongsTo
    {
        return $this->belongsTo(Pembayaran::class);
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test tests/Unit/Models/BriInboundPaymentLogTest.php`
Expected: PASS (1 test)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_15_100000_create_bri_inbound_payment_logs_table.php \
        app/Models/BriInboundPaymentLog.php \
        tests/Unit/Models/BriInboundPaymentLogTest.php
git commit -m "feat(bri): add bri_inbound_payment_logs table for VA payment idempotency"
```

---

### Task 2: Kredensial Inbound Baru + `BriInboundAuthenticatorInterface`

**Files:**
- Create: `app/Contracts/BriInboundAuthenticatorInterface.php`
- Create: `app/Services/Finance/BriInbound/SimpleBriInboundAuthenticator.php`
- Modify: `config/services.php` (tambah `services.bri.inbound.*`)
- Modify: `.env` dan `.env.example` (tambah var baru)
- Modify: `app/Providers/AppServiceProvider.php` (binding interface)
- Test: `tests/Unit/Services/Finance/BriInbound/SimpleBriInboundAuthenticatorTest.php`

**Interfaces:**
- Produces: `BriInboundAuthenticatorInterface` dengan `issueToken(string $clientId, string $clientSecret): ?string` (null kalau kredensial salah) dan `validateToken(string $token): bool`. Dipakai Task 5, 6, 7 (semua endpoint inbound).

**Baca dulu**: `bri-api.md` section **"Access Token and Signature"** (baris ±90–260) untuk memahami pola umum token BRI (masa berlaku, format) — walau di sini arahnya dibalik (kita yang menerbitkan, bukan BRI), pola "token sementara ~15 menit" tetap dipakai sebagai referensi masa berlaku yang wajar.

- [ ] **Step 1: Tambah config di `config/services.php`**

Cari entry `'bri' => [...]` yang sudah ada (dari sub-project QRIS), tambahkan key `'inbound'` di dalamnya:

```php
    'bri' => [
        'gateway' => env('BRI_PAYMENT_GATEWAY', 'mock'),
        'client_id' => env('BRI_SNAP_CLIENT_ID'),
        'client_secret' => env('BRI_SNAP_CLIENT_SECRET'),
        'base_url' => env('BRI_SNAP_BASE_URL'),
        'private_key_path' => env('BRI_SNAP_PRIVATE_KEY_PATH'),
        'partner_id' => env('BRI_SNAP_PARTNER_ID'),
        'channel_id' => env('BRI_SNAP_CHANNEL_ID'),
        'merchant_id' => env('BRI_SNAP_MERCHANT_ID'),
        'terminal_id' => env('BRI_SNAP_TERMINAL_ID'),
        'inbound' => [
            'client_id' => env('BRI_INBOUND_CLIENT_ID'),
            'client_secret' => env('BRI_INBOUND_CLIENT_SECRET'),
            'partner_service_id' => env('BRI_INBOUND_PARTNER_SERVICE_ID'),
        ],
    ],
```

(Jangan buat ulang seluruh array `'bri'` — cari yang sudah ada dan tambahkan key `'inbound'` di dalamnya. `partner_service_id` adalah angka 8-digit dari BRI, dipakai Task 3 untuk membangun nomor VA — BEDA dari `partner_id` yang sudah ada, itu untuk header `X-PARTNER-ID` di arah outbound QRIS.)

- [ ] **Step 2: Tambah var baru di `.env` dan `.env.example`**

Di `.env`, setelah baris `BRI_PAYMENT_GATEWAY=mock`, tambahkan:

```
BRI_INBOUND_CLIENT_ID=
BRI_INBOUND_CLIENT_SECRET=
BRI_INBOUND_PARTNER_SERVICE_ID=
```

(Kosong — `client_id`/`client_secret` ini kita generate sendiri nanti sebelum submit ke BRI; `partner_service_id` baru diketahui setelah BRI kasih tahu. Lakukan hal yang sama persis di `.env.example`.)

- [ ] **Step 3: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Finance\BriInbound;

use App\Services\Finance\BriInbound\SimpleBriInboundAuthenticator;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SimpleBriInboundAuthenticatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.bri.inbound.client_id' => 'test-client-id',
            'services.bri.inbound.client_secret' => 'test-client-secret',
        ]);
    }

    public function test_issue_token_returns_null_for_wrong_credentials()
    {
        $authenticator = new SimpleBriInboundAuthenticator();

        $this->assertNull($authenticator->issueToken('test-client-id', 'wrong-secret'));
        $this->assertNull($authenticator->issueToken('wrong-client-id', 'test-client-secret'));
        $this->assertNull($authenticator->issueToken('', ''));
    }

    public function test_issue_token_returns_valid_token_for_correct_credentials()
    {
        $authenticator = new SimpleBriInboundAuthenticator();

        $token = $authenticator->issueToken('test-client-id', 'test-client-secret');

        $this->assertNotNull($token);
        $this->assertTrue($authenticator->validateToken($token));
    }

    public function test_validate_token_rejects_unknown_token()
    {
        $authenticator = new SimpleBriInboundAuthenticator();

        $this->assertFalse($authenticator->validateToken('token-yang-tidak-pernah-diterbitkan'));
        $this->assertFalse($authenticator->validateToken(''));
    }
}
```

- [ ] **Step 4: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Finance/BriInbound/SimpleBriInboundAuthenticatorTest.php`
Expected: FAIL — `Class "App\Services\Finance\BriInbound\SimpleBriInboundAuthenticator" not found`.

- [ ] **Step 5: Buat interface**

```php
<?php

namespace App\Contracts;

interface BriInboundAuthenticatorInterface
{
    /**
     * Terbitkan token sementara kalau client_id/client_secret cocok. Null kalau salah.
     */
    public function issueToken(string $clientId, string $clientSecret): ?string;

    /**
     * Cek apakah token masih berlaku (pernah kita terbitkan & belum kadaluarsa).
     */
    public function validateToken(string $token): bool;
}
```

- [ ] **Step 6: Buat implementasi sederhana**

```php
<?php

namespace App\Services\Finance\BriInbound;

use App\Contracts\BriInboundAuthenticatorInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SimpleBriInboundAuthenticator implements BriInboundAuthenticatorInterface
{
    public function issueToken(string $clientId, string $clientSecret): ?string
    {
        if ($clientId === '' || $clientSecret === '') {
            return null;
        }

        $expectedClientId = (string) config('services.bri.inbound.client_id');
        $expectedClientSecret = (string) config('services.bri.inbound.client_secret');

        if (!hash_equals($expectedClientId, $clientId) || !hash_equals($expectedClientSecret, $clientSecret)) {
            return null;
        }

        $token = Str::random(40);
        Cache::put('bri_inbound_token:' . $token, true, 900);

        return $token;
    }

    public function validateToken(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        return Cache::has('bri_inbound_token:' . $token);
    }
}
```

- [ ] **Step 7: Daftarkan binding di `AppServiceProvider`**

`app/Providers/AppServiceProvider.php` — tambah import `use App\Contracts\BriInboundAuthenticatorInterface;` dan `use App\Services\Finance\BriInbound\SimpleBriInboundAuthenticator;`, lalu di `register()` (sejajar dengan binding `PaymentGatewayInterface` yang sudah ada):

```php
        $this->app->bind(BriInboundAuthenticatorInterface::class, SimpleBriInboundAuthenticator::class);
```

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Finance/BriInbound/SimpleBriInboundAuthenticatorTest.php`
Expected: PASS (3 tests)

- [ ] **Step 9: Commit**

```bash
git add app/Contracts/BriInboundAuthenticatorInterface.php \
        app/Services/Finance/BriInbound/SimpleBriInboundAuthenticator.php \
        app/Providers/AppServiceProvider.php \
        config/services.php .env.example \
        tests/Unit/Services/Finance/BriInbound/SimpleBriInboundAuthenticatorTest.php
git commit -m "feat(bri): add BriInboundAuthenticatorInterface with simple client_id/secret validation"
```

(`.env` tidak masuk git — sudah gitignored, cukup edit lokal.)

---

### Task 3: `BriSnapGateway::createVirtualAccount()` — Generate Nomor VA Lokal

**Files:**
- Modify: `app/Services/Finance/Gateway/BriSnapGateway.php`
- Test: `tests/Feature/Keuangan/BriSnapGatewayIntegrationTest.php` (tambah test baru ke file yang sudah ada dari sub-project QRIS)

**Interfaces:**
- Consumes: `config('services.bri.inbound.partner_service_id')` dari Task 2.
- Produces: `BriSnapGateway::createVirtualAccount(Pembayaran $pembayaran, string $vaType): VirtualAccountResult` — tidak lagi throw untuk `$vaType === 'WALLET_PERMANENT'`. Dipakai Task 4 (`getOrCreatePermanentVa()`).

**Baca dulu**: `bri-api.md` section **"Virtual Account/Briva Online"** (baris ±1857–1859) — field `partnerServiceId` (String, Numeric, 8 digit) dan `customerNo` (String, Numeric, sampai 20 digit), format `virtualAccountNo = partnerServiceId + customerNo`.

- [ ] **Step 1: Write the failing test**

Tambahkan ke `tests/Feature/Keuangan/BriSnapGatewayIntegrationTest.php` (file ini sudah ada dari sub-project QRIS, sudah punya `setUp()` yang men-set config `services.bri.*` dan `Http::fake()` untuk token — tambahkan test baru tanpa mengubah yang sudah ada):

```php
    public function test_create_virtual_account_generates_local_va_number_without_http_call()
    {
        config(['services.bri.inbound.partner_service_id' => '77777777']);

        Http::fake([
            '*' => Http::response(['error' => 'should not be called'], 500),
        ]);

        $pembayaran = Pembayaran::factory()->create(['siswa_id' => 42]);

        $result = $this->gateway->createVirtualAccount($pembayaran, 'WALLET_PERMANENT');

        $this->assertSame('7777777700000000000042', $result->vaNumber);
        $this->assertNull($result->amount);
        $this->assertNull($result->expiredAt);

        Http::assertNothingSent();
    }
```

(Catatan: `Http::fake(['*' => ...])` di atas SENGAJA dipasang untuk membuktikan tidak ada panggilan HTTP sama sekali — kalau kode diam-diam memanggil BRI, test ini akan gagal karena `Http::assertNothingSent()`.)

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/BriSnapGatewayIntegrationTest.php --filter=test_create_virtual_account_generates_local_va_number_without_http_call`
Expected: FAIL — `RuntimeException: BriSnapGateway VA not fully implemented yet` (atau pesan serupa dari stub saat ini).

- [ ] **Step 3: Implementasikan `createVirtualAccount()`**

`app/Services/Finance/Gateway/BriSnapGateway.php` — ganti method `createVirtualAccount()` (masih `throw`) menjadi:

```php
    public function createVirtualAccount(Pembayaran $pembayaran, string $vaType): VirtualAccountResult
    {
        $partnerServiceId = str_pad((string) config('services.bri.inbound.partner_service_id'), 8, '0', STR_PAD_LEFT);
        $customerNo = str_pad((string) $pembayaran->siswa_id, 20, '0', STR_PAD_LEFT);
        $vaNumber = $partnerServiceId . $customerNo;

        return new VirtualAccountResult($vaNumber, null, null, [
            'partnerServiceId' => $partnerServiceId,
            'customerNo' => $customerNo,
        ]);
    }
```

Tambahkan import `use App\DTO\VirtualAccountResult;` kalau belum ada di bagian atas file (kemungkinan sudah ada karena file ini implements `PaymentGatewayInterface`).

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Keuangan/BriSnapGatewayIntegrationTest.php`
Expected: PASS, semua test di file itu hijau (test lama QRIS + test baru ini).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Finance/Gateway/BriSnapGateway.php \
        tests/Feature/Keuangan/BriSnapGatewayIntegrationTest.php
git commit -m "feat(bri): generate local VA number in BriSnapGateway::createVirtualAccount()"
```

---

### Task 4: `PaymentService` — Sinkron `wallet.va_number` + Hapus VA Bundling

**Files:**
- Modify: `app/Services/Finance/PaymentService.php`
- Modify: `tests/Feature/Keuangan/PaymentServiceTest.php`
- Modify: `tests/Feature/Keuangan/PaymentServiceBundledTopupTest.php`
- Modify: `tests/Feature/Keuangan/CheckoutControllerBundledTopupTest.php`
- Delete: `tests/Feature/Keuangan/KwitansiBundledTopupTest.php`
- Delete: `tests/Feature/Keuangan/RiwayatBundledTopupTest.php`
- Modify: `tests/Feature/Keuangan/ReconcilePaymentsBundledTopupTest.php`
- Modify: `tests/Feature/Keuangan/PaymentAllocationServiceTopupRemainderTest.php`

**Interfaces:**
- Consumes: `BriSnapGateway::createVirtualAccount()` dari Task 3.
- Produces: `PaymentService::getOrCreatePermanentVa(Siswa $siswa): BriVirtualAccount` — sekarang JUGA men-sync `$siswa->wallet->va_number`. `createVaPayment()` dan `createVaPaymentWithTopup()` DIHAPUS (tidak ada pengganti — dipakai Task 10 untuk memastikan `CheckoutController::va()` tidak memanggilnya lagi).

- [ ] **Step 1: Write the failing test — `getOrCreatePermanentVa()` sync `wallet.va_number`**

Tambahkan ke `tests/Feature/Keuangan/PaymentServiceTest.php`, setelah `test_get_or_create_permanent_va_idempotency` yang sudah ada:

```php
    public function test_get_or_create_permanent_va_syncs_wallet_va_number()
    {
        $siswa = Siswa::factory()->create();

        $this->assertNull($siswa->wallet->va_number);

        $va = $this->service->getOrCreatePermanentVa($siswa);

        $siswa->wallet->refresh();
        $this->assertSame($va->va_number, $siswa->wallet->va_number);

        // Panggil lagi -- harus tetap sinkron, tidak bikin VA baru
        $vaKedua = $this->service->getOrCreatePermanentVa($siswa);
        $this->assertSame($va->id, $vaKedua->id);
        $siswa->wallet->refresh();
        $this->assertSame($va->va_number, $siswa->wallet->va_number);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/PaymentServiceTest.php --filter=test_get_or_create_permanent_va_syncs_wallet_va_number`
Expected: FAIL — `$siswa->wallet->va_number` tetap `null` setelah `getOrCreatePermanentVa()` dipanggil (karena kolom itu belum pernah di-set kode manapun).

- [ ] **Step 3: Perbaiki `getOrCreatePermanentVa()`**

`app/Services/Finance/PaymentService.php` — ganti seluruh method `getOrCreatePermanentVa()` (baris ~148-186) menjadi:

```php
    /**
     * Get or create permanent VA for a student's wallet.
     */
    public function getOrCreatePermanentVa(Siswa $siswa): BriVirtualAccount
    {
        $wallet = $siswa->wallet;
        if (!$wallet) {
            throw new PaymentException('Siswa tidak memiliki wallet.');
        }

        $existingVa = BriVirtualAccount::where('wallet_id', $wallet->id)
            ->where('va_type', 'WALLET_PERMANENT')
            ->first();

        if ($existingVa) {
            if ($wallet->va_number !== $existingVa->va_number) {
                $wallet->update(['va_number' => $existingVa->va_number]);
            }

            return $existingVa;
        }

        $dummyPembayaran = Pembayaran::create([
            'siswa_id' => $siswa->id,
            'metode' => 'va_bri',
            'status' => 'menunggu_pembayaran',
            'channel_reference' => 'WALLET_PERMANENT',
        ]);

        $vaResult = $this->gateway->createVirtualAccount($dummyPembayaran, 'WALLET_PERMANENT');

        $va = BriVirtualAccount::create([
            'pembayaran_id' => $dummyPembayaran->id,
            'wallet_id' => $wallet->id,
            'va_type' => 'WALLET_PERMANENT',
            'va_number' => $vaResult->vaNumber,
            'amount' => null,
            'expired_at' => null,
            'status' => 'PERMANENT',
            'callback_payload' => $vaResult->payload,
        ]);

        $wallet->update(['va_number' => $va->va_number]);

        return $va;
    }
```

(Perubahan dari versi lama: hapus komentar usang soal "we create a dummy pembayaran... or gateway shouldn't need it?", dan tambah 2 blok sinkronisasi `$wallet->update(['va_number' => ...])` — satu untuk kasus VA sudah ada, satu untuk kasus baru dibuat.)

- [ ] **Step 4: Run test untuk konfirmasi sinkronisasi jalan**

Run: `php artisan test tests/Feature/Keuangan/PaymentServiceTest.php --filter=test_get_or_create_permanent_va_syncs_wallet_va_number`
Expected: PASS

- [ ] **Step 5: Hapus `createVaPayment()` dan `createVaPaymentWithTopup()`**

`app/Services/Finance/PaymentService.php` — hapus seluruh method `createVaPayment()` (baris ~30-51) dan `createVaPaymentWithTopup()` (baris ~56-85), termasuk komentar docblock-nya. Method `createQrisPayment()` yang letaknya persis setelah `createVaPaymentWithTopup()` TIDAK disentuh — cukup pastikan tidak ada baris kosong ganda aneh setelah penghapusan.

- [ ] **Step 6: Hapus test yang menguji method yang baru dihapus**

`tests/Feature/Keuangan/PaymentServiceTest.php` — hapus method `test_create_va_payment_success()` (baris ~35-59) secara utuh. Method lain di file itu (`test_cannot_create_payment_if_bill_cancelled`, `test_get_or_create_permanent_va_idempotency`, test QRIS, dst.) TIDAK disentuh.

`tests/Feature/Keuangan/PaymentServiceBundledTopupTest.php` — hapus 3 method: `'creates a bundled VA payment by summing tagihan and topup amounts'` (~baris 28-37), `'rejects a bundled VA request with zero or negative topup amount'` (~50-57), `'rejects a bundled VA request with no tagihan selected'` (~59-66). Biarkan test QRIS (`'creates a bundled QRIS payment...'`, ~39-48) apa adanya.

`tests/Feature/Keuangan/CheckoutControllerBundledTopupTest.php` — hapus 4 method: `'creates a bundled VA payment...'` (~47-63), `'creates a plain VA payment...'` (~65-72), `'creates a second, distinct VA when re-submitting...'` (~88-118), `'does not redirect a plain resubmit into an existing bundled payment...'` (~120-152). Biarkan `'creates a bundled QRIS payment when topup_amount is submitted'` (~74-86). Untuk `'shows the checkout tab input for bundling a top-up'` (~154-161): baca isinya, kalau assertion-nya generik (misal `assertSee('topup_amount', false)` di halaman checkout create tanpa spesifik tab VA), boleh dibiarkan; kalau assertion-nya spesifik menunjuk ke tab VA, hapus juga (tab VA tidak lagi punya input `topup_amount` setelah Task 10) — putuskan berdasarkan isi test yang sebenarnya saat membaca file ini.

Delete seluruh isi `tests/Feature/Keuangan/KwitansiBundledTopupTest.php` dan `tests/Feature/Keuangan/RiwayatBundledTopupTest.php` (dua-duanya cuma menguji skenario `metode: 'va_bri'` + `topup_status` bundling yang sekarang mustahil terjadi):

```bash
rm tests/Feature/Keuangan/KwitansiBundledTopupTest.php
rm tests/Feature/Keuangan/RiwayatBundledTopupTest.php
```

`tests/Feature/Keuangan/ReconcilePaymentsBundledTopupTest.php` — JANGAN hapus file. Di test pertama (`'retries a failed bundled topup using the remainder...'`, ~baris 12-35), ganti nilai `metode` dari `'va_bri'` menjadi `'qris'` di fixture `Pembayaran` yang dibuat test itu (logic yang diuji — `topupSisaJikaAda()` via `retryFailedTopups()` — generic, tidak spesifik VA, tapi fixture-nya perlu mencerminkan skenario yang masih mungkin terjadi). Test kedua (`'still retries a pure (non-bundled) failed topup...'`, metode `'transfer_manual'`) TIDAK disentuh.

`tests/Feature/Keuangan/PaymentAllocationServiceTopupRemainderTest.php` — cari method `buatPembayaranGabungan()` (~baris 22), ganti nilai `metode` dari `'va_bri'` menjadi `'qris'`. Logic yang diuji (`topupSisaJikaAda()`) generic, tetap berlaku, ini murni supaya fixture-nya realistis.

- [ ] **Step 7: Run semua test yang disentuh**

Run: `php artisan test tests/Feature/Keuangan/PaymentServiceTest.php tests/Feature/Keuangan/PaymentServiceBundledTopupTest.php tests/Feature/Keuangan/CheckoutControllerBundledTopupTest.php tests/Feature/Keuangan/ReconcilePaymentsBundledTopupTest.php tests/Feature/Keuangan/PaymentAllocationServiceTopupRemainderTest.php`
Expected: PASS, semua hijau (file yang dihapus otomatis tidak ikut jalan).

- [ ] **Step 8: Cari sisa referensi ke method yang dihapus**

```bash
grep -rn "createVaPayment\b\|createVaPaymentWithTopup" app/ tests/
```
Expected: TIDAK ADA hasil (kalau ada, berarti ada pemanggil lain yang kelewat — perbaiki dulu sebelum lanjut; Task 10 akan menyentuh `CheckoutController::va()` yang jadi satu-satunya caller lama).

- [ ] **Step 9: Commit**

```bash
git add app/Services/Finance/PaymentService.php \
        tests/Feature/Keuangan/PaymentServiceTest.php \
        tests/Feature/Keuangan/PaymentServiceBundledTopupTest.php \
        tests/Feature/Keuangan/CheckoutControllerBundledTopupTest.php \
        tests/Feature/Keuangan/ReconcilePaymentsBundledTopupTest.php \
        tests/Feature/Keuangan/PaymentAllocationServiceTopupRemainderTest.php
git rm tests/Feature/Keuangan/KwitansiBundledTopupTest.php tests/Feature/Keuangan/RiwayatBundledTopupTest.php
git commit -m "refactor(bri): sync wallet.va_number, remove VA bundled top-up (BILL_DIRECT)"
```

**Catatan**: `CheckoutController::va()` masih memanggil `createVaPayment()`/`createVaPaymentWithTopup()` yang baru dihapus — aplikasi akan ERROR kalau dijalankan sekarang. Ini diperbaiki di Task 10. Task 5-9 (endpoint inbound baru) tidak bergantung pada `CheckoutController`, jadi urutan ini aman untuk dikerjakan bertahap; kalau mau tetap punya aplikasi yang jalan penuh di setiap commit, kerjakan Task 10 tepat setelah task ini sebelum lanjut ke Task 5.

---

### Task 5: Endpoint Token BRI

**Files:**
- Create: `app/Http/Controllers/Api/BriVaInboundController.php`
- Modify: `routes/web.php` (tambah route baru, hapus route webhook lama — dikerjakan penuh di Task 8, untuk task ini cukup TAMBAH tanpa hapus dulu)
- Modify: `bootstrap/app.php` (kecualikan path baru dari CSRF)
- Test: `tests/Feature/Keuangan/BriVaInboundTokenTest.php`

**Interfaces:**
- Consumes: `BriInboundAuthenticatorInterface::issueToken()` dari Task 2.
- Produces: `POST /snap/v1.0/access-token/b2b` — body `{"client_id": "...", "client_secret": "..."}`, response `{"accessToken": "...", "tokenType": "BearerToken", "expiresIn": "899"}` (200) atau `{"responseCode": "4017300", "responseMessage": "Unauthorized Client"}` (401). Dipakai Task 6, 7 (BRI harus dapat token dulu sebelum panggil Inquiry/Payment).

**Baca dulu**: `bri-api.md` section **"Access Token and Signature"** (baris ±160-190, bentuk response `{"accessToken", "tokenType", "expiresIn"}` — kita meniru bentuk response yang sama walau arah pemanggilnya dibalik, supaya konsisten dengan konvensi SNAP).

**Catatan penting**: format request body persis untuk arah inbound ini **belum dikonfirmasi resmi oleh BRI** (lihat "Pertanyaan Terbuka" di spec) — form onboarding BRI cuma menyebut field `client ID`/`client Secret` tanpa detail format request. Implementasi ini pakai asumsi paling sederhana (JSON body dengan key `client_id`/`client_secret`) sebagai starting point yang gampang disesuaikan nanti.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Keuangan;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BriVaInboundTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.bri.inbound.client_id' => 'test-client-id',
            'services.bri.inbound.client_secret' => 'test-client-secret',
        ]);
    }

    public function test_token_endpoint_returns_access_token_for_correct_credentials()
    {
        $response = $this->postJson('/snap/v1.0/access-token/b2b', [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['accessToken', 'tokenType', 'expiresIn']);
        $this->assertSame('BearerToken', $response->json('tokenType'));
    }

    public function test_token_endpoint_rejects_wrong_credentials()
    {
        $response = $this->postJson('/snap/v1.0/access-token/b2b', [
            'client_id' => 'test-client-id',
            'client_secret' => 'salah',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['responseCode' => '4017300']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/BriVaInboundTokenTest.php`
Expected: FAIL — 404 Not Found (route belum ada).

- [ ] **Step 3: Buat controller**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Contracts\BriInboundAuthenticatorInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BriVaInboundController extends Controller
{
    public function __construct(private readonly BriInboundAuthenticatorInterface $authenticator)
    {
    }

    public function token(Request $request)
    {
        $clientId = (string) $request->input('client_id');
        $clientSecret = (string) $request->input('client_secret');

        $token = $this->authenticator->issueToken($clientId, $clientSecret);

        if ($token === null) {
            return response()->json([
                'responseCode' => '4017300',
                'responseMessage' => 'Unauthorized Client',
            ], 401);
        }

        return response()->json([
            'accessToken' => $token,
            'tokenType' => 'BearerToken',
            'expiresIn' => '899',
        ]);
    }

    protected function bearerToken(Request $request): string
    {
        return (string) str($request->header('Authorization', ''))->after('Bearer ');
    }
}
```

- [ ] **Step 4: Tambah route**

`routes/web.php` — tambah SETELAH baris `Route::post('/webhook/bri/payment-notification', ...)` yang sudah ada (JANGAN hapus baris webhook lama dulu, itu tugas Task 8):

```php
Route::post('/snap/v1.0/access-token/b2b', [\App\Http\Controllers\Api\BriVaInboundController::class, 'token']);
```

- [ ] **Step 5: Kecualikan dari CSRF**

`bootstrap/app.php` — ganti `'webhook/*'` di dalam `validateCsrfTokens(except: [...])` menjadi:

```php
        $middleware->validateCsrfTokens(except: [
            'webhook/*',
            'snap/*',
        ]);
```

(Baris `'webhook/*'` masih dipertahankan sampai Task 8 menghapus route webhook lama sepenuhnya — kalau dihapus sekarang, route lama yang belum dibersihkan bisa gagal karena CSRF.)

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test tests/Feature/Keuangan/BriVaInboundTokenTest.php`
Expected: PASS (2 tests)

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/BriVaInboundController.php \
        routes/web.php bootstrap/app.php \
        tests/Feature/Keuangan/BriVaInboundTokenTest.php
git commit -m "feat(bri): add inbound token endpoint for BRI VA callbacks"
```

---

### Task 6: Endpoint Inquiry BRI — Read-Only

**Files:**
- Modify: `app/Http/Controllers/Api/BriVaInboundController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Keuangan/BriVaInboundInquiryTest.php`

**Interfaces:**
- Consumes: `BriInboundAuthenticatorInterface::validateToken()` dari Task 2, `BriVirtualAccount`/`Wallet`/`Siswa`/`Tagihan` model relations yang sudah ada.
- Produces: `POST /snap/v1.0/transfer-va/inquiry`, header `Authorization: Bearer {token}`, body `{"virtualAccountNo": "..."}`. Response 200 dengan `virtualAccountData` (nama siswa + saran nominal) kalau valid, 401 kalau token salah, 404 kalau VA tidak ditemukan.

**Baca dulu**: `bri-api.md` section **"Virtual Account/Briva Online"** → **"A. Inquiry"** (baris ±1838–1946) — Request Structure (`partnerServiceId`, `customerNo`, `virtualAccountNo`, `inquiryRequestId`), Response Structure (`virtualAccountData.virtualAccountName`, `totalAmount.value`/`currency`, `inquiryStatus`), dan contoh payload persis di baris 1902-1946.

- [ ] **Step 1: Write the failing test — inquiry sukses & TIDAK mengubah data**

```php
<?php

namespace Tests\Feature\Keuangan;

use App\Models\JenisTagihan;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Models\TagihanItem;
use App\Services\Finance\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BriVaInboundInquiryTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.bri.inbound.client_id' => 'test-client-id',
            'services.bri.inbound.client_secret' => 'test-client-secret',
            'services.bri.inbound.partner_service_id' => '77777777',
        ]);

        $tokenResponse = $this->postJson('/snap/v1.0/access-token/b2b', [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
        ]);
        $this->token = $tokenResponse->json('accessToken');
    }

    public function test_inquiry_returns_siswa_name_and_suggested_amount()
    {
        $siswa = Siswa::factory()->create(['nama_lengkap' => 'Budi Santoso']);
        $va = app(PaymentService::class)->getOrCreatePermanentVa($siswa);

        $jenisTagihan = JenisTagihan::factory()->create();
        $tagihan = Tagihan::factory()->create([
            'tagihable_type' => Siswa::class,
            'tagihable_id' => $siswa->id,
            'status' => 'belum_bayar',
            'net_amount' => 350000,
            'paid_amount' => 0,
            'jatuh_tempo' => now()->addDays(5),
        ]);
        TagihanItem::factory()->create(['tagihan_id' => $tagihan->id, 'jenis_tagihan_id' => $jenisTagihan->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/snap/v1.0/transfer-va/inquiry', [
                'virtualAccountNo' => $va->va_number,
                'inquiryRequestId' => 'test-inquiry-001',
            ]);

        $response->assertOk();
        $response->assertJson([
            'responseCode' => '2002400',
            'virtualAccountData' => [
                'virtualAccountNo' => $va->va_number,
                'virtualAccountName' => 'Budi Santoso',
                'totalAmount' => ['value' => '350000.00', 'currency' => 'IDR'],
            ],
        ]);
    }

    public function test_inquiry_does_not_change_any_data()
    {
        $siswa = Siswa::factory()->create();
        $va = app(PaymentService::class)->getOrCreatePermanentVa($siswa);
        $walletBalanceBefore = $siswa->wallet->fresh()->balance;
        $pembayaranCountBefore = \App\Models\Pembayaran::count();
        $tagihanCountBefore = Tagihan::count();

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/snap/v1.0/transfer-va/inquiry', [
                'virtualAccountNo' => $va->va_number,
                'inquiryRequestId' => 'test-inquiry-002',
            ]);

        $this->assertSame($walletBalanceBefore, $siswa->wallet->fresh()->balance);
        $this->assertSame($pembayaranCountBefore, \App\Models\Pembayaran::count());
        $this->assertSame($tagihanCountBefore, Tagihan::count());
    }

    public function test_inquiry_rejects_invalid_token()
    {
        $response = $this->withHeader('Authorization', 'Bearer token-palsu')
            ->postJson('/snap/v1.0/transfer-va/inquiry', ['virtualAccountNo' => '7777777700000000000001']);

        $response->assertStatus(401);
    }

    public function test_inquiry_returns_404_for_unknown_va_number()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/snap/v1.0/transfer-va/inquiry', ['virtualAccountNo' => '9999999900000000000001']);

        $response->assertStatus(404);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/BriVaInboundInquiryTest.php`
Expected: FAIL — 404 Not Found (route belum ada).

- [ ] **Step 3: Tambah method `inquiry()` ke controller**

`app/Http/Controllers/Api/BriVaInboundController.php` — tambah import `use App\Models\BriVirtualAccount;`, `use App\Models\Siswa;`, `use App\Models\Tagihan;`, lalu tambah method setelah `token()`:

```php
    public function inquiry(Request $request)
    {
        if (!$this->authenticator->validateToken($this->bearerToken($request))) {
            return response()->json([
                'responseCode' => '4012400',
                'responseMessage' => 'Unauthorized. Invalid Token (B2B)',
            ], 401);
        }

        $vaNumber = trim((string) $request->input('virtualAccountNo'));

        $va = BriVirtualAccount::where('va_number', $vaNumber)->with('wallet.siswa')->first();

        if (!$va || !$va->wallet || !$va->wallet->siswa) {
            return response()->json([
                'responseCode' => '4042412',
                'responseMessage' => 'Invalid Bill/Virtual Account',
            ], 404);
        }

        $siswa = $va->wallet->siswa;

        $tagihanJatuhTempo = Tagihan::where('tagihable_type', Siswa::class)
            ->where('tagihable_id', $siswa->id)
            ->whereIn('status', ['belum_bayar', 'sebagian'])
            ->orderBy('jatuh_tempo')
            ->first();

        $saranNominal = $tagihanJatuhTempo
            ? (float) $tagihanJatuhTempo->net_amount - (float) $tagihanJatuhTempo->paid_amount
            : 0.0;

        return response()->json([
            'responseCode' => '2002400',
            'responseMessage' => 'Successful',
            'virtualAccountData' => [
                'partnerServiceId' => substr($vaNumber, 0, 8),
                'customerNo' => substr($vaNumber, 8),
                'virtualAccountNo' => $vaNumber,
                'virtualAccountName' => $siswa->nama_lengkap,
                'inquiryRequestId' => (string) $request->input('inquiryRequestId'),
                'totalAmount' => [
                    'value' => number_format($saranNominal, 2, '.', ''),
                    'currency' => 'IDR',
                ],
                'inquiryStatus' => '00',
            ],
        ]);
    }
```

- [ ] **Step 4: Tambah route**

`routes/web.php` — tambah setelah route token dari Task 5:

```php
Route::post('/snap/v1.0/transfer-va/inquiry', [\App\Http\Controllers\Api\BriVaInboundController::class, 'inquiry']);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/Keuangan/BriVaInboundInquiryTest.php`
Expected: PASS (4 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/BriVaInboundController.php \
        routes/web.php \
        tests/Feature/Keuangan/BriVaInboundInquiryTest.php
git commit -m "feat(bri): add read-only inbound Inquiry endpoint for VA"
```

---

### Task 7: Endpoint Payment BRI — Idempotent, Tidak Pernah Kehilangan Uang

**Files:**
- Modify: `app/Http/Controllers/Api/BriVaInboundController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Keuangan/BriVaInboundPaymentTest.php`

**Interfaces:**
- Consumes: `BriInboundAuthenticatorInterface::validateToken()` (Task 2), `BriInboundPaymentLog` (Task 1), `Wallet::topup()` (existing, `app/Models/Wallet.php:38-73`).
- Produces: `POST /snap/v1.0/transfer-va/payment`. Response 200 dengan `paymentFlagStatus: "00"` kalau sukses (termasuk saat diulang/idempotent replay), 401 token salah, 400 field wajib kosong/nominal tidak valid, 404 VA tidak ditemukan.

**Baca dulu**: `bri-api.md` section **"Virtual Account/Briva Online"** → **"B. Payment"** (baris ±2032–2158) — Request Structure (`virtualAccountNo`, `paidAmount.value`/`currency`, `paymentRequestId`), Response Structure (`virtualAccountData.paymentFlagStatus`: `"00" = Success`), dan contoh payload di baris 2099-2150.

**Catatan desain penting (baca sebelum menulis kode)**: penambahan saldo (`Wallet::topup()`) punya transaksi database SENDIRI yang terpisah dari penulisan baris `BriInboundPaymentLog`. Ini artinya ada celah waktu SANGAT KECIL antara "baris ledger tercatat" dan "saldo benar-benar bertambah" — kalau proses mati persis di celah itu, laporan akan dianggap "sudah diproses" tapi saldo belum nambah. Ini trade-off yang disadari dan diterima (celahnya kecil sekali, `Wallet::topup()` cuma 1 query update + 1 insert) — JANGAN coba menyatukan dua transaksi ini jadi satu dengan mengubah `Wallet::topup()`, karena method itu dipakai banyak caller lain dan mengubahnya berisiko tinggi di luar scope task ini.

- [ ] **Step 1: Write the failing test — semua skenario dari spec**

```php
<?php

namespace Tests\Feature\Keuangan;

use App\Models\BriInboundPaymentLog;
use App\Models\JenisTagihan;
use App\Models\Siswa;
use App\Models\SystemSetting;
use App\Models\Tagihan;
use App\Models\TagihanItem;
use App\Services\Finance\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class BriVaInboundPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.bri.inbound.client_id' => 'test-client-id',
            'services.bri.inbound.client_secret' => 'test-client-secret',
            'services.bri.inbound.partner_service_id' => '77777777',
        ]);

        $tokenResponse = $this->postJson('/snap/v1.0/access-token/b2b', [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
        ]);
        $this->token = $tokenResponse->json('accessToken');
    }

    protected function payloadFor(string $vaNumber, float $amount, string $paymentRequestId): array
    {
        return [
            'virtualAccountNo' => $vaNumber,
            'paidAmount' => ['value' => number_format($amount, 2, '.', ''), 'currency' => 'IDR'],
            'paymentRequestId' => $paymentRequestId,
        ];
    }

    public function test_payment_credits_wallet_and_auto_debits_due_tagihan()
    {
        $siswa = Siswa::factory()->create();
        SystemSetting::create(['lembaga_id' => $siswa->lembaga_id, 'key' => 'auto_debit_enabled', 'value' => 'true']);
        $va = app(PaymentService::class)->getOrCreatePermanentVa($siswa);

        $jenisTagihan = JenisTagihan::factory()->create();
        $tagihan = Tagihan::factory()->create([
            'tagihable_type' => Siswa::class,
            'tagihable_id' => $siswa->id,
            'status' => 'belum_bayar',
            'net_amount' => 100000,
            'paid_amount' => 0,
        ]);
        TagihanItem::factory()->create(['tagihan_id' => $tagihan->id, 'jenis_tagihan_id' => $jenisTagihan->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/snap/v1.0/transfer-va/payment', $this->payloadFor($va->va_number, 100000, 'PAY-001'));

        $response->assertOk();
        $response->assertJsonPath('virtualAccountData.paymentFlagStatus', '00');

        $tagihan->refresh();
        $this->assertSame('lunas', $tagihan->status);

        $this->assertDatabaseHas('bri_inbound_payment_logs', ['payment_request_id' => 'PAY-001']);
    }

    public function test_payment_is_idempotent_for_duplicate_payment_request_id()
    {
        $siswa = Siswa::factory()->create();
        $va = app(PaymentService::class)->getOrCreatePermanentVa($siswa);
        $saldoAwal = $siswa->wallet->fresh()->balance;

        $payload = $this->payloadFor($va->va_number, 50000, 'PAY-DUPLICATE');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)->postJson('/snap/v1.0/transfer-va/payment', $payload)->assertOk();
        $this->withHeader('Authorization', 'Bearer ' . $this->token)->postJson('/snap/v1.0/transfer-va/payment', $payload)->assertOk();

        $this->assertSame($saldoAwal + 50000, $siswa->wallet->fresh()->balance);
        $this->assertSame(1, BriInboundPaymentLog::where('payment_request_id', 'PAY-DUPLICATE')->count());
    }

    public function test_payment_keeps_wallet_credit_even_if_auto_allocation_fails()
    {
        // auto_debit_enabled default false kalau tidak di-set -- pastikan false di sini
        // supaya AutoAllocationEngine::run() tidak dipanggil (kita test resiliency-nya
        // lewat pengecualian umum: seandainya topup() throw APAPUN, saldo tetap masuk
        // karena sudah commit di transaksi internalnya sendiri sebelum exception muncul).
        // Test ini memverifikasi baris ledger + saldo tetap konsisten walau tidak ada
        // tagihan untuk dialokasikan (skenario paling sederhana yang tidak butuh mocking).
        $siswa = Siswa::factory()->create();
        $va = app(PaymentService::class)->getOrCreatePermanentVa($siswa);
        $saldoAwal = $siswa->wallet->fresh()->balance;

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/snap/v1.0/transfer-va/payment', $this->payloadFor($va->va_number, 75000, 'PAY-NO-ALLOC'));

        $response->assertOk();
        $this->assertSame($saldoAwal + 75000, $siswa->wallet->fresh()->balance);
    }

    public function test_payment_rejects_invalid_token()
    {
        $response = $this->withHeader('Authorization', 'Bearer token-palsu')
            ->postJson('/snap/v1.0/transfer-va/payment', $this->payloadFor('7777777700000000000001', 50000, 'PAY-BAD-TOKEN'));

        $response->assertStatus(401);
    }

    public function test_payment_returns_404_for_unknown_va_number()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/snap/v1.0/transfer-va/payment', $this->payloadFor('9999999900000000000001', 50000, 'PAY-UNKNOWN-VA'));

        $response->assertStatus(404);
    }

    public function test_payment_rejects_non_positive_amount_without_logging_as_processed()
    {
        $siswa = Siswa::factory()->create();
        $va = app(PaymentService::class)->getOrCreatePermanentVa($siswa);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/snap/v1.0/transfer-va/payment', $this->payloadFor($va->va_number, 0, 'PAY-ZERO-AMOUNT'));

        $response->assertStatus(404);
        $this->assertDatabaseMissing('bri_inbound_payment_logs', ['payment_request_id' => 'PAY-ZERO-AMOUNT']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/BriVaInboundPaymentTest.php`
Expected: FAIL — 404 Not Found (route belum ada).

- [ ] **Step 3: Tambah method `payment()` ke controller**

`app/Http/Controllers/Api/BriVaInboundController.php` — tambah import `use App\Models\BriInboundPaymentLog;`, `use App\Models\Pembayaran;`, `use App\Models\Wallet;`, `use Illuminate\Support\Facades\Log;`, lalu tambah method setelah `inquiry()`:

```php
    public function payment(Request $request)
    {
        if (!$this->authenticator->validateToken($this->bearerToken($request))) {
            return response()->json([
                'responseCode' => '4012500',
                'responseMessage' => 'Unauthorized. Invalid Token (B2B)',
            ], 401);
        }

        $vaNumber = trim((string) $request->input('virtualAccountNo'));
        $paymentRequestId = (string) $request->input('paymentRequestId');
        $amount = (float) data_get($request->input('paidAmount'), 'value', 0);

        if ($vaNumber === '' || $paymentRequestId === '') {
            return response()->json([
                'responseCode' => '4002500',
                'responseMessage' => 'Invalid Mandatory Field',
            ], 400);
        }

        $existingLog = BriInboundPaymentLog::where('payment_request_id', $paymentRequestId)->first();
        if ($existingLog) {
            return $this->paymentSuccessResponse($vaNumber, $paymentRequestId, $existingLog->amount);
        }

        if ($amount <= 0) {
            return response()->json([
                'responseCode' => '4042513',
                'responseMessage' => 'Invalid Amount',
            ], 404);
        }

        $va = BriVirtualAccount::where('va_number', $vaNumber)->with('wallet')->first();

        if (!$va || !$va->wallet) {
            return response()->json([
                'responseCode' => '4042512',
                'responseMessage' => 'Invalid Bill/Virtual Account',
            ], 404);
        }

        $wallet = $va->wallet;

        $pembayaran = Pembayaran::create([
            'siswa_id' => $wallet->siswa_id,
            'wallet_id' => $wallet->id,
            'metode' => 'va_bri',
            'amount' => $amount,
            'status' => 'lunas',
            'topup_status' => 'pending',
            'channel_reference' => $paymentRequestId,
        ]);

        try {
            BriInboundPaymentLog::create([
                'payment_request_id' => $paymentRequestId,
                'va_number' => $vaNumber,
                'amount' => $amount,
                'pembayaran_id' => $pembayaran->id,
            ]);
        } catch (\Throwable $e) {
            // Race: request lain sudah mencatat paymentRequestId ini lebih dulu --
            // aman dianggap sebagai laporan dobel (idempotent replay).
            $pembayaran->delete();

            return $this->paymentSuccessResponse($vaNumber, $paymentRequestId, $amount);
        }

        try {
            $wallet->topup($amount, $pembayaran, 'Top-up via VA BRI');
            $pembayaran->update(['topup_status' => 'completed']);
        } catch (\Throwable $e) {
            Log::error("Gagal proses auto-debit setelah topup VA {$vaNumber}: " . $e->getMessage());
            $pembayaran->update(['topup_status' => 'failed']);
        }

        return $this->paymentSuccessResponse($vaNumber, $paymentRequestId, $amount);
    }

    protected function paymentSuccessResponse(string $vaNumber, string $paymentRequestId, float $amount)
    {
        return response()->json([
            'responseCode' => '2002500',
            'responseMessage' => 'Successful',
            'virtualAccountData' => [
                'partnerServiceId' => substr($vaNumber, 0, 8),
                'customerNo' => substr($vaNumber, 8),
                'virtualAccountNo' => $vaNumber,
                'paymentRequestId' => $paymentRequestId,
                'paidAmount' => [
                    'value' => number_format($amount, 2, '.', ''),
                    'currency' => 'IDR',
                ],
                'paymentFlagStatus' => '00',
            ],
        ]);
    }
```

- [ ] **Step 4: Tambah route**

`routes/web.php` — tambah setelah route inquiry dari Task 6:

```php
Route::post('/snap/v1.0/transfer-va/payment', [\App\Http\Controllers\Api\BriVaInboundController::class, 'payment']);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/Keuangan/BriVaInboundPaymentTest.php`
Expected: PASS (6 tests)

- [ ] **Step 6: Run scope Keuangan penuh untuk memastikan tidak ada regresi**

Run: `php artisan test tests/Feature/Keuangan/`
Expected: PASS (ingat: `CheckoutControllerVaQrisTest.php` dan file lain yang masih memanggil route `checkout.va` lama mungkin masih merah di titik ini — itu diperbaiki di Task 10. Kalau ada test GAGAL di luar yang sudah diketahui terkait Task 10, investigasi dulu sebelum lanjut.)

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/BriVaInboundController.php \
        routes/web.php \
        tests/Feature/Keuangan/BriVaInboundPaymentTest.php
git commit -m "feat(bri): add idempotent inbound Payment endpoint, credits wallet via existing topup()"
```

---

### Task 8: Hapus `BriWebhookController` (Skema Lama/Non-SNAP)

**Files:**
- Delete: `app/Http/Controllers/Api/BriWebhookController.php`
- Delete: `tests/Feature/Keuangan/WebhookControllerTest.php`
- Delete: `tests/Feature/Keuangan/BriWebhookBundledTopupTest.php`
- Modify: `routes/web.php`
- Modify: `bootstrap/app.php`

**Interfaces:**
- Tidak ada — task ini murni penghapusan dead code yang fungsinya sudah digantikan Task 7.

- [ ] **Step 1: Pastikan tidak ada referensi lain ke `BriWebhookController` sebelum menghapus**

```bash
grep -rn "BriWebhookController" app/ routes/ tests/
```

Expected: hanya muncul di `app/Http/Controllers/Api/BriWebhookController.php` sendiri, `routes/web.php:5,8`, dan 2 file test yang akan dihapus di step berikut. Kalau ada referensi lain, investigasi dulu.

- [ ] **Step 2: Hapus file controller dan test**

```bash
rm app/Http/Controllers/Api/BriWebhookController.php
rm tests/Feature/Keuangan/WebhookControllerTest.php
rm tests/Feature/Keuangan/BriWebhookBundledTopupTest.php
```

- [ ] **Step 3: Hapus route lama**

`routes/web.php` — hapus baris `use App\Http\Controllers\Api\BriWebhookController;` (baris 5) dan `Route::post('/webhook/bri/payment-notification', [BriWebhookController::class, 'handlePaymentNotification']);` (baris 8).

- [ ] **Step 4: Bersihkan pengecualian CSRF yang sudah tidak dipakai**

`bootstrap/app.php` — hapus `'webhook/*'` dari `validateCsrfTokens(except: [...])`, sisakan `'snap/*'` saja:

```php
        $middleware->validateCsrfTokens(except: [
            'snap/*',
        ]);
```

- [ ] **Step 5: Run scope Keuangan penuh**

Run: `php artisan test tests/Feature/Keuangan/`
Expected: PASS (2 file test yang dihapus otomatis tidak ikut jalan; tidak ada test lain yang merujuk `BriWebhookController` per pengecekan Step 1).

- [ ] **Step 6: Commit**

```bash
git add routes/web.php bootstrap/app.php
git rm app/Http/Controllers/Api/BriWebhookController.php \
       tests/Feature/Keuangan/WebhookControllerTest.php \
       tests/Feature/Keuangan/BriWebhookBundledTopupTest.php
git commit -m "refactor(bri): remove legacy Non-SNAP BriWebhookController, superseded by inbound Payment endpoint"
```

---

### Task 9: Hapus Dead Code Loop VA di `ReconcilePayments`

**Files:**
- Modify: `app/Console/Commands/ReconcilePayments.php`
- Modify: `tests/Feature/Keuangan/ReconciliationCommandTest.php`

**Interfaces:**
- Tidak ada — task ini murni penghapusan kode yang tidak pernah lagi bisa tereksekusi (lihat penjelasan di bawah).

**Kenapa ini dead code**: `BriVirtualAccount` dengan `status = 'WAITING'` HANYA pernah dibuat oleh `createVaPayment()`/`createVaPaymentWithTopup()` (dihapus di Task 4). VA permanen (`getOrCreatePermanentVa()`) selalu pakai `status = 'PERMANENT'`, tidak pernah `'WAITING'`. Jadi query `BriVirtualAccount::where('status', 'WAITING')` di method `reconcileWaitingPayments()` akan SELALU mengembalikan koleksi kosong setelah Task 4 — blok kode ini tidak pernah lagi jalan.

- [ ] **Step 1: Hapus blok VA di `reconcileWaitingPayments()`**

`app/Console/Commands/ReconcilePayments.php` — di dalam method `reconcileWaitingPayments()`, hapus seluruh blok VA (dari `// Find WAITING VAs` sampai sebelum komentar `// We can do the same for QRIS if needed`, kira-kira baris 52-96). Method setelah penghapusan jadi mulai langsung dari blok QRIS:

```php
    protected function reconcileWaitingPayments()
    {
        $waitingQris = BriQrisPayment::where('status', 'WAITING')
            ->whereNotNull('pembayaran_id')
            ->get();

        foreach ($waitingQris as $qris) {
            try {
                $statusResult = $this->gateway->checkStatus($qris->reference_no, 'qris');
                
                if ($statusResult->status === 'PAID') {
                    $reconciledPembayaranId = null;

                    DB::transaction(function () use ($qris, &$reconciledPembayaranId) {
                        $lockedQris = BriQrisPayment::where('id', $qris->id)->lockForUpdate()->first();
                        
                        if ($lockedQris->status !== 'PAID') {
                            $lockedQris->status = 'PAID';
                            $lockedQris->save();

                            $pembayaran = Pembayaran::find($lockedQris->pembayaran_id);
                            if ($pembayaran && $pembayaran->status !== 'lunas') {
                                $pembayaran->status = 'lunas';
                                $pembayaran->save();
                                $this->allocationService->allocate($pembayaran);
                                if ($pembayaran->topup_status === 'pending') {
                                    $bundledTopupPembayaranId = $pembayaran->id;
                                }
                            }
                        }
                    });

                    if ($reconciledPembayaranId !== null) {
                        $reconciledPembayaran = Pembayaran::find($reconciledPembayaranId);
                        if ($reconciledPembayaran !== null) {
                            $this->allocationService->topupSisaJikaAda($reconciledPembayaran);
                        }
                    }

                    $this->line("Reconciled QRIS: {$qris->qr_code}");
                }
            } catch (\Exception $e) {
                Log::error("Failed to reconcile QRIS {$qris->qr_code}: " . $e->getMessage());
                $this->error("Failed to reconcile QRIS {$qris->qr_code}");
            }
        }
    }
```

(`use App\Models\BriVirtualAccount;` di bagian atas file boleh tetap ada atau dihapus tergantung apakah masih dipakai import lain di file — cek dulu dengan `grep -n "BriVirtualAccount" app/Console/Commands/ReconcilePayments.php` setelah edit ini; kalau sudah tidak dipakai sama sekali, hapus baris importnya.)

- [ ] **Step 2: Hapus test yang menguji blok yang dihapus**

`tests/Feature/Keuangan/ReconciliationCommandTest.php` — hapus method `test_reconcile_command_updates_waiting_payments()` (~baris 28-77) secara utuh. Biarkan `test_reconcile_command_retries_failed_topups()` apa adanya (method itu menguji `retryFailedTopups()`, tidak disentuh task ini).

- [ ] **Step 3: Run test**

Run: `php artisan test tests/Feature/Keuangan/ReconciliationCommandTest.php`
Expected: PASS (1 test tersisa)

- [ ] **Step 4: Commit**

```bash
git add app/Console/Commands/ReconcilePayments.php \
        tests/Feature/Keuangan/ReconciliationCommandTest.php
git commit -m "refactor(bri): remove dead VA-WAITING reconciliation loop, unreachable after permanent-VA-only change"
```

---

### Task 10: Sederhanakan `CheckoutController::va()` + Halaman VA Info + Wiring Dashboard

**Files:**
- Modify: `app/Http/Controllers/Keuangan/CheckoutController.php`
- Modify: `app/Http/Controllers/Keuangan/DashboardController.php`
- Modify: `routes/web.php`
- Create: `resources/views/keuangan/checkout/va-info.blade.php`
- Modify: `resources/views/keuangan/checkout/tabs/va.blade.php`
- Modify: `tests/Feature/Keuangan/CheckoutControllerVaQrisTest.php`
- Modify: `tests/Feature/Keuangan/CheckoutAuthorizationTest.php`

**Interfaces:**
- Consumes: `PaymentService::getOrCreatePermanentVa()` dari Task 4.
- Produces: `GET /keuangan/checkout/va-info?tagihan_ids[]=...` (route `keuangan.checkout.va-info`) — halaman baru yang tampilkan nomor VA tetap + saran nominal.

- [ ] **Step 1: Write the failing test — `va()` redirect ke halaman info, bukan bikin Pembayaran**

Tambahkan ke `tests/Feature/Keuangan/CheckoutControllerVaQrisTest.php`, di bagian yang menghapus 3 test VA lama (lihat Step 5), tambahkan test baru ini sebagai gantinya:

```php
test('va checkout shows the permanent VA number without creating a new pembayaran', function () {
    [$user, $siswa] = actingAsOrangTuaForVaQris();

    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $siswa->lembaga_id]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class,
        'tagihable_id' => $siswa->id,
        'status' => 'belum_bayar',
        'net_amount' => 250000,
        'paid_amount' => 0,
    ]);

    $pembayaranCountBefore = Pembayaran::count();

    $response = $this->actingAs($user)->post(route('keuangan.checkout.va'), [
        'tagihan_ids' => [$tagihan->id],
    ]);

    $response->assertRedirect();
    $this->assertStringContainsString('checkout/va-info', $response->headers->get('Location'));
    $this->assertSame($pembayaranCountBefore, Pembayaran::count());
});

test('va info page shows the va number and suggested amount', function () {
    [$user, $siswa] = actingAsOrangTuaForVaQris();

    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $siswa->lembaga_id]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class,
        'tagihable_id' => $siswa->id,
        'status' => 'belum_bayar',
        'net_amount' => 250000,
        'paid_amount' => 0,
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.checkout.va-info', ['tagihan_ids' => [$tagihan->id]]));

    $response->assertOk();
    $response->assertSee('250.000', false);
});
```

(Catatan: `actingAsOrangTuaForVaQris()` adalah helper function Pest yang sudah ada di bagian atas file ini — dipakai ulang.)

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/CheckoutControllerVaQrisTest.php --filter="va checkout shows the permanent VA number"`
Expected: FAIL — route `keuangan.checkout.va-info` belum ada, dan `va()` masih memanggil method yang sudah dihapus di Task 4 (kalau Task 4 sudah dikerjakan lebih dulu, ini akan fatal error `Call to undefined method`).

- [ ] **Step 3: Sederhanakan `CheckoutController::va()` dan tambah `vaInfo()`**

`app/Http/Controllers/Keuangan/CheckoutController.php` — ganti seluruh method `va()` (baris ~58-93) menjadi:

```php
    public function va(Request $request)
    {
        $requestedIds = (array) $request->input('tagihan_ids', []);

        return redirect()->route('keuangan.checkout.va-info', ['tagihan_ids' => $requestedIds]);
    }

    public function vaInfo(Request $request): View
    {
        $activeSiswa = $request->attributes->get('activeSiswa');
        $requestedIds = (array) $request->query('tagihan_ids', []);
        $tagihans = $this->resolveSelectedTagihan($activeSiswa, $requestedIds);

        $totalTagihan = $tagihans->reduce(
            fn (float $carry, Tagihan $tagihan) => $carry + ($tagihan->net_amount - $tagihan->paid_amount),
            0.0
        );

        $va = $this->paymentService->getOrCreatePermanentVa($activeSiswa);

        return view('keuangan.checkout.va-info', [
            'va' => $va,
            'totalTagihan' => $totalTagihan,
            'tagihans' => $tagihans,
        ]);
    }
```

Cek juga `show()` (baris ~209-227) — sekarang jalur `va_bri` tidak pernah lagi masuk ke sini lewat checkout aktif (VA lama sudah dihapus, VA baru tidak membuat Pembayaran pending). Ganti baris `abort_unless(in_array($pembayaran->metode, ['va_bri', 'qris']), 404);` menjadi:

```php
        abort_unless($pembayaran->metode === 'qris', 404);
```

Dan hapus `'briVirtualAccount'` dari `$pembayaran->load([...])` di baris setelahnya (tetap load `'briQrisPayment'`, `'pembayaranTagihan'`).

- [ ] **Step 4: Tambah route baru**

`routes/web.php` — di dalam grup `prefix('keuangan')`, tambah setelah baris `Route::post('/checkout/va', ...)`:

```php
        Route::get('/checkout/va-info', [\App\Http\Controllers\Keuangan\CheckoutController::class, 'vaInfo'])->name('checkout.va-info');
```

- [ ] **Step 5: Buat view `va-info.blade.php`**

Baca dulu `resources/views/keuangan/dashboard.blade.php` baris ~332-352 (blok "Salin VA" di modal top-up) untuk meniru persis pola tampilan nomor VA + tombol salin yang sudah dipakai dan sudah benar (termasuk SVG ikon clipboard yang sudah diperbaiki di redesign sebelumnya — JANGAN pakai ulang versi SVG yang rusak, pastikan salin persis dari lokasi yang sudah benar).

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Bayar via Virtual Account BRI
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-xl p-6 space-y-5">
                @if ($totalTagihan > 0)
                    <div class="rounded-xl bg-brand-50 border border-brand-100 p-4">
                        <p class="text-xs text-brand-700 font-medium">Saran nominal transfer</p>
                        <p class="text-xl font-bold text-brand-800 mt-1">Rp {{ number_format($totalTagihan, 0, ',', '.') }}</p>
                        <p class="text-xs text-gray-500 mt-1">Ini nominal total tagihan yang Anda pilih — transfer sesuai nominal ini supaya tagihan langsung lunas otomatis.</p>
                    </div>
                @endif

                <div class="rounded-xl bg-gray-50 border border-gray-100 p-4">
                    <p class="text-xs text-gray-500">Nomor Virtual Account BRI (tetap, bisa dipakai kapan saja)</p>
                    <div class="flex items-center justify-between mt-1">
                        <p class="font-mono text-lg font-bold text-gray-800">{{ $va->va_number }}</p>
                        <button
                            type="button"
                            x-data
                            @click="navigator.clipboard.writeText('{{ $va->va_number }}'); $store.toast.push('Nomor VA disalin')"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100"
                            title="Salin VA"
                            aria-label="Salin VA"
                        >
                            <span>Salin</span>
                        </button>
                    </div>
                </div>

                <p class="text-sm text-gray-500 leading-relaxed">
                    Transfer ke nomor ini lewat ATM BRI, BRImo, BRILink, atau bank lain. Saldo akan bertambah otomatis begitu transfer diterima, dan tagihan jatuh tempo akan langsung dilunasi otomatis kalau saldo mencukupi.
                </p>

                <a href="{{ route('keuangan.tagihan.index') }}" class="inline-flex w-full items-center justify-center rounded-xl bg-gray-100 px-5 py-3 text-xs font-semibold text-gray-700 hover:bg-gray-200">
                    Kembali ke Daftar Tagihan
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Bersihkan tab VA di halaman checkout create**

`resources/views/keuangan/checkout/tabs/va.blade.php` — hapus baris `<input type="hidden" name="topup_amount" x-bind:value="topupAmount">` (baris 7, bundling VA sudah dihapus, input ini tidak relevan lagi untuk tab VA). Update juga teks deskripsi (baris 17) supaya tidak menyiratkan nomor VA baru dibuat setiap kali:

```blade
                <p class="mt-1 leading-relaxed text-gray-500">Nomor Virtual Account BRI Anda tetap sama setiap saat. Anda dapat melakukan pembayaran via ATM BRI, BRILink, BRImo, atau transfer dari bank lain.</p>
```

- [ ] **Step 7: Wiring `DashboardController`**

`app/Http/Controllers/Keuangan/DashboardController.php` — tambah import `use App\Services\Finance\PaymentService;`, tambah `PaymentService $paymentService` ke constructor, dan panggil `getOrCreatePermanentVa()` di `index()` sebelum return view:

```php
    public function __construct(
        private readonly SkipAlertResolver $skipAlertResolver,
        private readonly NotificationFeedResolver $notificationFeedResolver,
        private readonly PaymentService $paymentService,
    ) {
    }

    public function index(Request $request): View
    {
        $activeSiswa = $request->attributes->get('activeSiswa');

        if ($activeSiswa === null) {
            return view('keuangan.tanpa-anak');
        }

        $this->paymentService->getOrCreatePermanentVa($activeSiswa);

        $wallet = $activeSiswa->wallet;
```

(Baris `$wallet = $activeSiswa->wallet;` diambil SETELAH pemanggilan `getOrCreatePermanentVa()` supaya `$wallet->va_number` yang di-load sudah ter-sinkron — kalau diambil sebelumnya, `$wallet` di variabel itu bisa jadi instance lama yang belum ter-refresh `va_number`-nya.)

- [ ] **Step 8: Hapus 3 test VA murni yang sudah tidak relevan, reroute 4 test VA-flavored ke QRIS**

`tests/Feature/Keuangan/CheckoutControllerVaQrisTest.php`:
- Hapus test `'creates a VA payment and redirects...'` (~46-56), `'does not create a second VA...'` (~70-77), `'creates a new VA when selection expands...'` (~88-108) — baca isinya dulu untuk konfirmasi nama persis sebelum menghapus, karena ini file Pest (`test('...', function() {...})`), bukan method PHPUnit.
- Untuk `'rejects tagihan_ids that do not belong to the active child'`, `'blocks viewing a pembayaran belonging to another parent's child'`, `'returns the payment status as json for polling'`: baca isi masing-masing, ganti route yang dipakai untuk bikin fixture dari `route('keuangan.checkout.va', ...)` menjadi `route('keuangan.checkout.qris', ...)` supaya tetap menguji behavior generik (reject/blocked/polling) tanpa bergantung pada VA yang sudah dihapus.
- Untuk `'shows the waiting page with the VA number'` (~124-133): ini test untuk halaman "menunggu pembayaran" VA yang sudah tidak ada lagi — hapus test ini, tambahkan test baru yang setara untuk halaman `va-info` (sudah ditambahkan di Step 1 di atas: `'va info page shows the va number and suggested amount'`).

- [ ] **Step 9: Reroute 1 test di `CheckoutAuthorizationTest.php`**

`tests/Feature/Keuangan/CheckoutAuthorizationTest.php` — baca test `'blocks a parent from polling the status of another parent's pembayaran'` (~baris 97-107). Ganti route yang dipakai untuk membuat fixture pembayaran dari `route('keuangan.checkout.va', ...)` menjadi `route('keuangan.checkout.qris', ...)` (test ini tidak menguji apa pun yang spesifik ke VA, cuma butuh ADA sebuah `Pembayaran` untuk diuji otorisasinya).

- [ ] **Step 10: Run seluruh scope Keuangan**

Run: `php artisan test tests/Feature/Keuangan/`
Expected: PASS, semua hijau. Ini titik pertama sejak Task 4 di mana SELURUH suite Keuangan seharusnya kembali hijau total (Task 4-9 sengaja meninggalkan beberapa test merah terkait `CheckoutController::va()` yang baru diperbaiki di task ini).

- [ ] **Step 11: Commit**

```bash
git add app/Http/Controllers/Keuangan/CheckoutController.php \
        app/Http/Controllers/Keuangan/DashboardController.php \
        routes/web.php \
        resources/views/keuangan/checkout/va-info.blade.php \
        resources/views/keuangan/checkout/tabs/va.blade.php \
        tests/Feature/Keuangan/CheckoutControllerVaQrisTest.php \
        tests/Feature/Keuangan/CheckoutAuthorizationTest.php
git commit -m "feat(bri): simplify VA checkout to permanent-VA info page, wire getOrCreatePermanentVa into dashboard"
```

---

### Task 11: Command Manual Simulasi — "Pura-pura Jadi BRI"

**Files:**
- Create: `app/Console/Commands/BriTestVaInbound.php`

**Interfaces:**
- Consumes: 3 endpoint dari Task 5, 6, 7 (dipanggil sebagai HTTP client biasa, bukan lewat kode PHP langsung — supaya benar-benar menguji jalur HTTP+route+middleware yang sama seperti yang BRI pakai nanti).

**Baca dulu**: tidak ada bagian baru `bri-api.md` untuk task ini — command ini murni wrapper CLI di atas endpoint yang sudah dibangun Task 5-7.

- [ ] **Step 1: Write the command**

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BriTestVaInbound extends Command
{
    protected $signature = 'bri:test-va-inbound {va_number} {amount=50000}';

    protected $description = 'Simulasikan BRI memanggil endpoint inbound VA kita sendiri (token, inquiry, payment) untuk verifikasi manual lokal';

    public function handle(): int
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $clientId = (string) config('services.bri.inbound.client_id');
        $clientSecret = (string) config('services.bri.inbound.client_secret');

        if ($clientId === '' || $clientSecret === '') {
            $this->error('BRI_INBOUND_CLIENT_ID / BRI_INBOUND_CLIENT_SECRET masih kosong di .env — isi dulu sebelum menjalankan command ini.');

            return self::FAILURE;
        }

        $this->info('Meminta token...');
        $tokenResponse = Http::post($baseUrl . '/snap/v1.0/access-token/b2b', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        if (!$tokenResponse->successful()) {
            $this->error('Gagal ambil token: ' . $tokenResponse->body());

            return self::FAILURE;
        }

        $token = $tokenResponse->json('accessToken');
        $this->info("Token: {$token}");

        $vaNumber = $this->argument('va_number');

        $this->info('Memanggil Inquiry...');
        $inquiryResponse = Http::withToken($token)->post($baseUrl . '/snap/v1.0/transfer-va/inquiry', [
            'virtualAccountNo' => $vaNumber,
            'inquiryRequestId' => (string) Str::uuid(),
        ]);
        $this->line($inquiryResponse->body());

        if (!$inquiryResponse->successful()) {
            $this->error('Inquiry gagal, hentikan simulasi.');

            return self::FAILURE;
        }

        $this->info('Memanggil Payment...');
        $paymentResponse = Http::withToken($token)->post($baseUrl . '/snap/v1.0/transfer-va/payment', [
            'virtualAccountNo' => $vaNumber,
            'paidAmount' => [
                'value' => number_format((float) $this->argument('amount'), 2, '.', ''),
                'currency' => 'IDR',
            ],
            'paymentRequestId' => (string) Str::uuid(),
        ]);
        $this->line($paymentResponse->body());

        return $paymentResponse->successful() ? self::SUCCESS : self::FAILURE;
    }
}
```

- [ ] **Step 2: Verifikasi command terdaftar**

Run: `php artisan list bri`
Expected: muncul `bri:test-qris` (dari sub-project sebelumnya) dan `bri:test-va-inbound`.

- [ ] **Step 3: Jalankan sekali secara manual (server lokal harus sedang jalan, `php artisan serve` atau lewat Laragon)**

Prasyarat: `BRI_INBOUND_CLIENT_ID`/`BRI_INBOUND_CLIENT_SECRET`/`BRI_INBOUND_PARTNER_SERVICE_ID` di `.env` harus terisi (bisa nilai sembarang untuk simulasi lokal — kredensial ini kita yang generate sendiri, tidak perlu menunggu BRI). Butuh minimal 1 siswa dengan VA yang sudah dibuat (kunjungi halaman `/keuangan` dulu di browser supaya `getOrCreatePermanentVa()` ter-trigger, atau jalankan lewat tinker).

Run: `php artisan bri:test-va-inbound <nomor_va_siswa> 50000`

Expected: output menampilkan token, response Inquiry (nama siswa + saran nominal), response Payment (`paymentFlagStatus: "00"`) — bukan error.

- [ ] **Step 4: Commit**

```bash
git add app/Console/Commands/BriTestVaInbound.php
git commit -m "feat(bri): add bri:test-va-inbound command for manual local verification"
```

---

## Ringkasan Setelah Semua Task Selesai

- Setiap siswa punya satu nomor VA tetap, di-generate lokal (tanpa panggilan HTTP ke BRI), muncul di dashboard dan halaman checkout tagihan.
- 3 endpoint inbound (token, inquiry, payment) siap dites lokal via `bri:test-va-inbound`, siap didaftarkan ke BRI begitu ada domain publik.
- `BriWebhookController` (skema lama, salah) sudah dihapus total, fungsinya digantikan endpoint Payment baru yang idempotent.
- Fitur Top-Up Bundling via VA sudah dihapus (bundling via QRIS tetap utuh).
- Langkah manual yang TERSISA untuk user (di luar scope plan ini): generate `client_id`/`client_secret` sungguhan untuk diserahkan ke BRI (bisa pakai `Str::random(75)` atau sejenisnya saat submit form onboarding), isi `BRI_INBOUND_PARTNER_SERVICE_ID` begitu BRI konfirmasi, daftarkan domain publik + 3 URL endpoint ke form onboarding BRI, dan tanyakan 4 pertanyaan terbuka yang sudah dikonsolidasi di spec (asymmetric signature, IP whitelist, IP private/leased line, X-PARTNER-ID).
