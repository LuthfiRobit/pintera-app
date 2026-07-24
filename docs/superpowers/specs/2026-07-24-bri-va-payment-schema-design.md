# BRI Virtual Account — Schema & Service Scaffolding

## Konteks

Bagian dari Sub-project 4 (Pembayaran Biaya Pendaftaran) inisiatif SPMB account-first redesign — 3 dari 4 sub-project lain sudah shipped. Sistem pembayaran hari ini 100% manual: `PembayaranService::catatPembayaran()` selalu mencatat `metode = 'transfer_manual'`, pendaftar upload bukti transfer, admin verifikasi manual lewat `verifikasiPembayaran()`. Tidak ada integrasi payment gateway apa pun di codebase.

User ingin mengintegrasikan **BRI Virtual Account (BRIVA/H2H)** — institusi sudah terdaftar sebagai partner BRI, tapi dokumentasi API & kredensial resminya perlu diperoleh ulang dan belum ada di tangan saat spec ini ditulis. Karena itu, scope pekerjaan ini **secara eksplisit BUKAN integrasi API BRI yang sesungguhnya** — menebak format endpoint/autentikasi/webhook BRI tanpa dokumen asli akan menghasilkan kode yang salah. Scope-nya adalah menyiapkan skema data dan lapisan abstraksi service yang gateway-agnostic, supaya begitu dokumen BRI tersedia, sisa pekerjaan tinggal mengisi satu kelas implementasi tanpa migrasi skema lagi.

**Temuan penting saat eksplorasi kode**: kolom `pembayaran.metode` **sudah** punya nilai enum `'va_bri'` (dari migrasi awal `create_pembayaran_table`), tapi belum pernah benar-benar dipakai — `catatPembayaran()` selalu hardcode `'transfer_manual'`. Jadi sebagian "niat" untuk fitur ini sudah ada di skema lama, hanya belum ditindaklanjuti.

## Keputusan yang sudah dikunci (via AskUserQuestion)

1. **Cakupan**: VA hanya berlaku untuk pembayaran `Tagihan` penuh (lunas sekali bayar). Skema cicilan (`Cicilan`/`SkemaCicilan`) TETAP pakai transfer manual seperti sekarang — tidak disentuh sub-project ini.
2. **Struktur data**: tabel baru `virtual_account`, bukan kolom tambahan langsung di `tagihan`.
3. **Sumber pembayaran gateway**: `pembayaran.sumber` mendapat nilai enum baru `'gateway'` (selain `calon_siswa`/`admin` yang sudah ada), supaya pembayaran yang dikonfirmasi otomatis oleh sistem bank bisa dibedakan dari input manual siswa/admin di audit log.
4. **Scope UI**: TIDAK ADA perubahan UI/route/controller di portal maupun admin pada tahap ini. Backend saja — migrasi, model, dan interface service. Tombol "Bayar via VA" baru ditambahkan saat integrasi aslinya benar-benar dikerjakan (sub-project/tahap terpisah, di luar spec ini).

## Desain

### 1. Tabel `virtual_account`

```
id                   bigint, PK
tagihan_id           bigint, FK -> tagihan.id, cascadeOnDelete, NOT NULL
provider             string(30), NOT NULL          -- 'bri' hari ini; kolom ini yang membuat skema gateway-agnostic, bukan tabel/nama kelas terpisah per bank
nomor_va             string(30), NOT NULL, unique
status               enum('aktif','kedaluwarsa','dipakai'), default 'aktif'
kedaluwarsa_pada     timestamp, nullable
referensi_eksternal  string(100), nullable          -- ID transaksi dari BRI saat VA DIBUAT (create-VA response)
payload_permintaan   json, nullable                 -- request mentah yang dikirim ke API BRI, untuk audit/debug
payload_respons      json, nullable                 -- response mentah dari API BRI, untuk audit/debug
created_at / updated_at
```

**Bukan relasi 1:1 dengan `tagihan`** — satu `tagihan` boleh punya beberapa baris `virtual_account` sepanjang waktu (VA lama kedaluwarsa → digenerate ulang → baris baru), supaya riwayat regenerasi tidak hilang. Tidak ada unique constraint DB level pada `tagihan_id`; yang dijaga di level service adalah **hanya ada satu VA berstatus `aktif` per tagihan pada satu waktu** (lihat §3, `VirtualAccountRepository`-style logic langsung di service — lihat implementasi `buatVirtualAccount()`).

`nomor_va` unique secara global (lintas tagihan) karena nomor VA dari bank memang harus unik.

### 2. Model `VirtualAccount`

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

Tambah relasi baru di `Tagihan`: `public function virtualAccount(): HasMany` (banyak baris seiring waktu, lihat §1 — bukan `hasOne`, meskipun di setiap titik waktu praktiknya hanya satu yang `aktif`).

### 3. Perluasan tabel & model `pembayaran`

Migrasi baru menambah:
- Kolom `referensi_eksternal` (string, nullable) — ID transaksi dari notifikasi/webhook saat pembayaran BENAR-BENAR masuk (beda dari `virtual_account.referensi_eksternal` yang itu untuk saat VA dibuat, bukan saat dibayar).
- Nilai enum baru `'gateway'` pada kolom `sumber`.

**Catatan implementasi penting**: proyek ini TIDAK menginstal `doctrine/dbal`, jadi `$table->enum('sumber', [...])->change()` lewat Schema Builder Laravel tidak akan berfungsi (method `change()` untuk sebagian besar tipe kolom, termasuk enum, butuh `doctrine/dbal`). Migrasi perubahan enum harus pakai raw SQL:

```php
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
```

`metode` TIDAK diubah — nilai `'va_bri'` sudah ada dari migrasi awal.

Model `Pembayaran`: tambah `'referensi_eksternal'` ke `$fillable`.

### 4. Generalisasi `PembayaranService::catatPembayaran()`

Signature saat ini:
```php
public function catatPembayaran(?Tagihan $tagihan, ?Cicilan $cicilan, string $sumber, ?string $filePath, ?int $userId): Pembayaran
```

Signature baru (2 parameter opsional ditambahkan di akhir, backward-compatible — semua pemanggil existing di `TagihanController` tidak perlu berubah karena default-nya menghasilkan perilaku identik dengan sekarang):
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

Baris `'metode' => 'transfer_manual',` yang di-hardcode di dalam `Pembayaran::create([...])` diganti jadi `'metode' => $metode,`, dan tambah `'referensi_eksternal' => $referensiEksternal,`.

Baris `$statusAwal = $sumber === 'admin' ? 'lunas' : 'menunggu_verifikasi';` diubah jadi `$statusAwal = in_array($sumber, ['admin', 'gateway'], true) ? 'lunas' : 'menunggu_verifikasi';` — pembayaran dari gateway otomatis langsung `lunas` sama seperti fast-path admin, karena bank sudah mengkonfirmasi dananya diterima; tidak ada langkah verifikasi manual tambahan untuk sumber ini.

### 5. `PaymentGatewayInterface` + stub `BrivaGatewayService`

File baru: `app/Services/PaymentGateway/PaymentGatewayInterface.php`

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

File baru: `app/Services/PaymentGateway/BrivaGatewayService.php`

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

**Tidak di-bind ke service container (`AppServiceProvider`), tidak dipanggil dari controller/route manapun.** Kelas-kelas ini murni tersedia untuk dipakai nanti — mem-bind interface ke implementasi konkret di container adalah langkah pertama saat integrasi sungguhan dikerjakan, bukan bagian dari spec ini.

## Testing Plan

- Migrasi `virtual_account` bisa dijalankan (`up()`)/di-rollback (`down()`) tanpa error.
- Migrasi perubahan `pembayaran.sumber` (raw SQL) bisa dijalankan/di-rollback tanpa error; kolom `referensi_eksternal` baru ada dan nullable.
- `VirtualAccount` model: relasi `tagihan()` mengembalikan instance `Tagihan` yang benar; cast `payload_permintaan`/`payload_respons` menghasilkan array PHP, bukan string JSON mentah.
- `Tagihan::virtualAccount()` (HasMany) mengembalikan banyak baris jika beberapa VA dibuat untuk tagihan yang sama.
- **Regresi**: seluruh test existing untuk `PembayaranService::catatPembayaran()`/`verifikasiPembayaran()` (transfer manual, admin fast-path, cicilan) tetap lulus tanpa perubahan assertion — membuktikan parameter baru yang opsional benar-benar backward-compatible.
- Test baru: `catatPembayaran()` dipanggil dengan `sumber='gateway'` menghasilkan `Pembayaran` berstatus `lunas` langsung (sama seperti `sumber='admin'`), dengan `metode` dan `referensi_eksternal` yang di-passing tersimpan benar, dan `Tagihan`-nya ikut ter-update jadi `lunas` (lewat `tandaiLunas()` yang sudah ada, tidak diubah).
- `BrivaGatewayService::buatVirtualAccount()` dan `::tanganiNotifikasi()` masing-masing melempar `RuntimeException` dengan pesan yang mengandung teks "belum tersedia" — bukti eksplisit bahwa stub ini sengaja belum berfungsi, sekaligus regression guard kalau suatu saat isinya diisi tapi lupa dites ulang.

## Di luar scope (deferred)

- Integrasi API BRI yang sesungguhnya (endpoint, autentikasi, format notifikasi/signature) — menunggu dokumentasi resmi.
- Route webhook (`POST /api/payment/briva/callback` atau sejenisnya) — akan dibuat bersamaan dengan implementasi `BrivaGatewayService` yang sesungguhnya, bukan sekarang, karena tanpa isi nyata route ini cuma dead code.
- Perubahan UI portal (tombol "Bayar via VA", dsb) dan UI admin.
- Dukungan VA untuk `Cicilan` per-termin (hanya `Tagihan` penuh yang dicakup spec ini).
- Binding `PaymentGatewayInterface` ke container.
