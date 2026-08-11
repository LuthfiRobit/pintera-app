# Keuangan Sub-project 1: Fondasi Skema — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend the existing PPDB billing schema (`tagihan`, `jenis_tagihan`, `pembayaran`) so it can also represent recurring school billing (SPP) for enrolled students, without breaking any existing PPDB behavior, and lay down every new table the later Keuangan sub-projects (2-6) will build on.

**Architecture:** Additive, expand-only schema changes. `tagihan` gains a polymorphic `tagihable_type`/`tagihable_id` pair alongside the existing `pendaftaran_id` (which stays populated and untouched for PPDB rows — no PPDB controller needs to change). Seven new tables support multi-criteria targeting, dimensional pricing, per-student overrides, discount rules, and multi-bill payment allocation. One decommission migration retires the legacy `lembaga.memungut_iuran` fields once their data is folded into the new `jenis_tagihan` shape.

**Tech Stack:** Laravel 11 migrations (no doctrine/dbal — schema changes needing raw SQL use `DB::statement`), Eloquent models, Pest tests with `RefreshDatabase`.

## Global Constraints

- Every existing PPDB test (`tests/Feature/Admin/Tagihan*`, `tests/Feature/Admin/JenisTagihanTest.php`, `tests/Feature/Admin/*Pembayaran*`, `tests/Feature/Portal/TagihanPembayaranTest.php`, `tests/Feature/Spmb/TagihanPendaftaranHookTest.php`) must stay green through every task — this is the definition of done, not a separate cleanup pass.
- No `doctrine/dbal` is installed — any column-type change or enum widening on an existing table uses `DB::statement('ALTER TABLE ...')`, matching the existing convention in `database/migrations/2026_08_05_110000_widen_kasus_status_enum.php`.
- New nullable/default-valued columns only on existing tables — never a new NOT NULL column without a default, since existing rows must remain valid.
- `tagihable_type` stores the full class name (`App\Models\Pendaftaran`, `App\Models\Siswa`) — no morph map is defined anywhere in this codebase, so none is introduced here either.
- All new migration timestamps use 2026-08-10, following the existing per-hour spacing convention seen in recent migrations.

---

### Task 1: `jenis_tagihan_sasaran_grup` + `jenis_tagihan_sasaran_kriteria` tables

**Files:**
- Create: `database/migrations/2026_08_10_090000_create_jenis_tagihan_sasaran_table.php`
- Create: `app/Models/JenisTagihanSasaranGrup.php`
- Create: `app/Models/JenisTagihanSasaranKriteria.php`
- Test: `tests/Feature/Keuangan/JenisTagihanSasaranGrupTest.php`

**Interfaces:**
- Consumes: `App\Models\JenisTagihan` (existing, `database/factories/JenisTagihanFactory.php` already exists)
- Produces: `JenisTagihanSasaranGrup::create(['jenis_tagihan_id' => int, 'tipe' => 'sasaran'|'tarif', 'nominal' => ?float])`, `JenisTagihanSasaranKriteria::create(['jenis_tagihan_sasaran_grup_id' => int, 'field' => string, 'operator' => 'in'|'not_in', 'value' => array])`, relation `JenisTagihanSasaranGrup::kriteria(): HasMany`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Keuangan/JenisTagihanSasaranGrupTest.php

use App\Models\JenisTagihan;
use App\Models\JenisTagihanSasaranGrup;
use App\Models\JenisTagihanSasaranKriteria;

it('stores a sasaran grup with AND-ed criteria rows under one jenis_tagihan', function () {
    $jenisTagihan = JenisTagihan::factory()->create();

    $grup = JenisTagihanSasaranGrup::create([
        'jenis_tagihan_id' => $jenisTagihan->id,
        'tipe' => 'sasaran',
    ]);

    JenisTagihanSasaranKriteria::create([
        'jenis_tagihan_sasaran_grup_id' => $grup->id,
        'field' => 'kelas',
        'operator' => 'in',
        'value' => [1, 2],
    ]);

    expect($grup->tipe)->toBe('sasaran');
    expect($grup->kriteria)->toHaveCount(1);
    expect($grup->kriteria->first()->value)->toBe([1, 2]);
});

it('stores a tarif grup with a nominal override', function () {
    $jenisTagihan = JenisTagihan::factory()->create();

    $grup = JenisTagihanSasaranGrup::create([
        'jenis_tagihan_id' => $jenisTagihan->id,
        'tipe' => 'tarif',
        'nominal' => 500000,
    ]);

    expect((float) $grup->nominal)->toBe(500000.0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=JenisTagihanSasaranGrupTest`
Expected: FAIL with "Class \"App\Models\JenisTagihanSasaranGrup\" not found" (table and model don't exist yet)

- [ ] **Step 3: Write the migration**

```php
<?php
// database/migrations/2026_08_10_090000_create_jenis_tagihan_sasaran_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_tagihan_sasaran_grup', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_tagihan_id')->constrained('jenis_tagihan')->cascadeOnDelete();
            $table->enum('tipe', ['sasaran', 'tarif']);
            $table->decimal('nominal', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('jenis_tagihan_sasaran_kriteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_tagihan_sasaran_grup_id')->constrained('jenis_tagihan_sasaran_grup')->cascadeOnDelete();
            $table->enum('field', ['lembaga', 'tahun_ajaran', 'tingkat', 'kelas', 'jenis_kelamin', 'status_siswa']);
            $table->enum('operator', ['in', 'not_in']);
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jenis_tagihan_sasaran_kriteria');
        Schema::dropIfExists('jenis_tagihan_sasaran_grup');
    }
};
```

- [ ] **Step 4: Write the models**

```php
<?php
// app/Models/JenisTagihanSasaranGrup.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisTagihanSasaranGrup extends Model
{
    protected $table = 'jenis_tagihan_sasaran_grup';

    protected $fillable = ['jenis_tagihan_id', 'tipe', 'nominal'];

    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
        ];
    }

    public function jenisTagihan(): BelongsTo
    {
        return $this->belongsTo(JenisTagihan::class);
    }

    public function kriteria(): HasMany
    {
        return $this->hasMany(JenisTagihanSasaranKriteria::class, 'jenis_tagihan_sasaran_grup_id');
    }
}
```

```php
<?php
// app/Models/JenisTagihanSasaranKriteria.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JenisTagihanSasaranKriteria extends Model
{
    protected $table = 'jenis_tagihan_sasaran_kriteria';

    protected $fillable = ['jenis_tagihan_sasaran_grup_id', 'field', 'operator', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public function grup(): BelongsTo
    {
        return $this->belongsTo(JenisTagihanSasaranGrup::class, 'jenis_tagihan_sasaran_grup_id');
    }
}
```

- [ ] **Step 5: Run migration and test to verify it passes**

Run: `php artisan migrate && php artisan test --filter=JenisTagihanSasaranGrupTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_10_090000_create_jenis_tagihan_sasaran_table.php app/Models/JenisTagihanSasaranGrup.php app/Models/JenisTagihanSasaranKriteria.php tests/Feature/Keuangan/JenisTagihanSasaranGrupTest.php
git commit -m "feat(keuangan): add jenis_tagihan_sasaran_grup/kriteria tables for OR-of-AND targeting"
```

---

### Task 2: `nominal_tagihan_siswa` table

**Files:**
- Create: `database/migrations/2026_08_10_100000_create_nominal_tagihan_siswa_table.php`
- Create: `app/Models/NominalTagihanSiswa.php`
- Test: `tests/Feature/Keuangan/NominalTagihanSiswaTest.php`

**Interfaces:**
- Consumes: `App\Models\JenisTagihan`, `App\Models\Siswa` (existing, `database/factories/SiswaFactory.php` already exists)
- Produces: `NominalTagihanSiswa::create(['jenis_tagihan_id' => int, 'siswa_id' => int, 'nominal' => float])`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Keuangan/NominalTagihanSiswaTest.php

use App\Models\JenisTagihan;
use App\Models\NominalTagihanSiswa;
use App\Models\Siswa;

it('stores a per-siswa nominal override for a jenis_tagihan', function () {
    $jenisTagihan = JenisTagihan::factory()->create();
    $siswa = Siswa::factory()->create();

    NominalTagihanSiswa::create([
        'jenis_tagihan_id' => $jenisTagihan->id,
        'siswa_id' => $siswa->id,
        'nominal' => 300000,
    ]);

    $override = NominalTagihanSiswa::where('jenis_tagihan_id', $jenisTagihan->id)->where('siswa_id', $siswa->id)->first();
    expect((float) $override->nominal)->toBe(300000.0);
});

it('rejects a duplicate override for the same jenis_tagihan and siswa pair', function () {
    $jenisTagihan = JenisTagihan::factory()->create();
    $siswa = Siswa::factory()->create();

    NominalTagihanSiswa::create(['jenis_tagihan_id' => $jenisTagihan->id, 'siswa_id' => $siswa->id, 'nominal' => 300000]);

    expect(fn () => NominalTagihanSiswa::create(['jenis_tagihan_id' => $jenisTagihan->id, 'siswa_id' => $siswa->id, 'nominal' => 400000]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=NominalTagihanSiswaTest`
Expected: FAIL with "Class \"App\Models\NominalTagihanSiswa\" not found"

- [ ] **Step 3: Write the migration**

```php
<?php
// database/migrations/2026_08_10_100000_create_nominal_tagihan_siswa_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nominal_tagihan_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_tagihan_id')->constrained('jenis_tagihan')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->decimal('nominal', 12, 2);
            $table->timestamps();

            $table->unique(['jenis_tagihan_id', 'siswa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nominal_tagihan_siswa');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php
// app/Models/NominalTagihanSiswa.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NominalTagihanSiswa extends Model
{
    protected $table = 'nominal_tagihan_siswa';

    protected $fillable = ['jenis_tagihan_id', 'siswa_id', 'nominal'];

    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
        ];
    }

    public function jenisTagihan(): BelongsTo
    {
        return $this->belongsTo(JenisTagihan::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }
}
```

- [ ] **Step 5: Run migration and test to verify it passes**

Run: `php artisan migrate && php artisan test --filter=NominalTagihanSiswaTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_10_100000_create_nominal_tagihan_siswa_table.php app/Models/NominalTagihanSiswa.php tests/Feature/Keuangan/NominalTagihanSiswaTest.php
git commit -m "feat(keuangan): add nominal_tagihan_siswa table for per-student overrides"
```

---

### Task 3: Keringanan tables (`kategori_keringanan`, `jenis_tagihan_keringanan`, `siswa_keringanan`)

**Files:**
- Create: `database/migrations/2026_08_10_110000_create_keringanan_tables.php`
- Create: `app/Models/KategoriKeringanan.php`
- Create: `app/Models/JenisTagihanKeringanan.php`
- Create: `app/Models/SiswaKeringanan.php`
- Test: `tests/Feature/Keuangan/KeringananTest.php`

**Interfaces:**
- Consumes: `App\Models\Lembaga`, `App\Models\JenisTagihan`, `App\Models\Siswa`, `App\Models\Concerns\BelongsToTenant` (existing trait, auto-scopes queries by `lembaga_id` and auto-fills it on create)
- Produces: `KategoriKeringanan::create(['lembaga_id' => int, 'nama' => string, 'keterangan' => ?string])`, `JenisTagihanKeringanan::create(['jenis_tagihan_id' => int, 'kategori_keringanan_id' => int, 'tipe_potongan' => 'fixed'|'persen', 'nilai' => float, 'keterangan' => ?string])`, `SiswaKeringanan::create(['siswa_id' => int, 'kategori_keringanan_id' => int, 'berlaku_dari' => date, 'berlaku_sampai' => ?date])`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Keuangan/KeringananTest.php

use App\Models\JenisTagihan;
use App\Models\JenisTagihanKeringanan;
use App\Models\KategoriKeringanan;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\SiswaKeringanan;
use App\Models\User;

it('lets a jenis_tagihan define its own discount rule for a kategori_keringanan', function () {
    $jenisTagihan = JenisTagihan::factory()->create();
    $kategori = KategoriKeringanan::create(['lembaga_id' => $jenisTagihan->lembaga_id, 'nama' => 'Anak Pegawai']);

    $rule = JenisTagihanKeringanan::create([
        'jenis_tagihan_id' => $jenisTagihan->id,
        'kategori_keringanan_id' => $kategori->id,
        'tipe_potongan' => 'persen',
        'nilai' => 50,
    ]);

    expect($rule->kategoriKeringanan->nama)->toBe('Anak Pegawai');
    expect((float) $rule->nilai)->toBe(50.0);
});

it('marks a siswa as having a kategori_keringanan without storing a discount value on the pivot', function () {
    $siswa = Siswa::factory()->create();
    $kategori = KategoriKeringanan::create(['lembaga_id' => $siswa->lembaga_id, 'nama' => 'Anak Pegawai']);

    $siswaKeringanan = SiswaKeringanan::create([
        'siswa_id' => $siswa->id,
        'kategori_keringanan_id' => $kategori->id,
        'berlaku_dari' => now()->toDateString(),
    ]);

    expect($siswaKeringanan->berlaku_sampai)->toBeNull();
    expect($siswaKeringanan->kategoriKeringanan->nama)->toBe('Anak Pegawai');
});

it('scopes kategori_keringanan queries to the acting lembaga via BelongsToTenant', function () {
    $lembagaSendiri = Lembaga::factory()->create();
    $lembagaLain = Lembaga::factory()->create();

    KategoriKeringanan::create(['lembaga_id' => $lembagaSendiri->id, 'nama' => 'Anak Pegawai']);
    KategoriKeringanan::create(['lembaga_id' => $lembagaLain->id, 'nama' => 'Beasiswa Lembaga Lain']);

    $manager = User::factory()->create(['lembaga_id' => $lembagaSendiri->id]);
    $this->actingAs($manager);

    expect(KategoriKeringanan::count())->toBe(1);
    expect(KategoriKeringanan::first()->nama)->toBe('Anak Pegawai');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=KeringananTest`
Expected: FAIL with "Class \"App\Models\KategoriKeringanan\" not found"

- [ ] **Step 3: Write the migration**

```php
<?php
// database/migrations/2026_08_10_110000_create_keringanan_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kategori_keringanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->string('nama');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('jenis_tagihan_keringanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_tagihan_id')->constrained('jenis_tagihan')->cascadeOnDelete();
            $table->foreignId('kategori_keringanan_id')->constrained('kategori_keringanan')->restrictOnDelete();
            $table->enum('tipe_potongan', ['fixed', 'persen']);
            $table->decimal('nilai', 12, 2);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['jenis_tagihan_id', 'kategori_keringanan_id']);
        });

        Schema::create('siswa_keringanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('kategori_keringanan_id')->constrained('kategori_keringanan')->restrictOnDelete();
            $table->date('berlaku_dari');
            $table->date('berlaku_sampai')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siswa_keringanan');
        Schema::dropIfExists('jenis_tagihan_keringanan');
        Schema::dropIfExists('kategori_keringanan');
    }
};
```

- [ ] **Step 4: Write the models**

```php
<?php
// app/Models/KategoriKeringanan.php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KategoriKeringanan extends Model
{
    use BelongsToTenant;

    protected $table = 'kategori_keringanan';

    protected $fillable = ['lembaga_id', 'nama', 'keterangan'];

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function jenisTagihanKeringanan(): HasMany
    {
        return $this->hasMany(JenisTagihanKeringanan::class);
    }

    public function siswaKeringanan(): HasMany
    {
        return $this->hasMany(SiswaKeringanan::class);
    }
}
```

```php
<?php
// app/Models/JenisTagihanKeringanan.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JenisTagihanKeringanan extends Model
{
    protected $table = 'jenis_tagihan_keringanan';

    protected $fillable = ['jenis_tagihan_id', 'kategori_keringanan_id', 'tipe_potongan', 'nilai', 'keterangan'];

    protected function casts(): array
    {
        return [
            'nilai' => 'decimal:2',
        ];
    }

    public function jenisTagihan(): BelongsTo
    {
        return $this->belongsTo(JenisTagihan::class);
    }

    public function kategoriKeringanan(): BelongsTo
    {
        return $this->belongsTo(KategoriKeringanan::class);
    }
}
```

```php
<?php
// app/Models/SiswaKeringanan.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiswaKeringanan extends Model
{
    protected $table = 'siswa_keringanan';

    protected $fillable = ['siswa_id', 'kategori_keringanan_id', 'berlaku_dari', 'berlaku_sampai'];

    protected function casts(): array
    {
        return [
            'berlaku_dari' => 'date',
            'berlaku_sampai' => 'date',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function kategoriKeringanan(): BelongsTo
    {
        return $this->belongsTo(KategoriKeringanan::class);
    }
}
```

- [ ] **Step 5: Run migration and test to verify it passes**

Run: `php artisan migrate && php artisan test --filter=KeringananTest`
Expected: PASS (3 tests)

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_10_110000_create_keringanan_tables.php app/Models/KategoriKeringanan.php app/Models/JenisTagihanKeringanan.php app/Models/SiswaKeringanan.php tests/Feature/Keuangan/KeringananTest.php
git commit -m "feat(keuangan): add keringanan tables (per-jenis-tagihan discount rules)"
```

---

### Task 4: `pembayaran_tagihan` junction table

**Files:**
- Create: `database/migrations/2026_08_10_120000_create_pembayaran_tagihan_table.php`
- Create: `app/Models/PembayaranTagihan.php`
- Test: `tests/Feature/Keuangan/PembayaranTagihanTest.php`

**Interfaces:**
- Consumes: `App\Models\Pembayaran`, `App\Models\Tagihan` (existing, both have factories)
- Produces: `PembayaranTagihan::create(['pembayaran_id' => int, 'tagihan_id' => int, 'amount_allocated' => float])`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Keuangan/PembayaranTagihanTest.php

use App\Models\Pembayaran;
use App\Models\PembayaranTagihan;
use App\Models\Tagihan;

it('lets a single pembayaran allocate its amount across multiple tagihan rows', function () {
    $pembayaran = Pembayaran::factory()->create();
    $tagihanSatu = Tagihan::factory()->create();
    $tagihanDua = Tagihan::factory()->create();

    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihanSatu->id, 'amount_allocated' => 100000]);
    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihanDua->id, 'amount_allocated' => 50000]);

    $alokasi = PembayaranTagihan::where('pembayaran_id', $pembayaran->id)->get();
    expect($alokasi)->toHaveCount(2);
    expect((float) $alokasi->sum('amount_allocated'))->toBe(150000.0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PembayaranTagihanTest`
Expected: FAIL with "Class \"App\Models\PembayaranTagihan\" not found"

- [ ] **Step 3: Write the migration**

```php
<?php
// database/migrations/2026_08_10_120000_create_pembayaran_tagihan_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembayaran_tagihan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pembayaran_id')->constrained('pembayaran')->cascadeOnDelete();
            $table->foreignId('tagihan_id')->constrained('tagihan')->cascadeOnDelete();
            $table->decimal('amount_allocated', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran_tagihan');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php
// app/Models/PembayaranTagihan.php

namespace App\Models;

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

- [ ] **Step 5: Run migration and test to verify it passes**

Run: `php artisan migrate && php artisan test --filter=PembayaranTagihanTest`
Expected: PASS (1 test)

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_10_120000_create_pembayaran_tagihan_table.php app/Models/PembayaranTagihan.php tests/Feature/Keuangan/PembayaranTagihanTest.php
git commit -m "feat(keuangan): add pembayaran_tagihan junction table for multi-bill allocation"
```

---

### Task 5: `tagihan` polymorphic columns + `Tagihan`/`Siswa` model updates

**Files:**
- Create: `database/migrations/2026_08_10_130000_add_polymorphic_columns_to_tagihan_table.php`
- Modify: `app/Models/Tagihan.php` (full replacement)
- Modify: `app/Models/Siswa.php:1-77` (full replacement)
- Test: `tests/Feature/Keuangan/TagihanPolymorphicTest.php`

**Interfaces:**
- Consumes: `App\Models\Pendaftaran` (existing), `App\Models\Siswa` (existing), `App\Models\PembayaranTagihan` (Task 4), `App\Models\JenisTagihan` (existing)
- Produces: `Tagihan::tagihable(): MorphTo`, `Tagihan::jenisTagihan(): BelongsTo`, `Tagihan::pembayaranTagihan(): HasMany`, `Siswa::tagihan(): MorphMany`. `tagihan` table gains: `tagihable_type` (string, nullable), `tagihable_id` (unsignedBigInteger, nullable), `jenis_tagihan_id` (nullable FK), `billing_period` (string(7), nullable), `source_trigger` (string, default `'manual'`), `discount_amount`/`discount_type`/`net_amount` (nullable), `paid_amount` (decimal, default 0), `cancelled_by`/`cancelled_at`/`cancel_reason` (nullable). `pendaftaran_id` becomes nullable. `kategori` enum widens to include `spp,tahunan,kegiatan,custom`. `status` enum widens to include `sebagian,dibatalkan`.

**IMPORTANT:** This task does NOT touch `app/Http/Controllers/Admin/TagihanController.php`, `app/Http/Controllers/Portal/TagihanController.php`, or `app/Http/Controllers/Admin/PembayaranController.php`. Those controllers all resolve lembaga scope via `$tagihan->pendaftaran->lembaga_id`, and `pendaftaran_id` stays populated for every PPDB tagihan (this migration only adds new nullable columns and loosens one existing column's nullability) — the `pendaftaran()` relation keeps working exactly as before. Do not "refactor" those controllers; there is nothing to refactor.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Keuangan/TagihanPolymorphicTest.php

use App\Models\Pendaftaran;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Models\User;

it('lets a tagihan target a siswa via the tagihable polymorphic relation', function () {
    $siswa = Siswa::factory()->create();

    $tagihan = Tagihan::create([
        'tagihable_type' => Siswa::class,
        'tagihable_id' => $siswa->id,
        'kategori' => 'spp',
        'billing_period' => '2026-08',
        'source_trigger' => 'cron',
        'total_tagihan' => 300000,
        'net_amount' => 300000,
        'status' => 'belum_bayar',
    ]);

    expect($tagihan->tagihable)->toBeInstanceOf(Siswa::class);
    expect($tagihan->tagihable->id)->toBe($siswa->id);
    expect($tagihan->pendaftaran_id)->toBeNull();
    expect($siswa->tagihan)->toHaveCount(1);
});

it('still resolves the pendaftaran relation for PPDB tagihan rows created without tagihable columns set', function () {
    $pendaftaran = Pendaftaran::factory()->create();

    $tagihan = Tagihan::create([
        'pendaftaran_id' => $pendaftaran->id,
        'kategori' => 'pendaftaran',
        'total_tagihan' => 150000,
        'net_amount' => 150000,
        'status' => 'belum_bayar',
    ]);

    expect($tagihan->pendaftaran->id)->toBe($pendaftaran->id);
});

it('allows the dibatalkan status with a cancellation audit trail', function () {
    $pendaftaran = Pendaftaran::factory()->create();
    $admin = User::factory()->create();
    $tagihan = Tagihan::factory()->create(['pendaftaran_id' => $pendaftaran->id]);

    $tagihan->update([
        'status' => 'dibatalkan',
        'cancelled_by' => $admin->id,
        'cancelled_at' => now(),
        'cancel_reason' => 'Salah generate',
    ]);

    expect($tagihan->fresh()->status)->toBe('dibatalkan');
    expect($tagihan->fresh()->cancelled_by)->toBe($admin->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TagihanPolymorphicTest`
Expected: FAIL — "tagihable_type" column not found / "pendaftaran_id" NOT NULL constraint violated on the first test

- [ ] **Step 3: Write the migration**

(Corrected after a task-review fix — the original plan text below was superseded; see commit abfffa1.)

```php
<?php
// database/migrations/2026_08_10_130000_add_polymorphic_columns_to_tagihan_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The (pendaftaran_id, kategori) unique index is intentionally kept in place.
        // MySQL/InnoDB treats NULL as distinct from every other NULL in a unique index,
        // so it never blocked the polymorphic (NULL pendaftaran_id) rows this migration
        // introduces, while it still guards against duplicate PPDB tagihan rows for the
        // same pendaftaran_id + kategori. It also continues to back the pendaftaran_id
        // foreign key, so no extra plain index is needed.

        DB::statement('ALTER TABLE tagihan MODIFY pendaftaran_id BIGINT UNSIGNED NULL');

        Schema::table('tagihan', function (Blueprint $table) {
            $table->string('tagihable_type')->nullable()->after('pendaftaran_id');
            $table->unsignedBigInteger('tagihable_id')->nullable()->after('tagihable_type');
            $table->foreignId('jenis_tagihan_id')->nullable()->after('tagihable_id')->constrained('jenis_tagihan')->nullOnDelete();
            $table->string('billing_period', 7)->nullable()->after('kategori');
            $table->string('source_trigger')->default('manual')->after('billing_period');
            $table->decimal('discount_amount', 12, 2)->nullable()->after('total_tagihan');
            $table->string('discount_type')->nullable()->after('discount_amount');
            $table->decimal('net_amount', 12, 2)->nullable()->after('discount_type');
            $table->decimal('paid_amount', 12, 2)->default(0)->after('net_amount');
            $table->foreignId('cancelled_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            $table->text('cancel_reason')->nullable()->after('cancelled_at');

            $table->index(['tagihable_type', 'tagihable_id']);
        });

        DB::statement("ALTER TABLE tagihan MODIFY kategori ENUM('pendaftaran', 'daftar_ulang', 'spp', 'tahunan', 'kegiatan', 'custom') NOT NULL");
        DB::statement("ALTER TABLE tagihan MODIFY status ENUM('belum_bayar', 'dicicil', 'lunas', 'sebagian', 'dibatalkan') NOT NULL DEFAULT 'belum_bayar'");
    }

    // NOTE: Rolling back is only safe on a schema with no siswa-targeted (polymorphic)
    // tagihan rows yet — narrowing the enums back and re-tightening pendaftaran_id to
    // NOT NULL will fail (or corrupt data) once such rows exist.
    public function down(): void
    {
        DB::statement("ALTER TABLE tagihan MODIFY status ENUM('belum_bayar', 'dicicil', 'lunas') NOT NULL DEFAULT 'belum_bayar'");
        DB::statement("ALTER TABLE tagihan MODIFY kategori ENUM('pendaftaran', 'daftar_ulang') NOT NULL");

        Schema::table('tagihan', function (Blueprint $table) {
            $table->dropIndex(['tagihable_type', 'tagihable_id']);
            $table->dropConstrainedForeignId('jenis_tagihan_id');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['tagihable_type', 'tagihable_id', 'billing_period', 'source_trigger', 'discount_amount', 'discount_type', 'net_amount', 'paid_amount', 'cancelled_at', 'cancel_reason']);
        });

        DB::statement('ALTER TABLE tagihan MODIFY pendaftaran_id BIGINT UNSIGNED NOT NULL');
    }
};
```

- [ ] **Step 4: Replace `app/Models/Tagihan.php`**

```php
<?php
// app/Models/Tagihan.php

namespace App\Models;

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
        return $this->hasManyThrough(Cicilan::class, SkemaCicilan::class, 'tagihan_id', 'skema_cicilan_id');
    }

    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class);
    }

    public function pembayaranTagihan(): HasMany
    {
        return $this->hasMany(PembayaranTagihan::class);
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'total_tagihan'])
            ->logOnlyDirty()
            ->useLogName('tagihan');
    }
}
```

- [ ] **Step 5: Replace `app/Models/Siswa.php`**

```php
<?php
// app/Models/Siswa.php

namespace App\Models;

use App\Enums\StatusSiswa;
use App\Enums\SumberDataSiswa;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Siswa extends Model
{
    use HasFactory, BelongsToTenant, LogsActivity;

    protected $table = 'siswa';

    protected $fillable = [
        'lembaga_id', 'kelas_id', 'calon_murid_id', 'pendaftaran_asal_id', 'user_id',
        'sumber_data', 'nis', 'nisn', 'nama_lengkap', 'jenis_kelamin',
        'tempat_lahir', 'tanggal_lahir', 'agama', 'status',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'sumber_data' => SumberDataSiswa::class,
            'status' => StatusSiswa::class,
        ];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function calonMurid(): BelongsTo
    {
        return $this->belongsTo(CalonMurid::class);
    }

    public function pendaftaranAsal(): BelongsTo
    {
        return $this->belongsTo(Pendaftaran::class, 'pendaftaran_asal_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orangTua(): BelongsToMany
    {
        return $this->belongsToMany(OrangTua::class, 'siswa_orang_tua')
            ->withPivot(['hubungan', 'is_kontak_utama'])
            ->withTimestamps()
            ->using(SiswaOrangTua::class);
    }

    public function tagihan(): MorphMany
    {
        return $this->morphMany(Tagihan::class, 'tagihable');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nama_lengkap', 'kelas_id', 'status', 'lembaga_id'])
            ->logOnlyDirty()
            ->useLogName('siswa');
    }
}
```

- [ ] **Step 6: Run migration and test to verify it passes**

Run: `php artisan migrate && php artisan test --filter=TagihanPolymorphicTest`
Expected: PASS (3 tests)

- [ ] **Step 7: Run the full existing PPDB tagihan/pembayaran test files to confirm nothing broke**

Run: `php artisan test tests/Feature/Admin/TagihanIndexTest.php tests/Feature/Admin/TagihanSusulanTest.php tests/Feature/Admin/TagihanDaftarUlangHookTest.php tests/Feature/Admin/JenisTagihanTest.php tests/Feature/Admin/CatatManualPembayaranTest.php tests/Feature/Admin/VerifikasiPembayaranTest.php tests/Feature/Portal/TagihanPembayaranTest.php tests/Feature/Spmb/TagihanPendaftaranHookTest.php`
Expected: PASS, all of them, unchanged

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_10_130000_add_polymorphic_columns_to_tagihan_table.php app/Models/Tagihan.php app/Models/Siswa.php tests/Feature/Keuangan/TagihanPolymorphicTest.php
git commit -m "feat(keuangan): make tagihan polymorphic (tagihable_type/tagihable_id) alongside pendaftaran_id"
```

---

### Task 6: Backfill legacy rows + `TagihanGenerator` dual-write + `TagihanFactory`

**Files:**
- Create: `database/migrations/2026_08_10_140000_backfill_tagihan_tagihable_columns.php`
- Modify: `app/Services/TagihanGenerator.php:55-68`
- Modify: `database/factories/TagihanFactory.php` (full replacement)
- Test: `tests/Feature/Keuangan/TagihanBackfillTest.php`
- Test: `tests/Feature/Keuangan/TagihanGeneratorDualWriteTest.php`

**Interfaces:**
- Consumes: `App\Models\Pendaftaran`, `App\Models\Tagihan` (Task 5), `App\Services\TagihanGenerator::generate(Pendaftaran $pendaftaran, string $kategori): ?Tagihan` (existing signature, unchanged)
- Produces: every `Tagihan` created by `TagihanGenerator::generate()` now also has `tagihable_type`/`tagihable_id` set; `TagihanFactory` auto-derives them from `pendaftaran_id` when not explicitly overridden

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Keuangan/TagihanBackfillTest.php

use App\Models\Pendaftaran;
use Illuminate\Support\Facades\DB;

it('backfills tagihable_type and tagihable_id for legacy rows that only have pendaftaran_id', function () {
    $pendaftaran = Pendaftaran::factory()->create();

    $legacyId = DB::table('tagihan')->insertGetId([
        'pendaftaran_id' => $pendaftaran->id,
        'kategori' => 'pendaftaran',
        'total_tagihan' => 150000,
        'net_amount' => 150000,
        'paid_amount' => 0,
        'status' => 'belum_bayar',
        'source_trigger' => 'manual',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('tagihan')->where('id', $legacyId)->value('tagihable_type'))->toBeNull();

    (require database_path('migrations/2026_08_10_140000_backfill_tagihan_tagihable_columns.php'))->up();

    $row = DB::table('tagihan')->where('id', $legacyId)->first();
    expect($row->tagihable_type)->toBe(Pendaftaran::class);
    expect((int) $row->tagihable_id)->toBe($pendaftaran->id);
});

it('does not touch rows that already have tagihable columns set', function () {
    $pendaftaran = Pendaftaran::factory()->create();
    $siswa = \App\Models\Siswa::factory()->create();

    $alreadySetId = DB::table('tagihan')->insertGetId([
        'tagihable_type' => \App\Models\Siswa::class,
        'tagihable_id' => $siswa->id,
        'kategori' => 'spp',
        'total_tagihan' => 300000,
        'net_amount' => 300000,
        'paid_amount' => 0,
        'status' => 'belum_bayar',
        'source_trigger' => 'cron',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    (require database_path('migrations/2026_08_10_140000_backfill_tagihan_tagihable_columns.php'))->up();

    $row = DB::table('tagihan')->where('id', $alreadySetId)->first();
    expect($row->tagihable_type)->toBe(\App\Models\Siswa::class);
    expect((int) $row->tagihable_id)->toBe($siswa->id);
});
```

```php
<?php
// tests/Feature/Keuangan/TagihanGeneratorDualWriteTest.php

use App\Models\JenisTagihan;
use App\Models\NominalTagihanJalur;
use App\Models\Pendaftaran;
use App\Services\TagihanGenerator;

it('sets tagihable_type and tagihable_id to the pendaftaran when generating a PPDB tagihan', function () {
    $pendaftaran = Pendaftaran::factory()->create();

    $jenisTagihan = JenisTagihan::create([
        'lembaga_id' => $pendaftaran->lembaga_id,
        'nama' => 'Biaya Pendaftaran',
        'kategori' => 'pendaftaran',
        'bisa_dicicil' => false,
    ]);
    NominalTagihanJalur::create([
        'jenis_tagihan_id' => $jenisTagihan->id,
        'jalur_ppdb_id' => $pendaftaran->jalur_ppdb_id,
        'nominal' => 150000,
    ]);

    $tagihan = app(TagihanGenerator::class)->generate($pendaftaran, 'pendaftaran');

    expect($tagihan)->not->toBeNull();
    expect($tagihan->tagihable_type)->toBe(Pendaftaran::class);
    expect($tagihan->tagihable_id)->toBe($pendaftaran->id);
    expect((float) $tagihan->net_amount)->toBe(150000.0);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=TagihanBackfillTest`
Expected: FAIL — file `2026_08_10_140000_backfill_tagihan_tagihable_columns.php` doesn't exist yet

Run: `php artisan test --filter=TagihanGeneratorDualWriteTest`
Expected: FAIL — `$tagihan->tagihable_type` is null (TagihanGenerator doesn't set it yet)

- [ ] **Step 3: Write the backfill migration**

```php
<?php
// database/migrations/2026_08_10_140000_backfill_tagihan_tagihable_columns.php

use App\Models\Pendaftaran;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One-time historical backfill: every tagihan row that predates the
     * polymorphic columns (added in 2026_08_10_130000) only has
     * pendaftaran_id set. All pre-existing tagihan rows are PPDB rows, so
     * tagihable is always Pendaftaran. Guarded by whereNull so re-running
     * this migration (e.g. via migrate:refresh) never overwrites rows that
     * were created after the polymorphic columns already existed.
     */
    public function up(): void
    {
        DB::table('tagihan')
            ->whereNull('tagihable_type')
            ->whereNotNull('pendaftaran_id')
            ->update(['tagihable_type' => Pendaftaran::class]);

        DB::statement(
            'UPDATE tagihan SET tagihable_id = pendaftaran_id WHERE tagihable_type = ? AND tagihable_id IS NULL',
            [Pendaftaran::class]
        );
    }

    public function down(): void
    {
        DB::table('tagihan')
            ->where('tagihable_type', Pendaftaran::class)
            ->update(['tagihable_type' => null, 'tagihable_id' => null]);
    }
};
```

- [ ] **Step 4: Update `TagihanGenerator::generate()` to dual-write**

Modify `app/Services/TagihanGenerator.php`, the `DB::transaction` closure (currently lines 55-68):

```php
        return DB::transaction(function () use ($pendaftaran, $kategori, $items, $total) {
            $tagihan = Tagihan::create([
                'pendaftaran_id' => $pendaftaran->id,
                'tagihable_type' => Pendaftaran::class,
                'tagihable_id' => $pendaftaran->id,
                'kategori' => $kategori,
                'total_tagihan' => $total,
                'net_amount' => $total,
                'status' => $total == 0 ? 'lunas' : 'belum_bayar',
            ]);

            foreach ($items as $item) {
                TagihanItem::create(array_merge(['tagihan_id' => $tagihan->id], $item));
            }

            return $tagihan;
        });
```

- [ ] **Step 5: Replace `database/factories/TagihanFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Models\Pendaftaran;
use App\Models\Tagihan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tagihan> */
class TagihanFactory extends Factory
{
    protected $model = Tagihan::class;

    public function definition(): array
    {
        return [
            'pendaftaran_id' => Pendaftaran::factory(),
            'kategori' => 'pendaftaran',
            'total_tagihan' => 150000,
            'net_amount' => 150000,
            'status' => 'belum_bayar',
        ];
    }

    /**
     * Existing tests create Tagihan::factory()->create(['pendaftaran_id' => $x])
     * without knowing about tagihable_type/tagihable_id. This keeps those call
     * sites working unmodified by deriving the polymorphic columns from
     * whatever pendaftaran_id ends up on the model after factory state/overrides
     * are applied, exactly like TagihanGenerator now does for real PPDB rows.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Tagihan $tagihan) {
            if ($tagihan->pendaftaran_id && ! $tagihan->tagihable_type) {
                $tagihan->tagihable_type = Pendaftaran::class;
                $tagihan->tagihable_id = $tagihan->pendaftaran_id;
            }
        });
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=TagihanBackfillTest`
Expected: PASS (2 tests)

Run: `php artisan test --filter=TagihanGeneratorDualWriteTest`
Expected: PASS (1 test)

- [ ] **Step 7: Run the full existing PPDB tagihan/pembayaran test files again**

Run: `php artisan test tests/Feature/Admin/TagihanIndexTest.php tests/Feature/Admin/TagihanSusulanTest.php tests/Feature/Admin/TagihanDaftarUlangHookTest.php tests/Feature/Admin/JenisTagihanTest.php tests/Feature/Admin/CatatManualPembayaranTest.php tests/Feature/Admin/VerifikasiPembayaranTest.php tests/Feature/Portal/TagihanPembayaranTest.php tests/Feature/Spmb/TagihanPendaftaranHookTest.php`
Expected: PASS, all of them, unchanged

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_10_140000_backfill_tagihan_tagihable_columns.php app/Services/TagihanGenerator.php database/factories/TagihanFactory.php tests/Feature/Keuangan/TagihanBackfillTest.php tests/Feature/Keuangan/TagihanGeneratorDualWriteTest.php
git commit -m "feat(keuangan): backfill legacy tagihan rows and dual-write tagihable columns going forward"
```

---

### Task 7: `jenis_tagihan` billing columns + model update

**Files:**
- Create: `database/migrations/2026_08_10_150000_add_billing_columns_to_jenis_tagihan_table.php`
- Modify: `app/Models/JenisTagihan.php` (full replacement)
- Test: `tests/Feature/Keuangan/JenisTagihanBillingColumnsTest.php`

**Interfaces:**
- Consumes: `App\Models\JenisTagihanSasaranGrup` (Task 1), `App\Models\NominalTagihanSiswa` (Task 2), `App\Models\JenisTagihanKeringanan` (Task 3)
- Produces: `JenisTagihan::sasaranGrup(): HasMany`, `JenisTagihan::nominalTagihanSiswa(): HasMany`, `JenisTagihan::keringananRules(): HasMany`. `jenis_tagihan` table gains: `priority_score` (nullable), `default_amount` (nullable decimal), `mode` (enum `manual`|`otomatis`, default `manual`), `tanggal_mulai`/`tanggal_selesai` (nullable date), `tanggal_generate`/`hari_jatuh_tempo` (nullable tinyint), `va_expire_hours` (nullable), `is_active` (boolean, default true), `last_generated_period` (nullable string(7)). `kategori` enum widens to include `spp,tahunan,kegiatan,custom`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Keuangan/JenisTagihanBillingColumnsTest.php

use App\Models\JenisTagihan;
use App\Models\Lembaga;

it('stores mode otomatis scheduling fields for a spp jenis_tagihan', function () {
    $jenisTagihan = JenisTagihan::create([
        'lembaga_id' => Lembaga::factory()->create()->id,
        'nama' => 'SPP Bulanan',
        'kategori' => 'spp',
        'bisa_dicicil' => false,
        'priority_score' => 1,
        'default_amount' => 300000,
        'mode' => 'otomatis',
        'tanggal_mulai' => '2026-08-01',
        'tanggal_generate' => 1,
        'hari_jatuh_tempo' => 10,
    ]);

    expect($jenisTagihan->mode)->toBe('otomatis');
    expect($jenisTagihan->is_active)->toBeTrue();
    expect((float) $jenisTagihan->default_amount)->toBe(300000.0);
});

it('defaults mode to manual and is_active to true when not specified', function () {
    $jenisTagihan = JenisTagihan::factory()->create();

    expect($jenisTagihan->mode)->toBe('manual');
    expect($jenisTagihan->is_active)->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=JenisTagihanBillingColumnsTest`
Expected: FAIL — "priority_score" column not found

- [ ] **Step 3: Write the migration**

```php
<?php
// database/migrations/2026_08_10_150000_add_billing_columns_to_jenis_tagihan_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jenis_tagihan', function (Blueprint $table) {
            $table->unsignedInteger('priority_score')->nullable()->after('kategori');
            $table->decimal('default_amount', 12, 2)->nullable()->after('priority_score');
            $table->enum('mode', ['manual', 'otomatis'])->default('manual')->after('default_amount');
            $table->date('tanggal_mulai')->nullable()->after('mode');
            $table->date('tanggal_selesai')->nullable()->after('tanggal_mulai');
            $table->unsignedTinyInteger('tanggal_generate')->nullable()->after('tanggal_selesai');
            $table->unsignedTinyInteger('hari_jatuh_tempo')->nullable()->after('tanggal_generate');
            $table->unsignedInteger('va_expire_hours')->nullable()->after('hari_jatuh_tempo');
            $table->boolean('is_active')->default(true)->after('va_expire_hours');
            $table->string('last_generated_period', 7)->nullable()->after('is_active');
        });

        DB::statement("ALTER TABLE jenis_tagihan MODIFY kategori ENUM('pendaftaran', 'daftar_ulang', 'lainnya', 'spp', 'tahunan', 'kegiatan', 'custom') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE jenis_tagihan MODIFY kategori ENUM('pendaftaran', 'daftar_ulang', 'lainnya') NOT NULL");

        Schema::table('jenis_tagihan', function (Blueprint $table) {
            $table->dropColumn([
                'priority_score', 'default_amount', 'mode', 'tanggal_mulai', 'tanggal_selesai',
                'tanggal_generate', 'hari_jatuh_tempo', 'va_expire_hours', 'is_active', 'last_generated_period',
            ]);
        });
    }
};
```

- [ ] **Step 4: Replace `app/Models/JenisTagihan.php`**

```php
<?php
// app/Models/JenisTagihan.php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisTagihan extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'jenis_tagihan';

    protected $fillable = [
        'lembaga_id', 'nama', 'kategori', 'bisa_dicicil', 'maks_cicilan',
        'priority_score', 'default_amount', 'mode',
        'tanggal_mulai', 'tanggal_selesai', 'tanggal_generate', 'hari_jatuh_tempo',
        'va_expire_hours', 'is_active', 'last_generated_period',
    ];

    protected function casts(): array
    {
        return [
            'bisa_dicicil' => 'boolean',
            'default_amount' => 'decimal:2',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function nominalJalur(): HasMany
    {
        return $this->hasMany(NominalTagihanJalur::class);
    }

    public function tagihanItem(): HasMany
    {
        return $this->hasMany(TagihanItem::class);
    }

    public function sasaranGrup(): HasMany
    {
        return $this->hasMany(JenisTagihanSasaranGrup::class);
    }

    public function nominalTagihanSiswa(): HasMany
    {
        return $this->hasMany(NominalTagihanSiswa::class);
    }

    public function keringananRules(): HasMany
    {
        return $this->hasMany(JenisTagihanKeringanan::class);
    }
}
```

- [ ] **Step 5: Run migration and test to verify it passes**

Run: `php artisan migrate && php artisan test --filter=JenisTagihanBillingColumnsTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Run the existing `JenisTagihanTest.php` to confirm nothing broke**

Run: `php artisan test tests/Feature/Admin/JenisTagihanTest.php`
Expected: PASS, unchanged

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_10_150000_add_billing_columns_to_jenis_tagihan_table.php app/Models/JenisTagihan.php tests/Feature/Keuangan/JenisTagihanBillingColumnsTest.php
git commit -m "feat(keuangan): add billing schedule columns to jenis_tagihan"
```

---

### Task 8: `pembayaran` wallet-readiness columns + model update

**Files:**
- Create: `database/migrations/2026_08_10_160000_add_wallet_columns_to_pembayaran_table.php`
- Modify: `app/Models/Pembayaran.php` (full replacement)
- Test: `tests/Feature/Keuangan/PembayaranWalletColumnsTest.php`

**Interfaces:**
- Consumes: `App\Models\PembayaranTagihan` (Task 4)
- Produces: `Pembayaran::pembayaranTagihan(): HasMany`. `pembayaran` table gains: `wallet_id` (nullable unsignedBigInteger, no FK yet — the `wallets` table is created in Sub-project 3, which will add the constraint), `is_auto_allocation` (boolean, default false), `channel_reference` (nullable string), `identifier_method` (enum `manual`|`nfc`, default `manual`). `metode` enum widens to include `cash,qris,wallet_auto,wallet_saldo`. `sumber` enum widens to include `orang_tua`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Keuangan/PembayaranWalletColumnsTest.php

use App\Models\Pembayaran;
use App\Models\Tagihan;

it('accepts the new metode and sumber values for siswa-facing payments', function () {
    $pembayaran = Pembayaran::create([
        'tagihan_id' => Tagihan::factory()->create()->id,
        'sumber' => 'orang_tua',
        'metode' => 'wallet_auto',
        'status' => 'lunas',
        'is_auto_allocation' => true,
    ]);

    expect($pembayaran->metode)->toBe('wallet_auto');
    expect($pembayaran->sumber)->toBe('orang_tua');
    expect($pembayaran->is_auto_allocation)->toBeTrue();
});

it('defaults identifier_method to manual and is_auto_allocation to false', function () {
    $pembayaran = Pembayaran::factory()->create();

    expect($pembayaran->identifier_method)->toBe('manual');
    expect($pembayaran->is_auto_allocation)->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PembayaranWalletColumnsTest`
Expected: FAIL — SQL error, 'wallet_auto' is not a valid enum value for `metode` yet

- [ ] **Step 3: Write the migration**

```php
<?php
// database/migrations/2026_08_10_160000_add_wallet_columns_to_pembayaran_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pembayaran', function (Blueprint $table) {
            $table->unsignedBigInteger('wallet_id')->nullable()->after('cicilan_id');
            $table->boolean('is_auto_allocation')->default(false)->after('metode');
            $table->string('channel_reference')->nullable()->after('is_auto_allocation');
            $table->enum('identifier_method', ['manual', 'nfc'])->default('manual')->after('channel_reference');

            $table->index('wallet_id');
        });

        DB::statement("ALTER TABLE pembayaran MODIFY metode ENUM('transfer_manual', 'va_bri', 'cash', 'qris', 'wallet_auto', 'wallet_saldo') NOT NULL DEFAULT 'transfer_manual'");
        DB::statement("ALTER TABLE pembayaran MODIFY sumber ENUM('calon_siswa', 'admin', 'orang_tua') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE pembayaran MODIFY sumber ENUM('calon_siswa', 'admin') NOT NULL");
        DB::statement("ALTER TABLE pembayaran MODIFY metode ENUM('transfer_manual', 'va_bri') NOT NULL DEFAULT 'transfer_manual'");

        Schema::table('pembayaran', function (Blueprint $table) {
            $table->dropIndex(['wallet_id']);
            $table->dropColumn(['wallet_id', 'is_auto_allocation', 'channel_reference', 'identifier_method']);
        });
    }
};
```

- [ ] **Step 4: Replace `app/Models/Pembayaran.php`**

```php
<?php
// app/Models/Pembayaran.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Pembayaran extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'pembayaran';

    protected $fillable = [
        'tagihan_id', 'cicilan_id', 'sumber', 'metode', 'file_path',
        'status', 'catatan_verifikasi', 'diverifikasi_oleh_user_id', 'diverifikasi_pada',
        'wallet_id', 'is_auto_allocation', 'channel_reference', 'identifier_method',
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'catatan_verifikasi', 'diverifikasi_oleh_user_id'])
            ->logOnlyDirty()
            ->useLogName('pembayaran');
    }
}
```

- [ ] **Step 5: Run migration and test to verify it passes**

Run: `php artisan migrate && php artisan test --filter=PembayaranWalletColumnsTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Run the existing pembayaran-related test files to confirm nothing broke**

Run: `php artisan test tests/Feature/Admin/CatatManualPembayaranTest.php tests/Feature/Admin/VerifikasiPembayaranTest.php tests/Feature/Portal/TagihanPembayaranTest.php`
Expected: PASS, all of them, unchanged

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_10_160000_add_wallet_columns_to_pembayaran_table.php app/Models/Pembayaran.php tests/Feature/Keuangan/PembayaranWalletColumnsTest.php
git commit -m "feat(keuangan): add wallet-readiness columns to pembayaran"
```

---

### Task 9: Migrate `lembaga` iuran data into `jenis_tagihan`

**Files:**
- Create: `database/migrations/2026_08_10_170000_migrate_lembaga_iuran_to_jenis_tagihan.php`
- Test: `tests/Feature/Keuangan/LembagaIuranMigrationTest.php`

**Interfaces:**
- Consumes: `lembaga.memungut_iuran`/`nominal_iuran`/`periode_iuran` columns (existing, still present until Task 10 drops them), `jenis_tagihan` columns from Task 7 (`mode`, `default_amount`, `tanggal_generate`, `hari_jatuh_tempo`, `is_active`)
- Produces: one `jenis_tagihan` row per `lembaga` where `memungut_iuran = true`, `kategori = 'spp'`, idempotent (a second run creates nothing new)

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Keuangan/LembagaIuranMigrationTest.php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The memungut_iuran/nominal_iuran/periode_iuran columns this migration
 * reads from are dropped from `lembaga` by Task 10's migration, which runs
 * later in this same plan. By the time this test executes, migrate:fresh
 * has already applied every migration file in the repo — including that
 * drop. Re-adding the columns here for the duration of this one test lets
 * us exercise the real migration logic against a realistic "before" row
 * without depending on migration file ordering at test-run time. MySQL DDL
 * isn't transactional, so the try/finally guarantees the columns are gone
 * again before any other test runs, pass or fail.
 */
function withLegacyIuranColumns(callable $callback): void
{
    Schema::table('lembaga', function (Blueprint $table) {
        $table->boolean('memungut_iuran')->default(false);
        $table->decimal('nominal_iuran', 15, 2)->nullable();
        $table->enum('periode_iuran', ['bulanan', 'tahunan'])->nullable();
    });

    try {
        $callback();
    } finally {
        Schema::table('lembaga', function (Blueprint $table) {
            $table->dropColumn(['memungut_iuran', 'nominal_iuran', 'periode_iuran']);
        });
    }
}

it('creates a spp jenis_tagihan for every lembaga that had memungut_iuran enabled', function () {
    withLegacyIuranColumns(function () {
        $lembaga = Lembaga::factory()->create();
        DB::table('lembaga')->where('id', $lembaga->id)->update([
            'memungut_iuran' => true,
            'nominal_iuran' => 275000,
            'periode_iuran' => 'bulanan',
        ]);

        (require database_path('migrations/2026_08_10_170000_migrate_lembaga_iuran_to_jenis_tagihan.php'))->up();

        $jenisTagihan = JenisTagihan::where('lembaga_id', $lembaga->id)->where('kategori', 'spp')->first();

        expect($jenisTagihan)->not->toBeNull();
        expect((float) $jenisTagihan->default_amount)->toBe(275000.0);
        expect($jenisTagihan->mode)->toBe('otomatis');
        expect($jenisTagihan->nama)->toBe('SPP Bulanan');
        expect($jenisTagihan->tanggal_generate)->toBe(1);
        expect($jenisTagihan->hari_jatuh_tempo)->toBe(10);
    });
});

it('does not duplicate the jenis_tagihan when the migration runs twice', function () {
    withLegacyIuranColumns(function () {
        $lembaga = Lembaga::factory()->create();
        DB::table('lembaga')->where('id', $lembaga->id)->update([
            'memungut_iuran' => true,
            'nominal_iuran' => 100000,
            'periode_iuran' => 'bulanan',
        ]);

        $migration = require database_path('migrations/2026_08_10_170000_migrate_lembaga_iuran_to_jenis_tagihan.php');
        $migration->up();
        $migration->up();

        expect(JenisTagihan::where('lembaga_id', $lembaga->id)->where('kategori', 'spp')->count())->toBe(1);
    });
});

it('skips a lembaga where memungut_iuran is false', function () {
    withLegacyIuranColumns(function () {
        $lembaga = Lembaga::factory()->create();
        DB::table('lembaga')->where('id', $lembaga->id)->update(['memungut_iuran' => false]);

        (require database_path('migrations/2026_08_10_170000_migrate_lembaga_iuran_to_jenis_tagihan.php'))->up();

        expect(JenisTagihan::where('lembaga_id', $lembaga->id)->where('kategori', 'spp')->exists())->toBeFalse();
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=LembagaIuranMigrationTest`
Expected: FAIL — file `2026_08_10_170000_migrate_lembaga_iuran_to_jenis_tagihan.php` doesn't exist yet

- [ ] **Step 3: Write the migration**

```php
<?php
// database/migrations/2026_08_10_170000_migrate_lembaga_iuran_to_jenis_tagihan.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $lembagaList = DB::table('lembaga')->where('memungut_iuran', true)->get();

        foreach ($lembagaList as $lembaga) {
            $sudahAda = DB::table('jenis_tagihan')
                ->where('lembaga_id', $lembaga->id)
                ->where('kategori', 'spp')
                ->exists();

            if ($sudahAda) {
                continue;
            }

            DB::table('jenis_tagihan')->insert([
                'lembaga_id' => $lembaga->id,
                'nama' => $lembaga->periode_iuran === 'tahunan' ? 'SPP Tahunan' : 'SPP Bulanan',
                'kategori' => 'spp',
                'bisa_dicicil' => false,
                'maks_cicilan' => null,
                'priority_score' => null,
                'default_amount' => $lembaga->nominal_iuran,
                'mode' => 'otomatis',
                'tanggal_mulai' => now()->toDateString(),
                'tanggal_selesai' => null,
                'tanggal_generate' => 1,
                'hari_jatuh_tempo' => 10,
                'va_expire_hours' => null,
                'is_active' => true,
                'last_generated_period' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Destructive on rollback: removes every 'spp' jenis_tagihan, not just
     * the ones this migration created. Acceptable for a one-time historical
     * data migration in this dev/demo environment — rolling back this far
     * back in the migration history is not an expected operation once
     * Sub-project 2 (which lets admins create their own 'spp' jenis_tagihan
     * rows through the UI) has shipped.
     */
    public function down(): void
    {
        DB::table('jenis_tagihan')->where('kategori', 'spp')->delete();
    }
};
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=LembagaIuranMigrationTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_08_10_170000_migrate_lembaga_iuran_to_jenis_tagihan.php tests/Feature/Keuangan/LembagaIuranMigrationTest.php
git commit -m "feat(keuangan): migrate lembaga.memungut_iuran data into jenis_tagihan rows"
```

---

### Task 10: Drop `lembaga` iuran columns + clean up model/controller/views/seeder/tests

**Files:**
- Create: `database/migrations/2026_08_10_180000_drop_iuran_columns_from_lembaga_table.php`
- Modify: `app/Models/Lembaga.php:19-47`
- Modify: `app/Http/Controllers/Admin/LembagaController.php:145-201`
- Modify: `resources/views/admin/lembaga/_form.blade.php:290-317`
- Modify: `resources/views/admin/lembaga/tabs/profil.blade.php:158-168`
- Modify: `database/seeders/LembagaSeeder.php`
- Modify: `tests/Feature/Admin/LembagaCrudTest.php:173-275`
- Test: `tests/Feature/Admin/LembagaCrudTest.php` (extends existing file, see Step 6)

**Interfaces:**
- Consumes: nothing new
- Produces: `lembaga` table no longer has `memungut_iuran`/`nominal_iuran`/`periode_iuran`; `Lembaga` model, admin CRUD, seeder, and tests no longer reference them

- [ ] **Step 1: Write the migration**

```php
<?php
// database/migrations/2026_08_10_180000_drop_iuran_columns_from_lembaga_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lembaga', function (Blueprint $table) {
            $table->dropColumn(['memungut_iuran', 'nominal_iuran', 'periode_iuran']);
        });
    }

    public function down(): void
    {
        Schema::table('lembaga', function (Blueprint $table) {
            $table->boolean('memungut_iuran')->default(false);
            $table->decimal('nominal_iuran', 15, 2)->nullable();
            $table->enum('periode_iuran', ['bulanan', 'tahunan'])->nullable();
        });
    }
};
```

- [ ] **Step 2: Add a test asserting the columns are gone**

Add to the top of `tests/Feature/Admin/LembagaCrudTest.php` (after the existing `use` imports at the top of the file):

```php
it('has dropped the legacy iuran columns now that jenis_tagihan covers billing', function () {
    expect(\Illuminate\Support\Facades\Schema::hasColumn('lembaga', 'memungut_iuran'))->toBeFalse();
    expect(\Illuminate\Support\Facades\Schema::hasColumn('lembaga', 'nominal_iuran'))->toBeFalse();
    expect(\Illuminate\Support\Facades\Schema::hasColumn('lembaga', 'periode_iuran'))->toBeFalse();
});
```

- [ ] **Step 3: Run the new assertion test to verify it fails**

Run: `php artisan test --filter="has dropped the legacy iuran columns"`
Expected: FAIL — the columns still exist (migration not run yet)

- [ ] **Step 4: Run the migration**

Run: `php artisan migrate`
Expected: migration `2026_08_10_180000_drop_iuran_columns_from_lembaga_table` runs successfully

- [ ] **Step 5: Update `app/Models/Lembaga.php`**

Change the `$fillable` array (currently line 19-30) from:

```php
    protected $fillable = [
        'yayasan_id', 'npsn', 'nss', 'nama', 'slug', 'kode_lembaga', 'bentuk_pendidikan', 'status_sekolah',
        'status_kepemilikan', 'naungan', 'sk_pendirian_nomor', 'sk_pendirian_tanggal',
        'sk_izin_operasional_nomor', 'sk_izin_operasional_tanggal', 'akreditasi',
        'sk_akreditasi_nomor', 'tanggal_sk_akreditasi', 'nama_kepala_sekolah', 'nama_bendahara_bosp',
        'alamat_jalan', 'rt', 'rw', 'nama_dusun', 'desa_kelurahan', 'kecamatan',
        'kabupaten_kota', 'provinsi', 'kode_pos', 'lintang', 'bujur',
        'telepon', 'fax', 'email', 'website',
        'nama_bank', 'cabang_kcp_unit', 'rekening_atas_nama', 'nomor_rekening',
        'mbs', 'nama_wajib_pajak', 'npwp', 'memungut_iuran', 'nominal_iuran', 'periode_iuran',
        'status_aktif', 'hari_libur_mingguan',
    ];
```

to:

```php
    protected $fillable = [
        'yayasan_id', 'npsn', 'nss', 'nama', 'slug', 'kode_lembaga', 'bentuk_pendidikan', 'status_sekolah',
        'status_kepemilikan', 'naungan', 'sk_pendirian_nomor', 'sk_pendirian_tanggal',
        'sk_izin_operasional_nomor', 'sk_izin_operasional_tanggal', 'akreditasi',
        'sk_akreditasi_nomor', 'tanggal_sk_akreditasi', 'nama_kepala_sekolah', 'nama_bendahara_bosp',
        'alamat_jalan', 'rt', 'rw', 'nama_dusun', 'desa_kelurahan', 'kecamatan',
        'kabupaten_kota', 'provinsi', 'kode_pos', 'lintang', 'bujur',
        'telepon', 'fax', 'email', 'website',
        'nama_bank', 'cabang_kcp_unit', 'rekening_atas_nama', 'nomor_rekening',
        'mbs', 'nama_wajib_pajak', 'npwp',
        'status_aktif', 'hari_libur_mingguan',
    ];
```

Change the `casts()` method (currently line 32-48) from:

```php
    protected function casts(): array
    {
        return [
            'sk_pendirian_tanggal' => 'date',
            'sk_izin_operasional_tanggal' => 'date',
            'tanggal_sk_akreditasi' => 'date',
            'lintang' => 'decimal:7',
            'bujur' => 'decimal:7',
            'mbs' => 'boolean',
            'memungut_iuran' => 'boolean',
            'nominal_iuran' => 'decimal:2',
            'status_aktif' => 'boolean',
            'nomor_rekening' => 'encrypted',
            'npwp' => 'encrypted',
            'hari_libur_mingguan' => 'array',
        ];
    }
```

to:

```php
    protected function casts(): array
    {
        return [
            'sk_pendirian_tanggal' => 'date',
            'sk_izin_operasional_tanggal' => 'date',
            'tanggal_sk_akreditasi' => 'date',
            'lintang' => 'decimal:7',
            'bujur' => 'decimal:7',
            'mbs' => 'boolean',
            'status_aktif' => 'boolean',
            'nomor_rekening' => 'encrypted',
            'npwp' => 'encrypted',
            'hari_libur_mingguan' => 'array',
        ];
    }
```

- [ ] **Step 6: Update `app/Http/Controllers/Admin/LembagaController.php`**

In the `booleans()` method (currently line 145-155), remove the `memungut_iuran` line:

```php
    private function booleans(Request $request, bool $isCreate): array
    {
        return [
            'mbs' => $request->boolean('mbs'),
            // On create, a request that never sent status_aktif at all (e.g. a raw API
            // call that predates this form) should still land active, matching the
            // migration's own default(true) — only an edit's unchecked box means "off".
            'status_aktif' => $isCreate && ! $request->has('status_aktif') ? true : $request->boolean('status_aktif'),
        ];
    }
```

In the `validated()` method (currently line 157-206), remove these two lines just before the closing `]);`:

```php
            'nominal_iuran' => ['nullable', 'numeric', 'min:0'],
            'periode_iuran' => ['nullable', 'in:bulanan,tahunan'],
```

so the validation array ends with `'npwp' => ['nullable', 'string'],` followed directly by `]);`.

- [ ] **Step 7: Update `resources/views/admin/lembaga/_form.blade.php`**

Remove lines 296-316 (the `memungut_iuran` checkbox, the spacer `<div></div>`, the `nominal_iuran` field, and the `periode_iuran` field), keeping the `mbs` checkbox block (lines 290-295) and the closing `</div></div>` (currently lines 317-319) as-is. The block to delete is:

```blade
            <div class="flex items-center pt-1">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="memungut_iuran" value="1" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500" @checked($val('memungut_iuran'))>
                    Memungut iuran dari wali murid
                </label>
            </div>
            <div></div>

            <div>
                <x-input-label value="Nominal Iuran" />
                <x-text-input type="number" step="0.01" name="nominal_iuran" value="{{ $val('nominal_iuran') }}" placeholder="Contoh: 150000" class="mt-1.5" />
                <x-input-error :messages="$errors->get('nominal_iuran')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Periode Iuran" />
                <select name="periode_iuran" class="mt-1.5 {{ $selectClass }}">
                    <option value="">Tidak berlaku</option>
                    <option value="bulanan" @selected($val('periode_iuran') === 'bulanan')>Bulanan</option>
                    <option value="tahunan" @selected($val('periode_iuran') === 'tahunan')>Tahunan</option>
                </select>
            </div>
```

- [ ] **Step 8: Update `resources/views/admin/lembaga/tabs/profil.blade.php`**

Remove lines 158-168 (the "Iuran Wali Murid" `dt`/`dd` block):

```blade
                        <div class="flex justify-between items-center py-2">
                            <dt class="text-sm font-medium text-gray-500">Iuran Wali Murid</dt>
                            <dd class="text-right">
                                @if($lembaga->memungut_iuran)
                                    <span class="block font-bold text-brand-600">Rp {{ number_format($lembaga->nominal_iuran, 0, ',', '.') }}</span>
                                    <span class="text-xs capitalize text-gray-500">Periode: {{ $lembaga->periode_iuran ?: 'bulanan' }}</span>
                                @else
                                    <span class="text-xs font-medium text-gray-400">Tidak Ada Iuran</span>
                                @endif
                            </dd>
                        </div>
```

- [ ] **Step 9: Update `database/seeders/LembagaSeeder.php`**

Remove these 3 lines from **each of the 4** `Lembaga::firstOrCreate(...)` blocks (KBIT, TKIT, SDIT, SMPIT):

```php
                'memungut_iuran' => true,
                'nominal_iuran' => 200000,
                'periode_iuran' => 'bulanan',
```

(the nominal differs per block — 200000, 250000, 350000, 450000 — remove the matching 3-line group from each, leaving `'status_aktif' => true,` as the line directly after `'npwp' => '...',` in every block)

- [ ] **Step 10: Update `tests/Feature/Admin/LembagaCrudTest.php`**

In the test `it('turns status_aktif, mbs, and memungut_iuran off when their checkboxes are left unchecked on update', ...)`, rename it and remove the `memungut_iuran` references. Change:

```php
it('turns status_aktif, mbs, and memungut_iuran off when their checkboxes are left unchecked on update', function () {
    foreach (['lembaga.view', 'lembaga.create', 'lembaga.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['lembaga.view', 'lembaga.create', 'lembaga.edit']);
    $manager = User::factory()->create();
    $manager->assignRole($role);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create([
        'yayasan_id' => $yayasan->id,
        'status_aktif' => true,
        'mbs' => true,
        'memungut_iuran' => true,
    ]);

    $this->actingAs($manager)->put(route('admin.lembaga.update', $lembaga), [
        'yayasan_id' => $yayasan->id,
        'npsn' => $lembaga->npsn,
        'kode_lembaga' => $lembaga->kode_lembaga,
        'nama' => $lembaga->nama,
        'bentuk_pendidikan' => $lembaga->bentuk_pendidikan,
        'status_sekolah' => $lembaga->status_sekolah,
        'naungan' => $lembaga->naungan,
        // all three checkboxes omitted, simulating the user unchecking them
    ])->assertRedirect(route('admin.lembaga.index'));

    $fresh = $lembaga->fresh();
    expect($fresh->status_aktif)->toBeFalse();
    expect($fresh->mbs)->toBeFalse();
    expect($fresh->memungut_iuran)->toBeFalse();
});
```

to:

```php
it('turns status_aktif and mbs off when their checkboxes are left unchecked on update', function () {
    foreach (['lembaga.view', 'lembaga.create', 'lembaga.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['lembaga.view', 'lembaga.create', 'lembaga.edit']);
    $manager = User::factory()->create();
    $manager->assignRole($role);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create([
        'yayasan_id' => $yayasan->id,
        'status_aktif' => true,
        'mbs' => true,
    ]);

    $this->actingAs($manager)->put(route('admin.lembaga.update', $lembaga), [
        'yayasan_id' => $yayasan->id,
        'npsn' => $lembaga->npsn,
        'kode_lembaga' => $lembaga->kode_lembaga,
        'nama' => $lembaga->nama,
        'bentuk_pendidikan' => $lembaga->bentuk_pendidikan,
        'status_sekolah' => $lembaga->status_sekolah,
        'naungan' => $lembaga->naungan,
        // both checkboxes omitted, simulating the user unchecking them
    ])->assertRedirect(route('admin.lembaga.index'));

    $fresh = $lembaga->fresh();
    expect($fresh->status_aktif)->toBeFalse();
    expect($fresh->mbs)->toBeFalse();
});
```

In the test `it('persists the extended profile fields (alamat, kontak, bank) on create', ...)`, change:

```php
        'kecamatan' => 'Lowokwaru',
        'kabupaten_kota' => 'Malang',
        'provinsi' => 'Jawa Timur',
        'telepon' => '0341-123456',
        'nama_bank' => 'Bank Jatim',
        'nominal_iuran' => '150000',
        'periode_iuran' => 'bulanan',
    ])->assertRedirect(route('admin.lembaga.index'));

    $created = Lembaga::where('npsn', '20301237')->first();
    expect($created->kecamatan)->toBe('Lowokwaru');
    expect($created->kabupaten_kota)->toBe('Malang');
    expect($created->provinsi)->toBe('Jawa Timur');
    expect($created->nama_bank)->toBe('Bank Jatim');
    expect((float) $created->nominal_iuran)->toBe(150000.0);
    expect($created->periode_iuran)->toBe('bulanan');
});
```

to:

```php
        'kecamatan' => 'Lowokwaru',
        'kabupaten_kota' => 'Malang',
        'provinsi' => 'Jawa Timur',
        'telepon' => '0341-123456',
        'nama_bank' => 'Bank Jatim',
    ])->assertRedirect(route('admin.lembaga.index'));

    $created = Lembaga::where('npsn', '20301237')->first();
    expect($created->kecamatan)->toBe('Lowokwaru');
    expect($created->kabupaten_kota)->toBe('Malang');
    expect($created->provinsi)->toBe('Jawa Timur');
    expect($created->nama_bank)->toBe('Bank Jatim');
});
```

- [ ] **Step 11: Run the full Lembaga test file to verify everything passes**

Run: `php artisan test tests/Feature/Admin/LembagaCrudTest.php`
Expected: PASS, every test including the new "has dropped the legacy iuran columns" one

- [ ] **Step 12: Re-seed and verify the seeder still runs cleanly**

Run: `php artisan migrate:fresh --seed --seeder=Database\\Seeders\\LembagaSeeder` (or run the full `DatabaseSeeder` if that's how this project seeds — check `database/seeders/DatabaseSeeder.php` if the direct seeder class call errors)
Expected: seeder completes without error, 4 lembaga rows created

- [ ] **Step 13: Commit**

```bash
git add database/migrations/2026_08_10_180000_drop_iuran_columns_from_lembaga_table.php app/Models/Lembaga.php app/Http/Controllers/Admin/LembagaController.php resources/views/admin/lembaga/_form.blade.php resources/views/admin/lembaga/tabs/profil.blade.php database/seeders/LembagaSeeder.php tests/Feature/Admin/LembagaCrudTest.php
git commit -m "feat(keuangan): drop legacy lembaga iuran columns now superseded by jenis_tagihan"
```

---

### Task 11: Full regression suite verification

**Files:** none (verification only)

**Interfaces:** none

- [ ] **Step 1: Run the entire test suite**

Run: `php artisan test`
Expected: PASS — every test in the suite green, including all new `tests/Feature/Keuangan/*` files and every pre-existing PPDB/Lembaga test

- [ ] **Step 2: If anything fails, fix forward**

If a pre-existing test fails, it means an assumption made in an earlier task about "this code doesn't need to change" was wrong — find the exact failing assertion, trace it back to the schema/model change that broke it, and fix the production code (not the test, unless the test was asserting old, now-intentionally-changed behavior like the two `LembagaCrudTest` edits in Task 10). Re-run `php artisan test` until fully green.

- [ ] **Step 3: Migrate the real dev database**

Run: `php artisan migrate` (against the actual Laragon/MySQL dev DB, not just the test DB — per this project's convention, tests passing doesn't mean the dev DB is up to date)
Expected: all 10 new migrations from this plan apply cleanly against the real seeded dev data (4 lembaga, including their `memungut_iuran` rows) — confirm afterward with `php artisan tinker --execute="echo App\Models\JenisTagihan::where('kategori','spp')->count();"` and expect `4`

- [ ] **Step 4: Final commit (only if Step 2 required fixes)**

```bash
git add -A
git commit -m "fix(keuangan): address regressions found during full suite verification"
```
