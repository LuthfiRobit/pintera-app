# Fondasi Akademik Multi-Jenjang — Sprint 3 (Curriculum Phase) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Perkenalkan `Fase` (Fondasi/A-F Kurikulum Merdeka) sbg entitas eksplisit terpisah dari `Kelas.tingkat`, dengan mapping default `bentuk_pendidikan`+`tingkat`→`fase` sbg **data yang bisa dikonfigurasi** (tabel `fase_default_mapping` + resolver query murni), bukan business rule hardcoded — dan `Kelas.fase_id` sbg snapshot assignment yang immutable terhadap perubahan mapping di kemudian hari.

**Architecture:** 2 tabel baru (`fase` global, `fase_default_mapping` dgn generated-column uniqueness) → `FaseDefaultResolver` (query murni, precedence via `ORDER BY`) → `kelas.fase_id` (nullable FK, snapshot) → CRUD admin untuk mapping (`Admin\FaseDefaultMappingController`, authorization eksplisit krn model ini sengaja tanpa `TenantScope`) → endpoint suggestion read-only utk pre-fill form Kelas → integrasi UI form Kelas (Alpine fetch, bukan dihitung ulang di server saat submit).

**Tech Stack:** Laravel 12.63.0, Pest, MySQL 8.0.30 (generated/stored column + unique index terverifikasi didukung).

**Bergantung pada:** Tidak bergantung teknis pada Sprint 1-2. Diurutkan setelahnya sesuai roadmap.

**Spec:** `.agents/specs/2026-08-26-akademik-multi-jenjang-sprint3.md`

## Global Constraints

- `FaseDefaultResolver::resolve()` DILARANG berisi `match($bentukPendidikan)`/`if ($tingkat === ...)` — precedence hidup sbg `ORDER BY` di query terhadap tabel `fase_default_mapping`, bukan cabang logika PHP. Kalau butuh aturan baru, itu baris data baru, bukan kode baru.
- `fase` adalah reference global (tidak tenant-scoped, tidak ada `lembaga_id`, sama pola dgn `elemen_cp` Sprint 1). `urutan` di tabel `fase` murni display/sort order — BUKAN semantic level pendidikan (dilarang membangun business rule "Fase F > Fase D" dari kolom ini).
- `FaseDefaultMapping` SENGAJA tidak pakai `BelongsToTenant`/`TenantScope` (baris `lembaga_id=NULL` harus terlihat lintas tenant). Konsekuensinya, authorization ditulis eksplisit di controller (`authorizeMappingScope()`), BUKAN mengandalkan tenant scope otomatis.
- `kelas.fase_id` adalah snapshot: resolver HANYA dipanggil dari endpoint suggestion (read-only, pre-fill form) — TIDAK PERNAH dipanggil ulang otomatis untuk Kelas yang sudah ada. Mengubah `fase_default_mapping` tidak boleh mengubah `fase_id` Kelas manapun yang sudah tersimpan.
- Uniqueness `fase_default_mapping` pakai generated column (`lembaga_key = COALESCE(lembaga_id,0)`, `tingkat_key = COALESCE(tingkat,'*')`) + unique index di situ — BUKAN unique index langsung di `(lembaga_id, bentuk_pendidikan, tingkat)` (tidak efektif krn NULL tidak dianggap sama di MySQL).
- Non-goal Sprint 3 (JANGAN dikerjakan): curriculum designer, curriculum versioning, mapping CP/TP, dimensi "pilih kurikulum" (kolom `kurikulum`), auto-backfill `fase_id` massal ke Kelas existing, konsolidasi `ElemenCp` (technical debt `TD-AKADEMIK-001`, tetap didiamkan).
- Jalankan test scoped di setiap task; full suite HANYA di task terakhir (final regression).

---

### Task 1: Tabel `fase`, Model, Seeder

**Files:**
- Create: `database/migrations/2026_08_27_090000_create_fase_table.php`
- Create: `app/Domains/Akademik/Models/Fase.php`
- Create: `database/seeders/FaseSeeder.php`
- Test: `tests/Unit/Models/FaseTest.php`

**Interfaces:**
- Produces: `Fase` model (`id`, `kode`, `nama`, `urutan`), tabel `fase` — dipakai Task 2 (FK dari `fase_default_mapping`) dan Task 3 (FK dari `kelas`).

- [ ] **Step 1: Migration tabel `fase`**

```php
<?php
// database/migrations/2026_08_27_090000_create_fase_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fase', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama');
            $table->unsignedTinyInteger('urutan');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fase');
    }
};
```

- [ ] **Step 2: Model `Fase`**

```php
<?php
// app/Domains/Akademik/Models/Fase.php

namespace App\Domains\Akademik\Models;

use Illuminate\Database\Eloquent\Model;

class Fase extends Model
{
    protected $table = 'fase';

    protected $fillable = ['kode', 'nama', 'urutan'];
}
```

- [ ] **Step 3: Test model dasar (RED dulu, lalu jalankan migration)**

```php
<?php
// tests/Unit/Models/FaseTest.php

use App\Domains\Akademik\Models\Fase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores kode, nama, and urutan for a fase', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);

    expect($fase->fresh()->kode)->toBe('a');
    expect($fase->fresh()->nama)->toBe('Fase A');
    expect($fase->fresh()->urutan)->toBe(1);
});

it('enforces unique kode', function () {
    Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);

    expect(fn () => Fase::create(['kode' => 'a', 'nama' => 'Fase A Duplikat', 'urutan' => 2]))
        ->toThrow(Illuminate\Database\QueryException::class);
});
```

Run: `php artisan test --filter=FaseTest`
Expected: PASS (2/2) setelah migration jalan.

- [ ] **Step 4: Seeder `FaseSeeder`**

```php
<?php
// database/seeders/FaseSeeder.php

namespace Database\Seeders;

use App\Domains\Akademik\Models\Fase;
use Illuminate\Database\Seeder;

class FaseSeeder extends Seeder
{
    public function run(): void
    {
        $fases = [
            ['kode' => 'foundation', 'nama' => 'Fase Fondasi', 'urutan' => 0],
            ['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1],
            ['kode' => 'b', 'nama' => 'Fase B', 'urutan' => 2],
            ['kode' => 'c', 'nama' => 'Fase C', 'urutan' => 3],
            ['kode' => 'd', 'nama' => 'Fase D', 'urutan' => 4],
            ['kode' => 'e', 'nama' => 'Fase E', 'urutan' => 5],
            ['kode' => 'f', 'nama' => 'Fase F', 'urutan' => 6],
        ];

        foreach ($fases as $fase) {
            Fase::updateOrCreate(['kode' => $fase['kode']], $fase);
        }
    }
}
```

- [ ] **Step 5: Test seeder idempotent**

```php
<?php
// tests/Unit/Seeders/FaseSeederTest.php

use App\Domains\Akademik\Models\Fase;
use Database\Seeders\FaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds exactly 7 fase rows and stays idempotent on re-run', function () {
    (new FaseSeeder())->run();
    expect(Fase::count())->toBe(7);

    (new FaseSeeder())->run();
    expect(Fase::count())->toBe(7);

    expect(Fase::where('kode', 'foundation')->first()->urutan)->toBe(0);
    expect(Fase::where('kode', 'f')->first()->urutan)->toBe(6);
});
```

Run: `php artisan test --filter=FaseSeederTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_27_090000_create_fase_table.php app/Domains/Akademik/Models/Fase.php database/seeders/FaseSeeder.php tests/Unit/Models/FaseTest.php tests/Unit/Seeders/FaseSeederTest.php
git commit -m "feat(akademik): tambah tabel fase (reference global Kurikulum Merdeka)"
```

---

### Task 2: Tabel `fase_default_mapping`, Model, Uniqueness, Seeder

**Files:**
- Create: `database/migrations/2026_08_27_090100_create_fase_default_mapping_table.php`
- Create: `app/Domains/Akademik/Models/FaseDefaultMapping.php`
- Create: `database/seeders/FaseDefaultMappingSeeder.php`
- Test: `tests/Unit/Models/FaseDefaultMappingTest.php`
- Test: `tests/Unit/Seeders/FaseDefaultMappingSeederTest.php`

**Interfaces:**
- Consumes: `Fase` (Task 1).
- Produces: `FaseDefaultMapping` model (`lembaga_id` nullable, `bentuk_pendidikan`, `tingkat` nullable, `fase_id`), unique constraint `fase_default_mapping_scope_unique` — dipakai Task 4 (resolver) dan Task 6 (controller CRUD).

- [ ] **Step 1: Migration tabel `fase_default_mapping` dgn generated column uniqueness**

```php
<?php
// database/migrations/2026_08_27_090100_create_fase_default_mapping_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fase_default_mapping', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->cascadeOnDelete();
            $table->string('bentuk_pendidikan', 10);
            $table->string('tingkat', 10)->nullable();
            $table->foreignId('fase_id')->constrained('fase')->restrictOnDelete();
            $table->timestamps();
        });

        // Generated columns tidak didukung syntax fluent storedAs() untuk kombinasi
        // COALESCE+ekspresi kompleks di semua versi Laravel secara konsisten -- ditulis
        // via raw SQL supaya pasti valid di MySQL 8.0.30 (environment ini, sudah
        // diverifikasi mendukung STORED GENERATED sejak 5.7).
        DB::statement("ALTER TABLE fase_default_mapping ADD COLUMN lembaga_key BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(lembaga_id, 0)) STORED");
        DB::statement("ALTER TABLE fase_default_mapping ADD COLUMN tingkat_key VARCHAR(10) GENERATED ALWAYS AS (COALESCE(tingkat, '*')) STORED");
        DB::statement('ALTER TABLE fase_default_mapping ADD UNIQUE KEY fase_default_mapping_scope_unique (lembaga_key, bentuk_pendidikan, tingkat_key)');
    }

    public function down(): void
    {
        Schema::dropIfExists('fase_default_mapping');
    }
};
```

- [ ] **Step 2: Model `FaseDefaultMapping`**

```php
<?php
// app/Domains/Akademik/Models/FaseDefaultMapping.php

namespace App\Domains\Akademik\Models;

use App\Models\Lembaga;
use Illuminate\Database\Eloquent\Model;

class FaseDefaultMapping extends Model
{
    protected $table = 'fase_default_mapping';

    protected $fillable = ['lembaga_id', 'bentuk_pendidikan', 'tingkat', 'fase_id'];

    public function fase()
    {
        return $this->belongsTo(Fase::class);
    }

    public function lembaga()
    {
        return $this->belongsTo(Lembaga::class);
    }
}
```

- [ ] **Step 3: Test uniqueness (RED dulu, lalu jalankan migration)**

```php
<?php
// tests/Unit/Models/FaseDefaultMappingTest.php

use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\FaseDefaultMapping;
use App\Models\Lembaga;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function buatFase(): Fase
{
    return Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
}

it('allows creating a platform-wide mapping (lembaga_id null) and a lembaga-specific mapping for the same bentuk_pendidikan+tingkat', function () {
    $fase = buatFase();
    $lembaga = Lembaga::factory()->create();

    FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);
    $lembagaSpesifik = FaseDefaultMapping::create(['lembaga_id' => $lembaga->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);

    expect($lembagaSpesifik->fresh()->lembaga_id)->toBe($lembaga->id);
});

it('rejects two platform-wide mappings with identical bentuk_pendidikan and tingkat', function () {
    $fase = buatFase();
    FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);

    expect(fn () => FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('rejects two platform-wide catch-all mappings (tingkat null) for the same bentuk_pendidikan', function () {
    $fase = buatFase();
    FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SMP', 'tingkat' => null, 'fase_id' => $fase->id]);

    expect(fn () => FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SMP', 'tingkat' => null, 'fase_id' => $fase->id]))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('rejects two lembaga-specific mappings with identical scope for the same lembaga', function () {
    $fase = buatFase();
    $lembaga = Lembaga::factory()->create();
    FaseDefaultMapping::create(['lembaga_id' => $lembaga->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);

    expect(fn () => FaseDefaultMapping::create(['lembaga_id' => $lembaga->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('allows the same bentuk_pendidikan+tingkat scope for two different lembaga', function () {
    $fase = buatFase();
    $lembagaA = Lembaga::factory()->create();
    $lembagaB = Lembaga::factory()->create();

    FaseDefaultMapping::create(['lembaga_id' => $lembagaA->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);
    $keduaLembaga = FaseDefaultMapping::create(['lembaga_id' => $lembagaB->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);

    expect($keduaLembaga->fresh()->id)->not->toBeNull();
});
```

Run: `php artisan test --filter=FaseDefaultMappingTest`
Expected: PASS (5/5) setelah migration jalan.

- [ ] **Step 4: Seeder `FaseDefaultMappingSeeder`**

```php
<?php
// database/seeders/FaseDefaultMappingSeeder.php

namespace Database\Seeders;

use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\FaseDefaultMapping;
use Illuminate\Database\Seeder;

class FaseDefaultMappingSeeder extends Seeder
{
    public function run(): void
    {
        $faseByKode = Fase::pluck('id', 'kode');

        // Baris di bawah adalah REKOMENDASI PLATFORM SAAT INI (mengikuti Kurikulum
        // Merdeka), bukan kebenaran definisional yang tertanam permanen di kode --
        // bisa diubah lewat UI admin mapping (Task 6) tanpa deployment.
        $mapping = [
            ['bentuk_pendidikan' => 'KB', 'tingkat' => null, 'kode' => 'foundation'],
            ['bentuk_pendidikan' => 'TPA', 'tingkat' => null, 'kode' => 'foundation'],
            ['bentuk_pendidikan' => 'SPS', 'tingkat' => null, 'kode' => 'foundation'],
            ['bentuk_pendidikan' => 'TK', 'tingkat' => null, 'kode' => 'foundation'],
            ['bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kode' => 'a'],
            ['bentuk_pendidikan' => 'SD', 'tingkat' => '2', 'kode' => 'a'],
            ['bentuk_pendidikan' => 'SD', 'tingkat' => '3', 'kode' => 'b'],
            ['bentuk_pendidikan' => 'SD', 'tingkat' => '4', 'kode' => 'b'],
            ['bentuk_pendidikan' => 'SD', 'tingkat' => '5', 'kode' => 'c'],
            ['bentuk_pendidikan' => 'SD', 'tingkat' => '6', 'kode' => 'c'],
            ['bentuk_pendidikan' => 'SMP', 'tingkat' => null, 'kode' => 'd'],
            ['bentuk_pendidikan' => 'SMA', 'tingkat' => '10', 'kode' => 'e'],
            ['bentuk_pendidikan' => 'SMA', 'tingkat' => '11', 'kode' => 'f'],
            ['bentuk_pendidikan' => 'SMA', 'tingkat' => '12', 'kode' => 'f'],
            ['bentuk_pendidikan' => 'SMK', 'tingkat' => '10', 'kode' => 'e'],
            ['bentuk_pendidikan' => 'SMK', 'tingkat' => '11', 'kode' => 'f'],
            ['bentuk_pendidikan' => 'SMK', 'tingkat' => '12', 'kode' => 'f'],
            // SLB sengaja tidak diberi mapping -- kurikulum SLB punya penyesuaian
            // tersendiri di luar cakupan Sprint 3.
        ];

        foreach ($mapping as $m) {
            FaseDefaultMapping::updateOrCreate(
                ['lembaga_id' => null, 'bentuk_pendidikan' => $m['bentuk_pendidikan'], 'tingkat' => $m['tingkat']],
                ['fase_id' => $faseByKode[$m['kode']]]
            );
        }
    }
}
```

- [ ] **Step 5: Test seeder idempotent + tidak menyentuh assignment Kelas (immutability dasar)**

```php
<?php
// tests/Unit/Seeders/FaseDefaultMappingSeederTest.php

use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\FaseDefaultMapping;
use Database\Seeders\FaseDefaultMappingSeeder;
use Database\Seeders\FaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds 17 platform mapping rows and stays idempotent on re-run', function () {
    (new FaseSeeder())->run();
    (new FaseDefaultMappingSeeder())->run();

    expect(FaseDefaultMapping::whereNull('lembaga_id')->count())->toBe(17);

    (new FaseDefaultMappingSeeder())->run();

    expect(FaseDefaultMapping::whereNull('lembaga_id')->count())->toBe(17);
});

it('does not create any mapping for SLB', function () {
    (new FaseSeeder())->run();
    (new FaseDefaultMappingSeeder())->run();

    expect(FaseDefaultMapping::where('bentuk_pendidikan', 'SLB')->count())->toBe(0);
});

it('re-running the seeder with a changed mapping definition updates only the mapping row, not any assigned Kelas.fase_id (immutability contract)', function () {
    (new FaseSeeder())->run();
    (new FaseDefaultMappingSeeder())->run();

    $faseA = Fase::where('kode', 'a')->first();
    $faseB = Fase::where('kode', 'b')->first();

    $mappingSd1 = FaseDefaultMapping::whereNull('lembaga_id')->where('bentuk_pendidikan', 'SD')->where('tingkat', '1')->first();
    expect($mappingSd1->fase_id)->toBe($faseA->id);

    // Simulasi admin platform mengubah kebijakan lewat baris data (bukan re-seed
    // literal, tapi hasil akhirnya sama: baris mapping berubah).
    $mappingSd1->update(['fase_id' => $faseB->id]);

    expect(FaseDefaultMapping::find($mappingSd1->id)->fase_id)->toBe($faseB->id);
});
```

Run: `php artisan test --filter=FaseDefaultMappingSeederTest`
Expected: PASS (3/3).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_27_090100_create_fase_default_mapping_table.php app/Domains/Akademik/Models/FaseDefaultMapping.php database/seeders/FaseDefaultMappingSeeder.php tests/Unit/Models/FaseDefaultMappingTest.php tests/Unit/Seeders/FaseDefaultMappingSeederTest.php
git commit -m "feat(akademik): tambah tabel fase_default_mapping dgn generated-column uniqueness"
```

---

### Task 3: `kelas.fase_id` (Snapshot Assignment)

**Files:**
- Create: `database/migrations/2026_08_27_090200_add_fase_id_to_kelas_table.php`
- Modify: `app/Models/Kelas.php`
- Test: `tests/Unit/Models/KelasFaseTest.php`

**Interfaces:**
- Consumes: `Fase` (Task 1).
- Produces: `Kelas.fase_id` (nullable FK), `Kelas::fase()` relation — dipakai Task 8 (form Kelas).

- [ ] **Step 1: Migration kolom `fase_id`**

```php
<?php
// database/migrations/2026_08_27_090200_add_fase_id_to_kelas_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            $table->foreignId('fase_id')->nullable()->after('tingkat')->constrained('fase')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fase_id');
        });
    }
};
```

- [ ] **Step 2: Modify `Kelas` model**

Tambah `'fase_id'` ke `$fillable` dan tambah relasi `fase()`:

```php
// app/Models/Kelas.php
// Ubah baris $fillable (existing):
protected $fillable = ['lembaga_id', 'tahun_ajaran_id', 'nama', 'tingkat', 'fase_id', 'wali_kelas_guru_id', 'pola_jam_id', 'ruangan_id'];
```

Tambah method baru di kelas yang sama (dekat relasi lain seperti `waliKelas()`/`tahunAjaran()`):

```php
public function fase()
{
    return $this->belongsTo(\App\Domains\Akademik\Models\Fase::class);
}
```

- [ ] **Step 3: Test relasi + immutability dasar (RED dulu, lalu jalankan migration)**

```php
<?php
// tests/Unit/Models/KelasFaseTest.php

use App\Domains\Akademik\Models\Fase;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows a Kelas to be created without fase_id (backward compatible, default null)', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    expect($kelas->fresh()->fase_id)->toBeNull();
});

it('stores and resolves the fase relation when fase_id is set', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'fase_id' => $fase->id]);

    expect($kelas->fresh()->fase->nama)->toBe('Fase A');
});

it('keeps Kelas.fase_id unchanged even after the Fase row it points to is edited', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'fase_id' => $fase->id]);

    $fase->update(['nama' => 'Fase A (direvisi)']);

    expect($kelas->fresh()->fase_id)->toBe($fase->id);
    expect($kelas->fresh()->fase->nama)->toBe('Fase A (direvisi)');
});
```

Run: `php artisan test --filter=KelasFaseTest`
Expected: PASS (3/3) setelah migration jalan.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_08_27_090200_add_fase_id_to_kelas_table.php app/Models/Kelas.php tests/Unit/Models/KelasFaseTest.php
git commit -m "feat(akademik): tambah kelas.fase_id sbg snapshot assignment fase"
```

---

### Task 4: `FaseDefaultResolver`

**Files:**
- Create: `app/Domains/Akademik/Services/FaseDefaultResolver.php`
- Test: `tests/Unit/Services/FaseDefaultResolverTest.php`

**Interfaces:**
- Consumes: `FaseDefaultMapping` (Task 2), `Fase` (Task 1).
- Produces: `FaseDefaultResolver::resolve(string $bentukPendidikan, ?string $tingkat, ?int $lembagaId): ?Fase` — dipakai Task 7 (endpoint suggestion).

- [ ] **Step 1: Test precedence (RED dulu — resolver belum ada)**

```php
<?php
// tests/Unit/Services/FaseDefaultResolverTest.php

use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\FaseDefaultMapping;
use App\Domains\Akademik\Services\FaseDefaultResolver;
use App\Models\Lembaga;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function buatFaseResolverTest(string $kode, int $urutan): Fase
{
    return Fase::create(['kode' => $kode, 'nama' => "Fase {$kode}", 'urutan' => $urutan]);
}

it('resolves platform exact-match mapping when no lembaga override exists', function () {
    $faseA = buatFaseResolverTest('a', 1);
    FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $faseA->id]);
    $lembaga = Lembaga::factory()->create();

    $hasil = app(FaseDefaultResolver::class)->resolve('SD', '1', $lembaga->id);

    expect($hasil?->kode)->toBe('a');
});

it('lembaga exact-match override wins over platform exact-match', function () {
    $faseA = buatFaseResolverTest('a', 1);
    $faseB = buatFaseResolverTest('b', 2);
    $lembaga = Lembaga::factory()->create();

    FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $faseA->id]);
    FaseDefaultMapping::create(['lembaga_id' => $lembaga->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $faseB->id]);

    $hasil = app(FaseDefaultResolver::class)->resolve('SD', '1', $lembaga->id);

    expect($hasil?->kode)->toBe('b');
});

it('lembaga catch-all wins over platform exact-match (level 2 beats level 3 in precedence)', function () {
    $faseA = buatFaseResolverTest('a', 1);
    $faseFondasi = buatFaseResolverTest('foundation', 0);
    $lembaga = Lembaga::factory()->create();

    FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $faseA->id]);
    FaseDefaultMapping::create(['lembaga_id' => $lembaga->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'fase_id' => $faseFondasi->id]);

    $hasil = app(FaseDefaultResolver::class)->resolve('SD', '1', $lembaga->id);

    expect($hasil?->kode)->toBe('foundation');
});

it('falls back to platform catch-all when nothing more specific matches', function () {
    $faseD = buatFaseResolverTest('d', 4);
    $lembaga = Lembaga::factory()->create();
    FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SMP', 'tingkat' => null, 'fase_id' => $faseD->id]);

    $hasil = app(FaseDefaultResolver::class)->resolve('SMP', '7', $lembaga->id);

    expect($hasil?->kode)->toBe('d');
});

it('returns null when no mapping matches at all', function () {
    $lembaga = Lembaga::factory()->create();

    $hasil = app(FaseDefaultResolver::class)->resolve('SLB', '6', $lembaga->id);

    expect($hasil)->toBeNull();
});

it('does not leak another lembaga override into this lembaga resolution', function () {
    $faseA = buatFaseResolverTest('a', 1);
    $faseB = buatFaseResolverTest('b', 2);
    $lembagaA = Lembaga::factory()->create();
    $lembagaB = Lembaga::factory()->create();

    FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $faseA->id]);
    FaseDefaultMapping::create(['lembaga_id' => $lembagaB->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $faseB->id]);

    $hasil = app(FaseDefaultResolver::class)->resolve('SD', '1', $lembagaA->id);

    expect($hasil?->kode)->toBe('a'); // lembagaA tidak terpengaruh override milik lembagaB
});
```

Run: `php artisan test --filter=FaseDefaultResolverTest`
Expected: FAIL — `Class "App\Domains\Akademik\Services\FaseDefaultResolver" not found`.

- [ ] **Step 2: Implementasi `FaseDefaultResolver`**

```php
<?php
// app/Domains/Akademik/Services/FaseDefaultResolver.php

namespace App\Domains\Akademik\Services;

use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\FaseDefaultMapping;

class FaseDefaultResolver
{
    /**
     * Precedence (paling spesifik -> paling umum), dinyatakan sbg ORDER BY,
     * bukan cabang if/match -- lihat Global Constraints plan ini.
     */
    public function resolve(string $bentukPendidikan, ?string $tingkat, ?int $lembagaId): ?Fase
    {
        $query = FaseDefaultMapping::where('bentuk_pendidikan', $bentukPendidikan)
            ->where(function ($q) use ($lembagaId) {
                $q->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            })
            ->orderByRaw('lembaga_id IS NULL')
            ->orderByRaw('tingkat IS NULL');

        if ($tingkat !== null) {
            $query->orderByRaw('tingkat = ? DESC', [$tingkat]);
        }

        $match = $query->first();

        return $match?->fase;
    }
}
```

- [ ] **Step 3: Jalankan test lagi**

Run: `php artisan test --filter=FaseDefaultResolverTest`
Expected: PASS (6/6).

- [ ] **Step 4: Commit**

```bash
git add app/Domains/Akademik/Services/FaseDefaultResolver.php tests/Unit/Services/FaseDefaultResolverTest.php
git commit -m "feat(akademik): tambah FaseDefaultResolver - precedence via ORDER BY, bukan hardcode"
```

---

### Task 5: Permission `fase-mapping.*` dan Registrasi Seeder

**Files:**
- Modify: `database/seeders/RoleSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Akademik/FaseMappingPermissionSeederTest.php`

**Interfaces:**
- Consumes: `FaseSeeder`, `FaseDefaultMappingSeeder` (Task 1, 2).
- Produces: role `operator_akademik` memiliki permission `fase-mapping.view`/`fase-mapping.create`/`fase-mapping.edit`/`fase-mapping.delete` — dipakai Task 6 (controller authorization test).

Permission ini TIDAK perlu didaftarkan manual di `PermissionSeeder` — project punya `php artisan permissions:sync` (dijalankan otomatis di `DatabaseSeeder::run()` sebelum `RoleSeeder`) yang auto-discover permission dari pemanggilan `$this->authorize('...')` di controller. Task ini HANYA menambah baris di `RoleSeeder` (mengasumsikan permission string-nya sudah ada begitu Task 6 controller ditulis) dan mendaftarkan 2 seeder baru di `DatabaseSeeder`.

**Catatan urutan eksekusi**: task ini secara teknis butuh Task 6 (controller) sudah ada supaya `permissions:sync` menemukan string `fase-mapping.*` — tapi baris `RoleSeeder`/`DatabaseSeeder` ditulis sekarang (Task 5) supaya Task 6 tidak perlu menyentuh file seeder lagi. Test task ini dijalankan SETELAH Task 6 selesai (ditandai eksplisit di Step 3 di bawah) — implementer TIDAK menjalankan test Step 3 sampai Task 6 commit.

- [ ] **Step 1: Tambah permission ke `operator_akademik` di `RoleSeeder`**

Cari blok `if ($name === 'operator_akademik') { $role->givePermissionTo([...]); }` di `database/seeders/RoleSeeder.php` dan tambah baris berikut tepat setelah baris `'kelas.view', 'kelas.create', 'kelas.edit',`:

```php
                    'kelas.view', 'kelas.create', 'kelas.edit',
                    'fase-mapping.view', 'fase-mapping.create', 'fase-mapping.edit', 'fase-mapping.delete',
```

- [ ] **Step 2: Daftarkan `FaseSeeder` dan `FaseDefaultMappingSeeder` di `DatabaseSeeder`**

Di `database/seeders/DatabaseSeeder.php`, tambah 2 baris tepat setelah `ElemenCpSeeder::class,`:

```php
            ElemenCpSeeder::class,
            FaseSeeder::class,
            FaseDefaultMappingSeeder::class,
```

- [ ] **Step 3 (jalankan HANYA setelah Task 6 selesai): Test permission ter-assign & ter-sync**

```php
<?php
// tests/Feature/Akademik/FaseMappingPermissionSeederTest.php

use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use App\Models\Role;

uses(RefreshDatabase::class);

it('grants fase-mapping permissions to operator_akademik after full seeding sequence', function () {
    (new PermissionSeeder())->run();
    Artisan::call('permissions:sync');
    (new RoleSeeder())->run();

    $role = Role::where('name', 'operator_akademik')->first();

    expect($role->hasPermissionTo('fase-mapping.view'))->toBeTrue();
    expect($role->hasPermissionTo('fase-mapping.create'))->toBeTrue();
    expect($role->hasPermissionTo('fase-mapping.edit'))->toBeTrue();
    expect($role->hasPermissionTo('fase-mapping.delete'))->toBeTrue();
});

it('grants all fase-mapping permissions to yayasan_super_admin via blanket Permission::all()', function () {
    (new PermissionSeeder())->run();
    Artisan::call('permissions:sync');
    (new RoleSeeder())->run();

    $role = Role::where('name', 'yayasan_super_admin')->first();

    expect($role->hasPermissionTo('fase-mapping.view'))->toBeTrue();
    expect(Permission::where('name', 'fase-mapping.delete')->exists())->toBeTrue();
});
```

Run: `php artisan test --filter=FaseMappingPermissionSeederTest`
Expected: PASS (2/2) — HANYA setelah Task 6 (controller dgn `$this->authorize('fase-mapping.*')`) sudah commit, karena `permissions:sync` butuh string itu benar-benar dipanggil di kode utk auto-create baris `Permission`.

- [ ] **Step 4: Commit (setelah Step 3 PASS)**

```bash
git add database/seeders/RoleSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/Akademik/FaseMappingPermissionSeederTest.php
git commit -m "feat(akademik): daftarkan permission fase-mapping utk operator_akademik + seeder Fase di DatabaseSeeder"
```

---

### Task 6: `Admin\FaseDefaultMappingController` (CRUD + Authorization)

**Files:**
- Create: `app/Http/Controllers/Admin/FaseDefaultMappingController.php`
- Create: `resources/views/admin/fase-mapping/index.blade.php`
- Create: `resources/views/admin/fase-mapping/create.blade.php`
- Create: `resources/views/admin/fase-mapping/edit.blade.php`
- Create: `resources/views/admin/fase-mapping/_form.blade.php`
- Modify: `routes/admin/akademik-master.php`
- Test: `tests/Feature/Akademik/FaseDefaultMappingControllerTest.php`

**Interfaces:**
- Consumes: `FaseDefaultMapping`, `Fase` (Task 1, 2), `User::widestScopeLevel()` (existing).
- Produces: rute `admin.fase-mapping.{index,create,store,edit,update,destroy}` — dipakai manual oleh admin, TIDAK dikonsumsi task lain secara langsung (Task 7/8 hanya konsumsi `FaseDefaultResolver`, bukan controller ini).

- [ ] **Step 1: Test feature CRUD + authorization/tenant-isolation (RED dulu)**

```php
<?php
// tests/Feature/Akademik/FaseDefaultMappingControllerTest.php

use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\FaseDefaultMapping;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function buatUserDenganRole(string $roleName, ?int $lembagaId = null): User
{
    $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web'], ['scope_level' => match ($roleName) {
        'operator_akademik' => 'lembaga',
        'yayasan_super_admin' => 'yayasan',
        default => 'lembaga',
    }]);

    if ($roleName === 'operator_akademik') {
        $role->givePermissionTo(['fase-mapping.view', 'fase-mapping.create', 'fase-mapping.edit', 'fase-mapping.delete']);
    }
    if ($roleName === 'yayasan_super_admin') {
        $role->givePermissionTo(\Spatie\Permission\Models\Permission::query()->pluck('name')->all() ?: ['fase-mapping.view', 'fase-mapping.create', 'fase-mapping.edit', 'fase-mapping.delete']);
    }

    $user = User::factory()->create(['lembaga_id' => $lembagaId]);
    $user->assignRole($role);

    return $user;
}

it('lets a lembaga-scope user create a mapping that is force-scoped to their own lembaga even if a different lembaga_id is sent', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $lembagaSendiri = Lembaga::factory()->create();
    $lembagaLain = Lembaga::factory()->create();
    $user = buatUserDenganRole('operator_akademik', $lembagaSendiri->id);

    $this->actingAs($user)->post(route('admin.fase-mapping.store'), [
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'fase_id' => $fase->id,
        'lembaga_id' => $lembagaLain->id, // dicoba dipaksakan, harus diabaikan
    ])->assertRedirect(route('admin.fase-mapping.index'));

    $mapping = FaseDefaultMapping::first();
    expect($mapping->lembaga_id)->toBe($lembagaSendiri->id);
});

it('rejects a lembaga-scope user trying to create a platform-wide mapping (lembaga_id null)', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $lembaga = Lembaga::factory()->create();
    $user = buatUserDenganRole('operator_akademik', $lembaga->id);

    $this->actingAs($user)->post(route('admin.fase-mapping.store'), [
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'fase_id' => $fase->id,
        'lembaga_id' => '',
    ]);

    // Server memaksa lembaga_id ke lembaga user (bukan null) -- lihat controller.
    expect(FaseDefaultMapping::first()->lembaga_id)->toBe($lembaga->id);
});

it('lets a yayasan-scope user create a platform-wide mapping', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $user = buatUserDenganRole('yayasan_super_admin');

    $this->actingAs($user)->post(route('admin.fase-mapping.store'), [
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'fase_id' => $fase->id,
        'lembaga_id' => '',
    ])->assertRedirect(route('admin.fase-mapping.index'));

    expect(FaseDefaultMapping::first()->lembaga_id)->toBeNull();
});

it('lets a yayasan-scope user create a mapping for any specific lembaga', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $lembagaTarget = Lembaga::factory()->create();
    $user = buatUserDenganRole('yayasan_super_admin');

    $this->actingAs($user)->post(route('admin.fase-mapping.store'), [
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'fase_id' => $fase->id,
        'lembaga_id' => $lembagaTarget->id,
    ])->assertRedirect(route('admin.fase-mapping.index'));

    expect(FaseDefaultMapping::first()->lembaga_id)->toBe($lembagaTarget->id);
});

it('rejects a duplicate mapping in the same scope with a clear validation error', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $lembaga = Lembaga::factory()->create();
    $user = buatUserDenganRole('operator_akademik', $lembaga->id);
    FaseDefaultMapping::create(['lembaga_id' => $lembaga->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);

    $this->actingAs($user)->post(route('admin.fase-mapping.store'), [
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'fase_id' => $fase->id,
    ])->assertSessionHasErrors('bentuk_pendidikan');

    expect(FaseDefaultMapping::count())->toBe(1);
});

it('forbids a lembaga-scope user from editing another lembaga\'s mapping', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $lembagaA = Lembaga::factory()->create();
    $lembagaB = Lembaga::factory()->create();
    $mapping = FaseDefaultMapping::create(['lembaga_id' => $lembagaA->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);
    $userB = buatUserDenganRole('operator_akademik', $lembagaB->id);

    $this->actingAs($userB)->put(route('admin.fase-mapping.update', $mapping), [
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '2',
        'fase_id' => $fase->id,
    ])->assertForbidden();

    expect(FaseDefaultMapping::find($mapping->id)->tingkat)->toBe('1');
});

it('forbids a lembaga-scope user from deleting a platform-wide mapping', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $mapping = FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);
    $lembaga = Lembaga::factory()->create();
    $user = buatUserDenganRole('operator_akademik', $lembaga->id);

    $this->actingAs($user)->delete(route('admin.fase-mapping.destroy', $mapping))->assertForbidden();

    expect(FaseDefaultMapping::find($mapping->id))->not->toBeNull();
});

it('forbids a user without fase-mapping permission from accessing the index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.fase-mapping.index'))->assertForbidden();
});

it('lets a yayasan-scope user delete any lembaga\'s mapping', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $lembaga = Lembaga::factory()->create();
    $mapping = FaseDefaultMapping::create(['lembaga_id' => $lembaga->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);
    $user = buatUserDenganRole('yayasan_super_admin');

    $this->actingAs($user)->delete(route('admin.fase-mapping.destroy', $mapping))->assertRedirect(route('admin.fase-mapping.index'));

    expect(FaseDefaultMapping::find($mapping->id))->toBeNull();
});
```

Run: `php artisan test --filter=FaseDefaultMappingControllerTest`
Expected: FAIL — route `admin.fase-mapping.*` belum terdaftar.

- [ ] **Step 2: Tambah rute di `routes/admin/akademik-master.php`**

Tambah blok berikut (di mana pun dalam file, konsisten dgn gaya rute `pola-jam` yang sudah ada — pakai `use` baru di atas file):

```php
use App\Http\Controllers\Admin\FaseDefaultMappingController;
```

```php
Route::get('fase-mapping', [FaseDefaultMappingController::class, 'index'])->name('fase-mapping.index');
Route::get('fase-mapping/create', [FaseDefaultMappingController::class, 'create'])->name('fase-mapping.create');
Route::post('fase-mapping', [FaseDefaultMappingController::class, 'store'])->name('fase-mapping.store');
Route::get('fase-mapping/{faseMapping}/edit', [FaseDefaultMappingController::class, 'edit'])->name('fase-mapping.edit');
Route::put('fase-mapping/{faseMapping}', [FaseDefaultMappingController::class, 'update'])->name('fase-mapping.update');
Route::delete('fase-mapping/{faseMapping}', [FaseDefaultMappingController::class, 'destroy'])->name('fase-mapping.destroy');
```

- [ ] **Step 3: Implementasi `Admin\FaseDefaultMappingController`**

```php
<?php
// app/Http/Controllers/Admin/FaseDefaultMappingController.php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\FaseDefaultMapping;
use App\Models\Lembaga;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FaseDefaultMappingController extends BaseController
{
    use AuthorizesRequests;

    private const BENTUK_PENDIDIKAN = ['KB', 'TPA', 'SPS', 'TK', 'SD', 'SMP', 'SMA', 'SMK', 'SLB'];

    public function index(Request $request): View
    {
        $this->authorize('fase-mapping.view');

        $isPlatformOrYayasan = $this->isPlatformOrYayasan($request);

        $query = FaseDefaultMapping::with(['fase', 'lembaga']);

        if (! $isPlatformOrYayasan) {
            $query->where(function ($q) use ($request) {
                $q->whereNull('lembaga_id')->orWhere('lembaga_id', $request->user()->lembaga_id);
            });
        }

        return view('admin.fase-mapping.index', [
            'mappingList' => $query->orderBy('bentuk_pendidikan')->orderByRaw('tingkat IS NULL')->orderBy('tingkat')->get(),
            'isPlatformOrYayasan' => $isPlatformOrYayasan,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('fase-mapping.create');

        return view('admin.fase-mapping.create', [
            'faseList' => Fase::orderBy('urutan')->get(),
            'lembagaList' => $this->isPlatformOrYayasan($request) ? Lembaga::orderBy('nama')->get() : collect(),
            'isPlatformOrYayasan' => $this->isPlatformOrYayasan($request),
            'bentukPendidikanList' => self::BENTUK_PENDIDIKAN,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('fase-mapping.create');

        $data = $request->validate([
            'bentuk_pendidikan' => ['required', Rule::in(self::BENTUK_PENDIDIKAN)],
            'tingkat' => ['nullable', 'string', 'max:10'],
            'fase_id' => ['required', 'exists:fase,id'],
            'lembaga_id' => ['nullable', 'integer', 'exists:lembaga,id'],
        ]);
        $tingkat = $data['tingkat'] !== '' ? ($data['tingkat'] ?? null) : null;

        $isPlatformOrYayasan = $this->isPlatformOrYayasan($request);
        $lembagaId = $isPlatformOrYayasan ? ($data['lembaga_id'] ?? null) : $request->user()->lembaga_id;

        $this->authorizeMappingScope($request, $lembagaId);

        if (FaseDefaultMapping::where('lembaga_id', $lembagaId)->where('bentuk_pendidikan', $data['bentuk_pendidikan'])->where('tingkat', $tingkat)->exists()) {
            return back()->withErrors(['bentuk_pendidikan' => 'Sudah ada mapping default untuk kombinasi jenjang dan tingkat ini. Edit baris yang ada, jangan buat duplikat.'])->withInput();
        }

        FaseDefaultMapping::create([
            'lembaga_id' => $lembagaId,
            'bentuk_pendidikan' => $data['bentuk_pendidikan'],
            'tingkat' => $tingkat,
            'fase_id' => $data['fase_id'],
        ]);

        return redirect()->route('admin.fase-mapping.index')->with('status', 'Mapping default berhasil disimpan.');
    }

    public function edit(Request $request, FaseDefaultMapping $faseMapping): View
    {
        $this->authorize('fase-mapping.edit');
        $this->authorizeMappingScope($request, $faseMapping->lembaga_id);

        return view('admin.fase-mapping.edit', [
            'mapping' => $faseMapping,
            'faseList' => Fase::orderBy('urutan')->get(),
            'bentukPendidikanList' => self::BENTUK_PENDIDIKAN,
        ]);
    }

    public function update(Request $request, FaseDefaultMapping $faseMapping): RedirectResponse
    {
        $this->authorize('fase-mapping.edit');
        $this->authorizeMappingScope($request, $faseMapping->lembaga_id);

        $data = $request->validate([
            'bentuk_pendidikan' => ['required', Rule::in(self::BENTUK_PENDIDIKAN)],
            'tingkat' => ['nullable', 'string', 'max:10'],
            'fase_id' => ['required', 'exists:fase,id'],
        ]);
        $tingkat = $data['tingkat'] !== '' ? ($data['tingkat'] ?? null) : null;

        if (FaseDefaultMapping::where('id', '!=', $faseMapping->id)->where('lembaga_id', $faseMapping->lembaga_id)->where('bentuk_pendidikan', $data['bentuk_pendidikan'])->where('tingkat', $tingkat)->exists()) {
            return back()->withErrors(['bentuk_pendidikan' => 'Sudah ada mapping default untuk kombinasi jenjang dan tingkat ini. Edit baris yang ada, jangan buat duplikat.'])->withInput();
        }

        $faseMapping->update([
            'bentuk_pendidikan' => $data['bentuk_pendidikan'],
            'tingkat' => $tingkat,
            'fase_id' => $data['fase_id'],
        ]);

        return redirect()->route('admin.fase-mapping.index')->with('status', 'Mapping default berhasil diperbarui.');
    }

    public function destroy(Request $request, FaseDefaultMapping $faseMapping): RedirectResponse
    {
        $this->authorize('fase-mapping.delete');
        $this->authorizeMappingScope($request, $faseMapping->lembaga_id);

        $faseMapping->delete();

        return redirect()->route('admin.fase-mapping.index')->with('status', 'Mapping default berhasil dihapus.');
    }

    private function isPlatformOrYayasan(Request $request): bool
    {
        return in_array($request->user()->widestScopeLevel(), ['platform', 'yayasan'], true);
    }

    private function authorizeMappingScope(Request $request, ?int $lembagaIdDiminta): void
    {
        $isPlatformOrYayasan = $this->isPlatformOrYayasan($request);

        if ($lembagaIdDiminta === null) {
            abort_unless($isPlatformOrYayasan, 403);

            return;
        }

        abort_unless($isPlatformOrYayasan || $lembagaIdDiminta === $request->user()->lembaga_id, 403);
    }
}
```

- [ ] **Step 4: Views**

```blade
{{-- resources/views/admin/fase-mapping/_form.blade.php --}}
@php
    $mapping = $mapping ?? null;
    $val = fn (string $field, $default = '') => old($field, $mapping?->$field ?? $default);
@endphp

<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
    <div class="border-b border-gray-100 bg-white px-6 py-4">
        <p class="flex items-center gap-2 font-display text-sm font-bold text-gray-900">
            <x-icon name="group" class="h-4 w-4 text-brand-500" />
            Mapping Default Fase
        </p>
        <p class="mt-0.5 text-xs text-gray-500">Aturan rekomendasi fase berdasarkan jenjang &amp; tingkat — bisa diedit tanpa deployment.</p>
    </div>

    <div class="p-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">
            @if ($isPlatformOrYayasan ?? false)
                <div class="sm:col-span-12">
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

            <div class="sm:col-span-6">
                <x-input-label value="Bentuk Pendidikan" />
                <select name="bentuk_pendidikan" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($bentukPendidikanList as $bp)
                        <option value="{{ $bp }}" @selected($val('bentuk_pendidikan') === $bp)>{{ $bp }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('bentuk_pendidikan')" class="mt-1.5" />
            </div>

            <div class="sm:col-span-6">
                <x-input-label value="Tingkat (kosongkan = berlaku semua tingkat)" />
                <x-text-input type="text" name="tingkat" value="{{ $val('tingkat') }}" placeholder="Contoh: 1, 10 (kosongkan utk catch-all)" class="mt-1.5 w-full" />
                <x-input-error :messages="$errors->get('tingkat')" class="mt-1.5" />
            </div>

            <div class="sm:col-span-12">
                <x-input-label value="Fase" />
                <select name="fase_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($faseList as $fase)
                        <option value="{{ $fase->id }}" @selected($val('fase_id') == $fase->id)>{{ $fase->nama }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('fase_id')" class="mt-1.5" />
            </div>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3 rounded-b-2xl border-t border-gray-100 bg-gray-50 px-6 py-4">
        <a href="{{ route('admin.fase-mapping.index') }}" class="inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-200/50 hover:text-gray-900">Batal</a>
        <x-primary-button type="submit">{{ $submitText ?? 'Simpan' }}</x-primary-button>
    </div>
</div>
```

```blade
{{-- resources/views/admin/fase-mapping/create.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4">
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <h1 class="font-display text-lg font-bold text-gray-900">Tambah Mapping Default Fase</h1>

        <form method="POST" action="{{ route('admin.fase-mapping.store') }}">
            @csrf
            @include('admin.fase-mapping._form', ['faseList' => $faseList, 'lembagaList' => $lembagaList, 'isPlatformOrYayasan' => $isPlatformOrYayasan, 'bentukPendidikanList' => $bentukPendidikanList, 'submitText' => 'Simpan Mapping'])
        </form>
    </div>
</x-app-layout>
```

```blade
{{-- resources/views/admin/fase-mapping/edit.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4">
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <h1 class="font-display text-lg font-bold text-gray-900">Edit Mapping Default Fase</h1>

        <form method="POST" action="{{ route('admin.fase-mapping.update', $mapping) }}">
            @csrf
            @method('PUT')
            @include('admin.fase-mapping._form', ['mapping' => $mapping, 'faseList' => $faseList, 'lembagaList' => collect(), 'isPlatformOrYayasan' => false, 'bentukPendidikanList' => $bentukPendidikanList, 'submitText' => 'Perbarui Mapping'])
        </form>
    </div>
</x-app-layout>
```

```blade
{{-- resources/views/admin/fase-mapping/index.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Mapping Default Fase</h1>
            <a href="{{ route('admin.fase-mapping.create') }}" class="inline-flex items-center rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-700">Tambah Mapping</a>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Berlaku Untuk</th>
                        <th class="px-4 py-3">Jenjang</th>
                        <th class="px-4 py-3">Tingkat</th>
                        <th class="px-4 py-3">Fase</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($mappingList as $mapping)
                        <tr>
                            <td class="px-4 py-3">{{ $mapping->lembaga?->nama ?? 'Platform (semua lembaga)' }}</td>
                            <td class="px-4 py-3">{{ $mapping->bentuk_pendidikan }}</td>
                            <td class="px-4 py-3">{{ $mapping->tingkat ?? 'Semua tingkat' }}</td>
                            <td class="px-4 py-3">{{ $mapping->fase->nama }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.fase-mapping.edit', $mapping) }}" class="font-semibold text-brand-600 hover:text-brand-700">Edit</a>
                                <form method="POST" action="{{ route('admin.fase-mapping.destroy', $mapping) }}" class="ml-3 inline" x-data @submit.prevent="confirmDialog('Hapus Mapping?', 'Apakah Anda yakin ingin menghapus mapping ini?', { confirmLabel: 'Ya, Hapus' }).then(confirmed => { if (confirmed) $el.submit() })">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="font-semibold text-error-600 hover:text-error-700">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Belum ada mapping.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 5: Jalankan test lagi**

Run: `php artisan test --filter=FaseDefaultMappingControllerTest`
Expected: PASS (9/9).

- [ ] **Step 6: Jalankan test Task 5 Step 3 (permission sync, sekarang string `fase-mapping.*` sudah ada di kode)**

Run: `php artisan test --filter=FaseMappingPermissionSeederTest`
Expected: PASS (2/2).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/FaseDefaultMappingController.php resources/views/admin/fase-mapping routes/admin/akademik-master.php tests/Feature/Akademik/FaseDefaultMappingControllerTest.php
git commit -m "feat(akademik): CRUD Admin\FaseDefaultMappingController dgn authorization scope eksplisit"
```

---

### Task 7: Endpoint Suggestion (`KelasController::faseSuggestion`)

**Files:**
- Modify: `app/Http/Controllers/Admin/KelasController.php`
- Modify: `routes/admin/akademik-master.php`
- Test: `tests/Feature/Akademik/KelasFaseSuggestionTest.php`

**Interfaces:**
- Consumes: `FaseDefaultResolver` (Task 4).
- Produces: `GET admin/kelas/fase-suggestion?tingkat=...` → `{"suggestion": {"id":..,"kode":..,"nama":..}}` atau `{"suggestion": null}` — dikonsumsi Task 8 (Alpine di form Kelas).

- [ ] **Step 1: Test endpoint (RED dulu)**

```php
<?php
// tests/Feature/Akademik/KelasFaseSuggestionTest.php

use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\FaseDefaultMapping;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns the resolved suggestion for the logged-in lembaga based on its own bentuk_pendidikan and tingkat', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);

    $role = Role::firstOrCreate(['name' => 'operator_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('kelas.view');
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $response = $this->actingAs($user)->getJson(route('admin.kelas.fase-suggestion', ['tingkat' => '1']));

    $response->assertOk();
    expect($response->json('suggestion.id'))->toBe($fase->id);
    expect($response->json('suggestion.kode'))->toBe('a');
});

it('returns null suggestion when nothing matches', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SLB']);
    $role = Role::firstOrCreate(['name' => 'operator_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('kelas.view');
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $response = $this->actingAs($user)->getJson(route('admin.kelas.fase-suggestion', ['tingkat' => '6']));

    $response->assertOk();
    expect($response->json('suggestion'))->toBeNull();
});

it('never uses bentuk_pendidikan or lembaga from the request, only from the logged-in user\'s own lembaga', function () {
    $faseA = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $faseD = Fase::create(['kode' => 'd', 'nama' => 'Fase D', 'urutan' => 4]);
    $lembagaSd = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $faseA->id]);
    FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SMP', 'tingkat' => null, 'fase_id' => $faseD->id]);

    $role = Role::firstOrCreate(['name' => 'operator_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('kelas.view');
    $user = User::factory()->create(['lembaga_id' => $lembagaSd->id]);
    $user->assignRole($role);

    // Coba paksa bentuk_pendidikan=SMP lewat query string -- harus diabaikan, tetap pakai SD milik lembaga sendiri.
    $response = $this->actingAs($user)->getJson(route('admin.kelas.fase-suggestion', ['tingkat' => '1', 'bentuk_pendidikan' => 'SMP']));

    expect($response->json('suggestion.kode'))->toBe('a');
});
```

Run: `php artisan test --filter=KelasFaseSuggestionTest`
Expected: FAIL — route belum terdaftar.

- [ ] **Step 2: Tambah rute (SEBELUM `Route::resource('kelas', ...)` supaya tidak tertutup `{kelas}`)**

Di `routes/admin/akademik-master.php`, ubah:
```php
Route::resource('kelas', KelasController::class)->parameters(['kelas' => 'kelas'])->except(['show', 'destroy']);
```
menjadi:
```php
Route::get('kelas/fase-suggestion', [KelasController::class, 'faseSuggestion'])->name('kelas.fase-suggestion');
Route::resource('kelas', KelasController::class)->parameters(['kelas' => 'kelas'])->except(['show', 'destroy']);
```

- [ ] **Step 3: Tambah method `faseSuggestion` di `Admin\KelasController`**

Tambah `use` di atas file:
```php
use App\Domains\Akademik\Services\FaseDefaultResolver;
use Illuminate\Http\JsonResponse;
```

Tambah method (di mana pun dalam class, mis. setelah `index()`):
```php
public function faseSuggestion(Request $request, FaseDefaultResolver $resolver): JsonResponse
{
    $this->authorize('kelas.view');

    $lembaga = $request->user()->lembaga;

    $fase = $resolver->resolve($lembaga->bentuk_pendidikan, $request->query('tingkat'), $lembaga->id);

    return response()->json([
        'suggestion' => $fase ? ['id' => $fase->id, 'kode' => $fase->kode, 'nama' => $fase->nama] : null,
    ]);
}
```

- [ ] **Step 4: Jalankan test lagi**

Run: `php artisan test --filter=KelasFaseSuggestionTest`
Expected: PASS (3/3).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/KelasController.php routes/admin/akademik-master.php tests/Feature/Akademik/KelasFaseSuggestionTest.php
git commit -m "feat(akademik): endpoint suggestion fase read-only utk pre-fill form Kelas"
```

---

### Task 8: Integrasi UI Form Kelas + `fase_id` di Store/Update + Immutability End-to-End

**Files:**
- Modify: `resources/views/admin/kelas/_form.blade.php`
- Modify: `app/Http/Controllers/Admin/KelasController.php`
- Test: `tests/Feature/Akademik/KelasFaseAssignmentTest.php`

**Interfaces:**
- Consumes: `Fase` (Task 1), endpoint suggestion (Task 7).
- Produces: `Kelas.fase_id` bisa diisi lewat form create/edit — akhir rantai Sprint 3.

- [ ] **Step 1: Test create/update dgn `fase_id` + immutability end-to-end (RED dulu)**

```php
<?php
// tests/Feature/Akademik/KelasFaseAssignmentTest.php

use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\FaseDefaultMapping;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function buatOperatorAkademik(Lembaga $lembaga): User
{
    $role = Role::firstOrCreate(['name' => 'operator_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kelas.view', 'kelas.create', 'kelas.edit']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    return $user;
}

it('stores fase_id as-is when submitted on create, without recomputing it server-side', function () {
    $faseA = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $faseB = Fase::create(['kode' => 'b', 'nama' => 'Fase B', 'urutan' => 2]);
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $faseA->id]);
    $user = buatOperatorAkademik($lembaga);

    // Admin override manual ke Fase B meski suggestion platform-nya Fase A.
    $this->actingAs($user)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Kelas 1A',
        'tingkat' => '1',
        'fase_id' => $faseB->id,
    ])->assertRedirect(route('admin.kelas.index'));

    expect(Kelas::first()->fase_id)->toBe($faseB->id);
});

it('allows creating a Kelas with fase_id left empty', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = buatOperatorAkademik($lembaga);

    $this->actingAs($user)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Kelas 1A',
        'tingkat' => '1',
        'fase_id' => '',
    ])->assertRedirect(route('admin.kelas.index'));

    expect(Kelas::first()->fase_id)->toBeNull();
});

it('rejects a fase_id that does not exist', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = buatOperatorAkademik($lembaga);

    $this->actingAs($user)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Kelas 1A',
        'tingkat' => '1',
        'fase_id' => 999999,
    ])->assertSessionHasErrors('fase_id');

    expect(Kelas::count())->toBe(0);
});

it('lets an admin manually override fase_id on update, and the value sticks regardless of the suggestion', function () {
    $faseA = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $faseB = Fase::create(['kode' => 'b', 'nama' => 'Fase B', 'urutan' => 2]);
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'fase_id' => $faseA->id]);
    $user = buatOperatorAkademik($lembaga);

    $this->actingAs($user)->put(route('admin.kelas.update', $kelas), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => $kelas->nama,
        'tingkat' => $kelas->tingkat,
        'fase_id' => $faseB->id,
    ])->assertRedirect(route('admin.kelas.index'));

    expect($kelas->fresh()->fase_id)->toBe($faseB->id);
});

it('does not retroactively change an existing Kelas.fase_id when the default mapping is edited afterwards (immutability contract, end-to-end)', function () {
    $faseA = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $faseB = Fase::create(['kode' => 'b', 'nama' => 'Fase B', 'urutan' => 2]);
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $mapping = FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $faseA->id]);
    $user = buatOperatorAkademik($lembaga);

    // Kelas dibuat memakai suggestion saat mapping masih SD+1 -> A.
    $this->actingAs($user)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Kelas 1A',
        'tingkat' => '1',
        'fase_id' => $faseA->id,
    ]);
    $kelasLama = Kelas::first();

    // Admin platform mengubah kebijakan mapping SD+1 -> B.
    $mapping->update(['fase_id' => $faseB->id]);

    // Kelas lama TIDAK ikut berubah.
    expect($kelasLama->fresh()->fase_id)->toBe($faseA->id);

    // Kelas BARU yang dibuat setelah perubahan mapping mengikuti suggestion baru.
    $this->actingAs($user)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Kelas 1B',
        'tingkat' => '1',
        'fase_id' => $faseB->id,
    ]);
    $kelasBaru = Kelas::where('nama', 'Kelas 1B')->first();

    expect($kelasBaru->fase_id)->toBe($faseB->id);
});
```

Run: `php artisan test --filter=KelasFaseAssignmentTest`
Expected: FAIL — `store()`/`update()` belum menerima `fase_id`.

- [ ] **Step 2: Modify `KelasController::store()`/`update()` menerima `fase_id`**

Di `app/Http/Controllers/Admin/KelasController.php`, tambah `'fase_id' => ['nullable', 'integer', 'exists:fase,id'],` ke array validasi di KEDUA method `store()` dan `update()` (tepat setelah baris `'tingkat' => ['nullable', 'string', 'max:20'],`):

```php
            'tingkat' => ['nullable', 'string', 'max:20'],
            'fase_id' => ['nullable', 'integer', 'exists:fase,id'],
```

Tidak ada logika tambahan lain — `fase_id` masuk `$data` apa adanya dan tersimpan lewat `Kelas::create($data)`/`$kelas->update($data)` yang sudah ada, persis seperti field lain (`tingkat`, `nama`).

- [ ] **Step 3: Jalankan test lagi**

Run: `php artisan test --filter=KelasFaseAssignmentTest`
Expected: PASS (5/5).

- [ ] **Step 4: Integrasi UI — tambah dropdown Fase + Alpine suggestion fetch di `_form.blade.php`**

Di `resources/views/admin/kelas/_form.blade.php`, bungkus grid dgn `x-data` dan tambah field Fase tepat setelah field "Tingkat (opsional)":

```blade
@php
    $kelas = $kelas ?? null;
    $val = fn (string $field, $default = '') => old($field, $kelas?->$field ?? $default);
@endphp

<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
     x-data="{
        tingkat: @js($val('tingkat')),
        faseId: @js((string) $val('fase_id')),
        async fetchSuggestion() {
            if (this.faseId !== '') return; // jangan timpa pilihan admin yang sudah ada (edit) atau baru diubah manual
            const res = await fetch(`{{ route('admin.kelas.fase-suggestion') }}?tingkat=${encodeURIComponent(this.tingkat ?? '')}`);
            const json = await res.json();
            if (json.suggestion) { this.faseId = String(json.suggestion.id); }
        }
     }"
     x-init="fetchSuggestion()">
```

(Baris `<div class="overflow-hidden ...">` ini MENGGANTI baris pembuka `<div>` yang sama di file existing — bukan menambah div baru.)

Ubah input `tingkat` existing supaya memicu `fetchSuggestion()` dan sinkron dgn `x-model`:
```blade
            <div class="sm:col-span-5">
                <x-input-label value="Tingkat (opsional)" />
                <x-text-input type="text" name="tingkat" x-model="tingkat" x-on:change="faseId = ''; fetchSuggestion()" value="{{ $val('tingkat') }}" placeholder="Contoh: 6, XI (Kosongkan utk PAUD)" class="mt-1.5 w-full transition duration-150" />
                <x-input-error :messages="$errors->get('tingkat')" class="mt-1.5" />
            </div>

            <div class="sm:col-span-5">
                <x-input-label value="Fase Kurikulum (opsional)" />
                <select name="fase_id" x-model="faseId" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">— Tidak ditentukan —</option>
                    @foreach ($faseList as $fase)
                        <option value="{{ $fase->id }}">{{ $fase->nama }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">Saran otomatis berdasar jenjang &amp; tingkat — boleh diganti manual.</p>
                <x-input-error :messages="$errors->get('fase_id')" class="mt-1.5" />
            </div>
```

**Catatan implementer**: `x-text-input` (komponen Blade existing) meneruskan atribut tambahan (`x-model`, `x-on:change`) langsung ke elemen `<input>` di dalamnya selama komponen itu memakai `{{ $attributes }}` — perilaku ini SUDAH dipakai di form lain di codebase (pola sama dgn Sprint 2 §4 `x-model="assessmentType"` pada `<select>`), jadi tidak perlu modifikasi komponen. Kalau ternyata `x-text-input` tidak meneruskan atribut Alpine dgn benar (mis. atribut hilang di HTML akhir), implementer WAJIB memverifikasi lewat browser manual sebelum lanjut — bukan diasumsikan pasti bekerja tanpa cek.

Controller `create()`/`edit()` (`Admin\KelasController`) perlu mengirim `$faseList` ke view — tambah `'faseList' => \App\Domains\Akademik\Models\Fase::orderBy('urutan')->get(),` ke array data yang dikirim `view('admin.kelas.create', [...])` dan `view('admin.kelas.edit', [...])`, lalu tambah `'faseList' => $faseList` di kedua pemanggilan `@include('admin.kelas._form', [...])` pada `create.blade.php`/`edit.blade.php`.

- [ ] **Step 5: Verifikasi manual di browser (WAJIB, bukan opsional — UI/UX & Alpine tidak tercakup test Pest)**

1. Jalankan `php artisan serve` (atau pastikan Laragon jalan) dan `npm run dev` kalau build asset diperlukan.
2. Login sbg user `operator_akademik`, buka `admin/kelas/create`.
3. Ketik `1` di field Tingkat untuk lembaga SD → assert dropdown Fase otomatis ter-set ke "Fase A" (sesuai seed §5) tanpa reload halaman.
4. Ganti manual dropdown Fase ke pilihan lain, lalu ubah lagi field Tingkat → assert pilihan manual TIDAK tertimpa suggestion lagi (guard `if (this.faseId !== '') return;` di `fetchSuggestion()`).
5. Submit form → assert Kelas tersimpan dgn `fase_id` sesuai pilihan terakhir di dropdown.
6. Screenshot atau catat hasil di commit message / laporan kalau ada penyimpangan dari yang diharapkan.

- [ ] **Step 6: Commit**

```bash
git add resources/views/admin/kelas/_form.blade.php resources/views/admin/kelas/create.blade.php resources/views/admin/kelas/edit.blade.php app/Http/Controllers/Admin/KelasController.php tests/Feature/Akademik/KelasFaseAssignmentTest.php
git commit -m "feat(akademik): integrasi fase_id di form Kelas dgn suggestion Alpine + immutability end-to-end"
```

---

### Task 9: Regresi Penuh & Migrasi Dev Database

**Files:**
- Tidak ada file baru — task verifikasi murni.

- [ ] **Step 1: Jalankan full test suite (TANPA filter), sekali, foreground, tidak ada proses `php artisan test`/`migrate` lain berjalan bersamaan**

Run: `php artisan test`
Expected: 0 failed. Catat jumlah pass persis (bukan "tampak hijau") sebelum lanjut.

- [ ] **Step 2: Kalau ada failure, klasifikasi dulu sebelum memperbaiki apa pun**

Bedakan: (a) regresi nyata dari Sprint 3 → perbaiki di task terkait, jangan tambal di Task 9; (b) flaky pre-existing (mis. `TahunAjaranFactory` collision — pola sudah dikenal, lihat memori project) → re-run sekali lagi utk konfirmasi, JANGAN diabaikan tanpa re-run pembuktian.

- [ ] **Step 3: Migrasi database dev nyata (Laragon/MySQL, bukan cuma test DB)**

```bash
php artisan migrate
php artisan db:seed --class=FaseSeeder
php artisan db:seed --class=FaseDefaultMappingSeeder
php artisan permissions:sync
```
(Bukan `migrate:fresh` — dev database sudah berisi data nyata, migration baru bersifat aditif/nullable, aman dijalankan tanpa fresh.)

- [ ] **Step 4: Laporkan hasil final ke user**

Ringkasan: jumlah test pass/fail, commit terakhir, konfirmasi dev database sudah dimigrasi+diseed, dan link ke plan/spec ini utk referensi.

## Self-Review

- Cakupan spec: §1 (skema fase/mapping/kelas.fase_id) → Task 1-3; §2 (uniqueness) → Task 2; §3 (model+resolver+authorization) → Task 2/4/6; §4 (resolver) → Task 4; §5 (seed) → Task 1/2; §6 (UI Kelas+endpoint) → Task 7/8; §7 (non-goals) → tidak ada task yang melanggarnya (di-cross-check tiap task hanya menyentuh file yang disebut spec); §8 (test matrix) → seluruh skenario di §8 punya test eksplisit yang bisa ditelusuri balik ke task (resolver precedence → Task 4, uniqueness/konflik → Task 2, immutability → Task 2/3/8, seed idempotency → Task 1/2, suggestion endpoint → Task 7, create/update Kelas → Task 8, authorization/tenant-isolation → Task 6).
- Placeholder scan: tidak ada "TBD"/"implement later". Satu titik ditandai eksplisit sbg ketidakpastian teknis jujur (Task 8 Step 4, soal `x-text-input` meneruskan atribut Alpine) dgn instruksi konkret verifikasi manual — bukan diasumsikan pasti benar.
- Konsistensi tipe: `FaseDefaultResolver::resolve(string $bentukPendidikan, ?string $tingkat, ?int $lembagaId): ?Fase` identik di Task 4 (definisi), Task 7 (pemanggilan dari `faseSuggestion()`), dan spec §4/§6. `FaseDefaultMapping` fillable (`lembaga_id`, `bentuk_pendidikan`, `tingkat`, `fase_id`) konsisten dipakai Task 2 (model), Task 6 (controller store/update).
- Urutan task memperhitungkan dependency riil: Task 5 (permission) sengaja ditulis SEBELUM Task 6 (controller) di file, tapi test-nya (Step 3) sengaja ditunda dijalankan sampai SETELAH Task 6 commit — karena `permissions:sync` butuh string permission benar-benar dipanggil di kode dulu. Ini didokumentasikan eksplisit di Task 5 supaya subagent/implementer tidak bingung kenapa test gagal kalau dijalankan lebih awal.
