# RBAC v2: Konsistensi Penamaan Permission & Perbaikan Tool Sync Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Perbaiki tool `permissions:sync` yang terbukti rusak (regex-nya cuma menerima permission 2 segmen, tidak scan `canAny()`/FormRequest/Policy), bangun kemampuan audit konsistensi permission yang bisa dipakai berulang, jadikan hasilnya test regresi permanen, dan lengkapi label modul di halaman Role & Permission Builder.

**Architecture:** Satu service baru (`PermissionUsageScanner`) jadi satu-satunya sumber kebenaran untuk "permission apa saja yang benar-benar dipakai kode" — dipakai ULANG oleh `SyncPermissions` (command) dan test regresi permanen, supaya logic scan tidak terduplikasi di 2 tempat.

**Tech Stack:** Laravel 11, Pest tests, regex (PHP native `preg_match`/`preg_match_all`).

## Global Constraints

- **TIDAK ada rename permission apa pun tanpa persetujuan eksplisit user per-item.** Plan ini murni membangun kemampuan audit + memperbaiki tool, BUKAN mengeksekusi perbaikan atas semua temuan yang mungkin muncul.
- **Konvensi penamaan permission**: `modul.turunan.aksi` — urutan tetap, TAPI jumlah segmen BOLEH bervariasi (2 segmen `rapor.verify` maupun 3 segmen `pengadaan.lpj.submit` sama-sama valid tergantung apakah modul itu punya sub-fitur). JANGAN memaksa keseragaman jumlah segmen di kode manapun yang ditulis plan ini.
- **1 temuan RUSAK dari audit sudah diperbaiki di luar plan ini** (commit `2e58e80`, sebelum plan ini ditulis): `resources/views/admin/jadwal-pelajaran/_matrix-roster.blade.php` — `@can('pola-jam.kelola')` diganti jadi `@can('pola-jam.view')` (permission yang tidak pernah terdaftar, sekarang cocok dengan permission yang benar-benar menggerbang `admin.pola-jam.index`, tujuan tombol itu). Task 3 di plan ini mengasumsikan TIDAK ADA lagi temuan "rusak" tersisa di kode saat ini — kalau ternyata masih ada, itu artinya ada perubahan lain sejak plan ini ditulis, LAPORKAN ke user, jangan langsung diperbaiki sendiri.
- **4 permission "mati" (terdaftar, tidak dipakai kode manapun) TETAP DIBIARKAN, TIDAK dihapus**: `audit-log.view`, `keuangan.akses`, `pengadaan.proposal.delete`, `workflow.config.manage`. Ini keputusan spec (§4.4) — kategori "mati" cuma informasi, bukan hard-fail, karena bisa jadi memang sengaja disiapkan untuk fitur yang belum dibangun.
- Tests: jalankan HANYA test yang di-scope ke task itu di setiap task, sinkron di shell (jangan di-background lalu menunggu notifikasi). Full suite HANYA sekali di task terakhir, dan HANYA setelah bertanya ke user dulu.

---

## File Map

| File | Task | Keterangan |
|---|---|---|
| `app/Services/PermissionUsageScanner.php` | 1 | Service baru, sumber kebenaran scan |
| `tests/Unit/Services/PermissionUsageScannerTest.php` | 1 | Test service |
| `app/Console/Commands/SyncPermissions.php` | 2 | Refactor pakai scanner baru |
| `tests/Feature/Console/SyncPermissionsTest.php` | 2 | Test command (baru, sebelumnya 0 test) |
| `tests/Unit/PermissionConsistencyTest.php` | 3 | Test regresi permanen |
| `app/Services/PermissionCatalog.php` | 4 | + 24 `MODULE_LABELS` baru |
| `tests/Unit/Services/PermissionCatalogTest.php` | 4 | Test label (baru) |
| `.agents/logs/2026-08-20-0900-rbac-v2-permission-consistency.md` | 5 | Handoff log + laporan audit |

---

### Task 1: `PermissionUsageScanner` — Service Scan Reusable

**Files:**
- Create: `app/Services/PermissionUsageScanner.php`
- Test: `tests/Unit/Services/PermissionUsageScannerTest.php`

**Interfaces:**
- Produces: `PermissionUsageScanner::scanCodeUsage(): array<string, array<int, string>>` (nama permission → daftar file path yang memakainya). `PermissionUsageScanner::scanSeederRegistrations(): array<string, array<int, string>>` (nama permission → daftar file seeder yang mendaftarkannya).

**Regex yang WAJIB dipakai persis seperti ini** (sudah diverifikasi via eksekusi nyata `php -r`, JANGAN diubah tanpa menguji ulang seperti cara ini — regex lama di `SyncPermissions` rusak justru karena tidak pernah diuji terhadap kasus nyata):

```php
// Menangkap $this->authorize('a.b') ATAU $this->authorize('a.b.c') ATAU ->can('a.b') dst -
// TIDAK dibatasi jumlah segmen (beda dari regex lama yang cuma terima tepat 2 segmen/1 titik).
private const PATTERN_SINGLE = '/(?:->authorize\(|->can\()\s*[\'"]([a-z0-9\-]+(?:\.[a-z0-9\-]+)+)[\'"]\s*\)/';

// Menangkap isi array di dalam ->canAny([...]) - hasil capture group 1-nya (isi dalam kurung
// siku) lalu diproses lagi oleh PATTERN_ITEM untuk ekstrak tiap string permission di dalamnya.
private const PATTERN_CAN_ANY = '/->canAny\(\s*\[(.*?)\]\s*\)/s';

// Menangkap @can('a.b') di blade (pola sama seperti PATTERN_SINGLE tapi untuk sintaks blade).
private const PATTERN_BLADE_CAN = '/@can\(\s*[\'"]([a-z0-9\-]+(?:\.[a-z0-9\-]+)+)[\'"]\s*\)/';

// Menangkap isi array di dalam @canany([...]) - sama seperti PATTERN_CAN_ANY untuk blade.
private const PATTERN_BLADE_CAN_ANY = '/@canany\(\s*\[(.*?)\]\s*\)/s';

// Dipakai untuk ekstrak SETIAP string bertitik dari isi capture group PATTERN_CAN_ANY/
// PATTERN_BLADE_CAN_ANY (yang isinya bisa berupa beberapa string dipisah koma).
private const PATTERN_ITEM = '/[\'"]([a-z0-9\-]+(?:\.[a-z0-9\-]+)+)[\'"]/';
```

Verifikasi nyata yang membuktikan pola ini benar (dijalankan sebelum plan ini ditulis):

```
$this->authorize("rapor.verify");                                    => ["rapor.verify"]
$this->authorize('pengadaan.lpj.submit');                             => ["pengadaan.lpj.submit"]
->canAny(['rapor.verify', 'rapor.approve'])                           => ["rapor.verify","rapor.approve"]
return $this->user()->can('rapor.ajukan');                            => ["rapor.ajukan"]
->canAny(['pengadaan.approval.internal', 'pengadaan.approval.yayasan']) => ["pengadaan.approval.internal","pengadaan.approval.yayasan"]
@can("rapor.verify")                                                  => ["rapor.verify"]
@canany(['rapor.verify', 'rapor.approve'])                            => ["rapor.verify","rapor.approve"]
```

- [ ] **Step 1: Tulis test yang gagal**

Create `tests/Unit/Services/PermissionUsageScannerTest.php`:

```php
<?php

use App\Services\PermissionUsageScanner;
use Illuminate\Support\Facades\File;

function buatFileSementara(string $relatifDir, string $namaFile, string $isi): string
{
    $dir = base_path($relatifDir);
    File::ensureDirectoryExists($dir);
    $path = $dir.'/'.$namaFile;
    File::put($path, $isi);

    return $path;
}

afterEach(function () {
    File::deleteDirectory(base_path('tests/tmp-scanner-fixture'));
});

it('extracts a 2-segment and a 3-segment authorize() permission from a controller-like file', function () {
    buatFileSementara('tests/tmp-scanner-fixture/controllers', 'Fixture1Controller.php', <<<'PHP'
<?php
class Fixture1Controller {
    public function index() {
        $this->authorize('rapor.verify');
    }
    public function submit() {
        $this->authorize('pengadaan.lpj.submit');
    }
}
PHP);

    $found = (new PermissionUsageScanner())->scanCodeUsage(['tests/tmp-scanner-fixture/controllers']);

    expect(array_keys($found))->toContain('rapor.verify', 'pengadaan.lpj.submit');
});

it('extracts every permission listed inside a canAny([...]) call', function () {
    buatFileSementara('tests/tmp-scanner-fixture/controllers', 'Fixture2Controller.php', <<<'PHP'
<?php
class Fixture2Controller {
    public function index() {
        abort_unless($this->request->user()->canAny(['rapor.verify', 'rapor.approve']), 403);
    }
}
PHP);

    $found = (new PermissionUsageScanner())->scanCodeUsage(['tests/tmp-scanner-fixture/controllers']);

    expect(array_keys($found))->toContain('rapor.verify', 'rapor.approve');
});

it('extracts a can() permission from a FormRequest-like authorize() method', function () {
    buatFileSementara('tests/tmp-scanner-fixture/requests', 'FixtureRequest.php', <<<'PHP'
<?php
class FixtureRequest {
    public function authorize(): bool {
        return $this->user()->can('rapor.ajukan');
    }
}
PHP);

    $found = (new PermissionUsageScanner())->scanCodeUsage(['tests/tmp-scanner-fixture/requests']);

    expect(array_keys($found))->toContain('rapor.ajukan');
});

it('extracts @can and @canany permission names from a blade-like file', function () {
    buatFileSementara('tests/tmp-scanner-fixture/views', 'fixture.blade.php', <<<'PHP'
@can('rapor.verify')
    <p>Visible</p>
@endcan
@canany(['rapor.verify', 'rapor.approve'])
    <p>Also visible</p>
@endcanany
PHP);

    $found = (new PermissionUsageScanner())->scanCodeUsage(['tests/tmp-scanner-fixture/views']);

    expect(array_keys($found))->toContain('rapor.verify', 'rapor.approve');
});

it('records which file each permission was found in', function () {
    $path = buatFileSementara('tests/tmp-scanner-fixture/controllers', 'Fixture3Controller.php', <<<'PHP'
<?php
class Fixture3Controller {
    public function index() {
        $this->authorize('kasus.view');
    }
}
PHP);

    $found = (new PermissionUsageScanner())->scanCodeUsage(['tests/tmp-scanner-fixture/controllers']);

    expect($found['kasus.view'])->toContain($path);
});

it('extracts registered permission names from a seeder-like file, ignoring the array variable indirection', function () {
    buatFileSementara('tests/tmp-scanner-fixture/seeders', 'FixtureSeeder.php', <<<'PHP'
<?php
class FixtureSeeder {
    public function run(): void {
        $permissions = ['rapor.verify', 'pengadaan.lpj.submit'];
        foreach ($permissions as $name) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }
}
PHP);

    $found = (new PermissionUsageScanner())->scanSeederRegistrations(['tests/tmp-scanner-fixture/seeders/FixtureSeeder.php']);

    expect(array_keys($found))->toContain('rapor.verify', 'pengadaan.lpj.submit');
});
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test tests/Unit/Services/PermissionUsageScannerTest.php`
Expected: FAIL — `Class "App\Services\PermissionUsageScanner" not found`

- [ ] **Step 3: Tulis implementasi**

Create `app/Services/PermissionUsageScanner.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;

final class PermissionUsageScanner
{
    private const PATTERN_SINGLE = '/(?:->authorize\(|->can\()\s*[\'"]([a-z0-9\-]+(?:\.[a-z0-9\-]+)+)[\'"]\s*\)/';
    private const PATTERN_CAN_ANY = '/->canAny\(\s*\[(.*?)\]\s*\)/s';
    private const PATTERN_BLADE_CAN = '/@can\(\s*[\'"]([a-z0-9\-]+(?:\.[a-z0-9\-]+)+)[\'"]\s*\)/';
    private const PATTERN_BLADE_CAN_ANY = '/@canany\(\s*\[(.*?)\]\s*\)/s';
    private const PATTERN_ITEM = '/[\'"]([a-z0-9\-]+(?:\.[a-z0-9\-]+)+)[\'"]/';

    /**
     * Pindai penggunaan permission (authorize()/can()/canAny() di PHP, @can/@canany di blade)
     * di seluruh direktori yang diberikan.
     *
     * @param  array<int, string>  $directories  path relatif dari base_path(), mis. 'app/Http/Controllers'
     * @return array<string, array<int, string>> nama permission => daftar file path yang memakainya
     */
    public function scanCodeUsage(array $directories): array
    {
        $found = [];

        foreach ($directories as $directory) {
            $absolute = base_path($directory);
            if (! File::isDirectory($absolute)) {
                continue;
            }

            foreach (File::allFiles($absolute) as $file) {
                $extension = $file->getExtension();
                if ($extension !== 'php') {
                    continue;
                }

                $contents = File::get($file->getPathname());
                $isBlade = str_ends_with($file->getFilename(), '.blade.php');

                $singlePattern = $isBlade ? self::PATTERN_BLADE_CAN : self::PATTERN_SINGLE;
                $anyPattern = $isBlade ? self::PATTERN_BLADE_CAN_ANY : self::PATTERN_CAN_ANY;

                $this->collectSingle($contents, $singlePattern, $file->getPathname(), $found);
                $this->collectFromArrayCalls($contents, $anyPattern, $file->getPathname(), $found);
            }
        }

        return $found;
    }

    /**
     * Pindai permission yang terdaftar di file seeder (mis. Permission::firstOrCreate(['name' => $name, ...])
     * di dalam sebuah loop atas array literal) - mengekstrak SEMUA string bertitik dalam file itu,
     * bukan cuma yang langsung jadi argumen firstOrCreate(), karena permission biasanya didaftarkan
     * lewat variabel array, bukan literal langsung di pemanggilan firstOrCreate().
     *
     * @param  array<int, string>  $seederFiles  path relatif dari base_path() ke file seeder spesifik
     * @return array<string, array<int, string>>
     */
    public function scanSeederRegistrations(array $seederFiles): array
    {
        $found = [];

        foreach ($seederFiles as $relativePath) {
            $absolute = base_path($relativePath);
            if (! File::exists($absolute)) {
                continue;
            }

            $contents = File::get($absolute);
            if (preg_match_all(self::PATTERN_ITEM, $contents, $matches)) {
                foreach ($matches[1] as $name) {
                    $found[$name][] = $absolute;
                }
            }
        }

        return $found;
    }

    private function collectSingle(string $contents, string $pattern, string $path, array &$found): void
    {
        if (preg_match_all($pattern, $contents, $matches)) {
            foreach ($matches[1] as $name) {
                $found[$name][] = $path;
            }
        }
    }

    private function collectFromArrayCalls(string $contents, string $pattern, string $path, array &$found): void
    {
        if (preg_match_all($pattern, $contents, $matches)) {
            foreach ($matches[1] as $arrayContent) {
                if (preg_match_all(self::PATTERN_ITEM, $arrayContent, $itemMatches)) {
                    foreach ($itemMatches[1] as $name) {
                        $found[$name][] = $path;
                    }
                }
            }
        }
    }
}
```

- [ ] **Step 4: Jalankan test**

Run: `php artisan test tests/Unit/Services/PermissionUsageScannerTest.php`
Expected: PASS — 6 test lulus.

- [ ] **Step 5: Commit**

```bash
git add app/Services/PermissionUsageScanner.php tests/Unit/Services/PermissionUsageScannerTest.php
git commit -m "feat(rbac): tambah PermissionUsageScanner - sumber kebenaran scan permission"
```

---

### Task 2: Refactor `SyncPermissions` Pakai Scanner Baru

**Files:**
- Modify: `app/Console/Commands/SyncPermissions.php`
- Test: `tests/Feature/Console/SyncPermissionsTest.php` (baru — command ini SEBELUMNYA tidak punya test sama sekali)

**Interfaces:**
- Consumes: `PermissionUsageScanner::scanCodeUsage(array $directories): array<string, array<int, string>>` (Task 1).

- [ ] **Step 1: Tulis test yang gagal**

Create `tests/Feature/Console/SyncPermissionsTest.php`:

```php
<?php

use App\Http\Controllers\Admin\RaporController;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;

it('creates a permission referenced via a 3-segment authorize() call, which the old broken regex could never catch', function () {
    // Bukti regresi: sebelum diperbaiki, tool ini TIDAK PERNAH bisa mendeteksi permission
    // bersegmen-3 seperti yang dipakai di seluruh modul Pengadaan (mis. pengadaan.lpj.submit) -
    // dibuktikan lewat eksekusi regex lama secara langsung sebelum plan ini ditulis (0 match).
    expect(Permission::where('name', 'pengadaan.lpj.submit')->exists())->toBeFalse();

    $this->artisan('permissions:sync')->assertExitCode(0);

    expect(Permission::where('name', 'pengadaan.lpj.submit')->exists())->toBeTrue();
});

it('creates a permission referenced only via canAny([...]), which the old tool never scanned for', function () {
    expect(Permission::where('name', 'rapor.approve')->exists())->toBeFalse();

    $this->artisan('permissions:sync')->assertExitCode(0);

    expect(Permission::where('name', 'rapor.approve')->exists())->toBeTrue();
});

it('creates a permission referenced only inside a FormRequest authorize() method', function () {
    expect(Permission::where('name', 'rapor.ajukan')->exists())->toBeFalse();

    $this->artisan('permissions:sync')->assertExitCode(0);

    expect(Permission::where('name', 'rapor.ajukan')->exists())->toBeTrue();
});

it('reports stale permissions that exist in the database but are referenced by no code', function () {
    Permission::firstOrCreate(['name' => 'benar-benar.tidak-dipakai', 'guard_name' => 'web']);

    $this->artisan('permissions:sync')
        ->expectsOutputToContain('benar-benar.tidak-dipakai')
        ->assertExitCode(0);
});

it('is idempotent - running it twice creates no duplicate rows', function () {
    $this->artisan('permissions:sync')->assertExitCode(0);
    $firstCount = Permission::count();

    $this->artisan('permissions:sync')->assertExitCode(0);

    expect(Permission::count())->toBe($firstCount);
});
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test tests/Feature/Console/SyncPermissionsTest.php`
Expected: FAIL — minimal 3 test gagal (`pengadaan.lpj.submit`/`rapor.approve`/`rapor.ajukan` tidak pernah dibuat karena regex lama tidak mendeteksinya).

- [ ] **Step 3: Refactor implementasi**

Edit `app/Console/Commands/SyncPermissions.php` — ganti isi file sepenuhnya jadi:

```php
<?php

namespace App\Console\Commands;

use App\Services\PermissionUsageScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync';

    protected $description = 'Scan app/Http/Controllers, app/Http/Requests, app/Policies, and resources/views for authorize()/can()/canAny()/@can/@canany permission usage, and sync them into the permissions table';

    public function handle(PermissionUsageScanner $scanner): int
    {
        $found = array_keys($scanner->scanCodeUsage([
            'app/Http/Controllers',
            'app/Http/Requests',
            'app/Policies',
            'resources/views',
        ]));

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
            $this->warn('Permissions in database but not referenced by any controller/request/policy/view (not deleted automatically):');
            foreach ($stale as $name) {
                $this->line("  - {$name}");
            }
        }

        if ($createdCount === 0 && $stale->isEmpty()) {
            $this->info('Permissions already in sync.');
        }

        return self::SUCCESS;
    }
}
```

(Method `scanControllers()` yang lama DIHAPUS SELURUHNYA — logic-nya sudah dipindah jadi `PermissionUsageScanner::scanCodeUsage()` yang lebih benar dan reusable. Import `File` tidak lagi dipakai langsung di file ini kalau IDE/linter komplain unused import, boleh dihapus — cek dulu apakah masih dipakai sebelum menghapus.)

- [ ] **Step 4: Jalankan test**

Run: `php artisan test tests/Feature/Console/SyncPermissionsTest.php`
Expected: PASS — 5 test lulus.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/SyncPermissions.php tests/Feature/Console/SyncPermissionsTest.php
git commit -m "fix(rbac): SyncPermissions sekarang scan canAny/FormRequest/Policy, terima segmen apa pun"
```

---

### Task 3: Test Regresi Permanen — `PermissionConsistencyTest`

**Files:**
- Create: `tests/Unit/PermissionConsistencyTest.php`

**Interfaces:**
- Consumes: `PermissionUsageScanner::scanCodeUsage(array)`/`scanSeederRegistrations(array)` (Task 1).

**Konteks penting**: hasil audit NYATA yang sudah dijalankan sebelum plan ini ditulis (dengan scanner yang SAMA seperti Task 1, dipastikan reproducible) menunjukkan **0 permission "rusak"** saat ini (temuan satu-satunya, `pola-jam.kelola`, sudah diperbaiki di commit `2e58e80` sebelum plan ini — lihat Global Constraints). Karena itu, `$allowlistTemuanLama` di dalam test Step 1 di bawah ini SENGAJA KOSONG — kalau saat menjalankan test ini ternyata ADA yang gagal, itu artinya ada permission BARU yang tidak konsisten (bukan temuan lama yang sudah diketahui), dan HARUS dilaporkan ke user, BUKAN langsung ditambahkan ke allowlist tanpa persetujuan.

- [ ] **Step 1: Tulis test**

Create `tests/Unit/PermissionConsistencyTest.php`:

```php
<?php

use App\Services\PermissionUsageScanner;

it('does not use any permission in controllers, requests, policies, or blade views that is not registered in a seeder', function () {
    // Daftar permission yang SUDAH DIKETAHUI tidak konsisten SAAT test ini ditulis, dan SUDAH
    // disetujui user untuk dibiarkan sementara (bukan diperbaiki otomatis oleh test ini).
    // KOSONG per audit terakhir (lihat Task 3 plan RBAC v2) - satu-satunya temuan
    // (pola-jam.kelola) sudah diperbaiki sebelum test ini ditulis. JANGAN tambahkan entri baru
    // ke sini tanpa persetujuan eksplisit user - kalau test ini gagal karena permission BARU
    // yang tidak konsisten, laporkan dulu, jangan langsung di-allowlist.
    // Variabel lokal (bukan konstanta top-level) sengaja dipilih supaya tidak berisiko
    // "cannot redeclare" kalau ada test file lain yang kebetulan pakai nama sama - Pest
    // me-require SEMUA file test ke satu proses PHP yang sama saat full suite jalan.
    $allowlistTemuanLama = [];

    $scanner = new PermissionUsageScanner();

    $used = array_keys($scanner->scanCodeUsage([
        'app/Http/Controllers',
        'app/Http/Requests',
        'app/Policies',
        'resources/views',
    ]));

    $registered = array_keys($scanner->scanSeederRegistrations([
        'database/seeders/PermissionSeeder.php',
        'database/seeders/PengadaanPermissionSeeder.php',
        'database/seeders/SarprasPermissionSeeder.php',
    ]));

    $rusak = array_values(array_diff($used, $registered, $allowlistTemuanLama));

    expect($rusak)->toBe([], 'Permission dipakai di kode tapi TIDAK terdaftar di seeder manapun (selalu gagal akses untuk siapa pun): '.implode(', ', $rusak));
});
```

- [ ] **Step 2: Jalankan test**

Run: `php artisan test tests/Unit/PermissionConsistencyTest.php`
Expected: PASS — 1 test lulus (karena satu-satunya temuan rusak sudah diperbaiki sebelum plan ini). Kalau GAGAL, baca pesan assertion-nya (berisi daftar nama permission yang bermasalah) dan LAPORKAN ke user — JANGAN tambahkan ke `$allowlistTemuanLama` sendiri tanpa persetujuan.

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/PermissionConsistencyTest.php
git commit -m "test(rbac): tambah PermissionConsistencyTest - jaring pengaman permanen"
```

---

### Task 4: Perbarui `PermissionCatalog::MODULE_LABELS`

**Files:**
- Modify: `app/Services/PermissionCatalog.php`
- Test: `tests/Unit/Services/PermissionCatalogTest.php` (baru — sebelumnya tidak ada test untuk file ini)

**Interfaces:**
- Konsumsi tidak berubah — `PermissionCatalog::grouped(): array` tetap method publik yang sama, hanya isi `MODULE_LABELS` yang ditambah.

**Daftar 24 modul yang saat ini belum punya label** (hasil audit nyata — modul terdaftar di seeder yang belum ada di `MODULE_LABELS`, jatuh fallback `ucfirst()`):

```
asesmen, jabatan-tambahan-master, jadwal-pelajaran, jam-pelajaran, jenis-karyawan-master,
kalender-akademik, karyawan, kasus, kelas, kenaikan-kelas, keuangan, komponen-penilaian,
mata-pelajaran, orang-tua, pengadaan, pengaturan-akademik, pola-jam, presensi, rapor, rpp,
sarpras, whatsapp-template, workflow, yayasan
```

- [ ] **Step 1: Tulis test yang gagal**

Create `tests/Unit/Services/PermissionCatalogTest.php`:

```php
<?php

use App\Services\PermissionCatalog;
use Spatie\Permission\Models\Permission;

it('uses a human-readable Indonesian label for every module added in prior sub-tasks, not the ucfirst() fallback', function () {
    $modulesToCheck = [
        'rapor' => 'Rapor',
        'komponen-penilaian' => 'Komponen Penilaian',
        'kasus' => 'Kasus',
        'pengadaan' => 'Pengadaan',
        'sarpras' => 'Sarana & Prasarana',
        'rpp' => 'RPP',
    ];

    foreach ($modulesToCheck as $module => $expectedSubstring) {
        Permission::firstOrCreate(['name' => "{$module}.view", 'guard_name' => 'web']);
    }

    $grouped = PermissionCatalog::grouped();
    $labelsByModule = collect($grouped)->pluck('label', 'module');

    foreach ($modulesToCheck as $module => $expectedSubstring) {
        expect($labelsByModule[$module])->not->toBe(ucfirst($module));
    }
});
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test tests/Unit/Services/PermissionCatalogTest.php`
Expected: FAIL — modul-modul itu masih jatuh ke fallback `ucfirst()`.

- [ ] **Step 3: Perbarui `MODULE_LABELS`**

Edit `app/Services/PermissionCatalog.php` — tambahkan 24 baris berikut ke dalam array `MODULE_LABELS` (setelah baris `'cicilan' => 'Cicilan',`, sebelum penutup `];`):

```php
        'asesmen' => 'Asesmen',
        'jabatan-tambahan-master' => 'Jabatan Tambahan',
        'jadwal-pelajaran' => 'Jadwal Pelajaran',
        'jam-pelajaran' => 'Jam Pelajaran',
        'jenis-karyawan-master' => 'Jenis Karyawan',
        'kalender-akademik' => 'Kalender Akademik',
        'karyawan' => 'Karyawan',
        'kasus' => 'Manajemen Kasus Siswa',
        'kelas' => 'Kelas',
        'kenaikan-kelas' => 'Kenaikan Kelas',
        'keuangan' => 'Keuangan',
        'komponen-penilaian' => 'Komponen Penilaian (TP)',
        'mata-pelajaran' => 'Mata Pelajaran',
        'orang-tua' => 'Orang Tua',
        'pengadaan' => 'Pengadaan Sarpras',
        'pengaturan-akademik' => 'Pengaturan Akademik',
        'pola-jam' => 'Pola Jam',
        'presensi' => 'Presensi & Jurnal KBM',
        'rapor' => 'Rapor',
        'rpp' => 'Perangkat Ajar (RPP)',
        'sarpras' => 'Sarana & Prasarana',
        'whatsapp-template' => 'Template WhatsApp',
        'workflow' => 'Konfigurasi Alur Kerja',
        'yayasan' => 'Yayasan',
```

- [ ] **Step 4: Jalankan test**

Run: `php artisan test tests/Unit/Services/PermissionCatalogTest.php`
Expected: PASS — 1 test lulus.

- [ ] **Step 5: Commit**

```bash
git add app/Services/PermissionCatalog.php tests/Unit/Services/PermissionCatalogTest.php
git commit -m "feat(rbac): lengkapi 24 label modul yang hilang di PermissionCatalog"
```

---

### Task 5: Verifikasi Akhir & Handoff Log

**Files:**
- Create: `.agents/logs/2026-08-20-0900-rbac-v2-permission-consistency.md`

- [ ] **Step 1: Jalankan seluruh test scoped sub-task ini sebagai regression bundle**

Run: `php artisan test tests/Unit/Services/PermissionUsageScannerTest.php tests/Feature/Console/SyncPermissionsTest.php tests/Unit/PermissionConsistencyTest.php tests/Unit/Services/PermissionCatalogTest.php tests/Feature/Admin/RoleBuilderTest.php tests/Feature/Admin/JadwalPelajaranCrudTest.php tests/Feature/Admin/PolaJamCrudTest.php`
Expected: semua PASS, 0 gagal.

- [ ] **Step 2: Minta izin user, lalu jalankan full test suite**

Tanya: "RBAC v2 selesai diimplementasikan. Jalankan full test suite `php artisan test` sekarang untuk verifikasi akhir?"

Kalau disetujui, jalankan (sinkron, JANGAN di-background):

Run: `php artisan test`
Expected: semua lulus, 0 gagal.

Kalau ada yang gagal, perbaiki dulu sebelum lanjut ke Step 3.

- [ ] **Step 3: Tulis handoff log**

Create `.agents/logs/2026-08-20-0900-rbac-v2-permission-consistency.md`:

```markdown
# 📋 Handoff Log: RBAC v2 — Konsistensi Penamaan Permission

- **Spec:** [`.agents/specs/2026-08-20-0900-rbac-v2-permission-consistency.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-20-0900-rbac-v2-permission-consistency.md)
- **Plan:** [`.agents/plans/2026-08-20-0900-rbac-v2-permission-consistency.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-20-0900-rbac-v2-permission-consistency.md)
- **Branch:** `rbac-v2`
- **Status:** 🟢 SELESAI

## Ringkasan

`app/Console/Commands/SyncPermissions.php` terbukti rusak (regex cuma terima permission 2 segmen, tidak scan `canAny()`/FormRequest/Policy) — dibuktikan lewat eksekusi nyata sebelum perbaikan dimulai. Diperbaiki lewat `PermissionUsageScanner` baru (dipakai ulang oleh command DAN test regresi permanen `PermissionConsistencyTest`). `PermissionCatalog::MODULE_LABELS` dilengkapi 24 modul yang sebelumnya jatuh fallback `ucfirst()`.

## Hasil Audit Nyata (dijalankan sebelum implementasi dimulai)

- **1 permission RUSAK ditemukan & diperbaiki** (commit `2e58e80`, sebelum plan ini): `pola-jam.kelola` dipakai di `_matrix-roster.blade.php` tapi tidak pernah terdaftar — diganti jadi `pola-jam.view` (cocok dengan permission yang menggerbang route tujuan tombolnya).
- **4 permission MATI** (terdaftar, tidak dipakai kode manapun — DIBIARKAN per keputusan spec, bukan bug): `audit-log.view`, `keuangan.akses`, `pengadaan.proposal.delete`, `workflow.config.manage`.
- **0 pasangan nama mencurigakan-mirip (typo-like)** ditemukan.

## Item Terbuka

1. FASE 5.1 (Restrukturisasi Rute Modular) — dibahas terpisah, belum masuk sub-task manapun, tidak bergantung pada RBAC v2 ini.
2. UI multi-role assignment — dikonfirmasi di luar scope sesi ini, `Admin\UserController` masih 1 role per user.
3. 4 permission "mati" di atas — kalau memang tidak akan pernah dipakai, bisa dipertimbangkan dihapus dari seeder di sub-task terpisah (bukan bagian pekerjaan ini).
```

- [ ] **Step 4: Commit**

```bash
git add .agents/logs/2026-08-20-0900-rbac-v2-permission-consistency.md
git commit -m "docs(rbac): tutup RBAC v2 - konsistensi penamaan permission selesai"
```

---

## Self-Review Notes

- **Spec coverage**: §4.1 (perbaikan regex) → Task 1+2. §4.2 (audit 3 arah) → sudah dijalankan sebelum plan ditulis, hasilnya dibakukan ke Task 3's allowlist (kosong) + Task 5's laporan. §4.3 (MODULE_LABELS) → Task 4. §4.4 (test permanen) → Task 3. §4.5 (test SyncPermissions) → Task 2. Tidak ada gap.
- **Placeholder scan**: bersih — semua step berisi kode lengkap, semua regex sudah diverifikasi via eksekusi nyata sebelum ditulis ke plan (bukan ditulis lalu diasumsikan benar seperti kesalahan `SyncPermissions` versi lama).
- **Konsistensi tipe/nama**: `PermissionUsageScanner::scanCodeUsage()`/`scanSeederRegistrations()` dipakai identik oleh Task 2 (command) dan Task 3 (test permanen) — satu sumber kebenaran, tidak ada logic scan terduplikasi.
