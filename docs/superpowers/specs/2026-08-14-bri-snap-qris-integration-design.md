# Integrasi BRI SNAP API — QRIS (Fondasi + QRIS Real) Design

## Konteks

Modul Keuangan Sekolah Dinamis (Sub-project 1–6d) sudah selesai dan berjalan di atas `MockPaymentGateway`. `BriSnapGateway` (`app/Services/Finance/Gateway/BriSnapGateway.php`) adalah stub kosong — semua method-nya `throw new \RuntimeException('BriSnapGateway not implemented: awaiting credentials')`.

User sudah:
- Mendaftar sandbox BRI SNAP dan mendapatkan `client_id` + `client_secret` (tersimpan di `.env` sebagai `BRI_SNAP_CLIENT_ID`/`BRI_SNAP_CLIENT_SECRET`).
- Generate keypair RSA 2048-bit (`storage/app/bri/bri_private.pem` + `bri_public.pem`, gitignored via `/storage/app/bri/`), submit public key ke portal "Manage Snap Key" BRI.
- Memverifikasi manual lewat Postman bahwa **Get Token** (`POST /snap/v1.0/access-token/b2b`) berhasil — bukti keypair & credential valid.
- Menyediakan dokumentasi lengkap BRI SNAP di `bri-api.md` (hasil scrape developer portal BRI, bukan PDF resmi).

Dari analisis `bri-api.md`, arsitektur BRI SNAP untuk **Virtual Account (VA)** berbeda fundamental dari asumsi `PaymentGatewayInterface` saat ini: VA number di-generate LOKAL oleh partner (bukan diminta ke BRI), dan BRI yang memanggil 3 endpoint INBOUND di sisi partner (Inquiry, Payment, Notify) — ini butuh partner jadi token issuer, yang masih menunggu konfirmasi BRI soal skema autentikasinya. **QRIS sebaliknya murni outbound** (partner memanggil BRI untuk Generate QR dan Inquiry Payment) — cocok dengan bentuk `PaymentGatewayInterface` yang ada sekarang, dan tidak terhalang pertanyaan yang masih pending ke BRI.

**Scope dokumen ini: fondasi client BRI SNAP + integrasi QRIS nyata. VA sengaja TIDAK masuk scope** — `BriSnapGateway::createVirtualAccount()` tetap throw, ditangani sub-project terpisah setelah BRI menjawab pertanyaan autentikasi callback.

## Tujuan

1. Partner (aplikasi Pintera) bisa generate QRIS dinamis nyata via BRI sandbox dan mengecek status pembayarannya, menggantikan `MOCK-QR-*` yang dipakai `MockPaymentGateway` — tanpa mengganggu alur VA yang masih di-mock.
2. Ada mekanisme reusable (bukan cuma manual Postman) untuk memverifikasi koneksi ke sandbox BRI kapan pun dibutuhkan.
3. `PaymentGatewayInterface::checkStatus()` bisa membedakan pengecekan status VA vs QRIS, karena kedua jenis pembayaran ini akan segera dilayani oleh gateway backend yang berbeda (BRI asli untuk QRIS, Mock untuk VA) sekaligus dalam siklus reconciliation yang sama.

## Arsitektur

### Komponen baru

**`BriSnapClient`** — `app/Services/Finance/Gateway/BriSnap/BriSnapClient.php`

HTTP client tipis, satu-satunya kelas yang tahu detail protokol BRI SNAP (signature, timestamp, token caching). Tidak tahu apa pun soal `Pembayaran`/domain Keuangan — tugasnya murni transport + auth BRI.

Tanggung jawab:
- `getAccessToken(): string` — ambil token via `POST /snap/v1.0/access-token/b2b`, cache dengan `Cache::remember('bri_snap_access_token', 850, ...)` (850 detik, di bawah `expiresIn` 900 detik BRI, supaya tidak pernah terpakai token yang sudah kedaluwarsa saat request berikutnya jalan).
- `post(string $path, array $body): array` — bangun header (`Authorization: Bearer {token}`, `X-TIMESTAMP`, `X-SIGNATURE` simetris, `X-PARTNER-ID`, `CHANNEL-ID`, `X-EXTERNAL-ID`, `Content-Type: application/json`), kirim POST ke `{base_url}{path}`, decode JSON response. Body yang dikirim harus persis sama (byte-for-byte) dengan body yang di-hash untuk signature — gunakan satu variabel string JSON yang sama untuk keduanya, jangan re-encode.
- Signature asimetris (dipakai hanya untuk Get Token): `base64_encode(openssl_sign($clientId . '|' . $timestamp, ..., $privateKey, OPENSSL_ALGO_SHA256))`.
- Signature simetris (dipakai untuk semua call lain): `base64_encode(hash_hmac('sha512', $stringToSign, $clientSecret, true))` dengan `$stringToSign = "{$method}:{$path}:{$accessToken}:{$bodyHashHex}:{$timestamp}"` dan `$bodyHashHex = strtolower(hash('sha256', $bodyJson))`. **Catatan ambiguitas dokumen BRI**: contoh formula stringToSign menuliskan token TANPA prefix `Bearer `. Implementasi mengikuti contoh formula (tanpa `Bearer `) sebagai default; jika verifikasi manual (lihat "Testing" di bawah) mengembalikan `401 Unauthorized Signature`, ubah jadi dengan prefix `Bearer ` dan re-test — dua-duanya harus dicoba saat verifikasi pertama kali karena dokumentasi sendiri tidak konsisten (bagian "Token" contohnya `Bearer R04XSUb...`, bagian contoh payload lengkap tanpa `Bearer`).
- `X-EXTERNAL-ID` di-generate baru setiap call (`(string) now()->valueOf()` atau setara), bukan konstanta — ini bukan idempotency key yang perlu konsisten antar-retry pada scope QRIS ini.
- Timestamp: `(new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d\TH:i:s.v') . $now->format('P')` — hasil `2026-08-14T10:30:00.123+07:00`.
- Error non-2xx atau `responseCode` yang menandakan gagal → lempar `BriApiException` (baru, `app/Exceptions/BriApiException.php`, extends `\RuntimeException`) berisi `responseCode` + `responseMessage` dari BRI di message-nya.

**`BriSnapGateway`** (file existing, diisi)

- `createQris(Pembayaran $pembayaran, string $qrisType): QrisResult` — panggil `BriSnapClient::post('/snap/v1.1/qr/qr-mpm-generate', $body)` dengan body:
  ```php
  [
      'partnerReferenceNo' => (string) $pembayaran->channel_reference,
      'amount' => ['value' => number_format($pembayaran->amount, 2, '.', ''), 'currency' => 'IDR'],
      'merchantId' => config('services.bri.merchant_id'),
      'terminalId' => config('services.bri.terminal_id'),
  ]
  ```
  Response di-map ke `new QrisResult($response['qrContent'], (float) $pembayaran->amount, now()->addMinutes(15), $response)` — `$response` mentah (termasuk `referenceNo`) disimpan utuh sebagai `$payload`, PaymentService yang nanti mengekstrak `referenceNo` darinya (lihat Bagian Migrasi Data). Tidak ada perubahan pada `QrisResult` DTO.
- `checkStatus(string $channelReference, string $type): PaymentStatusResult` — jika `$type === 'qris'`: panggil `BriSnapClient::post('/snap/v1.1/qr/qr-mpm-query', ['originalReferenceNo' => $channelReference, 'serviceCode' => '47', 'additionalInfo' => ['terminalId' => config('services.bri.terminal_id')]])`, map `latestTransactionStatus` (`'00'` → `'PAID'`, selain itu → `'PENDING'`) ke `PaymentStatusResult`. Jika `$type === 'va'`: tetap `throw new \RuntimeException(...)` seperti sekarang.
- `createVirtualAccount()`, `verifyCallbackSignature()`: **tidak berubah**, tetap throw.

**`HybridPaymentGateway`** (baru) — `app/Services/Finance/Gateway/HybridPaymentGateway.php`

```php
class HybridPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        protected BriSnapGateway $bri,
        protected MockPaymentGateway $mock,
    ) {}

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

### Perubahan interface

`PaymentGatewayInterface::checkStatus()` — tambah parameter kedua:

```php
public function checkStatus(string $channelReference, string $type): PaymentStatusResult;
```

Dampak ke seluruh implementer & caller (semua di-update sebagai bagian dari task yang sama, tidak boleh terlewat):
- `MockPaymentGateway::checkStatus()` — tambah parameter `string $type`, isi method tidak berubah (parameter diterima tapi tidak dipakai untuk logic, karena Mock selalu return `'PAID'` untuk keduanya).
- `BriSnapGateway::checkStatus()` — seperti dijelaskan di atas.
- `HybridPaymentGateway::checkStatus()` — seperti dijelaskan di atas.
- `app/Console/Commands/ReconcilePayments.php` — 2 call site: `$this->gateway->checkStatus($va->va_number, 'va')` (baris ~59) dan `$this->gateway->checkStatus($qris->reference_no, 'qris')` (baris ~104, JUGA ganti argumen dari `$qris->qr_code` ke `$qris->reference_no` — lihat Migrasi Data).
- Semua test existing yang memanggil `checkStatus()` (grep `->checkStatus(` di `tests/`) harus diupdate menambahkan argumen `$type` sesuai konteksnya.

`BriWebhookController` **tidak disentuh** — bentuk payload-nya (`BrivaNo`/`CustCode`/`BRI-Signature`) adalah placeholder VA lama yang tidak sesuai spek SNAP asli, dan dari `bri-api.md` tidak ditemukan endpoint notify inbound terpisah untuk QRIS — reconciliation QRIS asli sepenuhnya lewat polling cron (`ReconcilePayments`) yang memanggil Inquiry Payment.

### AppServiceProvider

`config('services.bri.gateway')` dapat opsi baru `'hybrid'`:

```php
$this->app->bind(PaymentGatewayInterface::class, function ($app) {
    $gatewayConfig = config('services.bri.gateway', 'mock');

    if ($gatewayConfig === 'bri') {
        return $app->make(BriSnapGateway::class);
    }

    if ($gatewayConfig === 'hybrid') {
        return $app->make(HybridPaymentGateway::class);
    }

    return $app->make(MockPaymentGateway::class);
});
```

## Migrasi Data

Migration baru: tambah kolom `reference_no` (string, nullable) ke `bri_qris_payments` — BRI Inquiry Payment butuh `referenceNo` (nomor referensi dari BRI), bukan `qr_code` (isi string QR). Nullable karena baris lama (dari `MockPaymentGateway`, yang tidak punya konsep `referenceNo`) harus tetap valid.

`PaymentService::createQrisPayment()` dan `createQrisPaymentWithTopup()` — saat membuat `BriQrisPayment`, tambah:
```php
'reference_no' => $qrisResult->payload['referenceNo'] ?? null,
```
`MockPaymentGateway::createQris()` tidak mengisi `referenceNo` di payload-nya (karena tidak relevan untuk mock) — `??` fallback ke `null` memastikan Mock tetap jalan tanpa error. `BriQrisPayment` model perlu ditambah `reference_no` ke daftar kolom yang di-guard sudah otomatis lewat `protected $guarded = ['id']` (tidak perlu perubahan model, hanya migration).

## Config

`config/services.php`, entry `bri` (menggantikan/melengkapi baris `bri` yang mungkin sudah ada dari sub-project sebelumnya — cek dulu sebelum menambah agar tidak duplikat key `gateway`):

```php
'bri' => [
    'gateway' => env('BRI_PAYMENT_GATEWAY', 'mock'), // mock | bri | hybrid
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

`.env` dan `.env.example` — tambah 4 var baru (`BRI_SNAP_PARTNER_ID`, `BRI_SNAP_CHANNEL_ID`, `BRI_SNAP_MERCHANT_ID`, `BRI_SNAP_TERMINAL_ID`), kosong sampai user mengonfirmasi nilainya dari portal BRI atau dari BRI langsung. Tambah `BRI_PAYMENT_GATEWAY=mock` di `.env` (default eksplisit, tidak diubah ke `hybrid` sampai verifikasi manual command (lihat Testing) lolos DAN keempat nilai di atas terisi).

## Testing

### Artisan command manual — `bri:test-qris`

`app/Console/Commands/BriTestQris.php`, signature `bri:test-qris {amount=10000}`:
1. Instansiasi `BriSnapClient` & `BriSnapGateway` langsung (bukan lewat `PaymentGatewayInterface` binding — supaya tetap bisa dijalankan walau `BRI_PAYMENT_GATEWAY` masih `mock`).
2. Bangun `Pembayaran` in-memory dummy (tidak disimpan ke DB — `new Pembayaran(['channel_reference' => 'TEST-' . now()->timestamp, 'amount' => $amount])`) untuk dilempar ke `createQris()`.
3. Generate QR, cetak `qrContent` dan `referenceNo` ke terminal.
4. Langsung panggil `checkStatus($referenceNo, 'qris')`, cetak `latestTransactionStatus`/status hasil mapping.
5. Tangkap `BriApiException` dan tampilkan `responseCode`+`responseMessage` BRI apa adanya (bukan generic "gagal") supaya gampang didiagnosis.

Ini pengganti permanen proses manual Postman/tinker yang sudah dilakukan untuk Get Token — command ini bisa dipakai berulang kapan saja tanpa menyusun ulang signature secara manual.

### Test otomatis (masuk suite `php artisan test`, TIDAK menghubungi sandbox asli)

- `tests/Unit/Services/Finance/Gateway/BriSnap/BriSnapClientTest.php`:
  - Known-answer test: `hash('sha256', '{"hello":"world"}')` harus menghasilkan `93a23971a914e5eacbf0a8d25154cda309c3c1c72fbb9914d47c60f3cb681588` (nilai ini diambil langsung dari contoh di `bri-api.md`, bukan dihitung ulang oleh test itu sendiri — memvalidasi bahwa implementasi hash body-nya benar terhadap spek BRI, bukan cuma konsisten dengan dirinya sendiri).
  - Test format `stringToSign` untuk signature asimetris (`clientId . '|' . timestamp`) dan simetris (`method:path:token:bodyHash:timestamp`) sesuai formula, pakai nilai dummy yang dikontrol test.
- `tests/Feature/Finance/Gateway/BriSnapGatewayTest.php`:
  - `Http::fake()` dengan response sample dari `bri-api.md` (Generate QR normal response, Inquiry Payment normal response) — verifikasi `createQris()` dan `checkStatus(..., 'qris')` memetakan field dengan benar (`qrContent`→`qrCodeData`, `referenceNo` tersimpan di `payload`, `latestTransactionStatus` '00'→'PAID').
  - Test `BriApiException` dilempar saat `Http::fake()` mengembalikan response error (mis. `responseCode: 4007301`).
- `tests/Feature/Finance/Gateway/HybridPaymentGatewayTest.php`: verifikasi routing — `createQris()`/`checkStatus(..., 'qris')` memanggil `BriSnapGateway` (bisa di-mock di test), `createVirtualAccount()`/`checkStatus(..., 'va')`/`verifyCallbackSignature()` memanggil `MockPaymentGateway`.
- Update test existing yang memanggil `checkStatus()` (perlu di-grep saat implementasi) untuk menambahkan argumen `$type`.
- `ReconcilePayments` test existing (kalau ada) — verifikasi 2 call site memanggil `checkStatus()` dengan `$type` yang benar per loop.

## Batasan & Yang Sengaja Tidak Dikerjakan

- **VA (Virtual Account) real** — `createVirtualAccount()`, `checkStatus($ref, 'va')` versi BRI, dan `verifyCallbackSignature()` versi BRI TIDAK dikerjakan di sini. Diblokir oleh pertanyaan yang masih menunggu jawaban BRI: siapa jadi token issuer untuk 3 endpoint inbound VA (Inquiry, Payment, Notify), dan `partner_id`/`X-PARTNER-ID` yang jadi basis `partnerServiceId` untuk generate VA number lokal juga belum dikonfirmasi.
- **`BriWebhookController`** tidak diubah sama sekali — masih dalam bentuk placeholder VA lama, akan direvisi bersamaan dengan sub-project VA nanti.
- **`BRI_PAYMENT_GATEWAY` tidak di-flip ke `hybrid` di `.env` sebagai bagian dari plan ini** — itu langkah manual terpisah yang dilakukan user setelah `bri:test-qris` lolos dan nilai `partner_id`/`channel_id`/`merchant_id`/`terminal_id` terisi benar.
- **Base URL production** tidak ada di dokumentasi BRI yang sudah dibaca — hanya sandbox. Tidak relevan untuk scope ini (semuanya sandbox), tapi dicatat sebagai open item untuk sub-project produksi nanti.
