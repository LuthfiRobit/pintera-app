# Integrasi BRI SNAP API — QRIS (Fondasi + QRIS Real) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ganti `MockPaymentGateway` dengan gateway BRI SNAP asli untuk QRIS (Generate QR + Inquiry Payment), sambil VA (Virtual Account) tetap dilayani Mock — tanpa mengganggu alur VA yang sudah berjalan.

**Architecture:** `BriSnapClient` (protokol: token caching, signature asimetris/simetris, HTTP POST generik) dipakai oleh `BriSnapGateway` (domain: mapping request/response BRI ke DTO aplikasi). `HybridPaymentGateway` route `createQris()`/`checkStatus(..., 'qris')` ke `BriSnapGateway`, dan `createVirtualAccount()`/`checkStatus(..., 'va')`/`verifyCallbackSignature()` ke `MockPaymentGateway`. `PaymentGatewayInterface::checkStatus()` diberi parameter `$type` supaya Hybrid tahu harus route ke mana.

**Tech Stack:** Laravel 12, `Illuminate\Support\Facades\Http` (dengan `Http::fake()` untuk test), `Illuminate\Support\Facades\Cache`, PHP `openssl_sign`/`hash_hmac`, PHPUnit (gaya class-based, mengikuti konvensi test existing di `tests/Feature/Keuangan/` dan `tests/Unit/`).

## Global Constraints

- Spec sumber: `docs/superpowers/specs/2026-08-14-bri-snap-qris-integration-design.md`.
- **Dokumentasi protokol BRI ada di `bri-api.md` (root project).** Setiap task yang menyentuh detail signature/header/endpoint format WAJIB merujuk section spesifik di file itu — jangan hanya mengandalkan ringkasan kode di plan ini. Section judul yang relevan: **"Access Token and Signature"** (baris ±119–260) dan **"QRIS Merchant Presented Mode (MPM) Dinamis v1.1"** → **"B. Generate QR"** dan **"C. Inquiry Payment"** (baris ±869–1138). Kalau kamu implementer baru tanpa histori percakapan sesi ini, BACA bagian itu langsung dari file sebelum menulis kode — plan ini merangkum, bukan menggantikan, dokumen aslinya.
- Scope TIDAK termasuk: VA real (`createVirtualAccount()`, `checkStatus($ref, 'va')` versi BRI, `verifyCallbackSignature()` real tetap `throw`), `BriWebhookController` (tidak disentuh sama sekali), dan mengubah `BRI_PAYMENT_GATEWAY`/`services.bri.gateway` ke `'hybrid'` di `.env` sungguhan (itu langkah manual user setelah Task 7 lolos, bukan bagian task manapun di sini).
- Value literal untuk `config('services.bri.gateway')` yang SUDAH ADA di kode adalah `'snap'` (bukan `'bri'` — cek `app/Providers/AppServiceProvider.php:28` sebelum menulis kode apa pun yang menyentuh binding ini). Task 6 menambahkan opsi ketiga `'hybrid'` di samping `'mock'` (default) dan `'snap'` (sudah ada).
- Jangan pernah hit sandbox BRI asli dari test otomatis (`php artisan test` / suite CI). Semua test protokol pakai `Http::fake()`. Verifikasi sandbox asli HANYA lewat command manual di Task 7.
- Private key produksi ada di `storage/app/bri/bri_private.pem` (gitignored, JANGAN pernah dibaca isinya ke output/log/commit). Test butuh keypair sendiri yang aman di-commit — dibuat di Task 2.

---

### Task 1: BriApiException

**Files:**
- Create: `app/Exceptions/BriApiException.php`
- Test: `tests/Unit/Exceptions/BriApiExceptionTest.php`

**Interfaces:**
- Produces: `App\Exceptions\BriApiException` — constructor `(string $responseCode, string $responseMessage)`, public readonly properties `$responseCode`, `$responseMessage`, extends `\RuntimeException`. Dipakai oleh `BriSnapClient` (Task 2) untuk membungkus semua error response BRI.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\BriApiException;
use PHPUnit\Framework\TestCase;

class BriApiExceptionTest extends TestCase
{
    public function test_exposes_response_code_and_message()
    {
        $exception = new BriApiException('4007301', 'Invalid Field Format');

        $this->assertSame('4007301', $exception->responseCode);
        $this->assertSame('Invalid Field Format', $exception->responseMessage);
        $this->assertSame('BRI SNAP API error [4007301]: Invalid Field Format', $exception->getMessage());
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Exceptions/BriApiExceptionTest.php`
Expected: FAIL — `Class "App\Exceptions\BriApiException" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Exceptions;

class BriApiException extends \RuntimeException
{
    public function __construct(
        public readonly string $responseCode,
        public readonly string $responseMessage,
    ) {
        parent::__construct("BRI SNAP API error [{$responseCode}]: {$responseMessage}");
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Exceptions/BriApiExceptionTest.php`
Expected: PASS (1 test, 4 assertions)

- [ ] **Step 5: Commit**

```bash
git add app/Exceptions/BriApiException.php tests/Unit/Exceptions/BriApiExceptionTest.php
git commit -m "feat(bri): add BriApiException for BRI SNAP error responses"
```

---

### Task 2: BriSnapClient — token, signature, HTTP transport

**Files:**
- Create: `app/Services/Finance/Gateway/BriSnap/BriSnapClient.php`
- Modify: `config/services.php` (tambah entry `bri`)
- Modify: `.env` (tambah `BRI_SNAP_PARTNER_ID`, `BRI_SNAP_CHANNEL_ID`, `BRI_SNAP_MERCHANT_ID`, `BRI_SNAP_TERMINAL_ID`, `BRI_PAYMENT_GATEWAY`)
- Modify: `.env.example` (var sama, kosong)
- Create (fixture, aman di-commit — BUKAN kunci produksi): `tests/Fixtures/bri/test_private.pem`
- Test: `tests/Unit/Services/Finance/Gateway/BriSnap/BriSnapClientTest.php`

**Interfaces:**
- Produces: `App\Services\Finance\Gateway\BriSnap\BriSnapClient` dengan:
  - `__construct(string $clientId, string $clientSecret, string $baseUrl, string $privateKeyPath, string $partnerId, string $channelId)`
  - `public static function fromConfig(): self`
  - `public function currentTimestamp(): string`
  - `public function buildAsymmetricStringToSign(string $timestamp): string`
  - `public function buildSymmetricStringToSign(string $method, string $path, string $accessToken, string $bodyHash, string $timestamp): string`
  - `public function hashBody(string $bodyJson): string`
  - `public function getAccessToken(): string` (cached 850 detik, key `bri_snap_access_token`)
  - `public function post(string $path, array $body): array` — return decoded JSON response array, `throw BriApiException` kalau gagal.
  - Dipakai oleh `BriSnapGateway` (Task 5).

**Baca dulu sebelum menulis kode**: `bri-api.md`, section **"Access Token and Signature"** (baris ±119–260) — khususnya "Header Structure" (baris 133–140, format `X-SIGNATURE`/`X-TIMESTAMP`/`X-CLIENT-KEY`), "B. Signature API Access" (baris 209–253, formula HMAC_SHA512 lengkap dengan contoh `stringToSign` dan contoh hash body), dan "Request & Response Payload Sample" (baris 160–190, bentuk body `{"grantType": "client_credentials"}` dan response `{"accessToken", "tokenType", "expiresIn"}`). Semua nilai known-answer di test-test di bawah diambil LANGSUNG dari contoh di section ini — kalau ada keraguan field/format, itu yang jadi rujukan, bukan ringkasan di plan ini.

- [ ] **Step 1: Buat fixture keypair test (bukan kunci produksi — aman di-commit)**

```bash
mkdir -p tests/Fixtures/bri
openssl genrsa -out tests/Fixtures/bri/test_private.pem 2048
```

Verifikasi file ada: `ls tests/Fixtures/bri/test_private.pem` harus menampilkan file itu. Ini keypair KHUSUS TEST, tidak berhubungan dengan `storage/app/bri/bri_private.pem` produksi (yang gitignored dan tidak boleh disentuh task ini).

- [ ] **Step 2: Tambah entry config `bri` di `config/services.php`**

Tambahkan array berikut setelah entry `'fonnte'` (baris 40, sebelum penutup `];`):

```php
    'bri' => [
        'gateway' => env('BRI_PAYMENT_GATEWAY', 'mock'), // mock | snap | hybrid
        'client_id' => env('BRI_SNAP_CLIENT_ID'),
        'client_secret' => env('BRI_SNAP_CLIENT_SECRET'),
        'base_url' => env('BRI_SNAP_BASE_URL'),
        'private_key_path' => env('BRI_SNAP_PRIVATE_KEY_PATH'),
        'partner_id' => env('BRI_SNAP_PARTNER_ID'),
        'channel_id' => env('BRI_SNAP_CHANNEL_ID'),
        'merchant_id' => env('BRI_SNAP_MERCHANT_ID'),
        'terminal_id' => env('BRI_SNAP_TERMINAL_ID'),
    ],
```

- [ ] **Step 3: Tambah var baru di `.env` dan `.env.example`**

Di `.env`, setelah baris `BRI_SNAP_PRIVATE_KEY_PATH=storage/app/bri/bri_private.pem`, tambahkan:

```
BRI_SNAP_PARTNER_ID=
BRI_SNAP_CHANNEL_ID=
BRI_SNAP_MERCHANT_ID=
BRI_SNAP_TERMINAL_ID=
BRI_PAYMENT_GATEWAY=mock
```

(Kosong karena nilainya belum diketahui — user akan isi setelah cek portal BRI atau konfirmasi langsung ke BRI. `BRI_PAYMENT_GATEWAY=mock` eksplisit supaya tidak ada ambiguitas — TIDAK diubah ke `hybrid` sebagai bagian dari task manapun di plan ini.)

Lakukan hal yang sama di `.env.example` (baris yang sama persis, semua kosong termasuk `BRI_PAYMENT_GATEWAY=mock` sebagai default eksplisit untuk developer baru).

- [ ] **Step 4: Write the failing tests — signature & timestamp (pure, tanpa network)**

```php
<?php

namespace Tests\Unit\Services\Finance\Gateway\BriSnap;

use App\Services\Finance\Gateway\BriSnap\BriSnapClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BriSnapClientTest extends TestCase
{
    protected BriSnapClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->client = new BriSnapClient(
            clientId: 'kBb2FjksOMkjTgW3JwNcZc7yBaWWpIML',
            clientSecret: 'Zz9VcSiWgN96BAFG',
            baseUrl: 'https://fake-sandbox.test',
            privateKeyPath: 'tests/Fixtures/bri/test_private.pem',
            partnerId: '77777',
            channelId: '00001',
        );
    }

    public function test_current_timestamp_matches_iso8601_with_offset()
    {
        $timestamp = $this->client->currentTimestamp();

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}[+-]\d{2}:\d{2}$/',
            $timestamp
        );
    }

    public function test_asymmetric_string_to_sign_matches_bri_formula()
    {
        // Formula dari bri-api.md "Access Token and Signature", baris 137:
        // stringToSign = client_ID + "|" + X-TIMESTAMP
        $stringToSign = $this->client->buildAsymmetricStringToSign('2026-08-14T10:00:00.000+07:00');

        $this->assertSame(
            'kBb2FjksOMkjTgW3JwNcZc7yBaWWpIML|2026-08-14T10:00:00.000+07:00',
            $stringToSign
        );
    }

    public function test_symmetric_string_to_sign_matches_documented_example()
    {
        // Contoh persis dari bri-api.md "B. Signature API Access", baris 222:
        // POST:/snap/v1.0/dummy:muhpwhwOkPRU9nNXYnyYHj8t54x3:8b4e9e83b5231cff4f84358ec8ca81951cfe9f999f635b1566452a501d5c23b2:2021-11-29T09:22:18.172+07:00
        $stringToSign = $this->client->buildSymmetricStringToSign(
            'POST',
            '/snap/v1.0/dummy',
            'muhpwhwOkPRU9nNXYnyYHj8t54x3',
            '8b4e9e83b5231cff4f84358ec8ca81951cfe9f999f635b1566452a501d5c23b2',
            '2021-11-29T09:22:18.172+07:00'
        );

        $this->assertSame(
            'POST:/snap/v1.0/dummy:muhpwhwOkPRU9nNXYnyYHj8t54x3:8b4e9e83b5231cff4f84358ec8ca81951cfe9f999f635b1566452a501d5c23b2:2021-11-29T09:22:18.172+07:00',
            $stringToSign
        );
    }

    public function test_hash_body_matches_documented_known_answer()
    {
        // Dari bri-api.md "B. Signature API Access" > "5. Body", baris 248-250:
        // Body: {"hello":"world"} -> SHA256 Result: 93a23971a914e5eacbf0a8d25154cda309c3c1c72fbb9914d47c60f3cb681588
        $this->assertSame(
            '93a23971a914e5eacbf0a8d25154cda309c3c1c72fbb9914d47c60f3cb681588',
            $this->client->hashBody('{"hello":"world"}')
        );
    }
}
```

- [ ] **Step 5: Run tests to verify they fail**

Run: `php artisan test tests/Unit/Services/Finance/Gateway/BriSnap/BriSnapClientTest.php`
Expected: FAIL — `Class "App\Services\Finance\Gateway\BriSnap\BriSnapClient" not found`.

- [ ] **Step 6: Write BriSnapClient — bagian signature & timestamp**

```php
<?php

namespace App\Services\Finance\Gateway\BriSnap;

use App\Exceptions\BriApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BriSnapClient
{
    public function __construct(
        protected string $clientId,
        protected string $clientSecret,
        protected string $baseUrl,
        protected string $privateKeyPath,
        protected string $partnerId,
        protected string $channelId,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            clientId: (string) config('services.bri.client_id'),
            clientSecret: (string) config('services.bri.client_secret'),
            baseUrl: (string) config('services.bri.base_url'),
            privateKeyPath: (string) config('services.bri.private_key_path'),
            partnerId: (string) config('services.bri.partner_id'),
            channelId: (string) config('services.bri.channel_id'),
        );
    }

    public function currentTimestamp(): string
    {
        $now = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));

        return $now->format('Y-m-d\TH:i:s.v') . $now->format('P');
    }

    public function buildAsymmetricStringToSign(string $timestamp): string
    {
        return $this->clientId . '|' . $timestamp;
    }

    public function buildSymmetricStringToSign(string $method, string $path, string $accessToken, string $bodyHash, string $timestamp): string
    {
        return "{$method}:{$path}:{$accessToken}:{$bodyHash}:{$timestamp}";
    }

    public function hashBody(string $bodyJson): string
    {
        return strtolower(hash('sha256', $bodyJson));
    }

    protected function asymmetricSignature(string $timestamp): string
    {
        $stringToSign = $this->buildAsymmetricStringToSign($timestamp);
        $privateKey = file_get_contents(base_path($this->privateKeyPath));

        openssl_sign($stringToSign, $signatureRaw, $privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signatureRaw);
    }

    protected function symmetricSignature(string $method, string $path, string $accessToken, string $bodyJson, string $timestamp): string
    {
        $bodyHash = $this->hashBody($bodyJson);
        $stringToSign = $this->buildSymmetricStringToSign($method, $path, $accessToken, $bodyHash, $timestamp);

        return base64_encode(hash_hmac('sha512', $stringToSign, $this->clientSecret, true));
    }
}
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Unit/Services/Finance/Gateway/BriSnap/BriSnapClientTest.php`
Expected: PASS (4 tests)

- [ ] **Step 8: Write the failing tests — getAccessToken() dan post() (pakai Http::fake, tanpa network asli)**

Tambahkan ke `tests/Unit/Services/Finance/Gateway/BriSnap/BriSnapClientTest.php`:

```php
    public function test_get_access_token_returns_token_from_response()
    {
        Http::fake([
            'https://fake-sandbox.test/snap/v1.0/access-token/b2b' => Http::response([
                'accessToken' => 'jwy7GgloLqfqbZ9OnxGxmYOuGu85',
                'tokenType' => 'BearerToken',
                'expiresIn' => '899',
            ], 200),
        ]);

        $token = $this->client->getAccessToken();

        $this->assertSame('jwy7GgloLqfqbZ9OnxGxmYOuGu85', $token);
    }

    public function test_get_access_token_is_cached_across_calls()
    {
        Http::fake([
            'https://fake-sandbox.test/snap/v1.0/access-token/b2b' => Http::response([
                'accessToken' => 'jwy7GgloLqfqbZ9OnxGxmYOuGu85',
                'tokenType' => 'BearerToken',
                'expiresIn' => '899',
            ], 200),
        ]);

        $this->client->getAccessToken();
        $this->client->getAccessToken();

        Http::assertSentCount(1);
    }

    public function test_get_access_token_throws_bri_api_exception_on_failure()
    {
        Http::fake([
            'https://fake-sandbox.test/snap/v1.0/access-token/b2b' => Http::response([
                'responseCode' => '4007301',
                'responseMessage' => 'Invalid Field Format',
            ], 400),
        ]);

        $this->expectException(BriApiException::class);

        $this->client->getAccessToken();
    }

    public function test_post_sends_body_matching_signature_and_returns_decoded_response()
    {
        Http::fake([
            'https://fake-sandbox.test/snap/v1.0/access-token/b2b' => Http::response([
                'accessToken' => 'jwy7GgloLqfqbZ9OnxGxmYOuGu85',
                'tokenType' => 'BearerToken',
                'expiresIn' => '899',
            ], 200),
            'https://fake-sandbox.test/snap/v1.1/qr/qr-mpm-generate' => Http::response([
                'responseCode' => '2004700',
                'responseMessage' => 'Successful',
                'partnerReferenceNo' => 'TEST0001',
                'qrContent' => '0002XXXXXXXXX',
                'referenceNo' => '409676201434',
            ], 200),
        ]);

        $result = $this->client->post('/snap/v1.1/qr/qr-mpm-generate', [
            'partnerReferenceNo' => 'TEST0001',
        ]);

        $this->assertSame('0002XXXXXXXXX', $result['qrContent']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://fake-sandbox.test/snap/v1.1/qr/qr-mpm-generate'
                && $request->hasHeader('Authorization', 'Bearer jwy7GgloLqfqbZ9OnxGxmYOuGu85')
                && $request->hasHeader('X-PARTNER-ID', '77777')
                && $request->hasHeader('CHANNEL-ID', '00001')
                && $request->hasHeader('X-SIGNATURE')
                && $request->hasHeader('X-TIMESTAMP');
        });
    }

    public function test_post_throws_bri_api_exception_on_non_success_response_code()
    {
        Http::fake([
            'https://fake-sandbox.test/snap/v1.0/access-token/b2b' => Http::response([
                'accessToken' => 'jwy7GgloLqfqbZ9OnxGxmYOuGu85',
                'tokenType' => 'BearerToken',
                'expiresIn' => '899',
            ], 200),
            'https://fake-sandbox.test/snap/v1.1/qr/qr-mpm-generate' => Http::response([
                'responseCode' => '4004701',
                'responseMessage' => 'Invalid Field Format',
            ], 400),
        ]);

        $this->expectException(BriApiException::class);

        $this->client->post('/snap/v1.1/qr/qr-mpm-generate', ['partnerReferenceNo' => 'TEST0001']);
    }
```

- [ ] **Step 9: Run tests to verify they fail**

Run: `php artisan test tests/Unit/Services/Finance/Gateway/BriSnap/BriSnapClientTest.php`
Expected: FAIL — `Call to undefined method ...::getAccessToken()` / `::post()`.

- [ ] **Step 10: Tambahkan `getAccessToken()` dan `post()` ke `BriSnapClient`**

Tambahkan method berikut di dalam class `BriSnapClient` (setelah `symmetricSignature()`):

```php
    public function getAccessToken(): string
    {
        return Cache::remember('bri_snap_access_token', 850, function () {
            $timestamp = $this->currentTimestamp();

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-CLIENT-KEY' => $this->clientId,
                'X-TIMESTAMP' => $timestamp,
                'X-SIGNATURE' => $this->asymmetricSignature($timestamp),
            ])->post($this->baseUrl . '/snap/v1.0/access-token/b2b', [
                'grantType' => 'client_credentials',
            ]);

            $data = $response->json() ?? [];

            if (!$response->successful() || empty($data['accessToken'])) {
                throw new BriApiException(
                    (string) ($data['responseCode'] ?? (string) $response->status()),
                    (string) ($data['responseMessage'] ?? 'Failed to retrieve BRI SNAP access token')
                );
            }

            return $data['accessToken'];
        });
    }

    public function post(string $path, array $body): array
    {
        $accessToken = $this->getAccessToken();
        $timestamp = $this->currentTimestamp();
        $bodyJson = json_encode($body, JSON_UNESCAPED_SLASHES);
        $signature = $this->symmetricSignature('POST', $path, $accessToken, $bodyJson, $timestamp);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
            'X-TIMESTAMP' => $timestamp,
            'X-SIGNATURE' => $signature,
            'X-PARTNER-ID' => $this->partnerId,
            'CHANNEL-ID' => $this->channelId,
            'X-EXTERNAL-ID' => (string) round(microtime(true) * 1000),
        ])->withBody($bodyJson, 'application/json')->post($this->baseUrl . $path);

        $data = $response->json() ?? [];

        if (!$response->successful() || (isset($data['responseCode']) && !str_starts_with((string) $data['responseCode'], '200'))) {
            throw new BriApiException(
                (string) ($data['responseCode'] ?? (string) $response->status()),
                (string) ($data['responseMessage'] ?? 'BRI SNAP API request failed')
            );
        }

        return $data;
    }
```

**Catatan implementasi**: `withBody($bodyJson, ...)` dipanggil SEBELUM `->post($url)` tanpa argumen kedua — ini penting supaya body yang benar-benar dikirim persis sama (byte-for-byte) dengan `$bodyJson` yang di-hash untuk signature. Jangan pernah panggil `->post($url, $body)` dengan array `$body` di sini — Laravel akan re-encode array itu sendiri dan hasilnya bisa beda whitespace/urutan key dari `$bodyJson` yang sudah di-hash, membuat signature tidak valid di sisi BRI.

- [ ] **Step 11: Run tests to verify they pass**

Run: `php artisan test tests/Unit/Services/Finance/Gateway/BriSnap/BriSnapClientTest.php`
Expected: PASS (9 tests)

- [ ] **Step 12: Commit**

```bash
git add app/Services/Finance/Gateway/BriSnap/BriSnapClient.php \
        config/services.php .env.example \
        tests/Fixtures/bri/test_private.pem \
        tests/Unit/Services/Finance/Gateway/BriSnap/BriSnapClientTest.php
git commit -m "feat(bri): add BriSnapClient with token caching and signature generation"
```

(`.env` tidak masuk git — sudah gitignored, cukup edit lokal.)

---

### Task 3: `PaymentGatewayInterface::checkStatus()` — tambah parameter `$type`

**Files:**
- Modify: `app/Contracts/PaymentGatewayInterface.php`
- Modify: `app/Services/Finance/Gateway/MockPaymentGateway.php`
- Modify: `app/Services/Finance/Gateway/BriSnapGateway.php`
- Modify: `app/Console/Commands/ReconcilePayments.php:59` dan `:104`
- Modify: `tests/Feature/Keuangan/GatewayImplementationTest.php:44`

**Interfaces:**
- Consumes: tidak ada dependency baru dari task sebelumnya.
- Produces: `PaymentGatewayInterface::checkStatus(string $channelReference, string $type): PaymentStatusResult` — `$type` adalah `'va'` atau `'qris'`. Dipakai `HybridPaymentGateway` (Task 6) untuk routing.

- [ ] **Step 1: Update signature di interface**

`app/Contracts/PaymentGatewayInterface.php` — ganti baris 30:

```php
    /**
     * Check payment status by channel reference (VA number or QRIS reference).
     */
    public function checkStatus(string $channelReference, string $type): PaymentStatusResult;
```

- [ ] **Step 2: Update `MockPaymentGateway::checkStatus()`**

`app/Services/Finance/Gateway/MockPaymentGateway.php:45-48` — ganti jadi:

```php
    public function checkStatus(string $channelReference, string $type): PaymentStatusResult
    {
        return new PaymentStatusResult('PAID', ['mock_response' => true]);
    }
```

- [ ] **Step 3: Update `BriSnapGateway::checkStatus()`**

`app/Services/Finance/Gateway/BriSnapGateway.php:28-31` — ganti jadi (implementasi asli QRIS masih Task 5, di sini baru signature-nya saja):

```php
    public function checkStatus(string $channelReference, string $type): PaymentStatusResult
    {
        throw new \RuntimeException('BriSnapGateway not implemented: awaiting credentials');
    }
```

- [ ] **Step 4: Update 2 call site di `ReconcilePayments`**

`app/Console/Commands/ReconcilePayments.php:59` — ganti:
```php
                $statusResult = $this->gateway->checkStatus($va->va_number);
```
menjadi:
```php
                $statusResult = $this->gateway->checkStatus($va->va_number, 'va');
```

`app/Console/Commands/ReconcilePayments.php:104` — ganti:
```php
                $statusResult = $this->gateway->checkStatus($qris->qr_code); 
```
menjadi (masih pakai `qr_code` di task ini — pindah ke `reference_no` terjadi di Task 4 setelah kolomnya ada):
```php
                $statusResult = $this->gateway->checkStatus($qris->qr_code, 'qris');
```

- [ ] **Step 5: Update test existing yang memanggil `checkStatus()`**

`tests/Feature/Keuangan/GatewayImplementationTest.php:44` — ganti:
```php
        $statusResult = $gateway->checkStatus('MOCK-VA-12345');
```
menjadi:
```php
        $statusResult = $gateway->checkStatus('MOCK-VA-12345', 'va');
```

- [ ] **Step 6: Run test untuk memastikan tidak ada regresi**

Run: `php artisan test tests/Feature/Keuangan/GatewayImplementationTest.php`
Expected: PASS (3 tests)

Run juga (memastikan tidak ada call site lain yang kelewat):
```bash
grep -rn "->checkStatus(" app/ tests/
```
Expected: SEMUA hasil punya 2 argumen (`, 'va')` atau `, 'qris')`). Kalau ada yang masih 1 argumen, perbaiki juga sebelum lanjut.

- [ ] **Step 7: Run full Keuangan test scope untuk memastikan tidak ada regresi di tempat lain**

Run: `php artisan test tests/Feature/Keuangan/`
Expected: PASS, semua test hijau (jangan jalankan full suite tanpa filter — mahal, lihat catatan project soal ini).

- [ ] **Step 8: Commit**

```bash
git add app/Contracts/PaymentGatewayInterface.php \
        app/Services/Finance/Gateway/MockPaymentGateway.php \
        app/Services/Finance/Gateway/BriSnapGateway.php \
        app/Console/Commands/ReconcilePayments.php \
        tests/Feature/Keuangan/GatewayImplementationTest.php
git commit -m "refactor(bri): add \$type parameter to PaymentGatewayInterface::checkStatus()"
```

---

### Task 4: Migration `reference_no` + `PaymentService` menyimpannya

**Files:**
- Create: `database/migrations/2026_08_14_100000_add_reference_no_to_bri_qris_payments_table.php`
- Modify: `app/Services/Finance/PaymentService.php:98-106` (`createQrisPayment`) dan `:132-140` (`createQrisPaymentWithTopup`)
- Modify: `app/Console/Commands/ReconcilePayments.php:104` (ganti argumen dari `$qris->qr_code` ke `$qris->reference_no`)
- Test: tambahkan ke `tests/Feature/Keuangan/PaymentServiceTest.php`

**Interfaces:**
- Consumes: `PaymentGatewayInterface::checkStatus(string, string)` dari Task 3.
- Produces: kolom `bri_qris_payments.reference_no` (nullable string). Dipakai `BriSnapGateway::checkStatus()` di Task 5 sebagai `originalReferenceNo` untuk Inquiry Payment.

- [ ] **Step 1: Write the failing test**

Tambahkan ke `tests/Feature/Keuangan/PaymentServiceTest.php` (setelah `test_create_va_payment_success`, sebelum `test_cannot_create_payment_if_bill_cancelled`):

```php
    public function test_create_qris_payment_stores_null_reference_no_for_mock_gateway()
    {
        $siswa = Siswa::factory()->create();

        $jenisTagihan = JenisTagihan::factory()->create();

        $tagihan = Tagihan::factory()->create([
            'status' => 'belum_bayar'
        ]);

        TagihanItem::factory()->create([
            'tagihan_id' => $tagihan->id,
            'jenis_tagihan_id' => $jenisTagihan->id
        ]);

        $pembayaran = $this->service->createQrisPayment($siswa, collect([$tagihan]));

        $this->assertNotNull($pembayaran->briQrisPayment);
        $this->assertNull($pembayaran->briQrisPayment->reference_no);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/PaymentServiceTest.php --filter=test_create_qris_payment_stores_null_reference_no_for_mock_gateway`
Expected: FAIL — `Unknown column 'reference_no'` (kolom belum ada).

- [ ] **Step 3: Buat migration**

```bash
php artisan make:migration add_reference_no_to_bri_qris_payments_table --table=bri_qris_payments
```

Isi file yang di-generate (rename timestamp-nya kalau perlu supaya sesuai `2026_08_14_100000_add_reference_no_to_bri_qris_payments_table.php`):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bri_qris_payments', function (Blueprint $table) {
            $table->string('reference_no')->nullable()->after('qr_code');
        });
    }

    public function down(): void
    {
        Schema::table('bri_qris_payments', function (Blueprint $table) {
            $table->dropColumn('reference_no');
        });
    }
};
```

- [ ] **Step 4: Jalankan migration**

Run: `php artisan migrate`
Expected: `2026_08_14_100000_add_reference_no_to_bri_qris_payments_table ... DONE`

- [ ] **Step 5: Run test lagi — masih fail (kolom ada, tapi belum diisi)**

Run: `php artisan test tests/Feature/Keuangan/PaymentServiceTest.php --filter=test_create_qris_payment_stores_null_reference_no_for_mock_gateway`
Expected: PASS sebenarnya sudah bisa lolos di titik ini karena default `null` — tapi jalankan untuk konfirmasi sebelum lanjut ke Step 6 (yang mengubah behaviour untuk kasus BRI asli nanti).

- [ ] **Step 6: Update `PaymentService::createQrisPayment()` dan `createQrisPaymentWithTopup()`**

`app/Services/Finance/PaymentService.php` — di `createQrisPayment()` (sekitar baris 98-106), tambahkan `'reference_no'` ke `BriQrisPayment::create([...])`:

```php
            BriQrisPayment::create([
                'pembayaran_id' => $pembayaran->id,
                'qris_type' => 'DIRECT',
                'amount' => $qrisResult->amount,
                'qr_code' => $qrisResult->qrCodeData,
                'reference_no' => $qrisResult->payload['referenceNo'] ?? null,
                'expired_at' => $qrisResult->expiredAt,
                'status' => 'WAITING',
                'callback_payload' => $qrisResult->payload,
            ]);
```

Lakukan perubahan yang SAMA persis di `createQrisPaymentWithTopup()` (sekitar baris 132-140) — tambahkan baris `'reference_no' => $qrisResult->payload['referenceNo'] ?? null,` di posisi yang sama dalam array `BriQrisPayment::create([...])` di method itu.

(`MockPaymentGateway::createQris()` tidak pernah mengisi key `referenceNo` di payload-nya, jadi `?? null` memastikan Mock tetap jalan tanpa error — konsisten dengan test Step 1 yang mengharapkan `null`.)

- [ ] **Step 7: Update `ReconcilePayments` supaya pakai `reference_no`, bukan `qr_code`**

`app/Console/Commands/ReconcilePayments.php:104` — ganti:
```php
                $statusResult = $this->gateway->checkStatus($qris->qr_code, 'qris');
```
menjadi:
```php
                $statusResult = $this->gateway->checkStatus($qris->reference_no, 'qris');
```

- [ ] **Step 8: Run test untuk memastikan semua lolos**

Run: `php artisan test tests/Feature/Keuangan/PaymentServiceTest.php`
Expected: PASS, semua test di file itu hijau.

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_08_14_100000_add_reference_no_to_bri_qris_payments_table.php \
        app/Services/Finance/PaymentService.php \
        app/Console/Commands/ReconcilePayments.php \
        tests/Feature/Keuangan/PaymentServiceTest.php
git commit -m "feat(bri): store BRI reference_no on bri_qris_payments for status inquiry"
```

---

### Task 5: `BriSnapGateway::createQris()` + `checkStatus(..., 'qris')` — implementasi asli

**Files:**
- Modify: `app/Services/Finance/Gateway/BriSnapGateway.php` (full rewrite method-methodnya)
- Modify: `app/Providers/AppServiceProvider.php` (binding `BriSnapClient` sebagai singleton + `$app->make()` untuk `BriSnapGateway`)
- Modify: `tests/Feature/Keuangan/GatewayImplementationTest.php:49-58` (`test_bri_snap_gateway_throws_not_implemented_exception` — constructor `BriSnapGateway` sekarang butuh `BriSnapClient`)
- Test: `tests/Feature/Keuangan/BriSnapGatewayQrisTest.php` (baru)

**Interfaces:**
- Consumes: `BriSnapClient::post(string, array): array` dari Task 2. `PaymentGatewayInterface::checkStatus(string, string)` dari Task 3. `BriQrisPayment.reference_no` dari Task 4.
- Produces: `BriSnapGateway::createQris()` dan `checkStatus($ref, 'qris')` real. Dipakai `HybridPaymentGateway` di Task 6.

**Baca dulu sebelum menulis kode**: `bri-api.md`, section **"QRIS Merchant Presented Mode (MPM) Dinamis v1.1"** → **"B. Generate QR"** (baris ±932–1007, terutama "Request Structure" baris 961-966, "Request Structure in Object 'amount'" baris 968-973, dan "Request & Response Payload Sample" baris 985-1007) dan **"C. Inquiry Payment"** (baris ±1011–1117, terutama "Request Structure" baris 1040-1044, "Response Structure" baris 1054-1065, dan payload sample baris 1085-1117). Field `serviceCode` untuk QRIS SELALU `"47"` per contoh di dokumen — nilai `latestTransactionStatus` `"00"` berarti sukses (lihat tabel enumerasi di baris 1060).

**Catatan panjang field**: `partnerReferenceNo` di tabel "Request Structure" tertulis Length 6, tapi contoh nilainya `"1234567890133"` (13 digit) — dokumen BRI sendiri kontradiktif di sini. Ikuti bentuk contoh (bukan angka "6" di kolom Length), dan kalau nanti verifikasi lewat `bri:test-qris` (Task 7) sandbox menolak `channel_reference` (UUID 36 karakter) karena kepanjangan, itu perlu ditindaklanjuti terpisah (di luar scope task ini) — jangan diam-diam dipotong tanpa verifikasi nyata.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Keuangan/BriSnapGatewayQrisTest.php`:

```php
<?php

namespace Tests\Feature\Keuangan;

use App\DTO\PaymentStatusResult;
use App\DTO\QrisResult;
use App\Exceptions\BriApiException;
use App\Models\Pembayaran;
use App\Services\Finance\Gateway\BriSnap\BriSnapClient;
use App\Services\Finance\Gateway\BriSnapGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BriSnapGatewayQrisTest extends TestCase
{
    use RefreshDatabase;

    protected BriSnapGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        config([
            'services.bri.client_id' => 'kBb2FjksOMkjTgW3JwNcZc7yBaWWpIML',
            'services.bri.client_secret' => 'Zz9VcSiWgN96BAFG',
            'services.bri.base_url' => 'https://fake-sandbox.test',
            'services.bri.private_key_path' => 'tests/Fixtures/bri/test_private.pem',
            'services.bri.partner_id' => '77777',
            'services.bri.channel_id' => '00001',
            'services.bri.merchant_id' => '00007100010926',
            'services.bri.terminal_id' => '213141251124',
        ]);

        $this->gateway = new BriSnapGateway(BriSnapClient::fromConfig());

        Http::fake([
            'https://fake-sandbox.test/snap/v1.0/access-token/b2b' => Http::response([
                'accessToken' => 'jwy7GgloLqfqbZ9OnxGxmYOuGu85',
                'tokenType' => 'BearerToken',
                'expiresIn' => '899',
            ], 200),
        ]);
    }

    public function test_create_qris_maps_bri_response_to_qris_result()
    {
        Http::fake([
            'https://fake-sandbox.test/snap/v1.0/access-token/b2b' => Http::response([
                'accessToken' => 'jwy7GgloLqfqbZ9OnxGxmYOuGu85',
                'tokenType' => 'BearerToken',
                'expiresIn' => '899',
            ], 200),
            'https://fake-sandbox.test/snap/v1.1/qr/qr-mpm-generate' => Http::response([
                'responseCode' => '2004700',
                'responseMessage' => 'Successful',
                'partnerReferenceNo' => '1234567890133',
                'qrContent' => '0002XXXXXXXXX',
                'referenceNo' => '409676201434',
            ], 200),
        ]);

        $pembayaran = Pembayaran::factory()->create(['amount' => 123456]);

        $result = $this->gateway->createQris($pembayaran, 'DIRECT');

        $this->assertInstanceOf(QrisResult::class, $result);
        $this->assertSame('0002XXXXXXXXX', $result->qrCodeData);
        $this->assertSame('409676201434', $result->payload['referenceNo']);
    }

    public function test_check_status_qris_maps_success_status()
    {
        Http::fake([
            'https://fake-sandbox.test/snap/v1.0/access-token/b2b' => Http::response([
                'accessToken' => 'jwy7GgloLqfqbZ9OnxGxmYOuGu85',
                'tokenType' => 'BearerToken',
                'expiresIn' => '899',
            ], 200),
            'https://fake-sandbox.test/snap/v1.1/qr/qr-mpm-query' => Http::response([
                'responseCode' => '2005100',
                'responseMessage' => 'Successful',
                'originalReferenceNo' => '290005165369',
                'serviceCode' => '47',
                'latestTransactionStatus' => '00',
                'transactionStatusDesc' => 'Successfully',
                'amount' => ['value' => '2000.00', 'currency' => 'IDR'],
            ], 200),
        ]);

        $result = $this->gateway->checkStatus('290005165369', 'qris');

        $this->assertInstanceOf(PaymentStatusResult::class, $result);
        $this->assertSame('PAID', $result->status);
    }

    public function test_check_status_qris_maps_pending_status()
    {
        Http::fake([
            'https://fake-sandbox.test/snap/v1.0/access-token/b2b' => Http::response([
                'accessToken' => 'jwy7GgloLqfqbZ9OnxGxmYOuGu85',
                'tokenType' => 'BearerToken',
                'expiresIn' => '899',
            ], 200),
            'https://fake-sandbox.test/snap/v1.1/qr/qr-mpm-query' => Http::response([
                'responseCode' => '2005100',
                'responseMessage' => 'Successful',
                'originalReferenceNo' => '290005165369',
                'serviceCode' => '47',
                'latestTransactionStatus' => '01',
                'transactionStatusDesc' => 'Initiated',
                'amount' => ['value' => '2000.00', 'currency' => 'IDR'],
            ], 200),
        ]);

        $result = $this->gateway->checkStatus('290005165369', 'qris');

        $this->assertSame('PENDING', $result->status);
    }

    public function test_check_status_va_still_throws_not_implemented()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('BriSnapGateway not implemented: awaiting credentials');

        $this->gateway->checkStatus('some-va-number', 'va');
    }

    public function test_create_qris_throws_bri_api_exception_on_error_response()
    {
        Http::fake([
            'https://fake-sandbox.test/snap/v1.0/access-token/b2b' => Http::response([
                'accessToken' => 'jwy7GgloLqfqbZ9OnxGxmYOuGu85',
                'tokenType' => 'BearerToken',
                'expiresIn' => '899',
            ], 200),
            'https://fake-sandbox.test/snap/v1.1/qr/qr-mpm-generate' => Http::response([
                'responseCode' => '4004701',
                'responseMessage' => 'Invalid Field Format',
            ], 400),
        ]);

        $pembayaran = Pembayaran::factory()->create(['amount' => 10000]);

        $this->expectException(BriApiException::class);

        $this->gateway->createQris($pembayaran, 'DIRECT');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/BriSnapGatewayQrisTest.php`
Expected: FAIL — `Too few arguments to function BriSnapGateway::__construct()` (constructor belum ada).

- [ ] **Step 3: Tulis ulang `BriSnapGateway`**

Replace seluruh isi `app/Services/Finance/Gateway/BriSnapGateway.php`:

```php
<?php

namespace App\Services\Finance\Gateway;

use App\Contracts\PaymentGatewayInterface;
use App\DTO\PaymentStatusResult;
use App\DTO\QrisResult;
use App\DTO\VirtualAccountResult;
use App\Models\Pembayaran;
use App\Services\Finance\Gateway\BriSnap\BriSnapClient;

class BriSnapGateway implements PaymentGatewayInterface
{
    public function __construct(protected BriSnapClient $client)
    {
    }

    public function createVirtualAccount(Pembayaran $pembayaran, string $vaType): VirtualAccountResult
    {
        throw new \RuntimeException('BriSnapGateway not implemented: awaiting credentials');
    }

    public function createQris(Pembayaran $pembayaran, string $qrisType): QrisResult
    {
        $response = $this->client->post('/snap/v1.1/qr/qr-mpm-generate', [
            'partnerReferenceNo' => (string) $pembayaran->channel_reference,
            'amount' => [
                'value' => number_format((float) $pembayaran->amount, 2, '.', ''),
                'currency' => 'IDR',
            ],
            'merchantId' => (string) config('services.bri.merchant_id'),
            'terminalId' => (string) config('services.bri.terminal_id'),
        ]);

        return new QrisResult(
            $response['qrContent'],
            (float) $pembayaran->amount,
            now()->addMinutes(15),
            $response
        );
    }

    public function verifyCallbackSignature(string $payload, string $signature): bool
    {
        throw new \RuntimeException('BriSnapGateway not implemented: awaiting credentials');
    }

    public function checkStatus(string $channelReference, string $type): PaymentStatusResult
    {
        if ($type === 'va') {
            throw new \RuntimeException('BriSnapGateway not implemented: awaiting credentials');
        }

        $response = $this->client->post('/snap/v1.1/qr/qr-mpm-query', [
            'originalReferenceNo' => $channelReference,
            'serviceCode' => '47',
            'additionalInfo' => [
                'terminalId' => (string) config('services.bri.terminal_id'),
            ],
        ]);

        $status = ($response['latestTransactionStatus'] ?? '') === '00' ? 'PAID' : 'PENDING';

        return new PaymentStatusResult($status, $response);
    }
}
```

- [ ] **Step 4: Daftarkan `BriSnapClient` sebagai singleton dan update binding `BriSnapGateway` di `AppServiceProvider`**

`app/Providers/AppServiceProvider.php` — tambah `use App\Services\Finance\Gateway\BriSnap\BriSnapClient;` di bagian import, lalu di `register()` (sebelum `$this->app->bind(PaymentGatewayInterface::class, ...)`):

```php
        $this->app->singleton(BriSnapClient::class, fn () => BriSnapClient::fromConfig());
```

Lalu ganti baris `return new BriSnapGateway();` (baris 29) menjadi:
```php
                return $app->make(BriSnapGateway::class);
```

- [ ] **Step 5: Update test existing yang instansiasi `BriSnapGateway` langsung**

`tests/Feature/Keuangan/GatewayImplementationTest.php:49-58` — ganti:
```php
    public function test_bri_snap_gateway_throws_not_implemented_exception()
    {
        $gateway = new BriSnapGateway();
        $pembayaran = Pembayaran::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('BriSnapGateway not implemented: awaiting credentials');

        $gateway->createVirtualAccount($pembayaran, 'WALLET_PERMANENT');
    }
```
menjadi:
```php
    public function test_bri_snap_gateway_throws_not_implemented_exception()
    {
        $gateway = new BriSnapGateway(BriSnapClient::fromConfig());
        $pembayaran = Pembayaran::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('BriSnapGateway not implemented: awaiting credentials');

        $gateway->createVirtualAccount($pembayaran, 'WALLET_PERMANENT');
    }
```

Tambahkan import `use App\Services\Finance\Gateway\BriSnap\BriSnapClient;` di bagian atas file itu.

- [ ] **Step 6: Run test untuk memastikan semua lolos**

Run: `php artisan test tests/Feature/Keuangan/BriSnapGatewayQrisTest.php tests/Feature/Keuangan/GatewayImplementationTest.php`
Expected: PASS, semua test hijau (5 test baru + 3 test existing).

- [ ] **Step 7: Run scope Keuangan penuh untuk memastikan binding baru tidak merusak apa pun**

Run: `php artisan test tests/Feature/Keuangan/`
Expected: PASS, semua hijau.

- [ ] **Step 8: Commit**

```bash
git add app/Services/Finance/Gateway/BriSnapGateway.php \
        app/Providers/AppServiceProvider.php \
        tests/Feature/Keuangan/GatewayImplementationTest.php \
        tests/Feature/Keuangan/BriSnapGatewayQrisTest.php
git commit -m "feat(bri): implement BriSnapGateway::createQris() and checkStatus(qris) via BriSnapClient"
```

---

### Task 6: `HybridPaymentGateway` + opsi binding `'hybrid'`

**Files:**
- Create: `app/Services/Finance/Gateway/HybridPaymentGateway.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Keuangan/HybridPaymentGatewayTest.php` (baru)
- Modify: `tests/Feature/Keuangan/GatewayImplementationTest.php` (`test_gateway_binding_in_service_provider_based_on_config` — tambah assertion `'hybrid'`)

**Interfaces:**
- Consumes: `BriSnapGateway` (Task 5), `MockPaymentGateway` (existing).
- Produces: `HybridPaymentGateway implements PaymentGatewayInterface`. Ini komponen TERAKHIR sebelum gateway bisa dipakai lewat `config('services.bri.gateway') = 'hybrid'` (tapi TIDAK di-flip ke `.env` sebagai bagian task ini — lihat Global Constraints).

- [x] **Step 1: Write the failing test**

Create `tests/Feature/Keuangan/HybridPaymentGatewayTest.php`:

```php
<?php

namespace Tests\Feature\Keuangan;

use App\DTO\PaymentStatusResult;
use App\DTO\QrisResult;
use App\DTO\VirtualAccountResult;
use App\Models\Pembayaran;
use App\Services\Finance\Gateway\BriSnapGateway;
use App\Services\Finance\Gateway\HybridPaymentGateway;
use App\Services\Finance\Gateway\MockPaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class HybridPaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_qris_routes_to_bri_snap_gateway()
    {
        $pembayaran = Pembayaran::factory()->create();
        $expected = new QrisResult('QR-CONTENT', 10000.0, now()->addMinutes(15), []);

        $bri = Mockery::mock(BriSnapGateway::class);
        $bri->shouldReceive('createQris')->once()->with($pembayaran, 'DIRECT')->andReturn($expected);
        $mock = Mockery::mock(MockPaymentGateway::class);
        $mock->shouldNotReceive('createQris');

        $hybrid = new HybridPaymentGateway($bri, $mock);

        $result = $hybrid->createQris($pembayaran, 'DIRECT');

        $this->assertSame($expected, $result);
    }

    public function test_create_virtual_account_routes_to_mock_gateway()
    {
        $pembayaran = Pembayaran::factory()->create();
        $expected = new VirtualAccountResult('MOCK-VA-000001', 10000, now()->addHours(24), []);

        $bri = Mockery::mock(BriSnapGateway::class);
        $bri->shouldNotReceive('createVirtualAccount');
        $mock = Mockery::mock(MockPaymentGateway::class);
        $mock->shouldReceive('createVirtualAccount')->once()->with($pembayaran, 'BILL_DIRECT')->andReturn($expected);

        $hybrid = new HybridPaymentGateway($bri, $mock);

        $result = $hybrid->createVirtualAccount($pembayaran, 'BILL_DIRECT');

        $this->assertSame($expected, $result);
    }

    public function test_check_status_qris_routes_to_bri_snap_gateway()
    {
        $expected = new PaymentStatusResult('PAID', []);

        $bri = Mockery::mock(BriSnapGateway::class);
        $bri->shouldReceive('checkStatus')->once()->with('REF-1', 'qris')->andReturn($expected);
        $mock = Mockery::mock(MockPaymentGateway::class);
        $mock->shouldNotReceive('checkStatus');

        $hybrid = new HybridPaymentGateway($bri, $mock);

        $result = $hybrid->checkStatus('REF-1', 'qris');

        $this->assertSame($expected, $result);
    }

    public function test_check_status_va_routes_to_mock_gateway()
    {
        $expected = new PaymentStatusResult('PAID', ['mock_response' => true]);

        $bri = Mockery::mock(BriSnapGateway::class);
        $bri->shouldNotReceive('checkStatus');
        $mock = Mockery::mock(MockPaymentGateway::class);
        $mock->shouldReceive('checkStatus')->once()->with('VA-1', 'va')->andReturn($expected);

        $hybrid = new HybridPaymentGateway($bri, $mock);

        $result = $hybrid->checkStatus('VA-1', 'va');

        $this->assertSame($expected, $result);
    }

    public function test_verify_callback_signature_routes_to_mock_gateway()
    {
        $bri = Mockery::mock(BriSnapGateway::class);
        $bri->shouldNotReceive('verifyCallbackSignature');
        $mock = Mockery::mock(MockPaymentGateway::class);
        $mock->shouldReceive('verifyCallbackSignature')->once()->with('payload', 'sig')->andReturn(true);

        $hybrid = new HybridPaymentGateway($bri, $mock);

        $this->assertTrue($hybrid->verifyCallbackSignature('payload', 'sig'));
    }
}
```

- [x] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/HybridPaymentGatewayTest.php`
Expected: FAIL — `Class "App\Services\Finance\Gateway\HybridPaymentGateway" not found`.

- [x] **Step 3: Write `HybridPaymentGateway`**

Create `app/Services/Finance/Gateway/HybridPaymentGateway.php`:

```php
<?php

namespace App\Services\Finance\Gateway;

use App\Contracts\PaymentGatewayInterface;
use App\DTO\PaymentStatusResult;
use App\DTO\QrisResult;
use App\DTO\VirtualAccountResult;
use App\Models\Pembayaran;

class HybridPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        protected BriSnapGateway $bri,
        protected MockPaymentGateway $mock,
    ) {
    }

    public function createVirtualAccount(Pembayaran $pembayaran, string $vaType): VirtualAccountResult
    {
        return $this->mock->createVirtualAccount($pembayaran, $vaType);
    }

    public function createQris(Pembayaran $pembayaran, string $qrisType): QrisResult
    {
        return $this->bri->createQris($pembayaran, $qrisType);
    }

    public function verifyCallbackSignature(string $payload, string $signature): bool
    {
        return $this->mock->verifyCallbackSignature($payload, $signature);
    }

    public function checkStatus(string $channelReference, string $type): PaymentStatusResult
    {
        return $type === 'qris'
            ? $this->bri->checkStatus($channelReference, $type)
            : $this->mock->checkStatus($channelReference, $type);
    }
}
```

- [x] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Keuangan/HybridPaymentGatewayTest.php`
Expected: PASS (5 tests)

- [x] **Step 5: Tambah opsi binding `'hybrid'` di `AppServiceProvider`**

`app/Providers/AppServiceProvider.php` — tambah `use App\Services\Finance\Gateway\HybridPaymentGateway;` di import, lalu ubah blok binding jadi:

```php
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            $gatewayConfig = config('services.bri.gateway', 'mock');

            if ($gatewayConfig === 'snap') {
                return $app->make(BriSnapGateway::class);
            }

            if ($gatewayConfig === 'hybrid') {
                return $app->make(HybridPaymentGateway::class);
            }

            return $app->make(MockPaymentGateway::class);
        });
```

- [x] **Step 6: Tambah assertion `'hybrid'` di test binding existing**

`tests/Feature/Keuangan/GatewayImplementationTest.php` — di `test_gateway_binding_in_service_provider_based_on_config()`, tambahkan setelah blok assertion `'snap'` (sebelum penutup method):

```php
        // Setup config to use hybrid
        Config::set('services.bri.gateway', 'hybrid');

        $this->app->forgetInstance(PaymentGatewayInterface::class);
        $resolvedGatewayHybrid = app()->make(PaymentGatewayInterface::class);
        $this->assertInstanceOf(\App\Services\Finance\Gateway\HybridPaymentGateway::class, $resolvedGatewayHybrid);
```

- [x] **Step 7: Run semua test terkait**

Run: `php artisan test tests/Feature/Keuangan/HybridPaymentGatewayTest.php tests/Feature/Keuangan/GatewayImplementationTest.php`
Expected: PASS, semua hijau.

Run scope Keuangan penuh sekali lagi untuk memastikan binding baru tidak merusak apa pun yang inject `PaymentGatewayInterface`:
Run: `php artisan test tests/Feature/Keuangan/`
Expected: PASS.

- [x] **Step 8: Commit**

```bash
git add app/Services/Finance/Gateway/HybridPaymentGateway.php \
        app/Providers/AppServiceProvider.php \
        tests/Feature/Keuangan/HybridPaymentGatewayTest.php \
        tests/Feature/Keuangan/GatewayImplementationTest.php
git commit -m "feat(bri): add HybridPaymentGateway routing QRIS to BRI and VA to Mock"
```

---

### Task 7: Artisan command `bri:test-qris` — verifikasi manual berulang ke sandbox asli

**Files:**
- Create: `app/Console/Commands/BriTestQris.php`
- Test: tidak ada test otomatis untuk command ini (by design — tujuannya justru menghubungi sandbox BRI asli, yang sengaja dikecualikan dari suite otomatis per Global Constraints). Verifikasi dilakukan manual oleh user menjalankan command-nya sendiri terhadap sandbox sungguhan.

**Interfaces:**
- Consumes: `BriSnapGateway` dan `BriSnapClient::fromConfig()` dari Task 2 & 5. TIDAK lewat `PaymentGatewayInterface` binding (supaya tetap bisa dijalankan walau `services.bri.gateway` masih `'mock'` di `.env`).

**Baca dulu sebelum menulis kode**: rujuk kembali `bri-api.md` section "B. Generate QR" dan "C. Inquiry Payment" (sama seperti Task 5, baris ±932–1117) — command ini sekadar wrapper CLI di atas `BriSnapGateway`, tidak ada logic protokol baru, tapi kalau responsnya aneh saat dites ke sandbox asli nanti, itu dokumen yang harus dicek lagi duluan sebelum menyalahkan kode.

- [x] **Step 1: Write the command**

Create `app/Console/Commands/BriTestQris.php`:

```php
<?php

namespace App\Console\Commands;

use App\Exceptions\BriApiException;
use App\Models\Pembayaran;
use App\Services\Finance\Gateway\BriSnap\BriSnapClient;
use App\Services\Finance\Gateway\BriSnapGateway;
use Illuminate\Console\Command;

class BriTestQris extends Command
{
    protected $signature = 'bri:test-qris {amount=10000}';

    protected $description = 'Generate QRIS via sandbox BRI SNAP asli dan langsung cek statusnya, tanpa menyimpan apa pun ke database';

    public function handle(): int
    {
        $gateway = new BriSnapGateway(BriSnapClient::fromConfig());

        $pembayaran = new Pembayaran([
            'channel_reference' => 'TEST-' . now()->timestamp,
            'amount' => (float) $this->argument('amount'),
        ]);

        $this->info('Generating QR...');

        try {
            $qrisResult = $gateway->createQris($pembayaran, 'DIRECT');
        } catch (BriApiException $e) {
            $this->error("Generate QR gagal — responseCode: {$e->responseCode}, responseMessage: {$e->responseMessage}");

            return self::FAILURE;
        }

        $referenceNo = $qrisResult->payload['referenceNo'] ?? null;

        $this->info("qrContent: {$qrisResult->qrCodeData}");
        $this->info("referenceNo: {$referenceNo}");

        if ($referenceNo === null) {
            $this->warn('referenceNo kosong di response BRI — tidak bisa lanjut Inquiry Payment.');

            return self::FAILURE;
        }

        $this->info('Checking status via Inquiry Payment...');

        try {
            $statusResult = $gateway->checkStatus($referenceNo, 'qris');
        } catch (BriApiException $e) {
            $this->error("Inquiry Payment gagal — responseCode: {$e->responseCode}, responseMessage: {$e->responseMessage}");

            return self::FAILURE;
        }

        $this->info("status: {$statusResult->status}");
        $this->info('payload lengkap: ' . json_encode($statusResult->payload, JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
```

- [x] **Step 2: Verifikasi command terdaftar**

Run: `php artisan list bri`
Expected: muncul `bri:test-qris` dengan deskripsinya.

- [x] **Step 3: Jalankan sekali secara manual ke sandbox asli (bukan bagian test suite otomatis)**

Prasyarat sebelum command ini bisa sukses penuh: `BRI_SNAP_PARTNER_ID`, `BRI_SNAP_CHANNEL_ID`, `BRI_SNAP_MERCHANT_ID`, `BRI_SNAP_TERMINAL_ID` di `.env` harus sudah terisi nilai asli dari portal BRI / konfirmasi BRI (lihat Task 2 Step 3 — masih kosong sampai user mengisinya).

Run: `php artisan bri:test-qris 15000`

Expected (kalau semua kredensial & konfigurasi benar): output menampilkan `qrContent`, `referenceNo`, lalu `status: PENDING` (karena belum ada yang scan QR-nya) — bukan error. Kalau muncul error `responseCode`/`responseMessage`, itu petunjuk konkret apa yang salah (field kosong, X-PARTNER-ID salah, dll) — cocokkan dengan tabel "List of Error/Response Code" di `bri-api.md` section terkait untuk diagnosis.

- [x] **Step 4: Commit**

```bash
git add app/Console/Commands/BriTestQris.php
git commit -m "feat(bri): add bri:test-qris command for manual sandbox verification"
```

---

## Ringkasan Setelah Semua Task Selesai

- `MockPaymentGateway` tetap jadi default (`BRI_PAYMENT_GATEWAY=mock` di `.env`) — tidak ada perilaku aplikasi yang berubah untuk user sampai keputusan manual mengubahnya ke `hybrid`.
- QRIS asli sudah lengkap dan teruji (unit + feature test dengan `Http::fake()`, plus alat verifikasi manual `bri:test-qris` ke sandbox sungguhan).
- VA tetap 100% di Mock — `BriSnapGateway::createVirtualAccount()`/`checkStatus($ref, 'va')`/`verifyCallbackSignature()` tetap throw, menunggu sub-project terpisah setelah BRI menjawab pertanyaan autentikasi callback inbound.
- Langkah manual yang TERSISA untuk user (di luar scope plan ini): isi `BRI_SNAP_PARTNER_ID`/`CHANNEL_ID`/`MERCHANT_ID`/`TERMINAL_ID` di `.env`, jalankan `bri:test-qris` untuk verifikasi sandbox asli, baru putuskan sendiri kapan mengubah `BRI_PAYMENT_GATEWAY` ke `hybrid`.
