# Kehadiran SDM Sub-project 1 — Fondasi + Admin Manual + QR Statis — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bangun fondasi domain Kehadiran SDM: model data (metode absensi per lembaga, titik absen, event immutable, record harian agregat, QR pegawai), Action untuk input manual dan scan QR, RBAC baru (`admin_sdm`), dan UI admin minimal untuk mengelola dan mencatat kehadiran pegawai (guru & karyawan).

**Architecture:** Domain baru `App\Domains\Sdm\` (Actions/DataTransferObjects/Enums/Models/Services), mengikuti pola domain existing (`App\Domains\Kasus`). Controller thin di `App\Http\Controllers\Admin\`, memanggil Action. Tenant isolation 100% reuse `App\Models\Concerns\BelongsToTenant` + `App\Models\Scopes\TenantScope` yang sudah ada — TIDAK ADA kode isolasi baru ditulis. Relasi ke pegawai (Guru/Karyawan) polymorphic (`morphTo`/`morphMany`), tanpa morph map terdaftar (mengikuti pola `App\Domains\Workflow\Models\ApprovalRequest::approvable()` yang sudah ada di codebase ini — FQCN mentah disimpan di kolom `*_type`).

**Tech Stack:** Laravel 11, PHP 8.2+, Pest (test), Spatie Permission, Tailwind + Alpine.js, Tom Select (npm `tom-select`, sudah terpasang).

## Global Constraints

- Branch kerja: `sdm-v1`. JANGAN buat branch baru, JANGAN buat worktree — branch ini sudah ada dan sudah jadi tempat kerja fitur ini.
- Baseline: commit `b7f79b5` di branch `sdm-v1` (working tree bersih). Kalau ada commit baru masuk sebelum eksekusi, verifikasi ulang file yang dikutip plan ini (terutama `database/seeders/RoleSeeder.php`, `database/seeders/PermissionSeeder.php`, `app/Models/Guru.php`, `app/Models/Karyawan.php`) sebelum melanjutkan — laporkan ke user kalau isinya beda signifikan dari yang dikutip di sini.
- Spec lengkap ada di `.agents/specs/2026-08-22-sdm-01-fondasi-kehadiran-admin-qr.md` — baca dulu untuk konteks "kenapa", plan ini adalah "bagaimana"-nya.
- TIDAK ADA hardcode nama role (`hasRole('admin_sdm')` dst) di kode manapun — semua authorization pakai `$this->authorize('kehadiran-sdm.xxx')` lewat Spatie Permission.
- TIDAK ADA kolom/status `terlambat`/`is_late`/`late_minutes` di sub-project ini — itu Sub-project 3.
- TIDAK ADA validasi hari libur/kalender kerja di sub-project ini — itu Sub-project 2.
- TIDAK menyentuh `App\Domains\Akademik\Models\SesiPembelajaran`/`Presensi` murid sama sekali.
- Testing policy: test scoped per task, dijalankan SEBELUM commit setiap task. Full suite HANYA di Task 10, dan HANYA setelah izin eksplisit user — jangan otomatis jalankan.
- Satu commit per task, pesan commit sesuai yang ditentukan di tiap task Step terakhir.
- Test framework: Pest, gaya `it('...', function () { ... })`, ikuti persis gaya file test existing yang dikutip di tiap task (BUKAN PHPUnit class-based).

---

### Konteks tenant-isolation yang WAJIB dipahami sebelum mulai (jangan tulis ulang, cukup reuse)

`app/Models/Scopes/TenantScope.php` (SUDAH ADA, JANGAN DIUBAH) otomatis diterapkan ke setiap model yang pakai trait `App\Models\Concerns\BelongsToTenant`:
- Aktor `widestScopeLevel() === 'lembaga'` → query otomatis difilter `lembaga_id = $actingUser->lembaga_id`.
- Aktor `widestScopeLevel() === 'yayasan'` → query otomatis difilter berdasar `session('active_lembaga_id')` (kalau sudah pilih lewat UI switch-lembaga) atau seluruh lembaga miliknya (kalau belum pilih).

Untuk operasi TULIS (create), setiap Controller yang bikin data baru WAJIB resolve `lembaga_id` eksplisit lewat helper privat identik dengan yang sudah dipakai di `app/Http/Controllers/Admin/GuruController.php`:

```php
private function resolveLembagaId(Request $request): ?int
{
    if ($request->user()->widestScopeLevel() === 'yayasan') {
        return session('active_lembaga_id');
    }

    return $request->user()->lembaga_id;
}
```

Setiap Controller di plan ini yang punya method `store`/scan WAJIB punya method privat ini sendiri (kopi persis, controller lain di codebase ini juga masing-masing punya salinannya sendiri, bukan trait/helper global — ikuti konvensi yang sudah ada).

---

## Task 1: Migrasi 5 Tabel + Enum `AttendanceMethod` & `AttendanceStatus`

**Files:**
- Create: `database/migrations/2026_08_22_090000_create_attendance_method_configurations_table.php`
- Create: `database/migrations/2026_08_22_090100_create_attendance_points_table.php`
- Create: `database/migrations/2026_08_22_090200_create_attendance_events_table.php`
- Create: `database/migrations/2026_08_22_090300_create_attendance_records_table.php`
- Create: `database/migrations/2026_08_22_090400_create_employee_qr_codes_table.php`
- Create: `app/Domains/Sdm/Enums/AttendanceMethod.php`
- Create: `app/Domains/Sdm/Enums/AttendanceStatus.php`

**Interfaces:**
- Produces: tabel `attendance_method_configurations`, `attendance_points`, `attendance_events`, `attendance_records`, `employee_qr_codes` — kolom persis seperti didefinisikan di bawah, dipakai oleh Task 2 (Models).
- Produces: `App\Domains\Sdm\Enums\AttendanceMethod` (case `Admin = 'admin'`, `Qr = 'qr'`) dan `App\Domains\Sdm\Enums\AttendanceStatus` (case `Hadir = 'hadir'`, `Izin = 'izin'`, `Sakit = 'sakit'`, `Alpa = 'alpa'`) — dipakai Task 2 dst sebagai cast Model.

- [ ] **Step 1: Buat migrasi `attendance_method_configurations`**

```php
<?php
// database/migrations/2026_08_22_090000_create_attendance_method_configurations_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_method_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yayasan_id')->constrained('yayasan')->cascadeOnDelete();
            $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->cascadeOnDelete();
            $table->string('method');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['yayasan_id', 'lembaga_id', 'method'], 'attendance_method_config_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_method_configurations');
    }
};
```

- [ ] **Step 2: Buat migrasi `attendance_points`**

```php
<?php
// database/migrations/2026_08_22_090100_create_attendance_points_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->string('nama');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_points');
    }
};
```

- [ ] **Step 3: Buat migrasi `attendance_events` (immutable — tidak ada `updated_at`)**

```php
<?php
// database/migrations/2026_08_22_090200_create_attendance_events_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->morphs('pegawai');
            $table->foreignId('attendance_point_id')->nullable()->constrained('attendance_points')->nullOnDelete();
            $table->string('method');
            $table->enum('arah', ['masuk', 'pulang']);
            $table->string('status');
            $table->dateTime('waktu');
            $table->foreignId('dicatat_oleh_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['pegawai_type', 'pegawai_id', 'waktu']);
            $table->index(['lembaga_id', 'waktu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_events');
    }
};
```

- [ ] **Step 4: Buat migrasi `attendance_records`**

```php
<?php
// database/migrations/2026_08_22_090300_create_attendance_records_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->morphs('pegawai');
            $table->date('tanggal');
            $table->string('status');
            $table->dateTime('waktu_masuk')->nullable();
            $table->dateTime('waktu_pulang')->nullable();
            $table->timestamps();

            $table->unique(['pegawai_type', 'pegawai_id', 'tanggal'], 'attendance_record_pegawai_tanggal_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
```

- [ ] **Step 5: Buat migrasi `employee_qr_codes`**

```php
<?php
// database/migrations/2026_08_22_090400_create_employee_qr_codes_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_qr_codes', function (Blueprint $table) {
            $table->id();
            $table->morphs('pegawai');
            $table->string('token', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_qr_codes');
    }
};
```

- [ ] **Step 6: Jalankan migrasi dan verifikasi**

Run: `php artisan migrate`
Expected: 5 migrasi baru berjalan sukses, tidak ada error.

Verifikasi tambahan: `php artisan migrate:status | grep attendance` dan `php artisan migrate:status | grep employee_qr_codes` harus menunjukkan kelimanya berstatus `Ran`.

- [ ] **Step 7: Buat enum `AttendanceMethod`**

```php
<?php

namespace App\Domains\Sdm\Enums;

enum AttendanceMethod: string
{
    case Admin = 'admin';
    case Qr = 'qr';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Input Manual Admin',
            self::Qr => 'Scan QR',
        };
    }
}
```

- [ ] **Step 8: Buat enum `AttendanceStatus`**

```php
<?php

namespace App\Domains\Sdm\Enums;

enum AttendanceStatus: string
{
    case Hadir = 'hadir';
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Alpa = 'alpa';

    public function label(): string
    {
        return match ($this) {
            self::Hadir => 'Hadir',
            self::Izin => 'Izin',
            self::Sakit => 'Sakit',
            self::Alpa => 'Alpa',
        };
    }

    public function badgeTone(): string
    {
        return match ($this) {
            self::Hadir => 'green',
            self::Izin => 'blue',
            self::Sakit => 'amber',
            self::Alpa => 'red',
        };
    }
}
```

- [ ] **Step 9: Verifikasi enum lewat tinker**

Run: `php artisan tinker --execute="echo App\Domains\Sdm\Enums\AttendanceMethod::Admin->value . ' / ' . App\Domains\Sdm\Enums\AttendanceStatus::Hadir->label();"`
Expected output: `admin / Hadir`

- [ ] **Step 10: Commit**

```bash
git add database/migrations/2026_08_22_090000_create_attendance_method_configurations_table.php database/migrations/2026_08_22_090100_create_attendance_points_table.php database/migrations/2026_08_22_090200_create_attendance_events_table.php database/migrations/2026_08_22_090300_create_attendance_records_table.php database/migrations/2026_08_22_090400_create_employee_qr_codes_table.php app/Domains/Sdm/Enums/AttendanceMethod.php app/Domains/Sdm/Enums/AttendanceStatus.php
git commit -m "feat(sdm): migrasi 5 tabel fondasi Kehadiran SDM + enum AttendanceMethod/AttendanceStatus"
```

---

## Task 2: Models + Relasi Polymorphic di Guru/Karyawan

**Files:**
- Create: `app/Domains/Sdm/Models/AttendanceMethodConfiguration.php`
- Create: `app/Domains/Sdm/Models/AttendancePoint.php`
- Create: `app/Domains/Sdm/Models/AttendanceEvent.php`
- Create: `app/Domains/Sdm/Models/AttendanceRecord.php`
- Create: `app/Domains/Sdm/Models/EmployeeQrCode.php`
- Modify: `app/Models/Guru.php`
- Modify: `app/Models/Karyawan.php`
- Test: `tests/Feature/Sdm/AttendanceModelsTest.php`

**Interfaces:**
- Consumes: tabel dari Task 1.
- Produces: `AttendanceMethodConfiguration::create([...])`, `AttendancePoint::create([...])`, `AttendanceEvent::create([...])`, `AttendanceRecord::updateOrCreate([...], [...])`, `EmployeeQrCode::create([...])` — dipakai Task 4-6 (Actions). `Guru::attendanceEvents()`, `Guru::attendanceRecords()`, `Guru::employeeQrCode()` (dan padanan di `Karyawan`) — morph relations, dipakai Task 4-9.

- [ ] **Step 1: Buat model `AttendanceMethodConfiguration`**

```php
<?php

namespace App\Domains\Sdm\Models;

use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceMethodConfiguration extends Model
{
    use BelongsToTenant;

    protected $table = 'attendance_method_configurations';

    protected $fillable = ['yayasan_id', 'lembaga_id', 'method', 'is_enabled'];

    protected function casts(): array
    {
        return [
            'method' => AttendanceMethod::class,
            'is_enabled' => 'boolean',
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

- [ ] **Step 2: Buat model `AttendancePoint`**

```php
<?php

namespace App\Domains\Sdm\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePoint extends Model
{
    use BelongsToTenant;

    protected $table = 'attendance_points';

    protected $fillable = ['lembaga_id', 'nama', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }
}
```

- [ ] **Step 3: Buat model `AttendanceEvent` (immutable — tanpa `UPDATED_AT`)**

```php
<?php

namespace App\Domains\Sdm\Models;

use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Domains\Sdm\Enums\AttendanceStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AttendanceEvent extends Model
{
    use BelongsToTenant;

    const UPDATED_AT = null;

    protected $table = 'attendance_events';

    protected $fillable = [
        'lembaga_id', 'pegawai_type', 'pegawai_id', 'attendance_point_id',
        'method', 'arah', 'status', 'waktu', 'dicatat_oleh_user_id', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'method' => AttendanceMethod::class,
            'status' => AttendanceStatus::class,
            'waktu' => 'datetime',
        ];
    }

    public function pegawai(): MorphTo
    {
        return $this->morphTo();
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function attendancePoint(): BelongsTo
    {
        return $this->belongsTo(AttendancePoint::class);
    }

    public function dicatatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh_user_id');
    }
}
```

- [ ] **Step 4: Buat model `AttendanceRecord`**

```php
<?php

namespace App\Domains\Sdm\Models;

use App\Domains\Sdm\Enums\AttendanceStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AttendanceRecord extends Model
{
    use BelongsToTenant;

    protected $table = 'attendance_records';

    protected $fillable = [
        'lembaga_id', 'pegawai_type', 'pegawai_id', 'tanggal', 'status', 'waktu_masuk', 'waktu_pulang',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'tanggal' => 'date',
            'waktu_masuk' => 'datetime',
            'waktu_pulang' => 'datetime',
        ];
    }

    public function pegawai(): MorphTo
    {
        return $this->morphTo();
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }
}
```

- [ ] **Step 5: Buat model `EmployeeQrCode`**

Model ini SENGAJA TIDAK pakai `BelongsToTenant` (tidak ada kolom `lembaga_id` sendiri — lihat spec §5.1, isolasi didapat transitif lewat pegawai pemiliknya).

```php
<?php

namespace App\Domains\Sdm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EmployeeQrCode extends Model
{
    protected $table = 'employee_qr_codes';

    protected $fillable = ['pegawai_type', 'pegawai_id', 'token', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function pegawai(): MorphTo
    {
        return $this->morphTo();
    }
}
```

- [ ] **Step 6: Tambah relasi morphMany di `app/Models/Guru.php`**

Tambahkan `use` statement baru dan 3 method baru. Cari blok `use` di baris 8-10 (`use Illuminate\Database\Eloquent\Relations\BelongsToMany;`) dan tambahkan setelahnya:

```php
use App\Domains\Sdm\Models\AttendanceEvent;
use App\Domains\Sdm\Models\AttendanceRecord;
use App\Domains\Sdm\Models\EmployeeQrCode;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
```

Lalu tambahkan 3 method baru setelah method `jabatanTambahan()` (sebelum `getActivitylogOptions()`):

```php
    public function attendanceEvents(): MorphMany
    {
        return $this->morphMany(AttendanceEvent::class, 'pegawai');
    }

    public function attendanceRecords(): MorphMany
    {
        return $this->morphMany(AttendanceRecord::class, 'pegawai');
    }

    public function employeeQrCode(): MorphOne
    {
        return $this->morphOne(EmployeeQrCode::class, 'pegawai')->where('is_active', true);
    }
```

- [ ] **Step 7: Tambah relasi morphMany di `app/Models/Karyawan.php`**

Tambahkan `use` statement baru setelah `use Illuminate\Database\Eloquent\Relations\BelongsTo;`:

```php
use App\Domains\Sdm\Models\AttendanceEvent;
use App\Domains\Sdm\Models\AttendanceRecord;
use App\Domains\Sdm\Models\EmployeeQrCode;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
```

Lalu tambahkan 3 method baru setelah method `jenisKaryawan()` (method terakhir di class):

```php
    public function attendanceEvents(): MorphMany
    {
        return $this->morphMany(AttendanceEvent::class, 'pegawai');
    }

    public function attendanceRecords(): MorphMany
    {
        return $this->morphMany(AttendanceRecord::class, 'pegawai');
    }

    public function employeeQrCode(): MorphOne
    {
        return $this->morphOne(EmployeeQrCode::class, 'pegawai')->where('is_active', true);
    }
```

- [ ] **Step 8: Tulis test `tests/Feature/Sdm/AttendanceModelsTest.php`**

```php
<?php
// tests/Feature/Sdm/AttendanceModelsTest.php

use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Domains\Sdm\Enums\AttendanceStatus;
use App\Domains\Sdm\Models\AttendanceEvent;
use App\Domains\Sdm\Models\AttendanceMethodConfiguration;
use App\Domains\Sdm\Models\AttendancePoint;
use App\Domains\Sdm\Models\AttendanceRecord;
use App\Domains\Sdm\Models\EmployeeQrCode;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;

it('creates an attendance method configuration scoped to a yayasan default row', function () {
    $yayasan = Yayasan::factory()->create();

    $config = AttendanceMethodConfiguration::create([
        'yayasan_id' => $yayasan->id,
        'lembaga_id' => null,
        'method' => AttendanceMethod::Admin,
        'is_enabled' => true,
    ]);

    expect($config->method)->toBe(AttendanceMethod::Admin);
    expect($config->is_enabled)->toBeTrue();
});

it('creates an attendance point for a lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $point = AttendancePoint::create(['lembaga_id' => $lembaga->id, 'nama' => 'Gerbang Utama']);

    expect($point->nama)->toBe('Gerbang Utama');
    expect($point->is_active)->toBeTrue();
});

it('creates an attendance event for a guru via the morph relation and reads it back through Guru::attendanceEvents()', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $event = $guru->attendanceEvents()->create([
        'lembaga_id' => $lembaga->id,
        'method' => AttendanceMethod::Admin,
        'arah' => 'masuk',
        'status' => AttendanceStatus::Hadir,
        'waktu' => now(),
    ]);

    expect($event->pegawai_type)->toBe(Guru::class);
    expect($event->pegawai_id)->toBe($guru->id);
    expect($guru->attendanceEvents()->count())->toBe(1);
    expect($event->pegawai->id)->toBe($guru->id);
});

it('creates an attendance event for a karyawan via the morph relation', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $karyawan = Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'yayasan_id' => $yayasan->id]);

    $event = $karyawan->attendanceEvents()->create([
        'lembaga_id' => $lembaga->id,
        'method' => AttendanceMethod::Qr,
        'arah' => 'masuk',
        'status' => AttendanceStatus::Hadir,
        'waktu' => now(),
    ]);

    expect($event->pegawai_type)->toBe(Karyawan::class);
    expect($karyawan->attendanceEvents()->count())->toBe(1);
});

it('upserts an attendance record keyed by pegawai and tanggal', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $tanggal = now()->toDateString();

    $record = AttendanceRecord::updateOrCreate(
        ['pegawai_type' => Guru::class, 'pegawai_id' => $guru->id, 'tanggal' => $tanggal],
        ['lembaga_id' => $lembaga->id, 'status' => AttendanceStatus::Hadir, 'waktu_masuk' => now()]
    );

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->count())->toBe(1);
    expect($record->status)->toBe(AttendanceStatus::Hadir);

    AttendanceRecord::updateOrCreate(
        ['pegawai_type' => Guru::class, 'pegawai_id' => $guru->id, 'tanggal' => $tanggal],
        ['lembaga_id' => $lembaga->id, 'status' => AttendanceStatus::Izin]
    );

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->count())->toBe(1);
    expect($record->fresh()->status)->toBe(AttendanceStatus::Izin);
});

it('creates and reads an employee qr code via Guru::employeeQrCode()', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    EmployeeQrCode::create(['pegawai_type' => Guru::class, 'pegawai_id' => $guru->id, 'token' => str()->random(48), 'is_active' => true]);

    expect($guru->fresh()->employeeQrCode)->not->toBeNull();
    expect($guru->fresh()->employeeQrCode->is_active)->toBeTrue();
});
```

- [ ] **Step 9: Jalankan test**

Run: `php artisan test tests/Feature/Sdm/AttendanceModelsTest.php`
Expected: 6 passed, 0 failed.

- [ ] **Step 10: Commit**

```bash
git add app/Domains/Sdm/Models/AttendanceMethodConfiguration.php app/Domains/Sdm/Models/AttendancePoint.php app/Domains/Sdm/Models/AttendanceEvent.php app/Domains/Sdm/Models/AttendanceRecord.php app/Domains/Sdm/Models/EmployeeQrCode.php app/Models/Guru.php app/Models/Karyawan.php tests/Feature/Sdm/AttendanceModelsTest.php
git commit -m "feat(sdm): tambah 5 model Kehadiran SDM + relasi morphMany di Guru/Karyawan"
```

---

## Task 3: RBAC — Role `admin_sdm` + Permission `kehadiran-sdm.*`

**Files:**
- Modify: `database/seeders/PermissionSeeder.php`
- Modify: `database/seeders/RoleSeeder.php`
- Test: `tests/Feature/Sdm/AttendanceRbacSeedTest.php`

**Interfaces:**
- Produces: permission `kehadiran-sdm.view`, `kehadiran-sdm.catat`, `kehadiran-sdm.kelola-konfigurasi`, `kehadiran-sdm.lihat-qr-sendiri`; role `admin_sdm` (`scope_level: lembaga`) dengan keempat permission. Dipakai Task 7-9 (Controller `$this->authorize(...)`).

- [ ] **Step 1: Tambah 4 permission baru di `database/seeders/PermissionSeeder.php`**

Cari baris berikut (baris terakhir sebelum `];` di array `$permissions`):

```php
            'rpp.view', 'rpp.kelola', 'rpp.verify',
        ];
```

Ganti jadi:

```php
            'rpp.view', 'rpp.kelola', 'rpp.verify',
            'kehadiran-sdm.view', 'kehadiran-sdm.catat', 'kehadiran-sdm.kelola-konfigurasi', 'kehadiran-sdm.lihat-qr-sendiri',
        ];
```

- [ ] **Step 2: Tambah role `admin_sdm` di `database/seeders/RoleSeeder.php`**

Cari baris:

```php
            'admin_sarpras' => ['scope_level' => 'yayasan', 'is_protected' => false],
        ];
```

Ganti jadi:

```php
            'admin_sarpras' => ['scope_level' => 'yayasan', 'is_protected' => false],
            'admin_sdm' => ['scope_level' => 'lembaga', 'is_protected' => false],
        ];
```

- [ ] **Step 3: Assign permission ke `admin_sdm`, dan `kehadiran-sdm.lihat-qr-sendiri` ke `guru`/`karyawan_pool`/`karyawan_lembaga`**

Cari blok:

```php
            if ($name === 'guru') {
                $role->givePermissionTo([
                    'presensi.isi', 'asesmen.kelola', 'komponen-penilaian.kelola-sendiri', 'rapor.input-wali', 'rapor.ajukan',
                    'kasus.ajukan', 'kasus.view',
                    'rpp.view', 'rpp.kelola',
                ]);
            }
```

Ganti jadi (tambah `'kehadiran-sdm.lihat-qr-sendiri'` di array yang sama):

```php
            if ($name === 'guru') {
                $role->givePermissionTo([
                    'presensi.isi', 'asesmen.kelola', 'komponen-penilaian.kelola-sendiri', 'rapor.input-wali', 'rapor.ajukan',
                    'kasus.ajukan', 'kasus.view',
                    'rpp.view', 'rpp.kelola',
                    'kehadiran-sdm.lihat-qr-sendiri',
                ]);
            }
```

Cari blok:

```php
            if (in_array($name, ['karyawan_pool', 'karyawan_lembaga'], true)) {
                $role->givePermissionTo(['kasus.view']);
            }
```

Ganti jadi:

```php
            if (in_array($name, ['karyawan_pool', 'karyawan_lembaga'], true)) {
                $role->givePermissionTo(['kasus.view', 'kehadiran-sdm.lihat-qr-sendiri']);
            }

            if ($name === 'admin_sdm') {
                $role->givePermissionTo([
                    'kehadiran-sdm.view', 'kehadiran-sdm.catat', 'kehadiran-sdm.kelola-konfigurasi', 'kehadiran-sdm.lihat-qr-sendiri',
                ]);
            }
```

- [ ] **Step 4: Tulis test seeding**

```php
<?php
// tests/Feature/Sdm/AttendanceRbacSeedTest.php

use App\Models\Role;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;

it('seeds the admin_sdm role with all 4 kehadiran-sdm permissions', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);

    foreach (['kehadiran-sdm.view', 'kehadiran-sdm.catat', 'kehadiran-sdm.kelola-konfigurasi', 'kehadiran-sdm.lihat-qr-sendiri'] as $permission) {
        expect(Permission::where('name', $permission)->exists())->toBeTrue();
    }

    $role = Role::where('name', 'admin_sdm')->first();
    expect($role)->not->toBeNull();
    expect($role->scope_level)->toBe('lembaga');
    expect($role->hasPermissionTo('kehadiran-sdm.view'))->toBeTrue();
    expect($role->hasPermissionTo('kehadiran-sdm.catat'))->toBeTrue();
    expect($role->hasPermissionTo('kehadiran-sdm.kelola-konfigurasi'))->toBeTrue();
    expect($role->hasPermissionTo('kehadiran-sdm.lihat-qr-sendiri'))->toBeTrue();
});

it('gives guru and karyawan roles the lihat-qr-sendiri permission only', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);

    foreach (['guru', 'karyawan_pool', 'karyawan_lembaga'] as $roleName) {
        $role = Role::where('name', $roleName)->first();
        expect($role->hasPermissionTo('kehadiran-sdm.lihat-qr-sendiri'))->toBeTrue();
        expect($role->hasPermissionTo('kehadiran-sdm.kelola-konfigurasi'))->toBeFalse();
    }
});
```

- [ ] **Step 5: Jalankan test**

Run: `php artisan test tests/Feature/Sdm/AttendanceRbacSeedTest.php`
Expected: 2 passed, 0 failed.

- [ ] **Step 6: Commit**

```bash
git add database/seeders/PermissionSeeder.php database/seeders/RoleSeeder.php tests/Feature/Sdm/AttendanceRbacSeedTest.php
git commit -m "feat(sdm): tambah role admin_sdm + permission kehadiran-sdm.* di RBAC seeder"
```

---

## Task 4: DTO + `AttendanceRecordAggregator` + `RecordManualAttendanceAction`

**Files:**
- Create: `app/Domains/Sdm/DataTransferObjects/RecordManualAttendanceData.php`
- Create: `app/Domains/Sdm/Services/AttendanceRecordAggregator.php`
- Create: `app/Domains/Sdm/Actions/RecordManualAttendanceAction.php`
- Test: `tests/Feature/Sdm/RecordManualAttendanceActionTest.php`

**Interfaces:**
- Consumes: `AttendanceEvent`, `AttendanceRecord` model (Task 2); `AttendanceMethod`, `AttendanceStatus` enum (Task 1).
- Produces: `RecordManualAttendanceData` (readonly DTO); `AttendanceRecordAggregator::sync(Model $pegawai, CarbonImmutable $tanggal): AttendanceRecord`; `RecordManualAttendanceAction::execute(Model $pegawai, RecordManualAttendanceData $data): AttendanceEvent` — dipakai Task 8 (`AttendanceController`).

- [ ] **Step 1: Buat DTO `RecordManualAttendanceData`**

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
    ) {}
}
```

- [ ] **Step 2: Buat `AttendanceRecordAggregator`**

Resolusi status mengikuti spec §7.3: event non-`hadir` (izin/sakit/alpa) mana pun pada hari itu override jadi status record; kalau semua event `hadir` → `hadir`; kalau tidak ada event sama sekali, method ini TIDAK dipanggil (Action pemanggil selalu punya minimal 1 event baru saat memanggil `sync()`).

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
            ]
        );
    }
}
```

- [ ] **Step 3: Buat `RecordManualAttendanceAction`**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions;

use App\Domains\Sdm\DataTransferObjects\RecordManualAttendanceData;
use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Domains\Sdm\Models\AttendanceEvent;
use App\Domains\Sdm\Services\AttendanceRecordAggregator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class RecordManualAttendanceAction
{
    public function __construct(private readonly AttendanceRecordAggregator $aggregator) {}

    public function execute(Model $pegawai, RecordManualAttendanceData $data): AttendanceEvent
    {
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

- [ ] **Step 4: Tulis test**

```php
<?php
// tests/Feature/Sdm/RecordManualAttendanceActionTest.php

use App\Domains\Sdm\Actions\RecordManualAttendanceAction;
use App\Domains\Sdm\DataTransferObjects\RecordManualAttendanceData;
use App\Domains\Sdm\Enums\AttendanceStatus;
use App\Domains\Sdm\Models\AttendanceRecord;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Carbon\CarbonImmutable;

it('records a manual check-in event and creates an aggregate record for the day', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $waktu = CarbonImmutable::parse('2026-08-22 07:15:00');

    $action = app(RecordManualAttendanceAction::class);
    $event = $action->execute($guru, new RecordManualAttendanceData(
        lembagaId: $lembaga->id,
        arah: 'masuk',
        status: AttendanceStatus::Hadir,
        waktu: $waktu,
        dicatatOlehUserId: $admin->id,
    ));

    expect($event->arah)->toBe('masuk');
    expect($event->status)->toBe(AttendanceStatus::Hadir);

    $record = AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->first();
    expect($record)->not->toBeNull();
    expect($record->status)->toBe(AttendanceStatus::Hadir);
    expect($record->waktu_masuk->format('H:i'))->toBe('07:15');
    expect($record->waktu_pulang)->toBeNull();
});

it('merges a check-out event into the same day record produced by an earlier check-in', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $action = app(RecordManualAttendanceAction::class);

    $action->execute($guru, new RecordManualAttendanceData(
        lembagaId: $lembaga->id, arah: 'masuk', status: AttendanceStatus::Hadir,
        waktu: CarbonImmutable::parse('2026-08-22 07:00:00'), dicatatOlehUserId: $admin->id,
    ));
    $action->execute($guru, new RecordManualAttendanceData(
        lembagaId: $lembaga->id, arah: 'pulang', status: AttendanceStatus::Hadir,
        waktu: CarbonImmutable::parse('2026-08-22 15:30:00'), dicatatOlehUserId: $admin->id,
    ));

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->count())->toBe(1);
    $record = AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->first();
    expect($record->waktu_masuk->format('H:i'))->toBe('07:00');
    expect($record->waktu_pulang->format('H:i'))->toBe('15:30');
});

it('overrides the day status to izin when an izin event exists alongside a hadir event', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $action = app(RecordManualAttendanceAction::class);

    $action->execute($guru, new RecordManualAttendanceData(
        lembagaId: $lembaga->id, arah: 'masuk', status: AttendanceStatus::Hadir,
        waktu: CarbonImmutable::parse('2026-08-22 07:00:00'), dicatatOlehUserId: $admin->id,
    ));
    $action->execute($guru, new RecordManualAttendanceData(
        lembagaId: $lembaga->id, arah: 'pulang', status: AttendanceStatus::Izin,
        waktu: CarbonImmutable::parse('2026-08-22 09:00:00'), dicatatOlehUserId: $admin->id,
        catatan: 'Izin keperluan keluarga setelah absen masuk.',
    ));

    $record = AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->first();
    expect($record->status)->toBe(AttendanceStatus::Izin);
});
```

- [ ] **Step 5: Jalankan test**

Run: `php artisan test tests/Feature/Sdm/RecordManualAttendanceActionTest.php`
Expected: 3 passed, 0 failed.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Sdm/DataTransferObjects/RecordManualAttendanceData.php app/Domains/Sdm/Services/AttendanceRecordAggregator.php app/Domains/Sdm/Actions/RecordManualAttendanceAction.php tests/Feature/Sdm/RecordManualAttendanceActionTest.php
git commit -m "feat(sdm): tambah AttendanceRecordAggregator dan RecordManualAttendanceAction"
```

---

## Task 5: `GenerateEmployeeQrTokenAction` + `SetAttendanceMethodConfigurationAction`

**Files:**
- Create: `app/Domains/Sdm/Actions/GenerateEmployeeQrTokenAction.php`
- Create: `app/Domains/Sdm/Actions/SetAttendanceMethodConfigurationAction.php`
- Test: `tests/Feature/Sdm/GenerateEmployeeQrTokenActionTest.php`
- Test: `tests/Feature/Sdm/SetAttendanceMethodConfigurationActionTest.php`

**Interfaces:**
- Consumes: `EmployeeQrCode`, `AttendanceMethodConfiguration` model (Task 2).
- Produces: `GenerateEmployeeQrTokenAction::execute(Model $pegawai): EmployeeQrCode`; `SetAttendanceMethodConfigurationAction::execute(int $yayasanId, ?int $lembagaId, AttendanceMethod $method, bool $isEnabled): AttendanceMethodConfiguration` — dipakai Task 7 & 9 (`AttendanceConfigurationController`, `EmployeeQrCodeController`).

- [ ] **Step 1: Buat `GenerateEmployeeQrTokenAction`**

Menonaktifkan token lama (kalau ada) sebelum membuat token baru — spec §4.6: unique constraint efektif "1 token aktif per pegawai" dijaga di level Action, bukan DB partial index (portabilitas driver).

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions;

use App\Domains\Sdm\Models\EmployeeQrCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class GenerateEmployeeQrTokenAction
{
    public function execute(Model $pegawai): EmployeeQrCode
    {
        return DB::transaction(function () use ($pegawai) {
            EmployeeQrCode::where('pegawai_type', $pegawai::class)
                ->where('pegawai_id', $pegawai->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            return EmployeeQrCode::create([
                'pegawai_type' => $pegawai::class,
                'pegawai_id' => $pegawai->id,
                'token' => Str::random(48),
                'is_active' => true,
            ]);
        });
    }
}
```

- [ ] **Step 2: Buat `SetAttendanceMethodConfigurationAction`**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions;

use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Domains\Sdm\Models\AttendanceMethodConfiguration;

final class SetAttendanceMethodConfigurationAction
{
    public function execute(int $yayasanId, ?int $lembagaId, AttendanceMethod $method, bool $isEnabled): AttendanceMethodConfiguration
    {
        return AttendanceMethodConfiguration::updateOrCreate(
            ['yayasan_id' => $yayasanId, 'lembaga_id' => $lembagaId, 'method' => $method],
            ['is_enabled' => $isEnabled]
        );
    }
}
```

- [ ] **Step 3: Tulis test `GenerateEmployeeQrTokenActionTest`**

```php
<?php
// tests/Feature/Sdm/GenerateEmployeeQrTokenActionTest.php

use App\Domains\Sdm\Actions\GenerateEmployeeQrTokenAction;
use App\Domains\Sdm\Models\EmployeeQrCode;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Yayasan;

it('generates a random unique active token for a pegawai with no prior token', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $qr = app(GenerateEmployeeQrTokenAction::class)->execute($guru);

    expect($qr->token)->toHaveLength(48);
    expect($qr->is_active)->toBeTrue();
    expect($qr->token)->not->toContain((string) $guru->nik);
});

it('deactivates the previous token when regenerating for the same pegawai', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $action = app(GenerateEmployeeQrTokenAction::class);

    $first = $action->execute($guru);
    $second = $action->execute($guru);

    expect($first->fresh()->is_active)->toBeFalse();
    expect($second->is_active)->toBeTrue();
    expect(EmployeeQrCode::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->where('is_active', true)->count())->toBe(1);
});
```

- [ ] **Step 4: Tulis test `SetAttendanceMethodConfigurationActionTest`**

```php
<?php
// tests/Feature/Sdm/SetAttendanceMethodConfigurationActionTest.php

use App\Domains\Sdm\Actions\SetAttendanceMethodConfigurationAction;
use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Models\Lembaga;
use App\Models\Yayasan;

it('creates a yayasan-level default configuration when lembaga_id is null', function () {
    $yayasan = Yayasan::factory()->create();

    $config = app(SetAttendanceMethodConfigurationAction::class)->execute($yayasan->id, null, AttendanceMethod::Qr, true);

    expect($config->lembaga_id)->toBeNull();
    expect($config->is_enabled)->toBeTrue();
});

it('updates an existing configuration instead of creating a duplicate row', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $action = app(SetAttendanceMethodConfigurationAction::class);

    $action->execute($yayasan->id, $lembaga->id, AttendanceMethod::Qr, true);
    $action->execute($yayasan->id, $lembaga->id, AttendanceMethod::Qr, false);

    expect(\App\Domains\Sdm\Models\AttendanceMethodConfiguration::where('lembaga_id', $lembaga->id)->count())->toBe(1);
    expect(\App\Domains\Sdm\Models\AttendanceMethodConfiguration::where('lembaga_id', $lembaga->id)->first()->is_enabled)->toBeFalse();
});
```

- [ ] **Step 5: Jalankan kedua test**

Run: `php artisan test tests/Feature/Sdm/GenerateEmployeeQrTokenActionTest.php tests/Feature/Sdm/SetAttendanceMethodConfigurationActionTest.php`
Expected: 4 passed, 0 failed.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Sdm/Actions/GenerateEmployeeQrTokenAction.php app/Domains/Sdm/Actions/SetAttendanceMethodConfigurationAction.php tests/Feature/Sdm/GenerateEmployeeQrTokenActionTest.php tests/Feature/Sdm/SetAttendanceMethodConfigurationActionTest.php
git commit -m "feat(sdm): tambah GenerateEmployeeQrTokenAction dan SetAttendanceMethodConfigurationAction"
```

---

## Task 6: `ScanQrAttendanceAction` (dengan Penolakan Lintas-Lembaga)

**Files:**
- Create: `app/Domains/Sdm/DataTransferObjects/ScanQrAttendanceData.php`
- Create: `app/Domains/Sdm/Exceptions/InvalidQrTokenException.php`
- Create: `app/Domains/Sdm/Exceptions/QrTokenLembagaMismatchException.php`
- Create: `app/Domains/Sdm/Actions/ScanQrAttendanceAction.php`
- Test: `tests/Feature/Sdm/ScanQrAttendanceActionTest.php`

**Interfaces:**
- Consumes: `EmployeeQrCode` (Task 2), `AttendanceRecordAggregator` (Task 4).
- Produces: `ScanQrAttendanceAction::execute(ScanQrAttendanceData $data): AttendanceEvent` (throws `InvalidQrTokenException` / `QrTokenLembagaMismatchException`) — dipakai Task 9 (`AttendanceQrScanController`).

- [ ] **Step 1: Buat DTO `ScanQrAttendanceData`**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\DataTransferObjects;

final readonly class ScanQrAttendanceData
{
    public function __construct(
        public string $token,
        public string $arah,
        public int $lembagaId,
        public int $dicatatOlehUserId,
        public ?int $attendancePointId = null,
    ) {}
}
```

- [ ] **Step 2: Buat 2 exception class**

```php
<?php

namespace App\Domains\Sdm\Exceptions;

use RuntimeException;

class InvalidQrTokenException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('QR tidak valid atau sudah tidak aktif.');
    }
}
```

```php
<?php

namespace App\Domains\Sdm\Exceptions;

use RuntimeException;

class QrTokenLembagaMismatchException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('QR ini milik pegawai dari lembaga lain dan tidak dapat discan di sini.');
    }
}
```

- [ ] **Step 3: Buat `ScanQrAttendanceAction`**

QR scan selalu status `hadir` (spec §7.2 poin 3). Query token TANPA tenant scope tambahan dulu (token unik global), lalu verifikasi manual `lembaga_id` pegawai pemilik token sama dengan `$data->lembagaId` milik petugas yang scan — pertahanan eksplisit di atas TenantScope karena token datang dari input eksternal (hasil scan), bukan dari query yang sudah terfilter.

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions;

use App\Domains\Sdm\DataTransferObjects\ScanQrAttendanceData;
use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Domains\Sdm\Enums\AttendanceStatus;
use App\Domains\Sdm\Exceptions\InvalidQrTokenException;
use App\Domains\Sdm\Exceptions\QrTokenLembagaMismatchException;
use App\Domains\Sdm\Models\AttendanceEvent;
use App\Domains\Sdm\Models\EmployeeQrCode;
use App\Domains\Sdm\Services\AttendanceRecordAggregator;
use Illuminate\Support\Facades\DB;

final class ScanQrAttendanceAction
{
    public function __construct(private readonly AttendanceRecordAggregator $aggregator) {}

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

- [ ] **Step 4: Tulis test**

```php
<?php
// tests/Feature/Sdm/ScanQrAttendanceActionTest.php

use App\Domains\Sdm\Actions\GenerateEmployeeQrTokenAction;
use App\Domains\Sdm\Actions\ScanQrAttendanceAction;
use App\Domains\Sdm\DataTransferObjects\ScanQrAttendanceData;
use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Domains\Sdm\Exceptions\InvalidQrTokenException;
use App\Domains\Sdm\Exceptions\QrTokenLembagaMismatchException;
use App\Domains\Sdm\Models\AttendanceRecord;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;

it('records a hadir event when scanning a valid token for a pegawai in the same lembaga as the petugas', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $petugas = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $qr = app(GenerateEmployeeQrTokenAction::class)->execute($guru);

    $event = app(ScanQrAttendanceAction::class)->execute(new ScanQrAttendanceData(
        token: $qr->token, arah: 'masuk', lembagaId: $lembaga->id, dicatatOlehUserId: $petugas->id,
    ));

    expect($event->method)->toBe(AttendanceMethod::Qr);
    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeTrue();
});

it('rejects an unknown or inactive token', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $petugas = User::factory()->create(['lembaga_id' => $lembaga->id]);

    expect(fn () => app(ScanQrAttendanceAction::class)->execute(new ScanQrAttendanceData(
        token: 'token-tidak-pernah-ada', arah: 'masuk', lembagaId: $lembaga->id, dicatatOlehUserId: $petugas->id,
    )))->toThrow(InvalidQrTokenException::class);
});

it('rejects a token belonging to an employee from a different lembaga than the scanning petugas', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guruLembagaB = Guru::factory()->create(['lembaga_id' => $lembagaB->id]);
    $petugasLembagaA = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $qr = app(GenerateEmployeeQrTokenAction::class)->execute($guruLembagaB);

    expect(fn () => app(ScanQrAttendanceAction::class)->execute(new ScanQrAttendanceData(
        token: $qr->token, arah: 'masuk', lembagaId: $lembagaA->id, dicatatOlehUserId: $petugasLembagaA->id,
    )))->toThrow(QrTokenLembagaMismatchException::class);

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guruLembagaB->id)->exists())->toBeFalse();
});
```

- [ ] **Step 5: Jalankan test**

Run: `php artisan test tests/Feature/Sdm/ScanQrAttendanceActionTest.php`
Expected: 3 passed, 0 failed.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Sdm/DataTransferObjects/ScanQrAttendanceData.php app/Domains/Sdm/Exceptions/InvalidQrTokenException.php app/Domains/Sdm/Exceptions/QrTokenLembagaMismatchException.php app/Domains/Sdm/Actions/ScanQrAttendanceAction.php tests/Feature/Sdm/ScanQrAttendanceActionTest.php
git commit -m "feat(sdm): tambah ScanQrAttendanceAction dengan penolakan token lintas-lembaga"
```

---

## Task 7: `AttendanceConfigurationController` (Metode + Titik Absen) + Routes + Views

**Files:**
- Create: `app/Http/Controllers/Admin/AttendanceConfigurationController.php`
- Create: `routes/admin/kehadiran-sdm.php`
- Modify: `routes/admin.php`
- Create: `resources/views/admin/kehadiran-sdm/konfigurasi.blade.php`
- Test: `tests/Feature/Admin/AttendanceConfigurationControllerTest.php`

**Interfaces:**
- Consumes: `SetAttendanceMethodConfigurationAction` (Task 5), `AttendanceMethodConfiguration`, `AttendancePoint` model (Task 2).
- Produces: route `admin.kehadiran-sdm.konfigurasi.index` (GET), `admin.kehadiran-sdm.konfigurasi.metode` (POST), `admin.kehadiran-sdm.titik.store` (POST), `admin.kehadiran-sdm.titik.update` (PUT), `admin.kehadiran-sdm.titik.destroy` (DELETE) — dipakai Task 8 & 9 sebagai referensi navigasi (bukan dependency kode).

- [ ] **Step 1: Buat `AttendanceConfigurationController`**

Query baris konfigurasi WAJIB bypass `TenantScope` secara sengaja (`withoutGlobalScope`), sama seperti pola yang sudah dipakai `KasusController::triase()` untuk kasus serupa — alasan: `TenantScope` untuk aktor `scope_level: lembaga` (termasuk `admin_sdm`) menambahkan `WHERE lembaga_id = $actingUser->lembaga_id` sebagai kondisi AND top-level, yang membuat baris default yayasan (`lembaga_id IS NULL`) TIDAK PERNAH ikut manapun query tambahan `orWhereNull('lembaga_id')` dibuat — kombinasi `lembaga_id = X AND (lembaga_id = X OR lembaga_id IS NULL)` tetap collapse ke `lembaga_id = X` saja. Bypass ini aman karena diganti dengan filter manual eksplisit `where('yayasan_id', ...)` + `(lembaga_id = aktif OR lembaga_id IS NULL)`, jadi tetap tidak bocor lintas yayasan.

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Sdm\Actions\SetAttendanceMethodConfigurationAction;
use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Domains\Sdm\Models\AttendanceMethodConfiguration;
use App\Domains\Sdm\Models\AttendancePoint;
use App\Models\Scopes\TenantScope;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class AttendanceConfigurationController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('kehadiran-sdm.view');

        $lembagaId = $this->resolveLembagaId($request);

        $konfigurasi = AttendanceMethodConfiguration::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $request->user()->yayasan_id)
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

    public function updateMetode(Request $request, SetAttendanceMethodConfigurationAction $action): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        $data = $request->validate([
            'method' => ['required', 'in:admin,qr'],
            'is_enabled' => ['required', 'boolean'],
        ]);

        $lembagaId = $this->resolveLembagaId($request);

        if ($lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum mengubah konfigurasi.']);
        }

        $action->execute($request->user()->yayasan_id, $lembagaId, AttendanceMethod::from($data['method']), (bool) $data['is_enabled']);

        return back()->with('status', 'Konfigurasi metode absensi berhasil diperbarui.');
    }

    public function storeTitik(Request $request): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        $data = $request->validate(['nama' => ['required', 'string', 'max:255']]);
        $lembagaId = $this->resolveLembagaId($request);

        if ($lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah titik absen.']);
        }

        AttendancePoint::create(['lembaga_id' => $lembagaId, 'nama' => $data['nama']]);

        return back()->with('status', 'Titik absen berhasil ditambahkan.');
    }

    public function updateTitik(Request $request, AttendancePoint $titik): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $titik->update($data);

        return back()->with('status', 'Titik absen berhasil diperbarui.');
    }

    public function destroyTitik(AttendancePoint $titik): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        $titik->delete();

        return back()->with('status', 'Titik absen berhasil dihapus.');
    }

    private function resolveLembagaId(Request $request): ?int
    {
        if ($request->user()->widestScopeLevel() === 'yayasan') {
            return session('active_lembaga_id');
        }

        return $request->user()->lembaga_id;
    }
}
```

- [ ] **Step 2: Buat `routes/admin/kehadiran-sdm.php`**

```php
<?php

use App\Http\Controllers\Admin\AttendanceConfigurationController;
use Illuminate\Support\Facades\Route;

Route::get('kehadiran-sdm/konfigurasi', [AttendanceConfigurationController::class, 'index'])->name('kehadiran-sdm.konfigurasi.index');
Route::post('kehadiran-sdm/konfigurasi/metode', [AttendanceConfigurationController::class, 'updateMetode'])->name('kehadiran-sdm.konfigurasi.metode');
Route::post('kehadiran-sdm/konfigurasi/titik', [AttendanceConfigurationController::class, 'storeTitik'])->name('kehadiran-sdm.titik.store');
Route::put('kehadiran-sdm/konfigurasi/titik/{titik}', [AttendanceConfigurationController::class, 'updateTitik'])->name('kehadiran-sdm.titik.update');
Route::delete('kehadiran-sdm/konfigurasi/titik/{titik}', [AttendanceConfigurationController::class, 'destroyTitik'])->name('kehadiran-sdm.titik.destroy');
```

- [ ] **Step 3: Registrasikan file route di `routes/admin.php`**

Cari baris:

```php
    require base_path('routes/admin/pengadaan.php');
});
```

Ganti jadi:

```php
    require base_path('routes/admin/pengadaan.php');
    require base_path('routes/admin/kehadiran-sdm.php');
});
```

- [ ] **Step 4: Buat view `resources/views/admin/kehadiran-sdm/konfigurasi.blade.php`**

```blade
<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6" x-data="{
        showTitikModal: false,
        editingTitik: null,
        formTitik: { nama: '' },
        openTitikModal(titik = null) {
            this.editingTitik = titik;
            this.formTitik = { nama: titik ? titik.nama : '' };
            this.showTitikModal = true;
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
            <p class="mt-1 text-sm text-gray-500">Atur metode absensi yang aktif dan titik absen untuk lembaga ini.</p>
        </div>

        {{-- Metode Absensi --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
            <h2 class="font-display text-sm font-bold text-gray-900">Metode Absensi Aktif</h2>
            <p class="mt-1 text-xs text-gray-500">Input manual admin selalu tersedia sebagai fallback. Metode lain bisa diaktifkan/nonaktifkan per lembaga.</p>

            <div class="mt-4 space-y-3">
                @foreach ($methods as $method)
                    @php
                        $existing = $konfigurasi->firstWhere('method', $method);
                        $isEnabled = $existing?->is_enabled ?? ($method->value === 'admin');
                    @endphp
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
                @endforeach
            </div>
        </div>

        {{-- Titik Absen --}}
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

        {{-- Modal Titik Absen --}}
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
    </div>
</x-app-layout>
```

- [ ] **Step 5: Tulis test**

```php
<?php
// tests/Feature/Admin/AttendanceConfigurationControllerTest.php

use App\Domains\Sdm\Models\AttendanceMethodConfiguration;
use App\Domains\Sdm\Models\AttendancePoint;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

if (! function_exists('actingAsAdminSdm')) {
    function actingAsAdminSdm(Lembaga $lembaga): User
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

it('lets an admin_sdm enable the qr method for their own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = actingAsAdminSdm($lembaga);

    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.konfigurasi.metode'), [
        'method' => 'qr', 'is_enabled' => '1',
    ])->assertRedirect();

    expect(AttendanceMethodConfiguration::where('lembaga_id', $lembaga->id)->where('method', 'qr')->first()?->is_enabled)->toBeTrue();
});

it('lets an admin_sdm add an attendance point for their own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = actingAsAdminSdm($lembaga);

    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.titik.store'), ['nama' => 'Gerbang Utama'])
        ->assertRedirect();

    expect(AttendancePoint::where('lembaga_id', $lembaga->id)->where('nama', 'Gerbang Utama')->exists())->toBeTrue();
});

it('rejects an admin without kehadiran-sdm.kelola-konfigurasi permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $noPermissionUser = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($noPermissionUser)->post(route('admin.kehadiran-sdm.titik.store'), ['nama' => 'Gerbang Utama'])
        ->assertForbidden();
});

it('shows the yayasan-level default method configuration (lembaga_id null) to a lembaga-scoped admin_sdm', function () {
    // Regression guard: TenantScope forces `lembaga_id = actingUser->lembaga_id` as a
    // top-level AND for a scope_level:lembaga actor, so a naive query would never surface
    // the yayasan default row (lembaga_id IS NULL) no matter what OR clause is added on top.
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = actingAsAdminSdm($lembaga);

    AttendanceMethodConfiguration::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => null, 'method' => 'qr', 'is_enabled' => true,
    ]);

    $this->actingAs($admin)->get(route('admin.kehadiran-sdm.konfigurasi.index'))
        ->assertOk()
        ->assertViewHas('konfigurasi', function ($konfigurasi) {
            return $konfigurasi->contains(fn ($row) => $row->method->value === 'qr' && $row->lembaga_id === null && $row->is_enabled === true);
        });
});
```

- [ ] **Step 6: Jalankan test**

Run: `php artisan test tests/Feature/Admin/AttendanceConfigurationControllerTest.php`
Expected: 4 passed, 0 failed.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/AttendanceConfigurationController.php routes/admin/kehadiran-sdm.php routes/admin.php resources/views/admin/kehadiran-sdm/konfigurasi.blade.php tests/Feature/Admin/AttendanceConfigurationControllerTest.php
git commit -m "feat(sdm): tambah AttendanceConfigurationController (metode + titik absen) dan view-nya"
```

---

## Task 8: `AttendanceController` (Input Manual + Daftar Kehadiran) + Routes + Views

**Files:**
- Create: `app/Http/Controllers/Admin/AttendanceController.php`
- Modify: `routes/admin/kehadiran-sdm.php`
- Create: `resources/views/admin/kehadiran-sdm/index.blade.php`
- Create: `resources/views/admin/kehadiran-sdm/create.blade.php`
- Create: `resources/js/attendance-manual-form.js`
- Modify: `resources/js/app.js`
- Test: `tests/Feature/Admin/AttendanceControllerTest.php`

**Interfaces:**
- Consumes: `RecordManualAttendanceAction` (Task 4), `AttendanceRecord`/`AttendancePoint` model (Task 2).
- Produces: route `admin.kehadiran-sdm.index` (GET, daftar record harian), `admin.kehadiran-sdm.create` (GET, form input), `admin.kehadiran-sdm.store` (POST) — endpoint utama admin mencatat kehadiran manual.

- [ ] **Step 1: Buat `AttendanceController`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Sdm\Actions\RecordManualAttendanceAction;
use App\Domains\Sdm\DataTransferObjects\RecordManualAttendanceData;
use App\Domains\Sdm\Enums\AttendanceStatus;
use App\Domains\Sdm\Models\AttendancePoint;
use App\Domains\Sdm\Models\AttendanceRecord;
use App\Models\Guru;
use App\Models\Karyawan;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class AttendanceController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('kehadiran-sdm.view');

        $tanggal = $request->query('tanggal', now()->toDateString());

        $recordList = AttendanceRecord::with('pegawai')
            ->whereDate('tanggal', $tanggal)
            ->orderBy('tanggal', 'desc')
            ->get();

        return view('admin.kehadiran-sdm.index', [
            'recordList' => $recordList,
            'tanggal' => $tanggal,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('kehadiran-sdm.catat');

        $lembagaId = $this->resolveLembagaId($request);

        $guruList = $lembagaId ? Guru::where('lembaga_id', $lembagaId)->orderBy('nama')->get(['id', 'nama']) : collect();
        $karyawanList = $lembagaId ? Karyawan::where('lembaga_id', $lembagaId)->orderBy('nama')->get(['id', 'nama']) : collect();
        $titikAbsen = $lembagaId ? AttendancePoint::where('lembaga_id', $lembagaId)->where('is_active', true)->orderBy('nama')->get() : collect();

        return view('admin.kehadiran-sdm.create', [
            'guruList' => $guruList,
            'karyawanList' => $karyawanList,
            'titikAbsen' => $titikAbsen,
            'statusOptions' => AttendanceStatus::cases(),
        ]);
    }

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

    private function resolveLembagaId(Request $request): ?int
    {
        if ($request->user()->widestScopeLevel() === 'yayasan') {
            return session('active_lembaga_id');
        }

        return $request->user()->lembaga_id;
    }
}
```

- [ ] **Step 2: Tambah 3 route ke `routes/admin/kehadiran-sdm.php`**

Tambahkan di AWAL file (sebelum route `kehadiran-sdm/konfigurasi`), setelah baris `use Illuminate\Support\Facades\Route;`:

```php
use App\Http\Controllers\Admin\AttendanceController;

Route::get('kehadiran-sdm', [AttendanceController::class, 'index'])->name('kehadiran-sdm.index');
Route::get('kehadiran-sdm/catat', [AttendanceController::class, 'create'])->name('kehadiran-sdm.create');
Route::post('kehadiran-sdm', [AttendanceController::class, 'store'])->name('kehadiran-sdm.store');
```

File lengkap setelah perubahan:

```php
<?php

use App\Http\Controllers\Admin\AttendanceConfigurationController;
use App\Http\Controllers\Admin\AttendanceController;
use Illuminate\Support\Facades\Route;

Route::get('kehadiran-sdm', [AttendanceController::class, 'index'])->name('kehadiran-sdm.index');
Route::get('kehadiran-sdm/catat', [AttendanceController::class, 'create'])->name('kehadiran-sdm.create');
Route::post('kehadiran-sdm', [AttendanceController::class, 'store'])->name('kehadiran-sdm.store');

Route::get('kehadiran-sdm/konfigurasi', [AttendanceConfigurationController::class, 'index'])->name('kehadiran-sdm.konfigurasi.index');
Route::post('kehadiran-sdm/konfigurasi/metode', [AttendanceConfigurationController::class, 'updateMetode'])->name('kehadiran-sdm.konfigurasi.metode');
Route::post('kehadiran-sdm/konfigurasi/titik', [AttendanceConfigurationController::class, 'storeTitik'])->name('kehadiran-sdm.titik.store');
Route::put('kehadiran-sdm/konfigurasi/titik/{titik}', [AttendanceConfigurationController::class, 'updateTitik'])->name('kehadiran-sdm.titik.update');
Route::delete('kehadiran-sdm/konfigurasi/titik/{titik}', [AttendanceConfigurationController::class, 'destroyTitik'])->name('kehadiran-sdm.titik.destroy');
```

- [ ] **Step 3: Buat `resources/js/attendance-manual-form.js`**

Dropdown pemilihan pegawai bisa berisi puluhan nama per lembaga — WAJIB pakai Tom Select (searchable), bukan native select polos, konsisten pola yang sudah dipakai `resources/js/karyawan-form.js`.

```js
import TomSelect from 'tom-select';

export function attendanceManualForm() {
    return {
        pegawaiTipe: 'guru',

        initSelect(el) {
            new TomSelect(el, { maxItems: 1, create: false, allowEmptyOption: true, controlInput: null });
        },
    };
}
```

- [ ] **Step 4: Registrasikan di `resources/js/app.js`**

Cari baris:

```js
import { karyawanForm } from './karyawan-form';
```

Tambahkan setelahnya:

```js
import { attendanceManualForm } from './attendance-manual-form';
```

Cari baris:

```js
Alpine.data('karyawanForm', karyawanForm);
```

Tambahkan setelahnya:

```js
Alpine.data('attendanceManualForm', attendanceManualForm);
```

- [ ] **Step 5: Buat view `resources/views/admin/kehadiran-sdm/index.blade.php`**

```blade
<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">SDM &amp; Kepegawaian</p>
                <h1 class="mt-0.5 font-display text-xl font-bold tracking-tight text-gray-900">Kehadiran SDM</h1>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.kehadiran-sdm.konfigurasi.index') }}" class="text-xs font-semibold text-gray-500 hover:text-gray-700">Konfigurasi</a>
                @can('kehadiran-sdm.catat')
                    <a href="{{ route('admin.kehadiran-sdm.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600 active:scale-[0.98]">+ Catat Kehadiran</a>
                @endcan
            </div>
        </div>

        <form method="GET" class="flex items-center gap-3">
            <label class="text-xs font-semibold text-gray-600">Tanggal</label>
            <input type="date" name="tanggal" value="{{ $tanggal }}" onchange="this.form.submit()" class="rounded-lg border-gray-200 text-sm">
        </form>

        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-card">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        <th class="px-5 py-3">Pegawai</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Masuk</th>
                        <th class="px-5 py-3">Pulang</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($recordList as $record)
                        <tr>
                            <td class="px-5 py-3 font-semibold text-gray-900">{{ $record->pegawai->nama ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center rounded-full bg-{{ $record->status->badgeTone() }}-100 px-2.5 py-1 text-xs font-semibold text-{{ $record->status->badgeTone() }}-800">
                                    {{ $record->status->label() }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-gray-600">{{ $record->waktu_masuk?->format('H:i') ?? '—' }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $record->waktu_pulang?->format('H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-12 text-center text-sm text-gray-400">Belum ada data kehadiran pada tanggal ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Buat view `resources/views/admin/kehadiran-sdm/create.blade.php`**

```blade
<x-app-layout>
    <div class="mx-auto max-w-2xl space-y-6" x-data="attendanceManualForm()">
        @if ($errors->any())
            <div class="rounded-xl border border-rose-100 bg-rose-50/50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif

        <div>
            <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">SDM &amp; Kepegawaian</p>
            <h1 class="mt-0.5 font-display text-xl font-bold tracking-tight text-gray-900">Catat Kehadiran Manual</h1>
        </div>

        <form method="POST" action="{{ route('admin.kehadiran-sdm.store') }}" class="space-y-5 rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
            @csrf

            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jenis Pegawai</label>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 text-sm"><input type="radio" name="pegawai_tipe" value="guru" x-model="pegawaiTipe" checked> Guru</label>
                    <label class="flex items-center gap-2 text-sm"><input type="radio" name="pegawai_tipe" value="karyawan" x-model="pegawaiTipe"> Karyawan</label>
                </div>
            </div>

            <div x-show="pegawaiTipe === 'guru'">
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Nama Guru</label>
                <select name="pegawai_id" x-ref="guruSelect" x-init="initSelect($refs.guruSelect)" class="w-full rounded-lg border-gray-200 text-sm">
                    <option value="">Pilih guru...</option>
                    @foreach ($guruList as $guru)
                        <option value="{{ $guru->id }}">{{ $guru->nama }}</option>
                    @endforeach
                </select>
            </div>

            <div x-show="pegawaiTipe === 'karyawan'" x-cloak>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Nama Karyawan</label>
                <select name="pegawai_id" x-ref="karyawanSelect" x-init="initSelect($refs.karyawanSelect)" class="w-full rounded-lg border-gray-200 text-sm">
                    <option value="">Pilih karyawan...</option>
                    @foreach ($karyawanList as $karyawan)
                        <option value="{{ $karyawan->id }}">{{ $karyawan->nama }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Arah</label>
                    <select name="arah" required class="w-full rounded-lg border-gray-200 text-sm">
                        <option value="masuk">Masuk</option>
                        <option value="pulang">Pulang</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Status</label>
                    <select name="status" required class="w-full rounded-lg border-gray-200 text-sm">
                        @foreach ($statusOptions as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Waktu</label>
                <input type="datetime-local" name="waktu" required value="{{ now()->format('Y-m-d\TH:i') }}" class="w-full rounded-lg border-gray-200 text-sm">
            </div>

            @if ($titikAbsen->isNotEmpty())
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Titik Absen (opsional)</label>
                    <select name="attendance_point_id" class="w-full rounded-lg border-gray-200 text-sm">
                        <option value="">—</option>
                        @foreach ($titikAbsen as $titik)
                            <option value="{{ $titik->id }}">{{ $titik->nama }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Catatan (opsional)</label>
                <textarea name="catatan" rows="2" class="w-full rounded-lg border-gray-200 text-sm"></textarea>
            </div>

            <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                <a href="{{ route('admin.kehadiran-sdm.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">Batal</a>
                <x-primary-button type="submit">Simpan Kehadiran</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
```

- [ ] **Step 7: Tulis test**

```php
<?php
// tests/Feature/Admin/AttendanceControllerTest.php

use App\Domains\Sdm\Models\AttendanceRecord;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

if (! function_exists('actingAsAdminSdmCatat')) {
    function actingAsAdminSdmCatat(Lembaga $lembaga): User
    {
        foreach (['kehadiran-sdm.view', 'kehadiran-sdm.catat'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $role = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
        $role->givePermissionTo(['kehadiran-sdm.view', 'kehadiran-sdm.catat']);

        $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $user->assignRole($role);

        return $user;
    }
}

it('lets an admin_sdm record manual attendance for a guru in their own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin = actingAsAdminSdmCatat($lembaga);

    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.store'), [
        'pegawai_tipe' => 'guru',
        'pegawai_id' => $guru->id,
        'arah' => 'masuk',
        'status' => 'hadir',
        'waktu' => now()->format('Y-m-d H:i:s'),
    ])->assertRedirect(route('admin.kehadiran-sdm.index'));

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeTrue();
});

it('404s when recording attendance for a guru from a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guruLembagaB = Guru::factory()->create(['lembaga_id' => $lembagaB->id]);
    $adminLembagaA = actingAsAdminSdmCatat($lembagaA);

    $this->actingAs($adminLembagaA)->post(route('admin.kehadiran-sdm.store'), [
        'pegawai_tipe' => 'guru',
        'pegawai_id' => $guruLembagaB->id,
        'arah' => 'masuk',
        'status' => 'hadir',
        'waktu' => now()->format('Y-m-d H:i:s'),
    ])->assertNotFound();

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guruLembagaB->id)->exists())->toBeFalse();
});

it('rejects an admin without kehadiran-sdm.catat permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $noPermissionUser = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($noPermissionUser)->post(route('admin.kehadiran-sdm.store'), [
        'pegawai_tipe' => 'guru', 'pegawai_id' => $guru->id, 'arah' => 'masuk', 'status' => 'hadir', 'waktu' => now()->format('Y-m-d H:i:s'),
    ])->assertForbidden();
});
```

- [ ] **Step 8: Build asset dan jalankan test**

Run: `npm run build`
Expected: build sukses tanpa error (memverifikasi `attendance-manual-form.js` valid dan ter-bundle).

Run: `php artisan test tests/Feature/Admin/AttendanceControllerTest.php`
Expected: 3 passed, 0 failed.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Admin/AttendanceController.php routes/admin/kehadiran-sdm.php resources/views/admin/kehadiran-sdm/index.blade.php resources/views/admin/kehadiran-sdm/create.blade.php resources/js/attendance-manual-form.js resources/js/app.js tests/Feature/Admin/AttendanceControllerTest.php
git commit -m "feat(sdm): tambah AttendanceController (input manual + daftar kehadiran harian) dan view-nya"
```

---

## Task 9: `AttendanceQrScanController` + `EmployeeQrCodeController` + Routes + Views

**Files:**
- Create: `app/Http/Controllers/Admin/AttendanceQrScanController.php`
- Create: `app/Http/Controllers/EmployeeQrCodeController.php`
- Modify: `routes/admin/kehadiran-sdm.php`
- Create: `routes/sdm.php`
- Modify: `routes/web.php`
- Create: `resources/views/admin/kehadiran-sdm/scan.blade.php`
- Create: `resources/views/sdm/qr-saya.blade.php`
- Test: `tests/Feature/Admin/AttendanceQrScanControllerTest.php`
- Test: `tests/Feature/Sdm/EmployeeQrCodeControllerTest.php`

**Interfaces:**
- Consumes: `ScanQrAttendanceAction` (Task 6), `GenerateEmployeeQrTokenAction` (Task 5).
- Produces: route `admin.kehadiran-sdm.scan.index` (GET halaman scan), `admin.kehadiran-sdm.scan.store` (POST hasil scan); route `sdm.qr-saya` (GET, self-view QR pegawai).

- [ ] **Step 1: Buat `AttendanceQrScanController`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Sdm\Actions\ScanQrAttendanceAction;
use App\Domains\Sdm\DataTransferObjects\ScanQrAttendanceData;
use App\Domains\Sdm\Exceptions\InvalidQrTokenException;
use App\Domains\Sdm\Exceptions\QrTokenLembagaMismatchException;
use App\Domains\Sdm\Models\AttendancePoint;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class AttendanceQrScanController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('kehadiran-sdm.catat');

        $lembagaId = $this->resolveLembagaId($request);
        $titikAbsen = $lembagaId ? AttendancePoint::where('lembaga_id', $lembagaId)->where('is_active', true)->orderBy('nama')->get() : collect();

        return view('admin.kehadiran-sdm.scan', ['titikAbsen' => $titikAbsen]);
    }

    public function store(Request $request, ScanQrAttendanceAction $action): JsonResponse
    {
        $this->authorize('kehadiran-sdm.catat');

        $data = $request->validate([
            'token' => ['required', 'string'],
            'arah' => ['required', 'in:masuk,pulang'],
            'attendance_point_id' => ['nullable', 'integer', 'exists:attendance_points,id'],
        ]);

        $lembagaId = $this->resolveLembagaId($request);

        if ($lembagaId === null) {
            return response()->json(['message' => 'Pilih lembaga aktif melalui pengalih lembaga terlebih dahulu.'], 422);
        }

        try {
            $event = $action->execute(new ScanQrAttendanceData(
                token: $data['token'],
                arah: $data['arah'],
                lembagaId: $lembagaId,
                dicatatOlehUserId: $request->user()->id,
                attendancePointId: $data['attendance_point_id'] ?? null,
            ));
        } catch (InvalidQrTokenException|QrTokenLembagaMismatchException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Kehadiran berhasil dicatat: '.$event->pegawai->nama,
        ]);
    }

    private function resolveLembagaId(Request $request): ?int
    {
        if ($request->user()->widestScopeLevel() === 'yayasan') {
            return session('active_lembaga_id');
        }

        return $request->user()->lembaga_id;
    }
}
```

- [ ] **Step 2: Buat `EmployeeQrCodeController`**

Controller ini untuk halaman self-view — pegawai (guru/karyawan yang punya akun) melihat QR miliknya sendiri. Resolusi pegawai dari `auth()->user()`: cek `Guru::where('user_id', ...)` dulu, kalau tidak ada cek `Karyawan::where('user_id', ...)`.

```php
<?php

namespace App\Http\Controllers;

use App\Domains\Sdm\Actions\GenerateEmployeeQrTokenAction;
use App\Models\Guru;
use App\Models\Karyawan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class EmployeeQrCodeController extends BaseController
{
    use AuthorizesRequests;

    public function show(Request $request): View
    {
        $this->authorize('kehadiran-sdm.lihat-qr-sendiri');

        $pegawai = $this->resolvePegawai($request);
        abort_if($pegawai === null, 404, 'Data kepegawaian Anda tidak ditemukan.');

        return view('sdm.qr-saya', ['pegawai' => $pegawai, 'qrCode' => $pegawai->employeeQrCode]);
    }

    public function generate(Request $request, GenerateEmployeeQrTokenAction $action): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.lihat-qr-sendiri');

        $pegawai = $this->resolvePegawai($request);
        abort_if($pegawai === null, 404, 'Data kepegawaian Anda tidak ditemukan.');

        $action->execute($pegawai);

        return back()->with('status', 'QR kehadiran Anda berhasil diperbarui.');
    }

    private function resolvePegawai(Request $request): Guru|Karyawan|null
    {
        $userId = $request->user()->id;

        return Guru::where('user_id', $userId)->first() ?? Karyawan::where('user_id', $userId)->first();
    }
}
```

- [ ] **Step 3: Tambah 2 route scan ke `routes/admin/kehadiran-sdm.php`**

Cari baris:

```php
Route::post('kehadiran-sdm', [AttendanceController::class, 'store'])->name('kehadiran-sdm.store');
```

Tambahkan setelahnya:

```php
Route::get('kehadiran-sdm/scan', [\App\Http\Controllers\Admin\AttendanceQrScanController::class, 'index'])->name('kehadiran-sdm.scan.index');
Route::post('kehadiran-sdm/scan', [\App\Http\Controllers\Admin\AttendanceQrScanController::class, 'store'])->name('kehadiran-sdm.scan.store');
```

- [ ] **Step 4: Buat `routes/sdm.php`**

```php
<?php

use App\Http\Controllers\EmployeeQrCodeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('sdm')->name('sdm.')->group(function () {
    Route::get('qr-saya', [EmployeeQrCodeController::class, 'show'])->name('qr-saya');
    Route::post('qr-saya/generate', [EmployeeQrCodeController::class, 'generate'])->name('qr-saya.generate');
});
```

- [ ] **Step 5: Registrasikan di `routes/web.php`**

Cari baris:

```php
require __DIR__.'/spmb.php';
require __DIR__.'/portal.php';
```

Ganti jadi:

```php
require __DIR__.'/spmb.php';
require __DIR__.'/portal.php';
require __DIR__.'/sdm.php';
```

- [ ] **Step 6: Buat view `resources/views/admin/kehadiran-sdm/scan.blade.php`**

Halaman scan pakai library kamera browser sederhana lewat input manual token untuk MVP (petugas mengetik/scan token via scanner hardware yang berperilaku seperti keyboard input — pola paling umum untuk scanner QR fisik di kios, tidak butuh akses kamera JS). Ini konsisten dengan cakupan spec §2 ("Petugas login akun admin biasa, buka halaman scan").

```blade
<x-app-layout>
    <div class="mx-auto max-w-lg space-y-6" x-data="{
        arah: 'masuk',
        token: '',
        loading: false,
        message: null,
        messageType: 'success',
        async submitScan() {
            if (!this.token.trim()) return;
            this.loading = true;
            this.message = null;
            try {
                const response = await fetch('{{ route('admin.kehadiran-sdm.scan.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content'),
                    },
                    body: JSON.stringify({ token: this.token, arah: this.arah }),
                });
                const data = await response.json();
                this.message = data.message;
                this.messageType = response.ok ? 'success' : 'error';
            } catch (e) {
                this.message = 'Gagal menghubungi server.';
                this.messageType = 'error';
            } finally {
                this.token = '';
                this.loading = false;
                this.$nextTick(() => this.$refs.tokenInput.focus());
            }
        }
    }">
        <div>
            <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">SDM &amp; Kepegawaian</p>
            <h1 class="mt-0.5 font-display text-xl font-bold tracking-tight text-gray-900">Scan Kehadiran QR</h1>
            <p class="mt-1 text-sm text-gray-500">Arahkan scanner QR ke kode pegawai, atau ketik token secara manual.</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
            <div class="mb-4 flex gap-4">
                <label class="flex items-center gap-2 text-sm"><input type="radio" x-model="arah" value="masuk" checked> Masuk</label>
                <label class="flex items-center gap-2 text-sm"><input type="radio" x-model="arah" value="pulang"> Pulang</label>
            </div>

            <form @submit.prevent="submitScan()">
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Token QR</label>
                <input x-ref="tokenInput" x-model="token" type="text" autofocus placeholder="Scan atau ketik token..." class="w-full rounded-lg border-gray-200 text-sm">
                <button type="submit" x-bind:disabled="loading" class="mt-4 w-full rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-600 disabled:opacity-50">
                    <span x-text="loading ? 'Memproses...' : 'Catat Kehadiran'"></span>
                </button>
            </form>

            <template x-if="message">
                <p class="mt-4 rounded-lg p-3 text-sm" :class="messageType === 'success' ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800'" x-text="message"></p>
            </template>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 7: Buat view `resources/views/sdm/qr-saya.blade.php`**

QR ditampilkan sebagai teks token (di dalam kotak monospace) untuk MVP — generate gambar QR visual (butuh library rendering QR) di luar cakupan sub-project ini; token inilah yang di-scan/diketik petugas di halaman scan.

```blade
<x-app-layout>
    <div class="mx-auto max-w-md space-y-6">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <div>
            <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">Kehadiran Saya</p>
            <h1 class="mt-0.5 font-display text-xl font-bold tracking-tight text-gray-900">QR Kehadiran Saya</h1>
            <p class="mt-1 text-sm text-gray-500">Tunjukkan kode ini ke petugas untuk dicatat kehadiran Anda.</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 text-center shadow-card">
            @if ($qrCode)
                <p class="break-all rounded-lg bg-gray-50 p-4 font-mono text-sm text-gray-800">{{ $qrCode->token }}</p>
                <p class="mt-3 text-xs text-gray-400">Kode ini unik untuk {{ $pegawai->nama }} dan berlaku sampai Anda meminta perubahan baru.</p>
            @else
                <p class="text-sm text-gray-500">Anda belum memiliki QR kehadiran.</p>
            @endif

            <form method="POST" action="{{ route('sdm.qr-saya.generate') }}" class="mt-4" onsubmit="return confirm('{{ $qrCode ? 'Kode lama akan langsung tidak berlaku. Lanjutkan?' : 'Buat QR kehadiran baru?' }}')">
                @csrf
                <button type="submit" class="w-full rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    {{ $qrCode ? 'Buat Ulang QR' : 'Buat QR Kehadiran' }}
                </button>
            </form>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 8: Tulis test scan controller**

```php
<?php
// tests/Feature/Admin/AttendanceQrScanControllerTest.php

use App\Domains\Sdm\Actions\GenerateEmployeeQrTokenAction;
use App\Domains\Sdm\Models\AttendanceRecord;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

if (! function_exists('actingAsPetugasScan')) {
    function actingAsPetugasScan(Lembaga $lembaga): User
    {
        Permission::firstOrCreate(['name' => 'kehadiran-sdm.catat', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
        $role->givePermissionTo(['kehadiran-sdm.catat']);

        $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $user->assignRole($role);

        return $user;
    }
}

it('records attendance via qr scan endpoint for an employee in the same lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $petugas = actingAsPetugasScan($lembaga);
    $qr = app(GenerateEmployeeQrTokenAction::class)->execute($guru);

    $this->actingAs($petugas)->postJson(route('admin.kehadiran-sdm.scan.store'), [
        'token' => $qr->token, 'arah' => 'masuk',
    ])->assertOk();

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeTrue();
});

it('returns a 422 json error for an invalid token via the scan endpoint', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $petugas = actingAsPetugasScan($lembaga);

    $this->actingAs($petugas)->postJson(route('admin.kehadiran-sdm.scan.store'), [
        'token' => 'tidak-ada', 'arah' => 'masuk',
    ])->assertStatus(422)->assertJson(['message' => 'QR tidak valid atau sudah tidak aktif.']);
});
```

- [ ] **Step 9: Tulis test employee QR controller**

```php
<?php
// tests/Feature/Sdm/EmployeeQrCodeControllerTest.php

use App\Domains\Sdm\Models\EmployeeQrCode;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('lets a guru view and generate their own qr code', function () {
    Permission::firstOrCreate(['name' => 'kehadiran-sdm.lihat-qr-sendiri', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('kehadiran-sdm.lihat-qr-sendiri');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $user->id]);

    $this->actingAs($user)->get(route('sdm.qr-saya'))
        ->assertOk()
        ->assertSee('Anda belum memiliki QR kehadiran');

    $this->actingAs($user)->post(route('sdm.qr-saya.generate'))->assertRedirect();

    expect(EmployeeQrCode::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->where('is_active', true)->exists())->toBeTrue();
});

it('rejects a user without kehadiran-sdm.lihat-qr-sendiri permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->get(route('sdm.qr-saya'))->assertForbidden();
});
```

- [ ] **Step 10: Jalankan kedua test**

Run: `php artisan test tests/Feature/Admin/AttendanceQrScanControllerTest.php tests/Feature/Sdm/EmployeeQrCodeControllerTest.php`
Expected: 4 passed, 0 failed.

- [ ] **Step 11: Commit**

```bash
git add app/Http/Controllers/Admin/AttendanceQrScanController.php app/Http/Controllers/EmployeeQrCodeController.php routes/admin/kehadiran-sdm.php routes/sdm.php routes/web.php resources/views/admin/kehadiran-sdm/scan.blade.php resources/views/sdm/qr-saya.blade.php tests/Feature/Admin/AttendanceQrScanControllerTest.php tests/Feature/Sdm/EmployeeQrCodeControllerTest.php
git commit -m "feat(sdm): tambah AttendanceQrScanController dan EmployeeQrCodeController (self-view QR) + view"
```

---

## Task 10: Verifikasi Akhir + Full Test Suite (Butuh Izin User)

**Files:** Tidak ada file baru — task ini murni verifikasi.

**Interfaces:** N/A (gate akhir).

- [ ] **Step 1: Grep ulang untuk memastikan tidak ada hardcode role**

Run: `grep -rn "hasRole('admin_sdm')\|hasRole(\"admin_sdm\")" app/`
Expected: tidak ada hasil (kosong). Kalau ada hasil, itu pelanggaran §6 spec — perbaiki sebelum lanjut (ganti jadi `$this->authorize('kehadiran-sdm.xxx')`).

- [ ] **Step 2: Jalankan seluruh test scoped domain SDM sekali lagi bersama-sama**

Run: `php artisan test tests/Feature/Sdm tests/Feature/Admin/AttendanceConfigurationControllerTest.php tests/Feature/Admin/AttendanceControllerTest.php tests/Feature/Admin/AttendanceQrScanControllerTest.php`
Expected: semua test dari Task 2-9 hijau bersama-sama (total ≥ 25 test), 0 failed.

- [ ] **Step 3: MINTA IZIN EKSPLISIT USER sebelum lanjut ke Step 4**

Tampilkan pesan ke user: "Semua test scoped Kehadiran SDM Sub-project 1 sudah hijau. Boleh saya jalankan full test suite sekarang?" — TUNGGU jawaban eksplisit sebelum menjalankan Step 4. JANGAN otomatis lanjut.

- [ ] **Step 4: (Setelah izin diberikan) Jalankan full test suite**

Run: `php artisan test`
Expected: 0 failed, 0 error. Total test harus ≥ 1904 (baseline sebelumnya) + jumlah test baru dari Task 2-9 (kurang lebih 25).

Catatan: kalau ada test GAGAL yang TIDAK terkait Kehadiran SDM sama sekali, ada riwayat flaky test pre-existing di branch ini (`KomponenPenilaianCrudTest`, `RaporPdfDataBuilderTest`) — jalankan ulang test yang gagal SENDIRIAN dulu untuk konfirmasi sebelum melaporkan sebagai regresi baru.

- [ ] **Step 5: Tulis handoff log**

Buat file `.agents/logs/2026-08-22-sdm-01-fondasi-kehadiran-admin-qr.md` berisi: ringkasan per task (1-10), commit hash tiap task, hasil verifikasi akhir dengan angka pasti (jumlah test passed, waktu eksekusi), dan daftar deviasi (kalau ada) dari plan ini yang ditemukan saat eksekusi nyata.

- [ ] **Step 6: Commit handoff log**

```bash
git add .agents/logs/2026-08-22-sdm-01-fondasi-kehadiran-admin-qr.md
git commit -m "docs(sdm): handoff log Sub-project 1 Kehadiran SDM (fondasi + admin manual + QR statis)"
```

---

## Self-Review (dilakukan penulis plan, bukan executor)

**Spec coverage**: §4 (5 tabel) → Task 1. §2/§6 RBAC → Task 3. §7.1 → Task 4. §4.6 QR generation → Task 5. §7.2 → Task 6. §3 4 controller → Task 7-9. §9 testing (tenant isolation, aggregator, QR lintas-lembaga, RBAC) → tersebar di tiap task terkait. Semua requirement spec punya task yang mengimplementasikannya.

**Placeholder scan**: tidak ada TBD/TODO, semua kode lengkap per step.

**Type consistency**: `RecordManualAttendanceData`/`ScanQrAttendanceData` dipakai identik di Action (Task 4/6) dan Controller (Task 8/9) — nama properti dan tipe sama persis di semua pemakaian. `AttendanceRecordAggregator::sync()` signature konsisten dipanggil dari `RecordManualAttendanceAction` dan `ScanQrAttendanceAction`.
