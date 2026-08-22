# Kehadiran SDM Sub-project 2 — Kalender Kerja SDM — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bangun kalender kerja SDM independen dari kalender akademik: tabel `kalender_kerja_sdm` + kolom `hari_libur_mingguan_sdm` di `Lembaga`, resolver resolusi hari libur/kerja, integrasi ke `RecordManualAttendanceAction`/`ScanQrAttendanceAction` (tolak input di hari libur, override manual saja), command harian auto-tandai-Alpa, dan UI kelola kalender (termasuk fitur salin snapshot dari kalender akademik).

**Architecture:** Domain `App\Domains\Sdm\` (sudah ada dari Sub-project 1) diperluas: `Models\KalenderKerjaSdm`, `Services\KalenderKerjaSdmResolver`, `Actions\SetHariLiburMingguanSdmAction`, `Actions\CopyKalenderAkademikNasionalAction`, `Exceptions\AttendanceOnHolidayException`. `KalenderKerjaSdmResolver` ditulis terpisah TOTAL dari `App\Domains\Akademik\Services\KalenderAkademikResolver` (tidak ada base class bersama) tapi meniru urutan resolusinya persis (lembaga override → nasional → mingguan). Command baru `App\Console\Commands\TandaiAlpaOtomatisSdm` didaftarkan di scheduler.

**Tech Stack:** Laravel 11, PHP 8.2+, Pest (test), sama seperti Sub-project 1.

## Global Constraints

- Branch kerja: `sdm-v1`. JANGAN buat branch baru, JANGAN buat worktree.
- Baseline: commit `ad1b1ed` di branch `sdm-v1` (spec Sub-project 2 baru dikomit). Kalau ada commit baru masuk sebelum eksekusi, verifikasi ulang file yang dikutip plan ini sebelum melanjutkan — terutama `app/Domains/Sdm/Actions/RecordManualAttendanceAction.php`, `app/Domains/Sdm/Actions/ScanQrAttendanceAction.php`, `app/Http/Controllers/Admin/AttendanceConfigurationController.php`, `resources/views/admin/kehadiran-sdm/konfigurasi.blade.php`, `routes/console.php`.
- Spec lengkap: `.agents/specs/2026-08-22-sdm-02-kalender-kerja-sdm.md` — baca dulu untuk "kenapa", plan ini "bagaimana"-nya.
- **`KalenderKerjaSdm` model WAJIB pakai `BelongsToTenant`** — BEDA dari `KalenderAkademik` (yang scope manual). Ini keputusan sadar (lihat spec §2), JANGAN "koreksi" jadi manual scope karena mengikuti pola `KalenderAkademik`.
- **`KalenderKerjaSdmResolver`, KEDUA query di dalamnya WAJIB `withoutGlobalScope(TenantScope::class)`** — kalau tidak, resolver akan salah hasil untuk aktor `scope_level: lembaga` yang memanggilnya lewat request HTTP (lihat spec §4 penjelasan detail kenapa). Ini bukan opsional, ini mencegah bug nyata.
- TIDAK ADA hardcode nama role apapun — gerbang nasional pakai `$request->user()->widestScopeLevel() === 'yayasan'`, BUKAN `hasRole(...)`.
- TIDAK menulis apapun ke tabel `kalender_akademik` — hanya dibaca (read-only) oleh fitur salin.
- TIDAK membangun shift kerja/jadwal per-pegawai — itu Sub-project 3, di luar cakupan total plan ini.
- Testing policy: test scoped per task, dijalankan SEBELUM commit setiap task. Full suite HANYA di Task 10, dan HANYA setelah izin eksplisit user.
- Satu commit per task, pesan commit sesuai yang ditentukan di tiap task Step terakhir.
- Test framework: Pest, gaya `it('...', function () { ... })`.

---

## Task 1: Migrasi (Kolom Lembaga + Tabel Kalender) + Enum Baru

**Files:**
- Create: `database/migrations/2026_08_22_100000_add_hari_libur_mingguan_sdm_to_lembaga_table.php`
- Create: `database/migrations/2026_08_22_100100_create_kalender_kerja_sdm_table.php`
- Create: `app/Domains/Sdm/Enums/TipeKalenderKerjaSdm.php`
- Modify: `app/Domains/Sdm/Enums/AttendanceMethod.php`
- Modify: `app/Models/Lembaga.php`

**Interfaces:**
- Produces: kolom `lembaga.hari_libur_mingguan_sdm`; tabel `kalender_kerja_sdm`; enum `TipeKalenderKerjaSdm` (`Libur`, `Kerja`); `AttendanceMethod::System` case baru — dipakai Task 2 dst.

- [ ] **Step 1: Buat migrasi kolom `hari_libur_mingguan_sdm`**

```php
<?php
// database/migrations/2026_08_22_100000_add_hari_libur_mingguan_sdm_to_lembaga_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lembaga', function (Blueprint $table) {
            $table->json('hari_libur_mingguan_sdm')->default(DB::raw('(JSON_ARRAY(0))'))->after('hari_libur_mingguan');
        });
    }

    public function down(): void
    {
        Schema::table('lembaga', function (Blueprint $table) {
            $table->dropColumn('hari_libur_mingguan_sdm');
        });
    }
};
```

- [ ] **Step 2: Buat migrasi tabel `kalender_kerja_sdm`**

```php
<?php
// database/migrations/2026_08_22_100100_create_kalender_kerja_sdm_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kalender_kerja_sdm', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yayasan_id')->constrained('yayasan')->cascadeOnDelete();
            $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->cascadeOnDelete();
            $table->date('tanggal');
            $table->date('tanggal_selesai')->nullable();
            $table->string('nama');
            $table->string('tipe');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['lembaga_id', 'tanggal']);
            $table->index(['yayasan_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kalender_kerja_sdm');
    }
};
```

- [ ] **Step 3: Jalankan migrasi dan verifikasi**

Run: `php artisan migrate`
Expected: 2 migrasi baru berjalan sukses.

Run: `php artisan tinker --execute="echo \App\Models\Lembaga::factory()->make()->hari_libur_mingguan_sdm === null ? 'NEED_DEFAULT_CHECK' : 'ok';"`
(Catatan: nilai default DB baru terlihat setelah row benar-benar di-INSERT, bukan `make()`. Verifikasi sebenarnya ada di Task 2 lewat test factory `create()`.)

- [ ] **Step 4: Buat enum `TipeKalenderKerjaSdm`**

```php
<?php

namespace App\Domains\Sdm\Enums;

enum TipeKalenderKerjaSdm: string
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

- [ ] **Step 5: Tambah case `System` ke `AttendanceMethod`**

Buka `app/Domains/Sdm/Enums/AttendanceMethod.php`, ganti isinya jadi:

```php
<?php

namespace App\Domains\Sdm\Enums;

enum AttendanceMethod: string
{
    case Admin = 'admin';
    case Qr = 'qr';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Input Manual Admin',
            self::Qr => 'Scan QR',
            self::System => 'Otomatis Sistem',
        };
    }
}
```

- [ ] **Step 6: Tambah `hari_libur_mingguan_sdm` ke `$fillable` dan cast di `app/Models/Lembaga.php`**

Cari baris:

```php
        'status_aktif', 'hari_libur_mingguan',
    ];
```

Ganti jadi:

```php
        'status_aktif', 'hari_libur_mingguan', 'hari_libur_mingguan_sdm',
    ];
```

Cari baris:

```php
            'hari_libur_mingguan' => 'array',
        ];
```

Ganti jadi:

```php
            'hari_libur_mingguan' => 'array',
            'hari_libur_mingguan_sdm' => 'array',
        ];
```

- [ ] **Step 7: Verifikasi lewat tinker**

Run: `php artisan tinker --execute="echo App\Domains\Sdm\Enums\AttendanceMethod::System->value . ' / ' . App\Domains\Sdm\Enums\TipeKalenderKerjaSdm::Libur->label();"`
Expected output: `system / Libur`

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_22_100000_add_hari_libur_mingguan_sdm_to_lembaga_table.php database/migrations/2026_08_22_100100_create_kalender_kerja_sdm_table.php app/Domains/Sdm/Enums/TipeKalenderKerjaSdm.php app/Domains/Sdm/Enums/AttendanceMethod.php app/Models/Lembaga.php
git commit -m "feat(sdm): migrasi kalender_kerja_sdm + kolom hari_libur_mingguan_sdm, enum TipeKalenderKerjaSdm, AttendanceMethod::System"
```

---

## Task 2: Model `KalenderKerjaSdm` + `KalenderKerjaSdmResolver`

**Files:**
- Create: `app/Domains/Sdm/Models/KalenderKerjaSdm.php`
- Create: `app/Domains/Sdm/Services/KalenderKerjaSdmResolver.php`
- Test: `tests/Unit/Services/KalenderKerjaSdmResolverTest.php`
- Test: `tests/Feature/Sdm/KalenderKerjaSdmTenantIsolationTest.php`

**Interfaces:**
- Consumes: `AttendanceMethod` (Task 1, tidak dipakai langsung tapi domain sama), `App\Models\Scopes\TenantScope` (existing).
- Produces: `KalenderKerjaSdm::create([...])`; `KalenderKerjaSdmResolver::resolve(Lembaga $lembaga, CarbonInterface $tanggal): array{libur: bool, alasan: string}` — dipakai Task 4, 5, 6.

- [ ] **Step 1: Buat model `KalenderKerjaSdm`**

```php
<?php

namespace App\Domains\Sdm\Models;

use App\Domains\Sdm\Enums\TipeKalenderKerjaSdm;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KalenderKerjaSdm extends Model
{
    use BelongsToTenant;

    protected $table = 'kalender_kerja_sdm';

    protected $fillable = ['yayasan_id', 'lembaga_id', 'tanggal', 'tanggal_selesai', 'nama', 'tipe', 'keterangan'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'tanggal_selesai' => 'date',
            'tipe' => TipeKalenderKerjaSdm::class,
        ];
    }

    public function yayasan(): BelongsTo
    {
        return $this->belongsTo(Yayasan::class);
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }
}
```

- [ ] **Step 2: Buat `KalenderKerjaSdmResolver`**

Urutan resolusi identik `KalenderAkademikResolver` (`app/Domains/Akademik/Services/KalenderAkademikResolver.php`), TAPI kedua query WAJIB `withoutGlobalScope(TenantScope::class)` (lihat Global Constraints — alasan detail ada di spec §4).

```php
<?php

namespace App\Domains\Sdm\Services;

use App\Domains\Sdm\Enums\TipeKalenderKerjaSdm;
use App\Domains\Sdm\Models\KalenderKerjaSdm;
use App\Models\Lembaga;
use App\Models\Scopes\TenantScope;
use Carbon\CarbonInterface;

class KalenderKerjaSdmResolver
{
    /**
     * @return array{libur: bool, alasan: string}
     */
    public function resolve(Lembaga $lembaga, CarbonInterface $tanggal): array
    {
        $entriLembaga = KalenderKerjaSdm::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $lembaga->id)
            ->where(fn ($q) => $this->cocokRentang($q, $tanggal))
            ->first();

        if ($entriLembaga) {
            return [
                'libur' => $entriLembaga->tipe === TipeKalenderKerjaSdm::Libur,
                'alasan' => $entriLembaga->nama,
            ];
        }

        $entriNasional = KalenderKerjaSdm::withoutGlobalScope(TenantScope::class)
            ->whereNull('lembaga_id')
            ->where('yayasan_id', $lembaga->yayasan_id)
            ->where(fn ($q) => $this->cocokRentang($q, $tanggal))
            ->first();

        if ($entriNasional) {
            return [
                'libur' => $entriNasional->tipe === TipeKalenderKerjaSdm::Libur,
                'alasan' => $entriNasional->nama,
            ];
        }

        if (in_array($tanggal->dayOfWeek, $lembaga->hari_libur_mingguan_sdm ?? [], true)) {
            return ['libur' => true, 'alasan' => 'Libur mingguan SDM'];
        }

        return ['libur' => false, 'alasan' => 'Hari kerja efektif'];
    }

    private function cocokRentang($query, CarbonInterface $tanggal)
    {
        $tgl = $tanggal->toDateString();

        return $query
            ->whereDate('tanggal', '<=', $tgl)
            ->where(fn ($q) => $q->whereDate('tanggal_selesai', '>=', $tgl)
                ->orWhere(fn ($q2) => $q2->whereNull('tanggal_selesai')->whereDate('tanggal', '>=', $tgl))
            );
    }
}
```

- [ ] **Step 3: Tulis test resolver `tests/Unit/Services/KalenderKerjaSdmResolverTest.php`**

```php
<?php
// tests/Unit/Services/KalenderKerjaSdmResolverTest.php

use App\Domains\Sdm\Enums\TipeKalenderKerjaSdm;
use App\Domains\Sdm\Models\KalenderKerjaSdm;
use App\Domains\Sdm\Services\KalenderKerjaSdmResolver;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Carbon\Carbon;

it('resolves a plain weekday with no calendar entries as a work day', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);

    $result = (new KalenderKerjaSdmResolver)->resolve($lembaga, Carbon::parse('2026-08-19')); // Wednesday

    expect($result['libur'])->toBeFalse();
});

it('resolves a Sunday as libur via hari_libur_mingguan_sdm when no calendar entry exists', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);

    $result = (new KalenderKerjaSdmResolver)->resolve($lembaga, Carbon::parse('2026-08-16')); // Sunday

    expect($result['libur'])->toBeTrue();
    expect($result['alasan'])->toBe('Libur mingguan SDM');
});

it('national calendar entry overrides the weekly recurring default', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    KalenderKerjaSdm::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null, 'tanggal' => '2026-08-17', 'nama' => 'Hari Kemerdekaan RI', 'tipe' => TipeKalenderKerjaSdm::Libur]);

    $result = (new KalenderKerjaSdmResolver)->resolve($lembaga, Carbon::parse('2026-08-17')); // Monday, not a weekly off-day

    expect($result['libur'])->toBeTrue();
    expect($result['alasan'])->toBe('Hari Kemerdekaan RI');
});

it('lembaga-specific override beats the national entry for the same date', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    KalenderKerjaSdm::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null, 'tanggal' => '2027-01-01', 'nama' => 'Tahun Baru Masehi', 'tipe' => TipeKalenderKerjaSdm::Libur]);
    KalenderKerjaSdm::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'tanggal' => '2027-01-01', 'nama' => 'TU Tetap Masuk', 'tipe' => TipeKalenderKerjaSdm::Kerja]);

    $result = (new KalenderKerjaSdmResolver)->resolve($lembaga, Carbon::parse('2027-01-01'));

    expect($result['libur'])->toBeFalse();
    expect($result['alasan'])->toBe('TU Tetap Masuk');
});

it('lembaga-specific entry does not leak to a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    KalenderKerjaSdm::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembagaA->id, 'tanggal' => '2027-01-01', 'nama' => 'TU Tetap Masuk', 'tipe' => TipeKalenderKerjaSdm::Kerja]);
    KalenderKerjaSdm::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null, 'tanggal' => '2027-01-01', 'nama' => 'Tahun Baru Masehi', 'tipe' => TipeKalenderKerjaSdm::Libur]);

    $result = (new KalenderKerjaSdmResolver)->resolve($lembagaB, Carbon::parse('2027-01-01'));

    expect($result['libur'])->toBeTrue();
    expect($result['alasan'])->toBe('Tahun Baru Masehi');
});

it('resolves a date in the middle of a multi-day lembaga range as libur', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    KalenderKerjaSdm::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'tanggal' => '2026-08-23', 'tanggal_selesai' => '2026-09-01', 'nama' => 'Libur Maulid', 'tipe' => TipeKalenderKerjaSdm::Libur]);

    $result = (new KalenderKerjaSdmResolver)->resolve($lembaga, Carbon::parse('2026-08-27'));

    expect($result)->toBe(['libur' => true, 'alasan' => 'Libur Maulid']);
});

it('resolves the day after a range ends as a normal work day', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    KalenderKerjaSdm::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'tanggal' => '2026-08-23', 'tanggal_selesai' => '2026-09-01', 'nama' => 'Libur Maulid', 'tipe' => TipeKalenderKerjaSdm::Libur]);

    $result = (new KalenderKerjaSdmResolver)->resolve($lembaga, Carbon::parse('2026-09-02'));

    expect($result)->toBe(['libur' => false, 'alasan' => 'Hari kerja efektif']);
});
```

- [ ] **Step 4: Jalankan test resolver**

Run: `php artisan test tests/Unit/Services/KalenderKerjaSdmResolverTest.php`
Expected: 7 passed, 0 failed.

- [ ] **Step 5: Tulis test regresi tenant-isolation `tests/Feature/Sdm/KalenderKerjaSdmTenantIsolationTest.php`**

Test ini MEMBUKTIKAN bug class yang dijelaskan di spec §4 TIDAK terjadi — resolver harus tetap benar walau dipanggil dalam konteks aktor `scope_level: lembaga` yang sedang login (bukan cuma dari command tanpa aktor).

```php
<?php
// tests/Feature/Sdm/KalenderKerjaSdmTenantIsolationTest.php

use App\Domains\Sdm\Enums\TipeKalenderKerjaSdm;
use App\Domains\Sdm\Models\KalenderKerjaSdm;
use App\Domains\Sdm\Services\KalenderKerjaSdmResolver;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Carbon\Carbon;

it('resolver still sees the national holiday entry when called while a lembaga-scoped actor is authenticated', function () {
    // Regression guard: KalenderKerjaSdm uses BelongsToTenant, so without an explicit
    // withoutGlobalScope(TenantScope::class) inside the resolver, TenantScope would force
    // `lembaga_id = actingUser->lembaga_id` onto the resolver's whereNull('lembaga_id') query
    // for the national entry, making it impossible to ever match (0 rows), silently hiding
    // every national holiday from a logged-in scope_level:lembaga actor.
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    KalenderKerjaSdm::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null, 'tanggal' => '2026-08-17', 'nama' => 'Hari Kemerdekaan RI', 'tipe' => TipeKalenderKerjaSdm::Libur]);

    $role = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $actor = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $actor->assignRole($role);

    $this->actingAs($actor);

    $result = app(KalenderKerjaSdmResolver::class)->resolve($lembaga, Carbon::parse('2026-08-17'));

    expect($result['libur'])->toBeTrue();
    expect($result['alasan'])->toBe('Hari Kemerdekaan RI');
});
```

- [ ] **Step 6: Jalankan test tenant isolation**

Run: `php artisan test tests/Feature/Sdm/KalenderKerjaSdmTenantIsolationTest.php`
Expected: 1 passed, 0 failed.

- [ ] **Step 7: Commit**

```bash
git add app/Domains/Sdm/Models/KalenderKerjaSdm.php app/Domains/Sdm/Services/KalenderKerjaSdmResolver.php tests/Unit/Services/KalenderKerjaSdmResolverTest.php tests/Feature/Sdm/KalenderKerjaSdmTenantIsolationTest.php
git commit -m "feat(sdm): tambah model KalenderKerjaSdm dan KalenderKerjaSdmResolver dengan bypass TenantScope eksplisit"
```

---

## Task 3: `SetHariLiburMingguanSdmAction`

**Files:**
- Create: `app/Domains/Sdm/DataTransferObjects/HariKerjaSdmData.php`
- Create: `app/Domains/Sdm/Actions/SetHariLiburMingguanSdmAction.php`
- Test: `tests/Feature/Sdm/SetHariLiburMingguanSdmActionTest.php`

**Interfaces:**
- Consumes: `Lembaga` model (existing).
- Produces: `SetHariLiburMingguanSdmAction::execute(Lembaga $lembaga, HariKerjaSdmData $data): Lembaga` — dipakai Task 8 (`AttendanceConfigurationController`).

- [ ] **Step 1: Buat DTO `HariKerjaSdmData`**

Framing DTO/UI POSITIF (hari kerja), meniru `HariAktifLembagaData` (`app/Domains/Akademik/DataTransferObjects/HariAktifLembagaData.php`) — WALAU kolom DB-nya (`hari_libur_mingguan_sdm`) menyimpan arah NEGATIF (hari libur). Inversi terjadi di Action, bukan di DTO.

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\DataTransferObjects;

final readonly class HariKerjaSdmData
{
    /**
     * @param  array<int, int>  $hariKerja  hari 0 (minggu) - 6 (sabtu) yang menjadi hari kerja SDM
     */
    public function __construct(
        public array $hariKerja,
    ) {}
}
```

- [ ] **Step 2: Buat `SetHariLiburMingguanSdmAction`**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions;

use App\Domains\Sdm\DataTransferObjects\HariKerjaSdmData;
use App\Models\Lembaga;

final class SetHariLiburMingguanSdmAction
{
    public function execute(Lembaga $lembaga, HariKerjaSdmData $data): Lembaga
    {
        $hariLibur = array_values(array_diff(range(0, 6), $data->hariKerja));

        $lembaga->update(['hari_libur_mingguan_sdm' => $hariLibur]);

        return $lembaga->fresh();
    }
}
```

- [ ] **Step 3: Tulis test**

```php
<?php
// tests/Feature/Sdm/SetHariLiburMingguanSdmActionTest.php

use App\Domains\Sdm\Actions\SetHariLiburMingguanSdmAction;
use App\Domains\Sdm\DataTransferObjects\HariKerjaSdmData;
use App\Models\Lembaga;
use App\Models\Yayasan;

it('converts a positive work-day list into the stored negative off-day list', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $updated = app(SetHariLiburMingguanSdmAction::class)->execute($lembaga, new HariKerjaSdmData(hariKerja: [1, 2, 3, 4, 5]));

    expect($updated->hari_libur_mingguan_sdm)->toBe([0, 6]);
});

it('supports a 6-day work week (only Sunday off)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $updated = app(SetHariLiburMingguanSdmAction::class)->execute($lembaga, new HariKerjaSdmData(hariKerja: [1, 2, 3, 4, 5, 6]));

    expect($updated->hari_libur_mingguan_sdm)->toBe([0]);
});
```

- [ ] **Step 4: Jalankan test**

Run: `php artisan test tests/Feature/Sdm/SetHariLiburMingguanSdmActionTest.php`
Expected: 2 passed, 0 failed.

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Sdm/DataTransferObjects/HariKerjaSdmData.php app/Domains/Sdm/Actions/SetHariLiburMingguanSdmAction.php tests/Feature/Sdm/SetHariLiburMingguanSdmActionTest.php
git commit -m "feat(sdm): tambah SetHariLiburMingguanSdmAction"
```

---

## Task 4: `AttendanceOnHolidayException` + Integrasi ke `RecordManualAttendanceAction`

**Files:**
- Create: `app/Domains/Sdm/Exceptions/AttendanceOnHolidayException.php`
- Modify: `app/Domains/Sdm/DataTransferObjects/RecordManualAttendanceData.php`
- Modify: `app/Domains/Sdm/Actions/RecordManualAttendanceAction.php`
- Test: `tests/Feature/Sdm/RecordManualAttendanceActionTest.php`

**Interfaces:**
- Consumes: `KalenderKerjaSdmResolver` (Task 2).
- Produces: `RecordManualAttendanceData` dengan property baru `overrideHariLibur`; `RecordManualAttendanceAction::execute()` melempar `AttendanceOnHolidayException` kalau libur & tidak di-override — dipakai Task 9 (`AttendanceController`).

- [ ] **Step 1: Buat `AttendanceOnHolidayException`**

```php
<?php

namespace App\Domains\Sdm\Exceptions;

use RuntimeException;

class AttendanceOnHolidayException extends RuntimeException
{
    public function __construct(string $alasan)
    {
        parent::__construct("Tanggal ini libur: {$alasan}");
    }
}
```

- [ ] **Step 2: Tambah property `overrideHariLibur` ke `RecordManualAttendanceData`**

Baca dulu isi file saat ini (`app/Domains/Sdm/DataTransferObjects/RecordManualAttendanceData.php`) untuk pastikan urutan property sesuai baseline Sub-project 1. Tambahkan parameter baru DI AKHIR daftar constructor (supaya named-argument call site lama tetap valid tanpa perubahan):

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\DataTransferObjects;

use App\Domains\Sdm\Enums\AttendanceStatus;
use Carbon\CarbonImmutable;

final readonly class RecordManualAttendanceData
{
    public function __construct(
        public int $lembagaId,
        public string $arah,
        public AttendanceStatus $status,
        public CarbonImmutable $waktu,
        public int $dicatatOlehUserId,
        public ?int $attendancePointId = null,
        public ?string $catatan = null,
        public bool $overrideHariLibur = false,
    ) {}
}
```

- [ ] **Step 3: Modifikasi `RecordManualAttendanceAction`**

Tambahkan cek kalender SEBELUM membuat event, di DALAM transaksi tapi sebelum operasi tulis apapun (supaya tidak ada partial write kalau exception dilempar).

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions;

use App\Domains\Sdm\DataTransferObjects\RecordManualAttendanceData;
use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Domains\Sdm\Exceptions\AttendanceOnHolidayException;
use App\Domains\Sdm\Models\AttendanceEvent;
use App\Domains\Sdm\Services\AttendanceRecordAggregator;
use App\Domains\Sdm\Services\KalenderKerjaSdmResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class RecordManualAttendanceAction
{
    public function __construct(
        private readonly AttendanceRecordAggregator $aggregator,
        private readonly KalenderKerjaSdmResolver $kalenderResolver,
    ) {}

    public function execute(Model $pegawai, RecordManualAttendanceData $data): AttendanceEvent
    {
        $resolusi = $this->kalenderResolver->resolve($pegawai->lembaga, $data->waktu);

        if ($resolusi['libur'] && ! $data->overrideHariLibur) {
            throw new AttendanceOnHolidayException($resolusi['alasan']);
        }

        return DB::transaction(function () use ($pegawai, $data) {
            $event = $pegawai->attendanceEvents()->create([
                'lembaga_id' => $data->lembagaId,
                'attendance_point_id' => $data->attendancePointId,
                'method' => AttendanceMethod::Admin,
                'arah' => $data->arah,
                'status' => $data->status,
                'waktu' => $data->waktu,
                'dicatat_oleh_user_id' => $data->dicatatOlehUserId,
                'catatan' => $data->catatan,
            ]);

            $this->aggregator->sync($pegawai, $data->waktu->toImmutable());

            return $event;
        });
    }
}
```

- [ ] **Step 4: Perbarui test `tests/Feature/Sdm/RecordManualAttendanceActionTest.php`**

File ini SUDAH ADA dari Sub-project 1 dengan 3 test. Tambahkan 2 test baru DI AKHIR file (jangan hapus 3 test yang sudah ada — pastikan test lama tetap lulus karena `overrideHariLibur` defaultnya `false` dan tanggal yang dipakai test lama, `2026-08-22`, adalah hari Sabtu bukan Minggu, dan `hari_libur_mingguan_sdm` default barunya `[0]` (Minggu) — jadi test lama TIDAK terpengaruh, Sabtu tetap dianggap hari kerja).

Tambahkan di akhir file:

```php
it('rejects a manual attendance record on a day the calendar resolver marks as libur', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $action = app(\App\Domains\Sdm\Actions\RecordManualAttendanceAction::class);

    expect(fn () => $action->execute($guru, new RecordManualAttendanceData(
        lembagaId: $lembaga->id, arah: 'masuk', status: AttendanceStatus::Hadir,
        waktu: CarbonImmutable::parse('2026-08-23 07:00:00'), dicatatOlehUserId: $admin->id, // Sunday
    )))->toThrow(\App\Domains\Sdm\Exceptions\AttendanceOnHolidayException::class);

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeFalse();
});

it('allows a manual attendance record on a libur day when overrideHariLibur is true', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $action = app(\App\Domains\Sdm\Actions\RecordManualAttendanceAction::class);

    $event = $action->execute($guru, new RecordManualAttendanceData(
        lembagaId: $lembaga->id, arah: 'masuk', status: AttendanceStatus::Hadir,
        waktu: CarbonImmutable::parse('2026-08-23 07:00:00'), dicatatOlehUserId: $admin->id, // Sunday
        overrideHariLibur: true,
    ));

    expect($event->arah)->toBe('masuk');
    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeTrue();
});
```

- [ ] **Step 5: Jalankan test (file lengkap, 3 lama + 2 baru)**

Run: `php artisan test tests/Feature/Sdm/RecordManualAttendanceActionTest.php`
Expected: 5 passed, 0 failed.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Sdm/Exceptions/AttendanceOnHolidayException.php app/Domains/Sdm/DataTransferObjects/RecordManualAttendanceData.php app/Domains/Sdm/Actions/RecordManualAttendanceAction.php tests/Feature/Sdm/RecordManualAttendanceActionTest.php
git commit -m "feat(sdm): tolak input manual di hari libur kalender kerja SDM, dengan opsi override"
```

---

## Task 5: Integrasi Kalender ke `ScanQrAttendanceAction` (Tanpa Override)

**Files:**
- Modify: `app/Domains/Sdm/Actions/ScanQrAttendanceAction.php`
- Test: `tests/Feature/Sdm/ScanQrAttendanceActionTest.php`

**Interfaces:**
- Consumes: `KalenderKerjaSdmResolver` (Task 2), `AttendanceOnHolidayException` (Task 4).
- Produces: `ScanQrAttendanceAction::execute()` melempar `AttendanceOnHolidayException` kalau libur, TANPA jalur override — dipakai Task 9 (`AttendanceQrScanController`).

- [ ] **Step 1: Modifikasi `ScanQrAttendanceAction`**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions;

use App\Domains\Sdm\DataTransferObjects\ScanQrAttendanceData;
use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Domains\Sdm\Enums\AttendanceStatus;
use App\Domains\Sdm\Exceptions\AttendanceOnHolidayException;
use App\Domains\Sdm\Exceptions\InvalidQrTokenException;
use App\Domains\Sdm\Exceptions\QrTokenLembagaMismatchException;
use App\Domains\Sdm\Models\AttendanceEvent;
use App\Domains\Sdm\Models\EmployeeQrCode;
use App\Domains\Sdm\Services\AttendanceRecordAggregator;
use App\Domains\Sdm\Services\KalenderKerjaSdmResolver;
use Illuminate\Support\Facades\DB;

final class ScanQrAttendanceAction
{
    public function __construct(
        private readonly AttendanceRecordAggregator $aggregator,
        private readonly KalenderKerjaSdmResolver $kalenderResolver,
    ) {}

    public function execute(ScanQrAttendanceData $data): AttendanceEvent
    {
        $qrCode = EmployeeQrCode::where('token', $data->token)->where('is_active', true)->first();

        if (! $qrCode) {
            throw new InvalidQrTokenException();
        }

        $pegawai = $qrCode->pegawai;

        if (! $pegawai || (int) $pegawai->lembaga_id !== $data->lembagaId) {
            throw new QrTokenLembagaMismatchException();
        }

        $resolusi = $this->kalenderResolver->resolve($pegawai->lembaga, now());

        if ($resolusi['libur']) {
            throw new AttendanceOnHolidayException($resolusi['alasan']);
        }

        return DB::transaction(function () use ($pegawai, $data) {
            $event = $pegawai->attendanceEvents()->create([
                'lembaga_id' => $data->lembagaId,
                'attendance_point_id' => $data->attendancePointId,
                'method' => AttendanceMethod::Qr,
                'arah' => $data->arah,
                'status' => AttendanceStatus::Hadir,
                'waktu' => now(),
                'dicatat_oleh_user_id' => $data->dicatatOlehUserId,
            ]);

            $this->aggregator->sync($pegawai, now()->toImmutable());

            return $event;
        });
    }
}
```

- [ ] **Step 2: Tambah test di akhir `tests/Feature/Sdm/ScanQrAttendanceActionTest.php`**

File ini SUDAH ADA dari Sub-project 1 dengan 3 test — JANGAN dihapus. Test lama sudah pakai `Guru::factory()->create(['lembaga_id' => $lembaga->id])` tanpa set `hari_libur_mingguan_sdm` eksplisit, sehingga defaultnya `[0]` (Minggu) berlaku — VERIFIKASI dulu tanggal `now()` saat CI berjalan tidak kebetulan hari Minggu (kalau khawatir flaky, boleh tambahkan `Carbon::setTestNow(Carbon::parse('2026-08-24'))` — Senin — di awal 3 test lama itu juga; putuskan saat implementasi berdasar apakah test lama lulus konsisten atau tidak, laporkan ke user kalau perlu mengubah test lama).

Tambahkan di akhir file:

```php
it('rejects a qr scan on a day the calendar resolver marks as libur, with no override path', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => range(0, 6)]); // every day is libur
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $petugas = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $qr = app(\App\Domains\Sdm\Actions\GenerateEmployeeQrTokenAction::class)->execute($guru);

    expect(fn () => app(ScanQrAttendanceAction::class)->execute(new ScanQrAttendanceData(
        token: $qr->token, arah: 'masuk', lembagaId: $lembaga->id, dicatatOlehUserId: $petugas->id,
    )))->toThrow(\App\Domains\Sdm\Exceptions\AttendanceOnHolidayException::class);

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeFalse();
});
```

- [ ] **Step 3: Jalankan test**

Run: `php artisan test tests/Feature/Sdm/ScanQrAttendanceActionTest.php`
Expected: 4 passed, 0 failed.

- [ ] **Step 4: Commit**

```bash
git add app/Domains/Sdm/Actions/ScanQrAttendanceAction.php tests/Feature/Sdm/ScanQrAttendanceActionTest.php
git commit -m "feat(sdm): tolak scan QR di hari libur kalender kerja SDM tanpa jalur override"
```

---

## Task 6: Command Terjadwal `sdm:tandai-alpa-otomatis`

**Files:**
- Create: `app/Console/Commands/TandaiAlpaOtomatisSdm.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Sdm/TandaiAlpaOtomatisSdmTest.php`

**Interfaces:**
- Consumes: `KalenderKerjaSdmResolver` (Task 2), `AttendanceRecordAggregator` (Sub-project 1).
- Produces: command `sdm:tandai-alpa-otomatis`, terjadwal `dailyAt('01:00')`.

- [ ] **Step 1: Buat command `TandaiAlpaOtomatisSdm`**

```php
<?php

namespace App\Console\Commands;

use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Domains\Sdm\Enums\AttendanceStatus;
use App\Domains\Sdm\Models\AttendanceRecord;
use App\Domains\Sdm\Services\AttendanceRecordAggregator;
use App\Domains\Sdm\Services\KalenderKerjaSdmResolver;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use Illuminate\Console\Command;

class TandaiAlpaOtomatisSdm extends Command
{
    protected $signature = 'sdm:tandai-alpa-otomatis';

    protected $description = 'Tandai pegawai aktif sebagai Alpa untuk hari kerja kemarin (H-1) yang sama sekali tidak punya AttendanceRecord';

    public function __construct(
        private readonly KalenderKerjaSdmResolver $resolver,
        private readonly AttendanceRecordAggregator $aggregator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tanggal = now()->subDay()->toImmutable();
        $jumlahDitandai = 0;

        foreach (Lembaga::all() as $lembaga) {
            $resolusi = $this->resolver->resolve($lembaga, $tanggal);

            if ($resolusi['libur']) {
                continue;
            }

            $pegawaiList = collect()
                ->concat(Guru::where('lembaga_id', $lembaga->id)->where('status_aktif', 'aktif')->get())
                ->concat(Karyawan::where('lembaga_id', $lembaga->id)->where('status_aktif', 'aktif')->get());

            foreach ($pegawaiList as $pegawai) {
                $sudahAda = AttendanceRecord::where('pegawai_type', $pegawai::class)
                    ->where('pegawai_id', $pegawai->id)
                    ->whereDate('tanggal', $tanggal->toDateString())
                    ->exists();

                if ($sudahAda) {
                    continue;
                }

                $pegawai->attendanceEvents()->create([
                    'lembaga_id' => $lembaga->id,
                    'method' => AttendanceMethod::System,
                    'arah' => 'masuk',
                    'status' => AttendanceStatus::Alpa,
                    'waktu' => $tanggal->setTime(23, 59),
                    'dicatat_oleh_user_id' => null,
                    'catatan' => 'Ditandai otomatis oleh sistem — tidak ada aktivitas kehadiran pada hari kerja ini.',
                ]);

                $this->aggregator->sync($pegawai, $tanggal);
                $jumlahDitandai++;
            }
        }

        $this->info("{$jumlahDitandai} pegawai ditandai Alpa otomatis untuk tanggal {$tanggal->toDateString()}.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 2: Daftarkan jadwal di `routes/console.php`**

Cari baris:

```php
use App\Console\Commands\TandaiTugasTerlewat;
```

Tambahkan setelahnya:

```php
use App\Console\Commands\TandaiAlpaOtomatisSdm;
```

Cari baris:

```php
Schedule::command(TandaiTugasTerlewat::class)->dailyAt('01:00');
```

Tambahkan setelahnya:

```php
Schedule::command(TandaiAlpaOtomatisSdm::class)->dailyAt('01:00');
```

- [ ] **Step 3: Tulis test**

```php
<?php
// tests/Feature/Sdm/TandaiAlpaOtomatisSdmTest.php

use App\Domains\Sdm\Enums\AttendanceStatus;
use App\Domains\Sdm\Models\AttendanceRecord;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Carbon\Carbon;

it('marks an active guru with no attendance record as Alpa for a work-day yesterday', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-25 01:00:00')); // Tuesday
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => 'aktif']);

    $this->artisan('sdm:tandai-alpa-otomatis')->assertSuccessful();

    $record = AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->first();
    expect($record)->not->toBeNull();
    expect($record->status)->toBe(AttendanceStatus::Alpa);
    expect($record->tanggal->toDateString())->toBe('2026-08-24'); // Monday, H-1

    Carbon::setTestNow();
});

it('does not mark anyone Alpa for a lembaga whose yesterday was a libur day', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-24 01:00:00')); // Monday, so H-1 = Sunday
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => 'aktif']);

    $this->artisan('sdm:tandai-alpa-otomatis')->assertSuccessful();

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeFalse();

    Carbon::setTestNow();
});

it('does not mark a karyawan with status_aktif non_aktif', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-25 01:00:00')); // Tuesday
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $karyawan = Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'yayasan_id' => $yayasan->id, 'status_aktif' => 'non_aktif']);

    $this->artisan('sdm:tandai-alpa-otomatis')->assertSuccessful();

    expect(AttendanceRecord::where('pegawai_type', Karyawan::class)->where('pegawai_id', $karyawan->id)->exists())->toBeFalse();

    Carbon::setTestNow();
});

it('is idempotent when run twice for the same day', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-25 01:00:00')); // Tuesday
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => 'aktif']);

    $this->artisan('sdm:tandai-alpa-otomatis')->assertSuccessful();
    $this->artisan('sdm:tandai-alpa-otomatis')->assertSuccessful();

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->count())->toBe(1);

    Carbon::setTestNow();
});

it('skips a guru who already has a manual attendance record for that day', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-25 01:00:00')); // Tuesday
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => 'aktif']);
    $admin = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);

    app(\App\Domains\Sdm\Actions\RecordManualAttendanceAction::class)->execute($guru, new \App\Domains\Sdm\DataTransferObjects\RecordManualAttendanceData(
        lembagaId: $lembaga->id, arah: 'masuk', status: AttendanceStatus::Hadir,
        waktu: \Carbon\CarbonImmutable::parse('2026-08-24 07:00:00'), dicatatOlehUserId: $admin->id,
    ));

    $this->artisan('sdm:tandai-alpa-otomatis')->assertSuccessful();

    $record = AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->first();
    expect($record->status)->toBe(AttendanceStatus::Hadir);

    Carbon::setTestNow();
});
```

- [ ] **Step 4: Jalankan test**

Run: `php artisan test tests/Feature/Sdm/TandaiAlpaOtomatisSdmTest.php`
Expected: 5 passed, 0 failed.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/TandaiAlpaOtomatisSdm.php routes/console.php tests/Feature/Sdm/TandaiAlpaOtomatisSdmTest.php
git commit -m "feat(sdm): tambah command terjadwal sdm:tandai-alpa-otomatis (dailyAt 01:00)"
```

---

## Task 7: `CopyKalenderAkademikNasionalAction`

**Files:**
- Create: `app/Domains/Sdm/Actions/CopyKalenderAkademikNasionalAction.php`
- Test: `tests/Feature/Sdm/CopyKalenderAkademikNasionalActionTest.php`

**Interfaces:**
- Consumes: `App\Domains\Akademik\Models\KalenderAkademik` (existing, READ-ONLY), `KalenderKerjaSdm` (Task 2).
- Produces: `CopyKalenderAkademikNasionalAction::execute(int $yayasanId, array $kalenderAkademikIds): \Illuminate\Support\Collection` — dipakai Task 8 (`AttendanceConfigurationController`).

- [ ] **Step 1: Buat `CopyKalenderAkademikNasionalAction`**

Pemetaan tipe: `TipeKalenderAkademik::Libur` → `TipeKalenderKerjaSdm::Libur`, `TipeKalenderAkademik::Kerja` → `TipeKalenderKerjaSdm::Kerja` (nilai string keduanya SAMA persis — `'libur'`/`'kerja'` — jadi cukup `TipeKalenderKerjaSdm::from($entriAkademik->tipe->value)`, TIDAK perlu match manual). Skip entri yang `id`-nya bukan entri nasional (`lembaga_id !== null`) — pertahanan tambahan kalau ada ID nyasar dikirim.

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions;

use App\Domains\Akademik\Models\KalenderAkademik;
use App\Domains\Sdm\Enums\TipeKalenderKerjaSdm;
use App\Domains\Sdm\Models\KalenderKerjaSdm;
use App\Models\Scopes\TenantScope;
use Illuminate\Support\Collection;

final class CopyKalenderAkademikNasionalAction
{
    /**
     * @param  array<int, int>  $kalenderAkademikIds
     */
    public function execute(int $yayasanId, array $kalenderAkademikIds): Collection
    {
        $entriAkademikList = KalenderAkademik::nasional()->whereIn('id', $kalenderAkademikIds)->get();

        return $entriAkademikList->map(function (KalenderAkademik $entriAkademik) use ($yayasanId) {
            $sudahAda = KalenderKerjaSdm::withoutGlobalScope(TenantScope::class)
                ->whereNull('lembaga_id')
                ->where('yayasan_id', $yayasanId)
                ->where('tanggal', $entriAkademik->tanggal->toDateString())
                ->where('nama', $entriAkademik->nama)
                ->exists();

            if ($sudahAda) {
                return null;
            }

            return KalenderKerjaSdm::create([
                'yayasan_id' => $yayasanId,
                'lembaga_id' => null,
                'tanggal' => $entriAkademik->tanggal,
                'tanggal_selesai' => $entriAkademik->tanggal_selesai,
                'nama' => $entriAkademik->nama,
                'tipe' => TipeKalenderKerjaSdm::from($entriAkademik->tipe->value),
                'keterangan' => $entriAkademik->keterangan,
            ]);
        })->filter();
    }
}
```

- [ ] **Step 2: Tulis test**

```php
<?php
// tests/Feature/Sdm/CopyKalenderAkademikNasionalActionTest.php

use App\Domains\Akademik\Models\KalenderAkademik;
use App\Domains\Sdm\Actions\CopyKalenderAkademikNasionalAction;
use App\Domains\Sdm\Models\KalenderKerjaSdm;
use App\Models\Yayasan;

it('copies a national academic calendar entry into an independent SDM calendar entry', function () {
    $yayasan = Yayasan::factory()->create();
    $entriAkademik = KalenderAkademik::create(['lembaga_id' => null, 'tanggal' => '2026-08-17', 'nama' => 'Hari Kemerdekaan RI', 'tipe' => 'libur']);

    $hasil = app(CopyKalenderAkademikNasionalAction::class)->execute($yayasan->id, [$entriAkademik->id]);

    expect($hasil)->toHaveCount(1);
    expect(KalenderKerjaSdm::where('yayasan_id', $yayasan->id)->whereNull('lembaga_id')->where('nama', 'Hari Kemerdekaan RI')->exists())->toBeTrue();
});

it('does not create a duplicate when the same entry is copied twice', function () {
    $yayasan = Yayasan::factory()->create();
    $entriAkademik = KalenderAkademik::create(['lembaga_id' => null, 'tanggal' => '2026-08-17', 'nama' => 'Hari Kemerdekaan RI', 'tipe' => 'libur']);
    $action = app(CopyKalenderAkademikNasionalAction::class);

    $action->execute($yayasan->id, [$entriAkademik->id]);
    $hasilKedua = $action->execute($yayasan->id, [$entriAkademik->id]);

    expect($hasilKedua)->toHaveCount(0);
    expect(KalenderKerjaSdm::where('yayasan_id', $yayasan->id)->where('nama', 'Hari Kemerdekaan RI')->count())->toBe(1);
});

it('keeps the copied SDM entry independent from the original academic entry after copying', function () {
    $yayasan = Yayasan::factory()->create();
    $entriAkademik = KalenderAkademik::create(['lembaga_id' => null, 'tanggal' => '2026-08-17', 'nama' => 'Hari Kemerdekaan RI', 'tipe' => 'libur']);
    app(CopyKalenderAkademikNasionalAction::class)->execute($yayasan->id, [$entriAkademik->id]);

    $entriAkademik->update(['nama' => 'Nama Diubah di Akademik']);

    $entriSdm = KalenderKerjaSdm::where('yayasan_id', $yayasan->id)->where('nama', 'Hari Kemerdekaan RI')->first();
    expect($entriSdm)->not->toBeNull();
});
```

- [ ] **Step 3: Jalankan test**

Run: `php artisan test tests/Feature/Sdm/CopyKalenderAkademikNasionalActionTest.php`
Expected: 3 passed, 0 failed.

- [ ] **Step 4: Commit**

```bash
git add app/Domains/Sdm/Actions/CopyKalenderAkademikNasionalAction.php tests/Feature/Sdm/CopyKalenderAkademikNasionalActionTest.php
git commit -m "feat(sdm): tambah CopyKalenderAkademikNasionalAction (salin snapshot, bukan live-sync)"
```

---

## Task 8: Perluas `AttendanceConfigurationController` (Kalender Kerja) + Routes

**Files:**
- Modify: `app/Http/Controllers/Admin/AttendanceConfigurationController.php`
- Modify: `routes/admin/kehadiran-sdm.php`
- Test: `tests/Feature/Admin/AttendanceConfigurationKalenderControllerTest.php`

**Interfaces:**
- Consumes: `SetHariLiburMingguanSdmAction` (Task 3), `CopyKalenderAkademikNasionalAction` (Task 7), `KalenderKerjaSdm` (Task 2).
- Produces: route `admin.kehadiran-sdm.kalender.hari-kerja` (PUT), `admin.kehadiran-sdm.kalender.entri.store`/`.update`/`.destroy`, `admin.kehadiran-sdm.kalender.salin-tersedia` (GET), `admin.kehadiran-sdm.kalender.salin` (POST) — dipakai Task 9 (view).

- [ ] **Step 1: Perluas `AttendanceConfigurationController`**

Baca dulu isi file saat ini (sudah ada dari Sub-project 1) untuk memastikan `use` statement dan method `resolveLembagaId()`/`resolveYayasanId()` masih persis sama sebelum menambahkan. Tambahkan `use` statement baru dan 6 method baru SETELAH method `destroyTitik()` yang sudah ada, SEBELUM `resolveLembagaId()`:

Tambahkan ke blok `use` di bagian atas file:

```php
use App\Domains\Akademik\Models\KalenderAkademik;
use App\Domains\Sdm\Actions\CopyKalenderAkademikNasionalAction;
use App\Domains\Sdm\Actions\SetHariLiburMingguanSdmAction;
use App\Domains\Sdm\DataTransferObjects\HariKerjaSdmData;
use App\Domains\Sdm\Enums\TipeKalenderKerjaSdm;
use App\Domains\Sdm\Models\KalenderKerjaSdm;
use Illuminate\Http\JsonResponse;
```

Ubah method `index()` — tambahkan data kalender kerja ke array view. Method `index()` yang sudah ada saat ini:

```php
    public function index(Request $request): View
    {
        $this->authorize('kehadiran-sdm.view');

        $lembagaId = $this->resolveLembagaId($request);
        $yayasanId = $this->resolveYayasanId($request, $lembagaId);

        $konfigurasi = AttendanceMethodConfiguration::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $yayasanId)
            ->where(function ($query) use ($lembagaId) {
                $query->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            })
            ->get();

        $titikAbsen = $lembagaId ? AttendancePoint::where('lembaga_id', $lembagaId)->orderBy('nama')->get() : collect();

        return view('admin.kehadiran-sdm.konfigurasi', [
            'methods' => AttendanceMethod::cases(),
            'konfigurasi' => $konfigurasi,
            'titikAbsen' => $titikAbsen,
            'lembagaId' => $lembagaId,
        ]);
    }
```

Ganti jadi (tambahan: `$lembaga`, `$kalenderEntriList`, `$bolehKelolaNasional`):

```php
    public function index(Request $request): View
    {
        $this->authorize('kehadiran-sdm.view');

        $lembagaId = $this->resolveLembagaId($request);
        $yayasanId = $this->resolveYayasanId($request, $lembagaId);

        $konfigurasi = AttendanceMethodConfiguration::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $yayasanId)
            ->where(function ($query) use ($lembagaId) {
                $query->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            })
            ->get();

        $titikAbsen = $lembagaId ? AttendancePoint::where('lembaga_id', $lembagaId)->orderBy('nama')->get() : collect();

        $lembaga = $lembagaId ? Lembaga::find($lembagaId) : null;

        $kalenderEntriList = $yayasanId ? KalenderKerjaSdm::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $yayasanId)
            ->where(function ($query) use ($lembagaId) {
                $query->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            })
            ->orderBy('tanggal')
            ->get() : collect();

        return view('admin.kehadiran-sdm.konfigurasi', [
            'methods' => AttendanceMethod::cases(),
            'konfigurasi' => $konfigurasi,
            'titikAbsen' => $titikAbsen,
            'lembagaId' => $lembagaId,
            'lembaga' => $lembaga,
            'kalenderEntriList' => $kalenderEntriList,
            'tipeKalenderOptions' => TipeKalenderKerjaSdm::cases(),
            'bolehKelolaNasional' => $request->user()->widestScopeLevel() === 'yayasan',
        ]);
    }
```

Tambahkan 6 method baru setelah `destroyTitik()`:

```php
    public function updateHariKerja(Request $request, SetHariLiburMingguanSdmAction $action): JsonResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        $data = $request->validate([
            'hari_kerja' => ['present', 'array'],
            'hari_kerja.*' => ['integer', 'between:0,6'],
        ]);

        $lembagaId = $this->resolveLembagaId($request);

        if ($lembagaId === null) {
            return response()->json(['message' => 'Pilih lembaga aktif melalui pengalih lembaga terlebih dahulu.'], 422);
        }

        $lembaga = Lembaga::findOrFail($lembagaId);
        $lembaga = $action->execute($lembaga, new HariKerjaSdmData(hariKerja: $data['hari_kerja']));

        return response()->json(['data' => ['hari_libur_mingguan_sdm' => $lembaga->hari_libur_mingguan_sdm]]);
    }

    public function storeKalenderEntri(Request $request): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'tanggal' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal'],
            'tipe' => ['required', 'in:libur,kerja'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'is_nasional' => ['nullable', 'boolean'],
        ]);

        $isNasional = (bool) ($data['is_nasional'] ?? false);

        if ($isNasional && $request->user()->widestScopeLevel() !== 'yayasan') {
            abort(403, 'Hanya aktor berscope yayasan yang boleh membuat entri kalender nasional.');
        }

        $lembagaId = $isNasional ? null : $this->resolveLembagaId($request);

        if (! $isNasional && $lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah entri kalender.']);
        }

        $yayasanId = $this->resolveYayasanId($request, $lembagaId);

        KalenderKerjaSdm::create([
            'yayasan_id' => $yayasanId,
            'lembaga_id' => $lembagaId,
            'tanggal' => $data['tanggal'],
            'tanggal_selesai' => $data['tanggal_selesai'] ?? null,
            'nama' => $data['nama'],
            'tipe' => $data['tipe'],
            'keterangan' => $data['keterangan'] ?? null,
        ]);

        return back()->with('status', 'Entri kalender kerja berhasil ditambahkan.');
    }

    public function updateKalenderEntri(Request $request, KalenderKerjaSdm $entri): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        if ($entri->lembaga_id === null && $request->user()->widestScopeLevel() !== 'yayasan') {
            abort(403, 'Hanya aktor berscope yayasan yang boleh mengubah entri kalender nasional.');
        }

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'tanggal' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal'],
            'tipe' => ['required', 'in:libur,kerja'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        $entri->update($data);

        return back()->with('status', 'Entri kalender kerja berhasil diperbarui.');
    }

    public function destroyKalenderEntri(Request $request, KalenderKerjaSdm $entri): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        if ($entri->lembaga_id === null && $request->user()->widestScopeLevel() !== 'yayasan') {
            abort(403, 'Hanya aktor berscope yayasan yang boleh menghapus entri kalender nasional.');
        }

        $entri->delete();

        return back()->with('status', 'Entri kalender kerja berhasil dihapus.');
    }

    public function kalenderSalinTersedia(Request $request): JsonResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');
        abort_unless($request->user()->widestScopeLevel() === 'yayasan', 403);

        $yayasanId = $request->user()->yayasan_id;

        // Dedup key = tanggal+nama (BUKAN nama saja) — supaya entri tahunan berulang (mis.
        // "Hari Kemerdekaan RI" tiap tahun beda tanggal) tidak keliru dianggap "sudah pernah
        // disalin" hanya karena namanya sama persis. Harus konsisten dengan dedup key yang
        // dipakai CopyKalenderAkademikNasionalAction (Task 7), bukan dedup nama saja.
        $sudahDisalinKey = KalenderKerjaSdm::withoutGlobalScope(TenantScope::class)
            ->whereNull('lembaga_id')
            ->where('yayasan_id', $yayasanId)
            ->get(['tanggal', 'nama'])
            ->map(fn ($entri) => $entri->tanggal->toDateString().'|'.$entri->nama);

        $tersedia = KalenderAkademik::nasional()
            ->orderBy('tanggal')
            ->get(['id', 'nama', 'tanggal', 'tipe'])
            ->reject(fn ($entri) => $sudahDisalinKey->contains($entri->tanggal->toDateString().'|'.$entri->nama))
            ->values();

        return response()->json(['items' => $tersedia]);
    }

    public function kalenderSalin(Request $request, CopyKalenderAkademikNasionalAction $action): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');
        abort_unless($request->user()->widestScopeLevel() === 'yayasan', 403);

        $data = $request->validate([
            'kalender_akademik_ids' => ['required', 'array', 'min:1'],
            'kalender_akademik_ids.*' => ['integer'],
        ]);

        $disalin = $action->execute($request->user()->yayasan_id, $data['kalender_akademik_ids']);

        return back()->with('status', "{$disalin->count()} entri kalender berhasil disalin dari kalender akademik.");
    }
```

- [ ] **Step 2: Tambah 6 route baru ke `routes/admin/kehadiran-sdm.php`**

Cari baris:

```php
Route::delete('kehadiran-sdm/konfigurasi/titik/{titik}', [AttendanceConfigurationController::class, 'destroyTitik'])->name('kehadiran-sdm.titik.destroy');
```

Tambahkan setelahnya:

```php
Route::put('kehadiran-sdm/konfigurasi/kalender/hari-kerja', [AttendanceConfigurationController::class, 'updateHariKerja'])->name('kehadiran-sdm.kalender.hari-kerja');
Route::post('kehadiran-sdm/konfigurasi/kalender/entri', [AttendanceConfigurationController::class, 'storeKalenderEntri'])->name('kehadiran-sdm.kalender.entri.store');
Route::put('kehadiran-sdm/konfigurasi/kalender/entri/{entri}', [AttendanceConfigurationController::class, 'updateKalenderEntri'])->name('kehadiran-sdm.kalender.entri.update');
Route::delete('kehadiran-sdm/konfigurasi/kalender/entri/{entri}', [AttendanceConfigurationController::class, 'destroyKalenderEntri'])->name('kehadiran-sdm.kalender.entri.destroy');
Route::get('kehadiran-sdm/konfigurasi/kalender/salin-tersedia', [AttendanceConfigurationController::class, 'kalenderSalinTersedia'])->name('kehadiran-sdm.kalender.salin-tersedia');
Route::post('kehadiran-sdm/konfigurasi/kalender/salin', [AttendanceConfigurationController::class, 'kalenderSalin'])->name('kehadiran-sdm.kalender.salin');
```

- [ ] **Step 3: Tulis test**

```php
<?php
// tests/Feature/Admin/AttendanceConfigurationKalenderControllerTest.php

use App\Domains\Akademik\Models\KalenderAkademik;
use App\Domains\Sdm\Models\KalenderKerjaSdm;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

if (! function_exists('actingAsAdminSdmKalender')) {
    function actingAsAdminSdmKalender(Lembaga $lembaga): User
    {
        foreach (['kehadiran-sdm.view', 'kehadiran-sdm.kelola-konfigurasi'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $role = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
        $role->givePermissionTo(['kehadiran-sdm.view', 'kehadiran-sdm.kelola-konfigurasi']);

        $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $user->assignRole($role);

        return $user;
    }
}

if (! function_exists('actingAsYayasanSuperAdminKalender')) {
    function actingAsYayasanSuperAdminKalender(Yayasan $yayasan): User
    {
        $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
        $role->givePermissionTo(Permission::all());

        $user = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
        $user->assignRole($role);

        return $user;
    }
}

it('lets an admin_sdm update the weekly work-day pattern for their own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = actingAsAdminSdmKalender($lembaga);

    $this->actingAs($admin)->putJson(route('admin.kehadiran-sdm.kalender.hari-kerja'), [
        'hari_kerja' => [1, 2, 3, 4, 5],
    ])->assertOk()->assertJson(['data' => ['hari_libur_mingguan_sdm' => [0, 6]]]);
});

it('lets an admin_sdm create a lembaga-specific calendar entry', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = actingAsAdminSdmKalender($lembaga);

    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.kalender.entri.store'), [
        'nama' => 'Rapat Internal', 'tanggal' => '2026-09-01', 'tipe' => 'kerja',
    ])->assertRedirect();

    expect(KalenderKerjaSdm::where('lembaga_id', $lembaga->id)->where('nama', 'Rapat Internal')->exists())->toBeTrue();
});

it('rejects an admin_sdm (scope_level lembaga) trying to create a national calendar entry', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = actingAsAdminSdmKalender($lembaga);

    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.kalender.entri.store'), [
        'nama' => 'Cuti Bersama', 'tanggal' => '2026-09-01', 'tipe' => 'libur', 'is_nasional' => true,
    ])->assertForbidden();

    expect(KalenderKerjaSdm::whereNull('lembaga_id')->where('nama', 'Cuti Bersama')->exists())->toBeFalse();
});

it('lets a yayasan-scope actor create a national calendar entry', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $actor = actingAsYayasanSuperAdminKalender($yayasan);

    session(['active_lembaga_id' => $lembaga->id]);

    $this->actingAs($actor)->post(route('admin.kehadiran-sdm.kalender.entri.store'), [
        'nama' => 'Cuti Bersama', 'tanggal' => '2026-09-01', 'tipe' => 'libur', 'is_nasional' => true,
    ])->assertRedirect();

    expect(KalenderKerjaSdm::withoutGlobalScopes()->whereNull('lembaga_id')->where('nama', 'Cuti Bersama')->exists())->toBeTrue();
});

it('lists academic national entries not yet copied, and excludes ones already copied', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $actor = actingAsYayasanSuperAdminKalender($yayasan);
    session(['active_lembaga_id' => $lembaga->id]);

    KalenderAkademik::create(['lembaga_id' => null, 'tanggal' => '2026-08-17', 'nama' => 'Hari Kemerdekaan RI', 'tipe' => 'libur']);
    KalenderAkademik::create(['lembaga_id' => null, 'tanggal' => '2026-12-25', 'nama' => 'Hari Natal', 'tipe' => 'libur']);
    KalenderKerjaSdm::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null, 'tanggal' => '2026-12-25', 'nama' => 'Hari Natal', 'tipe' => 'libur']);

    $response = $this->actingAs($actor)->getJson(route('admin.kehadiran-sdm.kalender.salin-tersedia'));

    $response->assertOk();
    $items = $response->json('items');
    expect(collect($items)->pluck('nama')->all())->toBe(['Hari Kemerdekaan RI']);
});

it('copies selected academic entries into SDM calendar entries via the salin endpoint', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $actor = actingAsYayasanSuperAdminKalender($yayasan);
    session(['active_lembaga_id' => $lembaga->id]);

    $entriAkademik = KalenderAkademik::create(['lembaga_id' => null, 'tanggal' => '2026-08-17', 'nama' => 'Hari Kemerdekaan RI', 'tipe' => 'libur']);

    $this->actingAs($actor)->post(route('admin.kehadiran-sdm.kalender.salin'), [
        'kalender_akademik_ids' => [$entriAkademik->id],
    ])->assertRedirect();

    expect(KalenderKerjaSdm::withoutGlobalScopes()->where('yayasan_id', $yayasan->id)->where('nama', 'Hari Kemerdekaan RI')->exists())->toBeTrue();
});
```

- [ ] **Step 4: Jalankan test**

Run: `php artisan test tests/Feature/Admin/AttendanceConfigurationKalenderControllerTest.php`
Expected: 6 passed, 0 failed.

- [ ] **Step 5: Jalankan ulang test Sub-project 1 yang menyentuh controller ini untuk pastikan tidak regresi**

Run: `php artisan test tests/Feature/Admin/AttendanceConfigurationControllerTest.php`
Expected: 4 passed, 0 failed (tidak berubah dari Sub-project 1).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/AttendanceConfigurationController.php routes/admin/kehadiran-sdm.php tests/Feature/Admin/AttendanceConfigurationKalenderControllerTest.php
git commit -m "feat(sdm): perluas AttendanceConfigurationController dengan endpoint kalender kerja (hari kerja, CRUD entri, salin dari akademik)"
```

---

## Task 9: View — Tab "Kalender Kerja" + Checkbox Override + Pesan Error Libur

**Files:**
- Modify: `resources/views/admin/kehadiran-sdm/konfigurasi.blade.php`
- Modify: `resources/views/admin/kehadiran-sdm/create.blade.php`
- Modify: `app/Http/Controllers/Admin/AttendanceController.php`
- Modify: `app/Http/Controllers/Admin/AttendanceQrScanController.php`
- Test: `tests/Feature/Admin/AttendanceKonfigurasiKalenderViewTest.php`
- Test: `tests/Feature/Admin/AttendanceHolidayOverrideViewTest.php`

**Interfaces:**
- Consumes: semua endpoint Task 8, `AttendanceOnHolidayException` (Task 4).

- [ ] **Step 1: Ubah `AttendanceController::store()` untuk tangani `AttendanceOnHolidayException` + terima `override_hari_libur`**

Baca dulu isi file saat ini. Method `store()` yang sudah ada:

```php
    public function store(Request $request, RecordManualAttendanceAction $action): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.catat');

        $data = $request->validate([
            'pegawai_tipe' => ['required', 'in:guru,karyawan'],
            'pegawai_id' => ['required', 'integer'],
            'arah' => ['required', 'in:masuk,pulang'],
            'status' => ['required', 'in:hadir,izin,sakit,alpa'],
            'waktu' => ['required', 'date'],
            'attendance_point_id' => ['nullable', 'integer', 'exists:attendance_points,id'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        $lembagaId = $this->resolveLembagaId($request);

        if ($lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga terlebih dahulu.'])->withInput();
        }

        $pegawaiModel = $data['pegawai_tipe'] === 'guru' ? Guru::class : Karyawan::class;
        $pegawai = $pegawaiModel::find($data['pegawai_id']);

        abort_if($pegawai === null || (int) $pegawai->lembaga_id !== $lembagaId, 404, 'Pegawai tidak ditemukan di lembaga aktif Anda.');

        $action->execute($pegawai, new RecordManualAttendanceData(
            lembagaId: $lembagaId,
            arah: $data['arah'],
            status: AttendanceStatus::from($data['status']),
            waktu: CarbonImmutable::parse($data['waktu']),
            dicatatOlehUserId: $request->user()->id,
            attendancePointId: $data['attendance_point_id'] ?? null,
            catatan: $data['catatan'] ?? null,
        ));

        return redirect()->route('admin.kehadiran-sdm.index')->with('status', 'Kehadiran berhasil dicatat.');
    }
```

Ganti jadi (tambah validasi `override_hari_libur`, DTO param baru, try/catch):

```php
    public function store(Request $request, RecordManualAttendanceAction $action): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.catat');

        $data = $request->validate([
            'pegawai_tipe' => ['required', 'in:guru,karyawan'],
            'pegawai_id' => ['required', 'integer'],
            'arah' => ['required', 'in:masuk,pulang'],
            'status' => ['required', 'in:hadir,izin,sakit,alpa'],
            'waktu' => ['required', 'date'],
            'attendance_point_id' => ['nullable', 'integer', 'exists:attendance_points,id'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'override_hari_libur' => ['nullable', 'boolean'],
        ]);

        $lembagaId = $this->resolveLembagaId($request);

        if ($lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga terlebih dahulu.'])->withInput();
        }

        $pegawaiModel = $data['pegawai_tipe'] === 'guru' ? Guru::class : Karyawan::class;
        $pegawai = $pegawaiModel::find($data['pegawai_id']);

        abort_if($pegawai === null || (int) $pegawai->lembaga_id !== $lembagaId, 404, 'Pegawai tidak ditemukan di lembaga aktif Anda.');

        try {
            $action->execute($pegawai, new RecordManualAttendanceData(
                lembagaId: $lembagaId,
                arah: $data['arah'],
                status: AttendanceStatus::from($data['status']),
                waktu: CarbonImmutable::parse($data['waktu']),
                dicatatOlehUserId: $request->user()->id,
                attendancePointId: $data['attendance_point_id'] ?? null,
                catatan: $data['catatan'] ?? null,
                overrideHariLibur: (bool) ($data['override_hari_libur'] ?? false),
            ));
        } catch (\App\Domains\Sdm\Exceptions\AttendanceOnHolidayException $exception) {
            return back()->withErrors(['tanggal' => $exception->getMessage().' Centang "Tetap catat meski hari libur" kalau ini disengaja.'])->withInput();
        }

        return redirect()->route('admin.kehadiran-sdm.index')->with('status', 'Kehadiran berhasil dicatat.');
    }
```

- [ ] **Step 2: Ubah `AttendanceQrScanController::store()` untuk tangani `AttendanceOnHolidayException`**

Cari baris:

```php
        } catch (InvalidQrTokenException|QrTokenLembagaMismatchException $exception) {
```

Ganti jadi:

```php
        } catch (InvalidQrTokenException|QrTokenLembagaMismatchException|\App\Domains\Sdm\Exceptions\AttendanceOnHolidayException $exception) {
```

- [ ] **Step 3: Tambah checkbox override di `resources/views/admin/kehadiran-sdm/create.blade.php`**

Cari blok:

```blade
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Catatan (opsional)</label>
                <textarea name="catatan" rows="2" class="w-full rounded-lg border-gray-200 text-sm"></textarea>
            </div>
```

Tambahkan SETELAH blok itu, SEBELUM blok tombol submit:

```blade
            <div class="flex items-center gap-2 rounded-lg bg-amber-50 border border-amber-100 px-3.5 py-3">
                <input type="checkbox" name="override_hari_libur" id="override_hari_libur" value="1" class="rounded border-gray-300">
                <label for="override_hari_libur" class="text-xs text-amber-800">Tetap catat meski hari ini libur menurut kalender kerja SDM (mis. lembur/acara khusus).</label>
            </div>
```

- [ ] **Step 4: Tambah tab "Kalender Kerja" di `resources/views/admin/kehadiran-sdm/konfigurasi.blade.php`**

Ganti SELURUH isi file dengan versi bertab (Metode Absensi + Titik Absen dijadikan 1 tab "Metode & Titik Absen", Kalender Kerja jadi tab baru):

```blade
<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6" x-data="{
        tab: 'metode',
        showTitikModal: false,
        editingTitik: null,
        formTitik: { nama: '' },
        openTitikModal(titik = null) {
            this.editingTitik = titik;
            this.formTitik = { nama: titik ? titik.nama : '' };
            this.showTitikModal = true;
        },
        showEntriModal: false,
        editingEntri: null,
        formEntri: { nama: '', tanggal: '', tanggal_selesai: '', tipe: 'libur', keterangan: '', is_nasional: false },
        openEntriModal(entri = null, nasional = false) {
            this.editingEntri = entri;
            this.formEntri = entri
                ? { nama: entri.nama, tanggal: entri.tanggal.split('T')[0], tanggal_selesai: entri.tanggal_selesai ? entri.tanggal_selesai.split('T')[0] : '', tipe: entri.tipe, keterangan: entri.keterangan ?? '', is_nasional: entri.lembaga_id === null }
                : { nama: '', tanggal: '', tanggal_selesai: '', tipe: 'libur', keterangan: '', is_nasional: nasional };
            this.showEntriModal = true;
        },
        hariKerja: @js($lembaga ? collect(range(0, 6))->reject(fn ($d) => in_array($d, $lembaga->hari_libur_mingguan_sdm ?? [], true))->values()->all() : [1,2,3,4,5]),
        savingHariKerja: false,
        toggleHari(day) {
            this.hariKerja = this.hariKerja.includes(day) ? this.hariKerja.filter((d) => d !== day) : [...this.hariKerja, day];
        },
        async simpanHariKerja() {
            this.savingHariKerja = true;
            try {
                const response = await fetch(@js(route('admin.kehadiran-sdm.kalender.hari-kerja')), {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ hari_kerja: this.hariKerja }),
                });
                if (!response.ok) { alert('Gagal menyimpan hari kerja.'); return; }
                alert('Hari kerja mingguan berhasil disimpan.');
            } finally {
                this.savingHariKerja = false;
            }
        },
        showSalinModal: false,
        entriTersedia: [],
        entriTercentang: [],
        async bukaSalinModal() {
            const response = await fetch(@js(route('admin.kehadiran-sdm.kalender.salin-tersedia')), { headers: { Accept: 'application/json' } });
            const json = await response.json();
            this.entriTersedia = json.items ?? [];
            this.entriTercentang = [];
            this.showSalinModal = true;
        }
    }">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-rose-100 bg-rose-50/50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif

        <div>
            <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">SDM &amp; Kepegawaian</p>
            <h1 class="mt-0.5 font-display text-xl font-bold tracking-tight text-gray-900">Konfigurasi Kehadiran SDM</h1>
        </div>

        <div class="flex items-center gap-1 border-b border-gray-200">
            <button type="button" @click="tab = 'metode'" :class="tab === 'metode' ? 'border-b-2 border-brand-500 text-brand-600' : 'text-gray-500 hover:text-gray-700'" class="rounded-t-lg px-4 py-2.5 text-sm font-semibold transition">Metode &amp; Titik Absen</button>
            <button type="button" @click="tab = 'kalender'" :class="tab === 'kalender' ? 'border-b-2 border-brand-500 text-brand-600' : 'text-gray-500 hover:text-gray-700'" class="rounded-t-lg px-4 py-2.5 text-sm font-semibold transition">Kalender Kerja</button>
        </div>

        {{-- Tab: Metode & Titik Absen --}}
        <div x-show="tab === 'metode'" class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
                <h2 class="font-display text-sm font-bold text-gray-900">Metode Absensi Aktif</h2>
                <p class="mt-1 text-xs text-gray-500">Input manual admin selalu tersedia sebagai fallback. Metode lain bisa diaktifkan/nonaktifkan per lembaga.</p>

                <div class="mt-4 space-y-3">
                    @foreach ($methods as $method)
                        @php
                            $existing = $konfigurasi->firstWhere('method', $method);
                            $isEnabled = $existing?->is_enabled ?? ($method->value === 'admin');
                        @endphp
                        @if ($method->value !== 'system')
                            <form method="POST" action="{{ route('admin.kehadiran-sdm.konfigurasi.metode') }}" class="flex items-center justify-between rounded-xl border border-gray-100 bg-gray-50/60 px-4 py-3">
                                @csrf
                                <input type="hidden" name="method" value="{{ $method->value }}">
                                <div>
                                    <p class="text-sm font-semibold text-gray-800">{{ $method->label() }}</p>
                                    @if ($method->value === 'admin')
                                        <p class="text-[11px] text-gray-400">Fallback wajib — tidak dapat dinonaktifkan.</p>
                                    @endif
                                </div>
                                @if ($method->value === 'admin')
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-800">Selalu Aktif</span>
                                @else
                                    <button type="submit" name="is_enabled" value="{{ $isEnabled ? '0' : '1' }}" class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $isEnabled ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-200 text-gray-600' }}">
                                        {{ $isEnabled ? 'Aktif' : 'Nonaktif' }}
                                    </button>
                                @endif
                            </form>
                        @endif
                    @endforeach
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-sm font-bold text-gray-900">Titik Absen</h2>
                    @can('kehadiran-sdm.kelola-konfigurasi')
                        <x-primary-button type="button" @click="openTitikModal()">+ Tambah Titik Absen</x-primary-button>
                    @endcan
                </div>

                <div class="mt-4 divide-y divide-gray-100">
                    @forelse ($titikAbsen as $titik)
                        <div class="flex items-center justify-between py-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">{{ $titik->nama }}</p>
                                <span class="text-[11px] {{ $titik->is_active ? 'text-emerald-600' : 'text-gray-400' }}">{{ $titik->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                @can('kehadiran-sdm.kelola-konfigurasi')
                                    <button type="button" @click="openTitikModal({{ $titik->toJson() }})" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Edit</button>
                                    <form method="POST" action="{{ route('admin.kehadiran-sdm.titik.destroy', $titik) }}" onsubmit="return confirm('Hapus titik absen ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-700">Hapus</button>
                                    </form>
                                @endcan
                            </div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-gray-400">Belum ada titik absen untuk lembaga ini.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Tab: Kalender Kerja --}}
        <div x-show="tab === 'kalender'" x-cloak class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
                <h2 class="font-display text-sm font-bold text-gray-900">Hari Kerja Mingguan SDM</h2>
                <p class="mt-1 text-xs text-gray-500">Terpisah dari hari aktif akademik — pegawai (TU, satpam, dst) bisa punya pola kerja beda dari jadwal siswa.</p>

                <div class="mt-4 grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                    @foreach ([1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 0 => 'Minggu'] as $hari => $label)
                        <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700">
                            <input type="checkbox" :checked="hariKerja.includes({{ $hari }})" @change="toggleHari({{ $hari }})" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>

                @can('kehadiran-sdm.kelola-konfigurasi')
                    <div class="mt-4">
                        <x-primary-button type="button" x-bind:disabled="savingHariKerja" @click="simpanHariKerja()">Simpan Hari Kerja</x-primary-button>
                    </div>
                @endcan
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-display text-sm font-bold text-gray-900">Entri Kalender (Libur / Tetap Masuk)</h2>
                    <div class="flex items-center gap-2">
                        @if ($bolehKelolaNasional)
                            <button type="button" @click="bukaSalinModal()" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Salin dari Kalender Akademik</button>
                        @endif
                        @can('kehadiran-sdm.kelola-konfigurasi')
                            <x-primary-button type="button" @click="openEntriModal(null, false)">+ Tambah Entri</x-primary-button>
                        @endcan
                    </div>
                </div>

                <div class="mt-4 divide-y divide-gray-100">
                    @forelse ($kalenderEntriList as $entri)
                        <div class="flex items-center justify-between py-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">
                                    {{ $entri->nama }}
                                    @if ($entri->lembaga_id === null)
                                        <span class="ml-1 inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700">Nasional</span>
                                    @endif
                                </p>
                                <p class="text-[11px] text-gray-400">
                                    {{ $entri->tanggal->format('d M Y') }}{{ $entri->tanggal_selesai ? ' — '.$entri->tanggal_selesai->format('d M Y') : '' }}
                                    · <span class="{{ $entri->tipe->value === 'libur' ? 'text-rose-600' : 'text-emerald-600' }}">{{ $entri->tipe->label() }}</span>
                                </p>
                            </div>
                            @can('kehadiran-sdm.kelola-konfigurasi')
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="openEntriModal({{ $entri->toJson() }})" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Edit</button>
                                    <form method="POST" action="{{ route('admin.kehadiran-sdm.kalender.entri.destroy', $entri) }}" onsubmit="return confirm('Hapus entri kalender ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-700">Hapus</button>
                                    </form>
                                </div>
                            @endcan
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-gray-400">Belum ada entri kalender kerja.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Modal Titik Absen (sudah ada dari Sub-project 1) --}}
        <div x-show="showTitikModal" class="fixed inset-0 z-50 flex items-center justify-center px-4" style="display: none;">
            <div class="fixed inset-0 bg-gray-900/60" @click="showTitikModal = false"></div>
            <div class="relative z-10 w-full max-w-sm rounded-2xl bg-white p-6 shadow-elevated">
                <h3 class="font-display text-base font-bold text-gray-900" x-text="editingTitik ? 'Edit Titik Absen' : 'Tambah Titik Absen'"></h3>
                <form method="POST" :action="editingTitik ? `/admin/kehadiran-sdm/konfigurasi/titik/${editingTitik.id}` : '{{ route('admin.kehadiran-sdm.titik.store') }}'" class="mt-4 space-y-4">
                    @csrf
                    <template x-if="editingTitik"><input type="hidden" name="_method" value="PUT"></template>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Nama Titik Absen</label>
                        <input x-model="formTitik.nama" name="nama" type="text" required placeholder="Contoh: Gerbang Utama" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <template x-if="editingTitik">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="is_active" value="1" x-bind:checked="true" class="rounded border-gray-300">
                            <label class="text-xs font-semibold text-gray-700">Aktif</label>
                        </div>
                    </template>
                    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <x-secondary-button type="button" @click="showTitikModal = false">Batal</x-secondary-button>
                        <x-primary-button type="submit">Simpan</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal Entri Kalender --}}
        <div x-show="showEntriModal" class="fixed inset-0 z-50 flex items-center justify-center px-4" style="display: none;">
            <div class="fixed inset-0 bg-gray-900/60" @click="showEntriModal = false"></div>
            <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-elevated">
                <h3 class="font-display text-base font-bold text-gray-900" x-text="editingEntri ? 'Edit Entri Kalender' : 'Tambah Entri Kalender'"></h3>
                <form method="POST" :action="editingEntri ? `/admin/kehadiran-sdm/konfigurasi/kalender/entri/${editingEntri.id}` : '{{ route('admin.kehadiran-sdm.kalender.entri.store') }}'" class="mt-4 space-y-4">
                    @csrf
                    <template x-if="editingEntri"><input type="hidden" name="_method" value="PUT"></template>
                    <template x-if="!editingEntri && formEntri.is_nasional"><input type="hidden" name="is_nasional" value="1"></template>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Nama</label>
                        <input x-model="formEntri.nama" name="nama" type="text" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Tanggal</label>
                            <input x-model="formEntri.tanggal" name="tanggal" type="date" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Sampai (opsional)</label>
                            <input x-model="formEntri.tanggal_selesai" name="tanggal_selesai" type="date" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Tipe</label>
                        <select x-model="formEntri.tipe" name="tipe" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm">
                            @foreach ($tipeKalenderOptions as $tipe)
                                <option value="{{ $tipe->value }}">{{ $tipe->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Keterangan (opsional)</label>
                        <textarea x-model="formEntri.keterangan" name="keterangan" rows="2" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <x-secondary-button type="button" @click="showEntriModal = false">Batal</x-secondary-button>
                        <x-primary-button type="submit">Simpan</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal Salin dari Kalender Akademik --}}
        <div x-show="showSalinModal" class="fixed inset-0 z-50 flex items-center justify-center px-4" style="display: none;">
            <div class="fixed inset-0 bg-gray-900/60" @click="showSalinModal = false"></div>
            <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-elevated">
                <h3 class="font-display text-base font-bold text-gray-900">Salin dari Kalender Akademik</h3>
                <p class="mt-1 text-xs text-gray-500">Hanya entri nasional yang belum pernah disalin.</p>
                <form method="POST" action="{{ route('admin.kehadiran-sdm.kalender.salin') }}" class="mt-4 space-y-3">
                    @csrf
                    <div class="max-h-64 space-y-2 overflow-y-auto">
                        <template x-if="entriTersedia.length === 0">
                            <p class="py-4 text-center text-sm text-gray-400">Tidak ada entri baru untuk disalin.</p>
                        </template>
                        <template x-for="item in entriTersedia" :key="item.id">
                            <label class="flex items-center gap-2 rounded-lg border border-gray-100 px-3 py-2 text-sm">
                                <input type="checkbox" name="kalender_akademik_ids[]" :value="item.id" x-model="entriTercentang">
                                <span x-text="item.nama + ' — ' + item.tanggal.split('T')[0]"></span>
                            </label>
                        </template>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <x-secondary-button type="button" @click="showSalinModal = false">Batal</x-secondary-button>
                        <x-primary-button type="submit" x-bind:disabled="entriTercentang.length === 0">Salin Terpilih</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 5: Tulis test `tests/Feature/Admin/AttendanceKonfigurasiKalenderViewTest.php`**

```php
<?php
// tests/Feature/Admin/AttendanceKonfigurasiKalenderViewTest.php

use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('renders the konfigurasi page with the Kalender Kerja tab present', function () {
    Permission::firstOrCreate(['name' => 'kehadiran-sdm.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('kehadiran-sdm.view');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    $this->actingAs($admin)->get(route('admin.kehadiran-sdm.konfigurasi.index'))
        ->assertOk()
        ->assertSee('Kalender Kerja')
        ->assertSee('Hari Kerja Mingguan SDM');
});
```

- [ ] **Step 6: Tulis test `tests/Feature/Admin/AttendanceHolidayOverrideViewTest.php`**

```php
<?php
// tests/Feature/Admin/AttendanceHolidayOverrideViewTest.php

use App\Domains\Sdm\Models\AttendanceRecord;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('shows a holiday error message and allows retry with override checkbox checked', function () {
    foreach (['kehadiran-sdm.view', 'kehadiran-sdm.catat'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kehadiran-sdm.view', 'kehadiran-sdm.catat']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    $payload = [
        'pegawai_tipe' => 'guru', 'pegawai_id' => $guru->id, 'arah' => 'masuk',
        'status' => 'hadir', 'waktu' => '2026-08-23 07:00:00', // Sunday
    ];

    $this->actingAs($admin)->from(route('admin.kehadiran-sdm.create'))->post(route('admin.kehadiran-sdm.store'), $payload)
        ->assertRedirect(route('admin.kehadiran-sdm.create'))
        ->assertSessionHasErrors('tanggal');

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeFalse();

    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.store'), $payload + ['override_hari_libur' => '1'])
        ->assertRedirect(route('admin.kehadiran-sdm.index'));

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeTrue();
});
```

- [ ] **Step 7: Jalankan semua test Task 9**

Run: `php artisan test tests/Feature/Admin/AttendanceKonfigurasiKalenderViewTest.php tests/Feature/Admin/AttendanceHolidayOverrideViewTest.php`
Expected: 2 passed, 0 failed.

- [ ] **Step 8: Jalankan ulang test view Sub-project 1 yang berpotensi tersentuh (index/create AttendanceController) untuk pastikan tidak regresi**

Run: `php artisan test tests/Feature/Admin/AttendanceControllerTest.php`
Expected: 3 passed, 0 failed (tidak berubah dari Sub-project 1).

- [ ] **Step 9: Commit**

```bash
git add resources/views/admin/kehadiran-sdm/konfigurasi.blade.php resources/views/admin/kehadiran-sdm/create.blade.php app/Http/Controllers/Admin/AttendanceController.php app/Http/Controllers/Admin/AttendanceQrScanController.php tests/Feature/Admin/AttendanceKonfigurasiKalenderViewTest.php tests/Feature/Admin/AttendanceHolidayOverrideViewTest.php
git commit -m "feat(sdm): tambah tab Kalender Kerja di halaman konfigurasi, checkbox override hari libur di form input manual"
```

---

## Task 10: Verifikasi Akhir + Full Test Suite (Butuh Izin User)

**Files:** Tidak ada file baru — task ini murni verifikasi.

- [ ] **Step 1: Grep ulang untuk memastikan tidak ada hardcode role**

Run: `grep -rn "hasRole(" app/Domains/Sdm app/Http/Controllers/Admin/AttendanceConfigurationController.php app/Console/Commands/TandaiAlpaOtomatisSdm.php`
Expected: tidak ada hasil (kosong).

- [ ] **Step 2: Grep untuk memastikan tidak ada write ke tabel `kalender_akademik` dari domain Sdm**

Run: `grep -rn "KalenderAkademik::create\|KalenderAkademik::update\|->save()" app/Domains/Sdm/`
Expected: tidak ada hasil yang menulis ke `KalenderAkademik` (hanya boleh membaca via `KalenderAkademik::nasional()`/`whereIn` di `CopyKalenderAkademikNasionalAction`).

- [ ] **Step 3: Jalankan seluruh test scoped Sub-project 2 bersama-sama**

Run: `php artisan test tests/Unit/Services/KalenderKerjaSdmResolverTest.php tests/Feature/Sdm tests/Feature/Admin/AttendanceConfigurationKalenderControllerTest.php tests/Feature/Admin/AttendanceKonfigurasiKalenderViewTest.php tests/Feature/Admin/AttendanceHolidayOverrideViewTest.php`
Expected: semua test dari Task 2-9 hijau bersama-sama (total ≥ 36 test baru), 0 failed.

- [ ] **Step 4: Jalankan ulang SELURUH test domain Sdm dari Sub-project 1 + Sub-project 2 sekaligus, untuk pastikan tidak ada regresi silang**

Run: `php artisan test tests/Feature/Sdm tests/Feature/Admin/Attendance*.php tests/Feature/Sdm/EmployeeQrCodeControllerTest.php tests/Unit/Services/KalenderKerjaSdmResolverTest.php`
Expected: 0 failed.

- [ ] **Step 5: MINTA IZIN EKSPLISIT USER sebelum lanjut ke Step 6**

Tampilkan pesan ke user: "Semua test scoped Kehadiran SDM Sub-project 2 sudah hijau. Boleh saya jalankan full test suite sekarang?" — TUNGGU jawaban eksplisit sebelum menjalankan Step 6.

- [ ] **Step 6: (Setelah izin diberikan) Jalankan full test suite**

Run: `php artisan test`
Expected: 0 failed, 0 error. Total test harus ≥ 1933 (baseline Sub-project 1) + jumlah test baru Sub-project 2 (kurang lebih 36).

Catatan: kalau ada test GAGAL yang TIDAK terkait Kehadiran SDM sama sekali, ada riwayat flaky test pre-existing di branch ini (`KomponenPenilaianCrudTest`, `RaporPdfDataBuilderTest`) — jalankan ulang test yang gagal SENDIRIAN dulu untuk konfirmasi sebelum melaporkan sebagai regresi.

- [ ] **Step 7: Tulis handoff log**

Buat file `.agents/logs/2026-08-22-sdm-02-kalender-kerja-sdm.md` berisi: ringkasan per task (1-10), commit hash tiap task, hasil verifikasi akhir dengan angka pasti, dan daftar deviasi (kalau ada) dari plan ini.

- [ ] **Step 8: Commit handoff log**

```bash
git add .agents/logs/2026-08-22-sdm-02-kalender-kerja-sdm.md
git commit -m "docs(sdm): handoff log Sub-project 2 Kalender Kerja SDM"
```

---

## Self-Review (dilakukan penulis plan, bukan executor)

**Spec coverage**: §3 struktur data → Task 1-2. §4 resolver + bypass TenantScope → Task 2. §5.1 penolakan+override manual → Task 4. §5.1 QR tanpa override → Task 5. §5.2 command auto-alpa → Task 6. §6 RBAC gerbang nasional → Task 8. §7 UI + fitur salin → Task 7-9. §8 batasan (tidak sentuh kalender akademik, tidak ada shift) → diverifikasi eksplisit di Task 10 Step 2. Semua requirement spec punya task yang mengimplementasikannya.

**Placeholder scan**: tidak ada TBD/TODO, semua kode lengkap per step.

**Type consistency**: `RecordManualAttendanceData::overrideHariLibur` ditambahkan sebagai parameter TERAKHIR (default `false`) supaya named-argument call site Sub-project 1 tetap valid — diverifikasi di Task 4 Step 4 dengan menjalankan ulang 3 test lama plus 2 baru dalam 1 file yang sama. `KalenderKerjaSdmResolver::resolve()` signature identik dipakai di `RecordManualAttendanceAction`, `ScanQrAttendanceAction`, dan `TandaiAlpaOtomatisSdm` — parameter `Lembaga $lembaga, CarbonInterface $tanggal` konsisten di ketiganya.

**Regresi Sub-project 1**: Task 8 Step 5 dan Task 9 Step 8 eksplisit menjalankan ulang test controller/view Sub-project 1 yang tersentuh perubahan, memastikan tidak ada regresi silang sebelum lanjut ke task berikutnya.
