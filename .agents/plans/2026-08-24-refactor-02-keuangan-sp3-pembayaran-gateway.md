# Migrasi Domain Keuangan Sub-project 3: Pembayaran & Gateway Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memindahkan seluruh alur Pembayaran (2 jalur paralel: checkout modern + verifikasi manual legacy), Gateway family, Contracts/DTO, dan webhook BRI SNAP ke `app/Domains/Keuangan/*`, tanpa mengubah perilaku aplikasi.

**Architecture:** Model pindah fisik (hanya `$fillable`/`$guarded`/`casts()`/relationship). Controller mutasi direfactor jadi Action. Webhook diekstrak penuh ke Action tapi controller merakit response JSON byte-identical. `AutoAllocationEngine`/`SkipAlertResolver`/`PaymentAllocationService` (utuh) TIDAK dipindah — tetap di `app/Services/Finance/`, diinject dari domain baru seperti biasa.

**Tech Stack:** Laravel 12, Pest.

## Global Constraints

- **Zero-behavior-change** — pesan error, kode status HTTP, urutan validasi, format respons JSON/redirect HARUS identik kata-per-kata. Kalau ditemukan celah/inkonsistensi di kode lama, JANGAN diperbaiki diam-diam — laporkan ke user.
- Route NAME dan PATH tidak berubah sama sekali. URL webhook (`/snap/v1.0/...`) khususnya TIDAK BOLEH berubah — literal string di `Route::post()`, tidak terikat namespace controller.
- **Response JSON webhook (`Api\BriVaInboundController`) WAJIB byte-identical** — field, tipe (string desimal 2 digit via `number_format(...,2,'.','')`, BUKAN angka), dan status HTTP untuk SETIAP dari 11 kombinasi kondisi di §4 spec HARUS sama persis dengan kode asli.
- **7 guard otorisasi/tenant-scope berikut WAJIB dipertahankan PERSIS** (pelajaran dari celah HIGH review SP1 dan deviasi namespace tak terungkap review SP2 — modul ini uang sungguhan, risikonya lebih tinggi):
  1. `Admin\PembayaranController::verifikasi()` — dua jalur resolusi lembaga (via `tagihan` ATAU via `cicilan`) dengan null-coalesce, urutan tidak boleh diubah.
  2. `Admin\ManualPaymentController::approve()`/`reject()` — `siswaLembagaId()` dengan bypass `TenantScope` eksplisit.
  3. `Admin\ManualPaymentController::approve()` — **GUARD DATA-CONSISTENCY KRITIS**: `hasTagihanTargets` vs `isTopup` mutually exclusive, `Log::critical()` + `abort(500)` kalau drift di kedua arah. Ini guard PALING KRITIS di seluruh SP3.
  4. `Admin\VirtualAccountController::riwayat()` — pola `siswaLembagaId()` identik dengan ManualPaymentController, JANGAN dikonsolidasi jadi 1 helper tanpa didiskusikan ke user dulu.
  5. `AuthorizesPembayaran::authorizePembayaran()` — cek kepemilikan orangTua-siswa via `TenantScope` bypass, dipakai di SETIAP titik akses `Pembayaran` oleh portal siswa/ortu.
  6. Webhook `payment()` — urutan idempotency-check → validasi amount → VA lookup → insert log dengan disambiguasi genuine-duplicate vs real-failure. Correctness-critical untuk cegah double-charge/double-credit.
  7. `PembayaranService::catatPembayaran()` — mutual-exclusivity tagihan/cicilan, row-lock, cek pembayaran-aktif SEBELUM insert, `pastikanUrutanBoleh()` (cicilan berurutan).
- **`newFactory()`**: `Pembayaran`, `PembayaranTagihan` (TIDAK pakai HasFactory saat ini — cek dulu di Task 1/2, JANGAN tambahkan kalau tidak ada), `BriVirtualAccount`, `BriQrisPayment`, `BriInboundPaymentLog`, `ManualPaymentRequest` (SEMUA pakai `HasFactory` — WAJIB `newFactory()`).
- **Verifikasi grep WAJIB menyisir `app database tests`** (bukan cuma `app/Models`) — cari string `App\Models\{ClassName}` (menangkap `use` DAN FQCN inline).
- Referensi lintas-namespace dari file yang TETAP di lokasi lama pakai **FQCN inline**, BUKAN `use` statement tambahan.
- `AutoAllocationEngine`, `SkipAlertResolver`, `PaymentAllocationService` (UTUH, termasuk method `allocate()`), `NotificationDispatcher`, `Wallet`, `Cicilan` — TIDAK dipindah, tetap diinject dari `app/Services/Finance/`/`app/Models/` seperti biasa.
- `Portal\TagihanController`, `Admin\TagihanSusulanController`, `TagihanGenerator` (PPDB) — TIDAK disentuh selain cross-scope touch eksplisit.
- Baseline kode: commit `ffe5400` di branch `refactor-v1`. Kalau isi file berbeda signifikan dari yang dikutip plan, STOP, laporkan ke user.
- Tiap task: test SCOPED SEBELUM commit. Full suite HANYA task terakhir, izin eksplisit user dulu.

---

## Task 1: Pindahkan Model `Pembayaran` (+ Perbaiki Gotcha `Cicilan.php`/`Wallet.php`)

**Files:**
- Move: `app/Models/Pembayaran.php` → `app/Domains/Keuangan/Models/Pembayaran.php`
- Modify: `database/factories/PembayaranFactory.php` + seluruh file hasil grep `use App\Models\Pembayaran;` yang BUKAN bagian task lain di plan ini (grep ulang WAJIB, daftar berikut per 24 Agustus 2026, 56 file)
- Modify (gotcha implisit FQCN): `app/Models/Cicilan.php`, `app/Models/Wallet.php` (4 type-hint parameter)

**Interfaces:**
- Produces: `App\Domains\Keuangan\Models\Pembayaran` — dipakai seluruh task berikutnya.

- [ ] **Step 1: Pindahkan file fisik**

```bash
git mv app/Models/Pembayaran.php app/Domains/Keuangan/Models/Pembayaran.php
```

- [ ] **Step 2: Ubah isi file — namespace, `newFactory()`, FQCN untuk `Cicilan`**

Timpa seluruh isi `app/Domains/Keuangan/Models/Pembayaran.php` dengan:

```php
<?php
// app/Domains/Keuangan/Models/Pembayaran.php

namespace App\Domains\Keuangan\Models;

use App\Models\Cicilan;
use App\Models\Siswa;
use App\Models\User;
use Database\Factories\PembayaranFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Pembayaran extends Model
{
    use HasFactory, LogsActivity;

    protected static function newFactory(): PembayaranFactory
    {
        return PembayaranFactory::new();
    }

    protected $table = 'pembayaran';

    protected $attributes = [
        'is_auto_allocation' => false,
        'identifier_method' => 'manual',
    ];

    protected $fillable = [
        'tagihan_id', 'cicilan_id', 'sumber', 'metode', 'amount', 'file_path',
        'status', 'catatan_verifikasi', 'diverifikasi_oleh_user_id', 'diverifikasi_pada',
        'wallet_id', 'siswa_id', 'is_auto_allocation', 'channel_reference', 'identifier_method',
        'topup_status',
    ];

    protected function casts(): array
    {
        return [
            'diverifikasi_pada' => 'datetime',
            'is_auto_allocation' => 'boolean',
        ];
    }

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(Tagihan::class);
    }

    public function cicilan(): BelongsTo
    {
        return $this->belongsTo(Cicilan::class);
    }

    public function diverifikasiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh_user_id');
    }

    public function pembayaranTagihan(): HasMany
    {
        return $this->hasMany(PembayaranTagihan::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function briVirtualAccount(): HasOne
    {
        return $this->hasOne(BriVirtualAccount::class);
    }

    public function briQrisPayment(): HasOne
    {
        return $this->hasOne(BriQrisPayment::class);
    }

    public function manualRequest(): HasOne
    {
        return $this->hasOne(ManualPaymentRequest::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'catatan_verifikasi', 'diverifikasi_oleh_user_id'])
            ->logOnlyDirty()
            ->useLogName('pembayaran');
    }
}
```

Catatan: `tagihan()` menunjuk ke `Tagihan` (SP2, sudah di `Domains\Keuangan\Models`, TIDAK perlu `use` tambahan). `briVirtualAccount()`/`briQrisPayment()`/`manualRequest()`/`pembayaranTagihan()` menunjuk ke model yang JUGA pindah di Task 2-3 sub-project ini — TIDAK perlu `use`, akan sama-namespace begitu task-task itu selesai. `cicilan()` tetap `use App\Models\Cicilan;` (SP4, TIDAK pindah, referensi biasa dari file yang PINDAH ke file yang TIDAK pindah — bukan gotcha, sama seperti pola `Tagihan.php`'s `pembayaran()`/`pembayaranTagihan()` di SP2).

- [ ] **Step 3: Cek apakah `PembayaranFactory` sudah ada, update `use`-nya**

```bash
cat database/factories/PembayaranFactory.php
```

Ganti baris `use App\Models\Pembayaran;` menjadi `use App\Domains\Keuangan\Models\Pembayaran;`. Kalau ada `use App\Domains\Keuangan\Models\Tagihan;`/referensi model lain yang sudah di domain baru, biarkan apa adanya (sudah benar).

- [ ] **Step 4: Grep ulang untuk daftar file consumer PASTI**

```bash
grep -rln "use App\\\\Models\\\\Pembayaran;" --include="*.php" app database tests
```

Bandingkan dengan daftar berikut (grep 24 Agustus 2026, 56 file — WAJIB grep ulang, ini referensi awal bukan final). File yang ditandai "JANGAN diedit di sini" akan diupdate di task lain di plan ini:

```
tests/Unit/PembayaranDataLayerTest.php
app/Domains/Keuangan/Models/Tagihan.php
tests/Unit/PembayaranServiceTest.php
tests/Unit/PembayaranSeederTest.php
tests/Unit/DashboardStatsServiceTest.php
tests/Feature/Portal/TagihanPembayaranTest.php
tests/Feature/Keuangan/RiwayatControllerIndexTest.php
tests/Feature/Keuangan/RiwayatAuthorizationTest.php
tests/Feature/Keuangan/ReconciliationCommandTest.php
tests/Feature/Keuangan/ReconcilePaymentsBundledTopupTest.php
tests/Feature/Keuangan/PembayaranWalletColumnsTest.php
tests/Feature/Keuangan/PembayaranTagihanTest.php
tests/Feature/Keuangan/PembayaranBerhasilNotificationTest.php
tests/Feature/Keuangan/PaymentServiceWalletPaymentTest.php
tests/Feature/Keuangan/PaymentServiceTest.php
tests/Feature/Keuangan/PaymentServiceBundledTopupTest.php
tests/Feature/Keuangan/PaymentAllocationServiceTopupRemainderTest.php
tests/Feature/Keuangan/PaymentAllocationServiceTest.php
tests/Feature/Keuangan/KwitansiControllerTest.php
tests/Feature/Keuangan/CheckoutControllerWalletTest.php
tests/Feature/Keuangan/CheckoutControllerVaQrisTest.php
tests/Feature/Keuangan/CheckoutControllerTransferTest.php
tests/Feature/Keuangan/CheckoutControllerBundledTopupTest.php
tests/Feature/Keuangan/CheckoutAuthorizationTest.php
tests/Feature/Keuangan/AutoAllocationEngineTest.php
tests/Feature/Admin/VerifikasiPembayaranTest.php
tests/Feature/Admin/ManualPaymentNotificationTest.php
tests/Feature/Admin/ManualPaymentIndexControllerTest.php
tests/Feature/Admin/ManualPaymentIndexAuthorizationTest.php
tests/Feature/Admin/ManualPaymentControllerTest.php
tests/Feature/Admin/CatatManualPembayaranTest.php
database/seeders/PembayaranSeeder.php
database/factories/PembayaranFactory.php          <- SUDAH diedit Step 3
app/Services/Finance/PaymentService.php           <- JANGAN diedit di sini, ditangani Task 7
app/Services/Finance/PaymentAllocationService.php <- TIDAK PINDAH, tapi use Pembayaran-nya TETAP (Pembayaran sekarang di domain baru) - JANGAN diedit, lihat catatan di bawah
app/Services/Finance/AutoAllocationEngine.php     <- TIDAK PINDAH, sama seperti di atas - JANGAN diedit
app/Services/PembayaranService.php                <- JANGAN diedit di sini, ditangani Task 8
app/Services/DashboardStatsService.php
app/Http/Controllers/Keuangan/CheckoutController.php  <- JANGAN diedit di sini, ditangani Task 14
app/Http/Controllers/Api/BriVaInboundController.php   <- JANGAN diedit di sini, ditangani Task 16
tests/Feature/Keuangan/BriSnapGatewayIntegrationTest.php
app/Http/Controllers/Keuangan/RiwayatController.php   <- JANGAN diedit di sini, ditangani Task 15
app/Console/Commands/ReconcilePayments.php            <- JANGAN diedit di sini, ditangani Task 17
tests/Feature/Keuangan/GatewayImplementationTest.php
app/Services/Finance/Gateway/BriSnapGateway.php       <- JANGAN diedit di sini, ditangani Task 6
tests/Unit/Models/BriInboundPaymentLogTest.php
tests/Feature/Keuangan/ReconcilePaymentsQrisTest.php
app/Console/Commands/BriTestQris.php
tests/Feature/Keuangan/HybridPaymentGatewayTest.php
app/Services/Finance/Gateway/HybridPaymentGateway.php <- JANGAN diedit di sini, ditangani Task 6
app/Services/Finance/Gateway/MockPaymentGateway.php   <- JANGAN diedit di sini, ditangani Task 6
app/Contracts/PaymentGatewayInterface.php             <- JANGAN diedit di sini, ditangani Task 5
app/Http/Controllers/Keuangan/Concerns/AuthorizesPembayaran.php <- JANGAN diedit di sini, ditangani Task 9
tests/Feature/Keuangan/PaymentServiceManualTopupTest.php
tests/Feature/Keuangan/PaymentChannelModelsTest.php
app/Http/Controllers/Admin/PembayaranController.php   <- JANGAN diedit di sini, ditangani Task 10
```

**PENTING soal `PaymentAllocationService.php`/`AutoAllocationEngine.php`**: kedua file ini TIDAK PINDAH namespace (tetap `app/Services/Finance/`), tapi mereka mengimpor `App\Models\Pembayaran` yang SEKARANG sudah pindah ke `App\Domains\Keuangan\Models\Pembayaran`. **`use`-nya TETAP WAJIB diupdate** meski filenya sendiri tidak pindah — ini persis pola "cross-scope touch" dari SP1/SP2 (mis. `TagihanController.php`'s `SkemaCicilan` import di SP1). Update kedua file ini di Step 5 di bawah, JANGAN dilewati hanya karena ditandai berbeda di daftar atas.

- [ ] **Step 5: Update `use` statement di file consumer (KECUALI yang ditandai "JANGAN diedit di sini" untuk task LAIN, TAPI TERMASUK `PaymentAllocationService.php`/`AutoAllocationEngine.php`/`DashboardStatsService.php`/2 file test Gateway/`BriInboundPaymentLogTest.php`/`BriTestQris.php`)**

Untuk setiap file yang boleh diedit di task ini, ganti `use App\Models\Pembayaran;` → `use App\Domains\Keuangan\Models\Pembayaran;`. **HANYA baris `use` yang berubah.**

- [ ] **Step 6: Perbaiki gotcha implisit di `app/Models/Cicilan.php`**

Baca file, cari baris `return $this->hasMany(Pembayaran::class);`, ganti jadi `return $this->hasMany(\App\Domains\Keuangan\Models\Pembayaran::class);`.

- [ ] **Step 7: Perbaiki gotcha implisit di `app/Models/Wallet.php` (4 type-hint parameter)**

Baca file, cari 4 method yang punya parameter `?Pembayaran $pembayaran = null` (method `topup()`, `debit()`, `debitWithinTransaction()`, `debitCore()` — nama persis bisa dicek dengan `grep -n "Pembayaran \$pembayaran" app/Models/Wallet.php`). Ganti SETIAP `?Pembayaran $pembayaran` menjadi `?\App\Domains\Keuangan\Models\Pembayaran $pembayaran`. `BriVirtualAccount::class` di method `briVirtualAccounts()` JANGAN diedit di task ini (ditangani Task 3 setelah model itu pindah).

- [ ] **Step 8: Verifikasi minimal**

```bash
php artisan tinker --execute="echo class_exists(\App\Domains\Keuangan\Models\Pembayaran::class) ? 'OK' : 'MISSING';"
```
Expected: `OK`.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah model Pembayaran ke Domains\Keuangan\Models, perbaiki gotcha Cicilan.php dan Wallet.php"
```

---

## Task 2: Pindahkan Model `PembayaranTagihan`

**Files:**
- Move: `app/Models/PembayaranTagihan.php` → `app/Domains/Keuangan/Models/PembayaranTagihan.php`
- Modify: seluruh file hasil grep `use App\Models\PembayaranTagihan;` (16 file, TERMASUK `PaymentAllocationService.php`/`AutoAllocationEngine.php` yang tidak pindah tapi tetap perlu update `use`)

**Interfaces:**
- Produces: `App\Domains\Keuangan\Models\PembayaranTagihan` — dipakai Task 7 (PaymentService).

- [ ] **Step 1: Pindahkan file fisik**

```bash
git mv app/Models/PembayaranTagihan.php app/Domains/Keuangan/Models/PembayaranTagihan.php
```

- [ ] **Step 2: Ubah isi file (TIDAK pakai `HasFactory` — cek dulu, JANGAN tambahkan kalau memang tidak ada)**

Timpa seluruh isi `app/Domains/Keuangan/Models/PembayaranTagihan.php` dengan:

```php
<?php

namespace App\Domains\Keuangan\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PembayaranTagihan extends Model
{
    protected $table = 'pembayaran_tagihan';

    protected $fillable = ['pembayaran_id', 'tagihan_id', 'amount_allocated'];

    protected function casts(): array
    {
        return [
            'amount_allocated' => 'decimal:2',
        ];
    }

    public function pembayaran(): BelongsTo
    {
        return $this->belongsTo(Pembayaran::class);
    }

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(Tagihan::class);
    }
}
```

- [ ] **Step 3: Grep ulang dan update consumer**

```bash
grep -rln "use App\\\\Models\\\\PembayaranTagihan;" --include="*.php" app database tests
```

Daftar per 24 Agustus 2026 (16 file, grep ulang WAJIB):
```
app/Domains/Keuangan/Models/Tagihan.php
tests/Feature/Keuangan/RiwayatControllerIndexTest.php
tests/Feature/Keuangan/RiwayatAuthorizationTest.php
tests/Feature/Keuangan/ReconciliationCommandTest.php
tests/Feature/Keuangan/ReconcilePaymentsBundledTopupTest.php
tests/Feature/Keuangan/PembayaranTagihanTest.php
tests/Feature/Keuangan/PembayaranBerhasilNotificationTest.php
tests/Feature/Keuangan/PaymentAllocationServiceTopupRemainderTest.php
tests/Feature/Keuangan/PaymentAllocationServiceTest.php
tests/Feature/Keuangan/KwitansiControllerTest.php
tests/Feature/Admin/ManualPaymentNotificationTest.php
tests/Feature/Admin/ManualPaymentIndexControllerTest.php
tests/Feature/Admin/ManualPaymentIndexAuthorizationTest.php
tests/Feature/Admin/ManualPaymentControllerTest.php
app/Services/Finance/PaymentService.php           <- JANGAN diedit di sini, ditangani Task 7
app/Services/Finance/AutoAllocationEngine.php     <- TIDAK PINDAH tapi use-nya WAJIB diupdate (sama seperti Task 1)
```

Update `use App\Models\PembayaranTagihan;` → `use App\Domains\Keuangan\Models\PembayaranTagihan;` di SEMUA file KECUALI `app/Services/Finance/PaymentService.php` (ditangani Task 7).

- [ ] **Step 4: Verifikasi**

```bash
grep -rln "use App\\\\Models\\\\PembayaranTagihan;" --include="*.php" app database tests
```
Expected: hanya `app/Services/Finance/PaymentService.php` yang tersisa (ditangani Task 7).

- [ ] **Step 5: Jalankan test scoped minimal**

```bash
php artisan tinker --execute="echo class_exists(\App\Domains\Keuangan\Models\PembayaranTagihan::class) ? 'OK' : 'MISSING';"
```

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah model PembayaranTagihan ke Domains\Keuangan\Models"
```

---

## Task 3: Pindahkan 4 Model BRI Kecil (`BriVirtualAccount`, `BriQrisPayment`, `BriInboundPaymentLog`, `ManualPaymentRequest`) + Perbaiki Gotcha `Wallet.php`

**Files:**
- Move: `app/Models/BriVirtualAccount.php`, `BriQrisPayment.php`, `BriInboundPaymentLog.php`, `ManualPaymentRequest.php` → `app/Domains/Keuangan/Models/`
- Modify: `database/factories/BriVirtualAccountFactory.php` (dan factory lain kalau ada untuk 3 model sisanya — cek dulu) + seluruh file hasil grep (21 file gabungan)
- Modify (gotcha implisit FQCN): `app/Models/Wallet.php` (relasi `briVirtualAccounts()`)
- Modify (cross-scope touch): `app/Exports/VirtualAccountExport.php`

**Interfaces:**
- Produces: `App\Domains\Keuangan\Models\{BriVirtualAccount,BriQrisPayment,BriInboundPaymentLog,ManualPaymentRequest}`.

- [ ] **Step 1: Pindahkan 4 file fisik**

```bash
git mv app/Models/BriVirtualAccount.php app/Domains/Keuangan/Models/BriVirtualAccount.php
git mv app/Models/BriQrisPayment.php app/Domains/Keuangan/Models/BriQrisPayment.php
git mv app/Models/BriInboundPaymentLog.php app/Domains/Keuangan/Models/BriInboundPaymentLog.php
git mv app/Models/ManualPaymentRequest.php app/Domains/Keuangan/Models/ManualPaymentRequest.php
```

- [ ] **Step 2: Ubah isi `BriVirtualAccount.php` — namespace, `newFactory()`**

Timpa seluruh isi `app/Domains/Keuangan/Models/BriVirtualAccount.php` dengan:

```php
<?php

namespace App\Domains\Keuangan\Models;

use App\Models\Wallet;
use Database\Factories\BriVirtualAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BriVirtualAccount extends Model
{
    use HasFactory;

    protected static function newFactory(): BriVirtualAccountFactory
    {
        return BriVirtualAccountFactory::new();
    }

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'expired_at' => 'datetime',
        'callback_payload' => 'array',
    ];

    public function pembayaran(): BelongsTo
    {
        return $this->belongsTo(Pembayaran::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
```

- [ ] **Step 3: Ubah isi `BriQrisPayment.php` — namespace, `newFactory()` (cek dulu ada factory-nya atau tidak)**

```bash
ls database/factories/ | grep -i BriQrisPayment
```

Kalau ADA file factory-nya, timpa seluruh isi `app/Domains/Keuangan/Models/BriQrisPayment.php` dengan (sertakan `newFactory()`):

```php
<?php

namespace App\Domains\Keuangan\Models;

use Database\Factories\BriQrisPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BriQrisPayment extends Model
{
    use HasFactory;

    protected static function newFactory(): BriQrisPaymentFactory
    {
        return BriQrisPaymentFactory::new();
    }

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'expired_at' => 'datetime',
        'callback_payload' => 'array',
    ];

    public function pembayaran(): BelongsTo
    {
        return $this->belongsTo(Pembayaran::class);
    }
}
```

Kalau TIDAK ADA file factory (model punya `HasFactory` tapi Laravel resolve otomatis lewat konvensi nama tanpa `newFactory()` eksplisit), timpa TANPA method `newFactory()`, cukup `use HasFactory;` saja — samakan dengan pola aslinya persis (baca dulu file asli `app/Models/BriQrisPayment.php` SEBELUM Step 1 dijalankan kalau ragu, jangan asumsi).

- [ ] **Step 4: Ubah isi `BriInboundPaymentLog.php` — namespace, `newFactory()` (cek dulu sama seperti Step 3)**

```bash
ls database/factories/ | grep -i BriInboundPaymentLog
```

Timpa seluruh isi `app/Domains/Keuangan/Models/BriInboundPaymentLog.php` dengan:

```php
<?php

namespace App\Domains\Keuangan\Models;

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

Tambahkan method `newFactory()` HANYA kalau Step 4's `ls` menemukan file factory-nya (ikuti pola Step 3).

- [ ] **Step 5: Ubah isi `ManualPaymentRequest.php` — namespace, `newFactory()` (cek dulu sama seperti Step 3)**

```bash
ls database/factories/ | grep -i ManualPaymentRequest
```

Timpa seluruh isi `app/Domains/Keuangan/Models/ManualPaymentRequest.php` dengan:

```php
<?php

namespace App\Domains\Keuangan\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualPaymentRequest extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'transfer_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function pembayaran(): BelongsTo
    {
        return $this->belongsTo(Pembayaran::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
```

Tambahkan `newFactory()` HANYA kalau ada file factory-nya (ikuti pola Step 3).

- [ ] **Step 6: Update factory `BriVirtualAccountFactory.php` (dan factory lain yang ditemukan di Step 3-5)**

Baca tiap factory yang ada, ganti `use App\Models\{Model};` → `use App\Domains\Keuangan\Models\{Model};`.

- [ ] **Step 7: Grep ulang untuk daftar consumer gabungan**

```bash
grep -rln "use App\\\\Models\\\\BriVirtualAccount;\|use App\\\\Models\\\\BriQrisPayment;\|use App\\\\Models\\\\BriInboundPaymentLog;\|use App\\\\Models\\\\ManualPaymentRequest;" --include="*.php" app database tests
```

Daftar per 24 Agustus 2026 (21 file gabungan, grep ulang WAJIB):
```
tests/Feature/Keuangan/ReconciliationCommandTest.php
tests/Feature/Keuangan/BriVaInboundPaymentTest.php
tests/Feature/Admin/ManualPaymentNotificationTest.php
tests/Feature/Admin/ManualPaymentIndexControllerTest.php
tests/Feature/Admin/ManualPaymentIndexAuthorizationTest.php
tests/Feature/Admin/ManualPaymentControllerTest.php
app/Services/Finance/PaymentService.php            <- JANGAN diedit di sini, ditangani Task 7
app/Http/Controllers/Api/BriVaInboundController.php     <- JANGAN diedit di sini, ditangani Task 16
app/Http/Controllers/Admin/VirtualAccountController.php <- JANGAN diedit di sini, ditangani Task 12
tests/Feature/Admin/VirtualAccountAuthorizationTest.php
tests/Feature/Admin/VirtualAccountControllerTest.php
app/Exports/VirtualAccountExport.php
tests/Unit/Models/WalletBriVirtualAccountsRelationTest.php
app/Http/Controllers/Admin/ManualPaymentController.php  <- JANGAN diedit di sini, ditangani Task 11
app/Console/Commands/ReconcilePayments.php              <- JANGAN diedit di sini, ditangani Task 17
tests/Feature/Keuangan/SimulateBriInboundCommandTest.php
tests/Unit/Models/BriInboundPaymentLogTest.php
tests/Feature/Keuangan/ReconcilePaymentsQrisTest.php
tests/Feature/Keuangan/PaymentServiceManualTopupTest.php
database/factories/BriVirtualAccountFactory.php     <- SUDAH diedit Step 6
tests/Feature/Keuangan/PaymentChannelModelsTest.php
```

Update `use` di SETIAP file KECUALI yang ditandai "JANGAN diedit di sini". Untuk `app/Exports/VirtualAccountExport.php`: ganti `use App\Models\BriVirtualAccount;` → `use App\Domains\Keuangan\Models\BriVirtualAccount;` — HANYA baris itu, isi class TIDAK disentuh (file ini bukan bagian migrasi controller/view, murni cross-scope touch).

- [ ] **Step 8: Perbaiki gotcha implisit di `app/Models/Wallet.php`**

Baca file, cari baris `return $this->hasMany(BriVirtualAccount::class);` di method `briVirtualAccounts()`, ganti jadi `return $this->hasMany(\App\Domains\Keuangan\Models\BriVirtualAccount::class);`.

- [ ] **Step 9: Verifikasi**

```bash
grep -rln "use App\\\\Models\\\\BriVirtualAccount;\|use App\\\\Models\\\\BriQrisPayment;\|use App\\\\Models\\\\BriInboundPaymentLog;\|use App\\\\Models\\\\ManualPaymentRequest;" --include="*.php" app database tests
```
Expected: hanya file yang ditangani task lain (PaymentService, BriVaInboundController, VirtualAccountController, ManualPaymentController, ReconcilePayments) yang tersisa.

- [ ] **Step 10: Jalankan test scoped**

```bash
php artisan test tests/Unit/Models/BriInboundPaymentLogTest.php tests/Unit/Models/WalletBriVirtualAccountsRelationTest.php tests/Feature/Keuangan/PaymentChannelModelsTest.php
```
Expected: semua PASS.

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah 4 model BRI (BriVirtualAccount, BriQrisPayment, BriInboundPaymentLog, ManualPaymentRequest) ke Domains\Keuangan\Models, perbaiki gotcha Wallet.php"
```

---

## Task 4: Cross-Scope Touch — Sisa Consumer Model Setelah Task 1-3

**Files:**
- Modify: `app/Services/DashboardStatsService.php` (dan `tests/Unit/DashboardStatsServiceTest.php` kalau ada referensi FQCN inline di dalamnya)
- Verifikasi: seluruh daftar consumer Task 1-3 sudah benar-benar ter-update KECUALI yang memang ditangani task lain

**Interfaces:**
- Tidak ada file baru — task ini murni cross-scope touch + verifikasi gate sebelum lanjut ke Contracts/Gateway/Service.

- [ ] **Step 1: Update `app/Services/DashboardStatsService.php`**

Baca file, cari baris `use App\Models\Pembayaran;`, ganti jadi `use App\Domains\Keuangan\Models\Pembayaran;`. Kalau ada referensi FQCN inline lain ke `Tagihan`/`PembayaranTagihan`/model BRI yang terlewat, perbaiki juga (baca seluruh file, jangan cuma grep baris `use`).

- [ ] **Step 2: Verifikasi gabungan — tidak ada `use App\Models\{Model}` tersisa untuk 6 model SP3, KECUALI file yang memang ditangani task lain**

```bash
grep -rln "use App\\\\Models\\\\Pembayaran;\|use App\\\\Models\\\\PembayaranTagihan;\|use App\\\\Models\\\\BriVirtualAccount;\|use App\\\\Models\\\\BriQrisPayment;\|use App\\\\Models\\\\BriInboundPaymentLog;\|use App\\\\Models\\\\ManualPaymentRequest;" --include="*.php" app database tests
```

Expected HANYA menyisakan:
```
app/Services/Finance/PaymentService.php           (Task 7)
app/Services/PembayaranService.php                (Task 8)
app/Http/Controllers/Keuangan/Concerns/AuthorizesPembayaran.php (Task 9)
app/Http/Controllers/Admin/PembayaranController.php    (Task 10)
app/Http/Controllers/Admin/ManualPaymentController.php (Task 11)
app/Http/Controllers/Admin/VirtualAccountController.php (Task 12)
app/Http/Controllers/Keuangan/CheckoutController.php   (Task 14)
app/Http/Controllers/Keuangan/RiwayatController.php    (Task 15)
app/Http/Controllers/Api/BriVaInboundController.php    (Task 16)
app/Console/Commands/ReconcilePayments.php             (Task 17)
app/Services/Finance/Gateway/BriSnapGateway.php        (Task 6)
app/Services/Finance/Gateway/HybridPaymentGateway.php  (Task 6)
app/Services/Finance/Gateway/MockPaymentGateway.php    (Task 6)
```

Kalau ada file LAIN di luar daftar ini yang masih muncul, itu berarti ada consumer yang terlewat di Task 1-3 — perbaiki SEKARANG sebelum lanjut, JANGAN dibiarkan menumpuk.

- [ ] **Step 3: Jalankan test scoped luas**

```bash
php artisan test tests/Unit tests/Feature/Keuangan tests/Feature/Admin --filter="Pembayaran|BriVirtualAccount|BriQris|ManualPayment|Wallet|Cicilan"
```
Expected: semua PASS (kalau ada "Class not found", cek lagi Task 1-4).

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): cross-scope touch DashboardStatsService.php, verifikasi checkpoint model Task 1-3"
```

---

## Task 5: Pindahkan Contract & DTO (`PaymentGatewayInterface`, `BriInboundAuthenticatorInterface`, 3 DTO)

**Files:**
- Move: `app/Contracts/PaymentGatewayInterface.php` → `app/Domains/Keuangan/Contracts/PaymentGatewayInterface.php`
- Move: `app/Contracts/BriInboundAuthenticatorInterface.php` → `app/Domains/Keuangan/Contracts/BriInboundAuthenticatorInterface.php`
- Move: `app/DTO/PaymentStatusResult.php`, `QrisResult.php`, `VirtualAccountResult.php` → `app/Domains/Keuangan/DataTransferObjects/`
- Modify: seluruh file hasil grep untuk kelimanya (Gateway implementations ditangani Task 6, JANGAN diedit di sini)

**Interfaces:**
- Produces: `App\Domains\Keuangan\Contracts\{PaymentGatewayInterface,BriInboundAuthenticatorInterface}`, `App\Domains\Keuangan\DataTransferObjects\{PaymentStatusResult,QrisResult,VirtualAccountResult}` — dipakai Task 6 (Gateway) dan seterusnya.

- [ ] **Step 1: Pindahkan 5 file fisik**

```bash
mkdir -p app/Domains/Keuangan/Contracts app/Domains/Keuangan/DataTransferObjects
git mv app/Contracts/PaymentGatewayInterface.php app/Domains/Keuangan/Contracts/PaymentGatewayInterface.php
git mv app/Contracts/BriInboundAuthenticatorInterface.php app/Domains/Keuangan/Contracts/BriInboundAuthenticatorInterface.php
git mv app/DTO/PaymentStatusResult.php app/Domains/Keuangan/DataTransferObjects/PaymentStatusResult.php
git mv app/DTO/QrisResult.php app/Domains/Keuangan/DataTransferObjects/QrisResult.php
git mv app/DTO/VirtualAccountResult.php app/Domains/Keuangan/DataTransferObjects/VirtualAccountResult.php
```

- [ ] **Step 2: Ubah isi `PaymentGatewayInterface.php`**

Timpa seluruh isi dengan:

```php
<?php

namespace App\Domains\Keuangan\Contracts;

use App\Domains\Keuangan\DataTransferObjects\PaymentStatusResult;
use App\Domains\Keuangan\DataTransferObjects\QrisResult;
use App\Domains\Keuangan\DataTransferObjects\VirtualAccountResult;
use App\Domains\Keuangan\Models\Pembayaran;

interface PaymentGatewayInterface
{
    /**
     * Create a virtual account.
     */
    public function createVirtualAccount(Pembayaran $pembayaran, string $vaType): VirtualAccountResult;

    /**
     * Create a QRIS payment.
     */
    public function createQris(Pembayaran $pembayaran, string $qrisType): QrisResult;

    /**
     * Verify callback signature.
     */
    public function verifyCallbackSignature(string $payload, string $signature): bool;

    /**
     * Check payment status by channel reference (VA number or QRIS reference).
     */
    public function checkStatus(string $channelReference, string $type): PaymentStatusResult;
}
```

- [ ] **Step 3: Ubah isi `BriInboundAuthenticatorInterface.php`**

Timpa seluruh isi dengan:

```php
<?php

namespace App\Domains\Keuangan\Contracts;

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

- [ ] **Step 4: Ubah isi 3 DTO — hanya namespace, isi persis sama**

`app/Domains/Keuangan/DataTransferObjects/PaymentStatusResult.php`:
```php
<?php

namespace App\Domains\Keuangan\DataTransferObjects;

class PaymentStatusResult
{
    public function __construct(
        public readonly string $status,
        public readonly array $payload
    ) {
    }
}
```

`app/Domains/Keuangan/DataTransferObjects/QrisResult.php`:
```php
<?php

namespace App\Domains\Keuangan\DataTransferObjects;

class QrisResult
{
    public function __construct(
        public readonly string $qrCodeData,
        public readonly float $amount,
        public readonly \DateTimeInterface $expiredAt,
        public readonly array $payload
    ) {
    }
}
```

`app/Domains/Keuangan/DataTransferObjects/VirtualAccountResult.php`:
```php
<?php

namespace App\Domains\Keuangan\DataTransferObjects;

class VirtualAccountResult
{
    public function __construct(
        public readonly string $vaNumber,
        public readonly ?float $amount,
        public readonly ?\DateTimeInterface $expiredAt,
        public readonly array $payload
    ) {
    }
}
```

- [ ] **Step 5: Grep ulang dan update seluruh consumer (KECUALI file Gateway yang ditangani Task 6)**

```bash
grep -rln "App\\\\Contracts\\\\PaymentGatewayInterface\|App\\\\Contracts\\\\BriInboundAuthenticatorInterface\|App\\\\DTO\\\\PaymentStatusResult\|App\\\\DTO\\\\QrisResult\|App\\\\DTO\\\\VirtualAccountResult" --include="*.php" app database tests
```

Untuk SETIAP file hasil grep KECUALI `app/Services/Finance/Gateway/*.php` dan `app/Services/Finance/BriInbound/SimpleBriInboundAuthenticator.php` (ditangani Task 6), ganti:
- `use App\Contracts\PaymentGatewayInterface;` → `use App\Domains\Keuangan\Contracts\PaymentGatewayInterface;`
- `use App\Contracts\BriInboundAuthenticatorInterface;` → `use App\Domains\Keuangan\Contracts\BriInboundAuthenticatorInterface;`
- `use App\DTO\PaymentStatusResult;` → `use App\Domains\Keuangan\DataTransferObjects\PaymentStatusResult;`
- `use App\DTO\QrisResult;` → `use App\Domains\Keuangan\DataTransferObjects\QrisResult;`
- `use App\DTO\VirtualAccountResult;` → `use App\Domains\Keuangan\DataTransferObjects\VirtualAccountResult;`

Ini termasuk `app/Providers/AppServiceProvider.php` (baris `use App\Contracts\BriInboundAuthenticatorInterface;` dan bind di `register()` — HANYA baris `use`, isi `register()`/`boot()` TIDAK disentuh di task ini, ditangani Task 6 Step terakhir untuk bagian yang bind ke Gateway class).

- [ ] **Step 6: Verifikasi**

```bash
grep -rln "App\\\\Contracts\\\\PaymentGatewayInterface\|App\\\\Contracts\\\\BriInboundAuthenticatorInterface\|App\\\\DTO\\\\PaymentStatusResult\|App\\\\DTO\\\\QrisResult\|App\\\\DTO\\\\VirtualAccountResult" --include="*.php" app database tests
```
Expected: hanya file di `app/Services/Finance/Gateway/*` dan `BriInbound/SimpleBriInboundAuthenticator.php` yang tersisa.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah PaymentGatewayInterface, BriInboundAuthenticatorInterface, dan 3 DTO ke Domains\Keuangan\Contracts dan DataTransferObjects"
```

---

## Task 6: Pindahkan Gateway Family + `SimpleBriInboundAuthenticator` + Update Binding `AppServiceProvider`

**Files:**
- Move: `app/Services/Finance/Gateway/BriSnapGateway.php`, `MockPaymentGateway.php`, `HybridPaymentGateway.php` → `app/Domains/Keuangan/Services/Gateway/`
- Move: `app/Services/Finance/Gateway/BriSnap/BriSnapClient.php` → `app/Domains/Keuangan/Services/Gateway/BriSnap/BriSnapClient.php`
- Move: `app/Services/Finance/BriInbound/SimpleBriInboundAuthenticator.php` → `app/Domains/Keuangan/Services/BriInbound/SimpleBriInboundAuthenticator.php`
- Modify: `app/Providers/AppServiceProvider.php` (binding `register()`)
- Modify: seluruh file test hasil grep untuk kelima class ini

**Interfaces:**
- Consumes: `App\Domains\Keuangan\Contracts\{PaymentGatewayInterface,BriInboundAuthenticatorInterface}`, `App\Domains\Keuangan\DataTransferObjects\*` (Task 5), `App\Domains\Keuangan\Models\Pembayaran` (Task 1).
- Produces: `App\Domains\Keuangan\Services\Gateway\{BriSnapGateway,MockPaymentGateway,HybridPaymentGateway}`, `App\Domains\Keuangan\Services\Gateway\BriSnap\BriSnapClient`, `App\Domains\Keuangan\Services\BriInbound\SimpleBriInboundAuthenticator`.

- [ ] **Step 1: Pindahkan 5 file fisik**

```bash
mkdir -p app/Domains/Keuangan/Services/Gateway/BriSnap app/Domains/Keuangan/Services/BriInbound
git mv app/Services/Finance/Gateway/BriSnapGateway.php app/Domains/Keuangan/Services/Gateway/BriSnapGateway.php
git mv app/Services/Finance/Gateway/MockPaymentGateway.php app/Domains/Keuangan/Services/Gateway/MockPaymentGateway.php
git mv app/Services/Finance/Gateway/HybridPaymentGateway.php app/Domains/Keuangan/Services/Gateway/HybridPaymentGateway.php
git mv app/Services/Finance/Gateway/BriSnap/BriSnapClient.php app/Domains/Keuangan/Services/Gateway/BriSnap/BriSnapClient.php
git mv app/Services/Finance/BriInbound/SimpleBriInboundAuthenticator.php app/Domains/Keuangan/Services/BriInbound/SimpleBriInboundAuthenticator.php
rmdir app/Services/Finance/Gateway/BriSnap app/Services/Finance/Gateway app/Services/Finance/BriInbound 2>&1 || true
```

- [ ] **Step 2: Ubah isi `BriSnapGateway.php`**

Timpa seluruh isi dengan:

```php
<?php

namespace App\Domains\Keuangan\Services\Gateway;

use App\Domains\Keuangan\Contracts\PaymentGatewayInterface;
use App\Domains\Keuangan\DataTransferObjects\PaymentStatusResult;
use App\Domains\Keuangan\DataTransferObjects\QrisResult;
use App\Domains\Keuangan\DataTransferObjects\VirtualAccountResult;
use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Services\Gateway\BriSnap\BriSnapClient;

class BriSnapGateway implements PaymentGatewayInterface
{
    public function __construct(protected BriSnapClient $client)
    {
    }

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

    public function createQris(Pembayaran $pembayaran, string $qrisType): QrisResult
    {
        $payload = [
            // channel_reference is a 36-char UUID and BRI's docs contradict themselves on
            // partnerReferenceNo's allowed length, so we use the numeric pembayaran id
            // (zero-padded) to avoid that ambiguity entirely.
            'partnerReferenceNo' => str_pad((string) $pembayaran->id, 6, '0', STR_PAD_LEFT),
            'amount' => [
                'value' => number_format($pembayaran->amount, 2, '.', ''),
                'currency' => 'IDR'
            ],
            'merchantId' => config('services.bri.merchant_id'),
            'terminalId' => config('services.bri.terminal_id'),
        ];

        $response = $this->client->post('/snap/v1.1/qr/qr-mpm-generate', $payload);

        if (!isset($response['qrContent'])) {
            throw new \App\Exceptions\BriApiException(
                (string) ($response['responseCode'] ?? 'unknown'),
                'BRI response missing qrContent field'
            );
        }

        return new QrisResult(
            $response['qrContent'],
            $pembayaran->amount,
            now()->addMinutes(15),
            $response
        );
    }

    public function verifyCallbackSignature(string $payload, string $signature): bool
    {
        throw new \RuntimeException('BriSnapGateway VA not fully implemented yet');
    }

    public function checkStatus(string $channelReference, string $type): PaymentStatusResult
    {
        if ($type === 'qris') {
            $payload = [
                'originalReferenceNo' => $channelReference,
                'serviceCode' => '47',
                'additionalInfo' => [
                    'terminalId' => config('services.bri.terminal_id'),
                ]
            ];

            $response = $this->client->post('/snap/v1.1/qr/qr-mpm-query', $payload);

            // Mapped to WAITING/PAID/FAILED to match the bri_qris_payments.status enum
            // values, intentionally not the plan's raw PENDING/PAID binary.
            $status = 'WAITING';
            if (($response['latestTransactionStatus'] ?? '') === '00') {
                $status = 'PAID';
            } elseif (in_array(($response['latestTransactionStatus'] ?? ''), ['04', '05', '06'])) {
                $status = 'FAILED';
            }

            return new PaymentStatusResult($status, $response);
        }

        throw new \RuntimeException('BriSnapGateway VA checkStatus not fully implemented yet');
    }
}
```

- [ ] **Step 3: Ubah isi `MockPaymentGateway.php`**

Timpa seluruh isi dengan:

```php
<?php

namespace App\Domains\Keuangan\Services\Gateway;

use App\Domains\Keuangan\Contracts\PaymentGatewayInterface;
use App\Domains\Keuangan\DataTransferObjects\PaymentStatusResult;
use App\Domains\Keuangan\DataTransferObjects\QrisResult;
use App\Domains\Keuangan\DataTransferObjects\VirtualAccountResult;
use App\Domains\Keuangan\Models\Pembayaran;

class MockPaymentGateway implements PaymentGatewayInterface
{
    public function createVirtualAccount(Pembayaran $pembayaran, string $vaType): VirtualAccountResult
    {
        $vaNumber = 'MOCK-VA-' . str_pad($pembayaran->id, 6, '0', STR_PAD_LEFT);
        $amount = $vaType === 'WALLET_PERMANENT' ? null : 10000;
        $expiredAt = $vaType === 'WALLET_PERMANENT' ? null : now()->addHours(24);

        return new VirtualAccountResult(
            $vaNumber,
            $amount,
            $expiredAt,
            ['mock_response' => true]
        );
    }

    public function createQris(Pembayaran $pembayaran, string $qrisType): QrisResult
    {
        $qrCodeData = 'MOCK-QR-' . str_pad($pembayaran->id, 6, '0', STR_PAD_LEFT);
        $amount = 10000;

        return new QrisResult(
            $qrCodeData,
            $amount,
            now()->addMinutes(15),
            ['mock_response' => true]
        );
    }

    public function verifyCallbackSignature(string $payload, string $signature): bool
    {
        return true;
    }

    public function checkStatus(string $channelReference, string $type): PaymentStatusResult
    {
        return new PaymentStatusResult('PAID', ['mock_response' => true]);
    }
}
```

- [ ] **Step 4: Ubah isi `HybridPaymentGateway.php`**

Timpa seluruh isi dengan:

```php
<?php

namespace App\Domains\Keuangan\Services\Gateway;

use App\Domains\Keuangan\Contracts\PaymentGatewayInterface;
use App\Domains\Keuangan\DataTransferObjects\PaymentStatusResult;
use App\Domains\Keuangan\DataTransferObjects\QrisResult;
use App\Domains\Keuangan\DataTransferObjects\VirtualAccountResult;
use App\Domains\Keuangan\Models\Pembayaran;

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

- [ ] **Step 5: Ubah isi `BriSnapClient.php` — HANYA namespace, isi identik (tidak ada dependency model apapun)**

Timpa baris pertama isi file (baris 1-3) dari:
```php
<?php

namespace App\Services\Finance\Gateway\BriSnap;
```
menjadi:
```php
<?php

namespace App\Domains\Keuangan\Services\Gateway\BriSnap;
```
Sisa isi file (baris 4 sampai akhir) TIDAK berubah sama sekali — baca dulu untuk konfirmasi tidak ada `use App\Models\*`/`use App\Contracts\*` di file ini (dikonfirmasi saat plan ditulis: hanya `use App\Exceptions\BriApiException;`, `Illuminate\Support\Facades\Cache;`, `Illuminate\Support\Facades\Http;` — ketiganya TIDAK pindah, tidak perlu diubah).

- [ ] **Step 6: Ubah isi `SimpleBriInboundAuthenticator.php`**

Timpa seluruh isi dengan:

```php
<?php

namespace App\Domains\Keuangan\Services\BriInbound;

use App\Domains\Keuangan\Contracts\BriInboundAuthenticatorInterface;
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

- [ ] **Step 7: Update `app/Providers/AppServiceProvider.php`**

Baca file, ganti baris `use`:
```php
use App\Services\Finance\Gateway\BriSnapGateway;
use App\Services\Finance\Gateway\MockPaymentGateway;
use App\Services\Finance\Gateway\BriSnap\BriSnapClient;
use App\Services\Finance\Gateway\HybridPaymentGateway;
use App\Services\Finance\BriInbound\SimpleBriInboundAuthenticator;
```
menjadi:
```php
use App\Domains\Keuangan\Services\Gateway\BriSnapGateway;
use App\Domains\Keuangan\Services\Gateway\MockPaymentGateway;
use App\Domains\Keuangan\Services\Gateway\BriSnap\BriSnapClient;
use App\Domains\Keuangan\Services\Gateway\HybridPaymentGateway;
use App\Domains\Keuangan\Services\BriInbound\SimpleBriInboundAuthenticator;
```

Isi `register()`/`boot()` (logic bind, kondisi `$gatewayConfig`) TIDAK berubah, hanya `use` di atas yang diganti. `use App\Contracts\BriInboundAuthenticatorInterface;` sudah diganti di Task 5 Step 5 — verifikasi baris itu sudah `use App\Domains\Keuangan\Contracts\BriInboundAuthenticatorInterface;`, kalau belum perbaiki sekarang.

- [ ] **Step 8: Grep ulang untuk test consumer**

```bash
grep -rln "App\\\\Services\\\\Finance\\\\Gateway\\\\BriSnapGateway\|App\\\\Services\\\\Finance\\\\Gateway\\\\MockPaymentGateway\|App\\\\Services\\\\Finance\\\\Gateway\\\\HybridPaymentGateway\|App\\\\Services\\\\Finance\\\\Gateway\\\\BriSnap\\\\BriSnapClient\|App\\\\Services\\\\Finance\\\\BriInbound\\\\SimpleBriInboundAuthenticator" --include="*.php" app database tests
```

Update `use`/FQCN di SETIAP file hasil grep (kemungkinan besar `tests/Feature/Keuangan/GatewayImplementationTest.php`, `HybridPaymentGatewayTest.php`, `BriSnapGatewayIntegrationTest.php`, dan lainnya — grep ulang untuk daftar pasti) ke namespace `Domains\Keuangan\Services\Gateway\*`/`BriInbound\*`.

- [ ] **Step 9: Verifikasi**

```bash
grep -rln "App\\\\Services\\\\Finance\\\\Gateway\\\\\|App\\\\Services\\\\Finance\\\\BriInbound\\\\" --include="*.php" app database tests
```
Expected: kosong.

```bash
ls app/Services/Finance/Gateway app/Services/Finance/BriInbound 2>&1
```
Expected: error "No such file or directory" untuk keduanya (folder terhapus otomatis setelah kosong).

- [ ] **Step 10: Jalankan test scoped**

```bash
php artisan config:clear
php artisan test tests/Feature/Keuangan/GatewayImplementationTest.php tests/Feature/Keuangan/HybridPaymentGatewayTest.php tests/Feature/Keuangan/BriSnapGatewayIntegrationTest.php
```
Expected: semua PASS. `config:clear` dijalankan karena `AppServiceProvider` binding berubah — cache config lama bisa menyimpan referensi class lama.

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah Gateway family + SimpleBriInboundAuthenticator ke Domains\Keuangan\Services, update binding AppServiceProvider"
```

---

## Task 7: Pindahkan `PaymentService` (Finance, Jalur Checkout Modern)

**Files:**
- Move: `app/Services/Finance/PaymentService.php` → `app/Domains/Keuangan/Services/PaymentService.php`
- Modify: seluruh file hasil grep `use App\Services\Finance\PaymentService;`

**Interfaces:**
- Consumes: `App\Domains\Keuangan\Contracts\PaymentGatewayInterface` (Task 5), `App\Domains\Keuangan\Models\{Pembayaran,PembayaranTagihan,BriQrisPayment,BriVirtualAccount,ManualPaymentRequest,Tagihan}` (Task 1-3), `App\Services\Finance\PaymentAllocationService` (TIDAK PINDAH, tetap `app/Services/Finance/`).
- Produces: `App\Domains\Keuangan\Services\PaymentService` — dipakai Task 12 (VirtualAccountController), Task 14 (CheckoutController).

- [ ] **Step 1: Pindahkan file fisik**

```bash
git mv app/Services/Finance/PaymentService.php app/Domains/Keuangan/Services/PaymentService.php
```

- [ ] **Step 2: Ubah isi file**

Timpa seluruh isi `app/Domains/Keuangan/Services/PaymentService.php` dengan:

```php
<?php

namespace App\Domains\Keuangan\Services;

use App\Domains\Keuangan\Contracts\PaymentGatewayInterface;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\PaymentException;
use App\Domains\Keuangan\Models\BriQrisPayment;
use App\Domains\Keuangan\Models\BriVirtualAccount;
use App\Domains\Keuangan\Models\ManualPaymentRequest;
use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Models\PembayaranTagihan;
use App\Models\Siswa;
use App\Domains\Keuangan\Models\Tagihan;
use App\Services\Finance\PaymentAllocationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        protected PaymentGatewayInterface $gateway,
        protected PaymentAllocationService $allocationService
    ) {
    }


    /**
     * Create QRIS payment.
     */
    public function createQrisPayment(Siswa $siswa, Collection $tagihans): Pembayaran
    {
        $this->guardAgainstInvalidTagihan($tagihans);

        return DB::transaction(function () use ($siswa, $tagihans) {
            $pembayaran = $this->createPembayaranRecord($siswa, $tagihans, 'qris', 'menunggu_pembayaran');

            $qrisResult = $this->gateway->createQris($pembayaran, 'DIRECT');

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

            return $pembayaran;
        });
    }

    /**
     * Create QRIS payment bundled with a wallet top-up.
     */
    public function createQrisPaymentWithTopup(Siswa $siswa, Collection $tagihans, float $topupAmount): Pembayaran
    {
        if ($topupAmount <= 0 || $tagihans->isEmpty()) {
            throw new PaymentException('Top-up hanya bisa digabung dengan minimal satu tagihan dan nominal top-up harus lebih dari 0.');
        }

        $this->guardAgainstInvalidTagihan($tagihans);

        return DB::transaction(function () use ($siswa, $tagihans, $topupAmount) {
            $pembayaran = $this->createPembayaranRecord($siswa, $tagihans, 'qris', 'menunggu_pembayaran');

            $pembayaran->amount = $pembayaran->pembayaranTagihan()->sum('amount_allocated') + $topupAmount;
            $pembayaran->topup_status = 'pending';
            $pembayaran->save();

            $qrisResult = $this->gateway->createQris($pembayaran, 'DIRECT');

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

            return $pembayaran;
        });
    }
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

    /**
     * Create manual payment request.
     */
    public function createManualPayment(Siswa $siswa, Collection $tagihans, array $data): Pembayaran
    {
        $this->guardAgainstInvalidTagihan($tagihans);

        return DB::transaction(function () use ($siswa, $tagihans, $data) {
            $pembayaran = $this->createPembayaranRecord($siswa, $tagihans, 'transfer_manual', 'menunggu_verifikasi');

            ManualPaymentRequest::create([
                'pembayaran_id' => $pembayaran->id,
                'requested_by' => $data['requested_by'],
                'amount' => $data['amount'],
                'transfer_proof_path' => $data['transfer_proof_path'],
                'bank_origin' => $data['bank_origin'] ?? null,
                'transfer_date' => $data['transfer_date'],
                'status' => 'PENDING',
            ]);

            return $pembayaran;
        });
    }

    /**
     * Create a manual transfer request intended as a wallet top-up (not tied to any tagihan).
     */
    public function createManualTopupPayment(Siswa $siswa, array $data): Pembayaran
    {
        return DB::transaction(function () use ($siswa, $data) {
            $pembayaran = Pembayaran::create([
                'siswa_id' => $siswa->id,
                'metode' => 'transfer_manual',
                'status' => 'menunggu_verifikasi',
                'amount' => $data['amount'],
                'topup_status' => 'pending',
                'channel_reference' => (string) Str::uuid(),
            ]);

            ManualPaymentRequest::create([
                'pembayaran_id' => $pembayaran->id,
                'requested_by' => $data['requested_by'],
                'amount' => $data['amount'],
                'transfer_proof_path' => $data['transfer_proof_path'],
                'bank_origin' => $data['bank_origin'] ?? null,
                'transfer_date' => $data['transfer_date'],
                'status' => 'PENDING',
            ]);

            return $pembayaran;
        });
    }

    /**
     * Pay one or more tagihan directly from the student's wallet balance.
     * Debits within the same locked transaction as the balance check
     * (via Wallet::debitWithinTransaction) so two concurrent submissions
     * cannot both pass the balance check and double-spend.
     */
    public function createWalletPayment(Siswa $siswa, Collection $tagihans): Pembayaran
    {
        $this->guardAgainstInvalidTagihan($tagihans);

        $tagihanIds = $tagihans->pluck('id');

        return DB::transaction(function () use ($siswa, $tagihanIds) {
            $wallet = $siswa->wallet()->lockForUpdate()->first();

            if ($wallet === null) {
                throw new PaymentException('Siswa tidak memiliki wallet.');
            }

            // Re-fetch the tagihan set fresh from the database now that the wallet
            // row is locked, so a concurrent request that already paid these tagihan
            // (and is now unblocked by that lock) doesn't operate on a stale,
            // pre-payment copy of paid_amount/status. We intentionally do NOT
            // lockForUpdate() the tagihan rows here -- PaymentAllocationService::allocate()
            // already does its own row-level locking further down this flow.
            $tagihans = Tagihan::whereIn('id', $tagihanIds)
                ->whereIn('status', ['belum_bayar', 'sebagian'])
                ->get();

            if ($tagihans->count() !== $tagihanIds->count()) {
                throw new PaymentException('Tagihan sudah berubah status, silakan coba lagi.');
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

    /**
     * Create instant cash payment (kasir).
     */
    public function createCashPayment(Siswa $siswa, Collection $tagihans, int $cashierUserId): Pembayaran
    {
        $this->guardAgainstInvalidTagihan($tagihans);

        return DB::transaction(function () use ($siswa, $tagihans, $cashierUserId) {
            $pembayaran = $this->createPembayaranRecord($siswa, $tagihans, 'cash', 'lunas');

            $pembayaran->update([
                'diverifikasi_oleh_user_id' => $cashierUserId,
                'diverifikasi_pada' => now(),
            ]);

            // For cash, we allocate immediately
            $this->allocationService->allocate($pembayaran);

            return $pembayaran;
        });
    }

    protected function guardAgainstInvalidTagihan(Collection $tagihans): void
    {
        foreach ($tagihans as $tagihan) {
            if (in_array($tagihan->status, ['dibatalkan', 'lunas'])) {
                throw new PaymentException('Terdapat tagihan yang sudah dibatalkan atau lunas.');
            }
        }
    }

    protected function createPembayaranRecord(Siswa $siswa, Collection $tagihans, string $metode, string $status): Pembayaran
    {
        $pembayaran = Pembayaran::create([
            'siswa_id' => $siswa->id,
            'metode' => $metode,
            'status' => $status,
            'channel_reference' => (string) Str::uuid(),
        ]);

        foreach ($tagihans as $tagihan) {
            $amountToPay = $tagihan->net_amount - $tagihan->paid_amount;
            if ($amountToPay > 0) {
                PembayaranTagihan::create([
                    'pembayaran_id' => $pembayaran->id,
                    'tagihan_id' => $tagihan->id,
                    'amount_allocated' => $amountToPay,
                ]);
            }
        }

        return $pembayaran;
    }
}
```

Catatan: `PaymentAllocationService` TETAP `use App\Services\Finance\PaymentAllocationService;` — kelas itu SENGAJA TIDAK pindah (ditunda SP4), `PaymentService` di domain baru meng-inject-nya dari lokasi lama, persis pola cross-domain dependency yang sudah dipakai berulang di SP1/SP2.

- [ ] **Step 3: Grep ulang dan update consumer**

```bash
grep -rln "use App\\\\Services\\\\Finance\\\\PaymentService;" --include="*.php" app database tests
```

Update `use App\Services\Finance\PaymentService;` → `use App\Domains\Keuangan\Services\PaymentService;` di SETIAP file hasil grep KECUALI `app/Http/Controllers/Admin/VirtualAccountController.php` (Task 12) dan `app/Http/Controllers/Keuangan/CheckoutController.php` (Task 14).

- [ ] **Step 4: Verifikasi**

```bash
grep -rln "use App\\\\Services\\\\Finance\\\\PaymentService;" --include="*.php" app database tests
```
Expected: hanya `VirtualAccountController.php` dan `CheckoutController.php` yang tersisa.

- [ ] **Step 5: Jalankan test scoped**

```bash
php artisan test tests/Feature/Keuangan/PaymentServiceTest.php tests/Feature/Keuangan/PaymentServiceWalletPaymentTest.php tests/Feature/Keuangan/PaymentServiceBundledTopupTest.php tests/Feature/Keuangan/PaymentServiceManualTopupTest.php
```
Expected: semua PASS.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah PaymentService (Finance) ke Domains\Keuangan\Services"
```

---

## Task 8: Pindahkan `PembayaranService` (Legacy, Jalur Verifikasi Manual PPDB) + Cross-Scope Touch Consumer SP1/SP2

**Files:**
- Move: `app/Services/PembayaranService.php` → `app/Domains/Keuangan/Services/PembayaranService.php`
- Modify: `app/Domains/Keuangan/Actions/Tagihan/BuatSkemaCicilanAction.php`, `SimpanNominalCicilanAction.php`, `CatatManualTagihanAction.php`, `CatatManualCicilanAction.php` (SP2 — TIDAK dimigrasi controllernya lagi di sini, HANYA `use PembayaranService` yang diupdate)
- Modify: `app/Http/Controllers/Portal/TagihanController.php` (portal pendaftar PPDB, TIDAK dimigrasi sejak SP2 — HANYA `use PembayaranService` yang diupdate)
- Modify: `app/Http/Controllers/Admin/PembayaranController.php` (JANGAN pindah/refactor total di sini — itu Task 10, HANYA `use` di-update dulu supaya tidak error di window antar-task)

**Interfaces:**
- Consumes: `App\Domains\Keuangan\Models\{Tagihan,SkemaCicilan}` (SP1/SP2), `App\Models\Cicilan` (SP4, tetap).
- Produces: `App\Domains\Keuangan\Services\PembayaranService` — dipakai Task 10 dan seluruh consumer SP2 yang di-cross-scope-touch di sini.

- [ ] **Step 1: Pindahkan file fisik**

```bash
git mv app/Services/PembayaranService.php app/Domains/Keuangan/Services/PembayaranService.php
```

- [ ] **Step 2: Ubah isi file**

Timpa seluruh isi `app/Domains/Keuangan/Services/PembayaranService.php` dengan:

```php
<?php
// app/Domains/Keuangan/Services/PembayaranService.php

namespace App\Domains\Keuangan\Services;

use App\Models\Cicilan;
use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Models\SkemaCicilan;
use App\Domains\Keuangan\Models\Tagihan;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class PembayaranService
{
    /**
     * The only place that ever creates a skema_cicilan + its cicilan rows.
     * Splits evenly with the rounding remainder absorbed by the last termin,
     * so the sum always matches total_tagihan exactly.
     */
    public function buatSkemaCicilan(Tagihan $tagihan, int $jumlahTermin, string $dibuatOleh, ?int $dibuatOlehUserId = null): SkemaCicilan
    {
        if ($tagihan->skemaCicilan()->exists()) {
            throw new RuntimeException('Tagihan ini sudah punya skema cicilan.');
        }

        $totalTagihan = (int) $tagihan->total_tagihan;
        $perTermin = intdiv($totalTagihan, $jumlahTermin);

        return DB::transaction(function () use ($tagihan, $jumlahTermin, $dibuatOleh, $dibuatOlehUserId, $totalTagihan, $perTermin) {
            $skema = SkemaCicilan::create([
                'tagihan_id' => $tagihan->id,
                'jumlah_termin' => $jumlahTermin,
                'dibuat_oleh' => $dibuatOleh,
                'dibuat_oleh_user_id' => $dibuatOlehUserId,
            ]);

            $jatuhTempoTerakhir = null;

            for ($urutan = 1; $urutan <= $jumlahTermin; $urutan++) {
                $nominal = $urutan < $jumlahTermin
                    ? $perTermin
                    : $totalTagihan - ($perTermin * ($jumlahTermin - 1));
                $jatuhTempoTerakhir = now()->addDays(30 * $urutan);

                Cicilan::create([
                    'skema_cicilan_id' => $skema->id,
                    'urutan' => $urutan,
                    'nominal' => $nominal,
                    'jatuh_tempo' => $jatuhTempoTerakhir,
                    'status' => 'belum_bayar',
                ]);
            }

            $tagihan->update(['status' => 'dicicil', 'jatuh_tempo' => $jatuhTempoTerakhir]);

            return $skema->fresh();
        });
    }

    /**
     * Admin-only manual override of per-termin nominal. Rejects (throws,
     * saves nothing) unless the new total matches total_tagihan exactly, and
     * refuses to touch a termin that is already lunas.
     */
    public function simpanNominalManual(SkemaCicilan $skemaCicilan, array $nominalPerUrutan): void
    {
        $cicilanByUrutan = $skemaCicilan->cicilan()->get()->keyBy('urutan');

        if ($cicilanByUrutan->keys()->sort()->values()->all() !== collect(array_keys($nominalPerUrutan))->sort()->values()->all()) {
            throw new InvalidArgumentException('Nominal harus diisi untuk semua termin, tidak boleh sebagian.');
        }

        foreach ($nominalPerUrutan as $urutan => $nominal) {
            if ($cicilanByUrutan[$urutan]->status === 'lunas') {
                throw new InvalidArgumentException("Termin {$urutan} sudah lunas, tidak bisa diubah.");
            }
        }

        $total = array_sum($nominalPerUrutan);
        $totalTagihan = (int) $skemaCicilan->tagihan->total_tagihan;

        if ($total !== $totalTagihan) {
            throw new InvalidArgumentException("Total nominal cicilan (Rp{$total}) harus persis sama dengan total tagihan (Rp{$totalTagihan}).");
        }

        DB::transaction(function () use ($skemaCicilan, $nominalPerUrutan) {
            foreach ($nominalPerUrutan as $urutan => $nominal) {
                $skemaCicilan->cicilan()->where('urutan', $urutan)->update(['nominal' => $nominal]);
            }
        });
    }

    /**
     * The only place a pembayaran row is ever created. Insert-only: never
     * reuses/updates a prior rejected attempt for the same target.
     */
    public function catatPembayaran(?Tagihan $tagihan, ?Cicilan $cicilan, string $sumber, ?string $filePath, ?int $userId): Pembayaran
    {
        if (($tagihan === null) === ($cicilan === null)) {
            throw new InvalidArgumentException('Tepat satu dari tagihan atau cicilan harus diisi.');
        }

        if ($cicilan) {
            $this->pastikanUrutanBoleh($cicilan);
        }

        return DB::transaction(function () use ($tagihan, $cicilan, $sumber, $filePath, $userId) {
            // Lock the target row so concurrent attempts for the same
            // tagihan/cicilan serialize on this row instead of racing past
            // the "already has an active payment" check below.
            if ($tagihan) {
                $tagihan = Tagihan::whereKey($tagihan->id)->lockForUpdate()->first();
            } else {
                $cicilan = Cicilan::whereKey($cicilan->id)->lockForUpdate()->first();
            }

            $adaPembayaranAktif = $tagihan
                ? Pembayaran::where('tagihan_id', $tagihan->id)->whereIn('status', ['menunggu_verifikasi', 'lunas'])->exists()
                : Pembayaran::where('cicilan_id', $cicilan->id)->whereIn('status', ['menunggu_verifikasi', 'lunas'])->exists();

            if ($adaPembayaranAktif) {
                throw new RuntimeException('Sudah ada pembayaran yang menunggu verifikasi atau sudah lunas untuk ini.');
            }

            $statusAwal = $sumber === 'admin' ? 'lunas' : 'menunggu_verifikasi';

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

            if ($statusAwal === 'lunas') {
                $this->tandaiLunas($tagihan, $cicilan);
            } elseif ($cicilan) {
                $cicilan->update(['status' => 'menunggu_verifikasi']);
            }

            return $pembayaran;
        });
    }

    /**
     * The only place a pembayaran row's status is ever mutated after
     * creation. On 'lunas', cascades into cicilan/tagihan status via the
     * same shared tandaiLunas() logic catatPembayaran() uses for the
     * sumber=admin fast path — one code path, never duplicated.
     */
    public function verifikasiPembayaran(Pembayaran $pembayaran, string $keputusan, ?string $catatan, int $adminUserId): void
    {
        if ($pembayaran->status !== 'menunggu_verifikasi') {
            throw new RuntimeException('Pembayaran ini sudah diverifikasi sebelumnya.');
        }

        DB::transaction(function () use ($pembayaran, $keputusan, $catatan, $adminUserId) {
            $pembayaran->update([
                'status' => $keputusan,
                'catatan_verifikasi' => $catatan,
                'diverifikasi_oleh_user_id' => $adminUserId,
                'diverifikasi_pada' => now(),
            ]);

            if ($keputusan === 'lunas') {
                $this->tandaiLunas($pembayaran->tagihan, $pembayaran->cicilan);
            } elseif ($pembayaran->cicilan) {
                $pembayaran->cicilan->update(['status' => 'ditolak']);
            }
        });
    }

    private function pastikanUrutanBoleh(Cicilan $cicilan): void
    {
        if ($cicilan->urutan === 1) {
            return;
        }

        $terminSebelumnya = Cicilan::where('skema_cicilan_id', $cicilan->skema_cicilan_id)
            ->where('urutan', $cicilan->urutan - 1)
            ->first();

        if (! $terminSebelumnya || $terminSebelumnya->status !== 'lunas') {
            throw new RuntimeException('Termin sebelumnya belum lunas — bayar berurutan.');
        }
    }

    private function tandaiLunas(?Tagihan $tagihan, ?Cicilan $cicilan): void
    {
        if ($tagihan) {
            $tagihan->update(['status' => 'lunas']);

            return;
        }

        $cicilan->update(['status' => 'lunas']);

        $skema = $cicilan->skemaCicilan;
        $semuaLunas = $skema->cicilan()->where('status', '!=', 'lunas')->doesntExist();

        if ($semuaLunas) {
            $skema->tagihan->update(['status' => 'lunas']);
        }
    }
}
```

- [ ] **Step 3: Update 4 Action SP2**

Untuk setiap file `app/Domains/Keuangan/Actions/Tagihan/{BuatSkemaCicilanAction,SimpanNominalCicilanAction,CatatManualTagihanAction,CatatManualCicilanAction}.php`, ganti baris `use App\Services\PembayaranService;` menjadi `use App\Domains\Keuangan\Services\PembayaranService;`. Tidak ada perubahan lain di keempat file itu.

- [ ] **Step 4: Update `app/Http/Controllers/Portal/TagihanController.php`**

Ganti baris `use App\Services\PembayaranService;` menjadi `use App\Domains\Keuangan\Services\PembayaranService;`. File ini TIDAK dimigrasi (portal pendaftar PPDB) — hanya baris ini yang disentuh.

- [ ] **Step 5: Update `app/Http/Controllers/Admin/PembayaranController.php` (sementara, sebelum direfactor total di Task 10)**

Ganti baris `use App\Services\PembayaranService;` menjadi `use App\Domains\Keuangan\Services\PembayaranService;`. Controller ini akan direfactor total (namespace pindah + jadi Action) di Task 10 — perubahan di sini murni supaya file tetap valid di window antar-task.

- [ ] **Step 6: Grep ulang untuk verifikasi**

```bash
grep -rln "use App\\\\Services\\\\PembayaranService;" --include="*.php" app database tests
```
Expected: kosong (kalau ada file lain muncul yang belum ditangani Step 3-5, update juga di sini).

- [ ] **Step 7: Jalankan test scoped**

```bash
php artisan test tests/Unit/PembayaranServiceTest.php tests/Feature/Admin/VerifikasiPembayaranTest.php tests/Feature/Admin/CatatManualPembayaranTest.php tests/Feature/Portal/TagihanPembayaranTest.php tests/Feature/Admin/SkemaCicilanTest.php tests/Feature/Admin/TagihanIndexTest.php
```
Expected: semua PASS.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah PembayaranService (legacy) ke Domains\Keuangan\Services, update 6 consumer cross-scope (4 Action SP2, Portal\TagihanController, Admin\PembayaranController)"
```

---

## Task 9: Pindahkan Trait `AuthorizesPembayaran`

**Files:**
- Move: `app/Http/Controllers/Keuangan/Concerns/AuthorizesPembayaran.php` → `app/Domains/Keuangan/Concerns/AuthorizesPembayaran.php`
- Modify: `app/Http/Controllers/Keuangan/CheckoutController.php`, `app/Http/Controllers/Keuangan/RiwayatController.php` (HANYA `use` — controllernya sendiri direfactor total di Task 14/15)

**Interfaces:**
- Produces: `App\Domains\Keuangan\Concerns\AuthorizesPembayaran` — dipakai Task 14, 15.

**PENTING — GUARD KRITIS**: trait ini berisi `abort_unless($ownsChild, 403)` (§7.5 spec) — cek kepemilikan orangTua-siswa via `TenantScope` bypass. Isi method HARUS disalin PERSIS, TIDAK ADA perubahan logic sedikit pun.

- [ ] **Step 1: Pindahkan file fisik**

```bash
mkdir -p app/Domains/Keuangan/Concerns
git mv app/Http/Controllers/Keuangan/Concerns/AuthorizesPembayaran.php app/Domains/Keuangan/Concerns/AuthorizesPembayaran.php
rmdir app/Http/Controllers/Keuangan/Concerns 2>&1 || true
```

- [ ] **Step 2: Ubah isi file — HANYA namespace, logic identik**

Timpa seluruh isi dengan:

```php
<?php
// app/Domains/Keuangan/Concerns/AuthorizesPembayaran.php

namespace App\Domains\Keuangan\Concerns;

use App\Domains\Keuangan\Models\Pembayaran;
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

- [ ] **Step 3: Update `use` di 2 controller (sementara, sebelum direfactor total Task 14/15)**

Di `app/Http/Controllers/Keuangan/CheckoutController.php` dan `app/Http/Controllers/Keuangan/RiwayatController.php`, ganti baris `use App\Http\Controllers\Keuangan\Concerns\AuthorizesPembayaran;` menjadi `use App\Domains\Keuangan\Concerns\AuthorizesPembayaran;`. Tidak ada perubahan lain — kedua controller ini akan direfactor total di Task 14/15.

- [ ] **Step 4: Grep ulang untuk verifikasi**

```bash
grep -rln "use App\\\\Http\\\\Controllers\\\\Keuangan\\\\Concerns\\\\AuthorizesPembayaran;" --include="*.php" app database tests
```
Expected: kosong.

```bash
ls app/Http/Controllers/Keuangan/Concerns 2>&1
```
Expected: error "No such file or directory".

- [ ] **Step 5: Jalankan test scoped**

```bash
php artisan test tests/Feature/Keuangan/CheckoutAuthorizationTest.php tests/Feature/Keuangan/RiwayatAuthorizationTest.php
```
Expected: semua PASS.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah trait AuthorizesPembayaran ke Domains\Keuangan\Concerns"
```

---

## Task 10: Refactor `Admin\PembayaranController` — Namespace + Action

**Files:**
- Create: `app/Http/Controllers/Lembaga/Keuangan/PembayaranController.php`
- Delete: `app/Http/Controllers/Admin/PembayaranController.php`
- Create: `app/Domains/Keuangan/Actions/Pembayaran/VerifikasiPembayaranAction.php`
- Move: `resources/views/admin/pembayaran/*` → `resources/views/portals/lembaga/keuangan/pembayaran/`
- Modify: `routes/admin/keuangan.php`

**Interfaces:**
- Consumes: `App\Domains\Keuangan\Models\Pembayaran` (Task 1), `App\Domains\Keuangan\Services\PembayaranService` (Task 8).
- Produces: `App\Domains\Keuangan\Actions\Pembayaran\VerifikasiPembayaranAction`.

**GUARD KRITIS §7.1 WAJIB dipertahankan persis**: `$pendaftaranLembagaId = $pembayaran->tagihan?->pendaftaran->lembaga_id ?? $pembayaran->cicilan->skemaCicilan->tagihan->pendaftaran->lembaga_id;` — dua jalur resolusi (via `tagihan` ATAU `cicilan`) dengan null-coalesce, TIDAK BOLEH diubah urutannya.

Baseline kode controller (117 baris, commit `ffe5400`) — baca ulang untuk konfirmasi sebelum edit.

- [ ] **Step 1: Buat `VerifikasiPembayaranAction`**

`app/Domains/Keuangan/Actions/Pembayaran/VerifikasiPembayaranAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\Pembayaran;

use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Services\PembayaranService;

class VerifikasiPembayaranAction
{
    public function __construct(private readonly PembayaranService $service)
    {
    }

    /**
     * @throws \RuntimeException
     */
    public function execute(Pembayaran $pembayaran, string $keputusan, ?string $catatan, int $adminUserId): void
    {
        $this->service->verifikasiPembayaran($pembayaran, $keputusan, $catatan, $adminUserId);
    }
}
```

- [ ] **Step 2: Buat controller baru di `Lembaga\Keuangan\`**

`app/Http/Controllers/Lembaga/Keuangan/PembayaranController.php`:

```php
<?php
// app/Http/Controllers/Lembaga/Keuangan/PembayaranController.php

namespace App\Http\Controllers\Lembaga\Keuangan;

use App\Domains\Keuangan\Actions\Pembayaran\VerifikasiPembayaranAction;
use App\Domains\Keuangan\Models\Pembayaran;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PembayaranController extends Controller
{
    use AuthorizesRequests;

    private function lembagaId(Request $request): ?int
    {
        return $request->user()->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $request->user()->lembaga_id;
    }

    private function labelJenis(Pembayaran $pembayaran): string
    {
        if ($pembayaran->cicilan_id) {
            $nominal = number_format($pembayaran->cicilan->nominal, 0, ',', '.');

            return "Cicilan Termin {$pembayaran->cicilan->urutan} — Rp {$nominal}";
        }

        $label = $pembayaran->tagihan->kategori === 'pendaftaran' ? 'Tagihan Pendaftaran' : 'Tagihan Daftar Ulang';
        $nominal = number_format($pembayaran->tagihan->total_tagihan, 0, ',', '.');

        return "{$label} — Rp {$nominal}";
    }

    public function index(Request $request): View
    {
        $this->authorize('pembayaran.view');

        return view('portals.lembaga.keuangan.pembayaran.index', [
            'lembagaBelumDipilih' => $this->lembagaId($request) === null,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('pembayaran.view');

        $lembagaId = $this->lembagaId($request);

        if ($lembagaId === null) {
            return response()->json([
                'data' => [],
                'meta' => ['current_page' => 0, 'last_page' => 0, 'per_page' => 0, 'total' => 0],
            ]);
        }

        $query = Pembayaran::where('status', 'menunggu_verifikasi')
            ->where(function ($q) use ($lembagaId) {
                $q->whereHas('tagihan.pendaftaran', fn ($p) => $p->where('lembaga_id', $lembagaId))
                    ->orWhereHas('cicilan.skemaCicilan.tagihan.pendaftaran', fn ($p) => $p->where('lembaga_id', $lembagaId));
            })
            ->with(['tagihan.pendaftaran.calonMurid', 'cicilan.skemaCicilan.tagihan.pendaftaran.calonMurid'])
            ->latest('created_at');

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->getCollection()->map(function (Pembayaran $pembayaran) {
                $pendaftaran = $pembayaran->tagihan?->pendaftaran ?? $pembayaran->cicilan->skemaCicilan->tagihan->pendaftaran;

                return [
                    'id' => $pembayaran->id,
                    'nama_calon_murid' => $pendaftaran->calonMurid->nama_lengkap,
                    'kode_pendaftaran' => $pendaftaran->kode_pendaftaran,
                    'jenis' => $this->labelJenis($pembayaran),
                    'sumber' => $pembayaran->sumber,
                    'pendaftaran_id' => $pendaftaran->id,
                ];
            })->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function verifikasi(Request $request, Pembayaran $pembayaran, VerifikasiPembayaranAction $action): RedirectResponse
    {
        $this->authorize('pembayaran.verifikasi');

        $pendaftaranLembagaId = $pembayaran->tagihan?->pendaftaran->lembaga_id
            ?? $pembayaran->cicilan->skemaCicilan->tagihan->pendaftaran->lembaga_id;
        abort_unless($pendaftaranLembagaId === $this->lembagaId($request), 404);

        $data = $request->validate([
            'keputusan' => ['required', 'in:lunas,ditolak'],
            'catatan_verifikasi' => ['required_if:keputusan,ditolak', 'nullable', 'string', 'max:1000'],
        ]);

        try {
            $action->execute($pembayaran, $data['keputusan'], $data['catatan_verifikasi'] ?? null, $request->user()->id);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['keputusan' => $exception->getMessage()]);
        }

        return redirect()->route('admin.pembayaran.index')->with('status', 'Pembayaran berhasil diverifikasi.');
    }
}
```

- [ ] **Step 3: Hapus controller lama**

```bash
git rm app/Http/Controllers/Admin/PembayaranController.php
```

- [ ] **Step 4: Pindahkan view**

```bash
mkdir -p resources/views/portals/lembaga/keuangan/pembayaran
git mv resources/views/admin/pembayaran/index.blade.php resources/views/portals/lembaga/keuangan/pembayaran/index.blade.php
```

Kalau ada file lain di `resources/views/admin/pembayaran/` (partial dsb — cek dulu dengan `ls resources/views/admin/pembayaran/`), pindahkan semuanya dengan `git mv` yang sama, dan cek `@include` internal antar file (kalau ada, sesuaikan path prefix-nya dari `admin.pembayaran.*` ke `portals.lembaga.keuangan.pembayaran.*`).

- [ ] **Step 5: Update `routes/admin/keuangan.php`**

Ganti baris:
```php
use App\Http\Controllers\Admin\PembayaranController;
```
menjadi:
```php
use App\Http\Controllers\Lembaga\Keuangan\PembayaranController;
```

Baris route `pembayaran.*` (baris 34-36 di baseline) TIDAK diubah.

- [ ] **Step 6: Jalankan test scoped**

```bash
php artisan route:list --name=admin.pembayaran
php artisan test tests/Feature/Admin/VerifikasiPembayaranTest.php
```
Expected: `route:list` menunjukkan `Lembaga\Keuangan\PembayaranController`, nama route tidak berubah. Test PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): refactor Admin\PembayaranController jadi VerifikasiPembayaranAction, pindah ke Lembaga\Keuangan\"
```

---

## Task 11: Refactor `Admin\ManualPaymentController` — Namespace + Action + Test Guard Kritis Baru

**Files:**
- Create: `app/Http/Controllers/Lembaga/Keuangan/ManualPaymentController.php`
- Delete: `app/Http/Controllers/Admin/ManualPaymentController.php`
- Create: `app/Domains/Keuangan/Actions/Pembayaran/ApproveManualPaymentAction.php`
- Create: `app/Domains/Keuangan/Actions/Pembayaran/RejectManualPaymentAction.php`
- Move: `resources/views/admin/manual-payment/*` → `resources/views/portals/lembaga/keuangan/manual-payment/`
- Modify: `routes/admin/keuangan.php`
- Test: tambah test baru di `tests/Feature/Admin/ManualPaymentControllerTest.php` yang menyerang guard data-consistency kritis

**Interfaces:**
- Consumes: `App\Domains\Keuangan\Models\ManualPaymentRequest` (Task 3), `App\Services\Finance\PaymentAllocationService`/`App\Models\Wallet` (TIDAK PINDAH, tetap `app/Services/Finance`, `app/Models`), `App\Services\Finance\NotificationDispatcher` (TIDAK PINDAH).
- Produces: `App\Domains\Keuangan\Actions\Pembayaran\{ApproveManualPaymentAction,RejectManualPaymentAction}`.

**INI TASK PALING KRITIS DI SELURUH SP3.** `approve()` mengandung 3 guard yang WAJIB dipertahankan PERSIS tanpa penyederhanaan apa pun:
1. `siswaLembagaId()` — bypass `TenantScope` eksplisit (§7.2 spec).
2. **Guard data-consistency**: `hasTagihanTargets` vs `isTopup` mutually exclusive, `Log::critical()` + `abort(500)` kalau drift di KEDUA arah (§7.3 spec — "uang nyata terlibat, lebih baik gagal keras").
3. Pola 3-cabang exception handling topup (`AutoAllocationFailedException` = saldo aman cuma alokasi gagal; `Throwable` lain = saldo tidak ter-kredit) — SAMA PERSIS logic-nya dengan `PaymentAllocationService::topupSisaJikaAda()` dan webhook `payment()` (§8 spec) — JANGAN disatukan jadi helper bersama di task ini, itu di luar scope zero-behavior-change.

Baseline kode (226 baris, commit `ffe5400`) — baca ulang untuk konfirmasi sebelum edit.

- [ ] **Step 1: Buat `ApproveManualPaymentAction`**

`app/Domains/Keuangan/Actions/Pembayaran/ApproveManualPaymentAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\Pembayaran;

use App\Domains\Keuangan\Models\ManualPaymentRequest;
use App\Exceptions\AutoAllocationFailedException;
use App\Models\Wallet;
use App\Notifications\Finance\TransferManualDisetujuiNotification;
use App\Services\Finance\NotificationDispatcher;
use App\Services\Finance\PaymentAllocationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApproveManualPaymentAction
{
    public function __construct(
        private readonly PaymentAllocationService $allocationService,
        private readonly NotificationDispatcher $dispatcher,
    ) {
    }

    public function execute(ManualPaymentRequest $manualPaymentRequest, int $reviewedByUserId): void
    {
        if ($manualPaymentRequest->status !== 'PENDING') {
            abort(422, 'Permintaan ini sudah diproses sebelumnya.');
        }

        $pembayaran = $manualPaymentRequest->pembayaran;

        // Cross-validasi diskriminator SEBELUM dipercaya — topup_status dan keberadaan
        // pembayaran_tagihan wajib konsisten (mutually exclusive by construction, karena
        // createManualPayment() dan createManualTopupPayment() adalah 2 jalur creation
        // terpisah), tapi endpoint ini TIDAK BOLEH cuma percaya topup_status mentah-mentah —
        // kalau suatu saat data drift terjadi, approve() bisa diam-diam salah: skip topup
        // yang seharusnya jalan, ATAU skip alokasi tagihan sambil tetap menandai lunas.
        // Uang nyata terlibat — lebih baik gagal keras & jelas daripada salah diam-diam.
        $hasTagihanTargets = $pembayaran->pembayaranTagihan()->exists();
        $isTopup = $pembayaran->topup_status !== 'none';

        if ($hasTagihanTargets && $isTopup) {
            Log::critical("Manual payment guard mismatch: pembayaran id={$pembayaran->id} punya target tagihan (hasTagihanTargets=true) sekaligus ditandai topup (isTopup=true).");
            abort(500, 'Data pembayaran tidak konsisten: punya target tagihan sekaligus ditandai topup.');
        }
        if (! $hasTagihanTargets && ! $isTopup) {
            Log::critical("Manual payment guard mismatch: pembayaran id={$pembayaran->id} tidak ada target tagihan (hasTagihanTargets=false) maupun penanda topup (isTopup=false).");
            abort(500, 'Data pembayaran tidak konsisten: tidak ada target tagihan maupun penanda topup.');
        }

        DB::transaction(function () use ($manualPaymentRequest, $pembayaran, $reviewedByUserId) {
            $manualPaymentRequest->update([
                'status' => 'APPROVED',
                'reviewed_by' => $reviewedByUserId,
                'reviewed_at' => now(),
            ]);

            $pembayaran->update(['status' => 'lunas']);

            // Kasus bill-payment: alokasi terjadi DI DALAM transaction ini (pola
            // createCashPayment()), tidak ada Wallet::topup() yang terlibat sama sekali
            // untuk cabang ini.
            if ($pembayaran->topup_status === 'none') {
                $this->allocationService->allocate($pembayaran);
            }
        });

        // Kasus topup: Wallet::topup() dipanggil DI LUAR transaction, persis konvensi
        // webhook BRI — try/catch menandai topup_status completed/failed, TIDAK pernah
        // membungkus ulang topup() dalam transaction tambahan (ReconcilePayments sudah
        // menyediakan retry kalau langkah ini gagal, sama seperti jalur webhook).
        if ($isTopup) {
            $wallet = Wallet::where('siswa_id', $pembayaran->siswa_id)->first();

            if ($wallet !== null) {
                try {
                    $wallet->topup((float) $pembayaran->amount, $pembayaran, 'Topup via transfer manual disetujui');
                    $pembayaran->update(['topup_status' => 'completed']);
                } catch (AutoAllocationFailedException $e) {
                    // Saldo wallet SUDAH ter-kredit sukses (increment itu commit di dalam
                    // transaction internal Wallet::topup(), sebelum AutoAllocationEngine::run()
                    // dijalankan) -- hanya langkah auto-alokasi berikutnya yang gagal.
                    // topup_status wajib mencerminkan bahwa kreditnya sudah selesai, kalau
                    // tidak ReconcilePayments::retryFailedTopups() akan pilih ulang Pembayaran
                    // ini dan mengkredit wallet dua kali.
                    Log::error('Auto-alokasi gagal setelah topup manual payment berhasil di-kredit (saldo AMAN, hanya alokasi yang gagal): '.$e->getMessage());
                    $pembayaran->update(['topup_status' => 'completed']);
                } catch (\Throwable $e) {
                    Log::error('Gagal topup dari manual payment approval: '.$e->getMessage());
                    $pembayaran->update(['topup_status' => 'failed']);
                }
            } else {
                Log::error("Wallet tidak ditemukan saat approve manual topup payment: pembayaran id={$pembayaran->id}, siswa_id={$pembayaran->siswa_id}.");
                $pembayaran->update(['topup_status' => 'failed']);
            }
        }

        $siswa = $pembayaran->siswa;
        $kontakUtama = $siswa?->orangTua()->wherePivot('is_kontak_utama', true)->first();
        if ($kontakUtama !== null) {
            try {
                $this->dispatcher->send($kontakUtama, new TransferManualDisetujuiNotification());
            } catch (\Throwable $e) {
                Log::error('Gagal mengirim TransferManualDisetujuiNotification: '.$e->getMessage());
            }
        }
    }
}
```

- [ ] **Step 2: Buat `RejectManualPaymentAction`**

`app/Domains/Keuangan/Actions/Pembayaran/RejectManualPaymentAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\Pembayaran;

use App\Domains\Keuangan\Models\ManualPaymentRequest;
use App\Notifications\Finance\TransferManualDitolakNotification;
use App\Services\Finance\NotificationDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RejectManualPaymentAction
{
    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    public function execute(ManualPaymentRequest $manualPaymentRequest, int $reviewedByUserId, string $rejectionReason): void
    {
        if ($manualPaymentRequest->status !== 'PENDING') {
            abort(422, 'Permintaan ini sudah diproses sebelumnya.');
        }

        DB::transaction(function () use ($manualPaymentRequest, $reviewedByUserId, $rejectionReason) {
            $manualPaymentRequest->update([
                'status' => 'REJECTED',
                'reviewed_by' => $reviewedByUserId,
                'reviewed_at' => now(),
                'rejection_reason' => $rejectionReason,
            ]);

            // Reject tidak pernah memicu Wallet::topup() — baik kasus bill maupun topup,
            // ditolak berarti tidak ada dana yang masuk sama sekali, cukup ubah status.
            $manualPaymentRequest->pembayaran->update(['status' => 'ditolak']);
        });

        $siswa = $manualPaymentRequest->pembayaran->siswa;
        $kontakUtama = $siswa?->orangTua()->wherePivot('is_kontak_utama', true)->first();
        if ($kontakUtama !== null) {
            try {
                $this->dispatcher->send($kontakUtama, new TransferManualDitolakNotification($rejectionReason));
            } catch (\Throwable $e) {
                Log::error('Gagal mengirim TransferManualDitolakNotification: '.$e->getMessage());
            }
        }
    }
}
```

- [ ] **Step 3: Buat controller baru di `Lembaga\Keuangan\`**

`app/Http/Controllers/Lembaga/Keuangan/ManualPaymentController.php`:

```php
<?php

namespace App\Http\Controllers\Lembaga\Keuangan;

use App\Domains\Keuangan\Actions\Pembayaran\ApproveManualPaymentAction;
use App\Domains\Keuangan\Actions\Pembayaran\RejectManualPaymentAction;
use App\Domains\Keuangan\Models\ManualPaymentRequest;
use App\Http\Controllers\Controller;
use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManualPaymentController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('pembayaran.verifikasi');

        $lembagaId = $this->lembagaId($request);

        $query = ManualPaymentRequest::where('status', 'PENDING')
            ->whereHas('pembayaran', function ($q) use ($lembagaId) {
                $q->whereHas('siswa', fn ($q2) => $q2->where('lembaga_id', $lembagaId));
            })
            ->with(['pembayaran.siswa', 'pembayaran.pembayaranTagihan', 'requestedBy'])
            ->latest('transfer_date');

        if ($search = $request->input('search')) {
            $query->whereHas('pembayaran.siswa', fn ($q) => $q->where('nama_lengkap', 'like', '%'.$search.'%'));
        }

        if ($dari = $request->input('dari')) {
            $query->where('transfer_date', '>=', $dari);
        }

        if ($sampai = $request->input('sampai')) {
            $query->where('transfer_date', '<=', $sampai);
        }

        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;
        $paginated = $query->paginate($perPage)->withQueryString();

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('portals.lembaga.keuangan.manual-payment._daftar', [
                'requestList' => $paginated,
                'perPage' => $perPage,
            ]);
        }

        return view('portals.lembaga.keuangan.manual-payment.index', [
            'requestList' => $paginated,
            'perPage' => $perPage,
            'totalMenunggu' => ManualPaymentRequest::where('status', 'PENDING')
                ->whereHas('pembayaran.siswa', fn ($q) => $q->where('lembaga_id', $lembagaId))
                ->count(),
            'totalNominalMenunggu' => ManualPaymentRequest::where('status', 'PENDING')
                ->whereHas('pembayaran.siswa', fn ($q) => $q->where('lembaga_id', $lembagaId))
                ->sum('amount'),
        ]);
    }

    private function lembagaId(Request $request): ?int
    {
        return $request->user()->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $request->user()->lembaga_id;
    }

    // Siswa punya TenantScope global (BelongsToTenant) yang otomatis memfilter query
    // berdasarkan tenant user yang sedang login — artinya relasi ->siswa biasa akan
    // bernilai null (bukan siswa milik tenant lain) kalau diakses oleh admin dari
    // lembaga berbeda. Di sini kita justru BUTUH tahu lembaga_id sebenarnya (siswa
    // tenant manapun) supaya bisa dibandingkan secara eksplisit dengan lembagaId(),
    // makanya scope-nya sengaja di-bypass.
    private function siswaLembagaId(?int $siswaId): ?int
    {
        if ($siswaId === null) {
            return null;
        }

        return Siswa::withoutGlobalScope(TenantScope::class)->find($siswaId)?->lembaga_id;
    }

    public function approve(Request $request, ManualPaymentRequest $manualPaymentRequest, ApproveManualPaymentAction $action): RedirectResponse
    {
        $this->authorize('pembayaran.verifikasi');

        $siswaLembagaId = $this->siswaLembagaId($manualPaymentRequest->pembayaran->siswa_id);
        abort_unless($siswaLembagaId !== null && $siswaLembagaId === $this->lembagaId($request), 404);

        $action->execute($manualPaymentRequest, $request->user()->id);

        return redirect()->back()->with('status', 'Transfer manual berhasil disetujui.');
    }

    public function reject(Request $request, ManualPaymentRequest $manualPaymentRequest, RejectManualPaymentAction $action): RedirectResponse
    {
        $this->authorize('pembayaran.verifikasi');

        $siswaLembagaId = $this->siswaLembagaId($manualPaymentRequest->pembayaran->siswa_id);
        abort_unless($siswaLembagaId !== null && $siswaLembagaId === $this->lembagaId($request), 404);

        $request->validate(['rejection_reason' => ['required', 'string', 'max:255']]);

        $action->execute($manualPaymentRequest, $request->user()->id, $request->rejection_reason);

        return redirect()->back()->with('status', 'Transfer manual ditolak.');
    }
}
```

- [ ] **Step 4: Hapus controller lama**

```bash
git rm app/Http/Controllers/Admin/ManualPaymentController.php
```

- [ ] **Step 5: Pindahkan view**

```bash
mkdir -p resources/views/portals/lembaga/keuangan/manual-payment
git mv resources/views/admin/manual-payment/index.blade.php resources/views/portals/lembaga/keuangan/manual-payment/index.blade.php
git mv resources/views/admin/manual-payment/_daftar.blade.php resources/views/portals/lembaga/keuangan/manual-payment/_daftar.blade.php
```

Cek dulu `ls resources/views/admin/manual-payment/` untuk daftar file pasti sebelum `git mv` — kalau ada file lain di luar 2 ini, pindahkan juga dengan pola yang sama, dan cek `@include` internal untuk disesuaikan prefix path-nya.

- [ ] **Step 6: Update `routes/admin/keuangan.php`**

Ganti baris:
```php
use App\Http\Controllers\Admin\ManualPaymentController;
```
menjadi:
```php
use App\Http\Controllers\Lembaga\Keuangan\ManualPaymentController;
```

Baris route `manual-payment.*` (baris 38-40 di baseline) TIDAK diubah.

- [ ] **Step 7: Tambah test baru yang eksplisit menyerang guard data-consistency kritis**

Baca `tests/Feature/Admin/ManualPaymentControllerTest.php`, cocokkan pola helper user/siswa/pembayaran yang sudah ada, lalu tambahkan 2 test berikut (sesuaikan nama helper dengan yang benar-benar ada di file):

```php
it('aborts with 500 and does not change any status when a manual payment has BOTH tagihan targets and topup flag set (data drift)', function () {
    [$user, $lembaga, $siswa] = buatManualPaymentFixture(); // ganti dengan nama helper yang benar-benar ada

    $pembayaran = \App\Domains\Keuangan\Models\Pembayaran::factory()->create([
        'siswa_id' => $siswa->id,
        'topup_status' => 'pending',
    ]);
    \App\Domains\Keuangan\Models\PembayaranTagihan::factory()->create(['pembayaran_id' => $pembayaran->id]);
    $manualPaymentRequest = \App\Domains\Keuangan\Models\ManualPaymentRequest::factory()->create([
        'pembayaran_id' => $pembayaran->id,
        'status' => 'PENDING',
    ]);

    $response = $this->actingAs($user)->post(route('admin.manual-payment.approve', $manualPaymentRequest));

    $response->assertStatus(500);
    expect($manualPaymentRequest->fresh()->status)->toBe('PENDING');
    expect($pembayaran->fresh()->status)->not->toBe('lunas');
});

it('aborts with 500 and does not change any status when a manual payment has NEITHER tagihan targets NOR topup flag (data drift)', function () {
    [$user, $lembaga, $siswa] = buatManualPaymentFixture(); // ganti dengan nama helper yang benar-benar ada

    $pembayaran = \App\Domains\Keuangan\Models\Pembayaran::factory()->create([
        'siswa_id' => $siswa->id,
        'topup_status' => 'none',
    ]);
    $manualPaymentRequest = \App\Domains\Keuangan\Models\ManualPaymentRequest::factory()->create([
        'pembayaran_id' => $pembayaran->id,
        'status' => 'PENDING',
    ]);

    $response = $this->actingAs($user)->post(route('admin.manual-payment.approve', $manualPaymentRequest));

    $response->assertStatus(500);
    expect($manualPaymentRequest->fresh()->status)->toBe('PENDING');
    expect($pembayaran->fresh()->status)->not->toBe('lunas');
});
```

**Sebelum menulis persis seperti di atas**: baca dulu isi file test yang ada, cocokkan nama helper pembuat user+lembaga+siswa (`buatManualPaymentFixture` HANYA placeholder), dan pastikan `Pembayaran`/`PembayaranTagihan`/`ManualPaymentRequest` factory sudah punya `newFactory()` (dari Task 1/3).

- [ ] **Step 8: Jalankan test scoped**

```bash
php artisan route:list --name=admin.manual-payment
php artisan test tests/Feature/Admin/ManualPaymentControllerTest.php tests/Feature/Admin/ManualPaymentIndexControllerTest.php tests/Feature/Admin/ManualPaymentIndexAuthorizationTest.php tests/Feature/Admin/ManualPaymentNotificationTest.php
```
Expected: semua PASS termasuk 2 test baru.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): refactor Admin\ManualPaymentController jadi 2 Action, pindah ke Lembaga\Keuangan\, tambah 2 test guard data-consistency kritis"
```

---

## Task 12: Refactor `Admin\VirtualAccountController` — Namespace + Action + Test Guard Baru

**Files:**
- Create: `app/Http/Controllers/Lembaga/Keuangan/VirtualAccountController.php`
- Delete: `app/Http/Controllers/Admin/VirtualAccountController.php`
- Create: `app/Domains/Keuangan/Actions/VirtualAccount/GenerateVirtualAccountAction.php`
- Move: `resources/views/admin/virtual-account/*` → `resources/views/portals/lembaga/keuangan/virtual-account/`
- Modify: `routes/admin/keuangan.php`
- Test: tambah test baru di `tests/Feature/Admin/VirtualAccountControllerTest.php` yang menyerang guard `siswaLembagaId()`

**Interfaces:**
- Consumes: `App\Domains\Keuangan\Models\{BriVirtualAccount,BriInboundPaymentLog}` (Task 3), `App\Domains\Keuangan\Services\PaymentService` (Task 7).
- Produces: `App\Domains\Keuangan\Actions\VirtualAccount\GenerateVirtualAccountAction`.

**GUARD §7.4 WAJIB dipertahankan persis** — pola `siswaLembagaId()` di `riwayat()` IDENTIK dengan `ManualPaymentController` (Task 11). **JANGAN dikonsolidasi jadi 1 helper bersama** — itu perubahan struktural di luar zero-behavior-change, kalau ingin diusulkan catat sebagai temuan terpisah ke user, jangan dieksekusi diam-diam.

Baseline kode (219 baris, commit `ffe5400`) — baca ulang untuk konfirmasi sebelum edit.

- [ ] **Step 1: Buat `GenerateVirtualAccountAction`**

`app/Domains/Keuangan/Actions/VirtualAccount/GenerateVirtualAccountAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\VirtualAccount;

use App\Domains\Keuangan\Services\PaymentService;
use App\Models\Siswa;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class GenerateVirtualAccountAction
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    /**
     * @param  Collection<int, Siswa>  $siswaList
     * @return array{berhasil: int, gagalNama: array<int, string>}
     */
    public function execute(Collection $siswaList): array
    {
        $berhasil = 0;
        $gagalNama = [];

        foreach ($siswaList as $siswa) {
            try {
                $this->paymentService->getOrCreatePermanentVa($siswa);
                $berhasil++;
            } catch (\Throwable $e) {
                Log::error("Gagal generate VA untuk siswa id={$siswa->id}: ".$e->getMessage());
                $gagalNama[] = $siswa->nama_lengkap;
            }
        }

        return ['berhasil' => $berhasil, 'gagalNama' => $gagalNama];
    }
}
```

- [ ] **Step 2: Buat controller baru di `Lembaga\Keuangan\`**

`app/Http/Controllers/Lembaga/Keuangan/VirtualAccountController.php`:

```php
<?php

namespace App\Http\Controllers\Lembaga\Keuangan;

use App\Domains\Keuangan\Actions\VirtualAccount\GenerateVirtualAccountAction;
use App\Domains\Keuangan\Models\BriInboundPaymentLog;
use App\Domains\Keuangan\Models\BriVirtualAccount;
use App\Enums\StatusSiswa;
use App\Exports\VirtualAccountExport;
use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class VirtualAccountController extends Controller
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
            return view('portals.lembaga.keuangan.virtual-account._daftar', [
                'vaList' => $paginated,
                'perPage' => $perPage,
            ]);
        }

        $totalVa = BriVirtualAccount::where('va_type', 'WALLET_PERMANENT')
            ->whereHas('wallet.siswa', fn ($q) => $q->where('lembaga_id', $lembagaId))
            ->count();

        $totalSaldo = (float) BriVirtualAccount::where('va_type', 'WALLET_PERMANENT')
            ->whereHas('wallet.siswa', fn ($q) => $q->where('lembaga_id', $lembagaId))
            ->join('wallets', 'bri_virtual_accounts.wallet_id', '=', 'wallets.id')
            ->sum('wallets.balance');

        $totalBelumVa = Siswa::where('lembaga_id', $lembagaId)
            ->where('status', StatusSiswa::Aktif->value)
            ->whereDoesntHave('wallet.briVirtualAccounts', fn ($q) => $q->where('va_type', 'WALLET_PERMANENT'))
            ->count();

        $kelasList = Kelas::where('lembaga_id', $lembagaId)
            ->with('tahunAjaran')
            ->orderBy('nama')
            ->get();

        $kelasListGrouped = $kelasList->groupBy(fn ($k) => $k->tahunAjaran?->nama ?? 'Tanpa Tahun Ajaran');

        return view('portals.lembaga.keuangan.virtual-account.index', [
            'vaList' => $paginated,
            'perPage' => $perPage,
            'kelasList' => $kelasList,
            'kelasListGrouped' => $kelasListGrouped,
            'totalVa' => $totalVa,
            'totalSaldo' => $totalSaldo,
            'totalBelumVa' => $totalBelumVa,
        ]);
    }

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

        return view('portals.lembaga.keuangan.virtual-account._riwayat-list', ['logs' => $logs]);
    }

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

    public function generate(Request $request, GenerateVirtualAccountAction $action): RedirectResponse
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

        $hasil = $action->execute($siswaList);

        $status = "{$hasil['berhasil']} nomor VA berhasil dibuat.";
        if (count($hasil['gagalNama']) > 0) {
            $status .= ' Gagal untuk: '.implode(', ', $hasil['gagalNama']).'.';
        }

        return redirect()->route('admin.virtual-account.index')->with('status', $status);
    }

    public function export(Request $request)
    {
        $this->authorize('pembayaran.virtual-account');

        return Excel::download(new VirtualAccountExport($this->lembagaId($request)), 'virtual-account.xlsx');
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
    // sama persis app/Http/Controllers/Lembaga/Keuangan/ManualPaymentController.php.
    private function siswaLembagaId(?int $siswaId): ?int
    {
        if ($siswaId === null) {
            return null;
        }

        return Siswa::withoutGlobalScope(TenantScope::class)->find($siswaId)?->lembaga_id;
    }
}
```

- [ ] **Step 3: Hapus controller lama**

```bash
git rm app/Http/Controllers/Admin/VirtualAccountController.php
```

- [ ] **Step 4: Pindahkan view**

```bash
mkdir -p resources/views/portals/lembaga/keuangan/virtual-account
git mv resources/views/admin/virtual-account/index.blade.php resources/views/portals/lembaga/keuangan/virtual-account/index.blade.php
git mv resources/views/admin/virtual-account/_daftar.blade.php resources/views/portals/lembaga/keuangan/virtual-account/_daftar.blade.php
git mv resources/views/admin/virtual-account/_riwayat-list.blade.php resources/views/portals/lembaga/keuangan/virtual-account/_riwayat-list.blade.php
```

Cek `ls resources/views/admin/virtual-account/` dulu untuk daftar file pasti — riset awal menemukan kemungkinan ada `_generate-modal.blade.php`/`_topup-modal.blade.php` juga, kalau ada pindahkan dengan pola sama. Cek `@include` di SETIAP file yang dipindah (termasuk `index.blade.php` yang kemungkinan `@include` modal-modal itu), sesuaikan prefix path dari `admin.virtual-account.*` ke `portals.lembaga.keuangan.virtual-account.*`.

- [ ] **Step 5: Update `routes/admin/keuangan.php`**

Ganti baris:
```php
use App\Http\Controllers\Admin\VirtualAccountController;
```
menjadi:
```php
use App\Http\Controllers\Lembaga\Keuangan\VirtualAccountController;
```

Baris route `virtual-account.*` (baris 42-46 di baseline) TIDAK diubah.

- [ ] **Step 6: Tambah test baru yang eksplisit menyerang guard `siswaLembagaId()`**

Baca `tests/Feature/Admin/VirtualAccountControllerTest.php`, cocokkan pola helper yang ada, lalu tambahkan:

```php
it('returns 404 when riwayat targets a siswa belonging to a different lembaga', function () {
    [$userA] = buatUserVirtualAccount(); // ganti dengan nama helper yang benar-benar ada
    [, $lembagaB] = buatUserVirtualAccount();
    $siswaB = \App\Models\Siswa::factory()->create(['lembaga_id' => $lembagaB->id]);

    $response = $this->actingAs($userA)->get(route('admin.virtual-account.riwayat', $siswaB));

    $response->assertNotFound();
});
```

**Sebelum menulis persis seperti di atas**: baca dulu isi file test yang ada, cocokkan nama helper (`buatUserVirtualAccount` HANYA placeholder).

- [ ] **Step 7: Jalankan test scoped**

```bash
php artisan route:list --name=admin.virtual-account
php artisan test tests/Feature/Admin/VirtualAccountControllerTest.php tests/Feature/Admin/VirtualAccountAuthorizationTest.php
```
Expected: semua PASS termasuk test baru.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): refactor Admin\VirtualAccountController jadi GenerateVirtualAccountAction, pindah ke Lembaga\Keuangan\, tambah test guard siswaLembagaId"
```

---

## Task 13: Verifikasi Checkpoint — Sisi Admin Selesai Sebelum Lanjut ke Portal & Webhook

**Files:**
- Tidak ada file baru — task ini murni verifikasi gate.

- [ ] **Step 1: Verifikasi gabungan — tidak ada referensi namespace lama tersisa untuk sisi admin**

```bash
grep -rln "use App\\\\Http\\\\Controllers\\\\Admin\\\\PembayaranController;\|use App\\\\Http\\\\Controllers\\\\Admin\\\\ManualPaymentController;\|use App\\\\Http\\\\Controllers\\\\Admin\\\\VirtualAccountController;" --include="*.php" app database tests routes
```
Expected: kosong.

- [ ] **Step 2: Verifikasi file lama sudah tidak ada**

```bash
ls app/Http/Controllers/Admin/PembayaranController.php app/Http/Controllers/Admin/ManualPaymentController.php app/Http/Controllers/Admin/VirtualAccountController.php 2>&1
```
Expected: error "No such file or directory" untuk ketiganya.

- [ ] **Step 3: Jalankan test scoped luas sisi admin**

```bash
php artisan test tests/Feature/Admin --filter="Pembayaran|ManualPayment|VirtualAccount"
```
Expected: semua PASS.

- [ ] **Step 4: Kalau ada temuan yang tidak sesuai, STOP dan perbaiki sebelum lanjut Task 14.**

Tidak ada commit di task ini — murni gate verifikasi.

---

## Task 14: Refactor `Keuangan\CheckoutController` — Namespace + Action

**Files:**
- Create: `app/Http/Controllers/Portal/Keuangan/CheckoutController.php`
- Delete: `app/Http/Controllers/Keuangan/CheckoutController.php`
- Create: `app/Domains/Keuangan/Actions/Pembayaran/CreateQrisPaymentAction.php`
- Create: `app/Domains/Keuangan/Actions/Pembayaran/CreateWalletPaymentAction.php`
- Create: `app/Domains/Keuangan/Actions/Pembayaran/CreateManualTransferPaymentAction.php`
- Move: `resources/views/keuangan/checkout/*` → `resources/views/portals/portal/keuangan/checkout/`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `App\Domains\Keuangan\Models\{Pembayaran,Tagihan}` (Task 1, SP2), `App\Domains\Keuangan\Services\PaymentService` (Task 7), `App\Domains\Keuangan\Concerns\AuthorizesPembayaran` (Task 9).
- Produces: 3 Action baru — dipakai controller baru.

**GUARD §7.5 WAJIB dipertahankan** — `authorizePembayaran()` di SETIAP titik akses `Pembayaran` (baris `menungguVerifikasi`/`sukses`/`show`/`status`).

Baseline kode (269 baris, commit `ffe5400`) — baca ulang untuk konfirmasi sebelum edit. Method `create()`/`va()`/`vaInfo()` TETAP inline di controller (read-only/redirect, tidak ada mutasi state selain baca — konsisten pola SP1/SP2: hanya method mutasi yang diekstrak ke Action).

- [ ] **Step 1: Buat `CreateQrisPaymentAction`**

`app/Domains/Keuangan/Actions/Pembayaran/CreateQrisPaymentAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\Pembayaran;

use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Services\PaymentService;
use App\Exceptions\PaymentException;
use App\Models\Siswa;
use Illuminate\Support\Collection;

class CreateQrisPaymentAction
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    /**
     * @throws PaymentException
     */
    public function execute(Siswa $siswa, Collection $tagihans, float $topupAmount): Pembayaran
    {
        return $topupAmount > 0
            ? $this->paymentService->createQrisPaymentWithTopup($siswa, $tagihans, $topupAmount)
            : $this->paymentService->createQrisPayment($siswa, $tagihans);
    }
}
```

- [ ] **Step 2: Buat `CreateWalletPaymentAction`**

`app/Domains/Keuangan/Actions/Pembayaran/CreateWalletPaymentAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\Pembayaran;

use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Services\PaymentService;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\PaymentException;
use App\Models\Siswa;
use Illuminate\Support\Collection;

class CreateWalletPaymentAction
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    /**
     * @throws InsufficientBalanceException|PaymentException
     */
    public function execute(Siswa $siswa, Collection $tagihans): Pembayaran
    {
        return $this->paymentService->createWalletPayment($siswa, $tagihans);
    }
}
```

- [ ] **Step 3: Buat `CreateManualTransferPaymentAction`**

`app/Domains/Keuangan/Actions/Pembayaran/CreateManualTransferPaymentAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\Pembayaran;

use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Services\PaymentService;
use App\Exceptions\PaymentException;
use App\Models\Siswa;
use Illuminate\Support\Collection;

class CreateManualTransferPaymentAction
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    /**
     * @param  array{amount: float, transfer_proof_path: string, bank_origin: ?string, transfer_date: string, requested_by: int}  $data
     *
     * @throws PaymentException
     */
    public function execute(Siswa $siswa, Collection $tagihans, array $data): Pembayaran
    {
        return $this->paymentService->createManualPayment($siswa, $tagihans, $data);
    }
}
```

- [ ] **Step 4: Buat controller baru di `Portal\Keuangan\`**

`app/Http/Controllers/Portal/Keuangan/CheckoutController.php`:

```php
<?php
// app/Http/Controllers/Portal/Keuangan/CheckoutController.php

namespace App\Http\Controllers\Portal\Keuangan;

use App\Domains\Keuangan\Actions\Pembayaran\CreateManualTransferPaymentAction;
use App\Domains\Keuangan\Actions\Pembayaran\CreateQrisPaymentAction;
use App\Domains\Keuangan\Actions\Pembayaran\CreateWalletPaymentAction;
use App\Domains\Keuangan\Concerns\AuthorizesPembayaran;
use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Models\Tagihan;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\PaymentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Keuangan\StoreManualTransferRequest;
use App\Models\Scopes\TenantScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    use AuthorizesPembayaran;

    public function __construct(private readonly \App\Domains\Keuangan\Services\PaymentService $paymentService)
    {
    }

    public function create(Request $request): View
    {
        $activeSiswa = $request->attributes->get('activeSiswa');

        if ($activeSiswa === null) {
            return view('keuangan.tanpa-anak');
        }

        $tagihanIds = (array) $request->query('tagihan_ids', []);

        $tagihans = Tagihan::where('tagihable_type', get_class($activeSiswa))
            ->where('tagihable_id', $activeSiswa->id)
            ->whereIn('status', ['belum_bayar', 'sebagian'])
            ->whereIn('id', $tagihanIds)
            ->with(['jenisTagihan' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
            ->get();

        $totalTagihan = $tagihans->reduce(
            fn (float $carry, Tagihan $tagihan) => $carry + ($tagihan->net_amount - $tagihan->paid_amount),
            0.0
        );

        return view('portals.portal.keuangan.checkout.create', [
            'activeSiswa' => $activeSiswa,
            'tagihans' => $tagihans,
            'totalTagihan' => $totalTagihan,
            'wallet' => $activeSiswa->wallet,
        ]);
    }

    public function va(Request $request)
    {
        $requestedIds = (array) $request->input('tagihan_ids', []);

        return redirect()->route('keuangan.checkout.va-info', ['tagihan_ids' => $requestedIds]);
    }

    public function vaInfo(Request $request): View|RedirectResponse
    {
        $activeSiswa = $request->attributes->get('activeSiswa');

        if ($activeSiswa === null) {
            return redirect()->route('keuangan.tagihan.index');
        }

        $requestedIds = (array) $request->query('tagihan_ids', []);
        $tagihans = $this->resolveSelectedTagihan($activeSiswa, $requestedIds);

        $totalTagihan = $tagihans->reduce(
            fn (float $carry, Tagihan $tagihan) => $carry + ($tagihan->net_amount - $tagihan->paid_amount),
            0.0
        );

        try {
            $va = $this->paymentService->getOrCreatePermanentVa($activeSiswa);
        } catch (PaymentException $e) {
            Log::error('Gagal membuat VA BRI Permanen: '.$e->getMessage());
            return back()->withErrors(['tagihan_ids' => 'Gagal mendapatkan VA, silakan coba lagi.']);
        }

        return view('portals.portal.keuangan.checkout.va-info', [
            'va' => $va,
            'totalTagihan' => $totalTagihan,
            'tagihans' => $tagihans,
        ]);
    }

    public function qris(Request $request, CreateQrisPaymentAction $action)
    {
        $activeSiswa = $request->attributes->get('activeSiswa');
        $requestedIds = (array) $request->input('tagihan_ids', []);
        $topupAmount = (float) $request->input('topup_amount', 0);
        $tagihans = $this->resolveSelectedTagihan($activeSiswa, $requestedIds);

        if ($tagihans->isEmpty()) {
            return back()->withErrors(['tagihan_ids' => 'Tidak ada tagihan valid yang dipilih.']);
        }

        if ($tagihans->count() !== count(array_unique($requestedIds))) {
            return redirect()->route('keuangan.tagihan.index')
                ->withErrors(['tagihan_ids' => 'Sebagian tagihan yang dipilih sudah lunas, silakan cek kembali.']);
        }

        if ($topupAmount <= 0) {
            $existing = $this->findPendingPembayaranFor('qris', $tagihans);
            if ($existing !== null) {
                return redirect()->route('keuangan.checkout.show', $existing);
            }
        }

        try {
            $pembayaran = $action->execute($activeSiswa, $tagihans, $topupAmount);
        } catch (PaymentException $e) {
            Log::error('Gagal membuat QRIS: '.$e->getMessage());
            return back()->withErrors(['tagihan_ids' => 'Gagal membuat pembayaran, silakan coba lagi.']);
        }

        return redirect()->route('keuangan.checkout.show', $pembayaran);
    }

    public function wallet(Request $request, CreateWalletPaymentAction $action)
    {
        $activeSiswa = $request->attributes->get('activeSiswa');
        $requestedIds = (array) $request->input('tagihan_ids', []);
        $tagihans = $this->resolveSelectedTagihan($activeSiswa, $requestedIds);

        if ($tagihans->isEmpty()) {
            return back()->withErrors(['tagihan_ids' => 'Tidak ada tagihan valid yang dipilih.']);
        }

        if ($tagihans->count() !== count(array_unique($requestedIds))) {
            return redirect()->route('keuangan.tagihan.index')
                ->withErrors(['tagihan_ids' => 'Sebagian tagihan yang dipilih sudah lunas, silakan cek kembali.']);
        }

        try {
            $pembayaran = $action->execute($activeSiswa, $tagihans);
        } catch (InsufficientBalanceException|PaymentException $e) {
            return back()->withErrors(['tagihan_ids' => 'Saldo wallet tidak mencukupi untuk tagihan terpilih.']);
        }

        return redirect()->route('keuangan.checkout.sukses', $pembayaran);
    }

    public function transfer(StoreManualTransferRequest $request, CreateManualTransferPaymentAction $action)
    {
        $activeSiswa = $request->attributes->get('activeSiswa');
        $requestedIds = (array) $request->input('tagihan_ids', []);
        $tagihans = $this->resolveSelectedTagihan($activeSiswa, $requestedIds);

        if ($tagihans->isEmpty()) {
            return back()->withErrors(['tagihan_ids' => 'Tidak ada tagihan valid yang dipilih.']);
        }

        if ($tagihans->count() !== count(array_unique($requestedIds))) {
            return redirect()->route('keuangan.tagihan.index')
                ->withErrors(['tagihan_ids' => 'Sebagian tagihan yang dipilih sudah lunas, silakan cek kembali.']);
        }

        $totalTagihan = $tagihans->reduce(
            fn (float $carry, Tagihan $tagihan) => $carry + ($tagihan->net_amount - $tagihan->paid_amount),
            0.0
        );

        try {
            $path = $request->file('transfer_proof')->store('bukti-transfer', 'public');

            $pembayaran = $action->execute($activeSiswa, $tagihans, [
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
        $this->authorizePembayaran($pembayaran);

        return view('portals.portal.keuangan.checkout.menunggu-verifikasi', ['pembayaran' => $pembayaran->load('manualRequest')]);
    }

    public function sukses(Request $request, Pembayaran $pembayaran)
    {
        $this->authorizePembayaran($pembayaran);

        $pembayaran->load(['pembayaranTagihan.tagihan.jenisTagihan' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)]);

        return view('portals.portal.keuangan.checkout.sukses', ['pembayaran' => $pembayaran]);
    }

    public function show(Request $request, Pembayaran $pembayaran)
    {
        $this->authorizePembayaran($pembayaran);

        abort_unless($pembayaran->metode === 'qris', 404);

        $pembayaran->load(['briQrisPayment', 'pembayaranTagihan']);

        $qrCodeDataUri = null;
        if ($pembayaran->briQrisPayment) {
            $svg = \SimpleSoftwareIO\QrCode\Facades\QrCode::size(180)->generate($pembayaran->briQrisPayment->qr_code);
            $qrCodeDataUri = 'data:image/svg+xml;base64,'.base64_encode($svg);
        }

        return view('portals.portal.keuangan.checkout.show', [
            'pembayaran' => $pembayaran,
            'qrCodeDataUri' => $qrCodeDataUri,
        ]);
    }

    public function status(Request $request, Pembayaran $pembayaran)
    {
        $this->authorizePembayaran($pembayaran);

        return response()->json(['status' => $pembayaran->status]);
    }

    private function resolveSelectedTagihan($activeSiswa, array $tagihanIds)
    {
        if ($activeSiswa === null) {
            return collect();
        }

        return Tagihan::where('tagihable_type', get_class($activeSiswa))
            ->where('tagihable_id', $activeSiswa->id)
            ->whereIn('status', ['belum_bayar', 'sebagian'])
            ->whereIn('id', $tagihanIds)
            ->get();
    }

    private function findPendingPembayaranFor(string $metode, $tagihans): ?Pembayaran
    {
        $relation = $metode === 'qris' ? 'briQrisPayment' : 'briVirtualAccount';
        $requestedIds = $tagihans->pluck('id')->sort()->values()->all();

        $candidates = Pembayaran::where('metode', $metode)
            ->where('status', 'menunggu_pembayaran')
            ->where('topup_status', 'none')
            ->whereHas('pembayaranTagihan', fn ($q) => $q->whereIn('tagihan_id', $requestedIds))
            ->whereHas($relation, fn ($q) => $q->where('expired_at', '>', now()))
            ->with('pembayaranTagihan')
            ->get();

        return $candidates->first(function (Pembayaran $candidate) use ($requestedIds) {
            $candidateIds = $candidate->pembayaranTagihan->pluck('tagihan_id')->sort()->values()->all();

            return $candidateIds === $requestedIds;
        });
    }
}
```

- [ ] **Step 5: Hapus controller lama**

```bash
git rm app/Http/Controllers/Keuangan/CheckoutController.php
```

- [ ] **Step 6: Pindahkan view**

```bash
mkdir -p resources/views/portals/portal/keuangan/checkout
git mv resources/views/keuangan/checkout/create.blade.php resources/views/portals/portal/keuangan/checkout/create.blade.php
git mv resources/views/keuangan/checkout/va-info.blade.php resources/views/portals/portal/keuangan/checkout/va-info.blade.php
git mv resources/views/keuangan/checkout/menunggu-verifikasi.blade.php resources/views/portals/portal/keuangan/checkout/menunggu-verifikasi.blade.php
git mv resources/views/keuangan/checkout/sukses.blade.php resources/views/portals/portal/keuangan/checkout/sukses.blade.php
git mv resources/views/keuangan/checkout/show.blade.php resources/views/portals/portal/keuangan/checkout/show.blade.php
```

Cek `ls resources/views/keuangan/checkout/` dulu untuk daftar file pasti, sesuaikan kalau berbeda dari daftar di atas. Cek `@include` di tiap file, sesuaikan prefix `keuangan.checkout.*` → `portals.portal.keuangan.checkout.*`.

- [ ] **Step 7: Update `routes/web.php`**

Baca file, di dalam grup `Route::middleware([...])->prefix('keuangan')->name('keuangan.')->group(function () { ... })`, ganti SETIAP `\App\Http\Controllers\Keuangan\CheckoutController::class` menjadi `\App\Http\Controllers\Portal\Keuangan\CheckoutController::class` (8 baris: `checkout.create`, `checkout.va`, `checkout.qris`, `checkout.wallet`, `checkout.va-info`, `checkout.transfer`, `checkout.sukses`, `checkout.menunggu-verifikasi`, `checkout.show`, `checkout.status` — total 10 route, cek jumlah pasti dengan `grep -c "Keuangan\\\\CheckoutController" routes/web.php` sebelum dan sesudah edit, harus 0 setelahnya). Baris `dashboard`, `tagihan.index`, `riwayat.*` di grup yang sama JANGAN diubah (bukan bagian task ini).

- [ ] **Step 8: Jalankan test scoped**

```bash
php artisan route:list --name=keuangan.checkout
php artisan test tests/Feature/Keuangan/CheckoutControllerWalletTest.php tests/Feature/Keuangan/CheckoutControllerVaQrisTest.php tests/Feature/Keuangan/CheckoutControllerTransferTest.php tests/Feature/Keuangan/CheckoutControllerBundledTopupTest.php tests/Feature/Keuangan/CheckoutAuthorizationTest.php tests/Feature/Keuangan/CheckoutControllerCreateTest.php
```
Expected: semua PASS, `route:list` menunjukkan `Portal\Keuangan\CheckoutController`.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): refactor Keuangan\CheckoutController jadi 3 Action, pindah ke Portal\Keuangan\"
```

---

## Task 15: Refactor `Keuangan\RiwayatController` — Namespace (Tanpa Action, Murni Read-Only)

**Files:**
- Create: `app/Http/Controllers/Portal/Keuangan/RiwayatController.php`
- Delete: `app/Http/Controllers/Keuangan/RiwayatController.php`
- Move: `resources/views/keuangan/riwayat/*` → `resources/views/portals/portal/keuangan/riwayat/`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `App\Domains\Keuangan\Models\Pembayaran` (Task 1), `App\Domains\Keuangan\Concerns\AuthorizesPembayaran` (Task 9).

Controller ini 100% read-only (index = query+filter, kwitansi = generate PDF) — TIDAK ADA Action baru, konsisten pola SP1/SP2 (method read-only tetap inline).

Baseline kode (93 baris, commit `ffe5400`) — baca ulang untuk konfirmasi.

- [ ] **Step 1: Buat controller baru di `Portal\Keuangan\`**

`app/Http/Controllers/Portal/Keuangan/RiwayatController.php`:

```php
<?php
// app/Http/Controllers/Portal/Keuangan/RiwayatController.php

namespace App\Http\Controllers\Portal\Keuangan;

use App\Domains\Keuangan\Concerns\AuthorizesPembayaran;
use App\Domains\Keuangan\Models\Pembayaran;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RiwayatController extends Controller
{
    use AuthorizesPembayaran;

    public function index(Request $request): View
    {
        $activeSiswa = $request->attributes->get('activeSiswa');

        if ($activeSiswa === null) {
            return view('keuangan.tanpa-anak');
        }

        $validated = $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date'],
            'metode' => ['nullable', 'string'],
        ]);

        $dari = $validated['dari'] ?? null;
        $sampai = $validated['sampai'] ?? null;
        $metode = $validated['metode'] ?? null;

        $dateRangeValid = ! ($dari && $sampai && $dari > $sampai);

        $pembayarans = Pembayaran::where('siswa_id', $activeSiswa->id)
            ->where(fn ($q) => $q->where('channel_reference', '!=', 'WALLET_PERMANENT')->orWhereNull('channel_reference'))
            ->when($dateRangeValid && $dari, fn ($q) => $q->where('created_at', '>=', $dari.' 00:00:00'))
            ->when($dateRangeValid && $sampai, fn ($q) => $q->where('created_at', '<=', $sampai.' 23:59:59'))
            ->when($metode, fn ($q) => $q->where('metode', $metode))
            ->with(['pembayaranTagihan.tagihan.jenisTagihan' => fn ($q) => $q->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)])
            ->orderByDesc('created_at')
            ->paginate(15)
            ->appends($request->query());

        $statsQuery = fn () => Pembayaran::where('siswa_id', $activeSiswa->id)
            ->where(fn ($q) => $q->where('channel_reference', '!=', 'WALLET_PERMANENT')->orWhereNull('channel_reference'))
            ->when($dateRangeValid && $dari, fn ($q) => $q->where('created_at', '>=', $dari.' 00:00:00'))
            ->when($dateRangeValid && $sampai, fn ($q) => $q->where('created_at', '<=', $sampai.' 23:59:59'))
            ->when($metode, fn ($q) => $q->where('metode', $metode));

        $totalLunasNominal = $statsQuery()->where('status', 'lunas')->sum('amount');
        $totalMenungguCount = $statsQuery()->whereIn('status', ['menunggu_pembayaran', 'menunggu_verifikasi'])->count();
        $totalTransaksiCount = $statsQuery()->count();

        return view('portals.portal.keuangan.riwayat.index', [
            'activeSiswa' => $activeSiswa,
            'pembayarans' => $pembayarans,
            'dari' => $dari,
            'sampai' => $sampai,
            'metode' => $metode,
            'filterActive' => $metode || ($dateRangeValid && ($dari || $sampai)),
            'totalLunasNominal' => $totalLunasNominal,
            'totalMenungguCount' => $totalMenungguCount,
            'totalTransaksiCount' => $totalTransaksiCount,
        ]);
    }

    public function kwitansi(Request $request, Pembayaran $pembayaran)
    {
        $this->authorizePembayaran($pembayaran);

        abort_unless($pembayaran->status === 'lunas', 404);

        $pembayaran->load([
            'pembayaranTagihan.tagihan.jenisTagihan' => fn ($q) => $q->withoutGlobalScope(\App\Models\Scopes\TenantScope::class),
            'siswa' => fn ($q) => $q->withoutGlobalScope(\App\Models\Scopes\TenantScope::class),
            'siswa.lembaga.yayasan',
            'siswa.kelas' => fn ($q) => $q->withoutGlobalScope(\App\Models\Scopes\TenantScope::class),
        ]);

        $pdf = Pdf::loadView('pdf.kwitansi', [
            'pembayaran' => $pembayaran,
            'siswa' => $pembayaran->siswa,
            'lembaga' => $pembayaran->siswa->lembaga,
            'yayasan' => $pembayaran->siswa->lembaga->yayasan,
        ]);

        return $pdf->stream('kwitansi-'.$pembayaran->id.'.pdf');
    }
}
```

- [ ] **Step 2: Hapus controller lama**

```bash
git rm app/Http/Controllers/Keuangan/RiwayatController.php
```

- [ ] **Step 3: Pindahkan view**

```bash
mkdir -p resources/views/portals/portal/keuangan/riwayat
git mv resources/views/keuangan/riwayat/index.blade.php resources/views/portals/portal/keuangan/riwayat/index.blade.php
```

Cek `ls resources/views/keuangan/riwayat/` dulu — kalau ada file lain, pindahkan juga. Cek `@include` internal, sesuaikan prefix. `resources/views/pdf/kwitansi.blade.php` TIDAK dipindah (§3.5 spec) — biarkan di lokasi lama, TIDAK perlu disentuh (tidak ada referensi model lama di dalamnya yang perlu cross-scope-touch, tapi baca dulu isinya untuk konfirmasi sebelum melewatinya).

- [ ] **Step 4: Update `routes/web.php`**

Ganti 2 baris:
```php
Route::get('/riwayat', [\App\Http\Controllers\Keuangan\RiwayatController::class, 'index'])->name('riwayat.index');
Route::get('/riwayat/{pembayaran}/kwitansi', [\App\Http\Controllers\Keuangan\RiwayatController::class, 'kwitansi'])->name('riwayat.kwitansi');
```
menjadi:
```php
Route::get('/riwayat', [\App\Http\Controllers\Portal\Keuangan\RiwayatController::class, 'index'])->name('riwayat.index');
Route::get('/riwayat/{pembayaran}/kwitansi', [\App\Http\Controllers\Portal\Keuangan\RiwayatController::class, 'kwitansi'])->name('riwayat.kwitansi');
```

- [ ] **Step 5: Jalankan test scoped**

```bash
php artisan route:list --name=keuangan.riwayat
php artisan test tests/Feature/Keuangan/RiwayatControllerIndexTest.php tests/Feature/Keuangan/RiwayatAuthorizationTest.php tests/Feature/Keuangan/KwitansiControllerTest.php
```
Expected: semua PASS, `route:list` menunjukkan `Portal\Keuangan\RiwayatController`.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah Keuangan\RiwayatController ke Portal\Keuangan\ (murni namespace, read-only)"
```

---

## Task 16: Refactor Webhook `Api\BriVaInboundController` — Full Action Extraction, Response JSON Byte-Identical

**Files:**
- Create: `app/Http/Controllers/Api/Keuangan/BriVaInboundController.php`
- Delete: `app/Http/Controllers/Api/BriVaInboundController.php`
- Create: `app/Domains/Keuangan/Actions/Webhook/IssueBriAccessTokenAction.php`
- Create: `app/Domains/Keuangan/Actions/Webhook/InquiryBriVirtualAccountAction.php`
- Create: `app/Domains/Keuangan/Actions/Webhook/ProcessBriVaPaymentAction.php`
- Create: `app/Domains/Keuangan/DataTransferObjects/BriVaInquiryResult.php`
- Create: `app/Domains/Keuangan/DataTransferObjects/BriVaPaymentOutcome.php`
- Modify: `routes/web.php` (baris 7-9, `use` implisit via FQCN inline — cek dan update)
- Test: tambah test yang menyerang SETIAP dari 11 kombinasi kondisi di §4 spec kalau belum ada test yang menyerangnya

**Interfaces:**
- Consumes: `App\Domains\Keuangan\Contracts\BriInboundAuthenticatorInterface` (Task 5), `App\Domains\Keuangan\Models\{BriVirtualAccount,BriInboundPaymentLog,Pembayaran,Tagihan}` (Task 1-3, SP2), `App\Models\{Wallet,Siswa}` (TIDAK PINDAH).

**INI FILE PALING KRITIS DI SELURUH MIGRASI KEUANGAN.** Baca §4 spec (11 kombinasi kondisi response, tabel lengkap) SEBELUM mengerjakan task ini. **Response JSON WAJIB byte-identical** — field, urutan pengecekan, dan format `number_format($x, 2, '.', '')` (string desimal, BUKAN angka) HARUS sama persis dengan kode asli. Urutan pengecekan di `payment()` (idempotent-replay SEBELUM validasi amount, baru VA lookup, baru insert log dengan disambiguasi genuine-duplicate) WAJIB dipertahankan PERSIS (§7.6 spec).

Baseline kode (257 baris, commit `ffe5400`, sudah dikutip lengkap di plan ini) — baca ulang `app/Http/Controllers/Api/BriVaInboundController.php` untuk konfirmasi PERSIS sebelum edit. Kalau isinya beda dari yang dikutip di sini, STOP, laporkan ke user.

- [ ] **Step 1: Buat DTO hasil `BriVaInquiryResult`**

`app/Domains/Keuangan/DataTransferObjects/BriVaInquiryResult.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\DataTransferObjects;

final readonly class BriVaInquiryResult
{
    public function __construct(
        public string $virtualAccountName,
        public float $saranNominal,
    ) {}
}
```

- [ ] **Step 2: Buat DTO hasil `BriVaPaymentOutcome`**

`app/Domains/Keuangan/DataTransferObjects/BriVaPaymentOutcome.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\DataTransferObjects;

final readonly class BriVaPaymentOutcome
{
    private function __construct(
        public string $status,
        public ?float $amount = null,
        public ?string $virtualAccountName = null,
    ) {}

    public static function invalidAmount(): self
    {
        return new self('invalid_amount');
    }

    public static function vaNotFound(): self
    {
        return new self('va_not_found');
    }

    public static function logWriteFailed(): self
    {
        return new self('log_write_failed');
    }

    public static function success(float $amount, ?string $virtualAccountName): self
    {
        return new self('success', $amount, $virtualAccountName);
    }
}
```

- [ ] **Step 3: Buat `IssueBriAccessTokenAction`**

`app/Domains/Keuangan/Actions/Webhook/IssueBriAccessTokenAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\Webhook;

use App\Domains\Keuangan\Contracts\BriInboundAuthenticatorInterface;

class IssueBriAccessTokenAction
{
    public function __construct(private readonly BriInboundAuthenticatorInterface $authenticator)
    {
    }

    public function execute(string $clientId, string $clientSecret): ?string
    {
        return $this->authenticator->issueToken($clientId, $clientSecret);
    }
}
```

- [ ] **Step 4: Buat `InquiryBriVirtualAccountAction`**

`app/Domains/Keuangan/Actions/Webhook/InquiryBriVirtualAccountAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\Webhook;

use App\Domains\Keuangan\DataTransferObjects\BriVaInquiryResult;
use App\Domains\Keuangan\Models\BriVirtualAccount;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Siswa;

class InquiryBriVirtualAccountAction
{
    public function execute(string $vaNumber): ?BriVaInquiryResult
    {
        $va = BriVirtualAccount::where('va_number', $vaNumber)->with('wallet.siswa')->first();

        if (!$va || !$va->wallet || !$va->wallet->siswa) {
            return null;
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

        return new BriVaInquiryResult($siswa->nama_lengkap, $saranNominal);
    }
}
```

- [ ] **Step 5: Buat `ProcessBriVaPaymentAction`**

`app/Domains/Keuangan/Actions/Webhook/ProcessBriVaPaymentAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\Webhook;

use App\Domains\Keuangan\DataTransferObjects\BriVaPaymentOutcome;
use App\Domains\Keuangan\Models\BriInboundPaymentLog;
use App\Domains\Keuangan\Models\BriVirtualAccount;
use App\Domains\Keuangan\Models\Pembayaran;
use App\Exceptions\AutoAllocationFailedException;
use Illuminate\Support\Facades\Log;

class ProcessBriVaPaymentAction
{
    public function execute(string $vaNumber, string $paymentRequestId, float $amount): BriVaPaymentOutcome
    {
        $existingLog = BriInboundPaymentLog::where('payment_request_id', $paymentRequestId)->first();
        if ($existingLog) {
            return BriVaPaymentOutcome::success(
                (float) $existingLog->amount,
                $this->resolveVirtualAccountName($vaNumber)
            );
        }

        if ($amount <= 0) {
            return BriVaPaymentOutcome::invalidAmount();
        }

        $va = BriVirtualAccount::where('va_number', $vaNumber)->with('wallet.siswa')->first();

        if (!$va || !$va->wallet) {
            return BriVaPaymentOutcome::vaNotFound();
        }

        $wallet = $va->wallet;
        $virtualAccountName = $wallet->siswa?->nama_lengkap;

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
            // Only treat this as a safe idempotent replay if a log row for this
            // paymentRequestId genuinely exists now (a concurrent duplicate request
            // won the race and inserted it first). If it does NOT exist, the insert
            // failed for some other reason (connection issue, unrelated constraint
            // violation, etc.) -- that is a real, unrecovered failure: the Pembayaran
            // we just created has no ledger backing and no wallet credit, so delete
            // the orphan, log it for investigation, and tell BRI to retry.
            $isGenuineDuplicate = BriInboundPaymentLog::where('payment_request_id', $paymentRequestId)->exists();

            $pembayaran->delete();

            if ($isGenuineDuplicate) {
                return BriVaPaymentOutcome::success($amount, $virtualAccountName);
            }

            Log::error("Gagal menulis BriInboundPaymentLog (bukan duplikat) untuk VA {$vaNumber}: " . $e->getMessage(), [
                'payment_request_id' => $paymentRequestId,
                'va_number' => $vaNumber,
                'amount' => $amount,
                'exception' => $e->getMessage(),
            ]);

            return BriVaPaymentOutcome::logWriteFailed();
        }

        try {
            $wallet->topup($amount, $pembayaran, 'Top-up via VA BRI');
            $pembayaran->update(['topup_status' => 'completed']);
        } catch (AutoAllocationFailedException $e) {
            // The wallet balance itself WAS already credited successfully (that
            // increment committed inside Wallet::topup()'s own DB transaction,
            // before AutoAllocationEngine::run() ever ran) -- only the subsequent
            // auto-allocation step failed. topup_status must reflect that the
            // credit is done, otherwise ReconcilePayments::retryFailedTopups()
            // would re-select this Pembayaran and double-credit the wallet.
            Log::error("Auto-alokasi gagal setelah topup VA {$vaNumber} berhasil di-kredit (saldo AMAN, hanya alokasi yang gagal): " . $e->getMessage(), [
                'payment_request_id' => $paymentRequestId,
                'va_number' => $vaNumber,
                'amount' => $amount,
                'exception' => $e->getMessage(),
            ]);
            $pembayaran->update(['topup_status' => 'completed']);
        } catch (\Throwable $e) {
            // Genuine topup failure -- the balance was NOT credited (the internal
            // transaction rolled back), so this really does need a retry.
            Log::error("Gagal proses topup VA {$vaNumber}: " . $e->getMessage(), [
                'payment_request_id' => $paymentRequestId,
                'va_number' => $vaNumber,
                'amount' => $amount,
                'exception' => $e->getMessage(),
            ]);
            $pembayaran->update(['topup_status' => 'failed']);
        }

        return BriVaPaymentOutcome::success($amount, $virtualAccountName);
    }

    protected function resolveVirtualAccountName(string $vaNumber): ?string
    {
        $va = BriVirtualAccount::where('va_number', $vaNumber)->with('wallet.siswa')->first();

        return $va?->wallet?->siswa?->nama_lengkap;
    }
}
```

**Verifikasi kritis sebelum lanjut**: baca ulang kode Action ini vs baseline `payment()` method line-by-line — urutan HARUS: idempotent-check → amount-check → VA-lookup → create Pembayaran → insert log (try/catch genuine-duplicate) → wallet topup (3-cabang exception). TIDAK ADA langkah yang boleh tertukar urutan atau hilang.

- [ ] **Step 6: Buat controller baru di `Api\Keuangan\`**

`app/Http/Controllers/Api/Keuangan/BriVaInboundController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Keuangan;

use App\Domains\Keuangan\Actions\Webhook\InquiryBriVirtualAccountAction;
use App\Domains\Keuangan\Actions\Webhook\IssueBriAccessTokenAction;
use App\Domains\Keuangan\Actions\Webhook\ProcessBriVaPaymentAction;
use App\Domains\Keuangan\Contracts\BriInboundAuthenticatorInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BriVaInboundController extends Controller
{
    public function __construct(private readonly BriInboundAuthenticatorInterface $authenticator)
    {
    }

    public function token(Request $request, IssueBriAccessTokenAction $action)
    {
        $clientId = (string) $request->input('client_id');
        $clientSecret = (string) $request->input('client_secret');

        $token = $action->execute($clientId, $clientSecret);

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

    public function inquiry(Request $request, InquiryBriVirtualAccountAction $action)
    {
        if (!$this->authenticator->validateToken($this->bearerToken($request))) {
            return response()->json([
                'responseCode' => '4012400',
                'responseMessage' => 'Unauthorized. Invalid Token (B2B)',
            ], 401);
        }

        $vaNumber = trim((string) $request->input('virtualAccountNo'));

        $result = $action->execute($vaNumber);

        if ($result === null) {
            return response()->json([
                'responseCode' => '4042412',
                'responseMessage' => 'Invalid Bill/Virtual Account',
            ], 404);
        }

        return response()->json([
            'responseCode' => '2002400',
            'responseMessage' => 'Successful',
            'virtualAccountData' => [
                'partnerServiceId' => substr($vaNumber, 0, 8),
                'customerNo' => substr($vaNumber, 8),
                'virtualAccountNo' => $vaNumber,
                'virtualAccountName' => $result->virtualAccountName,
                'inquiryRequestId' => (string) $request->input('inquiryRequestId'),
                'totalAmount' => [
                    'value' => number_format($result->saranNominal, 2, '.', ''),
                    'currency' => 'IDR',
                ],
                'inquiryStatus' => '00',
            ],
        ]);
    }

    public function payment(Request $request, ProcessBriVaPaymentAction $action)
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

        $outcome = $action->execute($vaNumber, $paymentRequestId, $amount);

        return match ($outcome->status) {
            'invalid_amount' => response()->json([
                'responseCode' => '4042513',
                'responseMessage' => 'Invalid Amount',
            ], 404),
            'va_not_found' => response()->json([
                'responseCode' => '4042512',
                'responseMessage' => 'Invalid Bill/Virtual Account',
            ], 404),
            'log_write_failed' => response()->json([
                'responseCode' => '5002500',
                'responseMessage' => 'Internal Server Error',
            ], 500),
            'success' => $this->paymentSuccessResponse($vaNumber, $paymentRequestId, $outcome->amount, $outcome->virtualAccountName),
        };
    }

    protected function paymentSuccessResponse(string $vaNumber, string $paymentRequestId, float $amount, ?string $virtualAccountName = null)
    {
        return response()->json([
            'responseCode' => '2002500',
            'responseMessage' => 'Successful',
            'virtualAccountData' => [
                'partnerServiceId' => substr($vaNumber, 0, 8),
                'customerNo' => substr($vaNumber, 8),
                'virtualAccountNo' => $vaNumber,
                'virtualAccountName' => $virtualAccountName,
                'paymentRequestId' => $paymentRequestId,
                'paidAmount' => [
                    'value' => number_format($amount, 2, '.', ''),
                    'currency' => 'IDR',
                ],
                'paymentFlagStatus' => '00',
            ],
        ]);
    }

    protected function bearerToken(Request $request): string
    {
        return (string) str($request->header('Authorization', ''))->after('Bearer ');
    }
}
```

**Verifikasi kritis**: bandingkan SETIAP `response()->json([...], $status)` di controller baru ini dengan tabel §4 spec — field, value, dan status HTTP harus sama persis huruf demi huruf dengan kode asli.

- [ ] **Step 7: Hapus controller lama**

```bash
git rm app/Http/Controllers/Api/BriVaInboundController.php
```

- [ ] **Step 8: Update `routes/web.php`**

Ganti 3 baris:
```php
Route::post('/snap/v1.0/access-token/b2b', [\App\Http\Controllers\Api\BriVaInboundController::class, 'token']);
Route::post('/snap/v1.0/transfer-va/inquiry', [\App\Http\Controllers\Api\BriVaInboundController::class, 'inquiry']);
Route::post('/snap/v1.0/transfer-va/payment', [\App\Http\Controllers\Api\BriVaInboundController::class, 'payment']);
```
menjadi:
```php
Route::post('/snap/v1.0/access-token/b2b', [\App\Http\Controllers\Api\Keuangan\BriVaInboundController::class, 'token']);
Route::post('/snap/v1.0/transfer-va/inquiry', [\App\Http\Controllers\Api\Keuangan\BriVaInboundController::class, 'inquiry']);
Route::post('/snap/v1.0/transfer-va/payment', [\App\Http\Controllers\Api\Keuangan\BriVaInboundController::class, 'payment']);
```

**URL path (`/snap/v1.0/...`) TIDAK BOLEH berubah sama sekali** — hanya nama class controller yang berubah.

- [ ] **Step 9: Cek test existing menguji SEMUA 11 kombinasi §4 spec, tambahkan yang belum ada**

```bash
grep -rl "BriVaInbound\|snap/v1.0" tests --include="*.php"
```

Baca SETIAP file hasil grep (kemungkinan `tests/Feature/Keuangan/BriVaInboundPaymentTest.php`, `BriVaInboundInquiryTest.php`, `BriVaInboundInquiryTest.php`, `SimulateBriInboundCommandTest.php`, dan lainnya). Untuk SETIAP baris di tabel §4 spec, pastikan ADA test yang menyerang kondisi itu dengan assert response code + status HTTP yang PERSIS. Kalau ada kondisi yang belum ditest (contoh paling mungkin terlewat: `token` gagal 401, `payment` field kosong 400, `payment` genuine-duplicate-race), tambahkan test baru mengikuti pola test yang sudah ada di file itu. Tulis daftar kondisi mana yang SUDAH ada test-nya vs BARU ditambahkan di task ini — WAJIB dicatat di handoff log nanti (Task 19), bukan diam-diam.

- [ ] **Step 10: Jalankan test scoped**

```bash
php artisan route:list --path=snap
php artisan test tests/Feature/Keuangan/BriVaInboundPaymentTest.php tests/Feature/Keuangan/BriVaInboundInquiryTest.php tests/Feature/Keuangan/SimulateBriInboundCommandTest.php
```
Expected: semua PASS, `route:list` menunjukkan URL `/snap/v1.0/...` TIDAK berubah, Action mengarah ke `Api\Keuangan\BriVaInboundController`.

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): refactor webhook BriVaInboundController jadi 3 Action, pindah ke Api\Keuangan\, response JSON byte-identical dipertahankan"
```

---

## Task 17: Cross-Scope Touch — `ReconcilePayments` Command

**Files:**
- Modify: `app/Console/Commands/ReconcilePayments.php` (HANYA `use` statement — command TETAP di `app/Console/Commands/`, bukan bagian struktur Domain per SKILL.md)
- Modify: `app/Console/Commands/BriTestQris.php` (kalau ada referensi model/gateway yang pindah)

**Interfaces:**
- Consumes: `App\Domains\Keuangan\Models\{Pembayaran,BriQrisPayment}` (Task 1, 3), `App\Domains\Keuangan\Contracts\PaymentGatewayInterface` (Task 5) kalau dipakai langsung, `App\Services\Finance\PaymentAllocationService` (TIDAK PINDAH, tetap `app/Services/Finance`).

- [ ] **Step 1: Baca `app/Console/Commands/ReconcilePayments.php` lengkap, update SEMUA `use` yang menunjuk model/contract/service yang pindah**

Command ini TIDAK pindah lokasi (tetap `app/Console/Commands/ReconcilePayments.php`) — hanya baris `use` yang mengarah ke `App\Models\Pembayaran`, `App\Models\BriQrisPayment`, `App\Contracts\PaymentGatewayInterface` (kalau dipakai langsung, bukan cuma lewat `PaymentAllocationService`) yang diupdate ke namespace `Domains\Keuangan\*`. `use App\Services\Finance\PaymentAllocationService;` TIDAK diubah (kelas itu sengaja tidak pindah).

- [ ] **Step 2: Baca `app/Console/Commands/BriTestQris.php` lengkap, update `use` yang sama kalau ada**

Command dev/testing ini menginstansiasi `BriSnapGateway` manual (dikonfirmasi saat riset) — update `use App\Services\Finance\Gateway\BriSnapGateway;` (kalau ada) ke `use App\Domains\Keuangan\Services\Gateway\BriSnapGateway;`, dan `use App\Models\Pembayaran;` (kalau ada) ke namespace baru.

- [ ] **Step 3: Grep ulang untuk verifikasi**

```bash
grep -rln "App\\\\Models\\\\Pembayaran\|App\\\\Models\\\\BriQrisPayment\|App\\\\Contracts\\\\PaymentGatewayInterface\|App\\\\Services\\\\Finance\\\\Gateway\\\\BriSnapGateway" app/Console/Commands
```
Expected: kosong (semua referensi di `app/Console/Commands/*` sudah ke namespace baru).

- [ ] **Step 4: Jalankan test scoped**

```bash
php artisan test tests/Feature/Keuangan/ReconciliationCommandTest.php tests/Feature/Keuangan/ReconcilePaymentsBundledTopupTest.php tests/Feature/Keuangan/ReconcilePaymentsQrisTest.php tests/Feature/Keuangan/SimulateBriInboundCommandTest.php
```
Expected: semua PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): cross-scope touch ReconcilePayments dan BriTestQris command (namespace model/gateway baru)"
```

---

## Task 18: Verifikasi Gabungan Akhir Sebelum Handoff

**Files:**
- Tidak ada file baru — task ini murni verifikasi gate sebelum Task 19.

- [ ] **Step 1: Verifikasi gabungan — tidak ada referensi namespace lama tersisa di manapun**

```bash
grep -rln "use App\\\\Models\\\\Pembayaran;\|use App\\\\Models\\\\PembayaranTagihan;\|use App\\\\Models\\\\BriVirtualAccount;\|use App\\\\Models\\\\BriQrisPayment;\|use App\\\\Models\\\\BriInboundPaymentLog;\|use App\\\\Models\\\\ManualPaymentRequest;\|use App\\\\Contracts\\\\PaymentGatewayInterface;\|use App\\\\Contracts\\\\BriInboundAuthenticatorInterface;\|use App\\\\DTO\\\\PaymentStatusResult;\|use App\\\\DTO\\\\QrisResult;\|use App\\\\DTO\\\\VirtualAccountResult;\|use App\\\\Services\\\\Finance\\\\PaymentService;\|use App\\\\Services\\\\PembayaranService;\|use App\\\\Http\\\\Controllers\\\\Keuangan\\\\Concerns\\\\AuthorizesPembayaran;" --include="*.php" app database tests
```
Expected: KOSONG total.

- [ ] **Step 2: Verifikasi file/folder lama sudah tidak ada**

```bash
ls app/Models/Pembayaran.php app/Models/PembayaranTagihan.php app/Models/BriVirtualAccount.php app/Models/BriQrisPayment.php app/Models/BriInboundPaymentLog.php app/Models/ManualPaymentRequest.php app/Contracts/PaymentGatewayInterface.php app/Contracts/BriInboundAuthenticatorInterface.php app/DTO/PaymentStatusResult.php app/DTO/QrisResult.php app/DTO/VirtualAccountResult.php app/Services/Finance/PaymentService.php app/Services/PembayaranService.php app/Services/Finance/Gateway app/Services/Finance/BriInbound app/Http/Controllers/Keuangan/Concerns app/Http/Controllers/Admin/PembayaranController.php app/Http/Controllers/Admin/ManualPaymentController.php app/Http/Controllers/Admin/VirtualAccountController.php app/Http/Controllers/Keuangan/CheckoutController.php app/Http/Controllers/Keuangan/RiwayatController.php app/Http/Controllers/Api/BriVaInboundController.php 2>&1
```
Expected: error "No such file or directory" untuk SEMUANYA.

- [ ] **Step 3: Verifikasi route name dan URL tidak berubah**

```bash
php artisan route:list --name=admin.pembayaran
php artisan route:list --name=admin.manual-payment
php artisan route:list --name=admin.virtual-account
php artisan route:list --name=keuangan.checkout
php artisan route:list --name=keuangan.riwayat
php artisan route:list --path=snap
```
Bandingkan dengan daftar route sebelum migrasi (nama harus identik, URL webhook `/snap/v1.0/...` harus identik, Action target harus mengarah ke namespace baru).

- [ ] **Step 4: Kalau ada temuan yang tidak sesuai Step 1-3, STOP dan perbaiki sebelum lanjut Task 19.**

Tidak ada commit di task ini — murni gate verifikasi.

---

## Task 19: Verifikasi Akhir + Handoff Log

**Files:**
- Create: `.agents/logs/2026-08-24-refactor-02-keuangan-sp3-pembayaran-gateway.md`

- [ ] **Step 1: Jalankan test scoped gabungan luas**

```bash
php artisan test tests/Feature/Keuangan tests/Feature/Admin tests/Unit tests/Feature/Portal tests/Feature/Spmb tests/Feature/Console
```
Catat jumlah pasti passed/failed. Flaky yang sudah dikenal (hari-Minggu terkait hari libur mingguan SDM) — kalau itu SATU-SATUNYA yang gagal, jalankan ulang sendirian untuk konfirmasi, BUKAN regresi dari sub-project ini.

- [ ] **Step 2: Minta izin user untuk full test suite**

Tanya ke user: "Task 1-18 selesai, test scoped semua hijau. Boleh saya jalankan full test suite (`php artisan test`) untuk verifikasi akhir?" — TUNGGU jawaban eksplisit. JANGAN jalankan otomatis tanpa izin.

- [ ] **Step 3: Jalankan full suite (HANYA setelah izin didapat)**

```bash
php artisan test
```
Catat angka PASTI passed/failed/duration.

- [ ] **Step 4: Tulis handoff log**

Buat `.agents/logs/2026-08-24-refactor-02-keuangan-sp3-pembayaran-gateway.md` (Bahasa Indonesia): ringkasan tiap task (1-17) dengan commit hash, hasil test dengan angka PASTI dari Step 1 dan Step 3 (JANGAN dicampur), hasil Task 18 (harus "kosong"/sesuai). **WAJIB sebutkan eksplisit**:
- Daftar kondisi webhook (dari 11 kombinasi §4 spec) mana yang SUDAH ada test-nya sebelum SP3 vs BARU ditambahkan di Task 16 Step 9 — JANGAN digeneralisir jadi "semua sudah tertest".
- Apakah kedua test guard data-consistency baru (Task 11 Step 7) dan guard siswaLembagaId baru (Task 12 Step 6) berhasil ditulis persis seperti di plan atau disesuaikan — sebutkan apa yang berubah dan kenapa kalau disesuaikan.
- Kalau ada file di luar daftar yang disebutkan plan yang ternyata perlu disentuh, laporkan sebagai temuan terpisah — JANGAN diam-diam.
- Konfirmasi eksplisit bahwa TIDAK ADA perubahan pada urutan guard §7 manapun (sebutkan satu per satu, ceklist 7 item).

- [ ] **Step 5: Update `.agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md` §6**

Tambahkan baris baru di tabel Sub-Task untuk "Migrasi Domain Keuangan Sub-project 3 (Pembayaran & Gateway)" dengan link ke spec/plan/log, status 🟢 SELESAI.

- [ ] **Step 6: Commit**

```bash
git add .agents/logs/2026-08-24-refactor-02-keuangan-sp3-pembayaran-gateway.md .agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md
git commit -m "docs(refactor): handoff log migrasi domain Keuangan Sub-project 3 (pembayaran & gateway)"
```
