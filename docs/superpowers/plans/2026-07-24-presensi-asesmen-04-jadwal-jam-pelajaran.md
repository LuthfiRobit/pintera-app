# Tahap 4 — Jadwal & Jam Pelajaran Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `PolaJam` (reusable time-slot templates), `JamPelajaran` (per-day slots inside a pola), the deferred `kelas.pola_jam_id` column, and `JadwalPelajaran` (which mapel/guru teaches which class at which slot each semester) — plus admin CRUD for all of it.

**Architecture:** Five slices in dependency order: (1) `Hari` enum, (2) `PolaJam` migration/model/factory, (3) `JamPelajaran` migration/model/factory (belongs to `PolaJam`), (4) the `kelas.pola_jam_id` column deferred from Tahap 1 Task 3, (5) `JadwalPelajaran` migration/model/factory, then admin CRUD for `PolaJam`+`JamPelajaran` (nested, mirroring the existing `TahunAjaran`+`Semester` nested-form UI pattern) and for `JadwalPelajaran`.

**Tech Stack:** Laravel 12, Blade, Pest 4.

## Global Constraints

- Same conventions as Tahap 1-3 (`casts()` method style, inline validation, `AuthorizesRequests`, Blade token set, `permissions:sync` — never hand-edit a seeder).
- `jam_pelajaran.hari` is scoped **inside** a `pola_jam` — the same pola can have a different number/shape of slots per day (e.g. Monday has an extra "Upacara" slot). Do not add a separate `hari` column anywhere else; `jadwal_pelajaran` never stores its own day — the day is always implied by which `jam_pelajaran_id` it points to.
- `jam_pelajaran.is_pelajaran = false` rows (istirahat, upacara, sholat) must be excluded from the `jam_pelajaran` dropdown when creating a `JadwalPelajaran` row — enforce this in the controller's query, not just the view.
- `kelas.pola_jam_id` is nullable — a `Kelas` created in Tahap 1 before a pola exists must keep working un-migrated data intact.

---

### Task 1: `Hari` enum

**Files:**
- Create: `app/Enums/Hari.php`
- Test: `tests/Unit/Enums/HariTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `App\Enums\Hari` with cases `Senin = 'senin'`, `Selasa = 'selasa'`, `Rabu = 'rabu'`, `Kamis = 'kamis'`, `Jumat = 'jumat'`, `Sabtu = 'sabtu'`, `Minggu = 'minggu'`. Task 3 (`JamPelajaran`) casts its `hari` column to this enum.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Enums/HariTest.php`:

```php
<?php

use App\Enums\Hari;

it('defines all 7 days starting with Senin', function () {
    expect(array_column(Hari::cases(), 'value'))
        ->toBe(['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Enums/HariTest.php`
Expected: FAIL with `Class "App\Enums\Hari" not found`

- [ ] **Step 3: Create the enum**

Create `app/Enums/Hari.php`:

```php
<?php

namespace App\Enums;

enum Hari: string
{
    case Senin = 'senin';
    case Selasa = 'selasa';
    case Rabu = 'rabu';
    case Kamis = 'kamis';
    case Jumat = 'jumat';
    case Sabtu = 'sabtu';
    case Minggu = 'minggu';

    public function label(): string
    {
        return match ($this) {
            self::Senin => 'Senin',
            self::Selasa => 'Selasa',
            self::Rabu => 'Rabu',
            self::Kamis => 'Kamis',
            self::Jumat => 'Jumat',
            self::Sabtu => 'Sabtu',
            self::Minggu => 'Minggu',
        };
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Enums/HariTest.php`
Expected: PASS (1 test)

- [ ] **Step 5: Commit**

```bash
git add app/Enums/Hari.php tests/Unit/Enums/HariTest.php
git commit -m "feat: add Hari enum"
```

---

### Task 2: `PolaJam` migration, model, factory

**Files:**
- Create: `database/migrations/2026_07_25_110000_create_pola_jam_table.php`
- Create: `app/Models/PolaJam.php`
- Create: `database/factories/PolaJamFactory.php`
- Test: `tests/Unit/Models/PolaJamTest.php`

**Interfaces:**
- Consumes: `App\Models\Concerns\BelongsToTenant`.
- Produces: `App\Models\PolaJam` with `$fillable = ['lembaga_id', 'nama']`, `jamPelajaran(): HasMany`. Task 3's `JamPelajaran` belongs to this; Task 4's `kelas.pola_jam_id` references this table.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/PolaJamTest.php`:

```php
<?php

use App\Models\Lembaga;
use App\Models\PolaJam;
use App\Models\Yayasan;

it('belongs to a lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $pola = PolaJam::create(['lembaga_id' => $lembaga->id, 'nama' => 'Kelas Tinggi 4-6']);

    expect($pola->fresh()->lembaga->id)->toBe($lembaga->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Models/PolaJamTest.php`
Expected: FAIL with `Class "App\Models\PolaJam" not found`

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_25_110000_create_pola_jam_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pola_jam', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->string('nama');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pola_jam');
    }
};
```

Run: `php artisan migrate`
Expected: `pola_jam` table created without error.

- [ ] **Step 4: Create the model**

Create `app/Models/PolaJam.php`:

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PolaJam extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'pola_jam';

    protected $fillable = ['lembaga_id', 'nama'];

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function jamPelajaran(): HasMany
    {
        return $this->hasMany(JamPelajaran::class);
    }
}
```

- [ ] **Step 5: Create the factory**

Create `database/factories/PolaJamFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Lembaga;
use App\Models\PolaJam;
use Illuminate\Database\Eloquent\Factories\Factory;

class PolaJamFactory extends Factory
{
    protected $model = PolaJam::class;

    public function definition(): array
    {
        return [
            'lembaga_id' => Lembaga::factory(),
            'nama' => $this->faker->randomElement(['Kelas Rendah 1-3', 'Kelas Tinggi 4-6', 'Kelompok Bermain']),
        ];
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test tests/Unit/Models/PolaJamTest.php`
Expected: PASS (1 test)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_25_110000_create_pola_jam_table.php app/Models/PolaJam.php database/factories/PolaJamFactory.php tests/Unit/Models/PolaJamTest.php
git commit -m "feat: add PolaJam migration, model, factory"
```

---

### Task 3: `JamPelajaran` migration, model, factory

**Files:**
- Create: `database/migrations/2026_07_25_110100_create_jam_pelajaran_table.php`
- Create: `app/Models/JamPelajaran.php`
- Create: `database/factories/JamPelajaranFactory.php`
- Test: `tests/Unit/Models/JamPelajaranTest.php`

**Interfaces:**
- Consumes: `App\Models\PolaJam` (Task 2), `App\Enums\Hari` (Task 1).
- Produces: `App\Models\JamPelajaran` with `$fillable = ['pola_jam_id', 'hari', 'urutan', 'label', 'jam_mulai', 'jam_selesai', 'is_pelajaran']`, `hari` cast to `Hari`, `is_pelajaran` cast to `bool`. Task 5's `JadwalPelajaran` references `jam_pelajaran_id`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/JamPelajaranTest.php`:

```php
<?php

use App\Enums\Hari;
use App\Models\JamPelajaran;
use App\Models\Lembaga;
use App\Models\PolaJam;
use App\Models\Yayasan;

it('belongs to a pola jam and casts hari to the enum', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);

    $jam = JamPelajaran::create([
        'pola_jam_id' => $pola->id,
        'hari' => Hari::Senin->value,
        'urutan' => 1,
        'label' => 'Upacara',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:35',
        'is_pelajaran' => false,
    ]);

    expect($jam->fresh()->hari)->toBe(Hari::Senin);
    expect($jam->fresh()->polaJam->id)->toBe($pola->id);
    expect($jam->fresh()->is_pelajaran)->toBeFalse();
});

it('supports a different set of slots on Monday vs other days within the same pola', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);

    JamPelajaran::create(['pola_jam_id' => $pola->id, 'hari' => Hari::Senin->value, 'urutan' => 1, 'label' => 'Upacara', 'jam_mulai' => '07:00', 'jam_selesai' => '07:35', 'is_pelajaran' => false]);
    JamPelajaran::create(['pola_jam_id' => $pola->id, 'hari' => Hari::Senin->value, 'urutan' => 2, 'label' => 'Jam ke-1', 'jam_mulai' => '07:35', 'jam_selesai' => '08:10', 'is_pelajaran' => true]);
    JamPelajaran::create(['pola_jam_id' => $pola->id, 'hari' => Hari::Selasa->value, 'urutan' => 1, 'label' => 'Jam ke-1', 'jam_mulai' => '07:00', 'jam_selesai' => '07:35', 'is_pelajaran' => true]);

    expect($pola->fresh()->jamPelajaran()->where('hari', Hari::Senin->value)->count())->toBe(2);
    expect($pola->fresh()->jamPelajaran()->where('hari', Hari::Selasa->value)->count())->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Models/JamPelajaranTest.php`
Expected: FAIL with `Class "App\Models\JamPelajaran" not found`

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_25_110100_create_jam_pelajaran_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jam_pelajaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pola_jam_id')->constrained('pola_jam')->cascadeOnDelete();
            $table->enum('hari', ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu']);
            $table->unsignedInteger('urutan');
            $table->string('label');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->boolean('is_pelajaran')->default(true);
            $table->timestamps();

            $table->unique(['pola_jam_id', 'hari', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jam_pelajaran');
    }
};
```

Run: `php artisan migrate`
Expected: `jam_pelajaran` table created without error.

- [ ] **Step 4: Create the model**

Create `app/Models/JamPelajaran.php`:

```php
<?php

namespace App\Models;

use App\Enums\Hari;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JamPelajaran extends Model
{
    use HasFactory;

    protected $table = 'jam_pelajaran';

    protected $fillable = ['pola_jam_id', 'hari', 'urutan', 'label', 'jam_mulai', 'jam_selesai', 'is_pelajaran'];

    protected function casts(): array
    {
        return [
            'hari' => Hari::class,
            'is_pelajaran' => 'boolean',
        ];
    }

    public function polaJam(): BelongsTo
    {
        return $this->belongsTo(PolaJam::class);
    }

    public function scopeIsPelajaran($query)
    {
        return $query->where('is_pelajaran', true);
    }
}
```

- [ ] **Step 5: Create the factory**

Create `database/factories/JamPelajaranFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\Hari;
use App\Models\JamPelajaran;
use App\Models\PolaJam;
use Illuminate\Database\Eloquent\Factories\Factory;

class JamPelajaranFactory extends Factory
{
    protected $model = JamPelajaran::class;

    public function definition(): array
    {
        return [
            'pola_jam_id' => PolaJam::factory(),
            'hari' => Hari::Senin->value,
            'urutan' => 1,
            'label' => 'Jam ke-1',
            'jam_mulai' => '07:00',
            'jam_selesai' => '07:35',
            'is_pelajaran' => true,
        ];
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test tests/Unit/Models/JamPelajaranTest.php`
Expected: PASS (2 tests)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_25_110100_create_jam_pelajaran_table.php app/Models/JamPelajaran.php database/factories/JamPelajaranFactory.php tests/Unit/Models/JamPelajaranTest.php
git commit -m "feat: add JamPelajaran migration, model, factory"
```

---

### Task 4: `kelas.pola_jam_id` (deferred column from Tahap 1)

**Files:**
- Create: `database/migrations/2026_07_25_110200_add_pola_jam_id_to_kelas_table.php`
- Modify: `app/Models/Kelas.php`
- Modify: `app/Http/Controllers/Admin/KelasController.php`
- Modify: `resources/views/admin/kelas/_form.blade.php`
- Test: `tests/Feature/Admin/KelasPolaJamTest.php`

**Interfaces:**
- Consumes: `App\Models\PolaJam` (Task 2), existing `Kelas` CRUD (Tahap 1 Task 7).
- Produces: `Kelas::polaJam(): BelongsTo`, `kelas_id` selectable to a `PolaJam` in the existing create/edit forms. Tahap 5's sesi-generation logic reads `$kelas->polaJam->jamPelajaran` to know which slots exist for a given class on a given day.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/KelasPolaJamTest.php`:

```php
<?php

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\PolaJam;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('assigns a pola jam to a kelas on create', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);

    foreach (['kelas.view', 'kelas.create', 'kelas.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kelas.view', 'kelas.create', 'kelas.edit']);
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => '6A',
        'pola_jam_id' => $pola->id,
    ])->assertRedirect(route('admin.kelas.index'));

    $kelas = Kelas::where('nama', '6A')->firstOrFail();
    expect($kelas->polaJam->id)->toBe($pola->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/KelasPolaJamTest.php`
Expected: FAIL with `Call to undefined relationship [polaJam]` or `pola_jam_id` not a fillable/known column.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_25_110200_add_pola_jam_id_to_kelas_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            $table->foreignId('pola_jam_id')->nullable()->after('tingkat')->constrained('pola_jam')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pola_jam_id');
        });
    }
};
```

Run: `php artisan migrate`
Expected: `pola_jam_id` column added to `kelas`.

- [ ] **Step 4: Update the `Kelas` model**

Open `app/Models/Kelas.php`. Add `'pola_jam_id'` to `$fillable`, and add this relation method:

```php
public function polaJam(): BelongsTo
{
    return $this->belongsTo(PolaJam::class);
}
```

- [ ] **Step 5: Update `KelasController`**

Open `app/Http/Controllers/Admin/KelasController.php`. In `create()`, add `'polaJamList' => PolaJam::orderBy('nama')->get(),` to the returned view data array (alongside `tahunAjaranList`/`guruList`). Do the same in `edit()`. Add `use App\Models\PolaJam;` to the imports. In both `store()` and `update()`'s validation array, add:

```php
'pola_jam_id' => ['nullable', 'exists:pola_jam,id'],
```

- [ ] **Step 6: Update the shared kelas form partial**

**Design note:** `resources/views/admin/kelas/create.blade.php` and `edit.blade.php` both include a single shared partial, `resources/views/admin/kelas/_form.blade.php` (from the post-Tahap-2 design system correction — read that file before editing). It exposes a `$val(string $field, $default = '')` closure (`old($field, $kelas?->$field ?? $default)`) and a `$selectClass` string, and its fields live in a `grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3` — you only need to touch this ONE file, not `create.blade.php`/`edit.blade.php` directly, and `$val()` already handles the create-vs-edit distinction for you (no separate `@selected(old('pola_jam_id', $kelas->pola_jam_id) ...)` needed).

In `resources/views/admin/kelas/_form.blade.php`, add this block inside the existing grid, right after the "Tingkat (opsional)" field's closing `</div>`:

```blade
<div>
    <x-input-label value="Pola Jam (opsional)" />
    <select name="pola_jam_id" class="mt-1.5 {{ $selectClass }}">
        <option value="">— Belum ditentukan —</option>
        @foreach ($polaJamList as $pola)
            <option value="{{ $pola->id }}" @selected($val('pola_jam_id') == $pola->id)>{{ $pola->nama }}</option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('pola_jam_id')" class="mt-1.5" />
</div>
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test tests/Feature/Admin/KelasPolaJamTest.php`
Expected: PASS (1 test)

Also re-run Tahap 1's `KelasCrudTest.php` to confirm no regression:

Run: `php artisan test tests/Feature/Admin/KelasCrudTest.php`
Expected: PASS (still 4 tests — `pola_jam_id` is optional so existing payloads without it remain valid)

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_07_25_110200_add_pola_jam_id_to_kelas_table.php app/Models/Kelas.php app/Http/Controllers/Admin/KelasController.php resources/views/admin/kelas/_form.blade.php tests/Feature/Admin/KelasPolaJamTest.php
git commit -m "feat: add pola_jam_id to Kelas (deferred from Tahap 1)"
```

---

### Task 5: `JadwalPelajaran` migration, model, factory

**Files:**
- Create: `database/migrations/2026_07_25_110300_create_jadwal_pelajaran_table.php`
- Create: `app/Models/JadwalPelajaran.php`
- Create: `database/factories/JadwalPelajaranFactory.php`
- Test: `tests/Unit/Models/JadwalPelajaranTest.php`

**Interfaces:**
- Consumes: `App\Models\Kelas`, `App\Models\JamPelajaran` (Task 3), `App\Models\MataPelajaran` (Tahap 1), `App\Models\Guru`, `App\Models\Semester` (existing).
- Produces: `App\Models\JadwalPelajaran` with `$fillable = ['kelas_id', 'jam_pelajaran_id', 'mata_pelajaran_id', 'guru_id', 'semester_id']`. Tahap 5's `sesi_pembelajaran` and Tahap 6's asesmen both reference `JadwalPelajaran::class`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/JadwalPelajaranTest.php`:

```php
<?php

use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\PolaJam;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

it('links kelas, jam pelajaran, mata pelajaran, guru, and semester together', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $jadwal = JadwalPelajaran::create([
        'kelas_id' => $kelas->id,
        'jam_pelajaran_id' => $jam->id,
        'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $guru->id,
        'semester_id' => $semester->id,
    ]);

    $fresh = $jadwal->fresh();
    expect($fresh->kelas->id)->toBe($kelas->id);
    expect($fresh->jamPelajaran->id)->toBe($jam->id);
    expect($fresh->mataPelajaran->id)->toBe($mapel->id);
    expect($fresh->guru->id)->toBe($guru->id);
    expect($fresh->semester->id)->toBe($semester->id);
});

it('allows mata_pelajaran_id to be null for PAUD-style generic slots', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $jadwal = JadwalPelajaran::create([
        'kelas_id' => $kelas->id,
        'jam_pelajaran_id' => $jam->id,
        'mata_pelajaran_id' => null,
        'guru_id' => $guru->id,
        'semester_id' => $semester->id,
    ]);

    expect($jadwal->fresh()->mata_pelajaran_id)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Models/JadwalPelajaranTest.php`
Expected: FAIL with `Class "App\Models\JadwalPelajaran" not found`

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_25_110300_create_jadwal_pelajaran_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_pelajaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('jam_pelajaran_id')->constrained('jam_pelajaran')->cascadeOnDelete();
            $table->foreignId('mata_pelajaran_id')->nullable()->constrained('mata_pelajaran')->nullOnDelete();
            $table->foreignId('guru_id')->constrained('guru')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semester')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['kelas_id', 'jam_pelajaran_id', 'semester_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_pelajaran');
    }
};
```

Run: `php artisan migrate`
Expected: `jadwal_pelajaran` table created without error.

- [ ] **Step 4: Create the model**

Create `app/Models/JadwalPelajaran.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalPelajaran extends Model
{
    use HasFactory;

    protected $table = 'jadwal_pelajaran';

    protected $fillable = ['kelas_id', 'jam_pelajaran_id', 'mata_pelajaran_id', 'guru_id', 'semester_id'];

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function jamPelajaran(): BelongsTo
    {
        return $this->belongsTo(JamPelajaran::class);
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }
}
```

- [ ] **Step 5: Create the factory**

Create `database/factories/JadwalPelajaranFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

class JadwalPelajaranFactory extends Factory
{
    protected $model = JadwalPelajaran::class;

    public function definition(): array
    {
        return [
            'kelas_id' => Kelas::factory(),
            'jam_pelajaran_id' => JamPelajaran::factory(),
            'mata_pelajaran_id' => MataPelajaran::factory(),
            'guru_id' => Guru::factory(),
            'semester_id' => Semester::factory(),
        ];
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test tests/Unit/Models/JadwalPelajaranTest.php`
Expected: PASS (2 tests)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_25_110300_create_jadwal_pelajaran_table.php app/Models/JadwalPelajaran.php database/factories/JadwalPelajaranFactory.php tests/Unit/Models/JadwalPelajaranTest.php
git commit -m "feat: add JadwalPelajaran migration, model, factory"
```

---

### Task 6: Admin CRUD — Pola Jam & Jam Pelajaran (nested)

**Files:**
- Create: `app/Http/Controllers/Admin/PolaJamController.php`
- Create: `app/Http/Controllers/Admin/JamPelajaranController.php`
- Create: `resources/views/admin/pola-jam/index.blade.php`
- Create: `resources/views/admin/pola-jam/create.blade.php`
- Modify: `routes/admin.php`
- Modify: `resources/views/layouts/sidebar.blade.php`
- Test: `tests/Feature/Admin/PolaJamCrudTest.php`

**Interfaces:**
- Consumes: `App\Models\PolaJam`, `App\Models\JamPelajaran` (Task 2/3), `App\Enums\Hari` (Task 1).
- Produces: Routes `admin.pola-jam.index/create/store`, `admin.jam-pelajaran.store`, permissions `pola-jam.view`, `pola-jam.create`, `jam-pelajaran.create`. One page lists all `PolaJam`, each with its `JamPelajaran` rows and an inline add-row form.

**Design note:** use this codebase's current "TailAdmin-style" token set (see `resources/views/admin/mata-pelajaran/index.blade.php` and `resources/views/admin/kelas/index.blade.php` as the canonical reference) — NOT `admin/tahun-ajaran`'s older `text-ink`/`bg-paper`/`text-brass`/`<x-panel>`/`<x-slot name="header">` tokens (that page predates the post-Tahap-2 design system correction and is out of scope to fix here). Breadcrumb `<h1>`+`<p>` header, `rounded-2xl border border-gray-200 bg-white shadow-card` cards, `<x-link-button>`/`<x-primary-button>`/`<x-input-label>`/`<x-text-input>`/`<x-input-error>`, `<x-badge tone="brass|green|red|amber|blue|slate">` for the "Non-pelajaran" marker.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/PolaJamCrudTest.php`:

```php
<?php

use App\Models\JamPelajaran;
use App\Models\Lembaga;
use App\Models\PolaJam;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsPolaJamManager(Lembaga $lembaga): User
{
    foreach (['pola-jam.view', 'pola-jam.create', 'jam-pelajaran.create'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['pola-jam.view', 'pola-jam.create', 'jam-pelajaran.create']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('denies access without pola-jam.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.pola-jam.index'))->assertForbidden();
});

it('creates a pola jam', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);

    $this->actingAs($manager)->post(route('admin.pola-jam.store'), [
        'nama' => 'Kelas Tinggi 4-6',
    ])->assertRedirect(route('admin.pola-jam.index'));

    expect(PolaJam::where('nama', 'Kelas Tinggi 4-6')->exists())->toBeTrue();
});

it('adds a jam pelajaran slot to an existing pola jam', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->post(route('admin.jam-pelajaran.store'), [
        'pola_jam_id' => $pola->id,
        'hari' => 'senin',
        'urutan' => 1,
        'label' => 'Upacara',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:35',
        'is_pelajaran' => '0',
    ])->assertRedirect(route('admin.pola-jam.index'));

    expect(JamPelajaran::where('pola_jam_id', $pola->id)->where('label', 'Upacara')->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/PolaJamCrudTest.php`
Expected: FAIL with route `admin.pola-jam.index` not defined.

- [ ] **Step 3: Create the controllers**

Create `app/Http/Controllers/Admin/PolaJamController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\PolaJam;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class PolaJamController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('pola-jam.view');

        return view('admin.pola-jam.index', [
            'polaJamList' => PolaJam::with('jamPelajaran')->orderBy('nama')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('pola-jam.create');

        return view('admin.pola-jam.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('pola-jam.create');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
        ]);

        if ($request->user()->widestScopeLevel() === 'yayasan') {
            $lembagaId = session('active_lembaga_id');

            if ($lembagaId === null) {
                return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat pola jam.'])->withInput();
            }

            $data['lembaga_id'] = $lembagaId;
        }

        PolaJam::create($data);

        return redirect()->route('admin.pola-jam.index')->with('status', 'Pola jam berhasil dibuat.');
    }
}
```

Create `app/Http/Controllers/Admin/JamPelajaranController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\JamPelajaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class JamPelajaranController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('jam-pelajaran.create');

        $data = $request->validate([
            'pola_jam_id' => ['required', 'exists:pola_jam,id'],
            'hari' => ['required', 'in:senin,selasa,rabu,kamis,jumat,sabtu,minggu'],
            'urutan' => ['required', 'integer', 'min:1'],
            'label' => ['required', 'string', 'max:255'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'is_pelajaran' => ['required', 'boolean'],
        ]);

        JamPelajaran::create($data);

        return redirect()->route('admin.pola-jam.index')->with('status', 'Jam pelajaran berhasil ditambahkan.');
    }
}
```

- [ ] **Step 4: Add routes**

In `routes/admin.php`, add:

```php
Route::get('pola-jam', [PolaJamController::class, 'index'])->name('pola-jam.index');
Route::get('pola-jam/create', [PolaJamController::class, 'create'])->name('pola-jam.create');
Route::post('pola-jam', [PolaJamController::class, 'store'])->name('pola-jam.store');
Route::post('jam-pelajaran', [JamPelajaranController::class, 'store'])->name('jam-pelajaran.store');
```

Add `use App\Http\Controllers\Admin\PolaJamController;` and `use App\Http\Controllers\Admin\JamPelajaranController;` at the top.

- [ ] **Step 5: Create the views**

Create `resources/views/admin/pola-jam/index.blade.php`:

```blade
<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Pola Jam &amp; Jam Pelajaran</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Pola Jam</b>
            </p>
        </div>

        <div class="flex justify-end">
            <x-link-button href="{{ route('admin.pola-jam.create') }}">
                <span class="text-base leading-none">+</span> Tambah Pola Jam
            </x-link-button>
        </div>

        @foreach ($polaJamList as $pola)
            <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
                <div class="border-b border-gray-200 px-5 py-4">
                    <p class="font-display text-sm font-bold text-gray-900">{{ $pola->nama }}</p>
                </div>

                @foreach (\App\Enums\Hari::cases() as $hari)
                    @php $slotHariIni = $pola->jamPelajaran->where('hari', $hari)->sortBy('urutan'); @endphp
                    @if ($slotHariIni->isNotEmpty())
                        <div class="border-b border-gray-100 px-5 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ $hari->label() }}</p>
                            <ul class="mt-1.5 space-y-1">
                                @foreach ($slotHariIni as $slot)
                                    <li class="flex items-center gap-2 text-sm text-gray-700">
                                        {{ $slot->jam_mulai }}–{{ $slot->jam_selesai }} &middot; {{ $slot->label }}
                                        @unless ($slot->is_pelajaran)
                                            <x-badge tone="slate">Non-pelajaran</x-badge>
                                        @endunless
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach

                <form method="POST" action="{{ route('admin.jam-pelajaran.store') }}" class="flex flex-wrap items-end gap-2 bg-gray-50 px-5 py-4">
                    @csrf
                    <input type="hidden" name="pola_jam_id" value="{{ $pola->id }}">
                    <select name="hari" class="rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        @foreach (\App\Enums\Hari::cases() as $hari)
                            <option value="{{ $hari->value }}">{{ $hari->label() }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="urutan" placeholder="Urutan" min="1" class="w-24 rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <input type="text" name="label" placeholder="Label (Jam ke-1, Istirahat, ...)" class="w-48 rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <input type="time" name="jam_mulai" class="rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <input type="time" name="jam_selesai" class="rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <select name="is_pelajaran" class="rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="1">Jam Belajar</option>
                        <option value="0">Non-belajar (istirahat/upacara/sholat)</option>
                    </select>
                    <x-primary-button type="submit">Tambah Slot</x-primary-button>
                </form>
            </div>
        @endforeach
    </div>
</x-app-layout>
```

Create `resources/views/admin/pola-jam/create.blade.php`:

```blade
<x-app-layout>
    <div class="mx-auto max-w-2xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Tambah Pola Jam</h1>
            <p class="text-sm text-gray-500">
                <a href="{{ route('admin.pola-jam.index') }}" class="text-gray-500 hover:text-gray-700">Pola Jam</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Tambah</b>
            </p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <form method="POST" action="{{ route('admin.pola-jam.store') }}" class="space-y-4">
                @csrf

                <div>
                    <x-input-label value="Nama Pola" />
                    <x-text-input type="text" name="nama" value="{{ old('nama') }}" placeholder="Kelas Rendah 1-3" class="mt-1.5" />
                    <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
                </div>

                <x-primary-button type="submit">Simpan</x-primary-button>
            </form>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Add sidebar entry**

In `resources/views/layouts/sidebar.blade.php`, inside the `'III. Akademik'` group (created in Tahap 3 Task 4), add after the `kalender-akademik.view` entry:

```php
Auth::user()->can('pola-jam.view') ? ['route' => 'admin.pola-jam.index', 'pattern' => 'admin.pola-jam.*', 'label' => 'Pola Jam', 'icon' => 'schedule'] : null,
```

- [ ] **Step 7: Sync permissions**

Run: `php artisan permissions:sync`
Expected: Output includes `Created permission: pola-jam.view`, `Created permission: pola-jam.create`, `Created permission: jam-pelajaran.create`.

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test tests/Feature/Admin/PolaJamCrudTest.php`
Expected: PASS (3 tests)

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Admin/PolaJamController.php app/Http/Controllers/Admin/JamPelajaranController.php resources/views/admin/pola-jam routes/admin.php resources/views/layouts/sidebar.blade.php tests/Feature/Admin/PolaJamCrudTest.php
git commit -m "feat: add Pola Jam and Jam Pelajaran admin CRUD (nested)"
```

---

### Task 7: Admin CRUD — Jadwal Pelajaran

**Files:**
- Create: `app/Http/Controllers/Admin/JadwalPelajaranController.php`
- Create: `resources/views/admin/jadwal-pelajaran/index.blade.php`
- Create: `resources/views/admin/jadwal-pelajaran/create.blade.php`
- Modify: `routes/admin.php`
- Modify: `resources/views/layouts/sidebar.blade.php`
- Test: `tests/Feature/Admin/JadwalPelajaranCrudTest.php`

**Interfaces:**
- Consumes: `App\Models\JadwalPelajaran` (Task 5), `App\Models\Kelas`, `App\Models\JamPelajaran`, `App\Models\MataPelajaran`, `App\Models\Guru`, `App\Models\Semester`.
- Produces: Routes `admin.jadwal-pelajaran.index/create/store`, permission `jadwal-pelajaran.kelola`. Tahap 5 (sesi_pembelajaran generator) reads `JadwalPelajaran` rows created through this UI, keyed by `(kelas_id, semester_id)`.

**Design note:** same TailAdmin token set as Task 6 (see `resources/views/admin/mata-pelajaran/index.blade.php` as the canonical reference) — breadcrumb `<h1>`+`<p>` header, `rounded-2xl border border-gray-200 bg-white shadow-card` cards, `<x-primary-button>`/`<x-input-label>`/`<x-input-error>`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/JadwalPelajaranCrudTest.php`:

```php
<?php

use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\PolaJam;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsJadwalManager(Lembaga $lembaga): User
{
    Permission::firstOrCreate(['name' => 'jadwal-pelajaran.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['jadwal-pelajaran.kelola']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('denies access without jadwal-pelajaran.kelola permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.jadwal-pelajaran.index'))->assertForbidden();
});

it('creates a jadwal pelajaran entry', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsJadwalManager($lembaga);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelas->id,
        'jam_pelajaran_id' => $jam->id,
        'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $guru->id,
        'semester_id' => $semester->id,
    ])->assertRedirect(route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    expect(JadwalPelajaran::where('kelas_id', $kelas->id)->where('jam_pelajaran_id', $jam->id)->exists())->toBeTrue();
});

it('only offers is_pelajaran slots when creating a jadwal entry', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jamBelajar = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true, 'label' => 'Jam ke-1']);
    $jamIstirahat = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => false, 'label' => 'Istirahat']);
    $manager = actingAsJadwalManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.create', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $response->assertViewHas('jamPelajaranList', function ($list) use ($jamBelajar, $jamIstirahat) {
        return $list->contains('id', $jamBelajar->id) && ! $list->contains('id', $jamIstirahat->id);
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php`
Expected: FAIL with route `admin.jadwal-pelajaran.index` not defined.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Admin/JadwalPelajaranController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Semester;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class JadwalPelajaranController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('jadwal-pelajaran.kelola');

        $kelasId = $request->query('kelas_id');
        $semesterId = $request->query('semester_id');

        return view('admin.jadwal-pelajaran.index', [
            'kelasList' => Kelas::orderBy('nama')->get(),
            'semesterList' => Semester::orderByDesc('id')->get(),
            'jadwalList' => $kelasId && $semesterId
                ? JadwalPelajaran::with(['jamPelajaran', 'mataPelajaran', 'guru'])
                    ->where('kelas_id', $kelasId)->where('semester_id', $semesterId)->get()
                : collect(),
            'kelasId' => $kelasId,
            'semesterId' => $semesterId,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('jadwal-pelajaran.kelola');

        $kelas = Kelas::findOrFail($request->query('kelas_id'));

        return view('admin.jadwal-pelajaran.create', [
            'kelas' => $kelas,
            'semesterId' => $request->query('semester_id'),
            'jamPelajaranList' => $kelas->pola_jam_id
                ? JamPelajaran::where('pola_jam_id', $kelas->pola_jam_id)->isPelajaran()->orderBy('hari')->orderBy('urutan')->get()
                : collect(),
            'mataPelajaranList' => MataPelajaran::orderBy('nama')->get(),
            'guruList' => Guru::orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('jadwal-pelajaran.kelola');

        $data = $request->validate([
            'kelas_id' => ['required', 'exists:kelas,id'],
            'jam_pelajaran_id' => ['required', 'exists:jam_pelajaran,id'],
            'mata_pelajaran_id' => ['nullable', 'exists:mata_pelajaran,id'],
            'guru_id' => ['required', 'exists:guru,id'],
            'semester_id' => ['required', 'exists:semester,id'],
        ]);

        JadwalPelajaran::create($data);

        return redirect()->route('admin.jadwal-pelajaran.index', [
            'kelas_id' => $data['kelas_id'],
            'semester_id' => $data['semester_id'],
        ])->with('status', 'Jadwal pelajaran berhasil ditambahkan.');
    }
}
```

- [ ] **Step 4: Add routes**

In `routes/admin.php`, add:

```php
Route::get('jadwal-pelajaran', [JadwalPelajaranController::class, 'index'])->name('jadwal-pelajaran.index');
Route::get('jadwal-pelajaran/create', [JadwalPelajaranController::class, 'create'])->name('jadwal-pelajaran.create');
Route::post('jadwal-pelajaran', [JadwalPelajaranController::class, 'store'])->name('jadwal-pelajaran.store');
```

Add `use App\Http\Controllers\Admin\JadwalPelajaranController;` at the top.

- [ ] **Step 5: Create the views**

Create `resources/views/admin/jadwal-pelajaran/index.blade.php`:

```blade
<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Jadwal Pelajaran</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Jadwal Pelajaran</b>
            </p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <form method="GET" action="{{ route('admin.jadwal-pelajaran.index') }}" class="flex flex-wrap items-end gap-2">
                <div>
                    <x-input-label value="Kelas" />
                    <select name="kelas_id" class="mt-1.5 rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Pilih Kelas —</option>
                        @foreach ($kelasList as $kelas)
                            <option value="{{ $kelas->id }}" @selected($kelasId == $kelas->id)>{{ $kelas->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label value="Semester" />
                    <select name="semester_id" class="mt-1.5 rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Pilih Semester —</option>
                        @foreach ($semesterList as $semester)
                            <option value="{{ $semester->id }}" @selected($semesterId == $semester->id)>{{ $semester->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <x-primary-button type="submit">Tampilkan</x-primary-button>
                @if ($kelasId && $semesterId)
                    <x-link-button variant="ghost" href="{{ route('admin.jadwal-pelajaran.create', ['kelas_id' => $kelasId, 'semester_id' => $semesterId]) }}">
                        <span class="text-base leading-none">+</span> Tambah Slot
                    </x-link-button>
                @endif
            </form>
        </div>

        @if ($kelasId && $semesterId)
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
                <ul class="divide-y divide-gray-100">
                    @forelse ($jadwalList as $jadwal)
                        <li class="px-5 py-3 text-sm text-gray-700">
                            {{ $jadwal->jamPelajaran->hari->label() }}, {{ $jadwal->jamPelajaran->jam_mulai }}–{{ $jadwal->jamPelajaran->jam_selesai }}
                            &middot; {{ $jadwal->mataPelajaran?->nama ?? '(tanpa mapel)' }}
                            &middot; {{ $jadwal->guru->nama }}
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-gray-500">Belum ada jadwal untuk kelas &amp; semester ini.</li>
                    @endforelse
                </ul>
            </div>
        @endif
    </div>
</x-app-layout>
```

Create `resources/views/admin/jadwal-pelajaran/create.blade.php`:

```blade
<x-app-layout>
    <div class="mx-auto max-w-2xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Tambah Jadwal — {{ $kelas->nama }}</h1>
            <p class="text-sm text-gray-500">
                <a href="{{ route('admin.jadwal-pelajaran.index') }}" class="text-gray-500 hover:text-gray-700">Jadwal Pelajaran</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Tambah</b>
            </p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <form method="POST" action="{{ route('admin.jadwal-pelajaran.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="kelas_id" value="{{ $kelas->id }}">
                <input type="hidden" name="semester_id" value="{{ $semesterId }}">

                <div>
                    <x-input-label value="Jam Pelajaran" />
                    <select name="jam_pelajaran_id" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        @forelse ($jamPelajaranList as $jam)
                            <option value="{{ $jam->id }}">{{ $jam->hari->label() }}, {{ $jam->jam_mulai }}–{{ $jam->jam_selesai }} ({{ $jam->label }})</option>
                        @empty
                            <option value="">Kelas ini belum punya Pola Jam — atur dulu di halaman Pola Jam</option>
                        @endforelse
                    </select>
                    <x-input-error :messages="$errors->get('jam_pelajaran_id')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label value="Mata Pelajaran (opsional utk PAUD)" />
                    <select name="mata_pelajaran_id" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Tidak ada —</option>
                        @foreach ($mataPelajaranList as $mapel)
                            <option value="{{ $mapel->id }}">{{ $mapel->nama }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('mata_pelajaran_id')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label value="Guru" />
                    <select name="guru_id" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($guruList as $guru)
                            <option value="{{ $guru->id }}">{{ $guru->nama }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('guru_id')" class="mt-1.5" />
                </div>

                <x-primary-button type="submit">Simpan</x-primary-button>
            </form>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Add sidebar entry**

In `resources/views/layouts/sidebar.blade.php`, inside the `'III. Akademik'` group, add after `pola-jam.view`:

```php
Auth::user()->can('jadwal-pelajaran.kelola') ? ['route' => 'admin.jadwal-pelajaran.index', 'pattern' => 'admin.jadwal-pelajaran.*', 'label' => 'Jadwal Pelajaran', 'icon' => 'fact_check'] : null,
```

- [ ] **Step 7: Sync permissions**

Run: `php artisan permissions:sync`
Expected: Output includes `Created permission: jadwal-pelajaran.kelola`.

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php`
Expected: PASS (3 tests)

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Admin/JadwalPelajaranController.php resources/views/admin/jadwal-pelajaran routes/admin.php resources/views/layouts/sidebar.blade.php tests/Feature/Admin/JadwalPelajaranCrudTest.php
git commit -m "feat: add Jadwal Pelajaran admin CRUD"
```

---

## Plan Self-Review Notes

- **Spec coverage**: Implements spec Section 3 in full — `pola_jam`, `jam_pelajaran` (per-day slots within a pola), the deferred `kelas.pola_jam_id`, and `jadwal_pelajaran`.
- **Type consistency check**: `JadwalPelajaran`'s `$fillable` (`kelas_id`, `jam_pelajaran_id`, `mata_pelajaran_id`, `guru_id`, `semester_id`) is the exact contract Tahap 5 (sesi_pembelajaran) and Tahap 6 (asesmen) will consume when they read `jadwal_pelajaran_id`/reference these columns.
- **Dependency note for Tahap 5**: the sesi-generation logic will need `$kelas->polaJam->jamPelajaran()->where('hari', $tanggal->dayOfWeekIso...)` style lookups combined with `JadwalPelajaran::where('kelas_id', ...)->where('jam_pelajaran_id', ...)->where('semester_id', ...)` to find which guru/mapel teaches a given slot on a given date — both pieces now exist for that plan to consume.
- **Pre-flight correction (2026-07-25, applied before any task was dispatched)**: this plan was originally drafted before the post-Tahap-2 design system correction and Tahap 3's admin CRUD precedent existed, so its Blade snippets still used the old `text-ink`/`bg-paper`/`text-brass`/`<x-panel>`/`<x-slot name="header">` token set and an invalid `x-icon` name (`calendar_view_week`, not in the component's whitelist — would have rendered blank). Fixed in place: Task 4's kelas form edit was retargeted from `create.blade.php`+`edit.blade.php` to the actual shared `_form.blade.php` partial those two files now include (a structure that didn't exist when this plan was first written); Task 6 and 7's views were rewritten to the current TailAdmin token set (breadcrumb header, `rounded-2xl` cards, `<x-input-label>`/`<x-text-input>`/`<x-input-error>`/`<x-primary-button>`/`<x-link-button>`); the sidebar icon for Jadwal Pelajaran was changed to `fact_check`. No functional/interaction changes were made — same routes, same controller logic, same form fields.
