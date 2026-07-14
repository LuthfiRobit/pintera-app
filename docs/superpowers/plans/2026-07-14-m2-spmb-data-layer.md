# M2 — SPMB Portal Publik: Data Layer & Services Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the complete relational data layer (9 tables, 9 models) and supporting services (OTP verification, kode pendaftaran generation, mail infrastructure) for the M2 SPMB Portal Publik, fully testable via Pest with no HTTP/routes involved yet.

**Architecture:** Data modeled relationally per Dapodik category (not one flat table), mirroring the existing `Guru` satellite-table pattern (`RiwayatPendidikanGuru`, `SertifikasiGuru`) and its encrypted-NIK + `nik_hash` sidecar pattern exactly. New models do **not** use the `BelongsToTenant` trait (they're created from an unauthenticated public context with no acting staff user) — tenant scoping on `Pendaftaran` is a plain `lembaga_id` foreign key set explicitly by the (future, Plan 2) controller, not auto-filled by a trait.

**Tech Stack:** Laravel 12, MySQL, Pest PHP, `barryvdh/laravel-dompdf` (new dependency, added in this plan for the future bukti-pendaftaran PDF), Laravel Mail (`MAIL_MAILER=log` in this environment — emails render and log, don't actually send).

## Global Constraints

- No `BelongsToTenant` trait on any new model in this plan — tenant/parent identifiers (`lembaga_id`, `yayasan_id`, etc.) are plain foreign key columns, filled explicitly by calling code (this plan's tests fill them directly; the future public controller will do the same).
- Every new table gets an explicit `protected $table = '...'` on its model — table names are singular snake_case (`calon_murid`, not `calon_murids`), matching this project's existing PPDB tables (`gelombang_ppdb`, `jalur_ppdb`, `dokumen_syarat_ppdb`), so Laravel's automatic pluralization guess must not be relied on.
- NIK encryption follows `App\Models\Guru`'s exact pattern: `'nik' => 'encrypted'` cast (`app/Models/Guru.php:34`) plus a `nik_hash` sidecar column (`string(64)`, unique) computed via `hash('sha256', $model->nik)` in a `booted()` `saving` hook (`app/Models/Guru.php:38-43`) — copy this pattern verbatim for `CalonMurid`.
- No new CRUD admin UI in this plan — this is the data layer only. No controllers, no routes, no Blade views (those are Plan 2, built on top of this one).
- Table/column names in Indonesian, matching the existing PPDB and Guru schema's language and style.

---

### Task 1: `CalonMurid` model — core identity (Data Pribadi + Kontak)

**Files:**
- Create: `database/migrations/2026_07_14_090000_create_calon_murid_table.php`
- Create: `app/Models/CalonMurid.php`
- Test: `tests/Unit/CalonMuridModelTest.php`

**Interfaces:**
- Produces: `CalonMurid` model with `nik` (encrypted), `nik_hash` (unique, sidecar), `nisn`, `no_kk` (encrypted, nullable), `nama_lengkap`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `agama`, `golongan_darah`, `no_telepon`, `email_kontak`, `yayasan_id`. Static helper `CalonMurid::findByNik(string $nik): ?CalonMurid`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/CalonMuridModelTest.php`:

```php
<?php

use App\Models\CalonMurid;
use App\Models\Yayasan;
use Illuminate\Support\Facades\DB;

it('encrypts nik and no_kk and keeps a deterministic nik_hash for uniqueness', function () {
    $yayasan = Yayasan::factory()->create();

    $calonMurid = CalonMurid::create([
        'yayasan_id' => $yayasan->id,
        'nik' => '3201234567890123',
        'no_kk' => '3201234567890000',
        'nisn' => '0012345678',
        'nama_lengkap' => 'Ahmad Fauzan',
        'jenis_kelamin' => 'L',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2015-03-10',
        'agama' => 'Islam',
        'golongan_darah' => 'O',
        'no_telepon' => '081234567890',
        'email_kontak' => 'wali@example.test',
    ]);

    expect($calonMurid->nik)->toBe('3201234567890123');
    expect($calonMurid->nik_hash)->toBe(hash('sha256', '3201234567890123'));

    $raw = DB::table('calon_murid')->where('id', $calonMurid->id)->first();
    expect($raw->nik)->not->toBe('3201234567890123');
    expect($raw->no_kk)->not->toBe('3201234567890000');
});

it('rejects a duplicate nik_hash', function () {
    $yayasan = Yayasan::factory()->create();

    CalonMurid::create([
        'yayasan_id' => $yayasan->id,
        'nik' => '3201234567890123',
        'nama_lengkap' => 'Ahmad Fauzan',
        'jenis_kelamin' => 'L',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2015-03-10',
        'agama' => 'Islam',
    ]);

    expect(fn () => CalonMurid::create([
        'yayasan_id' => $yayasan->id,
        'nik' => '3201234567890123',
        'nama_lengkap' => 'Nama Lain',
        'jenis_kelamin' => 'P',
        'tempat_lahir' => 'Jakarta',
        'tanggal_lahir' => '2015-05-20',
        'agama' => 'Islam',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('finds a calon murid by plaintext nik via findByNik', function () {
    $yayasan = Yayasan::factory()->create();
    $calonMurid = CalonMurid::create([
        'yayasan_id' => $yayasan->id,
        'nik' => '3201234567890123',
        'nama_lengkap' => 'Ahmad Fauzan',
        'jenis_kelamin' => 'L',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2015-03-10',
        'agama' => 'Islam',
    ]);

    $found = CalonMurid::findByNik('3201234567890123');
    expect($found)->not->toBeNull();
    expect($found->id)->toBe($calonMurid->id);

    expect(CalonMurid::findByNik('9999999999999999'))->toBeNull();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Unit/CalonMuridModelTest.php`
Expected: FAIL — table/model don't exist yet.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_07_14_090000_create_calon_murid_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calon_murid', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yayasan_id')->constrained('yayasan');
            $table->text('nik');
            $table->string('nik_hash', 64)->unique();
            $table->text('no_kk')->nullable();
            $table->string('nisn')->nullable();
            $table->string('nama_lengkap');
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->string('tempat_lahir');
            $table->date('tanggal_lahir');
            $table->string('agama');
            $table->string('golongan_darah')->nullable();
            $table->string('no_telepon')->nullable();
            $table->string('email_kontak')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calon_murid');
    }
};
```

- [ ] **Step 4: Write the model**

Create `app/Models/CalonMurid.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CalonMurid extends Model
{
    use HasFactory;

    protected $table = 'calon_murid';

    protected $fillable = [
        'yayasan_id',
        'nik',
        'no_kk',
        'nisn',
        'nama_lengkap',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'agama',
        'golongan_darah',
        'no_telepon',
        'email_kontak',
    ];

    protected function casts(): array
    {
        return [
            'nik' => 'encrypted',
            'no_kk' => 'encrypted',
            'tanggal_lahir' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CalonMurid $calonMurid) {
            $calonMurid->nik_hash = hash('sha256', $calonMurid->nik);
        });
    }

    public static function findByNik(string $nik): ?self
    {
        return static::where('nik_hash', hash('sha256', $nik))->first();
    }

    public function yayasan(): BelongsTo
    {
        return $this->belongsTo(Yayasan::class);
    }

    public function alamat(): HasOne
    {
        return $this->hasOne(AlamatCalonMurid::class);
    }

    public function keluarga(): HasMany
    {
        return $this->hasMany(KeluargaCalonMurid::class);
    }

    public function dataPeriodik(): HasOne
    {
        return $this->hasOne(DataPeriodikCalonMurid::class);
    }

    public function dataKhusus(): HasOne
    {
        return $this->hasOne(DataKhususCalonMurid::class);
    }

    public function pendaftaran(): HasMany
    {
        return $this->hasMany(Pendaftaran::class);
    }
}
```

Note: `HasFactory` is added even though no factory is created in this plan (no admin UI needs one yet) — Plan 2's tests will need `CalonMurid::factory()` for wizard-flow test setup, so add `database/factories/CalonMuridFactory.php` now to avoid a missing-factory error surfacing later:

Create `database/factories/CalonMuridFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\CalonMurid;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Factories\Factory;

class CalonMuridFactory extends Factory
{
    protected $model = CalonMurid::class;

    public function definition(): array
    {
        return [
            'yayasan_id' => Yayasan::factory(),
            'nik' => $this->faker->unique()->numerify('################'),
            'nama_lengkap' => $this->faker->name(),
            'jenis_kelamin' => $this->faker->randomElement(['L', 'P']),
            'tempat_lahir' => $this->faker->city(),
            'tanggal_lahir' => $this->faker->date(),
            'agama' => 'Islam',
        ];
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Unit/CalonMuridModelTest.php`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_14_090000_create_calon_murid_table.php app/Models/CalonMurid.php database/factories/CalonMuridFactory.php tests/Unit/CalonMuridModelTest.php
git commit -m "feat: add CalonMurid model with encrypted NIK and nik_hash sidecar"
```

---

### Task 2: Satellite tables — Alamat, Keluarga, Data Periodik, Data Khusus

**Files:**
- Create: `database/migrations/2026_07_14_090100_create_alamat_calon_murid_table.php`
- Create: `database/migrations/2026_07_14_090200_create_keluarga_calon_murid_table.php`
- Create: `database/migrations/2026_07_14_090300_create_data_periodik_calon_murid_table.php`
- Create: `database/migrations/2026_07_14_090400_create_data_khusus_calon_murid_table.php`
- Create: `app/Models/AlamatCalonMurid.php`
- Create: `app/Models/KeluargaCalonMurid.php`
- Create: `app/Models/DataPeriodikCalonMurid.php`
- Create: `app/Models/DataKhususCalonMurid.php`
- Test: `tests/Unit/CalonMuridSatelliteTablesTest.php`

**Interfaces:**
- Consumes: `CalonMurid` (Task 1).
- Produces: `AlamatCalonMurid` (1:1), `KeluargaCalonMurid` (1:many, `jenis` enum ayah/ibu/wali), `DataPeriodikCalonMurid` (1:1, nullable/optional), `DataKhususCalonMurid` (1:1, nullable/optional) — all reachable via `CalonMurid`'s relations added in Task 1.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/CalonMuridSatelliteTablesTest.php`:

```php
<?php

use App\Models\AlamatCalonMurid;
use App\Models\CalonMurid;
use App\Models\DataKhususCalonMurid;
use App\Models\DataPeriodikCalonMurid;
use App\Models\KeluargaCalonMurid;

it('relates alamat, keluarga, data periodik, and data khusus to a calon murid', function () {
    $calonMurid = CalonMurid::factory()->create();

    AlamatCalonMurid::create([
        'calon_murid_id' => $calonMurid->id,
        'alamat_jalan' => 'Jl. Merdeka No. 10',
        'rt' => '001',
        'rw' => '002',
        'desa_kelurahan' => 'Sukamaju',
        'kecamatan' => 'Cibeunying',
        'kabupaten_kota' => 'Bandung',
        'provinsi' => 'Jawa Barat',
        'kode_pos' => '40123',
    ]);

    KeluargaCalonMurid::create([
        'calon_murid_id' => $calonMurid->id,
        'jenis' => 'ayah',
        'nama' => 'Budi Santoso',
        'pekerjaan' => 'Wiraswasta',
    ]);
    KeluargaCalonMurid::create([
        'calon_murid_id' => $calonMurid->id,
        'jenis' => 'ibu',
        'nama' => 'Siti Aminah',
        'pekerjaan' => 'Ibu Rumah Tangga',
    ]);

    DataPeriodikCalonMurid::create([
        'calon_murid_id' => $calonMurid->id,
        'tinggi_badan_cm' => 120,
        'berat_badan_kg' => 25,
    ]);

    DataKhususCalonMurid::create([
        'calon_murid_id' => $calonMurid->id,
        'kepemilikan_kip' => true,
        'nomor_kip' => '1234567890',
    ]);

    $calonMurid->refresh();
    expect($calonMurid->alamat->alamat_jalan)->toBe('Jl. Merdeka No. 10');
    expect($calonMurid->keluarga)->toHaveCount(2);
    expect($calonMurid->keluarga->firstWhere('jenis', 'ayah')->nama)->toBe('Budi Santoso');
    expect($calonMurid->dataPeriodik->tinggi_badan_cm)->toBe(120);
    expect($calonMurid->dataKhusus->kepemilikan_kip)->toBeTrue();
});

it('allows a calon murid to have no data periodik or data khusus (both optional)', function () {
    $calonMurid = CalonMurid::factory()->create();

    expect($calonMurid->dataPeriodik)->toBeNull();
    expect($calonMurid->dataKhusus)->toBeNull();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Unit/CalonMuridSatelliteTablesTest.php`
Expected: FAIL — tables/models don't exist yet.

- [ ] **Step 3: Write the migrations**

Create `database/migrations/2026_07_14_090100_create_alamat_calon_murid_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alamat_calon_murid', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calon_murid_id')->unique()->constrained('calon_murid')->cascadeOnDelete();
            $table->string('alamat_jalan');
            $table->string('rt', 5)->nullable();
            $table->string('rw', 5)->nullable();
            $table->string('dusun')->nullable();
            $table->string('desa_kelurahan');
            $table->string('kecamatan');
            $table->string('kabupaten_kota');
            $table->string('provinsi');
            $table->string('kode_pos', 10)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alamat_calon_murid');
    }
};
```

Create `database/migrations/2026_07_14_090200_create_keluarga_calon_murid_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keluarga_calon_murid', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calon_murid_id')->constrained('calon_murid')->cascadeOnDelete();
            $table->enum('jenis', ['ayah', 'ibu', 'wali']);
            $table->string('nama');
            $table->text('nik')->nullable();
            $table->unsignedSmallInteger('tahun_lahir')->nullable();
            $table->string('pendidikan_terakhir')->nullable();
            $table->string('pekerjaan')->nullable();
            $table->string('penghasilan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keluarga_calon_murid');
    }
};
```

Create `database/migrations/2026_07_14_090300_create_data_periodik_calon_murid_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_periodik_calon_murid', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calon_murid_id')->unique()->constrained('calon_murid')->cascadeOnDelete();
            $table->unsignedSmallInteger('tinggi_badan_cm')->nullable();
            $table->unsignedSmallInteger('berat_badan_kg')->nullable();
            $table->unsignedSmallInteger('jarak_tempuh_km')->nullable();
            $table->unsignedSmallInteger('waktu_tempuh_menit')->nullable();
            $table->unsignedTinyInteger('jumlah_saudara_kandung')->nullable();
            $table->string('alat_transportasi')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_periodik_calon_murid');
    }
};
```

Create `database/migrations/2026_07_14_090400_create_data_khusus_calon_murid_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_khusus_calon_murid', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calon_murid_id')->unique()->constrained('calon_murid')->cascadeOnDelete();
            $table->boolean('kepemilikan_kip')->default(false);
            $table->string('nomor_kip')->nullable();
            $table->text('riwayat_beasiswa')->nullable();
            $table->text('kebutuhan_khusus')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_khusus_calon_murid');
    }
};
```

- [ ] **Step 4: Write the models**

Create `app/Models/AlamatCalonMurid.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlamatCalonMurid extends Model
{
    protected $table = 'alamat_calon_murid';

    protected $fillable = [
        'calon_murid_id',
        'alamat_jalan',
        'rt',
        'rw',
        'dusun',
        'desa_kelurahan',
        'kecamatan',
        'kabupaten_kota',
        'provinsi',
        'kode_pos',
    ];

    public function calonMurid(): BelongsTo
    {
        return $this->belongsTo(CalonMurid::class);
    }
}
```

Create `app/Models/KeluargaCalonMurid.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeluargaCalonMurid extends Model
{
    protected $table = 'keluarga_calon_murid';

    protected $fillable = [
        'calon_murid_id',
        'jenis',
        'nama',
        'nik',
        'tahun_lahir',
        'pendidikan_terakhir',
        'pekerjaan',
        'penghasilan',
    ];

    protected function casts(): array
    {
        return [
            'nik' => 'encrypted',
        ];
    }

    public function calonMurid(): BelongsTo
    {
        return $this->belongsTo(CalonMurid::class);
    }
}
```

Create `app/Models/DataPeriodikCalonMurid.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataPeriodikCalonMurid extends Model
{
    protected $table = 'data_periodik_calon_murid';

    protected $fillable = [
        'calon_murid_id',
        'tinggi_badan_cm',
        'berat_badan_kg',
        'jarak_tempuh_km',
        'waktu_tempuh_menit',
        'jumlah_saudara_kandung',
        'alat_transportasi',
    ];

    public function calonMurid(): BelongsTo
    {
        return $this->belongsTo(CalonMurid::class);
    }
}
```

Create `app/Models/DataKhususCalonMurid.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataKhususCalonMurid extends Model
{
    protected $table = 'data_khusus_calon_murid';

    protected $fillable = [
        'calon_murid_id',
        'kepemilikan_kip',
        'nomor_kip',
        'riwayat_beasiswa',
        'kebutuhan_khusus',
    ];

    protected function casts(): array
    {
        return [
            'kepemilikan_kip' => 'boolean',
        ];
    }

    public function calonMurid(): BelongsTo
    {
        return $this->belongsTo(CalonMurid::class);
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Unit/CalonMuridSatelliteTablesTest.php`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_14_090100_create_alamat_calon_murid_table.php database/migrations/2026_07_14_090200_create_keluarga_calon_murid_table.php database/migrations/2026_07_14_090300_create_data_periodik_calon_murid_table.php database/migrations/2026_07_14_090400_create_data_khusus_calon_murid_table.php app/Models/AlamatCalonMurid.php app/Models/KeluargaCalonMurid.php app/Models/DataPeriodikCalonMurid.php app/Models/DataKhususCalonMurid.php tests/Unit/CalonMuridSatelliteTablesTest.php
git commit -m "feat: add CalonMurid satellite tables (alamat, keluarga, data periodik, data khusus)"
```

---

### Task 3: `Pendaftaran` model

**Files:**
- Create: `database/migrations/2026_07_14_090500_create_pendaftaran_table.php`
- Create: `app/Models/Pendaftaran.php`
- Modify: `app/Models/TahunAjaran.php:1-13` (add `HasFactory` trait)
- Modify: `app/Models/JalurPpdb.php:1-14` (add `HasFactory` trait)
- Modify: `app/Models/GelombangPpdb.php:1-14` (add `HasFactory` trait)
- Create: `database/factories/TahunAjaranFactory.php`
- Create: `database/factories/JalurPpdbFactory.php`
- Create: `database/factories/GelombangPpdbFactory.php`
- Create: `database/factories/PendaftaranFactory.php`
- Test: `tests/Unit/PendaftaranModelTest.php`

**Note:** `database/factories/LembagaFactory.php` and `YayasanFactory.php` already exist and their models already use `HasFactory` (confirmed). `TahunAjaran`, `JalurPpdb`, and `GelombangPpdb` (all from the already-merged M1 module) currently do **not** use `HasFactory` — confirmed by reading all three files in full. `PendaftaranFactory` (below) needs `TahunAjaran::factory()`, `JalurPpdb::factory()`, and `GelombangPpdb::factory()` to work, so this task adds the trait to all three (a minimal, additive, one-line-per-file change — no other change to these files) before creating their factories.

**Interfaces:**
- Consumes: `CalonMurid` (Task 1), `Lembaga`, `TahunAjaran`, `JalurPpdb`, `GelombangPpdb` (all pre-existing from M0/M1).
- Produces: `Pendaftaran` model with `status` enum (`menunggu_verifikasi, diterima, ditolak, daftar_ulang, aktif`, default `menunggu_verifikasi`), unique `(calon_murid_id, gelombang_ppdb_id)`, `kode_pendaftaran` unique **per lembaga** (composite `(lembaga_id, kode_pendaftaran)`, not a bare global unique — the numbering sequence restarts per lembaga per year, see Task 6, so two different lembaga legitimately share the same code string), `email_pendaftaran`, `submitted_at`. Relations back to all five parents, plus `jawabanFormulir()` and `dokumen()` (added in Task 4).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/PendaftaranModelTest.php`:

```php
<?php

use App\Models\CalonMurid;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

function buatKonteksPendaftaran(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => now()->subDay(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 40,
    ]);
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id]);

    return [$lembaga, $tahunAjaran, $jalur, $gelombang, $calonMurid];
}

it('creates a pendaftaran linking calon murid to lembaga, tahun ajaran, jalur, and gelombang', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang, $calonMurid] = buatKonteksPendaftaran();

    $pendaftaran = Pendaftaran::create([
        'calon_murid_id' => $calonMurid->id,
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id,
        'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-00001',
        'email_pendaftaran' => 'wali@example.test',
        'submitted_at' => now(),
    ]);

    expect($pendaftaran->status)->toBe('menunggu_verifikasi');
    expect($pendaftaran->calonMurid->id)->toBe($calonMurid->id);
    expect($pendaftaran->lembaga->id)->toBe($lembaga->id);
    expect($pendaftaran->jalurPpdb->id)->toBe($jalur->id);
    expect($pendaftaran->gelombangPpdb->id)->toBe($gelombang->id);
});

it('rejects a second pendaftaran for the same calon murid in the same gelombang', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang, $calonMurid] = buatKonteksPendaftaran();

    Pendaftaran::create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-00001', 'email_pendaftaran' => 'wali@example.test', 'submitted_at' => now(),
    ]);

    expect(fn () => Pendaftaran::create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-00002', 'email_pendaftaran' => 'wali@example.test', 'submitted_at' => now(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('allows the same calon murid to register again in a different gelombang', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang, $calonMurid] = buatKonteksPendaftaran();
    $gelombangKedua = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 2',
        'tanggal_buka' => now()->addMonth(), 'tanggal_tutup' => now()->addMonths(2), 'kuota' => 40,
    ]);

    Pendaftaran::create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-00001', 'email_pendaftaran' => 'wali@example.test', 'submitted_at' => now(),
    ]);

    $kedua = Pendaftaran::create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombangKedua->id,
        'kode_pendaftaran' => 'REG-2026-00002', 'email_pendaftaran' => 'wali@example.test', 'submitted_at' => now(),
    ]);

    expect($kedua->id)->not->toBe(null);
    expect(Pendaftaran::where('calon_murid_id', $calonMurid->id)->count())->toBe(2);
});

it('rejects a duplicate kode_pendaftaran', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang, $calonMurid] = buatKonteksPendaftaran();
    $calonMuridLain = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);

    Pendaftaran::create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-00001', 'email_pendaftaran' => 'wali@example.test', 'submitted_at' => now(),
    ]);

    expect(fn () => Pendaftaran::create([
        'calon_murid_id' => $calonMuridLain->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-00001', 'email_pendaftaran' => 'lain@example.test', 'submitted_at' => now(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Unit/PendaftaranModelTest.php`
Expected: FAIL — table/model don't exist yet.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_07_14_090500_create_pendaftaran_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pendaftaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calon_murid_id')->constrained('calon_murid');
            $table->foreignId('lembaga_id')->constrained('lembaga');
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran');
            $table->foreignId('jalur_ppdb_id')->constrained('jalur_ppdb');
            $table->foreignId('gelombang_ppdb_id')->constrained('gelombang_ppdb');
            $table->string('kode_pendaftaran');
            $table->string('email_pendaftaran');
            $table->enum('status', ['menunggu_verifikasi', 'diterima', 'ditolak', 'daftar_ulang', 'aktif'])
                ->default('menunggu_verifikasi');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['calon_murid_id', 'gelombang_ppdb_id']);
            $table->unique(['lembaga_id', 'kode_pendaftaran']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pendaftaran');
    }
};
```

- [ ] **Step 4: Write the model**

Create `app/Models/Pendaftaran.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pendaftaran extends Model
{
    use HasFactory;

    protected $table = 'pendaftaran';

    protected $fillable = [
        'calon_murid_id',
        'lembaga_id',
        'tahun_ajaran_id',
        'jalur_ppdb_id',
        'gelombang_ppdb_id',
        'kode_pendaftaran',
        'email_pendaftaran',
        'status',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    public function calonMurid(): BelongsTo
    {
        return $this->belongsTo(CalonMurid::class);
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function jalurPpdb(): BelongsTo
    {
        return $this->belongsTo(JalurPpdb::class);
    }

    public function gelombangPpdb(): BelongsTo
    {
        return $this->belongsTo(GelombangPpdb::class);
    }

    public function jawabanFormulir(): HasMany
    {
        return $this->hasMany(JawabanFormulirPendaftaran::class);
    }

    public function dokumen(): HasMany
    {
        return $this->hasMany(DokumenPendaftaran::class);
    }
}
```

Add `HasFactory` to the three existing M1 models (each is a two-line change: add the `use` import, add the trait to the `use` statement inside the class — no other line changes):

In `app/Models/TahunAjaran.php`, replace:
```php
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class TahunAjaran extends Model
{
    use BelongsToTenant;
```
with:
```php
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class TahunAjaran extends Model
{
    use BelongsToTenant, HasFactory;
```

In `app/Models/JalurPpdb.php`, replace:
```php
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class JalurPpdb extends Model
{
    use BelongsToTenant, LogsActivity;
```
with:
```php
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class JalurPpdb extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity;
```

In `app/Models/GelombangPpdb.php`, replace:
```php
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class GelombangPpdb extends Model
{
    use BelongsToTenant, LogsActivity;
```
with:
```php
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class GelombangPpdb extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity;
```

Create `database/factories/TahunAjaranFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

class TahunAjaranFactory extends Factory
{
    protected $model = TahunAjaran::class;

    public function definition(): array
    {
        $tahunMulai = $this->faker->numberBetween(2020, 2099);

        return [
            'lembaga_id' => Lembaga::factory(),
            'nama' => $tahunMulai.'/'.($tahunMulai + 1),
            'tanggal_mulai' => now(),
            'tanggal_selesai' => now()->addYear(),
            'status_aktif' => false,
        ];
    }
}
```

Create `database/factories/JalurPpdbFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

class JalurPpdbFactory extends Factory
{
    protected $model = JalurPpdb::class;

    public function definition(): array
    {
        return [
            'lembaga_id' => Lembaga::factory(),
            'tahun_ajaran_id' => TahunAjaran::factory(),
            'nama' => 'Jalur '.$this->faker->unique()->randomNumber(6),
            'status_aktif' => true,
        ];
    }
}
```

Create `database/factories/GelombangPpdbFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\GelombangPpdb;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

class GelombangPpdbFactory extends Factory
{
    protected $model = GelombangPpdb::class;

    public function definition(): array
    {
        return [
            'lembaga_id' => Lembaga::factory(),
            'tahun_ajaran_id' => TahunAjaran::factory(),
            'nama' => 'Gelombang '.$this->faker->unique()->randomNumber(6),
            'tanggal_buka' => now()->subMonth(),
            'tanggal_tutup' => now()->addMonth(),
            'kuota' => $this->faker->numberBetween(20, 100),
        ];
    }
}
```

Create `database/factories/PendaftaranFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\CalonMurid;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

class PendaftaranFactory extends Factory
{
    protected $model = Pendaftaran::class;

    public function definition(): array
    {
        return [
            'calon_murid_id' => CalonMurid::factory(),
            'lembaga_id' => Lembaga::factory(),
            'tahun_ajaran_id' => TahunAjaran::factory(),
            'jalur_ppdb_id' => JalurPpdb::factory(),
            'gelombang_ppdb_id' => GelombangPpdb::factory(),
            'kode_pendaftaran' => 'REG-'.now()->year.'-'.$this->faker->unique()->numerify('#####'),
            'email_pendaftaran' => $this->faker->safeEmail(),
            'submitted_at' => now(),
        ];
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Unit/PendaftaranModelTest.php`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_14_090500_create_pendaftaran_table.php app/Models/Pendaftaran.php app/Models/TahunAjaran.php app/Models/JalurPpdb.php app/Models/GelombangPpdb.php database/factories/TahunAjaranFactory.php database/factories/JalurPpdbFactory.php database/factories/GelombangPpdbFactory.php database/factories/PendaftaranFactory.php tests/Unit/PendaftaranModelTest.php
git commit -m "feat: add Pendaftaran model linking calon murid to lembaga/tahun ajaran/jalur/gelombang"
```

---

### Task 4: `JawabanFormulirPendaftaran` + `DokumenPendaftaran`

**Files:**
- Create: `database/migrations/2026_07_14_090600_create_jawaban_formulir_pendaftaran_table.php`
- Create: `database/migrations/2026_07_14_090700_create_dokumen_pendaftaran_table.php`
- Create: `app/Models/JawabanFormulirPendaftaran.php`
- Create: `app/Models/DokumenPendaftaran.php`
- Test: `tests/Unit/PendaftaranAnswersAndDocumentsTest.php`

**Interfaces:**
- Consumes: `Pendaftaran` (Task 3), `FormulirField`/`DokumenSyaratPpdb` (pre-existing M1 models).
- Produces: `JawabanFormulirPendaftaran` (answer to one dynamic form field), `DokumenPendaftaran` (one uploaded document, `status_verifikasi` enum prepared for M3).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/PendaftaranAnswersAndDocumentsTest.php`:

```php
<?php

use App\Models\DokumenPendaftaran;
use App\Models\DokumenSyaratPpdb;
use App\Models\FormulirField;
use App\Models\JawabanFormulirPendaftaran;
use App\Models\Pendaftaran;

it('stores a jawaban for a dynamic formulir field', function () {
    $pendaftaran = Pendaftaran::factory()->create();
    $field = FormulirField::create([
        'jalur_ppdb_id' => $pendaftaran->jalur_ppdb_id,
        'label' => 'Nilai Rata-rata Rapor',
        'field_type' => 'number',
    ]);

    $jawaban = JawabanFormulirPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id,
        'formulir_field_id' => $field->id,
        'nilai' => '88.5',
    ]);

    expect($pendaftaran->jawabanFormulir()->first()->nilai)->toBe('88.5');
    expect($jawaban->formulirField->label)->toBe('Nilai Rata-rata Rapor');
});

it('stores a dokumen pendaftaran with a default belum_diverifikasi status', function () {
    $pendaftaran = Pendaftaran::factory()->create();
    $syarat = DokumenSyaratPpdb::create([
        'jalur_ppdb_id' => $pendaftaran->jalur_ppdb_id,
        'nama_dokumen' => 'Akta Kelahiran',
    ]);

    $dokumen = DokumenPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id,
        'dokumen_syarat_ppdb_id' => $syarat->id,
        'file_path' => 'pendaftaran/1/akta.pdf',
        'nama_file_asli' => 'akta-ahmad.pdf',
        'mime_type' => 'application/pdf',
        'ukuran_bytes' => 102400,
    ]);

    expect($dokumen->status_verifikasi)->toBe('belum_diverifikasi');
    expect($pendaftaran->dokumen()->first()->nama_file_asli)->toBe('akta-ahmad.pdf');
    expect($dokumen->dokumenSyaratPpdb->nama_dokumen)->toBe('Akta Kelahiran');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Unit/PendaftaranAnswersAndDocumentsTest.php`
Expected: FAIL — tables/models don't exist yet.

- [ ] **Step 3: Write the migrations**

Create `database/migrations/2026_07_14_090600_create_jawaban_formulir_pendaftaran_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jawaban_formulir_pendaftaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_id')->constrained('pendaftaran')->cascadeOnDelete();
            $table->foreignId('formulir_field_id')->constrained('formulir_field');
            $table->text('nilai')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jawaban_formulir_pendaftaran');
    }
};
```

Create `database/migrations/2026_07_14_090700_create_dokumen_pendaftaran_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dokumen_pendaftaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_id')->constrained('pendaftaran')->cascadeOnDelete();
            $table->foreignId('dokumen_syarat_ppdb_id')->constrained('dokumen_syarat_ppdb');
            $table->string('file_path');
            $table->string('nama_file_asli');
            $table->string('mime_type');
            $table->unsignedInteger('ukuran_bytes');
            $table->enum('status_verifikasi', ['belum_diverifikasi', 'diterima', 'ditolak'])
                ->default('belum_diverifikasi');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen_pendaftaran');
    }
};
```

- [ ] **Step 4: Write the models**

Create `app/Models/JawabanFormulirPendaftaran.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JawabanFormulirPendaftaran extends Model
{
    protected $table = 'jawaban_formulir_pendaftaran';

    protected $fillable = [
        'pendaftaran_id',
        'formulir_field_id',
        'nilai',
    ];

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(Pendaftaran::class);
    }

    public function formulirField(): BelongsTo
    {
        return $this->belongsTo(FormulirField::class);
    }
}
```

Create `app/Models/DokumenPendaftaran.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DokumenPendaftaran extends Model
{
    protected $table = 'dokumen_pendaftaran';

    protected $fillable = [
        'pendaftaran_id',
        'dokumen_syarat_ppdb_id',
        'file_path',
        'nama_file_asli',
        'mime_type',
        'ukuran_bytes',
        'status_verifikasi',
    ];

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(Pendaftaran::class);
    }

    public function dokumenSyaratPpdb(): BelongsTo
    {
        return $this->belongsTo(DokumenSyaratPpdb::class);
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Unit/PendaftaranAnswersAndDocumentsTest.php`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_14_090600_create_jawaban_formulir_pendaftaran_table.php database/migrations/2026_07_14_090700_create_dokumen_pendaftaran_table.php app/Models/JawabanFormulirPendaftaran.php app/Models/DokumenPendaftaran.php tests/Unit/PendaftaranAnswersAndDocumentsTest.php
git commit -m "feat: add JawabanFormulirPendaftaran and DokumenPendaftaran models"
```

---

### Task 5: `VerifikasiEmailOtp` model + `OtpService`

**Files:**
- Create: `database/migrations/2026_07_14_090800_create_verifikasi_email_otp_table.php`
- Create: `app/Models/VerifikasiEmailOtp.php`
- Create: `app/Services/OtpService.php`
- Create: `app/Mail/KodeOtpMail.php`
- Create: `resources/views/mail/kode-otp.blade.php`
- Test: `tests/Unit/OtpServiceTest.php`

**Interfaces:**
- Produces: `VerifikasiEmailOtp` model (`email`, `kode_otp`, `expires_at`, `verified_at` nullable). `OtpService::kirim(string $email): void` (generates + emails a 6-digit code, expires in 10 minutes, clears prior unverified codes for that email first). `OtpService::verifikasi(string $email, string $kode): bool` (validates code, marks it verified, single-use).
- Consumes (later, Plan 2): the wizard's email-verification step calls both methods.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/OtpServiceTest.php`:

```php
<?php

use App\Mail\KodeOtpMail;
use App\Models\VerifikasiEmailOtp;
use App\Services\OtpService;
use Illuminate\Support\Facades\Mail;

it('generates and emails a 6-digit otp code', function () {
    Mail::fake();

    (new OtpService())->kirim('wali@example.test');

    expect(VerifikasiEmailOtp::where('email', 'wali@example.test')->count())->toBe(1);
    $otp = VerifikasiEmailOtp::where('email', 'wali@example.test')->first();
    expect($otp->kode_otp)->toMatch('/^\d{6}$/');
    expect($otp->expires_at)->toBeGreaterThan(now());

    Mail::assertSent(KodeOtpMail::class, function (KodeOtpMail $mail) use ($otp) {
        return $mail->hasTo('wali@example.test') && $mail->kodeOtp === $otp->kode_otp;
    });
});

it('clears prior unverified codes for the same email before issuing a new one', function () {
    Mail::fake();
    $service = new OtpService();

    $service->kirim('wali@example.test');
    expect(VerifikasiEmailOtp::where('email', 'wali@example.test')->count())->toBe(1);

    $service->kirim('wali@example.test');
    expect(VerifikasiEmailOtp::where('email', 'wali@example.test')->count())->toBe(1);
});

it('verifies a correct, unexpired, unused code', function () {
    Mail::fake();
    $service = new OtpService();
    $service->kirim('wali@example.test');
    $kode = VerifikasiEmailOtp::where('email', 'wali@example.test')->first()->kode_otp;

    expect($service->verifikasi('wali@example.test', $kode))->toBeTrue();
    expect(VerifikasiEmailOtp::where('email', 'wali@example.test')->first()->verified_at)->not->toBeNull();
});

it('rejects a wrong code', function () {
    Mail::fake();
    $service = new OtpService();
    $service->kirim('wali@example.test');

    expect($service->verifikasi('wali@example.test', '000000'))->toBeFalse();
});

it('rejects an expired code', function () {
    Mail::fake();
    $service = new OtpService();
    $service->kirim('wali@example.test');
    VerifikasiEmailOtp::where('email', 'wali@example.test')->update(['expires_at' => now()->subMinute()]);
    $kode = VerifikasiEmailOtp::where('email', 'wali@example.test')->first()->kode_otp;

    expect($service->verifikasi('wali@example.test', $kode))->toBeFalse();
});

it('rejects a code that has already been used once', function () {
    Mail::fake();
    $service = new OtpService();
    $service->kirim('wali@example.test');
    $kode = VerifikasiEmailOtp::where('email', 'wali@example.test')->first()->kode_otp;

    expect($service->verifikasi('wali@example.test', $kode))->toBeTrue();
    expect($service->verifikasi('wali@example.test', $kode))->toBeFalse();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Unit/OtpServiceTest.php`
Expected: FAIL — table/model/service/mailable don't exist yet.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_07_14_090800_create_verifikasi_email_otp_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verifikasi_email_otp', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('kode_otp', 6);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifikasi_email_otp');
    }
};
```

- [ ] **Step 4: Write the model**

Create `app/Models/VerifikasiEmailOtp.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerifikasiEmailOtp extends Model
{
    protected $table = 'verifikasi_email_otp';

    protected $fillable = [
        'email',
        'kode_otp',
        'expires_at',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }
}
```

- [ ] **Step 5: Write the Mailable and its view**

Create `app/Mail/KodeOtpMail.php`:

```php
<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class KodeOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $kodeOtp)
    {
    }

    public function build(): self
    {
        return $this->subject('Kode Verifikasi Pendaftaran SPMB')
            ->view('mail.kode-otp')
            ->with(['kodeOtp' => $this->kodeOtp]);
    }
}
```

Create `resources/views/mail/kode-otp.blade.php`:

```blade
<p>Gunakan kode berikut untuk memverifikasi email Anda dan melanjutkan pendaftaran SPMB:</p>

<h2 style="letter-spacing: 4px;">{{ $kodeOtp }}</h2>

<p>Kode ini berlaku selama 10 menit. Jika Anda tidak sedang mendaftar, abaikan email ini.</p>
```

- [ ] **Step 6: Write the service**

Create `app/Services/OtpService.php`:

```php
<?php

namespace App\Services;

use App\Mail\KodeOtpMail;
use App\Models\VerifikasiEmailOtp;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    public function kirim(string $email): void
    {
        VerifikasiEmailOtp::where('email', $email)->whereNull('verified_at')->delete();

        $kode = (string) random_int(100000, 999999);

        VerifikasiEmailOtp::create([
            'email' => $email,
            'kode_otp' => $kode,
            'expires_at' => now()->addMinutes(10),
        ]);

        Mail::to($email)->send(new KodeOtpMail($kode));
    }

    public function verifikasi(string $email, string $kode): bool
    {
        $otp = VerifikasiEmailOtp::where('email', $email)
            ->where('kode_otp', $kode)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $otp) {
            return false;
        }

        $otp->update(['verified_at' => now()]);

        return true;
    }
}
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Unit/OtpServiceTest.php`
Expected: PASS (6 tests).

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_07_14_090800_create_verifikasi_email_otp_table.php app/Models/VerifikasiEmailOtp.php app/Services/OtpService.php app/Mail/KodeOtpMail.php resources/views/mail/kode-otp.blade.php tests/Unit/OtpServiceTest.php
git commit -m "feat: add email OTP verification service for the public SPMB portal"
```

---

### Task 6: `KodePendaftaranGenerator` service

**Files:**
- Create: `app/Services/KodePendaftaranGenerator.php`
- Test: `tests/Unit/KodePendaftaranGeneratorTest.php`

**Interfaces:**
- Consumes: `Pendaftaran` (Task 3).
- Produces: `KodePendaftaranGenerator::generate(int $lembagaId): string` — returns a not-yet-used code in the format `REG-{tahun kalender saat ini}-{5 digit, dimulai dari 00001, per lembaga per tahun}`.
- Consumed by (later, Plan 2): the review & submit controller calls this immediately before `Pendaftaran::create()`, inside the same DB transaction, and should retry once if `Pendaftaran::create()` still throws a unique-constraint violation on `kode_pendaftaran` (an unlikely race at pilot scale, but the retry is cheap insurance) — this plan only builds and tests the generator in isolation.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/KodePendaftaranGeneratorTest.php`:

```php
<?php

use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Services\KodePendaftaranGenerator;

it('generates a code in the REG-{tahun}-{5 digit} format starting from 00001', function () {
    $lembaga = Lembaga::factory()->create();

    $kode = (new KodePendaftaranGenerator())->generate($lembaga->id);

    expect($kode)->toBe('REG-'.now()->year.'-00001');
});

it('increments the sequence per lembaga per year based on existing pendaftaran', function () {
    $lembaga = Lembaga::factory()->create();
    Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'kode_pendaftaran' => 'REG-'.now()->year.'-00001']);

    $kode = (new KodePendaftaranGenerator())->generate($lembaga->id);

    expect($kode)->toBe('REG-'.now()->year.'-00002');
});

it('keeps sequences independent between different lembaga', function () {
    $lembagaA = Lembaga::factory()->create();
    $lembagaB = Lembaga::factory()->create();
    Pendaftaran::factory()->create(['lembaga_id' => $lembagaA->id, 'kode_pendaftaran' => 'REG-'.now()->year.'-00001']);

    $kode = (new KodePendaftaranGenerator())->generate($lembagaB->id);

    expect($kode)->toBe('REG-'.now()->year.'-00001');
});

it('skips a candidate code that already exists (defensive collision handling)', function () {
    $lembaga = Lembaga::factory()->create();
    Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'kode_pendaftaran' => 'REG-'.now()->year.'-00001']);
    // Simulate a gap where the "next" count-based guess collides with a code
    // that already exists for a different reason (e.g. manually seeded data).
    Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'kode_pendaftaran' => 'REG-'.now()->year.'-00002']);

    $kode = (new KodePendaftaranGenerator())->generate($lembaga->id);

    expect($kode)->toBe('REG-'.now()->year.'-00003');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Unit/KodePendaftaranGeneratorTest.php`
Expected: FAIL — service doesn't exist yet.

- [ ] **Step 3: Write the service**

Create `app/Services/KodePendaftaranGenerator.php`:

```php
<?php

namespace App\Services;

use App\Models\Pendaftaran;
use RuntimeException;

class KodePendaftaranGenerator
{
    private const MAKS_PERCOBAAN = 20;

    public function generate(int $lembagaId): string
    {
        $tahun = now()->year;
        $urutanAwal = Pendaftaran::where('lembaga_id', $lembagaId)
            ->whereYear('created_at', $tahun)
            ->count() + 1;

        for ($percobaan = 0; $percobaan < self::MAKS_PERCOBAAN; $percobaan++) {
            $kode = sprintf('REG-%d-%05d', $tahun, $urutanAwal + $percobaan);

            if (! Pendaftaran::where('kode_pendaftaran', $kode)->exists()) {
                return $kode;
            }
        }

        throw new RuntimeException('Gagal membuat kode pendaftaran unik setelah '.self::MAKS_PERCOBAAN.' percobaan.');
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Unit/KodePendaftaranGeneratorTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/KodePendaftaranGenerator.php tests/Unit/KodePendaftaranGeneratorTest.php
git commit -m "feat: add per-lembaga-per-year kode pendaftaran generator"
```

---

### Task 7: PDF dependency + storage link + final regression

**Files:**
- Modify: `composer.json` (via `composer require`)
- No new application files — this task is setup + verification.

**Interfaces:**
- Produces: `barryvdh/laravel-dompdf` installed and available for Plan 2's bukti-pendaftaran PDF task; `public/storage` symlink created so uploaded documents (Plan 2) are web-accessible.

- [ ] **Step 1: Install the PDF package**

Run: `composer require barryvdh/laravel-dompdf`
Expected: package added to `composer.json`/`composer.lock`, no errors. This package auto-registers its service provider via Laravel package discovery — no manual config needed for this plan (Plan 2's PDF-generation task will use `Pdf::loadView(...)` directly).

- [ ] **Step 2: Create the storage symlink**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan storage:link`
Expected: creates `public/storage` → `storage/app/public` symlink. Confirm with `ls public/storage` (should list the same contents as `storage/app/public`, initially empty aside from a possible `.gitignore`).

- [ ] **Step 3: Run the full test suite**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test`
Expected: PASS, 0 failures — this plan added roughly 19 new tests (3 + 2 + 4 + 2 + 6 + 4 = 21 across Tasks 1-6) on top of the prior baseline (167 passing after the Roles-redesign plan), so expect around 188 passed.

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: add barryvdh/laravel-dompdf and create the public storage symlink for M2 file uploads"
```

---

## Post-Plan Note

This plan covers only the data layer and supporting services for M2 (9 tables/models, OTP verification, kode pendaftaran generation, PDF/storage setup) — all fully tested via Pest with no HTTP routes involved. The public-facing wizard (routes, controllers, Blade views matching `docs/superpowers/m2-frontend-design-reference.md`, the NIK-reuse-with-email-verification flow, and the cek-status page) is intentionally **out of scope** here and will be written as a follow-up implementation plan once this one is merged, mirroring how the RBAC permission migration and the Roles page redesign were sequenced as two plans from one design spec.
