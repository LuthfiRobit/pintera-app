# M3 SPMB Verifikasi & Keputusan — Data Layer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the database schema, Eloquent models, and dynamic RBAC permissions that M3's admin UI (a separate follow-up plan) will consume — document verification audit fields, manual test scoring (`hasil_seleksi`), batch SK issuance (`sk_ppdb`), and five new granular permissions under a `spmb-pendaftaran` module.

**Architecture:** Additive migrations on the existing `pendaftaran`/`dokumen_pendaftaran` tables (no breaking changes — M2's already-shipped public wizard keeps working untouched), two new tables, and an extension of the existing `PermissionCatalog`/`RolePermissionSeeder` infrastructure from Phase A. No routes, controllers, or views in this plan — pure data layer, exactly like M2's Plan 1.

**Tech Stack:** Laravel 12, Eloquent, Spatie Laravel Permission (already installed), Spatie Activitylog (already installed and used by `Guru`/`Lembaga`/etc.), Pest PHP.

## Global Constraints

- Every new/modified model follows the existing project conventions exactly: explicit `protected $table`, explicit `$fillable` (no `$guarded`), `casts()` method (not the old `$casts` property) for type casting.
- `Pendaftaran` deliberately does NOT use `BelongsToTenant` (established in the M2 data-layer plan — it's written by unauthenticated public routes with `lembaga_id` set explicitly). Do not add `BelongsToTenant` to `Pendaftaran`, `DokumenPendaftaran`, or `HasilSeleksi` in this plan.
- `SkPpdb` DOES use `BelongsToTenant` — its `lembaga_id` column exists specifically for this, and M3's controllers (built in the next plan) are authenticated admin routes where `TenantScope` correctly auto-scopes queries to the acting user's lembaga (confirmed: `TenantScope::apply()` no-ops when `auth()->id()` is null, so this is safe and matches every other M1 config model like `GelombangPpdb`/`JalurPpdb`).
- New permission names follow the existing `modul.aksi` convention exactly (verified against `PermissionCatalog`/`RolePermissionSeeder`): module `spmb-pendaftaran`, actions `view`, `verifikasi-dokumen`, `nilai-seleksi`, `tetapkan-keputusan`, `terbitkan-sk`.
- Reuse `Spatie\Activitylog\Traits\LogsActivity` on `Pendaftaran` and `DokumenPendaftaran` (pattern copied exactly from `Guru::getActivitylogOptions()`) instead of building a bespoke audit table.
- Migration timestamps continue chronologically after M2's last migration (`2026_07_14_090800_create_verifikasi_email_otp_table.php`).

---

### Task 1: Migrations, models, and factories

**Files:**
- Create: `database/migrations/2026_07_14_091000_add_verifikasi_columns_to_dokumen_pendaftaran_table.php`
- Create: `database/migrations/2026_07_14_091100_create_hasil_seleksi_table.php`
- Create: `database/migrations/2026_07_14_091200_create_sk_ppdb_table.php`
- Create: `database/migrations/2026_07_14_091300_add_keputusan_dan_sk_columns_to_pendaftaran_table.php`
- Modify: `app/Models/DokumenPendaftaran.php`
- Modify: `app/Models/Pendaftaran.php`
- Create: `app/Models/HasilSeleksi.php`
- Create: `app/Models/SkPpdb.php`
- Modify: `app/Models/JenisTesMaster.php` (add `HasFactory`)
- Modify: `app/Models/SeleksiPpdb.php` (add `HasFactory`)
- Create: `database/factories/JenisTesMasterFactory.php`
- Create: `database/factories/SeleksiPpdbFactory.php`
- Create: `database/factories/HasilSeleksiFactory.php`
- Create: `database/factories/SkPpdbFactory.php`
- Test: `tests/Unit/M3VerifikasiDataLayerTest.php`

**Interfaces:**
- Consumes: `Pendaftaran`, `DokumenPendaftaran`, `SeleksiPpdb`, `GelombangPpdb`, `Lembaga`, `User` (all already exist).
- Produces: `HasilSeleksi` (fillable: `pendaftaran_id, seleksi_ppdb_id, nilai, catatan, dinilai_oleh_user_id, dinilai_pada`; relations `pendaftaran()`, `seleksiPpdb()`, `dinilaiOleh()`), `SkPpdb` (fillable: `gelombang_ppdb_id, lembaga_id, nomor_sk, tanggal_terbit, diterbitkan_oleh_user_id, file_path`; relations `gelombangPpdb()`, `lembaga()`, `diterbitkanOleh()`, `pendaftaran()` hasMany). `Pendaftaran` gains fillable `catatan_keputusan, ditetapkan_oleh_user_id, ditetapkan_pada, sk_ppdb_id` and relations `ditetapkanOleh()`, `skPpdb()`, `hasilSeleksi()` (hasMany). `DokumenPendaftaran` gains fillable `catatan_verifikasi, diverifikasi_oleh_user_id, diverifikasi_pada` and relation `diverifikasiOleh()`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/M3VerifikasiDataLayerTest.php`:

```php
<?php

use App\Models\CalonMurid;
use App\Models\DokumenPendaftaran;
use App\Models\DokumenSyaratPpdb;
use App\Models\GelombangPpdb;
use App\Models\HasilSeleksi;
use App\Models\JalurPpdb;
use App\Models\JenisTesMaster;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\SeleksiPpdb;
use App\Models\SkPpdb;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function buatKonteksM3Verifikasi(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => now()->subDay(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 40,
    ]);
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id]);
    $pendaftaran = Pendaftaran::create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-00001', 'email_pendaftaran' => 'wali@example.test', 'submitted_at' => now(),
    ]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    return [$lembaga, $gelombang, $jalur, $pendaftaran, $user];
}

it('records verification audit fields on a dokumen pendaftaran', function () {
    [$lembaga, $gelombang, $jalur, $pendaftaran, $user] = buatKonteksM3Verifikasi();
    $syarat = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    $dokumen = DokumenPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id, 'dokumen_syarat_ppdb_id' => $syarat->id,
        'file_path' => 'pendaftaran/1/akta.pdf', 'nama_file_asli' => 'akta.pdf',
        'mime_type' => 'application/pdf', 'ukuran_bytes' => 1000,
    ]);

    $dokumen->update([
        'status_verifikasi' => 'ditolak',
        'catatan_verifikasi' => 'Foto buram, tidak terbaca.',
        'diverifikasi_oleh_user_id' => $user->id,
        'diverifikasi_pada' => now(),
    ]);

    $dokumen->refresh();
    expect($dokumen->status_verifikasi)->toBe('ditolak');
    expect($dokumen->catatan_verifikasi)->toBe('Foto buram, tidak terbaca.');
    expect($dokumen->diverifikasiOleh->id)->toBe($user->id);
    expect($dokumen->diverifikasi_pada)->not->toBeNull();
});

it('records decision audit fields and an optional sk_ppdb link on a pendaftaran', function () {
    [$lembaga, $gelombang, $jalur, $pendaftaran, $user] = buatKonteksM3Verifikasi();

    $pendaftaran->update([
        'status' => 'diterima',
        'catatan_keputusan' => 'Nilai memenuhi syarat.',
        'ditetapkan_oleh_user_id' => $user->id,
        'ditetapkan_pada' => now(),
    ]);

    $pendaftaran->refresh();
    expect($pendaftaran->status)->toBe('diterima');
    expect($pendaftaran->ditetapkanOleh->id)->toBe($user->id);
    expect($pendaftaran->sk_ppdb_id)->toBeNull();

    $sk = SkPpdb::create([
        'gelombang_ppdb_id' => $gelombang->id, 'lembaga_id' => $lembaga->id,
        'nomor_sk' => '421.3/SK-PPDB.001/2026', 'tanggal_terbit' => now()->toDateString(),
        'diterbitkan_oleh_user_id' => $user->id, 'file_path' => 'sk/1/sk.pdf',
    ]);
    $pendaftaran->update(['sk_ppdb_id' => $sk->id]);

    expect($pendaftaran->fresh()->skPpdb->nomor_sk)->toBe('421.3/SK-PPDB.001/2026');
    expect($sk->pendaftaran)->toHaveCount(1);
    expect($sk->gelombangPpdb->id)->toBe($gelombang->id);
    expect($sk->diterbitkanOleh->id)->toBe($user->id);
});

it('records manual nilai per pendaftaran x seleksi_ppdb pair, one row per pair', function () {
    [$lembaga, $gelombang, $jalur, $pendaftaran, $user] = buatKonteksM3Verifikasi();
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);
    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id, 'jenis_tes_master_id' => $jenisTes->id,
        'jadwal' => now()->addWeek(), 'kriteria_kelulusan' => 'Nilai minimal 65', 'bobot' => 60,
    ]);

    $hasil = HasilSeleksi::updateOrCreate(
        ['pendaftaran_id' => $pendaftaran->id, 'seleksi_ppdb_id' => $seleksi->id],
        ['nilai' => 85.5, 'catatan' => 'Baik', 'dinilai_oleh_user_id' => $user->id, 'dinilai_pada' => now()]
    );

    expect(HasilSeleksi::count())->toBe(1);
    expect($hasil->pendaftaran->id)->toBe($pendaftaran->id);
    expect($hasil->seleksiPpdb->id)->toBe($seleksi->id);
    expect((float) $hasil->nilai)->toBe(85.5);

    // Re-entry via the same pair updates the existing row, never creates a second one.
    HasilSeleksi::updateOrCreate(
        ['pendaftaran_id' => $pendaftaran->id, 'seleksi_ppdb_id' => $seleksi->id],
        ['nilai' => 90, 'dinilai_oleh_user_id' => $user->id, 'dinilai_pada' => now()]
    );

    expect(HasilSeleksi::count())->toBe(1);
    expect((float) HasilSeleksi::first()->nilai)->toBe(90.0);
});

it('rejects a duplicate nilai row for the same pendaftaran x seleksi_ppdb pair inserted directly', function () {
    [$lembaga, $gelombang, $jalur, $pendaftaran, $user] = buatKonteksM3Verifikasi();
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);
    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id, 'jenis_tes_master_id' => $jenisTes->id,
        'jadwal' => now()->addWeek(), 'kriteria_kelulusan' => 'Nilai minimal 65', 'bobot' => 60,
    ]);
    HasilSeleksi::create(['pendaftaran_id' => $pendaftaran->id, 'seleksi_ppdb_id' => $seleksi->id, 'nilai' => 80]);

    expect(fn () => HasilSeleksi::create(['pendaftaran_id' => $pendaftaran->id, 'seleksi_ppdb_id' => $seleksi->id, 'nilai' => 70]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('rejects a duplicate nomor_sk for the same lembaga but allows it for a different lembaga', function () {
    [$lembagaA, $gelombangA] = buatKonteksM3Verifikasi();
    [$lembagaB, $gelombangB] = buatKonteksM3Verifikasi();
    $userA = User::factory()->create(['lembaga_id' => $lembagaA->id]);

    SkPpdb::create([
        'gelombang_ppdb_id' => $gelombangA->id, 'lembaga_id' => $lembagaA->id,
        'nomor_sk' => '421.3/SK-PPDB.001/2026', 'tanggal_terbit' => now()->toDateString(),
        'diterbitkan_oleh_user_id' => $userA->id, 'file_path' => 'sk/a/sk.pdf',
    ]);

    expect(fn () => SkPpdb::create([
        'gelombang_ppdb_id' => $gelombangA->id, 'lembaga_id' => $lembagaA->id,
        'nomor_sk' => '421.3/SK-PPDB.001/2026', 'tanggal_terbit' => now()->toDateString(),
        'diterbitkan_oleh_user_id' => $userA->id, 'file_path' => 'sk/a2/sk.pdf',
    ]))->toThrow(\Illuminate\Database\QueryException::class);

    $userB = User::factory()->create(['lembaga_id' => $lembagaB->id]);
    $skLembagaB = SkPpdb::create([
        'gelombang_ppdb_id' => $gelombangB->id, 'lembaga_id' => $lembagaB->id,
        'nomor_sk' => '421.3/SK-PPDB.001/2026', 'tanggal_terbit' => now()->toDateString(),
        'diterbitkan_oleh_user_id' => $userB->id, 'file_path' => 'sk/b/sk.pdf',
    ]);

    expect($skLembagaB->id)->not->toBeNull();
});

it('allows a gelombang to have more than one sk_ppdb over time', function () {
    [$lembaga, $gelombang, $jalur, $pendaftaran, $user] = buatKonteksM3Verifikasi();

    $skPertama = SkPpdb::create([
        'gelombang_ppdb_id' => $gelombang->id, 'lembaga_id' => $lembaga->id,
        'nomor_sk' => '421.3/SK-PPDB.001/2026', 'tanggal_terbit' => now()->toDateString(),
        'diterbitkan_oleh_user_id' => $user->id, 'file_path' => 'sk/1/sk.pdf',
    ]);
    $skSusulan = SkPpdb::create([
        'gelombang_ppdb_id' => $gelombang->id, 'lembaga_id' => $lembaga->id,
        'nomor_sk' => '421.3/SK-PPDB.002-SUSULAN/2026', 'tanggal_terbit' => now()->addWeek()->toDateString(),
        'diterbitkan_oleh_user_id' => $user->id, 'file_path' => 'sk/2/sk.pdf',
    ]);

    expect(SkPpdb::where('gelombang_ppdb_id', $gelombang->id)->count())->toBe(2);
    expect($skPertama->id)->not->toBe($skSusulan->id);
});
```

- [ ] **Step 2: Run the tests to confirm they fail**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Unit/M3VerifikasiDataLayerTest.php`
Expected: FAIL — columns/tables/models don't exist yet.

- [ ] **Step 3: Create the `dokumen_pendaftaran` verification columns migration**

Create `database/migrations/2026_07_14_091000_add_verifikasi_columns_to_dokumen_pendaftaran_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dokumen_pendaftaran', function (Blueprint $table) {
            $table->text('catatan_verifikasi')->nullable()->after('status_verifikasi');
            $table->foreignId('diverifikasi_oleh_user_id')->nullable()->after('catatan_verifikasi')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_pada')->nullable()->after('diverifikasi_oleh_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('dokumen_pendaftaran', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diverifikasi_oleh_user_id');
            $table->dropColumn(['catatan_verifikasi', 'diverifikasi_pada']);
        });
    }
};
```

- [ ] **Step 4: Create the `hasil_seleksi` table migration**

Create `database/migrations/2026_07_14_091100_create_hasil_seleksi_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hasil_seleksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_id')->constrained('pendaftaran')->cascadeOnDelete();
            $table->foreignId('seleksi_ppdb_id')->constrained('seleksi_ppdb')->cascadeOnDelete();
            $table->decimal('nilai', 5, 2)->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('dinilai_oleh_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dinilai_pada')->nullable();
            $table->timestamps();

            $table->unique(['pendaftaran_id', 'seleksi_ppdb_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hasil_seleksi');
    }
};
```

- [ ] **Step 5: Create the `sk_ppdb` table migration**

Create `database/migrations/2026_07_14_091200_create_sk_ppdb_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sk_ppdb', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gelombang_ppdb_id')->constrained('gelombang_ppdb')->cascadeOnDelete();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->string('nomor_sk');
            $table->date('tanggal_terbit');
            $table->foreignId('diterbitkan_oleh_user_id')->constrained('users');
            $table->string('file_path');
            $table->timestamps();

            $table->unique(['lembaga_id', 'nomor_sk']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sk_ppdb');
    }
};
```

- [ ] **Step 6: Create the `pendaftaran` keputusan + SK columns migration**

Create `database/migrations/2026_07_14_091300_add_keputusan_dan_sk_columns_to_pendaftaran_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pendaftaran', function (Blueprint $table) {
            $table->text('catatan_keputusan')->nullable()->after('status');
            $table->foreignId('ditetapkan_oleh_user_id')->nullable()->after('catatan_keputusan')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('ditetapkan_pada')->nullable()->after('ditetapkan_oleh_user_id');
            $table->foreignId('sk_ppdb_id')->nullable()->after('ditetapkan_pada')
                ->constrained('sk_ppdb')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pendaftaran', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sk_ppdb_id');
            $table->dropConstrainedForeignId('ditetapkan_oleh_user_id');
            $table->dropColumn(['catatan_keputusan', 'ditetapkan_pada']);
        });
    }
};
```

- [ ] **Step 7: Update `DokumenPendaftaran` model**

Modify `app/Models/DokumenPendaftaran.php` to this exact content:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DokumenPendaftaran extends Model
{
    protected $table = 'dokumen_pendaftaran';

    protected $fillable = [
        'pendaftaran_id',
        'dokumen_syarat_ppdb_id',
        'file_path',
        'nama_file_asli',
        'mime_type',
        'ukuran_bytes',
        'status_verifikasi',
        'catatan_verifikasi',
        'diverifikasi_oleh_user_id',
        'diverifikasi_pada',
    ];

    protected $attributes = [
        'status_verifikasi' => 'belum_diverifikasi',
    ];

    protected function casts(): array
    {
        return [
            'diverifikasi_pada' => 'datetime',
        ];
    }

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(Pendaftaran::class);
    }

    public function dokumenSyaratPpdb(): BelongsTo
    {
        return $this->belongsTo(DokumenSyaratPpdb::class);
    }

    public function diverifikasiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh_user_id');
    }
}
```

- [ ] **Step 8: Update `Pendaftaran` model**

Modify `app/Models/Pendaftaran.php` to this exact content:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pendaftaran extends Model
{
    use HasFactory;

    protected $table = 'pendaftaran';

    protected $attributes = [
        'status' => 'menunggu_verifikasi',
    ];

    protected $fillable = [
        'calon_murid_id',
        'lembaga_id',
        'tahun_ajaran_id',
        'jalur_ppdb_id',
        'gelombang_ppdb_id',
        'kode_pendaftaran',
        'email_pendaftaran',
        'status',
        'submitted_at',
        'catatan_keputusan',
        'ditetapkan_oleh_user_id',
        'ditetapkan_pada',
        'sk_ppdb_id',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'ditetapkan_pada' => 'datetime',
        ];
    }

    public function calonMurid(): BelongsTo
    {
        return $this->belongsTo(CalonMurid::class);
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function jalurPpdb(): BelongsTo
    {
        return $this->belongsTo(JalurPpdb::class);
    }

    public function gelombangPpdb(): BelongsTo
    {
        return $this->belongsTo(GelombangPpdb::class);
    }

    public function jawabanFormulir(): HasMany
    {
        return $this->hasMany(JawabanFormulirPendaftaran::class);
    }

    public function dokumen(): HasMany
    {
        return $this->hasMany(DokumenPendaftaran::class);
    }

    public function hasilSeleksi(): HasMany
    {
        return $this->hasMany(HasilSeleksi::class);
    }

    public function ditetapkanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditetapkan_oleh_user_id');
    }

    public function skPpdb(): BelongsTo
    {
        return $this->belongsTo(SkPpdb::class);
    }
}
```

- [ ] **Step 9: Create `HasilSeleksi` model**

Create `app/Models/HasilSeleksi.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HasilSeleksi extends Model
{
    use HasFactory;

    protected $table = 'hasil_seleksi';

    protected $fillable = [
        'pendaftaran_id',
        'seleksi_ppdb_id',
        'nilai',
        'catatan',
        'dinilai_oleh_user_id',
        'dinilai_pada',
    ];

    protected function casts(): array
    {
        return [
            'nilai' => 'decimal:2',
            'dinilai_pada' => 'datetime',
        ];
    }

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(Pendaftaran::class);
    }

    public function seleksiPpdb(): BelongsTo
    {
        return $this->belongsTo(SeleksiPpdb::class);
    }

    public function dinilaiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dinilai_oleh_user_id');
    }
}
```

- [ ] **Step 10: Create `SkPpdb` model**

Create `app/Models/SkPpdb.php`:

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkPpdb extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'sk_ppdb';

    protected $fillable = [
        'gelombang_ppdb_id',
        'lembaga_id',
        'nomor_sk',
        'tanggal_terbit',
        'diterbitkan_oleh_user_id',
        'file_path',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_terbit' => 'date',
        ];
    }

    public function gelombangPpdb(): BelongsTo
    {
        return $this->belongsTo(GelombangPpdb::class);
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function diterbitkanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diterbitkan_oleh_user_id');
    }

    public function pendaftaran(): HasMany
    {
        return $this->hasMany(Pendaftaran::class);
    }
}
```

- [ ] **Step 11: Create the missing `JenisTesMaster` and `SeleksiPpdb` factories**

Neither exists yet (verified: `database/factories/JenisTesMasterFactory.php` and `database/factories/SeleksiPpdbFactory.php` are both absent), and `HasilSeleksiFactory` (next step) needs `SeleksiPpdb::factory()` to work as a bare `SeleksiPpdb::factory()` call.

Create `database/factories/JenisTesMasterFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\JenisTesMaster;
use App\Models\Lembaga;
use Illuminate\Database\Eloquent\Factories\Factory;

class JenisTesMasterFactory extends Factory
{
    protected $model = JenisTesMaster::class;

    public function definition(): array
    {
        return [
            'lembaga_id' => Lembaga::factory(),
            'nama' => 'Tes '.$this->faker->unique()->word(),
            'deskripsi' => $this->faker->sentence(),
        ];
    }
}
```

Create `database/factories/SeleksiPpdbFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\JenisTesMaster;
use App\Models\SeleksiPpdb;
use Illuminate\Database\Eloquent\Factories\Factory;

class SeleksiPpdbFactory extends Factory
{
    protected $model = SeleksiPpdb::class;

    public function definition(): array
    {
        return [
            'jalur_ppdb_id' => JalurPpdb::factory(),
            'gelombang_ppdb_id' => GelombangPpdb::factory(),
            'jenis_tes_master_id' => JenisTesMaster::factory(),
            'jadwal' => now()->addWeek(),
            'kriteria_kelulusan' => 'Nilai minimal 65',
            'bobot' => 50,
        ];
    }
}
```

Also add `use Illuminate\Database\Eloquent\Factories\HasFactory;` and the `HasFactory` trait to `app/Models/JenisTesMaster.php` and `app/Models/SeleksiPpdb.php` (both currently lack it — check first; neither file has `use HasFactory` in its trait list today) — one import + one trait addition per file, matching exactly how the M2 data-layer plan added `HasFactory` to `TahunAjaran`/`JalurPpdb`/`GelombangPpdb` (minimal, touching nothing else in either file).

- [ ] **Step 12: Create the `HasilSeleksi` and `SkPpdb` factories**

Create `database/factories/HasilSeleksiFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\HasilSeleksi;
use App\Models\Pendaftaran;
use App\Models\SeleksiPpdb;
use Illuminate\Database\Eloquent\Factories\Factory;

class HasilSeleksiFactory extends Factory
{
    protected $model = HasilSeleksi::class;

    public function definition(): array
    {
        return [
            'pendaftaran_id' => Pendaftaran::factory(),
            'seleksi_ppdb_id' => SeleksiPpdb::factory(),
            'nilai' => $this->faker->randomFloat(2, 40, 100),
            'catatan' => null,
            'dinilai_oleh_user_id' => null,
            'dinilai_pada' => null,
        ];
    }
}
```

Create `database/factories/SkPpdbFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\GelombangPpdb;
use App\Models\Lembaga;
use App\Models\SkPpdb;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SkPpdbFactory extends Factory
{
    protected $model = SkPpdb::class;

    public function definition(): array
    {
        return [
            'gelombang_ppdb_id' => GelombangPpdb::factory(),
            'lembaga_id' => Lembaga::factory(),
            'nomor_sk' => '421.3/SK-PPDB.'.$this->faker->unique()->numberBetween(1, 999).'/2026',
            'tanggal_terbit' => now()->toDateString(),
            'diterbitkan_oleh_user_id' => User::factory(),
            'file_path' => 'sk/'.$this->faker->uuid().'.pdf',
        ];
    }
}
```

- [ ] **Step 13: Run the tests to confirm they pass**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Unit/M3VerifikasiDataLayerTest.php`
Expected: PASS (6 tests).

- [ ] **Step 14: Add `LogsActivity` to `Pendaftaran` and `DokumenPendaftaran`**

Modify `app/Models/Pendaftaran.php`: add `use Spatie\Activitylog\LogOptions;` and `use Spatie\Activitylog\Traits\LogsActivity;` to the imports, add `LogsActivity` to the `use HasFactory;` trait line (becomes `use HasFactory, LogsActivity;`), and add this method at the end of the class before the closing brace:

```php
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'catatan_keputusan', 'ditetapkan_oleh_user_id', 'sk_ppdb_id'])
            ->logOnlyDirty()
            ->useLogName('pendaftaran');
    }
```

Modify `app/Models/DokumenPendaftaran.php`: add the same two imports, change `class DokumenPendaftaran extends Model` to add `use LogsActivity;` as the first line inside the class body, and add:

```php
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status_verifikasi', 'catatan_verifikasi', 'diverifikasi_oleh_user_id'])
            ->logOnlyDirty()
            ->useLogName('dokumen_pendaftaran');
    }
```

Add this test to `tests/Unit/M3VerifikasiDataLayerTest.php` (append after the existing tests):

```php
it('logs activity when a pendaftaran decision changes', function () {
    [$lembaga, $gelombang, $jalur, $pendaftaran, $user] = buatKonteksM3Verifikasi();

    $pendaftaran->update(['status' => 'diterima', 'ditetapkan_oleh_user_id' => $user->id]);

    expect(\Spatie\Activitylog\Models\Activity::where('log_name', 'pendaftaran')->where('subject_id', $pendaftaran->id)->exists())->toBeTrue();
});

it('logs activity when a dokumen pendaftaran verification status changes', function () {
    [$lembaga, $gelombang, $jalur, $pendaftaran, $user] = buatKonteksM3Verifikasi();
    $syarat = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    $dokumen = DokumenPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id, 'dokumen_syarat_ppdb_id' => $syarat->id,
        'file_path' => 'pendaftaran/1/akta.pdf', 'nama_file_asli' => 'akta.pdf',
        'mime_type' => 'application/pdf', 'ukuran_bytes' => 1000,
    ]);

    $dokumen->update(['status_verifikasi' => 'diterima', 'diverifikasi_oleh_user_id' => $user->id]);

    expect(\Spatie\Activitylog\Models\Activity::where('log_name', 'dokumen_pendaftaran')->where('subject_id', $dokumen->id)->exists())->toBeTrue();
});
```

- [ ] **Step 15: Run the tests to confirm they pass**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Unit/M3VerifikasiDataLayerTest.php`
Expected: PASS (8 tests).

- [ ] **Step 16: Run the full suite and commit**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test`
Expected: 226 (M2 baseline) + 8 (new) = 234 passed, 0 failures.

```bash
git add database/migrations/2026_07_14_091000_add_verifikasi_columns_to_dokumen_pendaftaran_table.php database/migrations/2026_07_14_091100_create_hasil_seleksi_table.php database/migrations/2026_07_14_091200_create_sk_ppdb_table.php database/migrations/2026_07_14_091300_add_keputusan_dan_sk_columns_to_pendaftaran_table.php app/Models/DokumenPendaftaran.php app/Models/Pendaftaran.php app/Models/HasilSeleksi.php app/Models/SkPpdb.php app/Models/JenisTesMaster.php app/Models/SeleksiPpdb.php database/factories/JenisTesMasterFactory.php database/factories/SeleksiPpdbFactory.php database/factories/HasilSeleksiFactory.php database/factories/SkPpdbFactory.php tests/Unit/M3VerifikasiDataLayerTest.php
git commit -m "feat: add M3 data layer — dokumen/pendaftaran audit columns, hasil_seleksi, sk_ppdb"
```

---

### Task 2: Dynamic RBAC permissions for verification & decisions

**Files:**
- Modify: `app/Services/PermissionCatalog.php`
- Modify: `database/seeders/RolePermissionSeeder.php`
- Modify: `tests/Feature/RolePermissionSeederTest.php`

**Interfaces:**
- Consumes: existing `PermissionCatalog::MODULE_LABELS`/`ACTION_LABELS` structure, existing `RolePermissionSeeder` role/permission arrays.
- Produces: 5 new permissions (`spmb-pendaftaran.view`, `spmb-pendaftaran.verifikasi-dokumen`, `spmb-pendaftaran.nilai-seleksi`, `spmb-pendaftaran.tetapkan-keputusan`, `spmb-pendaftaran.terbitkan-sk`), automatically visible in the existing Roles page permission matrix (Phase B UI, unchanged) since it already reads `PermissionCatalog::grouped()`. The next plan's controllers will call `$this->authorize('spmb-pendaftaran.xxx')` exactly like `GelombangPpdbController` already does for its own module.

- [ ] **Step 1: Write the failing test**

Modify `tests/Feature/RolePermissionSeederTest.php` — replace the whole file with this exact content (extends every existing test's expected list/count, and adds two new tests for the M3-specific defaults):

```php
<?php

use App\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Permission;

it('seeds the initial permissions', function () {
    (new RolePermissionSeeder())->run();

    $expected = [
        'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
        'users.view', 'users.create', 'users.edit', 'users.toggle-active',
        'lembaga.view', 'lembaga.create', 'lembaga.edit',
        'guru.view', 'guru.create', 'guru.edit',
        'tahun-ajaran.view', 'tahun-ajaran.create', 'tahun-ajaran.activate',
        'semester.create', 'semester.activate',
        'jenis-tes.view', 'jenis-tes.create', 'jenis-tes.delete',
        'gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit',
        'jalur-ppdb.view', 'jalur-ppdb.create', 'jalur-ppdb.edit',
        'formulir-field.create', 'formulir-field.delete',
        'dokumen-syarat.create', 'dokumen-syarat.delete',
        'seleksi.create', 'seleksi.delete',
        'spmb-konfigurasi.duplikasi',
        'audit-log.view',
        'spmb-pendaftaran.view', 'spmb-pendaftaran.verifikasi-dokumen', 'spmb-pendaftaran.nilai-seleksi',
        'spmb-pendaftaran.tetapkan-keputusan', 'spmb-pendaftaran.terbitkan-sk',
    ];

    foreach ($expected as $name) {
        expect(Permission::where('name', $name)->exists())->toBeTrue();
    }

    expect(Permission::count())->toBe(41);
});

it('seeds the initial roles with correct scope and protection', function () {
    (new RolePermissionSeeder())->run();

    $superAdmin = Role::where('name', 'yayasan_super_admin')->first();
    expect($superAdmin->scope_level)->toBe('yayasan');
    expect($superAdmin->is_protected)->toBeTrue();
    expect($superAdmin->permissions()->count())->toBe(41);

    expect(Role::where('name', 'kepala_sekolah')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'admin_administrasi')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'admin_keuangan')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'guru')->first()->scope_level)->toBe('diri_sendiri');
});

it('gives admin_administrasi the SPMB-related granular permissions by default', function () {
    (new RolePermissionSeeder())->run();

    $adminAdministrasi = Role::where('name', 'admin_administrasi')->first();
    $expected = [
        'jenis-tes.view', 'jenis-tes.create', 'jenis-tes.delete',
        'gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit',
        'jalur-ppdb.view', 'jalur-ppdb.create', 'jalur-ppdb.edit',
        'formulir-field.create', 'formulir-field.delete',
        'dokumen-syarat.create', 'dokumen-syarat.delete',
        'seleksi.create', 'seleksi.delete',
        'spmb-konfigurasi.duplikasi',
        'spmb-pendaftaran.view', 'spmb-pendaftaran.verifikasi-dokumen', 'spmb-pendaftaran.nilai-seleksi',
    ];

    foreach ($expected as $name) {
        expect($adminAdministrasi->hasPermissionTo($name))->toBeTrue();
    }
    expect($adminAdministrasi->permissions()->count())->toBe(19);
});

it('gives kepala_sekolah all five spmb-pendaftaran permissions by default, including tetapkan-keputusan and terbitkan-sk', function () {
    (new RolePermissionSeeder())->run();

    $kepalaSekolah = Role::where('name', 'kepala_sekolah')->first();
    $expected = [
        'spmb-pendaftaran.view', 'spmb-pendaftaran.verifikasi-dokumen', 'spmb-pendaftaran.nilai-seleksi',
        'spmb-pendaftaran.tetapkan-keputusan', 'spmb-pendaftaran.terbitkan-sk',
    ];

    foreach ($expected as $name) {
        expect($kepalaSekolah->hasPermissionTo($name))->toBeTrue();
    }
    expect($kepalaSekolah->permissions()->count())->toBe(5);
});

it('is idempotent when run twice', function () {
    (new RolePermissionSeeder())->run();
    (new RolePermissionSeeder())->run();

    expect(Role::count())->toBe(5);
    expect(Permission::count())->toBe(41);
});

it('removes orphaned old flat permission rows on re-seed', function () {
    Permission::firstOrCreate(['name' => 'manage-guru', 'guard_name' => 'web']);

    (new RolePermissionSeeder())->run();

    expect(Permission::where('name', 'manage-guru')->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run the test to confirm it fails**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Feature/RolePermissionSeederTest.php`
Expected: FAIL — new permissions/kepala_sekolah defaults don't exist yet.

- [ ] **Step 3: Update `PermissionCatalog`**

Modify `app/Services/PermissionCatalog.php` — add one entry to `MODULE_LABELS` and four entries to `ACTION_LABELS`:

```php
    private const MODULE_LABELS = [
        'roles' => 'Roles',
        'users' => 'Pengguna',
        'lembaga' => 'Lembaga',
        'guru' => 'Guru',
        'tahun-ajaran' => 'Tahun Ajaran',
        'semester' => 'Semester',
        'jenis-tes' => 'Jenis Tes',
        'gelombang-ppdb' => 'Gelombang PPDB',
        'jalur-ppdb' => 'Jalur PPDB',
        'formulir-field' => 'Formulir Field',
        'dokumen-syarat' => 'Dokumen Syarat',
        'seleksi' => 'Seleksi',
        'spmb-konfigurasi' => 'Konfigurasi SPMB',
        'spmb-pendaftaran' => 'Verifikasi & Keputusan SPMB',
        'audit-log' => 'Log Aktivitas',
    ];

    private const ACTION_LABELS = [
        'view' => 'Lihat',
        'create' => 'Tambah',
        'edit' => 'Ubah',
        'delete' => 'Hapus',
        'activate' => 'Aktifkan',
        'toggle-active' => 'Aktif/Nonaktifkan',
        'duplikasi' => 'Duplikasi',
        'verifikasi-dokumen' => 'Verifikasi Dokumen',
        'nilai-seleksi' => 'Input Nilai',
        'tetapkan-keputusan' => 'Tetapkan Keputusan',
        'terbitkan-sk' => 'Terbitkan SK',
    ];
```

- [ ] **Step 4: Update `RolePermissionSeeder`**

Modify `database/seeders/RolePermissionSeeder.php` to this exact content:

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
        Permission::whereIn('name', [
            'manage-roles', 'manage-users', 'manage-yayasan',
            'manage-lembaga', 'manage-tahun-ajaran', 'manage-guru',
            'view-audit-log', 'manage-ppdb',
        ])->delete();

        $permissions = [
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
            'users.view', 'users.create', 'users.edit', 'users.toggle-active',
            'lembaga.view', 'lembaga.create', 'lembaga.edit',
            'guru.view', 'guru.create', 'guru.edit',
            'tahun-ajaran.view', 'tahun-ajaran.create', 'tahun-ajaran.activate',
            'semester.create', 'semester.activate',
            'jenis-tes.view', 'jenis-tes.create', 'jenis-tes.delete',
            'gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit',
            'jalur-ppdb.view', 'jalur-ppdb.create', 'jalur-ppdb.edit',
            'formulir-field.create', 'formulir-field.delete',
            'dokumen-syarat.create', 'dokumen-syarat.delete',
            'seleksi.create', 'seleksi.delete',
            'spmb-konfigurasi.duplikasi',
            'audit-log.view',
            'spmb-pendaftaran.view', 'spmb-pendaftaran.verifikasi-dokumen', 'spmb-pendaftaran.nilai-seleksi',
            'spmb-pendaftaran.tetapkan-keputusan', 'spmb-pendaftaran.terbitkan-sk',
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
                $role->givePermissionTo([
                    'jenis-tes.view', 'jenis-tes.create', 'jenis-tes.delete',
                    'gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit',
                    'jalur-ppdb.view', 'jalur-ppdb.create', 'jalur-ppdb.edit',
                    'formulir-field.create', 'formulir-field.delete',
                    'dokumen-syarat.create', 'dokumen-syarat.delete',
                    'seleksi.create', 'seleksi.delete',
                    'spmb-konfigurasi.duplikasi',
                    'spmb-pendaftaran.view', 'spmb-pendaftaran.verifikasi-dokumen', 'spmb-pendaftaran.nilai-seleksi',
                ]);
            }

            if ($name === 'kepala_sekolah') {
                $role->givePermissionTo([
                    'spmb-pendaftaran.view', 'spmb-pendaftaran.verifikasi-dokumen', 'spmb-pendaftaran.nilai-seleksi',
                    'spmb-pendaftaran.tetapkan-keputusan', 'spmb-pendaftaran.terbitkan-sk',
                ]);
            }
        }
    }
}
```

- [ ] **Step 5: Run the test to confirm it passes**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Feature/RolePermissionSeederTest.php`
Expected: PASS (6 tests).

- [ ] **Step 6: Run the full suite and commit**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test`
Expected: 235 passed, 0 failures. (234 after Task 1, plus a net +1 from `RolePermissionSeederTest.php`: the file is rewritten from 5 tests to 6 — the 5 original tests still exist with extended assertions, plus one brand-new test for `kepala_sekolah`'s defaults — so the net change is +1 test, not 0.)

```bash
git add app/Services/PermissionCatalog.php database/seeders/RolePermissionSeeder.php tests/Feature/RolePermissionSeederTest.php
git commit -m "feat: add spmb-pendaftaran granular permissions with kepala_sekolah/admin_administrasi defaults"
```

---

## Post-Plan Note

This plan is intentionally scoped to data + permissions only, mirroring M2's Plan 1/Plan 2 split. The follow-up plan (not yet written) covers: the `spmb-pendaftaran` admin index (server-side datatable, mirroring `RoleController`/`roles-table.js`/`admin.roles.index` exactly per the user's explicit instruction), the detail page (dokumen verification + nilai entry + keputusan, all AJAX+toast like the Roles edit page), the mass nilai-entry page, SK PDF generation (`barryvdh/laravel-dompdf`, already installed), the M2 public-page integration (`bukti-pendaftaran.blade.php` SK reference line), and — per the user's explicit request — a **dummy-data seeding task at the end of that plan** so the whole M3 feature is immediately manually-testable after implementation without any further ad-hoc setup requests: realistic `Pendaftaran` rows across the existing SMP/SMA demo lembaga in a spread of states (some with all documents verified, some with one rejected, some with `hasil_seleksi` entered, some already `diterima`/`ditolak`, and at least one `sk_ppdb` already issued covering a batch of decided registrations).
