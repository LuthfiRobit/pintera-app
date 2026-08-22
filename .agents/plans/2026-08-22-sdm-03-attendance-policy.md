# Kehadiran SDM Sub-project 3 — Attendance Policy Dasar — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bangun Attendance Policy (jam kerja + toleransi per `jenis_ptk`/`jenis_karyawan_id`), tambahkan deteksi `is_late`/`late_minutes` di `AttendanceRecord`, dan tutup sebagian celah auto-alpa Sub-project 2 untuk kategori pegawai yang Policy-nya meng-override hari kerja (mis. satpam kerja 7 hari).

**Architecture:** Domain `App\Domains\Sdm\` diperluas: `Models\AttendancePolicy`, `Services\AttendancePolicyResolver` (baru, MEMBUNGKUS `KalenderKerjaSdmResolver` Sub-project 2 TANPA mengubahnya). `RecordManualAttendanceAction`/`ScanQrAttendanceAction` (Sub-project 1) ganti sumber resolusi libur dari `KalenderKerjaSdmResolver` langsung jadi `AttendancePolicyResolver`. `AttendanceRecordAggregator` (Sub-project 1) dapat dependency baru untuk hitung `is_late`/`late_minutes`. `TandaiAlpaOtomatisSdm` (Sub-project 2) dapat lapis pengecekan tambahan untuk pegawai berkategori ber-Policy-override di lembaga yang kalendernya libur.

**Tech Stack:** Laravel 11, PHP 8.2+, Pest (test), sama seperti Sub-project 1 & 2.

## Global Constraints

- Branch kerja: `sdm-v1`. JANGAN buat branch baru, JANGAN buat worktree.
- Baseline: commit `1badcf0` di branch `sdm-v1` (spec Sub-project 3 baru dikomit). Kalau ada commit baru masuk sebelum eksekusi, verifikasi ulang file yang dikutip plan ini sebelum melanjutkan — terutama `app/Domains/Sdm/Actions/RecordManualAttendanceAction.php`, `app/Domains/Sdm/Actions/ScanQrAttendanceAction.php`, `app/Domains/Sdm/Services/AttendanceRecordAggregator.php`, `app/Console/Commands/TandaiAlpaOtomatisSdm.php`, `app/Http/Controllers/Admin/AttendanceConfigurationController.php`, `resources/views/admin/kehadiran-sdm/konfigurasi.blade.php`.
- Spec lengkap: `.agents/specs/2026-08-22-sdm-03-attendance-policy.md` — baca dulu untuk "kenapa", plan ini "bagaimana"-nya.
- **`AttendancePolicy` model WAJIB pakai `BelongsToTenant`**, dan **`AttendancePolicyResolver::resolvePolicy()` KEDUA query di dalamnya WAJIB `withoutGlobalScope(TenantScope::class)`** — pola dan alasan IDENTIK `KalenderKerjaSdmResolver` (Sub-project 2). Ini bukan opsional.
- **`KalenderKerjaSdmResolver` (Sub-project 2) TIDAK BOLEH diubah/disentuh sama sekali** di plan ini — `AttendancePolicyResolver` membungkusnya lewat constructor injection, bukan memodifikasinya.
- Unique index DB pada `attendance_policies` (`yayasan_id, lembaga_id, jenis_ptk, jenis_karyawan_id`) TIDAK CUKUP mencegah duplikat sendirian — MySQL menganggap 2 baris `NULL` sebagai "tidak sama" untuk keperluan unique index, jadi 2 Policy `jenis_ptk='guru_kelas'` dengan `jenis_karyawan_id` sama-sama `NULL` TIDAK akan ditolak DB. WAJIB ada pengecekan duplikat eksplisit di level controller (query `exists()` sebelum insert) — dijelaskan detail di Task 7.
- TIDAK ADA hardcode nama role apapun — gerbang baris default yayasan pakai `$request->user()->widestScopeLevel() === 'yayasan'`.
- TIDAK membangun shift bergilir per periode — di luar cakupan total plan ini.
- Testing policy: test scoped per task, dijalankan SEBELUM commit setiap task. Full suite HANYA di Task 9, dan HANYA setelah izin eksplisit user.
- Satu commit per task, pesan commit sesuai yang ditentukan di tiap task Step terakhir.
- Test framework: Pest, gaya `it('...', function () { ... })`.

---

## Task 1: Migrasi (`attendance_policies` + kolom `is_late`/`late_minutes`)

**Files:**
- Create: `database/migrations/2026_08_22_110000_create_attendance_policies_table.php`
- Create: `database/migrations/2026_08_22_110100_add_is_late_columns_to_attendance_records_table.php`

**Interfaces:**
- Produces: tabel `attendance_policies`; kolom `attendance_records.is_late` (boolean, default false), `attendance_records.late_minutes` (integer, nullable) — dipakai Task 2 dst.

- [ ] **Step 1: Buat migrasi `attendance_policies`**

```php
<?php
// database/migrations/2026_08_22_110000_create_attendance_policies_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yayasan_id')->constrained('yayasan')->cascadeOnDelete();
            $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->cascadeOnDelete();
            $table->string('jenis_ptk')->nullable();
            $table->foreignId('jenis_karyawan_id')->nullable()->constrained('jenis_karyawan_master')->cascadeOnDelete();
            $table->time('jam_masuk');
            $table->time('jam_pulang')->nullable();
            $table->unsignedInteger('toleransi_menit')->default(0);
            $table->json('hari_kerja')->nullable();
            $table->timestamps();

            $table->unique(['yayasan_id', 'lembaga_id', 'jenis_ptk', 'jenis_karyawan_id'], 'attendance_policy_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_policies');
    }
};
```

- [ ] **Step 2: Buat migrasi kolom `is_late`/`late_minutes`**

```php
<?php
// database/migrations/2026_08_22_110100_add_is_late_columns_to_attendance_records_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->boolean('is_late')->default(false)->after('status');
            $table->unsignedInteger('late_minutes')->nullable()->after('is_late');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['is_late', 'late_minutes']);
        });
    }
};
```

- [ ] **Step 3: Jalankan migrasi dan verifikasi**

Run: `php artisan migrate`
Expected: 2 migrasi baru berjalan sukses.

Run: `php artisan migrate:status | grep attendance_polic`
Expected: `Ran`.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_08_22_110000_create_attendance_policies_table.php database/migrations/2026_08_22_110100_add_is_late_columns_to_attendance_records_table.php
git commit -m "feat(sdm): migrasi tabel attendance_policies + kolom is_late/late_minutes di attendance_records"
```

---

## Task 2: Model `AttendancePolicy`

**Files:**
- Create: `app/Domains/Sdm/Models/AttendancePolicy.php`
- Modify: `app/Domains/Sdm/Models/AttendanceRecord.php`
- Test: `tests/Feature/Sdm/AttendancePolicyModelTest.php`

**Interfaces:**
- Produces: `AttendancePolicy::create([...])`; `AttendanceRecord` dengan `is_late`/`late_minutes` di `$fillable`+cast — dipakai Task 3 dst.

- [ ] **Step 1: Buat model `AttendancePolicy`**

```php
<?php

namespace App\Domains\Sdm\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\JenisKaryawanMaster;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePolicy extends Model
{
    use BelongsToTenant;

    protected $table = 'attendance_policies';

    protected $fillable = [
        'yayasan_id', 'lembaga_id', 'jenis_ptk', 'jenis_karyawan_id',
        'jam_masuk', 'jam_pulang', 'toleransi_menit', 'hari_kerja',
    ];

    protected function casts(): array
    {
        return [
            'toleransi_menit' => 'integer',
            'hari_kerja' => 'array',
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

Catatan: `jam_masuk`/`jam_pulang` SENGAJA TIDAK di-cast (tetap string mentah `"07:00:00"` dari kolom `TIME` MySQL) — supaya gampang digabung manual dengan tanggal spesifik di `AttendancePolicyResolver`/`AttendanceRecordAggregator` (Task 3, 5) tanpa perlu ekstraksi ulang dari objek `Carbon` yang teranchor ke tanggal sembarangan.

- [ ] **Step 2: Tambah `is_late`/`late_minutes` ke `AttendanceRecord`**

Cari baris:

```php
    protected $fillable = [
        'lembaga_id', 'pegawai_type', 'pegawai_id', 'tanggal', 'status', 'waktu_masuk', 'waktu_pulang',
    ];
```

Ganti jadi:

```php
    protected $fillable = [
        'lembaga_id', 'pegawai_type', 'pegawai_id', 'tanggal', 'status', 'waktu_masuk', 'waktu_pulang',
        'is_late', 'late_minutes',
    ];
```

Cari baris:

```php
    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'tanggal' => 'date',
            'waktu_masuk' => 'datetime',
            'waktu_pulang' => 'datetime',
        ];
    }
```

Ganti jadi:

```php
    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'tanggal' => 'date',
            'waktu_masuk' => 'datetime',
            'waktu_pulang' => 'datetime',
            'is_late' => 'boolean',
            'late_minutes' => 'integer',
        ];
    }
```

- [ ] **Step 3: Tulis test**

```php
<?php
// tests/Feature/Sdm/AttendancePolicyModelTest.php

use App\Domains\Sdm\Models\AttendancePolicy;
use App\Models\JenisKaryawanMaster;
use App\Models\Lembaga;
use App\Models\Yayasan;

it('creates an attendance policy for a guru category (jenis_ptk)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $policy = AttendancePolicy::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id,
        'jenis_ptk' => 'guru_kelas', 'jenis_karyawan_id' => null,
        'jam_masuk' => '07:00', 'toleransi_menit' => 15,
    ]);

    expect($policy->jenis_ptk)->toBe('guru_kelas');
    expect($policy->jenis_karyawan_id)->toBeNull();
    expect($policy->toleransi_menit)->toBe(15);
});

it('creates an attendance policy for a karyawan category (jenis_karyawan_id) with a hari_kerja override', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $jenisKaryawan = JenisKaryawanMaster::factory()->create(['nama' => 'Satpam']);

    $policy = AttendancePolicy::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id,
        'jenis_ptk' => null, 'jenis_karyawan_id' => $jenisKaryawan->id,
        'jam_masuk' => '18:00', 'jam_pulang' => '06:00', 'toleransi_menit' => 10,
        'hari_kerja' => [0, 1, 2, 3, 4, 5, 6],
    ]);

    expect($policy->jenis_karyawan_id)->toBe($jenisKaryawan->id);
    expect($policy->hari_kerja)->toBe([0, 1, 2, 3, 4, 5, 6]);
});
```

- [ ] **Step 4: Jalankan test**

Run: `php artisan test tests/Feature/Sdm/AttendancePolicyModelTest.php`
Expected: 2 passed, 0 failed.

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Sdm/Models/AttendancePolicy.php app/Domains/Sdm/Models/AttendanceRecord.php tests/Feature/Sdm/AttendancePolicyModelTest.php
git commit -m "feat(sdm): tambah model AttendancePolicy, kolom is_late/late_minutes di AttendanceRecord"
```

---

## Task 3: `AttendancePolicyResolver`

**Files:**
- Create: `app/Domains/Sdm/Services/AttendancePolicyResolver.php`
- Test: `tests/Unit/Services/AttendancePolicyResolverTest.php`
- Test: `tests/Feature/Sdm/AttendancePolicyTenantIsolationTest.php`

**Interfaces:**
- Consumes: `AttendancePolicy` (Task 2), `KalenderKerjaSdmResolver` (Sub-project 2, TIDAK diubah).
- Produces: `AttendancePolicyResolver::resolvePolicy(Model $pegawai): ?AttendancePolicy`; `AttendancePolicyResolver::resolveLibur(Model $pegawai, CarbonInterface $tanggal): array{libur: bool, alasan: string}` — dipakai Task 4, 5, 6.

- [ ] **Step 1: Buat `AttendancePolicyResolver`**

```php
<?php

namespace App\Domains\Sdm\Services;

use App\Domains\Sdm\Models\AttendancePolicy;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Scopes\TenantScope;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class AttendancePolicyResolver
{
    public function __construct(private readonly KalenderKerjaSdmResolver $kalenderResolver) {}

    public function resolvePolicy(Model $pegawai): ?AttendancePolicy
    {
        $kolomKategori = $pegawai instanceof Guru ? 'jenis_ptk' : 'jenis_karyawan_id';
        $nilaiKategori = $pegawai instanceof Guru ? $pegawai->jenis_ptk : $pegawai->jenis_karyawan_id;

        $policyLembaga = AttendancePolicy::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $pegawai->lembaga_id)
            ->where($kolomKategori, $nilaiKategori)
            ->first();

        if ($policyLembaga) {
            return $policyLembaga;
        }

        return AttendancePolicy::withoutGlobalScope(TenantScope::class)
            ->whereNull('lembaga_id')
            ->where('yayasan_id', $pegawai->lembaga->yayasan_id)
            ->where($kolomKategori, $nilaiKategori)
            ->first();
    }

    /**
     * @return array{libur: bool, alasan: string}
     */
    public function resolveLibur(Model $pegawai, CarbonInterface $tanggal): array
    {
        $policy = $this->resolvePolicy($pegawai);

        if ($policy && $policy->hari_kerja !== null) {
            $adalahHariKerja = in_array($tanggal->dayOfWeek, $policy->hari_kerja, true);

            return $adalahHariKerja
                ? ['libur' => false, 'alasan' => 'Hari kerja sesuai kebijakan peran']
                : ['libur' => true, 'alasan' => 'Hari libur sesuai kebijakan peran'];
        }

        return $this->kalenderResolver->resolve($pegawai->lembaga, $tanggal);
    }
}
```

- [ ] **Step 2: Tulis test `tests/Unit/Services/AttendancePolicyResolverTest.php`**

```php
<?php
// tests/Unit/Services/AttendancePolicyResolverTest.php

use App\Domains\Sdm\Models\AttendancePolicy;
use App\Domains\Sdm\Services\AttendancePolicyResolver;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\JenisKaryawanMaster;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Carbon\Carbon;

it('resolves the lembaga-specific policy over the yayasan default for the same jenis_ptk', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas']);

    AttendancePolicy::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null, 'jenis_ptk' => 'guru_kelas', 'jam_masuk' => '07:00', 'toleransi_menit' => 0]);
    AttendancePolicy::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas', 'jam_masuk' => '07:30', 'toleransi_menit' => 10]);

    $policy = app(AttendancePolicyResolver::class)->resolvePolicy($guru);

    expect($policy->jam_masuk)->toBe('07:30:00');
    expect($policy->toleransi_menit)->toBe(10);
});

it('falls back to the yayasan default policy when no lembaga override exists', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_mapel']);

    AttendancePolicy::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null, 'jenis_ptk' => 'guru_mapel', 'jam_masuk' => '07:00', 'toleransi_menit' => 5]);

    $policy = app(AttendancePolicyResolver::class)->resolvePolicy($guru);

    expect($policy->jam_masuk)->toBe('07:00:00');
});

it('returns null when no policy exists for the pegawai category at any scope', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_bk']);

    $policy = app(AttendancePolicyResolver::class)->resolvePolicy($guru);

    expect($policy)->toBeNull();
});

it('resolves a karyawan policy by jenis_karyawan_id independently from jenis_ptk policies', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $jenisKaryawan = JenisKaryawanMaster::factory()->create();
    $karyawan = Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'yayasan_id' => $yayasan->id, 'jenis_karyawan_id' => $jenisKaryawan->id]);

    AttendancePolicy::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_karyawan_id' => $jenisKaryawan->id, 'jam_masuk' => '18:00', 'toleransi_menit' => 10]);

    $policy = app(AttendancePolicyResolver::class)->resolvePolicy($karyawan);

    expect($policy->jam_masuk)->toBe('18:00:00');
});

it('resolveLibur overrides the calendar with the policy hari_kerja when set', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-23')); // Sunday
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $jenisKaryawan = JenisKaryawanMaster::factory()->create();
    $karyawan = Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'yayasan_id' => $yayasan->id, 'jenis_karyawan_id' => $jenisKaryawan->id]);

    AttendancePolicy::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_karyawan_id' => $jenisKaryawan->id,
        'jam_masuk' => '18:00', 'toleransi_menit' => 10, 'hari_kerja' => [0, 1, 2, 3, 4, 5, 6],
    ]);

    $result = app(AttendancePolicyResolver::class)->resolveLibur($karyawan, Carbon::now());

    expect($result)->toBe(['libur' => false, 'alasan' => 'Hari kerja sesuai kebijakan peran']);
    Carbon::setTestNow();
});

it('resolveLibur delegates entirely to the calendar resolver when the policy has no hari_kerja override', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas']);
    AttendancePolicy::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas', 'jam_masuk' => '07:00', 'toleransi_menit' => 0]);

    $result = app(AttendancePolicyResolver::class)->resolveLibur($guru, \Carbon\Carbon::parse('2026-08-23')); // Sunday

    expect($result['libur'])->toBeTrue();
    expect($result['alasan'])->toBe('Libur mingguan SDM');
});

it('resolveLibur delegates entirely to the calendar resolver when the pegawai has no policy at all', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_bk']);

    $result = app(AttendancePolicyResolver::class)->resolveLibur($guru, \Carbon\Carbon::parse('2026-08-19')); // Wednesday

    expect($result)->toBe(['libur' => false, 'alasan' => 'Hari kerja efektif']);
});
```

- [ ] **Step 3: Jalankan test resolver**

Run: `php artisan test tests/Unit/Services/AttendancePolicyResolverTest.php`
Expected: 7 passed, 0 failed.

- [ ] **Step 4: Tulis test regresi tenant-isolation `tests/Feature/Sdm/AttendancePolicyTenantIsolationTest.php`**

```php
<?php
// tests/Feature/Sdm/AttendancePolicyTenantIsolationTest.php

use App\Domains\Sdm\Models\AttendancePolicy;
use App\Domains\Sdm\Services\AttendancePolicyResolver;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;

it('resolver still finds the yayasan-default policy when called while a lembaga-scoped actor is authenticated', function () {
    // Regression guard: AttendancePolicy uses BelongsToTenant, so without an explicit
    // withoutGlobalScope(TenantScope::class), TenantScope would force `lembaga_id =
    // actingUser->lembaga_id` onto resolvePolicy()'s whereNull('lembaga_id') query for the
    // yayasan-default row, making it impossible to ever match for a logged-in scope_level:
    // lembaga actor.
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas']);
    AttendancePolicy::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null, 'jenis_ptk' => 'guru_kelas', 'jam_masuk' => '07:00', 'toleransi_menit' => 0]);

    $role = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $actor = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $actor->assignRole($role);
    $this->actingAs($actor);

    $policy = app(AttendancePolicyResolver::class)->resolvePolicy($guru);

    expect($policy)->not->toBeNull();
    expect($policy->jam_masuk)->toBe('07:00:00');
});
```

- [ ] **Step 5: Jalankan test tenant isolation**

Run: `php artisan test tests/Feature/Sdm/AttendancePolicyTenantIsolationTest.php`
Expected: 1 passed, 0 failed.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Sdm/Services/AttendancePolicyResolver.php tests/Unit/Services/AttendancePolicyResolverTest.php tests/Feature/Sdm/AttendancePolicyTenantIsolationTest.php
git commit -m "feat(sdm): tambah AttendancePolicyResolver (membungkus KalenderKerjaSdmResolver tanpa mengubahnya)"
```

---

## Task 4: Ganti Sumber Resolusi Libur di `RecordManualAttendanceAction`/`ScanQrAttendanceAction`

**Files:**
- Modify: `app/Domains/Sdm/Actions/RecordManualAttendanceAction.php`
- Modify: `app/Domains/Sdm/Actions/ScanQrAttendanceAction.php`
- Test: `tests/Feature/Sdm/RecordManualAttendanceActionTest.php`
- Test: `tests/Feature/Sdm/ScanQrAttendanceActionTest.php`

**Interfaces:**
- Consumes: `AttendancePolicyResolver` (Task 3).
- Produces: kedua Action TETAP melempar `AttendanceOnHolidayException` yang SAMA (Sub-project 2), cuma sumbernya kini lewat Policy dulu baru kalender.

- [ ] **Step 1: Ganti dependency di `RecordManualAttendanceAction`**

Baca dulu isi file saat ini untuk pastikan baseline cocok. Ganti SELURUH isinya jadi:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions;

use App\Domains\Sdm\DataTransferObjects\RecordManualAttendanceData;
use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Domains\Sdm\Exceptions\AttendanceOnHolidayException;
use App\Domains\Sdm\Models\AttendanceEvent;
use App\Domains\Sdm\Services\AttendancePolicyResolver;
use App\Domains\Sdm\Services\AttendanceRecordAggregator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class RecordManualAttendanceAction
{
    public function __construct(
        private readonly AttendanceRecordAggregator $aggregator,
        private readonly AttendancePolicyResolver $policyResolver,
    ) {}

    public function execute(Model $pegawai, RecordManualAttendanceData $data): AttendanceEvent
    {
        $resolusi = $this->policyResolver->resolveLibur($pegawai, $data->waktu);

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

- [ ] **Step 2: Ganti dependency di `ScanQrAttendanceAction`**

Ganti SELURUH isinya jadi:

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
use App\Domains\Sdm\Services\AttendancePolicyResolver;
use App\Domains\Sdm\Services\AttendanceRecordAggregator;
use Illuminate\Support\Facades\DB;

final class ScanQrAttendanceAction
{
    public function __construct(
        private readonly AttendanceRecordAggregator $aggregator,
        private readonly AttendancePolicyResolver $policyResolver,
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

        $resolusi = $this->policyResolver->resolveLibur($pegawai, now());

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

- [ ] **Step 3: Jalankan ulang SEMUA test existing kedua Action (dari Sub-project 1 & 2) untuk pastikan tidak regresi**

Run: `php artisan test tests/Feature/Sdm/RecordManualAttendanceActionTest.php tests/Feature/Sdm/ScanQrAttendanceActionTest.php`
Expected: SEMUA test lama (5 di `RecordManualAttendanceActionTest`, 4 di `ScanQrAttendanceActionTest`) tetap passed, 0 failed — TIDAK ADA perubahan perilaku untuk pegawai TANPA Policy (delegasi penuh ke `KalenderKerjaSdmResolver` di `AttendancePolicyResolver::resolveLibur()` menjamin ini).

- [ ] **Step 4: Tambah 1 test baru di akhir `tests/Feature/Sdm/RecordManualAttendanceActionTest.php` membuktikan Policy override memengaruhi hasil**

```php
it('allows a manual attendance record on a lembaga-libur day when the pegawai category has a policy hari_kerja override', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $jenisKaryawan = \App\Models\JenisKaryawanMaster::factory()->create();
    $karyawan = \App\Models\Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'yayasan_id' => $yayasan->id, 'jenis_karyawan_id' => $jenisKaryawan->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    \App\Domains\Sdm\Models\AttendancePolicy::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_karyawan_id' => $jenisKaryawan->id,
        'jam_masuk' => '18:00', 'toleransi_menit' => 10, 'hari_kerja' => [0, 1, 2, 3, 4, 5, 6],
    ]);
    $action = app(\App\Domains\Sdm\Actions\RecordManualAttendanceAction::class);

    $event = $action->execute($karyawan, new RecordManualAttendanceData(
        lembagaId: $lembaga->id, arah: 'masuk', status: AttendanceStatus::Hadir,
        waktu: CarbonImmutable::parse('2026-08-23 18:05:00'), dicatatOlehUserId: $admin->id, // Sunday, but policy overrides it as a work day
    ));

    expect($event->arah)->toBe('masuk');
    expect(AttendanceRecord::where('pegawai_type', \App\Models\Karyawan::class)->where('pegawai_id', $karyawan->id)->exists())->toBeTrue();
});
```

- [ ] **Step 5: Jalankan lagi seluruh file test Action manual**

Run: `php artisan test tests/Feature/Sdm/RecordManualAttendanceActionTest.php`
Expected: 6 passed, 0 failed.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Sdm/Actions/RecordManualAttendanceAction.php app/Domains/Sdm/Actions/ScanQrAttendanceAction.php tests/Feature/Sdm/RecordManualAttendanceActionTest.php
git commit -m "feat(sdm): ganti sumber resolusi hari libur di RecordManualAttendanceAction/ScanQrAttendanceAction ke AttendancePolicyResolver"
```

---

## Task 5: Deteksi `is_late`/`late_minutes` di `AttendanceRecordAggregator`

**Files:**
- Modify: `app/Domains/Sdm/Services/AttendanceRecordAggregator.php`
- Test: `tests/Feature/Sdm/AttendanceRecordAggregatorLateDetectionTest.php`

**Interfaces:**
- Consumes: `AttendancePolicyResolver` (Task 3).
- Produces: `AttendanceRecordAggregator::sync()` (signature TIDAK berubah) sekarang juga mengisi `is_late`/`late_minutes` di `AttendanceRecord` yang dihasilkan.

- [ ] **Step 1: Modifikasi `AttendanceRecordAggregator`**

Ganti SELURUH isi file jadi:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Services;

use App\Domains\Sdm\Enums\AttendanceStatus;
use App\Domains\Sdm\Models\AttendanceRecord;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecordAggregator
{
    public function __construct(private readonly AttendancePolicyResolver $policyResolver) {}

    public function sync(Model $pegawai, CarbonImmutable $tanggal): AttendanceRecord
    {
        $events = $pegawai->attendanceEvents()
            ->whereDate('waktu', $tanggal->toDateString())
            ->orderBy('waktu')
            ->get();

        $waktuMasuk = $events->firstWhere('arah', 'masuk')?->waktu;
        $waktuPulang = $events->where('arah', 'pulang')->last()?->waktu;

        $statusOverride = $events->first(fn ($event) => $event->status !== AttendanceStatus::Hadir);
        $status = $statusOverride?->status ?? AttendanceStatus::Hadir;

        [$isLate, $lateMinutes] = $this->hitungKeterlambatan($pegawai, $tanggal, $waktuMasuk);

        return AttendanceRecord::updateOrCreate(
            [
                'pegawai_type' => $pegawai::class,
                'pegawai_id' => $pegawai->id,
                'tanggal' => $tanggal->toDateString(),
            ],
            [
                'lembaga_id' => $pegawai->lembaga_id,
                'status' => $status,
                'waktu_masuk' => $waktuMasuk,
                'waktu_pulang' => $waktuPulang,
                'is_late' => $isLate,
                'late_minutes' => $lateMinutes,
            ]
        );
    }

    /**
     * @return array{0: bool, 1: int|null}
     */
    private function hitungKeterlambatan(Model $pegawai, CarbonImmutable $tanggal, ?CarbonImmutable $waktuMasuk): array
    {
        if (! $waktuMasuk) {
            return [false, null];
        }

        $policy = $this->policyResolver->resolvePolicy($pegawai);

        if (! $policy) {
            return [false, null];
        }

        $batasWaktu = CarbonImmutable::parse($tanggal->toDateString().' '.$policy->jam_masuk)->addMinutes($policy->toleransi_menit);

        if ($waktuMasuk->lessThanOrEqualTo($batasWaktu)) {
            return [false, 0];
        }

        return [true, $batasWaktu->diffInMinutes($waktuMasuk)];
    }
}
```

Catatan: kalau pegawai punya Policy tapi TIDAK terlambat, `late_minutes` diisi `0` (bukan `null`) — `null` KHUSUS dipakai untuk "tidak ada Policy sama sekali" atau "tidak ada event masuk", supaya di UI/laporan nanti bisa dibedakan "tepat waktu" (`0`) dari "tidak diketahui" (`null`).

- [ ] **Step 2: Jalankan ulang SEMUA test existing yang menyentuh Aggregator (tidak langsung, tapi lewat Action) untuk pastikan tidak regresi**

Run: `php artisan test tests/Feature/Sdm/RecordManualAttendanceActionTest.php tests/Feature/Sdm/ScanQrAttendanceActionTest.php tests/Feature/Sdm/TandaiAlpaOtomatisSdmTest.php`
Expected: semua tetap passed seperti sebelumnya (pegawai tanpa Policy → `is_late=false`, `late_minutes=null`, tidak mengubah assertion lama manapun karena test lama tidak mengecek kolom itu).

- [ ] **Step 3: Tulis test baru**

```php
<?php
// tests/Feature/Sdm/AttendanceRecordAggregatorLateDetectionTest.php

use App\Domains\Sdm\Actions\RecordManualAttendanceAction;
use App\Domains\Sdm\DataTransferObjects\RecordManualAttendanceData;
use App\Domains\Sdm\Enums\AttendanceStatus;
use App\Domains\Sdm\Models\AttendancePolicy;
use App\Domains\Sdm\Models\AttendanceRecord;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Carbon\CarbonImmutable;

it('marks is_late true with correct late_minutes when arriving after jam_masuk plus toleransi', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas']);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    AttendancePolicy::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas', 'jam_masuk' => '07:00', 'toleransi_menit' => 15]);

    app(RecordManualAttendanceAction::class)->execute($guru, new RecordManualAttendanceData(
        lembagaId: $lembaga->id, arah: 'masuk', status: AttendanceStatus::Hadir,
        waktu: CarbonImmutable::parse('2026-08-24 07:20:00'), dicatatOlehUserId: $admin->id, // Monday
    ));

    $record = AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->first();
    expect($record->is_late)->toBeTrue();
    expect($record->late_minutes)->toBe(5);
});

it('marks is_late false when arriving within the toleransi window', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas']);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    AttendancePolicy::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas', 'jam_masuk' => '07:00', 'toleransi_menit' => 15]);

    app(RecordManualAttendanceAction::class)->execute($guru, new RecordManualAttendanceData(
        lembagaId: $lembaga->id, arah: 'masuk', status: AttendanceStatus::Hadir,
        waktu: CarbonImmutable::parse('2026-08-24 07:10:00'), dicatatOlehUserId: $admin->id, // Monday
    ));

    $record = AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->first();
    expect($record->is_late)->toBeFalse();
    expect($record->late_minutes)->toBe(0);
});

it('leaves is_late false and late_minutes null when the pegawai has no policy at all', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_bk']);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);

    app(RecordManualAttendanceAction::class)->execute($guru, new RecordManualAttendanceData(
        lembagaId: $lembaga->id, arah: 'masuk', status: AttendanceStatus::Hadir,
        waktu: CarbonImmutable::parse('2026-08-24 10:00:00'), dicatatOlehUserId: $admin->id, // Monday, clearly "late" by any normal standard
    ));

    $record = AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->first();
    expect($record->is_late)->toBeFalse();
    expect($record->late_minutes)->toBeNull();
});
```

- [ ] **Step 4: Jalankan test baru**

Run: `php artisan test tests/Feature/Sdm/AttendanceRecordAggregatorLateDetectionTest.php`
Expected: 3 passed, 0 failed.

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Sdm/Services/AttendanceRecordAggregator.php tests/Feature/Sdm/AttendanceRecordAggregatorLateDetectionTest.php
git commit -m "feat(sdm): hitung is_late/late_minutes di AttendanceRecordAggregator berdasar AttendancePolicy"
```

---

## Task 6: `TandaiAlpaOtomatisSdm` Sadar Policy-Override

**Files:**
- Modify: `app/Console/Commands/TandaiAlpaOtomatisSdm.php`
- Test: `tests/Feature/Sdm/TandaiAlpaOtomatisSdmTest.php`

**Interfaces:**
- Consumes: `AttendancePolicyResolver` (Task 3).
- Produces: command menandai Alpa per-pegawai berdasar `AttendancePolicyResolver::resolveLibur()` (bukan lagi cek level-lembaga) — menangani DUA arah celah: pegawai ber-Policy-override tetap ditandai kerja walau lembaga libur (mis. satpam), MAUPUN pegawai ber-Policy-override yang harusnya libur walau lembaga kerja (mis. kategori part-time) tidak ditandai Alpa.

- [ ] **Step 1: Modifikasi `TandaiAlpaOtomatisSdm`**

**PENTING — celah dua arah, bukan cuma satu**: Policy override hari kerja bisa membuat pegawai TETAP kerja di hari lembaga libur (mis. satpam kerja 7 hari — kasus yang mendasari sub-project ini), TAPI SEBALIKNYA JUGA BISA TERJADI: pegawai yang kategorinya cuma kerja sebagian hari (mis. Policy `hari_kerja: [1,2,3]`, Senin-Rabu saja) di lembaga yang kalendernya Senin-Jumat kerja — kalau command HANYA mengecek Policy saat lembaga libur (satu arah saja), pegawai part-time ini akan SALAH ditandai Alpa di hari Kamis/Jumat padahal menurut Policy-nya dia memang tidak seharusnya kerja hari itu.

Solusi: JANGAN cabang dua-jalur (fast-skip lembaga dulu, baru kondisional cek Policy). Sebagai gantinya, panggil `AttendancePolicyResolver::resolveLibur($pegawai, $tanggal)` untuk SETIAP pegawai TANPA KECUALI — method itu SUDAH otomatis delegasikan ke `KalenderKerjaSdmResolver` kalau pegawai itu tidak punya Policy override (lihat Task 3), jadi perilaku pegawai TANPA Policy tetap 100% sama seperti sebelumnya. Ini menghilangkan optimasi "skip seluruh lembaga sekali cek" dari versi sebelumnya, tapi jumlah pegawai per lembaga kecil (puluhan), jadi biaya query tambahan ini diabaikan demi kebenaran.

Ganti SELURUH isi file jadi:

```php
<?php

namespace App\Console\Commands;

use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Domains\Sdm\Enums\AttendanceStatus;
use App\Domains\Sdm\Models\AttendanceRecord;
use App\Domains\Sdm\Services\AttendancePolicyResolver;
use App\Domains\Sdm\Services\AttendanceRecordAggregator;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class TandaiAlpaOtomatisSdm extends Command
{
    protected $signature = 'sdm:tandai-alpa-otomatis';

    protected $description = 'Tandai pegawai aktif sebagai Alpa untuk hari kerja kemarin (H-1) yang sama sekali tidak punya AttendanceRecord';

    public function __construct(
        private readonly AttendancePolicyResolver $policyResolver,
        private readonly AttendanceRecordAggregator $aggregator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tanggal = now()->subDay()->toImmutable();
        $jumlahDitandai = 0;

        foreach (Lembaga::all() as $lembaga) {
            $pegawaiList = collect()
                ->concat(Guru::where('lembaga_id', $lembaga->id)->where('status_aktif', 'aktif')->get())
                ->concat(Karyawan::where('lembaga_id', $lembaga->id)->where('status_aktif', 'aktif')->get())
                // Cek PER PEGAWAI, bukan per lembaga — resolveLibur() otomatis delegasikan ke
                // KalenderKerjaSdmResolver kalau pegawai itu tidak punya Policy override, jadi
                // perilaku pegawai tanpa Policy tetap identik Sub-project 2. Ini menangani DUA
                // arah celah: pegawai yang Policy-nya menambah hari kerja (satpam 7 hari) MAUPUN
                // yang menguranginya (kategori part-time) terhadap kalender lembaga.
                ->filter(fn ($pegawai) => ! $this->policyResolver->resolveLibur($pegawai, $tanggal)['libur']);

            $jumlahDitandai += $this->tandaiPegawaiTanpaRecord($pegawaiList, $lembaga, $tanggal);
        }

        $this->info("{$jumlahDitandai} pegawai ditandai Alpa otomatis untuk tanggal {$tanggal->toDateString()}.");

        return self::SUCCESS;
    }

    private function tandaiPegawaiTanpaRecord(Collection $pegawaiList, Lembaga $lembaga, \Carbon\CarbonImmutable $tanggal): int
    {
        $jumlah = 0;

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
            $jumlah++;
        }

        return $jumlah;
    }
}
```

- [ ] **Step 2: Jalankan ulang SEMUA 5 test existing dari Sub-project 2 untuk pastikan tidak regresi**

Run: `php artisan test tests/Feature/Sdm/TandaiAlpaOtomatisSdmTest.php`
Expected: 5 passed, 0 failed (perilaku pegawai TANPA Policy override sama sekali tidak berubah).

- [ ] **Step 3: Tambah 2 test baru di akhir `tests/Feature/Sdm/TandaiAlpaOtomatisSdmTest.php`**

```php
it('marks a karyawan with a policy hari_kerja override as Alpa even on a lembaga-libur day', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-24 01:00:00')); // Monday, so H-1 = Sunday (lembaga libur)
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $jenisKaryawan = \App\Models\JenisKaryawanMaster::factory()->create();
    $karyawan = Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'yayasan_id' => $yayasan->id, 'jenis_karyawan_id' => $jenisKaryawan->id, 'status_aktif' => 'aktif']);
    \App\Domains\Sdm\Models\AttendancePolicy::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_karyawan_id' => $jenisKaryawan->id,
        'jam_masuk' => '18:00', 'toleransi_menit' => 10, 'hari_kerja' => [0, 1, 2, 3, 4, 5, 6],
    ]);

    $this->artisan('sdm:tandai-alpa-otomatis')->assertSuccessful();

    $record = AttendanceRecord::where('pegawai_type', Karyawan::class)->where('pegawai_id', $karyawan->id)->first();
    expect($record)->not->toBeNull();
    expect($record->status)->toBe(AttendanceStatus::Alpa);

    Carbon::setTestNow();
});

it('still skips a guru with no policy override on a lembaga-libur day, alongside a policy-overridden karyawan in the same lembaga', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-24 01:00:00')); // Monday, H-1 = Sunday (lembaga libur)
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas', 'status_aktif' => 'aktif']);
    $jenisKaryawan = \App\Models\JenisKaryawanMaster::factory()->create();
    $karyawan = Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'yayasan_id' => $yayasan->id, 'jenis_karyawan_id' => $jenisKaryawan->id, 'status_aktif' => 'aktif']);
    \App\Domains\Sdm\Models\AttendancePolicy::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_karyawan_id' => $jenisKaryawan->id,
        'jam_masuk' => '18:00', 'toleransi_menit' => 10, 'hari_kerja' => [0, 1, 2, 3, 4, 5, 6],
    ]);

    $this->artisan('sdm:tandai-alpa-otomatis')->assertSuccessful();

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeFalse();
    expect(AttendanceRecord::where('pegawai_type', Karyawan::class)->where('pegawai_id', $karyawan->id)->exists())->toBeTrue();

    Carbon::setTestNow();
});

it('does NOT mark a karyawan as Alpa on a lembaga work day when the policy hari_kerja override excludes that day (reverse direction of the shift gap)', function () {
    // Regression guard for the OTHER direction of the celah: a part-time-style category
    // (Policy hari_kerja narrower than the lembaga's default work week) must NOT be wrongly
    // marked Alpa on a day the lembaga calendar says is a work day but the pegawai's own
    // Policy says is not.
    Carbon::setTestNow(Carbon::parse('2026-08-21 01:00:00')); // Friday, so H-1 = Thursday (lembaga work day)
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]); // Mon-Sat is lembaga work days
    $jenisKaryawan = \App\Models\JenisKaryawanMaster::factory()->create();
    $karyawan = Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'yayasan_id' => $yayasan->id, 'jenis_karyawan_id' => $jenisKaryawan->id, 'status_aktif' => 'aktif']);
    \App\Domains\Sdm\Models\AttendancePolicy::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_karyawan_id' => $jenisKaryawan->id,
        'jam_masuk' => '08:00', 'toleransi_menit' => 10, 'hari_kerja' => [1, 2, 3], // Only Mon-Wed for this category
    ]);

    $this->artisan('sdm:tandai-alpa-otomatis')->assertSuccessful();

    expect(AttendanceRecord::where('pegawai_type', Karyawan::class)->where('pegawai_id', $karyawan->id)->exists())->toBeFalse();

    Carbon::setTestNow();
});
```

- [ ] **Step 4: Jalankan seluruh file test (5 lama + 3 baru)**

Run: `php artisan test tests/Feature/Sdm/TandaiAlpaOtomatisSdmTest.php`
Expected: 8 passed, 0 failed.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/TandaiAlpaOtomatisSdm.php tests/Feature/Sdm/TandaiAlpaOtomatisSdmTest.php
git commit -m "feat(sdm): TandaiAlpaOtomatisSdm sadar Policy override hari kerja di lembaga yang kalendernya libur"
```

---

## Task 7: Endpoint CRUD Attendance Policy di `AttendanceConfigurationController` + Routes

**Files:**
- Modify: `app/Http/Controllers/Admin/AttendanceConfigurationController.php`
- Modify: `routes/admin/kehadiran-sdm.php`
- Test: `tests/Feature/Admin/AttendancePolicyControllerTest.php`

**Interfaces:**
- Consumes: `AttendancePolicy` (Task 2).
- Produces: route `admin.kehadiran-sdm.policy.store`/`.update`/`.destroy`; `index()` diperluas dengan `policyList`, `jenisPtkOptions`, `jenisKaryawanList` — dipakai Task 8 (view).

- [ ] **Step 1: Perluas `index()` dan tambah 3 method baru**

Baca dulu isi file saat ini untuk pastikan baseline cocok (harus sudah punya `updateHariKerja`, `storeKalenderEntri`, dst dari Sub-project 2). Tambahkan `use` statement baru di bagian atas file:

```php
use App\Domains\Sdm\Models\AttendancePolicy;
use App\Models\JenisKaryawanMaster;
```

Cari blok `index()` yang sudah ada, TAMBAHKAN query `policyList` dan 2 array opsi SEBELUM `return view(...)`, lalu tambahkan ke array data view. Cari baris:

```php
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
```

Ganti jadi:

```php
        $policyList = $yayasanId ? AttendancePolicy::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $yayasanId)
            ->where(function ($query) use ($lembagaId) {
                $query->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            })
            ->with('jenisKaryawan')
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
            'policyList' => $policyList,
            'jenisPtkOptions' => self::JENIS_PTK_OPTIONS,
            'jenisKaryawanList' => JenisKaryawanMaster::orderBy('nama')->get(),
        ]);
```

Tambahkan konstanta baru di AWAL class, SEBELUM method `index()`:

```php
    private const JENIS_PTK_OPTIONS = [
        'guru_kelas' => 'Guru Kelas',
        'guru_mapel' => 'Guru Mapel',
        'kepala_sekolah' => 'Kepala Sekolah',
        'tenaga_administrasi' => 'Tenaga Administrasi',
        'guru_bk' => 'Guru BK',
    ];
```

Tambahkan 3 method baru SETELAH `kalenderSalin()`, SEBELUM `resolveLembagaId()`:

```php
    public function storePolicy(Request $request): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        $data = $request->validate([
            'kategori_tipe' => ['required', 'in:guru,karyawan'],
            'jenis_ptk' => ['required_if:kategori_tipe,guru', 'nullable', 'in:guru_kelas,guru_mapel,kepala_sekolah,tenaga_administrasi,guru_bk'],
            'jenis_karyawan_id' => ['required_if:kategori_tipe,karyawan', 'nullable', 'integer', 'exists:jenis_karyawan_master,id'],
            'jam_masuk' => ['required', 'date_format:H:i'],
            'jam_pulang' => ['nullable', 'date_format:H:i'],
            'toleransi_menit' => ['required', 'integer', 'min:0'],
            'hari_kerja' => ['nullable', 'array'],
            'hari_kerja.*' => ['integer', 'between:0,6'],
            'is_nasional' => ['nullable', 'boolean'],
        ]);

        $isNasional = (bool) ($data['is_nasional'] ?? false);

        if ($isNasional && $request->user()->widestScopeLevel() !== 'yayasan') {
            abort(403, 'Hanya aktor berscope yayasan yang boleh membuat Attendance Policy nasional.');
        }

        $lembagaId = $isNasional ? null : $this->resolveLembagaId($request);

        if (! $isNasional && $lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah Attendance Policy.']);
        }

        $yayasanId = $this->resolveYayasanId($request, $lembagaId);
        $jenisPtk = $data['kategori_tipe'] === 'guru' ? $data['jenis_ptk'] : null;
        $jenisKaryawanId = $data['kategori_tipe'] === 'karyawan' ? $data['jenis_karyawan_id'] : null;

        $sudahAda = AttendancePolicy::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $yayasanId)
            ->where('lembaga_id', $lembagaId)
            ->where('jenis_ptk', $jenisPtk)
            ->where('jenis_karyawan_id', $jenisKaryawanId)
            ->exists();

        if ($sudahAda) {
            return back()->withErrors(['kategori_tipe' => 'Attendance Policy untuk kategori dan scope ini sudah ada. Edit yang sudah ada, jangan buat baru.']);
        }

        AttendancePolicy::create([
            'yayasan_id' => $yayasanId,
            'lembaga_id' => $lembagaId,
            'jenis_ptk' => $jenisPtk,
            'jenis_karyawan_id' => $jenisKaryawanId,
            'jam_masuk' => $data['jam_masuk'],
            'jam_pulang' => $data['jam_pulang'] ?? null,
            'toleransi_menit' => $data['toleransi_menit'],
            'hari_kerja' => $data['hari_kerja'] ?? null,
        ]);

        return back()->with('status', 'Attendance Policy berhasil ditambahkan.');
    }

    public function updatePolicy(Request $request, AttendancePolicy $policy): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        if ($policy->lembaga_id === null && $request->user()->widestScopeLevel() !== 'yayasan') {
            abort(403, 'Hanya aktor berscope yayasan yang boleh mengubah Attendance Policy nasional.');
        }

        $data = $request->validate([
            'jam_masuk' => ['required', 'date_format:H:i'],
            'jam_pulang' => ['nullable', 'date_format:H:i'],
            'toleransi_menit' => ['required', 'integer', 'min:0'],
            'hari_kerja' => ['nullable', 'array'],
            'hari_kerja.*' => ['integer', 'between:0,6'],
        ]);

        $policy->update([
            'jam_masuk' => $data['jam_masuk'],
            'jam_pulang' => $data['jam_pulang'] ?? null,
            'toleransi_menit' => $data['toleransi_menit'],
            'hari_kerja' => $data['hari_kerja'] ?? null,
        ]);

        return back()->with('status', 'Attendance Policy berhasil diperbarui.');
    }

    public function destroyPolicy(Request $request, AttendancePolicy $policy): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        if ($policy->lembaga_id === null && $request->user()->widestScopeLevel() !== 'yayasan') {
            abort(403, 'Hanya aktor berscope yayasan yang boleh menghapus Attendance Policy nasional.');
        }

        $policy->delete();

        return back()->with('status', 'Attendance Policy berhasil dihapus.');
    }
```

Catatan penting: `updatePolicy()` SENGAJA TIDAK mengizinkan ubah `kategori_tipe`/`jenis_ptk`/`jenis_karyawan_id` (kategori target Policy tetap, cuma jam/toleransi/hari_kerja yang bisa diedit) — kalau kategorinya mau diganti, hapus lalu buat baru. Ini konsisten dengan pola `updateKalenderEntri` Sub-project 2 yang juga tidak mengizinkan ubah `is_nasional` setelah dibuat.

- [ ] **Step 2: Tambah 3 route baru ke `routes/admin/kehadiran-sdm.php`**

Cari baris terakhir file (route `kehadiran-sdm.kalender.salin`), tambahkan setelahnya:

```php
Route::post('kehadiran-sdm/konfigurasi/policy', [AttendanceConfigurationController::class, 'storePolicy'])->name('kehadiran-sdm.policy.store');
Route::put('kehadiran-sdm/konfigurasi/policy/{policy}', [AttendanceConfigurationController::class, 'updatePolicy'])->name('kehadiran-sdm.policy.update');
Route::delete('kehadiran-sdm/konfigurasi/policy/{policy}', [AttendanceConfigurationController::class, 'destroyPolicy'])->name('kehadiran-sdm.policy.destroy');
```

- [ ] **Step 3: Tulis test**

```php
<?php
// tests/Feature/Admin/AttendancePolicyControllerTest.php

use App\Domains\Sdm\Models\AttendancePolicy;
use App\Models\JenisKaryawanMaster;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

if (! function_exists('actingAsAdminSdmPolicy')) {
    function actingAsAdminSdmPolicy(Lembaga $lembaga): User
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

it('lets an admin_sdm create a lembaga-scoped policy for a jenis_ptk category', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = actingAsAdminSdmPolicy($lembaga);

    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.policy.store'), [
        'kategori_tipe' => 'guru', 'jenis_ptk' => 'guru_kelas', 'jam_masuk' => '07:00', 'toleransi_menit' => 15,
    ])->assertRedirect();

    expect(AttendancePolicy::where('lembaga_id', $lembaga->id)->where('jenis_ptk', 'guru_kelas')->exists())->toBeTrue();
});

it('lets an admin_sdm create a lembaga-scoped policy for a jenis_karyawan_id category with hari_kerja override', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $jenisKaryawan = JenisKaryawanMaster::factory()->create();
    $admin = actingAsAdminSdmPolicy($lembaga);

    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.policy.store'), [
        'kategori_tipe' => 'karyawan', 'jenis_karyawan_id' => $jenisKaryawan->id,
        'jam_masuk' => '18:00', 'toleransi_menit' => 10, 'hari_kerja' => [0, 1, 2, 3, 4, 5, 6],
    ])->assertRedirect();

    $policy = AttendancePolicy::where('lembaga_id', $lembaga->id)->where('jenis_karyawan_id', $jenisKaryawan->id)->first();
    expect($policy)->not->toBeNull();
    expect($policy->hari_kerja)->toBe([0, 1, 2, 3, 4, 5, 6]);
});

it('rejects creating a duplicate policy for the same category and scope', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = actingAsAdminSdmPolicy($lembaga);
    AttendancePolicy::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas', 'jam_masuk' => '07:00', 'toleransi_menit' => 0]);

    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.policy.store'), [
        'kategori_tipe' => 'guru', 'jenis_ptk' => 'guru_kelas', 'jam_masuk' => '07:30', 'toleransi_menit' => 5,
    ])->assertSessionHasErrors('kategori_tipe');

    expect(AttendancePolicy::where('lembaga_id', $lembaga->id)->where('jenis_ptk', 'guru_kelas')->count())->toBe(1);
});

it('rejects an admin_sdm (scope_level lembaga) trying to create a national policy', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = actingAsAdminSdmPolicy($lembaga);

    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.policy.store'), [
        'kategori_tipe' => 'guru', 'jenis_ptk' => 'guru_kelas', 'jam_masuk' => '07:00', 'toleransi_menit' => 0, 'is_nasional' => true,
    ])->assertForbidden();

    expect(AttendancePolicy::whereNull('lembaga_id')->where('jenis_ptk', 'guru_kelas')->exists())->toBeFalse();
});

it('lets an admin_sdm update the jam_masuk and toleransi of an existing policy without changing its category', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = actingAsAdminSdmPolicy($lembaga);
    $policy = AttendancePolicy::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas', 'jam_masuk' => '07:00', 'toleransi_menit' => 0]);

    $this->actingAs($admin)->put(route('admin.kehadiran-sdm.policy.update', $policy), [
        'jam_masuk' => '07:30', 'toleransi_menit' => 20,
    ])->assertRedirect();

    $policy->refresh();
    expect($policy->jam_masuk)->toBe('07:30:00');
    expect($policy->toleransi_menit)->toBe(20);
    expect($policy->jenis_ptk)->toBe('guru_kelas');
});

it('rejects an admin without kehadiran-sdm.kelola-konfigurasi permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $noPermissionUser = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($noPermissionUser)->post(route('admin.kehadiran-sdm.policy.store'), [
        'kategori_tipe' => 'guru', 'jenis_ptk' => 'guru_kelas', 'jam_masuk' => '07:00', 'toleransi_menit' => 0,
    ])->assertForbidden();
});
```

- [ ] **Step 4: Jalankan test**

Run: `php artisan test tests/Feature/Admin/AttendancePolicyControllerTest.php`
Expected: 6 passed, 0 failed.

- [ ] **Step 5: Jalankan ulang test controller Sub-project 1/2 yang berpotensi tersentuh perubahan `index()`**

Run: `php artisan test tests/Feature/Admin/AttendanceConfigurationControllerTest.php tests/Feature/Admin/AttendanceConfigurationKalenderControllerTest.php`
Expected: semua tetap passed seperti sebelumnya (4 + 6), tidak ada regresi.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/AttendanceConfigurationController.php routes/admin/kehadiran-sdm.php tests/Feature/Admin/AttendancePolicyControllerTest.php
git commit -m "feat(sdm): tambah endpoint CRUD Attendance Policy di AttendanceConfigurationController"
```

---

## Task 8: View — Tab "Attendance Policy"

**Files:**
- Modify: `resources/views/admin/kehadiran-sdm/konfigurasi.blade.php`
- Test: `tests/Feature/Admin/AttendancePolicyViewTest.php`

**Interfaces:**
- Consumes: semua endpoint Task 7.

- [ ] **Step 1: Tambah tab ke-3 di `konfigurasi.blade.php`**

Baca dulu isi file saat ini (3 tab jadi tujuan akhir: Metode & Titik Absen, Kalender Kerja, Attendance Policy). Cari baris tombol tab yang sudah ada:

```blade
        <div class="flex items-center gap-1 border-b border-gray-200">
            <button type="button" @click="tab = 'metode'" :class="tab === 'metode' ? 'border-b-2 border-brand-500 text-brand-600' : 'text-gray-500 hover:text-gray-700'" class="rounded-t-lg px-4 py-2.5 text-sm font-semibold transition">Metode &amp; Titik Absen</button>
            <button type="button" @click="tab = 'kalender'" :class="tab === 'kalender' ? 'border-b-2 border-brand-500 text-brand-600' : 'text-gray-500 hover:text-gray-700'" class="rounded-t-lg px-4 py-2.5 text-sm font-semibold transition">Kalender Kerja</button>
        </div>
```

Ganti jadi (tambah tombol tab ke-3):

```blade
        <div class="flex items-center gap-1 border-b border-gray-200">
            <button type="button" @click="tab = 'metode'" :class="tab === 'metode' ? 'border-b-2 border-brand-500 text-brand-600' : 'text-gray-500 hover:text-gray-700'" class="rounded-t-lg px-4 py-2.5 text-sm font-semibold transition">Metode &amp; Titik Absen</button>
            <button type="button" @click="tab = 'kalender'" :class="tab === 'kalender' ? 'border-b-2 border-brand-500 text-brand-600' : 'text-gray-500 hover:text-gray-700'" class="rounded-t-lg px-4 py-2.5 text-sm font-semibold transition">Kalender Kerja</button>
            <button type="button" @click="tab = 'policy'" :class="tab === 'policy' ? 'border-b-2 border-brand-500 text-brand-600' : 'text-gray-500 hover:text-gray-700'" class="rounded-t-lg px-4 py-2.5 text-sm font-semibold transition">Attendance Policy</button>
        </div>
```

Tambahkan state Alpine baru ke `x-data` di root div. Cari baris:

```blade
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
```

Ganti jadi (tambah state modal Policy):

```blade
        showSalinModal: false,
        entriTersedia: [],
        entriTercentang: [],
        async bukaSalinModal() {
            const response = await fetch(@js(route('admin.kehadiran-sdm.kalender.salin-tersedia')), { headers: { Accept: 'application/json' } });
            const json = await response.json();
            this.entriTersedia = json.items ?? [];
            this.entriTercentang = [];
            this.showSalinModal = true;
        },
        showPolicyModal: false,
        editingPolicy: null,
        formPolicy: { kategori_tipe: 'guru', jenis_ptk: 'guru_kelas', jenis_karyawan_id: '', jam_masuk: '07:00', jam_pulang: '', toleransi_menit: 0, hari_kerja: [], overrideHariKerja: false, is_nasional: false },
        openPolicyModal(policy = null, nasional = false) {
            this.editingPolicy = policy;
            this.formPolicy = policy
                ? { kategori_tipe: policy.jenis_ptk ? 'guru' : 'karyawan', jenis_ptk: policy.jenis_ptk ?? 'guru_kelas', jenis_karyawan_id: policy.jenis_karyawan_id ?? '', jam_masuk: policy.jam_masuk.slice(0, 5), jam_pulang: policy.jam_pulang ? policy.jam_pulang.slice(0, 5) : '', toleransi_menit: policy.toleransi_menit, hari_kerja: policy.hari_kerja ?? [], overrideHariKerja: policy.hari_kerja !== null, is_nasional: policy.lembaga_id === null }
                : { kategori_tipe: 'guru', jenis_ptk: 'guru_kelas', jenis_karyawan_id: '', jam_masuk: '07:00', jam_pulang: '', toleransi_menit: 0, hari_kerja: [], overrideHariKerja: false, is_nasional: nasional };
            this.showPolicyModal = true;
        },
        togglePolicyHari(day) {
            this.formPolicy.hari_kerja = this.formPolicy.hari_kerja.includes(day) ? this.formPolicy.hari_kerja.filter((d) => d !== day) : [...this.formPolicy.hari_kerja, day];
        }
    }">
```

Tambahkan blok tab ke-3, SETELAH `</div>` penutup blok "Tab: Kalender Kerja" dan SEBELUM komentar `{{-- Modal Titik Absen (sudah ada dari Sub-project 1) --}}`:

```blade
        {{-- Tab: Attendance Policy --}}
        <div x-show="tab === 'policy'" x-cloak class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="font-display text-sm font-bold text-gray-900">Attendance Policy</h2>
                        <p class="mt-1 text-xs text-gray-500">Jam kerja &amp; toleransi keterlambatan per kategori pegawai. Tanpa Policy, pegawai tidak pernah ditandai terlambat.</p>
                    </div>
                    @can('kehadiran-sdm.kelola-konfigurasi')
                        <x-primary-button type="button" @click="openPolicyModal(null, false)">+ Tambah Policy</x-primary-button>
                    @endcan
                </div>

                <div class="mt-4 divide-y divide-gray-100">
                    @forelse ($policyList as $policy)
                        <div class="flex items-center justify-between py-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">
                                    {{ $policy->jenis_ptk ? ($jenisPtkOptions[$policy->jenis_ptk] ?? $policy->jenis_ptk) : ($policy->jenisKaryawan->nama ?? '—') }}
                                    @if ($policy->lembaga_id === null)
                                        <span class="ml-1 inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700">Nasional</span>
                                    @endif
                                    @if ($policy->hari_kerja !== null)
                                        <span class="ml-1 inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700">Override Hari Kerja</span>
                                    @endif
                                </p>
                                <p class="text-[11px] text-gray-400">
                                    Masuk {{ substr($policy->jam_masuk, 0, 5) }}{{ $policy->jam_pulang ? ' — Pulang '.substr($policy->jam_pulang, 0, 5) : '' }}
                                    · Toleransi {{ $policy->toleransi_menit }} menit
                                </p>
                            </div>
                            @can('kehadiran-sdm.kelola-konfigurasi')
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="openPolicyModal({{ $policy->toJson() }})" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Edit</button>
                                    <form method="POST" action="{{ route('admin.kehadiran-sdm.policy.destroy', $policy) }}" onsubmit="return confirm('Hapus Attendance Policy ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-700">Hapus</button>
                                    </form>
                                </div>
                            @endcan
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-gray-400">Belum ada Attendance Policy.</p>
                    @endforelse
                </div>
            </div>
        </div>

Tambahkan modal Policy, SETELAH modal "Salin dari Kalender Akademik" (`{{-- Modal Salin dari Kalender Akademik --}}`), SEBELUM `</div>` penutup terakhir dan `</x-app-layout>`:

```blade
        {{-- Modal Attendance Policy --}}
        <div x-show="showPolicyModal" class="fixed inset-0 z-50 flex items-center justify-center px-4" style="display: none;">
            <div class="fixed inset-0 bg-gray-900/60" @click="showPolicyModal = false"></div>
            <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-elevated">
                <h3 class="font-display text-base font-bold text-gray-900" x-text="editingPolicy ? 'Edit Attendance Policy' : 'Tambah Attendance Policy'"></h3>
                <form method="POST" :action="editingPolicy ? `/admin/kehadiran-sdm/konfigurasi/policy/${editingPolicy.id}` : '{{ route('admin.kehadiran-sdm.policy.store') }}'" class="mt-4 space-y-4">
                    @csrf
                    <template x-if="editingPolicy"><input type="hidden" name="_method" value="PUT"></template>
                    <template x-if="!editingPolicy && formPolicy.is_nasional"><input type="hidden" name="is_nasional" value="1"></template>

                    <template x-if="!editingPolicy">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Kategori Pegawai</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 text-sm"><input type="radio" name="kategori_tipe" value="guru" x-model="formPolicy.kategori_tipe"> Guru (jenis PTK)</label>
                                <label class="flex items-center gap-2 text-sm"><input type="radio" name="kategori_tipe" value="karyawan" x-model="formPolicy.kategori_tipe"> Karyawan (jenis karyawan)</label>
                            </div>
                        </div>
                    </template>

                    <template x-if="!editingPolicy && formPolicy.kategori_tipe === 'guru'">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jenis PTK</label>
                            <select name="jenis_ptk" x-model="formPolicy.jenis_ptk" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm">
                                @foreach ($jenisPtkOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </template>

                    <template x-if="!editingPolicy && formPolicy.kategori_tipe === 'karyawan'">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jenis Karyawan</label>
                            <select name="jenis_karyawan_id" x-model="formPolicy.jenis_karyawan_id" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm">
                                @foreach ($jenisKaryawanList as $jk)
                                    <option value="{{ $jk->id }}">{{ $jk->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                    </template>

                    <template x-if="editingPolicy">
                        <p class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500" x-text="'Kategori: ' + (editingPolicy.jenis_ptk || 'Karyawan') + ' (tidak dapat diubah — hapus lalu buat baru kalau perlu ganti kategori)'"></p>
                    </template>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jam Masuk</label>
                            <input x-model="formPolicy.jam_masuk" name="jam_masuk" type="time" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jam Pulang (opsional)</label>
                            <input x-model="formPolicy.jam_pulang" name="jam_pulang" type="time" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm">
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Toleransi Keterlambatan (menit)</label>
                        <input x-model.number="formPolicy.toleransi_menit" name="toleransi_menit" type="number" min="0" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm">
                    </div>

                    <div class="rounded-lg bg-amber-50 border border-amber-100 px-3.5 py-3">
                        <label class="flex items-center gap-2 text-xs font-semibold text-amber-800">
                            <input type="checkbox" x-model="formPolicy.overrideHariKerja" class="rounded border-gray-300">
                            Override hari kerja kalender lembaga untuk kategori ini
                        </label>
                        <template x-if="formPolicy.overrideHariKerja">
                            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                <template x-for="[hari, label] in Object.entries({1: 'Senin', 2: 'Selasa', 3: 'Rabu', 4: 'Kamis', 5: 'Jumat', 6: 'Sabtu', 0: 'Minggu'})" :key="hari">
                                    <label class="flex items-center gap-1.5 rounded-lg border border-amber-200 bg-white px-2 py-1.5 text-xs">
                                        <input type="checkbox" :checked="formPolicy.hari_kerja.includes(Number(hari))" @change="togglePolicyHari(Number(hari))">
                                        <span x-text="label"></span>
                                    </label>
                                </template>
                            </div>
                        </template>
                        <template x-for="day in (formPolicy.overrideHariKerja ? formPolicy.hari_kerja : [])" :key="day">
                            <input type="hidden" name="hari_kerja[]" :value="day">
                        </template>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <x-secondary-button type="button" @click="showPolicyModal = false">Batal</x-secondary-button>
                        <x-primary-button type="submit">Simpan</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
```

- [ ] **Step 2: Tulis test `tests/Feature/Admin/AttendancePolicyViewTest.php`**

```php
<?php
// tests/Feature/Admin/AttendancePolicyViewTest.php

use App\Domains\Sdm\Models\AttendancePolicy;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('renders the konfigurasi page with the Attendance Policy tab and existing policy rows', function () {
    Permission::firstOrCreate(['name' => 'kehadiran-sdm.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('kehadiran-sdm.view');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    AttendancePolicy::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas', 'jam_masuk' => '07:00', 'toleransi_menit' => 15]);

    $this->actingAs($admin)->get(route('admin.kehadiran-sdm.konfigurasi.index'))
        ->assertOk()
        ->assertSee('Attendance Policy')
        ->assertSee('Guru Kelas')
        ->assertSee('07:00');
});
```

- [ ] **Step 3: Jalankan test baru**

Run: `php artisan test tests/Feature/Admin/AttendancePolicyViewTest.php`
Expected: 1 passed, 0 failed.

- [ ] **Step 4: Jalankan ulang test view Sub-project 1/2 yang berpotensi tersentuh untuk pastikan tidak regresi**

Run: `php artisan test tests/Feature/Admin/AttendanceKonfigurasiKalenderViewTest.php tests/Feature/Admin/AttendanceHolidayOverrideViewTest.php`
Expected: 2 passed, 0 failed (tidak berubah dari Sub-project 2).

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/kehadiran-sdm/konfigurasi.blade.php tests/Feature/Admin/AttendancePolicyViewTest.php
git commit -m "feat(sdm): tambah tab Attendance Policy di halaman konfigurasi kehadiran SDM"
```

---

## Task 9: Verifikasi Akhir + Full Test Suite (Butuh Izin User)

**Files:** Tidak ada file baru — task ini murni verifikasi.

- [ ] **Step 1: Grep ulang untuk memastikan tidak ada hardcode role**

Run: `grep -rn "hasRole(" app/Domains/Sdm app/Http/Controllers/Admin/AttendanceConfigurationController.php app/Console/Commands/TandaiAlpaOtomatisSdm.php`
Expected: tidak ada hasil (kosong).

- [ ] **Step 2: Grep untuk memastikan `KalenderKerjaSdmResolver` tidak diubah isinya (hanya di-inject, bukan dimodifikasi)**

Run: `git diff 1badcf0..HEAD -- app/Domains/Sdm/Services/KalenderKerjaSdmResolver.php`
Expected: output KOSONG (tidak ada perubahan sama sekali pada file ini sejak baseline plan).

- [ ] **Step 3: Jalankan seluruh test scoped Sub-project 3 bersama-sama**

Run: `php artisan test tests/Unit/Services/AttendancePolicyResolverTest.php tests/Feature/Sdm tests/Feature/Admin/AttendancePolicyControllerTest.php tests/Feature/Admin/AttendancePolicyViewTest.php`
Expected: semua test dari Task 2-8 hijau bersama-sama (total ≥ 26 test baru dari Sub-project 3, PLUS seluruh test Sub-project 1/2 yang ada di folder `tests/Feature/Sdm` tetap hijau), 0 failed.

- [ ] **Step 4: Jalankan ulang SELURUH test domain Sdm dari Sub-project 1, 2, dan 3 sekaligus, untuk pastikan tidak ada regresi silang**

Run: `php artisan test tests/Feature/Sdm tests/Feature/Admin/Attendance*.php tests/Unit/Services/KalenderKerjaSdmResolverTest.php tests/Unit/Services/AttendancePolicyResolverTest.php`
Expected: 0 failed.

- [ ] **Step 5: MINTA IZIN EKSPLISIT USER sebelum lanjut ke Step 6**

Tampilkan pesan ke user: "Semua test scoped Kehadiran SDM Sub-project 3 sudah hijau. Boleh saya jalankan full test suite sekarang?" — TUNGGU jawaban eksplisit sebelum menjalankan Step 6.

- [ ] **Step 6: (Setelah izin diberikan) Jalankan full test suite**

Run: `php artisan test`
Expected: 0 failed, 0 error. Total test harus ≥ 1962 (baseline Sub-project 2) + jumlah test baru Sub-project 3 (kurang lebih 26).

Catatan: kalau ada test GAGAL yang TIDAK terkait Kehadiran SDM sama sekali, ada riwayat flaky test pre-existing di branch ini (`KomponenPenilaianCrudTest`, `RaporPdfDataBuilderTest`) — jalankan ulang test yang gagal SENDIRIAN dulu untuk konfirmasi sebelum melaporkan sebagai regresi.

- [ ] **Step 7: Tulis handoff log**

Buat file `.agents/logs/2026-08-22-sdm-03-attendance-policy.md` berisi: ringkasan per task (1-9), commit hash tiap task, hasil verifikasi akhir dengan angka pasti, dan daftar deviasi (kalau ada) dari plan ini.

- [ ] **Step 8: Commit handoff log**

```bash
git add .agents/logs/2026-08-22-sdm-03-attendance-policy.md
git commit -m "docs(sdm): handoff log Sub-project 3 Attendance Policy dasar"
```

---

## Self-Review (dilakukan penulis plan, bukan executor)

**Spec coverage**: §3 struktur data → Task 1-2. §4 resolver + bypass TenantScope → Task 3. §5 integrasi Action & command → Task 4, 6. §6 perhitungan is_late/late_minutes → Task 5. §7 RBAC → Task 7. §8 UI → Task 8. §9 batasan (shift bergilir tidak dibangun, `KalenderKerjaSdmResolver` tidak diubah) → diverifikasi eksplisit di Task 9 Step 2. Semua requirement spec punya task yang mengimplementasikannya.

**Placeholder scan**: tidak ada TBD/TODO, semua kode lengkap per step.

**Type consistency**: `AttendancePolicyResolver::resolveLibur()` signature (`array{libur, alasan}`) identik dipakai di `RecordManualAttendanceAction`, `ScanQrAttendanceAction`, dan `TandaiAlpaOtomatisSdm` — sama seperti `KalenderKerjaSdmResolver::resolve()` sebelumnya. `resolvePolicy()` dipakai konsisten di `AttendancePolicyResolver::resolveLibur()` sendiri DAN `AttendanceRecordAggregator::hitungKeterlambatan()`.

**Regresi Sub-project 1 & 2**: Task 4 Step 3, Task 5 Step 2, Task 6 Step 2, Task 7 Step 5, dan Task 8 Step 4 masing-masing eksplisit menjalankan ulang test Sub-project 1/2 yang tersentuh perubahan, memastikan tidak ada regresi silang sebelum lanjut ke task berikutnya. Task 9 Step 2 secara eksplisit memverifikasi `KalenderKerjaSdmResolver.php` (Sub-project 2) benar-benar 0 byte berubah — jaminan konkret bahwa independensi kalender-vs-policy yang jadi prinsip desain sub-project ini benar-benar dijaga, bukan cuma klaim.

**Deviasi sadar dari deskripsi literal spec §5**: spec hanya menyebut skenario "lembaga libur, Policy override jadi kerja" (mis. satpam). Saat menulis Task 6, ditemukan celah SIMETRIS yang tidak disebut eksplisit di spec: kategori dengan Policy `hari_kerja` LEBIH SEMPIT dari kalender lembaga (mis. peran paruh waktu Senin-Rabu saja) akan SALAH ditandai Alpa di hari lembaga bekerja tapi Policy-nya libur, kalau command cuma mengecek Policy di jalur "lembaga libur" saja. Task 6 diperbaiki untuk memanggil `AttendancePolicyResolver::resolveLibur()` per-pegawai TANPA syarat (bukan bercabang berdasar status lembaga dulu) — ini strict lebih benar dari deskripsi literal spec, tidak bertentangan dengan tujuan spec (menutup celah auto-alpa), dan tidak mengubah perilaku pegawai tanpa Policy sama sekali (dibuktikan test regresi Step 2). Kalau user ingin spec diperbarui mencerminkan ini secara eksplisit, itu perubahan kecil dan bisa dilakukan terpisah.

