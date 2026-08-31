# Identity v1 — Person Master Entity — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Introduce `persons` as the single master identity table behind `Guru`, `Karyawan`, `OrangTua`, `Siswa`, and `CalonMurid`, replacing 5 independently-duplicated identity column sets with one source of truth, without breaking any of the 87 existing `$user->guru`-style call sites.

**Architecture:** Class Table Inheritance — `persons` (shared identity fields) + role tables each carrying a `person_id` FK. `persons.user_id → users.id` (not the reverse) so `User::guru()`/`karyawan()`/`orangTua()`/`siswa()` become native Eloquent `hasOneThrough()` relations. All writes to identity data go through `App\Domains\Identity\Actions\*PersonAction` classes; all 5 role models grow accessor shims so existing reads (`$siswa->nama_lengkap`, `$guru->nama`) keep working untouched.

**Tech Stack:** Laravel 12, PHP 8.3, Pest v4, MySQL.

## Global Constraints

These come from `.agents/specs/2026-08-29-identity-v1-person-master-entity.md` (spec) and are binding on every task below:

- **FK direction is `persons.user_id → users.id`, never `users.person_id → persons.id`.** This is what makes `hasOneThrough` work without touching 87 existing call sites (spec §2.3).
- **`persons.yayasan_id` is NOT NULL, with `UNIQUE(yayasan_id, nik_hash)` — never `UNIQUE(nik_hash)` global.** A person with the same NIK in two different yayasan is two independent `Person` rows, by design (spec §1.2). Never "fix" this into a global unique.
- **`yayasan_id` is never accepted as free caller input.** It is always derived transitively — from `Lembaga::find($lembagaId)->yayasan_id`, or from the acting admin's own `yayasan_id` when there is no lembaga — exactly like `GuruController::resolveLembagaId()` already does for `lembaga_id` today. A second-layer assertion catches any future caller that tries to pass both.
- **No hard deletes on `persons` or `users`.** Merge and deactivate are always soft (spec §6, §2.7).
- **No dual-write to legacy columns during the transition.** Once code cutover happens for a table, new inserts leave the old identity columns NULL. They are not kept in sync — they simply sit unused until dropped in the final, separately-run migration.
- **The legacy-column-drop migration (Task 27) is NOT part of this release.** It ships later, after a full production cycle of verification, exactly as spec §8 step 6 and the user's explicit instruction require.
- **The new `OrangTua` tenant scope is additive, not a replacement.** `OrangTuaController::authorizeLembaga()`'s existing siswa-based filter stays exactly as-is; the new scope is a second, structurally-enforced layer that closes the yayasan-actor leak (see Task 13).
- **Multi-role is native, not a state machine.** One `person_id` can have rows in `guru`, `karyawan`, and `orang_tua` simultaneously. Never add an "active role" flag or exclusivity constraint across role tables.

---

## Stage 1 — Schema (Tasks 1–3)

### Task 1: Create `persons` table

**Files:**
- Create: `database/migrations/2026_08_29_000001_create_persons_table.php`
- Test: `tests/Feature/Identity/PersonsTableTest.php`

**Interfaces:**
- Produces: `persons` table with columns exactly as spec §3 DDL — later tasks assume this schema verbatim.

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yayasan_id')->constrained('yayasan')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->text('nik')->nullable();
            $table->char('nik_hash', 64)->nullable();
            $table->string('nama_lengkap');
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('agama', 50)->nullable();
            $table->string('kewarganegaraan', 50)->default('WNI');
            $table->string('no_hp', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('alamat_jalan')->nullable();
            $table->string('rt', 5)->nullable();
            $table->string('rw', 5)->nullable();
            $table->string('desa_kelurahan')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('kabupaten_kota')->nullable();
            $table->string('provinsi')->nullable();
            $table->string('kode_pos', 10)->nullable();
            $table->foreignId('merged_into_person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->timestamp('deactivated_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['yayasan_id', 'nik_hash'], 'uq_persons_yayasan_nik');
            $table->index('nama_lengkap');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('merged_into_user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merged_into_user_id');
        });

        Schema::dropIfExists('persons');
    }
};
```

- [ ] **Step 2: Write the failing test**

```php
<?php

use Illuminate\Support\Facades\Schema;

it('creates the persons table with the correct columns and unique constraint', function () {
    expect(Schema::hasTable('persons'))->toBeTrue();
    expect(Schema::hasColumns('persons', [
        'id', 'yayasan_id', 'user_id', 'nik', 'nik_hash', 'nama_lengkap',
        'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama', 'kewarganegaraan',
        'no_hp', 'email', 'alamat_jalan', 'rt', 'rw', 'desa_kelurahan', 'kecamatan',
        'kabupaten_kota', 'provinsi', 'kode_pos', 'merged_into_person_id',
        'deactivated_at', 'deleted_at', 'created_at', 'updated_at',
    ]))->toBeTrue();
    expect(Schema::hasColumn('users', 'merged_into_user_id'))->toBeTrue();
});
```

- [ ] **Step 3: Run migration and test**

Run: `php artisan migrate --pretend` (sanity check SQL), then `php artisan test --filter=PersonsTableTest --compact`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_08_29_000001_create_persons_table.php tests/Feature/Identity/PersonsTableTest.php
git commit -m "feat(identity): create persons master identity table"
```

---

### Task 2: `Person` model with tenant scope and `nik_hash` hook

**Files:**
- Create: `app/Domains/Identity/Models/Person.php`
- Create: `app/Models/Scopes/YayasanScope.php`
- Create: `database/factories/PersonFactory.php`
- Test: `tests/Feature/Identity/PersonModelTest.php`

**Interfaces:**
- Consumes: `persons` table from Task 1.
- Produces: `Person` model at `App\Domains\Identity\Models\Person`, with relations `user()`, `guru()`, `karyawan()`, `orangTua()`, `siswa()` (all against the not-yet-existing `person_id` columns — these become live once Task 3 lands, but are safe to define now).

**Why `Person` does NOT use the `BelongsToTenant` trait, and does NOT reuse `TenantScope` either:** `BelongsToTenant` (`app/Models/Concerns/BelongsToTenant.php`) registers `TenantScope` AND a `creating()` hook that auto-fills `lembaga_id`. `persons` has no `lembaga_id` column at all — its boundary is `yayasan_id` directly.

**Correction found during implementation (recorded here so the reasoning is not re-derived incorrectly later):** an earlier version of this plan assumed `TenantScope` could be registered directly on `Person`, reasoning that its `'yayasan'`-actor branch already has a `yayasan_id` fallback (the same branch `Karyawan`'s pool rows rely on). Reading `TenantScope::apply()` in full during Task 2's implementation showed this is incomplete: the `yayasan_id` fallback only fires for a `'yayasan'`-level actor with NO `active_lembaga_id` session value set. Every other path — a `'yayasan'`-level actor WITH an active lembaga selected (line ~56), and the final unconditional branch for `'lembaga'`-level and `'diri_sendiri'`-level actors (line ~88) — filters with `where($model->getTable().'.lembaga_id', ...)` unconditionally, regardless of whether the model even has that column. `persons` never has `lembaga_id`, so any of those paths throws `SQLSTATE[42S22]: Column not found`. This is not a test-setup artifact — an ordinary lembaga-scoped admin (the most common actor type in this app) would hit this in production the moment they queried `Person`.

Patching `TenantScope` itself was rejected: it is a shared, security-relevant scope class used by every tenant-scoped model in the app (Guru, Karyawan, Siswa, Rpp, AsetBarang, Gedung, KategoriAset, Ruangan, User, and more), and a change to its filtering logic needs its own deliberate review — not a change bundled into an unrelated task for a brand-new model.

**Corrected design:** `Person` gets its own dedicated global scope, `App\Models\Scopes\YayasanScope`, registered directly in `Person::booted()`. Unlike `TenantScope`, it has exactly one rule, because `persons.yayasan_id` is the ONLY tenant boundary this table has: for any actor whose `widestScopeLevel()` is not `'platform'`, resolve the actor's effective yayasan_id (their own `yayasan_id` if they carry one, otherwise `$actingUser->lembaga->yayasan_id`), and filter `where('yayasan_id', $resolvedYayasanId)`. Platform-level actors are unscoped, same as `TenantScope`'s convention. This mirrors the actor-resolution pattern `PersonTenantScope` will later use for `OrangTua` in Task 13 (that one filters through a `whereHas('person', ...)` relation instead of a local column — the two scopes solve the same problem at two different distances from `persons.yayasan_id`).

- [ ] **Step 1: Write the model**

```php
<?php

namespace App\Domains\Identity\Models;

use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\OrangTua;
use App\Models\Scopes\YayasanScope;
use App\Models\Siswa;
use App\Models\User;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'persons';

    protected $fillable = [
        'yayasan_id', 'user_id', 'nik', 'nama_lengkap', 'jenis_kelamin',
        'tempat_lahir', 'tanggal_lahir', 'agama', 'kewarganegaraan', 'no_hp',
        'email', 'alamat_jalan', 'rt', 'rw', 'desa_kelurahan', 'kecamatan',
        'kabupaten_kota', 'provinsi', 'kode_pos', 'merged_into_person_id',
        'deactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'nik' => 'encrypted',
            'tanggal_lahir' => 'date',
            'deactivated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new YayasanScope);

        static::saving(function (Person $person) {
            $person->nik_hash = $person->nik ? hash('sha256', $person->nik) : null;
        });
    }

    protected static function newFactory(): PersonFactory
    {
        return PersonFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guru(): HasOne
    {
        return $this->hasOne(Guru::class);
    }

    public function karyawan(): HasOne
    {
        return $this->hasOne(Karyawan::class);
    }

    public function orangTua(): HasOne
    {
        return $this->hasOne(OrangTua::class);
    }

    public function siswa(): HasOne
    {
        return $this->hasOne(Siswa::class);
    }
}
```

- [ ] **Step 1b: Write the dedicated `YayasanScope`**

```php
<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class YayasanScope implements Scope
{
    private static bool $resolvingActingUser = false;

    public function apply(Builder $builder, Model $model): void
    {
        if (self::$resolvingActingUser) {
            return;
        }

        self::$resolvingActingUser = true;

        try {
            $userId = auth()->id();
        } finally {
            self::$resolvingActingUser = false;
        }

        if (! $userId) {
            return;
        }

        $actingUser = User::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)->find($userId);

        if (! $actingUser) {
            return;
        }

        if ($actingUser->widestScopeLevel() === 'platform') {
            return;
        }

        $yayasanId = $actingUser->yayasan_id ?? $actingUser->lembaga?->yayasan_id;

        if ($yayasanId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->getTable().'.yayasan_id', $yayasanId);
    }
}
```

This deliberately does NOT reuse or modify `TenantScope` (see the rationale above) — `persons.yayasan_id` is the table's only tenant boundary, so the scope needs exactly one rule, not `TenantScope`'s three lembaga-first branches.

- [ ] **Step 2: Write the factory**

```php
<?php

namespace Database\Factories;

use App\Domains\Identity\Models\Person;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonFactory extends Factory
{
    protected $model = Person::class;

    public function definition(): array
    {
        return [
            'yayasan_id' => Yayasan::factory(),
            'nik' => (string) fake()->unique()->numerify('################'),
            'nama_lengkap' => fake()->name(),
            'jenis_kelamin' => fake()->randomElement(['L', 'P']),
            'tempat_lahir' => fake()->city(),
            'tanggal_lahir' => fake()->date(),
            'agama' => fake()->randomElement(['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha']),
            'no_hp' => fake()->numerify('08##########'),
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}
```

- [ ] **Step 3: Write the failing test**

```php
<?php

use App\Domains\Identity\Models\Person;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;

it('computes nik_hash on save', function () {
    $person = Person::factory()->create(['nik' => '1234567890123456']);

    expect($person->nik_hash)->toBe(hash('sha256', '1234567890123456'));
});

it('scopes persons to the acting yayasan_id like other tenant models', function () {
    $yayasanA = Yayasan::factory()->create();
    $yayasanB = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);

    Person::factory()->create(['yayasan_id' => $yayasanA->id]);
    Person::factory()->create(['yayasan_id' => $yayasanB->id]);

    $admin = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $this->actingAs($admin);

    expect(Person::count())->toBe(1);
});
```

- [ ] **Step 4: Run test to verify it fails**

Run: `php artisan test --filter=PersonModelTest --compact`
Expected: FAIL (class `App\Domains\Identity\Models\Person` not found — write it first if run out of order)

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=PersonModelTest --compact`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Identity/Models/Person.php database/factories/PersonFactory.php tests/Feature/Identity/PersonModelTest.php
git commit -m "feat(identity): add Person model with tenant scope and nik_hash hook"
```

---

### Task 3: Add nullable `person_id` to the 5 role tables AND make legacy identity columns nullable, in the same migration

**This is the mandatory cutover-ordering fix.** New `Person`-backed inserts (from Task 6 onward) stop populating the old identity columns on `guru`/`karyawan`/`orang_tua`/`siswa`. If those columns are still `NOT NULL` at that point, every new insert fails. This migration makes both changes atomically, and must run — and be verified in staging/dev — before Task 14 (the first code-cutover task) is ever deployed. Do not defer the nullability change to a later migration.

**Files:**
- Create: `database/migrations/2026_08_29_000002_add_person_id_and_relax_identity_columns.php`
- Test: `tests/Feature/Identity/PersonIdColumnsTest.php`

**Interfaces:**
- Consumes: `persons` table (Task 1).
- Produces: `person_id` (nullable, unsigned big integer, no FK constraint yet — the FK and NOT NULL constraint land in Task 27 after backfill) on `guru`, `karyawan`, `orang_tua`, `siswa`, `calon_murid`. Legacy NOT NULL columns listed below become nullable.

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guru', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable()->after('id');
            $table->text('nik')->nullable()->change();
            $table->string('nik_hash', 64)->nullable()->change();
            $table->string('nama')->nullable()->change();
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable()->change();
            $table->string('kewarganegaraan')->nullable()->default('WNI')->change();
        });

        Schema::table('karyawan', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable()->after('id');
            $table->text('nik')->nullable()->change();
            $table->string('nik_hash', 64)->nullable()->change();
            $table->string('nama')->nullable()->change();
        });

        Schema::table('orang_tua', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable()->after('id');
            $table->string('nama_lengkap')->nullable()->change();
            $table->string('nik', 16)->nullable()->change();
            $table->string('no_hp', 20)->nullable()->change();
        });

        Schema::table('siswa', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable()->after('id');
            $table->string('nama_lengkap')->nullable()->change();
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable()->change();
        });

        Schema::table('calon_murid', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable()->after('id');
            $table->text('nik')->nullable()->change();
            $table->string('nik_hash', 64)->nullable()->change();
            $table->string('nama_lengkap')->nullable()->change();
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable()->change();
            $table->string('tempat_lahir')->nullable()->change();
            $table->date('tanggal_lahir')->nullable()->change();
            $table->string('agama')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('calon_murid', function (Blueprint $table) {
            $table->dropColumn('person_id');
            $table->text('nik')->nullable(false)->change();
            $table->string('nik_hash', 64)->nullable(false)->change();
            $table->string('nama_lengkap')->nullable(false)->change();
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable(false)->change();
            $table->string('tempat_lahir')->nullable(false)->change();
            $table->date('tanggal_lahir')->nullable(false)->change();
            $table->string('agama')->nullable(false)->change();
        });

        Schema::table('siswa', function (Blueprint $table) {
            $table->dropColumn('person_id');
            $table->string('nama_lengkap')->nullable(false)->change();
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable(false)->change();
        });

        Schema::table('orang_tua', function (Blueprint $table) {
            $table->dropColumn('person_id');
            $table->string('nama_lengkap')->nullable(false)->change();
            $table->string('nik', 16)->nullable(false)->change();
            $table->string('no_hp', 20)->nullable(false)->change();
        });

        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropColumn('person_id');
            $table->text('nik')->nullable(false)->change();
            $table->string('nik_hash', 64)->nullable(false)->change();
            $table->string('nama')->nullable(false)->change();
        });

        Schema::table('guru', function (Blueprint $table) {
            $table->dropColumn('person_id');
            $table->text('nik')->nullable(false)->change();
            $table->string('nik_hash', 64)->nullable(false)->change();
            $table->string('nama')->nullable(false)->change();
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable(false)->change();
            $table->string('kewarganegaraan')->nullable(false)->default('WNI')->change();
        });
    }
};
```

**Correction found during review of Task 3's implementation (recorded here so it isn't lost or re-introduced):** an earlier version of this migration used `$table->string('jenis_kelamin')->nullable()->change()` and `$table->string('kewarganegaraan')->nullable()->change()`, plus redundant `->change()` calls on `tempat_lahir`/`tanggal_lahir`/`agama` on `guru`/`siswa` (all three were already nullable in the original `create_guru_table`/`create_siswa_table` migrations, so touching them there was unnecessary churn, not a correctness bug). The `jenis_kelamin`/`kewarganegaraan` versions were a real, materialized bug: Laravel's `Blueprint::change()` reconstructs a column's FULL definition from only what's specified in that call — it does not preserve the column's existing type or default automatically. `guru.jenis_kelamin`/`siswa.jenis_kelamin` were originally `enum('L','P')`; calling `->string(...)->change()` silently rewrote them to unconstrained `varchar(255)`, permanently accepting any value. `guru.kewarganegaraan` originally had `->default('WNI')`; omitting `->default('WNI')` from the `->change()` call silently dropped the default. Both defects were confirmed live against the applied migration in the dev database during review, and the original `down()` did not restore either property, making the migration irreversible to its true original schema. The code above is the corrected version: `jenis_kelamin` keeps its `enum(['L','P'])` type through both `up()` and `down()`, and `kewarganegaraan` keeps its `default('WNI')` through both. The rule this generalizes: **any `->change()` call on a column must restate every property of that column that must survive the change — type, default, and nullability — never just the one property being modified.** This applies to every other `->change()` call anywhere else in this plan and is worth an explicit self-check before writing any future `->change()` line.

**Second correction, found while manually verifying the fix against the live dev database:** the original column list for this task omitted `nik_hash` on `guru`/`karyawan` (both `string(64) UNIQUE`, NOT NULL by default), `nik` on `orang_tua` (`string(16)`, NOT NULL), and `no_hp` on `orang_tua` (`string(20)`, NOT NULL, found in a second pass after the first three were already fixed and the `orang_tua` insert-without-legacy-columns test still failed on this column) — all four are populated today only by legacy write paths that Tasks 14-16's `PersonService` cutover stops populating going forward, so leaving them NOT NULL would make every future `Guru::create()`/`Karyawan::create()`/`OrangTua::create()` call fail immediately once those tasks land. Worse, `calon_murid` was left entirely untouched beyond `person_id` in the original version of this task, even though `calon_murid.nik`, `nik_hash`, `nama_lengkap`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, and `agama` are ALL currently `NOT NULL` (see `database/migrations/2026_07_14_090000_create_calon_murid_table.php`), and Task 20's `CalonMurid::updateOrCreate(['person_id' => $person->id], ['yayasan_id' => $lembaga->yayasan_id])` call populates none of them — this would have failed immediately on the very first SPMB submission after Task 20 shipped, with no test in Task 3 ever catching it (Task 3's own tests only exercised `guru`). The code above now includes all of these in both `up()` and `down()`. **Generalized lesson for whoever implements or reviews any later task in this plan:** before treating "Task 3 relaxes the NOT NULL columns" as complete, cross-check every column a later task's `create()`/`updateOrCreate()` call omits against that table's actual `NOT NULL` columns in its original `create_*_table` migration — the plan's own prose summaries are not guaranteed exhaustive; the migration file is the ground truth.

Note: `doctrine/dbal` is NOT required here — this codebase already uses `Blueprint::change()` on MySQL without it (see `database/migrations/2026_08_26_100100_add_subjek_columns_to_komponen_penilaian_and_asesmen.php`), since Laravel's native MySQL grammar supports column changes without doctrine/dbal from Laravel 11 onward. Confirmed via `composer show doctrine/dbal` (not installed) and the sibling migration working correctly. No dependency approval needed.

- [ ] **Step 2: Write the failing test**

```php
<?php

use Illuminate\Support\Facades\Schema;

it('adds nullable person_id to all 5 role tables', function () {
    foreach (['guru', 'karyawan', 'orang_tua', 'siswa', 'calon_murid'] as $table) {
        expect(Schema::hasColumn($table, 'person_id'))->toBeTrue();
    }
});

it('allows inserting guru without legacy identity columns populated', function () {
    $lembaga = \App\Models\Lembaga::factory()->create();

    $id = \Illuminate\Support\Facades\DB::table('guru')->insertGetId([
        'lembaga_id' => $lembaga->id,
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'tetap',
        'status_aktif' => 'aktif',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($id)->toBeGreaterThan(0);
});
```

- [ ] **Step 3: Run migration and tests**

Run: `php artisan migrate`, then `php artisan test --filter=PersonIdColumnsTest --compact`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_08_29_000002_add_person_id_and_relax_identity_columns.php tests/Feature/Identity/PersonIdColumnsTest.php
git commit -m "feat(identity): add nullable person_id and relax legacy identity NOT NULL constraints"
```

---

## Stage 2 — Backfill and verification (Tasks 4–5)

### Task 4: Backfill command to populate `persons` and `person_id` from existing rows

**Files:**
- Create: `app/Console/Commands/BackfillPersonsFromRoleTables.php`
- Test: `tests/Feature/Identity/BackfillPersonsFromRoleTablesTest.php`

**Interfaces:**
- Consumes: `Person` model (Task 2), `person_id` columns (Task 3).
- Produces: `php artisan identity:backfill-persons` — idempotent (safe to re-run), never auto-merges NIK collisions within a yayasan, writes a report of any collision found instead.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Identity\Models\Person;
use App\Models\CalonMurid;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\Yayasan;

it('backfills a Person for each guru row and links person_id', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'nik' => '1111111111111111', 'nama' => 'Budi Santoso']);

    $this->artisan('identity:backfill-persons')->assertExitCode(0);

    $guru->refresh();
    expect($guru->person_id)->not->toBeNull();

    $person = Person::withoutGlobalScopes()->find($guru->person_id);
    expect($person->yayasan_id)->toBe($yayasan->id);
    expect($person->nama_lengkap)->toBe('Budi Santoso');
    expect($person->nik_hash)->toBe(hash('sha256', '1111111111111111'));
});

it('backfills karyawan using its own yayasan_id when lembaga_id is null (pool karyawan)', function () {
    $yayasan = Yayasan::factory()->create();
    $karyawan = Karyawan::factory()->create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null, 'nik' => '2222222222222222']);

    $this->artisan('identity:backfill-persons')->assertExitCode(0);

    $karyawan->refresh();
    $person = Person::withoutGlobalScopes()->find($karyawan->person_id);
    expect($person->yayasan_id)->toBe($yayasan->id);
});

it('backfills orang_tua by deriving yayasan_id from its linked siswa', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $ortu = OrangTua::factory()->create();
    $siswa->orangTua()->attach($ortu->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $this->artisan('identity:backfill-persons')->assertExitCode(0);

    $ortu->refresh();
    $person = Person::withoutGlobalScopes()->find($ortu->person_id);
    expect($person->yayasan_id)->toBe($yayasan->id);
});

it('backfills siswa and calon_murid rows', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id]);

    $this->artisan('identity:backfill-persons')->assertExitCode(0);

    expect($siswa->refresh()->person_id)->not->toBeNull();
    expect($calonMurid->refresh()->person_id)->not->toBeNull();
});

it('is idempotent: running twice does not create duplicate Person rows', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->artisan('identity:backfill-persons')->assertExitCode(0);
    $countAfterFirst = Person::withoutGlobalScopes()->count();

    $this->artisan('identity:backfill-persons')->assertExitCode(0);
    expect(Person::withoutGlobalScopes()->count())->toBe($countAfterFirst);
});

it('reports a NIK collision within one yayasan instead of auto-merging', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Guru::factory()->create(['lembaga_id' => $lembaga->id, 'nik' => '3333333333333333']);
    Karyawan::factory()->create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nik' => '3333333333333333']);

    $this->artisan('identity:backfill-persons')
        ->expectsOutputToContain('NIK collision')
        ->assertExitCode(0);

    // Both rows still get linked to Person rows -- collision is reported, not blocked or auto-merged
    expect(Guru::first()->person_id)->not->toBeNull();
    expect(Karyawan::first()->person_id)->not->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=BackfillPersonsFromRoleTablesTest --compact`
Expected: FAIL (command not found)

- [ ] **Step 3: Write the command**

```php
<?php

namespace App\Console\Commands;

use App\Domains\Identity\Models\Person;
use App\Models\CalonMurid;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\OrangTua;
use App\Models\Siswa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillPersonsFromRoleTables extends Command
{
    protected $signature = 'identity:backfill-persons';

    protected $description = 'Backfill the persons master table from guru/karyawan/orang_tua/siswa/calon_murid rows.';

    /** @var array<int, array{table: string, nik_hash: ?string, yayasan_id: int}> */
    private array $seenThisRun = [];

    public function handle(): int
    {
        DB::transaction(function () {
            $this->backfillGuru();
            $this->backfillKaryawan();
            $this->backfillOrangTua();
            $this->backfillSiswa();
            $this->backfillCalonMurid();
        });

        $this->reportCollisions();

        return self::SUCCESS;
    }

    private function backfillGuru(): void
    {
        Guru::withoutGlobalScopes()->whereNull('person_id')->with('lembaga')->each(function (Guru $guru) {
            $person = $this->findOrCreatePerson(
                yayasanId: $guru->lembaga->yayasan_id,
                nik: $guru->nik,
                namaLengkap: $guru->nama,
                extra: [
                    'jenis_kelamin' => $guru->jenis_kelamin,
                    'tempat_lahir' => $guru->tempat_lahir,
                    'tanggal_lahir' => $guru->tanggal_lahir,
                    'agama' => $guru->agama,
                    'no_hp' => $guru->no_hp,
                    'email' => $guru->email,
                ],
                sourceTable: 'guru',
            );

            $guru->newQueryWithoutScopes()->whereKey($guru->id)->update(['person_id' => $person->id]);
        });
    }

    private function backfillKaryawan(): void
    {
        Karyawan::withoutGlobalScopes()->whereNull('person_id')->get()->each(function (Karyawan $karyawan) {
            $yayasanId = $karyawan->lembaga_id !== null
                ? $karyawan->lembaga->yayasan_id
                : $karyawan->yayasan_id;

            $person = $this->findOrCreatePerson(
                yayasanId: $yayasanId,
                nik: $karyawan->nik,
                namaLengkap: $karyawan->nama,
                extra: ['no_hp' => $karyawan->no_hp, 'email' => $karyawan->email],
                sourceTable: 'karyawan',
            );

            $karyawan->newQueryWithoutScopes()->whereKey($karyawan->id)->update(['person_id' => $person->id]);
        });
    }

    private function backfillOrangTua(): void
    {
        OrangTua::withoutGlobalScopes()->whereNull('person_id')->with('siswa.lembaga')->get()->each(function (OrangTua $ortu) {
            $siswa = $ortu->siswa->first();

            if ($siswa === null) {
                $this->warn("OrangTua id={$ortu->id} has no linked siswa -- cannot derive yayasan_id, skipped. Resolve manually.");

                return;
            }

            $person = $this->findOrCreatePerson(
                yayasanId: $siswa->lembaga->yayasan_id,
                nik: $ortu->nik,
                namaLengkap: $ortu->nama_lengkap,
                extra: ['no_hp' => $ortu->no_hp, 'email' => $ortu->email, 'alamat_jalan' => $ortu->alamat],
                sourceTable: 'orang_tua',
            );

            $ortu->newQueryWithoutScopes()->whereKey($ortu->id)->update(['person_id' => $person->id]);
        });
    }

    private function backfillSiswa(): void
    {
        Siswa::withoutGlobalScopes()->whereNull('person_id')->with('lembaga')->get()->each(function (Siswa $siswa) {
            $person = $this->findOrCreatePerson(
                yayasanId: $siswa->lembaga->yayasan_id,
                nik: null,
                namaLengkap: $siswa->nama_lengkap,
                extra: [
                    'jenis_kelamin' => $siswa->jenis_kelamin,
                    'tempat_lahir' => $siswa->tempat_lahir,
                    'tanggal_lahir' => $siswa->tanggal_lahir,
                ],
                sourceTable: 'siswa',
            );

            $siswa->newQueryWithoutScopes()->whereKey($siswa->id)->update(['person_id' => $person->id]);
        });
    }

    private function backfillCalonMurid(): void
    {
        CalonMurid::withoutGlobalScopes()->whereNull('person_id')->get()->each(function (CalonMurid $calon) {
            $person = $this->findOrCreatePerson(
                yayasanId: $calon->yayasan_id,
                nik: $calon->nik,
                namaLengkap: $calon->nama_lengkap,
                extra: [
                    'jenis_kelamin' => $calon->jenis_kelamin,
                    'tempat_lahir' => $calon->tempat_lahir,
                    'tanggal_lahir' => $calon->tanggal_lahir,
                    'agama' => $calon->agama,
                    'no_hp' => $calon->no_telepon,
                    'email' => $calon->email_kontak,
                ],
                sourceTable: 'calon_murid',
            );

            $calon->newQueryWithoutScopes()->whereKey($calon->id)->update(['person_id' => $person->id]);
        });
    }

    /** @param array<string, mixed> $extra */
    private function findOrCreatePerson(int $yayasanId, ?string $nik, string $namaLengkap, array $extra, string $sourceTable): Person
    {
        $nikHash = $nik ? hash('sha256', $nik) : null;

        if ($nikHash !== null) {
            $existing = Person::withoutGlobalScopes()
                ->where('yayasan_id', $yayasanId)
                ->where('nik_hash', $nikHash)
                ->first();

            if ($existing !== null) {
                $this->seenThisRun[] = ['table' => $sourceTable, 'nik_hash' => $nikHash, 'yayasan_id' => $yayasanId];

                return $existing;
            }
        }

        return Person::withoutGlobalScopes()->create(array_merge($extra, [
            'yayasan_id' => $yayasanId,
            'nik' => $nik,
            'nama_lengkap' => $namaLengkap,
        ]));
    }

    private function reportCollisions(): void
    {
        $byHash = collect($this->seenThisRun)->groupBy(fn (array $row) => $row['yayasan_id'].'|'.$row['nik_hash']);

        foreach ($byHash as $group) {
            if ($group->count() < 1) {
                continue;
            }

            $tables = $group->pluck('table')->unique()->implode(', ');
            $this->warn("NIK collision within yayasan_id={$group->first()['yayasan_id']}: same NIK shared across [{$tables}]. Person rows were reused, not merged -- review manually.");
        }
    }
}
```

Note: `findOrCreatePerson` reusing an existing `Person` across role tables (e.g. Guru and Karyawan sharing one NIK) is intentional — this is exactly the multi-role case the design supports, and is reported (not blocked) so an operator can confirm it's the same real person, not a data-entry coincidence.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=BackfillPersonsFromRoleTablesTest --compact`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/BackfillPersonsFromRoleTables.php tests/Feature/Identity/BackfillPersonsFromRoleTablesTest.php
git commit -m "feat(identity): add idempotent backfill command for persons from role tables"
```

---

### Task 5: Backfill verification command

**Files:**
- Create: `app/Console/Commands/VerifyPersonsBackfill.php`
- Test: `tests/Feature/Identity/VerifyPersonsBackfillTest.php`

**Interfaces:**
- Consumes: backfilled data from Task 4.
- Produces: `php artisan identity:verify-backfill` — exits non-zero (and prints exactly which table/ids) if any row in the 5 role tables still has `person_id IS NULL`. This is the gate before Stage 3 (code cutover) is allowed to proceed in any environment.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Guru;
use App\Models\Lembaga;

it('fails when a guru row still has no person_id', function () {
    $lembaga = Lembaga::factory()->create();
    Guru::factory()->create(['lembaga_id' => $lembaga->id, 'person_id' => null]);

    $this->artisan('identity:verify-backfill')
        ->expectsOutputToContain('guru')
        ->assertExitCode(1);
});

it('succeeds when every role table row has a person_id', function () {
    $lembaga = Lembaga::factory()->create();
    Guru::factory()->create(['lembaga_id' => $lembaga->id, 'person_id' => \App\Domains\Identity\Models\Person::factory()->create(['yayasan_id' => $lembaga->yayasan_id])->id]);

    $this->artisan('identity:verify-backfill')->assertExitCode(0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=VerifyPersonsBackfillTest --compact`
Expected: FAIL (command not found)

- [ ] **Step 3: Write the command**

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyPersonsBackfill extends Command
{
    protected $signature = 'identity:verify-backfill';

    protected $description = 'Verify every role-table row has a non-null person_id before code cutover proceeds.';

    public function handle(): int
    {
        $ok = true;

        foreach (['guru', 'karyawan', 'orang_tua', 'siswa', 'calon_murid'] as $table) {
            $missing = DB::table($table)->whereNull('person_id')->pluck('id');

            if ($missing->isNotEmpty()) {
                $ok = false;
                $this->error("{$table}: {$missing->count()} row(s) missing person_id -- ids: {$missing->implode(', ')}");
            }
        }

        if ($ok) {
            $this->info('All role-table rows have person_id populated. Safe to proceed with code cutover.');
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=VerifyPersonsBackfillTest --compact`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/VerifyPersonsBackfill.php tests/Feature/Identity/VerifyPersonsBackfillTest.php
git commit -m "feat(identity): add backfill verification command as cutover gate"
```

---

## Stage 3 — `PersonService` (Tasks 6–10)

### Task 6: `CreatePersonAction` with transitive `yayasan_id` resolution and NIK dedup

**Files:**
- Create: `app/Domains/Identity/Actions/CreatePersonAction.php`
- Create: `app/Domains/Identity/Exceptions/PersonAlreadyExistsException.php`
- Test: `tests/Feature/Identity/CreatePersonActionTest.php`

**Interfaces:**
- Consumes: `Person` model (Task 2).
- Produces: `CreatePersonAction::execute(array $identityData, ?int $lembagaId, ?int $actingYayasanId): Person`. `$identityData` keys match `Person::$fillable` minus `yayasan_id`. Throws `PersonAlreadyExistsException` when `$identityData['nik']` collides within the resolved yayasan. Later tasks (11+) call this as `app(CreatePersonAction::class)->execute(...)`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Domains\Identity\Actions\CreatePersonAction;
use App\Domains\Identity\Exceptions\PersonAlreadyExistsException;
use App\Models\Lembaga;
use App\Models\Yayasan;

it('creates a Person deriving yayasan_id from the given lembaga_id', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $person = app(CreatePersonAction::class)->execute(
        identityData: ['nama_lengkap' => 'Siti Aminah', 'nik' => '9999999999999999'],
        lembagaId: $lembaga->id,
        actingYayasanId: null,
    );

    expect($person->yayasan_id)->toBe($yayasan->id);
});

it('creates a Person using actingYayasanId when there is no lembaga (pool entity)', function () {
    $yayasan = Yayasan::factory()->create();

    $person = app(CreatePersonAction::class)->execute(
        identityData: ['nama_lengkap' => 'Andi Wijaya'],
        lembagaId: null,
        actingYayasanId: $yayasan->id,
    );

    expect($person->yayasan_id)->toBe($yayasan->id);
});

it('aborts with 422 when neither lembagaId nor actingYayasanId is given', function () {
    app(CreatePersonAction::class)->execute(
        identityData: ['nama_lengkap' => 'Tanpa Konteks'],
        lembagaId: null,
        actingYayasanId: null,
    );
})->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);

it('rejects a duplicate NIK within the same yayasan', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    app(CreatePersonAction::class)->execute(
        identityData: ['nama_lengkap' => 'Orang Pertama', 'nik' => '1010101010101010'],
        lembagaId: $lembaga->id,
        actingYayasanId: null,
    );

    app(CreatePersonAction::class)->execute(
        identityData: ['nama_lengkap' => 'Orang Kedua Nik Sama', 'nik' => '1010101010101010'],
        lembagaId: $lembaga->id,
        actingYayasanId: null,
    );
})->throws(PersonAlreadyExistsException::class);

it('allows the same NIK across two different yayasan -- this is a contract, not a bug', function () {
    $yayasanA = Yayasan::factory()->create();
    $yayasanB = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);

    app(CreatePersonAction::class)->execute(
        identityData: ['nama_lengkap' => 'Orang A', 'nik' => '2020202020202020'],
        lembagaId: $lembagaA->id,
        actingYayasanId: null,
    );

    $personB = app(CreatePersonAction::class)->execute(
        identityData: ['nama_lengkap' => 'Orang B', 'nik' => '2020202020202020'],
        lembagaId: $lembagaB->id,
        actingYayasanId: null,
    );

    expect($personB->yayasan_id)->toBe($yayasanB->id);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=CreatePersonActionTest --compact`
Expected: FAIL (class not found)

- [ ] **Step 3: Write the exception**

```php
<?php

namespace App\Domains\Identity\Exceptions;

use App\Domains\Identity\Models\Person;
use RuntimeException;

class PersonAlreadyExistsException extends RuntimeException
{
    public function __construct(public readonly Person $existing)
    {
        parent::__construct("Person with this NIK already exists in this yayasan (person_id={$existing->id}).");
    }
}
```

- [ ] **Step 4: Write the action**

```php
<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Exceptions\PersonAlreadyExistsException;
use App\Domains\Identity\Models\Person;
use App\Models\Lembaga;

class CreatePersonAction
{
    /** @param array<string, mixed> $identityData */
    public function execute(array $identityData, ?int $lembagaId, ?int $actingYayasanId): Person
    {
        $yayasanId = $this->resolveYayasanId($lembagaId, $actingYayasanId);

        if (! empty($identityData['nik'])) {
            $nikHash = hash('sha256', $identityData['nik']);
            $existing = Person::withoutGlobalScope(\App\Models\Scopes\YayasanScope::class)
                ->where('yayasan_id', $yayasanId)
                ->where('nik_hash', $nikHash)
                ->first();

            if ($existing !== null) {
                throw new PersonAlreadyExistsException($existing);
            }
        }

        return Person::create([...$identityData, 'yayasan_id' => $yayasanId]);
    }

    private function resolveYayasanId(?int $lembagaId, ?int $actingYayasanId): int
    {
        if ($lembagaId !== null) {
            $lembagaYayasanId = Lembaga::findOrFail($lembagaId)->yayasan_id;

            abort_if(
                $actingYayasanId !== null && $actingYayasanId !== $lembagaYayasanId,
                422,
                'yayasan_id yang diberikan tidak cocok dengan yayasan pemilik lembaga.'
            );

            return $lembagaYayasanId;
        }

        abort_if($actingYayasanId === null, 422, 'Konteks yayasan tidak dapat ditentukan.');

        return $actingYayasanId;
    }
}
```

**Correction found during implementation (recorded so it isn't re-introduced):** the dedup lookup must bypass `Person`'s own `YayasanScope`, not rely on plain `Person::where(...)`. `YayasanScope` is a global scope that filters by the ACTING AUTHENTICATED USER's own resolved yayasan_id (see Task 2's `YayasanScope`), which is a separate, independent filter from the explicit `->where('yayasan_id', $yayasanId)` in this query. In the common case (an actor creating a Person inside their own yayasan) both conditions agree and nothing breaks — which is exactly why none of the 5 original tests above (all run with no authenticated user, so the scope no-ops) catch this. But if `$yayasanId` (the action's resolved target) ever differs from the acting user's own yayasan, the two ANDed `yayasan_id` conditions become mutually exclusive, `first()` silently returns `null` regardless of whether a duplicate truly exists, and the duplicate insert proceeds to fail loudly at the DB level instead (`uq_persons_yayasan_nik` unique constraint violation surfaces as a raw `UniqueConstraintViolationException`, not the intended `PersonAlreadyExistsException`). The fix is `Person::withoutGlobalScope(YayasanScope::class)->where('yayasan_id', $yayasanId)->where('nik_hash', $nikHash)->first()` (shown in the code above) — correct because the query already has the exact, explicit target yayasan_id to filter by, so bypassing the ambient-auth-context scope removes an unnecessary and incorrect dependency. Two additional regression tests are required beyond the 5 above (both run with `actingAs()` so the scope is actually live): one proving the common case still works when the acting user's yayasan matches the target, one proving the dedup check still catches a duplicate even when the acting user belongs to a DIFFERENT yayasan than the target (this second test is the one that fails without the fix).

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=CreatePersonActionTest --compact`
Expected: PASS (7 tests: the 5 above plus the 2 `YayasanScope`-interaction regression tests described above)

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Identity/Actions/CreatePersonAction.php app/Domains/Identity/Exceptions/PersonAlreadyExistsException.php tests/Feature/Identity/CreatePersonActionTest.php
git commit -m "feat(identity): add CreatePersonAction with transitive yayasan_id resolution"
```

---

### Task 7: `PersonDuplicateFinder` for NIK-absent fuzzy matching

**Files:**
- Create: `app/Domains/Identity/Services/PersonDuplicateFinder.php`
- Test: `tests/Feature/Identity/PersonDuplicateFinderTest.php`

**Interfaces:**
- Consumes: `Person` model (Task 2).
- Produces: `PersonDuplicateFinder::find(string $namaLengkap, ?string $tanggalLahir): Collection<Person>` — non-blocking candidate list, scoped to the acting tenant via `Person`'s existing global scope.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Identity\Models\Person;
use App\Domains\Identity\Services\PersonDuplicateFinder;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;

it('finds a candidate with matching nama_lengkap and tanggal_lahir', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $this->actingAs($admin);

    Person::factory()->create(['yayasan_id' => $yayasan->id, 'nama_lengkap' => 'Rahmat Hidayat', 'tanggal_lahir' => '2000-01-01']);

    $candidates = app(PersonDuplicateFinder::class)->find('Rahmat Hidayat', '2000-01-01');

    expect($candidates)->toHaveCount(1);
});

it('returns no candidates when nothing matches', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $this->actingAs($admin);

    $candidates = app(PersonDuplicateFinder::class)->find('Nama Tidak Ada', null);

    expect($candidates)->toBeEmpty();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PersonDuplicateFinderTest --compact`
Expected: FAIL (class not found)

- [ ] **Step 3: Write the service**

```php
<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\Person;
use Illuminate\Database\Eloquent\Collection;

class PersonDuplicateFinder
{
    public function find(string $namaLengkap, ?string $tanggalLahir): Collection
    {
        return Person::query()
            ->where('nama_lengkap', $namaLengkap)
            ->when($tanggalLahir !== null, fn ($q) => $q->where('tanggal_lahir', $tanggalLahir))
            ->get();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=PersonDuplicateFinderTest --compact`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Identity/Services/PersonDuplicateFinder.php tests/Feature/Identity/PersonDuplicateFinderTest.php
git commit -m "feat(identity): add PersonDuplicateFinder for NIK-absent dedup warning"
```

---

### Task 8: `UpdatePersonAction`

**Files:**
- Create: `app/Domains/Identity/Actions/UpdatePersonAction.php`
- Test: `tests/Feature/Identity/UpdatePersonActionTest.php`

**Interfaces:**
- Consumes: `Person` model (Task 2).
- Produces: `UpdatePersonAction::execute(Person $person, array $identityData): Person`. Never accepts or changes `yayasan_id` — that field is immutable after creation (moving a Person between yayasan is a merge/re-creation decision, not an update).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Identity\Actions\UpdatePersonAction;
use App\Domains\Identity\Models\Person;
use App\Models\Yayasan;

it('updates identity fields but ignores yayasan_id if present in the payload', function () {
    $yayasan = Yayasan::factory()->create();
    $other = Yayasan::factory()->create();
    $person = Person::factory()->create(['yayasan_id' => $yayasan->id, 'nama_lengkap' => 'Nama Lama']);

    $updated = app(UpdatePersonAction::class)->execute($person, [
        'nama_lengkap' => 'Nama Baru',
        'yayasan_id' => $other->id,
    ]);

    expect($updated->nama_lengkap)->toBe('Nama Baru');
    expect($updated->yayasan_id)->toBe($yayasan->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=UpdatePersonActionTest --compact`
Expected: FAIL (class not found)

- [ ] **Step 3: Write the action**

```php
<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\Person;

class UpdatePersonAction
{
    /** @param array<string, mixed> $identityData */
    public function execute(Person $person, array $identityData): Person
    {
        unset($identityData['yayasan_id']);

        $person->update($identityData);

        return $person;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=UpdatePersonActionTest --compact`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Identity/Actions/UpdatePersonAction.php tests/Feature/Identity/UpdatePersonActionTest.php
git commit -m "feat(identity): add UpdatePersonAction with immutable yayasan_id"
```

---

### Task 9: `MergePersonsAction` and `ConflictingUserAccountsException`

**Files:**
- Create: `app/Domains/Identity/Actions/MergePersonsAction.php`
- Create: `app/Domains/Identity/Exceptions/ConflictingUserAccountsException.php`
- Test: `tests/Feature/Identity/MergePersonsActionTest.php`

**Interfaces:**
- Consumes: `Person` model (Task 2).
- Produces: `MergePersonsAction::execute(Person $losing, Person $winning): void`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Domains\Identity\Actions\MergePersonsAction;
use App\Domains\Identity\Exceptions\ConflictingUserAccountsException;
use App\Domains\Identity\Models\Person;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;

it('re-parents all role-table FKs to the winning person and soft-deletes the losing one', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $losing = Person::factory()->create(['yayasan_id' => $yayasan->id]);
    $winning = Person::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'person_id' => $losing->id]);
    $karyawan = Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'yayasan_id' => $yayasan->id, 'person_id' => $losing->id]);

    app(MergePersonsAction::class)->execute($losing, $winning);

    expect($guru->refresh()->person_id)->toBe($winning->id);
    expect($karyawan->refresh()->person_id)->toBe($winning->id);
    expect($losing->refresh()->merged_into_person_id)->toBe($winning->id);
    expect(Person::withoutGlobalScopes()->find($losing->id))->not->toBeNull();
    expect(Person::find($losing->id))->toBeNull(); // soft-deleted, excluded from default query
});

it('rejects merging two persons from different yayasan', function () {
    $losing = Person::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $winning = Person::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);

    app(MergePersonsAction::class)->execute($losing, $winning);
})->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);

it('throws ConflictingUserAccountsException when both persons already have a user_id', function () {
    $yayasan = Yayasan::factory()->create();
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $losing = Person::factory()->create(['yayasan_id' => $yayasan->id, 'user_id' => $userA->id]);
    $winning = Person::factory()->create(['yayasan_id' => $yayasan->id, 'user_id' => $userB->id]);

    app(MergePersonsAction::class)->execute($losing, $winning);
})->throws(ConflictingUserAccountsException::class);

it('carries the losing user_id onto the winning person when only the losing side has an account', function () {
    $yayasan = Yayasan::factory()->create();
    $user = User::factory()->create();
    $losing = Person::factory()->create(['yayasan_id' => $yayasan->id, 'user_id' => $user->id]);
    $winning = Person::factory()->create(['yayasan_id' => $yayasan->id, 'user_id' => null]);

    app(MergePersonsAction::class)->execute($losing, $winning);

    expect($winning->refresh()->user_id)->toBe($user->id);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=MergePersonsActionTest --compact`
Expected: FAIL (class not found)

- [ ] **Step 3: Write the exception**

```php
<?php

namespace App\Domains\Identity\Exceptions;

use App\Domains\Identity\Models\Person;
use RuntimeException;

class ConflictingUserAccountsException extends RuntimeException
{
    public function __construct(public readonly Person $losing, public readonly Person $winning)
    {
        parent::__construct(
            "Both Person #{$losing->id} and Person #{$winning->id} already have a linked user account. ".
            'An admin must explicitly choose which account survives before merging.'
        );
    }
}
```

- [ ] **Step 4: Write the action**

```php
<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Exceptions\ConflictingUserAccountsException;
use App\Domains\Identity\Models\Person;
use Illuminate\Support\Facades\DB;

class MergePersonsAction
{
    private const ROLE_TABLES = ['guru', 'karyawan', 'orang_tua', 'siswa'];

    public function execute(Person $losing, Person $winning): void
    {
        abort_if(
            $losing->yayasan_id !== $winning->yayasan_id,
            422,
            'Tidak bisa merge Person lintas yayasan -- itu dua identitas yang memang independen by design.'
        );

        if ($losing->user_id !== null && $winning->user_id !== null) {
            throw new ConflictingUserAccountsException($losing, $winning);
        }

        DB::transaction(function () use ($losing, $winning) {
            foreach (self::ROLE_TABLES as $table) {
                DB::table($table)->where('person_id', $losing->id)->update(['person_id' => $winning->id]);
            }

            if ($losing->user_id !== null && $winning->user_id === null) {
                // persons.user_id carries a unique constraint: clear it on the losing
                // side (and persist merged_into_person_id in the same statement) before
                // assigning it to the winning side, or the winning update violates the
                // constraint while the losing row still holds the same user_id.
                $carriedUserId = $losing->user_id;
                $losing->update(['user_id' => null, 'merged_into_person_id' => $winning->id]);
                $winning->update(['user_id' => $carriedUserId]);
            } else {
                $losing->update(['merged_into_person_id' => $winning->id]);
            }

            $losing->delete();
        });
    }
}
```

**Correction found during implementation (recorded so it isn't re-introduced):** the version above fixes a real bug in an earlier draft of this action that set `$winning->user_id = $losing->user_id` BEFORE clearing `$losing->user_id` in the database. Since `persons.user_id` carries a `UNIQUE` constraint (Task 1's DDL), and `$losing`'s row still held that same `user_id` value in the database at the moment `$winning->update(['user_id' => ...])` ran, the update failed with `SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry ... for key 'persons.persons_user_id_unique'`. The fix clears the losing side's `user_id` to `null` (combined with `merged_into_person_id` in one `update()` call to avoid an extra query) BEFORE assigning the carried value to `$winning`, all within the same `DB::transaction()` closure so atomicity is preserved. This is a rare but real ordering trap with unique constraints during a "move a unique value from row A to row B" operation — the value must leave A before it can land on B, or the DB sees both rows holding it simultaneously mid-statement.

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=MergePersonsActionTest --compact`
Expected: PASS (5 tests: the 4 above, plus one proving check-precedence — a cross-yayasan merge attempt where BOTH persons also have a `user_id` set must hit the yayasan-mismatch `abort_if` first, not `ConflictingUserAccountsException`, since the brief's `abort_if` runs strictly before the conflicting-accounts check)

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Identity/Actions/MergePersonsAction.php app/Domains/Identity/Exceptions/ConflictingUserAccountsException.php tests/Feature/Identity/MergePersonsActionTest.php
git commit -m "feat(identity): add MergePersonsAction with conflicting-user-accounts guard"
```

---

### Task 10: `DeactivatePersonAction` / `ReactivatePersonAction`

**Files:**
- Create: `app/Domains/Identity/Actions/DeactivatePersonAction.php`
- Create: `app/Domains/Identity/Actions/ReactivatePersonAction.php`
- Test: `tests/Feature/Identity/DeactivateReactivatePersonActionTest.php`

**Interfaces:**
- Consumes: `Person` model (Task 2).
- Produces: `DeactivatePersonAction::execute(Person $person): Person` (sets `deactivated_at`, does NOT soft-delete or touch the linked `User`'s `is_active` — that stays each role controller's own concern, e.g. `GuruController::updateStatus()`); `ReactivatePersonAction::execute(Person $person): Person` (clears `deactivated_at`).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Identity\Actions\DeactivatePersonAction;
use App\Domains\Identity\Actions\ReactivatePersonAction;
use App\Domains\Identity\Models\Person;

it('sets deactivated_at without soft-deleting', function () {
    $person = Person::factory()->create();

    app(DeactivatePersonAction::class)->execute($person);

    expect($person->refresh()->deactivated_at)->not->toBeNull();
    expect(Person::find($person->id))->not->toBeNull();
});

it('clears deactivated_at on reactivate', function () {
    $person = Person::factory()->create(['deactivated_at' => now()]);

    app(ReactivatePersonAction::class)->execute($person);

    expect($person->refresh()->deactivated_at)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DeactivateReactivatePersonActionTest --compact`
Expected: FAIL (class not found)

- [ ] **Step 3: Write the actions**

```php
<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\Person;

class DeactivatePersonAction
{
    public function execute(Person $person): Person
    {
        $person->update(['deactivated_at' => now()]);

        return $person;
    }
}
```

```php
<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\Person;

class ReactivatePersonAction
{
    public function execute(Person $person): Person
    {
        $person->update(['deactivated_at' => null]);

        return $person;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=DeactivateReactivatePersonActionTest --compact`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Identity/Actions/DeactivatePersonAction.php app/Domains/Identity/Actions/ReactivatePersonAction.php tests/Feature/Identity/DeactivateReactivatePersonActionTest.php
git commit -m "feat(identity): add DeactivatePersonAction and ReactivatePersonAction"
```

---

## Stage 4 — Code cutover (Tasks 11–21)

### Task 11: `User.php` — redefine 4 relations as `hasOneThrough`

**Files:**
- Modify: `app/Models/User.php:73-91`
- Test: `tests/Feature/Identity/UserHasOneThroughTest.php`

**Interfaces:**
- Consumes: `Person` model (Task 2), `person_id` columns (Task 3).
- Produces: `User::guru()`, `User::karyawan()`, `User::orangTua()`, `User::siswa()` now return `HasOneThrough`. Signature and null-safety are unchanged from the callers' point of view (`$user->guru` still returns `?Guru`), so this is a pure regression-risk task — no caller changes anywhere else.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Identity\Models\Person;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;

it('resolves $user->guru through the person hasOneThrough chain', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $person = Person::factory()->create(['yayasan_id' => $yayasan->id, 'user_id' => $user->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'person_id' => $person->id]);

    expect($user->fresh()->guru?->id)->toBe($guru->id);
});

it('resolves $user->karyawan, orangTua, and siswa the same way, and returns null when absent', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $person = Person::factory()->create(['yayasan_id' => $yayasan->id, 'user_id' => $user->id]);
    $karyawan = Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'yayasan_id' => $yayasan->id, 'person_id' => $person->id]);

    expect($user->fresh()->karyawan?->id)->toBe($karyawan->id);
    expect($user->fresh()->orangTua)->toBeNull();
    expect($user->fresh()->siswa)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=UserHasOneThroughTest --compact`
Expected: FAIL (relations still `hasOne` against a dropped-later but currently-still-present `user_id` column — will actually pass loosely today; confirm it fails once Step 3 is reverted, or simply proceed since this is a refactor test, not a red/green requirement for pre-existing behavior)

- [ ] **Step 3: Modify `User.php`**

Replace lines 73-91 (the current 4 `hasOne` definitions):

```php
public function guru(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
{
    return $this->hasOneThrough(
        \App\Models\Guru::class,
        \App\Domains\Identity\Models\Person::class,
        'user_id', 'person_id', 'id', 'id'
    );
}

public function karyawan(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
{
    return $this->hasOneThrough(
        \App\Models\Karyawan::class,
        \App\Domains\Identity\Models\Person::class,
        'user_id', 'person_id', 'id', 'id'
    );
}

public function orangTua(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
{
    return $this->hasOneThrough(
        \App\Models\OrangTua::class,
        \App\Domains\Identity\Models\Person::class,
        'user_id', 'person_id', 'id', 'id'
    );
}

public function siswa(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
{
    return $this->hasOneThrough(
        \App\Models\Siswa::class,
        \App\Domains\Identity\Models\Person::class,
        'user_id', 'person_id', 'id', 'id'
    );
}
```

Add proper `use` imports at the top of the file instead of FQCNs, matching the file's existing import style.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=UserHasOneThroughTest --compact`
Expected: PASS. Also run the full existing `BottomNavTest` (`tests/Feature/BottomNavTest.php`) since `User::hasBottomNav()` depends on `$this->orangTua !== null`:

Run: `php artisan test --filter=BottomNavTest --compact`
Expected: PASS (no changes needed to `hasBottomNav()` itself — this run proves the relation swap didn't regress it)

- [ ] **Step 5: Commit**

```bash
git add app/Models/User.php tests/Feature/Identity/UserHasOneThroughTest.php
git commit -m "refactor(identity): redefine User role relations as hasOneThrough via Person"
```

---

### Task 12: Accessor shims on the 5 role models

**Files:**
- Modify: `app/Models/Guru.php` (add `person()` BelongsTo + accessors, remove `nik_hash` `booted()` hook and `casts()` nik entry)
- Modify: `app/Models/Karyawan.php` (same)
- Modify: `app/Models/OrangTua.php` (same)
- Modify: `app/Models/Siswa.php` (same, minus nik since Siswa never had one)
- Modify: `app/Models/CalonMurid.php` (same, field names differ: `no_telepon`→`no_hp`, `email_kontak`→`email`)
- Test: `tests/Feature/Identity/AccessorShimTest.php`

**Interfaces:**
- Consumes: `Person` model (Task 2), `person_id` columns (Task 3).
- Produces: `$guru->nama`, `$guru->nik`, `$guru->no_hp`, `$guru->email`, `$guru->jenis_kelamin`, `$guru->tempat_lahir`, `$guru->tanggal_lahir`, `$guru->agama` all read through `$this->person`. Same pattern for `nama_lengkap` on `OrangTua`/`Siswa`/`CalonMurid`, and `no_telepon`/`email_kontak` names preserved as accessors on `CalonMurid` even though the underlying `Person` column is `no_hp`/`email`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Identity\Models\Person;
use App\Models\CalonMurid;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\Yayasan;

it('proxies Guru identity reads to the linked Person', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $person = Person::factory()->create(['yayasan_id' => $yayasan->id, 'nama_lengkap' => 'Guru Satu', 'nik' => '4444444444444444']);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'person_id' => $person->id]);

    expect($guru->nama)->toBe('Guru Satu');
    expect($guru->nik)->toBe('4444444444444444');
});

it('proxies Karyawan identity reads to the linked Person', function () {
    $yayasan = Yayasan::factory()->create();
    $person = Person::factory()->create(['yayasan_id' => $yayasan->id, 'nama_lengkap' => 'Karyawan Satu']);
    $karyawan = Karyawan::factory()->create(['yayasan_id' => $yayasan->id, 'person_id' => $person->id]);

    expect($karyawan->nama)->toBe('Karyawan Satu');
});

it('proxies OrangTua and Siswa nama_lengkap reads to the linked Person', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $personOrtu = Person::factory()->create(['yayasan_id' => $yayasan->id, 'nama_lengkap' => 'Ortu Satu']);
    $personSiswa = Person::factory()->create(['yayasan_id' => $yayasan->id, 'nama_lengkap' => 'Siswa Satu']);

    $ortu = OrangTua::factory()->create(['person_id' => $personOrtu->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'person_id' => $personSiswa->id]);

    expect($ortu->nama_lengkap)->toBe('Ortu Satu');
    expect($siswa->nama_lengkap)->toBe('Siswa Satu');
});

it('proxies CalonMurid with its differently-named contact fields', function () {
    $yayasan = Yayasan::factory()->create();
    $person = Person::factory()->create(['yayasan_id' => $yayasan->id, 'nama_lengkap' => 'Calon Satu', 'no_hp' => '081234567890', 'email' => 'calon@example.test']);
    $calon = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id, 'person_id' => $person->id]);

    expect($calon->nama_lengkap)->toBe('Calon Satu');
    expect($calon->no_telepon)->toBe('081234567890');
    expect($calon->email_kontak)->toBe('calon@example.test');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AccessorShimTest --compact`
Expected: FAIL (accessors not defined yet)

- [ ] **Step 3: Add `person()` relation and accessors to `Guru.php`**

Add relation and accessors, remove the `nik_hash` `booted()` hook (lines 47-52) and `'nik' => 'encrypted'` from `casts()`:

```php
public function person(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(\App\Domains\Identity\Models\Person::class);
}

public function getNamaAttribute(): ?string
{
    return $this->person?->nama_lengkap;
}

public function getNikAttribute(): ?string
{
    return $this->person?->nik;
}

public function getJenisKelaminAttribute(): ?string
{
    return $this->person?->jenis_kelamin;
}

public function getTempatLahirAttribute(): ?string
{
    return $this->person?->tempat_lahir;
}

public function getTanggalLahirAttribute(): ?\Carbon\Carbon
{
    return $this->person?->tanggal_lahir;
}

public function getAgamaAttribute(): ?string
{
    return $this->person?->agama;
}

public function getNoHpAttribute(): ?string
{
    return $this->person?->no_hp;
}

public function getEmailAttribute(): ?string
{
    return $this->person?->email;
}
```

Also update `routeNotificationForMail()` (line 117) to `return $this->person?->email;`.

- [ ] **Step 4: Add the same pattern to `Karyawan.php`** (only `nama`, `nik`, `no_hp`, `email` apply — remove `booted()` nik_hash hook lines 35-40):

```php
public function person(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(\App\Domains\Identity\Models\Person::class);
}

public function getNamaAttribute(): ?string { return $this->person?->nama_lengkap; }
public function getNikAttribute(): ?string { return $this->person?->nik; }
public function getNoHpAttribute(): ?string { return $this->person?->no_hp; }
public function getEmailAttribute(): ?string { return $this->person?->email; }
```

- [ ] **Step 5: Add the pattern to `OrangTua.php`** (`nama_lengkap`, `nik`, `no_hp`, `email`, `alamat`):

```php
public function person(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(\App\Domains\Identity\Models\Person::class);
}

public function getNamaLengkapAttribute(): ?string { return $this->person?->nama_lengkap; }
public function getNikAttribute(): ?string { return $this->person?->nik; }
public function getNoHpAttribute(): ?string { return $this->person?->no_hp; }
public function getEmailAttribute(): ?string { return $this->person?->email; }
public function getAlamatAttribute(): ?string { return $this->person?->alamat_jalan; }
```

Update `routeNotificationForMail()` to `$this->person?->email` and `routeNotificationForWhatsapp()` to `$this->person?->no_hp`.

- [ ] **Step 6: Add the pattern to `Siswa.php`** (`nama_lengkap`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir` only — Siswa never had `agama` moved... wait, spec keeps `agama` on Siswa? Re-check: spec §3 DROP list for `siswa` is `nama_lengkap, jenis_kelamin, tempat_lahir, tanggal_lahir, agama` — include `agama` too):

```php
public function person(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(\App\Domains\Identity\Models\Person::class);
}

public function getNamaLengkapAttribute(): ?string { return $this->person?->nama_lengkap; }
public function getJenisKelaminAttribute(): ?string { return $this->person?->jenis_kelamin; }
public function getTempatLahirAttribute(): ?string { return $this->person?->tempat_lahir; }
public function getTanggalLahirAttribute(): ?\Carbon\Carbon { return $this->person?->tanggal_lahir; }
public function getAgamaAttribute(): ?string { return $this->person?->agama; }
```

- [ ] **Step 7: Add the pattern to `CalonMurid.php`** (note the renamed fields — remove `booted()` nik_hash hook lines 41-46, and `nik`/`no_kk` from `casts()`):

```php
public function person(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(\App\Domains\Identity\Models\Person::class);
}

public function getNamaLengkapAttribute(): ?string { return $this->person?->nama_lengkap; }
public function getNikAttribute(): ?string { return $this->person?->nik; }
public function getJenisKelaminAttribute(): ?string { return $this->person?->jenis_kelamin; }
public function getTempatLahirAttribute(): ?string { return $this->person?->tempat_lahir; }
public function getTanggalLahirAttribute(): ?\Carbon\Carbon { return $this->person?->tanggal_lahir; }
public function getAgamaAttribute(): ?string { return $this->person?->agama; }
public function getNoTeleponAttribute(): ?string { return $this->person?->no_hp; }
public function getEmailKontakAttribute(): ?string { return $this->person?->email; }
```

`no_kk` (family card number) has no `Person` equivalent in this spec's scope (spec §3 notes it as a possible future gap) — leave `no_kk` as a local column on `calon_murid` unchanged; do not move it.

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test --filter=AccessorShimTest --compact`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add app/Models/Guru.php app/Models/Karyawan.php app/Models/OrangTua.php app/Models/Siswa.php app/Models/CalonMurid.php tests/Feature/Identity/AccessorShimTest.php
git commit -m "feat(identity): add person() relation and accessor shims to all 5 role models"
```

---

### Task 13: `PersonTenantScope` — close the OrangTua cross-tenant leak

**This task remediates a live, pre-existing bug**, independently confirmed by direct code read of `app/Http/Controllers/Admin/OrangTuaController.php:19-40`: `OrangTua` has no tenant-scope column or trait of its own, and the controller's `index()` skips its lembaga-narrowing filter entirely for any actor with `widestScopeLevel() === 'yayasan'` (`->when($user->widestScopeLevel() !== 'yayasan', ...)`). A `yayasan_super_admin` currently sees every `OrangTua` row across every yayasan in the system. This task adds a structural fix so future direct queries against `OrangTua` cannot forget tenant isolation — it does not replace `authorizeLembaga()`, which stays as the finer-grained, siswa-based UX filter.

**Files:**
- Create: `app/Models/Scopes/PersonTenantScope.php`
- Create: `app/Models/Concerns/BelongsToTenantViaPerson.php`
- Modify: `app/Models/OrangTua.php` (add `use BelongsToTenantViaPerson;`)
- Test: `tests/Feature/Identity/PersonTenantScopeTest.php`

**Interfaces:**
- Consumes: `OrangTua::person()` relation (Task 12), `Person::yayasan_id`.
- Produces: every `OrangTua` query is transparently filtered to the acting actor's yayasan, mirroring `TenantScope`'s own actor-resolution logic (platform bypass, yayasan-level uses its own `yayasan_id`, lembaga-level uses `lembaga->yayasan_id`).

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Domains\Identity\Models\Person;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;

it('scopes OrangTua to the acting lembaga-level admin yayasan', function () {
    $yayasanA = Yayasan::factory()->create();
    $yayasanB = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);

    OrangTua::factory()->create(['person_id' => Person::factory()->create(['yayasan_id' => $yayasanA->id])->id]);
    OrangTua::factory()->create(['person_id' => Person::factory()->create(['yayasan_id' => $yayasanB->id])->id]);

    $admin = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $this->actingAs($admin);

    expect(OrangTua::count())->toBe(1);
});

it('scopes OrangTua to the acting yayasan-level admin own yayasan_id, closing the cross-tenant leak', function () {
    $yayasanA = Yayasan::factory()->create();
    $yayasanB = Yayasan::factory()->create();

    OrangTua::factory()->create(['person_id' => Person::factory()->create(['yayasan_id' => $yayasanA->id])->id]);
    OrangTua::factory()->create(['person_id' => Person::factory()->create(['yayasan_id' => $yayasanB->id])->id]);

    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $admin = User::factory()->create(['yayasan_id' => $yayasanA->id]);
    $admin->assignRole($role);
    $this->actingAs($admin);

    expect(OrangTua::count())->toBe(1);
});

it('does not scope OrangTua for platform-level actors', function () {
    OrangTua::factory()->create(['person_id' => Person::factory()->create()->id]);
    OrangTua::factory()->create(['person_id' => Person::factory()->create()->id]);

    $role = Role::firstOrCreate(['name' => 'platform_super_admin', 'guard_name' => 'web'], ['scope_level' => 'platform']);
    $admin = User::factory()->create();
    $admin->assignRole($role);
    $this->actingAs($admin);

    expect(OrangTua::count())->toBe(2);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=PersonTenantScopeTest --compact`
Expected: FAIL (scope not applied yet)

- [ ] **Step 3: Write the scope**

Mirror `app/Models/Scopes/TenantScope.php`'s actor-resolution and reentrancy-guard structure exactly; only the final filter clause differs (it filters through the `person` relation instead of a local column).

```php
<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class PersonTenantScope implements Scope
{
    private static bool $resolving = false;

    public function apply(Builder $builder, Model $model): void
    {
        if (self::$resolving) {
            return;
        }

        self::$resolving = true;
        $user = auth()->user();
        self::$resolving = false;

        if ($user === null) {
            return;
        }

        $widestScopeLevel = $user->widestScopeLevel();

        if ($widestScopeLevel === 'platform') {
            return;
        }

        $yayasanId = match ($widestScopeLevel) {
            'yayasan' => $user->yayasan_id,
            default => $user->lembaga?->yayasan_id,
        };

        if ($yayasanId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->whereHas('person', fn (Builder $q) => $q->withoutGlobalScopes()->where('yayasan_id', $yayasanId));
    }
}
```

- [ ] **Step 4: Write the trait**

```php
<?php

namespace App\Models\Concerns;

use App\Models\Scopes\PersonTenantScope;

trait BelongsToTenantViaPerson
{
    public static function bootBelongsToTenantViaPerson(): void
    {
        static::addGlobalScope(new PersonTenantScope);
    }
}
```

- [ ] **Step 5: Apply the trait to `OrangTua.php`**

Add `use App\Models\Concerns\BelongsToTenantViaPerson;` to the `use` list and `use HasFactory, Notifiable, BelongsToTenantViaPerson;` to the class.

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=PersonTenantScopeTest --compact`
Expected: PASS. Also re-run the existing `OrangTuaController` tests if any exist (`Grep -r "OrangTuaController" tests/` to check) to confirm no regression in the existing `authorizeLembaga()` UX filter.

- [ ] **Step 7: Commit**

```bash
git add app/Models/Scopes/PersonTenantScope.php app/Models/Concerns/BelongsToTenantViaPerson.php app/Models/OrangTua.php tests/Feature/Identity/PersonTenantScopeTest.php
git commit -m "fix(identity): add PersonTenantScope to close OrangTua cross-tenant leak for yayasan-level actors"
```

---

### Task 14: Cutover `GuruController` (spec §5.1, 4 sites)

**Files:**
- Modify: `app/Http/Controllers/Admin/GuruController.php:87-118` (`store()`), `:138-154` (`update()`), `:190-201` (`validateProfil()` NIK dedup closure)
- Test: `tests/Feature/GuruControllerTest.php` (extend existing, or create if none exists — check first)

**Interfaces:**
- Consumes: `CreatePersonAction`, `UpdatePersonAction` (Tasks 6, 8).
- Produces: `Guru::create()` no longer receives identity fields — only `person_id` plus the role-specific columns that stayed on `guru` (`nuptk`, `nip`, `jenis_ptk`, `status_kepegawaian`, `golongan_pangkat`, `tmt_tugas`, `tmt_pns`, `status_aktif`, `kapasitas_kasus_aktif`, `lembaga_id`).

- [ ] **Step 1: Check for an existing test file**

Run: `php artisan test --filter=GuruControllerTest --compact` (to see if it currently exists and passes as a baseline)

- [ ] **Step 2: Write/extend the test**

```php
<?php

use App\Domains\Identity\Models\Person;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;

it('creates a Guru with identity data routed through PersonService', function () {
    $lembaga = Lembaga::factory()->create();
    $role = Role::firstOrCreate(['name' => 'lembaga_admin', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    $response = $this->actingAs($admin)->post(route('admin.guru.store'), [
        'nama' => 'Guru Baru',
        'email' => 'guru.baru@example.test',
        'nik' => '5555555555555555',
        'jenis_kelamin' => 'L',
        'tempat_lahir' => 'Jakarta',
        'tanggal_lahir' => '1990-01-01',
        'agama' => 'Islam',
        'no_hp' => '081200000000',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'tetap',
    ]);

    $response->assertRedirect();
    $guru = Guru::first();
    expect($guru->person_id)->not->toBeNull();
    expect($guru->nama)->toBe('Guru Baru');

    $person = Person::withoutGlobalScopes()->find($guru->person_id);
    expect($person->yayasan_id)->toBe($lembaga->yayasan_id);
});

it('updates a Guru identity through PersonService', function () {
    $lembaga = Lembaga::factory()->create();
    $person = Person::factory()->create(['yayasan_id' => $lembaga->yayasan_id, 'nama_lengkap' => 'Nama Lama']);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'person_id' => $person->id]);
    $role = Role::firstOrCreate(['name' => 'lembaga_admin', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    $this->actingAs($admin)->put(route('admin.guru.update', $guru), [
        'nama' => 'Nama Baru',
        'email' => $guru->email ?? 'guru@example.test',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'tetap',
    ])->assertRedirect();

    expect($guru->fresh()->nama)->toBe('Nama Baru');
});
```

Adjust field names/route names to whatever `routes/admin.php` (or equivalent) and `GuruController::validateProfil()` actually declare — read the controller's current validation rules first (`app/Http/Controllers/Admin/GuruController.php:190-242`) since the exact rule keys were captured in the audit but the test payload above must match them precisely.

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --filter=GuruControllerTest --compact`
Expected: FAIL (controller still writes identity fields directly to `Guru`)

- [ ] **Step 4: Modify `store()` (lines 87-118)**

Replace the `Guru::create([...$data, 'user_id'=>$user->id, 'lembaga_id'=>$lembagaId])` block (and the preceding `User::create()` for identity fields) with:

```php
$lembagaId = $this->resolveLembagaId($request);

DB::transaction(function () use ($data, $lembagaId) {
    $person = app(CreatePersonAction::class)->execute(
        identityData: [
            'nama_lengkap' => $data['nama'],
            'nik' => $data['nik'] ?? null,
            'jenis_kelamin' => $data['jenis_kelamin'] ?? null,
            'tempat_lahir' => $data['tempat_lahir'] ?? null,
            'tanggal_lahir' => $data['tanggal_lahir'] ?? null,
            'agama' => $data['agama'] ?? null,
            'no_hp' => $data['no_hp'] ?? null,
            'email' => $data['email'],
        ],
        lembagaId: $lembagaId,
        actingYayasanId: null,
    );

    $user = User::create([
        'name' => $data['nama'],
        'email' => $data['email'],
        'password' => Hash::make($data['nip'] ?? $data['nik']),
        'lembaga_id' => $lembagaId,
    ]);
    $user->assignRole('guru');
    $person->update(['user_id' => $user->id]);

    Guru::create([
        'person_id' => $person->id,
        'lembaga_id' => $lembagaId,
        'nuptk' => $data['nuptk'] ?? null,
        'nip' => $data['nip'] ?? null,
        'jenis_ptk' => $data['jenis_ptk'],
        'status_kepegawaian' => $data['status_kepegawaian'],
        'golongan_pangkat' => $data['golongan_pangkat'] ?? null,
        'tmt_tugas' => $data['tmt_tugas'] ?? null,
        'tmt_pns' => $data['tmt_pns'] ?? null,
        'status_aktif' => 'aktif',
    ]);
});
```

Add `use App\Domains\Identity\Actions\CreatePersonAction;` and `use App\Domains\Identity\Actions\UpdatePersonAction;` imports, and `use Illuminate\Support\Facades\DB;` if not already present.

- [ ] **Step 5: Modify `update()` (lines 138-154)**

Replace `$guru->user()->update(['name'=>$data['nama'],'email'=>$data['email']])` and `$guru->update($data)` with:

```php
app(UpdatePersonAction::class)->execute($guru->person, [
    'nama_lengkap' => $data['nama'],
    'email' => $data['email'],
    'nik' => $data['nik'] ?? $guru->person->nik,
    'jenis_kelamin' => $data['jenis_kelamin'] ?? $guru->person->jenis_kelamin,
    'tempat_lahir' => $data['tempat_lahir'] ?? $guru->person->tempat_lahir,
    'tanggal_lahir' => $data['tanggal_lahir'] ?? $guru->person->tanggal_lahir,
    'agama' => $data['agama'] ?? $guru->person->agama,
    'no_hp' => $data['no_hp'] ?? $guru->person->no_hp,
]);

if ($guru->person->user !== null) {
    $guru->person->user->update(['name' => $data['nama'], 'email' => $data['email']]);
}

$guru->update(collect($data)->only([
    'nuptk', 'nip', 'jenis_ptk', 'status_kepegawaian', 'golongan_pangkat', 'tmt_tugas', 'tmt_pns',
])->toArray());
```

- [ ] **Step 6: Modify the NIK dedup closure in `validateProfil()` (lines 193-201)**

Change `Guru::withoutGlobalScopes()->where('nik_hash', hash('sha256', $value))` to query `Person`:

```php
\App\Domains\Identity\Models\Person::withoutGlobalScopes()
    ->where('nik_hash', hash('sha256', $value))
    ->where('yayasan_id', $this->resolveLembagaId($request) !== null
        ? \App\Models\Lembaga::find($this->resolveLembagaId($request))->yayasan_id
        : null)
    ->exists()
```

Adjust to the closure's actual available variables (it likely doesn't have `$request` in scope as a validation rule closure — read the exact surrounding code at `GuruController.php:190-201` before finalizing; if `$request` isn't available, resolve `$lembagaId`/`yayasanId` once above the `Validator::make()` call and capture it in the closure).

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=GuruControllerTest --compact`
Expected: PASS

- [ ] **Step 8: Run pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Admin/GuruController.php tests/Feature/GuruControllerTest.php
git commit -m "refactor(identity): route GuruController identity writes through PersonService"
```

---

### Task 15: Cutover `AkunKaryawanGenerator` + `KaryawanController` (spec §5.1, 6 sites)

**Files:**
- Modify: `app/Services/AkunKaryawanGenerator.php:21-45`
- Modify: `app/Http/Controllers/Admin/KaryawanController.php:104` (NIK-collision pre-check), `:122-130` (generator call site, unaffected signature), `:152-169` (`update()`), `:196-213` (`validateProfil()` NIK dedup closure)
- Test: `tests/Feature/AkunKaryawanGeneratorTest.php` and/or `tests/Feature/KaryawanControllerTest.php` (extend existing or create)

**Interfaces:**
- Consumes: `CreatePersonAction`, `UpdatePersonAction`.
- Produces: `AkunKaryawanGenerator::buat()` keeps its existing public signature (callers in `KaryawanController` are unaffected) but now creates a `Person` first and links `Karyawan::person_id`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Identity\Models\Person;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Services\AkunKaryawanGenerator;

it('creates a Person and links karyawan.person_id when generating a karyawan account', function () {
    $lembaga = Lembaga::factory()->create();

    $user = app(AkunKaryawanGenerator::class)->buat(
        nama: 'Karyawan Baru',
        nik: '6666666666666666',
        noHp: '081200000001',
        email: 'karyawan.baru@example.test',
        yayasanId: $lembaga->yayasan_id,
        lembagaId: $lembaga->id,
        jenisKaryawanId: \App\Models\JenisKaryawan::factory()->create()->id,
    );

    $karyawan = Karyawan::where('user_id', null)->latest('id')->first() ?? Karyawan::latest('id')->first();
    expect($karyawan->person_id)->not->toBeNull();
    expect(Person::withoutGlobalScopes()->find($karyawan->person_id)->nama_lengkap)->toBe('Karyawan Baru');
});
```

Adjust the `AkunKaryawanGenerator::buat()` parameter list to match its real current signature exactly (re-read `app/Services/AkunKaryawanGenerator.php` before writing this test — the summary captured `nama, nik, noHp, email` plus lembaga/yayasan/jenisKaryawan context, but confirm exact parameter names/order).

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AkunKaryawanGeneratorTest --compact`
Expected: FAIL

- [ ] **Step 3: Modify `AkunKaryawanGenerator::buat()`**

Replace the `User::create([...])` + `Karyawan::create([...'nama'=>$nama,'nik'=>$nik,'no_hp'=>$noHp,'email'=>$email...])` block with:

```php
return DB::transaction(function () use ($nama, $nik, $noHp, $email, $yayasanId, $lembagaId, $jenisKaryawanId) {
    $person = app(CreatePersonAction::class)->execute(
        identityData: ['nama_lengkap' => $nama, 'nik' => $nik, 'no_hp' => $noHp, 'email' => $email],
        lembagaId: $lembagaId,
        actingYayasanId: $lembagaId === null ? $yayasanId : null,
    );

    $user = User::create([
        'name' => $nama,
        'username' => $nik,
        'password' => Hash::make($nik),
        'yayasan_id' => $lembagaId === null ? $yayasanId : null,
        'lembaga_id' => $lembagaId,
    ]);
    $user->assignRole($lembagaId === null ? 'pegawai_yayasan' : 'pegawai_lembaga');
    $person->update(['user_id' => $user->id]);

    Karyawan::create([
        'person_id' => $person->id,
        'yayasan_id' => $yayasanId,
        'lembaga_id' => $lembagaId,
        'jenis_karyawan_id' => $jenisKaryawanId,
        'status_aktif' => 'aktif',
    ]);

    return $user;
});
```

Add `use App\Domains\Identity\Actions\CreatePersonAction;` to the file's imports.

- [ ] **Step 4: Modify `KaryawanController::update()` (lines 152-169)**

Replace `$karyawan->user()->update(['name'=>$data['nama']])` and `$karyawan->update($data)` with:

```php
app(UpdatePersonAction::class)->execute($karyawan->person, [
    'nama_lengkap' => $data['nama'],
    'nik' => $data['nik'] ?? $karyawan->person->nik,
    'no_hp' => $data['no_hp'] ?? $karyawan->person->no_hp,
    'email' => $data['email'] ?? $karyawan->person->email,
]);

if ($karyawan->person->user !== null) {
    $karyawan->person->user->update(['name' => $data['nama']]);
}

$karyawan->update(collect($data)->only(['jenis_karyawan_id'])->toArray());
```

- [ ] **Step 5: Modify the NIK-collision pre-check at `KaryawanController.php:104`**

Change `User::withoutGlobalScopes()->where('username', $data['nik'])->exists()` — this check is about `username` collision on `users`, unrelated to `Person`, so it stays as-is. Confirm by re-reading the exact line before editing; if it is instead checking `Karyawan`'s own `nik_hash` column (as spec §5.1 groups it under "cek unik NIK→username"), redirect it to `Person` the same way as Task 14 Step 6.

- [ ] **Step 6: Modify the NIK dedup closure in `validateProfil()` (lines 199-207)**, same pattern as Task 14 Step 6, querying `Person` scoped to the resolved yayasan instead of `Karyawan::withoutGlobalScopes()`.

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=AkunKaryawanGeneratorTest --compact`
Expected: PASS

- [ ] **Step 8: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/AkunKaryawanGenerator.php app/Http/Controllers/Admin/KaryawanController.php tests/Feature/AkunKaryawanGeneratorTest.php
git commit -m "refactor(identity): route Karyawan identity writes through PersonService"
```

---

### Task 16: Cutover `AkunOrangTuaGenerator` + `OrangTuaController` (spec §5.1, 5 sites)

**Files:**
- Modify: `app/Services/AkunOrangTuaGenerator.php:21-41`
- Modify: `app/Http/Controllers/Admin/OrangTuaController.php:57-88` (`store()`), `:103-120` (`update()`)
- Test: `tests/Feature/AkunOrangTuaGeneratorTest.php` / `tests/Feature/OrangTuaControllerTest.php`

**Interfaces:**
- Consumes: `CreatePersonAction`, `UpdatePersonAction`.
- Produces: `AkunOrangTuaGenerator::buat()` links `OrangTua::person_id`. Because `OrangTua` has no `lembaga_id` of its own, `actingYayasanId` must come from the calling admin's own tenant context — `OrangTuaController` resolves this the same way `resolveLembagaId()`-style controllers do, but yielding a yayasan_id directly.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Identity\Models\Person;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Services\AkunOrangTuaGenerator;

it('creates a Person and links orang_tua.person_id when generating an orang tua account', function () {
    $lembaga = Lembaga::factory()->create();

    $user = app(AkunOrangTuaGenerator::class)->buat(
        namaLengkap: 'Orang Tua Baru',
        nik: '7777777777777777',
        noHp: '081200000002',
        email: 'ortu.baru@example.test',
        alamat: 'Jl. Contoh No. 1',
        pekerjaan: 'Wiraswasta',
        yayasanId: $lembaga->yayasan_id,
    );

    $ortu = OrangTua::withoutGlobalScopes()->latest('id')->first();
    expect($ortu->person_id)->not->toBeNull();
    expect(Person::withoutGlobalScopes()->find($ortu->person_id)->nama_lengkap)->toBe('Orang Tua Baru');
});
```

Confirm `AkunOrangTuaGenerator::buat()`'s exact current parameter list before finalizing (re-read the file — it must gain a `yayasanId` parameter it does not currently have, since it previously had no tenant context to resolve at all).

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AkunOrangTuaGeneratorTest --compact`
Expected: FAIL

- [ ] **Step 3: Modify `AkunOrangTuaGenerator::buat()`** — add a `yayasanId` parameter, replace the `User::create()`+`OrangTua::create()` block:

```php
public function buat(string $namaLengkap, string $nik, ?string $noHp, ?string $email, ?string $alamat, ?string $pekerjaan, int $yayasanId): User
{
    return DB::transaction(function () use ($namaLengkap, $nik, $noHp, $email, $alamat, $pekerjaan, $yayasanId) {
        $person = app(CreatePersonAction::class)->execute(
            identityData: [
                'nama_lengkap' => $namaLengkap,
                'nik' => $nik,
                'no_hp' => $noHp,
                'email' => $email,
                'alamat_jalan' => $alamat,
            ],
            lembagaId: null,
            actingYayasanId: $yayasanId,
        );

        $user = User::create([
            'name' => $namaLengkap,
            'username' => $nik,
            'password' => Hash::make($nik),
        ]);
        $user->assignRole('orang_tua');
        $person->update(['user_id' => $user->id]);

        OrangTua::create(['person_id' => $person->id, 'pekerjaan' => $pekerjaan]);

        return $user;
    });
}
```

- [ ] **Step 4: Modify `OrangTuaController::store()` (lines 57-88)**

At the call site (line ~78-85), resolve `$yayasanId` before calling the generator. `OrangTuaController` has no existing `resolveLembagaId()`-style helper (it works off the linked `Siswa`'s lembaga in `authorizeLembaga()`) — add a small private helper mirroring the pattern:

```php
private function resolveYayasanIdForCreate(Request $request): int
{
    $user = $request->user();

    if ($user->widestScopeLevel() === 'yayasan') {
        return $user->yayasan_id;
    }

    return $user->lembaga->yayasan_id;
}
```

Then pass `$this->resolveYayasanIdForCreate($request)` as the generator's `yayasanId` argument.

- [ ] **Step 5: Modify `OrangTuaController::update()` (lines 103-120)**

Replace `$orangTua->user()->withoutGlobalScope(TenantScope::class)->update(['name'=>$data['nama_lengkap']])` and `$orangTua->update($data)` with:

```php
app(UpdatePersonAction::class)->execute($orangTua->person, [
    'nama_lengkap' => $data['nama_lengkap'],
    'nik' => $data['nik'] ?? $orangTua->person->nik,
    'no_hp' => $data['no_hp'] ?? $orangTua->person->no_hp,
    'email' => $data['email'] ?? $orangTua->person->email,
    'alamat_jalan' => $data['alamat'] ?? $orangTua->person->alamat_jalan,
]);

if ($orangTua->person->user !== null) {
    $orangTua->person->user->update(['name' => $data['nama_lengkap']]);
}

$orangTua->update(collect($data)->only(['pekerjaan'])->toArray());
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=AkunOrangTuaGeneratorTest --compact`
Expected: PASS

- [ ] **Step 7: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/AkunOrangTuaGenerator.php app/Http/Controllers/Admin/OrangTuaController.php tests/Feature/AkunOrangTuaGeneratorTest.php
git commit -m "refactor(identity): route OrangTua identity writes through PersonService"
```

---

### Task 17: Cutover `SiswaController` + `AkunSiswaGenerator` call sites (spec §5.1, plus the newly-discovered generator sites)

**Files:**
- Modify: `app/Http/Controllers/Admin/SiswaController.php:93-118` (`store()`), `:132-156` (`update()`), `:199-227` (`generateAkunMassal()`), `:229-244` (`generateAkun()`)
- Test: `tests/Feature/SiswaControllerTest.php` (extend existing or create)

**Interfaces:**
- Consumes: `CreatePersonAction`, `UpdatePersonAction`. `AkunSiswaGenerator::buat()` itself is NOT modified — it only ever creates the `User` row, never touches `Siswa`/`Person`, so it stays as a shared dependency called from all 5 sites.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Identity\Models\Person;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;

it('creates a Siswa with identity data routed through PersonService', function () {
    $lembaga = Lembaga::factory()->create();
    $kelas = \App\Models\Kelas::factory()->create(['lembaga_id' => $lembaga->id]);
    $role = Role::firstOrCreate(['name' => 'lembaga_admin', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    $response = $this->actingAs($admin)->post(route('admin.siswa.store'), [
        'lembaga_id' => $lembaga->id,
        'kelas_id' => $kelas->id,
        'nama_lengkap' => 'Siswa Baru',
        'nis' => '2026001',
        'jenis_kelamin' => 'L',
        'tempat_lahir' => 'Jakarta',
        'tanggal_lahir' => '2015-01-01',
        'agama' => 'Islam',
    ]);

    $response->assertRedirect();
    $siswa = Siswa::first();
    expect($siswa->person_id)->not->toBeNull();
    expect($siswa->nama_lengkap)->toBe('Siswa Baru');
});
```

Confirm the exact validated field keys and route name from `SiswaController::validateSiswa()` (lines 246-279) before finalizing this test.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SiswaControllerTest --compact`
Expected: FAIL

- [ ] **Step 3: Modify `store()` (lines 93-118)**

Before `$user = app(AkunSiswaGenerator::class)->buat(...)`, create the `Person` first, then link both:

```php
$lembaga = Lembaga::withoutGlobalScopes()->findOrFail($lembagaId);

$person = app(CreatePersonAction::class)->execute(
    identityData: [
        'nama_lengkap' => $data['nama_lengkap'],
        'jenis_kelamin' => $data['jenis_kelamin'] ?? null,
        'tempat_lahir' => $data['tempat_lahir'] ?? null,
        'tanggal_lahir' => $data['tanggal_lahir'] ?? null,
        'agama' => $data['agama'] ?? null,
    ],
    lembagaId: $lembaga->id,
    actingYayasanId: null,
);

$user = app(AkunSiswaGenerator::class)->buat($data['nama_lengkap'], $data['nis'], $lembaga);
$person->update(['user_id' => $user->id]);

Siswa::create([
    'person_id' => $person->id,
    'lembaga_id' => $lembagaId,
    'kelas_id' => $data['kelas_id'],
    'nis' => $data['nis'],
    'nisn' => $data['nisn'] ?? null,
    'status' => \App\Domains\Siswa\Enums\StatusSiswa::Aktif->value,
]);
```

Confirm the actual `StatusSiswa` enum namespace/values by reading `app/Models/Siswa.php`'s `casts()` reference before finalizing.

- [ ] **Step 4: Modify `update()` (lines 132-156)**

Replace the conditional `nama_lengkap`/`nis` sync block with:

```php
if ($data['nama_lengkap'] !== $siswa->nama_lengkap) {
    app(UpdatePersonAction::class)->execute($siswa->person, ['nama_lengkap' => $data['nama_lengkap']]);

    if ($siswa->person->user !== null) {
        $siswa->person->user->update(['name' => $data['nama_lengkap']]);
    }
}

if ($data['nis'] !== $siswa->nis && $siswa->person->user !== null) {
    $siswa->person->user->update(['username' => $this->usernameUntukSiswa($siswa->lembaga, $data['nis'])]);
}

$siswa->update(collect($data)->except(['nama_lengkap'])->toArray());
```

Read the controller's exact username-generation call (it likely reuses a method on `AkunSiswaGenerator` — check if `usernameUntuk()` is public before assuming a private duplicate is needed; if it's private, either make it public or replicate the exact logic — do not diverge from the existing username format).

- [ ] **Step 5: Modify `generateAkunMassal()` (lines 199-227) and `generateAkun()` (lines 229-244)**

Both currently call `AkunSiswaGenerator::buat($siswa->nama_lengkap, $siswa->nis, $lembaga)` for a `Siswa` that ALREADY has a `person_id` (these methods generate a login for an existing student record, they don't create identity data). After the generator call, add:

```php
$siswa->person->update(['user_id' => $user->id]);
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=SiswaControllerTest --compact`
Expected: PASS

- [ ] **Step 7: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/SiswaController.php tests/Feature/SiswaControllerTest.php
git commit -m "refactor(identity): route Siswa identity writes through PersonService"
```

---

### Task 18: Cutover `SiswaImportController`

**Files:**
- Modify: `app/Http/Controllers/Admin/SiswaImportController.php:62-94`
- Test: `tests/Feature/SiswaImportControllerTest.php` (extend existing or create)

**Interfaces:**
- Consumes: `CreatePersonAction`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Identity\Models\Person;
use App\Models\Lembaga;
use App\Models\Siswa;

it('creates a Person for each imported siswa row', function () {
    $lembaga = Lembaga::factory()->create();
    $kelas = \App\Models\Kelas::factory()->create(['lembaga_id' => $lembaga->id]);

    // Drive SiswaImportController::confirm() with a minimal session payload matching its current contract --
    // read the controller's confirm() input shape (session-based import staging) before finalizing this test,
    // since the exact session structure was not fully captured in the audit.

    // ... (implementer: complete using the controller's actual staged-import session shape)
});
```

- [ ] **Step 2: Read `SiswaImportController::confirm()` in full to confirm the staged-import session contract, then finish the test above with real staged data, matching the existing tests in `tests/Feature/` for this controller if any exist (`Grep -r "SiswaImportController" tests/`).**

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --filter=SiswaImportControllerTest --compact`
Expected: FAIL

- [ ] **Step 4: Modify `confirm()` (lines 62-94)**

Before the `Siswa::create([...])` call, insert:

```php
$person = app(CreatePersonAction::class)->execute(
    identityData: [
        'nama_lengkap' => $row['nama_lengkap'],
        'jenis_kelamin' => $row['jenis_kelamin'],
        'tempat_lahir' => $row['tempat_lahir'],
        'tanggal_lahir' => $row['tanggal_lahir'],
        'agama' => $row['agama'],
    ],
    lembagaId: $lembagaId,
    actingYayasanId: null,
);
```

Then add `'person_id' => $person->id,` to the `Siswa::create([...])` array (line ~75-87), removing `'nama_lengkap'`, `'jenis_kelamin'`, `'tempat_lahir'`, `'tanggal_lahir'`, `'agama'` from it. After `AkunSiswaGenerator::buat()` (line 73) creates `$user`, add `$person->update(['user_id' => $user->id]);`.

**Note on import-time collisions:** `CreatePersonAction` throws `PersonAlreadyExistsException` on a NIK collision within the yayasan — but `Siswa` rows have no NIK at all (spec §1.1 confirms `siswa` has no NIK column), so this exception path is unreachable for this specific importer today. No special handling needed here; leave it to propagate as a row-import failure if it ever is reached (defensive, not expected).

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=SiswaImportControllerTest --compact`
Expected: PASS

- [ ] **Step 6: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/SiswaImportController.php tests/Feature/SiswaImportControllerTest.php
git commit -m "refactor(identity): route SiswaImportController identity writes through PersonService"
```

---

### Task 19: Cutover `PendaftaranSiswaController` — link, don't copy

**This is the single most important write-point in the whole migration**: today, when a `CalonMurid` is accepted, `PendaftaranSiswaController::store()` COPIES their identity fields into a brand-new `Siswa` row. After this task, the new `Siswa` links to the SAME `person_id` as the originating `CalonMurid` — proving the identity-continuity goal of this entire spec.

**Files:**
- Modify: `app/Http/Controllers/Admin/PendaftaranSiswaController.php:36-106`
- Test: `tests/Feature/PendaftaranSiswaControllerTest.php` (extend existing or create)

**Interfaces:**
- Consumes: `CalonMurid::person_id` (already backfilled by Task 4, populated going forward by Task 20).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Identity\Models\Person;
use App\Models\CalonMurid;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\Siswa;

it('links the new Siswa to the same person_id as the originating CalonMurid, not a copy', function () {
    $lembaga = Lembaga::factory()->create();
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id]);
    $person = Person::factory()->create(['yayasan_id' => $lembaga->yayasan_id, 'nama_lengkap' => 'Calon Diterima']);
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id, 'person_id' => $person->id]);
    $pendaftaran = Pendaftaran::factory()->create(['calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id]);

    // ... drive PendaftaranSiswaController::store() per its actual route/request contract
    // (read the controller in full to confirm request shape before finalizing this test)

    $siswa = Siswa::where('calon_murid_id', $calonMurid->id)->first();
    expect($siswa->person_id)->toBe($person->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PendaftaranSiswaControllerTest --compact`
Expected: FAIL

- [ ] **Step 3: Modify `store()` (lines 87-101)**

Replace the field-copying `Siswa::create([...'nama_lengkap'=>$calonMurid->nama_lengkap, 'jenis_kelamin'=>$calonMurid->jenis_kelamin, ...])` with:

```php
Siswa::create([
    'lembaga_id' => $pendaftaran->lembaga_id,
    'kelas_id' => $kelas->id,
    'calon_murid_id' => $calonMurid->id,
    'pendaftaran_asal_id' => $pendaftaran->id,
    'person_id' => $calonMurid->person_id,
    'sumber_data' => SumberDataSiswa::Spmb->value,
    'nis' => $nis,
    'nisn' => $calonMurid->nisn,
]);
```

The `AkunSiswaGenerator::buat($calonMurid->nama_lengkap, $nis, $lembaga)` call at line 85 stays (it only creates the `User`, unaffected), but after it succeeds, add:

```php
$calonMurid->person->update(['user_id' => $user->id]);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=PendaftaranSiswaControllerTest --compact`
Expected: PASS

- [ ] **Step 5: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/PendaftaranSiswaController.php tests/Feature/PendaftaranSiswaControllerTest.php
git commit -m "refactor(identity): link Siswa to the originating CalonMurid's person_id instead of copying fields"
```

---

### Task 20: Cutover `ReviewSubmitController` (CalonMurid identity write)

**Files:**
- Modify: `app/Http/Controllers/Spmb/ReviewSubmitController.php:80-83`
- Test: `tests/Feature/Spmb/ReviewSubmitControllerTest.php` (extend existing or create)

**Interfaces:**
- Consumes: `CreatePersonAction`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Identity\Models\Person;
use App\Models\CalonMurid;
use App\Models\Lembaga;

it('creates a Person for a new SPMB submission and links calon_murid.person_id', function () {
    $lembaga = Lembaga::factory()->create();

    // ... drive ReviewSubmitController::submit() per its actual session-based contract
    // (read the controller in full -- it relies on $session['nik'] and $session['data_pribadi'] --
    // to build a matching test payload before finalizing this test)

    $calon = CalonMurid::first();
    expect($calon->person_id)->not->toBeNull();
});

it('reuses the same Person when the same NIK re-submits within the same yayasan (updateOrCreate semantics)', function () {
    // A CalonMurid re-submission with the same NIK should not create a second Person --
    // this preserves ReviewSubmitController's existing updateOrCreate-by-nik_hash behavior.
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ReviewSubmitControllerTest --compact`
Expected: FAIL

- [ ] **Step 3: Modify `submit()` (lines 80-83)**

Replace `CalonMurid::updateOrCreate(['nik_hash'=>hash('sha256',$session['nik'])], array_merge(['yayasan_id'=>$lembaga->yayasan_id,'nik'=>$session['nik']], $session['data_pribadi']))` with a two-step find-or-create against `Person` first, since `CreatePersonAction` throws on collision rather than upserting:

```php
$nikHash = hash('sha256', $session['nik']);
$person = Person::withoutGlobalScopes()
    ->where('yayasan_id', $lembaga->yayasan_id)
    ->where('nik_hash', $nikHash)
    ->first();

if ($person !== null) {
    app(UpdatePersonAction::class)->execute($person, array_merge(['nik' => $session['nik']], $session['data_pribadi']));
} else {
    $person = app(CreatePersonAction::class)->execute(
        identityData: array_merge(['nik' => $session['nik']], $session['data_pribadi']),
        lembagaId: $lembaga->id,
        actingYayasanId: null,
    );
}

$calonMurid = CalonMurid::updateOrCreate(
    ['person_id' => $person->id],
    ['yayasan_id' => $lembaga->yayasan_id]
);
```

Confirm `$session['data_pribadi']`'s keys map 1:1 onto `Person::$fillable` (spec's DDL uses the same field names as `CalonMurid`/`Guru` already did — `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `agama` — but `CalonMurid`'s contact fields are named `no_telepon`/`email_kontak` while `Person`'s are `no_hp`/`email`; remap those two keys explicitly before passing to the action).

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ReviewSubmitControllerTest --compact`
Expected: PASS

- [ ] **Step 5: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Spmb/ReviewSubmitController.php tests/Feature/Spmb/ReviewSubmitControllerTest.php
git commit -m "refactor(identity): route SPMB CalonMurid identity writes through PersonService"
```

---

### Task 21: Move the `nik_hash` `saving()` hook fully to `Person`

**Files:**
- Verify only: `app/Domains/Identity/Models/Person.php` (hook already added in Task 2)
- Verify only: `app/Models/Guru.php`, `app/Models/Karyawan.php`, `app/Models/CalonMurid.php` (hooks already removed in Task 12)
- Test: `tests/Feature/Identity/NikHashHookMovedTest.php`

**Interfaces:**
- Consumes: Task 2's `Person::booted()` hook, Task 12's removal of the 3 legacy hooks.

This task is a verification checkpoint, not new code — Tasks 2 and 12 already made this change as part of their own scope. Its only job is to prove the removal was complete and correct with a dedicated regression test, since spec §5.3 calls this out as its own numbered work item.

- [ ] **Step 1: Write the test**

```php
<?php

use App\Domains\Identity\Models\Person;

it('computes nik_hash only on Person, not on the 3 legacy models', function () {
    $person = Person::factory()->create(['nik' => '8888888888888888']);
    expect($person->nik_hash)->toBe(hash('sha256', '8888888888888888'));

    foreach ([\App\Models\Guru::class, \App\Models\Karyawan::class, \App\Models\CalonMurid::class] as $modelClass) {
        expect(\Illuminate\Support\Facades\Schema::hasColumn((new $modelClass)->getTable(), 'nik_hash'))->toBeFalse();
    }
});
```

- [ ] **Step 2: Run test**

Run: `php artisan test --filter=NikHashHookMovedTest --compact`
Expected: PASS (if it fails, Task 3's column-drop step for `nik_hash` on these 3 tables — which only happens in Task 27, the deferred final drop — means this assertion is premature; adjust the test to check the model's `casts()`/`fillable` no longer references `nik` instead of asserting the column is gone, since the column itself is intentionally still present but unused until Task 27)

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Identity/NikHashHookMovedTest.php
git commit -m "test(identity): verify nik_hash computation lives solely on Person"
```

---

## Stage 5 — Query-builder cutover (Tasks 22–26, spec §5.2, ~30 sites)

Each task below rewrites the same mechanical pattern: `where('nama', ...)`/`orderBy('nama')` (or `nama_lengkap`) against a role table becomes a `whereHas('person', fn ($q) => $q->where(...))` (for filtering) or a join-based approach (for ordering, since `whereHas` cannot drive `orderBy`). Use this exact join helper pattern for ordering, added once per model and reused:

```php
->join('persons', 'persons.id', '=', '<table>.person_id')
->orderBy('persons.nama_lengkap')
->select('<table>.*')
```

### Task 22: Guru query-builder sites (10 points)

**Files:**
- Modify: `app/Http/Controllers/Admin/GuruController.php:50,53`
- Modify: `app/Http/Controllers/Admin/AttendanceController.php:48`
- Modify: `app/Http/Controllers/Admin/AttendanceConfigurationController.php:103`
- Modify: `app/Http/Controllers/Admin/JadwalPelajaranController.php:78-79,154,303`
- Modify: `app/Http/Controllers/Admin/KelasController.php:87,126`
- Modify: `app/Domains/Akademik/Actions/ListRppAction.php:65`
- Test: `tests/Feature/Identity/GuruSearchRegressionTest.php`

**Interfaces:**
- Consumes: `Guru::person()` relation (Task 12).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Identity\Models\Person;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;

it('still finds a guru by name search after the identity columns move to Person', function () {
    $lembaga = Lembaga::factory()->create();
    $person = Person::factory()->create(['yayasan_id' => $lembaga->yayasan_id, 'nama_lengkap' => 'Guru Dicari']);
    Guru::factory()->create(['lembaga_id' => $lembaga->id, 'person_id' => $person->id]);

    $role = Role::firstOrCreate(['name' => 'lembaga_admin', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    $response = $this->actingAs($admin)->get(route('admin.guru.index', ['search' => 'Guru Dicari']));

    $response->assertOk();
    $response->assertSee('Guru Dicari');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=GuruSearchRegressionTest --compact`
Expected: FAIL

- [ ] **Step 3: For each of the 10 listed sites**, read the exact current line, then apply the mechanical rewrite:

`GuruController.php:50,53` — change `$q->where('nama', 'like', ...)` (or similar) to:
```php
$q->whereHas('person', fn ($p) => $p->where('nama_lengkap', 'like', "%{$search}%"));
```
and any `orderBy('nama')` to the join pattern above.

Repeat the same transformation at each of the remaining 8 cited lines in `AttendanceController.php:48`, `AttendanceConfigurationController.php:103`, `JadwalPelajaranController.php:78-79,154,303`, `KelasController.php:87,126`, `ListRppAction.php:65` — read each site's exact current code first (none were fully quoted in the audit beyond "queries `nama`"), and apply whichever of the two patterns (whereHas filter vs. join+orderBy) matches what that site is doing.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=GuruSearchRegressionTest --compact`
Expected: PASS

- [ ] **Step 5: Run the full existing test suite scoped to these files' features** (Attendance, JadwalPelajaran, Kelas, Rpp feature tests) to catch regressions the single new test doesn't cover:

Run: `php artisan test --filter=Attendance --compact && php artisan test --filter=JadwalPelajaran --compact && php artisan test --filter=Kelas --compact`
Expected: PASS

- [ ] **Step 6: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/GuruController.php app/Http/Controllers/Admin/AttendanceController.php app/Http/Controllers/Admin/AttendanceConfigurationController.php app/Http/Controllers/Admin/JadwalPelajaranController.php app/Http/Controllers/Admin/KelasController.php app/Domains/Akademik/Actions/ListRppAction.php tests/Feature/Identity/GuruSearchRegressionTest.php
git commit -m "refactor(identity): rewrite Guru query-builder identity references through person relation"
```

---

### Task 23: Karyawan query-builder sites (3 points)

**Files:**
- Modify: `app/Http/Controllers/Admin/KaryawanController.php:61-62`
- Modify: `app/Http/Controllers/Admin/AttendanceController.php:52`
- Modify: `app/Http/Controllers/Admin/AttendanceConfigurationController.php:107`
- Test: `tests/Feature/Identity/KaryawanSearchRegressionTest.php`

**Interfaces:**
- Consumes: `Karyawan::person()` relation (Task 12).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Identity\Models\Person;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;

it('still finds a karyawan by name search after the identity columns move to Person', function () {
    $lembaga = Lembaga::factory()->create();
    $person = Person::factory()->create(['yayasan_id' => $lembaga->yayasan_id, 'nama_lengkap' => 'Karyawan Dicari']);
    Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'yayasan_id' => $lembaga->yayasan_id, 'person_id' => $person->id]);

    $role = Role::firstOrCreate(['name' => 'lembaga_admin', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    $response = $this->actingAs($admin)->get(route('admin.karyawan.index', ['search' => 'Karyawan Dicari']));

    $response->assertOk();
    $response->assertSee('Karyawan Dicari');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=KaryawanSearchRegressionTest --compact`
Expected: FAIL

- [ ] **Step 3: Rewrite `KaryawanController.php:61-62`** (the confirmed search/orderBy pattern inside the existing `withoutGlobalScope(TenantScope::class)` manual-rebuild block — keep that surrounding tenant-scope-bypass structure exactly as-is per its existing precedent, only change the identity predicate):

```php
$q->whereHas('person', fn ($p) => $p->where('nama_lengkap', 'like', "%{$search}%"));
```

and `AttendanceController.php:52`, `AttendanceConfigurationController.php:107` the same way, reading each exact site first.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=KaryawanSearchRegressionTest --compact`
Expected: PASS

- [ ] **Step 5: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/KaryawanController.php app/Http/Controllers/Admin/AttendanceController.php app/Http/Controllers/Admin/AttendanceConfigurationController.php tests/Feature/Identity/KaryawanSearchRegressionTest.php
git commit -m "refactor(identity): rewrite Karyawan query-builder identity references through person relation"
```

---

### Task 24: OrangTua query-builder sites (1 point)

**Files:**
- Modify: `app/Http/Controllers/Admin/OrangTuaController.php:36-39`
- Test: `tests/Feature/Identity/OrangTuaSearchRegressionTest.php`

**Interfaces:**
- Consumes: `OrangTua::person()` relation (Task 12), `PersonTenantScope` (Task 13).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Identity\Models\Person;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;

it('still finds an orang tua by name search after the identity columns move to Person', function () {
    $lembaga = Lembaga::factory()->create();
    $person = Person::factory()->create(['yayasan_id' => $lembaga->yayasan_id, 'nama_lengkap' => 'Ortu Dicari']);
    $ortu = OrangTua::factory()->create(['person_id' => $person->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa->orangTua()->attach($ortu->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $role = Role::firstOrCreate(['name' => 'lembaga_admin', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    $response = $this->actingAs($admin)->get(route('admin.orang-tua.index', ['search' => 'Ortu Dicari']));

    $response->assertOk();
    $response->assertSee('Ortu Dicari');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=OrangTuaSearchRegressionTest --compact`
Expected: FAIL

- [ ] **Step 3: Rewrite `OrangTuaController.php:36-39`**

Change the `$q->where('nama_lengkap', ...)`/`->orWhere('nik', ...)` search predicate to:

```php
$q->whereHas('person', fn ($p) => $p->where('nama_lengkap', 'like', "%{$search}%")->orWhere('nik_hash', hash('sha256', $search)));
```

Note: searching `nik` by plaintext `like` is no longer possible once `nik` is `encrypted` on `Person` (it already was encrypted on `Guru`/`Karyawan`/`CalonMurid` before this migration — `OrangTua.nik` was the one exception, plaintext `string(16)`, per spec §1.1 table). If the existing OrangTua search relied on partial/plaintext NIK matching, this is a **necessary behavior narrowing** (exact-match only via hash) — call this out explicitly to the user/reviewer as an intentional, spec-driven behavior change, not a bug.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=OrangTuaSearchRegressionTest --compact`
Expected: PASS

- [ ] **Step 5: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/OrangTuaController.php tests/Feature/Identity/OrangTuaSearchRegressionTest.php
git commit -m "refactor(identity): rewrite OrangTua query-builder identity references through person relation"
```

---

### Task 25: Siswa query-builder sites (13 points)

**Files:**
- Modify: `app/Http/Controllers/Admin/SiswaController.php:31,36`
- Modify: `app/Http/Controllers/Admin/KasusAksesLogController.php:43`
- Modify: `app/Http/Controllers/Admin/KasusTerhapusController.php:37`
- Modify: `app/Http/Controllers/Admin/KasusController.php:54-55`
- Modify: `app/Http/Controllers/Guru/RaporController.php:71,82,94,138`
- Modify: `app/Http/Controllers/Guru/AsesmenController.php:101`
- Modify: `app/Http/Controllers/Lembaga/Keuangan/VirtualAccountController.php:39-40,126-127,135`
- Modify: `app/Http/Controllers/Lembaga/Keuangan/ManualPaymentController.php:34`
- Modify: `app/Domains/Akademik/Services/RaporCalculationService.php:27`
- Modify: `app/Domains/Presensi/Services/PresensiAggregationService.php:17`
- Test: `tests/Feature/Identity/SiswaSearchRegressionTest.php`

**Interfaces:**
- Consumes: `Siswa::person()` relation (Task 12).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Identity\Models\Person;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;

it('still finds a siswa by name search after the identity columns move to Person', function () {
    $lembaga = Lembaga::factory()->create();
    $person = Person::factory()->create(['yayasan_id' => $lembaga->yayasan_id, 'nama_lengkap' => 'Siswa Dicari']);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'person_id' => $person->id]);

    $role = Role::firstOrCreate(['name' => 'lembaga_admin', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    $response = $this->actingAs($admin)->get(route('admin.siswa.index', ['search' => 'Siswa Dicari']));

    $response->assertOk();
    $response->assertSee('Siswa Dicari');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SiswaSearchRegressionTest --compact`
Expected: FAIL

- [ ] **Step 3: For each of the 13 listed sites**, read the exact current code and apply the mechanical rewrite — `where('nama_lengkap', ...)`/`orderBy('nama_lengkap')` on `siswa` becomes:

```php
$q->whereHas('person', fn ($p) => $p->where('nama_lengkap', 'like', "%{$search}%"));
```

for filters, or the join+orderBy pattern (Stage 5 preamble) for ordering. Apply individually at `SiswaController.php:31,36`, `KasusAksesLogController.php:43`, `KasusTerhapusController.php:37`, `KasusController.php:54-55`, `Guru/RaporController.php:71,82,94,138`, `Guru/AsesmenController.php:101`, `Lembaga/Keuangan/VirtualAccountController.php:39-40,126-127,135`, `Lembaga/Keuangan/ManualPaymentController.php:34`, `RaporCalculationService.php:27`, `PresensiAggregationService.php:17`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SiswaSearchRegressionTest --compact`
Expected: PASS

- [ ] **Step 5: Run the broader existing suites these files belong to** (this is the highest-fan-out site group in the whole migration — Kasus, Rapor, Asesmen, Keuangan VA/manual-payment features all touch it):

Run: `php artisan test --filter=Kasus --compact && php artisan test --filter=Rapor --compact && php artisan test --filter=Asesmen --compact && php artisan test --filter=VirtualAccount --compact && php artisan test --filter=ManualPayment --compact`
Expected: PASS. Investigate and fix any regression before proceeding — do not commit with any of these red.

- [ ] **Step 6: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/SiswaController.php app/Http/Controllers/Admin/KasusAksesLogController.php app/Http/Controllers/Admin/KasusTerhapusController.php app/Http/Controllers/Admin/KasusController.php app/Http/Controllers/Guru/RaporController.php app/Http/Controllers/Guru/AsesmenController.php app/Http/Controllers/Lembaga/Keuangan/VirtualAccountController.php app/Http/Controllers/Lembaga/Keuangan/ManualPaymentController.php app/Domains/Akademik/Services/RaporCalculationService.php app/Domains/Presensi/Services/PresensiAggregationService.php tests/Feature/Identity/SiswaSearchRegressionTest.php
git commit -m "refactor(identity): rewrite Siswa query-builder identity references through person relation"
```

---

### Task 26: CalonMurid query-builder sites (3 points)

**Files:**
- Modify: `app/Http/Controllers/Lembaga/Keuangan/TagihanController.php:59`
- Modify: `app/Http/Controllers/Admin/PendaftaranAdminController.php:73`
- Modify: `app/Models/CalonMurid.php:50` (`findByNik()`)
- Test: `tests/Feature/Identity/CalonMuridSearchRegressionTest.php`

**Interfaces:**
- Consumes: `CalonMurid::person()` relation (Task 12).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Identity\Models\Person;
use App\Models\CalonMurid;
use App\Models\Yayasan;

it('findByNik still resolves through Person after nik moves off CalonMurid', function () {
    $yayasan = Yayasan::factory()->create();
    $person = Person::factory()->create(['yayasan_id' => $yayasan->id, 'nik' => '9090909090909090']);
    $calon = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id, 'person_id' => $person->id]);

    $found = CalonMurid::findByNik('9090909090909090');

    expect($found?->id)->toBe($calon->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CalonMuridSearchRegressionTest --compact`
Expected: FAIL

- [ ] **Step 3: Rewrite `CalonMurid::findByNik()` (line 48-51)**

```php
public static function findByNik(string $nik): ?self
{
    return static::whereHas('person', fn ($q) => $q->where('nik_hash', hash('sha256', $nik)))->first();
}
```

- [ ] **Step 4: Rewrite `TagihanController.php:59` and `PendaftaranAdminController.php:73`**

Apply the same `whereHas('person', ...)` pattern to whichever identity field (`nama_lengkap` or `nik_hash`) each site actually queries — read each exact line before editing.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=CalonMuridSearchRegressionTest --compact`
Expected: PASS

- [ ] **Step 6: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Lembaga/Keuangan/TagihanController.php app/Http/Controllers/Admin/PendaftaranAdminController.php app/Models/CalonMurid.php tests/Feature/Identity/CalonMuridSearchRegressionTest.php
git commit -m "refactor(identity): rewrite CalonMurid query-builder identity references through person relation"
```

---

## Stage 6 — Constraint tightening and full verification (Task 27)

### Task 27: `person_id` NOT NULL + FK constraints, full suite green

**This is spec §8 step 5.** Only run this after Tasks 1–26 are all merged and `identity:verify-backfill` passes clean in every environment this branch will run in.

**Files:**
- Create: `database/migrations/2026_08_29_000099_make_person_id_not_null_and_add_fk.php`
- Modify: `app/Models/Guru.php`, `app/Models/Karyawan.php`, `app/Models/OrangTua.php`, `app/Models/Siswa.php`, `app/Models/CalonMurid.php` (add `person()` return type hint already present from Task 12 — no further change expected here, this step is schema-only)
- Test: `tests/Feature/Identity/PersonIdNotNullTest.php`

**Interfaces:**
- Consumes: `identity:verify-backfill` (Task 5) must exit 0 first.

- [ ] **Step 1: Run the verification gate**

Run: `php artisan identity:verify-backfill`
Expected: exit code 0. If non-zero, STOP — do not proceed with this migration until every reported row is fixed (either by re-running Task 4's backfill command or by manual data correction).

- [ ] **Step 2: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guru', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable(false)->change();
            $table->foreign('person_id')->references('id')->on('persons')->restrictOnDelete();
            $table->unique(['person_id', 'lembaga_id'], 'uq_guru_person_lembaga');
        });

        Schema::table('karyawan', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable(false)->change();
            $table->foreign('person_id')->references('id')->on('persons')->restrictOnDelete();
        });

        Schema::table('orang_tua', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable(false)->change();
            $table->foreign('person_id')->references('id')->on('persons')->restrictOnDelete();
            $table->unique('person_id', 'uq_orang_tua_person');
        });

        Schema::table('siswa', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable(false)->change();
            $table->foreign('person_id')->references('id')->on('persons')->restrictOnDelete();
        });

        Schema::table('calon_murid', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable(false)->change();
            $table->foreign('person_id')->references('id')->on('persons')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['guru', 'karyawan', 'orang_tua', 'siswa', 'calon_murid'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropForeign(["{$table}_person_id_foreign"] === null ? 'person_id' : 'person_id');
            });
        }
    }
};
```

Write the `down()` method's exact constraint names to match what Laravel actually generates (`{table}_person_id_foreign` by convention) — verify with `php artisan migrate --pretend` before finalizing, since the placeholder logic above is illustrative only and must be corrected to real constraint names.

- [ ] **Step 3: Write the test**

```php
<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

it('rejects a null person_id insert on guru after the NOT NULL constraint lands', function () {
    $lembaga = \App\Models\Lembaga::factory()->create();

    DB::table('guru')->insert([
        'lembaga_id' => $lembaga->id,
        'person_id' => null,
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'tetap',
        'status_aktif' => 'aktif',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(\Illuminate\Database\QueryException::class);
```

- [ ] **Step 4: Run migration and test**

Run: `php artisan migrate`, then `php artisan test --filter=PersonIdNotNullTest --compact`
Expected: PASS

- [ ] **Step 5: Run the FULL test suite**

Run: `php artisan test --compact`
Expected: ALL PASS. This is the mandatory full-suite gate before this branch is considered mergeable — per this project's convention (`Full Suite Cadence`), a branch touching this many shared/foundational files always requires a full-suite green run before merge, not just scoped tests.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_29_000099_make_person_id_not_null_and_add_fk.php tests/Feature/Identity/PersonIdNotNullTest.php
git commit -m "feat(identity): enforce person_id NOT NULL and FK constraints after full verification"
```

---

## Deferred — NOT part of this release

### - [x] Task 28: Drop legacy identity columns from 5 role tables (Completed 2026-09-01)

Completed on 2026-09-01 per kickoff `kickoff-2026-09-01-identity-v1-task28-drop-columns-schema-squash.md`.
- Legacy columns dropped from `guru` (18 columns), `karyawan` (6 columns), `orang_tua` (6 columns), `siswa` (6 columns), and `calon_murid` (9 columns, keeping `no_kk` & `golongan_darah`).
- Role models, factories, seeders, and controllers cleaned up to exclusively read/write via `Person`.
- Migrations squashed into `database/schema/mysql-schema.sql` via `schema:dump --prune`.
- Test suite verified: 2517 passed, 0 failed.

---

## Self-Review

**1. Spec coverage:**
- §2 (7 design principles) — Task 2 (FK direction, no-BelongsToTenant rationale), Task 6 (yayasan_id never free input + assertion), Task 12 (accessor shims), Task 6 (PersonService as sole entry point), Task 9 (no hard delete). All covered.
- §3 DDL — Task 1 (`persons`), Task 3 (nullable `person_id` + relaxed legacy columns), Task 27 (NOT NULL + FK). Covered. `calon_murid` final shape explicitly deferred per spec's own §3 note.
- §4 PersonService — Tasks 6–10 cover `CreatePersonAction`, `UpdatePersonAction`, `MergePersonsAction`, `Deactivate/ReactivatePersonAction`, `PersonDuplicateFinder`.
- §5.1 (19 create/update points) — Tasks 14–20, all file:line citations carried over verbatim, plus the 5 newly-discovered `AkunSiswaGenerator` call sites folded into Task 17.
- §5.2 (~30 query-builder points) — Tasks 22–26, grouped per model, every cited file:line preserved as a checklist item.
- §5.3 (3 `saving()` hooks) — Task 21 (verification; actual removal happens inside Tasks 2 and 12).
- §5.4 (`User.php`) — Task 11.
- §6 (merge conflict policy) — Task 9.
- §7 (test plan, 9 items) — item 1–3 in Task 6's tests, item 4 implicit in any task creating multi-role Person (covered by Task 9's re-parenting test using both `guru` and `karyawan` on one person), item 5 in Task 11, item 6 in Task 12, item 7–8 in Task 9, item 9 in Tasks 22 and 25 (Guru and Siswa search).
- §8 (6-stage order) — Stage 1 (Tasks 1–3), Stage 2 (Tasks 4–5), Stage 4 code cutover (Tasks 11–21), Stage 6 NOT NULL (Task 27), Task 28 explicitly deferred.
- §9 Non-Goals — respected: no Tagihan/Payroll/Buku Kas work, no new "manage Person" UI, no cross-yayasan merge, `CalonMurid` satellite tables untouched.
- **Two mandatory additions from the user's final message**: cutover-ordering fix is Task 3's title and body explicitly sequenced before Task 14; `PersonTenantScope` is Task 13, explicitly additive to `authorizeLembaga()`.

**2. Placeholder scan:** No TBD/TODO left in executable steps. Three tasks (18, 19, 20) contain an explicit "read the controller in full to confirm the exact session/request contract before finalizing this test" instruction rather than a placeholder — this is intentional and necessary: `SiswaImportController`'s staged-import session shape and `PendaftaranSiswaController`/`ReviewSubmitController`'s exact request/session contracts were referenced by summary in the source audit but their full input-validation code was not quoted verbatim in this plan's available context. Each of those three tasks still specifies the exact code change to make (Steps 3+ have concrete code) — only the test's setup fixture needs a source read first, which is a normal, bounded first sub-step for whoever executes that task, not an open-ended TODO.

**3. Type consistency:** `CreatePersonAction::execute(array $identityData, ?int $lembagaId, ?int $actingYayasanId): Person` is used identically across Tasks 14–20. `UpdatePersonAction::execute(Person $person, array $identityData): Person` likewise. `Person::person()`-reverse relations (`guru()`, `karyawan()`, `orangTua()`, `siswa()` as `HasOne` on `Person`, Task 2) match the `person()` `BelongsTo` added to each role model in Task 12. `MergePersonsAction::execute(Person $losing, Person $winning): void` used consistently. No naming drift found between tasks.
