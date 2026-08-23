# Kuota/Saldo Cuti Tahunan — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menegakkan batas jatah Cuti tahunan per pegawai di alur pengajuan izin/cuti yang sudah ada, tanpa tabel saldo/ledger — sisa kuota dihitung ulang dari data pengajuan yang sudah ada.

**Architecture:** 1 tabel config baru (`kuota_cuti_config`, pola identik `attendance_policies`) + 1 service resolver baru (`KuotaCutiResolver`, hitung sisa kuota on-the-fly) yang dikonsumsi oleh `AjukanIzinCutiAction` (validasi sebelum membuat pengajuan, dibungkus `Cache::lock()` untuk mencegah race condition) dan oleh UI self-service (tampilkan sisa kuota).

**Tech Stack:** Laravel 12, Pest, Alpine.js, MySQL (prod/dev), SQLite/array cache (test).

## Global Constraints

- Kuota **hanya berlaku untuk kategori `Cuti`**. `Izin` dan `Sakit` TIDAK PERNAH kena validasi kuota apapun.
- **TIDAK ADA tabel saldo/ledger.** Sisa kuota = `jatah − SUM(hari)` dari `PengajuanIzinCuti` kategori Cuti tahun berjalan berstatus Pending/InReview/Approved (Cancelled/Rejected tidak dihitung).
- Kalau lembaga/yayasan **belum punya config kuota sama sekali** (`jatahTahunan() === 0`), validasi kuota **TIDAK ditegakkan sama sekali** — regresi-aman untuk lembaga yang belum setting.
- Pengajuan Cuti dengan `tanggal_mulai` dan `tanggal_selesai` di **tahun kalender berbeda** → DITOLAK (`ValidationException`). Ini KHUSUS kategori Cuti — Izin/Sakit lintas tahun tetap diizinkan seperti sekarang, tidak tersentuh sama sekali.
- Concurrency **WAJIB** ditangani via `Cache::lock('kuota-cuti:{pegawai_type}:{pegawai_id}:{tahun}', 10)->block(5, ...)` mengelilingi cek-sisa + buat-pengajuan, HANYA saat kategori Cuti dan ada config aktif. `CACHE_STORE` environment ini adalah `database` — cek `.env` kalau ragu, JANGAN asumsikan.
- `App\Domains\Workflow` (Model/Service/Action approval generik) — TIDAK disentuh sama sekali.
- `App\Domains\Sdm\Actions\ProsesApprovalIzinCutiAction` — TIDAK diubah.
- `KalenderKerjaSdmResolver`, `AttendancePolicyResolver`, `ShiftAwareAttendanceResolver` — TIDAK disentuh.
- Baseline kode yang dikutip plan ini: commit `210c673` di branch `sdm-v1`.
- **Catatan desain penting** (WAJIB dipahami sebelum Task 2): `KuotaCutiResolver` query-nya SENGAJA berbeda bentuk dari `AttendancePolicyResolver` yang sudah ada. `AttendancePolicyResolver` MEWAJIBKAN tiap baris config match persis `jenis_ptk`/`jenis_karyawan_id` pegawai (tidak ada baris "berlaku untuk semua"). `KuotaCutiResolver` butuh baris "flat/catch-all" (kedua kolom itu NULL = berlaku semua pegawai) KARENA itu memang kebutuhan MVP ini — jangan "diperbaiki" supaya sama persis `AttendancePolicyResolver`, itu bukan bug, itu keputusan desain yang disengaja (lihat Task 2 untuk resolusi 4-tingkat lengkapnya).

---

## Task 1: Migration + Model `KuotaCutiConfig`

**Files:**
- Create: `database/migrations/2026_08_23_100000_create_kuota_cuti_config_table.php`
- Create: `app/Domains/Sdm/Models/KuotaCutiConfig.php`
- Test: `tests/Feature/Sdm/KuotaCutiConfigTest.php`

**Interfaces:**
- Produces: model `App\Domains\Sdm\Models\KuotaCutiConfig` dengan `$fillable = ['yayasan_id', 'lembaga_id', 'jenis_ptk', 'jenis_karyawan_id', 'jatah_hari_per_tahun']`, dipakai Task 2 (`KuotaCutiResolver`) dan Task 4 (controller admin).

- [ ] **Step 1: Buat migration**

Buat file `database/migrations/2026_08_23_100000_create_kuota_cuti_config_table.php`:

```php
<?php
// database/migrations/2026_08_23_100000_create_kuota_cuti_config_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kuota_cuti_config', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yayasan_id')->constrained('yayasan')->cascadeOnDelete();
            $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->cascadeOnDelete();
            $table->string('jenis_ptk')->nullable();
            $table->foreignId('jenis_karyawan_id')->nullable()->constrained('jenis_karyawan_master')->cascadeOnDelete();
            $table->unsignedInteger('jatah_hari_per_tahun');
            $table->timestamps();

            $table->unique(['yayasan_id', 'lembaga_id', 'jenis_ptk', 'jenis_karyawan_id'], 'kuota_cuti_config_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kuota_cuti_config');
    }
};
```

- [ ] **Step 2: Jalankan migration**

```bash
php artisan migrate
```

Expected: `2026_08_23_100000_create_kuota_cuti_config_table ... DONE`.

- [ ] **Step 3: Buat model**

Buat file `app/Domains/Sdm/Models/KuotaCutiConfig.php`:

```php
<?php

namespace App\Domains\Sdm\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\JenisKaryawanMaster;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KuotaCutiConfig extends Model
{
    use BelongsToTenant;

    protected $table = 'kuota_cuti_config';

    protected $fillable = [
        'yayasan_id', 'lembaga_id', 'jenis_ptk', 'jenis_karyawan_id', 'jatah_hari_per_tahun',
    ];

    protected function casts(): array
    {
        return [
            'jatah_hari_per_tahun' => 'integer',
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

    public function jenisKaryawan(): BelongsTo
    {
        return $this->belongsTo(JenisKaryawanMaster::class, 'jenis_karyawan_id');
    }
}
```

- [ ] **Step 4: Tulis test skema**

Buat file `tests/Feature/Sdm/KuotaCutiConfigTest.php`:

```php
<?php
// tests/Feature/Sdm/KuotaCutiConfigTest.php

use App\Domains\Sdm\Models\KuotaCutiConfig;
use App\Models\Lembaga;
use App\Models\Yayasan;

it('creates a flat kuota cuti config row for a lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $config = KuotaCutiConfig::create([
        'yayasan_id' => $yayasan->id,
        'lembaga_id' => $lembaga->id,
        'jenis_ptk' => null,
        'jenis_karyawan_id' => null,
        'jatah_hari_per_tahun' => 12,
    ]);

    expect($config->jatah_hari_per_tahun)->toBe(12);
    expect(KuotaCutiConfig::find($config->id)->lembaga_id)->toBe($lembaga->id);
});

it('rejects a duplicate config row for the exact same scope', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    KuotaCutiConfig::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jatah_hari_per_tahun' => 12]);

    expect(fn () => KuotaCutiConfig::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jatah_hari_per_tahun' => 15]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 5: Jalankan test**

```bash
php artisan test tests/Feature/Sdm/KuotaCutiConfigTest.php
```

Expected: **PASS** (2 passed).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_23_100000_create_kuota_cuti_config_table.php app/Domains/Sdm/Models/KuotaCutiConfig.php tests/Feature/Sdm/KuotaCutiConfigTest.php
git commit -m "feat(sdm): tambah tabel & model KuotaCutiConfig"
```

---

## Task 2: `KuotaCutiResolver` service

**Files:**
- Create: `app/Domains/Sdm/Services/KuotaCutiResolver.php`
- Test: `tests/Feature/Sdm/KuotaCutiResolverTest.php`

**Interfaces:**
- Consumes: `App\Domains\Sdm\Models\KuotaCutiConfig` (Task 1), `App\Domains\Sdm\Models\PengajuanIzinCuti` (sudah ada, tidak diubah), `App\Domains\Workflow\Enums\ApprovalStatus` (sudah ada, tidak diubah).
- Produces: `KuotaCutiResolver::jatahTahunan(Model $pegawai): int` dan `KuotaCutiResolver::sisaKuota(Model $pegawai, int $tahun): int` — dipakai Task 3 (`AjukanIzinCutiAction`) dan Task 5 (self-service UI).

- [ ] **Step 1: Tulis test yang gagal dulu**

Buat file `tests/Feature/Sdm/KuotaCutiResolverTest.php`:

```php
<?php
// tests/Feature/Sdm/KuotaCutiResolverTest.php

use App\Domains\Sdm\Actions\AjukanIzinCutiAction;
use App\Domains\Sdm\Enums\KategoriPengajuanIzin;
use App\Domains\Sdm\Models\KuotaCutiConfig;
use App\Domains\Sdm\Services\KuotaCutiResolver;
use App\Domains\Workflow\Actions\ProcessApprovalAction;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Support\Facades\Artisan;

function seedKuotaCutiWorkflowForTest(): void
{
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\WorkflowDefinitionSeeder']);
}

it('returns 0 jatah when there is no config at all', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    expect(app(KuotaCutiResolver::class)->jatahTahunan($guru))->toBe(0);
});

it('resolves the lembaga-level flat config over nasional when both exist', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    KuotaCutiConfig::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null, 'jatah_hari_per_tahun' => 10]);
    KuotaCutiConfig::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jatah_hari_per_tahun' => 12]);

    expect(app(KuotaCutiResolver::class)->jatahTahunan($guru))->toBe(12);
});

it('falls back to nasional flat config when there is no lembaga-level config', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    KuotaCutiConfig::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null, 'jatah_hari_per_tahun' => 10]);

    expect(app(KuotaCutiResolver::class)->jatahTahunan($guru))->toBe(10);
});

it('only counts Cuti pengajuan with Pending/InReview/Approved status in the given year', function () {
    seedKuotaCutiWorkflowForTest();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    KuotaCutiConfig::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jatah_hari_per_tahun' => 12]);
    $kepsekRole = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $kepsek = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $kepsek->assignRole($kepsekRole);

    // Pending (3 hari) — dihitung.
    app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Cuti, '2026-09-01', '2026-09-03', 'Cuti A.');

    // Rejected (5 hari) — TIDAK dihitung.
    $ditolak = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Cuti, '2026-10-01', '2026-10-05', 'Cuti B.');
    app(ProcessApprovalAction::class)->execute($ditolak->approvalRequest, $kepsek, ApprovalAction::Reject, 'Ditolak.');

    // Beda kategori (Sakit, 100 hari) — TIDAK dihitung meski hari-nya besar.
    app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Sakit, '2026-11-01', '2026-11-05', 'Sakit.');

    $sisa = app(KuotaCutiResolver::class)->sisaKuota($guru, 2026);

    expect($sisa)->toBe(9); // 12 - 3 (hanya pengajuan Pending yang dihitung)
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

```bash
php artisan test tests/Feature/Sdm/KuotaCutiResolverTest.php
```

Expected: **FAIL** — `KuotaCutiResolver` belum ada (`Class "App\Domains\Sdm\Services\KuotaCutiResolver" not found`).

- [ ] **Step 3: Implementasi `KuotaCutiResolver`**

Buat file `app/Domains/Sdm/Services/KuotaCutiResolver.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Services;

use App\Domains\Sdm\Enums\KategoriPengajuanIzin;
use App\Domains\Sdm\Models\KuotaCutiConfig;
use App\Domains\Sdm\Models\PengajuanIzinCuti;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Models\Guru;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

class KuotaCutiResolver
{
    /**
     * Resolusi 4-tingkat: spesifik-jenis lembaga -> flat lembaga -> spesifik-jenis nasional -> flat nasional.
     * MVP saat ini hanya pernah membuat baris "flat" (tier 2/4, jenis_ptk & jenis_karyawan_id NULL) —
     * tier 1/3 (spesifik per jenis) sudah disiapkan resolusinya untuk pengembangan masa depan.
     */
    public function resolveConfig(Model $pegawai): ?KuotaCutiConfig
    {
        $kolomKategori = $pegawai instanceof Guru ? 'jenis_ptk' : 'jenis_karyawan_id';
        $nilaiKategori = $pegawai instanceof Guru ? $pegawai->jenis_ptk : $pegawai->jenis_karyawan_id;

        $spesifikLembaga = KuotaCutiConfig::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $pegawai->lembaga_id)
            ->where($kolomKategori, $nilaiKategori)
            ->first();
        if ($spesifikLembaga) {
            return $spesifikLembaga;
        }

        $flatLembaga = KuotaCutiConfig::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $pegawai->lembaga_id)
            ->whereNull('jenis_ptk')
            ->whereNull('jenis_karyawan_id')
            ->first();
        if ($flatLembaga) {
            return $flatLembaga;
        }

        $yayasanId = $pegawai->lembaga->yayasan_id;

        $spesifikNasional = KuotaCutiConfig::withoutGlobalScope(TenantScope::class)
            ->whereNull('lembaga_id')
            ->where('yayasan_id', $yayasanId)
            ->where($kolomKategori, $nilaiKategori)
            ->first();
        if ($spesifikNasional) {
            return $spesifikNasional;
        }

        return KuotaCutiConfig::withoutGlobalScope(TenantScope::class)
            ->whereNull('lembaga_id')
            ->where('yayasan_id', $yayasanId)
            ->whereNull('jenis_ptk')
            ->whereNull('jenis_karyawan_id')
            ->first();
    }

    public function jatahTahunan(Model $pegawai): int
    {
        return $this->resolveConfig($pegawai)?->jatah_hari_per_tahun ?? 0;
    }

    public function sisaKuota(Model $pegawai, int $tahun): int
    {
        $jatah = $this->jatahTahunan($pegawai);

        if ($jatah === 0) {
            return 0;
        }

        $terpakai = PengajuanIzinCuti::withoutGlobalScope(TenantScope::class)
            ->where('pegawai_type', get_class($pegawai))
            ->where('pegawai_id', $pegawai->id)
            ->where('kategori', KategoriPengajuanIzin::Cuti)
            ->whereYear('tanggal_mulai', $tahun)
            ->whereHas('approvalRequest', fn ($q) => $q->whereIn('status', [
                ApprovalStatus::Pending, ApprovalStatus::InReview, ApprovalStatus::Approved,
            ]))
            ->get()
            ->sum(fn (PengajuanIzinCuti $p) => $p->tanggal_mulai->diffInDays($p->tanggal_selesai) + 1);

        return max(0, $jatah - $terpakai);
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

```bash
php artisan test tests/Feature/Sdm/KuotaCutiResolverTest.php
```

Expected: **PASS** (4 passed).

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Sdm/Services/KuotaCutiResolver.php tests/Feature/Sdm/KuotaCutiResolverTest.php
git commit -m "feat(sdm): tambah KuotaCutiResolver, hitung sisa kuota on-the-fly"
```

---

## Task 3: Enforcement di `AjukanIzinCutiAction`

**Files:**
- Modify: `app/Domains/Sdm/Actions/AjukanIzinCutiAction.php`
- Test: `tests/Feature/Sdm/AjukanIzinCutiActionTest.php` (tambah test baru, JANGAN hapus test yang sudah ada)

**Interfaces:**
- Consumes: `KuotaCutiResolver::jatahTahunan()`, `KuotaCutiResolver::sisaKuota()` (Task 2).
- Signature publik `AjukanIzinCutiAction::execute(Model $pegawai, KategoriPengajuanIzin $kategori, string $tanggalMulai, string $tanggalSelesai, string $alasan): PengajuanIzinCuti` **TIDAK BERUBAH** — tetap dipanggil persis sama dari `PengajuanIzinCutiController::store()` yang sudah ada, TIDAK PERLU diubah.

Isi file `AjukanIzinCutiAction.php` SAAT INI (baseline, baca dulu untuk konfirmasi sebelum edit):

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions;

use App\Domains\Sdm\Enums\KategoriPengajuanIzin;
use App\Domains\Sdm\Models\PengajuanIzinCuti;
use App\Domains\Workflow\Actions\InitializeApprovalRequestAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AjukanIzinCutiAction
{
    public function __construct(private readonly InitializeApprovalRequestAction $initWorkflowAction) {}

    public function execute(
        Model $pegawai,
        KategoriPengajuanIzin $kategori,
        string $tanggalMulai,
        string $tanggalSelesai,
        string $alasan,
    ): PengajuanIzinCuti {
        if ($tanggalMulai > $tanggalSelesai) {
            throw ValidationException::withMessages([
                'tanggal_mulai' => 'Tanggal mulai tidak boleh setelah tanggal selesai.',
            ]);
        }

        return DB::transaction(function () use ($pegawai, $kategori, $tanggalMulai, $tanggalSelesai, $alasan) {
            $pengajuan = $pegawai->pengajuanIzinCuti()->create([
                'lembaga_id' => $pegawai->lembaga_id,
                'kategori' => $kategori,
                'tanggal_mulai' => $tanggalMulai,
                'tanggal_selesai' => $tanggalSelesai,
                'alasan' => $alasan,
            ]);

            $this->initWorkflowAction->execute(
                workflowCode: 'IZIN_CUTI_SDM',
                approvable: $pengajuan,
                requester: $pegawai,
            );

            return $pengajuan;
        });
    }
}
```

Kalau isi file yang kamu baca BEDA dari kutipan di atas (bukan cuma beda baris), STOP dan laporkan ke user — jangan menebak.

- [ ] **Step 1: Tulis test yang gagal dulu**

Buka `tests/Feature/Sdm/AjukanIzinCutiActionTest.php`, TAMBAHKAN (jangan hapus 2 test yang sudah ada) test-test berikut di akhir file:

```php

it('rejects a Cuti pengajuan spanning two different calendar years', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    expect(fn () => app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Cuti, '2026-12-30', '2027-01-02', 'Cuti tahun baru.'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('allows a Sakit pengajuan spanning two different calendar years (only Cuti is restricted)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $pengajuan = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Sakit, '2026-12-30', '2027-01-02', 'Sakit lintas tahun.');

    expect($pengajuan->kategori)->toBe(\App\Domains\Sdm\Enums\KategoriPengajuanIzin::Sakit);
});

it('allows a Cuti pengajuan when there is no kuota config at all (no enforcement)', function () {
    seedKuotaCutiWorkflowForTest_ajukan();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $pengajuan = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Cuti, '2026-09-01', '2026-09-30', 'Cuti panjang, tidak ada config kuota.');

    expect($pengajuan)->not->toBeNull();
});

it('rejects a Cuti pengajuan that exceeds the remaining kuota', function () {
    seedKuotaCutiWorkflowForTest_ajukan();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    \App\Domains\Sdm\Models\KuotaCutiConfig::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jatah_hari_per_tahun' => 5]);

    expect(fn () => app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Cuti, '2026-09-01', '2026-09-10', 'Cuti 10 hari, jatah cuma 5.'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('allows a Cuti pengajuan within the remaining kuota', function () {
    seedKuotaCutiWorkflowForTest_ajukan();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    \App\Domains\Sdm\Models\KuotaCutiConfig::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jatah_hari_per_tahun' => 12]);

    $pengajuan = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Cuti, '2026-09-01', '2026-09-05', 'Cuti 5 hari, jatah 12.');

    expect($pengajuan)->not->toBeNull();
});

it('serializes concurrent Cuti submissions for the same pegawai+tahun via Cache::lock (isolation test, not true concurrency)', function () {
    seedKuotaCutiWorkflowForTest_ajukan();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    \App\Domains\Sdm\Models\KuotaCutiConfig::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jatah_hari_per_tahun' => 12]);

    // Simulasikan "request lain" sedang memegang lock yang sama PERSIS dengan yang akan
    // dipakai AjukanIzinCutiAction (format kuota-cuti:{class}:{id}:{tahun}) — membuktikan
    // Action benar-benar memakai key ini dan benar-benar terblokir kalau lock sedang dipegang,
    // BUKAN true concurrent HTTP request (keterbatasan Pest, dicatat eksplisit di spec §3.6).
    $lockKey = 'kuota-cuti:'.\App\Models\Guru::class.':'.$guru->id.':2026';
    $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 10);
    expect($lock->get())->toBeTrue(); // test proses "menang" duluan pegang lock

    expect(fn () => app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Cuti, '2026-09-01', '2026-09-03', 'Cuti saat lock dipegang.'))
        ->toThrow(\Illuminate\Contracts\Cache\LockTimeoutException::class);

    $lock->release();

    // Setelah lock dilepas, submit yang sama sekarang harus berhasil normal.
    $pengajuan = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Cuti, '2026-09-01', '2026-09-03', 'Cuti setelah lock dilepas.');
    expect($pengajuan)->not->toBeNull();
});

function seedKuotaCutiWorkflowForTest_ajukan(): void
{
    Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);
    Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\WorkflowDefinitionSeeder']);
}
```

**Catatan penting untuk Step 3 (implementasi)**: supaya test lock di atas bisa gagal dengan cepat (bukan menunggu 5 detik penuh sebelum throw), pastikan `AjukanIzinCutiAction` memanggil `Cache::lock($lockKey, 10)->block(5, ...)` PERSIS seperti kode di Step 3 — `block()` akan otomatis throw `LockTimeoutException` setelah gagal mengambil lock dalam 5 detik. Test ini akan memakan waktu nyata ~5 detik untuk berjalan (itu wajar, bukan bug) — kalau ingin test lebih cepat boleh diubah passing angka detik yang lebih kecil KHUSUS di baris pemanggilan test ini saja (jangan ubah angka di kode Action), TAPI defaultnya biarkan apa adanya dulu supaya perilaku production yang diuji benar-benar sama persis.

- [ ] **Step 2: Jalankan test, pastikan yang baru gagal**

```bash
php artisan test tests/Feature/Sdm/AjukanIzinCutiActionTest.php
```

Expected: 2 test lama tetap PASS, 6 test baru **FAIL** (belum ada validasi lintas-tahun maupun kuota, dan `Cache::lock` belum dipakai sama sekali).

- [ ] **Step 3: Implementasi modifikasi**

Timpa SELURUH isi `app/Domains/Sdm/Actions/AjukanIzinCutiAction.php` dengan:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions;

use App\Domains\Sdm\Enums\KategoriPengajuanIzin;
use App\Domains\Sdm\Models\PengajuanIzinCuti;
use App\Domains\Sdm\Services\KuotaCutiResolver;
use App\Domains\Workflow\Actions\InitializeApprovalRequestAction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AjukanIzinCutiAction
{
    public function __construct(
        private readonly InitializeApprovalRequestAction $initWorkflowAction,
        private readonly KuotaCutiResolver $kuotaResolver,
    ) {}

    public function execute(
        Model $pegawai,
        KategoriPengajuanIzin $kategori,
        string $tanggalMulai,
        string $tanggalSelesai,
        string $alasan,
    ): PengajuanIzinCuti {
        if ($tanggalMulai > $tanggalSelesai) {
            throw ValidationException::withMessages([
                'tanggal_mulai' => 'Tanggal mulai tidak boleh setelah tanggal selesai.',
            ]);
        }

        $tahunMulai = (int) substr($tanggalMulai, 0, 4);
        $tahunSelesai = (int) substr($tanggalSelesai, 0, 4);

        if ($kategori === KategoriPengajuanIzin::Cuti && $tahunMulai !== $tahunSelesai) {
            throw ValidationException::withMessages([
                'tanggal_selesai' => 'Pengajuan Cuti tidak boleh melewati pergantian tahun kalender. Silakan ajukan terpisah untuk setiap tahun.',
            ]);
        }

        if ($kategori === KategoriPengajuanIzin::Cuti && $this->kuotaResolver->jatahTahunan($pegawai) > 0) {
            $lockKey = 'kuota-cuti:'.get_class($pegawai).':'.$pegawai->id.':'.$tahunMulai;

            return Cache::lock($lockKey, 10)->block(5, function () use ($pegawai, $kategori, $tanggalMulai, $tanggalSelesai, $alasan, $tahunMulai) {
                $hariDiajukan = Carbon::parse($tanggalMulai)->diffInDays(Carbon::parse($tanggalSelesai)) + 1;
                $sisa = $this->kuotaResolver->sisaKuota($pegawai, $tahunMulai);

                if ($hariDiajukan > $sisa) {
                    throw ValidationException::withMessages([
                        'tanggal_mulai' => "Sisa kuota Cuti Anda tahun ini tinggal {$sisa} hari, tidak cukup untuk {$hariDiajukan} hari yang diajukan.",
                    ]);
                }

                return $this->buatPengajuan($pegawai, $kategori, $tanggalMulai, $tanggalSelesai, $alasan);
            });
        }

        return $this->buatPengajuan($pegawai, $kategori, $tanggalMulai, $tanggalSelesai, $alasan);
    }

    private function buatPengajuan(
        Model $pegawai,
        KategoriPengajuanIzin $kategori,
        string $tanggalMulai,
        string $tanggalSelesai,
        string $alasan,
    ): PengajuanIzinCuti {
        return DB::transaction(function () use ($pegawai, $kategori, $tanggalMulai, $tanggalSelesai, $alasan) {
            $pengajuan = $pegawai->pengajuanIzinCuti()->create([
                'lembaga_id' => $pegawai->lembaga_id,
                'kategori' => $kategori,
                'tanggal_mulai' => $tanggalMulai,
                'tanggal_selesai' => $tanggalSelesai,
                'alasan' => $alasan,
            ]);

            $this->initWorkflowAction->execute(
                workflowCode: 'IZIN_CUTI_SDM',
                approvable: $pengajuan,
                requester: $pegawai,
            );

            return $pengajuan;
        });
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan semua lulus**

```bash
php artisan test tests/Feature/Sdm/AjukanIzinCutiActionTest.php
```

Expected: **PASS** (8 passed — 2 test lama + 6 test baru).

- [ ] **Step 5: Jalankan ulang test Sub-project 4 yang berpotensi tersentuh (regresi)**

```bash
php artisan test tests/Feature/Sdm/ProsesApprovalIzinCutiActionTest.php tests/Feature/Sdm/PengajuanIzinCutiControllerTest.php tests/Feature/Sdm/BatalkanPengajuanIzinCutiActionTest.php tests/Feature/Sdm/KuotaCutiResolverTest.php
```

Expected: **PASS**, semua tanpa perubahan jumlah dari sebelumnya.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Sdm/Actions/AjukanIzinCutiAction.php tests/Feature/Sdm/AjukanIzinCutiActionTest.php
git commit -m "feat(sdm): tegakkan validasi kuota Cuti tahunan + larangan lintas-tahun di AjukanIzinCutiAction"
```

---

## Task 4: Admin UI konfigurasi kuota (tab ke-5)

**Files:**
- Modify: `routes/admin/kehadiran-sdm.php`
- Modify: `app/Http/Controllers/Admin/AttendanceConfigurationController.php`
- Modify: `resources/views/admin/kehadiran-sdm/konfigurasi.blade.php`
- Test: `tests/Feature/Admin/KuotaCutiConfigControllerTest.php` (baru)

**Interfaces:**
- Consumes: `App\Domains\Sdm\Models\KuotaCutiConfig` (Task 1).
- Route baru: `admin.kehadiran-sdm.kuota-cuti.store` (POST), `admin.kehadiran-sdm.kuota-cuti.update` (PUT), `admin.kehadiran-sdm.kuota-cuti.destroy` (DELETE).

- [ ] **Step 1: Tambah route**

Buka `routes/admin/kehadiran-sdm.php`, tambahkan 3 baris berikut TEPAT SETELAH baris `Route::delete('kehadiran-sdm/konfigurasi/penugasan-shift/{penugasanShift}', ...)` (baris 37 di baseline) dan SEBELUM baris kosong yang memisahkan ke blok `izin-cuti`:

```php
Route::post('kehadiran-sdm/konfigurasi/kuota-cuti', [AttendanceConfigurationController::class, 'storeKuotaCuti'])->name('kehadiran-sdm.kuota-cuti.store');
Route::put('kehadiran-sdm/konfigurasi/kuota-cuti/{kuotaCuti}', [AttendanceConfigurationController::class, 'updateKuotaCuti'])->name('kehadiran-sdm.kuota-cuti.update');
Route::delete('kehadiran-sdm/konfigurasi/kuota-cuti/{kuotaCuti}', [AttendanceConfigurationController::class, 'destroyKuotaCuti'])->name('kehadiran-sdm.kuota-cuti.destroy');
```

- [ ] **Step 2: Tambah data kuota di `index()` controller**

Buka `app/Http/Controllers/Admin/AttendanceConfigurationController.php`. Tambahkan import di bagian atas file, setelah baris `use App\Domains\Sdm\Models\KalenderKerjaSdm;`:

```php
use App\Domains\Sdm\Models\KuotaCutiConfig;
```

Lalu cari blok:
```php
        $jenisShiftList = $yayasanId ? JenisShift::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $yayasanId)
            ->where(function ($query) use ($lembagaId) {
                $query->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            })
            ->orderBy('nama')
            ->get() : collect();
```

Tambahkan TEPAT SETELAH blok itu (sebelum `$penugasanShiftList = ...`):

```php
        $kuotaCutiList = $yayasanId ? KuotaCutiConfig::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $yayasanId)
            ->where(function ($query) use ($lembagaId) {
                $query->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            })
            ->orderByRaw('lembaga_id IS NULL')
            ->get() : collect();
```

Lalu cari `return view('admin.kehadiran-sdm.konfigurasi', [` dan tambahkan 1 baris baru setelah `'karyawanList' => $karyawanList,`:

```php
            'kuotaCutiList' => $kuotaCutiList,
```

- [ ] **Step 3: Tambah 3 method handler**

Di file yang sama, tambahkan 3 method baru TEPAT SETELAH method `destroyPenugasanShift()` (sebelum `private function resolveLembagaId`):

```php
    public function storeKuotaCuti(Request $request): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        $data = $request->validate([
            'jatah_hari_per_tahun' => ['required', 'integer', 'min:0'],
            'is_nasional' => ['nullable', 'boolean'],
        ]);

        $isNasional = (bool) ($data['is_nasional'] ?? false);

        if ($isNasional && $request->user()->widestScopeLevel() !== 'yayasan') {
            abort(403, 'Hanya aktor berscope yayasan yang boleh membuat kuota cuti nasional.');
        }

        $lembagaId = $isNasional ? null : $this->resolveLembagaId($request);

        if (! $isNasional && $lembagaId === null) {
            return back()->withErrors(['jatah_hari_per_tahun' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah kuota cuti.']);
        }

        $yayasanId = $this->resolveYayasanId($request, $lembagaId);

        $sudahAda = KuotaCutiConfig::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $yayasanId)
            ->where('lembaga_id', $lembagaId)
            ->whereNull('jenis_ptk')
            ->whereNull('jenis_karyawan_id')
            ->exists();

        if ($sudahAda) {
            return back()->withErrors(['jatah_hari_per_tahun' => 'Kuota cuti untuk scope ini sudah ada. Edit yang sudah ada, jangan buat baru.']);
        }

        KuotaCutiConfig::create([
            'yayasan_id' => $yayasanId,
            'lembaga_id' => $lembagaId,
            'jenis_ptk' => null,
            'jenis_karyawan_id' => null,
            'jatah_hari_per_tahun' => $data['jatah_hari_per_tahun'],
        ]);

        return back()->with('status', 'Kuota cuti berhasil ditambahkan.');
    }

    public function updateKuotaCuti(Request $request, KuotaCutiConfig $kuotaCuti): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        if ($kuotaCuti->lembaga_id === null && $request->user()->widestScopeLevel() !== 'yayasan') {
            abort(403, 'Hanya aktor berscope yayasan yang boleh mengubah kuota cuti nasional.');
        }

        $data = $request->validate([
            'jatah_hari_per_tahun' => ['required', 'integer', 'min:0'],
        ]);

        $kuotaCuti->update(['jatah_hari_per_tahun' => $data['jatah_hari_per_tahun']]);

        return back()->with('status', 'Kuota cuti berhasil diperbarui.');
    }

    public function destroyKuotaCuti(Request $request, KuotaCutiConfig $kuotaCuti): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        if ($kuotaCuti->lembaga_id === null && $request->user()->widestScopeLevel() !== 'yayasan') {
            abort(403, 'Hanya aktor berscope yayasan yang boleh menghapus kuota cuti nasional.');
        }

        $kuotaCuti->delete();

        return back()->with('status', 'Kuota cuti berhasil dihapus.');
    }
```

- [ ] **Step 4: Tambah state Alpine di `konfigurasi.blade.php`**

Buka `resources/views/admin/kehadiran-sdm/konfigurasi.blade.php`. Cari blok (baris 99-101 di baseline):

```
        togglePenugasanHari(day) {
            this.formPenugasan.hari_kerja = this.formPenugasan.hari_kerja.includes(day) ? this.formPenugasan.hari_kerja.filter((d) => d !== day) : [...this.formPenugasan.hari_kerja, day];
        }
    }">
```

Ganti dengan (menambahkan koma di baris `}` pertama dan menambahkan state baru sebelum `    }">`):

```
        togglePenugasanHari(day) {
            this.formPenugasan.hari_kerja = this.formPenugasan.hari_kerja.includes(day) ? this.formPenugasan.hari_kerja.filter((d) => d !== day) : [...this.formPenugasan.hari_kerja, day];
        },
        showKuotaModal: false,
        editingKuota: null,
        formKuota: { jatah_hari_per_tahun: 12, is_nasional: false },
        openKuotaModal(kuota = null, nasional = false) {
            this.editingKuota = kuota;
            this.formKuota = kuota
                ? { jatah_hari_per_tahun: kuota.jatah_hari_per_tahun, is_nasional: kuota.lembaga_id === null }
                : { jatah_hari_per_tahun: 12, is_nasional: nasional };
            this.showKuotaModal = true;
        }
    }">
```

- [ ] **Step 5: Tambah tombol tab ke-5**

Di file yang sama, cari blok tombol tab "Shift Bergilir" (baris 171-181 di baseline):

```
                <button 
                    type="button" 
                    @click="setTab('shift')" 
                    :class="tab === 'shift' ? 'bg-brand-50 text-brand-700 font-semibold shadow-2xs border border-brand-200' : 'text-gray-600 hover:bg-gray-50 border border-transparent'" 
                    class="rounded-xl px-4 py-2 text-xs transition whitespace-nowrap flex items-center gap-2"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Shift Bergilir</span>
                </button>
            </div>
        </div>
```

Ganti dengan (tambah 1 tombol baru sebelum `</div>\n        </div>` penutup tab bar):

```
                <button 
                    type="button" 
                    @click="setTab('shift')" 
                    :class="tab === 'shift' ? 'bg-brand-50 text-brand-700 font-semibold shadow-2xs border border-brand-200' : 'text-gray-600 hover:bg-gray-50 border border-transparent'" 
                    class="rounded-xl px-4 py-2 text-xs transition whitespace-nowrap flex items-center gap-2"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Shift Bergilir</span>
                </button>

                <button 
                    type="button" 
                    @click="setTab('kuota')" 
                    :class="tab === 'kuota' ? 'bg-brand-50 text-brand-700 font-semibold shadow-2xs border border-brand-200' : 'text-gray-600 hover:bg-gray-50 border border-transparent'" 
                    class="rounded-xl px-4 py-2 text-xs transition whitespace-nowrap flex items-center gap-2"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <span>Kuota Cuti</span>
                </button>
            </div>
        </div>
```

- [ ] **Step 6: Tambah konten tab ke-5**

Di file yang sama, cari blok penutup tab "shift" beserta awal blok modal (baris 500-509 di baseline):

```
                    @empty
                        <div class="py-8 text-center text-gray-400">
                            <p class="text-xs font-semibold text-gray-700">Belum ada penugasan shift untuk lembaga ini.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Standard Modal 1: Titik Absen --}}
```

Ganti dengan (menyisipkan konten Tab 5 + modal-nya SEBELUM komentar `Standard Modal 1`):

```
                    @empty
                        <div class="py-8 text-center text-gray-400">
                            <p class="text-xs font-semibold text-gray-700">Belum ada penugasan shift untuk lembaga ini.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Tab 5: Kuota Cuti --}}
        <div x-show="tab === 'kuota'" x-cloak class="space-y-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <div>
                        <h2 class="font-display text-sm font-bold text-gray-900">Kuota Cuti Tahunan</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Jatah hari Cuti per tahun kalender. Hanya berlaku untuk kategori Cuti — Izin dan Sakit tidak dibatasi. Tanpa kuota terkonfigurasi, pengajuan Cuti tidak dibatasi sama sekali.</p>
                    </div>
                    @can('kehadiran-sdm.kelola-konfigurasi')
                        <button type="button" @click="openKuotaModal(null, false)" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-brand-700 transition active:scale-[0.98]">
                            <span>+ Tambah Kuota Cuti</span>
                        </button>
                    @endcan
                </div>

                <div class="mt-3 divide-y divide-gray-100">
                    @forelse ($kuotaCutiList as $kuota)
                        <div class="flex items-center justify-between py-3">
                            <div>
                                <p class="text-xs font-bold text-gray-900">
                                    {{ $kuota->lembaga_id === null ? 'Semua Pegawai (Flat)' : 'Semua Pegawai Lembaga Ini (Flat)' }}
                                    @if ($kuota->lembaga_id === null)
                                        <span class="ml-1 inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700 border border-indigo-200">Nasional</span>
                                    @endif
                                </p>
                                <p class="text-[11px] text-gray-500 font-mono mt-0.5">{{ $kuota->jatah_hari_per_tahun }} hari / tahun</p>
                            </div>
                            @can('kehadiran-sdm.kelola-konfigurasi')
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="openKuotaModal({{ $kuota->toJson() }})" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-brand-200 bg-brand-50 text-xs font-semibold text-brand-700 hover:bg-brand-100 transition shadow-2xs">Edit</button>
                                    <form 
                                        method="POST" 
                                        action="{{ route('admin.kehadiran-sdm.kuota-cuti.destroy', $kuota) }}?tab=kuota" 
                                        @submit.prevent="confirmDialog('Hapus Kuota Cuti?', 'Apakah Anda yakin ingin menghapus konfigurasi kuota cuti ini?', { confirmLabel: 'Ya, Hapus Kuota', isDanger: true }).then(confirmed => { if (confirmed) $el.submit() })"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-rose-200 bg-rose-50 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition shadow-2xs">Hapus</button>
                                    </form>
                                </div>
                            @endcan
                        </div>
                    @empty
                        <div class="py-8 text-center text-gray-400">
                            <p class="text-xs font-semibold text-gray-700">Belum ada kuota cuti dikonfigurasi.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Standard Modal 7: Kuota Cuti --}}
        <div x-show="showKuotaModal" class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center" style="display: none;">
            <div x-show="showKuotaModal" class="fixed inset-0 transform transition-all" @click="showKuotaModal = false"
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-xs"></div>
            </div>

            <div x-show="showKuotaModal" class="bg-white rounded-2xl overflow-hidden shadow-elevated transform transition-all sm:max-w-md sm:w-full z-10 p-6 relative text-left"
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                <div class="flex items-center justify-between pb-3.5 border-b border-gray-100">
                    <h3 class="font-display text-base font-bold text-gray-900" x-text="editingKuota ? 'Edit Kuota Cuti' : 'Tambah Kuota Cuti'"></h3>
                    <button @click="showKuotaModal = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <form method="POST" :action="editingKuota ? `/admin/kehadiran-sdm/konfigurasi/kuota-cuti/${editingKuota.id}?tab=kuota` : '{{ route('admin.kehadiran-sdm.kuota-cuti.store') }}?tab=kuota'" class="mt-4 space-y-4">
                    @csrf
                    <template x-if="editingKuota"><input type="hidden" name="_method" value="PUT"></template>
                    <template x-if="!editingKuota && formKuota.is_nasional"><input type="hidden" name="is_nasional" value="1"></template>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jatah Hari Cuti per Tahun <span class="text-rose-500">*</span></label>
                        <input x-model.number="formKuota.jatah_hari_per_tahun" name="jatah_hari_per_tahun" type="number" min="0" required class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-4 mt-6 border-t border-gray-100">
                        <x-secondary-button type="button" @click="showKuotaModal = false">Batal</x-secondary-button>
                        <x-primary-button type="submit">Simpan Perubahan</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Standard Modal 1: Titik Absen --}}
```

- [ ] **Step 7: Tulis test render + CRUD**

Buat file `tests/Feature/Admin/KuotaCutiConfigControllerTest.php`:

```php
<?php
// tests/Feature/Admin/KuotaCutiConfigControllerTest.php

use App\Domains\Sdm\Models\KuotaCutiConfig;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsAdminSdmKuotaCuti(Lembaga $lembaga): User
{
    Permission::firstOrCreate(['name' => 'kehadiran-sdm.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'kehadiran-sdm.kelola-konfigurasi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kehadiran-sdm.view', 'kehadiran-sdm.kelola-konfigurasi']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    return $user;
}

it('renders the Kuota Cuti tab on the konfigurasi page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = actingAsAdminSdmKuotaCuti($lembaga);

    $response = $this->actingAs($user)->get(route('admin.kehadiran-sdm.konfigurasi.index'));

    $response->assertOk()->assertSee('Kuota Cuti')->assertSee('kuota-cuti', false);
});

it('lets admin_sdm create a flat kuota cuti config for their lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = actingAsAdminSdmKuotaCuti($lembaga);

    $this->actingAs($user)->post(route('admin.kehadiran-sdm.kuota-cuti.store'), [
        'jatah_hari_per_tahun' => 12,
    ])->assertRedirect();

    expect(KuotaCutiConfig::where('lembaga_id', $lembaga->id)->where('jatah_hari_per_tahun', 12)->exists())->toBeTrue();
});

it('rejects creating a duplicate flat kuota cuti config for the same lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = actingAsAdminSdmKuotaCuti($lembaga);
    KuotaCutiConfig::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jatah_hari_per_tahun' => 10]);

    $this->actingAs($user)->post(route('admin.kehadiran-sdm.kuota-cuti.store'), [
        'jatah_hari_per_tahun' => 15,
    ])->assertSessionHasErrors('jatah_hari_per_tahun');
});
```

- [ ] **Step 8: Jalankan test**

```bash
php artisan test tests/Feature/Admin/KuotaCutiConfigControllerTest.php
```

Expected: **PASS** (3 passed).

- [ ] **Step 9: Jalankan ulang test konfigurasi lain (regresi render halaman)**

```bash
php artisan test tests/Feature/Sdm --filter=Konfigurasi
```

Kalau tidak ada test dengan nama itu, jalankan minimal seluruh direktori `tests/Feature/Admin` yang menyentuh `AttendanceConfigurationController` untuk pastikan tidak ada regresi render:

```bash
php artisan test tests/Feature/Sdm tests/Feature/Admin
```

Expected: semua PASS, tidak ada test lama yang berubah dari hijau jadi merah (test flaky hari-Minggu yang sudah dikenal, kalau muncul, boleh diabaikan — lihat catatan di Task 6).

- [ ] **Step 10: Commit**

```bash
git add routes/admin/kehadiran-sdm.php app/Http/Controllers/Admin/AttendanceConfigurationController.php resources/views/admin/kehadiran-sdm/konfigurasi.blade.php tests/Feature/Admin/KuotaCutiConfigControllerTest.php
git commit -m "feat(sdm): tambah tab Kuota Cuti di halaman konfigurasi admin kehadiran SDM"
```

---

## Task 5: Tampilkan sisa kuota di form self-service pegawai

**Files:**
- Modify: `app/Http/Controllers/PengajuanIzinCutiController.php`
- Modify: `resources/views/sdm/izin-cuti/create.blade.php`
- Test: `tests/Feature/Sdm/PengajuanIzinCutiControllerTest.php` (tambah test baru, JANGAN hapus yang sudah ada)

**Interfaces:**
- Consumes: `KuotaCutiResolver::sisaKuota()` (Task 2).

- [ ] **Step 1: Tulis test yang gagal dulu**

Buka `tests/Feature/Sdm/PengajuanIzinCutiControllerTest.php`, TAMBAHKAN test berikut di akhir file:

```php

it('shows the remaining Cuti kuota on the create form when configured', function () {
    Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);
    $yayasan = App\Models\Yayasan::factory()->create();
    $lembaga = App\Models\Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = App\Models\Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    App\Domains\Sdm\Models\KuotaCutiConfig::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jatah_hari_per_tahun' => 12]);
    $role = App\Models\Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'kehadiran-sdm.izin.ajukan', 'guard_name' => 'web']);
    $role->givePermissionTo(['kehadiran-sdm.izin.ajukan']);
    $guru->user->assignRole($role);

    $response = $this->actingAs($guru->user)->get(route('sdm.izin-cuti.create'));

    $response->assertOk()->assertSee('12');
});
```

**Catatan:** kalau relasi `$guru->user` tidak ada/berbeda dari yang diasumsikan (mis. nama relasinya beda), baca `app/Models/Guru.php` dulu untuk konfirmasi nama relasi User yang benar sebelum menulis test ini persis seperti di atas — sesuaikan tanpa mengubah maksud test-nya.

- [ ] **Step 2: Jalankan test, pastikan gagal**

```bash
php artisan test tests/Feature/Sdm/PengajuanIzinCutiControllerTest.php
```

Expected: test lama tetap PASS, test baru **FAIL** (halaman belum menampilkan angka sisa kuota).

- [ ] **Step 3: Modifikasi controller**

Buka `app/Http/Controllers/PengajuanIzinCutiController.php`. Tambahkan import setelah `use App\Domains\Sdm\Enums\KategoriPengajuanIzin;`:

```php
use App\Domains\Sdm\Services\KuotaCutiResolver;
```

Ganti method `create()` yang sekarang:

```php
    public function create(Request $request): View
    {
        $this->authorize('kehadiran-sdm.izin.ajukan');

        return view('sdm.izin-cuti.create', ['kategoriOptions' => KategoriPengajuanIzin::cases()]);
    }
```

Menjadi:

```php
    public function create(Request $request, KuotaCutiResolver $kuotaResolver): View
    {
        $this->authorize('kehadiran-sdm.izin.ajukan');

        $pegawai = $this->resolvePegawai($request);
        $sisaKuotaCuti = $pegawai ? $kuotaResolver->sisaKuota($pegawai, (int) now()->format('Y')) : null;
        $adaKonfigurasiKuota = $pegawai && $kuotaResolver->jatahTahunan($pegawai) > 0;

        return view('sdm.izin-cuti.create', [
            'kategoriOptions' => KategoriPengajuanIzin::cases(),
            'sisaKuotaCuti' => $sisaKuotaCuti,
            'adaKonfigurasiKuota' => $adaKonfigurasiKuota,
        ]);
    }
```

- [ ] **Step 4: Modifikasi view**

Buka `resources/views/sdm/izin-cuti/create.blade.php`. Ganti baris pembuka form:

```blade
            <form 
                method="POST" 
                action="{{ route('sdm.izin-cuti.store') }}" 
                class="space-y-5"
                x-data
                @submit.prevent="confirmDialog(
```

Menjadi:

```blade
            <form 
                method="POST" 
                action="{{ route('sdm.izin-cuti.store') }}" 
                class="space-y-5"
                x-data="{ kategori: '{{ old('kategori') }}' }"
                @submit.prevent="confirmDialog(
```

Lalu cari blok Kategori Dropdown:

```blade
                {{-- Kategori Dropdown --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Kategori Permohonan <span class="text-rose-500">*</span></label>
                    <select name="kategori" required class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Pilih Kategori Permohonan —</option>
                        @foreach ($kategoriOptions as $kategori)
                            <option value="{{ $kategori->value }}" {{ old('kategori') === $kategori->value ? 'selected' : '' }}>
                                {{ $kategori->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
```

Ganti dengan (tambah `x-model` di select, dan tambah info card sisa kuota di bawahnya):

```blade
                {{-- Kategori Dropdown --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Kategori Permohonan <span class="text-rose-500">*</span></label>
                    <select name="kategori" x-model="kategori" required class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Pilih Kategori Permohonan —</option>
                        @foreach ($kategoriOptions as $kategori)
                            <option value="{{ $kategori->value }}" {{ old('kategori') === $kategori->value ? 'selected' : '' }}>
                                {{ $kategori->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @if ($adaKonfigurasiKuota)
                    <div x-show="kategori === 'cuti'" x-cloak class="rounded-xl border border-brand-100 bg-brand-50/50 p-3.5 text-xs font-semibold text-brand-800">
                        Sisa kuota Cuti Anda tahun ini: <span class="font-mono">{{ $sisaKuotaCuti }}</span> hari.
                    </div>
                @endif
```

- [ ] **Step 5: Jalankan test, pastikan lulus**

```bash
php artisan test tests/Feature/Sdm/PengajuanIzinCutiControllerTest.php
```

Expected: **PASS**, semua test (lama + baru).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/PengajuanIzinCutiController.php resources/views/sdm/izin-cuti/create.blade.php tests/Feature/Sdm/PengajuanIzinCutiControllerTest.php
git commit -m "feat(sdm): tampilkan sisa kuota Cuti di form pengajuan self-service"
```

---

## Task 6: Verifikasi akhir + handoff log

**Files:**
- Create: `.agents/logs/2026-08-23-sdm-06-kuota-cuti-tahunan.md`

- [ ] **Step 1: Jalankan seluruh test SDM (scoped, bukan full suite)**

```bash
php artisan test tests/Feature/Sdm tests/Feature/Admin
```

Catat jumlah pasti passed/failed. **Catatan flaky yang sudah dikenal**: `ScanQrAttendanceActionTest` bisa gagal kalau hari eksekusi kebetulan hari Minggu (default hari libur mingguan) karena test itu memakai `now()` — kalau itu satu-satunya yang gagal, jalankan test itu SENDIRIAN untuk konfirmasi, dan itu BUKAN regresi dari kerjaan ini.

- [ ] **Step 2: Verifikasi `git diff` terhadap file yang TIDAK BOLEH berubah**

```bash
git diff 210c673..HEAD -- app/Domains/Workflow/ app/Domains/Sdm/Actions/ProsesApprovalIzinCutiAction.php app/Domains/Sdm/Services/KalenderKerjaSdmResolver.php app/Domains/Sdm/Services/AttendancePolicyResolver.php app/Domains/Sdm/Services/ShiftAwareAttendanceResolver.php
```

Expected: **KOSONG** (tidak ada output). Kalau ada output, itu pelanggaran hard constraint — STOP dan laporkan.

- [ ] **Step 3: Minta izin user untuk full test suite**

Tanya ke user: "Task 1-5 selesai, test scoped semua hijau. Boleh saya jalankan full test suite (`php artisan test`) untuk verifikasi akhir?" — TUNGGU jawaban eksplisit sebelum lanjut ke Step 4. JANGAN jalankan otomatis tanpa izin.

- [ ] **Step 4: Jalankan full suite (HANYA setelah izin didapat)**

```bash
php artisan test
```

Catat angka pasti passed/failed/duration.

- [ ] **Step 5: Tulis handoff log**

Buat file `.agents/logs/2026-08-23-sdm-06-kuota-cuti-tahunan.md` (Bahasa Indonesia) berisi: ringkasan tiap task (1-5) dengan commit hash masing-masing, hasil test dengan angka pasti (jangan klaim tanpa command nyata), hasil `git diff` Step 2, dan hasil full suite Step 4 (atau catatan kalau user belum memberi izin saat log ditulis).

- [ ] **Step 6: Commit handoff log**

```bash
git add .agents/logs/2026-08-23-sdm-06-kuota-cuti-tahunan.md
git commit -m "docs(sdm): handoff log kuota/saldo cuti tahunan"
```
