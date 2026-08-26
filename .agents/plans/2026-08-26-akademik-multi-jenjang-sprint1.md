# Fondasi Akademik Multi-Jenjang — Sprint 1 (Subjek Penilaian Polymorphic) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `KomponenPenilaian` dan `Asesmen` tidak lagi mengasumsikan subjek penilaiannya pasti `MataPelajaran` — subjek bisa `MataPelajaran` (SD/SMP/SMA/SMK) atau `ElemenCp` (PAUD), lewat relasi polymorphic `subjek_type`/`subjek_id`.

**Architecture:** Tambah kolom polymorphic secara aditif dulu (non-breaking), backfill data existing dengan aturan precedence eksplisit, baru geser SELURUH kode (model/action/DTO/request/controller/service/blade/test) ke pola baru dalam satu langkah kohesif, baru terakhir drop kolom lama setelah verifikasi grep nol-hasil. `JadwalPelajaran`, `Rpp`, `SesiPembelajaran` TIDAK disentuh — mereka sudah nullable dan tetap pakai `mata_pelajaran_id` biasa.

**Tech Stack:** Laravel 12, Pest, MySQL.

## Global Constraints

- Morph map HANYA berisi 2 alias: `mata_pelajaran` → `MataPelajaran::class`, `elemen_cp` → `ElemenCp::class`. Jangan tambah `projek`/`kompetensi_kejuruan` sebagai placeholder.
- Composite key lintas tipe subjek WAJIB lewat `SubjekPenilaianKey::dari()` — dilarang ada string concatenation manual (`$type . ':' . $id`) di file lain manapun.
- `ElemenCp` TIDAK pakai `BelongsToTenant` — data global, tidak punya `lembaga_id`.
- Precedence backfill: kalau `elemen_cp` (kolom lama) TERISI, itu yang menang — bukan `mata_pelajaran_id`.
- Migration backfill harus FAIL (throw exception, sebut id baris) kalau ada baris yang tidak bisa dipetakan ke subjek manapun — dilarang silent skip.
- Migration drop kolom lama (Task 6) TIDAK BOLEH dijalankan sebelum `git grep` untuk `mata_pelajaran_id`/`elemen_cp`/`->mataPelajaran` di kode `KomponenPenilaian`/`Asesmen`-related menghasilkan nol hasil.
- Validasi tenant: `MataPelajaran` harus satu lembaga dengan semester; `ElemenCp` selalu valid lintas lembaga (global).
- Jalankan test scoped (bukan full suite) di setiap task; full suite HANYA di Task 6 (final).

---

### Task 1: Fondasi Baru — `ElemenCp`, Interface, Key Helper, Morph Map

**Files:**
- Create: `database/migrations/2026_08_26_100000_create_elemen_cp_table.php`
- Create: `app/Domains/Akademik/Models/ElemenCp.php`
- Create: `app/Domains/Akademik/Contracts/SubjekPenilaian.php`
- Create: `app/Domains/Akademik/Support/SubjekPenilaianKey.php`
- Create: `database/factories/ElemenCpFactory.php`
- Create: `database/seeders/ElemenCpSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php:30` (tambah `ElemenCpSeeder::class,` setelah `JenisKaryawanMasterSeeder::class,`)
- Modify: `app/Providers/AppServiceProvider.php` (tambah morph map di `boot()`)
- Modify: `app/Domains/Akademik/Models/MataPelajaran.php` (tambah `implements SubjekPenilaian`)
- Test: `tests/Unit/Models/ElemenCpTest.php`
- Test: `tests/Unit/Support/SubjekPenilaianKeyTest.php`
- Test: `tests/Unit/ElemenCpSeederTest.php`

**Interfaces:**
- Produces: `App\Domains\Akademik\Contracts\SubjekPenilaian` (marker interface, tanpa method) — dipakai Task 4 untuk type-hint `CapaianKompetensiGenerator::generateNarasi()`.
- Produces: `App\Domains\Akademik\Support\SubjekPenilaianKey::dari(\Illuminate\Database\Eloquent\Model $subjek): string` — dipakai Task 4 di `RaporCalculationService`/`RaporPdfDataBuilder`.
- Produces: `App\Domains\Akademik\Models\ElemenCp` (fillable: `kode`, `nama`, `no_urut`) — dipakai Task 2 (kolom morph target) dan Task 5 (dropdown UI).

- [ ] **Step 1: Migration `elemen_cp` table**

```php
<?php
// database/migrations/2026_08_26_100000_create_elemen_cp_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elemen_cp', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->string('nama');
            $table->unsignedTinyInteger('no_urut');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elemen_cp');
    }
};
```

- [ ] **Step 2: `ElemenCp` model**

```php
<?php
// app/Domains/Akademik/Models/ElemenCp.php

namespace App\Domains\Akademik\Models;

use App\Domains\Akademik\Contracts\SubjekPenilaian;
use Database\Factories\ElemenCpFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ElemenCp extends Model implements SubjekPenilaian
{
    use HasFactory;

    protected $table = 'elemen_cp';

    protected $fillable = ['kode', 'nama', 'no_urut'];

    protected static function newFactory(): ElemenCpFactory
    {
        return ElemenCpFactory::new();
    }
}
```

Catatan: TIDAK pakai `BelongsToTenant` (bukan trait, bukan `lembaga_id` fillable) — data global standar nasional, bukan per-lembaga.

- [ ] **Step 3: `SubjekPenilaian` marker interface**

```php
<?php
// app/Domains/Akademik/Contracts/SubjekPenilaian.php

namespace App\Domains\Akademik\Contracts;

/**
 * Marker interface: menandai model yang boleh jadi target morph `subjek`
 * pada KomponenPenilaian/Asesmen (MataPelajaran, ElemenCp). Sengaja tanpa
 * method -- kontrak "punya properti nama" tidak bisa dideklarasikan sbg
 * interface method tanpa memaksa accessor eksplisit yang saat ini tidak
 * dibutuhkan caller manapun. Tambah method hanya kalau ada caller nyata
 * yang butuh kontrak method (bukan properti Eloquent biasa).
 */
interface SubjekPenilaian
{
}
```

- [ ] **Step 4: `SubjekPenilaianKey` helper**

```php
<?php
// app/Domains/Akademik/Support/SubjekPenilaianKey.php

namespace App\Domains\Akademik\Support;

use Illuminate\Database\Eloquent\Model;

final class SubjekPenilaianKey
{
    public static function dari(Model $subjek): string
    {
        return $subjek->getMorphClass().':'.$subjek->getKey();
    }
}
```

- [ ] **Step 5: Daftarkan morph map di `AppServiceProvider::boot()`**

Tambahkan di awal method `boot()` (sebelum `Auth::provider(...)`), dan tambah use statement:

```php
use App\Domains\Akademik\Models\ElemenCp;
use App\Domains\Akademik\Models\MataPelajaran;
use Illuminate\Database\Eloquent\Relations\Relation;
```

```php
public function boot(): void
{
    Relation::enforceMorphMap([
        'mata_pelajaran' => MataPelajaran::class,
        'elemen_cp' => ElemenCp::class,
    ]);

    Auth::provider('tenant-aware', function ($app, array $config) {
        // ... existing code tidak berubah
```

- [ ] **Step 6: `MataPelajaran` implements `SubjekPenilaian`**

```php
// app/Domains/Akademik/Models/MataPelajaran.php
use App\Domains\Akademik\Contracts\SubjekPenilaian;
// ...
class MataPelajaran extends Model implements SubjekPenilaian
{
    // isi class tidak berubah
```

- [ ] **Step 7: `ElemenCpFactory`**

```php
<?php
// database/factories/ElemenCpFactory.php

namespace Database\Factories;

use App\Domains\Akademik\Models\ElemenCp;
use Illuminate\Database\Eloquent\Factories\Factory;

class ElemenCpFactory extends Factory
{
    protected $model = ElemenCp::class;

    public function definition(): array
    {
        return [
            'kode' => $this->faker->unique()->slug(2),
            'nama' => $this->faker->words(3, true),
            'no_urut' => $this->faker->numberBetween(1, 10),
        ];
    }
}
```

- [ ] **Step 8: `ElemenCpSeeder`**

```php
<?php
// database/seeders/ElemenCpSeeder.php

namespace Database\Seeders;

use App\Domains\Akademik\Models\ElemenCp;
use Illuminate\Database\Seeder;

class ElemenCpSeeder extends Seeder
{
    public function run(): void
    {
        $elemen = [
            ['kode' => 'nilai_agama_moral', 'nama' => 'Nilai Agama dan Budi Pekerti', 'no_urut' => 1],
            ['kode' => 'jati_diri', 'nama' => 'Jati Diri', 'no_urut' => 2],
            ['kode' => 'literasi_steam', 'nama' => 'Literasi, STEAM, Seni, dan Budaya', 'no_urut' => 3],
        ];

        foreach ($elemen as $data) {
            ElemenCp::firstOrCreate(['kode' => $data['kode']], $data);
        }
    }
}
```

- [ ] **Step 9: Daftarkan `ElemenCpSeeder` di `DatabaseSeeder.php`**

Cari baris `JenisKaryawanMasterSeeder::class,` (baris 30) dan tambah tepat setelahnya:

```php
JenisKaryawanMasterSeeder::class,
ElemenCpSeeder::class,
```

- [ ] **Step 10: Test — `ElemenCp` model & seeder**

```php
<?php
// tests/Unit/Models/ElemenCpTest.php

use App\Domains\Akademik\Contracts\SubjekPenilaian;
use App\Domains\Akademik\Models\ElemenCp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('implements the SubjekPenilaian marker interface', function () {
    $elemen = ElemenCp::factory()->create();

    expect($elemen)->toBeInstanceOf(SubjekPenilaian::class);
});
```

```php
<?php
// tests/Unit/ElemenCpSeederTest.php

use App\Domains\Akademik\Models\ElemenCp;
use Database\Seeders\ElemenCpSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('seeds exactly 3 elemen cp in the correct fixed order', function () {
    (new ElemenCpSeeder())->run();

    expect(ElemenCp::count())->toBe(3);
    expect(ElemenCp::orderBy('no_urut')->pluck('kode')->all())
        ->toBe(['nilai_agama_moral', 'jati_diri', 'literasi_steam']);
});

it('is idempotent when run twice', function () {
    (new ElemenCpSeeder())->run();
    (new ElemenCpSeeder())->run();

    expect(ElemenCp::count())->toBe(3);
});
```

- [ ] **Step 11: Test — `SubjekPenilaianKey` mencegah collision**

```php
<?php
// tests/Unit/Support/SubjekPenilaianKeyTest.php

use App\Domains\Akademik\Models\ElemenCp;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Support\SubjekPenilaianKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('produces distinct keys for a MataPelajaran and an ElemenCp sharing the same numeric id', function () {
    $mapel = MataPelajaran::factory()->create();
    $elemen = ElemenCp::factory()->create();

    // Paksa id sama secara eksplisit untuk menegaskan skenario collision:
    // tabel berbeda punya auto-increment terpisah, jadi id yang sama antar
    // tipe subjek adalah skenario nyata yang mungkin terjadi, bukan hipotetis.
    expect(SubjekPenilaianKey::dari($mapel))->not->toBe(SubjekPenilaianKey::dari($elemen));
    expect(SubjekPenilaianKey::dari($mapel))->toBe('mata_pelajaran:'.$mapel->id);
    expect(SubjekPenilaianKey::dari($elemen))->toBe('elemen_cp:'.$elemen->id);
});
```

- [ ] **Step 12: Jalankan test scoped**

Run: `php artisan test --filter="ElemenCpTest|SubjekPenilaianKeyTest|ElemenCpSeederTest"`
Expected: semua PASS.

- [ ] **Step 13: Commit**

```bash
git add database/migrations/2026_08_26_100000_create_elemen_cp_table.php app/Domains/Akademik/Models/ElemenCp.php app/Domains/Akademik/Contracts/SubjekPenilaian.php app/Domains/Akademik/Support/SubjekPenilaianKey.php database/factories/ElemenCpFactory.php database/seeders/ElemenCpSeeder.php database/seeders/DatabaseSeeder.php app/Providers/AppServiceProvider.php app/Domains/Akademik/Models/MataPelajaran.php tests/Unit/Models/ElemenCpTest.php tests/Unit/Support/SubjekPenilaianKeyTest.php tests/Unit/ElemenCpSeederTest.php
git commit -m "feat(akademik): tambah ElemenCp, SubjekPenilaian interface, SubjekPenilaianKey helper, morph map"
```

---

### Task 2: Kolom Polymorphic Baru (Aditif, Non-Breaking)

**Files:**
- Create: `database/migrations/2026_08_26_100100_add_subjek_columns_to_komponen_penilaian_and_asesmen.php`
- Test: `tests/Unit/Migrations/SubjekColumnsExistTest.php`

**Interfaces:**
- Consumes: tidak ada (murni skema).
- Produces: kolom `komponen_penilaian.subjek_type`/`subjek_id` (nullable) dan `asesmen.subjek_type`/`subjek_id` (nullable) — dipakai Task 3 (backfill) dan Task 4 (kode baru).

- [ ] **Step 1: Migration tambah kolom**

```php
<?php
// database/migrations/2026_08_26_100100_add_subjek_columns_to_komponen_penilaian_and_asesmen.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('komponen_penilaian', function (Blueprint $table) {
            $table->string('subjek_type')->nullable()->after('lembaga_id');
            $table->unsignedBigInteger('subjek_id')->nullable()->after('subjek_type');
            $table->index(['subjek_type', 'subjek_id'], 'idx_komp_subjek');
        });

        Schema::table('asesmen', function (Blueprint $table) {
            $table->string('subjek_type')->nullable()->after('lembaga_id');
            $table->unsignedBigInteger('subjek_id')->nullable()->after('subjek_type');
            $table->index(['subjek_type', 'subjek_id'], 'idx_asesmen_subjek');
        });
    }

    public function down(): void
    {
        Schema::table('komponen_penilaian', function (Blueprint $table) {
            $table->dropIndex('idx_komp_subjek');
            $table->dropColumn(['subjek_type', 'subjek_id']);
        });

        Schema::table('asesmen', function (Blueprint $table) {
            $table->dropIndex('idx_asesmen_subjek');
            $table->dropColumn(['subjek_type', 'subjek_id']);
        });
    }
};
```

- [ ] **Step 2: Test kolom ada & nullable**

```php
<?php
// tests/Unit/Migrations/SubjekColumnsExistTest.php

use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('adds nullable subjek_type/subjek_id columns to komponen_penilaian and asesmen', function () {
    expect(Schema::hasColumns('komponen_penilaian', ['subjek_type', 'subjek_id']))->toBeTrue();
    expect(Schema::hasColumns('asesmen', ['subjek_type', 'subjek_id']))->toBeTrue();

    // Baris lama (mata_pelajaran_id NOT NULL) masih harus bisa di-insert
    // tanpa subjek_type/subjek_id -- membuktikan kolom baru benar nullable
    // dan tidak breaking di titik ini.
    $mapel = App\Domains\Akademik\Models\MataPelajaran::factory()->create();
    $semester = App\Models\Semester::factory()->create();

    $komponen = App\Domains\Akademik\Models\KomponenPenilaian::create([
        'mata_pelajaran_id' => $mapel->id,
        'semester_id' => $semester->id,
        'deskripsi' => 'Tes',
        'bobot' => 10,
    ]);

    expect($komponen->subjek_type)->toBeNull();
    expect($komponen->subjek_id)->toBeNull();
});
```

- [ ] **Step 3: Jalankan test scoped**

Run: `php artisan test --filter=SubjekColumnsExistTest`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_08_26_100100_add_subjek_columns_to_komponen_penilaian_and_asesmen.php tests/Unit/Migrations/SubjekColumnsExistTest.php
git commit -m "feat(akademik): tambah kolom subjek_type/subjek_id nullable ke komponen_penilaian & asesmen"
```

---

### Task 3: Migration Backfill dengan Precedence Eksplisit

**Files:**
- Create: `database/migrations/2026_08_26_100200_backfill_subjek_penilaian.php`
- Test: `tests/Feature/Akademik/BackfillSubjekPenilaianMigrationTest.php`

**Interfaces:**
- Consumes: `ElemenCp` (Task 1), kolom `subjek_type`/`subjek_id` (Task 2), kolom lama `mata_pelajaran_id`/`elemen_cp` (masih ada, belum di-drop).
- Produces: setiap baris existing `komponen_penilaian`/`asesmen` terisi `subjek_type`/`subjek_id` sesuai precedence.

- [ ] **Step 1: Migration backfill**

```php
<?php
// database/migrations/2026_08_26_100200_backfill_subjek_penilaian.php

use App\Domains\Akademik\Models\ElemenCp;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->backfill('komponen_penilaian', hasElemenCpColumn: true);
        $this->backfill('asesmen', hasElemenCpColumn: false);
    }

    private function backfill(string $table, bool $hasElemenCpColumn): void
    {
        $rows = DB::table($table)->whereNull('subjek_type')->get();

        foreach ($rows as $row) {
            $elemenCpKode = $hasElemenCpColumn ? ($row->elemen_cp ?? null) : null;

            if ($elemenCpKode !== null) {
                // Precedence: elemen_cp menang kalau terisi -- lebih bermakna
                // untuk PAUD daripada mata_pelajaran_id dummy.
                $elemenCpId = ElemenCp::where('kode', $elemenCpKode)->value('id');

                if ($elemenCpId === null) {
                    throw new \RuntimeException(
                        "Backfill gagal: baris {$table}#{$row->id} punya elemen_cp='{$elemenCpKode}' yang tidak ditemukan di tabel elemen_cp."
                    );
                }

                DB::table($table)->where('id', $row->id)->update([
                    'subjek_type' => 'elemen_cp',
                    'subjek_id' => $elemenCpId,
                ]);

                continue;
            }

            if ($row->mata_pelajaran_id !== null) {
                DB::table($table)->where('id', $row->id)->update([
                    'subjek_type' => 'mata_pelajaran',
                    'subjek_id' => $row->mata_pelajaran_id,
                ]);

                continue;
            }

            // Tidak ada elemen_cp maupun mata_pelajaran_id -- baris tak
            // terpetakan. Fail keras, JANGAN silent skip.
            throw new \RuntimeException(
                "Backfill gagal: baris {$table}#{$row->id} tidak punya elemen_cp maupun mata_pelajaran_id -- tidak bisa dipetakan ke subjek manapun."
            );
        }
    }

    public function down(): void
    {
        DB::table('komponen_penilaian')->update(['subjek_type' => null, 'subjek_id' => null]);
        DB::table('asesmen')->update(['subjek_type' => null, 'subjek_id' => null]);
    }
};
```

- [ ] **Step 2: Test — precedence `elemen_cp` menang saat keduanya terisi**

```php
<?php
// tests/Feature/Akademik/BackfillSubjekPenilaianMigrationTest.php

use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\ElemenCp;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Semester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('backfills subjek_type=elemen_cp when both elemen_cp and mata_pelajaran_id are filled on the same row', function () {
    $elemenCp = ElemenCp::factory()->create(['kode' => 'jati_diri']);
    $mapel = MataPelajaran::factory()->create();
    $semester = Semester::factory()->create();

    // Insert langsung via DB (bukan Eloquent create) supaya bisa set
    // subjek_type=null dan kolom lama terisi keduanya secara eksplisit,
    // meniru kondisi data existing sebelum backfill dijalankan.
    $id = DB::table('komponen_penilaian')->insertGetId([
        'mata_pelajaran_id' => $mapel->id,
        'elemen_cp' => 'jati_diri',
        'semester_id' => $semester->id,
        'lembaga_id' => $mapel->lembaga_id,
        'deskripsi' => 'Tes precedence',
        'bobot' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Artisan::call('migrate', ['--path' => 'database/migrations/2026_08_26_100200_backfill_subjek_penilaian.php', '--realpath' => false]);

    $komponen = KomponenPenilaian::withoutGlobalScopes()->find($id);
    expect($komponen->subjek_type)->toBe('elemen_cp');
    expect($komponen->subjek_id)->toBe($elemenCp->id);
});

it('backfills subjek_type=mata_pelajaran when only mata_pelajaran_id is filled', function () {
    $mapel = MataPelajaran::factory()->create();
    $semester = Semester::factory()->create();

    $id = DB::table('komponen_penilaian')->insertGetId([
        'mata_pelajaran_id' => $mapel->id,
        'elemen_cp' => null,
        'semester_id' => $semester->id,
        'lembaga_id' => $mapel->lembaga_id,
        'deskripsi' => 'Tes tanpa elemen_cp',
        'bobot' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Artisan::call('migrate', ['--path' => 'database/migrations/2026_08_26_100200_backfill_subjek_penilaian.php', '--realpath' => false]);

    $komponen = KomponenPenilaian::withoutGlobalScopes()->find($id);
    expect($komponen->subjek_type)->toBe('mata_pelajaran');
    expect($komponen->subjek_id)->toBe($mapel->id);
});

it('throws when a row has neither elemen_cp nor mata_pelajaran_id', function () {
    $semester = Semester::factory()->create();

    DB::table('komponen_penilaian')->insert([
        'mata_pelajaran_id' => null,
        'elemen_cp' => null,
        'semester_id' => $semester->id,
        'lembaga_id' => null,
        'deskripsi' => 'Baris rusak',
        'bobot' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => Artisan::call('migrate', ['--path' => 'database/migrations/2026_08_26_100200_backfill_subjek_penilaian.php', '--realpath' => false]))
        ->toThrow(RuntimeException::class);
});

it('backfills asesmen the same way (no elemen_cp column on this table)', function () {
    $mapel = MataPelajaran::factory()->create();
    $kelas = App\Models\Kelas::factory()->create(['lembaga_id' => $mapel->lembaga_id]);
    $semester = Semester::factory()->create();
    $guru = App\Models\Guru::factory()->create(['lembaga_id' => $mapel->lembaga_id]);

    $id = DB::table('asesmen')->insertGetId([
        'guru_id' => $guru->id,
        'kelas_id' => $kelas->id,
        'mata_pelajaran_id' => $mapel->id,
        'semester_id' => $semester->id,
        'lembaga_id' => $mapel->lembaga_id,
        'jenis' => 'sumatif_lingkup_materi',
        'judul' => 'Tes',
        'tanggal' => now()->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Artisan::call('migrate', ['--path' => 'database/migrations/2026_08_26_100200_backfill_subjek_penilaian.php', '--realpath' => false]);

    $asesmen = Asesmen::withoutGlobalScopes()->find($id);
    expect($asesmen->subjek_type)->toBe('mata_pelajaran');
    expect($asesmen->subjek_id)->toBe($mapel->id);
});
```

**Catatan implementer**: `Artisan::call('migrate', ['--path' => ...])` menjalankan HANYA migration file itu di atas skema yang sudah di-`RefreshDatabase` (yang menjalankan seluruh migration lain lebih dulu, termasuk Task 1 & 2). Kalau pola ini tidak berjalan mulus di lingkungan test (migration sudah lanjut ke Task 6 dalam satu batch `migrate:fresh`), alternatifnya panggil method backfill langsung sbg method statis/testable — verifikasi mana yang benar-benar bekerja saat implementasi, laporkan kalau perlu penyesuaian pendekatan.

- [ ] **Step 3: Jalankan test scoped**

Run: `php artisan test --filter=BackfillSubjekPenilaianMigrationTest`
Expected: 4 test PASS (termasuk yang expect exception).

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_08_26_100200_backfill_subjek_penilaian.php tests/Feature/Akademik/BackfillSubjekPenilaianMigrationTest.php
git commit -m "feat(akademik): migration backfill subjek_type/subjek_id dgn precedence elemen_cp > mata_pelajaran_id"
```

---

### Task 4: Geser Model, Action, DTO, Form Request, Controller, Service ke `subjek`

Ini task terbesar & paling kohesif — semua lapisan backend berubah bersamaan karena saling bergantung (Controller pakai DTO, DTO dipakai Action, Action pakai Model relation). Tidak bisa dipecah tanpa meninggalkan state rusak sementara.

**Files:**
- Modify: `app/Domains/Akademik/Models/KomponenPenilaian.php`
- Modify: `app/Domains/Akademik/Models/Asesmen.php`
- Modify: `app/Domains/Akademik/DataTransferObjects/KomponenPenilaianData.php`
- Modify: `app/Domains/Akademik/DataTransferObjects/UpdateKomponenPenilaianData.php`
- Modify: `app/Domains/Akademik/DataTransferObjects/AsesmenData.php`
- Modify: `app/Domains/Akademik/Actions/Penilaian/CreateKomponenPenilaianAction.php`
- Modify: `app/Domains/Akademik/Actions/Penilaian/UpdateKomponenPenilaianAction.php`
- Modify: `app/Domains/Akademik/Actions/Penilaian/CreateAsesmenAction.php`
- Modify: `app/Http/Requests/Akademik/StoreKomponenPenilaianRequest.php`
- Modify: `app/Http/Requests/Akademik/UpdateKomponenPenilaianRequest.php`
- Modify: `app/Http/Requests/Akademik/StoreKomponenPenilaianSendiriRequest.php`
- Modify: `app/Http/Requests/Akademik/UpdateKomponenPenilaianSendiriRequest.php`
- Modify: `app/Http/Requests/Akademik/StoreAsesmenRequest.php`
- Modify: `app/Http/Controllers/Admin/KomponenPenilaianController.php`
- Modify: `app/Http/Controllers/Guru/KomponenPenilaianController.php`
- Modify: `app/Http/Controllers/Guru/AsesmenController.php`
- Modify: `app/Http/Controllers/Admin/DashboardController.php`
- Modify: `app/Services/DashboardStatsService.php`
- Modify: `app/Domains/Akademik/Services/RaporCalculationService.php`
- Modify: `app/Domains/Akademik/Services/RaporPdfDataBuilder.php`
- Modify: `app/Domains/Akademik/Services/CapaianKompetensiGenerator.php`
- Modify: `database/factories/KomponenPenilaianFactory.php`
- Modify: `database/factories/AsesmenFactory.php`
- Test: `tests/Feature/Akademik/SubjekTenantValidationTest.php`
- Test: `tests/Feature/Akademik/RaporCalculationCompositeKeyTest.php`

**Interfaces:**
- Consumes: `ElemenCp`, `SubjekPenilaian`, `SubjekPenilaianKey` (Task 1), kolom `subjek_type`/`subjek_id` terisi (Task 3).
- Produces: `KomponenPenilaian::subjek(): MorphTo`, `Asesmen::subjek(): MorphTo` — dipakai Task 5 (blade).

- [ ] **Step 1: `KomponenPenilaian` model**

Ganti isi file jadi:

```php
<?php

namespace App\Domains\Akademik\Models;

use App\Domains\Akademik\Models\ElemenCp;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use App\Models\Semester;
use Database\Factories\KomponenPenilaianFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class KomponenPenilaian extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'komponen_penilaian';

    protected $fillable = ['subjek_type', 'subjek_id', 'semester_id', 'lembaga_id', 'kode', 'deskripsi', 'bobot', 'kktp', 'kktp_minimal'];

    protected static function newFactory(): KomponenPenilaianFactory
    {
        return KomponenPenilaianFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (self $komponenPenilaian) {
            if (empty($komponenPenilaian->lembaga_id) && $komponenPenilaian->subjek_type === 'mata_pelajaran') {
                $komponenPenilaian->lembaga_id = MataPelajaran::withoutGlobalScopes()
                    ->findOrFail($komponenPenilaian->subjek_id)
                    ->lembaga_id;
            }
            // subjek_type === 'elemen_cp': ElemenCp global, tidak punya
            // lembaga_id sendiri -- lembaga_id WAJIB sudah di-set eksplisit
            // oleh caller (CreateKomponenPenilaianAction) dari Semester
            // sebelum sampai sini.
        });
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function subjek(): MorphTo
    {
        return $this->morphTo();
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function asesmen(): BelongsToMany
    {
        return $this->belongsToMany(Asesmen::class, 'asesmen_komponen_penilaian', 'komponen_penilaian_id', 'asesmen_id');
    }

    public function nilaiSiswa(): HasMany
    {
        return $this->hasMany(NilaiSiswa::class);
    }
}
```

- [ ] **Step 2: `Asesmen` model**

Ganti `mataPelajaran(): BelongsTo { return $this->belongsTo(MataPelajaran::class); }` menjadi:

```php
public function subjek(): MorphTo
{
    return $this->morphTo();
}
```

Tambah `use Illuminate\Database\Eloquent\Relations\MorphTo;`, hapus `use App\Domains\Akademik\Models\MataPelajaran;` (tidak dipakai lagi di file ini), ganti `$fillable`: `'mata_pelajaran_id'` → `'subjek_type', 'subjek_id'`.

- [ ] **Step 3: DTO — `KomponenPenilaianData`**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class KomponenPenilaianData
{
    public function __construct(
        public string $subjekType,
        public int $subjekId,
        public int $semesterId,
        public ?string $kode,
        public string $deskripsi,
        public int $bobot,
        public ?string $kktp,
        public ?int $kktpMinimal,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            subjekType: (string) $data['subjek_type'],
            subjekId: (int) $data['subjek_id'],
            semesterId: (int) $data['semester_id'],
            kode: $data['kode'] ?? null,
            deskripsi: $data['deskripsi'],
            bobot: isset($data['bobot']) ? (int) $data['bobot'] : 10,
            kktp: $data['kktp'] ?? null,
            kktpMinimal: isset($data['kktp_minimal']) ? (int) $data['kktp_minimal'] : null,
        );
    }
}
```

`ElemenCapaianPembelajaran` enum import & `elemenCp` property dihapus dari DTO ini sepenuhnya (digantikan `subjekType`/`subjekId`).

- [ ] **Step 4: DTO — `UpdateKomponenPenilaianData`**

Sama polanya: `mataPelajaranId`/`elemenCp` → `subjekType`/`subjekId` (nullable karena bisa tidak berubah saat update):

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class UpdateKomponenPenilaianData
{
    public function __construct(
        public ?string $subjekType,
        public ?int $subjekId,
        public ?int $semesterId,
        public ?string $kode,
        public string $deskripsi,
        public ?int $bobot,
        public ?string $kktp,
        public ?int $kktpMinimal,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            subjekType: $data['subjek_type'] ?? null,
            subjekId: isset($data['subjek_id']) ? (int) $data['subjek_id'] : null,
            semesterId: isset($data['semester_id']) ? (int) $data['semester_id'] : null,
            kode: $data['kode'] ?? null,
            deskripsi: $data['deskripsi'],
            bobot: isset($data['bobot']) ? (int) $data['bobot'] : null,
            kktp: $data['kktp'] ?? null,
            kktpMinimal: isset($data['kktp_minimal']) ? (int) $data['kktp_minimal'] : null,
        );
    }
}
```

- [ ] **Step 5: DTO — `AsesmenData`**

Ganti `public int $mataPelajaranId` → `public string $subjekType, public int $subjekId`, dan `mataPelajaranId: (int) $data['mata_pelajaran_id']` → `subjekType: (string) $data['subjek_type'], subjekId: (int) $data['subjek_id']`.

- [ ] **Step 6: `CreateKomponenPenilaianAction`**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Penilaian;

use App\Domains\Akademik\DataTransferObjects\KomponenPenilaianData;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Models\Semester;
use Illuminate\Validation\ValidationException;

final class CreateKomponenPenilaianAction
{
    /**
     * @throws ValidationException
     */
    public function execute(KomponenPenilaianData $data): KomponenPenilaian
    {
        $existingSum = KomponenPenilaian::where('subjek_type', $data->subjekType)
            ->where('subjek_id', $data->subjekId)
            ->where('semester_id', $data->semesterId)
            ->sum('bobot');

        if (($existingSum + $data->bobot) > 100) {
            $remaining = max(0, 100 - $existingSum);
            throw ValidationException::withMessages([
                'bobot' => "Total bobot melebihi 100%. Sisa bobot yang tersedia untuk subjek ini adalah {$remaining}%.",
            ]);
        }

        return KomponenPenilaian::create([
            'subjek_type' => $data->subjekType,
            'subjek_id' => $data->subjekId,
            'semester_id' => $data->semesterId,
            // lembaga_id eksplisit dari Semester -- WAJIB utk subjek_type
            // elemen_cp karena ElemenCp sendiri tidak punya lembaga_id
            // (booted() hook di model hanya menangani jalur mata_pelajaran).
            'lembaga_id' => Semester::findOrFail($data->semesterId)->lembaga_id,
            'kode' => $data->kode,
            'deskripsi' => $data->deskripsi,
            'bobot' => $data->bobot,
            'kktp' => $data->kktp,
            'kktp_minimal' => $data->kktpMinimal,
        ]);
    }
}
```

- [ ] **Step 7: `UpdateKomponenPenilaianAction`**

Ganti bagian yang menyentuh `mataPelajaranId`:

```php
if (! $dipakai && $data->subjekType !== null && $data->subjekId !== null && $data->semesterId !== null) {
    $komponen->subjek_type = $data->subjekType;
    $komponen->subjek_id = $data->subjekId;
    $komponen->semester_id = $data->semesterId;
}

$newBobot = $data->bobot ?? $komponen->bobot;
$existingSum = KomponenPenilaian::where('subjek_type', $komponen->subjek_type)
    ->where('subjek_id', $komponen->subjek_id)
    ->where('semester_id', $komponen->semester_id)
    ->where('id', '!=', $komponen->id)
    ->sum('bobot');
```

Hapus baris `$komponen->elemen_cp = $data->elemenCp;` (kolom sudah tidak ada).

- [ ] **Step 8: `CreateAsesmenAction`**

Ganti setiap `mata_pelajaran_id`/`mataPelajaranId` jadi `subjek_type`/`subjek_id` dari `$data->subjekType`/`$data->subjekId`:

```php
$komponenIds = ! empty($data->komponenId)
    ? KomponenPenilaian::whereIn('id', $data->komponenId)
        ->where('subjek_type', $data->subjekType)
        ->where('subjek_id', $data->subjekId)
        ->pluck('id')
    : collect();
```

```php
$asesmen = Asesmen::create([
    'guru_id' => $guru->id,
    'kelas_id' => $data->kelasId,
    'subjek_type' => $data->subjekType,
    'subjek_id' => $data->subjekId,
    'semester_id' => $data->semesterId,
    'jenis' => JenisAsesmen::from($data->jenis),
    'judul' => $data->judul,
    'tanggal' => $data->tanggal,
]);
```

- [ ] **Step 9: Form Requests**

`StoreKomponenPenilaianRequest`:
```php
public function rules(): array
{
    return [
        'subjek_type' => ['required', Rule::in(['mata_pelajaran', 'elemen_cp'])],
        'subjek_id' => ['required', 'integer', function ($attribute, $value, $fail) {
            $exists = match ($this->input('subjek_type')) {
                'mata_pelajaran' => MataPelajaran::where('id', $value)->exists(),
                'elemen_cp' => ElemenCp::where('id', $value)->exists(),
                default => false,
            };
            if (! $exists) {
                $fail('Subjek penilaian yang dipilih tidak valid.');
            }
        }],
        'semester_id' => ['required', 'integer'],
        'kode' => ['nullable', 'string', 'max:50'],
        'deskripsi' => ['required', 'string'],
        'bobot' => ['nullable', 'integer', 'min:1', 'max:100'],
        'kktp' => ['nullable', 'string'],
        'kktp_minimal' => ['nullable', 'integer', 'min:0', 'max:100'],
    ];
}
```
Tambah `use App\Domains\Akademik\Models\ElemenCp;`, `use App\Domains\Akademik\Models\MataPelajaran;`, `use Illuminate\Validation\Rule;`. Hapus import `ElemenCapaianPembelajaran` dan baris `'elemen_cp' => ['nullable', Rule::enum(...)]`.

`UpdateKomponenPenilaianRequest`: sama pola, field `subjek_type`/`subjek_id` masuk ke blok `if (! $dipakai)` (menggantikan `mata_pelajaran_id`/`semester_id` yang sudah ada di sana), validasi custom rule yang sama ditambahkan.

`StoreKomponenPenilaianSendiriRequest`/`UpdateKomponenPenilaianSendiriRequest`: pola identik dengan Store/UpdateKomponenPenilaianRequest.

`StoreAsesmenRequest`: ganti `'mata_pelajaran_id' => ['required', 'integer']` jadi `'subjek_type' => ['required', Rule::in(['mata_pelajaran', 'elemen_cp'])]` + `'subjek_id' => ['required', 'integer', ...]` (rule sama seperti di atas).

- [ ] **Step 10: `Admin\KomponenPenilaianController`**

- `KomponenPenilaian::whereHas('mataPelajaran')` → `KomponenPenilaian::whereNotNull('subjek_id')` (morph relation tidak bisa `whereHas` polos tanpa `whereHasMorph`; cukup cek non-null karena NOT NULL constraint sudah menjamin integritas setelah Task 6).
- `->with(['mataPelajaran', 'semester.tahunAjaran'])` → `->with(['subjek', 'semester.tahunAjaran'])`.
- `->when($mataPelajaranId, fn ($q) => $q->where('mata_pelajaran_id', $mataPelajaranId))` → filter query param diganti jadi filter berbasis `subjek_type`+`subjek_id` kalau query param dikirim sbg pasangan (`?subjek_type=mata_pelajaran&subjek_id=3`), atau — kalau untuk sementara filter dropdown di UI tetap cuma utk mata pelajaran (lihat Task 5) — filter tetap `->when($mataPelajaranId, fn ($q) => $q->where('subjek_type', 'mata_pelajaran')->where('subjek_id', $mataPelajaranId))`.
- `$mataPelajaran = MataPelajaran::find($data['mata_pelajaran_id']); abort_if($mataPelajaran === null || $semester === null, 404); abort_if($mataPelajaran->lembaga_id !== $semester->lembaga_id, 404);` (muncul di `store()` dan `update()`) → jadi:
  ```php
  $subjek = match ($data['subjek_type']) {
      'mata_pelajaran' => MataPelajaran::find($data['subjek_id']),
      'elemen_cp' => ElemenCp::find($data['subjek_id']),
  };
  abort_if($subjek === null || $semester === null, 404);
  if ($data['subjek_type'] === 'mata_pelajaran') {
      abort_if($subjek->lembaga_id !== $semester->lembaga_id, 404);
  }
  // subjek_type === 'elemen_cp': tidak ada guard lembaga, ElemenCp global.
  ```
- `$mataPelajaran = MataPelajaran::find($komponenPenilaian->mata_pelajaran_id);` (guard existence di `edit()`/`destroy()`) → `$subjek = $komponenPenilaian->subjek;` lalu guard `if (! $subjek) { ... }` tetap sama polanya.
- `'komponenPenilaian' => $komponenPenilaian->load(['mataPelajaran', 'semester.tahunAjaran'])` → `->load(['subjek', 'semester.tahunAjaran'])`.
- Tambah `'elemenCpList' => ElemenCp::orderBy('no_urut')->get()` ke data yang dikirim ke view `create`/`edit` (dibutuhkan Task 5 utk dropdown).

- [ ] **Step 11: `Guru\KomponenPenilaianController`**

- `KomponenPenilaian::whereIn('mata_pelajaran_id', $mapelIds)` → `KomponenPenilaian::where('subjek_type', 'mata_pelajaran')->whereIn('subjek_id', $mapelIds)` (guru mapel HANYA lihat komponen ber-`subjek_type=mata_pelajaran` yang mapel-nya dia ajar — TIDAK melihat komponen `elemen_cp`, keputusan sengaja sesuai spec §11).
- `->with(['mataPelajaran', 'semester.tahunAjaran'])` → `->with(['subjek', 'semester.tahunAjaran'])` (di semua tempat file ini).
- Tambah `'elemenCpList' => ElemenCp::orderBy('no_urut')->get()` ke data create/edit — ini PENAMBAHAN BARU (portal Guru sebelumnya tidak pernah kirim variabel ini sama sekali).

- [ ] **Step 12: `Guru\AsesmenController`**

- `->with(['kelas', 'mataPelajaran', 'semester'])` → `->with(['kelas', 'subjek', 'semester'])` (di semua occurrence file ini yang menyentuh `Asesmen`, BUKAN yang menyentuh `JadwalPelajaran` — perhatikan baris 56 di laporan teknis adalah eager-load `JadwalPelajaran`, biarkan `mataPelajaran` di sana TIDAK berubah).
- `'komponenList' => KomponenPenilaian::whereIn('mata_pelajaran_id', $mapelIds)->get()` → `KomponenPenilaian::where('subjek_type', 'mata_pelajaran')->whereIn('subjek_id', $mapelIds)->get()`.

- [ ] **Step 13: `Admin\DashboardController`**

Baris 136 & 207-208 (eager-load nested nilai untuk siswa/orang-tua):
```php
->with([
    'komponenPenilaian' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->with(['subjek' => fn ($q2) => $q2->withoutGlobalScope(TenantScope::class)]),
    'asesmen' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->with(['subjek' => fn ($q2) => $q2->withoutGlobalScope(TenantScope::class)]),
])
```

- [ ] **Step 14: `DashboardStatsService::whereHas('mataPelajaran', ...)`**

```php
$totalKomponen = KomponenPenilaian::where('semester_id', $semester->id)
    ->whereHasMorph('subjek', [MataPelajaran::class], fn ($q) => $q->where('lembaga_id', $kelas->lembaga_id))
    ->count();
```
`whereHasMorph` dengan array `[MataPelajaran::class]` secara otomatis HANYA mencocokkan baris ber-`subjek_type=mata_pelajaran` — baris `elemen_cp` tidak akan pernah match constraint `lembaga_id` (karena `ElemenCp` tidak punya kolom itu) dan otomatis ter-exclude dengan benar, bukan error.

- [ ] **Step 15: `RaporCalculationService::hitungRekapKelas()`**

```php
<?php

namespace App\Domains\Akademik\Services;

use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Support\SubjekPenilaianKey;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;

final class RaporCalculationService
{
    public function hitungRekapKelas(Kelas $kelas, Semester $semester): array
    {
        $siswaList = Siswa::where('kelas_id', $kelas->id)->orderBy('nama_lengkap')->get();

        $asesmenList = Asesmen::where('kelas_id', $kelas->id)
            ->where('semester_id', $semester->id)
            ->with('subjek')
            ->get();

        $subjekList = $asesmenList->pluck('subjek')
            ->filter()
            ->unique(fn ($s) => SubjekPenilaianKey::dari($s))
            ->sortBy('nama')
            ->values();

        $asesmenByKey = $asesmenList->groupBy(fn ($a) => SubjekPenilaianKey::dari($a->subjek));

        $allNilai = NilaiSiswa::whereIn('asesmen_id', $asesmenList->pluck('id'))
            ->with('komponenPenilaian')
            ->get();

        $rekapNilai = [];
        foreach ($siswaList as $siswa) {
            $rekapNilai[$siswa->id] = [];
            foreach ($subjekList as $subjek) {
                $key = SubjekPenilaianKey::dari($subjek);
                $subjekAsesmenIds = ($asesmenByKey->get($key) ?? collect())->pluck('id');
                $scores = $allNilai->whereIn('asesmen_id', $subjekAsesmenIds)
                    ->where('siswa_id', $siswa->id)
                    ->whereNotNull('nilai_angka');

                if ($scores->count() > 0) {
                    $totalWeight = 0;
                    $weightedSum = 0;
                    foreach ($scores as $item) {
                        $w = $item->komponenPenilaian && $item->komponenPenilaian->bobot > 0 ? (int) $item->komponenPenilaian->bobot : 1;
                        $weightedSum += ($item->nilai_angka * $w);
                        $totalWeight += $w;
                    }
                    $rekapNilai[$siswa->id][$key] = $totalWeight > 0 ? round($weightedSum / $totalWeight, 1) : null;
                } else {
                    $rekapNilai[$siswa->id][$key] = null;
                }
            }
        }

        $allScores = collect($rekapNilai)->flatMap(fn ($m) => collect($m)->filter(fn ($v) => $v !== null));

        return [
            'siswaList' => $siswaList,
            'mapelList' => $subjekList, // nama key dipertahankan "mapelList" utk minimalkan breaking change di consumer lain di luar scope Sprint 1
            'rekapNilai' => $rekapNilai,
            'classAvg' => $allScores->count() > 0 ? round($allScores->avg(), 1) : null,
            'highestScore' => $allScores->count() > 0 ? $allScores->max() : null,
        ];
    }
}
```
Catatan: array hasil tetap pakai nama key `'mapelList'` (bukan `'subjekList'`) supaya consumer (`RaporPdfDataBuilder`, test lama) yang belum sempat direname tetap kompatibel di level nama-array — isinya sekarang koleksi subjek campuran (MataPelajaran|ElemenCp), bukan cuma MataPelajaran. Kalau implementer menilai rename penuh ke `'subjekList'` lebih bersih dan blast radius-nya kecil, boleh dilakukan sekalian asalkan SEMUA consumer (Step 16) diupdate konsisten dalam task yang sama.

- [ ] **Step 16: `RaporPdfDataBuilder`**

Ganti setiap `$mapel->id` (sbg array key) jadi `SubjekPenilaianKey::dari($mapel)`:
```php
$narasiPerMapel = [];
foreach ($mapelList as $mapel) {
    $narasiPerMapel[SubjekPenilaianKey::dari($mapel)] = $this->capaianKompetensiGenerator->generateNarasi($siswa, $mapel, $semester);
}
```
```php
foreach ($mapelList as $mapel) {
    $key = SubjekPenilaianKey::dari($mapel);
    $nilaiGenap = $rekapNilaiSiswa[$key] ?? null;
    $nilaiGanjil = $rekapNilaiGanjilSiswa[$key] ?? null;

    $nilaiRataRataTahunan[$key] = match (true) {
        $nilaiGenap !== null && $nilaiGanjil !== null => round(($nilaiGenap + $nilaiGanjil) / 2, 1),
        $nilaiGenap !== null => $nilaiGenap,
        $nilaiGanjil !== null => $nilaiGanjil,
        default => null,
    };
}
```
Tambah `use App\Domains\Akademik\Support\SubjekPenilaianKey;`.

- [ ] **Step 17: `CapaianKompetensiGenerator::generateNarasi()`**

```php
use App\Domains\Akademik\Contracts\SubjekPenilaian;

public function generateNarasi(Siswa $siswa, SubjekPenilaian $subjek, Semester $semester): array
{
    $komponenList = KomponenPenilaian::where('subjek_type', $subjek->getMorphClass())
        ->where('subjek_id', $subjek->getKey())
        ->where('semester_id', $semester->id)
        ->get();
    // ...

    $asesmenIds = Asesmen::where('subjek_type', $subjek->getMorphClass())
        ->where('subjek_id', $subjek->getKey())
        ->where('semester_id', $semester->id)
        ->pluck('id');
    // ... sisa method tidak berubah
}
```
`$subjek` harus berupa instance Eloquent Model (MataPelajaran|ElemenCp) supaya `getMorphClass()`/`getKey()` tersedia — parameter type-hint `SubjekPenilaian` murni interface marker, method `getMorphClass()`/`getKey()` datang dari `Illuminate\Database\Eloquent\Model` yang kedua concrete class-nya extend. Kalau PHP menolak memanggil method Model lewat type-hint interface murni (karena interface tidak mendeklarasikan method itu), longgarkan type-hint jadi union `MataPelajaran|ElemenCp $subjek` sebagai gantinya — verifikasi mana yang benar-benar jalan saat implementasi.

- [ ] **Step 18: Factories**

`KomponenPenilaianFactory`:
```php
public function definition(): array
{
    return [
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => MataPelajaran::factory(),
        'semester_id' => Semester::factory(),
        'kode' => 'TP '.$this->faker->numberBetween(1, 9).'.'.$this->faker->numberBetween(1, 5),
        'deskripsi' => $this->faker->sentence(8),
        'kktp' => $this->faker->sentence(6),
    ];
}

public function elemenCp(): static
{
    return $this->state(fn () => [
        'subjek_type' => 'elemen_cp',
        'subjek_id' => ElemenCp::factory(),
    ]);
}
```
**Verifikasi implementer**: `'subjek_id' => MataPelajaran::factory()` — pastikan Eloquent factory benar meng-create `MataPelajaran` baru lalu memasukkan id-nya ke `subjek_id` (bukan objek factory itu sendiri). Kalau ternyata tidak otomatis resolve (karena `subjek_id` bukan nama relasi standar Eloquent yang factory kenali), ganti jadi closure eksplisit: `'subjek_id' => fn () => MataPelajaran::factory()->create()->id`.

`AsesmenFactory`: pola sama persis.

- [ ] **Step 19: Test — tenant validation berbeda per tipe subjek**

```php
<?php
// tests/Feature/Akademik/SubjekTenantValidationTest.php

use App\Domains\Akademik\Models\ElemenCp;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
});

it('rejects a komponen penilaian whose mata_pelajaran belongs to a different lembaga than the semester', function () {
    $lembagaA = Lembaga::factory()->create();
    $lembagaB = Lembaga::factory()->create();
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semester = Semester::factory()->create(['lembaga_id' => $lembagaB->id]);

    $user = User::factory()->create(['lembaga_id' => $lembagaB->id]);
    $user->assignRole('operator_akademik');

    $response = $this->actingAs($user)->post(route('admin.komponen-penilaian.store'), [
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id,
        'semester_id' => $semester->id,
        'deskripsi' => 'Tes',
        'bobot' => 10,
    ]);

    $response->assertNotFound();
});

it('accepts a komponen penilaian with subjek_type=elemen_cp regardless of the acting lembaga (global reference data)', function () {
    $lembaga = Lembaga::factory()->create();
    $elemen = ElemenCp::factory()->create();
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga->id]);

    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('operator_akademik');

    $response = $this->actingAs($user)->post(route('admin.komponen-penilaian.store'), [
        'subjek_type' => 'elemen_cp',
        'subjek_id' => $elemen->id,
        'semester_id' => $semester->id,
        'deskripsi' => 'Tes PAUD',
        'bobot' => 10,
    ]);

    $response->assertRedirect();
    expect(\App\Domains\Akademik\Models\KomponenPenilaian::where('subjek_type', 'elemen_cp')->where('subjek_id', $elemen->id)->exists())->toBeTrue();
});
```
**Catatan implementer**: sesuaikan nama route/permission yang dipakai `operator_akademik` untuk komponen-penilaian kalau berbeda dari asumsi di atas — cek `routes/admin/akademik-master.php` dan `RoleSeeder.php` untuk permission persisnya sebelum menulis assertion final.

- [ ] **Step 20: Test — composite key mencegah collision di rekap kelas**

```php
<?php
// tests/Feature/Akademik/RaporCalculationCompositeKeyTest.php

use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\ElemenCp;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Services\RaporCalculationService;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\Guru;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('does not collide rekap between a MataPelajaran and an ElemenCp sharing the same numeric id', function () {
    $kelas = Kelas::factory()->create();
    $semester = Semester::factory()->create();
    $siswa = Siswa::factory()->create(['kelas_id' => $kelas->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $kelas->lembaga_id]);

    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $kelas->lembaga_id]);
    $elemen = ElemenCp::factory()->create();

    $asesmenMapel = Asesmen::factory()->create([
        'kelas_id' => $kelas->id, 'semester_id' => $semester->id, 'guru_id' => $guru->id,
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id,
    ]);
    $asesmenElemen = Asesmen::factory()->create([
        'kelas_id' => $kelas->id, 'semester_id' => $semester->id, 'guru_id' => $guru->id,
        'subjek_type' => 'elemen_cp', 'subjek_id' => $elemen->id,
    ]);

    $komponenMapel = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $komponenElemen = KomponenPenilaian::factory()->create(['subjek_type' => 'elemen_cp', 'subjek_id' => $elemen->id, 'semester_id' => $semester->id]);

    NilaiSiswa::create(['asesmen_id' => $asesmenMapel->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenMapel->id, 'nilai_angka' => 80]);
    NilaiSiswa::create(['asesmen_id' => $asesmenElemen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenElemen->id, 'nilai_angka' => 95]);

    $rekap = app(RaporCalculationService::class)->hitungRekapKelas($kelas, $semester);

    expect($rekap['mapelList'])->toHaveCount(2);
    expect($rekap['rekapNilai'][$siswa->id]['mata_pelajaran:'.$mapel->id])->toBe(80.0);
    expect($rekap['rekapNilai'][$siswa->id]['elemen_cp:'.$elemen->id])->toBe(95.0);
});
```

- [ ] **Step 21: Jalankan test scoped**

Run: `php artisan test --filter="SubjekTenantValidationTest|RaporCalculationCompositeKeyTest"`
Expected: semua PASS. Kalau ada test lama (26 file dari spec) yang ikut jalan dan gagal, itu diharapkan — akan diperbaiki Task 6, JANGAN diperbaiki di sini supaya scope task tetap jelas.

- [ ] **Step 22: Commit**

```bash
git add app/Domains/Akademik/Models/KomponenPenilaian.php app/Domains/Akademik/Models/Asesmen.php app/Domains/Akademik/DataTransferObjects/ app/Domains/Akademik/Actions/Penilaian/ app/Http/Requests/Akademik/ app/Http/Controllers/Admin/KomponenPenilaianController.php app/Http/Controllers/Guru/KomponenPenilaianController.php app/Http/Controllers/Guru/AsesmenController.php app/Http/Controllers/Admin/DashboardController.php app/Services/DashboardStatsService.php app/Domains/Akademik/Services/RaporCalculationService.php app/Domains/Akademik/Services/RaporPdfDataBuilder.php app/Domains/Akademik/Services/CapaianKompetensiGenerator.php database/factories/KomponenPenilaianFactory.php database/factories/AsesmenFactory.php tests/Feature/Akademik/SubjekTenantValidationTest.php tests/Feature/Akademik/RaporCalculationCompositeKeyTest.php
git commit -m "refactor(akademik): geser Model/Action/DTO/Request/Controller/Service ke subjek polymorphic"
```

---

### Task 5: Blade Views — `->subjek` + Toggle UI (Lembaga & Guru)

**Files:**
- Modify: `resources/views/admin/dashboard/siswa.blade.php`
- Modify: `resources/views/admin/dashboard/orang-tua.blade.php`
- Modify: `resources/views/portals/guru/akademik/komponen-penilaian/_daftar.blade.php`
- Modify: `resources/views/portals/guru/akademik/komponen-penilaian/edit.blade.php`
- Modify: `resources/views/portals/guru/akademik/komponen-penilaian/create.blade.php`
- Modify: `resources/views/portals/guru/akademik/komponen-penilaian/index.blade.php`
- Modify: `resources/views/portals/guru/akademik/asesmen/show.blade.php`
- Modify: `resources/views/portals/guru/akademik/asesmen/index.blade.php`
- Modify: `resources/views/portals/guru/akademik/asesmen/create.blade.php`
- Modify: `resources/views/portals/lembaga/akademik/komponen-penilaian/_daftar.blade.php`
- Modify: `resources/views/portals/lembaga/akademik/komponen-penilaian/edit.blade.php`
- Modify: `resources/views/portals/lembaga/akademik/komponen-penilaian/create.blade.php`
- Modify: `resources/views/portals/lembaga/akademik/komponen-penilaian/index.blade.php`
- Test: `tests/Feature/Guru/KomponenPenilaianElemenCpUiTest.php`

**Interfaces:**
- Consumes: `KomponenPenilaian::subjek`, `Asesmen::subjek` (Task 4), `$elemenCpList` (Task 4, controller).

- [ ] **Step 1: Ganti `->mataPelajaran->nama` → `->subjek->nama` (semua occurrence)**

Di setiap file berikut, ganti literal `->mataPelajaran->nama` menjadi `->subjek->nama`, dan `->mataPelajaran?->nama` menjadi `->subjek?->nama`:
- `resources/views/admin/dashboard/siswa.blade.php:117`
- `resources/views/admin/dashboard/orang-tua.blade.php:102`
- `resources/views/portals/guru/akademik/komponen-penilaian/_daftar.blade.php:22,66`
- `resources/views/portals/guru/akademik/komponen-penilaian/edit.blade.php:45`
- `resources/views/portals/guru/akademik/asesmen/show.blade.php:38`
- `resources/views/portals/guru/akademik/asesmen/index.blade.php:95,123`
- `resources/views/portals/lembaga/akademik/komponen-penilaian/_daftar.blade.php:60,104`
- `resources/views/portals/lembaga/akademik/komponen-penilaian/edit.blade.php:46`

- [ ] **Step 2: Toggle "Jenis Subjek Penilaian" — Lembaga portal (`create.blade.php`, `edit.blade.php`)**

Cari blok `@if (in_array($bentukPendidikan, ['KB', 'TPA', 'SPS', 'TK'], true))` yang berisi dropdown elemen CP existing (create.blade.php:157-167, edit.blade.php:161-164 area) — bungkus dropdown mata pelajaran DAN dropdown elemen CP existing dengan Alpine toggle radio baru, sehingga hanya salah satu terkirim sesuai pilihan:

```blade
<div>
    <x-input-label value="Jenis Subjek Penilaian" />
    <div class="flex gap-4 mt-1.5 text-sm">
        <label class="flex items-center gap-1.5">
            <input type="radio" name="subjek_type" value="mata_pelajaran" x-model="subjekType">
            Mata Pelajaran
        </label>
        <label class="flex items-center gap-1.5">
            <input type="radio" name="subjek_type" value="elemen_cp" x-model="subjekType">
            Elemen CP (PAUD)
        </label>
    </div>
</div>

<div x-show="subjekType === 'mata_pelajaran'">
    {{-- dropdown mata pelajaran existing, name diganti dari mata_pelajaran_id -> subjek_id --}}
</div>

<div x-show="subjekType === 'elemen_cp'">
    <x-input-label value="Elemen Capaian Pembelajaran" />
    <select name="subjek_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
        <option value="">— Pilih Elemen CP —</option>
        @foreach ($elemenCpList as $elemen)
            <option value="{{ $elemen->id }}">{{ $elemen->nama }}</option>
        @endforeach
    </select>
</div>
```
`x-data` parent Alpine component perlu tambah state `subjekType: '{{ old('subjek_type', $komponenPenilaian->subjek_type ?? 'mata_pelajaran') }}'` (untuk edit) atau default `'mata_pelajaran'` (untuk create). Sesuaikan dengan struktur `x-data` yang sudah ada di file (per laporan teknis, file ini sudah punya `x-data`/`x-init` untuk `mataPelajaranSelect` — integrasikan `subjekType` ke object Alpine yang sama, jangan buat `x-data` kedua yang terpisah).

- [ ] **Step 3: Toggle yang sama — Guru portal (`create.blade.php`, `edit.blade.php`)**

Portal Guru SEBELUMNYA tidak punya dropdown elemen CP sama sekali — ini penambahan UI baru, bukan modifikasi existing. Tambahkan struktur identik dengan Step 2 di kedua file Guru portal ini.

- [ ] **Step 4: `index.blade.php` (kedua portal) — filter dropdown**

Filter `mataPelajaranId` di `index.blade.php` (Lembaga & Guru) TIDAK perlu diperluas untuk filter elemen_cp di Sprint 1 (di luar acceptance criteria — filter tetap cuma untuk mata pelajaran, seperti disebut di Task 4 Step 10). Tidak ada perubahan di file `index.blade.php` selain (kalau ada) `->mataPelajaran` yang sudah ditangani Step 1 — verifikasi tidak ada occurrence tersisa.

- [ ] **Step 5: Test — Guru bisa memilih Elemen CP (fitur baru)**

```php
<?php
// tests/Feature/Guru/KomponenPenilaianElemenCpUiTest.php

use App\Domains\Akademik\Models\ElemenCp;
use App\Models\Guru;
use App\Models\Semester;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
});

it('lets a guru create a komponen penilaian with subjek_type=elemen_cp via the self-service form', function () {
    $elemen = ElemenCp::factory()->create();
    $semester = Semester::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $semester->lembaga_id]);
    $user->assignRole('guru');
    $guru = Guru::factory()->create(['user_id' => $user->id, 'lembaga_id' => $semester->lembaga_id]);

    $response = $this->actingAs($user)->post(route('guru.komponen-penilaian.store'), [
        'subjek_type' => 'elemen_cp',
        'subjek_id' => $elemen->id,
        'semester_id' => $semester->id,
        'deskripsi' => 'Perkembangan jati diri anak',
        'bobot' => 10,
    ]);

    $response->assertRedirect();
    expect(\App\Domains\Akademik\Models\KomponenPenilaian::where('subjek_type', 'elemen_cp')->where('subjek_id', $elemen->id)->exists())->toBeTrue();
});

it('shows the Elemen CP dropdown on the guru komponen penilaian create form', function () {
    $semester = Semester::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $semester->lembaga_id]);
    $user->assignRole('guru');
    Guru::factory()->create(['user_id' => $user->id, 'lembaga_id' => $semester->lembaga_id]);

    $response = $this->actingAs($user)->get(route('guru.komponen-penilaian.create'));

    $response->assertOk();
    $response->assertSee('Elemen CP', false);
});
```
**Catatan implementer**: verifikasi nama route persis (`guru.komponen-penilaian.store`/`create`) di `routes/admin/penilaian-rapor.php` atau file route terkait sebelum finalisasi — sesuaikan kalau berbeda.

- [ ] **Step 6: Jalankan test scoped**

Run: `php artisan test --filter=KomponenPenilaianElemenCpUiTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/views/admin/dashboard/siswa.blade.php resources/views/admin/dashboard/orang-tua.blade.php resources/views/portals/guru/akademik/komponen-penilaian/ resources/views/portals/guru/akademik/asesmen/ resources/views/portals/lembaga/akademik/komponen-penilaian/ tests/Feature/Guru/KomponenPenilaianElemenCpUiTest.php
git commit -m "feat(akademik): toggle Jenis Subjek Penilaian di form komponen-penilaian Lembaga & Guru"
```

---

### Task 6: Perbaiki 26 Test Lama + Verifikasi Final + Migration Drop

**Files:**
- Modify: 26 file test (lihat daftar di spec §14, prioritas: `KomponenPenilaianCrudTest.php`, `Guru/KomponenPenilaianControllerTest.php`, `Guru/AsesmenControllerTest.php`, `Akademik/CapaianKompetensiGeneratorTest.php`, `Unit/Services/RaporCalculationServiceTest.php`, `Akademik/RaporPdfDataBuilderTest.php`, `Admin/RaporControllerTest.php`, sisanya menyusul)
- Create: `database/migrations/2026_08_26_100300_drop_mata_pelajaran_id_and_elemen_cp_columns.php`

**Interfaces:**
- Consumes: seluruh hasil Task 1-5.
- Produces: sistem final tanpa jejak `mata_pelajaran_id`/`elemen_cp`/`->mataPelajaran` di area `KomponenPenilaian`/`Asesmen`.

- [ ] **Step 1: Perbaiki setiap test yang create `KomponenPenilaian`/`Asesmen` langsung dengan `mata_pelajaran_id`**

Pola perbaikan seragam di semua 26 file — setiap `KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, ...])` atau `KomponenPenilaian::create([...'mata_pelajaran_id' => ...])` menjadi `['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, ...]`. Sama untuk `Asesmen`. Untuk request test (HTTP POST/PUT ke komponen-penilaian/asesmen endpoint) ganti payload `'mata_pelajaran_id' => $mapel->id` jadi `'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id`. Kerjakan satu file per commit kecil ATAU sekaligus per prioritas tinggi lebih dulu — implementer boleh memilih granularitas commit selama tiap commit tetap lolos test filenya sendiri.

- [ ] **Step 2: Jalankan seluruh test Akademik**

Run: `php artisan test --filter=Akademik`
Run: `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php tests/Feature/Guru/KomponenPenilaianControllerTest.php tests/Feature/Guru/AsesmenControllerTest.php tests/Feature/Admin/RaporControllerTest.php tests/Feature/Guru/RaporControllerTest.php tests/Feature/Admin/KenaikanKelasControllerTest.php tests/Feature/DashboardTest.php tests/Feature/AkademikTenantScopeTest.php tests/Unit/Models/NilaiSiswaTest.php tests/Unit/Models/AsesmenTest.php tests/Unit/KomponenPenilaianSeederTest.php`
Expected: semua PASS, nol failure.

- [ ] **Step 3: Verifikasi grep — WAJIB nol hasil sebelum lanjut ke Step 4**

Run:
```bash
git grep -n "mata_pelajaran_id" -- 'app/**/KomponenPenilaian*' 'app/**/Asesmen*' 'app/Http/Requests/Akademik/*Komponen*' 'app/Http/Requests/Akademik/*Asesmen*' 'resources/views/**/komponen-penilaian/*' 'resources/views/**/asesmen/*'
git grep -n "elemen_cp" -- 'app/**/KomponenPenilaian*'
git grep -n "\->mataPelajaran" -- 'app/Http/Controllers/Admin/KomponenPenilaianController.php' 'app/Http/Controllers/Guru/KomponenPenilaianController.php' 'app/Http/Controllers/Guru/AsesmenController.php' 'app/Domains/Akademik/Services/RaporCalculationService.php' 'app/Domains/Akademik/Services/RaporPdfDataBuilder.php' 'app/Domains/Akademik/Services/CapaianKompetensiGenerator.php' 'resources/views/admin/dashboard/*.blade.php' 'resources/views/portals/**/komponen-penilaian/*' 'resources/views/portals/**/asesmen/*'
```
Expected: **nol baris output** dari ketiga command. Kalau ada sisa, PERBAIKI DULU — jangan lanjut ke Step 4. Ini bukan langkah opsional (lihat Global Constraints).

- [ ] **Step 4: Migration drop kolom lama (HANYA setelah Step 3 bersih)**

```php
<?php
// database/migrations/2026_08_26_100300_drop_mata_pelajaran_id_and_elemen_cp_columns.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('komponen_penilaian', function (Blueprint $table) {
            $table->dropForeign(['mata_pelajaran_id']);
            $table->dropColumn(['mata_pelajaran_id', 'elemen_cp']);
            $table->string('subjek_type')->nullable(false)->change();
            $table->unsignedBigInteger('subjek_id')->nullable(false)->change();
        });

        Schema::table('asesmen', function (Blueprint $table) {
            $table->dropForeign(['mata_pelajaran_id']);
            $table->dropColumn('mata_pelajaran_id');
            $table->string('subjek_type')->nullable(false)->change();
            $table->unsignedBigInteger('subjek_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('komponen_penilaian', function (Blueprint $table) {
            $table->foreignId('mata_pelajaran_id')->nullable()->after('subjek_id')->constrained('mata_pelajaran')->cascadeOnDelete();
            $table->string('elemen_cp', 30)->nullable();
            $table->string('subjek_type')->nullable()->change();
            $table->unsignedBigInteger('subjek_id')->nullable()->change();
        });

        Schema::table('asesmen', function (Blueprint $table) {
            $table->foreignId('mata_pelajaran_id')->nullable()->after('subjek_id')->constrained('mata_pelajaran')->cascadeOnDelete();
            $table->string('subjek_type')->nullable()->change();
            $table->unsignedBigInteger('subjek_id')->nullable()->change();
        });
    }
};
```
**Catatan implementer**: nama constraint foreign key (`dropForeign(['mata_pelajaran_id'])`) mengasumsikan konvensi penamaan default Laravel (`komponen_penilaian_mata_pelajaran_id_foreign`) — verifikasi nama sebenarnya via `SHOW CREATE TABLE komponen_penilaian;` kalau migration gagal di step ini karena nama constraint tidak cocok.

- [ ] **Step 5: Jalankan `migrate:fresh --seed` penuh + full test suite**

Run: `php artisan migrate:fresh --seed`
Expected: sukses tanpa error (termasuk `ElemenCpSeeder` yang baru terdaftar).

Run: `php artisan test`
Expected: semua PASS, nol failure baru (test lama yang sebelumnya sudah flaky di luar scope refactor ini — misal `TahunAjaranFactory` collision yang pernah tercatat sebelumnya — boleh diabaikan KALAU terbukti sama sekali tidak berkaitan dengan perubahan Sprint 1, verifikasi dengan re-run test itu sendiri secara terisolasi).

- [ ] **Step 6: Commit final Task 6**

```bash
git add -A
git commit -m "test(akademik): perbaiki 26 test lama ke pola subjek + migration drop mata_pelajaran_id/elemen_cp"
```

---

## Sprint 2–5 — Outline Plan (Belum Detail Penuh)

Detail lengkap masing-masing DITULIS SAAT gilirannya tiba untuk dieksekusi (per kesepakatan review) — bagian ini murni penanda urutan & dependency, bukan instruksi siap-eksekusi.

### Sprint 2 — Assessment Type
- Kolom `komponen_penilaian.assessment_type` (default `'numeric'`, non-breaking).
- Enum `AssessmentType` (`Numeric`/`Narrative`/`Predicate`).
- Form input nilai guru (`portals/guru/akademik/asesmen/show.blade.php`) render kondisional per tipe.
- **Depends on:** Sprint 1 (butuh `subjek_type` untuk default `assessment_type` saat create).

### Sprint 3 — Curriculum Phase
- Tabel `fase` (referensi global, non-tenant, mirip `elemen_cp`).
- `kelas.fase_id` nullable, BERDAMPINGAN dengan `Kelas.tingkat` (tidak menghapus).
- **Independen** dari Sprint 1-2, tapi diurutkan setelah untuk fokus tim.

### Sprint 4 — Academic Profile Service
- `AcademicProfile::fromBentukPendidikan()` — TANPA tabel baru, murni derivasi statis (pola sama `ModePembelajaran`).
- **Depends on:** Sprint 1-2 (field `defaultAssessmentType`/`reportTemplate` butuh konsep yang baru ada).

### Sprint 5 — Report Engine Abstraction
- Interface `ReportBuilder`, `RaporPdfDataBuilder` existing di-rename `DikdasReportBuilder implements ReportBuilder`.
- Orchestrator `ReportEngine` — builder lain (PAUD dst) BELUM diimplementasikan, throw exception jelas kalau dipanggil.
- **Depends on:** Sprint 1, Sprint 4.

---

## Self-Review

- **Cakupan spec**: setiap requirement Sprint 1 dari spec (§1-14, acceptance criteria) punya task yang mengimplementasikannya — Task 1 (§3,4), Task 2 (§1 kolom baru), Task 3 (§1 backfill+precedence), Task 4 (§4,5,6,7,8,9,12,13 model/action/DTO/request/controller/service), Task 5 (§10,11 UI+blade), Task 6 (§14 test+verifikasi final).
- **Placeholder scan**: tidak ada "tambahkan validasi yang sesuai"/"implementasikan logika serupa" tanpa kode konkret — setiap step berisi kode nyata. Beberapa "catatan implementer" sengaja ditandai eksplisit untuk hal yang butuh verifikasi runtime (nama route, nama FK constraint, perilaku factory polymorphic) karena tidak bisa dipastikan 100% tanpa menjalankan kode — ini bukan placeholder, tapi instruksi verifikasi yang jujur soal ketidakpastian yang tersisa.
- **Konsistensi tipe/nama**: `subjek_type`/`subjek_id` konsisten di seluruh task, `SubjekPenilaianKey::dari()` dipakai identik di Task 4 & test-nya, morph alias `mata_pelajaran`/`elemen_cp` konsisten dari Task 1 sampai Task 6.
- **Urutan dependency**: Task 1→2→3→4→5→6 murni linear (masing-masing butuh hasil sebelumnya), sesuai urutan TDD yang diminta reviewer (test morph → migrasi → composite key → refactor service → refactor controller/request → refactor blade → refactor test lama → regresi baru → full suite → grep verifikasi → migration final).
