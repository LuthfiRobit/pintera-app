# Kehadiran SDM Sub-project 4 (Terakhir) — Izin/Cuti Berjenjang — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bangun alur pengajuan izin/sakit/cuti mandiri oleh Guru/Karyawan, approval berjenjang 2 lapis (Kepala Sekolah → Admin SDM) via reuse total `App\Domains\Workflow`, otomatis catat `AttendanceEvent` saat disetujui, dan integrasi ke `TandaiAlpaOtomatisSdm` supaya pengajuan pending tidak keliru ditandai Alpa.

**Architecture:** Domain `App\Domains\Sdm\` diperluas: `Models\PengajuanIzinCuti`, `Enums\KategoriPengajuanIzin`, `Actions\AjukanIzinCutiAction`, `Actions\ProsesApprovalIzinCutiAction`, `Actions\BatalkanPengajuanIzinCutiAction`. `App\Domains\Workflow` (SHARED, dipakai Rapor Akademik & Pengadaan Sarpras) diperluas MURNI ADITIF: `ApprovalStatus::Cancelled`, `ApprovalAction::Cancel`. `App\Domains\Sdm\Enums\AttendanceStatus` diperluas MURNI ADITIF: `Cuti`. `TandaiAlpaOtomatisSdm` (Sub-project 2/3/3b) dapat 1 filter tambahan.

**Tech Stack:** Laravel 12, PHP 8.2+, Pest (test), Tailwind + Alpine.js — sama seperti Sub-project 1-3b.

## Global Constraints

- Branch kerja: `sdm-v1`. JANGAN buat branch baru, JANGAN buat worktree.
- **Framework: Laravel 12** (bukan 11 — cek `composer.json` kalau ragu, `"laravel/framework": "^12.0"`). Semua API yang dipakai plan ini kompatibel Laravel 12.
- Baseline: commit `d5df314` di branch `sdm-v1` (spec Sub-project 4 baru dikomit). Kalau ada commit baru masuk sebelum eksekusi, verifikasi ulang file yang dikutip plan ini — terutama `app/Domains/Workflow/` (SELURUH isi), `database/seeders/WorkflowDefinitionSeeder.php`, `app/Console/Commands/TandaiAlpaOtomatisSdm.php`, `app/Domains/Sdm/Enums/AttendanceStatus.php`, `database/seeders/RoleSeeder.php`, `database/seeders/PermissionSeeder.php`.
- Spec lengkap: `.agents/specs/2026-08-22-sdm-04-izin-cuti-berjenjang.md` — baca dulu untuk "kenapa", plan ini "bagaimana"-nya.
- **`App\Domains\Workflow\Enums\ApprovalStatus` dan `App\Domains\Workflow\Enums\ApprovalAction` HANYA BOLEH ditambah 1 case baru masing-masing** (`Cancelled`/`Cancel`) — TIDAK ADA perubahan lain ke file itu maupun ke `WorkflowDefinition.php`, `WorkflowStep.php`, `ApprovalRequest.php`, `ApprovalLog.php`, `ApproverResolverService.php`, `InitializeApprovalRequestAction.php`, `ProcessApprovalAction.php`. File-file ini dipakai Rapor Akademik & Pengadaan Sarpras — TIDAK BOLEH ada perubahan LOGIC di dalamnya, HANYA penambahan case enum.
- **`ShiftAwareAttendanceResolver.php`, `AttendancePolicyResolver.php`, `KalenderKerjaSdmResolver.php` TIDAK disentuh sama sekali** di plan ini (tidak relevan dengan sub-project ini, tapi tetap tegaskan supaya tidak tergoda).
- **JANGAN meniru pola `App\Http\Controllers\Yayasan\Pengadaan\ApprovalPengadaanController.php`** kalau kamu membacanya sebagai referensi — file itu punya 1 baris `$user->hasRole(['super_admin', 'yayasan_super_admin'])` (hardcode role) yang BERTENTANGAN dengan disiplin modul SDM ini. Controller baru di plan ini WAJIB pakai `$this->authorize('kehadiran-sdm.izin.xxx')` SAJA, TIDAK ADA `hasRole()` di manapun.
- Controller SDM baru mengikuti konvensi SDM sendiri yang SUDAH established (namespace `App\Http\Controllers\Admin\` untuk approval, `App\Http\Controllers\` top-level untuk self-service — pola sama `EmployeeQrCodeController` Sub-project 1), BUKAN pola `App\Http\Controllers\Yayasan\...` + `TenantContext` service yang dipakai Pengadaan.
- TIDAK ADA hardcode nama role apapun.
- TIDAK membangun kuota/saldo cuti — di luar cakupan.
- Testing policy: test scoped per task, dijalankan SEBELUM commit setiap task. Full suite HANYA di Task 10, dan HANYA setelah izin eksplisit user. **Task 10 WAJIB juga menjalankan test suite Rapor Akademik DAN Pengadaan Sarpras secara scoped** (bukan cuma full suite di akhir) — ini satu-satunya sub-project SDM yang menyentuh file benar-benar shared lintas domain.
- Satu commit per task, pesan commit sesuai yang ditentukan di tiap task Step terakhir.
- Test framework: Pest, gaya `it('...', function () { ... })`.

---

## Task 1: Migrasi + Enum (`KategoriPengajuanIzin`, `AttendanceStatus::Cuti`, `ApprovalStatus::Cancelled`, `ApprovalAction::Cancel`)

**Files:**
- Create: `database/migrations/2026_08_22_130000_create_pengajuan_izin_cuti_table.php`
- Create: `app/Domains/Sdm/Enums/KategoriPengajuanIzin.php`
- Modify: `app/Domains/Sdm/Enums/AttendanceStatus.php`
- Modify: `app/Domains/Workflow/Enums/ApprovalStatus.php`
- Modify: `app/Domains/Workflow/Enums/ApprovalAction.php`

**Interfaces:**
- Produces: tabel `pengajuan_izin_cuti`; enum `KategoriPengajuanIzin` (`Izin`, `Sakit`, `Cuti`) dengan method `toAttendanceStatus(): AttendanceStatus`; `AttendanceStatus::Cuti`; `ApprovalStatus::Cancelled`; `ApprovalAction::Cancel` — dipakai Task 2 dst.

- [ ] **Step 1: Buat migrasi `pengajuan_izin_cuti`**

```php
<?php
// database/migrations/2026_08_22_130000_create_pengajuan_izin_cuti_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_izin_cuti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->morphs('pegawai');
            $table->string('kategori');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->text('alasan');
            $table->timestamps();

            $table->index(['pegawai_type', 'pegawai_id', 'tanggal_mulai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_izin_cuti');
    }
};
```

- [ ] **Step 2: Jalankan migrasi dan verifikasi**

Run: `php artisan migrate`
Expected: migrasi baru berjalan sukses.

- [ ] **Step 3: Buat enum `KategoriPengajuanIzin`**

```php
<?php

namespace App\Domains\Sdm\Enums;

enum KategoriPengajuanIzin: string
{
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Cuti = 'cuti';

    public function label(): string
    {
        return match ($this) {
            self::Izin => 'Izin',
            self::Sakit => 'Sakit',
            self::Cuti => 'Cuti',
        };
    }

    public function toAttendanceStatus(): AttendanceStatus
    {
        return match ($this) {
            self::Izin => AttendanceStatus::Izin,
            self::Sakit => AttendanceStatus::Sakit,
            self::Cuti => AttendanceStatus::Cuti,
        };
    }
}
```

- [ ] **Step 4: Tambah case `Cuti` ke `AttendanceStatus`**

Baca dulu isi file saat ini. Ganti SELURUH isinya jadi:

```php
<?php

namespace App\Domains\Sdm\Enums;

enum AttendanceStatus: string
{
    case Hadir = 'hadir';
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Alpa = 'alpa';
    case Cuti = 'cuti';

    public function label(): string
    {
        return match ($this) {
            self::Hadir => 'Hadir',
            self::Izin => 'Izin',
            self::Sakit => 'Sakit',
            self::Alpa => 'Alpa',
            self::Cuti => 'Cuti',
        };
    }

    public function badgeTone(): string
    {
        return match ($this) {
            self::Hadir => 'green',
            self::Izin => 'blue',
            self::Sakit => 'amber',
            self::Alpa => 'red',
            self::Cuti => 'indigo',
        };
    }
}
```

- [ ] **Step 5: Tambah case `Cancelled` ke `ApprovalStatus` (SHARED — HANYA tambah case, TIDAK UBAH APAPUN LAIN)**

Baca dulu isi file saat ini untuk pastikan baseline cocok (harus persis seperti dikutip di spec §2.2 sebelum diedit). Cari baris:

```php
    case RevisionRequired = 'revision_required';
```

Tambahkan SETELAHNYA:

```php
    case Cancelled = 'cancelled';
```

Cari method `label()`, tambahkan baris baru di dalam `match`:

```php
            self::RevisionRequired => 'Perlu Revisi',
```

Ganti jadi:

```php
            self::RevisionRequired => 'Perlu Revisi',
            self::Cancelled => 'Dibatalkan',
```

Cari method `badgeTone()`, tambahkan baris baru di dalam `match`:

```php
            self::RevisionRequired => 'amber',
```

Ganti jadi:

```php
            self::RevisionRequired => 'amber',
            self::Cancelled => 'slate',
```

- [ ] **Step 6: Tambah case `Cancel` ke `ApprovalAction` (SHARED — HANYA tambah case)**

Baca dulu isi file saat ini. Cari baris:

```php
    case RequestRevision = 'REQUEST_REVISION';
```

Tambahkan SETELAHNYA:

```php
    case Cancel = 'CANCEL';
```

Cari method `label()`, tambahkan baris baru:

```php
            self::RequestRevision => 'Minta Revisi',
```

Ganti jadi:

```php
            self::RequestRevision => 'Minta Revisi',
            self::Cancel => 'Dibatalkan',
```

- [ ] **Step 7: Verifikasi lewat tinker — PENTING, buktikan kedua enum shared TIDAK melempar `UnhandledMatchError` untuk SEMUA case (termasuk case lama milik Rapor/Pengadaan)**

Run:
```
php artisan tinker --execute="
foreach (App\Domains\Workflow\Enums\ApprovalStatus::cases() as \$c) { echo \$c->value.': '.\$c->label().' / '.\$c->badgeTone().PHP_EOL; }
foreach (App\Domains\Workflow\Enums\ApprovalAction::cases() as \$c) { echo \$c->value.': '.\$c->label().PHP_EOL; }
foreach (App\Domains\Sdm\Enums\AttendanceStatus::cases() as \$c) { echo \$c->value.': '.\$c->label().' / '.\$c->badgeTone().PHP_EOL; }
"
```
Expected: SEMUA case (lama maupun baru) tercetak tanpa error PHP apapun.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_22_130000_create_pengajuan_izin_cuti_table.php app/Domains/Sdm/Enums/KategoriPengajuanIzin.php app/Domains/Sdm/Enums/AttendanceStatus.php app/Domains/Workflow/Enums/ApprovalStatus.php app/Domains/Workflow/Enums/ApprovalAction.php
git commit -m "feat(sdm): migrasi pengajuan_izin_cuti, enum KategoriPengajuanIzin, tambah case Cuti/Cancelled/Cancel (murni aditif)"
```

---

## Task 2: Model `PengajuanIzinCuti` + Relasi

**Files:**
- Create: `app/Domains/Sdm/Models/PengajuanIzinCuti.php`
- Modify: `app/Models/Guru.php`
- Modify: `app/Models/Karyawan.php`
- Test: `tests/Feature/Sdm/PengajuanIzinCutiModelTest.php`

**Interfaces:**
- Produces: `PengajuanIzinCuti::create([...])`; `$pegawai->pengajuanIzinCuti()` (morphMany, Guru & Karyawan); `PengajuanIzinCuti::approvalRequest()` (morphOne ke `ApprovalRequest`) — dipakai Task 4 dst.

- [ ] **Step 1: Buat model `PengajuanIzinCuti`**

```php
<?php

namespace App\Domains\Sdm\Models;

use App\Domains\Sdm\Enums\KategoriPengajuanIzin;
use App\Domains\Workflow\Models\ApprovalRequest;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PengajuanIzinCuti extends Model
{
    use BelongsToTenant;

    protected $table = 'pengajuan_izin_cuti';

    protected $fillable = ['lembaga_id', 'pegawai_type', 'pegawai_id', 'kategori', 'tanggal_mulai', 'tanggal_selesai', 'alasan'];

    protected function casts(): array
    {
        return [
            'kategori' => KategoriPengajuanIzin::class,
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
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

    public function approvalRequest(): MorphOne
    {
        return $this->morphOne(ApprovalRequest::class, 'approvable');
    }
}
```

- [ ] **Step 2: Tambah relasi `pengajuanIzinCuti()` di `app/Models/Guru.php`**

Cari blok `use` (setelah `use App\Domains\Sdm\Models\PenugasanShift;` yang sudah ada dari Sub-project 3b), tambahkan:

```php
use App\Domains\Sdm\Models\PengajuanIzinCuti;
```

Tambahkan method baru setelah `penugasanShift()`:

```php
    public function pengajuanIzinCuti(): MorphMany
    {
        return $this->morphMany(PengajuanIzinCuti::class, 'pegawai');
    }
```

- [ ] **Step 3: Tambah relasi `pengajuanIzinCuti()` di `app/Models/Karyawan.php`**

Pola sama persis — tambahkan `use App\Domains\Sdm\Models\PengajuanIzinCuti;` dan method `pengajuanIzinCuti(): MorphMany` setelah `penugasanShift()`.

- [ ] **Step 4: Tulis test**

```php
<?php
// tests/Feature/Sdm/PengajuanIzinCutiModelTest.php

use App\Domains\Sdm\Enums\KategoriPengajuanIzin;
use App\Domains\Sdm\Models\PengajuanIzinCuti;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Yayasan;

it('creates a pengajuan izin cuti for a guru via the morph relation', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $pengajuan = $guru->pengajuanIzinCuti()->create([
        'lembaga_id' => $lembaga->id, 'kategori' => KategoriPengajuanIzin::Sakit,
        'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-09-02', 'alasan' => 'Demam tinggi.',
    ]);

    expect($pengajuan->pegawai_type)->toBe(Guru::class);
    expect($pengajuan->kategori)->toBe(KategoriPengajuanIzin::Sakit);
    expect($guru->pengajuanIzinCuti()->count())->toBe(1);
});

it('maps each kategori to the correct AttendanceStatus', function () {
    expect(KategoriPengajuanIzin::Izin->toAttendanceStatus())->toBe(\App\Domains\Sdm\Enums\AttendanceStatus::Izin);
    expect(KategoriPengajuanIzin::Sakit->toAttendanceStatus())->toBe(\App\Domains\Sdm\Enums\AttendanceStatus::Sakit);
    expect(KategoriPengajuanIzin::Cuti->toAttendanceStatus())->toBe(\App\Domains\Sdm\Enums\AttendanceStatus::Cuti);
});
```

- [ ] **Step 5: Jalankan test**

Run: `php artisan test tests/Feature/Sdm/PengajuanIzinCutiModelTest.php`
Expected: 2 passed, 0 failed.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Sdm/Models/PengajuanIzinCuti.php app/Models/Guru.php app/Models/Karyawan.php tests/Feature/Sdm/PengajuanIzinCutiModelTest.php
git commit -m "feat(sdm): tambah model PengajuanIzinCuti + relasi morphMany di Guru/Karyawan"
```

---

## Task 3: Workflow Seed + RBAC

**Files:**
- Modify: `database/seeders/WorkflowDefinitionSeeder.php`
- Modify: `database/seeders/PermissionSeeder.php`
- Modify: `database/seeders/RoleSeeder.php`
- Test: `tests/Feature/Sdm/IzinCutiWorkflowSeedTest.php`

**Interfaces:**
- Produces: `WorkflowDefinition` code `IZIN_CUTI_SDM` (2 step: `kepala_sekolah` → `admin_sdm`); permission `kehadiran-sdm.izin.ajukan`, `kehadiran-sdm.izin.approve`, `kehadiran-sdm.izin.lihat-sendiri` — dipakai Task 8-9.

- [ ] **Step 1: Tambah blok `IZIN_CUTI_SDM` ke `WorkflowDefinitionSeeder.php`**

Baca dulu isi file saat ini (harus persis seperti dikutip spec §7, sudah punya blok `PENGADAAN_SARPRAS` dan `RAPOR_SEMESTER`). Tambahkan blok baru di AKHIR method `run()`, SEBELUM `}` penutup method:

```php
        // 3. Workflow Izin/Cuti SDM
        $izinCuti = WorkflowDefinition::updateOrCreate(
            ['code' => 'IZIN_CUTI_SDM'],
            [
                'nama_workflow' => 'Izin/Cuti Pegawai',
                'deskripsi' => 'Alur persetujuan pengajuan izin/sakit/cuti mandiri oleh guru dan karyawan.',
                'is_active' => true,
            ]
        );

        $this->assertRoleExists('kepala_sekolah');
        WorkflowStep::updateOrCreate(
            ['workflow_definition_id' => $izinCuti->id, 'step_number' => 1],
            [
                'step_name' => 'Verifikasi Kepala Sekolah',
                'approver_type' => ApproverType::Role,
                'approver_value' => 'kepala_sekolah',
                'scope_level' => 'lembaga',
                'is_final_step' => false,
            ]
        );

        $this->assertRoleExists('admin_sdm');
        WorkflowStep::updateOrCreate(
            ['workflow_definition_id' => $izinCuti->id, 'step_number' => 2],
            [
                'step_name' => 'Persetujuan Admin SDM',
                'approver_type' => ApproverType::Role,
                'approver_value' => 'admin_sdm',
                'scope_level' => 'lembaga',
                'is_final_step' => true,
            ]
        );
```

- [ ] **Step 2: Tambah 3 permission baru ke `PermissionSeeder.php`**

Cari baris terakhir array `$permissions` (baris terakhir sebelum `];`, saat ini `'kehadiran-sdm.view', 'kehadiran-sdm.catat', 'kehadiran-sdm.kelola-konfigurasi', 'kehadiran-sdm.lihat-qr-sendiri',`). Tambahkan SETELAHNYA:

```php
            'kehadiran-sdm.izin.ajukan', 'kehadiran-sdm.izin.approve', 'kehadiran-sdm.izin.lihat-sendiri',
```

- [ ] **Step 3: Assign permission baru ke role terkait di `RoleSeeder.php`**

Cari blok `guru`:

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

Ganti jadi:

```php
            if ($name === 'guru') {
                $role->givePermissionTo([
                    'presensi.isi', 'asesmen.kelola', 'komponen-penilaian.kelola-sendiri', 'rapor.input-wali', 'rapor.ajukan',
                    'kasus.ajukan', 'kasus.view',
                    'rpp.view', 'rpp.kelola',
                    'kehadiran-sdm.lihat-qr-sendiri', 'kehadiran-sdm.izin.ajukan', 'kehadiran-sdm.izin.lihat-sendiri',
                ]);
            }
```

Cari blok `karyawan_pool`/`karyawan_lembaga`:

```php
            if (in_array($name, ['karyawan_pool', 'karyawan_lembaga'], true)) {
                $role->givePermissionTo(['kasus.view', 'kehadiran-sdm.lihat-qr-sendiri']);
            }
```

Ganti jadi:

```php
            if (in_array($name, ['karyawan_pool', 'karyawan_lembaga'], true)) {
                $role->givePermissionTo(['kasus.view', 'kehadiran-sdm.lihat-qr-sendiri', 'kehadiran-sdm.izin.ajukan', 'kehadiran-sdm.izin.lihat-sendiri']);
            }
```

Cari blok `kepala_sekolah`:

```php
            if ($name === 'kepala_sekolah') {
                $role->givePermissionTo([
                    'spmb-pendaftaran.view', 'spmb-pendaftaran.verifikasi-dokumen', 'spmb-pendaftaran.nilai-seleksi',
                    'spmb-pendaftaran.tetapkan-keputusan', 'spmb-pendaftaran.terbitkan-sk',
                    'tagihan.view',
                    'komponen-penilaian.kelola', 'rapor.view', 'rapor.approve',
                    'kenaikan-kelas.kelola',
                    'rpp.view', 'rpp.verify',
                ]);
            }
```

Ganti jadi (tambah `'kehadiran-sdm.izin.approve'`):

```php
            if ($name === 'kepala_sekolah') {
                $role->givePermissionTo([
                    'spmb-pendaftaran.view', 'spmb-pendaftaran.verifikasi-dokumen', 'spmb-pendaftaran.nilai-seleksi',
                    'spmb-pendaftaran.tetapkan-keputusan', 'spmb-pendaftaran.terbitkan-sk',
                    'tagihan.view',
                    'komponen-penilaian.kelola', 'rapor.view', 'rapor.approve',
                    'kenaikan-kelas.kelola',
                    'rpp.view', 'rpp.verify',
                    'kehadiran-sdm.izin.approve',
                ]);
            }
```

Cari blok `admin_sdm`:

```php
            if ($name === 'admin_sdm') {
                $role->givePermissionTo([
                    'kehadiran-sdm.view', 'kehadiran-sdm.catat', 'kehadiran-sdm.kelola-konfigurasi', 'kehadiran-sdm.lihat-qr-sendiri',
                ]);
            }
```

Ganti jadi:

```php
            if ($name === 'admin_sdm') {
                $role->givePermissionTo([
                    'kehadiran-sdm.view', 'kehadiran-sdm.catat', 'kehadiran-sdm.kelola-konfigurasi', 'kehadiran-sdm.lihat-qr-sendiri',
                    'kehadiran-sdm.izin.approve',
                ]);
            }
```

- [ ] **Step 4: Tulis test**

```php
<?php
// tests/Feature/Sdm/IzinCutiWorkflowSeedTest.php

use App\Domains\Workflow\Enums\ApproverType;
use App\Domains\Workflow\Models\WorkflowDefinition;
use App\Models\Role;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;

it('seeds the IZIN_CUTI_SDM workflow with 2 role-based steps', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\WorkflowDefinitionSeeder']);

    $definition = WorkflowDefinition::where('code', 'IZIN_CUTI_SDM')->first();
    expect($definition)->not->toBeNull();
    expect($definition->is_active)->toBeTrue();

    $steps = $definition->steps;
    expect($steps)->toHaveCount(2);
    expect($steps[0]->approver_type)->toBe(ApproverType::Role);
    expect($steps[0]->approver_value)->toBe('kepala_sekolah');
    expect($steps[0]->is_final_step)->toBeFalse();
    expect($steps[1]->approver_value)->toBe('admin_sdm');
    expect($steps[1]->is_final_step)->toBeTrue();
});

it('seeds the 3 kehadiran-sdm.izin.* permissions and grants them to the right roles', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);

    foreach (['kehadiran-sdm.izin.ajukan', 'kehadiran-sdm.izin.approve', 'kehadiran-sdm.izin.lihat-sendiri'] as $permission) {
        expect(Permission::where('name', $permission)->exists())->toBeTrue();
    }

    expect(Role::where('name', 'guru')->first()->hasPermissionTo('kehadiran-sdm.izin.ajukan'))->toBeTrue();
    expect(Role::where('name', 'karyawan_lembaga')->first()->hasPermissionTo('kehadiran-sdm.izin.ajukan'))->toBeTrue();
    expect(Role::where('name', 'kepala_sekolah')->first()->hasPermissionTo('kehadiran-sdm.izin.approve'))->toBeTrue();
    expect(Role::where('name', 'admin_sdm')->first()->hasPermissionTo('kehadiran-sdm.izin.approve'))->toBeTrue();
});
```

- [ ] **Step 5: Jalankan test**

Run: `php artisan test tests/Feature/Sdm/IzinCutiWorkflowSeedTest.php`
Expected: 2 passed, 0 failed.

- [ ] **Step 6: Commit**

```bash
git add database/seeders/WorkflowDefinitionSeeder.php database/seeders/PermissionSeeder.php database/seeders/RoleSeeder.php tests/Feature/Sdm/IzinCutiWorkflowSeedTest.php
git commit -m "feat(sdm): seed workflow IZIN_CUTI_SDM (2 step role-based) + 3 permission kehadiran-sdm.izin.*"
```

---

## Task 4: `AjukanIzinCutiAction`

**Files:**
- Create: `app/Domains/Sdm/Actions/AjukanIzinCutiAction.php`
- Test: `tests/Feature/Sdm/AjukanIzinCutiActionTest.php`

**Interfaces:**
- Consumes: `InitializeApprovalRequestAction` (Workflow domain, TIDAK diubah).
- Produces: `AjukanIzinCutiAction::execute(Model $pegawai, KategoriPengajuanIzin $kategori, string $tanggalMulai, string $tanggalSelesai, string $alasan): PengajuanIzinCuti` — dipakai Task 8 (controller self-service).

- [ ] **Step 1: Buat `AjukanIzinCutiAction`**

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

- [ ] **Step 2: Tulis test**

```php
<?php
// tests/Feature/Sdm/AjukanIzinCutiActionTest.php

use App\Domains\Sdm\Actions\AjukanIzinCutiAction;
use App\Domains\Sdm\Enums\KategoriPengajuanIzin;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Yayasan;
use Illuminate\Support\Facades\Artisan;

it('creates a pengajuan and initializes a pending approval request at step 1', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\WorkflowDefinitionSeeder']);
    Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $pengajuan = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Sakit, '2026-09-01', '2026-09-02', 'Demam.');

    expect($pengajuan->pegawai_id)->toBe($guru->id);
    $approvalRequest = $pengajuan->approvalRequest;
    expect($approvalRequest)->not->toBeNull();
    expect($approvalRequest->status)->toBe(ApprovalStatus::Pending);
    expect($approvalRequest->currentStep->step_name)->toBe('Verifikasi Kepala Sekolah');
});

it('rejects a pengajuan where tanggal_mulai is after tanggal_selesai', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    expect(fn () => app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Cuti, '2026-09-10', '2026-09-05', 'Cuti.'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});
```

- [ ] **Step 3: Jalankan test**

Run: `php artisan test tests/Feature/Sdm/AjukanIzinCutiActionTest.php`
Expected: 2 passed, 0 failed.

- [ ] **Step 4: Commit**

```bash
git add app/Domains/Sdm/Actions/AjukanIzinCutiAction.php tests/Feature/Sdm/AjukanIzinCutiActionTest.php
git commit -m "feat(sdm): tambah AjukanIzinCutiAction"
```

---

## Task 5: `ProsesApprovalIzinCutiAction`

**Files:**
- Create: `app/Domains/Sdm/Actions/ProsesApprovalIzinCutiAction.php`
- Test: `tests/Feature/Sdm/ProsesApprovalIzinCutiActionTest.php`

**Interfaces:**
- Consumes: `ProcessApprovalAction` (Workflow domain, TIDAK diubah), `AttendanceRecordAggregator` (Sub-project 1/3b).
- Produces: `ProsesApprovalIzinCutiAction::execute(PengajuanIzinCuti $pengajuan, User $user, ApprovalAction $action, ?string $notes = null): void` — dipakai Task 9 (controller approval).

- [ ] **Step 1: Buat `ProsesApprovalIzinCutiAction`**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions;

use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Domains\Sdm\Models\PengajuanIzinCuti;
use App\Domains\Sdm\Services\AttendanceRecordAggregator;
use App\Domains\Workflow\Actions\ProcessApprovalAction;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProsesApprovalIzinCutiAction
{
    public function __construct(
        private readonly ProcessApprovalAction $workflowAction,
        private readonly AttendanceRecordAggregator $aggregator,
    ) {}

    public function execute(PengajuanIzinCuti $pengajuan, User $user, ApprovalAction $action, ?string $notes = null): void
    {
        $approvalRequest = $pengajuan->approvalRequest;

        if (! $approvalRequest) {
            throw ValidationException::withMessages([
                'approval' => 'Pengajuan ini tidak memiliki alur persetujuan aktif.',
            ]);
        }

        DB::transaction(function () use ($pengajuan, $approvalRequest, $user, $action, $notes) {
            $this->workflowAction->execute($approvalRequest, $user, $action, $notes);
            $approvalRequest->refresh();

            if ($approvalRequest->status === ApprovalStatus::Approved) {
                $pegawai = $pengajuan->pegawai;
                $status = $pengajuan->kategori->toAttendanceStatus();

                $tanggal = $pengajuan->tanggal_mulai->toImmutable();
                while ($tanggal->lessThanOrEqualTo($pengajuan->tanggal_selesai)) {
                    $pegawai->attendanceEvents()->create([
                        'lembaga_id' => $pengajuan->lembaga_id,
                        'method' => AttendanceMethod::System,
                        'arah' => 'masuk',
                        'status' => $status,
                        'waktu' => $tanggal->setTime(23, 59),
                        'dicatat_oleh_user_id' => null,
                        'catatan' => 'Disetujui via pengajuan izin/cuti #'.$pengajuan->id,
                    ]);

                    $this->aggregator->sync($pegawai, $tanggal);
                    $tanggal = $tanggal->addDay();
                }
            }
        });
    }
}
```

- [ ] **Step 2: Tulis test**

```php
<?php
// tests/Feature/Sdm/ProsesApprovalIzinCutiActionTest.php

use App\Domains\Sdm\Actions\AjukanIzinCutiAction;
use App\Domains\Sdm\Actions\ProsesApprovalIzinCutiAction;
use App\Domains\Sdm\Enums\AttendanceStatus;
use App\Domains\Sdm\Enums\KategoriPengajuanIzin;
use App\Domains\Sdm\Models\AttendanceRecord;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Support\Facades\Artisan;

function seedIzinCutiWorkflowForTest(): void
{
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\WorkflowDefinitionSeeder']);
}

it('moves to step 2 and does not create any AttendanceEvent after step-1 approval', function () {
    seedIzinCutiWorkflowForTest();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $kepsekRole = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $kepsek = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $kepsek->assignRole($kepsekRole);
    $pengajuan = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Sakit, '2026-09-01', '2026-09-01', 'Demam.');

    app(ProsesApprovalIzinCutiAction::class)->execute($pengajuan, $kepsek, ApprovalAction::Approve);

    $pengajuan->refresh();
    expect($pengajuan->approvalRequest->status)->toBe(ApprovalStatus::InReview);
    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeFalse();
});

it('creates one AttendanceEvent per day in range with the correct status after final-step approval', function () {
    seedIzinCutiWorkflowForTest();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $kepsekRole = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $adminSdmRole = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $kepsek = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $kepsek->assignRole($kepsekRole);
    $adminSdm = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $adminSdm->assignRole($adminSdmRole);
    $pengajuan = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Cuti, '2026-09-01', '2026-09-03', 'Acara keluarga.');
    app(ProsesApprovalIzinCutiAction::class)->execute($pengajuan, $kepsek, ApprovalAction::Approve);

    app(ProsesApprovalIzinCutiAction::class)->execute($pengajuan, $adminSdm, ApprovalAction::Approve);

    $pengajuan->refresh();
    expect($pengajuan->approvalRequest->status)->toBe(ApprovalStatus::Approved);
    $records = AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->orderBy('tanggal')->get();
    expect($records)->toHaveCount(3);
    foreach ($records as $record) {
        expect($record->status)->toBe(AttendanceStatus::Cuti);
    }
});

it('creates no AttendanceEvent when rejected', function () {
    seedIzinCutiWorkflowForTest();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $kepsekRole = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $kepsek = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $kepsek->assignRole($kepsekRole);
    $pengajuan = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Izin, '2026-09-01', '2026-09-01', 'Keperluan pribadi.');

    app(ProsesApprovalIzinCutiAction::class)->execute($pengajuan, $kepsek, ApprovalAction::Reject, 'Tidak disetujui.');

    $pengajuan->refresh();
    expect($pengajuan->approvalRequest->status)->toBe(ApprovalStatus::Rejected);
    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeFalse();
});
```

- [ ] **Step 3: Jalankan test**

Run: `php artisan test tests/Feature/Sdm/ProsesApprovalIzinCutiActionTest.php`
Expected: 3 passed, 0 failed.

- [ ] **Step 4: Commit**

```bash
git add app/Domains/Sdm/Actions/ProsesApprovalIzinCutiAction.php tests/Feature/Sdm/ProsesApprovalIzinCutiActionTest.php
git commit -m "feat(sdm): tambah ProsesApprovalIzinCutiAction (generate AttendanceEvent saat Approved penuh)"
```

---

## Task 6: `BatalkanPengajuanIzinCutiAction`

**Files:**
- Create: `app/Domains/Sdm/Actions/BatalkanPengajuanIzinCutiAction.php`
- Test: `tests/Feature/Sdm/BatalkanPengajuanIzinCutiActionTest.php`

**Interfaces:**
- Produces: `BatalkanPengajuanIzinCutiAction::execute(PengajuanIzinCuti $pengajuan, User $user): void` — dipakai Task 8.

- [ ] **Step 1: Buat `BatalkanPengajuanIzinCutiAction`**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions;

use App\Domains\Sdm\Models\PengajuanIzinCuti;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Domains\Workflow\Models\ApprovalLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BatalkanPengajuanIzinCutiAction
{
    public function execute(PengajuanIzinCuti $pengajuan, User $user): void
    {
        $approvalRequest = $pengajuan->approvalRequest;

        if (! $approvalRequest) {
            throw ValidationException::withMessages(['approval' => 'Pengajuan ini tidak memiliki alur persetujuan aktif.']);
        }

        if (! in_array($approvalRequest->status, [ApprovalStatus::Pending, ApprovalStatus::InReview], true)) {
            throw ValidationException::withMessages([
                'approval' => 'Pengajuan yang sudah '.$approvalRequest->status->label().' tidak dapat dibatalkan.',
            ]);
        }

        $pegawai = $pengajuan->pegawai;

        if ((int) $pegawai->user_id !== (int) $user->id) {
            throw ValidationException::withMessages(['approval' => 'Anda hanya dapat membatalkan pengajuan Anda sendiri.']);
        }

        DB::transaction(function () use ($approvalRequest, $user) {
            ApprovalLog::create([
                'approval_request_id' => $approvalRequest->id,
                'workflow_step_id' => $approvalRequest->current_step_id,
                'user_id' => $user->id,
                'action' => ApprovalAction::Cancel,
                'notes' => 'Dibatalkan oleh pengaju.',
            ]);

            $approvalRequest->status = ApprovalStatus::Cancelled;
            $approvalRequest->save();
        });
    }
}
```

- [ ] **Step 2: Tulis test**

```php
<?php
// tests/Feature/Sdm/BatalkanPengajuanIzinCutiActionTest.php

use App\Domains\Sdm\Actions\AjukanIzinCutiAction;
use App\Domains\Sdm\Actions\BatalkanPengajuanIzinCutiAction;
use App\Domains\Sdm\Actions\ProsesApprovalIzinCutiAction;
use App\Domains\Sdm\Enums\KategoriPengajuanIzin;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Support\Facades\Artisan;

it('lets the requester cancel their own pending pengajuan', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\WorkflowDefinitionSeeder']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $user->id]);
    $pengajuan = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Cuti, '2026-09-10', '2026-09-11', 'Acara.');

    app(BatalkanPengajuanIzinCutiAction::class)->execute($pengajuan, $user);

    expect($pengajuan->approvalRequest->fresh()->status)->toBe(ApprovalStatus::Cancelled);
});

it('rejects cancelling a pengajuan that is already Approved', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\WorkflowDefinitionSeeder']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $user->id]);
    $kepsekRole = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $adminSdmRole = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $kepsek = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $kepsek->assignRole($kepsekRole);
    $adminSdm = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $adminSdm->assignRole($adminSdmRole);
    $pengajuan = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Sakit, '2026-09-01', '2026-09-01', 'Sakit.');
    app(ProsesApprovalIzinCutiAction::class)->execute($pengajuan, $kepsek, ApprovalAction::Approve);
    app(ProsesApprovalIzinCutiAction::class)->execute($pengajuan, $adminSdm, ApprovalAction::Approve);

    expect(fn () => app(BatalkanPengajuanIzinCutiAction::class)->execute($pengajuan->fresh(), $user))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('rejects cancelling someone else\'s pengajuan', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\WorkflowDefinitionSeeder']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $ownerUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $ownerUser->id]);
    $otherUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $pengajuan = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Izin, '2026-09-01', '2026-09-01', 'Keperluan.');

    expect(fn () => app(BatalkanPengajuanIzinCutiAction::class)->execute($pengajuan, $otherUser))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});
```

- [ ] **Step 3: Jalankan test**

Run: `php artisan test tests/Feature/Sdm/BatalkanPengajuanIzinCutiActionTest.php`
Expected: 3 passed, 0 failed.

- [ ] **Step 4: Commit**

```bash
git add app/Domains/Sdm/Actions/BatalkanPengajuanIzinCutiAction.php tests/Feature/Sdm/BatalkanPengajuanIzinCutiActionTest.php
git commit -m "feat(sdm): tambah BatalkanPengajuanIzinCutiAction"
```

---

## Task 7: Integrasi ke `TandaiAlpaOtomatisSdm`

**Files:**
- Modify: `app/Console/Commands/TandaiAlpaOtomatisSdm.php`
- Test: `tests/Feature/Sdm/TandaiAlpaOtomatisSdmTest.php`

**Interfaces:**
- Produces: command SKIP pegawai yang punya `PengajuanIzinCuti` berstatus Pending/InReview mencakup tanggal H-1.

- [ ] **Step 1: Tambah filter tambahan di `TandaiAlpaOtomatisSdm`**

Baca dulu isi file saat ini (harus persis seperti Sub-project 3b, dengan `->filter(fn ($pegawai) => ! $this->resolver->resolveLibur($pegawai, $tanggal)['libur'])` di `handle()`). Tambahkan `use` statement baru:

```php
use App\Domains\Sdm\Models\PengajuanIzinCuti;
use App\Domains\Workflow\Enums\ApprovalStatus;
```

Cari baris:

```php
                ->filter(fn ($pegawai) => ! $this->resolver->resolveLibur($pegawai, $tanggal)['libur']);
```

Ganti jadi:

```php
                ->filter(fn ($pegawai) => ! $this->resolver->resolveLibur($pegawai, $tanggal)['libur'])
                ->filter(fn ($pegawai) => ! $this->punyaPengajuanPending($pegawai, $tanggal));
```

Tambahkan method privat baru SETELAH `handle()`, SEBELUM `tandaiPegawaiTanpaRecord()`:

```php
    private function punyaPengajuanPending($pegawai, \Carbon\CarbonImmutable $tanggal): bool
    {
        return PengajuanIzinCuti::where('pegawai_type', $pegawai::class)
            ->where('pegawai_id', $pegawai->id)
            ->where('tanggal_mulai', '<=', $tanggal->toDateString())
            ->where('tanggal_selesai', '>=', $tanggal->toDateString())
            ->whereHas('approvalRequest', fn ($q) => $q->whereIn('status', [ApprovalStatus::Pending, ApprovalStatus::InReview]))
            ->exists();
    }
```

- [ ] **Step 2: Jalankan ulang SEMUA 9 test existing untuk pastikan tidak regresi**

Run: `php artisan test tests/Feature/Sdm/TandaiAlpaOtomatisSdmTest.php`
Expected: 9 passed, 0 failed.

- [ ] **Step 3: Tambah 2 test baru di akhir file**

```php
it('skips a pegawai whose pending pengajuan covers H-1', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-02 01:00:00')); // Wednesday, H-1 = 2026-09-01
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\WorkflowDefinitionSeeder']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => 'aktif']);
    app(\App\Domains\Sdm\Actions\AjukanIzinCutiAction::class)->execute($guru, \App\Domains\Sdm\Enums\KategoriPengajuanIzin::Sakit, '2026-09-01', '2026-09-01', 'Sakit.');

    $this->artisan('sdm:tandai-alpa-otomatis')->assertSuccessful();

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeFalse();

    Carbon::setTestNow();
});

it('marks Alpa normally once the pengajuan for that day has been rejected', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-02 01:00:00')); // Wednesday, H-1 = 2026-09-01
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\WorkflowDefinitionSeeder']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => 'aktif']);
    $kepsekRole = \App\Models\Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $kepsek = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $kepsek->assignRole($kepsekRole);
    $pengajuan = app(\App\Domains\Sdm\Actions\AjukanIzinCutiAction::class)->execute($guru, \App\Domains\Sdm\Enums\KategoriPengajuanIzin::Sakit, '2026-09-01', '2026-09-01', 'Sakit.');
    app(\App\Domains\Sdm\Actions\ProsesApprovalIzinCutiAction::class)->execute($pengajuan, $kepsek, \App\Domains\Workflow\Enums\ApprovalAction::Reject);

    $this->artisan('sdm:tandai-alpa-otomatis')->assertSuccessful();

    $record = AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->first();
    expect($record)->not->toBeNull();
    expect($record->status)->toBe(AttendanceStatus::Alpa);

    Carbon::setTestNow();
});
```

Tambahkan `use Illuminate\Support\Facades\Artisan;` ke bagian atas file kalau belum ada.

- [ ] **Step 4: Jalankan seluruh file test (9 lama + 2 baru)**

Run: `php artisan test tests/Feature/Sdm/TandaiAlpaOtomatisSdmTest.php`
Expected: 11 passed, 0 failed.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/TandaiAlpaOtomatisSdm.php tests/Feature/Sdm/TandaiAlpaOtomatisSdmTest.php
git commit -m "feat(sdm): TandaiAlpaOtomatisSdm skip pegawai dengan pengajuan izin/cuti Pending/InReview"
```

---

## Task 8: Controller Self-Service (Ajukan, Riwayat, Batalkan) + Routes + Views

**Files:**
- Create: `app/Http/Controllers/PengajuanIzinCutiController.php`
- Modify: `routes/sdm.php`
- Create: `resources/views/sdm/izin-cuti/index.blade.php`
- Create: `resources/views/sdm/izin-cuti/create.blade.php`
- Test: `tests/Feature/Sdm/PengajuanIzinCutiControllerTest.php`

**Interfaces:**
- Consumes: `AjukanIzinCutiAction` (Task 4), `BatalkanPengajuanIzinCutiAction` (Task 6).
- Produces: route `sdm.izin-cuti.index`, `.create`, `.store`, `.destroy`.

- [ ] **Step 1: Buat `PengajuanIzinCutiController`**

Pola resolusi pegawai SAMA PERSIS `EmployeeQrCodeController::resolvePegawai()` (Sub-project 1).

```php
<?php

namespace App\Http\Controllers;

use App\Domains\Sdm\Actions\AjukanIzinCutiAction;
use App\Domains\Sdm\Actions\BatalkanPengajuanIzinCutiAction;
use App\Domains\Sdm\Enums\KategoriPengajuanIzin;
use App\Domains\Sdm\Models\PengajuanIzinCuti;
use App\Models\Guru;
use App\Models\Karyawan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PengajuanIzinCutiController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('kehadiran-sdm.izin.lihat-sendiri');

        $pegawai = $this->resolvePegawai($request);
        abort_if($pegawai === null, 404, 'Data kepegawaian Anda tidak ditemukan.');

        $riwayat = $pegawai->pengajuanIzinCuti()->with('approvalRequest.currentStep')->latest('tanggal_mulai')->get();

        return view('sdm.izin-cuti.index', ['riwayat' => $riwayat]);
    }

    public function create(Request $request): View
    {
        $this->authorize('kehadiran-sdm.izin.ajukan');

        return view('sdm.izin-cuti.create', ['kategoriOptions' => KategoriPengajuanIzin::cases()]);
    }

    public function store(Request $request, AjukanIzinCutiAction $action): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.izin.ajukan');

        $data = $request->validate([
            'kategori' => ['required', 'in:izin,sakit,cuti'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date'],
            'alasan' => ['required', 'string', 'max:1000'],
        ]);

        $pegawai = $this->resolvePegawai($request);
        abort_if($pegawai === null, 404, 'Data kepegawaian Anda tidak ditemukan.');

        $action->execute(
            $pegawai,
            KategoriPengajuanIzin::from($data['kategori']),
            $data['tanggal_mulai'],
            $data['tanggal_selesai'],
            $data['alasan'],
        );

        return redirect()->route('sdm.izin-cuti.index')->with('status', 'Pengajuan berhasil dikirim, menunggu persetujuan.');
    }

    public function destroy(Request $request, PengajuanIzinCuti $izinCuti, BatalkanPengajuanIzinCutiAction $action): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.izin.lihat-sendiri');

        $action->execute($izinCuti, $request->user());

        return redirect()->route('sdm.izin-cuti.index')->with('status', 'Pengajuan berhasil dibatalkan.');
    }

    private function resolvePegawai(Request $request): Guru|Karyawan|null
    {
        $userId = $request->user()->id;

        return Guru::where('user_id', $userId)->first() ?? Karyawan::where('user_id', $userId)->first();
    }
}
```

Catatan: `PengajuanIzinCutiController extends BaseController` — tambahkan `use Illuminate\Routing\Controller as BaseController;` ke `use` statement (pola sama controller lain di app ini).

- [ ] **Step 2: Tambah 4 route ke `routes/sdm.php`**

Baca dulu isi file saat ini (sudah ada route `qr-saya` dari Sub-project 1). Tambahkan `use` dan 4 route baru:

```php
<?php

use App\Http\Controllers\EmployeeQrCodeController;
use App\Http\Controllers\PengajuanIzinCutiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('sdm')->name('sdm.')->group(function () {
    Route::get('qr-saya', [EmployeeQrCodeController::class, 'show'])->name('qr-saya');
    Route::post('qr-saya/generate', [EmployeeQrCodeController::class, 'generate'])->name('qr-saya.generate');

    Route::get('izin-cuti', [PengajuanIzinCutiController::class, 'index'])->name('izin-cuti.index');
    Route::get('izin-cuti/ajukan', [PengajuanIzinCutiController::class, 'create'])->name('izin-cuti.create');
    Route::post('izin-cuti', [PengajuanIzinCutiController::class, 'store'])->name('izin-cuti.store');
    Route::delete('izin-cuti/{izinCuti}', [PengajuanIzinCutiController::class, 'destroy'])->name('izin-cuti.destroy');
});
```

- [ ] **Step 3: Buat view `resources/views/sdm/izin-cuti/index.blade.php`**

```blade
<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-6">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-rose-100 bg-rose-50/50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif

        <div class="flex items-center justify-between">
            <div>
                <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">Kehadiran Saya</p>
                <h1 class="mt-0.5 font-display text-xl font-bold tracking-tight text-gray-900">Riwayat Izin/Cuti</h1>
            </div>
            @can('kehadiran-sdm.izin.ajukan')
                <a href="{{ route('sdm.izin-cuti.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600">+ Ajukan Baru</a>
            @endcan
        </div>

        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-card">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        <th class="px-5 py-3">Kategori</th>
                        <th class="px-5 py-3">Tanggal</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Langkah Saat Ini</th>
                        <th class="px-5 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($riwayat as $item)
                        @php $ar = $item->approvalRequest; @endphp
                        <tr>
                            <td class="px-5 py-3 font-semibold text-gray-900">{{ $item->kategori->label() }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $item->tanggal_mulai->format('d M Y') }} — {{ $item->tanggal_selesai->format('d M Y') }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center rounded-full bg-{{ $ar?->status->badgeTone() }}-100 px-2.5 py-1 text-xs font-semibold text-{{ $ar?->status->badgeTone() }}-800">
                                    {{ $ar?->status->label() ?? '—' }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-gray-600">{{ $ar?->currentStep?->step_name ?? '—' }}</td>
                            <td class="px-5 py-3">
                                @if ($ar && in_array($ar->status->value, ['pending', 'in_review'], true))
                                    <form method="POST" action="{{ route('sdm.izin-cuti.destroy', $item) }}" onsubmit="return confirm('Batalkan pengajuan ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-700">Batalkan</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center text-sm text-gray-400">Belum ada pengajuan izin/cuti.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 4: Buat view `resources/views/sdm/izin-cuti/create.blade.php`**

```blade
<x-app-layout>
    <div class="mx-auto max-w-lg space-y-6">
        @if ($errors->any())
            <div class="rounded-xl border border-rose-100 bg-rose-50/50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif

        <div>
            <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">Kehadiran Saya</p>
            <h1 class="mt-0.5 font-display text-xl font-bold tracking-tight text-gray-900">Ajukan Izin/Cuti</h1>
        </div>

        <form method="POST" action="{{ route('sdm.izin-cuti.store') }}" class="space-y-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
            @csrf
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Kategori</label>
                <select name="kategori" required class="w-full rounded-lg border-gray-200 text-sm">
                    @foreach ($kategoriOptions as $kategori)
                        <option value="{{ $kategori->value }}">{{ $kategori->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Tanggal Mulai</label>
                    <input type="date" name="tanggal_mulai" required class="w-full rounded-lg border-gray-200 text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Tanggal Selesai</label>
                    <input type="date" name="tanggal_selesai" required class="w-full rounded-lg border-gray-200 text-sm">
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Alasan</label>
                <textarea name="alasan" rows="3" required class="w-full rounded-lg border-gray-200 text-sm"></textarea>
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                <a href="{{ route('sdm.izin-cuti.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">Batal</a>
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-600">Kirim Pengajuan</button>
            </div>
        </form>
    </div>
</x-app-layout>
```

- [ ] **Step 5: Tulis test**

```php
<?php
// tests/Feature/Sdm/PengajuanIzinCutiControllerTest.php

use App\Domains\Sdm\Models\PengajuanIzinCuti;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;

it('lets a guru submit a pengajuan izin/cuti for themselves', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\WorkflowDefinitionSeeder']);
    foreach (['kehadiran-sdm.izin.ajukan', 'kehadiran-sdm.izin.lihat-sendiri'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['kehadiran-sdm.izin.ajukan', 'kehadiran-sdm.izin.lihat-sendiri']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $user->id]);

    $this->actingAs($user)->post(route('sdm.izin-cuti.store'), [
        'kategori' => 'sakit', 'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-09-01', 'alasan' => 'Demam.',
    ])->assertRedirect(route('sdm.izin-cuti.index'));

    expect(PengajuanIzinCuti::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeTrue();
});

it('lets a guru cancel their own pending pengajuan via the destroy endpoint', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\WorkflowDefinitionSeeder']);
    foreach (['kehadiran-sdm.izin.ajukan', 'kehadiran-sdm.izin.lihat-sendiri'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['kehadiran-sdm.izin.ajukan', 'kehadiran-sdm.izin.lihat-sendiri']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $user->id]);
    $pengajuan = app(\App\Domains\Sdm\Actions\AjukanIzinCutiAction::class)->execute($guru, \App\Domains\Sdm\Enums\KategoriPengajuanIzin::Cuti, '2026-09-10', '2026-09-11', 'Acara.');

    $this->actingAs($user)->delete(route('sdm.izin-cuti.destroy', $pengajuan))
        ->assertRedirect(route('sdm.izin-cuti.index'));

    expect($pengajuan->approvalRequest->fresh()->status)->toBe(\App\Domains\Workflow\Enums\ApprovalStatus::Cancelled);
});

it('rejects a user without kehadiran-sdm.izin.ajukan permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->post(route('sdm.izin-cuti.store'), [
        'kategori' => 'sakit', 'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-09-01', 'alasan' => 'Demam.',
    ])->assertForbidden();
});
```

- [ ] **Step 6: Jalankan test**

Run: `php artisan test tests/Feature/Sdm/PengajuanIzinCutiControllerTest.php`
Expected: 3 passed, 0 failed.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/PengajuanIzinCutiController.php routes/sdm.php resources/views/sdm/izin-cuti/index.blade.php resources/views/sdm/izin-cuti/create.blade.php tests/Feature/Sdm/PengajuanIzinCutiControllerTest.php
git commit -m "feat(sdm): tambah PengajuanIzinCutiController (self-service ajukan/riwayat/batalkan) + view"
```

---

## Task 9: Controller Approval (Inbox + Keputusan) + Routes + Views

**Files:**
- Create: `app/Http/Controllers/Admin/ApprovalIzinCutiController.php`
- Modify: `routes/admin/kehadiran-sdm.php`
- Create: `resources/views/admin/kehadiran-sdm/izin-cuti/index.blade.php`
- Create: `resources/views/admin/kehadiran-sdm/izin-cuti/show.blade.php`
- Test: `tests/Feature/Admin/ApprovalIzinCutiControllerTest.php`

**Interfaces:**
- Consumes: `ProsesApprovalIzinCutiAction` (Task 5).
- Produces: route `admin.kehadiran-sdm.izin-cuti.index`, `.show`, `.decision`.

- [ ] **Step 1: Buat `ApprovalIzinCutiController`**

Pola listing SAMA seperti `ApprovalPengadaanController::index()` (Pengadaan Sarpras) TAPI TANPA `TenantContext` dan TANPA `hasRole()` — cukup permission gate + `BelongsToTenant` (`PengajuanIzinCuti` sudah otomatis ter-scope lembaga aktor lewat `TenantScope`, TIDAK PERLU filter manual lembaga). Keabsahan approver-per-langkah divalidasi DI DALAM `ProcessApprovalAction` (Workflow domain, sudah ada) — controller TIDAK PERLU memanggil `canUserApprove()` sendiri di listing (mengikuti persis pola Pengadaan: siapa saja yang punya permission bisa LIHAT daftar, tapi cuma yang tepat gilirannya yang bisa BERHASIL memutuskan, divalidasi otomatis oleh Action).

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Sdm\Actions\ProsesApprovalIzinCutiAction;
use App\Domains\Sdm\Models\PengajuanIzinCuti;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Domains\Workflow\Enums\ApprovalStatus;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ApprovalIzinCutiController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('kehadiran-sdm.izin.approve');

        $daftar = PengajuanIzinCuti::with(['pegawai', 'approvalRequest.currentStep'])
            ->whereHas('approvalRequest', fn ($q) => $q->whereIn('status', [ApprovalStatus::Pending, ApprovalStatus::InReview]))
            ->latest('tanggal_mulai')
            ->get();

        return view('admin.kehadiran-sdm.izin-cuti.index', ['daftar' => $daftar]);
    }

    public function show(PengajuanIzinCuti $izinCuti): View
    {
        $this->authorize('kehadiran-sdm.izin.approve');

        $izinCuti->load(['pegawai', 'approvalRequest.currentStep', 'approvalRequest.logs.user']);

        return view('admin.kehadiran-sdm.izin-cuti.show', ['izinCuti' => $izinCuti]);
    }

    public function decision(Request $request, PengajuanIzinCuti $izinCuti, ProsesApprovalIzinCutiAction $action): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.izin.approve');

        $data = $request->validate([
            'action' => ['required', 'in:APPROVE,REJECT'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $action->execute($izinCuti, $request->user(), ApprovalAction::from($data['action']), $data['notes'] ?? null);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('admin.kehadiran-sdm.izin-cuti.index')->with('status', 'Keputusan berhasil diproses.');
    }
}
```

- [ ] **Step 2: Tambah 3 route ke `routes/admin/kehadiran-sdm.php`**

Tambahkan di AKHIR file, setelah route `kehadiran-sdm.penugasan-shift.destroy`:

```php
Route::get('kehadiran-sdm/izin-cuti', [\App\Http\Controllers\Admin\ApprovalIzinCutiController::class, 'index'])->name('kehadiran-sdm.izin-cuti.index');
Route::get('kehadiran-sdm/izin-cuti/{izinCuti}', [\App\Http\Controllers\Admin\ApprovalIzinCutiController::class, 'show'])->name('kehadiran-sdm.izin-cuti.show');
Route::post('kehadiran-sdm/izin-cuti/{izinCuti}/keputusan', [\App\Http\Controllers\Admin\ApprovalIzinCutiController::class, 'decision'])->name('kehadiran-sdm.izin-cuti.decision');
```

- [ ] **Step 3: Buat view `resources/views/admin/kehadiran-sdm/izin-cuti/index.blade.php`**

```blade
<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <div>
            <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">SDM &amp; Kepegawaian</p>
            <h1 class="mt-0.5 font-display text-xl font-bold tracking-tight text-gray-900">Persetujuan Izin/Cuti</h1>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-card">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        <th class="px-5 py-3">Pegawai</th>
                        <th class="px-5 py-3">Kategori</th>
                        <th class="px-5 py-3">Tanggal</th>
                        <th class="px-5 py-3">Langkah Saat Ini</th>
                        <th class="px-5 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($daftar as $item)
                        <tr>
                            <td class="px-5 py-3 font-semibold text-gray-900">{{ $item->pegawai->nama ?? '—' }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $item->kategori->label() }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $item->tanggal_mulai->format('d M Y') }} — {{ $item->tanggal_selesai->format('d M Y') }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $item->approvalRequest?->currentStep?->step_name ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.kehadiran-sdm.izin-cuti.show', $item) }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Review</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center text-sm text-gray-400">Tidak ada pengajuan menunggu persetujuan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 4: Buat view `resources/views/admin/kehadiran-sdm/izin-cuti/show.blade.php`**

```blade
<x-app-layout>
    <div class="mx-auto max-w-2xl space-y-6">
        @if ($errors->any())
            <div class="rounded-xl border border-rose-100 bg-rose-50/50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif

        <div>
            <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">SDM &amp; Kepegawaian</p>
            <h1 class="mt-0.5 font-display text-xl font-bold tracking-tight text-gray-900">Review Pengajuan {{ $izinCuti->pegawai->nama ?? '' }}</h1>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div><dt class="text-xs text-gray-400">Kategori</dt><dd class="font-semibold text-gray-900">{{ $izinCuti->kategori->label() }}</dd></div>
                <div><dt class="text-xs text-gray-400">Tanggal</dt><dd class="font-semibold text-gray-900">{{ $izinCuti->tanggal_mulai->format('d M Y') }} — {{ $izinCuti->tanggal_selesai->format('d M Y') }}</dd></div>
                <div class="col-span-2"><dt class="text-xs text-gray-400">Alasan</dt><dd class="text-gray-700">{{ $izinCuti->alasan }}</dd></div>
                <div><dt class="text-xs text-gray-400">Langkah Saat Ini</dt><dd class="text-gray-700">{{ $izinCuti->approvalRequest?->currentStep?->step_name ?? '—' }}</dd></div>
            </dl>

            @if ($izinCuti->approvalRequest?->logs->isNotEmpty())
                <div class="mt-4 border-t border-gray-100 pt-4">
                    <p class="text-xs font-semibold text-gray-500">Riwayat Keputusan</p>
                    <ul class="mt-2 space-y-1 text-xs text-gray-600">
                        @foreach ($izinCuti->approvalRequest->logs as $log)
                            <li>{{ $log->user->name ?? '—' }} — {{ $log->action->label() }}@if($log->notes): {{ $log->notes }}@endif</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.kehadiran-sdm.izin-cuti.decision', $izinCuti) }}" class="mt-6 space-y-4 border-t border-gray-100 pt-4">
                @csrf
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Catatan (opsional)</label>
                    <textarea name="notes" rows="2" class="w-full rounded-lg border-gray-200 text-sm"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="submit" name="action" value="REJECT" class="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-white px-4 py-2.5 text-sm font-semibold text-rose-600 hover:bg-rose-50">Tolak</button>
                    <button type="submit" name="action" value="APPROVE" class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-600">Setujui</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 5: Tulis test**

```php
<?php
// tests/Feature/Admin/ApprovalIzinCutiControllerTest.php

use App\Domains\Sdm\Actions\AjukanIzinCutiAction;
use App\Domains\Sdm\Enums\KategoriPengajuanIzin;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;

it('lets a kepala_sekolah approve a step-1 pending pengajuan via the decision endpoint', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\WorkflowDefinitionSeeder']);
    Permission::firstOrCreate(['name' => 'kehadiran-sdm.izin.approve', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('kehadiran-sdm.izin.approve');
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $kepsek = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $kepsek->assignRole($role);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $pengajuan = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Sakit, '2026-09-01', '2026-09-01', 'Sakit.');

    $this->actingAs($kepsek)->post(route('admin.kehadiran-sdm.izin-cuti.decision', $pengajuan), [
        'action' => 'APPROVE',
    ])->assertRedirect(route('admin.kehadiran-sdm.izin-cuti.index'));

    expect($pengajuan->approvalRequest->fresh()->status)->toBe(ApprovalStatus::InReview);
});

it('rejects an actor whose role cannot approve the current step (wrong turn)', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\WorkflowDefinitionSeeder']);
    Permission::firstOrCreate(['name' => 'kehadiran-sdm.izin.approve', 'guard_name' => 'web']);
    $adminSdmRole = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $adminSdmRole->givePermissionTo('kehadiran-sdm.izin.approve');
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $adminSdm = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $adminSdm->assignRole($adminSdmRole);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $pengajuan = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Sakit, '2026-09-01', '2026-09-01', 'Sakit.');

    // admin_sdm punya permission kehadiran-sdm.izin.approve, tapi step 1 butuh kepala_sekolah —
    // ProcessApprovalAction (Workflow domain) yang menolak, bukan permission gate controller.
    $this->actingAs($adminSdm)->post(route('admin.kehadiran-sdm.izin-cuti.decision', $pengajuan), [
        'action' => 'APPROVE',
    ])->assertRedirect();

    expect($pengajuan->approvalRequest->fresh()->status)->toBe(ApprovalStatus::Pending);
});

it('rejects an admin without kehadiran-sdm.izin.approve permission', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\WorkflowDefinitionSeeder']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $noPermissionUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $pengajuan = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Sakit, '2026-09-01', '2026-09-01', 'Sakit.');

    $this->actingAs($noPermissionUser)->get(route('admin.kehadiran-sdm.izin-cuti.index'))->assertForbidden();
});
```

- [ ] **Step 6: Jalankan test**

Run: `php artisan test tests/Feature/Admin/ApprovalIzinCutiControllerTest.php`
Expected: 3 passed, 0 failed.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/ApprovalIzinCutiController.php routes/admin/kehadiran-sdm.php resources/views/admin/kehadiran-sdm/izin-cuti/index.blade.php resources/views/admin/kehadiran-sdm/izin-cuti/show.blade.php tests/Feature/Admin/ApprovalIzinCutiControllerTest.php
git commit -m "feat(sdm): tambah ApprovalIzinCutiController (inbox + keputusan approval) + view"
```

---

## Task 10: Verifikasi Akhir + Regresi Rapor/Pengadaan + Full Test Suite (Butuh Izin User)

**Files:** Tidak ada file baru — task ini murni verifikasi.

- [ ] **Step 1: Grep ulang untuk memastikan tidak ada hardcode role**

Run: `grep -rn "hasRole(" app/Domains/Sdm app/Http/Controllers/Admin/ApprovalIzinCutiController.php app/Http/Controllers/PengajuanIzinCutiController.php app/Console/Commands/TandaiAlpaOtomatisSdm.php`
Expected: kosong.

- [ ] **Step 2: Grep untuk memastikan file Workflow domain LOGIC tidak berubah (hanya enum case yang boleh)**

Run: `git diff d5df314..HEAD -- app/Domains/Workflow/Models app/Domains/Workflow/Services app/Domains/Workflow/Actions`
Expected: output KOSONG (tidak ada perubahan sama sekali pada Models/Services/Actions Workflow domain — HANYA `Enums/ApprovalStatus.php` dan `Enums/ApprovalAction.php` yang boleh berubah, sudah dicek terpisah di Step 3).

- [ ] **Step 3: Verifikasi diff enum shared HANYA berisi penambahan case (tidak ada baris DIHAPUS)**

Run: `git diff d5df314..HEAD -- app/Domains/Workflow/Enums/ApprovalStatus.php app/Domains/Workflow/Enums/ApprovalAction.php`
Expected: semua baris di output diawali `+` (penambahan), TIDAK ADA baris diawali `-` (penghapusan) SELAIN baris konteks yang wajar dari penyisipan `match()`.

- [ ] **Step 4: Jalankan test suite scoped Rapor Akademik DAN Pengadaan Sarpras SECARA UTUH — WAJIB, bukan opsional**

Run: `php artisan test --filter=Rapor`
Run: `php artisan test --filter=Pengadaan`
Expected: SEMUA test kedua domain itu tetap hijau, 0 failed — pembuktian konkret bahwa penambahan case enum shared benar-benar tidak berdampak.

- [ ] **Step 5: Jalankan seluruh test scoped Sub-project 4 bersama-sama**

Run: `php artisan test tests/Feature/Sdm tests/Feature/Admin/ApprovalIzinCutiControllerTest.php`
Expected: semua test dari Task 2-9 hijau bersama-sama (total ≥ 22 test baru dari Sub-project 4, PLUS seluruh test Sub-project 1-3b di folder `tests/Feature/Sdm` tetap hijau), 0 failed.

- [ ] **Step 6: MINTA IZIN EKSPLISIT USER sebelum lanjut ke Step 7**

Tampilkan pesan ke user: "Semua test scoped Sub-project 4 sudah hijau, termasuk regresi Rapor Akademik & Pengadaan Sarpras. Boleh saya jalankan full test suite sekarang?" — TUNGGU jawaban eksplisit sebelum menjalankan Step 7.

- [ ] **Step 7: (Setelah izin diberikan) Jalankan full test suite**

Run: `php artisan test`
Expected: 0 failed, 0 error. Total test harus ≥ 2007 (baseline Sub-project 3b) + jumlah test baru Sub-project 4 (kurang lebih 22).

Catatan: kalau ada test GAGAL yang TIDAK terkait Sub-project 4 sama sekali, ada riwayat flaky test pre-existing di branch ini (`KomponenPenilaianCrudTest`, `RaporPdfDataBuilderTest`) — jalankan ulang test yang gagal SENDIRIAN dulu untuk konfirmasi sebelum melaporkan sebagai regresi.

- [ ] **Step 8: Tulis handoff log**

Buat file `.agents/logs/2026-08-22-sdm-04-izin-cuti-berjenjang.md` berisi: ringkasan per task (1-10), commit hash tiap task, hasil verifikasi akhir dengan angka pasti (TERMASUK hasil test Rapor & Pengadaan secara eksplisit), dan daftar deviasi (kalau ada) dari plan ini.

- [ ] **Step 9: Commit handoff log**

```bash
git add .agents/logs/2026-08-22-sdm-04-izin-cuti-berjenjang.md
git commit -m "docs(sdm): handoff log Sub-project 4 (terakhir) Izin/Cuti Berjenjang — MODUL KEHADIRAN SDM SELESAI TOTAL"
```

---

## Self-Review (dilakukan penulis plan, bukan executor)

**Spec coverage**: §2.1 alur bisnis → Task 4-7. §2.2 enum aditif → Task 1. §3 struktur data → Task 1-2. §4 Action → Task 4-6. §5 integrasi auto-alpa → Task 7. §6 RBAC → Task 3. §7 seed workflow → Task 3. §8 UI → Task 8-9. §9 batasan (Workflow domain shared tidak diubah logic-nya) → diverifikasi eksplisit Task 10 Step 2-3. Semua requirement spec punya task yang mengimplementasikannya.

**Placeholder scan**: tidak ada TBD/TODO, semua kode lengkap per step.

**Type consistency**: `AjukanIzinCutiAction::execute()` return `PengajuanIzinCuti` dipakai konsisten di Task 8 controller. `ProsesApprovalIzinCutiAction::execute()` signature (`PengajuanIzinCuti, User, ApprovalAction, ?string`) dipakai identik di Task 9 controller. `KategoriPengajuanIzin::toAttendanceStatus()` dipakai di `ProsesApprovalIzinCutiAction` (Task 5), diverifikasi Task 2's test sendiri.

**Regresi lintas-domain (KHUSUS sub-project ini, tidak ada di sub-project SDM sebelumnya)**: Task 10 Step 4 adalah SATU-SATUNYA task di SELURUH modul Kehadiran SDM yang mewajibkan test suite domain LAIN (Rapor Akademik, Pengadaan Sarpras) dijalankan sebagai bagian dari verifikasi wajib — bukan cuma full-suite-di-akhir yang mungkin lolos tanpa disadari kalau ada masalah tersembunyi di antara ribuan test lain. Ini proporsional dengan risiko nyata: ini SATU-SATUNYA sub-project yang menyentuh file benar-benar dipakai bersama domain lain (`ApprovalStatus`, `ApprovalAction` enum).
