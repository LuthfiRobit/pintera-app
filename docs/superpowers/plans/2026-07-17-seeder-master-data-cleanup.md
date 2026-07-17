# Seeder Master/Reference Data Cleanup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Split the monolithic `DemoDataSeeder` (currently touching ~18 tables in one file) into 20 single-table seeders, add 2 new seeders for `jenis_tagihan`/`nominal_tagihan_jalur` (previously left deliberately empty), wire all of them into `DatabaseSeeder` in dependency order, delete `DemoDataSeeder.php`, and update the M4 Keuangan manual testing guide to match.

**Architecture:** Each new seeder owns exactly one table. Because Laravel's `$this->call([...])` doesn't pass return values between seeders, every seeder that needs a parent row (a specific `Lembaga`, `TahunAjaran`, `User`, etc.) looks it up by a stable natural key (NPSN for Lembaga, email for User, `lembaga_id` + `status_aktif` for the active TahunAjaran) rather than receiving it as a parameter — matching the lookup pattern already used by `M3DemoDataSeeder`/`EssentialUserSeeder`. Content is preserved exactly from the current `DemoDataSeeder.php` (same lembaga, same staff, same guru profiles, same PPDB configuration, including SMP's deliberate old-tahun-ajaran-plus-active-tahun-ajaran double PPDB setup) — this plan reorganizes files, it does not change values, except for the two genuinely new `jenis_tagihan`-related seeders.

**Tech Stack:** Laravel 12, Pest PHP.

## Global Constraints

- All 20 new seeders are `firstOrCreate`/`firstOrFail`-based, matching the idempotent style already used throughout `DemoDataSeeder.php`/`M3DemoDataSeeder.php` — running the full `DatabaseSeeder` twice must never error or duplicate rows.
- Content parity: every value (name, date, NPSN, permission list, etc.) that exists in the current `database/seeders/DemoDataSeeder.php` must appear unchanged in its new home. This plan is a reorganization, not a data change, with the sole exception of Task 7 (`JenisTagihanSeeder`/`NominalTagihanJalurSeeder`), which is genuinely new content.
- SMP's PPDB configuration is deliberately seeded TWICE (once for the old/inactive 2025/2026 tahun ajaran, to exercise the duplication feature; once for the active 2026/2027 tahun ajaran, so the public SPMB wizard works immediately) — SMA is seeded once, directly in its active tahun ajaran. Do not collapse this into a single call.
- `NominalTagihanJalur` rows are only created against the ACTIVE tahun ajaran's `JalurPpdb` rows for each lembaga — never against the old/inactive tahun ajaran's duplicate jalur (billing is irrelevant to a tahun ajaran nobody can submit against).
- The "Prestasi" jalur is deliberately left WITHOUT a `NominalTagihanJalur` row for either jenis tagihan, for both lembaga — this keeps `TagihanGenerator`'s "skip unconfigured combinations, never fabricate a tagihan" behavior demonstrable even though the rest of the jenis-tagihan data is no longer empty.
- `email_verified_at` is passed to `User::firstOrCreate()` calls throughout the current `DemoDataSeeder.php` but silently dropped by mass assignment (`email_verified_at` is not in `User::$fillable`) — a pre-existing gap flagged during the RBAC seeder cleanup's final review as "worth fixing when this file gets rewritten." Task 2 fixes it by adding `email_verified_at` to `User::$fillable`.
- PHP is not on PATH — use `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe` for all `artisan`/test commands.

---

### Task 1: `LembagaSeeder`

**Files:**
- Create: `database/seeders/LembagaSeeder.php`
- Test: `tests/Unit/LembagaSeederTest.php`

**Interfaces:**
- Consumes: `Yayasan::firstOrFail()` (from `YayasanSeeder`, already registered in `DatabaseSeeder` before this task's position).
- Produces: 2 `Lembaga` rows, findable by NPSN `20223344` (SMP Islam Al-Hikmah) and `20223355` (SMA Islam Al-Hikmah) — every later task in this plan looks up its lembaga by one of these two NPSN values.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/LembagaSeederTest.php

use App\Models\Lembaga;
use App\Models\Yayasan;
use Database\Seeders\LembagaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('seeds SMP and SMA with the expected identifying fields', function () {
    Yayasan::factory()->create(['nama' => 'Yayasan Pendidikan Islam Al-Hikmah']);

    (new LembagaSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    expect($smp)->not->toBeNull();
    expect($smp->nama)->toBe('SMP Islam Al-Hikmah');
    expect($smp->bentuk_pendidikan)->toBe('SMP');
    expect($smp->status_aktif)->toBeTrue();

    $sma = Lembaga::where('npsn', '20223355')->first();
    expect($sma)->not->toBeNull();
    expect($sma->nama)->toBe('SMA Islam Al-Hikmah');
    expect($sma->bentuk_pendidikan)->toBe('SMA');
});

it('is idempotent when run twice', function () {
    Yayasan::factory()->create();

    (new LembagaSeeder())->run();
    (new LembagaSeeder())->run();

    expect(Lembaga::count())->toBe(2);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/LembagaSeederTest.php`
Expected: FAIL — `Database\Seeders\LembagaSeeder` doesn't exist yet.

- [ ] **Step 3: Write `LembagaSeeder`**

```php
<?php
// database/seeders/LembagaSeeder.php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Database\Seeder;

class LembagaSeeder extends Seeder
{
    public function run(): void
    {
        $yayasan = Yayasan::firstOrFail();

        Lembaga::firstOrCreate(
            ['npsn' => '20223344'],
            [
                'yayasan_id' => $yayasan->id,
                'nss' => '202026001045',
                'nama' => 'SMP Islam Al-Hikmah',
                'bentuk_pendidikan' => 'SMP',
                'status_sekolah' => 'swasta',
                'status_kepemilikan' => 'Yayasan',
                'naungan' => 'kemendikdasmen',
                'sk_pendirian_nomor' => '421.3/SK.045/Disdik/2006',
                'sk_pendirian_tanggal' => '2006-06-01',
                'sk_izin_operasional_nomor' => '421.3/IOP.089/Disdik/2006',
                'sk_izin_operasional_tanggal' => '2006-07-15',
                'akreditasi' => 'A',
                'sk_akreditasi_nomor' => '1234/BAN-SM/SK/2022',
                'tanggal_sk_akreditasi' => '2022-11-10',
                'nama_kepala_sekolah' => 'Drs. H. Bambang Suryadi, M.Pd.',
                'nama_bendahara_bosp' => 'Nur Aisyah, S.Pd.',
                'alamat_jalan' => 'Jl. Pendidikan Raya No. 45',
                'rt' => '003',
                'rw' => '008',
                'desa_kelurahan' => 'Sukaluyu',
                'kecamatan' => 'Cibeunying Kaler',
                'kabupaten_kota' => 'Kota Bandung',
                'provinsi' => 'Jawa Barat',
                'kode_pos' => '40123',
                'lintang' => '-6.8951000',
                'bujur' => '107.6134000',
                'telepon' => '022-7301234',
                'email' => 'smp@alhikmah.sch.id',
                'website' => 'https://smp.alhikmah.sch.id',
                'nama_bank' => 'Bank BRI',
                'cabang_kcp_unit' => 'KCP Bandung Cibeunying',
                'rekening_atas_nama' => 'SMP Islam Al-Hikmah',
                'nomor_rekening' => '0123-01-987654-50-1',
                'mbs' => true,
                'nama_wajib_pajak' => 'SMP Islam Al-Hikmah',
                'npwp' => '02.345.678.9-012.000',
                'memungut_iuran' => true,
                'nominal_iuran' => 350000,
                'periode_iuran' => 'bulanan',
                'status_aktif' => true,
            ]
        );

        Lembaga::firstOrCreate(
            ['npsn' => '20223355'],
            [
                'yayasan_id' => $yayasan->id,
                'nss' => '302026001046',
                'nama' => 'SMA Islam Al-Hikmah',
                'bentuk_pendidikan' => 'SMA',
                'status_sekolah' => 'swasta',
                'status_kepemilikan' => 'Yayasan',
                'naungan' => 'kemendikdasmen',
                'sk_pendirian_nomor' => '421.3/SK.078/Disdik/2010',
                'sk_pendirian_tanggal' => '2010-05-20',
                'sk_izin_operasional_nomor' => '421.3/IOP.112/Disdik/2010',
                'sk_izin_operasional_tanggal' => '2010-06-30',
                'akreditasi' => 'A',
                'sk_akreditasi_nomor' => '5678/BAN-SM/SK/2021',
                'tanggal_sk_akreditasi' => '2021-09-05',
                'nama_kepala_sekolah' => 'Dr. Hj. Ratna Dewi, M.M.Pd.',
                'nama_bendahara_bosp' => 'Fajar Ramadhan, S.E.',
                'alamat_jalan' => 'Jl. Pendidikan Raya No. 47',
                'rt' => '003',
                'rw' => '008',
                'desa_kelurahan' => 'Sukaluyu',
                'kecamatan' => 'Cibeunying Kaler',
                'kabupaten_kota' => 'Kota Bandung',
                'provinsi' => 'Jawa Barat',
                'kode_pos' => '40123',
                'lintang' => '-6.8953000',
                'bujur' => '107.6138000',
                'telepon' => '022-7301235',
                'email' => 'sma@alhikmah.sch.id',
                'website' => 'https://sma.alhikmah.sch.id',
                'nama_bank' => 'Bank BRI',
                'cabang_kcp_unit' => 'KCP Bandung Cibeunying',
                'rekening_atas_nama' => 'SMA Islam Al-Hikmah',
                'nomor_rekening' => '0123-01-987655-50-2',
                'mbs' => true,
                'nama_wajib_pajak' => 'SMA Islam Al-Hikmah',
                'npwp' => '02.345.679.0-012.000',
                'memungut_iuran' => true,
                'nominal_iuran' => 450000,
                'periode_iuran' => 'bulanan',
                'status_aktif' => true,
            ]
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/LembagaSeederTest.php`
Expected: PASS (2/2)

- [ ] **Step 5: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all pre-existing tests still pass (this file is additive only — `DemoDataSeeder.php`/`DatabaseSeeder.php` are untouched until Task 8).

- [ ] **Step 6: Commit**

```bash
git add database/seeders/LembagaSeeder.php tests/Unit/LembagaSeederTest.php
git commit -m "feat: add LembagaSeeder, splitting lembaga registration out of DemoDataSeeder"
```

---

### Task 2: `UserSeeder`

**Files:**
- Modify: `app/Models/User.php`
- Create: `database/seeders/UserSeeder.php`
- Test: `tests/Unit/UserSeederTest.php`

**Interfaces:**
- Consumes: `Lembaga` rows by NPSN (Task 1), `Role` rows (from sub-project 1's `RoleSeeder`, already registered before this task's position in `DatabaseSeeder`).
- Produces: `User` rows findable by email — `admin.yayasan@alhikmah.sch.id`, `kepsek.smp@alhikmah.sch.id`, `adm.smp@alhikmah.sch.id`, `keuangan.smp@alhikmah.sch.id`, `budi.santoso@alhikmah.sch.id`, `siti.rahmawati@alhikmah.sch.id`, `andi.wijaya@alhikmah.sch.id` (SMP), and the SMA equivalents `kepsek.sma@alhikmah.sch.id`, `adm.sma@alhikmah.sch.id`, `keuangan.sma@alhikmah.sch.id`, `hendra.gunawan@alhikmah.sch.id`, `maya.anggraini@alhikmah.sch.id`, `taufik.hidayat@alhikmah.sch.id`. Task 4's `GuruSeeder` looks up the 6 guru-role emails by email to attach `Guru` profiles.

- [ ] **Step 1: Fix `email_verified_at` mass-assignment (pre-existing gap, fixed here since this file is being rewritten)**

In `app/Models/User.php`, change:

```php
    protected $fillable = [
        'name',
        'email',
        'password',
        'lembaga_id',
        'is_active',
    ];
```

to:

```php
    protected $fillable = [
        'name',
        'email',
        'password',
        'lembaga_id',
        'is_active',
        'email_verified_at',
    ];
```

- [ ] **Step 2: Write the failing test**

```php
<?php
// tests/Unit/UserSeederTest.php

use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
    Yayasan::factory()->create(['nama' => 'Yayasan Pendidikan Islam Al-Hikmah']);
    (new LembagaSeeder())->run();
});

it('seeds the yayasan admin and per-lembaga staff with correct roles and lembaga_id', function () {
    (new UserSeeder())->run();

    $adminYayasan = User::where('email', 'admin.yayasan@alhikmah.sch.id')->first();
    expect($adminYayasan)->not->toBeNull();
    expect($adminYayasan->hasRole('yayasan_super_admin'))->toBeTrue();
    expect($adminYayasan->lembaga_id)->toBeNull();
    expect($adminYayasan->email_verified_at)->not->toBeNull();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $kepsekSmp = User::where('email', 'kepsek.smp@alhikmah.sch.id')->first();
    expect($kepsekSmp->hasRole('kepala_sekolah'))->toBeTrue();
    expect($kepsekSmp->lembaga_id)->toBe($smp->id);

    $keuanganSmp = User::where('email', 'keuangan.smp@alhikmah.sch.id')->first();
    expect($keuanganSmp->hasRole('admin_keuangan'))->toBeTrue();

    $guruSmp = User::where('email', 'budi.santoso@alhikmah.sch.id')->first();
    expect($guruSmp->hasRole('guru'))->toBeTrue();
    expect($guruSmp->lembaga_id)->toBe($smp->id);

    $sma = Lembaga::where('npsn', '20223355')->first();
    $kepsekSma = User::where('email', 'kepsek.sma@alhikmah.sch.id')->first();
    expect($kepsekSma->hasRole('kepala_sekolah'))->toBeTrue();
    expect($kepsekSma->lembaga_id)->toBe($sma->id);
});

it('is idempotent when run twice', function () {
    (new UserSeeder())->run();
    (new UserSeeder())->run();

    expect(User::count())->toBe(13);
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/UserSeederTest.php`
Expected: FAIL — `Database\Seeders\UserSeeder` doesn't exist yet.

- [ ] **Step 4: Write `UserSeeder`**

```php
<?php
// database/seeders/UserSeeder.php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (! User::where('email', 'admin.yayasan@alhikmah.sch.id')->exists()) {
            $adminYayasan = User::create([
                'name' => 'Ahmad Fauzi (Admin Yayasan)',
                'email' => 'admin.yayasan@alhikmah.sch.id',
                'password' => 'password',
                'email_verified_at' => now(),
                'is_active' => true,
            ]);
            $adminYayasan->assignRole('yayasan_super_admin');
        }

        $smp = Lembaga::where('npsn', '20223344')->firstOrFail();
        $sma = Lembaga::where('npsn', '20223355')->firstOrFail();

        $this->seedStaf($smp, [
            ['name' => 'Drs. H. Bambang Suryadi, M.Pd.', 'email' => 'kepsek.smp@alhikmah.sch.id', 'role' => 'kepala_sekolah'],
            ['name' => 'Dewi Lestari, S.Pd.', 'email' => 'adm.smp@alhikmah.sch.id', 'role' => 'admin_administrasi'],
            ['name' => 'Nur Aisyah, S.Pd.', 'email' => 'keuangan.smp@alhikmah.sch.id', 'role' => 'admin_keuangan'],
        ], [
            ['name' => 'Budi Santoso, S.Pd.', 'email' => 'budi.santoso@alhikmah.sch.id'],
            ['name' => 'Siti Rahmawati, S.Pd.', 'email' => 'siti.rahmawati@alhikmah.sch.id'],
            ['name' => 'Andi Wijaya, S.Pd.I.', 'email' => 'andi.wijaya@alhikmah.sch.id'],
        ]);

        $this->seedStaf($sma, [
            ['name' => 'Dr. Hj. Ratna Dewi, M.M.Pd.', 'email' => 'kepsek.sma@alhikmah.sch.id', 'role' => 'kepala_sekolah'],
            ['name' => 'Rizal Firmansyah, S.Kom.', 'email' => 'adm.sma@alhikmah.sch.id', 'role' => 'admin_administrasi'],
            ['name' => 'Fajar Ramadhan, S.E.', 'email' => 'keuangan.sma@alhikmah.sch.id', 'role' => 'admin_keuangan'],
        ], [
            ['name' => 'Hendra Gunawan, S.Pd.', 'email' => 'hendra.gunawan@alhikmah.sch.id'],
            ['name' => 'Maya Anggraini, S.Pd.', 'email' => 'maya.anggraini@alhikmah.sch.id'],
            ['name' => 'Taufik Hidayat, S.Pd.', 'email' => 'taufik.hidayat@alhikmah.sch.id'],
        ]);
    }

    private function seedStaf(Lembaga $lembaga, array $pimpinan, array $guruList): void
    {
        foreach ($pimpinan as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => 'password',
                    'lembaga_id' => $lembaga->id,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );
            $user->assignRole($data['role']);
        }

        foreach ($guruList as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => 'password',
                    'lembaga_id' => $lembaga->id,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );
            $user->assignRole('guru');
        }
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/UserSeederTest.php`
Expected: PASS (2/2)

- [ ] **Step 6: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all pre-existing tests still pass. The `User::$fillable` change is additive (one more field), so no existing `User::create()`/`firstOrCreate()` call elsewhere in the codebase should be affected.

- [ ] **Step 7: Commit**

```bash
git add app/Models/User.php database/seeders/UserSeeder.php tests/Unit/UserSeederTest.php
git commit -m "feat: add UserSeeder, splitting staff account registration out of DemoDataSeeder"
```

---

### Task 3: `TahunAjaranSeeder` and `SemesterSeeder`

**Files:**
- Create: `database/seeders/TahunAjaranSeeder.php`
- Create: `database/seeders/SemesterSeeder.php`
- Test: `tests/Unit/TahunAjaranSeederTest.php`
- Test: `tests/Unit/SemesterSeederTest.php`

**Interfaces:**
- Consumes: `Lembaga` rows by NPSN (Task 1).
- Produces: for each lembaga, two `TahunAjaran` rows (`2025/2026` inactive, `2026/2027` active) and two `Semester` rows per `TahunAjaran` (`Ganjil`, `Genap`) — Task 5's `LembagaDataPeriodikSeeder` and Task 6's PPDB seeders both look up the active `TahunAjaran` per lembaga via `TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first()`, and the inactive one via `where('status_aktif', false)`.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Unit/TahunAjaranSeederTest.php

use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\TahunAjaranSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
});

it('seeds an inactive 2025/2026 and an active 2026/2027 tahun ajaran per lembaga', function () {
    (new TahunAjaranSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $lama = TahunAjaran::where('lembaga_id', $smp->id)->where('nama', '2025/2026')->first();
    expect($lama)->not->toBeNull();
    expect($lama->status_aktif)->toBeFalse();

    $baru = TahunAjaran::where('lembaga_id', $smp->id)->where('nama', '2026/2027')->first();
    expect($baru)->not->toBeNull();
    expect($baru->status_aktif)->toBeTrue();

    $sma = Lembaga::where('npsn', '20223355')->first();
    expect(TahunAjaran::where('lembaga_id', $sma->id)->where('status_aktif', true)->exists())->toBeTrue();
});

it('is idempotent when run twice', function () {
    (new TahunAjaranSeeder())->run();
    (new TahunAjaranSeeder())->run();

    expect(TahunAjaran::count())->toBe(4);
});
```

```php
<?php
// tests/Unit/SemesterSeederTest.php

use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\SemesterSeeder;
use Database\Seeders\TahunAjaranSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
    (new TahunAjaranSeeder())->run();
});

it('seeds Ganjil and Genap semester for every tahun ajaran, active semester matching its tahun ajaran', function () {
    (new SemesterSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $aktif = TahunAjaran::where('lembaga_id', $smp->id)->where('status_aktif', true)->first();

    $ganjil = Semester::where('tahun_ajaran_id', $aktif->id)->where('nama', 'Ganjil')->first();
    expect($ganjil)->not->toBeNull();
    expect($ganjil->status_aktif)->toBeTrue();
    expect($ganjil->urutan)->toBe(1);

    $genap = Semester::where('tahun_ajaran_id', $aktif->id)->where('nama', 'Genap')->first();
    expect($genap)->not->toBeNull();
    expect($genap->status_aktif)->toBeFalse();
});

it('is idempotent when run twice', function () {
    (new SemesterSeeder())->run();
    (new SemesterSeeder())->run();

    expect(Semester::count())->toBe(8);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/TahunAjaranSeederTest.php tests/Unit/SemesterSeederTest.php`
Expected: FAIL — neither class exists yet.

- [ ] **Step 3: Write `TahunAjaranSeeder`**

```php
<?php
// database/seeders/TahunAjaranSeeder.php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class TahunAjaranSeeder extends Seeder
{
    public function run(): void
    {
        $smp = Lembaga::where('npsn', '20223344')->firstOrFail();
        $sma = Lembaga::where('npsn', '20223355')->firstOrFail();

        $this->seedTahunAjaran($smp, '2026', '2027');
        $this->seedTahunAjaran($sma, '2026', '2027');
    }

    private function seedTahunAjaran(Lembaga $lembaga, string $tahunAwal, string $tahunAkhir): void
    {
        TahunAjaran::firstOrCreate(
            ['lembaga_id' => $lembaga->id, 'nama' => ($tahunAwal - 1).'/'.$tahunAwal],
            [
                'tanggal_mulai' => ($tahunAwal - 1).'-07-01',
                'tanggal_selesai' => $tahunAwal.'-06-30',
                'status_aktif' => false,
            ]
        );

        TahunAjaran::firstOrCreate(
            ['lembaga_id' => $lembaga->id, 'nama' => $tahunAwal.'/'.$tahunAkhir],
            [
                'tanggal_mulai' => $tahunAwal.'-07-01',
                'tanggal_selesai' => $tahunAkhir.'-06-30',
                'status_aktif' => true,
            ]
        );
    }
}
```

- [ ] **Step 4: Write `SemesterSeeder`**

```php
<?php
// database/seeders/SemesterSeeder.php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class SemesterSeeder extends Seeder
{
    public function run(): void
    {
        $lembagaList = Lembaga::whereIn('npsn', ['20223344', '20223355'])->get();

        foreach ($lembagaList as $lembaga) {
            foreach (TahunAjaran::where('lembaga_id', $lembaga->id)->get() as $tahunAjaran) {
                $tahunGanjil = (int) explode('/', $tahunAjaran->nama)[0];
                $ganjilAktif = $tahunAjaran->status_aktif;

                Semester::firstOrCreate(
                    ['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Ganjil'],
                    [
                        'lembaga_id' => $lembaga->id,
                        'urutan' => 1,
                        'kode_dapodik' => $tahunGanjil.'1',
                        'tanggal_mulai' => $tahunGanjil.'-07-01',
                        'tanggal_selesai' => $tahunGanjil.'-12-20',
                        'status_aktif' => $ganjilAktif,
                    ]
                );

                Semester::firstOrCreate(
                    ['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Genap'],
                    [
                        'lembaga_id' => $lembaga->id,
                        'urutan' => 2,
                        'kode_dapodik' => $tahunGanjil.'2',
                        'tanggal_mulai' => ($tahunGanjil + 1).'-01-05',
                        'tanggal_selesai' => ($tahunGanjil + 1).'-06-30',
                        'status_aktif' => false,
                    ]
                );
            }
        }
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/TahunAjaranSeederTest.php tests/Unit/SemesterSeederTest.php`
Expected: PASS (4/4)

- [ ] **Step 6: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all pre-existing tests still pass.

- [ ] **Step 7: Commit**

```bash
git add database/seeders/TahunAjaranSeeder.php database/seeders/SemesterSeeder.php tests/Unit/TahunAjaranSeederTest.php tests/Unit/SemesterSeederTest.php
git commit -m "feat: add TahunAjaranSeeder and SemesterSeeder, splitting them out of DemoDataSeeder"
```

---

### Task 4: Guru Profile Seeders

**Files:**
- Create: `database/seeders/GuruSeeder.php`
- Create: `database/seeders/RiwayatPendidikanGuruSeeder.php`
- Create: `database/seeders/SertifikasiGuruSeeder.php`
- Create: `database/seeders/GuruJabatanTambahanSeeder.php`
- Test: `tests/Unit/GuruSeederTest.php`
- Test: `tests/Unit/RiwayatPendidikanGuruSeederTest.php`
- Test: `tests/Unit/SertifikasiGuruSeederTest.php`
- Test: `tests/Unit/GuruJabatanTambahanSeederTest.php`

**Interfaces:**
- Consumes: `User` rows by email (Task 2, the 6 guru-role emails), `Lembaga` rows by NPSN (Task 1), `JabatanTambahanMaster` rows by `nama` (already seeded by `JabatanTambahanMasterSeeder`, registered before this task's position in `DatabaseSeeder`).
- Produces: 6 `Guru` rows (one per guru-role `User`, findable by `user_id`) — `RiwayatPendidikanGuruSeeder`/`SertifikasiGuruSeeder`/`GuruJabatanTambahanSeeder` all look up their parent `Guru` by the same `user_id → email` chain.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Unit/GuruSeederTest.php

use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\GuruSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
    (new UserSeeder())->run();
});

it('seeds a Guru profile for every guru-role user, with correct lembaga_id and jenis_ptk', function () {
    (new GuruSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $user = User::where('email', 'budi.santoso@alhikmah.sch.id')->first();
    $guru = Guru::where('user_id', $user->id)->first();

    expect($guru)->not->toBeNull();
    expect($guru->lembaga_id)->toBe($smp->id);
    expect($guru->nik)->toBe('3273011503850001');
    expect($guru->status_kepegawaian)->toBe('PNS');
});

it('is idempotent when run twice', function () {
    (new GuruSeeder())->run();
    (new GuruSeeder())->run();

    expect(Guru::count())->toBe(6);
});
```

```php
<?php
// tests/Unit/RiwayatPendidikanGuruSeederTest.php

use App\Models\Guru;
use App\Models\RiwayatPendidikanGuru;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\GuruSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RiwayatPendidikanGuruSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
    (new UserSeeder())->run();
    (new GuruSeeder())->run();
});

it('seeds education history for a guru with a known S1 record', function () {
    (new RiwayatPendidikanGuruSeeder())->run();

    $user = User::where('email', 'budi.santoso@alhikmah.sch.id')->first();
    $guru = Guru::where('user_id', $user->id)->first();

    $riwayat = RiwayatPendidikanGuru::where('guru_id', $guru->id)->where('jenjang_pendidikan', 'S1')->first();
    expect($riwayat)->not->toBeNull();
    expect($riwayat->sekolah_formal)->toBe('Universitas Pendidikan Indonesia');
    expect($riwayat->bidang_studi)->toBe('Pendidikan Matematika');
});

it('seeds a guru with two education records (S1 and S2)', function () {
    (new RiwayatPendidikanGuruSeeder())->run();

    $user = User::where('email', 'hendra.gunawan@alhikmah.sch.id')->first();
    $guru = Guru::where('user_id', $user->id)->first();

    expect(RiwayatPendidikanGuru::where('guru_id', $guru->id)->count())->toBe(2);
});

it('is idempotent when run twice', function () {
    (new RiwayatPendidikanGuruSeeder())->run();
    (new RiwayatPendidikanGuruSeeder())->run();

    expect(RiwayatPendidikanGuru::count())->toBe(7);
});
```

```php
<?php
// tests/Unit/SertifikasiGuruSeederTest.php

use App\Models\Guru;
use App\Models\SertifikasiGuru;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\GuruSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SertifikasiGuruSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
    (new UserSeeder())->run();
    (new GuruSeeder())->run();
});

it('seeds certification only for guru who have one, leaving others without', function () {
    (new SertifikasiGuruSeeder())->run();

    $bersertifikat = User::where('email', 'budi.santoso@alhikmah.sch.id')->first();
    $guruBersertifikat = Guru::where('user_id', $bersertifikat->id)->first();
    expect(SertifikasiGuru::where('guru_id', $guruBersertifikat->id)->exists())->toBeTrue();

    $tanpaSertifikat = User::where('email', 'siti.rahmawati@alhikmah.sch.id')->first();
    $guruTanpaSertifikat = Guru::where('user_id', $tanpaSertifikat->id)->first();
    expect(SertifikasiGuru::where('guru_id', $guruTanpaSertifikat->id)->exists())->toBeFalse();
});

it('is idempotent when run twice', function () {
    (new SertifikasiGuruSeeder())->run();
    (new SertifikasiGuruSeeder())->run();

    expect(SertifikasiGuru::count())->toBe(3);
});
```

```php
<?php
// tests/Unit/GuruJabatanTambahanSeederTest.php

use App\Models\Guru;
use App\Models\GuruJabatanTambahan;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\GuruJabatanTambahanSeeder;
use Database\Seeders\GuruSeeder;
use Database\Seeders\JabatanTambahanMasterSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
    (new JabatanTambahanMasterSeeder())->run();
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
    (new UserSeeder())->run();
    (new GuruSeeder())->run();
});

it('assigns Wali Kelas to the guru who has one, and Wakil Kepala Sekolah Kurikulum to another', function () {
    (new GuruJabatanTambahanSeeder())->run();

    $siti = User::where('email', 'siti.rahmawati@alhikmah.sch.id')->first();
    $guruSiti = Guru::where('user_id', $siti->id)->first();
    expect(GuruJabatanTambahan::where('guru_id', $guruSiti->id)->exists())->toBeTrue();

    $hendra = User::where('email', 'hendra.gunawan@alhikmah.sch.id')->first();
    $guruHendra = Guru::where('user_id', $hendra->id)->first();
    expect(GuruJabatanTambahan::where('guru_id', $guruHendra->id)->exists())->toBeTrue();
});

it('is idempotent when run twice', function () {
    (new GuruJabatanTambahanSeeder())->run();
    (new GuruJabatanTambahanSeeder())->run();

    expect(GuruJabatanTambahan::count())->toBe(4);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/GuruSeederTest.php tests/Unit/RiwayatPendidikanGuruSeederTest.php tests/Unit/SertifikasiGuruSeederTest.php tests/Unit/GuruJabatanTambahanSeederTest.php`
Expected: FAIL — none of the 4 classes exist yet.

- [ ] **Step 3: Write `GuruSeeder`**

```php
<?php
// database/seeders/GuruSeeder.php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\User;
use Illuminate\Database\Seeder;

class GuruSeeder extends Seeder
{
    public function run(): void
    {
        $smp = Lembaga::where('npsn', '20223344')->firstOrFail();
        $sma = Lembaga::where('npsn', '20223355')->firstOrFail();

        $this->seedGuru($smp, [
            [
                'email' => 'budi.santoso@alhikmah.sch.id', 'name' => 'Budi Santoso, S.Pd.',
                'nik' => '3273011503850001', 'nuptk' => '1234567890123456', 'nip' => '198503152010011001',
                'jenis_kelamin' => 'L', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1985-03-15',
                'no_hp' => '081234567801', 'jenis_ptk' => 'guru_mapel', 'status_kepegawaian' => 'PNS',
                'golongan_pangkat' => 'Penata Muda Tk.I / III-b', 'tmt_tugas' => '2010-01-01', 'tmt_pns' => '2010-01-01',
            ],
            [
                'email' => 'siti.rahmawati@alhikmah.sch.id', 'name' => 'Siti Rahmawati, S.Pd.',
                'nik' => '3273015207880002', 'nuptk' => null, 'nip' => null,
                'jenis_kelamin' => 'P', 'tempat_lahir' => 'Cimahi', 'tanggal_lahir' => '1988-07-12',
                'no_hp' => '081234567802', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
                'golongan_pangkat' => null, 'tmt_tugas' => '2015-07-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'andi.wijaya@alhikmah.sch.id', 'name' => 'Andi Wijaya, S.Pd.I.',
                'nik' => '3273012009900003', 'nuptk' => null, 'nip' => null,
                'jenis_kelamin' => 'L', 'tempat_lahir' => 'Garut', 'tanggal_lahir' => '1990-09-20',
                'no_hp' => '081234567803', 'jenis_ptk' => 'guru_mapel', 'status_kepegawaian' => 'Honorer',
                'golongan_pangkat' => null, 'tmt_tugas' => '2019-07-01', 'tmt_pns' => null,
            ],
        ]);

        $this->seedGuru($sma, [
            [
                'email' => 'hendra.gunawan@alhikmah.sch.id', 'name' => 'Hendra Gunawan, S.Pd.',
                'nik' => '3273010108820004', 'nuptk' => '2234567890123456', 'nip' => '198201082008011002',
                'jenis_kelamin' => 'L', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1982-01-08',
                'no_hp' => '081234567804', 'jenis_ptk' => 'guru_mapel', 'status_kepegawaian' => 'PNS',
                'golongan_pangkat' => 'Penata / III-c', 'tmt_tugas' => '2008-01-01', 'tmt_pns' => '2008-01-01',
            ],
            [
                'email' => 'maya.anggraini@alhikmah.sch.id', 'name' => 'Maya Anggraini, S.Pd.',
                'nik' => '3273014412910005', 'nuptk' => null, 'nip' => null,
                'jenis_kelamin' => 'P', 'tempat_lahir' => 'Sumedang', 'tanggal_lahir' => '1991-12-04',
                'no_hp' => '081234567805', 'jenis_ptk' => 'guru_mapel', 'status_kepegawaian' => 'PPPK',
                'golongan_pangkat' => 'IX', 'tmt_tugas' => '2021-03-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'taufik.hidayat@alhikmah.sch.id', 'name' => 'Taufik Hidayat, S.Pd.',
                'nik' => '3273011511870006', 'nuptk' => null, 'nip' => null,
                'jenis_kelamin' => 'L', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1987-11-15',
                'no_hp' => '081234567806', 'jenis_ptk' => 'guru_mapel', 'status_kepegawaian' => 'GTY',
                'golongan_pangkat' => null, 'tmt_tugas' => '2016-07-01', 'tmt_pns' => null,
            ],
        ]);
    }

    private function seedGuru(Lembaga $lembaga, array $guruList): void
    {
        foreach ($guruList as $data) {
            $user = User::where('email', $data['email'])->firstOrFail();

            Guru::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'lembaga_id' => $lembaga->id,
                    'nik' => $data['nik'],
                    'nuptk' => $data['nuptk'],
                    'nip' => $data['nip'],
                    'nama' => $data['name'],
                    'jenis_kelamin' => $data['jenis_kelamin'],
                    'tempat_lahir' => $data['tempat_lahir'],
                    'tanggal_lahir' => $data['tanggal_lahir'],
                    'agama' => 'Islam',
                    'kewarganegaraan' => 'WNI',
                    'alamat_jalan' => 'Jl. Cihampelas No. '.random_int(10, 200),
                    'rt' => '002',
                    'rw' => '005',
                    'desa_kelurahan' => 'Cipaganti',
                    'kecamatan' => 'Coblong',
                    'kabupaten_kota' => 'Kota Bandung',
                    'provinsi' => 'Jawa Barat',
                    'kode_pos' => '40131',
                    'no_hp' => $data['no_hp'],
                    'email' => $data['email'],
                    'jenis_ptk' => $data['jenis_ptk'],
                    'status_kepegawaian' => $data['status_kepegawaian'],
                    'golongan_pangkat' => $data['golongan_pangkat'],
                    'tmt_tugas' => $data['tmt_tugas'],
                    'tmt_pns' => $data['tmt_pns'],
                    'status_aktif' => 'aktif',
                ]
            );
        }
    }
}
```

- [ ] **Step 4: Write `RiwayatPendidikanGuruSeeder`**

```php
<?php
// database/seeders/RiwayatPendidikanGuruSeeder.php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\RiwayatPendidikanGuru;
use App\Models\User;
use Illuminate\Database\Seeder;

class RiwayatPendidikanGuruSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'budi.santoso@alhikmah.sch.id' => [
                ['jenjang_pendidikan' => 'S1', 'sekolah_formal' => 'Universitas Pendidikan Indonesia', 'fakultas' => 'FPMIPA', 'bidang_studi' => 'Pendidikan Matematika', 'kependidikan' => true, 'tahun_masuk' => 2003, 'tahun_lulus' => 2007],
            ],
            'siti.rahmawati@alhikmah.sch.id' => [
                ['jenjang_pendidikan' => 'S1', 'sekolah_formal' => 'Universitas Islam Negeri Sunan Gunung Djati', 'fakultas' => 'Tarbiyah', 'bidang_studi' => 'Pendidikan Guru Madrasah Ibtidaiyah', 'kependidikan' => true, 'tahun_masuk' => 2006, 'tahun_lulus' => 2010],
            ],
            'andi.wijaya@alhikmah.sch.id' => [
                ['jenjang_pendidikan' => 'S1', 'sekolah_formal' => 'Institut Agama Islam Negeri Bandung', 'fakultas' => 'Tarbiyah', 'bidang_studi' => 'Pendidikan Agama Islam', 'kependidikan' => true, 'tahun_masuk' => 2008, 'tahun_lulus' => 2012],
            ],
            'hendra.gunawan@alhikmah.sch.id' => [
                ['jenjang_pendidikan' => 'S1', 'sekolah_formal' => 'Institut Teknologi Bandung', 'fakultas' => 'FMIPA', 'bidang_studi' => 'Fisika', 'kependidikan' => false, 'tahun_masuk' => 2000, 'tahun_lulus' => 2004],
                ['jenjang_pendidikan' => 'S2', 'sekolah_formal' => 'Universitas Pendidikan Indonesia', 'fakultas' => 'Sekolah Pascasarjana', 'bidang_studi' => 'Pendidikan Fisika', 'kependidikan' => true, 'tahun_masuk' => 2013, 'tahun_lulus' => 2015],
            ],
            'maya.anggraini@alhikmah.sch.id' => [
                ['jenjang_pendidikan' => 'S1', 'sekolah_formal' => 'Universitas Pendidikan Indonesia', 'fakultas' => 'FPBS', 'bidang_studi' => 'Pendidikan Bahasa Inggris', 'kependidikan' => true, 'tahun_masuk' => 2009, 'tahun_lulus' => 2013],
            ],
            'taufik.hidayat@alhikmah.sch.id' => [
                ['jenjang_pendidikan' => 'S1', 'sekolah_formal' => 'Universitas Pendidikan Indonesia', 'fakultas' => 'FIP', 'bidang_studi' => 'Bimbingan dan Konseling', 'kependidikan' => true, 'tahun_masuk' => 2005, 'tahun_lulus' => 2009],
            ],
        ];

        foreach ($data as $email => $riwayatList) {
            $user = User::where('email', $email)->firstOrFail();
            $guru = Guru::where('user_id', $user->id)->firstOrFail();

            foreach ($riwayatList as $riwayat) {
                RiwayatPendidikanGuru::firstOrCreate(
                    ['guru_id' => $guru->id, 'jenjang_pendidikan' => $riwayat['jenjang_pendidikan']],
                    $riwayat + ['gelar_akademik' => null]
                );
            }
        }
    }
}
```

- [ ] **Step 5: Write `SertifikasiGuruSeeder`**

```php
<?php
// database/seeders/SertifikasiGuruSeeder.php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\SertifikasiGuru;
use App\Models\User;
use Illuminate\Database\Seeder;

class SertifikasiGuruSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'budi.santoso@alhikmah.sch.id' => ['jenis_sertifikasi' => 'Sertifikasi Guru (Portofolio)', 'nomor_sertifikat' => '123456789012', 'bidang_studi_sertifikasi' => 'Matematika', 'nrg' => '112233445566', 'tahun_sertifikasi' => 2012],
            'hendra.gunawan@alhikmah.sch.id' => ['jenis_sertifikasi' => 'Sertifikasi Guru (PLPG)', 'nomor_sertifikat' => '223456789012', 'bidang_studi_sertifikasi' => 'Fisika', 'nrg' => '122334455667', 'tahun_sertifikasi' => 2010],
            'maya.anggraini@alhikmah.sch.id' => ['jenis_sertifikasi' => 'Sertifikasi Guru (PPG Dalam Jabatan)', 'nomor_sertifikat' => '323456789012', 'bidang_studi_sertifikasi' => 'Bahasa Inggris', 'nrg' => '132334455668', 'tahun_sertifikasi' => 2022],
        ];

        foreach ($data as $email => $sertifikasi) {
            $user = User::where('email', $email)->firstOrFail();
            $guru = Guru::where('user_id', $user->id)->firstOrFail();

            SertifikasiGuru::firstOrCreate(
                ['guru_id' => $guru->id, 'jenis_sertifikasi' => $sertifikasi['jenis_sertifikasi']],
                $sertifikasi
            );
        }
    }
}
```

- [ ] **Step 6: Write `GuruJabatanTambahanSeeder`**

```php
<?php
// database/seeders/GuruJabatanTambahanSeeder.php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\GuruJabatanTambahan;
use App\Models\JabatanTambahanMaster;
use App\Models\User;
use Illuminate\Database\Seeder;

class GuruJabatanTambahanSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'siti.rahmawati@alhikmah.sch.id' => ['jabatan' => 'Wali Kelas', 'tmt_tugas' => '2015-07-01'],
            'andi.wijaya@alhikmah.sch.id' => ['jabatan' => 'Pembina Ekstrakurikuler', 'tmt_tugas' => '2019-07-01'],
            'hendra.gunawan@alhikmah.sch.id' => ['jabatan' => 'Wakil Kepala Sekolah Kurikulum', 'tmt_tugas' => '2008-01-01'],
            'taufik.hidayat@alhikmah.sch.id' => ['jabatan' => 'Koordinator BK', 'tmt_tugas' => '2016-07-01'],
        ];

        foreach ($data as $email => $info) {
            $user = User::where('email', $email)->firstOrFail();
            $guru = Guru::where('user_id', $user->id)->firstOrFail();
            $jabatan = JabatanTambahanMaster::where('nama', $info['jabatan'])->firstOrFail();

            GuruJabatanTambahan::firstOrCreate(
                ['guru_id' => $guru->id, 'jabatan_tambahan_master_id' => $jabatan->id],
                [
                    'mulai_periode' => $info['tmt_tugas'],
                    'no_sk' => 'SK.'.random_int(100, 999).'/Yayasan/'.date('Y', strtotime($info['tmt_tugas'])),
                ]
            );
        }
    }
}
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/GuruSeederTest.php tests/Unit/RiwayatPendidikanGuruSeederTest.php tests/Unit/SertifikasiGuruSeederTest.php tests/Unit/GuruJabatanTambahanSeederTest.php`
Expected: PASS (8/8)

- [ ] **Step 8: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all pre-existing tests still pass.

- [ ] **Step 9: Commit**

```bash
git add database/seeders/GuruSeeder.php database/seeders/RiwayatPendidikanGuruSeeder.php database/seeders/SertifikasiGuruSeeder.php database/seeders/GuruJabatanTambahanSeeder.php tests/Unit/GuruSeederTest.php tests/Unit/RiwayatPendidikanGuruSeederTest.php tests/Unit/SertifikasiGuruSeederTest.php tests/Unit/GuruJabatanTambahanSeederTest.php
git commit -m "feat: add Guru profile seeders, splitting them out of DemoDataSeeder"
```

---

### Task 5: Lembaga Profile & Facility Seeders

**Files:**
- Create: `database/seeders/LembagaDataPeriodikSeeder.php`
- Create: `database/seeders/LayananKhususLembagaSeeder.php`
- Create: `database/seeders/ProgramInklusiLembagaSeeder.php`
- Create: `database/seeders/EkstrakurikulerLembagaSeeder.php`
- Test: `tests/Unit/LembagaDataPeriodikSeederTest.php`
- Test: `tests/Unit/LembagaProfileSeedersTest.php` (covers `LayananKhususLembagaSeeder`/`ProgramInklusiLembagaSeeder`/`EkstrakurikulerLembagaSeeder` together, since all three are simple, independent, single-assertion-per-row seeders)

**Interfaces:**
- Consumes: `Lembaga` rows by NPSN (Task 1), active `TahunAjaran`'s `Semester` rows (Task 3).
- Produces: no later task depends on these — they're leaf data for the Data Induk / Lembaga profile pages.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Unit/LembagaDataPeriodikSeederTest.php

use App\Models\Lembaga;
use App\Models\LembagaDataPeriodik;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Database\Seeders\LembagaDataPeriodikSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\SemesterSeeder;
use Database\Seeders\TahunAjaranSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
    (new TahunAjaranSeeder())->run();
    (new SemesterSeeder())->run();
});

it('seeds a data periodik row for each semester of the active tahun ajaran, per lembaga', function () {
    (new LembagaDataPeriodikSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $aktif = TahunAjaran::where('lembaga_id', $smp->id)->where('status_aktif', true)->first();
    $semesterAktif = Semester::where('tahun_ajaran_id', $aktif->id)->get();

    foreach ($semesterAktif as $semester) {
        $periodik = LembagaDataPeriodik::where('lembaga_id', $smp->id)->where('semester_id', $semester->id)->first();
        expect($periodik)->not->toBeNull();
        expect($periodik->sumber_listrik)->toBe('PLN');
        expect($periodik->daya_listrik)->toBe(5500);
    }
});

it('is idempotent when run twice', function () {
    (new LembagaDataPeriodikSeeder())->run();
    $sebelum = LembagaDataPeriodik::count();
    (new LembagaDataPeriodikSeeder())->run();

    expect(LembagaDataPeriodik::count())->toBe($sebelum);
});
```

```php
<?php
// tests/Unit/LembagaProfileSeedersTest.php

use App\Models\EkstrakurikulerLembaga;
use App\Models\Lembaga;
use App\Models\LayananKhususLembaga;
use App\Models\ProgramInklusiLembaga;
use App\Models\Yayasan;
use Database\Seeders\EkstrakurikulerLembagaSeeder;
use Database\Seeders\LayananKhususLembagaSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\ProgramInklusiLembagaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
});

it('seeds Kelas Tahfidz Intensif as a layanan khusus for both lembaga', function () {
    (new LayananKhususLembagaSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    expect(LayananKhususLembaga::where('lembaga_id', $smp->id)->where('jenis_layanan', 'Kelas Tahfidz Intensif')->exists())->toBeTrue();
});

it('seeds Tunadaksa as a program inklusi for both lembaga', function () {
    (new ProgramInklusiLembagaSeeder())->run();

    $sma = Lembaga::where('npsn', '20223355')->first();
    expect(ProgramInklusiLembaga::where('lembaga_id', $sma->id)->where('kebutuhan_khusus', 'Tunadaksa')->exists())->toBeTrue();
});

it('seeds jenjang-specific ekstrakurikuler for SMP and SMA', function () {
    (new EkstrakurikulerLembagaSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    expect(EkstrakurikulerLembaga::where('lembaga_id', $smp->id)->where('nama_ekskul', 'Futsal')->exists())->toBeTrue();

    $sma = Lembaga::where('npsn', '20223355')->first();
    expect(EkstrakurikulerLembaga::where('lembaga_id', $sma->id)->where('nama_ekskul', 'Basket')->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/LembagaDataPeriodikSeederTest.php tests/Unit/LembagaProfileSeedersTest.php`
Expected: FAIL — none of the 4 classes exist yet.

- [ ] **Step 3: Write `LembagaDataPeriodikSeeder`**

```php
<?php
// database/seeders/LembagaDataPeriodikSeeder.php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\LembagaDataPeriodik;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class LembagaDataPeriodikSeeder extends Seeder
{
    public function run(): void
    {
        $lembagaList = Lembaga::whereIn('npsn', ['20223344', '20223355'])->get();

        foreach ($lembagaList as $lembaga) {
            $aktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();

            if (! $aktif) {
                continue;
            }

            foreach ($aktif->semester as $semester) {
                LembagaDataPeriodik::firstOrCreate(
                    ['lembaga_id' => $lembaga->id, 'semester_id' => $semester->id],
                    [
                        'waktu_penyelenggaraan' => 'Pagi',
                        'sumber_listrik' => 'PLN',
                        'daya_listrik' => 5500,
                        'akses_internet' => 'Telkom Indihome (Fiber Optik)',
                        'status_bos' => true,
                        'sertifikasi_iso' => null,
                        'ketersediaan_air_bersih' => true,
                        'kecukupan_air_bersih' => true,
                        'jumlah_tempat_cuci_tangan' => 8,
                        'jumlah_jamban' => 6,
                        'stratifikasi_uks' => 'Strata 3 (Optimal)',
                        'media_kie_sanitasi' => true,
                    ]
                );
            }
        }
    }
}
```

- [ ] **Step 4: Write `LayananKhususLembagaSeeder`, `ProgramInklusiLembagaSeeder`, `EkstrakurikulerLembagaSeeder`**

```php
<?php
// database/seeders/LayananKhususLembagaSeeder.php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\LayananKhususLembaga;
use Illuminate\Database\Seeder;

class LayananKhususLembagaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::whereIn('npsn', ['20223344', '20223355'])->get() as $lembaga) {
            LayananKhususLembaga::firstOrCreate(
                ['lembaga_id' => $lembaga->id, 'jenis_layanan' => 'Kelas Tahfidz Intensif'],
                [
                    'no_sk' => 'SK.021/Yayasan/2020',
                    'tmt' => '2020-07-01',
                    'tst' => null,
                    'keterangan' => 'Program unggulan hafalan Al-Qur\'an minimal 5 juz sebelum lulus.',
                ]
            );
        }
    }
}
```

```php
<?php
// database/seeders/ProgramInklusiLembagaSeeder.php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\ProgramInklusiLembaga;
use Illuminate\Database\Seeder;

class ProgramInklusiLembagaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::whereIn('npsn', ['20223344', '20223355'])->get() as $lembaga) {
            ProgramInklusiLembaga::firstOrCreate(
                ['lembaga_id' => $lembaga->id, 'kebutuhan_khusus' => 'Tunadaksa'],
                [
                    'no_sk' => 'SK.033/Yayasan/2021',
                    'tanggal_sk' => '2021-02-10',
                    'tmt' => '2021-07-01',
                    'tst' => null,
                    'keterangan' => 'Menyediakan akses ramah kursi roda dan pendamping belajar.',
                ]
            );
        }
    }
}
```

```php
<?php
// database/seeders/EkstrakurikulerLembagaSeeder.php

namespace Database\Seeders;

use App\Models\EkstrakurikulerLembaga;
use App\Models\Lembaga;
use Illuminate\Database\Seeder;

class EkstrakurikulerLembagaSeeder extends Seeder
{
    public function run(): void
    {
        $smp = Lembaga::where('npsn', '20223344')->firstOrFail();
        $sma = Lembaga::where('npsn', '20223355')->firstOrFail();

        $this->seedEkskul($smp, [
            ['Olahraga', 'Futsal', 4],
            ['Kepramukaan', 'Pramuka', 2],
            ['Keagamaan', 'Qiroah', 2],
        ]);

        $this->seedEkskul($sma, [
            ['Olahraga', 'Basket', 4],
            ['Kepramukaan', 'Paskibra', 3],
            ['Seni', 'Teater', 2],
        ]);
    }

    private function seedEkskul(Lembaga $lembaga, array $ekskulList): void
    {
        foreach ($ekskulList as [$jenis, $nama, $jam]) {
            EkstrakurikulerLembaga::firstOrCreate(
                ['lembaga_id' => $lembaga->id, 'nama_ekskul' => $nama],
                [
                    'jenis_ekskul' => $jenis,
                    'no_sk' => 'SK.'.random_int(100, 999).'/Yayasan/2024',
                    'tanggal_sk' => '2024-07-01',
                    'jam_per_minggu' => $jam,
                ]
            );
        }
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/LembagaDataPeriodikSeederTest.php tests/Unit/LembagaProfileSeedersTest.php`
Expected: PASS (5/5)

- [ ] **Step 6: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all pre-existing tests still pass.

- [ ] **Step 7: Commit**

```bash
git add database/seeders/LembagaDataPeriodikSeeder.php database/seeders/LayananKhususLembagaSeeder.php database/seeders/ProgramInklusiLembagaSeeder.php database/seeders/EkstrakurikulerLembagaSeeder.php tests/Unit/LembagaDataPeriodikSeederTest.php tests/Unit/LembagaProfileSeedersTest.php
git commit -m "feat: add lembaga profile/facility seeders, splitting them out of DemoDataSeeder"
```

---

### Task 6: PPDB Configuration Seeders

**Files:**
- Create: `database/seeders/JenisTesMasterSeeder.php`
- Create: `database/seeders/GelombangPpdbSeeder.php`
- Create: `database/seeders/JalurPpdbSeeder.php`
- Create: `database/seeders/FormulirFieldSeeder.php`
- Create: `database/seeders/DokumenSyaratPpdbSeeder.php`
- Create: `database/seeders/SeleksiPpdbSeeder.php`
- Test: `tests/Unit/JenisTesMasterSeederTest.php`
- Test: `tests/Unit/PpdbConfigurationSeedersTest.php` (covers `GelombangPpdbSeeder`/`JalurPpdbSeeder`/`FormulirFieldSeeder`/`DokumenSyaratPpdbSeeder`/`SeleksiPpdbSeeder` together, since they form one coherent PPDB-configuration story per jalur that only makes sense tested as a whole — matching how `DemoDataSeeder`'s own `seedKonfigurasiPpdb` already treats them as one unit)

**Interfaces:**
- Consumes: `Lembaga` rows by NPSN (Task 1), `TahunAjaran` rows (active and inactive, Task 3).
- Produces: for SMP, `JalurPpdb`/`GelombangPpdb`/etc. rows exist in BOTH its inactive 2025/2026 tahun ajaran (config only, no active-wizard use) and its active 2026/2027 tahun ajaran. For SMA, only in its active 2026/2027 tahun ajaran. Task 7's `NominalTagihanJalurSeeder` looks up `JalurPpdb` scoped to each lembaga's ACTIVE tahun ajaran only.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Unit/JenisTesMasterSeederTest.php

use App\Models\JenisTesMaster;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Database\Seeders\JenisTesMasterSeeder;
use Database\Seeders\LembagaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
});

it('seeds jenis tes distinct per lembaga', function () {
    (new JenisTesMasterSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    expect(JenisTesMaster::where('lembaga_id', $smp->id)->where('nama', 'Tes Baca Al-Qur\'an')->exists())->toBeTrue();

    $sma = Lembaga::where('npsn', '20223355')->first();
    expect(JenisTesMaster::where('lembaga_id', $sma->id)->where('nama', 'Tes Potensi Akademik')->exists())->toBeTrue();
    expect(JenisTesMaster::where('lembaga_id', $sma->id)->where('nama', 'Tes Baca Al-Qur\'an')->exists())->toBeFalse();
});

it('is idempotent when run twice', function () {
    (new JenisTesMasterSeeder())->run();
    (new JenisTesMasterSeeder())->run();

    expect(JenisTesMaster::count())->toBe(6);
});
```

```php
<?php
// tests/Unit/PpdbConfigurationSeedersTest.php

use App\Models\DokumenSyaratPpdb;
use App\Models\FormulirField;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\JenisTesMaster;
use App\Models\Lembaga;
use App\Models\SeleksiPpdb;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Database\Seeders\DokumenSyaratPpdbSeeder;
use Database\Seeders\FormulirFieldSeeder;
use Database\Seeders\GelombangPpdbSeeder;
use Database\Seeders\JalurPpdbSeeder;
use Database\Seeders\JenisTesMasterSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\SeleksiPpdbSeeder;
use Database\Seeders\SemesterSeeder;
use Database\Seeders\TahunAjaranSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
    (new TahunAjaranSeeder())->run();
    (new SemesterSeeder())->run();
    (new JenisTesMasterSeeder())->run();
});

function jalankanKonfigurasiPpdb(): void
{
    (new GelombangPpdbSeeder())->run();
    (new JalurPpdbSeeder())->run();
    (new FormulirFieldSeeder())->run();
    (new DokumenSyaratPpdbSeeder())->run();
    (new SeleksiPpdbSeeder())->run();
}

it('seeds SMP PPDB configuration in BOTH the inactive and active tahun ajaran', function () {
    jalankanKonfigurasiPpdb();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $lama = TahunAjaran::where('lembaga_id', $smp->id)->where('status_aktif', false)->first();
    $baru = TahunAjaran::where('lembaga_id', $smp->id)->where('status_aktif', true)->first();

    expect(JalurPpdb::where('lembaga_id', $smp->id)->where('tahun_ajaran_id', $lama->id)->where('nama', 'Reguler')->exists())->toBeTrue();
    expect(JalurPpdb::where('lembaga_id', $smp->id)->where('tahun_ajaran_id', $baru->id)->where('nama', 'Reguler')->exists())->toBeTrue();
});

it('seeds SMA PPDB configuration only in its active tahun ajaran', function () {
    jalankanKonfigurasiPpdb();

    $sma = Lembaga::where('npsn', '20223355')->first();
    $baru = TahunAjaran::where('lembaga_id', $sma->id)->where('status_aktif', true)->first();

    expect(JalurPpdb::where('lembaga_id', $sma->id)->where('tahun_ajaran_id', $baru->id)->where('nama', 'Reguler')->exists())->toBeTrue();
    expect(GelombangPpdb::where('lembaga_id', $sma->id)->where('tahun_ajaran_id', $baru->id)->exists())->toBeTrue();
});

it('seeds 3 jalur (Reguler, Prestasi, Afirmasi) with their formulir/dokumen/seleksi for the SMP active tahun ajaran', function () {
    jalankanKonfigurasiPpdb();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $baru = TahunAjaran::where('lembaga_id', $smp->id)->where('status_aktif', true)->first();

    $reguler = JalurPpdb::where('lembaga_id', $smp->id)->where('tahun_ajaran_id', $baru->id)->where('nama', 'Reguler')->first();
    expect($reguler)->not->toBeNull();
    expect(FormulirField::where('jalur_ppdb_id', $reguler->id)->where('label', 'Sekolah Asal')->exists())->toBeTrue();
    expect(DokumenSyaratPpdb::where('jalur_ppdb_id', $reguler->id)->where('nama_dokumen', 'Akta Kelahiran')->exists())->toBeTrue();
    expect(SeleksiPpdb::where('jalur_ppdb_id', $reguler->id)->count())->toBe(2);

    $afirmasi = JalurPpdb::where('lembaga_id', $smp->id)->where('tahun_ajaran_id', $baru->id)->where('nama', 'Afirmasi')->first();
    expect($afirmasi)->not->toBeNull();
    expect(SeleksiPpdb::where('jalur_ppdb_id', $afirmasi->id)->count())->toBe(0);
});

it('is idempotent when run twice', function () {
    jalankanKonfigurasiPpdb();
    $jalurSebelum = JalurPpdb::count();
    jalankanKonfigurasiPpdb();

    expect(JalurPpdb::count())->toBe($jalurSebelum);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/JenisTesMasterSeederTest.php tests/Unit/PpdbConfigurationSeedersTest.php`
Expected: FAIL — none of the 6 classes exist yet.

- [ ] **Step 3: Write `JenisTesMasterSeeder`**

```php
<?php
// database/seeders/JenisTesMasterSeeder.php

namespace Database\Seeders;

use App\Models\JenisTesMaster;
use App\Models\Lembaga;
use Illuminate\Database\Seeder;

class JenisTesMasterSeeder extends Seeder
{
    public function run(): void
    {
        $smp = Lembaga::where('npsn', '20223344')->firstOrFail();
        $sma = Lembaga::where('npsn', '20223355')->firstOrFail();

        $this->seedJenisTes($smp, ['Tes Tulis', 'Wawancara', 'Tes Baca Al-Qur\'an']);
        $this->seedJenisTes($sma, ['Tes Tulis', 'Tes Wawancara', 'Tes Potensi Akademik']);
    }

    private function seedJenisTes(Lembaga $lembaga, array $namaList): void
    {
        foreach ($namaList as $nama) {
            JenisTesMaster::firstOrCreate(
                ['lembaga_id' => $lembaga->id, 'nama' => $nama],
                ['deskripsi' => "Seleksi berupa {$nama} yang dinilai oleh tim penerimaan murid baru."]
            );
        }
    }
}
```

- [ ] **Step 4: Write `GelombangPpdbSeeder`**

```php
<?php
// database/seeders/GelombangPpdbSeeder.php

namespace Database\Seeders;

use App\Models\GelombangPpdb;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class GelombangPpdbSeeder extends Seeder
{
    public function run(): void
    {
        $smp = Lembaga::where('npsn', '20223344')->firstOrFail();
        $sma = Lembaga::where('npsn', '20223355')->firstOrFail();

        $smpLama = TahunAjaran::where('lembaga_id', $smp->id)->where('status_aktif', false)->firstOrFail();
        $smpBaru = TahunAjaran::where('lembaga_id', $smp->id)->where('status_aktif', true)->firstOrFail();
        $smaBaru = TahunAjaran::where('lembaga_id', $sma->id)->where('status_aktif', true)->firstOrFail();

        // Tahun ajaran lama SMP: tanggal tetap (2025), sengaja hanya untuk uji fitur duplikasi,
        // bukan untuk wizard SPMB publik sungguhan.
        $this->seedGelombang($smp, $smpLama, [
            ['nama' => 'Gelombang 1', 'tanggal_buka' => '2025-01-06', 'tanggal_tutup' => '2025-02-14', 'kuota' => 80],
            ['nama' => 'Gelombang 2', 'tanggal_buka' => '2025-03-03', 'tanggal_tutup' => '2025-04-11', 'kuota' => 40],
        ]);

        // Tahun ajaran aktif SMP dan SMA: tanggal relatif ke hari ini, supaya selalu "sedang buka"
        // kapan pun seeder dijalankan -- wizard SPMB publik langsung bisa diuji.
        $this->seedGelombang($smp, $smpBaru, [
            ['nama' => 'Gelombang 1', 'tanggal_buka' => now()->subDays(5)->toDateString(), 'tanggal_tutup' => now()->addMonths(2)->toDateString(), 'kuota' => 80],
            ['nama' => 'Gelombang 2', 'tanggal_buka' => now()->addMonths(3)->toDateString(), 'tanggal_tutup' => now()->addMonths(4)->toDateString(), 'kuota' => 40],
        ]);

        $this->seedGelombang($sma, $smaBaru, [
            ['nama' => 'Gelombang 1', 'tanggal_buka' => now()->subDays(5)->toDateString(), 'tanggal_tutup' => now()->addMonths(2)->toDateString(), 'kuota' => 120],
            ['nama' => 'Gelombang 2', 'tanggal_buka' => now()->addMonths(3)->toDateString(), 'tanggal_tutup' => now()->addMonths(4)->toDateString(), 'kuota' => 60],
        ]);
    }

    private function seedGelombang(Lembaga $lembaga, TahunAjaran $tahunAjaran, array $gelombangConfig): void
    {
        foreach ($gelombangConfig as $g) {
            GelombangPpdb::firstOrCreate(
                ['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => $g['nama']],
                [
                    'tanggal_buka' => $g['tanggal_buka'],
                    'tanggal_tutup' => $g['tanggal_tutup'],
                    'kuota' => $g['kuota'],
                ]
            );
        }
    }
}
```

- [ ] **Step 5: Write `JalurPpdbSeeder`**

```php
<?php
// database/seeders/JalurPpdbSeeder.php

namespace Database\Seeders;

use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class JalurPpdbSeeder extends Seeder
{
    public function run(): void
    {
        $smp = Lembaga::where('npsn', '20223344')->firstOrFail();
        $sma = Lembaga::where('npsn', '20223355')->firstOrFail();

        $smpLama = TahunAjaran::where('lembaga_id', $smp->id)->where('status_aktif', false)->firstOrFail();
        $smpBaru = TahunAjaran::where('lembaga_id', $smp->id)->where('status_aktif', true)->firstOrFail();
        $smaBaru = TahunAjaran::where('lembaga_id', $sma->id)->where('status_aktif', true)->firstOrFail();

        $this->seedJalur($smp, $smpLama, $this->jalurSmp());
        $this->seedJalur($smp, $smpBaru, $this->jalurSmp());
        $this->seedJalur($sma, $smaBaru, $this->jalurSma());
    }

    /**
     * @return array<int, array{nama: string, deskripsi: string, status_aktif: bool}>
     */
    public function jalurSmp(): array
    {
        return [
            ['nama' => 'Reguler', 'deskripsi' => 'Jalur pendaftaran umum berdasarkan urutan pendaftaran dan kelengkapan berkas.', 'status_aktif' => true],
            ['nama' => 'Prestasi', 'deskripsi' => 'Jalur khusus bagi calon murid dengan prestasi akademik atau non-akademik.', 'status_aktif' => true],
            ['nama' => 'Afirmasi', 'deskripsi' => 'Jalur bagi calon murid dari keluarga kurang mampu, bebas biaya pendaftaran.', 'status_aktif' => true],
        ];
    }

    /**
     * @return array<int, array{nama: string, deskripsi: string, status_aktif: bool}>
     */
    public function jalurSma(): array
    {
        return [
            ['nama' => 'Reguler', 'deskripsi' => 'Jalur pendaftaran umum berdasarkan nilai rapor dan hasil tes seleksi.', 'status_aktif' => true],
            ['nama' => 'Prestasi', 'deskripsi' => 'Jalur khusus bagi calon murid dengan prestasi akademik, olahraga, atau seni tingkat kabupaten/kota ke atas.', 'status_aktif' => true],
            ['nama' => 'Afirmasi', 'deskripsi' => 'Jalur bagi calon murid dari keluarga kurang mampu, bebas biaya pendaftaran.', 'status_aktif' => true],
        ];
    }

    private function seedJalur(Lembaga $lembaga, TahunAjaran $tahunAjaran, array $jalurConfig): void
    {
        foreach ($jalurConfig as $j) {
            JalurPpdb::firstOrCreate(
                ['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => $j['nama']],
                [
                    'deskripsi' => $j['deskripsi'],
                    'status_aktif' => $j['status_aktif'],
                ]
            );
        }
    }
}
```

- [ ] **Step 6: Write `FormulirFieldSeeder`**

```php
<?php
// database/seeders/FormulirFieldSeeder.php

namespace Database\Seeders;

use App\Models\FormulirField;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class FormulirFieldSeeder extends Seeder
{
    public function run(): void
    {
        $smp = Lembaga::where('npsn', '20223344')->firstOrFail();
        $sma = Lembaga::where('npsn', '20223355')->firstOrFail();

        foreach (TahunAjaran::where('lembaga_id', $smp->id)->get() as $tahunAjaran) {
            $this->seedFormulir($smp, $tahunAjaran, $this->formulirSmp());
        }

        $smaBaru = TahunAjaran::where('lembaga_id', $sma->id)->where('status_aktif', true)->firstOrFail();
        $this->seedFormulir($sma, $smaBaru, $this->formulirSma());
    }

    /**
     * @return array<string, array<int, array{label: string, field_type: string, is_required: bool, options: ?array}>>
     */
    private function formulirSmp(): array
    {
        return [
            'Reguler' => [
                ['label' => 'Sekolah Asal', 'field_type' => 'text', 'is_required' => true, 'options' => null],
                ['label' => 'Nilai Rata-rata Rapor', 'field_type' => 'number', 'is_required' => true, 'options' => null],
            ],
            'Prestasi' => [
                ['label' => 'Jenis Prestasi', 'field_type' => 'select', 'is_required' => true, 'options' => ['Akademik', 'Non-Akademik', 'Keagamaan']],
                ['label' => 'Uraian Prestasi', 'field_type' => 'textarea', 'is_required' => true, 'options' => null],
                ['label' => 'Sertifikat Pendukung', 'field_type' => 'file', 'is_required' => true, 'options' => null],
            ],
            'Afirmasi' => [],
        ];
    }

    /**
     * @return array<string, array<int, array{label: string, field_type: string, is_required: bool, options: ?array}>>
     */
    private function formulirSma(): array
    {
        return [
            'Reguler' => [
                ['label' => 'Sekolah Asal', 'field_type' => 'text', 'is_required' => true, 'options' => null],
                ['label' => 'Pilihan Jurusan', 'field_type' => 'select', 'is_required' => true, 'options' => ['IPA', 'IPS']],
            ],
            'Prestasi' => [
                ['label' => 'Tingkat Prestasi', 'field_type' => 'select', 'is_required' => true, 'options' => ['Kabupaten/Kota', 'Provinsi', 'Nasional', 'Internasional']],
                ['label' => 'Uraian Prestasi', 'field_type' => 'textarea', 'is_required' => true, 'options' => null],
            ],
            'Afirmasi' => [],
        ];
    }

    private function seedFormulir(Lembaga $lembaga, TahunAjaran $tahunAjaran, array $formulirPerJalur): void
    {
        foreach ($formulirPerJalur as $namaJalur => $fields) {
            $jalur = JalurPpdb::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $tahunAjaran->id)->where('nama', $namaJalur)->first();

            if (! $jalur) {
                continue;
            }

            foreach ($fields as $urutan => $field) {
                FormulirField::firstOrCreate(
                    ['jalur_ppdb_id' => $jalur->id, 'label' => $field['label']],
                    [
                        'lembaga_id' => $lembaga->id,
                        'field_type' => $field['field_type'],
                        'options' => $field['options'],
                        'is_required' => $field['is_required'],
                        'urutan' => $urutan,
                    ]
                );
            }
        }
    }
}
```

- [ ] **Step 7: Write `DokumenSyaratPpdbSeeder`**

```php
<?php
// database/seeders/DokumenSyaratPpdbSeeder.php

namespace Database\Seeders;

use App\Models\DokumenSyaratPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class DokumenSyaratPpdbSeeder extends Seeder
{
    public function run(): void
    {
        $smp = Lembaga::where('npsn', '20223344')->firstOrFail();
        $sma = Lembaga::where('npsn', '20223355')->firstOrFail();

        foreach (TahunAjaran::where('lembaga_id', $smp->id)->get() as $tahunAjaran) {
            $this->seedDokumen($smp, $tahunAjaran, $this->dokumenSmp());
        }

        $smaBaru = TahunAjaran::where('lembaga_id', $sma->id)->where('status_aktif', true)->firstOrFail();
        $this->seedDokumen($sma, $smaBaru, $this->dokumenSma());
    }

    /**
     * @return array<string, array<int, array{nama_dokumen: string, wajib: bool}>>
     */
    private function dokumenSmp(): array
    {
        return [
            'Reguler' => [
                ['nama_dokumen' => 'Akta Kelahiran', 'wajib' => true],
                ['nama_dokumen' => 'Kartu Keluarga', 'wajib' => true],
                ['nama_dokumen' => 'Fotokopi Rapor', 'wajib' => true],
                ['nama_dokumen' => 'Pas Foto 3x4', 'wajib' => true],
            ],
            'Prestasi' => [
                ['nama_dokumen' => 'Akta Kelahiran', 'wajib' => true],
                ['nama_dokumen' => 'Kartu Keluarga', 'wajib' => true],
                ['nama_dokumen' => 'Sertifikat/Piagam Prestasi', 'wajib' => true],
            ],
            'Afirmasi' => [
                ['nama_dokumen' => 'Kartu Keluarga Sejahtera (KKS) / SKTM', 'wajib' => true],
                ['nama_dokumen' => 'Akta Kelahiran', 'wajib' => true],
            ],
        ];
    }

    /**
     * @return array<string, array<int, array{nama_dokumen: string, wajib: bool}>>
     */
    private function dokumenSma(): array
    {
        return [
            'Reguler' => [
                ['nama_dokumen' => 'Ijazah / SKL SMP', 'wajib' => true],
                ['nama_dokumen' => 'Kartu Keluarga', 'wajib' => true],
                ['nama_dokumen' => 'Fotokopi Rapor Kelas VII-IX', 'wajib' => true],
                ['nama_dokumen' => 'Pas Foto 3x4', 'wajib' => true],
            ],
            'Prestasi' => [
                ['nama_dokumen' => 'Ijazah / SKL SMP', 'wajib' => true],
                ['nama_dokumen' => 'Sertifikat/Piagam Prestasi', 'wajib' => true],
            ],
            'Afirmasi' => [
                ['nama_dokumen' => 'Kartu Keluarga Sejahtera (KKS) / SKTM', 'wajib' => true],
                ['nama_dokumen' => 'Ijazah / SKL SMP', 'wajib' => true],
            ],
        ];
    }

    private function seedDokumen(Lembaga $lembaga, TahunAjaran $tahunAjaran, array $dokumenPerJalur): void
    {
        foreach ($dokumenPerJalur as $namaJalur => $dokumenList) {
            $jalur = JalurPpdb::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $tahunAjaran->id)->where('nama', $namaJalur)->first();

            if (! $jalur) {
                continue;
            }

            foreach ($dokumenList as $urutan => $dokumen) {
                DokumenSyaratPpdb::firstOrCreate(
                    ['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => $dokumen['nama_dokumen']],
                    [
                        'lembaga_id' => $lembaga->id,
                        'wajib' => $dokumen['wajib'],
                        'urutan' => $urutan,
                    ]
                );
            }
        }
    }
}
```

- [ ] **Step 8: Write `SeleksiPpdbSeeder`**

```php
<?php
// database/seeders/SeleksiPpdbSeeder.php

namespace Database\Seeders;

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\JenisTesMaster;
use App\Models\Lembaga;
use App\Models\SeleksiPpdb;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class SeleksiPpdbSeeder extends Seeder
{
    public function run(): void
    {
        $smp = Lembaga::where('npsn', '20223344')->firstOrFail();
        $sma = Lembaga::where('npsn', '20223355')->firstOrFail();

        foreach (TahunAjaran::where('lembaga_id', $smp->id)->get() as $tahunAjaran) {
            $this->seedSeleksi($smp, $tahunAjaran, $this->seleksiSmp());
        }

        $smaBaru = TahunAjaran::where('lembaga_id', $sma->id)->where('status_aktif', true)->firstOrFail();
        $this->seedSeleksi($sma, $smaBaru, $this->seleksiSma());
    }

    /**
     * @return array<string, array<int, array{gelombang: string, jenis_tes: string, jadwal: string, kriteria: string, bobot: int}>>
     */
    private function seleksiSmp(): array
    {
        return [
            'Reguler' => [
                ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Tes Tulis', 'jadwal' => '2025-02-20 08:00:00', 'kriteria' => 'Nilai minimal 65', 'bobot' => 60],
                ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Wawancara', 'jadwal' => '2025-02-21 08:00:00', 'kriteria' => 'Lolos wawancara motivasi', 'bobot' => 40],
            ],
            'Prestasi' => [
                ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Wawancara', 'jadwal' => '2025-02-22 09:00:00', 'kriteria' => 'Verifikasi keaslian sertifikat & wawancara', 'bobot' => 100],
            ],
            'Afirmasi' => [],
        ];
    }

    /**
     * @return array<string, array<int, array{gelombang: string, jenis_tes: string, jadwal: string, kriteria: string, bobot: int}>>
     */
    private function seleksiSma(): array
    {
        return [
            'Reguler' => [
                ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Tes Tulis', 'jadwal' => '2026-08-24 08:00:00', 'kriteria' => 'Nilai minimal 70', 'bobot' => 50],
                ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Tes Potensi Akademik', 'jadwal' => '2026-08-25 08:00:00', 'kriteria' => 'Skor TPA minimal 60', 'bobot' => 50],
            ],
            'Prestasi' => [
                ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Tes Wawancara', 'jadwal' => '2026-08-26 09:00:00', 'kriteria' => 'Verifikasi keaslian sertifikat & wawancara', 'bobot' => 100],
            ],
            'Afirmasi' => [],
        ];
    }

    private function seedSeleksi(Lembaga $lembaga, TahunAjaran $tahunAjaran, array $seleksiPerJalur): void
    {
        foreach ($seleksiPerJalur as $namaJalur => $seleksiList) {
            $jalur = JalurPpdb::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $tahunAjaran->id)->where('nama', $namaJalur)->first();

            if (! $jalur) {
                continue;
            }

            foreach ($seleksiList as $seleksi) {
                $gelombang = GelombangPpdb::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $tahunAjaran->id)->where('nama', $seleksi['gelombang'])->first();
                $jenisTes = JenisTesMaster::where('lembaga_id', $lembaga->id)->where('nama', $seleksi['jenis_tes'])->first();

                if (! $gelombang || ! $jenisTes) {
                    continue;
                }

                SeleksiPpdb::firstOrCreate(
                    [
                        'jalur_ppdb_id' => $jalur->id,
                        'gelombang_ppdb_id' => $gelombang->id,
                        'jenis_tes_master_id' => $jenisTes->id,
                    ],
                    [
                        'lembaga_id' => $lembaga->id,
                        'jadwal' => $seleksi['jadwal'],
                        'kriteria_kelulusan' => $seleksi['kriteria'],
                        'bobot' => $seleksi['bobot'],
                    ]
                );
            }
        }
    }
}
```

- [ ] **Step 9: Run tests to verify they pass**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/JenisTesMasterSeederTest.php tests/Unit/PpdbConfigurationSeedersTest.php`
Expected: PASS (6/6)

- [ ] **Step 10: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all pre-existing tests still pass.

- [ ] **Step 11: Commit**

```bash
git add database/seeders/JenisTesMasterSeeder.php database/seeders/GelombangPpdbSeeder.php database/seeders/JalurPpdbSeeder.php database/seeders/FormulirFieldSeeder.php database/seeders/DokumenSyaratPpdbSeeder.php database/seeders/SeleksiPpdbSeeder.php tests/Unit/JenisTesMasterSeederTest.php tests/Unit/PpdbConfigurationSeedersTest.php
git commit -m "feat: add PPDB configuration seeders, splitting them out of DemoDataSeeder"
```

---

### Task 7: `JenisTagihanSeeder` and `NominalTagihanJalurSeeder`

**Files:**
- Create: `database/seeders/JenisTagihanSeeder.php`
- Create: `database/seeders/NominalTagihanJalurSeeder.php`
- Test: `tests/Unit/JenisTagihanSeederTest.php`
- Test: `tests/Unit/NominalTagihanJalurSeederTest.php`

**Interfaces:**
- Consumes: `Lembaga` rows by NPSN (Task 1), the active `TahunAjaran`'s `JalurPpdb` rows (Task 6).
- Produces: no later task in this plan depends on these — this is genuinely new content (the PRD's "nominal dinamis, termasuk gratis" principle demonstrated with real seed data instead of an empty table).

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Unit/JenisTagihanSeederTest.php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Database\Seeders\JenisTagihanSeeder;
use Database\Seeders\LembagaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
});

it('seeds Biaya Pendaftaran (not cicilable) and Uang Pangkal (cicilable, max 3) per lembaga', function () {
    (new JenisTagihanSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();

    $pendaftaran = JenisTagihan::where('lembaga_id', $smp->id)->where('nama', 'Biaya Pendaftaran')->first();
    expect($pendaftaran)->not->toBeNull();
    expect($pendaftaran->kategori)->toBe('pendaftaran');
    expect($pendaftaran->bisa_dicicil)->toBeFalse();

    $daftarUlang = JenisTagihan::where('lembaga_id', $smp->id)->where('nama', 'Uang Pangkal')->first();
    expect($daftarUlang)->not->toBeNull();
    expect($daftarUlang->kategori)->toBe('daftar_ulang');
    expect($daftarUlang->bisa_dicicil)->toBeTrue();
    expect($daftarUlang->maks_cicilan)->toBe(3);
});

it('is idempotent when run twice', function () {
    (new JenisTagihanSeeder())->run();
    (new JenisTagihanSeeder())->run();

    expect(JenisTagihan::count())->toBe(4);
});
```

```php
<?php
// tests/Unit/NominalTagihanJalurSeederTest.php

use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\NominalTagihanJalur;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Database\Seeders\JalurPpdbSeeder;
use Database\Seeders\JenisTagihanSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\NominalTagihanJalurSeeder;
use Database\Seeders\TahunAjaranSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
    (new TahunAjaranSeeder())->run();
    (new JalurPpdbSeeder())->run();
    (new JenisTagihanSeeder())->run();
});

it('sets a real nominal for Reguler and exactly 0 for Afirmasi, and skips Prestasi entirely', function () {
    (new NominalTagihanJalurSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $aktif = TahunAjaran::where('lembaga_id', $smp->id)->where('status_aktif', true)->first();
    $pendaftaran = JenisTagihan::where('lembaga_id', $smp->id)->where('nama', 'Biaya Pendaftaran')->first();

    $reguler = JalurPpdb::where('lembaga_id', $smp->id)->where('tahun_ajaran_id', $aktif->id)->where('nama', 'Reguler')->first();
    $nominalReguler = NominalTagihanJalur::where('jenis_tagihan_id', $pendaftaran->id)->where('jalur_ppdb_id', $reguler->id)->first();
    expect((int) $nominalReguler->nominal)->toBe(150000);

    $afirmasi = JalurPpdb::where('lembaga_id', $smp->id)->where('tahun_ajaran_id', $aktif->id)->where('nama', 'Afirmasi')->first();
    $nominalAfirmasi = NominalTagihanJalur::where('jenis_tagihan_id', $pendaftaran->id)->where('jalur_ppdb_id', $afirmasi->id)->first();
    expect((int) $nominalAfirmasi->nominal)->toBe(0);

    $prestasi = JalurPpdb::where('lembaga_id', $smp->id)->where('tahun_ajaran_id', $aktif->id)->where('nama', 'Prestasi')->first();
    expect(NominalTagihanJalur::where('jenis_tagihan_id', $pendaftaran->id)->where('jalur_ppdb_id', $prestasi->id)->exists())->toBeFalse();
});

it('does not set nominal against the inactive tahun ajaran jalur for SMP', function () {
    (new NominalTagihanJalurSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $lama = TahunAjaran::where('lembaga_id', $smp->id)->where('status_aktif', false)->first();
    $jalurLama = JalurPpdb::where('lembaga_id', $smp->id)->where('tahun_ajaran_id', $lama->id)->pluck('id');

    expect(NominalTagihanJalur::whereIn('jalur_ppdb_id', $jalurLama)->exists())->toBeFalse();
});

it('is idempotent when run twice', function () {
    (new NominalTagihanJalurSeeder())->run();
    $sebelum = NominalTagihanJalur::count();
    (new NominalTagihanJalurSeeder())->run();

    expect(NominalTagihanJalur::count())->toBe($sebelum);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/JenisTagihanSeederTest.php tests/Unit/NominalTagihanJalurSeederTest.php`
Expected: FAIL — neither class exists yet.

- [ ] **Step 3: Write `JenisTagihanSeeder`**

```php
<?php
// database/seeders/JenisTagihanSeeder.php

namespace Database\Seeders;

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use Illuminate\Database\Seeder;

class JenisTagihanSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::whereIn('npsn', ['20223344', '20223355'])->get() as $lembaga) {
            JenisTagihan::firstOrCreate(
                ['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran'],
                ['bisa_dicicil' => false, 'maks_cicilan' => null]
            );

            JenisTagihan::firstOrCreate(
                ['lembaga_id' => $lembaga->id, 'nama' => 'Uang Pangkal', 'kategori' => 'daftar_ulang'],
                ['bisa_dicicil' => true, 'maks_cicilan' => 3]
            );
        }
    }
}
```

- [ ] **Step 4: Write `NominalTagihanJalurSeeder`**

```php
<?php
// database/seeders/NominalTagihanJalurSeeder.php

namespace Database\Seeders;

use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\NominalTagihanJalur;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class NominalTagihanJalurSeeder extends Seeder
{
    public function run(): void
    {
        $smp = Lembaga::where('npsn', '20223344')->firstOrFail();
        $sma = Lembaga::where('npsn', '20223355')->firstOrFail();

        // Prestasi sengaja TIDAK diberi nominal apapun (baik SMP maupun SMA) -- demonstrasi
        // yang sudah mapan bahwa TagihanGenerator melewati kombinasi jenis-tagihan x jalur yang
        // belum dikonfigurasi, tidak pernah membuat tagihan Rp0 palsu untuk itu.
        $this->seedNominal($smp, ['Reguler' => 150000, 'Afirmasi' => 0], 'pendaftaran');
        $this->seedNominal($smp, ['Reguler' => 3000000, 'Afirmasi' => 0], 'daftar_ulang');

        $this->seedNominal($sma, ['Reguler' => 200000, 'Afirmasi' => 0], 'pendaftaran');
        $this->seedNominal($sma, ['Reguler' => 4500000, 'Afirmasi' => 0], 'daftar_ulang');
    }

    /**
     * @param  array<string, int>  $nominalPerJalur
     */
    private function seedNominal(Lembaga $lembaga, array $nominalPerJalur, string $kategori): void
    {
        $aktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();

        if (! $aktif) {
            return;
        }

        $jenisTagihanNama = $kategori === 'pendaftaran' ? 'Biaya Pendaftaran' : 'Uang Pangkal';
        $jenisTagihan = JenisTagihan::where('lembaga_id', $lembaga->id)->where('nama', $jenisTagihanNama)->first();

        if (! $jenisTagihan) {
            return;
        }

        foreach ($nominalPerJalur as $namaJalur => $nominal) {
            $jalur = JalurPpdb::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $aktif->id)->where('nama', $namaJalur)->first();

            if (! $jalur) {
                continue;
            }

            NominalTagihanJalur::firstOrCreate(
                ['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id],
                ['nominal' => $nominal]
            );
        }
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/JenisTagihanSeederTest.php tests/Unit/NominalTagihanJalurSeederTest.php`
Expected: PASS (6/6)

- [ ] **Step 6: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all pre-existing tests still pass.

- [ ] **Step 7: Commit**

```bash
git add database/seeders/JenisTagihanSeeder.php database/seeders/NominalTagihanJalurSeeder.php tests/Unit/JenisTagihanSeederTest.php tests/Unit/NominalTagihanJalurSeederTest.php
git commit -m "feat: add JenisTagihanSeeder and NominalTagihanJalurSeeder with realistic dummy nominal data"
```

---

### Task 8: Wire `DatabaseSeeder`, Delete `DemoDataSeeder`, Update the M4 Manual Testing Guide

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`
- Delete: `database/seeders/DemoDataSeeder.php`
- Modify: `docs/pengujian-manual/2026-07-16-m4-keuangan-master-tagihan-manual-testing.md`

**Interfaces:**
- Consumes: all 20 seeders from Tasks 1-7, plus `PermissionSeeder`/`RoleSeeder`/`YayasanSeeder`/`JabatanTambahanMasterSeeder`/`EssentialUserSeeder`/`M3DemoDataSeeder` (already registered from before this sub-project or from sub-project 1).
- Produces: a fully wired `DatabaseSeeder::run()` — the final integration point of this whole sub-project.

- [ ] **Step 1: Confirm no test references `DemoDataSeeder` directly**

Run (from the project root, using Git Bash or any POSIX-compatible shell available in this environment):
```
grep -rl "DemoDataSeeder" --include="*.php" . --exclude-dir=vendor --exclude-dir=node_modules
```
Expected output: only `database/seeders/DatabaseSeeder.php` and `database/seeders/DemoDataSeeder.php` itself. If any test file appears in this list, STOP and report BLOCKED — that means a test directly instantiates `DemoDataSeeder` and this task's plan (which assumes no such reference exists) needs to be revisited before deleting the file.

- [ ] **Step 2: Update `database/seeders/DatabaseSeeder.php`**

Replace the file with:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            YayasanSeeder::class,
            JabatanTambahanMasterSeeder::class,
            LembagaSeeder::class,
            EssentialUserSeeder::class,
            UserSeeder::class,
            TahunAjaranSeeder::class,
            SemesterSeeder::class,
            GuruSeeder::class,
            RiwayatPendidikanGuruSeeder::class,
            SertifikasiGuruSeeder::class,
            GuruJabatanTambahanSeeder::class,
            LembagaDataPeriodikSeeder::class,
            LayananKhususLembagaSeeder::class,
            ProgramInklusiLembagaSeeder::class,
            EkstrakurikulerLembagaSeeder::class,
            JenisTesMasterSeeder::class,
            GelombangPpdbSeeder::class,
            JalurPpdbSeeder::class,
            FormulirFieldSeeder::class,
            DokumenSyaratPpdbSeeder::class,
            SeleksiPpdbSeeder::class,
            JenisTagihanSeeder::class,
            NominalTagihanJalurSeeder::class,
            M3DemoDataSeeder::class,
        ]);
    }
}
```

(Note: the old file imported `use App\Models\User;` for a type hint that no longer exists in this rewritten version — that import is correctly dropped, matching the whole-branch review note from the RBAC seeder cleanup sub-project that flagged it as unused.)

- [ ] **Step 3: Delete `database/seeders/DemoDataSeeder.php`**

```bash
rm database/seeders/DemoDataSeeder.php
```

- [ ] **Step 4: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all tests pass, with the same or higher total count as the last task (this task adds no new automated tests of its own — it's pure integration — but it must not break any of the 20 seeders' own tests, `M3DemoDataSeederTest`, or any other test that seeds via `DatabaseSeeder`).

- [ ] **Step 5: Run a real `migrate:fresh --seed` to confirm the full chain works end-to-end**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan migrate:fresh --seed`
Expected: completes with no errors. This exercises the real MySQL database (not the isolated test database), so it's the closest thing to a true end-to-end confirmation that the dependency order in Step 2 is correct.

- [ ] **Step 6: Update the M4 Keuangan manual testing guide**

In `docs/pengujian-manual/2026-07-16-m4-keuangan-master-tagihan-manual-testing.md`, replace this paragraph:

```
**Penting — tidak ada data jenis tagihan bawaan.** Berbeda dengan modul-modul sebelumnya, `migrate:fresh --seed` **tidak** membuat jenis tagihan/nominal apapun — tabelnya benar-benar kosong di awal. Ini disengaja: langkah pertama panduan ini adalah membuat konfigurasinya sendiri, supaya kamu melihat seluruh alur dari nol persis seperti admin keuangan sungguhan akan mengalaminya.
```

with:

```
**Catatan — jenis tagihan sekarang sudah ter-seed.** Sejak pembersihan arsitektur seeder, `migrate:fresh --seed` sudah otomatis membuat "Biaya Pendaftaran" dan "Uang Pangkal" untuk SMP dan SMA, lengkap dengan nominal per jalur (termasuk Rp0 untuk jalur Afirmasi) — kecuali jalur **Prestasi**, yang sengaja dibiarkan tanpa nominal supaya kamu tetap bisa menguji perilaku "lewati saja, jangan buat tagihan palsu" di Bagian 2 tanpa perlu membuat konfigurasi kosong secara manual dulu.
```

Then replace the whole of "## 1. Membuat Jenis Tagihan & Nominal per Jalur" (steps 1.1 through 1.6) with:

```
## 1. Memverifikasi Jenis Tagihan & Nominal yang Sudah Ter-seed

Login sebagai `keuangan.smp@alhikmah.sch.id`.

- [ ] **1.1** Cek sidebar kiri — harus ada grup **"IV. Keuangan"** dengan menu "Jenis Tagihan" dan "Tagihan". Klik "Jenis Tagihan".
- [ ] **1.2** Harus sudah ada 2 baris: "Biaya Pendaftaran" (kategori Pendaftaran) dan "Uang Pangkal" (kategori Daftar Ulang, bisa dicicil maks 3x) — bukan halaman kosong seperti sebelumnya.
- [ ] **1.3** Buka "Kelola Nominal" pada "Biaya Pendaftaran" — jalur **Reguler** harus terisi `150000`, jalur **Afirmasi** harus terisi `0` (gratis beneran, bukan kosong), jalur **Prestasi** harus **kosong** (sengaja belum dikonfigurasi — ini yang akan diuji di Bagian 2).
- [ ] **1.4** Ulangi pengecekan yang sama untuk "Uang Pangkal" — Reguler `3000000`, Afirmasi `0`, Prestasi kosong.
```

Renumber the rest of the document's sections accordingly (what was "## 2." stays "## 2." since its content doesn't change, just its lead-in no longer needs to say "buat dari nol" — leave the rest of the document as-is; only the two blocks above and any obvious "kosong dari awal" phrasing pointing back at them need updating).

- [ ] **Step 7: Commit**

```bash
git add database/seeders/DatabaseSeeder.php docs/pengujian-manual/2026-07-16-m4-keuangan-master-tagihan-manual-testing.md
git rm database/seeders/DemoDataSeeder.php
git commit -m "refactor: wire the 20 new master-data seeders into DatabaseSeeder, remove DemoDataSeeder"
```

---

## Post-Plan Note

After Task 8, `DemoDataSeeder.php` no longer exists — its entire content lives in 20 focused, single-table seeders, plus 2 genuinely new ones for `jenis_tagihan`/`nominal_tagihan_jalur`. This closes sub-project 2 of 3 in the seeder-architecture-cleanup initiative. Sub-project 3 (transactional/scenario data — `CalonMuridSeeder`, `PendaftaranSeeder`, `TagihanSeeder`, etc., replacing `M3DemoDataSeeder`/`PembayaranDemoSeeder`) is a separate, not-yet-started plan.
