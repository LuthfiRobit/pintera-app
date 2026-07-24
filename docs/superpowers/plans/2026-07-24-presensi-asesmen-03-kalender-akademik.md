# Tahap 3 — Kalender Akademik & Hari Libur Dinamis Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the 3-layer calendar resolution (weekly recurring off-days → national calendar → per-lembaga override) from the design spec: `hari_libur_mingguan` on `Lembaga`, the `kalender_akademik` table, a resolver service, and admin CRUD to manage entries.

**Architecture:** Four slices in dependency order: (1) `hari_libur_mingguan` column + `Lembaga` cast, (2) `TipeKalenderAkademik` enum + `kalender_akademik` migration/model/factory, (3) `KalenderAkademikResolver` service implementing the 3-layer resolution as a pure, independently-testable class, (4) admin CRUD where national entries (`lembaga_id = null`) require a separate permission from lembaga-scoped entries.

**Tech Stack:** Laravel 12, Blade, Pest 4.

## Global Constraints

- Same conventions as Tahap 1/2: `casts()` method style, inline validation, `AuthorizesRequests`, no FormRequest classes, same Blade token set.
- `kalender_akademik.lembaga_id` is nullable — `NULL` means a national entry. This table is **explicitly excluded** from `BelongsToTenant` (do not add that trait to the `KalenderAkademik` model) because national rows must be visible/creatable independent of any single lembaga's tenant scope; scoping is handled manually in the resolver and controller instead.
- Per the design spec, `kalender_akademik` has **no** `tahun_ajaran_id` column — date-range filtering for "calendar for tahun ajaran X" is done via `whereBetween('tanggal', [$tahunAjaran->tanggal_mulai, $tahunAjaran->tanggal_selesai])` at query time, never a stored FK.
- Uniqueness of "one entry per date per lembaga" is enforced in the Form Request-equivalent inline validation (a closure), not a DB unique index — MySQL does not treat `NULL` values as duplicates in unique indexes, so it would not catch duplicate national entries.
- Creating a **national** entry (`lembaga_id = null`) requires permission `kalender-akademik.kelola-nasional`, separate from `kalender-akademik.kelola` (own-lembaga entries) — do not merge these into one permission.

---

### Task 1: `hari_libur_mingguan` on `Lembaga`

**Files:**
- Create: `database/migrations/2026_07_25_100000_add_hari_libur_mingguan_to_lembaga_table.php`
- Modify: `app/Models/Lembaga.php`
- Test: `tests/Unit/Models/LembagaHariLiburMingguanTest.php`

**Interfaces:**
- Consumes: existing `App\Models\Lembaga`.
- Produces: `Lembaga::$hari_libur_mingguan` cast to `array` (default `[0]`, 0=Sunday..6=Saturday). Task 3's `KalenderAkademikResolver` reads this column directly.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/LembagaHariLiburMingguanTest.php`:

```php
<?php

use App\Models\Lembaga;
use App\Models\Yayasan;

it('defaults hari_libur_mingguan to Sunday and casts it to an array', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    expect($lembaga->fresh()->hari_libur_mingguan)->toBe([0]);
});

it('can store a custom set of weekly off-days, e.g. Friday for a pesantren', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembaga->update(['hari_libur_mingguan' => [5]]);

    expect($lembaga->fresh()->hari_libur_mingguan)->toBe([5]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Models/LembagaHariLiburMingguanTest.php`
Expected: FAIL — `hari_libur_mingguan` returns `null` instead of `[0]` (column does not exist yet, or default not applied).

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_25_100000_add_hari_libur_mingguan_to_lembaga_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lembaga', function (Blueprint $table) {
            $table->json('hari_libur_mingguan')->default(json_encode([0]));
        });
    }

    public function down(): void
    {
        Schema::table('lembaga', function (Blueprint $table) {
            $table->dropColumn('hari_libur_mingguan');
        });
    }
};
```

Run: `php artisan migrate`
Expected: column added without error.

- [ ] **Step 4: Add the cast to `Lembaga`**

Open `app/Models/Lembaga.php`. Add `'hari_libur_mingguan'` to `$fillable`, and add (or extend, if a `casts()` method already exists) a `casts()` method:

```php
protected function casts(): array
{
    return [
        'hari_libur_mingguan' => 'array',
    ];
}
```

If `Lembaga` currently has no `casts()` method at all, add this as a new method on the class. If it already has one (check the file before editing), merge `'hari_libur_mingguan' => 'array'` into its existing return array instead of creating a second method.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Unit/Models/LembagaHariLiburMingguanTest.php`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_25_100000_add_hari_libur_mingguan_to_lembaga_table.php app/Models/Lembaga.php tests/Unit/Models/LembagaHariLiburMingguanTest.php
git commit -m "feat: add hari_libur_mingguan column to lembaga"
```

---

### Task 2: `TipeKalenderAkademik` enum + `kalender_akademik` migration, model, factory

**Files:**
- Create: `app/Enums/TipeKalenderAkademik.php`
- Create: `database/migrations/2026_07_25_100100_create_kalender_akademik_table.php`
- Create: `app/Models/KalenderAkademik.php`
- Create: `database/factories/KalenderAkademikFactory.php`
- Test: `tests/Unit/Models/KalenderAkademikTest.php`

**Interfaces:**
- Consumes: `App\Models\Lembaga`.
- Produces: `App\Enums\TipeKalenderAkademik` (`Libur = 'libur'`, `Kerja = 'kerja'`), `App\Models\KalenderAkademik` with `$fillable = ['lembaga_id', 'tanggal', 'nama', 'tipe', 'keterangan']`, `tanggal` cast to `date`, `tipe` cast to `TipeKalenderAkademik`. Task 3's resolver and Task 4's controller both query this model directly.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/KalenderAkademikTest.php`:

```php
<?php

use App\Enums\TipeKalenderAkademik;
use App\Models\KalenderAkademik;
use App\Models\Lembaga;
use App\Models\Yayasan;

it('allows a null lembaga_id to represent a national entry', function () {
    $entry = KalenderAkademik::create([
        'lembaga_id' => null,
        'tanggal' => '2026-08-17',
        'nama' => 'Hari Kemerdekaan RI',
        'tipe' => TipeKalenderAkademik::Libur->value,
    ]);

    expect($entry->fresh()->lembaga_id)->toBeNull();
    expect($entry->fresh()->tipe)->toBe(TipeKalenderAkademik::Libur);
});

it('can belong to a specific lembaga as an override', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $entry = KalenderAkademik::create([
        'lembaga_id' => $lembaga->id,
        'tanggal' => '2026-01-01',
        'nama' => 'Tetap Masuk (Kebijakan Internal)',
        'tipe' => TipeKalenderAkademik::Kerja->value,
    ]);

    expect($entry->fresh()->lembaga->id)->toBe($lembaga->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Models/KalenderAkademikTest.php`
Expected: FAIL with `Class "App\Models\KalenderAkademik" not found`

- [ ] **Step 3: Create the enum**

Create `app/Enums/TipeKalenderAkademik.php`:

```php
<?php

namespace App\Enums;

enum TipeKalenderAkademik: string
{
    case Libur = 'libur';
    case Kerja = 'kerja';

    public function label(): string
    {
        return match ($this) {
            self::Libur => 'Libur',
            self::Kerja => 'Tetap Masuk (Override)',
        };
    }
}
```

- [ ] **Step 4: Create the migration**

Create `database/migrations/2026_07_25_100100_create_kalender_akademik_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kalender_akademik', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('nama');
            $table->enum('tipe', ['libur', 'kerja']);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['lembaga_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kalender_akademik');
    }
};
```

Run: `php artisan migrate`
Expected: `kalender_akademik` table created without error.

- [ ] **Step 5: Create the model**

Create `app/Models/KalenderAkademik.php`:

```php
<?php

namespace App\Models;

use App\Enums\TipeKalenderAkademik;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KalenderAkademik extends Model
{
    use HasFactory;

    protected $table = 'kalender_akademik';

    protected $fillable = ['lembaga_id', 'tanggal', 'nama', 'tipe', 'keterangan'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'tipe' => TipeKalenderAkademik::class,
        ];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function scopeNasional($query)
    {
        return $query->whereNull('lembaga_id');
    }

    public function scopeUntukLembaga($query, int $lembagaId)
    {
        return $query->where('lembaga_id', $lembagaId);
    }
}
```

- [ ] **Step 6: Create the factory**

Create `database/factories/KalenderAkademikFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\TipeKalenderAkademik;
use App\Models\KalenderAkademik;
use Illuminate\Database\Eloquent\Factories\Factory;

class KalenderAkademikFactory extends Factory
{
    protected $model = KalenderAkademik::class;

    public function definition(): array
    {
        return [
            'lembaga_id' => null,
            'tanggal' => $this->faker->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'nama' => $this->faker->sentence(3),
            'tipe' => TipeKalenderAkademik::Libur->value,
            'keterangan' => null,
        ];
    }
}
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test tests/Unit/Models/KalenderAkademikTest.php`
Expected: PASS (2 tests)

- [ ] **Step 8: Commit**

```bash
git add app/Enums/TipeKalenderAkademik.php database/migrations/2026_07_25_100100_create_kalender_akademik_table.php app/Models/KalenderAkademik.php database/factories/KalenderAkademikFactory.php tests/Unit/Models/KalenderAkademikTest.php
git commit -m "feat: add TipeKalenderAkademik enum, KalenderAkademik migration/model/factory"
```

---

### Task 3: `KalenderAkademikResolver` service (3-layer resolution)

**Files:**
- Create: `app/Services/KalenderAkademikResolver.php`
- Test: `tests/Unit/Services/KalenderAkademikResolverTest.php`

**Interfaces:**
- Consumes: `App\Models\Lembaga` (`hari_libur_mingguan`, Task 1), `App\Models\KalenderAkademik` (Task 2).
- Produces: `KalenderAkademikResolver::resolve(Lembaga $lembaga, \Carbon\CarbonInterface $tanggal): array{libur: bool, alasan: string}`. Tahap 5 (Sesi Pembelajaran generator) calls this method to decide whether to generate a `sesi_pembelajaran` for a given date.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Services/KalenderAkademikResolverTest.php`:

```php
<?php

use App\Models\KalenderAkademik;
use App\Models\Lembaga;
use App\Models\Yayasan;
use App\Services\KalenderAkademikResolver;
use Carbon\Carbon;

it('resolves a plain weekday with no calendar entries as a school day', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0]]);

    $result = (new KalenderAkademikResolver)->resolve($lembaga, Carbon::parse('2026-08-19')); // Wednesday

    expect($result['libur'])->toBeFalse();
});

it('resolves a Sunday as libur via hari_libur_mingguan when no calendar entry exists', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0]]);

    $result = (new KalenderAkademikResolver)->resolve($lembaga, Carbon::parse('2026-08-16')); // Sunday

    expect($result['libur'])->toBeTrue();
    expect($result['alasan'])->toBe('Libur mingguan');
});

it('resolves a Friday as a normal school day for a lembaga whose weekly off-day is Sunday only', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0]]);

    $result = (new KalenderAkademikResolver)->resolve($lembaga, Carbon::parse('2026-08-21')); // Friday

    expect($result['libur'])->toBeFalse();
});

it('national calendar entry overrides the weekly recurring default', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0]]);
    KalenderAkademik::create(['lembaga_id' => null, 'tanggal' => '2026-08-17', 'nama' => 'Hari Kemerdekaan RI', 'tipe' => 'libur']);

    $result = (new KalenderAkademikResolver)->resolve($lembaga, Carbon::parse('2026-08-17')); // Monday, not a weekly off-day

    expect($result['libur'])->toBeTrue();
    expect($result['alasan'])->toBe('Hari Kemerdekaan RI');
});

it('lembaga-specific override beats the national entry for the same date', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0]]);
    KalenderAkademik::create(['lembaga_id' => null, 'tanggal' => '2027-01-01', 'nama' => 'Tahun Baru Masehi', 'tipe' => 'libur']);
    KalenderAkademik::create(['lembaga_id' => $lembaga->id, 'tanggal' => '2027-01-01', 'nama' => 'Tetap Masuk (Kebijakan Internal)', 'tipe' => 'kerja']);

    $result = (new KalenderAkademikResolver)->resolve($lembaga, Carbon::parse('2027-01-01'));

    expect($result['libur'])->toBeFalse();
    expect($result['alasan'])->toBe('Tetap Masuk (Kebijakan Internal)');
});

it('lembaga-specific entry does not leak to a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0]]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0]]);
    KalenderAkademik::create(['lembaga_id' => $lembagaA->id, 'tanggal' => '2027-01-01', 'nama' => 'Tetap Masuk (Kebijakan Internal)', 'tipe' => 'kerja']);
    KalenderAkademik::create(['lembaga_id' => null, 'tanggal' => '2027-01-01', 'nama' => 'Tahun Baru Masehi', 'tipe' => 'libur']);

    $result = (new KalenderAkademikResolver)->resolve($lembagaB, Carbon::parse('2027-01-01'));

    expect($result['libur'])->toBeTrue();
    expect($result['alasan'])->toBe('Tahun Baru Masehi');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/KalenderAkademikResolverTest.php`
Expected: FAIL with `Class "App\Services\KalenderAkademikResolver" not found`

- [ ] **Step 3: Create the resolver**

Create `app/Services/KalenderAkademikResolver.php`:

```php
<?php

namespace App\Services;

use App\Enums\TipeKalenderAkademik;
use App\Models\KalenderAkademik;
use App\Models\Lembaga;
use Carbon\CarbonInterface;

class KalenderAkademikResolver
{
    /**
     * @return array{libur: bool, alasan: string}
     */
    public function resolve(Lembaga $lembaga, CarbonInterface $tanggal): array
    {
        $entriLembaga = KalenderAkademik::untukLembaga($lembaga->id)
            ->whereDate('tanggal', $tanggal->toDateString())
            ->first();

        if ($entriLembaga) {
            return [
                'libur' => $entriLembaga->tipe === TipeKalenderAkademik::Libur,
                'alasan' => $entriLembaga->nama,
            ];
        }

        $entriNasional = KalenderAkademik::nasional()
            ->whereDate('tanggal', $tanggal->toDateString())
            ->first();

        if ($entriNasional) {
            return [
                'libur' => $entriNasional->tipe === TipeKalenderAkademik::Libur,
                'alasan' => $entriNasional->nama,
            ];
        }

        if (in_array($tanggal->dayOfWeek, $lembaga->hari_libur_mingguan ?? [], true)) {
            return ['libur' => true, 'alasan' => 'Libur mingguan'];
        }

        return ['libur' => false, 'alasan' => 'Hari efektif belajar'];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/KalenderAkademikResolverTest.php`
Expected: PASS (6 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/KalenderAkademikResolver.php tests/Unit/Services/KalenderAkademikResolverTest.php
git commit -m "feat: add KalenderAkademikResolver implementing 3-layer calendar resolution"
```

---

### Task 4: Admin CRUD — Kalender Akademik

**Files:**
- Create: `app/Http/Controllers/Admin/KalenderAkademikController.php`
- Create: `resources/views/admin/kalender-akademik/index.blade.php`
- Create: `resources/views/admin/kalender-akademik/create.blade.php`
- Create: `resources/views/admin/kalender-akademik/edit.blade.php`
- Modify: `routes/admin.php`
- Modify: `resources/views/layouts/sidebar.blade.php`
- Test: `tests/Feature/Admin/KalenderAkademikCrudTest.php`

**Interfaces:**
- Consumes: `App\Models\KalenderAkademik` (Task 2), `App\Enums\TipeKalenderAkademik` (Task 2).
- Produces: Routes `admin.kalender-akademik.index/create/store/edit/update`, permissions `kalender-akademik.view`, `kalender-akademik.kelola` (own lembaga), `kalender-akademik.kelola-nasional` (entries with `lembaga_id = null`). Tahap 5's `sesi_pembelajaran` generator reads rows this controller creates via the resolver from Task 3.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/KalenderAkademikCrudTest.php`:

```php
<?php

use App\Enums\TipeKalenderAkademik;
use App\Models\KalenderAkademik;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsKalenderManager(Lembaga $lembaga, bool $bolehNasional = false): User
{
    $permissions = ['kalender-akademik.view', 'kalender-akademik.kelola'];
    if ($bolehNasional) {
        $permissions[] = 'kalender-akademik.kelola-nasional';
    }
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_kalender_'.($bolehNasional ? 'pusat' : 'lembaga'), 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo($permissions);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('denies access without kalender-akademik.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.kalender-akademik.index'))->assertForbidden();
});

it('creates a lembaga-scoped calendar entry without needing kelola-nasional', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKalenderManager($lembaga, bolehNasional: false);

    $this->actingAs($manager)->post(route('admin.kalender-akademik.store'), [
        'tanggal' => '2026-09-01',
        'nama' => 'Libur Yayasan',
        'tipe' => TipeKalenderAkademik::Libur->value,
        'berlaku_nasional' => '0',
    ])->assertRedirect(route('admin.kalender-akademik.index'));

    $entry = KalenderAkademik::where('nama', 'Libur Yayasan')->firstOrFail();
    expect($entry->lembaga_id)->toBe($lembaga->id);
});

it('rejects a national entry from a manager without kelola-nasional permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKalenderManager($lembaga, bolehNasional: false);

    $this->actingAs($manager)->post(route('admin.kalender-akademik.store'), [
        'tanggal' => '2026-09-01',
        'nama' => 'Coba Nasional',
        'tipe' => TipeKalenderAkademik::Libur->value,
        'berlaku_nasional' => '1',
    ])->assertForbidden();

    expect(KalenderAkademik::where('nama', 'Coba Nasional')->exists())->toBeFalse();
});

it('allows a national entry from a manager with kelola-nasional permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKalenderManager($lembaga, bolehNasional: true);

    $this->actingAs($manager)->post(route('admin.kalender-akademik.store'), [
        'tanggal' => '2026-08-17',
        'nama' => 'Hari Kemerdekaan RI',
        'tipe' => TipeKalenderAkademik::Libur->value,
        'berlaku_nasional' => '1',
    ])->assertRedirect(route('admin.kalender-akademik.index'));

    $entry = KalenderAkademik::where('nama', 'Hari Kemerdekaan RI')->firstOrFail();
    expect($entry->lembaga_id)->toBeNull();
});

it('rejects a second entry on the same date for the same scope', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKalenderManager($lembaga, bolehNasional: false);
    KalenderAkademik::create(['lembaga_id' => $lembaga->id, 'tanggal' => '2026-09-01', 'nama' => 'Entri Pertama', 'tipe' => 'libur']);

    $this->actingAs($manager)->post(route('admin.kalender-akademik.store'), [
        'tanggal' => '2026-09-01',
        'nama' => 'Entri Kedua',
        'tipe' => TipeKalenderAkademik::Kerja->value,
        'berlaku_nasional' => '0',
    ])->assertSessionHasErrors('tanggal');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/KalenderAkademikCrudTest.php`
Expected: FAIL with route `admin.kalender-akademik.index` not defined.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Admin/KalenderAkademikController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\KalenderAkademik;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class KalenderAkademikController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('kalender-akademik.view');

        $lembagaId = $request->user()->lembaga_id ?? session('active_lembaga_id');

        return view('admin.kalender-akademik.index', [
            'entriList' => KalenderAkademik::where(function ($query) use ($lembagaId) {
                $query->whereNull('lembaga_id')->orWhere('lembaga_id', $lembagaId);
            })->orderBy('tanggal')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('kalender-akademik.kelola');

        return view('admin.kalender-akademik.create', [
            'bolehNasional' => $this->authorizeNasional(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('kalender-akademik.kelola');

        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'nama' => ['required', 'string', 'max:255'],
            'tipe' => ['required', 'in:libur,kerja'],
            'keterangan' => ['nullable', 'string'],
            'berlaku_nasional' => ['nullable', 'boolean'],
        ]);

        $nasional = $request->boolean('berlaku_nasional');

        if ($nasional) {
            $this->authorize('kalender-akademik.kelola-nasional');
        }

        $lembagaId = $nasional ? null : ($request->user()->lembaga_id ?? session('active_lembaga_id'));

        $duplikat = KalenderAkademik::where('tanggal', $data['tanggal'])
            ->where(fn ($q) => $lembagaId === null ? $q->whereNull('lembaga_id') : $q->where('lembaga_id', $lembagaId))
            ->exists();

        if ($duplikat) {
            return back()->withErrors(['tanggal' => 'Sudah ada entri kalender untuk tanggal dan cakupan ini.'])->withInput();
        }

        KalenderAkademik::create([
            'lembaga_id' => $lembagaId,
            'tanggal' => $data['tanggal'],
            'nama' => $data['nama'],
            'tipe' => $data['tipe'],
            'keterangan' => $data['keterangan'] ?? null,
        ]);

        return redirect()->route('admin.kalender-akademik.index')->with('status', 'Entri kalender berhasil disimpan.');
    }

    public function edit(KalenderAkademik $kalenderAkademik): View
    {
        $this->authorize('kalender-akademik.kelola');

        return view('admin.kalender-akademik.edit', ['entri' => $kalenderAkademik]);
    }

    public function update(Request $request, KalenderAkademik $kalenderAkademik): RedirectResponse
    {
        $this->authorize('kalender-akademik.kelola');

        if ($kalenderAkademik->lembaga_id === null) {
            $this->authorize('kalender-akademik.kelola-nasional');
        }

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'tipe' => ['required', 'in:libur,kerja'],
            'keterangan' => ['nullable', 'string'],
        ]);

        $kalenderAkademik->update($data);

        return redirect()->route('admin.kalender-akademik.index')->with('status', 'Entri kalender berhasil diperbarui.');
    }

    private function authorizeNasional(): bool
    {
        return auth()->user()?->can('kalender-akademik.kelola-nasional') ?? false;
    }
}
```

- [ ] **Step 4: Add routes**

In `routes/admin.php`, add:

```php
Route::resource('kalender-akademik', KalenderAkademikController::class)->except(['show', 'destroy']);
```

Add `use App\Http\Controllers\Admin\KalenderAkademikController;` at the top.

- [ ] **Step 5: Create the views**

**Design note:** these views use this codebase's "TailAdmin-style" token set (see `resources/views/admin/lembaga/*.blade.php` as the canonical reference) — NOT the older `text-ink`/`bg-paper`/`text-brass`/`<x-panel>` tokens used by `admin/guru`/`admin/tahun-ajaran`. Breadcrumb `<h1>`+`<p>` header (no `<x-slot name="header">`), `rounded-2xl border border-gray-200 bg-white shadow-card` cards, `<x-table-actions>`/`<x-dropdown-link>` for the leftmost sticky "Aksi" column, `<x-badge tone="brass|green|red|amber|blue|slate">`, `<x-link-button>`/`<x-primary-button>`/`<x-input-label>`/`<x-text-input>`/`<x-input-error>`, and a shared `_form.blade.php` partial included by both `create.blade.php` and `edit.blade.php` (matching `admin/lembaga/_form.blade.php`'s pattern of a `$val()` closure keyed off an optional `$entri` variable).

Create `resources/views/admin/kalender-akademik/index.blade.php`:

```blade
<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Kalender Akademik</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Kalender Akademik</b>
            </p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
                <p class="font-display text-sm font-bold text-gray-900">Daftar Entri Kalender</p>
                <x-link-button href="{{ route('admin.kalender-akademik.create') }}">
                    <span class="text-base leading-none">+</span> Tambah Entri
                </x-link-button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            <th class="sticky left-0 z-10 bg-white px-5 py-3">Aksi</th>
                            <th class="px-5 py-3">Tanggal</th>
                            <th class="px-5 py-3">Nama</th>
                            <th class="px-5 py-3">Tipe</th>
                            <th class="px-5 py-3">Cakupan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($entriList as $entri)
                            <tr class="transition hover:bg-gray-50">
                                <td class="sticky left-0 z-10 bg-white px-5 py-3">
                                    <x-table-actions>
                                        <x-dropdown-link :href="route('admin.kalender-akademik.edit', $entri)">
                                            <span class="inline-flex items-center gap-2.5">
                                                <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                                Edit Entri
                                            </span>
                                        </x-dropdown-link>
                                    </x-table-actions>
                                </td>
                                <td class="px-5 py-3.5 text-gray-600">{{ $entri->tanggal->translatedFormat('d F Y') }}</td>
                                <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $entri->nama }}</td>
                                <td class="px-5 py-3.5">
                                    <x-badge :tone="$entri->tipe->value === 'libur' ? 'red' : 'green'">{{ $entri->tipe->label() }}</x-badge>
                                </td>
                                <td class="px-5 py-3.5">
                                    <x-badge :tone="$entri->lembaga_id === null ? 'blue' : 'slate'">{{ $entri->lembaga_id === null ? 'Nasional' : 'Khusus Lembaga Ini' }}</x-badge>
                                </td>
                            </tr>
                        @endforeach

                        @if ($entriList->isEmpty())
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-gray-500">Belum ada entri kalender.</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
```

Create `resources/views/admin/kalender-akademik/_form.blade.php`:

```blade
@php
    $entri = $entri ?? null;
    $val = fn (string $field, $default = '') => old($field, $entri?->$field instanceof \BackedEnum ? $entri->$field->value : ($entri?->$field ?? $default));
    $selectClass = 'w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500';
@endphp

<div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
    <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
        <x-icon name="calendar_month" class="h-[15px] w-[15px] text-gray-400" />
        Detail Entri
    </p>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        @if ($entri)
            <div class="sm:col-span-2">
                <p class="text-sm text-gray-500">
                    Tanggal: <span class="font-semibold text-gray-700">{{ $entri->tanggal->translatedFormat('d F Y') }}</span>
                    &middot; Cakupan: <span class="font-semibold text-gray-700">{{ $entri->lembaga_id === null ? 'Nasional' : 'Khusus Lembaga Ini' }}</span>
                    — tanggal &amp; cakupan tidak dapat diubah setelah dibuat.
                </p>
            </div>
        @else
            <div>
                <x-input-label value="Tanggal" />
                <x-text-input type="date" name="tanggal" value="{{ $val('tanggal') }}" class="mt-1.5" />
                <x-input-error :messages="$errors->get('tanggal')" class="mt-1.5" />
            </div>
        @endif

        <div>
            <x-input-label value="Nama" />
            <x-text-input type="text" name="nama" value="{{ $val('nama') }}" placeholder="Libur Semester Ganjil" class="mt-1.5" />
            <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label value="Tipe" />
            <select name="tipe" class="mt-1.5 {{ $selectClass }}">
                <option value="libur" @selected($val('tipe') === 'libur')>Libur</option>
                <option value="kerja" @selected($val('tipe') === 'kerja')>Tetap Masuk (Override)</option>
            </select>
            <x-input-error :messages="$errors->get('tipe')" class="mt-1.5" />
        </div>

        @if (! $entri && ($bolehNasional ?? false))
            <div class="flex items-center pt-1">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="berlaku_nasional" value="1" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                    Berlaku untuk semua lembaga (nasional)
                </label>
            </div>
        @endif

        <div class="sm:col-span-2">
            <x-input-label value="Keterangan (opsional)" />
            <textarea name="keterangan" rows="2" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">{{ $val('keterangan') }}</textarea>
            <x-input-error :messages="$errors->get('keterangan')" class="mt-1.5" />
        </div>
    </div>
</div>
```

Create `resources/views/admin/kalender-akademik/create.blade.php`:

```blade
<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Tambah Entri Kalender</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.kalender-akademik.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Kalender Akademik</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Tambah</b>
            </p>
        </div>

        <form method="POST" action="{{ route('admin.kalender-akademik.store') }}">
            @csrf

            @include('admin.kalender-akademik._form', ['bolehNasional' => $bolehNasional])

            <div class="mt-4 flex items-center gap-3">
                <x-primary-button type="submit">Simpan Entri</x-primary-button>
                <a href="{{ route('admin.kalender-akademik.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Batal</a>
            </div>
        </form>
    </div>
</x-app-layout>
```

Create `resources/views/admin/kalender-akademik/edit.blade.php`:

```blade
<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Edit Entri: {{ $entri->nama }}</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.kalender-akademik.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Kalender Akademik</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Edit</b>
            </p>
        </div>

        <form method="POST" action="{{ route('admin.kalender-akademik.update', $entri) }}">
            @csrf
            @method('PUT')

            @include('admin.kalender-akademik._form', ['entri' => $entri])

            <div class="mt-4 flex items-center gap-3">
                <x-primary-button type="submit">Simpan Perubahan</x-primary-button>
                <a href="{{ route('admin.kalender-akademik.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Batal</a>
            </div>
        </form>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Add sidebar entry**

In `resources/views/layouts/sidebar.blade.php`, add a new `'III. Akademik'` group after `'II. Data Induk'` (this is the first entry in this group — later tahap add more items here):

```php
[
    'label' => 'III. Akademik',
    'items' => array_filter([
        Auth::user()->can('kalender-akademik.view') ? ['route' => 'admin.kalender-akademik.index', 'pattern' => 'admin.kalender-akademik.*', 'label' => 'Kalender Akademik', 'icon' => 'event'] : null,
    ]),
],
```

- [ ] **Step 7: Sync permissions**

Run: `php artisan permissions:sync`
Expected: Output includes `Created permission: kalender-akademik.view`, `Created permission: kalender-akademik.kelola`, `Created permission: kalender-akademik.kelola-nasional`.

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test tests/Feature/Admin/KalenderAkademikCrudTest.php`
Expected: PASS (5 tests)

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Admin/KalenderAkademikController.php resources/views/admin/kalender-akademik routes/admin.php resources/views/layouts/sidebar.blade.php tests/Feature/Admin/KalenderAkademikCrudTest.php
git commit -m "feat: add Kalender Akademik admin CRUD"
```

Note: `resources/views/admin/kalender-akademik/_form.blade.php` is included by the `git add resources/views/admin/kalender-akademik` directory add above — no separate line needed.

---

## Plan Self-Review Notes

- **Spec coverage**: Implements spec Section 2 in full — layered resolution (2.1), schema (2.2, minus the `tahun_ajaran_id` FK deliberately omitted per 2.3), and the query-time date-range approach for "calendar for a tahun ajaran" (2.3) is available to any future controller via `KalenderAkademik::whereBetween('tanggal', [...])` — no dedicated print/report UI is built in this tahap since the spec did not request one yet.
- **Type consistency check**: `KalenderAkademikResolver::resolve()`'s return shape `['libur' => bool, 'alasan' => string]` is the contract Tahap 5 will consume — keep these exact key names when writing that plan.
- **Note for Tahap 5**: the resolver is a plain service class with no constructor dependencies (`new KalenderAkademikResolver`), so Tahap 5's sesi-generation job/command can instantiate it directly or resolve it from the container — either works since there's no bound interface.
