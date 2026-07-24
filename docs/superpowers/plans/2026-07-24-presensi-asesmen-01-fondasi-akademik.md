# Tahap 1 — Fondasi Akademik (Siswa, Kelas, Mata Pelajaran) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the three foundation models — `MataPelajaran`, `Kelas`, `Siswa` — with full admin CRUD (list/create/edit), plus the first native PHP Enums in this codebase and a `permissions:sync` Artisan command that replaces manually-maintained permission lists going forward.

**Architecture:** Four independently-testable slices, in dependency order: (1) three native PHP Enums with no dependencies, (2) three migration+model+factory triples (`MataPelajaran`, `Kelas`, `Siswa` — `Kelas` references `Guru` for `wali_kelas_guru_id`, `Siswa` references `Kelas`), (3) a `permissions:sync` command that scans controllers instead of a hand-maintained seeder array, (4) three admin CRUD modules (controller + routes + views + sidebar entry), each authorizing via permissions the sync command will pick up automatically. `Siswa`'s CRUD in this plan is **manual-entry only** — SPMB batch conversion and Excel import are built in Tahap 2 on top of the `Siswa` model this plan produces.

**Tech Stack:** Laravel 12, Blade, Tailwind CSS (this project's `ink`/`brass`/`paper`/`signal-*` token set — NOT the navy `portal-*` tokens used in the SPMB portal), Pest 4, `spatie/laravel-permission`, `spatie/laravel-activitylog`.

## Global Constraints

- Every new tenant-scoped model uses `App\Models\Concerns\BelongsToTenant` (auto-fills `lembaga_id` only when `auth()->user()->widestScopeLevel() === 'lembaga'`) — controllers must manually resolve `session('active_lembaga_id')` for yayasan-scoped actors and error back if unset, exactly like `TahunAjaranController::store` (`app/Http/Controllers/Admin/TahunAjaranController.php:42-50`).
- Models use the **method-style cast** `protected function casts(): array { return [...]; }` — never the old `protected $casts` property. This is the established convention (`TahunAjaran`, `Guru`).
- Native PHP Enums are cast the same way: `'field' => SomeEnum::class` inside `casts()`.
- Controllers extend `Illuminate\Routing\Controller as BaseController`, `use AuthorizesRequests;`, call `$this->authorize('module.action')` as the first line of every action, and validate inline via `$request->validate([...])` — no FormRequest classes exist in this codebase, do not introduce one.
- No `show`/`destroy` routes unless explicitly required — this codebase's admin CRUD is list/create/edit only (`Route::resource(...)->except(['show', 'destroy'])`), matching `Guru` and `TahunAjaran`.
- Blade views use `<x-app-layout>`, `<x-panel>`, `<x-badge tone="...">`, `<x-link-button>`, token classes `font-display`, `text-ink`, `text-brass`, `border-ink/10`, `bg-paper/50`, `bg-signal-green/10` (success), `bg-signal-red/10` (error), inputs styled `rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass` — copy this exactly, do not invent new tokens.
- Sidebar entries go in the `'II. Data Induk'` group of `resources/views/layouts/sidebar.blade.php`, each gated by `Auth::user()->can('module.view')`.
- Tests are Pest functional style (`it('...', function () {...})`), no test classes. Permissions/roles are created inline per test file via `Permission::firstOrCreate()` + `Role::firstOrCreate()`, not via seeders.

---

### Task 1: Native PHP Enums

**Files:**
- Create: `app/Enums/SumberDataSiswa.php`
- Create: `app/Enums/StatusSiswa.php`
- Create: `app/Enums/TipeMataPelajaran.php`
- Test: `tests/Unit/Enums/AcademicEnumsTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `App\Enums\SumberDataSiswa` (cases `Spmb = 'spmb'`, `Import = 'import'`, `Manual = 'manual'`), `App\Enums\StatusSiswa` (cases `Aktif = 'aktif'`, `Lulus = 'lulus'`, `Pindah = 'pindah'`, `Keluar = 'keluar'`), `App\Enums\TipeMataPelajaran` (cases `Mapel = 'mapel'`, `AspekPerkembangan = 'aspek_perkembangan'`) — these are the first native enums in this codebase (confirmed zero existing `app/Enums/*.php` files), all three used by Task 2/3/4 models' `casts()`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Enums/AcademicEnumsTest.php`:

```php
<?php

use App\Enums\StatusSiswa;
use App\Enums\SumberDataSiswa;
use App\Enums\TipeMataPelajaran;

it('defines the expected SumberDataSiswa cases', function () {
    expect(array_column(SumberDataSiswa::cases(), 'value'))
        ->toBe(['spmb', 'import', 'manual']);
});

it('defines the expected StatusSiswa cases', function () {
    expect(array_column(StatusSiswa::cases(), 'value'))
        ->toBe(['aktif', 'lulus', 'pindah', 'keluar']);
});

it('defines the expected TipeMataPelajaran cases', function () {
    expect(array_column(TipeMataPelajaran::cases(), 'value'))
        ->toBe(['mapel', 'aspek_perkembangan']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Enums/AcademicEnumsTest.php`
Expected: FAIL with `Class "App\Enums\SumberDataSiswa" not found`

- [ ] **Step 3: Create the three enums**

Create `app/Enums/SumberDataSiswa.php`:

```php
<?php

namespace App\Enums;

enum SumberDataSiswa: string
{
    case Spmb = 'spmb';
    case Import = 'import';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Spmb => 'SPMB',
            self::Import => 'Import',
            self::Manual => 'Input Manual',
        };
    }
}
```

Create `app/Enums/StatusSiswa.php`:

```php
<?php

namespace App\Enums;

enum StatusSiswa: string
{
    case Aktif = 'aktif';
    case Lulus = 'lulus';
    case Pindah = 'pindah';
    case Keluar = 'keluar';

    public function label(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Lulus => 'Lulus',
            self::Pindah => 'Pindah',
            self::Keluar => 'Keluar',
        };
    }
}
```

Create `app/Enums/TipeMataPelajaran.php`:

```php
<?php

namespace App\Enums;

enum TipeMataPelajaran: string
{
    case Mapel = 'mapel';
    case AspekPerkembangan = 'aspek_perkembangan';

    public function label(): string
    {
        return match ($this) {
            self::Mapel => 'Mata Pelajaran',
            self::AspekPerkembangan => 'Aspek Perkembangan',
        };
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Enums/AcademicEnumsTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Enums/SumberDataSiswa.php app/Enums/StatusSiswa.php app/Enums/TipeMataPelajaran.php tests/Unit/Enums/AcademicEnumsTest.php
git commit -m "feat: add SumberDataSiswa, StatusSiswa, TipeMataPelajaran enums"
```

---

### Task 2: `MataPelajaran` migration, model, factory

**Files:**
- Create: `database/migrations/2026_07_25_090000_create_mata_pelajaran_table.php`
- Create: `app/Models/MataPelajaran.php`
- Create: `database/factories/MataPelajaranFactory.php`
- Test: `tests/Unit/Models/MataPelajaranTest.php`

**Interfaces:**
- Consumes: `App\Enums\TipeMataPelajaran` (Task 1), `App\Models\Concerns\BelongsToTenant` (existing, `app/Models/Concerns/BelongsToTenant.php`), `App\Models\Lembaga` (existing).
- Produces: `App\Models\MataPelajaran` with `$fillable = ['lembaga_id', 'nama', 'tipe']`, `tipe` cast to `TipeMataPelajaran`, `lembaga(): BelongsTo`. Later tasks (`Kelas`'s admin views, Tahap 4 Jadwal, Tahap 6 Asesmen) reference `MataPelajaran::class` and its `tipe` column.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/MataPelajaranTest.php`:

```php
<?php

use App\Enums\TipeMataPelajaran;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\Yayasan;

it('casts tipe to the TipeMataPelajaran enum', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $mapel = MataPelajaran::create([
        'lembaga_id' => $lembaga->id,
        'nama' => 'Matematika',
        'tipe' => TipeMataPelajaran::Mapel->value,
    ]);

    expect($mapel->fresh()->tipe)->toBe(TipeMataPelajaran::Mapel);
});

it('belongs to a lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);

    expect($mapel->lembaga->id)->toBe($lembaga->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Models/MataPelajaranTest.php`
Expected: FAIL with `Class "App\Models\MataPelajaran" not found`

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_25_090000_create_mata_pelajaran_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mata_pelajaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->string('nama');
            $table->enum('tipe', ['mapel', 'aspek_perkembangan']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mata_pelajaran');
    }
};
```

Run: `php artisan migrate`
Expected: `mata_pelajaran` table created without error.

- [ ] **Step 4: Create the model**

Create `app/Models/MataPelajaran.php`:

```php
<?php

namespace App\Models;

use App\Enums\TipeMataPelajaran;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MataPelajaran extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'mata_pelajaran';

    protected $fillable = ['lembaga_id', 'nama', 'tipe'];

    protected function casts(): array
    {
        return [
            'tipe' => TipeMataPelajaran::class,
        ];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }
}
```

- [ ] **Step 5: Create the factory**

Create `database/factories/MataPelajaranFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\TipeMataPelajaran;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use Illuminate\Database\Eloquent\Factories\Factory;

class MataPelajaranFactory extends Factory
{
    protected $model = MataPelajaran::class;

    public function definition(): array
    {
        return [
            'lembaga_id' => Lembaga::factory(),
            'nama' => $this->faker->randomElement(['Matematika', 'Bahasa Indonesia', 'IPA', 'IPS', 'Pendidikan Agama']),
            'tipe' => TipeMataPelajaran::Mapel->value,
        ];
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test tests/Unit/Models/MataPelajaranTest.php`
Expected: PASS (2 tests)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_25_090000_create_mata_pelajaran_table.php app/Models/MataPelajaran.php database/factories/MataPelajaranFactory.php tests/Unit/Models/MataPelajaranTest.php
git commit -m "feat: add MataPelajaran migration, model, factory"
```

---

### Task 3: `Kelas` migration, model, factory

**Files:**
- Create: `database/migrations/2026_07_25_090100_create_kelas_table.php`
- Create: `app/Models/Kelas.php`
- Create: `database/factories/KelasFactory.php`
- Test: `tests/Unit/Models/KelasTest.php`

**Interfaces:**
- Consumes: `App\Models\Concerns\BelongsToTenant`, `App\Models\Lembaga`, `App\Models\TahunAjaran` (existing), `App\Models\Guru` (existing, for `wali_kelas_guru_id`).
- Produces: `App\Models\Kelas` with `$fillable = ['lembaga_id', 'tahun_ajaran_id', 'nama', 'tingkat', 'wali_kelas_guru_id']`, `lembaga(): BelongsTo`, `tahunAjaran(): BelongsTo`, `waliKelas(): BelongsTo` (to `Guru`). **`pola_jam_id` is deliberately NOT added in this task** — Tahap 4 (Jadwal & Jam Pelajaran) adds it via a separate `ALTER TABLE` migration once `pola_jam` exists, per the design spec's dependency order. Later tasks (Siswa in this plan, Tahap 4's `jadwal_pelajaran`, Tahap 5's `sesi_pembelajaran`) reference `Kelas::class`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/KelasTest.php`:

```php
<?php

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

it('belongs to a lembaga and a tahun ajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $kelas = Kelas::create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => '6A',
        'tingkat' => '6',
    ]);

    expect($kelas->fresh()->lembaga->id)->toBe($lembaga->id);
    expect($kelas->fresh()->tahunAjaran->id)->toBe($tahunAjaran->id);
});

it('optionally belongs to a wali kelas guru', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $kelas = Kelas::create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => '6A',
        'wali_kelas_guru_id' => $guru->id,
    ]);

    expect($kelas->fresh()->waliKelas->id)->toBe($guru->id);
});

it('allows a null tingkat for PAUD-style kelompok naming', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $kelas = Kelas::create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Kelompok A',
        'tingkat' => null,
    ]);

    expect($kelas->fresh()->tingkat)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Models/KelasTest.php`
Expected: FAIL with `Class "App\Models\Kelas" not found`

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_25_090100_create_kelas_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->cascadeOnDelete();
            $table->string('nama');
            $table->string('tingkat')->nullable();
            $table->foreignId('wali_kelas_guru_id')->nullable()->constrained('guru')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tahun_ajaran_id', 'nama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas');
    }
};
```

Run: `php artisan migrate`
Expected: `kelas` table created without error.

- [ ] **Step 4: Create the model**

Create `app/Models/Kelas.php`:

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kelas extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'kelas';

    protected $fillable = ['lembaga_id', 'tahun_ajaran_id', 'nama', 'tingkat', 'wali_kelas_guru_id'];

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function waliKelas(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'wali_kelas_guru_id');
    }

    public function siswa(): HasMany
    {
        return $this->hasMany(Siswa::class);
    }
}
```

- [ ] **Step 5: Create the factory**

Create `database/factories/KelasFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

class KelasFactory extends Factory
{
    protected $model = Kelas::class;

    public function definition(): array
    {
        return [
            'lembaga_id' => Lembaga::factory(),
            'tahun_ajaran_id' => TahunAjaran::factory(),
            'nama' => $this->faker->unique()->numerify('#A'),
            'tingkat' => (string) $this->faker->numberBetween(1, 6),
            'wali_kelas_guru_id' => null,
        ];
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test tests/Unit/Models/KelasTest.php`
Expected: PASS (3 tests)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_25_090100_create_kelas_table.php app/Models/Kelas.php database/factories/KelasFactory.php tests/Unit/Models/KelasTest.php
git commit -m "feat: add Kelas migration, model, factory"
```

---

### Task 4: `Siswa` migration, model, factory

**Files:**
- Create: `database/migrations/2026_07_25_090200_create_siswa_table.php`
- Create: `app/Models/Siswa.php`
- Create: `database/factories/SiswaFactory.php`
- Test: `tests/Unit/Models/SiswaTest.php`

**Interfaces:**
- Consumes: `App\Enums\SumberDataSiswa`, `App\Enums\StatusSiswa` (Task 1), `App\Models\Kelas` (Task 3), `App\Models\CalonMurid`, `App\Models\Pendaftaran` (existing).
- Produces: `App\Models\Siswa` with `$fillable = ['lembaga_id', 'kelas_id', 'calon_murid_id', 'pendaftaran_asal_id', 'sumber_data', 'nis', 'nisn', 'nama_lengkap', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama', 'status']`, `sumber_data` cast to `SumberDataSiswa`, `status` cast to `StatusSiswa`, `tanggal_lahir` cast to `date`. Tahap 2 (SPMB batch conversion, Excel import) creates `Siswa` rows via `Siswa::create()` using these exact fillable/cast names — do not rename any of them without updating Tahap 2's plan.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/SiswaTest.php`:

```php
<?php

use App\Enums\StatusSiswa;
use App\Enums\SumberDataSiswa;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

it('casts sumber_data and status to their enums, and tanggal_lahir to a date', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $siswa = Siswa::create([
        'lembaga_id' => $lembaga->id,
        'nis' => '2026001',
        'nisn' => '0012345678',
        'nama_lengkap' => 'Budi Santoso',
        'jenis_kelamin' => 'L',
        'tanggal_lahir' => '2015-03-10',
        'sumber_data' => SumberDataSiswa::Manual->value,
        'status' => StatusSiswa::Aktif->value,
    ]);

    $fresh = $siswa->fresh();
    expect($fresh->sumber_data)->toBe(SumberDataSiswa::Manual);
    expect($fresh->status)->toBe(StatusSiswa::Aktif);
    expect($fresh->tanggal_lahir)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('can optionally belong to a kelas', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    expect($siswa->kelas->id)->toBe($kelas->id);
});

it('allows kelas_id, calon_murid_id, and pendaftaran_asal_id to all be null', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $siswa = Siswa::factory()->create([
        'lembaga_id' => $lembaga->id,
        'kelas_id' => null,
        'calon_murid_id' => null,
        'pendaftaran_asal_id' => null,
    ]);

    expect($siswa->fresh()->kelas_id)->toBeNull();
    expect($siswa->fresh()->calon_murid_id)->toBeNull();
    expect($siswa->fresh()->pendaftaran_asal_id)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Models/SiswaTest.php`
Expected: FAIL with `Class "App\Models\Siswa" not found`

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_25_090200_create_siswa_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->foreignId('kelas_id')->nullable()->constrained('kelas')->nullOnDelete();
            $table->foreignId('calon_murid_id')->nullable()->constrained('calon_murid')->nullOnDelete();
            $table->foreignId('pendaftaran_asal_id')->nullable()->constrained('pendaftaran')->nullOnDelete();

            $table->enum('sumber_data', ['spmb', 'import', 'manual']);
            $table->string('nis');
            $table->string('nisn')->nullable();
            $table->string('nama_lengkap');
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('agama')->nullable();
            $table->enum('status', ['aktif', 'lulus', 'pindah', 'keluar'])->default('aktif');

            $table->timestamps();

            $table->unique(['lembaga_id', 'nis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siswa');
    }
};
```

Run: `php artisan migrate`
Expected: `siswa` table created without error.

- [ ] **Step 4: Create the model**

Create `app/Models/Siswa.php`:

```php
<?php

namespace App\Models;

use App\Enums\StatusSiswa;
use App\Enums\SumberDataSiswa;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Siswa extends Model
{
    use HasFactory, BelongsToTenant, LogsActivity;

    protected $table = 'siswa';

    protected $fillable = [
        'lembaga_id', 'kelas_id', 'calon_murid_id', 'pendaftaran_asal_id',
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nama_lengkap', 'kelas_id', 'status', 'lembaga_id'])
            ->logOnlyDirty()
            ->useLogName('siswa');
    }
}
```

- [ ] **Step 5: Create the factory**

Create `database/factories/SiswaFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\StatusSiswa;
use App\Enums\SumberDataSiswa;
use App\Models\Lembaga;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

class SiswaFactory extends Factory
{
    protected $model = Siswa::class;

    public function definition(): array
    {
        return [
            'lembaga_id' => Lembaga::factory(),
            'kelas_id' => null,
            'calon_murid_id' => null,
            'pendaftaran_asal_id' => null,
            'sumber_data' => SumberDataSiswa::Manual->value,
            'nis' => $this->faker->unique()->numerify('2026####'),
            'nisn' => $this->faker->unique()->numerify('00########'),
            'nama_lengkap' => $this->faker->name(),
            'jenis_kelamin' => $this->faker->randomElement(['L', 'P']),
            'tempat_lahir' => $this->faker->city(),
            'tanggal_lahir' => $this->faker->dateTimeBetween('-13 years', '-6 years')->format('Y-m-d'),
            'agama' => 'Islam',
            'status' => StatusSiswa::Aktif->value,
        ];
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test tests/Unit/Models/SiswaTest.php`
Expected: PASS (3 tests)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_25_090200_create_siswa_table.php app/Models/Siswa.php database/factories/SiswaFactory.php tests/Unit/Models/SiswaTest.php
git commit -m "feat: add Siswa migration, model, factory"
```

---

### Task 5: `permissions:sync` Artisan command

**Files:**
- Create: `app/Console/Commands/SyncPermissions.php`
- Test: `tests/Feature/Console/SyncPermissionsTest.php`

**Interfaces:**
- Consumes: `Spatie\Permission\Models\Permission` (existing package), the real contents of `app/Http/Controllers/**/*.php` at scan time.
- Produces: Artisan command `permissions:sync` (class `App\Console\Commands\SyncPermissions`). Task 6/7/8 (the three admin CRUD modules below) do **not** manually add rows to `PermissionSeeder.php` — instead, once their controllers contain `$this->authorize('module.action')` calls, running `php artisan permissions:sync` creates the matching `Permission` rows. This is the mechanism, agreed in the design spec Section 7.3, that replaces hand-maintained permission lists for all new modules going forward.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Console/SyncPermissionsTest.php`:

```php
<?php

use Spatie\Permission\Models\Permission;

it('creates a permission for every $this->authorize() call found in app/Http/Controllers', function () {
    $this->artisan('permissions:sync')->assertExitCode(0);

    expect(Permission::where('name', 'guru.view')->exists())->toBeTrue();
    expect(Permission::where('name', 'tahun-ajaran.activate')->exists())->toBeTrue();
});

it('reports permissions that exist in the database but are no longer referenced by any controller, without deleting them', function () {
    Permission::firstOrCreate(['name' => 'modul-lama.aksi-usang', 'guard_name' => 'web']);

    $this->artisan('permissions:sync')
        ->expectsOutputToContain('modul-lama.aksi-usang')
        ->assertExitCode(0);

    expect(Permission::where('name', 'modul-lama.aksi-usang')->exists())->toBeTrue();
});

it('is safe to run twice in a row without creating duplicates', function () {
    $this->artisan('permissions:sync')->assertExitCode(0);
    $countAfterFirstRun = Permission::where('name', 'guru.view')->count();

    $this->artisan('permissions:sync')->assertExitCode(0);
    $countAfterSecondRun = Permission::where('name', 'guru.view')->count();

    expect($countAfterFirstRun)->toBe(1);
    expect($countAfterSecondRun)->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Console/SyncPermissionsTest.php`
Expected: FAIL with `Command "permissions:sync" is not defined`

- [ ] **Step 3: Create the command**

Create `app/Console/Commands/SyncPermissions.php`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync';

    protected $description = 'Scan app/Http/Controllers for $this->authorize() calls and sync them into the permissions table';

    public function handle(): int
    {
        $found = $this->scanControllers();

        $createdCount = 0;
        foreach ($found as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            if ($permission->wasRecentlyCreated) {
                $this->info("Created permission: {$name}");
                $createdCount++;
            }
        }

        $stale = Permission::where('guard_name', 'web')
            ->pluck('name')
            ->reject(fn (string $name) => in_array($name, $found, true))
            ->values();

        if ($stale->isNotEmpty()) {
            $this->warn('Permissions in database but not referenced by any controller (not deleted automatically):');
            foreach ($stale as $name) {
                $this->line("  - {$name}");
            }
        }

        if ($createdCount === 0 && $stale->isEmpty()) {
            $this->info('Permissions already in sync.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function scanControllers(): array
    {
        $files = File::allFiles(app_path('Http/Controllers'));
        $names = [];

        foreach ($files as $file) {
            $contents = File::get($file->getPathname());

            if (preg_match_all('/\$this->authorize\(\s*[\'"]([a-z0-9\-]+\.[a-z0-9\-]+)[\'"]\s*\)/', $contents, $matches)) {
                foreach ($matches[1] as $match) {
                    $names[$match] = $match;
                }
            }
        }

        return array_values($names);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Console/SyncPermissionsTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/SyncPermissions.php tests/Feature/Console/SyncPermissionsTest.php
git commit -m "feat: add permissions:sync command to replace hand-maintained permission seeder"
```

---

### Task 6: Admin CRUD — Mata Pelajaran

**Files:**
- Create: `app/Http/Controllers/Admin/MataPelajaranController.php`
- Create: `resources/views/admin/mata-pelajaran/index.blade.php`
- Create: `resources/views/admin/mata-pelajaran/create.blade.php`
- Create: `resources/views/admin/mata-pelajaran/edit.blade.php`
- Modify: `routes/admin.php`
- Modify: `resources/views/layouts/sidebar.blade.php`
- Test: `tests/Feature/Admin/MataPelajaranCrudTest.php`

**Interfaces:**
- Consumes: `App\Models\MataPelajaran` (Task 2), `App\Enums\TipeMataPelajaran` (Task 1), route helper `admin.mata-pelajaran.*`.
- Produces: Routes `admin.mata-pelajaran.index/create/store/edit/update`, permissions `mata-pelajaran.view`/`mata-pelajaran.create`/`mata-pelajaran.edit` (picked up by `permissions:sync`, Task 5). Tahap 4 (Jadwal) and Tahap 6 (Asesmen) reference `MataPelajaran` records created through this UI.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/MataPelajaranCrudTest.php`:

```php
<?php

use App\Enums\TipeMataPelajaran;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsMataPelajaranManager(Lembaga $lembaga): User
{
    foreach (['mata-pelajaran.view', 'mata-pelajaran.create', 'mata-pelajaran.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['mata-pelajaran.view', 'mata-pelajaran.create', 'mata-pelajaran.edit']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('denies access to a user without mata-pelajaran.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.mata-pelajaran.index'))->assertForbidden();
});

it('creates a mata pelajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsMataPelajaranManager($lembaga);

    $this->actingAs($manager)->post(route('admin.mata-pelajaran.store'), [
        'nama' => 'Matematika',
        'tipe' => TipeMataPelajaran::Mapel->value,
    ])->assertRedirect(route('admin.mata-pelajaran.index'));

    expect(MataPelajaran::where('nama', 'Matematika')->exists())->toBeTrue();
});

it('only lists mata pelajaran belonging to the acting manager\'s own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsMataPelajaranManager($lembagaA);

    MataPelajaran::create(['lembaga_id' => $lembagaA->id, 'nama' => 'Mapel Lembaga A', 'tipe' => TipeMataPelajaran::Mapel->value]);
    MataPelajaran::withoutGlobalScopes()->create(['lembaga_id' => $lembagaB->id, 'nama' => 'Mapel Lembaga B', 'tipe' => TipeMataPelajaran::Mapel->value]);

    $response = $this->actingAs($manager)->get(route('admin.mata-pelajaran.index'));

    $response->assertSee('Mapel Lembaga A');
    $response->assertDontSee('Mapel Lembaga B');
});

it('updates a mata pelajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsMataPelajaranManager($lembaga);
    $mapel = MataPelajaran::create(['lembaga_id' => $lembaga->id, 'nama' => 'IPA', 'tipe' => TipeMataPelajaran::Mapel->value]);

    $this->actingAs($manager)->put(route('admin.mata-pelajaran.update', $mapel), [
        'nama' => 'Ilmu Pengetahuan Alam',
        'tipe' => TipeMataPelajaran::Mapel->value,
    ])->assertRedirect(route('admin.mata-pelajaran.index'));

    expect($mapel->fresh()->nama)->toBe('Ilmu Pengetahuan Alam');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/MataPelajaranCrudTest.php`
Expected: FAIL with route `admin.mata-pelajaran.index` not defined.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Admin/MataPelajaranController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\MataPelajaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class MataPelajaranController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('mata-pelajaran.view');

        return view('admin.mata-pelajaran.index', [
            'mataPelajaranList' => MataPelajaran::orderBy('nama')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('mata-pelajaran.create');

        return view('admin.mata-pelajaran.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('mata-pelajaran.create');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'tipe' => ['required', 'in:mapel,aspek_perkembangan'],
        ]);

        if ($request->user()->widestScopeLevel() === 'yayasan') {
            $lembagaId = session('active_lembaga_id');

            if ($lembagaId === null) {
                return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat mata pelajaran.'])->withInput();
            }

            $data['lembaga_id'] = $lembagaId;
        }

        MataPelajaran::create($data);

        return redirect()->route('admin.mata-pelajaran.index')->with('status', 'Mata pelajaran berhasil disimpan.');
    }

    public function edit(MataPelajaran $mataPelajaran): View
    {
        $this->authorize('mata-pelajaran.edit');

        return view('admin.mata-pelajaran.edit', ['mataPelajaran' => $mataPelajaran]);
    }

    public function update(Request $request, MataPelajaran $mataPelajaran): RedirectResponse
    {
        $this->authorize('mata-pelajaran.edit');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'tipe' => ['required', 'in:mapel,aspek_perkembangan'],
        ]);

        $mataPelajaran->update($data);

        return redirect()->route('admin.mata-pelajaran.index')->with('status', 'Mata pelajaran berhasil diperbarui.');
    }
}
```

- [ ] **Step 4: Add routes**

In `routes/admin.php`, alongside the existing `guru`/`tahun-ajaran` block, add:

```php
Route::resource('mata-pelajaran', MataPelajaranController::class)->except(['show', 'destroy']);
```

Add the corresponding `use App\Http\Controllers\Admin\MataPelajaranController;` import at the top of `routes/admin.php` next to the other Admin controller imports.

- [ ] **Step 5: Create the views**

Create `resources/views/admin/mata-pelajaran/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Data Induk</p>
                <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Mata Pelajaran</h2>
            </div>
            <x-link-button href="{{ route('admin.mata-pelajaran.create') }}">
                <span class="text-base leading-none">+</span> Tambah Mata Pelajaran
            </x-link-button>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status'))
            <div class="rounded-xl bg-signal-green/10 p-4 text-sm text-signal-green">{{ session('status') }}</div>
        @endif

        <x-panel>
            <ul class="divide-y divide-ink/10">
                @forelse ($mataPelajaranList as $mapel)
                    <li class="flex items-center justify-between px-6 py-4">
                        <div>
                            <p class="text-sm font-medium text-ink">{{ $mapel->nama }}</p>
                            <p class="text-xs text-ink/60">{{ $mapel->tipe->label() }}</p>
                        </div>
                        <a href="{{ route('admin.mata-pelajaran.edit', $mapel) }}" class="text-sm font-medium text-ink hover:text-brass">Ubah</a>
                    </li>
                @empty
                    <li class="px-6 py-8 text-center text-sm text-ink/60">Belum ada mata pelajaran.</li>
                @endforelse
            </ul>
        </x-panel>
    </div>
</x-app-layout>
```

Create `resources/views/admin/mata-pelajaran/create.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-2xl font-semibold text-ink">Tambah Mata Pelajaran</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <x-panel>
            <form method="POST" action="{{ route('admin.mata-pelajaran.store') }}" class="space-y-4 p-6">
                @csrf

                <div>
                    <label class="text-sm font-medium text-ink">Nama</label>
                    <input type="text" name="nama" value="{{ old('nama') }}" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                    @error('nama')
                        <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-ink">Tipe</label>
                    <select name="tipe" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        <option value="mapel" @selected(old('tipe') === 'mapel')>Mata Pelajaran</option>
                        <option value="aspek_perkembangan" @selected(old('tipe') === 'aspek_perkembangan')>Aspek Perkembangan (PAUD)</option>
                    </select>
                    @error('tipe')
                        <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="rounded-xl bg-ink px-4 py-2 text-sm font-medium text-paper transition hover:bg-ink/90">Simpan</button>
            </form>
        </x-panel>
    </div>
</x-app-layout>
```

Create `resources/views/admin/mata-pelajaran/edit.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-2xl font-semibold text-ink">Ubah Mata Pelajaran</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <x-panel>
            <form method="POST" action="{{ route('admin.mata-pelajaran.update', $mataPelajaran) }}" class="space-y-4 p-6">
                @csrf
                @method('PUT')

                <div>
                    <label class="text-sm font-medium text-ink">Nama</label>
                    <input type="text" name="nama" value="{{ old('nama', $mataPelajaran->nama) }}" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                    @error('nama')
                        <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-ink">Tipe</label>
                    <select name="tipe" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        <option value="mapel" @selected(old('tipe', $mataPelajaran->tipe->value) === 'mapel')>Mata Pelajaran</option>
                        <option value="aspek_perkembangan" @selected(old('tipe', $mataPelajaran->tipe->value) === 'aspek_perkembangan')>Aspek Perkembangan (PAUD)</option>
                    </select>
                    @error('tipe')
                        <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="rounded-xl bg-ink px-4 py-2 text-sm font-medium text-paper transition hover:bg-ink/90">Simpan Perubahan</button>
            </form>
        </x-panel>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Add sidebar entry**

In `resources/views/layouts/sidebar.blade.php`, inside the `'II. Data Induk'` group's `array_filter([...])`, add this line alongside the existing `guru.view`/`tahun-ajaran.view` entries:

```php
Auth::user()->can('mata-pelajaran.view') ? ['route' => 'admin.mata-pelajaran.index', 'pattern' => 'admin.mata-pelajaran.*', 'label' => 'Mata Pelajaran', 'icon' => 'menu_book'] : null,
```

- [ ] **Step 7: Sync permissions**

Run: `php artisan permissions:sync`
Expected: Output includes `Created permission: mata-pelajaran.view`, `Created permission: mata-pelajaran.create`, `Created permission: mata-pelajaran.edit`.

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test tests/Feature/Admin/MataPelajaranCrudTest.php`
Expected: PASS (4 tests)

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Admin/MataPelajaranController.php resources/views/admin/mata-pelajaran routes/admin.php resources/views/layouts/sidebar.blade.php tests/Feature/Admin/MataPelajaranCrudTest.php
git commit -m "feat: add Mata Pelajaran admin CRUD"
```

---

### Task 7: Admin CRUD — Kelas

**Files:**
- Create: `app/Http/Controllers/Admin/KelasController.php`
- Create: `resources/views/admin/kelas/index.blade.php`
- Create: `resources/views/admin/kelas/create.blade.php`
- Create: `resources/views/admin/kelas/edit.blade.php`
- Modify: `routes/admin.php`
- Modify: `resources/views/layouts/sidebar.blade.php`
- Test: `tests/Feature/Admin/KelasCrudTest.php`

**Interfaces:**
- Consumes: `App\Models\Kelas` (Task 3), `App\Models\TahunAjaran`, `App\Models\Guru` (existing).
- Produces: Routes `admin.kelas.index/create/store/edit/update`, permissions `kelas.view`/`kelas.create`/`kelas.edit`. Task 8 (Siswa CRUD, below) and Tahap 2 (SPMB batch conversion) both use `Kelas` records created through this UI as the dropdown source for placing a `Siswa`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/KelasCrudTest.php`:

```php
<?php

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsKelasManager(Lembaga $lembaga): User
{
    foreach (['kelas.view', 'kelas.create', 'kelas.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kelas.view', 'kelas.create', 'kelas.edit']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('denies access to a user without kelas.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.kelas.index'))->assertForbidden();
});

it('creates a kelas', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKelasManager($lembaga);

    $this->actingAs($manager)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => '6A',
        'tingkat' => '6',
    ])->assertRedirect(route('admin.kelas.index'));

    expect(Kelas::where('nama', '6A')->exists())->toBeTrue();
});

it('offers only guru belonging to the current lembaga as wali kelas options', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKelasManager($lembagaA);

    $guruA = Guru::factory()->create(['lembaga_id' => $lembagaA->id]);
    Guru::withoutGlobalScopes()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembagaB->id])->id,
        'lembaga_id' => $lembagaB->id,
        'nik' => '3201234567891111',
        'nama' => 'Guru Lembaga B',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);

    $response = $this->actingAs($manager)->get(route('admin.kelas.create'));

    $response->assertViewHas('guruList', function ($guruList) use ($guruA) {
        return $guruList->count() === 1 && $guruList->first()->id === $guruA->id;
    });
});

it('updates a kelas including assigning a wali kelas', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKelasManager($lembaga);
    $kelas = Kelas::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '6A']);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->put(route('admin.kelas.update', $kelas), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => '6A',
        'tingkat' => '6',
        'wali_kelas_guru_id' => $guru->id,
    ])->assertRedirect(route('admin.kelas.index'));

    expect($kelas->fresh()->wali_kelas_guru_id)->toBe($guru->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/KelasCrudTest.php`
Expected: FAIL with route `admin.kelas.index` not defined.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Admin/KelasController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class KelasController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('kelas.view');

        return view('admin.kelas.index', [
            'kelasList' => Kelas::with(['tahunAjaran', 'waliKelas'])->orderBy('nama')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('kelas.create');

        return view('admin.kelas.create', [
            'tahunAjaranList' => TahunAjaran::orderByDesc('tanggal_mulai')->get(),
            'guruList' => Guru::orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('kelas.create');

        $data = $request->validate([
            'tahun_ajaran_id' => ['required', 'exists:tahun_ajaran,id'],
            'nama' => ['required', 'string', 'max:255'],
            'tingkat' => ['nullable', 'string', 'max:20'],
            'wali_kelas_guru_id' => ['nullable', 'exists:guru,id'],
        ]);

        if ($request->user()->widestScopeLevel() === 'yayasan') {
            $lembagaId = session('active_lembaga_id');

            if ($lembagaId === null) {
                return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat kelas.'])->withInput();
            }

            $data['lembaga_id'] = $lembagaId;
        }

        Kelas::create($data);

        return redirect()->route('admin.kelas.index')->with('status', 'Kelas berhasil disimpan.');
    }

    public function edit(Kelas $kelas): View
    {
        $this->authorize('kelas.edit');

        return view('admin.kelas.edit', [
            'kelas' => $kelas,
            'tahunAjaranList' => TahunAjaran::orderByDesc('tanggal_mulai')->get(),
            'guruList' => Guru::orderBy('nama')->get(),
        ]);
    }

    public function update(Request $request, Kelas $kelas): RedirectResponse
    {
        $this->authorize('kelas.edit');

        $data = $request->validate([
            'tahun_ajaran_id' => ['required', 'exists:tahun_ajaran,id'],
            'nama' => ['required', 'string', 'max:255'],
            'tingkat' => ['nullable', 'string', 'max:20'],
            'wali_kelas_guru_id' => ['nullable', 'exists:guru,id'],
        ]);

        $kelas->update($data);

        return redirect()->route('admin.kelas.index')->with('status', 'Kelas berhasil diperbarui.');
    }
}
```

- [ ] **Step 4: Add routes**

In `routes/admin.php`, add alongside `mata-pelajaran`:

```php
Route::resource('kelas', KelasController::class)->except(['show', 'destroy']);
```

Add `use App\Http\Controllers\Admin\KelasController;` at the top.

- [ ] **Step 5: Create the views**

Create `resources/views/admin/kelas/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Data Induk</p>
                <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Kelas</h2>
            </div>
            <x-link-button href="{{ route('admin.kelas.create') }}">
                <span class="text-base leading-none">+</span> Tambah Kelas
            </x-link-button>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status'))
            <div class="rounded-xl bg-signal-green/10 p-4 text-sm text-signal-green">{{ session('status') }}</div>
        @endif

        <x-panel>
            <ul class="divide-y divide-ink/10">
                @forelse ($kelasList as $kelas)
                    <li class="flex items-center justify-between px-6 py-4">
                        <div>
                            <p class="text-sm font-medium text-ink">{{ $kelas->nama }}</p>
                            <p class="text-xs text-ink/60">
                                {{ $kelas->tahunAjaran->nama }}
                                @if ($kelas->waliKelas)
                                    &middot; Wali Kelas: {{ $kelas->waliKelas->nama }}
                                @endif
                            </p>
                        </div>
                        <a href="{{ route('admin.kelas.edit', $kelas) }}" class="text-sm font-medium text-ink hover:text-brass">Ubah</a>
                    </li>
                @empty
                    <li class="px-6 py-8 text-center text-sm text-ink/60">Belum ada kelas.</li>
                @endforelse
            </ul>
        </x-panel>
    </div>
</x-app-layout>
```

Create `resources/views/admin/kelas/create.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-2xl font-semibold text-ink">Tambah Kelas</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <x-panel>
            <form method="POST" action="{{ route('admin.kelas.store') }}" class="space-y-4 p-6">
                @csrf

                <div>
                    <label class="text-sm font-medium text-ink">Tahun Ajaran</label>
                    <select name="tahun_ajaran_id" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        @foreach ($tahunAjaranList as $tahunAjaran)
                            <option value="{{ $tahunAjaran->id }}" @selected(old('tahun_ajaran_id') == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
                        @endforeach
                    </select>
                    @error('tahun_ajaran_id')
                        <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-ink">Nama Kelas</label>
                    <input type="text" name="nama" value="{{ old('nama') }}" placeholder="6A, Kelompok A, XI IPA 2" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                    @error('nama')
                        <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-ink">Tingkat (opsional)</label>
                    <input type="text" name="tingkat" value="{{ old('tingkat') }}" placeholder="6, XI, kosongkan utk PAUD" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                    @error('tingkat')
                        <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-ink">Wali Kelas (opsional)</label>
                    <select name="wali_kelas_guru_id" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        <option value="">— Belum ditentukan —</option>
                        @foreach ($guruList as $guru)
                            <option value="{{ $guru->id }}" @selected(old('wali_kelas_guru_id') == $guru->id)>{{ $guru->nama }}</option>
                        @endforeach
                    </select>
                    @error('wali_kelas_guru_id')
                        <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="rounded-xl bg-ink px-4 py-2 text-sm font-medium text-paper transition hover:bg-ink/90">Simpan</button>
            </form>
        </x-panel>
    </div>
</x-app-layout>
```

Create `resources/views/admin/kelas/edit.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-2xl font-semibold text-ink">Ubah Kelas</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <x-panel>
            <form method="POST" action="{{ route('admin.kelas.update', $kelas) }}" class="space-y-4 p-6">
                @csrf
                @method('PUT')

                <div>
                    <label class="text-sm font-medium text-ink">Tahun Ajaran</label>
                    <select name="tahun_ajaran_id" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        @foreach ($tahunAjaranList as $tahunAjaran)
                            <option value="{{ $tahunAjaran->id }}" @selected(old('tahun_ajaran_id', $kelas->tahun_ajaran_id) == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
                        @endforeach
                    </select>
                    @error('tahun_ajaran_id')
                        <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-ink">Nama Kelas</label>
                    <input type="text" name="nama" value="{{ old('nama', $kelas->nama) }}" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                    @error('nama')
                        <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-ink">Tingkat (opsional)</label>
                    <input type="text" name="tingkat" value="{{ old('tingkat', $kelas->tingkat) }}" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                    @error('tingkat')
                        <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-ink">Wali Kelas (opsional)</label>
                    <select name="wali_kelas_guru_id" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        <option value="">— Belum ditentukan —</option>
                        @foreach ($guruList as $guru)
                            <option value="{{ $guru->id }}" @selected(old('wali_kelas_guru_id', $kelas->wali_kelas_guru_id) == $guru->id)>{{ $guru->nama }}</option>
                        @endforeach
                    </select>
                    @error('wali_kelas_guru_id')
                        <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="rounded-xl bg-ink px-4 py-2 text-sm font-medium text-paper transition hover:bg-ink/90">Simpan Perubahan</button>
            </form>
        </x-panel>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Add sidebar entry**

In `resources/views/layouts/sidebar.blade.php`, inside the `'II. Data Induk'` group, add:

```php
Auth::user()->can('kelas.view') ? ['route' => 'admin.kelas.index', 'pattern' => 'admin.kelas.*', 'label' => 'Kelas', 'icon' => 'meeting_room'] : null,
```

- [ ] **Step 7: Sync permissions**

Run: `php artisan permissions:sync`
Expected: Output includes `Created permission: kelas.view`, `Created permission: kelas.create`, `Created permission: kelas.edit`.

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test tests/Feature/Admin/KelasCrudTest.php`
Expected: PASS (4 tests)

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Admin/KelasController.php resources/views/admin/kelas routes/admin.php resources/views/layouts/sidebar.blade.php tests/Feature/Admin/KelasCrudTest.php
git commit -m "feat: add Kelas admin CRUD"
```

---

### Task 8: Admin CRUD — Siswa (manual entry only)

**Files:**
- Create: `app/Http/Controllers/Admin/SiswaController.php`
- Create: `resources/views/admin/siswa/index.blade.php`
- Create: `resources/views/admin/siswa/create.blade.php`
- Create: `resources/views/admin/siswa/edit.blade.php`
- Modify: `routes/admin.php`
- Modify: `resources/views/layouts/sidebar.blade.php`
- Test: `tests/Feature/Admin/SiswaCrudTest.php`

**Interfaces:**
- Consumes: `App\Models\Siswa`, `App\Enums\SumberDataSiswa` (Task 4/1), `App\Models\Kelas` (Task 3).
- Produces: Routes `admin.siswa.index/create/store/edit/update`, permissions `siswa.view`/`siswa.create`/`siswa.edit`. Every `Siswa` created through this controller has `sumber_data` hardcoded to `SumberDataSiswa::Manual`. Tahap 2 adds two more entry points (`admin.siswa.spmb-daftar` batch action, `admin.siswa.import`) into the SAME `Siswa` model/table — it does not touch this controller's `store`/`update` actions.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/SiswaCrudTest.php`:

```php
<?php

use App\Enums\SumberDataSiswa;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsSiswaManager(Lembaga $lembaga): User
{
    foreach (['siswa.view', 'siswa.create', 'siswa.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['siswa.view', 'siswa.create', 'siswa.edit']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('denies access to a user without siswa.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.siswa.index'))->assertForbidden();
});

it('creates a siswa manually with sumber_data forced to manual', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $manager = actingAsSiswaManager($lembaga);

    $this->actingAs($manager)->post(route('admin.siswa.store'), [
        'kelas_id' => $kelas->id,
        'nis' => '2026001',
        'nisn' => '0012345678',
        'nama_lengkap' => 'Budi Santoso',
        'jenis_kelamin' => 'L',
        'tempat_lahir' => 'Jakarta',
        'tanggal_lahir' => '2015-03-10',
        'agama' => 'Islam',
    ])->assertRedirect(route('admin.siswa.index'));

    $siswa = Siswa::where('nis', '2026001')->firstOrFail();
    expect($siswa->sumber_data)->toBe(SumberDataSiswa::Manual);
    expect($siswa->kelas_id)->toBe($kelas->id);
});

it('rejects a duplicate NIS within the same lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsSiswaManager($lembaga);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nis' => '2026001']);

    $this->actingAs($manager)->post(route('admin.siswa.store'), [
        'nis' => '2026001',
        'nama_lengkap' => 'Siswa Kedua',
        'jenis_kelamin' => 'P',
    ])->assertSessionHasErrors('nis');
});

it('only lists siswa belonging to the acting manager\'s own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsSiswaManager($lembagaA);

    Siswa::factory()->create(['lembaga_id' => $lembagaA->id, 'nama_lengkap' => 'Siswa Lembaga A']);
    Siswa::withoutGlobalScopes()->create(array_merge(
        Siswa::factory()->raw(),
        ['lembaga_id' => $lembagaB->id, 'nama_lengkap' => 'Siswa Lembaga B', 'nis' => '9999999']
    ));

    $response = $this->actingAs($manager)->get(route('admin.siswa.index'));

    $response->assertSee('Siswa Lembaga A');
    $response->assertDontSee('Siswa Lembaga B');
});

it('updates a siswa', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsSiswaManager($lembaga);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nis' => '2026005']);

    $this->actingAs($manager)->put(route('admin.siswa.update', $siswa), [
        'nis' => '2026005',
        'nama_lengkap' => 'Nama Diperbarui',
        'jenis_kelamin' => 'L',
    ])->assertRedirect(route('admin.siswa.index'));

    expect($siswa->fresh()->nama_lengkap)->toBe('Nama Diperbarui');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/SiswaCrudTest.php`
Expected: FAIL with route `admin.siswa.index` not defined.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Admin/SiswaController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SumberDataSiswa;
use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class SiswaController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('siswa.view');

        return view('admin.siswa.index', [
            'siswaList' => Siswa::with('kelas')->orderBy('nama_lengkap')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('siswa.create');

        return view('admin.siswa.create', [
            'kelasList' => Kelas::orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('siswa.create');

        $data = $this->validateSiswa($request);
        $data['sumber_data'] = SumberDataSiswa::Manual->value;

        if ($request->user()->widestScopeLevel() === 'yayasan') {
            $lembagaId = session('active_lembaga_id');

            if ($lembagaId === null) {
                return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah siswa.'])->withInput();
            }

            $data['lembaga_id'] = $lembagaId;
        }

        Siswa::create($data);

        return redirect()->route('admin.siswa.index')->with('status', 'Siswa berhasil disimpan.');
    }

    public function edit(Siswa $siswa): View
    {
        $this->authorize('siswa.edit');

        return view('admin.siswa.edit', [
            'siswa' => $siswa,
            'kelasList' => Kelas::orderBy('nama')->get(),
        ]);
    }

    public function update(Request $request, Siswa $siswa): RedirectResponse
    {
        $this->authorize('siswa.edit');

        $data = $this->validateSiswa($request, $siswa);

        $siswa->update($data);

        return redirect()->route('admin.siswa.index')->with('status', 'Siswa berhasil diperbarui.');
    }

    private function validateSiswa(Request $request, ?Siswa $current = null): array
    {
        return $request->validate([
            'kelas_id' => ['nullable', 'exists:kelas,id'],
            'nis' => [
                'required', 'string', 'max:30',
                function ($attribute, $value, $fail) use ($current) {
                    $exists = Siswa::withoutGlobalScopes()
                        ->where('lembaga_id', $current?->lembaga_id ?? auth()->user()->lembaga_id ?? session('active_lembaga_id'))
                        ->where('nis', $value)
                        ->when($current, fn ($query) => $query->where('id', '!=', $current->id))
                        ->exists();
                    if ($exists) {
                        $fail('NIS sudah dipakai siswa lain di lembaga ini.');
                    }
                },
            ],
            'nisn' => ['nullable', 'string', 'max:20'],
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'tempat_lahir' => ['nullable', 'string', 'max:255'],
            'tanggal_lahir' => ['nullable', 'date'],
            'agama' => ['nullable', 'string', 'max:50'],
        ]);
    }
}
```

- [ ] **Step 4: Add routes**

In `routes/admin.php`, add alongside `kelas`:

```php
Route::resource('siswa', SiswaController::class)->except(['show', 'destroy']);
```

Add `use App\Http\Controllers\Admin\SiswaController;` at the top.

- [ ] **Step 5: Create the views**

Create `resources/views/admin/siswa/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Data Induk</p>
                <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Siswa</h2>
            </div>
            <x-link-button href="{{ route('admin.siswa.create') }}">
                <span class="text-base leading-none">+</span> Tambah Siswa
            </x-link-button>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-6">
        @if (session('status'))
            <div class="rounded-xl bg-signal-green/10 p-4 text-sm text-signal-green">{{ session('status') }}</div>
        @endif

        <x-panel>
            <ul class="divide-y divide-ink/10">
                @forelse ($siswaList as $siswa)
                    <li class="flex items-center justify-between px-6 py-4">
                        <div>
                            <p class="text-sm font-medium text-ink">{{ $siswa->nama_lengkap }}</p>
                            <p class="text-xs text-ink/60">
                                NIS {{ $siswa->nis }}
                                @if ($siswa->kelas)
                                    &middot; {{ $siswa->kelas->nama }}
                                @else
                                    &middot; Belum ditempatkan
                                @endif
                                &middot; {{ $siswa->sumber_data->label() }}
                            </p>
                        </div>
                        <a href="{{ route('admin.siswa.edit', $siswa) }}" class="text-sm font-medium text-ink hover:text-brass">Ubah</a>
                    </li>
                @empty
                    <li class="px-6 py-8 text-center text-sm text-ink/60">Belum ada siswa.</li>
                @endforelse
            </ul>
        </x-panel>
    </div>
</x-app-layout>
```

Create `resources/views/admin/siswa/create.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-2xl font-semibold text-ink">Tambah Siswa</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <x-panel>
            <form method="POST" action="{{ route('admin.siswa.store') }}" class="space-y-4 p-6">
                @csrf

                <div>
                    <label class="text-sm font-medium text-ink">Kelas (opsional)</label>
                    <select name="kelas_id" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        <option value="">— Belum ditempatkan —</option>
                        @foreach ($kelasList as $kelas)
                            <option value="{{ $kelas->id }}" @selected(old('kelas_id') == $kelas->id)>{{ $kelas->nama }}</option>
                        @endforeach
                    </select>
                    @error('kelas_id')
                        <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-ink">NIS</label>
                        <input type="text" name="nis" value="{{ old('nis') }}" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        @error('nis')
                            <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium text-ink">NISN</label>
                        <input type="text" name="nisn" value="{{ old('nisn') }}" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        @error('nisn')
                            <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium text-ink">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" value="{{ old('nama_lengkap') }}" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                    @error('nama_lengkap')
                        <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-ink">Jenis Kelamin</label>
                        <select name="jenis_kelamin" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                            <option value="L" @selected(old('jenis_kelamin') === 'L')>Laki-laki</option>
                            <option value="P" @selected(old('jenis_kelamin') === 'P')>Perempuan</option>
                        </select>
                        @error('jenis_kelamin')
                            <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium text-ink">Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" value="{{ old('tanggal_lahir') }}" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        @error('tanggal_lahir')
                            <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-ink">Tempat Lahir</label>
                        <input type="text" name="tempat_lahir" value="{{ old('tempat_lahir') }}" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        @error('tempat_lahir')
                            <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium text-ink">Agama</label>
                        <input type="text" name="agama" value="{{ old('agama') }}" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        @error('agama')
                            <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <button type="submit" class="rounded-xl bg-ink px-4 py-2 text-sm font-medium text-paper transition hover:bg-ink/90">Simpan</button>
            </form>
        </x-panel>
    </div>
</x-app-layout>
```

Create `resources/views/admin/siswa/edit.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-2xl font-semibold text-ink">Ubah Siswa</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <x-panel>
            <form method="POST" action="{{ route('admin.siswa.update', $siswa) }}" class="space-y-4 p-6">
                @csrf
                @method('PUT')

                <div>
                    <label class="text-sm font-medium text-ink">Kelas (opsional)</label>
                    <select name="kelas_id" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        <option value="">— Belum ditempatkan —</option>
                        @foreach ($kelasList as $kelas)
                            <option value="{{ $kelas->id }}" @selected(old('kelas_id', $siswa->kelas_id) == $kelas->id)>{{ $kelas->nama }}</option>
                        @endforeach
                    </select>
                    @error('kelas_id')
                        <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-ink">NIS</label>
                        <input type="text" name="nis" value="{{ old('nis', $siswa->nis) }}" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        @error('nis')
                            <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium text-ink">NISN</label>
                        <input type="text" name="nisn" value="{{ old('nisn', $siswa->nisn) }}" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        @error('nisn')
                            <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium text-ink">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" value="{{ old('nama_lengkap', $siswa->nama_lengkap) }}" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                    @error('nama_lengkap')
                        <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-ink">Jenis Kelamin</label>
                        <select name="jenis_kelamin" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                            <option value="L" @selected(old('jenis_kelamin', $siswa->jenis_kelamin) === 'L')>Laki-laki</option>
                            <option value="P" @selected(old('jenis_kelamin', $siswa->jenis_kelamin) === 'P')>Perempuan</option>
                        </select>
                        @error('jenis_kelamin')
                            <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium text-ink">Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" value="{{ old('tanggal_lahir', optional($siswa->tanggal_lahir)->format('Y-m-d')) }}" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        @error('tanggal_lahir')
                            <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-ink">Tempat Lahir</label>
                        <input type="text" name="tempat_lahir" value="{{ old('tempat_lahir', $siswa->tempat_lahir) }}" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        @error('tempat_lahir')
                            <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium text-ink">Agama</label>
                        <input type="text" name="agama" value="{{ old('agama', $siswa->agama) }}" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        @error('agama')
                            <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <button type="submit" class="rounded-xl bg-ink px-4 py-2 text-sm font-medium text-paper transition hover:bg-ink/90">Simpan Perubahan</button>
            </form>
        </x-panel>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Add sidebar entry**

In `resources/views/layouts/sidebar.blade.php`, inside the `'II. Data Induk'` group, add:

```php
Auth::user()->can('siswa.view') ? ['route' => 'admin.siswa.index', 'pattern' => 'admin.siswa.*', 'label' => 'Siswa', 'icon' => 'groups'] : null,
```

- [ ] **Step 7: Sync permissions**

Run: `php artisan permissions:sync`
Expected: Output includes `Created permission: siswa.view`, `Created permission: siswa.create`, `Created permission: siswa.edit`.

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test tests/Feature/Admin/SiswaCrudTest.php`
Expected: PASS (5 tests)

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Admin/SiswaController.php resources/views/admin/siswa routes/admin.php resources/views/layouts/sidebar.blade.php tests/Feature/Admin/SiswaCrudTest.php
git commit -m "feat: add Siswa admin CRUD (manual entry)"
```

---

## Plan Self-Review Notes

- **Spec coverage**: This plan covers spec Section 1 (Fondasi Data Akademik) for the `Siswa`/`Kelas`/`MataPelajaran` models and manual-entry CRUD, plus the RBAC infrastructure improvement from Section 7.3 (`permissions:sync`). It deliberately does **not** cover: SPMB batch conversion, Excel import (both spec Section 1.1, deferred to Tahap 2), or `pola_jam_id` on `Kelas` (spec Section 3, deferred to Tahap 4's migration).
- **Dependency note for Tahap 2**: the SPMB→Siswa batch conversion plan will add a new controller action (not modify `SiswaController::store`), consuming `Siswa::create()` with `sumber_data = SumberDataSiswa::Spmb->value` and populating `calon_murid_id`/`pendaftaran_asal_id` — both columns already exist on the `siswa` table from Task 4.
- **Dependency note for Tahap 4**: adding `pola_jam_id` to `kelas` will be a new `Schema::table('kelas', ...)` migration, not a modification of Task 3's `create_kelas_table` migration.
