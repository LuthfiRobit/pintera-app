# M1: SPMB — Konfigurasi Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the admin configuration panel for SPMB/PPDB: Gelombang, Jalur (with Formulir Field, Dokumen Syarat, Seleksi/Tes as nested config), a lembaga-level Jenis Tes master, and a year-to-year config duplication feature.

**Architecture:** Server-rendered Blade + Alpine.js (no Livewire), following the exact conventions established in M0 (`TahunAjaran`/`Semester` nested-resource pattern, `BelongsToTenant` trait + `TenantScope` global scope, `spatie/laravel-activitylog` for audit trail, Pest for tests). Six new tables, five new controllers, one new service class for duplication.

**Tech Stack:** Laravel 12, Blade, Alpine.js, Tailwind CSS, Spatie Permission, Spatie Activitylog, Pest.

## Global Constraints

- DB naming convention: Bahasa Indonesia, matching PRD terms (`gelombang_ppdb`, `jalur_ppdb`, etc.) — from `docs/superpowers/specs/2026-07-12-m0-fondasi-design.md`.
- All new tenant-scoped tables use the existing `App\Models\Concerns\BelongsToTenant` trait — never write manual `lembaga_id` `where()` clauses in queries.
- Any validation rule referencing another tenant-scoped table's ID must use `Illuminate\Validation\Rule::exists(...)->where(...)` scoped to the acting user's lembaga (and tahun ajaran where relevant) — never a bare `exists:table,id` string rule. This is a deliberate improvement over the existing `SemesterController::store()` validation, called out in the design spec section 6.
- `gelombang_ppdb` and `jalur_ppdb` are snapshot-per-`tahun_ajaran_id` — never make them tahun-ajaran-independent master data (see spec section 2.1 for why).
- `jenis_tes_master` is the one exception: scoped to `lembaga_id` only, reused across tahun ajaran, never duplicated.
- New permission `manage-ppdb` gates every controller action in this plan.
- Reference spec: `docs/superpowers/specs/2026-07-13-spmb-m1-konfigurasi-design.md` — read it before starting if anything below is unclear.

---

## Task 1: Migrations & Models for the six SPMB tables

**Files:**
- Create: `database/migrations/2026_07_13_090000_create_jenis_tes_master_table.php`
- Create: `database/migrations/2026_07_13_090100_create_gelombang_ppdb_table.php`
- Create: `database/migrations/2026_07_13_090200_create_jalur_ppdb_table.php`
- Create: `database/migrations/2026_07_13_090300_create_formulir_field_table.php`
- Create: `database/migrations/2026_07_13_090400_create_dokumen_syarat_ppdb_table.php`
- Create: `database/migrations/2026_07_13_090500_create_seleksi_ppdb_table.php`
- Create: `app/Models/JenisTesMaster.php`
- Create: `app/Models/GelombangPpdb.php`
- Create: `app/Models/JalurPpdb.php`
- Create: `app/Models/FormulirField.php`
- Create: `app/Models/DokumenSyaratPpdb.php`
- Create: `app/Models/SeleksiPpdb.php`
- Test: `tests/Feature/PpdbModelsTest.php`

**Interfaces:**
- Produces: `JenisTesMaster` (fillable: `lembaga_id, nama, deskripsi`), `GelombangPpdb` (fillable: `lembaga_id, tahun_ajaran_id, nama, tanggal_buka, tanggal_tutup, kuota`; relation `seleksi(): HasMany`), `JalurPpdb` (fillable: `lembaga_id, tahun_ajaran_id, nama, deskripsi, status_aktif`; relations `formulirField(): HasMany`, `dokumenSyarat(): HasMany`, `seleksi(): HasMany`), `FormulirField` (fillable: `jalur_ppdb_id, lembaga_id, label, field_type, options, is_required, urutan`), `DokumenSyaratPpdb` (fillable: `jalur_ppdb_id, lembaga_id, nama_dokumen, wajib, urutan`), `SeleksiPpdb` (fillable: `jalur_ppdb_id, gelombang_ppdb_id, lembaga_id, jenis_tes_master_id, jadwal, kriteria_kelulusan, bobot`). All six models live in `App\Models` and use `App\Models\Concerns\BelongsToTenant`.

- [ ] **Step 1: Write the migrations**

`database/migrations/2026_07_13_090000_create_jenis_tes_master_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_tes_master', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->timestamps();

            $table->unique(['lembaga_id', 'nama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jenis_tes_master');
    }
};
```

`database/migrations/2026_07_13_090100_create_gelombang_ppdb_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gelombang_ppdb', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->cascadeOnDelete();
            $table->string('nama');
            $table->date('tanggal_buka');
            $table->date('tanggal_tutup');
            $table->unsignedInteger('kuota');
            $table->timestamps();

            $table->unique(['tahun_ajaran_id', 'nama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gelombang_ppdb');
    }
};
```

`database/migrations/2026_07_13_090200_create_jalur_ppdb_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jalur_ppdb', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->cascadeOnDelete();
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();

            $table->unique(['tahun_ajaran_id', 'nama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jalur_ppdb');
    }
};
```

`database/migrations/2026_07_13_090300_create_formulir_field_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formulir_field', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jalur_ppdb_id')->constrained('jalur_ppdb')->cascadeOnDelete();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->string('label');
            $table->enum('field_type', ['text', 'textarea', 'number', 'date', 'select', 'file']);
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formulir_field');
    }
};
```

`database/migrations/2026_07_13_090400_create_dokumen_syarat_ppdb_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dokumen_syarat_ppdb', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jalur_ppdb_id')->constrained('jalur_ppdb')->cascadeOnDelete();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->string('nama_dokumen');
            $table->boolean('wajib')->default(true);
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen_syarat_ppdb');
    }
};
```

`database/migrations/2026_07_13_090500_create_seleksi_ppdb_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seleksi_ppdb', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jalur_ppdb_id')->constrained('jalur_ppdb')->cascadeOnDelete();
            $table->foreignId('gelombang_ppdb_id')->constrained('gelombang_ppdb')->cascadeOnDelete();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->foreignId('jenis_tes_master_id')->constrained('jenis_tes_master')->restrictOnDelete();
            $table->dateTime('jadwal');
            $table->text('kriteria_kelulusan')->nullable();
            $table->decimal('bobot', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seleksi_ppdb');
    }
};
```

- [ ] **Step 2: Run the migrations**

Run: `php artisan migrate`
Expected: all six migrations report `DONE`, no errors.

- [ ] **Step 3: Write the models**

`app/Models/JenisTesMaster.php`:

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisTesMaster extends Model
{
    use BelongsToTenant;

    protected $table = 'jenis_tes_master';

    protected $fillable = ['lembaga_id', 'nama', 'deskripsi'];

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function seleksi(): HasMany
    {
        return $this->hasMany(SeleksiPpdb::class, 'jenis_tes_master_id');
    }
}
```

`app/Models/GelombangPpdb.php`:

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class GelombangPpdb extends Model
{
    use BelongsToTenant, LogsActivity;

    protected $table = 'gelombang_ppdb';

    protected $fillable = ['lembaga_id', 'tahun_ajaran_id', 'nama', 'tanggal_buka', 'tanggal_tutup', 'kuota'];

    protected function casts(): array
    {
        return [
            'tanggal_buka' => 'date',
            'tanggal_tutup' => 'date',
        ];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function seleksi(): HasMany
    {
        return $this->hasMany(SeleksiPpdb::class, 'gelombang_ppdb_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nama', 'tanggal_buka', 'tanggal_tutup', 'kuota'])
            ->logOnlyDirty()
            ->useLogName('gelombang_ppdb');
    }
}
```

`app/Models/JalurPpdb.php`:

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class JalurPpdb extends Model
{
    use BelongsToTenant, LogsActivity;

    protected $table = 'jalur_ppdb';

    protected $fillable = ['lembaga_id', 'tahun_ajaran_id', 'nama', 'deskripsi', 'status_aktif'];

    protected function casts(): array
    {
        return [
            'status_aktif' => 'boolean',
        ];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function formulirField(): HasMany
    {
        return $this->hasMany(FormulirField::class, 'jalur_ppdb_id')->orderBy('urutan');
    }

    public function dokumenSyarat(): HasMany
    {
        return $this->hasMany(DokumenSyaratPpdb::class, 'jalur_ppdb_id')->orderBy('urutan');
    }

    public function seleksi(): HasMany
    {
        return $this->hasMany(SeleksiPpdb::class, 'jalur_ppdb_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nama', 'deskripsi', 'status_aktif'])
            ->logOnlyDirty()
            ->useLogName('jalur_ppdb');
    }
}
```

`app/Models/FormulirField.php`:

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormulirField extends Model
{
    use BelongsToTenant;

    protected $table = 'formulir_field';

    protected $fillable = ['jalur_ppdb_id', 'lembaga_id', 'label', 'field_type', 'options', 'is_required', 'urutan'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FormulirField $field) {
            if (empty($field->lembaga_id)) {
                $field->lembaga_id = JalurPpdb::withoutGlobalScopes()
                    ->findOrFail($field->jalur_ppdb_id)
                    ->lembaga_id;
            }
        });
    }

    public function jalurPpdb(): BelongsTo
    {
        return $this->belongsTo(JalurPpdb::class, 'jalur_ppdb_id');
    }
}
```

`app/Models/DokumenSyaratPpdb.php`:

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DokumenSyaratPpdb extends Model
{
    use BelongsToTenant;

    protected $table = 'dokumen_syarat_ppdb';

    protected $fillable = ['jalur_ppdb_id', 'lembaga_id', 'nama_dokumen', 'wajib', 'urutan'];

    protected function casts(): array
    {
        return [
            'wajib' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DokumenSyaratPpdb $dokumen) {
            if (empty($dokumen->lembaga_id)) {
                $dokumen->lembaga_id = JalurPpdb::withoutGlobalScopes()
                    ->findOrFail($dokumen->jalur_ppdb_id)
                    ->lembaga_id;
            }
        });
    }

    public function jalurPpdb(): BelongsTo
    {
        return $this->belongsTo(JalurPpdb::class, 'jalur_ppdb_id');
    }
}
```

`app/Models/SeleksiPpdb.php`:

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeleksiPpdb extends Model
{
    use BelongsToTenant;

    protected $table = 'seleksi_ppdb';

    protected $fillable = ['jalur_ppdb_id', 'gelombang_ppdb_id', 'lembaga_id', 'jenis_tes_master_id', 'jadwal', 'kriteria_kelulusan', 'bobot'];

    protected function casts(): array
    {
        return [
            'jadwal' => 'datetime',
            'bobot' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SeleksiPpdb $seleksi) {
            if (empty($seleksi->lembaga_id)) {
                $seleksi->lembaga_id = JalurPpdb::withoutGlobalScopes()
                    ->findOrFail($seleksi->jalur_ppdb_id)
                    ->lembaga_id;
            }
        });
    }

    public function jalurPpdb(): BelongsTo
    {
        return $this->belongsTo(JalurPpdb::class, 'jalur_ppdb_id');
    }

    public function gelombangPpdb(): BelongsTo
    {
        return $this->belongsTo(GelombangPpdb::class, 'gelombang_ppdb_id');
    }

    public function jenisTesMaster(): BelongsTo
    {
        return $this->belongsTo(JenisTesMaster::class, 'jenis_tes_master_id');
    }
}
```

- [ ] **Step 4: Write the failing test**

`tests/Feature/PpdbModelsTest.php`:

```php
<?php

use App\Models\DokumenSyaratPpdb;
use App\Models\FormulirField;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\SeleksiPpdb;
use App\Models\TahunAjaran;
use App\Models\GelombangPpdb;
use App\Models\JenisTesMaster;
use App\Models\User;
use App\Models\Yayasan;

function buatKonteksLembaga(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id,
        'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2027-06-30',
        'status_aktif' => true,
    ]);

    return [$lembaga, $user, $tahunAjaran];
}

it('copies lembaga_id from the parent jalur onto a new formulir field', function () {
    [$lembaga, $user, $tahunAjaran] = buatKonteksLembaga();
    test()->actingAs($user);

    $jalur = JalurPpdb::create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Reguler',
    ]);

    $field = FormulirField::create([
        'jalur_ppdb_id' => $jalur->id,
        'label' => 'Nomor Hafalan Juz',
        'field_type' => 'number',
    ]);

    expect($field->lembaga_id)->toBe($lembaga->id);
});

it('copies lembaga_id from the parent jalur onto a new dokumen syarat', function () {
    [$lembaga, $user, $tahunAjaran] = buatKonteksLembaga();
    test()->actingAs($user);

    $jalur = JalurPpdb::create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Tahfidz',
    ]);

    $dokumen = DokumenSyaratPpdb::create([
        'jalur_ppdb_id' => $jalur->id,
        'nama_dokumen' => 'Sertifikat Hafalan',
    ]);

    expect($dokumen->lembaga_id)->toBe($lembaga->id);
});

it('copies lembaga_id from the parent jalur onto a new seleksi row', function () {
    [$lembaga, $user, $tahunAjaran] = buatKonteksLembaga();
    test()->actingAs($user);

    $jalur = JalurPpdb::create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Prestasi',
    ]);

    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Gelombang 1',
        'tanggal_buka' => '2026-01-01',
        'tanggal_tutup' => '2026-02-01',
        'kuota' => 30,
    ]);

    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);

    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id,
        'gelombang_ppdb_id' => $gelombang->id,
        'jenis_tes_master_id' => $jenisTes->id,
        'jadwal' => '2026-01-15 09:00:00',
    ]);

    expect($seleksi->lembaga_id)->toBe($lembaga->id);
});

it('loads a jalur with its formulir field, dokumen syarat, and seleksi relations', function () {
    [$lembaga, $user, $tahunAjaran] = buatKonteksLembaga();
    test()->actingAs($user);

    $jalur = JalurPpdb::create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Afirmasi',
    ]);

    FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Alasan', 'field_type' => 'textarea']);
    DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Surat Keterangan Tidak Mampu']);

    expect($jalur->fresh()->formulirField)->toHaveCount(1);
    expect($jalur->fresh()->dokumenSyarat)->toHaveCount(1);
});
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=PpdbModelsTest`
Expected: 4 passed.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_13_090*.php app/Models/JenisTesMaster.php app/Models/GelombangPpdb.php app/Models/JalurPpdb.php app/Models/FormulirField.php app/Models/DokumenSyaratPpdb.php app/Models/SeleksiPpdb.php tests/Feature/PpdbModelsTest.php
git commit -m "feat: add SPMB konfigurasi tables and models (gelombang, jalur, formulir field, dokumen syarat, seleksi, jenis tes master)"
```

---

## Task 2: `manage-ppdb` permission

**Files:**
- Modify: `database/seeders/RolePermissionSeeder.php`
- Modify: `tests/Feature/RolePermissionSeederTest.php`

**Interfaces:**
- Consumes: `App\Models\Role` (existing), `Spatie\Permission\Models\Permission` (existing).
- Produces: permission named `manage-ppdb`, granted to `yayasan_super_admin` and `admin_administrasi`.

- [ ] **Step 1: Update the failing test first**

Edit `tests/Feature/RolePermissionSeederTest.php` — replace the three `it()` blocks with:

```php
<?php

use App\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Permission;

it('seeds the initial permissions', function () {
    (new RolePermissionSeeder())->run();

    $expected = [
        'manage-roles', 'manage-users', 'manage-yayasan',
        'manage-lembaga', 'manage-tahun-ajaran', 'manage-guru', 'view-audit-log',
        'manage-ppdb',
    ];

    foreach ($expected as $name) {
        expect(Permission::where('name', $name)->exists())->toBeTrue();
    }
});

it('seeds the initial roles with correct scope and protection', function () {
    (new RolePermissionSeeder())->run();

    $superAdmin = Role::where('name', 'yayasan_super_admin')->first();
    expect($superAdmin->scope_level)->toBe('yayasan');
    expect($superAdmin->is_protected)->toBeTrue();
    expect($superAdmin->permissions()->count())->toBe(8);

    expect(Role::where('name', 'kepala_sekolah')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'admin_administrasi')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'admin_keuangan')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'guru')->first()->scope_level)->toBe('diri_sendiri');
});

it('gives admin_administrasi the manage-ppdb permission by default', function () {
    (new RolePermissionSeeder())->run();

    $adminAdministrasi = Role::where('name', 'admin_administrasi')->first();
    expect($adminAdministrasi->hasPermissionTo('manage-ppdb'))->toBeTrue();
});

it('is idempotent when run twice', function () {
    (new RolePermissionSeeder())->run();
    (new RolePermissionSeeder())->run();

    expect(Role::count())->toBe(5);
    expect(Permission::count())->toBe(8);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=RolePermissionSeederTest`
Expected: FAIL — `manage-ppdb` permission not found, and permission counts still at 7.

- [ ] **Step 3: Update the seeder**

Replace the contents of `database/seeders/RolePermissionSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'manage-roles', 'manage-users', 'manage-yayasan',
            'manage-lembaga', 'manage-tahun-ajaran', 'manage-guru', 'view-audit-log',
            'manage-ppdb',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $roles = [
            'yayasan_super_admin' => ['scope_level' => 'yayasan', 'is_protected' => true],
            'kepala_sekolah' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'admin_administrasi' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'admin_keuangan' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'guru' => ['scope_level' => 'diri_sendiri', 'is_protected' => false],
        ];

        foreach ($roles as $name => $attributes) {
            $role = Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                $attributes
            );

            if ($name === 'yayasan_super_admin') {
                $role->syncPermissions($permissions);
            }

            if ($name === 'admin_administrasi') {
                $role->givePermissionTo('manage-ppdb');
            }
        }
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=RolePermissionSeederTest`
Expected: 4 passed.

- [ ] **Step 5: Re-run the seeder in the actual database**

Run: `php artisan db:seed --class=RolePermissionSeeder`
Expected: no errors (the seeder is idempotent via `firstOrCreate`/`givePermissionTo`, safe to re-run).

- [ ] **Step 6: Commit**

```bash
git add database/seeders/RolePermissionSeeder.php tests/Feature/RolePermissionSeederTest.php
git commit -m "feat: add manage-ppdb permission, granted to yayasan_super_admin and admin_administrasi"
```

---

## Task 3: Jenis Tes master CRUD

**Files:**
- Create: `app/Http/Controllers/Admin/JenisTesMasterController.php`
- Create: `resources/views/admin/jenis-tes/index.blade.php`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/JenisTesMasterTest.php`

**Interfaces:**
- Consumes: `App\Models\JenisTesMaster` (Task 1), `App\Models\SeleksiPpdb` (Task 1, for the delete-in-use guard).
- Produces: routes `admin.jenis-tes.index` (GET), `admin.jenis-tes.store` (POST), `admin.jenis-tes.destroy` (DELETE `admin/jenis-tes/{jenisTes}`).

- [ ] **Step 1: Write the failing test**

`tests/Feature/Admin/JenisTesMasterTest.php`:

```php
<?php

use App\Models\JenisTesMaster;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function buatAdminPpdb(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Permission::firstOrCreate(['name' => 'manage-ppdb', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('manage-ppdb');
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    return [$lembaga, $user];
}

it('denies access without the manage-ppdb permission', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->get(route('admin.jenis-tes.index'))->assertForbidden();
});

it('creates a jenis tes scoped to the acting user lembaga', function () {
    [$lembaga, $user] = buatAdminPpdb();

    $this->actingAs($user)
        ->post(route('admin.jenis-tes.store'), ['nama' => 'Tes Tulis', 'deskripsi' => 'Tes tertulis akademik'])
        ->assertRedirect(route('admin.jenis-tes.index'));

    $jenisTes = JenisTesMaster::first();
    expect($jenisTes->nama)->toBe('Tes Tulis');
    expect($jenisTes->lembaga_id)->toBe($lembaga->id);
});

it('404s when a lembaga-scoped admin tries to delete a jenis tes belonging to another lembaga', function () {
    [$lembaga, $user] = buatAdminPpdb();
    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);

    $otherJenisTes = JenisTesMaster::withoutGlobalScopes()->create([
        'lembaga_id' => $otherLembaga->id,
        'nama' => 'Wawancara',
    ]);

    $this->actingAs($user)
        ->delete(route('admin.jenis-tes.destroy', $otherJenisTes))
        ->assertNotFound();
});

it('blocks deleting a jenis tes that is still referenced by a seleksi row', function () {
    [$lembaga, $user] = buatAdminPpdb();
    $this->actingAs($user);

    $tahunAjaran = \App\Models\TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = \App\Models\JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    $gelombang = \App\Models\GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => '2026-01-01', 'tanggal_tutup' => '2026-02-01', 'kuota' => 20,
    ]);
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);
    \App\Models\SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'jenis_tes_master_id' => $jenisTes->id, 'jadwal' => '2026-01-15 09:00:00',
    ]);

    $this->delete(route('admin.jenis-tes.destroy', $jenisTes))
        ->assertRedirect(route('admin.jenis-tes.index'))
        ->assertSessionHasErrors('jenis_tes');

    expect(JenisTesMaster::find($jenisTes->id))->not->toBeNull();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=JenisTesMasterTest`
Expected: FAIL — route `admin.jenis-tes.index` not defined.

- [ ] **Step 3: Add the routes**

Append to `routes/admin.php` (add the `use` import at top, and the route block inside the existing `Route::middleware(...)->group(function () { ... })`):

```php
use App\Http\Controllers\Admin\JenisTesMasterController;
```

Inside the group, after the `semester.activate` line:

```php
    Route::get('jenis-tes', [JenisTesMasterController::class, 'index'])->name('jenis-tes.index');
    Route::post('jenis-tes', [JenisTesMasterController::class, 'store'])->name('jenis-tes.store');
    Route::delete('jenis-tes/{jenisTes}', [JenisTesMasterController::class, 'destroy'])->name('jenis-tes.destroy');
```

- [ ] **Step 4: Write the controller**

`app/Http/Controllers/Admin/JenisTesMasterController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\JenisTesMaster;
use App\Models\SeleksiPpdb;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class JenisTesMasterController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('manage-ppdb');

        return view('admin.jenis-tes.index', ['jenisTesList' => JenisTesMaster::orderBy('nama')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-ppdb');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
        ]);

        JenisTesMaster::create($data);

        return redirect()->route('admin.jenis-tes.index')->with('status', 'Jenis tes berhasil ditambahkan.');
    }

    public function destroy(JenisTesMaster $jenisTes): RedirectResponse
    {
        $this->authorize('manage-ppdb');

        if (SeleksiPpdb::where('jenis_tes_master_id', $jenisTes->id)->exists()) {
            return redirect()->route('admin.jenis-tes.index')
                ->withErrors(['jenis_tes' => 'Jenis tes ini masih dipakai di satu atau lebih jadwal seleksi, tidak bisa dihapus.']);
        }

        $jenisTes->delete();

        return redirect()->route('admin.jenis-tes.index')->with('status', 'Jenis tes berhasil dihapus.');
    }
}
```

- [ ] **Step 5: Write the view**

`resources/views/admin/jenis-tes/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">SPMB</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Jenis Tes</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl space-y-6">
        @if (session('status'))
            <div class="rounded-xl bg-signal-green/10 p-4 text-sm text-signal-green">{{ session('status') }}</div>
        @endif
        @error('jenis_tes')
            <div class="rounded-xl bg-signal-red/10 p-4 text-sm text-signal-red">{{ $message }}</div>
        @enderror

        <x-panel>
            <ul class="divide-y divide-ink/10 px-6">
                @forelse ($jenisTesList as $jenisTes)
                    <li class="flex items-center justify-between py-3">
                        <div>
                            <p class="text-sm font-medium text-ink">{{ $jenisTes->nama }}</p>
                            @if ($jenisTes->deskripsi)
                                <p class="text-xs text-slate">{{ $jenisTes->deskripsi }}</p>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('admin.jenis-tes.destroy', $jenisTes) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm font-medium text-signal-red hover:text-signal-red/80">Hapus</button>
                        </form>
                    </li>
                @empty
                    <li class="py-6 text-center text-sm text-slate">Belum ada jenis tes. Tambahkan lewat form di bawah.</li>
                @endforelse
            </ul>

            <form method="POST" action="{{ route('admin.jenis-tes.store') }}" class="flex flex-wrap items-end gap-2 border-t border-ink/10 bg-paper/50 px-6 py-4">
                @csrf
                <div class="flex-1">
                    <x-input-label value="Nama Jenis Tes" />
                    <x-text-input type="text" name="nama" placeholder="mis. Tes Tulis, Wawancara" class="mt-1.5" />
                </div>
                <div class="flex-1">
                    <x-input-label value="Deskripsi (opsional)" />
                    <x-text-input type="text" name="deskripsi" class="mt-1.5" />
                </div>
                <x-primary-button>Tambah</x-primary-button>
            </form>
        </x-panel>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter=JenisTesMasterTest`
Expected: 4 passed.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/JenisTesMasterController.php resources/views/admin/jenis-tes/index.blade.php routes/admin.php tests/Feature/Admin/JenisTesMasterTest.php
git commit -m "feat: add Jenis Tes master CRUD panel"
```

---

## Task 4: Gelombang PPDB CRUD

**Files:**
- Create: `app/Http/Controllers/Admin/GelombangPpdbController.php`
- Create: `resources/views/admin/gelombang-ppdb/index.blade.php`
- Create: `resources/views/admin/gelombang-ppdb/create.blade.php`
- Create: `resources/views/admin/gelombang-ppdb/edit.blade.php`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/GelombangPpdbTest.php`

**Interfaces:**
- Consumes: `App\Models\GelombangPpdb`, `App\Models\TahunAjaran` (existing — used to resolve the lembaga's active tahun ajaran).
- Produces: routes `admin.gelombang-ppdb.index|create|store|edit|update` (no `show`/`destroy` — matches the `Lembaga`/`Guru` pattern of deactivating rather than deleting; here there is no deactivate concept for a wave, so out-of-date waves are simply left as historical rows).

- [ ] **Step 1: Write the failing test**

`tests/Feature/Admin/GelombangPpdbTest.php`:

```php
<?php

use App\Models\GelombangPpdb;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function buatAdminPpdbDenganTahunAktif(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Permission::firstOrCreate(['name' => 'manage-ppdb', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('manage-ppdb');
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);

    return [$lembaga, $user, $tahunAjaran];
}

it('shows an empty-state prompt when the lembaga has no active tahun ajaran', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    Permission::firstOrCreate(['name' => 'manage-ppdb', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('manage-ppdb');
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $this->actingAs($user)->get(route('admin.gelombang-ppdb.index'))
        ->assertOk()
        ->assertSee('Aktifkan tahun ajaran');
});

it('creates a gelombang scoped to the active tahun ajaran', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminPpdbDenganTahunAktif();

    $this->actingAs($user)->post(route('admin.gelombang-ppdb.store'), [
        'nama' => 'Gelombang 1',
        'tanggal_buka' => '2026-08-01',
        'tanggal_tutup' => '2026-09-01',
        'kuota' => 40,
    ])->assertRedirect(route('admin.gelombang-ppdb.index'));

    $gelombang = GelombangPpdb::first();
    expect($gelombang->tahun_ajaran_id)->toBe($tahunAjaran->id);
    expect($gelombang->lembaga_id)->toBe($lembaga->id);
});

it('rejects a gelombang whose tanggal_tutup is before tanggal_buka', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminPpdbDenganTahunAktif();

    $this->actingAs($user)->post(route('admin.gelombang-ppdb.store'), [
        'nama' => 'Gelombang 1',
        'tanggal_buka' => '2026-09-01',
        'tanggal_tutup' => '2026-08-01',
        'kuota' => 40,
    ])->assertSessionHasErrors('tanggal_tutup');

    expect(GelombangPpdb::count())->toBe(0);
});

it('404s when a lembaga-scoped admin opens the edit page for a gelombang in another lembaga', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminPpdbDenganTahunAktif();
    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    $otherTahun = TahunAjaran::withoutGlobalScopes()->create([
        'lembaga_id' => $otherLembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $otherGelombang = GelombangPpdb::withoutGlobalScopes()->create([
        'lembaga_id' => $otherLembaga->id, 'tahun_ajaran_id' => $otherTahun->id,
        'nama' => 'Gelombang 1', 'tanggal_buka' => '2026-08-01', 'tanggal_tutup' => '2026-09-01', 'kuota' => 40,
    ]);

    $this->actingAs($user)->get(route('admin.gelombang-ppdb.edit', $otherGelombang))->assertNotFound();
});

it('denies access without the manage-ppdb permission', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminPpdbDenganTahunAktif();
    $noRoleUser = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($noRoleUser)->get(route('admin.gelombang-ppdb.index'))->assertForbidden();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=GelombangPpdbTest`
Expected: FAIL — route `admin.gelombang-ppdb.index` not defined.

- [ ] **Step 3: Add the routes**

Add import to `routes/admin.php`:

```php
use App\Http\Controllers\Admin\GelombangPpdbController;
```

Inside the group:

```php
    Route::resource('gelombang-ppdb', GelombangPpdbController::class)->except(['show', 'destroy']);
```

- [ ] **Step 4: Write the controller**

`app/Http/Controllers/Admin/GelombangPpdbController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\GelombangPpdb;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GelombangPpdbController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('manage-ppdb');

        $tahunAjaranAktif = TahunAjaran::where('status_aktif', true)->first();

        return view('admin.gelombang-ppdb.index', [
            'tahunAjaranAktif' => $tahunAjaranAktif,
            'gelombangList' => $tahunAjaranAktif
                ? GelombangPpdb::where('tahun_ajaran_id', $tahunAjaranAktif->id)->orderBy('tanggal_buka')->get()
                : collect(),
            'tahunAjaranSebelumnya' => $tahunAjaranAktif
                ? TahunAjaran::where('id', '!=', $tahunAjaranAktif->id)
                    ->where('tanggal_mulai', '<', $tahunAjaranAktif->tanggal_mulai)
                    ->orderByDesc('tanggal_mulai')
                    ->first()
                : null,
        ]);
    }

    public function create(): View
    {
        $this->authorize('manage-ppdb');

        return view('admin.gelombang-ppdb.create', [
            'tahunAjaranAktif' => TahunAjaran::where('status_aktif', true)->firstOrFail(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-ppdb');

        $tahunAjaranAktif = TahunAjaran::where('status_aktif', true)->firstOrFail();
        $data = $this->validated($request);
        $data['tahun_ajaran_id'] = $tahunAjaranAktif->id;

        GelombangPpdb::create($data);

        return redirect()->route('admin.gelombang-ppdb.index')->with('status', 'Gelombang berhasil ditambahkan.');
    }

    public function edit(GelombangPpdb $gelombangPpdb): View
    {
        $this->authorize('manage-ppdb');

        return view('admin.gelombang-ppdb.edit', ['gelombang' => $gelombangPpdb]);
    }

    public function update(Request $request, GelombangPpdb $gelombangPpdb): RedirectResponse
    {
        $this->authorize('manage-ppdb');

        $gelombangPpdb->update($this->validated($request, $gelombangPpdb));

        return redirect()->route('admin.gelombang-ppdb.index')->with('status', 'Gelombang berhasil diperbarui.');
    }

    private function validated(Request $request, ?GelombangPpdb $current = null): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'tanggal_buka' => ['required', 'date'],
            'tanggal_tutup' => ['required', 'date', 'after:tanggal_buka'],
            'kuota' => ['required', 'integer', 'min:1'],
        ]);
    }
}
```

- [ ] **Step 5: Write the views**

`resources/views/admin/gelombang-ppdb/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">SPMB</p>
                <h2 class="mt-1 font-display text-2xl font-semibold text-ink">
                    Gelombang PPDB
                    @if ($tahunAjaranAktif)
                        <span class="text-base font-normal text-slate">— {{ $tahunAjaranAktif->nama }}</span>
                    @endif
                </h2>
            </div>
            @if ($tahunAjaranAktif)
                <x-link-button href="{{ route('admin.gelombang-ppdb.create') }}">
                    <span class="text-base leading-none">+</span> Tambah Gelombang
                </x-link-button>
            @endif
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        @if (session('status'))
            <div class="rounded-xl bg-signal-green/10 p-4 text-sm text-signal-green">{{ session('status') }}</div>
        @endif

        @if (! $tahunAjaranAktif)
            <x-panel class="p-6 text-center text-sm text-slate">
                Aktifkan tahun ajaran terlebih dahulu di menu
                <a href="{{ route('admin.tahun-ajaran.index') }}" class="font-medium text-ink underline">Tahun Ajaran</a>
                sebelum mengatur gelombang PPDB.
            </x-panel>
        @elseif ($gelombangList->isEmpty())
            <x-panel class="p-6">
                <p class="text-sm text-slate">Belum ada konfigurasi SPMB untuk {{ $tahunAjaranAktif->nama }}.</p>
                @if ($tahunAjaranSebelumnya)
                    <form method="POST" action="{{ route('admin.spmb-konfigurasi.duplikasi') }}" class="mt-3">
                        @csrf
                        <input type="hidden" name="tahun_ajaran_sumber_id" value="{{ $tahunAjaranSebelumnya->id }}">
                        <button type="submit" class="rounded-xl bg-brass/10 px-4 py-2 text-sm font-bold text-brass transition hover:bg-brass/20">
                            Salin dari {{ $tahunAjaranSebelumnya->nama }}
                        </button>
                    </form>
                @endif
            </x-panel>
        @else
            <x-panel>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-ink/10 bg-paper/60 text-left text-xs uppercase tracking-wide text-slate">
                            <th class="px-5 py-3 font-display font-semibold">Nama</th>
                            <th class="px-5 py-3 font-display font-semibold">Tanggal Buka</th>
                            <th class="px-5 py-3 font-display font-semibold">Tanggal Tutup</th>
                            <th class="px-5 py-3 font-display font-semibold">Kuota</th>
                            <th class="px-5 py-3 font-display font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink/10">
                        @foreach ($gelombangList as $gelombang)
                            <tr class="transition hover:bg-paper/50">
                                <td class="px-5 py-3.5 font-medium text-ink">{{ $gelombang->nama }}</td>
                                <td class="px-5 py-3.5 text-slate">{{ $gelombang->tanggal_buka->format('d M Y') }}</td>
                                <td class="px-5 py-3.5 text-slate">{{ $gelombang->tanggal_tutup->format('d M Y') }}</td>
                                <td class="px-5 py-3.5 font-mono text-slate">{{ $gelombang->kuota }}</td>
                                <td class="px-5 py-3.5">
                                    <a href="{{ route('admin.gelombang-ppdb.edit', $gelombang) }}" class="font-medium text-ink hover:text-brass">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-panel>
        @endif
    </div>
</x-app-layout>
```

`resources/views/admin/gelombang-ppdb/create.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">SPMB</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Tambah Gelombang — {{ $tahunAjaranAktif->nama }}</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <x-panel>
            <form method="POST" action="{{ route('admin.gelombang-ppdb.store') }}" class="space-y-5 p-6">
                @csrf

                <div>
                    <x-input-label value="Nama Gelombang" />
                    <x-text-input type="text" name="nama" value="{{ old('nama') }}" placeholder="mis. Gelombang 1" class="mt-1.5" />
                    <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label value="Tanggal Buka" />
                        <x-text-input type="date" name="tanggal_buka" value="{{ old('tanggal_buka') }}" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('tanggal_buka')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label value="Tanggal Tutup" />
                        <x-text-input type="date" name="tanggal_tutup" value="{{ old('tanggal_tutup') }}" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('tanggal_tutup')" class="mt-1.5" />
                    </div>
                </div>

                <div>
                    <x-input-label value="Kuota" />
                    <x-text-input type="number" name="kuota" value="{{ old('kuota') }}" class="mt-1.5" />
                    <x-input-error :messages="$errors->get('kuota')" class="mt-1.5" />
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-primary-button>Simpan</x-primary-button>
                    <a href="{{ route('admin.gelombang-ppdb.index') }}" class="text-sm text-slate hover:text-ink">Batal</a>
                </div>
            </form>
        </x-panel>
    </div>
</x-app-layout>
```

`resources/views/admin/gelombang-ppdb/edit.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">SPMB</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Edit Gelombang: {{ $gelombang->nama }}</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <x-panel>
            <form method="POST" action="{{ route('admin.gelombang-ppdb.update', $gelombang) }}" class="space-y-5 p-6">
                @csrf
                @method('PUT')

                <div>
                    <x-input-label value="Nama Gelombang" />
                    <x-text-input type="text" name="nama" value="{{ old('nama', $gelombang->nama) }}" class="mt-1.5" />
                    <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label value="Tanggal Buka" />
                        <x-text-input type="date" name="tanggal_buka" value="{{ old('tanggal_buka', $gelombang->tanggal_buka->format('Y-m-d')) }}" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('tanggal_buka')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label value="Tanggal Tutup" />
                        <x-text-input type="date" name="tanggal_tutup" value="{{ old('tanggal_tutup', $gelombang->tanggal_tutup->format('Y-m-d')) }}" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('tanggal_tutup')" class="mt-1.5" />
                    </div>
                </div>

                <div>
                    <x-input-label value="Kuota" />
                    <x-text-input type="number" name="kuota" value="{{ old('kuota', $gelombang->kuota) }}" class="mt-1.5" />
                    <x-input-error :messages="$errors->get('kuota')" class="mt-1.5" />
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-primary-button>Simpan</x-primary-button>
                    <a href="{{ route('admin.gelombang-ppdb.index') }}" class="text-sm text-slate hover:text-ink">Batal</a>
                </div>
            </form>
        </x-panel>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter=GelombangPpdbTest`
Expected: 5 passed. (The `Route [admin.spmb-konfigurasi.duplikasi] not defined` error will NOT appear yet because that route/link only renders inside the `@if ($tahunAjaranSebelumnya)` branch, which is false in these tests — no previous tahun ajaran exists. Task 9 adds that route.)

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/GelombangPpdbController.php resources/views/admin/gelombang-ppdb routes/admin.php tests/Feature/Admin/GelombangPpdbTest.php
git commit -m "feat: add Gelombang PPDB CRUD panel scoped to the active tahun ajaran"
```

---

## Task 5: Jalur PPDB CRUD + dossier detail page

**Files:**
- Create: `app/Http/Controllers/Admin/JalurPpdbController.php`
- Create: `resources/views/admin/jalur-ppdb/index.blade.php`
- Create: `resources/views/admin/jalur-ppdb/create.blade.php`
- Create: `resources/views/admin/jalur-ppdb/edit.blade.php`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/JalurPpdbTest.php`

**Interfaces:**
- Consumes: `App\Models\JalurPpdb`, `App\Models\TahunAjaran`.
- Produces: routes `admin.jalur-ppdb.index|create|store|edit|update`. The `edit` view (`resources/views/admin/jalur-ppdb/edit.blade.php`) is the "dossier" page — Task 6, 7, 8 each add one `@include` section to it.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Admin/JalurPpdbTest.php`:

```php
<?php

use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function buatAdminJalur(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Permission::firstOrCreate(['name' => 'manage-ppdb', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('manage-ppdb');
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);

    return [$lembaga, $user, $tahunAjaran];
}

it('creates a jalur scoped to the active tahun ajaran', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();

    $this->actingAs($user)->post(route('admin.jalur-ppdb.store'), [
        'nama' => 'Prestasi',
        'deskripsi' => 'Jalur berdasarkan nilai rapor',
    ])->assertRedirect(route('admin.jalur-ppdb.index'));

    $jalur = JalurPpdb::first();
    expect($jalur->tahun_ajaran_id)->toBe($tahunAjaran->id);
    expect($jalur->status_aktif)->toBeTrue();
});

it('shows the kelengkapan indicator as empty when a jalur has no children yet', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();
    $this->actingAs($user);

    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);

    $response = $this->get(route('admin.jalur-ppdb.edit', $jalur));

    $response->assertOk();
    $response->assertSee('Formulir (0)');
    $response->assertSee('Dokumen (0)');
    $response->assertSee('Seleksi (0)');
});

it('404s when a lembaga-scoped admin opens the edit page for a jalur in another lembaga', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();
    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    $otherTahun = TahunAjaran::withoutGlobalScopes()->create([
        'lembaga_id' => $otherLembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $otherJalur = JalurPpdb::withoutGlobalScopes()->create([
        'lembaga_id' => $otherLembaga->id, 'tahun_ajaran_id' => $otherTahun->id, 'nama' => 'Reguler',
    ]);

    $this->actingAs($user)->get(route('admin.jalur-ppdb.edit', $otherJalur))->assertNotFound();
});

it('denies access without the manage-ppdb permission', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();
    $noRoleUser = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($noRoleUser)->get(route('admin.jalur-ppdb.index'))->assertForbidden();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=JalurPpdbTest`
Expected: FAIL — route `admin.jalur-ppdb.index` not defined.

- [ ] **Step 3: Add the routes**

Add import to `routes/admin.php`:

```php
use App\Http\Controllers\Admin\JalurPpdbController;
```

Inside the group:

```php
    Route::resource('jalur-ppdb', JalurPpdbController::class)->except(['show', 'destroy']);
```

- [ ] **Step 4: Write the controller**

`app/Http/Controllers/Admin/JalurPpdbController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\JalurPpdb;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class JalurPpdbController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('manage-ppdb');

        $tahunAjaranAktif = TahunAjaran::where('status_aktif', true)->first();

        return view('admin.jalur-ppdb.index', [
            'tahunAjaranAktif' => $tahunAjaranAktif,
            'jalurList' => $tahunAjaranAktif
                ? JalurPpdb::where('tahun_ajaran_id', $tahunAjaranAktif->id)->orderBy('nama')->get()
                : collect(),
            'tahunAjaranSebelumnya' => $tahunAjaranAktif
                ? TahunAjaran::where('id', '!=', $tahunAjaranAktif->id)
                    ->where('tanggal_mulai', '<', $tahunAjaranAktif->tanggal_mulai)
                    ->orderByDesc('tanggal_mulai')
                    ->first()
                : null,
        ]);
    }

    public function create(): View
    {
        $this->authorize('manage-ppdb');

        return view('admin.jalur-ppdb.create', [
            'tahunAjaranAktif' => TahunAjaran::where('status_aktif', true)->firstOrFail(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-ppdb');

        $tahunAjaranAktif = TahunAjaran::where('status_aktif', true)->firstOrFail();
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['tahun_ajaran_id'] = $tahunAjaranAktif->id;

        $jalur = JalurPpdb::create($data);

        return redirect()->route('admin.jalur-ppdb.edit', $jalur)->with('status', 'Jalur berhasil ditambahkan. Lengkapi formulir, dokumen, dan jadwal seleksinya di bawah.');
    }

    public function edit(JalurPpdb $jalurPpdb): View
    {
        $this->authorize('manage-ppdb');

        $jalurPpdb->load(['formulirField', 'dokumenSyarat', 'seleksi.gelombangPpdb', 'seleksi.jenisTesMaster']);

        return view('admin.jalur-ppdb.edit', [
            'jalur' => $jalurPpdb,
            'gelombangList' => \App\Models\GelombangPpdb::where('tahun_ajaran_id', $jalurPpdb->tahun_ajaran_id)->orderBy('nama')->get(),
            'jenisTesList' => \App\Models\JenisTesMaster::orderBy('nama')->get(),
        ]);
    }

    public function update(Request $request, JalurPpdb $jalurPpdb): RedirectResponse
    {
        $this->authorize('manage-ppdb');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'status_aktif' => ['required', 'boolean'],
        ]);

        $jalurPpdb->update($data);

        return redirect()->route('admin.jalur-ppdb.edit', $jalurPpdb)->with('status', 'Jalur berhasil diperbarui.');
    }
}
```

- [ ] **Step 5: Write the index and create views**

`resources/views/admin/jalur-ppdb/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">SPMB</p>
                <h2 class="mt-1 font-display text-2xl font-semibold text-ink">
                    Jalur PPDB
                    @if ($tahunAjaranAktif)
                        <span class="text-base font-normal text-slate">— {{ $tahunAjaranAktif->nama }}</span>
                    @endif
                </h2>
            </div>
            @if ($tahunAjaranAktif)
                <x-link-button href="{{ route('admin.jalur-ppdb.create') }}">
                    <span class="text-base leading-none">+</span> Tambah Jalur
                </x-link-button>
            @endif
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        @if (session('status'))
            <div class="rounded-xl bg-signal-green/10 p-4 text-sm text-signal-green">{{ session('status') }}</div>
        @endif

        @if (! $tahunAjaranAktif)
            <x-panel class="p-6 text-center text-sm text-slate">
                Aktifkan tahun ajaran terlebih dahulu di menu
                <a href="{{ route('admin.tahun-ajaran.index') }}" class="font-medium text-ink underline">Tahun Ajaran</a>
                sebelum mengatur jalur PPDB.
            </x-panel>
        @elseif ($jalurList->isEmpty())
            <x-panel class="p-6">
                <p class="text-sm text-slate">Belum ada konfigurasi SPMB untuk {{ $tahunAjaranAktif->nama }}.</p>
                @if ($tahunAjaranSebelumnya)
                    <form method="POST" action="{{ route('admin.spmb-konfigurasi.duplikasi') }}" class="mt-3">
                        @csrf
                        <input type="hidden" name="tahun_ajaran_sumber_id" value="{{ $tahunAjaranSebelumnya->id }}">
                        <button type="submit" class="rounded-xl bg-brass/10 px-4 py-2 text-sm font-bold text-brass transition hover:bg-brass/20">
                            Salin dari {{ $tahunAjaranSebelumnya->nama }}
                        </button>
                    </form>
                @endif
            </x-panel>
        @else
            <x-panel>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-ink/10 bg-paper/60 text-left text-xs uppercase tracking-wide text-slate">
                            <th class="px-5 py-3 font-display font-semibold">Nama</th>
                            <th class="px-5 py-3 font-display font-semibold">Status</th>
                            <th class="px-5 py-3 font-display font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink/10">
                        @foreach ($jalurList as $jalur)
                            <tr class="transition hover:bg-paper/50">
                                <td class="px-5 py-3.5 font-medium text-ink">{{ $jalur->nama }}</td>
                                <td class="px-5 py-3.5">
                                    @if ($jalur->status_aktif)
                                        <x-badge tone="brass">Aktif</x-badge>
                                    @else
                                        <x-badge tone="slate">Nonaktif</x-badge>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5">
                                    <a href="{{ route('admin.jalur-ppdb.edit', $jalur) }}" class="font-medium text-ink hover:text-brass">Kelola</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-panel>
        @endif
    </div>
</x-app-layout>
```

`resources/views/admin/jalur-ppdb/create.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">SPMB</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Tambah Jalur — {{ $tahunAjaranAktif->nama }}</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <x-panel>
            <form method="POST" action="{{ route('admin.jalur-ppdb.store') }}" class="space-y-5 p-6">
                @csrf

                <div>
                    <x-input-label value="Nama Jalur" />
                    <x-text-input type="text" name="nama" value="{{ old('nama') }}" placeholder="mis. Reguler, Prestasi, Afirmasi" class="mt-1.5" />
                    <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label value="Deskripsi (opsional)" />
                    <textarea name="deskripsi" rows="3" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">{{ old('deskripsi') }}</textarea>
                    <x-input-error :messages="$errors->get('deskripsi')" class="mt-1.5" />
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-primary-button>Simpan &amp; Lanjutkan</x-primary-button>
                    <a href="{{ route('admin.jalur-ppdb.index') }}" class="text-sm text-slate hover:text-ink">Batal</a>
                </div>
            </form>
        </x-panel>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Write the dossier (edit) view**

`resources/views/admin/jalur-ppdb/edit.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">SPMB</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Jalur: {{ $jalur->nama }}</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status'))
            <div class="rounded-xl bg-signal-green/10 p-4 text-sm text-signal-green">{{ session('status') }}</div>
        @endif
        @error('seleksi')
            <div class="rounded-xl bg-signal-red/10 p-4 text-sm text-signal-red">{{ $message }}</div>
        @enderror

        <x-panel>
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-ink/10 px-6 py-4">
                <h3 class="font-display font-semibold text-ink">Kelengkapan</h3>
                <div class="flex flex-wrap gap-2">
                    <x-badge :tone="$jalur->formulirField->count() > 0 ? 'brass' : 'slate'">Formulir ({{ $jalur->formulirField->count() }})</x-badge>
                    <x-badge :tone="$jalur->dokumenSyarat->count() > 0 ? 'brass' : 'slate'">Dokumen ({{ $jalur->dokumenSyarat->count() }})</x-badge>
                    <x-badge :tone="$jalur->seleksi->count() > 0 ? 'brass' : 'slate'">Seleksi ({{ $jalur->seleksi->count() }})</x-badge>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.jalur-ppdb.update', $jalur) }}" class="space-y-5 p-6">
                @csrf
                @method('PUT')

                <div>
                    <x-input-label value="Nama Jalur" />
                    <x-text-input type="text" name="nama" value="{{ old('nama', $jalur->nama) }}" class="mt-1.5" />
                    <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label value="Deskripsi" />
                    <textarea name="deskripsi" rows="3" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">{{ old('deskripsi', $jalur->deskripsi) }}</textarea>
                </div>

                <label class="flex items-center gap-2 text-sm text-ink">
                    <input type="hidden" name="status_aktif" value="0">
                    <input type="checkbox" name="status_aktif" value="1" class="rounded border-ink/25 text-brass focus:ring-brass" @checked($jalur->status_aktif)>
                    Jalur aktif (bisa dipilih calon murid saat portal pendaftaran dibuka)
                </label>

                <x-primary-button>Simpan Perubahan</x-primary-button>
            </form>
        </x-panel>

        @include('admin.jalur-ppdb.partials.formulir-field')
        @include('admin.jalur-ppdb.partials.dokumen-syarat')
        @include('admin.jalur-ppdb.partials.seleksi')
    </div>
</x-app-layout>
```

- [ ] **Step 7: Create placeholder partials so the view renders**

Create `resources/views/admin/jalur-ppdb/partials/formulir-field.blade.php`, `resources/views/admin/jalur-ppdb/partials/dokumen-syarat.blade.php`, and `resources/views/admin/jalur-ppdb/partials/seleksi.blade.php`, each with this exact placeholder content for now (Task 6, 7, 8 replace them):

```blade
{{-- replaced in a later task --}}
```

- [ ] **Step 8: Run the tests to verify they pass**

Run: `php artisan test --filter=JalurPpdbTest`
Expected: 4 passed.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Admin/JalurPpdbController.php resources/views/admin/jalur-ppdb routes/admin.php tests/Feature/Admin/JalurPpdbTest.php
git commit -m "feat: add Jalur PPDB CRUD panel with dossier detail page"
```

---

## Task 6: Formulir Field nested CRUD

**Files:**
- Create: `app/Http/Controllers/Admin/FormulirFieldController.php`
- Modify: `resources/views/admin/jalur-ppdb/partials/formulir-field.blade.php`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/FormulirFieldTest.php`

**Interfaces:**
- Consumes: `App\Models\FormulirField`, `App\Models\JalurPpdb`.
- Produces: routes `admin.formulir-field.store` (POST), `admin.formulir-field.destroy` (DELETE `admin/formulir-field/{formulirField}`).

- [ ] **Step 1: Write the failing test**

`tests/Feature/Admin/FormulirFieldTest.php`:

```php
<?php

use App\Models\FormulirField;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function buatJalurUntukFormulir(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Permission::firstOrCreate(['name' => 'manage-ppdb', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('manage-ppdb');
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Tahfidz']);

    return [$lembaga, $user, $jalur];
}

it('adds a text field to a jalur', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukFormulir();

    $this->actingAs($user)->post(route('admin.formulir-field.store'), [
        'jalur_ppdb_id' => $jalur->id,
        'label' => 'Jumlah Juz Hafalan',
        'field_type' => 'number',
        'is_required' => '1',
    ])->assertRedirect(route('admin.jalur-ppdb.edit', $jalur));

    $field = FormulirField::first();
    expect($field->label)->toBe('Jumlah Juz Hafalan');
    expect($field->is_required)->toBeTrue();
    expect($field->urutan)->toBe(0);
});

it('auto-increments urutan for successive fields on the same jalur', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukFormulir();
    $this->actingAs($user);

    $this->post(route('admin.formulir-field.store'), ['jalur_ppdb_id' => $jalur->id, 'label' => 'Field A', 'field_type' => 'text']);
    $this->post(route('admin.formulir-field.store'), ['jalur_ppdb_id' => $jalur->id, 'label' => 'Field B', 'field_type' => 'text']);

    expect(FormulirField::where('label', 'Field A')->first()->urutan)->toBe(0);
    expect(FormulirField::where('label', 'Field B')->first()->urutan)->toBe(1);
});

it('requires at least two options for a select field', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukFormulir();

    $this->actingAs($user)->post(route('admin.formulir-field.store'), [
        'jalur_ppdb_id' => $jalur->id,
        'label' => 'Pilihan Ekstrakurikuler',
        'field_type' => 'select',
        'options' => 'Pramuka',
    ])->assertSessionHasErrors('options');

    expect(FormulirField::count())->toBe(0);
});

it('saves select options as a json array', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukFormulir();

    $this->actingAs($user)->post(route('admin.formulir-field.store'), [
        'jalur_ppdb_id' => $jalur->id,
        'label' => 'Pilihan Ekstrakurikuler',
        'field_type' => 'select',
        'options' => "Pramuka\nPaskibra\nPMR",
    ]);

    expect(FormulirField::first()->options)->toBe(['Pramuka', 'Paskibra', 'PMR']);
});

it('rejects a formulir field targeting a jalur in another lembaga', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukFormulir();
    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    $otherTahun = TahunAjaran::withoutGlobalScopes()->create([
        'lembaga_id' => $otherLembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $otherJalur = JalurPpdb::withoutGlobalScopes()->create([
        'lembaga_id' => $otherLembaga->id, 'tahun_ajaran_id' => $otherTahun->id, 'nama' => 'Reguler',
    ]);

    $this->actingAs($user)->post(route('admin.formulir-field.store'), [
        'jalur_ppdb_id' => $otherJalur->id,
        'label' => 'Field',
        'field_type' => 'text',
    ])->assertSessionHasErrors('jalur_ppdb_id');

    expect(FormulirField::count())->toBe(0);
});

it('deletes a formulir field', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukFormulir();
    $this->actingAs($user);
    $field = FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Field A', 'field_type' => 'text']);

    $this->delete(route('admin.formulir-field.destroy', $field))->assertRedirect(route('admin.jalur-ppdb.edit', $jalur));

    expect(FormulirField::find($field->id))->toBeNull();
});

it('denies access without the manage-ppdb permission', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukFormulir();
    $noRoleUser = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($noRoleUser)->post(route('admin.formulir-field.store'), [
        'jalur_ppdb_id' => $jalur->id,
        'label' => 'Field',
        'field_type' => 'text',
    ])->assertForbidden();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=FormulirFieldTest`
Expected: FAIL — route `admin.formulir-field.store` not defined.

- [ ] **Step 3: Add the routes**

Add import to `routes/admin.php`:

```php
use App\Http\Controllers\Admin\FormulirFieldController;
```

Inside the group:

```php
    Route::post('formulir-field', [FormulirFieldController::class, 'store'])->name('formulir-field.store');
    Route::delete('formulir-field/{formulirField}', [FormulirFieldController::class, 'destroy'])->name('formulir-field.destroy');
```

- [ ] **Step 4: Write the controller**

`app/Http/Controllers/Admin/FormulirFieldController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\FormulirField;
use App\Models\JalurPpdb;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;

class FormulirFieldController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-ppdb');

        $data = $request->validate([
            'jalur_ppdb_id' => ['required', Rule::exists('jalur_ppdb', 'id')->where(fn ($query) => $query->whereIn('id', JalurPpdb::pluck('id')))],
            'label' => ['required', 'string', 'max:255'],
            'field_type' => ['required', 'in:text,textarea,number,date,select,file'],
            'is_required' => ['nullable', 'boolean'],
            'options' => ['required_if:field_type,select', 'nullable', 'string'],
        ]);

        $jalur = JalurPpdb::findOrFail($data['jalur_ppdb_id']);

        $options = null;
        if ($data['field_type'] === 'select') {
            $options = array_values(array_filter(array_map('trim', explode("\n", $data['options'] ?? ''))));

            if (count($options) < 2) {
                return back()->withErrors(['options' => 'Field bertipe pilihan butuh minimal 2 opsi (satu opsi per baris).'])->withInput();
            }
        }

        FormulirField::create([
            'jalur_ppdb_id' => $jalur->id,
            'label' => $data['label'],
            'field_type' => $data['field_type'],
            'options' => $options,
            'is_required' => $request->boolean('is_required'),
            'urutan' => $jalur->formulirField()->count(),
        ]);

        return redirect()->route('admin.jalur-ppdb.edit', $jalur)->with('status', 'Field formulir berhasil ditambahkan.');
    }

    public function destroy(FormulirField $formulirField): RedirectResponse
    {
        $this->authorize('manage-ppdb');

        $jalur = $formulirField->jalurPpdb;
        $formulirField->delete();

        return redirect()->route('admin.jalur-ppdb.edit', $jalur)->with('status', 'Field formulir berhasil dihapus.');
    }
}
```

**Note on the `jalur_ppdb_id` validation rule:** `Rule::exists('jalur_ppdb', 'id')->where(fn ($query) => $query->whereIn('id', JalurPpdb::pluck('id')))` restricts the exists-check to IDs the *Eloquent-scoped* `JalurPpdb` query returns (i.e., respecting `TenantScope`), which is exactly the "scoped `Rule::exists`, not a bare `exists:table,id`" pattern required by the global constraints. `JalurPpdb::pluck('id')` runs through the model (global scope applied), not a raw `DB::table()` call.

- [ ] **Step 5: Write the partial view**

Replace `resources/views/admin/jalur-ppdb/partials/formulir-field.blade.php`:

```blade
<x-panel>
    <div class="border-b border-ink/10 px-6 py-4">
        <h3 class="font-display font-semibold text-ink">Formulir Field</h3>
        <p class="mt-0.5 text-sm text-slate">Field tambahan di luar data wajib Dapodik, khusus untuk jalur ini.</p>
    </div>

    <ul class="divide-y divide-ink/10 px-6">
        @forelse ($jalur->formulirField as $field)
            <li class="flex items-center justify-between py-3">
                <div>
                    <span class="text-sm font-medium text-ink">{{ $field->label }}</span>
                    <span class="ml-2 text-xs uppercase text-slate">{{ $field->field_type }}</span>
                    @if ($field->is_required)
                        <x-badge tone="brass">Wajib</x-badge>
                    @endif
                    @if ($field->field_type === 'select' && $field->options)
                        <p class="mt-0.5 text-xs text-slate">Opsi: {{ implode(', ', $field->options) }}</p>
                    @endif
                </div>
                <form method="POST" action="{{ route('admin.formulir-field.destroy', $field) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm font-medium text-signal-red hover:text-signal-red/80">Hapus</button>
                </form>
            </li>
        @empty
            <li class="py-6 text-center text-sm text-slate">Belum ada field tambahan.</li>
        @endforelse
    </ul>

    <form method="POST" action="{{ route('admin.formulir-field.store') }}" class="space-y-3 border-t border-ink/10 bg-paper/50 px-6 py-4">
        @csrf
        <input type="hidden" name="jalur_ppdb_id" value="{{ $jalur->id }}">
        <div class="flex flex-wrap items-end gap-2">
            <div class="flex-1">
                <x-input-label value="Label Field" />
                <x-text-input type="text" name="label" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Tipe" />
                <select name="field_type" class="mt-1.5 rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                    <option value="text">Teks</option>
                    <option value="textarea">Teks Panjang</option>
                    <option value="number">Angka</option>
                    <option value="date">Tanggal</option>
                    <option value="select">Pilihan</option>
                    <option value="file">Berkas</option>
                </select>
            </div>
            <label class="flex items-center gap-2 pb-2.5 text-sm text-ink">
                <input type="checkbox" name="is_required" value="1" class="rounded border-ink/25 text-brass focus:ring-brass">
                Wajib
            </label>
        </div>
        <div>
            <x-input-label value="Opsi (khusus tipe Pilihan, satu per baris)" />
            <textarea name="options" rows="2" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass"></textarea>
        </div>
        <x-secondary-button type="submit">Tambah Field</x-secondary-button>
    </form>
</x-panel>
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter=FormulirFieldTest`
Expected: 7 passed.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/FormulirFieldController.php resources/views/admin/jalur-ppdb/partials/formulir-field.blade.php routes/admin.php tests/Feature/Admin/FormulirFieldTest.php
git commit -m "feat: add Formulir Field nested CRUD on the Jalur PPDB dossier"
```

---

## Task 7: Dokumen Syarat nested CRUD

**Files:**
- Create: `app/Http/Controllers/Admin/DokumenSyaratController.php`
- Modify: `resources/views/admin/jalur-ppdb/partials/dokumen-syarat.blade.php`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/DokumenSyaratTest.php`

**Interfaces:**
- Consumes: `App\Models\DokumenSyaratPpdb`, `App\Models\JalurPpdb`.
- Produces: routes `admin.dokumen-syarat.store` (POST), `admin.dokumen-syarat.destroy` (DELETE `admin/dokumen-syarat/{dokumenSyarat}`).

- [ ] **Step 1: Write the failing test**

`tests/Feature/Admin/DokumenSyaratTest.php`:

```php
<?php

use App\Models\DokumenSyaratPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function buatJalurUntukDokumen(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Permission::firstOrCreate(['name' => 'manage-ppdb', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('manage-ppdb');
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Afirmasi']);

    return [$lembaga, $user, $jalur];
}

it('adds a required dokumen syarat to a jalur', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukDokumen();

    $this->actingAs($user)->post(route('admin.dokumen-syarat.store'), [
        'jalur_ppdb_id' => $jalur->id,
        'nama_dokumen' => 'Surat Keterangan Tidak Mampu',
        'wajib' => '1',
    ])->assertRedirect(route('admin.jalur-ppdb.edit', $jalur));

    $dokumen = DokumenSyaratPpdb::first();
    expect($dokumen->nama_dokumen)->toBe('Surat Keterangan Tidak Mampu');
    expect($dokumen->wajib)->toBeTrue();
    expect($dokumen->urutan)->toBe(0);
});

it('rejects a dokumen syarat targeting a jalur in another lembaga', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukDokumen();
    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    $otherTahun = TahunAjaran::withoutGlobalScopes()->create([
        'lembaga_id' => $otherLembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $otherJalur = JalurPpdb::withoutGlobalScopes()->create([
        'lembaga_id' => $otherLembaga->id, 'tahun_ajaran_id' => $otherTahun->id, 'nama' => 'Reguler',
    ]);

    $this->actingAs($user)->post(route('admin.dokumen-syarat.store'), [
        'jalur_ppdb_id' => $otherJalur->id,
        'nama_dokumen' => 'Akta Kelahiran',
    ])->assertSessionHasErrors('jalur_ppdb_id');

    expect(DokumenSyaratPpdb::count())->toBe(0);
});

it('deletes a dokumen syarat', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukDokumen();
    $this->actingAs($user);
    $dokumen = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);

    $this->delete(route('admin.dokumen-syarat.destroy', $dokumen))->assertRedirect(route('admin.jalur-ppdb.edit', $jalur));

    expect(DokumenSyaratPpdb::find($dokumen->id))->toBeNull();
});

it('denies access without the manage-ppdb permission', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukDokumen();
    $noRoleUser = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($noRoleUser)->post(route('admin.dokumen-syarat.store'), [
        'jalur_ppdb_id' => $jalur->id,
        'nama_dokumen' => 'Akta Kelahiran',
    ])->assertForbidden();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=DokumenSyaratTest`
Expected: FAIL — route `admin.dokumen-syarat.store` not defined.

- [ ] **Step 3: Add the routes**

Add import to `routes/admin.php`:

```php
use App\Http\Controllers\Admin\DokumenSyaratController;
```

Inside the group:

```php
    Route::post('dokumen-syarat', [DokumenSyaratController::class, 'store'])->name('dokumen-syarat.store');
    Route::delete('dokumen-syarat/{dokumenSyarat}', [DokumenSyaratController::class, 'destroy'])->name('dokumen-syarat.destroy');
```

- [ ] **Step 4: Write the controller**

`app/Http/Controllers/Admin/DokumenSyaratController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\DokumenSyaratPpdb;
use App\Models\JalurPpdb;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;

class DokumenSyaratController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-ppdb');

        $data = $request->validate([
            'jalur_ppdb_id' => ['required', Rule::exists('jalur_ppdb', 'id')->where(fn ($query) => $query->whereIn('id', JalurPpdb::pluck('id')))],
            'nama_dokumen' => ['required', 'string', 'max:255'],
            'wajib' => ['nullable', 'boolean'],
        ]);

        $jalur = JalurPpdb::findOrFail($data['jalur_ppdb_id']);

        DokumenSyaratPpdb::create([
            'jalur_ppdb_id' => $jalur->id,
            'nama_dokumen' => $data['nama_dokumen'],
            'wajib' => $request->boolean('wajib', true),
            'urutan' => $jalur->dokumenSyarat()->count(),
        ]);

        return redirect()->route('admin.jalur-ppdb.edit', $jalur)->with('status', 'Dokumen syarat berhasil ditambahkan.');
    }

    public function destroy(DokumenSyaratPpdb $dokumenSyarat): RedirectResponse
    {
        $this->authorize('manage-ppdb');

        $jalur = $dokumenSyarat->jalurPpdb;
        $dokumenSyarat->delete();

        return redirect()->route('admin.jalur-ppdb.edit', $jalur)->with('status', 'Dokumen syarat berhasil dihapus.');
    }
}
```

- [ ] **Step 5: Write the partial view**

Replace `resources/views/admin/jalur-ppdb/partials/dokumen-syarat.blade.php`:

```blade
<x-panel>
    <div class="border-b border-ink/10 px-6 py-4">
        <h3 class="font-display font-semibold text-ink">Dokumen Syarat</h3>
        <p class="mt-0.5 text-sm text-slate">Daftar dokumen yang harus diunggah calon murid pada jalur ini.</p>
    </div>

    <ul class="divide-y divide-ink/10 px-6">
        @forelse ($jalur->dokumenSyarat as $dokumen)
            <li class="flex items-center justify-between py-3">
                <span class="flex items-center gap-2 text-sm text-ink">
                    {{ $dokumen->nama_dokumen }}
                    @if ($dokumen->wajib)
                        <x-badge tone="brass">Wajib</x-badge>
                    @else
                        <x-badge tone="slate">Opsional</x-badge>
                    @endif
                </span>
                <form method="POST" action="{{ route('admin.dokumen-syarat.destroy', $dokumen) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm font-medium text-signal-red hover:text-signal-red/80">Hapus</button>
                </form>
            </li>
        @empty
            <li class="py-6 text-center text-sm text-slate">Belum ada dokumen syarat.</li>
        @endforelse
    </ul>

    <form method="POST" action="{{ route('admin.dokumen-syarat.store') }}" class="flex flex-wrap items-end gap-2 border-t border-ink/10 bg-paper/50 px-6 py-4">
        @csrf
        <input type="hidden" name="jalur_ppdb_id" value="{{ $jalur->id }}">
        <div class="flex-1">
            <x-input-label value="Nama Dokumen" />
            <x-text-input type="text" name="nama_dokumen" placeholder="mis. Akta Kelahiran" class="mt-1.5" />
        </div>
        <label class="flex items-center gap-2 pb-2.5 text-sm text-ink">
            <input type="hidden" name="wajib" value="0">
            <input type="checkbox" name="wajib" value="1" class="rounded border-ink/25 text-brass focus:ring-brass" checked>
            Wajib
        </label>
        <x-secondary-button type="submit">Tambah Dokumen</x-secondary-button>
    </form>
</x-panel>
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter=DokumenSyaratTest`
Expected: 4 passed.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/DokumenSyaratController.php resources/views/admin/jalur-ppdb/partials/dokumen-syarat.blade.php routes/admin.php tests/Feature/Admin/DokumenSyaratTest.php
git commit -m "feat: add Dokumen Syarat nested CRUD on the Jalur PPDB dossier"
```

---

## Task 8: Seleksi/Tes nested CRUD

**Files:**
- Create: `app/Http/Controllers/Admin/SeleksiController.php`
- Modify: `resources/views/admin/jalur-ppdb/partials/seleksi.blade.php`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/SeleksiTest.php`

**Interfaces:**
- Consumes: `App\Models\SeleksiPpdb`, `App\Models\JalurPpdb`, `App\Models\GelombangPpdb`, `App\Models\JenisTesMaster`.
- Produces: routes `admin.seleksi.store` (POST), `admin.seleksi.destroy` (DELETE `admin/seleksi/{seleksi}`).

- [ ] **Step 1: Write the failing test**

`tests/Feature/Admin/SeleksiTest.php`:

```php
<?php

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\JenisTesMaster;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\SeleksiPpdb;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function buatKonteksSeleksi(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Permission::firstOrCreate(['name' => 'manage-ppdb', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('manage-ppdb');
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Prestasi']);
    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => '2026-08-01', 'tanggal_tutup' => '2026-09-01', 'kuota' => 40,
    ]);
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);

    return [$lembaga, $user, $tahunAjaran, $jalur, $gelombang, $jenisTes];
}

it('adds a seleksi schedule linking a jalur, gelombang, and jenis tes', function () {
    [$lembaga, $user, $tahunAjaran, $jalur, $gelombang, $jenisTes] = buatKonteksSeleksi();

    $this->actingAs($user)->post(route('admin.seleksi.store'), [
        'jalur_ppdb_id' => $jalur->id,
        'gelombang_ppdb_id' => $gelombang->id,
        'jenis_tes_master_id' => $jenisTes->id,
        'jadwal' => '2026-08-15 09:00',
        'kriteria_kelulusan' => 'Nilai minimal 70',
        'bobot' => '40',
    ])->assertRedirect(route('admin.jalur-ppdb.edit', $jalur));

    $seleksi = SeleksiPpdb::first();
    expect($seleksi->gelombang_ppdb_id)->toBe($gelombang->id);
    expect($seleksi->jenis_tes_master_id)->toBe($jenisTes->id);
    expect((float) $seleksi->bobot)->toBe(40.0);
});

it('rejects a gelombang that belongs to a different tahun ajaran than the jalur', function () {
    [$lembaga, $user, $tahunAjaran, $jalur, $gelombang, $jenisTes] = buatKonteksSeleksi();

    $tahunLain = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2025/2026',
        'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30', 'status_aktif' => false,
    ]);
    $gelombangTahunLain = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLain->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => '2025-08-01', 'tanggal_tutup' => '2025-09-01', 'kuota' => 40,
    ]);

    $this->actingAs($user)->post(route('admin.seleksi.store'), [
        'jalur_ppdb_id' => $jalur->id,
        'gelombang_ppdb_id' => $gelombangTahunLain->id,
        'jenis_tes_master_id' => $jenisTes->id,
        'jadwal' => '2026-08-15 09:00',
    ])->assertSessionHasErrors('gelombang_ppdb_id');

    expect(SeleksiPpdb::count())->toBe(0);
});

it('rejects a jenis tes belonging to another lembaga', function () {
    [$lembaga, $user, $tahunAjaran, $jalur, $gelombang, $jenisTes] = buatKonteksSeleksi();
    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    $otherJenisTes = JenisTesMaster::withoutGlobalScopes()->create(['lembaga_id' => $otherLembaga->id, 'nama' => 'Wawancara']);

    $this->actingAs($user)->post(route('admin.seleksi.store'), [
        'jalur_ppdb_id' => $jalur->id,
        'gelombang_ppdb_id' => $gelombang->id,
        'jenis_tes_master_id' => $otherJenisTes->id,
        'jadwal' => '2026-08-15 09:00',
    ])->assertSessionHasErrors('jenis_tes_master_id');

    expect(SeleksiPpdb::count())->toBe(0);
});

it('deletes a seleksi row', function () {
    [$lembaga, $user, $tahunAjaran, $jalur, $gelombang, $jenisTes] = buatKonteksSeleksi();
    $this->actingAs($user);
    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'jenis_tes_master_id' => $jenisTes->id, 'jadwal' => '2026-08-15 09:00:00',
    ]);

    $this->delete(route('admin.seleksi.destroy', $seleksi))->assertRedirect(route('admin.jalur-ppdb.edit', $jalur));

    expect(SeleksiPpdb::find($seleksi->id))->toBeNull();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=SeleksiTest`
Expected: FAIL — route `admin.seleksi.store` not defined.

- [ ] **Step 3: Add the routes**

Add import to `routes/admin.php`:

```php
use App\Http\Controllers\Admin\SeleksiController;
```

Inside the group:

```php
    Route::post('seleksi', [SeleksiController::class, 'store'])->name('seleksi.store');
    Route::delete('seleksi/{seleksi}', [SeleksiController::class, 'destroy'])->name('seleksi.destroy');
```

- [ ] **Step 4: Write the controller**

`app/Http/Controllers/Admin/SeleksiController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\JenisTesMaster;
use App\Models\SeleksiPpdb;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;

class SeleksiController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-ppdb');

        $data = $request->validate([
            'jalur_ppdb_id' => ['required', Rule::exists('jalur_ppdb', 'id')->where(fn ($query) => $query->whereIn('id', JalurPpdb::pluck('id')))],
            'gelombang_ppdb_id' => ['required', Rule::exists('gelombang_ppdb', 'id')->where(fn ($query) => $query->whereIn('id', GelombangPpdb::pluck('id')))],
            'jenis_tes_master_id' => ['required', Rule::exists('jenis_tes_master', 'id')->where(fn ($query) => $query->whereIn('id', JenisTesMaster::pluck('id')))],
            'jadwal' => ['required', 'date'],
            'kriteria_kelulusan' => ['nullable', 'string', 'max:2000'],
            'bobot' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $jalur = JalurPpdb::findOrFail($data['jalur_ppdb_id']);
        $gelombang = GelombangPpdb::findOrFail($data['gelombang_ppdb_id']);

        if ($gelombang->tahun_ajaran_id !== $jalur->tahun_ajaran_id) {
            return back()->withErrors(['gelombang_ppdb_id' => 'Gelombang yang dipilih bukan dari tahun ajaran yang sama dengan jalur ini.'])->withInput();
        }

        SeleksiPpdb::create($data);

        return redirect()->route('admin.jalur-ppdb.edit', $jalur)->with('status', 'Jadwal seleksi berhasil ditambahkan.');
    }

    public function destroy(SeleksiPpdb $seleksi): RedirectResponse
    {
        $this->authorize('manage-ppdb');

        $jalur = $seleksi->jalurPpdb;
        $seleksi->delete();

        return redirect()->route('admin.jalur-ppdb.edit', $jalur)->with('status', 'Jadwal seleksi berhasil dihapus.');
    }
}
```

- [ ] **Step 5: Write the partial view**

Replace `resources/views/admin/jalur-ppdb/partials/seleksi.blade.php`:

```blade
<x-panel>
    <div class="border-b border-ink/10 px-6 py-4">
        <h3 class="font-display font-semibold text-ink">Seleksi &amp; Tes</h3>
        <p class="mt-0.5 text-sm text-slate">Jadwal tes untuk jalur ini, per gelombang. Boleh dikosongkan jika jalur tidak memakai tes.</p>
    </div>

    <ul class="divide-y divide-ink/10 px-6">
        @forelse ($jalur->seleksi as $seleksi)
            <li class="flex items-center justify-between py-3">
                <div>
                    <span class="text-sm font-medium text-ink">{{ $seleksi->jenisTesMaster->nama }}</span>
                    <span class="ml-2 text-xs text-slate">{{ $seleksi->gelombangPpdb->nama }} &middot; {{ $seleksi->jadwal->format('d M Y H:i') }}</span>
                    @if ($seleksi->kriteria_kelulusan)
                        <p class="mt-0.5 text-xs text-slate">{{ $seleksi->kriteria_kelulusan }}</p>
                    @endif
                </div>
                <form method="POST" action="{{ route('admin.seleksi.destroy', $seleksi) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm font-medium text-signal-red hover:text-signal-red/80">Hapus</button>
                </form>
            </li>
        @empty
            <li class="py-6 text-center text-sm text-slate">Belum ada jadwal seleksi.</li>
        @endforelse
    </ul>

    <form method="POST" action="{{ route('admin.seleksi.store') }}" class="space-y-3 border-t border-ink/10 bg-paper/50 px-6 py-4">
        @csrf
        <input type="hidden" name="jalur_ppdb_id" value="{{ $jalur->id }}">
        <div class="flex flex-wrap items-end gap-2">
            <div>
                <x-input-label value="Gelombang" />
                <select name="gelombang_ppdb_id" class="mt-1.5 rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                    @foreach ($gelombangList as $gelombang)
                        <option value="{{ $gelombang->id }}">{{ $gelombang->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label value="Jenis Tes" />
                <select name="jenis_tes_master_id" class="mt-1.5 rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                    @foreach ($jenisTesList as $jenisTes)
                        <option value="{{ $jenisTes->id }}">{{ $jenisTes->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label value="Jadwal" />
                <x-text-input type="datetime-local" name="jadwal" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Bobot (%)" />
                <x-text-input type="number" name="bobot" class="mt-1.5 w-24" />
            </div>
        </div>
        <div>
            <x-input-label value="Kriteria Kelulusan (opsional)" />
            <x-text-input type="text" name="kriteria_kelulusan" class="mt-1.5" />
        </div>
        <x-secondary-button type="submit">Tambah Jadwal Seleksi</x-secondary-button>
    </form>
</x-panel>
```

Note: the form above renders an empty `<select name="gelombang_ppdb_id">` when there are no gelombang yet for this tahun ajaran — that's expected and matches the existing Formulir Field / Dokumen Syarat pattern of not hiding the form, just letting submission fail validation if nothing is selectable. No extra guard needed here since Task 4 already prompts the admin to add a gelombang first via the Gelombang index page.

- [ ] **Step 6: Add the permission-denial test**

Add this `it()` block to `tests/Feature/Admin/SeleksiTest.php`:

```php
it('denies access without the manage-ppdb permission', function () {
    [$lembaga, $user, $tahunAjaran, $jalur, $gelombang, $jenisTes] = buatKonteksSeleksi();
    $noRoleUser = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($noRoleUser)->post(route('admin.seleksi.store'), [
        'jalur_ppdb_id' => $jalur->id,
        'gelombang_ppdb_id' => $gelombang->id,
        'jenis_tes_master_id' => $jenisTes->id,
        'jadwal' => '2026-08-15 09:00',
    ])->assertForbidden();
});
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test --filter=SeleksiTest`
Expected: 5 passed.

- [ ] **Step 8: Run the full Jalur dossier test again to confirm the partials compose correctly**

Run: `php artisan test --filter=JalurPpdbTest`
Expected: 4 passed (the "kelengkapan" test now exercises the real partials instead of the Task 5 placeholders).

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Admin/SeleksiController.php resources/views/admin/jalur-ppdb/partials/seleksi.blade.php routes/admin.php tests/Feature/Admin/SeleksiTest.php
git commit -m "feat: add Seleksi/Tes nested CRUD on the Jalur PPDB dossier"
```

---

## Task 9: Konfigurasi duplication feature

**Files:**
- Create: `app/Services/SpmbKonfigurasiDuplikasi.php`
- Create: `app/Http/Controllers/Admin/SpmbKonfigurasiController.php`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/SpmbKonfigurasiDuplikasiTest.php`

**Interfaces:**
- Consumes: `App\Models\GelombangPpdb`, `App\Models\JalurPpdb`, `App\Models\FormulirField`, `App\Models\DokumenSyaratPpdb`, `App\Models\SeleksiPpdb`, `App\Models\TahunAjaran`.
- Produces: `SpmbKonfigurasiDuplikasi::duplikasi(TahunAjaran $sumber, TahunAjaran $tujuan): array` returning `['gelombang' => int, 'jalur' => int, 'formulir_field' => int, 'dokumen_syarat' => int, 'seleksi' => int]`. Route `admin.spmb-konfigurasi.duplikasi` (POST), consumed by the empty-state forms already written into `resources/views/admin/gelombang-ppdb/index.blade.php` and `resources/views/admin/jalur-ppdb/index.blade.php` in Tasks 4 and 5.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Admin/SpmbKonfigurasiDuplikasiTest.php`:

```php
<?php

use App\Models\DokumenSyaratPpdb;
use App\Models\FormulirField;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\JenisTesMaster;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\SeleksiPpdb;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function buatKonteksDuplikasi(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Permission::firstOrCreate(['name' => 'manage-ppdb', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('manage-ppdb');
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $tahunLama = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2025/2026',
        'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30', 'status_aktif' => false,
    ]);
    $tahunBaru = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);

    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => '2025-08-01', 'tanggal_tutup' => '2025-09-01', 'kuota' => 30,
    ]);
    $jalur = JalurPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id,
        'nama' => 'Prestasi', 'deskripsi' => 'Jalur nilai rapor', 'status_aktif' => true,
    ]);
    FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Nilai Rata-rata Rapor', 'field_type' => 'number', 'urutan' => 0]);
    DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Fotokopi Rapor', 'wajib' => true, 'urutan' => 0]);
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Wawancara']);
    SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'jenis_tes_master_id' => $jenisTes->id, 'jadwal' => '2025-08-20 09:00:00',
        'kriteria_kelulusan' => 'Lolos wawancara', 'bobot' => 30,
    ]);

    return [$lembaga, $user, $tahunLama, $tahunBaru];
}

it('duplicates the entire SPMB configuration chain into the target tahun ajaran', function () {
    [$lembaga, $user, $tahunLama, $tahunBaru] = buatKonteksDuplikasi();

    $this->actingAs($user)->post(route('admin.spmb-konfigurasi.duplikasi'), [
        'tahun_ajaran_sumber_id' => $tahunLama->id,
    ])->assertRedirect();

    expect(GelombangPpdb::where('tahun_ajaran_id', $tahunBaru->id)->count())->toBe(1);
    expect(JalurPpdb::where('tahun_ajaran_id', $tahunBaru->id)->count())->toBe(1);

    $jalurBaru = JalurPpdb::where('tahun_ajaran_id', $tahunBaru->id)->first();
    expect($jalurBaru->nama)->toBe('Prestasi');
    expect($jalurBaru->formulirField)->toHaveCount(1);
    expect($jalurBaru->dokumenSyarat)->toHaveCount(1);
    expect($jalurBaru->seleksi)->toHaveCount(1);

    $gelombangBaru = GelombangPpdb::where('tahun_ajaran_id', $tahunBaru->id)->first();
    expect($gelombangBaru->tanggal_buka->format('Y-m-d'))->toBe('2026-08-01');
    expect($gelombangBaru->tanggal_tutup->format('Y-m-d'))->toBe('2026-09-01');

    $seleksiBaru = $jalurBaru->seleksi->first();
    expect($seleksiBaru->gelombang_ppdb_id)->toBe($gelombangBaru->id);
    expect($seleksiBaru->jenis_tes_master_id)->toBe($jalurBaru->seleksi->first()->jenis_tes_master_id);
});

it('refuses to duplicate into a tahun ajaran that already has gelombang or jalur data', function () {
    [$lembaga, $user, $tahunLama, $tahunBaru] = buatKonteksDuplikasi();
    JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id, 'nama' => 'Sudah Ada']);

    $this->actingAs($user)->post(route('admin.spmb-konfigurasi.duplikasi'), [
        'tahun_ajaran_sumber_id' => $tahunLama->id,
    ])->assertSessionHasErrors('tahun_ajaran_sumber_id');

    expect(JalurPpdb::where('tahun_ajaran_id', $tahunBaru->id)->count())->toBe(1);
});

it('rejects duplicating from a tahun ajaran belonging to another lembaga', function () {
    [$lembaga, $user, $tahunLama, $tahunBaru] = buatKonteksDuplikasi();
    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    $otherTahun = TahunAjaran::withoutGlobalScopes()->create([
        'lembaga_id' => $otherLembaga->id, 'nama' => '2025/2026',
        'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30', 'status_aktif' => false,
    ]);

    $this->actingAs($user)->post(route('admin.spmb-konfigurasi.duplikasi'), [
        'tahun_ajaran_sumber_id' => $otherTahun->id,
    ])->assertSessionHasErrors('tahun_ajaran_sumber_id');

    expect(GelombangPpdb::where('tahun_ajaran_id', $tahunBaru->id)->count())->toBe(0);
});

it('denies access without the manage-ppdb permission', function () {
    [$lembaga, $user, $tahunLama, $tahunBaru] = buatKonteksDuplikasi();
    $noRoleUser = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($noRoleUser)->post(route('admin.spmb-konfigurasi.duplikasi'), [
        'tahun_ajaran_sumber_id' => $tahunLama->id,
    ])->assertForbidden();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=SpmbKonfigurasiDuplikasiTest`
Expected: FAIL — route `admin.spmb-konfigurasi.duplikasi` not defined.

- [ ] **Step 3: Add the route**

Add import to `routes/admin.php`:

```php
use App\Http\Controllers\Admin\SpmbKonfigurasiController;
```

Inside the group:

```php
    Route::post('spmb-konfigurasi/duplikasi', [SpmbKonfigurasiController::class, 'duplikasi'])->name('spmb-konfigurasi.duplikasi');
```

- [ ] **Step 4: Write the service class**

`app/Services/SpmbKonfigurasiDuplikasi.php`:

```php
<?php

namespace App\Services;

use App\Models\DokumenSyaratPpdb;
use App\Models\FormulirField;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\SeleksiPpdb;
use App\Models\TahunAjaran;
use Illuminate\Support\Facades\DB;

class SpmbKonfigurasiDuplikasi
{
    /**
     * @return array{gelombang: int, jalur: int, formulir_field: int, dokumen_syarat: int, seleksi: int}
     */
    public function duplikasi(TahunAjaran $sumber, TahunAjaran $tujuan): array
    {
        return DB::transaction(function () use ($sumber, $tujuan) {
            $jumlah = ['gelombang' => 0, 'jalur' => 0, 'formulir_field' => 0, 'dokumen_syarat' => 0, 'seleksi' => 0];

            $pemetaanGelombang = [];
            foreach (GelombangPpdb::where('tahun_ajaran_id', $sumber->id)->get() as $gelombangLama) {
                $gelombangBaru = GelombangPpdb::create([
                    'lembaga_id' => $tujuan->lembaga_id,
                    'tahun_ajaran_id' => $tujuan->id,
                    'nama' => $gelombangLama->nama,
                    'tanggal_buka' => $gelombangLama->tanggal_buka->copy()->addYear(),
                    'tanggal_tutup' => $gelombangLama->tanggal_tutup->copy()->addYear(),
                    'kuota' => $gelombangLama->kuota,
                ]);
                $pemetaanGelombang[$gelombangLama->id] = $gelombangBaru->id;
                $jumlah['gelombang']++;
            }

            foreach (JalurPpdb::where('tahun_ajaran_id', $sumber->id)->get() as $jalurLama) {
                $jalurBaru = JalurPpdb::create([
                    'lembaga_id' => $tujuan->lembaga_id,
                    'tahun_ajaran_id' => $tujuan->id,
                    'nama' => $jalurLama->nama,
                    'deskripsi' => $jalurLama->deskripsi,
                    'status_aktif' => $jalurLama->status_aktif,
                ]);
                $jumlah['jalur']++;

                foreach ($jalurLama->formulirField as $field) {
                    FormulirField::create([
                        'jalur_ppdb_id' => $jalurBaru->id,
                        'label' => $field->label,
                        'field_type' => $field->field_type,
                        'options' => $field->options,
                        'is_required' => $field->is_required,
                        'urutan' => $field->urutan,
                    ]);
                    $jumlah['formulir_field']++;
                }

                foreach ($jalurLama->dokumenSyarat as $dokumen) {
                    DokumenSyaratPpdb::create([
                        'jalur_ppdb_id' => $jalurBaru->id,
                        'nama_dokumen' => $dokumen->nama_dokumen,
                        'wajib' => $dokumen->wajib,
                        'urutan' => $dokumen->urutan,
                    ]);
                    $jumlah['dokumen_syarat']++;
                }

                foreach ($jalurLama->seleksi as $seleksi) {
                    if (! isset($pemetaanGelombang[$seleksi->gelombang_ppdb_id])) {
                        continue;
                    }

                    SeleksiPpdb::create([
                        'jalur_ppdb_id' => $jalurBaru->id,
                        'gelombang_ppdb_id' => $pemetaanGelombang[$seleksi->gelombang_ppdb_id],
                        'jenis_tes_master_id' => $seleksi->jenis_tes_master_id,
                        'jadwal' => $seleksi->jadwal->copy()->addYear(),
                        'kriteria_kelulusan' => $seleksi->kriteria_kelulusan,
                        'bobot' => $seleksi->bobot,
                    ]);
                    $jumlah['seleksi']++;
                }
            }

            return $jumlah;
        });
    }
}
```

- [ ] **Step 5: Write the controller**

`app/Http/Controllers/Admin/SpmbKonfigurasiController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\TahunAjaran;
use App\Services\SpmbKonfigurasiDuplikasi;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;

class SpmbKonfigurasiController extends BaseController
{
    use AuthorizesRequests;

    public function duplikasi(Request $request, SpmbKonfigurasiDuplikasi $duplikasi): RedirectResponse
    {
        $this->authorize('manage-ppdb');

        $tujuan = TahunAjaran::where('status_aktif', true)->firstOrFail();

        $data = $request->validate([
            'tahun_ajaran_sumber_id' => [
                'required',
                Rule::exists('tahun_ajaran', 'id')->where(fn ($query) => $query->whereIn('id', TahunAjaran::pluck('id'))),
                function ($attribute, $value, $fail) use ($tujuan) {
                    if (GelombangPpdb::where('tahun_ajaran_id', $tujuan->id)->exists() || JalurPpdb::where('tahun_ajaran_id', $tujuan->id)->exists()) {
                        $fail('Tahun ajaran ini sudah punya konfigurasi SPMB, tidak bisa disalin ulang.');
                    }
                },
            ],
        ]);

        $sumber = TahunAjaran::findOrFail($data['tahun_ajaran_sumber_id']);

        $jumlah = $duplikasi->duplikasi($sumber, $tujuan);

        activity('spmb_konfigurasi')
            ->causedBy($request->user())
            ->withProperties([
                'dari_tahun_ajaran' => $sumber->nama,
                'ke_tahun_ajaran' => $tujuan->nama,
                'jumlah' => $jumlah,
            ])
            ->log('Konfigurasi SPMB disalin dari '.$sumber->nama.' ke '.$tujuan->nama);

        return redirect()->route('admin.jalur-ppdb.index')
            ->with('status', 'Konfigurasi berhasil disalin dari '.$sumber->nama.'.');
    }
}
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter=SpmbKonfigurasiDuplikasiTest`
Expected: 4 passed.

- [ ] **Step 7: Run the Gelombang and Jalur index tests again to confirm the empty-state duplication button now resolves**

Run: `php artisan test --filter=GelombangPpdbTest`
Run: `php artisan test --filter=JalurPpdbTest`
Expected: both still fully green (the `admin.spmb-konfigurasi.duplikasi` route referenced in their empty-state views now exists).

- [ ] **Step 8: Commit**

```bash
git add app/Services/SpmbKonfigurasiDuplikasi.php app/Http/Controllers/Admin/SpmbKonfigurasiController.php routes/admin.php tests/Feature/Admin/SpmbKonfigurasiDuplikasiTest.php
git commit -m "feat: add SPMB configuration duplication from a previous tahun ajaran"
```

---

## Task 10: Sidebar navigation

**Files:**
- Modify: `resources/views/layouts/sidebar.blade.php`

**Interfaces:**
- Consumes: routes `admin.gelombang-ppdb.index`, `admin.jalur-ppdb.index`, `admin.jenis-tes.index` (all defined by Tasks 3–5).

- [ ] **Step 1: Read the current file to find the exact insertion point**

Run: `grep -n "III. Akses" resources/views/layouts/sidebar.blade.php`
Expected: shows the line with `'label' => 'III. Akses & Peran',` inside the `$navGroups` array.

- [ ] **Step 2: Insert the new nav group before "Akses & Peran" and renumber it to IV**

In `resources/views/layouts/sidebar.blade.php`, find this block (currently the third entry in `$navGroups`):

```php
        [
            'label' => 'III. Akses & Peran',
            'items' => array_filter([
                Auth::user()->can('manage-users') ? ['route' => 'admin.users.index', 'pattern' => 'admin.users.*', 'label' => 'Pengguna', 'icon' => 'group'] : null,
                Auth::user()->can('manage-roles') ? ['route' => 'admin.roles.index', 'pattern' => 'admin.roles.*', 'label' => 'Peran', 'icon' => 'shield_person'] : null,
            ]),
        ],
```

Replace it with:

```php
        [
            'label' => 'III. SPMB',
            'items' => array_filter([
                Auth::user()->can('manage-ppdb') ? ['route' => 'admin.gelombang-ppdb.index', 'pattern' => 'admin.gelombang-ppdb.*', 'label' => 'Gelombang PPDB', 'icon' => 'waves'] : null,
                Auth::user()->can('manage-ppdb') ? ['route' => 'admin.jalur-ppdb.index', 'pattern' => 'admin.jalur-ppdb.*', 'label' => 'Jalur PPDB', 'icon' => 'signpost'] : null,
                Auth::user()->can('manage-ppdb') ? ['route' => 'admin.jenis-tes.index', 'pattern' => 'admin.jenis-tes.*', 'label' => 'Jenis Tes', 'icon' => 'quiz'] : null,
            ]),
        ],
        [
            'label' => 'IV. Akses & Peran',
            'items' => array_filter([
                Auth::user()->can('manage-users') ? ['route' => 'admin.users.index', 'pattern' => 'admin.users.*', 'label' => 'Pengguna', 'icon' => 'group'] : null,
                Auth::user()->can('manage-roles') ? ['route' => 'admin.roles.index', 'pattern' => 'admin.roles.*', 'label' => 'Peran', 'icon' => 'shield_person'] : null,
            ]),
        ],
```

- [ ] **Step 3: Rebuild frontend assets**

Run: `npm run build`
Expected: build succeeds (no new Tailwind classes were introduced here beyond what's already used elsewhere in the file).

- [ ] **Step 4: Manually verify via a real login**

Run: `php artisan serve --port=8000` (in the background if not already running), then log in as a user with the `admin_administrasi` role (or `yayasan_super_admin`) and confirm the sidebar shows "III. SPMB" with Gelombang PPDB, Jalur PPDB, and Jenis Tes links, and "IV. Akses & Peran" below it.

- [ ] **Step 5: Commit**

```bash
git add resources/views/layouts/sidebar.blade.php
git commit -m "feat: add SPMB nav group to the admin sidebar"
```

---

## Task 11: Full regression pass

**Files:** none created — verification only.

- [ ] **Step 1: Run the entire test suite**

Run: `php artisan test`
Expected: all tests pass, including every pre-existing M0 test (`CrossTenantAuthorizationTest`, `TenantScopeTest`, `ResolveTenantMiddlewareTest`, `RolePermissionSeederTest`, `SemesterActivationTest`, `TahunAjaranActivationTest`, `ActivityLogTest`, `DashboardTest`, `ProfileTest`, `Auth/*`, `Admin/*`) plus every new test added in Tasks 1–9.

- [ ] **Step 2: If anything fails, fix it in place and re-run**

Do not proceed to sign-off until `php artisan test` is fully green.

- [ ] **Step 3: Final commit (only if Step 2 required fixes)**

```bash
git add -A
git commit -m "fix: address regressions found in M1 SPMB full test pass"
```
