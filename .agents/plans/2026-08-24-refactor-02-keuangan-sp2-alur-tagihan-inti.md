# Migrasi Domain Keuangan Sub-project 2: Alur Tagihan Inti + Portal Tampilan — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memindahkan model `Tagihan`+`BillingJobLog`, service `TagihanBillingGenerator`+`TagihanCicilanEligibilityService` (baru), event `BillTypeActivated`+3 listener, dan 3 controller (`JenisTagihanMonitoringController`, `TagihanController` admin, `TagihanController` portal siswa/ortu aktif) ke `app/Domains/Keuangan/*`, tanpa mengubah perilaku aplikasi.

**Architecture:** Model pindah fisik (hanya `$fillable`/`casts()`/relationship + `LogsActivity` config untuk `Tagihan`). 2 method business-logic (`bisaDicicil()`/`maksCicilan()`) diekstrak ke service baru. Controller mutasi (bukan read-only) direfactor jadi Action. `buatSusulan()` diekstrak ke controller SPMB terpisah di luar domain Keuangan. `Portal\TagihanController` (portal pendaftar PPDB, dikoreksi dari estimasi awal) TIDAK dimigrasi.

**Tech Stack:** Laravel 12, Pest.

## Global Constraints

- **Zero-behavior-change** — pesan error, kode status HTTP, urutan validasi, format respons JSON/redirect HARUS identik kata-per-kata. Kalau ditemukan celah/inkonsistensi di kode lama, JANGAN diperbaiki diam-diam — laporkan ke user.
- Route NAME dan PATH tidak berubah sama sekali. Hanya `use` statement controller di file route yang diganti.
- Model pindahan HANYA `$fillable`/`casts()`/relationship/trait config (`LogsActivity`) — TIDAK ADA method business logic. `Tagihan::bisaDicicil()`/`maksCicilan()` WAJIB dihapus dari model, dipindah ke `TagihanCicilanEligibilityService`.
- **2 guard keamanan berikut WAJIB dipertahankan PERSIS, tanpa penyederhanaan apa pun** (pelajaran langsung dari celah HIGH yang ditemukan di review SP1 — guard tenant-isolation yang hilang saat refactor Action):
  1. `JenisTagihanMonitoringController::batalTagihan()` — cek kepemilikan (`$tagihan->jenis_tagihan_id !== $jenisTagihan->id` → `abort(403, ...)`) HARUS dijalankan SEBELUM cek status bisnis (`$tagihan->status !== 'belum_bayar'` → `abort(422, ...)`). Urutan ini TIDAK BOLEH dibalik.
  2. `Admin\TagihanController` — pola `abort_unless($tagihan->pendaftaran->lembaga_id === $this->lembagaId($request), 404)` (atau varian via relasi `skemaCicilan->tagihan`/`cicilan->skemaCicilan->tagihan`) di 4 method (`buatSkemaCicilan`, `simpanNominalCicilan`, `catatManualTagihan`, `catatManualCicilan`) WAJIB tetap ada persis, karena `Tagihan` tidak punya `lembaga_id` langsung.
- **Verifikasi grep WAJIB menyisir `app database tests`** (bukan cuma `app/Models`) — cari string `App\Models\{ClassName}` (menangkap `use` DAN FQCN inline), bukan cuma `{ClassName}::class`.
- Referensi lintas-namespace dari file yang TETAP di lokasi lama pakai **FQCN inline**, BUKAN `use` statement tambahan.
- `newFactory()` WAJIB untuk `Tagihan` (pakai `HasFactory`). **TIDAK ditambahkan** untuk `BillingJobLog` (tidak pakai `HasFactory` sekarang, tidak ada factory-nya).
- Console Command (`GenerateTagihanHarian`, `KirimDueReminderTagihan`, `ProsesTagihan`) TETAP di `app/Console/Commands/` — hanya `use` statement yang diupdate, bukan bagian struktur `Domains/`.
- Notification (`app/Notifications/Finance/TagihanDiterbitkanNotification.php`, `DueReminderNotification.php`) TETAP di lokasi asal.
- `Portal\TagihanController`, `Keuangan\DashboardController`, `Keuangan\RiwayatController`, `Keuangan\CheckoutController`, `Keuangan\NotifikasiController`, `PembayaranService`, `TagihanGenerator` (PPDB) — TIDAK disentuh selain cross-scope touch eksplisit di plan ini.
- Baseline kode: commit `8a8c475` di branch `refactor-v1`. Kalau isi file berbeda signifikan dari yang dikutip plan, STOP, laporkan ke user.
- Tiap task: test SCOPED SEBELUM commit. Full suite HANYA task terakhir, izin eksplisit user dulu.

---

## Task 1: Pindahkan Model `Tagihan` (+ Hapus 2 Method Business Logic)

**Files:**
- Move: `app/Models/Tagihan.php` → `app/Domains/Keuangan/Models/Tagihan.php`
- Modify: `database/factories/TagihanFactory.php` + seluruh file hasil grep `use App\Models\Tagihan;` yang BUKAN bagian task 3/4/5/7/8/9/10/11 di plan ini (grep ulang WAJIB, daftar berikut per 24 Agustus 2026, JANGAN dipercaya buta — grep ulang dulu sebelum edit massal)

**Interfaces:**
- Produces: `App\Domains\Keuangan\Models\Tagihan` — TANPA method `bisaDicicil()`/`maksCicilan()` (dipindah ke `TagihanCicilanEligibilityService` di Task 3). Dipakai di seluruh task berikutnya.

**PENTING:** Method `bisaDicicil()`/`maksCicilan()` DIHAPUS dari model di task ini, TAPI service penggantinya (`TagihanCicilanEligibilityService`) baru dibuat di Task 3. Ada window singkat (antara Task 1 dan Task 3 selesai) di mana 2 call site controller (`Admin\TagihanController:129`, `Portal\TagihanController:47`) akan error "method not found" kalau dijalankan — INI AMAN kalau task dieksekusi berurutan tanpa deploy parsial di antaranya (sama seperti pola window `booted()` di SP1 Task 1).

- [ ] **Step 1: Pindahkan file fisik**

```bash
git mv app/Models/Tagihan.php app/Domains/Keuangan/Models/Tagihan.php
```

- [ ] **Step 2: Ubah isi file — namespace, `newFactory()`, HAPUS `bisaDicicil()`/`maksCicilan()`**

Timpa seluruh isi `app/Domains/Keuangan/Models/Tagihan.php` dengan:

```php
<?php
// app/Domains/Keuangan/Models/Tagihan.php

namespace App\Domains\Keuangan\Models;

use App\Models\Pembayaran;
use App\Models\PembayaranTagihan;
use App\Models\Pendaftaran;
use Database\Factories\TagihanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Tagihan extends Model
{
    use HasFactory, LogsActivity;

    protected static function newFactory(): TagihanFactory
    {
        return TagihanFactory::new();
    }

    protected $table = 'tagihan';

    protected $fillable = [
        'pendaftaran_id', 'tagihable_type', 'tagihable_id', 'jenis_tagihan_id',
        'kategori', 'billing_period', 'source_trigger',
        'total_tagihan', 'discount_amount', 'discount_type', 'net_amount', 'paid_amount',
        'status', 'jatuh_tempo', 'cancelled_by', 'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'jatuh_tempo' => 'date',
            'discount_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(Pendaftaran::class);
    }

    public function tagihable(): MorphTo
    {
        return $this->morphTo();
    }

    public function jenisTagihan(): BelongsTo
    {
        return $this->belongsTo(JenisTagihan::class);
    }

    public function item(): HasMany
    {
        return $this->hasMany(TagihanItem::class);
    }

    public function skemaCicilan(): HasOne
    {
        return $this->hasOne(SkemaCicilan::class);
    }

    public function cicilan(): HasManyThrough
    {
        return $this->hasManyThrough(\App\Models\Cicilan::class, SkemaCicilan::class, 'tagihan_id', 'skema_cicilan_id');
    }

    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class);
    }

    public function pembayaranTagihan(): HasMany
    {
        return $this->hasMany(PembayaranTagihan::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'total_tagihan'])
            ->logOnlyDirty()
            ->useLogName('tagihan');
    }
}
```

Catatan: `jenisTagihan()`, `item()`, `skemaCicilan()` menunjuk ke model yang SUDAH pindah ke `Domains\Keuangan\Models` di SP1 (`JenisTagihan`, `TagihanItem`, `SkemaCicilan`) — TIDAK perlu `use` tambahan, sudah satu namespace. `cicilan()` menunjuk `\App\Models\Cicilan` (SP4, FQCN inline karena beda namespace). `pembayaran()`/`pembayaranTagihan()` tetap `use App\Models\Pembayaran;`/`use App\Models\PembayaranTagihan;` (SP3, model itu sendiri TIDAK pindah, jadi pakai `use` biasa dari file yang PINDAH ke file yang TIDAK pindah — ini normal, bukan gotcha).

- [ ] **Step 3: Update `database/factories/TagihanFactory.php`**

Ganti baris `use App\Models\Tagihan;` menjadi `use App\Domains\Keuangan\Models\Tagihan;`. Tidak ada perubahan lain (isi `definition()`/`configure()` tetap sama).

- [ ] **Step 4: Grep ulang untuk daftar file consumer PASTI**

```bash
grep -rln "use App\\\\Models\\\\Tagihan;" --include="*.php" app database tests
```

Bandingkan hasilnya dengan daftar berikut (hasil grep 24 Agustus 2026, WAJIB dianggap sebagai referensi awal, BUKAN daftar final — kalau ada file baru di luar daftar ini, tambahkan ke proses edit; kalau ada file di daftar ini yang sudah tidak ada, lewati):

```
tests/Feature/Keuangan/BillTypeActivatedEventTest.php
app/Services/TagihanBillingGenerator.php               <- JANGAN diedit di sini, ditangani Task 4
tests/Feature/Keuangan/TagihanBillingGeneratorTest.php
tests/Feature/Portal/TagihanPembayaranTest.php
tests/Feature/Admin/SkemaCicilanTest.php
tests/Unit/SkemaCicilanSeederTest.php
tests/Unit/PembayaranServiceTest.php
tests/Unit/PembayaranDataLayerTest.php
tests/Unit/DashboardStatsServiceTest.php
app/Services/PembayaranService.php
app/Http/Controllers/Admin/TagihanController.php       <- JANGAN diedit di sini, ditangani Task 8/9
database/factories/SkemaCicilanFactory.php
tests/Unit/TagihanItemSeederTest.php
tests/Unit/PembayaranSeederTest.php
tests/Unit/KeuanganDataLayerTest.php
tests/Feature/Keuangan/PaymentServiceTest.php
tests/Feature/Keuangan/BriVaInboundPaymentTest.php
tests/Feature/Keuangan/BriVaInboundInquiryTest.php
tests/Feature/Admin/CatatManualPembayaranTest.php
database/seeders/TagihanItemSeeder.php
database/seeders/KeuanganDemoSeeder.php
app/Services/TagihanGenerator.php
database/factories/TagihanItemFactory.php
tests/Feature/Keuangan/StudentBillingEventsTest.php
tests/Feature/Admin/JenisTagihanProsesTest.php
tests/Unit/TagihanSeederTest.php
tests/Unit/TagihanGeneratorTest.php
tests/Feature/Spmb/TagihanPendaftaranHookTest.php
tests/Feature/Admin/TagihanSusulanTest.php
tests/Feature/Admin/TagihanDaftarUlangHookTest.php
database/seeders/TagihanSeeder.php
tests/Feature/Keuangan/TagihanControllerTest.php
tests/Feature/Keuangan/SystemSettingTest.php
tests/Feature/Keuangan/SaldoTidakCukupNotificationTest.php
tests/Feature/Keuangan/RiwayatControllerIndexTest.php
tests/Feature/Keuangan/RiwayatAuthorizationTest.php
tests/Feature/Keuangan/ReconcilePaymentsBundledTopupTest.php
tests/Feature/Keuangan/ProsesTagihanCommandTest.php
tests/Feature/Keuangan/PembayaranBerhasilNotificationTest.php
tests/Feature/Keuangan/PaymentServiceBundledTopupTest.php
tests/Feature/Keuangan/PaymentAllocationServiceTopupRemainderTest.php
tests/Feature/Keuangan/KwitansiControllerTest.php
tests/Feature/Keuangan/GenerateTagihanHarianCommandTest.php
tests/Feature/Keuangan/DashboardControllerTest.php
tests/Feature/Keuangan/DashboardAuthorizationTest.php
tests/Feature/Keuangan/CheckoutControllerWalletTest.php
tests/Feature/Keuangan/CheckoutControllerVaQrisTest.php
tests/Feature/Keuangan/CheckoutControllerTransferTest.php
tests/Feature/Keuangan/CheckoutControllerCreateTest.php
tests/Feature/Keuangan/CheckoutControllerBundledTopupTest.php
tests/Feature/Keuangan/CheckoutAuthorizationTest.php
tests/Feature/Keuangan/AutoAllocationEngineTest.php
tests/Feature/Console/KirimDueReminderTagihanTest.php
tests/Feature/Admin/TagihanIndexTest.php
tests/Feature/Admin/ManualPaymentNotificationTest.php
tests/Feature/Admin/ManualPaymentIndexControllerTest.php
tests/Feature/Admin/ManualPaymentIndexAuthorizationTest.php
tests/Feature/Admin/ManualPaymentControllerTest.php
tests/Feature/Admin/JenisTagihanMonitoringTest.php
tests/Feature/Admin/JenisTagihanFinalReviewFixesTest.php
app/Http/Controllers/Admin/JenisTagihanMonitoringController.php  <- JANGAN diedit di sini, ditangani Task 7
tests/Unit/CicilanSeederTest.php
tests/Feature/Admin/DashboardYayasanTest.php
database/seeders/SkemaCicilanSeeder.php
database/seeders/PembayaranSeeder.php
database/seeders/CicilanSeeder.php
app/Services/Finance/PaymentAllocationService.php
app/Http/Controllers/Api/BriVaInboundController.php
app/Http/Controllers/Keuangan/DashboardController.php
app/Http/Controllers/Keuangan/CheckoutController.php
tests/Feature/Keuangan/ReconciliationCommandTest.php
app/Services/Finance/PaymentService.php
tests/Feature/Keuangan/PaymentServiceWalletPaymentTest.php
app/Http/Controllers/Keuangan/TagihanController.php     <- JANGAN diedit di sini, ditangani Task 10
app/Services/Finance/SkipAlertResolver.php
app/Services/Finance/AutoAllocationEngine.php
app/Notifications/Finance/DueReminderNotification.php
app/Notifications/Finance/SaldoTidakCukupNotification.php
tests/Feature/Keuangan/PaymentAllocationServiceTest.php
app/Notifications/Finance/PembayaranBerhasilNotification.php
app/Notifications/Finance/TagihanDiterbitkanNotification.php
tests/Feature/Keuangan/TagihanPolymorphicTest.php
tests/Feature/Keuangan/PembayaranWalletColumnsTest.php
tests/Feature/Keuangan/PembayaranTagihanTest.php
database/factories/TagihanFactory.php                   <- SUDAH diedit di Step 3
tests/Feature/Admin/PendaftaranSiswaControllerTest.php
app/Services/DashboardStatsService.php
tests/Feature/Admin/DashboardLembagaTest.php
tests/Feature/Admin/VerifikasiPembayaranTest.php
app/Http/Controllers/Portal/TagihanController.php       <- JANGAN diedit di sini, ditangani Task 3
database/factories/PembayaranFactory.php
```

- [ ] **Step 5: Update `use` statement di SETIAP file hasil grep Step 4, KECUALI file yang ditandai "JANGAN diedit di sini" di atas**

Untuk setiap file (selain yang dikecualikan), ganti baris `use App\Models\Tagihan;` menjadi `use App\Domains\Keuangan\Models\Tagihan;`. **HANYA baris `use` yang berubah — isi method/logic TIDAK disentuh sama sekali.**

- [ ] **Step 6: Verifikasi minimal — class ter-load**

```bash
php artisan tinker --execute="echo class_exists(\App\Domains\Keuangan\Models\Tagihan::class) ? 'OK' : 'MISSING';"
```
Expected: `OK`.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah model Tagihan ke Domains\Keuangan\Models, hapus bisaDicicil()/maksCicilan() (pindah ke TagihanCicilanEligibilityService di Task 3)"
```

---

## Task 2: Pindahkan Model `BillingJobLog`

**Files:**
- Move: `app/Models/BillingJobLog.php` → `app/Domains/Keuangan/Models/BillingJobLog.php`
- Modify: seluruh file hasil grep `BillingJobLog` yang BUKAN model itu sendiri dan BUKAN `TagihanBillingGenerator.php` (ditangani Task 4)

**Interfaces:**
- Produces: `App\Domains\Keuangan\Models\BillingJobLog` — dipakai Task 4.

- [ ] **Step 1: Pindahkan file fisik**

```bash
git mv app/Models/BillingJobLog.php app/Domains/Keuangan/Models/BillingJobLog.php
```

- [ ] **Step 2: Ubah isi file (TANPA `newFactory()` — model ini tidak pakai `HasFactory`)**

Timpa seluruh isi `app/Domains/Keuangan/Models/BillingJobLog.php` dengan:

```php
<?php

namespace App\Domains\Keuangan\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingJobLog extends Model
{
    protected $table = 'billing_job_logs';

    protected $fillable = [
        'jenis_tagihan_id', 'trigger_type', 'trigger_event', 'period',
        'bills_generated', 'status', 'error_log', 'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'error_log' => 'array',
            'executed_at' => 'datetime',
        ];
    }

    public function jenisTagihan(): BelongsTo
    {
        return $this->belongsTo(JenisTagihan::class);
    }
}
```

- [ ] **Step 3: Grep ulang dan update consumer**

```bash
grep -rln "BillingJobLog" --include="*.php" app database tests
```

Hasil per 24 Agustus 2026 (grep ulang WAJIB, daftar ini referensi awal):
```
tests/Feature/Keuangan/BillTypeActivatedEventTest.php   <- punya \App\Models\BillingJobLog::count() inline FQCN dari fix SP1, ganti ke \App\Domains\Keuangan\Models\BillingJobLog::count()
app/Services/TagihanBillingGenerator.php                <- JANGAN diedit di sini, ditangani Task 4
tests/Feature/Keuangan/TagihanBillingGeneratorTest.php
tests/Feature/Keuangan/BillingJobLogTest.php
app/Models/BillingJobLog.php                             <- sudah dipindah Step 1-2
```

Untuk `tests/Feature/Keuangan/BillTypeActivatedEventTest.php`: cari baris `\App\Models\BillingJobLog::count()` (ditambahkan saat fix review SP1), ganti jadi `\App\Domains\Keuangan\Models\BillingJobLog::count()`.

Untuk `tests/Feature/Keuangan/TagihanBillingGeneratorTest.php` dan `tests/Feature/Keuangan/BillingJobLogTest.php`: kalau ada `use App\Models\BillingJobLog;`, ganti ke `use App\Domains\Keuangan\Models\BillingJobLog;`. Kalau referensinya FQCN inline (`\App\Models\BillingJobLog::class`/`::count()`/dst), ganti ke `\App\Domains\Keuangan\Models\BillingJobLog`.

- [ ] **Step 4: Verifikasi**

```bash
grep -rn "App\\\\Models\\\\BillingJobLog" --include="*.php" app database tests
```
Expected: kosong (kecuali `app/Services/TagihanBillingGenerator.php` yang ditangani Task 4 — kalau muncul di sini itu WAJAR, jangan diedit sekarang).

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah model BillingJobLog ke Domains\Keuangan\Models"
```

---

## Task 3: Buat Service `TagihanCicilanEligibilityService` + Update 4 Call Site

**Files:**
- Create: `app/Domains/Keuangan/Services/TagihanCicilanEligibilityService.php`
- Modify: `app/Http/Controllers/Admin/TagihanController.php` (baris 129 — file ini akan direfactor total di Task 9, TAPI baris ini WAJIB diupdate SEKARANG supaya tidak error di window antar-task)
- Modify: `app/Http/Controllers/Portal/TagihanController.php` (baris 47 — file ini TIDAK dimigrasi, cross-scope touch)
- Modify: `resources/views/admin/spmb-pendaftaran/show.blade.php` (baris 301 — TIDAK dimigrasi, cross-scope touch)
- Modify: `resources/views/portal/tagihan/index.blade.php` (baris 59 — TIDAK dimigrasi karena ikut `Portal\TagihanController`, cross-scope touch)

**Interfaces:**
- Consumes: `App\Domains\Keuangan\Models\Tagihan` (Task 1).
- Produces: `App\Domains\Keuangan\Services\TagihanCicilanEligibilityService` dengan method `bisaDicicil(Tagihan $tagihan): bool` dan `maksCicilan(Tagihan $tagihan): ?int` — dipakai Task 9 (`Lembaga\Keuangan\TagihanController`).

- [ ] **Step 1: Buat service baru**

`app/Domains/Keuangan/Services/TagihanCicilanEligibilityService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Services;

use App\Domains\Keuangan\Models\Tagihan;

class TagihanCicilanEligibilityService
{
    /**
     * A tagihan can bundle multiple jenis_tagihan (line items) with different
     * bisa_dicicil rules — offering installment is allowed if ANY item is
     * cicilable, and the safe max termin count is the smallest maks_cicilan
     * among the cicilable items (never lets the whole invoice cicil beyond
     * what any single cicilable item's own rule allows).
     */
    public function bisaDicicil(Tagihan $tagihan): bool
    {
        return $tagihan->item()->whereHas('jenisTagihan', fn ($q) => $q->where('bisa_dicicil', true))->exists();
    }

    public function maksCicilan(Tagihan $tagihan): ?int
    {
        return $tagihan->item()
            ->whereHas('jenisTagihan', fn ($q) => $q->where('bisa_dicicil', true))
            ->with('jenisTagihan')
            ->get()
            ->min(fn ($item) => $item->jenisTagihan->maks_cicilan);
    }
}
```

- [ ] **Step 2: Update `app/Http/Controllers/Admin/TagihanController.php` baris 129**

Baca file, cari baris:
```php
            'jumlah_termin' => ['required', 'integer', 'min:2', 'max:'.($tagihan->maksCicilan() ?? 2)],
```
Ganti jadi:
```php
            'jumlah_termin' => ['required', 'integer', 'min:2', 'max:'.(app(\App\Domains\Keuangan\Services\TagihanCicilanEligibilityService::class)->maksCicilan($tagihan) ?? 2)],
```

**Catatan:** file ini akan direfactor TOTAL di Task 9 (pindah namespace, jadi Action/DTO). Perubahan di sini HANYA supaya file tetap valid syntactically dan lolos test di window antara Task 1 dan Task 9 — kalau plan dieksekusi berurutan tanpa lompat, baris ini akan diganti lagi (dengan cara yang lebih rapi lewat dependency injection) di Task 9.

- [ ] **Step 3: Update `app/Http/Controllers/Portal/TagihanController.php` baris 47**

Baca file, cari baris:
```php
            'jumlah_termin' => ['required', 'integer', 'min:2', 'max:'.($tagihan->maksCicilan() ?? 2)],
```
Ganti jadi:
```php
            'jumlah_termin' => ['required', 'integer', 'min:2', 'max:'.(app(\App\Domains\Keuangan\Services\TagihanCicilanEligibilityService::class)->maksCicilan($tagihan) ?? 2)],
```

File ini TIDAK dimigrasi (portal pendaftar PPDB, bagian SPMB yang ditunda) — HANYA baris ini yang disentuh, tidak ada perubahan lain.

- [ ] **Step 4: Update `resources/views/admin/spmb-pendaftaran/show.blade.php` baris 301**

Baca file, cari baris yang memanggil `$tagihan->bisaDicicil()`, ganti pemanggilannya jadi `app(\App\Domains\Keuangan\Services\TagihanCicilanEligibilityService::class)->bisaDicicil($tagihan)`. Konteks baris (dari baseline commit `8a8c475`):
```blade
                                        @if ($tagihan->status === 'belum_bayar' && $tagihan->bisaDicicil() && auth()->user()->can('cicilan.kelola'))
```
Ganti jadi:
```blade
                                        @if ($tagihan->status === 'belum_bayar' && app(\App\Domains\Keuangan\Services\TagihanCicilanEligibilityService::class)->bisaDicicil($tagihan) && auth()->user()->can('cicilan.kelola'))
```

- [ ] **Step 5: Update `resources/views/portal/tagihan/index.blade.php` baris 59**

Baca file, cari baris:
```blade
                                    @if ($tagihan->bisaDicicil())
```
Ganti jadi:
```blade
                                    @if (app(\App\Domains\Keuangan\Services\TagihanCicilanEligibilityService::class)->bisaDicicil($tagihan))
```

- [ ] **Step 6: Jalankan test scoped**

```bash
php artisan test tests/Feature/Portal/TagihanPembayaranTest.php tests/Feature/Admin/SkemaCicilanTest.php
```
Expected: semua PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat(keuangan): buat TagihanCicilanEligibilityService, ekstrak bisaDicicil()/maksCicilan() dari model Tagihan, update 4 call site cross-scope"
```

---

## Task 4: Pindahkan Service `TagihanBillingGenerator`

**Files:**
- Move: `app/Services/TagihanBillingGenerator.php` → `app/Domains/Keuangan/Services/TagihanBillingGenerator.php`
- Modify: seluruh file hasil grep `use App\Services\TagihanBillingGenerator;`

**Interfaces:**
- Consumes: `App\Domains\Keuangan\Models\{Tagihan,BillingJobLog}` (Task 1-2).
- Produces: `App\Domains\Keuangan\Services\TagihanBillingGenerator` — dipakai Task 5 (listener), `GenerateTagihanHarian`, `ProsesTagihan` command, Task 7/9 (Action monitoring/tagihan).

- [ ] **Step 1: Pindahkan file fisik**

```bash
git mv app/Services/TagihanBillingGenerator.php app/Domains/Keuangan/Services/TagihanBillingGenerator.php
```

- [ ] **Step 2: Ubah isi file**

Timpa seluruh isi `app/Domains/Keuangan/Services/TagihanBillingGenerator.php` dengan:

```php
<?php
// app/Domains/Keuangan/Services/TagihanBillingGenerator.php

namespace App\Domains\Keuangan\Services;

use App\Domains\Keuangan\Models\BillingJobLog;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Siswa;
use App\Notifications\Finance\TagihanDiterbitkanNotification;
use App\Services\Finance\NotificationDispatcher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TagihanBillingGenerator
{
    private const PPDB_KATEGORI = ['pendaftaran', 'daftar_ulang'];

    public function __construct(
        private readonly JenisTagihanSasaranMatcher $matcher,
        private readonly TagihanNominalResolver $nominalResolver,
        private readonly NotificationDispatcher $dispatcher,
    ) {
    }

    public function generate(JenisTagihan $jenisTagihan, string $triggerType, ?string $triggerEvent = null): BillingJobLog
    {
        $this->assertBillable($jenisTagihan);

        $targetSiswa = $this->matcher->resolveTargetSiswa($jenisTagihan);

        $billsGenerated = 0;
        $errors = [];

        foreach ($targetSiswa as $siswa) {
            try {
                if ($this->generateForSiswa($siswa, $jenisTagihan, $triggerType)) {
                    $billsGenerated++;
                }
            } catch (\Throwable $e) {
                $errors[] = ['siswa_id' => $siswa->id, 'message' => $e->getMessage()];
            }
        }

        return $this->logResult($jenisTagihan, $triggerType, $triggerEvent, $billsGenerated, $errors);
    }

    public function generateForSiswa(Siswa $siswa, JenisTagihan $jenisTagihan, string $triggerType): bool
    {
        $createdTagihan = null;

        $result = DB::transaction(function () use ($siswa, $jenisTagihan, $triggerType, &$createdTagihan) {
            $billingPeriod = $jenisTagihan->mode === 'otomatis' ? now()->format('Y-m') : null;

            $exists = Tagihan::where('tagihable_type', Siswa::class)
                ->where('tagihable_id', $siswa->id)
                ->where('jenis_tagihan_id', $jenisTagihan->id)
                ->where('billing_period', $billingPeriod)
                ->where('status', '!=', 'dibatalkan')
                ->exists();

            if ($exists) {
                return false;
            }

            $resolved = $this->nominalResolver->resolve($siswa, $jenisTagihan);
            $netAmount = max(0, $resolved['nominal'] - $resolved['discount_amount']);

            $createdTagihan = Tagihan::create([
                'tagihable_type' => Siswa::class,
                'tagihable_id' => $siswa->id,
                'jenis_tagihan_id' => $jenisTagihan->id,
                'kategori' => $jenisTagihan->kategori,
                'billing_period' => $billingPeriod,
                'source_trigger' => $triggerType,
                'total_tagihan' => $resolved['nominal'],
                'discount_amount' => $resolved['discount_amount'] ?: null,
                'discount_type' => $resolved['discount_type'],
                'net_amount' => $netAmount,
                'jatuh_tempo' => $this->resolveDueDate($jenisTagihan, $billingPeriod),
                'status' => 'belum_bayar',
            ]);

            return true;
        });

        if ($createdTagihan !== null) {
            $kontakUtama = $siswa->orangTua()->wherePivot('is_kontak_utama', true)->first();
            if ($kontakUtama !== null) {
                try {
                    $this->dispatcher->send($kontakUtama, new TagihanDiterbitkanNotification($createdTagihan->load('jenisTagihan')));
                } catch (\Throwable $e) {
                    Log::error('Gagal mengirim TagihanDiterbitkanNotification: '.$e->getMessage());
                }
            }
        }

        return $result;
    }

    public function generateForSiswaViaEvent(Siswa $siswa, JenisTagihan $jenisTagihan, string $triggerEvent): BillingJobLog
    {
        $this->assertBillable($jenisTagihan);

        $billsGenerated = 0;
        $errors = [];

        try {
            if ($this->generateForSiswa($siswa, $jenisTagihan, 'event')) {
                $billsGenerated = 1;
            }
        } catch (\Throwable $e) {
            $errors[] = ['siswa_id' => $siswa->id, 'message' => $e->getMessage()];
        }

        return $this->logResult($jenisTagihan, 'event', $triggerEvent, $billsGenerated, $errors);
    }

    private function assertBillable(JenisTagihan $jenisTagihan): void
    {
        if (in_array($jenisTagihan->kategori, self::PPDB_KATEGORI, true)) {
            throw new \RuntimeException(
                "Jenis tagihan berkategori {$jenisTagihan->kategori} tidak bisa diproses lewat billing engine — gunakan alur pendaftaran PPDB."
            );
        }
    }

    private function resolveDueDate(JenisTagihan $jenisTagihan, ?string $billingPeriod): ?string
    {
        if (! $billingPeriod || ! $jenisTagihan->hari_jatuh_tempo) {
            return null;
        }

        $year = (int) substr($billingPeriod, 0, 4);
        $month = (int) substr($billingPeriod, 5, 2);
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $day = min($jenisTagihan->hari_jatuh_tempo, $daysInMonth);

        return Carbon::create($year, $month, $day)->toDateString();
    }

    private function logResult(JenisTagihan $jenisTagihan, string $triggerType, ?string $triggerEvent, int $billsGenerated, array $errors): BillingJobLog
    {
        $status = match (true) {
            empty($errors) => 'success',
            $billsGenerated === 0 => 'failed',
            default => 'partial',
        };

        return BillingJobLog::create([
            'jenis_tagihan_id' => $jenisTagihan->id,
            'trigger_type' => $triggerType,
            'trigger_event' => $triggerEvent,
            'period' => $jenisTagihan->mode === 'otomatis' ? now()->format('Y-m') : null,
            'bills_generated' => $billsGenerated,
            'status' => $status,
            'error_log' => empty($errors) ? null : $errors,
            'executed_at' => now(),
        ]);
    }
}
```

Catatan: `JenisTagihanSasaranMatcher`/`TagihanNominalResolver` sudah di `App\Domains\Keuangan\Services` sejak SP1 — karena `TagihanBillingGenerator` SEKARANG juga di namespace yang sama, TIDAK perlu `use` statement untuk keduanya (constructor type-hint tanpa import, resolve otomatis lewat namespace yang sama).

- [ ] **Step 3: Update seluruh file consumer**

```bash
grep -rln "use App\\\\Services\\\\TagihanBillingGenerator;" --include="*.php" app database tests
```

Di SETIAP file hasil grep, ganti `use App\Services\TagihanBillingGenerator;` → `use App\Domains\Keuangan\Services\TagihanBillingGenerator;`. Ini termasuk `app/Console/Commands/GenerateTagihanHarian.php`, `app/Console/Commands/ProsesTagihan.php`, `app/Listeners/GenerateTagihanForActivatedBillType.php`, `app/Listeners/GenerateTagihanForNewStudent.php`, `app/Listeners/GenerateTagihanForUpdatedClass.php` (3 listener ini akan pindah namespace-nya sendiri di Task 5 — untuk SEKARANG cukup update `use TagihanBillingGenerator`-nya saja, namespace file listener itu sendiri belum berubah di task ini).

- [ ] **Step 4: Verifikasi**

```bash
grep -rln "use App\\\\Services\\\\TagihanBillingGenerator;" --include="*.php" app database tests
```
Expected: kosong.

- [ ] **Step 5: Jalankan test scoped**

```bash
php artisan test tests/Feature/Keuangan/TagihanBillingGeneratorTest.php tests/Feature/Keuangan/StudentBillingEventsTest.php tests/Feature/Keuangan/BillingJobLogTest.php tests/Feature/Console/KirimDueReminderTagihanTest.php tests/Feature/Keuangan/GenerateTagihanHarianCommandTest.php tests/Feature/Keuangan/ProsesTagihanCommandTest.php
```
Expected: semua PASS.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah TagihanBillingGenerator ke Domains\Keuangan\Services"
```

---

## Task 5: Pindahkan Event `BillTypeActivated` + 3 Listener

**Files:**
- Move: `app/Events/BillTypeActivated.php` → `app/Domains/Keuangan/Events/BillTypeActivated.php`
- Move: `app/Listeners/GenerateTagihanForActivatedBillType.php` → `app/Domains/Keuangan/Listeners/GenerateTagihanForActivatedBillType.php`
- Move: `app/Listeners/GenerateTagihanForNewStudent.php` → `app/Domains/Keuangan/Listeners/GenerateTagihanForNewStudent.php`
- Move: `app/Listeners/GenerateTagihanForUpdatedClass.php` → `app/Domains/Keuangan/Listeners/GenerateTagihanForUpdatedClass.php`
- Modify: `app/Domains/Keuangan/Actions/JenisTagihan/UpdateJenisTagihanAction.php` (SP1, masih `use App\Events\BillTypeActivated;`)
- Modify: seluruh file hasil grep lain untuk `BillTypeActivated`/3 nama listener

**Interfaces:**
- Produces: `App\Domains\Keuangan\Events\BillTypeActivated`, `App\Domains\Keuangan\Listeners\{GenerateTagihanForActivatedBillType,GenerateTagihanForNewStudent,GenerateTagihanForUpdatedClass}`.

**PENTING:** Registrasi listener di Laravel 12 ini pakai auto-discovery (type-hint `handle(EventType $event)` di listener, TIDAK ada `EventServiceProvider::$listen` manual — sudah dikonfirmasi lewat grep di `app/` dan `bootstrap/` saat spec ditulis). **TIDAK ADA file registrasi terpisah yang perlu diupdate** — cukup pastikan `handle()` type-hint event yang benar.

- [ ] **Step 1: Pindahkan 4 file fisik**

```bash
mkdir -p app/Domains/Keuangan/Events app/Domains/Keuangan/Listeners
git mv app/Events/BillTypeActivated.php app/Domains/Keuangan/Events/BillTypeActivated.php
git mv app/Listeners/GenerateTagihanForActivatedBillType.php app/Domains/Keuangan/Listeners/GenerateTagihanForActivatedBillType.php
git mv app/Listeners/GenerateTagihanForNewStudent.php app/Domains/Keuangan/Listeners/GenerateTagihanForNewStudent.php
git mv app/Listeners/GenerateTagihanForUpdatedClass.php app/Domains/Keuangan/Listeners/GenerateTagihanForUpdatedClass.php
```

- [ ] **Step 2: Ubah isi `app/Domains/Keuangan/Events/BillTypeActivated.php`**

Timpa seluruh isi dengan:

```php
<?php

namespace App\Domains\Keuangan\Events;

use App\Domains\Keuangan\Models\JenisTagihan;
use Illuminate\Foundation\Events\Dispatchable;

class BillTypeActivated
{
    use Dispatchable;

    public function __construct(public readonly JenisTagihan $jenisTagihan)
    {
    }
}
```

- [ ] **Step 3: Ubah isi `app/Domains/Keuangan/Listeners/GenerateTagihanForActivatedBillType.php`**

Timpa seluruh isi dengan:

```php
<?php

namespace App\Domains\Keuangan\Listeners;

use App\Domains\Keuangan\Events\BillTypeActivated;
use App\Domains\Keuangan\Services\TagihanBillingGenerator;

class GenerateTagihanForActivatedBillType
{
    private const PPDB_KATEGORI = ['pendaftaran', 'daftar_ulang'];

    public function __construct(private readonly TagihanBillingGenerator $generator)
    {
    }

    public function handle(BillTypeActivated $event): void
    {
        if (in_array($event->jenisTagihan->kategori, self::PPDB_KATEGORI, true)) {
            return;
        }

        $this->generator->generate($event->jenisTagihan, 'event', 'BillTypeActivated');
    }
}
```

- [ ] **Step 4: Ubah isi `app/Domains/Keuangan/Listeners/GenerateTagihanForNewStudent.php`**

Timpa seluruh isi dengan:

```php
<?php
// app/Domains/Keuangan/Listeners/GenerateTagihanForNewStudent.php

namespace App\Domains\Keuangan\Listeners;

use App\Events\StudentCreated;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Scopes\TenantScope;
use App\Domains\Keuangan\Services\JenisTagihanSasaranMatcher;
use App\Domains\Keuangan\Services\TagihanBillingGenerator;

class GenerateTagihanForNewStudent
{
    public function __construct(
        private readonly JenisTagihanSasaranMatcher $matcher,
        private readonly TagihanBillingGenerator $generator,
    ) {
    }

    public function handle(StudentCreated $event): void
    {
        $siswa = $event->siswa;

        JenisTagihan::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $siswa->lembaga_id)
            ->where('is_active', true)
            ->whereNotIn('kategori', ['pendaftaran', 'daftar_ulang'])
            ->get()
            ->each(function (JenisTagihan $jenisTagihan) use ($siswa) {
                if ($this->matcher->siswaMatchesJenisTagihan($siswa, $jenisTagihan)) {
                    $this->generator->generateForSiswaViaEvent($siswa, $jenisTagihan, 'StudentCreated');
                }
            });
    }
}
```

Catatan: `StudentCreated` TETAP `use App\Events\StudentCreated;` — event itu BUKAN milik Keuangan, tidak pindah.

- [ ] **Step 5: Ubah isi `app/Domains/Keuangan/Listeners/GenerateTagihanForUpdatedClass.php`**

Timpa seluruh isi dengan:

```php
<?php
// app/Domains/Keuangan/Listeners/GenerateTagihanForUpdatedClass.php

namespace App\Domains\Keuangan\Listeners;

use App\Events\StudentUpdatedClass;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Scopes\TenantScope;
use App\Domains\Keuangan\Services\JenisTagihanSasaranMatcher;
use App\Domains\Keuangan\Services\TagihanBillingGenerator;

class GenerateTagihanForUpdatedClass
{
    public function __construct(
        private readonly JenisTagihanSasaranMatcher $matcher,
        private readonly TagihanBillingGenerator $generator,
    ) {
    }

    public function handle(StudentUpdatedClass $event): void
    {
        $siswa = $event->siswa;

        JenisTagihan::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $siswa->lembaga_id)
            ->where('is_active', true)
            ->whereNotIn('kategori', ['pendaftaran', 'daftar_ulang'])
            ->get()
            ->each(function (JenisTagihan $jenisTagihan) use ($siswa) {
                if ($this->matcher->siswaMatchesJenisTagihan($siswa, $jenisTagihan)) {
                    $this->generator->generateForSiswaViaEvent($siswa, $jenisTagihan, 'StudentUpdatedClass');
                }
            });
    }
}
```

- [ ] **Step 6: Update `UpdateJenisTagihanAction.php` (SP1) untuk pakai event namespace baru**

Baca `app/Domains/Keuangan/Actions/JenisTagihan/UpdateJenisTagihanAction.php`, ganti baris:
```php
use App\Events\BillTypeActivated;
```
menjadi:
```php
use App\Domains\Keuangan\Events\BillTypeActivated;
```
Tidak ada perubahan lain di file ini.

- [ ] **Step 7: Grep ulang untuk consumer lain**

```bash
grep -rln "App\\\\Events\\\\BillTypeActivated\|App\\\\Listeners\\\\GenerateTagihanForActivatedBillType\|App\\\\Listeners\\\\GenerateTagihanForNewStudent\|App\\\\Listeners\\\\GenerateTagihanForUpdatedClass" --include="*.php" app database tests
```

Kalau ada file lain di luar yang sudah ditangani Step 1-6 (misal test yang instansiasi listener langsung, atau `event(new \App\Events\BillTypeActivated(...))` inline FQCN di tempat lain), update `use`/FQCN-nya ke `App\Domains\Keuangan\Events\BillTypeActivated` / `App\Domains\Keuangan\Listeners\{Nama}`.

- [ ] **Step 8: Verifikasi tidak ada referensi lama tersisa**

```bash
grep -rln "use App\\\\Events\\\\BillTypeActivated;\|use App\\\\Listeners\\\\GenerateTagihanForActivatedBillType;\|use App\\\\Listeners\\\\GenerateTagihanForNewStudent;\|use App\\\\Listeners\\\\GenerateTagihanForUpdatedClass;" --include="*.php" app database tests
```
Expected: kosong.

```bash
ls app/Events/BillTypeActivated.php app/Listeners/GenerateTagihanForActivatedBillType.php app/Listeners/GenerateTagihanForNewStudent.php app/Listeners/GenerateTagihanForUpdatedClass.php 2>&1
```
Expected: error "No such file or directory" untuk ke-4nya.

- [ ] **Step 9: Jalankan test scoped**

```bash
php artisan event:cache
php artisan test tests/Feature/Keuangan/BillTypeActivatedEventTest.php tests/Feature/Keuangan/StudentBillingEventsTest.php tests/Feature/Admin/JenisTagihanFinalReviewFixesTest.php
php artisan event:clear
```

**Catatan:** `event:cache` dijalankan sekali untuk memaksa Laravel resolve ulang daftar listener lewat auto-discovery (mendeteksi kalau ada listener yang gagal ter-registrasi karena typo namespace) — kalau command ini ERROR, itu tanda ada masalah namespace listener. `event:clear` di akhir untuk kembali ke mode auto-discovery normal (tidak meninggalkan cache di repo).

Expected: `event:cache` sukses tanpa error, semua test PASS.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah event BillTypeActivated + 3 listener generate-tagihan ke Domains\Keuangan\Events dan Listeners"
```

---

## Task 6: Cross-Scope Touch — Sisa File yang Tidak Migrasi Tapi Perlu Disentuh

**Files:**
- Modify: `app/Http/Controllers/Keuangan/DashboardController.php` (baris 9)
- Modify: `app/Http/Controllers/Keuangan/CheckoutController.php` (baris 12)
- Verifikasi: seluruh file lain dari daftar Task 1 Step 4 dan Task 2 Step 3 sudah benar-benar ter-update

**Interfaces:**
- Tidak ada file baru — task ini murni cross-scope touch + verifikasi gabungan.

- [ ] **Step 1: Update `app/Http/Controllers/Keuangan/DashboardController.php` baris 9**

Baca file, ganti baris `use App\Models\Tagihan;` → `use App\Domains\Keuangan\Models\Tagihan;`. Tidak ada perubahan lain — controller ini TIDAK dimigrasi (ditunda ke SP4, campur 3 domain).

- [ ] **Step 2: Update `app/Http/Controllers/Keuangan/CheckoutController.php` baris 12**

Baca file, ganti baris `use App\Models\Tagihan;` → `use App\Domains\Keuangan\Models\Tagihan;`. Tidak ada perubahan lain — controller ini TIDAK dimigrasi (ditunda ke SP3, subjek Pembayaran).

- [ ] **Step 3: Verifikasi gabungan — tidak ada `use App\Models\Tagihan;`/`use App\Models\BillingJobLog;` tersisa di manapun**

```bash
grep -rln "use App\\\\Models\\\\Tagihan;\|use App\\\\Models\\\\BillingJobLog;" --include="*.php" app database tests
```
Expected: KOSONG total.

- [ ] **Step 4: Verifikasi gabungan — tidak ada referensi implisit `Tagihan::class`/`BillingJobLog::class` tersisa di `app/Models/`**

```bash
grep -rn "Tagihan::class\|BillingJobLog::class" --include="*.php" app/Models
```

Tinjau hasilnya satu per satu: kalau ada `Tagihan::class` yang sebenarnya merujuk ke `TagihanItem::class`/`SkemaCicilan::class` dsb (substring match), itu BUKAN masalah — pastikan yang benar-benar `Tagihan::class` murni (bukan substring) sudah di-FQCN-kan sebagai `\App\Domains\Keuangan\Models\Tagihan::class` kalau file itu TIDAK ikut pindah.

- [ ] **Step 5: Jalankan test scoped luas**

```bash
php artisan test tests/Feature/Keuangan tests/Feature/Admin tests/Unit tests/Feature/Portal tests/Feature/Spmb tests/Feature/Console
```
Expected: semua PASS (kalau ada yang gagal dengan "Class not found", cek lagi Task 1-6 — kemungkinan ada `use` statement yang kelewat).

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): cross-scope touch use Tagihan di Keuangan\DashboardController dan CheckoutController"
```

---

## Task 7: Refactor `JenisTagihanMonitoringController` — Namespace + Action + Test Guard Baru

**Files:**
- Create: `app/Http/Controllers/Lembaga/Keuangan/JenisTagihanMonitoringController.php`
- Delete: `app/Http/Controllers/Admin/JenisTagihanMonitoringController.php`
- Create: `app/Domains/Keuangan/Actions/Tagihan/BatalkanTagihanAction.php`
- Move: `resources/views/admin/jenis-tagihan/monitoring/index.blade.php` → `resources/views/portals/lembaga/keuangan/jenis-tagihan/monitoring/index.blade.php`
- Test: tambah test baru di `tests/Feature/Admin/JenisTagihanMonitoringTest.php` yang menyerang guard urutan cek kepemilikan-sebelum-status

**Interfaces:**
- Consumes: `App\Domains\Keuangan\Models\{JenisTagihan,Tagihan}` (SP1, Task 1).
- Produces: `App\Domains\Keuangan\Actions\Tagihan\BatalkanTagihanAction` — dipakai controller baru.

Baseline kode controller (commit `8a8c475`, 81 baris) sudah dikutip lengkap — baca ulang `app/Http/Controllers/Admin/JenisTagihanMonitoringController.php` sebelum edit untuk konfirmasi persis.

- [ ] **Step 1: Buat `BatalkanTagihanAction`**

`app/Domains/Keuangan/Actions/Tagihan/BatalkanTagihanAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\Tagihan;

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\Tagihan;

class BatalkanTagihanAction
{
    /**
     * Ownership check MUST run before any business-rule check — otherwise a status-based
     * 422/403 response difference leaks whether an arbitrary cross-tenant tagihan is
     * belum_bayar, before we've verified it even belongs to this jenisTagihan. Urutan ini
     * dipertahankan PERSIS dari JenisTagihanMonitoringController::batalTagihan() lama —
     * JANGAN dibalik, pelajaran langsung dari celah guard yang hilang di review SP1.
     */
    public function execute(JenisTagihan $jenisTagihan, Tagihan $tagihan, int $userId, string $cancelReason): void
    {
        if ($tagihan->jenis_tagihan_id !== $jenisTagihan->id) {
            abort(403, 'Tagihan tidak ditemukan untuk jenis tagihan ini.');
        }

        if ($tagihan->status !== 'belum_bayar') {
            abort(422, 'Hanya tagihan dengan status belum bayar yang dapat dibatalkan.');
        }

        $tagihan->update([
            'status' => 'dibatalkan',
            'cancelled_by' => $userId,
            'cancelled_at' => now(),
            'cancel_reason' => $cancelReason,
        ]);
    }
}
```

- [ ] **Step 2: Buat controller baru di `Lembaga\Keuangan\`**

`app/Http/Controllers/Lembaga/Keuangan/JenisTagihanMonitoringController.php`:

```php
<?php

namespace App\Http\Controllers\Lembaga\Keuangan;

use App\Domains\Keuangan\Actions\Tagihan\BatalkanTagihanAction;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class JenisTagihanMonitoringController extends Controller
{
    use AuthorizesRequests;

    public function index(JenisTagihan $jenisTagihan): View
    {
        $this->authorize('jenis-tagihan.view');

        $tagihanQuery = Tagihan::where('jenis_tagihan_id', $jenisTagihan->id);

        $ringkasan = [
            'total_penerima' => (clone $tagihanQuery)->count(),
            'lunas' => (clone $tagihanQuery)->where('status', 'lunas')->count(),
            'sebagian' => (clone $tagihanQuery)->where('status', 'sebagian')->count(),
            'belum_bayar' => (clone $tagihanQuery)->where('status', 'belum_bayar')->count(),
            'dibatalkan' => (clone $tagihanQuery)->where('status', 'dibatalkan')->count(),
            'total_tertagih' => (float) (clone $tagihanQuery)->where('status', '!=', 'dibatalkan')->sum('net_amount'),
            'total_masuk' => (float) (clone $tagihanQuery)->where('status', '!=', 'dibatalkan')->sum('paid_amount'),
        ];

        $tagihanPenerima = Tagihan::with('tagihable')
            ->where('jenis_tagihan_id', $jenisTagihan->id)
            ->where('status', '!=', 'dibatalkan')
            ->paginate(15, ['*'], 'penerima_page');

        $tagihanTunggakan = Tagihan::with('tagihable')
            ->where('jenis_tagihan_id', $jenisTagihan->id)
            ->whereIn('status', ['belum_bayar', 'sebagian'])
            ->selectRaw('MAX(id) as id, tagihable_type, tagihable_id, SUM(net_amount - paid_amount) as total_tunggakan, COUNT(*) as jumlah_tunggakan')
            ->groupBy('tagihable_type', 'tagihable_id')
            ->orderByDesc('total_tunggakan')
            ->paginate(15, ['*'], 'tunggakan_page');

        return view('portals.lembaga.keuangan.jenis-tagihan.monitoring.index', [
            'jenisTagihan' => $jenisTagihan,
            'ringkasan' => $ringkasan,
            'tagihanPenerima' => $tagihanPenerima,
            'tagihanTunggakan' => $tagihanTunggakan,
        ]);
    }

    public function batalTagihan(Request $request, JenisTagihan $jenisTagihan, Tagihan $tagihan, BatalkanTagihanAction $action): RedirectResponse
    {
        $this->authorize('jenis-tagihan.edit');

        $request->validate([
            'cancel_reason' => 'required|string|max:255',
        ]);

        $action->execute($jenisTagihan, $tagihan, auth()->id(), $request->cancel_reason);

        return back()->with('success', 'Tagihan berhasil dibatalkan.');
    }
}
```

- [ ] **Step 3: Hapus controller lama**

```bash
git rm app/Http/Controllers/Admin/JenisTagihanMonitoringController.php
```

- [ ] **Step 4: Pindahkan view**

```bash
mkdir -p resources/views/portals/lembaga/keuangan/jenis-tagihan/monitoring
git mv resources/views/admin/jenis-tagihan/monitoring/index.blade.php resources/views/portals/lembaga/keuangan/jenis-tagihan/monitoring/index.blade.php
```

Tidak ada `@include` di view ini (sudah dikonfirmasi saat plan ditulis) — tidak perlu edit isi.

- [ ] **Step 5: Update `use` statement di `routes/admin/keuangan.php`**

Ganti baris:
```php
use App\Http\Controllers\Admin\JenisTagihanMonitoringController;
```
menjadi:
```php
use App\Http\Controllers\Lembaga\Keuangan\JenisTagihanMonitoringController;
```

Baris route `jenis-tagihan.monitoring.*` (baris 22-23 di baseline) TIDAK diubah.

- [ ] **Step 6: Tambah test baru yang eksplisit menyerang guard urutan cek**

Baca `tests/Feature/Admin/JenisTagihanMonitoringTest.php`, cari fungsi helper yang sudah ada untuk membuat user/lembaga (ikuti pola yang sama), lalu tambahkan test ini di akhir file (sesuaikan nama helper dengan yang benar-benar ada di file):

```php
it('returns 403 (not 422) when batalTagihan targets a tagihan belonging to a different jenis tagihan, even when that tagihan is belum_bayar', function () {
    [$user, $lembaga] = buatUserKeuanganMonitoring(); // ganti dengan nama helper yang benar-benar ada di file ini

    $jenisTagihanA = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'SPP A', 'kategori' => 'spp', 'bisa_dicicil' => false]);
    $jenisTagihanB = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'SPP B', 'kategori' => 'spp', 'bisa_dicicil' => false]);

    $tagihanMilikB = Tagihan::factory()->create([
        'jenis_tagihan_id' => $jenisTagihanB->id,
        'status' => 'belum_bayar',
    ]);

    $response = $this->actingAs($user)->post(
        route('admin.jenis-tagihan.monitoring.batal', [$jenisTagihanA, $tagihanMilikB]),
        ['cancel_reason' => 'Uji guard kepemilikan']
    );

    $response->assertForbidden();
    expect($tagihanMilikB->fresh()->status)->toBe('belum_bayar');
});
```

**Sebelum menulis test ini persis seperti di atas**: baca dulu isi `tests/Feature/Admin/JenisTagihanMonitoringTest.php` yang sudah ada, cocokkan nama helper pembuat user (`buatUserKeuanganMonitoring` di atas HANYA nama placeholder pengganti — pakai nama fungsi/pola yang BENAR-BENAR ada di file), namespace `use` yang sudah dipakai (`JenisTagihan`/`Tagihan` harus sudah `use App\Domains\Keuangan\Models\JenisTagihan;`/`use App\Domains\Keuangan\Models\Tagihan;` dari Task 1-6), dan pastikan `Tagihan::factory()` valid (Task 1 Step 2 sudah menambahkan `newFactory()`).

- [ ] **Step 7: Jalankan test scoped**

```bash
php artisan route:list --name=jenis-tagihan.monitoring
php artisan test tests/Feature/Admin/JenisTagihanMonitoringTest.php
```
Expected: `route:list` menunjukkan `Lembaga\Keuangan\JenisTagihanMonitoringController`, nama route tidak berubah. Semua test PASS termasuk test baru.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): refactor JenisTagihanMonitoringController jadi BatalkanTagihanAction, pindah ke Lembaga\Keuangan\, tambah test guard kepemilikan"
```

---

## Task 8: Ekstrak `buatSusulan()` ke `Admin\TagihanSusulanController`

**Files:**
- Create: `app/Http/Controllers/Admin/TagihanSusulanController.php`
- Modify: `app/Http/Controllers/Admin/TagihanController.php` (hapus method `buatSusulan()` — akan direfactor total di Task 9, tapi hapus method ini SEKARANG supaya Task 9 lebih bersih)
- Modify: `routes/admin/spmb.php`

**Interfaces:**
- Tidak ada Action baru — method dipindah apa adanya (bukan domain Keuangan, business logic-nya `TagihanGenerator` tetap di `app/Services/TagihanGenerator.php`, TIDAK disentuh).

- [ ] **Step 1: Buat controller baru**

`app/Http/Controllers/Admin/TagihanSusulanController.php`:

```php
<?php
// app/Http/Controllers/Admin/TagihanSusulanController.php

namespace App\Http\Controllers\Admin;

use App\Models\Pendaftaran;
use App\Services\TagihanGenerator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class TagihanSusulanController extends BaseController
{
    use AuthorizesRequests;

    private function lembagaId(Request $request): ?int
    {
        return $request->user()->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $request->user()->lembaga_id;
    }

    public function buatSusulan(Request $request, Pendaftaran $pendaftaran, TagihanGenerator $generator): RedirectResponse
    {
        $this->authorize('tagihan.buat-susulan');
        abort_unless($pendaftaran->lembaga_id === $this->lembagaId($request), 404);

        $data = $request->validate([
            'kategori' => ['required', 'in:pendaftaran,daftar_ulang'],
        ]);

        $tagihan = $generator->generate($pendaftaran, $data['kategori']);

        if (! $tagihan) {
            return back()->withErrors([
                'kategori' => 'Tagihan sudah ada, atau belum ada nominal yang dikonfigurasi untuk jalur ini.',
            ]);
        }

        return back()->with('status', 'Tagihan susulan berhasil dibuat.');
    }
}
```

- [ ] **Step 2: Hapus method `buatSusulan()` dari `app/Http/Controllers/Admin/TagihanController.php`**

Baca file, hapus method `buatSusulan()` (baris 36-54 di baseline) beserta `use App\Services\TagihanGenerator;` dan `use App\Models\Pendaftaran;` KALAU tidak dipakai method lain di file itu (cek dulu — `Pendaftaran` TIDAK dipakai method lain, `TagihanGenerator` juga TIDAK dipakai method lain, jadi kedua `use` ini ikut dihapus). Private method `lembagaId()` TETAP ADA (masih dipakai method lain di controller ini).

- [ ] **Step 3: Update `routes/admin/spmb.php`**

Ganti baris:
```php
use App\Http\Controllers\Admin\TagihanController;
```
menjadi:
```php
use App\Http\Controllers\Admin\TagihanSusulanController;
```

Ganti baris:
```php
Route::post('spmb-pendaftaran/{pendaftaran}/tagihan-susulan', [TagihanController::class, 'buatSusulan'])->name('tagihan.susulan');
```
menjadi:
```php
Route::post('spmb-pendaftaran/{pendaftaran}/tagihan-susulan', [TagihanSusulanController::class, 'buatSusulan'])->name('tagihan.susulan');
```

- [ ] **Step 4: Jalankan test scoped**

```bash
php artisan route:list --name=tagihan.susulan
php artisan test tests/Feature/Admin/TagihanSusulanTest.php
```
Expected: `route:list` menunjukkan `Admin\TagihanSusulanController`, nama route `admin.tagihan.susulan` tidak berubah. Test PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "refactor(spmb): ekstrak buatSusulan() dari TagihanController ke TagihanSusulanController terpisah, di luar domain Keuangan"
```

---

## Task 9: Refactor `Admin\TagihanController` (Minus `buatSusulan`) — Namespace + Action

**Files:**
- Create: `app/Http/Controllers/Lembaga/Keuangan/TagihanController.php`
- Delete: `app/Http/Controllers/Admin/TagihanController.php`
- Create: `app/Domains/Keuangan/Actions/Tagihan/BuatSkemaCicilanAction.php`
- Create: `app/Domains/Keuangan/Actions/Tagihan/SimpanNominalCicilanAction.php`
- Create: `app/Domains/Keuangan/Actions/Tagihan/CatatManualTagihanAction.php`
- Create: `app/Domains/Keuangan/Actions/Tagihan/CatatManualCicilanAction.php`
- Move: `resources/views/admin/tagihan/index.blade.php` → `resources/views/portals/lembaga/keuangan/tagihan/index.blade.php`
- Test: tambah test baru di `tests/Feature/Admin/TagihanIndexTest.php` (atau file test lain yang sudah ada untuk `buatSkemaCicilan`/`catatManualTagihan`) yang menyerang guard cross-lembaga

**Interfaces:**
- Consumes: `App\Domains\Keuangan\Models\{Tagihan,SkemaCicilan}`, `App\Services\PembayaranService` (TETAP di `app/Services`, TIDAK pindah).
- Produces: 4 Action baru — dipakai controller baru.

**Desain keputusan (dicatat eksplisit supaya tidak dianggap kelalaian):** guard `abort_unless($tagihan->pendaftaran->lembaga_id === $this->lembagaId($request), 404)` TETAP di CONTROLLER (bukan dipindah ke Action) — ini murni cek otorisasi akses request-scoped terhadap resource yang di-bind lewat route, sejenis dengan `$this->authorize(...)` yang juga tetap di controller di SP1. Action HANYA membungkus pemanggilan `PembayaranService` + translasi exception. **Guard ini WAJIB tetap ada persis di controller baru, tidak boleh hilang.**

Baseline kode (commit `8a8c475`) sudah dikutip lengkap di Task sebelumnya — baca ulang file sebelum edit untuk konfirmasi.

- [ ] **Step 1: Buat `BuatSkemaCicilanAction`**

`app/Domains/Keuangan/Actions/Tagihan/BuatSkemaCicilanAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\Tagihan;

use App\Domains\Keuangan\Models\Tagihan;
use App\Services\PembayaranService;

class BuatSkemaCicilanAction
{
    public function __construct(private readonly PembayaranService $service)
    {
    }

    /**
     * @throws \RuntimeException
     */
    public function execute(Tagihan $tagihan, int $jumlahTermin, string $dibuatOleh, ?int $userId): void
    {
        $this->service->buatSkemaCicilan($tagihan, $jumlahTermin, $dibuatOleh, $userId);
    }
}
```

- [ ] **Step 2: Buat `SimpanNominalCicilanAction`**

`app/Domains/Keuangan/Actions/Tagihan/SimpanNominalCicilanAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\Tagihan;

use App\Domains\Keuangan\Models\SkemaCicilan;
use App\Services\PembayaranService;

class SimpanNominalCicilanAction
{
    public function __construct(private readonly PembayaranService $service)
    {
    }

    /**
     * @param  array<int, int>  $nominalPerTermin
     *
     * @throws \InvalidArgumentException
     */
    public function execute(SkemaCicilan $skemaCicilan, array $nominalPerTermin): void
    {
        $this->service->simpanNominalManual($skemaCicilan, $nominalPerTermin);
    }
}
```

- [ ] **Step 3: Buat `CatatManualTagihanAction`**

`app/Domains/Keuangan/Actions/Tagihan/CatatManualTagihanAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\Tagihan;

use App\Domains\Keuangan\Models\Tagihan;
use App\Services\PembayaranService;

class CatatManualTagihanAction
{
    public function __construct(private readonly PembayaranService $service)
    {
    }

    /**
     * @throws \RuntimeException
     */
    public function execute(Tagihan $tagihan, string $dicatatOleh, int $userId): void
    {
        $this->service->catatPembayaran($tagihan, null, $dicatatOleh, null, $userId);
    }
}
```

- [ ] **Step 4: Buat `CatatManualCicilanAction`**

`app/Domains/Keuangan/Actions/Tagihan/CatatManualCicilanAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\Tagihan;

use App\Models\Cicilan;
use App\Services\PembayaranService;

class CatatManualCicilanAction
{
    public function __construct(private readonly PembayaranService $service)
    {
    }

    /**
     * @throws \RuntimeException
     */
    public function execute(Cicilan $cicilan, string $dicatatOleh, int $userId): void
    {
        $this->service->catatPembayaran(null, $cicilan, $dicatatOleh, null, $userId);
    }
}
```

- [ ] **Step 5: Buat controller baru di `Lembaga\Keuangan\`**

`app/Http/Controllers/Lembaga/Keuangan/TagihanController.php`:

```php
<?php

namespace App\Http\Controllers\Lembaga\Keuangan;

use App\Domains\Keuangan\Actions\Tagihan\BuatSkemaCicilanAction;
use App\Domains\Keuangan\Actions\Tagihan\CatatManualCicilanAction;
use App\Domains\Keuangan\Actions\Tagihan\CatatManualTagihanAction;
use App\Domains\Keuangan\Actions\Tagihan\SimpanNominalCicilanAction;
use App\Domains\Keuangan\Models\SkemaCicilan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Services\TagihanCicilanEligibilityService;
use App\Http\Controllers\Controller;
use App\Models\Cicilan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TagihanController extends Controller
{
    use AuthorizesRequests;

    /**
     * Same duplicated-per-controller pattern as PendaftaranAdminController and
     * SkPpdbController: Tagihan has no lembaga_id of its own (derived
     * transitively via pendaftaran_id), so every action here must resolve and
     * apply the acting user's effective lembaga scope manually.
     */
    private function lembagaId(Request $request): ?int
    {
        return $request->user()->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $request->user()->lembaga_id;
    }

    public function index(Request $request): View
    {
        $this->authorize('tagihan.view');

        return view('portals.lembaga.keuangan.tagihan.index', [
            'lembagaBelumDipilih' => $this->lembagaId($request) === null,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('tagihan.view');

        $lembagaId = $this->lembagaId($request);

        if ($lembagaId === null) {
            return response()->json([
                'data' => [],
                'meta' => ['current_page' => 0, 'last_page' => 0, 'per_page' => 0, 'total' => 0],
            ]);
        }

        $query = Tagihan::whereHas('pendaftaran', fn ($q) => $q->where('lembaga_id', $lembagaId))
            ->with(['pendaftaran.calonMurid']);

        if ($search = trim((string) $request->string('search'))) {
            $query->whereHas('pendaftaran', function ($q) use ($search) {
                $q->where('kode_pendaftaran', 'like', '%'.$search.'%')
                    ->orWhereHas('calonMurid', fn ($cm) => $cm->where('nama_lengkap', 'like', '%'.$search.'%'));
            });
        }

        if ($status = $request->string('status')->value()) {
            $query->where('status', $status);
        }

        if ($kategori = $request->string('kategori')->value()) {
            $query->where('kategori', $kategori);
        }

        $sortable = ['created_at', 'total_tagihan'];
        $sort = in_array($request->string('sort')->value(), $sortable, true) ? $request->string('sort')->value() : 'created_at';
        $direction = $request->string('direction')->value() === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $direction);

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->getCollection()->map(fn (Tagihan $tagihan) => [
                'id' => $tagihan->id,
                'nama_calon_murid' => $tagihan->pendaftaran->calonMurid->nama_lengkap,
                'kode_pendaftaran' => $tagihan->pendaftaran->kode_pendaftaran,
                'kategori' => $tagihan->kategori,
                'total_tagihan' => (float) $tagihan->total_tagihan,
                'status' => $tagihan->status,
                'pendaftaran_id' => $tagihan->pendaftaran_id,
            ])->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function buatSkemaCicilan(Request $request, Tagihan $tagihan, BuatSkemaCicilanAction $action, TagihanCicilanEligibilityService $eligibility): RedirectResponse
    {
        $this->authorize('cicilan.kelola');
        abort_unless($tagihan->pendaftaran->lembaga_id === $this->lembagaId($request), 404);

        $data = $request->validate([
            'jumlah_termin' => ['required', 'integer', 'min:2', 'max:'.($eligibility->maksCicilan($tagihan) ?? 2)],
        ]);

        try {
            $action->execute($tagihan, $data['jumlah_termin'], 'admin', $request->user()->id);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['jumlah_termin' => $exception->getMessage()]);
        }

        return back()->with('status', 'Skema cicilan berhasil dibuat.');
    }

    public function simpanNominalCicilan(Request $request, SkemaCicilan $skemaCicilan, SimpanNominalCicilanAction $action): RedirectResponse
    {
        $this->authorize('cicilan.kelola');
        abort_unless($skemaCicilan->tagihan->pendaftaran->lembaga_id === $this->lembagaId($request), 404);

        $data = $request->validate([
            'nominal' => ['required', 'array'],
            'nominal.*' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $action->execute($skemaCicilan, array_map('intval', $data['nominal']));
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors(['nominal' => $exception->getMessage()]);
        }

        return back()->with('status', 'Nominal cicilan berhasil diperbarui.');
    }

    public function catatManualTagihan(Request $request, Tagihan $tagihan, CatatManualTagihanAction $action): RedirectResponse
    {
        $this->authorize('pembayaran.catat-manual');
        abort_unless($tagihan->pendaftaran->lembaga_id === $this->lembagaId($request), 404);

        try {
            $action->execute($tagihan, 'admin', $request->user()->id);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['pembayaran' => $exception->getMessage()]);
        }

        return back()->with('status', 'Pembayaran berhasil dicatat.');
    }

    public function catatManualCicilan(Request $request, Cicilan $cicilan, CatatManualCicilanAction $action): RedirectResponse
    {
        $this->authorize('pembayaran.catat-manual');
        abort_unless($cicilan->skemaCicilan->tagihan->pendaftaran->lembaga_id === $this->lembagaId($request), 404);

        try {
            $action->execute($cicilan, 'admin', $request->user()->id);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['pembayaran' => $exception->getMessage()]);
        }

        return back()->with('status', 'Pembayaran termin berhasil dicatat.');
    }
}
```

- [ ] **Step 6: Hapus controller lama**

```bash
git rm app/Http/Controllers/Admin/TagihanController.php
```

- [ ] **Step 7: Pindahkan view**

```bash
mkdir -p resources/views/portals/lembaga/keuangan/tagihan
git mv resources/views/admin/tagihan/index.blade.php resources/views/portals/lembaga/keuangan/tagihan/index.blade.php
```

Tidak ada `@include` di view ini (sudah dikonfirmasi saat plan ditulis) — tidak perlu edit isi.

- [ ] **Step 8: Update `routes/admin/keuangan.php`**

Ganti baris:
```php
use App\Http\Controllers\Admin\TagihanController;
```
menjadi:
```php
use App\Http\Controllers\Lembaga\Keuangan\TagihanController;
```

Baris route `tagihan.*`/`skema-cicilan.*`/`cicilan.*` (baris 27-32 di baseline) TIDAK diubah.

- [ ] **Step 9: Tambah test baru yang eksplisit menyerang guard cross-lembaga**

Baca `tests/Feature/Admin/TagihanIndexTest.php` (atau file test `catatManualTagihan`/`buatSkemaCicilan` yang sudah ada untuk `TagihanController` — cari dengan `grep -rl "TagihanController\|admin.tagihan\." tests/Feature/Admin`), cocokkan pola helper user/lembaga yang sudah ada, lalu tambahkan test berikut (sesuaikan nama helper dengan yang benar-benar ada):

```php
it('returns 404 when catatManualTagihan targets a tagihan belonging to a different lembaga', function () {
    [$userA] = buatUserKeuanganTagihan(); // ganti dengan nama helper yang benar-benar ada di file ini
    [, $lembagaB] = buatUserKeuanganTagihan();

    $pendaftaranB = Pendaftaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $tagihanMilikB = Tagihan::factory()->create(['pendaftaran_id' => $pendaftaranB->id, 'status' => 'belum_bayar']);

    $response = $this->actingAs($userA)->post(route('admin.tagihan.catat-manual', $tagihanMilikB));

    $response->assertNotFound();
});
```

**Sebelum menulis test ini persis seperti di atas**: baca dulu isi file test yang sudah ada untuk `TagihanController`, cocokkan nama helper pembuat user+lembaga (`buatUserKeuanganTagihan` di atas HANYA placeholder), pastikan `use App\Domains\Keuangan\Models\Tagihan;` dan `use App\Models\Pendaftaran;` sudah benar di file itu.

- [ ] **Step 10: Jalankan test scoped**

```bash
grep -rl "TagihanController\|admin\.tagihan\." tests/Feature/Admin --include="*.php"
```

Jalankan SEMUA file hasil grep di atas:

```bash
php artisan test <daftar file hasil grep di atas, dipisah spasi>
```
Expected: semua PASS termasuk test baru.

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): refactor Admin\TagihanController jadi 4 Action, pindah ke Lembaga\Keuangan\, tambah test guard cross-lembaga"
```

---

## Task 10: Refactor `Keuangan\TagihanController` — Namespace + View

**Files:**
- Create: `app/Http/Controllers/Portal/Keuangan/TagihanController.php`
- Delete: `app/Http/Controllers/Keuangan/TagihanController.php`
- Move: `resources/views/keuangan/tagihan/index.blade.php` → `resources/views/portals/portal/keuangan/tagihan/index.blade.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `App\Domains\Keuangan\Models\Tagihan` (Task 1).

Baseline kode (39 baris) sudah dikutip lengkap — controller ini PURE read-only, TIDAK ada mutasi, jadi TIDAK ada Action baru (konsisten pola SP1: read-only tetap inline di controller).

- [ ] **Step 1: Buat controller baru**

`app/Http/Controllers/Portal/Keuangan/TagihanController.php`:

```php
<?php
// app/Http/Controllers/Portal/Keuangan/TagihanController.php

namespace App\Http\Controllers\Portal\Keuangan;

use App\Domains\Keuangan\Models\Tagihan;
use App\Http\Controllers\Controller;
use App\Models\Scopes\TenantScope;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TagihanController extends Controller
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
            ->with(['jenisTagihan' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
            ->orderBy('jatuh_tempo')
            ->get();

        $autoDebitEnabled = (bool) SystemSetting::getResolved('auto_debit_enabled', $activeSiswa->lembaga_id, false);

        return view('portals.portal.keuangan.tagihan.index', [
            'activeSiswa' => $activeSiswa,
            'tagihans' => $tagihans,
            'autoDebitEnabled' => $autoDebitEnabled,
        ]);
    }
}
```

Catatan: `view('keuangan.tanpa-anak')` (fallback, dipakai lintas-SP) TIDAK diubah — sesuai spec §3.6, view ini TETAP di lokasi lama sampai SP terakhir yang menyentuhnya (SP4) selesai.

- [ ] **Step 2: Hapus controller lama**

```bash
git rm app/Http/Controllers/Keuangan/TagihanController.php
```

- [ ] **Step 3: Pindahkan view**

```bash
mkdir -p resources/views/portals/portal/keuangan/tagihan
git mv resources/views/keuangan/tagihan/index.blade.php resources/views/portals/portal/keuangan/tagihan/index.blade.php
```

Tidak ada `@include` di view ini (sudah dikonfirmasi saat plan ditulis) — tidak perlu edit isi.

- [ ] **Step 4: Update `routes/web.php`**

Baca `routes/web.php`, cari baris:
```php
        Route::get('/tagihan', [\App\Http\Controllers\Keuangan\TagihanController::class, 'index'])->name('tagihan.index');
```
di dalam grup `Route::middleware([...])->prefix('keuangan')->name('keuangan.')->group(function () { ... })`, ganti jadi:
```php
        Route::get('/tagihan', [\App\Http\Controllers\Portal\Keuangan\TagihanController::class, 'index'])->name('tagihan.index');
```

Baris route lain di grup yang sama (`dashboard`, `riwayat.*`, `checkout.*`) TIDAK diubah — controller-controller itu bukan bagian SP2.

- [ ] **Step 5: Jalankan test scoped**

```bash
php artisan route:list --name=keuangan.tagihan.index
php artisan test tests/Feature/Keuangan/TagihanControllerTest.php
```
Expected: `route:list` menunjukkan `Portal\Keuangan\TagihanController`, nama route `keuangan.tagihan.index` tidak berubah. Test PASS.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah Keuangan\TagihanController ke Portal\Keuangan\, view ke portals/portal/keuangan/"
```

---

## Task 11: Verifikasi Gabungan Akhir Sebelum Handoff

**Files:**
- Tidak ada file baru — task ini murni verifikasi gate sebelum Task 12.

- [ ] **Step 1: Verifikasi gabungan — tidak ada referensi namespace lama tersisa**

```bash
grep -rln "use App\\\\Models\\\\Tagihan;\|use App\\\\Models\\\\BillingJobLog;\|use App\\\\Services\\\\TagihanBillingGenerator;\|use App\\\\Events\\\\BillTypeActivated;\|use App\\\\Listeners\\\\GenerateTagihanForActivatedBillType;\|use App\\\\Listeners\\\\GenerateTagihanForNewStudent;\|use App\\\\Listeners\\\\GenerateTagihanForUpdatedClass;" --include="*.php" app database tests
```
Expected: KOSONG total.

- [ ] **Step 2: Verifikasi file lama sudah tidak ada**

```bash
ls app/Models/Tagihan.php app/Models/BillingJobLog.php app/Services/TagihanBillingGenerator.php app/Events/BillTypeActivated.php app/Listeners/GenerateTagihanForActivatedBillType.php app/Listeners/GenerateTagihanForNewStudent.php app/Listeners/GenerateTagihanForUpdatedClass.php app/Http/Controllers/Admin/JenisTagihanMonitoringController.php app/Http/Controllers/Admin/TagihanController.php app/Http/Controllers/Keuangan/TagihanController.php 2>&1
```
Expected: error "No such file or directory" untuk semuanya.

- [ ] **Step 3: Verifikasi route name tidak berubah**

```bash
php artisan route:list --name=jenis-tagihan.monitoring
php artisan route:list --name=admin.tagihan
php artisan route:list --name=keuangan.tagihan
php artisan route:list --name=tagihan.susulan
```
Bandingkan dengan daftar route sebelum migrasi (nama harus identik, Action target harus mengarah ke namespace baru).

- [ ] **Step 4: Kalau ada temuan yang tidak sesuai Step 1-3, STOP dan perbaiki sebelum lanjut Task 12.**

Tidak ada commit di task ini — murni gate verifikasi.

---

## Task 12: Verifikasi Akhir + Handoff Log

**Files:**
- Create: `.agents/logs/2026-08-24-refactor-02-keuangan-sp2-alur-tagihan-inti.md`

- [ ] **Step 1: Jalankan test scoped gabungan luas**

```bash
php artisan test tests/Feature/Keuangan tests/Feature/Admin tests/Unit tests/Feature/Spmb tests/Feature/Portal tests/Feature/Console
```
Catat jumlah pasti passed/failed. Flaky yang sudah dikenal (hari-Minggu terkait hari libur mingguan SDM) — kalau itu SATU-SATUNYA yang gagal, jalankan ulang sendirian untuk konfirmasi, BUKAN regresi dari sub-project ini.

- [ ] **Step 2: Minta izin user untuk full test suite**

Tanya ke user: "Task 1-11 selesai, test scoped semua hijau. Boleh saya jalankan full test suite (`php artisan test`) untuk verifikasi akhir?" — TUNGGU jawaban eksplisit. JANGAN jalankan otomatis tanpa izin.

- [ ] **Step 3: Jalankan full suite (HANYA setelah izin didapat)**

```bash
php artisan test
```
Catat angka PASTI passed/failed/duration.

- [ ] **Step 4: Tulis handoff log**

Buat `.agents/logs/2026-08-24-refactor-02-keuangan-sp2-alur-tagihan-inti.md` (Bahasa Indonesia): ringkasan tiap task (1-11) dengan commit hash, hasil test dengan angka PASTI dari Step 1 dan Step 3 (JANGAN dicampur/disatukan), hasil Task 11 (harus "kosong"/sesuai). **WAJIB sebutkan eksplisit** kalau ada file di luar daftar yang disebutkan plan yang ternyata perlu disentuh (jangan diam-diam seperti insiden sebelumnya di project ini) — laporkan sebagai temuan terpisah di log. **WAJIB sebutkan eksplisit** apakah kedua test guard baru (Task 7 Step 6, Task 9 Step 9) berhasil ditulis persis seperti di plan atau disesuaikan — dan kalau disesuaikan, sebutkan apa yang berubah dan kenapa.

- [ ] **Step 5: Update `.agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md` §6**

Tambahkan baris baru di tabel Sub-Task untuk "Migrasi Domain Keuangan Sub-project 2 (Alur Tagihan Inti + Portal Tampilan)" dengan link ke spec/plan/log, status 🟢 SELESAI.

- [ ] **Step 6: Commit**

```bash
git add .agents/logs/2026-08-24-refactor-02-keuangan-sp2-alur-tagihan-inti.md .agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md
git commit -m "docs(refactor): handoff log migrasi domain Keuangan Sub-project 2"
```
