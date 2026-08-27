# Kurikulum Framework (Priority 1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bangun fondasi kurikulum dinamis — entitas `KurikulumFramework` (enum K13/Merdeka), `KurikulumAssignment` (keputusan admin per lembaga+tahun_ajaran+bentuk_pendidikan+tingkat), resolver 4-level precedence, dan snapshot immutable `Kelas.kurikulum` yang di-set otomatis (tanpa override manual) saat `Kelas` dibuat.

**Architecture:** `KurikulumFramework` murni PHP backed enum (bukan tabel). `KurikulumAssignment` adalah model+tabel baru mirip `FaseDefaultMapping` tapi dengan sumbu `tahun_ajaran_id` wajib. `KurikulumAssignmentResolver` meniru pola ORDER BY precedence `FaseDefaultResolver`, tapi THROW (bukan return null) kalau tidak ada assignment cocok. `CreateKelasAction` memanggil resolver ini dan menyimpan hasilnya ke kolom baru `Kelas.kurikulum`; `UpdateKelasAction` tidak pernah menyentuh kolom ini.

**Tech Stack:** Laravel 12.63.0, Pest v4, MySQL 8.0.30 (virtual generated columns untuk unique constraint dgn NULL).

## Global Constraints

- `KurikulumFramework` HARUS backed enum PHP (`app/Domains/Akademik/Enums/KurikulumFramework.php`), TIDAK BOLEH jadi model/tabel. Cases v1: `K13 = 'k13'`, `Merdeka = 'merdeka'` — HANYA dua ini, tidak lebih.
- `BentukPendidikan` enum baru (`app/Domains/Akademik/Enums/BentukPendidikan.php`) HANYA dipakai di fitur `KurikulumAssignment` ini — DILARANG retrofit 4 lokasi lama (`StoreFaseDefaultMappingRequest`, `LembagaController`, `AcademicProfile`, `RaporPdfDataBuilder`) yang masih hardcode. Itu dicatat sbg technical debt terpisah (lihat Task 6 Step akhir).
- `Kelas.kurikulum` HARUS terkunci setelah dibuat — `UpdateKelasAction` TIDAK BOLEH menerima/menulis field ini dalam kondisi apa pun. `KelasData` TIDAK bertambah field untuk ini.
- `KurikulumAssignmentResolver::resolve()` HARUS throw `KurikulumAssignmentNotFoundException` (bukan return null/optional) kalau tidak ada assignment yang cocok — kegagalan pembuatan Kelas HARUS eksplisit (422), bukan silent `kurikulum=null`.
- Mengedit/menghapus `KurikulumAssignment` TIDAK PERNAH mengubah `Kelas.kurikulum` pada Kelas yang sudah ada.
- `kurikulum_assignment.tahun_ajaran_id` adalah filter EKSAK di resolver (bukan bagian dari precedence fallback) — tidak ada fallback lintas tahun ajaran.
- Ikuti pola FormRequest+DTO+Action penuh (standar TD-AKADEMIK-002) untuk `KurikulumAssignmentController` — bukan validasi inline.
- Jangan tambah menu sidebar baru — `FaseDefaultMappingController` yang jadi acuan pola juga tidak punya entri sidebar (halaman diakses langsung via URL), ikuti presedan yang sama.

---

## Task 1: Enum `KurikulumFramework` dan `BentukPendidikan`

**Files:**
- Create: `app/Domains/Akademik/Enums/KurikulumFramework.php`
- Create: `app/Domains/Akademik/Enums/BentukPendidikan.php`
- Test: `tests/Unit/Domains/Akademik/Enums/KurikulumFrameworkTest.php`
- Test: `tests/Unit/Domains/Akademik/Enums/BentukPendidikanTest.php`

**Interfaces:**
- Produces: `KurikulumFramework::K13`, `KurikulumFramework::Merdeka`, `KurikulumFramework::label(): string`. `BentukPendidikan` cases (`Kb,Tpa,Sps,Tk,Sd,Smp,Sma,Smk,Slb`), `BentukPendidikan::validTingkatValues(): array`.

- [ ] **Step 1: Tulis test untuk `KurikulumFramework`**

```php
<?php

use App\Domains\Akademik\Enums\KurikulumFramework;

it('has exactly two cases: K13 and Merdeka', function () {
    expect(KurikulumFramework::cases())->toHaveCount(2);
    expect(KurikulumFramework::from('k13'))->toBe(KurikulumFramework::K13);
    expect(KurikulumFramework::from('merdeka'))->toBe(KurikulumFramework::Merdeka);
});

it('labels each case in Indonesian', function () {
    expect(KurikulumFramework::K13->label())->toBe('Kurikulum 2013 (K13)');
    expect(KurikulumFramework::Merdeka->label())->toBe('Kurikulum Merdeka');
});
```

Simpan sebagai `tests/Unit/Domains/Akademik/Enums/KurikulumFrameworkTest.php`.

- [ ] **Step 2: Jalankan test, pastikan gagal (class belum ada)**

Run: `php artisan test tests/Unit/Domains/Akademik/Enums/KurikulumFrameworkTest.php`
Expected: FAIL — `Class "App\Domains\Akademik\Enums\KurikulumFramework" not found`

- [ ] **Step 3: Buat `KurikulumFramework`**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Enums;

enum KurikulumFramework: string
{
    case K13 = 'k13';
    case Merdeka = 'merdeka';

    public function label(): string
    {
        return match ($this) {
            self::K13 => 'Kurikulum 2013 (K13)',
            self::Merdeka => 'Kurikulum Merdeka',
        };
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Unit/Domains/Akademik/Enums/KurikulumFrameworkTest.php`
Expected: PASS (2 test)

- [ ] **Step 5: Tulis test untuk `BentukPendidikan`**

```php
<?php

use App\Domains\Akademik\Enums\BentukPendidikan;

it('has exactly the 9 bentuk_pendidikan values from the lembaga ENUM column', function () {
    $values = array_map(fn ($c) => $c->value, BentukPendidikan::cases());
    expect($values)->toEqualCanonicalizing(['KB', 'TPA', 'SPS', 'TK', 'SD', 'SMP', 'SMA', 'SMK', 'SLB']);
});

it('returns A/B for PAUD-type bentuk pendidikan', function () {
    expect(BentukPendidikan::Kb->validTingkatValues())->toBe(['A', 'B']);
    expect(BentukPendidikan::Tpa->validTingkatValues())->toBe(['A', 'B']);
    expect(BentukPendidikan::Sps->validTingkatValues())->toBe(['A', 'B']);
    expect(BentukPendidikan::Tk->validTingkatValues())->toBe(['A', 'B']);
});

it('returns 1-6 for SD and SLB', function () {
    expect(BentukPendidikan::Sd->validTingkatValues())->toBe(['1', '2', '3', '4', '5', '6']);
    expect(BentukPendidikan::Slb->validTingkatValues())->toBe(['1', '2', '3', '4', '5', '6']);
});

it('returns 7-9 for SMP', function () {
    expect(BentukPendidikan::Smp->validTingkatValues())->toBe(['7', '8', '9']);
});

it('returns 10-12 for SMA and SMK', function () {
    expect(BentukPendidikan::Sma->validTingkatValues())->toBe(['10', '11', '12']);
    expect(BentukPendidikan::Smk->validTingkatValues())->toBe(['10', '11', '12']);
});
```

Simpan sebagai `tests/Unit/Domains/Akademik/Enums/BentukPendidikanTest.php`.

- [ ] **Step 6: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Unit/Domains/Akademik/Enums/BentukPendidikanTest.php`
Expected: FAIL — class not found

- [ ] **Step 7: Buat `BentukPendidikan`**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Enums;

enum BentukPendidikan: string
{
    case Kb = 'KB';
    case Tpa = 'TPA';
    case Sps = 'SPS';
    case Tk = 'TK';
    case Sd = 'SD';
    case Smp = 'SMP';
    case Sma = 'SMA';
    case Smk = 'SMK';
    case Slb = 'SLB';

    /**
     * @return array<int, string> Tingkat valid untuk bentuk pendidikan ini.
     */
    public function validTingkatValues(): array
    {
        return match ($this) {
            self::Kb, self::Tpa, self::Sps, self::Tk => ['A', 'B'],
            self::Sd, self::Slb => ['1', '2', '3', '4', '5', '6'],
            self::Smp => ['7', '8', '9'],
            self::Sma, self::Smk => ['10', '11', '12'],
        };
    }
}
```

- [ ] **Step 8: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Unit/Domains/Akademik/Enums/BentukPendidikanTest.php`
Expected: PASS (5 test)

- [ ] **Step 9: Cek lint & commit**

Run: `php -l app/Domains/Akademik/Enums/KurikulumFramework.php && php -l app/Domains/Akademik/Enums/BentukPendidikan.php`
Expected: `No syntax errors detected` (2x)

```bash
git add app/Domains/Akademik/Enums/KurikulumFramework.php app/Domains/Akademik/Enums/BentukPendidikan.php tests/Unit/Domains/Akademik/Enums/KurikulumFrameworkTest.php tests/Unit/Domains/Akademik/Enums/BentukPendidikanTest.php
git commit -m "feat(akademik): tambah enum KurikulumFramework dan BentukPendidikan"
```

---

## Task 2: Model & Migration `KurikulumAssignment`

**Files:**
- Create: `database/migrations/2026_08_27_110000_create_kurikulum_assignment_table.php`
- Create: `app/Domains/Akademik/Models/KurikulumAssignment.php`
- Create: `app/Domains/Akademik/Exceptions/KurikulumAssignmentNotFoundException.php`
- Test: `tests/Unit/Models/KurikulumAssignmentTest.php`

**Interfaces:**
- Consumes: `KurikulumFramework` enum dari Task 1.
- Produces: `KurikulumAssignment` model dgn `$fillable = ['lembaga_id','tahun_ajaran_id','bentuk_pendidikan','tingkat','kurikulum']`, cast `kurikulum` ke `KurikulumFramework`, relasi `lembaga()`/`tahunAjaran()`. Tabel `kurikulum_assignment` dgn unique constraint `kurikulum_assignment_scope_unique` pada `(lembaga_key, tahun_ajaran_id, bentuk_pendidikan, tingkat_key)`.

- [ ] **Step 1: Tulis test model (akan gagal krn tabel belum ada)**

```php
<?php

use App\Domains\Akademik\Enums\KurikulumFramework;
use App\Domains\Akademik\Models\KurikulumAssignment;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('casts kurikulum column to KurikulumFramework enum', function () {
    $lembaga = Lembaga::factory()->create();
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $assignment = KurikulumAssignment::create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $ta->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ]);

    expect($assignment->fresh()->kurikulum)->toBe(KurikulumFramework::Merdeka);
});

it('allows a global default assignment with lembaga_id null', function () {
    $ta = TahunAjaran::factory()->create();

    $assignment = KurikulumAssignment::create([
        'lembaga_id' => null,
        'tahun_ajaran_id' => $ta->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => null,
        'kurikulum' => 'k13',
    ]);

    expect($assignment->fresh()->lembaga_id)->toBeNull();
});

it('rejects a duplicate assignment for the identical scope (lembaga+tahun_ajaran+bentuk+tingkat)', function () {
    $lembaga = Lembaga::factory()->create();
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    KurikulumAssignment::create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $ta->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => null,
        'kurikulum' => 'k13',
    ]);

    $duplikat = fn () => KurikulumAssignment::create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $ta->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => null,
        'kurikulum' => 'merdeka',
    ]);

    expect($duplikat)->toThrow(QueryException::class);
});

it('allows two global-default assignments for different tahun_ajaran (tahun_ajaran_id is part of the unique key)', function () {
    $taA = TahunAjaran::factory()->create();
    $taB = TahunAjaran::factory()->create();

    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $taA->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $taB->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'merdeka']);

    expect(KurikulumAssignment::count())->toBe(2);
});
```

Simpan sebagai `tests/Unit/Models/KurikulumAssignmentTest.php`.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Unit/Models/KurikulumAssignmentTest.php`
Expected: FAIL — table `kurikulum_assignment` doesn't exist

- [ ] **Step 3: Buat migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kurikulum_assignment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->cascadeOnDelete();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->cascadeOnDelete();
            $table->string('bentuk_pendidikan', 10);
            $table->string('tingkat', 10)->nullable();
            $table->string('kurikulum', 20);
            $table->unsignedBigInteger('lembaga_key')->virtualAs('COALESCE(lembaga_id, 0)');
            $table->string('tingkat_key', 10)->virtualAs("COALESCE(tingkat, '*')");
            $table->timestamps();

            $table->unique(
                ['lembaga_key', 'tahun_ajaran_id', 'bentuk_pendidikan', 'tingkat_key'],
                'kurikulum_assignment_scope_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kurikulum_assignment');
    }
};
```

Simpan sebagai `database/migrations/2026_08_27_110000_create_kurikulum_assignment_table.php`.

- [ ] **Step 4: Buat exception class**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Exceptions;

use RuntimeException;

final class KurikulumAssignmentNotFoundException extends RuntimeException {}
```

Simpan sebagai `app/Domains/Akademik/Exceptions/KurikulumAssignmentNotFoundException.php` (pola folder `Domains/{Domain}/Exceptions/` sudah dipakai di `app/Domains/Sdm/Exceptions/`).

- [ ] **Step 5: Buat model**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Models;

use App\Domains\Akademik\Enums\KurikulumFramework;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KurikulumAssignment extends Model
{
    protected $table = 'kurikulum_assignment';

    protected $fillable = [
        'lembaga_id',
        'tahun_ajaran_id',
        'bentuk_pendidikan',
        'tingkat',
        'kurikulum',
    ];

    protected function casts(): array
    {
        return [
            'kurikulum' => KurikulumFramework::class,
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
}
```

- [ ] **Step 6: Jalankan migration test database & jalankan test**

Run: `php artisan test tests/Unit/Models/KurikulumAssignmentTest.php`
Expected: PASS (4 test)

- [ ] **Step 7: Lint & commit**

Run: `php -l app/Domains/Akademik/Models/KurikulumAssignment.php && php -l app/Domains/Akademik/Exceptions/KurikulumAssignmentNotFoundException.php`
Expected: `No syntax errors detected` (2x)

```bash
git add database/migrations/2026_08_27_110000_create_kurikulum_assignment_table.php app/Domains/Akademik/Models/KurikulumAssignment.php app/Domains/Akademik/Exceptions/KurikulumAssignmentNotFoundException.php tests/Unit/Models/KurikulumAssignmentTest.php
git commit -m "feat(akademik): tambah model dan tabel KurikulumAssignment"
```

---

## Task 3: `KurikulumAssignmentResolver`

**Files:**
- Create: `app/Domains/Akademik/Services/KurikulumAssignmentResolver.php`
- Test: `tests/Unit/Services/KurikulumAssignmentResolverTest.php`

**Interfaces:**
- Consumes: `KurikulumAssignment` model (Task 2), `KurikulumFramework` enum (Task 1), `KurikulumAssignmentNotFoundException` (Task 2).
- Produces: `KurikulumAssignmentResolver::resolve(int $tahunAjaranId, string $bentukPendidikan, ?string $tingkat, ?int $lembagaId): KurikulumFramework` — dipakai Task 4's `CreateKelasAction`.

- [ ] **Step 1: Tulis test 4-level precedence + throw (pola identik `FaseDefaultResolverTest`)**

```php
<?php

use App\Domains\Akademik\Exceptions\KurikulumAssignmentNotFoundException;
use App\Domains\Akademik\Models\KurikulumAssignment;
use App\Domains\Akademik\Services\KurikulumAssignmentResolver;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('resolves level 1: lembaga exact-match + tingkat exact-match wins over everything else', function () {
    $lembaga = Lembaga::factory()->create();
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);
    KurikulumAssignment::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);
    KurikulumAssignment::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'merdeka']);

    $hasil = app(KurikulumAssignmentResolver::class)->resolve($ta->id, 'SD', '1', $lembaga->id);

    expect($hasil->value)->toBe('merdeka');
});

it('resolves level 2: lembaga exact-match + tingkat catch-all wins over global rows', function () {
    $lembaga = Lembaga::factory()->create();
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);
    KurikulumAssignment::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'merdeka']);

    $hasil = app(KurikulumAssignmentResolver::class)->resolve($ta->id, 'SD', '1', $lembaga->id);

    expect($hasil->value)->toBe('merdeka');
});

it('resolves level 3: global tingkat exact-match wins over global catch-all', function () {
    $lembaga = Lembaga::factory()->create();
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'merdeka']);

    $hasil = app(KurikulumAssignmentResolver::class)->resolve($ta->id, 'SD', '1', $lembaga->id);

    expect($hasil->value)->toBe('merdeka');
});

it('resolves level 4: falls back to global catch-all when nothing more specific matches', function () {
    $lembaga = Lembaga::factory()->create();
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);

    $hasil = app(KurikulumAssignmentResolver::class)->resolve($ta->id, 'SD', '3', $lembaga->id);

    expect($hasil->value)->toBe('k13');
});

it('throws KurikulumAssignmentNotFoundException when no assignment matches at all', function () {
    $lembaga = Lembaga::factory()->create();
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $resolve = fn () => app(KurikulumAssignmentResolver::class)->resolve($ta->id, 'SMK', '10', $lembaga->id);

    expect($resolve)->toThrow(KurikulumAssignmentNotFoundException::class);
});

it('does not fall back to an assignment from a different tahun_ajaran', function () {
    $lembaga = Lembaga::factory()->create();
    $taLain = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $taIni = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $taLain->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);

    $resolve = fn () => app(KurikulumAssignmentResolver::class)->resolve($taIni->id, 'SD', '1', $lembaga->id);

    expect($resolve)->toThrow(KurikulumAssignmentNotFoundException::class);
});

it('does not leak another lembaga override into this lembaga resolution', function () {
    $lembagaA = Lembaga::factory()->create();
    $lembagaB = Lembaga::factory()->create();
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);

    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);
    KurikulumAssignment::create(['lembaga_id' => $lembagaB->id, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'merdeka']);

    $hasil = app(KurikulumAssignmentResolver::class)->resolve($ta->id, 'SD', '1', $lembagaA->id);

    expect($hasil->value)->toBe('k13');
});
```

Simpan sebagai `tests/Unit/Services/KurikulumAssignmentResolverTest.php`.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Unit/Services/KurikulumAssignmentResolverTest.php`
Expected: FAIL — `Class "App\Domains\Akademik\Services\KurikulumAssignmentResolver" not found`

- [ ] **Step 3: Buat resolver**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Services;

use App\Domains\Akademik\Enums\KurikulumFramework;
use App\Domains\Akademik\Exceptions\KurikulumAssignmentNotFoundException;
use App\Domains\Akademik\Models\KurikulumAssignment;

class KurikulumAssignmentResolver
{
    /**
     * Precedence (paling spesifik -> paling umum), dinyatakan sbg ORDER BY,
     * pola sama seperti FaseDefaultResolver::resolve(). tahun_ajaran_id
     * adalah filter EKSAK, bukan bagian precedence -- tidak ada fallback
     * lintas tahun ajaran.
     *
     * @throws KurikulumAssignmentNotFoundException kalau tidak ada assignment yang cocok sama sekali.
     */
    public function resolve(int $tahunAjaranId, string $bentukPendidikan, ?string $tingkat, ?int $lembagaId): KurikulumFramework
    {
        $query = KurikulumAssignment::where('tahun_ajaran_id', $tahunAjaranId)
            ->where('bentuk_pendidikan', $bentukPendidikan)
            ->where(function ($q) use ($lembagaId) {
                $q->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            })
            ->orderByRaw('lembaga_id IS NULL')
            ->orderByRaw('tingkat IS NULL');

        if ($tingkat !== null) {
            $query->orderByRaw('tingkat = ? DESC', [$tingkat]);
        }

        $match = $query->first();

        if ($match === null) {
            throw new KurikulumAssignmentNotFoundException(
                "Kurikulum belum diatur untuk tahun_ajaran_id={$tahunAjaranId}, bentuk_pendidikan={$bentukPendidikan}, tingkat=".($tingkat ?? 'null').'.'
            );
        }

        return $match->kurikulum;
    }
}
```

Simpan sebagai `app/Domains/Akademik/Services/KurikulumAssignmentResolver.php`.

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Unit/Services/KurikulumAssignmentResolverTest.php`
Expected: PASS (7 test)

- [ ] **Step 5: Lint & commit**

Run: `php -l app/Domains/Akademik/Services/KurikulumAssignmentResolver.php`
Expected: `No syntax errors detected`

```bash
git add app/Domains/Akademik/Services/KurikulumAssignmentResolver.php tests/Unit/Services/KurikulumAssignmentResolverTest.php
git commit -m "feat(akademik): tambah KurikulumAssignmentResolver dgn 4-level precedence"
```

---

## Task 4: Integrasi Snapshot ke `Kelas`

**Files:**
- Create: `database/migrations/2026_08_27_110100_add_kurikulum_to_kelas_table.php`
- Modify: `app/Models/Kelas.php`
- Modify: `app/Domains/Akademik/Actions/Kelas/CreateKelasAction.php`
- Modify: `app/Http/Controllers/Admin/KelasController.php`
- Modify: `tests/Unit/Domains/Akademik/Actions/Kelas/CreateKelasActionTest.php` (retrofit 3 test existing)
- Modify: `tests/Feature/Akademik/KelasFaseAssignmentTest.php` (retrofit helper `buatUserKelas()`)
- Modify: `tests/Feature/Admin/KelasCrudTest.php` (retrofit 1 test: `it('creates a kelas', ...)`)
- Modify: `tests/Feature/Admin/KelasPolaJamTest.php` (retrofit 1 test)
- Test (baru): `tests/Feature/Akademik/KelasKurikulumSnapshotTest.php`

**Interfaces:**
- Consumes: `KurikulumAssignmentResolver::resolve()` (Task 3), `KurikulumAssignmentNotFoundException` (Task 2), `KurikulumFramework` (Task 1).
- Produces: `Kelas.kurikulum` (nullable string, cast ke `KurikulumFramework`), snapshot ditulis HANYA oleh `CreateKelasAction`.

**PENTING — kenapa 4 file test lama harus diretrofit**: `CreateKelasAction` yang baru MEWAJIBKAN ada `KurikulumAssignment` yang cocok sebelum Kelas bisa dibuat (`resolve()` throw kalau tidak ketemu). Test-test lama membuat Kelas TANPA setup assignment apa pun — begitu Task ini selesai, mereka akan gagal KECUALI ditambah 1 baris seed `KurikulumAssignment` per test/helper. Ini retrofit yang DISENGAJA (bukan bug baru), sama persis pola TD-AKADEMIK-001 yang mengubah assertion test lama secara sadar.

- [ ] **Step 1: Tulis test baru untuk integrasi Kelas (akan gagal — kolom & logic belum ada)**

```php
<?php

use App\Domains\Akademik\Models\KurikulumAssignment;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function siapkanKelasKurikulumUser(string $bentukPendidikan = 'SD'): array
{
    foreach (['kelas.view', 'kelas.create', 'kelas.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'operator_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kelas.view', 'kelas.create', 'kelas.edit']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => $bentukPendidikan]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return [$manager, $lembaga, $tahunAjaran];
}

it('rejects creating a kelas with 422-style redirect when no kurikulum assignment matches', function () {
    [$manager, $lembaga, $ta] = siapkanKelasKurikulumUser('SD');
    // TIDAK ada KurikulumAssignment yang dibuat sama sekali.

    $this->actingAs($manager)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $ta->id,
        'nama' => 'Kelas Tanpa Kurikulum',
        'tingkat' => '1',
    ])->assertSessionHasErrors('tingkat');

    expect(Kelas::where('nama', 'Kelas Tanpa Kurikulum')->exists())->toBeFalse();
});

it('snapshots the resolved kurikulum onto Kelas when created', function () {
    [$manager, $lembaga, $ta] = siapkanKelasKurikulumUser('SD');
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'merdeka']);

    $this->actingAs($manager)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $ta->id,
        'nama' => 'Kelas 1A',
        'tingkat' => '1',
    ])->assertRedirect(route('admin.kelas.index'));

    $kelas = Kelas::where('nama', 'Kelas 1A')->firstOrFail();
    expect($kelas->kurikulum->value)->toBe('merdeka');
});

it('does not change Kelas.kurikulum when the underlying assignment is edited afterwards', function () {
    [$manager, $lembaga, $ta] = siapkanKelasKurikulumUser('SD');
    $assignment = KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);

    $this->actingAs($manager)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $ta->id,
        'nama' => 'Kelas Lama',
        'tingkat' => '1',
    ]);
    $kelasLama = Kelas::where('nama', 'Kelas Lama')->firstOrFail();

    $assignment->update(['kurikulum' => 'merdeka']);

    expect($kelasLama->fresh()->kurikulum->value)->toBe('k13');
});

it('does not let UpdateKelasAction change kurikulum via the edit form', function () {
    [$manager, $lembaga, $ta] = siapkanKelasKurikulumUser('SD');
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);

    $this->actingAs($manager)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $ta->id,
        'nama' => 'Kelas Update',
        'tingkat' => '1',
    ]);
    $kelas = Kelas::where('nama', 'Kelas Update')->firstOrFail();
    expect($kelas->kurikulum->value)->toBe('k13');

    $this->actingAs($manager)->put(route('admin.kelas.update', $kelas), [
        'tahun_ajaran_id' => $ta->id,
        'nama' => 'Kelas Update (edited)',
        'tingkat' => '2',
    ])->assertRedirect(route('admin.kelas.index'));

    expect($kelas->fresh()->kurikulum->value)->toBe('k13');
    expect($kelas->fresh()->nama)->toBe('Kelas Update (edited)');
});

it('reads legacy kelas with kurikulum=null without error', function () {
    [$manager, $lembaga, $ta] = siapkanKelasKurikulumUser('SD');
    $legacy = Kelas::create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $ta->id,
        'nama' => 'Kelas Legacy',
        'tingkat' => '1',
    ]);

    expect($legacy->fresh()->kurikulum)->toBeNull();

    $this->actingAs($manager)->get(route('admin.kelas.index'))->assertOk();
});
```

Simpan sebagai `tests/Feature/Akademik/KelasKurikulumSnapshotTest.php`.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Feature/Akademik/KelasKurikulumSnapshotTest.php`
Expected: FAIL — kolom `kurikulum` belum ada di tabel `kelas`

- [ ] **Step 3: Buat migration tambah kolom**

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
            $table->string('kurikulum', 20)->nullable()->after('fase_id');
        });
    }

    public function down(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            $table->dropColumn('kurikulum');
        });
    }
};
```

Simpan sebagai `database/migrations/2026_08_27_110100_add_kurikulum_to_kelas_table.php`.

- [ ] **Step 4: Update `app/Models/Kelas.php`**

Tambahkan `'kurikulum'` ke `$fillable` dan `import` + method `casts()`:

```php
<?php

namespace App\Models;

use App\Domains\Akademik\Enums\KurikulumFramework;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kelas extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'kelas';

    protected $fillable = ['lembaga_id', 'tahun_ajaran_id', 'nama', 'tingkat', 'fase_id', 'kurikulum', 'wali_kelas_guru_id', 'pola_jam_id', 'ruangan_id'];

    protected function casts(): array
    {
        return [
            'kurikulum' => KurikulumFramework::class,
        ];
    }

    public function fase(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Akademik\Models\Fase::class);
    }

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Sarpras\Models\Ruangan::class, 'ruangan_id');
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function polaJam(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Akademik\Models\PolaJam::class);
    }

    public function waliKelas(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'wali_kelas_guru_id');
    }

    public function siswa(): HasMany
    {
        return $this->hasMany(Siswa::class);
    }

    public function jadwalPelajaran(): HasMany
    {
        return $this->hasMany(JadwalPelajaran::class);
    }
}
```

- [ ] **Step 5: Update `CreateKelasAction`**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Kelas;

use App\Domains\Akademik\DataTransferObjects\KelasData;
use App\Domains\Akademik\Models\PolaJam;
use App\Domains\Akademik\Services\KurikulumAssignmentResolver;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\TahunAjaran;

final class CreateKelasAction
{
    public function __construct(
        private readonly KurikulumAssignmentResolver $kurikulumResolver,
    ) {}

    public function execute(KelasData $data, ?int $lembagaIdOverride = null): Kelas
    {
        $tahunAjaran = TahunAjaran::find($data->tahunAjaranId);
        abort_if($tahunAjaran === null, 404);

        $waliKelasGuruId = null;
        if ($data->waliKelasGuruId !== null) {
            $guru = Guru::find($data->waliKelasGuruId);
            abort_if($guru === null || $guru->lembaga_id !== $tahunAjaran->lembaga_id, 404);
            $waliKelasGuruId = $guru->id;
        }

        $polaJamId = null;
        if ($data->polaJamId !== null) {
            $polaJam = PolaJam::find($data->polaJamId);
            abort_if($polaJam === null || $polaJam->lembaga_id !== $tahunAjaran->lembaga_id, 404);
            $polaJamId = $polaJam->id;
        }

        $lembaga = Lembaga::find($tahunAjaran->lembaga_id);
        abort_if($lembaga === null, 404);

        $kurikulum = $this->kurikulumResolver->resolve(
            tahunAjaranId: $tahunAjaran->id,
            bentukPendidikan: $lembaga->bentuk_pendidikan,
            tingkat: $data->tingkat,
            lembagaId: $tahunAjaran->lembaga_id,
        );

        return Kelas::create([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => $data->nama,
            'tingkat' => $data->tingkat,
            'fase_id' => $data->faseId,
            'kurikulum' => $kurikulum,
            'wali_kelas_guru_id' => $waliKelasGuruId,
            'pola_jam_id' => $polaJamId,
            'lembaga_id' => $lembagaIdOverride,
        ]);
    }
}
```

- [ ] **Step 6: Update `KelasController::store()` untuk menangkap exception**

```php
use App\Domains\Akademik\Exceptions\KurikulumAssignmentNotFoundException;
```

Tambahkan import ini di atas file, lalu ubah method `store()`:

```php
    public function store(StoreKelasRequest $request, CreateKelasAction $action): RedirectResponse
    {
        $this->authorize('kelas.create');

        $data = KelasData::fromValidated($request->validated());

        $lembagaIdOverride = null;
        if ($request->user()->widestScopeLevel() === 'yayasan') {
            $lembagaIdOverride = session('active_lembaga_id');

            if ($lembagaIdOverride === null) {
                return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat kelas.'])->withInput();
            }
        }

        try {
            $action->execute($data, $lembagaIdOverride);
        } catch (KurikulumAssignmentNotFoundException $e) {
            return back()->withErrors([
                'tingkat' => 'Kurikulum belum diatur untuk kombinasi jenjang dan tingkat ini. Atur dulu di menu Pengaturan Kurikulum.',
            ])->withInput();
        }

        return redirect()->route('admin.kelas.index')->with('status', 'Kelas berhasil disimpan.');
    }
```

Method lain (`faseSuggestion`, `index`, `create`, `edit`, `update`) TIDAK BERUBAH.

- [ ] **Step 7: Jalankan test baru, pastikan lulus**

Run: `php artisan test tests/Feature/Akademik/KelasKurikulumSnapshotTest.php`
Expected: PASS (5 test)

- [ ] **Step 8: Retrofit `CreateKelasActionTest.php` — tambah seed KurikulumAssignment ke 3 test**

Ganti isi file `tests/Unit/Domains/Akademik/Actions/Kelas/CreateKelasActionTest.php` menjadi:

```php
<?php

use App\Domains\Akademik\Actions\Kelas\CreateKelasAction;
use App\Domains\Akademik\DataTransferObjects\KelasData;
use App\Domains\Akademik\Models\KurikulumAssignment;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates a kelas with minimal fields', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $tahunAjaran->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);
    $role = Role::firstOrCreate(['name' => 'operator_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);
    $this->actingAs($user);

    $kelas = app(CreateKelasAction::class)->execute(new KelasData(
        tahunAjaranId: $tahunAjaran->id,
        nama: 'Kelas 1A',
        tingkat: '1',
        faseId: null,
        waliKelasGuruId: null,
        polaJamId: null,
    ));

    expect($kelas->fresh()->nama)->toBe('Kelas 1A');
    expect($kelas->fresh()->tahun_ajaran_id)->toBe($tahunAjaran->id);
    expect($kelas->fresh()->lembaga_id)->toBe($lembaga->id);
    expect($kelas->fresh()->kurikulum->value)->toBe('k13');
});

it('aborts with 404 when wali_kelas_guru_id belongs to a different lembaga than the tahun ajaran', function () {
    $lembagaA = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $lembagaB = Lembaga::factory()->create();
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $tahunAjaran->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);
    $guruLain = Guru::withoutGlobalScopes()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembagaB->id])->id,
        'lembaga_id' => $lembagaB->id,
        'nik' => '3201234567899999',
        'nama' => 'Guru Lembaga Lain',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);

    $execute = fn () => app(CreateKelasAction::class)->execute(new KelasData(
        tahunAjaranId: $tahunAjaran->id,
        nama: 'Kelas 1A',
        tingkat: '1',
        faseId: null,
        waliKelasGuruId: $guruLain->id,
        polaJamId: null,
    ));

    expect($execute)->toThrow(NotFoundHttpException::class);
});

it('overrides lembaga_id when provided (yayasan-scope create)', function () {
    $lembagaTarget = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaTarget->id]);
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $tahunAjaran->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);

    $kelas = app(CreateKelasAction::class)->execute(new KelasData(
        tahunAjaranId: $tahunAjaran->id,
        nama: 'Kelas 1A',
        tingkat: '1',
        faseId: null,
        waliKelasGuruId: null,
        polaJamId: null,
    ), lembagaIdOverride: $lembagaTarget->id);

    expect($kelas->fresh()->lembaga_id)->toBe($lembagaTarget->id);
});
```

Catatan: test kedua (`aborts with 404 ...`) tidak butuh assignment utk `lembagaB` krn abort 404 terjadi SEBELUM resolver dipanggil (urutan kode Step 5 di atas) — tapi tetap diberi assignment utk `lembagaA`/`$tahunAjaran` supaya kalau urutan berubah di masa depan, test tetap valid krn assignment sudah tersedia.

- [ ] **Step 9: Retrofit `KelasFaseAssignmentTest.php` — 1 baris di helper `buatUserKelas()`**

Ubah fungsi `buatUserKelas()`:

```php
use App\Domains\Akademik\Models\KurikulumAssignment;

function buatUserKelas(): array
{
    Permission::firstOrCreate(['name' => 'kelas.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'kelas.create', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'kelas.edit', 'guard_name' => 'web']);

    $role = Role::firstOrCreate(['name' => 'operator_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kelas.view', 'kelas.create', 'kelas.edit']);

    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $tahunAjaran->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    return [$user, $lembaga, $tahunAjaran];
}
```

Tambahkan `use App\Domains\Akademik\Models\KurikulumAssignment;` di bagian import file. Isi test-test lain di file ini TIDAK BERUBAH.

- [ ] **Step 10: Retrofit `KelasCrudTest.php` — 1 test `it('creates a kelas', ...)`**

Tambahkan import `use App\Domains\Akademik\Models\KurikulumAssignment;` di atas file, lalu ubah test:

```php
it('creates a kelas', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $tahunAjaran->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);
    $manager = actingAsKelasManager($lembaga);

    $this->actingAs($manager)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => '6A',
        'tingkat' => '6',
    ])->assertRedirect(route('admin.kelas.index'));

    expect(Kelas::where('nama', '6A')->exists())->toBeTrue();
});
```

Test lain di file ini TIDAK BERUBAH (mereka 404 sebelum resolver dipanggil, atau memakai `Kelas::create()` langsung yang bypass Action).

- [ ] **Step 11: Retrofit `KelasPolaJamTest.php` — 1 test**

Tambahkan import `use App\Domains\Akademik\Models\KurikulumAssignment;`, lalu ubah test:

```php
it('assigns a pola jam to a kelas on create', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $tahunAjaran->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);

    foreach (['kelas.view', 'kelas.create', 'kelas.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'operator_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
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

- [ ] **Step 12: Jalankan seluruh test terkait Kelas, pastikan semua lulus**

Run: `php artisan test tests/Unit/Domains/Akademik/Actions/Kelas/CreateKelasActionTest.php tests/Feature/Akademik/KelasFaseAssignmentTest.php tests/Feature/Admin/KelasCrudTest.php tests/Feature/Admin/KelasPolaJamTest.php tests/Feature/Akademik/KelasKurikulumSnapshotTest.php tests/Unit/Models/KelasTest.php`
Expected: PASS (semua test, 0 failed)

- [ ] **Step 13: Lint & commit**

Run: `vendor/bin/pint --dirty --format agent`
Expected: exit sukses, semua file terformat

```bash
git add database/migrations/2026_08_27_110100_add_kurikulum_to_kelas_table.php app/Models/Kelas.php app/Domains/Akademik/Actions/Kelas/CreateKelasAction.php app/Http/Controllers/Admin/KelasController.php tests/Unit/Domains/Akademik/Actions/Kelas/CreateKelasActionTest.php tests/Feature/Akademik/KelasFaseAssignmentTest.php tests/Feature/Admin/KelasCrudTest.php tests/Feature/Admin/KelasPolaJamTest.php tests/Feature/Akademik/KelasKurikulumSnapshotTest.php
git commit -m "feat(akademik): snapshot Kelas.kurikulum otomatis saat create, terkunci setelahnya"
```

---

## Task 5: CRUD Admin `KurikulumAssignment` (Pengaturan Kurikulum)

**Files:**
- Create: `app/Domains/Akademik/DataTransferObjects/KurikulumAssignmentData.php`
- Create: `app/Domains/Akademik/Actions/KurikulumAssignment/CreateKurikulumAssignmentAction.php`
- Create: `app/Domains/Akademik/Actions/KurikulumAssignment/UpdateKurikulumAssignmentAction.php`
- Create: `app/Http/Requests/Akademik/StoreKurikulumAssignmentRequest.php`
- Create: `app/Http/Requests/Akademik/UpdateKurikulumAssignmentRequest.php`
- Create: `app/Http/Controllers/Admin/KurikulumAssignmentController.php`
- Create: `resources/views/admin/kurikulum-assignment/_form.blade.php`
- Create: `resources/views/admin/kurikulum-assignment/index.blade.php`
- Create: `resources/views/admin/kurikulum-assignment/create.blade.php`
- Create: `resources/views/admin/kurikulum-assignment/edit.blade.php`
- Modify: `routes/admin/akademik-master.php`
- Modify: `database/seeders/PermissionSeeder.php`
- Modify: `database/seeders/RoleSeeder.php`
- Test: `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`

**Interfaces:**
- Consumes: `KurikulumAssignment` model (Task 2), `KurikulumFramework`/`BentukPendidikan` enum (Task 1).
- Produces: route `admin.kurikulum-assignment.{index,create,store,edit,update,destroy}`, permission `kurikulum-assignment.{view,create,edit,delete}`.

- [ ] **Step 1: Buat DTO**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class KurikulumAssignmentData
{
    public function __construct(
        public string $bentukPendidikan,
        public ?string $tingkat,
        public string $kurikulum,
        public ?int $lembagaId,
        public int $tahunAjaranId,
    ) {}
}
```

Simpan sebagai `app/Domains/Akademik/DataTransferObjects/KurikulumAssignmentData.php`.

- [ ] **Step 2: Buat Actions**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\KurikulumAssignment;

use App\Domains\Akademik\DataTransferObjects\KurikulumAssignmentData;
use App\Domains\Akademik\Models\KurikulumAssignment;

final class CreateKurikulumAssignmentAction
{
    public function execute(KurikulumAssignmentData $data): KurikulumAssignment
    {
        return KurikulumAssignment::create([
            'lembaga_id' => $data->lembagaId,
            'tahun_ajaran_id' => $data->tahunAjaranId,
            'bentuk_pendidikan' => $data->bentukPendidikan,
            'tingkat' => $data->tingkat,
            'kurikulum' => $data->kurikulum,
        ]);
    }
}
```

Simpan sebagai `app/Domains/Akademik/Actions/KurikulumAssignment/CreateKurikulumAssignmentAction.php`.

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\KurikulumAssignment;

use App\Domains\Akademik\DataTransferObjects\KurikulumAssignmentData;
use App\Domains\Akademik\Models\KurikulumAssignment;

final class UpdateKurikulumAssignmentAction
{
    public function execute(KurikulumAssignment $assignment, KurikulumAssignmentData $data): KurikulumAssignment
    {
        $assignment->update([
            'bentuk_pendidikan' => $data->bentukPendidikan,
            'tingkat' => $data->tingkat,
            'kurikulum' => $data->kurikulum,
        ]);

        return $assignment;
    }
}
```

Simpan sebagai `app/Domains/Akademik/Actions/KurikulumAssignment/UpdateKurikulumAssignmentAction.php`. Perhatikan: `lembaga_id`/`tahun_ajaran_id` TIDAK BISA diubah lewat update (pola sama `UpdateFaseDefaultMappingAction`) — hanya create yang menentukan scope-nya.

- [ ] **Step 3: Buat FormRequests dgn validasi silang bentuk_pendidikan+tingkat**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\Enums\BentukPendidikan;
use App\Domains\Akademik\Enums\KurikulumFramework;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreKurikulumAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bentuk_pendidikan' => ['required', Rule::enum(BentukPendidikan::class)],
            'tingkat' => ['nullable', 'string', 'max:10'],
            'kurikulum' => ['required', Rule::enum(KurikulumFramework::class)],
            'tahun_ajaran_id' => ['required', 'integer', 'exists:tahun_ajaran,id'],
            'lembaga_id' => ['nullable', 'integer', 'exists:lembaga,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $bentukPendidikan = $this->input('bentuk_pendidikan');
            $tingkat = $this->input('tingkat');

            if ($tingkat === null || $tingkat === '' || ! BentukPendidikan::tryFrom((string) $bentukPendidikan)) {
                return;
            }

            $valid = BentukPendidikan::from($bentukPendidikan)->validTingkatValues();

            if (! in_array($tingkat, $valid, true)) {
                $validator->errors()->add('tingkat', "Tingkat '{$tingkat}' tidak valid untuk bentuk pendidikan {$bentukPendidikan}. Nilai valid: ".implode(', ', $valid).'.');
            }
        });
    }
}
```

Simpan sebagai `app/Http/Requests/Akademik/StoreKurikulumAssignmentRequest.php`.

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\Enums\BentukPendidikan;
use App\Domains\Akademik\Enums\KurikulumFramework;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateKurikulumAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bentuk_pendidikan' => ['required', Rule::enum(BentukPendidikan::class)],
            'tingkat' => ['nullable', 'string', 'max:10'],
            'kurikulum' => ['required', Rule::enum(KurikulumFramework::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $bentukPendidikan = $this->input('bentuk_pendidikan');
            $tingkat = $this->input('tingkat');

            if ($tingkat === null || $tingkat === '' || ! BentukPendidikan::tryFrom((string) $bentukPendidikan)) {
                return;
            }

            $valid = BentukPendidikan::from($bentukPendidikan)->validTingkatValues();

            if (! in_array($tingkat, $valid, true)) {
                $validator->errors()->add('tingkat', "Tingkat '{$tingkat}' tidak valid untuk bentuk pendidikan {$bentukPendidikan}. Nilai valid: ".implode(', ', $valid).'.');
            }
        });
    }
}
```

Simpan sebagai `app/Http/Requests/Akademik/UpdateKurikulumAssignmentRequest.php`.

- [ ] **Step 4: Buat Controller**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Actions\KurikulumAssignment\CreateKurikulumAssignmentAction;
use App\Domains\Akademik\Actions\KurikulumAssignment\UpdateKurikulumAssignmentAction;
use App\Domains\Akademik\DataTransferObjects\KurikulumAssignmentData;
use App\Domains\Akademik\Enums\BentukPendidikan;
use App\Domains\Akademik\Enums\KurikulumFramework;
use App\Domains\Akademik\Models\KurikulumAssignment;
use App\Http\Requests\Akademik\StoreKurikulumAssignmentRequest;
use App\Http\Requests\Akademik\UpdateKurikulumAssignmentRequest;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class KurikulumAssignmentController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('kurikulum-assignment.view');

        $isPlatformOrYayasan = $this->isPlatformOrYayasan($request);

        $query = KurikulumAssignment::with(['lembaga', 'tahunAjaran']);

        if (! $isPlatformOrYayasan) {
            $query->where(function ($q) use ($request) {
                $q->whereNull('lembaga_id')->orWhere('lembaga_id', $request->user()->lembaga_id);
            });
        }

        return view('admin.kurikulum-assignment.index', [
            'assignmentList' => $query->orderByDesc('tahun_ajaran_id')->orderBy('bentuk_pendidikan')->orderByRaw('tingkat IS NULL')->orderBy('tingkat')->get(),
            'isPlatformOrYayasan' => $isPlatformOrYayasan,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('kurikulum-assignment.create');

        return view('admin.kurikulum-assignment.create', [
            'kurikulumList' => KurikulumFramework::cases(),
            'bentukPendidikanList' => BentukPendidikan::cases(),
            'tahunAjaranList' => $this->tahunAjaranListForScope($request),
            'lembagaList' => $this->isPlatformOrYayasan($request) ? Lembaga::orderBy('nama')->get() : collect(),
            'isPlatformOrYayasan' => $this->isPlatformOrYayasan($request),
        ]);
    }

    public function store(StoreKurikulumAssignmentRequest $request, CreateKurikulumAssignmentAction $action): RedirectResponse
    {
        $this->authorize('kurikulum-assignment.create');

        $validated = $request->validated();
        $tingkat = ($validated['tingkat'] ?? '') !== '' ? $validated['tingkat'] : null;

        $isPlatformOrYayasan = $this->isPlatformOrYayasan($request);
        $lembagaId = $isPlatformOrYayasan ? ($validated['lembaga_id'] ?? null) : $request->user()->lembaga_id;

        $this->authorizeAssignmentScope($request, $lembagaId);

        if (KurikulumAssignment::where('lembaga_id', $lembagaId)->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])->where('bentuk_pendidikan', $validated['bentuk_pendidikan'])->where('tingkat', $tingkat)->exists()) {
            return back()->withErrors(['bentuk_pendidikan' => 'Sudah ada assignment kurikulum untuk kombinasi tahun ajaran, jenjang, dan tingkat ini. Edit baris yang ada, jangan buat duplikat.'])->withInput();
        }

        $action->execute(new KurikulumAssignmentData(
            bentukPendidikan: $validated['bentuk_pendidikan'],
            tingkat: $tingkat,
            kurikulum: $validated['kurikulum'],
            lembagaId: $lembagaId,
            tahunAjaranId: (int) $validated['tahun_ajaran_id'],
        ));

        return redirect()->route('admin.kurikulum-assignment.index')->with('status', 'Assignment kurikulum berhasil disimpan.');
    }

    public function edit(Request $request, KurikulumAssignment $kurikulumAssignment): View
    {
        $this->authorize('kurikulum-assignment.edit');
        $this->authorizeAssignmentScope($request, $kurikulumAssignment->lembaga_id);

        return view('admin.kurikulum-assignment.edit', [
            'assignment' => $kurikulumAssignment,
            'kurikulumList' => KurikulumFramework::cases(),
            'bentukPendidikanList' => BentukPendidikan::cases(),
        ]);
    }

    public function update(UpdateKurikulumAssignmentRequest $request, KurikulumAssignment $kurikulumAssignment, UpdateKurikulumAssignmentAction $action): RedirectResponse
    {
        $this->authorize('kurikulum-assignment.edit');
        $this->authorizeAssignmentScope($request, $kurikulumAssignment->lembaga_id);

        $validated = $request->validated();
        $tingkat = ($validated['tingkat'] ?? '') !== '' ? $validated['tingkat'] : null;

        if (KurikulumAssignment::where('id', '!=', $kurikulumAssignment->id)->where('lembaga_id', $kurikulumAssignment->lembaga_id)->where('tahun_ajaran_id', $kurikulumAssignment->tahun_ajaran_id)->where('bentuk_pendidikan', $validated['bentuk_pendidikan'])->where('tingkat', $tingkat)->exists()) {
            return back()->withErrors(['bentuk_pendidikan' => 'Sudah ada assignment kurikulum untuk kombinasi tahun ajaran, jenjang, dan tingkat ini. Edit baris yang ada, jangan buat duplikat.'])->withInput();
        }

        $action->execute($kurikulumAssignment, new KurikulumAssignmentData(
            bentukPendidikan: $validated['bentuk_pendidikan'],
            tingkat: $tingkat,
            kurikulum: $validated['kurikulum'],
            lembagaId: $kurikulumAssignment->lembaga_id,
            tahunAjaranId: $kurikulumAssignment->tahun_ajaran_id,
        ));

        return redirect()->route('admin.kurikulum-assignment.index')->with('status', 'Assignment kurikulum berhasil diperbarui.');
    }

    public function destroy(Request $request, KurikulumAssignment $kurikulumAssignment): RedirectResponse
    {
        $this->authorize('kurikulum-assignment.delete');
        $this->authorizeAssignmentScope($request, $kurikulumAssignment->lembaga_id);

        $kurikulumAssignment->delete();

        return redirect()->route('admin.kurikulum-assignment.index')->with('status', 'Assignment kurikulum berhasil dihapus.');
    }

    private function isPlatformOrYayasan(Request $request): bool
    {
        return in_array($request->user()->widestScopeLevel(), ['platform', 'yayasan'], true);
    }

    private function authorizeAssignmentScope(Request $request, ?int $lembagaIdDiminta): void
    {
        $isPlatformOrYayasan = $this->isPlatformOrYayasan($request);

        if ($lembagaIdDiminta === null) {
            abort_unless($isPlatformOrYayasan, 403);

            return;
        }

        abort_unless($isPlatformOrYayasan || $lembagaIdDiminta === $request->user()->lembaga_id, 403);
    }

    private function tahunAjaranListForScope(Request $request)
    {
        if ($this->isPlatformOrYayasan($request)) {
            return TahunAjaran::orderByDesc('tanggal_mulai')->get();
        }

        return TahunAjaran::where('lembaga_id', $request->user()->lembaga_id)->orderByDesc('tanggal_mulai')->get();
    }
}
```

Simpan sebagai `app/Http/Controllers/Admin/KurikulumAssignmentController.php`.

- [ ] **Step 5: Tambah routes**

Tambahkan di `routes/admin/akademik-master.php`, setelah blok `fase-mapping` (baris 24) dan sebelum blok `kalender-akademik`:

```php
use App\Http\Controllers\Admin\KurikulumAssignmentController;
```

(tambahkan import ini di bagian atas file, urut alfabetis dgn import lain)

```php
Route::get('kurikulum-assignment', [KurikulumAssignmentController::class, 'index'])->name('kurikulum-assignment.index');
Route::get('kurikulum-assignment/create', [KurikulumAssignmentController::class, 'create'])->name('kurikulum-assignment.create');
Route::post('kurikulum-assignment', [KurikulumAssignmentController::class, 'store'])->name('kurikulum-assignment.store');
Route::get('kurikulum-assignment/{kurikulumAssignment}/edit', [KurikulumAssignmentController::class, 'edit'])->name('kurikulum-assignment.edit');
Route::put('kurikulum-assignment/{kurikulumAssignment}', [KurikulumAssignmentController::class, 'update'])->name('kurikulum-assignment.update');
Route::delete('kurikulum-assignment/{kurikulumAssignment}', [KurikulumAssignmentController::class, 'destroy'])->name('kurikulum-assignment.destroy');
```

- [ ] **Step 6: Tambah permission ke seeder**

Di `database/seeders/PermissionSeeder.php`, tambahkan baris setelah baris `fase-mapping.*` (baris 57):

```php
            'kurikulum-assignment.view', 'kurikulum-assignment.create', 'kurikulum-assignment.edit', 'kurikulum-assignment.delete',
```

Di `database/seeders/RoleSeeder.php`, tambahkan ke blok `operator_akademik` (setelah baris `fase-mapping.*`, baris 137):

```php
                    'kurikulum-assignment.view', 'kurikulum-assignment.create', 'kurikulum-assignment.edit', 'kurikulum-assignment.delete',
```

- [ ] **Step 7: Buat views**

`resources/views/admin/kurikulum-assignment/_form.blade.php`:

```blade
@php
    $assignment = $assignment ?? null;
    $val = fn (string $field, $default = '') => old($field, $assignment?->$field ?? $default);
@endphp

<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
    <div class="border-b border-gray-100 bg-white px-6 py-4">
        <p class="flex items-center gap-2 font-display text-sm font-bold text-gray-900">
            <x-icon name="group" class="h-4 w-4 text-brand-500" />
            Assignment Kurikulum
        </p>
        <p class="mt-0.5 text-xs text-gray-500">Kurikulum yang berlaku untuk jenjang &amp; tingkat pada tahun ajaran tertentu. Kelas baru akan otomatis mengikuti assignment ini saat dibuat.</p>
    </div>

    <div class="p-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">
            @if ($isPlatformOrYayasan ?? false)
                <div class="sm:col-span-6">
                    <x-input-label value="Berlaku Untuk" />
                    <select name="lembaga_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="" @selected($val('lembaga_id') === '')>— Platform (semua lembaga) —</option>
                        @foreach ($lembagaList as $lembaga)
                            <option value="{{ $lembaga->id }}" @selected($val('lembaga_id') == $lembaga->id)>{{ $lembaga->nama }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('lembaga_id')" class="mt-1.5" />
                </div>
            @endif

            @if (! $assignment)
                <div class="sm:col-span-6">
                    <x-input-label value="Tahun Ajaran" />
                    <select name="tahun_ajaran_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($tahunAjaranList as $ta)
                            <option value="{{ $ta->id }}" @selected($val('tahun_ajaran_id') == $ta->id)>{{ $ta->nama }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('tahun_ajaran_id')" class="mt-1.5" />
                </div>
            @else
                <div class="sm:col-span-6">
                    <x-input-label value="Tahun Ajaran" />
                    <p class="mt-1.5 rounded-lg border border-gray-100 bg-gray-50 px-3.5 py-2.5 text-sm text-gray-600">{{ $assignment->tahunAjaran->nama }} (tidak bisa diubah setelah dibuat)</p>
                </div>
            @endif

            <div class="sm:col-span-6">
                <x-input-label value="Bentuk Pendidikan" />
                <select name="bentuk_pendidikan" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($bentukPendidikanList as $bp)
                        <option value="{{ $bp->value }}" @selected($val('bentuk_pendidikan') === $bp->value)>{{ $bp->value }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('bentuk_pendidikan')" class="mt-1.5" />
            </div>

            <div class="sm:col-span-6">
                <x-input-label value="Tingkat (kosongkan = berlaku semua tingkat)" />
                <x-text-input type="text" name="tingkat" value="{{ $val('tingkat') }}" placeholder="Contoh: 1, 10, A (kosongkan utk catch-all)" class="mt-1.5 w-full" />
                <x-input-error :messages="$errors->get('tingkat')" class="mt-1.5" />
            </div>

            <div class="sm:col-span-12">
                <x-input-label value="Kurikulum" />
                <select name="kurikulum" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($kurikulumList as $k)
                        <option value="{{ $k->value }}" @selected($val('kurikulum') === $k->value)>{{ $k->label() }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('kurikulum')" class="mt-1.5" />
            </div>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3 rounded-b-2xl border-t border-gray-100 bg-gray-50 px-6 py-4">
        <a href="{{ route('admin.kurikulum-assignment.index') }}" class="inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-200/50 hover:text-gray-900">Batal</a>
        <x-primary-button type="submit">{{ $submitText ?? 'Simpan' }}</x-primary-button>
    </div>
</div>
```

`resources/views/admin/kurikulum-assignment/create.blade.php`:

```blade
<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4">
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <h1 class="font-display text-lg font-bold text-gray-900">Tambah Assignment Kurikulum</h1>

        <form method="POST" action="{{ route('admin.kurikulum-assignment.store') }}">
            @csrf
            @include('admin.kurikulum-assignment._form', ['kurikulumList' => $kurikulumList, 'bentukPendidikanList' => $bentukPendidikanList, 'tahunAjaranList' => $tahunAjaranList, 'lembagaList' => $lembagaList, 'isPlatformOrYayasan' => $isPlatformOrYayasan, 'submitText' => 'Simpan Assignment'])
        </form>
    </div>
</x-app-layout>
```

`resources/views/admin/kurikulum-assignment/edit.blade.php`:

```blade
<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4">
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <h1 class="font-display text-lg font-bold text-gray-900">Edit Assignment Kurikulum</h1>

        <form method="POST" action="{{ route('admin.kurikulum-assignment.update', $assignment) }}">
            @csrf
            @method('PUT')
            @include('admin.kurikulum-assignment._form', ['assignment' => $assignment, 'kurikulumList' => $kurikulumList, 'bentukPendidikanList' => $bentukPendidikanList, 'isPlatformOrYayasan' => false, 'submitText' => 'Simpan Perubahan'])
        </form>
    </div>
</x-app-layout>
```

`resources/views/admin/kurikulum-assignment/index.blade.php`:

```blade
<x-app-layout>
    <div class="space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif

        <div class="flex items-center justify-between">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Pengaturan Kurikulum</h1>
                <p class="text-xs text-gray-500">Kurikulum yang berlaku per jenjang, tingkat, dan tahun ajaran. Kelas baru mengikuti ini otomatis saat dibuat.</p>
            </div>
            @can('kurikulum-assignment.create')
                <a href="{{ route('admin.kurikulum-assignment.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                    <x-icon name="plus" class="h-4 w-4" />
                    Tambah Assignment
                </a>
            @endcan
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">Scope</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">Tahun Ajaran</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">Bentuk Pendidikan</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">Tingkat</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">Kurikulum</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse ($assignmentList as $a)
                        <tr class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-6 py-3.5 text-sm">
                                @if ($a->lembaga_id === null)
                                    <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">Platform Default</span>
                                @else
                                    <span class="inline-flex rounded-full bg-purple-50 px-2.5 py-0.5 text-xs font-medium text-purple-700">{{ $a->lembaga->nama ?? 'Lembaga #' . $a->lembaga_id }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-6 py-3.5 text-sm text-gray-600">{{ $a->tahunAjaran->nama ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-3.5 text-sm font-semibold text-gray-900">{{ $a->bentuk_pendidikan }}</td>
                            <td class="whitespace-nowrap px-6 py-3.5 text-sm text-gray-600">{{ $a->tingkat ?? 'Semua Tingkat' }}</td>
                            <td class="whitespace-nowrap px-6 py-3.5 text-sm text-gray-900">{{ $a->kurikulum->label() }}</td>
                            <td class="whitespace-nowrap px-6 py-3.5 text-right text-sm">
                                @php
                                    $canManage = $isPlatformOrYayasan || ($a->lembaga_id !== null && $a->lembaga_id === auth()->user()->lembaga_id);
                                @endphp
                                @if ($canManage)
                                    <div class="inline-flex items-center gap-2">
                                        @can('kurikulum-assignment.edit')
                                            <a href="{{ route('admin.kurikulum-assignment.edit', $a) }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Edit</a>
                                        @endcan
                                        @can('kurikulum-assignment.delete')
                                            <form method="POST" action="{{ route('admin.kurikulum-assignment.destroy', $a) }}" onsubmit="return confirm('Hapus assignment ini?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs font-semibold text-error-600 hover:text-error-700">Hapus</button>
                                            </form>
                                        @endcan
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400">Read-only (Platform)</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">Belum ada assignment kurikulum yang dikonfigurasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 8: Tulis test feature CRUD (pola mirip `FaseDefaultMappingControllerTest`)**

```php
<?php

use App\Domains\Akademik\Models\KurikulumAssignment;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function actingAsKurikulumAssignmentManager(Lembaga $lembaga): User
{
    foreach (['kurikulum-assignment.view', 'kurikulum-assignment.create', 'kurikulum-assignment.edit', 'kurikulum-assignment.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'operator_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kurikulum-assignment.view', 'kurikulum-assignment.create', 'kurikulum-assignment.edit', 'kurikulum-assignment.delete']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('denies access to a user without kurikulum-assignment.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.kurikulum-assignment.index'))->assertForbidden();
});

it('creates a kurikulum assignment', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKurikulumAssignmentManager($lembaga);

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.store'), [
        'tahun_ajaran_id' => $ta->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ])->assertRedirect(route('admin.kurikulum-assignment.index'));

    expect(KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->where('tingkat', '1')->exists())->toBeTrue();
});

it('rejects an invalid tingkat for the given bentuk_pendidikan', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKurikulumAssignmentManager($lembaga);

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.store'), [
        'tahun_ajaran_id' => $ta->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '13',
        'kurikulum' => 'merdeka',
    ])->assertSessionHasErrors('tingkat');

    expect(KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->exists())->toBeFalse();
});

it('rejects a duplicate assignment for the same scope via the controller duplicate-check', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKurikulumAssignmentManager($lembaga);
    KurikulumAssignment::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.store'), [
        'tahun_ajaran_id' => $ta->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ])->assertSessionHasErrors('bentuk_pendidikan');

    expect(KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->where('tingkat', '1')->count())->toBe(1);
});

it('updates a kurikulum assignment without changing its lembaga or tahun_ajaran scope', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKurikulumAssignmentManager($lembaga);
    $assignment = KurikulumAssignment::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $this->actingAs($manager)->put(route('admin.kurikulum-assignment.update', $assignment), [
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ])->assertRedirect(route('admin.kurikulum-assignment.index'));

    expect($assignment->fresh()->kurikulum->value)->toBe('merdeka');
    expect($assignment->fresh()->lembaga_id)->toBe($lembaga->id);
    expect($assignment->fresh()->tahun_ajaran_id)->toBe($ta->id);
});

it('deletes a kurikulum assignment', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKurikulumAssignmentManager($lembaga);
    $assignment = KurikulumAssignment::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $this->actingAs($manager)->delete(route('admin.kurikulum-assignment.destroy', $assignment))->assertRedirect(route('admin.kurikulum-assignment.index'));

    expect(KurikulumAssignment::find($assignment->id))->toBeNull();
});

it('forbids a lembaga-scoped user from managing another lembaga\'s assignment', function () {
    $lembagaSaya = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $lembagaLain = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $taLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $manager = actingAsKurikulumAssignmentManager($lembagaSaya);
    $assignmentLain = KurikulumAssignment::create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $taLain->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $this->actingAs($manager)->get(route('admin.kurikulum-assignment.edit', $assignmentLain))->assertForbidden();
});
```

Simpan sebagai `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`.

- [ ] **Step 9: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`
Expected: PASS (7 test)

- [ ] **Step 10: Jalankan `php artisan route:list` untuk verifikasi routes terdaftar**

Run: `php artisan route:list --name=kurikulum-assignment`
Expected: 6 routes tampil (index/create/store/edit/update/destroy)

- [ ] **Step 11: Lint & commit**

Run: `vendor/bin/pint --dirty --format agent`
Expected: exit sukses

```bash
git add app/Domains/Akademik/DataTransferObjects/KurikulumAssignmentData.php app/Domains/Akademik/Actions/KurikulumAssignment/ app/Http/Requests/Akademik/StoreKurikulumAssignmentRequest.php app/Http/Requests/Akademik/UpdateKurikulumAssignmentRequest.php app/Http/Controllers/Admin/KurikulumAssignmentController.php resources/views/admin/kurikulum-assignment/ routes/admin/akademik-master.php database/seeders/PermissionSeeder.php database/seeders/RoleSeeder.php tests/Feature/Akademik/KurikulumAssignmentControllerTest.php
git commit -m "feat(akademik): CRUD admin Pengaturan Kurikulum (KurikulumAssignment)"
```

---

## Task 6: Regresi Penuh & Technical Debt Note

**Files:**
- Modify: `PETA_PENGEMBANGAN.md` (tandai Prioritas #1 SELESAI + catat TD-AKADEMIK-003)
- Tidak ada file kode baru — task ini murni verifikasi.

- [ ] **Step 1: Grep referensi liar / sisa hardcode yang seharusnya dipakai enum baru**

Run: `grep -rn "KurikulumFramework\|KurikulumAssignment" app/ database/ tests/ routes/ resources/views/ --include="*.php" --include="*.blade.php" | wc -l`
Expected: jumlah > 0, tinjau manual bahwa semua referensi berasal dari Task 1-5 (tidak ada file tak terduga yang ikut menyebut nama ini)

- [ ] **Step 2: Jalankan full test suite tanpa filter**

Run: `php artisan test --compact`
Expected: 0 failed. Catat angka pasti (passed/skipped/assertions) untuk laporan akhir.

- [ ] **Step 3: Migrasi dev database nyata**

Run: `php artisan migrate`
Expected: 2 migration baru berhasil (`create_kurikulum_assignment_table`, `add_kurikulum_to_kelas_table`), tidak ada error.

- [ ] **Step 4: Update `PETA_PENGEMBANGAN.md`**

Di bagian `## 🔵 Roadmap Kurikulum Dinamis`, ubah baris tabel Prioritas #1 kolom "Status" dari `Belum Ada` menjadi:

```
✅ SELESAI (27 Agustus 2026) — lihat `.agents/specs/2026-08-27-akademik-kurikulum-framework-priority1.md`
```

Tambahkan paragraf baru setelah tabel prioritas (sebelum "**Urutan kerja disarankan**"):

```markdown
**Prioritas #1 SELESAI (27 Agustus 2026)**: `KurikulumFramework` (enum K13/Merdeka) + `KurikulumAssignment` (assignment per lembaga+tahun_ajaran+bentuk_pendidikan+tingkat, resolver 4-level precedence, throw kalau tidak ketemu) + snapshot immutable `Kelas.kurikulum` (di-set otomatis saat create, terkunci setelahnya, tidak pernah diubah `UpdateKelasAction`). Halaman admin "Pengaturan Kurikulum" (`admin.kurikulum-assignment.*`) sudah bisa dipakai. Dieksekusi lewat `.agents/plans/2026-08-27-akademik-kurikulum-framework-priority1.md`.

**Technical debt baru dicatat — `TD-AKADEMIK-003` (kandidat)**: `bentuk_pendidikan` masih di-hardcode terpisah di 4 lokasi lama (`StoreFaseDefaultMappingRequest.php`, `LembagaController.php`, `AcademicProfile.php`, `RaporPdfDataBuilder.php`) dengan daftar yang tidak selalu identik. Enum `BentukPendidikan` baru (`app/Domains/Akademik/Enums/BentukPendidikan.php`, dibuat khusus utk fitur ini) bisa jadi sumber tunggal kalau 4 lokasi ini di-retrofit — effort Kecil-Sedang, tidak urgent.
```

- [ ] **Step 5: Commit**

```bash
git add PETA_PENGEMBANGAN.md
git commit -m "docs: tandai Prioritas 1 Roadmap Kurikulum Dinamis SELESAI, catat TD-AKADEMIK-003"
```
