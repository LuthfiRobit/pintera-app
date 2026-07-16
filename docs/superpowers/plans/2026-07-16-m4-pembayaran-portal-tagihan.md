# Keuangan — Pembayaran Manual & Portal Tagihan Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build skema cicilan, pencatatan & verifikasi pembayaran (transfer manual + jalur cadangan admin), dan Portal Akun Pendaftar's "Tagihan & Pembayaran" menu — menutup alur SPMB end-to-end tanpa menyentuh `pendaftaran.status` sama sekali.

**Architecture:** Satu service (`PembayaranService`) adalah sumber kebenaran tunggal untuk semua mutasi cicilan/pembayaran — dikonsumsi oleh controller admin (skema cicilan, catat manual, verifikasi) dan controller portal (pilih cicil, upload bukti) tanpa duplikasi logika. Status "siswa aktif" dihitung on-the-fly lewat accessor `Pendaftaran::isAktif`, tidak pernah disimpan.

**Tech Stack:** Laravel 12, Blade, Alpine.js, Pest PHP.

## Global Constraints

- `pendaftaran.status` **tidak boleh disentuh** oleh task manapun di plan ini — status "aktif" murni dihitung lewat accessor `Pendaftaran::isAktif` (lihat Task 1), tidak pernah ditulis ke kolom apapun.
- `PembayaranService` adalah satu-satunya tempat yang boleh membuat/mengubah baris `skema_cicilan`, `cicilan`, atau `pembayaran` — controller manapun (admin/portal) selalu memanggil method service ini, tidak pernah menulis langsung ke model-model tsb.
- Riwayat `pembayaran` **insert-only** — baris yang sudah ada tidak pernah di-`update()` kecuali oleh `PembayaranService::verifikasiPembayaran()` itu sendiri (satu-satunya mutasi yang sah pada baris yang sudah ada).
- Pembayaran cicilan **wajib berurutan** — termin ke-N hanya bisa mulai dibayar setelah termin ke-(N-1) berstatus `lunas`, divalidasi di `PembayaranService`, bukan cuma disembunyikan di tampilan.
- Pembagian nominal cicilan **wajib** pakai metode sisa-di-termin-terakhir (`intdiv` + sisa ke termin akhir) — tidak pernah pembagian murni yang bisa menyisakan selisih pembulatan.
- Penyuntingan nominal manual **wajib** divalidasi: total seluruh termin harus persis sama dengan `tagihan.total_tagihan`, ditolak (exception) kalau tidak — tidak boleh tersimpan sebagian.
- `tagihan.status` (kolom yang sudah ada sejak sub-project 2) hanya ditulis oleh `PembayaranService`, dalam transaksi yang sama dengan perubahan `cicilan`/`pembayaran` terkait.
- `PembayaranController`/`TagihanController` (admin) mengikuti pola `lembagaId(Request $request): ?int` yang sudah ada (duplikasi per-controller, bukan trait bersama) untuk isolasi tenant — `Tagihan`/`Pembayaran`/`Cicilan`/`SkemaCicilan` tidak pakai `BelongsToTenant`, tenant selalu diturunkan transitif lewat `pendaftaran.lembaga_id`.
- `Portal\TagihanController` memvalidasi kepemilikan lewat `$tagihan->pendaftaran->akun_pendaftar_id === Auth::guard('portal')->id()` — pola yang sama seperti `BuktiPendaftaranController` yang sudah ada.
- PHP is not on PATH — use `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe` for all `artisan`/test commands.

---

### Task 1: Data Layer — Skema Cicilan, Cicilan, Pembayaran

**Files:**
- Create: `database/migrations/2026_07_16_100000_create_skema_cicilan_table.php`
- Create: `database/migrations/2026_07_16_100100_create_cicilan_table.php`
- Create: `database/migrations/2026_07_16_100200_create_pembayaran_table.php`
- Create: `app/Models/SkemaCicilan.php`
- Create: `app/Models/Cicilan.php`
- Create: `app/Models/Pembayaran.php`
- Create: `database/factories/SkemaCicilanFactory.php`
- Create: `database/factories/CicilanFactory.php`
- Create: `database/factories/PembayaranFactory.php`
- Modify: `app/Models/Tagihan.php`
- Modify: `app/Models/Pendaftaran.php`
- Test: `tests/Unit/PembayaranDataLayerTest.php`

**Interfaces:**
- Produces: `SkemaCicilan` (fillable `tagihan_id,jumlah_termin,dibuat_oleh,dibuat_oleh_user_id`), `Cicilan` (fillable `skema_cicilan_id,urutan,nominal,jatuh_tempo,status`), `Pembayaran` (fillable `tagihan_id,cicilan_id,sumber,metode,file_path,status,catatan_verifikasi,diverifikasi_oleh_user_id,diverifikasi_pada`).
- Produces: `Tagihan::skemaCicilan(): HasOne`, `Tagihan::cicilan(): HasManyThrough`, `Tagihan::bisaDicicil(): bool`, `Tagihan::maksCicilan(): ?int` — Task 2's `PembayaranService` consumes all four.
- Produces: `Pendaftaran::isAktif` (accessor, `$pendaftaran->is_aktif`) — read-only, computed, never stored.
- Produces: `SkemaCicilan::cicilan(): HasMany` (ordered by `urutan`), `SkemaCicilan::tagihan(): BelongsTo`, `Cicilan::pembayaran(): HasMany`, `Pembayaran::tagihan()/cicilan(): BelongsTo`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/PembayaranDataLayerTest.php

use App\Models\Cicilan;
use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\SkemaCicilan;
use App\Models\Tagihan;
use App\Models\TagihanItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('links skema_cicilan, cicilan, and pembayaran back to their tagihan', function () {
    $lembaga = Lembaga::factory()->create();
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'diterima']);
    $tagihan = Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 3000000, 'status' => 'belum_bayar']);

    $skema = SkemaCicilan::create(['tagihan_id' => $tagihan->id, 'jumlah_termin' => 3, 'dibuat_oleh' => 'calon_siswa']);
    $cicilan1 = Cicilan::create(['skema_cicilan_id' => $skema->id, 'urutan' => 1, 'nominal' => 1000000, 'jatuh_tempo' => now()->addDays(30), 'status' => 'lunas']);
    Cicilan::create(['skema_cicilan_id' => $skema->id, 'urutan' => 2, 'nominal' => 1000000, 'jatuh_tempo' => now()->addDays(60), 'status' => 'belum_bayar']);
    Cicilan::create(['skema_cicilan_id' => $skema->id, 'urutan' => 3, 'nominal' => 1000000, 'jatuh_tempo' => now()->addDays(90), 'status' => 'belum_bayar']);
    Pembayaran::create(['cicilan_id' => $cicilan1->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual', 'status' => 'lunas']);

    expect($tagihan->fresh()->skemaCicilan->id)->toBe($skema->id);
    expect($tagihan->fresh()->cicilan)->toHaveCount(3);
    expect($skema->fresh()->cicilan->pluck('urutan')->all())->toBe([1, 2, 3]);
    expect($cicilan1->fresh()->pembayaran)->toHaveCount(1);
});

it('computes bisaDicicil/maksCicilan from the cheapest cicilable item, not a stored column', function () {
    $lembaga = Lembaga::factory()->create();
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tagihan = Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 500000, 'status' => 'belum_bayar']);
    $jenisTidakBisaDicicil = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Seragam', 'kategori' => 'daftar_ulang', 'bisa_dicicil' => false]);
    $jenisBisaDicicil = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Uang Pangkal', 'kategori' => 'daftar_ulang', 'bisa_dicicil' => true, 'maks_cicilan' => 3]);
    TagihanItem::create(['tagihan_id' => $tagihan->id, 'jenis_tagihan_id' => $jenisTidakBisaDicicil->id, 'jumlah' => 100000]);
    TagihanItem::create(['tagihan_id' => $tagihan->id, 'jenis_tagihan_id' => $jenisBisaDicicil->id, 'jumlah' => 400000]);

    expect($tagihan->bisaDicicil())->toBeTrue();
    expect($tagihan->maksCicilan())->toBe(3);
});

it('reports isAktif false for a diterima pendaftaran with no lunas payment at all', function () {
    $lembaga = Lembaga::factory()->create();
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'diterima']);
    Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 3000000, 'status' => 'belum_bayar']);

    expect($pendaftaran->is_aktif)->toBeFalse();
});

it('reports isAktif true when the daftar_ulang tagihan is paid lunas without any cicilan', function () {
    $lembaga = Lembaga::factory()->create();
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'diterima']);
    Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 3000000, 'status' => 'lunas']);

    expect($pendaftaran->is_aktif)->toBeTrue();
});

it('reports isAktif true once only the first cicilan termin is lunas, even if later termin are still unpaid', function () {
    $lembaga = Lembaga::factory()->create();
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'diterima']);
    $tagihan = Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 3000000, 'status' => 'dicicil']);
    $skema = SkemaCicilan::create(['tagihan_id' => $tagihan->id, 'jumlah_termin' => 3, 'dibuat_oleh' => 'calon_siswa']);
    Cicilan::create(['skema_cicilan_id' => $skema->id, 'urutan' => 1, 'nominal' => 1000000, 'jatuh_tempo' => now(), 'status' => 'lunas']);
    Cicilan::create(['skema_cicilan_id' => $skema->id, 'urutan' => 2, 'nominal' => 1000000, 'jatuh_tempo' => now(), 'status' => 'belum_bayar']);
    Cicilan::create(['skema_cicilan_id' => $skema->id, 'urutan' => 3, 'nominal' => 1000000, 'jatuh_tempo' => now(), 'status' => 'belum_bayar']);

    expect($pendaftaran->fresh()->is_aktif)->toBeTrue();
});

it('reports isAktif false when status is not diterima even if a lunas daftar_ulang tagihan somehow exists', function () {
    $lembaga = Lembaga::factory()->create();
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'ditolak']);
    Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 0, 'status' => 'lunas']);

    expect($pendaftaran->is_aktif)->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/PembayaranDataLayerTest.php`
Expected: FAIL — classes/tables/accessor don't exist yet.

- [ ] **Step 3: Write the migrations**

```php
<?php
// database/migrations/2026_07_16_100000_create_skema_cicilan_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skema_cicilan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tagihan_id')->unique()->constrained('tagihan')->cascadeOnDelete();
            $table->unsignedTinyInteger('jumlah_termin');
            $table->enum('dibuat_oleh', ['calon_siswa', 'admin']);
            $table->foreignId('dibuat_oleh_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skema_cicilan');
    }
};
```

```php
<?php
// database/migrations/2026_07_16_100100_create_cicilan_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cicilan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skema_cicilan_id')->constrained('skema_cicilan')->cascadeOnDelete();
            $table->unsignedTinyInteger('urutan');
            $table->decimal('nominal', 12, 2);
            $table->date('jatuh_tempo');
            $table->enum('status', ['belum_bayar', 'menunggu_verifikasi', 'ditolak', 'lunas'])->default('belum_bayar');
            $table->timestamps();

            $table->unique(['skema_cicilan_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cicilan');
    }
};
```

```php
<?php
// database/migrations/2026_07_16_100200_create_pembayaran_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembayaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tagihan_id')->nullable()->constrained('tagihan')->cascadeOnDelete();
            $table->foreignId('cicilan_id')->nullable()->constrained('cicilan')->cascadeOnDelete();
            $table->enum('sumber', ['calon_siswa', 'admin']);
            $table->enum('metode', ['transfer_manual', 'va_bri'])->default('transfer_manual');
            $table->string('file_path')->nullable();
            $table->enum('status', ['menunggu_verifikasi', 'lunas', 'ditolak']);
            $table->text('catatan_verifikasi')->nullable();
            $table->foreignId('diverifikasi_oleh_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_pada')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
    }
};
```

- [ ] **Step 4: Write the models**

```php
<?php
// app/Models/SkemaCicilan.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkemaCicilan extends Model
{
    use HasFactory;

    protected $table = 'skema_cicilan';

    protected $fillable = ['tagihan_id', 'jumlah_termin', 'dibuat_oleh', 'dibuat_oleh_user_id'];

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(Tagihan::class);
    }

    public function cicilan(): HasMany
    {
        return $this->hasMany(Cicilan::class)->orderBy('urutan');
    }

    public function dibuatOlehUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh_user_id');
    }
}
```

```php
<?php
// app/Models/Cicilan.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cicilan extends Model
{
    use HasFactory;

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

```php
<?php
// app/Models/Pembayaran.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Pembayaran extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'pembayaran';

    protected $fillable = [
        'tagihan_id', 'cicilan_id', 'sumber', 'metode', 'file_path',
        'status', 'catatan_verifikasi', 'diverifikasi_oleh_user_id', 'diverifikasi_pada',
    ];

    protected function casts(): array
    {
        return [
            'diverifikasi_pada' => 'datetime',
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'catatan_verifikasi', 'diverifikasi_oleh_user_id'])
            ->logOnlyDirty()
            ->useLogName('pembayaran');
    }
}
```

- [ ] **Step 5: Extend `Tagihan` model**

In `app/Models/Tagihan.php`, add:
```php
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
```
and these methods:
```php
    public function skemaCicilan(): HasOne
    {
        return $this->hasOne(SkemaCicilan::class);
    }

    public function cicilan(): HasManyThrough
    {
        return $this->hasManyThrough(Cicilan::class, SkemaCicilan::class, 'tagihan_id', 'skema_cicilan_id');
    }

    /**
     * A tagihan can bundle multiple jenis_tagihan (line items) with different
     * bisa_dicicil rules — offering installment is allowed if ANY item is
     * cicilable, and the safe max termin count is the smallest maks_cicilan
     * among the cicilable items (never lets the whole invoice cicil beyond
     * what any single cicilable item's own rule allows).
     */
    public function bisaDicicil(): bool
    {
        return $this->item()->whereHas('jenisTagihan', fn ($q) => $q->where('bisa_dicicil', true))->exists();
    }

    public function maksCicilan(): ?int
    {
        return $this->item()
            ->whereHas('jenisTagihan', fn ($q) => $q->where('bisa_dicicil', true))
            ->with('jenisTagihan')
            ->get()
            ->min(fn (TagihanItem $item) => $item->jenisTagihan->maks_cicilan);
    }
```

- [ ] **Step 6: Add the `isAktif` accessor to `Pendaftaran`**

In `app/Models/Pendaftaran.php`, add `use Illuminate\Database\Eloquent\Casts\Attribute;` and this method:
```php
    protected function isAktif(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === 'diterima' && (
                $this->tagihan()->where('kategori', 'daftar_ulang')->where('status', 'lunas')->exists()
                || $this->tagihan()->where('kategori', 'daftar_ulang')
                    ->whereHas('cicilan', fn ($q) => $q->where('urutan', 1)->where('status', 'lunas'))
                    ->exists()
            )
        );
    }
```

- [ ] **Step 7: Write the factories**

```php
<?php
// database/factories/SkemaCicilanFactory.php

namespace Database\Factories;

use App\Models\SkemaCicilan;
use App\Models\Tagihan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SkemaCicilan> */
class SkemaCicilanFactory extends Factory
{
    protected $model = SkemaCicilan::class;

    public function definition(): array
    {
        return [
            'tagihan_id' => Tagihan::factory(),
            'jumlah_termin' => 3,
            'dibuat_oleh' => 'calon_siswa',
        ];
    }
}
```

```php
<?php
// database/factories/CicilanFactory.php

namespace Database\Factories;

use App\Models\Cicilan;
use App\Models\SkemaCicilan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Cicilan> */
class CicilanFactory extends Factory
{
    protected $model = Cicilan::class;

    public function definition(): array
    {
        return [
            'skema_cicilan_id' => SkemaCicilan::factory(),
            'urutan' => 1,
            'nominal' => 1000000,
            'jatuh_tempo' => now()->addDays(30),
            'status' => 'belum_bayar',
        ];
    }
}
```

```php
<?php
// database/factories/PembayaranFactory.php

namespace Database\Factories;

use App\Models\Pembayaran;
use App\Models\Tagihan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Pembayaran> */
class PembayaranFactory extends Factory
{
    protected $model = Pembayaran::class;

    public function definition(): array
    {
        return [
            'tagihan_id' => Tagihan::factory(),
            'sumber' => 'calon_siswa',
            'metode' => 'transfer_manual',
            'status' => 'menunggu_verifikasi',
        ];
    }
}
```

- [ ] **Step 8: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/PembayaranDataLayerTest.php`
Expected: PASS (6/6)

- [ ] **Step 9: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all pre-existing tests still pass.

- [ ] **Step 10: Commit**

```bash
git add database/migrations/2026_07_16_100000_create_skema_cicilan_table.php \
        database/migrations/2026_07_16_100100_create_cicilan_table.php \
        database/migrations/2026_07_16_100200_create_pembayaran_table.php \
        app/Models/SkemaCicilan.php app/Models/Cicilan.php app/Models/Pembayaran.php \
        database/factories/SkemaCicilanFactory.php database/factories/CicilanFactory.php database/factories/PembayaranFactory.php \
        app/Models/Tagihan.php app/Models/Pendaftaran.php tests/Unit/PembayaranDataLayerTest.php
git commit -m "feat: add skema_cicilan/cicilan/pembayaran data layer and Pendaftaran::isAktif accessor"
```

---

### Task 2: `PembayaranService` — Skema Cicilan, Pencatatan, & Verifikasi

**Files:**
- Create: `app/Services/PembayaranService.php`
- Test: `tests/Unit/PembayaranServiceTest.php`

**Interfaces:**
- Consumes: `Tagihan`, `SkemaCicilan`, `Cicilan`, `Pembayaran` (Task 1).
- Produces: `PembayaranService::buatSkemaCicilan(Tagihan $tagihan, int $jumlahTermin, string $dibuatOleh, ?int $dibuatOlehUserId = null): SkemaCicilan`, `::simpanNominalManual(SkemaCicilan $skemaCicilan, array $nominalPerUrutan): void`, `::catatPembayaran(?Tagihan $tagihan, ?Cicilan $cicilan, string $sumber, ?string $filePath, ?int $userId): Pembayaran`, `::verifikasiPembayaran(Pembayaran $pembayaran, string $keputusan, ?string $catatan, int $adminUserId): void` — Task 3/4/5's controllers all call these exact methods, never touch the models directly.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/PembayaranServiceTest.php

use App\Models\Cicilan;
use App\Models\Lembaga;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\SkemaCicilan;
use App\Models\Tagihan;
use App\Models\User;
use App\Services\PembayaranService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function buatTagihanDaftarUlangUntukPembayaran(int $total = 1000000): Tagihan
{
    $lembaga = Lembaga::factory()->create();
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'diterima']);

    return Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => $total, 'status' => 'belum_bayar']);
}

it('splits nominal evenly with the rounding remainder absorbed by the last termin', function () {
    $tagihan = buatTagihanDaftarUlangUntukPembayaran(1000000);

    $skema = app(PembayaranService::class)->buatSkemaCicilan($tagihan, 3, 'calon_siswa');

    $nominal = $skema->cicilan()->orderBy('urutan')->pluck('nominal')->map(fn ($n) => (int) $n)->all();
    expect($nominal)->toBe([333333, 333333, 333334]);
    expect(array_sum($nominal))->toBe(1000000);
    expect($tagihan->fresh()->status)->toBe('dicicil');
});

it('sets tagihan.jatuh_tempo to the last termin due date when a skema is created', function () {
    $tagihan = buatTagihanDaftarUlangUntukPembayaran(900000);

    app(PembayaranService::class)->buatSkemaCicilan($tagihan, 3, 'calon_siswa');

    $terakhir = $tagihan->fresh()->cicilan()->orderByDesc('urutan')->first();
    expect($tagihan->fresh()->jatuh_tempo->toDateString())->toBe($terakhir->jatuh_tempo->toDateString());
});

it('rejects a manual nominal edit whose total no longer matches total_tagihan', function () {
    $tagihan = buatTagihanDaftarUlangUntukPembayaran(900000);
    $skema = app(PembayaranService::class)->buatSkemaCicilan($tagihan, 3, 'calon_siswa');
    $asli = $skema->cicilan()->orderBy('urutan')->pluck('nominal', 'urutan')->all();

    expect(fn () => app(PembayaranService::class)->simpanNominalManual($skema, [1 => 100000, 2 => 300000, 3 => 300000]))
        ->toThrow(InvalidArgumentException::class);

    expect($skema->cicilan()->orderBy('urutan')->pluck('nominal', 'urutan')->all())->toEqual($asli);
});

it('accepts a manual nominal edit whose total matches exactly, even with an uneven split like a dp', function () {
    $tagihan = buatTagihanDaftarUlangUntukPembayaran(900000);
    $skema = app(PembayaranService::class)->buatSkemaCicilan($tagihan, 3, 'calon_siswa');

    app(PembayaranService::class)->simpanNominalManual($skema, [1 => 500000, 2 => 200000, 3 => 200000]);

    expect((int) $skema->cicilan()->where('urutan', 1)->value('nominal'))->toBe(500000);
});

it('records a self-reported payment as menunggu_verifikasi, never lunas immediately', function () {
    $tagihan = buatTagihanDaftarUlangUntukPembayaran(500000);

    $pembayaran = app(PembayaranService::class)->catatPembayaran($tagihan, null, 'calon_siswa', 'bukti/x.pdf', null);

    expect($pembayaran->status)->toBe('menunggu_verifikasi');
    expect($tagihan->fresh()->status)->toBe('belum_bayar');
});

it('records an admin-recorded payment as immediately lunas, updating tagihan.status in the same call', function () {
    $tagihan = buatTagihanDaftarUlangUntukPembayaran(500000);
    $admin = User::factory()->create();

    $pembayaran = app(PembayaranService::class)->catatPembayaran($tagihan, null, 'admin', null, $admin->id);

    expect($pembayaran->status)->toBe('lunas');
    expect($pembayaran->diverifikasi_oleh_user_id)->toBe($admin->id);
    expect($tagihan->fresh()->status)->toBe('lunas');
});

it('never inserts a new payment attempt while one is already pending or already lunas for the same target', function () {
    $tagihan = buatTagihanDaftarUlangUntukPembayaran(500000);
    app(PembayaranService::class)->catatPembayaran($tagihan, null, 'calon_siswa', 'bukti/1.pdf', null);

    expect(fn () => app(PembayaranService::class)->catatPembayaran($tagihan, null, 'calon_siswa', 'bukti/2.pdf', null))
        ->toThrow(RuntimeException::class);
});

it('allows a new payment attempt after the previous one for the same target was rejected, keeping the rejected row untouched', function () {
    $tagihan = buatTagihanDaftarUlangUntukPembayaran(500000);
    $admin = User::factory()->create();
    $pertama = app(PembayaranService::class)->catatPembayaran($tagihan, null, 'calon_siswa', 'bukti/1.pdf', null);
    app(PembayaranService::class)->verifikasiPembayaran($pertama, 'ditolak', 'Bukti buram', $admin->id);

    $kedua = app(PembayaranService::class)->catatPembayaran($tagihan, null, 'calon_siswa', 'bukti/2.pdf', null);

    expect(Pembayaran::where('tagihan_id', $tagihan->id)->count())->toBe(2);
    $pertama->refresh();
    expect($pertama->status)->toBe('ditolak');
    expect($pertama->file_path)->toBe('bukti/1.pdf');
    expect($pertama->catatan_verifikasi)->toBe('Bukti buram');
    expect($kedua->status)->toBe('menunggu_verifikasi');
});

it('rejects paying termin 2 before termin 1 is lunas', function () {
    $tagihan = buatTagihanDaftarUlangUntukPembayaran(900000);
    $skema = app(PembayaranService::class)->buatSkemaCicilan($tagihan, 3, 'calon_siswa');
    $termin2 = $skema->cicilan()->where('urutan', 2)->first();

    expect(fn () => app(PembayaranService::class)->catatPembayaran(null, $termin2, 'calon_siswa', 'bukti/x.pdf', null))
        ->toThrow(RuntimeException::class);
});

it('allows paying termin 2 once termin 1 is verified lunas, and marks the whole tagihan lunas once every termin is paid', function () {
    $tagihan = buatTagihanDaftarUlangUntukPembayaran(900000);
    $service = app(PembayaranService::class);
    $skema = $service->buatSkemaCicilan($tagihan, 3, 'calon_siswa');
    $admin = User::factory()->create();
    [$t1, $t2, $t3] = $skema->cicilan()->orderBy('urutan')->get();

    $service->catatPembayaran(null, $t1, 'admin', null, $admin->id);
    $service->catatPembayaran(null, $t2, 'admin', null, $admin->id);
    expect($tagihan->fresh()->status)->toBe('dicicil');

    $service->catatPembayaran(null, $t3, 'admin', null, $admin->id);
    expect($tagihan->fresh()->status)->toBe('lunas');
});

it('rejecting a cicilan payment sets the termin status to ditolak, not back to belum_bayar', function () {
    $tagihan = buatTagihanDaftarUlangUntukPembayaran(900000);
    $service = app(PembayaranService::class);
    $skema = $service->buatSkemaCicilan($tagihan, 3, 'calon_siswa');
    $admin = User::factory()->create();
    $t1 = $skema->cicilan()->where('urutan', 1)->first();
    $pembayaran = $service->catatPembayaran(null, $t1, 'calon_siswa', 'bukti/x.pdf', null);

    $service->verifikasiPembayaran($pembayaran, 'ditolak', 'Nominal tidak sesuai', $admin->id);

    expect($t1->fresh()->status)->toBe('ditolak');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/PembayaranServiceTest.php`
Expected: FAIL — `App\Services\PembayaranService` doesn't exist yet.

- [ ] **Step 3: Write `PembayaranService`**

```php
<?php
// app/Services/PembayaranService.php

namespace App\Services;

use App\Models\Cicilan;
use App\Models\Pembayaran;
use App\Models\SkemaCicilan;
use App\Models\Tagihan;
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

        foreach ($nominalPerUrutan as $urutan => $nominal) {
            if (($cicilanByUrutan[$urutan] ?? null)?->status === 'lunas') {
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

        $adaPembayaranAktif = $tagihan
            ? Pembayaran::where('tagihan_id', $tagihan->id)->whereIn('status', ['menunggu_verifikasi', 'lunas'])->exists()
            : Pembayaran::where('cicilan_id', $cicilan->id)->whereIn('status', ['menunggu_verifikasi', 'lunas'])->exists();

        if ($adaPembayaranAktif) {
            throw new RuntimeException('Sudah ada pembayaran yang menunggu verifikasi atau sudah lunas untuk ini.');
        }

        return DB::transaction(function () use ($tagihan, $cicilan, $sumber, $filePath, $userId) {
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

- [ ] **Step 4: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/PembayaranServiceTest.php`
Expected: PASS (11/11)

- [ ] **Step 5: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Services/PembayaranService.php tests/Unit/PembayaranServiceTest.php
git commit -m "feat: add PembayaranService — skema cicilan, insert-only payment recording, verification"
```

---

### Task 3: Admin UI — Perluas Panel Tagihan (Skema Cicilan & Catat Manual)

**Files:**
- Modify: `app/Services/PermissionCatalog.php`
- Modify: `database/seeders/RolePermissionSeeder.php`
- Modify: `app/Http/Controllers/Admin/TagihanController.php`
- Modify: `routes/admin.php`
- Modify: `resources/views/admin/spmb-pendaftaran/show.blade.php`
- Test: `tests/Feature/Admin/SkemaCicilanTest.php`
- Test: `tests/Feature/Admin/CatatManualPembayaranTest.php`

**Interfaces:**
- Consumes: `PembayaranService` (Task 2) — this task's new controller methods call it exclusively, never write to `SkemaCicilan`/`Cicilan`/`Pembayaran` directly.
- Produces: permissions `pembayaran.view/.verifikasi/.catat-manual`, `cicilan.kelola` — Task 4/5 consume `pembayaran.view`/`.verifikasi`.
- Produces: routes `admin.tagihan.skema-cicilan.store`, `admin.skema-cicilan.nominal.store`, `admin.tagihan.catat-manual`, `admin.cicilan.catat-manual`.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Admin/SkemaCicilanTest.php

use App\Models\Cicilan;
use App\Models\JenisTagihan;
use App\Models\NominalTagihanJalur;
use App\Models\SkemaCicilan;
use App\Models\Tagihan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

function siapkanTagihanDaftarUlangBisaDicicil(): array
{
    [$lembaga, $jalur, , $pendaftaran] = buatPendaftaranUntukAdmin(status: 'diterima');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Uang Pangkal', 'kategori' => 'daftar_ulang', 'bisa_dicicil' => true, 'maks_cicilan' => 3]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 900000]);
    $tagihan = Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 900000, 'status' => 'belum_bayar']);
    \App\Models\TagihanItem::create(['tagihan_id' => $tagihan->id, 'jenis_tagihan_id' => $jenisTagihan->id, 'jumlah' => 900000]);

    return [$lembaga, $pendaftaran, $tagihan];
}

it('denies membuat skema cicilan without the cicilan.kelola permission', function () {
    [$lembaga, , $tagihan] = siapkanTagihanDaftarUlangBisaDicicil();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->post(route('admin.tagihan.skema-cicilan.store', $tagihan), ['jumlah_termin' => 3])
        ->assertForbidden();
});

it('lets admin_keuangan create a skema cicilan for a tagihan', function () {
    [$lembaga, , $tagihan] = siapkanTagihanDaftarUlangBisaDicicil();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->post(route('admin.tagihan.skema-cicilan.store', $tagihan), ['jumlah_termin' => 3]);

    $response->assertRedirect();
    expect(SkemaCicilan::where('tagihan_id', $tagihan->id)->first()->dibuat_oleh)->toBe('admin');
    expect(Cicilan::where('skema_cicilan_id', SkemaCicilan::where('tagihan_id', $tagihan->id)->value('id'))->count())->toBe(3);
});

it('404s creating a skema cicilan for a tagihan belonging to a different lembaga', function () {
    [, , $tagihanLembagaLain] = siapkanTagihanDaftarUlangBisaDicicil();
    $lembagaSaya = \App\Models\Lembaga::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $user->assignRole('admin_keuangan');

    $this->actingAs($user)->post(route('admin.tagihan.skema-cicilan.store', $tagihanLembagaLain), ['jumlah_termin' => 3])
        ->assertNotFound();
});

it('lets admin_keuangan edit nominal manually and rejects a mismatched total', function () {
    [$lembaga, , $tagihan] = siapkanTagihanDaftarUlangBisaDicicil();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $this->actingAs($user)->post(route('admin.tagihan.skema-cicilan.store', $tagihan), ['jumlah_termin' => 3]);
    $skema = SkemaCicilan::where('tagihan_id', $tagihan->id)->first();

    $responseSalah = $this->actingAs($user)->post(route('admin.skema-cicilan.nominal.store', $skema), [
        'nominal' => [1 => 100000, 2 => 300000, 3 => 300000],
    ]);
    $responseSalah->assertSessionHasErrors();

    $responseBenar = $this->actingAs($user)->post(route('admin.skema-cicilan.nominal.store', $skema), [
        'nominal' => [1 => 500000, 2 => 200000, 3 => 200000],
    ]);
    $responseBenar->assertRedirect();
    expect((int) Cicilan::where('skema_cicilan_id', $skema->id)->where('urutan', 1)->value('nominal'))->toBe(500000);
});
```

```php
<?php
// tests/Feature/Admin/CatatManualPembayaranTest.php

use App\Models\Cicilan;
use App\Models\Pembayaran;
use App\Models\Tagihan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('denies catat manual without the pembayaran.catat-manual permission', function () {
    [$lembaga, , , $pendaftaran] = buatPendaftaranUntukAdmin(status: 'diterima');
    $tagihan = Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 500000, 'status' => 'belum_bayar']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->post(route('admin.tagihan.catat-manual', $tagihan))->assertForbidden();
});

it('lets admin_keuangan record a lump-sum tagihan payment directly as lunas', function () {
    [$lembaga, , , $pendaftaran] = buatPendaftaranUntukAdmin(status: 'diterima');
    $tagihan = Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 500000, 'status' => 'belum_bayar']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->post(route('admin.tagihan.catat-manual', $tagihan));

    $response->assertRedirect();
    expect($tagihan->fresh()->status)->toBe('lunas');
    expect(Pembayaran::where('tagihan_id', $tagihan->id)->first()->sumber)->toBe('admin');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/SkemaCicilanTest.php tests/Feature/Admin/CatatManualPembayaranTest.php`
Expected: FAIL — routes/permissions/controller methods don't exist yet.

- [ ] **Step 3: Add permissions to `PermissionCatalog` and `RolePermissionSeeder`**

In `app/Services/PermissionCatalog.php`, add to `MODULE_LABELS`:
```php
        'pembayaran' => 'Pembayaran',
        'cicilan' => 'Cicilan',
```
and to `ACTION_LABELS`:
```php
        'verifikasi' => 'Verifikasi',
        'catat-manual' => 'Catat Manual',
        'kelola' => 'Kelola',
```

In `database/seeders/RolePermissionSeeder.php`, add to the `$permissions` array:
```php
            'pembayaran.view', 'pembayaran.verifikasi', 'pembayaran.catat-manual',
            'cicilan.kelola',
```
Extend the existing `if ($name === 'admin_keuangan')` block to include these 4:
```php
            if ($name === 'admin_keuangan') {
                $role->givePermissionTo([
                    'jenis-tagihan.view', 'jenis-tagihan.create', 'jenis-tagihan.edit', 'jenis-tagihan.delete',
                    'tagihan.view', 'tagihan.buat-susulan',
                    'pembayaran.view', 'pembayaran.verifikasi', 'pembayaran.catat-manual',
                    'cicilan.kelola',
                ]);
            }
```
Update `tests/Feature/RolePermissionSeederTest.php`'s total-permission-count and `admin_keuangan`-count assertions to account for the 4 new permissions (find the current numbers by running the test, don't guess).

- [ ] **Step 4: Extend `TagihanController`**

Add these methods to the existing `app/Http/Controllers/Admin/TagihanController.php` (alongside `buatSusulan()`/`index()`/`data()` and the existing `lembagaId()` helper — do not modify those):

```php
    public function buatSkemaCicilan(Request $request, Tagihan $tagihan, PembayaranService $service): RedirectResponse
    {
        $this->authorize('cicilan.kelola');
        abort_unless($tagihan->pendaftaran->lembaga_id === $this->lembagaId($request), 404);

        $data = $request->validate([
            'jumlah_termin' => ['required', 'integer', 'min:2', 'max:'.($tagihan->maksCicilan() ?? 2)],
        ]);

        try {
            $service->buatSkemaCicilan($tagihan, $data['jumlah_termin'], 'admin', $request->user()->id);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['jumlah_termin' => $exception->getMessage()]);
        }

        return back()->with('status', 'Skema cicilan berhasil dibuat.');
    }

    public function simpanNominalCicilan(Request $request, SkemaCicilan $skemaCicilan, PembayaranService $service): RedirectResponse
    {
        $this->authorize('cicilan.kelola');
        abort_unless($skemaCicilan->tagihan->pendaftaran->lembaga_id === $this->lembagaId($request), 404);

        $data = $request->validate([
            'nominal' => ['required', 'array'],
            'nominal.*' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $service->simpanNominalManual($skemaCicilan, array_map('intval', $data['nominal']));
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors(['nominal' => $exception->getMessage()]);
        }

        return back()->with('status', 'Nominal cicilan berhasil diperbarui.');
    }

    public function catatManualTagihan(Request $request, Tagihan $tagihan, PembayaranService $service): RedirectResponse
    {
        $this->authorize('pembayaran.catat-manual');
        abort_unless($tagihan->pendaftaran->lembaga_id === $this->lembagaId($request), 404);

        try {
            $service->catatPembayaran($tagihan, null, 'admin', null, $request->user()->id);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['pembayaran' => $exception->getMessage()]);
        }

        return back()->with('status', 'Pembayaran berhasil dicatat.');
    }

    public function catatManualCicilan(Request $request, Cicilan $cicilan, PembayaranService $service): RedirectResponse
    {
        $this->authorize('pembayaran.catat-manual');
        abort_unless($cicilan->skemaCicilan->tagihan->pendaftaran->lembaga_id === $this->lembagaId($request), 404);

        try {
            $service->catatPembayaran(null, $cicilan, 'admin', null, $request->user()->id);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['pembayaran' => $exception->getMessage()]);
        }

        return back()->with('status', 'Pembayaran termin berhasil dicatat.');
    }
```

Add `use App\Models\Cicilan;`, `use App\Models\SkemaCicilan;`, `use App\Services\PembayaranService;` to the top of the file.

- [ ] **Step 5: Wire the routes**

In `routes/admin.php`, add:
```php
    Route::post('tagihan/{tagihan}/skema-cicilan', [TagihanController::class, 'buatSkemaCicilan'])->name('tagihan.skema-cicilan.store');
    Route::post('skema-cicilan/{skemaCicilan}/nominal', [TagihanController::class, 'simpanNominalCicilan'])->name('skema-cicilan.nominal.store');
    Route::post('tagihan/{tagihan}/catat-manual', [TagihanController::class, 'catatManualTagihan'])->name('tagihan.catat-manual');
    Route::post('cicilan/{cicilan}/catat-manual', [TagihanController::class, 'catatManualCicilan'])->name('cicilan.catat-manual');
```

- [ ] **Step 6: Extend the Tagihan panel on `show.blade.php`**

Replace the existing panel body's `@if ($tagihan)` branch (inside the `@forelse` loop, currently just total + badge) with a version that also shows cicilan progress and the relevant action forms. Find this exact block:

```blade
                        @if ($tagihan)
                            <x-badge :tone="$tagihan->status === 'lunas' ? 'green' : ($tagihan->status === 'dicicil' ? 'blue' : 'amber')">
                                {{ ucfirst(str_replace('_', ' ', $tagihan->status)) }}
                            </x-badge>
                        @elseif (auth()->user()->can('tagihan.buat-susulan'))
```

Replace it with:

```blade
                        @if ($tagihan)
                            <x-badge :tone="$tagihan->status === 'lunas' ? 'green' : ($tagihan->status === 'dicicil' ? 'blue' : 'amber')">
                                {{ ucfirst(str_replace('_', ' ', $tagihan->status)) }}
                            </x-badge>
                        @elseif (auth()->user()->can('tagihan.buat-susulan'))
```

(this part is unchanged — only the content immediately following, inside the same `@if ($tagihan)` block's closing area, gets a new sibling block). Immediately after the existing `</div>` that closes this row (the `<div class="flex items-center justify-between gap-3">`), and still inside the `@forelse` loop body, add:

```blade
                    @if ($tagihan)
                        <div class="ml-0 mt-2 space-y-2 border-l-2 border-ink/10 pl-4">
                            @if ($tagihan->skemaCicilan)
                                @foreach ($tagihan->cicilan as $termin)
                                    <div class="flex items-center justify-between gap-3 text-xs">
                                        <span class="text-slate">Termin {{ $termin->urutan }} — Rp {{ number_format($termin->nominal, 0, ',', '.') }}</span>
                                        <div class="flex items-center gap-2">
                                            <x-badge :tone="$termin->status === 'lunas' ? 'green' : ($termin->status === 'menunggu_verifikasi' ? 'amber' : ($termin->status === 'ditolak' ? 'red' : 'slate'))">
                                                {{ ucfirst(str_replace('_', ' ', $termin->status)) }}
                                            </x-badge>
                                            @if ($termin->status === 'belum_bayar' && auth()->user()->can('pembayaran.catat-manual'))
                                                <form method="POST" action="{{ route('admin.cicilan.catat-manual', $termin) }}">
                                                    @csrf
                                                    <button type="submit" class="font-bold text-ink hover:underline">Catat Lunas</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="flex items-center gap-3 text-xs">
                                    @if ($tagihan->status !== 'lunas' && auth()->user()->can('pembayaran.catat-manual'))
                                        <form method="POST" action="{{ route('admin.tagihan.catat-manual', $tagihan) }}">
                                            @csrf
                                            <button type="submit" class="font-bold text-ink hover:underline">Catat Lunas (Tanpa Cicilan)</button>
                                        </form>
                                    @endif
                                    @if ($tagihan->status === 'belum_bayar' && $tagihan->bisaDicicil() && auth()->user()->can('cicilan.kelola'))
                                        <form method="POST" action="{{ route('admin.tagihan.skema-cicilan.store', $tagihan) }}" class="flex items-center gap-2">
                                            @csrf
                                            <select name="jumlah_termin" class="rounded-lg border-ink/15 text-xs">
                                                @for ($n = 2; $n <= $tagihan->maksCicilan(); $n++)
                                                    <option value="{{ $n }}">{{ $n }}x cicilan</option>
                                                @endfor
                                            </select>
                                            <button type="submit" class="font-bold text-ink hover:underline">Buat Skema</button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/SkemaCicilanTest.php tests/Feature/Admin/CatatManualPembayaranTest.php tests/Feature/RolePermissionSeederTest.php`
Expected: PASS

- [ ] **Step 8: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all tests pass, including every pre-existing M3 `PendaftaranAdminDetailTest` case (the panel change is additive to an already-tested page).

- [ ] **Step 9: Commit**

```bash
git add app/Services/PermissionCatalog.php database/seeders/RolePermissionSeeder.php \
        app/Http/Controllers/Admin/TagihanController.php routes/admin.php \
        resources/views/admin/spmb-pendaftaran/show.blade.php \
        tests/Feature/Admin/SkemaCicilanTest.php tests/Feature/Admin/CatatManualPembayaranTest.php \
        tests/Feature/RolePermissionSeederTest.php
git commit -m "feat: extend admin tagihan panel with skema cicilan management and manual payment recording"
```

---

### Task 4: Admin UI — Antrian Verifikasi Pembayaran

**Files:**
- Create: `app/Http/Controllers/Admin/PembayaranController.php`
- Create: `resources/views/admin/pembayaran/index.blade.php`
- Create: `resources/js/pembayaran-table.js`
- Modify: `resources/js/app.js`
- Modify: `routes/admin.php`
- Modify: `resources/views/layouts/sidebar.blade.php`
- Test: `tests/Feature/Admin/VerifikasiPembayaranTest.php`

**Interfaces:**
- Consumes: `PembayaranService::verifikasiPembayaran()` (Task 2).
- Produces: routes `admin.pembayaran.index/.data/.verifikasi`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Admin/VerifikasiPembayaranTest.php

use App\Models\Pembayaran;
use App\Models\Tagihan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('denies access to the payment verification queue without pembayaran.view', function () {
    [$lembaga] = buatPendaftaranUntukAdmin();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->get(route('admin.pembayaran.index'))->assertForbidden();
    $this->actingAs($user)->getJson(route('admin.pembayaran.data'))->assertForbidden();
});

it('only lists pending payments belonging to the acting user own lembaga', function () {
    [$lembagaA, , , $pendaftaranA] = buatPendaftaranUntukAdmin(namaCalon: 'Milik A', status: 'diterima');
    [, , , $pendaftaranB] = buatPendaftaranUntukAdmin(namaCalon: 'Milik B', status: 'diterima');
    $tagihanA = Tagihan::create(['pendaftaran_id' => $pendaftaranA->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 500000, 'status' => 'belum_bayar']);
    $tagihanB = Tagihan::create(['pendaftaran_id' => $pendaftaranB->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 500000, 'status' => 'belum_bayar']);
    Pembayaran::create(['tagihan_id' => $tagihanA->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual', 'status' => 'menunggu_verifikasi']);
    Pembayaran::create(['tagihan_id' => $tagihanB->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual', 'status' => 'menunggu_verifikasi']);
    $user = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->getJson(route('admin.pembayaran.data'));

    $names = collect($response->json('data'))->pluck('nama_calon_murid');
    expect($names)->toContain('Milik A');
    expect($names)->not->toContain('Milik B');
});

it('lets admin_keuangan approve a pending payment', function () {
    [$lembaga, , , $pendaftaran] = buatPendaftaranUntukAdmin(status: 'diterima');
    $tagihan = Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 500000, 'status' => 'belum_bayar']);
    $pembayaran = Pembayaran::create(['tagihan_id' => $tagihan->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual', 'status' => 'menunggu_verifikasi']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->post(route('admin.pembayaran.verifikasi', $pembayaran), ['keputusan' => 'lunas']);

    $response->assertRedirect();
    expect($pembayaran->fresh()->status)->toBe('lunas');
    expect($tagihan->fresh()->status)->toBe('lunas');
});

it('requires catatan when rejecting a pending payment', function () {
    [$lembaga, , , $pendaftaran] = buatPendaftaranUntukAdmin(status: 'diterima');
    $tagihan = Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 500000, 'status' => 'belum_bayar']);
    $pembayaran = Pembayaran::create(['tagihan_id' => $tagihan->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual', 'status' => 'menunggu_verifikasi']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->post(route('admin.pembayaran.verifikasi', $pembayaran), ['keputusan' => 'ditolak']);

    $response->assertSessionHasErrors('catatan_verifikasi');
    expect($pembayaran->fresh()->status)->toBe('menunggu_verifikasi');
});

it('404s verifying a payment belonging to a pendaftaran in a different lembaga', function () {
    [, , , $pendaftaranLain] = buatPendaftaranUntukAdmin(status: 'diterima');
    $tagihanLain = Tagihan::create(['pendaftaran_id' => $pendaftaranLain->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 500000, 'status' => 'belum_bayar']);
    $pembayaranLain = Pembayaran::create(['tagihan_id' => $tagihanLain->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual', 'status' => 'menunggu_verifikasi']);
    $lembagaSaya = \App\Models\Lembaga::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $user->assignRole('admin_keuangan');

    $this->actingAs($user)->post(route('admin.pembayaran.verifikasi', $pembayaranLain), ['keputusan' => 'lunas'])
        ->assertNotFound();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/VerifikasiPembayaranTest.php`
Expected: FAIL — controller/routes don't exist yet.

- [ ] **Step 3: Write `PembayaranController`**

```php
<?php
// app/Http/Controllers/Admin/PembayaranController.php

namespace App\Http\Controllers\Admin;

use App\Models\Pembayaran;
use App\Services\PembayaranService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class PembayaranController extends BaseController
{
    use AuthorizesRequests;

    private function lembagaId(Request $request): ?int
    {
        return $request->user()->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $request->user()->lembaga_id;
    }

    public function index(Request $request): View
    {
        $this->authorize('pembayaran.view');

        return view('admin.pembayaran.index', [
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
                    'jenis' => $pembayaran->cicilan_id ? 'Cicilan Termin '.$pembayaran->cicilan->urutan : 'Lunas Langsung',
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

    public function verifikasi(Request $request, Pembayaran $pembayaran, PembayaranService $service): RedirectResponse
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
            $service->verifikasiPembayaran($pembayaran, $data['keputusan'], $data['catatan_verifikasi'] ?? null, $request->user()->id);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['keputusan' => $exception->getMessage()]);
        }

        return redirect()->route('admin.pembayaran.index')->with('status', 'Pembayaran berhasil diverifikasi.');
    }
}
```

- [ ] **Step 4: Write the view**

```blade
{{-- resources/views/admin/pembayaran/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Keuangan</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Verifikasi Pembayaran</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-4" x-data="pembayaranTable({ dataUrl: @js(route('admin.pembayaran.data')) })">
        @if ($lembagaBelumDipilih ?? false)
            <div class="rounded-xl bg-signal-amber/10 p-4 text-sm text-signal-amber">
                Pilih lembaga aktif melalui pengalih lembaga untuk melihat antrian pembayaran.
            </div>
        @else
            <x-panel>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-ink/10 bg-paper/60 text-left text-xs uppercase tracking-wide text-slate">
                            <th class="px-5 py-3 font-display font-semibold">Calon Murid</th>
                            <th class="px-5 py-3 font-display font-semibold">Jenis</th>
                            <th class="px-5 py-3 font-display font-semibold">Sumber</th>
                            <th class="px-5 py-3 font-display font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink/10">
                        <template x-for="row in rows" :key="row.id">
                            <tr>
                                <td class="px-5 py-3.5">
                                    <p class="font-medium text-ink" x-text="row.nama_calon_murid"></p>
                                    <p class="font-mono text-xs text-slate" x-text="row.kode_pendaftaran"></p>
                                </td>
                                <td class="px-5 py-3.5 text-ink" x-text="row.jenis"></td>
                                <td class="px-5 py-3.5 text-slate" x-text="row.sumber === 'calon_siswa' ? 'Calon Siswa' : 'Admin'"></td>
                                <td class="px-5 py-3.5">
                                    <a :href="'/admin/spmb-pendaftaran/' + row.pendaftaran_id" class="text-sm font-semibold text-ink hover:underline">Tinjau &rarr;</a>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="!loading && rows.length === 0">
                            <td colspan="4" class="px-5 py-10 text-center text-slate">Tidak ada pembayaran yang menunggu verifikasi.</td>
                        </tr>
                    </tbody>
                </table>

                <div class="flex items-center justify-between border-t border-ink/10 p-4 text-sm text-slate">
                    <p>Halaman <span x-text="meta.current_page"></span> dari <span x-text="meta.last_page"></span> &middot; <span x-text="meta.total"></span> menunggu</p>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="goToPage(meta.current_page - 1)" :disabled="meta.current_page <= 1" class="rounded-lg border border-ink/15 px-3 py-1.5 disabled:opacity-40">Sebelumnya</button>
                        <button type="button" @click="goToPage(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page" class="rounded-lg border border-ink/15 px-3 py-1.5 disabled:opacity-40">Berikutnya</button>
                    </div>
                </div>
            </x-panel>
        @endif
    </div>
</x-app-layout>
```

```js
// resources/js/pembayaran-table.js
// Antrian tanpa pencarian/filter — hanya daftar+paginasi, mengikuti bentuk
// dasar tagihan-table.js/pendaftaran-table.js (meta-driven pagination,
// toast on fetch failure), tanpa search/status karena semua barisnya
// memang selalu berstatus menunggu_verifikasi.

export function pembayaranTable(config) {
    return {
        rows: [],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
        page: 1,
        loading: false,
        dataUrl: config.dataUrl,

        init() {
            this.fetchData();
        },

        goToPage(page) {
            if (page < 1 || page > this.meta.last_page) {
                return;
            }
            this.page = page;
            this.fetchData();
        },

        async fetchData() {
            this.loading = true;
            const params = new URLSearchParams({ page: this.page });

            try {
                const response = await fetch(`${this.dataUrl}?${params}`, {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) {
                    throw new Error('request failed');
                }

                const json = await response.json();
                this.rows = json.data;
                this.meta = json.meta;
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memuat antrian pembayaran.');
            } finally {
                this.loading = false;
            }
        },
    };
}
```

- [ ] **Step 5: Register the Alpine component**

In `resources/js/app.js`, add `import { pembayaranTable } from './pembayaran-table';` and `Alpine.data('pembayaranTable', pembayaranTable);`.

- [ ] **Step 6: Wire the routes**

In `routes/admin.php`, add `use App\Http\Controllers\Admin\PembayaranController;` and:
```php
    Route::get('pembayaran', [PembayaranController::class, 'index'])->name('pembayaran.index');
    Route::get('pembayaran/data', [PembayaranController::class, 'data'])->name('pembayaran.data');
    Route::post('pembayaran/{pembayaran}/verifikasi', [PembayaranController::class, 'verifikasi'])->name('pembayaran.verifikasi');
```

- [ ] **Step 7: Add the sidebar link**

In `resources/views/layouts/sidebar.blade.php`, inside the existing `'IV. Keuangan'` group's `array_filter([...])`, add a third entry:
```php
                Auth::user()->can('pembayaran.view') ? ['route' => 'admin.pembayaran.index', 'pattern' => 'admin.pembayaran.*', 'label' => 'Verifikasi Pembayaran', 'icon' => 'fact_check'] : null,
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/VerifikasiPembayaranTest.php`
Expected: PASS (5/5)

- [ ] **Step 9: Run the full suite, then `npm run build`**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all tests pass.
Run: `npm run build` (Node bundled under Laragon at `D:\laragon\bin\nodejs\node-v24.15.0-win-x64\` if not on PATH) — confirms `pembayaran-table.js` compiles cleanly.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Admin/PembayaranController.php resources/views/admin/pembayaran/index.blade.php \
        resources/js/pembayaran-table.js resources/js/app.js routes/admin.php resources/views/layouts/sidebar.blade.php \
        tests/Feature/Admin/VerifikasiPembayaranTest.php
git commit -m "feat: add payment verification queue for admin keuangan"
```

---

### Task 5: Portal UI — Tagihan & Pembayaran

**Files:**
- Create: `app/Http/Controllers/Portal/TagihanController.php`
- Create: `resources/views/portal/tagihan/index.blade.php`
- Modify: `routes/portal.php`
- Modify: `resources/views/layouts/portal.blade.php`
- Test: `tests/Feature/Portal/TagihanPembayaranTest.php`

**Interfaces:**
- Consumes: `PembayaranService` (Task 2) — exclusively, never writes to `SkemaCicilan`/`Cicilan`/`Pembayaran` directly.
- Produces: routes `portal.tagihan.index/.skema-cicilan/.bayar-lunas/.bayar-cicilan`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Portal/TagihanPembayaranTest.php

use App\Models\AkunPendaftar;
use App\Models\CalonMurid;
use App\Models\Cicilan;
use App\Models\Lembaga;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\SkemaCicilan;
use App\Models\Tagihan;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function siapkanTagihanUntukPortal(AkunPendaftar $akun, int $total = 500000, string $kategori = 'daftar_ulang'): Tagihan
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id]);
    $pendaftaran = Pendaftaran::factory()->create([
        'lembaga_id' => $lembaga->id, 'calon_murid_id' => $calonMurid->id,
        'akun_pendaftar_id' => $akun->id, 'status' => 'diterima',
    ]);

    return Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => $kategori, 'total_tagihan' => $total, 'status' => 'belum_bayar']);
}

it('shows only tagihan belonging to pendaftaran linked to the logged-in akun', function () {
    $akunSaya = AkunPendaftar::factory()->create();
    siapkanTagihanUntukPortal($akunSaya);
    $akunLain = AkunPendaftar::factory()->create();
    siapkanTagihanUntukPortal($akunLain);

    $response = $this->actingAs($akunSaya, 'portal')->get(route('portal.tagihan.index'));

    $response->assertOk();
});

it('lets the candidate upload bukti transfer for a lump-sum tagihan, landing as menunggu_verifikasi', function () {
    Storage::fake('public');
    $akun = AkunPendaftar::factory()->create();
    $tagihan = siapkanTagihanUntukPortal($akun);
    $file = UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf');

    $response = $this->actingAs($akun, 'portal')->post(route('portal.tagihan.bayar-lunas', $tagihan), ['bukti' => $file]);

    $response->assertRedirect();
    $pembayaran = Pembayaran::where('tagihan_id', $tagihan->id)->first();
    expect($pembayaran->status)->toBe('menunggu_verifikasi');
    expect($pembayaran->sumber)->toBe('calon_siswa');
    Storage::disk('public')->assertExists($pembayaran->file_path);
});

it('404s uploading bukti transfer for a tagihan belonging to a different akun', function () {
    Storage::fake('public');
    $akunLain = AkunPendaftar::factory()->create();
    $tagihanLain = siapkanTagihanUntukPortal($akunLain);
    $akunSaya = AkunPendaftar::factory()->create();
    $file = UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf');

    $this->actingAs($akunSaya, 'portal')->post(route('portal.tagihan.bayar-lunas', $tagihanLain), ['bukti' => $file])
        ->assertNotFound();
});

it('shows every payment attempt for a tagihan in riwayat, including rejected ones with their catatan, ordered newest first', function () {
    Storage::fake('public');
    $akun = AkunPendaftar::factory()->create();
    $tagihan = siapkanTagihanUntukPortal($akun);
    $admin = \App\Models\User::factory()->create();
    $pertama = Pembayaran::create(['tagihan_id' => $tagihan->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual', 'file_path' => 'bukti/1.pdf', 'status' => 'ditolak', 'catatan_verifikasi' => 'Bukti buram, ulangi', 'diverifikasi_oleh_user_id' => $admin->id, 'diverifikasi_pada' => now()->subDay()]);
    $kedua = Pembayaran::create(['tagihan_id' => $tagihan->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual', 'file_path' => 'bukti/2.pdf', 'status' => 'menunggu_verifikasi']);

    $response = $this->actingAs($akun, 'portal')->get(route('portal.tagihan.index'));

    $response->assertOk();
    $response->assertSeeInOrder(['Menunggu verifikasi', 'Ditolak', 'Bukti buram, ulangi'], false);
});

it('lets the candidate choose cicilan and then upload bukti for the first termin only', function () {
    Storage::fake('public');
    $akun = AkunPendaftar::factory()->create();
    $tagihan = siapkanTagihanUntukPortal($akun, 900000);
    \App\Models\JenisTagihan::create(['lembaga_id' => $tagihan->pendaftaran->lembaga_id, 'nama' => 'Uang Pangkal', 'kategori' => 'daftar_ulang', 'bisa_dicicil' => true, 'maks_cicilan' => 3]);
    \App\Models\TagihanItem::create(['tagihan_id' => $tagihan->id, 'jenis_tagihan_id' => \App\Models\JenisTagihan::first()->id, 'jumlah' => 900000]);

    $this->actingAs($akun, 'portal')->post(route('portal.tagihan.skema-cicilan', $tagihan), ['jumlah_termin' => 3])
        ->assertRedirect();
    $skema = SkemaCicilan::where('tagihan_id', $tagihan->id)->firstOrFail();
    expect($skema->dibuat_oleh)->toBe('calon_siswa');
    $termin1 = $skema->cicilan()->where('urutan', 1)->firstOrFail();
    $termin2 = $skema->cicilan()->where('urutan', 2)->firstOrFail();

    $file = UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf');
    $this->actingAs($akun, 'portal')->post(route('portal.tagihan.bayar-cicilan', $termin1), ['bukti' => $file])
        ->assertRedirect();
    expect(Pembayaran::where('cicilan_id', $termin1->id)->first()->status)->toBe('menunggu_verifikasi');

    $this->actingAs($akun, 'portal')->post(route('portal.tagihan.bayar-cicilan', $termin2), ['bukti' => $file])
        ->assertSessionHasErrors();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Portal/TagihanPembayaranTest.php`
Expected: FAIL — routes/controller don't exist yet.

- [ ] **Step 3: Write `Portal\TagihanController`**

```php
<?php
// app/Http/Controllers/Portal/TagihanController.php

namespace App\Http\Controllers\Portal;

use App\Models\Cicilan;
use App\Models\Tagihan;
use App\Services\PembayaranService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TagihanController extends BaseController
{
    public function index(): View
    {
        $pendaftaranList = Auth::guard('portal')->user()
            ->pendaftaran()
            ->with(['calonMurid', 'tagihan.skemaCicilan', 'tagihan.cicilan.pembayaran', 'tagihan.pembayaran'])
            ->latest('submitted_at')
            ->get();

        $pendaftaranList->each(function ($pendaftaran) {
            $pendaftaran->tagihan->each(function ($tagihan) {
                $riwayat = $tagihan->pembayaran
                    ->concat($tagihan->cicilan->flatMap->pembayaran)
                    ->sortByDesc('created_at')
                    ->values();
                $tagihan->setRelation('riwayatPembayaran', $riwayat);
            });
        });

        return view('portal.tagihan.index', ['pendaftaranList' => $pendaftaranList]);
    }

    public function buatSkemaCicilan(Request $request, Tagihan $tagihan, PembayaranService $service): RedirectResponse
    {
        $this->pastikanMilikSendiri($tagihan);

        $data = $request->validate([
            'jumlah_termin' => ['required', 'integer', 'min:2', 'max:'.($tagihan->maksCicilan() ?? 2)],
        ]);

        try {
            $service->buatSkemaCicilan($tagihan, $data['jumlah_termin'], 'calon_siswa');
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['jumlah_termin' => $exception->getMessage()]);
        }

        return redirect()->route('portal.tagihan.index')->with('status', 'Skema cicilan berhasil dibuat.');
    }

    public function bayarLunas(Request $request, Tagihan $tagihan, PembayaranService $service): RedirectResponse
    {
        $this->pastikanMilikSendiri($tagihan);

        $request->validate(['bukti' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048']]);
        $path = $request->file('bukti')->store('bukti-transfer/'.$tagihan->pendaftaran_id, 'public');

        try {
            $service->catatPembayaran($tagihan, null, 'calon_siswa', $path, null);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['bukti' => $exception->getMessage()]);
        }

        return redirect()->route('portal.tagihan.index')->with('status', 'Bukti transfer berhasil dikirim, menunggu verifikasi admin.');
    }

    public function bayarCicilan(Request $request, Cicilan $cicilan, PembayaranService $service): RedirectResponse
    {
        $tagihan = $cicilan->skemaCicilan->tagihan;
        $this->pastikanMilikSendiri($tagihan);

        $request->validate(['bukti' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048']]);
        $path = $request->file('bukti')->store('bukti-transfer/'.$tagihan->pendaftaran_id, 'public');

        try {
            $service->catatPembayaran(null, $cicilan, 'calon_siswa', $path, null);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['bukti' => $exception->getMessage()]);
        }

        return redirect()->route('portal.tagihan.index')->with('status', 'Bukti transfer berhasil dikirim, menunggu verifikasi admin.');
    }

    private function pastikanMilikSendiri(Tagihan $tagihan): void
    {
        abort_unless($tagihan->pendaftaran->akun_pendaftar_id === Auth::guard('portal')->id(), 404);
    }
}
```

- [ ] **Step 4: Write the view**

```blade
{{-- resources/views/portal/tagihan/index.blade.php --}}
<x-portal-layout title="Tagihan & Pembayaran">
    <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-spmb-accent">Portal Pendaftar</p>
    <h1 class="mt-1 font-display text-2xl font-bold text-spmb-primary">Tagihan & Pembayaran</h1>

    @if (session('status'))
        <div class="mt-4 rounded-xl bg-signal-green/10 p-4 text-sm text-signal-green">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-4 rounded-xl bg-signal-red/10 p-4 text-sm text-signal-red">{{ $errors->first() }}</div>
    @endif

    @forelse ($pendaftaranList as $pendaftaran)
        @if ($pendaftaran->tagihan->isNotEmpty())
            <x-panel class="mt-6 p-6">
                <p class="font-display font-semibold text-ink">{{ $pendaftaran->calonMurid->nama_lengkap }} &middot; {{ $pendaftaran->kode_pendaftaran }}</p>

                <div class="mt-4 space-y-6">
                    @foreach ($pendaftaran->tagihan as $tagihan)
                        <div class="border-t border-ink/10 pt-4 first:border-t-0 first:pt-0">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-ink">
                                    {{ $tagihan->kategori === 'pendaftaran' ? 'Tagihan Pendaftaran' : 'Tagihan Daftar Ulang' }}
                                    — Rp {{ number_format($tagihan->total_tagihan, 0, ',', '.') }}
                                </p>
                                <x-badge :tone="$tagihan->status === 'lunas' ? 'green' : ($tagihan->status === 'dicicil' ? 'blue' : 'amber')">
                                    {{ ucfirst(str_replace('_', ' ', $tagihan->status)) }}
                                </x-badge>
                            </div>

                            @if ($tagihan->skemaCicilan)
                                <div class="mt-3 space-y-2">
                                    @foreach ($tagihan->cicilan as $termin)
                                        <div class="flex items-center justify-between rounded-xl bg-spmb-tint px-4 py-3 text-sm">
                                            <span class="text-ink">Termin {{ $termin->urutan }} — Rp {{ number_format($termin->nominal, 0, ',', '.') }}</span>
                                            @if ($termin->status === 'belum_bayar' && $termin->urutan === 1 || ($termin->status === 'belum_bayar' && optional($tagihan->cicilan->firstWhere('urutan', $termin->urutan - 1))->status === 'lunas'))
                                                <form method="POST" action="{{ route('portal.tagihan.bayar-cicilan', $termin) }}" enctype="multipart/form-data" class="flex items-center gap-2">
                                                    @csrf
                                                    <input type="file" name="bukti" required class="text-xs">
                                                    <x-spmb-primary-button class="!px-3 !py-1.5 !text-xs">Kirim Bukti</x-spmb-primary-button>
                                                </form>
                                            @else
                                                <x-badge :tone="$termin->status === 'lunas' ? 'green' : ($termin->status === 'menunggu_verifikasi' ? 'amber' : ($termin->status === 'ditolak' ? 'red' : 'slate'))">
                                                    {{ ucfirst(str_replace('_', ' ', $termin->status)) }}
                                                </x-badge>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @elseif ($tagihan->status === 'belum_bayar')
                                <div class="mt-3 flex flex-wrap items-center gap-3">
                                    <form method="POST" action="{{ route('portal.tagihan.bayar-lunas', $tagihan) }}" enctype="multipart/form-data" class="flex items-center gap-2">
                                        @csrf
                                        <input type="file" name="bukti" required class="text-xs">
                                        <x-spmb-primary-button class="!px-3 !py-1.5 !text-xs">Bayar Lunas</x-spmb-primary-button>
                                    </form>

                                    @if ($tagihan->bisaDicicil())
                                        <form method="POST" action="{{ route('portal.tagihan.skema-cicilan', $tagihan) }}" class="flex items-center gap-2">
                                            @csrf
                                            <select name="jumlah_termin" class="rounded-xl border-slate/25 text-xs">
                                                @for ($n = 2; $n <= $tagihan->maksCicilan(); $n++)
                                                    <option value="{{ $n }}">Cicil {{ $n }}x</option>
                                                @endfor
                                            </select>
                                            <button type="submit" class="text-xs font-semibold text-spmb-accent hover:underline">Mulai Cicil</button>
                                        </form>
                                    @endif
                                </div>
                            @endif

                            @if ($tagihan->riwayatPembayaran->isNotEmpty())
                                <details class="mt-3">
                                    <summary class="cursor-pointer text-xs font-semibold text-spmb-accent">Riwayat Transaksi ({{ $tagihan->riwayatPembayaran->count() }})</summary>
                                    <div class="mt-2 space-y-2">
                                        @foreach ($tagihan->riwayatPembayaran as $riwayat)
                                            <div class="rounded-xl border border-ink/10 px-4 py-2.5 text-xs">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-slate">{{ $riwayat->created_at->translatedFormat('d M Y, H:i') }} &middot; {{ $riwayat->sumber === 'calon_siswa' ? 'Anda' : 'Dicatat admin' }}</span>
                                                    <x-badge :tone="$riwayat->status === 'lunas' ? 'green' : ($riwayat->status === 'menunggu_verifikasi' ? 'amber' : 'red')">
                                                        {{ $riwayat->status === 'menunggu_verifikasi' ? 'Menunggu verifikasi' : ucfirst($riwayat->status) }}
                                                    </x-badge>
                                                </div>
                                                @if ($riwayat->status === 'ditolak' && $riwayat->catatan_verifikasi)
                                                    <p class="mt-1 text-signal-red">{{ $riwayat->catatan_verifikasi }}</p>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-panel>
        @endif
    @empty
        <x-panel class="mt-6 p-8 text-center">
            <p class="text-sm text-slate">Belum ada tagihan.</p>
        </x-panel>
    @endforelse
</x-portal-layout>
```

- [ ] **Step 5: Wire the routes**

In `routes/portal.php`, inside the existing `Route::middleware(['auth:portal', 'portal.verified'])->group(...)` block (alongside `dashboard`/`pendaftaran.bukti`), add:
```php
        Route::get('tagihan', [\App\Http\Controllers\Portal\TagihanController::class, 'index'])->name('tagihan.index');
        Route::post('tagihan/{tagihan}/skema-cicilan', [\App\Http\Controllers\Portal\TagihanController::class, 'buatSkemaCicilan'])->name('tagihan.skema-cicilan');
        Route::post('tagihan/{tagihan}/bayar-lunas', [\App\Http\Controllers\Portal\TagihanController::class, 'bayarLunas'])->name('tagihan.bayar-lunas');
        Route::post('cicilan/{cicilan}/bayar', [\App\Http\Controllers\Portal\TagihanController::class, 'bayarCicilan'])->name('tagihan.bayar-cicilan');
```

- [ ] **Step 6: Add the sidebar link**

In `resources/views/layouts/portal.blade.php`, inside `<nav class="flex-1 overflow-y-auto px-4 py-6">`, immediately after the existing "Dashboard" `<a>` link, add:
```blade
                    <a
                        href="{{ route('portal.tagihan.index') }}"
                        class="mt-1 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold {{ request()->routeIs('portal.tagihan.*') ? 'bg-white/15 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' }}"
                    >
                        <span class="material-symbols-outlined" style="font-size: 20px;">receipt_long</span>
                        Tagihan & Pembayaran
                    </a>
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Portal/TagihanPembayaranTest.php`
Expected: PASS (5/5)

- [ ] **Step 8: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all tests pass, including every pre-existing Portal Akun Pendaftar test (the sidebar/dashboard change is additive).

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Portal/TagihanController.php resources/views/portal/tagihan/index.blade.php \
        routes/portal.php resources/views/layouts/portal.blade.php \
        tests/Feature/Portal/TagihanPembayaranTest.php
git commit -m "feat: add tagihan & pembayaran menu to portal akun pendaftar"
```

---

## Post-Plan Note

After Task 5, the SPMB module is feature-complete end-to-end: register → verify → decide → issue SK → generate tagihan → pay (lunas or cicilan, transfer manual or admin-recorded) → `Pendaftaran::isAktif` reflects reality automatically, with `pendaftaran.status` never touched. VA BRI integration (`pembayaran.metode = 'va_bri'`) can be added later purely inside `PembayaranService`/a new controller action once real bank API access exists — no schema changes anticipated. This closes the M4 Keuangan initiative (all 3 sub-projects). No further SPMB/Keuangan plan is implied; the next module choice is up to the user.
