# Keuangan — Master Tagihan & Mesin Invoicing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a lembaga-scoped, category-tagged master catalog of billable items (`jenis_tagihan`), per-jalur nominal configuration, and an idempotent invoicing engine that auto-generates `tagihan`/`tagihan_item` rows from two SPMB events (M2 submit, M3 acceptance) — without ever fabricating a false "lunas" for an unconfigured fee.

**Architecture:** A small, generic data model (`jenis_tagihan`, `nominal_tagihan_jalur`, `tagihan`, `tagihan_item`) reusable beyond SPMB, driven by a single `TagihanGenerator` service consumed from three call sites: an additive hook in M2's `ReviewSubmitController::submit()`, an additive hook in M3's `PendaftaranAdminController::tetapkanKeputusan()`, and a manual "Buat Tagihan Susulan" admin action reusing the exact same service method (idempotent by construction, not by a separate check).

**Tech Stack:** Laravel 12, Blade, Alpine.js, Pest PHP.

## Global Constraints

- `jenis_tagihan` uses `BelongsToTenant` (same pattern as `JalurPpdb`/`GelombangPpdb` — pure admin-authenticated master data, never touched by M2's public routes).
- `tagihan`/`tagihan_item` do NOT use `BelongsToTenant` and have no `lembaga_id` column of their own — tenant identity is always derived transitively via `tagihan.pendaftaran_id → pendaftaran.lembaga_id`, exactly like `DokumenPendaftaran`/`HasilSeleksi` already do. `TagihanGenerator` itself never needs `auth()`/session context — it takes an explicit `Pendaftaran` and `kategori` and derives everything from those.
- `TagihanGenerator::generate(Pendaftaran $pendaftaran, string $kategori): ?Tagihan` is THE single source of truth for invoice creation — all three call sites (M2 hook, M3 hook, manual susulan) call this exact method, never duplicate its logic. It is idempotent: if a `tagihan` already exists for `(pendaftaran_id, kategori)`, it returns `null` and creates nothing.
- The generator NEVER creates a `tagihan` header with zero qualifying line items — if no `jenis_tagihan` of the requested `kategori` has a configured `nominal_tagihan_jalur` for this pendaftaran's `jalur_ppdb_id`, it creates nothing and returns `null`. A `tagihan` is only ever `status='lunas'` when its *actually-created* items sum to exactly 0 — never as a side effect of missing configuration.
- Every controller action calls `$this->authorize('modul.aksi')` independently — no shared/umbrella check.
- `PendaftaranAdminController`'s existing `lembagaId(Request $request): ?int` private helper pattern (yayasan-scoped → `session('active_lembaga_id')`, lembaga-scoped → `$request->user()->lembaga_id`) is duplicated verbatim into the new `TagihanController` — this project's established convention is per-controller duplication of this helper, not a shared trait (confirmed by `SkPpdbController` already doing the same).
- Neither `ReviewSubmitController::submit()` nor `PendaftaranAdminController::tetapkanKeputusan()` has any existing line changed — both hooks are purely additive blocks.
- PHP is not on PATH — use `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe` for all `artisan`/test commands.

---

### Task 1: Data Layer — Models, Migrations, Factories

**Files:**
- Create: `database/migrations/2026_07_15_120000_create_jenis_tagihan_table.php`
- Create: `database/migrations/2026_07_15_120100_create_nominal_tagihan_jalur_table.php`
- Create: `database/migrations/2026_07_15_120200_create_tagihan_table.php`
- Create: `database/migrations/2026_07_15_120300_create_tagihan_item_table.php`
- Create: `app/Models/JenisTagihan.php`
- Create: `app/Models/NominalTagihanJalur.php`
- Create: `app/Models/Tagihan.php`
- Create: `app/Models/TagihanItem.php`
- Create: `database/factories/JenisTagihanFactory.php`
- Create: `database/factories/NominalTagihanJalurFactory.php`
- Create: `database/factories/TagihanFactory.php`
- Create: `database/factories/TagihanItemFactory.php`
- Modify: `app/Models/Pendaftaran.php`
- Test: `tests/Unit/KeuanganDataLayerTest.php`

**Interfaces:**
- Produces: `JenisTagihan` (fillable `lembaga_id,nama,kategori,bisa_dicicil,maks_cicilan`; `BelongsToTenant`), `NominalTagihanJalur` (fillable `jenis_tagihan_id,jalur_ppdb_id,nominal`), `Tagihan` (fillable `pendaftaran_id,kategori,total_tagihan,status,jatuh_tempo`), `TagihanItem` (fillable `tagihan_id,jenis_tagihan_id,jumlah`).
- Produces: `Pendaftaran::tagihan(): HasMany`.
- Produces: `JenisTagihan::nominalJalur(): HasMany`, `Tagihan::item(): HasMany`, `Tagihan::pendaftaran(): BelongsTo`, `TagihanItem::tagihan(): BelongsTo`, `TagihanItem::jenisTagihan(): BelongsTo`, `NominalTagihanJalur::jenisTagihan(): BelongsTo`, `NominalTagihanJalur::jalurPpdb(): BelongsTo`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/KeuanganDataLayerTest.php

use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\NominalTagihanJalur;
use App\Models\Pendaftaran;
use App\Models\Tagihan;
use App\Models\TagihanItem;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates a jenis_tagihan scoped to the acting lembaga-scoped user', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user);
    $jenisTagihan = JenisTagihan::create([
        'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false, 'maks_cicilan' => null,
    ]);

    expect($jenisTagihan->fresh()->lembaga_id)->toBe($lembaga->id);

    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $userLain = User::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $this->actingAs($userLain);
    expect(JenisTagihan::find($jenisTagihan->id))->toBeNull();
});

it('exposes nominal_tagihan_jalur enforcing a unique jenis_tagihan+jalur pair', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    $jenisTagihan = JenisTagihan::create([
        'lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false,
    ]);

    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 150000]);

    expect(fn () => NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 200000]))
        ->toThrow(\Illuminate\Database\QueryException::class);

    expect($jenisTagihan->nominalJalur()->count())->toBe(1);
});

it('links a tagihan and its items back to the pendaftaran that owns them', function () {
    $lembaga = Lembaga::factory()->create();
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::create([
        'lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false,
    ]);

    $tagihan = Tagihan::create([
        'pendaftaran_id' => $pendaftaran->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 150000, 'status' => 'belum_bayar',
    ]);
    TagihanItem::create(['tagihan_id' => $tagihan->id, 'jenis_tagihan_id' => $jenisTagihan->id, 'jumlah' => 150000]);

    expect($pendaftaran->fresh()->tagihan)->toHaveCount(1);
    expect($tagihan->fresh()->item)->toHaveCount(1);
    expect($tagihan->fresh()->item->first()->jenisTagihan->nama)->toBe('Biaya Pendaftaran');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/KeuanganDataLayerTest.php`
Expected: FAIL — classes/tables don't exist yet.

- [ ] **Step 3: Write the migrations**

```php
<?php
// database/migrations/2026_07_15_120000_create_jenis_tagihan_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_tagihan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->string('nama');
            $table->enum('kategori', ['pendaftaran', 'daftar_ulang', 'lainnya']);
            $table->boolean('bisa_dicicil')->default(false);
            $table->unsignedInteger('maks_cicilan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jenis_tagihan');
    }
};
```

```php
<?php
// database/migrations/2026_07_15_120100_create_nominal_tagihan_jalur_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nominal_tagihan_jalur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_tagihan_id')->constrained('jenis_tagihan')->cascadeOnDelete();
            $table->foreignId('jalur_ppdb_id')->constrained('jalur_ppdb')->cascadeOnDelete();
            $table->decimal('nominal', 12, 2);
            $table->timestamps();

            $table->unique(['jenis_tagihan_id', 'jalur_ppdb_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nominal_tagihan_jalur');
    }
};
```

```php
<?php
// database/migrations/2026_07_15_120200_create_tagihan_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagihan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_id')->constrained('pendaftaran')->cascadeOnDelete();
            $table->enum('kategori', ['pendaftaran', 'daftar_ulang']);
            $table->decimal('total_tagihan', 12, 2);
            $table->enum('status', ['belum_bayar', 'dicicil', 'lunas'])->default('belum_bayar');
            $table->date('jatuh_tempo')->nullable();
            $table->timestamps();

            $table->unique(['pendaftaran_id', 'kategori']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagihan');
    }
};
```

```php
<?php
// database/migrations/2026_07_15_120300_create_tagihan_item_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagihan_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tagihan_id')->constrained('tagihan')->cascadeOnDelete();
            $table->foreignId('jenis_tagihan_id')->constrained('jenis_tagihan')->cascadeOnDelete();
            $table->decimal('jumlah', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagihan_item');
    }
};
```

Note the `unique(['pendaftaran_id', 'kategori'])` on `tagihan` — this is a DB-level backstop for the generator's idempotency, in addition to the application-level existence check in Task 3.

- [ ] **Step 4: Write the models**

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

    protected $fillable = ['lembaga_id', 'nama', 'kategori', 'bisa_dicicil', 'maks_cicilan'];

    protected function casts(): array
    {
        return [
            'bisa_dicicil' => 'boolean',
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
}
```

```php
<?php
// app/Models/NominalTagihanJalur.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NominalTagihanJalur extends Model
{
    use HasFactory;

    protected $table = 'nominal_tagihan_jalur';

    protected $fillable = ['jenis_tagihan_id', 'jalur_ppdb_id', 'nominal'];

    public function jenisTagihan(): BelongsTo
    {
        return $this->belongsTo(JenisTagihan::class);
    }

    public function jalurPpdb(): BelongsTo
    {
        return $this->belongsTo(JalurPpdb::class);
    }
}
```

```php
<?php
// app/Models/Tagihan.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Tagihan extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'tagihan';

    protected $fillable = ['pendaftaran_id', 'kategori', 'total_tagihan', 'status', 'jatuh_tempo'];

    protected function casts(): array
    {
        return [
            'jatuh_tempo' => 'date',
        ];
    }

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(Pendaftaran::class);
    }

    public function item(): HasMany
    {
        return $this->hasMany(TagihanItem::class);
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

```php
<?php
// app/Models/TagihanItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TagihanItem extends Model
{
    use HasFactory;

    protected $table = 'tagihan_item';

    protected $fillable = ['tagihan_id', 'jenis_tagihan_id', 'jumlah'];

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(Tagihan::class);
    }

    public function jenisTagihan(): BelongsTo
    {
        return $this->belongsTo(JenisTagihan::class);
    }
}
```

- [ ] **Step 5: Add the relation to `Pendaftaran`**

In `app/Models/Pendaftaran.php`, add `use Illuminate\Database\Eloquent\Relations\HasMany;` if not already imported (it already is, per `hasilSeleksi()`/`dokumen()`), then add:

```php
    public function tagihan(): HasMany
    {
        return $this->hasMany(Tagihan::class);
    }
```

- [ ] **Step 6: Write the factories**

```php
<?php
// database/factories/JenisTagihanFactory.php

namespace Database\Factories;

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JenisTagihan> */
class JenisTagihanFactory extends Factory
{
    protected $model = JenisTagihan::class;

    public function definition(): array
    {
        return [
            'lembaga_id' => Lembaga::factory(),
            'nama' => 'Biaya Pendaftaran',
            'kategori' => 'pendaftaran',
            'bisa_dicicil' => false,
            'maks_cicilan' => null,
        ];
    }
}
```

```php
<?php
// database/factories/NominalTagihanJalurFactory.php

namespace Database\Factories;

use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\NominalTagihanJalur;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NominalTagihanJalur> */
class NominalTagihanJalurFactory extends Factory
{
    protected $model = NominalTagihanJalur::class;

    public function definition(): array
    {
        return [
            'jenis_tagihan_id' => JenisTagihan::factory(),
            'jalur_ppdb_id' => JalurPpdb::factory(),
            'nominal' => 150000,
        ];
    }
}
```

```php
<?php
// database/factories/TagihanFactory.php

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
            'status' => 'belum_bayar',
        ];
    }
}
```

```php
<?php
// database/factories/TagihanItemFactory.php

namespace Database\Factories;

use App\Models\JenisTagihan;
use App\Models\Tagihan;
use App\Models\TagihanItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TagihanItem> */
class TagihanItemFactory extends Factory
{
    protected $model = TagihanItem::class;

    public function definition(): array
    {
        return [
            'tagihan_id' => Tagihan::factory(),
            'jenis_tagihan_id' => JenisTagihan::factory(),
            'jumlah' => 150000,
        ];
    }
}
```

- [ ] **Step 7: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/KeuanganDataLayerTest.php`
Expected: PASS (3/3)

- [ ] **Step 8: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all pre-existing tests still pass.

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_07_15_120000_create_jenis_tagihan_table.php \
        database/migrations/2026_07_15_120100_create_nominal_tagihan_jalur_table.php \
        database/migrations/2026_07_15_120200_create_tagihan_table.php \
        database/migrations/2026_07_15_120300_create_tagihan_item_table.php \
        app/Models/JenisTagihan.php app/Models/NominalTagihanJalur.php app/Models/Tagihan.php app/Models/TagihanItem.php \
        database/factories/JenisTagihanFactory.php database/factories/NominalTagihanJalurFactory.php \
        database/factories/TagihanFactory.php database/factories/TagihanItemFactory.php \
        app/Models/Pendaftaran.php tests/Unit/KeuanganDataLayerTest.php
git commit -m "feat: add keuangan data layer (jenis_tagihan, nominal per jalur, tagihan, tagihan_item)"
```

---

### Task 2: Permission & Master Jenis Tagihan Admin UI

**Files:**
- Modify: `app/Services/PermissionCatalog.php`
- Modify: `database/seeders/RolePermissionSeeder.php`
- Create: `app/Http/Controllers/Admin/JenisTagihanController.php`
- Create: `resources/views/admin/jenis-tagihan/index.blade.php`
- Create: `resources/views/admin/jenis-tagihan/create.blade.php`
- Create: `resources/views/admin/jenis-tagihan/edit.blade.php`
- Create: `resources/views/admin/jenis-tagihan/nominal.blade.php`
- Modify: `routes/admin.php`
- Modify: `resources/views/layouts/sidebar.blade.php`
- Test: `tests/Feature/Admin/JenisTagihanTest.php`
- Test: `tests/Feature/RolePermissionSeederTest.php`

**Interfaces:**
- Consumes: `JenisTagihan`/`NominalTagihanJalur` (Task 1), the established `JalurPpdbController` create/store yayasan-guard pattern (`widestScopeLevel() === 'yayasan' && session('active_lembaga_id') === null`).
- Produces: permissions `jenis-tagihan.view/.create/.edit/.delete`, `tagihan.view`, `tagihan.buat-susulan` — Task 4/5 controllers consume the latter two.
- Produces: routes `admin.jenis-tagihan.index/.create/.store/.edit/.update/.destroy/.nominal/.nominal.store`.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Admin/JenisTagihanTest.php

use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\NominalTagihanJalur;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

function buatLembagaDenganJalurUntukTagihan(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);

    return [$lembaga, $tahunAjaran, $jalur];
}

it('denies jenis tagihan management without permission', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->get(route('admin.jenis-tagihan.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.jenis-tagihan.store'), [])->assertForbidden();
});

it('lets admin_keuangan create a jenis tagihan scoped to their own lembaga', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->post(route('admin.jenis-tagihan.store'), [
        'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false,
    ]);

    $response->assertRedirect();
    $jenisTagihan = JenisTagihan::where('nama', 'Biaya Pendaftaran')->first();
    expect($jenisTagihan)->not->toBeNull();
    expect($jenisTagihan->lembaga_id)->toBe($lembaga->id);
});

it('lets admin_keuangan set nominal per jalur, rejecting a duplicate pair at the db level', function () {
    [$lembaga, , $jalur] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);

    $response = $this->actingAs($user)->post(route('admin.jenis-tagihan.nominal.store', $jenisTagihan), [
        'nominal' => [$jalur->id => 150000],
    ]);

    $response->assertRedirect();
    expect(NominalTagihanJalur::where('jenis_tagihan_id', $jenisTagihan->id)->where('jalur_ppdb_id', $jalur->id)->first()->nominal)->toEqual(150000);

    // Re-saving with a different value updates in place (updateOrCreate), never duplicates the pair.
    $this->actingAs($user)->post(route('admin.jenis-tagihan.nominal.store', $jenisTagihan), ['nominal' => [$jalur->id => 200000]]);
    expect(NominalTagihanJalur::where('jenis_tagihan_id', $jenisTagihan->id)->where('jalur_ppdb_id', $jalur->id)->count())->toBe(1);
    expect(NominalTagihanJalur::where('jenis_tagihan_id', $jenisTagihan->id)->where('jalur_ppdb_id', $jalur->id)->first()->nominal)->toEqual(200000);
});

it('only lists jenis tagihan belonging to the acting lembaga-scoped user own lembaga', function () {
    [$lembagaA] = buatLembagaDenganJalurUntukTagihan();
    [$lembagaB] = buatLembagaDenganJalurUntukTagihan();
    JenisTagihan::create(['lembaga_id' => $lembagaA->id, 'nama' => 'Punya A', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    JenisTagihan::create(['lembaga_id' => $lembagaB->id, 'nama' => 'Punya B', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    $user = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.index'));

    $response->assertOk()->assertSee('Punya A')->assertDontSee('Punya B');
});

it('denies kepala_sekolah from creating a jenis tagihan (view-only role for this module)', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('kepala_sekolah');

    $this->actingAs($user)->post(route('admin.jenis-tagihan.store'), [
        'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false,
    ])->assertForbidden();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/JenisTagihanTest.php`
Expected: FAIL — permissions/routes/controller don't exist yet.

- [ ] **Step 3: Add permissions to `PermissionCatalog`**

In `app/Services/PermissionCatalog.php`, add to `MODULE_LABELS`:
```php
        'jenis-tagihan' => 'Jenis Tagihan',
        'tagihan' => 'Tagihan',
```
and to `ACTION_LABELS`:
```php
        'buat-susulan' => 'Buat Tagihan Susulan',
```
(`view`/`create`/`edit`/`delete` already exist in `ACTION_LABELS` — no change needed for those.)

- [ ] **Step 4: Add permissions to `RolePermissionSeeder`**

In `database/seeders/RolePermissionSeeder.php`, add to the `$permissions` array:
```php
            'jenis-tagihan.view', 'jenis-tagihan.create', 'jenis-tagihan.edit', 'jenis-tagihan.delete',
            'tagihan.view', 'tagihan.buat-susulan',
```
Add a new `if ($name === 'admin_keuangan')` block (after the existing `admin_administrasi` block):
```php
            if ($name === 'admin_keuangan') {
                $role->givePermissionTo([
                    'jenis-tagihan.view', 'jenis-tagihan.create', 'jenis-tagihan.edit', 'jenis-tagihan.delete',
                    'tagihan.view', 'tagihan.buat-susulan',
                ]);
            }
```
Extend the existing `if ($name === 'kepala_sekolah')` block to also grant `tagihan.view`:
```php
            if ($name === 'kepala_sekolah') {
                $role->givePermissionTo([
                    'spmb-pendaftaran.view', 'spmb-pendaftaran.verifikasi-dokumen', 'spmb-pendaftaran.nilai-seleksi',
                    'spmb-pendaftaran.tetapkan-keputusan', 'spmb-pendaftaran.terbitkan-sk',
                    'tagihan.view',
                ]);
            }
```

Update `tests/Feature/RolePermissionSeederTest.php`'s total-permission-count assertion to account for the 6 new permissions (find the existing count assertion — e.g. `expect(Permission::count())->toBe(N)` — and increase `N` by 6), and admin_keuangan's permission-count assertion if one exists (should now be 6, was 0).

- [ ] **Step 5: Write `JenisTagihanController`**

```php
<?php
// app/Http/Controllers/Admin/JenisTagihanController.php

namespace App\Http\Controllers\Admin;

use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\NominalTagihanJalur;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class JenisTagihanController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('jenis-tagihan.view');

        return view('admin.jenis-tagihan.index', [
            'jenisTagihanList' => JenisTagihan::orderBy('nama')->get(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $this->authorize('jenis-tagihan.create');

        if ($request->user()->widestScopeLevel() === 'yayasan' && session('active_lembaga_id') === null) {
            return redirect()->route('admin.jenis-tagihan.index')
                ->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah jenis tagihan.']);
        }

        return view('admin.jenis-tagihan.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('jenis-tagihan.create');

        if ($request->user()->widestScopeLevel() === 'yayasan' && session('active_lembaga_id') === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah jenis tagihan.'])->withInput();
        }

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'kategori' => ['required', 'in:pendaftaran,daftar_ulang,lainnya'],
            'bisa_dicicil' => ['nullable', 'boolean'],
            'maks_cicilan' => ['nullable', 'integer', 'min:2', 'required_if:bisa_dicicil,1'],
        ]);
        $data['bisa_dicicil'] = $request->boolean('bisa_dicicil');

        $jenisTagihan = JenisTagihan::create($data);

        return redirect()->route('admin.jenis-tagihan.nominal', $jenisTagihan)
            ->with('status', 'Jenis tagihan berhasil ditambahkan. Atur nominal per jalur di bawah.');
    }

    public function edit(JenisTagihan $jenisTagihan): View
    {
        $this->authorize('jenis-tagihan.edit');

        return view('admin.jenis-tagihan.edit', ['jenisTagihan' => $jenisTagihan]);
    }

    public function update(Request $request, JenisTagihan $jenisTagihan): RedirectResponse
    {
        $this->authorize('jenis-tagihan.edit');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'kategori' => ['required', 'in:pendaftaran,daftar_ulang,lainnya'],
            'bisa_dicicil' => ['nullable', 'boolean'],
            'maks_cicilan' => ['nullable', 'integer', 'min:2', 'required_if:bisa_dicicil,1'],
        ]);
        $data['bisa_dicicil'] = $request->boolean('bisa_dicicil');

        $jenisTagihan->update($data);

        return redirect()->route('admin.jenis-tagihan.edit', $jenisTagihan)->with('status', 'Jenis tagihan berhasil diperbarui.');
    }

    public function destroy(JenisTagihan $jenisTagihan): RedirectResponse
    {
        $this->authorize('jenis-tagihan.delete');

        if ($jenisTagihan->nominalJalur()->exists()) {
            return back()->withErrors(['jenis_tagihan' => 'Tidak bisa menghapus jenis tagihan yang sudah punya nominal terkonfigurasi.']);
        }

        $jenisTagihan->delete();

        return redirect()->route('admin.jenis-tagihan.index')->with('status', 'Jenis tagihan berhasil dihapus.');
    }

    public function nominal(JenisTagihan $jenisTagihan): View
    {
        $this->authorize('jenis-tagihan.edit');

        $tahunAjaranAktif = TahunAjaran::where('lembaga_id', $jenisTagihan->lembaga_id)->where('status_aktif', true)->first();

        return view('admin.jenis-tagihan.nominal', [
            'jenisTagihan' => $jenisTagihan,
            'jalurList' => $tahunAjaranAktif
                ? JalurPpdb::where('tahun_ajaran_id', $tahunAjaranAktif->id)->orderBy('nama')->get()
                : collect(),
            'nominalMap' => NominalTagihanJalur::where('jenis_tagihan_id', $jenisTagihan->id)->pluck('nominal', 'jalur_ppdb_id'),
            'tahunAjaranAktif' => $tahunAjaranAktif,
        ]);
    }

    public function simpanNominal(Request $request, JenisTagihan $jenisTagihan): RedirectResponse
    {
        $this->authorize('jenis-tagihan.edit');

        $data = $request->validate([
            'nominal' => ['required', 'array'],
            'nominal.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $jalurIds = JalurPpdb::where('lembaga_id', $jenisTagihan->lembaga_id)->pluck('id');

        foreach ($data['nominal'] as $jalurPpdbId => $nominal) {
            if (! $jalurIds->contains((int) $jalurPpdbId) || $nominal === null || $nominal === '') {
                continue;
            }

            NominalTagihanJalur::updateOrCreate(
                ['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalurPpdbId],
                ['nominal' => $nominal]
            );
        }

        return redirect()->route('admin.jenis-tagihan.nominal', $jenisTagihan)->with('status', 'Nominal berhasil disimpan.');
    }
}
```

Note: `index()`/`edit()`/`destroy()`/`nominal()`/`simpanNominal()` rely on `JenisTagihan`'s `BelongsToTenant` global scope for tenant isolation (matching `JalurPpdbController`'s own pattern) — no manual `lembaga_id` filtering needed in this controller.

- [ ] **Step 6: Write the views**

```blade
{{-- resources/views/admin/jenis-tagihan/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Keuangan</p>
        <div class="mt-1 flex items-center justify-between">
            <h2 class="font-display text-2xl font-semibold text-ink">Jenis Tagihan</h2>
            @can('jenis-tagihan.create')
                <x-link-button href="{{ route('admin.jenis-tagihan.create') }}">Tambah Jenis Tagihan</x-link-button>
            @endcan
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl">
        <x-panel>
            <ul class="divide-y divide-ink/10">
                @forelse ($jenisTagihanList as $jenisTagihan)
                    <li class="flex items-center justify-between gap-3 px-6 py-4">
                        <div>
                            <p class="font-medium text-ink">{{ $jenisTagihan->nama }}</p>
                            <p class="text-xs text-slate">{{ ucfirst(str_replace('_', ' ', $jenisTagihan->kategori)) }} &middot; {{ $jenisTagihan->bisa_dicicil ? 'Bisa dicicil maks '.$jenisTagihan->maks_cicilan.'x' : 'Tidak bisa dicicil' }}</p>
                        </div>
                        @can('jenis-tagihan.edit')
                            <a href="{{ route('admin.jenis-tagihan.edit', $jenisTagihan) }}" class="text-sm font-semibold text-ink hover:underline">Kelola</a>
                        @endcan
                    </li>
                @empty
                    <li class="px-6 py-8 text-center text-sm text-slate">Belum ada jenis tagihan.</li>
                @endforelse
            </ul>
        </x-panel>
    </div>
</x-app-layout>
```

```blade
{{-- resources/views/admin/jenis-tagihan/create.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Keuangan</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Tambah Jenis Tagihan</h2>
    </x-slot>

    <div class="mx-auto max-w-xl">
        <x-panel class="p-6">
            <form method="POST" action="{{ route('admin.jenis-tagihan.store') }}" class="space-y-4">
                @csrf
                <div>
                    <x-input-label value="Nama" />
                    <x-text-input type="text" name="nama" class="mt-1.5" :value="old('nama')" required autofocus />
                    <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label value="Kategori" />
                    <select name="kategori" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass" required>
                        <option value="pendaftaran">Pendaftaran</option>
                        <option value="daftar_ulang">Daftar Ulang</option>
                        <option value="lainnya">Lainnya</option>
                    </select>
                    <x-input-error :messages="$errors->get('kategori')" class="mt-1.5" />
                </div>
                <div x-data="{ bisaDicicil: false }">
                    <label class="inline-flex items-center text-sm text-ink">
                        <input type="checkbox" name="bisa_dicicil" value="1" x-model="bisaDicicil" class="rounded border-ink/25 text-brass shadow-sm focus:ring-brass">
                        <span class="ms-2">Bisa dicicil</span>
                    </label>
                    <div x-show="bisaDicicil" x-cloak class="mt-3">
                        <x-input-label value="Maksimal Jumlah Cicilan" />
                        <x-text-input type="number" name="maks_cicilan" min="2" class="mt-1.5 w-32" :value="old('maks_cicilan')" />
                        <x-input-error :messages="$errors->get('maks_cicilan')" class="mt-1.5" />
                    </div>
                </div>
                <x-primary-button>Simpan</x-primary-button>
            </form>
        </x-panel>
    </div>
</x-app-layout>
```

```blade
{{-- resources/views/admin/jenis-tagihan/edit.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Keuangan</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">{{ $jenisTagihan->nama }}</h2>
    </x-slot>

    <div class="mx-auto max-w-xl space-y-6">
        <x-panel class="p-6">
            <form method="POST" action="{{ route('admin.jenis-tagihan.update', $jenisTagihan) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <x-input-label value="Nama" />
                    <x-text-input type="text" name="nama" class="mt-1.5" :value="old('nama', $jenisTagihan->nama)" required />
                    <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label value="Kategori" />
                    <select name="kategori" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass" required>
                        @foreach (['pendaftaran' => 'Pendaftaran', 'daftar_ulang' => 'Daftar Ulang', 'lainnya' => 'Lainnya'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('kategori', $jenisTagihan->kategori) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-data="{ bisaDicicil: @js($jenisTagihan->bisa_dicicil) }">
                    <label class="inline-flex items-center text-sm text-ink">
                        <input type="checkbox" name="bisa_dicicil" value="1" x-model="bisaDicicil" class="rounded border-ink/25 text-brass shadow-sm focus:ring-brass">
                        <span class="ms-2">Bisa dicicil</span>
                    </label>
                    <div x-show="bisaDicicil" x-cloak class="mt-3">
                        <x-input-label value="Maksimal Jumlah Cicilan" />
                        <x-text-input type="number" name="maks_cicilan" min="2" class="mt-1.5 w-32" :value="old('maks_cicilan', $jenisTagihan->maks_cicilan)" />
                    </div>
                </div>
                <x-primary-button>Simpan</x-primary-button>
            </form>
        </x-panel>

        <x-panel class="p-6">
            <p class="text-sm text-slate">Atur nominal per jalur untuk jenis tagihan ini.</p>
            <a href="{{ route('admin.jenis-tagihan.nominal', $jenisTagihan) }}" class="mt-2 inline-block text-sm font-semibold text-ink hover:underline">Kelola Nominal &rarr;</a>
        </x-panel>
    </div>
</x-app-layout>
```

```blade
{{-- resources/views/admin/jenis-tagihan/nominal.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Keuangan</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Nominal — {{ $jenisTagihan->nama }}</h2>
    </x-slot>

    <div class="mx-auto max-w-xl">
        @if (! $tahunAjaranAktif)
            <x-panel class="p-6 text-sm text-slate">Tidak ada tahun ajaran aktif untuk lembaga ini.</x-panel>
        @else
            <x-panel class="p-6">
                <form method="POST" action="{{ route('admin.jenis-tagihan.nominal.store', $jenisTagihan) }}" class="space-y-4">
                    @csrf
                    @foreach ($jalurList as $jalur)
                        <div>
                            <x-input-label :value="$jalur->nama" />
                            <x-text-input type="number" step="0.01" min="0" name="nominal[{{ $jalur->id }}]" class="mt-1.5" :value="old('nominal.'.$jalur->id, $nominalMap[$jalur->id] ?? '')" placeholder="0 untuk gratis" />
                        </div>
                    @endforeach
                    <x-primary-button>Simpan Semua Nominal</x-primary-button>
                </form>
            </x-panel>
        @endif
    </div>
</x-app-layout>
```

- [ ] **Step 7: Wire the routes**

In `routes/admin.php`, add near the other admin resource routes:

```php
    Route::get('jenis-tagihan', [JenisTagihanController::class, 'index'])->name('jenis-tagihan.index');
    Route::get('jenis-tagihan/create', [JenisTagihanController::class, 'create'])->name('jenis-tagihan.create');
    Route::post('jenis-tagihan', [JenisTagihanController::class, 'store'])->name('jenis-tagihan.store');
    Route::get('jenis-tagihan/{jenisTagihan}/edit', [JenisTagihanController::class, 'edit'])->name('jenis-tagihan.edit');
    Route::put('jenis-tagihan/{jenisTagihan}', [JenisTagihanController::class, 'update'])->name('jenis-tagihan.update');
    Route::delete('jenis-tagihan/{jenisTagihan}', [JenisTagihanController::class, 'destroy'])->name('jenis-tagihan.destroy');
    Route::get('jenis-tagihan/{jenisTagihan}/nominal', [JenisTagihanController::class, 'nominal'])->name('jenis-tagihan.nominal');
    Route::post('jenis-tagihan/{jenisTagihan}/nominal', [JenisTagihanController::class, 'simpanNominal'])->name('jenis-tagihan.nominal.store');
```

Add `use App\Http\Controllers\Admin\JenisTagihanController;` to the top of the file alongside the other controller imports.

- [ ] **Step 8: Add the sidebar group**

In `resources/views/layouts/sidebar.blade.php`, insert a new group after "III. SPMB" and renumber "Akses & Peran" from "IV." to "V.":

```php
        [
            'label' => 'IV. Keuangan',
            'items' => array_filter([
                Auth::user()->can('jenis-tagihan.view') ? ['route' => 'admin.jenis-tagihan.index', 'pattern' => 'admin.jenis-tagihan.*', 'label' => 'Jenis Tagihan', 'icon' => 'payments'] : null,
                Auth::user()->can('tagihan.view') ? ['route' => 'admin.tagihan.index', 'pattern' => 'admin.tagihan.*', 'label' => 'Tagihan', 'icon' => 'receipt_long'] : null,
            ]),
        ],
```
(insert this array entry between the existing "III. SPMB" and "IV. Akses & Peran" entries in the `$navGroups` array; change the latter's `'label'` string from `'IV. Akses & Peran'` to `'V. Akses & Peran'`.)

Note: `admin.tagihan.index` is created in Task 5 — this sidebar link will 404 until then, which is acceptable since the permission `tagihan.view` gate means it's invisible to everyone during Tasks 2-4 unless a test explicitly grants it and visits the URL (no test in this task does).

- [ ] **Step 9: Run tests to verify they pass**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/JenisTagihanTest.php tests/Feature/RolePermissionSeederTest.php`
Expected: PASS

- [ ] **Step 10: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all tests pass.

- [ ] **Step 11: Commit**

```bash
git add app/Services/PermissionCatalog.php database/seeders/RolePermissionSeeder.php \
        app/Http/Controllers/Admin/JenisTagihanController.php resources/views/admin/jenis-tagihan \
        routes/admin.php resources/views/layouts/sidebar.blade.php \
        tests/Feature/Admin/JenisTagihanTest.php tests/Feature/RolePermissionSeederTest.php
git commit -m "feat: add jenis tagihan master CRUD + nominal per jalur, wire admin_keuangan permissions"
```

---

### Task 3: Mesin Invoicing (Service) + Hook M2 (Tagihan Pendaftaran)

**Files:**
- Create: `app/Services/TagihanGenerator.php`
- Modify: `app/Http/Controllers/Spmb/ReviewSubmitController.php`
- Test: `tests/Unit/TagihanGeneratorTest.php`
- Test: `tests/Feature/Spmb/TagihanPendaftaranHookTest.php`

**Interfaces:**
- Produces: `App\Services\TagihanGenerator::generate(Pendaftaran $pendaftaran, string $kategori): ?Tagihan` — the single source of truth for invoice creation, consumed again in Task 4 (M3 hook + manual susulan action) without modification.
- Consumes: `JenisTagihan`, `NominalTagihanJalur`, `Tagihan`, `TagihanItem` (Task 1).

- [ ] **Step 1: Write the failing unit test for the generator**

```php
<?php
// tests/Unit/TagihanGeneratorTest.php

use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\NominalTagihanJalur;
use App\Models\Pendaftaran;
use App\Models\Tagihan;
use App\Services\TagihanGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function siapkanPendaftaranUntukInvoicing(): array
{
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = \App\Models\TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'jalur_ppdb_id' => $jalur->id]);

    return [$lembaga, $jalur, $pendaftaran];
}

it('creates a tagihan with items summing to the configured nominal', function () {
    [$lembaga, $jalur, $pendaftaran] = siapkanPendaftaranUntukInvoicing();
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 150000]);

    $tagihan = app(TagihanGenerator::class)->generate($pendaftaran, 'pendaftaran');

    expect($tagihan)->not->toBeNull();
    expect((float) $tagihan->total_tagihan)->toBe(150000.0);
    expect($tagihan->status)->toBe('belum_bayar');
    expect($tagihan->item)->toHaveCount(1);
});

it('creates no tagihan at all when nothing is configured for this jalur', function () {
    [, , $pendaftaran] = siapkanPendaftaranUntukInvoicing();

    $tagihan = app(TagihanGenerator::class)->generate($pendaftaran, 'pendaftaran');

    expect($tagihan)->toBeNull();
    $this->assertDatabaseMissing('tagihan', ['pendaftaran_id' => $pendaftaran->id]);
});

it('creates a tagihan with only the partially-configured items, not all-or-nothing', function () {
    [$lembaga, $jalur, $pendaftaran] = siapkanPendaftaranUntukInvoicing();
    $dikonfigurasi = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Uang Pangkal', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Seragam', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]); // no nominal set for this one
    NominalTagihanJalur::create(['jenis_tagihan_id' => $dikonfigurasi->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 500000]);

    $tagihan = app(TagihanGenerator::class)->generate($pendaftaran, 'pendaftaran');

    expect($tagihan)->not->toBeNull();
    expect($tagihan->item)->toHaveCount(1);
    expect((float) $tagihan->total_tagihan)->toBe(500000.0);
});

it('marks the tagihan lunas immediately when every configured item is genuinely zero', function () {
    [$lembaga, $jalur, $pendaftaran] = siapkanPendaftaranUntukInvoicing();
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran Afirmasi', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 0]);

    $tagihan = app(TagihanGenerator::class)->generate($pendaftaran, 'pendaftaran');

    expect($tagihan->status)->toBe('lunas');
});

it('is idempotent: a second call for the same pendaftaran and kategori creates nothing and returns null', function () {
    [$lembaga, $jalur, $pendaftaran] = siapkanPendaftaranUntukInvoicing();
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 150000]);
    $generator = app(TagihanGenerator::class);

    $pertama = $generator->generate($pendaftaran, 'pendaftaran');
    $kedua = $generator->generate($pendaftaran, 'pendaftaran');

    expect($pertama)->not->toBeNull();
    expect($kedua)->toBeNull();
    expect(Tagihan::where('pendaftaran_id', $pendaftaran->id)->where('kategori', 'pendaftaran')->count())->toBe(1);
});

it('ignores jenis_tagihan of a different kategori than requested', function () {
    [$lembaga, $jalur, $pendaftaran] = siapkanPendaftaranUntukInvoicing();
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Uang Pangkal', 'kategori' => 'daftar_ulang', 'bisa_dicicil' => false]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 1000000]);

    $tagihan = app(TagihanGenerator::class)->generate($pendaftaran, 'pendaftaran');

    expect($tagihan)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/TagihanGeneratorTest.php`
Expected: FAIL — `App\Services\TagihanGenerator` doesn't exist yet.

- [ ] **Step 3: Write `TagihanGenerator`**

```php
<?php
// app/Services/TagihanGenerator.php

namespace App\Services;

use App\Models\JenisTagihan;
use App\Models\NominalTagihanJalur;
use App\Models\Pendaftaran;
use App\Models\Tagihan;
use App\Models\TagihanItem;
use Illuminate\Support\Facades\DB;

class TagihanGenerator
{
    /**
     * The single source of truth for invoice creation. Idempotent per
     * (pendaftaran_id, kategori) — a second call for the same pair creates
     * nothing and returns null, backed by the tagihan table's own unique
     * constraint as well as this explicit check. Never creates a header with
     * zero qualifying line items: a genuinely-free tagihan (every configured
     * item is Rp 0) and an unconfigured one (no items configured at all) must
     * never be indistinguishable — the former is created and marked lunas,
     * the latter creates nothing at all.
     */
    public function generate(Pendaftaran $pendaftaran, string $kategori): ?Tagihan
    {
        if (Tagihan::where('pendaftaran_id', $pendaftaran->id)->where('kategori', $kategori)->exists()) {
            return null;
        }

        $jenisTagihanList = JenisTagihan::where('lembaga_id', $pendaftaran->lembaga_id)
            ->where('kategori', $kategori)
            ->get();

        $items = [];

        foreach ($jenisTagihanList as $jenisTagihan) {
            $nominal = NominalTagihanJalur::where('jenis_tagihan_id', $jenisTagihan->id)
                ->where('jalur_ppdb_id', $pendaftaran->jalur_ppdb_id)
                ->first();

            if (! $nominal) {
                continue;
            }

            $items[] = ['jenis_tagihan_id' => $jenisTagihan->id, 'jumlah' => $nominal->nominal];
        }

        if (empty($items)) {
            return null;
        }

        $total = array_sum(array_column($items, 'jumlah'));

        return DB::transaction(function () use ($pendaftaran, $kategori, $items, $total) {
            $tagihan = Tagihan::create([
                'pendaftaran_id' => $pendaftaran->id,
                'kategori' => $kategori,
                'total_tagihan' => $total,
                'status' => $total == 0 ? 'lunas' : 'belum_bayar',
            ]);

            foreach ($items as $item) {
                TagihanItem::create(array_merge(['tagihan_id' => $tagihan->id], $item));
            }

            return $tagihan;
        });
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/TagihanGeneratorTest.php`
Expected: PASS (6/6)

- [ ] **Step 5: Write the failing hook test**

```php
<?php
// tests/Feature/Spmb/TagihanPendaftaranHookTest.php

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\NominalTagihanJalur;
use App\Models\Pendaftaran;
use App\Models\Tagihan;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use App\Services\PendaftaranWizardSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('generates a tagihan pendaftaran automatically when the m2 wizard submit succeeds', function () {
    Storage::fake('public');
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => now()->subDay(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 40,
    ]);
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 150000]);

    app(PendaftaranWizardSession::class)->put($lembaga, $jalur, [
        'email_pendaftaran' => 'ahmad@example.test',
        'nik' => '3200000000000001',
        'data_pribadi' => ['nama_lengkap' => 'Ahmad Fauzan', 'jenis_kelamin' => 'L', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '2012-01-01', 'agama' => 'Islam'],
        'alamat' => ['alamat_jalan' => 'Jl. Mawar', 'desa_kelurahan' => 'A', 'kecamatan' => 'B', 'kabupaten_kota' => 'C', 'provinsi' => 'D'],
        'keluarga' => [['jenis' => 'ayah', 'nama' => 'Bapak Ahmad']],
    ]);

    $this->post(route('spmb.submit', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]));

    $pendaftaran = Pendaftaran::where('email_pendaftaran', 'ahmad@example.test')->firstOrFail();
    $tagihan = Tagihan::where('pendaftaran_id', $pendaftaran->id)->where('kategori', 'pendaftaran')->first();
    expect($tagihan)->not->toBeNull();
    expect((float) $tagihan->total_tagihan)->toBe(150000.0);
});

it('still submits successfully even when no jenis tagihan is configured for the jalur', function () {
    Storage::fake('public');
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => now()->subDay(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 40,
    ]);

    app(PendaftaranWizardSession::class)->put($lembaga, $jalur, [
        'email_pendaftaran' => 'budi@example.test',
        'nik' => '3200000000000002',
        'data_pribadi' => ['nama_lengkap' => 'Budi Santoso', 'jenis_kelamin' => 'L', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '2012-01-01', 'agama' => 'Islam'],
        'alamat' => ['alamat_jalan' => 'Jl. Melati', 'desa_kelurahan' => 'A', 'kecamatan' => 'B', 'kabupaten_kota' => 'C', 'provinsi' => 'D'],
        'keluarga' => [['jenis' => 'ayah', 'nama' => 'Bapak Budi']],
    ]);

    $response = $this->post(route('spmb.submit', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]));

    $response->assertRedirect();
    $pendaftaran = Pendaftaran::where('email_pendaftaran', 'budi@example.test')->firstOrFail();
    $this->assertDatabaseMissing('tagihan', ['pendaftaran_id' => $pendaftaran->id]);
});
```

- [ ] **Step 6: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Spmb/TagihanPendaftaranHookTest.php`
Expected: FAIL — no hook exists yet, no tagihan gets created in the first test.

- [ ] **Step 7: Add the hook to `ReviewSubmitController::submit()`**

In `app/Http/Controllers/Spmb/ReviewSubmitController.php`, add `use App\Services\TagihanGenerator;` to the imports, add `TagihanGenerator $tagihanGenerator` as a new parameter to the `submit()` method signature (Laravel resolves it automatically via the container, exactly like the existing `KodePendaftaranGenerator $kodeGenerator` parameter), and insert this line right after `$this->pindahkanDokumenKeLokasiFinal($pendaftaran);` (existing line, unchanged) and before the existing `Mail::to(...)` line:

```php
        $tagihanGenerator->generate($pendaftaran, 'pendaftaran');
```

The full updated method signature becomes:
```php
    public function submit(
        string $lembagaSlug,
        JalurPpdb $jalur,
        PendaftaranWizardSession $wizardSession,
        KodePendaftaranGenerator $kodeGenerator,
        TagihanGenerator $tagihanGenerator
    ): RedirectResponse {
```
No other line in this method changes.

- [ ] **Step 8: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Spmb/TagihanPendaftaranHookTest.php`
Expected: PASS (2/2)

- [ ] **Step 9: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all tests pass, including every pre-existing `Spmb\ReviewSubmitTest` case (the hook is purely additive).

- [ ] **Step 10: Commit**

```bash
git add app/Services/TagihanGenerator.php app/Http/Controllers/Spmb/ReviewSubmitController.php \
        tests/Unit/TagihanGeneratorTest.php tests/Feature/Spmb/TagihanPendaftaranHookTest.php
git commit -m "feat: add TagihanGenerator service and hook it into m2 submit for tagihan pendaftaran"
```

---

### Task 4: Hook M3 (Tagihan Daftar Ulang) + Buat Tagihan Susulan

**Files:**
- Modify: `app/Http/Controllers/Admin/PendaftaranAdminController.php`
- Create: `app/Http/Controllers/Admin/TagihanController.php` (partial — only `buatSusulan()` in this task; `index()`/`data()` land in Task 5)
- Modify: `routes/admin.php`
- Modify: `resources/views/admin/spmb-pendaftaran/show.blade.php`
- Test: `tests/Feature/Admin/TagihanDaftarUlangHookTest.php`
- Test: `tests/Feature/Admin/TagihanSusulanTest.php`

**Interfaces:**
- Consumes: `TagihanGenerator::generate()` (Task 3, unmodified).
- Produces: `App\Http\Controllers\Admin\TagihanController` (new file; Task 5 adds `index()`/`data()` to the same class), route `admin.tagihan.susulan`.

- [ ] **Step 1: Write the failing test for the M3 hook**

```php
<?php
// tests/Feature/Admin/TagihanDaftarUlangHookTest.php

use App\Models\JenisTagihan;
use App\Models\NominalTagihanJalur;
use App\Models\Tagihan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('generates a tagihan daftar ulang automatically when a pendaftaran is marked diterima', function () {
    [$lembaga, $jalur, , $pendaftaran] = buatPendaftaranUntukAdmin(status: 'menunggu_verifikasi');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Uang Pangkal', 'kategori' => 'daftar_ulang', 'bisa_dicicil' => true, 'maks_cicilan' => 3]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 3000000]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('kepala_sekolah');

    $this->actingAs($user)->post(route('admin.spmb-pendaftaran.keputusan', $pendaftaran), ['status' => 'diterima']);

    $tagihan = Tagihan::where('pendaftaran_id', $pendaftaran->id)->where('kategori', 'daftar_ulang')->first();
    expect($tagihan)->not->toBeNull();
    expect((float) $tagihan->total_tagihan)->toBe(3000000.0);
});

it('does not generate a tagihan daftar ulang when the decision is ditolak', function () {
    [$lembaga, $jalur, , $pendaftaran] = buatPendaftaranUntukAdmin(status: 'menunggu_verifikasi');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Uang Pangkal', 'kategori' => 'daftar_ulang', 'bisa_dicicil' => true, 'maks_cicilan' => 3]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 3000000]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('kepala_sekolah');

    $this->actingAs($user)->post(route('admin.spmb-pendaftaran.keputusan', $pendaftaran), ['status' => 'ditolak']);

    $this->assertDatabaseMissing('tagihan', ['pendaftaran_id' => $pendaftaran->id]);
});

it('still saves the keputusan successfully even when no tagihan daftar ulang can be generated', function () {
    [$lembaga, , , $pendaftaran] = buatPendaftaranUntukAdmin(status: 'menunggu_verifikasi');
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('kepala_sekolah');

    $response = $this->actingAs($user)->post(route('admin.spmb-pendaftaran.keputusan', $pendaftaran), ['status' => 'diterima']);

    $response->assertOk();
    expect($pendaftaran->fresh()->status)->toBe('diterima');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/TagihanDaftarUlangHookTest.php`
Expected: FAIL — first test fails (no tagihan generated), second and third should already pass incidentally (nothing to break yet) but run all three together to confirm the baseline.

- [ ] **Step 3: Add the hook to `tetapkanKeputusan()`**

In `app/Http/Controllers/Admin/PendaftaranAdminController.php`, add `use App\Services\TagihanGenerator;` to the imports, add `TagihanGenerator $tagihanGenerator` as a new parameter to `tetapkanKeputusan()`, and insert this block right after the existing `$pendaftaran->update([...]);` call, before the existing `return response()->json(...)` line:

```php
        if ($data['status'] === 'diterima') {
            $tagihanGenerator->generate($pendaftaran, 'daftar_ulang');
        }
```

The full updated method signature becomes:
```php
    public function tetapkanKeputusan(Request $request, Pendaftaran $pendaftaran, TagihanGenerator $tagihanGenerator): JsonResponse
    {
```
No other line in this method changes.

- [ ] **Step 4: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/TagihanDaftarUlangHookTest.php`
Expected: PASS (3/3)

- [ ] **Step 5: Write the failing test for Buat Tagihan Susulan**

```php
<?php
// tests/Feature/Admin/TagihanSusulanTest.php

use App\Models\JenisTagihan;
use App\Models\NominalTagihanJalur;
use App\Models\Tagihan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('denies buat tagihan susulan without the tagihan.buat-susulan permission', function () {
    [$lembaga, , , $pendaftaran] = buatPendaftaranUntukAdmin();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->post(route('admin.tagihan.susulan', $pendaftaran), ['kategori' => 'pendaftaran'])->assertForbidden();
});

it('lets admin_keuangan generate a missing tagihan susulan using current nominal', function () {
    [$lembaga, $jalur, , $pendaftaran] = buatPendaftaranUntukAdmin();
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 150000]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->post(route('admin.tagihan.susulan', $pendaftaran), ['kategori' => 'pendaftaran']);

    $response->assertRedirect();
    expect(Tagihan::where('pendaftaran_id', $pendaftaran->id)->where('kategori', 'pendaftaran')->exists())->toBeTrue();
});

it('does not create a duplicate tagihan when buat susulan is triggered twice for the same kategori', function () {
    [$lembaga, $jalur, , $pendaftaran] = buatPendaftaranUntukAdmin();
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 150000]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $this->actingAs($user)->post(route('admin.tagihan.susulan', $pendaftaran), ['kategori' => 'pendaftaran']);
    $this->actingAs($user)->post(route('admin.tagihan.susulan', $pendaftaran), ['kategori' => 'pendaftaran']);

    expect(Tagihan::where('pendaftaran_id', $pendaftaran->id)->where('kategori', 'pendaftaran')->count())->toBe(1);
});

it('404s when trying to generate a tagihan susulan for a pendaftaran belonging to a different lembaga', function () {
    [, , , $pendaftaranLembagaLain] = buatPendaftaranUntukAdmin();
    $lembagaSaya = \App\Models\Lembaga::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $user->assignRole('admin_keuangan');

    $this->actingAs($user)->post(route('admin.tagihan.susulan', $pendaftaranLembagaLain), ['kategori' => 'pendaftaran'])
        ->assertNotFound();
});
```

- [ ] **Step 6: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/TagihanSusulanTest.php`
Expected: FAIL — route/controller don't exist yet.

- [ ] **Step 7: Write `TagihanController::buatSusulan()`**

```php
<?php
// app/Http/Controllers/Admin/TagihanController.php

namespace App\Http\Controllers\Admin;

use App\Models\Pendaftaran;
use App\Services\TagihanGenerator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class TagihanController extends BaseController
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

- [ ] **Step 8: Wire the route**

In `routes/admin.php`, add `use App\Http\Controllers\Admin\TagihanController;` and:

```php
    Route::post('spmb-pendaftaran/{pendaftaran}/tagihan-susulan', [TagihanController::class, 'buatSusulan'])->name('tagihan.susulan');
```

- [ ] **Step 9: Add the panel to the pendaftaran detail page**

In `resources/views/admin/spmb-pendaftaran/show.blade.php`, add a new `<x-panel>` block after the existing "Penilaian & Keputusan" panel's closing tag, before the outer `</div>`:

```blade
        <x-panel>
            <div class="border-b border-ink/10 px-6 py-4">
                <h3 class="font-display font-semibold text-ink">Tagihan</h3>
            </div>
            <div class="p-6 space-y-4">
                @forelse (['pendaftaran' => 'Tagihan Pendaftaran', 'daftar_ulang' => 'Tagihan Daftar Ulang'] as $kategori => $label)
                    @php $tagihan = $pendaftaran->tagihan->firstWhere('kategori', $kategori); @endphp
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-ink">{{ $label }}</p>
                            @if ($tagihan)
                                <p class="text-xs text-slate">Rp {{ number_format($tagihan->total_tagihan, 0, ',', '.') }}</p>
                            @else
                                <p class="text-xs text-slate">Belum ada tagihan</p>
                            @endif
                        </div>
                        @if ($tagihan)
                            <x-badge :tone="$tagihan->status === 'lunas' ? 'green' : ($tagihan->status === 'dicicil' ? 'blue' : 'amber')">
                                {{ ucfirst(str_replace('_', ' ', $tagihan->status)) }}
                            </x-badge>
                        @elseif (auth()->user()->can('tagihan.buat-susulan'))
                            <form method="POST" action="{{ route('admin.tagihan.susulan', $pendaftaran) }}">
                                @csrf
                                <input type="hidden" name="kategori" value="{{ $kategori }}">
                                <button type="submit" class="text-xs font-bold text-ink hover:underline">Buat Tagihan Susulan</button>
                            </form>
                        @endif
                    </div>
                @endforelse
            </div>
        </x-panel>
```

Add `'tagihan'` to the `$pendaftaran->load([...])` array in `PendaftaranAdminController::show()` (Task 2 of the M3 plan, already merged) so this panel doesn't trigger an N+1 query — modify the existing `load([...])` call to append `'tagihan'` to the array.

- [ ] **Step 10: Run tests to verify they pass**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/TagihanSusulanTest.php tests/Feature/Admin/TagihanDaftarUlangHookTest.php`
Expected: PASS (7/7)

- [ ] **Step 11: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all tests pass, including every pre-existing M3 `PendaftaranAdminDetailTest`/`PendaftaranAdminIndexTest` case.

- [ ] **Step 12: Commit**

```bash
git add app/Http/Controllers/Admin/PendaftaranAdminController.php app/Http/Controllers/Admin/TagihanController.php \
        routes/admin.php resources/views/admin/spmb-pendaftaran/show.blade.php \
        tests/Feature/Admin/TagihanDaftarUlangHookTest.php tests/Feature/Admin/TagihanSusulanTest.php
git commit -m "feat: hook m3 keputusan diterima into tagihan daftar ulang, add buat tagihan susulan"
```

---

### Task 5: Admin UI — Tagihan Read-Only List

**Files:**
- Modify: `app/Http/Controllers/Admin/TagihanController.php`
- Create: `resources/views/admin/tagihan/index.blade.php`
- Create: `resources/js/tagihan-table.js`
- Modify: `resources/js/app.js`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/TagihanIndexTest.php`

**Interfaces:**
- Consumes: `Tagihan`/`TagihanItem` (Task 1), `TagihanController` (Task 4, extends the same class with `index()`/`data()`), the established `PendaftaranAdminController::data()` server-side-datatable JSON contract as the pattern to mirror.
- Produces: routes `admin.tagihan.index`/`admin.tagihan.data`, completing the sidebar link added (but not yet functional) in Task 2.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Admin/TagihanIndexTest.php

use App\Models\JenisTagihan;
use App\Models\Tagihan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('denies access to the tagihan list without the tagihan.view permission', function () {
    [$lembaga] = buatPendaftaranUntukAdmin();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->get(route('admin.tagihan.index'))->assertForbidden();
    $this->actingAs($user)->getJson(route('admin.tagihan.data'))->assertForbidden();
});

it('shows the index page with the view permission', function () {
    [$lembaga] = buatPendaftaranUntukAdmin();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $this->actingAs($user)->get(route('admin.tagihan.index'))->assertOk();
});

it('returns only tagihan belonging to the acting user own lembaga, via the linked pendaftaran', function () {
    [$lembagaA, $jalurA, , $pendaftaranA] = buatPendaftaranUntukAdmin(namaCalon: 'Milik A');
    [$lembagaB, $jalurB, , $pendaftaranB] = buatPendaftaranUntukAdmin(namaCalon: 'Milik B');
    $jenisTagihanA = JenisTagihan::create(['lembaga_id' => $lembagaA->id, 'nama' => 'Biaya A', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    $jenisTagihanB = JenisTagihan::create(['lembaga_id' => $lembagaB->id, 'nama' => 'Biaya B', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    Tagihan::create(['pendaftaran_id' => $pendaftaranA->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 100000, 'status' => 'belum_bayar']);
    Tagihan::create(['pendaftaran_id' => $pendaftaranB->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 200000, 'status' => 'belum_bayar']);
    $user = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->getJson(route('admin.tagihan.data'));

    $names = collect($response->json('data'))->pluck('nama_calon_murid');
    expect($names)->toContain('Milik A');
    expect($names)->not->toContain('Milik B');
});

it('filters by status', function () {
    [$lembaga, , , $pendaftaranLunas] = buatPendaftaranUntukAdmin(namaCalon: 'Sudah Lunas');
    [, , , $pendaftaranBelumBayar] = buatPendaftaranUntukAdmin($lembaga, 'Belum Bayar');
    Tagihan::create(['pendaftaran_id' => $pendaftaranLunas->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 0, 'status' => 'lunas']);
    Tagihan::create(['pendaftaran_id' => $pendaftaranBelumBayar->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 100000, 'status' => 'belum_bayar']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->getJson(route('admin.tagihan.data', ['status' => 'lunas']));

    $names = collect($response->json('data'))->pluck('nama_calon_murid');
    expect($names)->toContain('Sudah Lunas');
    expect($names)->not->toContain('Belum Bayar');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/TagihanIndexTest.php`
Expected: FAIL — `index()`/`data()` methods and routes don't exist yet.

- [ ] **Step 3: Add `index()`/`data()` to `TagihanController`**

Add these two methods to the existing `app/Http/Controllers/Admin/TagihanController.php` (alongside `buatSusulan()` and the existing `lembagaId()` helper):

```php
    public function index(Request $request): View
    {
        $this->authorize('tagihan.view');

        return view('admin.tagihan.index', [
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
```

Add `use Illuminate\Http\JsonResponse;`, `use Illuminate\View\View;`, and `use App\Models\Tagihan;` to the top of the file.

- [ ] **Step 4: Write the view**

```blade
{{-- resources/views/admin/tagihan/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Keuangan</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Tagihan</h2>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-4" x-data="tagihanTable({ dataUrl: @js(route('admin.tagihan.data')) })">
        @if ($lembagaBelumDipilih ?? false)
            <div class="rounded-xl bg-signal-amber/10 p-4 text-sm text-signal-amber">
                Pilih lembaga aktif melalui pengalih lembaga untuk melihat tagihan.
            </div>
        @else
            <x-panel>
                <div class="flex flex-wrap items-center gap-3 border-b border-ink/10 p-4">
                    <input
                        type="search"
                        x-model="search"
                        @input="onSearchInput()"
                        placeholder="Cari nama atau kode pendaftaran..."
                        class="w-full max-w-xs rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass"
                    >
                    <select x-model="status" @change="onStatusChange()" class="rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        <option value="">Semua Status</option>
                        <option value="belum_bayar">Belum Bayar</option>
                        <option value="dicicil">Dicicil</option>
                        <option value="lunas">Lunas</option>
                    </select>
                    <button
                        type="button"
                        @click="fetchData()"
                        class="ml-auto inline-flex items-center gap-2 rounded-xl border border-ink/15 px-3 py-2 text-sm font-medium text-ink hover:bg-paper"
                    >
                        <span x-show="loading" class="inline-block h-3 w-3 animate-spin rounded-full border-2 border-ink/30 border-t-ink"></span>
                        Refresh
                    </button>
                </div>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-ink/10 bg-paper/60 text-left text-xs uppercase tracking-wide text-slate">
                            <th class="px-5 py-3 font-display font-semibold">Calon Murid</th>
                            <th class="px-5 py-3 font-display font-semibold">Kategori</th>
                            <th class="px-5 py-3 font-display font-semibold">Total</th>
                            <th class="px-5 py-3 font-display font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink/10">
                        <template x-for="row in rows" :key="row.id">
                            <tr>
                                <td class="px-5 py-3.5">
                                    <p class="font-medium text-ink" x-text="row.nama_calon_murid"></p>
                                    <p class="font-mono text-xs text-slate" x-text="row.kode_pendaftaran"></p>
                                </td>
                                <td class="px-5 py-3.5 text-ink" x-text="row.kategori === 'pendaftaran' ? 'Pendaftaran' : 'Daftar Ulang'"></td>
                                <td class="px-5 py-3.5 text-ink" x-text="'Rp ' + Number(row.total_tagihan).toLocaleString('id-ID')"></td>
                                <td class="px-5 py-3.5">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold"
                                        :class="{
                                            'bg-signal-amber/10 text-signal-amber': row.status === 'belum_bayar',
                                            'bg-signal-green/10 text-signal-green': row.status === 'lunas',
                                            'bg-brass/10 text-brass': row.status === 'dicicil',
                                        }"
                                        x-text="row.status === 'belum_bayar' ? 'Belum Bayar' : (row.status === 'lunas' ? 'Lunas' : 'Dicicil')"
                                    ></span>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="!loading && rows.length === 0">
                            <td colspan="4" class="px-5 py-10 text-center text-slate">Tidak ada tagihan yang cocok.</td>
                        </tr>
                    </tbody>
                </table>

                <div class="flex items-center justify-between border-t border-ink/10 p-4 text-sm text-slate">
                    <p>Halaman <span x-text="meta.current_page"></span> dari <span x-text="meta.last_page"></span> &middot; <span x-text="meta.total"></span> tagihan</p>
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

This is byte-for-byte the same structure as `resources/views/admin/spmb-pendaftaran/index.blade.php` (`$lembagaBelumDipilih` banner, `x-panel` table, search/status/refresh header, pagination footer) — only the columns and status labels differ. Follow that file directly if anything here is ambiguous.

```js
// resources/js/tagihan-table.js
// Copy of resources/js/pendaftaran-table.js's exact shape (config object with
// dataUrl, init() lifecycle hook, onSearchInput()/onStatusChange() debounced
// handlers, meta-driven pagination, toast on fetch failure) — this table has
// no per-row navigation, so showUrl()/showUrlTemplate are omitted.

export function tagihanTable(config) {
    return {
        rows: [],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
        search: '',
        status: '',
        page: 1,
        loading: false,
        searchTimeout: null,
        dataUrl: config.dataUrl,

        init() {
            this.fetchData();
        },

        onSearchInput() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.page = 1;
                this.fetchData();
            }, 350);
        },

        onStatusChange() {
            this.page = 1;
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
            const params = new URLSearchParams({
                search: this.search,
                status: this.status,
                page: this.page,
            });

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
                Alpine.store('toast').push('error', 'Gagal memuat data tagihan.');
            } finally {
                this.loading = false;
            }
        },
    };
}
```

- [ ] **Step 5: Register the Alpine component**

In `resources/js/app.js`, add:
```js
import { tagihanTable } from './tagihan-table';
```
and
```js
Alpine.data('tagihanTable', tagihanTable);
```
alongside the existing registrations.

- [ ] **Step 6: Wire the routes**

In `routes/admin.php`, add:
```php
    Route::get('tagihan', [TagihanController::class, 'index'])->name('tagihan.index');
    Route::get('tagihan/data', [TagihanController::class, 'data'])->name('tagihan.data');
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/TagihanIndexTest.php`
Expected: PASS (4/4)

- [ ] **Step 8: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all tests pass.

- [ ] **Step 9: `npm run build`**

Run: `npm run build` (Node bundled under Laragon at `D:\laragon\bin\nodejs\node-v24.15.0-win-x64\` if not on PATH) — confirms `tagihan-table.js` compiles cleanly into the bundle.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Admin/TagihanController.php resources/views/admin/tagihan/index.blade.php \
        resources/js/tagihan-table.js resources/js/app.js routes/admin.php \
        tests/Feature/Admin/TagihanIndexTest.php
git commit -m "feat: add read-only tagihan monitoring list for admin keuangan"
```

---

## Post-Plan Note

After Task 5, sub-project 2 (Master Tagihan & Mesin Invoicing) is feature-complete: `jenis_tagihan` and per-jalur nominal are configurable by `admin_keuangan`, and `tagihan`/`tagihan_item` are generated automatically and idempotently from both SPMB events (M2 submit, M3 acceptance), with a manual escape hatch for anything missed. Sub-project 3 (Pembayaran Manual & Portal Tagihan) is the next plan to write — it builds `skema_cicilan`/`cicilan`/`pembayaran`/`bukti_transfer` on top of the `jenis_tagihan.bisa_dicicil`/`maks_cicilan` rules already in place here, and adds a "Tagihan & Pembayaran" menu to the Portal Akun Pendaftar's existing sidebar. Not yet started.
