# Migrasi Domain Keuangan Sub-project 4 (TERAKHIR): Wallet & Cicilan + Rekonsiliasi Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memindahkan `Wallet`, `WalletMutasi`, `Cicilan`, `AutoAllocationEngine`, `SkipAlertResolver`, `PaymentAllocationService`, dan `Keuangan\DashboardController` ke `app/Domains/Keuangan/*` — **menuntaskan seluruh migrasi domain Keuangan**, tanpa mengubah perilaku aplikasi KECUALI 1 bug fix yang disengaja (`WalletMutasi::pembayaran()`).

**Architecture:** Model pindah fisik. Service pindah utuh (termasuk `PaymentAllocationService` yang berisi 2 method beda subjek data — keputusan sadar, tidak dipecah). Controller read-only tetap tanpa Action baru. Diakhiri audit menyeluruh membuktikan tidak ada sisa kode Keuangan di luar domain.

**Tech Stack:** Laravel 12, Pest.

## Global Constraints

- **Zero-behavior-change** — KECUALI `WalletMutasi::pembayaran()` yang MEMANG disengaja diperbaiki dari rusak (§6.2 spec) — itu satu-satunya pengecualian di seluruh SP4, WAJIB dicatat eksplisit di setiap tempat yang relevan, jangan disamakan dengan bug lain yang tidak sengaja.
- Route NAME dan PATH tidak berubah.
- **Guard `SkipAlertResolver` — 2 titik `withoutGlobalScope(TenantScope::class)` (baris 39 dan 80 baseline) WAJIB dipertahankan persis** — pelajaran dari celah HIGH review SP1, guard tenant-scope adalah titik paling rawan hilang saat refactor.
- **Gotcha DUA ARAH (temuan baru SP4, WAJIB diperhatikan di SETIAP task pemindahan file)**: selain gotcha biasa (file yang TETAP mereferensikan class yang PINDAH tanpa `use`, perlu FQCN), SP4 juga punya gotcha ARAH SEBALIKNYA — file yang PINDAH mereferensikan sibling class yang TETAP tinggal tanpa `use` (karena dulu sama-namespace). WAJIB baca ULANG isi lengkap file SEBELUM dipindah untuk mencari SEMUA class name yang dipakai tanpa `use` statement jelas, bukan cuma yang sudah didata di plan ini.
- **`newFactory()`**: `Wallet`, `Cicilan` — ADA file factory-nya (`WalletFactory.php`, `CicilanFactory.php`), WAJIB `newFactory()`. `WalletMutasi` — pakai `HasFactory` TAPI TIDAK ADA file factory-nya (`WalletMutasiFactory.php` tidak ada) — JANGAN tambahkan `newFactory()` (state ini sudah begini SEBELUM migrasi, bukan sesuatu yang perlu "diperbaiki", pertahankan apa adanya).
- **Verifikasi grep WAJIB menyisir `app database tests`**, cari string `App\Models\{ClassName}` (menangkap `use` DAN FQCN inline) — bukan cuma `use App\Models\{ClassName};` (pelajaran dari Data Induk Sempit yang terus relevan; grep sempit sudah terbukti melewatkan file yang pakai FQCN inline tanpa `use`, seperti `CatatManualCicilanAction.php`/`Tagihan.php`/`SkemaCicilan.php` untuk `Cicilan`).
- `NotificationDispatcher`, `Notifications/Finance/*`, `SystemSetting`, `TagihanGenerator`, `Admin\TagihanSusulanController`, `Portal\TagihanController`, `Keuangan\NotifikasiController` — TIDAK dipindah (keputusan final, lihat spec §10).
- Baseline kode: commit `5c71903` di branch `refactor-v1`. Kalau isi file berbeda signifikan dari yang dikutip plan, STOP, laporkan ke user.
- Tiap task: test SCOPED SEBELUM commit. Full suite HANYA task terakhir, izin eksplisit user dulu.
- **Task terakhir WAJIB audit menyeluruh** (§9 spec) membuktikan TIDAK ADA sisa kode Keuangan di luar `app/Domains/Keuangan/` — ini sub-project PENUTUP, tidak ada lagi kesempatan menunda.

---

## Task 1: Pindahkan Model `Wallet` (+ 2 Gotcha Dua Arah)

**Files:**
- Move: `app/Models/Wallet.php` → `app/Domains/Keuangan/Models/Wallet.php`
- Modify: `database/factories/WalletFactory.php` + seluruh file hasil grep `App\Models\Wallet\b` yang BUKAN bagian task lain
- Modify (gotcha arah biasa, FQCN): `app/Models/Siswa.php`
- Modify (gotcha arah sebalik, file ini SENDIRI perlu `use` baru): `app/Domains/Keuangan/Models/Wallet.php` sendiri — `SystemSetting`

**Interfaces:**
- Produces: `App\Domains\Keuangan\Models\Wallet` — dipakai seluruh task berikutnya.

- [ ] **Step 1: Pindahkan file fisik**

```bash
git mv app/Models/Wallet.php app/Domains/Keuangan/Models/Wallet.php
```

- [ ] **Step 2: Ubah isi file — namespace, `newFactory()`, tambah `use SystemSetting` (gotcha arah sebalik), `AutoAllocationEngine` jadi biasa (akan sama-namespace setelah Task 5)**

Timpa seluruh isi `app/Domains/Keuangan/Models/Wallet.php` dengan:

```php
<?php

namespace App\Domains\Keuangan\Models;

use App\Exceptions\AutoAllocationFailedException;
use App\Exceptions\InsufficientBalanceException;
use App\Models\SystemSetting;
use Database\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Wallet extends Model
{
    use HasFactory;

    protected static function newFactory(): WalletFactory
    {
        return WalletFactory::new();
    }

    protected $fillable = [
        'siswa_id',
        'balance',
        'va_number',
        'total_topup',
        'total_deducted',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Siswa::class);
    }

    public function mutasi(): HasMany
    {
        return $this->hasMany(WalletMutasi::class);
    }

    public function briVirtualAccounts(): HasMany
    {
        return $this->hasMany(BriVirtualAccount::class);
    }

    /**
     * Top-up saldo secara aman dengan lock for update.
     */
    public function topup(float $amount, ?Pembayaran $pembayaran = null, ?string $keterangan = null): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Amount top-up harus lebih dari 0");
        }

        DB::transaction(function () use ($amount, $pembayaran, $keterangan) {
            // Pessimistic lock on this row
            $wallet = self::where('id', $this->id)->lockForUpdate()->first();

            $saldoSebelum = $wallet->balance;
            $saldoSesudah = $saldoSebelum + $amount;

            $wallet->balance = $saldoSesudah;
            $wallet->total_topup += $amount;
            $wallet->save();

            $wallet->mutasi()->create([
                'pembayaran_id' => $pembayaran?->id,
                'tipe'          => 'topup',
                'amount'        => $amount,
                'saldo_sebelum' => $saldoSebelum,
                'saldo_sesudah' => $saldoSesudah,
                'keterangan'    => $keterangan ?? 'Top-up wallet',
            ]);

            // Sync current instance
            $this->refresh();
        });

        // Cek toggle auto debit dan jalankan engine jika aktif
        // (Di luar transaction agar lock wallet terlepas dulu saat proses alokasi berjalan)
        if (SystemSetting::getResolved('auto_debit_enabled', $this->siswa->lembaga_id, true)) {
            try {
                app(AutoAllocationEngine::class)->run($this);
            } catch (\Throwable $e) {
                // The balance increment above has ALREADY committed at this point --
                // wrap in a distinct exception type so callers can tell "balance was
                // credited, only allocation failed" apart from "balance never credited".
                throw new AutoAllocationFailedException(
                    'AutoAllocationEngine::run() gagal setelah saldo wallet berhasil di-topup: '.$e->getMessage(),
                    $e
                );
            }
        }
    }

    /**
     * Debit saldo secara aman dengan pengecekan strict.
     * Gunakan ini dari luar engine (membungkus dalam transaction sendiri).
     */
    public function debit(float $amount, ?Pembayaran $pembayaran = null, ?string $keterangan = null): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Amount debit harus lebih dari 0");
        }

        DB::transaction(function () use ($amount, $pembayaran, $keterangan) {
            $this->debitCore($amount, $pembayaran, $keterangan, lockRow: true);
        });
    }

    /**
     * Debit saldo tanpa membuka transaction baru.
     * Dipakai oleh AutoAllocationEngine yang sudah dalam transaction + lockForUpdate.
     * Tidak melakukan re-lock (wallet sudah dikunci oleh caller).
     *
     * @internal Jangan pakai dari luar kecuali dalam konteks DB::transaction yang sudah ada.
     */
    public function debitWithinTransaction(float $amount, ?Pembayaran $pembayaran = null, ?string $keterangan = null): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Amount debit harus lebih dari 0");
        }

        $this->debitCore($amount, $pembayaran, $keterangan, lockRow: false);
    }

    /**
     * Core debit logic, bisa dipakai dengan atau tanpa lockForUpdate.
     */
    private function debitCore(float $amount, ?Pembayaran $pembayaran, ?string $keterangan, bool $lockRow): void
    {
        // Pessimistic lock (opsional, tidak dibutuhkan jika caller sudah lock)
        $wallet = $lockRow
            ? self::where('id', $this->id)->lockForUpdate()->first()
            : self::where('id', $this->id)->first();

        if ($wallet->balance < $amount) {
            throw new InsufficientBalanceException("Saldo tidak mencukupi untuk debit sejumlah " . $amount);
        }

        $saldoSebelum = $wallet->balance;
        $saldoSesudah = $saldoSebelum - $amount;

        $wallet->balance = $saldoSesudah;
        $wallet->total_deducted += $amount;
        $wallet->save();

        $wallet->mutasi()->create([
            'pembayaran_id' => $pembayaran?->id,
            'tipe'          => 'debit',
            'amount'        => $amount,
            'saldo_sebelum' => $saldoSebelum,
            'saldo_sesudah' => $saldoSesudah,
            'keterangan'    => $keterangan ?? 'Debit wallet',
        ]);

        // Sync current instance
        $this->refresh();
    }
}
```

**Catatan gotcha dua arah**: `AutoAllocationEngine::class` di baris `app(AutoAllocationEngine::class)` sekarang TANPA `use` — ini VALID karena `AutoAllocationEngine` akan pindah ke namespace yang SAMA (`Domains\Keuangan\Services`... TUNGGU, `Wallet` ada di `Domains\Keuangan\Models` sedangkan `AutoAllocationEngine` di `Domains\Keuangan\Services` — BEDA namespace! WAJIB FQCN, BUKAN `use` biasa. Perbaiki baris itu jadi:
```php
app(\App\Domains\Keuangan\Services\AutoAllocationEngine::class)->run($this);
```
`Pembayaran::class` (dipakai di 4 signature parameter type-hint) TIDAK perlu `use`/FQCN — sama-namespace `Domains\Keuangan\Models` (sudah dipindah SP3). `SystemSetting`, `AutoAllocationFailedException`, `InsufficientBalanceException` TETAP `use` biasa (semua TETAP di `app/Models`/`app/Exceptions`, tidak pindah).

- [ ] **Step 3: Perbaiki gotcha implisit di `app/Models/Siswa.php`**

Baca file, cari baris `return $this->hasOne(Wallet::class);` di method `wallet()` (baris 78 baseline), ganti jadi `return $this->hasOne(\App\Domains\Keuangan\Models\Wallet::class);`.

- [ ] **Step 4: Update `database/factories/WalletFactory.php`**

Ganti `use App\Models\Wallet;` → `use App\Domains\Keuangan\Models\Wallet;`.

- [ ] **Step 5: Grep ulang untuk daftar consumer PASTI**

```bash
grep -rln "App\\\\Models\\\\Wallet\b" --include="*.php" app database tests
```

Daftar per 24 Agustus 2026 (WAJIB grep ulang, referensi awal — bisa berubah):
```
tests/Feature/Admin/VirtualAccountControllerTest.php
tests/Feature/Admin/ManualPaymentControllerTest.php
tests/Feature/Keuangan/GatewayImplementationTest.php
tests/Feature/Keuangan/ReconciliationCommandTest.php
tests/Unit/Models/WalletBriVirtualAccountsRelationTest.php
tests/Feature/Keuangan/PaymentChannelModelsTest.php
tests/Feature/Admin/VirtualAccountAuthorizationTest.php
app/Domains/Keuangan/Models/BriVirtualAccount.php
app/Services/Finance/AutoAllocationEngine.php       <- JANGAN diedit di sini, ditangani Task 5
tests/Feature/Keuangan/AutoAllocationEngineTest.php
app/Services/Finance/PaymentAllocationService.php   <- JANGAN diedit di sini, ditangani Task 7
tests/Feature/Keuangan/SystemSettingTest.php
tests/Feature/Keuangan/WalletDatabaseTest.php
tests/Feature/Keuangan/WalletTest.php
tests/Feature/Keuangan/CreateWalletListenerTest.php
app/Listeners/CreateWalletForNewStudent.php
app/Domains/Keuangan/Actions/Pembayaran/ApproveManualPaymentAction.php
```

Update `use App\Models\Wallet;` → `use App\Domains\Keuangan\Models\Wallet;` di SETIAP file KECUALI yang ditandai "JANGAN diedit di sini".

- [ ] **Step 6: Verifikasi**

```bash
grep -rln "App\\\\Models\\\\Wallet\b" --include="*.php" app database tests
```
Expected: hanya `app/Services/Finance/AutoAllocationEngine.php` dan `app/Services/Finance/PaymentAllocationService.php` yang tersisa (ditangani Task 5 & 7).

- [ ] **Step 7: Jalankan test scoped minimal**

```bash
php artisan tinker --execute="echo class_exists(\App\Domains\Keuangan\Models\Wallet::class) ? 'OK' : 'MISSING';"
```

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah model Wallet ke Domains\Keuangan\Models, perbaiki gotcha dua arah (Siswa.php FQCN, tambah use SystemSetting)"
```

---

## Task 2: Pindahkan Model `WalletMutasi` (+ Perbaiki Bug `pembayaran()`)

**Files:**
- Move: `app/Models/WalletMutasi.php` → `app/Domains/Keuangan/Models/WalletMutasi.php`
- Test: tambah test regresi baru untuk bug fix

**Interfaces:**
- Produces: `App\Domains\Keuangan\Models\WalletMutasi`.

**PENTING — INI SATU-SATUNYA PERUBAHAN PERILAKU DI SELURUH SP4 (bukan zero-behavior-change biasa)**: relasi `pembayaran()` di baseline SUDAH RUSAK (referensi implisit `Pembayaran::class` yang resolve ke `App\Models\Pembayaran` yang sudah tidak ada sejak SP3). Memindahkan model ini ke namespace baru DENGAN referensi yang benar akan MEMPERBAIKI bug ini — perilaku SETELAH migrasi akan BERBEDA (benar) dari SEBELUM migrasi (rusak). Ini disengaja, dicatat eksplisit, BUKAN pelanggaran zero-behavior-change.

- [ ] **Step 1: Pindahkan file fisik**

```bash
git mv app/Models/WalletMutasi.php app/Domains/Keuangan/Models/WalletMutasi.php
```

- [ ] **Step 2: Ubah isi file — namespace, PERBAIKI relasi `pembayaran()`, TANPA `newFactory()` (tidak ada file factory-nya, JANGAN ditambahkan)**

Timpa seluruh isi `app/Domains/Keuangan/Models/WalletMutasi.php` dengan:

```php
<?php

namespace App\Domains\Keuangan\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletMutasi extends Model
{
    use HasFactory;

    protected $table = 'wallet_mutasi';

    protected $fillable = [
        'wallet_id',
        'pembayaran_id',
        'tipe',
        'amount',
        'saldo_sebelum',
        'saldo_sesudah',
        'keterangan',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function pembayaran(): BelongsTo
    {
        return $this->belongsTo(Pembayaran::class);
    }
}
```

Catatan: `Wallet::class` (Task 1) dan `Pembayaran::class` (SP3) sama-sama SUDAH di `Domains\Keuangan\Models` — begitu file ini pindah ke namespace yang sama, relasi `pembayaran()` OTOMATIS BENAR tanpa perlu `use`/FQCN tambahan (beda dengan baseline yang rusak karena `Pembayaran` sudah pindah keluar dari `App\Models` sejak SP3 tapi file ini tetap di situ tanpa `use`).

- [ ] **Step 3: Grep ulang untuk consumer (kemungkinan kosong/minim, `WalletMutasi` cuma diakses via relasi `wallet->mutasi()`, bukan FQCN langsung)**

```bash
grep -rln "App\\\\Models\\\\WalletMutasi\b" --include="*.php" app database tests
```
Expected: kosong atau sangat sedikit — update kalau ada.

- [ ] **Step 4: Tulis test regresi baru yang membuktikan bug sudah diperbaiki**

Cari test file yang sudah ada untuk `WalletMutasi` (`tests/Feature/Keuangan/WalletDatabaseTest.php` — baca isinya dulu untuk cocokkan pola helper), tambahkan:

```php
it('resolves the pembayaran relation on wallet_mutasi correctly (regression: this relation was silently broken by an implicit same-namespace reference after Pembayaran moved domains in SP3)', function () {
    $wallet = \App\Domains\Keuangan\Models\Wallet::factory()->create();
    $pembayaran = \App\Domains\Keuangan\Models\Pembayaran::factory()->create();

    $mutasi = $wallet->mutasi()->create([
        'pembayaran_id' => $pembayaran->id,
        'tipe' => 'topup',
        'amount' => 100000,
        'saldo_sebelum' => 0,
        'saldo_sesudah' => 100000,
        'keterangan' => 'Test regresi relasi pembayaran',
    ]);

    expect($mutasi->pembayaran)->not->toBeNull();
    expect($mutasi->pembayaran->id)->toBe($pembayaran->id);
});
```

**Sebelum menulis persis seperti di atas**: baca dulu isi `tests/Feature/Keuangan/WalletDatabaseTest.php`, cocokkan pola factory/helper yang sudah dipakai di file itu (mungkin ada helper pembuat wallet/siswa yang sebaiknya dipakai ulang daripada `Wallet::factory()->create()` polos).

- [ ] **Step 5: Jalankan test scoped**

```bash
php artisan test tests/Feature/Keuangan/WalletDatabaseTest.php tests/Feature/Keuangan/WalletTest.php
```
Expected: semua PASS termasuk test regresi baru.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "fix(keuangan): pindah model WalletMutasi ke Domains\Keuangan\Models, perbaiki relasi pembayaran() yang rusak sejak SP3, tambah test regresi"
```

---

## Task 3: Pindahkan Model `Cicilan`

**Files:**
- Move: `app/Models/Cicilan.php` → `app/Domains/Keuangan/Models/Cicilan.php`
- Modify: `database/factories/CicilanFactory.php` + seluruh file hasil grep `App\Models\Cicilan\b`

**Interfaces:**
- Produces: `App\Domains\Keuangan\Models\Cicilan`.

- [ ] **Step 1: Pindahkan file fisik**

```bash
git mv app/Models/Cicilan.php app/Domains/Keuangan/Models/Cicilan.php
```

- [ ] **Step 2: Ubah isi file**

Timpa seluruh isi `app/Domains/Keuangan/Models/Cicilan.php` dengan:

```php
<?php

namespace App\Domains\Keuangan\Models;

use Database\Factories\CicilanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cicilan extends Model
{
    use HasFactory;

    protected static function newFactory(): CicilanFactory
    {
        return CicilanFactory::new();
    }

    protected $table = 'cicilan';

    protected $fillable = ['skema_cicilan_id', 'urutan', 'nominal', 'jatuh_tempo', 'status'];

    protected function casts(): array
    {
        return [
            'jatuh_tempo' => 'date',
        ];
    }

    public function skemaCicilan(): BelongsTo
    {
        return $this->belongsTo(SkemaCicilan::class);
    }

    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class);
    }
}
```

Catatan: `SkemaCicilan::class`/`Pembayaran::class` sama-sama SUDAH di `Domains\Keuangan\Models` sejak SP1/SP3 — tidak perlu FQCN lagi setelah `Cicilan` sama-namespace.

- [ ] **Step 3: Update `database/factories/CicilanFactory.php`**

Ganti `use App\Models\Cicilan;` → `use App\Domains\Keuangan\Models\Cicilan;`.

- [ ] **Step 4: Grep ulang untuk daftar consumer PASTI (WAJIB pola `App\Models\Cicilan\b`, BUKAN cuma `use` — beberapa consumer pakai FQCN inline)**

```bash
grep -rln "App\\\\Models\\\\Cicilan\b" --include="*.php" app database tests
```

Daftar per 24 Agustus 2026 (WAJIB grep ulang):
```
tests/Feature/Portal/TagihanPembayaranTest.php
tests/Unit/PembayaranServiceTest.php
tests/Feature/Admin/CatatManualPembayaranTest.php
app/Http/Controllers/Portal/TagihanController.php          <- cross-scope touch, controller TIDAK migrasi
app/Domains/Keuangan/Services/PembayaranService.php
tests/Unit/PembayaranDataLayerTest.php
tests/Unit/DashboardStatsServiceTest.php
app/Domains/Keuangan/Models/Tagihan.php
app/Domains/Keuangan/Models/Pembayaran.php
app/Http/Controllers/Lembaga/Keuangan/TagihanController.php
tests/Unit/CicilanSeederTest.php
tests/Feature/Admin/SkemaCicilanTest.php
database/factories/CicilanFactory.php                       <- SUDAH diedit Step 3
app/Domains/Keuangan/Actions/Tagihan/CatatManualCicilanAction.php
app/Domains/Keuangan/Models/SkemaCicilan.php
```

Update `use App\Models\Cicilan;` (atau FQCN inline `\App\Models\Cicilan::class`) → `App\Domains\Keuangan\Models\Cicilan` di SETIAP file (kecuali factory yang sudah diedit Step 3).

- [ ] **Step 5: Verifikasi**

```bash
grep -rln "App\\\\Models\\\\Cicilan\b" --include="*.php" app database tests
```
Expected: kosong.

- [ ] **Step 6: Jalankan test scoped**

```bash
php artisan test tests/Unit/CicilanSeederTest.php tests/Feature/Admin/SkemaCicilanTest.php
```
Expected: semua PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah model Cicilan ke Domains\Keuangan\Models"
```

---

## Task 4: Verifikasi Checkpoint — 3 Model Selesai Sebelum Lanjut ke Service

**Files:**
- Tidak ada file baru — task ini murni verifikasi gate.

- [ ] **Step 1: Verifikasi gabungan — tidak ada referensi namespace lama tersisa untuk 3 model**

```bash
grep -rln "App\\\\Models\\\\Wallet\b\|App\\\\Models\\\\WalletMutasi\b\|App\\\\Models\\\\Cicilan\b" --include="*.php" app database tests
```
Expected: hanya `app/Services/Finance/AutoAllocationEngine.php` dan `app/Services/Finance/PaymentAllocationService.php` (ditangani Task 5 & 7).

- [ ] **Step 2: Verifikasi 3 file lama sudah tidak ada**

```bash
ls app/Models/Wallet.php app/Models/WalletMutasi.php app/Models/Cicilan.php 2>&1
```
Expected: error "No such file or directory" untuk ketiganya.

- [ ] **Step 3: Jalankan test scoped luas**

```bash
php artisan test tests/Feature/Keuangan tests/Unit --filter="Wallet|Cicilan"
```
Expected: semua PASS (kalau ada "Class not found", cek lagi Task 1-3).

- [ ] **Step 4: Kalau ada temuan tidak sesuai, STOP dan perbaiki sebelum lanjut Task 5.**

Tidak ada commit di task ini.

---

## Task 5: Pindahkan Service `AutoAllocationEngine` (+ Gotcha Dua Arah `NotificationDispatcher`)

**Files:**
- Move: `app/Services/Finance/AutoAllocationEngine.php` → `app/Domains/Keuangan/Services/AutoAllocationEngine.php`
- Modify: seluruh file hasil grep `use App\Services\Finance\AutoAllocationEngine;`

**Interfaces:**
- Consumes: `App\Domains\Keuangan\Models\{Wallet,Pembayaran,PembayaranTagihan,Tagihan}` (Task 1, SP1-3), `App\Services\Finance\NotificationDispatcher` (TIDAK PINDAH).
- Produces: `App\Domains\Keuangan\Services\AutoAllocationEngine` — dipakai `Wallet::topup()` (Task 1, via FQCN karena beda sub-namespace).

- [ ] **Step 1: Pindahkan file fisik**

```bash
git mv app/Services/Finance/AutoAllocationEngine.php app/Domains/Keuangan/Services/AutoAllocationEngine.php
```

- [ ] **Step 2: Ubah isi file — namespace, tambah `use NotificationDispatcher` (gotcha dua arah), `Wallet` jadi biasa (sama-namespace dengan... TUNGGU, `Wallet` di `Domains\Keuangan\Models`, `AutoAllocationEngine` di `Domains\Keuangan\Services` — BEDA sub-namespace, WAJIB `use` eksplisit meski sama domain)**

Timpa seluruh isi `app/Domains/Keuangan/Services/AutoAllocationEngine.php` dengan:

```php
<?php

namespace App\Domains\Keuangan\Services;

use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Models\PembayaranTagihan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Models\Wallet;
use App\Notifications\Finance\SaldoTidakCukupNotification;
use App\Services\Finance\NotificationDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AutoAllocationEngine
{
    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    public function run(Wallet $wallet): void
    {
        $skippedTagihan = collect();

        // lockForUpdate to ensure wallet balance doesn't change concurrently during calculation
        DB::transaction(function () use ($wallet, &$skippedTagihan) {
            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            $saldo = $wallet->balance;
            if ($saldo <= 0) {
                return; // Nothing to allocate
            }

            // Get active tagihan ordered by priority_score, then jatuh_tempo, then id
            $tagihans = $wallet->siswa->tagihan()
                ->join('jenis_tagihan', 'tagihan.jenis_tagihan_id', '=', 'jenis_tagihan.id')
                ->whereIn('tagihan.status', ['belum_bayar', 'sebagian'])
                ->orderBy('jenis_tagihan.priority_score', 'asc')
                ->orderBy('tagihan.jatuh_tempo', 'asc')
                ->orderBy('tagihan.id', 'asc')
                ->select('tagihan.*') // Ensure we only get tagihan columns
                ->lockForUpdate()
                ->get();

            if ($tagihans->isEmpty()) {
                return;
            }

            $allocated = [];
            $totalAllocatedAmount = 0;

            foreach ($tagihans as $tagihan) {
                if ($saldo <= 0) {
                    break;
                }

                $sisaTagihan = $tagihan->net_amount - $tagihan->paid_amount;
                $amountToPay = min($saldo, $sisaTagihan);

                if ($amountToPay > 0) {
                    $saldo -= $amountToPay;
                    $totalAllocatedAmount += $amountToPay;

                    $allocated[] = [
                        'tagihan' => $tagihan,
                        'amount' => $amountToPay,
                    ];
                }
            }

            $skippedTagihan = $tagihans->whereNotIn('id', collect($allocated)->pluck('tagihan.id'))->values();

            if ($totalAllocatedAmount > 0) {
                // Buat record pembayaran
                $pembayaran = Pembayaran::create([
                    'sumber' => 'admin',
                    'wallet_id' => $wallet->id,
                    'metode' => 'wallet_auto',
                    'is_auto_allocation' => true,
                    'status' => 'lunas',
                    'diverifikasi_pada' => now(),
                    'channel_reference' => 'AUTO-' . strtoupper(Str::random(10)),
                ]);

                // Debit wallet and create mutasi (within existing transaction — no nested lock)
                $wallet->debitWithinTransaction($totalAllocatedAmount, $pembayaran, 'Auto-allocation pembayaran tagihan');

                // Update tagihans and create pivot
                foreach ($allocated as $item) {
                    $tagihan = $item['tagihan'];
                    $amount = $item['amount'];

                    PembayaranTagihan::create([
                        'pembayaran_id' => $pembayaran->id,
                        'tagihan_id' => $tagihan->id,
                        'amount_allocated' => $amount,
                    ]);

                    $tagihan->paid_amount += $amount;
                    if ($tagihan->paid_amount >= $tagihan->net_amount) {
                        $tagihan->status = 'lunas';
                    } else {
                        $tagihan->status = 'sebagian';
                    }
                    $tagihan->save();
                }
            }
        });

        if ($skippedTagihan->isNotEmpty()) {
            $siswa = $wallet->siswa;
            $kontakUtama = $siswa?->orangTua()->wherePivot('is_kontak_utama', true)->first();

            if ($kontakUtama !== null) {
                // Spec (keuangan-05-notifikasi.md, tabel event notifikasi): "men-skip tagihan
                // prioritas tertinggi" — hanya tagihan pertama dalam koleksi $skippedTagihan
                // yang sudah terurut priority_score yang memicu notifikasi ini, bukan semuanya.
                $tagihan = $skippedTagihan->first();
                $selisih = (float) $tagihan->net_amount - (float) $tagihan->paid_amount;

                try {
                    $this->dispatcher->send($kontakUtama, new SaldoTidakCukupNotification($tagihan->load('jenisTagihan'), $selisih));
                } catch (\Throwable $e) {
                    Log::error('Gagal mengirim SaldoTidakCukupNotification: '.$e->getMessage());
                }
            }
        }
    }
}
```

- [ ] **Step 3: Grep ulang dan update consumer**

```bash
grep -rln "use App\\\\Services\\\\Finance\\\\AutoAllocationEngine;" --include="*.php" app database tests
```

Update `use App\Services\Finance\AutoAllocationEngine;` → `use App\Domains\Keuangan\Services\AutoAllocationEngine;` di SETIAP file hasil grep. Ini TIDAK termasuk `app/Domains/Keuangan/Models/Wallet.php` yang sudah pakai FQCN inline (Task 1 Step 2 catatan), cek dulu apakah FQCN itu sudah benar.

- [ ] **Step 4: Verifikasi**

```bash
grep -rln "use App\\\\Services\\\\Finance\\\\AutoAllocationEngine;" --include="*.php" app database tests
```
Expected: kosong.

- [ ] **Step 5: Jalankan test scoped**

```bash
php artisan test tests/Feature/Keuangan/AutoAllocationEngineTest.php tests/Feature/Keuangan/WalletTest.php tests/Feature/Keuangan/WalletDatabaseTest.php
```
Expected: semua PASS.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah AutoAllocationEngine ke Domains\Keuangan\Services, perbaiki gotcha dua arah (tambah use NotificationDispatcher)"
```

---

## Task 6: Pindahkan Service `SkipAlertResolver`

**Files:**
- Move: `app/Services/Finance/SkipAlertResolver.php` → `app/Domains/Keuangan/Services/SkipAlertResolver.php`
- Modify: seluruh file hasil grep `use App\Services\Finance\SkipAlertResolver;`

**Interfaces:**
- Consumes: `App\Domains\Keuangan\Models\Tagihan` (SP2).
- Produces: `App\Domains\Keuangan\Services\SkipAlertResolver` — dipakai Task 9 (`Portal\Keuangan\DashboardController`).

**GUARD WAJIB dipertahankan persis**: `withoutGlobalScope(TenantScope::class)` di baris 39 DAN baris 80 baseline (2 titik terpisah — query utama `tagihan()` DAN relasi `jenisTagihan()`), dengan komentar panjang alasan divergensi dari `AutoAllocationEngine::run()` (baris 12-27 baseline) — WAJIB disalin PERSIS, TIDAK disingkat.

- [ ] **Step 1: Pindahkan file fisik**

```bash
git mv app/Services/Finance/SkipAlertResolver.php app/Domains/Keuangan/Services/SkipAlertResolver.php
```

- [ ] **Step 2: Ubah isi file — HANYA namespace + `use Tagihan` biasa (sama-domain, beda sub-namespace, WAJIB `use` eksplisit)**

Timpa seluruh isi `app/Domains/Keuangan/Services/SkipAlertResolver.php` dengan:

```php
<?php
// app/Domains/Keuangan/Services/SkipAlertResolver.php

namespace App\Domains\Keuangan\Services;

use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Scopes\TenantScope;
use App\Models\Siswa;

class SkipAlertResolver
{
    /**
     * Read-only replica of AutoAllocationEngine::run()'s priority ordering and
     * allocation walk (zero-or-skip semantics — a tagihan receiving ANY partial
     * payment is not "skipped"), used ONLY to compute what the banner should
     * show. Never touches the wallet or any tagihan row, and does not call
     * AutoAllocationEngine itself.
     *
     * One deliberate divergence: AutoAllocationEngine::run() returns early when
     * balance <= 0, before computing $skippedTagihan at all, so a zero-balance
     * wallet with outstanding tagihan produces no notification from the engine.
     * This resolver has no such early return — a zero balance still surfaces the
     * dashboard banner (the highest-priority tagihan is treated as fully
     * skipped), which is the correct proactive-warning behavior for a parent
     * viewing their own dashboard, even though the backend notification system
     * would stay silent in the same scenario.
     *
     * @return array{tagihan: Tagihan, selisih: float}|null
     */
    public function resolve(Siswa $siswa): ?array
    {
        $wallet = $siswa->wallet;

        if ($wallet === null) {
            return null;
        }

        $tagihans = $siswa->tagihan()
            ->withoutGlobalScope(TenantScope::class)
            ->join('jenis_tagihan', 'tagihan.jenis_tagihan_id', '=', 'jenis_tagihan.id')
            ->whereIn('tagihan.status', ['belum_bayar', 'sebagian'])
            ->orderBy('jenis_tagihan.priority_score', 'asc')
            ->orderBy('tagihan.jatuh_tempo', 'asc')
            ->orderBy('tagihan.id', 'asc')
            ->select('tagihan.*')
            ->get();

        if ($tagihans->isEmpty()) {
            return null;
        }

        $saldo = (float) $wallet->balance;
        $allocatedIds = [];

        foreach ($tagihans as $tagihan) {
            if ($saldo <= 0) {
                break;
            }

            $sisaTagihan = (float) $tagihan->net_amount - (float) $tagihan->paid_amount;
            $amountToPay = min($saldo, $sisaTagihan);

            if ($amountToPay > 0) {
                $saldo -= $amountToPay;
                $allocatedIds[] = $tagihan->id;
            }
        }

        $skipped = $tagihans->whereNotIn('id', $allocatedIds)->values();

        if ($skipped->isEmpty()) {
            return null;
        }

        $tagihan = $skipped->first();
        $selisih = (float) $tagihan->net_amount - (float) $tagihan->paid_amount;

        $tagihan->setRelation(
            'jenisTagihan',
            $tagihan->jenisTagihan()->withoutGlobalScope(TenantScope::class)->first()
        );

        return ['tagihan' => $tagihan, 'selisih' => $selisih];
    }
}
```

- [ ] **Step 3: Grep ulang dan update consumer**

```bash
grep -rln "use App\\\\Services\\\\Finance\\\\SkipAlertResolver;" --include="*.php" app database tests
```

Update di SETIAP file hasil grep (kemungkinan besar `app/Http/Controllers/Keuangan/DashboardController.php` — JANGAN diedit di sini kalau muncul, ditangani Task 9 sekaligus dengan refactor controllernya).

- [ ] **Step 4: Verifikasi**

```bash
grep -rln "use App\\\\Services\\\\Finance\\\\SkipAlertResolver;" --include="*.php" app database tests
```
Expected: hanya `app/Http/Controllers/Keuangan/DashboardController.php` (ditangani Task 9).

- [ ] **Step 5: Jalankan test scoped**

```bash
php artisan test --filter="SkipAlert"
```
Expected: semua PASS.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah SkipAlertResolver ke Domains\Keuangan\Services, guard TenantScope dipertahankan persis"
```

---

## Task 7: Pindahkan Service `PaymentAllocationService` (Utuh, + Gotcha Dua Arah)

**Files:**
- Move: `app/Services/Finance/PaymentAllocationService.php` → `app/Domains/Keuangan/Services/PaymentAllocationService.php`
- Modify: `app/Console/Commands/ReconcilePayments.php` (`use` saja, isi TIDAK disentuh — ditangani lengkap Task 8, tapi `use`-nya WAJIB diupdate di sini supaya tidak error di window antar-task)

**Interfaces:**
- Consumes: `App\Domains\Keuangan\Models\{Pembayaran,Tagihan,Wallet}` (Task 1, SP2-3), `App\Models\Siswa` (tetap), `App\Services\Finance\NotificationDispatcher` (TIDAK PINDAH).
- Produces: `App\Domains\Keuangan\Services\PaymentAllocationService` — dipakai Task 8 (`ReconcilePayments`).

**Keputusan desain (spec §6.1)**: file ini pindah UTUH, KEDUA method (`allocate()` dan `topupSisaJikaAda()`) TETAP dalam 1 class, TIDAK dipecah meski subjek datanya beda (Tagihan/Pembayaran vs Wallet). JANGAN pisahkan jadi 2 file di task ini.

- [ ] **Step 1: Pindahkan file fisik**

```bash
git mv app/Services/Finance/PaymentAllocationService.php app/Domains/Keuangan/Services/PaymentAllocationService.php
```

- [ ] **Step 2: Ubah isi file — namespace, tambah `use NotificationDispatcher` (gotcha dua arah), `Wallet`/`Tagihan`/`Pembayaran` jadi `use` biasa dalam domain (beda sub-namespace tetap WAJIB `use` eksplisit kecuali sama-sub-namespace persis)**

Timpa seluruh isi `app/Domains/Keuangan/Services/PaymentAllocationService.php` dengan:

```php
<?php

namespace App\Domains\Keuangan\Services;

use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Models\Wallet;
use App\Exceptions\AutoAllocationFailedException;
use App\Models\Siswa;
use App\Notifications\Finance\PembayaranBerhasilNotification;
use App\Services\Finance\NotificationDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentAllocationService
{
    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    /**
     * Allocate payment amount to related bills (tagihan) and update their statuses.
     */
    public function allocate(Pembayaran $pembayaran): void
    {
        // Find all tagihans related to this payment via pembayaran_tagihan
        $pembayaranTagihans = $pembayaran->pembayaranTagihan()->with('tagihan')->get();

        foreach ($pembayaranTagihans as $pt) {
            $tagihan = $pt->tagihan;

            // Skip cancelled bills
            if ($tagihan->status === 'dibatalkan') {
                continue;
            }

            // Lock row for update just to be safe if within a transaction
            $lockedTagihan = $tagihan->lockForUpdate()->find($tagihan->id);
            if ($lockedTagihan->status === 'dibatalkan') {
                continue;
            }

            // Increase paid amount
            $lockedTagihan->paid_amount += $pt->amount_allocated;

            // Update status based on the new paid amount compared to net_amount
            $becameLunas = false;
            if ($lockedTagihan->paid_amount >= $lockedTagihan->net_amount) {
                $becameLunas = $lockedTagihan->status !== 'lunas';
                $lockedTagihan->status = 'lunas';
            } elseif ($lockedTagihan->paid_amount > 0) {
                $lockedTagihan->status = 'sebagian';
            }

            $lockedTagihan->save();

            if ($becameLunas) {
                $tagihanId = $lockedTagihan->id;
                $metode = $pembayaran->metode;

                DB::afterCommit(function () use ($tagihanId, $metode) {
                    $freshTagihan = Tagihan::with(['jenisTagihan', 'tagihable'])->find($tagihanId);
                    if ($freshTagihan === null || $freshTagihan->tagihable_type !== Siswa::class) {
                        return;
                    }

                    $siswa = $freshTagihan->tagihable;
                    $kontakUtama = $siswa?->orangTua()->wherePivot('is_kontak_utama', true)->first();
                    if ($kontakUtama !== null) {
                        try {
                            $this->dispatcher->send($kontakUtama, new PembayaranBerhasilNotification($freshTagihan, $metode));
                        } catch (\Throwable $e) {
                            Log::error('Gagal mengirim PembayaranBerhasilNotification: '.$e->getMessage());
                        }
                    }
                });
            }
        }
    }

    public function topupSisaJikaAda(Pembayaran $pembayaran): void
    {
        if (! in_array($pembayaran->topup_status, ['pending', 'failed'], true)) {
            return;
        }

        $porsiTagihan = $pembayaran->pembayaranTagihan()->sum('amount_allocated');
        $porsiTopup = (float) $pembayaran->amount - (float) $porsiTagihan;

        if ($porsiTopup <= 0) {
            Log::warning("topupSisaJikaAda: sisa <= 0 untuk pembayaran id={$pembayaran->id}, tidak ada yang perlu di-topup.");
            return;
        }

        // Siswa punya TenantScope global (BelongsToTenant) yang otomatis memfilter query
        // berdasarkan tenant user yang sedang login -- kita cari wallet langsung by
        // siswa_id, bukan lewat relasi $pembayaran->siswa->wallet, supaya tidak diam-diam
        // bernilai null kalau proses ini berjalan dalam konteks tenant yang berbeda.
        $wallet = Wallet::where('siswa_id', $pembayaran->siswa_id)->first();

        if ($wallet === null) {
            Log::error("Gagal topup dari pembayaran {$pembayaran->id}: Wallet siswa tidak ditemukan.");
            $pembayaran->update(['topup_status' => 'failed']);
            return;
        }

        try {
            $wallet->topup($porsiTopup, $pembayaran, "Top-up dari pembayaran {$pembayaran->metode} ({$pembayaran->id})");
            $pembayaran->update(['topup_status' => 'completed']);
        } catch (AutoAllocationFailedException $e) {
            // Saldo wallet SUDAH ter-kredit sukses (increment itu commit di dalam
            // transaction internal Wallet::topup(), sebelum AutoAllocationEngine::run()
            // dijalankan) -- hanya langkah auto-alokasi berikutnya yang gagal.
            // topup_status wajib mencerminkan bahwa kreditnya sudah selesai, kalau
            // tidak ReconcilePayments::retryFailedTopups() akan pilih ulang Pembayaran
            // ini dan mengkredit wallet dua kali.
            Log::error("Auto-alokasi gagal setelah topup dari pembayaran {$pembayaran->id} berhasil di-kredit (saldo AMAN, hanya alokasi yang gagal): ".$e->getMessage());
            $pembayaran->update(['topup_status' => 'completed']);
        } catch (\Throwable $e) {
            Log::error("Gagal mengeksekusi topup dari pembayaran {$pembayaran->id}: ".$e->getMessage());
            $pembayaran->update(['topup_status' => 'failed']);
        }
    }
}
```

- [ ] **Step 3: Grep ulang dan update consumer**

```bash
grep -rln "use App\\\\Services\\\\Finance\\\\PaymentAllocationService;" --include="*.php" app database tests
```

Update SETIAP file hasil grep, TERMASUK `app/Console/Commands/ReconcilePayments.php` (baris `use` saja — file ini akan direfactor lengkap namanya-tetap-sama di Task 8, tapi `use`-nya WAJIB diupdate sekarang supaya tidak error di window antar-task).

- [ ] **Step 4: Verifikasi**

```bash
grep -rln "use App\\\\Services\\\\Finance\\\\PaymentAllocationService;" --include="*.php" app database tests
```
Expected: kosong.

- [ ] **Step 5: Jalankan test scoped**

```bash
php artisan test tests/Feature/Keuangan/PaymentAllocationServiceTest.php tests/Feature/Keuangan/PaymentAllocationServiceTopupRemainderTest.php tests/Feature/Keuangan/ReconciliationCommandTest.php
```
Expected: semua PASS.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah PaymentAllocationService (utuh) ke Domains\Keuangan\Services, perbaiki gotcha dua arah (tambah use NotificationDispatcher)"
```

---

## Task 8: Cross-Scope Touch — `ReconcilePayments` Command (Verifikasi Final)

**Files:**
- Verifikasi: `app/Console/Commands/ReconcilePayments.php` (`use`-nya sudah diupdate di Task 7 Step 3 — task ini murni verifikasi + test scoped luas, TIDAK ada perubahan isi lagi)

**Interfaces:**
- Tidak ada file baru.

- [ ] **Step 1: Baca ulang `app/Console/Commands/ReconcilePayments.php`, konfirmasi `use App\Domains\Keuangan\Services\PaymentAllocationService;` sudah benar dari Task 7**

```bash
grep -n "^use" app/Console/Commands/ReconcilePayments.php
```
Expected: `PaymentGatewayInterface`, `BriQrisPayment`, `Pembayaran` sudah `Domains\Keuangan\*` (dari SP3), `PaymentAllocationService` sudah `Domains\Keuangan\Services\PaymentAllocationService` (dari Task 7). Isi method `reconcileWaitingPayments()`/`retryFailedTopups()` TIDAK berubah sama sekali.

- [ ] **Step 2: Jalankan test scoped**

```bash
php artisan test tests/Feature/Keuangan/ReconciliationCommandTest.php tests/Feature/Keuangan/ReconcilePaymentsBundledTopupTest.php tests/Feature/Keuangan/ReconcilePaymentsQrisTest.php
```
Expected: semua PASS.

- [ ] **Step 3: Kalau ada yang belum sesuai, perbaiki sekarang. Tidak ada commit baru di task ini kalau Task 7 sudah benar (murni verifikasi) — kalau ADA perbaikan, commit terpisah:**

```bash
git add -A
git commit -m "fix(keuangan): perbaiki sisa use statement ReconcilePayments.php yang terlewat Task 7"
```

---

## Task 9: Refactor `Keuangan\DashboardController` — Namespace + View

**Files:**
- Create: `app/Http/Controllers/Portal/Keuangan/DashboardController.php`
- Delete: `app/Http/Controllers/Keuangan/DashboardController.php`
- Move: `resources/views/keuangan/dashboard.blade.php` → `resources/views/portals/portal/keuangan/dashboard.blade.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `App\Domains\Keuangan\Services\SkipAlertResolver` (Task 6), `App\Domains\Keuangan\Services\PaymentService` (SP3), `App\Domains\Keuangan\Models\Tagihan` (SP2).

Controller ini 100% read-only (index() saja) — TIDAK ADA Action baru, konsisten pola read-only-tetap-inline dari SP1-3. Guard route-level TETAP di `routes/web.php` (`permission:keuangan.akses`, `resolve.active.siswa`), TIDAK ada `authorize()` di dalam class (sesuai kode asli).

Baseline kode (65 baris, commit `5c71903`) — baca ulang untuk konfirmasi sebelum edit.

- [ ] **Step 1: Buat controller baru di `Portal\Keuangan\`**

`app/Http/Controllers/Portal/Keuangan/DashboardController.php`:

```php
<?php
// app/Http/Controllers/Portal/Keuangan/DashboardController.php

namespace App\Http\Controllers\Portal\Keuangan;

use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Services\PaymentService;
use App\Domains\Keuangan\Services\SkipAlertResolver;
use App\Exceptions\PaymentException;
use App\Http\Controllers\Controller;
use App\Models\Scopes\TenantScope;
use App\Models\SystemSetting;
use App\Services\Notifications\NotificationFeedResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class DashboardController extends Controller
{
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
            return view('portals.portal.keuangan.tanpa-anak');
        }

        try {
            $this->paymentService->getOrCreatePermanentVa($activeSiswa);
        } catch (PaymentException $e) {
            Log::error('Gagal membuat VA BRI Permanen: '.$e->getMessage());
            // Tidak ada VA untuk disinkronkan -- dashboard tetap dirender, $wallet
            // di bawah kemungkinan null dan view sudah pakai null-safe operator.
        }

        $wallet = $activeSiswa->wallet;
        $skipAlert = $this->skipAlertResolver->resolve($activeSiswa);
        $notificationFeed = $this->notificationFeedResolver->resolve($request->user());

        $tagihans = Tagihan::where('tagihable_type', get_class($activeSiswa))
            ->where('tagihable_id', $activeSiswa->id)
            ->whereIn('status', ['belum_bayar', 'sebagian'])
            ->with(['jenisTagihan' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
            ->orderBy('jatuh_tempo')
            ->get();

        $autoDebitEnabled = (bool) SystemSetting::getResolved('auto_debit_enabled', $activeSiswa->lembaga_id, false);

        return view('portals.portal.keuangan.dashboard', [
            'activeSiswa' => $activeSiswa,
            'wallet' => $wallet,
            'skipAlert' => $skipAlert,
            'notificationFeed' => $notificationFeed,
            'tagihans' => $tagihans,
            'autoDebitEnabled' => $autoDebitEnabled,
        ]);
    }
}
```

**Catatan**: baris `view('keuangan.tanpa-anak')` SUDAH diubah jadi `view('portals.portal.keuangan.tanpa-anak')` LANGSUNG di sini (bukan tetap `keuangan.tanpa-anak` dulu) — karena Task 10 (pemindahan view `tanpa-anak.blade.php`) akan dieksekusi SETELAH task ini. Kalau plan dieksekusi berurutan tanpa lompat, ada window singkat (antara Task 9 dan Task 10 selesai) di mana baris ini menunjuk ke view yang BELUM ada — ini AMAN selama Task 10 langsung menyusul tanpa deploy parsial di antaranya, sama seperti pola window di SP1/SP2 sebelumnya.

- [ ] **Step 2: Hapus controller lama**

```bash
git rm app/Http/Controllers/Keuangan/DashboardController.php
```

- [ ] **Step 3: Pindahkan view `dashboard.blade.php`**

```bash
mkdir -p resources/views/portals/portal/keuangan
git mv resources/views/keuangan/dashboard.blade.php resources/views/portals/portal/keuangan/dashboard.blade.php
```

Baca isi view, cek `@include` internal — kalau ada yang merujuk path `keuangan.*` lain, sesuaikan prefix ke `portals.portal.keuangan.*`.

- [ ] **Step 4: Update `routes/web.php`**

Ganti baris:
```php
Route::get('/', [\App\Http\Controllers\Keuangan\DashboardController::class, 'index'])->name('dashboard');
```
menjadi:
```php
Route::get('/', [\App\Http\Controllers\Portal\Keuangan\DashboardController::class, 'index'])->name('dashboard');
```
Baris ini ada di dalam grup `Route::middleware([...])->prefix('keuangan')->name('keuangan.')->group(...)` — nama route final `keuangan.dashboard` TIDAK berubah.

- [ ] **Step 5: Jalankan test scoped**

```bash
php artisan route:list --name=keuangan.dashboard
php artisan test tests/Feature/Keuangan/DashboardControllerTest.php tests/Feature/Keuangan/DashboardAuthorizationTest.php
```
Expected: `route:list` menunjukkan `Portal\Keuangan\DashboardController`, nama route tidak berubah. Test kemungkinan GAGAL sementara kalau `tanpa-anak.blade.php` belum dipindah (Task 10) — CATAT hasilnya, lanjutkan ke Task 10 sebelum menganggap ada masalah nyata.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah Keuangan\DashboardController ke Portal\Keuangan\, view ke portals/portal/keuangan/"
```

---

## Task 10: Pindahkan View Fallback Bersama `tanpa-anak.blade.php` (4 Titik Panggil)

**Files:**
- Move: `resources/views/keuangan/tanpa-anak.blade.php` → `resources/views/portals/portal/keuangan/tanpa-anak.blade.php`
- Modify: `app/Http/Controllers/Portal/Keuangan/TagihanController.php`, `RiwayatController.php`, `CheckoutController.php` (SP2/SP3, HANYA baris `view('keuangan.tanpa-anak')`)

**Interfaces:**
- Tidak ada interface baru — murni pemindahan view + update 3 titik panggil (titik ke-4 di `Portal\Keuangan\DashboardController` SUDAH diupdate di Task 9 Step 1).

- [ ] **Step 1: Pindahkan file fisik**

```bash
git mv resources/views/keuangan/tanpa-anak.blade.php resources/views/portals/portal/keuangan/tanpa-anak.blade.php
```

- [ ] **Step 2: Update `app/Http/Controllers/Portal/Keuangan/TagihanController.php`**

Baca file, cari baris `return view('keuangan.tanpa-anak');` (baris 19 baseline), ganti jadi `return view('portals.portal.keuangan.tanpa-anak');`.

- [ ] **Step 3: Update `app/Http/Controllers/Portal/Keuangan/RiwayatController.php`**

Baca file, cari baris `return view('keuangan.tanpa-anak');` (baris 22 baseline), ganti jadi `return view('portals.portal.keuangan.tanpa-anak');`.

- [ ] **Step 4: Update `app/Http/Controllers/Portal/Keuangan/CheckoutController.php`**

Baca file, cari baris `return view('keuangan.tanpa-anak');` (baris 36 baseline), ganti jadi `return view('portals.portal.keuangan.tanpa-anak');`.

- [ ] **Step 5: Grep ulang untuk verifikasi SEMUA titik panggil sudah diupdate**

```bash
grep -rn "keuangan\.tanpa-anak" --include="*.php" app
```
Expected: KOSONG total (semua 4 titik, termasuk `Portal\Keuangan\DashboardController.php` dari Task 9, sudah `portals.portal.keuangan.tanpa-anak`).

```bash
ls resources/views/keuangan/tanpa-anak.blade.php 2>&1
```
Expected: error "No such file or directory".

- [ ] **Step 6: Jalankan test scoped**

```bash
php artisan test tests/Feature/Keuangan/DashboardControllerTest.php tests/Feature/Keuangan/DashboardAuthorizationTest.php tests/Feature/Keuangan/CheckoutControllerCreateTest.php tests/Feature/Keuangan/RiwayatControllerIndexTest.php tests/Feature/Keuangan/TagihanControllerTest.php
```
Expected: semua PASS (termasuk yang sempat gagal di Task 9 Step 5 karena view belum ada — sekarang harus PASS).

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah view fallback tanpa-anak.blade.php ke portals/portal/keuangan/, update 3 titik panggil cross-scope (Tagihan/Riwayat/Checkout Controller)"
```

---

## Task 11: Verifikasi Checkpoint — Service & Controller Selesai Sebelum Audit Final

**Files:**
- Tidak ada file baru — task ini murni verifikasi gate.

- [ ] **Step 1: Verifikasi gabungan — tidak ada referensi namespace lama tersisa**

```bash
grep -rln "App\\\\Models\\\\Wallet\b\|App\\\\Models\\\\WalletMutasi\b\|App\\\\Models\\\\Cicilan\b\|use App\\\\Services\\\\Finance\\\\AutoAllocationEngine;\|use App\\\\Services\\\\Finance\\\\SkipAlertResolver;\|use App\\\\Services\\\\Finance\\\\PaymentAllocationService;\|use App\\\\Http\\\\Controllers\\\\Keuangan\\\\DashboardController;\|keuangan\.tanpa-anak\|keuangan\.dashboard" --include="*.php" app database tests routes
```
Expected: KOSONG total.

- [ ] **Step 2: Verifikasi file lama sudah tidak ada**

```bash
ls app/Models/Wallet.php app/Models/WalletMutasi.php app/Models/Cicilan.php app/Services/Finance/AutoAllocationEngine.php app/Services/Finance/SkipAlertResolver.php app/Services/Finance/PaymentAllocationService.php app/Http/Controllers/Keuangan/DashboardController.php resources/views/keuangan/dashboard.blade.php resources/views/keuangan/tanpa-anak.blade.php 2>&1
```
Expected: error "No such file or directory" untuk SEMUANYA.

- [ ] **Step 3: Jalankan test scoped luas**

```bash
php artisan test tests/Feature/Keuangan tests/Unit --filter="Wallet|Cicilan|AutoAllocation|SkipAlert|Reconcil|Dashboard"
```
Expected: semua PASS.

- [ ] **Step 4: Kalau ada temuan tidak sesuai, STOP dan perbaiki sebelum lanjut Task 12.**

---

## Task 12: Audit Final Menyeluruh — Bukti Penutup Migrasi Domain Keuangan

**Files:**
- Tidak ada file baru — task ini murni audit dan dokumentasi hasilnya (akan dikutip di handoff log Task 13).

**INI TASK PALING PENTING SECARA SIMBOLIS — membuktikan migrasi domain Keuangan (4 sub-project, sejak SP1) benar-benar TUNTAS.**

- [ ] **Step 1: Audit `app/Models/` — cari sisa kelas Keuangan**

```bash
find app/Models -iname "*wallet*" -o -iname "*cicilan*" -o -iname "*tagihan*" -o -iname "*pembayaran*" -o -iname "*bri*"
```
Expected: KOSONG total (semua kelas dengan nama-nama ini sudah ada di `app/Domains/Keuangan/Models/`).

- [ ] **Step 2: Audit `app/Services/` level atas — cari sisa service Keuangan**

```bash
find app/Services -maxdepth 1 -iname "*finance*" -o -iname "*pembayaran*" -o -iname "*tagihan*"
ls app/Services/Finance 2>&1
```
Expected: `find` kosong. `ls app/Services/Finance` harus error "No such file or directory" (folder ikut terhapus otomatis setelah AutoAllocationEngine/SkipAlertResolver/PaymentAllocationService/Gateway/BriInbound semua pindah — kalau folder masih ada tapi kosong, hapus manual dengan `rmdir app/Services/Finance` kalau memang benar-benar kosong, JANGAN paksa hapus kalau masih ada isinya).

- [ ] **Step 3: Audit `app/Http/Controllers/Admin/` dan `app/Http/Controllers/Keuangan/` — cari sisa controller Keuangan**

```bash
find app/Http/Controllers/Admin -iname "*tagihan*" -o -iname "*pembayaran*" -o -iname "*virtual*" -o -iname "*manualpayment*"
find app/Http/Controllers/Keuangan -type f
```
Expected: command pertama HANYA menunjukkan `Admin\TagihanSusulanController.php` (dikonfirmasi milik PPDB, BUKAN temuan). Command kedua HANYA menunjukkan `NotifikasiController.php` (dikonfirmasi generic, BUKAN temuan) — `DashboardController.php` HARUS TIDAK ADA lagi di situ.

- [ ] **Step 4: Audit `app/Contracts/` dan `app/DTO/` — pastikan benar-benar kosong dari sisa Keuangan**

```bash
find app/Contracts -type f
find app/DTO -type f
```
Expected: KOSONG total untuk keduanya (semua sudah pindah ke `Domains\Keuangan\Contracts`/`DataTransferObjects` di SP3) — kalau folder `app/Contracts`/`app/DTO` sendiri sudah kosong, boleh dihapus (`rmdir`), TAPI JANGAN paksa kalau ternyata masih ada file non-Keuangan di situ.

- [ ] **Step 5: Audit final gabungan — grep seluruh app/ untuk nama kelas Keuangan yang mungkin tercecer**

```bash
grep -rln "class.*Tagihan\|class.*Pembayaran\|class.*Wallet\|class.*Cicilan\|class.*BriVirtualAccount\|class.*BriQris\|class.*ManualPayment" --include="*.php" app | grep -v "app/Domains/Keuangan/"
```

Tinjau HASIL satu-per-satu. Yang BOLEH muncul (sudah dikonfirmasi domain lain, BUKAN temuan): `app/Services/TagihanGenerator.php`, `app/Http/Controllers/Admin/TagihanSusulanController.php`, `app/Http/Controllers/Portal/TagihanController.php`. SELAIN itu, kalau ada yang muncul, itu TEMUAN — STOP, laporkan ke user, JANGAN lanjut ke Task 13 sebelum ini diklarifikasi.

- [ ] **Step 6: Catat SEMUA hasil Step 1-5 (termasuk command yang dijalankan dan output PERSIS) — akan dikutip penuh di handoff log Task 13.**

Tidak ada commit di task ini — murni audit, hasilnya didokumentasikan di Task 13.

---

## Task 13: Verifikasi Akhir + Handoff Log — Penutup Migrasi Domain Keuangan

**Files:**
- Create: `.agents/logs/2026-08-24-refactor-02-keuangan-sp4-wallet-cicilan-rekonsiliasi.md`

- [ ] **Step 1: Jalankan test scoped gabungan luas**

```bash
php artisan test tests/Feature/Keuangan tests/Feature/Admin tests/Unit tests/Feature/Portal tests/Feature/Spmb tests/Feature/Console
```
Catat jumlah pasti passed/failed. Flaky yang sudah dikenal (hari-Minggu terkait hari libur mingguan SDM) — kalau itu SATU-SATUNYA yang gagal, jalankan ulang sendirian untuk konfirmasi, BUKAN regresi dari sub-project ini.

- [ ] **Step 2: Minta izin user untuk full test suite**

Tanya ke user: "Task 1-12 selesai, test scoped semua hijau, audit final menyeluruh sudah bersih. Boleh saya jalankan full test suite (`php artisan test`) untuk verifikasi akhir?" — TUNGGU jawaban eksplisit. JANGAN jalankan otomatis tanpa izin.

**PENTING (pelajaran dari review SP3)**: JANGAN jalankan full suite atau test scoped lain SECARA BERSAMAAN di proses/terminal terpisah — MySQL test database bersama (`pintera_app_test`) rentan tabrakan kalau diakses beberapa proses `php artisan test` sekaligus, menyebabkan kegagalan palsu (deadlock/schema corruption) yang BUKAN regresi kode. Jalankan HANYA SATU proses test di satu waktu, tunggu sampai selesai total sebelum menjalankan proses test lain.

- [ ] **Step 3: Jalankan full suite SOLO (HANYA setelah izin didapat, TIDAK ada proses lain yang mengakses DB test bersamaan)**

```bash
php artisan test
```
Catat angka PASTI passed/failed/duration.

- [ ] **Step 4: Tulis handoff log**

Buat `.agents/logs/2026-08-24-refactor-02-keuangan-sp4-wallet-cicilan-rekonsiliasi.md` (Bahasa Indonesia): ringkasan tiap task (1-12) dengan commit hash, hasil test dengan angka PASTI dari Step 1 dan Step 3 (JANGAN dicampur). **WAJIB sertakan PENUH**:
- Hasil audit final Task 12 (Step 1-5, command + output persis, bukan ringkasan) sebagai bukti tuntasnya migrasi domain Keuangan.
- Konfirmasi eksplisit bug `WalletMutasi::pembayaran()` sudah diperbaiki + hasil test regresi baru (Task 2 Step 4).
- Konfirmasi eksplisit 3 gotcha dua arah (Wallet→SystemSetting, AutoAllocationEngine→NotificationDispatcher, PaymentAllocationService→NotificationDispatcher) sudah ditangani.
- Kalau ada file di luar daftar yang disebutkan plan yang ternyata perlu disentuh, laporkan sebagai temuan terpisah — JANGAN diam-diam.

- [ ] **Step 5: Update `.agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md` §6**

Tambahkan baris baru untuk "Migrasi Domain Keuangan Sub-project 4 (Wallet & Cicilan + Rekonsiliasi) — PENUTUP" dengan link ke spec/plan/log, status 🟢 SELESAI. **Tambahkan juga catatan eksplisit bahwa SELURUH migrasi domain Keuangan (SP1-4) sudah TUNTAS**, mengacu ke hasil audit final Task 12.

- [ ] **Step 6: Commit**

```bash
git add .agents/logs/2026-08-24-refactor-02-keuangan-sp4-wallet-cicilan-rekonsiliasi.md .agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md
git commit -m "docs(refactor): handoff log migrasi domain Keuangan Sub-project 4 (PENUTUP) - seluruh migrasi domain Keuangan tuntas"
```
