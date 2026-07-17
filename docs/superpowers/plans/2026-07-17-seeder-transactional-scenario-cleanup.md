# Seeder Transactional/Scenario Data Cleanup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Split `M3DemoDataSeeder.php` and `PembayaranDemoSeeder.php` (the last two monolithic demo seeders) into 11 single-table seeders, register all of them in `DatabaseSeeder` (including the payment chain, which was previously deliberately manual-only), extend the payment/cicilan/portal-account data to both lembaga (SMP and SMA, not SMP-only), then delete both old files. This is sub-project 3 of 3 (final) in the seeder-architecture-cleanup initiative.

**Architecture:** Every scenario record (a "calon", its "pendaftaran", its supporting documents/evaluations/decision) is looked up across seeders by a stable natural key — `CalonMurid.nama_lengkap` (deterministic, includes the lembaga name since `calon_murid` has no `lembaga_id` column) and `Pendaftaran.email_pendaftaran` + `lembaga_id`. This mirrors the email→User→Guru lookup chain established in sub-project 2. `SkemaCicilan`/`Cicilan` are the one exception to "each seeder only writes its own table": `PembayaranService::buatSkemaCicilan()` is existing production business logic that atomically creates both a `SkemaCicilan` row and its `Cicilan` rows in one transaction (computing per-termin amounts and due dates) — `SkemaCicilanSeeder` calls that service, and `CicilanSeeder` exists as `cicilan`'s dedicated, documented owner in the `DatabaseSeeder` chain, verifying (not re-creating) the rows the service produced, and failing loudly if that invariant is ever broken.

**Tech Stack:** Laravel 12, Pest PHP.

## Global Constraints

- All 11 new seeders are idempotent — running the full `DatabaseSeeder` twice must never error or duplicate rows. Most use `firstOrCreate`; `PembayaranSeeder` uses an explicit exists-check-then-create (no single natural unique key on `pembayaran` fits `firstOrCreate` cleanly); `SkemaCicilanSeeder` guards with `$tagihan->skemaCicilan()->exists()` because `PembayaranService::buatSkemaCicilan()` itself throws a `RuntimeException` if called twice on the same `Tagihan`.
- Content parity: every value (names, emails, dates, nominal amounts referenced by category) preserved exactly from `M3DemoDataSeeder.php`/`PembayaranDemoSeeder.php`, except the two changes explicitly approved in the spec: (a) `PembayaranDemoSeeder`'s scope extends from SMP-only to both lembaga, (b) `TagihanSeeder` no longer hardcodes nominal amounts (150000/900000) or creates its own `JenisTagihan`/`NominalTagihanJalur` rows — it looks up the real `NominalTagihanJalur` value already seeded by sub-project 2's `NominalTagihanJalurSeeder` for each lembaga's Reguler jalur, so SMP and SMA correctly get their own distinct, already-configured nominal amounts instead of one hardcoded value copy-pasted for both.
- All new seeders scope to both demo lembaga: `Lembaga::whereIn('npsn', ['20223344', '20223355'])->get()` (SMP, SMA).
- `AkunPendaftar::$fillable` is missing `email_verified_at` (same pre-existing mass-assignment gap pattern found and fixed for `User` in sub-project 2) — fixed in this plan's `AkunPendaftarSeeder` task.
- PHP is not on PATH — use `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe` for all `artisan`/test commands.

---

### Task 1: `CalonMuridSeeder` and `PendaftaranSeeder`

**Files:**
- Create: `database/seeders/CalonMuridSeeder.php`
- Create: `database/seeders/PendaftaranSeeder.php`
- Test: `tests/Unit/CalonMuridSeederTest.php`
- Test: `tests/Unit/PendaftaranSeederTest.php`

**Interfaces:**
- Consumes: `Lembaga` by npsn (sub-project 2), `TahunAjaran` active by `lembaga_id` (sub-project 2), `JalurPpdb` named `'Reguler'` scoped to the active tahun ajaran (sub-project 2), `GelombangPpdb` currently open (`tanggal_buka <= now() <= tanggal_tutup`) scoped to the active tahun ajaran (sub-project 2), `User` (any staff member) by `lembaga_id` (sub-project 2).
- Produces: 8 `CalonMurid` rows findable by `nama_lengkap` (`"Calon Menunggu Verifikasi (SMP Islam Al-Hikmah)"`, `"Calon Diterima (...)"`, `"Calon Ditolak (...)"`, `"Calon Cicilan Demo (...)"`, one set per lembaga). 8 `Pendaftaran` rows findable by `email_pendaftaran` + `lembaga_id` — `wali.menunggu@example.test`, `wali.diterima@example.test`, `wali.ditolak@example.test`, `wali.cicilan-demo@example.test`, each existing once per lembaga. Every later task in this plan looks up its parent `Pendaftaran` via this exact email + lembaga_id pair.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Unit/CalonMuridSeederTest.php

use App\Models\CalonMurid;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Database\Seeders\CalonMuridSeeder;
use Database\Seeders\LembagaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
});

it('seeds 4 calon per lembaga with lembaga-qualified names', function () {
    (new CalonMuridSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();

    expect(CalonMurid::where('nama_lengkap', 'Calon Menunggu Verifikasi ('.$smp->nama.')')->exists())->toBeTrue();
    expect(CalonMurid::where('nama_lengkap', 'Calon Diterima ('.$smp->nama.')')->exists())->toBeTrue();
    expect(CalonMurid::where('nama_lengkap', 'Calon Ditolak ('.$smp->nama.')')->exists())->toBeTrue();
    expect(CalonMurid::where('nama_lengkap', 'Calon Cicilan Demo ('.$smp->nama.')')->exists())->toBeTrue();
});

it('is idempotent when run twice', function () {
    (new CalonMuridSeeder())->run();
    (new CalonMuridSeeder())->run();

    expect(CalonMurid::count())->toBe(8);
});
```

```php
<?php
// tests/Unit/PendaftaranSeederTest.php

use App\Models\CalonMurid;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\CalonMuridSeeder;
use Database\Seeders\EssentialUserSeeder;
use Database\Seeders\GelombangPpdbSeeder;
use Database\Seeders\JalurPpdbSeeder;
use Database\Seeders\JenisTesMasterSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\PendaftaranSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SemesterSeeder;
use Database\Seeders\TahunAjaranSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
    (new EssentialUserSeeder())->run();
    (new UserSeeder())->run();
    (new TahunAjaranSeeder())->run();
    (new SemesterSeeder())->run();
    (new JenisTesMasterSeeder())->run();
    (new GelombangPpdbSeeder())->run();
    (new JalurPpdbSeeder())->run();
    (new CalonMuridSeeder())->run();
});

it('links each pendaftaran to the correct calon murid and lembaga, with decision fields set for diterima/ditolak', function () {
    (new PendaftaranSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $staf = User::where('lembaga_id', $smp->id)->first();

    $diterima = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
    expect($diterima)->not->toBeNull();
    expect($diterima->calonMurid->nama_lengkap)->toBe('Calon Diterima ('.$smp->nama.')');
    expect($diterima->status)->toBe('diterima');
    expect($diterima->ditetapkan_oleh_user_id)->toBe($staf->id);

    $ditolak = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.ditolak@example.test')->first();
    expect($ditolak->status)->toBe('ditolak');

    $menunggu = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.menunggu@example.test')->first();
    expect($menunggu->status)->toBe('menunggu_verifikasi');

    $cicilanDemo = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.cicilan-demo@example.test')->first();
    expect($cicilanDemo->status)->toBe('diterima');
    expect($cicilanDemo->kode_pendaftaran)->toBe('REG-PEMBAYARAN-DEMO-'.$smp->id);
});

it('does not mix up the same scenario email between SMP and SMA', function () {
    (new PendaftaranSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $sma = Lembaga::where('npsn', '20223355')->first();

    $pendaftaranSmp = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
    $pendaftaranSma = Pendaftaran::where('lembaga_id', $sma->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();

    expect($pendaftaranSmp->id)->not->toBe($pendaftaranSma->id);
    expect($pendaftaranSmp->calon_murid_id)->not->toBe($pendaftaranSma->calon_murid_id);
});

it('is idempotent when run twice', function () {
    (new PendaftaranSeeder())->run();
    (new PendaftaranSeeder())->run();

    expect(Pendaftaran::count())->toBe(8);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/CalonMuridSeederTest.php tests/Unit/PendaftaranSeederTest.php`
Expected: FAIL — neither class exists yet.

- [ ] **Step 3: Write `CalonMuridSeeder`**

```php
<?php
// database/seeders/CalonMuridSeeder.php

namespace Database\Seeders;

use App\Models\CalonMurid;
use App\Models\Lembaga;
use Illuminate\Database\Seeder;

class CalonMuridSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::whereIn('npsn', ['20223344', '20223355'])->get() as $lembaga) {
            $this->seedCalon($lembaga, 'Calon Menunggu Verifikasi', 'L');
            $this->seedCalon($lembaga, 'Calon Diterima', 'L');
            $this->seedCalon($lembaga, 'Calon Ditolak', 'L');
            $this->seedCalon($lembaga, 'Calon Cicilan Demo', 'P');
        }
    }

    private function seedCalon(Lembaga $lembaga, string $namaDasar, string $jenisKelamin): void
    {
        CalonMurid::firstOrCreate(
            ['nama_lengkap' => $namaDasar.' ('.$lembaga->nama.')'],
            [
                'yayasan_id' => $lembaga->yayasan_id,
                'nik' => (string) random_int(3200000000000000, 3299999999999999),
                'jenis_kelamin' => $jenisKelamin,
                'tempat_lahir' => 'Bandung',
                'tanggal_lahir' => now()->subYears(13)->toDateString(),
                'agama' => 'Islam',
            ]
        );
    }
}
```

- [ ] **Step 4: Write `PendaftaranSeeder`**

```php
<?php
// database/seeders/PendaftaranSeeder.php

namespace Database\Seeders;

use App\Models\CalonMurid;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Seeder;

class PendaftaranSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::whereIn('npsn', ['20223344', '20223355'])->get() as $lembaga) {
            $tahunAjaranAktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();

            if (! $tahunAjaranAktif) {
                continue;
            }

            $jalur = JalurPpdb::where('lembaga_id', $lembaga->id)
                ->where('tahun_ajaran_id', $tahunAjaranAktif->id)
                ->where('nama', 'Reguler')
                ->first();

            $gelombang = GelombangPpdb::where('lembaga_id', $lembaga->id)
                ->where('tahun_ajaran_id', $tahunAjaranAktif->id)
                ->where('tanggal_buka', '<=', now())
                ->where('tanggal_tutup', '>=', now())
                ->first();

            if (! $jalur || ! $gelombang) {
                continue;
            }

            $staf = User::where('lembaga_id', $lembaga->id)->first();

            $this->seedPendaftaran($lembaga, $tahunAjaranAktif, $jalur, $gelombang, 'Calon Menunggu Verifikasi', 'wali.menunggu@example.test', [
                'kode_pendaftaran' => 'REG-DEMO-'.$lembaga->id.'-'.random_int(10000, 99999),
                'submitted_at' => now()->subDays(random_int(1, 5)),
            ]);

            $this->seedPendaftaran($lembaga, $tahunAjaranAktif, $jalur, $gelombang, 'Calon Diterima', 'wali.diterima@example.test', [
                'kode_pendaftaran' => 'REG-DEMO-'.$lembaga->id.'-'.random_int(10000, 99999),
                'submitted_at' => now()->subDays(random_int(1, 5)),
                'status' => 'diterima',
                'catatan_keputusan' => 'Nilai dan kelengkapan dokumen memenuhi syarat.',
                'ditetapkan_oleh_user_id' => $staf?->id,
                'ditetapkan_pada' => now(),
            ]);

            $this->seedPendaftaran($lembaga, $tahunAjaranAktif, $jalur, $gelombang, 'Calon Ditolak', 'wali.ditolak@example.test', [
                'kode_pendaftaran' => 'REG-DEMO-'.$lembaga->id.'-'.random_int(10000, 99999),
                'submitted_at' => now()->subDays(random_int(1, 5)),
                'status' => 'ditolak',
                'catatan_keputusan' => 'Nilai belum memenuhi kriteria kelulusan minimum.',
                'ditetapkan_oleh_user_id' => $staf?->id,
                'ditetapkan_pada' => now(),
            ]);

            $this->seedPendaftaran($lembaga, $tahunAjaranAktif, $jalur, $gelombang, 'Calon Cicilan Demo', 'wali.cicilan-demo@example.test', [
                'kode_pendaftaran' => 'REG-PEMBAYARAN-DEMO-'.$lembaga->id,
                'submitted_at' => now()->subDays(2),
                'status' => 'diterima',
                'ditetapkan_pada' => now()->subDay(),
            ]);
        }
    }

    private function seedPendaftaran(
        Lembaga $lembaga,
        TahunAjaran $tahunAjaran,
        JalurPpdb $jalur,
        GelombangPpdb $gelombang,
        string $namaCalon,
        string $email,
        array $extra
    ): void {
        $calonMurid = CalonMurid::where('nama_lengkap', $namaCalon.' ('.$lembaga->nama.')')->first();

        if (! $calonMurid) {
            return;
        }

        Pendaftaran::firstOrCreate(
            ['email_pendaftaran' => $email, 'lembaga_id' => $lembaga->id],
            array_merge([
                'calon_murid_id' => $calonMurid->id,
                'tahun_ajaran_id' => $tahunAjaran->id,
                'jalur_ppdb_id' => $jalur->id,
                'gelombang_ppdb_id' => $gelombang->id,
            ], $extra)
        );
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/CalonMuridSeederTest.php tests/Unit/PendaftaranSeederTest.php`
Expected: PASS (5/5)

- [ ] **Step 6: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all pre-existing tests still pass (these files are additive only).

- [ ] **Step 7: Commit**

```bash
git add database/seeders/CalonMuridSeeder.php database/seeders/PendaftaranSeeder.php tests/Unit/CalonMuridSeederTest.php tests/Unit/PendaftaranSeederTest.php
git commit -m "feat: add CalonMuridSeeder and PendaftaranSeeder, splitting them out of M3DemoDataSeeder"
```

---

### Task 2: `DokumenPendaftaranSeeder` and `HasilSeleksiSeeder`

**Files:**
- Create: `database/seeders/DokumenPendaftaranSeeder.php`
- Create: `database/seeders/HasilSeleksiSeeder.php`
- Test: `tests/Unit/DokumenPendaftaranSeederTest.php`
- Test: `tests/Unit/HasilSeleksiSeederTest.php`

**Interfaces:**
- Consumes: `Pendaftaran` by `email_pendaftaran`+`lembaga_id` (Task 1), `JalurPpdb`'s `dokumenSyarat()` HasMany relation and `SeleksiPpdb` rows scoped to jalur+gelombang (sub-project 2), `User` staff by `lembaga_id` (sub-project 2).
- Produces: no later task depends on these — they're leaf evidence data attached to already-decided `Pendaftaran` rows.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Unit/DokumenPendaftaranSeederTest.php

use App\Models\DokumenPendaftaran;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\CalonMuridSeeder;
use Database\Seeders\DokumenPendaftaranSeeder;
use Database\Seeders\DokumenSyaratPpdbSeeder;
use Database\Seeders\EssentialUserSeeder;
use Database\Seeders\FormulirFieldSeeder;
use Database\Seeders\GelombangPpdbSeeder;
use Database\Seeders\JalurPpdbSeeder;
use Database\Seeders\JenisTesMasterSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\PendaftaranSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SemesterSeeder;
use Database\Seeders\SeleksiPpdbSeeder;
use Database\Seeders\TahunAjaranSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
    (new EssentialUserSeeder())->run();
    (new UserSeeder())->run();
    (new TahunAjaranSeeder())->run();
    (new SemesterSeeder())->run();
    (new JenisTesMasterSeeder())->run();
    (new GelombangPpdbSeeder())->run();
    (new JalurPpdbSeeder())->run();
    (new FormulirFieldSeeder())->run();
    (new DokumenSyaratPpdbSeeder())->run();
    (new SeleksiPpdbSeeder())->run();
    (new CalonMuridSeeder())->run();
    (new PendaftaranSeeder())->run();
});

it('seeds mixed-status dokumen for the menunggu-verifikasi pendaftaran and all-diterima for the diterima pendaftaran', function () {
    (new DokumenPendaftaranSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $jalur = JalurPpdb::where('lembaga_id', $smp->id)->where('nama', 'Reguler')
        ->whereHas('tahunAjaran', fn ($q) => $q->where('status_aktif', true))->first();
    $jumlahSyarat = $jalur->dokumenSyarat()->count();

    $menunggu = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.menunggu@example.test')->first();
    $dokumenMenunggu = DokumenPendaftaran::where('pendaftaran_id', $menunggu->id)->get();
    expect($dokumenMenunggu)->toHaveCount($jumlahSyarat);
    expect($dokumenMenunggu->pluck('status_verifikasi')->unique()->count())->toBeGreaterThan(1);

    $diterima = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
    $dokumenDiterima = DokumenPendaftaran::where('pendaftaran_id', $diterima->id)->get();
    expect($dokumenDiterima)->toHaveCount($jumlahSyarat);
    expect($dokumenDiterima->pluck('status_verifikasi')->unique()->all())->toBe(['diterima']);
});

it('is idempotent when run twice', function () {
    (new DokumenPendaftaranSeeder())->run();
    $sebelum = DokumenPendaftaran::count();
    (new DokumenPendaftaranSeeder())->run();

    expect(DokumenPendaftaran::count())->toBe($sebelum);
});
```

```php
<?php
// tests/Unit/HasilSeleksiSeederTest.php

use App\Models\HasilSeleksi;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\Yayasan;
use Database\Seeders\CalonMuridSeeder;
use Database\Seeders\DokumenSyaratPpdbSeeder;
use Database\Seeders\EssentialUserSeeder;
use Database\Seeders\FormulirFieldSeeder;
use Database\Seeders\GelombangPpdbSeeder;
use Database\Seeders\HasilSeleksiSeeder;
use Database\Seeders\JalurPpdbSeeder;
use Database\Seeders\JenisTesMasterSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\PendaftaranSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SemesterSeeder;
use Database\Seeders\SeleksiPpdbSeeder;
use Database\Seeders\TahunAjaranSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
    (new EssentialUserSeeder())->run();
    (new UserSeeder())->run();
    (new TahunAjaranSeeder())->run();
    (new SemesterSeeder())->run();
    (new JenisTesMasterSeeder())->run();
    (new GelombangPpdbSeeder())->run();
    (new JalurPpdbSeeder())->run();
    (new FormulirFieldSeeder())->run();
    (new DokumenSyaratPpdbSeeder())->run();
    (new SeleksiPpdbSeeder())->run();
    (new CalonMuridSeeder())->run();
    (new PendaftaranSeeder())->run();
});

it('seeds passing-range nilai for diterima and failing-range nilai for ditolak', function () {
    (new HasilSeleksiSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();

    $diterima = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
    $nilaiDiterima = HasilSeleksi::where('pendaftaran_id', $diterima->id)->get();
    expect($nilaiDiterima)->not->toBeEmpty();
    foreach ($nilaiDiterima as $hasil) {
        expect((float) $hasil->nilai)->toBeGreaterThanOrEqual(75)->toBeLessThanOrEqual(95);
    }

    $ditolak = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.ditolak@example.test')->first();
    $nilaiDitolak = HasilSeleksi::where('pendaftaran_id', $ditolak->id)->get();
    expect($nilaiDitolak)->not->toBeEmpty();
    foreach ($nilaiDitolak as $hasil) {
        expect((float) $hasil->nilai)->toBeGreaterThanOrEqual(30)->toBeLessThanOrEqual(55);
    }
});

it('is idempotent when run twice', function () {
    (new HasilSeleksiSeeder())->run();
    $sebelum = HasilSeleksi::count();
    (new HasilSeleksiSeeder())->run();

    expect(HasilSeleksi::count())->toBe($sebelum);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/DokumenPendaftaranSeederTest.php tests/Unit/HasilSeleksiSeederTest.php`
Expected: FAIL — neither class exists yet.

- [ ] **Step 3: Write `DokumenPendaftaranSeeder`**

```php
<?php
// database/seeders/DokumenPendaftaranSeeder.php

namespace Database\Seeders;

use App\Models\DokumenPendaftaran;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DokumenPendaftaranSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::whereIn('npsn', ['20223344', '20223355'])->get() as $lembaga) {
            $staf = User::where('lembaga_id', $lembaga->id)->first();

            $tahunAjaranAktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();

            if (! $tahunAjaranAktif) {
                continue;
            }

            $jalur = JalurPpdb::where('lembaga_id', $lembaga->id)
                ->where('tahun_ajaran_id', $tahunAjaranAktif->id)
                ->where('nama', 'Reguler')
                ->first();

            if (! $jalur) {
                continue;
            }

            $syaratDokumen = $jalur->dokumenSyarat;

            $this->seedDokumenMenunggu($lembaga, $syaratDokumen, $staf);
            $this->seedDokumenDiterima($lembaga, $syaratDokumen, $staf);
        }
    }

    private function seedDokumenMenunggu(Lembaga $lembaga, Collection $syaratDokumen, ?User $staf): void
    {
        $pendaftaran = Pendaftaran::where('lembaga_id', $lembaga->id)
            ->where('email_pendaftaran', 'wali.menunggu@example.test')
            ->first();

        if (! $pendaftaran) {
            return;
        }

        foreach ($syaratDokumen as $index => $syarat) {
            $status = match ($index % 3) {
                0 => 'diterima',
                1 => 'ditolak',
                default => 'belum_diverifikasi',
            };

            DokumenPendaftaran::firstOrCreate(
                ['pendaftaran_id' => $pendaftaran->id, 'dokumen_syarat_ppdb_id' => $syarat->id],
                [
                    'file_path' => 'demo/dokumen-contoh.pdf',
                    'nama_file_asli' => $syarat->nama_dokumen.'.pdf',
                    'mime_type' => 'application/pdf',
                    'ukuran_bytes' => 102400,
                    'status_verifikasi' => $status,
                    'catatan_verifikasi' => $status === 'ditolak' ? 'Contoh catatan: berkas kurang jelas, mohon diunggah ulang.' : null,
                    'diverifikasi_oleh_user_id' => $status !== 'belum_diverifikasi' ? $staf?->id : null,
                    'diverifikasi_pada' => $status !== 'belum_diverifikasi' ? now() : null,
                ]
            );
        }
    }

    private function seedDokumenDiterima(Lembaga $lembaga, Collection $syaratDokumen, ?User $staf): void
    {
        $pendaftaran = Pendaftaran::where('lembaga_id', $lembaga->id)
            ->where('email_pendaftaran', 'wali.diterima@example.test')
            ->first();

        if (! $pendaftaran) {
            return;
        }

        foreach ($syaratDokumen as $syarat) {
            DokumenPendaftaran::firstOrCreate(
                ['pendaftaran_id' => $pendaftaran->id, 'dokumen_syarat_ppdb_id' => $syarat->id],
                [
                    'file_path' => 'demo/dokumen-contoh.pdf',
                    'nama_file_asli' => $syarat->nama_dokumen.'.pdf',
                    'mime_type' => 'application/pdf',
                    'ukuran_bytes' => 102400,
                    'status_verifikasi' => 'diterima',
                    'diverifikasi_oleh_user_id' => $staf?->id,
                    'diverifikasi_pada' => now(),
                ]
            );
        }
    }
}
```

- [ ] **Step 4: Write `HasilSeleksiSeeder`**

```php
<?php
// database/seeders/HasilSeleksiSeeder.php

namespace Database\Seeders;

use App\Models\GelombangPpdb;
use App\Models\HasilSeleksi;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\SeleksiPpdb;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class HasilSeleksiSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::whereIn('npsn', ['20223344', '20223355'])->get() as $lembaga) {
            $staf = User::where('lembaga_id', $lembaga->id)->first();

            $tahunAjaranAktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();

            if (! $tahunAjaranAktif) {
                continue;
            }

            $jalur = JalurPpdb::where('lembaga_id', $lembaga->id)
                ->where('tahun_ajaran_id', $tahunAjaranAktif->id)
                ->where('nama', 'Reguler')
                ->first();

            $gelombang = GelombangPpdb::where('lembaga_id', $lembaga->id)
                ->where('tahun_ajaran_id', $tahunAjaranAktif->id)
                ->where('tanggal_buka', '<=', now())
                ->where('tanggal_tutup', '>=', now())
                ->first();

            if (! $jalur || ! $gelombang) {
                continue;
            }

            $seleksiList = SeleksiPpdb::where('jalur_ppdb_id', $jalur->id)->where('gelombang_ppdb_id', $gelombang->id)->get();

            $this->seedHasil($lembaga, $seleksiList, 'wali.diterima@example.test', 75, 95, $staf);
            $this->seedHasil($lembaga, $seleksiList, 'wali.ditolak@example.test', 30, 55, $staf);
        }
    }

    private function seedHasil(Lembaga $lembaga, Collection $seleksiList, string $email, int $min, int $max, ?User $staf): void
    {
        $pendaftaran = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', $email)->first();

        if (! $pendaftaran) {
            return;
        }

        foreach ($seleksiList as $seleksi) {
            HasilSeleksi::firstOrCreate(
                ['pendaftaran_id' => $pendaftaran->id, 'seleksi_ppdb_id' => $seleksi->id],
                [
                    'nilai' => random_int($min, $max),
                    'dinilai_oleh_user_id' => $staf?->id,
                    'dinilai_pada' => now(),
                ]
            );
        }
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/DokumenPendaftaranSeederTest.php tests/Unit/HasilSeleksiSeederTest.php`
Expected: PASS (4/4)

- [ ] **Step 6: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all pre-existing tests still pass.

- [ ] **Step 7: Commit**

```bash
git add database/seeders/DokumenPendaftaranSeeder.php database/seeders/HasilSeleksiSeeder.php tests/Unit/DokumenPendaftaranSeederTest.php tests/Unit/HasilSeleksiSeederTest.php
git commit -m "feat: add DokumenPendaftaranSeeder and HasilSeleksiSeeder, splitting them out of M3DemoDataSeeder"
```

---

### Task 3: `SkPpdbSeeder`

**Files:**
- Create: `database/seeders/SkPpdbSeeder.php`
- Test: `tests/Unit/SkPpdbSeederTest.php`

**Interfaces:**
- Consumes: `Pendaftaran` "Diterima"/"Ditolak" by `email_pendaftaran`+`lembaga_id` (Task 1), `GelombangPpdb` currently open (sub-project 2), `User` staff by `lembaga_id` (sub-project 2).
- Produces: 1 `SkPpdb` row per lembaga findable by `lembaga_id`+`nomor_sk`. Updates `pendaftaran.sk_ppdb_id` on both the "Diterima" and "Ditolak" rows for that lembaga — the one cross-table write in this task, mirroring the reverse-FK-update pattern already used for `Pendaftaran.akun_pendaftar_id` (Task 7) and matching the schema's own design (`sk_ppdb` doesn't know which pendaftaran it covers; `pendaftaran` points to it).

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/SkPpdbSeederTest.php

use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\SkPpdb;
use App\Models\Yayasan;
use Database\Seeders\CalonMuridSeeder;
use Database\Seeders\EssentialUserSeeder;
use Database\Seeders\GelombangPpdbSeeder;
use Database\Seeders\JalurPpdbSeeder;
use Database\Seeders\JenisTesMasterSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\PendaftaranSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SemesterSeeder;
use Database\Seeders\SkPpdbSeeder;
use Database\Seeders\TahunAjaranSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
    (new EssentialUserSeeder())->run();
    (new UserSeeder())->run();
    (new TahunAjaranSeeder())->run();
    (new SemesterSeeder())->run();
    (new JenisTesMasterSeeder())->run();
    (new GelombangPpdbSeeder())->run();
    (new JalurPpdbSeeder())->run();
    (new CalonMuridSeeder())->run();
    (new PendaftaranSeeder())->run();
});

it('creates one SK per lembaga and attaches it to both the diterima and ditolak pendaftaran of that lembaga', function () {
    (new SkPpdbSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $sma = Lembaga::where('npsn', '20223355')->first();

    $skSmp = SkPpdb::where('lembaga_id', $smp->id)->first();
    expect($skSmp)->not->toBeNull();

    $diterimaSmp = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
    $ditolakSmp = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.ditolak@example.test')->first();
    expect($diterimaSmp->sk_ppdb_id)->toBe($skSmp->id);
    expect($ditolakSmp->sk_ppdb_id)->toBe($skSmp->id);

    $skSma = SkPpdb::where('lembaga_id', $sma->id)->first();
    expect($skSma->id)->not->toBe($skSmp->id);
});

it('is idempotent when run twice', function () {
    (new SkPpdbSeeder())->run();
    (new SkPpdbSeeder())->run();

    expect(SkPpdb::count())->toBe(2);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/SkPpdbSeederTest.php`
Expected: FAIL — `Database\Seeders\SkPpdbSeeder` doesn't exist yet.

- [ ] **Step 3: Write `SkPpdbSeeder`**

```php
<?php
// database/seeders/SkPpdbSeeder.php

namespace Database\Seeders;

use App\Models\GelombangPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\SkPpdb;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Seeder;

class SkPpdbSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::whereIn('npsn', ['20223344', '20223355'])->get() as $lembaga) {
            $staf = User::where('lembaga_id', $lembaga->id)->first();

            if (! $staf) {
                continue;
            }

            $tahunAjaranAktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();

            if (! $tahunAjaranAktif) {
                continue;
            }

            $gelombang = GelombangPpdb::where('lembaga_id', $lembaga->id)
                ->where('tahun_ajaran_id', $tahunAjaranAktif->id)
                ->where('tanggal_buka', '<=', now())
                ->where('tanggal_tutup', '>=', now())
                ->first();

            if (! $gelombang) {
                continue;
            }

            $diterima = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
            $ditolak = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.ditolak@example.test')->first();

            if (! $diterima || ! $ditolak) {
                continue;
            }

            $sk = SkPpdb::firstOrCreate(
                ['lembaga_id' => $lembaga->id, 'nomor_sk' => '421.3/SK-PPDB.DEMO-'.$lembaga->id.'/2026'],
                [
                    'gelombang_ppdb_id' => $gelombang->id,
                    'tanggal_terbit' => now()->toDateString(),
                    'diterbitkan_oleh_user_id' => $staf->id,
                    'file_path' => 'demo/sk-contoh.pdf',
                ]
            );

            Pendaftaran::whereIn('id', [$diterima->id, $ditolak->id])->update(['sk_ppdb_id' => $sk->id]);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/SkPpdbSeederTest.php`
Expected: PASS (2/2)

- [ ] **Step 5: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all pre-existing tests still pass.

- [ ] **Step 6: Commit**

```bash
git add database/seeders/SkPpdbSeeder.php tests/Unit/SkPpdbSeederTest.php
git commit -m "feat: add SkPpdbSeeder, splitting it out of M3DemoDataSeeder"
```

---

### Task 4: `TagihanSeeder` and `TagihanItemSeeder`

**Files:**
- Create: `database/seeders/TagihanSeeder.php`
- Create: `database/seeders/TagihanItemSeeder.php`
- Test: `tests/Unit/TagihanSeederTest.php`
- Test: `tests/Unit/TagihanItemSeederTest.php`

**Interfaces:**
- Consumes: `Pendaftaran` "Diterima"/"Cicilan Demo" by `email_pendaftaran`+`lembaga_id` (Task 1), `JalurPpdb` named `'Reguler'` scoped to the active tahun ajaran (sub-project 2), `JenisTagihan` "Biaya Pendaftaran"/"Uang Pangkal" by `lembaga_id`+`nama` (sub-project 2), `NominalTagihanJalur` by `jenis_tagihan_id`+`jalur_ppdb_id` (sub-project 2).
- Produces: 6 `Tagihan` rows (2 per lembaga for "Diterima" — kategori `pendaftaran` and `daftar_ulang` — + 1 per lembaga for "Cicilan Demo" — kategori `daftar_ulang`), findable by `pendaftaran_id`+`kategori`. 6 `TagihanItem` rows, one per `Tagihan`, findable by `tagihan_id`+`jenis_tagihan_id`. Later tasks (`SkemaCicilanSeeder`, `PembayaranSeeder`) look up the "Cicilan Demo"/"Diterima" `Tagihan` rows the same way.
- **Correctness note:** `Tagihan.total_tagihan` is NOT hardcoded — it is read from the real `NominalTagihanJalur` value already configured per lembaga by sub-project 2's `NominalTagihanJalurSeeder` (SMP: 150000/3000000; SMA: 200000/4500000 — different amounts per lembaga). This is a deliberate improvement over the old `PembayaranDemoSeeder`, which hardcoded one nominal (150000/900000) that had already silently diverged from the real configured values.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Unit/TagihanSeederTest.php

use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\NominalTagihanJalur;
use App\Models\Pendaftaran;
use App\Models\Tagihan;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Database\Seeders\CalonMuridSeeder;
use Database\Seeders\EssentialUserSeeder;
use Database\Seeders\GelombangPpdbSeeder;
use Database\Seeders\JalurPpdbSeeder;
use Database\Seeders\JenisTagihanSeeder;
use Database\Seeders\JenisTesMasterSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\NominalTagihanJalurSeeder;
use Database\Seeders\PendaftaranSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SemesterSeeder;
use Database\Seeders\TagihanSeeder;
use Database\Seeders\TahunAjaranSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
    (new EssentialUserSeeder())->run();
    (new UserSeeder())->run();
    (new TahunAjaranSeeder())->run();
    (new SemesterSeeder())->run();
    (new JenisTesMasterSeeder())->run();
    (new GelombangPpdbSeeder())->run();
    (new JalurPpdbSeeder())->run();
    (new JenisTagihanSeeder())->run();
    (new NominalTagihanJalurSeeder())->run();
    (new CalonMuridSeeder())->run();
    (new PendaftaranSeeder())->run();
});

it('sets total_tagihan to the real configured NominalTagihanJalur value for each lembaga, not a hardcoded amount', function () {
    (new TagihanSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $sma = Lembaga::where('npsn', '20223355')->first();

    $aktifSmp = TahunAjaran::where('lembaga_id', $smp->id)->where('status_aktif', true)->first();
    $jalurSmp = JalurPpdb::where('lembaga_id', $smp->id)->where('tahun_ajaran_id', $aktifSmp->id)->where('nama', 'Reguler')->first();
    $jenisDaftarUlangSmp = JenisTagihan::where('lembaga_id', $smp->id)->where('nama', 'Uang Pangkal')->first();
    $nominalSmp = NominalTagihanJalur::where('jenis_tagihan_id', $jenisDaftarUlangSmp->id)->where('jalur_ppdb_id', $jalurSmp->id)->first();

    $diterimaSmp = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
    $tagihanDaftarUlangSmp = Tagihan::where('pendaftaran_id', $diterimaSmp->id)->where('kategori', 'daftar_ulang')->first();
    expect((int) $tagihanDaftarUlangSmp->total_tagihan)->toBe((int) $nominalSmp->nominal);

    $aktifSma = TahunAjaran::where('lembaga_id', $sma->id)->where('status_aktif', true)->first();
    $jalurSma = JalurPpdb::where('lembaga_id', $sma->id)->where('tahun_ajaran_id', $aktifSma->id)->where('nama', 'Reguler')->first();
    $jenisDaftarUlangSma = JenisTagihan::where('lembaga_id', $sma->id)->where('nama', 'Uang Pangkal')->first();
    $nominalSma = NominalTagihanJalur::where('jenis_tagihan_id', $jenisDaftarUlangSma->id)->where('jalur_ppdb_id', $jalurSma->id)->first();

    $diterimaSma = Pendaftaran::where('lembaga_id', $sma->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
    $tagihanDaftarUlangSma = Tagihan::where('pendaftaran_id', $diterimaSma->id)->where('kategori', 'daftar_ulang')->first();
    expect((int) $tagihanDaftarUlangSma->total_tagihan)->toBe((int) $nominalSma->nominal);

    expect((int) $tagihanDaftarUlangSmp->total_tagihan)->not->toBe((int) $tagihanDaftarUlangSma->total_tagihan);
});

it('creates 2 tagihan for the diterima candidate and 1 for the cicilan-demo candidate, per lembaga', function () {
    (new TagihanSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $diterima = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
    $cicilanDemo = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.cicilan-demo@example.test')->first();

    expect(Tagihan::where('pendaftaran_id', $diterima->id)->count())->toBe(2);
    expect(Tagihan::where('pendaftaran_id', $cicilanDemo->id)->count())->toBe(1);
    expect(Tagihan::where('pendaftaran_id', $cicilanDemo->id)->first()->kategori)->toBe('daftar_ulang');
});

it('is idempotent when run twice', function () {
    (new TagihanSeeder())->run();
    (new TagihanSeeder())->run();

    expect(Tagihan::count())->toBe(6);
});
```

```php
<?php
// tests/Unit/TagihanItemSeederTest.php

use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\Tagihan;
use App\Models\TagihanItem;
use App\Models\Yayasan;
use Database\Seeders\CalonMuridSeeder;
use Database\Seeders\EssentialUserSeeder;
use Database\Seeders\GelombangPpdbSeeder;
use Database\Seeders\JalurPpdbSeeder;
use Database\Seeders\JenisTagihanSeeder;
use Database\Seeders\JenisTesMasterSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\NominalTagihanJalurSeeder;
use Database\Seeders\PendaftaranSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SemesterSeeder;
use Database\Seeders\TagihanItemSeeder;
use Database\Seeders\TagihanSeeder;
use Database\Seeders\TahunAjaranSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
    (new EssentialUserSeeder())->run();
    (new UserSeeder())->run();
    (new TahunAjaranSeeder())->run();
    (new SemesterSeeder())->run();
    (new JenisTesMasterSeeder())->run();
    (new GelombangPpdbSeeder())->run();
    (new JalurPpdbSeeder())->run();
    (new JenisTagihanSeeder())->run();
    (new NominalTagihanJalurSeeder())->run();
    (new CalonMuridSeeder())->run();
    (new PendaftaranSeeder())->run();
    (new TagihanSeeder())->run();
});

it('creates exactly one item per tagihan, with jumlah matching total_tagihan', function () {
    (new TagihanItemSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $diterima = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();

    foreach (Tagihan::where('pendaftaran_id', $diterima->id)->get() as $tagihan) {
        $items = TagihanItem::where('tagihan_id', $tagihan->id)->get();
        expect($items)->toHaveCount(1);
        expect((int) $items->first()->jumlah)->toBe((int) $tagihan->total_tagihan);
    }
});

it('is idempotent when run twice', function () {
    (new TagihanItemSeeder())->run();
    (new TagihanItemSeeder())->run();

    expect(TagihanItem::count())->toBe(6);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/TagihanSeederTest.php tests/Unit/TagihanItemSeederTest.php`
Expected: FAIL — neither class exists yet.

- [ ] **Step 3: Write `TagihanSeeder`**

```php
<?php
// database/seeders/TagihanSeeder.php

namespace Database\Seeders;

use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\NominalTagihanJalur;
use App\Models\Pendaftaran;
use App\Models\Tagihan;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class TagihanSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::whereIn('npsn', ['20223344', '20223355'])->get() as $lembaga) {
            $tahunAjaranAktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();

            if (! $tahunAjaranAktif) {
                continue;
            }

            $jalur = JalurPpdb::where('lembaga_id', $lembaga->id)
                ->where('tahun_ajaran_id', $tahunAjaranAktif->id)
                ->where('nama', 'Reguler')
                ->first();

            if (! $jalur) {
                continue;
            }

            $jenisPendaftaran = JenisTagihan::where('lembaga_id', $lembaga->id)->where('nama', 'Biaya Pendaftaran')->first();
            $jenisDaftarUlang = JenisTagihan::where('lembaga_id', $lembaga->id)->where('nama', 'Uang Pangkal')->first();

            if (! $jenisPendaftaran || ! $jenisDaftarUlang) {
                continue;
            }

            $nominalPendaftaran = NominalTagihanJalur::where('jenis_tagihan_id', $jenisPendaftaran->id)->where('jalur_ppdb_id', $jalur->id)->first();
            $nominalDaftarUlang = NominalTagihanJalur::where('jenis_tagihan_id', $jenisDaftarUlang->id)->where('jalur_ppdb_id', $jalur->id)->first();

            if (! $nominalPendaftaran || ! $nominalDaftarUlang) {
                continue;
            }

            $diterima = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
            $cicilanDemo = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.cicilan-demo@example.test')->first();

            if ($diterima) {
                Tagihan::firstOrCreate(
                    ['pendaftaran_id' => $diterima->id, 'kategori' => 'pendaftaran'],
                    ['total_tagihan' => $nominalPendaftaran->nominal, 'status' => 'belum_bayar']
                );
                Tagihan::firstOrCreate(
                    ['pendaftaran_id' => $diterima->id, 'kategori' => 'daftar_ulang'],
                    ['total_tagihan' => $nominalDaftarUlang->nominal, 'status' => 'belum_bayar']
                );
            }

            if ($cicilanDemo) {
                Tagihan::firstOrCreate(
                    ['pendaftaran_id' => $cicilanDemo->id, 'kategori' => 'daftar_ulang'],
                    ['total_tagihan' => $nominalDaftarUlang->nominal, 'status' => 'belum_bayar']
                );
            }
        }
    }
}
```

- [ ] **Step 4: Write `TagihanItemSeeder`**

```php
<?php
// database/seeders/TagihanItemSeeder.php

namespace Database\Seeders;

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\Tagihan;
use App\Models\TagihanItem;
use Illuminate\Database\Seeder;

class TagihanItemSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::whereIn('npsn', ['20223344', '20223355'])->get() as $lembaga) {
            $jenisPendaftaran = JenisTagihan::where('lembaga_id', $lembaga->id)->where('nama', 'Biaya Pendaftaran')->first();
            $jenisDaftarUlang = JenisTagihan::where('lembaga_id', $lembaga->id)->where('nama', 'Uang Pangkal')->first();

            if (! $jenisPendaftaran || ! $jenisDaftarUlang) {
                continue;
            }

            $tagihanList = Tagihan::whereHas('pendaftaran', fn ($q) => $q->where('lembaga_id', $lembaga->id))->get();

            foreach ($tagihanList as $tagihan) {
                $jenisTagihan = $tagihan->kategori === 'pendaftaran' ? $jenisPendaftaran : $jenisDaftarUlang;

                TagihanItem::firstOrCreate(
                    ['tagihan_id' => $tagihan->id, 'jenis_tagihan_id' => $jenisTagihan->id],
                    ['jumlah' => $tagihan->total_tagihan]
                );
            }
        }
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/TagihanSeederTest.php tests/Unit/TagihanItemSeederTest.php`
Expected: PASS (5/5)

- [ ] **Step 6: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all pre-existing tests still pass.

- [ ] **Step 7: Commit**

```bash
git add database/seeders/TagihanSeeder.php database/seeders/TagihanItemSeeder.php tests/Unit/TagihanSeederTest.php tests/Unit/TagihanItemSeederTest.php
git commit -m "feat: add TagihanSeeder and TagihanItemSeeder, splitting them out of PembayaranDemoSeeder and extending to SMA"
```

---

### Task 5: `SkemaCicilanSeeder` and `CicilanSeeder`

**Files:**
- Create: `database/seeders/SkemaCicilanSeeder.php`
- Create: `database/seeders/CicilanSeeder.php`
- Test: `tests/Unit/SkemaCicilanSeederTest.php`
- Test: `tests/Unit/CicilanSeederTest.php`

**Interfaces:**
- Consumes: `Pendaftaran` "Cicilan Demo" by `email_pendaftaran`+`lembaga_id` (Task 1), `Tagihan` kategori `daftar_ulang` for that pendaftaran (Task 4), `App\Services\PembayaranService::buatSkemaCicilan(Tagihan $tagihan, int $jumlahTermin, string $dibuatOleh, ?int $dibuatOlehUserId = null): SkemaCicilan` (existing production service — creates the `SkemaCicilan` header row AND all `jumlahTermin` `Cicilan` rows in one DB transaction; throws `RuntimeException` if the tagihan already has a skema).
- Produces: 2 `SkemaCicilan` rows (one per lembaga), each with 3 `Cicilan` rows (6 total). `PembayaranSeeder` (Task 6) looks up the "Cicilan Demo" tagihan's `skemaCicilan->cicilan()->where('urutan', 1)` to attach a payment to termin 1.
- **Special case:** `CicilanSeeder` does not call `Cicilan::create()` — `SkemaCicilanSeeder`'s call to `PembayaranService::buatSkemaCicilan()` already creates every `Cicilan` row as part of its atomic transaction. `CicilanSeeder` exists as `cicilan`'s dedicated, documented owner in `DatabaseSeeder`'s call chain and verifies the expected rows are present, throwing loudly if they are not (see spec §2.3 — this exact split was explicitly approved).

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Unit/SkemaCicilanSeederTest.php

use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\SkemaCicilan;
use App\Models\Tagihan;
use App\Models\Yayasan;
use Database\Seeders\CalonMuridSeeder;
use Database\Seeders\EssentialUserSeeder;
use Database\Seeders\GelombangPpdbSeeder;
use Database\Seeders\JalurPpdbSeeder;
use Database\Seeders\JenisTagihanSeeder;
use Database\Seeders\JenisTesMasterSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\NominalTagihanJalurSeeder;
use Database\Seeders\PendaftaranSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SemesterSeeder;
use Database\Seeders\SkemaCicilanSeeder;
use Database\Seeders\TagihanSeeder;
use Database\Seeders\TahunAjaranSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
    (new EssentialUserSeeder())->run();
    (new UserSeeder())->run();
    (new TahunAjaranSeeder())->run();
    (new SemesterSeeder())->run();
    (new JenisTesMasterSeeder())->run();
    (new GelombangPpdbSeeder())->run();
    (new JalurPpdbSeeder())->run();
    (new JenisTagihanSeeder())->run();
    (new NominalTagihanJalurSeeder())->run();
    (new CalonMuridSeeder())->run();
    (new PendaftaranSeeder())->run();
    (new TagihanSeeder())->run();
});

it('creates a 3-termin skema cicilan per lembaga for the cicilan-demo tagihan', function () {
    (new SkemaCicilanSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $cicilanDemo = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.cicilan-demo@example.test')->first();
    $tagihan = Tagihan::where('pendaftaran_id', $cicilanDemo->id)->where('kategori', 'daftar_ulang')->first();

    $skema = SkemaCicilan::where('tagihan_id', $tagihan->id)->first();
    expect($skema)->not->toBeNull();
    expect($skema->jumlah_termin)->toBe(3);
    expect($tagihan->fresh()->status)->toBe('dicicil');
});

it('is idempotent when run twice', function () {
    (new SkemaCicilanSeeder())->run();
    (new SkemaCicilanSeeder())->run();

    expect(SkemaCicilan::count())->toBe(2);
});
```

```php
<?php
// tests/Unit/CicilanSeederTest.php

use App\Models\Cicilan;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\Tagihan;
use App\Models\Yayasan;
use Database\Seeders\CalonMuridSeeder;
use Database\Seeders\CicilanSeeder;
use Database\Seeders\EssentialUserSeeder;
use Database\Seeders\GelombangPpdbSeeder;
use Database\Seeders\JalurPpdbSeeder;
use Database\Seeders\JenisTagihanSeeder;
use Database\Seeders\JenisTesMasterSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\NominalTagihanJalurSeeder;
use Database\Seeders\PendaftaranSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SemesterSeeder;
use Database\Seeders\SkemaCicilanSeeder;
use Database\Seeders\TagihanSeeder;
use Database\Seeders\TahunAjaranSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
    (new EssentialUserSeeder())->run();
    (new UserSeeder())->run();
    (new TahunAjaranSeeder())->run();
    (new SemesterSeeder())->run();
    (new JenisTesMasterSeeder())->run();
    (new GelombangPpdbSeeder())->run();
    (new JalurPpdbSeeder())->run();
    (new JenisTagihanSeeder())->run();
    (new NominalTagihanJalurSeeder())->run();
    (new CalonMuridSeeder())->run();
    (new PendaftaranSeeder())->run();
    (new TagihanSeeder())->run();
});

it('does not error when the skema cicilan already has its 3 termin rows from SkemaCicilanSeeder', function () {
    (new SkemaCicilanSeeder())->run();

    (new CicilanSeeder())->run();

    expect(Cicilan::count())->toBe(6);

    $smp = Lembaga::where('npsn', '20223344')->first();
    $cicilanDemo = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.cicilan-demo@example.test')->first();
    $tagihan = Tagihan::where('pendaftaran_id', $cicilanDemo->id)->where('kategori', 'daftar_ulang')->first();
    $urutanList = $tagihan->skemaCicilan->cicilan()->orderBy('urutan')->pluck('urutan')->all();

    expect($urutanList)->toBe([1, 2, 3]);
});

it('throws if SkemaCicilanSeeder has not run yet (ordering invariant guard)', function () {
    (new CicilanSeeder())->run();
})->throws(RuntimeException::class);
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/SkemaCicilanSeederTest.php tests/Unit/CicilanSeederTest.php`
Expected: FAIL — neither class exists yet.

- [ ] **Step 3: Write `SkemaCicilanSeeder`**

```php
<?php
// database/seeders/SkemaCicilanSeeder.php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\Tagihan;
use App\Services\PembayaranService;
use Illuminate\Database\Seeder;

class SkemaCicilanSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(PembayaranService::class);

        foreach (Lembaga::whereIn('npsn', ['20223344', '20223355'])->get() as $lembaga) {
            $cicilanDemo = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.cicilan-demo@example.test')->first();

            if (! $cicilanDemo) {
                continue;
            }

            $tagihan = Tagihan::where('pendaftaran_id', $cicilanDemo->id)->where('kategori', 'daftar_ulang')->first();

            if (! $tagihan || $tagihan->skemaCicilan()->exists()) {
                continue;
            }

            $service->buatSkemaCicilan($tagihan, 3, 'calon_siswa');
        }
    }
}
```

- [ ] **Step 4: Write `CicilanSeeder`**

```php
<?php
// database/seeders/CicilanSeeder.php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\Tagihan;
use Illuminate\Database\Seeder;
use RuntimeException;

class CicilanSeeder extends Seeder
{
    /**
     * SkemaCicilanSeeder already creates every Cicilan row as a side effect of
     * PembayaranService::buatSkemaCicilan() (one atomic transaction covering both
     * tables). This seeder exists to give the `cicilan` table its own explicit,
     * documented owner in DatabaseSeeder's call chain, and to fail loudly if that
     * invariant is ever broken (e.g. someone reorders DatabaseSeeder and puts this
     * before SkemaCicilanSeeder).
     */
    public function run(): void
    {
        foreach (Lembaga::whereIn('npsn', ['20223344', '20223355'])->get() as $lembaga) {
            $cicilanDemo = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.cicilan-demo@example.test')->first();

            if (! $cicilanDemo) {
                continue;
            }

            $tagihan = Tagihan::where('pendaftaran_id', $cicilanDemo->id)->where('kategori', 'daftar_ulang')->first();

            if (! $tagihan) {
                continue;
            }

            $skema = $tagihan->skemaCicilan;

            if (! $skema || $skema->cicilan()->count() !== 3) {
                throw new RuntimeException(
                    "CicilanSeeder: skema cicilan untuk tagihan #{$tagihan->id} (lembaga {$lembaga->nama}) belum lengkap -- pastikan SkemaCicilanSeeder berjalan sebelum CicilanSeeder di DatabaseSeeder."
                );
            }
        }
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/SkemaCicilanSeederTest.php tests/Unit/CicilanSeederTest.php`
Expected: PASS (4/4)

- [ ] **Step 6: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all pre-existing tests still pass.

- [ ] **Step 7: Commit**

```bash
git add database/seeders/SkemaCicilanSeeder.php database/seeders/CicilanSeeder.php tests/Unit/SkemaCicilanSeederTest.php tests/Unit/CicilanSeederTest.php
git commit -m "feat: add SkemaCicilanSeeder and CicilanSeeder, splitting them out of PembayaranDemoSeeder and extending to SMA"
```

---

### Task 6: `PembayaranSeeder`

**Files:**
- Create: `database/seeders/PembayaranSeeder.php`
- Test: `tests/Unit/PembayaranSeederTest.php`

**Interfaces:**
- Consumes: `Pendaftaran` "Diterima"/"Cicilan Demo" by `email_pendaftaran`+`lembaga_id` (Task 1), `Tagihan` by `pendaftaran_id`+`kategori` (Task 4), `Cicilan` termin 1 via `$tagihan->skemaCicilan->cicilan()->where('urutan', 1)` (Task 5).
- Produces: 6 `Pembayaran` rows (2 per lembaga attached to "Diterima"'s 2 tagihan via `tagihan_id`, 1 per lembaga attached to "Cicilan Demo"'s termin-1 `Cicilan` via `cicilan_id`). No later task depends on this — it's the final leaf of the payment chain.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/PembayaranSeederTest.php

use App\Models\Lembaga;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\Tagihan;
use App\Models\TagihanItem;
use App\Models\Yayasan;
use Database\Seeders\CalonMuridSeeder;
use Database\Seeders\CicilanSeeder;
use Database\Seeders\EssentialUserSeeder;
use Database\Seeders\GelombangPpdbSeeder;
use Database\Seeders\JalurPpdbSeeder;
use Database\Seeders\JenisTagihanSeeder;
use Database\Seeders\JenisTesMasterSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\NominalTagihanJalurSeeder;
use Database\Seeders\PembayaranSeeder;
use Database\Seeders\PendaftaranSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SemesterSeeder;
use Database\Seeders\SkemaCicilanSeeder;
use Database\Seeders\TagihanItemSeeder;
use Database\Seeders\TagihanSeeder;
use Database\Seeders\TahunAjaranSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
    (new EssentialUserSeeder())->run();
    (new UserSeeder())->run();
    (new TahunAjaranSeeder())->run();
    (new SemesterSeeder())->run();
    (new JenisTesMasterSeeder())->run();
    (new GelombangPpdbSeeder())->run();
    (new JalurPpdbSeeder())->run();
    (new JenisTagihanSeeder())->run();
    (new NominalTagihanJalurSeeder())->run();
    (new CalonMuridSeeder())->run();
    (new PendaftaranSeeder())->run();
    (new TagihanSeeder())->run();
    (new TagihanItemSeeder())->run();
    (new SkemaCicilanSeeder())->run();
    (new CicilanSeeder())->run();
});

it('creates a pending payment for each of the diterima candidate 2 tagihan, per lembaga', function () {
    (new PembayaranSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $diterima = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();

    foreach (Tagihan::where('pendaftaran_id', $diterima->id)->get() as $tagihan) {
        $pembayaran = Pembayaran::where('tagihan_id', $tagihan->id)->first();
        expect($pembayaran)->not->toBeNull();
        expect($pembayaran->status)->toBe('menunggu_verifikasi');
        expect($pembayaran->sumber)->toBe('calon_siswa');
    }
});

it('creates a pending payment for termin 1 of the cicilan-demo candidate, per lembaga', function () {
    (new PembayaranSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $cicilanDemo = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.cicilan-demo@example.test')->first();
    $tagihan = Tagihan::where('pendaftaran_id', $cicilanDemo->id)->where('kategori', 'daftar_ulang')->first();
    $termin1 = $tagihan->skemaCicilan->cicilan()->where('urutan', 1)->first();

    $pembayaran = Pembayaran::where('cicilan_id', $termin1->id)->first();
    expect($pembayaran)->not->toBeNull();
    expect($pembayaran->status)->toBe('menunggu_verifikasi');
});

it('traces the full chain from a diterima pendaftaran through to its pembayaran, for both lembaga without cross-lembaga mixups', function () {
    (new PembayaranSeeder())->run();

    foreach (['20223344' => 'SMP', '20223355' => 'SMA'] as $npsn => $label) {
        $lembaga = Lembaga::where('npsn', $npsn)->first();
        $diterima = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();

        expect($diterima->calonMurid->nama_lengkap)->toBe('Calon Diterima ('.$lembaga->nama.')');
        expect($diterima->lembaga_id)->toBe($lembaga->id);
        expect($diterima->skPpdb->lembaga_id)->toBe($lembaga->id);

        $tagihanDaftarUlang = Tagihan::where('pendaftaran_id', $diterima->id)->where('kategori', 'daftar_ulang')->first();
        expect($tagihanDaftarUlang)->not->toBeNull();

        $item = TagihanItem::where('tagihan_id', $tagihanDaftarUlang->id)->first();
        expect($item->jenisTagihan->lembaga_id)->toBe($lembaga->id);
        expect((int) $item->jumlah)->toBe((int) $tagihanDaftarUlang->total_tagihan);

        $pembayaran = Pembayaran::where('tagihan_id', $tagihanDaftarUlang->id)->first();
        expect($pembayaran->tagihan->pendaftaran->id)->toBe($diterima->id);
    }

    $smp = Lembaga::where('npsn', '20223344')->first();
    $sma = Lembaga::where('npsn', '20223355')->first();
    $diterimaSmp = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
    $diterimaSma = Pendaftaran::where('lembaga_id', $sma->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();

    expect($diterimaSmp->sk_ppdb_id)->not->toBe($diterimaSma->sk_ppdb_id);
    expect($diterimaSmp->calon_murid_id)->not->toBe($diterimaSma->calon_murid_id);
});

it('is idempotent when run twice', function () {
    (new PembayaranSeeder())->run();
    (new PembayaranSeeder())->run();

    expect(Pembayaran::count())->toBe(6);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/PembayaranSeederTest.php`
Expected: FAIL — `Database\Seeders\PembayaranSeeder` doesn't exist yet.

- [ ] **Step 3: Write `PembayaranSeeder`**

```php
<?php
// database/seeders/PembayaranSeeder.php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\Tagihan;
use Illuminate\Database\Seeder;

class PembayaranSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::whereIn('npsn', ['20223344', '20223355'])->get() as $lembaga) {
            $diterima = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
            $cicilanDemo = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.cicilan-demo@example.test')->first();

            if ($diterima) {
                foreach (['pendaftaran', 'daftar_ulang'] as $kategori) {
                    $tagihan = Tagihan::where('pendaftaran_id', $diterima->id)->where('kategori', $kategori)->first();

                    if ($tagihan && ! Pembayaran::where('tagihan_id', $tagihan->id)->exists()) {
                        Pembayaran::create([
                            'tagihan_id' => $tagihan->id,
                            'sumber' => 'calon_siswa',
                            'metode' => 'transfer_manual',
                            'file_path' => 'demo/bukti-contoh.pdf',
                            'status' => 'menunggu_verifikasi',
                        ]);
                    }
                }
            }

            if ($cicilanDemo) {
                $tagihan = Tagihan::where('pendaftaran_id', $cicilanDemo->id)->where('kategori', 'daftar_ulang')->first();
                $termin1 = $tagihan?->skemaCicilan?->cicilan()->where('urutan', 1)->first();

                if ($termin1 && ! Pembayaran::where('cicilan_id', $termin1->id)->exists()) {
                    Pembayaran::create([
                        'cicilan_id' => $termin1->id,
                        'sumber' => 'calon_siswa',
                        'metode' => 'transfer_manual',
                        'file_path' => 'demo/bukti-contoh.pdf',
                        'status' => 'menunggu_verifikasi',
                    ]);
                }
            }
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/PembayaranSeederTest.php`
Expected: PASS (3/3)

- [ ] **Step 5: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all pre-existing tests still pass.

- [ ] **Step 6: Commit**

```bash
git add database/seeders/PembayaranSeeder.php tests/Unit/PembayaranSeederTest.php
git commit -m "feat: add PembayaranSeeder, splitting it out of PembayaranDemoSeeder and extending to SMA"
```

---

### Task 7: `AkunPendaftarSeeder`

**Files:**
- Modify: `app/Models/AkunPendaftar.php`
- Create: `database/seeders/AkunPendaftarSeeder.php`
- Test: `tests/Unit/AkunPendaftarSeederTest.php`

**Interfaces:**
- Consumes: `Lembaga` by npsn (sub-project 2), `Pendaftaran` "Diterima" by `email_pendaftaran`+`lembaga_id` (Task 1).
- Produces: 2 `AkunPendaftar` rows (`pendaftar.smp@example.test`, `pendaftar.sma@example.test`), each attached to that lembaga's "Diterima" `Pendaftaran` via `akun_pendaftar_id`. No later task depends on this — it's the entry point for manually testing the portal login flow.

- [ ] **Step 1: Fix `email_verified_at` mass-assignment (pre-existing gap, same pattern as `User::$fillable` fixed in sub-project 2)**

In `app/Models/AkunPendaftar.php`, change:

```php
    protected $fillable = [
        'nama',
        'email',
        'password',
    ];
```

to:

```php
    protected $fillable = [
        'nama',
        'email',
        'password',
        'email_verified_at',
    ];
```

- [ ] **Step 2: Write the failing test**

```php
<?php
// tests/Unit/AkunPendaftarSeederTest.php

use App\Models\AkunPendaftar;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\Yayasan;
use Database\Seeders\AkunPendaftarSeeder;
use Database\Seeders\CalonMuridSeeder;
use Database\Seeders\EssentialUserSeeder;
use Database\Seeders\GelombangPpdbSeeder;
use Database\Seeders\JalurPpdbSeeder;
use Database\Seeders\JenisTesMasterSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\PendaftaranSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SemesterSeeder;
use Database\Seeders\TahunAjaranSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
    (new EssentialUserSeeder())->run();
    (new UserSeeder())->run();
    (new TahunAjaranSeeder())->run();
    (new SemesterSeeder())->run();
    (new JenisTesMasterSeeder())->run();
    (new GelombangPpdbSeeder())->run();
    (new JalurPpdbSeeder())->run();
    (new CalonMuridSeeder())->run();
    (new PendaftaranSeeder())->run();
});

it('seeds one verified akun pendaftar per lembaga, attached to that lembaga diterima pendaftaran', function () {
    (new AkunPendaftarSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $akunSmp = AkunPendaftar::where('email', 'pendaftar.smp@example.test')->first();

    expect($akunSmp)->not->toBeNull();
    expect($akunSmp->email_verified_at)->not->toBeNull();
    expect(Hash::check('password', $akunSmp->password))->toBeTrue();

    $diterimaSmp = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
    expect($diterimaSmp->fresh()->akun_pendaftar_id)->toBe($akunSmp->id);
    expect($akunSmp->pendaftaran()->count())->toBe(1);

    $sma = Lembaga::where('npsn', '20223355')->first();
    $akunSma = AkunPendaftar::where('email', 'pendaftar.sma@example.test')->first();
    expect($akunSma->id)->not->toBe($akunSmp->id);
});

it('is idempotent when run twice', function () {
    (new AkunPendaftarSeeder())->run();
    (new AkunPendaftarSeeder())->run();

    expect(AkunPendaftar::count())->toBe(2);
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/AkunPendaftarSeederTest.php`
Expected: FAIL — `Database\Seeders\AkunPendaftarSeeder` doesn't exist yet.

- [ ] **Step 4: Write `AkunPendaftarSeeder`**

```php
<?php
// database/seeders/AkunPendaftarSeeder.php

namespace Database\Seeders;

use App\Models\AkunPendaftar;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use Illuminate\Database\Seeder;

class AkunPendaftarSeeder extends Seeder
{
    public function run(): void
    {
        $emailPerNpsn = [
            '20223344' => ['email' => 'pendaftar.smp@example.test', 'nama' => 'Wali SMP (Contoh)'],
            '20223355' => ['email' => 'pendaftar.sma@example.test', 'nama' => 'Wali SMA (Contoh)'],
        ];

        foreach (Lembaga::whereIn('npsn', array_keys($emailPerNpsn))->get() as $lembaga) {
            $data = $emailPerNpsn[$lembaga->npsn];

            $akun = AkunPendaftar::firstOrCreate(
                ['email' => $data['email']],
                [
                    'nama' => $data['nama'],
                    'password' => 'password',
                    'email_verified_at' => now(),
                ]
            );

            $diterima = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();

            if ($diterima) {
                $diterima->update(['akun_pendaftar_id' => $akun->id]);
            }
        }
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/AkunPendaftarSeederTest.php`
Expected: PASS (2/2)

- [ ] **Step 6: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all pre-existing tests still pass. The `AkunPendaftar::$fillable` change is additive, so no existing `AkunPendaftar::create()`/`firstOrCreate()` call elsewhere should be affected.

- [ ] **Step 7: Commit**

```bash
git add app/Models/AkunPendaftar.php database/seeders/AkunPendaftarSeeder.php tests/Unit/AkunPendaftarSeederTest.php
git commit -m "feat: add AkunPendaftarSeeder, giving the portal akun pendaftar feature its first demo data"
```

---

### Task 8: Wire `DatabaseSeeder`, Delete `M3DemoDataSeeder` and `PembayaranDemoSeeder`

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`
- Delete: `database/seeders/M3DemoDataSeeder.php`
- Delete: `database/seeders/PembayaranDemoSeeder.php`

**Interfaces:**
- Consumes: all 11 seeders from Tasks 1-7, plus everything already registered from sub-projects 1 and 2.
- Produces: a fully wired `DatabaseSeeder::run()` — the final integration point of the entire seeder-architecture-cleanup initiative (all 3 sub-projects).

- [ ] **Step 1: Confirm no test references `M3DemoDataSeeder` or `PembayaranDemoSeeder` directly**

Run (from the project root, using Git Bash or any POSIX-compatible shell available in this environment):
```
grep -rl "M3DemoDataSeeder\|PembayaranDemoSeeder" --include="*.php" . --exclude-dir=vendor --exclude-dir=node_modules
```
Expected output: only `database/seeders/DatabaseSeeder.php`, `database/seeders/M3DemoDataSeeder.php`, and `database/seeders/PembayaranDemoSeeder.php` themselves. If any test file appears in this list, STOP and report BLOCKED — investigate what that test needs before deleting either file (do not assume the same reasoning as sub-project 2 automatically applies; verify fresh).

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
            CalonMuridSeeder::class,
            PendaftaranSeeder::class,
            DokumenPendaftaranSeeder::class,
            HasilSeleksiSeeder::class,
            SkPpdbSeeder::class,
            TagihanSeeder::class,
            TagihanItemSeeder::class,
            SkemaCicilanSeeder::class,
            CicilanSeeder::class,
            PembayaranSeeder::class,
            AkunPendaftarSeeder::class,
        ]);
    }
}
```

- [ ] **Step 3: Delete `database/seeders/M3DemoDataSeeder.php` and `database/seeders/PembayaranDemoSeeder.php`**

```bash
rm database/seeders/M3DemoDataSeeder.php database/seeders/PembayaranDemoSeeder.php
```

- [ ] **Step 4: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all tests pass. This task adds no new automated tests of its own — it's pure integration — but it must not break any of the 11 new seeders' own tests, or any other test that seeds via `DatabaseSeeder`.

- [ ] **Step 5: Run a real `migrate:fresh --seed` to confirm the full chain works end-to-end**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan migrate:fresh --seed`
Expected: completes with no errors. This exercises the real configured database, confirming the complete 34-seeder chain (all 3 sub-projects combined) resolves in the correct order — most importantly here, that `SkemaCicilanSeeder` genuinely runs before `CicilanSeeder` (otherwise `CicilanSeeder`'s `RuntimeException` guard will fire and the whole command will fail loudly, which is the intended fail-safe behavior if the order is ever wrong).

- [ ] **Step 6: Commit**

```bash
git add database/seeders/DatabaseSeeder.php
git rm database/seeders/M3DemoDataSeeder.php database/seeders/PembayaranDemoSeeder.php
git commit -m "refactor: wire the 11 new transactional/scenario seeders into DatabaseSeeder, remove M3DemoDataSeeder and PembayaranDemoSeeder"
```

---

## Post-Plan Note

After Task 8, neither `M3DemoDataSeeder.php` nor `PembayaranDemoSeeder.php` exist anymore — their entire content lives in 11 focused, single-table (or single-table-equivalent, for the `SkemaCicilan`/`Cicilan` service-coupled pair) seeders, extended from SMP-only to both demo lembaga, and fully registered in `DatabaseSeeder` for the first time. This closes sub-project 3 of 3 — the final piece of the seeder-architecture-cleanup initiative. After this plan is executed and merged, `database/seeders/` contains no monolithic `*DemoDataSeeder.php` files: every table has exactly one seeder, and a single `migrate:fresh --seed` produces a complete, relationally-consistent demo dataset across RBAC, master/reference data, and transactional/scenario data — ready for manual testing of every feature without additional setup.
