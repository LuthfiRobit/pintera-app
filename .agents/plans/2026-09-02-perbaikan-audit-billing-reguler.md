# Perbaikan Audit Billing Reguler Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tutup 9 temuan audit (3 Critical, 3 Important, 3 Minor) di alur billing reguler (non-PPDB) — Keuangan module, branch `keuangan-v2`.

**Architecture:** Setiap task berdiri sendiri (independen secara file), tapi task 3 (B.9) dan task 4 (B.10) baru masuk akal SETELAH task 1 (B.3) dan task 2 (B.2) selesai — keduanya adalah "penguat guard" yang membuat `perlu_ditinjau_ulang` benar-benar tidak bisa dilewati, dan task 4 adalah "jalan keluar resmi" dari guard yang sekarang lebih ketat itu. Task 5-9 independen satu sama lain dan terhadap task 1-4.

**Tech Stack:** Laravel 12, PHP 8.3, Pest v4, MySQL 8.0.30.

## Global Constraints

- **`TagihanStatusResolver::resolve(float $paidAmount, float $netAmount, string $currentStatus): string`** tetap satu-satunya sumber kebenaran transisi status Tagihan (dipakai `PaymentAllocationService`, `RecalculateTagihanNominalAction`, dan sekarang juga `AutoAllocationEngine` di task 7) — jangan tulis ulang logic if/elseif manapun di file baru/yang diubah.
- **Tidak ada perubahan pada jalur PPDB** — semua task ini murni billing reguler (Siswa-tagihable). Jangan sentuh `TagihanController` (admin, PPDB), `PembayaranController` (admin, PPDB), atau apapun di bawah `/portal`.
- **Tidak membangun sistem refund apapun** — task 4 (B.10) kalau menghasilkan overpayment, status otomatis jadi `lunas` lewat `TagihanStatusResolver`, selisih dicatat implisit (`paid_amount - net_amount`), tidak ada field/tabel refund baru.
- **Guard `perlu_ditinjau_ulang` harus konsisten** — begitu task 1 & 2 selesai, semua jalur pembayaran (wallet, QRIS, manual, cash, auto-debit) harus menolak/skip tagihan yang di-flag. Task 3 & 4 dikerjakan SETELAH task 1 & 2, tidak sebelumnya.
- **Kode di task ini WAJIB dites dengan `RolePermissionSeeder`** (`Database\Seeders\RolePermissionSeeder`, memanggil `PermissionSeeder`+`RoleSeeder`) untuk semua test yang butuh permission — pola yang sudah dipakai di puluhan file test lain.

---

## Task 1: `AutoAllocationEngine` & `SkipAlertResolver` exclude tagihan yang ditinjau (B.3)

**Files:**
- Modify: `app/Domains/Keuangan/Services/AutoAllocationEngine.php:35-43`
- Modify: `app/Domains/Keuangan/Services/SkipAlertResolver.php:38-46`
- Test: `tests/Feature/Keuangan/AutoAllocationEngineTest.php`
- Test: `tests/Feature/Keuangan/SkipAlertResolverPerluDitinjauTest.php` (baru)

**Interfaces:**
- Tidak ada interface baru — murni menambah 1 klausa `where` di query yang sudah ada di kedua file.

- [ ] **Step 1: Write the failing test — AutoAllocationEngine**

Tambahkan ke `tests/Feature/Keuangan/AutoAllocationEngineTest.php` (di akhir file, sebelum penutup):

```php
it('does not allocate to a tagihan flagged perlu_ditinjau_ulang even when it has top priority', function () {
    $siswa = Siswa::factory()->create();
    $wallet = $siswa->wallet;
    $wallet->update(['balance' => 100000]);

    $jenisFlagged = JenisTagihan::factory()->create(['priority_score' => 1]);
    $jenisNormal = JenisTagihan::factory()->create(['priority_score' => 2]);

    $tagihanFlagged = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenisFlagged->id,
        'total_tagihan' => 100000, 'net_amount' => 100000, 'paid_amount' => 0, 'status' => 'belum_bayar',
        'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'contoh',
    ]);

    $tagihanNormal = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenisNormal->id,
        'total_tagihan' => 100000, 'net_amount' => 100000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);

    $engine = app(AutoAllocationEngine::class);
    $engine->run($wallet);

    $tagihanFlagged->refresh();
    $tagihanNormal->refresh();

    expect($tagihanFlagged->status)->toBe('belum_bayar');
    expect($tagihanFlagged->paid_amount)->toEqual(0);
    expect($tagihanNormal->status)->toBe('lunas');
    expect($tagihanNormal->paid_amount)->toEqual(100000);
});
```

- [ ] **Step 2: Write the failing test — SkipAlertResolver**

```php
<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Services\SkipAlertResolver;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('does not treat a perlu_ditinjau_ulang tagihan as a skip candidate at all', function () {
    $siswa = Siswa::factory()->create();
    $siswa->wallet->update(['balance' => 0]);

    $jenis = JenisTagihan::factory()->create(['priority_score' => 1]);
    Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'total_tagihan' => 100000, 'net_amount' => 100000, 'paid_amount' => 0, 'status' => 'belum_bayar',
        'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'contoh',
    ]);

    $result = app(SkipAlertResolver::class)->resolve($siswa);

    expect($result)->toBeNull();
});

it('still surfaces a normal (non-flagged) tagihan as a skip candidate', function () {
    $siswa = Siswa::factory()->create();
    $siswa->wallet->update(['balance' => 0]);

    $jenis = JenisTagihan::factory()->create(['priority_score' => 1, 'nama' => 'SPP']);
    Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'total_tagihan' => 100000, 'net_amount' => 100000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);

    $result = app(SkipAlertResolver::class)->resolve($siswa);

    expect($result)->not->toBeNull();
    expect($result['selisih'])->toBe(100000.0);
});
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `php artisan test --filter="AutoAllocationEngineTest|SkipAlertResolverPerluDitinjauTest"`
Expected: FAIL (tagihan flagged masih ikut dibayar/dihitung sebagai kandidat skip)

- [ ] **Step 4: Fix `AutoAllocationEngine`**

Di `app/Domains/Keuangan/Services/AutoAllocationEngine.php`, baris 37, ubah:
```php
->whereIn('tagihan.status', ['belum_bayar', 'sebagian'])
```
jadi:
```php
->whereIn('tagihan.status', ['belum_bayar', 'sebagian'])
->where('tagihan.perlu_ditinjau_ulang', false)
```

- [ ] **Step 5: Fix `SkipAlertResolver`**

Di `app/Domains/Keuangan/Services/SkipAlertResolver.php`, baris 41, ubah:
```php
->whereIn('tagihan.status', ['belum_bayar', 'sebagian'])
```
jadi:
```php
->whereIn('tagihan.status', ['belum_bayar', 'sebagian'])
->where('tagihan.perlu_ditinjau_ulang', false)
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter="AutoAllocationEngineTest|SkipAlertResolverPerluDitinjauTest"`
Expected: PASS (semua test, termasuk 4 test lama di `AutoAllocationEngineTest.php` yang harus tetap hijau)

- [ ] **Step 7: Commit**

```bash
git add app/Domains/Keuangan/Services/AutoAllocationEngine.php app/Domains/Keuangan/Services/SkipAlertResolver.php tests/Feature/Keuangan/AutoAllocationEngineTest.php tests/Feature/Keuangan/SkipAlertResolverPerluDitinjauTest.php
git commit -m "fix(keuangan): exclude perlu_ditinjau_ulang tagihan from auto-debit allocation and skip-alert"
```

---

## Task 2: `PaymentService` guard `perlu_ditinjau_ulang` di titik commit (B.2)

**Files:**
- Modify: `app/Domains/Keuangan/Services/PaymentService.php:196-240` (`createWalletPayment`), `:264-271` (`guardAgainstInvalidTagihan`)
- Test: `tests/Feature/Keuangan/PaymentServiceWalletPaymentTest.php`
- Test: `tests/Feature/Keuangan/PaymentServiceGuardPerluDitinjauTest.php` (baru)

**Interfaces:**
- Consumes: tidak ada dependency baru.
- Produces: `guardAgainstInvalidTagihan()` sekarang juga melempar `PaymentException` untuk tagihan `perlu_ditinjau_ulang=true` — dipakai oleh `createQrisPayment`, `createManualPayment`, `createWalletPayment`, `createCashPayment` (task lain TIDAK perlu menyesuaikan apapun, perilaku ini otomatis berlaku ke semua caller).

- [ ] **Step 1: Write the failing test — guard umum (dipakai semua metode pembayaran)**

```php
<?php

use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Services\PaymentService;
use App\Exceptions\PaymentException;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.bri.gateway' => 'mock']);
    $this->app->forgetInstance(\App\Domains\Keuangan\Contracts\PaymentGatewayInterface::class);
});

it('rejects wallet payment for a tagihan flagged perlu_ditinjau_ulang', function () {
    $siswa = Siswa::factory()->create();
    $siswa->wallet->update(['balance' => 100000]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class,
        'status' => 'belum_bayar', 'total_tagihan' => 60000, 'net_amount' => 60000, 'paid_amount' => 0,
        'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'contoh',
    ]);

    expect(fn () => app(PaymentService::class)->createWalletPayment($siswa, collect([$tagihan])))
        ->toThrow(PaymentException::class);

    $siswa->wallet->refresh();
    expect((float) $siswa->wallet->balance)->toBe(100000.0);
});

it('rejects manual transfer payment for a tagihan flagged perlu_ditinjau_ulang', function () {
    $siswa = Siswa::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class,
        'status' => 'belum_bayar', 'total_tagihan' => 60000, 'net_amount' => 60000, 'paid_amount' => 0,
        'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'contoh',
    ]);

    expect(fn () => app(PaymentService::class)->createManualPayment($siswa, collect([$tagihan]), [
        'amount' => 60000, 'transfer_proof_path' => 'bukti-transfer/contoh.jpg',
        'bank_origin' => 'BCA', 'transfer_date' => now()->toDateString(), 'requested_by' => null,
    ]))->toThrow(PaymentException::class);

    expect(\App\Domains\Keuangan\Models\ManualPaymentRequest::count())->toBe(0);
});

it('rejects a race where the tagihan gets flagged after checkout page load but before wallet-payment commit', function () {
    $siswa = Siswa::factory()->create();
    $siswa->wallet->update(['balance' => 100000]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class,
        'status' => 'belum_bayar', 'total_tagihan' => 60000, 'net_amount' => 60000, 'paid_amount' => 0,
    ]);

    // Simulasikan CheckoutController::create() memuat koleksi tagihan (belum di-flag),
    // lalu SEBELUM parent submit, admin memicu recalc yang men-flag tagihan ini.
    $staleTagihans = collect([$tagihan]);
    $tagihan->update(['perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'contoh']);

    expect(fn () => app(PaymentService::class)->createWalletPayment($siswa, $staleTagihans))
        ->toThrow(PaymentException::class);

    $siswa->wallet->refresh();
    expect((float) $siswa->wallet->balance)->toBe(100000.0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PaymentServiceGuardPerluDitinjauTest`
Expected: FAIL (pembayaran berhasil dibuat, seharusnya ditolak)

- [ ] **Step 3: Fix `guardAgainstInvalidTagihan()`**

Di `app/Domains/Keuangan/Services/PaymentService.php`, ganti method (baris 264-271):
```php
protected function guardAgainstInvalidTagihan(Collection $tagihans): void
{
    foreach ($tagihans as $tagihan) {
        if (in_array($tagihan->status, ['dibatalkan', 'lunas'])) {
            throw new PaymentException('Terdapat tagihan yang sudah dibatalkan atau lunas.');
        }
    }
}
```
jadi:
```php
protected function guardAgainstInvalidTagihan(Collection $tagihans): void
{
    foreach ($tagihans as $tagihan) {
        if (in_array($tagihan->status, ['dibatalkan', 'lunas']) || $tagihan->perlu_ditinjau_ulang) {
            throw new PaymentException('Terdapat tagihan yang sudah dibatalkan, lunas, atau sedang ditinjau ulang.');
        }
    }
}
```

- [ ] **Step 4: Fix re-fetch query di `createWalletPayment()`**

Di baris 215-217, ubah:
```php
$tagihans = Tagihan::whereIn('id', $tagihanIds)
    ->whereIn('status', ['belum_bayar', 'sebagian'])
    ->get();
```
jadi:
```php
$tagihans = Tagihan::whereIn('id', $tagihanIds)
    ->whereIn('status', ['belum_bayar', 'sebagian'])
    ->where('perlu_ditinjau_ulang', false)
    ->get();
```

(Baris `if ($tagihans->count() !== $tagihanIds->count())` sesudahnya SUDAH menangkap penurunan jumlah ini secara otomatis dan melempar `PaymentException` — tidak perlu diubah.)

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=PaymentServiceGuardPerluDitinjauTest`
Expected: PASS (3 test)

- [ ] **Step 6: Run existing regression**

Run: `php artisan test --filter='PaymentServiceWalletPaymentTest|PaymentServiceTest|PaymentServiceManualTopupTest|PaymentServiceBundledTopupTest|CheckoutController'`
Expected: PASS, unchanged.

- [ ] **Step 7: Commit**

```bash
git add app/Domains/Keuangan/Services/PaymentService.php tests/Feature/Keuangan/PaymentServiceGuardPerluDitinjauTest.php
git commit -m "fix(keuangan): reject payments against tagihan flagged perlu_ditinjau_ulang at commit time"
```

---

## Task 3: `BatalkanTagihanAction` tolak pembatalan tagihan yang ditinjau (B.9)

**Files:**
- Modify: `app/Domains/Keuangan/Actions/Tagihan/BatalkanTagihanAction.php`
- Test: `tests/Feature/Admin/JenisTagihanMonitoringTest.php`

**Interfaces:**
- Tidak ada perubahan signature — `execute(JenisTagihan $jenisTagihan, Tagihan $tagihan, int $userId, string $cancelReason): void` tetap sama.

- [ ] **Step 1: Write the failing test**

Tambahkan ke `tests/Feature/Admin/JenisTagihanMonitoringTest.php`:

```php
it('rejects cancelling a belum_bayar tagihan that is flagged perlu_ditinjau_ulang', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);

    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $tagihan = Tagihan::factory()->create([
        'jenis_tagihan_id' => $jenisTagihan->id, 'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class,
        'status' => 'belum_bayar', 'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'contoh',
    ]);

    $this->actingAs($user)
        ->post(route('admin.jenis-tagihan.monitoring.batal-tagihan', [$jenisTagihan, $tagihan]), ['cancel_reason' => 'Salah input'])
        ->assertStatus(422);

    expect($tagihan->fresh()->status)->toBe('belum_bayar');
});
```

Cek dulu nama route yang benar dengan `grep -n "batal-tagihan\|batalTagihan" routes/admin/keuangan.php` sebelum menulis test ini — kalau nama route berbeda dari yang ditulis di atas, sesuaikan.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=JenisTagihanMonitoringTest`
Expected: FAIL (tagihan berhasil dibatalkan meski sedang ditinjau)

- [ ] **Step 3: Fix `BatalkanTagihanAction`**

Di `app/Domains/Keuangan/Actions/Tagihan/BatalkanTagihanAction.php`, tambahkan pengecekan SETELAH cek status (urutan guard existing — ownership dulu, business rule sesudah — JANGAN dibalik):

```php
public function execute(JenisTagihan $jenisTagihan, Tagihan $tagihan, int $userId, string $cancelReason): void
{
    if ($tagihan->jenis_tagihan_id !== $jenisTagihan->id) {
        abort(403, 'Tagihan tidak ditemukan untuk jenis tagihan ini.');
    }

    if ($tagihan->status !== 'belum_bayar') {
        abort(422, 'Hanya tagihan dengan status belum bayar yang dapat dibatalkan.');
    }

    if ($tagihan->perlu_ditinjau_ulang) {
        abort(422, 'Tagihan ini sedang ditinjau ulang, selesaikan peninjauannya dulu sebelum membatalkan.');
    }

    $tagihan->update([
        'status' => 'dibatalkan',
        'cancelled_by' => $userId,
        'cancelled_at' => now(),
        'cancel_reason' => $cancelReason,
    ]);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=JenisTagihanMonitoringTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Keuangan/Actions/Tagihan/BatalkanTagihanAction.php tests/Feature/Admin/JenisTagihanMonitoringTest.php
git commit -m "fix(keuangan): reject cancelling a tagihan flagged perlu_ditinjau_ulang"
```

---

## Task 4: Aksi Koreksi Manual Nominal untuk Tagihan Perlu Ditinjau (B.10)

**Files:**
- Modify: `database/seeders/PermissionSeeder.php:49`
- Modify: `database/seeders/RoleSeeder.php:75`
- Create: `app/Domains/Keuangan/Actions/Tagihan/KoreksiNominalTagihanAction.php`
- Modify: `app/Http/Controllers/Lembaga/Keuangan/TagihanController.php` (tambah method `koreksiNominal`)
- Modify: `routes/admin/keuangan.php`
- Modify: `resources/views/portals/lembaga/keuangan/tagihan/perlu-ditinjau.blade.php`
- Test: `tests/Feature/Keuangan/KoreksiNominalTagihanActionTest.php`

**Interfaces:**
- Consumes: `TagihanStatusResolver::resolve(float $paidAmount, float $netAmount, string $currentStatus): string` (sudah ada).
- Produces: `KoreksiNominalTagihanAction::execute(Tagihan $tagihan, float $totalTagihanBaru, float $discountAmountBaru): void`. Route `POST admin/tagihan/{tagihan}/koreksi-nominal` bernama `admin.tagihan.koreksi-nominal`. Permission baru `tagihan.edit`.

- [ ] **Step 1: Tambah permission `tagihan.edit`**

Di `database/seeders/PermissionSeeder.php:49`, ubah:
```php
'tagihan.view', 'tagihan.buat-susulan',
```
jadi:
```php
'tagihan.view', 'tagihan.buat-susulan', 'tagihan.edit',
```

Di `database/seeders/RoleSeeder.php:75`, ubah:
```php
'tagihan.view', 'tagihan.buat-susulan',
```
jadi:
```php
'tagihan.view', 'tagihan.buat-susulan', 'tagihan.edit',
```

- [ ] **Step 2: Write the failing tests untuk Action**

```php
<?php

use App\Domains\Keuangan\Actions\Tagihan\KoreksiNominalTagihanAction;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('applies corrected nominal and clears the flag when net_amount stays above paid_amount', function () {
    $tagihan = Tagihan::factory()->create([
        'total_tagihan' => 500000, 'discount_amount' => 0, 'net_amount' => 500000, 'paid_amount' => 100000,
        'status' => 'sebagian', 'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'contoh',
    ]);

    app(KoreksiNominalTagihanAction::class)->execute($tagihan, 500000, 100000);

    $fresh = $tagihan->fresh();
    expect((float) $fresh->total_tagihan)->toBe(500000.0);
    expect((float) $fresh->discount_amount)->toBe(100000.0);
    expect((float) $fresh->net_amount)->toBe(400000.0);
    expect($fresh->status)->toBe('sebagian');
    expect($fresh->perlu_ditinjau_ulang)->toBeFalse();
    expect($fresh->alasan_perlu_ditinjau)->toBeNull();
});

it('sets status to lunas automatically when the correction results in an overpayment', function () {
    $tagihan = Tagihan::factory()->create([
        'total_tagihan' => 500000, 'discount_amount' => 0, 'net_amount' => 500000, 'paid_amount' => 500000,
        'status' => 'sebagian', 'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'contoh',
    ]);

    // Koreksi: keringanan baru membuat net_amount seharusnya cuma 400.000, padahal sudah dibayar 500.000.
    app(KoreksiNominalTagihanAction::class)->execute($tagihan, 500000, 100000);

    $fresh = $tagihan->fresh();
    expect((float) $fresh->net_amount)->toBe(400000.0);
    expect($fresh->status)->toBe('lunas'); // TagihanStatusResolver: paid_amount(500rb) >= net_amount(400rb)
    expect($fresh->perlu_ditinjau_ulang)->toBeFalse();
});

it('rejects correcting a tagihan that is not currently flagged', function () {
    $tagihan = Tagihan::factory()->create([
        'total_tagihan' => 500000, 'net_amount' => 500000, 'paid_amount' => 0,
        'status' => 'belum_bayar', 'perlu_ditinjau_ulang' => false,
    ]);

    expect(fn () => app(KoreksiNominalTagihanAction::class)->execute($tagihan, 400000, 0))
        ->toThrow(function (\Throwable $e) {
            expect($e)->toBeInstanceOf(\Symfony\Component\HttpKernel\Exception\HttpException::class);
            expect($e->getStatusCode())->toBe(422);
        });
});
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `php artisan test --filter=KoreksiNominalTagihanActionTest`
Expected: FAIL (class belum ada)

- [ ] **Step 4: Write the action**

```php
<?php

namespace App\Domains\Keuangan\Actions\Tagihan;

use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Services\TagihanStatusResolver;
use Illuminate\Support\Facades\DB;

class KoreksiNominalTagihanAction
{
    public function __construct(private readonly TagihanStatusResolver $statusResolver)
    {
    }

    public function execute(Tagihan $tagihan, float $totalTagihanBaru, float $discountAmountBaru): void
    {
        DB::transaction(function () use ($tagihan, $totalTagihanBaru, $discountAmountBaru) {
            $locked = Tagihan::lockForUpdate()->findOrFail($tagihan->id);

            if (! $locked->perlu_ditinjau_ulang) {
                abort(422, 'Tagihan ini tidak sedang ditinjau.');
            }

            $netAmountBaru = max(0, $totalTagihanBaru - $discountAmountBaru);

            $locked->total_tagihan = $totalTagihanBaru;
            $locked->discount_amount = $discountAmountBaru;
            $locked->net_amount = $netAmountBaru;
            $locked->status = $this->statusResolver->resolve((float) $locked->paid_amount, $netAmountBaru, $locked->status);
            $locked->perlu_ditinjau_ulang = false;
            $locked->alasan_perlu_ditinjau = null;
            $locked->save();
        });
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=KoreksiNominalTagihanActionTest`
Expected: PASS (3 test)

- [ ] **Step 6: Write the failing test untuk route/controller**

```php
<?php

use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Lembaga;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('lets an admin with tagihan.edit correct a flagged tagihan nominal via the route', function () {
    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);
    $tagihan = Tagihan::factory()->create([
        'lembaga_id' => $lembaga->id, 'total_tagihan' => 500000, 'net_amount' => 500000, 'paid_amount' => 100000,
        'status' => 'sebagian', 'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'contoh',
    ]);

    $response = $this->actingAs($admin)->post(route('admin.tagihan.koreksi-nominal', $tagihan), [
        'total_tagihan' => 500000, 'discount_amount' => 100000,
    ]);

    $response->assertRedirect();
    $fresh = $tagihan->fresh();
    expect((float) $fresh->net_amount)->toBe(400000.0);
    expect($fresh->perlu_ditinjau_ulang)->toBeFalse();
});

it('denies access without tagihan.edit permission', function () {
    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $tagihan = Tagihan::factory()->create(['lembaga_id' => $lembaga->id, 'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'x']);

    $this->actingAs($admin)->post(route('admin.tagihan.koreksi-nominal', $tagihan), [
        'total_tagihan' => 400000, 'discount_amount' => 0,
    ])->assertForbidden();
});

it('rejects discount_amount greater than total_tagihan at validation level', function () {
    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);
    $tagihan = Tagihan::factory()->create(['lembaga_id' => $lembaga->id, 'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'x']);

    $this->actingAs($admin)->post(route('admin.tagihan.koreksi-nominal', $tagihan), [
        'total_tagihan' => 100000, 'discount_amount' => 200000,
    ])->assertSessionHasErrors('discount_amount');
});
```

- [ ] **Step 7: Run tests to verify they fail**

Run: `php artisan test --filter=TagihanKoreksiNominalRouteTest` (atau nama file yang dipakai untuk Step 6 — simpan sebagai `tests/Feature/Keuangan/TagihanKoreksiNominalRouteTest.php`)
Expected: FAIL (route belum ada)

- [ ] **Step 8: Tambah controller method**

Di `app/Http/Controllers/Lembaga/Keuangan/TagihanController.php`, tambahkan method baru dekat `tandaiSelesaiDitinjau()` (pastikan `use App\Domains\Keuangan\Actions\Tagihan\KoreksiNominalTagihanAction;` ditambahkan di import):

```php
public function koreksiNominal(
    Request $request,
    Tagihan $tagihan,
    KoreksiNominalTagihanAction $action,
): RedirectResponse {
    $this->authorize('tagihan.edit');

    $data = $request->validate([
        'total_tagihan' => ['required', 'numeric', 'min:0'],
        'discount_amount' => ['required', 'numeric', 'min:0', 'lte:total_tagihan'],
    ]);

    $action->execute($tagihan, (float) $data['total_tagihan'], (float) $data['discount_amount']);

    return back()->with('status', 'Nominal tagihan berhasil dikoreksi.');
}
```

- [ ] **Step 9: Tambah route**

Di `routes/admin/keuangan.php`, dekat route `tagihan.selesai-ditinjau`:
```php
Route::post('tagihan/{tagihan}/koreksi-nominal', [TagihanController::class, 'koreksiNominal'])->name('tagihan.koreksi-nominal');
```

- [ ] **Step 10: Run tests to verify they pass**

Run: `php artisan test --filter=TagihanKoreksiNominalRouteTest`
Expected: PASS (3 test)

- [ ] **Step 11: Tambah form di halaman Perlu Ditinjau**

Baca `resources/views/portals/lembaga/keuangan/tagihan/perlu-ditinjau.blade.php` dulu untuk melihat struktur tabel yang sudah ada (kolom Subjek/Jenis/Nominal/Alasan/Aksi). Di kolom "Aksi" tiap baris, TAMBAHKAN (jangan hapus) form/tombol baru "Koreksi Nominal" di sebelah tombol "Selesai Ditinjau" yang sudah ada — bentuk sederhana: tombol yang toggle sebuah baris form inline (2 input: Total Tagihan, Potongan, pre-filled dengan `$tagihan->total_tagihan`/`$tagihan->discount_amount` saat ini) yang submit ke `route('admin.tagihan.koreksi-nominal', $tagihan)`. Pola toggle-inline bisa pakai Alpine `x-data="{ open: false }"` sederhana per baris, konsisten dengan pola Alpine yang sudah dipakai di file-file lain proyek ini (lihat `resources/views/portals/lembaga/keuangan/jenis-tagihan/form.blade.php` untuk gaya penulisan Alpine yang konsisten).

Contoh markup form inline (sesuaikan class Tailwind dengan yang sudah dipakai di file target agar konsisten visual):
```blade
<div x-data="{ open: false }" class="inline-block">
    <button type="button" @click="open = !open" class="inline-flex items-center gap-1.5 rounded-xl border border-ink/15 px-3 py-1.5 text-xs font-semibold text-ink hover:bg-paper transition">
        Koreksi Nominal
    </button>
    <div x-show="open" x-cloak class="mt-2 rounded-xl border border-ink/10 bg-paper/40 p-3 space-y-2 text-left">
        <form action="{{ route('admin.tagihan.koreksi-nominal', $tagihan) }}" method="POST" class="space-y-2">
            @csrf
            <div>
                <label class="block text-[10px] font-semibold text-slate mb-1">Total Tagihan</label>
                <input type="number" name="total_tagihan" value="{{ (int) $tagihan->total_tagihan }}" min="0" class="w-full rounded-lg border-ink/15 text-xs" required>
            </div>
            <div>
                <label class="block text-[10px] font-semibold text-slate mb-1">Potongan</label>
                <input type="number" name="discount_amount" value="{{ (int) $tagihan->discount_amount }}" min="0" class="w-full rounded-lg border-ink/15 text-xs" required>
            </div>
            <button type="submit" class="w-full rounded-lg bg-ink px-3 py-1.5 text-xs font-semibold text-paper hover:bg-ink/90">Terapkan Koreksi</button>
        </form>
    </div>
</div>
```

- [ ] **Step 12: Manual browser verification (wajib per CLAUDE.md project ini)**

`npm run build`, buka halaman `/admin/tagihan/perlu-ditinjau` di browser dengan minimal 1 tagihan ter-flag, uji: klik "Koreksi Nominal", ubah angka, submit, konfirmasi tagihan hilang dari daftar (flag ter-clear) dan angka baru tersimpan (cek halaman detail siswa atau riwayat).

- [ ] **Step 13: Run full regression pada file terkait**

Run: `php artisan test --filter='TagihanControllerTest|SelesaikanTinjauanTagihanActionTest|JenisTagihanMonitoringTest'`
Expected: PASS, unchanged.

- [ ] **Step 14: Commit**

```bash
git add database/seeders/PermissionSeeder.php database/seeders/RoleSeeder.php app/Domains/Keuangan/Actions/Tagihan/KoreksiNominalTagihanAction.php app/Http/Controllers/Lembaga/Keuangan/TagihanController.php routes/admin/keuangan.php resources/views/portals/lembaga/keuangan/tagihan/perlu-ditinjau.blade.php tests/Feature/Keuangan/KoreksiNominalTagihanActionTest.php tests/Feature/Keuangan/TagihanKoreksiNominalRouteTest.php
git commit -m "feat(keuangan): add manual nominal-correction action for tagihan under review"
```

---

## Task 5: Field `priority_score` di form Jenis Tagihan (B.1)

**Files:**
- Modify: `app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php:488-496` (`baseRules()`)
- Modify: `resources/views/portals/lembaga/keuangan/jenis-tagihan/form.blade.php`
- Test: `tests/Feature/Admin/JenisTagihanFormTest.php`

**Interfaces:**
- Tidak ada perubahan ke `JenisTagihanData`/`CreateJenisTagihanAction`/`UpdateJenisTagihanAction` — keduanya sudah `array_merge($data->attributes, [...])` lalu `JenisTagihan::create()/update($attributes)`, dan `priority_score` sudah `fillable` di model (`app/Domains/Keuangan/Models/JenisTagihan.php:37`). Field yang lolos validasi `baseRules()` otomatis ikut tersimpan tanpa perubahan lain.

- [ ] **Step 1: Write the failing test**

Tambahkan ke `tests/Feature/Admin/JenisTagihanFormTest.php`:

```php
it('accepts and persists priority_score on create', function () {
    [$user, $lembaga] = buatUserKeuangan();

    $response = $this->actingAs($user)->post(route('admin.jenis-tagihan.store'), [
        'nama' => 'SPP Bulanan', 'kategori' => 'spp', 'bisa_dicicil' => false,
        'mode' => 'otomatis', 'tipe' => 'bulanan', 'priority_score' => 1,
    ]);

    $response->assertRedirect();
    $jenisTagihan = \App\Domains\Keuangan\Models\JenisTagihan::where('nama', 'SPP Bulanan')->first();
    expect($jenisTagihan->priority_score)->toBe(1);
});

it('accepts priority_score being left empty (nullable)', function () {
    [$user, $lembaga] = buatUserKeuangan();

    $response = $this->actingAs($user)->post(route('admin.jenis-tagihan.store'), [
        'nama' => 'SPP Bulanan Tanpa Prioritas', 'kategori' => 'spp', 'bisa_dicicil' => false,
        'mode' => 'otomatis', 'tipe' => 'bulanan',
    ]);

    $response->assertRedirect();
    $jenisTagihan = \App\Domains\Keuangan\Models\JenisTagihan::where('nama', 'SPP Bulanan Tanpa Prioritas')->first();
    expect($jenisTagihan->priority_score)->toBeNull();
});

it('updates priority_score on an existing jenis tagihan', function () {
    [$user, $lembaga] = buatUserKeuangan();
    $jenisTagihan = \App\Domains\Keuangan\Models\JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Uji', 'kategori' => 'spp', 'bisa_dicicil' => false, 'priority_score' => 5]);

    $response = $this->actingAs($user)->put(route('admin.jenis-tagihan.update', $jenisTagihan), [
        'nama' => 'Uji', 'kategori' => 'spp', 'bisa_dicicil' => false,
        'mode' => 'manual', 'tipe' => 'sekali', 'priority_score' => 2,
    ]);

    $response->assertRedirect();
    expect($jenisTagihan->fresh()->priority_score)->toBe(2);
});
```

Cek dulu bagaimana pola request `store()`/`update()` yang lain di file test ini menuliskan `mode`/`tipe` (baca test `creates a non-ppdb jenis tagihan...` yang sudah ada, sesuaikan payload persis mengikuti pola itu kalau ada field wajib lain yang belum saya sertakan di atas).

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=JenisTagihanFormTest`
Expected: FAIL (kalau `priority_score` dikirim tapi belum ada di `baseRules()`, Laravel akan mengabaikannya secara diam-diam alih-alih error — pastikan test benar-benar gagal karena `priority_score` tidak tersimpan, bukan karena payload lain salah)

- [ ] **Step 3: Tambah validasi**

Di `app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php:495` (setelah baris `'bisa_dicicil' => ...`), tambahkan:
```php
'priority_score' => ['nullable', 'integer', 'min:0'],
```

- [ ] **Step 4: Tambah field di form**

Di `resources/views/portals/lembaga/keuangan/jenis-tagihan/form.blade.php`, cari blok "Bisa Dicicil" di sidebar "Identitas Tagihan" (`grep -n "Bisa Dicicil" resources/views/portals/lembaga/keuangan/jenis-tagihan/form.blade.php`), tambahkan field baru TEPAT SEBELUM blok "Status Aktif" (field ini relevan untuk SEMUA kategori termasuk PPDB, JANGAN dibungkus `x-if="kategoriPpdb"` atau `x-if="!kategoriPpdb"`):

```blade
<div>
    <x-input-label value="Prioritas Auto-Debit" />
    <x-text-input type="number" min="0" name="priority_score" :value="old('priority_score', $jenisTagihan?->priority_score)" placeholder="mis. 1 (lebih kecil = lebih diprioritaskan)" class="mt-1.5" />
    <p class="mt-1 text-[10px] text-gray-400">Menentukan urutan tagihan mana yang dibayar lebih dulu saat wallet siswa di-top-up dengan auto-debit aktif. Angka lebih kecil = didahulukan. Kosongkan kalau tidak perlu urutan khusus.</p>
</div>
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=JenisTagihanFormTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php resources/views/portals/lembaga/keuangan/jenis-tagihan/form.blade.php tests/Feature/Admin/JenisTagihanFormTest.php
git commit -m "feat(keuangan): expose priority_score as an editable field on the Jenis Tagihan form"
```

---

## Task 6: Badge "Sedang Ditinjau" di Jenis Tagihan Monitoring (B.8)

**Files:**
- Modify: `resources/views/portals/lembaga/keuangan/jenis-tagihan/monitoring/index.blade.php:84-92`
- Test: `tests/Feature/Admin/JenisTagihanMonitoringTest.php`

**Interfaces:**
- Tidak ada perubahan controller — `$tagihanPenerima` (dari `JenisTagihanMonitoringController.php:34-37`) sudah Eloquent model penuh, `perlu_ditinjau_ulang` sudah tersedia di tiap baris tanpa query tambahan.

- [ ] **Step 1: Write the failing test**

Tambahkan ke `tests/Feature/Admin/JenisTagihanMonitoringTest.php`:

```php
it('shows a Sedang Ditinjau badge for a flagged tagihan in the Daftar Penerima table', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    Tagihan::factory()->create([
        'jenis_tagihan_id' => $jenisTagihan->id, 'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class,
        'status' => 'belum_bayar', 'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'contoh',
    ]);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.monitoring.index', $jenisTagihan));

    $response->assertOk();
    $response->assertSee('Sedang Ditinjau', false);
});

it('does not show the Sedang Ditinjau badge for a normal (non-flagged) tagihan', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    Tagihan::factory()->create([
        'jenis_tagihan_id' => $jenisTagihan->id, 'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class,
        'status' => 'belum_bayar', 'perlu_ditinjau_ulang' => false,
    ]);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.monitoring.index', $jenisTagihan));

    $response->assertOk();
    $response->assertDontSee('Sedang Ditinjau', false);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=JenisTagihanMonitoringTest`
Expected: FAIL (badge belum ada test pertama; test kedua kemungkinan langsung PASS karena badge belum ada sama sekali — tetap tulis dulu, akan tetap PASS setelah implementasi karena kondisinya benar `false`)

- [ ] **Step 3: Tambah badge di view**

Baca `resources/views/portals/lembaga/keuangan/jenis-tagihan/monitoring/index.blade.php` baris 84-101 dulu untuk melihat markup badge status yang sudah ada persis, lalu tambahkan badge KEDUA tepat setelahnya (dalam `<td>` yang sama, jangan mengganti badge status yang sudah ada):

```blade
@if ($tagihan->perlu_ditinjau_ulang)
    <span class="ml-1 inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-700 border border-amber-200">Sedang Ditinjau</span>
@endif
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=JenisTagihanMonitoringTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/views/portals/lembaga/keuangan/jenis-tagihan/monitoring/index.blade.php tests/Feature/Admin/JenisTagihanMonitoringTest.php
git commit -m "feat(keuangan): show Sedang Ditinjau badge on Jenis Tagihan Monitoring's Daftar Penerima"
```

---

## Task 7: `AutoAllocationEngine` pakai `TagihanStatusResolver` (B.6)

**Files:**
- Modify: `app/Domains/Keuangan/Services/AutoAllocationEngine.php`
- Test: `tests/Feature/Keuangan/AutoAllocationEngineTest.php`

**Interfaces:**
- Consumes: `TagihanStatusResolver::resolve(float $paidAmount, float $netAmount, string $currentStatus): string` (sudah ada, dipakai `PaymentAllocationService`/`RecalculateTagihanNominalAction`).

- [ ] **Step 1: Write the failing test**

Tambahkan ke `tests/Feature/Keuangan/AutoAllocationEngineTest.php`:

```php
it('produces the same status as TagihanStatusResolver would for the same paid/net amounts', function () {
    $siswa = Siswa::factory()->create();
    $wallet = $siswa->wallet;
    $wallet->update(['balance' => 60000]);

    $jenis = JenisTagihan::factory()->create(['priority_score' => 1]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'total_tagihan' => 100000, 'net_amount' => 100000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);

    $engine = app(\App\Domains\Keuangan\Services\AutoAllocationEngine::class);
    $engine->run($wallet);

    $tagihan->refresh();
    $expectedStatus = app(\App\Domains\Keuangan\Services\TagihanStatusResolver::class)
        ->resolve((float) $tagihan->paid_amount, (float) $tagihan->net_amount, 'belum_bayar');

    expect($tagihan->status)->toBe($expectedStatus);
    expect($tagihan->status)->toBe('sebagian'); // sanity check nilai konkret: 60rb dari 100rb
});
```

- [ ] **Step 2: Run test to verify it fails atau langsung pass (regression guard)**

Run: `php artisan test --filter=AutoAllocationEngineTest`
Expected: kemungkinan besar SUDAH PASS di titik ini (logic inline `AutoAllocationEngine` saat ini kebetulan menghasilkan hasil sama untuk kasus normal) — test ini murni regression guard untuk Step 3, bukan bukti bug. Lanjut ke Step 3 apapun hasilnya.

- [ ] **Step 3: Refactor `AutoAllocationEngine`**

Di `app/Domains/Keuangan/Services/AutoAllocationEngine.php`, tambah dependency di constructor:
```php
public function __construct(
    private readonly NotificationDispatcher $dispatcher,
    private readonly TagihanStatusResolver $statusResolver,
) {
}
```

Tambahkan `use App\Domains\Keuangan\Services\TagihanStatusResolver;` di import (kalau namespace file yang sama, cukup referensi langsung tanpa use tambahan — cek dulu apakah sudah 1 namespace yang sama, `App\Domains\Keuangan\Services`, kalau iya TIDAK perlu `use` statement).

Ganti baris 99-104:
```php
$tagihan->paid_amount += $amount;
if ($tagihan->paid_amount >= $tagihan->net_amount) {
    $tagihan->status = 'lunas';
} else {
    $tagihan->status = 'sebagian';
}
$tagihan->save();
```
jadi:
```php
$tagihan->paid_amount += $amount;
$tagihan->status = $this->statusResolver->resolve((float) $tagihan->paid_amount, (float) $tagihan->net_amount, $tagihan->status);
$tagihan->save();
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=AutoAllocationEngineTest`
Expected: PASS (semua test termasuk 4 test lama + test baru di Task 1 & Task 7)

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Keuangan/Services/AutoAllocationEngine.php tests/Feature/Keuangan/AutoAllocationEngineTest.php
git commit -m "refactor(keuangan): make AutoAllocationEngine use TagihanStatusResolver instead of duplicated inline logic"
```

---

## Task 8: Samakan default `auto_debit_enabled` (B.5)

**Files:**
- Modify: `app/Http/Controllers/Portal/Keuangan/DashboardController.php:54`
- Modify: `app/Http/Controllers/Portal/Keuangan/TagihanController.php:32`
- Test: `tests/Feature/Keuangan/DashboardControllerTest.php`
- Test: `tests/Feature/Keuangan/TagihanControllerTest.php`

**Interfaces:**
- Tidak ada perubahan signature. Murni nilai default parameter ke-3 `SystemSetting::getResolved()`.

- [ ] **Step 1: Write the failing test — DashboardController**

Tambahkan ke `tests/Feature/Keuangan/DashboardControllerTest.php`:

```php
it('shows the auto-debit banner by default when the system setting has never been explicitly set', function () {
    [$user, , $siswa] = actingAsOrangTuaForDashboard();
    // TIDAK membuat SystemSetting apapun -- mengandalkan default getResolved() murni.

    $response = $this->actingAs($user)->get(route('keuangan.dashboard'));

    $response->assertOk();
    $response->assertSee('Sistem Auto-Debit Aktif');
});
```

- [ ] **Step 2: Write the failing test — TagihanController**

Tambahkan ke `tests/Feature/Keuangan/TagihanControllerTest.php`:

```php
it('shows the auto-debit banner by default when the system setting has never been explicitly set', function () {
    [$user] = actingAsOrangTuaForTagihan();

    $response = $this->actingAs($user)->get(route('keuangan.tagihan.index'));

    $response->assertOk();
    $response->assertSee('Sistem Auto-Debit Aktif');
});
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `php artisan test --filter='DashboardControllerTest|TagihanControllerTest'`
Expected: FAIL pada 2 test baru (banner tidak muncul karena default masih `false`)

- [ ] **Step 4: Fix kedua file**

Di `app/Http/Controllers/Portal/Keuangan/DashboardController.php:54`, ubah:
```php
$autoDebitEnabled = (bool) SystemSetting::getResolved('auto_debit_enabled', $activeSiswa->lembaga_id, false);
```
jadi:
```php
$autoDebitEnabled = (bool) SystemSetting::getResolved('auto_debit_enabled', $activeSiswa->lembaga_id, true);
```

Di `app/Http/Controllers/Portal/Keuangan/TagihanController.php:32`, ubah baris yang sama persis (default `false` → `true`).

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter='DashboardControllerTest|TagihanControllerTest'`
Expected: PASS (semua test, termasuk yang lama — kalau ada test lama yang secara eksplisit mengetes banner TIDAK muncul karena mengandalkan default lama, itu HARUS sudah membuat `SystemSetting` eksplisit dengan `value=false`, bukan mengandalkan default; kalau ternyata ada test lama yang gagal karena ini, periksa apakah test itu memang mengandalkan default lama secara implisit — kalau iya, itu konsisten dengan tujuan perbaikan ini dan test itu perlu diupdate untuk secara eksplisit set `SystemSetting` value `false` alih-alih mengandalkan default)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Portal/Keuangan/DashboardController.php app/Http/Controllers/Portal/Keuangan/TagihanController.php tests/Feature/Keuangan/DashboardControllerTest.php tests/Feature/Keuangan/TagihanControllerTest.php
git commit -m "fix(keuangan): make auto_debit_enabled default consistently true across all call sites"
```

---

## Task 9: Percepat cron rekonsiliasi QRIS/VA (B.4 — mitigasi)

**Files:**
- Modify: `routes/console.php:17`

**Interfaces:** Tidak ada — murni perubahan jadwal cron, tidak ada test otomatis yang mungkin (scheduler timing).

- [ ] **Step 1: Ubah jadwal**

Di `routes/console.php:17`, ubah:
```php
Schedule::command('finance:reconcile-payments')->hourly()->withoutOverlapping();
```
jadi:
```php
Schedule::command('finance:reconcile-payments')->everyTwoMinutes()->withoutOverlapping();
```

- [ ] **Step 2: Verifikasi manual**

Run: `php artisan schedule:list`
Expected: baris `finance:reconcile-payments` menunjukkan interval "Every 2 minutes", bukan lagi "Hourly".

- [ ] **Step 3: Commit**

```bash
git add routes/console.php
git commit -m "fix(keuangan): shorten payment reconciliation cron from hourly to every 2 minutes (QRIS/VA confirmation mitigation)"
```

**Catatan untuk handoff**: ini MITIGASI sementara, bukan perbaikan permanen. Perbaikan permanen (webhook QRIS asli dari BRI SNAP) perlu riset terpisah ke dokumentasi BRI SNAP, di luar scope plan ini — cantumkan sebagai item terbuka di handoff log.

---

## Final Step: Full Test Suite

- [ ] Run: `php artisan test --compact` (pastikan tidak ada proses `php artisan test` lain berjalan bersamaan — jalankan sendirian untuk menghindari `SQLSTATE[HY000]: 1412 Table definition has changed` akibat migrasi RefreshDatabase 2 proses bentrok)
- [ ] Expected: PASS, 0 failures.
- [ ] Run `vendor/bin/pint --dirty --format agent` dan commit hasil format terpisah kalau ada perubahan.
- [ ] Run `npm run build` untuk memastikan aset frontend (Task 4 mengubah Blade dengan Alpine) tidak error.

**Plan complete ketika full-suite run ini dan Pint pass keduanya bersih.**
