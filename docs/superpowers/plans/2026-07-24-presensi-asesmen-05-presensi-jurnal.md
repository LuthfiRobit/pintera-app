# Tahap 5 — Sesi Pembelajaran, Presensi & Jurnal Guru Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `SesiPembelajaran` (one row per class-session carrying the teaching journal) and `Presensi` (one row per student per session), a generator that turns `JadwalPelajaran` + `KalenderAkademikResolver` into sesi rows for a given date, and the guru-facing screen to fill jurnal + mark attendance — scoped so a guru only ever sees sessions where they are the assigned `guru_id`.

**Architecture:** Four slices: (1) `StatusSesiPembelajaran`/`StatusPresensi` enums, (2) `SesiPembelajaran` + `Presensi` migrations/models/factories, (3) `SesiPembelajaranGenerator` service consuming Tahap 3's `KalenderAkademikResolver` and Tahap 4's `JadwalPelajaran`/`JamPelajaran`, (4) the guru-facing controller/views (data-scoped to the logged-in guru's own `Guru::$id`, not just permission-gated).

**Tech Stack:** Laravel 12, Blade, Pest 4.

## Global Constraints

- Same conventions as Tahap 1-4 (`casts()` method style, inline validation, `AuthorizesRequests`, Blade tokens, `permissions:sync`).
- **Data scoping, not just RBAC**: the guru-facing controller in Task 4 filters every query by `sesi_pembelajaran.guru_id === $request->user()->guru->id` — a user having the `presensi.isi` permission is not sufficient by itself; they must also own the session. This mirrors the two-layer access model from the design spec Section 7.2.
- `Presensi` rows are auto-created with `status = 'hadir'` when a `SesiPembelajaran` is generated (the guru only needs to change exceptions, not mark every student individually) — this default must be applied in the generator, not left null.
- The generator is idempotent: calling it twice for the same `(jadwal_pelajaran_id, tanggal)` must not create duplicate `SesiPembelajaran` rows (`firstOrCreate` keyed on those two columns).
- `Hari` (Tahap 4 Task 1) uses Indonesian day-name string values (`senin`..`minggu`); Carbon's `->dayOfWeek` is numeric with `0 = Sunday`. Task 3 adds a `Hari::fromCarbonDayOfWeek(int): self` mapping method — do not compare `Hari` values to raw Carbon day numbers anywhere else.

---

### Task 1: `StatusSesiPembelajaran` and `StatusPresensi` enums

**Files:**
- Create: `app/Enums/StatusSesiPembelajaran.php`
- Create: `app/Enums/StatusPresensi.php`
- Test: `tests/Unit/Enums/PresensiEnumsTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `App\Enums\StatusSesiPembelajaran` (`Terlaksana = 'terlaksana'`, `Diganti = 'diganti'`, `Kosong = 'kosong'`), `App\Enums\StatusPresensi` (`Hadir = 'hadir'`, `Izin = 'izin'`, `Sakit = 'sakit'`, `Alpa = 'alpa'`, `Terlambat = 'terlambat'`). Task 2's models cast to these.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Enums/PresensiEnumsTest.php`:

```php
<?php

use App\Enums\StatusPresensi;
use App\Enums\StatusSesiPembelajaran;

it('defines the expected StatusSesiPembelajaran cases', function () {
    expect(array_column(StatusSesiPembelajaran::cases(), 'value'))
        ->toBe(['terlaksana', 'diganti', 'kosong']);
});

it('defines the expected StatusPresensi cases', function () {
    expect(array_column(StatusPresensi::cases(), 'value'))
        ->toBe(['hadir', 'izin', 'sakit', 'alpa', 'terlambat']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Enums/PresensiEnumsTest.php`
Expected: FAIL with `Class "App\Enums\StatusSesiPembelajaran" not found`

- [ ] **Step 3: Create the enums**

Create `app/Enums/StatusSesiPembelajaran.php`:

```php
<?php

namespace App\Enums;

enum StatusSesiPembelajaran: string
{
    case Terlaksana = 'terlaksana';
    case Diganti = 'diganti';
    case Kosong = 'kosong';

    public function label(): string
    {
        return match ($this) {
            self::Terlaksana => 'Terlaksana',
            self::Diganti => 'Diganti',
            self::Kosong => 'Kosong',
        };
    }
}
```

Create `app/Enums/StatusPresensi.php`:

```php
<?php

namespace App\Enums;

enum StatusPresensi: string
{
    case Hadir = 'hadir';
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Alpa = 'alpa';
    case Terlambat = 'terlambat';

    public function label(): string
    {
        return match ($this) {
            self::Hadir => 'Hadir',
            self::Izin => 'Izin',
            self::Sakit => 'Sakit',
            self::Alpa => 'Alpa',
            self::Terlambat => 'Terlambat',
        };
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Enums/PresensiEnumsTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Enums/StatusSesiPembelajaran.php app/Enums/StatusPresensi.php tests/Unit/Enums/PresensiEnumsTest.php
git commit -m "feat: add StatusSesiPembelajaran and StatusPresensi enums"
```

---

### Task 2: `SesiPembelajaran` and `Presensi` migrations, models, factories

**Files:**
- Create: `database/migrations/2026_07_25_120000_create_sesi_pembelajaran_table.php`
- Create: `database/migrations/2026_07_25_120100_create_presensi_table.php`
- Create: `app/Models/SesiPembelajaran.php`
- Create: `app/Models/Presensi.php`
- Create: `database/factories/SesiPembelajaranFactory.php`
- Create: `database/factories/PresensiFactory.php`
- Test: `tests/Unit/Models/SesiPembelajaranTest.php`
- Test: `tests/Unit/Models/PresensiTest.php`

**Interfaces:**
- Consumes: `App\Models\JadwalPelajaran` (Tahap 4), `App\Models\Kelas`, `App\Models\Guru`, `App\Models\MataPelajaran`, `App\Models\Siswa`, both enums from Task 1.
- Produces: `App\Models\SesiPembelajaran` (`$fillable = ['jadwal_pelajaran_id', 'kelas_id', 'guru_id', 'mata_pelajaran_id', 'tanggal', 'jam_mulai', 'jam_selesai', 'materi', 'status']`, `presensi(): HasMany`), `App\Models\Presensi` (`$fillable = ['sesi_pembelajaran_id', 'siswa_id', 'status', 'keterangan']`). Task 3's generator and Task 4's guru UI both operate on these.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Models/SesiPembelajaranTest.php`:

```php
<?php

use App\Enums\StatusSesiPembelajaran;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\SesiPembelajaran;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

it('casts status to the enum and allows a null jadwal_pelajaran_id for ad-hoc sessions like PKL', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $sesi = SesiPembelajaran::create([
        'jadwal_pelajaran_id' => null,
        'kelas_id' => $kelas->id,
        'guru_id' => $guru->id,
        'tanggal' => '2026-08-19',
        'jam_mulai' => '08:00',
        'jam_selesai' => '15:00',
        'materi' => 'PKL hari ke-1',
        'status' => StatusSesiPembelajaran::Terlaksana->value,
    ]);

    expect($sesi->fresh()->status)->toBe(StatusSesiPembelajaran::Terlaksana);
    expect($sesi->fresh()->jadwal_pelajaran_id)->toBeNull();
    expect($sesi->fresh()->guru->id)->toBe($guru->id);
});
```

Create `tests/Unit/Models/PresensiTest.php`:

```php
<?php

use App\Enums\StatusPresensi;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Presensi;
use App\Models\SesiPembelajaran;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

it('belongs to a sesi pembelajaran and a siswa, casting status to the enum', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $sesi = SesiPembelajaran::create([
        'kelas_id' => $kelas->id, 'guru_id' => $guru->id, 'tanggal' => '2026-08-19',
        'jam_mulai' => '07:00', 'jam_selesai' => '07:35', 'status' => 'terlaksana',
    ]);

    $presensi = Presensi::create([
        'sesi_pembelajaran_id' => $sesi->id,
        'siswa_id' => $siswa->id,
        'status' => StatusPresensi::Hadir->value,
    ]);

    expect($presensi->fresh()->sesiPembelajaran->id)->toBe($sesi->id);
    expect($presensi->fresh()->siswa->id)->toBe($siswa->id);
    expect($presensi->fresh()->status)->toBe(StatusPresensi::Hadir);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Models/SesiPembelajaranTest.php tests/Unit/Models/PresensiTest.php`
Expected: FAIL with `Class "App\Models\SesiPembelajaran" not found`

- [ ] **Step 3: Create the migrations**

Create `database/migrations/2026_07_25_120000_create_sesi_pembelajaran_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesi_pembelajaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jadwal_pelajaran_id')->nullable()->constrained('jadwal_pelajaran')->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('guru_id')->constrained('guru')->cascadeOnDelete();
            $table->foreignId('mata_pelajaran_id')->nullable()->constrained('mata_pelajaran')->nullOnDelete();
            $table->date('tanggal');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->text('materi')->nullable();
            $table->enum('status', ['terlaksana', 'diganti', 'kosong'])->default('terlaksana');
            $table->timestamps();

            $table->unique(['jadwal_pelajaran_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesi_pembelajaran');
    }
};
```

Create `database/migrations/2026_07_25_120100_create_presensi_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesi_pembelajaran_id')->constrained('sesi_pembelajaran')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->enum('status', ['hadir', 'izin', 'sakit', 'alpa', 'terlambat'])->default('hadir');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['sesi_pembelajaran_id', 'siswa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presensi');
    }
};
```

Run: `php artisan migrate`
Expected: both tables created without error.

- [ ] **Step 4: Create the models**

Create `app/Models/SesiPembelajaran.php`:

```php
<?php

namespace App\Models;

use App\Enums\StatusSesiPembelajaran;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SesiPembelajaran extends Model
{
    use HasFactory;

    protected $table = 'sesi_pembelajaran';

    protected $fillable = [
        'jadwal_pelajaran_id', 'kelas_id', 'guru_id', 'mata_pelajaran_id',
        'tanggal', 'jam_mulai', 'jam_selesai', 'materi', 'status',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'status' => StatusSesiPembelajaran::class,
        ];
    }

    public function jadwalPelajaran(): BelongsTo
    {
        return $this->belongsTo(JadwalPelajaran::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function presensi(): HasMany
    {
        return $this->hasMany(Presensi::class);
    }
}
```

Create `app/Models/Presensi.php`:

```php
<?php

namespace App\Models;

use App\Enums\StatusPresensi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presensi extends Model
{
    use HasFactory;

    protected $table = 'presensi';

    protected $fillable = ['sesi_pembelajaran_id', 'siswa_id', 'status', 'keterangan'];

    protected function casts(): array
    {
        return [
            'status' => StatusPresensi::class,
        ];
    }

    public function sesiPembelajaran(): BelongsTo
    {
        return $this->belongsTo(SesiPembelajaran::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }
}
```

- [ ] **Step 5: Create the factories**

Create `database/factories/SesiPembelajaranFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\SesiPembelajaran;
use Illuminate\Database\Eloquent\Factories\Factory;

class SesiPembelajaranFactory extends Factory
{
    protected $model = SesiPembelajaran::class;

    public function definition(): array
    {
        return [
            'jadwal_pelajaran_id' => null,
            'kelas_id' => Kelas::factory(),
            'guru_id' => Guru::factory(),
            'mata_pelajaran_id' => null,
            'tanggal' => now()->format('Y-m-d'),
            'jam_mulai' => '07:00',
            'jam_selesai' => '07:35',
            'materi' => null,
            'status' => 'terlaksana',
        ];
    }
}
```

Create `database/factories/PresensiFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Presensi;
use App\Models\SesiPembelajaran;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

class PresensiFactory extends Factory
{
    protected $model = Presensi::class;

    public function definition(): array
    {
        return [
            'sesi_pembelajaran_id' => SesiPembelajaran::factory(),
            'siswa_id' => Siswa::factory(),
            'status' => 'hadir',
            'keterangan' => null,
        ];
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Unit/Models/SesiPembelajaranTest.php tests/Unit/Models/PresensiTest.php`
Expected: PASS (2 tests)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_25_120000_create_sesi_pembelajaran_table.php database/migrations/2026_07_25_120100_create_presensi_table.php app/Models/SesiPembelajaran.php app/Models/Presensi.php database/factories/SesiPembelajaranFactory.php database/factories/PresensiFactory.php tests/Unit/Models/SesiPembelajaranTest.php tests/Unit/Models/PresensiTest.php
git commit -m "feat: add SesiPembelajaran and Presensi migrations, models, factories"
```

---

### Task 3: `SesiPembelajaranGenerator` service

**Files:**
- Modify: `app/Enums/Hari.php`
- Create: `app/Services/SesiPembelajaranGenerator.php`
- Test: `tests/Unit/Services/SesiPembelajaranGeneratorTest.php`

**Interfaces:**
- Consumes: `App\Services\KalenderAkademikResolver` (Tahap 3), `App\Models\JadwalPelajaran`/`JamPelajaran` (Tahap 4), `App\Models\Kelas`, `App\Models\Siswa`.
- Produces: `SesiPembelajaranGenerator::generateUntukTanggal(Kelas $kelas, CarbonInterface $tanggal, int $semesterId): Collection<SesiPembelajaran>`. Task 4's guru UI (or a future scheduled command, out of scope here) calls this to materialize sessions.

**Design note — block-merging (per design spec Section 4.1a, added after the original spec was written):** real teaching practice assigns the same `mata_pelajaran_id` + `guru_id` to 2+ **consecutive** `jam_pelajaran` slots for a class (a "double period"). This must produce **one** `SesiPembelajaran` for the whole block, not one per slot — confirmed with the user that schools don't need per-jam attendance granularity within such a block. The generator must group same-day `JadwalPelajaran` rows into blocks (consecutive `jam_pelajaran.urutan`, identical `mata_pelajaran_id` and `guru_id`) before creating sessions — see Step 4's rewritten algorithm. This does **not** change the migration's `unique(['jadwal_pelajaran_id', 'tanggal'])` constraint from Task 2: `jadwal_pelajaran_id` on a merged `SesiPembelajaran` row points at the block's **first** slot, and because block-detection is deterministic (same `JadwalPelajaran` data in, same block boundaries out), that first slot's id remains a stable, valid idempotency key across repeated generator calls — no schema change needed.

- [ ] **Step 1: Add the Carbon day-of-week mapping to `Hari`**

Open `app/Enums/Hari.php` and add this method inside the enum (alongside the existing `label()` method):

```php
public static function fromCarbonDayOfWeek(int $dayOfWeek): self
{
    return match ($dayOfWeek) {
        1 => self::Senin,
        2 => self::Selasa,
        3 => self::Rabu,
        4 => self::Kamis,
        5 => self::Jumat,
        6 => self::Sabtu,
        0 => self::Minggu,
        default => throw new \ValueError("Invalid Carbon day of week: {$dayOfWeek}"),
    };
}
```

- [ ] **Step 2: Write the failing test**

Create `tests/Unit/Services/SesiPembelajaranGeneratorTest.php`:

```php
<?php

use App\Enums\Hari;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\PolaJam;
use App\Models\Semester;
use App\Models\SesiPembelajaran;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use App\Services\SesiPembelajaranGenerator;
use Carbon\Carbon;

function siapkanKelasDenganJadwal(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0]]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'is_pelajaran' => true]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jadwal = JadwalPelajaran::create([
        'kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $guru->id, 'semester_id' => $semester->id,
    ]);
    Siswa::factory()->count(3)->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    return compact('kelas', 'jadwal', 'semester');
}

it('creates a sesi pembelajaran for each jadwal matching the date\'s day of week', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasDenganJadwal();

    $hasil = (new SesiPembelajaranGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id); // a Wednesday

    expect($hasil)->toHaveCount(1);
    expect(SesiPembelajaran::where('kelas_id', $kelas->id)->count())->toBe(1);
});

it('auto-creates a hadir presensi row for every siswa in the kelas', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasDenganJadwal();

    $hasil = (new SesiPembelajaranGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id);

    expect($hasil->first()->presensi()->count())->toBe(3);
    expect($hasil->first()->presensi()->first()->status->value)->toBe('hadir');
});

it('does not generate a sesi on a day the kalender resolver marks as libur', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasDenganJadwal();

    $hasil = (new SesiPembelajaranGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-16'), $semester->id); // a Sunday, weekly off-day

    expect($hasil)->toHaveCount(0);
    expect(SesiPembelajaran::where('kelas_id', $kelas->id)->count())->toBe(0);
});

it('is idempotent: calling it twice for the same date does not duplicate the sesi', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasDenganJadwal();

    (new SesiPembelajaranGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id);
    (new SesiPembelajaranGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id);

    expect(SesiPembelajaran::where('kelas_id', $kelas->id)->count())->toBe(1);
});

it('merges 2 consecutive jam pelajaran with the same mapel and guru into one sesi spanning the whole block', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0]]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jamSatu = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'urutan' => 1, 'is_pelajaran' => true, 'jam_mulai' => '07:35', 'jam_selesai' => '08:10']);
    $jamDua = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'urutan' => 2, 'is_pelajaran' => true, 'jam_mulai' => '08:10', 'jam_selesai' => '09:00']);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamSatu->id, 'mata_pelajaran_id' => $mapel->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);
    JadwalPelajaran::create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamDua->id, 'mata_pelajaran_id' => $mapel->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);
    Siswa::factory()->count(2)->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    $hasil = (new SesiPembelajaranGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id);

    expect($hasil)->toHaveCount(1);
    expect($hasil->first()->jam_mulai)->toBe('07:35:00');
    expect($hasil->first()->jam_selesai)->toBe('09:00:00');
    expect($hasil->first()->presensi()->count())->toBe(2);
});

it('does not merge consecutive slots when the mata pelajaran differs', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0]]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jamSatu = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'urutan' => 1, 'is_pelajaran' => true]);
    $jamDua = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'urutan' => 2, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $mapelSatu = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $mapelDua = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamSatu->id, 'mata_pelajaran_id' => $mapelSatu->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);
    JadwalPelajaran::create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamDua->id, 'mata_pelajaran_id' => $mapelDua->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);

    $hasil = (new SesiPembelajaranGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id);

    expect($hasil)->toHaveCount(2);
});

it('does not merge slots with the same mapel and guru if they are not adjacent (a different slot sits between them)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0]]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jamSatu = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'urutan' => 1, 'is_pelajaran' => true]);
    $jamDua = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'urutan' => 2, 'is_pelajaran' => true]);
    $jamTiga = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'urutan' => 3, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $mapelA = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $mapelLain = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamSatu->id, 'mata_pelajaran_id' => $mapelA->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);
    JadwalPelajaran::create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamDua->id, 'mata_pelajaran_id' => $mapelLain->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);
    JadwalPelajaran::create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamTiga->id, 'mata_pelajaran_id' => $mapelA->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);

    $hasil = (new SesiPembelajaranGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id);

    // 3 separate sesi: mapelA alone, mapelLain alone, mapelA alone again — urutan 1 and 3
    // share mata_pelajaran_id/guru_id but are not consecutive, so they must NOT merge.
    expect($hasil)->toHaveCount(3);
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/SesiPembelajaranGeneratorTest.php`
Expected: FAIL with `Class "App\Services\SesiPembelajaranGenerator" not found`

- [ ] **Step 4: Create the service**

Create `app/Services/SesiPembelajaranGenerator.php`:

```php
<?php

namespace App\Services;

use App\Enums\Hari;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Presensi;
use App\Models\SesiPembelajaran;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class SesiPembelajaranGenerator
{
    /**
     * @return Collection<int, SesiPembelajaran>
     */
    public function generateUntukTanggal(Kelas $kelas, CarbonInterface $tanggal, int $semesterId): Collection
    {
        $resolusi = (new KalenderAkademikResolver)->resolve($kelas->lembaga, $tanggal);

        if ($resolusi['libur'] || $kelas->pola_jam_id === null) {
            return collect();
        }

        $hari = Hari::fromCarbonDayOfWeek($tanggal->dayOfWeek);

        $jadwalHariIni = JadwalPelajaran::where('kelas_id', $kelas->id)
            ->where('semester_id', $semesterId)
            ->whereHas('jamPelajaran', fn ($q) => $q->where('pola_jam_id', $kelas->pola_jam_id)->where('hari', $hari->value))
            ->with('jamPelajaran')
            ->get()
            ->sortBy(fn (JadwalPelajaran $jadwal) => $jadwal->jamPelajaran->urutan)
            ->values();

        return $this->kelompokkanJadiBlok($jadwalHariIni)
            ->map(fn (Collection $blok) => $this->buatSesi($kelas, $blok, $tanggal));
    }

    /**
     * Groups same-day JadwalPelajaran rows (already sorted by jam_pelajaran.urutan) into
     * blocks of consecutive slots sharing the same mata_pelajaran_id and guru_id — a
     * "double period" taught by the same guru is one teaching session, not two.
     *
     * @param  Collection<int, JadwalPelajaran>  $jadwalHariIni
     * @return Collection<int, Collection<int, JadwalPelajaran>>
     */
    private function kelompokkanJadiBlok(Collection $jadwalHariIni): Collection
    {
        $semuaBlok = collect();
        $blokSaatIni = collect();

        foreach ($jadwalHariIni as $jadwal) {
            if ($blokSaatIni->isNotEmpty()) {
                $terakhir = $blokSaatIni->last();
                $berurutan = $jadwal->jamPelajaran->urutan === $terakhir->jamPelajaran->urutan + 1;
                $samaMapelDanGuru = $jadwal->mata_pelajaran_id === $terakhir->mata_pelajaran_id
                    && $jadwal->guru_id === $terakhir->guru_id;

                if (! ($berurutan && $samaMapelDanGuru)) {
                    $semuaBlok->push($blokSaatIni);
                    $blokSaatIni = collect();
                }
            }

            $blokSaatIni->push($jadwal);
        }

        if ($blokSaatIni->isNotEmpty()) {
            $semuaBlok->push($blokSaatIni);
        }

        return $semuaBlok;
    }

    /**
     * @param  Collection<int, JadwalPelajaran>  $blok  one or more consecutive same-mapel/guru slots
     */
    private function buatSesi(Kelas $kelas, Collection $blok, CarbonInterface $tanggal): SesiPembelajaran
    {
        $jadwalPertama = $blok->first();
        $jadwalTerakhir = $blok->last();

        $sesi = SesiPembelajaran::firstOrCreate(
            [
                'jadwal_pelajaran_id' => $jadwalPertama->id,
                'tanggal' => $tanggal->toDateString(),
            ],
            [
                'kelas_id' => $kelas->id,
                'guru_id' => $jadwalPertama->guru_id,
                'mata_pelajaran_id' => $jadwalPertama->mata_pelajaran_id,
                'jam_mulai' => $jadwalPertama->jamPelajaran->jam_mulai,
                'jam_selesai' => $jadwalTerakhir->jamPelajaran->jam_selesai,
                'status' => 'terlaksana',
            ]
        );

        if ($sesi->wasRecentlyCreated) {
            foreach ($kelas->siswa()->where('status', 'aktif')->get() as $siswa) {
                Presensi::firstOrCreate(
                    ['sesi_pembelajaran_id' => $sesi->id, 'siswa_id' => $siswa->id],
                    ['status' => 'hadir']
                );
            }
        }

        return $sesi;
    }
}
```

Note: `jadwal_pelajaran_id`/`tanggal` remains a valid, deterministic idempotency key for `firstOrCreate` even after this change — `$jadwalPertama` is always the same specific `JadwalPelajaran` row for a given block on a given date, since block-detection only depends on data that doesn't change between calls (`JadwalPelajaran`/`JamPelajaran` rows for that day). Do not change the Task 2 migration's `unique(['jadwal_pelajaran_id', 'tanggal'])` constraint for this.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/SesiPembelajaranGeneratorTest.php`
Expected: PASS (7 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Enums/Hari.php app/Services/SesiPembelajaranGenerator.php tests/Unit/Services/SesiPembelajaranGeneratorTest.php
git commit -m "feat: add SesiPembelajaranGenerator service"
```

---

### Task 4: Guru-facing screen — jurnal + presensi (data-scoped)

**Files:**
- Modify: `app/Models/Kelas.php`
- Create: `app/Http/Controllers/Guru/SesiPembelajaranController.php`
- Create: `resources/views/guru/sesi-pembelajaran/index.blade.php`
- Create: `resources/views/guru/sesi-pembelajaran/show.blade.php`
- Modify: `routes/admin.php` (guru routes live under the same `auth`+`verified` group per this codebase's single-group convention)
- Test: `tests/Feature/Guru/SesiPembelajaranControllerTest.php`

**Interfaces:**
- Consumes: `App\Services\SesiPembelajaranGenerator` (Task 3), `App\Models\SesiPembelajaran`/`Presensi` (Task 2), existing `Guru::$user_id` link.
- Produces: `Kelas::jadwalPelajaran(): HasMany` (new relation, added in this task), routes `guru.sesi.index` (today's sessions for the logged-in guru, auto-generating them first), `guru.sesi.show` (jurnal + presensi form), `guru.sesi.update` (save jurnal + per-student status), permission `presensi.isi`.

- [ ] **Step 1: Add the missing `Kelas::jadwalPelajaran()` relation**

The controller in Step 3 needs to look up which classes a guru teaches via `Kelas::whereHas('jadwalPelajaran', ...)`, but `Kelas` (Tahap 1/Tahap 4) never gained the inverse of `JadwalPelajaran::kelas()`. Open `app/Models/Kelas.php` and add:

```php
public function jadwalPelajaran(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(JadwalPelajaran::class);
}
```

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/Guru/SesiPembelajaranControllerTest.php`:

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
use App\Models\SesiPembelajaran;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Carbon\Carbon;
use Spatie\Permission\Models\Permission;
use App\Enums\Hari;

function siapkanGuruDenganJadwalHariIni(): array
{
    Carbon::setTestNow(Carbon::parse('2026-08-19')); // a Wednesday

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0]]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'is_pelajaran' => true]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);

    Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['presensi.isi']);
    $guruUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruUser->assignRole($role);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $guruUser->id]);

    $jadwal = JadwalPelajaran::create([
        'kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $guru->id, 'semester_id' => $semester->id,
    ]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    return compact('guruUser', 'guru', 'kelas', 'jadwal', 'semester', 'siswa');
}

it('denies access without presensi.isi permission', function () {
    $this->actingAs(User::factory()->create())->get(route('guru.sesi.index'))->assertForbidden();
});

it('auto-generates and lists today\'s sesi belonging to the logged-in guru', function () {
    ['guruUser' => $guruUser] = siapkanGuruDenganJadwalHariIni();

    $response = $this->actingAs($guruUser)->get(route('guru.sesi.index'));

    $response->assertOk();
    $response->assertViewHas('sesiList', fn ($list) => $list->count() === 1);
});

it('does not show a sesi belonging to a different guru', function () {
    ['kelas' => $kelas] = siapkanGuruDenganJadwalHariIni();

    Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $lainRole = Role::firstOrCreate(['name' => 'guru_lain', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $lainRole->givePermissionTo(['presensi.isi']);
    $guruLainUser = User::factory()->create(['lembaga_id' => $kelas->lembaga_id]);
    $guruLainUser->assignRole($lainRole);
    Guru::factory()->create(['lembaga_id' => $kelas->lembaga_id, 'user_id' => $guruLainUser->id]);

    $response = $this->actingAs($guruLainUser)->get(route('guru.sesi.index'));

    $response->assertViewHas('sesiList', fn ($list) => $list->count() === 0);
});

it('saves jurnal materi and per-student presensi status', function () {
    ['guruUser' => $guruUser, 'siswa' => $siswa] = siapkanGuruDenganJadwalHariIni();
    $this->actingAs($guruUser)->get(route('guru.sesi.index')); // triggers generation
    $sesi = SesiPembelajaran::firstOrFail();

    $this->actingAs($guruUser)->put(route('guru.sesi.update', $sesi), [
        'materi' => 'Perkalian dan pembagian',
        'presensi' => [
            $siswa->id => 'izin',
        ],
    ])->assertRedirect(route('guru.sesi.index'));

    expect($sesi->fresh()->materi)->toBe('Perkalian dan pembagian');
    expect($sesi->fresh()->presensi()->where('siswa_id', $siswa->id)->first()->status->value)->toBe('izin');
});

it('forbids a guru from updating a sesi that does not belong to them', function () {
    ['kelas' => $kelas] = siapkanGuruDenganJadwalHariIni();
    $sesi = SesiPembelajaran::firstOrFail();

    Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $lainRole = Role::firstOrCreate(['name' => 'guru_lain2', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $lainRole->givePermissionTo(['presensi.isi']);
    $guruLainUser = User::factory()->create(['lembaga_id' => $kelas->lembaga_id]);
    $guruLainUser->assignRole($lainRole);
    Guru::factory()->create(['lembaga_id' => $kelas->lembaga_id, 'user_id' => $guruLainUser->id]);

    $this->actingAs($guruLainUser)->put(route('guru.sesi.update', $sesi), [
        'materi' => 'Mencoba mengubah sesi orang lain',
        'presensi' => [],
    ])->assertForbidden();
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test tests/Feature/Guru/SesiPembelajaranControllerTest.php`
Expected: FAIL with route `guru.sesi.index` not defined.

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/Guru/SesiPembelajaranController.php`:

```php
<?php

namespace App\Http\Controllers\Guru;

use App\Enums\StatusPresensi;
use App\Models\Kelas;
use App\Models\SesiPembelajaran;
use App\Services\SesiPembelajaranGenerator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class SesiPembelajaranController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('presensi.isi');

        $guru = $request->user()->guru;
        $hariIni = now();

        if ($guru) {
            $kelasList = Kelas::whereHas('jadwalPelajaran', fn ($q) => $q->where('guru_id', $guru->id))
                ->orWhere('wali_kelas_guru_id', $guru->id)
                ->get();

            foreach ($kelasList as $kelas) {
                $semesterId = optional($kelas->tahunAjaran->semester()->where('status_aktif', true)->first())->id;
                if ($semesterId) {
                    (new SesiPembelajaranGenerator)->generateUntukTanggal($kelas, $hariIni, $semesterId);
                }
            }
        }

        return view('guru.sesi-pembelajaran.index', [
            'sesiList' => $guru
                ? SesiPembelajaran::where('guru_id', $guru->id)->whereDate('tanggal', $hariIni)->with('kelas', 'mataPelajaran')->get()
                : collect(),
        ]);
    }

    public function show(SesiPembelajaran $sesi): View
    {
        $this->authorize('presensi.isi');
        $this->authorizeMilikGuru($sesi);

        return view('guru.sesi-pembelajaran.show', [
            'sesi' => $sesi,
            'presensiList' => $sesi->presensi()->with('siswa')->get(),
        ]);
    }

    public function update(Request $request, SesiPembelajaran $sesi): RedirectResponse
    {
        $this->authorize('presensi.isi');
        $this->authorizeMilikGuru($sesi);

        $data = $request->validate([
            'materi' => ['nullable', 'string'],
            'presensi' => ['required', 'array'],
            'presensi.*' => ['required', 'in:hadir,izin,sakit,alpa,terlambat'],
        ]);

        $sesi->update(['materi' => $data['materi'] ?? null]);

        foreach ($data['presensi'] as $siswaId => $status) {
            $sesi->presensi()->where('siswa_id', $siswaId)->update(['status' => $status]);
        }

        return redirect()->route('guru.sesi.index')->with('status', 'Jurnal dan presensi berhasil disimpan.');
    }

    private function authorizeMilikGuru(SesiPembelajaran $sesi): void
    {
        $guru = auth()->user()->guru;

        abort_if($guru === null || $sesi->guru_id !== $guru->id, 403);
    }
}
```

- [ ] **Step 5: Add routes**

In `routes/admin.php`, add this block (with its own imports) right after the existing route groups — this file is loaded inside the same `auth`+`verified`+`admin` prefix group per this codebase's single-group convention, but guru routes need their own prefix so they don't collide with `admin.*` names. Add a new prefixed group at the end of the file, **outside** the existing `admin.` group:

```php
Route::middleware(['auth', 'verified'])->prefix('guru')->name('guru.')->group(function () {
    Route::get('sesi', [\App\Http\Controllers\Guru\SesiPembelajaranController::class, 'index'])->name('sesi.index');
    Route::get('sesi/{sesi}', [\App\Http\Controllers\Guru\SesiPembelajaranController::class, 'show'])->name('sesi.show');
    Route::put('sesi/{sesi}', [\App\Http\Controllers\Guru\SesiPembelajaranController::class, 'update'])->name('sesi.update');
});
```

- [ ] **Step 6: Create the views**

Create `resources/views/guru/sesi-pembelajaran/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-2xl font-semibold text-ink">Sesi Pembelajaran Hari Ini</h2>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        @if (session('status'))
            <div class="rounded-xl bg-signal-green/10 p-4 text-sm text-signal-green">{{ session('status') }}</div>
        @endif

        <x-panel>
            <ul class="divide-y divide-ink/10">
                @forelse ($sesiList as $sesi)
                    <li class="flex items-center justify-between px-6 py-4">
                        <div>
                            <p class="text-sm font-medium text-ink">{{ $sesi->kelas->nama }} &middot; {{ $sesi->mataPelajaran?->nama ?? '(tanpa mapel)' }}</p>
                            <p class="text-xs text-ink/60">{{ $sesi->jam_mulai }}–{{ $sesi->jam_selesai }}</p>
                        </div>
                        <a href="{{ route('guru.sesi.show', $sesi) }}" class="text-sm font-medium text-ink hover:text-brass">Isi Jurnal &amp; Presensi</a>
                    </li>
                @empty
                    <li class="px-6 py-8 text-center text-sm text-ink/60">Tidak ada sesi untuk hari ini.</li>
                @endforelse
            </ul>
        </x-panel>
    </div>
</x-app-layout>
```

Create `resources/views/guru/sesi-pembelajaran/show.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-2xl font-semibold text-ink">{{ $sesi->kelas->nama }} &middot; {{ $sesi->mataPelajaran?->nama ?? '(tanpa mapel)' }}</h2>
    </x-slot>

    <div class="mx-auto max-w-3xl">
        <x-panel>
            <form method="POST" action="{{ route('guru.sesi.update', $sesi) }}" class="space-y-6 p-6">
                @csrf
                @method('PUT')

                <div>
                    <label class="text-sm font-medium text-ink">Materi / Jurnal Mengajar</label>
                    <textarea name="materi" rows="3" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">{{ old('materi', $sesi->materi) }}</textarea>
                </div>

                <div>
                    <p class="mb-2 text-sm font-medium text-ink">Presensi Siswa</p>
                    <table class="w-full text-sm">
                        <tbody>
                            @foreach ($presensiList as $presensi)
                                <tr class="border-b border-ink/10">
                                    <td class="py-2 pr-2 text-ink">{{ $presensi->siswa->nama_lengkap }}</td>
                                    <td class="py-2">
                                        <select name="presensi[{{ $presensi->siswa_id }}]" class="rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                                            @foreach (\App\Enums\StatusPresensi::cases() as $status)
                                                <option value="{{ $status->value }}" @selected($presensi->status === $status)>{{ $status->label() }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <button type="submit" class="rounded-xl bg-ink px-4 py-2 text-sm font-medium text-paper transition hover:bg-ink/90">Simpan</button>
            </form>
        </x-panel>
    </div>
</x-app-layout>
```

- [ ] **Step 7: Sync permissions**

Run: `php artisan permissions:sync`
Expected: Output includes `Created permission: presensi.isi`.

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test tests/Feature/Guru/SesiPembelajaranControllerTest.php`
Expected: PASS (5 tests)

- [ ] **Step 9: Commit**

```bash
git add app/Models/Kelas.php app/Http/Controllers/Guru/SesiPembelajaranController.php resources/views/guru/sesi-pembelajaran routes/admin.php tests/Feature/Guru/SesiPembelajaranControllerTest.php
git commit -m "feat: add guru-facing jurnal and presensi screen, scoped to the owning guru"
```

---

## Plan Self-Review Notes

- **Spec coverage**: Implements spec Section 4 in full — `sesi_pembelajaran` as the journal-carrying parent, `presensi` as the lean per-student child, the harian/per-jam-pelajaran/PKL modes (PKL supported via `jadwal_pelajaran_id = null` ad-hoc sessions, though a dedicated PKL creation UI is not built in this tahap — only the model/generator support it), and the guru/wali-kelas data-scoping principle from Section 7.2.
- **Type consistency check**: `SesiPembelajaranGenerator::generateUntukTanggal()`'s signature (`Kelas $kelas, CarbonInterface $tanggal, int $semesterId`) matches what Task 4's controller calls it with.
- **Note for Tahap 6+**: Wali Kelas's "lihat rekap presensi lintas mapel untuk kelas yang diampu" (spec Section 7.1) is **not** built in this tahap — only guru-mapel-scoped access to their own sessions. A future tahap (or a follow-up task in this one, if prioritized later) would add a `Wali Kelas` recap view filtering by `kelas.wali_kelas_guru_id` instead of `sesi_pembelajaran.guru_id`.
- **Block-merging impact on Task 4 (added after spec Section 4.1a)**: Task 4's controller and views need **no change** for block-merged sessions. `SesiPembelajaranController::index()`/`show()`/`update()` all operate on `SesiPembelajaran` rows generically (by `guru_id` and by the model's own id) — they never iterate `JadwalPelajaran` slots themselves, so a merged block simply appears as one row instead of two. The index view's `{{ $sesi->jam_mulai }}–{{ $sesi->jam_selesai }}` already renders whatever range the row spans, so a double-period block reads as e.g. "07:35–09:00" with no template change needed. Task 4's test fixture (`siapkanGuruDenganJadwalHariIni()`) only exercises a single-slot session — it doesn't need to cover the merge path, since that behavior is already fully verified by Task 3's 3 new block-merge tests at the generator level, and Task 4 consumes `SesiPembelajaran` rows, not raw slots.
