# Spec: Perbaikan Temuan Audit Billing Reguler (Non-PPDB)

**Tanggal**: 2026-09-02
**Branch**: `keuangan-v2`
**Konteks**: Tindak lanjut dari audit menyeluruh alur bisnis billing reguler (`.agents/logs/2026-09-01-jenis-tagihan-konsolidasi-sasaran-tarif-keringanan.md` §11-14, plus laporan audit lengkap yang sama pada percakapan 2026-09-02). Menutup semua temuan Critical + Important yang disetujui user, plus 2 pembersihan Minor. **Tidak termasuk** B.7 (inkonsistensi sinkron/queue trigger recalculate — sengaja di-skip, risiko mengubah lebih besar dari manfaatnya).

## 1. Daftar Perbaikan & Urutan

| # | Temuan | Severity | Ringkasan Perbaikan |
|---|--------|----------|----------------------|
| 1 | B.3 | Critical | `AutoAllocationEngine`/`SkipAlertResolver` exclude `perlu_ditinjau_ulang` |
| 2 | B.2 | Important | `PaymentService::guardAgainstInvalidTagihan()` + re-fetch query di `createWalletPayment()` exclude `perlu_ditinjau_ulang` |
| 3 | B.9 | Minor | `BatalkanTagihanAction` tolak pembatalan tagihan yang sedang ditinjau |
| 4 | B.10 | Critical | Aksi baru `KoreksiNominalTagihanAction` + form di halaman Perlu Ditinjau |
| 5 | B.1 | Critical | Field `priority_score` ("Prioritas Auto-Debit") di form Jenis Tagihan |
| 6 | B.8 | Important | Badge "Sedang Ditinjau" di halaman Jenis Tagihan Monitoring |
| 7 | B.6 | Minor | `AutoAllocationEngine` pakai `TagihanStatusResolver` (bukan logic inline) |
| 8 | B.5 | Minor | Samakan default `auto_debit_enabled` jadi `true` di 3 tempat |
| 9 | B.4 | Important (mitigasi) | Percepat cron `finance:reconcile-payments` dari `hourly()` ke `everyTwoMinutes()` |

**Urutan implementasi**: #1 dan #2 dulu (guard murni, saling independen, paling mendesak dari sisi risiko uang), lalu #3 (guard kecil terkait), lalu #4 (butuh #1-#3 sebagai konteks selesai dulu karena ini "jalan keluar" dari flag yang sekarang lebih ketat dijaga), lalu #5-#9 (independen satu sama lain, bisa paralel).

## 2. Detail Per Perbaikan

### 2.1 B.3 — `AutoAllocationEngine` & `SkipAlertResolver` exclude tagihan yang ditinjau

**File**: `app/Domains/Keuangan/Services/AutoAllocationEngine.php:35-43`, `app/Domains/Keuangan/Services/SkipAlertResolver.php:38-46`

Tambahkan `->where('tagihan.perlu_ditinjau_ulang', false)` (AutoAllocationEngine, prefix kolom karena ada join) dan `->where('perlu_ditinjau_ulang', false)` (SkipAlertResolver, tanpa join) ke query kandidat tagihan di kedua file — persis setelah baris `whereIn('tagihan.status', ['belum_bayar', 'sebagian'])` / `whereIn('status', ['belum_bayar', 'sebagian'])`.

**Efek**: tagihan yang di-flag tidak akan pernah dibayar otomatis oleh auto-debit, dan tidak dihitung sebagai kandidat "displayed skip" di banner dashboard (karena memang bukan kandidat pembayaran sama sekali sekarang, bukan "di-skip karena saldo kurang" — 2 makna yang beda, sengaja dipisah oleh filter ini).

### 2.2 B.2 — Guard `perlu_ditinjau_ulang` di titik commit pembayaran

**File**: `app/Domains/Keuangan/Services/PaymentService.php`

`guardAgainstInvalidTagihan()` (baris 264-271) dipakai oleh KELIMA method pembuatan pembayaran (`createQrisPayment`, `createManualPayment`, `createWalletPayment`, `createCashPayment`, dan transitif oleh `createQrisPaymentWithTopup`) — jadi cukup 1 perubahan di sini menutup celah untuk semua metode:

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

Tambahan khusus `createWalletPayment()` (baris 215-217) — re-fetch query juga harus exclude, karena guard di atas cuma jalan terhadap koleksi tagihan versi LAMA (sebelum lock wallet):

```php
$tagihans = Tagihan::whereIn('id', $tagihanIds)
    ->whereIn('status', ['belum_bayar', 'sebagian'])
    ->where('perlu_ditinjau_ulang', false)
    ->get();
```

(Baris `if ($tagihans->count() !== $tagihanIds->count())` sesudahnya SUDAH otomatis menangkap penurunan jumlah ini dan melempar `PaymentException` — tidak perlu diubah.)

### 2.3 B.9 — `BatalkanTagihanAction` tolak pembatalan tagihan yang ditinjau

**File**: `app/Domains/Keuangan/Actions/Tagihan/BatalkanTagihanAction.php`

Tambahkan 1 pengecekan SETELAH cek `status !== 'belum_bayar'` (urutan guard existing — ownership dulu, business rule sesudah — TIDAK BOLEH diubah, ada komentar eksplisit soal ini di file):

```php
if ($tagihan->perlu_ditinjau_ulang) {
    abort(422, 'Tagihan ini sedang ditinjau ulang, selesaikan peninjauannya dulu sebelum membatalkan.');
}
```

### 2.4 B.10 — Aksi Koreksi Manual Nominal untuk Tagihan Perlu Ditinjau

**File baru**: `app/Domains/Keuangan/Actions/Tagihan/KoreksiNominalTagihanAction.php`

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

**Kenapa cukup pakai `TagihanStatusResolver` tanpa cabang khusus overpayment**: kalau `net_amount` baru < `paid_amount` (kasus overpayment yang tadinya diblokir), `TagihanStatusResolver::resolve()` otomatis mengembalikan `'lunas'` (`paid_amount >= net_amount`) — status jadi benar dengan sendirinya. Selisih kelebihan bayar TIDAK di-refund otomatis (sistem ini tidak punya mekanisme refund di manapun) — admin melihatnya secara implisit dari selisih `paid_amount - net_amount` di halaman manapun yang menampilkan kedua angka itu (mis. halaman detail siswa, riwayat), dan urusan pengembalian dana tetap manual/offline. Ini keputusan sadar untuk tidak membangun sistem refund baru di paket perbaikan ini.

**Controller**: `TagihanController.php`, tambah method baru dekat `tandaiSelesaiDitinjau()`:

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

**Permission baru dibutuhkan** — dikonfirmasi lewat `grep`, `tagihan.edit` **belum ada sama sekali** di `database/seeders/PermissionSeeder.php` (yang ada cuma `tagihan.view`, `tagihan.buat-susulan`). Tambahkan sebagai langkah eksplisit:
1. `database/seeders/PermissionSeeder.php:49` — tambah `'tagihan.edit'` ke array permission (di sebelah `'tagihan.view', 'tagihan.buat-susulan'`).
2. `database/seeders/RoleSeeder.php:75` — tambah `'tagihan.edit'` ke daftar permission role `bendahara_lembaga` (di baris yang sama dengan `'tagihan.view', 'tagihan.buat-susulan'`).
3. Permission ini SENGAJA dipisah dari `tagihan.view` (dipakai `tandaiSelesaiDitinjau()`, existing, TIDAK diubah di paket ini) karena `koreksiNominal()` mengubah nominal uang, bukan cuma menghapus flag — pemisahan level akses yang wajar untuk operasi sensitif.

**Route** (`routes/admin/keuangan.php`, dekat `tagihan.selesai-ditinjau`):
```php
Route::post('tagihan/{tagihan}/koreksi-nominal', [TagihanController::class, 'koreksiNominal'])->name('tagihan.koreksi-nominal');
```

**View** (`resources/views/portals/lembaga/keuangan/tagihan/perlu-ditinjau.blade.php`): tambahkan form kecil (bisa modal atau expand-inline) di tiap baris, berisi 2 input (`total_tagihan`, `discount_amount`) pre-filled dengan nilai SAAT INI, submit ke route di atas. Tombol "Selesai Ditinjau" (existing, cuma hapus flag tanpa ubah nominal) TETAP DIPERTAHANKAN sebagai opsi terpisah untuk kasus admin sudah tahu nominal lama sebenarnya sudah benar dan cuma mau menghapus flag tanpa perubahan apa-apa (mis. false-positive review).

### 2.5 B.1 — Field `priority_score` di form Jenis Tagihan

**Backend**: `JenisTagihanController.php:488-496` (`baseRules()`), tambah 1 baris:
```php
'priority_score' => ['nullable', 'integer', 'min:0'],
```

**TIDAK PERLU** perubahan apapun di `JenisTagihanData`, `CreateJenisTagihanAction`, atau `UpdateJenisTagihanAction` — keduanya sudah `array_merge($data->attributes, [...])` lalu `JenisTagihan::create()/update($attributes)`, dan `priority_score` sudah `fillable` di model (`JenisTagihan.php:37`). Field yang lolos validasi otomatis ikut tersimpan.

**Form** (`resources/views/portals/lembaga/keuangan/jenis-tagihan/form.blade.php`), tambahkan input angka di sidebar "Identitas Tagihan", dekat blok "Bisa Dicicil" (TIDAK dibungkus `x-if="kategoriPpdb"` — field ini relevan untuk SEMUA kategori, termasuk PPDB kalaupun cicilan-nya sendiri sudah dibatasi):

```html
<div>
    <x-input-label value="Prioritas Auto-Debit" />
    <x-text-input type="number" min="0" name="priority_score" :value="old('priority_score', $jenisTagihan?->priority_score)" placeholder="mis. 1 (lebih kecil = lebih diprioritaskan)" class="mt-1.5" />
    <p class="mt-1 text-[10px] text-gray-400">Menentukan urutan tagihan mana yang dibayar lebih dulu saat wallet siswa di-top-up dengan auto-debit aktif. Angka lebih kecil = didahulukan. Kosongkan kalau tidak perlu urutan khusus.</p>
</div>
```

### 2.6 B.8 — Badge "Sedang Ditinjau" di Jenis Tagihan Monitoring

**File**: `resources/views/portals/lembaga/keuangan/jenis-tagihan/monitoring/index.blade.php:84-92` (badge status "Daftar Penerima")

`$tagihanPenerima` (dari `JenisTagihanMonitoringController.php:34-37`) sudah Eloquent model penuh — `perlu_ditinjau_ulang` sudah tersedia di tiap `$tagihan` tanpa perlu ubah query controller. Tambahkan badge KEDUA (bukan mengganti badge status yang ada) tepat di sebelahnya:

```blade
@if ($tagihan->perlu_ditinjau_ulang)
    <span class="ml-1 inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-700 border border-amber-200">Sedang Ditinjau</span>
@endif
```

### 2.7 B.6 — `AutoAllocationEngine` pakai `TagihanStatusResolver`

**File**: `app/Domains/Keuangan/Services/AutoAllocationEngine.php`

Constructor tambah `TagihanStatusResolver $statusResolver`. Ganti baris 99-104:
```php
$tagihan->paid_amount += $amount;
if ($tagihan->paid_amount >= $tagihan->net_amount) {
    $tagihan->status = 'lunas';
} else {
    $tagihan->status = 'sebagian';
}
```
jadi:
```php
$tagihan->paid_amount += $amount;
$tagihan->status = $this->statusResolver->resolve((float) $tagihan->paid_amount, (float) $tagihan->net_amount, $tagihan->status);
```

### 2.8 B.5 — Samakan default `auto_debit_enabled`

**File**: `app/Http/Controllers/Portal/Keuangan/DashboardController.php:54`, `app/Http/Controllers/Portal/Keuangan/TagihanController.php:32`

Ganti default dari `false` jadi `true` di KEDUA baris, supaya konsisten dengan `Wallet::topup()` (`Wallet.php:82`) yang MEMANG sudah `true` — ini pilihan yang tidak mengubah perilaku alokasi uang yang sudah berjalan (yang mengatur uang beneran adalah `Wallet::topup()`, tidak diubah), cuma memperbaiki tampilan banner supaya jujur mencerminkan default yang sebenarnya berlaku:

```php
$autoDebitEnabled = (bool) SystemSetting::getResolved('auto_debit_enabled', $activeSiswa->lembaga_id, true);
```

### 2.9 B.4 — Mitigasi sementara: percepat cron rekonsiliasi QRIS/VA

**File**: `routes/console.php:17`

```php
Schedule::command('finance:reconcile-payments')->everyTwoMinutes()->withoutOverlapping();
```

**Catatan**: ini MITIGASI, bukan perbaikan permanen — perbaikan permanen (webhook QRIS asli dari BRI SNAP, kalau memang didukung) perlu riset terpisah ke dokumentasi BRI SNAP dan TIDAK termasuk paket ini.

## 3. Non-Goals

- B.7 (inkonsistensi sinkron/queue trigger recalculate) — sengaja di-skip.
- Sistem refund/kredit-maju untuk kasus overpayment — di luar scope, dicatat implisit lewat selisih `paid_amount - net_amount`.
- Webhook QRIS permanen — perlu riset BRI SNAP terpisah, cuma mitigasi cron di paket ini.
- Perubahan apapun ke jalur PPDB.

## 4. Test Requirements

- B.3: `AutoAllocationEngine` tidak mengalokasikan dana ke tagihan `perlu_ditinjau_ulang=true` meski dia urutan prioritas tertinggi; `SkipAlertResolver` tidak menghitungnya sebagai kandidat sama sekali (bukan "displayed skip", tidak muncul di banner).
- B.2: `createWalletPayment()`/`createQrisPayment()`/`createManualPayment()`/`createCashPayment()` menolak (exception) tagihan yang `perlu_ditinjau_ulang=true`, termasuk kasus race (tagihan di-flag SETELAH request checkout dimulai tapi SEBELUM commit — test lewat manipulasi urutan langsung di test, bukan concurrency asli).
- B.9: `BatalkanTagihanAction` menolak (422) tagihan `belum_bayar` yang `perlu_ditinjau_ulang=true`.
- B.10: `KoreksiNominalTagihanAction` — kasus normal (net_amount baru >= paid_amount, status jadi sebagian/belum_bayar sesuai), kasus overpayment (net_amount baru < paid_amount, status otomatis jadi lunas), kasus tolak kalau tagihan tidak sedang `perlu_ditinjau_ulang`, guard `discount_amount <= total_tagihan` di validasi controller.
- B.1: validasi `priority_score` diterima nullable/integer, tersimpan lewat create & update, dan benar-benar dipakai urutan alokasi (test end-to-end: 2 Jenis Tagihan beda priority_score, auto-debit membayar yang priority_score lebih kecil dulu).
- B.8: badge "Sedang Ditinjau" muncul di halaman Monitoring untuk tagihan yang di-flag, tidak muncul untuk yang tidak.
- B.6: test `AutoAllocationEngine` menghasilkan status yang SAMA dengan yang akan dihasilkan `TagihanStatusResolver::resolve()` untuk kombinasi paid_amount/net_amount yang sama (regression guard terhadap drift, sekaligus early test untuk task 2.7 sendiri).
- B.5: test dashboard/tagihan-list orang tua menampilkan banner "Auto-Debit Aktif" ketika `SystemSetting` untuk `auto_debit_enabled` belum pernah di-set eksplisit (mengandalkan default).
- B.4: tidak perlu test otomatis (perubahan jadwal cron), cukup verifikasi manual `php artisan schedule:list` menunjukkan interval baru.
